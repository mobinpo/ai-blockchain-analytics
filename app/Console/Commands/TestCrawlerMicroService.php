<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CrawlerMicroService\CrawlerOrchestrator;
use App\Models\CrawlerKeywordRule;
use App\Services\SocialCrawler\KeywordMatcher;
use App\Services\SocialCrawler\SentimentAnalyzer;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TestCrawlerMicroService extends Command
{
    protected $signature = 'crawler:test 
                           {--platforms=* : Platforms to crawl (twitter,reddit,telegram)}
                           {--keywords=* : Keywords to search for}
                           {--max-posts=10 : Maximum posts to collect}
                           {--priority=normal : Job priority (low,normal,high,urgent)}
                           {--rule-id= : Use existing keyword rule ID}
                           {--dry-run : Test without actually making API calls}';

    protected $description = 'Test the crawler micro-service with sample data';

    public function handle(): int
    {
        $this->info('🤖 Testing Crawler Micro-Service');
        $this->newLine();

        // Parse options
        $platforms = $this->option('platforms') ?: ['twitter', 'reddit'];
        $keywords = $this->option('keywords') ?: ['bitcoin', 'ethereum', 'blockchain'];
        $maxPosts = (int) $this->option('max-posts');
        $priority = $this->option('priority');
        $ruleId = $this->option('rule-id');
        $dryRun = $this->option('dry-run');

        // Display configuration
        $this->displayConfiguration($platforms, $keywords, $maxPosts, $priority, $dryRun);

        try {
            if ($dryRun) {
                return $this->runDryTest($platforms, $keywords, $maxPosts);
            }

            // Initialize dependencies
            $keywordMatcher = new KeywordMatcher();
            $sentimentAnalyzer = new SentimentAnalyzer();
            $orchestrator = new CrawlerOrchestrator($keywordMatcher, $sentimentAnalyzer);

            // Build job configuration
            $jobConfig = $this->buildJobConfig($platforms, $keywords, $maxPosts, $priority, $ruleId);

            // Execute crawling job
            $this->info('🚀 Starting crawling job...');
            $startTime = microtime(true);

            $results = $orchestrator->executeCrawlJob($jobConfig);

            $duration = round((microtime(true) - $startTime) * 1000);

            // Display results
            $this->displayResults($results, $duration);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Crawler test failed: ' . $e->getMessage());
            
            if ($this->option('verbose')) {
                $this->error('Stack trace:');
                $this->line($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    private function displayConfiguration(array $platforms, array $keywords, int $maxPosts, string $priority, bool $dryRun): void
    {
        $this->info('📋 Test Configuration:');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Platforms', implode(', ', $platforms)],
                ['Keywords', implode(', ', $keywords)],
                ['Max Posts', $maxPosts],
                ['Priority', $priority],
                ['Dry Run', $dryRun ? 'Yes' : 'No'],
            ]
        );
        $this->newLine();
    }

    private function buildJobConfig(array $platforms, array $keywords, int $maxPosts, string $priority, ?string $ruleId): array
    {
        $jobId = 'test_' . Str::random(8);
        
        $config = [
            'job_id' => $jobId,
            'platforms' => $platforms,
            'max_posts' => $maxPosts,
            'priority' => $priority
        ];

        if ($ruleId) {
            $rule = CrawlerKeywordRule::findOrFail($ruleId);
            $config['keyword_rules'] = [$rule->toCrawlerConfig()];
            
            $this->info("📝 Using keyword rule: {$rule->name}");
        } else {
            $config['keyword_rules'] = $keywords;
        }

        return $config;
    }

    private function runDryTest(array $platforms, array $keywords, int $maxPosts): int
    {
        $this->info('🧪 Running dry test (no actual API calls)...');
        $this->newLine();

        // Simulate platform checks
        foreach ($platforms as $platform) {
            $this->checkPlatformConfiguration($platform);
        }

        // Simulate job execution
        $this->info('⏱️  Simulating crawling job execution...');
        
        $mockResults = [
            'job_id' => 'test_dry_run',
            'started_at' => now()->toISOString(),
            'completed_at' => now()->addSeconds(5)->toISOString(),
            'platforms' => [],
            'total_posts' => 0,
            'total_matches' => 0,
            'processing_time_ms' => 5000,
            'status' => 'completed',
            'errors' => []
        ];

        foreach ($platforms as $platform) {
            $simulatedPosts = min($maxPosts, rand(5, 25));
            $simulatedMatches = rand(1, $simulatedPosts);

            $mockResults['platforms'][$platform] = [
                'status' => 'success',
                'posts_found' => $simulatedPosts,
                'keyword_matches' => $simulatedMatches,
                'processing_time_ms' => rand(1000, 3000)
            ];

            $mockResults['total_posts'] += $simulatedPosts;
            $mockResults['total_matches'] += $simulatedMatches;
        }

        $this->displayResults($mockResults, 5000);

        $this->info('✅ Dry run completed successfully!');
        $this->warn('💡 To run with actual API calls, remove the --dry-run flag');

        return Command::SUCCESS;
    }

    private function checkPlatformConfiguration(string $platform): void
    {
        $this->info("🔍 Checking {$platform} configuration...");

        $configChecks = match($platform) {
            'twitter' => $this->checkTwitterConfig(),
            'reddit' => $this->checkRedditConfig(),
            'telegram' => $this->checkTelegramConfig(),
            default => ['status' => 'unknown', 'message' => 'Unknown platform']
        };

        if ($configChecks['status'] === 'ok') {
            $this->line("  ✅ {$platform}: {$configChecks['message']}");
        } else {
            $this->line("  ⚠️  {$platform}: {$configChecks['message']}");
        }
    }

    private function checkTwitterConfig(): array
    {
        $bearerToken = config('social_crawler.apis.twitter.bearer_token');
        
        if (empty($bearerToken)) {
            return ['status' => 'warning', 'message' => 'Bearer token not configured'];
        }

        if (strlen($bearerToken) < 50) {
            return ['status' => 'warning', 'message' => 'Bearer token appears invalid'];
        }

        return ['status' => 'ok', 'message' => 'Configuration looks good'];
    }

    private function checkRedditConfig(): array
    {
        $config = config('social_crawler.apis.reddit');
        
        $required = ['client_id', 'client_secret', 'username', 'password'];
        $missing = [];

        foreach ($required as $key) {
            if (empty($config[$key])) {
                $missing[] = $key;
            }
        }

        if (!empty($missing)) {
            return ['status' => 'warning', 'message' => 'Missing: ' . implode(', ', $missing)];
        }

        return ['status' => 'ok', 'message' => 'Configuration looks good'];
    }

    private function checkTelegramConfig(): array
    {
        $config = config('social_crawler.apis.telegram');
        
        if (empty($config['bot_token'])) {
            return ['status' => 'warning', 'message' => 'Bot token not configured'];
        }

        if (empty($config['channels'])) {
            return ['status' => 'warning', 'message' => 'No channels configured'];
        }

        return ['status' => 'ok', 'message' => 'Configuration looks good'];
    }

    private function displayResults(array $results, int $duration): void
    {
        $this->newLine();
        $this->info('📊 Crawling Results:');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        // Summary table
        $this->table(
            ['Metric', 'Value'],
            [
                ['Job ID', $results['job_id']],
                ['Status', $results['status'] ?? 'unknown'],
                ['Total Posts Found', $results['total_posts']],
                ['Keyword Matches', $results['total_matches']],
                ['Processing Time', $duration . 'ms'],
                ['Match Rate', $this->calculateMatchRate($results)],
            ]
        );

        // Platform breakdown
        if (!empty($results['platforms'])) {
            $this->newLine();
            $this->info('🌐 Platform Breakdown:');
            
            $platformData = [];
            foreach ($results['platforms'] as $platform => $data) {
                $status = $data['status'] ?? 'unknown';
                $statusEmoji = match($status) {
                    'success' => '✅',
                    'error' => '❌',
                    default => '⚪'
                };

                $platformData[] = [
                    'Platform' => $statusEmoji . ' ' . ucfirst($platform),
                    'Posts Found' => $data['posts_found'] ?? 0,
                    'Matches' => $data['keyword_matches'] ?? 0,
                    'Time (ms)' => $data['processing_time_ms'] ?? 0,
                    'Status' => $status
                ];
            }

            $this->table(
                ['Platform', 'Posts Found', 'Matches', 'Time (ms)', 'Status'],
                $platformData
            );
        }

        // Errors
        if (!empty($results['errors'])) {
            $this->newLine();
            $this->error('❌ Errors encountered:');
            foreach ($results['errors'] as $error) {
                $this->line("  • {$error}");
            }
        }

        // Performance insights
        $this->newLine();
        $this->info('💡 Performance Insights:');
        
        $insights = $this->generateInsights($results, $duration);
        foreach ($insights as $insight) {
            $this->line("  • {$insight}");
        }
    }

    private function calculateMatchRate(array $results): string
    {
        $totalPosts = $results['total_posts'] ?? 0;
        $totalMatches = $results['total_matches'] ?? 0;

        if ($totalPosts === 0) {
            return '0%';
        }

        $rate = round(($totalMatches / $totalPosts) * 100, 1);
        return "{$rate}%";
    }

    private function generateInsights(array $results, int $duration): array
    {
        $insights = [];
        
        // Performance insights
        if ($duration < 5000) {
            $insights[] = '🚀 Excellent response time';
        } elseif ($duration < 15000) {
            $insights[] = '⚡ Good response time';
        } else {
            $insights[] = '🐌 Consider optimizing for better performance';
        }

        // Match rate insights
        $totalPosts = $results['total_posts'] ?? 0;
        $totalMatches = $results['total_matches'] ?? 0;
        
        if ($totalPosts > 0) {
            $matchRate = ($totalMatches / $totalPosts) * 100;
            
            if ($matchRate > 80) {
                $insights[] = '🎯 High keyword relevance';
            } elseif ($matchRate > 50) {
                $insights[] = '👍 Good keyword relevance';
            } else {
                $insights[] = '🔍 Consider refining keywords for better matches';
            }
        }

        // Platform insights
        $platforms = $results['platforms'] ?? [];
        $successfulPlatforms = array_filter($platforms, fn($p) => ($p['status'] ?? '') === 'success');
        
        if (count($successfulPlatforms) === count($platforms)) {
            $insights[] = '✅ All platforms responded successfully';
        } elseif (count($successfulPlatforms) > 0) {
            $insights[] = '⚠️ Some platforms had issues';
        } else {
            $insights[] = '❌ All platforms failed - check configuration';
        }

        return $insights;
    }
}