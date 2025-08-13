<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UsageMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'metric_type',
        'quantity',
        'cost',
        'metadata',
        'resource_type',
        'resource_id',
        'usage_date',
    ];

    protected $casts = [
        'metadata' => 'array',
        'usage_date' => 'date',
        'cost' => 'decimal:4',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter by metric type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('metric_type', $type);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('usage_date', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by current month
     */
    public function scopeCurrentMonth($query)
    {
        return $query->whereMonth('usage_date', now()->month)
                    ->whereYear('usage_date', now()->year);
    }

    /**
     * Get total usage for a user by type
     */
    public static function getUserUsage(int $userId, string $type, $startDate = null, $endDate = null): int
    {
        $query = static::where('user_id', $userId)->byType($type);
        
        if ($startDate && $endDate) {
            $query->byDateRange($startDate, $endDate);
        } else {
            $query->currentMonth();
        }
        
        return $query->sum('quantity');
    }

    /**
     * Get total cost for a user
     */
    public static function getUserCost(int $userId, $startDate = null, $endDate = null): float
    {
        $query = static::where('user_id', $userId);
        
        if ($startDate && $endDate) {
            $query->byDateRange($startDate, $endDate);
        } else {
            $query->currentMonth();
        }
        
        return (float) $query->sum('cost');
    }
}
