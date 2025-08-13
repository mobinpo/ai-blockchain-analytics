<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CacheWarmingQueue;
use App\Models\ContractCache;
use App\Models\ApiUsageTracking;
use App\Services\VerifiedSourceFetcher;
use App\Services\PostgresCacheManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Intelligent Cache Warming System to proactively avoid API limits
 * 
 * Features:
 * - Pattern-based predictive warming
 * - Usage-based priority calculation
 * - API quota-aware processing
 * - Batch processing optimization
 * - Smart scheduling based on access patterns
 */
final class IntelligentCacheWarmer
{
    private VerifiedSourceFetcher $sourceFetcher;
    private PostgresCacheManager $cacheManager;
    
    // Warming strategies
    private const WARMING_STRATEGIES = [
        'expiring_soon' => 'Contracts expiring within 24 hours',
        'high_access' => 'Frequently accessed contracts',
        'predictive' => 'Contracts likely to be accessed soon',
        'popular_tokens' => 'Popular token contracts',
        'new_contracts' => 'Recently deployed contracts',
        'failed_attempts' => 'Contracts with recent failed fetch attempts'
    ];

    // Processing limits per strategy
    private const STRATEGY_LIMITS = [
        'expiring_soon' => 100,
        'high_access' => 50,
        'predictive' => 30,
        'popular_tokens' => 20,
        'new_contracts' => 15,
        'failed_attempts' => 10
    ];

    public function __construct(
        VerifiedSourceFetcher $sourceFetcher,
        PostgresCacheManager $cacheManager
    ) {
        $this->sourceFetcher = $sourceFetcher;
        $this->cacheManager = $cacheManager;
    }

    /**
     * Execute intelligent cache warming based on multiple strategies
     */
    public function executeIntelligentWarming(
        array $strategies = null,
        int $maxApiCalls = 50,
        string $network = 'ethereum'
    ): array {
        $strategies = $strategies ?? array_keys(self::WARMING_STRATEGIES);
        
        Log::info('Starting intelligent cache warming', [
            'strategies' => $strategies,
            'max_api_calls' => $maxApiCalls,
            'network' => $network
        ]);

        $results = [
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'skipped_quota' => 0,
            'strategies_executed' => [],
            'api_calls_used' => 0,
            'time_taken_seconds' => 0
        ];

        $startTime = microtime(true);
        $apiCallsUsed = 0;

        // Check initial API quota
        if (!$this->hasApiQuotaAvailable($network, $maxApiCalls)) {
            Log::warning('Insufficient API quota for warming', [
                'network' => $network,
                'requested' => $maxApiCalls,
                'available' => $this->getRemainingApiQuota($network)
            ]);
            return array_merge($results, ['reason' => 'insufficient_api_quota']);
        }

        foreach ($strategies as $strategy) {
            if ($apiCallsUsed >= $maxApiCalls) {
                Log::info('API call limit reached, stopping warming', [
                    'calls_used' => $apiCallsUsed,
                    'max_calls' => $maxApiCalls
                ]);
                break;
            }

            $strategyResult = $this->executeStrategy(
                $strategy,
                $network,
                $maxApiCalls - $apiCallsUsed
            );

            $results['strategies_executed'][$strategy] = $strategyResult;
            $results['processed'] += $strategyResult['processed'];
            $results['successful'] += $strategyResult['successful'];
            $results['failed'] += $strategyResult['failed'];
            $apiCallsUsed += $strategyResult['api_calls_used'];

            // Small delay between strategies to avoid overwhelming the API
            usleep(100000); // 100ms
        }

        $results['api_calls_used'] = $apiCallsUsed;
        $results['time_taken_seconds'] = round(microtime(true) - $startTime, 2);

        Log::info('Intelligent cache warming completed', $results);

        return $results;
    }

    /**
     * Warm specific contracts with priority handling
     */
    public function warmSpecificContracts(
        array $contractAddresses,
        string $network = 'ethereum',
        string $priority = 'high'
    ): array {
        $results = ['successful' => 0, 'failed' => 0, 'already_cached' => 0];
        
        Log::info('Warming specific contracts', [
            'count' => count($contractAddresses),
            'network' => $network,
            'priority' => $priority
        ]);

        foreach ($contractAddresses as $address) {
            try {
                // Check if already fresh in cache
                if ($this->hasRecentCache($network, $address)) {
                    $results['already_cached']++;
                    continue;
                }

                // Check API quota
                if (!$this->hasApiQuotaAvailable($network, 1)) {
                    Log::warning('API quota exhausted during specific warming');
                    break;
                }

                // Fetch and cache
                $sourceData = $this->sourceFetcher->fetchVerifiedSource($address, $network, true);
                
                if ($sourceData) {
                    $results['successful']++;
                    Log::debug('Successfully warmed contract', [
                        'address' => $address,
                        'network' => $network
                    ]);
                } else {
                    $results['failed']++;
                }

                // Rate limiting
                usleep(250000); // 250ms delay

            } catch (\Exception $e) {
                $results['failed']++;
                Log::error('Failed to warm specific contract', [
                    'address' => $address,
                    'network' => $network,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    /**
     * Schedule predictive warming based on usage patterns
     */
    public function schedulePredictiveWarming(): array
    {
        $predictions = $this->generateAccessPredictions();
        $scheduled = [];

        foreach ($predictions as $prediction) {
            $queueResult = CacheWarmingQueue::queueContract(
                $prediction['network'],
                $prediction['contract_address'],
                'source',
                $prediction['priority'],
                $prediction['scheduled_for']
            );

            if ($queueResult) {
                $scheduled[] = $prediction;
            }
        }

        Log::info('Predictive warming scheduled', [
            'predictions_generated' => count($predictions),
            'contracts_scheduled' => count($scheduled)
        ]);

        return [
            'predictions_generated' => count($predictions),
            'contracts_scheduled' => count($scheduled),
            'scheduled_contracts' => $scheduled
        ];
    }

    /**
     * Process warming queue with intelligent prioritization
     */
    public function processWarmingQueue(int $maxProcessing = 20): array
    {
        $queueItems = CacheWarmingQueue::getNextBatch($maxProcessing);
        
        if (empty($queueItems)) {
            return ['message' => 'No items in warming queue'];
        }

        $results = ['processed' => 0, 'successful' => 0, 'failed' => 0, 'skipped' => 0];

        Log::info('Processing warming queue', [
            'queue_size' => count($queueItems),
            'max_processing' => $maxProcessing
        ]);

        foreach ($queueItems as $item) {
            try {
                // Check API quota for this network
                if (!$this->hasApiQuotaAvailable($item['network'], 1)) {
                    $results['skipped']++;
                    Log::debug('Skipped queue item due to API quota', [
                        'network' => $item['network'],
                        'address' => $item['contract_address']
                    ]);
                    continue;
                }

                // Check if still needs warming
                if ($this->hasRecentCache($item['network'], $item['contract_address'])) {
                    CacheWarmingQueue::markAsCompleted($item['id']);
                    $results['processed']++;
                    continue;
                }

                // Perform warming
                $sourceData = $this->sourceFetcher->fetchVerifiedSource(
                    $item['contract_address'],
                    $item['network'],
                    true
                );

                if ($sourceData) {
                    CacheWarmingQueue::markAsCompleted($item['id']);
                    $results['successful']++;
                } else {
                    CacheWarmingQueue::markAsFailed($item['id'], 'Failed to fetch source data');
                    $results['failed']++;
                }

                $results['processed']++;

                // Rate limiting
                usleep(200000); // 200ms

            } catch (\Exception $e) {
                CacheWarmingQueue::markAsFailed($item['id'], $e->getMessage());
                $results['failed']++;
                
                Log::error('Queue processing error', [
                    'item_id' => $item['id'],
                    'address' => $item['contract_address'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Warming queue processing completed', $results);

        return $results;
    }

    /**
     * Get warming statistics and recommendations
     */
    public function getWarmingStatistics(): array
    {
        $queueStats = CacheWarmingQueue::getQueueStats();
        $cacheStats = ContractCache::getCacheEfficiencyStats();
        $apiStats = $this->getApiUsageStats();

        $recommendations = $this->generateWarmingRecommendations($queueStats, $cacheStats, $apiStats);

        return [
            'queue_statistics' => $queueStats,
            'cache_statistics' => $cacheStats,
            'api_statistics' => $apiStats,
            'warming_efficiency' => $this->calculateWarmingEfficiency(),
            'recommendations' => $recommendations,
            'next_warming_candidates' => $this->getNextWarmingCandidates(10)
        ];
    }

    private function executeStrategy(string $strategy, string $network, int $maxCalls): array
    {
        $result = ['processed' => 0, 'successful' => 0, 'failed' => 0, 'api_calls_used' => 0];
        
        $candidates = $this->getCandidatesForStrategy($strategy, $network);
        $limit = min(count($candidates), self::STRATEGY_LIMITS[$strategy] ?? 10, $maxCalls);

        Log::debug("Executing warming strategy: {$strategy}", [
            'candidates_found' => count($candidates),
            'processing_limit' => $limit,
            'network' => $network
        ]);

        for ($i = 0; $i < $limit; $i++) {
            if (!isset($candidates[$i])) break;
            
            $candidate = $candidates[$i];
            
            try {
                // Skip if recently cached
                if ($this->hasRecentCache($network, $candidate['contract_address'])) {
                    continue;
                }

                // Check API quota
                if (!$this->hasApiQuotaAvailable($network, 1)) {
                    break;
                }

                // Warm the contract
                $sourceData = $this->sourceFetcher->fetchVerifiedSource(
                    $candidate['contract_address'],
                    $network,
                    true
                );

                if ($sourceData) {
                    $result['successful']++;
                } else {
                    $result['failed']++;
                }

                $result['api_calls_used']++;
                $result['processed']++;

                // Rate limiting
                usleep(150000); // 150ms

            } catch (\Exception $e) {
                $result['failed']++;
                Log::warning("Strategy {$strategy} failed for contract", [
                    'address' => $candidate['contract_address'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $result;
    }

    private function getCandidatesForStrategy(string $strategy, string $network): array
    {
        return match ($strategy) {
            'expiring_soon' => $this->getExpiringSoonCandidates($network),
            'high_access' => $this->getHighAccessCandidates($network),
            'predictive' => $this->getPredictiveCandidates($network),
            'popular_tokens' => $this->getPopularTokenCandidates($network),
            'new_contracts' => $this->getNewContractCandidates($network),
            'failed_attempts' => $this->getFailedAttemptCandidates($network),
            default => []
        };
    }

    private function getExpiringSoonCandidates(string $network): array
    {
        return ContractCache::where('network', $network)
            ->where('cache_type', 'source')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDay())
            ->orderBy('expires_at')
            ->limit(self::STRATEGY_LIMITS['expiring_soon'])
            ->get(['contract_address', 'expires_at', 'api_fetch_count'])
            ->toArray();
    }

    private function getHighAccessCandidates(string $network): array
    {
        return ContractCache::where('network', $network)
            ->where('cache_type', 'source')
            ->where('api_fetch_count', '>=', 5)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '<=', now()->addDays(2));
            })
            ->orderBy('api_fetch_count', 'desc')
            ->limit(self::STRATEGY_LIMITS['high_access'])
            ->get(['contract_address', 'api_fetch_count', 'expires_at'])
            ->toArray();
    }

    private function getPredictiveCandidates(string $network): array
    {
        // Contracts accessed in similar patterns recently
        return DB::select("
            SELECT DISTINCT c.contract_address, c.api_fetch_count
            FROM contract_cache c
            JOIN api_usage_tracking a ON a.network = c.network 
            WHERE c.network = ? 
            AND c.cache_type = 'source'
            AND c.api_fetch_count > 2
            AND (c.expires_at IS NULL OR c.expires_at <= NOW() + INTERVAL '3 days')
            AND a.created_at >= NOW() - INTERVAL '7 days'
            ORDER BY c.api_fetch_count DESC
            LIMIT ?
        ", [$network, self::STRATEGY_LIMITS['predictive']]);
    }

    private function getPopularTokenCandidates(string $network): array
    {
        // This would integrate with token popularity data
        $popularTokens = [
            'ethereum' => [
                '0xdac17f958d2ee523a2206206994597c13d831ec7', // USDT
                '0xa0b86a33e6441b8b9e27f7d64c9cc5d83ca0ccce', // USDC
                '0x6b175474e89094c44da98b954eedeac495271d0f'  // DAI
            ],
            'bsc' => [
                '0x55d398326f99059ff775485246999027b3197955', // USDT
                '0x8ac76a51cc950d9822d68b83fe1ad97b32cd580d'  // USDC
            ]
        ];

        $tokens = $popularTokens[$network] ?? [];
        return array_map(fn($address) => ['contract_address' => $address], $tokens);
    }

    private function getNewContractCandidates(string $network): array
    {
        return ContractCache::where('network', $network)
            ->where('cache_type', 'source')
            ->where('created_at', '>=', now()->subDays(7))
            ->whereNull('expires_at')
            ->orderBy('created_at', 'desc')
            ->limit(self::STRATEGY_LIMITS['new_contracts'])
            ->get(['contract_address', 'created_at'])
            ->toArray();
    }

    private function getFailedAttemptCandidates(string $network): array
    {
        return ContractCache::where('network', $network)
            ->where('cache_type', 'source')
            ->where('error_count', '>', 0)
            ->where('last_error_at', '>=', now()->subDays(2))
            ->where('error_count', '<', 5) // Don't retry too many times
            ->orderBy('last_error_at', 'desc')
            ->limit(self::STRATEGY_LIMITS['failed_attempts'])
            ->get(['contract_address', 'error_count', 'last_error_at'])
            ->toArray();
    }

    private function generateAccessPredictions(): array
    {
        // Predict which contracts might be accessed based on patterns
        $predictions = [];
        
        // Find contracts with regular access patterns
        $regularPatterns = DB::select("
            SELECT 
                network,
                contract_address,
                COUNT(*) as access_count,
                AVG(EXTRACT(EPOCH FROM (NOW() - last_api_fetch))/3600) as avg_hours_between
            FROM contract_cache 
            WHERE last_api_fetch IS NOT NULL 
            AND api_fetch_count >= 3
            AND last_api_fetch >= NOW() - INTERVAL '30 days'
            GROUP BY network, contract_address
            HAVING avg_hours_between BETWEEN 12 AND 168 -- 12 hours to 1 week
            ORDER BY access_count DESC
            LIMIT 20
        ");

        foreach ($regularPatterns as $pattern) {
            $predictions[] = [
                'network' => $pattern->network,
                'contract_address' => $pattern->contract_address,
                'priority' => $pattern->access_count > 10 ? 'high' : 'medium',
                'reason' => 'regular_access_pattern',
                'confidence' => min(90, $pattern->access_count * 10),
                'scheduled_for' => now()->addHours($pattern->avg_hours_between * 0.8)
            ];
        }

        return $predictions;
    }

    private function hasApiQuotaAvailable(string $network, int $requiredCalls): bool
    {
        $quotaStatus = $this->cacheManager->getApiQuotaStatus();
        $networkStatus = $quotaStatus[$network] ?? null;
        
        if (!$networkStatus) {
            return false;
        }

        return $networkStatus['remaining'] >= $requiredCalls;
    }

    private function getRemainingApiQuota(string $network): int
    {
        $quotaStatus = $this->cacheManager->getApiQuotaStatus();
        return $quotaStatus[$network]['remaining'] ?? 0;
    }

    private function hasRecentCache(string $network, string $contractAddress): bool
    {
        return ContractCache::where('network', $network)
            ->where('contract_address', strtolower($contractAddress))
            ->where('cache_type', 'source')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now()->addHours(12));
            })
            ->exists();
    }

    private function getApiUsageStats(): array
    {
        return ApiUsageTracking::whereDate('created_at', today())
            ->selectRaw('
                network,
                COUNT(*) as calls_today,
                COUNT(CASE WHEN success = true THEN 1 END) as successful_calls,
                AVG(response_time_ms) as avg_response_time
            ')
            ->groupBy('network')
            ->get()
            ->keyBy('network')
            ->toArray();
    }

    private function generateWarmingRecommendations(array $queueStats, array $cacheStats, array $apiStats): array
    {
        $recommendations = [];

        // Queue size recommendations
        if (($queueStats['pending'] ?? 0) > 100) {
            $recommendations[] = 'Large warming queue detected. Consider increasing processing frequency.';
        }

        // Cache hit rate recommendations
        if (($cacheStats['total_api_calls_saved'] ?? 0) < 100) {
            $recommendations[] = 'Low API call savings. Increase proactive warming for frequently accessed contracts.';
        }

        // API usage recommendations
        foreach ($apiStats as $network => $stats) {
            if ($stats['calls_today'] > 500) {
                $recommendations[] = "High API usage for {$network}. Consider more aggressive caching.";
            }
        }

        return $recommendations;
    }

    private function calculateWarmingEfficiency(): float
    {
        $totalWarmed = CacheWarmingQueue::where('status', 'completed')
            ->whereDate('completed_at', '>=', now()->subDays(7))
            ->count();
            
        $totalRequested = CacheWarmingQueue::whereDate('created_at', '>=', now()->subDays(7))
            ->count();

        return $totalRequested > 0 ? ($totalWarmed / $totalRequested) * 100 : 0;
    }

    private function getNextWarmingCandidates(int $limit): array
    {
        return ContractCache::where('cache_type', 'source')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '<=', now()->addHours(24));
            })
            ->where('api_fetch_count', '>', 1)
            ->orderBy('api_fetch_count', 'desc')
            ->orderBy('expires_at')
            ->limit($limit)
            ->get(['network', 'contract_address', 'expires_at', 'api_fetch_count'])
            ->toArray();
    }
}