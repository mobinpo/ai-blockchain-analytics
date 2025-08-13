<?php

namespace App\Console\Commands;

use App\Jobs\StreamingAnalysisJob;
use App\Models\Analysis;
use App\Services\OpenAiStreamService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

class TestStreamingAnalysis extends Command
{
    protected $signature = 'test:streaming-analysis 
                          {--contract= : Contract address to analyze}
                          {--network=ethereum : Network (ethereum, bsc, polygon, etc.)}
                          {--model=gpt-4 : OpenAI model to use}
                          {--max-tokens=2000 : Maximum tokens}
                          {--temperature=0.7 : Temperature setting}
                          {--type=security : Analysis type}
                          {--sync : Run synchronously instead of queuing}
                          {--status-check : Check status of existing analysis}
                          {--analysis-id= : Analysis ID to check status for}';

    protected $description = 'Test the streaming analysis functionality with OpenAI and Horizon';

    // Test contract addresses known to be verified
    private const TEST_CONTRACTS = [
        'ethereum' => '0xA0b86a33E6417c54bE6f6F91D6B20b5e5C82D6b1',
        'bsc' => '0x55d398326f99059fF775485246999027B3197955',
        'polygon' => '0x2791Bca1f2de4661ED88A30C99A7a9449Aa84174',
    ];

    public function handle(): int
    {
        $this->info('🧪 OpenAI Streaming Analysis Test');
        $this->newLine();

        try {
            // Check if we're just checking status
            if ($this->option('status-check')) {
                return $this->checkAnalysisStatus();
            }

            // Get contract address
            $contractAddress = $this->getContractAddress();
            $network = $this->option('network');

            $this->info("📊 Testing streaming analysis for:");
            $this->line("  Contract: {$contractAddress}");
            $this->line("  Network: {$network}");
            $this->line("  Model: {$this->option('model')}");
            $this->line("  Type: {$this->option('type')}");
            $this->newLine();

            // Create analysis record
            $analysis = $this->createAnalysisRecord($contractAddress, $network);
            
            $this->info("✅ Created analysis record: {$analysis->id}");

            // Prepare analysis config
            $analysisConfig = [
                'type' => $this->option('type'),
                'model' => $this->option('model'),
                'max_tokens' => (int) $this->option('max-tokens'),
                'temperature' => (float) $this->option('temperature')
            ];

            if ($this->option('sync')) {
                return $this->runSynchronously($analysis, $analysisConfig);
            } else {
                return $this->runAsynchronously($analysis, $analysisConfig);
            }

        } catch (\Exception $e) {
            $this->error("❌ Test failed: {$e->getMessage()}");
            if ($this->option('verbose')) {
                $this->line($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }

    private function getContractAddress(): string
    {
        if ($contractAddress = $this->option('contract')) {
            // Validate format
            if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $contractAddress)) {
                throw new \InvalidArgumentException('Invalid contract address format');
            }
            return Str::lower($contractAddress);
        }

        // Use test contract for the network
        $network = $this->option('network');
        if (!isset(self::TEST_CONTRACTS[$network])) {
            throw new \InvalidArgumentException("No test contract available for network: {$network}");
        }

        return self::TEST_CONTRACTS[$network];
    }

    private function createAnalysisRecord(string $contractAddress, string $network): Analysis
    {
        return Analysis::create([
            'contract_address' => $contractAddress,
            'network' => $network,
            'analysis_type' => $this->option('type'),
            'status' => 'queued',
            'parameters' => [
                'model' => $this->option('model'),
                'max_tokens' => (int) $this->option('max-tokens'),
                'temperature' => (float) $this->option('temperature'),
                'streaming_enabled' => true
            ],
            'metadata' => [
                'test_run' => true,
                'created_via_cli' => true,
                'queued_at' => now()->toISOString()
            ]
        ]);
    }

    private function runSynchronously(Analysis $analysis, array $analysisConfig): int
    {
        $this->info("🔄 Running analysis synchronously...");
        
        // Create and run job directly
        $job = new StreamingAnalysisJob(
            $analysis->contract_address,
            $analysis->network,
            $analysis->id,
            $analysisConfig
        );

        $streamService = app(OpenAiStreamService::class);
        $sourceService = app(\App\Services\SourceCodeFetchingService::class);
        $validator = app(\App\Services\SecurityFindingValidator::class);

        $startTime = microtime(true);
        
        try {
            $job->handle($streamService, $sourceService, $validator);
            $duration = round((microtime(true) - $startTime) * 1000);
            
            $this->info("✅ Analysis completed successfully in {$duration}ms");
            
            // Display results
            $analysis->refresh();
            $this->displayResults($analysis);
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000);
            $this->error("❌ Analysis failed after {$duration}ms: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function runAsynchronously(Analysis $analysis, array $analysisConfig): int
    {
        $this->info("📋 Queuing analysis job with Horizon...");
        
        // Create and dispatch job
        $job = new StreamingAnalysisJob(
            $analysis->contract_address,
            $analysis->network,
            $analysis->id,
            $analysisConfig
        );

        $job->onQueue('streaming-analysis');
        
        Queue::push($job);
        
        $this->info("✅ Job queued successfully!");
        $this->newLine();
        
        $this->info("📊 Monitor the job:");
        $this->line("  Analysis ID: {$analysis->id}");
        $this->line("  Status check: php artisan test:streaming-analysis --status-check --analysis-id={$analysis->id}");
        $this->line("  Horizon Dashboard: " . url('/horizon'));
        $this->newLine();
        
        // Ask if user wants to wait and monitor
        if ($this->confirm('Do you want to monitor the job progress?')) {
            return $this->monitorJobProgress($analysis->id);
        }
        
        return Command::SUCCESS;
    }

    private function checkAnalysisStatus(): int
    {
        $analysisId = $this->option('analysis-id');
        
        if (!$analysisId) {
            $this->error("❌ Please provide --analysis-id when using --status-check");
            return Command::FAILURE;
        }

        try {
            $analysis = Analysis::findOrFail($analysisId);
            
            $this->info("📊 Analysis Status Report");
            $this->newLine();
            
            $this->table(
                ['Property', 'Value'],
                [
                    ['ID', $analysis->id],
                    ['Contract', $analysis->contract_address],
                    ['Network', $analysis->network],
                    ['Type', $analysis->analysis_type],
                    ['Status', $analysis->status],
                    ['Created', $analysis->created_at->format('Y-m-d H:i:s')],
                    ['Started', $analysis->started_at?->format('Y-m-d H:i:s') ?? 'Not started'],
                    ['Completed', $analysis->completed_at?->format('Y-m-d H:i:s') ?? 'Not completed'],
                ]
            );

            // Check streaming status if available
            if ($analysis->job_id) {
                $streamService = app(OpenAiStreamService::class);
                $streamStatus = $streamService->getStreamStatus($analysis->job_id);
                
                if ($streamStatus) {
                    $this->newLine();
                    $this->info("🔄 Streaming Status:");
                    $this->table(
                        ['Property', 'Value'],
                        [
                            ['Status', $streamStatus['status'] ?? 'Unknown'],
                            ['Tokens Received', $streamStatus['tokens_received'] ?? 0],
                            ['Content Length', strlen($streamStatus['content'] ?? '')],
                            ['Processing Time', ($streamStatus['processing_time_ms'] ?? 0) . 'ms'],
                            ['Last Updated', $streamStatus['updated_at'] ?? 'Never']
                        ]
                    );
                }
            }

            // Show results if completed
            if ($analysis->status === 'completed' && $analysis->result) {
                $this->displayResults($analysis);
            }

            // Show error if failed
            if ($analysis->status === 'failed') {
                $this->newLine();
                $this->error("❌ Analysis failed: " . ($analysis->error_message ?? 'Unknown error'));
            }

            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("❌ Failed to get analysis status: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function monitorJobProgress(int $analysisId): int
    {
        $this->info("👀 Monitoring job progress... (Press Ctrl+C to stop)");
        $this->newLine();
        
        $lastStatus = null;
        $startTime = time();
        
        while (true) {
            try {
                $analysis = Analysis::find($analysisId);
                
                if (!$analysis) {
                    $this->error("❌ Analysis record not found");
                    return Command::FAILURE;
                }
                
                if ($analysis->status !== $lastStatus) {
                    $elapsed = time() - $startTime;
                    $this->line("[{$elapsed}s] Status: {$analysis->status}");
                    $lastStatus = $analysis->status;
                }
                
                // Check streaming progress
                if ($analysis->job_id) {
                    $streamService = app(OpenAiStreamService::class);
                    $streamStatus = $streamService->getStreamStatus($analysis->job_id);
                    
                    if ($streamStatus && isset($streamStatus['tokens_received'])) {
                        $tokens = $streamStatus['tokens_received'];
                        $this->line("  └─ Tokens: {$tokens}");
                    }
                }
                
                // Check if completed or failed
                if (in_array($analysis->status, ['completed', 'failed', 'cancelled'])) {
                    $this->newLine();
                    
                    if ($analysis->status === 'completed') {
                        $this->info("✅ Analysis completed!");
                        $this->displayResults($analysis);
                    } else {
                        $this->error("❌ Analysis {$analysis->status}: " . ($analysis->error_message ?? ''));
                    }
                    
                    return $analysis->status === 'completed' ? Command::SUCCESS : Command::FAILURE;
                }
                
                sleep(2); // Wait 2 seconds before next check
                
            } catch (\Exception $e) {
                $this->error("❌ Monitoring error: {$e->getMessage()}");
                return Command::FAILURE;
            }
        }
    }

    private function displayResults(Analysis $analysis): void
    {
        if (!$analysis->result) {
            return;
        }

        $result = $analysis->result;
        
        $this->newLine();
        $this->info("📋 Analysis Results:");
        
        // Summary table
        $summary = $result['summary'] ?? [];
        $findingsCount = $summary['total_findings'] ?? 0;
        $severityBreakdown = $summary['severity_breakdown'] ?? [];
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Findings', $findingsCount],
                ['Critical', $severityBreakdown['CRITICAL'] ?? 0],
                ['High', $severityBreakdown['HIGH'] ?? 0],
                ['Medium', $severityBreakdown['MEDIUM'] ?? 0],
                ['Low', $severityBreakdown['LOW'] ?? 0],
                ['Info', $severityBreakdown['INFO'] ?? 0],
            ]
        );

        // Show first few findings
        $findings = $result['findings'] ?? [];
        if (!empty($findings)) {
            $this->newLine();
            $this->info("🔍 Sample Findings:");
            
            $sampleFindings = array_slice($findings, 0, 3);
            foreach ($sampleFindings as $index => $finding) {
                $this->line("  " . ($index + 1) . ". [{$finding['severity']}] {$finding['title']}");
                $this->line("     " . Str::limit($finding['description'], 80));
            }
            
            if (count($findings) > 3) {
                $this->line("  ... and " . (count($findings) - 3) . " more findings");
            }
        }

        // Validation info
        if (isset($summary['validation_summary'])) {
            $validation = $summary['validation_summary'];
            $this->newLine();
            $this->info("✅ Validation Summary:");
            $this->line("  Success Rate: {$validation['success_rate']}%");
            $this->line("  Valid: {$validation['valid']} | Invalid: {$validation['invalid']}");
        }
    }
}