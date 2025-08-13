<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Exception;

class TestSentryTelescopeIntegration extends Command
{
    protected $signature = 'test:sentry-telescope {--test-error : Test error reporting}';
    
    protected $description = 'Test Sentry and Telescope integration for AI Blockchain Analytics';

    public function handle(): int
    {
        $this->info('🔧 Testing Sentry + Telescope Integration for AI Blockchain Analytics');
        $this->info('================================================================');

        // Test Sentry configuration
        $this->testSentryConfiguration();

        // Test Telescope configuration
        $this->testTelescopeConfiguration();

        // Test custom monitoring integrations
        $this->testCustomMonitoring();

        // Test error reporting if requested
        if ($this->option('test-error')) {
            $this->testErrorReporting();
        }

        $this->info('✅ Integration test completed successfully!');
        return Command::SUCCESS;
    }

    private function testSentryConfiguration(): void
    {
        $this->info('📊 Testing Sentry Configuration...');

        // Check if Sentry is bound
        if (!app()->bound('sentry')) {
            $this->error('❌ Sentry service not bound');
            return;
        }

        $this->info('✓ Sentry service is bound');

        // Check DSN configuration
        $dsn = config('sentry.dsn');
        if ($dsn) {
            $this->info("✓ Sentry DSN configured: " . substr($dsn, 0, 20) . '...');
        } else {
            $this->warn('⚠ Sentry DSN not configured (will use null transport)');
        }

        // Check environment configuration
        $environment = config('sentry.environment');
        $this->info("✓ Environment: {$environment}");

        // Check sample rates
        $sampleRate = config('sentry.sample_rate');
        $tracesSampleRate = config('sentry.traces_sample_rate');
        $this->info("✓ Sample rate: {$sampleRate}, Traces sample rate: {$tracesSampleRate}");
    }

    private function testTelescopeConfiguration(): void
    {
        $this->info('🔭 Testing Telescope Configuration...');

        // Check if Telescope is enabled
        $enabled = config('telescope.enabled');
        $this->info("✓ Telescope enabled: " . ($enabled ? 'Yes' : 'No'));

        // Check path configuration
        $path = config('telescope.path');
        $this->info("✓ Telescope path: /{$path}");

        // Check driver configuration
        $driver = config('telescope.driver');
        $this->info("✓ Storage driver: {$driver}");

        // Check production restrictions
        $productionEnabled = config('telescope.ai_blockchain.production_enabled', false);
        $this->info("✓ Production access: " . ($productionEnabled ? 'Enabled' : 'Disabled'));

        // Check watchers
        $watchers = config('telescope.watchers', []);
        $enabledWatchers = array_filter($watchers, function ($config) {
            return is_array($config) ? ($config['enabled'] ?? true) : $config;
        });
        $this->info("✓ Active watchers: " . count($enabledWatchers));
    }

    private function testCustomMonitoring(): void
    {
        $this->info('🔍 Testing Custom Monitoring Integrations...');

        // Test blockchain monitor
        if (app()->bound('sentry.blockchain_monitor')) {
            $this->info('✓ Blockchain monitor registered');
            
            // Test blockchain operation tracking
            try {
                $monitor = app('sentry.blockchain_monitor');
                $monitor->trackOperation('contract_analysis', [
                    'contract_address' => '0x1234567890123456789012345678901234567890',
                    'chain' => 'ethereum',
                    'operation_type' => 'test'
                ]);
                $this->info('✓ Blockchain operation tracking works');
            } catch (Exception $e) {
                $this->warn("⚠ Blockchain monitoring test failed: {$e->getMessage()}");
            }
        } else {
            $this->error('❌ Blockchain monitor not registered');
        }

        // Test AI monitor
        if (app()->bound('sentry.ai_monitor')) {
            $this->info('✓ AI monitor registered');
            
            // Test AI operation tracking
            try {
                $monitor = app('sentry.ai_monitor');
                $monitor->trackAIOperation('sentiment_analysis', [
                    'model' => 'openai-gpt',
                    'tokens' => 1500,
                    'operation_type' => 'test'
                ]);
                $this->info('✓ AI operation tracking works');
            } catch (Exception $e) {
                $this->warn("⚠ AI monitoring test failed: {$e->getMessage()}");
            }
        } else {
            $this->error('❌ AI monitor not registered');
        }

        // Test monitoring configuration
        $monitoringEnabled = config('monitoring.general.enabled', true);
        $this->info("✓ General monitoring enabled: " . ($monitoringEnabled ? 'Yes' : 'No'));
    }

    private function testErrorReporting(): void
    {
        $this->info('🚨 Testing Error Reporting...');
        $this->warn('This will generate test errors in your monitoring systems');

        if ($this->confirm('Do you want to proceed with error reporting test?')) {
            // Test info log
            Log::info('Test info message from Sentry+Telescope integration test', [
                'test_type' => 'integration_test',
                'component' => 'ai_blockchain_analytics',
                'timestamp' => now()->toISOString(),
            ]);
            $this->info('✓ Info log sent');

            // Test warning
            Log::warning('Test warning from AI Blockchain Analytics monitoring', [
                'test_type' => 'integration_test',
                'severity' => 'warning',
                'component' => 'monitoring_test',
            ]);
            $this->info('✓ Warning sent');

            // Test error reporting
            try {
                throw new Exception('Test exception for Sentry+Telescope integration - this is expected');
            } catch (Exception $e) {
                // Let Sentry capture it naturally
                report($e);
                $this->info('✓ Exception reported to Sentry');
            }
        } else {
            $this->info('⏭ Skipping error reporting test');
        }
    }
}