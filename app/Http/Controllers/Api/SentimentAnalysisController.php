<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailySentimentAggregate;
use App\Services\CoinGeckoService;
use App\Services\SentimentPipeline\DailySentimentAggregateService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Exception;

class SentimentAnalysisController extends Controller
{
    public function __construct(
        protected CoinGeckoService $coingeckoService,
        protected DailySentimentAggregateService $aggregateService
    ) {}

    /**
     * Get sentiment vs price correlation data for charts
     */
    public function getSentimentPriceCorrelation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'coin_id' => 'required|string|max:50',
            'start_date' => 'required|date|before_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date|before_or_equal:today',
            'platforms' => 'sometimes|string',
            'categories' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        try {
            $coinId = $request->input('coin_id');
            $startDate = Carbon::parse($request->input('start_date'));
            $endDate = Carbon::parse($request->input('end_date'));
            
            $platforms = $this->parsePlatforms($request->input('platforms', 'all'));
            $categories = $this->parseCategories($request->input('categories', 'all'));

            // Get combined sentiment and price data
            $correlationData = $this->coingeckoService->getCoinSentimentCorrelationData(
                $coinId,
                $startDate,
                $endDate,
                $platforms,
                $categories
            );

            // Calculate correlation statistics
            $stats = $this->calculateCorrelationStatistics($correlationData);

            return response()->json([
                'success' => true,
                'chart_data' => $correlationData,
                'correlation_stats' => $stats,
                'meta' => [
                    'coin_id' => $coinId,
                    'period' => [
                        'start' => $startDate->toDateString(),
                        'end' => $endDate->toDateString(),
                        'days' => $startDate->diffInDays($endDate) + 1
                    ],
                    'platforms' => $platforms,
                    'categories' => $categories,
                    'data_points' => count($correlationData)
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Sentiment price correlation API error', [
                'coin_id' => $request->input('coin_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to fetch sentiment correlation data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available cryptocurrencies for sentiment analysis
     */
    public function getAvailableCoins(): JsonResponse
    {
        try {
            $coins = $this->coingeckoService->getSupportedCoins();
            
            // Filter to popular coins for better UX
            $popularCoins = array_filter($coins, function($coin) {
                return in_array($coin['id'], [
                    'bitcoin', 'ethereum', 'binancecoin', 'cardano', 'solana',
                    'polkadot', 'chainlink', 'polygon', 'avalanche-2', 'uniswap',
                    'litecoin', 'dogecoin', 'shiba-inu', 'cosmos'
                ]);
            });

            // Sort by popularity/market cap order
            $sortOrder = [
                'bitcoin', 'ethereum', 'binancecoin', 'cardano', 'solana',
                'polkadot', 'chainlink', 'polygon', 'avalanche-2', 'uniswap'
            ];

            usort($popularCoins, function($a, $b) use ($sortOrder) {
                $aPos = array_search($a['id'], $sortOrder);
                $bPos = array_search($b['id'], $sortOrder);
                return ($aPos !== false ? $aPos : 999) <=> ($bPos !== false ? $bPos : 999);
            });

            return response()->json([
                'success' => true,
                'coins' => array_values($popularCoins),
                'total_count' => count($popularCoins)
            ]);

        } catch (Exception $e) {
            Log::error('Available coins API error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to fetch available coins',
                'coins' => $this->getDefaultCoins()
            ], 500);
        }
    }

    /**
     * Get current sentiment summary (last 24 hours) - for live updates
     */
    public function getCurrentSentimentSummary(Request $request): JsonResponse
    {
        try {
            // Always return demo data for now - this ensures the UI works without database dependencies
            // In production, you would try to fetch real data first and fall back to demo data
            $demoData = $this->generateDemoSentimentData();
            
            return response()->json([
                'success' => true,
                'data' => $demoData,
                'last_updated' => now()->toISOString(),
                'source' => 'demo' // Indicates this is demo data
            ]);

        } catch (Exception $e) {
            Log::error('Current sentiment summary failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Even if something goes wrong, return basic demo data
            return response()->json([
                'success' => true,
                'data' => [
                    'general' => [
                        'average_sentiment' => 0.65,
                        'total_posts' => 1250,
                        'sentiment_change' => 0.03,
                        'sentiment_breakdown' => [
                            'positive' => 0.65,
                            'neutral' => 0.28,
                            'negative' => 0.07
                        ]
                    ]
                ],
                'last_updated' => now()->toISOString(),
                'source' => 'fallback'
            ]);
        }
    }

    /**
     * Generate realistic demo sentiment data with slight variations
     */
    private function generateDemoSentimentData(): array
    {
        // Add slight random variations to make the demo more realistic
        $basePositive = 0.65 + (mt_rand(-50, 50) / 1000); // ±0.05 variation
        $baseNeutral = 0.28 + (mt_rand(-20, 20) / 1000);  // ±0.02 variation
        $baseNegative = 1 - $basePositive - $baseNeutral;  // Ensure it adds to 1
        
        $totalPosts = 1250 + mt_rand(-100, 200); // Vary total posts
        $sentimentChange = (mt_rand(-100, 100) / 1000); // ±0.1 change
        
        return [
            'general' => [
                'average_sentiment' => round($basePositive, 3),
                'total_posts' => $totalPosts,
                'sentiment_change' => round($sentimentChange, 3),
                'sentiment_breakdown' => [
                    'positive' => round($basePositive, 3),
                    'neutral' => round($baseNeutral, 3),
                    'negative' => round($baseNegative, 3)
                ]
            ],
            'protocols' => [
                'uniswap' => [
                    'average_sentiment' => round(0.73 + (mt_rand(-30, 30) / 1000), 3),
                    'total_posts' => 247 + mt_rand(-50, 50),
                    'sentiment_change' => round((mt_rand(-50, 50) / 1000), 3)
                ],
                'aave' => [
                    'average_sentiment' => round(0.85 + (mt_rand(-20, 20) / 1000), 3),
                    'total_posts' => 192 + mt_rand(-30, 30),
                    'sentiment_change' => round((mt_rand(-40, 60) / 1000), 3)
                ],
                'compound' => [
                    'average_sentiment' => round(0.42 + (mt_rand(-40, 40) / 1000), 3),
                    'total_posts' => 156 + mt_rand(-25, 25),
                    'sentiment_change' => round((mt_rand(-60, 20) / 1000), 3)
                ]
            ],
            'trending_topics' => [
                ['keyword' => 'DeFi', 'mentions' => 450 + mt_rand(-50, 50), 'average_sentiment' => round(0.72 + (mt_rand(-30, 30) / 1000), 3)],
                ['keyword' => 'NFT', 'mentions' => 380 + mt_rand(-40, 40), 'average_sentiment' => round(0.58 + (mt_rand(-40, 40) / 1000), 3)],
                ['keyword' => 'Web3', 'mentions' => 320 + mt_rand(-30, 30), 'average_sentiment' => round(0.68 + (mt_rand(-20, 20) / 1000), 3)],
                ['keyword' => 'Ethereum', 'mentions' => 280 + mt_rand(-25, 25), 'average_sentiment' => round(0.71 + (mt_rand(-25, 25) / 1000), 3)],
                ['keyword' => 'Bitcoin', 'mentions' => 250 + mt_rand(-20, 20), 'average_sentiment' => round(0.59 + (mt_rand(-35, 35) / 1000), 3)]
            ]
        ];
    }

    /**
     * Get sentiment analysis summary for a specific period
     */
    public function getSentimentSummary(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date|before_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date|before_or_equal:today',
            'platforms' => 'sometimes|string',
            'categories' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        try {
            $startDate = Carbon::parse($request->input('start_date'));
            $endDate = Carbon::parse($request->input('end_date'));
            $platforms = $this->parsePlatforms($request->input('platforms', 'all'));
            $categories = $this->parseCategories($request->input('categories', 'all'));

            // Get aggregated sentiment data for the period
            $aggregates = $this->aggregateService->aggregateMultipleDays($startDate, $endDate);

            // Filter by platforms and categories if specified
            $filteredAggregates = $this->filterAggregates($aggregates, $platforms, $categories);

            // Calculate summary statistics
            $summary = $this->calculateSentimentSummary($filteredAggregates);

            return response()->json([
                'success' => true,
                'summary' => $summary,
                'period' => [
                    'start' => $startDate->toDateString(),
                    'end' => $endDate->toDateString(),
                    'days' => $startDate->diffInDays($endDate) + 1
                ],
                'filters' => [
                    'platforms' => $platforms,
                    'categories' => $categories
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Sentiment summary API error', [
                'error' => $e->getMessage()
            ]);

            // Return mock data as fallback
            return response()->json([
                'success' => true,
                'summary' => $this->getMockSentimentSummary($startDate, $endDate),
                'period' => [
                    'start' => $startDate->toDateString(),
                    'end' => $endDate->toDateString(),
                    'days' => $startDate->diffInDays($endDate) + 1
                ],
                'message' => 'Using demo data (database not available)',
                'is_demo' => true
            ]);
        }
    }

    private function getMockSentimentSummary(Carbon $startDate, Carbon $endDate): array
    {
        $days = $startDate->diffInDays($endDate) + 1;
        
        return [
            'total_posts' => rand(1000, 5000) * $days,
            'average_sentiment' => round(rand(-50, 70) / 100, 3),
            'sentiment_trend' => ['bullish', 'bearish', 'neutral', 'volatile'][array_rand(['bullish', 'bearish', 'neutral', 'volatile'])],
            'platforms' => [
                'twitter' => rand(100, 500),
                'reddit' => rand(50, 200),
                'telegram' => rand(20, 150)
            ],
            'categories' => [
                'blockchain' => rand(200, 800),
                'defi' => rand(100, 400),
                'security' => rand(50, 200)
            ],
            'sentiment_distribution' => [
                'very_positive' => rand(10, 25),
                'positive' => rand(25, 35),
                'neutral' => rand(20, 30),
                'negative' => rand(15, 25),
                'very_negative' => rand(5, 15)
            ],
            'volatility' => round(rand(10, 50) / 100, 3),
            'confidence' => round(rand(70, 95) / 100, 2)
        ];
    }

    private function parsePlatforms(string $platforms): array
    {
        if ($platforms === 'all') {
            return ['all'];
        }
        
        return array_filter(explode(',', $platforms));
    }

    private function parseCategories(string $categories): array
    {
        if ($categories === 'all') {
            return ['all'];
        }
        
        return array_filter(explode(',', $categories));
    }

    private function calculateCorrelationStatistics(array $correlationData): array
    {
        if (empty($correlationData)) {
            return [
                'correlation_score' => '0.000',
                'avg_sentiment' => 0.0,
                'price_change_percent' => '0.00',
                'data_points' => 0,
                'sentiment_volatility' => 0.0
            ];
        }

        // Filter data to ensure we have both sentiment and price data
        $validData = array_filter($correlationData, fn($item) => 
            isset($item['sentiment_score']) && isset($item['price']) && 
            $item['sentiment_score'] !== null && $item['price'] !== null
        );
        
        if (empty($validData)) {
            return [
                'correlation_score' => '0.000',
                'avg_sentiment' => 0.0,
                'price_change_percent' => '0.00',
                'data_points' => 0,
                'sentiment_volatility' => 0.0
            ];
        }

        // Calculate average sentiment
        $sentiments = array_map(fn($item) => $item['sentiment_score'], $validData);
        $avgSentiment = array_sum($sentiments) / count($sentiments);

        // Calculate price change
        $prices = array_map(fn($item) => $item['price'], $validData);
        $priceChange = count($prices) > 1 
            ? (($prices[count($prices) - 1] - $prices[0]) / $prices[0]) * 100
            : 0;

        // Calculate correlation using correlation_strength field
        $correlationScores = array_map(fn($item) => $item['correlation_strength'] ?? 0, $validData);
        $avgCorrelation = array_sum($correlationScores) / count($correlationScores);

        // Calculate sentiment volatility
        $variance = array_sum(array_map(fn($s) => pow($s - $avgSentiment, 2), $sentiments)) / count($sentiments);
        $volatility = sqrt($variance);

        return [
            'correlation_score' => number_format($avgCorrelation, 3),
            'avg_sentiment' => round($avgSentiment, 3),
            'price_change_percent' => number_format($priceChange, 2),
            'data_points' => count($validData),
            'sentiment_volatility' => round($volatility, 3)
        ];
    }

    private function filterAggregates(array $aggregates, array $platforms, array $categories): array
    {
        return array_filter($aggregates, function($aggregate) use ($platforms, $categories) {
            $platformMatch = in_array('all', $platforms) || in_array($aggregate['platform'] ?? '', $platforms);
            $categoryMatch = in_array('all', $categories) || in_array($aggregate['keyword_category'] ?? '', $categories);
            
            return $platformMatch && $categoryMatch;
        });
    }

    private function calculateSentimentSummary(array $aggregates): array
    {
        if (empty($aggregates)) {
            return [
                'total_posts' => 0,
                'avg_sentiment' => 0.0,
                'sentiment_distribution' => [
                    'very_positive' => 0,
                    'positive' => 0,
                    'neutral' => 0,
                    'negative' => 0,
                    'very_negative' => 0
                ],
                'top_platforms' => [],
                'top_categories' => []
            ];
        }

        $totalPosts = array_sum(array_column($aggregates, 'total_posts'));
        $weightedSentiment = 0;
        $distribution = [
            'very_positive' => 0,
            'positive' => 0,
            'neutral' => 0,
            'negative' => 0,
            'very_negative' => 0
        ];

        foreach ($aggregates as $aggregate) {
            $posts = $aggregate['total_posts'] ?? 0;
            $sentiment = $aggregate['average_sentiment'] ?? 0;
            
            $weightedSentiment += $sentiment * $posts;
            
            $distribution['very_positive'] += $aggregate['very_positive_count'] ?? 0;
            $distribution['positive'] += $aggregate['positive_count'] ?? 0;
            $distribution['neutral'] += $aggregate['neutral_count'] ?? 0;
            $distribution['negative'] += $aggregate['negative_count'] ?? 0;
            $distribution['very_negative'] += $aggregate['very_negative_count'] ?? 0;
        }

        $avgSentiment = $totalPosts > 0 ? $weightedSentiment / $totalPosts : 0;

        // Get top platforms and categories
        $platformCounts = [];
        $categoryCounts = [];
        
        foreach ($aggregates as $aggregate) {
            $platform = $aggregate['platform'] ?? 'unknown';
            $category = $aggregate['keyword_category'] ?? 'unknown';
            $posts = $aggregate['total_posts'] ?? 0;
            
            $platformCounts[$platform] = ($platformCounts[$platform] ?? 0) + $posts;
            $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + $posts;
        }
        
        arsort($platformCounts);
        arsort($categoryCounts);

        return [
            'total_posts' => $totalPosts,
            'avg_sentiment' => round($avgSentiment, 3),
            'sentiment_distribution' => $distribution,
            'top_platforms' => array_slice($platformCounts, 0, 5, true),
            'top_categories' => array_slice($categoryCounts, 0, 5, true)
        ];
    }

    private function getDefaultCoins(): array
    {
        return [
            ['id' => 'bitcoin', 'symbol' => 'btc', 'name' => 'Bitcoin'],
            ['id' => 'ethereum', 'symbol' => 'eth', 'name' => 'Ethereum'],
            ['id' => 'binancecoin', 'symbol' => 'bnb', 'name' => 'BNB'],
            ['id' => 'cardano', 'symbol' => 'ada', 'name' => 'Cardano'],
            ['id' => 'solana', 'symbol' => 'sol', 'name' => 'Solana'],
            ['id' => 'polkadot', 'symbol' => 'dot', 'name' => 'Polkadot'],
            ['id' => 'chainlink', 'symbol' => 'link', 'name' => 'Chainlink'],
            ['id' => 'polygon', 'symbol' => 'matic', 'name' => 'Polygon']
        ];
    }
}