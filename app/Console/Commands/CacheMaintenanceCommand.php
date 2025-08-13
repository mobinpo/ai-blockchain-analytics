<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ApiCacheService;
use App\Services\CoinGeckoCacheService;
use App\Services\BlockchainCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class CacheMaintenanceCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cache:maintenance 
                            {--cleanup : Clean up expired cache entries}
                            {--aggressive : Use aggressive cleanup (removes low-efficiency entries)}
                            {--warm : Warm cache with popular data}
                            {--warm-coingecko : Warm CoinGecko cache}
                            {--warm-contracts : Warm blockchain contracts cache}
                            {--preload : Preload frequently accessed entries}
                            {--stats : Show cache statistics}
                            {--health : Perform health check}
                            {--all : Perform all maintenance tasks}';

    /**
     * The console command description.
     */
    protected $description = 'Perform cache maintenance tasks including cleanup, warming, and health checks';

    public function __construct(
        private readonly ApiCacheService $cacheService,
        private readonly CoinGeckoCacheService $coinGeckoService,
        private readonly BlockchainCacheService $blockchainService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔧 Starting cache maintenance...');
        
        $performAll = $this->option('all');
        $tasksPerformed = [];

        try {
            // Health Check
            if ($this->option('health') || $performAll) {
                $this->performHealthCheck();
                $tasksPerformed[] = 'health-check';
            }

            // Statistics
            if ($this->option('stats') || $performAll) {
                $this->showStatistics();
                $tasksPerformed[] = 'statistics';
            }

            // Cleanup
            if ($this->option('cleanup') || $performAll) {
                $this->performCleanup();
                $tasksPerformed[] = 'cleanup';
            }

            // Cache Warming
            if ($this->option('warm') || $this->option('warm-coingecko') || $performAll) {
                $this->warmCoinGeckoCache();
                $tasksPerformed[] = 'warm-coingecko';
            }

            if ($this->option('warm') || $this->option('warm-contracts') || $performAll) {
                $this->warmContractsCache();
                $tasksPerformed[] = 'warm-contracts';
            }

            // Preload frequently accessed
            if ($this->option('preload') || $performAll) {
                $this->preloadFrequentlyAccessed();
                $tasksPerformed[] = 'preload';
            }

            // Show final statistics if multiple tasks were performed
            if (count($tasksPerformed) > 1) {
                $this->newLine();
                $this->info('📊 Final cache statistics:');
                $this->showStatistics(false);
            }

            $this->newLine();
            $this->info('✅ Cache maintenance completed successfully!');
            $this->comment('Tasks performed: ' . implode(', ', $tasksPerformed));

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Cache maintenance failed: ' . $e->getMessage());
            Log::error('Cache maintenance command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return self::FAILURE;
        }
    }

    /**
     * Perform health check on cache system.
     */
    private function performHealthCheck(): void
    {
        $this->info('🏥 Performing health check...');
        
        $health = $this->cacheService->healthCheck();
        
        if ($health['status'] === 'healthy') {
            $this->info('✅ Cache system is healthy');
        } else {
            $this->warn('⚠️  Cache system needs attention');
            foreach ($health['issues'] as $issue) {
                $this->warn("  • {$issue}");
            }
            
            if (!empty($health['recommendations'])) {
                $this->comment('💡 Recommendations:');
                foreach ($health['recommendations'] as $recommendation) {
                    $this->comment("  • {$recommendation}");
                }
            }
        }
    }

    /**
     * Show comprehensive cache statistics.
     */
    private function showStatistics(bool $showHeader = true): void
    {
        if ($showHeader) {
            $this->info('📊 Cache statistics:');
        }
        
        $stats = $this->cacheService->getStatistics();
        $coinGeckoStats = $this->coinGeckoService->getRateLimitStatus();
        $blockchainStats = $this->blockchainService->getCacheStatistics();

        // Overall statistics
        $this->table([
            'Metric', 'Value'
        ], [
            ['Total Entries', number_format($stats['total_entries'])],
            ['Valid Entries', number_format($stats['valid_entries'])],
            ['Expired Entries', number_format($stats['expired_entries'])],
            ['Cache Hit Ratio', $stats['cache_hit_ratio'] . '%'],
            ['Cache Size', $stats['cache_size_mb'] . ' MB'],
            ['API Calls Saved', number_format($stats['api_cost_saved'])],
            ['Average Efficiency', $stats['efficiency_avg'] . '%'],
        ]);

        // API Sources breakdown
        if (!empty($stats['api_sources'])) {
            $this->newLine();
            $this->comment('API Sources:');
            $sourceData = [];
            
            foreach ($stats['api_sources'] as $source) {
                $sourceStats = $this->cacheService->getStatisticsForApiSource($source);
                $sourceData[] = [
                    $source,
                    number_format($sourceStats['total_entries']),
                    number_format($sourceStats['valid_entries']),
                    number_format($sourceStats['total_hits']),
                    $sourceStats['cache_size_mb'] . ' MB',
                ];
            }
            
            $this->table([
                'Source', 'Total', 'Valid', 'Hits', 'Size'
            ], $sourceData);
        }

        // CoinGecko specific stats
        $this->newLine();
        $this->comment('🪙 CoinGecko Performance:');
        $this->line("  Hit Ratio: {$coinGeckoStats['cache_hit_ratio']}%");
        $this->line("  API Calls Saved: " . number_format($coinGeckoStats['total_api_calls_saved']));
        $this->line("  Estimated Cost Saved: $" . number_format($coinGeckoStats['estimated_cost_saved'], 2));

        // Blockchain APIs stats
        $this->newLine();
        $this->comment('⛓️  Blockchain APIs Performance:');
        $this->line("  Etherscan Hits: " . number_format($blockchainStats['etherscan']['total_hits']));
        $this->line("  Moralis Hits: " . number_format($blockchainStats['moralis']['total_hits']));
        $this->line("  Total Saved: " . number_format($blockchainStats['total_blockchain_calls_saved']));
        $this->line("  Etherscan Cost Saved: $" . number_format($blockchainStats['estimated_cost_saved']['etherscan'], 4));
        $this->line("  Moralis Cost Saved: $" . number_format($blockchainStats['estimated_cost_saved']['moralis'], 3));
    }

    /**
     * Perform cache cleanup.
     */
    private function performCleanup(): void
    {
        $aggressive = $this->option('aggressive');
        $mode = $aggressive ? 'aggressive' : 'normal';
        
        $this->info("🧹 Performing {$mode} cache cleanup...");
        
        $stats = $this->cacheService->cleanup($aggressive);
        
        $this->info("✅ Cleanup completed:");
        $this->line("  • Deleted entries: " . number_format($stats['deleted']));
        $this->line("  • Space freed: {$stats['size_freed_mb']} MB");
        
        if ($aggressive && $stats['deleted'] > 0) {
            $this->comment('  • Aggressive cleanup removed low-efficiency entries');
        }
    }

    /**
     * Warm CoinGecko cache with popular coins.
     */
    private function warmCoinGeckoCache(): void
    {
        $this->info('🪙 Warming CoinGecko cache with popular coins...');
        
        $popularCoins = [
            'bitcoin', 'ethereum', 'binancecoin', 'cardano', 'solana',
            'polkadot', 'dogecoin', 'avalanche-2', 'polygon-ecosystem-token',
            'chainlink', 'uniswap', 'litecoin', 'bitcoin-cash', 'algorand',
            'cosmos', 'fantom', 'near', 'apecoin', 'the-sandbox', 'axie-infinity'
        ];
        
        try {
            $warmed = $this->coinGeckoService->warmPopularCoins($popularCoins);
            $this->info("✅ Warmed cache for {$warmed} popular coins");
        } catch (\Exception $e) {
            $this->warn("⚠️  CoinGecko cache warming failed: " . $e->getMessage());
        }
    }

    /**
     * Warm blockchain contracts cache.
     */
    private function warmContractsCache(): void
    {
        $this->info('⛓️  Warming blockchain contracts cache...');
        
        $popularContracts = [
            // Ethereum mainnet contracts
            '0xA0b86a33E6441f8C166768C8248906dEF09B2860', // Uniswap V3 Router
            '0x7d2768dE32b0b80b7a3454c06BdAc94A69DDc7A9', // Aave V2 Pool
            '0xC02aaA39b223FE8D0A0e5C4F27eAD9083C756Cc2', // WETH
            '0x6B175474E89094C44Da98b954EedeAC495271d0F', // DAI
            '0xA0b73E1Ff0B80914AB6fe0444E65848C4C34450b', // Compound USDC
            '0x1f9840a85d5aF5bf1D1762F925BDADdC4201F984', // Uniswap Token
            '0x7Fc66500c84A76Ad7e9c93437bFc5Ac33E2DDaE9', // AAVE Token
            '0x514910771AF9Ca656af840dff83E8264EcF986CA', // Chainlink Token
            '0x2260FAC5E5542a773Aa44fBCfeDf7C193bc2C599', // WBTC
            '0x95aD61b0a150d79219dCF64E1E6Cc01f0B64C4cE', // SHIB
        ];
        
        try {
            $warmed = $this->blockchainService->warmContractData($popularContracts);
            $this->info("✅ Warmed cache for {$warmed} popular contracts");
        } catch (\Exception $e) {
            $this->warn("⚠️  Contracts cache warming failed: " . $e->getMessage());
        }
    }

    /**
     * Preload frequently accessed cache entries.
     */
    private function preloadFrequentlyAccessed(): void
    {
        $this->info('🔄 Preloading frequently accessed cache entries...');
        
        try {
            $preloaded = $this->cacheService->preloadFrequentlyAccessed(200);
            $this->info("✅ Extended TTL for {$preloaded} frequently accessed entries");
        } catch (\Exception $e) {
            $this->warn("⚠️  Preloading failed: " . $e->getMessage());
        }
    }
}