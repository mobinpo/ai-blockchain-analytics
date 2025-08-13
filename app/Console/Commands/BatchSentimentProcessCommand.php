<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\GoogleCloudBatchSentimentService;
use App\Jobs\ProcessBatchSentimentWithAggregates;
use App\Models\SocialMediaPost;
use App\Models\DailySentimentAggregate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class BatchSentimentProcessCommand extends Command
{
    protected $signature = 'sentiment:batch-process
                           {--source= : Data source (file|database|stdin)}
                           {--file= : Path to text file (one text per line)}
                           {--platform=general : Platform name for categorization}
                           {--keyword= : Keyword for filtering/categorization}
                           {--date= : Target date (YYYY-MM-DD, defaults to today)}
                           {--batch-size=100 : Number of texts per batch}
                           {--queue : Process via job queue instead of synchronously}
                           {--dry-run : Show what would be processed without actual processing}
                           {--from-social-posts : Process unanalyzed social media posts}
                           {--limit= : Limit number of texts to process}';

    protected $description = 'Process batch sentiment analysis through Google Cloud NLP and store daily aggregates';

    public function handle(): int
    {
        $this->info('🚀 Starting Batch Sentiment Processing Pipeline');
        $this->newLine();

        try {
            // Parse and validate options
            $config = $this->parseOptions();
            
            if ($this->option('dry-run')) {
                return $this->handleDryRun($config);
            }

            // Load text data based on source
            $texts = $this->loadTextData($config);
            
            if (empty($texts)) {
                $this->error('❌ No texts found to process');
                return 1;
            }

            $this->info("📊 Loaded {count($texts)} texts for processing");
            
            // Process based on queue option
            if ($this->option('queue')) {
                return $this->handleQueueProcessing($texts, $config);
            } else {
                return $this->handleSynchronousProcessing($texts, $config);
            }

        } catch (Exception $e) {
            $this->error("❌ Processing failed: {$e->getMessage()}");
            Log::error('Batch sentiment command failed', [
                'error' => $e->getMessage(),
                'options' => $this->options()
            ]);
            return 1;
        }
    }

    /**
     * Parse and validate command options
     */
    private function parseOptions(): array
    {
        $config = [
            'source' => $this->option('source') ?: 'stdin',
            'file' => $this->option('file'),
            'platform' => $this->option('platform') ?: 'general',
            'keyword' => $this->option('keyword'),
            'date' => $this->option('date') ? Carbon::createFromFormat('Y-m-d', $this->option('date')) : Carbon::today(),
            'batch_size' => (int) ($this->option('batch-size') ?: 100),
            'from_social_posts' => $this->option('from-social-posts'),
            'limit' => $this->option('limit') ? (int) $this->option('limit') : null
        ];

        // Validation
        if ($config['source'] === 'file' && !$config['file']) {
            throw new Exception('File path is required when using file source');
        }

        if ($config['source'] === 'file' && !file_exists($config['file'])) {
            throw new Exception("File not found: {$config['file']}");
        }

        if ($config['batch_size'] < 1 || $config['batch_size'] > 1000) {
            throw new Exception('Batch size must be between 1 and 1000');
        }

        return $config;
    }

    /**
     * Load text data based on configuration
     */
    private function loadTextData(array $config): array
    {
        switch ($config['source']) {
            case 'file':
                return $this->loadFromFile($config['file'], $config['limit']);
                
            case 'database':
            case 'social-posts':
                return $this->loadFromSocialPosts($config);
                
            case 'stdin':
                return $this->loadFromStdin($config['limit']);
                
            default:
                throw new Exception("Unknown source: {$config['source']}");
        }
    }

    /**
     * Load texts from file
     */
    private function loadFromFile(string $filePath, ?int $limit): array
    {
        $this->info("📁 Loading texts from file: {$filePath}");
        
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if ($limit) {
            $lines = array_slice($lines, 0, $limit);
        }

        return array_filter($lines, fn($line) => strlen(trim($line)) > 0);
    }

    /**
     * Load texts from social media posts
     */
    private function loadFromSocialPosts(array $config): array
    {
        $this->info('📱 Loading texts from social media posts database');
        
        $query = SocialMediaPost::whereNull('sentiment_score')
            ->whereDate('platform_created_at', $config['date']);

        if ($config['platform'] !== 'general') {
            $query->where('platform', $config['platform']);
        }

        if ($config['keyword']) {
            $query->where('content', 'ILIKE', "%{$config['keyword']}%");
        }

        if ($config['limit']) {
            $query->limit($config['limit']);
        }

        $posts = $query->get();
        
        $this->info("📊 Found {$posts->count()} unprocessed social media posts");
        
        return $posts->pluck('content')->toArray();
    }

    /**
     * Load texts from stdin
     */
    private function loadFromStdin(?int $limit): array
    {
        $this->info('⌨️  Reading texts from stdin (enter empty line to finish)');
        $texts = [];
        $count = 0;

        while (true) {
            if ($limit && $count >= $limit) {
                break;
            }

            $line = trim(fgets(STDIN));
            
            if (empty($line)) {
                break;
            }

            $texts[] = $line;
            $count++;
            
            $this->line("   📝 Added text #{$count}");
        }

        return $texts;
    }

    /**
     * Handle dry run mode
     */
    private function handleDryRun(array $config): int
    {
        $this->info('🔍 DRY RUN MODE - No actual processing will occur');
        $this->newLine();

        // Load data to show what would be processed
        $texts = $this->loadTextData($config);
        
        $estimate = ProcessBatchSentimentWithAggregates::estimateProcessingTime(count($texts));
        
        $this->line('📋 <fg=cyan>PROCESSING PLAN</>');
        $this->line("   Source: {$config['source']}");
        $this->line("   Platform: {$config['platform']}");
        $this->line("   Keyword: " . ($config['keyword'] ?: 'none'));
        $this->line("   Target Date: {$config['date']->toDateString()}");
        $this->line("   Total Texts: " . count($texts));
        $this->line("   Batch Size: {$config['batch_size']}");
        $this->line("   Estimated Processing Time: {$estimate['estimated_minutes']} minutes");
        $this->line("   Estimated Cost: \${$estimate['cost_estimate']}");
        $this->newLine();

        // Show sample texts
        if (!empty($texts)) {
            $this->line('📄 <fg=cyan>SAMPLE TEXTS (first 3)</>');
            foreach (array_slice($texts, 0, 3) as $index => $text) {
                $preview = strlen($text) > 100 ? substr($text, 0, 100) . '...' : $text;
                $this->line("   " . ($index + 1) . ". {$preview}");
            }
        }

        return 0;
    }

    /**
     * Handle queue-based processing
     */
    private function handleQueueProcessing(array $texts, array $config): int
    {
        $this->info('📤 Dispatching batch sentiment processing jobs to queue');
        
        $options = [
            'update_posts' => $config['from_social_posts'],
            'trigger_weekly_aggregation' => true,
            'notify_completion' => true
        ];

        $jobIds = ProcessBatchSentimentWithAggregates::dispatchBatch(
            $texts,
            $config['platform'],
            $config['keyword'],
            $config['date'],
            $config['batch_size'],
            $options
        );

        $this->info("✅ Dispatched " . count($jobIds) . " batch processing jobs");
        $this->line("   Monitor progress with: php artisan horizon:status");
        $this->line("   Check results in daily_sentiment_aggregates table");

        return 0;
    }

    /**
     * Handle synchronous processing
     */
    private function handleSynchronousProcessing(array $texts, array $config): int
    {
        $this->info('⚡ Processing batch sentiment synchronously');
        $startTime = now();

        $batchService = app(GoogleCloudBatchSentimentService::class);
        
        // Process in chunks if necessary
        $chunks = array_chunk($texts, $config['batch_size']);
        $totalResults = [];

        foreach ($chunks as $chunkIndex => $chunk) {
            $this->line("   Processing chunk " . ($chunkIndex + 1) . "/" . count($chunks) . " ({count($chunk)} texts)");
            
            $result = $batchService->processBatchWithDailyAggregates(
                $chunk,
                $config['platform'],
                $config['keyword'],
                $config['date']
            );

            $totalResults[] = $result;
            
            $this->line("     ✅ Processed {$result['processed_count']} texts in {$result['processing_time']}s");
        }

        // Display final results
        $this->displayResults($totalResults, $config, $startTime);

        return 0;
    }

    /**
     * Display processing results
     */
    private function displayResults(array $results, array $config, Carbon $startTime): void
    {
        $totalProcessed = array_sum(array_column($results, 'processed_count'));
        $totalCost = array_sum(array_column($results, 'cost_estimate'));
        $duration = $startTime->diffInSeconds(now());

        $this->newLine();
        $this->info("✅ Batch sentiment processing completed in {$duration} seconds");
        $this->newLine();

        $this->line('📊 <fg=cyan>PROCESSING SUMMARY</>');
        $this->line("   Date: {$config['date']->toDateString()}");
        $this->line("   Platform: {$config['platform']}");
        $this->line("   Keyword: " . ($config['keyword'] ?: 'none'));
        $this->line("   Total texts processed: {$totalProcessed}");
        $this->line("   Processing batches: " . count($results));
        $this->line("   Total cost estimate: \${$totalCost}");
        $this->newLine();

        // Show aggregate information
        $aggregate = DailySentimentAggregate::where('date', $config['date']->toDateString())
            ->where('platform', $config['platform'])
            ->where('keyword', $config['keyword'])
            ->first();

        if ($aggregate) {
            $this->line('📈 <fg=cyan>DAILY AGGREGATE CREATED</>');
            $this->line("   Average Sentiment: " . number_format($aggregate->avg_sentiment_score, 4));
            $this->line("   Positive: {$aggregate->positive_count} ({$aggregate->positive_percentage}%)");
            $this->line("   Neutral: {$aggregate->neutral_count} ({$aggregate->neutral_percentage}%)");
            $this->line("   Negative: {$aggregate->negative_count} ({$aggregate->negative_percentage}%)");
        }

        $this->newLine();
        $this->info('🎉 Processing complete! Check the daily_sentiment_aggregates table for detailed results.');
    }
}
