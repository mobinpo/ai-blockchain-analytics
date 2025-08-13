<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\SentimentPipeline\GoogleCloudBatchProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job for processing text through the complete NLP pipeline
 * Text → Google Cloud NLP (batch sentiment) → Daily aggregates
 */
class ProcessTextThroughNLPPipeline implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes
    public int $tries = 3;
    public int $backoff = 60; // 1 minute backoff between retries

    public function __construct(
        private readonly array $texts,
        private readonly array $metadata = [],
        private readonly bool $generateAggregates = true
    ) {}

    public function handle(GoogleCloudBatchProcessor $processor): void
    {
        Log::info('Starting NLP pipeline job', [
            'text_count' => count($this->texts),
            'metadata' => $this->metadata,
            'generate_aggregates' => $this->generateAggregates
        ]);

        try {
            $result = $processor->processTextToDailyAggregates(
                $this->texts,
                $this->metadata,
                $this->generateAggregates
            );

            Log::info('NLP pipeline job completed successfully', [
                'batch_id' => $result['batch_id'],
                'processed_count' => $result['processed_count'],
                'execution_time_ms' => $result['execution_time_ms']
            ]);

        } catch (\Exception $e) {
            Log::error('NLP pipeline job failed', [
                'error' => $e->getMessage(),
                'text_count' => count($this->texts),
                'metadata' => $this->metadata
            ]);

            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('NLP pipeline job permanently failed', [
            'error' => $exception->getMessage(),
            'text_count' => count($this->texts),
            'metadata' => $this->metadata,
            'attempts' => $this->attempts()
        ]);
    }
}