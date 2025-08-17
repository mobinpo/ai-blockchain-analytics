<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SubscriptionPlanService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Stripe\Exception\CardException;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function __construct(
        private SubscriptionPlanService $planService
    ) {}

    /**
     * Get current plan name from subscription
     */
    private function getCurrentPlanName($subscription): string
    {
        if (!$subscription) {
            return 'starter';
        }

        $priceId = $subscription->stripe_price;
        $plans = config('billing.plans');

        // Map price ID to plan name
        foreach ($plans as $planKey => $plan) {
            if ($priceId === $plan['stripe_monthly_price_id'] || $priceId === $plan['stripe_yearly_price_id']) {
                return $planKey;
            }
        }

        // Default fallback
        return 'starter';
    }

    /**
     * Show billing dashboard
     */
    public function index(): Response
    {
        $user = Auth::user();
        $plans = $this->planService->getAllPlans();
        
        $currentSubscription = null;
        $currentUsage = null;
        $usagePercentages = null;
        
        if ($user->subscribed()) {
            $subscription = $user->subscription();
            $planName = $this->getCurrentPlanName($subscription);
            
            $currentSubscription = [
                'name' => $planName,
                'status' => $subscription->stripe_status,
                'trial_ends_at' => $subscription->trial_ends_at,
                'ends_at' => $subscription->ends_at,
                'on_trial' => $subscription->onTrial(),
                'cancelled' => $subscription->canceled(),
                'on_grace_period' => $subscription->onGracePeriod(),
                'stripe_price' => $subscription->stripe_price,
            ];
            
            $billingUsage = $user->getCurrentUsage();
            $currentUsage = [
                'analysis' => $billingUsage->analysis_count,
                'api_calls' => $billingUsage->api_calls_count,
                'tokens' => $billingUsage->tokens_used,
                'period_start' => $billingUsage->period_start->format('M j, Y'),
                'period_end' => $billingUsage->period_end->format('M j, Y'),
            ];
            
            $usagePercentages = $this->planService->getUsagePercentage(
                $planName,
                $currentUsage
            );
        }

        return Inertia::render('Billing/Dashboard', [
            'plans' => $plans,
            'currentSubscription' => $currentSubscription,
            'currentUsage' => $currentUsage,
            'usagePercentages' => $usagePercentages,
            'paymentMethods' => $user->paymentMethods(),
            'defaultPaymentMethod' => $user->defaultPaymentMethod(),
        ]);
    }

    /**
     * Show subscription plans
     */
    public function plans(): Response
    {
        $plans = $this->planService->getAllPlans()->map(function ($plan, $key) {
            return array_merge($plan, [
                'key' => $key,
                'features_list' => $this->planService->getPlanFeatures($key),
                'pricing' => $this->planService->getPlanPricing($key),
            ]);
        });

        return Inertia::render('Billing/Plans', [
            'plans' => $plans,
            'currentPlan' => Auth::user()->subscribed() ? $this->getCurrentPlanName(Auth::user()->subscription()) : null,
        ]);
    }

    /**
     * Create subscription
     */
    public function subscribe(Request $request): JsonResponse
    {
        $request->validate([
            'plan' => 'required|in:starter,professional,enterprise',
            'interval' => 'required|in:monthly,yearly',
            'payment_method' => 'required|string',
        ]);

        $user = Auth::user();
        $plan = $request->input('plan');
        $interval = $request->input('interval');
        
        $pricing = $this->planService->getPlanPricing($plan);
        $priceId = $pricing[$interval]['stripe_price_id'];

        if (!$priceId) {
            return response()->json(['error' => 'Invalid plan or interval'], 400);
        }

        try {
            \Log::info('Starting subscription process', [
                'user_id' => $user->id,
                'plan' => $plan,
                'interval' => $interval,
                'price_id' => $priceId,
                'payment_method' => $request->payment_method
            ]);

            // Ensure user is a Stripe customer
            if (!$user->hasStripeId()) {
                \Log::info('Creating Stripe customer for user');
                $user->createAsStripeCustomer([
                    'email' => $user->email,
                    'name' => $user->name ?? 'User',
                ]);
                \Log::info('Stripe customer created', ['stripe_id' => $user->stripe_id]);
            } else {
                \Log::info('User already has Stripe ID', ['stripe_id' => $user->stripe_id]);
            }

            // Add payment method
            \Log::info('Adding payment method to user');
            $user->addPaymentMethod($request->payment_method);
            \Log::info('Payment method added successfully');

            // Create subscription
            \Log::info('Creating subscription with price_id: ' . $priceId);
            $subscription = $user->newSubscription('default', $priceId)
                ->create($request->payment_method);
            
            // Store the plan name in Stripe metadata for easier access
            $subscription->updateStripeSubscription(['metadata' => ['plan_name' => $plan]]);
            \Log::info('Subscription created successfully', [
                'subscription_id' => $subscription->id ?? 'unknown',
                'stripe_id' => $subscription->stripe_id ?? 'unknown',
                'user_id' => $subscription->user_id ?? 'unknown',
                'type' => $subscription->type ?? 'unknown'
            ]);
            
            // Verify the subscription was saved to database
            $dbSubscription = \Laravel\Cashier\Subscription::where('user_id', $user->id)->first();
            if (!$dbSubscription) {
                \Log::error('Subscription not found in database after creation');
                throw new \Exception('Subscription was created in Stripe but not saved to database');
            }
            \Log::info('Subscription verified in database', ['db_subscription_id' => $dbSubscription->id]);

            return response()->json([
                'success' => true,
                'subscription' => $subscription,
                'message' => 'Subscription created successfully!'
            ]);

        } catch (IncompletePayment $exception) {
            return response()->json([
                'success' => false,
                'requires_action' => true,
                'payment_intent' => $exception->payment->id,
                'client_secret' => $exception->payment->client_secret,
            ]);

        } catch (CardException $exception) {
            \Log::error('Card exception during subscription', [
                'error' => $exception->getMessage(),
                'user_id' => $user->id,
                'plan' => $plan,
            ]);
            
            $errorMessage = $exception->getMessage();
            
            // Add helpful message for test card usage
            if (str_contains($errorMessage, 'real card while testing')) {
                $errorMessage .= ' Please use test card numbers like 4242 4242 4242 4242 for development.';
            }
            
            return response()->json([
                'success' => false,
                'error' => $errorMessage,
                'type' => 'card_error'
            ], 400);

        } catch (\Exception $exception) {
            \Log::error('Subscription creation failed', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'user_id' => $user->id,
                'plan' => $plan,
                'interval' => $interval,
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while processing your subscription: ' . $exception->getMessage(),
                'debug_info' => app()->environment('local') ? [
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ] : null
            ], 500);
        }
    }

    /**
     * Update subscription plan
     */
    public function updateSubscription(Request $request): JsonResponse
    {
        $request->validate([
            'plan' => 'required|in:starter,professional,enterprise',
            'interval' => 'required|in:monthly,yearly',
        ]);

        $user = Auth::user();
        
        if (!$user->subscribed()) {
            return response()->json(['error' => 'No active subscription found'], 400);
        }

        $plan = $request->input('plan');
        $interval = $request->input('interval');
        
        $pricing = $this->planService->getPlanPricing($plan);
        $priceId = $pricing[$interval]['stripe_price_id'];

        if (!$priceId) {
            return response()->json(['error' => 'Invalid plan or interval'], 400);
        }

        try {
            $subscription = $user->subscription();
            $subscription->swapAndInvoice($priceId);

            return response()->json([
                'success' => true,
                'message' => 'Subscription updated successfully!'
            ]);

        } catch (\Exception $exception) {
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while updating your subscription.',
            ], 500);
        }
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription(): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user->subscribed()) {
            return response()->json(['error' => 'No active subscription found'], 400);
        }

        try {
            $user->subscription()->cancel();

            return response()->json([
                'success' => true,
                'message' => 'Subscription cancelled successfully. You can continue using the service until the end of your billing period.'
            ]);

        } catch (\Exception $exception) {
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while cancelling your subscription.',
            ], 500);
        }
    }

    /**
     * Resume subscription
     */
    public function resumeSubscription(): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user->subscription() || !$user->subscription()->onGracePeriod()) {
            return response()->json(['error' => 'Cannot resume subscription'], 400);
        }

        try {
            $user->subscription()->resume();

            return response()->json([
                'success' => true,
                'message' => 'Subscription resumed successfully!'
            ]);

        } catch (\Exception $exception) {
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while resuming your subscription.',
            ], 500);
        }
    }

    /**
     * Get payment methods
     */
    public function paymentMethods(): JsonResponse
    {
        $user = Auth::user();
        
        return response()->json([
            'payment_methods' => $user->paymentMethods(),
            'default_payment_method' => $user->defaultPaymentMethod(),
        ]);
    }

    /**
     * Add payment method
     */
    public function addPaymentMethod(Request $request): JsonResponse
    {
        $request->validate([
            'payment_method' => 'required|string',
        ]);

        try {
            $user = Auth::user();
            $user->addPaymentMethod($request->payment_method);

            return response()->json([
                'success' => true,
                'message' => 'Payment method added successfully!'
            ]);

        } catch (\Exception $exception) {
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while adding the payment method.',
            ], 500);
        }
    }

    /**
     * Update default payment method
     */
    public function updateDefaultPaymentMethod(Request $request): JsonResponse
    {
        $request->validate([
            'payment_method' => 'required|string',
        ]);

        try {
            $user = Auth::user();
            $user->updateDefaultPaymentMethod($request->payment_method);

            return response()->json([
                'success' => true,
                'message' => 'Default payment method updated successfully!'
            ]);

        } catch (\Exception $exception) {
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while updating the payment method.',
            ], 500);
        }
    }

    /**
     * Delete payment method
     */
    public function deletePaymentMethod(Request $request): JsonResponse
    {
        $request->validate([
            'payment_method' => 'required|string',
        ]);

        try {
            $user = Auth::user();
            $paymentMethod = $user->findPaymentMethod($request->payment_method);
            
            if ($paymentMethod) {
                $paymentMethod->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment method deleted successfully!'
            ]);

        } catch (\Exception $exception) {
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while deleting the payment method.',
            ], 500);
        }
    }

    /**
     * Download invoice
     */
    public function downloadInvoice(Request $request, string $invoiceId)
    {
        $user = Auth::user();
        
        try {
            return $user->downloadInvoice($invoiceId, [
                'vendor' => config('app.name'),
                'product' => 'AI Blockchain Analytics Subscription',
            ]);

        } catch (\Exception $exception) {
            abort(404);
        }
    }

    /**
     * Get billing history
     */
    public function billingHistory(): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user->hasStripeId()) {
            return response()->json(['invoices' => []]);
        }

        try {
            $invoices = $user->invoices();
            
            return response()->json([
                'invoices' => $invoices->map(function ($invoice) {
                    return [
                        'id' => $invoice->id,
                        'date' => \Carbon\Carbon::createFromTimestamp($invoice->created)->format('M j, Y'),
                        'total' => $invoice->total(),
                        'status' => ucfirst($invoice->status),
                        'hosted_invoice_url' => $invoice->hosted_invoice_url,
                        'invoice_pdf' => $invoice->invoice_pdf,
                    ];
                }),
            ]);

        } catch (\Exception $exception) {
            return response()->json([
                'success' => false,
                'error' => 'Unable to fetch billing history.',
            ], 500);
        }
    }

    /**
     * Get current usage details
     */
    public function usage(): JsonResponse
    {
        $user = Auth::user();
        $billingUsage = $user->getCurrentUsage();
        
        $planName = $user->subscribed() ? $this->getCurrentPlanName($user->subscription()) : null;
        $planLimits = $planName ? 
            $user->getSubscriptionLimits($planName) : 
            $this->planService->getFreeTierLimits();

        $usagePercentages = $planName ? 
            $this->planService->getUsagePercentage($planName, [
                'analysis' => $billingUsage->analysis_count,
                'api_calls' => $billingUsage->api_calls_count,
                'tokens' => $billingUsage->tokens_used,
            ]) : [];

        return response()->json([
            'current_usage' => [
                'analysis_count' => $billingUsage->analysis_count,
                'api_calls_count' => $billingUsage->api_calls_count,
                'tokens_used' => $billingUsage->tokens_used,
                'total_cost' => $billingUsage->total_cost,
                'is_overage' => $billingUsage->is_overage,
                'overage_cost' => $billingUsage->overage_cost,
                'period_start' => $billingUsage->period_start,
                'period_end' => $billingUsage->period_end,
            ],
            'plan_limits' => $planLimits,
            'usage_percentages' => $usagePercentages,
            'plan_name' => $planName,
        ]);
    }
}
