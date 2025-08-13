<?php

declare(strict_types=1);

namespace App\Services\CrawlerMicroService;

use App\Models\CrawlerKeywordRule;
use App\Models\CrawlerJobStatus;
use App\Models\SocialMediaPost;
use App\Services\CrawlerMicroService\Platforms\TwitterCrawler;
use App\Services\CrawlerMicroService\Platforms\RedditCrawler;
use App\Services\CrawlerMicroService\Platforms\TelegramCrawler;
use App\Services\CrawlerMicroService\Engine\KeywordEngine;
use App\Services\CrawlerMicroService\Engine\ContentProcessor;
use App\Services\CrawlerMicroService\Engine\RateLimiter;
use App\Services\CrawlerMicroService\CrawlerCacheManager;
use App\Services\PostgresCacheService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Exception;
use Carbon\Carbon;

/**
 * Central crawler microservice manager
 * 
 * Coordinates crawling activities across Twitter/X, Reddit, and Telegram
 * with keyword-based filtering and intelligent scheduling.
 */
class CrawlerManager
{
    private array $crawlers = [];
    private KeywordEngine $keywordEngine;
    private ContentProcessor $contentProcessor;
    private RateLimiter $rateLimiter;
    private array $config;
    private array $metrics = [];

    public function __construct(
        KeywordEngine $keywordEngine,
        ContentProcessor $contentProcessor,
        RateLimiter $rateLimiter
    ) {
        $this->keywordEngine = $keywordEngine;
        $this->contentProcessor = $contentProcessor;
        $this->rateLimiter = $rateLimiter;
        $this->config = config('crawler_microservice');
        
        $this->initializeCrawlers();
        $this->initializeMetrics();
    }

    /**
     * Start the crawler microservice
     */
    public function start(array $options = []): array
    {
        Log::info('🚀 Starting Crawler Microservice', $options);
        
        try {
            // Get active keyword rules
            $activeRules = $this->getActiveKeywordRules();
            
            if ($activeRules->isEmpty()) {
                Log::warning('No active keyword rules found');
                return $this->buildResponse('warning', 'No active keyword rules', []);
            }

            // Execute crawling based on deployment mode
            $deploymentMode = $options['deployment_mode'] ?? $this->config['deployment_mode'];
            
            $results = match ($deploymentMode) {
                'octane' => $this->executeOctaneCrawling($activeRules, $options),
                'lambda' => $this->executeLambdaCrawling($activeRules, $options),
                default => throw new Exception("Unsupported deployment mode: {$deploymentMode}")
            };

            // Process and store results
            $processedResults = $this->processResults($results);
            
            // Update job status and metrics
            $this->updateJobStatus('completed', $processedResults);
            $this->updateMetrics($processedResults);
            
            Log::info('✅ Crawler Microservice completed', [
                'total_posts' => $processedResults['total_posts'],
                'platforms' => array_keys($processedResults['platforms']),
                'execution_time' => $processedResults['execution_time_seconds']
            ]);
            
            return $this->buildResponse('success', 'Crawling completed successfully', $processedResults);
            
        } catch (Exception $e) {
            Log::error('❌ Crawler Microservice failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->updateJobStatus('failed', ['error' => $e->getMessage()]);
            
            return $this->buildResponse('error', $e->getMessage(), []);
        }
    }

    /**
     * Stop all crawling activities
     */
    public function stop(): array
    {
        Log::info('🛑 Stopping Crawler Microservice');
        
        try {
            foreach ($this->crawlers as $platform => $crawler) {
                if (method_exists($crawler, 'stop')) {
                    $crawler->stop();
                    Log::info("Stopped {$platform} crawler");
                }
            }
            
            $this->updateJobStatus('stopped', ['stopped_at' => now()]);
            
            return $this->buildResponse('success', 'Crawler service stopped', []);
            
        } catch (Exception $e) {
            Log::error('Error stopping crawler service', ['error' => $e->getMessage()]);
            return $this->buildResponse('error', $e->getMessage(), []);
        }
    }

    /**
     * Get current system status
     */
    public function getStatus(): array
    {
        $platformStatus = [];
        
        foreach ($this->crawlers as $platform => $crawler) {
            $platformStatus[$platform] = [
                'enabled' => $this->config['platforms'][$platform]['enabled'] ?? false,
                'rate_limit_status' => $this->rateLimiter->getStatus($platform),
                'last_activity' => Cache::get("crawler_{$platform}_last_activity"),
                'posts_today' => $this->getPostsCount($platform, today()),
                'health' => $this->checkPlatformHealth($platform)
            ];
        }

        $activeRules = $this->getActiveKeywordRules()->count();
        $jobStatus = $this->getCurrentJobStatus();
        
        return [
            'service_status' => 'running',
            'deployment_mode' => $this->config['deployment_mode'],
            'active_keyword_rules' => $activeRules,
            'platform_status' => $platformStatus,
            'current_job' => $jobStatus,
            'metrics' => $this->getMetrics(),
            'system_health' => $this->calculateSystemHealth(),
            'last_update' => now()->toISOString()
        ];
    }

    /**
     * Execute crawling in Octane mode (Laravel tasks)
     */
    private function executeOctaneCrawling(Collection $rules, array $options): array
    {
        $startTime = microtime(true);
        $results = [
            'deployment_mode' => 'octane',
            'platforms' => [],
            'total_posts' => 0,
            'execution_time_seconds' => 0
        ];

        // Group rules by platform
        $platformRules = $this->groupRulesByPlatform($rules);
        
        foreach ($platformRules as $platform => $platformRuleSet) {
            if (!$this->isPlatformEnabled($platform)) {
                continue;
            }

            Log::info("Starting {$platform} crawling with " . count($platformRuleSet) . " rules");
            
            try {
                // Check rate limits
                if (!$this->rateLimiter->canMakeRequest($platform)) {
                    Log::warning("Rate limit exceeded for {$platform}");
                    continue;
                }

                // Execute platform-specific crawling
                $crawler = $this->crawlers[$platform];
                $platformResults = $crawler->crawl($platformRuleSet, $options);
                
                $results['platforms'][$platform] = $platformResults;
                $results['total_posts'] += $platformResults['posts_collected'] ?? 0;
                
                // Update rate limiter
                $this->rateLimiter->recordRequest($platform);
                
                Log::info("Completed {$platform} crawling", [
                    'posts_collected' => $platformResults['posts_collected'] ?? 0
                ]);
                
            } catch (Exception $e) {
                Log::error("Error crawling {$platform}", [
                    'error' => $e->getMessage()
                ]);
                
                $results['platforms'][$platform] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'posts_collected' => 0
                ];
            }
        }

        $results['execution_time_seconds'] = round(microtime(true) - $startTime, 2);
        
        return $results;
    }

    /**
     * Execute crawling in Lambda mode (Python serverless)
     */
    private function executeLambdaCrawling(Collection $rules, array $options): array
    {
        Log::info('Executing Lambda-based crawling');
        
        $startTime = microtime(true);
        $results = [
            'deployment_mode' => 'lambda',
            'platforms' => [],
            'total_posts' => 0,
            'execution_time_seconds' => 0
        ];

        // Group rules by platform for Lambda execution
        $platformRules = $this->groupRulesByPlatform($rules);
        
        // Execute Lambda functions for each platform
        foreach ($platformRules as $platform => $platformRuleSet) {
            if (!$this->isPlatformEnabled($platform)) {
                continue;
            }
            
            try {
                // Queue Lambda execution job
                $lambdaResult = $this->executeLambdaFunction($platform, $platformRuleSet, $options);
                
                $results['platforms'][$platform] = $lambdaResult;
                $results['total_posts'] += $lambdaResult['posts_collected'] ?? 0;
                
            } catch (Exception $e) {
                Log::error("Error executing Lambda for {$platform}", [
                    'error' => $e->getMessage()
                ]);
                
                $results['platforms'][$platform] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'posts_collected' => 0
                ];
            }
        }

        $results['execution_time_seconds'] = round(microtime(true) - $startTime, 2);
        
        return $results;
    }

    /**
     * Execute Lambda function for specific platform
     */
    private function executeLambdaFunction(string $platform, array $rules, array $options): array
    {
        $lambdaConfig = $this->config['drivers']['lambda'];
        
        if (!$lambdaConfig['enabled']) {
            throw new Exception('Lambda deployment not enabled');
        }

        // Prepare Lambda payload
        $payload = [
            'platform' => $platform,
            'keyword_rules' => $rules,
            'options' => $options,
            'config' => $this->config['platforms'][$platform] ?? [],
            'timestamp' => now()->toISOString()
        ];

        // For now, simulate Lambda execution
        // In production, this would use AWS SDK to invoke the Lambda function
        Log::info("Simulating Lambda execution for {$platform}", [
            'rules_count' => count($rules),
            'function_name' => $lambdaConfig['lambda_function_name']
        ]);
        
        // TODO: Replace with actual AWS Lambda invocation
        return $this->simulateLambdaExecution($platform, $payload);
    }

    /**
     * Simulate Lambda execution (replace with real AWS SDK call)
     */
    private function simulateLambdaExecution(string $platform, array $payload): array
    {
        // This is a placeholder - replace with actual Lambda invocation
        sleep(2); // Simulate processing time
        
        return [
            'status' => 'completed',
            'posts_collected' => rand(10, 50),
            'execution_time_ms' => rand(1500, 5000),
            'memory_used_mb' => rand(100, 400),
            'lambda_request_id' => 'sim-' . uniqid()
        ];
    }

    /**
     * Process crawling results and store data
     */
    private function processResults(array $results): array
    {
        $totalProcessed = 0;
        $processedPlatforms = [];
        
        foreach ($results['platforms'] as $platform => $platformResults) {
            if ($platformResults['status'] === 'error') {
                continue;
            }
            
            $postsProcessed = $this->contentProcessor->processPlatformResults(
                $platform, 
                $platformResults
            );
            
            $processedPlatforms[$platform] = [
                'posts_collected' => $platformResults['posts_collected'] ?? 0,
                'posts_processed' => $postsProcessed,
                'processing_rate' => $postsProcessed > 0 ? 
                    round(($postsProcessed / max($platformResults['posts_collected'], 1)) * 100, 2) : 0
            ];
            
            $totalProcessed += $postsProcessed;
        }
        
        return [
            'total_posts' => $results['total_posts'],
            'total_processed' => $totalProcessed,
            'platforms' => $processedPlatforms,
            'execution_time_seconds' => $results['execution_time_seconds'],
            'deployment_mode' => $results['deployment_mode']
        ];
    }

    /**
     * Initialize platform crawlers
     */
    private function initializeCrawlers(): void
    {
        $this->crawlers = [
            'twitter' => new TwitterCrawler($this->rateLimiter, $this->keywordEngine),
            'reddit' => new RedditCrawler($this->rateLimiter, $this->keywordEngine),
            'telegram' => new TelegramCrawler($this->rateLimiter, $this->keywordEngine)
        ];
    }

    /**
     * Initialize metrics tracking
     */
    private function initializeMetrics(): void
    {
        $this->metrics = [
            'requests_made' => 0,
            'posts_collected' => 0,
            'errors_encountered' => 0,
            'rate_limits_hit' => 0,
            'processing_time_ms' => 0
        ];
    }

    /**
     * Get active keyword rules
     */
    private function getActiveKeywordRules(): Collection
    {
        return CrawlerKeywordRule::where('is_active', true)
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Group rules by platforms they target
     */
    private function groupRulesByPlatform(Collection $rules): array
    {
        $platformRules = [];
        
        foreach ($rules as $rule) {
            $platforms = $rule->platforms ?? ['twitter', 'reddit'];
            
            foreach ($platforms as $platform) {
                if (!isset($platformRules[$platform])) {
                    $platformRules[$platform] = [];
                }
                $platformRules[$platform][] = $rule;
            }
        }
        
        return $platformRules;
    }

    /**
     * Check if platform is enabled
     */
    private function isPlatformEnabled(string $platform): bool
    {
        return $this->config['platforms'][$platform]['enabled'] ?? false;
    }

    /**
     * Update job status
     */
    private function updateJobStatus(string $status, array $data = []): void
    {
        foreach (array_keys($this->crawlers) as $platform) {
            CrawlerJobStatus::updateOrCreate(
                ['platform' => $platform, 'job_type' => 'keyword_search'],
                [
                    'status' => $status,
                    'last_run_at' => now(),
                    'posts_collected' => $data['total_processed'] ?? 0,
                    'last_error' => $status === 'failed' ? $data : null,
                    'next_run_at' => $this->calculateNextRun($platform)
                ]
            );
        }
    }

    /**
     * Calculate next run time for platform
     */
    private function calculateNextRun(string $platform): Carbon
    {
        $interval = $this->config['platforms'][$platform]['rate_limit']['requests_per_hour'] ?? 300;
        $minutes = max(5, 60 / ($interval / 60)); // At least 5 minutes between runs
        
        return now()->addMinutes($minutes);
    }

    /**
     * Update metrics
     */
    private function updateMetrics(array $results): void
    {
        Cache::put('crawler_metrics', [
            'last_run' => now(),
            'total_posts' => $results['total_posts'],
            'execution_time' => $results['execution_time_seconds'],
            'platforms' => $results['platforms']
        ], 3600); // Cache for 1 hour
    }

    /**
     * Get current metrics
     */
    private function getMetrics(): array
    {
        return Cache::get('crawler_metrics', [
            'last_run' => null,
            'total_posts' => 0,
            'execution_time' => 0,
            'platforms' => []
        ]);
    }

    /**
     * Get posts count for platform and date
     */
    private function getPostsCount(string $platform, Carbon $date): int
    {
        return SocialMediaPost::where('platform', $platform)
            ->whereDate('created_at', $date)
            ->count();
    }

    /**
     * Check platform health
     */
    private function checkPlatformHealth(string $platform): string
    {
        $rateLimitStatus = $this->rateLimiter->getStatus($platform);
        $lastActivity = Cache::get("crawler_{$platform}_last_activity");
        
        if ($rateLimitStatus['is_rate_limited']) {
            return 'rate_limited';
        }
        
        if (!$lastActivity || $lastActivity < now()->subHours(2)) {
            return 'inactive';
        }
        
        return 'healthy';
    }

    /**
     * Calculate overall system health
     */
    private function calculateSystemHealth(): array
    {
        $healthyPlatforms = 0;
        $totalPlatforms = 0;
        
        foreach (array_keys($this->crawlers) as $platform) {
            if ($this->isPlatformEnabled($platform)) {
                $totalPlatforms++;
                if ($this->checkPlatformHealth($platform) === 'healthy') {
                    $healthyPlatforms++;
                }
            }
        }
        
        $healthPercentage = $totalPlatforms > 0 ? 
            round(($healthyPlatforms / $totalPlatforms) * 100, 1) : 100;
        
        return [
            'health_percentage' => $healthPercentage,
            'healthy_platforms' => $healthyPlatforms,
            'total_enabled_platforms' => $totalPlatforms,
            'status' => $healthPercentage >= 80 ? 'healthy' : 
                       ($healthPercentage >= 50 ? 'degraded' : 'unhealthy')
        ];
    }

    /**
     * Get current job status
     */
    private function getCurrentJobStatus(): array
    {
        $jobStatuses = CrawlerJobStatus::whereIn('platform', array_keys($this->crawlers))
            ->where('job_type', 'keyword_search')
            ->get();
        
        return [
            'running_jobs' => $jobStatuses->where('status', 'running')->count(),
            'pending_jobs' => $jobStatuses->where('status', 'pending')->count(),
            'failed_jobs' => $jobStatuses->where('status', 'failed')->count(),
            'last_successful_run' => $jobStatuses->where('status', 'completed')
                ->max('last_run_at')
        ];
    }

    /**
     * Build standardized response
     */
    private function buildResponse(string $status, string $message, array $data): array
    {
        return [
            'status' => $status,
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->toISOString()
        ];
    }
}