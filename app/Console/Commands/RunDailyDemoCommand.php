<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Services\QuickAnalysisService;
use App\Services\EnhancedSentimentPipelineService;
use App\Services\SecureVerificationBadgeService;
use App\Services\SourceCodeService;
use App\Services\ContractValidationService;
use App\Models\Analysis;
use App\Models\DailySentimentAggregate;
use App\Models\VerificationBadge;
use App\Models\User;
use App\Models\Project;
use Carbon\Carbon;

final class RunDailyDemoCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'demo:daily
                            {--contracts=* : Specific contract addresses to analyze}
                            {--skip-analysis : Skip contract analysis demo}
                            {--skip-sentiment : Skip sentiment analysis demo}
                            {--skip-badges : Skip verification badges demo}
                            {--skip-source : Skip source code fetching demo}
                            {--skip-crawling : Skip social media crawling demo}
                            {--skip-reports : Skip report generation demo}
                            {--skip-onboarding : Skip onboarding email demos}
                            {--skip-cleanup : Skip cache cleanup}
                            {--dry-run : Run without making actual changes}
                            {--detailed : Show detailed output}
                            {--output-file= : Save demo results to file}';

    /**
     * The console command description.
     */
    protected $description = 'Run comprehensive daily demo showcasing all AI Blockchain Analytics features';

    private array $demoResults = [];
    private Carbon $startTime;
    private bool $isDryRun = false;
    private bool $isVerbose = false;

    public function __construct(
        private readonly QuickAnalysisService $quickAnalysisService,
        private readonly EnhancedSentimentPipelineService $sentimentPipelineService,
        private readonly SecureVerificationBadgeService $verificationBadgeService,
        private readonly SourceCodeService $sourceCodeService,
        private readonly ContractValidationService $validationService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->startTime = now();
        $this->isDryRun = $this->option('dry-run');
        $this->isVerbose = $this->option('detailed');

        $this->displayBanner();
        
        try {
            // Initialize demo environment
            $this->initializeDemoEnvironment();

            // Run demo modules
            if (!$this->option('skip-source')) {
                $this->runSourceCodeDemo();
            }

            if (!$this->option('skip-analysis')) {
                $this->runContractAnalysisDemo();
            }

            if (!$this->option('skip-sentiment')) {
                $this->runSentimentAnalysisDemo();
            }

            if (!$this->option('skip-badges')) {
                $this->runVerificationBadgesDemo();
            }

            // Generate comprehensive report
            $this->generateDemoReport();

            // Store results for monitoring
            $this->storeDemoResults();

            $this->info("\n🎉 Daily demo completed successfully!");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Demo failed: {$e->getMessage()}");
            Log::error('Daily demo failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Display demo banner
     */
    private function displayBanner(): void
    {
        $this->line('');
        $this->line('<fg=cyan>═══════════════════════════════════════════════════════════════════</>');
        $this->line('<fg=cyan>   🚀 AI Blockchain Analytics - Daily Demo Script</>');
        $this->line('<fg=cyan>   📊 Comprehensive Platform Feature Showcase</>');
        $this->line('<fg=cyan>═══════════════════════════════════════════════════════════════════</>');
        $this->line('');
        
        if ($this->isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No actual changes will be made');
        }
        
        $this->info("📅 Demo Date: {$this->startTime->format('Y-m-d H:i:s')}");
        $this->line('');
    }

    /**
     * Initialize demo environment
     */
    private function initializeDemoEnvironment(): void
    {
        $this->info('🔧 Initializing demo environment...');

        // Check database connectivity
        try {
            DB::connection()->getPdo();
            $this->line('   ✅ Database connection verified');
        } catch (\Exception $e) {
            throw new \Exception("Database connection failed: {$e->getMessage()}");
        }

        // Check cache connectivity
        try {
            Cache::put('demo_test', 'working', 60);
            Cache::forget('demo_test');
            $this->line('   ✅ Cache system verified');
        } catch (\Exception $e) {
            $this->warn("   ⚠️ Cache system unavailable: {$e->getMessage()}");
        }

        // Verify API endpoints
        $this->verifyApiEndpoints();

        $this->demoResults['environment'] = [
            'status' => 'initialized',
            'timestamp' => now()->toISOString(),
            'database' => 'connected',
            'cache' => 'available'
        ];
    }

    /**
     * Verify API endpoints
     */
    private function verifyApiEndpoints(): void
    {
        $endpoints = [
            'health' => '/api/health',
            'networks' => '/api/contract/networks',
            'sentiment_status' => '/api/sentiment/status'
        ];

        $baseUrl = config('app.url');
        $workingEndpoints = 0;

        foreach ($endpoints as $name => $endpoint) {
            try {
                $response = Http::timeout(10)->get($baseUrl . $endpoint);
                if ($response->successful()) {
                    $workingEndpoints++;
                    if ($this->isVerbose) {
                        $this->line("   ✅ {$name}: {$endpoint}");
                    }
                } else {
                    if ($this->isVerbose) {
                        $this->line("   ❌ {$name}: {$endpoint} (HTTP {$response->status()})");
                    }
                }
            } catch (\Exception $e) {
                if ($this->isVerbose) {
                    $this->line("   ❌ {$name}: {$endpoint} (Error: {$e->getMessage()})");
                }
            }
        }

        $this->line("   📡 API Endpoints: {$workingEndpoints}/" . count($endpoints) . ' working');
    }

    /**
     * Run source code fetching demo
     */
    private function runSourceCodeDemo(): void
    {
        $this->info('📄 Running Source Code Fetching Demo...');

        $demoContracts = [
            ['address' => '0x5C69bEe701ef814a2B6a3EDD4B1652CB9cc5aA6f', 'name' => 'Uniswap V2 Factory', 'network' => 'ethereum'],
            ['address' => '0xcA143Ce32Fe78f1f7019d7d551a6402fC5350c73', 'name' => 'PancakeSwap Factory', 'network' => 'bsc'],
            ['address' => '0x5757371414417b8C6CAad45bAeF941aBc7d3Ab32', 'name' => 'QuickSwap Factory', 'network' => 'polygon']
        ];

        $results = [];

        foreach ($demoContracts as $contract) {
            $this->line("   🔍 Fetching: {$contract['name']} ({$contract['network']})");
            
            try {
                $sourceData = $this->sourceCodeService->fetchSourceCode(
                    $contract['address'],
                    $contract['network']
                );

                $results[] = [
                    'contract' => $contract['name'],
                    'address' => $contract['address'],
                    'network' => $contract['network'],
                    'success' => $sourceData['success'],
                    'verified' => $sourceData['verified'] ?? false,
                    'source_lines' => $sourceData['success'] ? substr_count($sourceData['source_code'] ?? '', "\n") : 0,
                    'compiler' => $sourceData['compiler_version'] ?? null,
                    'timestamp' => now()->toISOString()
                ];

                if ($sourceData['success']) {
                    $this->line("      ✅ Success - Verified: " . ($sourceData['verified'] ? 'Yes' : 'No'));
                    if ($this->isVerbose && $sourceData['verified']) {
                        $this->line("         Compiler: {$sourceData['compiler_version']}");
                        $this->line("         Lines: " . substr_count($sourceData['source_code'], "\n"));
                    }
                } else {
                    $this->line("      ❌ Failed: {$sourceData['message']}");
                }

            } catch (\Exception $e) {
                $this->line("      ❌ Error: {$e->getMessage()}");
                $results[] = [
                    'contract' => $contract['name'],
                    'address' => $contract['address'],
                    'network' => $contract['network'],
                    'success' => false,
                    'error' => $e->getMessage(),
                    'timestamp' => now()->toISOString()
                ];
            }
        }

        $successCount = count(array_filter($results, fn($r) => $r['success']));
        $this->line("   📊 Source Code Demo: {$successCount}/" . count($results) . ' successful');

        $this->demoResults['source_code'] = [
            'total_contracts' => count($results),
            'successful' => $successCount,
            'results' => $results,
            'completion_time' => now()->toISOString()
        ];
    }

    /**
     * Run contract analysis demo
     */
    private function runContractAnalysisDemo(): void
    {
        $this->info('🔍 Running Contract Analysis Demo...');

        $contracts = $this->option('contracts') ?: [
            '0x5C69bEe701ef814a2B6a3EDD4B1652CB9cc5aA6f', // Uniswap V2
            '0xA0b86a33E6417c7e4E6b42b0Db8FC0a41F34a3B4', // USDC
            '0x7d2768dE32b0b80b7a3454c06BdAc94A69DDc7A9'  // AAVE
        ];

        $results = [];

        foreach ($contracts as $contractAddress) {
            $this->line("   🔬 Analyzing: {$contractAddress}");

            try {
                if (!$this->isDryRun) {
                    $analysisResult = $this->quickAnalysisService->performQuickAnalysis(
                        $contractAddress,
                        'ethereum'
                    );

                    $results[] = [
                        'address' => $contractAddress,
                        'analysis_id' => $analysisResult['analysis_id'],
                        'security_score' => $analysisResult['security_score'],
                        'vulnerabilities' => [
                            'critical' => $analysisResult['critical_issues'],
                            'high' => $analysisResult['high_issues'],
                            'medium' => $analysisResult['medium_issues']
                        ],
                        'functions_count' => $analysisResult['functions_count'],
                        'lines_of_code' => $analysisResult['lines_of_code'],
                        'verified' => $analysisResult['verified'],
                        'processing_time' => $analysisResult['processing_time'],
                        'timestamp' => now()->toISOString()
                    ];

                    $this->line("      ✅ Analysis completed");
                    $this->line("         Security Score: {$analysisResult['security_score']}/100");
                    $this->line("         Issues: C:{$analysisResult['critical_issues']} H:{$analysisResult['high_issues']} M:{$analysisResult['medium_issues']}");
                    
                    if ($this->isVerbose) {
                        $this->line("         Functions: {$analysisResult['functions_count']}");
                        $this->line("         Lines: {$analysisResult['lines_of_code']}");
                        $this->line("         Processing: {$analysisResult['processing_time']}ms");
                    }
                } else {
                    $this->line("      🔍 [DRY RUN] Would analyze contract");
                    $results[] = [
                        'address' => $contractAddress,
                        'dry_run' => true,
                        'timestamp' => now()->toISOString()
                    ];
                }

            } catch (\Exception $e) {
                $this->line("      ❌ Analysis failed: {$e->getMessage()}");
                $results[] = [
                    'address' => $contractAddress,
                    'error' => $e->getMessage(),
                    'timestamp' => now()->toISOString()
                ];
            }
        }

        $successCount = count(array_filter($results, fn($r) => isset($r['analysis_id']) || isset($r['dry_run'])));
        $this->line("   📊 Analysis Demo: {$successCount}/" . count($results) . ' successful');

        $this->demoResults['contract_analysis'] = [
            'total_contracts' => count($results),
            'successful' => $successCount,
            'results' => $results,
            'completion_time' => now()->toISOString()
        ];
    }

    /**
     * Run sentiment analysis demo
     */
    private function runSentimentAnalysisDemo(): void
    {
        $this->info('💭 Running Sentiment Analysis Demo...');

        $sampleTexts = [
            'Bitcoin is showing strong bullish momentum with institutional adoption increasing.',
            'Ethereum gas fees are extremely high, making DeFi unusable for small investors.',
            'The new smart contract upgrade looks promising with improved security features.',
            'Market volatility is concerning, but long-term prospects remain positive.',
            'DeFi protocols are revolutionizing traditional finance with innovative solutions.'
        ];

        $results = [];

        foreach ($sampleTexts as $index => $text) {
            $this->line("   📝 Processing text " . ($index + 1) . "/" . count($sampleTexts));
            
            try {
                if (!$this->isDryRun) {
                    $sentimentResult = $this->sentimentPipelineService->processTextPipeline([
                        [
                            'text' => $text,
                            'platform' => 'demo',
                            'source_id' => 'demo_' . ($index + 1),
                            'metadata' => ['demo' => true]
                        ]
                    ]);

                    $results[] = [
                        'text_preview' => substr($text, 0, 50) . '...',
                        'sentiment_score' => $sentimentResult['average_sentiment'] ?? 0.0,
                        'magnitude' => $sentimentResult['average_magnitude'] ?? 0.0,
                        'classification' => $this->classifySentiment($sentimentResult['average_sentiment'] ?? 0.0),
                        'processing_time' => $sentimentResult['processing_time'] ?? 0,
                        'timestamp' => now()->toISOString()
                    ];

                    $score = $sentimentResult['average_sentiment'] ?? 0.0;
                    $classification = $this->classifySentiment($score);
                    $this->line("      ✅ Sentiment: {$classification} (Score: " . number_format($score, 2) . ")");

                } else {
                    $this->line("      🔍 [DRY RUN] Would analyze sentiment");
                    $results[] = [
                        'text_preview' => substr($text, 0, 50) . '...',
                        'dry_run' => true,
                        'timestamp' => now()->toISOString()
                    ];
                }

            } catch (\Exception $e) {
                $this->line("      ❌ Sentiment analysis failed: {$e->getMessage()}");
                $results[] = [
                    'text_preview' => substr($text, 0, 50) . '...',
                    'error' => $e->getMessage(),
                    'timestamp' => now()->toISOString()
                ];
            }
        }

        // Generate daily aggregate demo
        if (!$this->isDryRun) {
            $this->generateSentimentAggregateDemo();
        }

        $successCount = count(array_filter($results, fn($r) => isset($r['sentiment_score']) || isset($r['dry_run'])));
        $this->line("   📊 Sentiment Demo: {$successCount}/" . count($results) . ' successful');

        $this->demoResults['sentiment_analysis'] = [
            'total_texts' => count($results),
            'successful' => $successCount,
            'results' => $results,
            'completion_time' => now()->toISOString()
        ];
    }

    /**
     * Generate sentiment aggregate demo
     */
    private function generateSentimentAggregateDemo(): void
    {
        $this->line("   📊 Generating daily sentiment aggregates...");

        try {
            $aggregates = $this->sentimentPipelineService->generateDailyAggregates([
                'date' => now()->toDateString(),
                'platforms' => ['demo'],
                'keywords' => ['bitcoin', 'ethereum', 'defi']
            ]);

            $this->line("      ✅ Generated " . count($aggregates) . " aggregate records");

        } catch (\Exception $e) {
            $this->line("      ❌ Aggregate generation failed: {$e->getMessage()}");
        }
    }

    /**
     * Run verification badges demo
     */
    private function runVerificationBadgesDemo(): void
    {
        $this->info('🛡️ Running Verification Badges Demo...');

        $demoEntities = [
            ['type' => 'contract', 'id' => '0x5C69bEe701ef814a2B6a3EDD4B1652CB9cc5aA6f', 'level' => 'STANDARD'],
            ['type' => 'user', 'id' => 'demo_user_' . time(), 'level' => 'PREMIUM'],
            ['type' => 'project', 'id' => 'demo_project_' . time(), 'level' => 'BASIC']
        ];

        $results = [];

        foreach ($demoEntities as $entity) {
            $this->line("   🏷️ Creating badge: {$entity['type']} ({$entity['level']})");

            try {
                if (!$this->isDryRun) {
                    $badgeResult = $this->verificationBadgeService->generateSecureBadge(
                        $entity['type'],
                        $entity['id'],
                        [
                            'verification_level' => $entity['level'],
                            'demo' => true,
                            'created_by' => 'daily_demo'
                        ]
                    );

                    // Verify the badge
                    $verification = $this->verificationBadgeService->verifyBadge($badgeResult['token']);

                    $results[] = [
                        'entity_type' => $entity['type'],
                        'entity_id' => $entity['id'],
                        'verification_level' => $entity['level'],
                        'token' => $badgeResult['token'],
                        'badge_url' => $badgeResult['badge_url'],
                        'verification_url' => $badgeResult['verification_url'],
                        'verified' => $verification['valid'],
                        'expires_at' => $badgeResult['expires_at'],
                        'timestamp' => now()->toISOString()
                    ];

                    $this->line("      ✅ Badge created and verified");
                    if ($this->isVerbose) {
                        $this->line("         Token: " . substr($badgeResult['token'], 0, 20) . '...');
                        $this->line("         Expires: {$badgeResult['expires_at']}");
                    }

                } else {
                    $this->line("      🔍 [DRY RUN] Would create verification badge");
                    $results[] = [
                        'entity_type' => $entity['type'],
                        'entity_id' => $entity['id'],
                        'verification_level' => $entity['level'],
                        'dry_run' => true,
                        'timestamp' => now()->toISOString()
                    ];
                }

            } catch (\Exception $e) {
                $this->line("      ❌ Badge creation failed: {$e->getMessage()}");
                $results[] = [
                    'entity_type' => $entity['type'],
                    'entity_id' => $entity['id'],
                    'error' => $e->getMessage(),
                    'timestamp' => now()->toISOString()
                ];
            }
        }

        $successCount = count(array_filter($results, fn($r) => isset($r['token']) || isset($r['dry_run'])));
        $this->line("   📊 Badges Demo: {$successCount}/" . count($results) . ' successful');

        $this->demoResults['verification_badges'] = [
            'total_badges' => count($results),
            'successful' => $successCount,
            'results' => $results,
            'completion_time' => now()->toISOString()
        ];
    }

    /**
     * Generate comprehensive demo report
     */
    private function generateDemoReport(): void
    {
        $this->info('📋 Generating Demo Report...');

        $duration = now()->diffInSeconds($this->startTime);
        $totalOperations = 0;
        $successfulOperations = 0;

        foreach ($this->demoResults as $module => $data) {
            if ($module === 'environment') continue;
            
            $totalOperations += $data['total_contracts'] ?? $data['total_texts'] ?? $data['total_badges'] ?? 0;
            $successfulOperations += $data['successful'] ?? 0;
        }

        $successRate = $totalOperations > 0 ? ($successfulOperations / $totalOperations) * 100 : 0;

        $this->line('');
        $this->line('<fg=green>📊 DEMO RESULTS SUMMARY</>');
        $this->line('<fg=green>═══════════════════════</>');
        $this->line("🕐 Duration: {$duration} seconds");
        $this->line("📈 Success Rate: " . number_format($successRate, 1) . "%");
        $this->line("✅ Successful Operations: {$successfulOperations}/{$totalOperations}");
        $this->line('');

        // Module-specific results
        foreach ($this->demoResults as $module => $data) {
            if ($module === 'environment') continue;
            
            $moduleName = ucwords(str_replace('_', ' ', $module));
            $total = $data['total_contracts'] ?? $data['total_texts'] ?? $data['total_badges'] ?? 0;
            $successful = $data['successful'] ?? 0;
            
            $this->line("🔹 {$moduleName}: {$successful}/{$total}");
        }

        $this->demoResults['summary'] = [
            'duration_seconds' => $duration,
            'total_operations' => $totalOperations,
            'successful_operations' => $successfulOperations,
            'success_rate' => $successRate,
            'completion_time' => now()->toISOString()
        ];
    }

    /**
     * Store demo results for monitoring
     */
    private function storeDemoResults(): void
    {
        if ($this->isDryRun) {
            $this->line('🔍 [DRY RUN] Would store demo results');
            return;
        }

        try {
            $cacheKey = 'daily_demo_results:' . now()->toDateString();
            Cache::put($cacheKey, $this->demoResults, 86400 * 7); // Keep for 7 days

            // Log results for monitoring
            Log::info('Daily demo completed', [
                'results' => $this->demoResults,
                'date' => now()->toDateString()
            ]);

            // Save to output file if specified
            $outputFile = $this->option('output-file');
            if ($outputFile) {
                $this->saveResultsToFile($outputFile);
            }

            $this->line('   ✅ Demo results stored for monitoring');

        } catch (\Exception $e) {
            $this->warn("   ⚠️ Failed to store results: {$e->getMessage()}");
        }
    }

    /**
     * Save demo results to file
     */
    private function saveResultsToFile(string $outputFile): void
    {
        try {
            $results = [
                'demo_date' => now()->toISOString(),
                'version' => 'v0.9.0',
                'command' => 'RunDailyDemoCommand',
                'execution_stats' => [
                    'start_time' => $this->startTime->toISOString(),
                    'end_time' => now()->toISOString(),
                    'duration_seconds' => now()->diffInSeconds($this->startTime),
                    'dry_run' => $this->isDryRun,
                    'verbose' => $this->isVerbose
                ],
                'demo_results' => $this->demoResults,
                'performance_metrics' => [
                    'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB',
                    'peak_memory' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . 'MB'
                ]
            ];

            Storage::put($outputFile, json_encode($results, JSON_PRETTY_PRINT));
            $this->line("   📄 Demo results saved to: {$outputFile}");

        } catch (\Exception $e) {
            $this->warn("   ⚠️ Could not save results to file: {$e->getMessage()}");
        }
    }

    /**
     * Classify sentiment score
     */
    private function classifySentiment(float $score): string
    {
        if ($score > 0.3) return 'Positive';
        if ($score < -0.3) return 'Negative';
        return 'Neutral';
    }
}
