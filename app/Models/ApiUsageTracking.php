<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

final class ApiUsageTracking extends Model
{
    protected $table = 'api_usage_tracking';

    protected $fillable = [
        'network',
        'explorer',
        'endpoint',
        'request_time',
        'response_time_ms',
        'successful',
        'error_type',
        'error_message',
        'contract_address',
        'request_metadata'
    ];

    protected $casts = [
        'request_time' => 'datetime',
        'response_time_ms' => 'integer',
        'successful' => 'boolean',
        'request_metadata' => 'array'
    ];

    /**
     * Record a successful API request
     */
    public static function recordSuccess(
        string $network,
        string $explorer,
        string $endpoint,
        int $responseTimeMs,
        ?string $contractAddress = null,
        ?array $metadata = null
    ): void {
        self::create([
            'network' => $network,
            'explorer' => $explorer,
            'endpoint' => $endpoint,
            'request_time' => now(),
            'response_time_ms' => $responseTimeMs,
            'successful' => true,
            'contract_address' => $contractAddress,
            'request_metadata' => $metadata
        ]);
    }

    /**
     * Record a failed API request
     */
    public static function recordFailure(
        string $network,
        string $explorer,
        string $endpoint,
        string $errorType,
        string $errorMessage,
        ?int $responseTimeMs = null,
        ?string $contractAddress = null,
        ?array $metadata = null
    ): void {
        self::create([
            'network' => $network,
            'explorer' => $explorer,
            'endpoint' => $endpoint,
            'request_time' => now(),
            'response_time_ms' => $responseTimeMs,
            'successful' => false,
            'error_type' => $errorType,
            'error_message' => $errorMessage,
            'contract_address' => $contractAddress,
            'request_metadata' => $metadata
        ]);
    }

    /**
     * Get API usage statistics for date range
     */
    public static function getUsageStats(
        Carbon $startDate,
        Carbon $endDate,
        ?string $network = null,
        ?string $explorer = null
    ): array {
        $query = self::whereBetween('request_time', [$startDate, $endDate]);
        
        if ($network) {
            $query->where('network', $network);
        }
        
        if ($explorer) {
            $query->where('explorer', $explorer);
        }
        
        $totalRequests = $query->count();
        $successfulRequests = $query->where('successful', true)->count();
        $failedRequests = $totalRequests - $successfulRequests;
        $averageResponseTime = $query->where('successful', true)->avg('response_time_ms');
        
        $endpointStats = $query->selectRaw('
            endpoint,
            COUNT(*) as total_requests,
            SUM(CASE WHEN successful THEN 1 ELSE 0 END) as successful_requests,
            AVG(CASE WHEN successful THEN response_time_ms ELSE NULL END) as avg_response_time
        ')
        ->groupBy('endpoint')
        ->get()
        ->mapWithKeys(function ($stat) {
            return [$stat->endpoint => [
                'total_requests' => $stat->total_requests,
                'successful_requests' => $stat->successful_requests,
                'success_rate' => ($stat->successful_requests / max($stat->total_requests, 1)) * 100,
                'avg_response_time' => round($stat->avg_response_time ?? 0, 2)
            ]];
        });

        $errorBreakdown = $query->where('successful', false)
            ->selectRaw('error_type, COUNT(*) as count')
            ->groupBy('error_type')
            ->pluck('count', 'error_type');

        return [
            'total_requests' => $totalRequests,
            'successful_requests' => $successfulRequests,
            'failed_requests' => $failedRequests,
            'success_rate' => $totalRequests > 0 ? ($successfulRequests / $totalRequests) * 100 : 0,
            'average_response_time' => round($averageResponseTime ?? 0, 2),
            'endpoint_stats' => $endpointStats,
            'error_breakdown' => $errorBreakdown,
            'daily_usage' => self::getDailyUsage($startDate, $endDate, $network, $explorer)
        ];
    }

    /**
     * Get daily API usage breakdown
     */
    private static function getDailyUsage(
        Carbon $startDate,
        Carbon $endDate,
        ?string $network = null,
        ?string $explorer = null
    ): array {
        $query = self::selectRaw('
            DATE(request_time) as date,
            COUNT(*) as total_requests,
            SUM(CASE WHEN successful THEN 1 ELSE 0 END) as successful_requests
        ')
        ->whereBetween('request_time', [$startDate, $endDate])
        ->groupBy('date')
        ->orderBy('date');
        
        if ($network) {
            $query->where('network', $network);
        }
        
        if ($explorer) {
            $query->where('explorer', $explorer);
        }
        
        return $query->get()->mapWithKeys(function ($day) {
            return [$day->date => [
                'total_requests' => $day->total_requests,
                'successful_requests' => $day->successful_requests,
                'success_rate' => ($day->successful_requests / max($day->total_requests, 1)) * 100
            ]];
        })->toArray();
    }

    /**
     * Get current rate limit status
     */
    public static function getCurrentRateLimitStatus(string $network, string $explorer): array
    {
        $lastHour = now()->subHour();
        $lastMinute = now()->subMinute();
        
        $hourlyRequests = self::where('network', $network)
            ->where('explorer', $explorer)
            ->where('request_time', '>=', $lastHour)
            ->count();
            
        $minuteRequests = self::where('network', $network)
            ->where('explorer', $explorer)
            ->where('request_time', '>=', $lastMinute)
            ->count();
            
        $recentErrors = self::where('network', $network)
            ->where('explorer', $explorer)
            ->where('successful', false)
            ->where('request_time', '>=', $lastHour)
            ->count();

        return [
            'requests_last_hour' => $hourlyRequests,
            'requests_last_minute' => $minuteRequests,
            'errors_last_hour' => $recentErrors,
            'estimated_safe' => $hourlyRequests < 100 && $minuteRequests < 5, // Conservative limits
            'last_request_time' => self::where('network', $network)
                ->where('explorer', $explorer)
                ->latest('request_time')
                ->value('request_time')
        ];
    }

    /**
     * Clean up old tracking data
     */
    public static function cleanupOldData(int $daysToKeep = 30): int
    {
        $cutoffDate = now()->subDays($daysToKeep);
        
        return self::where('request_time', '<', $cutoffDate)->delete();
    }

    /**
     * Get top error messages for troubleshooting
     */
    public static function getTopErrors(int $limit = 10, ?string $network = null): array
    {
        $query = self::where('successful', false)
            ->where('request_time', '>=', now()->subDays(7));
            
        if ($network) {
            $query->where('network', $network);
        }
        
        return $query->selectRaw('error_message, error_type, COUNT(*) as count')
            ->groupBy('error_message', 'error_type')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(function ($error) {
                return [
                    'message' => $error->error_message,
                    'type' => $error->error_type,
                    'count' => $error->count
                ];
            })
            ->toArray();
    }
}