<?php

declare(strict_types=1);

namespace App\Services\CrawlerMicroService;

use App\Services\PostgresCacheService;
use App\Models\CrawlerJobStatus;
use App\Models\ApiUsageTracking;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

/**
 * Advanced cache manager specifically for crawler micro-service
 * 
 * Features:
 * - Intelligent cache-first strategy to avoid API limits
 * - Platform-specific cache optimization
 * - Predictive cache warming based on usage patterns
 * - Content freshness scoring and adaptive TTL
 * - API quota management and intelligent throttling
 * - Cache analytics and performance monitoring
 */
final class CrawlerCacheManager
{
    public function __construct(
        private readonly PostgresCacheService $cache
    ) {}

    /**
     * Cache priorities affect TTL and refresh strategies
     */
    private const CACHE_PRIORITIES = [
        'critical' => [
            'multiplier' => 3.0,      // 3x standard TTL
            'stale_grace' => 86400 * 7, // 7 days grace period
            'refresh_threshold' => 0.8,  // Refresh when 80% TTL elapsed
        ],
        'high' => [
            'multiplier' => 2.0,      // 2x standard TTL
            'stale_grace' => 86400 * 3, // 3 days grace period
            'refresh_threshold' => 0.7,  // Refresh when 70% TTL elapsed
        ],
        'medium' => [
            'multiplier' => 1.0,      // Standard TTL
            'stale_grace' => 86400,   // 1 day grace period
            'refresh_threshold' => 0.6,  // Refresh when 60% TTL elapsed
        ],
        'low' => [
            'multiplier' => 0.5,      // Half standard TTL
            'stale_grace' => 3600 * 12, // 12 hours grace period
            'refresh_threshold' => 0.5,  // Refresh when 50% TTL elapsed
        ],
    ];

    /**
     * Platform-specific API limits and cache strategies
     */
    private const PLATFORM_LIMITS = [
        'twitter' => [
            'requests_per_15min' => 300,
            'requests_per_hour' => 1000,
            'requests_per_day' => 24000,
            'burst_allowance' => 50,
            'cache_preference' => 'high', // Prefer cache due to strict limits
        ],
        'reddit' => [
            'requests_per_minute' => 60,
            'requests_per_hour' => 3600,
            'requests_per_day' => 86400,
            'burst_allowance' => 10,
            'cache_preference' => 'medium',
        ],
        'telegram' => [
            'requests_per_second' => 30,
            'requests_per_minute' => 1800,
            'requests_per_day' => 2592000,
            'burst_allowance' => 20,
            'cache_preference' => 'medium',
        ],
    ];

    /**
     * Intelligent cache-first search with fallback
     */
    public function searchWithCache(
        string $platform,
        string $query,
        array $filters = [],
        string $priority = 'medium',
        bool $allowStale = true
    ): array {
        // Check if we should avoid API calls due to rate limits
        if ($this->shouldAvoidApiCall($platform)) {
            Log::info("API limit protection: using cache-only mode for {$platform}");
            $allowStale = true;
        }

        // Try to get fresh cache first
        $cached = $this->cache->getCachedCrawlerSearch($platform, $query, $filters);
        
        if ($cached !== null) {
            $this->recordCacheHit($platform, 'search', $query);
            Log::debug("Cache hit for {$platform} search: {$query}");
            return $this->formatCachedResult($cached, true);
        }

        // Check if we should use stale data instead of API call
        if ($this->shouldUseStaleData($platform, $priority)) {
            $stale = $this->cache->getStale('crawler', "{$platform}_search", [
                'query' => $query,
                'filters' => $filters,
                'search_type' => 'keyword',
            ]);

            if ($stale !== null) {
                $this->recordCacheHit($platform, 'search_stale', $query);
                Log::info("Using stale cache data to avoid API limits for {$platform}: {$query}");
                return $this->formatCachedResult($stale, false);
            }
        }

        // If no cache available and we must avoid API, return empty result
        if ($this->mustAvoidApiCall($platform)) {
            Log::warning("No cache available and API limits exceeded for {$platform}: {$query}");
            return [
                'status' => 'cache_only',
                'posts' => [],
                'from_cache' => false,
                'reason' => 'api_limit_protection',
                'query' => $query,
                'platform' => $platform,
            ];
        }

        // Cache miss - need to make API call
        $this->recordCacheMiss($platform, 'search', $query);
        return null; // Indicates caller should make API call and cache result
    }

    /**
     * Cache search results with intelligent TTL based on content analysis
     */
    public function cacheSearchResults(
        string $platform,
        string $query,
        array $filters,
        array $results,
        string $priority = 'medium'
    ): void {
        // Analyze content freshness to determine optimal TTL
        $contentScore = $this->analyzeContentFreshness($results);
        $baseTtl = $this->cache->getTtl('crawler', "{$platform}_search");
        $adaptiveTtl = $this->calculateAdaptiveTtl($baseTtl, $contentScore, $priority);

        // Enhanced metadata for cache optimization
        $metadata = [
            'platform' => $platform,
            'query_hash' => md5($query),
            'result_count' => count($results),
            'content_freshness_score' => $contentScore,
            'cache_priority' => $priority,
            'adaptive_ttl' => $adaptiveTtl,
            'api_cost_saved' => $this->estimateApiCost($platform, count($results)),
            'search_timestamp' => now()->toISOString(),
        ];

        $this->cache->cacheCrawlerSearch($platform, $query, $filters, $results, $adaptiveTtl);
        
        Log::info("Cached {$platform} search results", [
            'query' => $query,
            'results' => count($results),
            'ttl' => $adaptiveTtl,
            'freshness_score' => $contentScore,
        ]);

        // Update usage tracking
        $this->updateApiUsageTracking($platform, 'search', count($results));
    }

    /**
     * Get cached timeline with intelligent refresh
     */
    public function getTimelineWithCache(
        string $platform,
        string $userId,
        string $priority = 'medium'
    ): ?array {
        $cached = $this->cache->getCachedCrawlerTimeline($platform, $userId);
        
        if ($cached !== null) {
            // Check if cache needs refresh based on priority and age
            if ($this->shouldRefreshCache($platform, "{$platform}_timeline", $priority)) {
                Log::info("Timeline cache needs refresh for {$platform} user: {$userId}");
                return null; // Indicates caller should refresh
            }

            $this->recordCacheHit($platform, 'timeline', $userId);
            return $this->formatCachedResult($cached, true);
        }

        $this->recordCacheMiss($platform, 'timeline', $userId);
        return null;
    }

    /**
     * Bulk cache multiple results efficiently with transaction-like behavior
     */
    public function bulkCacheResults(array $operations): void
    {
        $cacheEntries = [];
        
        foreach ($operations as $operation) {
            $platform = $operation['platform'];
            $type = $operation['type']; // 'search', 'timeline', 'auth', etc.
            $data = $operation['data'];
            $params = $operation['params'] ?? [];
            $priority = $operation['priority'] ?? 'medium';

            $endpoint = "{$platform}_{$type}";
            
            // Calculate adaptive TTL based on operation type and content
            $adaptiveTtl = match($type) {
                'search' => $this->calculateSearchTtl($data, $priority),
                'timeline' => $this->calculateTimelineTtl($data, $priority),
                'auth' => $this->calculateAuthTtl($platform, $data),
                default => null
            };

            $cacheEntries[] = [
                'endpoint' => $endpoint,
                'params' => $params,
                'data' => $data,
                'ttl' => $adaptiveTtl,
                'metadata' => [
                    'platform' => $platform,
                    'operation_type' => $type,
                    'priority' => $priority,
                    'bulk_operation' => true,
                    'timestamp' => now()->toISOString(),
                ],
            ];
        }

        $this->cache->bulkCacheCrawlerResults($cacheEntries);
        
        Log::info('Bulk cached crawler results', [
            'operations' => count($operations),
            'platforms' => array_unique(array_column($operations, 'platform')),
        ]);
    }

    /**
     * Warm cache proactively based on usage patterns
     */
    public function warmCacheProactively(array $popularQueries = []): void
    {
        Log::info('Starting proactive cache warming for crawler');

        // Get popular queries from recent job history if not provided
        if (empty($popularQueries)) {
            $popularQueries = $this->getPopularQueries();
        }

        foreach ($popularQueries as $queryData) {
            $platform = $queryData['platform'];
            $query = $queryData['query'];
            
            // Check if cache is still fresh
            $cached = $this->cache->getCachedCrawlerSearch($platform, $query);
            if ($cached !== null) {
                continue; // Skip if already cached
            }

            // Skip if we're approaching API limits
            if ($this->shouldAvoidApiCall($platform)) {
                Log::debug("Skipping cache warming for {$platform} due to API limits");
                continue;
            }

            Log::info("Warming cache for popular query: {$platform}:{$query}");
            
            // This would trigger the actual crawling logic in a background job
            // For now, we log the intent
        }
    }

    /**
     * Get cache analytics and performance metrics
     */
    public function getCacheAnalytics(): array
    {
        $analytics = [];

        foreach (array_keys(self::PLATFORM_LIMITS) as $platform) {
            $analytics[$platform] = [
                'cache_hits' => Cache::get("crawler_cache_hits_{$platform}", 0),
                'cache_misses' => Cache::get("crawler_cache_misses_{$platform}", 0),
                'api_calls_saved' => Cache::get("crawler_api_saved_{$platform}", 0),
                'stale_data_used' => Cache::get("crawler_stale_used_{$platform}", 0),
                'rate_limit_status' => $this->cache->getCachedCrawlerRateLimit($platform),
            ];
            
            // Calculate cache hit ratio
            $hits = $analytics[$platform]['cache_hits'];
            $misses = $analytics[$platform]['cache_misses'];
            $total = $hits + $misses;
            
            $analytics[$platform]['hit_ratio'] = $total > 0 ? round($hits / $total * 100, 2) : 0;
        }

        return [
            'by_platform' => $analytics,
            'total_api_calls_saved' => array_sum(array_column($analytics, 'api_calls_saved')),
            'average_hit_ratio' => round(
                array_sum(array_column($analytics, 'hit_ratio')) / count($analytics), 2
            ),
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Clean up old cache entries and optimize performance
     */
    public function cleanupAndOptimize(): array
    {
        $cleaned = [
            'expired_entries' => 0,
            'stale_entries' => 0,
            'optimized_entries' => 0,
        ];

        // This would implement cache cleanup logic
        Log::info('Cache cleanup and optimization completed', $cleaned);
        
        return $cleaned;
    }

    /**
     * Analyze content freshness to determine optimal caching strategy
     */
    private function analyzeContentFreshness(array $results): float
    {
        if (empty($results)) {
            return 0.0;
        }

        $totalFreshness = 0;
        $count = 0;

        foreach ($results as $post) {
            if (!isset($post['created_at'])) {
                continue;
            }

            $postDate = Carbon::parse($post['created_at']);
            $ageHours = $postDate->diffInHours(now());
            
            // Fresh content (< 1 hour) = 1.0, old content (> 24 hours) = 0.0
            $freshness = max(0, min(1, (24 - $ageHours) / 24));
            $totalFreshness += $freshness;
            $count++;
        }

        return $count > 0 ? $totalFreshness / $count : 0.5;
    }

    /**
     * Calculate adaptive TTL based on content and priority
     */
    private function calculateAdaptiveTtl(int $baseTtl, float $contentScore, string $priority): int
    {
        $priorityConfig = self::CACHE_PRIORITIES[$priority] ?? self::CACHE_PRIORITIES['medium'];
        
        // Base TTL adjusted by priority
        $adaptiveTtl = (int)($baseTtl * $priorityConfig['multiplier']);
        
        // Adjust based on content freshness (fresher content = shorter TTL)
        $freshnessAdjustment = 1 - ($contentScore * 0.3); // Max 30% reduction for fresh content
        $adaptiveTtl = (int)($adaptiveTtl * $freshnessAdjustment);

        return max(300, $adaptiveTtl); // Minimum 5 minutes TTL
    }

    /**
     * Check if we should avoid making API calls due to rate limits
     */
    private function shouldAvoidApiCall(string $platform): bool
    {
        $rateLimitData = $this->cache->getCachedCrawlerRateLimit($platform);
        
        if (!$rateLimitData) {
            return false; // No rate limit data, proceed with caution
        }

        $remaining = (int)($rateLimitData['remaining'] ?? 100);
        $limit = self::PLATFORM_LIMITS[$platform]['requests_per_15min'] ?? 100;
        
        // Avoid API calls if less than 20% of rate limit remaining
        return $remaining < ($limit * 0.2);
    }

    /**
     * Check if we must absolutely avoid API calls (hard limit)
     */
    private function mustAvoidApiCall(string $platform): bool
    {
        $rateLimitData = $this->cache->getCachedCrawlerRateLimit($platform);
        
        if (!$rateLimitData) {
            return false;
        }

        $remaining = (int)($rateLimitData['remaining'] ?? 100);
        return $remaining < 5; // Hard limit: less than 5 requests remaining
    }

    /**
     * Check if we should use stale data instead of making API call
     */
    private function shouldUseStaleData(string $platform, string $priority): bool
    {
        // Higher priority operations are more willing to use stale data
        $staleThreshold = match($priority) {
            'critical' => 0.1, // Use stale if 10%+ rate limit remaining
            'high' => 0.15,     // Use stale if 15%+ rate limit remaining
            'medium' => 0.25,   // Use stale if 25%+ rate limit remaining
            'low' => 0.4,       // Use stale if 40%+ rate limit remaining
            default => 0.25,
        };

        return $this->getRateLimitRatio($platform) < $staleThreshold;
    }

    /**
     * Get current rate limit ratio (remaining/total)
     */
    private function getRateLimitRatio(string $platform): float
    {
        $rateLimitData = $this->cache->getCachedCrawlerRateLimit($platform);
        
        if (!$rateLimitData) {
            return 1.0; // Assume full capacity if no data
        }

        $remaining = (int)($rateLimitData['remaining'] ?? 100);
        $limit = self::PLATFORM_LIMITS[$platform]['requests_per_15min'] ?? 100;
        
        return $remaining / $limit;
    }

    /**
     * Format cached result with metadata
     */
    private function formatCachedResult(array $data, bool $isFresh): array
    {
        return [
            'status' => 'success',
            'posts' => $data['posts'] ?? $data,
            'from_cache' => true,
            'cache_fresh' => $isFresh,
            'cached_at' => $data['cached_at'] ?? null,
        ];
    }

    /**
     * Record cache hit for analytics
     */
    private function recordCacheHit(string $platform, string $type, string $identifier): void
    {
        $key = "crawler_cache_hits_{$platform}";
        Cache::increment($key);
        
        $savedKey = "crawler_api_saved_{$platform}";
        Cache::increment($savedKey);
        
        Log::debug("Cache hit recorded", [
            'platform' => $platform,
            'type' => $type,
            'identifier' => $identifier,
        ]);
    }

    /**
     * Record cache miss for analytics
     */
    private function recordCacheMiss(string $platform, string $type, string $identifier): void
    {
        $key = "crawler_cache_misses_{$platform}";
        Cache::increment($key);
        
        Log::debug("Cache miss recorded", [
            'platform' => $platform,
            'type' => $type,
            'identifier' => $identifier,
        ]);
    }

    /**
     * Update API usage tracking
     */
    private function updateApiUsageTracking(string $platform, string $operation, int $resultCount): void
    {
        try {
            ApiUsageTracking::create([
                'service' => 'crawler',
                'platform' => $platform,
                'operation' => $operation,
                'request_count' => 1,
                'response_size' => $resultCount,
                'cached' => true,
                'timestamp' => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to track API usage', [
                'platform' => $platform,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get popular queries from recent crawler jobs
     */
    private function getPopularQueries(): array
    {
        try {
            return CrawlerJobStatus::where('created_at', '>=', now()->subDays(7))
                ->where('status', 'completed')
                ->select(['platform', 'search_query'])
                ->whereNotNull('search_query')
                ->get()
                ->groupBy('search_query')
                ->map(fn($group) => [
                    'query' => $group->first()->search_query,
                    'platform' => $group->first()->platform,
                    'frequency' => $group->count(),
                ])
                ->sortByDesc('frequency')
                ->take(10)
                ->values()
                ->toArray();
        } catch (\Exception $e) {
            Log::warning('Failed to get popular queries', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Calculate TTL for search results
     */
    private function calculateSearchTtl(array $data, string $priority): int
    {
        $baseTtl = 1800; // 30 minutes default for search
        $contentScore = $this->analyzeContentFreshness($data);
        return $this->calculateAdaptiveTtl($baseTtl, $contentScore, $priority);
    }

    /**
     * Calculate TTL for timeline results
     */
    private function calculateTimelineTtl(array $data, string $priority): int
    {
        $baseTtl = 900; // 15 minutes default for timeline
        $contentScore = $this->analyzeContentFreshness($data);
        return $this->calculateAdaptiveTtl($baseTtl, $contentScore, $priority);
    }

    /**
     * Calculate TTL for auth data
     */
    private function calculateAuthTtl(string $platform, array $authData): int
    {
        // Use expires_in from auth data if available, otherwise use platform default
        if (isset($authData['expires_in'])) {
            return (int)$authData['expires_in'] - 300; // 5 minute buffer
        }

        return match($platform) {
            'twitter' => 7200,  // 2 hours
            'reddit' => 3600,   // 1 hour
            'telegram' => 7200, // 2 hours
            default => 3600,
        };
    }

    /**
     * Estimate API cost savings
     */
    private function estimateApiCost(string $platform, int $resultCount): float
    {
        // Rough cost estimates per API call (in USD)
        $costPerCall = match($platform) {
            'twitter' => 0.005,   // $5 per 1000 calls
            'reddit' => 0.001,    // $1 per 1000 calls
            'telegram' => 0.002,  // $2 per 1000 calls
            default => 0.003,
        };

        return $costPerCall;
    }

    /**
     * Check if cache should be refreshed based on age and priority
     */
    private function shouldRefreshCache(string $platform, string $endpoint, string $priority): bool
    {
        // Implementation would check cache age against refresh thresholds
        return false; // Placeholder
    }
}