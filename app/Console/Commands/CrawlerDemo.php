<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CrawlerMicroService\SocialMediaCrawler;
use App\Services\CrawlerMicroService\CrawlerOrchestrator;
use App\Models\CrawlerKeywordRule;
use App\Models\SocialMediaPost;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

final class CrawlerDemo extends Command
{
    protected $signature = 'crawler:demo
                           {--keywords=blockchain,security,defi : Comma-separated keywords to demonstrate}
                           {--posts=10 : Number of sample posts to generate}
                           {--show-analytics : Show analytics dashboard after demo}';

    protected $description = 'Demonstrate crawler micro-service capabilities with simulated data';

    public function handle(): int
    {
        $this->displayHeader();
        
        $keywords = explode(',', $this->option('keywords'));
        $postCount = (int) $this->option('posts');
        
        $this->info("🎯 Demo Configuration:");
        $this->table(['Setting', 'Value'], [
            ['Keywords', implode(', ', $keywords)],
            ['Sample Posts', $postCount],
            ['Platforms', 'Twitter, Reddit, Telegram'],
            ['Mode', 'Simulation (Safe Demo)']
        ]);
        
        $this->newLine();
        $this->info('🚀 Starting Crawler Micro-Service Demo...');
        $this->newLine();

        // Demonstrate crawler workflow
        $this->demonstrateCrawlerWorkflow($keywords, $postCount);
        
        if ($this->option('show-analytics')) {
            $this->showAnalyticsDashboard();
        }
        
        $this->displaySummary();
        
        return Command::SUCCESS;
    }

    private function displayHeader(): void
    {
        $this->info('🕷️  SOCIAL MEDIA CRAWLER MICRO-SERVICE DEMO');
        $this->info('Comprehensive demonstration of multi-platform crawling capabilities');
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->newLine();
    }

    private function demonstrateCrawlerWorkflow(array $keywords, int $postCount): void
    {
        // Step 1: Platform Configuration
        $this->demonstratePlatformConfig();
        
        // Step 2: Keyword Rules
        $this->demonstrateKeywordRules($keywords);
        
        // Step 3: Simulated Crawling
        $this->simulateCrawlingProcess($keywords, $postCount);
        
        // Step 4: Data Processing
        $this->demonstrateDataProcessing();
        
        // Step 5: Results Analysis
        $this->demonstrateResultsAnalysis();
    }

    private function demonstratePlatformConfig(): void
    {
        $this->info('📋 Step 1: Platform Configuration');
        
        $platforms = [
            'Twitter/X' => [
                'API' => 'Twitter API v2',
                'Auth' => 'Bearer Token',
                'Features' => 'Real-time search, user tweets, trending topics',
                'Rate Limit' => '300 requests/15min',
                'Status' => '🟡 Demo Mode'
            ],
            'Reddit' => [
                'API' => 'Reddit API',
                'Auth' => 'OAuth2',
                'Features' => 'Subreddit posts, comments, search',
                'Rate Limit' => '100 requests/min',
                'Status' => '🟡 Demo Mode'
            ],
            'Telegram' => [
                'API' => 'Bot API + MTProto',
                'Auth' => 'Bot Token',
                'Features' => 'Channel messages, group posts, media',
                'Rate Limit' => '30 requests/sec',
                'Status' => '🟡 Demo Mode'
            ]
        ];

        foreach ($platforms as $platform => $config) {
            $this->line("📱 {$platform}:");
            foreach ($config as $key => $value) {
                $this->line("   {$key}: {$value}");
            }
            $this->newLine();
        }
    }

    private function demonstrateKeywordRules(array $keywords): void
    {
        $this->info('🔑 Step 2: Keyword Rules Setup');
        
        $rules = [
            [
                'name' => 'Security Focus',
                'keywords' => array_slice($keywords, 0, 2),
                'platforms' => ['twitter', 'reddit'],
                'priority' => 'high',
                'filters' => ['min_engagement' => 5, 'verified_accounts' => true]
            ],
            [
                'name' => 'General Crypto',
                'keywords' => array_slice($keywords, 2),
                'platforms' => ['twitter', 'reddit', 'telegram'],
                'priority' => 'medium',
                'filters' => ['language' => 'en', 'exclude_spam' => true]
            ]
        ];

        $ruleData = [];
        foreach ($rules as $rule) {
            $ruleData[] = [
                $rule['name'],
                implode(', ', $rule['keywords']),
                implode(', ', $rule['platforms']),
                ucfirst($rule['priority'])
            ];
        }

        $this->table(['Rule Name', 'Keywords', 'Platforms', 'Priority'], $ruleData);
        $this->newLine();
    }

    private function simulateCrawlingProcess(array $keywords, int $postCount): void
    {
        $this->info('🔄 Step 3: Simulated Crawling Process');
        
        $progressBar = $this->output->createProgressBar(100);
        $progressBar->setFormat('🕷️  Crawling: %current%/%max% [%bar%] %percent:3s%% - %message%');
        $progressBar->start();

        $platforms = ['Twitter', 'Reddit', 'Telegram'];
        $results = [];

        foreach ($platforms as $i => $platform) {
            $progressBar->setMessage("Searching {$platform}...");
            
            // Simulate API calls
            for ($j = 0; $j < 33; $j++) {
                $progressBar->advance();
                usleep(30000); // 30ms delay for realistic feel
            }
            
            // Generate realistic results
            $platformResults = $this->generatePlatformResults($platform, $keywords, $postCount);
            $results[$platform] = $platformResults;
            
            $progressBar->setMessage("{$platform} completed: {$platformResults['posts_found']} posts");
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->displayCrawlingResults($results);
    }

    private function generatePlatformResults(string $platform, array $keywords, int $maxPosts): array
    {
        $baseMultipliers = [
            'Twitter' => 1.5,
            'Reddit' => 0.8,
            'Telegram' => 0.6
        ];

        $multiplier = $baseMultipliers[$platform] ?? 1.0;
        $postsFound = (int) (rand(5, $maxPosts) * $multiplier);
        $keywordMatches = rand(2, min($postsFound, count($keywords) * 3));
        $processingTime = rand(800, 2500);

        return [
            'posts_found' => $postsFound,
            'keyword_matches' => $keywordMatches,
            'processing_time_ms' => $processingTime,
            'status' => 'completed',
            'unique_authors' => rand(3, $postsFound),
            'avg_sentiment' => round(rand(-50, 80) / 100, 3)
        ];
    }

    private function displayCrawlingResults(array $results): void
    {
        $this->info('📊 Crawling Results Summary');
        
        $resultData = [];
        $totalPosts = 0;
        $totalMatches = 0;
        
        foreach ($results as $platform => $result) {
            $resultData[] = [
                "📱 {$platform}",
                $result['posts_found'],
                $result['keyword_matches'],
                $result['unique_authors'],
                $result['avg_sentiment'],
                $result['processing_time_ms'] . 'ms'
            ];
            
            $totalPosts += $result['posts_found'];
            $totalMatches += $result['keyword_matches'];
        }

        $this->table(['Platform', 'Posts Found', 'Keyword Matches', 'Authors', 'Avg Sentiment', 'Time'], $resultData);
        
        $this->newLine();
        $this->info("🎯 Total Results: {$totalPosts} posts collected, {$totalMatches} keyword matches");
        $this->newLine();
    }

    private function demonstrateDataProcessing(): void
    {
        $this->info('⚙️  Step 4: Data Processing Pipeline');
        
        $pipeline = [
            'Content Cleaning' => '✅ Remove spam, normalize text, extract entities',
            'Sentiment Analysis' => '✅ Google Cloud Natural Language API integration',
            'Keyword Matching' => '✅ Fuzzy matching, stemming, context awareness',
            'Duplicate Detection' => '✅ Hash-based deduplication across platforms',
            'Data Enrichment' => '✅ Author metrics, engagement scoring, trends',
            'Storage Optimization' => '✅ Compressed JSON, indexed for fast queries'
        ];

        foreach ($pipeline as $step => $description) {
            $this->line("   {$step}: {$description}");
        }
        $this->newLine();
    }

    private function demonstrateResultsAnalysis(): void
    {
        $this->info('📈 Step 5: Results Analysis & Insights');
        
        // Sample insights
        $insights = [
            'Top Keywords' => [
                'blockchain → 45 mentions',
                'security → 32 mentions', 
                'smart contract → 28 mentions'
            ],
            'Platform Performance' => [
                'Twitter: Highest volume, real-time updates',
                'Reddit: Quality discussions, technical depth',
                'Telegram: Community insights, breaking news'
            ],
            'Sentiment Trends' => [
                'Overall: Neutral to Positive (0.23)',
                'Security topics: More cautious (-0.15)',
                'Innovation topics: Very positive (0.67)'
            ],
            'Time Patterns' => [
                'Peak activity: 9-11 AM, 2-4 PM UTC',
                'Weekend discussions more technical',
                'Breaking news drives immediate spikes'
            ]
        ];

        foreach ($insights as $category => $items) {
            $this->line("🔍 {$category}:");
            foreach ($items as $item) {
                $this->line("   • {$item}");
            }
            $this->newLine();
        }
    }

    private function showAnalyticsDashboard(): void
    {
        $this->newLine();
        $this->info('📊 ANALYTICS DASHBOARD PREVIEW');
        $this->info('═══════════════════════════════════════');
        $this->newLine();

        // Mock real-time metrics
        $metrics = [
            ['Active Jobs', '3'],
            ['Posts/Hour', '145'],
            ['Success Rate', '98.7%'],
            ['Avg Response Time', '1.2s'],
            ['Cache Hit Rate', '89%'],
            ['API Quotas Used', '23% Twitter, 15% Reddit, 8% Telegram']
        ];

        $this->table(['Metric', 'Value'], $metrics);
        $this->newLine();

        // Recent activity simulation
        $this->info('📝 Recent Activity (Last 5 minutes):');
        $activities = [
            '🐦 Twitter: "DeFi protocol launches new governance token" - Positive sentiment',
            '📋 Reddit: "Smart contract audit reveals critical vulnerability" - Negative sentiment', 
            '📢 Telegram: "Blockchain conference announces speakers" - Neutral sentiment',
            '🐦 Twitter: "Security researcher discovers new attack vector" - Negative sentiment',
            '📋 Reddit: "Guide to secure smart contract development" - Positive sentiment'
        ];

        foreach ($activities as $activity) {
            $this->line("   {$activity}");
        }
        $this->newLine();
    }

    private function displaySummary(): void
    {
        $this->info('🎉 CRAWLER MICRO-SERVICE DEMO COMPLETE');
        $this->info('═══════════════════════════════════════════════════');
        $this->newLine();

        $this->info('🚀 Key Capabilities Demonstrated:');
        $capabilities = [
            '✅ Multi-platform crawling (Twitter, Reddit, Telegram)',
            '✅ Advanced keyword rule engine with filters',
            '✅ Real-time sentiment analysis integration',
            '✅ Intelligent rate limiting and error handling',
            '✅ Comprehensive data processing pipeline',
            '✅ Performance monitoring and analytics',
            '✅ Scalable architecture (Octane + Queue workers)',
            '✅ RESTful API for external integrations'
        ];

        foreach ($capabilities as $capability) {
            $this->line("   {$capability}");
        }
        $this->newLine();

        $this->info('🛠️  Available Commands:');
        $commands = [
            'crawler:start     → Start crawling with custom keywords',
            'crawler:status    → Monitor job status and system health', 
            'crawler:dashboard → View comprehensive analytics',
            'crawler:config    → Manage configuration and test connections'
        ];

        foreach ($commands as $command) {
            $this->line("   {$command}");
        }
        $this->newLine();

        $this->info('🔧 Next Steps:');
        $this->line('   1. Configure API credentials in .env');
        $this->line('   2. Set up keyword rules: php artisan crawler:config rules');
        $this->line('   3. Test connections: php artisan crawler:config test');
        $this->line('   4. Start real crawling: php artisan crawler:start');
        $this->newLine();

        $this->info('📖 For detailed setup instructions, see: CRAWLER_MICROSERVICE_GUIDE.md');
    }
}