<?php

namespace App\Console\Commands;

use App\Models\ContractCache;
use App\Services\SourceCodeFetchingService;
use Illuminate\Console\Command;

class ManageContractCache extends Command
{
    protected $signature = 'cache:manage-contracts 
                          {action : Action: stats, warm, cleanup}
                          {--contracts= : Contract addresses (comma-separated)}
                          {--network=ethereum : Network}
                          {--priority=medium : Priority for warming}';

    protected $description = 'Manage contract cache to avoid API limits';

    private SourceCodeFetchingService $fetchingService;

    public function __construct(SourceCodeFetchingService $fetchingService)
    {
        parent::__construct();
        $this->fetchingService = $fetchingService;
    }

    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'stats' => $this->showStats(),
            'warm' => $this->warmContracts(),
            'cleanup' => $this->cleanup(),
            default => $this->showHelp()
        };
    }

    private function showStats(): int
    {
        $this->info('📊 PostgreSQL Cache Statistics');
        $this->newLine();

        try {
            $stats = $this->fetchingService->getComprehensiveCacheStatistics();
            
            if (isset($stats['database_cache_disabled'])) {
                $this->warn('❌ Database cache is disabled');
                return Command::SUCCESS;
            }

            $efficiency = $stats['cache_efficiency'];
            $this->table(
                ['Metric', 'Value'],
                [
                    ['📦 Total Cache Entries', number_format($efficiency['total_entries'])],
                    ['✅ Active Entries', number_format($efficiency['active_entries'])],
                    ['💾 API Calls Saved', number_format($efficiency['total_api_calls_saved'])],
                    ['⭐ Average Quality Score', $efficiency['average_quality_score'] . '/1.0'],
                    ['🔄 Refresh Queue Size', number_format($efficiency['refresh_queue_size'])]
                ]
            );

            if (isset($stats['system_health'])) {
                $health = $stats['system_health'];
                $this->newLine();
                $this->info('🏥 Cache Performance:');
                $this->table(
                    ['Metric', 'Value', 'Status'],
                    [
                        [
                            'Cache Hit Rate',
                            round($health['cache_hit_rate'], 1) . '%',
                            $health['cache_hit_rate'] > 80 ? '🟢 Excellent' : '🟡 Good'
                        ],
                        [
                            'API Success Rate',
                            round($health['recent_api_success_rate'], 1) . '%',
                            $health['recent_api_success_rate'] > 95 ? '🟢 Excellent' : '🟡 Good'
                        ]
                    ]
                );
            }

            $this->newLine();
            $this->comment('💡 High cache hit rate = fewer API calls = avoiding rate limits');

        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function warmContracts(): int
    {
        $contractsOption = $this->option('contracts');
        if (!$contractsOption) {
            $this->error('❌ --contracts option required');
            return Command::FAILURE;
        }

        $contracts = array_map('trim', explode(',', $contractsOption));
        $network = $this->option('network');
        $priority = $this->option('priority');

        $this->info("🔥 Warming PostgreSQL cache for " . count($contracts) . " contracts");
        $this->info("🌐 Network: {$network}");
        $this->newLine();

        $results = $this->fetchingService->batchQueueForCacheWarming($contracts, $network, $priority);

        $this->table(
            ['Result', 'Count'],
            [
                ['✅ Successfully Queued', $results['queued']],
                ['❌ Failed', $results['failed']],
                ['📊 Success Rate', round(($results['queued'] / count($contracts)) * 100, 1) . '%']
            ]
        );

        $this->newLine();
        $this->comment('💡 Contracts queued for background processing to populate cache');
        $this->comment('💡 Run "php artisan cache:warm-contracts" to process the queue');

        return Command::SUCCESS;
    }

    private function cleanup(): int
    {
        $this->info('🧹 Cleaning up expired cache entries...');
        
        $expiredCount = ContractCache::where('expires_at', '<', now())->count();
        
        if ($expiredCount === 0) {
            $this->info('✅ No expired entries to clean up');
            return Command::SUCCESS;
        }

        if ($this->confirm("Delete {$expiredCount} expired cache entries?")) {
            $deleted = ContractCache::where('expires_at', '<', now())->delete();
            $this->info("✅ Deleted {$deleted} expired entries");
        }

        return Command::SUCCESS;
    }

    private function showHelp(): int
    {
        $this->error('❌ Invalid action');
        $this->newLine();
        $this->info('Available actions:');
        $this->line('  stats   - Show PostgreSQL cache statistics');
        $this->line('  warm    - Queue contracts for cache warming');
        $this->line('  cleanup - Remove expired cache entries');
        $this->newLine();
        $this->info('Examples:');
        $this->line('  php artisan cache:manage-contracts stats');
        $this->line('  php artisan cache:manage-contracts warm --contracts=0x123...,0x456...');
        $this->line('  php artisan cache:manage-contracts cleanup');

        return Command::FAILURE;
    }
}