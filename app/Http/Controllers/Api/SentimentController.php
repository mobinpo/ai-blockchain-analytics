<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

final class SentimentController extends Controller
{
    /**
     * Get sentiment timeline for a symbol
     */
    public function getTimeline(Request $request, string $symbol): JsonResponse
    {
        try {
            $request->validate([
                'period' => 'sometimes|string|in:1h,24h,7d,30d,90d',
                'granularity' => 'sometimes|string|in:5m,1h,1d'
            ]);

            $period = $request->query('period', '7d');
            $granularity = $request->query('granularity', '1h');
            
            $cacheKey = "sentiment_timeline_{$symbol}_{$period}_{$granularity}";
            
            $data = Cache::remember($cacheKey, 300, function() use ($symbol, $period, $granularity) {
                return $this->generateSentimentTimeline($symbol, $period, $granularity);
            });
            
            return response()->json([
                'success' => true,
                'symbol' => $symbol,
                'period' => $period,
                'granularity' => $granularity,
                'data' => $data,
                'timestamp' => Carbon::now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sentiment timeline',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get current sentiment for a symbol
     */
    public function getCurrent(Request $request, string $symbol): JsonResponse
    {
        try {
            $cacheKey = "sentiment_current_{$symbol}";
            
            $data = Cache::remember($cacheKey, 60, function() use ($symbol) {
                return $this->generateCurrentSentiment($symbol);
            });
            
            return response()->json([
                'success' => true,
                'symbol' => $symbol,
                'data' => $data,
                'timestamp' => Carbon::now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch current sentiment',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get live sentiment trends
     */
    public function getLiveTrends(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'symbols' => 'sometimes|array|max:10',
                'symbols.*' => 'string|max:20'
            ]);

            $symbols = $request->query('symbols', ['bitcoin', 'ethereum']);
            
            $trends = [];
            foreach ($symbols as $symbol) {
                $cacheKey = "sentiment_trends_{$symbol}";
                $trends[$symbol] = Cache::remember($cacheKey, 120, function() use ($symbol) {
                    return $this->generateLiveTrends($symbol);
                });
            }
            
            return response()->json([
                'success' => true,
                'symbols' => $symbols,
                'trends' => $trends,
                'timestamp' => Carbon::now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch live trends',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Generate sentiment timeline data
     */
    private function generateSentimentTimeline(string $symbol, string $period, string $granularity): array
    {
        $intervals = $this->getTimeIntervals($period, $granularity);
        $data = [];
        
        $basePrice = $this->getSymbolBasePrice($symbol);
        $baseSentiment = 0.5 + (rand(-20, 20) / 100); // Random base around neutral
        
        foreach ($intervals as $i => $time) {
            // Generate realistic sentiment fluctuations
            $sentimentNoise = (rand(-15, 15) / 100);
            $sentiment = max(0, min(1, $baseSentiment + $sentimentNoise));
            
            // Generate price based on some correlation with sentiment
            $priceInfluence = ($sentiment - 0.5) * 0.1; // Sentiment influences price
            $priceNoise = (rand(-5, 5) / 100);
            $priceChange = $priceInfluence + $priceNoise;
            $price = $basePrice * (1 + $priceChange);
            
            $data[] = [
                'timestamp' => $time->toISOString(),
                'sentiment_score' => round($sentiment, 3),
                'price' => round($price, 2),
                'volume' => rand(100, 1000),
                'mentions_count' => rand(50, 500),
                'positive_mentions' => rand(20, 300),
                'negative_mentions' => rand(10, 150),
                'neutral_mentions' => rand(20, 200)
            ];
            
            // Update base values for next iteration (trending)
            $baseSentiment = $sentiment;
            $basePrice = $price;
        }
        
        return $data;
    }

    /**
     * Generate current sentiment data
     */
    private function generateCurrentSentiment(string $symbol): array
    {
        return [
            'sentiment_score' => rand(30, 85) / 100,
            'current_price' => $this->getSymbolBasePrice($symbol) * (1 + rand(-10, 10) / 100),
            'price_change_24h' => rand(-8, 12) / 100,
            'mentions_count' => rand(1000, 5000),
            'mention_growth' => rand(-30, 50) / 100,
            'correlation' => rand(-50, 80) / 100,
            'top_keywords' => ['bullish', 'breakout', 'resistance', 'support'],
            'platform_breakdown' => [
                'twitter' => rand(40, 60),
                'reddit' => rand(20, 40),
                'telegram' => rand(10, 25),
                'discord' => rand(5, 15)
            ]
        ];
    }

    /**
     * Generate live trends data
     */
    private function generateLiveTrends(string $symbol): array
    {
        $trends = [];
        $baseTime = Carbon::now()->subHours(5);
        
        for ($i = 0; $i < 20; $i++) {
            $time = $baseTime->copy()->addMinutes($i * 15);
            $trends[] = [
                'time' => $time->format('H:i'),
                'sentiment' => rand(30, 85) / 100,
                'price_change' => rand(-3, 5) / 10,
                'volume_spike' => rand(0, 1) > 0.8
            ];
        }
        
        return $trends;
    }

    /**
     * Get time intervals for the given period and granularity
     */
    private function getTimeIntervals(string $period, string $granularity): array
    {
        $end = Carbon::now();
        $start = match($period) {
            '1h' => $end->copy()->subHour(),
            '24h' => $end->copy()->subDay(),
            '7d' => $end->copy()->subWeek(),
            '30d' => $end->copy()->subMonth(),
            '90d' => $end->copy()->subMonths(3),
            default => $end->copy()->subWeek()
        };
        
        $interval = match($granularity) {
            '5m' => 5,
            '1h' => 60,
            '1d' => 1440,
            default => 60
        };
        
        $intervals = [];
        $current = $start->copy();
        
        while ($current <= $end) {
            $intervals[] = $current->copy();
            $current->addMinutes($interval);
        }
        
        return $intervals;
    }

    /**
     * Get base price for symbol (would come from price API in production)
     */
    private function getSymbolBasePrice(string $symbol): float
    {
        return match(strtolower($symbol)) {
            'bitcoin', 'btc' => 65000,
            'ethereum', 'eth' => 3200,
            'cardano', 'ada' => 0.45,
            'solana', 'sol' => 140,
            'polygon', 'matic' => 0.85,
            'chainlink', 'link' => 12.5,
            default => rand(1, 100)
        };
    }
}
