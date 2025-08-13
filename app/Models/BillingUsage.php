<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class BillingUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'period',
        'period_start',
        'period_end',
        'analysis_count',
        'api_calls_count',
        'tokens_used',
        'total_cost',
        'breakdown',
        'subscription_name',
        'stripe_subscription_id',
        'is_overage',
        'overage_cost',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'total_cost' => 'decimal:4',
        'overage_cost' => 'decimal:4',
        'breakdown' => 'array',
        'is_overage' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get or create billing usage for current period
     */
    public static function getCurrentPeriodUsage(int $userId, string $period = 'monthly'): self
    {
        $now = now();
        
        if ($period === 'monthly') {
            $periodStart = $now->startOfMonth()->toDateString();
            $periodEnd = $now->endOfMonth()->toDateString();
        } else {
            $periodStart = $now->startOfYear()->toDateString();
            $periodEnd = $now->endOfYear()->toDateString();
        }

        return static::firstOrCreate([
            'user_id' => $userId,
            'period' => $period,
            'period_start' => $periodStart,
        ], [
            'period_end' => $periodEnd,
            'analysis_count' => 0,
            'api_calls_count' => 0,
            'tokens_used' => 0,
            'total_cost' => 0,
        ]);
    }

    /**
     * Add usage to billing record
     */
    public function addUsage(string $type, int $quantity = 1, float $cost = 0, array $metadata = []): void
    {
        switch ($type) {
            case 'analysis':
                $this->increment('analysis_count', $quantity);
                break;
            case 'api_call':
                $this->increment('api_calls_count', $quantity);
                break;
            case 'tokens':
                $this->increment('tokens_used', $quantity);
                break;
        }

        $this->increment('total_cost', $cost);

        // Update breakdown
        $breakdown = $this->breakdown ?? [];
        $breakdown[$type] = ($breakdown[$type] ?? 0) + $quantity;
        $this->update(['breakdown' => $breakdown]);
    }

    /**
     * Check if user has exceeded their plan limits
     */
    public function checkOverage(array $planLimits): bool
    {
        $isOverage = false;

        if (isset($planLimits['analysis_limit']) && $this->analysis_count > $planLimits['analysis_limit']) {
            $isOverage = true;
        }

        if (isset($planLimits['api_calls_limit']) && $this->api_calls_count > $planLimits['api_calls_limit']) {
            $isOverage = true;
        }

        if (isset($planLimits['tokens_limit']) && $this->tokens_used > $planLimits['tokens_limit']) {
            $isOverage = true;
        }

        if ($isOverage !== $this->is_overage) {
            $this->update(['is_overage' => $isOverage]);
        }

        return $isOverage;
    }

    /**
     * Calculate overage charges
     */
    public function calculateOverageCharges(array $planLimits, array $overageRates): float
    {
        $overageCharges = 0;

        // Analysis overage
        if (isset($planLimits['analysis_limit']) && $this->analysis_count > $planLimits['analysis_limit']) {
            $overageQuantity = $this->analysis_count - $planLimits['analysis_limit'];
            $overageCharges += $overageQuantity * ($overageRates['analysis'] ?? 0);
        }

        // API calls overage
        if (isset($planLimits['api_calls_limit']) && $this->api_calls_count > $planLimits['api_calls_limit']) {
            $overageQuantity = $this->api_calls_count - $planLimits['api_calls_limit'];
            $overageCharges += $overageQuantity * ($overageRates['api_call'] ?? 0);
        }

        // Tokens overage
        if (isset($planLimits['tokens_limit']) && $this->tokens_used > $planLimits['tokens_limit']) {
            $overageQuantity = $this->tokens_used - $planLimits['tokens_limit'];
            $overageCharges += $overageQuantity * ($overageRates['token'] ?? 0);
        }

        $this->update(['overage_cost' => $overageCharges]);

        return $overageCharges;
    }
}
