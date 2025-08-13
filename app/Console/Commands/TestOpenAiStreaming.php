<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\EnhancedOpenAiStreamingJob;
use App\Services\OpenAiJobProgressTracker;
use App\Models\OpenAiJobResult;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class TestOpenAiStreaming extends Command
{
    protected $signature = 'openai:test-streaming 
                            {--demo : Run demonstration scenarios}
                            {--load-test : Run load testing with multiple jobs}
                            {--progress : Test progress tracking features}
                            {--cleanup : Clean up test data}
                            {--jobs=3 : Number of concurrent jobs for load testing}
                            {--monitor : Monitor running jobs in real-time}';

    protected $description = 'Test and demonstrate OpenAI streaming functionality';

    private OpenAiJobProgressTracker $progressTracker;

    public function __construct(OpenAiJobProgressTracker $progressTracker)
    {
        parent::__construct();
        $this->progressTracker = $progressTracker;
    }

    public function handle()
    {
        $this->info('🤖 OpenAI Streaming Test Suite');
        $this->info('================================');

        if ($this->option('cleanup')) {
            return $this->runCleanup();
        }

        if ($this->option('monitor')) {
            return $this->monitorJobs();
        }

        if ($this->option('demo')) {
            return $this->runDemoScenarios();
        }

        if ($this->option('load-test')) {
            return $this->runLoadTest();
        }

        if ($this->option('progress')) {
            return $this->testProgressTracking();
        }

        // Interactive mode
        return $this->runInteractiveMode();
    }

    private function runDemoScenarios(): int
    {
        $this->info('🎭 Running demonstration scenarios...');
        
        $scenarios = [
            [
                'name' => 'Security Analysis Demo',
                'job_type' => 'security_analysis',
                'prompt' => $this->getSecurityAnalysisPrompt(),
                'config' => [
                    'model' => 'gpt-4',
                    'max_tokens' => 2000,
                    'temperature' => 0.1,
                    'priority' => 'high'
                ]
            ],
            [
                'name' => 'Code Review Demo',
                'job_type' => 'code_review',
                'prompt' => $this->getCodeReviewPrompt(),
                'config' => [
                    'model' => 'gpt-3.5-turbo',
                    'max_tokens' => 1500,
                    'temperature' => 0.2,
                    'priority' => 'normal'
                ]
            ]
        ];

        $jobIds = [];
        
        foreach ($scenarios as $scenario) {
            $this->info("\n📋 Running: {$scenario['name']}");
            
            $jobId = $this->createTestJob($scenario['job_type'], $scenario['prompt'], $scenario['config']);
            $jobIds[] = $jobId;
            
            $this->info("   Job ID: {$jobId}");
            $this->info("   Model: {$scenario['config']['model']}");
            $this->info("   Priority: {$scenario['config']['priority']}");
            
            // Short delay between jobs
            sleep(2);
        }

        $this->info("\n⏱️  Monitoring job progress...");
        $this->monitorJobsProgress($jobIds, 60); // Monitor for 60 seconds

        return 0;
    }

    private function createTestJob(string $jobType, string $prompt, array $config, array $metadata = []): string
    {
        $jobId = 'test_' . Str::random(12);
        
        $job = new EnhancedOpenAiStreamingJob(
            prompt: $prompt,
            jobId: $jobId,
            config: $config,
            metadata: array_merge($metadata, [
                'created_via' => 'test_command',
                'test_scenario' => true
            ]),
            jobType: $jobType,
            userId: null,
            priority: $config['priority'] ?? 'normal'
        );

        dispatch($job);

        // Initialize progress tracking
        $this->progressTracker->initializeJobProgress($jobId, array_merge($config, [
            'job_type' => $jobType,
            'metadata' => $metadata
        ]));

        return $jobId;
    }

    private function monitorJobsProgress(array $jobIds, int $durationSeconds): void
    {
        $startTime = time();
        $endTime = $startTime + $durationSeconds;

        while (time() < $endTime) {
            $this->info("\n" . str_repeat('=', 60));
            $this->info('⏰ ' . date('H:i:s') . ' - Monitoring Progress (' . (($endTime - time())) . 's remaining)');
            
            foreach ($jobIds as $jobId) {
                $this->displayJobStatus($jobId);
            }

            if (time() < $endTime) {
                sleep(5); // Update every 5 seconds
            }
        }

        $this->info("\n✅ Monitoring completed");
    }

    private function displayJobStatus(string $jobId): void
    {
        $job = OpenAiJobResult::where('job_id', $jobId)->first();
        $progress = $this->progressTracker->getJobProgress($jobId);

        if (!$job) {
            return;
        }

        $status = $job->status;
        $progressPercent = $progress['progress_percentage'] ?? 0;
        $tokensProcessed = $progress['tokens_processed'] ?? 0;
        $currentStage = $progress['current_stage'] ?? 'unknown';

        $statusEmoji = match($status) {
            'completed' => '✅',
            'failed' => '❌', 
            'processing' => '🔄',
            'pending' => '⏳',
            default => '❓'
        };

        $this->line(sprintf(
            '%s %s | %s | %.1f%% | %d tokens | Stage: %s',
            $statusEmoji,
            substr($jobId, 0, 12) . '...',
            ucfirst($status),
            $progressPercent,
            $tokensProcessed,
            $currentStage
        ));
    }

    private function runCleanup(): int
    {
        $this->info('🧹 Cleaning up test data...');

        // Clean up test jobs
        $testJobs = OpenAiJobResult::where('job_id', 'like', 'stream_%')
            ->orWhere('job_id', 'like', 'test_%')
            ->get();

        foreach ($testJobs as $job) {
            // Clean cache data
            Cache::forget("openai_stream_{$job->job_id}");
            Cache::forget("openai_job_progress:{$job->job_id}");
            
            // Clean Redis data
            Redis::del("openai_progress_events:{$job->job_id}");
            Redis::del("stream:{$job->job_id}");
            
            $job->delete();
        }

        $cleanedCount = $testJobs->count();
        $this->progressTracker->cleanupCompletedJobs(1); // Cleanup jobs older than 1 hour

        $this->info("✅ Cleaned up {$cleanedCount} test jobs and associated data");

        return 0;
    }

    private function testProgressTracking(): int
    {
        $this->info('📈 Testing enhanced progress tracking...');

        $jobId = 'progress_test_' . Str::random(8);
        $config = [
            'job_type' => 'security_analysis',
            'model' => 'gpt-4',
            'max_tokens' => 2000,
            'metadata' => ['test_mode' => true]
        ];

        // Initialize progress tracking
        $this->info("\n1️⃣ Initializing progress tracking...");
        $this->progressTracker->initializeJobProgress($jobId, $config);

        // Simulate progress updates
        $this->info("\n2️⃣ Simulating progress updates...");
        
        $stages = ['setup', 'code_parsing', 'vulnerability_scan', 'analysis_generation', 'validation'];
        $totalTokens = 2000;
        $processedTokens = 0;

        foreach ($stages as $index => $stage) {
            $this->info("   Stage: {$stage}");
            
            $this->progressTracker->updateJobProgress($jobId, [
                'current_stage' => $stage,
                'status' => 'processing'
            ]);

            // Simulate token processing
            $stageTokens = intval($totalTokens / count($stages));
            $processedTokens += $stageTokens;
            
            $this->progressTracker->updateJobProgress($jobId, [
                'tokens_processed' => $processedTokens,
                'performance_metrics' => [
                    'memory_usage_mb' => rand(100, 500),
                    'cpu_usage_percent' => rand(20, 80)
                ]
            ]);

            // Complete stage
            $this->progressTracker->completeJobStage($jobId, $stage, [
                'tokens_processed' => $stageTokens,
                'duration_ms' => rand(1000, 5000)
            ]);

            sleep(1);
        }

        // Complete job
        $this->info("\n3️⃣ Completing job...");
        $this->progressTracker->completeJob($jobId, [
            'total_findings' => 15,
            'critical_findings' => 3
        ]);

        return 0;
    }

    private function runInteractiveMode(): int
    {
        $this->info('🎮 Interactive testing mode');

        while (true) {
            $choice = $this->choice(
                'What would you like to test?',
                [
                    'Demo scenarios',
                    'Progress tracking',
                    'Monitor jobs',
                    'Cleanup test data',
                    'Exit'
                ]
            );

            switch ($choice) {
                case 'Demo scenarios':
                    $this->runDemoScenarios();
                    break;
                case 'Progress tracking':
                    $this->testProgressTracking();
                    break;
                case 'Monitor jobs':
                    $this->monitorJobs();
                    break;
                case 'Cleanup test data':
                    $this->runCleanup();
                    break;
                case 'Exit':
                    $this->info('👋 Goodbye!');
                    return 0;
            }

            $this->newLine();
        }
    }

    private function monitorJobs(): int
    {
        $this->info('👀 Real-time job monitoring...');
        
        $runningJobs = OpenAiJobResult::where('status', 'processing')
            ->orderBy('started_at', 'desc')
            ->limit(10)
            ->get();

        if ($runningJobs->isEmpty()) {
            $this->warn('No running jobs found. Create some jobs first with --demo');
            return 1;
        }

        $jobIds = $runningJobs->pluck('job_id')->toArray();
        $this->info("Monitoring " . count($jobIds) . " running jobs...");

        $this->monitorJobsProgress($jobIds, 120); // Monitor for 2 minutes

        return 0;
    }

    private function getSecurityAnalysisPrompt(): string
    {
        return "Analyze this Solidity smart contract for security vulnerabilities:\n\n" . 
               "pragma solidity ^0.8.0;\n\n" .
               "contract TestContract {\n" .
               "    mapping(address => uint256) public balances;\n\n" .
               "    function withdraw(uint256 amount) external {\n" .
               "        require(balances[msg.sender] >= amount, \"Insufficient balance\");\n" .
               "        payable(msg.sender).transfer(amount);\n" .
               "        balances[msg.sender] -= amount;\n" .
               "    }\n" .
               "}";
    }

    private function getCodeReviewPrompt(): string
    {
        return "Review this JavaScript function for code quality and suggest improvements:\n\n" .
               "function calculateTotal(items) {\n" .
               "  var total = 0;\n" .
               "  for (var i = 0; i < items.length; i++) {\n" .
               "    if (items[i].price) {\n" .
               "      total += items[i].price * items[i].quantity;\n" .
               "    }\n" .
               "  }\n" .
               "  return total;\n" .
               "}";
    }
}