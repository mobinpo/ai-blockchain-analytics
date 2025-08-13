<?php

namespace App\Jobs;

use App\Models\Analysis;
use App\Models\ContractAnalysis;
use App\Services\OpenAiStreamService;
use App\Services\SourceCodeFetchingService;
use App\Services\SecurityFindingValidator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StreamingAnalysisJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $contractAddress;
    public string $network;
    public int $analysisId;
    public array $analysisConfig;
    public int $timeout = 900; // 15 minutes
    public int $tries = 3;

    public function __construct(
        string $contractAddress,
        string $network,
        int $analysisId,
        array $analysisConfig = []
    ) {
        $this->contractAddress = $contractAddress;
        $this->network = $network;
        $this->analysisId = $analysisId;
        $this->analysisConfig = $analysisConfig;

        // Queue configuration
        $this->onQueue('streaming-analysis');
    }

    public function handle(
        OpenAiStreamService $streamService,
        SourceCodeFetchingService $sourceService,
        SecurityFindingValidator $validator
    ): void {
        $jobId = $this->generateJobId();
        $analysis = null;

        try {
            Log::info("Starting streaming analysis job", [
                'job_id' => $jobId,
                'analysis_id' => $this->analysisId,
                'contract_address' => $this->contractAddress,
                'network' => $this->network
            ]);

            // Step 1: Get or create analysis record
            $analysis = Analysis::find($this->analysisId);
            if (!$analysis) {
                throw new \Exception("Analysis record not found: {$this->analysisId}");
            }

            // Update analysis status
            $analysis->update([
                'status' => 'processing',
                'job_id' => $jobId,
                'started_at' => now(),
                'metadata' => array_merge($analysis->metadata ?? [], [
                    'streaming_enabled' => true,
                    'job_started_at' => now()->toISOString()
                ])
            ]);

            // Step 2: Fetch source code
            Log::info("Fetching source code for analysis {$this->analysisId}");
            $sourceData = $sourceService->fetchSourceCode($this->contractAddress, $this->network);
            
            if (empty($sourceData['contracts'])) {
                throw new \Exception('No source code available for contract');
            }

            // Step 3: Prepare analysis configuration
            $analysisType = $this->analysisConfig['type'] ?? 'security';
            $streamConfig = [
                'model' => $this->analysisConfig['model'] ?? 'gpt-4',
                'max_tokens' => $this->analysisConfig['max_tokens'] ?? 2000,
                'temperature' => $this->analysisConfig['temperature'] ?? 0.7
            ];

            // Configure streaming service
            $streamService->configure($streamConfig);

            // Step 4: Get cleaned source code for analysis
            $mainContract = $sourceData['main_contract'] ?? array_key_first($sourceData['contracts']);
            $sourceCode = $sourceData['contracts'][$mainContract]['source'] ?? '';

            if (empty($sourceCode)) {
                throw new \Exception('Main contract source code is empty');
            }

            Log::info("Starting OpenAI streaming analysis", [
                'analysis_id' => $this->analysisId,
                'job_id' => $jobId,
                'main_contract' => $mainContract,
                'source_size' => strlen($sourceCode),
                'analysis_type' => $analysisType
            ]);

            // Step 5: Perform streaming analysis
            $analysisResult = $streamService->analyzeBlockchainCodeStructured(
                $sourceCode,
                $jobId,
                $analysisType
            );

            // Step 6: Store results
            $this->storeAnalysisResults($analysis, $analysisResult, $sourceData, $jobId);

            // Step 7: Mark as completed
            $analysis->update([
                'status' => 'completed',
                'completed_at' => now(),
                'metadata' => array_merge($analysis->metadata ?? [], [
                    'job_completed_at' => now()->toISOString(),
                    'streaming_stats' => $streamService->getStreamStatus($jobId),
                    'findings_count' => count($analysisResult['findings']),
                    'validation_success_rate' => $analysisResult['validation_summary']['success_rate']
                ])
            ]);

            Log::info("Streaming analysis job completed successfully", [
                'job_id' => $jobId,
                'analysis_id' => $this->analysisId,
                'findings_count' => count($analysisResult['findings']),
                'processing_time' => now()->diffInSeconds($analysis->started_at) . 's'
            ]);

        } catch (\Exception $e) {
            Log::error("Streaming analysis job failed", [
                'job_id' => $jobId,
                'analysis_id' => $this->analysisId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Update analysis with error
            if ($analysis) {
                $analysis->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'failed_at' => now(),
                    'metadata' => array_merge($analysis->metadata ?? [], [
                        'error_details' => [
                            'message' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'failed_at' => now()->toISOString()
                        ]
                    ])
                ]);
            }

            throw $e;
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Streaming analysis job permanently failed", [
            'analysis_id' => $this->analysisId,
            'contract_address' => $this->contractAddress,
            'network' => $this->network,
            'exception' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Mark analysis as permanently failed
        try {
            $analysis = Analysis::find($this->analysisId);
            if ($analysis) {
                $analysis->update([
                    'status' => 'failed',
                    'error_message' => 'Job failed after ' . $this->tries . ' attempts: ' . $exception->getMessage(),
                    'failed_at' => now(),
                    'metadata' => array_merge($analysis->metadata ?? [], [
                        'permanent_failure' => true,
                        'attempts_made' => $this->attempts(),
                        'final_error' => $exception->getMessage()
                    ])
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Failed to update analysis status after job failure: " . $e->getMessage());
        }
    }

    /**
     * Store analysis results in database
     */
    private function storeAnalysisResults(
        Analysis $analysis,
        array $analysisResult,
        array $sourceData,
        string $jobId
    ): void {
        DB::transaction(function () use ($analysis, $analysisResult, $sourceData, $jobId) {
            // Update main analysis record
            $analysis->update([
                'result' => [
                    'findings' => $analysisResult['findings'],
                    'summary' => [
                        'total_findings' => count($analysisResult['findings']),
                        'severity_breakdown' => $this->calculateSeverityBreakdown($analysisResult['findings']),
                        'validation_summary' => $analysisResult['validation_summary']
                    ],
                    'source_info' => [
                        'main_contract' => $sourceData['main_contract'],
                        'contracts_count' => count($sourceData['contracts']),
                        'total_lines' => $sourceData['statistics']['total_lines'],
                        'compiler_version' => $sourceData['compiler_version']
                    ],
                    'analysis_metadata' => [
                        'job_id' => $jobId,
                        'model_used' => $this->analysisConfig['model'] ?? 'gpt-4',
                        'analysis_type' => $this->analysisConfig['type'] ?? 'security',
                        'streaming_enabled' => true
                    ]
                ]
            ]);

            // Store detailed contract analysis
            ContractAnalysis::create([
                'analysis_id' => $analysis->id,
                'contract_address' => $this->contractAddress,
                'network' => $this->network,
                'main_contract_name' => $sourceData['main_contract'],
                'compiler_version' => $sourceData['compiler_version'],
                'optimization_enabled' => $sourceData['optimization_enabled'],
                'source_code' => $sourceData['contracts'][$sourceData['main_contract']]['source'] ?? '',
                'findings' => $analysisResult['findings'],
                'findings_count' => count($analysisResult['findings']),
                'severity_breakdown' => $this->calculateSeverityBreakdown($analysisResult['findings']),
                'raw_openai_response' => $analysisResult['raw_response'],
                'validation_summary' => $analysisResult['validation_summary'],
                'analysis_metadata' => [
                    'job_id' => $jobId,
                    'streaming_stats' => app(OpenAiStreamService::class)->getStreamStatus($jobId),
                    'source_statistics' => $sourceData['statistics'],
                    'processed_at' => now()->toISOString()
                ]
            ]);

            Log::info("Analysis results stored successfully", [
                'analysis_id' => $analysis->id,
                'findings_stored' => count($analysisResult['findings']),
                'contract_analysis_created' => true
            ]);
        });
    }

    /**
     * Calculate severity breakdown of findings
     */
    private function calculateSeverityBreakdown(array $findings): array
    {
        $breakdown = [
            'CRITICAL' => 0,
            'HIGH' => 0,
            'MEDIUM' => 0,
            'LOW' => 0,
            'INFO' => 0
        ];

        foreach ($findings as $finding) {
            $severity = $finding['severity'] ?? 'UNKNOWN';
            if (isset($breakdown[$severity])) {
                $breakdown[$severity]++;
            }
        }

        return $breakdown;
    }

    /**
     * Generate unique job ID
     */
    private function generateJobId(): string
    {
        return 'stream_' . $this->analysisId . '_' . Str::random(8);
    }

    /**
     * Get job tags for Horizon monitoring
     */
    public function tags(): array
    {
        return [
            'streaming-analysis',
            'contract:' . substr($this->contractAddress, 0, 10),
            'network:' . $this->network,
            'analysis:' . $this->analysisId
        ];
    }

    /**
     * Get job display name for Horizon
     */
    public function displayName(): string
    {
        return "Streaming Analysis: {$this->contractAddress} on {$this->network}";
    }
}