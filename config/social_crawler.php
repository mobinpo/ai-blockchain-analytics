<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Social Media Crawler Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Twitter/X, Reddit, and Telegram crawlers
    |
    */

    'enabled' => env('SOCIAL_CRAWLER_ENABLED', true),
    'default_max_results' => env('SOCIAL_CRAWLER_MAX_RESULTS', 100),
    'default_schedule' => env('SOCIAL_CRAWLER_SCHEDULE', 'hourly'),

    /*
    |--------------------------------------------------------------------------
    | Twitter/X Configuration
    |--------------------------------------------------------------------------
    */
    'twitter' => [
        'bearer_token' => env('TWITTER_BEARER_TOKEN'),
        'api_version' => '2',
        'default_max_results' => 100,
        'rate_limit' => 300, // Per 15 minutes
        'timeout' => 30,
        'retry_attempts' => 3,
        'retry_delay' => 1000, // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Reddit Configuration
    |--------------------------------------------------------------------------
    */
    'reddit' => [
        'client_id' => env('REDDIT_CLIENT_ID'),
        'client_secret' => env('REDDIT_CLIENT_SECRET'),
        'user_agent' => env('REDDIT_USER_AGENT', 'BlockchainAnalytics/1.0'),
        'default_max_results' => 100,
        'rate_limit' => 100, // Per 10 minutes
        'timeout' => 30,
        'retry_attempts' => 3,
        'default_subreddits' => [
            'cryptocurrency',
            'CryptoCurrency',
            'bitcoin',
            'ethereum',
            'defi',
            'NFT',
            'CryptoMarkets',
            'BlockChain',
            'CryptoCurrencyTrading',
            'altcoin',
            'CryptoTechnology',
            'ethtrader',
            'bitcoinmarkets',
            'cryptomoonshots',
            'web3',
            'solana',
            'cardano',
            'polygon',
            'avalanche',
            'fantom'
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Telegram Configuration
    |--------------------------------------------------------------------------
    */
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'webhook_url' => env('TELEGRAM_WEBHOOK_URL'),
        'default_max_results' => 50,
        'rate_limit' => 30, // Per minute
        'timeout' => 30,
        'monitored_channels' => [
            '@cryptonews',
            '@blockchain',
            '@defi_news',
            '@nft_news',
            // Add your monitored channels here
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        'twitter' => [
            'default' => ['requests' => 300, 'window_minutes' => 15],
            'endpoints' => [
                'tweets/search/recent' => ['requests' => 300, 'window_minutes' => 15],
                'users/{id}/tweets' => ['requests' => 300, 'window_minutes' => 15],
                'tweets/{id}' => ['requests' => 300, 'window_minutes' => 15],
            ]
        ],
        'reddit' => [
            'default' => ['requests' => 100, 'window_minutes' => 10],
            'endpoints' => [
                'search' => ['requests' => 100, 'window_minutes' => 10],
                'user/*/submitted' => ['requests' => 100, 'window_minutes' => 10],
                'r/*/new' => ['requests' => 100, 'window_minutes' => 10],
            ]
        ],
        'telegram' => [
            'default' => ['requests' => 30, 'window_minutes' => 1],
            'endpoints' => [
                'getUpdates' => ['requests' => 30, 'window_minutes' => 1],
                'getChat' => ['requests' => 30, 'window_minutes' => 1],
                'sendMessage' => ['requests' => 30, 'window_minutes' => 1],
            ]
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Keyword Rule Defaults
    |--------------------------------------------------------------------------
    */
    'default_keywords' => [
        'blockchain',
        'cryptocurrency',
        'bitcoin',
        'ethereum',
        'defi',
        'smart contract',
        'nft',
        'web3',
        'crypto',
        'vulnerability',
        'exploit',
        'hack',
        'security',
        'audit',
        'rug pull',
        'flash loan',
        'governance attack',
        'oracle manipulation',
        'front running',
        'mev',
        'sandwich attack'
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Processing
    |--------------------------------------------------------------------------
    */
    'processing' => [
        'enable_sentiment_analysis' => env('ENABLE_SENTIMENT_ANALYSIS', true),
        'enable_spam_filtering' => env('ENABLE_SPAM_FILTERING', true),
        'min_content_length' => 10,
        'max_content_length' => 10000,
        'auto_translate' => false,
        'supported_languages' => ['en', 'es', 'fr', 'de', 'pt', 'it'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    */
    'storage' => [
        'save_raw_data' => true,
        'save_media_files' => false,
        'media_storage_disk' => 's3',
        'media_max_size' => 10485760, // 10MB
        'cleanup_old_posts_days' => 90,
        'archive_old_posts_days' => 365,
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'connection' => env('SOCIAL_CRAWLER_QUEUE_CONNECTION', 'redis'),
        'queue_name' => env('SOCIAL_CRAWLER_QUEUE_NAME', 'social-crawler'),
        'max_tries' => 3,
        'retry_delay' => 60, // seconds
        'batch_size' => 50,
        'timeout' => 300, // 5 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Lambda Configuration (for Python Lambda deployment)
    |--------------------------------------------------------------------------
    */
    'lambda' => [
        'function_name' => env('LAMBDA_FUNCTION_NAME', 'social-media-crawler'),
        'runtime' => 'python3.11',
        'timeout' => 300,
        'memory_size' => 512,
        'environment_variables' => [
            'TWITTER_BEARER_TOKEN' => env('TWITTER_BEARER_TOKEN'),
            'REDDIT_CLIENT_ID' => env('REDDIT_CLIENT_ID'),
            'REDDIT_CLIENT_SECRET' => env('REDDIT_CLIENT_SECRET'),
            'TELEGRAM_BOT_TOKEN' => env('TELEGRAM_BOT_TOKEN'),
            'S3_RESULTS_BUCKET' => env('S3_RESULTS_BUCKET'),
            'SQS_QUEUE_URL' => env('SQS_QUEUE_URL'),
            'REDIS_URL' => env('REDIS_URL'),
        ],
        'schedule_expression' => 'rate(1 hour)', // Run every hour
        's3_bucket' => env('S3_RESULTS_BUCKET'),
        'sqs_queue_url' => env('SQS_QUEUE_URL'),
    ],
];