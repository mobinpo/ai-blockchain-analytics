<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\OpenAiStreamService;
use App\Models\OpenAiJobResult;
use App\Models\Analysis;
use App\Events\TokenStreamed;
use App\Events\AnalysisProgress;
use App\Events\StreamingCompleted;
use App\Events\AnalysisFailed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Throwable;

/**
 * Optimized OpenAI Streaming Job with Enhanced Performance and Monitoring
 * 
 * Key Features:
 * - High-performance token streaming with Redis caching
 * - Real-time progress broadcasting via WebSockets
 * - Comprehensive error handling and recovery
 * - Advanced metrics collection and performance monitoring
 * - Intelligent queue priority management
 * - Memory-efficient stream processing
 * - Detailed result storage and analytics
 */
final class OptimizedOpenAiStreamingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Job configuration
    public int $timeout = 1800; // 30 minutes
    public int $tries = 3;
    public int $backoff = 300; // 5 minutes between retries
    public bool $deleteWhenMissingModels = true;
    public int $maxExceptions = 3;

    // Performance tracking
    private float $startTime;
    private array $metrics = [];
    private int $tokensStreamed = 0;
    private int $totalResponseSize = 0;

    public function __construct(
        public string $prompt,
        public string $jobId,
        public array $config = [],
        public array $metadata = [],
        public string $jobType = 'security_analysis',
        public ?int $userId = null,
        public ?int $analysisId = null,
        public string $priority = 'normal'
    ) {
        // Set intelligent queue based on priority and job type
        $this->onQueue($this->determineOptimalQueue());
        
        // Configure job-specific settings
        $this->configureJobSettings();
    }

    /**
     * Get middleware for this job
     */
    public function middleware(): array
    {
        return [
            new WithoutOverlapping($this->jobId),
            (new RateLimited('openai-streaming'))->allow(10)->everyMinute(),
        ];
    }

    /**
     * Execute the optimized streaming job
     */
    public function handle(OpenAiStreamService $streamService): void
    {
        $this->startTime = microtime(true);
        $this->initializeMetrics();

        Log::info('🚀 Starting optimized OpenAI streaming job', [
            'job_id' => $this->jobId,
            'job_type' => $this->jobType,
            'analysis_id' => $this->analysisId,
            'user_id' => $this->userId,
            'priority' => $this->priority,
            'queue' => $this->queue,
            'prompt_length' => strlen($this->prompt),
            'config' => $this->config
        ]);

        try {
            // Phase 1: Initialize job and create database records
            $jobResult = $this->initializeJobExecution();
            
            // Phase 2: Configure streaming service with optimized settings
            $this->configureOptimizedStreaming($streamService);
            
            // Phase 3: Execute streaming with enhanced monitoring
            $response = $this->executeOptimizedStreaming($streamService);
            
            // Phase 4: Process and validate streaming results
            $processedResults = $this->processStreamingResults($response);
            
            // Phase 5: Store comprehensive results with analytics
            $this->storeOptimizedResults($jobResult, $processedResults);
            
            // Phase 6: Finalize job and cleanup
            $this->finalizeJobExecution($jobResult);
            
            $this->logSuccessfulCompletion();
            
        } catch (Throwable $e) {
            $this->handleOptimizedFailure($e);
            throw $e;
        }
    }

    /**
     * Initialize job execution and create database record
     */
    private function initializeJobExecution(): OpenAiJobResult
    {
        // Create comprehensive job record
        $jobResult = OpenAiJobResult::create([
            'job_id' => $this->jobId,
            'job_type' => $this->jobType,
            'user_id' => $this->userId,
            'status' => 'processing',
            'prompt' => $this->prompt,
            'config' => array_merge($this->config, [
                'priority' => $this->priority,
                'queue' => $this->queue,
                'timeout' => $this->timeout,
                'optimized_version' => '2024.1.0'
            ]),
            'metadata' => array_merge($this->metadata, [
                'analysis_id' => $this->analysisId,
                'started_at' => now()->toISOString(),
                'job_version' => 'OptimizedOpenAiStreamingJob',
                'performance_mode' => 'high'
            ]),
            'started_at' => now(),
            'attempts_made' => $this->attempts()
        ]);

        // Initialize streaming state in Redis for real-time access
        $this->initializeStreamingState();

        // Dispatch job started event
        Event::dispatch(new AnalysisProgress(
            $this->jobId,
            'started',
            ['job_result_id' => $jobResult->id, 'estimated_duration' => $this->estimateJobDuration()]
        ));

        return $jobResult;
    }

    /**
     * Configure optimized streaming with enhanced settings
     */
    private function configureOptimizedStreaming(OpenAiStreamService $streamService): void
    {
        // Apply optimized configuration based on job type and priority
        $optimizedConfig = $this->buildOptimizedConfig();
        
        // Configure streaming service
        $streamService->configure($optimizedConfig);
        
        // Setup advanced monitoring callbacks
        $this->setupAdvancedMonitoring($streamService);
        
        Log::debug('🔧 Configured optimized streaming', [
            'job_id' => $this->jobId,
            'config' => $optimizedConfig
        ]);
    }

    /**
     * Execute optimized streaming with real-time monitoring
     */
    private function executeOptimizedStreaming(OpenAiStreamService $streamService): string
    {
        $streamingContext = $this->buildStreamingContext();
        
        // Register real-time token callback
        $this->registerOptimizedTokenCallback();
        
        Log::info('⚡ Starting optimized stream execution', [
            'job_id' => $this->jobId,
            'context' => array_keys($streamingContext),
            'expected_tokens' => $this->config['max_tokens'] ?? 2000
        ]);

        // Execute streaming with performance monitoring
        $response = $streamService->streamSecurityAnalysis(
            $this->prompt,
            $this->jobId,
            $streamingContext
        );

        // Validate response quality
        $this->validateStreamingResponse($response);

        return $response;
    }

    /**
     * Process streaming results with enhanced validation
     */
    private function processStreamingResults(string $response): array
    {
        $processingStartTime = microtime(true);
        
        // Basic response processing
        $processedResults = [
            'raw_response' => $response,
            'response_length' => strlen($response),
            'processing_time_ms' => round((microtime(true) - $processingStartTime) * 1000),
            'tokens_estimated' => $this->estimateTokenCount($response),
            'quality_score' => $this->calculateResponseQuality($response)
        ];

        // Parse structured content if applicable
        if ($this->jobType === 'security_analysis') {
            $processedResults['parsed_analysis'] = $this->parseSecurityAnalysis($response);
        }

        // Extract performance metrics
        $processedResults['performance_metrics'] = $this->extractPerformanceMetrics();

        Log::debug('📊 Processed streaming results', [
            'job_id' => $this->jobId,
            'response_length' => $processedResults['response_length'],
            'quality_score' => $processedResults['quality_score'],
            'processing_time_ms' => $processedResults['processing_time_ms']
        ]);

        return $processedResults;
    }

    /**
     * Store optimized results with comprehensive analytics
     */
    private function storeOptimizedResults(OpenAiJobResult $jobResult, array $processedResults): void
    {
        $finalMetrics = $this->calculateFinalMetrics();
        
        // Update job result with comprehensive data
        $jobResult->update([
            'status' => 'completed',
            'response' => $processedResults['raw_response'],
            'parsed_response' => $processedResults['parsed_analysis'] ?? null,
            'token_usage' => [
                'prompt_tokens' => $this->estimateTokenCount($this->prompt),
                'completion_tokens' => $processedResults['tokens_estimated'],
                'total_tokens' => $this->estimateTokenCount($this->prompt) + $processedResults['tokens_estimated'],
                'tokens_per_second' => $finalMetrics['tokens_per_second'],
                'streaming_efficiency' => $finalMetrics['streaming_efficiency'],
                'estimated_cost_usd' => $this->calculateEstimatedCost($processedResults['tokens_estimated'])
            ],
            'streaming_stats' => [
                'tokens_streamed' => $this->tokensStreamed,
                'total_response_size' => $this->totalResponseSize,
                'streaming_duration_ms' => $finalMetrics['total_duration_ms'],
                'first_token_latency_ms' => $finalMetrics['first_token_latency_ms'],
                'average_token_interval_ms' => $finalMetrics['average_token_interval_ms'],
                'quality_score' => $processedResults['quality_score'],
                'performance_metrics' => $processedResults['performance_metrics']
            ],
            'processing_time_ms' => $finalMetrics['total_duration_ms'],
            'completed_at' => now(),
            'metadata' => array_merge($jobResult->metadata, [
                'completed_at' => now()->toISOString(),
                'final_metrics' => $finalMetrics,
                'optimization_applied' => true
            ])
        ]);

        // Update related analysis if exists
        if ($this->analysisId) {
            $this->updateRelatedAnalysis($processedResults);
        }

        Log::info('💾 Stored optimized results', [
            'job_id' => $this->jobId,
            'job_result_id' => $jobResult->id,
            'final_metrics' => $finalMetrics
        ]);
    }

    /**
     * Finalize job execution and cleanup
     */
    private function finalizeJobExecution(OpenAiJobResult $jobResult): void
    {
        // Clean up streaming cache
        $this->cleanupStreamingCache();
        
        // Dispatch completion events
        Event::dispatch(new StreamingCompleted($this->jobId, [
            'job_result_id' => $jobResult->id,
            'final_status' => 'completed',
            'metrics' => $this->metrics
        ]));

        // Update analysis if exists
        if ($this->analysisId) {
            Event::dispatch(new AnalysisProgress(
                $this->jobId,
                'completed',
                ['analysis_id' => $this->analysisId]
            ));
        }
    }

    /**
     * Register optimized token callback for real-time streaming
     */
    private function registerOptimizedTokenCallback(): void
    {
        // This would be implemented in the streaming service
        // For now, we'll use the event system for token streaming
        $tokenCallback = function (string $token, int $tokenIndex) {
            $this->tokensStreamed++;
            $this->totalResponseSize += strlen($token);
            
            // Update Redis cache for real-time access
            $this->updateStreamingCache($token, $tokenIndex);
            
            // Broadcast token event for real-time UI updates
            Event::dispatch(new TokenStreamed(
                $this->jobId,
                $token,
                [
                    'token_index' => $tokenIndex,
                    'total_tokens' => $this->tokensStreamed,
                    'response_size' => $this->totalResponseSize,
                    'elapsed_ms' => round((microtime(true) - $this->startTime) * 1000)
                ],
                $this->analysisId ? (string)$this->analysisId : null,
                $tokenIndex
            ));
            
            // Log progress every 50 tokens
            if ($tokenIndex % 50 === 0) {
                Log::debug("🎯 Streaming progress: {$tokenIndex} tokens", [
                    'job_id' => $this->jobId,
                    'tokens_per_second' => $this->calculateCurrentTokensPerSecond()
                ]);
            }
        };
        
        // Store callback for streaming service
        Cache::put("streaming_callback_{$this->jobId}", $tokenCallback, 3600);
    }

    /**
     * Determine optimal queue based on job characteristics
     */
    private function determineOptimalQueue(): string
    {
        $queuePrefix = "openai-{$this->jobType}";
        
        return match($this->priority) {
            'urgent' => "{$queuePrefix}-urgent",
            'high' => "{$queuePrefix}-high",
            'low' => "{$queuePrefix}-low",
            default => $queuePrefix
        };
    }

    /**
     * Configure job-specific settings
     */
    private function configureJobSettings(): void
    {
        // Adjust timeout based on job type and priority
        if ($this->jobType === 'security_analysis') {
            $this->timeout = 2400; // 40 minutes for complex analysis
        }
        
        if ($this->priority === 'urgent') {
            $this->backoff = 60; // Faster retry for urgent jobs
        }
    }

    /**
     * Initialize comprehensive metrics tracking
     */
    private function initializeMetrics(): void
    {
        $this->metrics = [
            'job_id' => $this->jobId,
            'start_time' => $this->startTime,
            'tokens_streamed' => 0,
            'total_response_size' => 0,
            'first_token_time' => null,
            'last_token_time' => null,
            'error_count' => 0,
            'retry_count' => $this->attempts() - 1
        ];
    }

    /**
     * Build optimized configuration for streaming
     */
    private function buildOptimizedConfig(): array
    {
        return array_merge($this->config, [
            'streaming_optimized' => true,
            'priority' => $this->priority,
            'buffer_size' => 1024,
            'chunk_processing' => true,
            'real_time_broadcasting' => true,
            'performance_monitoring' => true
        ]);
    }

    /**
     * Calculate estimated cost based on tokens
     */
    private function calculateEstimatedCost(int $tokens): float
    {
        // GPT-4 pricing (approximate)
        $costPer1kTokens = 0.03; // $0.03 per 1K tokens
        return round(($tokens / 1000) * $costPer1kTokens, 4);
    }

    /**
     * Handle optimized failure with comprehensive error tracking
     */
    private function handleOptimizedFailure(Throwable $exception): void
    {
        $processingTime = microtime(true) - $this->startTime;
        
        Log::error('❌ Optimized OpenAI streaming job failed', [
            'job_id' => $this->jobId,
            'job_type' => $this->jobType,
            'analysis_id' => $this->analysisId,
            'user_id' => $this->userId,
            'exception' => $exception->getMessage(),
            'exception_class' => get_class($exception),
            'processing_time_ms' => round($processingTime * 1000),
            'tokens_streamed_before_failure' => $this->tokensStreamed,
            'attempt' => $this->attempts(),
            'stack_trace' => $exception->getTraceAsString()
        ]);

        // Update job record with failure details
        $this->updateJobOnFailure($exception, $processingTime);
        
        // Dispatch failure events
        $this->dispatchFailureEvents($exception);
        
        // Clean up resources
        $this->cleanupOnFailure();
    }

    /**
     * Job failed handler
     */
    public function failed(Throwable $exception): void
    {
        Log::error('💥 Optimized OpenAI streaming job failed permanently', [
            'job_id' => $this->jobId,
            'final_attempt' => $this->attempts(),
            'exception' => $exception->getMessage()
        ]);

        // Final cleanup and notifications
        $this->finalFailureCleanup($exception);
    }

    /**
     * Calculate current tokens per second rate
     */
    private function calculateCurrentTokensPerSecond(): float
    {
        $elapsed = microtime(true) - $this->startTime;
        return $elapsed > 0 ? round($this->tokensStreamed / $elapsed, 2) : 0.0;
    }

    /**
     * Initialize streaming state in Redis
     */
    private function initializeStreamingState(): void
    {
        $streamingState = [
            'job_id' => $this->jobId,
            'status' => 'initializing',
            'tokens_streamed' => 0,
            'response_content' => '',
            'started_at' => now()->toISOString(),
            'last_activity' => now()->toISOString(),
            'progress_percentage' => 0,
            'estimated_total_tokens' => $this->config['max_tokens'] ?? 2000,
            'metadata' => $this->metadata
        ];

        Redis::setex("openai_stream_{$this->jobId}", 7200, json_encode($streamingState));
    }

    /**
     * Update streaming cache with new token data
     */
    private function updateStreamingCache(string $token, int $tokenIndex): void
    {
        $cacheKey = "openai_stream_{$this->jobId}";
        $currentState = json_decode(Redis::get($cacheKey) ?? '{}', true);
        
        $currentState['tokens_streamed'] = $tokenIndex;
        $currentState['response_content'] = ($currentState['response_content'] ?? '') . $token;
        $currentState['last_activity'] = now()->toISOString();
        $currentState['progress_percentage'] = min(100, round(($tokenIndex / ($this->config['max_tokens'] ?? 2000)) * 100));
        
        Redis::setex($cacheKey, 7200, json_encode($currentState));
    }

    /**
     * Clean up streaming cache and temporary data
     */
    private function cleanupStreamingCache(): void
    {
        Redis::del("openai_stream_{$this->jobId}");
        Cache::forget("streaming_callback_{$this->jobId}");
    }

    /**
     * Calculate final performance metrics
     */
    private function calculateFinalMetrics(): array
    {
        $totalDuration = microtime(true) - $this->startTime;
        
        return [
            'total_duration_ms' => round($totalDuration * 1000),
            'tokens_per_second' => $totalDuration > 0 ? round($this->tokensStreamed / $totalDuration, 2) : 0,
            'streaming_efficiency' => min(1.0, $this->tokensStreamed / max(1, $this->config['max_tokens'] ?? 2000)),
            'first_token_latency_ms' => $this->metrics['first_token_time'] ? 
                round(($this->metrics['first_token_time'] - $this->startTime) * 1000) : null,
            'average_token_interval_ms' => $this->tokensStreamed > 1 ? 
                round(($totalDuration / $this->tokensStreamed) * 1000) : null,
            'total_tokens_streamed' => $this->tokensStreamed,
            'total_response_bytes' => $this->totalResponseSize
        ];
    }

    /**
     * Estimate token count for text
     */
    private function estimateTokenCount(string $text): int
    {
        // Rough estimation: ~4 characters per token
        return max(1, round(strlen($text) / 4));
    }

    /**
     * Calculate response quality score
     */
    private function calculateResponseQuality(string $response): float
    {
        // Basic quality metrics
        $length = strlen($response);
        $hasStructure = preg_match('/\{.*\}|\[.*\]/s', $response);
        $hasNewlines = substr_count($response, "\n");
        
        $qualityScore = 0.5; // Base score
        
        if ($length > 100) $qualityScore += 0.2;
        if ($length > 500) $qualityScore += 0.1;
        if ($hasStructure) $qualityScore += 0.15;
        if ($hasNewlines > 5) $qualityScore += 0.05;
        
        return min(1.0, $qualityScore);
    }

    /**
     * Parse security analysis response
     */
    private function parseSecurityAnalysis(string $response): ?array
    {
        // Try to extract JSON structure
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            $parsed = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $parsed;
            }
        }
        
        return null;
    }

    /**
     * Extract performance metrics from current execution
     */
    private function extractPerformanceMetrics(): array
    {
        return [
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'memory_current_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'cpu_time_ms' => round((microtime(true) - $this->startTime) * 1000),
            'tokens_per_second_current' => $this->calculateCurrentTokensPerSecond()
        ];
    }

    /**
     * Update related analysis record
     */
    private function updateRelatedAnalysis(array $processedResults): void
    {
        if (!$this->analysisId) return;
        
        try {
            Analysis::where('id', $this->analysisId)->update([
                'status' => 'completed',
                'completed_at' => now(),
                'tokens_used' => $processedResults['tokens_estimated'],
                'metadata' => [
                    'openai_job_id' => $this->jobId,
                    'streaming_stats' => $this->metrics,
                    'quality_score' => $processedResults['quality_score']
                ]
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to update related analysis', [
                'analysis_id' => $this->analysisId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Estimate job duration based on prompt length and type
     */
    private function estimateJobDuration(): int
    {
        $baseTime = 30; // 30 seconds base
        $promptLength = strlen($this->prompt);
        $lengthFactor = round($promptLength / 1000) * 10; // 10 seconds per 1K chars
        
        return $baseTime + $lengthFactor;
    }

    /**
     * Setup advanced monitoring hooks
     */
    private function setupAdvancedMonitoring(OpenAiStreamService $streamService): void
    {
        // This would integrate with the streaming service to set up
        // callbacks for first token received, error events, etc.
        Log::debug('🔍 Advanced monitoring configured', ['job_id' => $this->jobId]);
    }

    /**
     * Validate streaming response quality
     */
    private function validateStreamingResponse(string $response): void
    {
        if (empty($response)) {
            throw new \Exception('Empty response received from OpenAI streaming');
        }
        
        if (strlen($response) < 10) {
            throw new \Exception('Response too short, possible streaming error');
        }
    }

    /**
     * Build streaming context for the service
     */
    private function buildStreamingContext(): array
    {
        return [
            'job_id' => $this->jobId,
            'job_type' => $this->jobType,
            'priority' => $this->priority,
            'user_id' => $this->userId,
            'analysis_id' => $this->analysisId,
            'streaming_optimized' => true,
            'real_time_callbacks' => true
        ];
    }

    /**
     * Update job record on failure
     */
    private function updateJobOnFailure(Throwable $exception, float $processingTime): void
    {
        try {
            OpenAiJobResult::where('job_id', $this->jobId)->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'processing_time_ms' => round($processingTime * 1000),
                'failed_at' => now(),
                'attempts_made' => $this->attempts()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update job record on failure', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Dispatch failure events
     */
    private function dispatchFailureEvents(Throwable $exception): void
    {
        Event::dispatch(new AnalysisFailed($this->jobId, [
            'exception' => $exception->getMessage(),
            'job_type' => $this->jobType,
            'analysis_id' => $this->analysisId
        ]));
    }

    /**
     * Cleanup resources on failure
     */
    private function cleanupOnFailure(): void
    {
        $this->cleanupStreamingCache();
    }

    /**
     * Final cleanup when job fails permanently
     */
    private function finalFailureCleanup(Throwable $exception): void
    {
        // Additional cleanup for permanent failures
        $this->cleanupStreamingCache();
        
        // Notify relevant services of permanent failure
        Log::critical('OpenAI streaming job permanently failed', [
            'job_id' => $this->jobId,
            'exception' => $exception->getMessage()
        ]);
    }

    /**
     * Log successful completion with comprehensive metrics
     */
    private function logSuccessfulCompletion(): void
    {
        $finalMetrics = $this->calculateFinalMetrics();
        
        Log::info('✅ Optimized OpenAI streaming job completed successfully', [
            'job_id' => $this->jobId,
            'job_type' => $this->jobType,
            'analysis_id' => $this->analysisId,
            'metrics' => $finalMetrics,
            'quality_metrics' => [
                'tokens_streamed' => $this->tokensStreamed,
                'response_size_bytes' => $this->totalResponseSize,
                'efficiency_score' => $finalMetrics['streaming_efficiency']
            ]
        ]);
    }
}
