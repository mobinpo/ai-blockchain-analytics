<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CrawlerJobStatus;
use App\Models\SocialMediaPost;
use App\Models\CrawlerKeywordRule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

final class CrawlerStatus extends Command
{
    protected $signature = 'crawler:status
                           {job_id? : Specific job ID to check}
                           {--live : Live monitoring mode (refreshes every 5 seconds)}
                           {--platform= : Filter by platform}
                           {--hours=24 : Show jobs from last N hours}
                           {--format=table : Output format (table,json)}';

    protected $description = 'Monitor crawler job status and system health';

    public function handle(): int
    {
        if ($this->option('live')) {
            return $this->liveMonitoring();
        }

        if ($jobId = $this->argument('job_id')) {
            return $this->showJobStatus($jobId);
        }

        return $this->showSystemStatus();
    }

    private function liveMonitoring(): int
    {
        $this->info('🔄 Live Crawler Monitoring (Press Ctrl+C to exit)');
        $this->newLine();

        while (true) {
            // Clear screen
            system('clear');
            
            $this->info('🕷️  CRAWLER LIVE MONITORING - ' . now()->format('Y-m-d H:i:s'));
            $this->info('═══════════════════════════════════════════════════════');
            $this->newLine();

            $this->displaySystemHealth();
            $this->newLine();
            
            $this->displayActiveJobs();
            $this->newLine();
            
            $this->displayRecentActivity();
            $this->newLine();
            
            $this->info('Refreshing in 5 seconds... (Press Ctrl+C to exit)');
            sleep(5);
        }

        return Command::SUCCESS;
    }

    private function showJobStatus(string $jobId): int
    {
        $job = CrawlerJobStatus::where('job_id', $jobId)->first();
        
        if (!$job) {
            $this->error("❌ Job {$jobId} not found");
            return Command::FAILURE;
        }

        $this->info("📋 Job Status: {$jobId}");
        $this->newLine();

        // Job details
        $this->table(['Property', 'Value'], [
            ['Job ID', $job->job_id],
            ['Status', $this->getStatusIcon($job->status) . ' ' . ucfirst($job->status)],
            ['Platforms', implode(', ', $job->platforms)],
            ['Priority', ucfirst($job->priority)],
            ['Started At', $job->started_at?->format('Y-m-d H:i:s')],
            ['Completed At', $job->completed_at?->format('Y-m-d H:i:s') ?? 'N/A'],
            ['Duration', $this->calculateDuration($job)],
            ['Posts Collected', $job->posts_collected ?? 0],
            ['Posts Stored', $job->posts_stored ?? 0],
            ['Keywords Matched', $job->keywords_matched ?? 0],
            ['Error Message', $job->error_message ?? 'None']
        ]);

        // Platform breakdown
        if ($job->results && isset($job->results['platforms'])) {
            $this->newLine();
            $this->info('🌐 Platform Results:');
            
            $platformData = [];
            foreach ($job->results['platforms'] as $platform => $result) {
                $platformData[] = [
                    ucfirst($platform),
                    $result['posts_found'] ?? 0,
                    $result['keyword_matches'] ?? 0,
                    $result['processing_time_ms'] ?? 0 . 'ms',
                    $result['status'] ?? 'unknown'
                ];
            }
            
            $this->table(['Platform', 'Posts', 'Matches', 'Time', 'Status'], $platformData);
        }

        // Recent posts from this job
        $recentPosts = SocialMediaPost::where('crawler_job_id', $jobId)
            ->latest()
            ->limit(5)
            ->get();
            
        if ($recentPosts->isNotEmpty()) {
            $this->newLine();
            $this->info('📝 Recent Posts Collected:');
            
            $postData = [];
            foreach ($recentPosts as $post) {
                $postData[] = [
                    ucfirst($post->platform),
                    $post->author,
                    substr($post->content, 0, 50) . '...',
                    $post->created_at->format('H:i:s'),
                    $post->sentiment_score ?? 'N/A'
                ];
            }
            
            $this->table(['Platform', 'Author', 'Content (Preview)', 'Time', 'Sentiment'], $postData);
        }

        return Command::SUCCESS;
    }

    private function showSystemStatus(): int
    {
        $this->info('🕷️  CRAWLER SYSTEM STATUS');
        $this->newLine();

        $this->displaySystemHealth();
        $this->newLine();
        
        $this->displayRecentJobs();
        $this->newLine();
        
        $this->displayKeywordRules();
        $this->newLine();
        
        $this->displaySystemMetrics();

        return Command::SUCCESS;
    }

    private function displaySystemHealth(): void
    {
        $config = config('crawler_microservice');
        
        $this->info('🏥 System Health');
        
        $healthData = [];
        
        // Platform health
        foreach (['twitter', 'reddit', 'telegram'] as $platform) {
            $enabled = $config[$platform]['enabled'] ?? false;
            $configured = $this->isPlatformConfigured($platform);
            
            $status = $enabled && $configured ? '✅ Healthy' : '❌ Offline';
            $healthData[] = [ucfirst($platform), $status];
        }
        
        // Queue health
        $queueHealth = $this->checkQueueHealth();
        $healthData[] = ['Queue System', $queueHealth ? '✅ Healthy' : '❌ Issues'];
        
        // Cache health
        $cacheHealth = $this->checkCacheHealth();
        $healthData[] = ['Cache System', $cacheHealth ? '✅ Healthy' : '❌ Issues'];

        $this->table(['Component', 'Status'], $healthData);
    }

    private function displayActiveJobs(): void
    {
        $activeJobs = CrawlerJobStatus::whereIn('status', ['pending', 'running', 'processing'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $this->info('⚡ Active Jobs (' . $activeJobs->count() . ')');
        
        if ($activeJobs->isEmpty()) {
            $this->line('   No active jobs');
            return;
        }

        $jobData = [];
        foreach ($activeJobs as $job) {
            $jobData[] = [
                $job->job_id,
                $this->getStatusIcon($job->status) . ' ' . ucfirst($job->status),
                implode(', ', $job->platforms),
                $job->priority,
                $job->created_at->diffForHumans(),
                $job->posts_collected ?? 0
            ];
        }

        $this->table(['Job ID', 'Status', 'Platforms', 'Priority', 'Started', 'Posts'], $jobData);
    }

    private function displayRecentJobs(): void
    {
        $hours = (int) $this->option('hours');
        $platform = $this->option('platform');
        
        $query = CrawlerJobStatus::where('created_at', '>=', now()->subHours($hours));
        
        if ($platform) {
            $query->whereJsonContains('platforms', $platform);
        }
        
        $recentJobs = $query->orderBy('created_at', 'desc')
            ->limit(15)
            ->get();

        $this->info("📋 Recent Jobs (Last {$hours} hours)" . ($platform ? " - {$platform}" : ''));
        
        if ($recentJobs->isEmpty()) {
            $this->line('   No recent jobs found');
            return;
        }

        $jobData = [];
        foreach ($recentJobs as $job) {
            $jobData[] = [
                substr($job->job_id, -8),
                $this->getStatusIcon($job->status) . ' ' . ucfirst($job->status),
                implode(',', array_slice($job->platforms, 0, 2)),
                $job->posts_collected ?? 0,
                $job->posts_stored ?? 0,
                $this->calculateDuration($job),
                $job->created_at->format('H:i')
            ];
        }

        $this->table(['Job ID', 'Status', 'Platforms', 'Collected', 'Stored', 'Duration', 'Time'], $jobData);
    }

    private function displayRecentActivity(): void
    {
        $recentPosts = SocialMediaPost::where('created_at', '>=', now()->subMinutes(30))
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $this->info('📝 Recent Activity (Last 30 minutes)');
        
        if ($recentPosts->isEmpty()) {
            $this->line('   No recent activity');
            return;
        }

        $activityData = [];
        foreach ($recentPosts as $post) {
            $activityData[] = [
                ucfirst($post->platform),
                $post->author ?? 'Unknown',
                substr($post->content, 0, 40) . '...',
                $post->sentiment_score ? round($post->sentiment_score, 2) : 'N/A',
                $post->created_at->format('H:i:s')
            ];
        }

        $this->table(['Platform', 'Author', 'Content', 'Sentiment', 'Time'], $activityData);
    }

    private function displayKeywordRules(): void
    {
        $rules = CrawlerKeywordRule::active()->get();
        
        $this->info('🔑 Active Keyword Rules (' . $rules->count() . ')');
        
        if ($rules->isEmpty()) {
            $this->line('   No active keyword rules found');
            return;
        }

        $ruleData = [];
        foreach ($rules as $rule) {
            $ruleData[] = [
                $rule->name,
                substr(implode(', ', $rule->keywords), 0, 40) . '...',
                implode(', ', $rule->platforms),
                $rule->priority,
                $rule->updated_at->diffForHumans()
            ];
        }

        $this->table(['Name', 'Keywords', 'Platforms', 'Priority', 'Updated'], $ruleData);
    }

    private function displaySystemMetrics(): void
    {
        $this->info('📊 System Metrics (Last 24 Hours)');
        
        $metrics = [
            'Total Jobs' => CrawlerJobStatus::where('created_at', '>=', now()->subDay())->count(),
            'Successful Jobs' => CrawlerJobStatus::where('created_at', '>=', now()->subDay())->where('status', 'completed')->count(),
            'Failed Jobs' => CrawlerJobStatus::where('created_at', '>=', now()->subDay())->where('status', 'failed')->count(),
            'Posts Collected' => SocialMediaPost::where('created_at', '>=', now()->subDay())->count(),
            'Avg Processing Time' => $this->getAverageProcessingTime() . 'ms',
            'Cache Hit Rate' => $this->getCacheHitRate() . '%'
        ];

        $metricsData = [];
        foreach ($metrics as $metric => $value) {
            $metricsData[] = [$metric, $value];
        }

        $this->table(['Metric', 'Value'], $metricsData);
    }

    private function getStatusIcon(string $status): string
    {
        return match($status) {
            'completed' => '✅',
            'failed', 'error' => '❌',
            'pending' => '⏳',
            'running', 'processing' => '🔄',
            'cancelled' => '⛔',
            default => '❓'
        };
    }

    private function calculateDuration(CrawlerJobStatus $job): string
    {
        if (!$job->started_at) return 'N/A';
        
        $end = $job->completed_at ?? now();
        $seconds = $job->started_at->diffInSeconds($end);
        
        if ($seconds < 60) return "{$seconds}s";
        if ($seconds < 3600) return round($seconds / 60, 1) . 'm';
        return round($seconds / 3600, 1) . 'h';
    }

    private function isPlatformConfigured(string $platform): bool
    {
        $config = config("crawler_microservice.{$platform}");
        
        return match($platform) {
            'twitter' => !empty($config['bearer_token']),
            'reddit' => !empty($config['client_id']) && !empty($config['client_secret']),
            'telegram' => !empty($config['bot_token']),
            default => false
        };
    }

    private function checkQueueHealth(): bool
    {
        try {
            // Simple queue health check
            return Cache::get('queue_health', true);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkCacheHealth(): bool
    {
        try {
            Cache::put('health_check', 'ok', 60);
            return Cache::get('health_check') === 'ok';
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getAverageProcessingTime(): string
    {
        $avgTime = CrawlerJobStatus::where('created_at', '>=', now()->subDay())
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (completed_at - started_at)) * 1000) as avg_time')
            ->value('avg_time');
            
        return $avgTime ? round($avgTime) : '0';
    }

    private function getCacheHitRate(): string
    {
        // Simplified cache hit rate calculation
        $hits = Cache::get('crawler_cache_hits', 0);
        $misses = Cache::get('crawler_cache_misses', 0);
        $total = $hits + $misses;
        
        return $total > 0 ? round(($hits / $total) * 100) : '0';
    }
}