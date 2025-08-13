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

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],


    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Browserless Service Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Browserless service which provides high-quality PDF
    | generation using headless Chrome browser instances.
    |
    */
    
    'browserless' => [
        'enabled' => env('BROWSERLESS_ENABLED', false),
        'url' => env('BROWSERLESS_URL', 'https://chrome.browserless.io'),
        'token' => env('BROWSERLESS_TOKEN'),
        'timeout' => env('BROWSERLESS_TIMEOUT', 30),
        'health_check_interval' => env('BROWSERLESS_HEALTH_CHECK_INTERVAL', 300),
        'max_retries' => env('BROWSERLESS_MAX_RETRIES', 2),
        'api_key' => env('BROWSERLESS_API_KEY', null), // For hosted Browserless
        'concurrency' => env('BROWSERLESS_CONCURRENCY', 3),
        'concurrent_limit' => env('BROWSERLESS_CONCURRENT_LIMIT', 10),
        'quality' => env('BROWSERLESS_QUALITY', 90),
        'viewport' => [
            'width' => env('BROWSERLESS_VIEWPORT_WIDTH', 1920),
            'height' => env('BROWSERLESS_VIEWPORT_HEIGHT', 1080),
        ],
        'options' => [
            'headless' => true,
            'args' => [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-gpu',
                '--disable-web-security',
                '--disable-features=VizDisplayCompositor',
                '--run-all-compositor-stages-before-draw',
                '--memory-pressure-off'
            ]
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Blockchain Explorer APIs
    |--------------------------------------------------------------------------
    */
    
    'blockchain_explorers' => [
        'etherscan' => [
            'url' => 'https://api.etherscan.io/api',
            'api_key' => env('ETHERSCAN_API_KEY'),
        ],
        'bscscan' => [
            'url' => 'https://api.bscscan.com/api',
            'api_key' => env('BSCSCAN_API_KEY'),
        ],
        'polygonscan' => [
            'url' => 'https://api.polygonscan.com/api',
            'api_key' => env('POLYGONSCAN_API_KEY'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Services
    |--------------------------------------------------------------------------
    */
    
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'organization' => env('OPENAI_ORGANIZATION'),
        'request_timeout' => env('OPENAI_REQUEST_TIMEOUT', 30),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'models' => [
            'gpt-4' => 'gpt-4',
            'gpt-3.5-turbo' => 'gpt-3.5-turbo',
            'text-embedding-ada-002' => 'text-embedding-ada-002',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Social Media APIs
    |--------------------------------------------------------------------------
    */
    
    'twitter' => [
        'bearer_token' => env('TWITTER_BEARER_TOKEN'),
        'api_key' => env('TWITTER_API_KEY'),
        'api_secret' => env('TWITTER_API_SECRET'),
        'access_token' => env('TWITTER_ACCESS_TOKEN'),
        'access_token_secret' => env('TWITTER_ACCESS_TOKEN_SECRET'),
    ],

    'reddit' => [
        'client_id' => env('REDDIT_CLIENT_ID'),
        'client_secret' => env('REDDIT_CLIENT_SECRET'),
        'username' => env('REDDIT_USERNAME'),
        'password' => env('REDDIT_PASSWORD'),
        'user_agent' => env('REDDIT_USER_AGENT', 'AIBlockchainAnalytics/1.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Services
    |--------------------------------------------------------------------------
    */
    
    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    /*
    |--------------------------------------------------------------------------
    | File Storage Services
    |--------------------------------------------------------------------------
    */
    
    'aws' => [
        'credentials' => [
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'token' => env('AWS_SESSION_TOKEN'),
        ],
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'version' => 'latest',
        'url' => env('AWS_URL'),
        'endpoint' => env('AWS_ENDPOINT'),
        'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        'throw' => false,
        
        's3' => [
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring & Analytics
    |--------------------------------------------------------------------------
    */
    
    'sentry' => [
        'dsn' => env('SENTRY_LARAVEL_DSN'),
        'release' => env('SENTRY_RELEASE'),
        'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV')),
        'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Services
    |--------------------------------------------------------------------------
    */
    
    'stripe' => [
        'model' => env('STRIPE_MODEL', App\Models\User::class),
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook' => [
            'secret' => env('STRIPE_WEBHOOK_SECRET'),
            'tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
        ],
        'currency' => env('CASHIER_CURRENCY', 'usd'),
        'currency_locale' => env('CASHIER_CURRENCY_LOCALE', 'en'),
    ],

];