<?php

return [
    /*
    |--------------------------------------------------------------------------
    | PDF Generation Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for PDF generation using DomPDF and Browserless
    |
    */

    'default_engine' => env('PDF_ENGINE', 'dompdf'), // dompdf or browserless

    /*
    |--------------------------------------------------------------------------
    | Browserless Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for browserless PDF generation service
    | Excellent for Vue.js components, charts, and complex layouts
    |
    */
    'browserless' => [
        'url' => env('BROWSERLESS_URL', 'http://localhost:3000'),
        'token' => env('BROWSERLESS_TOKEN'),
        'timeout' => 120, // seconds
        'default_options' => [
            'format' => 'A4',
            'landscape' => false,
            'margin' => [
                'top' => '10mm',
                'right' => '10mm', 
                'bottom' => '10mm',
                'left' => '10mm'
            ],
            'printBackground' => true,
            'preferCSSPageSize' => true,
            'displayHeaderFooter' => false,
            'waitUntil' => 'networkidle2',
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | DomPDF Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for DomPDF engine
    | Good for server-side rendered content and simple layouts
    |
    */
    'dompdf' => [
        'paper_size' => 'A4',
        'orientation' => 'portrait',
        'options' => [
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'isFontSubsettingEnabled' => true,
            'defaultFont' => 'Arial',
            'dpi' => 96,
            'debugPng' => false,
            'debugKeepTemp' => false,
            'debugCss' => false,
            'debugLayout' => false,
            'debugLayoutLines' => false,
            'debugLayoutBlocks' => false,
            'debugLayoutInline' => false,
            'debugLayoutPaddingBox' => false,
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    */
    'storage' => [
        'disk' => 'public',
        'path' => 'pdfs',
        'cleanup_after' => 7, // days
    ],

    /*
    |--------------------------------------------------------------------------
    | Common Templates and Options
    |--------------------------------------------------------------------------
    */
    'templates' => [
        'report' => [
            'header_template' => '<div style="font-size: 10px; text-align: center; width: 100%;">Report Generated on <span class="date"></span></div>',
            'footer_template' => '<div style="font-size: 10px; text-align: center; width: 100%;"><span class="pageNumber"></span> of <span class="totalPages"></span></div>',
        ],
        'chart' => [
            'wait_for_charts' => true,
            'chart_selectors' => ['.chart-container', 'canvas', '.recharts-wrapper'],
            'additional_wait' => 2000, // ms
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    */
    'security' => [
        'allowed_domains' => [
            'localhost',
            '127.0.0.1',
            env('APP_URL'),
        ],
        'max_file_size' => 50 * 1024 * 1024, // 50MB
        'allowed_extensions' => ['pdf'],
    ]
];