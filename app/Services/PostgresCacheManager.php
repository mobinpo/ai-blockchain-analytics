<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ContractCache;
use App\Models\ApiUsageTracking;
use App\Models\CacheWarmingQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

/**
 * Advanced PostgreSQL Cache Manager to aggressively avoid API limits
 * 
 * Features:
 * - Intelligent cache-first strategy with stale fallback
 * - Predictive cache warming based on usage patterns
 * - API quota management and throttling
 * - Multi-layered caching with different TTLs
 * - Cache analytics and optimization
 */
final class PostgresCacheManager
{
    // Cache priority levels affect TTL and refresh frequency
    private const CACHE_PRIORITIES = [
        'critical' => 86400 * 7,    // 7 days
        'high' => 86400 * 3,        // 3 days  
        'medium' => 86400,          // 1 day
        'low' => 3600 * 12          // 12 hours
    ];

    // Stale data extension periods
    private const STALE_EXTENSIONS = [
        'critical' => 86400 * 30,   // 30 days
        'high' => 86400 * 14,       // 14 days
        'medium' => 86400 * 7,      // 7 days  
        'low' => 86400 * 3          // 3 days
    ];

    private const API_DAILY_LIMITS = [
        'ethereum' => 100000,   // Etherscan API limit
        'bsc' => 10000,         // BSCScan API limit
        'polygon' => 5000       // PolygonScan API limit
    ];

    /**
     * Get cached data with aggressive cache-first strategy
     */
    public function getCachedDataWithFallback(
        string $network,
        string $contractAddress,
        string $cacheType,
        bool $allowStale = true
    ): ?array {
        $contractAddress = strtolower($contractAddress);
        
        // 1. Try fresh cache first
        $fresh = $this->getFreshCachedData($network, $contractAddress, $cacheType);
        if ($fresh) {
            Log::debug('Cache HIT (fresh)', [
                'network' => $network,
                'address' => $contractAddress,
                'type' => $cacheType,
                'expires_at' => $fresh['cache_expires_at'] ?? 'N/A'
            ]);
            return $fresh;
        }

        // 2. Try stale cache if allowed
        if ($allowStale) {
            $stale = $this->getStaleCachedData($network, $contractAddress, $cacheType);
            if ($stale) {
                Log::info('Cache HIT (stale)', [
                    'network' => $network,
                    'address' => $contractAddress,
                    'type' => $cacheType,
                    'expired_at' => $stale['cache_expires_at'] ?? 'N/A'
                ]);
                
                // Queue for background refresh if not already queued
                $this->queueForBackgroundRefresh($network, $contractAddress, $cacheType);
                
                return array_merge($stale, [
                    'is_stale' => true,
                    'stale_extension_count' => ($stale['stale_extension_count'] ?? 0) + 1
                ]);
            }
        }

        // 3. Check API quota before allowing cache miss
        if (!$this->canMakeApiCall($network)) {
            Log::warning('API quota exceeded, cannot fetch new data', [
                'network' => $network,
                'address' => $contractAddress
            ]);
            
            // Try even older stale data as last resort
            $veryStale = $this->getVeryStaleData($network, $contractAddress, $cacheType);
            if ($veryStale) {
                return array_merge($veryStale, [
                    'is_stale' => true,
                    'very_stale' => true,
                    'reason' => 'API quota exceeded'
                ]);
            }
            
            return null;
        }

        Log::debug('Cache MISS', [
            'network' => $network,
            'address' => $contractAddress,
            'type' => $cacheType
        ]);

        return null;
    }

    /**
     * Store data with intelligent TTL calculation
     */
    public function storeWithIntelligentTTL(
        string $network,
        string $contractAddress,
        string $cacheType,
        array $data,
        string $priority = 'medium',
        bool $fromApi = true
    ): bool {
        $contractAddress = strtolower($contractAddress);
        
        try {
            // Calculate optimal TTL based on data characteristics
            $ttl = $this->calculateOptimalTTL($data, $cacheType, $priority);
            
            // Enhance data with cache metadata
            $enhancedData = $this->enhanceDataForCache($data, $network, $contractAddress, $cacheType);
            
            // Store in database with enhanced metadata
            $cache = ContractCache::storeContractDataEnhanced(
                $network,
                $contractAddress,
                $cacheType,
                $enhancedData,
                $ttl,
                $priority,
                $fromApi
            );

            // Record API usage if from API
            if ($fromApi) {
                $this->recordApiUsage($network, $cacheType);
            }

            // Update memory cache for fast access
            $memoryKey = $this->getMemoryCacheKey($network, $contractAddress, $cacheType);
            Cache::put($memoryKey, $enhancedData, min($ttl, 3600)); // Max 1 hour in memory

            Log::info('Data cached successfully', [
                'network' => $network,
                'address' => $contractAddress,
                'type' => $cacheType,
                'ttl_seconds' => $ttl,
                'priority' => $priority,
                'from_api' => $fromApi
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to store cache data', [
                'network' => $network,
                'address' => $contractAddress,
                'type' => $cacheType,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Warm cache proactively for high-value contracts
     */
    public function warmCacheProactively(array $contractAddresses, string $network): array
    {
        $results = ['queued' => 0, 'already_cached' => 0, 'failed' => 0];
        
        foreach ($contractAddresses as $address) {
            try {
                $address = strtolower($address);
                
                // Check if already fresh in cache
                if ($this->hasFreshCache($network, $address, 'source')) {
                    $results['already_cached']++;
                    continue;
                }
                
                // Queue for warming with high priority
                CacheWarmingQueue::queueContract(
                    $network,
                    $address,
                    'source',
                    'high'
                );
                
                $results['queued']++;
                
            } catch (\Exception $e) {
                $results['failed']++;
                Log::warning('Failed to queue contract for warming', [
                    'address' => $address,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        Log::info('Cache warming batch completed', [
            'network' => $network,
            'total_contracts' => count($contractAddresses),
            'results' => $results
        ]);
        
        return $results;
    }

    /**
     * Get comprehensive cache analytics
     */
    public function getCacheAnalytics(int $days = 7): array
    {
        $startDate = now()->subDays($days);
        
        // Cache hit/miss analytics
        $hitMissStats = $this->getCacheHitMissStats($startDate);
        
        // API usage analytics
        $apiStats = $this->getApiUsageStats($startDate);
        
        // Storage analytics
        $storageStats = $this->getStorageAnalytics();
        
        // Performance analytics
        $performanceStats = $this->getPerformanceAnalytics($startDate);
        
        return [
            'period_days' => $days,
            'start_date' => $startDate->toISOString(),
            'end_date' => now()->toISOString(),
            'cache_performance' => $hitMissStats,
            'api_usage' => $apiStats,
            'storage' => $storageStats,
            'performance' => $performanceStats,
            'recommendations' => $this->generateRecommendations($hitMissStats, $apiStats)
        ];
    }

    /**
     * Optimize cache based on usage patterns
     */
    public function optimizeCache(): array
    {
        $optimizations = [];
        
        // 1. Extend TTL for frequently accessed data
        $frequentlyAccessed = $this->getFrequentlyAccessedContracts();
        foreach ($frequentlyAccessed as $contract) {
            $this->extendCacheTTL($contract, 'extend_frequent');
            $optimizations[] = "Extended TTL for frequently accessed contract: {$contract['contract_address']}";
        }
        
        // 2. Clean up rarely accessed expired data
        $cleaned = $this->cleanupRarelyAccessedExpired();
        $optimizations[] = "Cleaned up {$cleaned} rarely accessed expired entries";
        
        // 3. Promote high-quality cache entries
        $promoted = $this->promoteHighQualityEntries();
        $optimizations[] = "Promoted {$promoted} high-quality cache entries";
        
        // 4. Queue predictive warming
        $warmed = $this->queuePredictiveWarming();
        $optimizations[] = "Queued {$warmed} contracts for predictive warming";
        
        Log::info('Cache optimization completed', [
            'optimizations_count' => count($optimizations),
            'optimizations' => $optimizations
        ]);
        
        return $optimizations;
    }

    /**
     * Get API quota status for all networks
     */
    public function getApiQuotaStatus(): array
    {
        $status = [];
        
        foreach (self::API_DAILY_LIMITS as $network => $dailyLimit) {
            $todayUsage = $this->getTodayApiUsage($network);
            $remaining = max(0, $dailyLimit - $todayUsage);
            $percentUsed = $dailyLimit > 0 ? ($todayUsage / $dailyLimit) * 100 : 0;
            
            $status[$network] = [
                'daily_limit' => $dailyLimit,
                'used_today' => $todayUsage,
                'remaining' => $remaining,
                'percent_used' => round($percentUsed, 2),
                'status' => $this->getQuotaStatus($percentUsed),
                'estimated_hours_remaining' => $this->estimateHoursRemaining($remaining)
            ];
        }
        
        return $status;
    }

    private function getFreshCachedData(string $network, string $contractAddress, string $cacheType): ?array
    {
        $cached = ContractCache::where('network', $network)
            ->where('contract_address', $contractAddress)
            ->where('cache_type', $cacheType)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->first();

        return $cached ? $cached->toServiceResponse() : null;
    }

    private function getStaleCachedData(string $network, string $contractAddress, string $cacheType): ?array
    {
        $cached = ContractCache::where('network', $network)
            ->where('contract_address', $contractAddress)  
            ->where('cache_type', $cacheType)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->where('expires_at', '>', now()->subDays(30)) // Within 30 days
            ->orderBy('expires_at', 'desc')
            ->first();

        return $cached ? $cached->toServiceResponse() : null;
    }

    private function getVeryStaleData(string $network, string $contractAddress, string $cacheType): ?array
    {
        $cached = ContractCache::where('network', $network)
            ->where('contract_address', $contractAddress)
            ->where('cache_type', $cacheType)
            ->orderBy('updated_at', 'desc')
            ->first();

        return $cached ? $cached->toServiceResponse() : null;
    }

    private function canMakeApiCall(string $network): bool
    {
        $dailyLimit = self::API_DAILY_LIMITS[$network] ?? 1000;
        $todayUsage = $this->getTodayApiUsage($network);
        
        // Leave 10% buffer for critical calls
        $safeLimit = $dailyLimit * 0.9;
        
        return $todayUsage < $safeLimit;
    }

    private function getTodayApiUsage(string $network): int
    {
        return ApiUsageTracking::where('network', $network)
            ->whereDate('created_at', today())
            ->count();
    }

    private function calculateOptimalTTL(array $data, string $cacheType, string $priority): int
    {
        $baseTTL = self::CACHE_PRIORITIES[$priority] ?? self::CACHE_PRIORITIES['medium'];
        
        // Adjust TTL based on data characteristics
        $multiplier = 1.0;
        
        if ($cacheType === 'source') {
            // Verified contracts get longer TTL
            if ($data['is_verified'] ?? false) {
                $multiplier *= 1.5;
            }
            
            // Proxy contracts get shorter TTL (might change)
            if ($data['proxy'] ?? false) {
                $multiplier *= 0.7;
            }
            
            // Large contracts get longer TTL (expensive to re-fetch)
            $sourceLength = strlen($data['source_code'] ?? '');
            if ($sourceLength > 50000) {
                $multiplier *= 1.3;
            }
        }
        
        return (int) ($baseTTL * $multiplier);
    }

    private function enhanceDataForCache(array $data, string $network, string $contractAddress, string $cacheType): array
    {
        return array_merge($data, [
            'cache_enhanced_at' => now()->toISOString(),
            'cache_version' => '2.0',
            'cache_metadata' => [
                'network' => $network,
                'contract_address' => $contractAddress,
                'cache_type' => $cacheType,
                'data_size_bytes' => strlen(json_encode($data)),
                'enhancement_version' => '2.0'
            ]
        ]);
    }

    private function recordApiUsage(string $network, string $cacheType): void
    {
        try {
            ApiUsageTracking::create([
                'network' => $network,
                'explorer' => $network . 'scan',
                'endpoint' => "contract/{$cacheType}",
                'response_time_ms' => 0, // Will be updated by actual service
                'success' => true,
                'contract_address' => null, // Will be updated by actual service
                'metadata' => ['cached_at' => now()->toISOString()]
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to record API usage', ['error' => $e->getMessage()]);
        }
    }

    private function getMemoryCacheKey(string $network, string $contractAddress, string $cacheType): string
    {
        return "postgres_cache_{$network}_{$contractAddress}_{$cacheType}";
    }

    private function queueForBackgroundRefresh(string $network, string $contractAddress, string $cacheType): void
    {
        // Only queue if not already queued recently
        $recentQueue = CacheWarmingQueue::where('network', $network)
            ->where('contract_address', $contractAddress)
            ->where('cache_type', $cacheType)
            ->where('created_at', '>', now()->subHour())
            ->exists();
            
        if (!$recentQueue) {
            CacheWarmingQueue::queueContract($network, $contractAddress, $cacheType, 'medium');
        }
    }

    private function hasFreshCache(string $network, string $contractAddress, string $cacheType): bool
    {
        return ContractCache::where('network', $network)
            ->where('contract_address', $contractAddress)
            ->where('cache_type', $cacheType)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    private function getCacheHitMissStats(Carbon $startDate): array
    {
        // This would integrate with ContractCacheAnalytics if available
        $totalRequests = ApiUsageTracking::where('created_at', '>=', $startDate)->count();
        $cacheHits = ContractCache::where('updated_at', '>=', $startDate)->count();
        
        $hitRate = $totalRequests > 0 ? ($cacheHits / $totalRequests) * 100 : 0;
        
        return [
            'total_requests' => $totalRequests,
            'cache_hits' => $cacheHits,
            'cache_misses' => max(0, $totalRequests - $cacheHits),
            'hit_rate_percent' => round($hitRate, 2),
            'api_calls_saved' => $cacheHits
        ];
    }

    private function getApiUsageStats(Carbon $startDate): array
    {
        $usage = ApiUsageTracking::where('created_at', '>=', $startDate)
            ->selectRaw('
                network,
                COUNT(*) as total_calls,
                COUNT(CASE WHEN success = true THEN 1 END) as successful_calls,
                AVG(response_time_ms) as avg_response_time
            ')
            ->groupBy('network')
            ->get()
            ->keyBy('network');
            
        return $usage->toArray();
    }

    private function getStorageAnalytics(): array
    {
        return DB::select("
            SELECT 
                cache_type,
                COUNT(*) as entry_count,
                AVG(CASE WHEN cache_quality_score IS NOT NULL THEN cache_quality_score END) as avg_quality,
                SUM(CASE WHEN source_code IS NOT NULL THEN LENGTH(source_code) ELSE 0 END) as total_source_bytes,
                SUM(CASE WHEN abi IS NOT NULL THEN LENGTH(abi::text) ELSE 0 END) as total_abi_bytes
            FROM contract_cache 
            GROUP BY cache_type
        ");
    }

    private function getPerformanceAnalytics(Carbon $startDate): array
    {
        return [
            'avg_cache_age_hours' => $this->getAverageCacheAge(),
            'stale_usage_rate' => $this->getStaleUsageRate($startDate),
            'cache_efficiency_score' => $this->calculateCacheEfficiencyScore()
        ];
    }

    private function generateRecommendations(array $hitMissStats, array $apiStats): array
    {
        $recommendations = [];
        
        if ($hitMissStats['hit_rate_percent'] < 80) {
            $recommendations[] = 'Cache hit rate is below 80%. Consider warming more contracts proactively.';
        }
        
        foreach ($apiStats as $network => $stats) {
            if (($stats['successful_calls'] ?? 0) / ($stats['total_calls'] ?? 1) < 0.95) {
                $recommendations[] = "API success rate for {$network} is low. Check API key and network connectivity.";
            }
        }
        
        return $recommendations;
    }

    private function getFrequentlyAccessedContracts(int $minAccess = 10): array
    {
        return ContractCache::select('network', 'contract_address', 'cache_type')
            ->where('api_fetch_count', '>=', $minAccess)
            ->where('updated_at', '>', now()->subDays(7))
            ->get()
            ->toArray();
    }

    private function extendCacheTTL(array $contract, string $reason): void
    {
        ContractCache::where('network', $contract['network'])
            ->where('contract_address', $contract['contract_address'])
            ->where('cache_type', $contract['cache_type'])
            ->update([
                'expires_at' => now()->addDays(7),
                'cache_priority' => 'high',
                'refresh_strategy' => $reason
            ]);
    }

    private function cleanupRarelyAccessedExpired(): int
    {
        return ContractCache::where('expires_at', '<', now()->subDays(30))
            ->where('api_fetch_count', '<', 2)
            ->delete();
    }

    private function promoteHighQualityEntries(): int
    {
        return ContractCache::where('cache_quality_score', '>', 0.8)
            ->where('cache_priority', '!=', 'high')
            ->update([
                'cache_priority' => 'high',
                'expires_at' => DB::raw('expires_at + INTERVAL \'3 days\'')
            ]);
    }

    private function queuePredictiveWarming(): int
    {
        // Find contracts that might be accessed soon based on patterns
        $candidates = ContractCache::where('api_fetch_count', '>', 5)
            ->where('expires_at', '>', now())
            ->where('expires_at', '<', now()->addDays(2))
            ->limit(50)
            ->get();
            
        $queued = 0;
        foreach ($candidates as $cache) {
            CacheWarmingQueue::queueContract(
                $cache->network,
                $cache->contract_address,
                $cache->cache_type,
                'medium'
            );
            $queued++;
        }
        
        return $queued;
    }

    private function getQuotaStatus(float $percentUsed): string
    {
        return match (true) {
            $percentUsed >= 90 => 'critical',
            $percentUsed >= 75 => 'warning', 
            $percentUsed >= 50 => 'moderate',
            default => 'healthy'
        };
    }

    private function estimateHoursRemaining(int $remaining): float
    {
        if ($remaining <= 0) return 0;
        
        // Assume steady usage pattern
        $currentHour = now()->hour;
        $hoursLeft = 24 - $currentHour;
        
        return $hoursLeft > 0 ? $remaining / ($hoursLeft ?: 1) : 0;
    }

    private function getAverageCacheAge(): float
    {
        $avg = ContractCache::whereNotNull('fetched_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (NOW() - fetched_at))/3600) as avg_age_hours')
            ->value('avg_age_hours');
            
        return round($avg ?: 0, 2);
    }

    private function getStaleUsageRate(Carbon $startDate): float
    {
        // This would require additional tracking - placeholder for now
        return 15.0; // Assume 15% stale usage rate
    }

    private function calculateCacheEfficiencyScore(): float
    {
        $stats = ContractCache::selectRaw('
            COUNT(*) as total,
            COUNT(CASE WHEN expires_at > NOW() THEN 1 END) as fresh,
            AVG(CASE WHEN cache_quality_score IS NOT NULL THEN cache_quality_score END) as avg_quality
        ')->first();
        
        if (!$stats || $stats->total == 0) return 0;
        
        $freshRatio = $stats->fresh / $stats->total;
        $qualityScore = $stats->avg_quality ?: 0;
        
        return round(($freshRatio * 0.6 + $qualityScore * 0.4) * 100, 2);
    }
}