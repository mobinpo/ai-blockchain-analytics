<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'stripe_id' => config('services.stripe.plans.starter', 'price_starter_monthly'),
                'price' => 29.00,
                'currency' => 'usd',
                'interval' => 'month',
                'analysis_limit' => 10,
                'project_limit' => 3,
                'features' => [
                    'Basic blockchain analysis',
                    'Smart contract scanning',
                    'Email support',
                    'API access (100 calls/day)',
                ],
            ],
            [
                'name' => 'Professional',
                'stripe_id' => config('services.stripe.plans.professional', 'price_professional_monthly'),
                'price' => 99.00,
                'currency' => 'usd',
                'interval' => 'month',
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
            [
                'name' => 'Enterprise',
                'stripe_id' => config('services.stripe.plans.enterprise', 'price_enterprise_monthly'),
                'price' => 299.00,
                'currency' => 'usd',
                'interval' => 'month',
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

        foreach ($plans as $planData) {
            SubscriptionPlan::updateOrCreate(
                ['stripe_id' => $planData['stripe_id']],
                $planData
            );
        }
    }
}
