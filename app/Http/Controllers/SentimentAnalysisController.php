<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\CoinGeckoService;
use App\Models\DailySentimentAggregate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class SentimentAnalysisController extends Controller
{
    public function __construct(
        protected CoinGeckoService $coingeckoService
    ) {}

    /**
     * Display the sentiment analysis dashboard.
     */
    public function index(): Response
    {
        // Get recent sentiment overview
        $recentSentiment = $this->getRecentSentimentOverview();
        
        // Get available platforms and categories
        $availableFilters = $this->getAvailableFilters();
        
        return Inertia::render('SentimentAnalysis/Index', [
            'recentSentiment' => $recentSentiment,
            'availableFilters' => $availableFilters,
        ]);
    }

    /**
     * Display the sentiment vs price chart page.
     */
    public function sentimentPriceChart(Request $request): Response
    {
        $coin = $request->query('coin', 'bitcoin');
        $days = (int) $request->query('days', 30);
        
        // Fetch initial chart data to pass through Inertia props
        $chartData = null;
        try {
            $sentimentChartController = app(\App\Http\Controllers\Api\SentimentChartController::class);
            
            // Create request with proper data structure
            $requestData = [
                'coin_id' => $coin,
                'start_date' => Carbon::today()->subDays($days)->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'platforms' => ['all'],
                'categories' => ['all']
            ];
            
            $dataRequest = new Request($requestData);
            
            $response = $sentimentChartController->getSentimentPriceData($dataRequest);
            $responseData = json_decode($response->getContent(), true);
            
            if ($responseData && isset($responseData['success']) && $responseData['success']) {
                $chartData = $responseData;
            }
        } catch (\Exception $e) {
            // If data fetching fails, component will handle API fallback
            Log::warning('Failed to prefetch sentiment chart data: ' . $e->getMessage());
        }
        
        return Inertia::render('SentimentAnalysis/SentimentPriceChart', [
            'initialCoin' => $coin,
            'initialDays' => $days,
            'chartData' => $chartData, // Pre-fetched data
            'availableCoins' => $this->getDefaultCoins(), // Coins for dropdown
        ]);
    }

    /**
     * Display detailed sentiment analysis for a specific platform/category.
     */
    public function platformAnalysis(Request $request): Response
    {
        $platform = $request->query('platform', 'all');
        $category = $request->query('category', 'all');
        $days = (int) $request->query('days', 30);
        
        $endDate = Carbon::today();
        $startDate = $endDate->copy()->subDays($days);
        
        $analysis = $this->getPlatformAnalysis($platform, $category, $startDate, $endDate);
        
        return Inertia::render('SentimentAnalysis/PlatformAnalysis', [
            'platform' => $platform,
            'category' => $category,
            'days' => $days,
            'analysis' => $analysis,
        ]);
    }

    /**
     * Display sentiment trends and historical data.
     */
    public function trends(Request $request): Response
    {
        $timeframe = $request->query('timeframe', '30d');
        $comparison = $request->query('comparison', 'none');
        
        $trends = $this->getSentimentTrends($timeframe, $comparison);
        
        return Inertia::render('SentimentAnalysis/Trends', [
            'timeframe' => $timeframe,
            'comparison' => $comparison,
            'trends' => $trends,
        ]);
    }

    /**
     * Display sentiment correlation analysis with various metrics.
     */
    public function correlations(): Response
    {
        $correlations = $this->getCorrelationAnalysis();
        
        return Inertia::render('SentimentAnalysis/Correlations', [
            'correlations' => $correlations,
        ]);
    }

    protected function getRecentSentimentOverview(): array
    {
        $endDate = Carbon::today();
        $startDate = $endDate->copy()->subDays(7);
        
        $aggregates = DailySentimentAggregate::dateRange($startDate, $endDate)
                        ->where('platform', 'all')
            ->where('keyword_category', 'all')
            ->orderBy('aggregate_date', 'desc')
            ->get();
        
        if ($aggregates->isEmpty()) {
            return [
                'current_sentiment' => 0,
                'trend' => 'neutral',
                'change_7d' => 0,
                'total_posts_7d' => 0,
                'daily_data' => [],
            ];
        }
        
        $latest = $aggregates->first();
        $oldest = $aggregates->last();
        
        $sentimentChange = (float) $latest->avg_sentiment_score - (float) $oldest->avg_sentiment_score;
        $trend = abs($sentimentChange) < 0.05 ? 'neutral' : ($sentimentChange > 0 ? 'positive' : 'negative');
        
        return [
            'current_sentiment' => round((float) $latest->avg_sentiment_score, 3),
            'trend' => $trend,
            'change_7d' => round((float) $sentimentChange, 3),
            'total_posts_7d' => $aggregates->sum('total_posts'),
            'daily_data' => $aggregates->map(function ($aggregate) {
                return [
                    'date' => $aggregate->aggregate_date->format('Y-m-d'),
                    'sentiment' => round((float) $aggregate->avg_sentiment_score, 3),
                    'posts' => $aggregate->total_posts,
                    'volatility' => round((float) $aggregate->sentiment_volatility, 3),
                ];
            })->values()->toArray(),
        ];
    }

    protected function getAvailableFilters(): array
    {
        $platforms = DailySentimentAggregate::select('platform')
            ->distinct()
            ->where('platform', '!=', 'all')
            ->pluck('platform')
            ->toArray();
        
        $categories = DailySentimentAggregate::select('keyword_category')
            ->distinct()
            ->where('keyword_category', '!=', 'all')
            ->whereNotNull('keyword_category')
            ->pluck('keyword_category')
            ->toArray();
        
        return [
            'platforms' => $platforms,
            'categories' => $categories,
        ];
    }

    protected function getPlatformAnalysis(
        string $platform,
        string $category,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        $query = DailySentimentAggregate::dateRange($startDate, $endDate);
        
        if ($platform !== 'all') {
            $query->forPlatform($platform);
        }
        
        if ($category !== 'all') {
            $query->forKeyword($category);
        }
        
        $aggregates = $query->orderBy('aggregate_date')->get();
        
        return [
            'summary' => [
                'total_days' => $aggregates->count(),
                'total_posts' => $aggregates->sum('total_posts'),
                'avg_sentiment_score' => round((float) $aggregates->avg('avg_sentiment_score'), 3),
                'sentiment_range' => [
                    'min' => round((float) $aggregates->min('avg_sentiment_score'), 3),
                    'max' => round((float) $aggregates->max('avg_sentiment_score'), 3),
                ],
                'average_volatility' => round((float) $aggregates->avg('sentiment_volatility'), 3),
            ],
            'daily_breakdown' => $aggregates->map(function ($aggregate) {
                return [
                    'date' => $aggregate->aggregate_date->format('Y-m-d'),
                    'sentiment' => round((float) $aggregate->avg_sentiment_score, 3),
                    'posts' => $aggregate->total_posts,
                    'volatility' => round((float) $aggregate->sentiment_volatility, 3),
                    'distribution' => $aggregate->getSentimentDistribution(),
                ];
            })->values()->toArray(),
            'sentiment_distribution' => [
                'very_positive' => $aggregates->sum('very_positive_count'),
                'positive' => $aggregates->sum('positive_count'),
                'neutral' => $aggregates->sum('neutral_count'),
                'negative' => $aggregates->sum('negative_count'),
                'very_negative' => $aggregates->sum('very_negative_count'),
            ],
        ];
    }

    protected function getSentimentTrends(string $timeframe, string $comparison): array
    {
        $days = match($timeframe) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            '180d' => 180,
            '365d' => 365,
            default => 30
        };
        
        $endDate = Carbon::today();
        $startDate = $endDate->copy()->subDays($days);
        
        $currentPeriod = $this->getTrendData($startDate, $endDate);
        $comparisonData = null;
        
        if ($comparison === 'previous') {
            $comparisonEndDate = $startDate->copy()->subDay();
            $comparisonStartDate = $comparisonEndDate->copy()->subDays($days);
            $comparisonData = $this->getTrendData($comparisonStartDate, $comparisonEndDate);
        }
        
        return [
            'current_period' => $currentPeriod,
            'comparison_period' => $comparisonData,
            'timeframe' => $timeframe,
            'comparison_type' => $comparison,
        ];
    }

    protected function getTrendData(Carbon $startDate, Carbon $endDate): array
    {
        $aggregates = DailySentimentAggregate::dateRange($startDate, $endDate)
                        ->orderBy('aggregate_date')
            ->get();
        
        $groupedByPlatform = $aggregates->groupBy('platform');
        
        return [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'overall_stats' => [
                'avg_sentiment_score' => round((float) $aggregates->avg('avg_sentiment_score'), 3),
                'total_posts' => $aggregates->sum('total_posts'),
                'days_with_data' => $aggregates->count(),
            ],
            'platform_breakdown' => $groupedByPlatform->map(function ($platformAggregates, $platform) {
                return [
                    'platform' => $platform,
                    'avg_sentiment_score' => round((float) $platformAggregates->avg('avg_sentiment_score'), 3),
                    'total_posts' => $platformAggregates->sum('total_posts'),
                    'sentiment_range' => [
                        'min' => round((float) $platformAggregates->min('avg_sentiment_score'), 3),
                        'max' => round((float) $platformAggregates->max('avg_sentiment_score'), 3),
                    ],
                ];
            })->values()->toArray(),
            'daily_timeline' => $aggregates->map(function ($aggregate) {
                return [
                    'date' => $aggregate->aggregate_date->format('Y-m-d'),
                    'sentiment' => round((float) $aggregate->avg_sentiment_score, 3),
                    'posts' => $aggregate->total_posts,
                    'platform' => $aggregate->platform,
                ];
            })->values()->toArray(),
        ];
    }

    protected function getCorrelationAnalysis(): array
    {
        // Get popular coins for correlation analysis
        $popularCoins = ['bitcoin', 'ethereum', 'binancecoin', 'cardano'];
        $correlations = [];
        
        $endDate = Carbon::today();
        $startDate = $endDate->copy()->subDays(30);
        
        foreach ($popularCoins as $coinId) {
            try {
                $correlationData = $this->coingeckoService->getCoinSentimentCorrelationData(
                    $coinId,
                    $startDate,
                    $endDate
                );
                
                if (!empty($correlationData)) {
                    $sentiments = collect($correlationData)->pluck('sentiment_data.avg_sentiment_score')->filter();
                    $priceChanges = collect($correlationData)->pluck('price_data.price_change_percent')->filter();
                    
                    if ($sentiments->count() > 1 && $priceChanges->count() > 1) {
                        $correlation = $this->calculatePearsonCorrelation(
                            $sentiments->toArray(),
                            $priceChanges->toArray()
                        );
                        
                        $correlations[] = [
                            'coin_id' => $coinId,
                            'correlation' => round((float) $correlation, 4),
                            'data_points' => min($sentiments->count(), $priceChanges->count()),
                            'sentiment_avg' => round((float) $sentiments->avg(), 3),
                            'price_change_avg' => round((float) $priceChanges->avg(), 2),
                        ];
                    }
                }
            } catch (\Exception $e) {
                // Skip this coin if there's an error
                continue;
            }
        }
        
        return [
            'coin_correlations' => $correlations,
            'analysis_period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'days' => 30,
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

    /**
     * Get sentiment vs price correlation data for API.
     */
    public function getSentimentPriceCorrelation(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'coin_id' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'platforms' => 'nullable|string',
            'categories' => 'nullable|string'
        ]);

        try {
            $coinId = $request->input('coin_id');
            $startDate = Carbon::parse($request->input('start_date'));
            $endDate = Carbon::parse($request->input('end_date'));
            $platforms = $request->input('platforms', 'all');
            $categories = $request->input('categories', 'all');

            // Temporarily return demo data to allow load testing while debugging ORM issue
            $days = $startDate->diffInDays($endDate);
            $sentimentData = [];
            $priceData = [];
            
            // Generate demo sentiment data
            for ($i = 0; $i <= $days; $i++) {
                $date = $startDate->copy()->addDays($i);
                $sentiment = sin($i * 0.1) * 0.3 + (rand(-100, 100) / 1000);
                $sentiment = max(-1, min(1, $sentiment)); // Clamp between -1 and 1
                
                $sentimentData[] = [
                    'date' => $date->format('Y-m-d'),
                    'sentiment' => round($sentiment, 3),
                    'volume' => rand(50, 500)
                ];
            }
            
            // Generate demo price data (correlated with sentiment)
            $basePrice = 50000; // Bitcoin base price
            for ($i = 0; $i <= $days; $i++) {
                $date = $startDate->copy()->addDays($i);
                $sentiment = $sentimentData[$i]['sentiment'];
                $priceChange = $sentiment * 0.02 + (rand(-100, 100) / 10000); // Correlated with sentiment
                $basePrice = $basePrice * (1 + $priceChange);
                
                $priceData[] = [
                    'date' => $date->format('Y-m-d'),
                    'price' => round($basePrice, 2),
                    'volume' => rand(1000000, 10000000)
                ];
            }

            // Prepare chart data
            $chartData = [
                'sentiment_timeline' => $sentimentData,
                'price_timeline' => $priceData
            ];

            // Calculate demo correlation
            $sentimentValues = array_column($sentimentData, 'sentiment');
            $priceValues = array_column($priceData, 'price');
            $correlation = $this->calculatePearsonCorrelation($sentimentValues, $priceValues);

            return response()->json([
                'success' => true,
                'chart_data' => $chartData,
                'correlation_stats' => [
                    'correlation_coefficient' => $correlation ? round($correlation, 4) : null,
                    'data_points' => count($sentimentData),
                    'period' => [
                        'start' => $startDate->format('Y-m-d'),
                        'end' => $endDate->format('Y-m-d'),
                        'days' => $days
                    ]
                ],
                'demo_mode' => true,
                'message' => 'Using demo data - database integration pending'
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching sentiment price correlation: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch correlation data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available coins for API.
     */
    public function getAvailableCoins(): \Illuminate\Http\JsonResponse
    {
        try {
            $coins = $this->getDefaultCoins();
            
            return response()->json([
                'success' => true,
                'coins' => $coins
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching available coins: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch available coins',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get default coins list.
     */
    private function getDefaultCoins(): array
    {
        try {
            $sentimentChartController = app(\App\Http\Controllers\Api\SentimentChartController::class);
            $response = $sentimentChartController->getAvailableCoins(new Request());
            $responseData = json_decode($response->getContent(), true);
            
            if ($responseData && isset($responseData['success']) && $responseData['success']) {
                return $responseData['coins'] ?? [];
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch available coins: ' . $e->getMessage());
        }
        
        // Fallback to default coins
        return [
            ['id' => 'bitcoin', 'name' => 'Bitcoin', 'symbol' => 'BTC'],
            ['id' => 'ethereum', 'name' => 'Ethereum', 'symbol' => 'ETH'],
            ['id' => 'cardano', 'name' => 'Cardano', 'symbol' => 'ADA'],
            ['id' => 'solana', 'name' => 'Solana', 'symbol' => 'SOL'],
            ['id' => 'polygon', 'name' => 'Polygon', 'symbol' => 'MATIC'],
        ];
    }

    /**
     * Get sentiment summary for API.
     */
    public function getSentimentSummary(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $days = (int) $request->input('days', 7);
            $platform = $request->input('platform', 'all');
            $category = $request->input('category', 'all');

            $endDate = Carbon::today();
            $startDate = $endDate->copy()->subDays($days);

            $query = DailySentimentAggregate::dateRange($startDate, $endDate);
            
            if ($platform !== 'all') {
                $query->forPlatform($platform);
            }
            
            if ($category !== 'all') {
                $query->forKeyword($category);
            }

            $aggregates = $query->orderBy('aggregate_date', 'desc')->get();

            $summary = [
                'period' => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'days' => $days
                ],
                'overall' => [
                    'avg_sentiment_score' => $aggregates->avg('avg_sentiment_score') ? round((float) $aggregates->avg('avg_sentiment_score'), 3) : 0,
                    'total_posts' => $aggregates->sum('total_posts'),
                    'days_with_data' => $aggregates->count()
                ],
                'distribution' => [
                    'very_positive' => $aggregates->sum('very_positive_count'),
                    'positive' => $aggregates->sum('positive_count'),
                    'neutral' => $aggregates->sum('neutral_count'),
                    'negative' => $aggregates->sum('negative_count'),
                    'very_negative' => $aggregates->sum('very_negative_count')
                ],
                'daily_data' => $aggregates->map(function ($aggregate) {
                    return [
                        'date' => $aggregate->aggregate_date->format('Y-m-d'),
                        'sentiment' => round((float) $aggregate->avg_sentiment_score, 3),
                        'posts' => $aggregate->total_posts,
                        'volatility' => round((float) $aggregate->sentiment_volatility, 3)
                    ];
                })->toArray()
            ];

            return response()->json([
                'success' => true,
                'summary' => $summary
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching sentiment summary: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sentiment summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current sentiment summary for API.
     */
    public function getCurrentSentimentSummary(): \Illuminate\Http\JsonResponse
    {
        try {
            $recentSentiment = $this->getRecentSentimentOverview();
            
            return response()->json([
                'success' => true,
                'current_sentiment' => $recentSentiment
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching current sentiment summary: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch current sentiment summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}