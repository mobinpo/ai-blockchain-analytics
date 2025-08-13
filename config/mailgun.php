<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Mailgun Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Mailgun email service integration
    |
    */

    'domain' => env('MAILGUN_DOMAIN'),
    'secret' => env('MAILGUN_SECRET'),
    'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    'scheme' => 'https',

    // Onboarding email flow settings
    'onboarding' => [
        'enabled' => env('MAILGUN_ONBOARDING_ENABLED', true),
        'from_email' => env('MAILGUN_FROM_EMAIL', 'welcome@blockchain-analytics.com'),
        'from_name' => env('MAILGUN_FROM_NAME', 'AI Blockchain Analytics Team'),
        'reply_to' => env('MAILGUN_REPLY_TO', 'support@blockchain-analytics.com'),
        
        // Email sequence timing (in minutes)
        'delays' => [
            'welcome' => 0,          // Immediate
            'getting_started' => 60, // 1 hour
            'first_analysis' => 1440,  // 24 hours
            'advanced_features' => 4320, // 3 days
            'feedback' => 10080,     // 7 days
        ],

        // Template settings
        'templates' => [
            'welcome' => 'onboarding.welcome',
            'getting_started' => 'onboarding.getting-started',
            'first_analysis' => 'onboarding.first-analysis',
            'advanced_features' => 'onboarding.advanced-features',
            'feedback' => 'onboarding.feedback',
        ],

        // Tracking and analytics
        'tracking' => [
            'opens' => true,
            'clicks' => true,
            'unsubscribes' => true,
        ],

        // Tags for segmentation
        'tags' => [
            'onboarding',
            'new-user',
            'email-sequence',
        ],
    ],

    // Email preferences
    'preferences' => [
        'marketing' => true,
        'product_updates' => true,
        'security_alerts' => true,
        'weekly_digest' => true,
    ],

    // Rate limiting
    'rate_limit' => [
        'per_hour' => 100,
        'per_day' => 1000,
    ],

    // Webhook configuration
    'webhooks' => [
        'signing_key' => env('MAILGUN_WEBHOOK_SIGNING_KEY'),
        'endpoints' => [
            'delivered' => '/webhooks/mailgun/delivered',
            'opened' => '/webhooks/mailgun/opened',
            'clicked' => '/webhooks/mailgun/clicked',
            'unsubscribed' => '/webhooks/mailgun/unsubscribed',
            'complained' => '/webhooks/mailgun/complained',
            'bounced' => '/webhooks/mailgun/bounced',
        ],
    ],
];