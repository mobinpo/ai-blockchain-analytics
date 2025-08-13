<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enhanced PDF Generation Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file contains settings for the enhanced PDF generation
    | system that supports both Browserless (high-quality) and DomPDF (fallback)
    | rendering methods.
    |
    */

    'default_method' => env('PDF_DEFAULT_METHOD', 'browserless'),

    /*
    |--------------------------------------------------------------------------
    | Browserless Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the Browserless service which provides high-quality PDF
    | generation using headless Chrome.
    |
    */

    'browserless' => [
        'enabled' => env('BROWSERLESS_ENABLED', false),
        'url' => env('BROWSERLESS_URL', 'http://localhost:3000'),
        'timeout' => env('BROWSERLESS_TIMEOUT', 30), // seconds
        'health_check_interval' => env('BROWSERLESS_HEALTH_CHECK_INTERVAL', 300), // seconds
        'max_retries' => env('BROWSERLESS_MAX_RETRIES', 2),
        
        // Default options for Browserless PDF generation
        'default_options' => [
            'format' => 'A4',
            'orientation' => 'portrait',
            'margin' => [
                'top' => '1cm',
                'right' => '1cm',
                'bottom' => '1cm',
                'left' => '1cm'
            ],
            'scale' => 1.0,
            'print_background' => true,
            'prefer_css_page_size' => true,
            'wait_until' => 'networkidle0',
            'quality' => 'high'
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | DomPDF Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for DomPDF which serves as a fallback when Browserless is
    | unavailable or for simpler PDF generation needs.
    |
    */

    'dompdf' => [
        'enabled' => env('DOMPDF_ENABLED', true),
        'options' => [
            'dpi' => 96,
            'default_font' => 'Arial',
            'enable_font_subsetting' => true,
            'enable_php' => false,
            'enable_javascript' => false,
            'enable_remote' => false,
            'enable_css_float' => true,
            'enable_html5_parser' => true
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for PDF file storage and management.
    |
    */

    'storage' => [
        'disk' => env('PDF_STORAGE_DISK', 'public'),
        'path' => env('PDF_STORAGE_PATH', 'pdfs'),
        'subdirectories' => [
            'browserless' => 'browserless',
            'dompdf' => 'dompdf',
            'basic_dompdf' => 'basic-dompdf'
        ],
        'cleanup' => [
            'enabled' => env('PDF_CLEANUP_ENABLED', true),
            'max_age_days' => env('PDF_CLEANUP_MAX_AGE_DAYS', 30),
            'schedule' => env('PDF_CLEANUP_SCHEDULE', 'daily')
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Security settings for PDF generation including preview URLs and
    | access control.
    |
    */

    'security' => [
        'preview_token_lifetime' => env('PDF_PREVIEW_TOKEN_LIFETIME', 10), // minutes
        'max_file_size' => env('PDF_MAX_FILE_SIZE', 50 * 1024 * 1024), // 50MB
        'allowed_routes' => [
            'sentiment-timeline-demo',
            'dashboard',
            'north-star-demo',
            'sentiment-analysis'
        ],
        'allowed_components' => [
            'EnhancedSentimentPriceTimeline',
            'SentimentPriceChart',
            'DashboardReport',
            'NorthStarDashboard'
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | Settings to optimize PDF generation performance and resource usage.
    |
    */

    'performance' => [
        'concurrent_jobs' => env('PDF_CONCURRENT_JOBS', 3),
        'queue_connection' => env('PDF_QUEUE_CONNECTION', 'default'),
        'queue_name' => env('PDF_QUEUE_NAME', 'pdf-generation'),
        'memory_limit' => env('PDF_MEMORY_LIMIT', '512M'),
        'execution_timeout' => env('PDF_EXECUTION_TIMEOUT', 120), // seconds
        
        // Caching
        'cache_enabled' => env('PDF_CACHE_ENABLED', true),
        'cache_ttl' => env('PDF_CACHE_TTL', 3600), // seconds
        'cache_prefix' => env('PDF_CACHE_PREFIX', 'enhanced_pdf')
    ],

    /*
    |--------------------------------------------------------------------------
    | Vue Route to Blade Template Mapping
    |--------------------------------------------------------------------------
    |
    | Maps Vue routes to corresponding Blade templates for DomPDF fallback
    | rendering when Browserless is unavailable.
    |
    */

    'route_template_mapping' => [
        'sentiment-timeline-demo' => 'pdf.sentiment-price-timeline',
        'dashboard' => 'pdf.dashboard-report',
        'north-star-demo' => 'pdf.north-star-dashboard',
        'sentiment-analysis' => 'pdf.sentiment-analysis',
        
        // Component mappings
        'pdf/component/EnhancedSentimentPriceTimeline' => 'pdf.sentiment-price-timeline',
        'pdf/component/SentimentPriceChart' => 'pdf.sentiment-price-chart',
        'pdf/component/DashboardReport' => 'reports.dashboard',
        'pdf/component/NorthStarDashboard' => 'reports.north-star'
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Options
    |--------------------------------------------------------------------------
    |
    | Default options applied to all PDF generation requests unless
    | overridden by specific parameters.
    |
    */

    'default_options' => [
        'format' => 'A4',
        'orientation' => 'portrait',
        'quality' => 'high',
        'margin' => [
            'top' => '1cm',
            'right' => '1cm',
            'bottom' => '1cm',
            'left' => '1cm'
        ],
        'wait_time' => 2000, // milliseconds
        'wait_for_selector' => null,
        'filename_template' => '{type}-{date}-{time}.pdf'
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for PDF generation logging and monitoring.
    |
    */

    'logging' => [
        'enabled' => env('PDF_LOGGING_ENABLED', true),
        'level' => env('PDF_LOG_LEVEL', 'info'),
        'channel' => env('PDF_LOG_CHANNEL', 'stack'),
        'include_performance_metrics' => env('PDF_LOG_PERFORMANCE', true),
        'include_file_details' => env('PDF_LOG_FILE_DETAILS', true)
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring and Health Checks
    |--------------------------------------------------------------------------
    |
    | Configuration for monitoring PDF generation service health and
    | performance metrics.
    |
    */

    'monitoring' => [
        'enabled' => env('PDF_MONITORING_ENABLED', true),
        'health_check_endpoints' => [
            'browserless' => '/health',
            'storage' => null // Will check storage disk availability
        ],
        'metrics' => [
            'track_generation_time' => true,
            'track_file_sizes' => true,
            'track_success_rate' => true,
            'track_method_usage' => true
        ],
        'alerts' => [
            'enabled' => env('PDF_ALERTS_ENABLED', false),
            'failure_threshold' => env('PDF_ALERT_FAILURE_THRESHOLD', 0.8),
            'notification_channels' => env('PDF_ALERT_CHANNELS', 'mail,slack')
        ]
    ]
];