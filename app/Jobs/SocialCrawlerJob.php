<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\CrawlerMicroService\CrawlerOrchestrator;
use App\Models\CrawlerJobStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

final class SocialCrawlerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800; // 30 minutes
    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        private array $jobConfig
    ) {
        // Set queue based on priority
        $priority = $jobConfig['priority'] ?? 'normal';
        $queueMap = config('crawler_microservice.jobs.priority_queues');
        $this->onQueue($queueMap[$priority] ?? $queueMap['normal']);
    }

    public function handle(CrawlerOrchestrator $orchestrator): void
    {
        $jobId = $this->jobConfig['job_id'];
        
        Log::info("Starting social crawler job: {$jobId}", [
            'job_config' => $this->jobConfig,
            'attempt' => $this->attempts()
        ]);

        try {
            // Execute the crawling job
            $results = $orchestrator->executeCrawlJob($this->jobConfig);
            
            Log::info("Social crawler job completed: {$jobId}", [
                'total_posts' => $results['total_posts'],
                'total_matches' => $results['total_matches'],
                'status' => $results['status'] ?? 'completed'
            ]);

        } catch (\Exception $e) {
            Log::error("Social crawler job failed: {$jobId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'attempt' => $this->attempts()
            ]);

            // Update job status to failed
            CrawlerJobStatus::where('job_id', $jobId)->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now()
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $jobId = $this->jobConfig['job_id'];
        
        Log::error("Social crawler job permanently failed: {$jobId}", [
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Update job status to permanently failed
        CrawlerJobStatus::where('job_id', $jobId)->update([
            'status' => 'permanently_failed',
            'error_message' => $exception->getMessage(),
            'completed_at' => now()
        ]);
    }

    public function retryUntil(): Carbon
    {
        return now()->addHours(2);
    }
}