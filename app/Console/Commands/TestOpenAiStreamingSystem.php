<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EnhancedOpenAiJobManager;
use App\Services\OpenAiStreamService;
use App\Models\OpenAiJobResult;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

/**
 * Comprehensive test suite for the OpenAI streaming job system
 * 
 * Features:
 * - End-to-end streaming functionality tests
 * - Error handling and retry logic tests
 * - Performance and scalability tests
 * - WebSocket and real-time progress tests
 * - System integration tests
 */
final class TestOpenAiStreamingSystem extends Command
{
    protected $signature = 'openai:test-streaming
                            {--suite=all : Test suite to run (all|basic|advanced|performance|integration)}
                            {--cleanup : Clean up test data after running}
                            {--mock : Use mock OpenAI responses}
                            {--concurrent=3 : Number of concurrent jobs for performance testing}
                            {--timeout=300 : Test timeout in seconds}';

    protected $description = 'Comprehensive test suite for OpenAI streaming job system';

    private EnhancedOpenAiJobManager $jobManager;
    private array $testResults = [];
    private array $createdJobIds = [];

    public function __construct(EnhancedOpenAiJobManager $jobManager)
    {
        parent::__construct();
        $this->jobManager = $jobManager;
    }

    public function handle(): void
    {
        $suite = $this->option('suite');
        $this->info('🧪 Starting OpenAI Streaming System Test Suite');
        $this->info("Test Suite: {$suite}");
        $this->info('═══════════════════════════════════════════════');
        $this->newLine();

        try {
            match($suite) {
                'basic' => $this->runBasicTests(),
                'advanced' => $this->runAdvancedTests(),
                'performance' => $this->runPerformanceTests(),
                'integration' => $this->runIntegrationTests(),
                'all' => $this->runAllTests(),
                default => $this->runAllTests()
            };

            $this->displayTestResults();

            if ($this->option('cleanup')) {
                $this->cleanupTestData();
            }

        } catch (\Exception $e) {
            $this->error("❌ Test suite failed: {$e->getMessage()}");
            
            if ($this->option('cleanup')) {
                $this->cleanupTestData();
            }
        }
    }

    /**
     * Run all test suites
     */
    private function runAllTests(): void
    {
        $this->runBasicTests();
        $this->runAdvancedTests();
        $this->runPerformanceTests();
        $this->runIntegrationTests();
    }

    /**
     * Run basic functionality tests
     */
    private function runBasicTests(): void
    {
        $this->info('🔧 Running Basic Tests...');
        $this->newLine();

        $this->testJobCreation();
        $this->testJobStatusTracking();
        $this->testJobCancellation();
        $this->testBasicStreaming();

        $this->newLine();
    }

    /**
     * Run advanced functionality tests
     */
    private function runAdvancedTests(): void
    {
        $this->info('🚀 Running Advanced Tests...');
        $this->newLine();

        $this->testBatchJobProcessing();
        $this->testRetryLogic();
        $this->testPriorityQueuing();
        $this->testValidationSystem();

        $this->newLine();
    }

    /**
     * Run performance tests
     */
    private function runPerformanceTests(): void
    {
        $this->info('⚡ Running Performance Tests...');
        $this->newLine();

        $this->testConcurrentJobs();
        $this->testMemoryUsage();
        $this->testStreamingEfficiency();

        $this->newLine();
    }

    /**
     * Run integration tests
     */
    private function runIntegrationTests(): void
    {
        $this->info('🔗 Running Integration Tests...');
        $this->newLine();

        $this->testWebSocketBroadcasting();
        $this->testCacheIntegration();
        $this->testDatabaseIntegration();
        $this->testSystemHealth();

        $this->newLine();
    }

    /**
     * Test job creation functionality
     */
    private function testJobCreation(): void
    {
        $testName = 'Job Creation';
        $this->line("Testing: {$testName}");

        try {
            $jobId = $this->jobManager->createEnhancedJob(
                prompt: 'Test security analysis of smart contract',
                jobType: 'security_analysis',
                config: ['test_mode' => true],
                metadata: ['test_suite' => 'basic', 'test_name' => $testName],
                priority: 'normal'
            );

            $this->createdJobIds[] = $jobId;
            
            if (!empty($jobId) && is_string($jobId)) {
                $this->recordTestResult($testName, true, "Job created with ID: {$jobId}");
            } else {
                $this->recordTestResult($testName, false, 'Invalid job ID returned');
            }

        } catch (\Exception $e) {
            $this->recordTestResult($testName, false, $e->getMessage());
        }
    }

    /**
     * Test job status tracking
     */
    private function testJobStatusTracking(): void
    {
        $testName = 'Job Status Tracking';
        $this->line("Testing: {$testName}");

        try {
            if (empty($this->createdJobIds)) {
                $this->recordTestResult($testName, false, 'No jobs available for status tracking');
                return;
            }

            $jobId = $this->createdJobIds[0];
            $status = $this->jobManager->getJobStatus($jobId);

            if (!empty($status) && is_array($status) && isset($status['job_id'])) {
                $this->recordTestResult($testName, true, "Status retrieved for job {$jobId}");
            } else {
                $this->recordTestResult($testName, false, 'Invalid status response');
            }

        } catch (\Exception $e) {
            $this->recordTestResult($testName, false, $e->getMessage());
        }
    }

    /**
     * Test job cancellation
     */
    private function testJobCancellation(): void
    {
        $testName = 'Job Cancellation';
        $this->line("Testing: {$testName}");

        try {
            // Create a job specifically for cancellation
            $jobId = $this->jobManager->createEnhancedJob(
                prompt: 'Test job for cancellation',
                jobType: 'security_analysis',
                config: ['test_mode' => true],
                metadata: ['test_suite' => 'cancellation_test'],
                priority: 'low'
            );

            $this->createdJobIds[] = $jobId;

            // Attempt to cancel it
            $cancelled = $this->jobManager->cancelJob($jobId, 'Test cancellation');

            if ($cancelled) {
                $this->recordTestResult($testName, true, "Job {$jobId} cancelled successfully");
            } else {
                $this->recordTestResult($testName, false, 'Job cancellation failed');
            }

        } catch (\Exception $e) {
            $this->recordTestResult($testName, false, $e->getMessage());
        }
    }

    /**
     * Test basic streaming functionality
     */
    private function testBasicStreaming(): void
    {
        $testName = 'Basic Streaming';
        $this->line("Testing: {$testName}");

        try {
            // Test streaming state initialization
            $jobId = 'test-streaming-' . uniqid();
            $streamingState = [
                'job_id' => $jobId,
                'status' => 'initializing',
                'tokens_received' => 0,
                'started_at' => now()->toISOString()
            ];

            Cache::put("openai_stream_{$jobId}", $streamingState, 600);
            
            $retrievedState = Cache::get("openai_stream_{$jobId}");

            if ($retrievedState && $retrievedState['job_id'] === $jobId) {
                $this->recordTestResult($testName, true, 'Streaming state management working');
            } else {
                $this->recordTestResult($testName, false, 'Streaming state not properly stored/retrieved');
            }

            // Cleanup
            Cache::forget("openai_stream_{$jobId}");

        } catch (\Exception $e) {
            $this->recordTestResult($testName, false, $e->getMessage());
        }
    }

    /**
     * Test batch job processing
     */
    private function testBatchJobProcessing(): void
    {
        $testName = 'Batch Job Processing';
        $this->line("Testing: {$testName}");

        try {
            $jobSpecs = [
                [
                    'prompt' => 'Test batch job 1',
                    'job_type' => 'security_analysis',
                    'metadata' => ['batch_test' => true]
                ],
                [
                    'prompt' => 'Test batch job 2', 
                    'job_type' => 'code_review',
                    'metadata' => ['batch_test' => true]
                ],
                [
                    'prompt' => 'Test batch job 3',
                    'job_type' => 'documentation',
                    'metadata' => ['batch_test' => true]
                ]
            ];

            $batchId = $this->jobManager->createBatchJobs(
                jobSpecs: $jobSpecs,
                batchConfig: ['test_mode' => true]
            );

            if (!empty($batchId) && is_string($batchId)) {
                $batchStatus = $this->jobManager->getBatchStatus($batchId);
                
                if ($batchStatus && $batchStatus['job_count'] === 3) {
                    $this->recordTestResult($testName, true, "Batch {$batchId} created with 3 jobs");
                    
                    // Track batch job IDs for cleanup
                    foreach ($batchStatus['jobs'] as $job) {
                        $this->createdJobIds[] = $job['job_id'];
                    }
                } else {
                    $this->recordTestResult($testName, false, 'Batch status incorrect');
                }
            } else {
                $this->recordTestResult($testName, false, 'Invalid batch ID returned');
            }

        } catch (\Exception $e) {
            $this->recordTestResult($testName, false, $e->getMessage());
        }
    }

    /**
     * Test retry logic
     */
    private function testRetryLogic(): void
    {
        $testName = 'Retry Logic';
        $this->line("Testing: {$testName}");

        try {
            // Create a job record that appears to be failed
            $originalJobId = 'test-failed-' . uniqid();
            $failedJob = OpenAiJobResult::create([
                'job_id' => $originalJobId,
                'job_type' => 'security_analysis',
                'status' => 'failed',
                'prompt' => 'Test prompt for retry',
                'error_message' => 'Simulated failure for test',
                'failed_at' => now(),
                'attempts_made' => 1,
                'config' => ['test_mode' => true],
                'metadata' => ['test_suite' => 'retry_test']
            ]);

            $this->createdJobIds[] = $originalJobId;

            // Attempt to retry the job
            $retryJobId = $this->jobManager->retryJob($originalJobId, ['retry_test' => true]);

            if (!empty($retryJobId) && $retryJobId !== $originalJobId) {
                $this->createdJobIds[] = $retryJobId;
                $this->recordTestResult($testName, true, "Retry created new job: {$retryJobId}");
            } else {
                $this->recordTestResult($testName, false, 'Retry failed to create new job');
            }

        } catch (\Exception $e) {
            $this->recordTestResult($testName, false, $e->getMessage());
        }
    }

    /**
     * Test priority queuing
     */
    private function testPriorityQueuing(): void
    {
        $testName = 'Priority Queuing';
        $this->line("Testing: {$testName}");

        try {
            // Create jobs with different priorities
            $urgentJob = $this->jobManager->createEnhancedJob(
                prompt: 'Urgent priority test',
                jobType: 'security_analysis',
                priority: 'urgent',
                metadata: ['priority_test' => true]
            );

            $lowJob = $this->jobManager->createEnhancedJob(
                prompt: 'Low priority test',
                jobType: 'security_analysis', 
                priority: 'low',
                metadata: ['priority_test' => true]
            );

            $this->createdJobIds[] = $urgentJob;
            $this->createdJobIds[] = $lowJob;

            if (!empty($urgentJob) && !empty($lowJob)) {
                $this->recordTestResult($testName, true, 'Priority jobs created successfully');
            } else {
                $this->recordTestResult($testName, false, 'Failed to create priority jobs');
            }

        } catch (\Exception $e) {
            $this->recordTestResult($testName, false, $e->getMessage());
        }
    }

    /**
     * Test validation system
     */
    private function testValidationSystem(): void
    {
        $testName = 'Validation System';
        $this->line("Testing: {$testName}");

        try {
            // Test invalid job type
            try {
                $this->jobManager->createEnhancedJob(
                    prompt: 'Test prompt',
                    jobType: 'invalid_type',
                    priority: 'normal'
                );
                
                $this->recordTestResult($testName, false, 'Validation did not catch invalid job type');
            } catch (\InvalidArgumentException $e) {
                // This is expected
                $this->recordTestResult($testName, true, 'Validation correctly rejected invalid job type');
            }

        } catch (\Exception $e) {
            $this->recordTestResult($testName, false, "Unexpected error: {$e->getMessage()}");
        }
    }

    /**
     * Test concurrent jobs
     */
    private function testConcurrentJobs(): void
    {
        $testName = 'Concurrent Jobs';
        $this->line("Testing: {$testName}");

        try {
            $concurrent = (int) $this->option('concurrent');
            $startTime = microtime(true);
            $jobIds = [];

            // Create multiple jobs concurrently
            for ($i = 1; $i <= $concurrent; $i++) {
                $jobId = $this->jobManager->createEnhancedJob(
                    prompt: "Concurrent test job {$i}",
                    jobType: 'security_analysis',
                    config: ['test_mode' => true, 'concurrent_test' => true],
                    metadata: ['concurrent_index' => $i],
                    priority: 'normal'
                );
                
                $jobIds[] = $jobId;
                $this->createdJobIds[] = $jobId;
            }

            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);

            if (count($jobIds) === $concurrent) {
                $this->recordTestResult($testName, true, "Created {$concurrent} jobs in {$duration}ms");
            } else {
                $this->recordTestResult($testName, false, 'Not all concurrent jobs were created');
            }

        } catch (\Exception $e) {
            $this->recordTestResult($testName, false, $e->getMessage());
        }
    }

    /**
     * Test memory usage
     */
    private function testMemoryUsage(): void
    {
        $testName = 'Memory Usage';
        $this->line("Testing: {$testName}");

        try {
            $startMemory = memory_get_usage(true);
            
            // Create several jobs and check memory usage
            for ($i = 0; $i < 10; $i++) {
                $jobId = $this->jobManager->createEnhancedJob(
                    prompt: "Memory test job {$i}",
                    jobType: 'security_analysis',
                    metadata: ['memory_test' => true]
                );
                $this->createdJobIds[] = $jobId;
            }

            $endMemory = memory_get_usage(true);
            $memoryUsed = $endMemory - $startMemory;
            $memoryUsedMB = round($memoryUsed / 1024 / 1024, 2);

            if ($memoryUsedMB < 10) { // Less than 10MB should be reasonable
                $this->recordTestResult($testName, true, "Memory usage: {$memoryUsedMB}MB");
            } else {
                $this->recordTestResult($testName, false, "High memory usage: {$memoryUsedMB}MB");
            }

        } catch (\Exception $e) {
            $this->recordTestResult($testName, false, $e->getMessage());
        }
    }

    /**
     * Test streaming efficiency
     */
    private function testStreamingEfficiency(): void
    {
        $testName = 'Streaming Efficiency';
        $this->line("Testing: {$testName}");

        try {
            // Simulate streaming state updates
            $jobId = 'test-efficiency-' . uniqid();
            $startTime = microtime(true);
            
            for ($i = 1; $i <= 100; $i++) {
                $streamingState = [
                    'job_id' => $jobId,
                    'tokens_received' => $i,
                    'progress_percentage' => $i,
                    'last_activity' => now()->toISOString()
                ];
                
                Cache::put("openai_stream_{$jobId}", $streamingState, 600);
            }

            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);

            // Cleanup
            Cache::forget("openai_stream_{$jobId}");

            if ($duration < 1000) { // Less than 1 second for 100 updates
                $this->recordTestResult($testName, true, "100 updates in {$duration}ms");
            } else {
                $this->recordTestResult($testName, false, "Slow streaming updates: {$duration}ms");
            }

        } catch (\Exception $e) {
            $this->recordTestResult($testName, false, $e->getMessage());
        }
    }

    /**
     * Test WebSocket broadcasting
     */
    private function testWebSocketBroadcasting(): void
    {
        $testName = 'WebSocket Broadcasting';
        $this->line("Testing: {$testName}");

        try {
            // Test Redis publishing for WebSocket broadcasting
            $testMessage = [
                'job_id' => 'test-websocket-' . uniqid(),
                'type' => 'token',
                'token' => 'test_token',
                'timestamp' => now()->toISOString()
            ];

            $published = Redis::publish('openai-stream', json_encode($testMessage));

            if ($published >= 0) { // Redis returns number of subscribers
                $this->recordTestResult($testName, true, 'Redis publishing working');
            } else {
                $this->recordTestResult($testName, false, 'Redis publishing failed');
            }

        } catch (\Exception $e) {
            $this->recordTestResult($testName, false, $e->getMessage());
        }
    }

    /**
     * Test cache integration
     */
    private function testCacheIntegration(): void
    {
        $testName = 'Cache Integration';
        $this->line("Testing: {$testName}");

        try {
            $testKey = 'openai_test_' . uniqid();
            $testData = ['test' => true, 'timestamp' => now()->toISOString()];

            // Test cache storage and retrieval
            Cache::put($testKey, $testData, 600);
            $retrieved = Cache::get($testKey);

            if ($retrieved && $retrieved['test'] === true) {
                $this->recordTestResult($testName, true, 'Cache integration working');
            } else {
                $this->recordTestResult($testName, false, 'Cache integration failed');
            }

            // Cleanup
            Cache::forget($testKey);

        } catch (\Exception $e) {
            $this->recordTestResult($testName, false, $e->getMessage());
        }
    }

    /**
     * Test database integration
     */
    private function testDatabaseIntegration(): void
    {
        $testName = 'Database Integration';
        $this->line("Testing: {$testName}");

        try {
            // Test database connectivity and basic operations
            $testJob = OpenAiJobResult::create([
                'job_id' => 'test-db-' . uniqid(),
                'job_type' => 'test',
                'status' => 'pending',
                'prompt' => 'Database integration test',
                'config' => ['test_mode' => true],
                'metadata' => ['test_suite' => 'database_integration']
            ]);

            $this->createdJobIds[] = $testJob->job_id;

            if ($testJob->exists) {
                $this->recordTestResult($testName, true, 'Database integration working');
            } else {
                $this->recordTestResult($testName, false, 'Database record not created');
            }

        } catch (\Exception $e) {
            $this->recordTestResult($testName, false, $e->getMessage());
        }
    }

    /**
     * Test system health
     */
    private function testSystemHealth(): void
    {
        $testName = 'System Health';
        $this->line("Testing: {$testName}");

        try {
            $systemStatus = $this->jobManager->getSystemStatus();
            
            if (!empty($systemStatus) && is_array($systemStatus)) {
                $health = $systemStatus['system_health'] ?? [];
                $healthyComponents = 0;
                $totalComponents = 0;

                foreach ($health as $component => $status) {
                    $totalComponents++;
                    if ($status === 'ok') {
                        $healthyComponents++;
                    }
                }

                $healthPercentage = $totalComponents > 0 ? 
                    round(($healthyComponents / $totalComponents) * 100, 1) : 0;

                if ($healthPercentage >= 75) {
                    $this->recordTestResult($testName, true, "System health: {$healthPercentage}%");
                } else {
                    $this->recordTestResult($testName, false, "Low system health: {$healthPercentage}%");
                }
            } else {
                $this->recordTestResult($testName, false, 'Unable to get system status');
            }

        } catch (\Exception $e) {
            $this->recordTestResult($testName, false, $e->getMessage());
        }
    }

    /**
     * Record test result
     */
    private function recordTestResult(string $testName, bool $passed, string $message): void
    {
        $status = $passed ? '✅' : '❌';
        $this->line("  {$status} {$testName}: {$message}");
        
        $this->testResults[] = [
            'test' => $testName,
            'passed' => $passed,
            'message' => $message,
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Display test results summary
     */
    private function displayTestResults(): void
    {
        $this->newLine();
        $this->info('📊 Test Results Summary');
        $this->info('═══════════════════════════════════════');

        $totalTests = count($this->testResults);
        $passedTests = count(array_filter($this->testResults, fn($result) => $result['passed']));
        $failedTests = $totalTests - $passedTests;
        $successRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 1) : 0;

        $this->table(['Metric', 'Value'], [
            ['Total Tests', $totalTests],
            ['Passed', $passedTests],
            ['Failed', $failedTests],
            ['Success Rate', $successRate . '%'],
            ['Test Duration', $this->getTestDuration()]
        ]);

        if ($failedTests > 0) {
            $this->newLine();
            $this->warn('❌ Failed Tests:');
            foreach ($this->testResults as $result) {
                if (!$result['passed']) {
                    $this->line("  • {$result['test']}: {$result['message']}");
                }
            }
        }

        $this->newLine();
        if ($successRate >= 90) {
            $this->info('🎉 Excellent! System is performing very well.');
        } elseif ($successRate >= 75) {
            $this->info('👍 Good! Most functionality is working correctly.');
        } elseif ($successRate >= 50) {
            $this->warn('⚠️ Fair! Some issues need attention.');
        } else {
            $this->error('🚨 Poor! Significant issues detected. Review system health.');
        }
    }

    /**
     * Clean up test data
     */
    private function cleanupTestData(): void
    {
        $this->newLine();
        $this->info('🧹 Cleaning up test data...');

        $cleaned = 0;
        foreach ($this->createdJobIds as $jobId) {
            try {
                OpenAiJobResult::where('job_id', $jobId)->delete();
                Cache::forget("openai_stream_{$jobId}");
                $cleaned++;
            } catch (\Exception $e) {
                $this->warn("Could not clean up job {$jobId}: {$e->getMessage()}");
            }
        }

        $this->info("✅ Cleaned up {$cleaned} test records.");
    }

    /**
     * Get test duration
     */
    private function getTestDuration(): string
    {
        // This would be more accurate with a proper start time tracking
        return 'N/A';
    }
}