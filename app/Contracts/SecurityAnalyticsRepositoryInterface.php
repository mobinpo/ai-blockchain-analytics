<?php

declare(strict_types=1);

namespace App\Contracts;

interface SecurityAnalyticsRepositoryInterface
{
    /**
     * Get risk matrix data aggregated from real security analyses
     */
    public function getRiskMatrix(): array;

    /**
     * Get security trends over time period
     */
    public function getSecurityTrends(string $period = '7D'): array;

    /**
     * Get vulnerability statistics by severity
     */
    public function getVulnerabilityStats(): array;

    /**
     * Get recent critical security findings
     */
    public function getCriticalFindings(int $limit = 10): array;
}
