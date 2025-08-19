<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Analysis;
use App\Models\ContractAnalysis;
use Carbon\Carbon;

final class AnalysisState
{
    /**
     * Get current analysis status from database - single source of truth
     */
    public function getCurrentStatus(): array
    {
        // Get real counts from both Analysis and ContractAnalysis models
        $activeCount = $this->getActiveCount();
        $queueCount = $this->getQueueCount();
        
        // Get latest activity timestamp
        $latestActivity = $this->getLatestActivity();
        
        // Determine system state
        $state = $this->determineState($activeCount, $queueCount);
        
        return [
            'state' => $state,
            'activeCount' => $activeCount,
            'queueCount' => $queueCount,
            'hasActiveAnalyses' => $activeCount > 0,
            'hasQueuedAnalyses' => $queueCount > 0,
            'isHealthy' => true,
            'lastActivity' => $latestActivity?->toISOString(),
            'summary' => $this->generateSummary($activeCount, $queueCount),
            'timestamp' => Carbon::now()->toISOString()
        ];
    }

    /**
     * Get active analysis count from database
     */
    private function getActiveCount(): int
    {
        $analysisCount = Analysis::whereIn('status', ['processing', 'streaming'])->count();
        $contractAnalysisCount = ContractAnalysis::whereIn('status', ['processing', 'analyzing'])->count();
        
        return $analysisCount + $contractAnalysisCount;
    }

    /**
     * Get queued analysis count from database
     */
    private function getQueueCount(): int
    {
        $analysisCount = Analysis::where('status', 'pending')->count();
        $contractAnalysisCount = ContractAnalysis::where('status', 'pending')->count();
        
        return $analysisCount + $contractAnalysisCount;
    }

    /**
     * Get latest activity timestamp
     */
    private function getLatestActivity(): ?Carbon
    {
        $latestAnalysis = Analysis::whereIn('status', ['processing', 'streaming', 'completed'])
            ->orderBy('updated_at', 'desc')
            ->first();
            
        $latestContractAnalysis = ContractAnalysis::whereIn('status', ['processing', 'analyzing', 'completed'])
            ->orderBy('updated_at', 'desc')
            ->first();
            
        if (!$latestAnalysis && !$latestContractAnalysis) {
            return null;
        }
        
        if (!$latestAnalysis) {
            return $latestContractAnalysis->updated_at;
        }
        
        if (!$latestContractAnalysis) {
            return $latestAnalysis->updated_at;
        }
        
        return $latestAnalysis->updated_at->gt($latestContractAnalysis->updated_at) 
            ? $latestAnalysis->updated_at 
            : $latestContractAnalysis->updated_at;
    }

    /**
     * Determine system state based on workload
     */
    private function determineState(int $activeCount, int $queueCount): string
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
     * Generate human-readable status summary
     */
    private function generateSummary(int $activeCount, int $queueCount): string
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
}