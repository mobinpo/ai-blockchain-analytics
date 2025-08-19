<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\SentimentAnalysisController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\Api\SentimentChartController;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\EnhancedPdfController;
use App\Http\Controllers\EnhancedVerificationController;
use App\Http\Controllers\VerificationController;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Landing');
})->name('landing');

Route::get('/welcome', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
})->name('welcome');

Route::get('/pricing', function () {
    $monthlyPlans = SubscriptionPlan::active()
        ->byInterval('month')
        ->ordered()
        ->get();
        
    $yearlyPlans = SubscriptionPlan::active()
        ->byInterval('year')
        ->ordered()
        ->get();

    return Inertia::render('Pricing', [
        'monthlyPlans' => $monthlyPlans,
        'yearlyPlans' => $yearlyPlans,
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
})->name('pricing');

Route::get('/dashboard', function () {
    $projectId = request('project');
    
    return Inertia::render('Dashboard', [
        'projectId' => $projectId,
        'showingProject' => !is_null($projectId)
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

// Redirect old subscription URL to billing
Route::get('/subscription', function () {
    return redirect('/billing');
})->middleware(['auth', 'verified'])->name('subscription.redirect');

// Verification Badge Routes (with security middleware)
Route::get('/get-verified', [VerificationController::class, 'index'])->name('verification.index');
Route::get('/verify-contract', [VerificationController::class, 'showVerification'])
    ->middleware('verification.security')
    ->name('verification.show');

Route::get('/sentiment-dashboard', function () {
    return Inertia::render('SentimentDashboard');
})->name('sentiment-dashboard');

// Verification Badge Routes
Route::prefix('verification')->name('verification.')->group(function () {
    Route::get('/badge', function () {
        return Inertia::render('VerificationBadge', [
            'badge_id' => request('badge_id'),
            'signature' => request('signature'),
            'type' => request('type')
        ]);
    })->name('badge');
    
    Route::get('/verify/{badge_id}/{signature}', function ($badgeId, $signature) {
        return Inertia::render('VerificationBadge', [
            'badge_id' => $badgeId,
            'signature' => $signature
        ]);
    })->name('verify');
    
    Route::get('/generator', function () {
        return Inertia::render('VerificationGenerator');
    })->name('generator');
});

Route::get('/projects', function () {
    return Inertia::render('Projects');
})->name('projects');

Route::get('/projects/{id}', function ($id) {
    return Inertia::render('ProjectDetails', [
        'projectId' => $id
    ]);
})->middleware(['auth', 'verified'])->name('projects.show');

// Project API Routes (moved from api.php for session compatibility)
Route::middleware(['auth'])->prefix('api/projects')->name('api.projects.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\ProjectController::class, 'index'])->name('index');
    Route::post('/', [\App\Http\Controllers\Api\ProjectController::class, 'store'])->name('store');
    Route::get('/{id}', [\App\Http\Controllers\Api\ProjectController::class, 'show'])->name('show');
});

Route::get('/security', function () {
    return Inertia::render('Security');
})->name('security');

Route::get('/sentiment', function () {
    return Inertia::render('Sentiment');
})->name('sentiment');

Route::get('/sentiment-chart-demo', function () {
    return Inertia::render('SentimentChartDemo');
})->middleware(['auth', 'verified'])->name('sentiment-chart-demo');

Route::get('/sentiment-price-chart', function () {
    return Inertia::render('SentimentPriceChart');
})->name('sentiment-price-chart');

// Verification Badge Routes
Route::prefix('verification')->name('verification.')->group(function () {
    Route::get('/badge/{token}', [\App\Http\Controllers\VerificationBadgeController::class, 'showBadge'])->name('badge');
    Route::get('/verify/{token}', [\App\Http\Controllers\VerificationBadgeController::class, 'showVerification'])->name('verify');
    Route::get('/embed/{token}', [\App\Http\Controllers\VerificationBadgeController::class, 'embedBadge'])->name('embed');
});

Route::get('/css-test', function () {
    return Inertia::render('CssTest');
})->name('css-test');

// Test monitoring systems endpoint
Route::get('/test-monitoring', function () {
    try {
        // Test Sentry context
        \Sentry\configureScope(function (\Sentry\State\Scope $scope): void {
            $scope->setTag('test_monitoring', true);
            $scope->setExtra('timestamp', now()->toISOString());
        });

        // Test data for monitoring
        $testData = [
            'sentry_configured' => config('sentry.dsn') !== null,
            'telescope_enabled' => config('telescope.enabled'),
            'middleware_loaded' => class_exists('\App\Http\Middleware\SentryContext'),
            'environment' => app()->environment(),
            'timestamp' => now()->toISOString(),
        ];

        // Trigger a test log entry
        \Log::info('Monitoring systems test completed', $testData);

        return response()->json([
            'status' => 'success',
            'message' => 'Monitoring systems are working correctly',
            'data' => $testData,
        ]);

    } catch (\Exception $e) {
        // This will be caught by Sentry
        \Log::error('Monitoring test failed', ['error' => $e->getMessage()]);
        
        return response()->json([
            'status' => 'error',
            'message' => 'Monitoring test failed',
            'error' => $e->getMessage(),
        ], 500);
    }
})->name('test-monitoring');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Sentiment Analysis routes
Route::middleware(['auth'])->prefix('sentiment-analysis')->name('sentiment-analysis.')->group(function () {
    Route::get('/', [SentimentAnalysisController::class, 'index'])->name('index');
    Route::get('/chart', [SentimentAnalysisController::class, 'sentimentPriceChart'])->name('chart');
    Route::get('/platform', [SentimentAnalysisController::class, 'platformAnalysis'])->name('platform');
    Route::get('/trends', [SentimentAnalysisController::class, 'trends'])->name('trends');
    Route::get('/correlations', [SentimentAnalysisController::class, 'correlations'])->name('correlations');
});

// Billing routes
Route::middleware(['auth'])->prefix('billing')->name('billing.')->group(function () {
    Route::get('/', [BillingController::class, 'index'])->name('index');
    Route::get('/plans', [BillingController::class, 'plans'])->name('plans');
    Route::get('/usage', [BillingController::class, 'usage'])->name('usage');
    Route::get('/history', [BillingController::class, 'billingHistory'])->name('history');
    
    // Subscription management
    Route::post('/subscribe', [BillingController::class, 'subscribe'])->name('subscribe');
    Route::put('/subscription', [BillingController::class, 'updateSubscription'])->name('subscription.update');
    Route::delete('/subscription', [BillingController::class, 'cancelSubscription'])->name('subscription.cancel');
    Route::post('/subscription/resume', [BillingController::class, 'resumeSubscription'])->name('subscription.resume');
    
    // Payment methods
    Route::get('/payment-methods', [BillingController::class, 'paymentMethods'])->name('payment-methods');
    Route::post('/payment-methods', [BillingController::class, 'addPaymentMethod'])->name('payment-methods.add');
    Route::put('/payment-methods/default', [BillingController::class, 'updateDefaultPaymentMethod'])->name('payment-methods.default');
    Route::delete('/payment-methods', [BillingController::class, 'deletePaymentMethod'])->name('payment-methods.delete');
    
    // Invoice management
    Route::get('/invoices/{invoice}/download', [BillingController::class, 'downloadInvoice'])->name('invoices.download');
});

// Stripe webhook (public endpoint)
Route::post('/stripe/webhook', [WebhookController::class, 'handleWebhook'])
    ->name('stripe.webhook')
    ->withoutMiddleware(['web', 'auth']);

// Note: Sentiment Chart API routes are now in api.php for public access

// North Star Demo Dashboard Route
Route::middleware(['auth'])->group(function () {
    Route::get('/demo', function () {
        return Inertia::render('Demo/NorthStarDashboard');
    })->name('north-star-demo');
    
    // Sentiment Price Timeline Demo Route
    Route::get('/sentiment-timeline-demo', function () {
        return Inertia::render('Demo/SentimentPriceTimelineDemo');
    })->name('sentiment-timeline-demo');
    
    // PDF Generation Demo Route
    Route::get('/pdf-generation-demo', [\App\Http\Controllers\VuePdfDemoController::class, 'showDemo'])->name('pdf-generation-demo');
    
    // Verification Badge Demo Route
    Route::get('/verification-badge-demo', function () {
        return Inertia::render('Demo/VerificationBadgeDemo');
    })->name('verification-badge-demo');
});

// PDF Generation routes
Route::prefix('pdf')->name('pdf.')->group(function () {
    // PDF Generation API endpoints (authenticated)
    Route::middleware(['auth'])->group(function () {
        // Generate dashboard PDF
        Route::post('/dashboard', [PdfController::class, 'generateDashboardPdf'])->name('dashboard');
        
        // Generate sentiment analysis PDF
        Route::post('/sentiment', [PdfController::class, 'generateSentimentPdf'])->name('sentiment');
        
        // Generate crawler report PDF
        Route::post('/crawler', [PdfController::class, 'generateCrawlerPdf'])->name('crawler');
        
        // Get PDF generation statistics
        Route::get('/statistics', [PdfController::class, 'getStatistics'])->name('statistics');
        
        // Cleanup old PDFs
        Route::delete('/cleanup', [PdfController::class, 'cleanup'])->name('cleanup');
        
        // PDF engine information and health check
        Route::get('/engine-info', [PdfController::class, 'getEngineInfo'])->name('engine-info');
    });
    
    // PDF preview route (for browserless rendering - accessible with token or when authenticated)
    Route::get('/preview/{component}', [PdfController::class, 'previewComponent'])
         ->name('preview')
         ->middleware(['web'])
         ->where('component', '[A-Za-z][A-Za-z0-9]*');
    
    // PDF download route (requires authentication)
    Route::get('/download/{filename}', [PdfController::class, 'downloadPdf'])
         ->name('download')
         ->middleware(['auth'])
         ->where('filename', '[A-Za-z][A-Za-z0-9\-_\.]*');
    
         // Test PDF generation with sample data
     Route::get('/test', [PdfController::class, 'test'])->name('test');
     
     // Generate test dashboard PDF without authentication
     Route::get('/test-dashboard', function() {
         try {
             $pdfService = app(\App\Services\PdfGenerationService::class);
             
             $testData = [
                 'title' => 'Test AI Blockchain Analytics Dashboard',
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
                 ]
             ];
             
             $options = [
                 'format' => 'A4',
                 'orientation' => 'portrait',
                 'filename' => 'test-dashboard-' . now()->timestamp . '.pdf'
             ];
             
             $result = $pdfService->generateDashboardReport($testData, $options);
             
                         // Remove binary content from result to prevent UTF-8 encoding issues
            $safeResult = $result;
            if (isset($safeResult['content'])) {
                $safeResult['content'] = base64_encode($safeResult['content']);
                $safeResult['content_encoding'] = 'base64';
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Test PDF generated successfully',
                'result' => $safeResult,
                'test_data' => $testData,
                'options' => $options
            ]);
             
         } catch (Exception $e) {
             return response()->json([
                 'status' => 'error',
                 'message' => 'Test PDF generation failed',
                 'error' => $e->getMessage(),
                 'trace' => $e->getTraceAsString()
             ], 500);
         }
     })->name('test-dashboard');
     
     // Test engine selection
     Route::get('/test-engines', function() {
         try {
             return response()->json([
                 'status' => 'success',
                 'engines' => [
                     'browserless' => [
                         'enabled' => config('services.browserless.enabled', false),
                         'url' => config('services.browserless.url'),
                         'available' => false // Will be checked dynamically
                     ],
                     'dompdf' => [
                         'enabled' => class_exists('\Dompdf\Dompdf'),
                         'version' => class_exists('\Dompdf\Dompdf') ? 'Available' : 'Not installed'
                     ]
                 ],
                 'config' => [
                     'storage_disk' => config('filesystems.default'),
                     'pdf_path' => 'pdfs/',
                     'supported_formats' => ['A4', 'Letter', 'Legal']
                 ]
             ]);
             
         } catch (Exception $e) {
             return response()->json([
                 'status' => 'error',
                 'error' => $e->getMessage()
             ], 500);
         }
     })->name('test-engines');
     
     // Sentiment chart preview for PDF
     Route::get('/sentiment-chart-preview', [PdfController::class, 'previewSentimentChart'])
         ->name('sentiment-chart-preview');
});

// Vue PDF Generation routes
Route::prefix('vue-pdf')->name('vue-pdf.')->group(function () {
    // PDF preview for Vue components (used by Browserless)
    Route::get('/preview/{token}', [\App\Http\Controllers\VuePdfController::class, 'servePreview'])
         ->name('preview')
         ->where('token', '[A-Za-z0-9]{16,64}');
    
    // PDF download
    Route::get('/download/{filename}', [\App\Http\Controllers\VuePdfController::class, 'downloadPdf'])
         ->name('download')
         ->where('filename', '[A-Za-z][A-Za-z0-9\-_\.]*');
});

// Enhanced Vue PDF Generation routes
Route::prefix('enhanced-pdf')->name('enhanced-pdf.')->group(function () {
    // Public routes (for Browserless access)
    Route::get('/preview', [EnhancedPdfController::class, 'preview'])->name('preview');
    Route::get('/download/{filename}', [EnhancedPdfController::class, 'download'])
         ->name('download')
         ->where('filename', '[A-Za-z][A-Za-z0-9\-_\.]*');
    
    // Authenticated routes
    Route::middleware(['auth'])->group(function () {
        // Generate preview token for Vue route
        Route::post('/preview/token', [EnhancedPdfController::class, 'generatePreviewToken'])->name('preview.token');
        
        // Generate PDF from Vue route
        Route::post('/generate/route', [EnhancedPdfController::class, 'generateFromRoute'])->name('generate.route');
        
        // Generate PDF from Vue component
        Route::post('/generate/component', [EnhancedPdfController::class, 'generateFromComponent'])->name('generate.component');
        
        // Generate sentiment timeline PDF
        Route::post('/generate/sentiment-timeline', [EnhancedPdfController::class, 'generateSentimentTimelinePdf'])->name('generate.sentiment-timeline');
        
        // Generate dashboard PDF
        Route::post('/generate/dashboard', [EnhancedPdfController::class, 'generateDashboardPdf'])->name('generate.dashboard');
        
        // Service management
        Route::get('/status', [EnhancedPdfController::class, 'getStatus'])->name('status');
        Route::get('/files', [EnhancedPdfController::class, 'listFiles'])->name('files');
        Route::delete('/cleanup', [EnhancedPdfController::class, 'cleanup'])->name('cleanup');
        
        // Demo page
        Route::get('/demo', [EnhancedPdfController::class, 'demo'])->name('demo');
    });
});

// Unified Vue PDF Preview routes for Browserless (needs to be outside middleware)
Route::prefix('pdf-preview')->name('pdf-preview.')->group(function () {
    // Component preview
    Route::get('/component/{token}', [\App\Http\Controllers\UnifiedVuePdfController::class, 'serveComponentPreview'])
         ->name('component')
         ->where('token', '[A-Za-z0-9]{16,64}');
    
    // Route preview
    Route::get('/route/{token}', [\App\Http\Controllers\UnifiedVuePdfController::class, 'serveRoutePreview'])
         ->name('route')
         ->where('token', '[A-Za-z0-9]{16,64}');
});

// PDF download route
Route::get('/pdf/download/{filename}', [\App\Http\Controllers\UnifiedVuePdfController::class, 'downloadPdf'])
     ->name('unified-vue-pdf.download')
     ->middleware(['auth'])
     ->where('filename', '[A-Za-z][A-Za-z0-9\-_\.]*');

// Legacy PDF Preview route for Browserless (needs to be outside middleware)
Route::get('/pdf-preview/{token}', [\App\Http\Controllers\VuePdfController::class, 'servePreview'])
     ->name('pdf-preview')
     ->where('token', '[A-Za-z0-9]{16,64}');

// Removed duplicate get-verified route - using VerificationController@index instead

// Badge Demo page (public access)
Route::get('/verification/badge-demo', function () {
    $contract = request('contract', '0x1234567890123456789012345678901234567890');
    
    return Inertia::render('Verification/BadgeDemo', [
        'contract' => $contract
    ]);
})->name('verification.demo');

// Verification Badge routes (browser-accessible)
Route::prefix('verify')->name('verification.')->group(function () {
    // Verify signed URL (browser endpoint)
    Route::get('/{token}', [VerificationController::class, 'verify'])->name('verify');
});

// Contract verification route (public access with signed URL)
Route::get('/verify-contract/{token}', [VerificationController::class, 'verify'])->name('contract.verify');

// Enhanced Verification Badge routes
Route::prefix('enhanced-verification')->name('enhanced-verification.')->group(function () {
    // Public routes
    Route::get('/verify/{token}', [EnhancedVerificationController::class, 'verifySignedUrl'])->name('verify');
    Route::get('/status/{contractAddress}', [EnhancedVerificationController::class, 'getVerificationStatus'])->name('status');
    Route::get('/badge/{contractAddress}', [EnhancedVerificationController::class, 'getBadgeHtml'])->name('badge');
    
    // Authenticated routes
    Route::middleware(['auth'])->group(function () {
        // Generate verification URLs
        Route::post('/generate', [EnhancedVerificationController::class, 'generateVerificationUrl'])->name('generate');
        
        // User verification management
        Route::get('/my-verifications', [EnhancedVerificationController::class, 'getUserVerifications'])->name('my-verifications');
        Route::post('/revoke', [EnhancedVerificationController::class, 'revokeVerification'])->name('revoke');
        
        // Batch operations
        Route::post('/batch/generate', [EnhancedVerificationController::class, 'batchGenerateUrls'])->name('batch.generate');
        
        // Statistics
        Route::get('/stats', [EnhancedVerificationController::class, 'getVerificationStats'])->name('stats');
        
        // Management pages
        Route::get('/manage', [EnhancedVerificationController::class, 'manage'])->name('manage');
        Route::get('/demo', [EnhancedVerificationController::class, 'demo'])->name('demo');
    });
});

// Email Management routes
Route::prefix('email')->name('email.')->group(function () {
    // Public unsubscribe routes (no auth required)
    Route::get('/unsubscribe', [\App\Http\Controllers\EmailPreferencesController::class, 'unsubscribe'])->name('unsubscribe');
    Route::post('/unsubscribe', [\App\Http\Controllers\EmailPreferencesController::class, 'unsubscribe']);
    Route::post('/resubscribe', [\App\Http\Controllers\EmailPreferencesController::class, 'resubscribe'])->name('resubscribe');
    
    // Tracking pixel endpoint
    Route::get('/tracking/pixel', [\App\Http\Controllers\EmailPreferencesController::class, 'trackingPixel'])->name('tracking.pixel');
    
    // Authenticated email preference routes
    Route::middleware(['auth'])->group(function () {
        Route::get('/preferences', [\App\Http\Controllers\EmailPreferencesController::class, 'show'])->name('preferences');
        Route::patch('/preferences', [\App\Http\Controllers\EmailPreferencesController::class, 'update'])->name('preferences.update');
        
        // PDF Generation routes
        Route::post('/preferences/pdf', [\App\Http\Controllers\EmailPreferencesController::class, 'generatePdf'])->name('preferences.pdf');
        Route::post('/preferences/pdf/{engine}', [\App\Http\Controllers\EmailPreferencesController::class, 'generatePdfWithEngine'])->name('preferences.pdf.engine');
        Route::get('/preferences/pdf/download/{filename}', [\App\Http\Controllers\EmailPreferencesController::class, 'downloadPdf'])->name('preferences.pdf.download');
    });
});

// Cache Management Routes (Admin only)
Route::middleware(['auth', 'verified'])->prefix('admin/cache')->name('admin.cache.')->group(function () {
    Route::get('/', [\App\Http\Controllers\CacheManagementController::class, 'dashboard'])->name('dashboard');
    Route::get('/statistics', [\App\Http\Controllers\CacheManagementController::class, 'statistics'])->name('statistics');
    Route::get('/entries', [\App\Http\Controllers\CacheManagementController::class, 'entries'])->name('entries');
    Route::get('/entries/{cacheId}', [\App\Http\Controllers\CacheManagementController::class, 'show'])->name('entries.show');
    Route::post('/entries/{cacheId}/invalidate', [\App\Http\Controllers\CacheManagementController::class, 'invalidateEntry'])->name('entries.invalidate');
    Route::post('/invalidate', [\App\Http\Controllers\CacheManagementController::class, 'invalidate'])->name('invalidate');
    Route::post('/cleanup', [\App\Http\Controllers\CacheManagementController::class, 'cleanup'])->name('cleanup');
    Route::post('/warm', [\App\Http\Controllers\CacheManagementController::class, 'warmCache'])->name('warm');
    Route::get('/metrics', [\App\Http\Controllers\CacheManagementController::class, 'metrics'])->name('metrics');
    Route::get('/export', [\App\Http\Controllers\CacheManagementController::class, 'export'])->name('export');
    Route::get('/health', [\App\Http\Controllers\CacheManagementController::class, 'healthCheck'])->name('health');
});

// PDF Generation Routes
Route::prefix('pdf')->name('pdf.')->group(function () {
    // Analytics Reports
    Route::get('/analytics/{contractId}', [\App\Http\Controllers\PdfController::class, 'generateAnalyticsReport'])
        ->name('analytics.report');
    
    // Sentiment Dashboard
    Route::get('/sentiment-dashboard', [\App\Http\Controllers\PdfController::class, 'generateSentimentDashboard'])
        ->name('sentiment.dashboard');
    
    // Vue Component PDF Generation
    Route::post('/generate-vue', [\App\Http\Controllers\PdfController::class, 'generateFromVueComponent'])
        ->name('generate.vue');
    
    // PDF Statistics
    Route::get('/statistics', [\App\Http\Controllers\PdfController::class, 'getStatistics'])
        ->name('statistics');
    
    // PDF Engine Information
    Route::get('/engine-info', [\App\Http\Controllers\PdfController::class, 'getEngineInfo'])
        ->name('engine.info');
    
    // Temporary Vue route for Browserless (public access needed)
    Route::get('/temp-vue/{token}', [\App\Http\Controllers\PdfController::class, 'tempVueRoute'])
        ->name('temp-vue');
});

// Mailgun webhook routes (public, no auth - verified via signature)
Route::prefix('webhooks/mailgun')->name('webhooks.mailgun.')->group(function () {
    Route::post('/', [\App\Http\Controllers\Api\MailgunWebhookController::class, 'handleWebhook'])->name('general');
    Route::post('/delivered', [\App\Http\Controllers\Api\MailgunWebhookController::class, 'handleDelivered'])->name('delivered');
    Route::post('/opened', [\App\Http\Controllers\Api\MailgunWebhookController::class, 'handleOpened'])->name('opened');
    Route::post('/clicked', [\App\Http\Controllers\Api\MailgunWebhookController::class, 'handleClicked'])->name('clicked');
    Route::post('/bounced', [\App\Http\Controllers\Api\MailgunWebhookController::class, 'handleBounced'])->name('bounced');
    Route::post('/complained', [\App\Http\Controllers\Api\MailgunWebhookController::class, 'handleComplained'])->name('complained');
    Route::post('/unsubscribed', [\App\Http\Controllers\Api\MailgunWebhookController::class, 'handleUnsubscribed'])->name('unsubscribed');
});

require __DIR__.'/auth.php';