<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\OpenAiJobResult;
use App\Services\OpenAiStreamService;
use App\Services\EnhancedOpenAiJobManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

class OpenAiJobMonitor extends Command
{
    private EnhancedOpenAiJobManager $jobManager;
    protected $signature = 'openai:monitor 
                           {--live : Enable live monitoring mode}
                           {--stats : Show comprehensive statistics}
                           {--retry-failed : Automatically retry failed jobs}
                           {--max-retries=3 : Maximum retry attempts per job}
                           {--alert-threshold=80 : Alert threshold for failure rate percentage}
                           {--cleanup : Perform cleanup of old records}
                           {--queue= : Monitor specific queue}
                           {--user= : Monitor specific user jobs}
                           {--status= : Filter by status (processing,completed,failed)}
                           {--hours=24 : Hours to look back for statistics}
                           {--refresh=5 : Refresh interval in seconds for live mode}';

    protected $description = 'Enhanced OpenAI job worker monitoring with automatic retry and alerts';

    public function __construct(EnhancedOpenAiJobManager $jobManager)
    {
        parent::__construct();
        $this->jobManager = $jobManager;
    }

    public function handle(): int
    {
        $this->displayHeader();

        // Handle cleanup option
        if ($this->option('cleanup')) {
            return $this->performCleanup();
        }

        // Handle retry-failed option
        if ($this->option('retry-failed')) {
            $this->retryFailedJobs();
        }

        // Check for alerts
        $this->checkAlerts();

        if ($this->option('live')) {
            return $this->startLiveMonitoring();
        }

        if ($this->option('stats')) {
            return $this->showComprehensiveStats();
        }

        return $this->showQuickStats();
    }

    private function displayHeader(): void
    {
        $this->info('📊 OpenAI Job Worker Monitor');
        $this->newLine();
    }

    private function startLiveMonitoring(): int
    {
        $refreshInterval = (int) $this->option('refresh');
        
        $this->info("🔄 Starting live monitoring (refresh every {$refreshInterval}s)");
        $this->info('Press Ctrl+C to stop');
        $this->newLine();

        $iteration = 0;
        while (true) {
            $iteration++;
            
            // Clear screen after first iteration
            if ($iteration > 1) {
                $this->output->write("\033[2J\033[H"); // Clear screen
            }

            $this->displayLiveStats($iteration);
            
            sleep($refreshInterval);
        }

        return Command::SUCCESS;
    }

    private function showComprehensiveStats(): int
    {
        $hours = (int) $this->option('hours');
        $since = now()->subHours($hours);
        
        $this->info("📈 Comprehensive Statistics (last {$hours} hours)");
        $this->newLine();

        // Overall statistics
        $this->displayOverallStats($since);
        $this->newLine();

        // Performance metrics
        $this->displayPerformanceMetrics($since);
        $this->newLine();

        // Queue distribution
        $this->displayQueueStats($since);
        $this->newLine();

        // Error analysis
        $this->displayErrorStats($since);
        $this->newLine();

        // Cost analysis
        $this->displayCostStats($since);

        return Command::SUCCESS;
    }

    private function showQuickStats(): int
    {
        $since = now()->subHours(24);
        
        $this->info('📊 Quick Statistics (last 24 hours)');
        $this->newLine();

        $stats = $this->getQuickStats($since);
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Jobs', number_format($stats['total_jobs'])],
                ['Completed', number_format($stats['completed']) . ' (' . round($stats['completion_rate'], 1) . '%)'],
                ['Failed', number_format($stats['failed']) . ' (' . round($stats['failure_rate'], 1) . '%)'],
                ['Processing', number_format($stats['processing'])],
                ['Average Duration', $stats['avg_duration'] . 's'],
                ['Total Tokens', number_format($stats['total_tokens'])],
                ['Est. Total Cost', '$' . number_format($stats['total_cost'], 4)],
                ['Tokens/Second', number_format($stats['avg_tokens_per_second'], 1)],
            ]
        );

        // Recent activity
        $this->newLine();
        $this->info('🕐 Recent Activity:');
        $this->displayRecentJobs();

        return Command::SUCCESS;
    }

    private function displayLiveStats(int $iteration): void
    {
        $timestamp = now()->format('Y-m-d H:i:s');
        $this->info("🔄 Live Monitor (Update #{$iteration}) - {$timestamp}");
        $this->newLine();

        // Real-time job counts
        $processing = $this->getJobCountByStatus('processing');
        $queued = $this->getQueuedJobsCount();
        $recentCompleted = $this->getRecentCompletedCount(5); // Last 5 minutes

        $this->table(
            ['Status', 'Count'],
            [
                ['🔄 Processing', $processing],
                ['⏳ Queued', $queued],
                ['✅ Completed (5min)', $recentCompleted],
                ['❌ Failed (5min)', $this->getRecentFailedCount(5)],
            ]
        );

        // Active streaming jobs
        $this->newLine();
        $this->displayActiveStreamingJobs();

        // System performance
        $this->newLine();
        $this->displaySystemPerformance();
    }

    private function displayOverallStats(Carbon $since): void
    {
        $stats = OpenAiJobResult::where('created_at', '>=', $since)
            ->selectRaw('
                COUNT(*) as total_jobs,
                COUNT(CASE WHEN status = "completed" THEN 1 END) as completed,
                COUNT(CASE WHEN status = "failed" THEN 1 END) as failed,
                COUNT(CASE WHEN status = "processing" THEN 1 END) as processing,
                AVG(processing_time_ms) as avg_processing_time,
                MAX(processing_time_ms) as max_processing_time,
                MIN(processing_time_ms) as min_processing_time
            ')
            ->first();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Jobs', number_format($stats->total_jobs)],
                ['Completed', number_format($stats->completed) . ' (' . round(($stats->completed / max($stats->total_jobs, 1)) * 100, 1) . '%)'],
                ['Failed', number_format($stats->failed) . ' (' . round(($stats->failed / max($stats->total_jobs, 1)) * 100, 1) . '%)'],
                ['Processing', number_format($stats->processing)],
                ['Avg Duration', round($stats->avg_processing_time / 1000, 2) . 's'],
                ['Max Duration', round($stats->max_processing_time / 1000, 2) . 's'],
                ['Min Duration', round($stats->min_processing_time / 1000, 2) . 's'],
            ]
        );
    }

    private function displayPerformanceMetrics(Carbon $since): void
    {
        $this->info('⚡ Performance Metrics:');
        
        $tokenStats = OpenAiJobResult::where('created_at', '>=', $since)
            ->where('status', 'completed')
            ->whereNotNull('token_usage')
            ->get()
            ->map(function ($job) {
                $usage = $job->token_usage;
                return [
                    'total_tokens' => $usage['total_tokens'] ?? 0,
                    'tokens_per_second' => $usage['tokens_per_second'] ?? 0,
                    'cost' => $usage['estimated_cost_usd'] ?? 0,
                ];
            });

        $totalTokens = $tokenStats->sum('total_tokens');
        $avgTokensPerSecond = $tokenStats->avg('tokens_per_second');
        $totalCost = $tokenStats->sum('cost');

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Tokens Processed', number_format($totalTokens)],
                ['Average Tokens/Second', number_format($avgTokensPerSecond, 1)],
                ['Total Estimated Cost', '$' . number_format($totalCost, 4)],
                ['Average Cost per Job', '$' . number_format($totalCost / max($tokenStats->count(), 1), 4)],
            ]
        );
    }

    private function displayQueueStats(Carbon $since): void
    {
        $this->info('📋 Queue Distribution:');
        
        $queueStats = OpenAiJobResult::where('created_at', '>=', $since)
            ->join('failed_jobs', 'open_ai_job_results.job_id', '=', DB::raw("JSON_UNQUOTE(JSON_EXTRACT(failed_jobs.payload, '$.data.jobId'))"), 'left')
            ->selectRaw('
                job_type,
                COUNT(*) as total,
                COUNT(CASE WHEN open_ai_job_results.status = "completed" THEN 1 END) as completed,
                COUNT(CASE WHEN open_ai_job_results.status = "failed" THEN 1 END) as failed,
                AVG(processing_time_ms) as avg_time
            ')
            ->groupBy('job_type')
            ->get();

        $tableData = $queueStats->map(function ($stat) {
            $successRate = round(($stat->completed / max($stat->total, 1)) * 100, 1);
            return [
                $stat->job_type,
                $stat->total,
                $stat->completed,
                $stat->failed,
                $successRate . '%',
                round($stat->avg_time / 1000, 1) . 's'
            ];
        })->toArray();

        $this->table(
            ['Job Type', 'Total', 'Completed', 'Failed', 'Success Rate', 'Avg Time'],
            $tableData
        );
    }

    private function displayErrorStats(Carbon $since): void
    {
        $this->info('❌ Error Analysis:');
        
        $errors = OpenAiJobResult::where('created_at', '>=', $since)
            ->where('status', 'failed')
            ->whereNotNull('error_message')
            ->selectRaw('
                LEFT(error_message, 100) as error_preview,
                COUNT(*) as occurrence_count
            ')
            ->groupBy(DB::raw('LEFT(error_message, 100)'))
            ->orderByDesc('occurrence_count')
            ->limit(5)
            ->get();

        if ($errors->isEmpty()) {
            $this->info('No errors found! 🎉');
            return;
        }

        $tableData = $errors->map(function ($error) {
            return [
                substr($error->error_preview, 0, 80) . '...',
                $error->occurrence_count
            ];
        })->toArray();

        $this->table(
            ['Error (first 80 chars)', 'Count'],
            $tableData
        );
    }

    private function displayCostStats(Carbon $since): void
    {
        $this->info('💰 Cost Analysis:');
        
        $modelCosts = OpenAiJobResult::where('created_at', '>=', $since)
            ->where('status', 'completed')
            ->whereNotNull('token_usage')
            ->selectRaw('
                JSON_UNQUOTE(JSON_EXTRACT(config, "$.model")) as model,
                COUNT(*) as job_count,
                SUM(JSON_UNQUOTE(JSON_EXTRACT(token_usage, "$.total_tokens"))) as total_tokens,
                SUM(JSON_UNQUOTE(JSON_EXTRACT(token_usage, "$.estimated_cost_usd"))) as total_cost
            ')
            ->groupBy(DB::raw('JSON_UNQUOTE(JSON_EXTRACT(config, "$.model"))'))
            ->get();

        $tableData = $modelCosts->map(function ($cost) {
            return [
                $cost->model ?: 'unknown',
                number_format($cost->job_count),
                number_format($cost->total_tokens),
                '$' . number_format($cost->total_cost, 4),
                '$' . number_format($cost->total_cost / max($cost->job_count, 1), 4)
            ];
        })->toArray();

        $this->table(
            ['Model', 'Jobs', 'Total Tokens', 'Total Cost', 'Cost/Job'],
            $tableData
        );
    }

    private function displayActiveStreamingJobs(): void
    {
        $this->info('🌊 Active Streaming Jobs:');
        
        $activeJobs = OpenAiJobResult::where('status', 'processing')
            ->orderBy('started_at', 'desc')
            ->limit(5)
            ->get();

        if ($activeJobs->isEmpty()) {
            $this->line('No active streaming jobs');
            return;
        }

        $tableData = [];
        foreach ($activeJobs as $job) {
            $streamStatus = Cache::get("openai_stream_{$job->job_id}");
            $tokensReceived = $streamStatus['tokens_received'] ?? 0;
            $duration = $job->started_at ? $job->started_at->diffInSeconds(now()) : 0;
            
            $tableData[] = [
                substr($job->job_id, 0, 12) . '...',
                $job->job_type,
                $tokensReceived,
                $duration . 's',
                $streamStatus['status'] ?? 'unknown'
            ];
        }

        $this->table(
            ['Job ID', 'Type', 'Tokens', 'Duration', 'Stream Status'],
            $tableData
        );
    }

    private function displaySystemPerformance(): void
    {
        $this->info('⚙️ System Performance:');
        
        // Memory usage
        $memoryUsage = round(memory_get_usage(true) / 1024 / 1024, 2);
        $memoryPeak = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        
        // Queue sizes (approximate)
        $queueSizes = $this->getQueueSizes();
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Memory Usage', $memoryUsage . ' MB'],
                ['Peak Memory', $memoryPeak . ' MB'],
                ['OpenAI Queue Size', $queueSizes['openai'] ?? 'N/A'],
                ['Streaming Queue Size', $queueSizes['streaming'] ?? 'N/A'],
                ['Cache Items', $this->getCacheItemCount()],
            ]
        );
    }

    private function displayRecentJobs(): void
    {
        $recentJobs = OpenAiJobResult::orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['job_id', 'job_type', 'status', 'created_at', 'processing_time_ms']);

        $tableData = $recentJobs->map(function ($job) {
            return [
                substr($job->job_id, 0, 12) . '...',
                $job->job_type,
                $job->status,
                $job->created_at->diffForHumans(),
                $job->processing_time_ms ? round($job->processing_time_ms / 1000, 1) . 's' : 'N/A'
            ];
        })->toArray();

        $this->table(
            ['Job ID', 'Type', 'Status', 'Created', 'Duration'],
            $tableData
        );
    }

    // Helper methods
    private function getQuickStats(Carbon $since): array
    {
        $jobs = OpenAiJobResult::where('created_at', '>=', $since)->get();
        
        $totalJobs = $jobs->count();
        $completed = $jobs->where('status', 'completed')->count();
        $failed = $jobs->where('status', 'failed')->count();
        $processing = $jobs->where('status', 'processing')->count();
        
        $completedJobs = $jobs->where('status', 'completed');
        $avgDuration = $completedJobs->avg('processing_time_ms') / 1000;
        
        $totalTokens = $completedJobs->sum(function ($job) {
            return $job->token_usage['total_tokens'] ?? 0;
        });
        
        $totalCost = $completedJobs->sum(function ($job) {
            return $job->token_usage['estimated_cost_usd'] ?? 0;
        });
        
        $avgTokensPerSecond = $completedJobs->avg(function ($job) {
            return $job->token_usage['tokens_per_second'] ?? 0;
        });

        return [
            'total_jobs' => $totalJobs,
            'completed' => $completed,
            'failed' => $failed,
            'processing' => $processing,
            'completion_rate' => $totalJobs > 0 ? ($completed / $totalJobs) * 100 : 0,
            'failure_rate' => $totalJobs > 0 ? ($failed / $totalJobs) * 100 : 0,
            'avg_duration' => round($avgDuration, 1),
            'total_tokens' => $totalTokens,
            'total_cost' => $totalCost,
            'avg_tokens_per_second' => $avgTokensPerSecond,
        ];
    }

    private function getJobCountByStatus(string $status): int
    {
        return OpenAiJobResult::where('status', $status)->count();
    }

    private function getQueuedJobsCount(): int
    {
        // This would need to be implemented based on your queue driver
        // For Redis, you could check queue lengths
        return 0; // Placeholder
    }

    private function getRecentCompletedCount(int $minutes): int
    {
        return OpenAiJobResult::where('status', 'completed')
            ->where('completed_at', '>=', now()->subMinutes($minutes))
            ->count();
    }

    private function getRecentFailedCount(int $minutes): int
    {
        return OpenAiJobResult::where('status', 'failed')
            ->where('failed_at', '>=', now()->subMinutes($minutes))
            ->count();
    }

    private function getQueueSizes(): array
    {
        // Placeholder - implement based on your queue driver
        return [
            'openai' => 0,
            'streaming' => 0,
        ];
    }

    private function getCacheItemCount(): string
    {
        // Get approximate count of streaming cache items
        $pattern = "openai_stream_*";
        
        try {
            // This is Redis-specific
            $redis = Cache::getRedis();
            $keys = $redis->keys($pattern);
            return count($keys);
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    /**
     * Retry failed jobs with comprehensive retry logic
     */
    private function retryFailedJobs(): void
    {
        $maxRetries = (int) $this->option('max-retries');
        $this->info("🔄 Retrying failed jobs (max {$maxRetries} attempts per job)...");

        // Get failed jobs that haven't exceeded retry limit
        $failedJobs = OpenAiJobResult::where('status', 'failed')
            ->where('attempts_made', '<', $maxRetries)
            ->where('failed_at', '>=', now()->subHours(24))
            ->orderBy('failed_at', 'desc')
            ->take(20) // Limit to prevent overwhelming the system
            ->get();

        if ($failedJobs->isEmpty()) {
            $this->info('✅ No failed jobs found that can be retried.');
            return;
        }

        $this->info("Found {$failedJobs->count()} jobs to retry:");

        $retryResults = [];
        $retryBar = $this->output->createProgressBar($failedJobs->count());

        foreach ($failedJobs as $failedJob) {
            try {
                $newJobId = $this->jobManager->retryJob($failedJob->job_id, [
                    'monitor_retry' => true,
                    'retry_timestamp' => now()->toISOString()
                ]);

                if ($newJobId) {
                    $retryResults[] = [
                        'Original Job' => substr($failedJob->job_id, 0, 12) . '...',
                        'New Job' => substr($newJobId, 0, 12) . '...',
                        'Type' => $failedJob->job_type,
                        'Status' => '✅ Retried'
                    ];
                } else {
                    $retryResults[] = [
                        'Original Job' => substr($failedJob->job_id, 0, 12) . '...',
                        'New Job' => 'N/A',
                        'Type' => $failedJob->job_type,
                        'Status' => '❌ Could not retry'
                    ];
                }

                $retryBar->advance();
                usleep(100000); // 0.1s delay between retries

            } catch (\Exception $e) {
                $retryResults[] = [
                    'Original Job' => substr($failedJob->job_id, 0, 12) . '...',
                    'New Job' => 'Error',
                    'Type' => $failedJob->job_type,
                    'Status' => "❌ {$e->getMessage()}"
                ];
                
                Log::error('Failed to retry OpenAI job', [
                    'job_id' => $failedJob->job_id,
                    'error' => $e->getMessage()
                ]);

                $retryBar->advance();
            }
        }

        $retryBar->finish();
        $this->newLine(2);

        if (!empty($retryResults)) {
            $this->table(['Original Job', 'New Job', 'Type', 'Status'], $retryResults);
        }

        $successfulRetries = collect($retryResults)->where('Status', '✅ Retried')->count();
        $this->info("✅ Successfully retried {$successfulRetries} out of {$failedJobs->count()} failed jobs.");
    }

    /**
     * Check for system alerts and notify if needed
     */
    private function checkAlerts(): void
    {
        $threshold = (float) $this->option('alert-threshold');
        $hours = (int) $this->option('hours');
        $since = now()->subHours($hours);

        // Calculate failure rate
        $stats = $this->getQuickStats($since);
        $failureRate = $stats['failure_rate'];

        // Check for high failure rate
        if ($failureRate > $threshold) {
            $this->error("🚨 HIGH FAILURE RATE ALERT!");
            $this->error("Current failure rate: {$failureRate}% (threshold: {$threshold}%)");
            $this->error("Failed jobs: {$stats['failed']} out of {$stats['total_jobs']} total jobs");
            
            Log::alert('OpenAI Job Monitor: High failure rate detected', [
                'failure_rate' => $failureRate,
                'threshold' => $threshold,
                'failed_jobs' => $stats['failed'],
                'total_jobs' => $stats['total_jobs'],
                'time_period_hours' => $hours
            ]);

            if ($this->option('retry-failed')) {
                $this->warn("Auto-retry is enabled. Failed jobs will be retried.");
            }
        }

        // Check for stuck jobs (processing for more than 2 hours)
        $stuckJobs = OpenAiJobResult::where('status', 'processing')
            ->where('started_at', '<', now()->subHours(2))
            ->count();

        if ($stuckJobs > 0) {
            $this->warn("⏰ WARNING: {$stuckJobs} jobs may be stuck (processing for more than 2 hours)");
            
            Log::warning('OpenAI Job Monitor: Stuck jobs detected', [
                'stuck_jobs_count' => $stuckJobs
            ]);
        }

        // Check Redis connection
        try {
            Redis::ping();
        } catch (\Exception $e) {
            $this->error("🔧 REDIS CONNECTION ALERT: {$e->getMessage()}");
            
            Log::error('OpenAI Job Monitor: Redis connection failed', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Perform cleanup of old records
     */
    private function performCleanup(): int
    {
        $this->info('🧹 Starting cleanup of old OpenAI job records...');

        try {
            $results = $this->jobManager->cleanup(30); // Clean records older than 30 days

            $this->info('✅ Cleanup completed successfully:');
            $this->table(['Cleanup Type', 'Items Cleaned'], [
                ['Database Records', $results['database_cleanup']['records_deleted'] ?? 0],
                ['Cache Entries', $results['cache_cleanup']['cache_entries_cleaned'] ?? 0],
                ['Redis Keys', $results['redis_cleanup']['redis_keys_cleaned'] ?? 0]
            ]);

            Log::info('OpenAI Job Monitor: Cleanup completed', $results);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Cleanup failed: {$e->getMessage()}");
            
            Log::error('OpenAI Job Monitor: Cleanup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Command::FAILURE;
        }
    }
}