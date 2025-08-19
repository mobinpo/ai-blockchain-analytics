<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Contracts\SecurityAnalyticsRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

final class AnalyticsController extends Controller
{
    public function __construct(
        private readonly SecurityAnalyticsRepositoryInterface $securityAnalytics
    ) {}

    /**
     * Get risk matrix data for the analytics dashboard
     */
    public function getRiskMatrix(Request $request): JsonResponse
    {
        try {
            $matrix = $this->securityAnalytics->getRiskMatrix();
            
            return response()->json([
                'success' => true,
                'matrix' => $matrix,
                'lastUpdated' => Carbon::now()->format('Y-m-d H:i:s'),
                'timestamp' => Carbon::now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch risk matrix data',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get security trend data for the chart
     */
    public function getSecurityTrend(Request $request): JsonResponse
    {
        try {
            $period = $request->query('period', '7D');
            $trendData = $this->securityAnalytics->getSecurityTrends($period);
            
            return response()->json([
                'success' => true,
                'period' => $period,
                'critical' => $trendData['critical'],
                'high' => $trendData['high'],
                'medium' => $trendData['medium'],
                'timestamp' => Carbon::now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch security trend data',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
