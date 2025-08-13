<?php

namespace App\Console\Commands;

use App\Services\SentimentPipelineProcessor;
use App\Jobs\ProcessDailySentiment;
use App\Models\SocialMediaPost;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ProcessDailySentimentCommand extends Command
{
    protected $signature = 'sentiment:process-daily 
                           {date? : Date to process (YYYY-MM-DD format, defaults to yesterday)}
                           {--queue : Queue the job instead of running synchronously}
                           {--platform= : Process specific platform only (twitter, reddit, telegram)}
                           {--force : Force reprocessing even if already processed}
                           {--dry-run : Show what would be processed without actually processing}';

    protected $description = 'Process daily sentiment analysis using Google Cloud NLP for social media posts';

    protected SentimentPipelineProcessor $processor;

    public function __construct(SentimentPipelineProcessor $processor)
    {
        parent::__construct();
        $this->processor = $processor;
    }

    public function handle()
    {
        $dateInput = $this->argument('date');
        $date = $dateInput ? Carbon::createFromFormat('Y-m-d', $dateInput) : Carbon::yesterday();
        
        $this->info("🔄 Processing sentiment analysis for date: {$date->toDateString()}");

        if ($this->option('dry-run')) {
            return $this->handleDryRun($date);
        }

        if ($this->option('queue')) {
            return $this->handleQueue($date);
        }

        return $this->handleSynchronous($date);
    }

    protected function handleDryRun(Carbon $date)
    {
        $this->info("🔍 DRY RUN - Analyzing posts for {$date->toDateString()}");

        $platforms = $this->option('platform') ? [$this->option('platform')] : ['twitter', 'reddit', 'telegram'];
        $totalPosts = 0;
        $unprocessedPosts = 0;

        foreach ($platforms as $platform) {
            $posts = SocialMediaPost::where('platform', $platform)
                ->whereDate('platform_created_at', $date)
                ->get();

            $unprocessed = $posts->whereNull('sentiment_score');

            $this->line("  📊 {$platform}: {$posts->count()} total posts, {$unprocessed->count()} unprocessed");
            
            $totalPosts += $posts->count();
            $unprocessedPosts += $unprocessed->count();
        }

        $this->info("📈 SUMMARY:");
        $this->line("  Total posts: {$totalPosts}");
        $this->line("  Unprocessed posts: {$unprocessedPosts}");
        $this->line("  Estimated processing time: " . ceil($unprocessedPosts / 50) . " minutes");
        $this->line("  Estimated Google Cloud API calls: {$unprocessedPosts}");

        return 0;
    }

    protected function handleQueue(Carbon $date)
    {
        $this->info("📤 Queueing sentiment processing job for {$date->toDateString()}");

        ProcessDailySentiment::dispatch($date);

        $this->info("✅ Job queued successfully");
        $this->line("   You can monitor the job progress with: docker compose exec app php artisan horizon:status");
        
        return 0;
    }

    protected function handleSynchronous(Carbon $date)
    {
        $this->info("⚡ Running synchronous sentiment processing...");
        $startTime = now();

        try {
            $results = $this->processor->processDailySentiment($date);

            $this->displayResults($results, $startTime);
            
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Sentiment processing failed: {$e->getMessage()}");
            Log::error('Sentiment processing command failed', [
                'date' => $date->toDateString(),
                'error' => $e->getMessage()
            ]);
            
            return 1;
        }
    }

    protected function displayResults(array $results, \Carbon\Carbon $startTime)
    {
        $duration = $startTime->diffInSeconds(now());
        
        $this->info("✅ Sentiment processing completed in {$duration} seconds");
        $this->newLine();

        // Overall summary
        $this->line("📊 <fg=cyan>PROCESSING SUMMARY</>");
        $this->line("   Date: {$results['date']}");
        $this->line("   Total posts processed: {$results['total_posts_processed']}");
        $this->line("   Total aggregates created: {$results['total_aggregates_created']}");
        $this->newLine();

        // Platform breakdown
        $this->line("🔍 <fg=cyan>PLATFORM BREAKDOWN</>");
        foreach ($results['platforms'] as $platform => $platformData) {
            if (isset($platformData['error'])) {
                $this->line("   <fg=red>{$platform}: ERROR - {$platformData['error']}</>");
            } else {
                $this->line("   <fg=green>{$platform}:</> {$platformData['posts_processed']} posts, {$platformData['aggregates_created']} aggregates");
                
                if (isset($platformData['sentiment_stats'])) {
                    $stats = $platformData['sentiment_stats'];
                    $dist = $stats['distribution'];
                    $this->line("      Sentiment: {$dist['positive']} positive, {$dist['neutral']} neutral, {$dist['negative']} negative");
                    $this->line("      Average sentiment: " . number_format($stats['avg_sentiment'], 3));
                }
            }
        }

        $this->newLine();
        $this->info("🎉 Processing complete! Check the daily_sentiment_aggregates table for results.");
    }
}
