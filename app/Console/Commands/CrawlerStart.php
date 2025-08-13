<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CrawlerMicroService\SocialMediaCrawler;
use App\Services\CrawlerMicroService\CrawlerOrchestrator;
use App\Models\CrawlerKeywordRule;
use App\Models\CrawlerJobStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

final class CrawlerStart extends Command
{
    protected $signature = 'crawler:start
                           {--platforms=* : Specific platforms to crawl (twitter,reddit,telegram)}
                           {--keywords=* : Specific keywords to search for}
                           {--max-posts=100 : Maximum posts to collect}
                           {--priority=normal : Job priority (low,normal,high,urgent)}
                           {--rule-id= : Use specific keyword rule ID}
                           {--async : Run job asynchronously}
                           {--format=table : Output format (table,json,csv)}';

    protected $description = 'Start social media crawler with keyword rules';

    public function handle(): int
    {
        $this->displayHeader();
        
        if (!$this->validateConfiguration()) {
            return Command::FAILURE;
        }

        $jobConfig = $this->buildJobConfig();
        
        $this->info("🚀 Starting crawler job with configuration:");
        $this->displayJobConfig($jobConfig);
        
        if (!$this->confirm('Start crawling job?', true)) {
            $this->warn('❌ Crawler job cancelled by user');
            return Command::SUCCESS;
        }

        if ($this->option('async')) {
            return $this->startAsyncJob($jobConfig);
        } else {
            return $this->startSyncJob($jobConfig);
        }
    }

    private function displayHeader(): void
    {
        $this->info('🕷️  SOCIAL MEDIA CRAWLER MICRO-SERVICE');
        $this->info('High-performance crawling for Twitter/X, Reddit, and Telegram');
        $this->newLine();
    }

    private function validateConfiguration(): bool
    {
        $config = config('crawler_microservice');
        
        if (!$config['enabled']) {
            $this->error('❌ Crawler micro-service is disabled in configuration');
            return false;
        }

        $issues = [];
        
        // Check platform configurations
        if ($config['twitter']['enabled'] && empty($config['twitter']['bearer_token'])) {
            $issues[] = 'Twitter Bearer Token not configured';
        }
        
        if ($config['reddit']['enabled'] && (empty($config['reddit']['client_id']) || empty($config['reddit']['client_secret']))) {
            $issues[] = 'Reddit API credentials not configured';
        }
        
        if ($config['telegram']['enabled'] && empty($config['telegram']['bot_token'])) {
            $issues[] = 'Telegram Bot Token not configured';
        }

        if (!empty($issues)) {
            $this->error('❌ Configuration issues found:');
            foreach ($issues as $issue) {
                $this->line("   • {$issue}");
            }
            $this->newLine();
            $this->info('💡 These platforms will be skipped during crawling');
            return true; // Continue but with warnings
        }

        $this->info('✅ Configuration validated successfully');
        return true;
    }

    private function buildJobConfig(): array
    {
        $platforms = $this->option('platforms');
        if (empty($platforms)) {
            $platforms = $this->getEnabledPlatforms();
        }

        $keywordRules = $this->getKeywordRules();
        
        return [
            'job_id' => 'crawl_' . uniqid(),
            'platforms' => $platforms,
            'keyword_rules' => $keywordRules,
            'max_posts' => (int) $this->option('max-posts'),
            'priority' => $this->option('priority'),
            'started_by' => 'artisan_command',
            'user_id' => null,
            'options' => [
                'async' => $this->option('async'),
                'format' => $this->option('format')
            ]
        ];
    }

    private function getEnabledPlatforms(): array
    {
        $config = config('crawler_microservice');
        $enabled = [];
        
        if ($config['twitter']['enabled']) $enabled[] = 'twitter';
        if ($config['reddit']['enabled']) $enabled[] = 'reddit';
        if ($config['telegram']['enabled']) $enabled[] = 'telegram';
        
        return $enabled;
    }

    private function getKeywordRules(): array
    {
        if ($ruleId = $this->option('rule-id')) {
            $rule = CrawlerKeywordRule::find($ruleId);
            if (!$rule) {
                $this->error("❌ Keyword rule with ID {$ruleId} not found");
                exit(1);
            }
            return [$rule->toCrawlerConfig()];
        }

        if ($keywords = $this->option('keywords')) {
            return [[
                'rule_id' => 'custom_' . uniqid(),
                'name' => 'Custom Keywords',
                'keywords' => $keywords,
                'platforms' => $this->option('platforms') ?: $this->getEnabledPlatforms(),
                'max_posts' => (int) $this->option('max-posts'),
                'priority' => $this->option('priority')
            ]];
        }

        // Get active keyword rules
        $rules = CrawlerKeywordRule::active()->get();
        
        if ($rules->isEmpty()) {
            $this->warn('⚠️  No active keyword rules found, creating default rules...');
            $this->createDefaultRules();
            $rules = CrawlerKeywordRule::active()->get();
        }

        return $rules->map->toCrawlerConfig()->toArray();
    }

    private function createDefaultRules(): void
    {
        CrawlerKeywordRule::createDefaults();
        $this->info('✅ Default keyword rules created');
    }

    private function displayJobConfig(array $config): void
    {
        $this->table(['Setting', 'Value'], [
            ['Job ID', $config['job_id']],
            ['Platforms', implode(', ', $config['platforms'])],
            ['Keyword Rules', count($config['keyword_rules'])],
            ['Max Posts', $config['max_posts']],
            ['Priority', $config['priority']],
            ['Async Mode', $config['options']['async'] ? 'Yes' : 'No']
        ]);

        if (!empty($config['keyword_rules'])) {
            $this->newLine();
            $this->info('📋 Active Keyword Rules:');
            
            $ruleData = [];
            foreach ($config['keyword_rules'] as $rule) {
                $ruleData[] = [
                    $rule['name'],
                    implode(', ', array_slice($rule['keywords'], 0, 3)) . (count($rule['keywords']) > 3 ? '...' : ''),
                    implode(', ', $rule['platforms']),
                    $rule['priority'] ?? 'normal'
                ];
            }
            
            $this->table(['Rule Name', 'Keywords (Sample)', 'Platforms', 'Priority'], $ruleData);
        }
    }

    private function startAsyncJob(array $config): int
    {
        try {
            // Dispatch to queue
            \App\Jobs\SocialCrawlerJob::dispatch($config)
                ->onQueue(config('crawler_microservice.jobs.priority_queues')[$config['priority']]);
            
            $this->info("✅ Crawler job {$config['job_id']} dispatched to queue");
            $this->info("📊 Monitor progress with: php artisan crawler:status {$config['job_id']}");
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("❌ Failed to dispatch crawler job: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function startSyncJob(array $config): int
    {
        try {
            $this->info('🔄 Starting synchronous crawling...');
            
            $progressBar = $this->output->createProgressBar(count($config['platforms']));
            $progressBar->setFormat('🕷️  Crawling: %current%/%max% [%bar%] %percent:3s%% - %message%');
            $progressBar->start();

            $orchestrator = app(CrawlerOrchestrator::class);
            $results = $orchestrator->executeCrawlJob($config);
            
            $progressBar->finish();
            $this->newLine(2);
            
            $this->displayResults($results);
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("❌ Crawler job failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function displayResults(array $results): void
    {
        $this->info('📊 CRAWLING RESULTS');
        $this->newLine();

        // Summary
        $this->table(['Metric', 'Value'], [
            ['Job ID', $results['job_id']],
            ['Status', $results['status'] ?? 'completed'],
            ['Total Posts', $results['total_posts']],
            ['Total Matches', $results['total_matches']],
            ['Processing Time', $results['processing_time_ms'] ?? 0 . 'ms'],
            ['Completed At', $results['completed_at'] ?? now()->toISOString()]
        ]);

        // Platform breakdown
        if (!empty($results['platforms'])) {
            $this->newLine();
            $this->info('🌐 Platform Results:');
            
            $platformData = [];
            foreach ($results['platforms'] as $platform => $result) {
                $status = $result['status'] ?? 'unknown';
                $statusIcon = $status === 'completed' ? '✅' : ($status === 'error' ? '❌' : '⚠️');
                
                $platformData[] = [
                    $statusIcon . ' ' . ucfirst($platform),
                    $result['posts_found'] ?? 0,
                    $result['keyword_matches'] ?? 0,
                    $result['error'] ?? 'None'
                ];
            }
            
            $this->table(['Platform', 'Posts Found', 'Keyword Matches', 'Error'], $platformData);
        }

        // Errors
        if (!empty($results['errors'])) {
            $this->newLine();
            $this->error('⚠️  Errors encountered:');
            foreach ($results['errors'] as $error) {
                $this->line("   • {$error}");
            }
        }

        $this->newLine();
        
        if ($results['total_posts'] > 0) {
            $this->info("🎉 Crawling completed successfully! {$results['total_posts']} posts collected with {$results['total_matches']} keyword matches.");
        } else {
            $this->warn('⚠️  No posts were collected. Consider adjusting your keyword rules or checking platform configurations.');
        }
    }
}