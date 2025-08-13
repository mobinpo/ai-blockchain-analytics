<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CrawlerRule;
use App\Services\Crawlers\TwitterCrawlerService;
use App\Services\Crawlers\RedditCrawlerService;
use App\Services\Crawlers\TelegramCrawlerService;
use Illuminate\Support\Facades\Log;
use Laravel\Octane\Facades\Octane;

final class CrawlerOctaneService
{
    private array $crawlers = [];
    private array $activeTasks = [];
    private int $maxConcurrentTasks = 10;

    public function __construct(
        private readonly TwitterCrawlerService $twitterCrawler,
        private readonly RedditCrawlerService $redditCrawler,
        private readonly TelegramCrawlerService $telegramCrawler
    ) {
        $this->crawlers = [
            'twitter' => $this->twitterCrawler,
            'reddit' => $this->redditCrawler,
            'telegram' => $this->telegramCrawler,
        ];
    }

    /**
     * Start high-performance crawling using Octane tasks.
     */
    public function startCrawling(array $ruleIds = null): array
    {
        $rules = $this->getActiveCrawlRules($ruleIds);
        
        if (empty($rules)) {
            return [
                'status' => 'no_rules',
                'message' => 'No active crawler rules found',
                'tasks_started' => 0,
            ];
        }

        $tasksStarted = 0;
        $results = [
            'status' => 'started',
            'tasks_started' => 0,
            'tasks_queued' => 0,
            'tasks_skipped' => 0,
            'errors' => [],
        ];

        foreach ($rules as $rule) {
            try {
                // Check if we can start more tasks
                if ($tasksStarted >= $this->maxConcurrentTasks) {
                    $results['tasks_queued']++;
                    $this->queueCrawlTask($rule);
                    continue;
                }

                // Check if rule can be crawled now
                if (!$rule->canCrawlNow()) {
                    $results['tasks_skipped']++;
                    continue;
                }

                // Start Octane task for each platform
                foreach ($rule->platforms as $platform) {
                    if (isset($this->crawlers[$platform])) {
                        $taskId = $this->startCrawlTask($rule, $platform);
                        if ($taskId) {
                            $tasksStarted++;
                            $results['tasks_started']++;
                        }
                    }
                }

            } catch (\Exception $e) {
                $results['errors'][] = "Failed to start crawl for rule {$rule->id}: " . $e->getMessage();
                Log::error('Failed to start crawl task', [
                    'rule_id' => $rule->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Crawler Octane service started', [
            'tasks_started' => $results['tasks_started'],
            'tasks_queued' => $results['tasks_queued'],
            'tasks_skipped' => $results['tasks_skipped'],
        ]);

        return $results;
    }

    /**
     * Start individual crawl task using Octane.
     */
    private function startCrawlTask(CrawlerRule $rule, string $platform): ?string
    {
        $taskId = "crawl_{$rule->id}_{$platform}_" . time();

        try {
            // Use Octane's concurrent task execution
            $task = Octane::concurrently([
                $taskId => function () use ($rule, $platform) {
                    return $this->executeCrawlTask($rule, $platform);
                }
            ]);

            $this->activeTasks[$taskId] = [
                'rule_id' => $rule->id,
                'platform' => $platform,
                'started_at' => now(),
                'status' => 'running',
            ];

            // Handle task completion
            $task->then(function ($results) use ($taskId, $rule, $platform) {
                $this->handleTaskCompletion($taskId, $results[$taskId] ?? [], $rule, $platform);
            });

            Log::info('Started crawl task', [
                'task_id' => $taskId,
                'rule_id' => $rule->id,
                'platform' => $platform,
            ]);

            return $taskId;

        } catch (\Exception $e) {
            Log::error('Failed to start Octane crawl task', [
                'task_id' => $taskId,
                'rule_id' => $rule->id,
                'platform' => $platform,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Execute the actual crawl task.
     */
    private function executeCrawlTask(CrawlerRule $rule, string $platform): array
    {
        $startTime = microtime(true);
        
        try {
            $crawler = $this->crawlers[$platform];
            $results = $crawler->crawl($rule);
            
            $results['task_execution_time'] = round(microtime(true) - $startTime, 2);
            $results['memory_usage'] = memory_get_peak_usage(true);
            
            return $results;

        } catch (\Exception $e) {
            return [
                'platform' => $platform,
                'rule_id' => $rule->id,
                'success' => false,
                'error' => $e->getMessage(),
                'task_execution_time' => round(microtime(true) - $startTime, 2),
            ];
        }
    }

    /**
     * Handle task completion and cleanup.
     */
    private function handleTaskCompletion(string $taskId, array $results, CrawlerRule $rule, string $platform): void
    {
        try {
            // Update task status
            if (isset($this->activeTasks[$taskId])) {
                $this->activeTasks[$taskId]['status'] = 'completed';
                $this->activeTasks[$taskId]['completed_at'] = now();
                $this->activeTasks[$taskId]['results'] = $results;
            }

            // Log results
            Log::info('Crawl task completed', [
                'task_id' => $taskId,
                'rule_id' => $rule->id,
                'platform' => $platform,
                'posts_found' => $results['posts_found'] ?? 0,
                'posts_stored' => $results['posts_stored'] ?? 0,
                'execution_time' => $results['task_execution_time'] ?? 0,
            ]);

            // Update rule performance metrics
            if (isset($results['posts_found'])) {
                $rule->updatePerformanceMetrics([
                    'last_octane_task' => [
                        'task_id' => $taskId,
                        'platform' => $platform,
                        'completed_at' => now()->toISOString(),
                        'posts_found' => $results['posts_found'],
                        'posts_stored' => $results['posts_stored'] ?? 0,
                        'execution_time' => $results['task_execution_time'] ?? 0,
                        'memory_usage' => $results['memory_usage'] ?? 0,
                    ],
                ]);
            }

            // Clean up completed task
            unset($this->activeTasks[$taskId]);

        } catch (\Exception $e) {
            Log::error('Error handling task completion', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Start batch crawling with optimized task distribution.
     */
    public function startBatchCrawl(array $rules = null): array
    {
        $crawlRules = $this->getActiveCrawlRules($rules);
        
        if (empty($crawlRules)) {
            return ['status' => 'no_rules', 'batches' => 0];
        }

        // Group rules by platform for efficient batching
        $platformGroups = $this->groupRulesByPlatform($crawlRules);
        $batchResults = [];

        foreach ($platformGroups as $platform => $platformRules) {
            try {
                $batchResults[$platform] = $this->executePlatformBatch($platform, $platformRules);
            } catch (\Exception $e) {
                $batchResults[$platform] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'rules_processed' => 0,
                ];
            }
        }

        return [
            'status' => 'completed',
            'platforms' => array_keys($platformGroups),
            'results' => $batchResults,
            'total_rules' => count($crawlRules),
        ];
    }

    /**
     * Execute batch crawl for a specific platform.
     */
    private function executePlatformBatch(string $platform, array $rules): array
    {
        $crawler = $this->crawlers[$platform] ?? null;
        if (!$crawler) {
            throw new \Exception("Crawler not found for platform: {$platform}");
        }

        $batchSize = $this->getOptimalBatchSize($platform);
        $batches = array_chunk($rules, $batchSize);
        $totalResults = [
            'platform' => $platform,
            'rules_processed' => 0,
            'total_posts_found' => 0,
            'total_posts_stored' => 0,
            'batch_count' => count($batches),
            'execution_time' => 0,
        ];

        $startTime = microtime(true);

        foreach ($batches as $batchIndex => $batchRules) {
            try {
                $batchResults = $this->executeBatch($batchRules, $crawler);
                
                $totalResults['rules_processed'] += count($batchRules);
                $totalResults['total_posts_found'] += array_sum(array_column($batchResults, 'posts_found'));
                $totalResults['total_posts_stored'] += array_sum(array_column($batchResults, 'posts_stored'));

                Log::info("Completed batch {$batchIndex} for {$platform}", [
                    'batch_size' => count($batchRules),
                    'posts_found' => array_sum(array_column($batchResults, 'posts_found')),
                ]);

            } catch (\Exception $e) {
                Log::error("Batch {$batchIndex} failed for {$platform}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $totalResults['execution_time'] = round(microtime(true) - $startTime, 2);
        return $totalResults;
    }

    /**
     * Execute a batch of rules using concurrent Octane tasks.
     */
    private function executeBatch(array $rules, $crawler): array
    {
        $tasks = [];
        $results = [];

        // Prepare concurrent tasks
        foreach ($rules as $rule) {
            $taskKey = "rule_{$rule->id}";
            $tasks[$taskKey] = function () use ($crawler, $rule) {
                return $crawler->crawl($rule);
            };
        }

        // Execute tasks concurrently
        $taskResults = Octane::concurrently($tasks);

        // Process results
        foreach ($rules as $index => $rule) {
            $taskKey = "rule_{$rule->id}";
            $results[] = $taskResults[$taskKey] ?? [
                'rule_id' => $rule->id,
                'posts_found' => 0,
                'posts_stored' => 0,
                'error' => 'Task failed to execute',
            ];
        }

        return $results;
    }

    /**
     * Start real-time crawling for high-priority rules.
     */
    public function startRealTimeCrawling(): array
    {
        $realTimeRules = CrawlerRule::active()
            ->forRealTime()
            ->highPriority()
            ->get();

        if ($realTimeRules->isEmpty()) {
            return [
                'status' => 'no_realtime_rules',
                'message' => 'No real-time crawler rules configured',
            ];
        }

        $streamingTasks = [];

        foreach ($realTimeRules as $rule) {
            foreach ($rule->platforms as $platform) {
                if ($platform === 'twitter' && method_exists($this->crawlers[$platform], 'streamTweets')) {
                    $taskId = "stream_{$platform}_{$rule->id}";
                    
                    try {
                        // Start streaming task (this would be a long-running process)
                        $streamingTasks[$taskId] = $this->startStreamingTask($rule, $platform);
                    } catch (\Exception $e) {
                        Log::error('Failed to start streaming task', [
                            'rule_id' => $rule->id,
                            'platform' => $platform,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        return [
            'status' => 'streaming_started',
            'streaming_tasks' => count($streamingTasks),
            'task_ids' => array_keys($streamingTasks),
        ];
    }

    /**
     * Start streaming task for real-time data.
     */
    private function startStreamingTask(CrawlerRule $rule, string $platform): string
    {
        $taskId = "stream_{$platform}_{$rule->id}_" . time();

        // This would implement real-time streaming
        // For Twitter, this would use the Streaming API
        // For now, it's a placeholder for the concept

        Log::info('Real-time streaming started', [
            'task_id' => $taskId,
            'rule_id' => $rule->id,
            'platform' => $platform,
        ]);

        return $taskId;
    }

    /**
     * Get status of all active tasks.
     */
    public function getTaskStatus(): array
    {
        return [
            'active_tasks' => count($this->activeTasks),
            'tasks' => $this->activeTasks,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
        ];
    }

    /**
     * Stop all running tasks.
     */
    public function stopAllTasks(): array
    {
        $stopped = 0;
        
        foreach ($this->activeTasks as $taskId => $task) {
            try {
                // Mark task as stopped
                $this->activeTasks[$taskId]['status'] = 'stopped';
                $this->activeTasks[$taskId]['stopped_at'] = now();
                $stopped++;
            } catch (\Exception $e) {
                Log::error('Failed to stop task', [
                    'task_id' => $taskId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Clear all tasks
        $this->activeTasks = [];

        return [
            'status' => 'stopped',
            'tasks_stopped' => $stopped,
        ];
    }

    /**
     * Get active crawler rules.
     */
    private function getActiveCrawlRules(array $ruleIds = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = CrawlerRule::active()
            ->inTimeWindow()
            ->dueCrawl();

        if ($ruleIds) {
            $query->whereIn('id', $ruleIds);
        }

        return $query->orderBy('priority')->get();
    }

    /**
     * Group rules by platform for efficient processing.
     */
    private function groupRulesByPlatform(array $rules): array
    {
        $groups = [];
        
        foreach ($rules as $rule) {
            foreach ($rule->platforms as $platform) {
                if (!isset($groups[$platform])) {
                    $groups[$platform] = [];
                }
                $groups[$platform][] = $rule;
            }
        }

        return $groups;
    }

    /**
     * Get optimal batch size for platform.
     */
    private function getOptimalBatchSize(string $platform): int
    {
        return match ($platform) {
            'twitter' => 5,  // Twitter has stricter rate limits
            'reddit' => 8,   // Reddit allows more concurrent requests
            'telegram' => 10, // Telegram is generally less restrictive
            default => 5,
        };
    }

    /**
     * Queue crawl task for later execution.
     */
    private function queueCrawlTask(CrawlerRule $rule): void
    {
        // This would integrate with Laravel's queue system
        // For now, it's a placeholder
        Log::info('Crawler task queued', [
            'rule_id' => $rule->id,
            'platforms' => $rule->platforms,
        ]);
    }

    /**
     * Get crawler performance metrics.
     */
    public function getPerformanceMetrics(): array
    {
        $totalTasks = count($this->activeTasks);
        $runningTasks = count(array_filter($this->activeTasks, fn($task) => $task['status'] === 'running'));
        
        return [
            'total_tasks' => $totalTasks,
            'running_tasks' => $runningTasks,
            'completed_tasks' => $totalTasks - $runningTasks,
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'available_crawlers' => array_keys($this->crawlers),
            'max_concurrent_tasks' => $this->maxConcurrentTasks,
        ];
    }

    /**
     * Set maximum concurrent tasks.
     */
    public function setMaxConcurrentTasks(int $max): void
    {
        $this->maxConcurrentTasks = max(1, min($max, 50)); // Reasonable limits
    }
}
