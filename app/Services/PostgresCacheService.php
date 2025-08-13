<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApiCache;
use App\Models\DemoCacheData;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

final class PostgresCacheService
{
    /**
     * Cache TTL configurations for different services (in seconds).
     */
    private const TTL_CONFIG = [
        'coingecko' => [
            'price_current' => 300,        // 5 minutes
            'price_history' => 3600,       // 1 hour
            'daily_price_history' => 3600, // 1 hour
            'coins_list' => 86400,         // 24 hours
            'coin_search' => 3600,         // 1 hour
        ],
        'sentiment' => [
            'daily_aggregate' => 1800,     // 30 minutes
            'live_feed' => 120,            // 2 minutes
            'summary' => 900,              // 15 minutes
        ],
        'blockchain' => [
            'transaction' => 3600,         // 1 hour (transactions don't change)
            'address_info' => 1800,        // 30 minutes
            'block_info' => 7200,          // 2 hours
        ],
        'crawler' => [
            // Search results cache longer (content doesn't change much)
            'twitter_search' => 1800,     // 30 minutes
            'reddit_search' => 3600,      // 1 hour
            'telegram_search' => 1800,    // 30 minutes
            
            // User timelines cache shorter (more dynamic)
            'twitter_timeline' => 900,    // 15 minutes
            'reddit_timeline' => 1800,    // 30 minutes
            'telegram_timeline' => 900,   // 15 minutes
            
            // Profile/user info cache longer (rarely changes)
            'twitter_user' => 86400,      // 24 hours
            'reddit_user' => 86400,       // 24 hours
            'telegram_user' => 86400,     // 24 hours
            
            // Authentication tokens cache for session duration
            'twitter_auth' => 7200,       // 2 hours
            'reddit_auth' => 3600,        // 1 hour
            'telegram_auth' => 7200,      // 2 hours
            
            // Rate limit status cache short (dynamic)
            'rate_limit' => 300,          // 5 minutes
            
            // Keyword/hashtag trends cache medium (semi-static)
            'trends' => 3600,             // 1 hour
            
            // Post details cache long (content doesn't change)
            'post_details' => 86400,      // 24 hours
            
            // Channel/subreddit info cache long (meta doesn't change often)
            'channel_info' => 86400 * 7,  // 7 days
        ],
        'demo' => [
            'default' => 60,               // 1 minute for demo data
        ],
    ];

    /**
     * Get data from cache or execute callback and cache the result.
     */
    public function remember(
        string $service,
        string $endpoint,
        array $params,
        callable $callback,
        ?int $ttl = null,
        bool $isDemoData = false
    ): mixed {
        // Check if PostgreSQL cache is available
        if (!$this->isPgCacheAvailable()) {
            return $this->fallbackToLaravelCache($service, $endpoint, $params, $callback, $ttl);
        }

        // Try to get from cache first
        $cached = $this->get($service, $endpoint, $params);
        
        if ($cached !== null) {
            Log::debug("PostgreSQL cache hit for {$service}:{$endpoint}", ['params' => $params]);
            return $cached;
        }

        // Cache miss - execute callback
        Log::debug("PostgreSQL cache miss for {$service}:{$endpoint}", ['params' => $params]);
        
        try {
            $result = $callback();
            
            // Store in cache
            $this->put($service, $endpoint, $params, $result, $ttl, $isDemoData);
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error("Error executing callback for cache {$service}:{$endpoint}", [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            // Try to return stale data if available
            return $this->getStale($service, $endpoint, $params);
        }
    }

    /**
     * Get data from cache.
     */
    public function get(string $service, string $endpoint, array $params = []): mixed
    {
        try {
            $cache = ApiCache::retrieve($service, $endpoint, $params);
            return $cache ? $cache->decoded_response_data : null;
        } catch (\Exception $e) {
            Log::warning("PostgreSQL cache retrieval failed, falling back to Laravel cache", [
                'service' => $service,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            
            // Fallback to Laravel cache
            $laravelCacheKey = $this->generateLaravelCacheKey($service, $endpoint, $params);
            return Cache::get($laravelCacheKey);
        }
    }

    /**
     * Store data in cache.
     */
    public function put(
        string $service,
        string $endpoint,
        array $params,
        mixed $data,
        ?int $ttl = null,
        bool $isDemoData = false,
        array $metadata = []
    ): void {
        $ttl = $ttl ?? $this->getTtl($service, $endpoint);
        
        ApiCache::store(
            $service,
            $endpoint,
            'data', // resourceType - generic type for cached data
            $data,
            $params,
            null, // resourceId
            $ttl,
            array_merge($metadata, [
                'cached_at' => now()->toISOString(),
                'user_agent' => request()->header('User-Agent'),
            ])
            // Note: isDemoData parameter doesn't exist in ApiCache::store
        );

        Log::debug("Cached data for {$service}:{$endpoint}", [
            'params' => $params,
            'ttl' => $ttl,
            'is_demo' => $isDemoData,
        ]);
    }

    /**
     * Get stale (expired) data as fallback.
     */
    public function getStale(string $service, string $endpoint, array $params = []): mixed
    {
        $cacheKey = ApiCache::generateKey($service, $endpoint, $params);
        
        $cache = ApiCache::where('cache_key', $cacheKey)
            ->orderBy('expires_at', 'desc')
            ->first();

        if ($cache) {
            Log::warning("Using stale cache data for {$service}:{$endpoint}", [
                'expired_at' => $cache->expires_at,
                'params' => $params,
            ]);
            
            return $cache->decoded_response_data;
        }

        return null;
    }

    /**
     * Invalidate cache for specific service/endpoint.
     */
    public function forget(string $service, string $endpoint, array $params = []): bool
    {
        $cacheKey = ApiCache::generateKey($service, $endpoint, $params);
        
        $deleted = ApiCache::where('cache_key', $cacheKey)->delete();
        
        Log::debug("Invalidated cache for {$service}:{$endpoint}", [
            'params' => $params,
            'deleted' => $deleted,
        ]);

        return $deleted > 0;
    }

    /**
     * Clear all cache for a service.
     */
    public function clearService(string $service): int
    {
        $deleted = ApiCache::where('service', $service)->delete();
        
        Log::info("Cleared all cache for service: {$service}", ['deleted' => $deleted]);
        
        return $deleted;
    }

    /**
     * Warm cache with demo data for booth presentation.
     */
    public function warmDemoCache(): void
    {
        Log::info('Warming demo cache for North Star presentation');

        // Warm Coingecko data
        $this->warmCoingeckoCache();
        
        // Warm sentiment data
        $this->warmSentimentCache();
        
        // Warm blockchain data
        $this->warmBlockchainCache();
        
        // Initialize demo data
        DemoCacheData::initializeDemoData();

        Log::info('Demo cache warming completed');
    }

    /**
     * Get cache statistics.
     */
    public function getStats(): array
    {
        return [
            'api_cache' => ApiCache::getStats(),
            'demo_cache' => DemoCacheData::getStats(),
        ];
    }

    /**
     * Clean up expired cache entries.
     */
    public function cleanup(): array
    {
        $apiCleanup = ApiCache::cleanup();
        $demoRefresh = DemoCacheData::refreshStaleData();
        
        Log::info('Cache cleanup completed', [
            'api_entries_deleted' => $apiCleanup,
            'demo_entries_refreshed' => $demoRefresh,
        ]);

        return [
            'api_entries_deleted' => $apiCleanup,
            'demo_entries_refreshed' => $demoRefresh,
        ];
    }

    /**
     * Get TTL for specific service and endpoint.
     */
    private function getTtl(string $service, string $endpoint): int
    {
        $serviceConfig = self::TTL_CONFIG[$service] ?? [];
        
        return $serviceConfig[$endpoint] ?? $serviceConfig['default'] ?? 3600;
    }

    /**
     * Warm Coingecko API cache.
     */
    private function warmCoingeckoCache(): void
    {
        $popularCoins = ['bitcoin', 'ethereum', 'cardano', 'solana', 'polygon'];
        $demoData = [
            'current_prices' => [
                'bitcoin' => ['usd' => 43250.00, 'usd_24h_change' => 2.34],
                'ethereum' => ['usd' => 2840.50, 'usd_24h_change' => 1.87],
                'cardano' => ['usd' => 0.485, 'usd_24h_change' => -0.52],
                'solana' => ['usd' => 98.75, 'usd_24h_change' => 3.21],
                'polygon' => ['usd' => 0.892, 'usd_24h_change' => 0.95],
            ],
        ];

        foreach ($popularCoins as $coin) {
            // Cache current price
            $this->put(
                'coingecko',
                'current_price',
                ['coin' => $coin],
                $demoData['current_prices'][$coin] ?? ['usd' => rand(100, 50000) / 100],
                null,
                true
            );

            // Cache price history
            $priceHistory = $this->generatePriceHistory($coin);
            $this->put(
                'coingecko',
                'price_history',
                ['coin' => $coin, 'days' => 30],
                $priceHistory,
                null,
                true
            );
        }

        // Cache coins list
        $coinsList = array_map(function ($coin) {
            return [
                'id' => $coin,
                'symbol' => strtoupper(substr($coin, 0, 3)),
                'name' => ucfirst($coin),
            ];
        }, $popularCoins);

        $this->put('coingecko', 'coins_list', [], $coinsList, null, true);
    }

    /**
     * Warm sentiment analysis cache.
     */
    private function warmSentimentCache(): void
    {
        $sentimentData = [
            'bitcoin' => [
                'average_sentiment' => 0.743,
                'total_posts' => 2847,
                'platforms' => ['twitter', 'reddit', 'telegram'],
                'correlation' => 0.73,
            ],
            'ethereum' => [
                'average_sentiment' => 0.682,
                'total_posts' => 1934,
                'platforms' => ['twitter', 'reddit'],
                'correlation' => 0.68,
            ],
        ];

        foreach ($sentimentData as $coin => $data) {
            $this->put(
                'sentiment',
                'daily_aggregate',
                ['coin' => $coin, 'date' => now()->format('Y-m-d')],
                $data,
                null,
                true
            );
        }

        // Cache live sentiment feed
        $liveFeed = [
            [
                'platform' => 'twitter',
                'content' => 'Bitcoin looking strong today! 🚀 Breaking resistance levels',
                'sentiment' => 0.85,
                'timestamp' => now()->subMinutes(2),
            ],
            [
                'platform' => 'reddit',
                'content' => 'Interesting analysis on BTC market dynamics. Bullish long term.',
                'sentiment' => 0.62,
                'timestamp' => now()->subMinutes(5),
            ],
        ];

        $this->put('sentiment', 'live_feed', ['limit' => 10], $liveFeed, null, true);
    }

    /**
     * Warm blockchain explorer cache.
     */
    private function warmBlockchainCache(): void
    {
        // Sample transaction
        $sampleTx = [
            'hash' => '0x1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef',
            'block_number' => 17500000,
            'from' => '0xabcdef1234567890abcdef1234567890abcdef12',
            'to' => '0x1234567890abcdef1234567890abcdef12345678',
            'value' => '2.5',
            'gas_used' => 21000,
            'status' => 'success',
            'risk_score' => 15,
            'legitimacy_score' => 92,
        ];

        $this->put(
            'blockchain',
            'transaction',
            ['hash' => $sampleTx['hash']],
            $sampleTx,
            null,
            true
        );

        // Sample address
        $sampleAddress = [
            'address' => '0xabcdef1234567890abcdef1234567890abcdef12',
            'balance' => '125.75',
            'tx_count' => 1847,
            'first_seen' => '45d ago',
            'risk_level' => 'Low',
            'recent_txs' => [
                ['hash' => '0x123...abc', 'type' => 'in', 'value' => '2.5', 'timeAgo' => '2h ago'],
                ['hash' => '0x456...def', 'type' => 'out', 'value' => '1.2', 'timeAgo' => '5h ago'],
            ],
        ];

        $this->put(
            'blockchain',
            'address',
            ['address' => $sampleAddress['address']],
            $sampleAddress,
            null,
            true
        );
    }

    /**
     * Generate synthetic price history for demo.
     */
    private function generatePriceHistory(string $coin): array
    {
        $basePrice = match ($coin) {
            'bitcoin' => 43000,
            'ethereum' => 2800,
            'cardano' => 0.48,
            'solana' => 95,
            'polygon' => 0.89,
            default => 100,
        };

        $history = [];
        $price = $basePrice;
        
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $change = (mt_rand(-500, 500) / 10000); // ±5% max daily change
            $price = $price * (1 + $change);
            
            $history[] = [
                'date' => $date->format('Y-m-d'),
                'price' => round($price, 2),
                'price_change' => round($change * 100, 2),
                'timestamp' => $date->timestamp,
            ];
        }

        return $history;
    }

    /**
     * Cache social media search results with intelligent TTL
     */
    public function cacheCrawlerSearch(
        string $platform,
        string $query,
        array $filters,
        array $results,
        ?int $customTtl = null
    ): void {
        $endpoint = "{$platform}_search";
        $params = [
            'query' => $query,
            'filters' => $filters,
            'search_type' => 'keyword',
        ];

        $metadata = [
            'platform' => $platform,
            'query_hash' => md5($query),
            'result_count' => count($results),
            'search_timestamp' => now()->toISOString(),
        ];

        $this->put('crawler', $endpoint, $params, $results, $customTtl, false, $metadata);
    }

    /**
     * Cache user timeline/profile data
     */
    public function cacheCrawlerTimeline(
        string $platform,
        string $userId,
        array $posts,
        ?int $customTtl = null
    ): void {
        $endpoint = "{$platform}_timeline";
        $params = [
            'user_id' => $userId,
            'timeline_type' => 'user',
        ];

        $metadata = [
            'platform' => $platform,
            'user_id' => $userId,
            'post_count' => count($posts),
            'last_post_timestamp' => !empty($posts) ? $posts[0]['created_at'] ?? null : null,
        ];

        $this->put('crawler', $endpoint, $params, $posts, $customTtl, false, $metadata);
    }

    /**
     * Cache authentication tokens with platform-specific TTL
     */
    public function cacheCrawlerAuth(
        string $platform,
        array $authData,
        ?int $customTtl = null
    ): void {
        $endpoint = "{$platform}_auth";
        $params = ['auth_type' => 'token'];

        $metadata = [
            'platform' => $platform,
            'token_type' => $authData['token_type'] ?? 'bearer',
            'expires_in' => $authData['expires_in'] ?? null,
        ];

        // Remove sensitive data from cached content but keep metadata
        $safeAuthData = array_intersect_key($authData, array_flip([
            'token_type', 'expires_in', 'scope', 'user_id'
        ]));

        $this->put('crawler', $endpoint, $params, $safeAuthData, $customTtl, false, $metadata);
    }

    /**
     * Cache rate limit status
     */
    public function cacheCrawlerRateLimit(
        string $platform,
        array $rateLimitData
    ): void {
        $endpoint = 'rate_limit';
        $params = ['platform' => $platform];

        $metadata = [
            'platform' => $platform,
            'remaining_requests' => $rateLimitData['remaining'] ?? null,
            'reset_timestamp' => $rateLimitData['reset'] ?? null,
        ];

        $this->put('crawler', $endpoint, $params, $rateLimitData, null, false, $metadata);
    }

    /**
     * Get cached search results if available
     */
    public function getCachedCrawlerSearch(
        string $platform,
        string $query,
        array $filters = []
    ): ?array {
        $endpoint = "{$platform}_search";
        $params = [
            'query' => $query,
            'filters' => $filters,
            'search_type' => 'keyword',
        ];

        return $this->get('crawler', $endpoint, $params);
    }

    /**
     * Get cached timeline if available
     */
    public function getCachedCrawlerTimeline(
        string $platform,
        string $userId
    ): ?array {
        $endpoint = "{$platform}_timeline";
        $params = [
            'user_id' => $userId,
            'timeline_type' => 'user',
        ];

        return $this->get('crawler', $endpoint, $params);
    }

    /**
     * Get cached authentication data
     */
    public function getCachedCrawlerAuth(string $platform): ?array
    {
        $endpoint = "{$platform}_auth";
        $params = ['auth_type' => 'token'];

        return $this->get('crawler', $endpoint, $params);
    }

    /**
     * Get cached rate limit status
     */
    public function getCachedCrawlerRateLimit(string $platform): ?array
    {
        $endpoint = 'rate_limit';
        $params = ['platform' => $platform];

        return $this->get('crawler', $endpoint, $params);
    }

    /**
     * Bulk cache multiple crawler results efficiently
     */
    public function bulkCacheCrawlerResults(array $cacheEntries): void
    {
        foreach ($cacheEntries as $entry) {
            $this->put(
                'crawler',
                $entry['endpoint'],
                $entry['params'],
                $entry['data'],
                $entry['ttl'] ?? null,
                $entry['is_demo'] ?? false,
                $entry['metadata'] ?? []
            );
        }

        Log::info('Bulk cached crawler results', ['entry_count' => count($cacheEntries)]);
    }

    /**
     * Check if PostgreSQL cache is available.
     */
    private function isPgCacheAvailable(): bool
    {
        try {
            // Try a simple database query to check connectivity
            ApiCache::query()->limit(1)->count();
            return true;
        } catch (\Exception $e) {
            Log::warning('PostgreSQL cache unavailable, will use Laravel cache fallback', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Fallback to Laravel cache when PostgreSQL is unavailable.
     */
    private function fallbackToLaravelCache(
        string $service,
        string $endpoint,
        array $params,
        callable $callback,
        ?int $ttl = null
    ): mixed {
        $cacheKey = $this->generateLaravelCacheKey($service, $endpoint, $params);
        $ttl = $ttl ?? $this->getTtl($service, $endpoint);
        
        Log::debug("Using Laravel cache fallback for {$service}:{$endpoint}");
        
        return Cache::remember($cacheKey, now()->addSeconds($ttl), $callback);
    }

    /**
     * Generate Laravel cache key.
     */
    private function generateLaravelCacheKey(string $service, string $endpoint, array $params): string
    {
        $paramString = empty($params) ? '' : md5(json_encode($params));
        return "pg_fallback:{$service}:{$endpoint}:{$paramString}";
    }
}