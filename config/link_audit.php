<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Link Audit Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Link Auditor system that crawls your Laravel
    | application to find broken links and suggests fixes.
    |
    */

    'base_url' => env('LINK_AUDIT_BASE_URL', 'http://localhost:8000'),

    'login' => [
        'email' => env('LINK_AUDIT_LOGIN_EMAIL'),
        'password' => env('LINK_AUDIT_LOGIN_PASSWORD'),
        'login_url' => '/login',
        'redirect_after_login' => '/dashboard',
    ],

    'crawl' => [
        'max_depth' => env('LINK_AUDIT_MAX_DEPTH', 2),
        'timeout_seconds' => env('LINK_AUDIT_TIMEOUT', 30),
        'wait_after_click' => env('LINK_AUDIT_WAIT_MS', 2000), // milliseconds
    ],

    'exclusions' => [
        'route_patterns' => [
            'api/*',
            'horizon/*',
            'sanctum/*',
            'stripe/*',
            'webhooks/*',
            'mailgun/*',
            'telescope/*',
            '_ignition/*',
        ],
        'paths' => [
            '/logout',
            '/admin/delete/*',
            '/admin/destroy/*',
        ],
        'external_domains' => true, // Skip external links
        'routes_with_params' => true, // Skip routes with {parameters}
        'non_get_methods' => true, // Skip POST/PUT/DELETE routes
    ],

    'suggestions' => [
        'minimum_score' => 0.82,
        'weights' => [
            'name_similarity' => 0.6,
            'uri_similarity' => 0.3,
            'link_text_similarity' => 0.1,
        ],
    ],

    'storage' => [
        'base_path' => storage_path('app/link-audit'),
        'routes_file' => 'routes.json',
        'static_findings_file' => 'static-findings.json',
        'browser_findings_dir' => 'browser-findings',
        'screenshots_dir' => 'screenshots',
        'backups_dir' => 'backups',
        'report_file' => 'report.html',
    ],

    'redirects' => [
        'auto_write' => false,
        'confidence_threshold' => 0.9,
        'config_file' => config_path('redirects.php'),
    ],

    'patterns' => [
        'problematic_hrefs' => [
            '#',
            'javascript:void(0)',
            'javascript:;',
            '',
        ],
        'link_selectors' => [
            'a[href]',
            'button[data-href]',
            '[role="link"]',
            'a[href^="/"]',
            'a[href^="http"]',
        ],
        'ignore_hrefs' => [
            'mailto:',
            'tel:',
            'sms:',
            'ftp:',
            '#',
        ],
    ],
];