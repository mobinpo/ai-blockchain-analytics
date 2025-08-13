<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Free API Services Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for free API alternatives to replace paid services
    | All services listed here are FREE with reasonable rate limits
    |
    */

    'huggingface' => [
        'enabled' => env('HUGGINGFACE_ENABLED', true),
        'api_key' => env('HUGGINGFACE_API_KEY', ''),
        'free_limit' => '30,000 characters/month',
        'timeout' => env('HUGGINGFACE_TIMEOUT', 60),
        'models' => [
            'code_analysis' => 'microsoft/CodeBERT-base',
            'text_generation' => 'microsoft/DialoGPT-large',
            'vulnerability_detection' => 'microsoft/codebert-base-mlm',
        ],
    ],

    'multi_ai' => [
        'enabled' => env('MULTI_AI_ENABLED', true),
        'services' => [
            'huggingface' => [
                'enabled' => true,
                'free_limit' => '30,000 chars/month',
                'priority' => 1
            ],
            'claude' => [
                'enabled' => env('CLAUDE_ENABLED', false),
                'api_key' => env('ANTHROPIC_API_KEY', ''),
                'free_limit' => '$5 free credit',
                'priority' => 2
            ],
            'cohere' => [
                'enabled' => env('COHERE_ENABLED', false),
                'api_key' => env('COHERE_API_KEY', ''),
                'free_limit' => '100 requests/minute',
                'priority' => 3
            ],
            'ai21' => [
                'enabled' => env('AI21_ENABLED', false),
                'api_key' => env('AI21_API_KEY', ''),
                'free_limit' => '$10 free credit',
                'priority' => 4
            ],
        ],
    ],

    'enhanced_sentiment' => [
        'enabled' => env('ENHANCED_SENTIMENT_ENABLED', true),
        'local_analyzer' => [
            'method' => env('FREE_SENTIMENT_METHOD', 'vader'),
            'cache_ttl' => env('FREE_SENTIMENT_CACHE_TTL', 1800),
            'batch_size' => env('FREE_SENTIMENT_BATCH_SIZE', 100),
        ],
        'online_services' => [
            'meaningcloud' => [
                'enabled' => env('MEANINGCLOUD_ENABLED', false),
                'api_key' => env('MEANINGCLOUD_API_KEY', ''),
                'free_limit' => '20,000 requests/month',
            ],
            'text_processing' => [
                'enabled' => env('TEXT_PROCESSING_ENABLED', true),
                'api_key' => null, // No API key required
                'free_limit' => '1,000 requests/day',
            ],
            'sentiment140' => [
                'enabled' => env('SENTIMENT140_ENABLED', true),
                'api_key' => null, // No API key required
                'free_limit' => 'unlimited',
            ],
            'paralleldots' => [
                'enabled' => env('PARALLELDOTS_ENABLED', false),
                'api_key' => env('PARALLELDOTS_API_KEY', ''),
                'free_limit' => '1,000 requests/month',
            ],
        ],
    ],

    'enhanced_crypto_data' => [
        'enabled' => env('ENHANCED_CRYPTO_DATA_ENABLED', true),
        'primary_source' => env('ENHANCED_CRYPTO_PRIMARY_SOURCE', 'binance'),
        'cache_ttl' => env('ENHANCED_CRYPTO_CACHE_TTL', 300),
        'timeout' => env('ENHANCED_CRYPTO_TIMEOUT', 30),
        'free_sources' => [
            'coincap' => [
                'url' => 'https://api.coincap.io/v2',
                'rate_limit' => '200/minute',
                'enabled' => true,
                'features' => ['price', 'historical', 'markets']
            ],
            'coingecko_free' => [
                'url' => 'https://api.coingecko.com/api/v3',
                'rate_limit' => '10-50/minute',
                'enabled' => true,
                'features' => ['price', 'historical', 'trending']
            ],
            'binance_public' => [
                'url' => 'https://api.binance.com/api/v3',
                'rate_limit' => '1200/minute',
                'enabled' => true,
                'features' => ['price', 'ticker', 'markets']
            ],
            'coinlore' => [
                'url' => 'https://api.coinlore.net/api',
                'rate_limit' => 'unlimited',
                'enabled' => true,
                'features' => ['price', 'markets']
            ],
        ],
        'trial_sources' => [
            'cryptocompare' => [
                'url' => 'https://min-api.cryptocompare.com/data',
                'api_key' => env('CRYPTOCOMPARE_API_KEY', ''),
                'free_limit' => '100,000/month',
                'enabled' => env('CRYPTOCOMPARE_ENABLED', false),
            ],
            'messari' => [
                'url' => 'https://data.messari.io/api',
                'api_key' => env('MESSARI_API_KEY', ''),
                'free_limit' => '1,000/month',
                'enabled' => env('MESSARI_ENABLED', false),
            ],
            'nomics' => [
                'url' => 'https://api.nomics.com/v1',
                'api_key' => env('NOMICS_API_KEY', ''),
                'free_limit' => '100k/month',
                'enabled' => env('NOMICS_ENABLED', false),
            ],
        ],
    ],

    'free_email' => [
        'enabled' => env('FREE_EMAIL_ENABLED', true),
        'service' => env('FREE_EMAIL_SERVICE', 'emailjs'), // emailjs, formspree, smtp
        'emailjs' => [
            'service_id' => env('EMAILJS_SERVICE_ID', ''),
            'template_id' => env('EMAILJS_TEMPLATE_ID', ''),
            'public_key' => env('EMAILJS_PUBLIC_KEY', ''),
            'rate_limit' => '200/month',
        ],
        'formspree' => [
            'endpoint' => env('FORMSPREE_ENDPOINT', ''),
            'rate_limit' => '50/month',
        ],
    ],

    'free_social_media' => [
        'enabled' => env('FREE_SOCIAL_MEDIA_ENABLED', true),
        'twitter_alternative' => env('FREE_TWITTER_SERVICE', 'nitter'), // nitter, rss
        'nitter_instances' => [
            'https://nitter.net',
            'https://nitter.it',
            'https://nitter.fdn.fr',
            'https://nitter.kavin.rocks',
        ],
        'reddit_free' => [
            'enabled' => true,
            'method' => 'rss', // rss, json
            'rate_limit' => 'unlimited',
        ],
        'telegram_free' => [
            'enabled' => true,
            'method' => 'rss', // rss, api
            'rate_limit' => 'unlimited',
        ],
    ],

    'crypto_payments' => [
        'enabled' => env('CRYPTO_PAYMENTS_ENABLED', true),
        'addresses' => [
            'ethereum' => env('ETH_PAYMENT_ADDRESS', ''),
            'bitcoin' => env('BTC_PAYMENT_ADDRESS', ''),
            'binance_smart_chain' => env('BSC_PAYMENT_ADDRESS', ''),
            'polygon' => env('POLYGON_PAYMENT_ADDRESS', ''),
        ],
        'pricing' => [
            'starter_monthly_usd' => 49,
            'professional_monthly_usd' => 149,
            'enterprise_monthly_usd' => 499,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Rate limits for free APIs to prevent hitting limits
    |
    */

    'rate_limits' => [
        'coincap' => [
            'requests_per_minute' => 200,
            'requests_per_hour' => 12000,
        ],
        'coingecko_free' => [
            'requests_per_minute' => 10,
            'requests_per_hour' => 600,
        ],
        'cryptocompare_free' => [
            'requests_per_minute' => 20,
            'requests_per_month' => 100000,
        ],
        'ollama' => [
            'requests_per_minute' => 60,
            'concurrent_requests' => 5,
        ],
        'free_sentiment' => [
            'requests_per_minute' => 1000,
            'batch_size' => 100,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback Configuration
    |--------------------------------------------------------------------------
    |
    | Fallback options when primary free services are unavailable
    |
    */

    'fallbacks' => [
        'smart_contract_analysis' => [
            'primary' => 'ollama',
            'secondary' => 'pattern_matching',
            'tertiary' => 'basic_checks',
        ],
        'sentiment_analysis' => [
            'primary' => 'free_sentiment',
            'secondary' => 'keyword_matching',
            'tertiary' => 'rule_based',
        ],
        'price_data' => [
            'primary' => 'coincap',
            'secondary' => 'coingecko_free',
            'tertiary' => 'cryptocompare_free',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Enable/disable free service features
    |
    */

    'features' => [
        'replace_openai_with_ollama' => env('FEATURE_OLLAMA_REPLACE_OPENAI', true),
        'replace_google_nlp_with_free' => env('FEATURE_FREE_SENTIMENT_REPLACE_GOOGLE', true),
        'replace_stripe_with_crypto' => env('FEATURE_CRYPTO_PAYMENTS_REPLACE_STRIPE', false), // Keep false until ready
        'replace_mailgun_with_free' => env('FEATURE_FREE_EMAIL_REPLACE_MAILGUN', false), // Keep false until ready
        'replace_twitter_api_with_nitter' => env('FEATURE_NITTER_REPLACE_TWITTER', true),
        'use_multiple_free_price_sources' => env('FEATURE_MULTIPLE_PRICE_SOURCES', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cost Tracking
    |--------------------------------------------------------------------------
    |
    | Track cost savings from using free services
    |
    */

    'cost_savings' => [
        'track_savings' => env('TRACK_COST_SAVINGS', true),
        'estimated_monthly_savings' => [
            'openai_replacement' => 200.00,
            'google_nlp_replacement' => 50.00,
            'coingecko_pro_replacement' => 129.00,
            'twitter_api_replacement' => 100.00,
            'total_monthly_savings' => 479.00,
        ],
    ],

];
