<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\UnifiedVuePdfService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Exception;

class UnifiedVuePdfController extends Controller
{
    public function __construct(
        private readonly UnifiedVuePdfService $pdfService
    ) {}

    /**
     * Generate PDF from Vue component
     */
    public function generateFromComponent(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'component' => 'required|string|max:100',
                'props' => 'array',
                'options' => 'array',
                'options.format' => 'string|in:A4,A3,A5,Letter,Legal',
                'options.orientation' => 'string|in:portrait,landscape',
                'options.filename' => 'string|max:255',
                'options.quality' => 'string|in:low,standard,high',
                'options.force_method' => 'string|in:browserless,dompdf'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $validator->errors()
                ], 422);
            }

            $result = $this->pdfService->generateFromVueComponent(
                $request->input('component'),
                $request->input('props', []),
                $request->input('options', []),
                $request->user()?->id
            );

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'PDF generated successfully'
            ]);

        } catch (Exception $e) {
            Log::error('Vue component PDF generation failed', [
                'component' => $request->input('component'),
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id
            ]);

            return response()->json([
                'success' => false,
                'error' => 'PDF generation failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate PDF from Vue route
     */
    public function generateFromRoute(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'route_name' => 'required|string|max:100',
                'route_params' => 'array',
                'data' => 'array',
                'options' => 'array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $validator->errors()
                ], 422);
            }

            $result = $this->pdfService->generateFromVueRoute(
                $request->input('route_name'),
                $request->input('route_params', []),
                $request->input('data', []),
                $request->input('options', [])
            );

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'PDF generated from route successfully'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Route PDF generation failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batch generate multiple PDFs
     */
    public function batchGenerate(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'components' => 'required|array|min:1|max:10',
                'components.*.component' => 'required|string|max:100',
                'components.*.props' => 'array',
                'components.*.options' => 'array',
                'global_options' => 'array',
                'global_options.delay_ms' => 'integer|min:100|max:5000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $validator->errors()
                ], 422);
            }

            $components = $request->input('components');
            $globalOptions = array_merge(
                $request->input('global_options', []),
                ['batch_id' => uniqid('batch_')]
            );

            $result = $this->pdfService->batchGenerate($components, $globalOptions);

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Batch PDF generation completed'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Batch PDF generation failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate sentiment dashboard PDF
     */
    public function generateSentimentDashboard(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'timeframe' => 'string|in:1h,4h,1d,7d,30d',
                'symbols' => 'array',
                'symbols.*' => 'string|max:10',
                'include_charts' => 'boolean',
                'options' => 'array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $validator->errors()
                ], 422);
            }

            $sentimentData = [
                'timeframe' => $request->input('timeframe', '7d'),
                'symbols' => $request->input('symbols', ['BTC', 'ETH']),
                'include_charts' => $request->input('include_charts', true),
                'generated_at' => now()->toISOString()
            ];

            $options = array_merge([
                'orientation' => 'landscape',
                'filename' => 'sentiment-dashboard-' . now()->format('Y-m-d-H-i-s') . '.pdf',
                'wait_for' => 'networkidle2',
                'timeout' => 45000,
                'has_charts' => true
            ], $request->input('options', []));

            $result = $this->pdfService->generateFromVueComponent(
                'SentimentDashboard',
                $sentimentData,
                $options,
                $request->user()?->id
            );

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Sentiment dashboard PDF generated successfully'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Sentiment dashboard PDF generation failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate analytics dashboard PDF
     */
    public function generateAnalyticsDashboard(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'contract_address' => 'required|string|size:42',
                'analysis_type' => 'string|in:security,performance,comprehensive',
                'include_charts' => 'boolean',
                'options' => 'array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $validator->errors()
                ], 422);
            }

            $analyticsData = [
                'contract_address' => $request->input('contract_address'),
                'analysis_type' => $request->input('analysis_type', 'comprehensive'),
                'include_charts' => $request->input('include_charts', true),
                'generated_at' => now()->toISOString()
            ];

            $options = array_merge([
                'format' => 'A4',
                'orientation' => 'portrait',
                'filename' => 'analytics-dashboard-' . now()->format('Y-m-d-H-i-s') . '.pdf',
                'has_charts' => $request->input('include_charts', true)
            ], $request->input('options', []));

            $result = $this->pdfService->generateFromVueComponent(
                'AnalyticsDashboard',
                $analyticsData,
                $options,
                $request->user()?->id
            );

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Analytics dashboard PDF generated successfully'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Analytics dashboard PDF generation failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Serve PDF preview for Browserless rendering
     */
    public function serveComponentPreview(string $token): Response
    {
        try {
            // Retrieve component data from cache
            $componentData = Cache::get("vue_pdf_component_{$token}");
            
            if (!$componentData) {
                abort(404, 'Preview token expired or invalid');
            }

            // Render the Vue component for PDF generation
            $html = $this->renderComponentForPdf(
                $componentData['component'],
                $componentData['props']
            );

            return response($html, 200, [
                'Content-Type' => 'text/html',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ]);

        } catch (Exception $e) {
            Log::error('PDF preview serving failed', [
                'token' => $token,
                'error' => $e->getMessage()
            ]);

            abort(500, 'Failed to serve PDF preview');
        }
    }

    /**
     * Serve route preview for Browserless rendering
     */
    public function serveRoutePreview(string $token): Response
    {
        try {
            $routeData = Cache::get("vue_pdf_route_{$token}");
            
            if (!$routeData) {
                abort(404, 'Route token expired or invalid');
            }

            // Redirect to the actual route with embedded data
            return redirect($routeData['route_url'] . '?pdf_data=' . base64_encode(json_encode($routeData['data'])));

        } catch (Exception $e) {
            Log::error('PDF route preview serving failed', [
                'token' => $token,
                'error' => $e->getMessage()
            ]);

            abort(500, 'Failed to serve route preview');
        }
    }

    /**
     * Download generated PDF
     */
    public function downloadPdf(string $filename): Response
    {
        try {
            // Validate filename to prevent invalid requests
            if (strlen($filename) < 2 || preg_match('/^[0-9]+$/', $filename)) {
                Log::warning('Invalid PDF filename requested', [
                    'filename' => $filename,
                    'user_id' => auth()->id(),
                    'ip' => request()->ip(),
                    'controller' => 'UnifiedVuePdfController'
                ]);
                
                abort(404, "Invalid PDF filename: '{$filename}'. Please provide a valid PDF filename.");
            }

            // Sanitize filename for security
            $filename = basename($filename);
            
            // Security: Only allow downloading PDFs from the vue-components directory
            $allowedPaths = [
                'pdfs/vue-components/',
                'pdfs/browserless/',
                'pdfs/dompdf/'
            ];

            $found = false;
            $filePath = null;
            
            foreach ($allowedPaths as $path) {
                $searchPath = $path . $filename;
                if (Storage::disk('public')->exists($searchPath)) {
                    $filePath = $searchPath;
                    $found = true;
                    break;
                }
                
                // Also check in date-based subdirectories
                $dateBasedPath = $path . now()->format('Y/m/d/') . $filename;
                if (Storage::disk('public')->exists($dateBasedPath)) {
                    $filePath = $dateBasedPath;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                Log::info('PDF file not found', [
                    'filename' => $filename,
                    'searched_paths' => $allowedPaths,
                    'user_id' => auth()->id()
                ]);
                
                abort(404, 'PDF file not found');
            }

            $content = Storage::disk('public')->get($filePath);
            
            Log::info('PDF download successful', [
                'filename' => $filename,
                'user_id' => auth()->id(),
                'controller' => 'UnifiedVuePdfController'
            ]);
            
            return response($content, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Content-Length' => strlen($content),
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ]);

        } catch (Exception $e) {
            Log::error('PDF download failed', [
                'filename' => $filename,
                'error' => $e->getMessage()
            ]);

            abort(500, 'Failed to download PDF');
        }
    }

    /**
     * Get service status and health
     */
    public function getServiceStatus(): JsonResponse
    {
        try {
            $status = $this->pdfService->getServiceStatus();
            
            return response()->json([
                'success' => true,
                'data' => $status,
                'message' => 'Service status retrieved successfully'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get service status',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List generated PDF files
     */
    public function listGeneratedFiles(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'limit' => 'integer|min:1|max:100',
                'path' => 'string|in:vue-components,browserless,dompdf'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $validator->errors()
                ], 422);
            }

            $limit = $request->input('limit', 20);
            $path = 'pdfs/' . ($request->input('path', 'vue-components')) . '/';
            
            $files = collect(Storage::disk('public')->allFiles($path))
                ->filter(fn($file) => str_ends_with($file, '.pdf'))
                ->map(function ($file) {
                    $fullPath = Storage::disk('public')->path($file);
                    return [
                        'filename' => basename($file),
                        'path' => $file,
                        'url' => Storage::url($file),
                        'download_url' => route('unified-vue-pdf.download', ['filename' => basename($file)]),
                        'size' => Storage::disk('public')->size($file),
                        'size_formatted' => $this->formatBytes(Storage::disk('public')->size($file)),
                        'created_at' => date('Y-m-d H:i:s', Storage::disk('public')->lastModified($file))
                    ];
                })
                ->sortByDesc('created_at')
                ->take($limit)
                ->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'files' => $files,
                    'total_count' => $files->count(),
                    'path' => $path
                ],
                'message' => 'Files retrieved successfully'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to list files',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test PDF generation with sample data
     */
    public function testGeneration(Request $request): JsonResponse
    {
        try {
            $testComponent = $request->input('component', 'TestComponent');
            $testProps = [
                'title' => 'PDF Generation Test',
                'content' => 'This is a test of the unified Vue PDF generation service.',
                'timestamp' => now()->toISOString(),
                'test_data' => [
                    'numbers' => [1, 2, 3, 4, 5],
                    'text' => 'Sample text content for testing',
                    'boolean' => true
                ]
            ];

            $testOptions = [
                'filename' => 'test-generation-' . now()->format('Y-m-d-H-i-s') . '.pdf',
                'format' => 'A4',
                'orientation' => 'portrait'
            ];

            $result = $this->pdfService->generateFromVueComponent(
                $testComponent,
                $testProps,
                $testOptions,
                $request->user()?->id
            );

            return response()->json([
                'success' => true,
                'data' => $result,
                'test_props' => $testProps,
                'message' => 'Test PDF generation completed successfully'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Test PDF generation failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Render Vue component for PDF preview
     */
    private function renderComponentForPdf(string $componentName, array $props): string
    {
        // This would ideally use server-side rendering of Vue components
        // For now, we'll create a basic HTML structure that loads the component
        
        $propsJson = json_encode($props);
        $appUrl = config('app.url');
        
        return "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$componentName} PDF</title>
            <link href='{$appUrl}/build/assets/app.css' rel='stylesheet'>
            <style>
                body { margin: 0; padding: 20px; font-family: 'Inter', sans-serif; }
                .pdf-container { max-width: 100%; }
                @media print { body { margin: 0; } }
            </style>
        </head>
        <body>
            <div id='app'>
                <div class='pdf-container'>
                    <div id='pdf-component'></div>
                </div>
            </div>
            
            <script>
                window.pdfComponentName = '{$componentName}';
                window.pdfComponentProps = {$propsJson};
            </script>
            <script src='{$appUrl}/build/assets/app.js'></script>
            
            <script>
                // Wait for component to render
                document.addEventListener('DOMContentLoaded', function() {
                    // Signal that the page is ready for PDF generation
                    window.pdfReady = true;
                    
                    // Dispatch custom event for Browserless to detect
                    window.dispatchEvent(new CustomEvent('pdf-ready'));
                });
            </script>
        </body>
        </html>";
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
}
