<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CoinGeckoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

final class SentimentTimelineController extends Controller
{
    public function __construct(
        private readonly CoinGeckoService $coinGeckoService
    ) {}

    /**
     * Get sentiment timeline data with optional price correlation
     */
    public function timeline(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'sometimes|string|in:bitcoin,ethereum,chainlink,uniswap',
            'timeframe' => 'sometimes|string|in:7d,30d,90d',
            'contract_address' => 'sometimes|string|max:42',
            'include_price' => 'sometimes|boolean',
            'granularity' => 'sometimes|string|in:hourly,daily,weekly'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid parameters',
                'errors' => $validator->errors()
            ], 422);
        }

        $token = $request->input('token', 'ethereum');
        $timeframe = $request->input('timeframe', '30d');
        $contractAddress = $request->input('contract_address');
        $includePrice = $request->input('include_price', true);
        $granularity = $request->input('granularity', 'daily');

        try {
            $cacheKey = "sentiment_timeline_{$token}_{$timeframe}_{$granularity}_" . md5($contractAddress ?? '');
            
            $data = Cache::remember($cacheKey, 300, function () use ($token, $timeframe, $contractAddress, $includePrice, $granularity) {
                return $this->generateTimelineData($token, $timeframe, $contractAddress, $includePrice, $granularity);
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'metadata' => [
                    'token' => $token,
                    'timeframe' => $timeframe,
                    'granularity' => $granularity,
                    'data_points' => count($data['sentiment_data']),
                    'cache_key' => $cacheKey,
                    'generated_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate timeline data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current sentiment vs price correlation
     */
    public function correlation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'period' => 'sometimes|integer|min:1|max:365'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $token = $request->input('token');
        $period = $request->input('period', 30);

        try {
            $correlation = $this->calculateSentimentPriceCorrelation($token, $period);

            return response()->json([
                'success' => true,
                'data' => [
                    'correlation' => $correlation,
                    'interpretation' => $this->interpretCorrelation($correlation),
                    'period_days' => $period,
                    'token' => $token
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate correlation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sentiment analysis summary for timeframe
     */
    public function summary(Request $request): JsonResponse
    {
        $token = $request->input('token', 'ethereum');
        $timeframe = $request->input('timeframe', '7d');

        try {
            $summary = $this->generateSentimentSummary($token, $timeframe);

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function generateTimelineData(string $token, string $timeframe, ?string $contractAddress, bool $includePrice, string $granularity): array
    {
        $days = (int) str_replace('d', '', $timeframe);
        $startDate = Carbon::now()->subDays($days);
        
        // Generate sentiment data (mock data for now)
        $sentimentData = $this->generateSentimentData($token, $startDate, $days, $granularity);
        
        $result = [
            'sentiment_data' => $sentimentData,
            'statistics' => $this->calculateSentimentStatistics($sentimentData)
        ];

        // Add price data if requested
        if ($includePrice) {
            try {
                $endDate = $startDate->copy();
                $adjustedStartDate = $startDate->copy()->subDays($days);
                $priceData = $this->coinGeckoService->getHistoricalPrices($token, $adjustedStartDate, $endDate);
                $result['price_data'] = $priceData;
                $result['price_statistics'] = $this->calculatePriceStatistics($priceData);
                
                // Calculate correlation
                if (!empty($sentimentData) && !empty($priceData)) {
                    $result['correlation'] = $this->calculateCorrelation($sentimentData, $priceData);
                }
            } catch (\Exception $e) {
                // If price data fails, continue without it
                $result['price_error'] = 'Failed to fetch price data: ' . $e->getMessage();
            }
        }

        return $result;
    }

    private function generateSentimentData(string $token, Carbon $startDate, int $days, string $granularity): array
    {
        $data = [];
        $interval = $this->getIntervalHours($granularity);
        $points = (int) ceil(($days * 24) / $interval);

        for ($i = 0; $i < $points; $i++) {
            $timestamp = $startDate->copy()->addHours($i * $interval);
            
            // Generate realistic sentiment data with some patterns
            $baseTime = $i / $points;
            $sentiment = $this->generateRealisticSentiment($token, $baseTime, $i);
            
            $data[] = [
                'timestamp' => $timestamp->toISOString(),
                'sentiment' => round($sentiment, 3),
                'confidence' => round(0.6 + (rand(0, 40) / 100), 2),
                'volume' => rand(50, 500),
                'sources' => [
                    'twitter' => rand(10, 100),
                    'reddit' => rand(5, 50),
                    'news' => rand(1, 20),
                    'telegram' => rand(20, 150)
                ]
            ];
        }

        return $data;
    }

    private function generateRealisticSentiment(string $token, float $baseTime, int $index): float
    {
        // Base sentiment varies by token
        $baseSentiment = match($token) {
            'bitcoin' => 0.1,
            'ethereum' => 0.15,
            'chainlink' => 0.05,
            'uniswap' => 0.0,
            default => 0.0
        };

        // Add some cyclical patterns
        $cyclical = sin($baseTime * 2 * M_PI) * 0.2;
        $weeklyPattern = sin($baseTime * 14 * M_PI) * 0.1;
        
        // Add random noise
        $noise = (rand(-100, 100) / 100) * 0.15;
        
        // Occasional sentiment spikes
        $spike = (rand(0, 100) < 5) ? (rand(-50, 50) / 100) * 0.3 : 0;
        
        $sentiment = $baseSentiment + $cyclical + $weeklyPattern + $noise + $spike;
        
        // Clamp between -1 and 1
        return max(-1, min(1, $sentiment));
    }

    private function getIntervalHours(string $granularity): int
    {
        return match($granularity) {
            'hourly' => 1,
            'daily' => 24,
            'weekly' => 168,
            default => 24
        };
    }

    private function calculateSentimentStatistics(array $sentimentData): array
    {
        if (empty($sentimentData)) {
            return [];
        }

        $sentiments = array_column($sentimentData, 'sentiment');
        $volumes = array_column($sentimentData, 'volume');

        return [
            'average_sentiment' => round(array_sum($sentiments) / count($sentiments), 3),
            'min_sentiment' => round(min($sentiments), 3),
            'max_sentiment' => round(max($sentiments), 3),
            'sentiment_volatility' => round($this->calculateStandardDeviation($sentiments), 3),
            'total_volume' => array_sum($volumes),
            'average_volume' => round(array_sum($volumes) / count($volumes), 0),
            'positive_periods' => count(array_filter($sentiments, fn($s) => $s > 0)),
            'negative_periods' => count(array_filter($sentiments, fn($s) => $s < 0)),
            'neutral_periods' => count(array_filter($sentiments, fn($s) => abs($s) <= 0.1))
        ];
    }

    private function calculatePriceStatistics(array $priceData): array
    {
        if (empty($priceData)) {
            return [];
        }

        $prices = array_column($priceData, 'price');

        return [
            'current_price' => end($prices),
            'start_price' => reset($prices),
            'min_price' => min($prices),
            'max_price' => max($prices),
            'price_change_percent' => $this->calculatePriceChangePercent($prices),
            'price_volatility' => round($this->calculateStandardDeviation($prices), 2),
            'average_price' => round(array_sum($prices) / count($prices), 2)
        ];
    }

    private function calculateCorrelation(array $sentimentData, array $priceData): array
    {
        $sentiments = array_column($sentimentData, 'sentiment');
        $prices = array_column($priceData, 'price');

        // Align arrays to same length
        $minLength = min(count($sentiments), count($prices));
        $sentiments = array_slice($sentiments, -$minLength);
        $prices = array_slice($prices, -$minLength);

        $correlation = $this->pearsonCorrelation($sentiments, $prices);

        return [
            'value' => round($correlation, 3),
            'strength' => $this->getCorrelationStrength($correlation),
            'interpretation' => $this->interpretCorrelation($correlation),
            'data_points' => $minLength
        ];
    }

    private function calculateSentimentPriceCorrelation(string $token, int $period): float
    {
        // This would typically query actual data from database
        // For now, return a simulated correlation
        $baseCorrelation = match($token) {
            'bitcoin' => 0.3,
            'ethereum' => 0.4,
            'chainlink' => 0.2,
            'uniswap' => 0.1,
            default => 0.2
        };

        // Add some randomness
        $noise = (rand(-20, 20) / 100);
        return max(-1, min(1, $baseCorrelation + $noise));
    }

    private function generateSentimentSummary(string $token, string $timeframe): array
    {
        $days = (int) str_replace('d', '', $timeframe);
        
        return [
            'overall_sentiment' => $this->getOverallSentiment($token),
            'trend' => $this->getSentimentTrend($token, $days),
            'key_events' => $this->getKeyEvents($token, $days),
            'top_sources' => $this->getTopSources($token),
            'sentiment_distribution' => $this->getSentimentDistribution($token, $days),
            'recommendations' => $this->getRecommendations($token, $days)
        ];
    }

    private function getOverallSentiment(string $token): array
    {
        $sentiment = $this->generateRealisticSentiment($token, 0.5, 0);
        
        return [
            'score' => round($sentiment, 2),
            'label' => $this->getSentimentLabel($sentiment),
            'confidence' => round(0.7 + (rand(0, 30) / 100), 2)
        ];
    }

    private function getSentimentTrend(string $token, int $days): string
    {
        $trends = ['bullish', 'bearish', 'neutral', 'volatile'];
        return $trends[array_rand($trends)];
    }

    private function getKeyEvents(string $token, int $days): array
    {
        // Mock key events
        return [
            [
                'date' => Carbon::now()->subDays(rand(1, $days))->format('Y-m-d'),
                'event' => 'Major protocol upgrade announced',
                'impact' => 'positive',
                'sentiment_change' => '+0.3'
            ],
            [
                'date' => Carbon::now()->subDays(rand(1, $days))->format('Y-m-d'),
                'event' => 'Market volatility concerns',
                'impact' => 'negative', 
                'sentiment_change' => '-0.2'
            ]
        ];
    }

    private function getTopSources(string $token): array
    {
        return [
            ['platform' => 'Twitter', 'mentions' => rand(1000, 5000), 'avg_sentiment' => rand(-50, 50) / 100],
            ['platform' => 'Reddit', 'mentions' => rand(500, 2000), 'avg_sentiment' => rand(-50, 50) / 100],
            ['platform' => 'News', 'mentions' => rand(50, 200), 'avg_sentiment' => rand(-50, 50) / 100],
            ['platform' => 'Telegram', 'mentions' => rand(200, 1000), 'avg_sentiment' => rand(-50, 50) / 100]
        ];
    }

    private function getSentimentDistribution(string $token, int $days): array
    {
        return [
            'very_positive' => rand(10, 30),
            'positive' => rand(20, 40),
            'neutral' => rand(15, 35),
            'negative' => rand(10, 25),
            'very_negative' => rand(5, 15)
        ];
    }

    private function getRecommendations(string $token, int $days): array
    {
        return [
            'Monitor upcoming protocol updates and their community reception',
            'Watch for correlation between sentiment spikes and price movements',
            'Pay attention to volume patterns during sentiment extremes'
        ];
    }

    private function pearsonCorrelation(array $x, array $y): float
    {
        if (count($x) !== count($y) || count($x) === 0) {
            return 0;
        }

        $n = count($x);
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

        $numerator = $n * $sumXY - $sumX * $sumY;
        $denominator = sqrt(($n * $sumXX - $sumX * $sumX) * ($n * $sumYY - $sumY * $sumY));

        return $denominator == 0 ? 0 : $numerator / $denominator;
    }

    private function calculateStandardDeviation(array $values): float
    {
        if (count($values) === 0) return 0;

        $mean = array_sum($values) / count($values);
        $squaredDiffs = array_map(fn($x) => pow($x - $mean, 2), $values);
        return sqrt(array_sum($squaredDiffs) / count($values));
    }

    private function calculatePriceChangePercent(array $prices): float
    {
        if (count($prices) < 2) return 0;
        
        $start = reset($prices);
        $end = end($prices);
        
        return $start == 0 ? 0 : round((($end - $start) / $start) * 100, 2);
    }

    private function getCorrelationStrength(float $correlation): string
    {
        $abs = abs($correlation);
        
        if ($abs >= 0.7) return 'strong';
        if ($abs >= 0.5) return 'moderate';
        if ($abs >= 0.3) return 'weak';
        return 'very_weak';
    }

    private function interpretCorrelation(float $correlation): string
    {
        if ($correlation > 0.7) {
            return 'Strong positive correlation - sentiment and price move together';
        } elseif ($correlation > 0.3) {
            return 'Moderate positive correlation - sentiment tends to follow price';
        } elseif ($correlation > -0.3) {
            return 'Weak correlation - sentiment and price move somewhat independently';
        } elseif ($correlation > -0.7) {
            return 'Moderate negative correlation - sentiment moves opposite to price';
        } else {
            return 'Strong negative correlation - sentiment and price move in opposite directions';
        }
    }

    private function getSentimentLabel(float $sentiment): string
    {
        if ($sentiment > 0.5) return 'Very Positive';
        if ($sentiment > 0.1) return 'Positive';
        if ($sentiment > -0.1) return 'Neutral';
        if ($sentiment > -0.5) return 'Negative';
        return 'Very Negative';
    }
}
