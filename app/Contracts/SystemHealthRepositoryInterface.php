<?php

declare(strict_types=1);

namespace App\Contracts;

interface SystemHealthRepositoryInterface
{
    /**
     * Get health status of all AI engine components
     */
    public function getComponentsHealth(): array;

    /**
     * Get overall system status summary
     */
    public function getSystemStatus(): array;

    /**
     * Get performance metrics for system components
     */
    public function getPerformanceMetrics(): array;

    /**
     * Check health of a specific service
     */
    public function checkServiceHealth(string $serviceName): array;
}
