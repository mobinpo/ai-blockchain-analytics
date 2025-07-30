<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'name',
        'stripe_id',
        'price',
        'currency',
        'interval',
        'features',
        'analysis_limit',
        'project_limit',
        'is_active',
    ];

    protected $casts = [
        'features' => 'array',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public const PLANS = [
        'starter' => [
            'name' => 'Starter',
            'price' => 29.00,
            'analysis_limit' => 10,
            'project_limit' => 3,
            'features' => [
                'Basic blockchain analysis',
                'Smart contract scanning',
                'Email support',
                'API access (100 calls/day)',
            ],
        ],
        'professional' => [
            'name' => 'Professional',
            'price' => 99.00,
            'analysis_limit' => 100,
            'project_limit' => 15,
            'features' => [
                'Advanced blockchain analysis',
                'Smart contract scanning',
                'Sentiment analysis',
                'Real-time monitoring',
                'Priority support',
                'API access (1000 calls/day)',
                'Custom reports',
            ],
        ],
        'enterprise' => [
            'name' => 'Enterprise',
            'price' => 299.00,
            'analysis_limit' => -1, // unlimited
            'project_limit' => -1, // unlimited
            'features' => [
                'All Professional features',
                'Unlimited analysis',
                'White-label reports',
                'Dedicated account manager',
                'Custom integrations',
                'SLA guarantee',
                'API access (unlimited)',
                'Advanced analytics',
            ],
        ],
    ];

    public function isFeatureEnabled(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }

    public function hasAnalysisLimit(): bool
    {
        return $this->analysis_limit !== -1;
    }

    public function hasProjectLimit(): bool
    {
        return $this->project_limit !== -1;
    }

    public function getPriceFormatted(): string
    {
        return '$' . number_format($this->price, 2);
    }
}