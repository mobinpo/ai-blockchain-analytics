<?php

declare(strict_types=1);

namespace App\Console\Commands\SentimentPipeline;

use App\Services\SentimentPipeline\TextAggregator;
use App\Services\SentimentPipeline\TextPreprocessor;
use Carbon\Carbon;
use Illuminate\Console\Command;

final class CreateSentimentBatch extends Command
{
    protected $signature = 'sentiment:create-batch 
                           {date? : Date to create batch for (YYYY-MM-DD format, defaults to yesterday)}
                           {--dry-run : Show what would be created without actually creating}';

    protected $description = 'Create sentiment analysis batch for a specific date';

    public function handle(TextAggregator $aggregator): int
    {
        $dateString = $this->argument('date') ?? now()->subDay()->toDateString();
        
        try {
            $date = Carbon::createFromFormat('Y-m-d', $dateString);
        } catch (\Exception $e) {
            $this->error("Invalid date format. Please use YYYY-MM-DD format.");
            return 1;
        }

        $this->info("Creating sentiment batch for date: {$date->toDateString()}");

        if ($this->option('dry-run')) {
            return $this->performDryRun($date, $aggregator);
        }

        try {
            $batch = $aggregator->createDailyBatch($date);

            $this->info("✅ Sentiment batch created successfully!");
            $this->displayBatchInfo($batch);

            $this->info("Use 'php artisan sentiment:process-batch {$batch->id}' to process this batch.");

            return 0;

        } catch (\Exception $e) {
            $this->error("Failed to create sentiment batch: {$e->getMessage()}");
            return 1;
        }
    }

    protected function performDryRun(Carbon $date, TextAggregator $aggregator): int
    {
        $this->warn("🔍 DRY RUN MODE - No batch will be created");
        
        try {
            // Check if batch already exists
            $batchId = $this->generateBatchId($date);
            $existing = \App\Models\SentimentBatch::where('batch_id', $batchId)->first();
            
            if ($existing) {
                $this->warn("Batch already exists for this date:");
                $this->displayBatchInfo($existing);
                return 0;
            }

            // Count potential documents
            $socialMediaCount = \App\Models\SocialMediaPost::whereDate('published_at', $date)
                ->whereNull('sentiment_score')
                ->count();

            $this->info("Would create batch with:");
            $this->table(['Metric', 'Count'], [
                ['Social Media Posts', $socialMediaCount],
                ['Estimated Processing Cost', '$' . number_format($socialMediaCount * 0.001, 2)],
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error("Dry run failed: {$e->getMessage()}");
            return 1;
        }
    }

    protected function displayBatchInfo($batch): void
    {
        $this->table(['Property', 'Value'], [
            ['Batch ID', $batch->batch_id],
            ['Processing Date', $batch->processing_date->toDateString()],
            ['Status', $batch->status],
            ['Total Documents', $batch->total_documents],
            ['Processed Documents', $batch->processed_documents],
            ['Failed Documents', $batch->failed_documents],
            ['Created At', $batch->created_at->format('Y-m-d H:i:s')],
        ]);
    }

    protected function generateBatchId(Carbon $date): string
    {
        return 'sentiment_' . $date->format('Y_m_d') . '_' . substr(md5($date->timestamp), 0, 8);
    }
}