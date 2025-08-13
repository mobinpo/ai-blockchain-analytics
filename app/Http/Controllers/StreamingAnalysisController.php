<?php

namespace App\Http\Controllers;

use App\Models\Analysis;
use App\Models\Project;
use App\Jobs\StreamingAnalysisJob;
use App\Services\OpenAiStreamService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class StreamingAnalysisController extends Controller
{
    private OpenAiStreamService $streamService;

    public function __construct(OpenAiStreamService $streamService)
    {
        $this->streamService = $streamService;
    }

    /**
     * Start a new streaming analysis
     */
    public function start(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'contract_address' => 'required|string|size:42|regex:/^0x[a-fA-F0-9]{40}$/',
            'network' => 'required|string|in:ethereum,bsc,polygon,arbitrum,optimism,avalanche,fantom',
            'project_id' => 'sometimes|exists:projects,id',
            'analysis_type' => 'sometimes|string|in:security,gas,code_quality,comprehensive',
            'model' => 'sometimes|string|in:gpt-4,gpt-4-turbo,gpt-3.5-turbo',
            'max_tokens' => 'sometimes|integer|min:100|max:4000',
            'temperature' => 'sometimes|numeric|min:0|max:2',
            'priority' => 'sometimes|string|in:low,normal,high,critical'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $contractAddress = Str::lower($request->input('contract_address'));
            $network = $request->input('network');
            $projectId = $request->input('project_id');

            // Create analysis record
            $analysis = Analysis::create([
                'project_id' => $projectId,
                'contract_address' => $contractAddress,
                'network' => $network,
                'analysis_type' => $request->input('analysis_type', 'security'),
                'status' => 'queued',
                'parameters' => [
                    'model' => $request->input('model', 'gpt-4'),
                    'max_tokens' => $request->input('max_tokens', 2000),
                    'temperature' => $request->input('temperature', 0.7),
                    'streaming_enabled' => true
                ],
                'metadata' => [
                    'queued_at' => now()->toISOString(),
                    'user_id' => auth()->id(),
                    'request_ip' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]
            ]);

            // Prepare job configuration
            $analysisConfig = [
                'type' => $request->input('analysis_type', 'security'),
                'model' => $request->input('model', 'gpt-4'),
                'max_tokens' => $request->input('max_tokens', 2000),
                'temperature' => $request->input('temperature', 0.7)
            ];

            // Dispatch streaming analysis job
            $job = new StreamingAnalysisJob(
                $contractAddress,
                $network,
                $analysis->id,
                $analysisConfig
            );

            // Set job priority
            $priority = match($request->input('priority', 'normal')) {
                'critical' => 'high',
                'high' => 'high', 
                'normal' => 'default',
                'low' => 'low',
                default => 'default'
            };

            $job->onQueue('streaming-analysis')->priority($priority);

            Queue::push($job);

            Log::info("Streaming analysis started", [
                'analysis_id' => $analysis->id,
                'contract_address' => $contractAddress,
                'network' => $network,
                'job_dispatched' => true
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Streaming analysis started successfully',
                'data' => [
                    'analysis_id' => $analysis->id,
                    'contract_address' => $contractAddress,
                    'network' => $network,
                    'status' => 'queued',
                    'streaming_url' => route('streaming-analysis.status', $analysis->id),
                    'websocket_channel' => "analysis.{$analysis->id}",
                    'estimated_duration' => '2-5 minutes'
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error("Failed to start streaming analysis", [
                'contract_address' => $request->input('contract_address'),
                'network' => $request->input('network'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to start analysis',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get streaming analysis status
     */
    public function status(int $analysisId): JsonResponse
    {
        try {
            $analysis = Analysis::findOrFail($analysisId);

            // Get streaming status if job is running
            $streamingStatus = null;
            if ($analysis->job_id && in_array($analysis->status, ['processing', 'queued'])) {
                $streamingStatus = $this->streamService->getStreamStatus($analysis->job_id);
            }

            $response = [
                'success' => true,
                'data' => [
                    'analysis_id' => $analysis->id,
                    'contract_address' => $analysis->contract_address,
                    'network' => $analysis->network,
                    'status' => $analysis->status,
                    'progress' => $this->calculateProgress($analysis, $streamingStatus),
                    'created_at' => $analysis->created_at->toISOString(),
                    'updated_at' => $analysis->updated_at->toISOString(),
                ]
            ];

            // Add timing information
            if ($analysis->started_at) {
                $response['data']['started_at'] = $analysis->started_at->toISOString();
                
                if ($analysis->status === 'processing') {
                    $response['data']['processing_duration'] = now()->diffInSeconds($analysis->started_at) . 's';
                }
            }

            if ($analysis->completed_at) {
                $response['data']['completed_at'] = $analysis->completed_at->toISOString();
                $response['data']['total_duration'] = $analysis->completed_at->diffInSeconds($analysis->started_at) . 's';
            }

            // Add streaming information
            if ($streamingStatus) {
                $response['data']['streaming'] = [
                    'status' => $streamingStatus['status'],
                    'tokens_received' => $streamingStatus['tokens_received'] ?? 0,
                    'current_content_length' => strlen($streamingStatus['content'] ?? ''),
                    'last_updated' => $streamingStatus['updated_at'] ?? null
                ];

                if (isset($streamingStatus['processing_time_ms'])) {
                    $response['data']['streaming']['processing_time_ms'] = $streamingStatus['processing_time_ms'];
                }
            }

            // Add results if completed
            if ($analysis->status === 'completed' && $analysis->result) {
                $response['data']['results'] = [
                    'findings_count' => count($analysis->result['findings'] ?? []),
                    'severity_breakdown' => $analysis->result['summary']['severity_breakdown'] ?? [],
                    'validation_success_rate' => $analysis->result['summary']['validation_summary']['success_rate'] ?? 0
                ];
            }

            // Add error information if failed
            if ($analysis->status === 'failed') {
                $response['data']['error'] = [
                    'message' => $analysis->error_message,
                    'failed_at' => $analysis->failed_at?->toISOString()
                ];
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error("Failed to get streaming analysis status", [
                'analysis_id' => $analysisId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Analysis not found',
                'error' => config('app.debug') ? $e->getMessage() : 'Not found'
            ], 404);
        }
    }

    /**
     * Get streaming analysis results
     */
    public function results(int $analysisId): JsonResponse
    {
        try {
            $analysis = Analysis::with('contractAnalysis')->findOrFail($analysisId);

            if ($analysis->status !== 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Analysis not completed yet',
                    'current_status' => $analysis->status
                ], 202);
            }

            $results = [
                'success' => true,
                'data' => [
                    'analysis_id' => $analysis->id,
                    'contract_address' => $analysis->contract_address,
                    'network' => $analysis->network,
                    'analysis_type' => $analysis->analysis_type,
                    'status' => $analysis->status,
                    'completed_at' => $analysis->completed_at->toISOString(),
                    'processing_duration' => $analysis->completed_at->diffInSeconds($analysis->started_at) . 's',
                    'findings' => $analysis->result['findings'] ?? [],
                    'summary' => $analysis->result['summary'] ?? [],
                    'source_info' => $analysis->result['source_info'] ?? [],
                    'metadata' => $analysis->result['analysis_metadata'] ?? []
                ]
            ];

            // Add detailed contract analysis if available
            if ($analysis->contractAnalysis) {
                $contractAnalysis = $analysis->contractAnalysis;
                $results['data']['detailed_analysis'] = [
                    'main_contract_name' => $contractAnalysis->main_contract_name,
                    'compiler_version' => $contractAnalysis->compiler_version,
                    'optimization_enabled' => $contractAnalysis->optimization_enabled,
                    'findings_count' => $contractAnalysis->findings_count,
                    'severity_breakdown' => $contractAnalysis->severity_breakdown,
                    'validation_summary' => $contractAnalysis->validation_summary
                ];
            }

            return response()->json($results);

        } catch (\Exception $e) {
            Log::error("Failed to get streaming analysis results", [
                'analysis_id' => $analysisId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Analysis not found',
                'error' => config('app.debug') ? $e->getMessage() : 'Not found'
            ], 404);
        }
    }

    /**
     * Stream real-time analysis updates via Server-Sent Events
     */
    public function stream(int $analysisId): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->stream(function () use ($analysisId) {
            try {
                $analysis = Analysis::findOrFail($analysisId);
                
                // Send initial status
                echo "event: status\n";
                echo "data: " . json_encode([
                    'analysis_id' => $analysisId,
                    'status' => $analysis->status,
                    'message' => 'Connected to streaming endpoint'
                ]) . "\n\n";
                
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
                
                $lastTokenCount = 0;
                $lastStatus = $analysis->status;
                
                // Stream updates until analysis is complete
                while ($analysis->status === 'processing' || $analysis->status === 'queued') {
                    $analysis->refresh();
                    
                    // Check for status changes
                    if ($analysis->status !== $lastStatus) {
                        echo "event: status_change\n";
                        echo "data: " . json_encode([
                            'analysis_id' => $analysisId,
                            'old_status' => $lastStatus,
                            'new_status' => $analysis->status,
                            'timestamp' => now()->toISOString()
                        ]) . "\n\n";
                        
                        $lastStatus = $analysis->status;
                        
                        if (ob_get_level()) {
                            ob_flush();
                        }
                        flush();
                    }
                    
                    // Check for streaming updates
                    if ($analysis->job_id) {
                        $streamingStatus = $this->streamService->getStreamStatus($analysis->job_id);
                        
                        if ($streamingStatus && isset($streamingStatus['tokens_received'])) {
                            $currentTokenCount = $streamingStatus['tokens_received'];
                            
                            if ($currentTokenCount > $lastTokenCount) {
                                echo "event: token_update\n";
                                echo "data: " . json_encode([
                                    'analysis_id' => $analysisId,
                                    'tokens_received' => $currentTokenCount,
                                    'new_tokens' => $currentTokenCount - $lastTokenCount,
                                    'content_length' => strlen($streamingStatus['content'] ?? ''),
                                    'last_token' => $streamingStatus['last_token'] ?? '',
                                    'timestamp' => now()->toISOString()
                                ]) . "\n\n";
                                
                                $lastTokenCount = $currentTokenCount;
                                
                                if (ob_get_level()) {
                                    ob_flush();
                                }
                                flush();
                            }
                            
                            // Send progress update
                            $progress = $this->calculateProgress($analysis, $streamingStatus);
                            echo "event: progress\n";
                            echo "data: " . json_encode([
                                'analysis_id' => $analysisId,
                                'progress' => $progress,
                                'timestamp' => now()->toISOString()
                            ]) . "\n\n";
                            
                            if (ob_get_level()) {
                                ob_flush();
                            }
                            flush();
                        }
                    }
                    
                    // Prevent infinite loop if analysis gets stuck
                    if ($analysis->created_at->diffInMinutes(now()) > 15) {
                        echo "event: timeout\n";
                        echo "data: " . json_encode([
                            'analysis_id' => $analysisId,
                            'message' => 'Analysis timeout after 15 minutes',
                            'timestamp' => now()->toISOString()
                        ]) . "\n\n";
                        break;
                    }
                    
                    sleep(2); // Poll every 2 seconds
                }
                
                // Send final status
                $analysis->refresh();
                echo "event: complete\n";
                echo "data: " . json_encode([
                    'analysis_id' => $analysisId,
                    'final_status' => $analysis->status,
                    'findings_count' => $analysis->result ? count($analysis->result['findings'] ?? []) : 0,
                    'message' => $analysis->status === 'completed' ? 'Analysis completed successfully' : 'Analysis ended',
                    'timestamp' => now()->toISOString()
                ]) . "\n\n";
                
            } catch (\Exception $e) {
                echo "event: error\n";
                echo "data: " . json_encode([
                    'analysis_id' => $analysisId,
                    'error' => $e->getMessage(),
                    'timestamp' => now()->toISOString()
                ]) . "\n\n";
            }
            
            if (ob_get_level()) {
                ob_flush();
            }
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // Disable nginx buffering
        ]);
    }

    /**
     * Cancel a streaming analysis
     */
    public function cancel(int $analysisId): JsonResponse
    {
        try {
            $analysis = Analysis::findOrFail($analysisId);
            
            if (!in_array($analysis->status, ['queued', 'processing'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Analysis cannot be cancelled',
                    'current_status' => $analysis->status
                ], 400);
            }
            
            // Update analysis status
            $analysis->update([
                'status' => 'cancelled',
                'error_message' => 'Analysis cancelled by user',
                'failed_at' => now(),
                'metadata' => array_merge($analysis->metadata ?? [], [
                    'cancelled_at' => now()->toISOString(),
                    'cancelled_by' => auth()->id()
                ])
            ]);
            
            // Try to cancel the job if it's in the queue
            if ($analysis->job_id) {
                // This would require additional job tracking infrastructure
                Log::info("Analysis cancelled", [
                    'analysis_id' => $analysisId,
                    'job_id' => $analysis->job_id
                ]);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Analysis cancelled successfully',
                'data' => [
                    'analysis_id' => $analysisId,
                    'status' => 'cancelled',
                    'cancelled_at' => now()->toISOString()
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error("Failed to cancel analysis", [
                'analysis_id' => $analysisId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel analysis',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * List streaming analyses for authenticated user
     */
    public function list(Request $request): JsonResponse
    {
        try {
            $query = Analysis::query()
                ->where('parameters->streaming_enabled', true)
                ->orderBy('created_at', 'desc');
            
            // Filter by status if provided
            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }
            
            // Filter by network if provided
            if ($request->has('network')) {
                $query->where('network', $request->input('network'));
            }
            
            // Filter by analysis type if provided
            if ($request->has('analysis_type')) {
                $query->where('analysis_type', $request->input('analysis_type'));
            }
            
            $perPage = min($request->input('per_page', 20), 100);
            $analyses = $query->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => $analyses->items(),
                'pagination' => [
                    'current_page' => $analyses->currentPage(),
                    'per_page' => $analyses->perPage(),
                    'total' => $analyses->total(),
                    'last_page' => $analyses->lastPage()
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error("Failed to list streaming analyses", [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve analyses',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Calculate analysis progress based on status and streaming data
     */
    private function calculateProgress(Analysis $analysis, ?array $streamingStatus): array
    {
        $progress = [
            'percentage' => 0,
            'stage' => 'unknown',
            'message' => 'Analysis status unknown'
        ];

        switch ($analysis->status) {
            case 'queued':
                $progress = [
                    'percentage' => 5,
                    'stage' => 'queued',
                    'message' => 'Analysis queued for processing'
                ];
                break;

            case 'processing':
                if ($streamingStatus) {
                    $tokens = $streamingStatus['tokens_received'] ?? 0;
                    $estimatedMaxTokens = 2000;
                    $tokenProgress = min(($tokens / $estimatedMaxTokens) * 80, 80);
                    
                    $progress = [
                        'percentage' => 20 + $tokenProgress,
                        'stage' => 'streaming',
                        'message' => "Streaming analysis in progress ({$tokens} tokens received)",
                        'tokens_received' => $tokens
                    ];
                } else {
                    $progress = [
                        'percentage' => 20,
                        'stage' => 'processing',
                        'message' => 'Processing analysis request'
                    ];
                }
                break;

            case 'completed':
                $progress = [
                    'percentage' => 100,
                    'stage' => 'completed',
                    'message' => 'Analysis completed successfully'
                ];
                break;

            case 'failed':
                $progress = [
                    'percentage' => 0,
                    'stage' => 'failed',
                    'message' => 'Analysis failed: ' . ($analysis->error_message ?? 'Unknown error')
                ];
                break;

            case 'cancelled':
                $progress = [
                    'percentage' => 0,
                    'stage' => 'cancelled',
                    'message' => 'Analysis was cancelled'
                ];
                break;
        }

        return $progress;
    }
}