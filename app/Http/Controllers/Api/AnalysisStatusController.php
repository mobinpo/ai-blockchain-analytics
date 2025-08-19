<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

final class AnalysisStatusController extends Controller
{
    private AnalysisService $analysisService;

    public function __construct(AnalysisService $analysisService)
    {
        $this->analysisService = $analysisService;
    }

    /**
     * Get current analysis status - canonical endpoint with no-store headers
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $status = $this->analysisService->status();

            return response()->json([
                'success' => true,
                'status' => $status,
                'timestamp' => Carbon::now()->toISOString()
            ])->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => 'Thu, 01 Jan 1970 00:00:00 GMT'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch analysis status',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error',
                'status' => [
                    'state' => 'error',
                    'activeCount' => 0,
                    'queueCount' => 0,
                    'hasActiveAnalyses' => false,
                    'hasQueuedAnalyses' => false,
                    'isHealthy' => false,
                    'lastActivity' => null,
                    'summary' => 'System error - unable to determine status'
                ]
            ], 500)->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => 'Thu, 01 Jan 1970 00:00:00 GMT'
            ]);
        }
    }

    /**
     * Clear analysis status cache
     */
    public function clearCache(Request $request): JsonResponse
    {
        try {
            $this->analysisService->clearStatusCache();

            return response()->json([
                'success' => true,
                'message' => 'Analysis status cache cleared'
            ])->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}