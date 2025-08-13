<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\EnhancedVuePdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Exception;

/**
 * Enhanced PDF Controller
 * 
 * Handles Vue component to PDF conversion using Browserless and DomPDF
 */
final class EnhancedPdfController extends Controller
{
    public function __construct(
        private readonly EnhancedVuePdfService $pdfService
    ) {}

    /**
     * Generate preview token for Vue route (for browser preview)
     */
    public function generatePreviewToken(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'route' => 'required|string|max:200',
                'data' => 'sometimes|array'
            ]);

            $route = $validated['route'];
            $data = $validated['data'] ?? [];
            $userId = auth()->id();
            
            // Generate token and cache data
            $token = \Illuminate\Support\Str::random(32);
            $expiresAt = now()->addMinutes(10);
            
            Cache::put("pdf_data:{$token}", [
                'route' => $route,
                'data' => $data,
                'user_id' => $userId,
                'expires_at' => $expiresAt
            ], $expiresAt);

            // Generate preview URL
            $previewUrl = route('enhanced-pdf.preview', [
                'route' => $route,
                'token' => $token
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Preview token generated successfully',
                'data' => [
                    'token' => $token,
                    'preview_url' => $previewUrl,
                    'expires_at' => $expiresAt->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('PDF preview token generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate preview token',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate PDF from Vue route
     */
    public function generateFromRoute(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'route' => 'required|string|max:200',
                'data' => 'sometimes|array',
                'options' => 'sometimes|array',
                'filename' => 'sometimes|string|max:100',
                'format' => 'sometimes|string|in:A4,A3,Letter,Legal',
                'orientation' => 'sometimes|string|in:portrait,landscape',
                'quality' => 'sometimes|string|in:high,standard,low'
            ]);

            $route = $validated['route'];
            $data = $validated['data'] ?? [];
            $options = $validated['options'] ?? [];
            
            // Apply request-level options
            if (isset($validated['filename'])) {
                $options['filename'] = $validated['filename'];
            }
            if (isset($validated['format'])) {
                $options['format'] = $validated['format'];
            }
            if (isset($validated['orientation'])) {
                $options['orientation'] = $validated['orientation'];
            }
            if (isset($validated['quality'])) {
                $options['quality'] = $validated['quality'];
            }

            $userId = auth()->id();
            $result = $this->pdfService->generateFromVueRoute($route, $data, $options, $userId);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'PDF generated successfully',
                    'data' => $result
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'PDF generation failed',
                    'error' => $result['error'] ?? 'Unknown error'
                ], 500);
            }

        } catch (Exception $e) {
            Log::error('PDF generation request failed', [
                'route' => $request->input('route'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'PDF generation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate PDF from Vue component
     */
    public function generateFromComponent(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'component' => 'required|string|max:100',
                'props' => 'sometimes|array',
                'options' => 'sometimes|array',
                'filename' => 'sometimes|string|max:100'
            ]);

            $component = $validated['component'];
            $props = $validated['props'] ?? [];
            $options = $validated['options'] ?? [];
            
            if (isset($validated['filename'])) {
                $options['filename'] = $validated['filename'];
            }

            $result = $this->pdfService->generateFromVueComponent($component, $props, $options);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'PDF generated from component successfully',
                    'data' => $result
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Component PDF generation failed',
                    'error' => $result['error'] ?? 'Unknown error'
                ], 500);
            }

        } catch (Exception $e) {
            Log::error('Component PDF generation failed', [
                'component' => $request->input('component'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Component PDF generation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate PDF from sentiment price timeline
     */
    public function generateSentimentTimelinePdf(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'coin' => 'required|string|max:50',
                'days' => 'integer|min:1|max:365',
                'include_volume' => 'boolean',
                'filename' => 'sometimes|string|max:100',
                'format' => 'sometimes|string|in:A4,A3,Letter',
                'orientation' => 'sometimes|string|in:portrait,landscape'
            ]);

            $data = [
                'coin' => $validated['coin'],
                'days' => $validated['days'] ?? 30,
                'include_volume' => $validated['include_volume'] ?? false,
                'pdf_mode' => true
            ];

            $options = [
                'filename' => $validated['filename'] ?? "sentiment-timeline-{$validated['coin']}-" . now()->format('Y-m-d') . '.pdf',
                'format' => $validated['format'] ?? 'A4',
                'orientation' => $validated['orientation'] ?? 'landscape',
                'wait_for_selector' => '.sentiment-price-timeline canvas',
                'wait_time' => 3000,
                'title' => "Sentiment vs Price Timeline - {$validated['coin']}"
            ];

            $result = $this->pdfService->generateFromVueRoute('sentiment-timeline-demo', $data, $options, auth()->id());

            return response()->json([
                'success' => true,
                'message' => 'Sentiment timeline PDF generated successfully',
                'data' => $result
            ]);

        } catch (Exception $e) {
            Log::error('Sentiment timeline PDF generation failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Sentiment timeline PDF generation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate dashboard PDF
     */
    public function generateDashboardPdf(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'dashboard_data' => 'required|array',
                'title' => 'sometimes|string|max:200',
                'filename' => 'sometimes|string|max:100',
                'include_charts' => 'boolean',
                'format' => 'sometimes|string|in:A4,A3,Letter'
            ]);

            $data = array_merge($validated['dashboard_data'], [
                'pdf_mode' => true,
                'title' => $validated['title'] ?? 'Dashboard Report',
                'include_charts' => $validated['include_charts'] ?? true,
                'generated_at' => now()->toISOString()
            ]);

            $options = [
                'filename' => $validated['filename'] ?? 'dashboard-report-' . now()->format('Y-m-d-H-i') . '.pdf',
                'format' => $validated['format'] ?? 'A4',
                'orientation' => 'portrait',
                'wait_for_selector' => '.dashboard-content',
                'wait_time' => 2000,
                'title' => $data['title']
            ];

            $result = $this->pdfService->generateFromVueRoute('dashboard', $data, $options, auth()->id());

            return response()->json([
                'success' => true,
                'message' => 'Dashboard PDF generated successfully',
                'data' => $result
            ]);

        } catch (Exception $e) {
            Log::error('Dashboard PDF generation failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Dashboard PDF generation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PDF preview route (for Browserless to access)
     */
    public function preview(Request $request)
    {
        try {
            $route = $request->input('route');
            $token = $request->input('token');
            
            // If accessed without parameters (direct browser access), provide a demo view
            if (!$route || !$token) {
                // Check if user is authenticated for direct access
                if (!auth()->check()) {
                    Log::info('PDF preview accessed without authentication and parameters', [
                        'route' => $route,
                        'token' => $token,
                        'ip' => $request->ip(),
                        'user_agent' => $request->userAgent()
                    ]);
                    
                    return response()->json([
                        'error' => 'Authentication required',
                        'message' => 'Please authenticate to preview PDFs directly',
                    ], 401);
                }
                
                // Provide demo data for authenticated direct access
                Log::info('PDF preview accessed directly by authenticated user', [
                    'user_id' => auth()->id(),
                    'ip' => $request->ip()
                ]);
                
                return $this->renderDemoPreview();
            }

            // Retrieve cached data
            $cacheKey = "pdf_data:{$token}";
            $cachedData = Cache::get($cacheKey);
            
            if (!$cachedData) {
                Log::warning('PDF preview data not found in cache', [
                    'token' => $token,
                    'cache_key' => $cacheKey
                ]);
                
                return response()->view('errors.404', [
                    'message' => 'Preview data not found or expired'
                ], 404);
            }

            // Check if data has expired
            if (now()->isAfter($cachedData['expires_at'])) {
                Cache::forget($cacheKey);
                
                Log::info('PDF preview data expired', [
                    'token' => $token,
                    'expired_at' => $cachedData['expires_at']
                ]);
                
                return response()->view('errors.404', [
                    'message' => 'Preview data expired'
                ], 404);
            }

            $data = array_merge($cachedData['data'], [
                'pdf_mode' => true,
                'preview_mode' => true
            ]);

            // Map route to Vue component
            $componentMap = [
                'sentiment-timeline-demo' => 'Demo/SentimentPriceTimelineDemo',
                'dashboard' => 'Dashboard',
                'north-star-demo' => 'Demo/NorthStarDashboard'
            ];

            $component = $componentMap[$route] ?? $route;

            return Inertia::render($component, $data);

        } catch (Exception $e) {
            Log::error('PDF preview failed', [
                'route' => $request->input('route'),
                'error' => $e->getMessage()
            ]);

            abort(500, 'Preview generation failed');
        }
    }

    /**
     * Render demo preview for direct access
     */
    private function renderDemoPreview()
    {
        $demoData = [
            'data' => [
                'title' => 'AI Blockchain Analytics - Demo Report',
                'generated_at' => now()->toISOString(),
                'date_range' => [
                    'start' => now()->subDays(30)->toISOString(),
                    'end' => now()->toISOString()
                ],
                'metrics' => [
                    'total_posts' => 1247,
                    'sentiment_score' => 0.847, // 84.7% positive
                    'engagement' => 89540,
                    'platforms' => [
                        'Twitter' => 650,
                        'Reddit' => 342,
                        'Telegram' => 155,
                        'Discord' => 100
                    ]
                ],
                'charts' => [
                    'sentiment_trend' => [
                        ['date' => '2025-01-10', 'sentiment' => 0.82],
                        ['date' => '2025-01-11', 'sentiment' => 0.85],
                        ['date' => '2025-01-12', 'sentiment' => 0.79],
                        ['date' => '2025-01-13', 'sentiment' => 0.87],
                        ['date' => '2025-01-14', 'sentiment' => 0.84]
                    ],
                    'volume_trend' => [
                        ['date' => '2025-01-10', 'volume' => 245],
                        ['date' => '2025-01-11', 'volume' => 289],
                        ['date' => '2025-01-12', 'volume' => 267],
                        ['date' => '2025-01-13', 'volume' => 312],
                        ['date' => '2025-01-14', 'volume' => 298]
                    ]
                ],
                'insights' => [
                    'sentiment_growth' => 0.157, // 15.7% improvement
                    'platform_diversity' => true,
                    'volatility_high' => false
                ]
            ],
            'pdf_mode' => false,
            'demo_mode' => true
        ];

        return Inertia::render('Pdf/DashboardReport', $demoData);
    }

    /**
     * Download PDF file
     */
    public function download(string $filename): BinaryFileResponse|Response
    {
        try {
            // Validate filename to prevent invalid requests
            if (strlen($filename) < 2 || preg_match('/^[0-9]+$/', $filename)) {
                Log::warning('Invalid PDF filename requested', [
                    'filename' => $filename,
                    'user_id' => auth()->id(),
                    'ip' => request()->ip(),
                    'controller' => 'EnhancedPdfController'
                ]);
                
                abort(404, "Invalid PDF filename: '{$filename}'. Please provide a valid PDF filename.");
            }

            // Security check - only allow PDFs from our directories
            $allowedPaths = [
                'pdfs/browserless/',
                'pdfs/dompdf/',
                'pdfs/basic-dompdf/'
            ];

            $found = false;
            $fullPath = null;

            foreach ($allowedPaths as $path) {
                $testPath = $path . $filename;
                if (Storage::disk('public')->exists($testPath)) {
                    $fullPath = $testPath;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                Log::warning('PDF file not found for download', [
                    'filename' => $filename,
                    'searched_paths' => $allowedPaths,
                    'user_id' => auth()->id()
                ]);
                
                return response()->view('errors.404', [
                    'message' => 'PDF file not found'
                ], 404);
            }

            $filePath = Storage::disk('public')->path($fullPath);
            
            return response()->download($filePath, $filename, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ]);

        } catch (Exception $e) {
            Log::error('PDF download failed', [
                'filename' => $filename,
                'error' => $e->getMessage()
            ]);

            abort(500, 'Download failed');
        }
    }

    /**
     * Get PDF service status
     */
    public function getStatus(): JsonResponse
    {
        try {
            $status = $this->pdfService->getServiceStatus();
            
            return response()->json([
                'success' => true,
                'data' => $status
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List available PDF files
     */
    public function listFiles(Request $request): JsonResponse
    {
        try {
            $directories = ['pdfs/browserless', 'pdfs/dompdf', 'pdfs/basic-dompdf'];
            $files = [];

            foreach ($directories as $directory) {
                if (Storage::disk('public')->exists($directory)) {
                    $dirFiles = Storage::disk('public')->files($directory);
                    
                    foreach ($dirFiles as $file) {
                        $filename = basename($file);
                        $files[] = [
                            'filename' => $filename,
                            'path' => $file,
                            'url' => Storage::url($file),
                            'download_url' => route('pdf.download', ['filename' => $filename]),
                            'size' => Storage::disk('public')->size($file),
                            'size_formatted' => $this->formatBytes(Storage::disk('public')->size($file)),
                            'last_modified' => Storage::disk('public')->lastModified($file),
                            'last_modified_formatted' => date('Y-m-d H:i:s', Storage::disk('public')->lastModified($file)),
                            'method' => $this->getMethodFromPath($file)
                        ];
                    }
                }
            }

            // Sort by last modified (newest first)
            usort($files, fn($a, $b) => $b['last_modified'] <=> $a['last_modified']);

            return response()->json([
                'success' => true,
                'data' => [
                    'files' => $files,
                    'total_count' => count($files),
                    'total_size' => array_sum(array_column($files, 'size')),
                    'total_size_formatted' => $this->formatBytes(array_sum(array_column($files, 'size')))
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clean up old PDF files
     */
    public function cleanup(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'days_old' => 'integer|min:1|max:30'
            ]);

            $daysOld = $validated['days_old'] ?? 7;
            $result = $this->pdfService->cleanupOldFiles($daysOld);

            return response()->json([
                'success' => true,
                'message' => "Cleaned up {$result['files_deleted']} files",
                'data' => $result
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PDF generation demo page
     */
    public function demo(): InertiaResponse
    {
        return Inertia::render('Demo/PdfGeneration', [
            'service_status' => $this->pdfService->getServiceStatus(),
            'available_routes' => $this->getAvailableRoutes(),
            'available_components' => $this->getAvailableComponents()
        ]);
    }

    /**
     * Get method from file path
     */
    private function getMethodFromPath(string $path): string
    {
        if (str_contains($path, 'browserless')) {
            return 'browserless';
        } elseif (str_contains($path, 'dompdf')) {
            return 'dompdf';
        } elseif (str_contains($path, 'basic-dompdf')) {
            return 'basic_dompdf';
        }
        
        return 'unknown';
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes(int $size): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, 2) . ' ' . $units[$i];
    }

    /**
     * Get available routes for PDF generation
     */
    private function getAvailableRoutes(): array
    {
        return [
            [
                'route' => 'sentiment-timeline-demo',
                'name' => 'Sentiment Price Timeline',
                'description' => 'Interactive chart showing sentiment vs price correlation',
                'sample_data' => [
                    'coin' => 'bitcoin',
                    'days' => 30,
                    'include_volume' => true
                ]
            ],
            [
                'route' => 'dashboard',
                'name' => 'Dashboard Report',
                'description' => 'Main analytics dashboard with charts and metrics',
                'sample_data' => [
                    'title' => 'Analytics Dashboard',
                    'include_charts' => true
                ]
            ],
            [
                'route' => 'north-star-demo',
                'name' => 'North Star Demo',
                'description' => 'Demo dashboard showcasing all features',
                'sample_data' => []
            ]
        ];
    }

    /**
     * Get available components for PDF generation
     */
    private function getAvailableComponents(): array
    {
        return [
            [
                'component' => 'EnhancedSentimentPriceTimeline',
                'name' => 'Sentiment Price Timeline Chart',
                'description' => 'Advanced chart component with dual-axis visualization',
                'sample_props' => [
                    'initialCoin' => 'bitcoin',
                    'initialTimeframe' => 30,
                    'showVolume' => true,
                    'height' => 500
                ]
            ],
            [
                'component' => 'SentimentPriceChart',
                'name' => 'Basic Sentiment Chart',
                'description' => 'Simple sentiment vs price chart',
                'sample_props' => [
                    'coinSymbol' => 'BTC',
                    'height' => 400
                ]
            ],
            [
                'component' => 'DashboardReport',
                'name' => 'Dashboard Report',
                'description' => 'Comprehensive dashboard report component',
                'sample_props' => [
                    'title' => 'Analytics Report',
                    'data' => []
                ]
            ]
        ];
    }
}