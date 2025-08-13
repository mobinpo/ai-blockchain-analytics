<?php

declare(strict_types=1);

namespace App\Console\Commands;

// use App\Services\UnifiedVuePdfService; // Disabled temporarily
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Exception;

class TestVuePdfGenerationCommand extends Command
{
    protected $signature = 'pdf:test-vue-generation
                           {--component= : Test specific component (SentimentDashboard, AnalyticsDashboard, TestComponent)}
                           {--method= : Force method (browserless, dompdf)}
                           {--batch : Test batch generation}
                           {--all : Test all components and methods}';

    protected $description = 'Test Vue PDF generation with both Browserless and DomPDF approaches';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('🧪 Testing Vue PDF Generation System');
        $this->newLine();

        try {
            // Check service status first
            $this->checkServiceStatus();

            if ($this->option('all')) {
                return $this->runAllTests();
            }

            if ($this->option('batch')) {
                return $this->testBatchGeneration();
            }

            $component = $this->option('component') ?: 'TestComponent';
            $method = $this->option('method');

            return $this->testSingleComponent($component, $method);

        } catch (Exception $e) {
            $this->error("❌ Test failed: {$e->getMessage()}");
            return 1;
        }
    }

    private function checkServiceStatus(): void
    {
        $this->info('🔍 Checking service status...');
        
        try {
            $status = $this->pdfService->getServiceStatus();
            
            $this->line("   Service: {$status['service']} v{$status['version']}");
            $this->line("   Browserless: " . ($status['browserless']['enabled'] ? '✅ Enabled' : '❌ Disabled'));
            $this->line("   DomPDF: " . ($status['dompdf']['available'] ? '✅ Available' : '❌ Not available'));
            $this->line("   Storage: " . ($status['storage']['writable'] ? '✅ Writable' : '❌ Not writable'));
            
            if ($status['browserless']['enabled'] && !$status['browserless']['healthy']) {
                $this->warn('⚠️  Browserless service is enabled but not healthy');
            }
            
            $this->newLine();
            
        } catch (Exception $e) {
            $this->warn("⚠️  Could not check service status: {$e->getMessage()}");
        }
    }

    private function runAllTests(): int
    {
        $this->info('🚀 Running comprehensive PDF generation tests...');
        $this->newLine();

        $components = ['TestComponent', 'SentimentDashboard', 'AnalyticsDashboard'];
        $methods = ['auto', 'dompdf', 'browserless'];
        
        $results = [];
        $totalTests = count($components) * count($methods);
        $passed = 0;
        $failed = 0;

        foreach ($components as $component) {
            foreach ($methods as $method) {
                $this->line("Testing {$component} with {$method} method...");
                
                try {
                    $result = $this->generateTestPdf($component, $method);
                    
                    if ($result['success']) {
                        $this->info("   ✅ Success - {$result['method']} ({$result['processing_time']}s, {$result['size_formatted']})");
                        $passed++;
                    } else {
                        $this->error("   ❌ Failed - " . ($result['error'] ?? 'Unknown error'));
                        $failed++;
                    }
                    
                    $results[] = array_merge($result, [
                        'component' => $component,
                        'requested_method' => $method
                    ]);
                    
                } catch (Exception $e) {
                    $this->error("   ❌ Exception - {$e->getMessage()}");
                    $failed++;
                }
                
                // Small delay between tests
                usleep(500000); // 0.5 seconds
            }
            $this->newLine();
        }

        // Test batch generation
        $this->line('Testing batch generation...');
        try {
            $batchResult = $this->testBatchGeneration(false);
            if ($batchResult === 0) {
                $this->info('   ✅ Batch generation successful');
                $passed++;
            } else {
                $this->error('   ❌ Batch generation failed');
                $failed++;
            }
            $totalTests++;
        } catch (Exception $e) {
            $this->error("   ❌ Batch test exception - {$e->getMessage()}");
            $failed++;
            $totalTests++;
        }

        // Display summary
        $this->newLine();
        $this->info('📊 Test Summary');
        $this->line("   Total tests: {$totalTests}");
        $this->line("   Passed: {$passed}");
        $this->line("   Failed: {$failed}");
        $this->line("   Success rate: " . round(($passed / $totalTests) * 100, 1) . "%");

        return $failed > 0 ? 1 : 0;
    }

    private function testSingleComponent(string $component, ?string $method): int
    {
        $this->info("🧪 Testing {$component} component" . ($method ? " with {$method} method" : ''));
        $this->newLine();

        try {
            $result = $this->generateTestPdf($component, $method);
            
            if ($result['success']) {
                $this->displaySuccessResult($result);
                return 0;
            } else {
                $this->displayFailureResult($result);
                return 1;
            }
            
        } catch (Exception $e) {
            $this->error("❌ PDF generation failed: {$e->getMessage()}");
            return 1;
        }
    }

    private function testBatchGeneration(bool $displayOutput = true): int
    {
        if ($displayOutput) {
            $this->info('🧪 Testing batch PDF generation...');
            $this->newLine();
        }

        try {
            $components = [
                [
                    'component' => 'TestComponent',
                    'props' => ['title' => 'Batch Test 1', 'content' => 'First component in batch'],
                    'options' => ['filename' => 'batch-test-1.pdf']
                ],
                [
                    'component' => 'SentimentDashboard', 
                    'props' => [
                        'timeframe' => '7d',
                        'positive_count' => 150,
                        'negative_count' => 75,
                        'neutral_count' => 200
                    ],
                    'options' => ['filename' => 'batch-sentiment-dashboard.pdf']
                ],
                [
                    'component' => 'AnalyticsDashboard',
                    'props' => [
                        'contract_address' => '0x1234567890123456789012345678901234567890',
                        'analysis_type' => 'comprehensive',
                        'security_score' => 85,
                        'gas_efficiency' => 92
                    ],
                    'options' => ['filename' => 'batch-analytics-dashboard.pdf']
                ]
            ];

            $globalOptions = [
                'delay_ms' => 1000,
                'batch_id' => 'test_batch_' . now()->timestamp
            ];

            $result = $this->pdfService->batchGenerate($components, $globalOptions);

            if ($displayOutput) {
                $this->displayBatchResult($result);
            }

            return $result['summary']['failed'] > 0 ? 1 : 0;

        } catch (Exception $e) {
            if ($displayOutput) {
                $this->error("❌ Batch generation failed: {$e->getMessage()}");
            }
            return 1;
        }
    }

    private function generateTestPdf(string $component, ?string $method): array
    {
        $props = $this->getTestProps($component);
        $options = $this->getTestOptions($component, $method);

        return $this->pdfService->generateFromVueComponent($component, $props, $options);
    }

    private function getTestProps(string $component): array
    {
        return match ($component) {
            'SentimentDashboard' => [
                'timeframe' => '7d',
                'symbols' => ['BTC', 'ETH', 'ADA'],
                'positive_count' => 125,
                'negative_count' => 45,
                'neutral_count' => 180,
                'include_charts' => true,
                'top_keywords' => [
                    'blockchain' => 89,
                    'cryptocurrency' => 67,
                    'bitcoin' => 54,
                    'ethereum' => 43,
                    'defi' => 32
                ]
            ],
            'AnalyticsDashboard' => [
                'contract_address' => '0x1234567890123456789012345678901234567890',
                'analysis_type' => 'comprehensive',
                'include_charts' => true,
                'security_score' => 87,
                'gas_efficiency' => 94,
                'code_quality' => 91,
                'findings' => [
                    [
                        'severity' => 'Medium',
                        'title' => 'Potential Reentrancy Vulnerability',
                        'description' => 'Function may be susceptible to reentrancy attacks'
                    ],
                    [
                        'severity' => 'Low', 
                        'title' => 'Gas Optimization Opportunity',
                        'description' => 'Loop can be optimized to reduce gas consumption'
                    ]
                ],
                'recommendations' => [
                    [
                        'title' => 'Implement ReentrancyGuard',
                        'description' => 'Add OpenZeppelin ReentrancyGuard to prevent reentrancy attacks'
                    ]
                ]
            ],
            default => [
                'title' => 'PDF Generation Test',
                'content' => 'This is a test of the unified Vue PDF generation service.',
                'timestamp' => now()->toISOString(),
                'test_data' => [
                    'numbers' => [1, 2, 3, 4, 5],
                    'text' => 'Sample text content for testing PDF generation',
                    'boolean' => true,
                    'nested' => [
                        'key1' => 'value1',
                        'key2' => 'value2'
                    ]
                ]
            ]
        };
    }

    private function getTestOptions(string $component, ?string $method): array
    {
        $baseOptions = [
            'filename' => 'test-' . strtolower($component) . '-' . now()->format('Y-m-d-H-i-s') . '.pdf',
            'format' => 'A4',
            'orientation' => 'portrait'
        ];

        if ($method && $method !== 'auto') {
            $baseOptions['force_method'] = $method;
        }

        // Component-specific options
        if ($component === 'SentimentDashboard') {
            $baseOptions['orientation'] = 'landscape';
            $baseOptions['wait_for'] = 'networkidle2';
            $baseOptions['has_charts'] = true;
        }

        return $baseOptions;
    }

    private function displaySuccessResult(array $result): void
    {
        $this->info('✅ PDF generated successfully!');
        $this->newLine();
        
        $this->line('📄 <fg=cyan>PDF Details</>');
        $this->line("   Method: {$result['method']}");
        $this->line("   Filename: {$result['filename']}");
        $this->line("   Size: {$result['size_formatted']}");
        $this->line("   Processing time: {$result['processing_time']}s");
        $this->line("   Quality: " . ($result['quality'] ?? 'Standard'));
        
        if (isset($result['url'])) {
            $this->line("   URL: {$result['url']}");
        }
        
        if (isset($result['download_url'])) {
            $this->line("   Download: {$result['download_url']}");
        }
        
        if (isset($result['warning'])) {
            $this->warn("   ⚠️  {$result['warning']}");
        }
    }

    private function displayFailureResult(array $result): void
    {
        $this->error('❌ PDF generation failed!');
        $this->newLine();
        
        if (isset($result['error'])) {
            $this->line("Error: {$result['error']}");
        }
        
        if (isset($result['message'])) {
            $this->line("Message: {$result['message']}");
        }
    }

    private function displayBatchResult(array $result): void
    {
        $summary = $result['summary'];
        
        $this->info('✅ Batch generation completed!');
        $this->newLine();
        
        $this->line('📊 <fg=cyan>Batch Summary</>');
        $this->line("   Total components: {$summary['total_components']}");
        $this->line("   Successful: {$summary['successful']}");
        $this->line("   Failed: {$summary['failed']}");
        $this->line("   Success rate: {$summary['success_rate']}%");
        $this->line("   Total time: {$summary['total_processing_time']}s");
        $this->line("   Average time: {$summary['average_time_per_component']}s");
        
        if (isset($result['batch_id'])) {
            $this->line("   Batch ID: {$result['batch_id']}");
        }
        
        $this->newLine();
        
        // Display individual results
        if (isset($result['batch_results'])) {
            $this->line('📄 <fg=cyan>Individual Results</>');
            foreach ($result['batch_results'] as $individual) {
                $status = $individual['success'] ? '✅' : '❌';
                $method = isset($individual['method']) ? $individual['method'] : 'unknown';
                $this->line("   {$status} {$individual['component_name']} ({$method})");
            }
        }
    }
}
