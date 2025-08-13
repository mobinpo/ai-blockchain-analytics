<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\OpenAiStreamingJob;
use App\Models\OpenAiJobResult;
use App\Services\OpenAiStreamService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class OpenAiJobController extends Controller
{
    /**
     * Create and dispatch a new OpenAI streaming job
     */
    public function createJob(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => 'required|string|min:10|max:50000',
            'job_type' => 'required|string|in:security_analysis,sentiment_analysis,code_analysis,general',
            'config' => 'sometimes|array',
            'config.model' => 'sometimes|string|in:gpt-4,gpt-3.5-turbo,gpt-4-turbo',
            'config.max_tokens' => 'sometimes|integer|min:10|max:4000',
            'config.temperature' => 'sometimes|numeric|min:0|max:2',
            'config.priority' => 'sometimes|string|in:urgent,high,normal,low',
            'config.system_prompt' => 'sometimes|string|max:5000',
            'config.response_format' => 'sometimes|string|in:json,text',
            'metadata' => 'sometimes|array',
            'async' => 'sometimes|boolean'
        ]);

        try {
            $jobId = 'api_' . Str::random(12);
            $config = $this->buildJobConfig($validated['config'] ?? []);
            $async = $validated['async'] ?? true;

            Log::info('Creating OpenAI job via API', [
                'job_id' => $jobId,
                'job_type' => $validated['job_type'],
                'user_id' => $request->user()?->id,
                'async' => $async,
                'config' => $config
            ]);

            if ($async) {
                // Dispatch asynchronous job
                $job = new OpenAiStreamingJob(
                    prompt: $validated['prompt'],
                    jobId: $jobId,
                    config: $config,
                    metadata: array_merge($validated['metadata'] ?? [], [
                        'created_via' => 'api',
                        'user_agent' => $request->header('User-Agent'),
                        'ip_address' => $request->ip()
                    ]),
                    jobType: $validated['job_type'],
                    userId: $request->user()?->id
                );

                dispatch($job);

                return response()->json([
                    'success' => true,
                    'job_id' => $jobId,
                    'status' => 'queued',
                    'queue' => $this->getQueueName($validated['job_type'], $config['priority']),
                    'estimated_completion_time' => now()->addMinutes($this->estimateCompletionTime($config))->toISOString(),
                    'polling_endpoints' => [
                        'status' => route('openai-jobs.status', ['jobId' => $jobId]),
                        'stream' => route('openai-jobs.stream', ['jobId' => $jobId]),
                        'result' => route('openai-jobs.result', ['jobId' => $jobId])
                    ],
                    'config' => $config
                ], 202); // 202 Accepted

            } else {
                // Execute synchronously
                return $this->executeSynchronous($jobId, $validated, $config, $request);
            }

        } catch (\Exception $e) {
            Log::error('Failed to create OpenAI job', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to create job',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get job status and progress
     */
    public function getJobStatus(string $jobId): JsonResponse
    {
        try {
            // Get job result from database
            $jobResult = OpenAiJobResult::where('job_id', $jobId)->first();
            
            if (!$jobResult) {
                return response()->json([
                    'success' => false,
                    'error' => 'Job not found'
                ], 404);
            }

            // Get streaming status from cache
            $streamStatus = Cache::get("openai_stream_{$jobId}");

            $response = [
                'success' => true,
                'job_id' => $jobId,
                'status' => $jobResult->status,
                'job_type' => $jobResult->job_type,
                'created_at' => $jobResult->created_at->toISOString(),
                'started_at' => $jobResult->started_at?->toISOString(),
                'completed_at' => $jobResult->completed_at?->toISOString(),
                'failed_at' => $jobResult->failed_at?->toISOString(),
                'processing_time_ms' => $jobResult->processing_time_ms,
                'error_message' => $jobResult->error_message
            ];

            // Add streaming information if available
            if ($streamStatus) {
                $response['streaming'] = [
                    'status' => $streamStatus['status'] ?? 'unknown',
                    'tokens_received' => $streamStatus['tokens_received'] ?? 0,
                    'estimated_total_tokens' => $streamStatus['estimated_total_tokens'] ?? 0,
                    'progress_percentage' => $this->calculateProgress($streamStatus),
                    'last_activity' => $streamStatus['updated_at'] ?? null,
                    'tokens_per_second' => $this->calculateTokensPerSecond($streamStatus)
                ];
            }

            // Add completion information if job is done
            if ($jobResult->isCompleted()) {
                $response['completion'] = [
                    'total_tokens' => $jobResult->getTotalTokens(),
                    'estimated_cost_usd' => $jobResult->getEstimatedCost(),
                    'success_rate' => $jobResult->getSuccessRate(),
                    'response_size_bytes' => strlen($jobResult->response ?? ''),
                    'has_structured_response' => !empty($jobResult->parsed_response)
                ];
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('Failed to get job status', [
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to get job status',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get streaming updates for a job
     */
    public function getStreamingUpdates(string $jobId): JsonResponse
    {
        try {
            $streamStatus = Cache::get("openai_stream_{$jobId}");
            
            if (!$streamStatus) {
                return response()->json([
                    'success' => false,
                    'error' => 'No streaming data available'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'job_id' => $jobId,
                'streaming_data' => [
                    'status' => $streamStatus['status'] ?? 'unknown',
                    'tokens_received' => $streamStatus['tokens_received'] ?? 0,
                    'content' => $streamStatus['content'] ?? '',
                    'last_token' => $streamStatus['last_token'] ?? null,
                    'progress_percentage' => $this->calculateProgress($streamStatus),
                    'started_at' => $streamStatus['started_at'] ?? null,
                    'last_activity' => $streamStatus['updated_at'] ?? null,
                    'tokens_per_second' => $this->calculateTokensPerSecond($streamStatus),
                    'estimated_completion' => $this->estimateCompletion($streamStatus)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get streaming updates',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get job result
     */
    public function getJobResult(string $jobId): JsonResponse
    {
        try {
            $jobResult = OpenAiJobResult::where('job_id', $jobId)->first();
            
            if (!$jobResult) {
                return response()->json([
                    'success' => false,
                    'error' => 'Job not found'
                ], 404);
            }

            if (!$jobResult->isCompleted()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Job not completed yet',
                    'status' => $jobResult->status
                ], 409); // 409 Conflict
            }

            return response()->json([
                'success' => true,
                'job_id' => $jobId,
                'result' => [
                    'response' => $jobResult->response,
                    'parsed_response' => $jobResult->parsed_response,
                    'summary' => $jobResult->getResponseSummary(),
                    'streaming_metrics' => $jobResult->getStreamingMetrics(),
                    'analysis_data' => $jobResult->toAnalysisArray()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get job result',
                'message' => $e->getMessage()
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
                    'error' => 'Job not found'
                ], 404);
            }

            if (!$jobResult->isProcessing()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Job cannot be cancelled',
                    'status' => $jobResult->status
                ], 409);
            }

            // Update job status
            $jobResult->update([
                'status' => 'failed',
                'error_message' => 'Job cancelled by user',
                'failed_at' => now()
            ]);

            // Clean up streaming cache
            Cache::forget("openai_stream_{$jobId}");

            Log::info('OpenAI job cancelled', [
                'job_id' => $jobId,
                'cancelled_by_user' => true
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Job cancelled successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to cancel job',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List jobs for the current user
     */
    public function listJobs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|string|in:pending,processing,completed,failed',
            'job_type' => 'sometimes|string|in:security_analysis,sentiment_analysis,code_analysis,general',
            'limit' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1'
        ]);

        try {
            $query = OpenAiJobResult::query();

            // Filter by user if authenticated
            if ($request->user()) {
                $query->where('user_id', $request->user()->id);
            }

            // Apply filters
            if (!empty($validated['status'])) {
                $query->where('status', $validated['status']);
            }

            if (!empty($validated['job_type'])) {
                $query->where('job_type', $validated['job_type']);
            }

            // Order by most recent first
            $query->orderBy('created_at', 'desc');

            // Paginate
            $limit = $validated['limit'] ?? 20;
            $jobs = $query->paginate($limit);

            $jobsData = $jobs->getCollection()->map(function ($job) {
                return [
                    'job_id' => $job->job_id,
                    'job_type' => $job->job_type,
                    'status' => $job->status,
                    'created_at' => $job->created_at->toISOString(),
                    'completed_at' => $job->completed_at?->toISOString(),
                    'processing_time_ms' => $job->processing_time_ms,
                    'total_tokens' => $job->getTotalTokens(),
                    'estimated_cost_usd' => $job->getEstimatedCost(),
                    'success_rate' => $job->getSuccessRate(),
                    'model' => $job->getModel(),
                    'error_message' => $job->error_message
                ];
            });

            return response()->json([
                'success' => true,
                'jobs' => $jobsData,
                'pagination' => [
                    'current_page' => $jobs->currentPage(),
                    'last_page' => $jobs->lastPage(),
                    'per_page' => $jobs->perPage(),
                    'total' => $jobs->total()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to list jobs',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Execute job synchronously
     */
    private function executeSynchronous(string $jobId, array $validated, array $config, Request $request): JsonResponse
    {
        $startTime = microtime(true);

        try {
            // Create job record
            $jobResult = OpenAiJobResult::createForJob(
                $jobId,
                $validated['job_type'],
                $validated['prompt'],
                $config,
                $request->user()?->id
            );

            // Create streaming service
            $streamService = new OpenAiStreamService(
                $config['model'],
                $config['max_tokens'],
                $config['temperature']
            );

            // Execute streaming
            $response = $streamService->streamSecurityAnalysis(
                $validated['prompt'],
                $jobId,
                ['system_prompt' => $config['system_prompt'] ?? '']
            );

            $endTime = microtime(true);
            $processingTimeMs = round(($endTime - $startTime) * 1000);

            // Update job result
            $streamStats = $streamService->getStreamStatus($jobId);
            $parsedResponse = json_decode($response, true);

            $jobResult->update([
                'status' => 'completed',
                'response' => $response,
                'parsed_response' => $parsedResponse,
                'processing_time_ms' => $processingTimeMs,
                'streaming_stats' => $streamStats,
                'completed_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'job_id' => $jobId,
                'status' => 'completed',
                'result' => [
                    'response' => $response,
                    'parsed_response' => $parsedResponse,
                    'processing_time_ms' => $processingTimeMs,
                    'streaming_stats' => $streamStats
                ]
            ]);

        } catch (\Exception $e) {
            $processingTimeMs = round((microtime(true) - $startTime) * 1000);

            // Update job with error
            if (isset($jobResult)) {
                $jobResult->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'processing_time_ms' => $processingTimeMs,
                    'failed_at' => now()
                ]);
            }

            throw $e;
        }
    }

    /**
     * Build job configuration with defaults
     */
    private function buildJobConfig(array $config): array
    {
        return [
            'model' => $config['model'] ?? 'gpt-4',
            'max_tokens' => $config['max_tokens'] ?? 2000,
            'temperature' => $config['temperature'] ?? 0.7,
            'priority' => $config['priority'] ?? 'normal',
            'system_prompt' => $config['system_prompt'] ?? '',
            'response_format' => $config['response_format'] ?? 'json'
        ];
    }

    /**
     * Get queue name based on job type and priority
     */
    private function getQueueName(string $jobType, string $priority): string
    {
        $base = "openai-{$jobType}";
        
        return match($priority) {
            'urgent' => "{$base}-urgent",
            'high' => "{$base}-high",
            'low' => "{$base}-low",
            default => $base
        };
    }

    /**
     * Estimate completion time in minutes
     */
    private function estimateCompletionTime(array $config): int
    {
        $baseTime = 2; // 2 minutes base
        $tokenMultiplier = ceil($config['max_tokens'] / 500); // +1 minute per 500 tokens
        $modelMultiplier = $config['model'] === 'gpt-4' ? 2 : 1;
        
        return $baseTime + $tokenMultiplier + $modelMultiplier;
    }

    /**
     * Calculate progress percentage
     */
    private function calculateProgress(array $streamStatus): float
    {
        $tokensReceived = $streamStatus['tokens_received'] ?? 0;
        $estimatedTotal = $streamStatus['estimated_total_tokens'] ?? 2000;
        
        return min(100, round(($tokensReceived / $estimatedTotal) * 100, 2));
    }

    /**
     * Calculate tokens per second
     */
    private function calculateTokensPerSecond(array $streamStatus): float
    {
        if (empty($streamStatus['processing_time_ms']) || empty($streamStatus['tokens_received'])) {
            return 0.0;
        }

        $processingTimeSeconds = $streamStatus['processing_time_ms'] / 1000;
        return round($streamStatus['tokens_received'] / $processingTimeSeconds, 2);
    }

    /**
     * Estimate completion time based on current progress
     */
    private function estimateCompletion(array $streamStatus): ?string
    {
        $progress = $this->calculateProgress($streamStatus);
        
        if ($progress <= 0 || $progress >= 100) {
            return null;
        }

        $tokensPerSecond = $this->calculateTokensPerSecond($streamStatus);
        if ($tokensPerSecond <= 0) {
            return null;
        }

        $remainingTokens = ($streamStatus['estimated_total_tokens'] ?? 2000) - ($streamStatus['tokens_received'] ?? 0);
        $estimatedSecondsRemaining = $remainingTokens / $tokensPerSecond;

        return now()->addSeconds($estimatedSecondsRemaining)->toISOString();
    }
}