<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ContractAnalysis;
use App\Services\OpenAiStreamService;
use App\Services\SecurityFindingValidator;
use App\Services\SourceCodeFetchingService;
use App\Services\SolidityCleanerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use App\Events\AnalysisStarted;
use App\Events\AnalysisCompleted;
use App\Events\AnalysisFailed;

class AnalyzeContractJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutes
    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly string $contractAddress,
        public readonly string $network,
        public readonly string $analysisId,
        public readonly array $options = []
    ) {
        $this->onQueue('contract-analysis');
    }

    public function handle(
        OpenAiStreamService $streamService,
        SecurityFindingValidator $validator,
        SourceCodeFetchingService $sourceService,
        SolidityCleanerService $cleanerService
    ): void {
        $analysis = ContractAnalysis::findOrFail($this->analysisId);
        
        try {
            Log::info("Starting contract analysis job", [
                'analysis_id' => $this->analysisId,
                'contract' => $this->contractAddress,
                'network' => $this->network
            ]);

            // Update analysis status
            $analysis->update([
                'status' => 'processing',
                'started_at' => now(),
                'progress' => 10
            ]);

            Event::dispatch(new AnalysisStarted($analysis));

            // Step 1: Fetch source code
            $this->updateProgress($analysis, 20, 'Fetching contract source code...');
            $sourceData = $sourceService->fetchSourceCode($this->contractAddress, $this->network);
            
            if (empty($sourceData['contracts'])) {
                throw new \Exception('No source code found for contract');
            }

            // Step 2: Clean source code for AI analysis
            $this->updateProgress($analysis, 40, 'Preparing source code for analysis...');
            $cleanedCode = $this->prepareSourceForAnalysis($sourceData, $cleanerService);

            // Step 3: Generate analysis prompt
            $this->updateProgress($analysis, 50, 'Generating analysis prompt...');
            $prompt = $this->buildAnalysisPrompt($cleanedCode, $this->options);

            // Step 4: Stream OpenAI analysis
            $this->updateProgress($analysis, 60, 'Starting AI security analysis...');
            $streamResponse = $streamService->streamSecurityAnalysis(
                $prompt,
                $this->analysisId,
                [
                    'contract_address' => $this->contractAddress,
                    'network' => $this->network,
                    'analysis_id' => $this->analysisId
                ]
            );

            // Step 5: Parse and validate findings
            $this->updateProgress($analysis, 80, 'Processing security findings...');
            $findings = $validator->parseOpenAiResponse($streamResponse);

            // Step 6: Store results
            $this->updateProgress($analysis, 90, 'Storing analysis results...');
            $this->storeAnalysisResults($analysis, $findings, $streamResponse);

            // Step 7: Complete analysis
            $this->updateProgress($analysis, 100, 'Analysis completed successfully');
            $analysis->update([
                'status' => 'completed',
                'completed_at' => now(),
                'findings_count' => count($findings)
            ]);

            Event::dispatch(new AnalysisCompleted($analysis));

            Log::info("Contract analysis completed successfully", [
                'analysis_id' => $this->analysisId,
                'findings_count' => count($findings)
            ]);

        } catch (\Exception $e) {
            Log::error("Contract analysis job failed", [
                'analysis_id' => $this->analysisId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $analysis->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now()
            ]);

            Event::dispatch(new AnalysisFailed($analysis, $e->getMessage()));

            throw $e;
        }
    }

    private function updateProgress(ContractAnalysis $analysis, int $progress, string $message): void
    {
        $analysis->update([
            'progress' => $progress,
            'current_step' => $message
        ]);

        // Cache progress for real-time updates
        Cache::put("analysis_progress_{$this->analysisId}", [
            'progress' => $progress,
            'message' => $message,
            'updated_at' => now()->toISOString()
        ], 3600);
    }

    private function prepareSourceForAnalysis(array $sourceData, SolidityCleanerService $cleanerService): array
    {
        $cleanedContracts = [];
        
        foreach ($sourceData['contracts'] as $contractName => $contractData) {
            if (empty($contractData['source_code'])) {
                continue;
            }

            // Clean the source code for AI analysis
            $cleaningResult = $cleanerService->cleanForPrompt($contractData['source_code']);
            
            $cleanedContracts[$contractName] = [
                'name' => $contractName,
                'source_code' => $cleaningResult['cleaned_code'],
                'original_size' => $cleaningResult['original_size'],
                'cleaned_size' => $cleaningResult['cleaned_size'],
                'abi' => $contractData['abi'] ?? null
            ];
        }

        return $cleanedContracts;
    }

    private function buildAnalysisPrompt(array $cleanedContracts, array $options): string
    {
        $schemaExample = file_get_contents(base_path('schemas/security-finding-v3.json'));
        $schema = json_decode($schemaExample, true);
        $exampleFinding = $schema['examples'][0] ?? [];

        $prompt = "You are an expert smart contract security auditor. Analyze the following Solidity contracts for security vulnerabilities.\n\n";
        
        $prompt .= "ANALYSIS REQUIREMENTS:\n";
        $prompt .= "- Identify all security vulnerabilities, gas optimizations, and code quality issues\n";
        $prompt .= "- Focus on critical vulnerabilities: reentrancy, access control, integer overflow, etc.\n";
        $prompt .= "- Consider OWASP Top 10 and SWC Registry classifications\n";
        $prompt .= "- Analyze DeFi-specific vulnerabilities if applicable\n";
        $prompt .= "- Provide actionable remediation recommendations\n\n";

        $prompt .= "OUTPUT FORMAT:\n";
        $prompt .= "Return findings as a JSON array following this schema structure:\n";
        $prompt .= "```json\n" . json_encode($exampleFinding, JSON_PRETTY_PRINT) . "\n```\n\n";

        $prompt .= "CONTRACTS TO ANALYZE:\n";
        foreach ($cleanedContracts as $contractName => $contract) {
            $prompt .= "\n=== {$contractName} ===\n";
            $prompt .= "```solidity\n{$contract['source_code']}\n```\n";
        }

        $prompt .= "\nANALYSIS FOCUS:\n";
        if (!empty($options['focus_areas'])) {
            $prompt .= "Pay special attention to: " . implode(', ', $options['focus_areas']) . "\n";
        }
        
        if (!empty($options['severity_threshold'])) {
            $prompt .= "Include findings of severity: {$options['severity_threshold']} and above\n";
        }

        $prompt .= "\nProvide comprehensive security analysis with detailed findings in JSON format.";

        return $prompt;
    }

    private function storeAnalysisResults(ContractAnalysis $analysis, array $findings, string $rawResponse): void
    {
        $analysis->update([
            'raw_response' => $rawResponse,
            'findings' => $findings,
            'metadata' => [
                'total_findings' => count($findings),
                'critical_count' => count(array_filter($findings, fn($f) => $f['severity'] === 'CRITICAL')),
                'high_count' => count(array_filter($findings, fn($f) => $f['severity'] === 'HIGH')),
                'medium_count' => count(array_filter($findings, fn($f) => $f['severity'] === 'MEDIUM')),
                'low_count' => count(array_filter($findings, fn($f) => $f['severity'] === 'LOW')),
                'info_count' => count(array_filter($findings, fn($f) => $f['severity'] === 'INFO')),
                'categories' => array_unique(array_column($findings, 'category')),
                'analyzed_at' => now()->toISOString()
            ]
        ]);

        // Store individual findings if needed
        foreach ($findings as $finding) {
            // Could store in separate findings table for advanced querying
            Log::debug("Finding stored", [
                'analysis_id' => $this->analysisId,
                'severity' => $finding['severity'],
                'category' => $finding['category'],
                'title' => $finding['title']
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("AnalyzeContractJob failed permanently", [
            'analysis_id' => $this->analysisId,
            'contract' => $this->contractAddress,
            'error' => $exception->getMessage()
        ]);

        try {
            $analysis = ContractAnalysis::findOrFail($this->analysisId);
            $analysis->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'completed_at' => now()
            ]);

            Event::dispatch(new AnalysisFailed($analysis, $exception->getMessage()));
        } catch (\Exception $e) {
            Log::error("Failed to update analysis status after job failure", [
                'analysis_id' => $this->analysisId,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function tags(): array
    {
        return [
            'contract-analysis',
            "analysis:{$this->analysisId}",
            "network:{$this->network}",
            "address:{$this->contractAddress}"
        ];
    }
}