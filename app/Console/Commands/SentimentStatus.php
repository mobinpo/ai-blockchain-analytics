<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SentimentPipeline\SentimentPipelineService;
use App\Models\SentimentBatch;
use App\Models\SentimentBatchDocument;
use App\Models\DailySentimentAggregate;
use Illuminate\Console\Command;
use Carbon\Carbon;

final class SentimentStatus extends Command
{
    protected $signature = 'sentiment:status 
                           {--live : Show live monitoring with auto-refresh}
                           {--platform= : Filter by platform}
                           {--date= : Show status for specific date}
                           {--detailed : Show detailed information}
                           {--export=table : Export format (table, json, csv)}';

    protected $description = 'Display sentiment pipeline status and health metrics';

    public function handle(): int
    {
        if ($this->option('live')) {
            return $this->handleLiveMonitoring();
        }

        $this->displayHeader();
        $this->displaySystemHealth();
        $this->displayProcessingStats();
        $this->displayRecentActivity();
        
        if ($this->option('detailed')) {
            $this->displayDetailedMetrics();
        }
        
        $this->displayQuickActions();
        
        return Command::SUCCESS;
    }

    private function handleLiveMonitoring(): int
    {
        $this->info('🔴 LIVE SENTIMENT PIPELINE MONITORING');
        $this->info('Press Ctrl+C to exit');
        $this->info('═══════════════════════════════════════════════════════════');
        
        while (true) {
            // Clear screen
            $this->line("\033[2J\033[H");
            
            $this->info('🔴 LIVE MONITORING - ' . now()->format('Y-m-d H:i:s'));
            $this->info('═══════════════════════════════════════════════════════════');
            
            $this->displaySystemHealth();
            $this->displayProcessingStats();
            $this->displayRecentActivity();
            
            $this->newLine();
            $this->line('🔄 Refreshing in 30 seconds... (Ctrl+C to exit)');
            
            sleep(30);
        }
        
        return Command::SUCCESS;
    }

    private function displayHeader(): void
    {
        $this->info('📊 SENTIMENT PIPELINE STATUS');
        $this->info('Real-time monitoring of sentiment analysis pipeline');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->newLine();
    }

    private function displaySystemHealth(): void
    {
        $this->info('🏥 System Health');
        
        try {
            $pipelineService = app(SentimentPipelineService::class);
            $status = $pipelineService->getPipelineStatus();
            
            $healthData = [
                ['Component', 'Status', 'Details']
            ];
            
            // Google NLP Service
            $nlpStatus = $this->checkGoogleNlpHealth();
            $healthData[] = [
                'Google Cloud NLP',
                $nlpStatus['status'] === 'healthy' ? '✅ Healthy' : '❌ Unhealthy',
                $nlpStatus['message']
            ];
            
            // Database Connection
            $dbStatus = $this->checkDatabaseHealth();
            $healthData[] = [
                'Database',
                $dbStatus['status'] === 'healthy' ? '✅ Healthy' : '❌ Unhealthy',
                $dbStatus['message']
            ];
            
            // Queue System
            $queueStatus = $this->checkQueueHealth();
            $healthData[] = [
                'Queue System',
                $queueStatus['status'] === 'healthy' ? '✅ Healthy' : '❌ Unhealthy',
                $queueStatus['message']
            ];
            
            // Pipeline Health
            $pipelineHealth = $status['pipeline_health'] ?? ['status' => 'unknown'];
            $healthData[] = [
                'Pipeline',
                $pipelineHealth['status'] === 'healthy' ? '✅ Healthy' : 
                ($pipelineHealth['status'] === 'degraded' ? '⚠️ Degraded' : '❌ Unhealthy'),
                implode(', ', $pipelineHealth['issues'] ?? ['No issues'])
            ];
            
            $this->table(['Component', 'Status', 'Details'], array_slice($healthData, 1));
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to check system health: ' . $e->getMessage());
        }
        
        $this->newLine();
    }

    private function displayProcessingStats(): void
    {
        $this->info('📊 Processing Statistics');
        
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : today();
        $platform = $this->option('platform');
        
        try {
            // Today's stats
            $todayBatches = SentimentBatch::whereDate('created_at', $date);
            $todayDocuments = SentimentBatchDocument::whereDate('created_at', $date);
            
            $stats = [
                ['Metric', 'Today', 'Total'],
                [
                    'Batches Processed',
                    $todayBatches->where('status', 'completed')->count(),
                    SentimentBatch::where('status', 'completed')->count()
                ],
                [
                    'Batches Failed',
                    $todayBatches->where('status', 'failed')->count(),
                    SentimentBatch::where('status', 'failed')->count()
                ],
                [
                    'Documents Processed',
                    $todayDocuments->where('status', 'completed')->count(),
                    SentimentBatchDocument::where('status', 'completed')->count()
                ],
                [
                    'Documents Failed',
                    $todayDocuments->where('status', 'failed')->count(),
                    SentimentBatchDocument::where('status', 'failed')->count()
                ],
                [
                    'Active Batches',
                    SentimentBatch::where('status', 'processing')->count(),
                    '-'
                ]
            ];
            
            $this->table(['Metric', 'Today', 'Total'], array_slice($stats, 1));
            
            // Success rates
            $todayTotal = $todayDocuments->count();
            $todaySuccessful = $todayDocuments->where('status', 'completed')->count();
            $successRate = $todayTotal > 0 ? round(($todaySuccessful / $todayTotal) * 100, 1) : 0;
            
            $this->line("📈 Success Rate (Today): {$successRate}%");
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to fetch processing stats: ' . $e->getMessage());
        }
        
        $this->newLine();
    }

    private function displayRecentActivity(): void
    {
        $this->info('📝 Recent Activity (Last 24 hours)');
        
        try {
            $recentBatches = SentimentBatch::where('created_at', '>=', now()->subDay())
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
            
            if ($recentBatches->isEmpty()) {
                $this->line('   No recent activity');
                $this->newLine();
                return;
            }
            
            $activityData = [];
            foreach ($recentBatches as $batch) {
                $status = match($batch->status) {
                    'completed' => '✅ Completed',
                    'failed' => '❌ Failed',
                    'processing' => '🔄 Processing',
                    'pending' => '⏳ Pending',
                    default => $batch->status
                };
                
                $activityData[] = [
                    $batch->name,
                    $status,
                    $batch->total_documents ?? 0,
                    $batch->processed_documents ?? 0,
                    $batch->created_at->format('H:i:s'),
                    $batch->processing_time ? round($batch->processing_time, 1) . 's' : '-'
                ];
            }
            
            $this->table(
                ['Batch Name', 'Status', 'Total', 'Processed', 'Time', 'Duration'],
                $activityData
            );
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to fetch recent activity: ' . $e->getMessage());
        }
        
        $this->newLine();
    }

    private function displayDetailedMetrics(): void
    {
        $this->info('🔍 Detailed Metrics');
        
        try {
            // Sentiment distribution from recent aggregates
            $recentAggregates = DailySentimentAggregate::where('aggregate_date', '>=', now()->subDays(7))
                ->fullDay()
                ->get();
            
            if (!$recentAggregates->isEmpty()) {
                $this->line('📊 Sentiment Distribution (Last 7 days):');
                
                $totalPosts = $recentAggregates->sum('total_posts');
                $avgSentiment = $recentAggregates->avg('average_sentiment');
                $positiveCount = $recentAggregates->sum('positive_count') + $recentAggregates->sum('very_positive_count');
                $negativeCount = $recentAggregates->sum('negative_count') + $recentAggregates->sum('very_negative_count');
                $neutralCount = $recentAggregates->sum('neutral_count');
                
                $distributionData = [
                    ['Sentiment', 'Count', 'Percentage'],
                    ['Positive', $positiveCount, $totalPosts > 0 ? round(($positiveCount / $totalPosts) * 100, 1) . '%' : '0%'],
                    ['Negative', $negativeCount, $totalPosts > 0 ? round(($negativeCount / $totalPosts) * 100, 1) . '%' : '0%'],
                    ['Neutral', $neutralCount, $totalPosts > 0 ? round(($neutralCount / $totalPosts) * 100, 1) . '%' : '0%'],
                ];
                
                $this->table(['Sentiment', 'Count', 'Percentage'], array_slice($distributionData, 1));
                
                $this->line("📈 Average Sentiment Score: " . round($avgSentiment, 3));
                $this->line("📊 Total Posts Analyzed: " . number_format($totalPosts));
            }
            
            // Platform breakdown
            $platformStats = DailySentimentAggregate::where('aggregate_date', '>=', now()->subDays(7))
                ->fullDay()
                ->whereNotNull('platform')
                ->where('platform', '!=', 'all')
                ->groupBy('platform')
                ->selectRaw('platform, sum(total_posts) as total_posts, avg(average_sentiment) as avg_sentiment')
                ->get();
                
            if (!$platformStats->isEmpty()) {
                $this->newLine();
                $this->line('📱 Platform Breakdown (Last 7 days):');
                
                $platformData = [];
                foreach ($platformStats as $stat) {
                    $platformData[] = [
                        ucfirst($stat->platform),
                        number_format($stat->total_posts),
                        round($stat->avg_sentiment, 3),
                        $this->getSentimentLabel($stat->avg_sentiment)
                    ];
                }
                
                $this->table(['Platform', 'Posts', 'Avg Sentiment', 'Label'], $platformData);
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to fetch detailed metrics: ' . $e->getMessage());
        }
        
        $this->newLine();
    }

    private function displayQuickActions(): void
    {
        $this->info('🛠️  Quick Actions');
        $this->line('   sentiment:process --demo      → Run demo processing');
        $this->line('   sentiment:process --source=crawler --aggregate → Process recent crawler data');
        $this->line('   sentiment:aggregates --date=today → View today\'s aggregates');
        $this->line('   sentiment:cleanup             → Clean up old data');
        $this->newLine();
    }

    private function checkGoogleNlpHealth(): array
    {
        try {
            $projectId = config('sentiment_pipeline.google_nlp.project_id');
            $credentials = config('services.google_language.credentials');
            
            if (!$projectId || !$credentials) {
                return [
                    'status' => 'unhealthy',
                    'message' => 'Configuration missing'
                ];
            }
            
            // TODO: Add actual health check call to Google NLP
            return [
                'status' => 'healthy',
                'message' => 'Configured and ready'
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    private function checkDatabaseHealth(): array
    {
        try {
            \DB::connection()->getPdo();
            return [
                'status' => 'healthy',
                'message' => 'Connected'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Connection failed'
            ];
        }
    }

    private function checkQueueHealth(): array
    {
        try {
            $connection = config('queue.default');
            return [
                'status' => 'healthy',
                'message' => "Using {$connection}"
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Queue not configured'
            ];
        }
    }

    private function getSentimentLabel(float $score): string
    {
        return match(true) {
            $score > 0.6 => 'Very Positive',
            $score > 0.2 => 'Positive',
            $score > -0.2 => 'Neutral',
            $score > -0.6 => 'Negative',
            default => 'Very Negative'
        };
    }
}