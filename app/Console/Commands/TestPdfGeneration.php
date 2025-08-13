<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PdfGeneratorService;

class TestPdfGeneration extends Command
{
    protected $signature = 'test:pdf-generation {--type=sentiment : Type of PDF to generate} {--method=auto : Generation method}';
    protected $description = 'Test PDF generation with sample data';

    public function handle(PdfGeneratorService $pdfService): int
    {
        $this->info('🧪 Testing PDF Generation Service...');
        
        // Check service status
        $status = $pdfService->getStatus();
        $this->newLine();
        $this->info('📊 Service Status:');
        $this->line("  Browserless: " . ($status['browserless_available'] ? '✅ Available' : '❌ Unavailable'));
        $this->line("  DomPDF: " . ($status['dompdf_available'] ? '✅ Available' : '❌ Unavailable'));
        $this->line("  Storage: " . ($status['storage_writable'] ? '✅ Writable' : '❌ Not Writable'));
        
        if ($status['browserless_available']) {
            $this->line("  Browserless URL: " . $status['browserless_url']);
            $this->line("  Token: " . ($status['has_token'] ? '✅ Configured' : '❌ Missing'));
        }

        $type = $this->option('type');
        $method = $this->option('method');
        
        $this->newLine();
        $this->info("🚀 Generating {$type} report using {$method} method...");
        
        try {
            $filename = match($type) {
                'sentiment' => $this->testSentimentReport($pdfService),
                'social' => $this->testSocialReport($pdfService),
                'blockchain' => $this->testBlockchainReport($pdfService),
                'html' => $this->testHtmlGeneration($pdfService),
                default => $this->testSentimentReport($pdfService)
            };
            
            if ($filename) {
                $this->newLine();
                $this->info("✅ PDF generated successfully!");
                $this->line("📄 Filename: {$filename}");
                $this->line("🔗 URL: " . $pdfService->getPdfUrl($filename));
                
                // Display file info
                $filePath = storage_path("app/public/pdfs/{$filename}");
                if (file_exists($filePath)) {
                    $fileSize = round(filesize($filePath) / 1024, 2);
                    $this->line("📏 Size: {$fileSize} KB");
                }
            }
            
            return self::SUCCESS;
            
        } catch (\Exception $e) {
            $this->newLine();
            $this->error("❌ PDF generation failed!");
            $this->line("Error: " . $e->getMessage());
            $this->line("File: " . $e->getFile() . ':' . $e->getLine());
            
            return self::FAILURE;
        }
    }
    
    private function testSentimentReport(PdfGeneratorService $pdfService): string
    {
        $sampleData = [
            'symbol' => 'BTC',
            'period' => 30,
            'data' => [
                'posts' => [
                    [
                        'id' => 1,
                        'platform' => 'twitter',
                        'content' => 'Bitcoin is looking bullish! 🚀',
                        'sentiment_score' => 0.8,
                        'published_at' => now()->subHours(2)->toISOString()
                    ],
                    [
                        'id' => 2,
                        'platform' => 'reddit',
                        'content' => 'Concerned about the recent BTC dip...',
                        'sentiment_score' => -0.3,
                        'published_at' => now()->subHours(5)->toISOString()
                    ]
                ],
                'analytics' => [
                    'total_posts' => 15420,
                    'avg_sentiment' => 0.127,
                    'engagement_total' => 89234,
                    'platforms' => [
                        'twitter' => 8934,
                        'reddit' => 4123,
                        'telegram' => 2363
                    ],
                    'top_keywords' => [
                        'bitcoin' => 2345,
                        'btc' => 1876,
                        'crypto' => 1234,
                        'hodl' => 987,
                        'moon' => 654
                    ],
                    'sentiment_distribution' => [
                        'positive' => 45,
                        'neutral' => 35,
                        'negative' => 20
                    ]
                ]
            ],
            'generated_at' => now()->toISOString()
        ];
        
        return $pdfService->generateAnalyticsReport($sampleData, 'sentiment');
    }
    
    private function testSocialReport(PdfGeneratorService $pdfService): string
    {
        $posts = [
            [
                'platform' => 'twitter',
                'author_username' => 'crypto_analyst',
                'content' => 'Major DeFi protocol update coming soon!',
                'engagement_count' => 234,
                'published_at' => now()->subHours(1)->toISOString()
            ],
            [
                'platform' => 'reddit',
                'author_username' => 'blockchain_dev',
                'content' => 'New smart contract vulnerability discovered',
                'engagement_count' => 156,
                'published_at' => now()->subHours(3)->toISOString()
            ]
        ];
        
        $analytics = [
            'total_posts' => count($posts),
            'total_engagement' => array_sum(array_column($posts, 'engagement_count')),
            'platforms' => ['twitter' => 1, 'reddit' => 1],
            'avg_engagement' => 195
        ];
        
        return $pdfService->generateSocialReport($posts, $analytics);
    }
    
    private function testBlockchainReport(PdfGeneratorService $pdfService): string
    {
        $transactions = [
            [
                'hash' => '0x1234567890abcdef',
                'from_address' => '0xabcdef1234567890',
                'to_address' => '0x9876543210fedcba',
                'value' => 1.5,
                'gas_used' => 21000,
                'block_timestamp' => now()->subMinutes(30)->toISOString()
            ]
        ];
        
        $analysis = [
            'total_transactions' => count($transactions),
            'total_value' => 1.5,
            'avg_gas_used' => 21000,
            'unique_addresses' => 2
        ];
        
        return $pdfService->generateBlockchainReport($transactions, $analysis);
    }
    
    private function testHtmlGeneration(PdfGeneratorService $pdfService): string
    {
        $html = '
        <html>
        <head>
            <title>Test HTML PDF</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; }
                .header { color: #3B82F6; border-bottom: 2px solid #3B82F6; padding-bottom: 10px; }
                .content { margin-top: 20px; }
                .highlight { background-color: #FEF3C7; padding: 10px; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>🧪 PDF Generation Test</h1>
                <p>Generated on: ' . now()->format('Y-m-d H:i:s') . '</p>
            </div>
            <div class="content">
                <h2>✅ Test Results</h2>
                <div class="highlight">
                    <p><strong>Service Status:</strong> ✅ Working</p>
                    <p><strong>Method:</strong> ' . ($pdfService->isBrowserlessAvailable() ? 'Browserless' : 'DomPDF') . '</p>
                    <p><strong>Features:</strong></p>
                    <ul>
                        <li>HTML to PDF conversion ✅</li>
                        <li>CSS styling support ✅</li>
                        <li>Unicode characters support ✅</li>
                        <li>Custom fonts support ✅</li>
                    </ul>
                </div>
            </div>
        </body>
        </html>';
        
        return $pdfService->generateFromHtml($html, [
            'format' => 'A4',
            'orientation' => 'portrait'
        ]);
    }
}