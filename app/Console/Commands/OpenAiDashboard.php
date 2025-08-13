<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\OpenAiJobResult;
use App\Services\OpenAiJobManager;
use App\Services\EnhancedOpenAiJobManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class OpenAiDashboard extends Command
{
    protected $signature = 'openai:dashboard 
                           {--live : Enable live dashboard mode}
                           {--interactive : Enable interactive dashboard}
                           {--refresh=10 : Refresh interval in seconds for live mode}
                           {--hours=24 : Hours to look back for statistics}';

    protected $description = 'Enhanced OpenAI job worker dashboard with interactive features';

    private OpenAiJobManager $jobManager;
    private EnhancedOpenAiJobManager $enhancedJobManager;

    public function __construct(OpenAiJobManager $jobManager, EnhancedOpenAiJobManager $enhancedJobManager)
    {
        parent::__construct();
        $this->jobManager = $jobManager;
        $this->enhancedJobManager = $enhancedJobManager;
    }

    public function handle(): int
    {
        if ($this->option('interactive')) {
            return $this->startInteractiveDashboard();
        }

        if ($this->option('live')) {
            return $this->startLiveDashboard();
        }

        return $this->showStaticDashboard();
    }

    private function startLiveDashboard(): int
    {
        $refreshInterval = (int) $this->option('refresh');
        
        $this->info("🚀 OpenAI Live Dashboard");
        $this->info("Refresh interval: {$refreshInterval}s");
        $this->info('Press Ctrl+C to exit');
        $this->newLine();

        $iteration = 0;
        while (true) {
            $iteration++;
            
            // Clear screen after first iteration
            if ($iteration > 1) {
                $this->output->write("\033[2J\033[H"); // Clear screen
            }

            $this->displayDashboard($iteration);
            
            sleep($refreshInterval);
        }

        return Command::SUCCESS;
    }

    private function showStaticDashboard(): int
    {
        $this->displayDashboard();
        return Command::SUCCESS;
    }

    private function displayDashboard(int $iteration = 1): void
    {
        $timestamp = now()->format('Y-m-d H:i:s');
        $hours = (int) $this->option('hours');
        $since = now()->subHours($hours);
        
        $this->info("📊 OpenAI Job Worker Dashboard #{$iteration}");
        $this->info("Time: {$timestamp} | Data Range: Last {$hours} hours");
        $this->newLine();

        // System status
        $this->displaySystemStatus();
        $this->newLine();

        // Job statistics
        $this->displayJobStatistics($since);
        $this->newLine();

        // Performance metrics
        $this->displayPerformanceMetrics($since);
        $this->newLine();

        // Recent activity
        $this->displayRecentActivity();
        $this->newLine();

        // Queue status
        $this->displayQueueStatus();
        $this->newLine();

        // Active streaming jobs
        $this->displayActiveJobs();
    }

    private function displaySystemStatus(): void
    {
        $this->info('🔧 System Status:');
        
        // Current processing jobs
        $processingJobs = OpenAiJobResult::where('status', 'processing')->count();
        $queuedJobs = $this->getQueuedJobsEstimate();
        $completedToday = OpenAiJobResult::where('status', 'completed')
            ->where('completed_at', '>=', now()->startOfDay())
            ->count();
        $failedToday = OpenAiJobResult::where('status', 'failed')
            ->where('failed_at', '>=', now()->startOfDay())
            ->count();

        // System health indicators
        $healthScore = $this->calculateHealthScore();
        $healthColor = $this->getHealthColor($healthScore);
        
        $this->table(
            ['Metric', 'Value', 'Status'],
            [
                ['Processing Jobs', $processingJobs, $processingJobs > 0 ? '🔄 Active' : '⭐ Idle'],
                ['Queued Jobs (est)', $queuedJobs, $queuedJobs > 10 ? '⚠️ High' : '✅ Normal'],
                ['Completed Today', $completedToday, $completedToday > 0 ? '✅ Active' : '⭐ Quiet'],
                ['Failed Today', $failedToday, $failedToday > 5 ? '❌ High' : '✅ Low'],
                ['System Health', $healthScore . '%', $healthColor . ' ' . $this->getHealthStatus($healthScore)],
            ]
        );
    }

    private function displayJobStatistics(Carbon $since): void
    {
        $this->info('📈 Job Statistics:');
        
        $stats = $this->jobManager->getJobStatistics($since);
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Jobs', number_format($stats['total_jobs'])],
                ['Completed', number_format($stats['status_breakdown']['completed']) . ' (' . round(($stats['status_breakdown']['completed'] / max($stats['total_jobs'], 1)) * 100, 1) . '%)'],
                ['Failed', number_format($stats['status_breakdown']['failed']) . ' (' . round(($stats['status_breakdown']['failed'] / max($stats['total_jobs'], 1)) * 100, 1) . '%)'],
                ['Processing', number_format($stats['status_breakdown']['processing'])],
                ['Success Rate', $stats['performance_metrics']['success_rate'] . '%'],
            ]
        );

        // Job type breakdown
        if (!empty($stats['job_type_breakdown'])) {
            $this->newLine();
            $this->info('📋 Job Types:');
            
            $typeData = collect($stats['job_type_breakdown'])->map(function ($count, $type) {
                return [$type, number_format($count)];
            })->toArray();
            
            $this->table(['Type', 'Count'], $typeData);
        }
    }

    private function displayPerformanceMetrics(Carbon $since): void
    {
        $this->info('⚡ Performance Metrics:');
        
        $stats = $this->jobManager->getJobStatistics($since);
        $metrics = $stats['performance_metrics'];
        
        $avgProcessingTime = $metrics['avg_processing_time_ms'] ? 
            round($metrics['avg_processing_time_ms'] / 1000, 2) : 0;
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Avg Processing Time', $avgProcessingTime . 's'],
                ['Total Tokens Processed', number_format($metrics['total_tokens'])],
                ['Total Estimated Cost', '$' . number_format($metrics['total_cost'], 4)],
                ['Avg Cost per Job', '$' . number_format($metrics['total_cost'] / max($stats['status_breakdown']['completed'], 1), 4)],
                ['Tokens per Dollar', number_format($metrics['total_tokens'] / max($metrics['total_cost'], 0.0001))],
            ]
        );

        // Model usage breakdown
        if (!empty($stats['model_usage'])) {
            $this->newLine();
            $this->info('🤖 Model Usage:');
            
            $modelData = collect($stats['model_usage'])->map(function ($count, $model) use ($stats) {
                $percentage = round(($count / max($stats['total_jobs'], 1)) * 100, 1);
                return [$model, number_format($count), $percentage . '%'];
            })->toArray();
            
            $this->table(['Model', 'Jobs', 'Percentage'], $modelData);
        }
    }

    private function displayRecentActivity(): void
    {
        $this->info('🕐 Recent Activity (Last 10 jobs):');
        
        $recentJobs = OpenAiJobResult::orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
        
        if ($recentJobs->isEmpty()) {
            $this->line('No recent activity');
            return;
        }
        
        $tableData = $recentJobs->map(function ($job) {
            $duration = $job->processing_time_ms ? round($job->processing_time_ms / 1000, 1) . 's' : 'N/A';
            $tokens = $job->getTotalTokens();
            $cost = '$' . number_format($job->getEstimatedCost(), 4);
            
            return [
                substr($job->job_id, 0, 12) . '...',
                $job->job_type,
                $this->getStatusIcon($job->status) . ' ' . $job->status,
                $job->created_at->diffForHumans(),
                $duration,
                number_format($tokens),
                $cost,
            ];
        })->toArray();
        
        $this->table(
            ['Job ID', 'Type', 'Status', 'Created', 'Duration', 'Tokens', 'Cost'],
            $tableData
        );
    }

    private function displayQueueStatus(): void
    {
        $this->info('📋 Queue Status:');
        
        $queueStatus = $this->jobManager->getQueueStatus();
        
        $this->table(
            ['Status', 'Count (Last Hour)', 'Avg Processing Time'],
            collect($queueStatus['recent_jobs'])->map(function ($data, $status) {
                $avgTime = $data['avg_processing_time_ms'] ? 
                    round($data['avg_processing_time_ms'] / 1000, 1) . 's' : 'N/A';
                return [
                    $this->getStatusIcon($status) . ' ' . $status,
                    $data['count'],
                    $avgTime
                ];
            })->toArray()
        );
    }

    private function displayActiveJobs(): void
    {
        $this->info('🌊 Active Streaming Jobs:');
        
        $activeJobs = OpenAiJobResult::where('status', 'processing')
            ->orderBy('started_at', 'desc')
            ->limit(8)
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
            $progress = $streamStatus ? 
                round(($tokensReceived / max($streamStatus['estimated_total_tokens'] ?? 2000, 1)) * 100, 1) : 0;
            
            $tableData[] = [
                substr($job->job_id, 0, 12) . '...',
                $job->job_type,
                number_format($tokensReceived),
                $progress . '%',
                $duration . 's',
                $streamStatus['status'] ?? 'unknown',
            ];
        }
        
        $this->table(
            ['Job ID', 'Type', 'Tokens', 'Progress', 'Duration', 'Stream Status'],
            $tableData
        );
    }

    // Helper methods
    private function getQueuedJobsEstimate(): int
    {
        // This is a placeholder - implement based on your queue driver
        // For Redis, you could check queue lengths
        return 0;
    }

    private function calculateHealthScore(): float
    {
        $recentJobs = OpenAiJobResult::where('created_at', '>=', now()->subHour())->get();
        
        if ($recentJobs->isEmpty()) {
            return 100.0; // No jobs = healthy
        }
        
        $total = $recentJobs->count();
        $completed = $recentJobs->where('status', 'completed')->count();
        $failed = $recentJobs->where('status', 'failed')->count();
        
        // Base score on success rate
        $successRate = ($completed / $total) * 100;
        
        // Penalize for high failure rate
        $failureRate = ($failed / $total) * 100;
        $healthScore = $successRate - ($failureRate * 2); // Failures hurt more
        
        return max(0, min(100, $healthScore));
    }

    private function getHealthColor(float $score): string
    {
        if ($score >= 90) return '🟢';
        if ($score >= 70) return '🟡';
        if ($score >= 50) return '🟠';
        return '🔴';
    }

    private function getHealthStatus(float $score): string
    {
        if ($score >= 90) return 'Excellent';
        if ($score >= 70) return 'Good';
        if ($score >= 50) return 'Fair';
        return 'Poor';
    }

    private function getStatusIcon(string $status): string
    {
        return match($status) {
            'completed' => '✅',
            'failed' => '❌',
            'processing' => '🔄',
            'pending' => '⏳',
            default => '❓'
        };
    }

    /**
     * Start interactive dashboard with enhanced features
     */
    private function startInteractiveDashboard(): int
    {
        $this->info('🚀 Starting Interactive OpenAI Dashboard...');
        $this->newLine();

        $running = true;
        while ($running) {
            $this->output->write("\033[2J\033[H"); // Clear screen
            
            // Display enhanced dashboard
            $this->displayEnhancedDashboard();
            
            // Show menu and handle input
            $choice = $this->showInteractiveMenu();
            $running = $this->handleMenuChoice($choice);
        }

        $this->info('👋 Goodbye!');
        return Command::SUCCESS;
    }

    /**
     * Display enhanced dashboard with system status
     */
    private function displayEnhancedDashboard(): void
    {
        $timestamp = now()->format('Y-m-d H:i:s');
        $this->info("📊 Enhanced OpenAI Dashboard - {$timestamp}");
        $this->info('═══════════════════════════════════════════════════════════');
        $this->newLine();

        // Get system status from enhanced job manager
        try {
            $systemStatus = $this->enhancedJobManager->getSystemStatus();
            $analytics = $this->enhancedJobManager->getAnalytics(1);

            $this->displayEnhancedSystemStatus($systemStatus, $analytics);
        } catch (\Exception $e) {
            $this->error("Error getting system status: {$e->getMessage()}");
            $this->displayBasicStatus();
        }
    }

    /**
     * Display enhanced system status
     */
    private function displayEnhancedSystemStatus(array $systemStatus, array $analytics): void
    {
        // System health
        $health = $systemStatus['system_health'] ?? [];
        $this->info('🏥 System Health');
        $healthTable = [
            ['Component', 'Status'],
            ['Database', $this->formatHealthStatus($health['database_connection'] ?? 'unknown')],
            ['Redis', $this->formatHealthStatus($health['redis_connection'] ?? 'unknown')],
            ['Queue', $this->formatHealthStatus($health['queue_connection'] ?? 'unknown')],
            ['OpenAI API', $this->formatHealthStatus($health['openai_api_status'] ?? 'unknown')]
        ];
        $this->table($healthTable[0], array_slice($healthTable, 1));
        $this->newLine();

        // Job statistics
        $jobStats = $analytics['job_statistics'] ?? [];
        $this->info('📈 24h Job Statistics');
        $statsTable = [
            ['Metric', 'Value'],
            ['Total Jobs', number_format($jobStats->total_jobs ?? 0)],
            ['Completed', number_format($jobStats->completed_jobs ?? 0)],
            ['Failed', number_format($jobStats->failed_jobs ?? 0)],
            ['Processing', number_format($jobStats->processing_jobs ?? 0)],
            ['Active Jobs', number_format($systemStatus['active_jobs'] ?? 0)],
            ['Success Rate', $this->calculateAnalyticsSuccessRate($jobStats) . '%']
        ];
        $this->table($statsTable[0], array_slice($statsTable, 1));
        $this->newLine();

        // Recent activity
        $this->displayRecentActivity();
    }

    /**
     * Display basic status when enhanced manager is not available
     */
    private function displayBasicStatus(): void
    {
        $this->info('📊 Basic System Status');
        $processing = OpenAiJobResult::where('status', 'processing')->count();
        $completedToday = OpenAiJobResult::where('status', 'completed')
            ->where('completed_at', '>=', now()->startOfDay())
            ->count();
        
        $this->table(['Metric', 'Value'], [
            ['Processing Jobs', $processing],
            ['Completed Today', $completedToday]
        ]);
    }

    /**
     * Show interactive menu
     */
    private function showInteractiveMenu(): string
    {
        $this->newLine();
        $this->info('📋 Interactive Menu');
        $this->line('  [1] View Job Details');
        $this->line('  [2] Create New Job');
        $this->line('  [3] Retry Failed Jobs');
        $this->line('  [4] Cancel Job');
        $this->line('  [5] System Analytics');
        $this->line('  [6] Cleanup Old Records');
        $this->line('  [7] Monitor Live Jobs');
        $this->line('  [r] Refresh Dashboard');
        $this->line('  [q] Quit');
        $this->newLine();

        return $this->ask('Choose an option');
    }

    /**
     * Handle menu choice
     */
    private function handleMenuChoice(string $choice): bool
    {
        switch (strtolower($choice)) {
            case '1':
                $this->viewJobDetailsInteractive();
                break;
            case '2':
                $this->createJobInteractive();
                break;
            case '3':
                $this->retryJobsInteractive();
                break;
            case '4':
                $this->cancelJobInteractive();
                break;
            case '5':
                $this->showAnalyticsInteractive();
                break;
            case '6':
                $this->cleanupInteractive();
                break;
            case '7':
                $this->call('openai:monitor', ['--live' => true]);
                break;
            case 'r':
                // Just refresh - return true to continue loop
                break;
            case 'q':
                return false;
            default:
                $this->warn("Invalid choice: {$choice}");
                $this->line('Press Enter to continue...');
                fgets(STDIN);
        }

        return true;
    }

    /**
     * Interactive job details viewer
     */
    private function viewJobDetailsInteractive(): void
    {
        $jobId = $this->ask('Enter Job ID (full or partial)');
        if (empty($jobId)) return;

        $job = OpenAiJobResult::where('job_id', 'LIKE', "%{$jobId}%")->first();
        if (!$job) {
            $this->error("Job not found: {$jobId}");
            $this->waitForEnter();
            return;
        }

        try {
            $jobStatus = $this->enhancedJobManager->getJobStatus($job->job_id);
            $this->displayJobDetailsEnhanced($jobStatus);
        } catch (\Exception $e) {
            $this->error("Error getting job details: {$e->getMessage()}");
        }

        $this->waitForEnter();
    }

    /**
     * Display enhanced job details
     */
    private function displayJobDetailsEnhanced(array $jobStatus): void
    {
        $this->newLine();
        $this->info("📄 Enhanced Job Details: {$jobStatus['job_id']}");
        $this->newLine();

        // Database status
        $dbStatus = $jobStatus['database_record'] ?? [];
        if ($dbStatus) {
            $this->table(['Property', 'Value'], [
                ['Status', $dbStatus['status']],
                ['Type', $dbStatus['job_type'] ?? 'N/A'],
                ['User ID', $dbStatus['user_id'] ?? 'N/A'],
                ['Created', $dbStatus['created_at'] ?? 'N/A'],
                ['Started', $dbStatus['started_at'] ?? 'N/A'],
                ['Completed', $dbStatus['completed_at'] ?? 'N/A'],
                ['Duration (ms)', $dbStatus['processing_time_ms'] ?? 'N/A'],
                ['Attempts', $dbStatus['attempts_made'] ?? 0]
            ]);
        }

        // Streaming status
        $streamingState = $jobStatus['streaming_state'] ?? [];
        if (!empty($streamingState)) {
            $this->newLine();
            $this->info('🌊 Live Streaming Status');
            $this->table(['Metric', 'Value'], [
                ['Tokens Received', number_format($streamingState['tokens_received'] ?? 0)],
                ['Progress', round($streamingState['progress_percentage'] ?? 0, 1) . '%'],
                ['Last Activity', $streamingState['last_activity'] ?? 'N/A'],
                ['Status', $streamingState['status'] ?? 'unknown']
            ]);
        }

        // Performance metrics
        $performance = $jobStatus['performance_metrics'] ?? [];
        if (!empty($performance)) {
            $this->newLine();
            $this->info('⚡ Performance Metrics');
            $this->table(['Metric', 'Value'], [
                ['Processing Time', $performance['processing_time'] ?? 'N/A'],
                ['Memory Usage', $performance['memory_usage'] ?? 'N/A']
            ]);
        }
    }

    /**
     * Interactive job creation
     */
    private function createJobInteractive(): void
    {
        $this->newLine();
        $this->info('🆕 Create New OpenAI Job');

        $prompt = $this->ask('Enter prompt for analysis');
        if (empty($prompt)) return;

        $jobType = $this->choice('Job type', [
            'security_analysis',
            'code_review', 
            'documentation'
        ], 'security_analysis');

        $priority = $this->choice('Priority', [
            'urgent',
            'high',
            'normal',
            'low'
        ], 'normal');

        try {
            $jobId = $this->enhancedJobManager->createEnhancedJob(
                prompt: $prompt,
                jobType: $jobType,
                priority: $priority,
                metadata: ['created_via' => 'interactive_dashboard']
            );

            $this->info("✅ Job created successfully!");
            $this->info("Job ID: {$jobId}");

        } catch (\Exception $e) {
            $this->error("❌ Failed to create job: {$e->getMessage()}");
        }

        $this->waitForEnter();
    }

    /**
     * Interactive retry jobs
     */
    private function retryJobsInteractive(): void
    {
        $failedCount = OpenAiJobResult::where('status', 'failed')
            ->where('failed_at', '>=', now()->subHours(24))
            ->count();

        if ($failedCount === 0) {
            $this->info('✅ No failed jobs found in the last 24 hours.');
            $this->waitForEnter();
            return;
        }

        $this->warn("Found {$failedCount} failed jobs.");
        if (!$this->confirm('Retry all failed jobs?')) return;

        $this->call('openai:monitor', ['--retry-failed' => true]);
        $this->waitForEnter();
    }

    /**
     * Interactive job cancellation
     */
    private function cancelJobInteractive(): void
    {
        $jobId = $this->ask('Enter Job ID to cancel');
        if (empty($jobId)) return;

        try {
            $success = $this->enhancedJobManager->cancelJob($jobId, 'Cancelled via interactive dashboard');
            
            if ($success) {
                $this->info('✅ Job cancelled successfully!');
            } else {
                $this->error('❌ Failed to cancel job.');
            }
        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
        }

        $this->waitForEnter();
    }

    /**
     * Show interactive analytics
     */
    private function showAnalyticsInteractive(): void
    {
        $days = (int) $this->ask('Days for analytics', '7');
        
        try {
            $analytics = $this->enhancedJobManager->getAnalytics($days);
            $this->displayEnhancedAnalytics($analytics, $days);
        } catch (\Exception $e) {
            $this->error("Error getting analytics: {$e->getMessage()}");
        }

        $this->waitForEnter();
    }

    /**
     * Display enhanced analytics
     */
    private function displayEnhancedAnalytics(array $analytics, int $days): void
    {
        $this->newLine();
        $this->info("📊 Enhanced Analytics (Last {$days} days)");
        $this->newLine();

        // Job statistics
        $jobStats = $analytics['job_statistics'] ?? [];
        $this->info('📈 Job Statistics');
        $this->table(['Metric', 'Value'], [
            ['Total Jobs', number_format($jobStats->total_jobs ?? 0)],
            ['Completed', number_format($jobStats->completed_jobs ?? 0)],
            ['Failed', number_format($jobStats->failed_jobs ?? 0)],
            ['Success Rate', $this->calculateAnalyticsSuccessRate($jobStats) . '%']
        ]);

        // Cost analysis
        $costAnalysis = $analytics['cost_analysis'] ?? [];
        if (!empty($costAnalysis['total_cost_usd'])) {
            $this->newLine();
            $this->info('💰 Cost Analysis');
            $this->table(['Metric', 'Value'], [
                ['Total Cost', '$' . number_format($costAnalysis['total_cost_usd'], 4)],
                ['Avg Cost per Job', '$' . number_format($costAnalysis['avg_cost_per_job'], 4)]
            ]);
        }
    }

    /**
     * Interactive cleanup
     */
    private function cleanupInteractive(): void
    {
        $days = (int) $this->ask('Delete records older than how many days?', '30');
        
        if (!$this->confirm("Delete records older than {$days} days?")) return;

        try {
            $results = $this->enhancedJobManager->cleanup($days);
            
            $this->info('✅ Cleanup completed:');
            $this->table(['Type', 'Items Cleaned'], [
                ['Database', $results['database_cleanup']['records_deleted'] ?? 0],
                ['Cache', $results['cache_cleanup']['cache_entries_cleaned'] ?? 0],
                ['Redis', $results['redis_cleanup']['redis_keys_cleaned'] ?? 0]
            ]);

        } catch (\Exception $e) {
            $this->error("❌ Cleanup failed: {$e->getMessage()}");
        }

        $this->waitForEnter();
    }

    // Helper methods for enhanced features
    
    private function formatHealthStatus(string $status): string
    {
        return match($status) {
            'ok' => '✅ OK',
            'error' => '❌ Error', 
            'warning' => '⚠️ Warning',
            default => '❓ Unknown'
        };
    }

    private function calculateAnalyticsSuccessRate($jobStats): float
    {
        $total = $jobStats->total_jobs ?? 0;
        $completed = $jobStats->completed_jobs ?? 0;
        
        return $total > 0 ? round(($completed / $total) * 100, 1) : 0;
    }

    private function waitForEnter(): void
    {
        $this->newLine();
        $this->line('Press Enter to continue...');
        fgets(STDIN);
    }
}