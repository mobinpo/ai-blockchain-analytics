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
            $currentSubscription = [
                'name' => $subscription->name,
                'status' => $subscription->stripe_status,
                'trial_ends_at' => $subscription->trial_ends_at,
                'ends_at' => $subscription->ends_at,
                'on_trial' => $subscription->onTrial(),
                'cancelled' => $subscription->cancelled(),
                'on_grace_period' => $subscription->onGracePeriod(),
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
                $subscription->name,
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
            'currentPlan' => Auth::user()->subscribed() ? Auth::user()->subscription()->name : null,
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
            // Add payment method
            $user->addPaymentMethod($request->payment_method);

            // Create subscription
            $subscription = $user->newSubscription($plan, $priceId)
                ->create($request->payment_method);

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
            return response()->json([
                'success' => false,
                'error' => $exception->getMessage(),
            ], 400);

        } catch (\Exception $exception) {
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while processing your subscription.',
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
                        'date' => $invoice->created,
                        'total' => $invoice->total(),
                        'status' => $invoice->status,
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
        
        $planName = $user->subscribed() ? $user->subscription()->name : null;
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
