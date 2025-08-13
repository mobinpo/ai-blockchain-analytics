<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DemoSentimentPipeline extends Command
{
    protected $signature = 'pipeline:demo 
                           {--platforms=* : Platforms to demo (twitter,reddit,telegram)}
                           {--keywords=* : Keywords to demo}
                           {--max-posts=50 : Maximum posts to simulate}';

    protected $description = 'Demo the complete sentiment analysis pipeline with simulated data';

    public function handle(): int
    {
        $this->displayHeader();
        
        $platforms = $this->option('platforms') ?: ['twitter', 'reddit'];
        $keywords = $this->option('keywords') ?: ['bitcoin', 'ethereum', 'blockchain'];
        $maxPosts = (int) $this->option('max-posts');

        $this->displayConfiguration($platforms, $keywords, $maxPosts);

        if (!$this->confirm('Start pipeline demo?', true)) {
            $this->info('Demo cancelled.');
            return Command::SUCCESS;
        }

        $pipelineId = 'demo_' . uniqid();
        $this->newLine();
        $this->info("🚀 Starting pipeline demo: {$pipelineId}");
        $this->newLine();

        $totalStartTime = microtime(true);
        $results = [
            'pipeline_id' => $pipelineId,
            'started_at' => now()->toISOString(),
            'phases' => []
        ];

        // Phase 1: Social Media Crawling
        $results['phases']['crawling'] = $this->simulateCrawlingPhase($platforms, $keywords, $maxPosts);
        
        // Phase 2: Text Aggregation
        $results['phases']['text_aggregation'] = $this->simulateTextAggregationPhase();
        
        // Phase 3: Google Cloud NLP Sentiment Analysis
        $results['phases']['sentiment_analysis'] = $this->simulateSentimentAnalysisPhase();
        
        // Phase 4: Daily Aggregates Generation
        $results['phases']['daily_aggregation'] = $this->simulateDailyAggregationPhase();

        $totalDuration = microtime(true) - $totalStartTime;
        $results['total_duration_ms'] = round($totalDuration * 1000);
        $results['completed_at'] = now()->toISOString();
        $results['status'] = 'completed';

        $this->displayResults($results);

        return Command::SUCCESS;
    }

    private function displayHeader(): void
    {
        $this->info('🔄 Complete Sentiment Analysis Pipeline Demo');
        $this->info('Flow: Social Media → Text Aggregation → Google Cloud NLP → Daily Aggregates');
        $this->newLine();
    }

    private function displayConfiguration(array $platforms, array $keywords, int $maxPosts): void
    {
        $this->info('📋 Demo Configuration:');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Platforms', implode(', ', $platforms)],
                ['Keywords', implode(', ', $keywords)],
                ['Max Posts', number_format($maxPosts)],
                ['Mode', 'Simulation (no actual API calls)'],
            ]
        );
        $this->newLine();
    }

    private function simulateCrawlingPhase(array $platforms, array $keywords, int $maxPosts): array
    {
        $this->info('📱 Phase 1: Social Media Crawling');
        $this->output->progressStart(count($platforms));
        
        $startTime = microtime(true);
        $totalPosts = 0;
        $totalMatches = 0;
        $platformResults = [];

        foreach ($platforms as $platform) {
            // Simulate crawling time
            usleep(rand(500, 1500) * 1000); // 0.5-1.5 seconds
            
            $posts = rand(5, min($maxPosts / count($platforms), 25));
            $matches = rand(1, $posts);
            
            $platformResults[$platform] = [
                'posts_found' => $posts,
                'keyword_matches' => $matches,
                'status' => 'success'
            ];
            
            $totalPosts += $posts;
            $totalMatches += $matches;
            
            $this->output->progressAdvance();
        }
        
        $this->output->progressFinish();
        $duration = round((microtime(true) - $startTime) * 1000);
        
        $this->line("  ✅ Collected {$totalPosts} posts, {$totalMatches} keyword matches");
        $this->line("  ⏱️  Duration: {$duration}ms");
        $this->newLine();

        return [
            'status' => 'completed',
            'posts_collected' => $totalPosts,
            'keyword_matches' => $totalMatches,
            'platforms' => $platformResults,
            'duration_ms' => $duration
        ];
    }

    private function simulateTextAggregationPhase(): array
    {
        $this->info('📝 Phase 2: Text Aggregation');
        $this->output->progressStart(3);
        
        $startTime = microtime(true);
        
        // Simulate batch creation
        usleep(300 * 1000); // 0.3 seconds
        $this->output->progressAdvance();
        
        // Simulate text processing
        usleep(800 * 1000); // 0.8 seconds
        $this->output->progressAdvance();
        
        // Simulate document preparation
        usleep(400 * 1000); // 0.4 seconds
        $this->output->progressAdvance();
        
        $this->output->progressFinish();
        $duration = round((microtime(true) - $startTime) * 1000);
        
        $batchId = 'batch_' . Carbon::today()->format('Y_m_d');
        $documentsCount = rand(45, 75);
        
        $this->line("  ✅ Created batch: {$batchId}");
        $this->line("  📄 Documents prepared: {$documentsCount}");
        $this->line("  ⏱️  Duration: {$duration}ms");
        $this->newLine();

        return [
            'status' => 'completed',
            'batch_id' => $batchId,
            'documents_aggregated' => $documentsCount,
            'duration_ms' => $duration
        ];
    }

    private function simulateSentimentAnalysisPhase(): array
    {
        $this->info('🧠 Phase 3: Google Cloud NLP Sentiment Analysis');
        $this->output->progressStart(5);
        
        $startTime = microtime(true);
        $documentsAnalyzed = 0;
        $batches = ['batch_1', 'batch_2', 'batch_3'];
        
        foreach ($batches as $i => $batch) {
            // Simulate Google Cloud NLP API calls
            usleep(rand(1000, 2000) * 1000); // 1-2 seconds per batch
            
            $batchDocs = rand(15, 25);
            $documentsAnalyzed += $batchDocs;
            
            $this->output->progressAdvance();
            
            if ($i < count($batches) - 1) {
                // Add some additional processing steps
                usleep(500 * 1000); // 0.5 seconds
                $this->output->progressAdvance();
            }
        }
        
        $this->output->progressFinish();
        $duration = round((microtime(true) - $startTime) * 1000);
        
        // Generate sentiment distribution
        $sentimentDistribution = [
            'positive' => round(rand(35, 50), 1),
            'neutral' => round(rand(25, 40), 1),
            'negative' => round(rand(15, 30), 1)
        ];
        
        $this->line("  ✅ Analyzed {$documentsAnalyzed} documents with Google Cloud NLP");
        $this->line("  📊 Sentiment: {$sentimentDistribution['positive']}% pos, {$sentimentDistribution['neutral']}% neu, {$sentimentDistribution['negative']}% neg");
        $this->line("  ⏱️  Duration: {$duration}ms");
        $this->newLine();

        return [
            'status' => 'completed',
            'documents_analyzed' => $documentsAnalyzed,
            'batches_processed' => count($batches),
            'sentiment_distribution' => $sentimentDistribution,
            'nlp_provider' => 'Google Cloud Natural Language API',
            'duration_ms' => $duration
        ];
    }

    private function simulateDailyAggregationPhase(): array
    {
        $this->info('📈 Phase 4: Daily Aggregates Generation');
        $this->output->progressStart(4);
        
        $startTime = microtime(true);
        
        // Simulate aggregate calculations
        $platforms = ['twitter', 'reddit', 'telegram'];
        $categories = ['security', 'defi', 'general'];
        $aggregatesCreated = 0;
        
        foreach ($platforms as $platform) {
            usleep(300 * 1000); // 0.3 seconds
            $aggregatesCreated += count($categories);
            $this->output->progressAdvance();
        }
        
        // Final processing
        usleep(500 * 1000); // 0.5 seconds
        $this->output->progressAdvance();
        
        $this->output->progressFinish();
        $duration = round((microtime(true) - $startTime) * 1000);
        
        $totalPostsAggregated = rand(180, 250);
        
        $this->line("  ✅ Generated {$aggregatesCreated} daily aggregates");
        $this->line("  📊 Total posts aggregated: {$totalPostsAggregated}");
        $this->line("  ⏱️  Duration: {$duration}ms");
        $this->newLine();

        return [
            'status' => 'completed',
            'aggregates_created' => $aggregatesCreated,
            'total_posts_aggregated' => $totalPostsAggregated,
            'platforms_processed' => $platforms,
            'categories_processed' => $categories,
            'duration_ms' => $duration
        ];
    }

    private function displayResults(array $results): void
    {
        $this->info('🎉 Pipeline Demo Completed Successfully!');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        // Summary table
        $crawling = $results['phases']['crawling'];
        $aggregation = $results['phases']['text_aggregation'];
        $sentiment = $results['phases']['sentiment_analysis'];
        $daily = $results['phases']['daily_aggregation'];

        $this->table(
            ['Metric', 'Value'],
            [
                ['Pipeline ID', $results['pipeline_id']],
                ['Status', '✅ ' . $results['status']],
                ['Total Duration', number_format($results['total_duration_ms']) . 'ms'],
                ['Posts Collected', number_format($crawling['posts_collected'])],
                ['Keyword Matches', number_format($crawling['keyword_matches'])],
                ['Documents Analyzed', number_format($sentiment['documents_analyzed'])],
                ['Daily Aggregates', number_format($daily['aggregates_created'])],
                ['Match Rate', round(($crawling['keyword_matches'] / $crawling['posts_collected']) * 100, 1) . '%'],
            ]
        );

        // Phase breakdown
        $this->newLine();
        $this->info('📊 Phase Performance:');
        $phaseData = [];
        foreach ($results['phases'] as $phase => $data) {
            $phaseData[] = [
                'Phase' => '✅ ' . ucfirst(str_replace('_', ' ', $phase)),
                'Duration' => number_format($data['duration_ms']) . 'ms',
                'Status' => $data['status']
            ];
        }
        $this->table(['Phase', 'Duration', 'Status'], $phaseData);

        // Data flow summary
        $this->newLine();
        $this->info('🔄 Data Flow Summary:');
        $this->line("  📱 Social Media → {$crawling['posts_collected']} posts collected");
        $this->line("  📝 Text Aggregation → {$aggregation['documents_aggregated']} documents prepared");
        $this->line("  🧠 Google Cloud NLP → {$sentiment['documents_analyzed']} documents analyzed");
        $this->line("  📈 Daily Aggregates → {$daily['aggregates_created']} aggregates generated");

        // Next steps
        $this->newLine();
        $this->info('🚀 Next Steps for Production:');
        $this->line('  1. Configure Google Cloud NLP credentials');
        $this->line('  2. Set up social media API keys');
        $this->line('  3. Configure proxy settings for restricted networks');
        $this->line('  4. Set up monitoring and alerting');
        $this->line('  5. Schedule automated pipeline execution');
        
        $this->newLine();
        $this->info('📚 Available Commands:');
        $this->line('  • php artisan pipeline:sentiment --help  (Full pipeline options)');
        $this->line('  • php artisan crawler:test --help        (Test crawler micro-service)');
        $this->line('  • php artisan security:test-owasp-analysis --help  (Test security analysis)');

        $this->newLine();
        $this->comment('💡 This demo simulated the complete pipeline flow without actual API calls.');
        $this->comment('   The real pipeline integrates social media crawling with Google Cloud NLP for production sentiment analysis.');
    }
}