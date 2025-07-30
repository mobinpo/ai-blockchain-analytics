<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends CashierWebhookController
{
    /**
     * Handle a successful subscription creation event.
     */
    public function handleCustomerSubscriptionCreated(array $payload): void
    {
        Log::info('Subscription created', ['payload' => $payload]);
    }

    /**
     * Handle subscription update events.
     */
    public function handleCustomerSubscriptionUpdated(array $payload): void
    {
        Log::info('Subscription updated', ['payload' => $payload]);
    }

    /**
     * Handle subscription cancellation events.
     */
    public function handleCustomerSubscriptionDeleted(array $payload): void
    {
        Log::info('Subscription cancelled', ['payload' => $payload]);
    }

    /**
     * Handle successful payment events.
     */
    public function handleInvoicePaymentSucceeded(array $payload): void
    {
        Log::info('Payment succeeded', ['payload' => $payload]);
    }

    /**
     * Handle failed payment events.
     */
    public function handleInvoicePaymentFailed(array $payload): void
    {
        Log::info('Payment failed', ['payload' => $payload]);
    }
} 