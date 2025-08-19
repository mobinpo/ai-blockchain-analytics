<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\QueueMonitoringRepositoryInterface;
use App\Models\Analysis;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

final class QueueMonitoringRepository implements QueueMonitoringRepositoryInterface
{
    /**
     * Get currently active job analyses
     */
    public function getActiveAnalyses(): array
    {
        return Cache::remember('active_analyses', 30, function () {
            return Analysis::with('project')
                ->whereIn('status', ['analyzing', 'processing', 'in_progress'])
                ->orderBy('updated_at', 'desc')
                ->get()
                ->map(function ($analysis) {
                    return [
                        'id' => $analysis->id,
                        'contractName' => $analysis->project->name ?? 'Unknown Contract',
                        'network' => $analysis->project->network ?? 'ethereum',
                        'type' => $this->getAnalysisType($analysis),
                        'status' => $analysis->status,
                        'progress' => $this->calculateProgress($analysis),
                        'currentStep' => $this->getCurrentStep($analysis),
                        'eta' => $this->calculateETA($analysis),
                        'duration' => $this->getDuration($analysis),
                        'findingsCount' => $analysis->findings()->count(),
                        'gasAnalyzed' => $analysis->gas_analyzed ?? 0,
                        'recentFindings' => $this->getRecentFindings($analysis)
                    ];
                })
                ->toArray();
        });
    }

    /**
     * Get queued analyses waiting for processing
     */
    public function getQueuedAnalyses(): array
    {
        return Cache::remember('queued_analyses', 60, function () {
            $queuedAnalyses = Analysis::with('project')
                ->where('status', 'queued')
                ->orderBy('created_at', 'asc')
                ->get();

            return $queuedAnalyses->map(function ($analysis, $index) {
                return [
                    'id' => $analysis->id,
                    'contractName' => $analysis->project->name ?? 'Unknown Contract',
                    'network' => $analysis->project->network ?? 'ethereum',
                    'type' => $this->getAnalysisType($analysis),
                    'estimatedStart' => Carbon::now()->addMinutes(($index + 1) * 5)->format('H:i'),
                    'priority' => $this->getPriority($analysis),
                    'queuePosition' => $index + 1
                ];
            })->toArray();
        });
    }

    /**
     * Get performance metrics for the queue system
     */
    public function getQueueMetrics(): array
    {
        return Cache::remember('queue_metrics', 120, function () {
            $today = Carbon::today();
            
            $completedToday = Analysis::where('status', 'completed')
                ->whereDate('updated_at', $today)
                ->count();

            $failedToday = Analysis::where('status', 'failed')
                ->whereDate('created_at', $today)
                ->count();

            $avgCompletionTime = Analysis::where('status', 'completed')
                ->whereDate('updated_at', $today)
                ->whereNotNull('completed_at')
                ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, completed_at)) as avg_time')
                ->value('avg_time') ?? 0;

            $totalFindingsToday = Analysis::where('status', 'completed')
                ->whereDate('updated_at', $today)
                ->withCount('findings')
                ->get()
                ->sum('findings_count');

            $queueSize = Analysis::where('status', 'queued')->count();
            $activeWorkers = $this->getActiveWorkerCount();
            
            $successRate = $completedToday + $failedToday > 0 
                ? ($completedToday / ($completedToday + $failedToday)) * 100 
                : 100;

            return [
                'totalAnalysesToday' => $completedToday,
                'averageCompletionTime' => round($avgCompletionTime),
                'totalFindingsToday' => $totalFindingsToday,
                'systemLoad' => $this->getSystemLoad(),
                'successRate' => round($successRate, 1),
                'activeWorkers' => $activeWorkers,
                'queueSize' => $queueSize,
                'averageProcessingTime' => round($avgCompletionTime / 60) // in minutes
            ];
        });
    }

    /**
     * Get recent job failures and errors
     */
    public function getRecentFailures(int $limit = 10): array
    {
        return Analysis::with('project')
            ->where('status', 'failed')
            ->whereDate('updated_at', '>=', Carbon::now()->subDays(7))
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($analysis) {
                return [
                    'id' => $analysis->id,
                    'contract' => $analysis->project->name ?? 'Unknown',
                    'error' => $analysis->error_message ?? 'Unknown error',
                    'timestamp' => $analysis->updated_at,
                    'duration' => $this->getDuration($analysis)
                ];
            })
            ->toArray();
    }

    /**
     * Get analysis type based on analysis data
     */
    private function getAnalysisType($analysis): string
    {
        if ($analysis->analysis_type) {
            return $analysis->analysis_type;
        }

        // Infer from other data
        if ($analysis->findings()->where('severity', 'critical')->exists()) {
            return 'Security Audit';
        }

        return 'Code Review';
    }

    /**
     * Calculate progress percentage
     */
    private function calculateProgress($analysis): int
    {
        if ($analysis->progress) {
            return $analysis->progress;
        }

        // Calculate based on status and time elapsed
        $elapsed = $analysis->created_at->diffInMinutes(now());
        $estimatedTotal = 10; // 10 minutes estimated

        return min(90, ($elapsed / $estimatedTotal) * 100);
    }

    /**
     * Get current analysis step
     */
    private function getCurrentStep($analysis): string
    {
        return $analysis->current_step ?? match($analysis->status) {
            'analyzing' => 'Code Pattern Analysis',
            'processing' => 'Vulnerability Assessment',
            'in_progress' => 'Report Generation',
            default => 'Initializing'
        };
    }

    /**
     * Calculate estimated time to completion
     */
    private function calculateETA($analysis): string
    {
        $progress = $this->calculateProgress($analysis);
        
        if ($progress > 90) return '30s remaining';
        if ($progress > 70) return '2 min remaining';
        if ($progress > 40) return '5 min remaining';
        if ($progress > 20) return '8 min remaining';
        
        return 'Calculating...';
    }

    /**
     * Get analysis duration
     */
    private function getDuration($analysis): int
    {
        return $analysis->created_at->diffInSeconds(
            $analysis->completed_at ?? now()
        );
    }

    /**
     * Get recent findings for an analysis
     */
    private function getRecentFindings($analysis): array
    {
        return $analysis->findings()
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get()
            ->map(function ($finding) {
                return [
                    'id' => $finding->id,
                    'title' => $finding->title,
                    'severity' => $finding->severity,
                    'timestamp' => $finding->created_at->diffForHumans()
                ];
            })
            ->toArray();
    }

    /**
     * Get priority for queued analysis
     */
    private function getPriority($analysis): string
    {
        // Determine priority based on project or other factors
        return $analysis->priority ?? 'medium';
    }

    /**
     * Get active worker count from queue system
     */
    private function getActiveWorkerCount(): int
    {
        try {
            // Try to get from Horizon if available
            $redis = Redis::connection();
            $supervisors = $redis->smembers('supervisors');
            return count($supervisors);
        } catch (\Exception $e) {
            // Fallback to configured workers
            return config('queue.connections.redis.workers', 5);
        }
    }

    /**
     * Get current system load percentage
     */
    private function getSystemLoad(): int
    {
        $activeCount = Analysis::whereIn('status', ['analyzing', 'processing'])->count();
        $maxConcurrent = config('app.max_concurrent_analyses', 10);
        
        return min(100, ($activeCount / $maxConcurrent) * 100);
    }
}
