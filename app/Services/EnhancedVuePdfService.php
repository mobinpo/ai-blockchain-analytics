<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;

/**
 * Enhanced Vue PDF Generation Service
 * 
 * Supports both Browserless (high-quality) and DomPDF (fallback) rendering
 * Optimized for Vue components with SSR fallbacks
 */
final class EnhancedVuePdfService
{
    private array $config;
    private string $browserlessUrl;
    private bool $useBrowserless;
    private string $appUrl;
    private int $timeoutSeconds;
    private array $defaultOptions;

    public function __construct()
    {
        $this->config = config('pdf_generation', []);
        $this->browserlessUrl = config('services.browserless.url', 'http://localhost:3000');
        $this->useBrowserless = config('services.browserless.enabled', false);
        $this->appUrl = config('app.url');
        $this->timeoutSeconds = config('services.browserless.timeout', 30);
        
        $this->defaultOptions = [
            'format' => 'A4',
            'orientation' => 'portrait',
            'margin' => [
                'top' => '1cm',
                'right' => '1cm',
                'bottom' => '1cm',
                'left' => '1cm'
            ],
            'wait_for_selector' => null,
            'wait_time' => 2000, // ms
            'scale' => 1.0,
            'quality' => 'high'
        ];
    }

    /**
     * Generate PDF from Vue component route
     */
    public function generateFromVueRoute(
        string $route,
        array $data = [],
        array $options = [],
        ?int $userId = null
    ): array {
        $startTime = microtime(true);
        
        try {
            Log::info('Enhanced Vue PDF generation started', [
                'route' => $route,
                'method' => $this->useBrowserless ? 'browserless' : 'dompdf',
                'user_id' => $userId,
                'data_size' => count($data)
            ]);

            $options = array_merge($this->defaultOptions, $options);
            
            // Generate secure URL for the Vue component
            $componentUrl = $this->generateSecureComponentUrl($route, $data, $userId);
            
            // Try Browserless first if enabled and healthy
            if ($this->useBrowserless && $this->isBrowserlessHealthy()) {
                $result = $this->generateWithBrowserless($componentUrl, $options);
                if ($result['success']) {
                    return $result;
                }
                
                Log::warning('Browserless generation failed, falling back to DomPDF');
            }
            
            // Fallback to DomPDF with server-side rendering
            return $this->generateWithDomPdfFallback($route, $data, $options);
            
        } catch (Exception $e) {
            Log::error('Enhanced Vue PDF generation failed', [
                'route' => $route,
                'error' => $e->getMessage(),
                'execution_time' => round((microtime(true) - $startTime) * 1000, 2) . 'ms'
            ]);
            
            throw $e;
        }
    }

    /**
     * Generate PDF from Vue component with data embedding
     */
    public function generateFromVueComponent(
        string $componentName,
        array $props = [],
        array $options = []
    ): array {
        try {
            Log::info('Generating PDF from Vue component', [
                'component' => $componentName,
                'props_count' => count($props)
            ]);

            // Create a temporary route for the component
            $tempRoute = $this->createTempComponentRoute($componentName, $props);
            
            return $this->generateFromVueRoute($tempRoute, $props, $options);
            
        } catch (Exception $e) {
            Log::error('Vue component PDF generation failed', [
                'component' => $componentName,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Generate PDF with Browserless (high-quality)
     */
    private function generateWithBrowserless(string $url, array $options): array
    {
        $startTime = microtime(true);
        
        try {
            Log::info('Generating PDF with Browserless', [
                'url' => $url,
                'browserless_url' => $this->browserlessUrl
            ]);

            $browserlessPayload = [
                'url' => $url,
                'options' => [
                    'format' => $options['format'],
                    'landscape' => $options['orientation'] === 'landscape',
                    'margin' => $options['margin'],
                    'scale' => $options['scale'],
                    'printBackground' => true,
                    'preferCSSPageSize' => true,
                    'waitUntil' => 'networkidle0'
                ]
            ];

            // Add wait conditions if specified
            if ($options['wait_for_selector']) {
                $browserlessPayload['options']['waitForSelector'] = $options['wait_for_selector'];
            }

            if ($options['wait_time']) {
                $browserlessPayload['options']['waitForTimeout'] = $options['wait_time'];
            }

            $response = Http::timeout($this->timeoutSeconds)
                ->post($this->browserlessUrl . '/pdf', $browserlessPayload);

            if (!$response->successful()) {
                throw new Exception('Browserless API error: ' . $response->body());
            }

            $pdfContent = $response->body();
            $processingTime = microtime(true) - $startTime;

            // Save the PDF
            $filename = $options['filename'] ?? 'vue-browserless-' . now()->timestamp . '.pdf';
            $filePath = 'pdfs/browserless/' . $filename;
            Storage::disk('public')->put($filePath, $pdfContent);

            return [
                'success' => true,
                'method' => 'browserless',
                'file_path' => $filePath,
                'filename' => $filename,
                'url' => Storage::url($filePath),
                'download_url' => route('pdf.download', ['filename' => $filename]),
                'size' => strlen($pdfContent),
                'size_formatted' => $this->formatBytes(strlen($pdfContent)),
                'processing_time' => round($processingTime, 2),
                'quality' => 'high',
                'generated_at' => now()->toISOString(),
                'source_url' => $url,
                'options' => $options
            ];

        } catch (Exception $e) {
            Log::error('Browserless PDF generation failed', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'method' => 'browserless',
                'error' => $e->getMessage(),
                'processing_time' => round((microtime(true) - $startTime), 2)
            ];
        }
    }

    /**
     * Generate PDF with DomPDF fallback
     */
    private function generateWithDomPdfFallback(string $route, array $data, array $options): array
    {
        $startTime = microtime(true);
        
        try {
            Log::info('Generating PDF with DomPDF fallback', [
                'route' => $route
            ]);

            // Map Vue route to Blade template
            $bladeTemplate = $this->mapVueRouteToBladeTemplate($route);
            
            if (!$bladeTemplate) {
                throw new Exception("No Blade template mapping found for route: {$route}");
            }

            // Render Blade template with data
            $html = View::make($bladeTemplate, array_merge($data, [
                'pdf_mode' => true,
                'options' => $options
            ]))->render();

            // Generate PDF with DomPDF
            if (!class_exists('\Barryvdh\DomPDF\Facade\Pdf')) {
                return $this->generateWithBasicDomPdf($html, $options);
            }

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            $pdf->setPaper($options['format'], $options['orientation']);
            
            // Apply additional options
            if (isset($options['dpi'])) {
                $pdf->setOptions(['dpi' => $options['dpi']]);
            }

            $pdfContent = $pdf->output();
            $processingTime = microtime(true) - $startTime;

            // Save the PDF
            $filename = $options['filename'] ?? 'vue-dompdf-' . now()->timestamp . '.pdf';
            $filePath = 'pdfs/dompdf/' . $filename;
            Storage::disk('public')->put($filePath, $pdfContent);

            return [
                'success' => true,
                'method' => 'dompdf',
                'file_path' => $filePath,
                'filename' => $filename,
                'url' => Storage::url($filePath),
                'download_url' => route('pdf.download', ['filename' => $filename]),
                'size' => strlen($pdfContent),
                'size_formatted' => $this->formatBytes(strlen($pdfContent)),
                'processing_time' => round($processingTime, 2),
                'quality' => 'standard',
                'blade_template' => $bladeTemplate,
                'generated_at' => now()->toISOString(),
                'warning' => 'Generated using DomPDF - complex charts may not render perfectly',
                'options' => $options
            ];

        } catch (Exception $e) {
            Log::error('DomPDF fallback generation failed', [
                'route' => $route,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Generate with basic DomPDF (no Laravel wrapper)
     */
    private function generateWithBasicDomPdf(string $html, array $options): array
    {
        if (!class_exists('\Dompdf\Dompdf')) {
            throw new Exception('DomPDF not installed. Run: composer require dompdf/dompdf');
        }

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper($options['format'], $options['orientation']);
        $dompdf->render();

        $pdfContent = $dompdf->output();
        
        // Save the PDF
        $filename = $options['filename'] ?? 'vue-basic-dompdf-' . now()->timestamp . '.pdf';
        $filePath = 'pdfs/basic-dompdf/' . $filename;
        Storage::disk('public')->put($filePath, $pdfContent);

        return [
            'success' => true,
            'method' => 'basic_dompdf',
            'file_path' => $filePath,
            'filename' => $filename,
            'url' => Storage::url($filePath),
            'size' => strlen($pdfContent),
            'size_formatted' => $this->formatBytes(strlen($pdfContent))
        ];
    }

    /**
     * Generate secure component URL with temporary access
     */
    private function generateSecureComponentUrl(string $route, array $data, ?int $userId): string
    {
        // Create a temporary signed URL with embedded data
        $token = Str::random(32);
        $expiresAt = now()->addMinutes(10);
        
        // Cache the data with the token
        Cache::put("pdf_data:{$token}", [
            'route' => $route,
            'data' => $data,
            'user_id' => $userId,
            'expires_at' => $expiresAt
        ], $expiresAt);

        // Generate signed URL
        return URL::temporarySignedRoute('pdf.preview', $expiresAt, [
            'route' => $route,
            'token' => $token
        ]);
    }

    /**
     * Create temporary component route
     */
    private function createTempComponentRoute(string $componentName, array $props): string
    {
        // This would create a temporary route that renders the specific component
        // For now, we'll use a generic component renderer route
        return "pdf/component/{$componentName}";
    }

    /**
     * Map Vue routes to Blade templates
     */
    private function mapVueRouteToBladeTemplate(string $route): ?string
    {
        $mappings = [
            'sentiment-timeline-demo' => 'pdf.sentiment-price-chart',
            'pdf/component/SentimentPriceChart' => 'pdf.sentiment-price-chart',
            'pdf/component/EnhancedSentimentPriceTimeline' => 'pdf.sentiment-price-timeline',
            'dashboard' => 'pdf.dashboard-report',
            'sentiment-analysis' => 'pdf.sentiment-analysis',
            'north-star-demo' => 'pdf.north-star-dashboard',
            'pdf/component/DashboardReport' => 'reports.dashboard',
            'pdf/component/NorthStarDashboard' => 'reports.north-star',
        ];

        return $mappings[$route] ?? null;
    }

    /**
     * Check if Browserless service is healthy
     */
    private function isBrowserlessHealthy(): bool
    {
        if (!$this->useBrowserless) {
            return false;
        }

        $cacheKey = 'browserless_health_check';
        
        return Cache::remember($cacheKey, 300, function () {
            try {
                $response = Http::timeout(5)->get($this->browserlessUrl . '/health');
                return $response->successful();
            } catch (Exception $e) {
                Log::warning('Browserless health check failed', [
                    'url' => $this->browserlessUrl,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        });
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $size, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }

    /**
     * Get service status and configuration
     */
    public function getServiceStatus(): array
    {
        return [
            'browserless' => [
                'enabled' => $this->useBrowserless,
                'url' => $this->browserlessUrl,
                'healthy' => $this->isBrowserlessHealthy(),
                'timeout' => $this->timeoutSeconds
            ],
            'dompdf' => [
                'available' => class_exists('\Barryvdh\DomPDF\Facade\Pdf') || class_exists('\Dompdf\Dompdf'),
                'laravel_wrapper' => class_exists('\Barryvdh\DomPDF\Facade\Pdf'),
                'basic_dompdf' => class_exists('\Dompdf\Dompdf')
            ],
            'storage' => [
                'disk' => 'public',
                'base_path' => 'pdfs/',
                'writable' => Storage::disk('public')->exists('.')
            ],
            'default_options' => $this->defaultOptions
        ];
    }

    /**
     * Clean up old PDF files
     */
    public function cleanupOldFiles(int $daysOld = 7): array
    {
        $cutoffDate = now()->subDays($daysOld);
        $directories = ['pdfs/browserless', 'pdfs/dompdf', 'pdfs/basic-dompdf'];
        $deletedFiles = [];
        $totalSize = 0;

        foreach ($directories as $directory) {
            $files = Storage::disk('public')->allFiles($directory);
            
            foreach ($files as $file) {
                $lastModified = Storage::disk('public')->lastModified($file);
                
                if ($lastModified < $cutoffDate->timestamp) {
                    $size = Storage::disk('public')->size($file);
                    $totalSize += $size;
                    
                    Storage::disk('public')->delete($file);
                    $deletedFiles[] = [
                        'file' => $file,
                        'size' => $size,
                        'last_modified' => Carbon::createFromTimestamp($lastModified)->toISOString()
                    ];
                }
            }
        }

        Log::info('PDF cleanup completed', [
            'files_deleted' => count($deletedFiles),
            'total_size_freed' => $this->formatBytes($totalSize),
            'cutoff_date' => $cutoffDate->toISOString()
        ]);

        return [
            'success' => true,
            'files_deleted' => count($deletedFiles),
            'total_size_freed' => $totalSize,
            'size_freed_formatted' => $this->formatBytes($totalSize),
            'deleted_files' => $deletedFiles
        ];
    }
}