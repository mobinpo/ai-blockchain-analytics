<?php

return [
    /*
    |--------------------------------------------------------------------------
    | PDF Generation Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for PDF generation from Vue components and Blade templates
    |
    */

    // Default PDF options
    'defaults' => [
        'format' => 'A4',
        'orientation' => 'portrait',
        'margin' => [
            'top' => '1cm',
            'bottom' => '1cm',
            'left' => '1cm',
            'right' => '1cm'
        ],
        'print_background' => true,
        'prefer_css_page_size' => true,
        'timeout' => 30000,
        'wait_for' => 'networkidle0'
    ],

    // Storage settings
    'storage' => [
        'disk' => 'public',
        'path' => 'pdfs',
        'cleanup_days' => 7,
        'max_file_size' => '50MB'
    ],

    // Generation methods
    'methods' => [
        'browserless' => [
            'enabled' => env('PDF_BROWSERLESS_ENABLED', false),
            'url' => env('PDF_BROWSERLESS_URL', 'http://localhost:3000'),
            'timeout' => 60,
            'priority' => 1
        ],
        'dompdf' => [
            'enabled' => true,
            'timeout' => 30,
            'priority' => 2,
            'options' => [
                'enable_php' => false,
                'enable_javascript' => false,
                'enable_remote' => true,
                'paper_size' => 'A4',
                'paper_orientation' => 'portrait'
            ]
        ],
        'puppeteer_local' => [
            'enabled' => false,
            'binary_path' => env('PUPPETEER_BINARY_PATH'),
            'timeout' => 45,
            'priority' => 3
        ]
    ],

    // Component routing
    'components' => [
        'dashboard' => [
            'component' => 'DashboardReport',
            'template' => 'reports.dashboard',
            'prefer_method' => 'browserless'
        ],
        'sentiment' => [
            'component' => 'SentimentReport',
            'template' => 'reports.sentiment',
            'prefer_method' => 'browserless'
        ],
        'crawler' => [
            'component' => 'CrawlerReport',
            'template' => 'reports.crawler',
            'prefer_method' => 'browserless'
        ]
    ],

    // Security settings
    'security' => [
        'token_expiry_minutes' => 30,
        'allowed_domains' => [
            env('APP_URL', 'http://localhost')
        ],
        'rate_limit' => [
            'max_attempts' => 10,
            'decay_minutes' => 60
        ]
    ],

    // Performance settings
    'performance' => [
        'queue_enabled' => env('PDF_QUEUE_ENABLED', false),
        'queue_connection' => env('PDF_QUEUE_CONNECTION', 'default'),
        'cache_enabled' => true,
        'cache_ttl' => 3600, // 1 hour
        'max_concurrent' => 3
    ],

    // Monitoring and logging
    'monitoring' => [
        'log_generation' => true,
        'log_errors' => true,
        'track_performance' => true,
        'alert_on_failure' => false,
        'metrics_enabled' => true
    ]
];