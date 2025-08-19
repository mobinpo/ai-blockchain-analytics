<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Analysis;
use App\Models\ContractAnalysis;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

final class AnalysisMonitorController extends Controller
{
    /**
     * Get currently active analyses from database
     */
    public function getActiveAnalyses(Request $request): JsonResponse
    {
        try {
            $activeAnalyses = Cache::remember('active_analyses', 20, function () {
                return Analysis::whereIn('status', ['processing', 'streaming'])
                    ->orderBy('started_at', 'desc')
                    ->select([
                        'id',
                        'analysis_type',
                        'target_address', 
                        'status',
                        'started_at',
                        'tokens_streamed',
                        'token_limit',
                        'findings_count',
                        'gas_analyzed',
                        'metadata'
                    ])
                    ->get()
                    ->map(function ($analysis) {
                        return [
                            'id' => $analysis->id,
                            'contractName' => $analysis->metadata['contract_name'] ?? 'Unknown Contract',
                            'contractAddress' => $analysis->target_address,
                            'type' => $analysis->analysis_type ?? 'Security Analysis',
                            'status' => $analysis->status,
                            'progress' => $analysis->getStreamingProgress() ?? 0,
                            'startedAt' => $analysis->started_at?->toISOString(),
                            'duration' => $analysis->started_at ? $analysis->started_at->diffInSeconds(now()) : 0,
                            'findingsCount' => $analysis->findings_count ?? 0,
                            'gasAnalyzed' => $analysis->gas_analyzed ?? 0,
                        ];
                    })
                    ->toArray();
            });
            
            return response()->json([
                'success' => true,
                'analyses' => $activeAnalyses,
                'count' => count($activeAnalyses),
                'timestamp' => Carbon::now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch active analyses',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get queued analyses waiting to be processed from database
     */
    public function getQueuedAnalyses(Request $request): JsonResponse
    {
        try {
            $queuedAnalyses = Cache::remember('queued_analyses', 20, function () {
                return Analysis::where('status', 'pending')
                    ->orderBy('created_at', 'asc')
                    ->select([
                        'id',
                        'analysis_type',
                        'target_address',
                        'priority',
                        'created_at',
                        'metadata'
                    ])
                    ->get()
                    ->map(function ($analysis) {
                        return [
                            'id' => $analysis->id,
                            'contractName' => $analysis->metadata['contract_name'] ?? 'Unknown Contract',
                            'contractAddress' => $analysis->target_address,
                            'type' => $analysis->analysis_type ?? 'Security Analysis',
                            'priority' => $analysis->priority ?? 'medium',
                            'queuedAt' => $analysis->created_at?->toISOString(),
                        ];
                    })
                    ->toArray();
            });
            
            return response()->json([
                'success' => true,
                'queue' => $queuedAnalyses,
                'count' => count($queuedAnalyses),
                'timestamp' => Carbon::now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch queued analyses',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get real performance metrics from database
     */
    public function getMetrics(Request $request): JsonResponse
    {
        try {
            $metrics = Cache::remember('analysis_metrics', 20, function () {
                $today = Carbon::today();
                
                return [
                    'totalAnalysesToday' => Analysis::whereDate('created_at', $today)->count(),
                    'completedToday' => Analysis::whereDate('completed_at', $today)->count(),
                    'averageCompletionTime' => Analysis::whereNotNull('duration_seconds')
                        ->whereDate('completed_at', '>=', $today->subDays(7))
                        ->avg('duration_seconds') ?? 0,
                    'successRate' => $this->calculateSuccessRate(),
                    'totalFindingsToday' => Analysis::whereDate('created_at', $today)
                        ->sum('findings_count') ?? 0,
                    'activeWorkers' => Analysis::whereIn('status', ['processing', 'streaming'])->count(),
                    'queueSize' => Analysis::where('status', 'pending')->count(),
                ];
            });
            
            return response()->json([
                'success' => true,
                'metrics' => $metrics,
                'timestamp' => Carbon::now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch metrics',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get overall analysis status - single source of truth for all UI activity indicators
     */
    public function getAnalysisStatus(Request $request): JsonResponse
    {
        try {
            // Get real counts from database with caching
            $status = Cache::remember('analysis_status', 20, function () {
                $activeCount = $this->getRealActiveCount();
                $queueCount = $this->getRealQueueCount();
                
                // Get latest activity
                $latestActivity = Analysis::whereIn('status', ['processing', 'streaming', 'completed'])
                    ->orderBy('updated_at', 'desc')
                    ->first();
                
                // Determine system state based on real data
                $state = $this->determineSystemState($activeCount, $queueCount);
                
                return [
                    'state' => $state,
                    'hasActiveAnalyses' => $activeCount > 0,
                    'hasQueuedAnalyses' => $queueCount > 0,
                    'activeCount' => $activeCount,
                    'queueCount' => $queueCount,
                    'isHealthy' => true,
                    'lastActivity' => $latestActivity?->updated_at?->toISOString(),
                    'summary' => $this->generateStatusSummary($activeCount, $queueCount)
                ];
            });
            
            return response()->json([
                'success' => true,
                'status' => $status,
                'timestamp' => Carbon::now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch analysis status',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error',
                'status' => [
                    'state' => 'error',
                    'hasActiveAnalyses' => false,
                    'hasQueuedAnalyses' => false,
                    'activeCount' => 0,
                    'queueCount' => 0,
                    'isHealthy' => false,
                    'lastActivity' => null,
                    'summary' => 'System error - unable to determine status'
                ]
            ], 500);
        }
    }

    /**
     * Calculate success rate from database
     */
    private function calculateSuccessRate(): float
    {
        $total = Analysis::whereDate('completed_at', '>=', Carbon::today()->subDays(7))->count();
        
        if ($total === 0) {
            return 100.0;
        }
        
        $successful = Analysis::whereDate('completed_at', '>=', Carbon::today()->subDays(7))
            ->where('status', 'completed')
            ->count();
            
        return round(($successful / $total) * 100, 1);
    }

    /**
     * Determine overall system state based on current workload
     */
    private function determineSystemState(int $activeCount, int $queueCount): string
    {
        if ($activeCount === 0 && $queueCount === 0) {
            return 'idle';
        }
        
        if ($activeCount >= 5 || $queueCount >= 8) {
            return 'busy';
        }
        
        if ($activeCount > 0) {
            return 'active';
        }
        
        return 'idle';
    }

    /**
     * Generate a human-readable status summary
     */
    private function generateStatusSummary(int $activeCount, int $queueCount): string
    {
        if ($activeCount === 0 && $queueCount === 0) {
            return 'System is idle - no active analyses';
        }
        
        if ($activeCount === 1 && $queueCount === 0) {
            return '1 analysis running';
        }
        
        if ($activeCount > 1 && $queueCount === 0) {
            return "{$activeCount} analyses running";
        }
        
        if ($activeCount === 0 && $queueCount > 0) {
            return "{$queueCount} analyses queued";
        }
        
        return "{$activeCount} active, {$queueCount} queued";
    }

    /**
     * Clear all analysis-related caches
     */
    public static function clearAnalysisCaches(): void
    {
        Cache::forget('active_analyses');
        Cache::forget('queued_analyses');
        Cache::forget('analysis_metrics');
        Cache::forget('analysis_status');
    }

    /**
     * Get real active count from both Analysis and ContractAnalysis models
     */
    private function getRealActiveCount(): int
    {
        $analysisCount = Analysis::whereIn('status', ['processing', 'streaming'])->count();
        $contractAnalysisCount = ContractAnalysis::whereIn('status', ['processing', 'analyzing'])->count();
        
        return $analysisCount + $contractAnalysisCount;
    }

    /**
     * Get real queue count from both models
     */
    private function getRealQueueCount(): int
    {
        $analysisCount = Analysis::where('status', 'pending')->count();
        $contractAnalysisCount = ContractAnalysis::where('status', 'pending')->count();
        
        return $analysisCount + $contractAnalysisCount;
    }
}
