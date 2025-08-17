<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Cashier\Exceptions\IncompletePayment;

final class SubscriptionController extends Controller
{

    public function index(): Response
    {
        $user = auth()->user();
        $subscription = $user->subscription('default');
        
        // Get plans grouped by interval for pricing table
        $monthlyPlans = SubscriptionPlan::active()
            ->byInterval('month')
            ->ordered()
            ->get();
            
        $yearlyPlans = SubscriptionPlan::active()
            ->byInterval('year')
            ->ordered()
            ->get();

        return Inertia::render('Subscription/Index', [
            'subscription' => $subscription?->load('items') ?? null,
            'monthlyPlans' => $monthlyPlans,
            'yearlyPlans' => $yearlyPlans,
            'stripeKey' => config('services.stripe.key'),
            'intent' => $user->createSetupIntent(),
            'invoices' => $user->invoices(),
            'paymentMethods' => $user->paymentMethods(),
            'defaultPaymentMethod' => $user->defaultPaymentMethod(),
        ]);
    }

    public function subscribe(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'plan_slug' => 'required|exists:subscription_plans,slug',
            'payment_method' => 'required|string'
        ]);

        $user = auth()->user();
        $plan = SubscriptionPlan::where('slug', $request->plan_slug)->firstOrFail();

        // Check if user already has an active subscription
        if ($user->subscribed('default')) {
            return back()->withErrors([
                'subscription' => 'You already have an active subscription. Please cancel or change your existing subscription.'
            ]);
        }

        try {
            $subscription = $user->newSubscription('default', $plan->stripe_price_id);
            
            // Add trial if plan has trial period
            if ($plan->trial_period_days > 0) {
                $subscription->trialDays($plan->trial_period_days);
            }

            $subscription->create($request->payment_method, [
                'email' => $user->email,
                'name' => $user->name,
            ]);

            return redirect()->route('subscription.index')
                ->with('success', "Successfully subscribed to {$plan->name} plan!");
                
        } catch (IncompletePayment $exception) {
            return redirect()->route('cashier.payment', [$exception->payment->id]);
            
        } catch (\Exception $e) {
            return back()->withErrors([
                'subscription' => 'Failed to create subscription: ' . $e->getMessage()
            ]);
        }
    }

    public function change(Request $request): RedirectResponse
    {
        $request->validate([
            'plan_slug' => 'required|exists:subscription_plans,slug'
        ]);

        $user = auth()->user();
        $subscription = $user->subscription('default');
        $newPlan = SubscriptionPlan::where('slug', $request->plan_slug)->firstOrFail();

        if (!$subscription || !$subscription->active()) {
            return back()->withErrors([
                'subscription' => 'No active subscription found.'
            ]);
        }

        try {
            // Handle plan changes with prorations
            if ($request->prorate !== false) {
                $subscription->swap($newPlan->stripe_price_id);
            } else {
                $subscription->noProrate()->swap($newPlan->stripe_price_id);
            }

            return redirect()->route('subscription.index')
                ->with('success', "Successfully updated to {$newPlan->name} plan!");
                
        } catch (\Exception $e) {
            return back()->withErrors([
                'subscription' => 'Failed to update subscription: ' . $e->getMessage()
            ]);
        }
    }

    public function cancel(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $subscription = $user->subscription('default');

        if (!$subscription || !$subscription->active()) {
            return back()->withErrors([
                'subscription' => 'No active subscription found.'
            ]);
        }

        try {
            if ($request->boolean('immediate')) {
                $subscription->cancelNow();
                $message = 'Subscription cancelled immediately.';
            } else {
                $subscription->cancel();
                $endsAt = $subscription->ends_at->format('M d, Y');
                $message = "Subscription will be cancelled at the end of the billing period ({$endsAt}).";
            }

            return redirect()->route('subscription.index')
                ->with('success', $message);
                
        } catch (\Exception $e) {
            return back()->withErrors([
                'subscription' => 'Failed to cancel subscription: ' . $e->getMessage()
            ]);
        }
    }

    public function resume(): RedirectResponse
    {
        $user = auth()->user();
        $subscription = $user->subscription('default');

        if (!$subscription || !$subscription->canceled() || $subscription->ended()) {
            return back()->withErrors([
                'subscription' => 'No cancelled subscription found or subscription has already ended.'
            ]);
        }

        try {
            $subscription->resume();

            return redirect()->route('subscription.index')
                ->with('success', 'Subscription resumed successfully!');
                
        } catch (\Exception $e) {
            return back()->withErrors([
                'subscription' => 'Failed to resume subscription: ' . $e->getMessage()
            ]);
        }
    }

    public function invoices(): Response
    {
        $user = auth()->user();
        $invoices = collect($user->invoices())->map(function ($invoice) {
            return [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'amount_paid' => $invoice->amount_paid,
                'currency' => $invoice->currency,
                'status' => $invoice->status,
                'created' => $invoice->created,
                'hosted_invoice_url' => $invoice->hosted_invoice_url,
                'invoice_pdf' => $invoice->invoice_pdf,
            ];
        });

        return Inertia::render('Subscription/Invoices', [
            'invoices' => $invoices
        ]);
    }

    public function downloadInvoice(string $invoiceId): mixed
    {
        $user = auth()->user();
        
        return $user->downloadInvoice($invoiceId, [
            'vendor' => config('app.name'),
            'product' => 'AI Blockchain Analytics Subscription',
            'street' => '123 Main St',
            'location' => 'San Francisco, CA 94111',
            'phone' => '+1-555-123-4567',
            'email' => 'billing@aiblockchainanalytics.com',
        ]);
    }

    public function paymentMethods(): Response
    {
        $user = auth()->user();
        
        return Inertia::render('Subscription/PaymentMethods', [
            'paymentMethods' => $user->paymentMethods(),
            'defaultPaymentMethod' => $user->defaultPaymentMethod(),
            'intent' => $user->createSetupIntent(),
            'stripeKey' => config('services.stripe.key'),
        ]);
    }

    public function addPaymentMethod(Request $request): RedirectResponse
    {
        $request->validate([
            'payment_method' => 'required|string'
        ]);

        $user = auth()->user();
        
        try {
            $user->addPaymentMethod($request->payment_method);
            
            return redirect()->route('subscription.payment-methods')
                ->with('success', 'Payment method added successfully!');
                
        } catch (\Exception $e) {
            return back()->withErrors([
                'payment_method' => 'Failed to add payment method: ' . $e->getMessage()
            ]);
        }
    }

    public function removePaymentMethod(Request $request): RedirectResponse
    {
        $request->validate([
            'payment_method_id' => 'required|string'
        ]);

        $user = auth()->user();
        
        try {
            $paymentMethod = $user->findPaymentMethod($request->payment_method_id);
            
            if ($paymentMethod) {
                $paymentMethod->delete();
            }
            
            return redirect()->route('subscription.payment-methods')
                ->with('success', 'Payment method removed successfully!');
                
        } catch (\Exception $e) {
            return back()->withErrors([
                'payment_method' => 'Failed to remove payment method: ' . $e->getMessage()
            ]);
        }
    }

    public function setDefaultPaymentMethod(Request $request): RedirectResponse
    {
        $request->validate([
            'payment_method_id' => 'required|string'
        ]);

        $user = auth()->user();
        
        try {
            $user->updateDefaultPaymentMethod($request->payment_method_id);
            
            return redirect()->route('subscription.payment-methods')
                ->with('success', 'Default payment method updated successfully!');
                
        } catch (\Exception $e) {
            return back()->withErrors([
                'payment_method' => 'Failed to update default payment method: ' . $e->getMessage()
            ]);
        }
    }
}