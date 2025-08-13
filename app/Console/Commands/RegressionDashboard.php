<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

final class RegressionDashboard extends Command
{
    protected $signature = 'regression:dashboard
                           {--live : Enable live updates}
                           {--history=7 : Show history for N days}
                           {--detailed : Show detailed breakdown}';

    protected $description = 'Display regression test dashboard with historical data';

    public function handle(): int
    {
        if ($this->option('live')) {
            return $this->runLiveDashboard();
        }

        $this->displayStaticDashboard();
        return Command::SUCCESS;
    }

    private function runLiveDashboard(): int
    {
        $this->info('🔴 Live Regression Test Dashboard (Press Ctrl+C to exit)');
        $this->newLine();

        while (true) {
            $this->output->write("\033[2J\033[H"); // Clear screen
            $this->displayHeader();
            $this->displayCurrentStatus();
            $this->displayRecentResults();
            $this->displayTrends();
            
            sleep(5); // Update every 5 seconds
        }
    }

    private function displayStaticDashboard(): void
    {
        $this->displayHeader();
        $this->displayCurrentStatus();
        $this->displayRecentResults();
        $this->displayHistoricalTrends();
        
        if ($this->option('detailed')) {
            $this->displayDetailedBreakdown();
        }
    }

    private function displayHeader(): void
    {
        $this->info('📊 VULNERABILITY REGRESSION TEST DASHBOARD');
        $this->info('Real-time monitoring of smart contract security analysis');
        $this->line('Generated: ' . now()->format('Y-m-d H:i:s T'));
        $this->newLine();
    }

    private function displayCurrentStatus(): void
    {
        $this->info('🎯 CURRENT STATUS');
        $this->newLine();

        // Get latest results
        $latestResults = $this->getLatestResults();
        
        if (!$latestResults) {
            $this->warn('⚠️  No recent test results found. Run: php artisan regression:run');
            $this->newLine();
            return;
        }

        $metrics = $latestResults['metrics'] ?? [];
        $timestamp = $latestResults['timestamp'] ?? 'Unknown';
        
        $statusTable = [
            ['Last Run', Carbon::parse($timestamp)->diffForHumans()],
            ['Detection Rate', $this->formatDetectionRate($metrics['detection_rate'] ?? 0)],
            ['Average Risk Score', round($metrics['average_risk_score'] ?? 0, 1) . '%'],
            ['Total Findings', number_format($metrics['total_findings'] ?? 0)],
            ['Test Status', $this->getTestStatus($metrics)],
        ];

        $this->table(['Metric', 'Value'], $statusTable);
        $this->newLine();
    }

    private function displayRecentResults(): void
    {
        $this->info('📋 RECENT VULNERABILITY DETECTIONS');
        $this->newLine();

        $latestResults = $this->getLatestResults();
        if (!$latestResults || !isset($latestResults['results'])) {
            $this->warn('No recent results available');
            $this->newLine();
            return;
        }

        $results = $latestResults['results'];
        $tableData = [];

        foreach ($results as $result) {
            $status = $result['detected'] ? '✅ PASS' : '❌ FAIL';
            $severity = strtoupper($result['severity']);
            
            $tableData[] = [
                $result['contract_name'],
                $severity,
                $status,
                $result['risk_score'] . '%',
                $result['findings_count'],
                round(($result['processing_time_ms'] ?? 0) / 1000, 1) . 's'
            ];
        }

        $this->table(
            ['Contract', 'Severity', 'Status', 'Risk', 'Findings', 'Time'],
            $tableData
        );
        $this->newLine();
    }

    private function displayTrends(): void
    {
        $this->info('📈 DETECTION RATE TRENDS');
        $this->newLine();

        $history = $this->getHistoricalData((int) $this->option('history'));
        
        if (count($history) < 2) {
            $this->warn('Insufficient historical data for trends (need at least 2 test runs)');
            $this->newLine();
            return;
        }

        // Calculate trend data
        $trendData = [];
        foreach ($history as $entry) {
            $date = Carbon::parse($entry['timestamp'])->format('m-d H:i');
            $rate = round($entry['metrics']['detection_rate'] ?? 0, 1);
            $trendData[] = [$date, $rate . '%'];
        }

        $this->table(['Date', 'Detection Rate'], array_slice($trendData, -7)); // Last 7 entries
        
        // Show improvement/degradation
        $latest = end($history);
        $previous = prev($history);
        
        if ($latest && $previous) {
            $latestRate = $latest['metrics']['detection_rate'] ?? 0;
            $previousRate = $previous['metrics']['detection_rate'] ?? 0;
            $change = $latestRate - $previousRate;
            
            if ($change > 0) {
                $this->info("📈 Improvement: +{$change}% from previous run");
            } elseif ($change < 0) {
                $this->error("📉 Degradation: {$change}% from previous run");
            } else {
                $this->line("➡️  No change from previous run");
            }
        }
        
        $this->newLine();
    }

    private function displayHistoricalTrends(): void
    {
        $this->info('📊 HISTORICAL PERFORMANCE');
        $this->newLine();

        $history = $this->getHistoricalData((int) $this->option('history'));
        
        if (empty($history)) {
            $this->warn('No historical data available');
            $this->newLine();
            return;
        }

        // Calculate statistics
        $detectionRates = array_column(array_column($history, 'metrics'), 'detection_rate');
        $riskScores = array_column(array_column($history, 'metrics'), 'average_risk_score');
        $findingsCounts = array_column(array_column($history, 'metrics'), 'total_findings');

        $stats = [
            ['Metric', 'Min', 'Max', 'Average', 'Latest'],
            [
                'Detection Rate',
                round(min($detectionRates), 1) . '%',
                round(max($detectionRates), 1) . '%',
                round(array_sum($detectionRates) / count($detectionRates), 1) . '%',
                round(end($detectionRates), 1) . '%'
            ],
            [
                'Risk Score',
                round(min($riskScores), 1) . '%',
                round(max($riskScores), 1) . '%',
                round(array_sum($riskScores) / count($riskScores), 1) . '%',
                round(end($riskScores), 1) . '%'
            ],
            [
                'Total Findings',
                min($findingsCounts),
                max($findingsCounts),
                round(array_sum($findingsCounts) / count($findingsCounts), 1),
                end($findingsCounts)
            ]
        ];

        $this->table([], $stats);
        $this->newLine();
    }

    private function displayDetailedBreakdown(): void
    {
        $this->info('🔍 DETAILED BREAKDOWN');
        $this->newLine();

        $latestResults = $this->getLatestResults();
        if (!$latestResults) return;

        $results = $latestResults['results'] ?? [];
        $metrics = $latestResults['metrics'] ?? [];

        // Severity breakdown
        $this->line('📋 By Severity Level:');
        foreach ($metrics['severity_breakdown'] ?? [] as $severity => $stats) {
            if ($stats['total'] > 0) {
                $rate = round(($stats['detected'] / $stats['total']) * 100, 1);
                $this->line("  {$severity}: {$rate}% ({$stats['detected']}/{$stats['total']}) - Avg Risk: {$stats['avg_risk_score']}%");
            }
        }
        $this->newLine();

        // Performance breakdown
        $this->line('⚡ Performance Metrics:');
        if (isset($latestResults['performance'])) {
            $perf = $latestResults['performance'];
            $this->line("  Total Duration: " . round($perf['total_duration_seconds'], 2) . "s");
            $this->line("  Average Processing: " . round($perf['average_processing_time_ms'] / 1000, 2) . "s");
            $this->line("  Total Tokens: " . number_format($perf['total_tokens_used']));
        }
        $this->newLine();

        // Failed contracts
        $failed = array_filter($results, fn($r) => !$r['detected']);
        if (!empty($failed)) {
            $this->error('❌ Failed Detections:');
            foreach ($failed as $fail) {
                $this->line("  • {$fail['contract_name']} ({$fail['severity']}) - Risk: {$fail['risk_score']}%");
            }
            $this->newLine();
        }
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

        // Get the most recent file
        usort($files, fn($a, $b) => $b->getMTime() - $a->getMTime());
        $latestFile = $files[0];

        try {
            $content = File::get($latestFile->getPathname());
            return json_decode($content, true);
        } catch (\Exception $e) {
            return null;
        }
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
                    // Skip corrupted files
                    continue;
                }
            }
        }

        // Sort by timestamp
        usort($history, fn($a, $b) => 
            Carbon::parse($a['timestamp'])->timestamp - Carbon::parse($b['timestamp'])->timestamp
        );

        return $history;
    }

    private function formatDetectionRate(float $rate): string
    {
        $color = match(true) {
            $rate >= 90 => '✅',
            $rate >= 75 => '🟡',
            default => '❌'
        };
        
        return $color . ' ' . round($rate, 1) . '%';
    }

    private function getTestStatus(array $metrics): string
    {
        $rate = $metrics['detection_rate'] ?? 0;
        $threshold = $metrics['pass_threshold'] ?? 70;
        
        if ($rate >= $threshold) {
            return '✅ PASSING';
        } else {
            return '❌ FAILING';
        }
    }
}