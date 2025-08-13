<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Project;

final class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, Billable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'onboarding_emails_enabled',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'onboarding_emails_enabled' => 'boolean',
        ];
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function usageMetrics(): HasMany
    {
        return $this->hasMany(UsageMetric::class);
    }

    public function billingUsages(): HasMany
    {
        return $this->hasMany(BillingUsage::class);
    }

    public function onboardingEmailLogs(): HasMany
    {
        return $this->hasMany(OnboardingEmailLog::class);
    }

    /**
     * Get current month's usage
     */
    public function getCurrentUsage(): BillingUsage
    {
        return BillingUsage::getCurrentPeriodUsage($this->id);
    }

    /**
     * Check if user can perform an action based on their subscription
     */
    public function canPerformAction(string $action): bool
    {
        if (!$this->subscribed()) {
            return false;
        }

        $subscription = $this->subscription();
        $planLimits = $this->getSubscriptionLimits($subscription->name);
        $usage = $this->getCurrentUsage();

        return match($action) {
            'analysis' => $usage->analysis_count < $planLimits['analysis_limit'],
            'api_call' => $usage->api_calls_count < $planLimits['api_calls_limit'],
            default => true
        };
    }

    /**
     * Get subscription limits based on plan name
     */
    public function getSubscriptionLimits(string $planName): array
    {
        return match($planName) {
            'starter' => [
                'analysis_limit' => (int) config('billing.starter.analysis_limit', 10),
                'api_calls_limit' => (int) config('billing.starter.api_calls_limit', 1000),
                'tokens_limit' => (int) config('billing.starter.tokens_limit', 50000),
            ],
            'professional' => [
                'analysis_limit' => (int) config('billing.professional.analysis_limit', 100),
                'api_calls_limit' => (int) config('billing.professional.api_calls_limit', 10000),
                'tokens_limit' => (int) config('billing.professional.tokens_limit', 500000),
            ],
            'enterprise' => [
                'analysis_limit' => (int) config('billing.enterprise.analysis_limit', 1000),
                'api_calls_limit' => (int) config('billing.enterprise.api_calls_limit', 100000),
                'tokens_limit' => (int) config('billing.enterprise.tokens_limit', 5000000),
            ],
            default => [
                'analysis_limit' => 3,
                'api_calls_limit' => 100,
                'tokens_limit' => 10000,
            ]
        };
    }

    /**
     * Record usage for billing
     */
    public function recordUsage(string $type, int $quantity = 1, float $cost = 0, array $metadata = []): void
    {
        // Record detailed usage metric
        UsageMetric::create([
            'user_id' => $this->id,
            'metric_type' => $type,
            'quantity' => $quantity,
            'cost' => $cost,
            'metadata' => $metadata,
            'usage_date' => now()->toDateString(),
        ]);

        // Update billing usage summary
        $billing = $this->getCurrentUsage();
        $billing->addUsage($type, $quantity, $cost, $metadata);
    }
}
