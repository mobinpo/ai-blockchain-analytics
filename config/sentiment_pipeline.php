<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Text Preprocessing Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for text cleaning and preprocessing before sentiment analysis
    |
    */
    'preprocessing' => [
        'remove_urls' => true,
        'remove_emails' => true,
        'clean_social_markers' => true, // Remove @mentions and #hashtags
        'normalize_whitespace' => true,
        'remove_special_chars' => true,
        'to_lowercase' => false, // Keep original casing for better sentiment analysis
        'remove_short_words' => true,
        'remove_stopwords' => false, // Keep for better context in sentiment analysis
        'min_text_length' => 10,
        'max_text_length' => 5000,
        'cache_cleanup_days' => 30,
        'log_steps' => false, // Enable for debugging
    ],

    /*
    |--------------------------------------------------------------------------
    | Text Aggregation Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for aggregating text data into batches for processing
    |
    */
    'aggregation' => [
        'min_text_length' => 20,
        'max_text_length' => 5000,
        'exclude_patterns' => [
            '/^RT\s/', // Retweets
            '/^@\w+\s*$/', // Only mentions
            '/^\s*$/', // Empty content
            '/^\.{3,}/', // Ellipsis only
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Batch Processing Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for processing sentiment batches
    |
    */
    'batch_processing' => [
        'chunk_size' => 50, // Documents per chunk for aggregation
        'processing_chunk_size' => 10, // Documents per processing chunk
        'max_retries' => 3,
        'retry_delay_seconds' => 2,
        'cleanup_after_days' => 7, // Clean up completed batches after this many days
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Cloud NLP Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for Google Cloud Natural Language API
    |
    */
    'google_nlp' => [
        // Authentication and project configuration
        'project_id' => env('GOOGLE_CLOUD_PROJECT_ID'),
        'credentials_path' => env('GOOGLE_CLOUD_CREDENTIALS_PATH'),
        'api_key' => env('GOOGLE_CLOUD_API_KEY'),
        'endpoint' => env('GOOGLE_CLOUD_NLP_ENDPOINT', 'https://language.googleapis.com/v1'),
        
        // Analysis features
        'enable_sentiment_analysis' => true,
        'enable_entity_analysis' => true,
        'enable_classification' => true,
        'enable_syntax_analysis' => env('GOOGLE_NLP_SYNTAX_ANALYSIS', false),
        'detect_language' => true,
        
        // Batch processing settings
        'batch_size' => env('GOOGLE_NLP_BATCH_SIZE', 25),
        'max_text_length' => 20000,
        'concurrent_requests' => env('GOOGLE_NLP_CONCURRENT', 10),
        
        // Rate limiting
        'rate_limit_delay_ms' => 100,
        'requests_per_minute' => 600,
        'requests_per_day' => 10000,
        
        // Retry configuration
        'max_retries' => 3,
        'retry_delay_ms' => 1000,
        'exponential_backoff' => true,
        'retry_on_rate_limit' => true,
        
        // Request timeout settings
        'timeout' => [
            'connect' => 30,
            'request' => 120,
            'read' => 60,
        ],
        
        // Cost management
        'sentiment_analysis_cost' => 0.001,
        'entity_analysis_cost' => 0.001,
        'classification_cost' => 0.002,
        'estimated_monthly_cost' => 100.0,
        'daily_budget_limit' => 50.0,
        
        // Entity extraction settings
        'entity_types' => [
            'PERSON', 'LOCATION', 'ORGANIZATION', 'EVENT', 
            'WORK_OF_ART', 'CONSUMER_GOOD', 'OTHER'
        ],
        'min_entity_salience' => 0.1,
        'max_entities_per_document' => 20,
        
        // Classification settings
        'classification_confidence_threshold' => 0.5,
        'max_categories_per_document' => 5,
        
        // Language support
        'supported_languages' => ['en', 'es', 'fr', 'de', 'it', 'pt', 'ja', 'ko', 'zh'],
        'default_language' => 'en',
    ],

    /*
    |--------------------------------------------------------------------------
    | Daily Aggregation Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for generating daily sentiment aggregates
    |
    */
    'aggregation' => [
        'generate_hourly' => false, // Generate hourly aggregates in addition to daily
        'platforms' => ['all', 'twitter', 'reddit', 'telegram'],
        'keyword_categories' => ['all', 'blockchain', 'security', 'contracts', 'defi'],
        'languages' => ['all', 'en', 'es', 'fr', 'de'],
        'min_posts_for_aggregate' => 5, // Minimum posts required to create aggregate
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Queue settings for sentiment pipeline jobs
    |
    */
    'queue' => [
        'default_queue' => 'sentiment-processing',
        'bulk_queue' => 'sentiment-bulk-processing',
        'timeout' => 3600, // 1 hour
        'retry_after' => 21600, // 6 hours
        'max_tries' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cost Management
    |--------------------------------------------------------------------------
    |
    | Settings for tracking and managing API costs
    |
    */
    'cost_management' => [
        'sentiment_analysis_cost' => 0.001, // Cost per document for sentiment analysis
        'entity_analysis_cost' => 0.001, // Additional cost for entity analysis
        'classification_cost' => 0.002, // Additional cost for classification
        'daily_budget_limit' => 50.0, // Stop processing if daily cost exceeds this
        'monthly_budget_limit' => 1000.0, // Alert if monthly cost exceeds this
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring and Alerting
    |--------------------------------------------------------------------------
    |
    | Settings for monitoring pipeline health and performance
    |
    */
    'monitoring' => [
        'alert_on_high_failure_rate' => true,
        'failure_rate_threshold' => 0.1, // Alert if failure rate exceeds 10%
        'alert_on_processing_delays' => true,
        'max_processing_delay_hours' => 24,
        'alert_on_budget_exceeded' => true,
        'log_detailed_stats' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Retention
    |--------------------------------------------------------------------------
    |
    | Settings for data retention and cleanup
    |
    */
    'retention' => [
        'keep_batches_days' => 30,
        'keep_aggregates_days' => 365,
        'keep_preprocessing_cache_days' => 30,
        'archive_old_data' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Optimization
    |--------------------------------------------------------------------------
    |
    | Settings for optimizing pipeline performance
    |
    */
    'performance' => [
        'use_database_chunking' => true,
        'chunk_size' => 1000,
        'use_caching' => true,
        'cache_ttl_hours' => 24,
        'parallel_processing' => false, // Enable when you have multiple workers
        'max_concurrent_jobs' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sentiment Analysis Thresholds
    |--------------------------------------------------------------------------
    |
    | Thresholds for categorizing sentiment scores
    |
    */
    'sentiment_thresholds' => [
        'very_positive' => 0.6,
        'positive' => 0.2,
        'neutral_upper' => 0.2,
        'neutral_lower' => -0.2,
        'negative' => -0.6,
        // very_negative is anything below negative threshold
    ],

    /*
    |--------------------------------------------------------------------------
    | Alert Conditions
    |--------------------------------------------------------------------------
    |
    | Conditions that trigger sentiment alerts
    |
    */
    'alert_conditions' => [
        'sentiment_spike_threshold' => 0.5, // Alert if sentiment changes by more than 0.5 in a day
        'volume_anomaly_multiplier' => 3.0, // Alert if volume is 3x normal
        'volatility_threshold' => 0.4, // Alert if sentiment volatility exceeds 0.4
        'engagement_spike_multiplier' => 5.0, // Alert if engagement spikes 5x
    ],

];