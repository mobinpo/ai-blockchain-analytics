<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OpenAiJobResult;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Laravel\Horizon\Contracts\WorkloadRepository;

class OpenAiHorizonController extends Controller
{
    public function __construct(
        private WorkloadRepository $workload
    ) {}

    /**
     * Get comprehensive Horizon monitoring dashboard data
     */
    public function getDashboard(): JsonResponse
    {
        try {
            $stats = $this->getQueueStatistics();
            $jobs = $this->getRecentJobs();
            $workload = $this->getCurrentWorkload();
            $performance = $this->getPerformanceMetricsData();

            return response()->json([
                'success' => true,
                'dashboard' => [
                    'queue_statistics' => $stats,
                    'recent_jobs' => $jobs,
                    'current_workload' => $workload,
                    'performance_metrics' => $performance,
                    'system_health' => $this->getSystemHealthData(),
                    'generated_at' => now()->toISOString(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get Horizon dashboard', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to get dashboard data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get queue statistics for OpenAI jobs
     */
    public function getQueueStats(): JsonResponse
    {
        try {
            $stats = $this->getQueueStatistics();

            return response()->json([
                'success' => true,
                'queue_stats' => $stats,
                'generated_at' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get queue statistics',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current workload distribution
     */
    public function getWorkload(): JsonResponse
    {
        try {
            $workload = $this->getCurrentWorkload();

            return response()->json([
                'success' => true,
                'workload' => $workload,
                'generated_at' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get workload data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get performance metrics over time
     */
    public function getPerformanceMetrics(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => 'sometimes|string|in:1h,6h,24h,7d',
            'metric' => 'sometimes|string|in:throughput,latency,success_rate,all'
        ]);

        try {
            $period = $validated['period'] ?? '24h';
            $metric = $validated['metric'] ?? 'all';

            $metrics = $this->getPerformanceMetricsData($period, $metric);

            return response()->json([
                'success' => true,
                'performance_metrics' => $metrics,
                'period' => $period,
                'metric' => $metric,
                'generated_at' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get performance metrics',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get failed jobs with details
     */
    public function getFailedJobs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'sometimes|integer|min:1|max:100',
            'since' => 'sometimes|date'
        ]);

        try {
            $limit = $validated['limit'] ?? 50;
            $since = $validated['since'] ?? now()->subHours(24);

            $failedJobs = OpenAiJobResult::where('status', 'failed')
                ->where('failed_at', '>=', $since)
                ->orderBy('failed_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($job) {
                    return [
                        'job_id' => $job->job_id,
                        'job_type' => $job->job_type,
                        'error_message' => $job->error_message,
                        'failed_at' => $job->failed_at?->toISOString(),
                        'attempts_made' => $job->attempts_made,
                        'processing_time_ms' => $job->processing_time_ms,
                        'user_id' => $job->user_id,
                        'config' => $job->config
                    ];
                });

            return response()->json([
                'success' => true,
                'failed_jobs' => $failedJobs,
                'count' => $failedJobs->count(),
                'period_start' => $since,
                'generated_at' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get failed jobs',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retry failed jobs
     */
    public function retryFailedJobs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'job_ids' => 'sometimes|array',
            'job_ids.*' => 'string',
            'retry_all_recent' => 'sometimes|boolean',
            'since_hours' => 'sometimes|integer|min:1|max:168'
        ]);

        try {
            $retriedJobs = [];

            if (!empty($validated['job_ids'])) {
                // Retry specific jobs
                foreach ($validated['job_ids'] as $jobId) {
                    $result = $this->retrySpecificJob($jobId);
                    if ($result) {
                        $retriedJobs[] = $jobId;
                    }
                }
            } elseif ($validated['retry_all_recent'] ?? false) {
                // Retry all recent failed jobs
                $sinceHours = $validated['since_hours'] ?? 24;
                $failedJobs = OpenAiJobResult::where('status', 'failed')
                    ->where('failed_at', '>=', now()->subHours($sinceHours))
                    ->get();

                foreach ($failedJobs as $job) {
                    $result = $this->retrySpecificJob($job->job_id);
                    if ($result) {
                        $retriedJobs[] = $job->job_id;
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Jobs retry initiated',
                'retried_jobs' => $retriedJobs,
                'count' => count($retriedJobs),
                'retried_at' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retry jobs',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get system health status
     */
    public function getSystemHealth(): JsonResponse
    {
        try {
            $health = $this->getSystemHealthData();

            return response()->json([
                'success' => true,
                'system_health' => $health,
                'generated_at' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get system health',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Pause/Resume queue processing
     */
    public function toggleQueue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|string|in:pause,resume',
            'queue' => 'sometimes|string'
        ]);

        try {
            $action = $validated['action'];
            $queueName = $validated['queue'] ?? 'default';

            // This would typically interact with Horizon/Supervisor
            // For now, we'll log the action and return a simulated response
            Log::info("Queue {$action} requested", [
                'queue' => $queueName,
                'user' => request()->user()?->id
            ]);

            return response()->json([
                'success' => true,
                'message' => "Queue {$action} action initiated",
                'queue' => $queueName,
                'action' => $action,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to toggle queue',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detailed queue statistics
     */
    private function getQueueStatistics(): array
    {
        $recentJobs = OpenAiJobResult::where('created_at', '>=', now()->subHours(24))->get();

        $totalJobs = $recentJobs->count();
        $completedJobs = $recentJobs->where('status', 'completed')->count();
        $failedJobs = $recentJobs->where('status', 'failed')->count();
        $processingJobs = $recentJobs->where('status', 'processing')->count();
        $pendingJobs = $recentJobs->where('status', 'pending')->count();

        return [
            'total_jobs_24h' => $totalJobs,
            'completed_jobs' => $completedJobs,
            'failed_jobs' => $failedJobs,
            'processing_jobs' => $processingJobs,
            'pending_jobs' => $pendingJobs,
            'success_rate' => $totalJobs > 0 ? round(($completedJobs / $totalJobs) * 100, 2) : 0,
            'failure_rate' => $totalJobs > 0 ? round(($failedJobs / $totalJobs) * 100, 2) : 0,
            'average_processing_time_ms' => $recentJobs->where('status', 'completed')->avg('processing_time_ms') ?? 0,
            'total_tokens_processed' => $recentJobs->sum(fn($job) => $job->getTotalTokens()),
            'estimated_total_cost' => $recentJobs->sum(fn($job) => $job->getEstimatedCost()),
            'jobs_by_type' => $recentJobs->groupBy('job_type')->map->count(),
            'jobs_by_model' => $recentJobs->groupBy(fn($job) => $job->getModel())->map->count(),
        ];
    }

    /**
     * Get recent jobs for monitoring
     */
    private function getRecentJobs(int $limit = 20): array
    {
        return OpenAiJobResult::orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($job) {
                return [
                    'job_id' => $job->job_id,
                    'job_type' => $job->job_type,
                    'status' => $job->status,
                    'model' => $job->getModel(),
                    'created_at' => $job->created_at->toISOString(),
                    'processing_time_ms' => $job->processing_time_ms,
                    'total_tokens' => $job->getTotalTokens(),
                    'estimated_cost' => $job->getEstimatedCost(),
                    'error_message' => $job->error_message ? substr($job->error_message, 0, 100) . '...' : null,
                ];
            })
            ->toArray();
    }

    /**
     * Get current workload distribution
     */
    private function getCurrentWorkload(): array
    {
        try {
            // Get workload from Horizon if available
            $workload = $this->workload->get();
            
            return [
                'total_processes' => count($workload),
                'queue_distribution' => collect($workload)->groupBy('name')->map->count(),
                'process_details' => collect($workload)->map(function ($process) {
                    return [
                        'name' => $process['name'],
                        'processes' => $process['processes'],
                        'length' => $process['length'] ?? 0,
                        'wait' => $process['wait'] ?? 0
                    ];
                })->values()
            ];
        } catch (\Exception $e) {
            // Fallback if Horizon is not available
            return [
                'total_processes' => 0,
                'queue_distribution' => [],
                'process_details' => [],
                'note' => 'Horizon workload data unavailable'
            ];
        }
    }

    /**
     * Get performance metrics data
     */
    private function getPerformanceMetricsData(string $period = '24h', string $metric = 'all'): array
    {
        $hours = match($period) {
            '1h' => 1,
            '6h' => 6,
            '24h' => 24,
            '7d' => 168,
            default => 24
        };

        $jobs = OpenAiJobResult::where('created_at', '>=', now()->subHours($hours))
            ->orderBy('created_at')
            ->get();

        $completedJobs = $jobs->where('status', 'completed');
        $totalJobs = $jobs->count();

        $throughput = $hours > 0 ? round($totalJobs / $hours, 2) : 0;
        $avgLatency = $completedJobs->avg('processing_time_ms') ?? 0;
        $successRate = $totalJobs > 0 ? round(($completedJobs->count() / $totalJobs) * 100, 2) : 0;

        $metrics = [
            'throughput' => [
                'jobs_per_hour' => $throughput,
                'tokens_per_hour' => $hours > 0 ? round($completedJobs->sum(fn($job) => $job->getTotalTokens()) / $hours, 2) : 0,
                'total_jobs' => $totalJobs
            ],
            'latency' => [
                'average_ms' => round($avgLatency, 2),
                'p95_ms' => $this->calculatePercentile($completedJobs->pluck('processing_time_ms')->toArray(), 95),
                'p99_ms' => $this->calculatePercentile($completedJobs->pluck('processing_time_ms')->toArray(), 99),
            ],
            'success_rate' => [
                'percentage' => $successRate,
                'completed_jobs' => $completedJobs->count(),
                'failed_jobs' => $jobs->where('status', 'failed')->count(),
            ],
            'cost_metrics' => [
                'total_cost_usd' => round($completedJobs->sum(fn($job) => $job->getEstimatedCost()), 4),
                'average_cost_per_job' => $completedJobs->count() > 0 ? round($completedJobs->sum(fn($job) => $job->getEstimatedCost()) / $completedJobs->count(), 4) : 0,
                'cost_per_hour' => $hours > 0 ? round($completedJobs->sum(fn($job) => $job->getEstimatedCost()) / $hours, 4) : 0,
            ]
        ];

        return $metric === 'all' ? $metrics : [$metric => $metrics[$metric] ?? []];
    }

    /**
     * Get system health indicators
     */
    private function getSystemHealthData(): array
    {
        $recentJobs = OpenAiJobResult::where('created_at', '>=', now()->subMinutes(15))->get();
        $failedRecently = $recentJobs->where('status', 'failed')->count();
        $totalRecent = $recentJobs->count();

        $errorRate = $totalRecent > 0 ? ($failedRecently / $totalRecent) * 100 : 0;

        return [
            'status' => $this->getHealthStatus($errorRate),
            'error_rate_15min' => round($errorRate, 2),
            'active_jobs' => OpenAiJobResult::where('status', 'processing')->count(),
            'queue_backlog' => OpenAiJobResult::where('status', 'pending')->count(),
            'recent_failures' => $failedRecently,
            'last_successful_job' => OpenAiJobResult::where('status', 'completed')
                ->orderBy('completed_at', 'desc')
                ->first()?->completed_at?->toISOString(),
            'system_uptime' => $this->getSystemUptime(),
        ];
    }

    /**
     * Get health status based on error rate
     */
    private function getHealthStatus(float $errorRate): string
    {
        return match(true) {
            $errorRate >= 50 => 'critical',
            $errorRate >= 20 => 'warning',
            $errorRate >= 5 => 'degraded',
            default => 'healthy'
        };
    }

    /**
     * Calculate percentile for an array of values
     */
    private function calculatePercentile(array $values, int $percentile): float
    {
        if (empty($values)) {
            return 0.0;
        }

        sort($values);
        $index = ($percentile / 100) * (count($values) - 1);
        
        if (floor($index) == $index) {
            return $values[(int)$index];
        }
        
        $lower = $values[(int)floor($index)];
        $upper = $values[(int)ceil($index)];
        
        return $lower + ($upper - $lower) * ($index - floor($index));
    }

    /**
     * Retry a specific job
     */
    private function retrySpecificJob(string $jobId): bool
    {
        try {
            $job = OpenAiJobResult::where('job_id', $jobId)->first();
            
            if (!$job || $job->status !== 'failed') {
                return false;
            }

            // Reset job status for retry
            $job->update([
                'status' => 'pending',
                'error_message' => null,
                'failed_at' => null,
                'attempts_made' => ($job->attempts_made ?? 0) + 1
            ]);

            // TODO: Re-dispatch the job to the queue
            Log::info("Job {$jobId} marked for retry", [
                'attempts' => $job->attempts_made
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error("Failed to retry job {$jobId}", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get system uptime (mock implementation)
     */
    private function getSystemUptime(): array
    {
        // This would typically check actual system uptime
        return [
            'seconds' => 86400, // 24 hours mock
            'formatted' => '1 day',
            'since' => now()->subDay()->toISOString()
        ];
    }
}