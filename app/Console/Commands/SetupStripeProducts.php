<?php

namespace App\Console\Commands;

use App\Models\SubscriptionPlan;
use Illuminate\Console\Command;
use Stripe\StripeClient;

class SetupStripeProducts extends Command
{
    protected $signature = 'stripe:setup-products';
    protected $description = 'Create Stripe products and prices for subscription plans';

    public function handle(): int
    {
        if (!config('cashier.secret')) {
            $this->error('Stripe secret key not configured. Please set STRIPE_SECRET in your .env file.');
            return 1;
        }

        $stripe = new StripeClient(config('cashier.secret'));

        $plans = SubscriptionPlan::PLANS;

        foreach ($plans as $key => $planData) {
            $this->info("Creating Stripe product for {$planData['name']}...");

            try {
                $product = $stripe->products->create([
                    'name' => $planData['name'],
                    'description' => "AI Blockchain Analytics - {$planData['name']} Plan",
                    'metadata' => [
                        'plan_key' => $key,
                        'analysis_limit' => $planData['analysis_limit'],
                        'project_limit' => $planData['project_limit'],
                    ],
                ]);

                $price = $stripe->prices->create([
                    'product' => $product->id,
                    'unit_amount' => $planData['price'] * 100, // Convert to cents
                    'currency' => 'usd',
                    'recurring' => [
                        'interval' => 'month',
                    ],
                    'metadata' => [
                        'plan_key' => $key,
                    ],
                ]);

                $this->info("Created product {$product->id} with price {$price->id}");
                $this->line("Add this to your .env file: STRIPE_PLAN_" . strtoupper($key) . "={$price->id}");

                SubscriptionPlan::updateOrCreate(
                    ['name' => $planData['name']],
                    array_merge($planData, [
                        'stripe_id' => $price->id,
                        'currency' => 'usd',
                        'interval' => 'month',
                        'is_active' => true,
                    ])
                );

            } catch (\Exception $e) {
                $this->error("Failed to create product for {$planData['name']}: " . $e->getMessage());
                continue;
            }
        }

        $this->info('Stripe setup completed!');
        $this->info('Remember to update your webhook endpoint in Stripe Dashboard to: ' . route('stripe.webhook'));

        return 0;
    }
}