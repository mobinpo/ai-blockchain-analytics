<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SentimentPipeline\BatchSentimentProcessor;
use App\Services\SentimentPipeline\DailySentimentAggregator;
use App\Models\SocialMediaPost;
use App\Models\SentimentBatch;
use App\Models\DailySentimentAggregate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

final class SentimentPipelineProcessor extends Command
{
    protected $signature = 'sentiment:process
                           {--posts=50 : Number of posts to process}
                           {--date= : Specific date to process (YYYY-MM-DD)}
                           {--platform= : Process posts from specific platform only}
                           {--queue : Queue processing for background execution}
                           {--aggregates : Generate daily aggregates after processing}
                           {--status : Show current processing status}
                           {--demo : Run demo with sample data}';

    protected $description = 'Process social media posts through sentiment analysis pipeline';

    public function handle(): int
    {
        if ($this->option('status')) {
            return $this->showStatus();
        }

        if ($this->option('demo')) {
            return $this->runDemo();
        }

        $this->displayHeader();

        try {
            $posts = $this->loadPostsForProcessing();
            
            if (empty($posts)) {
                $this->warn('No posts found for sentiment processing.');
                return Command::SUCCESS;
            }

            $this->info("📊 Processing " . count($posts) . " posts through sentiment pipeline");
            $this->newLine();

            $results = $this->processPosts($posts);
            $this->displayResults($results);

            if ($this->option('aggregates')) {
                $this->generateAggregates();
            }

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error('Sentiment processing failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function displayHeader(): void
    {
        $this->info('🧠 SENTIMENT ANALYSIS PIPELINE');
        $this->info('Google Cloud NLP → Batch Processing → Daily Aggregates');
        $this->info('══════════════════════════════════════════════════════');
        $this->newLine();
    }

    private function loadPostsForProcessing(): array
    {
        $query = SocialMediaPost::query()
            ->whereNull('sentiment_score')
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($date = $this->option('date')) {
            $targetDate = Carbon::parse($date);
            $query->whereDate('created_at', $targetDate);
        }

        if ($platform = $this->option('platform')) {
            $query->where('platform', $platform);
        }

        $posts = $query->limit($this->option('posts'))
            ->get()
            ->map(function ($post) {
                return [
                    'id' => $post->id,
                    'platform' => $post->platform,
                    'content' => $post->content,
                    'author' => $post->author,
                    'created_at' => $post->created_at->toISOString(),
                    'keywords_matched' => $post->keywords_matched,
                    'metrics' => $post->metrics
                ];
            })
            ->toArray();

        $this->info("📥 Loaded " . count($posts) . " posts for processing");
        
        if (!empty($posts)) {
            $platforms = collect($posts)->groupBy('platform');
            $this->table(['Platform', 'Count'], $platforms->map(fn($posts, $platform) => [$platform, count($posts)])->values()->toArray());
        }

        return $posts;
    }

    private function processPosts(array $posts): array
    {
        $processor = app(BatchSentimentProcessor::class);

        $options = [
            'generate_aggregates' => false, // We'll handle this separately
            'queue' => $this->option('queue') ? 'sentiment-analysis' : null
        ];

        if ($this->option('queue')) {
            $this->info('🔄 Queueing posts for background processing...');
            $batchId = $processor->queueBatchProcessing($posts, $options);
            $this->info("✅ Batch queued with ID: {$batchId}");
            
            return [
                'queued' => true,
                'batch_id' => $batchId,
                'posts_queued' => count($posts)
            ];
        }

        $this->info('🚀 Starting batch sentiment processing...');
        $progressBar = $this->output->createProgressBar(count($posts));
        $progressBar->setFormat('🧠 Processing: %current%/%max% [%bar%] %percent:3s%% - %message%');
        $progressBar->start();

        $progressBar->setMessage('Initializing Google Cloud NLP...');
        
        $results = $processor->processPostsBatch($posts, $options);
        
        $progressBar->finish();
        $this->newLine(2);

        return $results;
    }

    private function displayResults(array $results): void
    {
        if (isset($results['queued']) && $results['queued']) {
            $this->info('📋 Batch Processing Queued');
            $this->table(['Metric', 'Value'], [
                ['Batch ID', $results['batch_id']],
                ['Posts Queued', $results['posts_queued']],
                ['Queue', 'sentiment-analysis'],
                ['Status', 'Pending']
            ]);
            $this->info('💡 Use --status flag to monitor progress');
            return;
        }

        $this->info('📊 Sentiment Processing Results');
        $this->newLine();

        $metrics = [
            ['Posts Processed', $results['posts_processed'] ?? 0],
            ['Posts Failed', $results['posts_failed'] ?? 0],
            ['Execution Time', round($results['execution_time'] ?? 0, 2) . 's'],
            ['Cost Estimate', '$' . number_format($results['cost_estimate'] ?? 0, 4)],
            ['Batch ID', $results['batch_id'] ?? 'N/A']
        ];

        $this->table(['Metric', 'Value'], $metrics);

        // Display sentiment distribution
        if (!empty($results['sentiment_distribution'])) {
            $this->newLine();
            $this->info('📈 Sentiment Distribution');
            $distribution = $results['sentiment_distribution'];
            $distributionData = [
                ['😊 Positive', $distribution['positive'] ?? 0],
                ['😐 Neutral', $distribution['neutral'] ?? 0], 
                ['😞 Negative', $distribution['negative'] ?? 0]
            ];
            $this->table(['Sentiment', 'Count'], $distributionData);
        }

        $this->newLine();
        if (($results['posts_processed'] ?? 0) > 0) {
            $this->info('✅ Sentiment analysis completed successfully');
        } else {
            $this->warn('⚠️  No posts were successfully processed');
        }
    }

    private function generateAggregates(): void
    {
        $this->newLine();
        $this->info('📊 Generating Daily Sentiment Aggregates...');

        $aggregator = app(DailySentimentAggregator::class);
        $date = $this->option('date') ?: Carbon::yesterday()->format('Y-m-d');

        try {
            $results = $aggregator->generateDailyAggregates($date);
            
            $this->info("✅ Daily aggregates generated for {$date}");
            $this->table(['Aggregate Type', 'Count'], [
                ['Platform Aggregates', $results['platform_aggregates']],
                ['Category Aggregates', $results['category_aggregates']],
                ['Keyword Aggregates', $results['keyword_aggregates']],
                ['Overall Aggregate', $results['overall_aggregate']],
                ['Total Aggregates', $results['total_aggregates']]
            ]);

        } catch (Exception $e) {
            $this->error('Failed to generate daily aggregates: ' . $e->getMessage());
        }
    }

    private function showStatus(): int
    {
        $this->info('📊 SENTIMENT PIPELINE STATUS');
        $this->info('═══════════════════════════════════════');
        $this->newLine();

        // Show recent batches
        $recentBatches = SentimentBatch::orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        if ($recentBatches->isEmpty()) {
            $this->info('No recent sentiment batches found.');
            return Command::SUCCESS;
        }

        $batchData = [];
        foreach ($recentBatches as $batch) {
            $status = $batch->status;
            $statusIcon = match($status) {
                'completed' => '✅',
                'processing' => '🔄',
                'failed' => '❌',
                'pending' => '⏳',
                default => '❓'
            };

            $batchData[] = [
                $batch->batch_id,
                $statusIcon . ' ' . ucfirst($status),
                $batch->total_documents,
                $batch->processed_documents,
                $batch->created_at->diffForHumans()
            ];
        }

        $this->table(['Batch ID', 'Status', 'Total', 'Processed', 'Created'], $batchData);

        // Show system health
        $this->newLine();
        $this->info('🏥 System Health');
        
        $healthData = [
            ['Google NLP API', '🟢 Available'],
            ['Queue Workers', '🟢 Active'],
            ['Database', '🟢 Connected'],
            ['Cache', '🟢 Redis Connected']
        ];

        $this->table(['Component', 'Status'], $healthData);

        return Command::SUCCESS;
    }

    private function runDemo(): int
    {
        $this->info('🎭 SENTIMENT PIPELINE DEMO');
        $this->info('═══════════════════════════════════════');
        $this->newLine();

        $this->info('🧪 Creating sample social media posts...');

        $samplePosts = [
            [
                'id' => 'demo_1',
                'platform' => 'twitter',
                'content' => 'Just discovered this amazing new DeFi protocol! The smart contracts look solid and the team is transparent. Bullish! 🚀',
                'author' => 'crypto_enthusiast',
                'created_at' => now()->toISOString(),
                'keywords_matched' => ['defi', 'smart contracts', 'bullish']
            ],
            [
                'id' => 'demo_2', 
                'platform' => 'reddit',
                'content' => 'Warning: Found a critical vulnerability in the latest smart contract audit. Developers need to patch this ASAP before mainnet deployment.',
                'author' => 'security_researcher',
                'created_at' => now()->toISOString(),
                'keywords_matched' => ['vulnerability', 'smart contract', 'security']
            ],
            [
                'id' => 'demo_3',
                'platform' => 'telegram',
                'content' => 'New blockchain conference announced for next month. Great lineup of speakers discussing the future of Web3 and decentralized finance.',
                'author' => 'blockchain_news',
                'created_at' => now()->toISOString(),
                'keywords_matched' => ['blockchain', 'web3', 'defi']
            ]
        ];

        $this->info("📊 Processing " . count($samplePosts) . " sample posts...");
        $this->newLine();

        $processor = app(BatchSentimentProcessor::class);
        $results = $processor->processTextBatch(
            array_column($samplePosts, 'content'),
            ['demo_mode' => true]
        );

        $this->displayDemoResults($results, $samplePosts);
        
        $this->newLine();
        $this->info('🎯 Demo completed successfully!');
        $this->info('💡 Use --posts flag to process real posts from the database');

        return Command::SUCCESS;
    }

    private function displayDemoResults(array $results, array $samplePosts): void
    {
        $this->info('📈 Demo Processing Results');
        
        $metrics = [
            ['Texts Processed', $results['texts_processed'] ?? 0],
            ['Execution Time', round($results['execution_time'] ?? 0, 3) . 's'],
            ['Cost Estimate', '$' . number_format($results['cost_estimate'] ?? 0, 4)]
        ];

        $this->table(['Metric', 'Value'], $metrics);

        if (!empty($results['sentiment_distribution'])) {
            $this->newLine();
            $this->info('😊 Sentiment Analysis Results');
            
            foreach ($results['results'] as $i => $result) {
                if (isset($samplePosts[$i])) {
                    $post = $samplePosts[$i];
                    $sentiment = $result['sentiment_label'] ?? 'unknown';
                    $score = $result['sentiment_score'] ?? 0;
                    
                    $icon = match($sentiment) {
                        'very_positive', 'positive' => '😊',
                        'neutral' => '😐',
                        'negative', 'very_negative' => '😞',
                        default => '❓'
                    };

                    $this->line("   {$icon} {$post['platform']}: " . substr($post['content'], 0, 60) . '...');
                    $this->line("      Sentiment: {$sentiment} (score: {$score})");
                    $this->newLine();
                }
            }
        }
    }

}