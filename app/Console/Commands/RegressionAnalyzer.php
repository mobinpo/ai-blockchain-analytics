<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

final class RegressionAnalyzer extends Command
{
    protected $signature = 'regression:analyze
                           {--compare=2 : Compare last N test runs}
                           {--contract= : Analyze specific contract performance}
                           {--export=csv : Export format (csv|json|md)}
                           {--benchmark : Run benchmark against expected results}';

    protected $description = 'Analyze regression test results and performance patterns';

    public function handle(): int
    {
        $this->displayHeader();

        if ($contract = $this->option('contract')) {
            return $this->analyzeContract($contract);
        }

        if ($this->option('benchmark')) {
            return $this->runBenchmarkAnalysis();
        }

        return $this->runComparisonAnalysis();
    }

    private function displayHeader(): void
    {
        $this->info('🔬 REGRESSION TEST ANALYZER');
        $this->info('Deep analysis of vulnerability detection patterns');
        $this->newLine();
    }

    private function analyzeContract(string $contractName): int
    {
        $this->info("🔍 Analyzing Contract: {$contractName}");
        $this->newLine();

        $history = $this->getHistoricalData(30); // Last 30 days
        $contractData = [];

        foreach ($history as $entry) {
            $results = $entry['results'] ?? [];
            foreach ($results as $result) {
                if (stripos($result['contract_name'], $contractName) !== false) {
                    $contractData[] = [
                        'timestamp' => $entry['timestamp'],
                        'detected' => $result['detected'],
                        'risk_score' => $result['risk_score'],
                        'findings_count' => $result['findings_count'],
                        'processing_time_ms' => $result['processing_time_ms'] ?? 0,
                        'tokens_used' => $result['tokens_used'] ?? 0,
                    ];
                }
            }
        }

        if (empty($contractData)) {
            $this->error("❌ No data found for contract: {$contractName}");
            return Command::FAILURE;
        }

        $this->displayContractAnalysis($contractName, $contractData);
        return Command::SUCCESS;
    }

    private function runBenchmarkAnalysis(): int
    {
        $this->info('📊 BENCHMARK ANALYSIS');
        $this->info('Comparing against expected vulnerability detection baseline');
        $this->newLine();

        // Load expected results
        $expectedPath = base_path('tests/Fixtures/VulnerabilityExpectedResults.json');
        if (!File::exists($expectedPath)) {
            $this->error('❌ Expected results file not found');
            return Command::FAILURE;
        }

        $expected = json_decode(File::get($expectedPath), true);
        $expectedFindings = $expected['regression_test_suite']['test_metadata'];

        // Get latest test results
        $latest = $this->getLatestResults();
        if (!$latest) {
            $this->error('❌ No test results found. Run: php artisan regression:run');
            return Command::FAILURE;
        }

        $this->displayBenchmarkComparison($expectedFindings, $latest);
        return Command::SUCCESS;
    }

    private function runComparisonAnalysis(): int
    {
        $compareCount = (int) $this->option('compare');
        $this->info("📈 COMPARISON ANALYSIS (Last {$compareCount} runs)");
        $this->newLine();

        $history = $this->getHistoricalData(30);
        $recentRuns = array_slice($history, -$compareCount);

        if (count($recentRuns) < 2) {
            $this->error('❌ Need at least 2 test runs for comparison');
            return Command::FAILURE;
        }

        $this->displayComparisonResults($recentRuns);
        
        if ($this->option('export')) {
            $this->exportAnalysis($recentRuns);
        }

        return Command::SUCCESS;
    }

    private function displayContractAnalysis(string $contractName, array $data): void
    {
        $totalRuns = count($data);
        $detectedCount = count(array_filter($data, fn($d) => $d['detected']));
        $detectionRate = ($detectedCount / $totalRuns) * 100;

        $riskScores = array_column($data, 'risk_score');
        $findingsCounts = array_column($data, 'findings_count');
        $processingTimes = array_column($data, 'processing_time_ms');

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Test Runs', $totalRuns],
                ['Detection Rate', round($detectionRate, 1) . "% ({$detectedCount}/{$totalRuns})"],
                ['Average Risk Score', round(array_sum($riskScores) / count($riskScores), 1) . '%'],
                ['Risk Score Range', min($riskScores) . '% - ' . max($riskScores) . '%'],
                ['Average Findings', round(array_sum($findingsCounts) / count($findingsCounts), 1)],
                ['Average Processing Time', round(array_sum($processingTimes) / count($processingTimes) / 1000, 2) . 's'],
            ]
        );

        $this->newLine();

        // Recent trend
        $recent5 = array_slice($data, -5);
        $recentDetectionRate = (count(array_filter($recent5, fn($d) => $d['detected'])) / count($recent5)) * 100;
        
        $this->info('📈 Recent Trend (Last 5 runs):');
        $this->table(
            ['Date', 'Detected', 'Risk Score', 'Findings'],
            array_map(fn($d) => [
                Carbon::parse($d['timestamp'])->format('m-d H:i'),
                $d['detected'] ? '✅' : '❌',
                $d['risk_score'] . '%',
                $d['findings_count']
            ], $recent5)
        );

        $trend = $recentDetectionRate > $detectionRate ? '📈 Improving' : 
                ($recentDetectionRate < $detectionRate ? '📉 Declining' : '➡️ Stable');
        
        $this->line("Recent Performance: {$trend} ({$recentDetectionRate}%)");
    }

    private function displayBenchmarkComparison(array $expected, array $actual): void
    {
        $expectedTotal = $expected['total_expected_findings'];
        $expectedByType = $expected['severity_distribution'];
        
        $actualMetrics = $actual['metrics'];
        $actualTotal = $actualMetrics['total_findings'];
        $actualByType = [];
        
        // Calculate actual severity distribution
        foreach ($actual['results'] as $result) {
            $severity = ucfirst($result['severity']);
            $actualByType[$severity] = ($actualByType[$severity] ?? 0) + $result['findings_count'];
        }

        $this->info('🎯 FINDINGS COMPARISON');
        $this->table(
            ['Severity', 'Expected', 'Actual', 'Difference', 'Coverage'],
            array_map(fn($sev) => [
                $sev,
                $expectedByType[$sev] ?? 0,
                $actualByType[$sev] ?? 0,
                ($actualByType[$sev] ?? 0) - ($expectedByType[$sev] ?? 0),
                $expectedByType[$sev] > 0 ? 
                    round((($actualByType[$sev] ?? 0) / $expectedByType[$sev]) * 100, 1) . '%' : 
                    'N/A'
            ], ['Critical', 'High', 'Medium', 'Low'])
        );

        $this->newLine();
        
        $overallCoverage = $expectedTotal > 0 ? ($actualTotal / $expectedTotal) * 100 : 0;
        $this->info("📊 Overall Coverage: " . round($overallCoverage, 1) . "% ({$actualTotal}/{$expectedTotal})");
        
        if ($overallCoverage >= 80) {
            $this->info('✅ Benchmark PASSED - Good coverage');
        } elseif ($overallCoverage >= 60) {
            $this->warn('🟡 Benchmark MARGINAL - Acceptable coverage');
        } else {
            $this->error('❌ Benchmark FAILED - Poor coverage');
        }
    }

    private function displayComparisonResults(array $runs): void
    {
        $this->info('📊 RUN COMPARISON');
        $this->newLine();

        // Create comparison table
        $tableData = [];
        foreach ($runs as $i => $run) {
            $metrics = $run['metrics'];
            $timestamp = Carbon::parse($run['timestamp']);
            
            $tableData[] = [
                'Run #' . ($i + 1),
                $timestamp->format('m-d H:i'),
                round($metrics['detection_rate'], 1) . '%',
                round($metrics['average_risk_score'], 1) . '%',
                $metrics['total_findings'],
                $metrics['detected_count'] . '/' . $metrics['total_contracts']
            ];
        }

        $this->table(
            ['Run', 'Time', 'Detection Rate', 'Avg Risk', 'Findings', 'Passed'],
            $tableData
        );

        $this->newLine();

        // Calculate trends
        $detectionRates = array_column(array_column($runs, 'metrics'), 'detection_rate');
        $riskScores = array_column(array_column($runs, 'metrics'), 'average_risk_score');
        
        $detectionTrend = end($detectionRates) - $detectionRates[0];
        $riskTrend = end($riskScores) - $riskScores[0];

        $this->info('📈 TRENDS');
        $this->line("Detection Rate: " . ($detectionTrend >= 0 ? '+' : '') . round($detectionTrend, 1) . '%');
        $this->line("Risk Score: " . ($riskTrend >= 0 ? '+' : '') . round($riskTrend, 1) . '%');

        // Performance analysis
        if (isset($runs[0]['performance']) && isset(end($runs)['performance'])) {
            $firstPerf = $runs[0]['performance'];
            $lastPerf = end($runs)['performance'];
            
            $timeTrend = $lastPerf['average_processing_time_ms'] - $firstPerf['average_processing_time_ms'];
            $tokenTrend = $lastPerf['total_tokens_used'] - $firstPerf['total_tokens_used'];
            
            $this->line("Processing Time: " . ($timeTrend >= 0 ? '+' : '') . round($timeTrend, 0) . 'ms');
            $this->line("Token Usage: " . ($tokenTrend >= 0 ? '+' : '') . number_format($tokenTrend));
        }
    }

    private function exportAnalysis(array $data): void
    {
        $format = $this->option('export');
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "regression_analysis_{$timestamp}.{$format}";
        $path = storage_path("app/regression_analysis/{$filename}");
        
        // Ensure directory exists
        File::ensureDirectoryExists(dirname($path));

        match($format) {
            'csv' => $this->exportToCsv($data, $path),
            'json' => $this->exportToJson($data, $path),
            'md' => $this->exportToMarkdown($data, $path),
            default => $this->error("Unsupported export format: {$format}")
        };

        if (File::exists($path)) {
            $this->info("📄 Analysis exported to: {$path}");
        }
    }

    private function exportToCsv(array $data, string $path): void
    {
        $csv = "Timestamp,Detection Rate,Average Risk Score,Total Findings,Contracts Passed\n";
        
        foreach ($data as $run) {
            $metrics = $run['metrics'];
            $csv .= sprintf(
                "%s,%.1f,%.1f,%d,%d\n",
                $run['timestamp'],
                $metrics['detection_rate'],
                $metrics['average_risk_score'],
                $metrics['total_findings'],
                $metrics['detected_count']
            );
        }

        File::put($path, $csv);
    }

    private function exportToJson(array $data, string $path): void
    {
        $exportData = [
            'export_timestamp' => now()->toISOString(),
            'analysis_type' => 'comparison',
            'runs_analyzed' => count($data),
            'runs' => $data
        ];

        File::put($path, json_encode($exportData, JSON_PRETTY_PRINT));
    }

    private function exportToMarkdown(array $data, string $path): void
    {
        $md = "# Regression Test Analysis\n\n";
        $md .= "Generated: " . now()->format('Y-m-d H:i:s T') . "\n\n";
        $md .= "## Run Comparison\n\n";
        $md .= "| Run | Time | Detection Rate | Avg Risk | Findings |\n";
        $md .= "|-----|------|---------------|----------|----------|\n";

        foreach ($data as $i => $run) {
            $metrics = $run['metrics'];
            $md .= sprintf(
                "| %d | %s | %.1f%% | %.1f%% | %d |\n",
                $i + 1,
                Carbon::parse($run['timestamp'])->format('m-d H:i'),
                $metrics['detection_rate'],
                $metrics['average_risk_score'],
                $metrics['total_findings']
            );
        }

        $md .= "\n## Summary\n\n";
        $md .= "- Total runs analyzed: " . count($data) . "\n";
        $md .= "- Analysis period: " . Carbon::parse($data[0]['timestamp'])->format('Y-m-d') . 
               " to " . Carbon::parse(end($data)['timestamp'])->format('Y-m-d') . "\n";

        File::put($path, $md);
    }

    private function getHistoricalData(int $days): array
    {
        $resultsPath = storage_path('app/regression_tests');
        
        if (!File::exists($resultsPath)) {
            return [];
        }

        $files = File::files($resultsPath);
        $cutoff = Carbon::now()->subDays($days);
        $history = [];

        foreach ($files as $file) {
            $modTime = Carbon::createFromTimestamp($file->getMTime());
            
            if ($modTime->gte($cutoff)) {
                try {
                    $content = File::get($file->getPathname());
                    $data = json_decode($content, true);
                    if ($data) {
                        $history[] = $data;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        usort($history, fn($a, $b) => 
            Carbon::parse($a['timestamp'])->timestamp - Carbon::parse($b['timestamp'])->timestamp
        );

        return $history;
    }

    private function getLatestResults(): ?array
    {
        $resultsPath = storage_path('app/regression_tests');
        
        if (!File::exists($resultsPath)) {
            return null;
        }

        $files = File::files($resultsPath);
        if (empty($files)) {
            return null;
        }

        usort($files, fn($a, $b) => $b->getMTime() - $a->getMTime());
        $latestFile = $files[0];

        try {
            $content = File::get($latestFile->getPathname());
            return json_decode($content, true);
        } catch (\Exception $e) {
            return null;
        }
    }
}