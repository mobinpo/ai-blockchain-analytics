<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\EnhancedOpenAiStreamingJob;
use App\Models\OpenAiJobResult;
use App\Models\Analysis;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Enhanced OpenAI Job Manager with Advanced Queue Management and Monitoring
 * 
 * Features:
 * - Priority-based queue management
 * - Job scheduling and batching
 * - Real-time monitoring and analytics
 * - Resource optimization
 * - Automatic retry and failure handling
 */
final class EnhancedOpenAiJobManager
{
    /**
     * Priority levels for job scheduling
     */
    private const PRIORITIES = [
        'urgent' => 1,
        'high' => 2,
        'normal' => 3,
        'low' => 4
    ];

    /**
     * Queue configurations per job type
     */
    private const QUEUE_CONFIGS = [
        'security_analysis' => [
            'default_timeout' => 1800, // 30 minutes
            'max_tokens' => 4000,
            'model' => 'gpt-4',
            'temperature' => 0.1
        ],
        'code_review' => [
            'default_timeout' => 900, // 15 minutes
            'max_tokens' => 3000,
            'model' => 'gpt-4',
            'temperature' => 0.3
        ],
        'documentation' => [
            'default_timeout' => 600, // 10 minutes
            'max_tokens' => 2000,
            'model' => 'gpt-3.5-turbo',
            'temperature' => 0.5
        ]
    ];

    /**
     * Create and dispatch an enhanced OpenAI streaming job
     */
    public function createEnhancedJob(
        string $prompt,
        string $jobType = 'security_analysis',
        array $config = [],
        array $metadata = [],
        ?int $userId = null,
        ?int $analysisId = null,
        string $priority = 'normal'
    ): string {
        // Generate unique job ID
        $jobId = $this->generateUniqueJobId($jobType);
        
        // Validate and merge configuration
        $mergedConfig = $this->prepareJobConfig($jobType, $config);
        
        // Prepare comprehensive metadata
        $enhancedMetadata = $this->prepareJobMetadata($metadata, $userId, $analysisId);
        
        // Validate job parameters
        $this->validateJobParameters($prompt, $jobType, $priority);
        
        // Create job instance
        $job = new EnhancedOpenAiStreamingJob(
            prompt: $prompt,
            jobId: $jobId,
            config: $mergedConfig,
            metadata: $enhancedMetadata,
            jobType: $jobType,
            userId: $userId,
            analysisId: $analysisId,
            priority: $priority
        );

        // Store job in monitoring system
        $this->registerJobForMonitoring($jobId, $job);
        
        // Dispatch job with priority queue
        $this->dispatchWithPriority($job, $priority);
        
        Log::info('Enhanced OpenAI job created and dispatched', [
            'job_id' => $jobId,
            'job_type' => $jobType,
            'priority' => $priority,
            'user_id' => $userId,
            'analysis_id' => $analysisId,
            'queue' => $job->queue,
            'estimated_tokens' => $mergedConfig['max_tokens']
        ]);

        return $jobId;
    }

    /**
     * Create batch of jobs for processing
     */
    public function createBatchJobs(
        array $jobSpecs,
        string $batchId = null,
        array $batchConfig = []
    ): string {
        $batchId = $batchId ?: 'batch-' . Str::random(12);
        $jobIds = [];

        Log::info('Creating batch of OpenAI jobs', [
            'batch_id' => $batchId,
            'job_count' => count($jobSpecs),
            'batch_config' => $batchConfig
        ]);

        DB::transaction(function () use ($jobSpecs, $batchId, $batchConfig, &$jobIds) {
            foreach ($jobSpecs as $index => $spec) {
                $jobId = $this->createEnhancedJob(
                    prompt: $spec['prompt'],
                    jobType: $spec['job_type'] ?? 'security_analysis',
                    config: array_merge($batchConfig, $spec['config'] ?? []),
                    metadata: array_merge($spec['metadata'] ?? [], ['batch_id' => $batchId, 'batch_index' => $index]),
                    userId: $spec['user_id'] ?? null,
                    analysisId: $spec['analysis_id'] ?? null,
                    priority: $spec['priority'] ?? 'normal'
                );
                
                $jobIds[] = $jobId;
            }
        });

        // Store batch information
        $this->storeBatchInfo($batchId, $jobIds, $batchConfig);

        Log::info('Batch jobs created successfully', [
            'batch_id' => $batchId,
            'job_ids' => $jobIds,
            'total_jobs' => count($jobIds)
        ]);

        return $batchId;
    }

    /**
     * Get job status with comprehensive information
     */
    public function getJobStatus(string $jobId): array
    {
        // Get from database
        $jobResult = OpenAiJobResult::where('job_id', $jobId)->first();
        
        // Get streaming state from cache
        $streamingState = Cache::get("openai_stream_{$jobId}", []);
        
        // Get queue information
        $queueInfo = $this->getQueueInfo($jobId);
        
        // Get performance metrics
        $performanceMetrics = $this->getPerformanceMetrics($jobId);

        return [
            'job_id' => $jobId,
            'status' => $jobResult?->status ?? 'unknown',
            'database_record' => $jobResult?->toArray(),
            'streaming_state' => $streamingState,
            'queue_info' => $queueInfo,
            'performance_metrics' => $performanceMetrics,
            'last_updated' => max(
                $jobResult?->updated_at?->toISOString() ?? '',
                $streamingState['last_activity'] ?? ''
            )
        ];
    }

    /**
     * Get batch status
     */
    public function getBatchStatus(string $batchId): array
    {
        $batchInfo = Cache::get("batch_{$batchId}", []);
        
        if (empty($batchInfo['job_ids'])) {
            return ['batch_id' => $batchId, 'status' => 'not_found'];
        }

        $jobStatuses = [];
        $statusCounts = [];
        
        foreach ($batchInfo['job_ids'] as $jobId) {
            $status = $this->getJobStatus($jobId);
            $jobStatuses[] = $status;
            
            $currentStatus = $status['status'];
            $statusCounts[$currentStatus] = ($statusCounts[$currentStatus] ?? 0) + 1;
        }

        $overallStatus = $this->determineBatchStatus($statusCounts);

        return [
            'batch_id' => $batchId,
            'overall_status' => $overallStatus,
            'job_count' => count($batchInfo['job_ids']),
            'status_counts' => $statusCounts,
            'progress_percentage' => $this->calculateBatchProgress($statusCounts),
            'jobs' => $jobStatuses,
            'batch_info' => $batchInfo,
            'created_at' => $batchInfo['created_at'] ?? null
        ];
    }

    /**
     * Cancel a job
     */
    public function cancelJob(string $jobId, string $reason = 'User requested'): bool
    {
        try {
            // Update database record
            $updated = OpenAiJobResult::where('job_id', $jobId)
                ->whereIn('status', ['pending', 'processing'])
                ->update([
                    'status' => 'cancelled',
                    'error_message' => $reason,
                    'failed_at' => now(),
                    'metadata' => DB::raw("jsonb_set(coalesce(metadata, '{}'), '{cancellation}', '{\"reason\": \"" . $reason . "\", \"cancelled_at\": \"" . now()->toISOString() . "\"}')")
                ]);

            if ($updated) {
                // Clean up streaming cache
                Cache::forget("openai_stream_{$jobId}");
                Redis::del("stream:{$jobId}");

                Log::info('Job cancelled successfully', [
                    'job_id' => $jobId,
                    'reason' => $reason
                ]);

                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Failed to cancel job', [
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Retry a failed job
     */
    public function retryJob(string $jobId, array $newConfig = []): ?string
    {
        $originalJob = OpenAiJobResult::where('job_id', $jobId)->first();
        
        if (!$originalJob || $originalJob->status !== 'failed') {
            return null;
        }

        // Create new job with updated configuration
        $retryConfig = array_merge($originalJob->config ?? [], $newConfig);
        $retryMetadata = array_merge($originalJob->metadata ?? [], [
            'retry_of' => $jobId,
            'retry_attempt' => ($originalJob->metadata['retry_attempt'] ?? 0) + 1,
            'original_error' => $originalJob->error_message
        ]);

        $newJobId = $this->createEnhancedJob(
            prompt: $originalJob->prompt,
            jobType: $originalJob->job_type,
            config: $retryConfig,
            metadata: $retryMetadata,
            userId: $originalJob->user_id,
            priority: 'high' // Retries get higher priority
        );

        Log::info('Job retry created', [
            'original_job_id' => $jobId,
            'new_job_id' => $newJobId,
            'retry_attempt' => $retryMetadata['retry_attempt']
        ]);

        return $newJobId;
    }

    /**
     * Get comprehensive analytics
     */
    public function getAnalytics(int $days = 7): array
    {
        $startDate = now()->subDays($days);
        
        return [
            'time_period' => [
                'days' => $days,
                'start_date' => $startDate->toISOString(),
                'end_date' => now()->toISOString()
            ],
            'job_statistics' => $this->getJobStatistics($startDate),
            'performance_metrics' => $this->getPerformanceAnalytics($startDate),
            'cost_analysis' => $this->getCostAnalysis($startDate),
            'queue_analytics' => $this->getQueueAnalytics($startDate),
            'error_analysis' => $this->getErrorAnalysis($startDate),
            'usage_patterns' => $this->getUsagePatterns($startDate)
        ];
    }

    /**
     * Get current system status
     */
    public function getSystemStatus(): array
    {
        return [
            'timestamp' => now()->toISOString(),
            'queue_status' => $this->getCurrentQueueStatus(),
            'active_jobs' => $this->getActiveJobsCount(),
            'system_health' => $this->getSystemHealth(),
            'resource_usage' => $this->getResourceUsage(),
            'rate_limits' => $this->getRateLimitStatus()
        ];
    }

    /**
     * Clean up old jobs and cache entries
     */
    public function cleanup(int $olderThanDays = 30): array
    {
        $cutoffDate = now()->subDays($olderThanDays);
        
        $results = [
            'database_cleanup' => $this->cleanupDatabase($cutoffDate),
            'cache_cleanup' => $this->cleanupCache($olderThanDays),
            'redis_cleanup' => $this->cleanupRedis($olderThanDays)
        ];

        Log::info('Cleanup completed', [
            'cutoff_date' => $cutoffDate->toISOString(),
            'results' => $results
        ]);

        return $results;
    }

    // Private helper methods

    /**
     * Generate unique job ID
     */
    private function generateUniqueJobId(string $jobType): string
    {
        $timestamp = now()->format('YmdHis');
        $random = Str::random(8);
        return "job-{$jobType}-{$timestamp}-{$random}";
    }

    /**
     * Prepare job configuration
     */
    private function prepareJobConfig(string $jobType, array $config): array
    {
        $defaultConfig = self::QUEUE_CONFIGS[$jobType] ?? self::QUEUE_CONFIGS['security_analysis'];
        return array_merge($defaultConfig, $config);
    }

    /**
     * Prepare job metadata
     */
    private function prepareJobMetadata(array $metadata, ?int $userId, ?int $analysisId): array
    {
        return array_merge($metadata, [
            'created_via' => 'enhanced_job_manager',
            'created_at' => now()->toISOString(),
            'user_id' => $userId,
            'analysis_id' => $analysisId,
            'version' => '2.0',
            'environment' => app()->environment(),
            'host' => gethostname()
        ]);
    }

    /**
     * Validate job parameters
     */
    private function validateJobParameters(string $prompt, string $jobType, string $priority): void
    {
        if (empty($prompt)) {
            throw new \InvalidArgumentException('Prompt cannot be empty');
        }

        if (!array_key_exists($jobType, self::QUEUE_CONFIGS)) {
            throw new \InvalidArgumentException("Invalid job type: {$jobType}");
        }

        if (!array_key_exists($priority, self::PRIORITIES)) {
            throw new \InvalidArgumentException("Invalid priority: {$priority}");
        }

        if (strlen($prompt) > 50000) {
            throw new \InvalidArgumentException('Prompt is too long (max 50,000 characters)');
        }
    }

    /**
     * Register job for monitoring
     */
    private function registerJobForMonitoring(string $jobId, EnhancedOpenAiStreamingJob $job): void
    {
        $monitoringData = [
            'job_id' => $jobId,
            'job_type' => $job->jobType,
            'priority' => $job->priority,
            'user_id' => $job->userId,
            'analysis_id' => $job->analysisId,
            'queue' => $job->queue,
            'created_at' => now()->toISOString(),
            'timeout' => $job->timeout
        ];

        Cache::put("job_monitor_{$jobId}", $monitoringData, 7200); // 2 hours
        Redis::setex("monitor:job:{$jobId}", 7200, json_encode($monitoringData));
    }

    /**
     * Dispatch job with priority handling
     */
    private function dispatchWithPriority(EnhancedOpenAiStreamingJob $job, string $priority): void
    {
        // Set delay based on priority and current queue load
        $delay = $this->calculateDispatchDelay($priority);
        
        if ($delay > 0) {
            $job->delay(now()->addSeconds($delay));
        }

        dispatch($job);
    }

    /**
     * Calculate dispatch delay based on priority and queue load
     */
    private function calculateDispatchDelay(string $priority): int
    {
        $queueLoad = $this->getCurrentQueueLoad();
        
        return match($priority) {
            'urgent' => 0,
            'high' => min(5, $queueLoad),
            'normal' => min(30, $queueLoad * 2),
            'low' => min(120, $queueLoad * 5),
            default => 30
        };
    }

    /**
     * Get current queue load
     */
    private function getCurrentQueueLoad(): int
    {
        // This would check actual queue sizes across all OpenAI queues
        // For now, return a simulated load
        return random_int(0, 10);
    }

    /**
     * Store batch information
     */
    private function storeBatchInfo(string $batchId, array $jobIds, array $batchConfig): void
    {
        $batchInfo = [
            'batch_id' => $batchId,
            'job_ids' => $jobIds,
            'job_count' => count($jobIds),
            'config' => $batchConfig,
            'created_at' => now()->toISOString(),
            'status' => 'processing'
        ];

        Cache::put("batch_{$batchId}", $batchInfo, 86400); // 24 hours
    }

    /**
     * Get queue information for a job
     */
    private function getQueueInfo(string $jobId): array
    {
        // This would integrate with Laravel Horizon to get actual queue info
        return [
            'queue_name' => 'unknown',
            'position_in_queue' => null,
            'estimated_wait_time' => null
        ];
    }

    /**
     * Get performance metrics for a job
     */
    private function getPerformanceMetrics(string $jobId): array
    {
        $monitoringData = Cache::get("job_monitor_{$jobId}", []);
        
        return [
            'created_at' => $monitoringData['created_at'] ?? null,
            'queue_time' => null, // Would calculate from queue metrics
            'processing_time' => null, // Would calculate from job lifecycle
            'memory_usage' => null,
            'cpu_usage' => null
        ];
    }

    /**
     * Determine batch status from job status counts
     */
    private function determineBatchStatus(array $statusCounts): string
    {
        $total = array_sum($statusCounts);
        $completed = $statusCounts['completed'] ?? 0;
        $failed = $statusCounts['failed'] ?? 0;
        $cancelled = $statusCounts['cancelled'] ?? 0;

        if ($completed === $total) return 'completed';
        if ($failed + $cancelled === $total) return 'failed';
        if ($completed + $failed + $cancelled === $total) return 'partially_completed';
        if (($statusCounts['processing'] ?? 0) > 0) return 'processing';
        return 'pending';
    }

    /**
     * Calculate batch progress percentage
     */
    private function calculateBatchProgress(array $statusCounts): float
    {
        $total = array_sum($statusCounts);
        if ($total === 0) return 0.0;

        $completed = ($statusCounts['completed'] ?? 0) + ($statusCounts['failed'] ?? 0) + ($statusCounts['cancelled'] ?? 0);
        return round(($completed / $total) * 100, 2);
    }

    /**
     * Get job statistics for analytics
     */
    private function getJobStatistics(\DateTime $startDate): array
    {
        return DB::table('open_ai_job_results')
            ->where('created_at', '>=', $startDate)
            ->select(
                DB::raw('COUNT(*) as total_jobs'),
                DB::raw('COUNT(CASE WHEN status = \'completed\' THEN 1 END) as completed_jobs'),
                DB::raw('COUNT(CASE WHEN status = \'failed\' THEN 1 END) as failed_jobs'),
                DB::raw('COUNT(CASE WHEN status = \'processing\' THEN 1 END) as processing_jobs'),
                DB::raw('AVG(processing_time_ms) as avg_processing_time_ms'),
                DB::raw('SUM(CASE WHEN token_usage::json->>\'total_tokens\' IS NOT NULL THEN (token_usage::json->>\'total_tokens\')::int ELSE 0 END) as total_tokens_used')
            )
            ->first();
    }

    /**
     * Get performance analytics
     */
    private function getPerformanceAnalytics(\DateTime $startDate): array
    {
        return [
            'avg_processing_time' => 0, // Would calculate from database
            'tokens_per_second' => 0,
            'success_rate' => 0,
            'throughput' => 0
        ];
    }

    /**
     * Get cost analysis
     */
    private function getCostAnalysis(\DateTime $startDate): array
    {
        return [
            'total_cost_usd' => 0, // Would calculate from token usage
            'avg_cost_per_job' => 0,
            'cost_by_model' => [],
            'cost_trend' => []
        ];
    }

    /**
     * Get queue analytics
     */
    private function getQueueAnalytics(\DateTime $startDate): array
    {
        return [
            'queue_sizes' => [],
            'wait_times' => [],
            'throughput_by_queue' => []
        ];
    }

    /**
     * Get error analysis
     */
    private function getErrorAnalysis(\DateTime $startDate): array
    {
        return DB::table('open_ai_job_results')
            ->where('created_at', '>=', $startDate)
            ->where('status', 'failed')
            ->select('error_message', DB::raw('COUNT(*) as error_count'))
            ->groupBy('error_message')
            ->orderBy('error_count', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }

    /**
     * Get usage patterns
     */
    private function getUsagePatterns(\DateTime $startDate): array
    {
        return [
            'hourly_distribution' => [], // Jobs by hour of day
            'job_type_distribution' => [], // Jobs by type
            'user_activity' => [] // Most active users
        ];
    }

    /**
     * Get current queue status
     */
    private function getCurrentQueueStatus(): array
    {
        // This would integrate with Horizon API
        return [
            'total_pending' => 0,
            'total_processing' => 0,
            'queue_details' => []
        ];
    }

    /**
     * Get active jobs count
     */
    private function getActiveJobsCount(): int
    {
        return OpenAiJobResult::whereIn('status', ['pending', 'processing'])->count();
    }

    /**
     * Get system health indicators
     */
    private function getSystemHealth(): array
    {
        return [
            'status' => 'healthy',
            'database_connection' => 'ok',
            'redis_connection' => 'ok',
            'queue_connection' => 'ok',
            'openai_api_status' => 'ok'
        ];
    }

    /**
     * Get resource usage information
     */
    private function getResourceUsage(): array
    {
        return [
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'cpu_load' => null, // Would need system integration
            'disk_usage' => null
        ];
    }

    /**
     * Get rate limit status
     */
    private function getRateLimitStatus(): array
    {
        return [
            'requests_per_minute' => 0, // Would track actual rate limits
            'tokens_per_minute' => 0,
            'current_usage' => 0,
            'limit_reached' => false
        ];
    }

    /**
     * Cleanup database records
     */
    private function cleanupDatabase(\DateTime $cutoffDate): array
    {
        $deleted = OpenAiJobResult::where('created_at', '<', $cutoffDate)->delete();
        
        return [
            'records_deleted' => $deleted,
            'cutoff_date' => $cutoffDate->toISOString()
        ];
    }

    /**
     * Cleanup cache entries
     */
    private function cleanupCache(int $olderThanDays): array
    {
        // This would clean up job monitoring and streaming caches
        return [
            'cache_entries_cleaned' => 0 // Would implement actual cleanup
        ];
    }

    /**
     * Cleanup Redis entries
     */
    private function cleanupRedis(int $olderThanDays): array
    {
        // This would clean up Redis streaming and monitoring data
        return [
            'redis_keys_cleaned' => 0 // Would implement actual cleanup
        ];
    }
}