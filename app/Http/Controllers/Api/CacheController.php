<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PostgresCacheService;
use App\Models\ApiCache;
use App\Models\DemoCacheData;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

final class CacheController extends Controller
{
    public function __construct(
        protected PostgresCacheService $cache
    ) {}

    /**
     * Get cache statistics.
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = $this->cache->getStats();
            
            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Cache statistics retrieved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve cache statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Warm cache with demo data.
     */
    public function warm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service' => 'sometimes|string|in:all,coingecko,demo',
            'demo_only' => 'sometimes|boolean',
            'force' => 'sometimes|boolean',
        ]);

        $service = $validated['service'] ?? 'all';
        $demoOnly = $validated['demo_only'] ?? false;
        $force = $validated['force'] ?? false;

        try {
            Log::info('API cache warming started', [
                'service' => $service,
                'demo_only' => $demoOnly,
                'force' => $force,
            ]);

            // Warm demo data
            if ($service === 'all' || $service === 'demo') {
                DemoCacheData::initializeDemoData();
                $this->cache->warmDemoCache();
            }

            // Warm real API data (if not demo-only)
            if (!$demoOnly && ($service === 'all' || $service === 'coingecko')) {
                // Note: This would require CoinGeckoService to be injected
                // For now, we'll just warm the demo cache portion
                $this->cache->warmDemoCache();
            }

            $stats = $this->cache->getStats();

            return response()->json([
                'success' => true,
                'message' => 'Cache warmed successfully',
                'data' => [
                    'service' => $service,
                    'demo_only' => $demoOnly,
                    'force' => $force,
                    'stats' => $stats,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Cache warming failed', [
                'service' => $service,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Cache warming failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clean up expired cache entries.
     */
    public function cleanup(): JsonResponse
    {
        try {
            $results = $this->cache->cleanup();

            return response()->json([
                'success' => true,
                'message' => 'Cache cleanup completed',
                'data' => $results,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cache cleanup failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear cache for specific service or all.
     */
    public function clear(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service' => 'sometimes|string',
            'confirm' => 'required|boolean|accepted',
        ]);

        if (!$validated['confirm']) {
            return response()->json([
                'success' => false,
                'message' => 'Cache clear operation requires confirmation',
            ], 400);
        }

        try {
            if (isset($validated['service'])) {
                $deleted = $this->cache->clearService($validated['service']);
                $message = "Cleared {$deleted} entries for service: {$validated['service']}";
            } else {
                $apiDeleted = ApiCache::query()->delete();
                $demoDeleted = DemoCacheData::query()->delete();
                $deleted = $apiDeleted + $demoDeleted;
                $message = "Cleared all cache data ({$apiDeleted} API, {$demoDeleted} demo entries)";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => ['deleted_count' => $deleted],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cache clear failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get specific cache entry.
     */
    public function get(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service' => 'required|string',
            'endpoint' => 'required|string',
            'params' => 'sometimes|array',
        ]);

        try {
            $data = $this->cache->get(
                $validated['service'],
                $validated['endpoint'],
                $validated['params'] ?? []
            );

            return response()->json([
                'success' => true,
                'data' => $data,
                'cached' => $data !== null,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve cache entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store data in cache.
     */
    public function put(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service' => 'required|string',
            'endpoint' => 'required|string',
            'params' => 'sometimes|array',
            'data' => 'required',
            'ttl' => 'sometimes|integer|min:1',
            'is_demo' => 'sometimes|boolean',
        ]);

        try {
            $this->cache->put(
                $validated['service'],
                $validated['endpoint'],
                $validated['params'] ?? [],
                $validated['data'],
                $validated['ttl'] ?? null,
                $validated['is_demo'] ?? false
            );

            return response()->json([
                'success' => true,
                'message' => 'Data cached successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cache data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Invalidate specific cache entry.
     */
    public function invalidate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service' => 'required|string',
            'endpoint' => 'required|string',
            'params' => 'sometimes|array',
        ]);

        try {
            $success = $this->cache->forget(
                $validated['service'],
                $validated['endpoint'],
                $validated['params'] ?? []
            );

            return response()->json([
                'success' => true,
                'invalidated' => $success,
                'message' => $success ? 'Cache entry invalidated' : 'Cache entry not found',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to invalidate cache entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get demo data.
     */
    public function getDemoData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'data_type' => 'sometimes|string',
            'identifier' => 'sometimes|string',
        ]);

        try {
            if (isset($validated['data_type']) && isset($validated['identifier'])) {
                // Get specific demo data
                $data = DemoCacheData::retrieve($validated['data_type'], $validated['identifier']);
                $result = $data ? $data->formatted_data : null;
            } elseif (isset($validated['data_type'])) {
                // Get all data for type
                $result = DemoCacheData::getByType($validated['data_type']);
            } else {
                // Get all demo data
                $result = DemoCacheData::getStats();
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve demo data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Initialize demo data for North Star presentation.
     */
    public function initializeDemoData(): JsonResponse
    {
        try {
            DemoCacheData::initializeDemoData();
            $stats = DemoCacheData::getStats();

            return response()->json([
                'success' => true,
                'message' => 'Demo data initialized successfully',
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize demo data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}