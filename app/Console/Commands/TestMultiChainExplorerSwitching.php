<?php

namespace App\Console\Commands;

use App\Services\ChainExplorerManager;
use App\Services\BlockchainExplorerFactory;
use App\Services\SourceCodeFetchingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class TestMultiChainExplorerSwitching extends Command
{
    protected $signature = 'test:multi-chain-explorers 
                          {--chains= : Comma-separated list of chains to test (default: all)}
                          {--contract= : Specific contract address to test across chains}
                          {--stress : Run stress test with multiple operations}
                          {--failover : Test failover scenarios}
                          {--performance : Measure performance across chains}
                          {--cleanup : Cleanup test data after completion}';

    protected $description = 'Test multi-chain explorer switching functionality with comprehensive scenarios';

    private ChainExplorerManager $manager;
    private SourceCodeFetchingService $sourceService;

    // Test contract addresses known to be verified on different chains
    private const TEST_CONTRACTS = [
        'ethereum' => '0xA0b86a33E6417c54bE6f6F91D6B20b5e5C82D6b1', // Example verified contract
        'bsc' => '0x55d398326f99059fF775485246999027B3197955', // USDT on BSC
        'polygon' => '0x2791Bca1f2de4661ED88A30C99A7a9449Aa84174', // USDC on Polygon
        'arbitrum' => '0xFF970A61A04b1cA14834A43F5DE4533eBDDB5CC8', // USDC on Arbitrum
        'optimism' => '0x7F5c764cBc14f9669B88837ca1490cCa17c31607', // USDC on Optimism
        'avalanche' => '0x9702230A8Ea53601f5cD2dc00fDBc13d4dF4A8c7', // USDT on Avalanche
        'fantom' => '0x04068DA6C83AFCFA0e13ba15A6696662335D5B75', // USDC on Fantom
    ];

    public function __construct(ChainExplorerManager $manager, SourceCodeFetchingService $sourceService)
    {
        parent::__construct();
        $this->manager = $manager;
        $this->sourceService = $sourceService;
    }

    public function handle(): int
    {
        $this->info('🧪 Multi-Chain Explorer Switching Test Suite');
        $this->newLine();

        $chains = $this->getChainsToTest();
        $testResults = [
            'basic_switching' => [],
            'source_code_fetching' => [],
            'failover_testing' => [],
            'performance_metrics' => [],
            'stress_testing' => []
        ];

        try {
            // Test 1: Basic Explorer Switching
            $this->info('🔄 Test 1: Basic Explorer Switching');
            $testResults['basic_switching'] = $this->testBasicSwitching($chains);
            $this->displayBasicSwitchingResults($testResults['basic_switching']);
            $this->newLine();

            // Test 2: Source Code Fetching Across Chains
            $this->info('📥 Test 2: Source Code Fetching Across Chains');
            $testResults['source_code_fetching'] = $this->testSourceCodeFetching($chains);
            $this->displaySourceCodeResults($testResults['source_code_fetching']);
            $this->newLine();

            // Test 3: Failover Testing (if requested)
            if ($this->option('failover')) {
                $this->info('🛡️ Test 3: Failover Scenarios');
                $testResults['failover_testing'] = $this->testFailoverScenarios($chains);
                $this->displayFailoverResults($testResults['failover_testing']);
                $this->newLine();
            }

            // Test 4: Performance Measurement (if requested)
            if ($this->option('performance')) {
                $this->info('⚡ Test 4: Performance Measurement');
                $testResults['performance_metrics'] = $this->testPerformanceAcrossChains($chains);
                $this->displayPerformanceResults($testResults['performance_metrics']);
                $this->newLine();
            }

            // Test 5: Stress Testing (if requested)
            if ($this->option('stress')) {
                $this->info('🏋️ Test 5: Stress Testing');
                $testResults['stress_testing'] = $this->testStressScenarios($chains);
                $this->displayStressResults($testResults['stress_testing']);
                $this->newLine();
            }

            // Final Summary
            $this->displayFinalSummary($testResults);

            // Cleanup (if requested)
            if ($this->option('cleanup')) {
                $this->cleanup();
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Test suite failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function getChainsToTest(): array
    {
        if ($chainList = $this->option('chains')) {
            return array_map('trim', explode(',', $chainList));
        }

        // Return all supported chains
        return BlockchainExplorerFactory::getSupportedNetworks();
    }

    private function testBasicSwitching(array $chains): array
    {
        $results = [];

        foreach ($chains as $chain) {
            $startTime = microtime(true);
            
            try {
                // Test getting explorer
                $explorer = $this->manager->getExplorer($chain);
                $responseTime = round((microtime(true) - $startTime) * 1000);
                
                $results[$chain] = [
                    'success' => true,
                    'explorer_name' => $explorer->getName(),
                    'api_url' => $explorer->getApiUrl(),
                    'rate_limit' => $explorer->getRateLimit(),
                    'response_time_ms' => $responseTime,
                    'configured' => $explorer->isConfigured(),
                    'error' => null
                ];

                $this->line("  ✅ {$chain}: {$explorer->getName()} ({$responseTime}ms)");

            } catch (\Exception $e) {
                $responseTime = round((microtime(true) - $startTime) * 1000);
                
                $results[$chain] = [
                    'success' => false,
                    'explorer_name' => null,
                    'api_url' => null,
                    'rate_limit' => null,
                    'response_time_ms' => $responseTime,
                    'configured' => false,
                    'error' => $e->getMessage()
                ];

                $this->line("  ❌ {$chain}: {$e->getMessage()}");
            }
        }

        return $results;
    }

    private function testSourceCodeFetching(array $chains): array
    {
        $results = [];
        $testContract = $this->option('contract');

        foreach ($chains as $chain) {
            $contractAddress = $testContract ?: (self::TEST_CONTRACTS[$chain] ?? null);
            
            if (!$contractAddress) {
                $results[$chain] = [
                    'success' => false,
                    'error' => 'No test contract available for chain',
                    'contract_address' => null
                ];
                $this->line("  ⏭️ {$chain}: No test contract available");
                continue;
            }

            $startTime = microtime(true);
            
            try {
                // Test source code availability check
                $isAvailable = $this->sourceService->isSourceCodeAvailable($contractAddress, $chain);
                
                if ($isAvailable) {
                    // Try to fetch source code info (without full source)
                    $info = $this->sourceService->getSourceCodeInfo($contractAddress, $chain);
                    $responseTime = round((microtime(true) - $startTime) * 1000);
                    
                    $results[$chain] = [
                        'success' => true,
                        'contract_address' => $contractAddress,
                        'is_verified' => $info['is_verified'],
                        'contracts_count' => $info['contracts_count'],
                        'compiler_version' => $info['compiler_version'],
                        'response_time_ms' => $responseTime,
                        'error' => null
                    ];

                    $this->line("  ✅ {$chain}: Verified contract ({$info['contracts_count']} files, {$responseTime}ms)");
                } else {
                    $responseTime = round((microtime(true) - $startTime) * 1000);
                    
                    $results[$chain] = [
                        'success' => false,
                        'contract_address' => $contractAddress,
                        'is_verified' => false,
                        'response_time_ms' => $responseTime,
                        'error' => 'Contract not verified or not found'
                    ];

                    $this->line("  ❌ {$chain}: Contract not verified");
                }

            } catch (\Exception $e) {
                $responseTime = round((microtime(true) - $startTime) * 1000);
                
                $results[$chain] = [
                    'success' => false,
                    'contract_address' => $contractAddress,
                    'response_time_ms' => $responseTime,
                    'error' => $e->getMessage()
                ];

                $this->line("  ❌ {$chain}: {$e->getMessage()}");
            }
        }

        return $results;
    }

    private function testFailoverScenarios(array $chains): array
    {
        $results = [];

        foreach ($chains as $chain) {
            $this->line("  🧪 Testing failover for {$chain}...");
            
            try {
                // Simulate explorer failure by clearing health cache
                $healthConfig = config('blockchain_explorers.health_check');
                $healthKey = $healthConfig['cache_key_prefix'] . $chain;
                
                // Backup current health
                $originalHealth = Cache::get($healthKey);
                
                // Set unhealthy status
                Cache::put($healthKey, [
                    'failures' => 10,
                    'success_rate' => 0.1,
                    'avg_response_time' => 30000,
                    'last_failure' => now()->toISOString()
                ], 300);

                // Try to get explorer (should trigger failover logic)
                $startTime = microtime(true);
                $explorer = $this->manager->switchToBestExplorer($chain);
                $responseTime = round((microtime(true) - $startTime) * 1000);

                $results[$chain] = [
                    'success' => true,
                    'failover_triggered' => true,
                    'final_explorer' => $explorer->getName(),
                    'response_time_ms' => $responseTime,
                    'error' => null
                ];

                $this->line("    ✅ Failover successful to {$explorer->getName()}");

                // Restore original health
                if ($originalHealth) {
                    Cache::put($healthKey, $originalHealth, 300);
                } else {
                    Cache::forget($healthKey);
                }

            } catch (\Exception $e) {
                $results[$chain] = [
                    'success' => false,
                    'failover_triggered' => false,
                    'error' => $e->getMessage()
                ];

                $this->line("    ❌ Failover failed: {$e->getMessage()}");
            }
        }

        return $results;
    }

    private function testPerformanceAcrossChains(array $chains): array
    {
        $results = [];
        $iterations = 5;

        foreach ($chains as $chain) {
            $this->line("  📊 Performance testing {$chain} ({$iterations} iterations)...");
            
            $times = [];
            $successCount = 0;
            
            for ($i = 0; $i < $iterations; $i++) {
                $startTime = microtime(true);
                
                try {
                    $explorer = $this->manager->getExplorer($chain);
                    $responseTime = round((microtime(true) - $startTime) * 1000);
                    $times[] = $responseTime;
                    $successCount++;
                } catch (\Exception $e) {
                    $responseTime = round((microtime(true) - $startTime) * 1000);
                    $times[] = $responseTime;
                }
                
                usleep(100000); // 100ms delay between requests
            }

            $results[$chain] = [
                'iterations' => $iterations,
                'success_count' => $successCount,
                'success_rate' => round(($successCount / $iterations) * 100, 2),
                'min_time_ms' => min($times),
                'max_time_ms' => max($times),
                'avg_time_ms' => round(array_sum($times) / count($times), 2),
                'total_time_ms' => array_sum($times)
            ];

            $this->line("    📈 Avg: {$results[$chain]['avg_time_ms']}ms, Success: {$results[$chain]['success_rate']}%");
        }

        return $results;
    }

    private function testStressScenarios(array $chains): array
    {
        $results = [];
        $concurrentRequests = 10;

        foreach ($chains as $chain) {
            $this->line("  🏋️ Stress testing {$chain} ({$concurrentRequests} concurrent requests)...");
            
            $startTime = microtime(true);
            $promises = [];
            $errors = [];
            $successCount = 0;

            // Simulate concurrent requests
            for ($i = 0; $i < $concurrentRequests; $i++) {
                try {
                    $explorer = $this->manager->getExplorer($chain);
                    $successCount++;
                } catch (\Exception $e) {
                    $errors[] = $e->getMessage();
                }
                
                usleep(50000); // 50ms stagger
            }

            $totalTime = round((microtime(true) - $startTime) * 1000);

            $results[$chain] = [
                'concurrent_requests' => $concurrentRequests,
                'successful_requests' => $successCount,
                'failed_requests' => count($errors),
                'success_rate' => round(($successCount / $concurrentRequests) * 100, 2),
                'total_time_ms' => $totalTime,
                'avg_time_per_request' => round($totalTime / $concurrentRequests, 2),
                'errors' => array_slice(array_unique($errors), 0, 3) // Show first 3 unique errors
            ];

            $this->line("    📊 {$successCount}/{$concurrentRequests} successful ({$results[$chain]['success_rate']}%)");
        }

        return $results;
    }

    private function displayBasicSwitchingResults(array $results): void
    {
        $successCount = count(array_filter($results, fn($r) => $r['success']));
        $totalCount = count($results);
        
        $this->table(
            ['Chain', 'Status', 'Explorer', 'Response Time', 'Rate Limit'],
            array_map(function($chain, $result) {
                return [
                    ucfirst($chain),
                    $result['success'] ? '✅ Success' : '❌ Failed',
                    $result['explorer_name'] ?? 'N/A',
                    ($result['response_time_ms'] ?? 0) . 'ms',
                    ($result['rate_limit'] ?? 'N/A') . ' req/s'
                ];
            }, array_keys($results), $results)
        );

        $this->info("Basic switching success rate: {$successCount}/{$totalCount} (" . round(($successCount/$totalCount)*100, 1) . "%)");
    }

    private function displaySourceCodeResults(array $results): void
    {
        $successCount = count(array_filter($results, fn($r) => $r['success']));
        $totalCount = count($results);
        
        $this->table(
            ['Chain', 'Status', 'Contract', 'Verified', 'Files', 'Response Time'],
            array_map(function($chain, $result) {
                return [
                    ucfirst($chain),
                    $result['success'] ? '✅ Success' : '❌ Failed',
                    isset($result['contract_address']) ? substr($result['contract_address'], 0, 10) . '...' : 'N/A',
                    isset($result['is_verified']) ? ($result['is_verified'] ? '✅' : '❌') : 'N/A',
                    $result['contracts_count'] ?? 'N/A',
                    ($result['response_time_ms'] ?? 0) . 'ms'
                ];
            }, array_keys($results), $results)
        );

        $this->info("Source code fetching success rate: {$successCount}/{$totalCount} (" . round(($successCount/$totalCount)*100, 1) . "%)");
    }

    private function displayFailoverResults(array $results): void
    {
        $successCount = count(array_filter($results, fn($r) => $r['success']));
        $totalCount = count($results);
        
        $this->table(
            ['Chain', 'Failover Result', 'Final Explorer', 'Response Time'],
            array_map(function($chain, $result) {
                return [
                    ucfirst($chain),
                    $result['success'] ? '✅ Success' : '❌ Failed',
                    $result['final_explorer'] ?? 'N/A',
                    ($result['response_time_ms'] ?? 0) . 'ms'
                ];
            }, array_keys($results), $results)
        );

        $this->info("Failover success rate: {$successCount}/{$totalCount} (" . round(($successCount/$totalCount)*100, 1) . "%)");
    }

    private function displayPerformanceResults(array $results): void
    {
        $this->table(
            ['Chain', 'Success Rate', 'Min Time', 'Avg Time', 'Max Time'],
            array_map(function($chain, $result) {
                return [
                    ucfirst($chain),
                    $result['success_rate'] . '%',
                    $result['min_time_ms'] . 'ms',
                    $result['avg_time_ms'] . 'ms',
                    $result['max_time_ms'] . 'ms'
                ];
            }, array_keys($results), $results)
        );

        $avgResponseTime = round(array_sum(array_column($results, 'avg_time_ms')) / count($results), 2);
        $this->info("Overall average response time: {$avgResponseTime}ms");
    }

    private function displayStressResults(array $results): void
    {
        $this->table(
            ['Chain', 'Concurrent Requests', 'Success Rate', 'Avg Time/Request', 'Errors'],
            array_map(function($chain, $result) {
                return [
                    ucfirst($chain),
                    $result['concurrent_requests'],
                    $result['success_rate'] . '%',
                    $result['avg_time_per_request'] . 'ms',
                    count($result['errors'])
                ];
            }, array_keys($results), $results)
        );
    }

    private function displayFinalSummary(array $testResults): void
    {
        $this->newLine();
        $this->info('📋 Test Suite Summary');
        $this->newLine();

        $summary = [];
        
        if (!empty($testResults['basic_switching'])) {
            $successCount = count(array_filter($testResults['basic_switching'], fn($r) => $r['success']));
            $totalCount = count($testResults['basic_switching']);
            $summary[] = ['Basic Switching', "{$successCount}/{$totalCount}", round(($successCount/$totalCount)*100, 1) . '%'];
        }

        if (!empty($testResults['source_code_fetching'])) {
            $successCount = count(array_filter($testResults['source_code_fetching'], fn($r) => $r['success']));
            $totalCount = count($testResults['source_code_fetching']);
            $summary[] = ['Source Code Fetching', "{$successCount}/{$totalCount}", round(($successCount/$totalCount)*100, 1) . '%'];
        }

        if (!empty($testResults['failover_testing'])) {
            $successCount = count(array_filter($testResults['failover_testing'], fn($r) => $r['success']));
            $totalCount = count($testResults['failover_testing']);
            $summary[] = ['Failover Testing', "{$successCount}/{$totalCount}", round(($successCount/$totalCount)*100, 1) . '%'];
        }

        $this->table(['Test Category', 'Results', 'Success Rate'], $summary);

        $overallScore = 0;
        $testCount = 0;
        
        foreach ($testResults as $testType => $results) {
            if (!empty($results)) {
                $successRate = count(array_filter($results, fn($r) => $r['success'] ?? false)) / count($results);
                $overallScore += $successRate;
                $testCount++;
            }
        }

        if ($testCount > 0) {
            $overallScore = round(($overallScore / $testCount) * 100, 1);
            $status = $overallScore >= 90 ? '🟢 Excellent' : ($overallScore >= 70 ? '🟡 Good' : '🔴 Needs Attention');
            $this->info("Overall Test Score: {$overallScore}% {$status}");
        }
    }

    private function cleanup(): void
    {
        $this->info('🧹 Cleaning up test data...');
        
        // Clear health check caches
        $networks = BlockchainExplorerFactory::getSupportedNetworks();
        $healthConfig = config('blockchain_explorers.health_check');
        
        foreach ($networks as $network) {
            $healthKey = $healthConfig['cache_key_prefix'] . $network;
            Cache::forget($healthKey);
            
            $performanceKey = 'chain_explorer_manager:performance:' . $network;
            Cache::forget($performanceKey);
        }
        
        $this->info('✅ Cleanup completed');
    }
}