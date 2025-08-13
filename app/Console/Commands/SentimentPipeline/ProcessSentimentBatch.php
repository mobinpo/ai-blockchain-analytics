<?php

declare(strict_types=1);

namespace App\Console\Commands\SentimentPipeline;

use App\Models\SentimentBatch;
use App\Services\SentimentPipeline\SentimentBatchProcessor;
use App\Jobs\SentimentPipelineJob;
use Illuminate\Console\Command;

final class ProcessSentimentBatch extends Command
{
    protected $signature = 'sentiment:process-batch 
                           {batch-id? : ID of the batch to process}
                           {--all : Process all pending batches}
                           {--queue : Process using queue instead of synchronously}
                           {--aggregates : Generate daily aggregates after processing}';

    protected $description = 'Process sentiment analysis batch(es)';

    public function handle(SentimentBatchProcessor $processor): int
    {
        if ($this->option('all')) {
            return $this->processAllPendingBatches($processor);
        }

        $batchId = $this->argument('batch-id');
        if (!$batchId) {
            $this->error('Please provide a batch ID or use --all flag');
            return 1;
        }

        return $this->processBatch((int)$batchId, $processor);
    }

    protected function processBatch(int $batchId, SentimentBatchProcessor $processor): int
    {
        try {
            $batch = SentimentBatch::findOrFail($batchId);
            
            $this->info("Processing sentiment batch: {$batch->batch_id}");
            $this->displayBatchInfo($batch);

            if ($this->option('queue')) {
                return $this->queueBatchProcessing($batch);
            }

            return $this->processBatchSynchronously($batch, $processor);

        } catch (\Exception $e) {
            $this->error("Failed to process batch: {$e->getMessage()}");
            return 1;
        }
    }

    protected function processAllPendingBatches(SentimentBatchProcessor $processor): int
    {
        $pendingBatches = SentimentBatch::pending()
            ->where('total_documents', '>', 0)
            ->orderBy('processing_date')
            ->get();

        if ($pendingBatches->isEmpty()) {
            $this->info("No pending batches found.");
            return 0;
        }

        $this->info("Found {$pendingBatches->count()} pending batches to process");

        if ($this->option('queue')) {
            return $this->queueAllBatches($pendingBatches);
        }

        return $this->processAllBatchesSynchronously($pendingBatches, $processor);
    }

    protected function processBatchSynchronously(SentimentBatch $batch, SentimentBatchProcessor $processor): int
    {
        $progressBar = $this->output->createProgressBar($batch->total_documents);
        $progressBar->setFormat('verbose');
        $progressBar->start();

        try {
            // Update progress periodically
            $this->updateProgressPeriodically($batch, $progressBar);

            $results = $processor->processBatch($batch);

            $progressBar->finish();
            $this->newLine(2);

            $this->displayProcessingResults($results);

            return 0;

        } catch (\Exception $e) {
            $progressBar->finish();
            $this->newLine();
            $this->error("Processing failed: {$e->getMessage()}");
            return 1;
        }
    }

    protected function processAllBatchesSynchronously($batches, SentimentBatchProcessor $processor): int
    {
        $totalBatches = $batches->count();
        $processed = 0;
        $failed = 0;

        foreach ($batches as $index => $batch) {
            $this->info("Processing batch " . ($index + 1) . "/{$totalBatches}: {$batch->batch_id}");
            
            try {
                $results = $processor->processBatch($batch);
                $processed++;
                
                $this->info("✅ Batch processed: {$results['processed']} documents, {$results['failed']} failed");
                
            } catch (\Exception $e) {
                $failed++;
                $this->error("❌ Failed to process batch {$batch->batch_id}: {$e->getMessage()}");
            }
        }

        $this->info("\n📊 Summary:");
        $this->info("Processed: {$processed} batches");
        $this->info("Failed: {$failed} batches");

        return $failed > 0 ? 1 : 0;
    }

    protected function queueBatchProcessing(SentimentBatch $batch): int
    {
        try {
            SentimentPipelineJob::dispatch($batch->id, $this->option('aggregates'));
            
            $this->info("✅ Batch queued for processing");
            $this->info("Monitor progress with: php artisan horizon:status");
            
            return 0;

        } catch (\Exception $e) {
            $this->error("Failed to queue batch: {$e->getMessage()}");
            return 1;
        }
    }

    protected function queueAllBatches($batches): int
    {
        $queued = 0;
        $failed = 0;

        foreach ($batches as $batch) {
            try {
                SentimentPipelineJob::dispatch($batch->id, $this->option('aggregates'));
                $queued++;
                
            } catch (\Exception $e) {
                $failed++;
                $this->error("Failed to queue batch {$batch->batch_id}: {$e->getMessage()}");
            }
        }

        $this->info("📊 Queuing Summary:");
        $this->info("Queued: {$queued} batches");
        $this->info("Failed: {$failed} batches");

        return $failed > 0 ? 1 : 0;
    }

    protected function updateProgressPeriodically(SentimentBatch $batch, $progressBar): void
    {
        // This is a simplified version - in practice you'd use a background process
        // to update progress or check the database periodically
        register_shutdown_function(function() use ($batch, $progressBar) {
            $batch->refresh();
            $progressBar->setProgress($batch->processed_documents);
        });
    }

    protected function displayBatchInfo(SentimentBatch $batch): void
    {
        $this->table(['Property', 'Value'], [
            ['Batch ID', $batch->batch_id],
            ['Processing Date', $batch->processing_date->toDateString()],
            ['Status', $batch->status],
            ['Total Documents', $batch->total_documents],
            ['Processed Documents', $batch->processed_documents],
            ['Failed Documents', $batch->failed_documents],
            ['Progress', $batch->progress_percentage . '%'],
        ]);
    }

    protected function displayProcessingResults(array $results): void
    {
        $this->info("✅ Processing completed!");
        
        $this->table(['Metric', 'Value'], [
            ['Processed Documents', $results['processed']],
            ['Failed Documents', $results['failed']],
            ['Success Rate', $results['success_rate'] . '%'],
            ['Total Cost', '$' . number_format($results['total_cost'], 4)],
            ['Processing Time', number_format($results['processing_time'], 2) . ' seconds'],
            ['Cost per Document', '$' . number_format($results['cost_per_document'], 4)],
        ]);
    }
}