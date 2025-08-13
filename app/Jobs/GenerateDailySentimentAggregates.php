<?php

namespace App\Jobs;

use App\Services\SentimentPipeline\DailySentimentAggregateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class GenerateDailySentimentAggregates implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1 hour timeout
    public int $tries = 3;
    public int $backoff = 300; // 5 minutes between retries

    private ?Carbon $date;
    private array $options;

    /**
     * Create a new job instance.
     */
    public function __construct(?Carbon $date = null, array $options = [])
    {
        $this->date = $date;
        $this->options = $options;
        
        // Use sentiment processing queue
        $this->onQueue(config('sentiment_pipeline.queue.default_queue', 'sentiment-processing'));
    }

    /**
     * Execute the job.
     */
    public function handle(DailySentimentAggregateService $aggregateService): void
    {
        $startTime = microtime(true);
        $date = $this->date ?? Carbon::yesterday();
        
        try {
            Log::info('Starting daily sentiment aggregation job', [
                'job_id' => $this->job->getJobId(),
                'date' => $date->toDateString(),
                'options' => $this->options
            ]);

            // Generate daily aggregates
            $aggregates = $aggregateService->generateDailyAggregates($date);
            
            $executionTime = microtime(true) - $startTime;
            
            Log::info('Daily sentiment aggregation job completed successfully', [
                'job_id' => $this->job->getJobId(),
                'date' => $date->toDateString(),
                'aggregates_created' => count($aggregates),
                'execution_time_seconds' => round($executionTime, 2)
            ]);

        } catch (Exception $e) {
            $executionTime = microtime(true) - $startTime;
            
            Log::error('Daily sentiment aggregation job failed', [
                'job_id' => $this->job->getJobId(),
                'date' => $date->toDateString(),
                'error' => $e->getMessage(),
                'execution_time_seconds' => round($executionTime, 2),
                'attempt' => $this->attempts()
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle job failure
     */
    public function failed(Exception $exception): void
    {
        $date = $this->date ?? Carbon::yesterday();
        
        Log::error('Daily sentiment aggregation job failed permanently', [
            'job_id' => $this->job?->getJobId(),
            'date' => $date->toDateString(),
            'error' => $exception->getMessage(),
            'attempts_made' => $this->attempts(),
            'options' => $this->options
        ]);

        // Here you could send notifications, update monitoring systems, etc.
    }
}