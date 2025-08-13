<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

final class DemoCacheData extends Model
{
    use HasFactory;

    protected $table = 'demo_cache_data';

    protected $fillable = [
        'data_type',
        'identifier',
        'data',
        'refresh_interval',
        'is_active',
    ];

    protected $casts = [
        'data' => 'array',
        'refresh_interval' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Scope to get only active demo data.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by data type.
     */
    public function scopeOfType(Builder $query, string $dataType): Builder
    {
        return $query->where('data_type', $dataType);
    }

    /**
     * Scope to get data that needs refreshing.
     */
    public function scopeNeedsRefresh(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereRaw('updated_at + INTERVAL refresh_interval SECOND < NOW()');
        });
    }

    /**
     * Check if this demo data needs refreshing.
     */
    public function needsRefresh(): bool
    {
        return $this->updated_at->addSeconds($this->refresh_interval)->isPast();
    }

    /**
     * Get formatted data with metadata.
     */
    public function getFormattedDataAttribute(): array
    {
        return [
            'data' => $this->data,
            'last_updated' => $this->updated_at,
            'next_refresh' => $this->updated_at->addSeconds($this->refresh_interval),
            'is_stale' => $this->needsRefresh(),
        ];
    }

    /**
     * Store or update demo data.
     */
    public static function store(
        string $dataType,
        string $identifier,
        array $data,
        int $refreshInterval = 300,
        bool $isActive = true
    ): self {
        return self::updateOrCreate(
            [
                'data_type' => $dataType,
                'identifier' => $identifier,
            ],
            [
                'data' => $data,
                'refresh_interval' => $refreshInterval,
                'is_active' => $isActive,
            ]
        );
    }

    /**
     * Retrieve demo data by type and identifier.
     */
    public static function retrieve(string $dataType, string $identifier): ?self
    {
        return self::active()
            ->ofType($dataType)
            ->where('identifier', $identifier)
            ->first();
    }

    /**
     * Get all data for a specific type.
     */
    public static function getByType(string $dataType): array
    {
        return self::active()
            ->ofType($dataType)
            ->get()
            ->map(function ($item) {
                return [
                    'identifier' => $item->identifier,
                    'data' => $item->data,
                    'last_updated' => $item->updated_at,
                    'needs_refresh' => $item->needsRefresh(),
                ];
            })
            ->toArray();
    }

    /**
     * Refresh stale demo data.
     */
    public static function refreshStaleData(): int
    {
        $staleData = self::active()->needsRefresh()->get();
        $refreshed = 0;

        foreach ($staleData as $item) {
            // Here you would trigger the appropriate refresh logic
            // For now, we'll just update the timestamp to simulate refresh
            $item->touch();
            $refreshed++;
        }

        return $refreshed;
    }

    /**
     * Get demo data statistics.
     */
    public static function getStats(): array
    {
        return [
            'total_entries' => self::count(),
            'active_entries' => self::active()->count(),
            'stale_entries' => self::active()->needsRefresh()->count(),
            'data_types' => self::active()->distinct('data_type')->pluck('data_type')->toArray(),
            'total_size_kb' => round(
                self::selectRaw('SUM(JSON_LENGTH(data)) / 1024 as size_kb')
                    ->value('size_kb') ?? 0,
                2
            ),
        ];
    }

    /**
     * Initialize default demo data for the North Star dashboard.
     */
    public static function initializeDemoData(): void
    {
        // Live statistics
        self::store('live_stats', 'dashboard_counters', [
            'contracts_analyzed' => 1247,
            'security_issues' => 89,
            'sentiment_score' => 0.743,
            'api_requests' => 15420,
        ], 60); // Refresh every minute

        // Live threats
        self::store('threats', 'security_feed', [
            [
                'id' => 1,
                'type' => 'Reentrancy Attack',
                'contract' => '0x1234...5678',
                'severity' => 'critical',
                'timestamp' => now()->subMinutes(2),
            ],
            [
                'id' => 2,
                'type' => 'Flash Loan Exploit',
                'contract' => '0xabcd...efgh',
                'severity' => 'high',
                'timestamp' => now()->subMinutes(5),
            ],
        ], 120); // Refresh every 2 minutes

        // Activity stream
        self::store('activities', 'live_feed', [
            [
                'id' => 1,
                'type' => 'security_scan',
                'message' => 'High-risk vulnerability detected in DeFi protocol',
                'timestamp' => now(),
                'severity' => 'high',
            ],
            [
                'id' => 2,
                'type' => 'sentiment_alert',
                'message' => 'Bitcoin sentiment spike detected (+15%)',
                'timestamp' => now()->subMinutes(2),
                'severity' => 'info',
            ],
        ], 180); // Refresh every 3 minutes

        // Performance metrics
        self::store('metrics', 'ai_engine', [
            'accuracy' => 98.7,
            'uptime' => 99.9,
            'response_time' => 1.2,
            'throughput' => 450,
            'active_jobs' => 12,
            'pending_jobs' => 8,
        ], 30); // Refresh every 30 seconds
    }
}