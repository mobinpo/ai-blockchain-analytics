<?php

namespace App\Services\CrawlerMicroService;

use App\Models\SocialMediaPost;
use App\Models\CrawlerKeywordRule;
use App\Models\CrawlerJobStatus;
use App\Services\CrawlerMicroService\Platforms\TwitterCrawler;
use App\Services\CrawlerMicroService\Platforms\RedditCrawler;
use App\Services\CrawlerMicroService\Platforms\TelegramCrawler;
use App\Services\CrawlerMicroService\Processors\ContentProcessor;
use App\Services\CrawlerMicroService\Storage\DataStorage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Laravel\Octane\Facades\Octane;
use Exception;
use Carbon\Carbon;

class SocialMediaCrawler
{
    private TwitterCrawler $twitterCrawler;
    private RedditCrawler $redditCrawler;
    private TelegramCrawler $telegramCrawler;
    private ContentProcessor $contentProcessor;
    private DataStorage $dataStorage;
    private array $config;
    private array $metrics;

    public function __construct()
    {
        $this->config = config('crawler_microservice');
        $this->twitterCrawler = new TwitterCrawler($this->config['twitter']);
        $this->redditCrawler = new RedditCrawler($this->config['reddit']);
        $this->telegramCrawler = new TelegramCrawler($this->config['telegram']);
        $this->contentProcessor = new ContentProcessor($this->config['processing']);
        $this->dataStorage = new DataStorage($this->config['storage']);
        $this->metrics = [];
    }

    /**
     * Main entry point for the crawler microservice
     */
    public function crawl(array $options = []): array
    {
        $startTime = microtime(true);
        $jobId = $options['job_id'] ?? uniqid('crawler_');
        
        Log::info('Starting social media crawler', [
            'job_id' => $jobId,
            'options' => $options
        ]);

        try {
            // Create job status record
            $jobStatus = $this->createJobStatus($jobId, $options);
            
            // Get active keyword rules
            $keywordRules = $this->getActiveKeywordRules($options);
            
            if (empty($keywordRules)) {
                throw new Exception('No active keyword rules found');
            }

            // Initialize metrics
            $this->initializeMetrics($jobId);
            
            // Run crawlers concurrently using Octane tasks
            $results = $this->runCrawlersInParallel($keywordRules, $options);
            
            // Process and store results
            $processedData = $this->processResults($results, $keywordRules);
            
            // Store data
            $storedCount = $this->dataStorage->store($processedData);
            
            // Update job status
            $this->updateJobStatus($jobStatus, 'completed', [
                'posts_collected' => count($processedData),
                'posts_stored' => $storedCount,
                'execution_time' => microtime(true) - $startTime,
                'metrics' => $this->metrics
            ]);

            Log::info('Crawler job completed successfully', [
                'job_id' => $jobId,
                'posts_collected' => count($processedData),
                'posts_stored' => $storedCount,
                'execution_time' => microtime(true) - $startTime
            ]);

            return [
                'success' => true,
                'job_id' => $jobId,
                'posts_collected' => count($processedData),
                'posts_stored' => $storedCount,
                'execution_time' => microtime(true) - $startTime,
                'metrics' => $this->metrics
            ];

        } catch (Exception $e) {
            Log::error('Crawler job failed', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);

            if (isset($jobStatus)) {
                $this->updateJobStatus($jobStatus, 'failed', [
                    'error_message' => $e->getMessage(),
                    'execution_time' => microtime(true) - $startTime
                ]);
            }

            return [
                'success' => false,
                'job_id' => $jobId,
                'error' => $e->getMessage(),
                'execution_time' => microtime(true) - $startTime
            ];
        }
    }

    /**
     * Run all platform crawlers in parallel using Octane tasks
     */
    private function runCrawlersInParallel(array $keywordRules, array $options): array
    {
        $tasks = [];
        $results = [];

        // Twitter crawler task
        if ($this->config['twitter']['enabled']) {
            $tasks['twitter'] = function () use ($keywordRules, $options) {
                return $this->runTwitterCrawler($keywordRules, $options);
            };
        }

        // Reddit crawler task
        if ($this->config['reddit']['enabled']) {
            $tasks['reddit'] = function () use ($keywordRules, $options) {
                return $this->runRedditCrawler($keywordRules, $options);
            };
        }

        // Telegram crawler task
        if ($this->config['telegram']['enabled']) {
            $tasks['telegram'] = function () use ($keywordRules, $options) {
                return $this->runTelegramCrawler($keywordRules, $options);
            };
        }

        // Execute tasks in parallel using Octane
        if (!empty($tasks)) {
            $results = Octane::concurrently($tasks);
        }

        return $results;
    }

    /**
     * Run Twitter crawler
     */
    private function runTwitterCrawler(array $keywordRules, array $options): array
    {
        $startTime = microtime(true);
        $posts = [];
        
        try {
            Log::info('Starting Twitter crawler');
            
            foreach ($keywordRules as $rule) {
                if (in_array('twitter', $rule['platforms'])) {
                    $twitterPosts = $this->twitterCrawler->searchByKeywords(
                        $rule['keywords'],
                        $options['max_posts'] ?? $this->config['schedule']['batch_size']
                    );
                    
                    $posts = array_merge($posts, $twitterPosts);
                    
                    // Respect rate limits
                    $this->respectRateLimit('twitter');
                }
            }
            
            $this->metrics['twitter'] = [
                'posts_collected' => count($posts),
                'execution_time' => microtime(true) - $startTime,
                'status' => 'success'
            ];
            
            Log::info('Twitter crawler completed', ['posts_collected' => count($posts)]);
            
        } catch (Exception $e) {
            Log::error('Twitter crawler failed', ['error' => $e->getMessage()]);
            
            $this->metrics['twitter'] = [
                'posts_collected' => 0,
                'execution_time' => microtime(true) - $startTime,
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
        
        return $posts;
    }

    /**
     * Run Reddit crawler
     */
    private function runRedditCrawler(array $keywordRules, array $options): array
    {
        $startTime = microtime(true);
        $posts = [];
        
        try {
            Log::info('Starting Reddit crawler');
            
            foreach ($keywordRules as $rule) {
                if (in_array('reddit', $rule['platforms'])) {
                    $redditPosts = $this->redditCrawler->searchByKeywords(
                        $rule['keywords'],
                        $options['max_posts'] ?? $this->config['schedule']['batch_size']
                    );
                    
                    $posts = array_merge($posts, $redditPosts);
                    
                    // Respect rate limits
                    $this->respectRateLimit('reddit');
                }
            }
            
            $this->metrics['reddit'] = [
                'posts_collected' => count($posts),
                'execution_time' => microtime(true) - $startTime,
                'status' => 'success'
            ];
            
            Log::info('Reddit crawler completed', ['posts_collected' => count($posts)]);
            
        } catch (Exception $e) {
            Log::error('Reddit crawler failed', ['error' => $e->getMessage()]);
            
            $this->metrics['reddit'] = [
                'posts_collected' => 0,
                'execution_time' => microtime(true) - $startTime,
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
        
        return $posts;
    }

    /**
     * Run Telegram crawler
     */
    private function runTelegramCrawler(array $keywordRules, array $options): array
    {
        $startTime = microtime(true);
        $posts = [];
        
        try {
            Log::info('Starting Telegram crawler');
            
            foreach ($keywordRules as $rule) {
                if (in_array('telegram', $rule['platforms'])) {
                    $telegramPosts = $this->telegramCrawler->searchByKeywords(
                        $rule['keywords'],
                        $options['max_posts'] ?? $this->config['schedule']['batch_size']
                    );
                    
                    $posts = array_merge($posts, $telegramPosts);
                    
                    // Respect rate limits
                    $this->respectRateLimit('telegram');
                }
            }
            
            $this->metrics['telegram'] = [
                'posts_collected' => count($posts),
                'execution_time' => microtime(true) - $startTime,
                'status' => 'success'
            ];
            
            Log::info('Telegram crawler completed', ['posts_collected' => count($posts)]);
            
        } catch (Exception $e) {
            Log::error('Telegram crawler failed', ['error' => $e->getMessage()]);
            
            $this->metrics['telegram'] = [
                'posts_collected' => 0,
                'execution_time' => microtime(true) - $startTime,
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
        
        return $posts;
    }

    /**
     * Process collected results
     */
    private function processResults(array $results, array $keywordRules): array
    {
        $allPosts = [];
        
        // Flatten results from all platforms
        foreach ($results as $platform => $posts) {
            if (is_array($posts)) {
                foreach ($posts as $post) {
                    $post['platform'] = $platform;
                    $post['collected_at'] = Carbon::now()->toISOString();
                    $allPosts[] = $post;
                }
            }
        }
        
        Log::info('Processing collected posts', ['total_posts' => count($allPosts)]);
        
        // Process content (sentiment analysis, keyword matching, etc.)
        $processedPosts = $this->contentProcessor->process($allPosts, $keywordRules);
        
        // Remove duplicates
        $uniquePosts = $this->removeDuplicates($processedPosts);
        
        Log::info('Post processing completed', [
            'original_count' => count($allPosts),
            'processed_count' => count($processedPosts),
            'unique_count' => count($uniquePosts)
        ]);
        
        return $uniquePosts;
    }

    /**
     * Remove duplicate posts
     */
    private function removeDuplicates(array $posts): array
    {
        $seen = [];
        $unique = [];
        
        foreach ($posts as $post) {
            // Create a hash of the content to identify duplicates
            $contentHash = hash('sha256', $post['content'] . $post['platform']);
            
            if (!isset($seen[$contentHash])) {
                $seen[$contentHash] = true;
                $unique[] = $post;
            }
        }
        
        return $unique;
    }

    /**
     * Get active keyword rules
     */
    private function getActiveKeywordRules(array $options): array
    {
        if (isset($options['keyword_rules'])) {
            return $options['keyword_rules'];
        }
        
        // Fetch from database
        $rules = CrawlerKeywordRule::where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get()
            ->toArray();
        
        // If no database rules, use default config rules
        if (empty($rules)) {
            $defaultRules = [];
            foreach ($this->config['default_keyword_rules'] as $category => $rule) {
                $defaultRules[] = array_merge($rule, [
                    'id' => $category,
                    'category' => $category,
                    'is_active' => true
                ]);
            }
            return $defaultRules;
        }
        
        return $rules;
    }

    /**
     * Respect rate limits for platforms
     */
    private function respectRateLimit(string $platform): void
    {
        $rateLimitConfig = $this->config['rate_limits'][$platform] ?? null;
        
        if (!$rateLimitConfig) {
            return;
        }
        
        $cacheKey = "crawler_rate_limit:{$platform}";
        $requests = Cache::get($cacheKey, 0);
        
        if ($requests >= $rateLimitConfig['requests_per_minute']) {
            Log::info("Rate limit reached for {$platform}, waiting...");
            sleep($rateLimitConfig['cooldown_seconds']);
            Cache::forget($cacheKey);
        } else {
            Cache::put($cacheKey, $requests + 1, 60); // Cache for 1 minute
        }
    }

    /**
     * Create job status record
     */
    private function createJobStatus(string $jobId, array $options): CrawlerJobStatus
    {
        return CrawlerJobStatus::create([
            'job_id' => $jobId,
            'status' => 'running',
            'platform' => 'all',
            'options' => $options,
            'started_at' => Carbon::now(),
            'metadata' => [
                'deployment_mode' => $this->config['deployment_mode'],
                'config_version' => '1.0'
            ]
        ]);
    }

    /**
     * Update job status
     */
    private function updateJobStatus(CrawlerJobStatus $jobStatus, string $status, array $results): void
    {
        $jobStatus->update([
            'status' => $status,
            'completed_at' => Carbon::now(),
            'results' => $results,
            'metadata' => array_merge($jobStatus->metadata ?? [], [
                'updated_at' => Carbon::now()->toISOString()
            ])
        ]);
    }

    /**
     * Initialize metrics collection
     */
    private function initializeMetrics(string $jobId): void
    {
        $this->metrics = [
            'job_id' => $jobId,
            'started_at' => Carbon::now()->toISOString(),
            'platforms' => []
        ];
    }

    /**
     * Schedule periodic crawling
     */
    public function scheduleCrawling(): void
    {
        if (!$this->config['enabled']) {
            Log::info('Crawler microservice is disabled');
            return;
        }

        $interval = $this->config['schedule']['interval'];
        
        // Use Octane's task scheduling for non-blocking execution
        Octane::tick('social-media-crawler', function () {
            $this->crawl();
        })->seconds($interval);
        
        Log::info('Scheduled crawler microservice', ['interval' => $interval]);
    }

    /**
     * Health check for the crawler service
     */
    public function healthCheck(): array
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => Carbon::now()->toISOString(),
            'platforms' => []
        ];

        // Check Twitter API connectivity
        if ($this->config['twitter']['enabled']) {
            try {
                $health['platforms']['twitter'] = $this->twitterCrawler->healthCheck();
            } catch (Exception $e) {
                $health['platforms']['twitter'] = [
                    'status' => 'unhealthy',
                    'error' => $e->getMessage()
                ];
                $health['status'] = 'degraded';
            }
        }

        // Check Reddit API connectivity
        if ($this->config['reddit']['enabled']) {
            try {
                $health['platforms']['reddit'] = $this->redditCrawler->healthCheck();
            } catch (Exception $e) {
                $health['platforms']['reddit'] = [
                    'status' => 'unhealthy',
                    'error' => $e->getMessage()
                ];
                $health['status'] = 'degraded';
            }
        }

        // Check Telegram API connectivity
        if ($this->config['telegram']['enabled']) {
            try {
                $health['platforms']['telegram'] = $this->telegramCrawler->healthCheck();
            } catch (Exception $e) {
                $health['platforms']['telegram'] = [
                    'status' => 'unhealthy',
                    'error' => $e->getMessage()
                ];
                $health['status'] = 'degraded';
            }
        }

        return $health;
    }

    /**
     * Get crawler metrics
     */
    public function getMetrics(): array
    {
        $cacheKey = 'crawler_metrics';
        
        return Cache::remember($cacheKey, 300, function () {
            return [
                'job_statistics' => $this->getJobStatistics(),
                'platform_statistics' => $this->getPlatformStatistics(),
                'rate_limit_status' => $this->getRateLimitStatus(),
                'performance_metrics' => $this->getPerformanceMetrics()
            ];
        });
    }

    /**
     * Get job statistics
     */
    private function getJobStatistics(): array
    {
        return [
            'total_jobs' => CrawlerJobStatus::count(),
            'running_jobs' => CrawlerJobStatus::where('status', 'running')->count(),
            'completed_jobs' => CrawlerJobStatus::where('status', 'completed')->count(),
            'failed_jobs' => CrawlerJobStatus::where('status', 'failed')->count(),
            'jobs_last_24h' => CrawlerJobStatus::where('created_at', '>=', Carbon::now()->subDay())->count()
        ];
    }

    /**
     * Get platform statistics
     */
    private function getPlatformStatistics(): array
    {
        return [
            'twitter' => [
                'posts_collected_24h' => SocialMediaPost::where('platform', 'twitter')
                    ->where('created_at', '>=', Carbon::now()->subDay())
                    ->count()
            ],
            'reddit' => [
                'posts_collected_24h' => SocialMediaPost::where('platform', 'reddit')
                    ->where('created_at', '>=', Carbon::now()->subDay())
                    ->count()
            ],
            'telegram' => [
                'posts_collected_24h' => SocialMediaPost::where('platform', 'telegram')
                    ->where('created_at', '>=', Carbon::now()->subDay())
                    ->count()
            ]
        ];
    }

    /**
     * Get rate limit status
     */
    private function getRateLimitStatus(): array
    {
        $status = [];
        
        foreach (['twitter', 'reddit', 'telegram'] as $platform) {
            $cacheKey = "crawler_rate_limit:{$platform}";
            $requests = Cache::get($cacheKey, 0);
            $limit = $this->config['rate_limits'][$platform]['requests_per_minute'] ?? 0;
            
            $status[$platform] = [
                'requests_used' => $requests,
                'requests_limit' => $limit,
                'requests_remaining' => max(0, $limit - $requests),
                'usage_percentage' => $limit > 0 ? round(($requests / $limit) * 100, 2) : 0
            ];
        }
        
        return $status;
    }

    /**
     * Get performance metrics
     */
    private function getPerformanceMetrics(): array
    {
        $completedJobs = CrawlerJobStatus::where('status', 'completed')
            ->where('created_at', '>=', Carbon::now()->subDay())
            ->get();
        
        if ($completedJobs->isEmpty()) {
            return [
                'average_execution_time' => 0,
                'posts_per_minute' => 0,
                'success_rate' => 0
            ];
        }
        
        $totalExecutionTime = $completedJobs->sum(function ($job) {
            return $job->results['execution_time'] ?? 0;
        });
        
        $totalPosts = $completedJobs->sum(function ($job) {
            return $job->results['posts_collected'] ?? 0;
        });
        
        $totalJobs = CrawlerJobStatus::where('created_at', '>=', Carbon::now()->subDay())->count();
        $successfulJobs = $completedJobs->count();
        
        return [
            'average_execution_time' => round($totalExecutionTime / $completedJobs->count(), 2),
            'posts_per_minute' => $totalExecutionTime > 0 ? round($totalPosts / ($totalExecutionTime / 60), 2) : 0,
            'success_rate' => $totalJobs > 0 ? round(($successfulJobs / $totalJobs) * 100, 2) : 0
        ];
    }
}