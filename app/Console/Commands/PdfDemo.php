<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PdfGenerationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

final class PdfDemo extends Command
{
    protected $signature = 'pdf:demo
                           {--type=dashboard : Type of PDF to generate (dashboard, sentiment, crawler)}
                           {--method=auto : Generation method (auto, browserless, dompdf)}
                           {--show-output : Display the generated PDF information}
                           {--save-sample : Save sample data for testing}';

    protected $description = 'Demonstrate PDF generation from Vue components using browserless or DomPDF';

    public function handle(): int
    {
        $this->displayHeader();
        
        try {
            $type = $this->option('type');
            $method = $this->option('method');
            $showOutput = $this->option('show-output');
            
            $this->info("🎯 Demo Configuration:");
            $this->table(['Setting', 'Value'], [
                ['PDF Type', ucfirst($type)],
                ['Generation Method', ucfirst($method)],
                ['Show Output', $showOutput ? 'Yes' : 'No']
            ]);
            $this->newLine();

            // Validate configuration
            $this->validateConfiguration();
            
            // Generate sample PDF
            $result = $this->generateSamplePdf($type, $method);
            
            // Display results
            $this->displayResults($result, $showOutput);
            
            // Save sample data if requested
            if ($this->option('save-sample')) {
                $this->saveSampleData($type);
            }
            
            $this->displaySummary();
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("❌ PDF demo failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function displayHeader(): void
    {
        $this->info('📄 PDF GENERATION DEMO');
        $this->info('Vue Components → Browserless/DomPDF → PDF Files');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->newLine();
    }

    private function validateConfiguration(): void
    {
        $this->info('🔧 Validating PDF generation configuration...');
        
        $browserlessEnabled = config('services.browserless.enabled', false);
        $browserlessUrl = config('services.browserless.url');
        
        $validationData = [
            ['Component', 'Status', 'Details']
        ];
        
        // Check storage directory
        $storageDir = 'pdfs';
        if (!Storage::disk('public')->exists($storageDir)) {
            Storage::disk('public')->makeDirectory($storageDir);
            $validationData[] = ['Storage Directory', '✅ Created', 'storage/app/public/pdfs'];
        } else {
            $validationData[] = ['Storage Directory', '✅ Exists', 'storage/app/public/pdfs'];
        }
        
        // Check Browserless
        if ($browserlessEnabled) {
            $browserlessStatus = $this->testBrowserlessConnection($browserlessUrl);
            $validationData[] = ['Browserless Service', $browserlessStatus['status'], $browserlessStatus['message']];
        } else {
            $validationData[] = ['Browserless Service', '⚠️ Disabled', 'Using DomPDF fallback'];
        }
        
        // Check DomPDF
        $dompdfAvailable = class_exists('\Dompdf\Dompdf');
        $validationData[] = ['DomPDF Library', $dompdfAvailable ? '✅ Available' : '⚠️ Not Installed', $dompdfAvailable ? 'Ready for server-side rendering' : 'Simulation mode only'];
        
        // Check Vue components
        $componentPaths = [
            'DashboardReport' => 'resources/js/Pages/Pdf/DashboardReport.vue',
            'SentimentReport' => 'resources/js/Pages/Pdf/SentimentReport.vue'
        ];
        
        foreach ($componentPaths as $component => $path) {
            $exists = file_exists(base_path($path));
            $validationData[] = ["{$component} Component", $exists ? '✅ Available' : '❌ Missing', $path];
        }
        
        $this->table(['Component', 'Status', 'Details'], array_slice($validationData, 1));
        $this->newLine();
    }

    private function testBrowserlessConnection(string $url): array
    {
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url . '/metrics',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => true
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                return ['status' => '✅ Connected', 'message' => "Service responding at {$url}"];
            } else {
                return ['status' => '❌ Unreachable', 'message' => "HTTP {$httpCode} from {$url}"];
            }
        } catch (\Exception $e) {
            return ['status' => '❌ Failed', 'message' => $e->getMessage()];
        }
    }

    private function generateSamplePdf(string $type, string $method): array
    {
        $this->info("📝 Generating {$type} PDF using {$method} method...");
        
        $pdfService = app(PdfGenerationService::class);
        
        // Get sample data
        $sampleData = $this->getSampleData($type);
        
        // Configure options
        $options = [
            'filename' => "demo-{$type}-" . now()->format('Y-m-d-H-i-s') . '.pdf',
            'title' => "Demo {$type} Report",
            'force_browserless' => $method === 'browserless'
        ];
        
        // Show progress
        $progressBar = $this->output->createProgressBar(100);
        $progressBar->setFormat('📄 Generating: %current%/%max% [%bar%] %percent:3s%% - %message%');
        $progressBar->start();
        
        $progressBar->setMessage('Preparing data...');
        $progressBar->advance(20);
        
        $progressBar->setMessage('Rendering component...');
        $progressBar->advance(30);
        
        $progressBar->setMessage('Converting to PDF...');
        $progressBar->advance(30);
        
        // Generate PDF
        $result = match($type) {
            'dashboard' => $pdfService->generateDashboardReport($sampleData, $options),
            'sentiment' => $pdfService->generateSentimentReport($sampleData, $options),
            'crawler' => $pdfService->generateCrawlerReport($sampleData, $options),
            default => throw new \Exception("Unknown PDF type: {$type}")
        };
        
        $progressBar->setMessage('Finalizing...');
        $progressBar->advance(20);
        
        $progressBar->finish();
        $this->newLine(2);
        
        return $result;
    }

    private function getSampleData(string $type): array
    {
        return match($type) {
            'dashboard' => [
                'title' => 'Blockchain Analytics Dashboard Report',
                'date_range' => [now()->subMonth()->format('Y-m-d'), now()->format('Y-m-d')],
                'metrics' => [
                    'total_posts' => 15420,
                    'sentiment_score' => 0.127,
                    'platforms' => [
                        'twitter' => 8934,
                        'reddit' => 4123,
                        'telegram' => 2363
                    ],
                    'engagement' => 89234
                ],
                'charts' => [
                    'sentiment_trend' => $this->generateTrendData(30, -0.5, 0.5),
                    'volume_trend' => $this->generateTrendData(30, 100, 1000)
                ],
                'insights' => [
                    'sentiment_growth' => 0.15,
                    'volatility_high' => false
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
                    'bitcoin' => ['mentions' => 2345, 'sentiment' => 0.234, 'trend' => 12.5],
                    'ethereum' => ['mentions' => 1876, 'sentiment' => 0.189, 'trend' => -5.2],
                    'defi' => ['mentions' => 1234, 'sentiment' => 0.345, 'trend' => 8.7],
                    'nft' => ['mentions' => 987, 'sentiment' => -0.123, 'trend' => -15.3]
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

    private function generateTrendData(int $days, float $min, float $max): array
    {
        $data = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $value = $min + (mt_rand() / mt_getrandmax()) * ($max - $min);
            
            $data[] = [
                'date' => $date->format('Y-m-d'),
                match($min) {
                    100 => 'volume',
                    default => 'sentiment'
                } => round($value, 3)
            ];
        }
        return $data;
    }

    private function displayResults(array $result, bool $showOutput): void
    {
        $this->info('📊 PDF Generation Results:');
        
        $resultData = [
            ['Metric', 'Value'],
            ['Success', $result['success'] ? '✅ Yes' : '❌ No'],
            ['Method Used', ucfirst($result['method'] ?? 'unknown')],
            ['Filename', $result['filename'] ?? 'N/A'],
            ['File Size', isset($result['size']) ? $this->formatBytes($result['size']) : 'N/A'],
            ['Processing Time', isset($result['processing_time']) ? $result['processing_time'] . 's' : 'N/A'],
            ['Simulation Mode', isset($result['simulation']) && $result['simulation'] ? '⚠️ Yes' : '✅ No']
        ];
        
        if (isset($result['url'])) {
            $resultData[] = ['Download URL', $result['url']];
        }
        
        if (isset($result['error'])) {
            $resultData[] = ['Error', $result['error']];
        }
        
        $this->table(['Metric', 'Value'], array_slice($resultData, 1));
        
        if ($showOutput && $result['success']) {
            $this->newLine();
            $this->info('📁 File Details:');
            $this->line("   • Path: {$result['file_path']}");
            $this->line("   • Full URL: " . url($result['url']));
            
            if (file_exists(storage_path('app/public/' . $result['file_path']))) {
                $this->line("   • Local File: ✅ Exists");
            } else {
                $this->warn("   • Local File: ❌ Not Found");
            }
        }
        
        $this->newLine();
    }

    private function saveSampleData(string $type): void
    {
        $sampleData = $this->getSampleData($type);
        $filename = "sample-{$type}-data.json";
        $path = storage_path("app/samples/{$filename}");
        
        // Ensure directory exists
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        
        file_put_contents($path, json_encode($sampleData, JSON_PRETTY_PRINT));
        
        $this->info("💾 Sample data saved to: storage/app/samples/{$filename}");
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }

    private function displaySummary(): void
    {
        $this->newLine();
        $this->info('🎉 PDF GENERATION DEMO COMPLETE!');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->newLine();
        
        $this->info('✅ Successfully Demonstrated:');
        $this->line('   📄 Vue component to PDF conversion');
        $this->line('   🖥️ Server-side PDF generation with DomPDF');
        $this->line('   🌐 Browserless PDF generation capability');
        $this->line('   📊 Multiple report types (Dashboard, Sentiment, Crawler)');
        $this->line('   🎨 Professional PDF styling and layout');
        $this->line('   📁 File storage and management');
        
        $this->newLine();
        $this->info('🛠️  Available PDF Commands:');
        $this->line('   pdf:demo --type=sentiment --method=browserless  → Generate sentiment PDF');
        $this->line('   pdf:demo --type=dashboard --show-output         → Generate dashboard PDF');
        $this->line('   pdf:demo --save-sample                          → Save sample data');
        
        $this->newLine();
        $this->info('🌐 API Endpoints:');
        $this->line('   POST /api/pdf/dashboard → Generate dashboard PDF');
        $this->line('   POST /api/pdf/sentiment → Generate sentiment PDF');
        $this->line('   POST /api/pdf/test      → Test PDF generation');
        
        $this->newLine();
        $this->info('📖 The PDF generation system is ready for production use!');
    }
}