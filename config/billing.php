<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Subscription Plans Configuration
    |--------------------------------------------------------------------------
    |
    | Define the available subscription plans with their limits and pricing.
    | These correspond to the Stripe products and prices.
    |
    */

    'plans' => [
        'starter' => [
            'name' => 'Starter',
            'description' => 'Perfect for individual developers and small projects',
            'monthly_price' => 29,
            'yearly_price' => 290, // 2 months free
            'stripe_monthly_price_id' => env('STRIPE_PRICE_STARTER_MONTHLY'),
            'stripe_yearly_price_id' => env('STRIPE_PRICE_STARTER_YEARLY'),
            'features' => [
                'analysis_limit' => 10,
                'api_calls_limit' => 1000,
                'tokens_limit' => 50000,
                'projects_limit' => 5,
                'support' => 'email',
                'vulnerability_scanning' => true,
                'code_analysis' => true,
                'basic_reporting' => true,
            ],
            'overage_rates' => [
                'analysis' => 3.00, // $3 per extra analysis
                'api_call' => 0.01, // $0.01 per extra API call
                'token' => 0.0001, // $0.0001 per extra token
            ],
        ],

        'professional' => [
            'name' => 'Professional',
            'description' => 'Ideal for growing teams and medium-sized projects',
            'monthly_price' => 99,
            'yearly_price' => 990, // 2 months free
            'stripe_monthly_price_id' => env('STRIPE_PRICE_PROFESSIONAL_MONTHLY'),
            'stripe_yearly_price_id' => env('STRIPE_PRICE_PROFESSIONAL_YEARLY'),
            'features' => [
                'analysis_limit' => 100,
                'api_calls_limit' => 10000,
                'tokens_limit' => 500000,
                'projects_limit' => 25,
                'support' => 'priority',
                'vulnerability_scanning' => true,
                'code_analysis' => true,
                'advanced_reporting' => true,
                'team_collaboration' => true,
                'custom_integrations' => true,
                'webhook_support' => true,
            ],
            'overage_rates' => [
                'analysis' => 2.50, // $2.50 per extra analysis
                'api_call' => 0.008, // $0.008 per extra API call
                'token' => 0.00008, // $0.00008 per extra token
            ],
        ],

        'enterprise' => [
            'name' => 'Enterprise',
            'description' => 'For large organizations with high-volume needs',
            'monthly_price' => 299,
            'yearly_price' => 2990, // 2 months free
            'stripe_monthly_price_id' => env('STRIPE_PRICE_ENTERPRISE_MONTHLY'),
            'stripe_yearly_price_id' => env('STRIPE_PRICE_ENTERPRISE_YEARLY'),
            'features' => [
                'analysis_limit' => 1000,
                'api_calls_limit' => 100000,
                'tokens_limit' => 5000000,
                'projects_limit' => -1, // unlimited
                'support' => 'dedicated',
                'vulnerability_scanning' => true,
                'code_analysis' => true,
                'enterprise_reporting' => true,
                'team_collaboration' => true,
                'custom_integrations' => true,
                'webhook_support' => true,
                'sso_integration' => true,
                'compliance_reporting' => true,
                'priority_processing' => true,
            ],
            'overage_rates' => [
                'analysis' => 2.00, // $2 per extra analysis
                'api_call' => 0.005, // $0.005 per extra API call
                'token' => 0.00005, // $0.00005 per extra token
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Free Tier Limits
    |--------------------------------------------------------------------------
    |
    | Limits for users without an active subscription
    |
    */

    'free_tier' => [
        'analysis_limit' => 3,
        'api_calls_limit' => 100,
        'tokens_limit' => 10000,
        'projects_limit' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Billing Configuration
    |--------------------------------------------------------------------------
    |
    | General billing settings
    |
    */

    'currency' => env('CASHIER_CURRENCY', 'usd'),
    'tax_rates' => [
        // Stripe tax rate IDs for different regions
        'default' => env('STRIPE_TAX_RATE_DEFAULT'),
        'eu' => env('STRIPE_TAX_RATE_EU'),
        'us' => env('STRIPE_TAX_RATE_US'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Usage Tracking
    |--------------------------------------------------------------------------
    |
    | Configuration for usage tracking and billing
    |
    */

    'usage_tracking' => [
        'enabled' => env('BILLING_USAGE_TRACKING', true),
        'batch_size' => 100, // Batch size for processing usage records
        'retention_days' => 730, // Keep usage records for 2 years
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Stripe webhook settings
    |
    */

    'webhooks' => [
        'tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
        'events' => [
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted',
            'invoice.payment_succeeded',
            'invoice.payment_failed',
            'customer.created',
            'customer.updated',
            'customer.deleted',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Trial Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for trial periods
    |
    */

    'trial' => [
        'days' => 14,
        'requires_payment_method' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Plan Limits Access
    |--------------------------------------------------------------------------
    |
    | Helper configuration for accessing plan limits
    |
    */

    'starter' => [
        'analysis_limit' => env('STARTER_ANALYSIS_LIMIT', 10),
        'api_calls_limit' => 1000,
        'tokens_limit' => 50000,
    ],

    'professional' => [
        'analysis_limit' => env('PROFESSIONAL_ANALYSIS_LIMIT', 100),
        'api_calls_limit' => 10000,
        'tokens_limit' => 500000,
    ],

    'enterprise' => [
        'analysis_limit' => env('ENTERPRISE_ANALYSIS_LIMIT', 1000),
        'api_calls_limit' => 100000,
        'tokens_limit' => 5000000,
    ],
];