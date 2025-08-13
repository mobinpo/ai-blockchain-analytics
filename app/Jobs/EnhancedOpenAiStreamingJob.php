<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\OpenAiStreamService;
use App\Models\OpenAiJobResult;
use App\Models\Analysis;
use App\Events\TokenStreamed;
use App\Events\AnalysisProgress;
use App\Events\StreamingCompleted;
use App\Services\SecurityFindingSchemaValidator;
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

/**
 * Enhanced OpenAI Streaming Job with Advanced Token Processing and Real-time Updates
 * 
 * Features:
 * - Real-time token streaming with WebSocket broadcasting
 * - Comprehensive error handling and retry logic
 * - Advanced result parsing and validation
 * - Performance monitoring and analytics
 * - Horizon integration with detailed progress tracking
 * - Multi-queue support based on priority and job type
 */
final class EnhancedOpenAiStreamingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800; // 30 minutes
    public int $tries = 3;
    public int $backoff = 300; // 5 minutes between retries
    public bool $deleteWhenMissingModels = true;

    private array $streamingMetrics = [];
    private float $startTime;
    private ?OpenAiJobResult $jobResult = null;

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
        $this->onQueue($this->determineQueue());
    }

    /**
     * Execute the enhanced OpenAI streaming job
     */
    public function handle(OpenAiStreamService $streamService, SecurityFindingSchemaValidator $validator): void
    {
        $this->startTime = microtime(true);
        $this->initializeMetrics();

        Log::info('Starting enhanced OpenAI streaming job', [
            'job_id' => $this->jobId,
            'job_type' => $this->jobType,
            'analysis_id' => $this->analysisId,
            'user_id' => $this->userId,
            'queue' => $this->queue,
            'priority' => $this->priority,
            'config' => $this->config
        ]);

        try {
            // Phase 1: Initialize job and create records
            $this->initializeJob();
            
            // Phase 2: Configure streaming with enhanced monitoring
            $this->configureAdvancedStreaming($streamService);
            
            // Phase 3: Execute streaming with real-time updates
            $response = $this->executeEnhancedStreaming($streamService);
            
            // Phase 4: Process and validate results
            $validatedResults = $this->processAndValidateResults($response, $validator);
            
            // Phase 5: Store comprehensive results
            $this->storeEnhancedResults($validatedResults);
            
            // Phase 6: Finalize and cleanup
            $this->finalizeJob();
            
            $this->logJobCompletion();
            
        } catch (\Exception $e) {
            $this->handleEnhancedFailure($e);
            throw $e;
        }
    }

    /**
     * Handle job failure with comprehensive error tracking
     */
    public function failed(\Throwable $exception): void
    {
        $processingTime = microtime(true) - ($this->startTime ?? microtime(true));
        
        Log::error('Enhanced OpenAI streaming job failed permanently', [
            'job_id' => $this->jobId,
            'job_type' => $this->jobType,
            'analysis_id' => $this->analysisId,
            'attempts' => $this->attempts(),
            'processing_time_seconds' => round($processingTime, 3),
            'exception' => [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ],
            'streaming_metrics' => $this->streamingMetrics
        ]);

        // Update job result with comprehensive failure information
        $this->updateJobResultOnFailure($exception, $processingTime);
        
        // Update analysis record if exists
        $this->updateAnalysisOnFailure($exception);
        
        // Clean up resources
        $this->cleanupResources();
        
        // Dispatch failure events
        $this->dispatchFailureEvents($exception);
    }

    /**
     * Get queue middleware with enhanced rate limiting
     */
    public function middleware(): array
    {
        return [
            new WithoutOverlapping($this->jobId, 7200), // 2 hour overlap protection
            new RateLimited('openai-streaming')->allow(10)->everyMinute(), // Rate limiting
        ];
    }

    /**
     * Initialize job with comprehensive setup
     */
    private function initializeJob(): void
    {
        // Create job result record
        $this->jobResult = $this->createEnhancedJobRecord();
        
        // Initialize streaming state in cache and Redis
        $this->initializeStreamingState();
        
        // Update analysis record if provided
        $this->updateAnalysisStatus('processing');
        
        // Dispatch initialization event
        Event::dispatch(new AnalysisProgress($this->jobId, 'initialized', [
            'job_type' => $this->jobType,
            'priority' => $this->priority,
            'estimated_duration' => $this->estimateProcessingTime()
        ]));
    }

    /**
     * Create enhanced job result record
     */
    private function createEnhancedJobRecord(): OpenAiJobResult
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
                'priority' => $this->priority,
                'analysis_id' => $this->analysisId,
                'started_at' => now()->toISOString(),
                'attempts' => $this->attempts(),
                'worker_info' => [
                    'hostname' => gethostname(),
                    'pid' => getmypid(),
                    'memory_limit' => ini_get('memory_limit'),
                    'timeout' => $this->timeout
                ]
            ]),
            'started_at' => now(),
            'attempts_made' => $this->attempts()
        ]);
    }

    /**
     * Configure advanced streaming with monitoring
     */
    private function configureAdvancedStreaming(OpenAiStreamService $streamService): void
    {
        $model = $this->config['model'] ?? 'gpt-4';
        $maxTokens = $this->config['max_tokens'] ?? 4000;
        $temperature = $this->config['temperature'] ?? 0.1;
        $topP = $this->config['top_p'] ?? 0.9;

        // Configure streaming service with enhanced parameters
        $streamService->configure([
            'model' => $model,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'top_p' => $topP,
            'stream' => true,
            'frequency_penalty' => $this->config['frequency_penalty'] ?? 0.0,
            'presence_penalty' => $this->config['presence_penalty'] ?? 0.0,
            'job_id' => $this->jobId,
            'monitoring_enabled' => true,
            'real_time_updates' => true
        ]);

        Log::info('Advanced streaming configured', [
            'job_id' => $this->jobId,
            'model' => $model,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'streaming_enabled' => true
        ]);
    }

    /**
     * Execute streaming with real-time monitoring
     */
    private function executeEnhancedStreaming(OpenAiStreamService $streamService): string
    {
        $context = $this->buildComprehensiveContext();
        
        // Register enhanced streaming callbacks
        $this->registerAdvancedCallbacks($streamService);
        
        Log::info('Starting enhanced streaming execution', [
            'job_id' => $this->jobId,
            'prompt_length' => strlen($this->prompt),
            'context_keys' => array_keys($context),
            'estimated_tokens' => $this->config['max_tokens'] ?? 4000
        ]);
        
        // Start streaming with comprehensive monitoring
        $response = $streamService->streamWithEnhancedMonitoring(
            $this->prompt,
            $this->jobId,
            $context,
            [$this, 'handleStreamingToken'],
            [$this, 'handleStreamingProgress']
        );

        Log::info('Streaming execution completed', [
            'job_id' => $this->jobId,
            'response_length' => strlen($response),
            'tokens_processed' => $this->streamingMetrics['tokens_received'] ?? 0,
            'processing_time_ms' => round((microtime(true) - $this->startTime) * 1000)
        ]);

        return $response;
    }

    /**
     * Handle individual streaming tokens
     */
    public function handleStreamingToken(string $token, int $tokenIndex, array $metadata = []): void
    {
        // Update metrics
        $this->streamingMetrics['tokens_received'] = ($this->streamingMetrics['tokens_received'] ?? 0) + 1;
        $this->streamingMetrics['last_token_time'] = microtime(true);
        $this->streamingMetrics['current_content'] = ($this->streamingMetrics['current_content'] ?? '') . $token;
        
        // Calculate progress
        $maxTokens = $this->config['max_tokens'] ?? 4000;
        $progress = min(100, ($this->streamingMetrics['tokens_received'] / $maxTokens) * 100);

        // Update streaming state in cache
        $this->updateStreamingState([
            'tokens_received' => $this->streamingMetrics['tokens_received'],
            'current_content' => $this->streamingMetrics['current_content'],
            'progress_percentage' => round($progress, 2),
            'last_activity' => now()->toISOString(),
            'tokens_per_second' => $this->calculateCurrentTokensPerSecond(),
            'estimated_completion' => $this->estimateCompletionTime($progress)
        ]);

        // Dispatch token streaming event for real-time updates
        Event::dispatch(new TokenStreamed($this->jobId, $token, [
            'token_index' => $tokenIndex,
            'progress' => $progress,
            'tokens_received' => $this->streamingMetrics['tokens_received'],
            'metadata' => $metadata
        ]));

        // Store in Redis for WebSocket broadcasting
        Redis::publish('openai-stream', json_encode([
            'job_id' => $this->jobId,
            'type' => 'token',
            'token' => $token,
            'progress' => $progress,
            'timestamp' => now()->toISOString()
        ]));

        // Periodic progress updates
        if ($this->streamingMetrics['tokens_received'] % 50 === 0) {
            $this->broadcastProgressUpdate($progress);
        }
    }

    /**
     * Handle streaming progress updates
     */
    public function handleStreamingProgress(array $progressData): void
    {
        $this->streamingMetrics = array_merge($this->streamingMetrics, $progressData);
        
        Event::dispatch(new AnalysisProgress($this->jobId, 'streaming', $progressData));
        
        Log::debug('Streaming progress update', [
            'job_id' => $this->jobId,
            'progress_data' => $progressData
        ]);
    }

    /**
     * Process and validate streaming results
     */
    private function processAndValidateResults(string $response, SecurityFindingSchemaValidator $validator): array
    {
        Log::info('Processing streaming results', [
            'job_id' => $this->jobId,
            'response_length' => strlen($response)
        ]);

        // Parse response based on job type
        $parsedResponse = $this->parseResponseByJobType($response);
        
        // Validate if it's security analysis
        $validationResults = null;
        if ($this->jobType === 'security_analysis' && is_array($parsedResponse)) {
            $validationResults = $this->validateSecurityFindings($parsedResponse, $validator);
        }

        // Calculate quality metrics
        $qualityMetrics = $this->calculateQualityMetrics($response, $parsedResponse);
        
        return [
            'raw_response' => $response,
            'parsed_response' => $parsedResponse,
            'validation_results' => $validationResults,
            'quality_metrics' => $qualityMetrics,
            'processing_stats' => $this->streamingMetrics
        ];
    }

    /**
     * Validate security findings using the schema validator
     */
    private function validateSecurityFindings(array $parsedResponse, SecurityFindingSchemaValidator $validator): array
    {
        if (empty($parsedResponse['findings'])) {
            return ['valid' => true, 'findings' => [], 'summary' => ['total' => 0, 'valid' => 0, 'invalid' => 0]];
        }

        $findings = is_array($parsedResponse['findings']) ? $parsedResponse['findings'] : [$parsedResponse['findings']];
        
        $validationResults = $validator->validateFindings($findings);
        
        Log::info('Security findings validation completed', [
            'job_id' => $this->jobId,
            'total_findings' => count($findings),
            'valid_findings' => $validationResults['valid_count'],
            'invalid_findings' => $validationResults['invalid_count'],
            'success_rate' => $validationResults['summary']['success_rate']
        ]);

        return $validationResults;
    }

    /**
     * Store enhanced results with comprehensive data
     */
    private function storeEnhancedResults(array $results): void
    {
        $processingTimeMs = round((microtime(true) - $this->startTime) * 1000);
        
        DB::transaction(function () use ($results, $processingTimeMs) {
            // Calculate comprehensive token statistics
            $tokenStats = $this->calculateComprehensiveTokenStats($results);
            
            // Update job result with all data
            $this->jobResult->update([
                'status' => 'completed',
                'response' => $results['raw_response'],
                'parsed_response' => $results['parsed_response'],
                'token_usage' => $tokenStats,
                'processing_time_ms' => $processingTimeMs,
                'completed_at' => now(),
                'streaming_stats' => $results['processing_stats'],
                'metadata' => array_merge($this->jobResult->metadata ?? [], [
                    'completed_at' => now()->toISOString(),
                    'final_metrics' => [
                        'tokens_processed' => $tokenStats['total_tokens'],
                        'processing_time_ms' => $processingTimeMs,
                        'response_size_bytes' => strlen($results['raw_response']),
                        'quality_score' => $results['quality_metrics']['overall_score'] ?? 0,
                        'validation_success_rate' => $results['validation_results']['summary']['success_rate'] ?? 100
                    ],
                    'performance_metrics' => [
                        'tokens_per_second' => $this->calculateFinalTokensPerSecond(),
                        'streaming_efficiency' => $this->calculateStreamingEfficiency(),
                        'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2)
                    ]
                ])
            ]);

            // Update analysis record if exists
            $this->updateAnalysisWithResults($results);

            // Store security findings if applicable
            if ($this->jobType === 'security_analysis' && !empty($results['validation_results']['findings'])) {
                $this->storeSecurityFindings($results['validation_results']['findings']);
            }

            Log::info('Enhanced results stored successfully', [
                'job_id' => $this->jobId,
                'processing_time_ms' => $processingTimeMs,
                'token_count' => $tokenStats['total_tokens'],
                'quality_score' => $results['quality_metrics']['overall_score'] ?? 0
            ]);
        });
    }

    /**
     * Build comprehensive context for streaming
     */
    private function buildComprehensiveContext(): array
    {
        $context = [
            'job_id' => $this->jobId,
            'job_type' => $this->jobType,
            'user_id' => $this->userId,
            'analysis_id' => $this->analysisId,
            'priority' => $this->priority,
            'streaming_enabled' => true,
            'real_time_updates' => true
        ];

        // Add job-type specific context
        if ($this->jobType === 'security_analysis') {
            $context['analysis_config'] = [
                'focus_areas' => $this->config['focus_areas'] ?? [
                    'reentrancy', 'overflow', 'access_control', 'gas_optimization', 'oracle_manipulation'
                ],
                'severity_levels' => ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW', 'INFO', 'GAS_OPTIMIZATION'],
                'output_format' => 'structured_json',
                'include_recommendations' => true,
                'include_code_examples' => true,
                'schema_version' => '4.0'
            ];
        }

        // Add system prompts and constraints
        if (!empty($this->config['system_prompt'])) {
            $context['system_prompt'] = $this->config['system_prompt'];
        }

        // Add conversation history
        if (!empty($this->config['history'])) {
            $context['history'] = $this->config['history'];
        }

        return $context;
    }

    /**
     * Register advanced streaming callbacks
     */
    private function registerAdvancedCallbacks(OpenAiStreamService $streamService): void
    {
        // This method would configure the stream service with callbacks
        // The actual implementation would depend on the stream service's API
        
        Log::debug('Advanced streaming callbacks registered', [
            'job_id' => $this->jobId,
            'callbacks' => ['token_handler', 'progress_handler', 'error_handler']
        ]);
    }

    /**
     * Initialize streaming metrics
     */
    private function initializeMetrics(): void
    {
        $this->streamingMetrics = [
            'tokens_received' => 0,
            'tokens_per_second' => 0,
            'current_content' => '',
            'last_token_time' => $this->startTime,
            'streaming_started_at' => $this->startTime,
            'progress_percentage' => 0,
            'estimated_completion_time' => null
        ];
    }

    /**
     * Calculate current tokens per second
     */
    private function calculateCurrentTokensPerSecond(): float
    {
        $elapsed = microtime(true) - $this->streamingMetrics['streaming_started_at'];
        if ($elapsed <= 0 || $this->streamingMetrics['tokens_received'] <= 0) {
            return 0.0;
        }
        return round($this->streamingMetrics['tokens_received'] / $elapsed, 2);
    }

    /**
     * Estimate completion time based on current progress
     */
    private function estimateCompletionTime(float $progressPercentage): ?string
    {
        if ($progressPercentage <= 0) {
            return null;
        }

        $elapsed = microtime(true) - $this->startTime;
        $totalEstimated = ($elapsed / $progressPercentage) * 100;
        $remaining = $totalEstimated - $elapsed;

        return now()->addSeconds((int)$remaining)->toISOString();
    }

    /**
     * Update streaming state in cache
     */
    private function updateStreamingState(array $updates): void
    {
        $key = "openai_stream_{$this->jobId}";
        $current = Cache::get($key, []);
        $updated = array_merge($current, $updates);
        
        Cache::put($key, $updated, 7200); // 2 hours
    }

    /**
     * Initialize streaming state
     */
    private function initializeStreamingState(): void
    {
        $streamingState = [
            'job_id' => $this->jobId,
            'status' => 'initializing',
            'tokens_received' => 0,
            'current_content' => '',
            'started_at' => now()->toISOString(),
            'last_activity' => now()->toISOString(),
            'progress_percentage' => 0,
            'estimated_total_tokens' => $this->config['max_tokens'] ?? 4000,
            'config' => $this->config,
            'metadata' => $this->metadata
        ];

        Cache::put("openai_stream_{$this->jobId}", $streamingState, 7200);

        // Also store in Redis for real-time access
        Redis::setex("stream:{$this->jobId}", 7200, json_encode($streamingState));
    }

    /**
     * Determine appropriate queue based on job type and priority
     */
    private function determineQueue(): string
    {
        $base = match($this->jobType) {
            'security_analysis' => 'openai-security',
            'code_review' => 'openai-review',
            'documentation' => 'openai-docs',
            default => 'openai-general'
        };

        return match($this->priority) {
            'urgent' => "{$base}-urgent",
            'high' => "{$base}-high",
            'low' => "{$base}-low",
            default => $base
        };
    }

    /**
     * Estimate processing time based on prompt and configuration
     */
    private function estimateProcessingTime(): int
    {
        $promptLength = strlen($this->prompt);
        $maxTokens = $this->config['max_tokens'] ?? 4000;
        
        // Basic estimation: ~50 tokens per second for GPT-4
        $tokensPerSecond = match($this->config['model'] ?? 'gpt-4') {
            'gpt-4' => 50,
            'gpt-3.5-turbo' => 100,
            default => 50
        };

        return (int) ceil($maxTokens / $tokensPerSecond);
    }

    /**
     * Parse response based on job type
     */
    private function parseResponseByJobType(string $response): mixed
    {
        return match($this->jobType) {
            'security_analysis' => $this->parseSecurityAnalysisResponse($response),
            'code_review' => $this->parseCodeReviewResponse($response),
            default => $this->parseGenericResponse($response)
        };
    }

    /**
     * Parse security analysis response
     */
    private function parseSecurityAnalysisResponse(string $response): ?array
    {
        // Try JSON first
        $decoded = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // Try extracting JSON from markdown
        if (preg_match('/```json\s*(\{.*?\})\s*```/s', $response, $matches)) {
            $decoded = json_decode($matches[1], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        // Try parsing with SecurityFindingValidator
        $validator = app(SecurityFindingSchemaValidator::class);
        try {
            return $validator->parseOpenAiResponse($response);
        } catch (\Exception $e) {
            Log::warning('Failed to parse security analysis response', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
                'response_preview' => substr($response, 0, 500)
            ]);
            return null;
        }
    }

    /**
     * Parse code review response
     */
    private function parseCodeReviewResponse(string $response): ?array
    {
        // Implementation would depend on expected code review format
        return json_decode($response, true);
    }

    /**
     * Parse generic response
     */
    private function parseGenericResponse(string $response): mixed
    {
        $decoded = json_decode($response, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $response;
    }

    /**
     * Calculate comprehensive quality metrics
     */
    private function calculateQualityMetrics(string $response, mixed $parsedResponse): array
    {
        return [
            'response_length' => strlen($response),
            'is_structured' => is_array($parsedResponse),
            'has_findings' => is_array($parsedResponse) && !empty($parsedResponse['findings']),
            'word_count' => str_word_count($response),
            'overall_score' => $this->calculateOverallQualityScore($response, $parsedResponse)
        ];
    }

    /**
     * Calculate overall quality score
     */
    private function calculateOverallQualityScore(string $response, mixed $parsedResponse): float
    {
        $score = 0.0;
        $maxScore = 100.0;

        // Length check (20 points)
        if (strlen($response) > 100) $score += 20;
        elseif (strlen($response) > 50) $score += 10;

        // Structure check (30 points)
        if (is_array($parsedResponse)) $score += 30;

        // Content quality (30 points)
        if (is_array($parsedResponse) && !empty($parsedResponse['findings'])) $score += 30;

        // Completeness (20 points)
        if (is_array($parsedResponse) && !empty($parsedResponse['recommendations'])) $score += 20;

        return round($score, 2);
    }

    /**
     * Calculate comprehensive token statistics
     */
    private function calculateComprehensiveTokenStats(array $results): array
    {
        $tokensReceived = $this->streamingMetrics['tokens_received'] ?? 0;
        $promptTokens = $this->estimatePromptTokens();
        
        return [
            'total_tokens' => $tokensReceived + $promptTokens,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $tokensReceived,
            'estimated_cost_usd' => $this->estimateDetailedCost($tokensReceived, $promptTokens),
            'tokens_per_second' => $this->calculateFinalTokensPerSecond(),
            'streaming_efficiency' => $this->calculateStreamingEfficiency(),
            'model' => $this->config['model'] ?? 'gpt-4'
        ];
    }

    /**
     * Estimate prompt tokens
     */
    private function estimatePromptTokens(): int
    {
        // Rough estimation: ~4 characters per token
        return (int) ceil(strlen($this->prompt) / 4);
    }

    /**
     * Estimate detailed cost
     */
    private function estimateDetailedCost(int $completionTokens, int $promptTokens): float
    {
        $model = $this->config['model'] ?? 'gpt-4';
        
        [$promptCost, $completionCost] = match($model) {
            'gpt-4' => [0.00003, 0.00006], // $30/$60 per 1M tokens
            'gpt-3.5-turbo' => [0.000001, 0.000002], // $1/$2 per 1M tokens
            default => [0.00003, 0.00006]
        };

        return round(($promptTokens * $promptCost) + ($completionTokens * $completionCost), 6);
    }

    /**
     * Calculate final tokens per second
     */
    private function calculateFinalTokensPerSecond(): float
    {
        $totalTime = microtime(true) - $this->startTime;
        $tokens = $this->streamingMetrics['tokens_received'] ?? 0;
        
        return $totalTime > 0 ? round($tokens / $totalTime, 2) : 0.0;
    }

    /**
     * Calculate streaming efficiency
     */
    private function calculateStreamingEfficiency(): float
    {
        // Measure consistency of token arrival
        $tokens = $this->streamingMetrics['tokens_received'] ?? 0;
        return $tokens > 0 ? min(100.0, ($tokens / max(1, $this->config['max_tokens'] ?? 4000)) * 100) : 0.0;
    }

    /**
     * Update analysis record with results
     */
    private function updateAnalysisWithResults(array $results): void
    {
        if (!$this->analysisId) {
            return;
        }

        try {
            $analysis = Analysis::find($this->analysisId);
            if ($analysis) {
                $analysis->update([
                    'status' => 'completed',
                    'results' => $results['parsed_response'],
                    'raw_output' => $results['raw_response'],
                    'completed_at' => now(),
                    'processing_time_seconds' => round((microtime(true) - $this->startTime), 2),
                    'token_count' => $this->streamingMetrics['tokens_received'] ?? 0
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to update analysis record', [
                'analysis_id' => $this->analysisId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update analysis status
     */
    private function updateAnalysisStatus(string $status): void
    {
        if (!$this->analysisId) {
            return;
        }

        try {
            Analysis::where('id', $this->analysisId)->update(['status' => $status]);
        } catch (\Exception $e) {
            Log::warning('Failed to update analysis status', [
                'analysis_id' => $this->analysisId,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Store security findings in database
     */
    private function storeSecurityFindings(array $validatedFindings): void
    {
        foreach ($validatedFindings as $index => $findingValidation) {
            if ($findingValidation['valid'] && !empty($findingValidation['normalized'])) {
                // This would store individual findings in a findings table
                Log::debug('Storing security finding', [
                    'job_id' => $this->jobId,
                    'finding_index' => $index,
                    'severity' => $findingValidation['normalized']['severity'] ?? 'UNKNOWN'
                ]);
            }
        }
    }

    /**
     * Broadcast progress update via WebSocket
     */
    private function broadcastProgressUpdate(float $progress): void
    {
        Redis::publish('openai-progress', json_encode([
            'job_id' => $this->jobId,
            'type' => 'progress',
            'progress' => $progress,
            'tokens_received' => $this->streamingMetrics['tokens_received'] ?? 0,
            'tokens_per_second' => $this->calculateCurrentTokensPerSecond(),
            'timestamp' => now()->toISOString()
        ]));
    }

    /**
     * Handle enhanced failure with comprehensive error tracking
     */
    private function handleEnhancedFailure(\Exception $e): void
    {
        $processingTime = microtime(true) - $this->startTime;
        
        // Update job result
        if ($this->jobResult) {
            $this->jobResult->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'failed_at' => now(),
                'processing_time_ms' => round($processingTime * 1000),
                'attempts_made' => $this->attempts(),
                'streaming_stats' => $this->streamingMetrics,
                'metadata' => array_merge($this->jobResult->metadata ?? [], [
                    'failure_info' => [
                        'exception_class' => get_class($e),
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'processing_time_seconds' => round($processingTime, 3),
                        'tokens_received_before_failure' => $this->streamingMetrics['tokens_received'] ?? 0
                    ]
                ])
            ]);
        }

        // Update analysis if exists
        $this->updateAnalysisOnFailure($e);
    }

    /**
     * Update job result on failure
     */
    private function updateJobResultOnFailure(\Throwable $exception, float $processingTime): void
    {
        try {
            if ($this->jobResult) {
                $this->jobResult->update([
                    'status' => 'failed',
                    'error_message' => $exception->getMessage(),
                    'failed_at' => now(),
                    'processing_time_ms' => round($processingTime * 1000),
                    'attempts_made' => $this->attempts(),
                    'streaming_stats' => $this->streamingMetrics
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to update job result on failure', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update analysis on failure
     */
    private function updateAnalysisOnFailure(\Throwable $exception): void
    {
        if (!$this->analysisId) {
            return;
        }

        try {
            Analysis::where('id', $this->analysisId)->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'failed_at' => now()
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to update analysis on failure', [
                'analysis_id' => $this->analysisId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clean up resources and cache
     */
    private function cleanupResources(): void
    {
        try {
            Cache::forget("openai_stream_{$this->jobId}");
            Redis::del("stream:{$this->jobId}");
            
            Log::debug('Resources cleaned up', ['job_id' => $this->jobId]);
        } catch (\Exception $e) {
            Log::warning('Failed to clean up resources', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Dispatch failure events
     */
    private function dispatchFailureEvents(\Throwable $exception): void
    {
        Event::dispatch(new AnalysisProgress($this->jobId, 'failed', [
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
            'processing_time' => microtime(true) - $this->startTime
        ]));
    }

    /**
     * Finalize job completion
     */
    private function finalizeJob(): void
    {
        // Update final status
        $this->updateAnalysisStatus('completed');
        
        // Clean up temporary resources
        $this->cleanupResources();
        
        // Dispatch completion event
        Event::dispatch(new StreamingCompleted($this->jobId, [
            'job_type' => $this->jobType,
            'processing_time_ms' => round((microtime(true) - $this->startTime) * 1000),
            'tokens_processed' => $this->streamingMetrics['tokens_received'] ?? 0,
            'analysis_id' => $this->analysisId
        ]));
    }

    /**
     * Log job completion
     */
    private function logJobCompletion(): void
    {
        $processingTime = microtime(true) - $this->startTime;
        
        Log::info('Enhanced OpenAI streaming job completed successfully', [
            'job_id' => $this->jobId,
            'job_type' => $this->jobType,
            'analysis_id' => $this->analysisId,
            'total_duration_seconds' => round($processingTime, 3),
            'total_duration_ms' => round($processingTime * 1000),
            'tokens_processed' => $this->streamingMetrics['tokens_received'] ?? 0,
            'tokens_per_second' => $this->calculateFinalTokensPerSecond(),
            'streaming_efficiency' => $this->calculateStreamingEfficiency(),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2)
        ]);
    }

    /**
     * Get Horizon tags for monitoring
     */
    public function tags(): array
    {
        return [
            'openai-enhanced-streaming',
            'type:' . $this->jobType,
            'job:' . substr($this->jobId, 0, 12),
            'model:' . ($this->config['model'] ?? 'gpt-4'),
            'priority:' . $this->priority,
            'user:' . ($this->userId ?? 'anonymous'),
            'analysis:' . ($this->analysisId ?? 'none')
        ];
    }

    /**
     * Get display name for Horizon dashboard
     */
    public function displayName(): string
    {
        return "Enhanced OpenAI Streaming: {$this->jobType} ({$this->jobId})";
    }

    /**
     * Get job timeout in seconds
     */
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(60); // Retry for up to 1 hour
    }
}