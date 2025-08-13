<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CrawlerRule;
use App\Services\Crawlers\TwitterCrawlerService;
use App\Services\Crawlers\RedditCrawlerService;
use App\Services\Crawlers\TelegramCrawlerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

final class ProcessCrawlerTask implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes
    public int $tries = 3;
    public int $backoff = 60; // 1 minute backoff between retries

    private array $crawlers = [];

    public function __construct(
        private readonly int $ruleId,
        private readonly string $platform,
        private readonly array $taskConfig = []
    ) {
        // Set queue based on priority
        $this->onQueue($this->getQueueName());
    }

    /**
     * Execute the crawler task.
     */
    public function handle(
        TwitterCrawlerService $twitterCrawler,
        RedditCrawlerService $redditCrawler,
        TelegramCrawlerService $telegramCrawler
    ): void {
        $this->initializeCrawlers($twitterCrawler, $redditCrawler, $telegramCrawler);

        $startTime = microtime(true);
        
        try {
            Log::info('Starting crawler task', [
                'rule_id' => $this->ruleId,
                'platform' => $this->platform,
                'job_id' => $this->job->getJobId(),
                'attempt' => $this->attempts(),
            ]);

            // Get crawler rule
            $rule = CrawlerRule::find($this->ruleId);
            
            if (!$rule) {
                throw new Exception("Crawler rule not found: {$this->ruleId}");
            }

            if (!$rule->active) {
                Log::info('Skipping inactive rule', ['rule_id' => $this->ruleId]);
                return;
            }

            // Check if rule can be crawled now
            if (!$rule->canCrawlNow()) {
                Log::info('Rule cannot be crawled now (rate limit or time window)', [
                    'rule_id' => $this->ruleId,
                    'last_crawl' => $rule->last_crawl_at,
                    'interval' => $rule->crawl_interval_minutes,
                ]);
                return;
            }

            // Get appropriate crawler
            $crawler = $this->crawlers[$this->platform] ?? null;
            if (!$crawler) {
                throw new Exception("Crawler not found for platform: {$this->platform}");
            }

            // Execute crawl
            $results = $crawler->crawl($rule);
            $executionTime = round(microtime(true) - $startTime, 2);

            // Log results
            Log::info('Crawler task completed', [
                'rule_id' => $this->ruleId,
                'platform' => $this->platform,
                'posts_found' => $results['posts_found'] ?? 0,
                'posts_stored' => $results['posts_stored'] ?? 0,
                'execution_time' => $executionTime,
                'errors' => count($results['errors'] ?? []),
            ]);

            // Update task statistics
            $this->updateTaskStats($rule, $results, $executionTime);

            // Dispatch follow-up tasks if needed
            $this->dispatchFollowUpTasks($rule, $results);

        } catch (Exception $e) {
            $executionTime = round(microtime(true) - $startTime, 2);
            
            Log::error('Crawler task failed', [
                'rule_id' => $this->ruleId,
                'platform' => $this->platform,
                'job_id' => $this->job->getJobId(),
                'attempt' => $this->attempts(),
                'execution_time' => $executionTime,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('Crawler task permanently failed', [
            'rule_id' => $this->ruleId,
            'platform' => $this->platform,
            'job_id' => $this->job?->getJobId(),
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);

        // Update rule to indicate failure
        try {
            $rule = CrawlerRule::find($this->ruleId);
            if ($rule) {
                $rule->updatePerformanceMetrics([
                    'last_failure' => [
                        'timestamp' => now()->toISOString(),
                        'platform' => $this->platform,
                        'error' => $exception->getMessage(),
                        'attempts' => $this->attempts(),
                    ],
                ]);
            }
        } catch (Exception $e) {
            Log::error('Failed to update rule failure metrics', [
                'rule_id' => $this->ruleId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Initialize crawler services.
     */
    private function initializeCrawlers(
        TwitterCrawlerService $twitterCrawler,
        RedditCrawlerService $redditCrawler,
        TelegramCrawlerService $telegramCrawler
    ): void {
        $this->crawlers = [
            'twitter' => $twitterCrawler,
            'reddit' => $redditCrawler,
            'telegram' => $telegramCrawler,
        ];
    }

    /**
     * Get appropriate queue name based on task priority.
     */
    private function getQueueName(): string
    {
        $priority = $this->taskConfig['priority'] ?? 'normal';
        
        return match ($priority) {
            'high' => 'crawler-high',
            'low' => 'crawler-low',
            default => 'crawler-normal',
        };
    }

    /**
     * Update task statistics and performance metrics.
     */
    private function updateTaskStats(CrawlerRule $rule, array $results, float $executionTime): void
    {
        try {
            // Update rule statistics
            $rule->updateCrawlStats([
                'posts_found' => $results['posts_found'] ?? 0,
                'posts_processed' => $results['posts_processed'] ?? 0,
                'platform' => $this->platform,
                'execution_time' => $executionTime,
                'job_id' => $this->job->getJobId(),
                'queue_processing' => true,
                'timestamp' => now()->toISOString(),
            ]);

            // Update performance metrics
            $rule->updatePerformanceMetrics([
                'queue_processing' => [
                    'last_execution' => [
                        'timestamp' => now()->toISOString(),
                        'platform' => $this->platform,
                        'execution_time' => $executionTime,
                        'posts_found' => $results['posts_found'] ?? 0,
                        'posts_stored' => $results['posts_stored'] ?? 0,
                        'memory_usage' => memory_get_peak_usage(true),
                        'job_id' => $this->job->getJobId(),
                    ],
                ],
            ]);

        } catch (Exception $e) {
            Log::warning('Failed to update task statistics', [
                'rule_id' => $this->ruleId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dispatch follow-up tasks based on results.
     */
    private function dispatchFollowUpTasks(CrawlerRule $rule, array $results): void
    {
        try {
            // If we found many posts, schedule a follow-up crawl sooner
            $postsFound = $results['posts_found'] ?? 0;
            
            if ($postsFound > 50) {
                // Schedule immediate follow-up for high activity
                $followUpDelay = 5 * 60; // 5 minutes
            } elseif ($postsFound > 20) {
                // Schedule follow-up for moderate activity
                $followUpDelay = 15 * 60; // 15 minutes
            } else {
                // Normal scheduling - no immediate follow-up needed
                return;
            }

            // Dispatch delayed follow-up task
            ProcessCrawlerTask::dispatch($this->ruleId, $this->platform, [
                'priority' => 'normal',
                'follow_up' => true,
                'parent_job_id' => $this->job->getJobId(),
            ])->delay(now()->addSeconds($followUpDelay));

            Log::info('Scheduled follow-up crawler task', [
                'rule_id' => $this->ruleId,
                'platform' => $this->platform,
                'delay_seconds' => $followUpDelay,
                'posts_found' => $postsFound,
            ]);

        } catch (Exception $e) {
            Log::warning('Failed to dispatch follow-up tasks', [
                'rule_id' => $this->ruleId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get unique tags for the job (for monitoring/debugging).
     */
    public function tags(): array
    {
        return [
            'crawler',
            "platform:{$this->platform}",
            "rule:{$this->ruleId}",
            'queue-processing',
        ];
    }

    /**
     * Get middleware for the job.
     */
    public function middleware(): array
    {
        return [
            // Add rate limiting middleware to prevent overwhelming APIs
            new \Illuminate\Queue\Middleware\RateLimited('crawler'),
        ];
    }

    /**
     * Calculate the number of seconds to wait before retrying.
     */
    public function backoff(): array
    {
        // Exponential backoff: 1 minute, 3 minutes, 9 minutes
        return [60, 180, 540];
    }

    /**
     * Determine if the job should be retried based on the exception.
     */
    public function retryUntil(): \DateTime
    {
        // Retry for up to 1 hour
        return now()->addHour();
    }

    /**
     * Create crawler task for multiple platforms.
     */
    public static function dispatchForRule(CrawlerRule $rule, array $options = []): array
    {
        $dispatched = [];
        $priority = $options['priority'] ?? 'normal';
        $delay = $options['delay'] ?? 0;

        foreach ($rule->platforms as $platform) {
            try {
                $job = self::dispatch($rule->id, $platform, [
                    'priority' => $priority,
                    'batch_id' => $options['batch_id'] ?? null,
                ]);

                if ($delay > 0) {
                    $job->delay(now()->addSeconds($delay));
                }

                $dispatched[] = [
                    'platform' => $platform,
                    'job_id' => $job->getJobId() ?? 'unknown',
                    'status' => 'dispatched',
                ];

                Log::info('Dispatched crawler task', [
                    'rule_id' => $rule->id,
                    'platform' => $platform,
                    'priority' => $priority,
                    'delay' => $delay,
                ]);

            } catch (Exception $e) {
                $dispatched[] = [
                    'platform' => $platform,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];

                Log::error('Failed to dispatch crawler task', [
                    'rule_id' => $rule->id,
                    'platform' => $platform,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $dispatched;
    }

    /**
     * Dispatch batch crawl tasks for multiple rules.
     */
    public static function dispatchBatch(array $rules, array $options = []): array
    {
        $batchId = $options['batch_id'] ?? 'batch_' . time();
        $results = [];

        foreach ($rules as $rule) {
            if (!($rule instanceof CrawlerRule)) {
                continue;
            }

            $ruleResults = self::dispatchForRule($rule, array_merge($options, [
                'batch_id' => $batchId,
            ]));

            $results[$rule->id] = $ruleResults;
        }

        Log::info('Dispatched batch crawler tasks', [
            'batch_id' => $batchId,
            'rules_count' => count($rules),
            'total_tasks' => array_sum(array_map('count', $results)),
        ]);

        return [
            'batch_id' => $batchId,
            'rules' => $results,
            'summary' => [
                'rules_processed' => count($rules),
                'total_tasks' => array_sum(array_map('count', $results)),
            ],
        ];
    }
}