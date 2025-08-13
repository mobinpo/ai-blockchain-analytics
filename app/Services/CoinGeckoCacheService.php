<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class CoinGeckoCacheService
{
    private const BASE_URL = 'https://api.coingecko.com/api/v3';
    private const RATE_LIMIT_DELAY = 1000; // 1 second between requests (free tier)
    
    public function __construct(
        private readonly ApiCacheService $cacheService
    ) {}

    /**
     * Get current price for single or multiple cryptocurrencies.
     */
    public function getCurrentPrice(
        array|string $coinIds, 
        string $vsCurrency = 'usd',
        bool $includeMarketCap = false,
        bool $include24hrVol = false,
        bool $include24hrChange = false,
        bool $includeLastUpdated = false
    ): array {
        $coinIdsArray = is_string($coinIds) ? [$coinIds] : $coinIds;
        $coinIdsString = implode(',', $coinIdsArray);
        
        $params = [
            'ids' => $coinIdsString,
            'vs_currencies' => $vsCurrency,
            'include_market_cap' => $includeMarketCap ? 'true' : 'false',
            'include_24hr_vol' => $include24hrVol ? 'true' : 'false',
            'include_24hr_change' => $include24hrChange ? 'true' : 'false',
            'include_last_updated_at' => $includeLastUpdated ? 'true' : 'false',
        ];

        return $this->cacheService->cacheOrRetrieve(
            'coingecko',
            'simple/price',
            'price',
            fn() => $this->makeApiCall('simple/price', $params),
            $params,
            $coinIdsString,
            300, // 5 minutes TTL for price data
            [
                'coin_count' => count($coinIdsArray),
                'vs_currency' => $vsCurrency,
                'include_extras' => $includeMarketCap || $include24hrVol || $include24hrChange,
            ]
        );
    }

    /**
     * Get historical price data for a specific coin.
     */
    public function getHistoricalPrice(
        string $coinId,
        string $vsCurrency = 'usd',
        int $days = 30,
        string $interval = 'daily'
    ): array {
        $params = [
            'vs_currency' => $vsCurrency,
            'days' => $days,
            'interval' => $interval,
        ];

        $resourceId = "{$coinId}_{$days}d_{$interval}";

        return $this->cacheService->cacheOrRetrieve(
            'coingecko',
            "coins/{$coinId}/market_chart",
            'historical_price',
            fn() => $this->makeApiCall("coins/{$coinId}/market_chart", $params),
            $params,
            $resourceId,
            3600, // 1 hour TTL for historical data
            [
                'coin_id' => $coinId,
                'days' => $days,
                'interval' => $interval,
                'vs_currency' => $vsCurrency,
            ]
        );
    }

    /**
     * Get comprehensive coin information.
     */
    public function getCoinInfo(
        string $coinId, 
        bool $localization = false,
        bool $tickers = false,
        bool $marketData = true,
        bool $communityData = false,
        bool $developerData = false
    ): array {
        $params = [
            'localization' => $localization ? 'true' : 'false',
            'tickers' => $tickers ? 'true' : 'false',
            'market_data' => $marketData ? 'true' : 'false',
            'community_data' => $communityData ? 'true' : 'false',
            'developer_data' => $developerData ? 'true' : 'false',
        ];

        return $this->cacheService->cacheOrRetrieve(
            'coingecko',
            "coins/{$coinId}",
            'coin_info',
            fn() => $this->makeApiCall("coins/{$coinId}", $params),
            $params,
            $coinId,
            86400, // 24 hours TTL for coin info
            [
                'coin_id' => $coinId,
                'include_tickers' => $tickers,
                'include_market_data' => $marketData,
            ]
        );
    }

    /**
     * Get market data for multiple coins with pagination.
     */
    public function getMarketData(
        string $vsCurrency = 'usd',
        array $coinIds = [],
        string $order = 'market_cap_desc',
        int $perPage = 100,
        int $page = 1,
        bool $sparkline = false,
        ?string $priceChangePercentage = null
    ): array {
        $params = [
            'vs_currency' => $vsCurrency,
            'order' => $order,
            'per_page' => min($perPage, 250), // API limit
            'page' => $page,
            'sparkline' => $sparkline ? 'true' : 'false',
        ];

        if (!empty($coinIds)) {
            $params['ids'] = implode(',', $coinIds);
        }

        if ($priceChangePercentage) {
            $params['price_change_percentage'] = $priceChangePercentage;
        }

        $resourceId = !empty($coinIds) ? 
            'custom_' . md5(implode(',', $coinIds)) : 
            "page_{$page}_{$perPage}";

        return $this->cacheService->cacheOrRetrieve(
            'coingecko',
            'coins/markets',
            'market_data',
            fn() => $this->makeApiCall('coins/markets', $params),
            $params,
            $resourceId,
            1800, // 30 minutes TTL for market data
            [
                'vs_currency' => $vsCurrency,
                'page' => $page,
                'per_page' => $perPage,
                'custom_ids' => !empty($coinIds),
                'coin_count' => count($coinIds),
            ]
        );
    }

    /**
     * Search for coins, exchanges, and categories.
     */
    public function search(string $query): array
    {
        $params = ['query' => $query];

        return $this->cacheService->cacheOrRetrieve(
            'coingecko',
            'search',
            'search_results',
            fn() => $this->makeApiCall('search', $params),
            $params,
            md5($query),
            3600, // 1 hour TTL for search results
            [
                'query' => $query,
                'query_length' => strlen($query),
            ]
        );
    }

    /**
     * Get trending coins.
     */
    public function getTrending(): array
    {
        return $this->cacheService->cacheOrRetrieve(
            'coingecko',
            'search/trending',
            'trending',
            fn() => $this->makeApiCall('search/trending'),
            [],
            'trending_coins',
            1800, // 30 minutes TTL for trending data
            [
                'type' => 'trending_coins',
            ]
        );
    }

    /**
     * Get global cryptocurrency market data.
     */
    public function getGlobalMarketData(): array
    {
        return $this->cacheService->cacheOrRetrieve(
            'coingecko',
            'global',
            'global_market_data',
            fn() => $this->makeApiCall('global'),
            [],
            'global',
            900, // 15 minutes TTL for global data
            [
                'type' => 'global_market_data',
            ]
        );
    }

    /**
     * Batch fetch multiple coin prices efficiently.
     */
    public function batchGetPrices(
        array $coinIds,
        string $vsCurrency = 'usd',
        int $batchSize = 50
    ): array {
        $results = [];
        $batches = array_chunk($coinIds, $batchSize);
        
        foreach ($batches as $batch) {
            $batchResults = $this->getCurrentPrice($batch, $vsCurrency);
            $results = array_merge($results, $batchResults);
            
            // Respect rate limits
            if (count($batches) > 1) {
                usleep(self::RATE_LIMIT_DELAY * 1000); // Convert to microseconds
            }
        }
        
        return $results;
    }

    /**
     * Warm cache for a list of popular coins.
     */
    public function warmPopularCoins(array $coinIds = null): int
    {
        $defaultCoins = [
            'bitcoin', 'ethereum', 'binancecoin', 'cardano', 'solana',
            'polkadot', 'dogecoin', 'avalanche-2', 'polygon-ecosystem-token',
            'chainlink', 'uniswap', 'litecoin', 'bitcoin-cash', 'algorand'
        ];
        
        $coinsToWarm = $coinIds ?? $defaultCoins;
        $warmed = 0;
        
        foreach ($coinsToWarm as $coinId) {
            try {
                // Warm current price
                $this->cacheService->warmCache(
                    'coingecko',
                    'simple/price',
                    'price',
                    fn() => $this->makeApiCall('simple/price', [
                        'ids' => $coinId,
                        'vs_currencies' => 'usd',
                        'include_24hr_change' => 'true',
                        'include_market_cap' => 'true',
                    ]),
                    ['ids' => $coinId, 'vs_currencies' => 'usd'],
                    $coinId
                );
                
                // Warm coin info
                $this->cacheService->warmCache(
                    'coingecko',
                    "coins/{$coinId}",
                    'coin_info',
                    fn() => $this->makeApiCall("coins/{$coinId}", [
                        'market_data' => 'true',
                        'localization' => 'false',
                    ]),
                    [],
                    $coinId
                );
                
                $warmed++;
                
                // Rate limiting
                usleep(self::RATE_LIMIT_DELAY * 1000);
                
            } catch (\Exception $e) {
                Log::warning("Failed to warm cache for coin: {$coinId}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        Log::info("Warmed cache for {$warmed} popular coins");
        
        return $warmed;
    }

    /**
     * Get rate limit status and recommendations.
     */
    public function getRateLimitStatus(): array
    {
        $stats = $this->cacheService->getStatisticsForApiSource('coingecko');
        
        return [
            'api_source' => 'coingecko',
            'cache_hit_ratio' => $stats['total_hits'] > 0 ? 
                round(($stats['total_hits'] / ($stats['total_entries'] + $stats['total_hits'])) * 100, 2) : 0,
            'total_api_calls_saved' => $stats['total_hits'],
            'estimated_cost_saved' => $stats['total_hits'] * 0.01, // Estimate $0.01 per call
            'recommendations' => $this->generateRateLimitRecommendations($stats),
        ];
    }

    /**
     * Make HTTP request to CoinGecko API with error handling.
     */
    private function makeApiCall(string $endpoint, array $params = []): array
    {
        $url = self::BASE_URL . '/' . ltrim($endpoint, '/');
        
        Log::debug("CoinGecko API call", [
            'endpoint' => $endpoint,
            'params' => $params,
        ]);
        
        $response = Http::timeout(30)
            ->retry(3, 1000)
            ->get($url, $params);
        
        if (!$response->successful()) {
            $error = "CoinGecko API error: HTTP {$response->status()}";
            
            if ($response->status() === 429) {
                $error .= " - Rate limit exceeded";
            }
            
            throw new \Exception($error);
        }
        
        return $response->json();
    }

    /**
     * Generate rate limit optimization recommendations.
     */
    private function generateRateLimitRecommendations(array $stats): array
    {
        $recommendations = [];
        
        $hitRatio = $stats['total_hits'] > 0 ? 
            ($stats['total_hits'] / ($stats['total_entries'] + $stats['total_hits'])) * 100 : 0;
        
        if ($hitRatio < 50) {
            $recommendations[] = 'Consider increasing cache TTL for price data';
        }
        
        if ($stats['total_entries'] > 1000) {
            $recommendations[] = 'Large number of cached entries - consider cleanup';
        }
        
        if (empty($recommendations)) {
            $recommendations[] = 'Cache performance is optimal';
        }
        
        return $recommendations;
    }
}
