<?php

namespace App\Jobs;

use App\Models\Analysis;
use App\Services\OpenAiStreamService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ProcessOpenAiAnalysis implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutes
    public int $tries = 3;
    public int $backoff = 30;

    private string $code;
    private int $analysisId;
    private string $analysisType;
    private array $options;
    private string $jobId;

    public function __construct(
        string $code,
        int $analysisId,
        string $analysisType = 'security',
        array $options = []
    ) {
        $this->code = $code;
        $this->analysisId = $analysisId;
        $this->analysisType = $analysisType;
        $this->options = $options;
        $this->jobId = Str::uuid()->toString();
        
        // Set queue based on analysis type
        $this->onQueue($this->getQueueName($analysisType));
    }

    public function handle(OpenAiStreamService $openAiService): void
    {
        Log::info("Starting OpenAI analysis job {$this->jobId} for analysis {$this->analysisId}");
        
        try {
            // Load the analysis record
            $analysis = Analysis::findOrFail($this->analysisId);
            
            // Update status to processing
            $analysis->update([
                'status' => 'processing',
                'job_id' => $this->jobId,
                'started_at' => now(),
                'metadata' => array_merge($analysis->metadata ?? [], [
                    'queue_attempts' => $this->attempts(),
                    'analysis_type' => $this->analysisType,
                    'options' => $this->options
                ])
            ]);

            // Configure OpenAI service based on options
            if (!empty($this->options)) {
                $openAiService->configure($this->options);
            }

            // Start streaming analysis
            Log::info("Starting OpenAI streaming analysis for job {$this->jobId}");
            
            $result = $openAiService->analyzeBlockchainCode(
                $this->code,
                $this->jobId,
                $this->analysisType
            );

            // Parse and structure the result
            $structuredResult = $this->parseAnalysisResult($result);

            // Update analysis with results
            $analysis->update([
                'status' => 'completed',
                'result' => $structuredResult,
                'completed_at' => now(),
                'metadata' => array_merge($analysis->metadata ?? [], [
                    'token_count' => $this->getTokenCount(),
                    'processing_time' => now()->diffInSeconds($analysis->started_at),
                    'job_id' => $this->jobId
                ])
            ]);

            // Create findings if this is a security analysis
            if ($this->analysisType === 'security' && isset($structuredResult['findings'])) {
                $this->createFindings($analysis, $structuredResult['findings']);
            }

            Log::info("OpenAI analysis job {$this->jobId} completed successfully");

        } catch (Throwable $e) {
            Log::error("OpenAI analysis job {$this->jobId} failed: " . $e->getMessage(), [
                'analysis_id' => $this->analysisId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Update analysis with error
            if (isset($analysis)) {
                $analysis->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'failed_at' => now(),
                    'metadata' => array_merge($analysis->metadata ?? [], [
                        'attempts' => $this->attempts(),
                        'last_error' => $e->getMessage()
                    ])
                ]);
            }

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error("OpenAI analysis job {$this->jobId} permanently failed after {$this->tries} attempts", [
            'analysis_id' => $this->analysisId,
            'error' => $exception->getMessage()
        ]);

        // Mark analysis as permanently failed
        try {
            $analysis = Analysis::findOrFail($this->analysisId);
            $analysis->update([
                'status' => 'failed',
                'error_message' => "Job failed after {$this->tries} attempts: " . $exception->getMessage(),
                'failed_at' => now()
            ]);
        } catch (Throwable $e) {
            Log::error("Failed to update analysis record on job failure: " . $e->getMessage());
        }
    }

    /**
     * Get unique job ID for tracking
     */
    public function getJobId(): string
    {
        return $this->jobId;
    }

    /**
     * Get queue name based on analysis type
     */
    private function getQueueName(string $analysisType): string
    {
        return match ($analysisType) {
            'security' => 'security-analysis',
            'gas' => 'gas-analysis', 
            'code_quality' => 'quality-analysis',
            default => 'default'
        };
    }

    /**
     * Parse and structure the OpenAI analysis result
     */
    private function parseAnalysisResult(string $result): array
    {
        // Try to extract structured data from the result
        $structured = [
            'raw_result' => $result,
            'summary' => '',
            'findings' => [],
            'recommendations' => [],
            'risk_score' => null
        ];

        // Extract summary (first paragraph or section)
        if (preg_match('/^(.+?)(?:\n\n|\n#{1,6}|\n\*\*)/s', trim($result), $matches)) {
            $structured['summary'] = trim($matches[1]);
        }

        // Extract findings for security analysis
        if ($this->analysisType === 'security') {
            $structured['findings'] = $this->extractSecurityFindings($result);
        }

        // Extract recommendations
        $structured['recommendations'] = $this->extractRecommendations($result);

        // Calculate risk score for security analysis
        if ($this->analysisType === 'security') {
            $structured['risk_score'] = $this->calculateRiskScore($structured['findings']);
        }

        return $structured;
    }

    /**
     * Extract security findings from the result
     */
    private function extractSecurityFindings(string $result): array
    {
        $findings = [];
        
        // Look for vulnerability patterns
        $patterns = [
            'critical' => '/(?:critical|high.risk|severe).{0,200}vulnerability/i',
            'high' => '/(?:high|important).{0,200}(?:vulnerability|issue|risk)/i',
            'medium' => '/(?:medium|moderate).{0,200}(?:vulnerability|issue|risk)/i',
            'low' => '/(?:low|minor).{0,200}(?:vulnerability|issue|risk)/i'
        ];

        foreach ($patterns as $severity => $pattern) {
            if (preg_match_all($pattern, $result, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $findings[] = [
                        'severity' => $severity,
                        'description' => trim($match[0]),
                        'position' => $match[1]
                    ];
                }
            }
        }

        return $findings;
    }

    /**
     * Extract recommendations from the result
     */
    private function extractRecommendations(string $result): array
    {
        $recommendations = [];
        
        // Look for recommendation patterns
        if (preg_match_all('/(?:recommend|should|must|consider).{10,300}/i', $result, $matches)) {
            foreach ($matches[0] as $match) {
                $recommendations[] = trim($match);
            }
        }

        return array_unique($recommendations);
    }

    /**
     * Calculate risk score based on findings
     */
    private function calculateRiskScore(array $findings): ?int
    {
        if (empty($findings)) {
            return 0;
        }

        $score = 0;
        $weights = [
            'critical' => 40,
            'high' => 25,
            'medium' => 10,
            'low' => 5
        ];

        foreach ($findings as $finding) {
            $severity = $finding['severity'] ?? 'low';
            $score += $weights[$severity] ?? 5;
        }

        return min(100, $score);
    }

    /**
     * Get token count from stream status
     */
    private function getTokenCount(): int
    {
        $streamStatus = app(OpenAiStreamService::class)->getStreamStatus($this->jobId);
        return $streamStatus['tokens_received'] ?? 0;
    }

    /**
     * Create finding records from analysis results
     */
    private function createFindings(Analysis $analysis, array $findings): void
    {
        foreach ($findings as $finding) {
            $analysis->findings()->create([
                'title' => $this->extractFindingTitle($finding['description']),
                'description' => $finding['description'],
                'severity' => $finding['severity'],
                'category' => $this->categorizeFindings($finding['description']),
                'line_number' => null, // Could be enhanced to extract line numbers
                'recommendation' => null, // Could be enhanced to extract specific recommendations
                'metadata' => [
                    'source' => 'openai',
                    'analysis_type' => $this->analysisType,
                    'position' => $finding['position'] ?? null
                ]
            ]);
        }
    }

    /**
     * Extract title from finding description
     */
    private function extractFindingTitle(string $description): string
    {
        // Take first sentence or first 100 characters
        if (preg_match('/^([^.!?]+[.!?])/', $description, $matches)) {
            return trim($matches[1]);
        }
        
        return Str::limit($description, 100);
    }

    /**
     * Categorize finding based on description
     */
    private function categorizeFindings(string $description): string
    {
        $categories = [
            'Re-entrancy' => '/re.?entranc/i',
            'A03:2021-Injection' => '/injection|sql|command/i',
            'A01:2021-Broken Access Control' => '/access.control|authorization|privilege/i',
            'A02:2021-Cryptographic Failures' => '/crypto|hash|encryption|signature/i',
            'Integer Overflow/Underflow' => '/overflow|underflow|integer/i',
            'Unvalidated Input' => '/input.validation|sanitiz/i',
        ];

        foreach ($categories as $category => $pattern) {
            if (preg_match($pattern, $description)) {
                return $category;
            }
        }

        return 'Other';
    }
}