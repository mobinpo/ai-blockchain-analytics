<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enhanced Verification Badge Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the enhanced verification badge system with
    | SHA-256 + HMAC cryptographic protection and anti-spoofing measures.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Cryptographic Keys
    |--------------------------------------------------------------------------
    |
    | These keys are used for generating and verifying cryptographic signatures.
    | The secret key is used for basic signature generation, while the HMAC key
    | provides an additional layer of authentication.
    |
    */

    'secret_key' => env('VERIFICATION_SECRET_KEY', null),
    'hmac_key' => env('VERIFICATION_HMAC_KEY', null),

    /*
    |--------------------------------------------------------------------------
    | URL Lifetime
    |--------------------------------------------------------------------------
    |
    | Default lifetime for verification URLs in seconds.
    | Users can override this when generating URLs, but this sets the default.
    |
    | Options:
    | - 1800 (30 minutes)
    | - 3600 (1 hour) - default
    | - 7200 (2 hours)
    | - 14400 (4 hours)
    |
    */

    'url_lifetime' => env('VERIFICATION_URL_LIFETIME', 3600),

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Advanced security features that can be enabled or disabled.
    | All features are enabled by default for maximum security.
    |
    */

    'security' => [
        // Require IP address binding (prevents use from different IPs)
        'require_ip_binding' => env('VERIFICATION_REQUIRE_IP_BINDING', true),
        
        // Require user agent binding (prevents use from different browsers)
        'require_user_agent_binding' => env('VERIFICATION_REQUIRE_USER_AGENT_BINDING', true),
        
        // Enable rate limiting to prevent abuse
        'enable_rate_limiting' => env('VERIFICATION_ENABLE_RATE_LIMITING', true),
        
        // Enable nonce tracking to prevent replay attacks
        'enable_nonce_tracking' => env('VERIFICATION_ENABLE_NONCE_TRACKING', true),
        
        // Current signature version for compatibility
        'signature_version' => 'v3.0',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configuration for rate limiting verification attempts to prevent abuse.
    |
    */

    'rate_limiting' => [
        // Maximum verification attempts per user per contract per hour
        'max_attempts' => env('VERIFICATION_MAX_ATTEMPTS', 5),
        
        // Time window for rate limiting (in seconds)
        'time_window' => env('VERIFICATION_RATE_LIMIT_WINDOW', 3600),
        
        // Global rate limit per IP per hour
        'global_max_per_ip' => env('VERIFICATION_GLOBAL_MAX_PER_IP', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Verification Badge Display
    |--------------------------------------------------------------------------
    |
    | Configuration for how verification badges are displayed and cached.
    |
    */

    'badge' => [
        // Cache verification status for this many seconds
        'cache_duration' => env('VERIFICATION_CACHE_DURATION', 3600),
        
        // Show enhanced security indicators
        'show_security_level' => env('VERIFICATION_SHOW_SECURITY_LEVEL', true),
        
        // Enable tooltip with detailed information
        'enable_tooltips' => env('VERIFICATION_ENABLE_TOOLTIPS', true),
        
        // Badge styling theme
        'theme' => env('VERIFICATION_BADGE_THEME', 'default'), // default, minimal, detailed
    ],

    /*
    |--------------------------------------------------------------------------
    | Metadata Validation
    |--------------------------------------------------------------------------
    |
    | Rules for validating metadata submitted with verification requests.
    |
    */

    'metadata' => [
        'allowed_fields' => [
            'project_name',
            'website',
            'description',
            'category',
            'tags'
        ],
        
        'max_lengths' => [
            'project_name' => 100,
            'website' => 200,
            'description' => 500,
            'category' => 50,
            'tag' => 30,
        ],
        
        'max_tags' => 10,
        
        'allowed_categories' => [
            'DeFi',
            'NFT',
            'Gaming',
            'Infrastructure',
            'Governance',
            'Bridge',
            'Exchange',
            'Lending',
            'Yield Farming',
            'Insurance',
            'Oracle',
            'Other'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for logging verification events and security incidents.
    |
    */

    'logging' => [
        // Log all verification attempts
        'log_attempts' => env('VERIFICATION_LOG_ATTEMPTS', true),
        
        // Log security violations (failed signatures, replay attempts, etc.)
        'log_security_violations' => env('VERIFICATION_LOG_SECURITY_VIOLATIONS', true),
        
        // Log successful verifications
        'log_successful_verifications' => env('VERIFICATION_LOG_SUCCESSFUL', true),
        
        // Log level for verification events
        'log_level' => env('VERIFICATION_LOG_LEVEL', 'info'),
        
        // Separate log channel for verification events
        'log_channel' => env('VERIFICATION_LOG_CHANNEL', 'single'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for database storage of verification records.
    |
    */

    'database' => [
        // Automatically clean up expired verification attempts
        'cleanup_expired' => env('VERIFICATION_CLEANUP_EXPIRED', true),
        
        // How often to run cleanup (in hours)
        'cleanup_interval' => env('VERIFICATION_CLEANUP_INTERVAL', 24),
        
        // Keep verification records for this many days
        'retention_days' => env('VERIFICATION_RETENTION_DAYS', 365),
        
        // Enable database indexing for performance
        'enable_indexing' => env('VERIFICATION_ENABLE_INDEXING', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for API endpoints and responses.
    |
    */

    'api' => [
        // Enable CORS for verification endpoints
        'enable_cors' => env('VERIFICATION_ENABLE_CORS', true),
        
        // Allowed origins for CORS
        'cors_origins' => env('VERIFICATION_CORS_ORIGINS', '*'),
        
        // API response format version
        'response_version' => 'v1.0',
        
        // Include debug information in responses (only for development)
        'include_debug_info' => env('VERIFICATION_INCLUDE_DEBUG', false),
        
        // Maximum batch size for batch operations
        'max_batch_size' => env('VERIFICATION_MAX_BATCH_SIZE', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Integration Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for integrating with external services and webhooks.
    |
    */

    'integration' => [
        // Webhook URL to notify on successful verification
        'webhook_url' => env('VERIFICATION_WEBHOOK_URL', null),
        
        // Webhook secret for signature verification
        'webhook_secret' => env('VERIFICATION_WEBHOOK_SECRET', null),
        
        // Enable blockchain verification (check if contract exists)
        'verify_on_blockchain' => env('VERIFICATION_VERIFY_ON_BLOCKCHAIN', false),
        
        // Blockchain RPC endpoints for verification
        'rpc_endpoints' => [
            'ethereum' => env('ETHEREUM_RPC_URL', null),
            'bsc' => env('BSC_RPC_URL', null),
            'polygon' => env('POLYGON_RPC_URL', null),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for performance optimization and caching.
    |
    */

    'performance' => [
        // Enable Redis caching for verification data
        'enable_redis_cache' => env('VERIFICATION_ENABLE_REDIS', true),
        
        // Cache prefix for verification data
        'cache_prefix' => env('VERIFICATION_CACHE_PREFIX', 'verification'),
        
        // Enable compression for large payloads
        'enable_compression' => env('VERIFICATION_ENABLE_COMPRESSION', true),
        
        // Queue verification processing for better performance
        'queue_processing' => env('VERIFICATION_QUEUE_PROCESSING', false),
        
        // Queue connection to use
        'queue_connection' => env('VERIFICATION_QUEUE_CONNECTION', 'redis'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Development Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options that should only be used in development.
    |
    */

    'development' => [
        // Enable test mode (less strict validation)
        'test_mode' => env('VERIFICATION_TEST_MODE', false),
        
        // Allow insecure URLs in test mode
        'allow_insecure_urls' => env('VERIFICATION_ALLOW_INSECURE', false),
        
        // Enable debug logging
        'debug_logging' => env('VERIFICATION_DEBUG_LOGGING', false),
        
        // Mock external services
        'mock_external_services' => env('VERIFICATION_MOCK_EXTERNAL', false),
    ],

];