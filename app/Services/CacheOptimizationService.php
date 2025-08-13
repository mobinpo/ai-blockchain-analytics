<?php

namespace App\Services;

use App\Models\ContractCache;
use App\Models\ApiUsageTracking;
use App\Models\CacheWarmingQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CacheOptimizationService
{
    /**
     * Enhanced cache TTL strategies to avoid API limits
     */
    private const CACHE_STRATEGIES = [
        'verified_contract' => [
            'base_ttl' => 7 * 24 * 3600, // 7 days for verified contracts
            'max_ttl' => 30 * 24 * 3600, // 30 days maximum
            'refresh_threshold' => 0.8, // Refresh when 80% of TTL elapsed
        ],
        'proxy_contract' => [
            'base_ttl' => 3 * 24 * 3600, // 3 days for proxy contracts (may change)
            'max_ttl' => 14 * 24 * 3600, // 14 days maximum
            'refresh_threshold' => 0.7,
        ],
        'unverified_contract' => [
            'base_ttl' => 24 * 3600, // 1 day for unverified
            'max_ttl' => 7 * 24 * 3600, // 7 days maximum
            'refresh_threshold' => 0.9,
        ],
        'popular_contract' => [
            'base_ttl' => 14 * 24 * 3600, // 14 days for popular contracts
            'max_ttl' => 60 * 24 * 3600, // 60 days maximum
            'refresh_threshold' => 0.6,
        ]
    ];

    /**
     * Determine optimal cache TTL based on contract characteristics
     */
    public function calculateOptimalTTL(array $contractData, string $cacheType = 'source'): int
    {
        $baseStrategy = $this->determineStrategy($contractData);
        $strategy = self::CACHE_STRATEGIES[$baseStrategy];
        
        $baseTTL = $strategy['base_ttl'];
        
        // Extend TTL based on contract stability indicators
        $multiplier = 1.0;
        
        // Verified contracts with complete data get longer cache
        if ($this->isHighQualityContract($contractData)) {
            $multiplier *= 1.5;
        }
        
        // Large contracts (expensive to re-fetch) get longer cache
        if ($this->isLargeContract($contractData)) {
            $multiplier *= 1.3;
        }
        
        // Recently accessed contracts get longer cache
        if ($this->isRecentlyAccessed($contractData)) {
            $multiplier *= 1.2;
        }
        
        // Apply network-specific adjustments
        $multiplier *= $this->getNetworkMultiplier($contractData['network'] ?? 'ethereum');
        
        $finalTTL = (int) ($baseTTL * $multiplier);
        
        return min($finalTTL, $strategy['max_ttl']);
    }

    /**
     * Implement cache-first strategy with smart fallbacks
     */
    public function getCachedDataWithFallback(string $network, string $address, string $type): ?array
    {
        // Try fresh cache first
        $cached = ContractCache::where('network', $network)
            ->where('contract_address', $address)
            ->where('cache_type', $type)
            ->where('expires_at', '>', now())
            ->first();

        if ($cached) {
            Log::debug('Cache hit (fresh)', [
                'address' => $address,
                'network' => $network,
                'type' => $type
            ]);
            return $cached->toServiceResponse();
        }

        // Try stale cache (extend TTL to avoid API calls)
        $staleCache = ContractCache::where('network', $network)
            ->where('contract_address', $address)
            ->where('cache_type', $type)
            ->where('expires_at', '>', now()->subDays(7)) // Accept up to 7 days stale
            ->first();

        if ($staleCache && $this->shouldUseStaleCache($network)) {
            // Extend TTL and queue for background refresh
            $this->extendCacheAndQueueRefresh($staleCache);
            
            Log::info('Using stale cache to avoid API limits', [
                'address' => $address,
                'network' => $network,
                'expired_at' => $staleCache->expires_at,
                'age_hours' => now()->diffInHours($staleCache->expires_at)
            ]);
            
            return $staleCache->toServiceResponse();
        }

        return null;
    }

    /**
     * Proactive cache warming for high-value contracts
     */
    public function warmHighValueContracts(int $limit = 50): array
    {
        $contracts = $this->identifyHighValueContracts($limit);
        $warmed = [];
        $errors = [];

        foreach ($contracts as $contract) {
            try {
                $queued = CacheWarmingQueue::queueContract(
                    $contract['network'],
                    $contract['address'],
                    CacheWarmingQueue::CACHE_TYPE_SOURCE,
                    'high'
                );

                if ($queued) {
                    $warmed[] = $contract;
                } else {
                    $errors[] = $contract['address'] . ' (already queued)';
                }

                // Rate limiting
                usleep(100000); // 100ms delay

            } catch (\Exception $e) {
                $errors[] = $contract['address'] . ': ' . $e->getMessage();
            }
        }

        Log::info('Cache warming completed', [
            'warmed_count' => count($warmed),
            'error_count' => count($errors)
        ]);

        return [
            'warmed' => $warmed,
            'errors' => $errors,
            'summary' => [
                'total_processed' => count($contracts),
                'successfully_queued' => count($warmed),
                'failed' => count($errors)
            ]
        ];
    }

    /**
     * Implement intelligent cache refresh scheduling
     */
    public function scheduleIntelligentRefresh(): array
    {
        $refreshed = 0;
        $errors = [];
        $skipped = 0;

        // Get contracts that need refresh based on priority and usage
        $contractsToRefresh = $this->getContractsForRefresh();

        foreach ($contractsToRefresh as $contract) {
            try {
                // Check if we should skip this refresh due to API limits
                if ($this->shouldSkipRefreshDueToLimits($contract['network'])) {
                    $skipped++;
                    continue;
                }

                // Queue for background refresh instead of immediate refresh
                CacheWarmingQueue::queueContract(
                    $contract['network'],
                    $contract['contract_address'],
                    $contract['cache_type'],
                    'medium'
                );

                $refreshed++;

            } catch (\Exception $e) {
                $errors[] = $contract['contract_address'] . ': ' . $e->getMessage();
            }
        }

        return [
            'refreshed' => $refreshed,
            'skipped' => $skipped,
            'errors' => count($errors),
            'total_candidates' => count($contractsToRefresh)
        ];
    }

    /**
     * Analyze cache efficiency and suggest optimizations
     */
    public function analyzeCacheEfficiency(): array
    {
        $stats = DB::select("
            SELECT 
                network,
                cache_type,
                COUNT(*) as total_entries,
                COUNT(CASE WHEN expires_at > NOW() THEN 1 END) as valid_entries,
                COUNT(CASE WHEN expires_at <= NOW() THEN 1 END) as expired_entries,
                AVG(cache_quality_score) as avg_quality,
                AVG(api_fetch_count) as avg_api_fetches,
                SUM(CASE WHEN fetched_from_api THEN 1 ELSE 0 END) as api_originated,
                AVG(EXTRACT(EPOCH FROM (expires_at - created_at))/3600) as avg_ttl_hours
            FROM contract_cache 
            WHERE created_at > NOW() - INTERVAL '30 days'
            GROUP BY network, cache_type
            ORDER BY network, cache_type
        ");

        $recommendations = [];
        
        foreach ($stats as $stat) {
            $hitRate = $stat->valid_entries / max($stat->total_entries, 1) * 100;
            
            if ($hitRate < 70) {
                $recommendations[] = [
                    'network' => $stat->network,
                    'type' => $stat->cache_type,
                    'issue' => 'Low cache hit rate',
                    'current_rate' => round($hitRate, 1) . '%',
                    'suggestion' => 'Increase TTL or implement proactive warming'
                ];
            }
            
            if ($stat->avg_api_fetches > 3) {
                $recommendations[] = [
                    'network' => $stat->network,
                    'type' => $stat->cache_type,
                    'issue' => 'High API fetch frequency',
                    'avg_fetches' => round($stat->avg_api_fetches, 1),
                    'suggestion' => 'Extend cache TTL for stable contracts'
                ];
            }
        }

        return [
            'stats' => $stats,
            'recommendations' => $recommendations,
            'overall_health' => $this->calculateOverallCacheHealth($stats)
        ];
    }

    /**
     * Get API usage statistics to inform cache decisions
     */
    public function getApiUsageStats(int $hours = 24): array
    {
        $stats = ApiUsageTracking::where('created_at', '>', now()->subHours($hours))
            ->selectRaw('
                network,
                COUNT(*) as total_calls,
                COUNT(CASE WHEN success THEN 1 END) as successful_calls,
                AVG(response_time_ms) as avg_response_time,
                COUNT(CASE WHEN success = false THEN 1 END) as failed_calls
            ')
            ->groupBy('network')
            ->get();

        $recommendations = [];
        
        foreach ($stats as $stat) {
            $successRate = ($stat->successful_calls / max($stat->total_calls, 1)) * 100;
            
            if ($stat->total_calls > 100) {
                $recommendations[] = [
                    'network' => $stat->network,
                    'total_calls' => $stat->total_calls,
                    'success_rate' => round($successRate, 1) . '%',
                    'suggestion' => 'High API usage - consider extending cache TTL'
                ];
            }
            
            if ($successRate < 90) {
                $recommendations[] = [
                    'network' => $stat->network,
                    'success_rate' => round($successRate, 1) . '%',
                    'suggestion' => 'High failure rate - use stale cache when possible'
                ];
            }
        }

        return [
            'stats' => $stats,
            'recommendations' => $recommendations,
            'period_hours' => $hours
        ];
    }

    /**
     * Determine caching strategy based on contract characteristics
     */
    private function determineStrategy(array $contractData): string
    {
        if ($this->isPopularContract($contractData)) {
            return 'popular_contract';
        }
        
        if ($contractData['proxy'] ?? false) {
            return 'proxy_contract';
        }
        
        if ($contractData['is_verified'] ?? false) {
            return 'verified_contract';
        }
        
        return 'unverified_contract';
    }

    /**
     * Check if contract is high quality (complete data)
     */
    private function isHighQualityContract(array $data): bool
    {
        return ($data['is_verified'] ?? false) &&
               !empty($data['source_code'] ?? '') &&
               !empty($data['abi'] ?? []) &&
               !empty($data['compiler_version'] ?? '');
    }

    /**
     * Check if contract is large (expensive to re-fetch)
     */
    private function isLargeContract(array $data): bool
    {
        $sourceCode = $data['source_code'] ?? '';
        $lineCount = substr_count($sourceCode, "\n") + 1;
        return $lineCount > 500 || strlen($sourceCode) > 50000;
    }

    /**
     * Check if contract was recently accessed
     */
    private function isRecentlyAccessed(array $data): bool
    {
        // This would check analytics data - simplified for now
        return ($data['api_fetch_count'] ?? 0) > 1;
    }

    /**
     * Get network-specific cache multiplier
     */
    private function getNetworkMultiplier(string $network): float
    {
        return match($network) {
            'ethereum' => 1.0, // Standard
            'bsc' => 1.2,      // BSC tends to be more stable
            'polygon' => 1.1,   // Polygon fairly stable
            'arbitrum' => 0.9,  // L2s change more frequently
            'optimism' => 0.9,  // L2s change more frequently
            default => 1.0
        };
    }

    /**
     * Check if we should use stale cache to avoid API limits
     */
    private function shouldUseStaleCache(string $network): bool
    {
        // Check recent API usage
        $recentCalls = ApiUsageTracking::where('network', $network)
            ->where('created_at', '>', now()->subHour())
            ->count();

        // Use stale cache if we've made many recent calls
        return $recentCalls > 50;
    }

    /**
     * Extend cache TTL and queue for background refresh
     */
    private function extendCacheAndQueueRefresh(ContractCache $cache): void
    {
        // Extend TTL by 24 hours
        $cache->update([
            'expires_at' => now()->addDay(),
            'stale_extension_count' => ($cache->stale_extension_count ?? 0) + 1
        ]);

        // Queue for background refresh
        CacheWarmingQueue::queueContract(
            $cache->network,
            $cache->contract_address,
            $cache->cache_type,
            'low' // Low priority since we have stale data
        );
    }

    /**
     * Identify high-value contracts for proactive caching
     */
    private function identifyHighValueContracts(int $limit): array
    {
        return DB::select("
            SELECT DISTINCT
                cc.network,
                cc.contract_address as address,
                cc.contract_name,
                COUNT(*) OVER (PARTITION BY cc.contract_address) as access_count,
                MAX(cc.updated_at) OVER (PARTITION BY cc.contract_address) as last_access
            FROM contract_cache cc
            WHERE cc.created_at > NOW() - INTERVAL '7 days'
            AND cc.cache_quality_score > 0.8
            ORDER BY access_count DESC, last_access DESC
            LIMIT ?
        ", [$limit]);
    }

    /**
     * Get contracts that need refresh based on intelligent criteria
     */
    private function getContractsForRefresh(): array
    {
        return DB::select("
            SELECT 
                network,
                contract_address,
                cache_type,
                expires_at,
                cache_priority,
                api_fetch_count,
                cache_quality_score
            FROM contract_cache
            WHERE (
                expires_at < NOW() + INTERVAL '2 hours' -- Refresh soon-to-expire
                OR (expires_at < NOW() AND stale_extension_count < 3) -- Or recently expired
            )
            AND cache_quality_score > 0.5 -- Only quality entries
            ORDER BY 
                CASE cache_priority 
                    WHEN 'critical' THEN 1 
                    WHEN 'high' THEN 2 
                    WHEN 'medium' THEN 3 
                    ELSE 4 
                END,
                expires_at ASC
            LIMIT 100
        ");
    }

    /**
     * Check if we should skip refresh due to API limits
     */
    private function shouldSkipRefreshDueToLimits(string $network): bool
    {
        $recentFailures = ApiUsageTracking::where('network', $network)
            ->where('created_at', '>', now()->subMinutes(30))
            ->where('success', false)
            ->count();

        return $recentFailures > 5; // Skip if many recent failures
    }

    /**
     * Check if contract is popular based on usage patterns
     */
    private function isPopularContract(array $data): bool
    {
        // Simplified check - would normally check analytics
        return ($data['api_fetch_count'] ?? 0) > 5;
    }

    /**
     * Calculate overall cache health score
     */
    private function calculateOverallCacheHealth($stats): array
    {
        $totalEntries = array_sum(array_column($stats, 'total_entries'));
        $validEntries = array_sum(array_column($stats, 'valid_entries'));
        $avgQuality = array_sum(array_column($stats, 'avg_quality')) / max(count($stats), 1);
        
        $hitRate = $totalEntries > 0 ? ($validEntries / $totalEntries * 100) : 0;
        
        $healthScore = (($hitRate / 100) * 0.6) + ($avgQuality * 0.4);
        
        return [
            'overall_hit_rate' => round($hitRate, 1),
            'average_quality' => round($avgQuality, 2),
            'health_score' => round($healthScore, 2),
            'status' => $healthScore > 0.8 ? 'excellent' : ($healthScore > 0.6 ? 'good' : 'needs_improvement')
        ];
    }
}