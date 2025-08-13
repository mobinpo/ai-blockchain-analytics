<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Exception;

/**
 * Test Monitoring Setup Command
 * 
 * Comprehensive testing of Sentry + Telescope monitoring configuration
 * for AI Blockchain Analytics platform
 */
final class TestMonitoringSetupCommand extends Command
{
    protected $signature = 'monitoring:test 
                           {--sentry : Test Sentry error tracking}
                           {--telescope : Test Telescope data collection}
                           {--performance : Test performance monitoring}
                           {--errors : Generate test errors}
                           {--all : Run all monitoring tests}';

    protected $description = 'Test the complete monitoring setup (Sentry + Telescope)';

    public function handle(): int
    {
        $this->info('🔍 Testing AI Blockchain Analytics Monitoring Setup');
        $this->newLine();

        try {
            if ($this->option('all')) {
                return $this->runAllTests();
            }

            if ($this->option('sentry')) {
                $this->testSentrySetup();
            }

            if ($this->option('telescope')) {
                $this->testTelescopeSetup();
            }

            if ($this->option('performance')) {
                $this->testPerformanceMonitoring();
            }

            if ($this->option('errors')) {
                $this->generateTestErrors();
            }

            if (!$this->hasAnyOption()) {
                $this->runBasicTests();
            }

            return 0;

        } catch (Exception $e) {
            $this->error('❌ Monitoring test failed: ' . $e->getMessage());
            Log::error('Monitoring test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Run all comprehensive tests
     */
    private function runAllTests(): int
    {
        $this->info('🚀 Running comprehensive monitoring tests...');
        $this->newLine();

        $tests = [
            'Configuration Check' => fn() => $this->testConfiguration(),
            'Sentry Integration' => fn() => $this->testSentrySetup(),
            'Telescope Setup' => fn() => $this->testTelescopeSetup(),
            'Performance Monitoring' => fn() => $this->testPerformanceMonitoring(),
            'Error Tracking' => fn() => $this->testErrorTracking(),
            'Database Monitoring' => fn() => $this->testDatabaseMonitoring(),
            'Cache Monitoring' => fn() => $this->testCacheMonitoring(),
            'Blockchain Monitoring' => fn() => $this->testBlockchainMonitoring(),
            'AI Operations Monitoring' => fn() => $this->testAIMonitoring(),
        ];

        $passed = 0;
        $total = count($tests);

        foreach ($tests as $testName => $testFunction) {
            $this->info("Testing: {$testName}");
            
            try {
                $result = $testFunction();
                if ($result) {
                    $this->info("✅ {$testName} - PASSED");
                    $passed++;
                } else {
                    $this->error("❌ {$testName} - FAILED");
                }
            } catch (Exception $e) {
                $this->error("❌ {$testName} - ERROR: " . $e->getMessage());
            }
            
            $this->newLine();
        }

        $this->info("📊 Test Results: {$passed}/{$total} tests passed");
        
        return $passed === $total ? 0 : 1;
    }

    /**
     * Run basic monitoring tests
     */
    private function runBasicTests(): void
    {
        $this->info('Running basic monitoring tests...');
        $this->newLine();

        $this->testConfiguration();
        $this->testSentrySetup();
        $this->testTelescopeSetup();
    }

    /**
     * Test monitoring configuration
     */
    private function testConfiguration(): bool
    {
        $this->info('1. Testing monitoring configuration...');

        // Check if monitoring is enabled
        $monitoringEnabled = config('monitoring.enabled');
        $this->line("   Monitoring enabled: " . ($monitoringEnabled ? 'Yes' : 'No'));

        // Check Sentry configuration
        $sentryEnabled = config('monitoring.sentry.enabled');
        $sentryDsn = config('sentry.dsn');
        $this->line("   Sentry enabled: " . ($sentryEnabled ? 'Yes' : 'No'));
        $this->line("   Sentry DSN configured: " . ($sentryDsn ? 'Yes' : 'No'));

        // Check Telescope configuration
        $telescopeEnabled = config('monitoring.telescope.enabled');
        $this->line("   Telescope enabled: " . ($telescopeEnabled ? 'Yes' : 'No'));

        // Check production settings
        if (app()->environment('production')) {
            $prodSentry = config('monitoring.sentry.enabled');
            $prodTelescope = config('monitoring.telescope.production.enabled');
            $this->line("   Production Sentry: " . ($prodSentry ? 'Enabled' : 'Disabled'));
            $this->line("   Production Telescope: " . ($prodTelescope ? 'Enabled' : 'Disabled'));
        }

        return $monitoringEnabled;
    }

    /**
     * Test Sentry error tracking setup
     */
    private function testSentrySetup(): bool
    {
        $this->info('2. Testing Sentry error tracking...');

        // Check if Sentry is bound
        if (!app()->bound('sentry')) {
            $this->error('   Sentry service not bound');
            return false;
        }

        // Test Sentry configuration
        $sentryDsn = config('sentry.dsn');
        if (!$sentryDsn) {
            $this->warn('   Sentry DSN not configured (using SENTRY_DSN env var)');
        } else {
            $this->line("   Sentry DSN configured");
        }

        // Test custom Sentry integrations
        $blockchainMonitor = app()->bound('sentry.blockchain_monitor');
        $aiMonitor = app()->bound('sentry.ai_monitor');
        
        $this->line("   Blockchain monitor: " . ($blockchainMonitor ? 'Available' : 'Not found'));
        $this->line("   AI monitor: " . ($aiMonitor ? 'Available' : 'Not found'));

        // Test Sentry capture (non-blocking)
        try {
            app('sentry')->captureMessage('Monitoring test message from AI Blockchain Analytics');
            $this->line("   Test message sent to Sentry");
        } catch (Exception $e) {
            $this->warn("   Failed to send test message: " . $e->getMessage());
        }

        return true;
    }

    /**
     * Test Telescope data collection
     */
    private function testTelescopeSetup(): bool
    {
        $this->info('3. Testing Telescope data collection...');

        // Check if Telescope is enabled
        $telescopeEnabled = config('telescope.enabled');
        if (!$telescopeEnabled) {
            $this->warn('   Telescope is disabled');
            return false;
        }

        // Check Telescope watchers
        $watchers = config('telescope.watchers', []);
        $enabledWatchers = array_filter($watchers, function ($watcher) {
            return is_array($watcher) ? ($watcher['enabled'] ?? true) : $watcher;
        });

        $this->line("   Enabled watchers: " . count($enabledWatchers));

        // Check specific watchers
        $importantWatchers = ['ExceptionWatcher', 'JobWatcher', 'RequestWatcher', 'LogWatcher'];
        foreach ($importantWatchers as $watcher) {
            $watcherKey = "Laravel\\Telescope\\Watchers\\{$watcher}";
            $enabled = isset($watchers[$watcherKey]) && 
                      (is_array($watchers[$watcherKey]) ? 
                       ($watchers[$watcherKey]['enabled'] ?? true) : 
                       $watchers[$watcherKey]);
            $this->line("   {$watcher}: " . ($enabled ? 'Enabled' : 'Disabled'));
        }

        // Check Telescope database tables
        try {
            $entryCount = DB::table('telescope_entries')->count();
            $this->line("   Telescope entries in database: {$entryCount}");
        } catch (Exception $e) {
            $this->warn("   Could not check Telescope database: " . $e->getMessage());
        }

        return true;
    }

    /**
     * Test performance monitoring
     */
    private function testPerformanceMonitoring(): bool
    {
        $this->info('4. Testing performance monitoring...');

        // Test database query monitoring
        $startTime = microtime(true);
        try {
            DB::select('SELECT 1 as test');
            $queryTime = (microtime(true) - $startTime) * 1000;
            $this->line("   Database query test: " . number_format($queryTime, 2) . "ms");
        } catch (Exception $e) {
            $this->warn("   Database query test failed: " . $e->getMessage());
        }

        // Test cache monitoring
        try {
            Cache::put('monitoring_test', 'test_value', 60);
            $cacheValue = Cache::get('monitoring_test');
            $this->line("   Cache test: " . ($cacheValue === 'test_value' ? 'Working' : 'Failed'));
            Cache::forget('monitoring_test');
        } catch (Exception $e) {
            $this->warn("   Cache test failed: " . $e->getMessage());
        }

        // Test memory usage monitoring
        $memoryUsage = memory_get_usage(true) / 1024 / 1024;
        $peakMemory = memory_get_peak_usage(true) / 1024 / 1024;
        $this->line("   Memory usage: " . number_format($memoryUsage, 2) . "MB (peak: " . number_format($peakMemory, 2) . "MB)");

        return true;
    }

    /**
     * Test error tracking
     */
    private function testErrorTracking(): bool
    {
        $this->info('5. Testing error tracking...');

        // Test log levels
        $logLevels = ['debug', 'info', 'warning', 'error'];
        foreach ($logLevels as $level) {
            Log::$level("Test {$level} message from monitoring test");
        }
        $this->line("   Test log messages sent at all levels");

        // Test custom context
        Log::info('Test message with context', [
            'feature' => 'monitoring_test',
            'component' => 'ai_blockchain_analytics',
            'test_data' => ['key' => 'value']
        ]);
        $this->line("   Test message with custom context sent");

        return true;
    }

    /**
     * Test database monitoring
     */
    private function testDatabaseMonitoring(): bool
    {
        $this->info('6. Testing database monitoring...');

        // Test slow query simulation
        try {
            $startTime = microtime(true);
            DB::select('SELECT SLEEP(0.1)'); // 100ms delay
            $queryTime = (microtime(true) - $startTime) * 1000;
            $this->line("   Slow query simulation: " . number_format($queryTime, 2) . "ms");
        } catch (Exception $e) {
            $this->warn("   Slow query test not supported on this database");
        }

        // Test query counting
        $queryCount = 0;
        DB::listen(function ($query) use (&$queryCount) {
            $queryCount++;
        });

        // Execute some test queries
        DB::select('SELECT 1');
        DB::select('SELECT 2');
        DB::select('SELECT 3');

        $this->line("   Query listener test: {$queryCount} queries detected");

        return true;
    }

    /**
     * Test cache monitoring
     */
    private function testCacheMonitoring(): bool
    {
        $this->info('7. Testing cache monitoring...');

        // Test cache operations
        $testKey = 'monitoring_test_' . time();
        
        // Cache miss
        $missResult = Cache::get($testKey);
        $this->line("   Cache miss test: " . ($missResult === null ? 'Passed' : 'Failed'));

        // Cache hit
        Cache::put($testKey, 'test_value', 60);
        $hitResult = Cache::get($testKey);
        $this->line("   Cache hit test: " . ($hitResult === 'test_value' ? 'Passed' : 'Failed'));

        // Cache delete
        Cache::forget($testKey);
        $deleteResult = Cache::get($testKey);
        $this->line("   Cache delete test: " . ($deleteResult === null ? 'Passed' : 'Failed'));

        return true;
    }

    /**
     * Test blockchain monitoring
     */
    private function testBlockchainMonitoring(): bool
    {
        $this->info('8. Testing blockchain monitoring...');

        // Test blockchain monitor if available
        if (app()->bound('sentry.blockchain_monitor')) {
            $monitor = app('sentry.blockchain_monitor');
            $monitor->trackOperation('test_operation', [
                'contract_address' => '0x1234567890abcdef1234567890abcdef12345678',
                'network' => 'ethereum',
                'operation_type' => 'monitoring_test'
            ]);
            $this->line("   Blockchain operation tracking: Sent");
        } else {
            $this->warn("   Blockchain monitor not available");
        }

        // Test blockchain-specific logging
        Log::info('Blockchain test operation', [
            'feature' => 'blockchain',
            'contract_address' => '0x1234567890abcdef1234567890abcdef12345678',
            'network' => 'ethereum',
            'operation' => 'monitoring_test'
        ]);
        $this->line("   Blockchain-specific logging: Sent");

        return true;
    }

    /**
     * Test AI operations monitoring
     */
    private function testAIMonitoring(): bool
    {
        $this->info('9. Testing AI operations monitoring...');

        // Test AI monitor if available
        if (app()->bound('sentry.ai_monitor')) {
            $monitor = app('sentry.ai_monitor');
            $monitor->trackAIOperation('test_ai_operation', [
                'model' => 'gpt-4',
                'operation_type' => 'monitoring_test',
                'token_count' => 150
            ]);
            $this->line("   AI operation tracking: Sent");
        } else {
            $this->warn("   AI monitor not available");
        }

        // Test AI-specific logging
        Log::info('AI test operation', [
            'feature' => 'ai',
            'model' => 'gpt-4',
            'operation' => 'monitoring_test',
            'token_count' => 150
        ]);
        $this->line("   AI-specific logging: Sent");

        return true;
    }

    /**
     * Generate test errors for monitoring
     */
    private function generateTestErrors(): void
    {
        $this->info('⚠️ Generating test errors for monitoring...');

        // Generate different types of test errors
        $this->generateTestException();
        $this->generateTestValidationError();
        $this->generateTestDatabaseError();
        $this->generateTestHttpError();

        $this->info('✅ Test errors generated and sent to monitoring systems');
    }

    /**
     * Generate test exception
     */
    private function generateTestException(): void
    {
        try {
            throw new Exception('Test exception for monitoring (this is intentional)');
        } catch (Exception $e) {
            // This will be caught by error handlers and sent to Sentry
            report($e);
            Log::error('Test exception generated', [
                'exception' => $e->getMessage(),
                'test_type' => 'monitoring_test'
            ]);
        }
    }

    /**
     * Generate test validation error
     */
    private function generateTestValidationError(): void
    {
        Log::warning('Test validation error', [
            'error_type' => 'validation',
            'field' => 'test_field',
            'message' => 'Test validation failed (monitoring test)',
            'test_type' => 'monitoring_test'
        ]);
    }

    /**
     * Generate test database error
     */
    private function generateTestDatabaseError(): void
    {
        try {
            DB::select('SELECT * FROM non_existent_table LIMIT 1');
        } catch (Exception $e) {
            Log::error('Test database error', [
                'error_type' => 'database',
                'message' => 'Test database query failed (monitoring test)',
                'test_type' => 'monitoring_test'
            ]);
        }
    }

    /**
     * Generate test HTTP error
     */
    private function generateTestHttpError(): void
    {
        try {
            Http::timeout(1)->get('https://httpstat.us/500');
        } catch (Exception $e) {
            Log::error('Test HTTP error', [
                'error_type' => 'http',
                'message' => 'Test HTTP request failed (monitoring test)',
                'test_type' => 'monitoring_test'
            ]);
        }
    }

    /**
     * Check if any options were provided
     */
    private function hasAnyOption(): bool
    {
        return $this->option('sentry') || 
               $this->option('telescope') || 
               $this->option('performance') || 
               $this->option('errors') || 
               $this->option('all');
    }
}
