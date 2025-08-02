<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SubscriptionPlan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'stripe_price_id',
        'price',
        'currency',
        'interval',
        'interval_count',
        'trial_period_days',
        'features',
        'analysis_limit',
        'project_limit',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'features' => 'array',
        'price' => 'integer', // Price in cents
        'is_active' => 'boolean',
        'trial_period_days' => 'integer',
        'interval_count' => 'integer',
        'analysis_limit' => 'integer',
        'project_limit' => 'integer',
        'sort_order' => 'integer',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'stripe_price', 'stripe_price_id');
    }

    public function scopeActive($query): mixed
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query): mixed
    {
        return $query->orderBy('sort_order');
    }

    public function scopeByInterval($query, string $interval): mixed
    {
        return $query->where('interval', $interval);
    }

    public function isFeatureEnabled(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }

    public function hasAnalysisLimit(): bool
    {
        return $this->analysis_limit !== -1 && $this->analysis_limit > 0;
    }

    public function hasProjectLimit(): bool
    {
        return $this->project_limit !== -1 && $this->project_limit > 0;
    }

    public function isUnlimited(): bool
    {
        return $this->analysis_limit === -1 || $this->project_limit === -1;
    }

    public function getPriceFormatted(): string
    {
        return '$' . number_format($this->price / 100, 2);
    }

    public function getPriceInDollars(): float
    {
        return $this->price / 100;
    }

    public function getMonthlyPrice(): float
    {
        if ($this->interval === 'month') {
            return $this->getPriceInDollars();
        }
        
        if ($this->interval === 'year') {
            return $this->getPriceInDollars() / 12;
        }
        
        return $this->getPriceInDollars();
    }

    public function getSavingsPercentage(): ?float
    {
        if ($this->interval !== 'year') {
            return null;
        }

        // Find corresponding monthly plan
        $basePlan = str_replace('-annual', '', $this->slug);
        $monthlyPlan = static::where('slug', $basePlan)->first();
        
        if (!$monthlyPlan) {
            return null;
        }

        $yearlyPrice = $this->getPriceInDollars();
        $monthlyYearlyPrice = $monthlyPlan->getPriceInDollars() * 12;
        
        return round((($monthlyYearlyPrice - $yearlyPrice) / $monthlyYearlyPrice) * 100);
    }

    public function getIntervalLabel(): string
    {
        return match ($this->interval) {
            'month' => $this->interval_count === 1 ? 'monthly' : $this->interval_count . ' months',
            'year' => $this->interval_count === 1 ? 'yearly' : $this->interval_count . ' years',
            default => $this->interval,
        };
    }

    public function getPlanTier(): string
    {
        return match (true) {
            str_contains(strtolower($this->slug), 'starter') => 'starter',
            str_contains(strtolower($this->slug), 'professional') => 'professional',
            str_contains(strtolower($this->slug), 'enterprise') => 'enterprise',
            default => 'unknown',
        };
    }

    public function isAnnual(): bool
    {
        return $this->interval === 'year';
    }

    public function isMonthly(): bool
    {
        return $this->interval === 'month';
    }

    public static function getPopularPlan(): ?self
    {
        return static::active()
            ->where('slug', 'professional')
            ->first();
    }

    public static function getStarterPlan(): ?self
    {
        return static::active()
            ->where('slug', 'starter')
            ->first();
    }

    public function toArray(): array
    {
        $array = parent::toArray();
        $array['price_formatted'] = $this->getPriceFormatted();
        $array['price_in_dollars'] = $this->getPriceInDollars();
        $array['monthly_price'] = $this->getMonthlyPrice();
        $array['interval_label'] = $this->getIntervalLabel();
        $array['plan_tier'] = $this->getPlanTier();
        $array['savings_percentage'] = $this->getSavingsPercentage();
        $array['is_annual'] = $this->isAnnual();
        $array['is_monthly'] = $this->isMonthly();
        $array['is_unlimited'] = $this->isUnlimited();
        
        return $array;
    }
}