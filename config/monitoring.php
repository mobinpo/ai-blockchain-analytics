<?php

declare(strict_types=1);

/**
 * AI Blockchain Analytics - Monitoring Configuration
 * 
 * Comprehensive monitoring configuration for Sentry + Telescope
 * with production-ready settings and blockchain-specific monitoring
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Monitoring Master Switch
    |--------------------------------------------------------------------------
    |
    | Enable or disable all monitoring features at once. When disabled,
    | all monitoring services will be bypassed for performance.
    |
    */

    'enabled' => env('MONITORING_ENABLED', env('APP_ENV') !== 'local'),

    /*
    |--------------------------------------------------------------------------
    | Sentry Configuration
    |--------------------------------------------------------------------------
    |
    | Enhanced Sentry configuration for error tracking and performance
    | monitoring with AI Blockchain Analytics specific settings.
    |
    */

    'sentry' => [
        'enabled' => env('SENTRY_ENABLED', env('APP_ENV') === 'production'),
        
        // Error tracking configuration
        'error_tracking' => [
            'capture_unhandled_exceptions' => env('SENTRY_CAPTURE_UNHANDLED', true),
            'capture_failed_jobs' => env('SENTRY_CAPTURE_FAILED_JOBS', true),
            'capture_slow_queries' => env('SENTRY_CAPTURE_SLOW_QUERIES', true),
            'slow_query_threshold' => env('SENTRY_SLOW_QUERY_THRESHOLD', 2000), // milliseconds
            'include_sql_bindings' => env('SENTRY_INCLUDE_SQL_BINDINGS', false),
            'max_breadcrumbs' => env('SENTRY_MAX_BREADCRUMBS', 50),
        ],

        // Performance monitoring
        'performance' => [
            'monitor_api_requests' => env('SENTRY_MONITOR_API', true),
            'monitor_blockchain_operations' => env('SENTRY_MONITOR_BLOCKCHAIN', true),
            'monitor_ai_operations' => env('SENTRY_MONITOR_AI', true),
            'transaction_sample_rate' => env('SENTRY_TRANSACTION_SAMPLE_RATE', 0.1),
            'slow_request_threshold' => env('SENTRY_SLOW_REQUEST_THRESHOLD', 5000), // milliseconds
        ],

        // Context configuration
        'context' => [
            'include_user_context' => env('SENTRY_INCLUDE_USER_CONTEXT', true),
            'include_request_context' => env('SENTRY_INCLUDE_REQUEST_CONTEXT', true),
            'include_server_context' => env('SENTRY_INCLUDE_SERVER_CONTEXT', true),
            'mask_sensitive_data' => env('SENTRY_MASK_SENSITIVE_DATA', true),
        ],

        // Custom tags for filtering and grouping
        'custom_tags' => [
            'platform' => 'ai-blockchain-analytics',
            'version' => env('APP_VERSION', 'v0.9.0'),
            'environment' => env('APP_ENV'),
            'component' => env('SENTRY_COMPONENT', 'main'),
            'deployment_id' => env('DEPLOYMENT_ID'),
        ],

        // Blockchain-specific monitoring
        'blockchain' => [
            'track_contract_analysis' => env('SENTRY_TRACK_CONTRACT_ANALYSIS', true),
            'track_verification_badges' => env('SENTRY_TRACK_VERIFICATION_BADGES', true),
            'track_sentiment_analysis' => env('SENTRY_TRACK_SENTIMENT_ANALYSIS', true),
            'track_network_operations' => env('SENTRY_TRACK_NETWORK_OPS', true),
            'max_contract_address_length' => 10, // For privacy in logs
        ],

        // AI operation monitoring
        'ai' => [
            'track_openai_requests' => env('SENTRY_TRACK_OPENAI', true),
            'track_sentiment_processing' => env('SENTRY_TRACK_SENTIMENT_PROCESSING', true),
            'track_analysis_generation' => env('SENTRY_TRACK_ANALYSIS_GENERATION', true),
            'include_ai_response_metadata' => env('SENTRY_INCLUDE_AI_METADATA', false),
        ],

        // Rate limiting and sampling
        'sampling' => [
            'error_sample_rate' => env('SENTRY_ERROR_SAMPLE_RATE', 1.0),
            'transaction_sample_rate' => env('SENTRY_TRANSACTION_SAMPLE_RATE', 0.1),
            'profile_sample_rate' => env('SENTRY_PROFILE_SAMPLE_RATE', 0.05),
            'session_sample_rate' => env('SENTRY_SESSION_SAMPLE_RATE', 1.0),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Telescope Configuration
    |--------------------------------------------------------------------------
    |
    | Enhanced Telescope configuration with production restrictions
    | and AI Blockchain Analytics specific watchers.
    |
    */

    'telescope' => [
        'enabled' => env('TELESCOPE_ENABLED', env('APP_ENV') !== 'production'),
        
        // Production access control
        'production' => [
            'enabled' => env('TELESCOPE_PRODUCTION_ENABLED', false),
            'require_authentication' => env('TELESCOPE_REQUIRE_AUTH', true),
            'allowed_ips' => array_filter(explode(',', env('TELESCOPE_ALLOWED_IPS', '127.0.0.1,::1'))),
            'allowed_emails' => array_filter(explode(',', env('TELESCOPE_ALLOWED_EMAILS', ''))),
            'max_failed_attempts' => env('TELESCOPE_MAX_FAILED_ATTEMPTS', 3),
            'lockout_duration' => env('TELESCOPE_LOCKOUT_DURATION', 300), // 5 minutes
            'log_access_attempts' => env('TELESCOPE_LOG_ACCESS_ATTEMPTS', true),
        ],

        // Performance and storage
        'performance' => [
            'sampling_rate' => env('TELESCOPE_SAMPLING_RATE', env('APP_ENV') === 'production' ? 0.1 : 1.0),
            'memory_limit' => env('TELESCOPE_MEMORY_LIMIT', '512M'),
            'time_limit' => env('TELESCOPE_TIME_LIMIT', 30),
            'max_entries_per_type' => env('TELESCOPE_MAX_ENTRIES', 1000),
            'prune_frequency' => env('TELESCOPE_PRUNE_FREQUENCY', 'daily'),
        ],

        // Data retention (in hours)
        'retention' => [
            'exceptions' => env('TELESCOPE_RETENTION_EXCEPTIONS', 168), // 7 days
            'jobs' => env('TELESCOPE_RETENTION_JOBS', 72), // 3 days
            'logs' => env('TELESCOPE_RETENTION_LOGS', 168), // 7 days
            'requests' => env('TELESCOPE_RETENTION_REQUESTS', 24), // 1 day
            'queries' => env('TELESCOPE_RETENTION_QUERIES', 24), // 1 day
            'cache' => env('TELESCOPE_RETENTION_CACHE', 24), // 1 day
            'commands' => env('TELESCOPE_RETENTION_COMMANDS', 72), // 3 days
            'notifications' => env('TELESCOPE_RETENTION_NOTIFICATIONS', 72), // 3 days
        ],

        // Watcher configuration
        'watchers' => [
            'cache' => env('TELESCOPE_CACHE_WATCHER', env('APP_ENV') !== 'production'),
            'commands' => env('TELESCOPE_COMMAND_WATCHER', true),
            'dumps' => env('TELESCOPE_DUMP_WATCHER', env('APP_ENV') !== 'production'),
            'events' => env('TELESCOPE_EVENT_WATCHER', env('APP_ENV') !== 'production'),
            'exceptions' => env('TELESCOPE_EXCEPTION_WATCHER', true),
            'jobs' => env('TELESCOPE_JOB_WATCHER', true),
            'logs' => env('TELESCOPE_LOG_WATCHER', true),
            'mail' => env('TELESCOPE_MAIL_WATCHER', true),
            'models' => env('TELESCOPE_MODEL_WATCHER', env('APP_ENV') !== 'production'),
            'notifications' => env('TELESCOPE_NOTIFICATION_WATCHER', true),
            'queries' => env('TELESCOPE_QUERY_WATCHER', env('APP_ENV') !== 'production'),
            'redis' => env('TELESCOPE_REDIS_WATCHER', true),
            'requests' => env('TELESCOPE_REQUEST_WATCHER', true),
            'schedule' => env('TELESCOPE_SCHEDULE_WATCHER', true),
            'views' => env('TELESCOPE_VIEW_WATCHER', env('APP_ENV') !== 'production'),
        ],

        // AI Blockchain Analytics specific filters
        'blockchain_filters' => [
            'track_contract_analysis' => env('TELESCOPE_TRACK_CONTRACT_ANALYSIS', true),
            'track_verification_operations' => env('TELESCOPE_TRACK_VERIFICATION', true),
            'track_sentiment_analysis' => env('TELESCOPE_TRACK_SENTIMENT', true),
            'track_ai_operations' => env('TELESCOPE_TRACK_AI_OPS', true),
            'hide_sensitive_contract_data' => env('TELESCOPE_HIDE_SENSITIVE_DATA', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Monitoring Features
    |--------------------------------------------------------------------------
    |
    | AI Blockchain Analytics specific monitoring features and integrations.
    |
    */

    'custom' => [
        // Application health monitoring
        'health_checks' => [
            'enabled' => env('HEALTH_CHECKS_ENABLED', true),
            'interval' => env('HEALTH_CHECK_INTERVAL', 300), // 5 minutes
            'endpoints' => [
                'database' => env('HEALTH_CHECK_DATABASE', true),
                'redis' => env('HEALTH_CHECK_REDIS', true),
                'external_apis' => env('HEALTH_CHECK_EXTERNAL_APIS', true),
                'blockchain_rpcs' => env('HEALTH_CHECK_BLOCKCHAIN_RPCS', true),
            ],
        ],

        // Performance metrics
        'metrics' => [
            'collect_response_times' => env('COLLECT_RESPONSE_TIMES', true),
            'collect_memory_usage' => env('COLLECT_MEMORY_USAGE', true),
            'collect_queue_sizes' => env('COLLECT_QUEUE_SIZES', true),
            'collect_cache_hit_rates' => env('COLLECT_CACHE_HIT_RATES', true),
        ],

        // Business metrics for AI Blockchain Analytics
        'business_metrics' => [
            'track_contract_analyses' => env('TRACK_CONTRACT_ANALYSES', true),
            'track_verification_badges' => env('TRACK_VERIFICATION_BADGES', true),
            'track_user_registrations' => env('TRACK_USER_REGISTRATIONS', true),
            'track_api_usage' => env('TRACK_API_USAGE', true),
            'track_subscription_events' => env('TRACK_SUBSCRIPTION_EVENTS', true),
        ],

        // Security monitoring
        'security' => [
            'track_failed_logins' => env('TRACK_FAILED_LOGINS', true),
            'track_suspicious_requests' => env('TRACK_SUSPICIOUS_REQUESTS', true),
            'track_rate_limit_hits' => env('TRACK_RATE_LIMIT_HITS', true),
            'track_unauthorized_access' => env('TRACK_UNAUTHORIZED_ACCESS', true),
            'alert_threshold' => env('SECURITY_ALERT_THRESHOLD', 10), // events per minute
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Alerting Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for automated alerts and notifications based on
    | monitoring data and error thresholds.
    |
    */

    'alerting' => [
        'enabled' => env('ALERTING_ENABLED', env('APP_ENV') === 'production'),
        
        // Error rate thresholds
        'error_thresholds' => [
            'critical' => env('ERROR_THRESHOLD_CRITICAL', 0.05), // 5% error rate
            'warning' => env('ERROR_THRESHOLD_WARNING', 0.02), // 2% error rate
            'time_window' => env('ERROR_THRESHOLD_WINDOW', 300), // 5 minutes
        ],

        // Performance thresholds
        'performance_thresholds' => [
            'response_time_critical' => env('RESPONSE_TIME_CRITICAL', 10000), // 10 seconds
            'response_time_warning' => env('RESPONSE_TIME_WARNING', 5000), // 5 seconds
            'memory_usage_critical' => env('MEMORY_USAGE_CRITICAL', 90), // 90%
            'memory_usage_warning' => env('MEMORY_USAGE_WARNING', 80), // 80%
        ],

        // Notification channels
        'channels' => [
            'slack' => env('ALERT_SLACK_ENABLED', false),
            'email' => env('ALERT_EMAIL_ENABLED', true),
            'sentry' => env('ALERT_SENTRY_ENABLED', true),
            'webhook' => env('ALERT_WEBHOOK_ENABLED', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Privacy and Compliance
    |--------------------------------------------------------------------------
    |
    | Configuration for data privacy, GDPR compliance, and sensitive
    | data handling in monitoring systems.
    |
    */

    'privacy' => [
        'mask_user_data' => env('MASK_USER_DATA', true),
        'mask_ip_addresses' => env('MASK_IP_ADDRESSES', false),
        'mask_contract_addresses' => env('MASK_CONTRACT_ADDRESSES', false),
        'exclude_personal_data' => env('EXCLUDE_PERSONAL_DATA', false),
        'data_retention_days' => env('MONITORING_DATA_RETENTION', 30),
        'anonymize_after_days' => env('ANONYMIZE_DATA_AFTER', 7),
        
        // GDPR compliance
        'gdpr_compliance' => [
            'enabled' => env('GDPR_COMPLIANCE_ENABLED', true),
            'data_processor_info' => [
                'sentry' => 'Sentry.io (error tracking)',
                'telescope' => 'Local storage (debugging)',
            ],
            'user_consent_required' => env('USER_CONSENT_REQUIRED', false),
        ],
    ],
];