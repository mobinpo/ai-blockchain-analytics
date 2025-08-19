<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AnalysisStarted;
use App\Events\AnalysisCompleted;
use App\Events\AnalysisFailed;
use App\Http\Controllers\Api\AnalysisMonitorController;

class ClearAnalysisCacheListener
{
    /**
     * Handle analysis events by clearing the cache
     */
    public function handle($event): void
    {
        // Clear all analysis-related caches when any analysis state changes
        AnalysisMonitorController::clearAnalysisCaches();
    }
}