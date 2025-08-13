<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\OptimizedOpenAiStreamingJob;
use App\Models\OpenAiJobResult;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * Test Command for OpenAI Streaming Job Worker
 * 
 * This command validates the complete OpenAI streaming workflow:
 * - Job creation and queueing
 * - Horizon worker processing
 * - Token streaming and real-time updates
 * - Result storage and analytics
 */
class TestOpenAiStreamingCommand extends Command
{
    protected $signature = 'openai:test-streaming 
                            {--type=security_analysis : Job type to test}
                            {--priority=normal : Job priority}
                            {--user-id= : User ID for testing (optional)}
                            {--monitor : Monitor job progress in real-time}
                            {--timeout=300 : Timeout for monitoring in seconds}';

    protected $description = 'Test OpenAI streaming job worker with comprehensive validation';

    public function handle(): int
    {
        $this->info('🚀 Testing OpenAI Streaming Job Worker');
        $this->newLine();

        try {
            // Step 1: Validate prerequisites
            $this->validatePrerequisites();
            
            // Step 2: Create test job
            $jobId = $this->createTestJob();
            
            // Step 3: Monitor job progress if requested
            if ($this->option('monitor')) {
                $this->monitorJob($jobId);
            }
            
            // Step 4: Validate results
            $this->validateResults($jobId);
            
            $this->newLine();
            $this->info('✅ OpenAI streaming test completed successfully!');
            return self::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("❌ Test failed: {$e->getMessage()}");
            $this->line($e->getTraceAsString());
            return self::FAILURE;
        }
    }

    /**
     * Validate system prerequisites
     */
    private function validatePrerequisites(): void
    {
        $this->info('🔍 Validating prerequisites...');
        
        // Check Redis connection
        try {
            Redis::ping();
            $this->line('   ✅ Redis connection: OK');
        } catch (\Exception $e) {
            throw new \Exception("Redis connection failed: {$e->getMessage()}");
        }
        
        // Check Horizon status
        $horizonStatus = $this->checkHorizonStatus();
        if ($horizonStatus) {
            $this->line('   ✅ Horizon status: Running');
        } else {
            $this->warn('   ⚠️ Horizon status: Not running (jobs will be queued)');
        }
        
        // Check OpenAI configuration
        $openaiKey = config('services.openai.key');
        if ($openaiKey) {
            $this->line('   ✅ OpenAI API key: Configured');
        } else {
            $this->warn('   ⚠️ OpenAI API key: Not configured (will use simulation mode)');
        }
        
        // Check database connectivity
        try {
            \DB::connection()->getPdo();
            $this->line('   ✅ Database connection: OK');
        } catch (\Exception $e) {
            throw new \Exception("Database connection failed: {$e->getMessage()}");
        }
        
        $this->newLine();
    }

    /**
     * Create and dispatch test job
     */
    private function createTestJob(): string
    {
        $this->info('📝 Creating test streaming job...');
        
        $jobId = 'test_openai_' . Str::uuid();
        $jobType = $this->option('type');
        $priority = $this->option('priority');
        $userId = $this->option('user-id') ?? $this->getTestUser()->id;
        
        // Create comprehensive test prompt
        $prompt = $this->generateTestPrompt($jobType);
        
        // Test configuration
        $config = [
            'model' => 'gpt-4',
            'max_tokens' => 500, // Shorter for testing
            'temperature' => 0.7,
            'stream' => true,
            'test_mode' => true
        ];
        
        // Test metadata
        $metadata = [
            'test_execution' => true,
            'test_command' => 'openai:test-streaming',
            'test_timestamp' => now()->toISOString(),
            'test_parameters' => [
                'type' => $jobType,
                'priority' => $priority,
                'user_id' => $userId
            ]
        ];
        
        // Dispatch the job
        OptimizedOpenAiStreamingJob::dispatch(
            prompt: $prompt,
            jobId: $jobId,
            config: $config,
            metadata: $metadata,
            jobType: $jobType,
            userId: $userId,
            analysisId: null,
            priority: $priority
        );
        
        $this->line("   ✅ Job created with ID: {$jobId}");
        $this->line("   📊 Job type: {$jobType}");
        $this->line("   ⚡ Priority: {$priority}");
        $this->line("   👤 User ID: {$userId}");
        $this->line("   📝 Prompt length: " . strlen($prompt) . " characters");
        
        $this->newLine();
        return $jobId;
    }

    /**
     * Monitor job progress in real-time
     */
    private function monitorJob(string $jobId): void
    {
        $this->info('👀 Monitoring job progress...');
        $timeout = (int) $this->option('timeout');
        $startTime = time();
        $lastStatus = '';
        $lastProgress = -1;
        
        while ((time() - $startTime) < $timeout) {
            // Get job status from database
            $jobResult = OpenAiJobResult::where('job_id', $jobId)->first();
            
            if (!$jobResult) {
                sleep(2);
                continue;
            }
            
            // Get streaming state from Redis
            $streamingState = $this->getStreamingState($jobId);
            
            // Display status updates
            if ($jobResult->status !== $lastStatus) {
                $this->line("   📊 Status: {$jobResult->status}");
                $lastStatus = $jobResult->status;
            }
            
            // Display progress updates
            if ($streamingState) {
                $progress = $streamingState['progress_percentage'] ?? 0;
                $tokens = $streamingState['tokens_streamed'] ?? 0;
                
                if ($progress !== $lastProgress) {
                    $this->line("   ⚡ Progress: {$progress}% ({$tokens} tokens)");
                    $lastProgress = $progress;
                }
            }
            
            // Check if job is completed
            if ($jobResult->isCompleted()) {
                $this->line('   ✅ Job completed successfully!');
                break;
            }
            
            if ($jobResult->hasFailed()) {
                $this->line('   ❌ Job failed!');
                $this->line("   Error: {$jobResult->error_message}");
                break;
            }
            
            sleep(1);
        }
        
        if ((time() - $startTime) >= $timeout) {
            $this->warn('   ⏰ Monitoring timeout reached');
        }
        
        $this->newLine();
    }

    /**
     * Validate job results
     */
    private function validateResults(string $jobId): void
    {
        $this->info('🔬 Validating results...');
        
        // Wait a moment for final updates
        sleep(2);
        
        $jobResult = OpenAiJobResult::where('job_id', $jobId)->first();
        
        if (!$jobResult) {
            throw new \Exception('Job result not found in database');
        }
        
        // Validate basic job data
        $this->line("   📊 Job Status: {$jobResult->status}");
        $this->line("   ⏱️ Processing Time: {$jobResult->getProcessingDurationSeconds()}s");
        
        if ($jobResult->isCompleted()) {
            // Validate completed job
            $this->validateCompletedJob($jobResult);
        } elseif ($jobResult->hasFailed()) {
            // Show failure details
            $this->line("   ❌ Failure Reason: {$jobResult->error_message}");
            $this->line("   🔄 Attempts Made: {$jobResult->attempts_made}");
        } else {
            $this->line("   ⏳ Job Status: Still processing");
        }
        
        // Validate streaming metrics
        $this->validateStreamingMetrics($jobResult);
        
        // Validate database integrity
        $this->validateDatabaseIntegrity($jobResult);
    }

    /**
     * Validate completed job results
     */
    private function validateCompletedJob(OpenAiJobResult $jobResult): void
    {
        // Check response content
        if (empty($jobResult->response)) {
            throw new \Exception('Job completed but response is empty');
        }
        
        $this->line("   📝 Response Length: " . strlen($jobResult->response) . " characters");
        
        // Check token usage
        $tokenUsage = $jobResult->token_usage ?? [];
        if (!empty($tokenUsage)) {
            $this->line("   🎯 Total Tokens: " . ($tokenUsage['total_tokens'] ?? 'N/A'));
            $this->line("   💰 Estimated Cost: $" . ($tokenUsage['estimated_cost_usd'] ?? 'N/A'));
            $this->line("   ⚡ Tokens/Second: " . ($tokenUsage['tokens_per_second'] ?? 'N/A'));
        }
        
        // Check streaming stats
        $streamingStats = $jobResult->streaming_stats ?? [];
        if (!empty($streamingStats)) {
            $this->line("   📊 Tokens Streamed: " . ($streamingStats['tokens_streamed'] ?? 'N/A'));
            $this->line("   📏 Response Size: " . ($streamingStats['total_response_size'] ?? 'N/A') . " bytes");
        }
        
        // Validate parsed response for security analysis
        if ($jobResult->job_type === 'security_analysis' && $jobResult->parsed_response) {
            $this->line("   🔍 Parsed Response: Available");
            $parsedData = $jobResult->parsed_response;
            if (isset($parsedData['findings'])) {
                $this->line("   🔎 Findings Count: " . count($parsedData['findings']));
            }
        }
    }

    /**
     * Validate streaming metrics
     */
    private function validateStreamingMetrics(OpenAiJobResult $jobResult): void
    {
        $metrics = $jobResult->getStreamingMetrics();
        
        if (!empty($metrics)) {
            $this->line("   📈 Streaming Metrics:");
            $this->line("      • Tokens Received: " . ($metrics['tokens_received'] ?? 'N/A'));
            $this->line("      • Streaming Efficiency: " . round(($metrics['streaming_efficiency'] ?? 0) * 100, 1) . "%");
            $this->line("      • First Token Latency: " . ($metrics['first_token_latency_ms'] ?? 'N/A') . "ms");
            $this->line("      • Avg Token Interval: " . ($metrics['average_token_interval_ms'] ?? 'N/A') . "ms");
        }
    }

    /**
     * Validate database integrity
     */
    private function validateDatabaseIntegrity(OpenAiJobResult $jobResult): void
    {
        // Check required fields
        $requiredFields = ['job_id', 'job_type', 'status', 'created_at'];
        foreach ($requiredFields as $field) {
            if (empty($jobResult->$field)) {
                throw new \Exception("Required field '{$field}' is missing or empty");
            }
        }
        
        // Check timestamps
        if ($jobResult->created_at && $jobResult->started_at) {
            if ($jobResult->started_at < $jobResult->created_at) {
                throw new \Exception('Invalid timestamps: started_at is before created_at');
            }
        }
        
        if ($jobResult->completed_at && $jobResult->started_at) {
            if ($jobResult->completed_at < $jobResult->started_at) {
                throw new \Exception('Invalid timestamps: completed_at is before started_at');
            }
        }
        
        $this->line("   ✅ Database integrity: Valid");
    }

    /**
     * Generate test prompt based on job type
     */
    private function generateTestPrompt(string $jobType): string
    {
        return match($jobType) {
            'security_analysis' => 'Analyze this smart contract for security vulnerabilities:\n\n```solidity\npragma solidity ^0.8.0;\n\ncontract SimpleStorage {\n    uint256 private value;\n    \n    function setValue(uint256 _value) public {\n        value = _value;\n    }\n    \n    function getValue() public view returns (uint256) {\n        return value;\n    }\n}\n```\n\nProvide a comprehensive security analysis including potential vulnerabilities, recommendations, and risk assessment.',
            
            'gas_analysis' => 'Analyze the following smart contract for gas optimization opportunities:\n\n```solidity\npragma solidity ^0.8.0;\n\ncontract GasTest {\n    mapping(address => uint256) public balances;\n    address[] public users;\n    \n    function addUser(address user, uint256 amount) public {\n        balances[user] = amount;\n        users.push(user);\n    }\n    \n    function getTotalUsers() public view returns (uint256) {\n        return users.length;\n    }\n}\n```\n\nIdentify gas optimization opportunities and provide specific recommendations.',
            
            'quality_analysis' => 'Review this smart contract code for quality, best practices, and maintainability:\n\n```solidity\npragma solidity ^0.8.0;\n\ncontract QualityTest {\n    uint public count;\n    \n    function increment() public {\n        count++;\n    }\n    \n    function getCount() public view returns (uint) {\n        return count;\n    }\n}\n```\n\nProvide feedback on code quality, documentation, and adherence to best practices.',
            
            'sentiment_analysis' => 'Analyze the sentiment of the following text about a cryptocurrency project:\n\n"This new DeFi protocol has revolutionized yield farming with innovative staking mechanisms. The tokenomics are well-designed and the team has delivered on all roadmap promises. However, some users have reported minor UI issues and the gas fees can be high during peak usage. Overall, it\'s a promising project with strong fundamentals."\n\nProvide sentiment analysis including overall sentiment score, key themes, and detailed breakdown.',
            
            default => 'This is a test prompt for OpenAI streaming job validation. Please provide a detailed response that demonstrates the streaming capabilities and token processing functionality.'
        };
    }

    /**
     * Get or create test user
     */
    private function getTestUser(): User
    {
        return User::firstOrCreate(
            ['email' => 'test-streaming@blockchain-analytics.com'],
            [
                'name' => 'OpenAI Streaming Test User',
                'password' => bcrypt('test-password'),
                'email_verified_at' => now()
            ]
        );
    }

    /**
     * Check if Horizon is running
     */
    private function checkHorizonStatus(): bool
    {
        try {
            $output = shell_exec('ps aux | grep horizon');
            return $output && str_contains($output, 'horizon:work');
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get streaming state from Redis
     */
    private function getStreamingState(string $jobId): ?array
    {
        try {
            $data = Redis::get("openai_stream_{$jobId}");
            return $data ? json_decode($data, true) : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
