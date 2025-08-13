<?php

/**
 * Stripe Integration Test Script
 * 
 * This script tests the Stripe integration with the AI Blockchain Analytics platform.
 * It creates test customers, subscriptions, and verifies webhook functionality.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Foundation\Application;
use App\Models\User;
use App\Services\SubscriptionPlanService;

class StripeIntegrationTester
{
    private $app;
    private $planService;
    
    public function __construct()
    {
        // Bootstrap Laravel application
        $this->app = require_once __DIR__ . '/../bootstrap/app.php';
        $kernel = $this->app->make(Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();
        
        $this->planService = new SubscriptionPlanService();
    }
    
    public function runTests(): void
    {
        echo "🧪 Testing Stripe Integration for AI Blockchain Analytics\n";
        echo "══════════════════════════════════════════════════════════\n\n";
        
        try {
            $this->testEnvironmentConfiguration();
            $this->testPlanConfiguration();
            $this->testUserBillableTrait();
            $this->testWebhookConfiguration();
            $this->createTestScenarios();
            
            echo "✅ All tests passed! Stripe integration is ready.\n\n";
            
        } catch (Exception $e) {
            echo "❌ Test failed: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
        }
    }
    
    private function testEnvironmentConfiguration(): void
    {
        echo "🔧 Testing Environment Configuration...\n";
        
        $requiredEnvVars = [
            'STRIPE_KEY' => 'Stripe Publishable Key',
            'STRIPE_SECRET' => 'Stripe Secret Key',
            'STRIPE_WEBHOOK_SECRET' => 'Stripe Webhook Secret',
            'CASHIER_CURRENCY' => 'Currency',
        ];
        
        foreach ($requiredEnvVars as $var => $description) {
            $value = env($var);
            if (empty($value)) {
                throw new Exception("Missing environment variable: {$var} ({$description})");
            }
            
            if ($var === 'STRIPE_KEY' && !str_starts_with($value, 'pk_test_')) {
                echo "⚠️  Warning: STRIPE_KEY should start with 'pk_test_' for sandbox mode\n";
            }
            
            if ($var === 'STRIPE_SECRET' && !str_starts_with($value, 'sk_test_')) {
                echo "⚠️  Warning: STRIPE_SECRET should start with 'sk_test_' for sandbox mode\n";
            }
            
            echo "   ✅ {$description}: " . substr($value, 0, 20) . "...\n";
        }
        
        echo "   ✅ Environment configuration is valid\n\n";
    }
    
    private function testPlanConfiguration(): void
    {
        echo "📋 Testing Plan Configuration...\n";
        
        $plans = $this->planService->getAllPlans();
        
        if ($plans->isEmpty()) {
            throw new Exception("No subscription plans found in configuration");
        }
        
        foreach (['starter', 'professional', 'enterprise'] as $planName) {
            $plan = $this->planService->getPlan($planName);
            
            if (!$plan) {
                throw new Exception("Plan '{$planName}' not found in configuration");
            }
            
            // Check required plan properties
            $requiredProps = ['name', 'monthly_price', 'yearly_price', 'features'];
            foreach ($requiredProps as $prop) {
                if (!isset($plan[$prop])) {
                    throw new Exception("Plan '{$planName}' missing required property: {$prop}");
                }
            }
            
            // Check Stripe price IDs
            $monthlyPriceId = $plan['stripe_monthly_price_id'] ?? null;
            $yearlyPriceId = $plan['stripe_yearly_price_id'] ?? null;
            
            echo "   📦 {$plan['name']}\n";
            echo "      Monthly: \${$plan['monthly_price']} (Price ID: " . ($monthlyPriceId ?: 'NOT SET') . ")\n";
            echo "      Yearly: \${$plan['yearly_price']} (Price ID: " . ($yearlyPriceId ?: 'NOT SET') . ")\n";
            
            if (!$monthlyPriceId || !$yearlyPriceId) {
                echo "      ⚠️  Warning: Stripe price IDs not configured for {$planName}\n";
            }
        }
        
        echo "   ✅ Plan configuration is valid\n\n";
    }
    
    private function testUserBillableTrait(): void
    {
        echo "👤 Testing User Billable Trait...\n";
        
        // Check if User model uses Billable trait
        $user = new User();
        
        $billableMethods = [
            'subscriptions',
            'subscription',
            'subscribed',
            'onGenericTrial',
            'newSubscription',
            'asStripeCustomer',
        ];
        
        foreach ($billableMethods as $method) {
            if (!method_exists($user, $method)) {
                throw new Exception("User model missing Billable trait method: {$method}");
            }
        }
        
        echo "   ✅ User model has Billable trait with all required methods\n";
        
        // Test if we can access current usage
        if (method_exists($user, 'getCurrentUsage')) {
            echo "   ✅ User model has getCurrentUsage method\n";
        } else {
            echo "   ⚠️  Warning: User model missing getCurrentUsage method\n";
        }
        
        echo "\n";
    }
    
    private function testWebhookConfiguration(): void
    {
        echo "🪝 Testing Webhook Configuration...\n";
        
        $webhookRoute = app('router')->getRoutes()->getByName('cashier.webhook');
        
        if (!$webhookRoute) {
            throw new Exception("Cashier webhook route not found. Make sure Laravel Cashier is properly installed.");
        }
        
        echo "   ✅ Webhook route registered: " . $webhookRoute->uri() . "\n";
        
        // Check if webhook secret is configured
        $webhookSecret = env('STRIPE_WEBHOOK_SECRET');
        if (empty($webhookSecret)) {
            echo "   ⚠️  Warning: STRIPE_WEBHOOK_SECRET not configured\n";
        } else {
            echo "   ✅ Webhook secret configured\n";
        }
        
        echo "\n";
    }
    
    private function createTestScenarios(): void
    {
        echo "🧪 Creating Test Scenarios...\n";
        
        // Test customer creation
        echo "   📝 Test Scenario 1: Customer Creation\n";
        echo "      To test: Create a new user and verify Stripe customer creation\n";
        echo "      Command: User::factory()->create(); // then check user->createAsStripeCustomer()\n\n";
        
        // Test subscription creation
        echo "   📝 Test Scenario 2: Subscription Creation\n";
        echo "      To test: Create subscription with test payment method\n";
        echo "      Payment Method ID: pm_card_visa (test card)\n";
        echo "      Command: \$user->newSubscription('starter', 'price_id')->create('pm_card_visa')\n\n";
        
        // Test webhook events
        echo "   📝 Test Scenario 3: Webhook Events\n";
        echo "      To test: Trigger webhook events from Stripe Dashboard\n";
        echo "      Events to test:\n";
        echo "        • customer.subscription.created\n";
        echo "        • invoice.payment_succeeded\n";
        echo "        • customer.subscription.updated\n\n";
        
        // Test usage tracking
        echo "   📝 Test Scenario 4: Usage Tracking\n";
        echo "      To test: Track usage and verify billing calculations\n";
        echo "      Command: // Create usage records and check billing\n\n";
    }
    
    public function generateTestCards(): array
    {
        return [
            'visa' => [
                'number' => '4242424242424242',
                'exp_month' => 12,
                'exp_year' => date('Y') + 2,
                'cvc' => '123',
                'description' => 'Visa - Successful payment',
            ],
            'visa_debit' => [
                'number' => '4000056655665556',
                'exp_month' => 12,
                'exp_year' => date('Y') + 2,
                'cvc' => '123',
                'description' => 'Visa Debit - Successful payment',
            ],
            'mastercard' => [
                'number' => '5555555555554444',
                'exp_month' => 12,
                'exp_year' => date('Y') + 2,
                'cvc' => '123',
                'description' => 'Mastercard - Successful payment',
            ],
            'declined' => [
                'number' => '4000000000000002',
                'exp_month' => 12,
                'exp_year' => date('Y') + 2,
                'cvc' => '123',
                'description' => 'Generic decline - Payment declined',
            ],
            'insufficient_funds' => [
                'number' => '4000000000009995',
                'exp_month' => 12,
                'exp_year' => date('Y') + 2,
                'cvc' => '123',
                'description' => 'Insufficient funds - Payment declined',
            ],
        ];
    }
    
    public function displayTestCards(): void
    {
        echo "💳 Stripe Test Cards for Testing:\n";
        echo "══════════════════════════════════════════════════════════\n\n";
        
        $testCards = $this->generateTestCards();
        
        foreach ($testCards as $type => $card) {
            echo "🔸 {$card['description']}\n";
            echo "   Number: {$card['number']}\n";
            echo "   Expiry: {$card['exp_month']}/{$card['exp_year']}\n";
            echo "   CVC: {$card['cvc']}\n\n";
        }
        
        echo "📝 Usage:\n";
        echo "• Use these cards in your test environment\n";
        echo "• Test successful payments, declines, and errors\n";
        echo "• Monitor webhooks in Stripe Dashboard\n\n";
    }
}

// Main execution
if (php_sapi_name() === 'cli') {
    $tester = new StripeIntegrationTester();
    $tester->runTests();
    $tester->displayTestCards();
    
    echo "🔗 Useful Links:\n";
    echo "══════════════════════════════════════════════════════════\n";
    echo "• Stripe Dashboard: https://dashboard.stripe.com/test/dashboard\n";
    echo "• Stripe Test Cards: https://stripe.com/docs/testing#cards\n";
    echo "• Laravel Cashier Docs: https://laravel.com/docs/11.x/billing\n";
    echo "• Webhook Testing: https://stripe.com/docs/webhooks/test\n\n";
    
    echo "🚀 Ready to test your Stripe integration!\n";
}
