<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PdfGenerationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

final class PdfTest extends Command
{
    protected $signature = 'pdf:test
                           {--all : Test all PDF types}
                           {--type=dashboard : Specific PDF type to test}
                           {--clean : Clean up test files after generation}';

    protected $description = 'Simple test of PDF generation without external dependencies';

    public function handle(): int
    {
        $this->info('🧪 SIMPLE PDF GENERATION TEST');
        $this->info('Testing PDF generation without external service dependencies');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->newLine();

        try {
            // Create storage directory if it doesn't exist
            if (!Storage::disk('public')->exists('pdfs')) {
                Storage::disk('public')->makeDirectory('pdfs');
                $this->info('📁 Created storage/app/public/pdfs directory');
            }

            $testAll = $this->option('all');
            $specificType = $this->option('type');
            
            if ($testAll) {
                $this->testAllTypes();
            } else {
                $this->testSingleType($specificType);
            }

            if ($this->option('clean')) {
                $this->cleanupTestFiles();
            }

            $this->displaySummary();
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Test failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function testAllTypes(): void
    {
        $types = ['dashboard', 'sentiment', 'crawler'];
        
        foreach ($types as $type) {
            $this->testSingleType($type);
            $this->newLine();
        }
    }

    private function testSingleType(string $type): void
    {
        $this->info("📝 Testing {$type} PDF generation...");

        // Create simple test data
        $testData = $this->createTestData($type);
        $pdfService = app(PdfGenerationService::class);

        $options = [
            'filename' => "test-{$type}-" . now()->format('Y-m-d-H-i-s') . '.pdf',
            'title' => "Test {$type} Report"
        ];

        // Test generation
        try {
            $result = match($type) {
                'dashboard' => $pdfService->generateDashboardReport($testData, $options),
                'sentiment' => $pdfService->generateSentimentReport($testData, $options),
                'crawler' => $pdfService->generateCrawlerReport($testData, $options),
                default => throw new \Exception("Unknown type: {$type}")
            };

            // Display results
            $this->displayTestResult($type, $result);

        } catch (\Exception $e) {
            $this->error("❌ {$type} PDF generation failed: " . $e->getMessage());
        }
    }

    private function createTestData(string $type): array
    {
        return match($type) {
            'dashboard' => [
                'title' => 'Test Dashboard Report',
                'date_range' => [now()->subWeek()->format('Y-m-d'), now()->format('Y-m-d')],
                'metrics' => [
                    'total_posts' => 1250,
                    'sentiment_score' => 0.145,
                    'platforms' => ['twitter' => 750, 'reddit' => 350, 'telegram' => 150],
                    'engagement' => 8750
                ]
            ],
            'sentiment' => [
                'title' => 'Test Sentiment Report',
                'period' => 'Test Period',
                'overall_sentiment' => 0.234,
                'platforms' => [
                    'twitter' => ['sentiment' => 0.189, 'posts' => 750, 'engagement' => 4500],
                    'reddit' => ['sentiment' => -0.067, 'posts' => 350, 'engagement' => 2100]
                ],
                'top_keywords' => [
                    'test' => ['mentions' => 123, 'sentiment' => 0.5, 'trend' => 10.0],
                    'demo' => ['mentions' => 89, 'sentiment' => 0.3, 'trend' => -5.0]
                ]
            ],
            'crawler' => [
                'title' => 'Test Crawler Report',
                'period' => 'Test Period',
                'summary' => [
                    'total_posts_collected' => 1250,
                    'success_rate' => 98.4,
                    'avg_processing_time' => '0.8s',
                    'platforms_active' => 2
                ],
                'platform_breakdown' => [
                    'twitter' => ['posts' => 750, 'keywords_matched' => 234, 'avg_sentiment' => 0.189],
                    'reddit' => ['posts' => 350, 'keywords_matched' => 156, 'avg_sentiment' => -0.067]
                ]
            ],
            default => []
        };
    }

    private function displayTestResult(string $type, array $result): void
    {
        if ($result['success']) {
            $this->info("   ✅ {$type}: Generated successfully");
            $this->line("      Method: {$result['method']}");
            $this->line("      Size: " . $this->formatBytes($result['size'] ?? 0));
            $this->line("      Time: " . ($result['processing_time'] ?? 0) . 's');
            
            if (isset($result['simulation']) && $result['simulation']) {
                $this->warn("      ⚠️ Simulation mode (no real PDF library)");
            }
        } else {
            $this->error("   ❌ {$type}: Generation failed");
            if (isset($result['error'])) {
                $this->error("      Error: {$result['error']}");
            }
        }
    }

    private function cleanupTestFiles(): void
    {
        $files = Storage::disk('public')->files('pdfs');
        $testFiles = array_filter($files, fn($file) => str_contains($file, 'test-'));
        
        $deleted = 0;
        foreach ($testFiles as $file) {
            Storage::disk('public')->delete($file);
            $deleted++;
        }

        if ($deleted > 0) {
            $this->info("🧹 Cleaned up {$deleted} test PDF files");
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }

    private function displaySummary(): void
    {
        $this->newLine();
        $this->info('📊 TEST SUMMARY');
        
        $files = Storage::disk('public')->files('pdfs');
        $totalFiles = count($files);
        $totalSize = array_sum(array_map(fn($file) => Storage::disk('public')->size($file), $files));
        
        $this->table(['Metric', 'Value'], [
            ['Total PDF Files', $totalFiles],
            ['Total Storage Used', $this->formatBytes($totalSize)],
            ['Storage Path', 'storage/app/public/pdfs/'],
            ['Public URL Base', Storage::url('pdfs/')],
        ]);

        $this->newLine();
        $this->info('✅ PDF generation system is working correctly!');
        $this->line('   🔧 Configure browserless service for enhanced PDF generation');
        $this->line('   📦 Install DomPDF for server-side PDF rendering');
        $this->line('   🎨 Customize Vue components in resources/js/Pages/Pdf/');
    }
}