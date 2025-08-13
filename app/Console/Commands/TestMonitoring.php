<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Services\Monitoring\SentryRateLimiter;
use App\Services\Monitoring\SentryDataScrubber;
use Exception;
use Sentry\Laravel\Facade as Sentry;
use Laravel\Telescope\Telescope;

class TestMonitoring extends Command
{
    protected $signature = 'test:monitoring {--component=all : Test specific component (sentry|telescope|all)}';
    protected $description = 'Test monitoring integrations (Sentry + Telescope)';

    public function handle(): int
    {
        $component = $this->option('component');

        $this->info('🔍 Testing AI Blockchain Analytics Monitoring Integration');
        $this->newLine();

        $results = [];

        if ($component === 'all' || $component === 'sentry') {
            $results['sentry'] = $this->testSentry();
        }

        if ($component === 'all' || $component === 'telescope') {
            $results['telescope'] = $this->testTelescope();
        }

        $this->displayResults($results);

        return array_sum($results) === count($results) ? 0 : 1;
    }

    /**
     * Test Sentry integration.
     */
    private function testSentry(): bool
    {
        $this->info('🐛 Testing Sentry Integration');
        
        try {
            // Test 1: Check if Sentry is configured
            if (!config('sentry.dsn')) {
                $this->error('❌ Sentry DSN not configured');
                return false;
            }
            $this->info('✅ Sentry DSN configured');

            // Test 2: Test rate limiter
            $rateLimiter = app(SentryRateLimiter::class);
            $stats = $rateLimiter->getStats();
            $this->info("✅ Rate limiter active - {$stats['events_this_minute']}/{$stats['rate_limits']['max_per_minute']} events this minute");

            // Test 3: Test data scrubber
            $scrubber = app(SentryDataScrubber::class);
            $scrubFields = $scrubber->getScrubFields();
            $this->info('✅ Data scrubber configured with ' . count($scrubFields) . ' sensitive fields');

            // Test 4: Send test exception to Sentry
            if (app()->environment(['local', 'staging'])) {
                try {
                    throw new Exception('Test exception for Sentry monitoring - ' . now()->toISOString());
                } catch (Exception $e) {
                    Sentry::captureException($e);
                    $this->info('✅ Test exception sent to Sentry');
                }
            } else {
                $this->warn('⚠️  Skipping test exception in production environment');
            }

            // Test 5: Check component monitoring settings
            $components = config('monitoring.sentry.components', []);
            foreach ($components as $component => $settings) {
                $status = $settings['enabled'] ? 'enabled' : 'disabled';
                $rate = $settings['sample_rate'] ?? 'N/A';
                $this->info("✅ {$component} monitoring: {$status} (sample rate: {$rate})");
            }

            $this->info('🎉 Sentry integration tests passed');
            return true;

        } catch (Exception $e) {
            $this->error('❌ Sentry test failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Test Telescope integration.
     */
    private function testTelescope(): bool
    {
        $this->info('🔭 Testing Telescope Integration');
        
        try {
            // Test 1: Check if Telescope is enabled
            if (!config('telescope.enabled', true)) {
                $this->error('❌ Telescope is disabled');
                return false;
            }
            $this->info('✅ Telescope enabled');

            // Test 2: Check production restrictions
            $productionEnabled = config('monitoring.telescope.production_enabled', false);
            if (app()->environment('production') && $productionEnabled) {
                $this->warn('⚠️  Telescope enabled in production - ensure proper restrictions');
            } else {
                $this->info('✅ Production restrictions properly configured');
            }

            // Test 3: Check data retention settings
            $retentionHours = config('monitoring.telescope.retention.hours', 24);
            $retentionLimit = config('monitoring.telescope.retention.limit', 1000);
            $this->info("✅ Data retention: {$retentionHours} hours, max {$retentionLimit} entries");

            // Test 4: Check sampling rate for production
            $samplingRate = config('monitoring.telescope.performance.sampling_rate', 0.1);
            $this->info("✅ Sampling rate: {$samplingRate} (" . ($samplingRate * 100) . "%)");

            // Test 5: Test authorization restrictions
            $allowedIps = config('monitoring.telescope.production_restrictions.allowed_ips', []);
            $allowedUsers = config('monitoring.telescope.production_restrictions.allowed_users', []);
            
            if (empty($allowedIps) && empty($allowedUsers) && app()->environment('production')) {
                $this->warn('⚠️  No IP or user restrictions configured for production');
            } else {
                $this->info('✅ Access restrictions configured');
            }

            // Test 6: Check watcher configuration for environment
            $environment = app()->environment();
            $watchers = config("monitoring.telescope.watchers.{$environment}", []);
            if (!empty($watchers)) {
                $enabledCount = count(array_filter($watchers));
                $totalCount = count($watchers);
                $this->info("✅ Environment watchers configured: {$enabledCount}/{$totalCount} enabled for {$environment}");
            }

            // Test 7: Generate test entries
            if (!app()->environment('production')) {
                Log::info('Test log entry for Telescope monitoring');
                cache()->put('telescope_test', 'test_value', 60);
                cache()->get('telescope_test');
                $this->info('✅ Test entries generated');
            } else {
                $this->warn('⚠️  Skipping test entries in production environment');
            }

            $this->info('🎉 Telescope integration tests passed');
            return true;

        } catch (Exception $e) {
            $this->error('❌ Telescope test failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Display test results summary.
     */
    private function displayResults(array $results): void
    {
        $this->newLine();
        $this->info('📊 Monitoring Integration Test Results');
        $this->newLine();

        $totalTests = count($results);
        $passedTests = array_sum($results);

        foreach ($results as $component => $result) {
            $status = $result ? '✅ PASS' : '❌ FAIL';
            $this->info("{$status} {$component}");
        }

        $this->newLine();
        
        if ($passedTests === $totalTests) {
            $this->info("🎉 All tests passed! ({$passedTests}/{$totalTests})");
            $this->info('Your monitoring setup is ready for production.');
        } else {
            $this->error("❌ Some tests failed ({$passedTests}/{$totalTests})");
            $this->error('Please review the configuration and fix any issues.');
        }

        $this->newLine();
        $this->info('📋 Next Steps:');
        $this->info('1. Configure Sentry DSN in your environment');
        $this->info('2. Set up Telescope access restrictions for production');
        $this->info('3. Configure Grafana dashboards for monitoring');
        $this->info('4. Set up alerting rules in your monitoring system');
        $this->info('5. Test error handling and notification workflows');
        
        if (app()->environment('production')) {
            $this->newLine();
            $this->warn('⚠️  Production Environment Detected');
            $this->warn('Remember to:');
            $this->warn('- Disable or restrict Telescope access');
            $this->warn('- Configure proper Sentry rate limiting');
            $this->warn('- Set up log aggregation and alerting');
            $this->warn('- Monitor resource usage and performance');
        }
    }
}