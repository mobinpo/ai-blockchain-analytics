<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\SecurityAnalyticsRepositoryInterface;
use App\Models\Analysis;
use App\Models\Finding;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

final class SecurityAnalyticsRepository implements SecurityAnalyticsRepositoryInterface
{
    /**
     * Get risk matrix data aggregated from real security analyses
     */
    public function getRiskMatrix(): array
    {
        return Cache::remember('security_risk_matrix', 300, function () {
            // Initialize 5x5 matrix (Impact x Probability)
            $matrix = array_fill(0, 5, array_fill(0, 5, ['count' => 0, 'examples' => []]));

            // Get findings with severity data
            $findings = Finding::select('severity', 'title')
                ->whereHas('analysis', function ($query) {
                    $query->where('status', 'completed')
                          ->where('created_at', '>=', Carbon::now()->subDays(30));
                })
                ->get();

            foreach ($findings as $finding) {
                $impact = $this->mapSeverityToImpact($finding->severity);
                $probability = $this->estimateProbability($finding);
                
                $matrix[$impact][$probability]['count']++;
                
                // Add example if under limit
                if (count($matrix[$impact][$probability]['examples']) < 3) {
                    $matrix[$impact][$probability]['examples'][] = $finding->title;
                }
            }

            return $matrix;
        });
    }

    /**
     * Get security trends over time period
     */
    public function getSecurityTrends(string $period = '7D'): array
    {
        $days = match($period) {
            '24H' => 1,
            '7D' => 7,
            '30D' => 30,
            '90D' => 90,
            default => 7
        };

        return Cache::remember("security_trends_{$period}", 600, function () use ($days) {
            $startDate = Carbon::now()->subDays($days);
            
            $trends = Finding::select(
                DB::raw('DATE(created_at) as date'),
                'severity',
                DB::raw('COUNT(*) as count')
            )
            ->where('created_at', '>=', $startDate)
            ->groupBy('date', 'severity')
            ->orderBy('date')
            ->get()
            ->groupBy('date');

            $critical = [];
            $high = [];
            $medium = [];

            foreach ($trends as $date => $severityGroups) {
                $x = Carbon::parse($date)->diffInDays($startDate) * 50 + 50;
                
                $criticalCount = $severityGroups->where('severity', 'critical')->sum('count');
                $highCount = $severityGroups->where('severity', 'high')->sum('count');
                $mediumCount = $severityGroups->where('severity', 'medium')->sum('count');

                $critical[] = [
                    'x' => $x,
                    'y' => 150 - ($criticalCount * 15),
                    'value' => $criticalCount
                ];

                $high[] = [
                    'x' => $x,
                    'y' => 150 - ($highCount * 8),
                    'value' => $highCount
                ];

                $medium[] = [
                    'x' => $x,
                    'y' => 150 - ($mediumCount * 4),
                    'value' => $mediumCount
                ];
            }

            return [
                'critical' => $critical,
                'high' => $high,
                'medium' => $medium
            ];
        });
    }

    /**
     * Get vulnerability statistics by severity
     */
    public function getVulnerabilityStats(): array
    {
        return Cache::remember('vulnerability_stats', 300, function () {
            return Finding::select('severity', DB::raw('COUNT(*) as count'))
                ->whereHas('analysis', function ($query) {
                    $query->where('status', 'completed')
                          ->where('created_at', '>=', Carbon::now()->subDays(30));
                })
                ->groupBy('severity')
                ->pluck('count', 'severity')
                ->toArray();
        });
    }

    /**
     * Get recent critical security findings
     */
    public function getCriticalFindings(int $limit = 10): array
    {
        return Cache::remember("critical_findings_{$limit}", 180, function () use ($limit) {
            return Finding::with(['analysis.project'])
                ->where('severity', 'critical')
                ->whereHas('analysis', function ($query) {
                    $query->where('status', 'completed');
                })
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($finding) {
                    return [
                        'id' => $finding->id,
                        'title' => $finding->title,
                        'severity' => $finding->severity,
                        'contract' => $finding->analysis->project->name ?? 'Unknown Contract',
                        'network' => $finding->analysis->project->network ?? 'ethereum',
                        'description' => $finding->description ?? 'No description provided',
                        'line' => $finding->line ?? 0,
                        'created_at' => $finding->created_at,
                        'impact' => ucfirst($finding->severity)
                    ];
                })
                ->toArray();
        });
    }

    /**
     * Map severity to impact level (0-4)
     */
    private function mapSeverityToImpact(string $severity): int
    {
        return match($severity) {
            'critical' => 4,
            'high' => 3,
            'medium' => 2,
            'low' => 1,
            'info' => 0,
            default => 0
        };
    }

    /**
     * Estimate probability score based on finding type
     */
    private function estimateProbability($finding): int
    {
        // Simple heuristic based on finding title/type
        $title = strtolower($finding->title);
        
        if (str_contains($title, 'reentrancy') || str_contains($title, 'overflow')) {
            return 3; // High probability
        }
        if (str_contains($title, 'access control') || str_contains($title, 'authorization')) {
            return 2; // Medium probability
        }
        if (str_contains($title, 'gas') || str_contains($title, 'optimization')) {
            return 1; // Low probability
        }
        
        return 2; // Default medium
    }
}
