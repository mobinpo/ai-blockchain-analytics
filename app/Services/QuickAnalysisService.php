<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Analysis;
use App\Models\Project;
use App\Models\User;
use App\Services\SourceCodeService;
use App\Services\SecurityAnalysisService;
use App\Jobs\AnalyzeContractJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class QuickAnalysisService
{
    public function __construct(
        private readonly SourceCodeService $sourceCodeService,
        private readonly SecurityAnalysisService $securityAnalysisService
    ) {
    }

    /**
     * Get existing analysis for a contract
     */
    public function getExistingAnalysis(string $contractAddress, string $network): ?array
    {
        $cacheKey = "quick_analysis:{$network}:{$contractAddress}";
        
        // Check cache first
        $cachedAnalysis = Cache::get($cacheKey);
        if ($cachedAnalysis) {
            Log::debug('Returning cached analysis', ['type' => gettype($cachedAnalysis), 'has_id' => isset($cachedAnalysis['id']) ? 'yes' : 'no']);
            return $cachedAnalysis;
        }

        // Check database for recent analysis (within 24 hours)
        // Note: Network filtering is handled via configuration or metadata since 
        // the analyses table is network-agnostic
        $analysis = Analysis::where('target_address', $contractAddress)
            ->where('target_type', 'contract')
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subDay())
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$analysis) {
            Log::debug('No existing analysis found in database');
            return null;
        }

        Log::debug('Found analysis in database', ['analysis_id' => $analysis->id, 'status' => $analysis->status]);

        $analysisData = [
            'id' => $analysis->id,
            'security_score' => $analysis->risk_score ?? 75,
            'critical_issues' => $analysis->critical_findings_count ?? 0,
            'high_issues' => $analysis->high_findings_count ?? 0,
            'medium_issues' => $analysis->findings_count - ($analysis->critical_findings_count ?? 0) - ($analysis->high_findings_count ?? 0),
            'functions_count' => 0, // Not tracked in current schema
            'lines_of_code' => 0, // Not tracked in current schema  
            'verified' => $analysis->verified ?? false,
            'completed_at' => $analysis->completed_at?->toISOString() ?? $analysis->updated_at?->toISOString()
        ];

        // Cache for 1 hour
        Cache::put($cacheKey, $analysisData, 3600);

        return $analysisData;
    }

    /**
     * Perform quick analysis of a contract
     */
    public function performQuickAnalysis(string $contractAddress, string $network): array
    {
        $startTime = microtime(true);

        // Check if we have source code BEFORE starting any database transactions
        try {
            $sourceData = $this->sourceCodeService->fetchSourceCode($contractAddress, $network);
        } catch (\Exception $e) {
            // Check if this might be an EOA rather than a contract
            if (str_contains($e->getMessage(), 'source code not verified') || 
                str_contains($e->getMessage(), 'Contract source code not verified')) {
                throw new \Exception('This address appears to be an EOA (wallet address) or an unverified contract');
            }
            throw $e;
        }
        
        if (!$sourceData['is_verified'] || empty($sourceData['source_code'])) {
            throw new \Exception('Contract exists but source code is not verified on ' . ucfirst($network === 'ethereum' ? 'Etherscan' : $network . 'scan'));
        }

        try {
            DB::beginTransaction();

            // Create or get anonymous user for public analyses
            $user = $this->getOrCreateAnonymousUser();

            // Create default project for quick analyses
            $project = $this->getOrCreateQuickAnalysisProject($user);

            // Create analysis record (we know source code is verified at this point)
            $analysis = Analysis::create([
                'project_id' => $project->id,
                'engine' => 'security', // Required field - type of analysis engine
                'analysis_type' => 'quick',
                'target_type' => 'contract',
                'target_address' => $contractAddress,
                'status' => 'processing',
                'started_at' => now(),
                'triggered_by' => 'manual'
            ]);

            // Perform quick security analysis
            $securityResults = $this->performQuickSecurityScan($sourceData['source_code'] ?? '');

            // Update analysis with results
            $analysis->update([
                'status' => 'completed',
                'security_score' => $securityResults['security_score'],
                'critical_issues_count' => $securityResults['critical_issues'],
                'high_issues_count' => $securityResults['high_issues'],
                'medium_issues_count' => $securityResults['medium_issues'],
                'low_issues_count' => $securityResults['low_issues'],
                'functions_count' => $securityResults['functions_count'],
                'lines_of_code' => $securityResults['lines_of_code'],
                'completed_at' => now()
            ]);

            DB::commit();

            $processingTime = round((microtime(true) - $startTime) * 1000, 2); // milliseconds

            $result = [
                'analysis_id' => $analysis->id,
                'security_score' => $securityResults['security_score'],
                'critical_issues' => $securityResults['critical_issues'],
                'high_issues' => $securityResults['high_issues'],
                'medium_issues' => $securityResults['medium_issues'],
                'functions_count' => $securityResults['functions_count'],
                'lines_of_code' => $securityResults['lines_of_code'],
                'verified' => $sourceData['verified'] ?? false,
                'processing_time' => $processingTime
            ];

            // Cache the result
            $cacheKey = "quick_analysis:{$network}:{$contractAddress}";
            Cache::put($cacheKey, $result, 3600);

            // Queue full analysis job for better results later
            AnalyzeContractJob::dispatch($contractAddress, $network, (string) $analysis->id)->delay(now()->addMinutes(1));

            return $result;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Quick analysis failed', [
                'contract_address' => $contractAddress,
                'network' => $network,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Perform quick security scan
     */
    private function performQuickSecurityScan(string $sourceCode): array
    {
        if (empty($sourceCode)) {
            return [
                'security_score' => 50,
                'critical_issues' => 0,
                'high_issues' => 0,
                'medium_issues' => 0,
                'low_issues' => 0,
                'functions_count' => 0,
                'lines_of_code' => 0
            ];
        }

        // Basic code analysis
        $lines = explode("\n", $sourceCode);
        $linesOfCode = count(array_filter($lines, fn($line) => !empty(trim($line)) && !str_starts_with(trim($line), '//')));
        
        // Count functions
        $functionsCount = preg_match_all('/function\s+\w+\s*\(/i', $sourceCode);

        // Quick vulnerability patterns
        $vulnerabilities = $this->scanForQuickVulnerabilities($sourceCode);

        // Calculate security score
        $securityScore = $this->calculateQuickSecurityScore($vulnerabilities, $linesOfCode);

        return [
            'security_score' => $securityScore,
            'critical_issues' => $vulnerabilities['critical'],
            'high_issues' => $vulnerabilities['high'],
            'medium_issues' => $vulnerabilities['medium'],
            'low_issues' => $vulnerabilities['low'],
            'functions_count' => $functionsCount,
            'lines_of_code' => $linesOfCode
        ];
    }

    /**
     * Scan for quick vulnerability patterns
     */
    private function scanForQuickVulnerabilities(string $sourceCode): array
    {
        $vulnerabilities = [
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0
        ];

        // Critical patterns
        $criticalPatterns = [
            '/tx\.origin/i' => 'tx.origin usage',
            '/selfdestruct\s*\(/i' => 'selfdestruct usage',
            '/suicide\s*\(/i' => 'suicide usage',
            '/delegatecall\s*\(/i' => 'delegatecall usage'
        ];

        // High severity patterns
        $highPatterns = [
            '/\.call\s*\(/i' => 'low-level call',
            '/\.send\s*\(/i' => 'send usage',
            '/assembly\s*\{/i' => 'inline assembly',
            '/pragma\s+solidity\s+\^/i' => 'floating pragma'
        ];

        // Medium severity patterns
        $mediumPatterns = [
            '/block\.timestamp/i' => 'timestamp dependence',
            '/block\.number/i' => 'block number dependence',
            '/block\.difficulty/i' => 'block difficulty dependence',
            '/msg\.value/i' => 'ether handling',
            '/transfer\s*\(/i' => 'transfer usage'
        ];

        // Low severity patterns
        $lowPatterns = [
            '/require\s*\(/i' => 'require statement',
            '/assert\s*\(/i' => 'assert usage',
            '/revert\s*\(/i' => 'revert usage'
        ];

        // Scan for patterns
        foreach ($criticalPatterns as $pattern => $description) {
            $vulnerabilities['critical'] += preg_match_all($pattern, $sourceCode);
        }

        foreach ($highPatterns as $pattern => $description) {
            $vulnerabilities['high'] += preg_match_all($pattern, $sourceCode);
        }

        foreach ($mediumPatterns as $pattern => $description) {
            $vulnerabilities['medium'] += preg_match_all($pattern, $sourceCode);
        }

        foreach ($lowPatterns as $pattern => $description) {
            $vulnerabilities['low'] += preg_match_all($pattern, $sourceCode);
        }

        return $vulnerabilities;
    }

    /**
     * Calculate quick security score
     */
    private function calculateQuickSecurityScore(array $vulnerabilities, int $linesOfCode): int
    {
        $baseScore = 100;

        // Deduct points for vulnerabilities
        $baseScore -= $vulnerabilities['critical'] * 20;
        $baseScore -= $vulnerabilities['high'] * 10;
        $baseScore -= $vulnerabilities['medium'] * 5;
        $baseScore -= $vulnerabilities['low'] * 1;

        // Bonus for having good practices
        $totalIssues = array_sum($vulnerabilities);
        if ($totalIssues === 0 && $linesOfCode > 50) {
            $baseScore += 10; // Bonus for clean code
        }

        // Adjust for code size
        if ($linesOfCode > 1000) {
            $baseScore -= 5; // Complexity penalty
        }

        return max(0, min(100, $baseScore));
    }

    /**
     * Get or create anonymous user for public analyses
     */
    private function getOrCreateAnonymousUser(): User
    {
        $user = User::where('email', 'anonymous@ai-blockchain-analytics.com')->first();

        if (!$user) {
            $user = User::create([
                'name' => 'Anonymous User',
                'email' => 'anonymous@ai-blockchain-analytics.com',
                'password' => bcrypt(Str::random(32)),
                'email_verified_at' => now()
            ]);
        }

        return $user;
    }

    /**
     * Get or create quick analysis project
     */
    private function getOrCreateQuickAnalysisProject(User $user): Project
    {
        $project = Project::where('user_id', $user->id)
            ->where('name', 'Quick Analysis')
            ->first();

        if (!$project) {
            $project = Project::create([
                'user_id' => $user->id,
                'name' => 'Quick Analysis',
                'description' => 'Public quick contract analyses',
                'is_public' => true
            ]);
        }

        return $project;
    }

    /**
     * Get analysis status for streaming updates
     */
    public function getAnalysisStatus(string $analysisId): ?array
    {
        $analysis = Analysis::find($analysisId);

        if (!$analysis) {
            return null;
        }

        return [
            'id' => $analysis->id,
            'status' => $analysis->status,
            'progress' => $this->calculateProgress($analysis),
            'security_score' => $analysis->security_score,
            'issues_found' => [
                'critical' => $analysis->critical_issues_count ?? 0,
                'high' => $analysis->high_issues_count ?? 0,
                'medium' => $analysis->medium_issues_count ?? 0,
                'low' => $analysis->low_issues_count ?? 0
            ],
            'created_at' => $analysis->created_at->toISOString(),
            'updated_at' => $analysis->updated_at->toISOString(),
            'completed_at' => $analysis->completed_at?->toISOString()
        ];
    }

    /**
     * Calculate analysis progress
     */
    private function calculateProgress(Analysis $analysis): int
    {
        return match ($analysis->status) {
            'pending' => 0,
            'processing' => 50,
            'completed' => 100,
            'failed' => 0,
            default => 0
        };
    }

    /**
     * Cancel ongoing analysis
     */
    public function cancelAnalysis(string $analysisId): bool
    {
        $analysis = Analysis::find($analysisId);

        if (!$analysis || $analysis->status === 'completed') {
            return false;
        }

        $analysis->update([
            'status' => 'cancelled'
        ]);

        return true;
    }
}
