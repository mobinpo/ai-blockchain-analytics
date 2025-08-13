<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SentimentBatch;
use App\Services\SentimentPipeline\SentimentBatchProcessor;
use App\Services\SentimentPipeline\DailySentimentAggregateService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class SentimentPipelineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1 hour timeout
    public int $maxExceptions = 3;
    public int $tries = 3;

    protected int $batchId;
    protected bool $generateAggregates;

    public function __construct(int $batchId, bool $generateAggregates = false)
    {
        $this->batchId = $batchId;
        $this->generateAggregates = $generateAggregates;
        
        // Use high priority queue for sentiment processing
        $this->onQueue('sentiment-processing');
    }

    public function handle(
        SentimentBatchProcessor $batchProcessor,
        DailySentimentAggregateService $aggregateService
    ): void {
        $startTime = microtime(true);
        
        Log::info('Starting sentiment pipeline job', [
            'batch_id' => $this->batchId,
            'generate_aggregates' => $this->generateAggregates,
            'job_id' => $this->job->getJobId(),
        ]);

        try {
            // Find the batch
            $batch = SentimentBatch::findOrFail($this->batchId);
            
            // Validate batch is ready for processing
            if (!$this->validateBatchForProcessing($batch)) {
                Log::warning('Batch not ready for processing', [
                    'batch_id' => $this->batchId,
                    'status' => $batch->status,
                    'total_documents' => $batch->total_documents,
                ]);
                return;
            }

            // Process the sentiment batch
            $results = $batchProcessor->processBatch($batch);
            
            // Generate daily aggregates if requested and processing was successful
            if ($this->generateAggregates && $results['processed'] > 0) {
                $this->generateDailyAggregates($batch, $aggregateService);
            }

            $totalTime = microtime(true) - $startTime;

            Log::info('Sentiment pipeline job completed successfully', [
                'batch_id' => $this->batchId,
                'processed_documents' => $results['processed'],
                'failed_documents' => $results['failed'],
                'success_rate' => $results['success_rate'],
                'total_cost' => $results['total_cost'],
                'processing_time' => round($totalTime, 2),
            ]);

        } catch (\Exception $e) {
            $this->handleJobFailure($e);
            throw $e;
        }
    }

    protected function validateBatchForProcessing(SentimentBatch $batch): bool
    {
        // Check if batch has documents to process
        if ($batch->total_documents === 0) {
            return false;
        }

        // Check if batch is in valid status
        if (!in_array($batch->status, ['pending', 'processing'])) {
            return false;
        }

        // Check if there are pending documents
        $pendingCount = $batch->documents()->where('processing_status', 'pending')->count();
        if ($pendingCount === 0) {
            return false;
        }

        return true;
    }

    protected function generateDailyAggregates(
        SentimentBatch $batch,
        DailySentimentAggregateService $aggregateService
    ): void {
        try {
            Log::info('Generating daily aggregates for batch', [
                'batch_id' => $batch->batch_id,
                'processing_date' => $batch->processing_date->toDateString(),
            ]);

            $aggregates = $aggregateService->generateDailyAggregates($batch->processing_date);

            Log::info('Daily aggregates generated successfully', [
                'batch_id' => $batch->batch_id,
                'aggregates_count' => count($aggregates),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate daily aggregates', [
                'batch_id' => $batch->batch_id,
                'error' => $e->getMessage(),
            ]);

            // Don't fail the main job if aggregation fails
            // Aggregates can be regenerated later
        }
    }

    protected function handleJobFailure(\Exception $e): void
    {
        Log::error('Sentiment pipeline job failed', [
            'batch_id' => $this->batchId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'attempts' => $this->attempts(),
        ]);

        // Update batch status to failed if this is the final attempt
        if ($this->attempts() >= $this->tries) {
            try {
                $batch = SentimentBatch::find($this->batchId);
                if ($batch) {
                    $batch->markAsFailed([
                        'job_error' => $e->getMessage(),
                        'job_attempts' => $this->attempts(),
                        'failed_at' => now()->toISOString(),
                    ]);
                }
            } catch (\Exception $updateException) {
                Log::error('Failed to update batch status after job failure', [
                    'batch_id' => $this->batchId,
                    'error' => $updateException->getMessage(),
                ]);
            }
        }
    }

    public function failed(\Exception $exception): void
    {
        Log::critical('Sentiment pipeline job permanently failed', [
            'batch_id' => $this->batchId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }

    public function retryUntil(): \DateTime
    {
        // Allow retries for up to 6 hours
        return now()->addHours(6);
    }

    public function backoff(): array
    {
        // Exponential backoff: 1 minute, 5 minutes, 15 minutes
        return [60, 300, 900];
    }

    public function tags(): array
    {
        return [
            'sentiment-pipeline',
            'batch:' . $this->batchId,
            'aggregates:' . ($this->generateAggregates ? 'yes' : 'no'),
        ];
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return 'sentiment-batch-' . $this->batchId;
    }

    /**
     * Determine if the job should be unique.
     */
    public function shouldBeUnique(): bool
    {
        return true;
    }

    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public function uniqueFor(): int
    {
        return 3600; // 1 hour
    }
}

/**
 * Job for processing multiple sentiment batches in sequence
 */
final class SentimentPipelineBulkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 7200; // 2 hours timeout
    public int $tries = 2;

    protected array $batchIds;
    protected bool $generateAggregates;

    public function __construct(array $batchIds, bool $generateAggregates = false)
    {
        $this->batchIds = $batchIds;
        $this->generateAggregates = $generateAggregates;
        
        $this->onQueue('sentiment-bulk-processing');
    }

    public function handle(
        SentimentBatchProcessor $batchProcessor,
        DailySentimentAggregateService $aggregateService
    ): void {
        Log::info('Starting bulk sentiment pipeline job', [
            'batch_count' => count($this->batchIds),
            'generate_aggregates' => $this->generateAggregates,
        ]);

        $processed = 0;
        $failed = 0;
        $datesProcessed = [];

        foreach ($this->batchIds as $batchId) {
            try {
                $batch = SentimentBatch::findOrFail($batchId);
                
                Log::info('Processing batch in bulk job', [
                    'batch_id' => $batchId,
                    'batch_date' => $batch->processing_date->toDateString(),
                ]);

                $results = $batchProcessor->processBatch($batch);
                
                if ($results['processed'] > 0) {
                    $processed++;
                    $datesProcessed[] = $batch->processing_date;
                } else {
                    $failed++;
                }

            } catch (\Exception $e) {
                Log::error('Failed to process batch in bulk job', [
                    'batch_id' => $batchId,
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        // Generate aggregates for all processed dates
        if ($this->generateAggregates && !empty($datesProcessed)) {
            $this->generateBulkAggregates(array_unique($datesProcessed), $aggregateService);
        }

        Log::info('Bulk sentiment pipeline job completed', [
            'processed_batches' => $processed,
            'failed_batches' => $failed,
            'total_batches' => count($this->batchIds),
            'dates_processed' => count(array_unique($datesProcessed)),
        ]);
    }

    protected function generateBulkAggregates(array $dates, DailySentimentAggregateService $aggregateService): void
    {
        foreach ($dates as $date) {
            try {
                $aggregateService->generateDailyAggregates($date);
            } catch (\Exception $e) {
                Log::error('Failed to generate aggregates for date in bulk job', [
                    'date' => $date->toDateString(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function tags(): array
    {
        return [
            'sentiment-pipeline',
            'bulk-processing',
            'batches:' . count($this->batchIds),
        ];
    }
}