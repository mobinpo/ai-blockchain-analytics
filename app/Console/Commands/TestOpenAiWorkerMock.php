<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\OpenAiStreamingJob;
use App\Models\OpenAiJobResult;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class TestOpenAiWorkerMock extends Command
{
    protected $signature = 'openai:test-worker-mock 
                           {--type=security_analysis : Job type to test}
                           {--async : Run job asynchronously}
                           {--monitor : Monitor job progress}';

    protected $description = 'Test OpenAI worker with mock streaming (no API key required)';

    public function handle(): int
    {
        $this->displayHeader();

        $type = $this->option('type');
        $async = $this->option('async');
        $monitor = $this->option('monitor');

        if ($monitor) {
            return $this->monitorJobs();
        }

        if ($async) {
            return $this->runAsynchronousTest($type);
        } else {
            return $this->runMockStreaming($type);
        }
    }

    private function displayHeader(): void
    {
        $this->info('🤖 OpenAI Worker Mock Test');
        $this->info('Testing job processing and streaming without API calls');
        $this->newLine();
    }

    private function runAsynchronousTest(string $type): int
    {
        $jobId = 'mock_test_' . Str::random(8);
        $prompt = $this->getTestPrompt($type);
        
        $this->info("🚀 Creating mock asynchronous job...");
        
        // Create job result record directly
        $jobResult = OpenAiJobResult::create([
            'job_id' => $jobId,
            'job_type' => $type,
            'status' => 'processing',
            'prompt' => $prompt,
            'config' => [
                'model' => 'gpt-4',
                'max_tokens' => 2000,
                'temperature' => 0.7,
                'priority' => 'normal'
            ],
            'metadata' => [
                'mock_test' => true,
                'created_via' => 'mock_command'
            ],
            'started_at' => now()
        ]);

        $this->displayJobInfo($jobId, $type);
        
        // Simulate streaming process
        $this->simulateStreaming($jobId, $jobResult);
        
        $this->info("✅ Mock job completed successfully!");
        $this->displayJobResults($jobResult->fresh());

        return Command::SUCCESS;
    }

    private function runMockStreaming(string $type): int
    {
        $jobId = 'sync_mock_' . Str::random(8);
        $prompt = $this->getTestPrompt($type);
        
        $this->info("⚡ Running mock synchronous streaming...");
        $this->displayJobInfo($jobId, $type);
        
        // Create job result
        $jobResult = OpenAiJobResult::create([
            'job_id' => $jobId,
            'job_type' => $type,
            'status' => 'processing',
            'prompt' => $prompt,
            'config' => ['model' => 'gpt-4', 'mock' => true],
            'started_at' => now()
        ]);

        // Simulate streaming
        $this->info("🔄 Starting mock streaming...");
        $this->newLine();
        
        $response = $this->simulateStreamingOutput($jobId);
        
        // Complete the job
        $jobResult->update([
            'status' => 'completed',
            'response' => $response,
            'parsed_response' => json_decode($response, true),
            'processing_time_ms' => 3500,
            'token_usage' => [
                'total_tokens' => 150,
                'prompt_tokens' => 50,
                'completion_tokens' => 100,
                'estimated_cost_usd' => 0.0045
            ],
            'completed_at' => now()
        ]);

        $this->newLine();
        $this->info("✅ Mock streaming completed!");
        $this->displayJobResults($jobResult);

        return Command::SUCCESS;
    }

    private function simulateStreaming(string $jobId, OpenAiJobResult $jobResult): void
    {
        $this->info("🔄 Simulating streaming progress...");
        
        $totalTokens = 150;
        $response = '';
        
        for ($i = 1; $i <= $totalTokens; $i++) {
            $token = $this->generateMockToken($i, $totalTokens);
            $response .= $token;
            
            // Update cache to simulate streaming
            Cache::put("openai_stream_{$jobId}", [
                'status' => 'streaming',
                'tokens_received' => $i,
                'content' => $response,
                'last_token' => $token,
                'updated_at' => now()->toISOString(),
                'estimated_total_tokens' => $totalTokens,
                'processing_time_ms' => $i * 20
            ], 3600);

            // Show progress every 10 tokens
            if ($i % 10 === 0) {
                $progress = round(($i / $totalTokens) * 100, 1);
                $this->line("Progress: {$progress}% ({$i}/{$totalTokens} tokens)");
            }
            
            usleep(50000); // 50ms delay to simulate real streaming
        }

        // Complete the job
        $finalResponse = $this->generateMockResponse();
        
        $jobResult->update([
            'status' => 'completed',
            'response' => $finalResponse,
            'parsed_response' => json_decode($finalResponse, true),
            'processing_time_ms' => $totalTokens * 20,
            'token_usage' => [
                'total_tokens' => $totalTokens,
                'prompt_tokens' => 50,
                'completion_tokens' => $totalTokens - 50,
                'estimated_cost_usd' => 0.0045
            ],
            'streaming_stats' => Cache::get("openai_stream_{$jobId}"),
            'completed_at' => now()
        ]);

        Cache::forget("openai_stream_{$jobId}");
    }

    private function simulateStreamingOutput(string $jobId): string
    {
        $response = '';
        $tokens = [
            '{"', 'findings', '": [', '{', '"severity":', '"HIGH",',
            '"title":', '"Re-entrancy in withdrawal function",',
            '"line":', '125,', '"recommendation":', '"Implement checks-effects-interactions pattern',
            ' and add ReentrancyGuard modifier to prevent recursive calls.',
            ' Move balance update before external call."',
            '}]}'
        ];

        foreach ($tokens as $i => $token) {
            $response .= $token;
            
            // Show streaming token
            $this->output->write($token);
            
            // Update cache
            Cache::put("openai_stream_{$jobId}", [
                'status' => 'streaming',
                'tokens_received' => $i + 1,
                'content' => $response,
                'last_token' => $token,
                'updated_at' => now()->toISOString()
            ], 3600);
            
            usleep(100000); // 100ms delay
        }

        $this->newLine();
        return $response;
    }

    private function monitorJobs(): int
    {
        $this->info("👀 Monitoring OpenAI jobs...");
        $this->newLine();

        $jobs = OpenAiJobResult::recent(6)->orderBy('created_at', 'desc')->take(10)->get();

        if ($jobs->isEmpty()) {
            $this->warn("No recent OpenAI jobs found.");
            return Command::SUCCESS;
        }

        $tableData = [];
        foreach ($jobs as $job) {
            $tableData[] = [
                'ID' => substr($job->job_id, 0, 15),
                'Type' => $job->job_type,
                'Status' => $this->colorizeStatus($job->status),
                'Tokens' => number_format($job->getTotalTokens()),
                'Duration' => $job->getProcessingDurationSeconds() . 's',
                'Cost' => '$' . number_format($job->getEstimatedCost(), 4),
                'Created' => $job->created_at->diffForHumans()
            ];
        }

        $this->table(
            ['ID', 'Type', 'Status', 'Tokens', 'Duration', 'Cost', 'Created'],
            $tableData
        );

        return Command::SUCCESS;
    }

    private function displayJobInfo(string $jobId, string $type): void
    {
        $this->table(
            ['Property', 'Value'],
            [
                ['Job ID', $jobId],
                ['Job Type', $type],
                ['Mode', 'Mock (No API calls)'],
                ['Model', 'gpt-4 (simulated)'],
                ['Estimated Tokens', '150'],
                ['Estimated Cost', '$0.0045']
            ]
        );
        $this->newLine();
    }

    private function displayJobResults(OpenAiJobResult $job): void
    {
        $this->newLine();
        $this->info('📊 Job Results:');
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Status', $this->colorizeStatus($job->status)],
                ['Total Tokens', number_format($job->getTotalTokens())],
                ['Processing Time', $job->getProcessingDurationSeconds() . 's'],
                ['Estimated Cost', '$' . number_format($job->getEstimatedCost(), 4)],
                ['Response Length', strlen($job->response ?? '') . ' characters'],
                ['Has Structured Data', $job->parsed_response ? 'Yes' : 'No']
            ]
        );

        if ($job->parsed_response) {
            $this->newLine();
            $this->info('📝 Parsed Response:');
            $this->line(json_encode($job->parsed_response, JSON_PRETTY_PRINT));
        }
    }

    private function generateMockToken(int $position, int $total): string
    {
        $tokens = [
            '{"findings":', '[', '{"severity":', '"HIGH",', '"title":', '"Re-entrancy",',
            '"line":', '125,', '"recommendation":', '"Use ReentrancyGuard"', '}', ']}'
        ];
        
        $index = min($position - 1, count($tokens) - 1);
        return $tokens[$index] ?? ' ';
    }

    private function generateMockResponse(): string
    {
        return json_encode([
            'findings' => [
                [
                    'severity' => 'HIGH',
                    'title' => 'Re-entrancy in withdrawal function',
                    'line' => 125,
                    'category' => 'Re-entrancy',
                    'recommendation' => 'Implement checks-effects-interactions pattern and add ReentrancyGuard modifier to prevent recursive calls.',
                    'description' => 'External call before state update enables classic re-entrancy attack vector',
                    'impact' => 'FUND_DRAINAGE',
                    'confidence' => 'HIGH'
                ],
                [
                    'severity' => 'MEDIUM',
                    'title' => 'Missing event emission',
                    'line' => 15,
                    'category' => 'Code Quality',
                    'recommendation' => 'Emit events for deposit operations to improve transparency',
                    'description' => 'Deposit function does not emit an event',
                    'impact' => 'MINIMAL',
                    'confidence' => 'HIGH'
                ]
            ],
            'summary' => [
                'total_findings' => 2,
                'critical' => 0,
                'high' => 1,
                'medium' => 1,
                'low' => 0
            ]
        ], JSON_PRETTY_PRINT);
    }

    private function getTestPrompt(string $type): string
    {
        return "Analyze this test smart contract for security vulnerabilities:\n\n```solidity\ncontract VulnerableBank {\n    mapping(address => uint256) balances;\n    function withdraw(uint256 amount) public {\n        require(balances[msg.sender] >= amount);\n        msg.sender.call{value: amount}(\"\");\n        balances[msg.sender] -= amount;\n    }\n}\n```";
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