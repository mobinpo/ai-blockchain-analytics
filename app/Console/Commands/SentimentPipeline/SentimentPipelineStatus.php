<?php

declare(strict_types=1);

namespace App\Console\Commands\SentimentPipeline;

use App\Models\SentimentBatch;
use App\Models\SentimentBatchDocument;
use App\Models\DailySentimentAggregate;
use App\Services\SentimentPipeline\SentimentBatchProcessor;
use Illuminate\Console\Command;

final class SentimentPipelineStatus extends Command
{
    protected $signature = 'sentiment:status 
                           {--detailed : Show detailed statistics}
                           {--recent-days=7 : Number of recent days to analyze}
                           {--cleanup : Clean up old data}';

    protected $description = 'Show sentiment pipeline status and statistics';

    public function handle(SentimentBatchProcessor $processor): int
    {
        $this->info("🔍 Sentiment Pipeline Status");
        $this->info("================================");

        $this->displayOverallStats();
        
        if ($this->option('detailed')) {
            $this->displayDetailedStats();
        }

        $this->displayRecentActivity();

        if ($this->option('cleanup')) {
            $this->performCleanup();
        }

        return 0;
    }

    protected function displayOverallStats(): void
    {
        $totalBatches = SentimentBatch::count();
        $pendingBatches = SentimentBatch::pending()->count();
        $processingBatches = SentimentBatch::where('status', 'processing')->count();
        $completedBatches = SentimentBatch::completed()->count();
        $failedBatches = SentimentBatch::failed()->count();

        $totalDocuments = SentimentBatchDocument::count();
        $processedDocuments = SentimentBatchDocument::where('processing_status', 'completed')->count();
        $failedDocuments = SentimentBatchDocument::where('processing_status', 'failed')->count();

        $totalAggregates = DailySentimentAggregate::count();

        $this->info("\n📊 Overall Statistics:");
        $this->table(['Metric', 'Count'], [
            ['Total Batches', $totalBatches],
            ['  └─ Pending', $pendingBatches],
            ['  └─ Processing', $processingBatches],
            ['  └─ Completed', $completedBatches],
            ['  └─ Failed', $failedBatches],
            ['', ''],
            ['Total Documents', $totalDocuments],
            ['  └─ Processed', $processedDocuments],
            ['  └─ Failed', $failedDocuments],
            ['', ''],
            ['Daily Aggregates', $totalAggregates],
        ]);

        // Show success rates
        if ($totalBatches > 0) {
            $batchSuccessRate = round(($completedBatches / $totalBatches) * 100, 2);
            $this->info("Batch Success Rate: {$batchSuccessRate}%");
        }

        if ($totalDocuments > 0) {
            $documentSuccessRate = round(($processedDocuments / $totalDocuments) * 100, 2);
            $this->info("Document Success Rate: {$documentSuccessRate}%");
        }
    }

    protected function displayDetailedStats(): void
    {
        $this->info("\n📈 Detailed Statistics:");
        
        // Processing costs
        $totalCost = SentimentBatch::sum('processing_cost');
        $averageCost = SentimentBatch::avg('processing_cost');
        
        // Processing times
        $averageTime = SentimentBatch::completed()
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->get()
            ->avg('duration');

        $this->table(['Metric', 'Value'], [
            ['Total Processing Cost', '$' . number_format($totalCost, 4)],
            ['Average Batch Cost', '$' . number_format($averageCost, 4)],
            ['Average Processing Time', number_format($averageTime ?? 0, 2) . ' seconds'],
        ]);

        // Language distribution
        $languageDistribution = SentimentBatchDocument::where('processing_status', 'completed')
            ->whereNotNull('detected_language')
            ->selectRaw('detected_language, count(*) as count')
            ->groupBy('detected_language')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        if ($languageDistribution->isNotEmpty()) {
            $this->info("\n🌍 Top Languages:");
            $languageData = $languageDistribution->map(function($item) {
                return [$item->detected_language, $item->count];
            })->toArray();
            
            $this->table(['Language', 'Documents'], $languageData);
        }

        // Sentiment distribution
        $this->displaySentimentDistribution();
    }

    protected function displaySentimentDistribution(): void
    {
        $recentAggregates = DailySentimentAggregate::where('aggregate_date', '>=', now()->subDays(7))
            ->fullDay()
            ->where('platform', 'all')
            ->where('keyword_category', 'all')
            ->get();

        if ($recentAggregates->isEmpty()) {
            return;
        }

        $totalPositive = $recentAggregates->sum('very_positive_count') + $recentAggregates->sum('positive_count');
        $totalNeutral = $recentAggregates->sum('neutral_count');
        $totalNegative = $recentAggregates->sum('negative_count') + $recentAggregates->sum('very_negative_count');
        $totalSentiment = $totalPositive + $totalNeutral + $totalNegative;

        if ($totalSentiment > 0) {
            $this->info("\n😊 Sentiment Distribution (Last 7 Days):");
            $this->table(['Sentiment', 'Count', 'Percentage'], [
                ['Positive', $totalPositive, number_format(($totalPositive / $totalSentiment) * 100, 1) . '%'],
                ['Neutral', $totalNeutral, number_format(($totalNeutral / $totalSentiment) * 100, 1) . '%'],
                ['Negative', $totalNegative, number_format(($totalNegative / $totalSentiment) * 100, 1) . '%'],
            ]);
        }
    }

    protected function displayRecentActivity(): void
    {
        $recentDays = (int) $this->option('recent-days');
        $cutoffDate = now()->subDays($recentDays);

        $recentBatches = SentimentBatch::where('created_at', '>=', $cutoffDate)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        if ($recentBatches->isNotEmpty()) {
            $this->info("\n🕐 Recent Activity (Last {$recentDays} Days):");
            
            $recentData = $recentBatches->map(function($batch) {
                return [
                    substr($batch->batch_id, -12),
                    $batch->processing_date->format('M d'),
                    $batch->status,
                    $batch->total_documents,
                    $batch->processed_documents,
                    $batch->progress_percentage . '%',
                ];
            })->toArray();

            $this->table(
                ['Batch ID', 'Date', 'Status', 'Total', 'Processed', 'Progress'],
                $recentData
            );
        }

        // Show pending batches that need attention
        $pendingBatches = SentimentBatch::pending()
            ->where('total_documents', '>', 0)
            ->orderBy('processing_date')
            ->get();

        if ($pendingBatches->isNotEmpty()) {
            $this->warn("\n⚠️  Pending Batches Requiring Processing:");
            
            $pendingData = $pendingBatches->map(function($batch) {
                return [
                    substr($batch->batch_id, -12),
                    $batch->processing_date->format('M d, Y'),
                    $batch->total_documents,
                ];
            })->toArray();

            $this->table(['Batch ID', 'Date', 'Documents'], $pendingData);
            
            $this->info("Run 'php artisan sentiment:process-batch --all' to process all pending batches.");
        }
    }

    protected function performCleanup(): void
    {
        $this->info("\n🧹 Performing Cleanup:");
        
        if (!$this->confirm("This will clean up old data. Continue?")) {
            return;
        }

        // Clean up old preprocessing cache
        $preprocessorClass = app(\App\Services\SentimentPipeline\TextPreprocessor::class);
        $cacheCleanup = $preprocessorClass->cleanupOldCache();
        $this->info("Cleaned up {$cacheCleanup} old preprocessing cache entries");

        // Clean up old completed batches
        $batchProcessorClass = app(\App\Services\SentimentPipeline\SentimentBatchProcessor::class);
        $batchCleanup = $batchProcessorClass->cleanupCompletedBatches(30);
        $this->info("Cleaned up {$batchCleanup} old completed batches");

        // Clean up old aggregates
        $aggregateServiceClass = app(\App\Services\SentimentPipeline\DailySentimentAggregateService::class);
        $aggregateCleanup = $aggregateServiceClass->cleanupOldAggregates(90);
        $this->info("Cleaned up {$aggregateCleanup} old daily aggregates");

        $this->info("✅ Cleanup completed!");
    }
}