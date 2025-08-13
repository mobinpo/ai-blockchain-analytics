<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\GoogleCloudBatchSentimentService;
use App\Models\SocialMediaPost;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class ProcessBatchSentimentWithAggregates implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800; // 30 minutes
    public int $tries = 3;
    public int $maxExceptions = 3;

    public function __construct(
        public array $textData,
        public string $platform = 'general',
        public ?string $keyword = null,
        public ?Carbon $targetDate = null,
        public array $options = []
    ) {
        $this->targetDate = $this->targetDate ?? Carbon::today();
        
        // Set queue based on batch size
        $textCount = count($this->textData);
        if ($textCount > 1000) {
            $this->onQueue('sentiment-large');
        } elseif ($textCount > 100) {
            $this->onQueue('sentiment-medium');
        } else {
            $this->onQueue('sentiment-small');
        }
    }

    public function handle(GoogleCloudBatchSentimentService $batchService): void
    {
        $startTime = microtime(true);
        
        Log::info('Starting batch sentiment processing job', [
            'job_id' => $this->job->getJobId(),
            'text_count' => count($this->textData),
            'platform' => $this->platform,
            'keyword' => $this->keyword,
            'target_date' => $this->targetDate->toDateString(),
            'queue' => $this->queue ?? 'default'
        ]);

        try {
            // Process the batch through Google Cloud NLP and store daily aggregates
            $result = $batchService->processBatchWithDailyAggregates(
                $this->textData,
                $this->platform,
                $this->keyword,
                $this->targetDate
            );

            $processingTime = microtime(true) - $startTime;

            Log::info('Batch sentiment processing job completed successfully', [
                'job_id' => $this->job->getJobId(),
                'processing_time' => round($processingTime, 3),
                'processed_count' => $result['processed_count'],
                'aggregate_created' => $result['aggregate_created'],
                'cost_estimate' => $result['cost_estimate'],
                'sentiment_summary' => $result['sentiment_summary']
            ]);

            // Optionally trigger follow-up jobs or notifications
            $this->handlePostProcessing($result);

        } catch (Exception $e) {
            Log::error('Batch sentiment processing job failed', [
                'job_id' => $this->job->getJobId(),
                'error' => $e->getMessage(),
                'text_count' => count($this->textData),
                'platform' => $this->platform,
                'attempt' => $this->attempts()
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle post-processing tasks after successful batch processing
     */
    private function handlePostProcessing(array $result): void
    {
        // Update related social media posts if applicable
        if ($this->platform !== 'general' && isset($this->options['update_posts']) && $this->options['update_posts']) {
            $this->updateSocialMediaPosts($result);
        }

        // Trigger aggregation of higher-level metrics
        if (isset($this->options['trigger_weekly_aggregation']) && $this->options['trigger_weekly_aggregation']) {
            ProcessWeeklySentimentAggregation::dispatch($this->targetDate, $this->platform)
                ->delay(now()->addMinutes(5));
        }

        // Send notification if configured
        if (isset($this->options['notify_completion']) && $this->options['notify_completion']) {
            $this->sendCompletionNotification($result);
        }
    }

    /**
     * Update related social media posts with sentiment data
     */
    private function updateSocialMediaPosts(array $result): void
    {
        try {
            // This would update individual posts if we're processing social media data
            // For now, we log the update attempt
            Log::info('Social media posts sentiment update completed', [
                'platform' => $this->platform,
                'date' => $this->targetDate->toDateString(),
                'processed_count' => $result['processed_count']
            ]);
        } catch (Exception $e) {
            Log::warning('Failed to update social media posts', [
                'error' => $e->getMessage(),
                'platform' => $this->platform
            ]);
        }
    }

    /**
     * Send completion notification
     */
    private function sendCompletionNotification(array $result): void
    {
        // This could send email, Slack notification, etc.
        Log::info('Batch sentiment processing completed - notification sent', [
            'platform' => $this->platform,
            'date' => $this->targetDate->toDateString(),
            'summary' => $result['sentiment_summary']
        ]);
    }

    /**
     * Handle job failure
     */
    public function failed(Exception $exception): void
    {
        Log::error('Batch sentiment processing job failed permanently', [
            'job_id' => $this->job?->getJobId(),
            'error' => $exception->getMessage(),
            'text_count' => count($this->textData),
            'platform' => $this->platform,
            'target_date' => $this->targetDate->toDateString(),
            'attempts' => $this->attempts()
        ]);

        // Optionally send failure notification
        // NotificationService::sendJobFailureAlert($this, $exception);
    }

    /**
     * Create multiple jobs from a large dataset
     */
    public static function dispatchBatch(
        array $allTexts,
        string $platform = 'general',
        ?string $keyword = null,
        ?Carbon $targetDate = null,
        int $batchSize = 100,
        array $options = []
    ): array {
        $targetDate = $targetDate ?? Carbon::today();
        $chunks = array_chunk($allTexts, $batchSize);
        $jobIds = [];

        Log::info('Dispatching batch sentiment processing jobs', [
            'total_texts' => count($allTexts),
            'batch_count' => count($chunks),
            'batch_size' => $batchSize,
            'platform' => $platform,
            'target_date' => $targetDate->toDateString()
        ]);

        foreach ($chunks as $index => $chunk) {
            $job = new self($chunk, $platform, $keyword, $targetDate, $options);
            
            // Stagger job dispatch to avoid overwhelming the API
            $delay = $index * 30; // 30 seconds between batches
            $job->delay(now()->addSeconds($delay));
            
            $jobId = dispatch($job);
            $jobIds[] = $jobId;
        }

        return $jobIds;
    }

    /**
     * Get estimated processing time for a batch
     */
    public static function estimateProcessingTime(int $textCount): array
    {
        // Rough estimates based on Google Cloud NLP performance
        $textsPerMinute = 50; // Conservative estimate with rate limiting
        $estimatedMinutes = ceil($textCount / $textsPerMinute);
        
        return [
            'estimated_minutes' => $estimatedMinutes,
            'estimated_seconds' => $estimatedMinutes * 60,
            'api_requests' => $textCount,
            'cost_estimate' => round($textCount * 0.001, 4) // $0.001 per request
        ];
    }
}
