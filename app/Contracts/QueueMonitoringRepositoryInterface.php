<?php

declare(strict_types=1);

namespace App\Contracts;

interface QueueMonitoringRepositoryInterface
{
    /**
     * Get currently active job analyses
     */
    public function getActiveAnalyses(): array;

    /**
     * Get queued analyses waiting for processing
     */
    public function getQueuedAnalyses(): array;

    /**
     * Get performance metrics for the queue system
     */
    public function getQueueMetrics(): array;

    /**
     * Get recent job failures and errors
     */
    public function getRecentFailures(int $limit = 10): array;
}
