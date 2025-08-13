<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OpenAiJobResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class OpenAiJobProgressTracker
{
    private const CACHE_TTL = 7200; // 2 hours
    private const PROGRESS_EVENTS_KEY = 'openai_progress_events:';
    private const BATCH_PROGRESS_KEY = 'openai_batch_progress:';

    /**
     * Initialize progress tracking for a job
     */
    public function initializeJobProgress(string $jobId, array $config = []): void
    {
        $progressData = [
            'job_id' => $jobId,
            'status' => 'initializing',
            'progress_percentage' => 0.0,
            'current_stage' => 'setup',
            'stages' => $this->getJobStages($config['job_type'] ?? 'general'),
            'started_at' => now()->toISOString(),
            'estimated_duration_seconds' => $this->estimateJobDuration($config),
            'estimated_completion_at' => now()->addSeconds($this->estimateJobDuration($config))->toISOString(),
            'tokens_processed' => 0,
            'total_estimated_tokens' => $config['max_tokens'] ?? 4000,
            'processing_rate_tokens_per_second' => 0.0,
            'last_activity_at' => now()->toISOString(),
            'milestones' => [],
            'performance_metrics' => [
                'memory_usage_mb' => 0,
                'cpu_usage_percent' => 0,
                'network_latency_ms' => 0,
                'api_call_count' => 0,
                'cache_hit_ratio' => 0.0
            ],
            'error_recovery' => [
                'retry_attempts' => 0,
                'last_error' => null,
                'recovery_strategies_applied' => []
            ],
            'metadata' => $config['metadata'] ?? []
        ];

        Cache::put($this->getProgressKey($jobId), $progressData, self::CACHE_TTL);
        $this->publishProgressUpdate($jobId, $progressData, 'initialized');
    }

    /**
     * Update job progress with detailed tracking
     */
    public function updateJobProgress(
        string $jobId, 
        array $updates,
        ?string $eventType = 'progress_update'
    ): void {
        $currentProgress = $this->getJobProgress($jobId);
        
        if (!$currentProgress) {
            Log::warning("Attempting to update progress for non-existent job: {$jobId}");
            return;
        }

        // Calculate new progress percentage if tokens are provided
        if (isset($updates['tokens_processed'])) {
            $totalTokens = $currentProgress['total_estimated_tokens'] ?? 4000;
            $updates['progress_percentage'] = min(100.0, ($updates['tokens_processed'] / $totalTokens) * 100);
            
            // Calculate processing rate
            $timeElapsed = now()->diffInSeconds($currentProgress['started_at']);
            if ($timeElapsed > 0) {
                $updates['processing_rate_tokens_per_second'] = $updates['tokens_processed'] / $timeElapsed;
                
                // Update estimated completion
                $remainingTokens = $totalTokens - $updates['tokens_processed'];
                if ($updates['processing_rate_tokens_per_second'] > 0) {
                    $remainingSeconds = $remainingTokens / $updates['processing_rate_tokens_per_second'];
                    $updates['estimated_completion_at'] = now()->addSeconds($remainingSeconds)->toISOString();
                }
            }
        }

        // Update stage progression
        if (isset($updates['current_stage'])) {
            $stages = $currentProgress['stages'] ?? [];
            $stageIndex = array_search($updates['current_stage'], array_column($stages, 'name'));
            
            if ($stageIndex !== false) {
                $stages[$stageIndex]['status'] = 'active';
                $stages[$stageIndex]['started_at'] = now()->toISOString();
                
                // Mark previous stages as completed
                for ($i = 0; $i < $stageIndex; $i++) {
                    if ($stages[$i]['status'] !== 'completed') {
                        $stages[$i]['status'] = 'completed';
                        $stages[$i]['completed_at'] = now()->toISOString();
                    }
                }
                
                $updates['stages'] = $stages;
            }
        }

        // Add milestone if specified
        if (isset($updates['milestone'])) {
            $currentProgress['milestones'][] = [
                'name' => $updates['milestone']['name'],
                'description' => $updates['milestone']['description'] ?? '',
                'achieved_at' => now()->toISOString(),
                'progress_at_milestone' => $updates['progress_percentage'] ?? $currentProgress['progress_percentage'],
                'tokens_at_milestone' => $updates['tokens_processed'] ?? $currentProgress['tokens_processed'],
                'metadata' => $updates['milestone']['metadata'] ?? []
            ];
            unset($updates['milestone']); // Remove from updates as it's handled separately
        }

        // Update performance metrics if provided
        if (isset($updates['performance_metrics'])) {
            $updates['performance_metrics'] = array_merge(
                $currentProgress['performance_metrics'] ?? [],
                $updates['performance_metrics']
            );
        }

        // Update error recovery info if provided
        if (isset($updates['error_info'])) {
            $updates['error_recovery'] = array_merge(
                $currentProgress['error_recovery'] ?? [],
                [
                    'last_error' => $updates['error_info'],
                    'error_occurred_at' => now()->toISOString()
                ]
            );
            unset($updates['error_info']);
        }

        $updates['last_activity_at'] = now()->toISOString();
        
        // Merge updates with current progress
        $updatedProgress = array_merge($currentProgress, $updates);
        
        // Store updated progress
        Cache::put($this->getProgressKey($jobId), $updatedProgress, self::CACHE_TTL);
        
        // Publish real-time update
        $this->publishProgressUpdate($jobId, $updatedProgress, $eventType);
        
        // Store progress event for historical tracking
        $this->storeProgressEvent($jobId, $updates, $eventType);
    }

    /**
     * Mark job stage as completed
     */
    public function completeJobStage(string $jobId, string $stageName, array $stageResults = []): void
    {
        $progress = $this->getJobProgress($jobId);
        
        if (!$progress) {
            return;
        }

        $stages = $progress['stages'] ?? [];
        $stageIndex = array_search($stageName, array_column($stages, 'name'));
        
        if ($stageIndex !== false) {
            $stages[$stageIndex]['status'] = 'completed';
            $stages[$stageIndex]['completed_at'] = now()->toISOString();
            $stages[$stageIndex]['results'] = $stageResults;
            
            // Calculate stage duration
            if (isset($stages[$stageIndex]['started_at'])) {
                $stages[$stageIndex]['duration_seconds'] = now()->diffInSeconds($stages[$stageIndex]['started_at']);
            }
            
            $this->updateJobProgress($jobId, [
                'stages' => $stages,
                'current_stage' => $this->getNextActiveStage($stages)
            ], 'stage_completed');
        }
    }

    /**
     * Add performance metrics snapshot
     */
    public function addPerformanceSnapshot(string $jobId, array $metrics): void
    {
        $this->updateJobProgress($jobId, [
            'performance_metrics' => $metrics
        ], 'performance_snapshot');
    }

    /**
     * Record error and recovery attempt
     */
    public function recordErrorAndRecovery(string $jobId, string $error, string $recoveryStrategy = null): void
    {
        $progress = $this->getJobProgress($jobId);
        
        if (!$progress) {
            return;
        }

        $errorRecovery = $progress['error_recovery'] ?? [];
        $errorRecovery['retry_attempts'] = ($errorRecovery['retry_attempts'] ?? 0) + 1;
        $errorRecovery['last_error'] = $error;
        $errorRecovery['last_error_at'] = now()->toISOString();
        
        if ($recoveryStrategy) {
            $errorRecovery['recovery_strategies_applied'][] = [
                'strategy' => $recoveryStrategy,
                'applied_at' => now()->toISOString()
            ];
        }

        $this->updateJobProgress($jobId, [
            'error_recovery' => $errorRecovery,
            'status' => 'recovering'
        ], 'error_recovery');
    }

    /**
     * Mark job as completed with final metrics
     */
    public function completeJob(string $jobId, array $finalResults = []): void
    {
        $progress = $this->getJobProgress($jobId);
        
        if (!$progress) {
            return;
        }

        $duration = now()->diffInSeconds($progress['started_at']);
        
        $completionData = [
            'status' => 'completed',
            'progress_percentage' => 100.0,
            'completed_at' => now()->toISOString(),
            'total_duration_seconds' => $duration,
            'final_results' => $finalResults
        ];

        // Mark all stages as completed
        $stages = $progress['stages'] ?? [];
        foreach ($stages as &$stage) {
            if ($stage['status'] !== 'completed') {
                $stage['status'] = 'completed';
                $stage['completed_at'] = now()->toISOString();
            }
        }
        $completionData['stages'] = $stages;

        $this->updateJobProgress($jobId, $completionData, 'job_completed');
    }

    /**
     * Mark job as failed with error details
     */
    public function failJob(string $jobId, string $error, array $errorContext = []): void
    {
        $completionData = [
            'status' => 'failed',
            'failed_at' => now()->toISOString(),
            'final_error' => $error,
            'error_context' => $errorContext
        ];

        $this->updateJobProgress($jobId, $completionData, 'job_failed');
    }

    /**
     * Get current job progress
     */
    public function getJobProgress(string $jobId): ?array
    {
        return Cache::get($this->getProgressKey($jobId));
    }

    /**
     * Get job progress history
     */
    public function getJobProgressHistory(string $jobId, int $limit = 100): Collection
    {
        $eventsKey = self::PROGRESS_EVENTS_KEY . $jobId;
        $events = Redis::lrange($eventsKey, 0, $limit - 1);
        
        return collect($events)->map(function ($event) {
            return json_decode($event, true);
        })->filter();
    }

    /**
     * Get batch progress for multiple jobs
     */
    public function getBatchProgress(array $jobIds): array
    {
        $batchProgress = [];
        
        foreach ($jobIds as $jobId) {
            $progress = $this->getJobProgress($jobId);
            if ($progress) {
                $batchProgress[$jobId] = $this->summarizeProgress($progress);
            }
        }

        return $batchProgress;
    }

    /**
     * Clean up completed job progress data
     */
    public function cleanupCompletedJobs(int $olderThanHours = 24): int
    {
        $cleanedCount = 0;
        $cutoffTime = now()->subHours($olderThanHours);
        
        // Get all jobs that completed before the cutoff
        $completedJobs = OpenAiJobResult::where('status', 'completed')
            ->where('completed_at', '<', $cutoffTime)
            ->pluck('job_id');

        foreach ($completedJobs as $jobId) {
            $progressKey = $this->getProgressKey($jobId);
            $eventsKey = self::PROGRESS_EVENTS_KEY . $jobId;
            
            if (Cache::forget($progressKey)) {
                $cleanedCount++;
            }
            
            Redis::del($eventsKey);
        }

        Log::info("Cleaned up progress data for {$cleanedCount} completed jobs");
        return $cleanedCount;
    }

    /**
     * Get job stages based on job type
     */
    private function getJobStages(string $jobType): array
    {
        return match($jobType) {
            'security_analysis' => [
                ['name' => 'setup', 'description' => 'Initialize analysis environment', 'status' => 'pending'],
                ['name' => 'code_parsing', 'description' => 'Parse and validate contract code', 'status' => 'pending'],
                ['name' => 'vulnerability_scan', 'description' => 'Scan for security vulnerabilities', 'status' => 'pending'],
                ['name' => 'analysis_generation', 'description' => 'Generate detailed security report', 'status' => 'pending'],
                ['name' => 'validation', 'description' => 'Validate and format results', 'status' => 'pending'],
                ['name' => 'finalization', 'description' => 'Finalize and store results', 'status' => 'pending']
            ],
            'code_review' => [
                ['name' => 'setup', 'description' => 'Initialize code review', 'status' => 'pending'],
                ['name' => 'syntax_analysis', 'description' => 'Analyze code syntax and structure', 'status' => 'pending'],
                ['name' => 'quality_assessment', 'description' => 'Assess code quality and patterns', 'status' => 'pending'],
                ['name' => 'recommendation_generation', 'description' => 'Generate improvement recommendations', 'status' => 'pending'],
                ['name' => 'finalization', 'description' => 'Finalize review report', 'status' => 'pending']
            ],
            default => [
                ['name' => 'setup', 'description' => 'Initialize processing', 'status' => 'pending'],
                ['name' => 'processing', 'description' => 'Main processing phase', 'status' => 'pending'],
                ['name' => 'finalization', 'description' => 'Finalize results', 'status' => 'pending']
            ]
        };
    }

    /**
     * Estimate job duration based on configuration
     */
    private function estimateJobDuration(array $config): int
    {
        $baseTime = 30; // 30 seconds base
        $maxTokens = $config['max_tokens'] ?? 4000;
        $jobType = $config['job_type'] ?? 'general';
        
        $tokenMultiplier = match($jobType) {
            'security_analysis' => 0.02, // 20ms per token
            'code_review' => 0.015, // 15ms per token
            default => 0.01 // 10ms per token
        };
        
        return $baseTime + (int)($maxTokens * $tokenMultiplier);
    }

    /**
     * Get next active stage
     */
    private function getNextActiveStage(array $stages): ?string
    {
        foreach ($stages as $stage) {
            if ($stage['status'] === 'pending') {
                return $stage['name'];
            }
        }
        
        return null; // All stages completed
    }

    /**
     * Publish progress update via Redis
     */
    private function publishProgressUpdate(string $jobId, array $progressData, string $eventType): void
    {
        $message = [
            'job_id' => $jobId,
            'event_type' => $eventType,
            'progress_data' => $this->summarizeProgress($progressData),
            'timestamp' => now()->toISOString()
        ];

        Redis::publish('openai-job-progress', json_encode($message));
    }

    /**
     * Store progress event for history
     */
    private function storeProgressEvent(string $jobId, array $updates, string $eventType): void
    {
        $event = [
            'event_type' => $eventType,
            'updates' => $updates,
            'timestamp' => now()->toISOString()
        ];

        $eventsKey = self::PROGRESS_EVENTS_KEY . $jobId;
        Redis::lpush($eventsKey, json_encode($event));
        Redis::expire($eventsKey, self::CACHE_TTL);
    }

    /**
     * Summarize progress for API responses
     */
    private function summarizeProgress(array $progress): array
    {
        return [
            'job_id' => $progress['job_id'],
            'status' => $progress['status'],
            'progress_percentage' => $progress['progress_percentage'],
            'current_stage' => $progress['current_stage'],
            'tokens_processed' => $progress['tokens_processed'],
            'processing_rate_tokens_per_second' => $progress['processing_rate_tokens_per_second'],
            'estimated_completion_at' => $progress['estimated_completion_at'],
            'last_activity_at' => $progress['last_activity_at'],
            'milestones_count' => count($progress['milestones'] ?? []),
            'error_recovery_attempts' => $progress['error_recovery']['retry_attempts'] ?? 0
        ];
    }

    /**
     * Get progress cache key
     */
    private function getProgressKey(string $jobId): string
    {
        return "openai_job_progress:{$jobId}";
    }
}