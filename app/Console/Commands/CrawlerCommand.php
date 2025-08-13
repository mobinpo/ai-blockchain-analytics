<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CrawlerMicroService\OctaneCrawlerService;
use App\Services\CrawlerMicroService\Engine\AdvancedKeywordEngine;
use App\Models\CrawlerKeywordRule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class CrawlerCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'crawler:manage 
                           {action : Action to perform (execute|health|stats|keywords|test)}
                           {--platforms=* : Platforms to crawl (twitter,reddit,telegram)}
                           {--keywords=* : Keywords to search for}
                           {--job-id= : Custom job ID}
                           {--service=octane : Service to use (octane|lambda)}
                           {--force : Force execution even if disabled}';

    /**
     * The console command description.
     */
    protected $description = 'Manage the social media crawler microservice';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');

        try {
            return match($action) {
                'execute' => $this->executeAction(),
                'health' => $this->healthAction(),
                'stats' => $this->statsAction(),
                'keywords' => $this->keywordsAction(),
                'test' => $this->testAction(),
                default => $this->invalidAction($action)
            };
        } catch (\Exception $e) {
            $this->error("Command failed: {$e->getMessage()}");
            if ($this->output->isVerbose()) {
                $this->error($e->getTraceAsString());
            }
            return 1;
        }
    }

    /**
     * Execute crawler action
     */
    private function executeAction(): int
    {
        $service = $this->option('service');
        
        if ($service === 'lambda') {
            return $this->executeLambdaCrawl();
        }

        // Octane execution
        $this->info('🚀 Starting crawler execution...');
        
        $crawler = new OctaneCrawlerService();
        
        $platforms = $this->option('platforms') ?: ['twitter', 'reddit', 'telegram'];
        $keywords = $this->option('keywords') ?: null;
        $jobId = $this->option('job-id') ?: 'console_' . uniqid();

        $config = [
            'job_id' => $jobId,
            'platforms' => $platforms,
            'keywords' => $keywords,
        ];

        $this->table(['Setting', 'Value'], [
            ['Job ID', $jobId],
            ['Platforms', implode(', ', $platforms)],
            ['Keywords', $keywords ? implode(', ', $keywords) : 'Auto-detect'],
            ['Service', 'Octane'],
        ]);

        if (!$this->confirm('Proceed with crawler execution?')) {
            $this->info('Execution cancelled.');
            return 0;
        }

        $startTime = microtime(true);
        $progressBar = $this->output->createProgressBar();
        $progressBar->start();

        try {
            $result = $crawler->executeCrawlJob($config);
            $progressBar->finish();
            $this->newLine(2);

            $executionTime = microtime(true) - $startTime;

            $this->info("✅ Crawler execution completed!");
            $this->table(['Metric', 'Value'], [
                ['Platforms Processed', $result['job_summary']['platforms_successful'] ?? 0],
                ['Posts Collected', $result['totals']['posts_collected'] ?? 0],
                ['Keyword Matches', $result['totals']['keyword_matches'] ?? 0],
                ['Execution Time', round($executionTime, 2) . 's'],
                ['Status', 'Success'],
            ]);

            if (isset($result['platform_breakdown'])) {
                $this->info("\n📊 Platform Breakdown:");
                $platformData = [];
                foreach ($result['platform_breakdown'] as $platform => $stats) {
                    $platformData[] = [
                        $platform,
                        $stats['posts_collected'] ?? 0,
                        $stats['keyword_matches'] ?? 0,
                        $stats['channels_processed'] ?? 0,
                    ];
                }
                $this->table(['Platform', 'Posts', 'Matches', 'Channels'], $platformData);
            }

            return 0;

        } catch (\Exception $e) {
            $progressBar->finish();
            $this->newLine(2);
            $this->error("❌ Execution failed: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Execute Lambda crawl
     */
    private function executeLambdaCrawl(): int
    {
        $this->info('🚀 Invoking Lambda crawler...');
        
        $functionName = config('crawler_microservice.lambda.function_name');
        if (!$functionName) {
            $this->error('Lambda function name not configured');
            return 1;
        }

        $platforms = $this->option('platforms') ?: ['twitter', 'reddit'];
        $keywords = $this->option('keywords') ?: null;
        $jobId = $this->option('job-id') ?: 'lambda_console_' . uniqid();

        $payload = [
            'job_id' => $jobId,
            'platforms' => $platforms,
            'platform_options' => []
        ];

        if ($keywords) {
            foreach ($platforms as $platform) {
                $payload['platform_options'][$platform] = ['keywords' => $keywords];
            }
        }

        $this->info("Invoking function: {$functionName}");
        $this->info("Payload: " . json_encode($payload, JSON_PRETTY_PRINT));

        // Here you would use AWS SDK to invoke the Lambda function
        // For demo purposes, showing what would happen
        $this->warn('Lambda invocation requires AWS SDK integration');
        $this->info('Use AWS CLI: aws lambda invoke --function-name ' . $functionName . ' --payload \'' . json_encode($payload) . '\' response.json');

        return 0;
    }

    /**
     * Health check action
     */
    private function healthAction(): int
    {
        $this->info('🏥 Running health checks...');

        $crawler = new OctaneCrawlerService();
        $health = $crawler->healthCheck();

        $overallStatus = $health['status'];
        $statusIcon = match($overallStatus) {
            'healthy' => '✅',
            'warning' => '⚠️',
            'degraded' => '🟡',
            'unhealthy' => '❌',
            default => '❓'
        };

        $this->info("{$statusIcon} Overall Status: " . strtoupper($overallStatus));
        $this->info("Timestamp: {$health['timestamp']}");
        $this->info("Execution Time: {$health['execution_time']}s");

        $this->newLine();
        $this->info('📋 Individual Checks:');

        $checkData = [];
        foreach ($health['checks'] as $checkName => $checkResult) {
            $status = $checkResult['healthy'] ? '✅ Healthy' : '❌ Failed';
            $error = $checkResult['error'] ?? '';
            $checkData[] = [$checkName, $status, $error];
        }

        $this->table(['Check', 'Status', 'Error'], $checkData);

        return $overallStatus === 'healthy' ? 0 : 1;
    }

    /**
     * Statistics action
     */
    private function statsAction(): int
    {
        $this->info('📊 Gathering crawler statistics...');

        $crawler = new OctaneCrawlerService();
        $stats = $crawler->getCrawlerStats();

        $this->info('📈 Last 24 Hours Summary:');
        $this->table(['Metric', 'Value'], [
            ['Total Jobs', $stats['last_24_hours']['total_jobs']],
            ['Successful Jobs', $stats['last_24_hours']['successful_jobs']],
            ['Failed Jobs', $stats['last_24_hours']['failed_jobs']],
            ['Running Jobs', $stats['last_24_hours']['running_jobs']],
        ]);

        if (!empty($stats['platform_performance'])) {
            $this->newLine();
            $this->info('🔧 Platform Performance:');
            $platformData = [];
            foreach ($stats['platform_performance'] as $platform => $performance) {
                $successRate = $performance['total_runs'] > 0 
                    ? round(($performance['successful_runs'] / $performance['total_runs']) * 100, 1) 
                    : 0;
                $platformData[] = [
                    $platform,
                    $performance['total_runs'],
                    $performance['successful_runs'],
                    $successRate . '%',
                ];
            }
            $this->table(['Platform', 'Total Runs', 'Successful', 'Success Rate'], $platformData);
        }

        if (!empty($stats['recent_posts'])) {
            $this->newLine();
            $this->info('📝 Recent Posts (Last Hour):');
            $recentData = [];
            foreach ($stats['recent_posts'] as $platform => $count) {
                $recentData[] = [$platform, $count];
            }
            $this->table(['Platform', 'Posts'], $recentData);
        }

        $this->newLine();
        $this->info('⚙️ Current Status:');
        $this->table(['Setting', 'Value'], [
            ['Enabled Platforms', implode(', ', $stats['current_status']['enabled_platforms'])],
            ['Active Keyword Rules', $stats['current_status']['keyword_rules_active']],
            ['Last Job At', $stats['current_status']['last_job_at'] ?: 'Never'],
        ]);

        return 0;
    }

    /**
     * Keywords management action
     */
    private function keywordsAction(): int
    {
        $this->info('🔑 Keyword Rules Management');

        $choice = $this->choice('What would you like to do?', [
            'list' => 'List all keyword rules',
            'active' => 'List active rules only',
            'create' => 'Create new rule',
            'refresh' => 'Refresh rules cache',
            'stats' => 'Show keyword statistics',
        ], 'list');

        switch ($choice) {
            case 'list':
                return $this->listKeywordRules(false);
            case 'active':
                return $this->listKeywordRules(true);
            case 'create':
                return $this->createKeywordRule();
            case 'refresh':
                return $this->refreshKeywordRules();
            case 'stats':
                return $this->showKeywordStats();
        }

        return 0;
    }

    /**
     * List keyword rules
     */
    private function listKeywordRules(bool $activeOnly = false): int
    {
        $query = CrawlerKeywordRule::query();
        
        if ($activeOnly) {
            $query->where('is_active', true);
            $this->info('📋 Active Keyword Rules:');
        } else {
            $this->info('📋 All Keyword Rules:');
        }

        $rules = $query->orderByDesc('priority')->orderBy('name')->get();

        if ($rules->isEmpty()) {
            $this->warn('No keyword rules found.');
            return 0;
        }

        $ruleData = [];
        foreach ($rules as $rule) {
            $keywords = is_array($rule->keywords) ? $rule->keywords : json_decode($rule->keywords, true);
            $platforms = is_array($rule->platforms) ? $rule->platforms : json_decode($rule->platforms, true);
            
            $ruleData[] = [
                $rule->id,
                $rule->name,
                $rule->category,
                $rule->priority,
                implode(', ', array_slice($keywords, 0, 3)) . (count($keywords) > 3 ? '...' : ''),
                implode(', ', $platforms),
                $rule->is_active ? '✅' : '❌',
            ];
        }

        $this->table(
            ['ID', 'Name', 'Category', 'Priority', 'Keywords', 'Platforms', 'Active'],
            $ruleData
        );

        return 0;
    }

    /**
     * Create keyword rule
     */
    private function createKeywordRule(): int
    {
        $this->info('📝 Creating new keyword rule...');

        $name = $this->ask('Rule name');
        if (!$name) {
            $this->error('Rule name is required');
            return 1;
        }

        $category = $this->ask('Category', 'general');
        $priority = (int) $this->ask('Priority (1-10)', '5');
        
        $keywordsInput = $this->ask('Keywords (comma-separated)');
        $keywords = array_map('trim', explode(',', $keywordsInput));

        $platformsInput = $this->ask('Platforms (comma-separated)', 'twitter,reddit,telegram');
        $platforms = array_map('trim', explode(',', $platformsInput));

        $isActive = $this->confirm('Activate rule?', true);

        try {
            $rule = CrawlerKeywordRule::create([
                'name' => $name,
                'keywords' => $keywords,
                'platforms' => $platforms,
                'category' => $category,
                'priority' => $priority,
                'is_active' => $isActive,
            ]);

            $this->info("✅ Created keyword rule: {$rule->name} (ID: {$rule->id})");

            // Refresh cache
            Cache::forget('crawler_keyword_rules_compiled');
            $this->info('♻️ Keyword rules cache refreshed');

            return 0;

        } catch (\Exception $e) {
            $this->error("Failed to create keyword rule: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Refresh keyword rules cache
     */
    private function refreshKeywordRules(): int
    {
        $this->info('♻️ Refreshing keyword rules cache...');

        try {
            $keywordEngine = new AdvancedKeywordEngine();
            $keywordEngine->refreshRules();

            $activeRules = CrawlerKeywordRule::where('is_active', true)->count();
            $this->info("✅ Cache refreshed successfully. {$activeRules} active rules loaded.");

            return 0;

        } catch (\Exception $e) {
            $this->error("Failed to refresh cache: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Show keyword statistics
     */
    private function showKeywordStats(): int
    {
        $this->info('📊 Keyword Rules Statistics');

        $totalRules = CrawlerKeywordRule::count();
        $activeRules = CrawlerKeywordRule::where('is_active', true)->count();
        $avgPriority = CrawlerKeywordRule::avg('priority');

        $this->table(['Metric', 'Value'], [
            ['Total Rules', $totalRules],
            ['Active Rules', $activeRules],
            ['Inactive Rules', $totalRules - $activeRules],
            ['Average Priority', round($avgPriority, 1)],
        ]);

        // Category breakdown
        $categories = CrawlerKeywordRule::where('is_active', true)
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->orderByDesc('count')
            ->get();

        if ($categories->isNotEmpty()) {
            $this->newLine();
            $this->info('📂 Categories:');
            $categoryData = $categories->map(fn($cat) => [$cat->category, $cat->count])->toArray();
            $this->table(['Category', 'Rules'], $categoryData);
        }

        return 0;
    }

    /**
     * Test action
     */
    private function testAction(): int
    {
        $this->info('🧪 Running crawler tests...');

        // Test keyword engine
        $this->info('Testing keyword engine...');
        $keywordEngine = new AdvancedKeywordEngine();
        
        $testContent = "This is a test about blockchain and cryptocurrency. Ethereum smart contracts are revolutionary.";
        $matches = $keywordEngine->matchContent($testContent, 'twitter');

        $this->info("Test content: {$testContent}");
        $this->info("Matches found: " . count($matches));

        if (!empty($matches)) {
            $matchData = [];
            foreach ($matches as $match) {
                $matchData[] = [
                    $match['keyword'],
                    $match['category'],
                    $match['priority'],
                    round($match['score'], 2),
                ];
            }
            $this->table(['Keyword', 'Category', 'Priority', 'Score'], $matchData);
        }

        // Test configuration
        $this->newLine();
        $this->info('Testing configuration...');
        $config = config('crawler_microservice');
        
        $configStatus = [];
        foreach (['twitter', 'reddit', 'telegram'] as $platform) {
            $enabled = $config['platforms'][$platform]['enabled'] ?? false;
            $hasCredentials = !empty($config['platforms'][$platform]['bearer_token'] ?? '') ||
                            (!empty($config['platforms'][$platform]['client_id'] ?? '') && 
                             !empty($config['platforms'][$platform]['client_secret'] ?? '')) ||
                            !empty($config['platforms'][$platform]['bot_token'] ?? '');
            
            $status = $enabled && $hasCredentials ? '✅ Ready' : 
                     ($enabled ? '⚠️ Missing credentials' : '❌ Disabled');
            
            $configStatus[] = [$platform, $status];
        }

        $this->table(['Platform', 'Status'], $configStatus);

        $this->info('✅ Test completed');

        return 0;
    }

    /**
     * Invalid action handler
     */
    private function invalidAction(string $action): int
    {
        $this->error("Invalid action: {$action}");
        $this->info('Available actions: execute, health, stats, keywords, test');
        return 1;
    }
}
