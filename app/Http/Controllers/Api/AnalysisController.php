<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeContractJob;
use App\Models\ContractAnalysis;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AnalysisController extends Controller
{
    /**
     * Start a new contract analysis
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'contract_address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/',
            'network' => 'required|string|in:ethereum,bsc,polygon,arbitrum,optimism,avalanche,fantom',
            'model' => 'sometimes|string|in:gpt-4,gpt-3.5-turbo',
            'focus_areas' => 'sometimes|array',
            'focus_areas.*' => 'string',
            'severity_threshold' => 'sometimes|string|in:CRITICAL,HIGH,MEDIUM,LOW,INFO'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $contractAddress = strtolower($data['contract_address']);
        $network = strtolower($data['network']);

        // Check for recent analysis
        $existingAnalysis = ContractAnalysis::forContract($contractAddress, $network)
            ->where('created_at', '>=', now()->subHours(1))
            ->whereIn('status', ['pending', 'processing', 'completed'])
            ->first();

        if ($existingAnalysis) {
            return response()->json([
                'success' => true,
                'message' => 'Analysis already exists or recently completed',
                'analysis' => $existingAnalysis->getAnalysisSummary()
            ]);
        }

        try {
            // Create new analysis record
            $analysis = ContractAnalysis::create([
                'contract_address' => $contractAddress,
                'network' => $network,
                'model' => $data['model'] ?? 'gpt-4',
                'analysis_options' => [
                    'focus_areas' => $data['focus_areas'] ?? [],
                    'severity_threshold' => $data['severity_threshold'] ?? 'LOW'
                ],
                'triggered_by' => 'api',
                'user_id' => auth()->id()
            ]);

            // Dispatch analysis job
            AnalyzeContractJob::dispatch(
                $contractAddress,
                $network,
                $analysis->id,
                $analysis->analysis_options
            );

            Log::info("Contract analysis initiated", [
                'analysis_id' => $analysis->id,
                'contract' => $contractAddress,
                'network' => $network,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Analysis started successfully',
                'analysis' => $analysis->getAnalysisSummary()
            ], 201);

        } catch (\Exception $e) {
            Log::error("Failed to start contract analysis", [
                'contract' => $contractAddress,
                'network' => $network,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to start analysis',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get analysis details
     */
    public function show(string $id): JsonResponse
    {
        try {
            $analysis = ContractAnalysis::findOrFail($id);

            return response()->json([
                'success' => true,
                'analysis' => [
                    'id' => $analysis->id,
                    'contract_address' => $analysis->contract_address,
                    'network' => $analysis->network,
                    'status' => $analysis->status,
                    'progress' => $analysis->progress,
                    'current_step' => $analysis->current_step,
                    'findings_count' => $analysis->findings_count,
                    'findings' => $analysis->findings,
                    'severity_counts' => $analysis->getSeverityCounts(),
                    'risk_score' => $analysis->getRiskScore(),
                    'categories' => $analysis->getUniqueCategories(),
                    'metadata' => $analysis->metadata,
                    'tokens_used' => $analysis->tokens_used,
                    'processing_time_ms' => $analysis->processing_time_ms,
                    'error_message' => $analysis->error_message,
                    'created_at' => $analysis->created_at,
                    'started_at' => $analysis->started_at,
                    'completed_at' => $analysis->completed_at,
                    'duration' => $analysis->getDuration()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Analysis not found'
            ], 404);
        }
    }

    /**
     * Get streaming status for analysis
     */
    public function streamStatus(string $id): JsonResponse
    {
        try {
            $analysis = ContractAnalysis::findOrFail($id);
            
            // Get streaming data from cache
            $streamData = cache()->get("openai_stream_{$id}");
            
            return response()->json([
                'success' => true,
                'analysis_id' => $id,
                'status' => $analysis->status,
                'progress' => $analysis->progress,
                'stream_data' => $streamData,
                'updated_at' => now()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Analysis not found'
            ], 404);
        }
    }

    /**
     * List user's analyses
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|string|in:pending,processing,completed,failed',
            'network' => 'sometimes|string|in:ethereum,bsc,polygon,arbitrum,optimism,avalanche,fantom',
            'limit' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $query = ContractAnalysis::query()
            ->when(auth()->id(), fn($q) => $q->where('user_id', auth()->id()))
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('network')) {
            $query->where('network', strtolower($request->network));
        }

        $limit = $request->integer('limit', 20);
        $analyses = $query->paginate($limit);

        return response()->json([
            'success' => true,
            'analyses' => $analyses->items(),
            'pagination' => [
                'current_page' => $analyses->currentPage(),
                'last_page' => $analyses->lastPage(),
                'per_page' => $analyses->perPage(),
                'total' => $analyses->total()
            ]
        ]);
    }

    /**
     * Get analysis statistics
     */
    public function stats(Request $request): JsonResponse
    {
        $days = $request->integer('days', 30);
        
        try {
            $stats = ContractAnalysis::getAnalyticsData($days);
            
            return response()->json([
                'success' => true,
                'stats' => $stats,
                'period_days' => $days
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to get analysis statistics", [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics'
            ], 500);
        }
    }

    /**
     * Cancel a pending/processing analysis
     */
    public function cancel(string $id): JsonResponse
    {
        try {
            $analysis = ContractAnalysis::findOrFail($id);

            if (!in_array($analysis->status, ['pending', 'processing'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot cancel analysis in current status'
                ], 400);
            }

            $analysis->update([
                'status' => 'failed',
                'error_message' => 'Cancelled by user',
                'completed_at' => now()
            ]);

            Log::info("Analysis cancelled by user", [
                'analysis_id' => $id,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Analysis cancelled successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Analysis not found'
            ], 404);
        }
    }

    /**
     * Retry a failed analysis
     */
    public function retry(string $id): JsonResponse
    {
        try {
            $analysis = ContractAnalysis::findOrFail($id);

            if ($analysis->status !== 'failed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Can only retry failed analyses'
                ], 400);
            }

            // Reset analysis state
            $analysis->update([
                'status' => 'pending',
                'progress' => 0,
                'current_step' => null,
                'error_message' => null,
                'raw_response' => null,
                'findings' => null,
                'findings_count' => 0,
                'tokens_used' => null,
                'processing_time_ms' => null,
                'started_at' => null,
                'completed_at' => null
            ]);

            // Dispatch new job
            AnalyzeContractJob::dispatch(
                $analysis->contract_address,
                $analysis->network,
                $analysis->id,
                $analysis->analysis_options
            );

            Log::info("Analysis retry initiated", [
                'analysis_id' => $id,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Analysis retry started',
                'analysis' => $analysis->getAnalysisSummary()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Analysis not found'
            ], 404);
        }
    }
}