<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ChainDetectorService;
use App\Services\SmartChainSwitchingService;
use App\Services\BlockchainExplorerFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class TestChainDetection extends Command
{
    protected $signature = 'chain:detect 
                           {address : Contract address to detect}
                           {--network= : Preferred network}
                           {--action=detect : Action to perform (detect|switch|verify|stats|clear)}
                           {--clear-cache : Clear cached results}
                           {--timeout=30 : Timeout in seconds}
                           {--detailed : Show detailed output}';

    protected $description = 'Test blockchain chain detection and smart switching';

    public function handle(): int
    {
        $address = $this->argument('address');
        $network = $this->option('network');
        $action = $this->option('action');
        $clearCache = $this->option('clear-cache');
        $detailed = $this->option('detailed');

        if ($clearCache) {
            $this->clearAllCaches($address);
        }

        return match ($action) {
            'detect' => $this->runDetection($address, $detailed),
            'switch' => $this->testSmartSwitching($address, $network, $detailed),
            'verify' => $this->checkVerification($address, $detailed),
            'stats' => $this->showSystemStats(),
            'clear' => $this->clearCaches($address),
            'health' => $this->showHealthStatus(),
            default => $this->showUsage()
        };
    }

    private function runDetection(string $address, bool $detailed): int
    {
        $this->info("🔍 Detecting blockchain networks for: {$address}");
        $this->newLine();

        $detector = app(ChainDetectorService::class);
        
        try {
            $startTime = microtime(true);
            $results = $detector->detectChain($address);
            $detectionTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->displayDetectionResults($results, $detectionTime, $detailed);
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Detection failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function testSmartSwitching(string $address, ?string $network, bool $detailed): int
    {
        $this->info("🔄 Testing smart chain switching for: {$address}");
        if ($network) {
            $this->line("Preferred network: {$network}");
        }
        $this->newLine();

        $switchingService = app(SmartChainSwitchingService::class);
        
        try {
            $startTime = microtime(true);
            
            // Test getting contract source with smart switching
            $result = $switchingService->getContractSource($address, $network);
            
            $totalTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->displaySwitchingResults($result, $totalTime, $detailed);
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Smart switching failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function checkVerification(string $address, bool $detailed): int
    {
        $this->info("✅ Checking contract verification status: {$address}");
        $this->newLine();

        $switchingService = app(SmartChainSwitchingService::class);
        
        try {
            $startTime = microtime(true);
            $results = $switchingService->getVerificationStatus($address);
            $checkTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->displayVerificationResults($results, $checkTime, $detailed);
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Verification check failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function showSystemStats(): int
    {
        $this->info("📊 System Statistics");
        $this->newLine();

        $switchingService = app(SmartChainSwitchingService::class);
        $stats = $switchingService->getChainSwitchingStats();

        // System Health
        $this->line("<fg=cyan>System Health Overview:</>");
        $systemHealth = $stats['system_health'];
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Networks', $systemHealth['total_networks']],
                ['Healthy Networks', $systemHealth['healthy_networks']],
                ['Unhealthy Networks', $systemHealth['unhealthy_networks']],
                ['Configured Networks', $systemHealth['configured_networks']],
                ['Average Health Score', $systemHealth['average_health_score']],
            ]
        );

        // Network Health Scores
        $this->newLine();
        $this->line("<fg=cyan>Network Health Scores:</>");
        $healthData = [];
        foreach ($stats['detector_stats']['network_health_scores'] as $network => $score) {
            $status = $score >= 0.8 ? '🟢' : ($score >= 0.5 ? '🟡' : '🔴');
            $healthData[] = [$network, number_format($score, 3), $status];
        }
        $this->table(['Network', 'Health Score', 'Status'], $healthData);

        // Recommendations
        if (!empty($stats['system_health']['recommendations'])) {
            $this->newLine();
            $this->line("<fg=yellow>Recommendations:</>");
            foreach ($stats['system_health']['recommendations'] as $recommendation) {
                $this->line("• {$recommendation}");
            }
        }

        return Command::SUCCESS;
    }

    private function showHealthStatus(): int
    {
        $this->info("🏥 Network Health Status");
        $this->newLine();

        $networks = BlockchainExplorerFactory::getSupportedNetworks();
        $healthData = [];

        foreach ($networks as $network) {
            $healthScore = BlockchainExplorerFactory::getExplorerHealthScore($network);
            $validation = BlockchainExplorerFactory::validateConfiguration($network);
            
            $status = $validation['valid'] ? 
                ($healthScore >= 0.8 ? '🟢 Excellent' : 
                 ($healthScore >= 0.5 ? '🟡 Fair' : '🔴 Poor')) : 
                '⚫ Not Configured';

            $healthData[] = [
                $network,
                $validation['valid'] ? 'Yes' : 'No',
                number_format($healthScore, 3),
                $status,
                count($validation['issues'])
            ];
        }

        $this->table(
            ['Network', 'Configured', 'Health Score', 'Status', 'Issues'],
            $healthData
        );

        return Command::SUCCESS;
    }

    private function clearCaches(string $address): int
    {
        $this->info("🧹 Clearing caches for: {$address}");

        $detector = app(ChainDetectorService::class);
        $switchingService = app(SmartChainSwitchingService::class);

        $detectorCleared = $detector->clearDetectionCache($address);
        $switchingCleared = $switchingService->clearSwitchingCache($address);

        $this->line("Detection cache cleared: " . ($detectorCleared ? '✅' : '❌'));
        $this->line("Switching cache cleared: " . ($switchingCleared ? '✅' : '❌'));

        return Command::SUCCESS;
    }

    private function clearAllCaches(string $address): void
    {
        $this->line("Clearing all caches...");
        $this->clearCaches($address);
    }

    private function displayDetectionResults(array $results, float $detectionTime, bool $detailed): void
    {
        // Summary
        $this->line("<fg=green>Detection completed in {$detectionTime}ms</>");
        $this->line("Found on {$results['successful_checks']}/{$results['total_networks_checked']} networks");
        
        if (!empty($results['found_on'])) {
            $this->line("Networks: " . implode(', ', $results['found_on']));
        } else {
            $this->line("<fg=red>Contract not found on any network</>");
        }

        // Detailed results
        if ($detailed && !empty($results['detection_results'])) {
            $this->newLine();
            $this->line("<fg=cyan>Detailed Results:</>");
            
            $tableData = [];
            foreach ($results['detection_results'] as $network => $result) {
                $tableData[] = [
                    $network,
                    $result['exists'] ? '✅' : '❌',
                    $result['verified'] ? '✅' : '❌',
                    "{$result['response_time_ms']}ms",
                    $result['explorer_name']
                ];
            }
            
            $this->table(
                ['Network', 'Exists', 'Verified', 'Response Time', 'Explorer'],
                $tableData
            );
        }

        // Errors
        if (!empty($results['errors']) && $detailed) {
            $this->newLine();
            $this->line("<fg=red>Errors:</>");
            foreach ($results['errors'] as $network => $error) {
                $this->line("• {$network}: {$error}");
            }
        }
    }

    private function displaySwitchingResults(array $result, float $totalTime, bool $detailed): void
    {
        $this->line("<fg=green>Smart switching completed in {$totalTime}ms</>");
        $this->line("Network used: {$result['network_used']}");
        $this->line("Explorer used: {$result['explorer_used']}");
        $this->line("Attempts made: {$result['attempts_made']}");
        $this->line("Response time: {$result['response_time_ms']}ms");
        
        if ($result['switched_explorer']) {
            $this->line("<fg=yellow>⚠ Explorer was switched during operation</>");
        }

        if ($detailed && isset($result['result'])) {
            $this->newLine();
            $this->line("<fg=cyan>Contract Information:</>");
            
            $contractResult = $result['result'];
            $this->line("Contract Address: " . ($contractResult['contract_address'] ?? 'N/A'));
            $this->line("Verified: " . (($contractResult['is_verified'] ?? false) ? '✅' : '❌'));
            $this->line("Contract Name: " . ($contractResult['contract_name'] ?? 'N/A'));
            
            if (!empty($contractResult['source_code'])) {
                $sourceLength = strlen($contractResult['source_code']);
                $this->line("Source Code Length: {$sourceLength} characters");
            }
        }
    }

    private function displayVerificationResults(array $results, float $checkTime, bool $detailed): void
    {
        $this->line("<fg=green>Verification check completed in {$checkTime}ms</>");
        $this->line("Contract verified: " . ($results['is_verified'] ? '✅' : '❌'));
        
        if ($results['is_verified']) {
            $this->line("Verified on: " . implode(', ', $results['verified_networks']));
            $this->line("Fastest network: {$results['fastest_verified_network']}");
        }
        
        if (isset($results['recommendation'])) {
            $this->newLine();
            $this->line("<fg=cyan>Recommendation:</>");
            $this->line($results['recommendation']);
        }

        if ($detailed && !empty($results['verification_details'])) {
            $this->newLine();
            $this->line("<fg=cyan>Verification Details:</>");
            
            $tableData = [];
            foreach ($results['verification_details'] as $detail) {
                $tableData[] = [
                    $detail['network'],
                    "{$detail['response_time_ms']}ms",
                    $detail['explorer_name']
                ];
            }
            
            $this->table(
                ['Network', 'Response Time', 'Explorer'],
                $tableData
            );
        }
    }

    private function showUsage(): int
    {
        $this->line("<fg=cyan>Available Actions:</>");
        $this->line("• detect - Detect which networks a contract exists on");
        $this->line("• switch - Test smart switching functionality");
        $this->line("• verify - Check contract verification status");
        $this->line("• stats - Show system statistics");
        $this->line("• health - Show network health status");
        $this->line("• clear - Clear caches for a contract");
        
        $this->newLine();
        $this->line("<fg=cyan>Examples:</>");
        $this->line("php artisan chain:detect 0x... --action=detect --detailed");
        $this->line("php artisan chain:detect 0x... --action=switch --network=ethereum");
        $this->line("php artisan chain:detect 0x... --action=verify");
        $this->line("php artisan chain:detect 0x... --action=stats");

        return Command::SUCCESS;
    }
}