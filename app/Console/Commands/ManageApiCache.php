<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PostgresCacheService;
use App\Models\ApiCache;
use App\Models\DemoCacheData;
use Illuminate\Support\Facades\Log;

class ManageApiCache extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cache:manage-api 
                            {action : Action to perform (stats, cleanup, clear, invalidate)}
                            {--service= : Service to target for clear/invalidate}
                            {--key= : Specific cache key to invalidate}
                            {--expired-only : Only cleanup expired entries}';

    /**
     * The console command description.
     */
    protected $description = 'Manage PostgreSQL API cache (stats, cleanup, clear, invalidate)';

    public function __construct(
        protected PostgresCacheService $cache
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'stats' => $this->showStats(),
            'cleanup' => $this->cleanupCache(),
            'clear' => $this->clearCache(),
            'invalidate' => $this->invalidateCache(),
            default => $this->invalidAction($action),
        };
    }

    /**
     * Show comprehensive cache statistics.
     */
    private function showStats(): int
    {
        $this->info('📊 PostgreSQL API Cache Statistics');
        $this->newLine();

        try {
            $stats = $this->cache->getStats();

            // API Cache Statistics
            $this->displayApiCacheStats($stats['api_cache']);
            $this->newLine();

            // Demo Cache Statistics
            $this->displayDemoCacheStats($stats['demo_cache']);
            $this->newLine();

            // Most accessed cache entries
            $this->displayTopAccessed($stats['api_cache']['most_accessed']);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Failed to retrieve cache stats: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Clean up expired cache entries.
     */
    private function cleanupCache(): int
    {
        $this->info('🧹 Cleaning up API cache...');

        try {
            $results = $this->cache->cleanup();

            $this->line("   ✓ Deleted {$results['api_entries_deleted']} expired API cache entries");
            $this->line("   ✓ Refreshed {$results['demo_entries_refreshed']} stale demo entries");

            $this->newLine();
            $this->info('✅ Cache cleanup completed successfully!');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Cache cleanup failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Clear cache for specific service or all.
     */
    private function clearCache(): int
    {
        $service = $this->option('service');

        if ($service) {
            $this->info("🗑️ Clearing cache for service: {$service}");
            
            try {
                $deleted = $this->cache->clearService($service);
                $this->line("   ✓ Deleted {$deleted} cache entries for {$service}");
                
                $this->newLine();
                $this->info('✅ Service cache cleared successfully!');

                return Command::SUCCESS;

            } catch (\Exception $e) {
                $this->error('❌ Failed to clear service cache: ' . $e->getMessage());
                return Command::FAILURE;
            }
        } else {
            return $this->clearAllCache();
        }
    }

    /**
     * Clear all cache data.
     */
    private function clearAllCache(): int
    {
        if (!$this->confirm('⚠️ This will delete ALL cached data. Are you sure?')) {
            $this->info('Cache clear operation cancelled.');
            return Command::SUCCESS;
        }

        $this->info('🗑️ Clearing ALL API cache data...');

        try {
            $apiDeleted = ApiCache::query()->delete();
            $demoDeleted = DemoCacheData::query()->delete();

            $this->line("   ✓ Deleted {$apiDeleted} API cache entries");
            $this->line("   ✓ Deleted {$demoDeleted} demo cache entries");

            $this->newLine();
            $this->info('✅ All cache data cleared successfully!');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Failed to clear all cache: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Invalidate specific cache entry.
     */
    private function invalidateCache(): int
    {
        $service = $this->option('service');
        $key = $this->option('key');

        if (!$service || !$key) {
            $this->error('❌ Both --service and --key options are required for invalidation');
            return Command::FAILURE;
        }

        $this->info("🎯 Invalidating cache key: {$key} for service: {$service}");

        try {
            $success = $this->cache->forget($service, $key, []);
            
            if ($success) {
                $this->line('   ✓ Cache entry invalidated successfully');
            } else {
                $this->warn('   ⚠ Cache entry not found or already expired');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Failed to invalidate cache: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Display API cache statistics.
     */
    private function displayApiCacheStats(array $stats): void
    {
        $this->line('📦 <fg=cyan>API Cache</fg=cyan>');
        $this->line("   Total entries: <fg=yellow>{$stats['total_entries']}</fg=yellow>");
        $this->line("   Valid entries: <fg=green>{$stats['valid_entries']}</fg=green>");
        $this->line("   Expired entries: <fg=red>{$stats['expired_entries']}</fg=red>");
        $this->line("   Demo entries: <fg=blue>{$stats['demo_entries']}</fg=blue>");
        $this->line("   Cache size: <fg=magenta>{$stats['cache_size_mb']} MB</fg=magenta>");
        $this->line("   Services: <fg=white>" . implode(', ', $stats['services']) . "</fg=white>");
    }

    /**
     * Display demo cache statistics.
     */
    private function displayDemoCacheStats(array $stats): void
    {
        $this->line('🎭 <fg=cyan>Demo Cache</fg=cyan>');
        $this->line("   Total entries: <fg=yellow>{$stats['total_entries']}</fg=yellow>");
        $this->line("   Active entries: <fg=green>{$stats['active_entries']}</fg=green>");
        $this->line("   Stale entries: <fg=red>{$stats['stale_entries']}</fg=red>");
        $this->line("   Cache size: <fg=magenta>{$stats['total_size_kb']} KB</fg=magenta>");
        $this->line("   Data types: <fg=white>" . implode(', ', $stats['data_types']) . "</fg=white>");
    }

    /**
     * Display most accessed cache entries.
     */
    private function displayTopAccessed(array $topAccessed): void
    {
        $this->line('🔥 <fg=cyan>Most Accessed Cache Entries</fg=cyan>');
        
        if (empty($topAccessed)) {
            $this->line('   No access data available');
            return;
        }

        foreach ($topAccessed as $entry) {
            $this->line("   {$entry['hit_count']} hits - {$entry['service']} - {$entry['cache_key']}");
        }
    }

    /**
     * Handle invalid action.
     */
    private function invalidAction(string $action): int
    {
        $this->error("❌ Invalid action: {$action}");
        $this->line('Valid actions: stats, cleanup, clear, invalidate');
        
        return Command::FAILURE;
    }
}