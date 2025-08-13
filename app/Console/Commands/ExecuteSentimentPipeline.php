<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CrawlerSentimentIntegration;
use App\Jobs\CompleteSentimentPipelineJob;
use App\Models\CrawlerKeywordRule;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ExecuteSentimentPipeline extends Command
{
    protected $signature = 'pipeline:sentiment 
                           {--platforms=* : Platforms to crawl (twitter,reddit,telegram)}
                           {--keywords=* : Keywords to search for}
                           {--rule-ids=* : Use existing keyword rule IDs}
                           {--date= : Date to process (default: today)}
                           {--max-posts=500 : Maximum posts to collect}
                           {--priority=normal : Job priority (low,normal,high,urgent)}
                           {--async : Run pipeline asynchronously}
                           {--scheduled : Run as scheduled pipeline with active rules}
                           {--existing-posts : Process existing posts without sentiment}
                           {--demo : Run demo mode with sample data}
                           {--queue=sentiment-pipeline : Queue name for async execution}';

    protected $description = 'Execute complete sentiment analysis pipeline: Crawl → Text Aggregation → Google NLP → Daily Aggregates';

    public function handle(CrawlerSentimentIntegration $integration): int
    {
        $this->displayHeader();

        try {
            if ($this->option('demo')) {
                return $this->runDemoMode($integration);
            }

            if ($this->option('existing-posts')) {
                return $this->processExistingPosts($integration);
            }

            if ($this->option('scheduled')) {
                return $this->runScheduledPipeline($integration);
            }

            return $this->runCustomPipeline($integration);

        } catch (\Exception $e) {
            $this->error('❌ Pipeline execution failed: ' . $e->getMessage());
            
            if ($this->option('verbose')) {
                $this->error('Stack trace:');
                $this->line($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    private function displayHeader(): void
    {
        $this->info('🔄 Sentiment Analysis Pipeline');
        $this->info('Flow: Social Media Crawl → Text Aggregation → Google Cloud NLP → Daily Aggregates');
        $this->newLine();
    }

    private function runCustomPipeline(CrawlerSentimentIntegration $integration): int
    {
        $config = $this->buildPipelineConfig();
        
        $this->displayConfiguration($config);

        if ($this->option('async')) {
            return $this->runAsyncPipeline($integration, $config);
        } else {
            return $this->runSyncPipeline($integration, $config);
        }
    }

    private function buildPipelineConfig(): array
    {
        $platforms = $this->option('platforms') ?: ['twitter', 'reddit'];
        $keywords = $this->option('keywords') ?: [];
        $ruleIds = array_map('intval', $this->option('rule-ids') ?: []);
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
        $maxPosts = (int) $this->option('max-posts');
        $priority = $this->option('priority');

        // If no keywords or rule IDs provided, suggest some defaults
        if (empty($keywords) && empty($ruleIds)) {
            $keywords = ['bitcoin', 'ethereum', 'blockchain', 'cryptocurrency'];
            $this->warn('💡 No keywords or rules specified. Using default keywords: ' . implode(', ', $keywords));
        }

        return [
            'platforms' => $platforms,
            'keyword_rules' => $keywords,
            'keyword_rule_ids' => $ruleIds,
            'date' => $date->toDateString(),
            'max_posts' => $maxPosts,
            'priority' => $priority
        ];
    }

    private function displayConfiguration(array $config): void
    {
        $this->info('📋 Pipeline Configuration:');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Date', $config['date']],
                ['Platforms', implode(', ', $config['platforms'])],
                ['Keywords', implode(', ', $config['keyword_rules'])],
                ['Rule IDs', implode(', ', $config['keyword_rule_ids']) ?: 'None'],
                ['Max Posts', number_format($config['max_posts'])],
                ['Priority', $config['priority']],
                ['Execution', $this->option('async') ? 'Asynchronous' : 'Synchronous'],
            ]
        );
        $this->newLine();
    }

    private function runSyncPipeline(CrawlerSentimentIntegration $integration, array $config): int
    {
        $this->info('🚀 Starting synchronous pipeline execution...');
        $this->newLine();

        $startTime = microtime(true);
        
        // Create progress bar for visual feedback
        $this->output->progressStart(4);

        try {
            // Execute complete pipeline
            $results = $integration->executePipeline($config);
            
            $this->output->progressFinish();
            $duration = microtime(true) - $startTime;

            // Display detailed results
            $this->displayResults($results, $duration);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->output->progressFinish();
            throw $e;
        }
    }

    private function runAsyncPipeline(CrawlerSentimentIntegration $integration, array $config): int
    {
        $this->info('⚡ Queueing pipeline for asynchronous execution...');
        
        $pipelineId = 'pipeline_' . Str::random(8);
        $queue = $this->option('queue');

        // Queue the complete pipeline job
        CompleteSentimentPipelineJob::dispatch($config, $pipelineId)
            ->onQueue($queue);

        $this->info("✅ Pipeline queued successfully!");
        $this->table(
            ['Property', 'Value'],
            [
                ['Pipeline ID', $pipelineId],
                ['Queue', $queue],
                ['Status', 'Queued'],
                ['Check Status', "php artisan pipeline:status {$pipelineId}"]
            ]
        );

        return Command::SUCCESS;
    }

    private function runScheduledPipeline(CrawlerSentimentIntegration $integration): int
    {
        $this->info('📅 Running scheduled sentiment pipeline...');
        
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::yesterday();
        
        $this->info("Processing date: {$date->toDateString()}");
        $this->newLine();

        $results = $integration->executeScheduledPipeline($date);
        
        $this->displayResults($results, 0);

        return Command::SUCCESS;
    }

    private function processExistingPosts(CrawlerSentimentIntegration $integration): int
    {
        $this->info('🔄 Processing existing posts through sentiment pipeline...');
        
        $filters = [];
        
        // Get filter options
        if ($this->option('platforms')) {
            $platforms = $this->option('platforms');
            if (count($platforms) === 1) {
                $filters['platform'] = $platforms[0];
            }
        }
        
        if ($this->option('date')) {
            $date = Carbon::parse($this->option('date'));
            $filters['date_from'] = $date->startOfDay();
            $filters['date_to'] = $date->endOfDay();
        }
        
        $filters['limit'] = (int) $this->option('max-posts');

        $this->info('📋 Processing filters:');
        foreach ($filters as $key => $value) {
            $this->line("  • {$key}: {$value}");
        }
        $this->newLine();

        $results = $integration->processExistingPosts($filters);
        
        $this->info('✅ Existing posts processing completed!');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Status', $results['status']],
                ['Posts Processed', $results['posts_processed'] ?? 0],
                ['Batch ID', $results['batch_id'] ?? 'N/A']
            ]
        );

        return Command::SUCCESS;
    }

    private function runDemoMode(CrawlerSentimentIntegration $integration): int
    {
        $this->info('🎯 Running demo mode with sample configuration...');
        $this->newLine();

        $demoConfig = [
            'platforms' => ['twitter', 'reddit'],
            'keyword_rules' => ['blockchain security', 'smart contract', 'defi exploit'],
            'date' => Carbon::today()->toDateString(),
            'max_posts' => 50,
            'priority' => 'high'
        ];

        $this->displayConfiguration($demoConfig);
        
        $this->warn('⚠️  Demo mode - limited data collection');
        $this->newLine();

        if (!$this->confirm('Continue with demo execution?', true)) {
            $this->info('Demo cancelled.');
            return Command::SUCCESS;
        }

        $results = $integration->executePipeline($demoConfig);
        $this->displayResults($results, 0);

        $this->newLine();
        $this->info('💡 Demo completed! For production use:');
        $this->line('  • Configure API credentials');
        $this->line('  • Set up Google Cloud NLP');
        $this->line('  • Create keyword rules');
        $this->line('  • Schedule regular execution');

        return Command::SUCCESS;
    }

    private function displayResults(array $results, float $duration): void
    {
        $status = $results['status'] ?? 'unknown';
        $summary = $results['summary'] ?? [];
        
        $this->newLine();
        $this->info('📊 Pipeline Results:');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        // Status indicator
        $statusEmoji = match($status) {
            'completed' => '✅',
            'completed_with_errors' => '⚠️',
            'failed' => '❌',
            default => '⚪'
        };

        $this->line("{$statusEmoji} Status: {$status}");
        $this->newLine();

        // Summary metrics
        $this->table(
            ['Metric', 'Value'],
            [
                ['Pipeline ID', $results['pipeline_id'] ?? 'N/A'],
                ['Total Posts Collected', number_format($summary['total_posts_collected'] ?? 0)],
                ['Keyword Matches', number_format($summary['keyword_matches'] ?? 0)],
                ['Documents Analyzed', number_format($summary['documents_analyzed'] ?? 0)],
                ['Daily Aggregates Created', number_format($summary['daily_aggregates_created'] ?? 0)],
                ['Success Rate', ($summary['success_rate'] ?? 0) . '%'],
                ['Data Quality Score', ($summary['data_quality_score'] ?? 0) . '%'],
                ['Total Duration', number_format($results['total_duration_ms'] ?? ($duration * 1000)) . 'ms'],
            ]
        );

        // Phase breakdown
        if (!empty($results['phases'])) {
            $this->newLine();
            $this->info('🔄 Phase Breakdown:');
            
            $phaseData = [];
            foreach ($results['phases'] as $phase => $data) {
                $phaseStatus = $data['status'] ?? 'unknown';
                $phaseEmoji = match($phaseStatus) {
                    'completed' => '✅',
                    'completed_with_errors' => '⚠️',
                    'failed' => '❌',
                    default => '⚪'
                };

                $phaseData[] = [
                    'Phase' => $phaseEmoji . ' ' . ucfirst(str_replace('_', ' ', $phase)),
                    'Status' => $phaseStatus,
                    'Duration' => number_format($data['duration_ms'] ?? 0) . 'ms'
                ];
            }

            $this->table(['Phase', 'Status', 'Duration'], $phaseData);
        }

        // Platform breakdown
        if (!empty($summary['platforms_processed'])) {
            $this->newLine();
            $this->info('🌐 Platforms Processed: ' . implode(', ', $summary['platforms_processed']));
        }

        // Processing chain timing
        if (!empty($summary['processing_chain'])) {
            $this->newLine();
            $this->info('⏱️  Processing Chain Timing:');
            foreach ($summary['processing_chain'] as $phase => $duration) {
                $this->line("  • " . ucfirst(str_replace('_', ' ', $phase)) . ": " . number_format($duration) . "ms");
            }
        }

        // Errors (if any)
        if (!empty($results['errors'])) {
            $this->newLine();
            $this->error('❌ Errors encountered:');
            foreach ($results['errors'] as $error) {
                $this->line("  • {$error}");
            }
        }

        // Performance insights
        $this->newLine();
        $this->info('💡 Insights:');
        $insights = $this->generateInsights($results, $summary);
        foreach ($insights as $insight) {
            $this->line("  • {$insight}");
        }
    }

    private function generateInsights(array $results, array $summary): array
    {
        $insights = [];
        
        // Performance insight
        $totalDuration = $results['total_duration_ms'] ?? 0;
        if ($totalDuration < 30000) {
            $insights[] = '🚀 Excellent pipeline performance';
        } elseif ($totalDuration < 120000) {
            $insights[] = '⚡ Good pipeline performance';
        } else {
            $insights[] = '🐌 Consider optimizing pipeline performance';
        }

        // Data quality insight
        $qualityScore = $summary['data_quality_score'] ?? 0;
        if ($qualityScore > 80) {
            $insights[] = '🎯 High data quality achieved';
        } elseif ($qualityScore > 60) {
            $insights[] = '👍 Good data quality';
        } else {
            $insights[] = '🔍 Consider improving keyword targeting';
        }

        // Success rate insight
        $successRate = $summary['success_rate'] ?? 0;
        if ($successRate >= 100) {
            $insights[] = '✅ All pipeline phases completed successfully';
        } elseif ($successRate >= 75) {
            $insights[] = '⚠️ Most phases completed with some issues';
        } else {
            $insights[] = '❌ Multiple pipeline phases failed - check configuration';
        }

        // Data volume insight
        $postsCollected = $summary['total_posts_collected'] ?? 0;
        $documentsAnalyzed = $summary['documents_analyzed'] ?? 0;
        
        if ($postsCollected > 0 && $documentsAnalyzed > 0) {
            $analysisRate = ($documentsAnalyzed / $postsCollected) * 100;
            if ($analysisRate > 90) {
                $insights[] = '📊 Excellent sentiment analysis coverage';
            } elseif ($analysisRate > 70) {
                $insights[] = '📈 Good sentiment analysis coverage';
            } else {
                $insights[] = '📉 Improve sentiment analysis processing';
            }
        }

        return $insights;
    }
}