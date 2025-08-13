<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| AI Blockchain Analytics v0.9.0 - Automated Demo Scheduling
|--------------------------------------------------------------------------
|
| Comprehensive daily demo script scheduling to showcase platform 
| capabilities, monitor system health, and generate demo content.
|
*/

// === FULL DAILY DEMO (Production Marketing) ===
Schedule::command('demo:daily', ['--detailed', '--output-file=storage/logs/daily-demo-full.json'])
    ->dailyAt('03:00')
    ->withoutOverlapping(60) // 60 minute timeout
    ->runInBackground()
    ->onOneServer()
    ->emailOutputOnFailure([env('DEMO_ALERT_EMAIL', 'admin@ai-blockchain-analytics.com')])
    ->before(function () {
        Log::info('Daily demo script starting', ['type' => 'full', 'time' => now()]);
    })
    ->after(function () {
        Log::info('Daily demo script completed', ['type' => 'full', 'time' => now()]);
    })
    ->onSuccess(function () {
        // Could trigger notifications to marketing team
        Log::info('Daily demo successful - ready for marketing use');
    })
    ->onFailure(function () {
        Log::error('Daily demo failed - investigate immediately');
    })
    ->appendOutputTo(storage_path('logs/demo-daily-full.log'));

// === WEEKLY COMPREHENSIVE DEMO (Deep Analysis) ===
Schedule::command('demo:daily', ['--detailed', '--output-file=storage/logs/weekly-demo-comprehensive.json'])
    ->weeklyOn(1, '04:00') // Monday at 4:00 AM
    ->withoutOverlapping(120) // 2 hour timeout for comprehensive demo
    ->runInBackground()
    ->onOneServer()
    ->emailOutputOnFailure([env('DEMO_ALERT_EMAIL', 'admin@ai-blockchain-analytics.com')])
    ->before(function () {
        Log::info('Weekly comprehensive demo starting', ['type' => 'comprehensive']);
    })
    ->appendOutputTo(storage_path('logs/demo-weekly-comprehensive.log'));

// === QUICK HEALTH CHECK (Every 6 hours) ===
Schedule::command('demo:daily', [
        '--skip-crawling', 
        '--skip-reports', 
        '--skip-onboarding',
        '--output-file=storage/logs/health-check.json'
    ])
    ->everySixHours()
    ->withoutOverlapping(30) // 30 minute timeout
    ->runInBackground()
    ->onOneServer()
    ->before(function () {
        Log::info('Health check demo starting');
    })
    ->appendOutputTo(storage_path('logs/demo-health-check.log'));

// === BUSINESS HOURS DEMO (For Live Presentations) ===
Schedule::command('demo:daily', [
        '--detailed', 
        '--skip-cleanup',
        '--output-file=storage/logs/presentation-demo.json'
    ])
    ->dailyAt('09:00') // 9 AM for business presentations
    ->weekdays()
    ->withoutOverlapping(45)
    ->runInBackground()
    ->onOneServer()
    ->environments(['production']) // Only in production
    ->appendOutputTo(storage_path('logs/demo-presentation.log'));

// === PERFORMANCE MONITORING DEMO (Twice Daily) ===
Schedule::command('demo:daily', [
        '--skip-analysis',
        '--skip-crawling', 
        '--skip-onboarding',
        '--output-file=storage/logs/performance-monitoring.json'
    ])
    ->twiceDaily(6, 18) // 6 AM and 6 PM
    ->withoutOverlapping(20)
    ->runInBackground()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/demo-performance.log'));

// === DEMO LOG CLEANUP (Weekly) ===
Schedule::call(function () {
    // Clean up old demo logs (keep last 30 days)
    $logPath = storage_path('logs');
    $files = glob($logPath . '/demo-*.log');
    
    foreach ($files as $file) {
        if (filemtime($file) < strtotime('-30 days')) {
            unlink($file);
            Log::info('Cleaned up old demo log', ['file' => basename($file)]);
        }
    }
    
    // Clean up old JSON results (keep last 14 days)
    $jsonFiles = glob($logPath . '/daily-demo-*.json');
    foreach ($jsonFiles as $file) {
        if (filemtime($file) < strtotime('-14 days')) {
            unlink($file);
        }
    }
})
->weeklyOn(0, '02:00') // Sunday at 2:00 AM
->name('demo-log-cleanup')
->onOneServer();
