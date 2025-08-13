<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

final class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            // Starter Plans
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'stripe_price_id' => config('services.stripe.prices.starter_monthly'),
                'price' => 2900, // in cents
                'currency' => 'usd',
                'interval' => 'month',
                'interval_count' => 1,
                'trial_period_days' => 14,
                'analysis_limit' => 10,
                'project_limit' => 3,
                'features' => [
                    'Basic blockchain analysis',
                    'Smart contract scanning',
                    'Email support',
                    'API access (100 calls/day)',
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Starter Annual',
                'slug' => 'starter-annual',
                'stripe_price_id' => config('services.stripe.prices.starter_yearly'),
                'price' => 29000, // $290/year (save $58)
                'currency' => 'usd',
                'interval' => 'year',
                'interval_count' => 1,
                'trial_period_days' => 14,
                'analysis_limit' => 10,
                'project_limit' => 3,
                'features' => [
                    'Basic blockchain analysis',
                    'Smart contract scanning',
                    'Email support',
                    'API access (100 calls/day)',
                    '2 months free (annual billing)',
                ],
                'is_active' => true,
                'sort_order' => 2,
            ],
            
            // Professional Plans
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'stripe_price_id' => config('services.stripe.prices.professional_monthly'),
                'price' => 9900, // $99/month
                'currency' => 'usd',
                'interval' => 'month',
                'interval_count' => 1,
                'trial_period_days' => 14,
                'analysis_limit' => 100,
                'project_limit' => 15,
                'features' => [
                    'Advanced blockchain analysis',
                    'Smart contract scanning',
                    'AI-powered sentiment analysis',
                    'Real-time monitoring',
                    'Priority support',
                    'API access (1,000 calls/day)',
                    'Custom reports',
                    'Advanced analytics dashboard',
                ],
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Professional Annual',
                'slug' => 'professional-annual',
                'stripe_price_id' => config('services.stripe.prices.professional_yearly'),
                'price' => 99000, // $990/year (save $198)
                'currency' => 'usd',
                'interval' => 'year',
                'interval_count' => 1,
                'trial_period_days' => 14,
                'analysis_limit' => 100,
                'project_limit' => 15,
                'features' => [
                    'Advanced blockchain analysis',
                    'Smart contract scanning',
                    'AI-powered sentiment analysis',
                    'Real-time monitoring',
                    'Priority support',
                    'API access (1,000 calls/day)',
                    'Custom reports',
                    'Advanced analytics dashboard',
                    '2 months free (annual billing)',
                ],
                'is_active' => true,
                'sort_order' => 4,
            ],
            
            // Enterprise Plans
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'stripe_price_id' => config('services.stripe.prices.enterprise_monthly'),
                'price' => 29900, // $299/month
                'currency' => 'usd',
                'interval' => 'month',
                'interval_count' => 1,
                'trial_period_days' => 30,
                'analysis_limit' => 1000,
                'project_limit' => -1, // unlimited
                'features' => [
                    'All Professional features',
                    'Unlimited projects',
                    'White-label reports',
                    'Dedicated account manager',
                    'Custom integrations',
                    'SLA guarantee (99.9% uptime)',
                    'API access (10,000 calls/day)',
                    'Advanced analytics & insights',
                    'Custom webhooks',
                    'SSO integration',
                ],
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Enterprise Annual',
                'slug' => 'enterprise-annual',
                'stripe_price_id' => config('services.stripe.prices.enterprise_yearly'),
                'price' => 299000, // $2,990/year (save $598)
                'currency' => 'usd',
                'interval' => 'year',
                'interval_count' => 1,
                'trial_period_days' => 30,
                'analysis_limit' => 1000,
                'project_limit' => -1, // unlimited
                'features' => [
                    'All Professional features',
                    'Unlimited projects',
                    'White-label reports',
                    'Dedicated account manager',
                    'Custom integrations',
                    'SLA guarantee (99.9% uptime)',
                    'API access (10,000 calls/day)',
                    'Advanced analytics & insights',
                    'Custom webhooks',
                    'SSO integration',
                    '2 months free (annual billing)',
                ],
                'is_active' => true,
                'sort_order' => 6,
            ],
        ];

        foreach ($plans as $planData) {
            // Add stripe_id_legacy as null since it's required by the database
            $planData['stripe_id_legacy'] = null;
            
            SubscriptionPlan::updateOrCreate(
                ['slug' => $planData['slug']],
                $planData
            );
        }
    }
}
