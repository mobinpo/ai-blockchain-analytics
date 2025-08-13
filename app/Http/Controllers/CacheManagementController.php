<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ApiCacheService;
use App\Services\CoinGeckoCacheService;
use App\Services\BlockchainCacheService;
use App\Models\ApiCache;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

final class CacheManagementController extends Controller
{
    public function __construct(
        private readonly ApiCacheService $cacheService,
        private readonly CoinGeckoCacheService $coinGeckoService,
        private readonly BlockchainCacheService $blockchainService
    ) {}

    /**
     * Show the cache management dashboard.
     */
    public function dashboard(): Response
    {
        $stats = $this->cacheService->getStatistics();
        $healthCheck = $this->cacheService->healthCheck();
        $coinGeckoStats = $this->coinGeckoService->getRateLimitStatus();
        $blockchainStats = $this->blockchainService->getCacheStatistics();

        return Inertia::render('Admin/CacheManagement', [
            'stats' => $stats,
            'health' => $healthCheck,
            'coinGeckoStats' => $coinGeckoStats,
            'blockchainStats' => $blockchainStats,
            'recentEntries' => ApiCache::orderBy('created_at', 'desc')
                ->limit(20)
                ->get(['id', 'api_source', 'resource_type', 'resource_id', 'hit_count', 'expires_at', 'created_at'])
                ->toArray(),
        ]);
    }

    /**
     * Get detailed cache statistics.
     */
    public function statistics(): JsonResponse
    {
        return response()->json([
            'overall' => $this->cacheService->getStatistics(),
            'coingecko' => $this->coinGeckoService->getRateLimitStatus(),
            'blockchain' => $this->blockchainService->getCacheStatistics(),
            'health' => $this->cacheService->healthCheck(),
        ]);
    }

    /**
     * Get cache entries with filtering and pagination.
     */
    public function entries(Request $request): JsonResponse
    {
        $query = ApiCache::query();

        // Apply filters
        if ($request->filled('api_source')) {
            $query->where('api_source', $request->api_source);
        }

        if ($request->filled('resource_type')) {
            $query->where('resource_type', $request->resource_type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('resource_id', 'ilike', "%{$search}%")
                  ->orWhere('endpoint', 'ilike', "%{$search}%")
                  ->orWhere('cache_key', 'ilike', "%{$search}%");
            });
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);

        // Get paginated results
        $entries = $query->paginate($request->get('per_page', 50));

        return response()->json($entries);
    }

    /**
     * Get details for a specific cache entry.
     */
    public function show(string $cacheId): JsonResponse
    {
        // Validate that the cache ID is a valid integer
        if (!is_numeric($cacheId) || (int)$cacheId != $cacheId) {
            return response()->json([
                'error' => 'Invalid cache ID format. Expected numeric ID.',
                'provided_id' => $cacheId,
            ], 400);
        }

        // Find the cache entry
        $cache = ApiCache::find((int)$cacheId);
        
        if (!$cache) {
            return response()->json([
                'error' => 'Cache entry not found.',
                'cache_id' => (int)$cacheId,
            ], 404);
        }

        return response()->json([
            'cache' => $cache->load([]),
            'integrity_check' => $cache->verifyIntegrity(),
            'age_hours' => now()->diffInHours($cache->created_at),
            'ttl_remaining_seconds' => max(0, $cache->expires_at->timestamp - now()->timestamp),
        ]);
    }

    /**
     * Invalidate specific cache entries.
     */
    public function invalidate(Request $request): JsonResponse
    {
        $request->validate([
            'criteria' => 'required|array',
            'criteria.api_source' => 'sometimes|string',
            'criteria.resource_type' => 'sometimes|string',
            'criteria.resource_id' => 'sometimes|string',
            'criteria.endpoint' => 'sometimes|string',
        ]);

        $invalidated = $this->cacheService->invalidate($request->criteria);

        return response()->json([
            'success' => true,
            'message' => "Successfully invalidated {$invalidated} cache entries",
            'invalidated_count' => $invalidated,
        ]);
    }

    /**
     * Invalidate single cache entry by ID.
     */
    public function invalidateEntry(string $cacheId): JsonResponse
    {
        // Validate that the cache ID is a valid integer
        if (!is_numeric($cacheId) || (int)$cacheId != $cacheId) {
            return response()->json([
                'error' => 'Invalid cache ID format. Expected numeric ID.',
                'provided_id' => $cacheId,
            ], 400);
        }

        // Find the cache entry
        $cache = ApiCache::find((int)$cacheId);
        
        if (!$cache) {
            return response()->json([
                'error' => 'Cache entry not found.',
                'cache_id' => (int)$cacheId,
            ], 404);
        }

        $cache->invalidate();

        return response()->json([
            'success' => true,
            'message' => 'Cache entry invalidated successfully',
        ]);
    }

    /**
     * Clean up expired cache entries.
     */
    public function cleanup(Request $request): JsonResponse
    {
        $aggressive = $request->boolean('aggressive', false);
        $stats = $this->cacheService->cleanup($aggressive);

        return response()->json([
            'success' => true,
            'message' => "Cleanup completed. Deleted {$stats['deleted']} entries, freed {$stats['size_freed_mb']} MB",
            'stats' => $stats,
        ]);
    }

    /**
     * Warm cache for popular data.
     */
    public function warmCache(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:coingecko,contracts,popular',
            'items' => 'sometimes|array',
        ]);

        $warmed = 0;
        $type = $request->type;

        try {
            switch ($type) {
                case 'coingecko':
                    $coinIds = $request->get('items', [
                        'bitcoin', 'ethereum', 'binancecoin', 'cardano', 'solana'
                    ]);
                    $warmed = $this->coinGeckoService->warmPopularCoins($coinIds);
                    break;

                case 'contracts':
                    $addresses = $request->get('items', [
                        '0xA0b86a33E6441f8C166768C8248906dEF09B2860', // Uniswap V3 Router
                        '0x7d2768dE32b0b80b7a3454c06BdAc94A69DDc7A9', // Aave V2 Pool
                        '0xC02aaA39b223FE8D0A0e5C4F27eAD9083C756Cc2', // WETH
                    ]);
                    $warmed = $this->blockchainService->warmContractData($addresses);
                    break;

                case 'popular':
                    $warmed += $this->cacheService->preloadFrequentlyAccessed();
                    break;
            }

            return response()->json([
                'success' => true,
                'message' => "Successfully warmed cache for {$warmed} items",
                'warmed_count' => $warmed,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cache warming failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get cache performance metrics over time.
     */
    public function metrics(Request $request): JsonResponse
    {
        $days = min($request->get('days', 7), 30); // Max 30 days
        
        $metrics = ApiCache::selectRaw('
            DATE(created_at) as date,
            api_source,
            COUNT(*) as entries_created,
            SUM(hit_count) as total_hits,
            AVG(cache_efficiency) as avg_efficiency,
            SUM(response_size) / 1024 / 1024 as size_mb
        ')
        ->where('created_at', '>=', now()->subDays($days))
        ->groupBy('date', 'api_source')
        ->orderBy('date', 'desc')
        ->get();

        // Calculate daily hit ratios
        $dailyStats = $metrics->groupBy('date')->map(function ($dayMetrics) {
            $totalEntries = $dayMetrics->sum('entries_created');
            $totalHits = $dayMetrics->sum('total_hits');
            $hitRatio = $totalEntries > 0 ? ($totalHits / ($totalEntries + $totalHits)) * 100 : 0;

            return [
                'date' => $dayMetrics->first()->date,
                'entries_created' => $totalEntries,
                'total_hits' => $totalHits,
                'hit_ratio' => round($hitRatio, 2),
                'avg_efficiency' => round($dayMetrics->avg('avg_efficiency'), 2),
                'size_mb' => round($dayMetrics->sum('size_mb'), 2),
                'by_source' => $dayMetrics->keyBy('api_source'),
            ];
        })->values();

        return response()->json([
            'daily_stats' => $dailyStats,
            'summary' => [
                'total_entries' => $metrics->sum('entries_created'),
                'total_hits' => $metrics->sum('total_hits'),
                'total_size_mb' => round($metrics->sum('size_mb'), 2),
                'period_days' => $days,
            ],
        ]);
    }

    /**
     * Export cache data for analysis.
     */
    public function export(Request $request): JsonResponse
    {
        $request->validate([
            'format' => 'required|in:json,csv',
            'api_source' => 'sometimes|string',
            'limit' => 'sometimes|integer|min:1|max:10000',
        ]);

        $query = ApiCache::query();

        if ($request->filled('api_source')) {
            $query->where('api_source', $request->api_source);
        }

        $entries = $query->limit($request->get('limit', 1000))
            ->orderBy('created_at', 'desc')
            ->get(['api_source', 'resource_type', 'resource_id', 'hit_count', 
                   'cache_efficiency', 'response_size', 'expires_at', 'created_at']);

        if ($request->format === 'csv') {
            $filename = 'cache_export_' . now()->format('Y-m-d_H-i-s') . '.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ];

            $csv = "API Source,Resource Type,Resource ID,Hit Count,Cache Efficiency,Response Size (bytes),Expires At,Created At\n";
            foreach ($entries as $entry) {
                $csv .= implode(',', [
                    $entry->api_source,
                    $entry->resource_type,
                    $entry->resource_id ?? '',
                    $entry->hit_count,
                    $entry->cache_efficiency,
                    $entry->response_size,
                    $entry->expires_at->toISOString(),
                    $entry->created_at->toISOString(),
                ]) . "\n";
            }

            return response($csv, 200, $headers);
        }

        return response()->json([
            'format' => 'json',
            'exported_at' => now()->toISOString(),
            'count' => $entries->count(),
            'data' => $entries,
        ]);
    }

    /**
     * Test cache service health and connectivity.
     */
    public function healthCheck(): JsonResponse
    {
        $checks = [
            'database' => false,
            'api_cache_table' => false,
            'indexes' => false,
            'services' => [
                'coingecko' => false,
                'etherscan' => false,
                'moralis' => false,
            ],
        ];

        try {
            // Test database connection
            \DB::connection()->getPdo();
            $checks['database'] = true;

            // Test api_cache table access
            ApiCache::count();
            $checks['api_cache_table'] = true;

            // Test indexes (check if GIN indexes exist)
            $indexes = \DB::select("
                SELECT indexname 
                FROM pg_indexes 
                WHERE tablename = 'api_cache' 
                AND indexname LIKE '%gin%'
            ");
            $checks['indexes'] = count($indexes) >= 2;

            // Test external API configurations
            $checks['services']['coingecko'] = !empty(config('services.coingecko.api_key', 'free'));
            $checks['services']['etherscan'] = !empty(config('services.etherscan.api_key'));
            $checks['services']['moralis'] = !empty(config('services.moralis.api_key'));

        } catch (\Exception $e) {
            // Specific checks will remain false
        }

        $overallHealth = $checks['database'] && $checks['api_cache_table'];

        return response()->json([
            'healthy' => $overallHealth,
            'checks' => $checks,
            'timestamp' => now()->toISOString(),
            'cache_stats' => $this->cacheService->healthCheck(),
        ]);
    }
}