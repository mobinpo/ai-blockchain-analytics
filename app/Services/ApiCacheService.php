<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApiCache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

final class ApiCacheService
{
    /**
     * Default TTL configurations for different API sources (in seconds).
     */
    private const DEFAULT_TTL = [
        'coingecko' => [
            'price' => 300,        // 5 minutes for price data
            'market_data' => 1800, // 30 minutes for market data
            'coin_info' => 86400,  // 24 hours for coin information
        ],
        'etherscan' => [
            'contract' => 3600,    // 1 hour for contract data
            'transaction' => 86400, // 24 hours for transaction data
            'balance' => 300,      // 5 minutes for balance data
        ],
        'moralis' => [
            'nft' => 1800,         // 30 minutes for NFT data
            'token' => 3600,       // 1 hour for token data
            'portfolio' => 900,    // 15 minutes for portfolio data
        ],
        'opensea' => [
            'collection' => 3600,  // 1 hour for collection data
            'asset' => 1800,       // 30 minutes for asset data
        ],
        'default' => 3600,         // 1 hour default
    ];

    /**
     * API call cost mapping for rate limit calculation.
     */
    private const API_COSTS = [
        'coingecko' => [
            'simple/price' => 1,
            'coins/markets' => 2,
            'coins/{id}' => 2,
        ],
        'etherscan' => [
            'api' => 1,  // Most Etherscan calls are 1 point
        ],
        'moralis' => [
            'default' => 1,
        ],
        'default' => 1,
    ];

    /**
     * Cache or retrieve API response data.
     */
    public function cacheOrRetrieve(
        string $apiSource,
        string $endpoint,
        string $resourceType,
        callable $apiCall,
        array $params = [],
        ?string $resourceId = null,
        ?int $customTtl = null,
        array $metadata = []
    ): mixed {
        // Try to retrieve from cache first
        $cached = ApiCache::retrieve($apiSource, $endpoint, $params, $resourceId);
        
        if ($cached) {
            Log::info("Cache HIT for {$apiSource}:{$endpoint}", [
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'hit_count' => $cached->hit_count,
            ]);
            
            return $cached->response_data;
        }

        // Cache miss - make API call
        Log::info("Cache MISS for {$apiSource}:{$endpoint}", [
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
        ]);

        try {
            $responseData = $apiCall();
            
            // Store in cache
            $ttl = $customTtl ?? $this->getTtl($apiSource, $resourceType);
            $cost = $this->getApiCost($apiSource, $endpoint);
            
            ApiCache::store(
                $apiSource,
                $endpoint,
                $resourceType,
                $responseData,
                $params,
                $resourceId,
                $ttl,
                array_merge($metadata, [
                    'cache_stored_at' => now()->toISOString(),
                    'api_response_time' => microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)),
                ]),
                $cost
            );

            return $responseData;
            
        } catch (\Exception $e) {
            Log::error("API call failed for {$apiSource}:{$endpoint}", [
                'error' => $e->getMessage(),
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
            ]);
            
            // Try to return stale cache data if available
            $staleCache = ApiCache::where('cache_key', 
                ApiCache::generateKey($apiSource, $endpoint, $params, $resourceId)
            )->first();
            
            if ($staleCache) {
                Log::warning("Returning stale cache data due to API failure", [
                    'api_source' => $apiSource,
                    'endpoint' => $endpoint,
                    'age_hours' => now()->diffInHours($staleCache->created_at),
                ]);
                
                return $staleCache->response_data;
            }
            
            throw $e;
        }
    }

    /**
     * Batch cache multiple API responses.
     */
    public function cacheBatch(array $cacheData): array
    {
        $results = [];
        
        DB::transaction(function () use ($cacheData, &$results) {
            foreach ($cacheData as $item) {
                $cached = ApiCache::store(
                    $item['api_source'],
                    $item['endpoint'],
                    $item['resource_type'],
                    $item['response_data'],
                    $item['params'] ?? [],
                    $item['resource_id'] ?? null,
                    $item['ttl'] ?? $this->getTtl($item['api_source'], $item['resource_type']),
                    $item['metadata'] ?? [],
                    $item['api_call_cost'] ?? $this->getApiCost($item['api_source'], $item['endpoint'])
                );
                
                $results[] = $cached;
            }
        });
        
        Log::info("Batch cached " . count($cacheData) . " items");
        
        return $results;
    }

    /**
     * Invalidate cache by various criteria.
     */
    public function invalidate(array $criteria): int
    {
        $invalidated = ApiCache::invalidateBy($criteria);
        
        Log::info("Cache invalidated", [
            'criteria' => $criteria,
            'count' => $invalidated,
        ]);
        
        return $invalidated;
    }

    /**
     * Warm cache with fresh data.
     */
    public function warmCache(
        string $apiSource,
        string $endpoint,
        string $resourceType,
        callable $apiCall,
        array $params = [],
        ?string $resourceId = null,
        ?int $customTtl = null
    ): ApiCache {
        // Force refresh by invalidating existing cache
        if ($resourceId) {
            $this->invalidate(['api_source' => $apiSource, 'resource_id' => $resourceId]);
        } else {
            $this->invalidate(['api_source' => $apiSource, 'endpoint' => $endpoint]);
        }
        
        // Make fresh API call and cache
        $responseData = $apiCall();
        $ttl = $customTtl ?? $this->getTtl($apiSource, $resourceType);
        $cost = $this->getApiCost($apiSource, $endpoint);
        
        return ApiCache::store(
            $apiSource,
            $endpoint,
            $resourceType,
            $responseData,
            $params,
            $resourceId,
            $ttl,
            ['cache_warmed_at' => now()->toISOString()],
            $cost
        );
    }

    /**
     * Clean up expired cache entries and optimize storage.
     */
    public function cleanup(bool $aggressive = false): array
    {
        $stats = ['deleted' => 0, 'size_freed_mb' => 0];
        
        if ($aggressive) {
            // Aggressive cleanup: remove low-efficiency entries
            $lowEfficiencyEntries = ApiCache::where('cache_efficiency', '<', 1)
                ->where('hit_count', '<', 2)
                ->where('created_at', '<', now()->subDays(7))
                ->get();
                
            $stats['size_freed_mb'] += round($lowEfficiencyEntries->sum('response_size') / 1024 / 1024, 2);
            $stats['deleted'] += $lowEfficiencyEntries->count();
            
            ApiCache::whereIn('id', $lowEfficiencyEntries->pluck('id'))->delete();
        }
        
        // Regular cleanup: remove expired entries
        $expiredEntries = ApiCache::expired()->get();
        $stats['size_freed_mb'] += round($expiredEntries->sum('response_size') / 1024 / 1024, 2);
        $stats['deleted'] += ApiCache::cleanup();
        
        Log::info("Cache cleanup completed", $stats);
        
        return $stats;
    }

    /**
     * Get comprehensive cache statistics.
     */
    public function getStatistics(): array
    {
        return ApiCache::getStats();
    }

    /**
     * Get statistics for a specific API source.
     */
    public function getStatisticsForApiSource(string $apiSource): array
    {
        return ApiCache::getStatsForApiSource($apiSource);
    }

    /**
     * Check if cache is healthy (good hit ratio, reasonable size).
     */
    public function healthCheck(): array
    {
        $stats = $this->getStatistics();
        
        $health = [
            'status' => 'healthy',
            'issues' => [],
            'recommendations' => [],
        ];
        
        // Check hit ratio
        if ($stats['cache_hit_ratio'] < 30) {
            $health['issues'][] = 'Low cache hit ratio (' . $stats['cache_hit_ratio'] . '%)';
            $health['recommendations'][] = 'Consider increasing TTL for frequently accessed data';
        }
        
        // Check cache size
        if ($stats['cache_size_mb'] > 1000) { // 1GB
            $health['issues'][] = 'Large cache size (' . $stats['cache_size_mb'] . ' MB)';
            $health['recommendations'][] = 'Run aggressive cleanup or reduce TTL';
        }
        
        // Check expired entries ratio
        $expiredRatio = $stats['total_entries'] > 0 ? 
            ($stats['expired_entries'] / $stats['total_entries']) * 100 : 0;
            
        if ($expiredRatio > 25) {
            $health['issues'][] = 'High expired entries ratio (' . round($expiredRatio, 1) . '%)';
            $health['recommendations'][] = 'Schedule more frequent cleanup';
        }
        
        if (!empty($health['issues'])) {
            $health['status'] = 'needs_attention';
        }
        
        return $health;
    }

    /**
     * Get optimal TTL for given API source and resource type.
     */
    private function getTtl(string $apiSource, string $resourceType): int
    {
        return self::DEFAULT_TTL[$apiSource][$resourceType] ?? 
               self::DEFAULT_TTL[$apiSource]['default'] ?? 
               self::DEFAULT_TTL['default'];
    }

    /**
     * Get API call cost for rate limiting.
     */
    private function getApiCost(string $apiSource, string $endpoint): int
    {
        // Normalize endpoint for lookup
        $normalizedEndpoint = $this->normalizeEndpoint($endpoint);
        
        return self::API_COSTS[$apiSource][$normalizedEndpoint] ?? 
               self::API_COSTS[$apiSource]['default'] ?? 
               self::API_COSTS['default'];
    }

    /**
     * Normalize endpoint for cost lookup.
     */
    private function normalizeEndpoint(string $endpoint): string
    {
        // Remove query parameters and normalize
        $endpoint = parse_url($endpoint, PHP_URL_PATH) ?: $endpoint;
        
        // Replace dynamic segments with placeholders
        $endpoint = preg_replace('/\/[a-f0-9]{40,}/i', '/{address}', $endpoint);
        $endpoint = preg_replace('/\/0x[a-f0-9]+/i', '/{address}', $endpoint);
        $endpoint = preg_replace('/\/\d+/', '/{id}', $endpoint);
        
        return ltrim($endpoint, '/');
    }

    /**
     * Preload frequently accessed cache entries.
     */
    public function preloadFrequentlyAccessed(int $limit = 100): int
    {
        $frequently_accessed = ApiCache::where('hit_count', '>', 5)
            ->where('status', 'active')
            ->where('expires_at', '<', now()->addHours(2))
            ->orderBy('hit_count', 'desc')
            ->limit($limit)
            ->get();

        $preloaded = 0;
        
        foreach ($frequently_accessed as $cache) {
            try {
                // Extend TTL for frequently accessed items
                $newTtl = $this->getTtl($cache->api_source, $cache->resource_type);
                $cache->update([
                    'expires_at' => now()->addSeconds($newTtl),
                    'metadata' => array_merge($cache->metadata ?? [], [
                        'preloaded_at' => now()->toISOString(),
                    ]),
                ]);
                $preloaded++;
            } catch (\Exception $e) {
                Log::warning("Failed to preload cache entry", [
                    'cache_id' => $cache->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info("Preloaded {$preloaded} frequently accessed cache entries");
        
        return $preloaded;
    }
}
