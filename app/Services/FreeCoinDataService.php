<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

/**
 * Free Cryptocurrency Data Service
 * Combines multiple free APIs for comprehensive market data
 */
final class FreeCoinDataService
{
    private array $freeApis;
    private int $timeout;

    public function __construct()
    {
        $this->timeout = 30;
        $this->freeApis = [
            'coincap' => 'https://api.coincap.io/v2',
            'coingecko_free' => 'https://api.coingecko.com/api/v3',
            'coinlore' => 'https://api.coinlore.net/api',
            'cryptocompare_free' => 'https://min-api.cryptocompare.com/data'
        ];
    }

    /**
     * Get current price for a cryptocurrency
     */
    public function getCurrentPrice(string $coinId): array
    {
        $cacheKey = "free_price_{$coinId}";
        
        return Cache::remember($cacheKey, 300, function () use ($coinId) {
            $prices = [];
            
            // Try CoinCap API first (highest rate limit)
            try {
                $price = $this->getPriceFromCoinCap($coinId);
                if ($price) $prices['coincap'] = $price;
            } catch (\Exception $e) {
                Log::warning('CoinCap API failed', ['error' => $e->getMessage()]);
            }

            // Try CoinGecko free tier
            try {
                $price = $this->getPriceFromCoinGecko($coinId);
                if ($price) $prices['coingecko'] = $price;
            } catch (\Exception $e) {
                Log::warning('CoinGecko free API failed', ['error' => $e->getMessage()]);
            }

            // Try CryptoCompare free tier
            try {
                $price = $this->getPriceFromCryptoCompare($coinId);
                if ($price) $prices['cryptocompare'] = $price;
            } catch (\Exception $e) {
                Log::warning('CryptoCompare free API failed', ['error' => $e->getMessage()]);
            }

            return $this->aggregatePriceData($coinId, $prices);
        });
    }

    /**
     * Get historical price data
     */
    public function getHistoricalData(string $coinId, int $days = 30): array
    {
        $cacheKey = "free_historical_{$coinId}_{$days}";
        
        return Cache::remember($cacheKey, 3600, function () use ($coinId, $days) {
            // Try CoinGecko first (best historical data)
            try {
                return $this->getHistoricalFromCoinGecko($coinId, $days);
            } catch (\Exception $e) {
                Log::warning('CoinGecko historical failed', ['error' => $e->getMessage()]);
            }

            // Fallback to CoinCap
            try {
                return $this->getHistoricalFromCoinCap($coinId, $days);
            } catch (\Exception $e) {
                Log::warning('CoinCap historical failed', ['error' => $e->getMessage()]);
            }

            return $this->getEmptyHistoricalData($coinId);
        });
    }

    /**
     * Get market data for multiple coins
     */
    public function getMarketData(array $coinIds): array
    {
        $results = [];
        
        foreach ($coinIds as $coinId) {
            $results[$coinId] = $this->getCurrentPrice($coinId);
        }

        return [
            'success' => true,
            'data' => $results,
            'source' => 'free_apis_combined',
            'cost' => 0.00,
            'rate_limit_info' => [
                'coincap' => '200 requests/minute',
                'coingecko' => '10-50 requests/minute',
                'cryptocompare' => '100,000 requests/month'
            ]
        ];
    }

    /**
     * Get price from CoinCap API
     */
    private function getPriceFromCoinCap(string $coinId): ?array
    {
        $coinCapId = $this->mapToCoinCapId($coinId);
        
        $response = Http::timeout($this->timeout)
            ->get("{$this->freeApis['coincap']}/assets/{$coinCapId}");

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json('data');
        
        if (!$data) return null;

        return [
            'price_usd' => (float) $data['priceUsd'],
            'market_cap' => (float) $data['marketCapUsd'],
            'volume_24h' => (float) $data['volumeUsd24Hr'],
            'change_24h' => (float) $data['changePercent24Hr'],
            'timestamp' => time(),
            'source' => 'coincap'
        ];
    }

    /**
     * Get price from CoinGecko free API
     */
    private function getPriceFromCoinGecko(string $coinId): ?array
    {
        $response = Http::timeout($this->timeout)
            ->get("{$this->freeApis['coingecko_free']}/simple/price", [
                'ids' => $coinId,
                'vs_currencies' => 'usd',
                'include_market_cap' => 'true',
                'include_24hr_vol' => 'true',
                'include_24hr_change' => 'true'
            ]);

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();
        
        if (!isset($data[$coinId])) return null;

        $coinData = $data[$coinId];

        return [
            'price_usd' => (float) $coinData['usd'],
            'market_cap' => (float) ($coinData['usd_market_cap'] ?? 0),
            'volume_24h' => (float) ($coinData['usd_24h_vol'] ?? 0),
            'change_24h' => (float) ($coinData['usd_24h_change'] ?? 0),
            'timestamp' => time(),
            'source' => 'coingecko_free'
        ];
    }

    /**
     * Get price from CryptoCompare free API
     */
    private function getPriceFromCryptoCompare(string $coinId): ?array
    {
        $symbol = $this->mapToCryptoCompareSymbol($coinId);
        
        $response = Http::timeout($this->timeout)
            ->get("{$this->freeApis['cryptocompare_free']}/pricemultifull", [
                'fsyms' => $symbol,
                'tsyms' => 'USD'
            ]);

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();
        
        if (!isset($data['RAW'][$symbol]['USD'])) return null;

        $coinData = $data['RAW'][$symbol]['USD'];

        return [
            'price_usd' => (float) $coinData['PRICE'],
            'market_cap' => (float) ($coinData['MKTCAP'] ?? 0),
            'volume_24h' => (float) ($coinData['VOLUME24HOUR'] ?? 0),
            'change_24h' => (float) ($coinData['CHANGEPCT24HOUR'] ?? 0),
            'timestamp' => time(),
            'source' => 'cryptocompare_free'
        ];
    }

    /**
     * Get historical data from CoinGecko
     */
    private function getHistoricalFromCoinGecko(string $coinId, int $days): array
    {
        $response = Http::timeout($this->timeout)
            ->get("{$this->freeApis['coingecko_free']}/coins/{$coinId}/market_chart", [
                'vs_currency' => 'usd',
                'days' => $days,
                'interval' => $days > 90 ? 'daily' : 'hourly'
            ]);

        if (!$response->successful()) {
            throw new \Exception('CoinGecko historical request failed');
        }

        $data = $response->json();
        
        $prices = [];
        foreach ($data['prices'] ?? [] as $pricePoint) {
            $prices[] = [
                'timestamp' => $pricePoint[0],
                'price' => $pricePoint[1],
                'date' => Carbon::createFromTimestampMs($pricePoint[0])->toISOString()
            ];
        }

        return [
            'success' => true,
            'coin_id' => $coinId,
            'days' => $days,
            'prices' => $prices,
            'source' => 'coingecko_free',
            'cost' => 0.00
        ];
    }

    /**
     * Get historical data from CoinCap
     */
    private function getHistoricalFromCoinCap(string $coinId, int $days): array
    {
        $coinCapId = $this->mapToCoinCapId($coinId);
        $interval = $days > 30 ? 'd1' : 'h1';
        $start = Carbon::now()->subDays($days)->timestamp * 1000;
        $end = Carbon::now()->timestamp * 1000;
        
        $response = Http::timeout($this->timeout)
            ->get("{$this->freeApis['coincap']}/assets/{$coinCapId}/history", [
                'interval' => $interval,
                'start' => $start,
                'end' => $end
            ]);

        if (!$response->successful()) {
            throw new \Exception('CoinCap historical request failed');
        }

        $data = $response->json('data', []);
        
        $prices = [];
        foreach ($data as $pricePoint) {
            $prices[] = [
                'timestamp' => $pricePoint['time'],
                'price' => (float) $pricePoint['priceUsd'],
                'date' => Carbon::createFromTimestampMs($pricePoint['time'])->toISOString()
            ];
        }

        return [
            'success' => true,
            'coin_id' => $coinId,
            'days' => $days,
            'prices' => $prices,
            'source' => 'coincap',
            'cost' => 0.00
        ];
    }

    /**
     * Aggregate price data from multiple sources
     */
    private function aggregatePriceData(string $coinId, array $prices): array
    {
        if (empty($prices)) {
            return [
                'success' => false,
                'coin_id' => $coinId,
                'error' => 'No price data available from any source',
                'cost' => 0.00
            ];
        }

        // Use the most reliable source first
        $sourceOrder = ['coincap', 'coingecko', 'cryptocompare'];
        $bestPrice = null;
        
        foreach ($sourceOrder as $source) {
            if (isset($prices[$source])) {
                $bestPrice = $prices[$source];
                break;
            }
        }

        // Calculate average if multiple sources available
        if (count($prices) > 1) {
            $avgPrice = array_sum(array_column($prices, 'price_usd')) / count($prices);
            $bestPrice['average_price'] = $avgPrice;
            $bestPrice['sources_count'] = count($prices);
        }

        return [
            'success' => true,
            'coin_id' => $coinId,
            'data' => $bestPrice,
            'all_sources' => $prices,
            'cost' => 0.00,
            'api_limits' => 'Using free tiers only'
        ];
    }

    /**
     * Map CoinGecko ID to CoinCap ID
     */
    private function mapToCoinCapId(string $coinGeckoId): string
    {
        $mapping = [
            'bitcoin' => 'bitcoin',
            'ethereum' => 'ethereum',
            'binancecoin' => 'binance-coin',
            'cardano' => 'cardano',
            'solana' => 'solana',
            'polkadot' => 'polkadot',
            'chainlink' => 'chainlink',
            'uniswap' => 'uniswap'
        ];

        return $mapping[$coinGeckoId] ?? $coinGeckoId;
    }

    /**
     * Map CoinGecko ID to CryptoCompare symbol
     */
    private function mapToCryptoCompareSymbol(string $coinGeckoId): string
    {
        $mapping = [
            'bitcoin' => 'BTC',
            'ethereum' => 'ETH',
            'binancecoin' => 'BNB',
            'cardano' => 'ADA',
            'solana' => 'SOL',
            'polkadot' => 'DOT',
            'chainlink' => 'LINK',
            'uniswap' => 'UNI'
        ];

        return $mapping[$coinGeckoId] ?? strtoupper($coinGeckoId);
    }

    /**
     * Get empty historical data structure
     */
    private function getEmptyHistoricalData(string $coinId): array
    {
        return [
            'success' => false,
            'coin_id' => $coinId,
            'days' => 0,
            'prices' => [],
            'error' => 'Historical data unavailable',
            'cost' => 0.00
        ];
    }
}
