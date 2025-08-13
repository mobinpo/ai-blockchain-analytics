<?php

/**
 * Sentry Laravel SDK configuration file for AI Blockchain Analytics v0.9.0
 * Optimized for production error tracking and monitoring
 *
 * @see https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/
 */
return [

    // @see https://docs.sentry.io/product/sentry-basics/dsn-explainer/
    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),

    // The release version of your application
    'release' => env('SENTRY_RELEASE', 'v0.9.0'),

    // When left empty or `null` the Laravel environment will be used
    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV', 'production')),

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#sample-rate
    'sample_rate' => (float) env('SENTRY_SAMPLE_RATE', 0.1),

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#traces-sample-rate
    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.05),

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#profiles-sample-rate
    'profiles_sample_rate' => (float) env('SENTRY_PROFILES_SAMPLE_RATE', 0.05),

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#enable-logs
    'enable_logs' => env('SENTRY_ENABLE_LOGS', true),

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#send-default-pii
    'send_default_pii' => env('SENTRY_SEND_DEFAULT_PII', false),

    // Ignore specific exceptions that are not critical
    'ignore_exceptions' => [
        Illuminate\Auth\AuthenticationException::class,
        Illuminate\Auth\Access\AuthorizationException::class,
        Illuminate\Database\Eloquent\ModelNotFoundException::class,
        Illuminate\Http\Exceptions\ThrottleRequestsException::class,
        Illuminate\Session\TokenMismatchException::class,
        Illuminate\Validation\ValidationException::class,
        Symfony\Component\HttpKernel\Exception\HttpException::class,
        Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class,
    ],

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#ignore-transactions
    'ignore_transactions' => [
        // Health check endpoints
        '/up',
        '/api/health',
        '/health',
        '/ping',
        
        // Telescope routes (should be disabled in production anyway)
        '/telescope*',
        
        // Monitoring endpoints
        '/metrics',
        '/status',
        
        // Static assets
        '*.css',
        '*.js',
        '*.png',
        '*.jpg',
        '*.jpeg',
        '*.gif',
        '*.svg',
        '*.ico',
        '*.woff',
        '*.woff2',
        '*.ttf',
        '*.eot',
        
        // Favicon requests
        '/favicon.ico',
        '/robots.txt',
        '/sitemap.xml',
        
        // Common bot requests
        '/.well-known/*',
        '/wp-admin*',
        '/wp-login*',
        '/admin*',
        
        // API health checks
        '/api/ping',
        '/api/status',
    ],

    // Maximum breadcrumbs
    'max_breadcrumbs' => (int) env('SENTRY_MAX_BREADCRUMBS', 50),

    // Attach stack trace for non-exception logs
    'attach_stacktrace' => env('SENTRY_ATTACH_STACKTRACE', true),

    // Error levels to capture
    'error_types' => E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED & ~E_NOTICE,

    // Custom tags for the application
    'tags' => [
        'platform' => 'ai-blockchain-analytics',
        'version' => 'v0.9.0',
        'component' => env('SENTRY_COMPONENT', 'main'),
    ],

    // Before send callback for custom processing
    'before_send' => function (\Sentry\Event $event, ?\Sentry\EventHint $hint): ?\Sentry\Event {
        // Add custom context for AI Blockchain Analytics
        $event->setTag('service', 'ai-blockchain-analytics');
        $event->setTag('deployment', env('APP_ENV', 'unknown'));
        
        // Add blockchain-specific context if available
        if (request()) {
            $request = request();
            
            // Add contract address if present
            if ($request->has('contract_address')) {
                $event->setExtra('contract_address', substr($request->get('contract_address'), 0, 10) . '...');
            }
            
            // Add network context
            if ($request->has('network')) {
                $event->setTag('blockchain_network', $request->get('network'));
            }
            
            // Add API endpoint context
            if ($request->is('api/*')) {
                $event->setTag('api_endpoint', $request->path());
            }
        }
        
        return $event;
    },

    // Before send transaction callback for performance monitoring
    'before_send_transaction' => function (\Sentry\Event $transaction): ?\Sentry\Event {
        // Only send transactions in production if they exceed thresholds
        if (app()->environment('production')) {
            $duration = $transaction->getStartTimestamp() - $transaction->getTimestamp();
            
            // Skip fast transactions in production to reduce noise
            if ($duration < 1.0) {
                return null;
            }
        }
        
        return $transaction;
    },

    // Context lines around error
    'context_lines' => env('SENTRY_CONTEXT_LINES', 5),
];