<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PostgresCacheManager;
use App\Services\IntelligentCacheWarmer;
use App\Models\ContractCache;
use App\Models\CacheWarmingQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ManagePostgresCache extends Command
{
    protected $signature = 'cache:postgres 
                           {action : Action to perform (stats|optimize|warm|cleanup|quota|analytics)}
                           {--network=ethereum : Network to focus on}
                           {--limit=50 : Limit for processing}
                           {--force : Force operations without confirmation}
                           {--strategy= : Warming strategy (expiring_soon,high_access,predictive,etc)}
                           {--contracts= : Comma-separated contract addresses}
                           {--days=7 : Number of days for analytics}';

    protected $description = 'Manage PostgreSQL cache to avoid API limits - Cache first, API last!';

    private PostgresCacheManager $cacheManager;
    private IntelligentCacheWarmer $warmer;

    public function __construct(
        PostgresCacheManager $cacheManager,
        IntelligentCacheWarmer $warmer
    ) {
        parent::__construct();
        $this->cacheManager = $cacheManager;
        $this->warmer = $warmer;
    }

    public function handle(): int
    {
        $action = $this->argument('action');
        
        $this->info("🗄️  PostgreSQL Cache Manager - CACHE FIRST, API LAST!");
        $this->newLine();

        try {
            return match ($action) {
                'stats' => $this->showCacheStatistics(),
                'optimize' => $this->optimizeCache(),
                'warm' => $this->warmCache(),
                'cleanup' => $this->cleanupCache(),
                'quota' => $this->showApiQuota(),
                'analytics' => $this->showAnalytics(),
                default => $this->showHelp()
            };

        } catch (\Exception $e) {
            $this->error("❌ Command failed: {$e->getMessage()}");
            if ($this->option('verbose')) {
                $this->line($e->getTraceAsString());
            }
            return self::FAILURE;
        }
    }

    private function showCacheStatistics(): int
    {
        $this->info("📊 PostgreSQL Cache Statistics");
        $this->newLine();

        // Basic cache stats
        $stats = ContractCache::getCacheEfficiencyStats();
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Cache Entries', number_format($stats['total_entries'])],
                ['Active Entries', number_format($stats['active_entries'])],
                ['Expired Entries', number_format($stats['expired_entries'])],
                ['Average Quality Score', $stats['average_quality_score']],
                ['Total API Calls Saved', number_format($stats['total_api_calls_saved'])],
                ['Pending Refresh Queue', number_format($stats['refresh_queue_size'])],
            ]
        );

        // Cache by type
        if (!empty($stats['cache_types'])) {
            $this->newLine();
            $this->info("📋 Cache by Type:");
            
            $typeRows = [];
            foreach ($stats['cache_types'] as $type => $data) {
                $typeRows[] = [
                    ucfirst($type),
                    number_format($data['count']),
                    $data['avg_quality'],
                    number_format($data['total_api_calls'])
                ];
            }
            
            $this->table(
                ['Type', 'Count', 'Avg Quality', 'API Calls'],
                $typeRows
            );
        }

        // Warming queue stats
        $queueStats = CacheWarmingQueue::getQueueStats();
        $this->newLine();
        $this->info("🔥 Cache Warming Queue:");
        
        $this->table(
            ['Status', 'Count'],
            [
                ['Pending', number_format($queueStats['pending'] ?? 0)],
                ['Processing', number_format($queueStats['processing'] ?? 0)],
                ['Completed Today', number_format($queueStats['completed_today'] ?? 0)],
                ['Failed Today', number_format($queueStats['failed_today'] ?? 0)],
            ]
        );

        return self::SUCCESS;
    }

    private function optimizeCache(): int
    {
        $this->info("🔧 Optimizing PostgreSQL Cache...");
        $this->newLine();

        if (!$this->option('force') && !$this->confirm('This will optimize cache entries and may update many records. Continue?')) {
            $this->info('Cache optimization cancelled.');
            return self::SUCCESS;
        }

        $optimizations = $this->cacheManager->optimizeCache();
        
        $this->info("✅ Cache optimization completed!");
        $this->newLine();
        
        foreach ($optimizations as $optimization) {
            $this->line("  • {$optimization}");
        }

        return self::SUCCESS;
    }

    private function warmCache(): int
    {
        $network = $this->option('network');
        $limit = (int) $this->option('limit');
        $strategy = $this->option('strategy');
        $contracts = $this->option('contracts');

        $this->info("🔥 Intelligent Cache Warming");
        $this->info("🌐 Network: {$network}");
        $this->info("📊 Max API calls: {$limit}");
        $this->newLine();

        // Specific contracts warming
        if ($contracts) {
            $addresses = array_map('trim', explode(',', $contracts));
            $this->info("🎯 Warming specific contracts (" . count($addresses) . ")");
            
            $result = $this->warmer->warmSpecificContracts($addresses, $network, 'high');
            
            $this->displayWarmingResults($result, 'Specific Contracts');
            return self::SUCCESS;
        }

        // Strategy-based warming
        $strategies = $strategy ? [$strategy] : null;
        
        if ($strategies) {
            $this->info("📋 Using strategy: {$strategy}");
        } else {
            $this->info("📋 Using all available strategies");
        }

        $result = $this->warmer->executeIntelligentWarming($strategies, $limit, $network);
        
        $this->displayWarmingResults($result, 'Intelligent Warming');
        
        // Show strategy breakdown
        if (!empty($result['strategies_executed'])) {
            $this->newLine();
            $this->info("📊 Strategy Breakdown:");
            
            $strategyRows = [];
            foreach ($result['strategies_executed'] as $strat => $data) {
                $strategyRows[] = [
                    ucfirst(str_replace('_', ' ', $strat)),
                    $data['processed'],
                    $data['successful'],
                    $data['failed'],
                    $data['api_calls_used']
                ];
            }
            
            $this->table(
                ['Strategy', 'Processed', 'Successful', 'Failed', 'API Calls'],
                $strategyRows
            );
        }

        return self::SUCCESS;
    }

    private function cleanupCache(): int
    {
        $this->info("🧹 Cache Cleanup Operations");
        $this->newLine();

        if (!$this->option('force') && !$this->confirm('This will delete expired and low-quality cache entries. Continue?')) {
            $this->info('Cache cleanup cancelled.');
            return self::SUCCESS;
        }

        $operations = [];

        // Clean expired entries
        $expiredCleaned = ContractCache::expired()->delete();
        $operations[] = "Removed {$expiredCleaned} expired cache entries";

        // Clean low-quality entries older than 30 days
        $lowQualityCleaned = ContractCache::where('cache_quality_score', '<', 0.3)
            ->where('created_at', '<', now()->subDays(30))
            ->delete();
        $operations[] = "Removed {$lowQualityCleaned} low-quality old entries";

        // Clean failed queue items older than 7 days
        $failedQueueCleaned = CacheWarmingQueue::where('status', 'failed')
            ->where('created_at', '<', now()->subDays(7))
            ->delete();
        $operations[] = "Removed {$failedQueueCleaned} old failed queue items";

        // Vacuum database tables for space reclamation
        if ($this->confirm('Run VACUUM on cache tables to reclaim disk space?', true)) {
            DB::statement('VACUUM ANALYZE contract_cache');
            DB::statement('VACUUM ANALYZE cache_warming_queue');
            $operations[] = "Vacuumed database tables for space reclamation";
        }

        $this->info("✅ Cache cleanup completed!");
        $this->newLine();
        
        foreach ($operations as $operation) {
            $this->line("  • {$operation}");
        }

        return self::SUCCESS;
    }

    private function showApiQuota(): int
    {
        $this->info("📊 API Quota Status - AVOID LIMITS AT ALL COSTS!");
        $this->newLine();

        $quotaStatus = $this->cacheManager->getApiQuotaStatus();

        $quotaRows = [];
        foreach ($quotaStatus as $network => $status) {
            $statusIcon = match ($status['status']) {
                'healthy' => '✅',
                'moderate' => '⚠️',
                'warning' => '🟡',
                'critical' => '🔴',
                default => '❓'
            };

            $quotaRows[] = [
                $network,
                number_format($status['daily_limit']),
                number_format($status['used_today']),
                number_format($status['remaining']),
                $status['percent_used'] . '%',
                $statusIcon . ' ' . ucfirst($status['status']),
                round($status['estimated_hours_remaining'], 1) . 'h'
            ];
        }

        $this->table(
            ['Network', 'Daily Limit', 'Used Today', 'Remaining', '% Used', 'Status', 'Est. Hours Left'],
            $quotaRows
        );

        // Show warnings for critical networks
        foreach ($quotaStatus as $network => $status) {
            if ($status['status'] === 'critical') {
                $this->error("🚨 CRITICAL: {$network} API quota is at {$status['percent_used']}%!");
                $this->line("   Cache aggressively to avoid API failures!");
            } elseif ($status['status'] === 'warning') {
                $this->warn("⚠️  WARNING: {$network} API quota is at {$status['percent_used']}%");
            }
        }

        return self::SUCCESS;
    }

    private function showAnalytics(): int
    {
        $days = (int) $this->option('days');
        
        $this->info("📈 Cache Analytics ({$days} days)");
        $this->newLine();

        $analytics = $this->cacheManager->getCacheAnalytics($days);

        // Cache performance
        $performance = $analytics['cache_performance'];
        $this->info("🎯 Cache Performance:");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Requests', number_format($performance['total_requests'])],
                ['Cache Hits', number_format($performance['cache_hits'])],
                ['Cache Misses', number_format($performance['cache_misses'])],
                ['Hit Rate', $performance['hit_rate_percent'] . '%'],
                ['API Calls Saved', number_format($performance['api_calls_saved'])],
            ]
        );

        // API usage by network
        if (!empty($analytics['api_usage'])) {
            $this->newLine();
            $this->info("🌐 API Usage by Network:");
            
            $apiRows = [];
            foreach ($analytics['api_usage'] as $network => $usage) {
                $apiRows[] = [
                    $network,
                    number_format($usage['total_calls']),
                    number_format($usage['successful_calls']),
                    round(($usage['successful_calls'] / max(1, $usage['total_calls'])) * 100, 1) . '%',
                    round($usage['avg_response_time'], 0) . 'ms'
                ];
            }
            
            $this->table(
                ['Network', 'Total Calls', 'Successful', 'Success Rate', 'Avg Response'],
                $apiRows
            );
        }

        // Storage analytics
        $storage = $analytics['storage'];
        if (!empty($storage)) {
            $this->newLine();
            $this->info("💾 Storage Analytics:");
            
            $storageRows = [];
            foreach ($storage as $stats) {
                $storageRows[] = [
                    ucfirst($stats->cache_type),
                    number_format($stats->entry_count),
                    round($stats->avg_quality, 2),
                    $this->formatBytes($stats->total_source_bytes + $stats->total_abi_bytes)
                ];
            }
            
            $this->table(
                ['Cache Type', 'Entries', 'Avg Quality', 'Storage Used'],
                $storageRows
            );
        }

        // Recommendations
        if (!empty($analytics['recommendations'])) {
            $this->newLine();
            $this->info("💡 Recommendations:");
            foreach ($analytics['recommendations'] as $recommendation) {
                $this->line("  • {$recommendation}");
            }
        }

        return self::SUCCESS;
    }

    private function showHelp(): int
    {
        $this->info("📚 PostgreSQL Cache Manager - Available Actions:");
        $this->newLine();

        $actions = [
            'stats' => 'Show comprehensive cache statistics',
            'optimize' => 'Optimize cache entries and TTLs',
            'warm' => 'Intelligently warm cache to avoid API calls',
            'cleanup' => 'Clean up expired and low-quality entries',
            'quota' => 'Show API quota status for all networks',
            'analytics' => 'Show detailed cache analytics and performance'
        ];

        foreach ($actions as $action => $description) {
            $this->line("  <info>{$action}</info> - {$description}");
        }

        $this->newLine();
        $this->info("💡 Examples:");
        $this->line("  php artisan cache:postgres stats");
        $this->line("  php artisan cache:postgres warm --network=ethereum --limit=100");
        $this->line("  php artisan cache:postgres warm --strategy=expiring_soon --limit=50");
        $this->line("  php artisan cache:postgres warm --contracts=0x123...,0x456...");
        $this->line("  php artisan cache:postgres quota");
        $this->line("  php artisan cache:postgres analytics --days=30");
        $this->line("  php artisan cache:postgres cleanup --force");

        return self::SUCCESS;
    }

    private function displayWarmingResults(array $result, string $type): void
    {
        $this->info("✅ {$type} Results:");
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Processed', $result['processed'] ?? 0],
                ['Successful', $result['successful'] ?? 0],
                ['Failed', $result['failed'] ?? 0],
                ['Already Cached', $result['already_cached'] ?? 0],
                ['Skipped (Quota)', $result['skipped_quota'] ?? 0],
                ['API Calls Used', $result['api_calls_used'] ?? 0],
                ['Time Taken', ($result['time_taken_seconds'] ?? 0) . 's'],
            ]
        );

        // Calculate success rate
        $total = $result['processed'] ?? 0;
        if ($total > 0) {
            $successRate = round((($result['successful'] ?? 0) / $total) * 100, 1);
            $this->newLine();
            $this->info("🎯 Success Rate: {$successRate}%");
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}