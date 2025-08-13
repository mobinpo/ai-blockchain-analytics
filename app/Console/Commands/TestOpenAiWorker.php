<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\OpenAiStreamingJob;
use App\Models\OpenAiJobResult;
use App\Services\OpenAiStreamService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

class TestOpenAiWorker extends Command
{
    protected $signature = 'openai:test-worker 
                           {--type=security_analysis : Job type to test}
                           {--model=gpt-4 : OpenAI model to use}
                           {--async : Run job asynchronously}
                           {--sync : Run job synchronously}
                           {--monitor : Monitor streaming progress}
                           {--priority=normal : Job priority (urgent,high,normal,low)}
                           {--prompt= : Custom prompt to test}
                           {--dry-run : Show what would be executed without running}';

    protected $description = 'Test the OpenAI streaming job worker with Horizon';

    public function handle(): int
    {
        $this->displayHeader();

        $type = $this->option('type');
        $model = $this->option('model');
        $async = $this->option('async');
        $sync = $this->option('sync');
        $monitor = $this->option('monitor');
        $priority = $this->option('priority');
        $customPrompt = $this->option('prompt');
        $dryRun = $this->option('dry-run');

        // Default to async if neither specified
        if (!$async && !$sync) {
            $async = true;
        }

        try {
            if ($dryRun) {
                return $this->showDryRun($type, $model, $async, $priority, $customPrompt);
            }

            if ($monitor) {
                return $this->monitorJobs();
            }

            if ($sync) {
                return $this->runSynchronous($type, $model, $priority, $customPrompt);
            } else {
                return $this->runAsynchronous($type, $model, $priority, $customPrompt);
            }

        } catch (\Exception $e) {
            $this->error('❌ Test failed: ' . $e->getMessage());
            
            if ($this->option('verbose')) {
                $this->error('Stack trace:');
                $this->line($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    private function displayHeader(): void
    {
        $this->info('🤖 OpenAI Streaming Job Worker Test');
        $this->info('Testing Horizon job processing with token streaming');
        $this->newLine();
    }

    private function runAsynchronous(string $type, string $model, string $priority, ?string $customPrompt): int
    {
        $jobId = 'test_' . Str::random(8);
        $prompt = $customPrompt ?: $this->getTestPrompt($type);
        $config = $this->buildJobConfig($model, $priority);

        $this->info("🚀 Dispatching asynchronous OpenAI job...");
        $this->displayJobConfiguration($jobId, $type, $config, strlen($prompt));

        // Dispatch the job
        $job = new OpenAiStreamingJob(
            prompt: $prompt,
            jobId: $jobId,
            config: $config,
            metadata: [
                'test_run' => true,
                'dispatched_via' => 'artisan_command',
                'dispatched_at' => now()->toISOString()
            ],
            jobType: $type,
            userId: null
        );

        // Add job tags for better monitoring
        $job->onQueue($this->getQueueName($type, $priority));

        dispatch($job);

        $this->info("✅ Job dispatched successfully!");
        $this->newLine();

        $this->displayMonitoringInfo($jobId);
        $this->displayHorizonInfo();

        // Optionally start monitoring
        if ($this->confirm('Start real-time monitoring?', false)) {
            return $this->startRealTimeMonitoring($jobId);
        }

        return Command::SUCCESS;
    }

    private function runSynchronous(string $type, string $model, string $priority, ?string $customPrompt): int
    {
        $jobId = 'sync_test_' . Str::random(8);
        $prompt = $customPrompt ?: $this->getTestPrompt($type);

        $this->info("⚡ Running synchronous OpenAI streaming test...");
        $this->newLine();

        // Create stream service instance
        $config = $this->buildJobConfig($model, $priority);
        $streamService = new OpenAiStreamService(
            $config['model'],
            $config['max_tokens'],
            $config['temperature']
        );

        $this->displayJobConfiguration($jobId, $type, $config, strlen($prompt));
        $this->newLine();

        $startTime = microtime(true);
        
        try {
            $this->info("🔄 Starting OpenAI streaming...");
            
            // Execute streaming with progress display
            $response = $streamService->streamSecurityAnalysis(
                $prompt,
                $jobId,
                ['system_prompt' => $this->getSystemPrompt($type)]
            );

            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000);

            $this->newLine();
            $this->info("✅ Streaming completed successfully!");
            
            // Display results
            $this->displaySyncResults($jobId, $response, $duration, $streamService);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Streaming failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function monitorJobs(): int
    {
        $this->info("👀 Monitoring OpenAI jobs...");
        $this->newLine();

        // Get recent jobs
        $jobs = OpenAiJobResult::recent(6)->orderBy('created_at', 'desc')->take(10)->get();

        if ($jobs->isEmpty()) {
            $this->warn("No recent OpenAI jobs found.");
            return Command::SUCCESS;
        }

        $this->displayJobsTable($jobs);
        $this->newLine();

        if ($this->confirm('Monitor a specific job in real-time?')) {
            $jobId = $this->ask('Enter job ID to monitor:');
            if ($jobId) {
                return $this->startRealTimeMonitoring($jobId);
            }
        }

        return Command::SUCCESS;
    }

    private function showDryRun(string $type, string $model, bool $async, string $priority, ?string $customPrompt): int
    {
        $this->info("🔍 Dry run - showing what would be executed:");
        $this->newLine();

        $jobId = 'dryrun_' . Str::random(8);
        $prompt = $customPrompt ?: $this->getTestPrompt($type);
        $config = $this->buildJobConfig($model, $priority);

        $this->table(
            ['Property', 'Value'],
            [
                ['Execution Mode', $async ? 'Asynchronous (Horizon)' : 'Synchronous'],
                ['Job ID', $jobId],
                ['Job Type', $type],
                ['Queue', $this->getQueueName($type, $priority)],
                ['Model', $config['model']],
                ['Max Tokens', $config['max_tokens']],
                ['Temperature', $config['temperature']],
                ['Priority', $priority],
                ['Prompt Length', strlen($prompt) . ' characters'],
                ['Estimated Cost', $this->estimateCost($config['max_tokens'], $model)],
            ]
        );

        $this->newLine();
        $this->info("Prompt Preview:");
        $this->line(substr($prompt, 0, 200) . (strlen($prompt) > 200 ? '...' : ''));

        return Command::SUCCESS;
    }

    private function startRealTimeMonitoring(string $jobId): int
    {
        $this->info("📊 Starting real-time monitoring for job: {$jobId}");
        $this->newLine();

        $maxIterations = 120; // 2 minutes max
        $iteration = 0;

        while ($iteration < $maxIterations) {
            $iteration++;
            
            // Get streaming status from cache
            $streamStatus = Cache::get("openai_stream_{$jobId}");
            
            // Get job result from database
            $jobResult = OpenAiJobResult::where('job_id', $jobId)->first();

            if (!$streamStatus && !$jobResult) {
                $this->warn("Job {$jobId} not found. It may not have started yet.");
                sleep(2);
                continue;
            }

            // Clear screen and display current status
            if ($iteration > 1) {
                $this->output->write("\033[2J\033[H"); // Clear screen and move cursor to top
            }

            $this->displayRealTimeStatus($jobId, $streamStatus, $jobResult, $iteration);

            // Check if job is completed
            if ($jobResult && in_array($jobResult->status, ['completed', 'failed'])) {
                $this->newLine();
                $this->info("✅ Job completed with status: {$jobResult->status}");
                
                if ($jobResult->status === 'completed') {
                    $this->displayFinalResults($jobResult);
                } else {
                    $this->error("Error: " . $jobResult->error_message);
                }
                
                break;
            }

            sleep(1); // Update every second
        }

        if ($iteration >= $maxIterations) {
            $this->warn("⏰ Monitoring timeout reached");
        }

        return Command::SUCCESS;
    }

    private function displayJobConfiguration(string $jobId, string $type, array $config, int $promptLength): void
    {
        $this->table(
            ['Configuration', 'Value'],
            [
                ['Job ID', $jobId],
                ['Job Type', $type],
                ['Model', $config['model']],
                ['Max Tokens', number_format($config['max_tokens'])],
                ['Temperature', $config['temperature']],
                ['Priority', $config['priority']],
                ['Queue', $this->getQueueName($type, $config['priority'])],
                ['Prompt Length', number_format($promptLength) . ' characters'],
                ['Est. Cost', $this->estimateCost($config['max_tokens'], $config['model'])],
            ]
        );
    }

    private function displayMonitoringInfo(string $jobId): void
    {
        $this->info("📊 Monitoring Information:");
        $this->line("  • Job ID: {$jobId}");
        $this->line("  • Horizon Dashboard: http://localhost:8003/horizon");
        $this->line("  • Monitor Command: php artisan openai:test-worker --monitor");
        $this->line("  • Specific Monitor: php artisan openai:test-worker --monitor (then enter job ID)");
        $this->newLine();
    }

    private function displayHorizonInfo(): void
    {
        $this->info("🔄 Horizon Queue Information:");
        $this->line("  • Dashboard: http://localhost:8003/horizon");
        $this->line("  • Start Horizon: php artisan horizon");
        $this->line("  • Queue Status: php artisan queue:work");
        $this->newLine();
    }

    private function displayJobsTable($jobs): void
    {
        $tableData = [];
        
        foreach ($jobs as $job) {
            $tableData[] = [
                'ID' => substr($job->job_id, 0, 12) . '...',
                'Type' => $job->job_type,
                'Status' => $this->colorizeStatus($job->status),
                'Model' => $job->getModel(),
                'Tokens' => number_format($job->getTotalTokens()),
                'Duration' => $job->getProcessingDurationSeconds() . 's',
                'Cost' => '$' . number_format($job->getEstimatedCost(), 4),
                'Created' => $job->created_at->diffForHumans()
            ];
        }

        $this->table(
            ['ID', 'Type', 'Status', 'Model', 'Tokens', 'Duration', 'Cost', 'Created'],
            $tableData
        );
    }

    private function displayRealTimeStatus(string $jobId, ?array $streamStatus, ?OpenAiJobResult $jobResult, int $iteration): void
    {
        $this->info("📊 Real-time Monitoring: {$jobId} (Update #{$iteration})");
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        if ($jobResult) {
            $this->line("Database Status: " . $this->colorizeStatus($jobResult->status));
            $this->line("Started: " . ($jobResult->started_at ? $jobResult->started_at->diffForHumans() : 'Not started'));
            
            if ($jobResult->processing_time_ms) {
                $this->line("Processing Time: " . round($jobResult->processing_time_ms / 1000, 2) . 's');
            }
        }

        if ($streamStatus) {
            $this->newLine();
            $this->line("Streaming Status: " . ($streamStatus['status'] ?? 'unknown'));
            $this->line("Tokens Received: " . number_format($streamStatus['tokens_received'] ?? 0));
            
            if (!empty($streamStatus['last_token'])) {
                $this->line("Last Token: " . substr($streamStatus['last_token'], 0, 50));
            }
            
            $progress = $this->calculateProgress($streamStatus);
            $this->line("Progress: " . number_format($progress, 1) . '%');
            
            // Show progress bar
            $this->displayProgressBar($progress);
            
            if (!empty($streamStatus['updated_at'])) {
                $this->line("Last Update: " . \Carbon\Carbon::parse($streamStatus['updated_at'])->diffForHumans());
            }
        } else {
            $this->warn("No streaming data available");
        }

        $this->newLine();
        $this->line("Press Ctrl+C to stop monitoring...");
    }

    private function displayProgressBar(float $progress): void
    {
        $width = 50;
        $filled = round($width * $progress / 100);
        $empty = $width - $filled;
        
        $bar = str_repeat('▓', $filled) . str_repeat('░', $empty);
        $this->line("Progress: [{$bar}] " . number_format($progress, 1) . '%');
    }

    private function calculateProgress(?array $streamStatus): float
    {
        if (!$streamStatus) return 0.0;
        
        $tokensReceived = $streamStatus['tokens_received'] ?? 0;
        $estimatedTotal = $streamStatus['estimated_total_tokens'] ?? 2000;
        
        return min(100, ($tokensReceived / $estimatedTotal) * 100);
    }

    private function displaySyncResults(string $jobId, string $response, int $durationMs, OpenAiStreamService $streamService): void
    {
        $streamStatus = $streamService->getStreamStatus($jobId);
        
        $this->newLine();
        $this->info('📊 Results Summary:');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $this->table(
            ['Metric', 'Value'],
            [
                ['Job ID', $jobId],
                ['Duration', number_format($durationMs) . 'ms'],
                ['Response Length', number_format(strlen($response)) . ' characters'],
                ['Tokens Received', number_format($streamStatus['tokens_received'] ?? 0)],
                ['Tokens/Second', number_format(($streamStatus['tokens_received'] ?? 0) / ($durationMs / 1000), 2)],
                ['Status', $streamStatus['status'] ?? 'unknown'],
            ]
        );

        $this->newLine();
        $this->info('📝 Response Preview:');
        $this->line(substr($response, 0, 500) . (strlen($response) > 500 ? '...' : ''));
    }

    private function displayFinalResults(OpenAiJobResult $jobResult): void
    {
        $summary = $jobResult->getResponseSummary();
        
        $this->table(
            ['Final Metrics', 'Value'],
            [
                ['Status', $summary['status']],
                ['Total Tokens', number_format($summary['total_tokens'])],
                ['Processing Time', $summary['processing_time_seconds'] . 's'],
                ['Tokens/Second', number_format($summary['tokens_per_second'], 2)],
                ['Estimated Cost', '$' . number_format($summary['estimated_cost_usd'], 4)],
                ['Success Rate', $summary['success_rate'] . '%'],
                ['Response Size', number_format($summary['response_size_bytes']) . ' bytes'],
            ]
        );
    }

    private function buildJobConfig(string $model, string $priority): array
    {
        return [
            'model' => $model,
            'max_tokens' => match($model) {
                'gpt-4' => 2000,
                'gpt-3.5-turbo' => 1500,
                default => 2000
            },
            'temperature' => 0.7,
            'priority' => $priority,
            'system_prompt' => $this->getSystemPrompt('security_analysis')
        ];
    }

    private function getTestPrompt(string $type): string
    {
        return match($type) {
            'security_analysis' => $this->getSecurityAnalysisPrompt(),
            'sentiment_analysis' => $this->getSentimentAnalysisPrompt(),
            default => $this->getGenericPrompt()
        };
    }

    private function getSecurityAnalysisPrompt(): string
    {
        return <<<PROMPT
Analyze this Solidity smart contract for security vulnerabilities and provide findings in JSON format:

```solidity
pragma solidity ^0.8.0;

contract VulnerableBank {
    mapping(address => uint256) public balances;
    
    function deposit() public payable {
        balances[msg.sender] += msg.value;
    }
    
    function withdraw(uint256 amount) public {
        require(balances[msg.sender] >= amount, "Insufficient balance");
        
        // Vulnerable: external call before state update
        (bool success, ) = msg.sender.call{value: amount}("");
        require(success, "Transfer failed");
        
        balances[msg.sender] -= amount;
    }
    
    function getBalance() public view returns (uint256) {
        return balances[msg.sender];
    }
}
```

Please identify security vulnerabilities using the OWASP-style schema format.
PROMPT;
    }

    private function getSentimentAnalysisPrompt(): string
    {
        return <<<PROMPT
Analyze the sentiment of the following social media posts about cryptocurrency:

1. "Bitcoin is going to the moon! Best investment ever! 🚀🚀🚀"
2. "Crypto is crashing again... lost so much money 😢"
3. "Ethereum's new update looks promising for developers"
4. "DeFi protocols are getting hacked left and right, be careful"
5. "Blockchain technology will revolutionize finance"

Provide sentiment analysis with scores from -1 (very negative) to +1 (very positive).
PROMPT;
    }

    private function getGenericPrompt(): string
    {
        return "Analyze the following code for potential issues and provide recommendations for improvement.";
    }

    private function getSystemPrompt(string $type): string
    {
        return match($type) {
            'security_analysis' => 'You are an expert blockchain security auditor. Analyze smart contracts for vulnerabilities and provide detailed findings with recommendations.',
            'sentiment_analysis' => 'You are an expert sentiment analyst. Analyze text for emotional tone and provide numerical sentiment scores.',
            default => 'You are an expert code analyst. Provide detailed analysis and recommendations.'
        };
    }

    private function getQueueName(string $type, string $priority): string
    {
        $base = "openai-{$type}";
        
        return match($priority) {
            'urgent' => "{$base}-urgent",
            'high' => "{$base}-high",
            'low' => "{$base}-low",
            default => $base
        };
    }

    private function estimateCost(int $maxTokens, string $model): string
    {
        $costPerToken = match($model) {
            'gpt-4' => 0.00003,
            'gpt-3.5-turbo' => 0.000002,
            default => 0.00003
        };

        $estimatedCost = $maxTokens * $costPerToken;
        return '$' . number_format($estimatedCost, 4);
    }

    private function colorizeStatus(string $status): string
    {
        return match($status) {
            'completed' => "<info>{$status}</info>",
            'processing' => "<comment>{$status}</comment>",
            'failed' => "<error>{$status}</error>",
            default => $status
        };
    }
}