<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Sentry Laravel Configuration
    |--------------------------------------------------------------------------
    |
    | Enhanced Sentry configuration for AI Blockchain Analytics with
    | production-ready error tracking, performance monitoring, and security.
    |
    */

    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),

    /*
    |--------------------------------------------------------------------------
    | Environment Configuration
    |--------------------------------------------------------------------------
    */

    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV', 'production')),
    'release' => env('SENTRY_RELEASE', 'ai-blockchain-analytics@'.config('app.version', '1.0.0')),

    /*
    |--------------------------------------------------------------------------
    | Error Tracking Configuration
    |--------------------------------------------------------------------------
    */

    'breadcrumbs' => [
        'logs' => env('SENTRY_BREADCRUMBS_LOGS', true),
        'cache' => env('SENTRY_BREADCRUMBS_CACHE', true),
        'livewire' => env('SENTRY_BREADCRUMBS_LIVEWIRE', false),
        'sql_queries' => env('SENTRY_BREADCRUMBS_SQL_QUERIES', env('APP_DEBUG', false)),
        'sql_bindings' => env('SENTRY_BREADCRUMBS_SQL_BINDINGS', env('APP_DEBUG', false)),
        'queue_info' => env('SENTRY_BREADCRUMBS_QUEUE_INFO', true),
        'command_info' => env('SENTRY_BREADCRUMBS_COMMAND_INFO', true),
        'http_client_requests' => env('SENTRY_BREADCRUMBS_HTTP_CLIENT_REQUESTS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring
    |--------------------------------------------------------------------------
    */

    'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE') === null ? null : (float) env('SENTRY_TRACES_SAMPLE_RATE'),
    'profiles_sample_rate' => env('SENTRY_PROFILES_SAMPLE_RATE') === null ? null : (float) env('SENTRY_PROFILES_SAMPLE_RATE'),

    /*
    |--------------------------------------------------------------------------
    | Security & Privacy
    |--------------------------------------------------------------------------
    */

    'send_default_pii' => env('SENTRY_SEND_DEFAULT_PII', false),
    'max_breadcrumbs' => env('SENTRY_MAX_BREADCRUMBS', 50),
    'max_value_length' => env('SENTRY_MAX_VALUE_LENGTH', 2048),

    /*
    |--------------------------------------------------------------------------
    | Sampling Configuration
    |--------------------------------------------------------------------------
    */

    'sample_rate' => env('SENTRY_SAMPLE_RATE') === null ? null : (float) env('SENTRY_SAMPLE_RATE'),

    /*
    |--------------------------------------------------------------------------
    | Data Scrubbing & Filtering
    |--------------------------------------------------------------------------
    */

    'before_send' => function (\Sentry\Event $event, ?\Sentry\EventHint $hint): ?\Sentry\Event {
        // Filter sensitive data
        $sensitiveKeys = [
            'password', 'passwd', 'secret', 'api_key', 'token', 'key',
            'authorization', 'auth', 'x-api-key', 'x-auth-token',
            'credit_card', 'cc_number', 'ssn', 'social_security_number'
        ];

        $context = $event->getContext();
        if ($context) {
            foreach ($sensitiveKeys as $key) {
                if (isset($context[$key])) {
                    $context[$key] = '[Filtered]';
                }
            }
            $event->setContext($context);
        }

        // Filter user data in production
        if (env('APP_ENV') === 'production') {
            $user = $event->getUser();
            if ($user && isset($user['email'])) {
                $user['email'] = hash('sha256', $user['email']); // Hash email for privacy
                $event->setUser($user);
            }
        }

        return $event;
    },

    'before_send_transaction' => function (\Sentry\Event $event): ?\Sentry\Event {
        // Filter slow queries and optimize performance tracking
        $tags = $event->getTags();
        
        // Skip health check transactions to reduce noise
        if (isset($tags['transaction']) && in_array($tags['transaction'], [
            'GET /health',
            'GET /ready',
            'GET /metrics',
            'GET /status'
        ])) {
            return null;
        }

        return $event;
    },

    /*
    |--------------------------------------------------------------------------
    | Integration Configuration
    |--------------------------------------------------------------------------
    */

    'integrations' => [
        Sentry\Laravel\Integration::class => [
            'enable_tracing' => env('SENTRY_ENABLE_TRACING', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Context Configuration
    |--------------------------------------------------------------------------
    */

    'context' => [
        'user' => true,
        'tags' => true,
        'extra' => true,
        'server_name' => env('SENTRY_SERVER_NAME', gethostname()),
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Tags
    |--------------------------------------------------------------------------
    */

    'tags' => [
        'component' => 'blockchain-analytics',
        'layer' => 'application',
        'php_version' => PHP_VERSION,
        'laravel_version' => \Illuminate\Foundation\Application::VERSION,
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Filtering
    |--------------------------------------------------------------------------
    */

    'ignore_exceptions' => [
        // HTTP exceptions we don't want to track
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class,
        
        // Authentication exceptions
        \Illuminate\Auth\AuthenticationException::class,
        \Laravel\Sanctum\Exceptions\MissingAbilityException::class,
        
        // Validation exceptions (too noisy)
        \Illuminate\Validation\ValidationException::class,
        
        // Rate limiting
        \Illuminate\Http\Exceptions\ThrottleRequestsException::class,
        
        // Custom blockchain exceptions that are expected
        \App\Exceptions\BlockchainConnectionException::class,
        \App\Exceptions\InvalidContractAddressException::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Error Processors
    |--------------------------------------------------------------------------
    */

    'processors' => [
        // Add custom processors here
        \App\Services\Monitoring\SentryErrorProcessor::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Integration
    |--------------------------------------------------------------------------
    */

    'capture_failed_jobs' => env('SENTRY_CAPTURE_FAILED_JOBS', true),
    'capture_sql_queries' => env('SENTRY_CAPTURE_SQL_QUERIES', env('APP_DEBUG', false)),

    /*
    |--------------------------------------------------------------------------
    | Session Configuration
    |--------------------------------------------------------------------------
    */

    'auto_session_tracking' => env('SENTRY_AUTO_SESSION_TRACKING', true),
    'session_sample_rate' => env('SENTRY_SESSION_SAMPLE_RATE', 1.0),

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Configuration
    |--------------------------------------------------------------------------
    */

    'http_timeout' => env('SENTRY_HTTP_TIMEOUT', 5),
    'http_connect_timeout' => env('SENTRY_HTTP_CONNECT_TIMEOUT', 1),
    'http_compression' => env('SENTRY_HTTP_COMPRESSION', true),

    /*
    |--------------------------------------------------------------------------
    | Caching Configuration
    |--------------------------------------------------------------------------
    */

    'cache' => [
        'enabled' => env('SENTRY_CACHE_ENABLED', true),
        'prefix' => env('SENTRY_CACHE_PREFIX', 'sentry:'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Development Configuration
    |--------------------------------------------------------------------------
    */

    'in_app_include' => [
        base_path('app'),
        base_path('bootstrap'),
        base_path('config'),
        base_path('database'),
        base_path('resources'),
        base_path('routes'),
    ],

    'in_app_exclude' => [
        base_path('vendor'),
        base_path('storage'),
        base_path('node_modules'),
    ],
];