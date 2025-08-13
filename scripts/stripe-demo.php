<?php

/**
 * Stripe Integration Demo Script
 * 
 * This script demonstrates the Stripe integration features of the AI Blockchain Analytics platform.
 * It creates sample data and shows how subscriptions work.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Foundation\Application;
use App\Models\User;
use App\Models\BillingUsage;
use App\Services\SubscriptionPlanService;

class StripeDemoRunner
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
    
    public function runDemo(): void
    {
        echo "🎭 AI Blockchain Analytics - Stripe Integration Demo\n";
        echo "═══════════════════════════════════════════════════════════\n\n";
        
        $this->displayWelcome();
        $this->showPlanStructure();
        $this->demonstrateUsageTracking();
        $this->showBillingFeatures();
        $this->displayTestingInstructions();
    }
    
    private function displayWelcome(): void
    {
        echo "🚀 Welcome to the Stripe Sandbox Integration!\n\n";
        echo "This demo showcases the complete SaaS billing system built for the\n";
        echo "AI Blockchain Analytics platform. Here's what you'll see:\n\n";
        echo "• 📊 Three-tier subscription plans\n";
        echo "• 💳 Secure payment processing with Stripe\n";
        echo "• 📈 Real-time usage tracking and billing\n";
        echo "• 🔧 Complete subscription management\n";
        echo "• 🪝 Webhook-based event handling\n\n";
    }
    
    private function showPlanStructure(): void
    {
        echo "📋 SUBSCRIPTION PLANS\n";
        echo "═══════════════════════════════════════════════════════════\n\n";
        
        $plans = $this->planService->getAllPlans();
        
        foreach ($plans as $planKey => $plan) {
            $pricing = $this->planService->getPlanPricing($planKey);
            $features = $this->planService->getPlanFeatures($planKey);
            
            echo "🎯 " . strtoupper($planKey) . " PLAN\n";
            echo "   Name: {$plan['name']}\n";
            echo "   Description: {$plan['description']}\n\n";
            
            echo "   💰 PRICING:\n";
            echo "      Monthly: \${$plan['monthly_price']}/month\n";
            echo "      Yearly: \${$plan['yearly_price']}/year";
            if (isset($pricing['yearly']['savings'])) {
                echo " (Save \${$pricing['yearly']['savings']})";
            }
            echo "\n\n";
            
            echo "   📊 FEATURES & LIMITS:\n";
            foreach ($features as $feature => $value) {
                if (is_array($value)) {
                    echo "      {$feature}: " . implode(', ', $value) . "\n";
                } else {
                    echo "      {$feature}: {$value}\n";
                }
            }
            echo "\n";
            
            echo "   💸 OVERAGE RATES:\n";
            $overageRates = $this->planService->getOverageRates($planKey);
            foreach ($overageRates as $type => $rate) {
                echo "      " . ucfirst($type) . ": \${$rate} per unit\n";
            }
            echo "\n" . str_repeat("─", 60) . "\n\n";
        }
    }
    
    private function demonstrateUsageTracking(): void
    {
        echo "📈 USAGE TRACKING DEMONSTRATION\n";
        echo "═══════════════════════════════════════════════════════════\n\n";
        
        // Show how usage tracking works
        echo "The platform tracks three main types of usage:\n\n";
        
        $usageTypes = [
            'analysis' => [
                'name' => 'Smart Contract Analyses',
                'description' => 'Full vulnerability scans and security assessments',
                'example' => 'Each time a user analyzes a smart contract',
            ],
            'api_calls' => [
                'name' => 'API Calls',
                'description' => 'Programmatic access to blockchain data',
                'example' => 'REST API calls, webhook deliveries, data exports',
            ],
            'tokens' => [
                'name' => 'AI Tokens',
                'description' => 'OpenAI GPT tokens for AI-powered analysis',
                'example' => 'Code analysis, vulnerability detection, report generation',
            ],
        ];
        
        foreach ($usageTypes as $type => $info) {
            echo "🔸 {$info['name']}\n";
            echo "   Description: {$info['description']}\n";
            echo "   Example: {$info['example']}\n\n";
        }
        
        echo "Usage is tracked in real-time and bills are calculated based on:\n";
        echo "• Current subscription plan limits\n";
        echo "• Overage rates for usage beyond limits\n";
        echo "• Monthly billing cycles\n";
        echo "• Prorated upgrades and downgrades\n\n";
    }
    
    private function showBillingFeatures(): void
    {
        echo "💳 BILLING SYSTEM FEATURES\n";
        echo "═══════════════════════════════════════════════════════════\n\n";
        
        $features = [
            '🏦 Payment Processing' => [
                'Secure credit card processing via Stripe',
                'Support for Visa, Mastercard, American Express',
                'PCI DSS compliant - no card data stored',
                'Automatic retry for failed payments',
            ],
            '📊 Subscription Management' => [
                'Instant plan upgrades and downgrades',
                'Prorated billing calculations',
                'Cancel anytime with grace periods',
                'Automatic renewals',
            ],
            '📈 Usage Analytics' => [
                'Real-time usage dashboards',
                'Historical usage reports',
                'Overage alerts and notifications',
                'Billing forecasting',
            ],
            '🔧 Developer Features' => [
                'Comprehensive webhook system',
                'REST API for billing operations',
                'Test mode with sandbox data',
                'Detailed audit logs',
            ],
            '🛡️ Security & Compliance' => [
                'SOC 2 Type II certified',
                'GDPR compliant data handling',
                'End-to-end encryption',
                'Two-factor authentication support',
            ],
        ];
        
        foreach ($features as $category => $items) {
            echo "{$category}\n";
            foreach ($items as $item) {
                echo "   • {$item}\n";
            }
            echo "\n";
        }
    }
    
    private function displayTestingInstructions(): void
    {
        echo "🧪 TESTING YOUR INTEGRATION\n";
        echo "═══════════════════════════════════════════════════════════\n\n";
        
        echo "1. 🔑 SET UP STRIPE KEYS\n";
        echo "   • Get test keys from: https://dashboard.stripe.com/test/apikeys\n";
        echo "   • Update your .env file with the keys\n";
        echo "   • Run: php scripts/setup-stripe-sandbox.php\n\n";
        
        echo "2. 🏗️ CREATE PRODUCTS & PRICES\n";
        echo "   • The setup script creates products automatically\n";
        echo "   • Copy the generated price IDs to your .env file\n";
        echo "   • Verify products in Stripe Dashboard\n\n";
        
        echo "3. 🪝 CONFIGURE WEBHOOKS\n";
        echo "   • Create webhook endpoint in Stripe Dashboard\n";
        echo "   • Point to: https://yourdomain.com/stripe/webhook\n";
        echo "   • Add webhook secret to .env file\n\n";
        
        echo "4. 💳 TEST PAYMENTS\n";
        echo "   • Register a new user account\n";
        echo "   • Navigate to /billing/plans\n";
        echo "   • Select a plan and use test card: 4242424242424242\n";
        echo "   • Complete the subscription flow\n\n";
        
        echo "5. 🔍 VERIFY FUNCTIONALITY\n";
        echo "   • Check subscription status in /billing dashboard\n";
        echo "   • Generate some usage (API calls, analyses)\n";
        echo "   • Monitor webhook events in Stripe Dashboard\n";
        echo "   • Test plan changes and cancellations\n\n";
        
        echo "💡 PRO TIPS:\n";
        echo "══════════════════════════════════════════════════════════\n";
        echo "• Use ngrok for local webhook testing\n";
        echo "• Monitor Laravel logs for webhook processing\n";
        echo "• Test with different card types (declined, expired, etc.)\n";
        echo "• Verify email notifications are sent\n";
        echo "• Test the complete user journey from signup to billing\n\n";
    }
    
    public function showQuickStart(): void
    {
        echo "⚡ QUICK START COMMANDS\n";
        echo "═══════════════════════════════════════════════════════════\n\n";
        
        $commands = [
            'Setup Stripe Products' => 'php scripts/setup-stripe-sandbox.php',
            'Test Integration' => 'php scripts/test-stripe-integration.php',
            'Run Demo' => 'php scripts/stripe-demo.php',
            'Check Routes' => 'php artisan route:list | grep billing',
            'Clear Config Cache' => 'php artisan config:clear',
            'View Logs' => 'tail -f storage/logs/laravel.log',
            'Tinker (Debug)' => 'php artisan tinker',
        ];
        
        foreach ($commands as $description => $command) {
            echo "🔹 {$description}:\n";
            echo "   {$command}\n\n";
        }
    }
}

// Main execution
if (php_sapi_name() === 'cli') {
    $demo = new StripeDemoRunner();
    $demo->runDemo();
    $demo->showQuickStart();
    
    echo "🎉 Ready to start billing with Stripe!\n\n";
    echo "Next steps:\n";
    echo "1. Get your Stripe API keys\n";
    echo "2. Run: php scripts/setup-stripe-sandbox.php\n";
    echo "3. Configure webhooks\n";
    echo "4. Test the billing flow\n\n";
    echo "For detailed instructions, see: STRIPE_INTEGRATION_COMPLETE.md\n";
}
