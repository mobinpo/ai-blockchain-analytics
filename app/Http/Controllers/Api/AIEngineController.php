<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

final class AIEngineController extends Controller
{
    /**
     * Get AI engine components status
     */
    public function getComponentsStatus(Request $request): JsonResponse
    {
        try {
            $components = $this->generateComponentsStatus();
            
            return response()->json([
                'success' => true,
                'components' => $components,
                'aiComponents' => $components, // Alternative key for flexibility
                'systemStatus' => $this->getOverallSystemStatus($components),
                'lastUpdated' => Carbon::now()->format('Y-m-d H:i:s'),
                'timestamp' => Carbon::now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch AI components status',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Generate realistic AI components status data
     */
    private function generateComponentsStatus(): array
    {
        $baseComponents = [
            [
                'name' => 'Vulnerability Scanner',
                'description' => 'OWASP security analysis engine',
                'service' => 'vulnerability_scanner',
                'version' => '2.1.4'
            ],
            [
                'name' => 'Sentiment Analyzer',
                'description' => 'Social media sentiment processing',
                'service' => 'sentiment_analyzer',
                'version' => '1.8.2'
            ],
            [
                'name' => 'Pattern Detector',
                'description' => 'Anomaly detection and pattern recognition',
                'service' => 'pattern_detector',
                'version' => '3.0.1'
            ],
            [
                'name' => 'Price Correlator',
                'description' => 'Market data integration and correlation',
                'service' => 'price_correlator',
                'version' => '1.5.7'
            ],
            [
                'name' => 'Blockchain Parser',
                'description' => 'Multi-chain data ingestion and processing',
                'service' => 'blockchain_parser',
                'version' => '2.3.0'
            ],
            [
                'name' => 'Smart Contract Auditor',
                'description' => 'Automated smart contract security auditing',
                'service' => 'contract_auditor',
                'version' => '1.9.3'
            ],
            [
                'name' => 'NLP Processor',
                'description' => 'Natural language processing for social sentiment',
                'service' => 'nlp_processor',
                'version' => '2.4.1'
            ],
            [
                'name' => 'Risk Assessment Engine',
                'description' => 'Advanced risk scoring and assessment',
                'service' => 'risk_engine',
                'version' => '1.7.8'
            ]
        ];

        $components = [];
        foreach ($baseComponents as $component) {
            $status = $this->generateComponentStatus();
            $components[] = [
                ...$component,
                'status' => $status['status'],
                'load' => $status['load'],
                'uptime' => $status['uptime'],
                'lastHealthCheck' => $status['lastHealthCheck'],
                'responseTime' => $status['responseTime'],
                'errorRate' => $status['errorRate'],
                'throughput' => $status['throughput']
            ];
        }

        return $components;
    }

    /**
     * Generate realistic status for a single component
     */
    private function generateComponentStatus(): array
    {
        // Weighted status distribution (healthy is most common)
        $statusOptions = [
            'healthy' => 75,  // 75% chance
            'warning' => 15,  // 15% chance
            'degraded' => 8,  // 8% chance
            'error' => 2      // 2% chance
        ];

        $status = $this->weightedRandomChoice($statusOptions);
        
        // Adjust other metrics based on status
        switch ($status) {
            case 'healthy':
                $load = rand(20, 70);
                $uptime = rand(9800, 9999) / 100; // 98-99.99%
                $errorRate = rand(0, 5) / 1000; // 0-0.5%
                $responseTime = rand(50, 200);
                break;
            case 'warning':
                $load = rand(70, 90);
                $uptime = rand(9500, 9800) / 100; // 95-98%
                $errorRate = rand(5, 20) / 1000; // 0.5-2%
                $responseTime = rand(200, 500);
                break;
            case 'degraded':
                $load = rand(85, 98);
                $uptime = rand(9000, 9500) / 100; // 90-95%
                $errorRate = rand(20, 50) / 1000; // 2-5%
                $responseTime = rand(500, 1000);
                break;
            case 'error':
                $load = rand(95, 100);
                $uptime = rand(8000, 9000) / 100; // 80-90%
                $errorRate = rand(50, 100) / 1000; // 5-10%
                $responseTime = rand(1000, 3000);
                break;
            default:
                $load = 0;
                $uptime = 0;
                $errorRate = 1;
                $responseTime = 0;
        }

        return [
            'status' => $status,
            'load' => $load,
            'uptime' => $uptime,
            'lastHealthCheck' => Carbon::now()->subMinutes(rand(1, 5))->format('H:i:s'),
            'responseTime' => $responseTime,
            'errorRate' => $errorRate,
            'throughput' => rand(50, 500) // requests per minute
        ];
    }

    /**
     * Get overall system status based on components
     */
    private function getOverallSystemStatus(array $components): array
    {
        $statusCounts = [
            'healthy' => 0,
            'warning' => 0,
            'degraded' => 0,
            'error' => 0,
            'offline' => 0
        ];

        $totalLoad = 0;
        $totalUptime = 0;
        $totalErrorRate = 0;

        foreach ($components as $component) {
            $statusCounts[$component['status']]++;
            $totalLoad += $component['load'];
            $totalUptime += $component['uptime'];
            $totalErrorRate += $component['errorRate'];
        }

        $componentCount = count($components);
        $overallStatus = 'healthy';

        if ($statusCounts['error'] > 0 || $statusCounts['offline'] > 0) {
            $overallStatus = 'critical';
        } elseif ($statusCounts['degraded'] > 0) {
            $overallStatus = 'degraded';
        } elseif ($statusCounts['warning'] > 0) {
            $overallStatus = 'warning';
        }

        return [
            'overall' => $overallStatus,
            'healthyComponents' => $statusCounts['healthy'],
            'totalComponents' => $componentCount,
            'averageLoad' => round($totalLoad / $componentCount, 1),
            'averageUptime' => round($totalUptime / $componentCount, 2),
            'averageErrorRate' => round($totalErrorRate / $componentCount, 4),
            'lastUpdated' => Carbon::now()->format('H:i:s')
        ];
    }

    /**
     * Weighted random choice helper
     */
    private function weightedRandomChoice(array $weights): string
    {
        $totalWeight = array_sum($weights);
        $random = rand(1, $totalWeight);
        
        $currentWeight = 0;
        foreach ($weights as $choice => $weight) {
            $currentWeight += $weight;
            if ($random <= $currentWeight) {
                return $choice;
            }
        }
        
        return array_key_first($weights); // Fallback
    }
}
