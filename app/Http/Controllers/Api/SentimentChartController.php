<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CoinGeckoService;
use App\Models\DailySentimentAggregate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

final class SentimentChartController extends Controller
{
    public function __construct(
        protected CoinGeckoService $coingeckoService
    ) {}

    /**
     * Get sentiment vs price correlation data for charting.
     */
    public function getSentimentPriceData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'coin_id' => 'required|string|max:50',
            'start_date' => 'required|date|before_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date|before_or_equal:today',
            'platforms' => 'sometimes|array',
            'platforms.*' => 'string|in:all,twitter,reddit,telegram',
            'categories' => 'sometimes|array', 
            'categories.*' => 'string|in:all,blockchain,security,contracts,defi',
            'vs_currency' => 'sometimes|string|in:usd,eur,btc,eth|max:5',
            'include_price' => 'sometimes|in:true,false,1,0',
            'include_volume' => 'sometimes|in:true,false,1,0',
        ]);

        try {
            $startDate = Carbon::parse($validated['start_date']);
            $endDate = Carbon::parse($validated['end_date']);
            $platforms = $validated['platforms'] ?? ['all'];
            $categories = $validated['categories'] ?? ['all'];
            $vsCurrency = $validated['vs_currency'] ?? 'usd';
            $includePrices = filter_var($validated['include_price'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
            $includeVolume = filter_var($validated['include_volume'] ?? 'false', FILTER_VALIDATE_BOOLEAN);

            // Validate date range
            if ($startDate->diffInDays($endDate) > 365) {
                return response()->json([
                    'error' => 'Date range cannot exceed 365 days',
                ], 422);
            }

            // Return sample data for now to test the API structure
            $data = [
                'metadata' => [
                    'coin_id' => $validated['coin_id'],
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'platforms' => $platforms,
                    'categories' => $categories,
                    'vs_currency' => $vsCurrency,
                    'total_days' => $startDate->diffInDays($endDate) + 1,
                ],
                'sentiment_data' => $this->getSampleSentimentData($startDate, $endDate),
                'price_data' => $includePrices ? $this->getSamplePriceData($startDate, $endDate, $validated['coin_id']) : null,
                'correlation_data' => null,
                'statistics' => [],
            ];

            // Calculate correlation if both sentiment and price data exist
            if ($data['sentiment_data'] && $data['price_data']) {
                $data['correlation_data'] = $this->calculateCorrelationData($data['sentiment_data'], $data['price_data']);
                $data['statistics'] = $this->calculateStatistics($data['correlation_data']);
            }

            return response()->json($data);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch chart data',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available cryptocurrencies for chart analysis.
     */
    public function getAvailableCoins(): JsonResponse
    {
        try {
            $coins = $this->coingeckoService->getSupportedCoins();
            
            // Filter to popular coins for better UX
            $popularCoins = array_filter($coins, function ($coin) {
                return in_array($coin['id'], [
                    'bitcoin', 'ethereum', 'binancecoin', 'cardano', 'solana',
                    'polkadot', 'chainlink', 'polygon', 'avalanche-2', 'cosmos',
                    'algorand', 'stellar', 'vechain', 'filecoin', 'chainlink',
                    'uniswap', 'aave', 'compound-governance-token', 'maker',
                    'synthetix-network-token', 'yearn-finance'
                ]);
            });

            return response()->json([
                'popular_coins' => array_values($popularCoins),
                'total_supported' => count($coins),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch supported coins',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search for cryptocurrencies.
     */
    public function searchCoins(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|min:2|max:50',
        ]);

        try {
            $results = $this->coingeckoService->searchCoins($validated['query']);
            
            return response()->json([
                'results' => array_slice($results, 0, 20), // Limit to 20 results
                'query' => $validated['query'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Search failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get sentiment analysis summary for a date range.
     */
    public function getSentimentSummary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'platform' => 'sometimes|string',
            'category' => 'sometimes|string',
        ]);

        try {
            $startDate = Carbon::parse($validated['start_date']);
            $endDate = Carbon::parse($validated['end_date']);

            $query = DailySentimentAggregate::dateRange($startDate, $endDate);

            if (isset($validated['platform']) && $validated['platform'] !== 'all') {
                $query->forPlatform($validated['platform']);
            }

            if (isset($validated['category']) && $validated['category'] !== 'all') {
                $query->where('keyword_category', $validated['category']);
            }

            $aggregates = $query->get();

            $summary = [
                'total_days' => $aggregates->count(),
                'total_posts' => $aggregates->sum('total_posts'),
                'average_sentiment' => round($aggregates->avg('avg_sentiment_score'), 3),
                'sentiment_range' => [
                    'min' => round($aggregates->min('avg_sentiment_score'), 3),
                    'max' => round($aggregates->max('avg_sentiment_score'), 3),
                ],
                'sentiment_distribution' => [
                    'very_positive' => $aggregates->where('avg_sentiment_score', '>', 0.6)->count(),
                    'positive' => $aggregates->whereBetween('avg_sentiment_score', [0.2, 0.6])->count(),
                    'neutral' => $aggregates->whereBetween('avg_sentiment_score', [-0.2, 0.2])->count(),
                    'negative' => $aggregates->whereBetween('avg_sentiment_score', [-0.6, -0.2])->count(),
                    'very_negative' => $aggregates->where('avg_sentiment_score', '<', -0.6)->count(),
                ],
                'volatility_stats' => [
                    'average' => round($aggregates->avg('avg_magnitude'), 3),
                    'max' => round($aggregates->max('avg_magnitude'), 3),
                ],
                'platforms' => $aggregates->pluck('platform')->unique()->values(),
                'categories' => $aggregates->pluck('keyword_category')->unique()->values(),
            ];

            return response()->json($summary);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch sentiment summary',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    protected function getSentimentData(
        Carbon $startDate,
        Carbon $endDate,
        array $platforms,
        array $categories
    ): array {
        $query = DailySentimentAggregate::dateRange($startDate, $endDate);

        if (!in_array('all', $platforms)) {
            $query->whereIn('platform', $platforms);
        }

        if (!in_array('all', $categories)) {
            $query->whereIn('keyword_category', $categories);
        }

        $aggregates = $query->orderBy('aggregate_date')->get();

        // Group by date and calculate weighted averages
        $sentimentData = [];
        foreach ($aggregates->groupBy('aggregate_date') as $date => $dayAggregates) {
            $totalPosts = $dayAggregates->sum('total_posts');
            
            if ($totalPosts > 0) {
                $weightedSentiment = $dayAggregates->sum(function ($aggregate) {
                    return $aggregate->avg_sentiment_score * $aggregate->total_posts;
                }) / $totalPosts;

                $sentimentData[] = [
                    'date' => $date,
                    'sentiment' => round($weightedSentiment, 3),
                    'magnitude' => round($dayAggregates->avg('avg_magnitude'), 3),
                    'volatility' => round($dayAggregates->avg('avg_magnitude'), 3),
                    'total_posts' => $totalPosts,
                    'sentiment_distribution' => [
                        'very_positive' => $dayAggregates->where('avg_sentiment_score', '>', 0.6)->count(),
                        'positive' => $dayAggregates->whereBetween('avg_sentiment_score', [0.2, 0.6])->count(),
                        'neutral' => $dayAggregates->whereBetween('avg_sentiment_score', [-0.2, 0.2])->count(),
                        'negative' => $dayAggregates->whereBetween('avg_sentiment_score', [-0.6, -0.2])->count(),
                        'very_negative' => $dayAggregates->where('avg_sentiment_score', '<', -0.6)->count(),
                    ],
                ];
            }
        }

        return $sentimentData;
    }

    protected function getPriceData(
        string $coinId,
        Carbon $startDate,
        Carbon $endDate,
        string $vsCurrency,
        bool $includeVolume
    ): ?array {
        try {
            return $this->coingeckoService->getDailyPricesForSentimentAnalysis(
                $coinId,
                $startDate,
                $endDate,
                $vsCurrency
            );
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function calculateCorrelationData(array $sentimentData, array $priceData): array
    {
        $combined = [];
        $sentimentByDate = collect($sentimentData)->keyBy('date');
        $priceByDate = collect($priceData)->keyBy('date');

        foreach ($sentimentByDate as $date => $sentiment) {
            $price = $priceByDate->get($date);
            
            if ($price) {
                $combined[] = [
                    'date' => $date,
                    'sentiment' => $sentiment['sentiment'],
                    'price' => $price['price_avg'],
                    'price_change' => $price['price_change_percent'],
                    'volume' => $price['volume'] ?? 0,
                    'posts' => $sentiment['total_posts'],
                    'volatility' => $sentiment['volatility'],
                ];
            }
        }

        return $combined;
    }

    protected function calculateStatistics(array $correlationData): array
    {
        if (empty($correlationData)) {
            return [];
        }

        $sentiments = collect($correlationData)->pluck('sentiment');
        $priceChanges = collect($correlationData)->pluck('price_change');
        
        // Calculate Pearson correlation coefficient
        $correlation = $this->calculatePearsonCorrelation(
            $sentiments->toArray(),
            $priceChanges->toArray()
        );

        return [
            'correlation_coefficient' => round($correlation, 4),
            'correlation_strength' => $this->getCorrelationStrength($correlation),
            'data_points' => count($correlationData),
            'sentiment_stats' => [
                'average' => round($sentiments->avg(), 3),
                'min' => round($sentiments->min(), 3),
                'max' => round($sentiments->max(), 3),
                'std_dev' => round($this->calculateStandardDeviation($sentiments->toArray()), 3),
            ],
            'price_stats' => [
                'average_change' => round($priceChanges->avg(), 2),
                'min_change' => round($priceChanges->min(), 2),
                'max_change' => round($priceChanges->max(), 2),
                'std_dev' => round($this->calculateStandardDeviation($priceChanges->toArray()), 2),
            ],
        ];
    }

    protected function calculatePearsonCorrelation(array $x, array $y): float
    {
        $n = count($x);
        if ($n !== count($y) || $n < 2) {
            return 0.0;
        }

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

        return $denominator != 0 ? $numerator / $denominator : 0.0;
    }

    protected function calculateStandardDeviation(array $values): float
    {
        $count = count($values);
        if ($count < 2) {
            return 0.0;
        }

        $mean = array_sum($values) / $count;
        $variance = array_sum(array_map(function ($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $values)) / ($count - 1);

        return sqrt($variance);
    }

    protected function getCorrelationStrength(float $correlation): string
    {
        $abs = abs($correlation);
        
        return match(true) {
            $abs >= 0.8 => 'Very Strong',
            $abs >= 0.6 => 'Strong',
            $abs >= 0.4 => 'Moderate',
            $abs >= 0.2 => 'Weak',
            default => 'Very Weak'
        } . ($correlation >= 0 ? ' Positive' : ' Negative');
    }

    protected function getSampleSentimentData(Carbon $startDate, Carbon $endDate): array
    {
        $sentimentData = [];
        $currentDate = $startDate->copy();
        
        while ($currentDate <= $endDate) {
            $sentiment = (sin(($currentDate->dayOfYear / 365) * 2 * M_PI) * 0.3) + (rand(-10, 10) / 100);
            $sentimentData[] = [
                'date' => $currentDate->toDateString(),
                'sentiment' => round($sentiment, 3),
                'magnitude' => round(rand(20, 80) / 100, 3),
                'volatility' => round(rand(10, 50) / 100, 3),
                'total_posts' => rand(50, 500),
                'sentiment_distribution' => [
                    'very_positive' => rand(0, 5),
                    'positive' => rand(5, 20),
                    'neutral' => rand(40, 60),
                    'negative' => rand(5, 20),
                    'very_negative' => rand(0, 5),
                ],
            ];
            $currentDate->addDay();
        }
        
        return $sentimentData;
    }

    protected function getSamplePriceData(Carbon $startDate, Carbon $endDate, string $coinId): array
    {
        $priceData = [];
        $currentDate = $startDate->copy();
        $basePrice = $coinId === 'bitcoin' ? 45000 : 2500; // Different base prices for different coins
        
        while ($currentDate <= $endDate) {
            $dayVariation = (sin(($currentDate->dayOfYear / 365) * 2 * M_PI) * 0.1) + (rand(-500, 500) / 10000);
            $price = $basePrice + ($basePrice * $dayVariation);
            $priceChange = rand(-10, 10);
            
            $priceData[] = [
                'date' => $currentDate->toDateString(),
                'price_avg' => round($price, 2),
                'price_change_percent' => round($priceChange, 2),
                'volume' => rand(1000000, 50000000),
            ];
            $currentDate->addDay();
        }
        
        return $priceData;
    }
}