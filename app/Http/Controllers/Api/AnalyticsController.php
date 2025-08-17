<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

final class AnalyticsController extends Controller
{
    /**
     * Get risk matrix data for the analytics dashboard
     */
    public function getRiskMatrix(Request $request): JsonResponse
    {
        try {
            // Generate realistic risk matrix data
            // In production, this would fetch from your database/analysis engine
            $matrix = $this->generateRiskMatrixData();
            
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
            $trendData = $this->generateSecurityTrendData($period);
            
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

    /**
     * Generate realistic risk matrix data based on actual security analysis patterns
     */
    private function generateRiskMatrixData(): array
    {
        // Risk matrix structure: [impact][probability]
        // Impact levels: Very Low(0), Low(1), Medium(2), High(3), Very High(4)
        // Probability levels: Very Low(0), Low(1), Medium(2), High(3), Very High(4)
        
        return [
            // Very Low Impact
            [
                ['count' => 12, 'examples' => ['Code style warnings', 'Documentation gaps', 'Minor naming conventions']],
                ['count' => 8, 'examples' => ['Optional optimizations', 'Unused imports']],
                ['count' => 3, 'examples' => ['Low-impact performance tweaks']],
                ['count' => 1, 'examples' => ['Rare edge case handling']],
                ['count' => 0, 'examples' => []]
            ],
            // Low Impact
            [
                ['count' => 15, 'examples' => ['Input validation suggestions', 'Event emission improvements']],
                ['count' => 22, 'examples' => ['Gas optimization hints', 'Function visibility improvements']],
                ['count' => 9, 'examples' => ['State variable optimizations']],
                ['count' => 4, 'examples' => ['Minor access control improvements']],
                ['count' => 2, 'examples' => ['Low-risk logic adjustments']]
            ],
            // Medium Impact
            [
                ['count' => 6, 'examples' => ['Moderate gas inefficiencies', 'Storage layout optimizations']],
                ['count' => 11, 'examples' => ['Function complexity warnings', 'Contract size considerations']],
                ['count' => 18, 'examples' => ['Visibility modifier issues', 'Error handling improvements']],
                ['count' => 8, 'examples' => ['State management concerns']],
                ['count' => 5, 'examples' => ['Medium-risk vulnerabilities']]
            ],
            // High Impact
            [
                ['count' => 2, 'examples' => ['Potential front-running risks']],
                ['count' => 5, 'examples' => ['Access control weaknesses', 'Privilege escalation risks']],
                ['count' => 12, 'examples' => ['State manipulation vulnerabilities', 'Logic bomb potential']],
                ['count' => 15, 'examples' => ['High-value attack vectors', 'Oracle manipulation risks']],
                ['count' => 9, 'examples' => ['Critical business logic flaws']]
            ],
            // Very High Impact
            [
                ['count' => 0, 'examples' => []],
                ['count' => 1, 'examples' => ['Potential fund lock scenarios']],
                ['count' => 3, 'examples' => ['Reentrancy vulnerabilities', 'Integer overflow/underflow']],
                ['count' => 7, 'examples' => ['Critical exploit vectors', 'Unauthorized fund access']],
                ['count' => 4, 'examples' => ['Severe security breaches', 'Total contract compromise']]
            ]
        ];
    }

    /**
     * Generate security trend data based on period
     */
    private function generateSecurityTrendData(string $period): array
    {
        $days = match($period) {
            '24H' => 1,
            '7D' => 7,
            '30D' => 30,
            '90D' => 90,
            default => 7
        };

        $points = min($days, 7); // Max 7 points for chart display
        $critical = [];
        $high = [];
        $medium = [];

        for ($i = 0; $i < $points; $i++) {
            $x = 50 + ($i * 50);
            
            // Generate realistic trend data based on period
            $criticalValue = rand(0, 6);
            $highValue = rand(2, 15);
            $mediumValue = rand(5, 25);
            
            $critical[] = [
                'x' => $x,
                'y' => 150 - ($criticalValue * 15),
                'value' => $criticalValue
            ];
            
            $high[] = [
                'x' => $x,
                'y' => 150 - ($highValue * 8),
                'value' => $highValue
            ];
            
            $medium[] = [
                'x' => $x,
                'y' => 150 - ($mediumValue * 4),
                'value' => $mediumValue
            ];
        }

        return [
            'critical' => $critical,
            'high' => $high,
            'medium' => $medium
        ];
    }
}
