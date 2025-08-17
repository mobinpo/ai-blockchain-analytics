<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

final class AnalysisMonitorController extends Controller
{
    /**
     * Get currently active analyses
     */
    public function getActiveAnalyses(Request $request): JsonResponse
    {
        try {
            // Fetch real active analyses from database
            $activeAnalyses = DB::table('analyses')
                ->join('projects', 'analyses.project_id', '=', 'projects.id')
                ->leftJoin('findings', 'analyses.id', '=', 'findings.analysis_id')
                ->where('analyses.status', 'running')
                ->select([
                    'analyses.id',
                    'analyses.engine as type',
                    'analyses.status',
                    'analyses.created_at',
                    'analyses.updated_at',
                    'projects.name as contractName',
                    'projects.blockchain_network as network',
                    DB::raw('COUNT(findings.id) as findingsCount')
                ])
                ->groupBy('analyses.id', 'analyses.engine', 'analyses.status', 'analyses.created_at', 'analyses.updated_at', 'projects.name', 'projects.blockchain_network')
                ->get()
                ->map(function ($analysis) {
                    $duration = Carbon::parse($analysis->created_at)->diffInSeconds(Carbon::now());
                    $progress = min(95, ($duration / 60) * 10); // Estimate progress based on time
                    
                    return [
                        'id' => 'analysis_' . $analysis->id,
                        'contractName' => $analysis->contractName,
                        'network' => $analysis->network ?: 'ethereum',
                        'type' => ucfirst($analysis->type) . ' Analysis',
                        'status' => 'analyzing',
                        'progress' => round($progress, 1),
                        'currentStep' => $this->getCurrentStep($progress),
                        'eta' => $this->calculateETA($progress),
                        'duration' => $duration,
                        'findingsCount' => $analysis->findingsCount,
                        'gasAnalyzed' => rand(50000, 500000), // This would come from actual analysis data
                        'recentFindings' => $this->getRecentFindings($analysis->id)
                    ];
                })
                ->toArray();
            
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
     * Get queued analyses waiting to be processed
     */
    public function getQueuedAnalyses(Request $request): JsonResponse
    {
        try {
            // Fetch real queued analyses from database
            $queuedAnalyses = DB::table('analyses')
                ->join('projects', 'analyses.project_id', '=', 'projects.id')
                ->where('analyses.status', 'pending')
                ->select([
                    'analyses.id',
                    'analyses.engine as type',
                    'analyses.created_at',
                    'projects.name as contractName',
                    'projects.blockchain_network as network'
                ])
                ->orderBy('analyses.created_at', 'asc')
                ->get()
                ->map(function ($analysis, $index) {
                    $estimatedStartMinutes = ($index + 1) * 5; // Estimate 5 minutes per queue position
                    
                    return [
                        'id' => 'queued_' . $analysis->id,
                        'contractName' => $analysis->contractName,
                        'network' => $analysis->network ?: 'ethereum',
                        'type' => ucfirst($analysis->type) . ' Analysis',
                        'estimatedStart' => Carbon::now()->addMinutes($estimatedStartMinutes)->format('H:i'),
                        'priority' => 'medium' // Could be determined by actual priority field
                    ];
                })
                ->toArray();
            
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
     * Get performance metrics for the analysis system
     */
    public function getMetrics(Request $request): JsonResponse
    {
        try {
            // Fetch real performance metrics from database
            $today = Carbon::today();
            
            $totalAnalysesToday = DB::table('analyses')
                ->whereDate('created_at', $today)
                ->count();
            
            $completedAnalysesToday = DB::table('analyses')
                ->whereDate('created_at', $today)
                ->where('status', 'completed')
                ->get();
            
            $averageCompletionTime = $completedAnalysesToday->count() > 0 
                ? $completedAnalysesToday->avg(function ($analysis) {
                    return Carbon::parse($analysis->updated_at)->diffInSeconds(Carbon::parse($analysis->created_at));
                })
                : 0;
            
            $totalFindingsToday = DB::table('findings')
                ->join('analyses', 'findings.analysis_id', '=', 'analyses.id')
                ->whereDate('analyses.created_at', $today)
                ->count();
            
            $activeAnalysesCount = DB::table('analyses')
                ->where('status', 'running')
                ->count();
            
            $queueSize = DB::table('analyses')
                ->where('status', 'pending')
                ->count();
            
            // Calculate system load based on active processes
            $systemLoad = min(100, ($activeAnalysesCount * 10) + ($queueSize * 2));
            
            $metrics = [
                'totalAnalysesToday' => $totalAnalysesToday,
                'averageCompletionTime' => round($averageCompletionTime),
                'totalFindingsToday' => $totalFindingsToday,
                'systemLoad' => $systemLoad,
                'successRate' => $this->calculateSuccessRate(),
                'activeWorkers' => $activeAnalysesCount,
                'queueSize' => $queueSize,
                'averageProcessingTime' => round($averageCompletionTime)
            ];
            
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
     * Get recent findings for a specific analysis
     */
    private function getRecentFindings(int $analysisId): array
    {
        return DB::table('findings')
            ->where('analysis_id', $analysisId)
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get(['id', 'title', 'severity', 'created_at'])
            ->map(function ($finding) {
                return [
                    'id' => $finding->id,
                    'title' => $finding->title,
                    'severity' => $finding->severity,
                    'timestamp' => Carbon::parse($finding->created_at)->diffForHumans()
                ];
            })
            ->toArray();
    }

    /**
     * Get current step based on progress percentage
     */
    private function getCurrentStep(float $progress): string
    {
        if ($progress > 90) return 'Report Generation';
        if ($progress > 70) return 'Vulnerability Assessment';
        if ($progress > 40) return 'Code Pattern Analysis';
        if ($progress > 20) return 'Function Mapping';
        return 'Contract Parsing';
    }

    /**
     * Calculate success rate from recent analyses
     */
    private function calculateSuccessRate(): float
    {
        $recentAnalyses = DB::table('analyses')
            ->whereDate('created_at', '>=', Carbon::now()->subDays(7))
            ->whereIn('status', ['completed', 'failed'])
            ->get();

        if ($recentAnalyses->count() === 0) {
            return 0.0;
        }

        $successful = $recentAnalyses->where('status', 'completed')->count();
        return round(($successful / $recentAnalyses->count()) * 100, 1);
    }

    /**
     * Calculate estimated time of completion
     */
    private function calculateETA(int $progress): string
    {
        if ($progress > 90) return '30s remaining';
        if ($progress > 70) return '2 min remaining';
        if ($progress > 40) return '5 min remaining';
        if ($progress > 20) return '8 min remaining';
        return 'Calculating...';
    }
}
