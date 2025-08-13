<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Carbon\Carbon;

class PdfGeneratorService
{
    private string $browserlessUrl;
    private ?string $browserlessToken;
    private string $appUrl;

    public function __construct()
    {
        $this->browserlessUrl = config('services.browserless.url', 'https://chrome.browserless.io');
        $this->browserlessToken = config('services.browserless.token');
        $this->appUrl = config('app.url');
    }

    /**
     * Generate PDF from Vue view using Browserless
     */
    public function generateFromVueView(string $route, array $data = [], array $options = []): string
    {
        $url = $this->buildViewUrl($route, $data);
        
        Log::info('Generating PDF from Vue view', [
            'route' => $route,
            'url' => $url,
            'method' => 'browserless'
        ]);

        return $this->generateWithBrowserless($url, $options);
    }

    /**
     * Generate PDF using Browserless (Chrome headless)
     */
    public function generateWithBrowserless(string $url, array $options = []): string
    {
        $defaultOptions = [
            'pdf' => [
                'format' => 'A4',
                'printBackground' => true,
                'margin' => [
                    'top' => '1cm',
                    'right' => '1cm',
                    'bottom' => '1cm',
                    'left' => '1cm'
                ]
            ],
            'viewport' => [
                'width' => 1920,
                'height' => 1080
            ],
            'timeout' => 30000,
            'waitFor' => 'networkidle2'
        ];

        $pdfOptions = array_merge_recursive($defaultOptions, $options);
        
        $payload = [
            'url' => $url,
            'options' => $pdfOptions['pdf'],
            'viewport' => $pdfOptions['viewport'],
            'timeout' => $pdfOptions['timeout'],
            'waitFor' => $pdfOptions['waitFor']
        ];

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->browserlessToken,
                    'Content-Type' => 'application/json'
                ])
                ->post($this->browserlessUrl . '/pdf', $payload);

            if ($response->failed()) {
                throw new \Exception('Browserless API failed: ' . $response->body());
            }

            $filename = $this->generateFilename('browserless');
            Storage::disk('public')->put("pdfs/{$filename}", $response->body());
            
            Log::info('PDF generated successfully with Browserless', [
                'filename' => $filename,
                'size' => strlen($response->body())
            ]);

            return $filename;

        } catch (\Exception $e) {
            Log::error('Browserless PDF generation failed', [
                'error' => $e->getMessage(),
                'url' => $url
            ]);
            
            // Fallback to DomPDF
            return $this->generateWithDomPDF($url, $options);
        }
    }

    /**
     * Generate PDF using DomPDF (fallback method)
     */
    public function generateWithDomPDF(string $url, array $options = []): string
    {
        Log::info('Generating PDF with DomPDF', ['url' => $url]);

        try {
            // Fetch the rendered HTML from the Vue view
            $html = $this->fetchRenderedHtml($url);
            
            $pdf = Pdf::loadHTML($html);
            
            // Apply options
            if (isset($options['pdf']['format'])) {
                $pdf->setPaper($options['pdf']['format']);
            }
            
            if (isset($options['pdf']['orientation'])) {
                $pdf->setPaper($options['pdf']['format'] ?? 'A4', $options['pdf']['orientation']);
            }

            $filename = $this->generateFilename('dompdf');
            $pdfContent = $pdf->output();
            
            Storage::disk('public')->put("pdfs/{$filename}", $pdfContent);
            
            Log::info('PDF generated successfully with DomPDF', [
                'filename' => $filename,
                'size' => strlen($pdfContent)
            ]);

            return $filename;

        } catch (\Exception $e) {
            Log::error('DomPDF generation failed', [
                'error' => $e->getMessage(),
                'url' => $url
            ]);
            throw $e;
        }
    }

    /**
     * Generate PDF from HTML content directly
     */
    public function generateFromHtml(string $html, array $options = []): string
    {
        try {
            $pdf = Pdf::loadHTML($html);
            
            if (isset($options['format'])) {
                $pdf->setPaper($options['format']);
            }
            
            if (isset($options['orientation'])) {
                $pdf->setPaper($options['format'] ?? 'A4', $options['orientation']);
            }

            $filename = $this->generateFilename('html');
            $pdfContent = $pdf->output();
            
            Storage::disk('public')->put("pdfs/{$filename}", $pdfContent);
            
            return $filename;

        } catch (\Exception $e) {
            Log::error('HTML PDF generation failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Generate analytics report PDF
     */
    public function generateAnalyticsReport(array $data, string $type = 'sentiment'): string
    {
        $template = match($type) {
            'sentiment' => 'pdf.sentiment-report',
            'price' => 'pdf.price-report',
            'social' => 'pdf.social-report',
            default => 'pdf.analytics-report'
        };

        // Use Blade template directly for now since Vue routes might not be configured
        $html = view($template, $data)->render();
        
        return $this->generateFromHtml($html, [
            'format' => 'A4',
            'orientation' => 'portrait'
        ]);
    }

    /**
     * Generate social media report PDF
     */
    public function generateSocialReport(array $posts, array $analytics): string
    {
        return $this->generateFromVueView('pdf.social-report', [
            'posts' => $posts,
            'analytics' => $analytics,
            'generated_at' => Carbon::now()->toISOString()
        ]);
    }

    /**
     * Generate blockchain analysis PDF
     */
    public function generateBlockchainReport(array $transactions, array $analysis): string
    {
        return $this->generateFromVueView('pdf.blockchain-report', [
            'transactions' => $transactions,
            'analysis' => $analysis,
            'generated_at' => Carbon::now()->toISOString()
        ], [
            'pdf' => [
                'format' => 'A4',
                'orientation' => 'landscape',
                'printBackground' => true
            ]
        ]);
    }

    /**
     * Get PDF file URL
     */
    public function getPdfUrl(string $filename): string
    {
        return Storage::disk('public')->url("pdfs/{$filename}");
    }

    /**
     * Download PDF file
     */
    public function downloadPdf(string $filename): Response
    {
        $path = storage_path("app/public/pdfs/{$filename}");
        
        if (!file_exists($path)) {
            abort(404, 'PDF file not found');
        }

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"'
        ]);
    }

    /**
     * Clean up old PDF files
     */
    public function cleanupOldPdfs(int $daysOld = 7): int
    {
        $files = Storage::disk('public')->files('pdfs');
        $deleted = 0;
        $cutoff = Carbon::now()->subDays($daysOld);

        foreach ($files as $file) {
            $lastModified = Carbon::createFromTimestamp(
                Storage::disk('public')->lastModified($file)
            );

            if ($lastModified->isBefore($cutoff)) {
                Storage::disk('public')->delete($file);
                $deleted++;
            }
        }

        Log::info("Cleaned up {$deleted} old PDF files");
        return $deleted;
    }

    /**
     * Build URL for Vue view
     */
    private function buildViewUrl(string $route, array $data = []): string
    {
        $url = $this->appUrl . '/' . ltrim($route, '/');
        
        if (!empty($data)) {
            $queryString = http_build_query(['pdf_data' => base64_encode(json_encode($data))]);
            $url .= '?' . $queryString;
        }

        return $url;
    }

    /**
     * Fetch rendered HTML from Vue view
     */
    private function fetchRenderedHtml(string $url): string
    {
        try {
            $response = Http::timeout(30)->get($url, [
                'pdf_mode' => 'true'
            ]);

            if ($response->failed()) {
                throw new \Exception('Failed to fetch HTML from: ' . $url);
            }

            return $this->optimizeHtmlForPdf($response->body());

        } catch (\Exception $e) {
            Log::error('Failed to fetch HTML', ['url' => $url, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Optimize HTML for PDF generation
     */
    private function optimizeHtmlForPdf(string $html): string
    {
        // Remove scripts and optimize for PDF
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $html);
        
        // Add PDF-specific styles
        $pdfStyles = '
            <style>
                body { font-family: "DejaVu Sans", sans-serif; }
                .page-break { page-break-before: always; }
                .no-print { display: none !important; }
                @media print {
                    .print-only { display: block !important; }
                    .no-print { display: none !important; }
                }
            </style>
        ';
        
        $html = str_replace('</head>', $pdfStyles . '</head>', $html);
        
        return $html;
    }

    /**
     * Generate unique filename
     */
    private function generateFilename(string $prefix = 'pdf'): string
    {
        return $prefix . '_' . Carbon::now()->format('Y-m-d_H-i-s') . '_' . uniqid() . '.pdf';
    }

    /**
     * Check if Browserless is available
     */
    public function isBrowserlessAvailable(): bool
    {
        if (empty($this->browserlessToken)) {
            return false;
        }

        try {
            $response = Http::timeout(5)
                ->withHeaders(['Authorization' => 'Bearer ' . $this->browserlessToken])
                ->get($this->browserlessUrl . '/');
            
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get service status
     */
    public function getStatus(): array
    {
        return [
            'browserless_available' => $this->isBrowserlessAvailable(),
            'dompdf_available' => class_exists('Barryvdh\DomPDF\Facade\Pdf'),
            'storage_writable' => Storage::disk('public')->exists('pdfs') || Storage::disk('public')->makeDirectory('pdfs'),
            'browserless_url' => $this->browserlessUrl,
            'has_token' => !empty($this->browserlessToken)
        ];
    }
}