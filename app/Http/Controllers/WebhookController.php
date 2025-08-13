<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;
use Laravel\Cashier\Subscription;
use Illuminate\Support\Facades\Log;

class WebhookController extends CashierWebhookController
{
    /**
     * Handle customer subscription created
     */
    public function handleCustomerSubscriptionCreated(array $payload): Response
    {
        Log::info('Subscription created webhook received', $payload);
        
        if ($user = $this->getUserByStripeId($payload['data']['object']['customer'])) {
            // Track subscription creation event
            $user->recordUsage('subscription_created', 1, 0, [
                'subscription_id' => $payload['data']['object']['id'],
                'plan' => $payload['data']['object']['items']['data'][0]['price']['nickname'] ?? 'unknown',
                'status' => $payload['data']['object']['status'],
            ]);
        }

        return parent::handleCustomerSubscriptionCreated($payload);
    }

    /**
     * Handle customer subscription updated
     */
    public function handleCustomerSubscriptionUpdated(array $payload): Response
    {
        Log::info('Subscription updated webhook received', $payload);
        
        if ($user = $this->getUserByStripeId($payload['data']['object']['customer'])) {
            // Track subscription update event
            $user->recordUsage('subscription_updated', 1, 0, [
                'subscription_id' => $payload['data']['object']['id'],
                'status' => $payload['data']['object']['status'],
                'cancel_at_period_end' => $payload['data']['object']['cancel_at_period_end'],
            ]);
        }

        return parent::handleCustomerSubscriptionUpdated($payload);
    }

    /**
     * Handle customer subscription deleted
     */
    public function handleCustomerSubscriptionDeleted(array $payload): Response
    {
        Log::info('Subscription deleted webhook received', $payload);
        
        if ($user = $this->getUserByStripeId($payload['data']['object']['customer'])) {
            // Track subscription cancellation event
            $user->recordUsage('subscription_cancelled', 1, 0, [
                'subscription_id' => $payload['data']['object']['id'],
                'ended_at' => $payload['data']['object']['ended_at'],
                'reason' => $payload['data']['object']['cancellation_details']['reason'] ?? 'unknown',
            ]);
        }

        return parent::handleCustomerSubscriptionDeleted($payload);
    }

    /**
     * Handle invoice payment succeeded
     */
    public function handleInvoicePaymentSucceeded(array $payload): Response
    {
        Log::info('Invoice payment succeeded webhook received', $payload);
        
        if ($user = $this->getUserByStripeId($payload['data']['object']['customer'])) {
            $invoice = $payload['data']['object'];
            
            // Track successful payment
            $user->recordUsage('payment_succeeded', 1, $invoice['amount_paid'] / 100, [
                'invoice_id' => $invoice['id'],
                'amount' => $invoice['amount_paid'],
                'currency' => $invoice['currency'],
                'subscription_id' => $invoice['subscription'],
            ]);

            // Reset overage flags for new billing period
            $billingUsage = $user->getCurrentUsage();
            if ($billingUsage->is_overage) {
                $billingUsage->update([
                    'is_overage' => false,
                    'overage_cost' => 0,
                ]);
            }
        }

        return parent::handleInvoicePaymentSucceeded($payload);
    }

    /**
     * Handle invoice payment failed
     */
    public function handleInvoicePaymentFailed(array $payload): Response
    {
        Log::warning('Invoice payment failed webhook received', $payload);
        
        if ($user = $this->getUserByStripeId($payload['data']['object']['customer'])) {
            $invoice = $payload['data']['object'];
            
            // Track failed payment
            $user->recordUsage('payment_failed', 1, 0, [
                'invoice_id' => $invoice['id'],
                'amount' => $invoice['amount_due'],
                'currency' => $invoice['currency'],
                'subscription_id' => $invoice['subscription'],
                'failure_reason' => $invoice['last_payment_error']['message'] ?? 'unknown',
            ]);

            // You might want to send a notification email or take other actions here
            // $this->notifyPaymentFailure($user, $invoice);
        }

        return parent::handleInvoicePaymentFailed($payload);
    }

    /**
     * Handle customer created
     */
    public function handleCustomerCreated(array $payload): Response
    {
        Log::info('Customer created webhook received', $payload);
        
        $customer = $payload['data']['object'];
        
        // Find user by email and update their Stripe customer ID if needed
        if ($customer['email'] && $user = User::where('email', $customer['email'])->first()) {
            if (!$user->stripe_id) {
                $user->update(['stripe_id' => $customer['id']]);
            }
        }

        return new Response('Webhook handled', 200);
    }

    /**
     * Handle customer updated
     */
    public function handleCustomerUpdated(array $payload): Response
    {
        Log::info('Customer updated webhook received', $payload);
        
        return new Response('Webhook handled', 200);
    }

    /**
     * Handle customer deleted
     */
    public function handleCustomerDeleted(array $payload): Response
    {
        Log::info('Customer deleted webhook received', $payload);
        
        if ($user = $this->getUserByStripeId($payload['data']['object']['id'])) {
            // Clear Stripe customer ID
            $user->update(['stripe_id' => null]);
            
            // Track customer deletion
            $user->recordUsage('customer_deleted', 1, 0, [
                'stripe_customer_id' => $payload['data']['object']['id'],
            ]);
        }

        return new Response('Webhook handled', 200);
    }

    /**
     * Handle usage record creation (for metered billing)
     */
    public function handleUsageRecordCreated(array $payload): Response
    {
        Log::info('Usage record created webhook received', $payload);
        
        $usageRecord = $payload['data']['object'];
        
        // You can implement metered billing logic here if needed
        // For example, tracking usage for specific subscription items
        
        return new Response('Webhook handled', 200);
    }

    /**
     * Get user by Stripe customer ID
     */
    protected function getUserByStripeId($stripeId)
    {
        return User::where('stripe_id', $stripeId)->first();
    }

    /**
     * Handle unhandled webhook events
     */
    protected function handleUnhandledWebhook(array $payload): Response
    {
        Log::info('Unhandled webhook event received', [
            'type' => $payload['type'] ?? 'unknown',
            'id' => $payload['id'] ?? 'unknown',
        ]);

        return new Response('Webhook received but not handled', 200);
    }

    /**
     * Verify webhook signature before processing
     */
    protected function verifyWebhookSignature(Request $request): bool
    {
        try {
            $signature = $request->header('Stripe-Signature');
            $payload = $request->getContent();
            $secret = config('cashier.webhook.secret');

            \Stripe\Webhook::constructEvent($payload, $signature, $secret);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Webhook signature verification failed', [
                'error' => $e->getMessage(),
                'signature' => $request->header('Stripe-Signature'),
            ]);
            
            return false;
        }
    }
}
