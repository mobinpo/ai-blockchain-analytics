<?php

namespace App\Console\Commands;

use App\Services\CacheOptimizationService;
use App\Services\SourceCodeFetchingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class OptimizeContractCache extends Command
{
    protected $signature = 'cache:optimize-contracts 
                          {--warm : Proactively warm cache for high-value contracts}
                          {--refresh : Refresh stale cache entries}
                          {--analyze : Analyze cache efficiency and provide recommendations}
                          {--stats : Show comprehensive cache statistics}
                          {--limit=50 : Limit for cache warming operations}
                          {--network= : Specific network to focus on}
                          {--api-usage : Show API usage statistics}
                          {--all : Run all optimization tasks}';

    protected $description = 'Optimize contract cache to minimize API calls and improve performance';

    private CacheOptimizationService $optimizer;
    private SourceCodeFetchingService $fetchingService;

    public function __construct(
        CacheOptimizationService $optimizer,
        SourceCodeFetchingService $fetchingService
    ) {
        parent::__construct();
        $this->optimizer = $optimizer;
        $this->fetchingService = $fetchingService;
    }

    public function handle(): int
    {
        $this->info('🚀 Contract Cache Optimization Tool');
        $this->newLine();

        if ($this->option('all')) {
            return $this->runAllOptimizations();
        }

        if ($this->option('stats')) {
            return $this->showComprehensiveStats();
        }

        if ($this->option('analyze')) {
            return $this->analyzeCacheEfficiency();
        }

        if ($this->option('api-usage')) {
            return $this->showApiUsageStats();
        }

        if ($this->option('warm')) {
            return $this->warmHighValueCache();
        }

        if ($this->option('refresh')) {
            return $this->refreshStaleCache();
        }

        // Show help if no specific option
        $this->showUsageHelp();
        return Command::SUCCESS;
    }

    private function runAllOptimizations(): int
    {
        $this->info('🔄 Running complete cache optimization suite...');
        $this->newLine();

        $results = [
            'analysis' => null,
            'warming' => null,
            'refresh' => null,
            'api_check' => null
        ];

        // 1. Analyze current efficiency
        $this->info('1. 📊 Analyzing cache efficiency...');
        $results['analysis'] = $this->optimizer->analyzeCacheEfficiency();
        $this->displayAnalysisResults($results['analysis']);
        $this->newLine();

        // 2. Check API usage patterns
        $this->info('2. 🌐 Checking API usage patterns...');
        $results['api_check'] = $this->optimizer->getApiUsageStats(24);
        $this->displayApiUsageResults($results['api_check']);
        $this->newLine();

        // 3. Warm high-value contracts
        $this->info('3. 🔥 Warming high-value contract cache...');
        $limit = $this->option('limit');
        $results['warming'] = $this->optimizer->warmHighValueContracts($limit);
        $this->displayWarmingResults($results['warming']);
        $this->newLine();

        // 4. Schedule intelligent refresh
        $this->info('4. 🔄 Scheduling intelligent cache refresh...');
        $results['refresh'] = $this->optimizer->scheduleIntelligentRefresh();
        $this->displayRefreshResults($results['refresh']);
        $this->newLine();

        // Summary
        $this->info('✅ Optimization suite completed!');
        $this->displayOptimizationSummary($results);

        return Command::SUCCESS;
    }

    private function showComprehensiveStats(): int
    {
        $this->info('📊 Comprehensive Contract Cache Statistics');
        $this->newLine();

        try {
            $stats = $this->fetchingService->getComprehensiveCacheStatistics();
            
            if (isset($stats['database_cache_disabled'])) {
                $this->warn('❌ Database cache is disabled');
                return Command::FAILURE;
            }

            // Cache Efficiency
            $efficiency = $stats['cache_efficiency'];
            $this->info('💾 Cache Efficiency Overview:');
            $this->table(
                ['Metric', 'Value', 'Impact'],
                [
                    ['Total Cache Entries', number_format($efficiency['total_entries']), $this->getEfficiencyStatus($efficiency['total_entries'], 1000, 'entries')],
                    ['Active Entries', number_format($efficiency['active_entries']), $this->getEfficiencyStatus($efficiency['active_entries'], 500, 'active')],
                    ['Average Quality Score', $efficiency['average_quality_score'] . '/1.0', $this->getQualityStatus($efficiency['average_quality_score'])],
                    ['Total API Calls Saved', number_format($efficiency['total_api_calls_saved']), $this->getApiSavingsStatus($efficiency['total_api_calls_saved'])],
                ]
            );

            // System Health
            if (isset($stats['system_health'])) {
                $this->newLine();
                $health = $stats['system_health'];
                $this->info('🏥 System Health Summary:');
                $this->table(
                    ['Health Metric', 'Current Value', 'Status', 'Recommendation'],
                    [
                        [
                            'Cache Hit Rate',
                            round($health['cache_hit_rate'], 1) . '%',
                            $this->getHitRateStatus($health['cache_hit_rate']),
                            $health['cache_hit_rate'] < 70 ? 'Increase TTL' : 'Excellent'
                        ],
                        [
                            'API Calls Saved Today',
                            number_format($health['api_calls_saved_today']),
                            $health['api_calls_saved_today'] > 50 ? '✅ High Impact' : '📊 Normal',
                            $health['api_calls_saved_today'] < 20 ? 'Enable proactive caching' : 'Maintain current strategy'
                        ],
                        [
                            'Recent API Success Rate',
                            round($health['recent_api_success_rate'], 1) . '%',
                            $this->getSuccessRateStatus($health['recent_api_success_rate']),
                            $health['recent_api_success_rate'] < 95 ? 'Check API keys and limits' : 'APIs healthy'
                        ]
                    ]
                );
            }

            // Network breakdown
            if (!empty($efficiency['by_network'])) {
                $this->newLine();
                $this->info('🌐 Cache Distribution by Network:');
                $networkData = [];
                foreach ($efficiency['by_network'] as $network => $count) {
                    $networkData[] = [
                        ucfirst($network),
                        number_format($count),
                        round(($count / max($efficiency['total_entries'], 1)) * 100, 1) . '%'
                    ];
                }
                $this->table(['Network', 'Entries', 'Percentage'], $networkData);
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to get comprehensive statistics: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function analyzeCacheEfficiency(): int
    {
        $this->info('🔍 Analyzing cache efficiency and generating recommendations...');
        $this->newLine();

        try {
            $analysis = $this->optimizer->analyzeCacheEfficiency();
            
            $this->displayAnalysisResults($analysis);
            
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Cache analysis failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function showApiUsageStats(): int
    {
        $this->info('🌐 API Usage Statistics and Optimization Opportunities');
        $this->newLine();

        $hours = 24; // Last 24 hours
        
        try {
            $apiStats = $this->optimizer->getApiUsageStats($hours);
            
            $this->displayApiUsageResults($apiStats);
            
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to get API usage stats: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function warmHighValueCache(): int
    {
        $this->info('🔥 Proactively warming cache for high-value contracts...');
        $this->newLine();

        $limit = $this->option('limit');
        
        try {
            $results = $this->optimizer->warmHighValueContracts($limit);
            
            $this->displayWarmingResults($results);
            
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Cache warming failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function refreshStaleCache(): int
    {
        $this->info('🔄 Refreshing stale cache entries with intelligent scheduling...');
        $this->newLine();

        try {
            $results = $this->optimizer->scheduleIntelligentRefresh();
            
            $this->displayRefreshResults($results);
            
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Cache refresh failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function displayAnalysisResults(array $analysis): void
    {
        // Statistics
        $this->info('📈 Cache Performance by Network & Type:');
        $tableData = [];
        foreach ($analysis['stats'] as $stat) {
            $hitRate = ($stat->valid_entries / max($stat->total_entries, 1)) * 100;
            $tableData[] = [
                ucfirst($stat->network),
                ucfirst($stat->cache_type),
                number_format($stat->total_entries),
                round($hitRate, 1) . '%',
                round($stat->avg_quality, 2),
                number_format($stat->avg_api_fetches, 1)
            ];
        }
        
        $this->table([
            'Network', 'Type', 'Entries', 'Hit Rate', 'Avg Quality', 'Avg API Fetches'
        ], $tableData);

        // Recommendations
        if (!empty($analysis['recommendations'])) {
            $this->newLine();
            $this->info('💡 Optimization Recommendations:');
            foreach ($analysis['recommendations'] as $rec) {
                $icon = $rec['issue'] === 'Low cache hit rate' ? '📉' : '🔄';
                $this->line("  {$icon} {$rec['network']} ({$rec['type']}): {$rec['issue']}");
                $this->line("     → {$rec['suggestion']}");
            }
        } else {
            $this->newLine();
            $this->info('✅ No optimization recommendations - cache is performing well!');
        }

        // Overall health
        $health = $analysis['overall_health'];
        $this->newLine();
        $this->info("🏥 Overall Cache Health: {$health['health_score']}/1.0 ({$health['status']})");
        $this->line("   Hit Rate: {$health['overall_hit_rate']}% | Quality: {$health['average_quality']}/1.0");
    }

    private function displayApiUsageResults(array $apiStats): void
    {
        $this->info("📊 API Usage Statistics (Last {$apiStats['period_hours']} hours):");
        
        $tableData = [];
        foreach ($apiStats['stats'] as $stat) {
            $successRate = ($stat->successful_calls / max($stat->total_calls, 1)) * 100;
            $tableData[] = [
                ucfirst($stat->network),
                number_format($stat->total_calls),
                number_format($stat->successful_calls),
                round($successRate, 1) . '%',
                round($stat->avg_response_time, 0) . 'ms'
            ];
        }
        
        $this->table([
            'Network', 'Total Calls', 'Successful', 'Success Rate', 'Avg Response'
        ], $tableData);

        if (!empty($apiStats['recommendations'])) {
            $this->newLine();
            $this->info('⚠️ API Usage Recommendations:');
            foreach ($apiStats['recommendations'] as $rec) {
                $icon = str_contains($rec['suggestion'], 'High API usage') ? '🚨' : '⚠️';
                $this->line("  {$icon} {$rec['network']}: {$rec['suggestion']}");
                if (isset($rec['total_calls'])) {
                    $this->line("     Calls: {$rec['total_calls']} | Success Rate: {$rec['success_rate']}");
                }
            }
        }
    }

    private function displayWarmingResults(array $results): void
    {
        $summary = $results['summary'];
        
        $this->table(
            ['Metric', 'Count'],
            [
                ['Contracts Processed', $summary['total_processed']],
                ['Successfully Queued', $summary['successfully_queued']],
                ['Failed', $summary['failed']],
                ['Success Rate', round(($summary['successfully_queued'] / max($summary['total_processed'], 1)) * 100, 1) . '%']
            ]
        );

        if (!empty($results['warmed'])) {
            $this->info('✅ Successfully queued for warming:');
            foreach (array_slice($results['warmed'], 0, 10) as $contract) {
                $this->line("  • {$contract['network']}: {$contract['address']} ({$contract['contract_name']})");
            }
            if (count($results['warmed']) > 10) {
                $this->line('  ... and ' . (count($results['warmed']) - 10) . ' more contracts');
            }
        }

        if (!empty($results['errors'])) {
            $this->newLine();
            $this->warn('❌ Warming errors:');
            foreach (array_slice($results['errors'], 0, 5) as $error) {
                $this->line("  • {$error}");
            }
        }
    }

    private function displayRefreshResults(array $results): void
    {
        $this->table(
            ['Operation', 'Count'],
            [
                ['Contracts Refreshed', $results['refreshed']],
                ['Skipped (API Limits)', $results['skipped']],
                ['Errors', $results['errors']],
                ['Total Candidates', $results['total_candidates']]
            ]
        );

        if ($results['skipped'] > 0) {
            $this->warn("⚠️ Skipped {$results['skipped']} refreshes due to API rate limits");
        }

        if ($results['refreshed'] > 0) {
            $this->info("✅ Queued {$results['refreshed']} contracts for background refresh");
        }
    }

    private function displayOptimizationSummary(array $results): void
    {
        $this->newLine();
        $this->info('📋 Optimization Summary:');
        
        $summaryData = [];
        
        if ($results['analysis']) {
            $health = $results['analysis']['overall_health'];
            $summaryData[] = ['Cache Health Score', $health['health_score'] . '/1.0 (' . $health['status'] . ')'];
        }
        
        if ($results['warming']) {
            $warming = $results['warming']['summary'];
            $summaryData[] = ['Contracts Warmed', $warming['successfully_queued'] . '/' . $warming['total_processed']];
        }
        
        if ($results['refresh']) {
            $summaryData[] = ['Contracts Refreshed', $results['refresh']['refreshed']];
        }
        
        if ($results['api_check']) {
            $totalCalls = array_sum(array_column($results['api_check']['stats'], 'total_calls'));
            $summaryData[] = ['API Calls (24h)', number_format($totalCalls)];
        }

        $this->table(['Metric', 'Result'], $summaryData);
        
        $this->newLine();
        $this->comment('💡 Run this command regularly to maintain optimal cache performance');
        $this->comment('💡 Consider scheduling: php artisan cache:optimize-contracts --all');
    }

    private function showUsageHelp(): void
    {
        $this->info('📖 Cache Optimization Commands:');
        $this->newLine();
        
        $commands = [
            ['--all', 'Run complete optimization suite'],
            ['--stats', 'Show comprehensive cache statistics'],
            ['--analyze', 'Analyze efficiency and get recommendations'],
            ['--api-usage', 'Show API usage patterns'],
            ['--warm', 'Proactively warm high-value contracts'],
            ['--refresh', 'Refresh stale cache entries'],
            ['--limit=50', 'Set limit for warming operations'],
            ['--network=ethereum', 'Focus on specific network']
        ];
        
        $this->table(['Option', 'Description'], $commands);
    }

    // Helper methods for status indicators
    private function getEfficiencyStatus(int $value, int $threshold, string $type): string
    {
        return match($type) {
            'entries' => $value > $threshold ? '✅ Good Volume' : '📊 Building',
            'active' => $value > ($threshold / 2) ? '✅ Healthy' : '⚠️ Low Activity',
            default => '📊 Normal'
        };
    }

    private function getQualityStatus(float $score): string
    {
        return $score > 0.8 ? '✅ Excellent' : ($score > 0.6 ? '⚠️ Good' : '❌ Needs Work');
    }

    private function getApiSavingsStatus(int $saved): string
    {
        return $saved > 100 ? '✅ High Impact' : ($saved > 25 ? '📊 Moderate' : '🔄 Building');
    }

    private function getHitRateStatus(float $rate): string
    {
        return $rate > 80 ? '✅ Excellent' : ($rate > 60 ? '⚠️ Good' : '❌ Poor');
    }

    private function getSuccessRateStatus(float $rate): string
    {
        return $rate > 95 ? '✅ Excellent' : ($rate > 90 ? '⚠️ Good' : '❌ Concerning');
    }
}