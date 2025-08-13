<?php

use Laravel\Telescope\Http\Middleware\Authorize;
use Laravel\Telescope\Watchers;

/**
 * Enhanced Telescope Configuration for Production Deployment
 * Optimized for Kubernetes and ECS deployments with security restrictions
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Telescope Master Switch - Production Safe
    |--------------------------------------------------------------------------
    */
    'enabled' => env('TELESCOPE_ENABLED', false),
    
    /*
    |--------------------------------------------------------------------------
    | Production Domain and Path Configuration
    |--------------------------------------------------------------------------
    */
    'domain' => env('TELESCOPE_DOMAIN'),
    'path' => env('TELESCOPE_PATH', 'telescope'),
    
    /*
    |--------------------------------------------------------------------------
    | Production Storage Driver
    |--------------------------------------------------------------------------
    */
    'driver' => env('TELESCOPE_DRIVER', 'database'),
    
    'storage' => [
        'database' => [
            'connection' => env('TELESCOPE_DB_CONNECTION', env('DB_CONNECTION', 'pgsql')),
            'chunk' => 1000,
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Production Queue Configuration
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'connection' => env('TELESCOPE_QUEUE_CONNECTION', 'redis'),
        'queue' => env('TELESCOPE_QUEUE', 'telescope'),
        'delay' => env('TELESCOPE_QUEUE_DELAY', 10),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Production Security Middleware
    |--------------------------------------------------------------------------
    */
    'middleware' => [
        'web',
        \App\Http\Middleware\TelescopeAuthorize::class,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Production Path and Command Restrictions
    |--------------------------------------------------------------------------
    */
    'only_paths' => [
        // Only monitor API routes in production
        'api/*',
    ],
    
    'ignore_paths' => [
        'livewire*',
        'nova-api*',
        'pulse*',
        '_debugbar*',
        'telescope*',
        'horizon*',
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
        '/health*',
        '/metrics*',
        '/status*',
    ],
    
    'ignore_commands' => [
        'telescope:*',
        'horizon:*',
        'queue:*',
        'schedule:*',
        'migrate:*',
        'db:seed',
        'config:*',
        'route:*',
        'view:*',
        'optimize:*',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Production Optimized Watchers
    |--------------------------------------------------------------------------
    */
    'watchers' => [
        // Critical monitoring watchers for production
        Watchers\ExceptionWatcher::class => env('TELESCOPE_EXCEPTION_WATCHER', true),
        
        Watchers\LogWatcher::class => [
            'enabled' => env('TELESCOPE_LOG_WATCHER', true),
            'level' => 'error', // Only log errors and above in production
        ],
        
        Watchers\QueryWatcher::class => [
            'enabled' => env('TELESCOPE_QUERY_WATCHER', true),
            'ignore_packages' => true,
            'ignore_paths' => [
                'telescope*',
                'horizon*',
                '_debugbar*',
            ],
            'slow' => 200, // Only capture slow queries in production
        ],
        
        Watchers\JobWatcher::class => env('TELESCOPE_JOB_WATCHER', true),
        Watchers\ScheduleWatcher::class => env('TELESCOPE_SCHEDULE_WATCHER', true),
        
        Watchers\RequestWatcher::class => [
            'enabled' => env('TELESCOPE_REQUEST_WATCHER', true),
            'size_limit' => env('TELESCOPE_RESPONSE_SIZE_LIMIT', 32), // Smaller limit for production
            'ignore_http_methods' => ['OPTIONS'],
            'ignore_status_codes' => [200, 301, 302, 304, 401, 403, 404],
        ],
        
        // Performance watchers - limited for production
        Watchers\CacheWatcher::class => [
            'enabled' => env('TELESCOPE_CACHE_WATCHER', false), // Disabled by default in production
            'hidden' => [],
            'ignore' => [],
        ],
        
        Watchers\RedisWatcher::class => env('TELESCOPE_REDIS_WATCHER', false), // Disabled by default
        
        // Security and notification watchers
        Watchers\MailWatcher::class => env('TELESCOPE_MAIL_WATCHER', true),
        Watchers\NotificationWatcher::class => env('TELESCOPE_NOTIFICATION_WATCHER', true),
        
        // Development watchers - disabled in production
        Watchers\DumpWatcher::class => [
            'enabled' => env('TELESCOPE_DUMP_WATCHER', false),
            'always' => env('TELESCOPE_DUMP_WATCHER_ALWAYS', false),
        ],
        
        Watchers\CommandWatcher::class => [
            'enabled' => env('TELESCOPE_COMMAND_WATCHER', false),
            'ignore' => [
                'telescope:*',
                'horizon:*',
                'queue:*',
            ],
        ],
        
        Watchers\EventWatcher::class => [
            'enabled' => env('TELESCOPE_EVENT_WATCHER', false),
            'ignore' => [
                'Illuminate\\*',
                'Laravel\\*',
            ],
        ],
        
        Watchers\GateWatcher::class => [
            'enabled' => env('TELESCOPE_GATE_WATCHER', false),
            'ignore_abilities' => [],
            'ignore_packages' => true,
            'ignore_paths' => [],
        ],
        
        Watchers\ModelWatcher::class => [
            'enabled' => env('TELESCOPE_MODEL_WATCHER', false),
            'events' => [], // Disabled in production
            'hydrations' => false,
        ],
        
        Watchers\ViewWatcher::class => env('TELESCOPE_VIEW_WATCHER', false), // Disabled in production
        Watchers\ClientRequestWatcher::class => env('TELESCOPE_CLIENT_REQUEST_WATCHER', false),
        Watchers\BatchWatcher::class => env('TELESCOPE_BATCH_WATCHER', true),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Production Data Retention
    |--------------------------------------------------------------------------
    */
    'pruning' => [
        'enabled' => true,
        'retention_hours' => env('TELESCOPE_RETENTION_HOURS', 48), // 2 days in production
    ],
];