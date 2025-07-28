<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;

class StripeWebhookController extends CashierWebhookController
{
    /**
     * Handle a successful subscription creation event.
     */
    public function handleCustomerSubscriptionCreated(array $payload): void
    {
        // Custom logic (e.g., send welcome email)
    }
} 