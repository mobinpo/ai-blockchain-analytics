<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Http\Response;
use Carbon\Carbon;
use Exception;

final class VuePdfGenerationService
{
    private array $config;
    private string $browserlessUrl;
    private bool $useBrowserless;
    private string $appUrl;

    public function __construct()
    {
        $this->config = config('pdf_generation', []);
        $this->browserlessUrl = config('services.browserless.url', 'http://localhost:3000');
        $this->useBrowserless = config('services.browserless.enabled', false);
        $this->appUrl = config('app.url');
    }

    /**
     * Generate PDF from Vue component with authentication-aware URL
     */
    public function generateFromVueComponent(
        string $componentRoute,
        array $data = [],
        array $options = [],
        ?int $userId = null
    ): array {
        try {
            Log::info('Starting Vue component PDF generation', [
                'route' => $componentRoute,
                'method' => $this->useBrowserless ? 'browserless' : 'dompdf',
                'user_id' => $userId,
                'data_keys' => array_keys($data)
            ]);

            // Generate secure preview URL with token
            $previewUrl = $this->generateSecurePreviewUrl($componentRoute, $data, $userId);
            
            if ($this->useBrowserless && $this->isBrowserlessHealthy()) {
                return $this->generateWithBrowserless($previewUrl, $options);
            } else {
                // Fallback to DomPDF with server-side rendered Vue component
                return $this->generateWithDomPdfFallback($componentRoute, $data, $options);
            }

        } catch (Exception $e) {
            Log::error('Vue component PDF generation failed', [
                'route' => $componentRoute,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Generate PDF from sentiment analysis dashboard
     */
    public function generateSentimentDashboard(array $sentimentData, array $options = []): array
    {
        $defaultOptions = [
            'format' => 'A4',
            'orientation' => 'landscape', // Better for charts
            'title' => 'Sentiment Analysis Dashboard',
            'filename' => 'sentiment-dashboard-' . now()->format('Y-m-d-H-i-s') . '.pdf',
            'wait_for' => 'networkidle0',
            'timeout' => 45000, // Longer timeout for chart rendering
            'margin' => [
                'top' => '1.5cm',
                'bottom' => '2cm',
                'left' => '1cm',
                'right' => '1cm'
            ]
        ];

        $mergedOptions = array_merge($defaultOptions, $options);

        return $this->generateFromVueComponent(
            'sentiment-analysis.dashboard',
            $sentimentData,
            $mergedOptions
        );
    }

    /**
     * Generate PDF from sentiment vs price chart
     */
    public function generateSentimentPriceChart(array $chartData, array $options = []): array
    {
        $defaultOptions = [
            'format' => 'A4',
            'orientation' => 'landscape',
            'title' => 'Sentiment vs Price Analysis',
            'filename' => 'sentiment-price-chart-' . now()->format('Y-m-d-H-i-s') . '.pdf',
            'wait_for' => 'networkidle2', // Wait for chart animations
            'timeout' => 60000, // Extended timeout for complex charts
            'chart_rendering' => true,
            'print_background' => true
        ];

        $mergedOptions = array_merge($defaultOptions, $options);

        return $this->generateFromVueComponent(
            'charts.sentiment-price',
            $chartData,
            $mergedOptions
        );
    }

    /**
     * Batch generate multiple Vue component PDFs
     */
    public function batchGenerateVueComponents(array $components, array $globalOptions = []): array
    {
        $results = [];
        $startTime = microtime(true);

        foreach ($components as $index => $component) {
            try {
                $componentOptions = array_merge($globalOptions, $component['options'] ?? []);
                
                // Add batch identifier to filename
                if (!isset($componentOptions['filename'])) {
                    $componentOptions['filename'] = "batch-{$index}-" . 
                        ($component['name'] ?? 'component') . '-' . 
                        now()->format('Y-m-d-H-i-s') . '.pdf';
                }

                $result = $this->generateFromVueComponent(
                    $component['route'],
                    $component['data'] ?? [],
                    $componentOptions
                );

                $results[] = array_merge($result, [
                    'component_name' => $component['name'] ?? "Component {$index}",
                    'batch_index' => $index
                ]);

                // Add delay between generations to avoid rate limiting
                if ($index < count($components) - 1) {
                    usleep(500000); // 500ms delay
                }

            } catch (Exception $e) {
                $results[] = [
                    'success' => false,
                    'component_name' => $component['name'] ?? "Component {$index}",
                    'batch_index' => $index,
                    'error' => $e->getMessage()
                ];
            }
        }

        $totalTime = microtime(true) - $startTime;

        return [
            'batch_results' => $results,
            'total_components' => count($components),
            'successful' => count(array_filter($results, fn($r) => $r['success'] ?? false)),
            'failed' => count(array_filter($results, fn($r) => !($r['success'] ?? true))),
            'total_processing_time' => round($totalTime, 2)
        ];
    }

    /**
     * Generate secure preview URL with temporary access token
     */
    private function generateSecurePreviewUrl(string $route, array $data, ?int $userId): string
    {
        // Create temporary access token
        $token = $this->generatePreviewToken($route, $data, $userId);
        
        // Store data in cache with token as key (expires in 10 minutes)
        Cache::put("pdf_preview_{$token}", [
            'route' => $route,
            'data' => $data,
            'user_id' => $userId,
            'created_at' => now()->toISOString()
        ], 600);

        return "{$this->appUrl}/pdf-preview/{$token}";
    }

    /**
     * Generate preview token for secure PDF generation
     */
    private function generatePreviewToken(string $route, array $data, ?int $userId): string
    {
        return hash('sha256', implode('|', [
            $route,
            md5(json_encode($data)),
            $userId ?? 'guest',
            now()->timestamp,
            config('app.key')
        ]));
    }

    /**
     * Generate PDF using Browserless headless Chrome
     */
    private function generateWithBrowserless(string $url, array $options): array
    {
        $startTime = microtime(true);

        $payload = [
            'url' => $url,
            'options' => [
                'format' => $options['format'] ?? 'A4',
                'landscape' => ($options['orientation'] ?? 'portrait') === 'landscape',
                'printBackground' => $options['print_background'] ?? true,
                'margin' => $options['margin'] ?? [
                    'top' => '1cm', 
                    'bottom' => '1cm', 
                    'left' => '1cm', 
                    'right' => '1cm'
                ],
                'preferCSSPageSize' => true,
                'displayHeaderFooter' => $options['header_footer'] ?? false,
                'scale' => $options['scale'] ?? 1,
                'width' => $options['width'] ?? null,
                'height' => $options['height'] ?? null
            ],
            'waitFor' => $options['wait_for'] ?? 'networkidle0',
            'timeout' => $options['timeout'] ?? 30000,
            'gotoOptions' => [
                'waitUntil' => 'networkidle0'
            ]
        ];

        // Add custom headers if needed
        if (!empty($options['headers'])) {
            $payload['headers'] = $options['headers'];
        }

        // Special handling for chart rendering
        if ($options['chart_rendering'] ?? false) {
            $payload['waitFor'] = 'networkidle2';
            $payload['timeout'] = max($payload['timeout'], 45000);
            $payload['gotoOptions']['waitUntil'] = 'networkidle2';
        }

        try {
            $response = $this->callBrowserlessApi($payload);
            $processingTime = microtime(true) - $startTime;

            // Save the PDF
            $filename = $options['filename'] ?? 'vue-component-' . now()->timestamp . '.pdf';
            $filePath = 'pdfs/vue-components/' . $filename;
            Storage::disk('public')->put($filePath, $response);

            return [
                'success' => true,
                'method' => 'browserless',
                'file_path' => $filePath,
                'filename' => $filename,
                'url' => Storage::url($filePath),
                'download_url' => route('pdf.download', ['filename' => $filename]),
                'size' => strlen($response),
                'size_formatted' => $this->formatBytes(strlen($response)),
                'processing_time' => round($processingTime, 2),
                'options' => $options,
                'generated_at' => now()->toISOString()
            ];

        } catch (Exception $e) {
            Log::error('Browserless PDF generation failed', [
                'url' => $url,
                'error' => $e->getMessage(),
                'payload' => $payload
            ]);

            throw new Exception("Browserless PDF generation failed: {$e->getMessage()}");
        }
    }

    /**
     * Generate PDF using DomPDF as fallback (server-side rendering)
     */
    private function generateWithDomPdfFallback(string $route, array $data, array $options): array
    {
        $startTime = microtime(true);

        try {
            // Map Vue routes to Blade templates
            $bladeTemplate = $this->mapVueRouteToBladeTemplate($route);
            
            if (!$bladeTemplate) {
                throw new Exception("No Blade template found for Vue route: {$route}");
            }

            // Render Blade template with data
            $html = View::make($bladeTemplate, $data)->render();
            
            // Use existing DomPDF generation
            if (!class_exists('\Barryvdh\DomPDF\Facade\Pdf')) {
                throw new Exception('DomPDF not installed. Run: composer require barryvdh/laravel-dompdf');
            }

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            $pdf->setPaper($options['format'] ?? 'A4', $options['orientation'] ?? 'portrait');
            
            $pdfContent = $pdf->output();
            $processingTime = microtime(true) - $startTime;

            // Save the PDF
            $filename = $options['filename'] ?? 'vue-fallback-' . now()->timestamp . '.pdf';
            $filePath = 'pdfs/vue-fallback/' . $filename;
            Storage::disk('public')->put($filePath, $pdfContent);

            return [
                'success' => true,
                'method' => 'dompdf_fallback',
                'file_path' => $filePath,
                'filename' => $filename,
                'url' => Storage::url($filePath),
                'download_url' => route('pdf.download', ['filename' => $filename]),
                'size' => strlen($pdfContent),
                'size_formatted' => $this->formatBytes(strlen($pdfContent)),
                'processing_time' => round($processingTime, 2),
                'blade_template' => $bladeTemplate,
                'warning' => 'Generated using DomPDF fallback - charts may not render correctly',
                'generated_at' => now()->toISOString()
            ];

        } catch (Exception $e) {
            Log::error('DomPDF fallback generation failed', [
                'route' => $route,
                'error' => $e->getMessage()
            ]);

            throw new Exception("DomPDF fallback generation failed: {$e->getMessage()}");
        }
    }

    /**
     * Call Browserless API
     */
    private function callBrowserlessApi(array $payload): string
    {
        $response = Http::timeout($payload['timeout'] / 1000)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest'
            ])
            ->post("{$this->browserlessUrl}/pdf", $payload);

        if (!$response->successful()) {
            throw new Exception("Browserless API error: {$response->status()} - {$response->body()}");
        }

        return $response->body();
    }

    /**
     * Check if Browserless service is healthy
     */
    private function isBrowserlessHealthy(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->browserlessUrl}/health");
            return $response->successful();
        } catch (Exception $e) {
            Log::warning('Browserless health check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Map Vue routes to corresponding Blade templates
     */
    private function mapVueRouteToBladeTemplate(string $route): ?string
    {
        $mapping = [
            'sentiment-analysis.dashboard' => 'pdf.sentiment-dashboard',
            'charts.sentiment-price' => 'pdf.sentiment-price-chart',
            'reports.dashboard' => 'pdf.dashboard-report',
            'analytics.overview' => 'pdf.analytics-overview'
        ];

        return $mapping[$route] ?? null;
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Get generation statistics
     */
    public function getGenerationStats(string $period = '7d'): array
    {
        // This would query your database for generation statistics
        // For now, return mock data
        return [
            'period' => $period,
            'total_generated' => 45,
            'successful' => 43,
            'failed' => 2,
            'success_rate' => 95.6,
            'methods' => [
                'browserless' => 38,
                'dompdf_fallback' => 5,
                'failed' => 2
            ],
            'average_processing_time' => 3.2,
            'total_size_mb' => 12.7
        ];
    }
}