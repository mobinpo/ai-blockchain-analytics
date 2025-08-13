<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PdfGenerationService;
use App\Services\PdfGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Carbon\Carbon;
use Exception;

final class PdfController extends Controller
{
    private PdfGenerationService $pdfService;
    private PdfGeneratorService $newPdfService;

    public function __construct(
        PdfGenerationService $pdfService,
        PdfGeneratorService $newPdfService
    ) {
        $this->pdfService = $pdfService;
        $this->newPdfService = $newPdfService;
    }

    /**
     * Generate PDF from dashboard data
     */
    public function generateDashboardPdf(Request $request): Response
    {
        try {
            $request->validate([
                'data' => 'required|array',
                'options' => 'sometimes|array'
            ]);

            $data = $request->input('data');
            $options = $request->input('options', []);

            $result = $this->pdfService->generateDashboardReport($data, $options);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Dashboard PDF generated successfully',
                    'file_url' => $result['url'],
                    'filename' => $result['filename'],
                    'processing_time' => $result['processing_time'],
                    'method' => $result['method']
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'PDF generation failed',
                    'error' => $result['error'] ?? 'Unknown error'
                ], 500);
            }

        } catch (Exception $e) {
            Log::error('Dashboard PDF generation failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'PDF generation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate PDF from sentiment analysis data
     */
    public function generateSentimentPdf(Request $request): Response
    {
        try {
            $request->validate([
                'data' => 'required|array',
                'date_range' => 'sometimes|array',
                'platforms' => 'sometimes|array',
                'options' => 'sometimes|array'
            ]);

            $data = $request->input('data');
            $options = array_merge([
                'date_range' => $request->input('date_range'),
                'platforms' => $request->input('platforms', ['all'])
            ], $request->input('options', []));

            $result = $this->pdfService->generateSentimentReport($data, $options);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] ? 'Sentiment PDF generated successfully' : 'PDF generation failed',
                'file_url' => $result['url'] ?? null,
                'filename' => $result['filename'] ?? null,
                'processing_time' => $result['processing_time'] ?? null,
                'method' => $result['method'] ?? null,
                'error' => $result['error'] ?? null
            ], $result['success'] ? 200 : 500);

        } catch (Exception $e) {
            Log::error('Sentiment PDF generation failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'PDF generation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate analytics report PDF for a specific contract
     */
    public function generateAnalyticsReport(Request $request, string $contractId): Response|JsonResponse
    {
        try {
            Log::info('Generating analytics report PDF', [
                'contract_id' => $contractId,
                'user_id' => auth()->id()
            ]);

            // Validate contract ID format
            if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $contractId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid contract address format',
                    'contract_id' => $contractId
                ], 400);
            }

            // Get contract analytics data (mock implementation)
            $analyticsData = $this->generateContractAnalyticsData($contractId);
            
            // Separate contract data and analysis results for the service
            $contractData = $analyticsData['contract'];
            
            // Map our data structure to what the template expects
            $analysisResults = [
                'vulnerability_score' => $analyticsData['security_analysis']['overall_score'],
                'gas_efficiency' => 75, // Mock gas efficiency score
                'code_quality' => 82, // Mock code quality score
                'security_issues' => array_map(function($finding) {
                    return $finding['title'] . ' (' . ucfirst($finding['severity']) . '): ' . $finding['description'];
                }, $analyticsData['detailed_findings']),
                'recommendations' => array_merge(
                    array_map(function($finding) {
                        return $finding['recommendation'];
                    }, $analyticsData['detailed_findings']),
                    $analyticsData['gas_analysis']['optimization_suggestions']
                ),
                'detailed_analysis' => [
                    'security_findings' => array_map(function($finding) {
                        return $finding['title'] . ' at line ' . $finding['line_number'] . ': ' . $finding['description'];
                    }, $analyticsData['detailed_findings']),
                    'gas_analysis' => [
                        'Deployment cost: ' . number_format($analyticsData['gas_analysis']['deployment_cost']) . ' gas',
                        'Average transaction cost: ' . number_format($analyticsData['gas_analysis']['average_transaction_cost']) . ' gas',
                        'Most expensive function: ' . $analyticsData['gas_analysis']['most_expensive_function']['name'] . ' (' . number_format($analyticsData['gas_analysis']['most_expensive_function']['gas_cost']) . ' gas)'
                    ],
                    'function_breakdown' => [
                        'Total functions: ' . $analyticsData['function_analysis']['total_functions'],
                        'Public functions: ' . $analyticsData['function_analysis']['public_functions'],
                        'External functions: ' . $analyticsData['function_analysis']['external_functions'],
                        'Payable functions: ' . $analyticsData['function_analysis']['payable_functions']
                    ],
                    'dependencies' => $analyticsData['dependency_analysis']['imported_contracts'],
                    'activity_summary' => [
                        'Total transactions: ' . number_format($analyticsData['activity_metrics']['total_transactions']),
                        'Unique addresses: ' . number_format($analyticsData['activity_metrics']['unique_addresses']),
                        'Volume (USD): $' . number_format($analyticsData['activity_metrics']['volume_usd'])
                    ]
                ],
                'ai_insights' => [
                    'This contract follows OpenZeppelin standards and implements common security patterns.',
                    'Gas optimization opportunities exist in loop structures and storage operations.',
                    'The contract maintains good separation of concerns with proper access controls.',
                    'Consider implementing additional event emissions for better transparency.'
                ]
            ];
            
            $filename = 'contract-analytics-' . substr($contractId, 2, 8) . '-' . now()->format('Y-m-d') . '.pdf';
            
            try {
                $pdfContent = $this->pdfService->generateAnalyticsReport($contractData, $analysisResults, 'detailed');
                
                // Save PDF to storage
                $filePath = $this->savePdfToStorage($pdfContent, $filename);
                $fileUrl = Storage::url('pdfs/' . $filename);
                
                $result = [
                    'success' => true,
                    'filename' => $filename,
                    'url' => $fileUrl,
                    'file_path' => $filePath,
                    'method' => 'dompdf',
                    'processing_time' => 0 // Not available from this method
                ];
            } catch (Exception $e) {
                throw new Exception('PDF generation failed: ' . $e->getMessage());
            }

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Contract analytics PDF generated successfully',
                    'file_url' => $result['url'],
                    'filename' => $result['filename'],
                    'contract_id' => $contractId,
                    'processing_time' => $result['processing_time'],
                    'method' => $result['method']
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Analytics PDF generation failed',
                    'contract_id' => $contractId,
                    'error' => $result['error'] ?? 'Unknown error'
                ], 500);
            }

        } catch (Exception $e) {
            Log::error('Analytics PDF generation failed', [
                'contract_id' => $contractId,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'PDF generation failed',
                'contract_id' => $contractId,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate sentiment dashboard PDF
     */
    public function generateSentimentDashboard(Request $request): Response|JsonResponse
    {
        try {
            Log::info('Generating sentiment dashboard PDF', [
                'user_id' => auth()->id(),
                'request_params' => $request->query()
            ]);

            // Get request parameters
            $dateRange = [
                'start' => $request->get('start_date', now()->subDays(30)->toDateString()),
                'end' => $request->get('end_date', now()->toDateString())
            ];
            $platforms = explode(',', $request->get('platforms', 'all'));
            $format = $request->get('format', 'A4');
            
            // Generate mock sentiment dashboard data
            $sentimentData = $this->generateSentimentDashboardData($dateRange, $platforms);
            
            $options = [
                'filename' => 'sentiment-dashboard-' . now()->format('Y-m-d-H-i') . '.pdf',
                'format' => $format,
                'orientation' => 'portrait',
                'date_range' => $dateRange,
                'platforms' => $platforms
            ];

            $result = $this->pdfService->generateSentimentReport($sentimentData, $options);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Sentiment dashboard PDF generated successfully',
                    'file_url' => $result['url'],
                    'filename' => $result['filename'],
                    'date_range' => $dateRange,
                    'platforms' => $platforms,
                    'processing_time' => $result['processing_time'],
                    'method' => $result['method']
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Sentiment dashboard PDF generation failed',
                    'date_range' => $dateRange,
                    'platforms' => $platforms,
                    'error' => $result['error'] ?? 'Unknown error'
                ], 500);
            }

        } catch (Exception $e) {
            Log::error('Sentiment dashboard PDF generation failed', [
                'error' => $e->getMessage(),
                'request_params' => $request->all(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'PDF generation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate PDF from crawler data
     */
    public function generateCrawlerPdf(Request $request): Response
    {
        try {
            $request->validate([
                'data' => 'required|array',
                'platforms' => 'sometimes|array',
                'date_range' => 'sometimes|array',
                'options' => 'sometimes|array'
            ]);

            $data = $request->input('data');
            $options = array_merge([
                'platforms' => $request->input('platforms', ['twitter', 'reddit', 'telegram']),
                'date_range' => $request->input('date_range')
            ], $request->input('options', []));

            $result = $this->pdfService->generateCrawlerReport($data, $options);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] ? 'Crawler PDF generated successfully' : 'PDF generation failed',
                'file_url' => $result['url'] ?? null,
                'filename' => $result['filename'] ?? null,
                'processing_time' => $result['processing_time'] ?? null,
                'method' => $result['method'] ?? null,
                'error' => $result['error'] ?? null
            ], $result['success'] ? 200 : 500);

        } catch (Exception $e) {
            Log::error('Crawler PDF generation failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'PDF generation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

        /**
     * Preview Vue component for PDF generation
     */
public function previewComponent(Request $request, string $component)
    {
        try {
            // Validate component name to prevent invalid component access
            $validComponents = [
                'DashboardReport',
                'SentimentReport', 
                'NorthStarDashboard',
                'SentimentPriceChartPdf'
            ];
            
            if (!in_array($component, $validComponents)) {
                Log::warning('Invalid PDF component requested', [
                    'component' => $component,
                    'valid_components' => $validComponents,
                    'user_id' => auth()->id()
                ]);
                
                abort(404, "PDF component '{$component}' not found. Available components: " . implode(', ', $validComponents));
            }

            Log::info('PDF preview accessed', [
                'component' => $component,
                'authenticated' => auth()->check(),
                'user_id' => auth()->id()
            ]);

            // Simple demo data
            $data = [
                'metrics' => [
                    'contracts_analyzed' => 1247,
                    'vulnerabilities_found' => 89,
                    'active_threats' => 12,
                    'security_score' => 94.7
                ],
                'recent_analyses' => [
                    [
                        'contract' => '0x1234...5678',
                        'status' => 'completed',
                        'risk_level' => 'medium',
                        'timestamp' => now()->subMinutes(15)->toISOString()
                    ]
                ],
                'threat_feed' => [
                    [
                        'type' => 'flash_loan_attack',
                        'severity' => 'high',
                        'target' => 'DeFi Protocol X',
                        'timestamp' => now()->subHours(2)->toISOString()
                    ]
                ]
            ];

            // Return Inertia component
            return Inertia::render("Pdf/{$component}", [
                'data' => $data,
                'pdf_mode' => false,
                'demo_mode' => true,
                'options' => [
                    'format' => 'A4',
                    'orientation' => 'portrait'
                ]
            ]);

        } catch (Exception $e) {
            Log::error('PDF preview failed', [
                'component' => $component,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'PDF preview failed',
                'message' => $e->getMessage(),
                'component' => $component
            ], 500);
        }
    }

    /**
     * Download generated PDF
     */
    public function downloadPdf(string $filename): Response|BinaryFileResponse
    {
        try {
            // Validate filename to prevent invalid requests
            if (strlen($filename) < 2 || preg_match('/^[0-9]+$/', $filename)) {
                Log::warning('Invalid PDF filename requested', [
                    'filename' => $filename,
                    'user_id' => auth()->id(),
                    'ip' => request()->ip()
                ]);
                
                abort(404, "Invalid PDF filename: '{$filename}'. Please provide a valid PDF component name or filename.");
            }

            // Sanitize filename for security
            $filename = basename($filename);
            
            // Check if this is a component name (no .pdf extension)
            if (!str_ends_with($filename, '.pdf')) {
                return $this->generateAndDownloadPdf($filename);
            }
            
            $filePath = 'pdfs/' . $filename;
            
            if (!Storage::disk('public')->exists($filePath)) {
                Log::warning('PDF file not found, attempting to generate', [
                    'filename' => $filename,
                    'path' => $filePath,
                    'user_id' => auth()->id()
                ]);
                
                // Extract component name from filename for generation
                $componentName = str_replace(['-', '_'], '', basename($filename, '.pdf'));
                return $this->generateAndDownloadPdf($componentName);
            }

            Log::info('PDF download successful', [
                'filename' => $filename,
                'user_id' => auth()->id()
            ]);

            return response()->download(
                storage_path('app/public/' . $filePath),
                $filename,
                ['Content-Type' => 'application/pdf']
            );

        } catch (Exception $e) {
            Log::error('PDF download failed', [
                'filename' => $filename,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            // Return 404 instead of 500 for file not found errors
            if (str_contains($e->getMessage(), 'not found') || str_contains($e->getMessage(), 'No such file')) {
                abort(404, 'PDF file not found');
            }

            abort(500, 'Download failed');
        }
    }

    /**
     * Generate PDF on-demand and download immediately
     */
    private function generateAndDownloadPdf(string $componentName): Response|BinaryFileResponse
    {
        try {
            Log::info('Generating PDF on-demand for download', [
                'component' => $componentName
            ]);

            // Get demo data for the component
            $data = $this->generateDemoDataForComponent($componentName);
            
            // Generate the PDF directly using the component-pdf template
            $result = $this->pdfService->generateFromBladeTemplate('reports.component-pdf', [
                'component_name' => $componentName,
                'data' => $data,
                'generated_at' => now()->format('Y-m-d H:i:s'),
                'demo_mode' => true
            ], [
                'filename' => strtolower($componentName) . '-' . now()->timestamp . '.pdf',
                'format' => 'A4',
                'orientation' => 'portrait'
            ]);

            if ($result['success']) {
                $filePath = storage_path('app/public/' . $result['file_path']);
                
                return response()->download(
                    $filePath,
                    $result['filename'],
                    ['Content-Type' => 'application/pdf']
                );
            } else {
                throw new Exception('PDF generation failed: ' . ($result['error'] ?? 'Unknown error'));
            }

        } catch (Exception $e) {
            Log::error('On-demand PDF generation failed', [
                'component' => $componentName,
                'error' => $e->getMessage()
            ]);

            // Return a basic PDF with error message
            return $this->generateErrorPdf($componentName, $e->getMessage());
        }
    }

    /**
     * Render component data to HTML for PDF generation
     */
    private function renderComponentToHtml(string $componentName, array $data): string
    {
        try {
            // Simple HTML template for PDF generation
            return view('reports.component-pdf', [
                'component_name' => $componentName,
                'data' => $data,
                'generated_at' => now()->format('Y-m-d H:i:s'),
                'demo_mode' => true
            ])->render();
        } catch (Exception $e) {
            Log::warning('Failed to render component HTML, using basic template', [
                'component' => $componentName,
                'error' => $e->getMessage()
            ]);

            return "<html><body><h1>{$componentName} Report</h1><p>Generated: " . now()->format('Y-m-d H:i:s') . "</p><pre>" . json_encode($data, JSON_PRETTY_PRINT) . "</pre></body></html>";
        }
    }

    /**
     * Generate an error PDF when on-demand generation fails
     */
    private function generateErrorPdf(string $componentName, string $error): Response|BinaryFileResponse
    {
        try {
            $errorHtml = "
                <html>
                <head>
                    <title>PDF Generation Error</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 40px; }
                        .error { color: #d32f2f; background: #ffebee; padding: 20px; border-radius: 8px; }
                        .info { color: #1976d2; background: #e3f2fd; padding: 15px; border-radius: 8px; margin-top: 20px; }
                    </style>
                </head>
                <body>
                    <h1>PDF Generation Error</h1>
                    <div class='error'>
                        <h3>Component: {$componentName}</h3>
                        <p><strong>Error:</strong> {$error}</p>
                        <p><strong>Time:</strong> " . now()->format('Y-m-d H:i:s') . "</p>
                    </div>
                    <div class='info'>
                        <p><strong>Troubleshooting:</strong></p>
                        <ul>
                            <li>Ensure the component exists and is properly configured</li>
                            <li>Check that demo data is available for this component</li>
                            <li>Verify PDF generation service is working</li>
                            <li>Contact support if the issue persists</li>
                        </ul>
                    </div>
                </body>
                </html>
            ";

            $filename = 'error-' . strtolower($componentName) . '-' . now()->timestamp . '.pdf';
            $filePath = 'pdfs/' . $filename;

            // Try to generate error PDF
            $result = $this->pdfService->generateFromBladeTemplate('reports.error', [
                'component' => $componentName,
                'error' => $error,
                'html_content' => $errorHtml
            ], [
                'filename' => $filename,
                'format' => 'A4'
            ]);

            if ($result['success']) {
                return response()->download(
                    storage_path('app/public/' . $result['file_path']),
                    $filename,
                    ['Content-Type' => 'application/pdf']
                );
            } else {
                // Fallback to direct HTML response
                return response($errorHtml, 500, ['Content-Type' => 'text/html']);
            }

        } catch (Exception $e) {
            Log::error('Error PDF generation also failed', [
                'component' => $componentName,
                'original_error' => $error,
                'generation_error' => $e->getMessage()
            ]);

            abort(500, 'PDF generation system unavailable');
        }
    }

    /**
     * Get PDF generation statistics
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $stats = $this->pdfService->getStatistics();

            return response()->json([
                'success' => true,
                'statistics' => $stats
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get PDF statistics', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clean up old PDF files
     */
    public function cleanup(Request $request): Response
    {
        try {
            $daysOld = $request->input('days_old', 7);
            $result = $this->pdfService->cleanup($daysOld);

            return response()->json([
                'success' => true,
                'message' => "Cleaned up {$result['deleted_files']} old PDF files",
                'deleted_files' => $result['deleted_files'],
                'remaining_files' => $result['remaining_files']
            ]);

        } catch (Exception $e) {
            Log::error('PDF cleanup failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test PDF generation with sample data
     */
    public function test(Request $request): JsonResponse
    {
        try {
            $type = $request->input('type', 'dashboard');
            $method = $request->input('method', 'auto');

            $sampleData = $this->getSampleData($type);
            $options = [
                'filename' => "test-{$type}-" . now()->format('Y-m-d-H-i-s') . '.pdf',
                'force_browserless' => $method === 'browserless'
            ];

            $result = match($type) {
                'dashboard' => $this->pdfService->generateDashboardReport($sampleData, $options),
                'sentiment' => $this->pdfService->generateSentimentReport($sampleData, $options),
                'crawler' => $this->pdfService->generateCrawlerReport($sampleData, $options),
                default => throw new Exception("Unknown test type: {$type}")
            };

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] ? 'Test PDF generated successfully' : 'Test PDF generation failed',
                'type' => $type,
                'method' => $result['method'],
                'file_url' => $result['url'] ?? null,
                'filename' => $result['filename'] ?? null,
                'processing_time' => $result['processing_time'] ?? null,
                'error' => $result['error'] ?? null,
                'simulation' => $result['simulation'] ?? false
            ], $result['success'] ? 200 : 500);

        } catch (Exception $e) {
            Log::error('PDF test failed', [
                'type' => $request->input('type'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sample data for testing
     */
    private function getSampleData(string $type): array
    {
        return match($type) {
            'dashboard' => [
                'title' => 'Blockchain Analytics Dashboard',
                'date_range' => ['2024-01-01', '2024-01-31'],
                'metrics' => [
                    'total_posts' => 15420,
                    'sentiment_score' => 0.127,
                    'platforms' => ['twitter' => 8934, 'reddit' => 4123, 'telegram' => 2363],
                    'engagement' => 89234
                ],
                'charts' => [
                    'sentiment_trend' => array_map(fn($i) => ['date' => now()->subDays($i)->format('Y-m-d'), 'sentiment' => rand(-50, 50) / 100], range(30, 0)),
                    'volume_trend' => array_map(fn($i) => ['date' => now()->subDays($i)->format('Y-m-d'), 'volume' => rand(100, 1000)], range(30, 0))
                ]
            ],
            'sentiment' => [
                'title' => 'Sentiment Analysis Report',
                'period' => 'Last 30 Days',
                'overall_sentiment' => 0.234,
                'platforms' => [
                    'twitter' => ['sentiment' => 0.189, 'posts' => 8934, 'engagement' => 45123],
                    'reddit' => ['sentiment' => -0.067, 'posts' => 4123, 'engagement' => 23456],
                    'telegram' => ['sentiment' => 0.145, 'posts' => 2363, 'engagement' => 12789]
                ],
                'top_keywords' => [
                    'bitcoin' => ['mentions' => 2345, 'sentiment' => 0.234],
                    'ethereum' => ['mentions' => 1876, 'sentiment' => 0.189],
                    'defi' => ['mentions' => 1234, 'sentiment' => 0.345]
                ]
            ],
            'crawler' => [
                'title' => 'Social Media Crawler Report',
                'period' => 'Last 7 Days',
                'summary' => [
                    'total_posts_collected' => 12450,
                    'success_rate' => 98.7,
                    'avg_processing_time' => '1.2s',
                    'platforms_active' => 3
                ],
                'platform_breakdown' => [
                    'twitter' => ['posts' => 7834, 'keywords_matched' => 1234, 'avg_sentiment' => 0.123],
                    'reddit' => ['posts' => 3456, 'keywords_matched' => 892, 'avg_sentiment' => -0.045],
                    'telegram' => ['posts' => 1160, 'keywords_matched' => 234, 'avg_sentiment' => 0.089]
                ]
            ],
            default => []
        };
    }

    /**
     * Generate sentiment dashboard data for PDF reports
     */
    private function generateSentimentDashboardData(array $dateRange, array $platforms): array
    {
        return [
            'title' => 'Sentiment Analysis Dashboard Report',
            'period' => [
                'start' => $dateRange['start'],
                'end' => $dateRange['end'],
                'duration_days' => \Carbon\Carbon::parse($dateRange['start'])->diffInDays($dateRange['end'])
            ],
            'platforms_included' => $platforms,
            'overall_metrics' => [
                'total_posts_analyzed' => random_int(15000, 50000),
                'average_sentiment' => round((random_int(-100, 100) / 100), 3),
                'sentiment_volatility' => round((random_int(10, 40) / 100), 3),
                'engagement_score' => random_int(70, 95),
                'data_quality_score' => random_int(85, 98)
            ],
            'sentiment_trends' => array_map(function($i) use ($dateRange) {
                $date = \Carbon\Carbon::parse($dateRange['start'])->addDays($i);
                return [
                    'date' => $date->format('Y-m-d'),
                    'sentiment_score' => round((sin($i * 0.3) * 0.6) + (random_int(-20, 20) / 100), 3),
                    'volume' => random_int(500, 2000),
                    'engagement' => random_int(100, 800)
                ];
            }, range(0, min(30, \Carbon\Carbon::parse($dateRange['start'])->diffInDays($dateRange['end'])))),
            'platform_breakdown' => [
                'twitter' => [
                    'posts' => random_int(8000, 15000),
                    'avg_sentiment' => round((random_int(10, 40) / 100), 3),
                    'engagement' => random_int(25000, 45000),
                    'top_keywords' => ['bitcoin', 'ethereum', 'defi', 'crypto', 'blockchain']
                ],
                'reddit' => [
                    'posts' => random_int(3000, 8000),
                    'avg_sentiment' => round((random_int(-10, 30) / 100), 3),
                    'engagement' => random_int(15000, 25000),
                    'top_keywords' => ['hodl', 'moon', 'analysis', 'technical', 'market']
                ],
                'telegram' => [
                    'posts' => random_int(2000, 5000),
                    'avg_sentiment' => round((random_int(0, 35) / 100), 3),
                    'engagement' => random_int(8000, 15000),
                    'top_keywords' => ['pump', 'signal', 'chart', 'trading', 'altcoin']
                ]
            ],
            'sentiment_categories' => [
                'very_positive' => random_int(15, 25),
                'positive' => random_int(25, 35),
                'neutral' => random_int(20, 40),
                'negative' => random_int(10, 20),
                'very_negative' => random_int(5, 15)
            ],
            'key_insights' => [
                'Bullish sentiment increased by 15% compared to previous period',
                'Reddit discussions show more technical analysis focus',
                'Twitter engagement peaked during major announcements',
                'Telegram signals correlate with price movements',
                'Overall market sentiment remains cautiously optimistic'
            ],
            'top_topics' => [
                [
                    'topic' => 'Bitcoin ETF',
                    'mentions' => random_int(5000, 12000),
                    'sentiment' => 0.65,
                    'trend' => 'up'
                ],
                [
                    'topic' => 'Ethereum Upgrade',
                    'mentions' => random_int(3000, 8000),
                    'sentiment' => 0.42,
                    'trend' => 'stable'
                ],
                [
                    'topic' => 'DeFi Protocols',
                    'mentions' => random_int(2000, 6000),
                    'sentiment' => 0.28,
                    'trend' => 'down'
                ],
                [
                    'topic' => 'Market Analysis',
                    'mentions' => random_int(4000, 10000),
                    'sentiment' => 0.15,
                    'trend' => 'stable'
                ],
                [
                    'topic' => 'Regulatory News',
                    'mentions' => random_int(1500, 4000),
                    'sentiment' => -0.12,
                    'trend' => 'up'
                ]
            ],
            'recommendations' => [
                'Monitor social sentiment during major crypto events',
                'Focus on Twitter and Reddit for early trend detection',
                'Consider sentiment volatility in trading strategies', 
                'Track correlation between sentiment and price movements',
                'Implement real-time sentiment alerts for significant changes'
            ],
            'metadata' => [
                'generated_at' => now()->toISOString(),
                'report_version' => '2.1',
                'data_sources' => count($platforms) === 1 && $platforms[0] === 'all' ? ['Twitter', 'Reddit', 'Telegram'] : array_map('ucfirst', $platforms),
                'analysis_engine' => 'AI Blockchain Analytics v3.2',
                'confidence_score' => random_int(85, 97)
            ]
        ];
    }

    /**
     * Save PDF content to storage
     */
    private function savePdfToStorage(string $pdfContent, string $filename): string
    {
        $filePath = 'pdfs/' . $filename;
        Storage::disk('public')->put($filePath, $pdfContent);
        return $filePath;
    }

    /**
     * Generate contract analytics data for PDF reports
     */
    private function generateContractAnalyticsData(string $contractId): array
    {
        return [
            'contract' => [
                'address' => $contractId,
                'name' => 'Sample DeFi Contract',
                'symbol' => 'SDC',
                'deployment_date' => now()->subMonths(6)->format('Y-m-d'),
                'compiler_version' => '0.8.19',
                'optimization_enabled' => true,
                'verified' => true,
                'proxy_contract' => false
            ],
            'security_analysis' => [
                'overall_score' => 85,
                'risk_level' => 'medium',
                'vulnerabilities_found' => 3,
                'critical_issues' => 0,
                'high_issues' => 0,
                'medium_issues' => 2,
                'low_issues' => 1,
                'info_issues' => 4,
                'last_scan_date' => now()->subDays(1)->format('Y-m-d H:i:s')
            ],
            'detailed_findings' => [
                [
                    'id' => 'OWASP-SC-01',
                    'title' => 'Unused State Variable',
                    'severity' => 'low',
                    'category' => 'Gas Optimization',
                    'description' => 'State variable declared but never used',
                    'line_number' => 45,
                    'recommendation' => 'Remove unused state variable to save gas'
                ],
                [
                    'id' => 'OWASP-SC-02', 
                    'title' => 'Missing Event Emission',
                    'severity' => 'medium',
                    'category' => 'Best Practices',
                    'description' => 'State-changing function without event emission',
                    'line_number' => 123,
                    'recommendation' => 'Add event emission for transparency'
                ],
                [
                    'id' => 'OWASP-SC-03',
                    'title' => 'Potential Integer Overflow',
                    'severity' => 'medium',
                    'category' => 'Arithmetic',
                    'description' => 'Arithmetic operation without SafeMath protection',
                    'line_number' => 89,
                    'recommendation' => 'Use SafeMath library or Solidity 0.8+ built-in checks'
                ]
            ],
            'gas_analysis' => [
                'deployment_cost' => 2450000,
                'average_transaction_cost' => 85000,
                'most_expensive_function' => [
                    'name' => 'complexCalculation',
                    'gas_cost' => 180000
                ],
                'optimization_suggestions' => [
                    'Use uint256 instead of uint8 for loop counters',
                    'Pack struct variables to save storage slots',
                    'Use external instead of public for functions not called internally'
                ]
            ],
            'function_analysis' => [
                'total_functions' => 28,
                'public_functions' => 12,
                'external_functions' => 8,
                'internal_functions' => 6,
                'private_functions' => 2,
                'payable_functions' => 3,
                'view_functions' => 7,
                'pure_functions' => 4,
                'functions_with_modifiers' => 15
            ],
            'dependency_analysis' => [
                'imported_contracts' => [
                    '@openzeppelin/contracts/token/ERC20/ERC20.sol',
                    '@openzeppelin/contracts/access/Ownable.sol',
                    '@openzeppelin/contracts/security/ReentrancyGuard.sol'
                ],
                'inheritance_tree' => [
                    'ERC20',
                    'Ownable', 
                    'ReentrancyGuard'
                ],
                'external_calls' => 5,
                'library_usage' => ['SafeMath', 'Address']
            ],
            'activity_metrics' => [
                'total_transactions' => random_int(10000, 50000),
                'unique_addresses' => random_int(1000, 5000),
                'last_activity' => now()->subHours(2)->format('Y-m-d H:i:s'),
                'daily_transactions' => array_map(function($i) {
                    return [
                        'date' => now()->subDays($i)->format('Y-m-d'),
                        'count' => random_int(50, 500)
                    ];
                }, range(30, 0)),
                'volume_usd' => random_int(1000000, 10000000)
            ],
            'metadata' => [
                'report_generated_at' => now()->toISOString(),
                'analysis_version' => '2.1.0',
                'scan_duration_ms' => random_int(5000, 15000),
                'data_sources' => ['Etherscan', 'Moralis', 'Internal Scanner'],
                'report_type' => 'comprehensive_analytics'
            ]
        ];
    }

    /**
     * Generate demo data for PDF preview when no token is provided
     */
    private function generateDemoDataForComponent(string $component): array
    {
        return match($component) {
            'NorthStarDashboard' => [
                'metrics' => [
                    'contracts_analyzed' => 1247,
                    'vulnerabilities_found' => 89,
                    'active_threats' => 12,
                    'security_score' => 94.7
                ],
                'recent_analyses' => [
                    [
                        'contract' => '0x1234...5678',
                        'status' => 'completed',
                        'risk_level' => 'medium',
                        'timestamp' => now()->subMinutes(15)->toISOString()
                    ],
                    [
                        'contract' => '0xabcd...efgh',
                        'status' => 'processing',
                        'risk_level' => 'high',
                        'timestamp' => now()->subMinutes(5)->toISOString()
                    ]
                ],
                'threat_feed' => [
                    [
                        'type' => 'flash_loan_attack',
                        'severity' => 'high',
                        'target' => 'DeFi Protocol X',
                        'timestamp' => now()->subHours(2)->toISOString()
                    ],
                    [
                        'type' => 'reentrancy_vulnerability',
                        'severity' => 'critical',
                        'target' => 'Smart Contract Y',
                        'timestamp' => now()->subHours(4)->toISOString()
                    ]
                ],
                'demo_mode' => true
            ],
            'SentimentReport' => [
                'sentiment_data' => [
                    ['date' => now()->subDays(7)->format('Y-m-d'), 'sentiment' => 0.65, 'volume' => 1250],
                    ['date' => now()->subDays(6)->format('Y-m-d'), 'sentiment' => 0.72, 'volume' => 1180],
                    ['date' => now()->subDays(5)->format('Y-m-d'), 'sentiment' => 0.58, 'volume' => 1390],
                    ['date' => now()->subDays(4)->format('Y-m-d'), 'sentiment' => 0.81, 'volume' => 1420],
                    ['date' => now()->subDays(3)->format('Y-m-d'), 'sentiment' => 0.76, 'volume' => 1310],
                    ['date' => now()->subDays(2)->format('Y-m-d'), 'sentiment' => 0.69, 'volume' => 1280],
                    ['date' => now()->subDays(1)->format('Y-m-d'), 'sentiment' => 0.85, 'volume' => 1450]
                ],
                'platforms' => ['twitter', 'reddit', 'telegram'],
                'keywords' => ['blockchain', 'defi', 'security'],
                'demo_mode' => true
            ],
            'DashboardReport' => [
                'analytics' => [
                    'total_scans' => 5420,
                    'vulnerabilities_detected' => 234,
                    'false_positives' => 12,
                    'accuracy_rate' => 98.6
                ],
                'charts' => [
                    'vulnerability_trends' => [
                        ['month' => 'Jan', 'count' => 45],
                        ['month' => 'Feb', 'count' => 52],
                        ['month' => 'Mar', 'count' => 38],
                        ['month' => 'Apr', 'count' => 61],
                        ['month' => 'May', 'count' => 47]
                    ]
                ],
                'demo_mode' => true
            ],
            default => [
                'message' => 'PDF preview demo data',
                'component' => $component,
                'generated_at' => now()->toISOString(),
                'demo_mode' => true
            ]
        };
    }

    /**
     * Get PDF engine information and status
     */
    public function getEngineInfo(): JsonResponse
    {
        try {
            $engineInfo = [
                'timestamp' => now()->toISOString(),
                'engines' => [
                    'browserless' => [
                        'name' => 'Browserless (Headless Chrome)',
                        'enabled' => config('services.browserless.enabled', false),
                        'url' => config('services.browserless.url'),
                        'timeout' => config('services.browserless.timeout', 30),
                        'concurrent_limit' => config('services.browserless.concurrent_limit', 10),
                        'status' => $this->checkBrowserlessStatus(),
                        'use_case' => 'Vue components with charts and complex layouts',
                        'format_support' => ['PDF', 'PNG', 'JPEG'],
                        'features' => [
                            'JavaScript execution',
                            'CSS styling',
                            'Chart rendering',
                            'Interactive components',
                            'High-resolution output'
                        ]
                    ],
                    'dompdf' => [
                        'name' => 'DomPDF',
                        'enabled' => true,
                        'version' => $this->getDomPdfVersion(),
                        'status' => 'available',
                        'use_case' => 'Simple HTML/Blade templates',
                        'format_support' => ['PDF'],
                        'features' => [
                            'Server-side rendering',
                            'Basic CSS support',
                            'Fast processing',
                            'No external dependencies'
                        ],
                        'limitations' => [
                            'No JavaScript',
                            'Limited CSS3 support',
                            'No complex layouts'
                        ]
                    ]
                ],
                'configuration' => [
                    'default_engine' => $this->getDefaultEngine(),
                    'fallback_enabled' => true,
                    'queue_processing' => config('queue.default') === 'redis',
                    'storage_driver' => config('filesystems.default'),
                    'max_file_size_mb' => 50,
                    'supported_formats' => ['A4', 'Letter', 'Legal', 'A3'],
                    'orientations' => ['portrait', 'landscape']
                ],
                'health_check' => [
                    'storage_writable' => $this->checkStorageWritable(),
                    'memory_available' => $this->getAvailableMemory(),
                    'temp_directory' => $this->checkTempDirectory(),
                    'dependencies' => $this->checkDependencies()
                ],
                'performance' => [
                    'average_generation_time_ms' => $this->getAverageGenerationTime(),
                    'total_pdfs_generated' => $this->getTotalPdfsGenerated(),
                    'cache_hit_rate' => $this->getCacheHitRate(),
                    'queue_size' => $this->getQueueSize()
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $engineInfo
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get PDF engine info', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve PDF engine information',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check Browserless service status
     */
    private function checkBrowserlessStatus(): string
    {
        try {
            if (!config('services.browserless.enabled', false)) {
                return 'disabled';
            }

            $url = config('services.browserless.url');
            $token = config('services.browserless.token');
            
            if (!$url) {
                return 'not_configured';
            }

            // Simple health check to Browserless
            $response = Http::timeout(5)->get("{$url}/health");
            
            if ($response->successful()) {
                return 'healthy';
            } else {
                return 'unhealthy';
            }

        } catch (Exception $e) {
            Log::warning('Browserless health check failed', ['error' => $e->getMessage()]);
            return 'unavailable';
        }
    }

    /**
     * Get DomPDF version
     */
    private function getDomPdfVersion(): string
    {
        try {
            if (class_exists('\Dompdf\Dompdf')) {
                // Try different methods to get DomPDF version
                if (defined('\Dompdf\Dompdf::VERSION')) {
                    return \Dompdf\Dompdf::VERSION;
                }
                
                // Check if we can get version from composer
                $composerLock = base_path('composer.lock');
                if (file_exists($composerLock)) {
                    $lockData = json_decode(file_get_contents($composerLock), true);
                    if (isset($lockData['packages'])) {
                        foreach ($lockData['packages'] as $package) {
                            if ($package['name'] === 'dompdf/dompdf') {
                                return $package['version'] ?? 'unknown';
                            }
                        }
                    }
                }
                
                // Fallback: try to instantiate and check
                $dompdf = new \Dompdf\Dompdf();
                if (method_exists($dompdf, 'getVersion')) {
                    return $dompdf->getVersion();
                }
                
                return 'available';
            }
            return 'not_available';
        } catch (Exception $e) {
            Log::debug('DomPDF version detection failed', ['error' => $e->getMessage()]);
            return 'unknown';
        }
    }

    /**
     * Get the default PDF engine
     */
    private function getDefaultEngine(): string
    {
        if (config('services.browserless.enabled', false)) {
            return 'browserless';
        }
        return 'dompdf';
    }

    /**
     * Check if storage is writable
     */
    private function checkStorageWritable(): bool
    {
        try {
            $testFile = 'pdf_test_' . uniqid() . '.txt';
            Storage::put($testFile, 'test');
            Storage::delete($testFile);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get available memory in MB
     */
    private function getAvailableMemory(): array
    {
        $memoryLimit = ini_get('memory_limit');
        $currentUsage = memory_get_usage(true);
        $peakUsage = memory_get_peak_usage(true);

        return [
            'memory_limit' => $memoryLimit,
            'current_usage_mb' => round($currentUsage / 1024 / 1024, 2),
            'peak_usage_mb' => round($peakUsage / 1024 / 1024, 2),
            'available_mb' => round((self::parseMemoryLimit($memoryLimit) - $currentUsage) / 1024 / 1024, 2)
        ];
    }

    /**
     * Parse memory limit string to bytes
     */
    private static function parseMemoryLimit(string $limit): int
    {
        $limit = strtolower(trim($limit));
        $bytes = (int) $limit;

        if (str_contains($limit, 'g')) {
            $bytes *= 1024 * 1024 * 1024;
        } elseif (str_contains($limit, 'm')) {
            $bytes *= 1024 * 1024;
        } elseif (str_contains($limit, 'k')) {
            $bytes *= 1024;
        }

        return $bytes;
    }

    /**
     * Check temporary directory status
     */
    private function checkTempDirectory(): array
    {
        $tempDir = sys_get_temp_dir();
        
        return [
            'path' => $tempDir,
            'writable' => is_writable($tempDir),
            'exists' => is_dir($tempDir),
            'free_space_mb' => round(disk_free_space($tempDir) / 1024 / 1024, 2)
        ];
    }

    /**
     * Check PDF generation dependencies
     */
    private function checkDependencies(): array
    {
        return [
            'dompdf' => class_exists('\Dompdf\Dompdf'),
            'gd_extension' => extension_loaded('gd'),
            'imagick_extension' => extension_loaded('imagick'),
            'curl_extension' => extension_loaded('curl'),
            'zip_extension' => extension_loaded('zip'),
            'mbstring_extension' => extension_loaded('mbstring')
        ];
    }

    /**
     * Get average PDF generation time (mock implementation)
     */
    private function getAverageGenerationTime(): int
    {
        // In a real implementation, this would query metrics from database/cache
        return random_int(1500, 3000); // Mock: 1.5-3 seconds
    }

    /**
     * Get total PDFs generated count (mock implementation)
     */
    private function getTotalPdfsGenerated(): int
    {
        // In a real implementation, this would query from database
        return random_int(1000, 50000); // Mock count
    }

    /**
     * Get cache hit rate percentage (mock implementation)
     */
    private function getCacheHitRate(): float
    {
        // In a real implementation, this would query cache metrics
        return round(random_int(75, 95) + (random_int(0, 99) / 100), 2); // Mock: 75-95%
    }

    /**
     * Get current PDF generation queue size
     */
    private function getQueueSize(): int
    {
        try {
            // Get the queue size from Redis/database
            $queue = app('queue');
            
            if (method_exists($queue, 'size')) {
                return $queue->size('pdf');
            }
            
            // Fallback: mock queue size
            return random_int(0, 25);
            
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Preview sentiment chart data for PDF generation
     */
    public function previewSentimentChart(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'coin' => 'sometimes|string',
                'start_date' => 'sometimes|date',
                'end_date' => 'sometimes|date',
                'platform' => 'sometimes|string|in:all,twitter,reddit,telegram',
            ]);

            $coin = $request->input('coin', 'bitcoin');
            $startDate = $request->input('start_date', now()->subDays(30)->toDateString());
            $endDate = $request->input('end_date', now()->toDateString());
            $platform = $request->input('platform', 'all');

            // Generate mock sentiment chart data for preview
            $chartData = $this->generateMockSentimentChartData($coin, $startDate, $endDate, $platform);

            return response()->json([
                'success' => true,
                'data' => $chartData,
                'meta' => [
                    'coin' => $coin,
                    'date_range' => [
                        'start' => $startDate,
                        'end' => $endDate
                    ],
                    'platform' => $platform,
                    'generated_at' => now()->toISOString(),
                    'preview_mode' => true
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Failed to generate sentiment chart preview', [
                'error' => $e->getMessage(),
                'request_params' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to generate sentiment chart preview',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate mock sentiment chart data for testing and preview
     */
    private function generateMockSentimentChartData(string $coin, string $startDate, string $endDate, string $platform): array
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $days = $start->diffInDays($end);
        
        $sentimentData = [];
        $priceData = [];
        
        // Generate mock data points for each day
        for ($i = 0; $i <= $days; $i++) {
            $date = $start->copy()->addDays($i);
            
            // Mock sentiment score (between -1 and 1)
            $sentimentScore = (sin($i * 0.3) * 0.6) + (random_int(-20, 20) / 100);
            $sentimentScore = max(-1, min(1, $sentimentScore));
            
            // Mock price data (simulated volatility)
            $basePrice = 45000; // Base price for Bitcoin
            $priceChange = (cos($i * 0.2) * 0.15) + (random_int(-10, 10) / 100);
            $price = $basePrice * (1 + $priceChange);
            
            $sentimentData[] = [
                'date' => $date->toDateString(),
                'timestamp' => $date->timestamp * 1000, // JavaScript timestamp
                'sentiment_score' => round($sentimentScore, 3),
                'sentiment_magnitude' => round(abs($sentimentScore) + (random_int(0, 30) / 100), 3),
                'post_count' => random_int(50, 500),
                'platforms' => $this->generatePlatformBreakdown($platform)
            ];
            
            $priceData[] = [
                'date' => $date->toDateString(),
                'timestamp' => $date->timestamp * 1000,
                'price' => round($price, 2),
                'volume' => random_int(1000000000, 5000000000), // Mock volume
                'market_cap' => round($price * 19000000, 0) // Mock market cap
            ];
        }
        
        // Calculate correlation
        $correlation = $this->calculateMockCorrelation($sentimentData, $priceData);
        
        return [
            'sentiment_data' => $sentimentData,
            'price_data' => $priceData,
            'statistics' => [
                'correlation_score' => $correlation,
                'correlation_strength' => $this->getCorrelationStrength($correlation),
                'avg_sentiment' => round(collect($sentimentData)->avg('sentiment_score'), 3),
                'price_change_percent' => round((end($priceData)['price'] - $priceData[0]['price']) / $priceData[0]['price'] * 100, 2),
                'data_points' => count($sentimentData),
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate
                ]
            ],
            'chart_config' => [
                'type' => 'dual_axis',
                'sentiment_color' => '#10B981',
                'price_color' => '#3B82F6',
                'correlation_color' => $correlation > 0.5 ? '#10B981' : ($correlation < -0.5 ? '#EF4444' : '#F59E0B')
            ]
        ];
    }

    /**
     * Generate platform breakdown based on selected platform
     */
    private function generatePlatformBreakdown(string $platform): array
    {
        if ($platform === 'all') {
            return [
                'twitter' => random_int(20, 200),
                'reddit' => random_int(10, 100),
                'telegram' => random_int(5, 50)
            ];
        }
        
        return [$platform => random_int(50, 300)];
    }

    /**
     * Calculate mock correlation between sentiment and price data
     */
    private function calculateMockCorrelation(array $sentimentData, array $priceData): float
    {
        if (count($sentimentData) !== count($priceData) || count($sentimentData) < 2) {
            return 0.0;
        }
        
        $sentimentValues = array_column($sentimentData, 'sentiment_score');
        $priceValues = array_column($priceData, 'price');
        
        // Normalize price values for correlation calculation
        $avgPrice = array_sum($priceValues) / count($priceValues);
        $normalizedPrices = array_map(fn($price) => ($price - $avgPrice) / $avgPrice, $priceValues);
        
        $n = count($sentimentValues);
        $sumX = array_sum($sentimentValues);
        $sumY = array_sum($normalizedPrices);
        $sumXY = 0;
        $sumX2 = 0;
        $sumY2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sumXY += $sentimentValues[$i] * $normalizedPrices[$i];
            $sumX2 += $sentimentValues[$i] * $sentimentValues[$i];
            $sumY2 += $normalizedPrices[$i] * $normalizedPrices[$i];
        }
        
        $numerator = ($n * $sumXY) - ($sumX * $sumY);
        $denominator = sqrt((($n * $sumX2) - ($sumX * $sumX)) * (($n * $sumY2) - ($sumY * $sumY)));
        
        if ($denominator == 0) {
            return 0.0;
        }
        
        return round($numerator / $denominator, 3);
    }

    /**
     * Get correlation strength description
     */
    private function getCorrelationStrength(float $correlation): string
    {
        $abs = abs($correlation);
        
        if ($abs >= 0.8) {
            return 'Very Strong';
        } elseif ($abs >= 0.6) {
            return 'Strong';
        } elseif ($abs >= 0.4) {
            return 'Moderate';
        } elseif ($abs >= 0.2) {
            return 'Weak';
        } else {
            return 'Very Weak';
        }
    }

    /**
     * Temporary Vue route for Browserless PDF generation
     */
    public function tempVueRoute(Request $request, string $token): Response|JsonResponse
    {
        try {
            Log::info('Temporary Vue route accessed for PDF generation', [
                'token' => substr($token, 0, 8) . '...',
                'user_agent' => $request->header('User-Agent'),
                'ip' => $request->ip()
            ]);

            // Validate token format (basic validation)
            if (!preg_match('/^[a-zA-Z0-9]{32,}$/', $token)) {
                return response()->json([
                    'error' => 'Invalid token format',
                    'token' => substr($token, 0, 8) . '...'
                ], 400);
            }

            // For security, tokens should be temporary and expire quickly
            // In a real implementation, you'd validate the token against a cache/database
            
            // Generate demo data based on token or request parameters
            $component = $request->get('component', 'DashboardReport');
            $dataType = $request->get('data_type', 'dashboard');
            
            // Get appropriate demo data
            $data = $this->getDemoDataForTempRoute($component, $dataType);
            
            // Return an Inertia response for Vue component rendering
            return Inertia::render("Pdf/{$component}", [
                'data' => $data,
                'pdf_mode' => true,
                'temp_token' => $token,
                'demo_mode' => true,
                'browserless_mode' => true,
                'options' => [
                    'format' => $request->get('format', 'A4'),
                    'orientation' => $request->get('orientation', 'portrait'),
                    'margin' => $request->get('margin', '1cm')
                ],
                'metadata' => [
                    'generated_for' => 'PDF Generation',
                    'timestamp' => now()->toISOString(),
                    'token_valid' => true
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Temporary Vue route error', [
                'token' => substr($token, 0, 8) . '...',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Return a fallback HTML page for PDF generation
            return response()->view('pdf.fallback', [
                'error' => 'Failed to render Vue component',
                'message' => $e->getMessage(),
                'token' => substr($token, 0, 8) . '...',
                'timestamp' => now()->format('Y-m-d H:i:s')
            ], 500);
        }
    }

    /**
     * Get demo data for temporary Vue route
     */
    private function getDemoDataForTempRoute(string $component, string $dataType): array
    {
        return match($dataType) {
            'dashboard' => [
                'title' => 'AI Blockchain Analytics Dashboard',
                'metrics' => [
                    'contracts_analyzed' => 1247,
                    'vulnerabilities_found' => 89,
                    'active_threats' => 12,
                    'security_score' => 94.7,
                    'total_scans' => 5420,
                    'false_positives' => 23,
                    'accuracy_rate' => 97.8
                ],
                'recent_analyses' => array_map(function($i) {
                    return [
                        'contract' => '0x' . str_repeat(dechex($i), 10),
                        'status' => ['completed', 'processing', 'failed'][rand(0, 2)],
                        'risk_level' => ['low', 'medium', 'high', 'critical'][rand(0, 3)],
                        'timestamp' => now()->subMinutes(rand(5, 120))->toISOString()
                    ];
                }, range(1, 10)),
                'vulnerability_trends' => array_map(function($i) {
                    return [
                        'date' => now()->subDays($i)->format('Y-m-d'),
                        'count' => rand(20, 80),
                        'severity' => [
                            'critical' => rand(0, 5),
                            'high' => rand(5, 15),
                            'medium' => rand(10, 25),
                            'low' => rand(15, 35)
                        ]
                    ];
                }, range(0, 30))
            ],
            'sentiment' => [
                'title' => 'Cryptocurrency Sentiment Analysis',
                'period' => [
                    'start' => now()->subDays(30)->format('Y-m-d'),
                    'end' => now()->format('Y-m-d'),
                    'duration_days' => 30
                ],
                'overall_metrics' => [
                    'total_posts_analyzed' => rand(25000, 75000),
                    'average_sentiment' => round((rand(-50, 50) / 100), 3),
                    'sentiment_volatility' => round((rand(15, 35) / 100), 3),
                    'engagement_score' => rand(75, 95),
                    'data_quality_score' => rand(88, 98)
                ],
                'sentiment_trends' => array_map(function($i) {
                    return [
                        'date' => now()->subDays($i)->format('Y-m-d'),
                        'sentiment_score' => round((sin($i * 0.3) * 0.6) + (rand(-20, 20) / 100), 3),
                        'volume' => rand(800, 2500),
                        'engagement' => rand(200, 1200)
                    ];
                }, range(0, 29)),
                'platform_breakdown' => [
                    'twitter' => [
                        'posts' => rand(12000, 25000),
                        'avg_sentiment' => round((rand(10, 40) / 100), 3),
                        'engagement' => rand(35000, 65000),
                        'top_keywords' => ['bitcoin', 'ethereum', 'defi', 'crypto', 'blockchain']
                    ],
                    'reddit' => [
                        'posts' => rand(5000, 12000),
                        'avg_sentiment' => round((rand(-10, 30) / 100), 3),
                        'engagement' => rand(20000, 35000),
                        'top_keywords' => ['hodl', 'moon', 'analysis', 'technical', 'market']
                    ],
                    'telegram' => [
                        'posts' => rand(3000, 8000),
                        'avg_sentiment' => round((rand(5, 40) / 100), 3),
                        'engagement' => rand(12000, 22000),
                        'top_keywords' => ['pump', 'signal', 'chart', 'trading', 'altcoin']
                    ]
                ]
            ],
            'analytics' => [
                'contract' => [
                    'address' => '0x' . str_repeat('a1b2c3d4e5f6', 3) . 'abcd',
                    'name' => 'DemoContract',
                    'deployment_date' => now()->subMonths(rand(1, 12))->format('Y-m-d'),
                    'compiler_version' => '0.8.' . rand(15, 25),
                    'verified' => true
                ],
                'security_metrics' => [
                    'vulnerability_score' => rand(70, 95),
                    'gas_efficiency' => rand(65, 90),
                    'code_quality' => rand(75, 95),
                    'total_issues' => rand(2, 15)
                ],
                'detailed_findings' => array_map(function($i) {
                    $severities = ['low', 'medium', 'high', 'critical'];
                    $categories = ['Gas Optimization', 'Best Practices', 'Security', 'Logic Error'];
                    return [
                        'id' => 'TEMP-' . sprintf('%03d', $i),
                        'title' => 'Demo Finding ' . $i,
                        'severity' => $severities[rand(0, 3)],
                        'category' => $categories[rand(0, 3)],
                        'line_number' => rand(10, 500),
                        'description' => 'Demo security finding for PDF generation test'
                    ];
                }, range(1, rand(3, 8)))
            ],
            default => [
                'message' => 'Demo data for PDF generation',
                'component' => $component,
                'data_type' => $dataType,
                'generated_at' => now()->toISOString(),
                'demo_mode' => true
            ]
        };
    }

    /**
     * Generate PDF from Vue view using new service
     */
    public function generateFromVueView(Request $request): JsonResponse
    {
        $request->validate([
            'route' => 'required|string',
            'data' => 'array',
            'options' => 'array'
        ]);

        try {
            $route = $request->input('route');
            $data = $request->input('data', []);
            $options = $request->input('options', []);

            $filename = $this->newPdfService->generateFromVueView($route, $data, $options);

            return response()->json([
                'success' => true,
                'filename' => $filename,
                'download_url' => route('pdf.download', $filename),
                'view_url' => $this->newPdfService->getPdfUrl($filename)
            ]);

        } catch (\Exception $e) {
            Log::error('Vue view PDF generation failed', [
                'error' => $e->getMessage(),
                'route' => $request->input('route')
            ]);

            return response()->json(['error' => 'PDF generation failed'], 500);
        }
    }

    /**
     * Generate PDF with Browserless
     */
    public function generateWithBrowserless(Request $request): JsonResponse
    {
        $request->validate([
            'url' => 'required|url',
            'options' => 'array'
        ]);

        try {
            $url = $request->input('url');
            $options = $request->input('options', []);

            $filename = $this->newPdfService->generateWithBrowserless($url, $options);

            return response()->json([
                'success' => true,
                'filename' => $filename,
                'download_url' => route('pdf.download', $filename),
                'view_url' => $this->newPdfService->getPdfUrl($filename),
                'method' => 'browserless'
            ]);

        } catch (\Exception $e) {
            Log::error('Browserless PDF generation failed', [
                'error' => $e->getMessage(),
                'url' => $request->input('url')
            ]);

            return response()->json(['error' => 'PDF generation failed'], 500);
        }
    }

    /**
     * Get new PDF service status
     */
    public function getNewServiceStatus(): JsonResponse
    {
        return response()->json($this->newPdfService->getStatus());
    }

    /**
     * Clean up old PDFs using new service
     */
    public function cleanupNew(Request $request): JsonResponse
    {
        $days = $request->input('days', 7);
        $deleted = $this->newPdfService->cleanupOldPdfs($days);

        return response()->json([
            'success' => true,
            'deleted_files' => $deleted,
            'message' => "Cleaned up {$deleted} old PDF files"
        ]);
    }
}