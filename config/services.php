<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'organization' => env('OPENAI_ORGANIZATION'),
    ],

    'google_language' => [
        'credentials' => env('GOOGLE_APPLICATION_CREDENTIALS'),
    ],

    'evm' => [
        'rpc_url' => env('EVM_RPC_URL'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'prices' => [
            'starter_monthly' => env('STRIPE_PRICE_STARTER_MONTHLY'),
            'starter_yearly' => env('STRIPE_PRICE_STARTER_YEARLY'),
            'professional_monthly' => env('STRIPE_PRICE_PROFESSIONAL_MONTHLY'),
            'professional_yearly' => env('STRIPE_PRICE_PROFESSIONAL_YEARLY'),
            'enterprise_monthly' => env('STRIPE_PRICE_ENTERPRISE_MONTHLY'),
            'enterprise_yearly' => env('STRIPE_PRICE_ENTERPRISE_YEARLY'),
        ],
        'features' => [
            'starter' => [
                'analysis_limit' => env('STARTER_ANALYSIS_LIMIT', 10),
                'ai_insights' => false,
                'priority_support' => false,
                'advanced_analytics' => false,
            ],
            'professional' => [
                'analysis_limit' => env('PROFESSIONAL_ANALYSIS_LIMIT', 100),
                'ai_insights' => true,
                'priority_support' => false,
                'advanced_analytics' => true,
            ],
            'enterprise' => [
                'analysis_limit' => env('ENTERPRISE_ANALYSIS_LIMIT', 1000),
                'ai_insights' => true,
                'priority_support' => true,
                'advanced_analytics' => true,
            ],
        ],
    ],

];
