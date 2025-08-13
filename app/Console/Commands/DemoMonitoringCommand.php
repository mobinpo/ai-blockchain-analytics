<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

final class DemoMonitoringCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'demo:monitor
                            {--days=7 : Number of days to analyze}
                            {--format=table : Output format (table, json, csv)}
                            {--export= : Export results to file}
                            {--alerts : Check for alerts and issues}';

    /**
     * The console command description.
     */
    protected $description = 'Monitor daily demo script execution and generate performance reports';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $format = $this->option('format');
        $exportFile = $this->option('export');
        $checkAlerts = $this->option('alerts');

        $this->displayBanner();

        try {
            // Collect demo results
            $results = $this->collectDemoResults($days);
            
            if (empty($results)) {
                $this->warn('No demo results found for the specified period.');
                return Command::SUCCESS;
            }

            // Generate analysis
            $analysis = $this->analyzeDemoResults($results);

            // Display results
            $this->displayResults($results, $analysis, $format);

            // Check for alerts
            if ($checkAlerts) {
                $this->checkAlerts($analysis);
            }

            // Export if requested
            if ($exportFile) {
                $this->exportResults($results, $analysis, $exportFile);
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Monitoring failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Display monitoring banner
     */
    private function displayBanner(): void
    {
        $this->line('');
        $this->line('<fg=cyan>═══════════════════════════════════════════════════════════════════</>');
        $this->line('<fg=cyan>   📊 AI Blockchain Analytics - Demo Monitoring Dashboard</>');
        $this->line('<fg=cyan>   🔍 Performance Analysis & Health Monitoring</>');
        $this->line('<fg=cyan>═══════════════════════════════════════════════════════════════════</>');
        $this->line('');
    }

    /**
     * Collect demo results from cache and logs
     */
    private function collectDemoResults(int $days): array
    {
        $results = [];
        $startDate = now()->subDays($days);

        $this->info("📅 Collecting demo results for the last {$days} days...");

        // Collect from cache
        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i);
            $cacheKey = 'daily_demo_results:' . $date->toDateString();
            
            $cachedResult = Cache::get($cacheKey);
            if ($cachedResult) {
                $results[$date->toDateString()] = $cachedResult;
            }
        }

        // Collect from JSON files
        $logPath = storage_path('logs');
        $jsonFiles = glob($logPath . '/daily-demo-*.json');
        
        foreach ($jsonFiles as $file) {
            $fileDate = $this->extractDateFromFilename(basename($file));
            if ($fileDate && $fileDate->gte($startDate)) {
                $content = json_decode(file_get_contents($file), true);
                if ($content) {
                    $results[$fileDate->toDateString()] = $content;
                }
            }
        }

        $this->line("   ✅ Found results for " . count($results) . " days");
        return $results;
    }

    /**
     * Analyze demo results
     */
    private function analyzeDemoResults(array $results): array
    {
        $this->info('🔍 Analyzing demo performance...');

        $analysis = [
            'total_days' => count($results),
            'successful_days' => 0,
            'failed_days' => 0,
            'average_success_rate' => 0,
            'average_duration' => 0,
            'module_performance' => [],
            'trends' => [],
            'issues' => []
        ];

        $totalSuccessRate = 0;
        $totalDuration = 0;
        $moduleStats = [];

        foreach ($results as $date => $result) {
            if (isset($result['summary'])) {
                $summary = $result['summary'];
                $successRate = $summary['success_rate'] ?? 0;
                $duration = $summary['duration_seconds'] ?? 0;

                if ($successRate > 80) {
                    $analysis['successful_days']++;
                } else {
                    $analysis['failed_days']++;
                    $analysis['issues'][] = [
                        'date' => $date,
                        'type' => 'low_success_rate',
                        'value' => $successRate,
                        'message' => "Low success rate: {$successRate}%"
                    ];
                }

                $totalSuccessRate += $successRate;
                $totalDuration += $duration;

                // Analyze module performance
                foreach ($result as $module => $data) {
                    if ($module === 'summary' || $module === 'environment') continue;
                    
                    if (!isset($moduleStats[$module])) {
                        $moduleStats[$module] = [
                            'total_operations' => 0,
                            'successful_operations' => 0,
                            'failures' => 0
                        ];
                    }

                    $total = $data['total_contracts'] ?? $data['total_texts'] ?? $data['total_badges'] ?? 0;
                    $successful = $data['successful'] ?? 0;

                    $moduleStats[$module]['total_operations'] += $total;
                    $moduleStats[$module]['successful_operations'] += $successful;
                    $moduleStats[$module]['failures'] += ($total - $successful);
                }
            }
        }

        if (count($results) > 0) {
            $analysis['average_success_rate'] = $totalSuccessRate / count($results);
            $analysis['average_duration'] = $totalDuration / count($results);
        }

        // Calculate module performance
        foreach ($moduleStats as $module => $stats) {
            $analysis['module_performance'][$module] = [
                'success_rate' => $stats['total_operations'] > 0 
                    ? ($stats['successful_operations'] / $stats['total_operations']) * 100 
                    : 0,
                'total_operations' => $stats['total_operations'],
                'failures' => $stats['failures']
            ];
        }

        // Detect trends
        $analysis['trends'] = $this->detectTrends($results);

        return $analysis;
    }

    /**
     * Detect performance trends
     */
    private function detectTrends(array $results): array
    {
        $trends = [];
        $sortedResults = [];

        // Sort results by date
        foreach ($results as $date => $result) {
            $sortedResults[$date] = $result['summary']['success_rate'] ?? 0;
        }
        ksort($sortedResults);

        $values = array_values($sortedResults);
        $count = count($values);

        if ($count >= 3) {
            $recent = array_slice($values, -3);
            $earlier = array_slice($values, 0, 3);

            $recentAvg = array_sum($recent) / count($recent);
            $earlierAvg = array_sum($earlier) / count($earlier);

            if ($recentAvg > $earlierAvg + 10) {
                $trends[] = ['type' => 'improving', 'message' => 'Performance is improving over time'];
            } elseif ($recentAvg < $earlierAvg - 10) {
                $trends[] = ['type' => 'declining', 'message' => 'Performance is declining over time'];
            } else {
                $trends[] = ['type' => 'stable', 'message' => 'Performance is stable'];
            }
        }

        return $trends;
    }

    /**
     * Display results
     */
    private function displayResults(array $results, array $analysis, string $format): void
    {
        $this->line('');
        $this->line('<fg=green>📊 DEMO MONITORING RESULTS</>');
        $this->line('<fg=green>══════════════════════════</>');

        // Summary statistics
        $this->line("📅 Analysis Period: {$analysis['total_days']} days");
        $this->line("✅ Successful Days: {$analysis['successful_days']}");
        $this->line("❌ Failed Days: {$analysis['failed_days']}");
        $this->line("📈 Average Success Rate: " . number_format($analysis['average_success_rate'], 1) . "%");
        $this->line("⏱️ Average Duration: " . number_format($analysis['average_duration'], 1) . " seconds");
        $this->line('');

        // Module performance
        if (!empty($analysis['module_performance'])) {
            $this->line('<fg=yellow>🔧 Module Performance:</>');
            
            $moduleData = [];
            foreach ($analysis['module_performance'] as $module => $stats) {
                $moduleData[] = [
                    'Module' => ucwords(str_replace('_', ' ', $module)),
                    'Success Rate' => number_format($stats['success_rate'], 1) . '%',
                    'Operations' => $stats['total_operations'],
                    'Failures' => $stats['failures']
                ];
            }

            if ($format === 'table') {
                $this->table(['Module', 'Success Rate', 'Operations', 'Failures'], $moduleData);
            } else {
                foreach ($moduleData as $row) {
                    $this->line("   {$row['Module']}: {$row['Success Rate']} ({$row['Operations']} ops, {$row['Failures']} failures)");
                }
            }
        }

        // Trends
        if (!empty($analysis['trends'])) {
            $this->line('<fg=cyan>📈 Performance Trends:</>');
            foreach ($analysis['trends'] as $trend) {
                $icon = match($trend['type']) {
                    'improving' => '📈',
                    'declining' => '📉',
                    'stable' => '➡️',
                    default => '📊'
                };
                $this->line("   {$icon} {$trend['message']}");
            }
            $this->line('');
        }

        // Recent results
        $this->line('<fg=magenta>📋 Recent Demo Results:</>');
        $recentResults = array_slice($results, -5, 5, true);
        
        $tableData = [];
        foreach ($recentResults as $date => $result) {
            $summary = $result['summary'] ?? [];
            $tableData[] = [
                'Date' => $date,
                'Success Rate' => number_format($summary['success_rate'] ?? 0, 1) . '%',
                'Duration' => number_format($summary['duration_seconds'] ?? 0, 1) . 's',
                'Operations' => $summary['successful_operations'] ?? 0 . '/' . ($summary['total_operations'] ?? 0),
                'Status' => ($summary['success_rate'] ?? 0) > 80 ? '✅' : '❌'
            ];
        }

        if ($format === 'table') {
            $this->table(['Date', 'Success Rate', 'Duration', 'Operations', 'Status'], $tableData);
        }
    }

    /**
     * Check for alerts
     */
    private function checkAlerts(array $analysis): void
    {
        $this->info('🚨 Checking for alerts and issues...');

        $alerts = [];

        // Low success rate alert
        if ($analysis['average_success_rate'] < 70) {
            $alerts[] = [
                'type' => 'critical',
                'message' => "Low average success rate: {$analysis['average_success_rate']}%"
            ];
        }

        // High failure rate alert
        if ($analysis['failed_days'] > $analysis['successful_days']) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "More failed days ({$analysis['failed_days']}) than successful ({$analysis['successful_days']})"
            ];
        }

        // Module-specific alerts
        foreach ($analysis['module_performance'] as $module => $stats) {
            if ($stats['success_rate'] < 50) {
                $alerts[] = [
                    'type' => 'warning',
                    'message' => "Module '{$module}' has low success rate: {$stats['success_rate']}%"
                ];
            }
        }

        // Performance decline alert
        foreach ($analysis['trends'] as $trend) {
            if ($trend['type'] === 'declining') {
                $alerts[] = [
                    'type' => 'warning',
                    'message' => 'Performance is declining over time'
                ];
            }
        }

        if (empty($alerts)) {
            $this->line('   ✅ No alerts detected - system is healthy');
        } else {
            $this->line('   🚨 Alerts detected:');
            foreach ($alerts as $alert) {
                $icon = $alert['type'] === 'critical' ? '🔴' : '🟡';
                $this->line("      {$icon} {$alert['message']}");
                
                // Log alert
                Log::warning('Demo monitoring alert', [
                    'type' => $alert['type'],
                    'message' => $alert['message'],
                    'timestamp' => now()
                ]);
            }
        }
    }

    /**
     * Export results
     */
    private function exportResults(array $results, array $analysis, string $filename): void
    {
        $this->info("📤 Exporting results to: {$filename}");

        $exportData = [
            'generated_at' => now()->toISOString(),
            'analysis' => $analysis,
            'raw_results' => $results
        ];

        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        
        switch ($extension) {
            case 'json':
                file_put_contents($filename, json_encode($exportData, JSON_PRETTY_PRINT));
                break;
            case 'csv':
                $this->exportToCsv($results, $filename);
                break;
            default:
                file_put_contents($filename, json_encode($exportData, JSON_PRETTY_PRINT));
        }

        $this->line("   ✅ Results exported to: {$filename}");
    }

    /**
     * Export to CSV format
     */
    private function exportToCsv(array $results, string $filename): void
    {
        $handle = fopen($filename, 'w');
        
        // Headers
        fputcsv($handle, ['Date', 'Success Rate', 'Duration', 'Total Operations', 'Successful Operations']);

        // Data
        foreach ($results as $date => $result) {
            $summary = $result['summary'] ?? [];
            fputcsv($handle, [
                $date,
                $summary['success_rate'] ?? 0,
                $summary['duration_seconds'] ?? 0,
                $summary['total_operations'] ?? 0,
                $summary['successful_operations'] ?? 0
            ]);
        }

        fclose($handle);
    }

    /**
     * Extract date from filename
     */
    private function extractDateFromFilename(string $filename): ?Carbon
    {
        if (preg_match('/daily-demo-(\d{4}-\d{2}-\d{2})\.json/', $filename, $matches)) {
            return Carbon::parse($matches[1]);
        }
        return null;
    }
}
