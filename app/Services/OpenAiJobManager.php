<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\OpenAiStreamingJob;
use App\Models\OpenAiJobResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Carbon\Carbon;

final class OpenAiJobManager
{
    /**
     * Create and dispatch an OpenAI streaming job
     */
    public function createJob(
        string $prompt,
        string $jobType = 'analysis',
        array $config = [],
        array $metadata = [],
        ?int $userId = null
    ): string {
        $jobId = $this->generateJobId($jobType);
        
        $defaultConfig = [
            'model' => 'gpt-4',
            'max_tokens' => 2000,
            'temperature' => 0.7,
            'priority' => 'normal',
        ];
        
        $mergedConfig = array_merge($defaultConfig, $config);
        
        $job = new OpenAiStreamingJob(
            prompt: $prompt,
            jobId: $jobId,
            config: $mergedConfig,
            metadata: array_merge($metadata, [
                'created_via' => 'job_manager',
                'created_at' => now()->toISOString(),
            ]),
            jobType: $jobType,
            userId: $userId
        );

        dispatch($job);
        
        return $jobId;
    }

    /**
     * Create multiple jobs in batch
     */
    public function createBatch(
        array $prompts,
        string $jobType = 'analysis',
        array $config = [],
        array $metadata = [],
        ?int $userId = null
    ): array {
        $batchId = 'batch_' . Str::random(8);
        $jobIds = [];
        
        foreach ($prompts as $index => $prompt) {
            $jobId = $this->createJob(
                prompt: $prompt,
                jobType: $jobType,
                config: $config,
                metadata: array_merge($metadata, [
                    'batch_id' => $batchId,
                    'batch_index' => $index + 1,
                ]),
                userId: $userId
            );
            
            $jobIds[] = $jobId;
        }
        
        // Store batch metadata
        Cache::put("batch_metadata_{$batchId}", [
            'batch_id' => $batchId,
            'total_jobs' => count($jobIds),
            'job_ids' => $jobIds,
            'created_at' => now()->toISOString(),
            'configuration' => $config,
        ], 86400); // 24 hours
        
        return [
            'batch_id' => $batchId,
            'job_ids' => $jobIds,
        ];
    }

    /**
     * Get job status and progress
     */
    public function getJobStatus(string $jobId): ?array
    {
        $jobResult = OpenAiJobResult::where('job_id', $jobId)->first();
        $streamStatus = Cache::get("openai_stream_{$jobId}");
        
        if (!$jobResult && !$streamStatus) {
            return null;
        }
        
        return [
            'job_id' => $jobId,
            'database_status' => $jobResult ? [
                'status' => $jobResult->status,
                'created_at' => $jobResult->created_at?->toISOString(),
                'started_at' => $jobResult->started_at?->toISOString(),
                'completed_at' => $jobResult->completed_at?->toISOString(),
                'failed_at' => $jobResult->failed_at?->toISOString(),
                'processing_time_ms' => $jobResult->processing_time_ms,
                'token_usage' => $jobResult->token_usage,
                'error_message' => $jobResult->error_message,
            ] : null,
            'streaming_status' => $streamStatus,
            'progress' => $this->calculateProgress($jobResult, $streamStatus),
        ];
    }

    /**
     * Cancel a running job
     */
    public function cancelJob(string $jobId): bool
    {
        try {
            // Update database record
            $jobResult = OpenAiJobResult::where('job_id', $jobId)->first();
            if ($jobResult && $jobResult->status === 'processing') {
                $jobResult->update([
                    'status' => 'failed',
                    'error_message' => 'Job cancelled by user',
                    'failed_at' => now(),
                ]);
            }
            
            // Clear streaming cache
            Cache::forget("openai_stream_{$jobId}");
            
            // Note: Actual job cancellation in queue is complex and depends on timing
            // The job might still complete if it's already running
            
            return true;
            
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get job results
     */
    public function getJobResult(string $jobId): ?array
    {
        $jobResult = OpenAiJobResult::where('job_id', $jobId)->first();
        
        if (!$jobResult) {
            return null;
        }
        
        return [
            'job_id' => $jobId,
            'status' => $jobResult->status,
            'job_type' => $jobResult->job_type,
            'prompt' => $jobResult->prompt,
            'response' => $jobResult->response,
            'parsed_response' => $jobResult->parsed_response,
            'config' => $jobResult->config,
            'metadata' => $jobResult->metadata,
            'token_usage' => $jobResult->token_usage,
            'processing_time_ms' => $jobResult->processing_time_ms,
            'streaming_stats' => $jobResult->streaming_stats,
            'error_message' => $jobResult->error_message,
            'timestamps' => [
                'created_at' => $jobResult->created_at?->toISOString(),
                'started_at' => $jobResult->started_at?->toISOString(),
                'completed_at' => $jobResult->completed_at?->toISOString(),
                'failed_at' => $jobResult->failed_at?->toISOString(),
            ],
            'performance_metrics' => $jobResult->getStreamingMetrics(),
        ];
    }

    /**
     * Get job statistics
     */
    public function getJobStatistics(
        ?Carbon $since = null,
        ?string $jobType = null,
        ?string $status = null
    ): array {
        $query = OpenAiJobResult::query();
        
        if ($since) {
            $query->where('created_at', '>=', $since);
        }
        
        if ($jobType) {
            $query->where('job_type', $jobType);
        }
        
        if ($status) {
            $query->where('status', $status);
        }
        
        $jobs = $query->get();
        
        return [
            'total_jobs' => $jobs->count(),
            'status_breakdown' => [
                'completed' => $jobs->where('status', 'completed')->count(),
                'failed' => $jobs->where('status', 'failed')->count(),
                'processing' => $jobs->where('status', 'processing')->count(),
                'pending' => $jobs->where('status', 'pending')->count(),
            ],
            'performance_metrics' => [
                'avg_processing_time_ms' => $jobs->where('status', 'completed')->avg('processing_time_ms'),
                'total_tokens' => $jobs->where('status', 'completed')->sum(function ($job) {
                    return $job->token_usage['total_tokens'] ?? 0;
                }),
                'total_cost' => $jobs->where('status', 'completed')->sum(function ($job) {
                    return $job->token_usage['estimated_cost_usd'] ?? 0;
                }),
                'success_rate' => $jobs->count() > 0 ? 
                    round(($jobs->where('status', 'completed')->count() / $jobs->count()) * 100, 2) : 0,
            ],
            'job_type_breakdown' => $jobs->groupBy('job_type')->map->count(),
            'model_usage' => $jobs->groupBy(function ($job) {
                return $job->config['model'] ?? 'unknown';
            })->map->count(),
        ];
    }

    /**
     * List jobs with filtering and pagination
     */
    public function listJobs(
        int $page = 1,
        int $perPage = 20,
        ?string $status = null,
        ?string $jobType = null,
        ?int $userId = null,
        ?Carbon $since = null
    ): array {
        $query = OpenAiJobResult::query();
        
        if ($status) {
            $query->where('status', $status);
        }
        
        if ($jobType) {
            $query->where('job_type', $jobType);
        }
        
        if ($userId) {
            $query->where('user_id', $userId);
        }
        
        if ($since) {
            $query->where('created_at', '>=', $since);
        }
        
        $total = $query->count();
        $jobs = $query->orderBy('created_at', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();
        
        return [
            'data' => $jobs->map(function ($job) {
                return [
                    'job_id' => $job->job_id,
                    'status' => $job->status,
                    'job_type' => $job->job_type,
                    'user_id' => $job->user_id,
                    'model' => $job->config['model'] ?? 'unknown',
                    'total_tokens' => $job->getTotalTokens(),
                    'estimated_cost' => $job->getEstimatedCost(),
                    'processing_time_seconds' => $job->getProcessingDurationSeconds(),
                    'created_at' => $job->created_at?->toISOString(),
                    'completed_at' => $job->completed_at?->toISOString(),
                ];
            }),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => ceil($total / $perPage),
                'has_more' => $page * $perPage < $total,
            ],
        ];
    }

    /**
     * Retry failed jobs
     */
    public function retryFailedJobs(array $jobIds = []): array
    {
        $query = OpenAiJobResult::where('status', 'failed');
        
        if (!empty($jobIds)) {
            $query->whereIn('job_id', $jobIds);
        }
        
        $failedJobs = $query->get();
        $retriedJobs = [];
        
        foreach ($failedJobs as $failedJob) {
            try {
                $newJobId = $this->createJob(
                    prompt: $failedJob->prompt,
                    jobType: $failedJob->job_type,
                    config: $failedJob->config ?? [],
                    metadata: array_merge($failedJob->metadata ?? [], [
                        'retry_of' => $failedJob->job_id,
                        'original_failure' => $failedJob->error_message,
                    ]),
                    userId: $failedJob->user_id
                );
                
                $retriedJobs[] = [
                    'original_job_id' => $failedJob->job_id,
                    'new_job_id' => $newJobId,
                ];
                
            } catch (\Exception $e) {
                $retriedJobs[] = [
                    'original_job_id' => $failedJob->job_id,
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return $retriedJobs;
    }

    /**
     * Cleanup old job records
     */
    public function cleanupOldJobs(
        int $completedRetentionDays = 30,
        int $failedRetentionDays = 7
    ): array {
        $completedCutoff = now()->subDays($completedRetentionDays);
        $failedCutoff = now()->subDays($failedRetentionDays);
        
        $deletedCompleted = OpenAiJobResult::where('status', 'completed')
            ->where('completed_at', '<', $completedCutoff)
            ->delete();
        
        $deletedFailed = OpenAiJobResult::where('status', 'failed')
            ->where('failed_at', '<', $failedCutoff)
            ->delete();
        
        return [
            'deleted_completed' => $deletedCompleted,
            'deleted_failed' => $deletedFailed,
            'total_deleted' => $deletedCompleted + $deletedFailed,
        ];
    }

    /**
     * Get queue status
     */
    public function getQueueStatus(): array
    {
        // This would need to be implemented based on your queue driver
        // For now, return basic information
        
        $recentJobs = OpenAiJobResult::where('created_at', '>=', now()->subHour())
            ->selectRaw('
                status,
                COUNT(*) as count,
                AVG(processing_time_ms) as avg_processing_time
            ')
            ->groupBy('status')
            ->get();
        
        return [
            'recent_jobs' => $recentJobs->mapWithKeys(function ($item) {
                return [$item->status => [
                    'count' => $item->count,
                    'avg_processing_time_ms' => $item->avg_processing_time,
                ]];
            })->toArray(),
            'queue_sizes' => [
                // These would need to be implemented based on your queue driver
                'openai-analysis' => 0,
                'openai-streaming' => 0,
            ],
        ];
    }

    // Private helper methods
    private function generateJobId(string $jobType): string
    {
        $prefix = match($jobType) {
            'security_analysis' => 'sec',
            'sentiment_analysis' => 'sent',
            'code_analysis' => 'code',
            default => 'job'
        };
        
        return "{$prefix}_" . Str::random(12);
    }

    private function calculateProgress(?OpenAiJobResult $jobResult, ?array $streamStatus): array
    {
        if (!$jobResult && !$streamStatus) {
            return ['percentage' => 0, 'status' => 'unknown'];
        }
        
        if ($jobResult && in_array($jobResult->status, ['completed', 'failed'])) {
            return [
                'percentage' => 100,
                'status' => $jobResult->status,
            ];
        }
        
        if ($streamStatus) {
            $tokensReceived = $streamStatus['tokens_received'] ?? 0;
            $estimatedTotal = $streamStatus['estimated_total_tokens'] ?? 2000;
            
            $percentage = min(($tokensReceived / $estimatedTotal) * 100, 95); // Cap at 95% until completion
            
            return [
                'percentage' => round($percentage, 1),
                'status' => $streamStatus['status'] ?? 'processing',
                'tokens_received' => $tokensReceived,
                'estimated_total' => $estimatedTotal,
            ];
        }
        
        return [
            'percentage' => 0,
            'status' => $jobResult ? $jobResult->status : 'unknown',
        ];
    }
}