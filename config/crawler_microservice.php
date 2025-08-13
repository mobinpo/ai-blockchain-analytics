<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Crawler Microservice Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the social media crawler microservice with support
    | for both Laravel Octane and Python Lambda implementations
    |
    */

    'default_service' => env('CRAWLER_DEFAULT_SERVICE', 'octane'), // octane, lambda

    /*
    |--------------------------------------------------------------------------
    | Platform Configurations
    |--------------------------------------------------------------------------
    */

    'platforms' => [
        'twitter' => [
            'enabled' => env('TWITTER_CRAWLER_ENABLED', true),
            'bearer_token' => env('TWITTER_BEARER_TOKEN'),
            'rate_limit_per_15min' => 300,
            'max_results_per_request' => 100,
            'default_hashtags' => ['blockchain', 'cryptocurrency', 'defi', 'nft', 'ethereum', 'bitcoin', 'smartcontracts', 'web3'],
            'default_users' => ['ethereum', 'VitalikButerin', 'uniswap', 'aave', 'compoundfinance', 'MakerDAO'],
            'exclude_retweets' => true,
            'exclude_replies' => false,
            'languages' => ['en'],
        ],

        'reddit' => [
            'enabled' => env('REDDIT_CRAWLER_ENABLED', true),
            'client_id' => env('REDDIT_CLIENT_ID'),
            'client_secret' => env('REDDIT_CLIENT_SECRET'),
            'username' => env('REDDIT_USERNAME'),
            'password' => env('REDDIT_PASSWORD'),
            'user_agent' => env('REDDIT_USER_AGENT', 'AIBlockchainAnalytics/1.0'),
            'rate_limit_per_minute' => 60,
            'max_posts_per_subreddit' => 100,
            'default_subreddits' => ['cryptocurrency', 'ethereum', 'bitcoin', 'defi', 'ethfinance', 'ethtrader', 'smartcontracts', 'web3', 'nft', 'solidity'],
            'sort_methods' => ['hot', 'new', 'top'],
            'time_filters' => ['day', 'week', 'month'],
            'exclude_nsfw' => true,
            'min_score' => 0,
        ],

        'telegram' => [
            'enabled' => env('TELEGRAM_CRAWLER_ENABLED', true),
            'bot_token' => env('TELEGRAM_BOT_TOKEN'),
            'rate_limit_per_minute' => 20,
            'rate_limit_per_second' => 1,
            'max_message_age_hours' => 24,
            'default_channels' => ['blockchain', 'cryptocurrency', 'defi', 'ethereum', 'bitcoin'],
            'include_media' => true,
            'include_forwards' => false,
            'include_replies' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Keyword Engine Configuration
    |--------------------------------------------------------------------------
    */

    'keyword_engine' => [
        'cache_duration' => 3600, // 1 hour
        'case_sensitive' => false,
        'use_regex' => true,
        'sentiment_weight' => 0.3,
        'engagement_weight' => 0.4,
        'keyword_density_weight' => 0.3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Octane Service Configuration
    |--------------------------------------------------------------------------
    */

    'octane' => [
        'enabled' => env('OCTANE_CRAWLER_ENABLED', true),
        'concurrent_platforms' => true,
        'max_execution_time' => 300, // 5 minutes
        'memory_limit' => '1024M',
        'job_timeout' => 600, // 10 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Lambda Service Configuration
    |--------------------------------------------------------------------------
    */

    'lambda' => [
        'enabled' => env('LAMBDA_CRAWLER_ENABLED', false),
        'function_name' => env('LAMBDA_CRAWLER_FUNCTION', 'social-media-crawler-dev'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'timeout' => 900, // 15 minutes (max for Lambda)
        'memory' => 1024,
        'invoke_async' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Processing Pipeline
    |--------------------------------------------------------------------------
    */

    'data_pipeline' => [
        'enabled' => true,
        'batch_size' => 100,
        'processing_delay' => 30, // seconds
        'storage_method' => 'database', // database, s3, both
        'sentiment_analysis' => [
            'enabled' => true,
            'provider' => 'openai', // openai, aws_comprehend, textblob
            'batch_processing' => true,
        ],
        'duplicate_detection' => [
            'enabled' => true,
            'similarity_threshold' => 0.8,
            'check_window_hours' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    */

    'storage' => [
        'database' => [
            'enabled' => true,
            'batch_insert' => true,
            'chunk_size' => 50,
        ],
        's3' => [
            'enabled' => env('CRAWLER_S3_ENABLED', false),
            'bucket' => env('CRAWLER_S3_BUCKET', 'social-crawler-data'),
            'prefix' => 'crawl-results',
            'retention_days' => 30,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring and Alerting
    |--------------------------------------------------------------------------
    */

    'monitoring' => [
        'enabled' => true,
        'metrics' => [
            'posts_per_platform',
            'keyword_matches',
            'execution_time',
            'error_rate',
            'api_rate_limits',
        ],
        'alerts' => [
            'high_error_rate' => [
                'threshold' => 10, // percentage
                'window_minutes' => 15,
            ],
            'low_collection_rate' => [
                'threshold' => 5, // posts per hour
                'window_minutes' => 60,
            ],
            'api_rate_limit' => [
                'threshold' => 90, // percentage of limit
            ],
        ],
        'notifications' => [
            'email' => env('CRAWLER_ALERT_EMAIL'),
            'slack_webhook' => env('CRAWLER_SLACK_WEBHOOK'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduling Configuration
    |--------------------------------------------------------------------------
    */

    'scheduling' => [
        'enabled' => true,
        'default_interval' => 30, // minutes
        'platform_intervals' => [
            'twitter' => 15, // minutes
            'reddit' => 30,
            'telegram' => 20,
        ],
        'peak_hours' => [
            'start' => '08:00',
            'end' => '20:00',
            'interval_multiplier' => 0.5, // more frequent during peak
        ],
        'maintenance_window' => [
            'start' => '02:00',
            'end' => '04:00',
            'timezone' => 'UTC',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting and Throttling
    |--------------------------------------------------------------------------
    */

    'rate_limiting' => [
        'enabled' => true,
        'global_limits' => [
            'requests_per_minute' => 180,
            'requests_per_hour' => 5000,
        ],
        'platform_limits' => [
            'twitter' => [
                'requests_per_15min' => 300,
                'backoff_strategy' => 'exponential',
            ],
            'reddit' => [
                'requests_per_minute' => 60,
                'backoff_strategy' => 'linear',
            ],
            'telegram' => [
                'requests_per_minute' => 20,
                'backoff_strategy' => 'fixed',
            ],
        ],
        'circuit_breaker' => [
            'failure_threshold' => 5,
            'recovery_timeout' => 300, // seconds
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching Configuration
    |--------------------------------------------------------------------------
    */

    'caching' => [
        'enabled' => true,
        'default_ttl' => 1800, // 30 minutes
        'cache_types' => [
            'api_responses' => [
                'ttl' => 300, // 5 minutes
                'tags' => ['api', 'responses'],
            ],
            'processed_posts' => [
                'ttl' => 3600, // 1 hour
                'tags' => ['posts', 'processed'],
            ],
            'keyword_matches' => [
                'ttl' => 1800, // 30 minutes
                'tags' => ['keywords', 'matches'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    */

    'queue' => [
        'enabled' => true,
        'default_queue' => 'crawler',
        'queues' => [
            'high_priority' => 'crawler-high',
            'normal_priority' => 'crawler',
            'low_priority' => 'crawler-low',
            'processing' => 'crawler-processing',
        ],
        'workers' => [
            'max_concurrent' => 5,
            'timeout' => 300,
            'memory_limit' => '512M',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Check Configuration
    |--------------------------------------------------------------------------
    */

    'health_check' => [
        'enabled' => true,
        'interval' => 300, // 5 minutes
        'checks' => [
            'database_connectivity',
            'api_connectivity',
            'queue_health',
            'memory_usage',
            'disk_space',
        ],
        'thresholds' => [
            'memory_usage' => 80, // percentage
            'disk_space' => 90, // percentage
            'queue_size' => 1000, // jobs
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Development and Testing
    |--------------------------------------------------------------------------
    */

    'development' => [
        'mock_apis' => env('CRAWLER_MOCK_APIS', false),
        'reduced_limits' => env('CRAWLER_REDUCED_LIMITS', false),
        'debug_mode' => env('CRAWLER_DEBUG', false),
        'test_data' => [
            'enabled' => env('CRAWLER_TEST_DATA', false),
            'sample_size' => 10,
        ],
    ],
];