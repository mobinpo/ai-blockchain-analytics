<?php

namespace App\Jobs;

use App\Services\SentimentPipelineProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ProcessDailySentiment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1 hour timeout
    public int $tries = 3;
    public int $backoff = 300; // 5 minutes between retries

    protected Carbon $date;
    
    /**
     * Create a new job instance.
     */
    public function __construct(Carbon $date = null)
    {
        $this->date = $date ?: Carbon::yesterday();
        $this->onQueue('sentiment-processing');
    }

    /**
     * Execute the job.
     */
    public function handle(SentimentPipelineProcessor $processor): void
    {
        Log::info('Starting daily sentiment processing job', [
            'date' => $this->date->toDateString(),
            'job_id' => $this->job?->getJobId(),
            'attempt' => $this->attempts()
        ]);

        try {
            $results = $processor->processDailySentiment($this->date);

            Log::info('Daily sentiment processing job completed successfully', [
                'date' => $this->date->toDateString(),
                'results' => $results,
                'processing_time' => $results['processing_time'] ?? null
            ]);

        } catch (\Exception $e) {
            Log::error('Daily sentiment processing job failed', [
                'date' => $this->date->toDateString(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'attempt' => $this->attempts()
            ]);

            throw $e; // Re-throw to trigger retry logic
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Daily sentiment processing job failed permanently', [
            'date' => $this->date->toDateString(),
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['sentiment-processing', 'daily-aggregate', $this->date->toDateString()];
    }
}
