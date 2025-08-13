<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\OpenAiStreamService;
use App\Models\OpenAiJobResult;
use App\Events\TokenStreamed;
use App\Events\AnalysisProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Carbon\Carbon;

final class OpenAiStreamingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800; // 30 minutes
    public int $tries = 3;
    public int $backoff = 300; // 5 minutes between retries
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public string $prompt,
        public string $jobId,
        public array $config = [],
        public array $metadata = [],
        public string $jobType = 'analysis',
        public ?int $userId = null
    ) {
        $this->onQueue($this->getQueueName());
    }

    /**
     * Execute the OpenAI streaming job
     */
    public function handle(OpenAiStreamService $streamService): void
    {
        $startTime = microtime(true);
        
        Log::info('Starting OpenAI streaming job', [
            'job_id' => $this->jobId,
            'job_type' => $this->jobType,
            'user_id' => $this->userId,
            'queue' => $this->queue,
            'config' => $this->config
        ]);

        try {
            // Create job result record
            $jobResult = $this->createJobRecord();
            
            // Configure streaming service
            $this->configureStreamService($streamService);
            
            // Initialize streaming state
            $this->initializeStreamingState();
            
            // Execute streaming analysis
            $response = $this->executeStreaming($streamService);
            
            // Process and store final results
            $this->storeResults($jobResult, $response, $startTime);
            
            // Clean up streaming cache
            $this->cleanupStreamingCache();
            
            Log::info('OpenAI streaming job completed successfully', [
                'job_id' => $this->jobId,
                'total_duration_ms' => round((microtime(true) - $startTime) * 1000),
                'response_length' => strlen($response)
            ]);
            
        } catch (\Exception $e) {
            $this->handleJobFailure($e, $startTime);
            throw $e;
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('OpenAI streaming job failed permanently', [
            'job_id' => $this->jobId,
            'job_type' => $this->jobType,
            'attempts' => $this->attempts(),
            'exception' => $exception->getMessage()
        ]);

        // Update job result with failure
        try {
            $jobResult = OpenAiJobResult::where('job_id', $this->jobId)->first();
            if ($jobResult) {
                $jobResult->update([
                    'status' => 'failed',
                    'error_message' => $exception->getMessage(),
                    'failed_at' => now(),
                    'attempts_made' => $this->attempts(),
                    'metadata' => array_merge($jobResult->metadata ?? [], [
                        'permanent_failure' => true,
                        'final_error' => [
                            'message' => $exception->getMessage(),
                            'file' => $exception->getFile(),
                            'line' => $exception->getLine(),
                            'trace' => $exception->getTraceAsString()
                        ]
                    ])
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to update job result after permanent failure', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage()
            ]);
        }

        // Clean up streaming cache
        $this->cleanupStreamingCache();
        
        // Dispatch failure event
        Event::dispatch(new AnalysisProgress($this->jobId, 'failed', [
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]));
    }

    /**
     * Get queue middleware
     */
    public function middleware(): array
    {
        return [
            new WithoutOverlapping($this->jobId, 3600) // Prevent duplicate jobs for 1 hour
        ];
    }

    /**
     * Create initial job result record
     */
    private function createJobRecord(): OpenAiJobResult
    {
        return OpenAiJobResult::create([
            'job_id' => $this->jobId,
            'job_type' => $this->jobType,
            'user_id' => $this->userId,
            'status' => 'processing',
            'prompt' => $this->prompt,
            'config' => $this->config,
            'metadata' => array_merge($this->metadata, [
                'queue' => $this->queue,
                'started_at' => now()->toISOString(),
                'attempts' => $this->attempts()
            ]),
            'started_at' => now()
        ]);
    }

    /**
     * Configure the OpenAI streaming service
     */
    private function configureStreamService(OpenAiStreamService $streamService): void
    {
        $model = $this->config['model'] ?? 'gpt-4';
        $maxTokens = $this->config['max_tokens'] ?? 2000;
        $temperature = $this->config['temperature'] ?? 0.7;
        
        // Create new instance with custom configuration
        $customStreamService = new OpenAiStreamService($model, $maxTokens, $temperature);
        
        Log::info('Configured OpenAI streaming service', [
            'job_id' => $this->jobId,
            'model' => $model,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature
        ]);
    }

    /**
     * Initialize streaming state in cache
     */
    private function initializeStreamingState(): void
    {
        $streamingState = [
            'job_id' => $this->jobId,
            'status' => 'initializing',
            'tokens_received' => 0,
            'content' => '',
            'started_at' => now()->toISOString(),
            'last_activity' => now()->toISOString(),
            'progress_percentage' => 0,
            'estimated_total_tokens' => $this->config['max_tokens'] ?? 2000,
            'config' => $this->config,
            'metadata' => $this->metadata
        ];

        Cache::put("openai_stream_{$this->jobId}", $streamingState, 7200); // 2 hours

        // Dispatch initialization event
        Event::dispatch(new AnalysisProgress($this->jobId, 'initialized', $streamingState));
    }

    /**
     * Execute streaming with enhanced monitoring
     */
    private function executeStreaming(OpenAiStreamService $streamService): string
    {
        $context = $this->buildStreamingContext();
        
        // Register streaming callback for real-time updates
        $this->registerStreamingCallback();
        
        Log::info('Starting OpenAI stream execution', [
            'job_id' => $this->jobId,
            'prompt_length' => strlen($this->prompt),
            'context' => array_keys($context)
        ]);
        
        // Execute streaming with custom context
        return $streamService->streamSecurityAnalysis(
            $this->prompt,
            $this->jobId,
            $context
        );
    }

    /**
     * Build streaming context from job configuration
     */
    private function buildStreamingContext(): array
    {
        $context = [
            'job_id' => $this->jobId,
            'job_type' => $this->jobType,
            'user_id' => $this->userId
        ];

        // Add system prompt if specified
        if (!empty($this->config['system_prompt'])) {
            $context['system_prompt'] = $this->config['system_prompt'];
        }

        // Add conversation history if provided
        if (!empty($this->config['history'])) {
            $context['history'] = $this->config['history'];
        }

        // Add response format requirements
        if (!empty($this->config['response_format'])) {
            $context['response_format'] = $this->config['response_format'];
        }

        // Add analysis-specific context
        if ($this->jobType === 'security_analysis') {
            $context['analysis_requirements'] = [
                'focus_areas' => $this->config['focus_areas'] ?? [
                    'reentrancy', 'overflow', 'access_control', 'gas_optimization'
                ],
                'output_format' => 'json',
                'include_recommendations' => true,
                'severity_levels' => ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW', 'INFO']
            ];
        }

        return $context;
    }

    /**
     * Register callback for streaming updates
     */
    private function registerStreamingCallback(): void
    {
        // This would typically be handled by event listeners
        // but we can add additional monitoring here
        
        $monitoringInterval = $this->config['monitoring_interval'] ?? 30; // seconds
        
        // Schedule periodic progress updates
        $this->scheduleProgressMonitoring($monitoringInterval);
    }

    /**
     * Schedule periodic progress monitoring
     */
    private function scheduleProgressMonitoring(int $intervalSeconds): void
    {
        // This could be enhanced with a separate monitoring job
        // For now, we rely on the streaming service's event dispatching
        
        Log::debug('Progress monitoring scheduled', [
            'job_id' => $this->jobId,
            'interval_seconds' => $intervalSeconds
        ]);
    }

    /**
     * Store final results in database
     */
    private function storeResults(OpenAiJobResult $jobResult, string $response, float $startTime): void
    {
        $endTime = microtime(true);
        $processingTimeMs = round(($endTime - $startTime) * 1000);
        
        // Get final streaming stats
        $streamingStats = Cache::get("openai_stream_{$this->jobId}", []);
        
        DB::transaction(function () use ($jobResult, $response, $processingTimeMs, $streamingStats) {
            // Parse response if it's JSON
            $parsedResponse = $this->parseResponse($response);
            
            // Calculate token usage
            $tokenStats = $this->calculateTokenStats($response, $streamingStats);
            
            // Update job result record
            $jobResult->update([
                'status' => 'completed',
                'response' => $response,
                'parsed_response' => $parsedResponse,
                'token_usage' => $tokenStats,
                'processing_time_ms' => $processingTimeMs,
                'completed_at' => now(),
                'streaming_stats' => $streamingStats,
                'metadata' => array_merge($jobResult->metadata ?? [], [
                    'completed_at' => now()->toISOString(),
                    'final_token_count' => $tokenStats['total_tokens'],
                    'processing_time_ms' => $processingTimeMs,
                    'response_size_bytes' => strlen($response),
                    'success_rate' => $this->calculateSuccessRate($parsedResponse)
                ])
            ]);

            // Store additional analysis results if this is a security analysis
            if ($this->jobType === 'security_analysis' && !empty($parsedResponse)) {
                $this->storeSecurityAnalysisResults($jobResult, $parsedResponse);
            }

            Log::info('OpenAI job results stored successfully', [
                'job_id' => $this->jobId,
                'response_size' => strlen($response),
                'token_count' => $tokenStats['total_tokens'],
                'processing_time_ms' => $processingTimeMs
            ]);
        });

        // Dispatch completion event
        Event::dispatch(new AnalysisProgress($this->jobId, 'completed', [
            'processing_time_ms' => $processingTimeMs,
            'token_count' => $tokenStats['total_tokens'],
            'response_size' => strlen($response)
        ]));
    }

    /**
     * Parse OpenAI response
     */
    private function parseResponse(string $response): ?array
    {
        // Try to parse as JSON first
        $decoded = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // If not JSON, try to extract JSON from markdown code blocks
        if (preg_match('/```json\s*(\{.*?\})\s*```/s', $response, $matches)) {
            $decoded = json_decode($matches[1], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        // Return null if can't parse as structured data
        return null;
    }

    /**
     * Calculate token usage statistics
     */
    private function calculateTokenStats(string $response, array $streamingStats): array
    {
        return [
            'total_tokens' => $streamingStats['tokens_received'] ?? str_word_count($response),
            'prompt_tokens' => str_word_count($this->prompt),
            'completion_tokens' => $streamingStats['tokens_received'] ?? str_word_count($response),
            'estimated_cost_usd' => $this->estimateCost($streamingStats['tokens_received'] ?? 0),
            'tokens_per_second' => $this->calculateTokensPerSecond($streamingStats),
            'streaming_efficiency' => $this->calculateStreamingEfficiency($streamingStats)
        ];
    }

    /**
     * Store security analysis specific results
     */
    private function storeSecurityAnalysisResults(OpenAiJobResult $jobResult, array $parsedResponse): void
    {
        if (!empty($parsedResponse['findings'])) {
            // Store individual findings
            foreach ($parsedResponse['findings'] as $finding) {
                // This could create records in a findings table
                Log::debug('Security finding detected', [
                    'job_id' => $this->jobId,
                    'severity' => $finding['severity'] ?? 'UNKNOWN',
                    'title' => $finding['title'] ?? 'Unnamed finding'
                ]);
            }
        }
    }

    /**
     * Calculate success rate based on parsed response
     */
    private function calculateSuccessRate(?array $parsedResponse): float
    {
        if (!$parsedResponse) {
            return 0.0;
        }

        // Basic success indicators
        $indicators = [
            'has_structured_data' => !empty($parsedResponse),
            'has_findings' => !empty($parsedResponse['findings']),
            'has_recommendations' => !empty($parsedResponse['recommendations']),
            'valid_severity_levels' => $this->hasValidSeverityLevels($parsedResponse)
        ];

        $successCount = count(array_filter($indicators));
        return round(($successCount / count($indicators)) * 100, 2);
    }

    /**
     * Check if response has valid severity levels
     */
    private function hasValidSeverityLevels(array $response): bool
    {
        if (empty($response['findings'])) {
            return false;
        }

        $validSeverities = ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW', 'INFO'];
        
        foreach ($response['findings'] as $finding) {
            if (empty($finding['severity']) || !in_array($finding['severity'], $validSeverities)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Estimate cost based on token usage
     */
    private function estimateCost(int $tokens): float
    {
        // GPT-4 pricing (approximate)
        $costPerToken = match($this->config['model'] ?? 'gpt-4') {
            'gpt-4' => 0.00003, // $0.03 per 1K tokens
            'gpt-3.5-turbo' => 0.000002, // $0.002 per 1K tokens
            default => 0.00003
        };

        return round($tokens * $costPerToken, 4);
    }

    /**
     * Calculate tokens per second
     */
    private function calculateTokensPerSecond(array $streamingStats): float
    {
        if (empty($streamingStats['processing_time_ms']) || empty($streamingStats['tokens_received'])) {
            return 0.0;
        }

        $processingTimeSeconds = $streamingStats['processing_time_ms'] / 1000;
        return round($streamingStats['tokens_received'] / $processingTimeSeconds, 2);
    }

    /**
     * Calculate streaming efficiency
     */
    private function calculateStreamingEfficiency(array $streamingStats): float
    {
        // This could measure how consistently tokens were received
        // For now, return a basic efficiency score
        return !empty($streamingStats['tokens_received']) ? 0.95 : 0.0;
    }

    /**
     * Handle job failure and cleanup
     */
    private function handleJobFailure(\Exception $e, float $startTime): void
    {
        $processingTimeMs = round((microtime(true) - $startTime) * 1000);
        
        Log::error('OpenAI streaming job failed', [
            'job_id' => $this->jobId,
            'error' => $e->getMessage(),
            'processing_time_ms' => $processingTimeMs,
            'attempt' => $this->attempts()
        ]);

        // Update job result with error
        try {
            $jobResult = OpenAiJobResult::where('job_id', $this->jobId)->first();
            if ($jobResult) {
                $jobResult->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'processing_time_ms' => $processingTimeMs,
                    'failed_at' => now(),
                    'metadata' => array_merge($jobResult->metadata ?? [], [
                        'error_details' => [
                            'message' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'attempt' => $this->attempts()
                        ]
                    ])
                ]);
            }
        } catch (\Exception $updateError) {
            Log::error('Failed to update job result on failure', [
                'job_id' => $this->jobId,
                'original_error' => $e->getMessage(),
                'update_error' => $updateError->getMessage()
            ]);
        }
    }

    /**
     * Clean up streaming cache
     */
    private function cleanupStreamingCache(): void
    {
        try {
            Cache::forget("openai_stream_{$this->jobId}");
            Log::debug('Streaming cache cleaned up', ['job_id' => $this->jobId]);
        } catch (\Exception $e) {
            Log::warning('Failed to clean up streaming cache', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get appropriate queue name based on job type and priority
     */
    private function getQueueName(): string
    {
        $priority = $this->config['priority'] ?? 'normal';
        $type = $this->jobType;

        return match($priority) {
            'urgent' => "openai-{$type}-urgent",
            'high' => "openai-{$type}-high", 
            'low' => "openai-{$type}-low",
            default => "openai-{$type}"
        };
    }

    /**
     * Get job tags for Horizon monitoring
     */
    public function tags(): array
    {
        return [
            'openai-streaming',
            'type:' . $this->jobType,
            'job:' . substr($this->jobId, 0, 10),
            'model:' . ($this->config['model'] ?? 'gpt-4'),
            'user:' . ($this->userId ?? 'anonymous')
        ];
    }

    /**
     * Get job display name for Horizon
     */
    public function displayName(): string
    {
        return "OpenAI Streaming: {$this->jobType} ({$this->jobId})";
    }
}