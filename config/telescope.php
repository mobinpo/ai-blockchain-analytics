<?php

use Laravel\Telescope\Http\Middleware\Authorize;
use Laravel\Telescope\Watchers;

return [

    /*
    |--------------------------------------------------------------------------
    | Telescope Master Switch
    |--------------------------------------------------------------------------
    |
    | This option may be used to disable all Telescope watchers regardless
    | of their individual configuration, which simply provides a single
    | and convenient way to enable or disable Telescope data storage.
    |
    */

    'enabled' => env('TELESCOPE_ENABLED', env('APP_ENV') !== 'production'),

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

    'path' => env('TELESCOPE_PATH', 'telescope'),

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
            'connection' => env('DB_CONNECTION', 'mysql'),
            'chunk' => 1000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Telescope Queue
    |--------------------------------------------------------------------------
    |
    | This configuration options determines the queue connection and queue
    | which will be used to process ProcessPendingUpdate jobs. This can
    | be changed if you would prefer to use a non-default connection.
    |
    */

    'queue' => [
        'connection' => env('TELESCOPE_QUEUE_CONNECTION', null),
        'queue' => env('TELESCOPE_QUEUE', null),
        'delay' => env('TELESCOPE_QUEUE_DELAY', 10),
    ],

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
        \App\Http\Middleware\EnhancedTelescopeAuthorize::class,
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
        // 'api/*'
    ],

    'ignore_paths' => [
        'livewire*',
        'nova-api*',
        'pulse*',
    ],

    'ignore_commands' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Telescope Watchers
    |--------------------------------------------------------------------------
    |
    | The following array lists the "watchers" that will be registered with
    | Telescope. The watchers gather the application's profile data when
    | a request or task is executed. Feel free to customize this list.
    |
    */

    'watchers' => [
        Watchers\BatchWatcher::class => env('TELESCOPE_BATCH_WATCHER', true),

        Watchers\CacheWatcher::class => [
            'enabled' => env('TELESCOPE_CACHE_WATCHER', env('APP_ENV') !== 'production'),
            'hidden' => [],
            'ignore' => [
                'config*',
                'route*',
                'view*',
                'telescope*',
            ],
        ],

        Watchers\ClientRequestWatcher::class => env('TELESCOPE_CLIENT_REQUEST_WATCHER', env('APP_ENV') !== 'production'),

        Watchers\CommandWatcher::class => [
            'enabled' => env('TELESCOPE_COMMAND_WATCHER', true),
            'ignore' => [
                'schedule:run',
                'horizon:work',
                'queue:work',
                'telescope:prune',
            ],
        ],

        Watchers\DumpWatcher::class => [
            'enabled' => env('TELESCOPE_DUMP_WATCHER', env('APP_ENV') !== 'production'),
            'always' => env('TELESCOPE_DUMP_WATCHER_ALWAYS', false),
        ],

        Watchers\EventWatcher::class => [
            'enabled' => env('TELESCOPE_EVENT_WATCHER', env('APP_ENV') !== 'production'),
            'ignore' => [
                'Illuminate\Log\Events\MessageLogged',
                'Illuminate\Cache\Events\*',
                'Illuminate\Session\Events\*',
            ],
        ],

        Watchers\ExceptionWatcher::class => env('TELESCOPE_EXCEPTION_WATCHER', true),

        Watchers\GateWatcher::class => [
            'enabled' => env('TELESCOPE_GATE_WATCHER', env('APP_ENV') !== 'production'),
            'ignore_abilities' => [],
            'ignore_packages' => true,
            'ignore_paths' => [],
        ],

        Watchers\JobWatcher::class => env('TELESCOPE_JOB_WATCHER', true),

        Watchers\LogWatcher::class => [
            'enabled' => env('TELESCOPE_LOG_WATCHER', true),
            'level' => env('APP_ENV') === 'production' ? 'warning' : 'debug',
        ],

        Watchers\MailWatcher::class => env('TELESCOPE_MAIL_WATCHER', true),

        Watchers\ModelWatcher::class => [
            'enabled' => env('TELESCOPE_MODEL_WATCHER', env('APP_ENV') !== 'production'),
            'events' => ['eloquent.*'],
            'hydrations' => env('APP_ENV') !== 'production',
        ],

        Watchers\NotificationWatcher::class => env('TELESCOPE_NOTIFICATION_WATCHER', true),

        Watchers\QueryWatcher::class => [
            'enabled' => env('TELESCOPE_QUERY_WATCHER', env('APP_ENV') !== 'production'),
            'ignore_packages' => true,
            'ignore_paths' => [],
            'slow' => 100,
        ],

        Watchers\RedisWatcher::class => env('TELESCOPE_REDIS_WATCHER', true),

        Watchers\RequestWatcher::class => [
            'enabled' => env('TELESCOPE_REQUEST_WATCHER', true),
            'size_limit' => env('TELESCOPE_RESPONSE_SIZE_LIMIT', 64),
            'ignore_http_methods' => [],
            'ignore_status_codes' => [],
        ],

        Watchers\ScheduleWatcher::class => env('TELESCOPE_SCHEDULE_WATCHER', true),
        Watchers\ViewWatcher::class => env('TELESCOPE_VIEW_WATCHER', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Blockchain Analytics - Custom Telescope Configuration v0.9.0
    |--------------------------------------------------------------------------
    |
    | Enhanced configuration for production environment with security and
    | performance optimizations for blockchain analytics platform.
    |
    */

    // Production-specific configuration
    'production_enabled' => env('TELESCOPE_PRODUCTION_ENABLED', false),
    
    // Admin email addresses allowed to access Telescope in production
    'admin_emails' => [
        'admin@ai-blockchain-analytics.com',
        // Add additional admin emails here
    ],
    
    // Allowed IP addresses for production access
    'allowed_ips' => [
        '127.0.0.1',
        '::1',
        // Add your office/VPN IPs here
    ],
    
    // Data retention settings (in days)
    'retention' => [
        'exceptions' => env('TELESCOPE_RETENTION_EXCEPTIONS', 7),
        'jobs' => env('TELESCOPE_RETENTION_JOBS', 3),
        'logs' => env('TELESCOPE_RETENTION_LOGS', 7),
        'requests' => env('TELESCOPE_RETENTION_REQUESTS', 1),
        'queries' => env('TELESCOPE_RETENTION_QUERIES', 1),
        'cache' => env('TELESCOPE_RETENTION_CACHE', 1),
    ],
    
    // Performance settings
    'performance' => [
        'max_entries_per_type' => env('TELESCOPE_MAX_ENTRIES', 1000),
        'prune_frequency' => env('TELESCOPE_PRUNE_FREQUENCY', 'daily'),
        'batch_size' => env('TELESCOPE_BATCH_SIZE', 500),
    ],
    
    // Security settings
    'security' => [
        'ip_whitelist_enabled' => env('TELESCOPE_IP_WHITELIST', true),
        'require_authentication' => env('TELESCOPE_REQUIRE_AUTH', true),
        'log_access_attempts' => env('TELESCOPE_LOG_ACCESS', true),
        'max_failed_attempts' => env('TELESCOPE_MAX_FAILED_ATTEMPTS', 3),
    ],

    /*
    */

    'ai_blockchain' => [
        // Production access control
        'production_enabled' => env('TELESCOPE_PRODUCTION_ENABLED', false),

        // Production restrictions
        'production_restrictions' => [
            'allowed_ips' => array_filter(explode(',', env('TELESCOPE_ALLOWED_IPS', ''))),
            'allowed_users' => array_filter(explode(',', env('TELESCOPE_ALLOWED_USERS', ''))),
            'required_permission' => env('TELESCOPE_REQUIRED_PERMISSION'),
            'auto_disable_hours' => env('TELESCOPE_AUTO_DISABLE_HOURS', 24),
        ],

        // Performance settings
        'performance' => [
            'sampling_rate' => env('TELESCOPE_SAMPLING_RATE', 0.1), // 10% in production
            'memory_limit' => env('TELESCOPE_MEMORY_LIMIT', '512M'),
            'time_limit' => env('TELESCOPE_TIME_LIMIT', 30),
        ],

        // Data retention
        'retention' => [
            'hours' => env('TELESCOPE_RETENTION_HOURS', 24),
            'limit' => env('TELESCOPE_RETENTION_LIMIT', 1000),
        ],

        // Security settings
        'security' => [
            'hide_sensitive_data' => env('TELESCOPE_HIDE_SENSITIVE', true),
            'mask_request_data' => env('TELESCOPE_MASK_REQUEST_DATA', true),
            'log_access_attempts' => env('TELESCOPE_LOG_ACCESS', true),
        ],
    ],
];
