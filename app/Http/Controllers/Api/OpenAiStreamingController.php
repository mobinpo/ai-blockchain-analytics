<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\OptimizedOpenAiStreamingJob;
use App\Models\OpenAiJobResult;
use App\Models\Analysis;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * OpenAI Streaming Job Management Controller
 * 
 * Provides endpoints for:
 * - Starting streaming jobs
 * - Monitoring job progress
 * - Retrieving results
 * - Managing job lifecycle
 */
final class OpenAiStreamingController extends Controller
{
    /**
     * Start a new OpenAI streaming job
     */
    public function startStreamingJob(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'prompt' => 'required|string|min:10|max:8000',
            'job_type' => 'required|string|in:security_analysis,gas_analysis,quality_analysis,sentiment_analysis',
            'priority' => 'sometimes|string|in:urgent,high,normal,low',
            'analysis_id' => 'sometimes|integer|exists:analyses,id',
            'config' => 'sometimes|array',
            'config.model' => 'sometimes|string|in:gpt-4,gpt-4-turbo,gpt-3.5-turbo',
            'config.max_tokens' => 'sometimes|integer|min:100|max:4000',
            'config.temperature' => 'sometimes|numeric|min:0|max:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $validator->validated();
            $jobId = 'openai_' . Str::uuid();
            $userId = auth()->id();

            // Build job configuration
            $config = array_merge([
                'model' => 'gpt-4',
                'max_tokens' => 2000,
                'temperature' => 0.7,
                'stream' => true
            ], $data['config'] ?? []);

            // Build metadata
            $metadata = [
                'created_via' => 'api',
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip(),
                'timestamp' => now()->toISOString()
            ];

            // Dispatch the optimized streaming job
            OptimizedOpenAiStreamingJob::dispatch(
                prompt: $data['prompt'],
                jobId: $jobId,
                config: $config,
                metadata: $metadata,
                jobType: $data['job_type'],
                userId: $userId,
                analysisId: $data['analysis_id'] ?? null,
                priority: $data['priority'] ?? 'normal'
            );

            Log::info('🚀 OpenAI streaming job dispatched', [
                'job_id' => $jobId,
                'job_type' => $data['job_type'],
                'user_id' => $userId,
                'analysis_id' => $data['analysis_id'] ?? null,
                'priority' => $data['priority'] ?? 'normal'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Streaming job started successfully',
                'data' => [
                    'job_id' => $jobId,
                    'job_type' => $data['job_type'],
                    'priority' => $data['priority'] ?? 'normal',
                    'status' => 'queued',
                    'estimated_duration_seconds' => $this->estimateJobDuration($data),
                    'monitoring_endpoints' => [
                        'status' => route('api.openai-streaming.status', ['jobId' => $jobId]),
                        'stream' => route('api.openai-streaming.stream', ['jobId' => $jobId]),
                        'results' => route('api.openai-streaming.results', ['jobId' => $jobId])
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to start OpenAI streaming job', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to start streaming job',
                'error' => app()->isProduction() ? 'Internal server error' : $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get streaming job status and progress
     */
    public function getJobStatus(string $jobId): JsonResponse
    {
        try {
            // Get job result from database
            $jobResult = OpenAiJobResult::where('job_id', $jobId)->first();
            
            if (!$jobResult) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            // Get real-time streaming state from Redis
            $streamingState = $this->getStreamingState($jobId);
            
            // Calculate progress and metrics
            $progress = $this->calculateJobProgress($jobResult, $streamingState);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'job_id' => $jobId,
                    'status' => $jobResult->status,
                    'job_type' => $jobResult->job_type,
                    'priority' => $jobResult->config['priority'] ?? 'normal',
                    'progress' => $progress,
                    'streaming_stats' => $streamingState,
                    'performance_metrics' => $jobResult->getStreamingMetrics(),
                    'created_at' => $jobResult->created_at?->toISOString(),
                    'started_at' => $jobResult->started_at?->toISOString(),
                    'completed_at' => $jobResult->completed_at?->toISOString(),
                    'estimated_completion_at' => $this->estimateCompletionTime($jobResult, $progress)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get job status', [
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve job status'
            ], 500);
        }
    }

    /**
     * Get real-time streaming data
     */
    public function getStreamingData(string $jobId): JsonResponse
    {
        try {
            $streamingState = $this->getStreamingState($jobId);
            
            if (!$streamingState) {
                return response()->json([
                    'success' => false,
                    'message' => 'No streaming data available for this job'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'job_id' => $jobId,
                    'streaming_active' => $streamingState['status'] === 'streaming',
                    'tokens_streamed' => $streamingState['tokens_streamed'],
                    'response_content' => $streamingState['response_content'],
                    'progress_percentage' => $streamingState['progress_percentage'],
                    'last_activity' => $streamingState['last_activity'],
                    'estimated_total_tokens' => $streamingState['estimated_total_tokens'],
                    'real_time_metrics' => [
                        'tokens_per_second' => $this->calculateCurrentTokensPerSecond($streamingState),
                        'estimated_time_remaining' => $this->estimateTimeRemaining($streamingState),
                        'response_size_bytes' => strlen($streamingState['response_content'])
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get streaming data', [
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve streaming data'
            ], 500);
        }
    }

    /**
     * Get job results
     */
    public function getJobResults(string $jobId): JsonResponse
    {
        try {
            $jobResult = OpenAiJobResult::where('job_id', $jobId)->first();
            
            if (!$jobResult) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            if (!$jobResult->isCompleted()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job is not yet completed',
                    'current_status' => $jobResult->status
                ], 409);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'job_id' => $jobId,
                    'job_type' => $jobResult->job_type,
                    'status' => $jobResult->status,
                    'response' => $jobResult->response,
                    'parsed_response' => $jobResult->parsed_response,
                    'token_usage' => $jobResult->token_usage,
                    'streaming_stats' => $jobResult->streaming_stats,
                    'performance_metrics' => $jobResult->getStreamingMetrics(),
                    'quality_metrics' => [
                        'response_length' => strlen($jobResult->response ?? ''),
                        'estimated_cost_usd' => $jobResult->getEstimatedCost(),
                        'processing_duration_seconds' => $jobResult->getProcessingDurationSeconds(),
                        'tokens_per_second' => $jobResult->getTokensPerSecond()
                    ],
                    'completed_at' => $jobResult->completed_at?->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get job results', [
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve job results'
            ], 500);
        }
    }

    /**
     * Cancel a running job
     */
    public function cancelJob(string $jobId): JsonResponse
    {
        try {
            $jobResult = OpenAiJobResult::where('job_id', $jobId)->first();
            
            if (!$jobResult) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            if ($jobResult->isCompleted() || $jobResult->hasFailed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot cancel completed or failed job',
                    'current_status' => $jobResult->status
                ], 409);
            }

            // Update job status
            $jobResult->update([
                'status' => 'cancelled',
                'completed_at' => now(),
                'metadata' => array_merge($jobResult->metadata, [
                    'cancelled_at' => now()->toISOString(),
                    'cancelled_by' => auth()->id()
                ])
            ]);

            // Clean up streaming state
            Redis::del("openai_stream_{$jobId}");
            
            Log::info('OpenAI streaming job cancelled', [
                'job_id' => $jobId,
                'cancelled_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Job cancelled successfully',
                'data' => [
                    'job_id' => $jobId,
                    'status' => 'cancelled',
                    'cancelled_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to cancel job', [
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel job'
            ], 500);
        }
    }

    /**
     * List user's streaming jobs
     */
    public function listJobs(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'sometimes|string|in:pending,processing,completed,failed,cancelled',
                'job_type' => 'sometimes|string|in:security_analysis,gas_analysis,quality_analysis,sentiment_analysis',
                'limit' => 'sometimes|integer|min:1|max:100',
                'page' => 'sometimes|integer|min:1'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = OpenAiJobResult::where('user_id', auth()->id())
                ->orderBy('created_at', 'desc');

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('job_type')) {
                $query->where('job_type', $request->job_type);
            }

            $limit = $request->get('limit', 20);
            $jobs = $query->paginate($limit);

            return response()->json([
                'success' => true,
                'data' => [
                    'jobs' => $jobs->items(),
                    'pagination' => [
                        'current_page' => $jobs->currentPage(),
                        'total_pages' => $jobs->lastPage(),
                        'total_items' => $jobs->total(),
                        'per_page' => $jobs->perPage()
                    ],
                    'summary' => [
                        'total_jobs' => $jobs->total(),
                        'status_breakdown' => $this->getStatusBreakdown(),
                        'recent_activity' => $this->getRecentActivity()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to list jobs', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve jobs'
            ], 500);
        }
    }

    /**
     * Get streaming job analytics and metrics
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'period' => 'sometimes|string|in:hour,day,week,month',
                'job_type' => 'sometimes|string|in:security_analysis,gas_analysis,quality_analysis,sentiment_analysis'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $period = $request->get('period', 'day');
            $jobType = $request->get('job_type');

            $analytics = $this->calculateAnalytics($period, $jobType);

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => $period,
                    'job_type' => $jobType,
                    'analytics' => $analytics,
                    'generated_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get analytics', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve analytics'
            ], 500);
        }
    }

    /**
     * Get streaming state from Redis
     */
    private function getStreamingState(string $jobId): ?array
    {
        $cacheKey = "openai_stream_{$jobId}";
        $data = Redis::get($cacheKey);
        
        return $data ? json_decode($data, true) : null;
    }

    /**
     * Calculate job progress percentage
     */
    private function calculateJobProgress(OpenAiJobResult $jobResult, ?array $streamingState): array
    {
        if ($jobResult->isCompleted()) {
            return [
                'percentage' => 100,
                'status' => 'completed',
                'estimated_remaining_seconds' => 0
            ];
        }

        if ($jobResult->hasFailed()) {
            return [
                'percentage' => 0,
                'status' => 'failed',
                'estimated_remaining_seconds' => 0
            ];
        }

        if (!$streamingState) {
            return [
                'percentage' => 0,
                'status' => 'queued',
                'estimated_remaining_seconds' => null
            ];
        }

        return [
            'percentage' => $streamingState['progress_percentage'] ?? 0,
            'status' => $streamingState['status'] ?? 'unknown',
            'estimated_remaining_seconds' => $this->estimateTimeRemaining($streamingState)
        ];
    }

    /**
     * Estimate job completion time
     */
    private function estimateCompletionTime(OpenAiJobResult $jobResult, array $progress): ?string
    {
        if ($jobResult->isCompleted() || $jobResult->hasFailed()) {
            return null;
        }

        $remainingSeconds = $progress['estimated_remaining_seconds'] ?? null;
        
        return $remainingSeconds ? now()->addSeconds($remainingSeconds)->toISOString() : null;
    }

    /**
     * Calculate current tokens per second
     */
    private function calculateCurrentTokensPerSecond(array $streamingState): float
    {
        $startTime = $streamingState['started_at'] ?? null;
        $tokens = $streamingState['tokens_streamed'] ?? 0;
        
        if (!$startTime || $tokens === 0) {
            return 0.0;
        }

        $elapsed = now()->diffInSeconds($startTime);
        return $elapsed > 0 ? round($tokens / $elapsed, 2) : 0.0;
    }

    /**
     * Estimate time remaining for job
     */
    private function estimateTimeRemaining(array $streamingState): ?int
    {
        $progress = $streamingState['progress_percentage'] ?? 0;
        $tokensStreamed = $streamingState['tokens_streamed'] ?? 0;
        $estimatedTotal = $streamingState['estimated_total_tokens'] ?? 1000;
        
        if ($progress >= 100 || $tokensStreamed >= $estimatedTotal) {
            return 0;
        }

        $tokensPerSecond = $this->calculateCurrentTokensPerSecond($streamingState);
        if ($tokensPerSecond <= 0) {
            return null;
        }

        $remainingTokens = $estimatedTotal - $tokensStreamed;
        return max(0, round($remainingTokens / $tokensPerSecond));
    }

    /**
     * Estimate job duration based on input data
     */
    private function estimateJobDuration(array $data): int
    {
        $baseTime = 30; // 30 seconds base
        $promptLength = strlen($data['prompt']);
        $maxTokens = $data['config']['max_tokens'] ?? 2000;
        
        // Estimate based on prompt length and max tokens
        $lengthFactor = round($promptLength / 100) * 2; // 2 seconds per 100 chars
        $tokenFactor = round($maxTokens / 100) * 3; // 3 seconds per 100 tokens
        
        return $baseTime + $lengthFactor + $tokenFactor;
    }

    /**
     * Get status breakdown for user's jobs
     */
    private function getStatusBreakdown(): array
    {
        return OpenAiJobResult::where('user_id', auth()->id())
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    /**
     * Get recent activity summary
     */
    private function getRecentActivity(): array
    {
        $recent = OpenAiJobResult::where('user_id', auth()->id())
            ->where('created_at', '>=', now()->subHours(24))
            ->get();

        return [
            'jobs_last_24h' => $recent->count(),
            'completed_last_24h' => $recent->where('status', 'completed')->count(),
            'tokens_consumed_last_24h' => $recent->sum(fn($job) => $job->getTotalTokens()),
            'estimated_cost_last_24h' => $recent->sum(fn($job) => $job->getEstimatedCost())
        ];
    }

    /**
     * Calculate comprehensive analytics
     */
    private function calculateAnalytics(string $period, ?string $jobType): array
    {
        $startDate = match($period) {
            'hour' => now()->subHour(),
            'day' => now()->subDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            default => now()->subDay()
        };

        $query = OpenAiJobResult::where('user_id', auth()->id())
            ->where('created_at', '>=', $startDate);

        if ($jobType) {
            $query->where('job_type', $jobType);
        }

        $jobs = $query->get();

        return [
            'total_jobs' => $jobs->count(),
            'completed_jobs' => $jobs->where('status', 'completed')->count(),
            'failed_jobs' => $jobs->where('status', 'failed')->count(),
            'total_tokens' => $jobs->sum(fn($job) => $job->getTotalTokens()),
            'total_cost_usd' => $jobs->sum(fn($job) => $job->getEstimatedCost()),
            'average_processing_time_seconds' => $jobs->avg(fn($job) => $job->getProcessingDurationSeconds()),
            'average_tokens_per_second' => $jobs->avg(fn($job) => $job->getTokensPerSecond()),
            'success_rate' => $jobs->count() > 0 ? 
                round(($jobs->where('status', 'completed')->count() / $jobs->count()) * 100, 2) : 0,
            'job_type_breakdown' => $jobs->groupBy('job_type')->map->count()->toArray()
        ];
    }
}