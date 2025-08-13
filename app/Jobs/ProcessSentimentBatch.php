<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SentimentBatch;
use App\Services\SentimentPipeline\BatchSentimentProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class ProcessSentimentBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $maxExceptions = 3;
    public int $timeout = 1800; // 30 minutes

    public function __construct(
        private int $batchId,
        private array $posts,
        private array $options = []
    ) {
        // Set queue based on priority
        $this->onQueue($options['queue'] ?? 'sentiment-analysis');
    }

    public function handle(BatchSentimentProcessor $processor): void
    {
        $batch = SentimentBatch::find($this->batchId);
        
        if (!$batch) {
            Log::error('Sentiment batch not found', ['batch_id' => $this->batchId]);
            return;
        }

        Log::info('Starting queued sentiment batch processing', [
            'batch_id' => $batch->batch_id,
            'posts_count' => count($this->posts),
            'attempt' => $this->attempts()
        ]);

        try {
            // Update batch status to processing
            $batch->update([
                'status' => 'processing',
                'started_at' => now()
            ]);

            // Process the batch
            $result = $processor->processPostsBatch($this->posts, $this->options);
            
            Log::info('Queued sentiment batch processing completed', [
                'batch_id' => $batch->batch_id,
                'result' => $result
            ]);

        } catch (Exception $e) {
            Log::error('Queued sentiment batch processing failed', [
                'batch_id' => $batch->batch_id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts()
            ]);

            // Update batch status to failed
            $batch->update([
                'status' => 'failed',
                'completed_at' => now(),
                'results' => [
                    'error' => $e->getMessage(),
                    'failed_at_attempt' => $this->attempts()
                ]
            ]);

            throw $e;
        }
    }

    public function failed(Exception $exception): void
    {
        $batch = SentimentBatch::find($this->batchId);
        
        if ($batch) {
            $batch->update([
                'status' => 'failed',
                'completed_at' => now(),
                'results' => [
                    'error' => $exception->getMessage(),
                    'failed_permanently' => true,
                    'total_attempts' => $this->tries
                ]
            ]);
        }

        Log::error('Sentiment batch job failed permanently', [
            'batch_id' => $this->batchId,
            'error' => $exception->getMessage(),
            'total_attempts' => $this->tries
        ]);
    }

    public function retryUntil(): \DateTime
    {
        return now()->addHours(2); // Retry for up to 2 hours
    }

    public function backoff(): array
    {
        return [60, 300, 900]; // 1 minute, 5 minutes, 15 minutes
    }
}