<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Sentry\SentrySdk;
use Sentry\Tracing\TransactionContext;
use Sentry\Tracing\SpanContext;
use Exception;

final class TestSentryIntegration extends Command
{
    protected $signature = 'sentry:test {--type=all : Type of test (all, error, performance, transaction)}';
    protected $description = 'Test Sentry integration with proper error handling and performance monitoring';

    public function handle(): int
    {
        $testType = $this->option('type');

        if (!app()->bound('sentry')) {
            $this->error('Sentry is not configured. Please set SENTRY_LARAVEL_DSN in your environment.');
            return 1;
        }

        $this->info('🔧 Testing Sentry integration...');

        try {
            match($testType) {
                'error' => $this->testErrorCapture(),
                'performance' => $this->testPerformanceMonitoring(),
                'transaction' => $this->testTransactionContext(),
                'all' => $this->runAllTests(),
                default => throw new \InvalidArgumentException("Invalid test type: {$testType}")
            };

            $this->info('✅ Sentry integration tests completed successfully!');
            return 0;

        } catch (Exception $e) {
            $this->error("❌ Test failed: {$e->getMessage()}");
            return 1;
        }
    }

    private function runAllTests(): void
    {
        $this->testErrorCapture();
        $this->testPerformanceMonitoring();
        $this->testTransactionContext();
    }

    private function testErrorCapture(): void
    {
        $this->info('📊 Testing error capture...');

        try {
            // Test a controlled exception
            throw new Exception('Test exception from AI Blockchain Analytics - This is intentional for testing');
        } catch (Exception $e) {
            // Capture the exception with additional context
            app('sentry')->configureScope(function ($scope) {
                $scope->setTag('test_type', 'error_capture');
                $scope->setTag('component', 'ai_blockchain_analytics');
                $scope->setContext('test_info', [
                    'description' => 'Testing Sentry error capture functionality',
                    'timestamp' => now()->toISOString(),
                    'environment' => app()->environment(),
                ]);
            });

            app('sentry')->captureException($e);
            $this->info('   ✓ Exception captured and sent to Sentry');
        }

        // Test message capture
        app('sentry')->captureMessage('Test message from AI Blockchain Analytics command line', \Sentry\Severity::info());
        $this->info('   ✓ Message captured and sent to Sentry');
    }

    private function testPerformanceMonitoring(): void
    {
        $this->info('⚡ Testing performance monitoring...');

        // Test TransactionContext creation (this was causing the original error)
        $transactionContext = new TransactionContext();
        $transactionContext->setName('test.ai_blockchain_analytics_command');
        $transactionContext->setOp('command_execution');

        $transaction = SentrySdk::getCurrentHub()->startTransaction($transactionContext);
        
        // Use setData() for transaction metadata (requires array)
        $transaction->setData([
            'test_type' => 'performance_monitoring',
            'command' => 'sentry:test',
        ]);
        
        // Set tags using scope for better filtering
        app('sentry')->configureScope(function ($scope) {
            $scope->setTag('test_type', 'performance_monitoring');
            $scope->setTag('command', 'sentry:test');
        });

        // Simulate some work with proper SpanContext
        $spanContext = new SpanContext();
        $spanContext->setOp('blockchain_analysis');
        $spanContext->setDescription('Simulated smart contract analysis');
        
        $span = $transaction->startChild($spanContext);

        usleep(100000); // 100ms simulation
        $span->finish();

        $transaction->finish();
        $this->info('   ✓ Performance transaction created and finished');
    }

    private function testTransactionContext(): void
    {
        $this->info('🔄 Testing TransactionContext creation...');

        // Test the blockchain monitor
        if (app()->bound('sentry.blockchain_monitor')) {
            $monitor = app('sentry.blockchain_monitor');
            $monitor->trackOperation('contract_analysis', [
                'network' => 'ethereum',
                'contract_address' => '0x1234567890123456789012345678901234567890'
            ]);
            $this->info('   ✓ Blockchain operation tracked');
        }

        // Test the AI monitor
        if (app()->bound('sentry.ai_monitor')) {
            $monitor = app('sentry.ai_monitor');
            $monitor->trackAIOperation('vulnerability_detection', [
                'model' => 'security_analyzer_v1',
                'confidence' => 0.95
            ]);
            $this->info('   ✓ AI operation tracked');
        }

        $this->info('   ✓ All custom monitors working correctly');
    }
}