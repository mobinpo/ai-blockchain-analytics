<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MultiChainExplorerManager;
use App\Services\BlockchainExplorerFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ManageMultiChainExplorers extends Command
{
    protected $signature = 'multichain:manage 
                           {action : Action (status|test|switch|analytics|health|optimize)}
                           {--network= : Specific network to test}
                           {--contract= : Contract address to test with}
                           {--all-chains : Test all supported chains}
                           {--detailed : Show detailed output}
                           {--force : Skip confirmations}';

    protected $description = 'Manage multi-chain blockchain explorer abstraction layer';

    private MultiChainExplorerManager $manager;

    public function __construct(MultiChainExplorerManager $manager)
    {
        parent::__construct();
        $this->manager = $manager;
    }

    public function handle(): int
    {
        $action = $this->argument('action');

        $this->info('🌐 Multi-Chain Explorer Abstraction Layer');
        $this->newLine();

        try {
            return match ($action) {
                'status' => $this->showNetworkStatus(),
                'test' => $this->testExplorers(),
                'switch' => $this->testExplorerSwitching(),
                'analytics' => $this->showAnalytics(),
                'health' => $this->showHealthReport(),
                'optimize' => $this->optimizeExplorers(),
                default => $this->showHelp()
            };

        } catch (\Exception $e) {
            $this->error("❌ Command failed: {$e->getMessage()}");
            if ($this->option('verbose')) {
                $this->line($e->getTraceAsString());
            }
            return self::FAILURE;
        }
    }

    private function showNetworkStatus(): int
    {
        $this->info('📊 Multi-Chain Network Status');
        $this->newLine();

        $status = $this->manager->getNetworkStatus();

        $statusRows = [];
        foreach ($status as $network => $data) {
            $availabilityIcon = $data['is_available'] ? '✅' : '❌';
            $healthIcon = match ($data['health_status']) {
                'excellent' => '🟢',
                'good' => '🔵', 
                'fair' => '🟡',
                'poor' => '🟠',
                'critical' => '🔴',
                default => '⚪'
            };

            $statusRows[] = [
                $network,
                $data['priority'],
                $availabilityIcon . ' ' . ($data['is_available'] ? 'Available' : 'Unavailable'),
                $healthIcon . ' ' . ucfirst($data['health_status']),
                round($data['health_score'], 3),
                $data['circuit_breaker'],
                round($data['avg_response_time'], 0) . 'ms',
                round($data['success_rate'] * 100, 1) . '%',
                number_format($data['total_requests'])
            ];
        }

        $this->table(
            ['Network', 'Priority', 'Status', 'Health', 'Score', 'Circuit', 'Avg Response', 'Success Rate', 'Requests'],
            $statusRows
        );

        // Show recommendations
        $hasRecommendations = false;
        foreach ($status as $network => $data) {
            if (!empty($data['recommendations'])) {
                if (!$hasRecommendations) {
                    $this->newLine();
                    $this->info('💡 Network Recommendations:');
                    $hasRecommendations = true;
                }
                $this->line("  🔹 {$network}:");
                foreach ($data['recommendations'] as $recommendation) {
                    $this->line("    • {$recommendation}");
                }
            }
        }

        return self::SUCCESS;
    }

    private function testExplorers(): int
    {
        $network = $this->option('network');
        $contractAddress = $this->option('contract') ?? '0xdac17f958d2ee523a2206206994597c13d831ec7'; // USDT
        $allChains = $this->option('all-chains');

        if ($allChains) {
            return $this->testAllChains($contractAddress);
        }

        if (!$network) {
            $this->error('❌ Please specify --network or use --all-chains');
            return self::FAILURE;
        }

        $this->info("🧪 Testing Explorer Abstraction for: {$network}");
        $this->info("📝 Contract: {$contractAddress}");
        $this->newLine();

        try {
            $startTime = microtime(true);
            
            $result = $this->manager->executeWithRetry($network, function ($explorer) use ($contractAddress) {
                return $explorer->getContractSource($contractAddress);
            });
            
            $duration = round((microtime(true) - $startTime) * 1000);
            
            $this->info("✅ Explorer test successful ({$duration}ms)");
            
            if ($this->option('detailed')) {
                $this->displayTestDetails($result, $duration);
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Explorer test failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    private function testAllChains(string $contractAddress): int
    {
        $this->info('🔗 Testing All Supported Chains');
        $this->info("📝 Contract: {$contractAddress}");
        $this->newLine();

        $networks = BlockchainExplorerFactory::getSupportedNetworks();
        
        $progressBar = $this->output->createProgressBar(count($networks));
        $progressBar->start();
        
        $results = $this->manager->executeMultiChain(
            $networks,
            function ($explorer) use ($contractAddress, $progressBar) {
                $result = $explorer->isContractVerified($contractAddress);
                $progressBar->advance();
                return $result;
            }
        );

        $progressBar->finish();
        $this->newLine(2);

        // Display results
        $this->displayMultiChainResults($results);

        return count($results['failed']) === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function testExplorerSwitching(): int
    {
        $this->info('🔄 Testing Explorer Switching & Failover Logic');
        $this->newLine();

        $networks = ['ethereum', 'bsc', 'polygon', 'arbitrum'];
        $contractAddress = $this->option('contract') ?? '0xdac17f958d2ee523a2206206994597c13d831ec7';

        foreach ($networks as $network) {
            $this->line("🔍 Testing {$network}...");
            
            try {
                // Test primary explorer selection
                $startTime = microtime(true);
                $explorer = $this->manager->getBestExplorer($network);
                $duration = round((microtime(true) - $startTime) * 1000);
                
                $this->line("  ✅ Selected explorer for {$network} ({$duration}ms)");
                $this->line("  📊 Explorer Class: " . get_class($explorer));
                $this->line("  🔗 API URL: " . $explorer->getApiUrl());
                
                // Test actual operation with retry logic
                $operationStart = microtime(true);
                $result = $this->manager->executeWithRetry($network, function ($explorer) use ($contractAddress) {
                    return $explorer->isContractVerified($contractAddress);
                });
                $operationDuration = round((microtime(true) - $operationStart) * 1000);
                
                $this->line("  🔍 Contract verification: " . ($result ? '✅ Verified' : '❌ Not verified') . " ({$operationDuration}ms)");
                
                // Test health check
                $healthScore = BlockchainExplorerFactory::getExplorerHealthScore($network);
                $this->line("  🏥 Health Score: {$healthScore}");
                
            } catch (\Exception $e) {
                $this->line("  ❌ Failed: {$e->getMessage()}");
                
                // Test if failover works
                $this->line("  🔄 Testing failover...");
                try {
                    $fallbackExplorer = $this->manager->getBestExplorer($network);
                    $this->line("  ✅ Failover successful");
                } catch (\Exception $fallbackError) {
                    $this->line("  ❌ Failover also failed: {$fallbackError->getMessage()}");
                }
            }
            
            $this->newLine();
        }

        return self::SUCCESS;
    }

    private function showAnalytics(): int
    {
        $hours = 24;
        $this->info("📈 Multi-Chain Performance Analytics ({$hours}h)");
        $this->newLine();

        $analytics = $this->manager->getPerformanceAnalytics($hours);

        // System-wide metrics
        $system = $analytics['system_metrics'];
        $this->info('🌐 System Overview:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Requests', number_format($system['total_requests'])],
                ['Total Successful', number_format($system['total_successful'])],
                ['Overall Success Rate', round($system['overall_success_rate'] * 100, 1) . '%'],
                ['Average Response Time', round($system['average_response_time'], 0) . 'ms'],
                ['Active Networks', $system['active_networks']],
                ['Healthy Networks', $system['healthy_networks']],
                ['Degraded Networks', $system['degraded_networks']],
            ]
        );

        // Per-network metrics
        if ($this->option('detailed')) {
            $this->newLine();
            $this->info('📊 Per-Network Performance:');
            
            $networkRows = [];
            foreach ($analytics['network_metrics'] as $network => $metrics) {
                $networkRows[] = [
                    $network,
                    number_format($metrics['total_requests']),
                    round($metrics['success_rate'] * 100, 1) . '%',
                    round($metrics['avg_response_time'], 0) . 'ms',
                    $metrics['recent_failures'],
                    $metrics['circuit_state']
                ];
            }
            
            $this->table(
                ['Network', 'Requests', 'Success Rate', 'Avg Response', 'Recent Failures', 'Circuit State'],
                $networkRows
            );
        }

        // Performance recommendations
        if (!empty($analytics['recommendations'])) {
            $this->newLine();
            $this->info('💡 Performance Recommendations:');
            foreach ($analytics['recommendations'] as $recommendation) {
                $this->line("  • {$recommendation}");
            }
        }

        return self::SUCCESS;
    }

    private function showHealthReport(): int
    {
        $this->info('🏥 Multi-Chain System Health Report');
        $this->newLine();

        $healthReport = BlockchainExplorerFactory::getSystemHealthReport();

        // Overall system health
        $this->info('📋 System Health Summary:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Networks', $healthReport['total_networks']],
                ['Configured Networks', $healthReport['configured_networks']],
                ['Healthy Networks', $healthReport['healthy_networks']],
                ['Unhealthy Networks', $healthReport['unhealthy_networks']],
                ['Average Health Score', $healthReport['average_health_score']],
                ['Report Generated', $healthReport['timestamp']],
            ]
        );

        // Individual network validation
        if ($this->option('detailed')) {
            $this->newLine();
            $this->info('🔍 Individual Network Analysis:');
            
            foreach ($healthReport['network_details'] as $network => $details) {
                $this->line("  🔹 {$network}:");
                $this->line("    Configured: " . ($details['configured'] ? '✅ Yes' : '❌ No'));
                $this->line("    Healthy: " . ($details['healthy'] ? '✅ Yes' : '❌ No'));
                $this->line("    Health Score: " . round($details['health_score'], 3));
                
                // Show configuration validation
                $validation = BlockchainExplorerFactory::validateConfiguration($network);
                if (!empty($validation['issues'])) {
                    $this->line("    Configuration Issues:");
                    foreach ($validation['issues'] as $issue) {
                        $this->line("      ❌ {$issue}");
                    }
                }
                
                if (!empty($validation['warnings'])) {
                    $this->line("    Warnings:");
                    foreach ($validation['warnings'] as $warning) {
                        $this->line("      ⚠️  {$warning}");
                    }
                }
                
                $this->newLine();
            }
        }

        // System-wide recommendations
        if (!empty($healthReport['recommendations'])) {
            $this->newLine();
            $this->info('💡 System Recommendations:');
            foreach ($healthReport['recommendations'] as $recommendation) {
                $this->line("  • {$recommendation}");
            }
        }

        return self::SUCCESS;
    }

    private function optimizeExplorers(): int
    {
        $this->info('⚡ Optimizing Multi-Chain Explorer Performance');
        $this->newLine();

        if (!$this->option('force') && !$this->confirm('This will optimize circuit breakers, cache, and failover logic. Continue?')) {
            $this->info('Optimization cancelled.');
            return self::SUCCESS;
        }

        $this->info('🔧 Running optimization...');
        
        $optimizations = $this->manager->optimizeExplorerSelection();

        if (empty($optimizations)) {
            $this->info('✅ No optimizations needed - all explorers performing optimally');
        } else {
            $this->info('✅ Optimization completed:');
            $this->newLine();
            
            foreach ($optimizations as $optimization) {
                $this->line("  • {$optimization}");
            }
        }

        // Refresh all explorer instances
        if (!$this->option('force') && $this->confirm('Refresh all explorer instances to apply optimizations?', true)) {
            $this->info('🔄 Refreshing explorer instances...');
            $refreshed = $this->manager->refreshAllExplorers();
            
            if (!empty($refreshed)) {
                $this->info("✅ Refreshed explorers for: " . implode(', ', $refreshed));
            } else {
                $this->warn('⚠️  No explorers were refreshed');
            }
        }

        return self::SUCCESS;
    }

    private function showHelp(): int
    {
        $this->info('📚 Multi-Chain Explorer Abstraction Layer - Available Actions:');
        $this->newLine();

        $actions = [
            'status' => 'Show current network status, health, and circuit breaker states', 
            'test' => 'Test explorer functionality and abstraction layer',
            'switch' => 'Test intelligent explorer switching and failover logic',
            'analytics' => 'Show performance analytics and metrics across all chains',
            'health' => 'Show comprehensive health report with configuration validation',
            'optimize' => 'Optimize explorer selection, circuit breakers, and performance'
        ];

        foreach ($actions as $action => $description) {
            $this->line("  <info>{$action}</info> - {$description}");
        }

        $this->newLine();
        $this->info('🌐 Supported Networks:');
        $networks = BlockchainExplorerFactory::getSupportedNetworks();
        $this->line('  ' . implode(', ', $networks));

        $this->newLine();
        $this->info('💡 Examples:');
        $this->line('  php artisan multichain:manage status --detailed');
        $this->line('  php artisan multichain:manage test --network=ethereum --contract=0x123...');
        $this->line('  php artisan multichain:manage test --all-chains');
        $this->line('  php artisan multichain:manage switch');
        $this->line('  php artisan multichain:manage analytics --detailed');
        $this->line('  php artisan multichain:manage health --detailed');
        $this->line('  php artisan multichain:manage optimize --force');

        $this->newLine();
        $this->info('🔧 Key Features:');
        $this->line('  • Automatic explorer selection based on health and performance');
        $this->line('  • Circuit breaker pattern for failing explorers');
        $this->line('  • Intelligent failover with fallback chains');
        $this->line('  • Load balancing and rate limit management');
        $this->line('  • Real-time performance monitoring and optimization');

        return self::SUCCESS;
    }

    private function displayTestDetails(array $result, int $duration): void
    {
        $this->newLine();
        $this->info('📊 Detailed Test Results:');
        
        $details = [
            ['Property', 'Value'],
            ['Contract Name', $result['contract_name'] ?? 'Unknown'],
            ['Compiler Version', $result['compiler_version'] ?? 'Unknown'],
            ['Is Verified', ($result['is_verified'] ?? false) ? '✅ Yes' : '❌ No'],
            ['Network', $result['network'] ?? 'Unknown'],
            ['Explorer Used', $result['explorer'] ?? 'Unknown'],
            ['Response Time', $duration . 'ms'],
            ['Cached', isset($result['cached']) && $result['cached'] ? '✅ Yes' : '❌ No'],
        ];
        
        $this->table(['Property', 'Value'], $details);
        
        if (!empty($result['parsed_sources'])) {
            $this->newLine();
            $this->info('📄 Source Files Found:');
            foreach (array_keys($result['parsed_sources']) as $filename) {
                $this->line("  • {$filename}");
            }
        }
    }

    private function displayMultiChainResults(array $results): void
    {
        $summary = $results['summary'];
        
        $this->info('📊 Multi-Chain Test Summary:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Networks Tested', $summary['total_networks']],
                ['Successful', $summary['successful_count']],
                ['Failed', $summary['failed_count']],
                ['Success Rate', round($summary['success_rate'], 1) . '%'],
            ]
        );

        // Show successful networks
        if (!empty($results['successful'])) {
            $this->newLine();
            $this->info('✅ Successful Networks:');
            foreach ($results['successful'] as $network => $result) {
                $status = $result ? 'Contract verified' : 'Contract not verified';
                $this->line("  • {$network}: {$status}");
            }
        }

        // Show failed networks  
        if (!empty($results['failed'])) {
            $this->newLine();
            $this->warn('❌ Failed Networks:');
            foreach ($results['failed'] as $network => $error) {
                $this->line("  • {$network}: {$error}");
            }
        }
    }
}