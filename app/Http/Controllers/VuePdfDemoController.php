<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\VuePdfGenerationService;
use App\Services\EnhancedVuePdfService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use Inertia\Inertia;
use Exception;

final class VuePdfDemoController extends Controller
{
    public function __construct(
        private VuePdfGenerationService $vuePdfService,
        private EnhancedVuePdfService $enhancedPdfService
    ) {}

    /**
     * Show the PDF generation demo page
     */
    public function showDemo()
    {
        return Inertia::render('Demo/PdfGenerationDemo');
    }

    /**
     * Generate PDF from Vue component (demo endpoint)
     */
    public function generatePdf(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'component_route' => 'required|string|max:100',
                'data' => 'sometimes|array',
                'options' => 'sometimes|array',
                'options.engine' => 'sometimes|string|in:auto,browserless,dompdf',
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
            $engine = $options['engine'] ?? 'auto';

            Log::info('Demo PDF generation requested', [
                'route' => $componentRoute,
                'engine' => $engine,
                'user_id' => auth()->id(),
                'has_data' => !empty($data)
            ]);

            // Use enhanced service for better error handling
            $result = $this->generateWithSelectedEngine($componentRoute, $data, $options, $engine);

            return response()->json([
                'success' => true,
                'message' => 'PDF generated successfully',
                'result' => $result
            ]);

        } catch (Exception $e) {
            Log::error('Demo PDF generation failed', [
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
     * Generate sentiment price chart PDF (specific endpoint)
     */
    public function generateSentimentChart(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'chart_data' => 'required|array',
                'chart_data.sentiment_timeline' => 'required|array',
                'chart_data.price_timeline' => 'required|array',
                'coin_symbol' => 'sometimes|string|max:10',
                'stats' => 'sometimes|array',
                'metadata' => 'sometimes|array',
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
            $stats = $request->input('stats', []);
            $metadata = $request->input('metadata', []);
            $options = $request->input('options', []);

            // Enhanced data for PDF template
            $enhancedData = [
                'chartData' => $chartData,
                'stats' => $stats,
                'metadata' => array_merge($metadata, [
                    'coin_symbol' => strtoupper($coinSymbol),
                    'generated_at' => now()->toISOString()
                ]),
                'coinSymbol' => strtoupper($coinSymbol),
                'title' => $options['title'] ?? "{$coinSymbol} Sentiment vs Price Analysis"
            ];

            // Set default options for sentiment charts
            $defaultOptions = [
                'format' => 'A4',
                'orientation' => 'landscape',
                'filename' => 'sentiment-chart-' . strtolower($coinSymbol) . '-' . now()->format('Y-m-d-H-i-s') . '.pdf',
                'wait_for' => 'networkidle2',
                'timeout' => 60000,
                'chart_rendering' => true,
                'print_background' => true
            ];

            $mergedOptions = array_merge($defaultOptions, $options);

            $result = $this->enhancedPdfService->generateFromVueRoute(
                'pdf.sentiment-price-chart',
                $enhancedData,
                $mergedOptions,
                auth()->id()
            );

            return response()->json([
                'success' => true,
                'message' => 'Sentiment chart PDF generated successfully',
                'result' => $result
            ]);

        } catch (Exception $e) {
            Log::error('Sentiment chart PDF generation failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Sentiment chart PDF generation failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate dashboard report PDF
     */
    public function generateDashboard(Request $request): JsonResponse
    {
        try {
            $data = $request->input('data', []);
            $options = $request->input('options', []);

            $defaultOptions = [
                'format' => 'A4',
                'orientation' => 'portrait',
                'title' => 'Analytics Dashboard Report',
                'filename' => 'dashboard-report-' . now()->format('Y-m-d-H-i-s') . '.pdf'
            ];

            $mergedOptions = array_merge($defaultOptions, $options);

            $result = $this->vuePdfService->generateFromVueComponent(
                'dashboard',
                $data,
                $mergedOptions,
                auth()->id()
            );

            return response()->json([
                'success' => true,
                'message' => 'Dashboard PDF generated successfully',
                'result' => $result
            ]);

        } catch (Exception $e) {
            Log::error('Dashboard PDF generation failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Dashboard PDF generation failed',
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
            $type = $request->input('type', 'sentiment-chart');
            $engine = $request->input('engine', 'auto');

            $testData = $this->generateTestData($type);
            
            $options = [
                'filename' => "test-{$type}-" . now()->timestamp . '.pdf',
                'title' => "Test {$type} PDF Generation",
                'engine' => $engine
            ];

            $result = $this->generateWithSelectedEngine(
                $this->mapTypeToRoute($type),
                $testData,
                $options,
                $engine
            );

            return response()->json([
                'success' => true,
                'message' => 'Test PDF generated successfully',
                'result' => $result,
                'test_data' => $testData
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
     * Serve PDF preview for testing
     */
    public function servePreview(string $type, Request $request)
    {
        try {
            $testData = $this->generateTestData($type);
            $testData['pdf_mode'] = true;
            $testData['print_styles'] = true;

            // Map type to Vue page
            $vuePage = $this->mapTypeToVuePage($type);

            if (!$vuePage) {
                return response()->view('errors.404', [], 404);
            }

            return Inertia::render($vuePage, $testData);

        } catch (Exception $e) {
            Log::error('PDF preview serving failed', [
                'type' => $type,
                'error' => $e->getMessage()
            ]);

            return response()->view('errors.500', [], 500);
        }
    }

    /**
     * Get PDF generation service status
     */
    public function getServiceStatus(): JsonResponse
    {
        try {
            $status = $this->enhancedPdfService->getServiceStatus();
            
            return response()->json([
                'success' => true,
                'status' => $status
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
     * Generate PDF with selected engine
     */
    private function generateWithSelectedEngine(string $route, array $data, array $options, string $engine): array
    {
        switch ($engine) {
            case 'browserless':
                // Force browserless only
                $options['force_browserless'] = true;
                return $this->enhancedPdfService->generateFromVueRoute($route, $data, $options, auth()->id());
                
            case 'dompdf':
                // Force DomPDF only
                $options['force_dompdf'] = true;
                return $this->enhancedPdfService->generateFromVueRoute($route, $data, $options, auth()->id());
                
            case 'auto':
            default:
                // Let the service decide (auto fallback)
                return $this->enhancedPdfService->generateFromVueRoute($route, $data, $options, auth()->id());
        }
    }

    /**
     * Generate test data for different PDF types
     */
    private function generateTestData(string $type): array
    {
        switch ($type) {
            case 'sentiment-chart':
                return $this->generateSampleSentimentData();
                
            case 'dashboard':
                return $this->generateSampleDashboardData();
                
            case 'report':
                return $this->generateSampleReportData();
                
            default:
                return [
                    'title' => 'Test PDF Generation',
                    'content' => 'This is a test PDF generated from a Vue component.',
                    'generated_at' => now()->toISOString()
                ];
        }
    }

    /**
     * Generate sample sentiment data
     */
    private function generateSampleSentimentData(): array
    {
        $data = [];
        $startDate = now()->subDays(30);
        
        for ($i = 0; $i < 30; $i++) {
            $date = $startDate->copy()->addDays($i);
            $sentiment = sin($i * 0.2) * 0.4 + (rand(-50, 50) / 100) * 0.3;
            $basePrice = 50000 + sin($i * 0.15) * 5000 + (rand(-1000, 1000));
            
            $data[] = [
                'date' => $date->format('Y-m-d'),
                'sentiment' => max(-1, min(1, $sentiment)),
                'price' => max(0, $basePrice),
                'volume' => rand(500000, 1500000)
            ];
        }
        
        return [
            'chartData' => [
                'sentiment' => array_map(fn($d) => ['x' => $d['date'], 'y' => $d['sentiment']], $data),
                'price' => array_map(fn($d) => ['x' => $d['date'], 'y' => $d['price']], $data),
                'currency' => 'USD'
            ],
            'stats' => [
                'correlation' => (rand(-100, 100) / 100) * 0.8,
                'avg_sentiment' => array_sum(array_column($data, 'sentiment')) / count($data),
                'price_change' => (($data[count($data) - 1]['price'] - $data[0]['price']) / $data[0]['price']) * 100,
                'data_points' => count($data)
            ],
            'metadata' => [
                'coin_symbol' => 'BTC',
                'start_date' => $data[0]['date'],
                'end_date' => $data[count($data) - 1]['date'],
                'days' => count($data),
                'is_demo' => true
            ],
            'coinSymbol' => 'BTC',
            'title' => 'Bitcoin Sentiment vs Price Analysis (Demo Data)'
        ];
    }

    /**
     * Generate sample dashboard data
     */
    private function generateSampleDashboardData(): array
    {
        return [
            'metrics' => [
                'total_analyses' => rand(5000, 15000),
                'security_score' => rand(60, 100),
                'sentiment_score' => (rand(-100, 100) / 100) * 1.5,
                'active_contracts' => rand(100, 800)
            ],
            'charts' => [
                'sentiment_trends' => $this->generateTimeSeriesData(7),
                'security_breakdown' => $this->generateCategoryData(['High', 'Medium', 'Low']),
                'platform_distribution' => $this->generateCategoryData(['Twitter', 'Reddit', 'Telegram'])
            ],
            'recent_activities' => $this->generateRecentActivities(10)
        ];
    }

    /**
     * Generate sample report data
     */
    private function generateSampleReportData(): array
    {
        return [
            'title' => 'Blockchain Security Analysis Report',
            'summary' => 'Comprehensive analysis of blockchain security findings and sentiment analysis',
            'sections' => [
                [
                    'title' => 'Executive Summary',
                    'content' => 'Overview of key findings and recommendations'
                ],
                [
                    'title' => 'Detailed Analysis',
                    'content' => 'In-depth technical analysis and methodology'
                ],
                [
                    'title' => 'Recommendations',
                    'content' => 'Actionable recommendations for improvement'
                ]
            ],
            'generated_at' => now()->toISOString()
        ];
    }

    /**
     * Generate time series data
     */
    private function generateTimeSeriesData(int $days): array
    {
        $data = [];
        for ($i = 0; $i < $days; $i++) {
            $data[] = [
                'date' => now()->subDays($days - $i)->format('Y-m-d'),
                'value' => rand(10, 100)
            ];
        }
        return $data;
    }

    /**
     * Generate category data
     */
    private function generateCategoryData(array $categories): array
    {
        return array_map(fn($cat) => [
            'category' => $cat,
            'value' => rand(10, 100)
        ], $categories);
    }

    /**
     * Generate recent activities
     */
    private function generateRecentActivities(int $count): array
    {
        $activities = [];
        $actions = ['Contract Analyzed', 'Vulnerability Found', 'Sentiment Alert', 'Security Scan'];
        
        for ($i = 0; $i < $count; $i++) {
            $activities[] = [
                'action' => $actions[array_rand($actions)],
                'timestamp' => now()->subMinutes(rand(1, 10080))->toISOString(),
                'details' => "Activity details for item " . ($i + 1)
            ];
        }
        
        return $activities;
    }

    /**
     * Map type to route
     */
    private function mapTypeToRoute(string $type): string
    {
        $mapping = [
            'sentiment-chart' => 'pdf.sentiment-price-chart',
            'dashboard' => 'reports.dashboard',
            'report' => 'reports.custom',
            'north-star' => 'demo.north-star'
        ];

        return $mapping[$type] ?? 'pdf.sentiment-price-chart';
    }

    /**
     * Map type to Vue page
     */
    private function mapTypeToVuePage(string $type): ?string
    {
        $mapping = [
            'sentiment-chart' => 'Pdf/SentimentPriceChartPdf',
            'dashboard' => 'Pdf/DashboardReport',
            'report' => 'Pdf/CustomReport',
            'north-star' => 'Demo/NorthStarDashboard'
        ];

        return $mapping[$type] ?? null;
    }
}
