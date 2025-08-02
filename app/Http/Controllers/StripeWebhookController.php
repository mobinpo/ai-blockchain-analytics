<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;

final class StripeWebhookController extends CashierWebhookController
{
    /**
     * Handle a Stripe webhook call.
     */
    public function handleWebhook(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sigHeader,
                $endpointSecret
            );
        } catch (\UnexpectedValueException $e) {
            Log::error('Invalid payload in Stripe webhook', ['error' => $e->getMessage()]);
            return response('Invalid payload', 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::error('Invalid signature in Stripe webhook', ['error' => $e->getMessage()]);
            return response('Invalid signature', 400);
        }

        // Handle the event
        return parent::handleWebhook($request);
    }

    /**
     * Handle a successful subscription creation event.
     */
    public function handleCustomerSubscriptionCreated(array $payload): void
    {
        $subscription = $payload['data']['object'];
        $customerId = $subscription['customer'];
        
        $user = User::where('stripe_id', $customerId)->first();
        
        if ($user) {
            Log::info('Subscription created for user', [
                'user_id' => $user->id,
                'subscription_id' => $subscription['id'],
                'status' => $subscription['status']
            ]);

            // Send welcome email
            // Mail::to($user)->send(new SubscriptionCreated($user, $subscription));
        }
    }

    /**
     * Handle subscription update events.
     */
    public function handleCustomerSubscriptionUpdated(array $payload): void
    {
        $subscription = $payload['data']['object'];
        $customerId = $subscription['customer'];
        
        $user = User::where('stripe_id', $customerId)->first();
        
        if ($user) {
            Log::info('Subscription updated for user', [
                'user_id' => $user->id,
                'subscription_id' => $subscription['id'],
                'status' => $subscription['status'],
                'current_period_end' => $subscription['current_period_end']
            ]);

            // Handle subscription status changes
            if ($subscription['status'] === 'active' && $subscription['cancel_at_period_end']) {
                Log::info('Subscription scheduled for cancellation', [
                    'user_id' => $user->id,
                    'ends_at' => $subscription['current_period_end']
                ]);
            }
        }
    }

    /**
     * Handle subscription cancellation events.
     */
    public function handleCustomerSubscriptionDeleted(array $payload): void
    {
        $subscription = $payload['data']['object'];
        $customerId = $subscription['customer'];
        
        $user = User::where('stripe_id', $customerId)->first();
        
        if ($user) {
            Log::info('Subscription cancelled for user', [
                'user_id' => $user->id,
                'subscription_id' => $subscription['id'],
                'cancelled_at' => $subscription['canceled_at']
            ]);

            // Send cancellation email
            // Mail::to($user)->send(new SubscriptionCancelled($user, $subscription));
        }
    }

    /**
     * Handle successful payment events.
     */
    public function handleInvoicePaymentSucceeded(array $payload): void
    {
        $invoice = $payload['data']['object'];
        $customerId = $invoice['customer'];
        
        $user = User::where('stripe_id', $customerId)->first();
        
        if ($user) {
            Log::info('Payment succeeded for user', [
                'user_id' => $user->id,
                'invoice_id' => $invoice['id'],
                'amount_paid' => $invoice['amount_paid'],
                'currency' => $invoice['currency']
            ]);

            // Send payment confirmation email
            // Mail::to($user)->send(new PaymentSucceeded($user, $invoice));
        }
    }

    /**
     * Handle failed payment events.
     */
    public function handleInvoicePaymentFailed(array $payload): void
    {
        $invoice = $payload['data']['object'];
        $customerId = $invoice['customer'];
        
        $user = User::where('stripe_id', $customerId)->first();
        
        if ($user) {
            Log::warning('Payment failed for user', [
                'user_id' => $user->id,
                'invoice_id' => $invoice['id'],
                'amount_due' => $invoice['amount_due'],
                'attempt_count' => $invoice['attempt_count']
            ]);

            // Send failed payment notification
            // Mail::to($user)->send(new PaymentFailed($user, $invoice));
        }
    }

    /**
     * Handle payment method attachment events.
     */
    public function handlePaymentMethodAttached(array $payload): void
    {
        $paymentMethod = $payload['data']['object'];
        $customerId = $paymentMethod['customer'];
        
        $user = User::where('stripe_id', $customerId)->first();
        
        if ($user) {
            Log::info('Payment method attached for user', [
                'user_id' => $user->id,
                'payment_method_id' => $paymentMethod['id'],
                'type' => $paymentMethod['type']
            ]);
        }
    }

    /**
     * Handle customer update events.
     */
    public function handleCustomerUpdated(array $payload): void
    {
        $customer = $payload['data']['object'];
        
        $user = User::where('stripe_id', $customer['id'])->first();
        
        if ($user) {
            Log::info('Customer updated', [
                'user_id' => $user->id,
                'customer_id' => $customer['id'],
                'email' => $customer['email']
            ]);

            // Sync customer data if needed
            if ($user->email !== $customer['email']) {
                Log::info('Customer email mismatch detected', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'stripe_email' => $customer['email']
                ]);
            }
        }
    }

    /**
     * Handle subscription trial will end events.
     */
    public function handleCustomerSubscriptionTrialWillEnd(array $payload): void
    {
        $subscription = $payload['data']['object'];
        $customerId = $subscription['customer'];
        
        $user = User::where('stripe_id', $customerId)->first();
        
        if ($user) {
            Log::info('Trial ending soon for user', [
                'user_id' => $user->id,
                'subscription_id' => $subscription['id'],
                'trial_end' => $subscription['trial_end']
            ]);

            // Send trial ending notification
            // Mail::to($user)->send(new TrialEndingSoon($user, $subscription));
        }
    }

    /**
     * Handle setup intent events.
     */
    public function handleSetupIntentSucceeded(array $payload): void
    {
        $setupIntent = $payload['data']['object'];
        $customerId = $setupIntent['customer'];
        
        if ($customerId) {
            $user = User::where('stripe_id', $customerId)->first();
            
            if ($user) {
                Log::info('Setup intent succeeded for user', [
                    'user_id' => $user->id,
                    'setup_intent_id' => $setupIntent['id'],
                    'payment_method' => $setupIntent['payment_method']
                ]);
            }
        }
    }
} 