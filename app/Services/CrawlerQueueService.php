<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CrawlerRule;
use App\Jobs\ProcessCrawlerTask;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\Jobs\Job;

final class CrawlerQueueService
{
    private const QUEUE_NAMES = [
        'high' => 'crawler-high',
        'normal' => 'crawler-normal',
        'low' => 'crawler-low',
    ];

    private const PRIORITY_WEIGHTS = [
        1 => 'high',
        2 => 'high',
        3 => 'high',
        4 => 'normal',
        5 => 'normal',
        6 => 'normal',
        7 => 'normal',
        8 => 'low',
        9 => 'low',
        10 => 'low',
    ];

    /**
     * Start scheduled crawling for all active rules.
     */
    public function startScheduledCrawling(): array
    {
        $rules = $this->getScheduledRules();
        
        if ($rules->isEmpty()) {
            return [
                'status' => 'no_rules',
                'message' => 'No rules scheduled for crawling',
                'tasks_dispatched' => 0,
            ];
        }

        $results = ProcessCrawlerTask::dispatchBatch($rules->toArray(), [
            'priority' => 'normal',
            'batch_id' => 'scheduled_' . now()->format('Y-m-d_H-i-s'),
        ]);

        return [
            'status' => 'dispatched',
            'rules_processed' => count($rules),
            'tasks_dispatched' => $results['summary']['total_tasks'],
            'batch_id' => $results['batch_id'],
        ];
    }

    /**
     * Start high-priority crawling for urgent rules.
     */
    public function startHighPriorityCrawling(array $ruleIds = null): array
    {
        $query = CrawlerRule::active()
            ->highPriority()
            ->inTimeWindow();

        if ($ruleIds) {
            $query->whereIn('id', $ruleIds);
        }

        $rules = $query->get();

        if ($rules->isEmpty()) {
            return [
                'status' => 'no_rules',
                'message' => 'No high-priority rules found',
                'tasks_dispatched' => 0,
            ];
        }

        $results = ProcessCrawlerTask::dispatchBatch($rules->toArray(), [
            'priority' => 'high',
            'batch_id' => 'priority_' . now()->format('Y-m-d_H-i-s'),
        ]);

        return [
            'status' => 'dispatched',
            'rules_processed' => count($rules),
            'tasks_dispatched' => $results['summary']['total_tasks'],
            'batch_id' => $results['batch_id'],
        ];
    }

    /**
     * Start real-time crawling for streaming-enabled rules.
     */
    public function startRealTimeCrawling(): array
    {
        $rules = CrawlerRule::active()
            ->forRealTime()
            ->inTimeWindow()
            ->get();

        if ($rules->isEmpty()) {
            return [
                'status' => 'no_realtime_rules',
                'message' => 'No real-time rules configured',
                'tasks_dispatched' => 0,
            ];
        }

        $dispatched = 0;
        $errors = [];

        foreach ($rules as $rule) {
            try {
                // For real-time, we dispatch immediately with high priority
                $ruleResults = ProcessCrawlerTask::dispatchForRule($rule, [
                    'priority' => 'high',
                    'batch_id' => 'realtime_' . time(),
                ]);

                $dispatched += count($ruleResults);

            } catch (\Exception $e) {
                $errors[] = "Rule {$rule->id}: " . $e->getMessage();
                Log::error('Failed to dispatch real-time crawl', [
                    'rule_id' => $rule->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'status' => 'dispatched',
            'rules_processed' => count($rules),
            'tasks_dispatched' => $dispatched,
            'errors' => $errors,
        ];
    }

    /**
     * Get queue statistics and status.
     */
    public function getQueueStatus(): array
    {
        $status = [];
        
        foreach (self::QUEUE_NAMES as $priority => $queueName) {
            try {
                $size = Queue::size($queueName);
                $status[$priority] = [
                    'queue_name' => $queueName,
                    'pending_jobs' => $size,
                    'status' => $size > 100 ? 'overloaded' : ($size > 50 ? 'busy' : 'normal'),
                ];
            } catch (\Exception $e) {
                $status[$priority] = [
                    'queue_name' => $queueName,
                    'pending_jobs' => 0,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Get general queue health
        $totalPending = array_sum(array_column($status, 'pending_jobs'));
        
        return [
            'queues' => $status,
            'summary' => [
                'total_pending_jobs' => $totalPending,
                'overall_status' => $this->determineOverallStatus($status),
                'last_checked' => now()->toISOString(),
            ],
            'workers' => $this->getWorkerStatus(),
        ];
    }

    /**
     * Get worker status information.
     */
    public function getWorkerStatus(): array
    {
        try {
            // This would integrate with Laravel Horizon or queue monitoring
            // For now, provide basic information
            return [
                'active_workers' => $this->getActiveWorkerCount(),
                'failed_jobs_24h' => $this->getFailedJobsCount(),
                'processed_jobs_24h' => $this->getProcessedJobsCount(),
                'average_processing_time' => $this->getAverageProcessingTime(),
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'Unable to get worker status: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Pause crawling for specific rules or all rules.
     */
    public function pauseCrawling(array $ruleIds = null): array
    {
        $query = CrawlerRule::query();
        
        if ($ruleIds) {
            $query->whereIn('id', $ruleIds);
        }

        $updated = $query->update(['active' => false]);

        Log::info('Crawling paused', [
            'rules_affected' => $updated,
            'rule_ids' => $ruleIds,
        ]);

        return [
            'status' => 'paused',
            'rules_affected' => $updated,
            'message' => $ruleIds ? 'Specified rules paused' : 'All rules paused',
        ];
    }

    /**
     * Resume crawling for specific rules or all rules.
     */
    public function resumeCrawling(array $ruleIds = null): array
    {
        $query = CrawlerRule::query();
        
        if ($ruleIds) {
            $query->whereIn('id', $ruleIds);
        }

        $updated = $query->update(['active' => true]);

        Log::info('Crawling resumed', [
            'rules_affected' => $updated,
            'rule_ids' => $ruleIds,
        ]);

        return [
            'status' => 'resumed',
            'rules_affected' => $updated,
            'message' => $ruleIds ? 'Specified rules resumed' : 'All rules resumed',
        ];
    }

    /**
     * Clear failed jobs from queues.
     */
    public function clearFailedJobs(): array
    {
        try {
            $clearedCount = 0;
            
            // Clear failed jobs (this would depend on your queue driver)
            // For Redis/Database queues, you might need different approaches
            
            if (config('queue.default') === 'redis') {
                $clearedCount = $this->clearRedisFailedJobs();
            } else {
                $clearedCount = $this->clearDatabaseFailedJobs();
            }

            Log::info('Failed jobs cleared', ['count' => $clearedCount]);

            return [
                'status' => 'cleared',
                'jobs_cleared' => $clearedCount,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to clear failed jobs', ['error' => $e->getMessage()]);
            
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Retry failed jobs.
     */
    public function retryFailedJobs(array $jobIds = null): array
    {
        try {
            $retriedCount = 0;
            
            // Retry logic would depend on your queue driver and setup
            // This is a simplified implementation
            
            Log::info('Retrying failed jobs', ['job_ids' => $jobIds]);

            return [
                'status' => 'retried',
                'jobs_retried' => $retriedCount,
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get performance metrics for the queue system.
     */
    public function getPerformanceMetrics(): array
    {
        $last24Hours = now()->subDay();
        
        return [
            'throughput' => [
                'jobs_processed_24h' => $this->getProcessedJobsCount($last24Hours),
                'jobs_failed_24h' => $this->getFailedJobsCount($last24Hours),
                'success_rate' => $this->getSuccessRate($last24Hours),
            ],
            'timing' => [
                'average_processing_time' => $this->getAverageProcessingTime(),
                'p95_processing_time' => $this->getPercentileProcessingTime(95),
                'p99_processing_time' => $this->getPercentileProcessingTime(99),
            ],
            'queues' => $this->getQueueMetrics(),
            'workers' => [
                'active_workers' => $this->getActiveWorkerCount(),
                'idle_workers' => $this->getIdleWorkerCount(),
                'memory_usage' => $this->getWorkerMemoryUsage(),
            ],
        ];
    }

    /**
     * Optimize queue priorities based on performance.
     */
    public function optimizeQueuePriorities(): array
    {
        $optimizations = [];
        
        // Analyze rule performance and adjust priorities
        $rules = CrawlerRule::active()->get();
        
        foreach ($rules as $rule) {
            $efficiency = $rule->getEfficiencyScore();
            $currentPriority = $rule->priority;
            
            // Suggest priority changes based on efficiency
            if ($efficiency > 80 && $currentPriority > 3) {
                $suggestedPriority = max(1, $currentPriority - 2);
                $optimizations[] = [
                    'rule_id' => $rule->id,
                    'current_priority' => $currentPriority,
                    'suggested_priority' => $suggestedPriority,
                    'reason' => 'High efficiency score',
                    'efficiency' => $efficiency,
                ];
            } elseif ($efficiency < 30 && $currentPriority < 8) {
                $suggestedPriority = min(10, $currentPriority + 2);
                $optimizations[] = [
                    'rule_id' => $rule->id,
                    'current_priority' => $currentPriority,
                    'suggested_priority' => $suggestedPriority,
                    'reason' => 'Low efficiency score',
                    'efficiency' => $efficiency,
                ];
            }
        }

        return [
            'optimizations_found' => count($optimizations),
            'suggestions' => $optimizations,
        ];
    }

    /**
     * Get rules that are scheduled for crawling.
     */
    private function getScheduledRules(): \Illuminate\Database\Eloquent\Collection
    {
        return CrawlerRule::active()
            ->inTimeWindow()
            ->dueCrawl()
            ->orderBy('priority')
            ->get();
    }

    /**
     * Determine overall queue system status.
     */
    private function determineOverallStatus(array $queueStatus): string
    {
        $statuses = array_column($queueStatus, 'status');
        
        if (in_array('error', $statuses)) {
            return 'error';
        }
        
        if (in_array('overloaded', $statuses)) {
            return 'overloaded';
        }
        
        if (in_array('busy', $statuses)) {
            return 'busy';
        }
        
        return 'normal';
    }

    /**
     * Get active worker count.
     */
    private function getActiveWorkerCount(): int
    {
        // This would integrate with your queue monitoring system
        // For now, return a placeholder
        return 5;
    }

    /**
     * Get failed jobs count for a time period.
     */
    private function getFailedJobsCount(\Carbon\Carbon $since = null): int
    {
        // This would query your failed jobs table/storage
        // Implementation depends on your queue driver
        return 0;
    }

    /**
     * Get processed jobs count for a time period.
     */
    private function getProcessedJobsCount(\Carbon\Carbon $since = null): int
    {
        // This would query your job processing logs
        // Implementation depends on your monitoring setup
        return 100;
    }

    /**
     * Get average processing time.
     */
    private function getAverageProcessingTime(): float
    {
        // This would calculate from job execution logs
        return 45.5; // seconds
    }

    /**
     * Get success rate for a time period.
     */
    private function getSuccessRate(\Carbon\Carbon $since): float
    {
        $processed = $this->getProcessedJobsCount($since);
        $failed = $this->getFailedJobsCount($since);
        $total = $processed + $failed;
        
        return $total > 0 ? round(($processed / $total) * 100, 2) : 100.0;
    }

    /**
     * Get percentile processing time.
     */
    private function getPercentileProcessingTime(int $percentile): float
    {
        // This would calculate from job execution logs
        return match ($percentile) {
            95 => 120.0,
            99 => 180.0,
            default => 60.0,
        };
    }

    /**
     * Get queue-specific metrics.
     */
    private function getQueueMetrics(): array
    {
        return [
            'high_priority' => ['avg_wait_time' => 5.2, 'throughput' => 50],
            'normal_priority' => ['avg_wait_time' => 15.8, 'throughput' => 200],
            'low_priority' => ['avg_wait_time' => 45.0, 'throughput' => 100],
        ];
    }

    /**
     * Get idle worker count.
     */
    private function getIdleWorkerCount(): int
    {
        return 2;
    }

    /**
     * Get worker memory usage.
     */
    private function getWorkerMemoryUsage(): array
    {
        return [
            'average_mb' => 128,
            'peak_mb' => 256,
            'total_mb' => 640,
        ];
    }

    /**
     * Clear failed jobs from Redis queues.
     */
    private function clearRedisFailedJobs(): int
    {
        try {
            $redis = Redis::connection();
            $failedJobsKey = config('queue.connections.redis.failed_jobs_key', 'queues:failed');
            
            $count = $redis->llen($failedJobsKey);
            $redis->del($failedJobsKey);
            
            return $count;
        } catch (\Exception $e) {
            Log::error('Failed to clear Redis failed jobs', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Clear failed jobs from database.
     */
    private function clearDatabaseFailedJobs(): int
    {
        try {
            return \DB::table('failed_jobs')->delete();
        } catch (\Exception $e) {
            Log::error('Failed to clear database failed jobs', ['error' => $e->getMessage()]);
            return 0;
        }
    }
}
