<?php

/**
 * Enhanced Sentry Configuration for Production Deployment
 * Optimized for Kubernetes and ECS deployments
 */
return [
    'dsn' => env('SENTRY_LARAVEL_DSN'),
    
    'release' => env('SENTRY_RELEASE', 'v1.0.0'),
    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV', 'production')),
    
    // Production optimized sample rates
    'sample_rate' => (float) env('SENTRY_SAMPLE_RATE', 0.1),
    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.05),
    'profiles_sample_rate' => (float) env('SENTRY_PROFILES_SAMPLE_RATE', 0.01),
    
    // Enhanced logging for production
    'enable_logs' => env('SENTRY_ENABLE_LOGS', true),
    'send_default_pii' => env('SENTRY_SEND_DEFAULT_PII', false),
    
    // Production-specific ignored transactions
    'ignore_transactions' => [
        '/up',
        '/health',
        '/metrics',
        '/status',
        '/api/health',
        '/api/status',
        '/telescope*',
        '/_debugbar*',
        '/horizon*',
        '*.css',
        '*.js',
        '*.ico',
        '*.png',
        '*.jpg',
        '*.jpeg',
        '*.gif',
        '*.svg',
        '*.woff*',
        '*.ttf',
        '*.eot',
        '/livewire*',
        '/broadcasting/auth',
    ],
    
    // Enhanced breadcrumb configuration for production
    'breadcrumbs' => [
        'logs' => env('SENTRY_BREADCRUMBS_LOGS_ENABLED', true),
        'cache' => env('SENTRY_BREADCRUMBS_CACHE_ENABLED', true),
        'livewire' => env('SENTRY_BREADCRUMBS_LIVEWIRE_ENABLED', false),
        'sql_queries' => env('SENTRY_BREADCRUMBS_SQL_QUERIES_ENABLED', false),
        'sql_bindings' => env('SENTRY_BREADCRUMBS_SQL_BINDINGS_ENABLED', false),
        'queue_info' => env('SENTRY_BREADCRUMBS_QUEUE_INFO_ENABLED', true),
        'command_info' => env('SENTRY_BREADCRUMBS_COMMAND_JOBS_ENABLED', true),
        'http_client_requests' => env('SENTRY_BREADCRUMBS_HTTP_CLIENT_REQUESTS_ENABLED', true),
        'notifications' => env('SENTRY_BREADCRUMBS_NOTIFICATIONS_ENABLED', true),
    ],
    
    // Enhanced performance monitoring for production
    'tracing' => [
        'queue_job_transactions' => env('SENTRY_TRACE_QUEUE_ENABLED', true),
        'queue_jobs' => env('SENTRY_TRACE_QUEUE_JOBS_ENABLED', true),
        'sql_queries' => env('SENTRY_TRACE_SQL_QUERIES_ENABLED', false),
        'sql_bindings' => env('SENTRY_TRACE_SQL_BINDINGS_ENABLED', false),
        'sql_origin' => env('SENTRY_TRACE_SQL_ORIGIN_ENABLED', false),
        'sql_origin_threshold_ms' => env('SENTRY_TRACE_SQL_ORIGIN_THRESHOLD_MS', 100),
        'views' => env('SENTRY_TRACE_VIEWS_ENABLED', false),
        'livewire' => env('SENTRY_TRACE_LIVEWIRE_ENABLED', false),
        'http_client_requests' => env('SENTRY_TRACE_HTTP_CLIENT_REQUESTS_ENABLED', true),
        'cache' => env('SENTRY_TRACE_CACHE_ENABLED', false),
        'redis_commands' => env('SENTRY_TRACE_REDIS_COMMANDS', false),
        'redis_origin' => env('SENTRY_TRACE_REDIS_ORIGIN_ENABLED', false),
        'notifications' => env('SENTRY_TRACE_NOTIFICATIONS_ENABLED', true),
        'missing_routes' => env('SENTRY_TRACE_MISSING_ROUTES_ENABLED', false),
        'continue_after_response' => env('SENTRY_TRACE_CONTINUE_AFTER_RESPONSE', true),
        'default_integrations' => env('SENTRY_TRACE_DEFAULT_INTEGRATIONS_ENABLED', true),
    ],
    
    // Production context enhancement
    'context_lines' => env('SENTRY_CONTEXT_LINES', 3),
    'max_request_body_size' => env('SENTRY_MAX_REQUEST_BODY_SIZE', 'medium'),
    
    // Container/K8s specific tags
    'tags' => [
        'deployment' => env('DEPLOYMENT_TYPE', 'unknown'),
        'cluster' => env('CLUSTER_NAME', 'unknown'),
        'namespace' => env('NAMESPACE', 'default'),
        'pod' => env('HOSTNAME', gethostname()),
    ],
    
    // Before send callback for production filtering
    'before_send' => function (\Sentry\Event $event, \Sentry\EventHint $hint): ?\Sentry\Event {
        // Filter out low-level errors in production
        if ($exception = $hint->exception) {
            // Skip common Laravel exceptions
            $skipExceptions = [
                \Illuminate\Http\Exceptions\ThrottleRequestsException::class,
                \Illuminate\Auth\AuthenticationException::class,
                \Illuminate\Validation\ValidationException::class,
                \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
                \Illuminate\Session\TokenMismatchException::class,
            ];
            
            foreach ($skipExceptions as $skipException) {
                if ($exception instanceof $skipException) {
                    return null;
                }
            }
        }
        
        return $event;
    },
];