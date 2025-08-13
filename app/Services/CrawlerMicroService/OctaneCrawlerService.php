<?php

declare(strict_types=1);

namespace App\Services\CrawlerMicroService;

use App\Models\CrawlerJobStatus;
use App\Models\SocialMediaPost;
use App\Services\CrawlerMicroService\Engine\AdvancedKeywordEngine;
use App\Services\CrawlerMicroService\Platforms\EnhancedTwitterCrawler;
use App\Services\CrawlerMicroService\Platforms\EnhancedRedditCrawler;
use App\Services\CrawlerMicroService\Platforms\EnhancedTelegramCrawler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Octane\Facades\Octane;
use Exception;
use Carbon\Carbon;

final class OctaneCrawlerService
{
    private AdvancedKeywordEngine $keywordEngine;
    private array $crawlers = [];
    private array $config;
    private array $metrics = [];

    public function __construct()
    {
        $this->config = config('crawler_microservice', []);
        $this->keywordEngine = new AdvancedKeywordEngine($this->config['keyword_engine'] ?? []);
        
        $this->initializeCrawlers();
        $this->initializeMetrics();
    }

    /**
     * Initialize platform crawlers
     */
    private function initializeCrawlers(): void
    {
        try {
            // Initialize Twitter crawler
            if ($this->config['platforms']['twitter']['enabled'] ?? false) {
                $this->crawlers['twitter'] = new EnhancedTwitterCrawler(
                    $this->keywordEngine,
                    $this->config['platforms']['twitter'] ?? []
                );
            }

            // Initialize Reddit crawler
            if ($this->config['platforms']['reddit']['enabled'] ?? false) {
                $this->crawlers['reddit'] = new EnhancedRedditCrawler(
                    $this->keywordEngine,
                    $this->config['platforms']['reddit'] ?? []
                );
            }

            // Initialize Telegram crawler
            if ($this->config['platforms']['telegram']['enabled'] ?? false) {
                $this->crawlers['telegram'] = new EnhancedTelegramCrawler(
                    $this->keywordEngine,
                    $this->config['platforms']['telegram'] ?? []
                );
            }

            Log::info('Crawler platforms initialized', [
                'enabled_platforms' => array_keys($this->crawlers)
            ]);

        } catch (Exception $e) {
            Log::error('Failed to initialize crawler platforms', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Initialize metrics tracking
     */
    private function initializeMetrics(): void
    {
        $this->metrics = [
            'start_time' => microtime(true),
            'platforms_processed' => 0,
            'total_posts_collected' => 0,
            'total_posts_stored' => 0,
            'total_keyword_matches' => 0,
            'errors' => [],
            'platform_stats' => [],
        ];
    }

    /**
     * Execute crawling job using Octane concurrent tasks
     */
    public function executeCrawlJob(array $jobConfig = []): array
    {
        $jobId = $jobConfig['job_id'] ?? uniqid('octane_crawl_');
        $platforms = $jobConfig['platforms'] ?? array_keys($this->crawlers);
        $keywords = $jobConfig['keywords'] ?? null;

        Log::info('Starting Octane crawler job', [
            'job_id' => $jobId,
            'platforms' => $platforms,
            'keywords_count' => $keywords ? count($keywords) : 'auto-detect'
        ]);

        try {
            // Create job status record
            $jobStatus = $this->createJobStatus($jobId, $jobConfig);

            // Update job status to running
            $this->updateJobStatus($jobStatus, 'running', ['started_at' => now()]);

            // Execute crawling tasks concurrently using Octane
            $results = $this->runConcurrentCrawlers($platforms, $keywords, $jobId);

            // Process and aggregate results
            $aggregatedResults = $this->aggregateResults($results);

            // Update final job status
            $this->updateJobStatus($jobStatus, 'completed', array_merge($aggregatedResults, [
                'completed_at' => now(),
                'execution_time' => microtime(true) - $this->metrics['start_time'],
                'metrics' => $this->metrics
            ]));

            Log::info('Octane crawler job completed', [
                'job_id' => $jobId,
                'platforms_processed' => count($results),
                'total_posts' => $aggregatedResults['total_posts_collected'],
                'execution_time' => $this->metrics['execution_time'] ?? 0
            ]);

            return $aggregatedResults;

        } catch (Exception $e) {
            Log::error('Octane crawler job failed', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            if (isset($jobStatus)) {
                $this->updateJobStatus($jobStatus, 'failed', [
                    'error_message' => $e->getMessage(),
                    'failed_at' => now()
                ]);
            }

            throw $e;
        }
    }

    /**
     * Run crawlers concurrently using Octane
     */
    private function runConcurrentCrawlers(array $platforms, ?array $keywords, string $jobId): array
    {
        $tasks = [];

        // Create crawling tasks for each platform
        foreach ($platforms as $platform) {
            if (!isset($this->crawlers[$platform])) {
                Log::warning("Crawler not available for platform: {$platform}");
                continue;
            }

            $tasks[$platform] = function () use ($platform, $keywords, $jobId) {
                return $this->executePlatformCrawl($platform, $keywords, $jobId);
            };
        }

        // Execute tasks concurrently
        if (empty($tasks)) {
            Log::warning('No crawler tasks to execute');
            return [];
        }

        Log::info('Executing concurrent crawler tasks', [
            'platforms' => array_keys($tasks),
            'job_id' => $jobId
        ]);

        // Use Octane's concurrent execution
        $results = Octane::concurrently($tasks);

        return $results;
    }

    /**
     * Execute crawling for a specific platform
     */
    private function executePlatformCrawl(string $platform, ?array $keywords, string $jobId): array
    {
        $startTime = microtime(true);
        
        try {
            Log::info("Starting {$platform} crawl", ['job_id' => $jobId]);

            $crawler = $this->crawlers[$platform];
            $options = [
                'keywords' => $keywords,
                'job_id' => $jobId,
            ];

            // Add platform-specific options
            $platformConfig = $this->config['platforms'][$platform] ?? [];
            $options = array_merge($options, $platformConfig);

            // Execute the crawl
            $results = $crawler->crawl($options);

            // Calculate platform metrics
            $platformMetrics = $this->calculatePlatformMetrics($results, $platform);
            
            Log::info("Completed {$platform} crawl", [
                'job_id' => $jobId,
                'execution_time' => microtime(true) - $startTime,
                'posts_collected' => $platformMetrics['posts_collected'],
                'keyword_matches' => $platformMetrics['keyword_matches']
            ]);

            return [
                'platform' => $platform,
                'success' => true,
                'results' => $results,
                'metrics' => $platformMetrics,
                'execution_time' => microtime(true) - $startTime,
            ];

        } catch (Exception $e) {
            Log::error("Platform crawl failed: {$platform}", [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
                'execution_time' => microtime(true) - $startTime
            ]);

            $this->metrics['errors'][] = [
                'platform' => $platform,
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ];

            return [
                'platform' => $platform,
                'success' => false,
                'error' => $e->getMessage(),
                'execution_time' => microtime(true) - $startTime,
            ];
        }
    }

    /**
     * Calculate metrics for a platform's results
     */
    private function calculatePlatformMetrics(array $results, string $platform): array
    {
        $postsCollected = 0;
        $keywordMatches = 0;
        $channels = [];

        foreach ($results as $channel => $channelData) {
            if (is_array($channelData) && isset($channelData['posts'])) {
                $channelPosts = is_array($channelData['posts']) ? $channelData['posts'] : [];
                $postsCollected += count($channelPosts);
                
                foreach ($channelPosts as $post) {
                    $keywordMatches += $post['match_count'] ?? 0;
                }
                
                $channels[] = $channel;
            }
        }

        return [
            'posts_collected' => $postsCollected,
            'keyword_matches' => $keywordMatches,
            'channels_processed' => count($channels),
            'channels' => $channels,
        ];
    }

    /**
     * Aggregate results from all platforms
     */
    private function aggregateResults(array $platformResults): array
    {
        $aggregated = [
            'job_summary' => [
                'platforms_requested' => count($platformResults),
                'platforms_successful' => 0,
                'platforms_failed' => 0,
            ],
            'totals' => [
                'posts_collected' => 0,
                'keyword_matches' => 0,
                'channels_processed' => 0,
            ],
            'platform_breakdown' => [],
            'errors' => [],
            'execution_times' => [],
        ];

        foreach ($platformResults as $platformName => $result) {
            if ($result['success']) {
                $aggregated['job_summary']['platforms_successful']++;
                
                $metrics = $result['metrics'] ?? [];
                $aggregated['totals']['posts_collected'] += $metrics['posts_collected'] ?? 0;
                $aggregated['totals']['keyword_matches'] += $metrics['keyword_matches'] ?? 0;
                $aggregated['totals']['channels_processed'] += $metrics['channels_processed'] ?? 0;
                
                $aggregated['platform_breakdown'][$platformName] = $metrics;
                
            } else {
                $aggregated['job_summary']['platforms_failed']++;
                $aggregated['errors'][] = [
                    'platform' => $platformName,
                    'error' => $result['error'] ?? 'Unknown error'
                ];
            }

            $aggregated['execution_times'][$platformName] = $result['execution_time'] ?? 0;
        }

        // Update global metrics
        $this->metrics['platforms_processed'] = $aggregated['job_summary']['platforms_successful'];
        $this->metrics['total_posts_collected'] = $aggregated['totals']['posts_collected'];
        $this->metrics['total_keyword_matches'] = $aggregated['totals']['keyword_matches'];
        $this->metrics['execution_time'] = microtime(true) - $this->metrics['start_time'];
        $this->metrics['platform_stats'] = $aggregated['platform_breakdown'];

        return $aggregated;
    }

    /**
     * Create job status record
     */
    private function createJobStatus(string $jobId, array $config): CrawlerJobStatus
    {
        return CrawlerJobStatus::create([
            'job_id' => $jobId,
            'status' => 'pending',
            'config' => $config,
            'platforms' => array_keys($this->crawlers),
            'created_at' => now(),
            'metadata' => [
                'service_type' => 'octane',
                'worker_id' => gethostname(),
                'keyword_engine_version' => '2.0',
            ]
        ]);
    }

    /**
     * Update job status
     */
    private function updateJobStatus(CrawlerJobStatus $jobStatus, string $status, array $data = []): void
    {
        $updateData = array_merge($data, [
            'status' => $status,
            'updated_at' => now(),
        ]);

        $jobStatus->update($updateData);
    }

    /**
     * Schedule crawling job for later execution
     */
    public function scheduleCrawlJob(array $jobConfig, Carbon $scheduledAt = null): string
    {
        $jobId = $jobConfig['job_id'] ?? uniqid('scheduled_crawl_');
        $scheduledAt = $scheduledAt ?? now()->addMinutes(5);

        // Store job in cache for scheduled execution
        $cacheKey = "scheduled_crawl_job_{$jobId}";
        Cache::put($cacheKey, $jobConfig, $scheduledAt->diffInMinutes(now()) + 60);

        Log::info('Crawl job scheduled', [
            'job_id' => $jobId,
            'scheduled_at' => $scheduledAt->toISOString(),
            'platforms' => $jobConfig['platforms'] ?? 'all'
        ]);

        return $jobId;
    }

    /**
     * Execute scheduled job
     */
    public function executeScheduledJob(string $jobId): array
    {
        $cacheKey = "scheduled_crawl_job_{$jobId}";
        $jobConfig = Cache::get($cacheKey);

        if (!$jobConfig) {
            throw new Exception("Scheduled job not found: {$jobId}");
        }

        // Remove from cache
        Cache::forget($cacheKey);

        // Execute the job
        return $this->executeCrawlJob($jobConfig);
    }

    /**
     * Get real-time crawler statistics
     */
    public function getCrawlerStats(): array
    {
        $recentJobs = CrawlerJobStatus::where('created_at', '>=', now()->subHours(24))
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $stats = [
            'last_24_hours' => [
                'total_jobs' => $recentJobs->count(),
                'successful_jobs' => $recentJobs->where('status', 'completed')->count(),
                'failed_jobs' => $recentJobs->where('status', 'failed')->count(),
                'running_jobs' => $recentJobs->where('status', 'running')->count(),
            ],
            'platform_performance' => [],
            'current_status' => [
                'enabled_platforms' => array_keys($this->crawlers),
                'keyword_rules_active' => $this->keywordEngine->getActiveRulesCount(),
                'last_job_at' => $recentJobs->first()?->created_at,
            ],
            'recent_posts' => SocialMediaPost::where('created_at', '>=', now()->subHours(1))
                ->selectRaw('platform, COUNT(*) as count')
                ->groupBy('platform')
                ->get()
                ->pluck('count', 'platform')
                ->toArray(),
        ];

        // Calculate platform performance
        foreach ($recentJobs as $job) {
            $platforms = $job->platforms ?? [];
            foreach ($platforms as $platform) {
                if (!isset($stats['platform_performance'][$platform])) {
                    $stats['platform_performance'][$platform] = [
                        'total_runs' => 0,
                        'successful_runs' => 0,
                        'avg_execution_time' => 0,
                        'total_posts' => 0,
                    ];
                }
                
                $stats['platform_performance'][$platform]['total_runs']++;
                if ($job->status === 'completed') {
                    $stats['platform_performance'][$platform]['successful_runs']++;
                }
            }
        }

        return $stats;
    }

    /**
     * Health check for the crawler service
     */
    public function healthCheck(): array
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'checks' => [],
        ];

        // Check platform availability
        foreach ($this->crawlers as $platform => $crawler) {
            try {
                $platformHealth = $this->checkPlatformHealth($platform);
                $health['checks'][$platform] = $platformHealth;
                
                if (!$platformHealth['healthy']) {
                    $health['status'] = 'degraded';
                }
            } catch (Exception $e) {
                $health['checks'][$platform] = [
                    'healthy' => false,
                    'error' => $e->getMessage()
                ];
                $health['status'] = 'degraded';
            }
        }

        // Check database connectivity
        try {
            DB::connection()->getPdo();
            $health['checks']['database'] = ['healthy' => true];
        } catch (Exception $e) {
            $health['checks']['database'] = ['healthy' => false, 'error' => $e->getMessage()];
            $health['status'] = 'unhealthy';
        }

        // Check cache connectivity
        try {
            Cache::put('health_check', 'ok', 10);
            $health['checks']['cache'] = ['healthy' => true];
        } catch (Exception $e) {
            $health['checks']['cache'] = ['healthy' => false, 'error' => $e->getMessage()];
            $health['status'] = 'unhealthy';
        }

        return $health;
    }

    /**
     * Check health of a specific platform
     */
    private function checkPlatformHealth(string $platform): array
    {
        // Implementation would check API connectivity, rate limits, etc.
        // For now, return healthy if crawler exists
        return [
            'healthy' => isset($this->crawlers[$platform]),
            'rate_limit_status' => 'ok', // Would check actual rate limits
            'api_connectivity' => 'ok',   // Would test API endpoints
        ];
    }

    /**
     * Refresh keyword rules across all crawlers
     */
    public function refreshKeywordRules(): void
    {
        $this->keywordEngine->refreshRules();
        
        Log::info('Keyword rules refreshed for all platforms');
    }

    /**
     * Get current configuration
     */
    public function getConfiguration(): array
    {
        return [
            'platforms' => array_keys($this->crawlers),
            'config' => $this->config,
            'keyword_engine' => [
                'active_rules' => $this->keywordEngine->getActiveRulesCount(),
                'last_refresh' => Cache::get('keyword_rules_last_refresh'),
            ],
        ];
    }
}
