<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Stripe\StripeClient;

class SubscriptionController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $subscription = $user->subscription('default');
        $plans = SubscriptionPlan::where('is_active', true)->get();

        return Inertia::render('Subscription/Index', [
            'subscription' => $subscription?->toArray(),
            'plans' => $plans,
            'intent' => $user->createSetupIntent()
        ]);
    }

    public function subscribe(Request $request)
    {
        $request->validate([
            'plan' => 'required|exists:subscription_plans,stripe_id',
            'payment_method' => 'required|string'
        ]);

        $user = auth()->user();
        $plan = SubscriptionPlan::where('stripe_id', $request->plan)->firstOrFail();

        try {
            $user->newSubscription('default', $request->plan)
                ->create($request->payment_method);

            return redirect()->route('subscription.index')
                ->with('success', "Successfully subscribed to {$plan->name} plan!");
        } catch (\Exception $e) {
            return back()->withErrors(['subscription' => 'Failed to create subscription: ' . $e->getMessage()]);
        }
    }

    public function update(Request $request)
    {
        $request->validate([
            'plan' => 'required|exists:subscription_plans,stripe_id'
        ]);

        $user = auth()->user();
        $subscription = $user->subscription('default');

        if (!$subscription) {
            return back()->withErrors(['subscription' => 'No active subscription found.']);
        }

        try {
            $subscription->swap($request->plan);
            $plan = SubscriptionPlan::where('stripe_id', $request->plan)->first();

            return redirect()->route('subscription.index')
                ->with('success', "Successfully updated to {$plan->name} plan!");
        } catch (\Exception $e) {
            return back()->withErrors(['subscription' => 'Failed to update subscription: ' . $e->getMessage()]);
        }
    }

    public function cancel(Request $request)
    {
        $user = auth()->user();
        $subscription = $user->subscription('default');

        if (!$subscription) {
            return back()->withErrors(['subscription' => 'No active subscription found.']);
        }

        try {
            if ($request->immediate) {
                $subscription->cancelNow();
                $message = 'Subscription cancelled immediately.';
            } else {
                $subscription->cancel();
                $message = 'Subscription will be cancelled at the end of the billing period.';
            }

            return redirect()->route('subscription.index')
                ->with('success', $message);
        } catch (\Exception $e) {
            return back()->withErrors(['subscription' => 'Failed to cancel subscription: ' . $e->getMessage()]);
        }
    }

    public function resume()
    {
        $user = auth()->user();
        $subscription = $user->subscription('default');

        if (!$subscription || !$subscription->cancelled()) {
            return back()->withErrors(['subscription' => 'No cancelled subscription found.']);
        }

        try {
            $subscription->resume();

            return redirect()->route('subscription.index')
                ->with('success', 'Subscription resumed successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['subscription' => 'Failed to resume subscription: ' . $e->getMessage()]);
        }
    }

    public function invoices()
    {
        $user = auth()->user();
        $invoices = $user->invoices();

        return Inertia::render('Subscription/Invoices', [
            'invoices' => $invoices
        ]);
    }

    public function downloadInvoice(Request $request, string $invoiceId)
    {
        $user = auth()->user();
        
        return $user->downloadInvoice($invoiceId, [
            'vendor' => config('app.name'),
            'product' => 'AI Blockchain Analytics',
        ]);
    }
}