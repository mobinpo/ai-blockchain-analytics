<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

/**
 * Enhanced Free Cryptocurrency Data Service
 * Combines multiple free APIs including those with trials and free tiers
 */
final class EnhancedFreeCryptoDataService
{
    private array $freeApis;
    private array $trialApis;
    private int $timeout;

    public function __construct()
    {
        $this->timeout = 30;
        
        // 100% Free APIs (no signup required)
        $this->freeApis = [
            'coincap' => [
                'url' => 'https://api.coincap.io/v2',
                'rate_limit' => '200 requests/minute',
                'features' => ['price', 'historical', 'markets']
            ],
            'coingecko_free' => [
                'url' => 'https://api.coingecko.com/api/v3',
                'rate_limit' => '10-50 requests/minute',
                'features' => ['price', 'historical', 'markets', 'trending']
            ],
            'coinlore' => [
                'url' => 'https://api.coinlore.net/api',
                'rate_limit' => 'unlimited',
                'features' => ['price', 'markets']
            ],
            'binance_public' => [
                'url' => 'https://api.binance.com/api/v3',
                'rate_limit' => '1200 requests/minute',
                'features' => ['price', 'ticker', 'markets']
            ],
            'bitfinex_public' => [
                'url' => 'https://api-pub.bitfinex.com/v2',
                'rate_limit' => '90 requests/minute',
                'features' => ['price', 'ticker']
            ]
        ];

        // Free tier/trial APIs (require signup but have generous free limits)
        $this->trialApis = [
            'cryptocompare_free' => [
                'url' => 'https://min-api.cryptocompare.com/data',
                'key' => env('CRYPTOCOMPARE_API_KEY', ''),
                'free_limit' => '100,000 requests/month',
                'features' => ['price', 'historical', 'news', 'social']
            ],
            'messari' => [
                'url' => 'https://data.messari.io/api',
                'key' => env('MESSARI_API_KEY', ''),
                'free_limit' => '20 requests/minute, 1000/month',
                'features' => ['price', 'metrics', 'news']
            ],
            'nomics' => [
                'url' => 'https://api.nomics.com/v1',
                'key' => env('NOMICS_API_KEY', ''),
                'free_limit' => '1 request/second, 100k/month',
                'features' => ['price', 'historical', 'volume']
            ],
            'alpaca_crypto' => [
                'url' => 'https://data.alpaca.markets/v1beta1/crypto',
                'key' => env('ALPACA_API_KEY', ''),
                'free_limit' => '200 requests/minute',
                'features' => ['price', 'historical']
            ]
        ];
    }

    /**
     * Get current price with multiple sources
     */
    public function getCurrentPrice(string $coinId): array
    {
        $cacheKey = "enhanced_price_{$coinId}";
        
        return Cache::remember($cacheKey, 300, function () use ($coinId) {
            $prices = [];
            
            // Try free APIs first (no API key required)
            $prices = array_merge($prices, $this->getPricesFromFreeApis($coinId));
            
            // Try trial/free tier APIs (if API keys available)
            $prices = array_merge($prices, $this->getPricesFromTrialApis($coinId));
            
            return $this->aggregatePriceData($coinId, $prices);
        });
    }

    /**
     * Get historical data with multiple sources
     */
    public function getHistoricalData(string $coinId, int $days = 30): array
    {
        $cacheKey = "enhanced_historical_{$coinId}_{$days}";
        
        return Cache::remember($cacheKey, 3600, function () use ($coinId, $days) {
            // Try APIs in order of reliability for historical data
            $sources = [
                'coingecko_free' => fn() => $this->getHistoricalFromCoinGecko($coinId, $days),
                'cryptocompare' => fn() => $this->getHistoricalFromCryptoCompare($coinId, $days),
                'coincap' => fn() => $this->getHistoricalFromCoinCap($coinId, $days),
                'messari' => fn() => $this->getHistoricalFromMessari($coinId, $days)
            ];

            foreach ($sources as $source => $getter) {
                try {
                    $result = $getter();
                    if ($result['success'] ?? false) {
                        return $result;
                    }
                } catch (\Exception $e) {
                    Log::warning("Historical data from {$source} failed", ['error' => $e->getMessage()]);
                }
            }

            return $this->getEmptyHistoricalData($coinId);
        });
    }

    /**
     * Get trending coins from multiple sources
     */
    public function getTrendingCoins(): array
    {
        $cacheKey = 'enhanced_trending_coins';
        
        return Cache::remember($cacheKey, 1800, function () {
            $trending = [];
            
            // CoinGecko trending
            try {
                $trending['coingecko'] = $this->getTrendingFromCoinGecko();
            } catch (\Exception $e) {
                Log::warning('CoinGecko trending failed', ['error' => $e->getMessage()]);
            }

            // CoinCap top gaining
            try {
                $trending['coincap'] = $this->getTrendingFromCoinCap();
            } catch (\Exception $e) {
                Log::warning('CoinCap trending failed', ['error' => $e->getMessage()]);
            }

            // Messari trending (if API key available)
            if (!empty($this->trialApis['messari']['key'])) {
                try {
                    $trending['messari'] = $this->getTrendingFromMessari();
                } catch (\Exception $e) {
                    Log::warning('Messari trending failed', ['error' => $e->getMessage()]);
                }
            }

            return [
                'success' => !empty($trending),
                'sources' => array_keys($trending),
                'data' => $trending,
                'cost' => 0.00
            ];
        });
    }

    /**
     * Get prices from free APIs (no signup required)
     */
    private function getPricesFromFreeApis(string $coinId): array
    {
        $prices = [];

        // CoinCap
        try {
            $price = $this->getPriceFromCoinCap($coinId);
            if ($price) $prices['coincap'] = $price;
        } catch (\Exception $e) {
            Log::warning('CoinCap failed', ['error' => $e->getMessage()]);
        }

        // CoinGecko Free
        try {
            $price = $this->getPriceFromCoinGecko($coinId);
            if ($price) $prices['coingecko'] = $price;
        } catch (\Exception $e) {
            Log::warning('CoinGecko failed', ['error' => $e->getMessage()]);
        }

        // Binance Public API
        try {
            $price = $this->getPriceFromBinance($coinId);
            if ($price) $prices['binance'] = $price;
        } catch (\Exception $e) {
            Log::warning('Binance public API failed', ['error' => $e->getMessage()]);
        }

        // CoinLore
        try {
            $price = $this->getPriceFromCoinLore($coinId);
            if ($price) $prices['coinlore'] = $price;
        } catch (\Exception $e) {
            Log::warning('CoinLore failed', ['error' => $e->getMessage()]);
        }

        return $prices;
    }

    /**
     * Get prices from trial/free tier APIs
     */
    private function getPricesFromTrialApis(string $coinId): array
    {
        $prices = [];

        // CryptoCompare (free tier)
        if (!empty($this->trialApis['cryptocompare_free']['key'])) {
            try {
                $price = $this->getPriceFromCryptoCompare($coinId);
                if ($price) $prices['cryptocompare'] = $price;
            } catch (\Exception $e) {
                Log::warning('CryptoCompare failed', ['error' => $e->getMessage()]);
            }
        }

        // Messari (free tier)
        if (!empty($this->trialApis['messari']['key'])) {
            try {
                $price = $this->getPriceFromMessari($coinId);
                if ($price) $prices['messari'] = $price;
            } catch (\Exception $e) {
                Log::warning('Messari failed', ['error' => $e->getMessage()]);
            }
        }

        // Nomics (free tier)
        if (!empty($this->trialApis['nomics']['key'])) {
            try {
                $price = $this->getPriceFromNomics($coinId);
                if ($price) $prices['nomics'] = $price;
            } catch (\Exception $e) {
                Log::warning('Nomics failed', ['error' => $e->getMessage()]);
            }
        }

        return $prices;
    }

    /**
     * Get price from Binance public API
     */
    private function getPriceFromBinance(string $coinId): ?array
    {
        $symbol = $this->mapToTradingSymbol($coinId) . 'USDT';
        
        $response = Http::timeout($this->timeout)
            ->get("{$this->freeApis['binance_public']['url']}/ticker/24hr", [
                'symbol' => $symbol
            ]);

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();

        return [
            'price_usd' => (float) $data['lastPrice'],
            'volume_24h' => (float) $data['volume'],
            'change_24h' => (float) $data['priceChangePercent'],
            'high_24h' => (float) $data['highPrice'],
            'low_24h' => (float) $data['lowPrice'],
            'timestamp' => time(),
            'source' => 'binance_public'
        ];
    }

    /**
     * Get price from CoinLore
     */
    private function getPriceFromCoinLore(string $coinId): ?array
    {
        $coinLoreId = $this->mapToCoinLoreId($coinId);
        
        $response = Http::timeout($this->timeout)
            ->get("{$this->freeApis['coinlore']['url']}/ticker/", [
                'id' => $coinLoreId
            ]);

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();
        
        if (empty($data) || !isset($data[0])) {
            return null;
        }

        $coinData = $data[0];

        return [
            'price_usd' => (float) $coinData['price_usd'],
            'market_cap' => (float) $coinData['market_cap_usd'],
            'volume_24h' => (float) $coinData['volume24'],
            'change_24h' => (float) $coinData['percent_change_24h'],
            'timestamp' => time(),
            'source' => 'coinlore'
        ];
    }

    /**
     * Get price from Messari (free tier)
     */
    private function getPriceFromMessari(string $coinId): ?array
    {
        $response = Http::timeout($this->timeout)
            ->withHeaders(['x-messari-api-key' => $this->trialApis['messari']['key']])
            ->get("{$this->trialApis['messari']['url']}/v1/assets/{$coinId}/metrics");

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json('data');
        
        if (!$data || !isset($data['market_data'])) {
            return null;
        }

        $marketData = $data['market_data'];

        return [
            'price_usd' => (float) $marketData['price_usd'],
            'market_cap' => (float) $marketData['marketcap']['current_marketcap_usd'],
            'volume_24h' => (float) $marketData['volume_last_24_hours'],
            'change_24h' => (float) $marketData['percent_change_usd_last_24_hours'],
            'timestamp' => time(),
            'source' => 'messari'
        ];
    }

    /**
     * Get price from Nomics (free tier)
     */
    private function getPriceFromNomics(string $coinId): ?array
    {
        $symbol = $this->mapToTradingSymbol($coinId);
        
        $response = Http::timeout($this->timeout)
            ->get("{$this->trialApis['nomics']['url']}/currencies/ticker", [
                'key' => $this->trialApis['nomics']['key'],
                'ids' => $symbol,
                'interval' => '1d'
            ]);

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();
        
        if (empty($data) || !isset($data[0])) {
            return null;
        }

        $coinData = $data[0];

        return [
            'price_usd' => (float) $coinData['price'],
            'market_cap' => (float) ($coinData['market_cap'] ?? 0),
            'volume_24h' => (float) ($coinData['1d']['volume'] ?? 0),
            'change_24h' => (float) ($coinData['1d']['price_change_pct'] ?? 0) * 100,
            'timestamp' => time(),
            'source' => 'nomics'
        ];
    }

    /**
     * Get trending from CoinGecko
     */
    private function getTrendingFromCoinGecko(): array
    {
        $response = Http::timeout($this->timeout)
            ->get("{$this->freeApis['coingecko_free']['url']}/search/trending");

        if (!$response->successful()) {
            throw new \Exception('CoinGecko trending request failed');
        }

        $data = $response->json();
        
        return [
            'coins' => $data['coins'] ?? [],
            'source' => 'coingecko',
            'timestamp' => time()
        ];
    }

    /**
     * Get trending from CoinCap (top gaining)
     */
    private function getTrendingFromCoinCap(): array
    {
        $response = Http::timeout($this->timeout)
            ->get("{$this->freeApis['coincap']['url']}/assets", [
                'limit' => 20
            ]);

        if (!$response->successful()) {
            throw new \Exception('CoinCap trending request failed');
        }

        $data = $response->json('data', []);
        
        // Sort by 24h change
        usort($data, function($a, $b) {
            return ($b['changePercent24Hr'] ?? 0) <=> ($a['changePercent24Hr'] ?? 0);
        });

        return [
            'coins' => array_slice($data, 0, 10),
            'source' => 'coincap',
            'timestamp' => time()
        ];
    }

    /**
     * Map coin IDs to different services
     */
    private function mapToTradingSymbol(string $coinId): string
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

        return $mapping[$coinId] ?? strtoupper($coinId);
    }

    /**
     * Map to CoinLore IDs
     */
    private function mapToCoinLoreId(string $coinId): string
    {
        $mapping = [
            'bitcoin' => '90',
            'ethereum' => '80',
            'binancecoin' => '2710',
            'cardano' => '257',
            'solana' => '48543',
            'polkadot' => '2577',
            'chainlink' => '1494',
            'uniswap' => '7083'
        ];

        return $mapping[$coinId] ?? '90'; // Default to Bitcoin
    }

    /**
     * Additional methods from parent class...
     */
    private function getPriceFromCoinCap(string $coinId): ?array
    {
        // Implementation from parent FreeCoinDataService
        $coinCapId = $this->mapToCoinCapId($coinId);
        
        $response = Http::timeout($this->timeout)
            ->get("{$this->freeApis['coincap']['url']}/assets/{$coinCapId}");

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

    private function getPriceFromCoinGecko(string $coinId): ?array
    {
        $response = Http::timeout($this->timeout)
            ->get("{$this->freeApis['coingecko_free']['url']}/simple/price", [
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

    private function getPriceFromCryptoCompare(string $coinId): ?array
    {
        $symbol = $this->mapToTradingSymbol($coinId);
        
        $response = Http::timeout($this->timeout)
            ->get("{$this->trialApis['cryptocompare_free']['url']}/pricemultifull", [
                'fsyms' => $symbol,
                'tsyms' => 'USD',
                'api_key' => $this->trialApis['cryptocompare_free']['key']
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
        $sourceOrder = ['binance', 'coincap', 'coingecko', 'cryptocompare', 'messari', 'nomics', 'coinlore'];
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
            'api_limits' => 'Using free tiers and trials only'
        ];
    }

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

    // Placeholder methods for historical data - implementation similar to parent class
    private function getHistoricalFromCoinGecko(string $coinId, int $days): array { return ['success' => false]; }
    private function getHistoricalFromCryptoCompare(string $coinId, int $days): array { return ['success' => false]; }
    private function getHistoricalFromCoinCap(string $coinId, int $days): array { return ['success' => false]; }
    private function getHistoricalFromMessari(string $coinId, int $days): array { return ['success' => false]; }
    private function getTrendingFromMessari(): array { return []; }
}
