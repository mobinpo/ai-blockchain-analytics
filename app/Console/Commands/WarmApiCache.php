<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PostgresCacheService;
use App\Services\CoinGeckoService;
use App\Models\DemoCacheData;
use Illuminate\Support\Facades\Log;

class WarmApiCache extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cache:warm-api 
                            {--service=all : Which service to warm (all, coingecko, demo)}
                            {--demo-only : Only warm demo data, skip real API calls}
                            {--force : Force refresh even if cache exists}';

    /**
     * The console command description.
     */
    protected $description = 'Warm API cache with demo data for booth presentations';

    public function __construct(
        protected PostgresCacheService $cache,
        protected CoinGeckoService $coingecko
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $service = $this->option('service');
        $demoOnly = $this->option('demo-only');
        $force = $this->option('force');

        $this->info('🚀 Starting API cache warming...');
        $this->newLine();

        try {
            if ($force) {
                $this->warn('🔥 Force mode: Will overwrite existing cache entries');
                $this->newLine();
            }

            // Warm demo data
            if ($service === 'all' || $service === 'demo') {
                $this->warmDemoData($force);
            }

            // Warm Coingecko cache
            if (!$demoOnly && ($service === 'all' || $service === 'coingecko')) {
                $this->warmCoingeckoCache($force);
            }

            // Show cache statistics
            $this->displayCacheStats();

            $this->newLine();
            $this->info('✅ Cache warming completed successfully!');
            
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Cache warming failed: ' . $e->getMessage());
            Log::error('Cache warming failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return Command::FAILURE;
        }
    }

    /**
     * Warm demo data cache.
     */
    private function warmDemoData(bool $force): void
    {
        $this->info('📊 Warming demo data cache...');

        // Initialize demo data
        DemoCacheData::initializeDemoData();

        // Warm PostgreSQL cache with demo data
        $this->cache->warmDemoCache();

        $this->line('   ✓ Demo statistics initialized');
        $this->line('   ✓ Live threats data ready');
        $this->line('   ✓ Activity stream prepared');
        $this->line('   ✓ Performance metrics set');
        $this->newLine();
    }

    /**
     * Warm Coingecko API cache.
     */
    private function warmCoingeckoCache(bool $force): void
    {
        $this->info('💰 Warming Coingecko API cache...');

        $popularCoins = ['bitcoin', 'ethereum', 'cardano', 'solana', 'polygon'];
        $progressBar = $this->output->createProgressBar(count($popularCoins) * 2 + 2);
        $progressBar->start();

        foreach ($popularCoins as $coin) {
            try {
                // Warm current prices
                if ($force || !$this->cache->get('coingecko', 'price_current', ['coin_ids' => $coin])) {
                    $this->coingecko->getCurrentPrices([$coin]);
                }
                $progressBar->advance();

                // Warm price history
                if ($force || !$this->cache->get('coingecko', 'price_history', [
                    'coin_id' => $coin,
                    'start_date' => now()->subDays(30)->format('Y-m-d'),
                    'end_date' => now()->format('Y-m-d'),
                ])) {
                    $this->coingecko->getPriceHistory($coin, now()->subDays(30), now());
                }
                $progressBar->advance();

            } catch (\Exception $e) {
                $this->warn("   ⚠ Failed to warm cache for {$coin}: {$e->getMessage()}");
            }
        }

        // Warm coins list
        try {
            if ($force || !$this->cache->get('coingecko', 'coins_list', [])) {
                $this->coingecko->getSupportedCoins();
            }
            $progressBar->advance();
        } catch (\Exception $e) {
            $this->warn("   ⚠ Failed to warm coins list: {$e->getMessage()}");
        }

        // Warm search cache for popular terms
        $searchTerms = ['bitcoin', 'ethereum', 'defi'];
        foreach ($searchTerms as $term) {
            try {
                if ($force || !$this->cache->get('coingecko', 'coin_search', ['query' => $term])) {
                    $this->coingecko->searchCoins($term);
                }
            } catch (\Exception $e) {
                $this->warn("   ⚠ Failed to warm search for {$term}: {$e->getMessage()}");
            }
        }
        $progressBar->advance();

        $progressBar->finish();
        $this->newLine();
        $this->line('   ✓ Current prices cached');
        $this->line('   ✓ Price history cached');
        $this->line('   ✓ Coins list cached');
        $this->line('   ✓ Search results cached');
        $this->newLine();
    }

    /**
     * Display cache statistics.
     */
    private function displayCacheStats(): void
    {
        $this->info('📈 Cache Statistics:');
        
        $stats = $this->cache->getStats();
        
        // API Cache stats
        $apiStats = $stats['api_cache'];
        $this->line("   📦 API Cache:");
        $this->line("      Total entries: {$apiStats['total_entries']}");
        $this->line("      Valid entries: {$apiStats['valid_entries']}");
        $this->line("      Demo entries: {$apiStats['demo_entries']}");
        $this->line("      Cache size: {$apiStats['cache_size_mb']} MB");
        $this->line("      Services: " . implode(', ', $apiStats['services']));

        // Demo Cache stats
        $demoStats = $stats['demo_cache'];
        $this->line("   🎭 Demo Cache:");
        $this->line("      Total entries: {$demoStats['total_entries']}");
        $this->line("      Active entries: {$demoStats['active_entries']}");
        $this->line("      Stale entries: {$demoStats['stale_entries']}");
        $this->line("      Data types: " . implode(', ', $demoStats['data_types']));
        $this->line("      Cache size: {$demoStats['total_size_kb']} KB");
    }
}