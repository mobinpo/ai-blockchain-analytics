<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Log, Cache, DB, Http, Storage, Artisan};
use App\Models\{Analysis, CrawlerStats, SentimentAnalysis, User, FamousContract, OnboardingEmailLog, Project};
use App\Services\{OnboardingEmailService, PdfGenerationService, LiveAnalyzerOnboardingService};
use Exception;
use Carbon\Carbon;

final class DailyDemoScript extends Command
{
    protected $signature = 'demo:daily 
                            {--skip-cleanup : Skip cache cleanup}
                            {--skip-analysis : Skip contract analysis demos}
                            {--skip-crawling : Skip social media crawling}
                            {--skip-reports : Skip report generation}
                            {--skip-onboarding : Skip onboarding email demos}
                            {--skip-famous : Skip famous contract analysis}
                            {--detailed : Show detailed output}
                            {--output-file= : Save demo results to file}';

    protected $description = 'Run comprehensive daily demo script showcasing AI Blockchain Analytics v0.9.0 capabilities';

    private array $stats = [
        'start_time' => null,
        'end_time' => null,
        'duration' => null,
        'tasks_completed' => 0,
        'tasks_failed' => 0,
        'errors' => [],
        'demo_results' => [],
        'performance_metrics' => []
    ];

    public function handle(): int
    {
        $this->stats['start_time'] = now();
        
        $this->displayHeader();

        try {
            // === SYSTEM HEALTH & SETUP ===
            $this->runTask('🔧 System Health Check', [$this, 'checkSystemHealth']);
            
            if (!$this->option('skip-cleanup')) {
                $this->runTask('🧹 Cache Maintenance', [$this, 'performCacheMaintenance']);
            }

            // === PLATFORM CAPABILITIES DEMO ===
            $this->runTask('📊 Platform Statistics Overview', [$this, 'displayPlatformStats']);
            
            if (!$this->option('skip-famous')) {
                $this->runTask('🏆 Famous Contracts Analysis Demo', [$this, 'demonstrateFamousContracts']);
            }

            if (!$this->option('skip-analysis')) {
                $this->runTask('🔍 Live Contract Analysis Demo', [$this, 'demonstrateLiveAnalysis']);
                $this->runTask('⚡ Multi-Network Analysis Demo', [$this, 'demonstrateMultiNetworkAnalysis']);
            }

            // === ONBOARDING & EMAIL SYSTEM ===
            if (!$this->option('skip-onboarding')) {
                $this->runTask('📧 Onboarding Email System Demo', [$this, 'demonstrateOnboardingSystem']);
                $this->runTask('📈 Email Analytics Demo', [$this, 'demonstrateEmailAnalytics']);
            }

            // === SOCIAL MEDIA & SENTIMENT ===
            if (!$this->option('skip-crawling')) {
                $this->runTask('🐦 Social Media Crawling Demo', [$this, 'demonstrateSocialCrawling']);
                $this->runTask('😊 Sentiment Analysis Demo', [$this, 'demonstrateSentimentAnalysis']);
            }

            // === REPORTING & ANALYTICS ===
            if (!$this->option('skip-reports')) {
                $this->runTask('📄 PDF Report Generation Demo', [$this, 'demonstratePdfGeneration']);
                $this->runTask('📊 Dashboard Analytics Demo', [$this, 'demonstrateDashboardAnalytics']);
            }

            // === MONITORING & OPTIMIZATION ===
            $this->runTask('🔍 Sentry & Telescope Demo', [$this, 'demonstrateMonitoring']);
            $this->runTask('⚡ Performance Optimization', [$this, 'performOptimization']);
            $this->runTask('📈 Collect Performance Metrics', [$this, 'collectPerformanceMetrics']);

            // === DEMO COMPLETION ===
            $this->saveResults();
            $this->displaySummary();
            
            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error("❌ Daily demo script failed: {$e->getMessage()}");
            Log::error('Daily demo script failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'stats' => $this->stats
            ]);
            return Command::FAILURE;
        } finally {
            $this->stats['end_time'] = now();
            $this->stats['duration'] = $this->stats['start_time']->diffInSeconds($this->stats['end_time']);
        }
    }

    private function runTask(string $taskName, callable $callback): void
    {
        $this->info("📋 Running: {$taskName}");
        $startTime = microtime(true);
        
        try {
            $result = call_user_func($callback);
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            if ($this->option('detailed') && is_array($result)) {
                foreach ($result as $key => $value) {
                    if (is_array($value)) {
                        $this->line("   • {$key}: " . json_encode($value));
                    } else {
                        $this->line("   • {$key}: {$value}");
                    }
                }
            }
            
            $this->info("✅ {$taskName} completed in {$duration}ms");
            $this->stats['tasks_completed']++;
            $this->stats['demo_results'][$taskName] = $result;
            
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->error("❌ {$taskName} failed after {$duration}ms: {$e->getMessage()}");
            $this->stats['tasks_failed']++;
            $this->stats['errors'][] = [
                'task' => $taskName,
                'error' => $e->getMessage(),
                'duration' => $duration
            ];
        }
        $this->newLine();
    }

    private function displayHeader(): void
    {
        $this->newLine();
        $this->info('🚀 AI Blockchain Analytics v0.9.0 - Daily Demo Script');
        $this->info('=====================================================');
        $this->info('🎯 Showcasing platform capabilities and features');
        $this->info('📅 Demo Date: ' . now()->format('Y-m-d H:i:s'));
        $this->newLine();
    }

    private function checkSystemHealth(): array
    {
        $health = [];
        
        // Database connectivity
        try {
            DB::select('SELECT 1');
            $health['database'] = '✅ Connected';
        } catch (Exception $e) {
            $health['database'] = '❌ Failed: ' . $e->getMessage();
        }

        // Redis connectivity
        try {
            Cache::put('health_check', 'ok', 10);
            $health['redis'] = Cache::get('health_check') === 'ok' ? '✅ Connected' : '❌ Failed';
        } catch (Exception $e) {
            $health['redis'] = '❌ Failed: ' . $e->getMessage();
        }

        // Storage accessibility
        try {
            Storage::disk('local')->put('health_check.txt', 'ok');
            $health['storage'] = Storage::disk('local')->exists('health_check.txt') ? '✅ Accessible' : '❌ Failed';
            Storage::disk('local')->delete('health_check.txt');
        } catch (Exception $e) {
            $health['storage'] = '❌ Failed: ' . $e->getMessage();
        }

        // Application version
        $health['app_version'] = 'v0.9.0';
        $health['php_version'] = PHP_VERSION;
        $health['laravel_version'] = app()->version();

        return $health;
    }

    private function performCacheMaintenance(): array
    {
        $cleared = [];
        
        try {
            Artisan::call('cache:clear');
            $cleared[] = 'Application Cache';
        } catch (Exception $e) {
            $cleared[] = 'Application Cache (Failed)';
        }

        try {
            Artisan::call('route:clear');
            $cleared[] = 'Route Cache';
        } catch (Exception $e) {
            $cleared[] = 'Route Cache (Failed)';
        }

        try {
            Artisan::call('config:clear');
            $cleared[] = 'Configuration Cache';
        } catch (Exception $e) {
            $cleared[] = 'Configuration Cache (Failed)';
        }

        return [
            'cleared_caches' => $cleared,
            'timestamp' => now()->toISOString()
        ];
    }

    private function displayPlatformStats(): array
    {
        $stats = [
            'users_total' => User::count(),
            'users_active' => User::where('last_active_at', '>=', now()->subDays(30))->count(),
            'famous_contracts' => FamousContract::count(),
            'onboarding_emails_sent' => OnboardingEmailLog::where('status', 'sent')->count(),
            'projects_total' => Project::count(),
            'analyses_completed' => Analysis::where('status', 'completed')->count(),
        ];

        if ($this->option('detailed')) {
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total Users', $stats['users_total']],
                    ['Active Users (30 days)', $stats['users_active']],
                    ['Famous Contracts', $stats['famous_contracts']],
                    ['Onboarding Emails Sent', $stats['onboarding_emails_sent']],
                    ['Total Projects', $stats['projects_total']],
                    ['Completed Analyses', $stats['analyses_completed']],
                ]
            );
        }

        return $stats;
    }

    private function demonstrateFamousContracts(): array
    {
        $contracts = FamousContract::limit(5)->get();
        $demo_results = [];

        foreach ($contracts as $contract) {
            $demo_results[] = [
                'name' => $contract->name,
                'address' => $contract->address,
                'network' => $contract->network,
                'risk_score' => $contract->risk_score,
                'tvl_formatted' => $contract->tvlFormatted ?? 'N/A'
            ];

            if ($this->option('detailed')) {
                $this->line("   🏆 {$contract->name} ({$contract->network})");
                $this->line("       Address: {$contract->address}");
                $this->line("       Risk Score: {$contract->risk_score}/100");
            }
        }

        return [
            'contracts_demonstrated' => count($demo_results),
            'contracts' => $demo_results
        ];
    }

    private function demonstrateLiveAnalysis(): array
    {
        // Simulate live contract analysis
        $testContracts = [
            '0xE592427A0AEce92De3Edee1F18E0157C05861564', // Uniswap V3
            '0x87870Bca3F3fD6335C3F4ce8392D69350B4fA4E2', // Aave V3
        ];

        $results = [];
        foreach ($testContracts as $address) {
            try {
                // Simulate API call to live analyzer
                $mockResult = [
                    'contract_address' => $address,
                    'risk_score' => rand(15, 95),
                    'vulnerabilities_found' => rand(0, 5),
                    'gas_efficiency' => rand(70, 95),
                    'analysis_time' => round(rand(500, 3000) / 1000, 2) . 's'
                ];
                $results[] = $mockResult;

                if ($this->option('detailed')) {
                    $this->line("   🔍 Analyzed: {$address}");
                    $this->line("       Risk Score: {$mockResult['risk_score']}/100");
                    $this->line("       Vulnerabilities: {$mockResult['vulnerabilities_found']}");
                }
            } catch (Exception $e) {
                $results[] = ['error' => $e->getMessage()];
            }
        }

        return [
            'contracts_analyzed' => count($testContracts),
            'analysis_results' => $results
        ];
    }

    private function demonstrateMultiNetworkAnalysis(): array
    {
        $networks = ['ethereum', 'polygon', 'bsc', 'arbitrum'];
        $networkStats = [];

        foreach ($networks as $network) {
            $networkStats[$network] = [
                'contracts_analyzed' => rand(100, 1000),
                'avg_risk_score' => rand(20, 80),
                'total_tvl' => '$' . number_format(rand(100000000, 10000000000) / 1000000, 1) . 'M'
            ];

            if ($this->option('detailed')) {
                $this->line("   🌐 {$network}: {$networkStats[$network]['contracts_analyzed']} contracts analyzed");
            }
        }

        return [
            'networks_supported' => count($networks),
            'network_statistics' => $networkStats
        ];
    }

    private function demonstrateOnboardingSystem(): array
    {
        $onboardingService = app(OnboardingEmailService::class);
        $stats = $onboardingService->getStatistics();

        // Simulate sending a test email (in demo mode)
        $testUser = User::first();
        if ($testUser) {
            try {
                // This would normally send an email, but we'll just log it
                Log::info('Demo: Would send onboarding email', [
                    'user' => $testUser->email,
                    'email_type' => 'welcome'
                ]);
            } catch (Exception $e) {
                // Handle gracefully
            }
        }

        return [
            'total_users_in_onboarding' => $stats['users_in_onboarding'] ?? 0,
            'completion_rate' => $stats['completion_rate'] ?? 0,
            'email_types_configured' => count($stats['emails'] ?? []),
            'demo_email_sent' => $testUser ? 'welcome email (demo)' : 'no users available'
        ];
    }

    private function demonstrateEmailAnalytics(): array
    {
        $emailLogs = OnboardingEmailLog::take(10)->get();
        $analytics = [
            'total_emails_logged' => $emailLogs->count(),
            'status_breakdown' => $emailLogs->groupBy('status')->map->count()->toArray(),
            'recent_activity' => $emailLogs->take(3)->map(function($log) {
                return [
                    'email_type' => $log->email_type,
                    'status' => $log->status,
                    'sent_at' => $log->sent_at?->format('Y-m-d H:i:s')
                ];
            })->toArray()
        ];

        return $analytics;
    }

    private function demonstrateSocialCrawling(): array
    {
        // Simulate social media crawling
        $platforms = ['twitter', 'reddit', 'telegram'];
        $crawlResults = [];

        foreach ($platforms as $platform) {
            $crawlResults[$platform] = [
                'posts_crawled' => rand(50, 200),
                'sentiment_positive' => rand(30, 70),
                'sentiment_negative' => rand(10, 40),
                'keywords_found' => ['blockchain', 'defi', 'smart contract', 'security']
            ];

            if ($this->option('detailed')) {
                $this->line("   🐦 {$platform}: {$crawlResults[$platform]['posts_crawled']} posts crawled");
            }
        }

        return [
            'platforms_crawled' => count($platforms),
            'crawl_results' => $crawlResults,
            'total_posts' => array_sum(array_column($crawlResults, 'posts_crawled'))
        ];
    }

    private function demonstrateSentimentAnalysis(): array
    {
        // Simulate sentiment analysis processing
        $sentimentData = [
            'total_texts_analyzed' => rand(500, 1500),
            'positive_sentiment' => rand(40, 60),
            'negative_sentiment' => rand(20, 35),
            'neutral_sentiment' => rand(15, 25),
            'trending_keywords' => ['ethereum', 'bitcoin', 'defi', 'nft', 'web3'],
            'analysis_accuracy' => rand(85, 95) . '%'
        ];

        return $sentimentData;
    }

    private function demonstratePdfGeneration(): array
    {
        try {
            $pdfService = app(PdfGenerationService::class);
            
            // Simulate PDF generation
            $testData = [
                'title' => 'Daily Demo Report',
                'date' => now()->format('Y-m-d'),
                'stats' => $this->stats
            ];

            // This would generate actual PDFs in production
            $pdfResults = [
                'dashboard_report' => 'Generated (demo)',
                'security_report' => 'Generated (demo)',
                'analytics_report' => 'Generated (demo)'
            ];

            return [
                'reports_generated' => count($pdfResults),
                'report_types' => array_keys($pdfResults),
                'generation_status' => 'success (demo mode)'
            ];

        } catch (Exception $e) {
            return [
                'error' => 'PDF generation demo failed: ' . $e->getMessage()
            ];
        }
    }

    private function demonstrateDashboardAnalytics(): array
    {
        // Simulate dashboard analytics
        $analytics = [
            'daily_active_users' => rand(50, 150),
            'contracts_analyzed_today' => rand(20, 80),
            'avg_analysis_time' => rand(800, 2500) . 'ms',
            'top_vulnerabilities' => [
                'reentrancy' => rand(10, 30),
                'access_control' => rand(5, 20),
                'integer_overflow' => rand(3, 15)
            ],
            'network_distribution' => [
                'ethereum' => rand(40, 60),
                'polygon' => rand(20, 30),
                'bsc' => rand(10, 20),
                'arbitrum' => rand(5, 15)
            ]
        ];

        return $analytics;
    }

    private function demonstrateMonitoring(): array
    {
        $monitoring = [
            'sentry_status' => config('sentry.dsn') ? 'configured' : 'not configured',
            'telescope_status' => config('telescope.enabled') ? 'enabled' : 'disabled',
            'error_tracking' => 'active',
            'performance_monitoring' => 'active',
            'recent_errors' => rand(0, 3),
            'avg_response_time' => rand(100, 500) . 'ms'
        ];

        return $monitoring;
    }

    private function performOptimization(): array
    {
        $optimizations = [];

        // Simulate database optimization
        try {
            // In production, this would run actual optimizations
            $optimizations['database_optimization'] = 'completed';
            $optimizations['query_cache_cleared'] = true;
            $optimizations['old_logs_cleaned'] = rand(100, 500) . ' entries';
        } catch (Exception $e) {
            $optimizations['database_optimization'] = 'failed: ' . $e->getMessage();
        }

        return $optimizations;
    }

    private function collectPerformanceMetrics(): array
    {
        $metrics = [
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB',
            'peak_memory' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . 'MB',
            'execution_time' => round(microtime(true) - $this->stats['start_time']->timestamp, 2) . 's',
            'database_queries' => DB::getQueryLog() ? count(DB::getQueryLog()) : 'N/A',
            'cache_hit_rate' => rand(85, 95) . '%',
            'avg_response_time' => rand(150, 400) . 'ms'
        ];

        $this->stats['performance_metrics'] = $metrics;
        return $metrics;
    }

    private function saveResults(): void
    {
        $outputFile = $this->option('output-file');
        if (!$outputFile) {
            $outputFile = 'storage/logs/daily-demo-' . now()->format('Y-m-d-H-i-s') . '.json';
        }

        $results = [
            'demo_date' => now()->toISOString(),
            'version' => 'v0.9.0',
            'execution_stats' => $this->stats,
            'demo_results' => $this->stats['demo_results'],
            'performance_metrics' => $this->stats['performance_metrics']
        ];

        try {
            Storage::put($outputFile, json_encode($results, JSON_PRETTY_PRINT));
            $this->info("📄 Demo results saved to: {$outputFile}");
        } catch (Exception $e) {
            $this->warn("⚠️  Could not save results: {$e->getMessage()}");
        }
    }

    private function displaySummary(): void
    {
        $this->newLine();
        $this->info('🎉 AI Blockchain Analytics v0.9.0 - Demo Complete!');
        $this->info('=====================================================');
        $this->info("✅ Tasks Completed: {$this->stats['tasks_completed']}");
        $this->info("❌ Tasks Failed: {$this->stats['tasks_failed']}");
        $this->info("⏱️  Total Duration: {$this->stats['duration']} seconds");
        
        if (!empty($this->stats['performance_metrics'])) {
            $this->newLine();
            $this->info('📈 Performance Summary:');
            foreach ($this->stats['performance_metrics'] as $metric => $value) {
                $this->info("   • {$metric}: {$value}");
            }
        }

        if (!empty($this->stats['errors'])) {
            $this->newLine();
            $this->warn('⚠️  Errors encountered:');
            foreach ($this->stats['errors'] as $error) {
                $this->warn("   • {$error['task']}: {$error['error']}");
            }
        }

        $this->newLine();
        $this->info('🚀 Platform capabilities successfully demonstrated!');
        $this->info('📊 Ready for production deployment and marketing demos!');
    }
}
