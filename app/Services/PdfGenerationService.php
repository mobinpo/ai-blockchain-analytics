<?php

declare(strict_types=1);

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use GuzzleHttp\Client;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Comprehensive PDF Generation Service
 * 
 * Supports both server-side rendering with DomPDF and 
 * client-side Vue component rendering with Browserless
 */
final class PdfGenerationService
{
    private readonly Client $httpClient;
    
    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Generate PDF from Blade template and save to storage (for compatibility)
     */
    public function generateFromBladeTemplate(
        string $template, 
        array $data = [], 
        array $options = []
    ): array {
        try {
            $pdfContent = $this->generateFromBlade($template, $data, $options);
            
            // Save to storage if filename is provided
            if (isset($options['filename'])) {
                $filename = $options['filename'];
                $filePath = $this->savePdf($pdfContent, $filename);
                
                return [
                    'success' => true,
                    'file_path' => $filePath,
                    'content' => $pdfContent,
                    'size' => strlen($pdfContent)
                ];
            }
            
            // Return content only if no filename specified
            return [
                'success' => true,
                'content' => $pdfContent,
                'size' => strlen($pdfContent)
            ];
        } catch (\Exception $e) {
            Log::error('PDF generation failed', [
                'template' => $template,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate PDF from Blade template (server-side rendering)
     */
    public function generateFromBlade(
        string $template, 
        array $data = [], 
        array $options = []
    ): string {
        $defaultOptions = [
            'format' => 'A4',
            'orientation' => 'portrait',
            'margin' => [
                'top' => 20,
                'right' => 15,
                'bottom' => 20,
                'left' => 15,
            ],
        ];
        
        $mergedOptions = array_merge($defaultOptions, $options);
        
        try {
            $pdf = Pdf::loadView($template, $data)
                ->setPaper($mergedOptions['format'], $mergedOptions['orientation'])
                ->setOptions([
                    'margin_top' => $mergedOptions['margin']['top'],
                    'margin_right' => $mergedOptions['margin']['right'],
                    'margin_bottom' => $mergedOptions['margin']['bottom'],
                    'margin_left' => $mergedOptions['margin']['left'],
                    'enable_php' => true,
                    'enable_javascript' => false,
                    'enable_css_float' => true,
                    'enable_html5_parser' => true,
                ]);
            
            return $pdf->output();
        } catch (\Exception $e) {
            Log::error('PDF generation from Blade failed', [
                'template' => $template,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Generate PDF from Vue component using Browserless
     */
    public function generateFromVue(
        string $component,
        array $props = [],
        array $options = []
    ): string {
        $browserlessUrl = config('services.browserless.url', 'https://chrome.browserless.io');
        $browserlessToken = config('services.browserless.token');
        
        if (!$browserlessToken) {
            throw new \Exception('Browserless token not configured');
        }
        
        // Generate temporary URL for Vue component
        $tempRoute = $this->createTempVueRoute($component, $props);
        
        try {
            $response = $this->httpClient->post("{$browserlessUrl}/pdf", [
                'json' => [
                    'url' => $tempRoute,
                    'options' => array_merge([
                        'format' => 'A4',
                        'printBackground' => true,
                        'margin' => [
                            'top' => '20px',
                            'right' => '15px',
                            'bottom' => '20px',
                            'left' => '15px',
                        ],
                        'displayHeaderFooter' => false,
                        'waitUntil' => 'networkidle2',
                        'timeout' => 30000,
                    ], $options),
                ],
                'headers' => [
                    'Authorization' => "Bearer {$browserlessToken}",
                ],
            ]);
            
            return $response->getBody()->getContents();
        } catch (\Exception $e) {
            Log::error('PDF generation from Vue failed', [
                'component' => $component,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            // Clean up temporary route
            $this->cleanupTempRoute($tempRoute);
        }
    }

    /**
     * Generate analytics report PDF
     */
    public function generateAnalyticsReport(
        array $contractData,
        array $analysisResults,
        string $format = 'detailed'
    ): string {
        $template = match ($format) {
            'summary' => 'pdf.analytics.summary',
            'detailed' => 'pdf.analytics.detailed',
            'executive' => 'pdf.analytics.executive',
            default => 'pdf.analytics.detailed',
        };
        
        $data = [
            'contract' => $contractData,
            'analysis' => $analysisResults,
            'generated_at' => now(),
            'format' => $format,
            'metadata' => [
                'title' => 'Smart Contract Analysis Report',
                'subtitle' => $contractData['name'] ?? 'Unknown Contract',
                'version' => '1.0',
                'confidentiality' => 'Internal Use',
            ],
        ];
        
        return $this->generateFromBlade($template, $data, [
            'orientation' => 'portrait',
            'format' => 'A4',
        ]);
    }

    /**
     * Generate dashboard report PDF
     */
    public function generateDashboardReport(array $data, array $options = []): array
    {
        try {
            $template = 'reports.dashboard';
            $pdfData = [
                'dashboard_data' => $data,
                'generated_at' => now(),
                'title' => 'Dashboard Report',
                'metadata' => [
                    'type' => 'dashboard',
                    'version' => '1.0'
                ]
            ];

            $startTime = microtime(true);
            $pdfContent = $this->generateFromBlade($template, $pdfData, $options);
            $processingTime = microtime(true) - $startTime;
            
            if (isset($options['filename'])) {
                $filePath = $this->savePdf($pdfContent, $options['filename']);
                return [
                    'success' => true,
                    'file_path' => $filePath,
                    'content' => $pdfContent,
                    'size' => strlen($pdfContent),
                    'method' => $options['force_browserless'] ?? false ? 'browserless' : 'blade',
                    'filename' => $options['filename'],
                    'url' => Storage::url($filePath),
                    'processing_time' => $processingTime
                ];
            }
            
            return [
                'success' => true,
                'content' => $pdfContent,
                'size' => strlen($pdfContent),
                'method' => $options['force_browserless'] ?? false ? 'browserless' : 'blade',
                'processing_time' => $processingTime
            ];
        } catch (\Exception $e) {
            Log::error('Dashboard report PDF generation failed', [
                'error' => $e->getMessage(),
                'options' => $options
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'method' => $options['force_browserless'] ?? false ? 'browserless' : 'blade'
            ];
        }
    }

    /**
     * Generate sentiment report PDF
     */
    public function generateSentimentReport(array $data, array $options = []): array
    {
        try {
            $template = 'reports.sentiment';
            $pdfData = [
                'sentiment_data' => $data,
                'generated_at' => now(),
                'title' => 'Sentiment Analysis Report',
                'metadata' => [
                    'type' => 'sentiment',
                    'version' => '1.0'
                ]
            ];

            $startTime = microtime(true);
            $pdfContent = $this->generateFromBlade($template, $pdfData, $options);
            $processingTime = microtime(true) - $startTime;
            
            if (isset($options['filename'])) {
                $filePath = $this->savePdf($pdfContent, $options['filename']);
                return [
                    'success' => true,
                    'file_path' => $filePath,
                    'content' => $pdfContent,
                    'size' => strlen($pdfContent),
                    'method' => $options['force_browserless'] ?? false ? 'browserless' : 'blade',
                    'filename' => $options['filename'],
                    'url' => Storage::url($filePath),
                    'processing_time' => $processingTime
                ];
            }
            
            return [
                'success' => true,
                'content' => $pdfContent,
                'size' => strlen($pdfContent),
                'method' => $options['force_browserless'] ?? false ? 'browserless' : 'blade',
                'processing_time' => $processingTime
            ];
        } catch (\Exception $e) {
            Log::error('Sentiment report PDF generation failed', [
                'error' => $e->getMessage(),
                'options' => $options
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'method' => $options['force_browserless'] ?? false ? 'browserless' : 'blade'
            ];
        }
    }

    /**
     * Generate sentiment dashboard PDF
     */
    public function generateSentimentDashboard(
        array $sentimentData,
        array $priceData,
        string $timeframe = '7d'
    ): string {
        // Use Vue component for complex charts
        if (config('services.browserless.enabled', false)) {
            return $this->generateFromVue('SentimentDashboard', [
                'sentimentData' => $sentimentData,
                'priceData' => $priceData,
                'timeframe' => $timeframe,
                'exportMode' => true,
            ], [
                'format' => 'A4',
                'landscape' => true,
                'margin' => [
                    'top' => '10px',
                    'right' => '10px',
                    'bottom' => '10px',
                    'left' => '10px',
                ],
            ]);
        }
        
        // Fallback to Blade template
        return $this->generateFromBlade('pdf.sentiment.dashboard', [
            'sentiment_data' => $sentimentData,
            'price_data' => $priceData,
            'timeframe' => $timeframe,
            'generated_at' => now(),
        ], [
            'orientation' => 'landscape',
            'format' => 'A4',
        ]);
    }

    /**
     * Generate crawler report PDF
     */
    public function generateCrawlerReport(array $data, array $options = []): array
    {
        try {
            // Support both old and new parameter formats
            if (isset($data['stats']) && isset($data['posts'])) {
                // New format: all data in $data array
                $template = 'reports.crawler';
                $pdfData = [
                    'crawler_data' => $data,
                    'generated_at' => now(),
                    'title' => 'Crawler Report',
                    'metadata' => [
                        'type' => 'crawler',
                        'version' => '1.0'
                    ]
                ];
            } else {
                // Legacy format support
                $template = 'pdf.crawler.report';
                $pdfData = [
                    'stats' => $data,
                    'posts' => $options['posts'] ?? [],
                    'period' => $options['period'] ?? 'weekly',
                    'generated_at' => now(),
                ];
            }

            $startTime = microtime(true);
            $pdfContent = $this->generateFromBlade($template, $pdfData, $options);
            $processingTime = microtime(true) - $startTime;
            
            if (isset($options['filename'])) {
                $filePath = $this->savePdf($pdfContent, $options['filename']);
                return [
                    'success' => true,
                    'file_path' => $filePath,
                    'content' => $pdfContent,
                    'size' => strlen($pdfContent),
                    'method' => $options['force_browserless'] ?? false ? 'browserless' : 'blade',
                    'filename' => $options['filename'],
                    'url' => Storage::url($filePath),
                    'processing_time' => $processingTime
                ];
            }
            
            return [
                'success' => true,
                'content' => $pdfContent,
                'size' => strlen($pdfContent),
                'method' => $options['force_browserless'] ?? false ? 'browserless' : 'blade',
                'processing_time' => $processingTime
            ];
        } catch (\Exception $e) {
            Log::error('Crawler report PDF generation failed', [
                'error' => $e->getMessage(),
                'options' => $options
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'method' => $options['force_browserless'] ?? false ? 'browserless' : 'blade'
            ];
        }
    }

    /**
     * Generate verification badge PDF certificate
     */
    public function generateVerificationCertificate(
        array $badgeData,
        array $verificationDetails
    ): string {
        return $this->generateFromBlade('pdf.verification.certificate', [
            'badge' => $badgeData,
            'verification' => $verificationDetails,
            'generated_at' => now(),
            'certificate_id' => Str::uuid(),
        ], [
            'format' => 'A4',
            'orientation' => 'portrait',
            'margin' => [
                'top' => 10,
                'right' => 10,
                'bottom' => 10,
                'left' => 10,
            ],
        ]);
    }

    /**
     * Generate multi-format report (multiple templates in one PDF)
     */
    public function generateMultiFormatReport(array $sections): string
    {
        $htmlContent = '';
        
        foreach ($sections as $section) {
            $sectionHtml = view($section['template'], $section['data'])->render();
            $htmlContent .= '<div class="pdf-section">' . $sectionHtml . '</div>';
            
            // Add page break between sections if specified
            if ($section['page_break'] ?? true) {
                $htmlContent .= '<div style="page-break-after: always;"></div>';
            }
        }
        
        $pdf = Pdf::loadHTML($htmlContent)
            ->setPaper('A4', 'portrait')
            ->setOptions([
                'enable_css_float' => true,
                'enable_html5_parser' => true,
                'margin_top' => 20,
                'margin_right' => 15,
                'margin_bottom' => 20,
                'margin_left' => 15,
            ]);
        
        return $pdf->output();
    }

    /**
     * Save PDF to storage and return path
     */
    public function savePdf(string $pdfContent, string $filename = null): string
    {
        $filename = $filename ?: 'report_' . now()->format('Y-m-d_H-i-s') . '.pdf';
        $path = 'pdfs/' . $filename;
        
        Storage::disk('public')->put($path, $pdfContent);
        
        return $path;
    }

    /**
     * Stream PDF as download response
     */
    public function streamPdf(string $pdfContent, string $filename = null): Response
    {
        $filename = $filename ?: 'report_' . now()->format('Y-m-d_H-i-s') . '.pdf';
        
        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Content-Length' => strlen($pdfContent),
        ]);
    }

    /**
     * Generate PDF with watermark
     */
    public function addWatermark(string $pdfContent, string $watermarkText): string
    {
        // For advanced watermarking, you might want to use a more sophisticated library
        // This is a simple implementation using CSS overlay
        $watermarkHtml = view('pdf.components.watermark', [
            'text' => $watermarkText,
        ])->render();
        
        // Re-generate with watermark overlay
        $pdf = Pdf::loadHTML($watermarkHtml . $pdfContent)
            ->setOptions(['enable_css_float' => true]);
        
        return $pdf->output();
    }

    /**
     * Batch generate multiple PDFs
     */
    public function batchGenerate(array $requests): array
    {
        $results = [];
        
        foreach ($requests as $key => $request) {
            try {
                $pdfContent = match ($request['type']) {
                    'blade' => $this->generateFromBlade(
                        $request['template'],
                        $request['data'] ?? [],
                        $request['options'] ?? []
                    ),
                    'vue' => $this->generateFromVue(
                        $request['component'],
                        $request['props'] ?? [],
                        $request['options'] ?? []
                    ),
                    'analytics' => $this->generateAnalyticsReport(
                        $request['contract_data'],
                        $request['analysis_results'],
                        $request['format'] ?? 'detailed'
                    ),
                    default => throw new \InvalidArgumentException("Unknown PDF type: {$request['type']}"),
                };
                
                $path = $this->savePdf($pdfContent, $request['filename'] ?? null);
                
                $results[$key] = [
                    'success' => true,
                    'path' => $path,
                    'size' => strlen($pdfContent),
                ];
            } catch (\Exception $e) {
                $results[$key] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
                
                Log::error('Batch PDF generation failed', [
                    'key' => $key,
                    'request' => $request,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        return $results;
    }

    /**
     * Create temporary route for Vue component rendering
     */
    private function createTempVueRoute(string $component, array $props): string
    {
        $token = Str::random(32);
        $url = route('pdf.temp-vue', ['token' => $token]);
        
        // Store component data temporarily (you might want to use Redis for this)
        cache()->put("pdf_vue_{$token}", [
            'component' => $component,
            'props' => $props,
        ], now()->addMinutes(10));
        
        return $url;
    }

    /**
     * Clean up temporary route data
     */
    private function cleanupTempRoute(string $url): void
    {
        $token = basename(parse_url($url, PHP_URL_PATH));
        cache()->forget("pdf_vue_{$token}");
    }

    /**
     * Get PDF generation statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_generated' => cache()->get('pdf_stats_total', 0),
            'generated_today' => cache()->get('pdf_stats_today_' . now()->format('Y-m-d'), 0),
            'average_size' => cache()->get('pdf_stats_avg_size', 0),
            'popular_templates' => cache()->get('pdf_stats_templates', []),
            'success_rate' => cache()->get('pdf_stats_success_rate', 100),
        ];
    }

    /**
     * Update PDF generation statistics
     */
    public function updateStatistics(string $template, int $size, bool $success): void
    {
        // Update total count
        cache()->increment('pdf_stats_total');
        
        // Update daily count
        $todayKey = 'pdf_stats_today_' . now()->format('Y-m-d');
        cache()->increment($todayKey);
        cache()->put($todayKey, cache()->get($todayKey, 0), now()->endOfDay());
        
        // Update average size
        $currentAvg = cache()->get('pdf_stats_avg_size', 0);
        $total = cache()->get('pdf_stats_total', 1);
        $newAvg = (($currentAvg * ($total - 1)) + $size) / $total;
        cache()->put('pdf_stats_avg_size', round($newAvg, 2));
        
        // Update template popularity
        $templates = cache()->get('pdf_stats_templates', []);
        $templates[$template] = ($templates[$template] ?? 0) + 1;
        cache()->put('pdf_stats_templates', $templates);
        
        // Update success rate
        if (!$success) {
            $failures = cache()->increment('pdf_stats_failures');
            $successRate = (($total - $failures) / $total) * 100;
            cache()->put('pdf_stats_success_rate', round($successRate, 2));
        }
    }

    /**
     * Optimize PDF size (compress images, remove metadata)
     */
    public function optimizePdf(string $pdfContent): string
    {
        // Basic optimization - remove metadata and compress
        // For advanced optimization, consider using external tools like Ghostscript
        
        $pdf = Pdf::loadHTML(base64_encode($pdfContent))
            ->setOptions([
                'compress' => true,
                'strip_metadata' => true,
                'image_dpi' => 150, // Reduce image quality slightly
            ]);
        
        return $pdf->output();
    }
}