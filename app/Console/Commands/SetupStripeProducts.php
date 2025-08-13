<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Stripe\StripeClient;

final class SetupStripeProducts extends Command
{
    protected $signature = 'stripe:setup-products';
    protected $description = 'Create Stripe products and prices for subscription plans';

    public function handle(): int
    {
        if (!config('cashier.secret')) {
            $this->error('Stripe secret key not configured. Please set STRIPE_SECRET in your .env file.');
            return 1;
        }

        $this->info('Setting up Stripe products and prices...');
        
        $stripe = new StripeClient(config('cashier.secret'));
        $plans = config('billing.plans');
        $priceIds = [];
        
        foreach ($plans as $planKey => $planConfig) {
            $this->info("Creating product for {$planConfig['name']} plan...");
            
            try {
                // Create or update product
                $product = $stripe->products->create([
                    'name' => $planConfig['name'],
                    'description' => $planConfig['description'],
                    'metadata' => [
                        'plan_key' => $planKey,
                        'analysis_limit' => $planConfig['features']['analysis_limit'],
                        'api_calls_limit' => $planConfig['features']['api_calls_limit'],
                        'tokens_limit' => $planConfig['features']['tokens_limit'],
                    ],
                ]);
                
                $this->info("Product created: {$product->id}");
                
                // Create monthly price
                $monthlyPrice = $stripe->prices->create([
                    'product' => $product->id,
                    'unit_amount' => $planConfig['monthly_price'] * 100, // Convert to cents
                    'currency' => config('billing.currency', 'usd'),
                    'recurring' => [
                        'interval' => 'month',
                    ],
                    'nickname' => "{$planConfig['name']} Monthly",
                    'metadata' => [
                        'plan_key' => $planKey,
                        'interval' => 'monthly',
                    ],
                ]);
                
                $priceIds["STRIPE_PRICE_" . strtoupper($planKey) . "_MONTHLY"] = $monthlyPrice->id;
                $this->info("Monthly price created: {$monthlyPrice->id}");
                
                // Create yearly price
                $yearlyPrice = $stripe->prices->create([
                    'product' => $product->id,
                    'unit_amount' => $planConfig['yearly_price'] * 100, // Convert to cents
                    'currency' => config('billing.currency', 'usd'),
                    'recurring' => [
                        'interval' => 'year',
                    ],
                    'nickname' => "{$planConfig['name']} Yearly",
                    'metadata' => [
                        'plan_key' => $planKey,
                        'interval' => 'yearly',
                    ],
                ]);
                
                $priceIds["STRIPE_PRICE_" . strtoupper($planKey) . "_YEARLY"] = $yearlyPrice->id;
                $this->info("Yearly price created: {$yearlyPrice->id}");
                
            } catch (\Exception $e) {
                $this->error("Error creating product for {$planKey}: " . $e->getMessage());
                continue;
            }
        }
        
        $this->newLine();
        $this->info('All products and prices created successfully!');
        $this->newLine();
        $this->info('Please update your .env file with the following price IDs:');
        $this->newLine();
        
        foreach ($priceIds as $key => $priceId) {
            $this->line("{$key}={$priceId}");
        }
        
        $this->newLine();
        $this->info('Remember to update your webhook endpoint in Stripe Dashboard to:');
        $this->line(route('stripe.webhook'));
        
        return 0;
    }
}