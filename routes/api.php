<?php

use App\Http\Controllers\Api\AnalysisController;
use App\Http\Controllers\Api\CrawlerMicroServiceController;
use App\Http\Controllers\Api\LoadTestController;
use App\Http\Controllers\Api\OpenAiJobController;
use App\Http\Controllers\Api\OpenAiStreamingController;
use App\Http\Controllers\Api\OpenAiHorizonController;
use App\Http\Controllers\Api\OWASPSecurityController;
use App\Http\Controllers\Api\SentimentPipelineController;
use App\Http\Controllers\Api\SentimentPipelineNLPController;
use App\Http\Controllers\Api\GoogleCloudNLPController;
use App\Http\Controllers\Api\SentimentPriceTimelineController;
use App\Http\Controllers\Api\SmartExplorerController;
use App\Http\Controllers\Api\SocialMediaCrawlerController;
use App\Http\Controllers\Api\SourceCodeController;
use App\Http\Controllers\Api\SentimentChartController;
use App\Http\Controllers\Api\SentimentAnalysisController;
use App\Http\Controllers\Api\LiveContractAnalyzerController;
use App\Http\Controllers\Api\OnboardingEmailController;
use App\Http\Controllers\Api\MailgunWebhookController;
use App\Http\Controllers\Api\SolidityCleanerController;
use App\Http\Controllers\Api\SentimentTimelineController;
use App\Http\Controllers\Api\VerificationBadgeController;
use App\Http\Controllers\StreamingAnalysisController;
use App\Http\Controllers\Api\CacheController;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\DashboardSummaryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public health check for load testing
Route::get('health', [LoadTestController::class, 'health']);

// Load testing endpoints
Route::prefix('load-test')->middleware(['throttle:1000,1'])->group(function () {
    Route::post('analysis', [LoadTestController::class, 'simulateAnalysis']);
    Route::get('analysis/{analysisId}/status', [LoadTestController::class, 'analysisStatus']);
    Route::post('sentiment', [LoadTestController::class, 'simulateSentiment']);
    Route::get('complex-query', [LoadTestController::class, 'complexQuery']);
    Route::post('cpu-intensive', [LoadTestController::class, 'cpuIntensive']);
});

// Live Contract Analyzer API Routes (no auth required for public access)
Route::prefix('contracts')->name('contracts.')->group(function () {
    Route::post('/analyze', [LiveContractAnalyzerController::class, 'analyze'])->name('analyze');
    
    // Simple demo endpoint for landing page
    Route::post('/analyze-demo', function(Request $request) {
        $contractInput = $request->input('contract_input');
        $network = $request->input('network', 'ethereum');
        
        // Simple validation
        if (!$contractInput) {
            return response()->json([
                'success' => false,
                'message' => 'Contract input is required'
            ], 400);
        }
        
        // Mock analysis for demo
        $isAddress = preg_match('/^0x[a-fA-F0-9]{40}$/', $contractInput);
        $riskScore = $isAddress ? rand(15, 35) : rand(40, 70);
        $gasOptimization = rand(80, 95);
        
        // Known contracts with specific scores
        if ($contractInput === '0xE592427A0AEce92De3Edee1F18E0157C05861564') {
            $riskScore = 15; // Uniswap V3 - very low risk
            $gasOptimization = 92;
            $contractName = 'Uniswap V3 SwapRouter';
        } elseif ($contractInput === '0x87870Bca3F3fD6335C3F4ce8392D69350B4fA4E2') {
            $riskScore = 25; // Aave V3 - low risk
            $gasOptimization = 88;
            $contractName = 'Aave V3 Pool';
        } elseif ($contractInput === '0x27182842E098f60e3D576794A5bFFb0777E025d3') {
            $riskScore = 95; // Euler - exploited
            $gasOptimization = 60;
            $contractName = 'Euler Finance (EXPLOITED)';
        } else {
            $contractName = $isAddress ? 'Contract ' . substr($contractInput, 0, 10) . '...' : 'Smart Contract';
        }
        
        $findings = [
            [
                'id' => 1,
                'title' => 'Reentrancy Check',
                'severity' => $riskScore > 80 ? 'critical' : ($riskScore > 50 ? 'medium' : 'low'),
                'description' => $riskScore > 80 ? 'Critical reentrancy vulnerability detected' : 'No reentrancy issues found',
                'location' => 'Function calls',
                'recommendation' => $riskScore > 80 ? 'Implement ReentrancyGuard' : 'Continue monitoring'
            ],
            [
                'id' => 2,
                'title' => 'Access Control',
                'severity' => 'info',
                'description' => 'Proper access control mechanisms detected',
                'location' => 'Modifier functions',
                'recommendation' => 'Maintain current security practices'
            ]
        ];
        
        $optimizations = [
            [
                'id' => 1,
                'title' => 'Gas Efficiency',
                'description' => $gasOptimization > 85 ? 'Well-optimized gas usage' : 'Consider optimizing loops and storage operations'
            ],
            [
                'id' => 2,
                'title' => 'Storage Optimization',
                'description' => 'Use packed structs for better storage efficiency'
            ]
        ];
        
        return response()->json([
            'success' => true,
            'projectId' => 'demo-' . time(),
            'analysisId' => 'analysis-' . time(),
            'contractAddress' => $isAddress ? $contractInput : null,
            'contractName' => $contractName,
            'network' => $network,
            'riskScore' => $riskScore,
            'gasOptimization' => $gasOptimization,
            'findings' => $findings,
            'optimizations' => $optimizations,
            'analysisTime' => round(rand(1000, 3000) / 1000, 2),
            'timestamp' => now()->toISOString(),
            'demo' => true
        ]);
    })->name('analyze-demo');
});

// API Routes for Vue components (refactored from mock data)
Route::prefix('analytics')->name('api.analytics.')->group(function () {
    Route::get('/risk-matrix', [App\Http\Controllers\Api\AnalyticsController::class, 'getRiskMatrix'])->name('risk-matrix');
    Route::get('/security-trend', [App\Http\Controllers\Api\AnalyticsController::class, 'getSecurityTrend'])->name('security-trend');
});

Route::prefix('analyses')->name('api.analyses.')->group(function () {
    Route::get('/active', [App\Http\Controllers\Api\AnalysisMonitorController::class, 'getActiveAnalyses'])->name('active');
    Route::get('/queue', [App\Http\Controllers\Api\AnalysisMonitorController::class, 'getQueuedAnalyses'])->name('queue');
    Route::get('/metrics', [App\Http\Controllers\Api\AnalysisMonitorController::class, 'getMetrics'])->name('metrics');
});

Route::prefix('analysis')->name('api.analysis.')->group(function () {
    Route::get('/status', [App\Http\Controllers\Api\AnalysisStatusController::class, 'show'])->name('status');
    Route::post('/status/clear', [App\Http\Controllers\Api\AnalysisStatusController::class, 'clearCache'])->name('status.clear');
});

Route::prefix('blockchain')->name('api.blockchain.')->group(function () {
    Route::get('/networks', [App\Http\Controllers\Api\BlockchainController::class, 'getNetworks'])->name('networks');
    Route::get('/examples', [App\Http\Controllers\Api\BlockchainController::class, 'getExamples'])->name('examples');
    Route::get('/contract-info', [App\Http\Controllers\Api\BlockchainController::class, 'getContractInfo'])->name('contract-info');
    Route::post('/security-analysis', [App\Http\Controllers\Api\BlockchainController::class, 'performSecurityAnalysis'])->name('security-analysis');
    Route::post('/sentiment-analysis', [App\Http\Controllers\Api\BlockchainController::class, 'performSentimentAnalysis'])->name('sentiment-analysis');
});

Route::prefix('ai')->name('api.ai.')->group(function () {
    Route::get('/components/status', [App\Http\Controllers\Api\AIEngineController::class, 'getComponentsStatus'])->name('components.status');
});

// Sentiment API Routes
Route::prefix('sentiment')->name('api.sentiment.')->group(function () {
    Route::get('/live-trends', [App\Http\Controllers\Api\SentimentController::class, 'getLiveTrends'])->name('live-trends');
    Route::get('/{symbol}/timeline', [App\Http\Controllers\Api\SentimentController::class, 'getTimeline'])->name('timeline');
    Route::get('/{symbol}/current', [App\Http\Controllers\Api\SentimentController::class, 'getCurrent'])->name('current');
});

// Dashboard API Routes
Route::prefix('dashboard')->name('api.dashboard.')->group(function () {
    Route::get('/stats', [App\Http\Controllers\Api\DashboardController::class, 'getStats'])->name('stats');
    Route::get('/projects', [App\Http\Controllers\Api\DashboardController::class, 'getRecentProjects'])->name('projects');
    Route::get('/critical-findings', [App\Http\Controllers\Api\DashboardController::class, 'getCriticalFindings'])->name('critical-findings');
    Route::get('/ai-insights', [App\Http\Controllers\Api\DashboardController::class, 'getAIInsights'])->name('ai-insights');
    Route::get('/project/{id}', [App\Http\Controllers\Api\DashboardController::class, 'getProjectDetails'])->name('project');
});

// Verification Badge API Routes (with security middleware)
Route::prefix('verification-api')->name('api.verification.secure.')->middleware('verification.security')->group(function () {
    Route::post('/generate', [VerificationController::class, 'generateVerification'])->name('generate');
    Route::post('/batch', [VerificationController::class, 'batchGenerate'])->name('batch');
    Route::post('/badge', [VerificationController::class, 'generateBadge'])->name('badge');
    Route::get('/stats', [VerificationController::class, 'getStats'])->name('stats');
    Route::get('/check/{contract}', [VerificationController::class, 'checkVerification'])->name('check');
});

// Mailgun Webhook Routes (no auth required for webhook processing)
Route::prefix('webhooks')->name('webhooks.')->group(function () {
    Route::post('/mailgun', [App\Http\Controllers\Api\MailgunWebhookController::class, 'handle'])->name('mailgun');
});

// Solidity Cleaner API Routes (no auth required for utility access)
Route::prefix('solidity-cleaner')->name('solidity-cleaner.')->group(function () {
    Route::post('/clean', [SolidityCleanerController::class, 'clean'])->name('clean');
    Route::post('/quick-clean', [SolidityCleanerController::class, 'quickClean'])->name('quick-clean');
    Route::post('/clean-with-preset', [SolidityCleanerController::class, 'cleanWithPreset'])->name('clean-with-preset');
    Route::post('/validate', [SolidityCleanerController::class, 'validate'])->name('validate');
    Route::get('/options', [SolidityCleanerController::class, 'options'])->name('options');
});

// Sentiment Timeline API Routes (no auth required for public access)
Route::prefix('sentiment-timeline')->name('sentiment-timeline.')->group(function () {
    Route::get('/timeline', [SentimentTimelineController::class, 'timeline'])->name('timeline');
    Route::get('/correlation', [SentimentTimelineController::class, 'correlation'])->name('correlation');
    Route::get('/summary', [SentimentTimelineController::class, 'summary'])->name('summary');
});

// Verification Badge API Routes (rate limited for security)
Route::prefix('verification-badge')->name('verification-badge.')->group(function () {
    Route::post('/generate', [VerificationBadgeController::class, 'generate'])->name('generate');
    Route::post('/verify', [VerificationBadgeController::class, 'verify'])->name('verify');
    Route::post('/verify-url', [VerificationBadgeController::class, 'verifyUrl'])->name('verify-url');
    Route::get('/levels', [VerificationBadgeController::class, 'levels'])->name('levels');
    Route::get('/stats', [VerificationBadgeController::class, 'stats'])->name('stats');
    Route::post('/embed-code', [VerificationBadgeController::class, 'embedCode'])->name('embed-code');
});

// Famous Contracts API Routes
use App\Http\Controllers\Api\FamousContractsController;

Route::prefix('famous-contracts')->name('famous-contracts.')->group(function () {
    Route::get('/', [FamousContractsController::class, 'index'])->name('index');
    Route::get('/exploited', [FamousContractsController::class, 'exploited'])->name('exploited');
    Route::get('/risk/{level}', [FamousContractsController::class, 'byRiskLevel'])->name('by-risk-level');
    Route::get('/{address}', [FamousContractsController::class, 'show'])->name('show');
});

// Mailgun Webhook Routes (no auth required - signature verified in controller)
Route::prefix('webhooks/mailgun')->name('webhooks.mailgun.')->group(function () {
    Route::post('/events', [MailgunWebhookController::class, 'handleWebhook'])->name('events');
    
    // Individual event handlers (for flexibility)
    Route::post('/delivered', [MailgunWebhookController::class, 'handleWebhook'])->name('delivered');
    Route::post('/opened', [MailgunWebhookController::class, 'handleWebhook'])->name('opened');
    Route::post('/clicked', [MailgunWebhookController::class, 'handleWebhook'])->name('clicked');
    Route::post('/unsubscribed', [MailgunWebhookController::class, 'handleWebhook'])->name('unsubscribed');
    Route::post('/complained', [MailgunWebhookController::class, 'handleWebhook'])->name('complained');
    Route::post('/bounced', [MailgunWebhookController::class, 'handleWebhook'])->name('bounced');
});

// Project Management API Routes moved to web.php for session compatibility

// Contract Analysis API Routes
Route::prefix('analyses')->group(function () {
    Route::post('/', [AnalysisController::class, 'store'])->name('api.analyses.store');
    Route::get('/', [AnalysisController::class, 'index'])->name('api.analyses.index');
    Route::get('/stats', [AnalysisController::class, 'stats'])->name('api.analyses.stats');
    Route::get('/{id}', [AnalysisController::class, 'show'])->name('api.analyses.show');
    Route::get('/{id}/stream-status', [AnalysisController::class, 'streamStatus'])->name('api.analyses.stream-status');
    Route::post('/{id}/cancel', [AnalysisController::class, 'cancel'])->name('api.analyses.cancel');
    Route::post('/{id}/retry', [AnalysisController::class, 'retry'])->name('api.analyses.retry');
});

// Source Code API Routes - Fetch verified Solidity source via blockchain explorers
Route::prefix('source-code')->name('source-code.')->group(function () {
    // Core source code fetching
    Route::get('/fetch', [SourceCodeController::class, 'fetchSourceCode'])->name('fetch');
    Route::get('/abi', [SourceCodeController::class, 'fetchContractAbi'])->name('abi');
    Route::get('/creation', [SourceCodeController::class, 'getContractCreation'])->name('creation');
    
    // Verification and analysis
    Route::get('/verify', [SourceCodeController::class, 'checkVerificationStatus'])->name('verify');
    Route::get('/info', [SourceCodeController::class, 'getContractInfo'])->name('info');
    Route::get('/functions', [SourceCodeController::class, 'extractFunctionSignatures'])->name('functions');
    
    // Advanced features
    Route::post('/search', [SourceCodeController::class, 'searchByPattern'])->name('search');
    Route::post('/batch', [SourceCodeController::class, 'batchFetchSourceCode'])->name('batch');
    
    // System info
    Route::get('/networks', [SourceCodeController::class, 'getSupportedNetworks'])->name('networks');
});

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
        'version' => config('app.version', '1.0.0')
    ]);
})->name('api.health');

// Streaming Analysis API routes
Route::middleware(['auth:sanctum'])->prefix('streaming')->name('streaming-analysis.')->group(function () {
    // Analysis management
    Route::post('/start', [StreamingAnalysisController::class, 'start'])->name('start');
    Route::get('/{analysis}/status', [StreamingAnalysisController::class, 'status'])->name('status');
    Route::get('/{analysis}/results', [StreamingAnalysisController::class, 'results'])->name('results');
    Route::post('/{analysis}/cancel', [StreamingAnalysisController::class, 'cancel'])->name('cancel');
    
    // Real-time streaming
    Route::get('/{analysis}/stream', [StreamingAnalysisController::class, 'stream'])->name('stream');
    
    // List analyses
    Route::get('/', [StreamingAnalysisController::class, 'list'])->name('list');
});

// OWASP-Style Security Analysis API routes
Route::middleware(['auth:sanctum'])->prefix('security')->name('owasp-security.')->group(function () {
    // Contract security analysis
    Route::post('/analyze', [OWASPSecurityController::class, 'analyzeContract'])->name('analyze');
    
    // Schema and validation utilities
    Route::get('/categories', [OWASPSecurityController::class, 'getSupportedCategories'])->name('categories');
    Route::post('/validate-finding', [OWASPSecurityController::class, 'validateFinding'])->name('validate-finding');
    Route::get('/schema', [OWASPSecurityController::class, 'getSchemaInfo'])->name('schema');
    
    // Testing utilities
    Route::get('/example-contract', [OWASPSecurityController::class, 'getExampleContract'])->name('example-contract');
    
    // Security findings for dashboard (moved outside auth middleware below)
});

// Public security findings endpoint (mock data)
Route::get('/security/findings', [App\Http\Controllers\Api\OWASPSecurityController::class, 'getFindings'])->name('security.findings');



// Network monitoring API routes (public for status information)
Route::prefix('network')->name('network.')->group(function () {
    // Network status information
    Route::get('/status', [App\Http\Controllers\Api\NetworkController::class, 'getStatus'])->name('status');
});

// Crawler Micro-Service API routes
Route::middleware(['auth:sanctum'])->prefix('crawler')->name('crawler-microservice.')->group(function () {
    // Job management
    Route::post('/start', [CrawlerMicroServiceController::class, 'startCrawlJob'])->name('start');
    Route::get('/job/{jobId}/status', [CrawlerMicroServiceController::class, 'getJobStatus'])->name('job-status');
    Route::get('/stats', [CrawlerMicroServiceController::class, 'getPlatformStats'])->name('stats');
    Route::get('/health', [CrawlerMicroServiceController::class, 'getHealthStatus'])->name('health');
    
    // Keyword rules management
    Route::get('/keyword-rules', [CrawlerMicroServiceController::class, 'getKeywordRules'])->name('keyword-rules.index');
    Route::post('/keyword-rules', [CrawlerMicroServiceController::class, 'createKeywordRule'])->name('keyword-rules.store');
    Route::put('/keyword-rules/{id}', [CrawlerMicroServiceController::class, 'updateKeywordRule'])->name('keyword-rules.update');
    Route::delete('/keyword-rules/{id}', [CrawlerMicroServiceController::class, 'deleteKeywordRule'])->name('keyword-rules.destroy');
    Route::post('/keyword-rules/test', [CrawlerMicroServiceController::class, 'testKeywordRule'])->name('keyword-rules.test');
});

// Complete Sentiment Analysis Pipeline API routes
Route::middleware(['auth:sanctum'])->prefix('sentiment-pipeline')->name('sentiment-pipeline.')->group(function () {
    // New text processing pipeline
    Route::post('/process-text', [SentimentPipelineController::class, 'processText'])->name('process-text');
    Route::get('/batch-status/{batchId}', [SentimentPipelineController::class, 'getBatchStatus'])->name('batch-status');
    
    // Daily aggregates and trends
    Route::get('/daily-aggregates', [SentimentPipelineController::class, 'getDailyAggregates'])->name('daily-aggregates');
    Route::get('/trends', [SentimentPipelineController::class, 'getSentimentTrends'])->name('trends');
    
    // Configuration
    Route::get('/configuration', [SentimentPipelineController::class, 'getConfiguration'])->name('configuration');
    
    // Legacy routes (kept for backward compatibility)
    Route::post('/execute', [SentimentPipelineController::class, 'processText'])->name('execute');
    Route::get('/status/{pipelineId}', [SentimentPipelineController::class, 'getBatchStatus'])->name('status');
});

// Vue PDF Generation API routes
Route::middleware(['auth:sanctum'])->prefix('vue-pdf')->name('vue-pdf.')->group(function () {
    // Generate PDF from Vue components
    Route::post('/generate', [\App\Http\Controllers\VuePdfController::class, 'generateFromVueComponent'])->name('generate');
    Route::post('/sentiment-dashboard', [\App\Http\Controllers\VuePdfController::class, 'generateSentimentDashboard'])->name('sentiment-dashboard');
    Route::post('/sentiment-price-chart', [\App\Http\Controllers\VuePdfController::class, 'generateSentimentPriceChart'])->name('sentiment-price-chart');
    Route::post('/batch-generate', [\App\Http\Controllers\VuePdfController::class, 'batchGenerate'])->name('batch-generate');
    
    // Statistics and testing
    Route::get('/stats', [\App\Http\Controllers\VuePdfController::class, 'getGenerationStats'])->name('stats');
    Route::post('/test', [\App\Http\Controllers\VuePdfController::class, 'testGeneration'])->name('test');
});

// OpenAI Streaming Jobs API routes
Route::middleware(['auth:sanctum'])->prefix('openai-jobs')->name('openai-jobs.')->group(function () {
    // Job management
    Route::post('/', [OpenAiJobController::class, 'createJob'])->name('create');
    Route::get('/list', [OpenAiJobController::class, 'listJobs'])->name('list');
    
    // Job monitoring and results
    Route::get('/{jobId}/status', [OpenAiJobController::class, 'getJobStatus'])->name('status');
    Route::get('/{jobId}/stream', [OpenAiJobController::class, 'getStreamingUpdates'])->name('stream');
    Route::get('/{jobId}/result', [OpenAiJobController::class, 'getJobResult'])->name('result');
    
    // Job control
    Route::delete('/{jobId}', [OpenAiJobController::class, 'cancelJob'])->name('cancel');
});

// OpenAI Real-time Streaming API routes (with Server-Sent Events)
Route::middleware(['auth:sanctum'])->prefix('openai-streaming')->name('openai-streaming.')->group(function () {
    // Create streaming job with real-time token updates
    Route::post('/', [OpenAiStreamingController::class, 'createStreamingJob'])->name('create');
    
    // Real-time streaming endpoints
    Route::get('/{jobId}/sse', [OpenAiStreamingController::class, 'streamServerSentEvents'])->name('sse');
    
    // Job status and progress monitoring
    Route::get('/{jobId}/status', [OpenAiStreamingController::class, 'getJobStatus'])->name('status');
    Route::get('/{jobId}/progress', [OpenAiStreamingController::class, 'getJobProgress'])->name('progress');
    Route::get('/{jobId}/progress-history', [OpenAiStreamingController::class, 'getJobProgressHistory'])->name('progress-history');
    Route::get('/{jobId}/result', [OpenAiStreamingController::class, 'getJobResult'])->name('result');
    
    // Job control
    Route::delete('/{jobId}/cancel', [OpenAiStreamingController::class, 'cancelJob'])->name('cancel');
    
    // System statistics and monitoring
    Route::get('/stats', [OpenAiStreamingController::class, 'getStreamingStats'])->name('stats');
});

// Dashboard Summary API route  
Route::get('/dashboard/summary', [DashboardSummaryController::class, 'show']);

// OpenAI Horizon Job Monitoring API routes (Laravel Horizon integration)
Route::middleware(['auth:sanctum'])->prefix('openai-horizon')->name('openai-horizon.')->group(function () {
    // Main monitoring dashboard
    Route::get('/dashboard', [OpenAiHorizonController::class, 'getDashboard'])->name('dashboard');
    
    // Queue statistics and monitoring
    Route::get('/queue-stats', [OpenAiHorizonController::class, 'getQueueStats'])->name('queue-stats');
    Route::get('/workload', [OpenAiHorizonController::class, 'getWorkload'])->name('workload');
    Route::get('/performance', [OpenAiHorizonController::class, 'getPerformanceMetrics'])->name('performance');
    
    // Failed jobs management
    Route::get('/failed-jobs', [OpenAiHorizonController::class, 'getFailedJobs'])->name('failed-jobs');
    Route::post('/retry-jobs', [OpenAiHorizonController::class, 'retryFailedJobs'])->name('retry-jobs');
    
    // System health and control
    Route::get('/system-health', [OpenAiHorizonController::class, 'getSystemHealth'])->name('system-health');
    Route::post('/queue/toggle', [OpenAiHorizonController::class, 'toggleQueue'])->name('toggle-queue');
});

// PDF Generation API routes
Route::middleware(['auth:sanctum'])->prefix('pdf')->name('api.pdf.')->group(function () {
    // Generate PDFs
    Route::post('/dashboard', [\App\Http\Controllers\PdfController::class, 'generateDashboardPdf'])->name('dashboard');
    Route::post('/sentiment', [\App\Http\Controllers\PdfController::class, 'generateSentimentPdf'])->name('sentiment');
    Route::post('/crawler', [\App\Http\Controllers\PdfController::class, 'generateCrawlerPdf'])->name('crawler');
    
    // Management
    Route::get('/statistics', [\App\Http\Controllers\PdfController::class, 'getStatistics'])->name('statistics');
    Route::post('/cleanup', [\App\Http\Controllers\PdfController::class, 'cleanup'])->name('pdf.cleanup');
    
    // Test generation
    Route::post('/test', [\App\Http\Controllers\PdfController::class, 'test'])->name('test');
});

// PostgreSQL Cache Management API routes
Route::middleware(['auth:sanctum'])->prefix('cache')->name('cache.')->group(function () {
    // Cache statistics and monitoring
    Route::get('/stats', [CacheController::class, 'stats'])->name('stats');
    
    // Cache warming for demo presentations
    Route::post('/warm', [CacheController::class, 'warm'])->name('warm');
    
    // Cache management operations
    Route::post('/cleanup', [CacheController::class, 'cleanup'])->name('cache.cleanup');
    Route::delete('/clear', [CacheController::class, 'clear'])->name('clear');
    
    // Individual cache entry operations
    Route::get('/entry', [CacheController::class, 'get'])->name('get');
    Route::post('/entry', [CacheController::class, 'put'])->name('put');
    Route::delete('/entry', [CacheController::class, 'invalidate'])->name('invalidate');
    
    // Demo data management
    Route::get('/demo', [CacheController::class, 'getDemoData'])->name('demo.get');
    Route::post('/demo/initialize', [CacheController::class, 'initializeDemoData'])->name('demo.initialize');
});

// Sentiment Analysis Chart Data API routes (Public access for charts)
Route::prefix('sentiment')->name('sentiment.')->group(function () {
    // Chart data endpoints
    Route::get('/price-correlation', [SentimentAnalysisController::class, 'getSentimentPriceCorrelation'])->name('price-correlation');
    Route::get('/available-coins', [SentimentAnalysisController::class, 'getAvailableCoins'])->name('available-coins');
    Route::get('/summary', [SentimentAnalysisController::class, 'getSentimentSummary'])->name('summary');
    Route::get('/current-summary', [SentimentAnalysisController::class, 'getCurrentSentimentSummary'])->name('current-summary');
});

// PDF Generation API routes
Route::prefix('pdf')->name('pdf.')->group(function () {
    // Sentiment chart PDF generation
    Route::post('/sentiment-chart', [PdfController::class, 'generateSentimentChart'])->name('sentiment-chart');
    Route::get('/sentiment-chart/preview', [PdfController::class, 'previewSentimentChart'])->name('sentiment-chart.preview');
    
    // Generic PDF generation
    Route::post('/generate-from-view', [PdfController::class, 'generateFromView'])->name('generate-from-view');
    Route::post('/generate-from-url', [PdfController::class, 'generateFromUrl'])->name('generate-from-url');
    
    // PDF engine information
    Route::get('/engine-info', [PdfController::class, 'getEngineInfo'])->name('engine-info');
});

// Public verification endpoints (no auth required)
Route::prefix('verification')->name('api.verification.public.')->group(function () {
    // Get verification statistics (public for display)
    Route::get('/stats', [VerificationController::class, 'getStats'])->name('stats');
    
    // Check verification status for a contract (public)
    Route::get('/status', [VerificationController::class, 'getStatus'])->name('status');
    
    // Get verification badge HTML/CSS/JSON (public)
    Route::get('/badge', [VerificationController::class, 'getBadge'])->name('public.badge');
});

// Authenticated verification endpoints (using web auth with session middleware for Inertia.js)
Route::middleware(['web', 'auth'])->prefix('verification')->name('api.verification.auth.')->group(function () {
    // Generate cryptographically signed verification URL (standard)
    Route::post('/generate', [VerificationController::class, 'generateVerificationUrl'])->name('generate');
    
    // Generate enhanced verification URL with SHA-256 + HMAC
    Route::post('/generate-enhanced', [\App\Http\Controllers\EnhancedVerificationController::class, 'generateVerificationUrl'])->name('generate-enhanced');
    
    // List all verified contracts (authenticated for user's contracts)
    Route::get('/verified', [VerificationController::class, 'listVerified'])->name('list');
    
    // Admin endpoints (should have admin middleware in production)
    Route::delete('/revoke', [VerificationController::class, 'revoke'])->name('revoke');
});

// Public verification badge CSS endpoint (no auth required)
Route::get('/verification/badge.css', [VerificationController::class, 'getBadgeCSS'])->name('api.verification.css');

// Social Media Crawler API routes
Route::middleware(['auth:sanctum'])->prefix('social-media')->name('social-media.')->group(function () {
    // Posts management
    Route::get('/', [SocialMediaCrawlerController::class, 'index'])->name('posts');
    Route::get('/stats', [SocialMediaCrawlerController::class, 'stats'])->name('stats');
    
    // Manual crawling
    Route::post('/crawl', [SocialMediaCrawlerController::class, 'crawl'])->name('crawl');
    
    // Rules management
    Route::get('/rules', [SocialMediaCrawlerController::class, 'rules'])->name('rules.index');
    Route::post('/rules', [SocialMediaCrawlerController::class, 'createRule'])->name('rules.store');
    Route::put('/rules/{rule}', [SocialMediaCrawlerController::class, 'updateRule'])->name('rules.update');
    Route::delete('/rules/{rule}', [SocialMediaCrawlerController::class, 'deleteRule'])->name('rules.destroy');
});

// Google Cloud NLP Sentiment Pipeline API routes
Route::middleware(['auth:sanctum'])->prefix('sentiment-nlp')->name('sentiment-nlp.')->group(function () {
    // Daily aggregates
    Route::get('/aggregates', [SentimentPipelineNLPController::class, 'getDailyAggregates'])->name('aggregates');
    Route::get('/trends', [SentimentPipelineNLPController::class, 'getSentimentTrends'])->name('trends');
    
    // Processing
    Route::post('/process-daily', [SentimentPipelineNLPController::class, 'processDailySentiment'])->name('process-daily');
    Route::post('/process-posts', [SentimentPipelineNLPController::class, 'processPostsSentiment'])->name('process-posts');
    
    // Keyword analysis
    Route::post('/keyword-sentiment', [SentimentPipelineNLPController::class, 'getKeywordSentiment'])->name('keyword-sentiment');
    
    // Status and health
    Route::get('/status', [SentimentPipelineNLPController::class, 'getPipelineStatus'])->name('status');
    Route::get('/health', [SentimentPipelineNLPController::class, 'getNLPServiceHealth'])->name('health');
});

// Google Cloud NLP Pipeline API routes - Text → Sentiment → Daily Aggregates
Route::middleware(['auth:sanctum'])->prefix('google-nlp')->name('google-nlp.')->group(function () {
    // Main pipeline processing
    Route::post('/process-texts', [GoogleCloudNLPController::class, 'processTexts'])->name('process-texts');
    Route::post('/process-single', [GoogleCloudNLPController::class, 'processSingleText'])->name('process-single');
    
    // Batch status and monitoring
    Route::get('/batch/{batchId}/status', [GoogleCloudNLPController::class, 'getBatchStatus'])->name('batch-status');
    
    // Daily aggregates
    Route::get('/daily-aggregates', [GoogleCloudNLPController::class, 'getDailyAggregates'])->name('daily-aggregates');
    
    // Health and status
    Route::get('/health', [GoogleCloudNLPController::class, 'getHealthStatus'])->name('health');
});

// Sentiment Price Timeline API routes (public access for charts)
Route::prefix('sentiment-price-timeline')->name('sentiment-price-timeline.')->group(function () {
    // Real data with Coingecko API integration
    Route::get('/', [SentimentPriceTimelineController::class, 'getData'])->name('data');
    
    // Demo data for testing without API calls
    Route::get('/demo', [SentimentPriceTimelineController::class, 'getDemoData'])->name('demo');
    
    // Available coins for dropdown
    Route::get('/coins', [SentimentPriceTimelineController::class, 'getAvailableCoins'])->name('coins');
});

// Sentiment Chart API routes (public access for dashboard widgets)
Route::prefix('sentiment-charts')->name('api.sentiment-charts.')->group(function () {
    Route::get('/data', [\App\Http\Controllers\Api\SentimentChartController::class, 'getSentimentPriceData'])->name('data');
    Route::post('/data', [\App\Http\Controllers\Api\SentimentChartController::class, 'getSentimentPriceData'])->name('data.post');
    Route::get('/coins', [\App\Http\Controllers\Api\SentimentChartController::class, 'getAvailableCoins'])->name('coins');
    Route::get('/coins/search', [\App\Http\Controllers\Api\SentimentChartController::class, 'searchCoins'])->name('coins.search');
    Route::get('/sentiment-summary', [\App\Http\Controllers\Api\SentimentChartController::class, 'getSentimentSummary'])->name('sentiment-summary');
});

// Vue PDF Generation API routes (authenticated)
Route::middleware(['auth:sanctum'])->prefix('vue-pdf')->name('vue-pdf.')->group(function () {
    // Generate PDF from Vue component
    Route::post('/generate', [\App\Http\Controllers\VuePdfController::class, 'generateFromVueComponent'])->name('generate');
    
    // Generate sentiment dashboard PDF
    Route::post('/sentiment-dashboard', [\App\Http\Controllers\VuePdfController::class, 'generateSentimentDashboard'])->name('sentiment-dashboard');
    
    // Generate sentiment price chart PDF
    Route::post('/sentiment-price-chart', [\App\Http\Controllers\VuePdfController::class, 'generateSentimentPriceChart'])->name('sentiment-price-chart');
    
    // Batch generate multiple PDFs
    Route::post('/batch', [\App\Http\Controllers\VuePdfController::class, 'batchGenerate'])->name('batch');
    
    // Get generation statistics
    Route::get('/stats', [\App\Http\Controllers\VuePdfController::class, 'getGenerationStats'])->name('stats');
    
    // Test generation
    Route::post('/test', [\App\Http\Controllers\VuePdfController::class, 'testGeneration'])->name('test');
});

// Unified Vue PDF API Routes (Enhanced)
Route::prefix('unified-vue-pdf')->name('unified-vue-pdf.')->middleware(['auth:sanctum'])->group(function () {
    // Generate PDF from Vue component (primary method)
    Route::post('/component', [\App\Http\Controllers\UnifiedVuePdfController::class, 'generateFromComponent'])->name('component');
    
    // Generate PDF from Vue route
    Route::post('/route', [\App\Http\Controllers\UnifiedVuePdfController::class, 'generateFromRoute'])->name('route');
    
    // Batch generate multiple PDFs
    Route::post('/batch', [\App\Http\Controllers\UnifiedVuePdfController::class, 'batchGenerate'])->name('batch');
    
    // Generate sentiment dashboard PDF
    Route::post('/sentiment-dashboard', [\App\Http\Controllers\UnifiedVuePdfController::class, 'generateSentimentDashboard'])->name('sentiment-dashboard');
    
    // Generate analytics dashboard PDF
    Route::post('/analytics-dashboard', [\App\Http\Controllers\UnifiedVuePdfController::class, 'generateAnalyticsDashboard'])->name('analytics-dashboard');
    
    // Service management
    Route::get('/status', [\App\Http\Controllers\UnifiedVuePdfController::class, 'getServiceStatus'])->name('status');
    Route::get('/files', [\App\Http\Controllers\UnifiedVuePdfController::class, 'listGeneratedFiles'])->name('files');
    
    // Test generation with sample data
    Route::post('/test', [\App\Http\Controllers\UnifiedVuePdfController::class, 'testGeneration'])->name('test');
});

// Vue PDF Demo API Routes
Route::prefix('vue-pdf')->name('vue-pdf.')->middleware(['auth'])->group(function () {
    // Generate PDF from Vue component
    Route::post('/generate', [\App\Http\Controllers\VuePdfDemoController::class, 'generatePdf'])->name('generate');
    
    // Generate sentiment chart PDF
    Route::post('/sentiment-chart', [\App\Http\Controllers\VuePdfDemoController::class, 'generateSentimentChart'])->name('sentiment-chart');
    
    // Generate dashboard PDF
    Route::post('/dashboard', [\App\Http\Controllers\VuePdfDemoController::class, 'generateDashboard'])->name('dashboard');
    
    // Test PDF generation
    Route::post('/test', [\App\Http\Controllers\VuePdfDemoController::class, 'testGeneration'])->name('test');
    
    // Get service status
    Route::get('/status', [\App\Http\Controllers\VuePdfDemoController::class, 'getServiceStatus'])->name('status');
});

// PDF Preview Routes
Route::prefix('pdf-preview')->name('pdf-preview.')->group(function () {
    Route::get('/{type}', [\App\Http\Controllers\VuePdfDemoController::class, 'servePreview'])->name('serve');
});

// Onboarding Email API Routes
Route::prefix('onboarding')->name('onboarding.')->group(function () {
    // Public routes
    Route::post('/unsubscribe', [OnboardingEmailController::class, 'unsubscribe'])->name('unsubscribe');
    Route::post('/webhook', [OnboardingEmailController::class, 'webhook'])->name('webhook');
    
    // Authenticated routes
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/progress', [OnboardingEmailController::class, 'getProgress'])->name('progress');
        Route::put('/preferences', [OnboardingEmailController::class, 'updatePreferences'])->name('preferences');
        Route::post('/resend', [OnboardingEmailController::class, 'resendEmail'])->name('resend');
        Route::get('/statistics', [OnboardingEmailController::class, 'getStatistics'])->name('statistics');
        Route::post('/test', [OnboardingEmailController::class, 'testEmail'])->name('test');
    });
});

// Smart Blockchain Explorer API Routes
Route::prefix('smart-explorer')->group(function () {
    // Chain detection
    Route::post('/detect', [SmartExplorerController::class, 'detectChain'])->name('api.smart-explorer.detect');
    
    // Contract source with intelligent switching
    Route::post('/contract/source', [SmartExplorerController::class, 'getContractSource'])->name('api.smart-explorer.source');
    
    // Contract verification check
    Route::post('/contract/verify', [SmartExplorerController::class, 'checkVerification'])->name('api.smart-explorer.verify');
    
    // Get optimal explorer for a contract
    Route::post('/optimal', [SmartExplorerController::class, 'getOptimalExplorer'])->name('api.smart-explorer.optimal');
    
    // System health and statistics
    Route::get('/stats', [SmartExplorerController::class, 'getSystemStats'])->name('api.smart-explorer.stats');
    
    // Supported networks
    Route::get('/networks', [SmartExplorerController::class, 'getSupportedNetworks'])->name('api.smart-explorer.networks');
    
    // Cache management
    Route::delete('/cache', [SmartExplorerController::class, 'clearCache'])->name('api.smart-explorer.clear-cache');
    
    // Batch processing
    Route::post('/batch', [SmartExplorerController::class, 'batchProcess'])->name('api.smart-explorer.batch');
});

// OpenAI Streaming Job Management API Routes
Route::middleware(['auth:sanctum'])->prefix('openai-streaming')->name('api.openai-streaming.')->group(function () {
    // Job lifecycle management
    Route::post('/start', [\App\Http\Controllers\Api\OpenAiStreamingController::class, 'startStreamingJob'])->name('start');
    Route::get('/jobs', [\App\Http\Controllers\Api\OpenAiStreamingController::class, 'listJobs'])->name('list');
    Route::get('/analytics', [\App\Http\Controllers\Api\OpenAiStreamingController::class, 'getAnalytics'])->name('analytics');
    
    // Individual job management
    Route::get('/{jobId}/status', [\App\Http\Controllers\Api\OpenAiStreamingController::class, 'getJobStatus'])->name('status');
    Route::get('/{jobId}/stream', [\App\Http\Controllers\Api\OpenAiStreamingController::class, 'getStreamingData'])->name('stream');
    Route::get('/{jobId}/results', [\App\Http\Controllers\Api\OpenAiStreamingController::class, 'getJobResults'])->name('results');
    Route::post('/{jobId}/cancel', [\App\Http\Controllers\Api\OpenAiStreamingController::class, 'cancelJob'])->name('cancel');
});

// Sentiment vs Price Chart API Routes
Route::prefix('sentiment-price')->name('api.sentiment-price.')->group(function () {
    // Public endpoints (no auth required)
    Route::get('/data', [\App\Http\Controllers\Api\SentimentPriceController::class, 'getSentimentPriceData'])->name('data');
    Route::get('/tokens', [\App\Http\Controllers\Api\SentimentPriceController::class, 'getAvailableTokens'])->name('tokens');
    Route::get('/snapshot', [\App\Http\Controllers\Api\SentimentPriceController::class, 'getRealTimeSnapshot'])->name('snapshot');
});

// Verification Badge API Routes
Route::prefix('verification')->name('api.verification.')->group(function () {
    // Public verification endpoints
    Route::post('/verify', [\App\Http\Controllers\VerificationBadgeController::class, 'verifyBadge'])->name('verify');
    Route::get('/statistics', [\App\Http\Controllers\VerificationBadgeController::class, 'getStatistics'])->name('statistics');
    
    // Badge generation (requires authentication)
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/generate', [\App\Http\Controllers\VerificationBadgeController::class, 'generateBadge'])->name('generate');
        Route::post('/generate/contract', [\App\Http\Controllers\VerificationBadgeController::class, 'generateContractBadge'])->name('generate-contract');
        Route::post('/revoke', [\App\Http\Controllers\VerificationBadgeController::class, 'revokeBadge'])->name('revoke');
    });
});

// Enhanced PDF Generation API Routes (New Service)
Route::prefix('pdf')->name('api.pdf.')->group(function () {
    // Service status
    Route::get('/status', [PdfController::class, 'getNewServiceStatus'])->name('status');
    
    // Public routes (no auth required for testing)
    Route::post('/test', [PdfController::class, 'testGeneration'])->name('test');
    Route::post('/html', [PdfController::class, 'generateFromHtml'])->name('html');
    
    // Authenticated routes
    Route::middleware(['auth:sanctum'])->group(function () {
        // Vue view PDF generation (Browserless)
        Route::post('/vue-view', [PdfController::class, 'generateFromVueView'])->name('vue-view');
        Route::post('/browserless', [PdfController::class, 'generateWithBrowserless'])->name('browserless');
        
        // Report generation endpoints
        Route::post('/sentiment-report', [PdfController::class, 'generateSentimentReport'])->name('sentiment-report');
        Route::post('/social-report', [PdfController::class, 'generateSocialReport'])->name('social-report');
        Route::post('/blockchain-report', [PdfController::class, 'generateBlockchainReport'])->name('blockchain-report');
        
        // PDF management
        Route::get('/download/{filename}', [PdfController::class, 'download'])->name('download');
        Route::delete('/cleanup', [PdfController::class, 'cleanupNew'])->name('pdf.cleanup.new');
    });
});

// Enhanced Sentiment Pipeline API Routes
Route::prefix('sentiment')->name('sentiment.')->group(function () {
    Route::post('/process', [\App\Http\Controllers\Api\EnhancedSentimentPipelineController::class, 'processText'])->name('process');
    Route::post('/process-and-aggregate', [\App\Http\Controllers\Api\EnhancedSentimentPipelineController::class, 'processAndAggregate'])->name('process.aggregate');
    Route::post('/queue-batches', [\App\Http\Controllers\Api\EnhancedSentimentPipelineController::class, 'queueBatches'])->name('queue.batches');
    Route::post('/estimate-cost', [\App\Http\Controllers\Api\EnhancedSentimentPipelineController::class, 'estimateCost'])->name('estimate.cost');
    
    Route::prefix('aggregates')->name('aggregates.')->group(function () {
        Route::get('/daily', [\App\Http\Controllers\Api\EnhancedSentimentPipelineController::class, 'getDailyAggregates'])->name('daily');
        Route::post('/generate', [\App\Http\Controllers\Api\EnhancedSentimentPipelineController::class, 'generateAggregates'])->name('generate');
    });
    
    Route::get('/performance', [\App\Http\Controllers\Api\EnhancedSentimentPipelineController::class, 'getPerformance'])->name('performance');
    Route::get('/status', [\App\Http\Controllers\Api\EnhancedSentimentPipelineController::class, 'getStatus'])->name('status');
    Route::get('/trends', [\App\Http\Controllers\Api\EnhancedSentimentPipelineController::class, 'getTrends'])->name('trends');
});

// Secure Verification Badge API Routes
Route::prefix('verification')->name('verification.')->group(function () {
    Route::post('/generate-secure-badge', [\App\Http\Controllers\Api\SecureVerificationController::class, 'generateSecureBadge'])->name('generate.secure');
    Route::post('/verify-secure-badge', [\App\Http\Controllers\Api\SecureVerificationController::class, 'verifySecureBadge'])->name('verify.secure');
    Route::get('/badge-display/{token}', [\App\Http\Controllers\Api\SecureVerificationController::class, 'getBadgeDisplay'])->name('badge.display');
    Route::post('/revoke-badge', [\App\Http\Controllers\Api\SecureVerificationController::class, 'revokeBadge'])->name('revoke');
    Route::get('/stats', [\App\Http\Controllers\Api\SecureVerificationController::class, 'getVerificationStats'])->name('stats');
    Route::get('/levels', [\App\Http\Controllers\Api\SecureVerificationController::class, 'getVerificationLevels'])->name('levels');
});

// Quick Contract Analysis API Routes
Route::prefix('contract')->name('contract.')->group(function () {
    Route::post('/quick-info', [\App\Http\Controllers\Api\QuickAnalysisController::class, 'getQuickInfo'])->name('quick.info');
    Route::get('/networks', [\App\Http\Controllers\Api\QuickAnalysisController::class, 'getSupportedNetworks'])->name('networks');
    Route::get('/popular', [\App\Http\Controllers\Api\QuickAnalysisController::class, 'getPopularContracts'])->name('popular');
});

Route::prefix('analysis')->name('analysis.')->group(function () {
    Route::post('/quick-analyze', [\App\Http\Controllers\Api\QuickAnalysisController::class, 'quickAnalyze'])->name('quick.analyze');
    Route::get('/status/{analysisId}', [\App\Http\Controllers\Api\QuickAnalysisController::class, 'getAnalysisStatus'])->name('status');
    Route::delete('/cancel/{analysisId}', [\App\Http\Controllers\Api\QuickAnalysisController::class, 'cancelAnalysis'])->name('cancel');
});

