<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CoinGeckoService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

final class SentimentPriceTimelineController extends Controller
{
    public function __construct(
        private readonly CoinGeckoService $coingeckoService
    ) {}

    /**
     * Get sentiment vs price timeline data
     */
    public function getData(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'coin' => 'required|string|max:50',
                'range' => 'nullable|string|in:7d,30d,90d,1y',
                'currency' => 'nullable|string|max:10',
                'platforms' => 'nullable|array',
                'platforms.*' => 'string|in:twitter,reddit,telegram,discord',
                'include_volume' => 'nullable|in:true,false,1,0'
            ]);

            $coinId = $validated['coin'];
            $range = $validated['range'] ?? '30d';
            $currency = $validated['currency'] ?? 'usd';
            $platforms = $validated['platforms'] ?? ['twitter', 'reddit'];
            $includeVolume = in_array($validated['include_volume'] ?? false, ['true', '1', 1, true], true);

            // Convert range to days
            $days = $this->convertRangeToDays($range);

            $result = $this->coingeckoService->getSentimentPriceTimelineData(
                $coinId,
                $days,
                $currency,
                $platforms
            );

            // Enhanced response format for Vue component
            if ($result['success']) {
                $timelineData = $this->formatTimelineDataForVue($result['data'], $coinId, $currency, $includeVolume);
                
                return response()->json([
                    'success' => true,
                    'data' => $timelineData,
                    'metadata' => [
                        'coin' => [
                            'id' => $coinId,
                            'name' => $this->getCoinName($coinId),
                            'symbol' => $this->getCoinSymbol($coinId)
                        ],
                        'currency' => $currency,
                        'range' => $range,
                        'days' => $days,
                        'platforms' => $platforms,
                        'start_date' => now()->subDays($days)->toDateString(),
                        'end_date' => now()->toDateString(),
                        'is_demo' => false,
                        'include_volume' => $includeVolume
                    ],
                    'stats' => $this->calculateEnhancedStats($timelineData),
                    'generated_at' => now()->toISOString()
                ]);
            }

            // Fallback to demo data if real data fails
            return $this->getDemoData($request);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            \Log::error('Sentiment price timeline error', [
                'coin' => $request->get('coin'),
                'error' => $e->getMessage()
            ]);

            // Return demo data as fallback
            return $this->getDemoData($request);
        }
    }

    /**
     * Get demo data for testing without real API calls
     */
    public function getDemoData(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'coin' => 'nullable|string|max:50',
                'range' => 'nullable|string|in:7d,30d,90d,1y',
                'currency' => 'nullable|string|max:10',
                'include_volume' => 'nullable|in:true,false,1,0'
            ]);

            $coinId = $validated['coin'] ?? 'bitcoin';
            $range = $validated['range'] ?? '30d';
            $currency = $validated['currency'] ?? 'usd';
            $includeVolume = in_array($validated['include_volume'] ?? false, ['true', '1', 1, true], true);

            $days = $this->convertRangeToDays($range);
            $demoData = $this->generateDemoData($coinId, $days, $currency, $includeVolume);

            return response()->json([
                'success' => true,
                'data' => $demoData,
                'metadata' => [
                    'coin' => [
                        'id' => $coinId,
                        'name' => $this->getCoinName($coinId),
                        'symbol' => $this->getCoinSymbol($coinId)
                    ],
                    'currency' => $currency,
                    'range' => $range,
                    'days' => $days,
                    'platforms' => ['demo'],
                    'start_date' => Carbon::now()->subDays($days)->toDateString(),
                    'end_date' => Carbon::now()->toDateString(),
                    'is_demo' => true,
                    'include_volume' => $includeVolume
                ],
                'stats' => $this->calculateEnhancedStats($demoData),
                'generated_at' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate demo data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate realistic demo data
     */
    private function generateDemoData(string $coinId, int $days, string $currency, bool $includeVolume = false): array
    {
        $timelineData = [];
        
        $basePrice = $this->getBasePriceForCoin($coinId);
        $currentPrice = $basePrice;
        $baseVolume = $basePrice * 1000000; // Volume roughly correlates with price
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            
            // Generate correlated sentiment and price data
            $marketTrend = sin(($days - $i) * 0.1) * 0.3; // Overall trend
            $randomFactor = (mt_rand() / mt_getrandmax() - 0.5) * 0.4; // Random noise
            $sentimentScore = max(0, min(100, 50 + ((max(-1, min(1, $marketTrend + $randomFactor))) * 50))); // 0-100 scale
            
            // Price follows sentiment with some lag and noise
            $priceChange = (($sentimentScore - 50) / 50 * 0.05) + ((mt_rand() / mt_getrandmax() - 0.5) * 0.02);
            $currentPrice = $currentPrice * (1 + $priceChange);
            
            // Volume inversely correlates with price stability
            $volumeMultiplier = 1 + (abs($priceChange) * 10);
            $currentVolume = $baseVolume * $volumeMultiplier * (0.5 + mt_rand() / mt_getrandmax());
            
            $dataPoint = [
                'timestamp' => $date->toISOString(),
                'price' => round($currentPrice, $currentPrice >= 1 ? 2 : 6),
                'sentiment' => round($sentimentScore, 2)
            ];
            
            if ($includeVolume) {
                $dataPoint['volume'] = round($currentVolume, 0);
            }
            
            $timelineData[] = $dataPoint;
        }
        
        return $timelineData;
    }

    /**
     * Get base price for demo data generation
     */
    private function getBasePriceForCoin(string $coinId): float
    {
        $basePrices = [
            'bitcoin' => 45000,
            'ethereum' => 3200,
            'cardano' => 0.85,
            'solana' => 120,
            'polygon' => 1.2,
            'polkadot' => 8.5,
            'chainlink' => 15.0,
            'binancecoin' => 420,
        ];

        return $basePrices[$coinId] ?? 100;
    }

    /**
     * Calculate simple correlation coefficient
     */
    private function calculateSimpleCorrelation(array $x, array $y): float
    {
        $n = count($x);
        if ($n < 2) return 0;

        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0;
        $sumXX = 0;
        $sumYY = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumXX += $x[$i] * $x[$i];
            $sumYY += $y[$i] * $y[$i];
        }

        $numerator = ($n * $sumXY) - ($sumX * $sumY);
        $denominator = sqrt((($n * $sumXX) - ($sumX * $sumX)) * (($n * $sumYY) - ($sumY * $sumY)));

        if ($denominator == 0) return 0;

        return round($numerator / $denominator, 3);
    }

    /**
     * Get available coins for the timeline
     */
    public function getAvailableCoins(): JsonResponse
    {
        $coins = [
            ['id' => 'bitcoin', 'name' => 'Bitcoin', 'symbol' => 'BTC'],
            ['id' => 'ethereum', 'name' => 'Ethereum', 'symbol' => 'ETH'],
            ['id' => 'cardano', 'name' => 'Cardano', 'symbol' => 'ADA'],
            ['id' => 'solana', 'name' => 'Solana', 'symbol' => 'SOL'],
            ['id' => 'polygon', 'name' => 'Polygon', 'symbol' => 'MATIC'],
            ['id' => 'polkadot', 'name' => 'Polkadot', 'symbol' => 'DOT'],
            ['id' => 'chainlink', 'name' => 'Chainlink', 'symbol' => 'LINK'],
            ['id' => 'binancecoin', 'name' => 'BNB', 'symbol' => 'BNB'],
            ['id' => 'avalanche-2', 'name' => 'Avalanche', 'symbol' => 'AVAX'],
        ];

        return response()->json([
            'success' => true,
            'coins' => $coins
        ]);
    }

    /**
     * Format sentiment data for Vue component
     */
    private function formatSentimentData(array $data): array
    {
        return array_map(function ($item) {
            return [
                'date' => $item['date'] ?? $item['x'] ?? date('Y-m-d'),
                'sentiment' => $item['sentiment'] ?? $item['y'] ?? 0
            ];
        }, $data);
    }

    /**
     * Format price data for Vue component
     */
    private function formatPriceData(array $data, bool $includeVolume = false): array
    {
        return array_map(function ($item) use ($includeVolume) {
            $formatted = [
                'date' => $item['date'] ?? $item['x'] ?? date('Y-m-d'),
                'price' => $item['price'] ?? $item['y'] ?? 0
            ];
            
            if ($includeVolume && isset($item['volume'])) {
                $formatted['volume'] = $item['volume'];
            }
            
            return $formatted;
        }, $data);
    }

    /**
     * Get coin symbol from coin ID
     */
    private function getCoinSymbol(string $coinId): string
    {
        $symbols = [
            'bitcoin' => 'BTC',
            'ethereum' => 'ETH',
            'cardano' => 'ADA',
            'solana' => 'SOL',
            'polygon' => 'MATIC',
            'polkadot' => 'DOT',
            'chainlink' => 'LINK',
            'binancecoin' => 'BNB',
            'avalanche-2' => 'AVAX',
        ];

        return $symbols[$coinId] ?? strtoupper($coinId);
    }

    /**
     * Get coin name from coin ID
     */
    private function getCoinName(string $coinId): string
    {
        $names = [
            'bitcoin' => 'Bitcoin',
            'ethereum' => 'Ethereum',
            'cardano' => 'Cardano',
            'solana' => 'Solana',
            'polygon' => 'Polygon',
            'polkadot' => 'Polkadot',
            'chainlink' => 'Chainlink',
            'binancecoin' => 'BNB',
            'avalanche-2' => 'Avalanche',
        ];

        return $names[$coinId] ?? ucfirst($coinId);
    }

    /**
     * Convert range string to days
     */
    private function convertRangeToDays(string $range): int
    {
        return match($range) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            '1y' => 365,
            default => 30
        };
    }

    /**
     * Format timeline data for Vue component
     */
    private function formatTimelineDataForVue(array $data, string $coinId, string $currency, bool $includeVolume = false): array
    {
        if (empty($data)) {
            return [];
        }

        // If data comes from CoinGeckoService with separate arrays
        if (isset($data['sentiment']) && isset($data['price'])) {
            $formatted = [];
            $sentimentData = $data['sentiment'];
            $priceData = $data['price'];

            // Combine the data points
            for ($i = 0; $i < min(count($sentimentData), count($priceData)); $i++) {
                $dataPoint = [
                    'timestamp' => isset($sentimentData[$i]['x']) ? 
                        (new Carbon($sentimentData[$i]['x']))->toISOString() : 
                        now()->subDays(count($sentimentData) - $i)->toISOString(),
                    'price' => $priceData[$i]['y'] ?? 0,
                    'sentiment' => $sentimentData[$i]['y'] ?? 50
                ];

                if ($includeVolume && isset($priceData[$i]['volume'])) {
                    $dataPoint['volume'] = $priceData[$i]['volume'];
                }

                $formatted[] = $dataPoint;
            }

            return $formatted;
        }

        // If data is already in the expected format (from CoinGeckoService)
        // Convert CoinGecko format to Vue component format
        $formatted = [];
        foreach ($data as $item) {
            $formatted[] = [
                'timestamp' => $item['timestamp'] ?? $item['date'] ?? now()->toISOString(),
                'price' => $item['price'] ?? 0,
                'sentiment' => $item['sentiment_score'] ?? $item['sentiment'] ?? 0,
                'volume' => $includeVolume && isset($item['social_volume']) ? $item['social_volume'] : null
            ];
        }
        
        return $formatted;
    }

    /**
     * Calculate enhanced statistics for the timeline data
     */
    private function calculateEnhancedStats(array $timelineData): array
    {
        if (empty($timelineData)) {
            return [
                'price_stats' => ['current' => 0, 'change' => 0, 'high' => 0, 'low' => 0],
                'sentiment_stats' => ['current' => 0, 'average' => 0, 'high' => 0, 'low' => 0],
                'correlation_stats' => ['coefficient' => 0, 'strength' => 'No data', 'rSquared' => 0, 'dataPoints' => 0]
            ];
        }

        $prices = array_column($timelineData, 'price');
        $sentiments = array_column($timelineData, 'sentiment');

        // Check if we have valid data arrays
        if (empty($prices) || empty($sentiments)) {
            return [
                'price_stats' => ['current' => 0, 'change' => 0, 'high' => 0, 'low' => 0],
                'sentiment_stats' => ['current' => 0, 'average' => 0, 'high' => 0, 'low' => 0],
                'correlation_stats' => ['coefficient' => 0, 'strength' => 'No data', 'rSquared' => 0, 'dataPoints' => 0]
            ];
        }

        // Price statistics
        $currentPrice = end($prices);
        $firstPrice = $prices[0];
        $priceChange = $firstPrice > 0 ? (($currentPrice - $firstPrice) / $firstPrice) * 100 : 0;

        $priceStats = [
            'current' => $currentPrice,
            'change' => round($priceChange, 2),
            'high' => max($prices),
            'low' => min($prices)
        ];

        // Sentiment statistics - safe division
        $currentSentiment = end($sentiments);
        $sentimentCount = count($sentiments);
        $avgSentiment = $sentimentCount > 0 ? array_sum($sentiments) / $sentimentCount : 0;

        $sentimentStats = [
            'current' => $currentSentiment,
            'average' => round($avgSentiment, 2),
            'high' => max($sentiments),
            'low' => min($sentiments)
        ];

        // Correlation statistics
        $correlation = $this->calculateSimpleCorrelation($sentiments, $prices);
        $correlationStats = [
            'coefficient' => $correlation,
            'strength' => $this->getCorrelationStrength(abs($correlation)),
            'rSquared' => round($correlation * $correlation, 3),
            'dataPoints' => count($timelineData)
        ];

        return [
            'price_stats' => $priceStats,
            'sentiment_stats' => $sentimentStats,
            'correlation_stats' => $correlationStats
        ];
    }

    /**
     * Get correlation strength description
     */
    private function getCorrelationStrength(float $absCorrelation): string
    {
        if ($absCorrelation >= 0.8) return 'Very Strong';
        if ($absCorrelation >= 0.6) return 'Strong';
        if ($absCorrelation >= 0.4) return 'Moderate';
        if ($absCorrelation >= 0.2) return 'Weak';
        return 'Very Weak';
    }

    /**
     * Calculate price volatility
     */
    private function calculateVolatility(array $prices): float
    {
        if (count($prices) < 2) return 0;

        $returns = [];
        for ($i = 1; $i < count($prices); $i++) {
            if ($prices[$i - 1] > 0) {
                $returns[] = log($prices[$i] / $prices[$i - 1]);
            }
        }

        if (empty($returns)) return 0;

        $mean = array_sum($returns) / count($returns);
        $variance = array_sum(array_map(function ($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $returns)) / count($returns);

        return sqrt($variance * 252); // Annualized volatility
    }
}