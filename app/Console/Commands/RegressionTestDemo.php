<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class RegressionTestDemo extends Command
{
    protected $signature = 'regression:demo
                           {--contracts=3 : Number of contracts to test}
                           {--show-results : Display detailed results}';

    protected $description = 'Demo the regression test suite without database dependencies';

    public function handle(): int
    {
        $this->displayHeader();
        
        $contractCount = (int) $this->option('contracts');
        $this->runDemo($contractCount);
        
        if ($this->option('show-results')) {
            $this->showSampleResults();
        }

        return Command::SUCCESS;
    }

    private function displayHeader(): void
    {
        $this->info('🎭 REGRESSION TEST DEMO');
        $this->info('Demonstration of vulnerability detection capabilities');
        $this->newLine();
    }

    private function runDemo(int $contractCount): void
    {
        $contracts = $this->getSampleContracts($contractCount);
        
        $this->info("🔍 Testing {$contractCount} vulnerable contracts:");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($contractCount);
        $progressBar->setFormat('🧪 Analyzing: %current%/%max% [%bar%] %percent:3s%% - %message%');
        $progressBar->start();

        $results = [];
        
        foreach ($contracts as $i => $contract) {
            $progressBar->setMessage("Testing {$contract['name']}");
            
            // Simulate analysis time
            usleep(500000); // 0.5 seconds
            
            $result = $this->simulateAnalysis($contract);
            $results[] = $result;
            
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->displayResults($results);
    }

    private function getSampleContracts(int $count): array
    {
        $allContracts = [
            [
                'name' => 'ReentrancyAttack',
                'severity' => 'critical',
                'category' => 'Reentrancy',
                'description' => 'External call before state update'
            ],
            [
                'name' => 'IntegerOverflow', 
                'severity' => 'high',
                'category' => 'Arithmetic',
                'description' => 'Unchecked arithmetic operations'
            ],
            [
                'name' => 'AccessControl',
                'severity' => 'critical', 
                'category' => 'Access Control',
                'description' => 'Missing access controls'
            ],
            [
                'name' => 'UnprotectedSelfDestruct',
                'severity' => 'critical',
                'category' => 'Self-Destruct', 
                'description' => 'Unprotected selfdestruct function'
            ],
            [
                'name' => 'WeakRandomness',
                'severity' => 'high',
                'category' => 'Randomness',
                'description' => 'Block-based randomness'
            ],
            [
                'name' => 'UncheckedExternalCall',
                'severity' => 'high',
                'category' => 'External Calls',
                'description' => 'Unchecked return values'
            ],
            [
                'name' => 'FrontRunning', 
                'severity' => 'medium',
                'category' => 'MEV/Front-Running',
                'description' => 'Price manipulation vulnerability'
            ],
            [
                'name' => 'DenialOfService',
                'severity' => 'high',
                'category' => 'Denial of Service',
                'description' => 'Unbounded loops'
            ],
            [
                'name' => 'TimestampDependence',
                'severity' => 'medium', 
                'category' => 'Timestamp Dependence',
                'description' => 'Time-based logic manipulation'
            ],
            [
                'name' => 'FlashLoanAttack',
                'severity' => 'critical',
                'category' => 'Flash Loan Attack',
                'description' => 'Oracle manipulation'
            ]
        ];

        return array_slice($allContracts, 0, min($count, count($allContracts)));
    }

    private function simulateAnalysis(array $contract): array
    {
        $severityScores = [
            'critical' => rand(75, 95),
            'high' => rand(55, 80), 
            'medium' => rand(30, 60),
            'low' => rand(15, 35)
        ];

        $findingsCounts = [
            'critical' => rand(2, 5),
            'high' => rand(1, 4),
            'medium' => rand(1, 3), 
            'low' => rand(1, 2)
        ];

        $riskScore = $severityScores[$contract['severity']];
        $findings = $findingsCounts[$contract['severity']];
        $detected = $riskScore >= 25 && $findings >= 1;

        return [
            'contract_name' => $contract['name'],
            'severity' => $contract['severity'],
            'category' => $contract['category'],
            'description' => $contract['description'],
            'detected' => $detected,
            'risk_score' => $riskScore,
            'findings_count' => $findings,
            'processing_time_ms' => rand(1500, 6000),
            'tokens_used' => rand(400, 1200),
            'confidence' => rand(75, 95),
        ];
    }

    private function displayResults(array $results): void
    {
        $this->info('📊 DEMO RESULTS');
        $this->newLine();

        $tableData = [];
        $detected = 0;
        $totalRisk = 0;
        $totalFindings = 0;

        foreach ($results as $result) {
            $status = $result['detected'] ? '✅ DETECTED' : '❌ MISSED';
            $severity = strtoupper($result['severity']);
            
            $tableData[] = [
                $result['contract_name'],
                $severity,
                $status,
                $result['risk_score'] . '%',
                $result['findings_count'],
                round($result['processing_time_ms'] / 1000, 1) . 's'
            ];

            if ($result['detected']) $detected++;
            $totalRisk += $result['risk_score'];
            $totalFindings += $result['findings_count'];
        }

        $this->table(
            ['Contract', 'Severity', 'Status', 'Risk', 'Findings', 'Time'],
            $tableData
        );

        $this->newLine();
        
        // Summary metrics
        $detectionRate = ($detected / count($results)) * 100;
        $avgRisk = $totalRisk / count($results);
        
        $this->info('📈 SUMMARY METRICS');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Detection Rate', round($detectionRate, 1) . "% ({$detected}/" . count($results) . ")"],
                ['Average Risk Score', round($avgRisk, 1) . '%'],
                ['Total Findings', $totalFindings],
                ['Average Processing Time', round(array_sum(array_column($results, 'processing_time_ms')) / count($results) / 1000, 2) . 's'],
                ['Total Tokens Used', number_format(array_sum(array_column($results, 'tokens_used')))],
            ]
        );

        $this->newLine();
        
        if ($detectionRate >= 80) {
            $this->info('🎉 Demo Results: EXCELLENT detection capability!');
        } elseif ($detectionRate >= 60) {
            $this->info('👍 Demo Results: GOOD detection capability');
        } else {
            $this->warn('⚠️  Demo Results: Detection capability needs improvement');
        }
    }

    private function showSampleResults(): void
    {
        $this->newLine();
        $this->info('🔍 SAMPLE VULNERABILITY FINDING');
        $this->newLine();

        $sampleFinding = [
            'ID' => 'VULN-RE001',
            'Severity' => 'CRITICAL',
            'Title' => 'Reentrancy vulnerability in withdraw function',
            'Category' => 'SWC-107 (Reentrancy)',
            'Line' => '45-52',
            'Function' => 'withdraw()',
            'Description' => 'External call executed before state update allows recursive calls',
            'Impact' => 'Complete fund drainage possible',
            'Confidence' => 'HIGH (95%)',
            'Recommendation' => 'Implement checks-effects-interactions pattern'
        ];

        $this->table(['Property', 'Value'], collect($sampleFinding)->map(fn($v, $k) => [$k, $v])->values());

        $this->newLine();
        $this->info('💡 This demonstrates the type of structured findings the system produces');
        $this->info('📋 The actual test suite covers 10 different vulnerability categories');
    }
}