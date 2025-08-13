<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\VuePdfGenerationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Inertia\Inertia;
use Exception;

final class VuePdfController extends Controller
{
    public function __construct(
        private VuePdfGenerationService $vuePdfService
    ) {}

    /**
     * Generate PDF from Vue component
     */
    public function generateFromVueComponent(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'component_route' => 'required|string|max:100',
                'data' => 'sometimes|array',
                'options' => 'sometimes|array',
                'options.format' => 'sometimes|string|in:A4,A3,Letter,Legal',
                'options.orientation' => 'sometimes|string|in:portrait,landscape',
                'options.filename' => 'sometimes|string|max:100',
                'options.title' => 'sometimes|string|max:200'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $validator->errors()
                ], 422);
            }

            $componentRoute = $request->input('component_route');
            $data = $request->input('data', []);
            $options = $request->input('options', []);
            $userId = auth()->id();

            Log::info('Vue PDF generation requested', [
                'route' => $componentRoute,
                'user_id' => $userId,
                'has_data' => !empty($data)
            ]);

            $result = $this->vuePdfService->generateFromVueComponent(
                $componentRoute,
                $data,
                $options,
                $userId
            );

            return response()->json([
                'success' => true,
                'message' => 'PDF generated successfully',
                'result' => $result
            ]);

        } catch (Exception $e) {
            Log::error('Vue PDF generation failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'PDF generation failed',
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
                'sentiment_data' => 'required|array',
                'sentiment_data.daily_aggregates' => 'required|array',
                'sentiment_data.trends' => 'sometimes|array',
                'sentiment_data.platform_breakdown' => 'sometimes|array',
                'options' => 'sometimes|array',
                'date_range' => 'sometimes|array',
                'date_range.start' => 'sometimes|date',
                'date_range.end' => 'sometimes|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $validator->errors()
                ], 422);
            }

            $sentimentData = $request->input('sentiment_data');
            $options = $request->input('options', []);
            $dateRange = $request->input('date_range');

            // Add metadata to options
            if ($dateRange) {
                $options['date_range'] = $dateRange;
                $options['title'] = 'Sentiment Analysis Dashboard - ' . 
                    ($dateRange['start'] ?? 'Recent') . ' to ' . 
                    ($dateRange['end'] ?? 'Present');
            }

            $result = $this->vuePdfService->generateSentimentDashboard($sentimentData, $options);

            return response()->json([
                'success' => true,
                'message' => 'Sentiment dashboard PDF generated successfully',
                'result' => $result
            ]);

        } catch (Exception $e) {
            Log::error('Sentiment dashboard PDF generation failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Sentiment dashboard PDF generation failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate sentiment vs price chart PDF
     */
    public function generateSentimentPriceChart(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'chart_data' => 'required|array',
                'chart_data.sentiment_timeline' => 'required|array',
                'chart_data.price_timeline' => 'required|array',
                'coin_symbol' => 'sometimes|string|max:10',
                'options' => 'sometimes|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $validator->errors()
                ], 422);
            }

            $chartData = $request->input('chart_data');
            $coinSymbol = $request->input('coin_symbol', 'BTC');
            $options = $request->input('options', []);

            // Enhance chart data with metadata
            $enhancedData = array_merge($chartData, [
                'coin_symbol' => strtoupper($coinSymbol),
                'generated_at' => now()->toISOString(),
                'correlation_coefficient' => $this->calculateCorrelation(
                    $chartData['sentiment_timeline'],
                    $chartData['price_timeline']
                )
            ]);

            $result = $this->vuePdfService->generateSentimentPriceChart($enhancedData, $options);

            return response()->json([
                'success' => true,
                'message' => 'Sentiment vs Price chart PDF generated successfully',
                'result' => $result
            ]);

        } catch (Exception $e) {
            Log::error('Sentiment price chart PDF generation failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Sentiment price chart PDF generation failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batch generate multiple Vue component PDFs
     */
    public function batchGenerate(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'components' => 'required|array|min:1|max:10',
                'components.*.route' => 'required|string',
                'components.*.name' => 'sometimes|string',
                'components.*.data' => 'sometimes|array',
                'components.*.options' => 'sometimes|array',
                'global_options' => 'sometimes|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $validator->errors()
                ], 422);
            }

            $components = $request->input('components');
            $globalOptions = $request->input('global_options', []);

            Log::info('Batch Vue PDF generation started', [
                'component_count' => count($components),
                'user_id' => auth()->id()
            ]);

            $result = $this->vuePdfService->batchGenerateVueComponents($components, $globalOptions);

            return response()->json([
                'success' => true,
                'message' => "Batch generation completed: {$result['successful']} successful, {$result['failed']} failed",
                'result' => $result
            ]);

        } catch (Exception $e) {
            Log::error('Batch Vue PDF generation failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Batch PDF generation failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Serve PDF preview for Vue components (used by Browserless)
     */
    public function servePreview(string $token): Response
    {
        try {
            // Retrieve preview data from cache
            $previewData = Cache::get("pdf_preview_{$token}");

            if (!$previewData) {
                return response()->view('errors.404', [], 404);
            }

            $route = $previewData['route'];
            $data = $previewData['data'];
            $userId = $previewData['user_id'];

            Log::info('Serving PDF preview', [
                'token' => $token,
                'route' => $route,
                'user_id' => $userId
            ]);

            // Map route to Vue component/Inertia page
            $vuePage = $this->mapRouteToVuePage($route);

            if (!$vuePage) {
                return response()->view('errors.404', [], 404);
            }

            // Add PDF-specific styling
            $data['pdf_mode'] = true;
            $data['print_styles'] = true;

            // Return Inertia response for the Vue component
            return Inertia::render($vuePage, $data);

        } catch (Exception $e) {
            Log::error('PDF preview serving failed', [
                'token' => $token,
                'error' => $e->getMessage()
            ]);

            return response()->view('errors.500', [], 500);
        }
    }

    /**
     * Download generated PDF
     */
    public function downloadPdf(string $filename): BinaryFileResponse|Response
    {
        try {
            // Validate filename to prevent invalid requests
            if (strlen($filename) < 2 || preg_match('/^[0-9]+$/', $filename)) {
                Log::warning('Invalid PDF filename requested', [
                    'filename' => $filename,
                    'user_id' => auth()->id(),
                    'ip' => request()->ip(),
                    'controller' => 'VuePdfController'
                ]);
                
                abort(404, "Invalid PDF filename: '{$filename}'. Please provide a valid PDF filename.");
            }

            // Security check - ensure filename is safe
            $filename = basename($filename);
            
            // Check both possible storage locations
            $paths = [
                'pdfs/vue-components/' . $filename,
                'pdfs/vue-fallback/' . $filename,
                'pdfs/' . $filename
            ];

            $filePath = null;
            foreach ($paths as $path) {
                if (Storage::disk('public')->exists($path)) {
                    $filePath = $path;
                    break;
                }
            }

            if (!$filePath) {
                Log::warning('PDF file not found for download', [
                    'filename' => $filename,
                    'searched_paths' => $paths,
                    'user_id' => auth()->id()
                ]);
                
                return response()->view('errors.404', [
                    'message' => 'PDF file not found'
                ], 404);
            }

            $fullPath = Storage::disk('public')->path($filePath);

            Log::info('PDF download requested', [
                'filename' => $filename,
                'file_path' => $filePath,
                'user_id' => auth()->id()
            ]);

            return response()->download($fullPath, $filename, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
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
     * Get PDF generation statistics
     */
    public function getGenerationStats(Request $request): JsonResponse
    {
        try {
            $period = $request->input('period', '7d');
            $stats = $this->vuePdfService->getGenerationStats($period);

            return response()->json([
                'success' => true,
                'stats' => $stats
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get generation stats',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test PDF generation endpoint
     */
    public function testGeneration(Request $request): JsonResponse
    {
        try {
            $testData = [
                'title' => 'Test PDF Generation',
                'generated_at' => now()->toISOString(),
                'test_data' => [
                    'sample_number' => rand(1, 1000),
                    'random_text' => 'This is a test PDF generation'
                ]
            ];

            $result = $this->vuePdfService->generateFromVueComponent(
                'test.pdf-generation',
                $testData,
                ['filename' => 'test-pdf-' . now()->timestamp . '.pdf']
            );

            return response()->json([
                'success' => true,
                'message' => 'Test PDF generated successfully',
                'result' => $result
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
     * Map component routes to Vue pages
     */
    private function mapRouteToVuePage(string $route): ?string
    {
        $mapping = [
            'sentiment-analysis.dashboard' => 'SentimentDashboard',
            'charts.sentiment-price' => 'Charts/SentimentPriceChart',
            'pdf.sentiment-price-chart' => 'Pdf/SentimentPriceChartPdf',
            'reports.dashboard' => 'Reports/Dashboard',
            'analytics.overview' => 'Analytics/Overview',
            'test.pdf-generation' => 'Test/PdfGeneration'
        ];

        return $mapping[$route] ?? null;
    }

    /**
     * Calculate correlation coefficient between sentiment and price data
     */
    private function calculateCorrelation(array $sentimentData, array $priceData): float
    {
        if (count($sentimentData) !== count($priceData) || count($sentimentData) < 2) {
            return 0.0;
        }

        $n = count($sentimentData);
        $sentimentValues = array_column($sentimentData, 'value');
        $priceValues = array_column($priceData, 'value');

        $sumX = array_sum($sentimentValues);
        $sumY = array_sum($priceValues);
        $sumXY = 0;
        $sumXX = 0;
        $sumYY = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumXY += $sentimentValues[$i] * $priceValues[$i];
            $sumXX += $sentimentValues[$i] * $sentimentValues[$i];
            $sumYY += $priceValues[$i] * $priceValues[$i];
        }

        $numerator = $n * $sumXY - $sumX * $sumY;
        $denominator = sqrt(($n * $sumXX - $sumX * $sumX) * ($n * $sumYY - $sumY * $sumY));

        return $denominator != 0 ? round($numerator / $denominator, 4) : 0.0;
    }
}