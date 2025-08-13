<?php

use Laravel\Telescope\Http\Middleware\Authorize;
use Laravel\Telescope\Watchers;

return [
    /*
    |--------------------------------------------------------------------------
    | Telescope Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Telescope will be accessible from. If the
    | setting is null, Telescope will reside under the same domain as the
    | application. Otherwise, this value will be used as the subdomain.
    |
    */

    'domain' => env('TELESCOPE_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Telescope Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Telescope will be accessible from. Feel free
    | to change this path to anything you like. Note that the URI will not
    | affect the paths of its internal API that aren't exposed to users.
    |
    */

    'path' => env('TELESCOPE_PATH', 'admin/telescope'),

    /*
    |--------------------------------------------------------------------------
    | Telescope Storage Driver
    |--------------------------------------------------------------------------
    |
    | This configuration options determines the storage driver that will
    | be used to store Telescope's data. In addition, you may set any
    | custom options as needed by the particular driver you choose.
    |
    */

    'driver' => env('TELESCOPE_DRIVER', 'database'),

    'storage' => [
        'database' => [
            'connection' => env('DB_CONNECTION', 'pgsql'),
            'chunk' => 1000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Production Environment Restrictions
    |--------------------------------------------------------------------------
    |
    | Enhanced security for production environments with strict access controls
    | and monitoring limitations to prevent performance impact.
    |
    */

    'enabled' => env('TELESCOPE_ENABLED', env('APP_ENV') !== 'production'),

    /*
    |--------------------------------------------------------------------------
    | Telescope Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will be assigned to every Telescope route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can simply stick with this list.
    |
    */

    'middleware' => [
        'web',
        Authorize::class,
        \App\Http\Middleware\RestrictTelescopeAccess::class, // Custom security middleware
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed / Ignored Paths & Commands
    |--------------------------------------------------------------------------
    |
    | The following array lists the URI paths and Artisan commands that will
    | not be watched by Telescope. In addition to this list, some Laravel
    | commands, like migrations and queue commands, are always ignored.
    |
    */

    'only_paths' => [
        // Only watch specific critical paths in production
        'api/*',
        'verification/*',
        'sentiment/*',
        'admin/*',
    ],

    'ignore_paths' => [
        'horizon*',
        'health*',
        'ready*',
        'metrics*',
        'status*',
        'favicon.ico',
        '_ignition*',
        'nova-api*',
        'pulse*',
        'storage/app/public*',
        'livewire*',
    ],

    'ignore_commands' => [
        'migrate*',
        'queue:*',
        'schedule:*',
        'horizon:*',
        'telescope:*',
        'route:cache',
        'config:cache',
        'view:cache',
    ],

    /*
    |--------------------------------------------------------------------------
    | Telescope Watchers
    |--------------------------------------------------------------------------
    |
    | The following array lists the "watchers" that will be registered with
    | Telescope. The watchers gather application information when a request
    | is handled. Feel free to customize this list for your application.
    |
    */

    'watchers' => [
        Watchers\BatchWatcher::class => [
            'enabled' => env('TELESCOPE_BATCH_WATCHER', true),
        ],

        Watchers\CacheWatcher::class => [
            'enabled' => env('TELESCOPE_CACHE_WATCHER', env('APP_ENV') !== 'production'),
            'hidden' => [
                'telescope:*',
                'sentry:*',
                'session:*',
                'laravel_cache:*',
            ],
        ],

        Watchers\CommandWatcher::class => [
            'enabled' => env('TELESCOPE_COMMAND_WATCHER', true),
            'ignore' => [
                'migrate*',
                'queue:*',
                'schedule:*',
                'telescope:prune',
                'horizon:*',
            ],
        ],

        Watchers\DumpWatcher::class => [
            'enabled' => env('TELESCOPE_DUMP_WATCHER', true),
            'always' => env('TELESCOPE_DUMP_WATCHER_ALWAYS', false),
        ],

        Watchers\EventWatcher::class => [
            'enabled' => env('TELESCOPE_EVENT_WATCHER', true),
            'ignore' => [
                'Illuminate\Auth\Events\*',
                'Illuminate\Broadcasting\*',
                'Illuminate\Cache\Events\*',
                'Illuminate\Database\Events\*',
                'Laravel\Horizon\Events\*',
                'Laravel\Telescope\Events\*',
            ],
        ],

        Watchers\ExceptionWatcher::class => [
            'enabled' => env('TELESCOPE_EXCEPTION_WATCHER', true),
        ],

        Watchers\JobWatcher::class => [
            'enabled' => env('TELESCOPE_JOB_WATCHER', true),
        ],

        Watchers\LogWatcher::class => [
            'enabled' => env('TELESCOPE_LOG_WATCHER', true),
            'level' => env('TELESCOPE_LOG_LEVEL', 'warning'),
        ],

        Watchers\MailWatcher::class => [
            'enabled' => env('TELESCOPE_MAIL_WATCHER', true),
        ],

        Watchers\ModelWatcher::class => [
            'enabled' => env('TELESCOPE_MODEL_WATCHER', env('APP_ENV') !== 'production'),
            'hydrations' => env('TELESCOPE_MODEL_HYDRATIONS', false),
        ],

        Watchers\NotificationWatcher::class => [
            'enabled' => env('TELESCOPE_NOTIFICATION_WATCHER', true),
        ],

        Watchers\QueryWatcher::class => [
            'enabled' => env('TELESCOPE_QUERY_WATCHER', env('APP_ENV') !== 'production'),
            'ignore_packages' => true,
            'ignore_paths' => [
                'telescope*',
                'horizon*',
                'nova*',
            ],
            'slow' => env('TELESCOPE_SLOW_QUERY_THRESHOLD', 500), // ms
        ],

        Watchers\RedisWatcher::class => [
            'enabled' => env('TELESCOPE_REDIS_WATCHER', env('APP_ENV') !== 'production'),
        ],

        Watchers\RequestWatcher::class => [
            'enabled' => env('TELESCOPE_REQUEST_WATCHER', true),
            'size_limit' => env('TELESCOPE_REQUEST_SIZE_LIMIT', 64),
            'ignore_http_methods' => [
                'OPTIONS',
            ],
            'ignore_status_codes' => [
                404, 405,
            ],
        ],

        Watchers\ScheduleWatcher::class => [
            'enabled' => env('TELESCOPE_SCHEDULE_WATCHER', true),
        ],

        Watchers\ViewWatcher::class => [
            'enabled' => env('TELESCOPE_VIEW_WATCHER', env('APP_ENV') !== 'production'),
        ],

        Watchers\ClientRequestWatcher::class => [
            'enabled' => env('TELESCOPE_CLIENT_REQUEST_WATCHER', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Telescope Performance Restrictions
    |--------------------------------------------------------------------------
    |
    | Production-optimized settings to minimize performance impact
    |
    */

    'recording' => [
        'enabled' => env('TELESCOPE_RECORDING_ENABLED', env('APP_ENV') !== 'production'),
        'probability' => env('TELESCOPE_RECORDING_PROBABILITY', env('APP_ENV') === 'production' ? 0.01 : 1.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Retention
    |--------------------------------------------------------------------------
    |
    | Configure how long telescope data should be retained
    |
    */

    'prune' => [
        'hours' => env('TELESCOPE_PRUNE_HOURS', 48), // Shorter retention in production
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure telescope queue settings for background processing
    |
    */

    'queue' => [
        'connection' => env('TELESCOPE_QUEUE_CONNECTION', 'redis'),
        'queue' => env('TELESCOPE_QUEUE', 'telescope'),
        'batch_size' => env('TELESCOPE_QUEUE_BATCH_SIZE', 25),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Enhanced security settings for production environments
    |
    */

    'security' => [
        'ip_whitelist' => env('TELESCOPE_IP_WHITELIST') ? explode(',', env('TELESCOPE_IP_WHITELIST')) : [],
        'require_auth' => env('TELESCOPE_REQUIRE_AUTH', true),
        'allowed_roles' => env('TELESCOPE_ALLOWED_ROLES') ? explode(',', env('TELESCOPE_ALLOWED_ROLES')) : ['admin', 'developer'],
        'session_timeout' => env('TELESCOPE_SESSION_TIMEOUT', 3600), // 1 hour
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring
    |--------------------------------------------------------------------------
    |
    | Configure performance monitoring thresholds
    |
    */

    'performance' => [
        'slow_request_threshold' => env('TELESCOPE_SLOW_REQUEST_THRESHOLD', 1000), // ms
        'memory_limit_threshold' => env('TELESCOPE_MEMORY_LIMIT_THRESHOLD', 128), // MB
        'cpu_threshold' => env('TELESCOPE_CPU_THRESHOLD', 80), // percentage
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Configure database-specific telescope settings
    |
    */

    'database' => [
        'max_entries' => env('TELESCOPE_MAX_ENTRIES', 10000),
        'cleanup_batch_size' => env('TELESCOPE_CLEANUP_BATCH_SIZE', 1000),
        'index_optimization' => env('TELESCOPE_INDEX_OPTIMIZATION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring Integration
    |--------------------------------------------------------------------------
    |
    | Integration with external monitoring services
    |
    */

    'integrations' => [
        'sentry' => [
            'enabled' => env('TELESCOPE_SENTRY_INTEGRATION', true),
            'sync_exceptions' => env('TELESCOPE_SENTRY_SYNC_EXCEPTIONS', true),
        ],
        'prometheus' => [
            'enabled' => env('TELESCOPE_PROMETHEUS_INTEGRATION', false),
            'endpoint' => env('TELESCOPE_PROMETHEUS_ENDPOINT', '/metrics'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Alert Configuration
    |--------------------------------------------------------------------------
    |
    | Configure alerting thresholds and notifications
    |
    */

    'alerts' => [
        'error_threshold' => env('TELESCOPE_ERROR_THRESHOLD', 10), // errors per minute
        'slow_query_threshold' => env('TELESCOPE_SLOW_QUERY_ALERT', 2000), // ms
        'memory_threshold' => env('TELESCOPE_MEMORY_ALERT', 256), // MB
        'queue_threshold' => env('TELESCOPE_QUEUE_ALERT', 100), // jobs
    ],
];