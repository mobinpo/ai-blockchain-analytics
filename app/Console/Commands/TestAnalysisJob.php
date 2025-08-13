<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\AnalyzeContractJob;
use App\Models\ContractAnalysis;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TestAnalysisJob extends Command
{
    protected $signature = 'analysis:test 
                            {contract : Contract address to analyze}
                            {--network=ethereum : Network to use}
                            {--model=gpt-4 : OpenAI model to use}
                            {--sync : Run synchronously for testing}';

    protected $description = 'Test the contract analysis job system';

    public function handle(): int
    {
        $contractAddress = $this->argument('contract');
        $network = $this->option('network');
        $model = $this->option('model');
        
        $this->info("🚀 Testing Contract Analysis Job");
        $this->line("Contract: {$contractAddress}");
        $this->line("Network: {$network}");
        $this->line("Model: {$model}");
        $this->newLine();

        try {
            // Create analysis record
            $analysis = ContractAnalysis::create([
                'contract_address' => strtolower($contractAddress),
                'network' => strtolower($network),
                'model' => $model,
                'analysis_options' => [
                    'focus_areas' => ['reentrancy', 'access-control', 'integer-overflow'],
                    'severity_threshold' => 'LOW'
                ],
                'triggered_by' => 'cli',
                'user_id' => null
            ]);

            $this->info("✅ Analysis record created: {$analysis->id}");

            if ($this->option('sync')) {
                $this->line("🔄 Running analysis synchronously...");
                
                // Run job synchronously for testing
                $job = new AnalyzeContractJob(
                    $contractAddress,
                    $network,
                    $analysis->id,
                    $analysis->analysis_options
                );
                
                $job->handle(
                    app(\App\Services\OpenAiStreamService::class),
                    app(\App\Services\SecurityFindingValidator::class),
                    app(\App\Services\SourceCodeFetchingService::class),
                    app(\App\Services\SolidityCleanerService::class)
                );
                
                $analysis->refresh();
                $this->displayResults($analysis);
                
            } else {
                $this->line("📤 Dispatching to queue...");
                
                // Dispatch to queue
                AnalyzeContractJob::dispatch(
                    $contractAddress,
                    $network,
                    $analysis->id,
                    $analysis->analysis_options
                );
                
                $this->info("✅ Job dispatched to queue");
                $this->line("Monitor progress with: php artisan analysis:monitor {$analysis->id}");
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Test failed: {$e->getMessage()}");
            $this->line($e->getTraceAsString());
            return 1;
        }
    }

    private function displayResults(ContractAnalysis $analysis): void
    {
        $this->newLine();
        $this->info("📊 Analysis Results");
        
        $this->table(['Property', 'Value'], [
            ['Status', $analysis->status],
            ['Findings Count', $analysis->findings_count],
            ['Critical/High', count($analysis->getHighSeverityFindings())],
            ['Risk Score', $analysis->getRiskScore()],
            ['Processing Time', $analysis->getProcessingTimeFormatted()],
            ['Tokens Used', $analysis->tokens_used ?? 'N/A'],
            ['Duration', $analysis->getDuration() ? $analysis->getDuration() . 's' : 'N/A']
        ]);

        if ($analysis->findings && count($analysis->findings) > 0) {
            $this->newLine();
            $this->info("🔍 Security Findings");
            
            foreach ($analysis->findings as $index => $finding) {
                $severity = $finding['severity'] ?? 'UNKNOWN';
                $title = $finding['title'] ?? 'Untitled Finding';
                $line = $finding['location']['line'] ?? 'N/A';
                
                $severityColor = match($severity) {
                    'CRITICAL' => 'red',
                    'HIGH' => 'yellow',
                    'MEDIUM' => 'blue',
                    default => 'white'
                };
                
                $this->line("  " . ($index + 1) . ". <fg={$severityColor}>[{$severity}]</> {$title} (Line: {$line})");
            }
        }

        if ($analysis->status === 'failed') {
            $this->newLine();
            $this->error("❌ Analysis failed: " . ($analysis->error_message ?? 'Unknown error'));
        }
    }
}