<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\AnalysisState;
use Illuminate\Support\Facades\Cache;

final class AnalysisService
{
    private AnalysisState $analysisState;

    public function __construct(AnalysisState $analysisState)
    {
        $this->analysisState = $analysisState;
    }

    /**
     * Get current analysis status with short-term caching
     */
    public function status(): array
    {
        return Cache::remember('analysis_status_canonical', 15, function () {
            return $this->analysisState->getCurrentStatus();
        });
    }

    /**
     * Clear the analysis status cache
     */
    public function clearStatusCache(): void
    {
        Cache::forget('analysis_status_canonical');
    }

    /**
     * Get fresh status without cache
     */
    public function freshStatus(): array
    {
        return $this->analysisState->getCurrentStatus();
    }
}