<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

final class ContractCacheAnalytics extends Model
{
    protected $table = 'contract_cache_analytics';

    protected $fillable = [
        'network',
        'cache_type',
        'date',
        'total_requests',
        'cache_hits',
        'cache_misses',
        'api_calls_saved',
        'cache_hit_rate',
        'unique_contracts',
        'hourly_stats'
    ];

    protected $casts = [
        'date' => 'date',
        'total_requests' => 'integer',
        'cache_hits' => 'integer',
        'cache_misses' => 'integer',
        'api_calls_saved' => 'integer',
        'cache_hit_rate' => 'float',
        'unique_contracts' => 'integer',
        'hourly_stats' => 'array'
    ];

    /**
     * Record a cache hit
     */
    public static function recordCacheHit(string $network, string $cacheType, string $contractAddress): void
    {
        $analytics = self::getOrCreateForToday($network, $cacheType);
        
        $analytics->increment('total_requests');
        $analytics->increment('cache_hits');
        $analytics->increment('api_calls_saved');
        
        // Update hourly stats
        $hour = now()->hour;
        $hourlyStats = $analytics->hourly_stats ?? array_fill(0, 24, ['hits' => 0, 'misses' => 0]);
        $hourlyStats[$hour]['hits'] = ($hourlyStats[$hour]['hits'] ?? 0) + 1;
        $analytics->hourly_stats = $hourlyStats;
        
        // Recalculate hit rate
        $analytics->cache_hit_rate = ($analytics->cache_hits / max($analytics->total_requests, 1)) * 100;
        
        $analytics->save();
    }

    /**
     * Record a cache miss (API call required)
     */
    public static function recordCacheMiss(string $network, string $cacheType, string $contractAddress): void
    {
        $analytics = self::getOrCreateForToday($network, $cacheType);
        
        $analytics->increment('total_requests');
        $analytics->increment('cache_misses');
        
        // Update hourly stats
        $hour = now()->hour;
        $hourlyStats = $analytics->hourly_stats ?? array_fill(0, 24, ['hits' => 0, 'misses' => 0]);
        $hourlyStats[$hour]['misses'] = ($hourlyStats[$hour]['misses'] ?? 0) + 1;
        $analytics->hourly_stats = $hourlyStats;
        
        // Recalculate hit rate
        $analytics->cache_hit_rate = ($analytics->cache_hits / max($analytics->total_requests, 1)) * 100;
        
        $analytics->save();
    }

    /**
     * Get or create analytics record for today
     */
    private static function getOrCreateForToday(string $network, string $cacheType): self
    {
        return self::firstOrCreate(
            [
                'network' => $network,
                'cache_type' => $cacheType,
                'date' => today()
            ],
            [
                'total_requests' => 0,
                'cache_hits' => 0,
                'cache_misses' => 0,
                'api_calls_saved' => 0,
                'cache_hit_rate' => 0,
                'unique_contracts' => 0,
                'hourly_stats' => array_fill(0, 24, ['hits' => 0, 'misses' => 0])
            ]
        );
    }

    /**
     * Get analytics for date range
     */
    public static function getAnalyticsForDateRange(
        Carbon $startDate,
        Carbon $endDate,
        ?string $network = null,
        ?string $cacheType = null
    ): array {
        $query = self::whereBetween('date', [$startDate, $endDate]);
        
        if ($network) {
            $query->where('network', $network);
        }
        
        if ($cacheType) {
            $query->where('cache_type', $cacheType);
        }
        
        $analytics = $query->get();
        
        return [
            'total_requests' => $analytics->sum('total_requests'),
            'total_cache_hits' => $analytics->sum('cache_hits'),
            'total_cache_misses' => $analytics->sum('cache_misses'),
            'total_api_calls_saved' => $analytics->sum('api_calls_saved'),
            'average_hit_rate' => $analytics->avg('cache_hit_rate'),
            'unique_contracts' => $analytics->sum('unique_contracts'),
            'daily_breakdown' => $analytics->groupBy('date')->map(function ($dayData) {
                return [
                    'requests' => $dayData->sum('total_requests'),
                    'hits' => $dayData->sum('cache_hits'),
                    'misses' => $dayData->sum('cache_misses'),
                    'hit_rate' => $dayData->avg('cache_hit_rate')
                ];
            }),
            'network_breakdown' => $analytics->groupBy('network')->map(function ($networkData) {
                return [
                    'requests' => $networkData->sum('total_requests'),
                    'hits' => $networkData->sum('cache_hits'),
                    'hit_rate' => $networkData->avg('cache_hit_rate'),
                    'api_calls_saved' => $networkData->sum('api_calls_saved')
                ];
            })
        ];
    }

    /**
     * Get current cache performance summary
     */
    public static function getCurrentPerformanceSummary(): array
    {
        $today = self::where('date', today())->get();
        $thisWeek = self::whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()])->get();
        $thisMonth = self::whereBetween('date', [now()->startOfMonth(), now()->endOfMonth()])->get();

        return [
            'today' => [
                'total_requests' => $today->sum('total_requests'),
                'cache_hits' => $today->sum('cache_hits'),
                'api_calls_saved' => $today->sum('api_calls_saved'),
                'hit_rate' => $today->avg('cache_hit_rate') ?? 0
            ],
            'this_week' => [
                'total_requests' => $thisWeek->sum('total_requests'),
                'cache_hits' => $thisWeek->sum('cache_hits'),
                'api_calls_saved' => $thisWeek->sum('api_calls_saved'),
                'hit_rate' => $thisWeek->avg('cache_hit_rate') ?? 0
            ],
            'this_month' => [
                'total_requests' => $thisMonth->sum('total_requests'),
                'cache_hits' => $thisMonth->sum('cache_hits'),
                'api_calls_saved' => $thisMonth->sum('api_calls_saved'),
                'hit_rate' => $thisMonth->avg('cache_hit_rate') ?? 0
            ]
        ];
    }

    /**
     * Get hourly performance for today
     */
    public static function getTodayHourlyPerformance(?string $network = null): array
    {
        $query = self::where('date', today());
        
        if ($network) {
            $query->where('network', $network);
        }
        
        $analytics = $query->get();
        
        $hourlyData = array_fill(0, 24, ['hits' => 0, 'misses' => 0, 'total' => 0]);
        
        foreach ($analytics as $record) {
            $hourlyStats = $record->hourly_stats ?? [];
            foreach ($hourlyStats as $hour => $stats) {
                $hourlyData[$hour]['hits'] += $stats['hits'] ?? 0;
                $hourlyData[$hour]['misses'] += $stats['misses'] ?? 0;
                $hourlyData[$hour]['total'] += ($stats['hits'] ?? 0) + ($stats['misses'] ?? 0);
            }
        }
        
        return $hourlyData;
    }
}