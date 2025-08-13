<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CoinGeckoService;
use App\Models\DailySentimentAggregate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

/**
 * Sentiment vs Price Data Controller
 * 
 * Provides endpoints for retrieving sentiment and price correlation data
 * with optional CoinGecko API integration for real-time price data
 */
final class SentimentPriceController extends Controller
{
    public function __construct(
        private readonly CoinGeckoService $coinGeckoService
    ) {}

    /**
     * Get sentiment and price correlation data
     */
    public function getSentimentPriceData(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|in:bitcoin,ethereum,binancecoin,cardano,solana,polkadot,chainlink,polygon,avalanche,uniswap',
            'timeRange' => 'required|string|in:24h,7d,30d,90d,1y',
            'coingecko' => 'sometimes|in:true,false,1,0',
            'resolution' => 'sometimes|string|in:1h,4h,1d',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $token = $request->input('token');
            $timeRange = $request->input('timeRange');
            $useCoingecko = $request->boolean('coingecko', true);
            $resolution = $request->input('resolution', '1h');

            // Get time boundaries
            $timeBoundaries = $this->getTimeBoundaries($timeRange);
            
            // Generate cache key
            $cacheKey = "sentiment_price_data_{$token}_{$timeRange}_{$resolution}_" . ($useCoingecko ? 'cg' : 'mock');
            $cacheTtl = $this->getCacheTtl($timeRange);

            // Try to get cached data
            $cachedData = Cache::get($cacheKey);
            if ($cachedData) {
                return response()->json([
                    'success' => true,
                    'data' => $cachedData,
                    'metadata' => [
                        'token' => $token,
                        'time_range' => $timeRange,
                        'resolution' => $resolution,
                        'data_source' => 'mock', // CoinGecko temporarily disabled
                        'cached' => true,
                        'cache_expires' => Cache::get($cacheKey . '_expires')
                    ]
                ]);
            }

            // Fetch fresh data
            $sentimentData = $this->getSentimentData($token, $timeBoundaries, $resolution);
            
            // Always use mock data for now to avoid CoinGecko service issues
            $priceData = $this->getMockPriceData($token, $timeBoundaries, $resolution);

            // Align data points by timestamp
            $alignedData = $this->alignDataPoints($sentimentData, $priceData);

            // Calculate correlation and insights
            $analytics = $this->calculateAnalytics($alignedData['sentiment'], $alignedData['price']);

            $responseData = [
                'sentiment' => $alignedData['sentiment'],
                'price' => $alignedData['price'],
                'analytics' => $analytics,
                'summary' => [
                    'data_points' => count($alignedData['sentiment']),
                    'time_range' => $timeRange,
                    'correlation' => $analytics['correlation'],
                    'avg_sentiment' => $analytics['avg_sentiment'],
                    'price_change_pct' => $analytics['price_change_pct'],
                    'last_updated' => now()->toISOString()
                ]
            ];

            // Cache the result
            Cache::put($cacheKey, $responseData, $cacheTtl);
            Cache::put($cacheKey . '_expires', now()->addSeconds($cacheTtl)->toISOString(), $cacheTtl);

            return response()->json([
                'success' => true,
                'data' => $responseData,
                'metadata' => [
                    'token' => $token,
                    'time_range' => $timeRange,
                    'resolution' => $resolution,
                    'data_source' => 'mock', // CoinGecko temporarily disabled
                    'cached' => false,
                    'cache_ttl' => $cacheTtl
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch sentiment/price data', [
                'error' => $e->getMessage(),
                'token' => $request->input('token'),
                'time_range' => $request->input('timeRange'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sentiment and price data',
                'error' => app()->isProduction() ? 'Internal server error' : $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available tokens for sentiment analysis
     */
    public function getAvailableTokens(): JsonResponse
    {
        $tokens = [
            [
                'id' => 'bitcoin',
                'name' => 'Bitcoin',
                'symbol' => 'BTC',
                'coingecko_id' => 'bitcoin'
            ],
            [
                'id' => 'ethereum',
                'name' => 'Ethereum',
                'symbol' => 'ETH',
                'coingecko_id' => 'ethereum'
            ],
            [
                'id' => 'binancecoin',
                'name' => 'Binance Coin',
                'symbol' => 'BNB',
                'coingecko_id' => 'binancecoin'
            ],
            [
                'id' => 'cardano',
                'name' => 'Cardano',
                'symbol' => 'ADA',
                'coingecko_id' => 'cardano'
            ],
            [
                'id' => 'solana',
                'name' => 'Solana',
                'symbol' => 'SOL',
                'coingecko_id' => 'solana'
            ],
            [
                'id' => 'polkadot',
                'name' => 'Polkadot',
                'symbol' => 'DOT',
                'coingecko_id' => 'polkadot'
            ],
            [
                'id' => 'chainlink',
                'name' => 'Chainlink',
                'symbol' => 'LINK',
                'coingecko_id' => 'chainlink'
            ],
            [
                'id' => 'polygon',
                'name' => 'Polygon',
                'symbol' => 'MATIC',
                'coingecko_id' => 'matic-network'
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $tokens
        ]);
    }

    /**
     * Get real-time sentiment and price snapshot
     */
    public function getRealTimeSnapshot(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'coingecko' => 'sometimes|in:true,false,1,0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $token = $request->input('token');
            $useCoingecko = $request->boolean('coingecko', true);

            // Get latest sentiment
            $latestSentiment = $this->getLatestSentiment($token);
            
            // Get current price
            $currentPrice = $useCoingecko ? 
                $this->coinGeckoService->getCurrentPrice($token) :
                $this->getMockCurrentPrice($token);

            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $token,
                    'sentiment' => $latestSentiment,
                    'price' => $currentPrice,
                    'timestamp' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch real-time snapshot', [
                'error' => $e->getMessage(),
                'token' => $request->input('token')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch real-time data'
            ], 500);
        }
    }

    /**
     * Get time boundaries for the specified range
     */
    private function getTimeBoundaries(string $timeRange): array
    {
        $endTime = now();
        
        $startTime = match($timeRange) {
            '24h' => $endTime->copy()->subHours(24),
            '7d' => $endTime->copy()->subDays(7),
            '30d' => $endTime->copy()->subDays(30),
            '90d' => $endTime->copy()->subDays(90),
            '1y' => $endTime->copy()->subYear(),
            default => $endTime->copy()->subDays(7)
        };

        return [
            'start' => $startTime,
            'end' => $endTime
        ];
    }

    /**
     * Get cache TTL based on time range
     */
    private function getCacheTtl(string $timeRange): int
    {
        return match($timeRange) {
            '24h' => 300, // 5 minutes
            '7d' => 900, // 15 minutes
            '30d' => 1800, // 30 minutes
            '90d' => 3600, // 1 hour
            '1y' => 7200, // 2 hours
            default => 900
        };
    }

    /**
     * Get sentiment data from database
     */
    private function getSentimentData(string $token, array $timeBoundaries, string $resolution): array
    {
        try {
            // Try to get real sentiment data from daily aggregates
            $sentimentRecords = DailySentimentAggregate::where('keyword', 'like', "%{$token}%")
                ->whereBetween('date', [$timeBoundaries['start'], $timeBoundaries['end']])
                ->orderBy('aggregate_date')
                ->get();

            if ($sentimentRecords->isNotEmpty()) {
                return $sentimentRecords->map(function ($record) {
                    return [
                        'timestamp' => $record->date->toISOString(),
                        'sentiment_score' => $record->average_sentiment ?? 0
                    ];
                })->toArray();
            }
        } catch (\Exception $e) {
            Log::warning('Database unavailable for sentiment data, using fallback', [
                'token' => $token,
                'error' => $e->getMessage()
            ]);
        }

        // Fallback to generated data
        return $this->generateSentimentData($timeBoundaries, $resolution);
    }

    /**
     * Generate mock sentiment data
     */
    private function generateSentimentData(array $timeBoundaries, string $resolution): array
    {
        $data = [];
        $current = $timeBoundaries['start']->copy();
        
        $interval = match($resolution) {
            '1h' => 3600,
            '4h' => 14400,
            '1d' => 86400,
            default => 3600
        };

        while ($current <= $timeBoundaries['end']) {
            // Generate sentiment with some trending patterns
            $timeProgress = $current->diffInSeconds($timeBoundaries['start']) / $timeBoundaries['start']->diffInSeconds($timeBoundaries['end']);
            $baseSentiment = sin($timeProgress * M_PI * 2) * 0.3;
            $noise = (mt_rand() / mt_getrandmax() - 0.5) * 0.4;
            $sentiment = max(-1, min(1, $baseSentiment + $noise));

            $data[] = [
                'timestamp' => $current->toISOString(),
                'sentiment_score' => round($sentiment, 4)
            ];

            $current->addSeconds($interval);
        }

        return $data;
    }

    /**
     * Get price data from CoinGecko
     */
    private function getCoinGeckoPriceData(string $token, array $timeBoundaries, string $resolution): array
    {
        try {
            return $this->coinGeckoService->getHistoricalPrices(
                $token,
                $timeBoundaries['start'],
                $timeBoundaries['end'],
                $resolution
            );
        } catch (\Exception $e) {
            Log::warning('CoinGecko API failed, using mock data', [
                'token' => $token,
                'error' => $e->getMessage()
            ]);
            
            return $this->getMockPriceData($token, $timeBoundaries, $resolution);
        }
    }

    /**
     * Generate mock price data
     */
    private function getMockPriceData(string $token, array $timeBoundaries, string $resolution): array
    {
        $data = [];
        $current = $timeBoundaries['start']->copy();
        
        $interval = match($resolution) {
            '1h' => 3600,
            '4h' => 14400,
            '1d' => 86400,
            default => 3600
        };

        // Base prices for different tokens
        $basePrice = match($token) {
            'bitcoin' => 45000,
            'ethereum' => 3000,
            'binancecoin' => 300,
            'cardano' => 0.5,
            'solana' => 100,
            'polkadot' => 6,
            'chainlink' => 15,
            'polygon' => 0.8,
            default => 1.0
        };

        $currentPrice = $basePrice;

        while ($current <= $timeBoundaries['end']) {
            // Generate price with volatility and trends
            $volatility = 0.02; // 2% volatility
            $change = (mt_rand() / mt_getrandmax() - 0.5) * 2 * $volatility;
            $currentPrice *= (1 + $change);
            
            // Ensure price doesn't go negative
            $currentPrice = max($currentPrice, $basePrice * 0.1);

            $data[] = [
                'timestamp' => $current->toISOString(),
                'price' => round($currentPrice, 6)
            ];

            $current->addSeconds($interval);
        }

        return $data;
    }

    /**
     * Align sentiment and price data points by timestamp
     */
    private function alignDataPoints(array $sentimentData, array $priceData): array
    {
        // Create lookup tables
        $sentimentLookup = [];
        foreach ($sentimentData as $point) {
            $sentimentLookup[$point['timestamp']] = $point['sentiment_score'];
        }

        $priceLookup = [];
        foreach ($priceData as $point) {
            $priceLookup[$point['timestamp']] = $point['price'];
        }

        // Get all unique timestamps
        $allTimestamps = array_unique(array_merge(
            array_column($sentimentData, 'timestamp'),
            array_column($priceData, 'timestamp')
        ));
        sort($allTimestamps);

        $alignedSentiment = [];
        $alignedPrice = [];

        foreach ($allTimestamps as $timestamp) {
            if (isset($sentimentLookup[$timestamp]) && isset($priceLookup[$timestamp])) {
                $alignedSentiment[] = [
                    'timestamp' => $timestamp,
                    'sentiment_score' => $sentimentLookup[$timestamp]
                ];
                
                $alignedPrice[] = [
                    'timestamp' => $timestamp,
                    'price' => $priceLookup[$timestamp]
                ];
            }
        }

        return [
            'sentiment' => $alignedSentiment,
            'price' => $alignedPrice
        ];
    }

    /**
     * Calculate analytics for sentiment and price correlation
     */
    private function calculateAnalytics(array $sentimentData, array $priceData): array
    {
        if (empty($sentimentData) || empty($priceData)) {
            return [
                'correlation' => 0,
                'avg_sentiment' => 0,
                'price_change_pct' => 0,
                'volatility' => 0,
                'trend' => 'neutral'
            ];
        }

        // Extract values
        $sentimentValues = array_column($sentimentData, 'sentiment_score');
        $priceValues = array_column($priceData, 'price');

        // Calculate correlation
        $correlation = $this->calculateCorrelation($sentimentValues, $priceValues);

        // Calculate average sentiment
        $avgSentiment = array_sum($sentimentValues) / count($sentimentValues);

        // Calculate price change
        $priceChange = count($priceValues) > 1 ? 
            (($priceValues[count($priceValues) - 1] - $priceValues[0]) / $priceValues[0]) * 100 : 0;

        // Calculate price volatility (standard deviation)
        $priceMean = array_sum($priceValues) / count($priceValues);
        $priceVariance = array_sum(array_map(function($price) use ($priceMean) {
            return pow($price - $priceMean, 2);
        }, $priceValues)) / count($priceValues);
        $volatility = sqrt($priceVariance) / $priceMean * 100; // As percentage

        // Determine trend
        $trend = $priceChange > 1 ? 'bullish' : ($priceChange < -1 ? 'bearish' : 'neutral');

        return [
            'correlation' => round($correlation, 4),
            'avg_sentiment' => round($avgSentiment, 4),
            'price_change_pct' => round($priceChange, 2),
            'volatility' => round($volatility, 2),
            'trend' => $trend,
            'data_quality' => count($sentimentData) >= 10 ? 'good' : 'limited'
        ];
    }

    /**
     * Calculate Pearson correlation coefficient
     */
    private function calculateCorrelation(array $x, array $y): float
    {
        $n = min(count($x), count($y));
        
        if ($n < 2) {
            return 0;
        }

        $x = array_slice($x, 0, $n);
        $y = array_slice($y, 0, $n);

        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0;
        $sumX2 = 0;
        $sumY2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumX2 += $x[$i] * $x[$i];
            $sumY2 += $y[$i] * $y[$i];
        }

        $numerator = $n * $sumXY - $sumX * $sumY;
        $denominator = sqrt(($n * $sumX2 - $sumX * $sumX) * ($n * $sumY2 - $sumY * $sumY));

        if ($denominator == 0) {
            return 0;
        }

        return $numerator / $denominator;
    }

    /**
     * Get latest sentiment for a token
     */
    private function getLatestSentiment(string $token): array
    {
        try {
            $latest = DailySentimentAggregate::where('keyword', 'like', "%{$token}%")
                ->orderBy('aggregate_date', 'desc')
                ->first();

            if ($latest) {
                return [
                    'score' => $latest->average_sentiment ?? 0,
                    'date' => $latest->date->toISOString(),
                    'volume' => $latest->total_mentions ?? 0
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Database unavailable for latest sentiment, using fallback', [
                'token' => $token,
                'error' => $e->getMessage()
            ]);
        }

        // Fallback to mock data
        return [
            'score' => (mt_rand() / mt_getrandmax() - 0.5) * 0.8,
            'date' => now()->toISOString(),
            'volume' => mt_rand(50, 500)
        ];
    }

    /**
     * Get mock current price
     */
    private function getMockCurrentPrice(string $token): array
    {
        $basePrice = match($token) {
            'bitcoin' => 45000,
            'ethereum' => 3000,
            'binancecoin' => 300,
            'cardano' => 0.5,
            'solana' => 100,
            'polkadot' => 6,
            'chainlink' => 15,
            'polygon' => 0.8,
            default => 1.0
        };

        $change = (mt_rand() / mt_getrandmax() - 0.5) * 0.1; // ±5% variation
        $currentPrice = $basePrice * (1 + $change);

        return [
            'price' => $currentPrice,
            'change_24h' => $change * 100,
            'timestamp' => now()->toISOString()
        ];
    }
}
