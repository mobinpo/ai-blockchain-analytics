<?php

namespace App\Console\Commands;

use App\Services\ChainExplorerManager;
use App\Services\BlockchainExplorerFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ManageChainExplorers extends Command
{
    protected $signature = 'explorers:manage 
                          {--status : Show status of all chain explorers}
                          {--test : Test connectivity to all chains}
                          {--test-chain= : Test specific chain connectivity}
                          {--health : Show comprehensive health report}
                          {--switch= : Switch to best explorer for chain}
                          {--clear-cache : Clear all explorer caches}
                          {--validate= : Validate configuration for specific chain}
                          {--performance : Show performance metrics}
                          {--monitor : Start continuous monitoring}
                          {--repair : Attempt to repair unhealthy explorers}
                          {--export-config : Export current configuration}';

    protected $description = 'Manage blockchain explorer abstraction layer with health monitoring and dynamic switching';

    private ChainExplorerManager $manager;

    public function __construct(ChainExplorerManager $manager)
    {
        parent::__construct();
        $this->manager = $manager;
    }

    public function handle(): int
    {
        $this->info('🔗 Blockchain Explorer Management System');
        $this->newLine();

        // Show status by default or when explicitly requested
        if ($this->option('status') || !$this->hasAnyOptions()) {
            return $this->showStatus();
        }

        if ($this->option('test')) {
            return $this->testAllChains();
        }

        if ($testChain = $this->option('test-chain')) {
            return $this->testSpecificChain($testChain);
        }

        if ($this->option('health')) {
            return $this->showHealthReport();
        }

        if ($switchChain = $this->option('switch')) {
            return $this->switchExplorer($switchChain);
        }

        if ($this->option('clear-cache')) {
            return $this->clearCaches();
        }

        if ($validateChain = $this->option('validate')) {
            return $this->validateConfiguration($validateChain);
        }

        if ($this->option('performance')) {
            return $this->showPerformanceMetrics();
        }

        if ($this->option('monitor')) {
            return $this->startMonitoring();
        }

        if ($this->option('repair')) {
            return $this->repairUnhealthyExplorers();
        }

        if ($this->option('export-config')) {
            return $this->exportConfiguration();
        }

        return Command::SUCCESS;
    }

    private function showStatus(): int
    {
        $this->info('📊 Chain Explorer Status Overview');
        $this->newLine();

        try {
            $status = $this->manager->getAllChainsStatus();
            $summary = $status['summary'];

            // Summary table
            $this->info('🎯 System Summary:');
            $this->table(
                ['Metric', 'Value', 'Status'],
                [
                    ['Total Chains', $summary['total_chains'], '📈'],
                    ['Configured Chains', $summary['configured_chains'], $this->getStatusIcon($summary['configured_chains'], $summary['total_chains'])],
                    ['Healthy Chains', $summary['healthy_chains'], $this->getStatusIcon($summary['healthy_chains'], $summary['configured_chains'])],
                    ['Average Health Score', round($summary['average_health_score'], 3), $this->getHealthIcon($summary['average_health_score'])],
                    ['Chains with Issues', count($summary['chains_with_issues']), $this->getIssueIcon(count($summary['chains_with_issues']))]
                ]
            );

            // Individual chain status
            $this->newLine();
            $this->info('🌐 Individual Chain Status:');
            
            $chainData = [];
            foreach ($status['chains'] as $chain => $info) {
                if (isset($info['error'])) {
                    $chainData[] = [
                        ucfirst($chain),
                        '❌ Error',
                        '0.0',
                        'N/A',
                        substr($info['error'], 0, 50) . '...'
                    ];
                } else {
                    $chainData[] = [
                        ucfirst($info['chain_info']['name'] ?? $chain),
                        $info['explorer_status']['healthy'] ? '✅ Healthy' : '❌ Unhealthy',
                        $info['explorer_status']['health_score'],
                        $info['performance_metrics']['success_rate'] . '%',
                        $info['recommended_action']
                    ];
                }
            }

            $this->table(
                ['Chain', 'Status', 'Health Score', 'Success Rate', 'Recommended Action'],
                $chainData
            );

            // Issues summary
            if (!empty($summary['chains_with_issues'])) {
                $this->newLine();
                $this->warn('⚠️ Chains Requiring Attention:');
                foreach ($summary['chains_with_issues'] as $issue) {
                    $this->line("  • {$issue['chain']}: {$issue['recommended_action']}");
                    if (!empty($issue['issues'])) {
                        foreach ($issue['issues'] as $problemDetail) {
                            $this->line("    - {$problemDetail}");
                        }
                    }
                }
            } else {
                $this->newLine();
                $this->info('✅ All configured chains are healthy!');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to get chain status: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function testAllChains(): int
    {
        $this->info('🧪 Testing Connectivity to All Chains...');
        $this->newLine();

        try {
            $results = $this->manager->testAllChains();

            $this->info("📈 Test Results Summary:");
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total Chains Tested', $results['total_chains_tested']],
                    ['Successful Connections', $results['successful_connections']],
                    ['Failed Connections', $results['failed_connections']],
                    ['Success Rate', round(($results['successful_connections'] / $results['total_chains_tested']) * 100, 1) . '%']
                ]
            );

            $this->newLine();
            $this->info('🔍 Detailed Test Results:');
            
            $testData = [];
            foreach ($results['results'] as $chain => $result) {
                $testData[] = [
                    ucfirst($chain),
                    $result['success'] ? '✅ Pass' : '❌ Fail',
                    $result['response_time_ms'] . 'ms',
                    $result['explorer_name'],
                    $result['error'] ?? 'N/A'
                ];
            }

            $this->table(
                ['Chain', 'Result', 'Response Time', 'Explorer', 'Error'],
                $testData
            );

            $failedTests = array_filter($results['results'], fn($r) => !$r['success']);
            if (!empty($failedTests)) {
                $this->newLine();
                $this->warn('❌ Failed Tests:');
                foreach ($failedTests as $chain => $result) {
                    $this->line("  • {$chain}: {$result['error']}");
                }
            } else {
                $this->newLine();
                $this->info('🎉 All connectivity tests passed!');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Test execution failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function testSpecificChain(string $chain): int
    {
        $this->info("🔍 Testing connectivity to {$chain}...");
        $this->newLine();

        try {
            $result = $this->manager->testChainConnectivity($chain);

            $status = $result['success'] ? 'PASS' : 'FAIL';
            $icon = $result['success'] ? '✅' : '❌';
            
            $this->line("{$icon} Test Result: {$status}");
            $this->line("⏱️  Response Time: {$result['response_time_ms']}ms");
            $this->line("🔗 Explorer: {$result['explorer_name']}");
            
            if ($result['error']) {
                $this->line("❗ Error: {$result['error']}");
            }

            $this->newLine();
            
            if ($result['success']) {
                $this->info("✅ {$chain} connectivity test passed!");
            } else {
                $this->error("❌ {$chain} connectivity test failed!");
                return Command::FAILURE;
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Test failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function showHealthReport(): int
    {
        $this->info('🏥 Comprehensive Health Report');
        $this->newLine();

        try {
            $report = BlockchainExplorerFactory::getSystemHealthReport();

            $this->info('📊 System Health Overview:');
            $this->table(
                ['Metric', 'Value', 'Status'],
                [
                    ['Total Networks', $report['total_networks'], '📈'],
                    ['Healthy Networks', $report['healthy_networks'], $this->getStatusIcon($report['healthy_networks'], $report['total_networks'])],
                    ['Configured Networks', $report['configured_networks'], $this->getStatusIcon($report['configured_networks'], $report['total_networks'])],
                    ['Average Health Score', $report['average_health_score'], $this->getHealthIcon($report['average_health_score'])]
                ]
            );

            if (!empty($report['recommendations'])) {
                $this->newLine();
                $this->warn('📋 System Recommendations:');
                foreach ($report['recommendations'] as $recommendation) {
                    $this->line("  • {$recommendation}");
                }
            }

            $this->newLine();
            $this->info('🔍 Network Health Details:');
            
            $healthData = [];
            foreach ($report['network_details'] as $network => $details) {
                $healthData[] = [
                    ucfirst($network),
                    $details['configured'] ? '✅' : '❌',
                    $details['healthy'] ? '✅' : '❌',
                    $details['health_score'],
                    count($details['issues']),
                    count($details['warnings'])
                ];
            }

            $this->table(
                ['Network', 'Configured', 'Healthy', 'Score', 'Issues', 'Warnings'],
                $healthData
            );

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to generate health report: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function switchExplorer(string $chain): int
    {
        $this->info("🔄 Switching to best explorer for {$chain}...");

        try {
            $explorer = $this->manager->switchToBestExplorer($chain);
            
            $this->info("✅ Successfully switched to explorer for {$chain}");
            $this->line("🔗 Explorer: {$explorer->getName()}");
            $this->line("🌐 API URL: {$explorer->getApiUrl()}");
            $this->line("⚡ Rate Limit: {$explorer->getRateLimit()} req/s");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to switch explorer: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function clearCaches(): int
    {
        $this->info('🧹 Clearing explorer caches...');

        try {
            // Clear health check caches
            $healthConfig = config('blockchain_explorers.health_check');
            $networks = BlockchainExplorerFactory::getSupportedNetworks();
            
            foreach ($networks as $network) {
                $healthKey = $healthConfig['cache_key_prefix'] . $network;
                Cache::forget($healthKey);
                
                $performanceKey = 'chain_explorer_manager:performance:' . $network;
                Cache::forget($performanceKey);
                
                $healthCheckKey = 'chain_explorer_manager:health_check:' . $network;
                Cache::forget($healthCheckKey);
            }

            $this->info('✅ All explorer caches cleared successfully');
            $this->comment('💡 Next health checks will rebuild cache data');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to clear caches: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function validateConfiguration(string $chain): int
    {
        $this->info("🔍 Validating configuration for {$chain}...");
        $this->newLine();

        try {
            $validation = BlockchainExplorerFactory::validateConfiguration($chain);

            $this->line("Chain: " . ucfirst($chain));
            $this->line("Valid: " . ($validation['valid'] ? '✅ Yes' : '❌ No'));
            $this->line("Config Key: " . ($validation['config_key'] ?? 'N/A'));
            $this->line("Can Create Explorer: " . ($validation['can_create_explorer'] ? '✅ Yes' : '❌ No'));
            $this->line("Health Score: " . $validation['health_score']);
            $this->line("Fallbacks Available: " . $validation['fallbacks_available']);

            if (!empty($validation['issues'])) {
                $this->newLine();
                $this->error('❌ Configuration Issues:');
                foreach ($validation['issues'] as $issue) {
                    $this->line("  • {$issue}");
                }
            }

            if (!empty($validation['warnings'])) {
                $this->newLine();
                $this->warn('⚠️ Configuration Warnings:');
                foreach ($validation['warnings'] as $warning) {
                    $this->line("  • {$warning}");
                }
            }

            if ($validation['valid']) {
                $this->newLine();
                $this->info("✅ {$chain} configuration is valid!");
            } else {
                $this->newLine();
                $this->error("❌ {$chain} configuration has issues that need to be resolved.");
                return Command::FAILURE;
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Validation failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function showPerformanceMetrics(): int
    {
        $this->info('📈 Explorer Performance Metrics');
        $this->newLine();

        try {
            $status = $this->manager->getAllChainsStatus();
            
            $this->info('⚡ Performance Overview:');
            
            $perfData = [];
            foreach ($status['chains'] as $chain => $info) {
                if (!isset($info['error'])) {
                    $perf = $info['performance_metrics'];
                    $perfData[] = [
                        ucfirst($chain),
                        $perf['total_operations'],
                        $perf['success_rate'] . '%',
                        round($perf['average_response_time'], 0) . 'ms',
                        $perf['operations_last_hour'],
                        $perf['last_operation'] ?? 'Never'
                    ];
                }
            }

            $this->table(
                ['Chain', 'Total Ops', 'Success Rate', 'Avg Response', 'Last Hour', 'Last Operation'],
                $perfData
            );

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to get performance metrics: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function startMonitoring(): int
    {
        $this->info('👁️  Starting continuous monitoring...');
        $this->comment('Press Ctrl+C to stop monitoring');
        $this->newLine();

        $iteration = 0;
        while (true) {
            $iteration++;
            $this->line("--- Monitor Cycle #{$iteration} at " . now()->format('H:i:s') . " ---");

            try {
                $status = $this->manager->getAllChainsStatus();
                $summary = $status['summary'];

                $this->line("Healthy: {$summary['healthy_chains']}/{$summary['configured_chains']} | " .
                          "Avg Health: {$summary['average_health_score']} | " .
                          "Issues: " . count($summary['chains_with_issues']));

                if (!empty($summary['chains_with_issues'])) {
                    foreach ($summary['chains_with_issues'] as $issue) {
                        if ($issue['health_score'] < 0.3) {
                            $this->error("  🚨 CRITICAL: {$issue['chain']} - {$issue['recommended_action']}");
                        } elseif ($issue['health_score'] < 0.7) {
                            $this->warn("  ⚠️  WARNING: {$issue['chain']} - {$issue['recommended_action']}");
                        }
                    }
                }

            } catch (\Exception $e) {
                $this->error("Monitor error: {$e->getMessage()}");
            }

            sleep(30); // Check every 30 seconds
        }

        return Command::SUCCESS;
    }

    private function repairUnhealthyExplorers(): int
    {
        $this->info('🔧 Attempting to repair unhealthy explorers...');
        $this->newLine();

        try {
            $status = $this->manager->getAllChainsStatus();
            $unhealthyChains = [];

            foreach ($status['chains'] as $chain => $info) {
                if (!isset($info['error']) && 
                    (!$info['explorer_status']['healthy'] || $info['explorer_status']['health_score'] < 0.5)) {
                    $unhealthyChains[] = $chain;
                }
            }

            if (empty($unhealthyChains)) {
                $this->info('✅ No unhealthy explorers found - all systems normal!');
                return Command::SUCCESS;
            }

            $this->info("Found " . count($unhealthyChains) . " unhealthy explorers to repair:");
            
            $repairedCount = 0;
            $failedCount = 0;

            foreach ($unhealthyChains as $chain) {
                $this->line("🔧 Repairing {$chain}...");
                
                try {
                    // Clear caches for this chain
                    $healthConfig = config('blockchain_explorers.health_check');
                    $healthKey = $healthConfig['cache_key_prefix'] . $chain;
                    Cache::forget($healthKey);
                    
                    // Force a fresh explorer creation
                    $explorer = $this->manager->switchToBestExplorer($chain);
                    
                    // Test the new explorer
                    $testResult = $this->manager->testChainConnectivity($chain);
                    
                    if ($testResult['success']) {
                        $this->info("  ✅ {$chain} repaired successfully");
                        $repairedCount++;
                    } else {
                        $this->error("  ❌ {$chain} repair failed: {$testResult['error']}");
                        $failedCount++;
                    }
                    
                } catch (\Exception $e) {
                    $this->error("  ❌ {$chain} repair failed: {$e->getMessage()}");
                    $failedCount++;
                }
            }

            $this->newLine();
            $this->info("🎯 Repair Summary:");
            $this->line("  • Repaired: {$repairedCount}");
            $this->line("  • Failed: {$failedCount}");
            $this->line("  • Total Processed: " . count($unhealthyChains));

            return $repairedCount > 0 ? Command::SUCCESS : Command::FAILURE;

        } catch (\Exception $e) {
            $this->error("Repair operation failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function exportConfiguration(): int
    {
        $this->info('📄 Exporting current configuration...');

        try {
            $networkInfo = BlockchainExplorerFactory::getNetworkInfo();
            $systemHealth = BlockchainExplorerFactory::getSystemHealthReport();
            
            $export = [
                'export_timestamp' => now()->toISOString(),
                'system_health' => $systemHealth,
                'network_configurations' => $networkInfo,
                'environment_variables' => $this->getRelevantEnvVars()
            ];

            $filename = 'explorer_config_' . now()->format('Y-m-d_H-i-s') . '.json';
            $filepath = storage_path('app/' . $filename);
            
            file_put_contents($filepath, json_encode($export, JSON_PRETTY_PRINT));
            
            $this->info("✅ Configuration exported to: {$filepath}");
            $this->comment("💡 Use this file for backup or debugging purposes");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Export failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function hasAnyOptions(): bool
    {
        $options = ['status', 'test', 'test-chain', 'health', 'switch', 'clear-cache', 
                   'validate', 'performance', 'monitor', 'repair', 'export-config'];
        
        foreach ($options as $option) {
            if ($this->option($option)) {
                return true;
            }
        }
        
        return false;
    }

    private function getStatusIcon(int $current, int $total): string
    {
        $percentage = $total > 0 ? ($current / $total) * 100 : 0;
        
        return match(true) {
            $percentage >= 90 => '✅',
            $percentage >= 70 => '⚠️',
            default => '❌'
        };
    }

    private function getHealthIcon(float $score): string
    {
        return match(true) {
            $score >= 0.9 => '✅',
            $score >= 0.7 => '⚠️',
            $score >= 0.5 => '🟡',
            default => '❌'
        };
    }

    private function getIssueIcon(int $issueCount): string
    {
        return match(true) {
            $issueCount === 0 => '✅',
            $issueCount <= 2 => '⚠️',
            default => '❌'
        };
    }

    private function getRelevantEnvVars(): array
    {
        $envVars = [];
        $networks = ['ETHERSCAN', 'BSCSCAN', 'POLYGONSCAN', 'ARBISCAN', 
                    'OPTIMISTIC_ETHERSCAN', 'SNOWTRACE', 'FTMSCAN'];
        
        foreach ($networks as $network) {
            $envVars["{$network}_API_KEY"] = env("{$network}_API_KEY") ? 'SET' : 'NOT SET';
            $envVars["{$network}_API_URL"] = env("{$network}_API_URL", 'DEFAULT');
        }
        
        return $envVars;
    }
}