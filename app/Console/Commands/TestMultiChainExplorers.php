<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\BlockchainExplorerFactory;
use App\Services\SourceCodeFetchingService;
use Illuminate\Console\Command;

class TestMultiChainExplorers extends Command
{
    protected $signature = 'blockchain:test-explorers 
                            {--network=* : Specific networks to test (default: all)}
                            {--basic : Run basic explorer tests only}
                            {--source : Test source code fetching}
                            {--prices : Test price fetching}
                            {--gas : Test gas oracle}';

    protected $description = 'Test multi-chain blockchain explorer abstraction layer';

    public function __construct(
        private readonly BlockchainExplorerFactory $explorerFactory,
        private readonly SourceCodeFetchingService $sourceService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('🚀 Testing Multi-Chain Blockchain Explorer Abstraction Layer');
        $this->newLine();

        $networks = $this->option('network') ?: $this->getAllSupportedNetworks();
        $testResults = [];

        foreach ($networks as $network) {
            $this->info("🔗 Testing {$network} explorer...");
            $testResults[$network] = $this->testNetworkExplorer($network);
            $this->newLine();
        }

        $this->displayResults($testResults);

        return $this->hasFailures($testResults) ? 1 : 0;
    }

    private function getAllSupportedNetworks(): array
    {
        return ['ethereum', 'bsc', 'polygon', 'arbitrum', 'optimism', 'avalanche', 'fantom'];
    }

    private function testNetworkExplorer(string $network): array
    {
        $results = [
            'explorer_creation' => false,
            'basic_info' => false,
            'source_code' => false,
            'price_data' => false,
            'gas_oracle' => false,
            'errors' => []
        ];

        try {
            $explorer = $this->explorerFactory->createExplorer($network);
            $results['explorer_creation'] = true;
            $this->line("  ✅ Explorer created: {$explorer->getName()}");

            if ($this->option('basic') || !$this->hasSpecificOptions()) {
                $results['basic_info'] = $this->testBasicInfo($explorer);
            }

            if ($this->option('source') || !$this->hasSpecificOptions()) {
                $results['source_code'] = $this->testSourceCodeFetching($network);
            }

            if ($this->option('prices') || !$this->hasSpecificOptions()) {
                $results['price_data'] = $this->testPriceData($explorer, $network);
            }

            if ($this->option('gas') || !$this->hasSpecificOptions()) {
                $results['gas_oracle'] = $this->testGasOracle($explorer);
            }

        } catch (\Exception $e) {
            $results['errors'][] = "Explorer creation failed: {$e->getMessage()}";
            $this->error("  ❌ Failed to create explorer: {$e->getMessage()}");
        }

        return $results;
    }

    private function testBasicInfo($explorer): bool
    {
        try {
            $name = $explorer->getName();
            $network = $explorer->getNetwork();
            $endpoints = $explorer->getAvailableEndpoints();

            $this->line("  ✅ Name: {$name}");
            $this->line("  ✅ Network: {$network}");
            $this->line("  ✅ Available endpoints: " . count($endpoints));

            return true;
        } catch (\Exception $e) {
            $this->error("  ❌ Basic info test failed: {$e->getMessage()}");
            return false;
        }
    }

    private function testSourceCodeFetching(string $network): bool
    {
        $testContracts = $this->getTestContracts();
        
        if (!isset($testContracts[$network])) {
            $this->line("  ⚠️  No test contract available for {$network}");
            return true;
        }

        try {
            $contractAddress = $testContracts[$network];
            $sourceData = $this->sourceService->fetchSourceCode($contractAddress, $network);

            if (!empty($sourceData['contracts'])) {
                $this->line("  ✅ Source code fetched: " . count($sourceData['contracts']) . " contracts");
                return true;
            } else {
                $this->error("  ❌ No source code data returned");
                return false;
            }
        } catch (\Exception $e) {
            $this->error("  ❌ Source code test failed: {$e->getMessage()}");
            return false;
        }
    }

    private function testPriceData($explorer, string $network): bool
    {
        $priceMethods = $this->getPriceMethodsForNetwork($network);
        
        if (empty($priceMethods)) {
            $this->line("  ⚠️  No price methods available for {$network}");
            return true;
        }

        foreach ($priceMethods as $method) {
            try {
                if (method_exists($explorer, $method)) {
                    $priceData = $explorer->$method();
                    $this->line("  ✅ Price data ({$method}): " . json_encode(array_slice($priceData, 0, 2)));
                } else {
                    $this->line("  ⚠️  Method {$method} not implemented");
                }
            } catch (\Exception $e) {
                $this->error("  ❌ Price test ({$method}) failed: {$e->getMessage()}");
                return false;
            }
        }

        return true;
    }

    private function testGasOracle($explorer): bool
    {
        if (!method_exists($explorer, 'getGasPrices')) {
            $this->line("  ⚠️  Gas oracle not available");
            return true;
        }

        try {
            $gasData = $explorer->getGasPrices();
            $this->line("  ✅ Gas prices: " . json_encode(array_slice($gasData, 0, 3)));
            return true;
        } catch (\Exception $e) {
            $this->error("  ❌ Gas oracle test failed: {$e->getMessage()}");
            return false;
        }
    }

    private function getTestContracts(): array
    {
        return [
            'ethereum' => '0xA0b86a33E6441d7F92E86b9a1Ba5bC83eE9D2b02',
            'bsc' => '0x1Af3F329e8BE154074D8769D1FFa4eE058B1DBc3',
            'polygon' => '0x2791Bca1f2de4661ED88A30C99A7a9449Aa84174',
            'arbitrum' => '0xFF970A61A04b1cA14834A43f5dE4533eBDDB5CC8',
            'optimism' => '0x7F5c764cBc14f9669B88837ca1490cCa17c31607',
            'avalanche' => '0x9702230A8Ea53601f5cD2dc00fDBc13d4dF4A8c7',
            'fantom' => '0x04068DA6C83AFCFA0e13ba15A6696662335D5B75'
        ];
    }

    private function getPriceMethodsForNetwork(string $network): array
    {
        return match ($network) {
            'ethereum' => ['getEthPrice'],
            'bsc' => ['getBnbPrice'],
            'polygon' => ['getMaticPrice'],
            'arbitrum', 'optimism' => ['getEthPrice'],
            'avalanche' => ['getAvaxPrice'],
            'fantom' => ['getFtmPrice'],
            default => []
        };
    }

    private function hasSpecificOptions(): bool
    {
        return $this->option('basic') || $this->option('source') || 
               $this->option('prices') || $this->option('gas');
    }

    private function displayResults(array $testResults): void
    {
        $this->newLine();
        $this->info('📊 Test Results Summary');
        $this->table(
            ['Network', 'Explorer', 'Basic', 'Source', 'Prices', 'Gas', 'Errors'],
            collect($testResults)->map(function ($result, $network) {
                return [
                    $network,
                    $result['explorer_creation'] ? '✅' : '❌',
                    $result['basic_info'] ? '✅' : '❌',
                    $result['source_code'] ? '✅' : '❌',
                    $result['price_data'] ? '✅' : '❌',
                    $result['gas_oracle'] ? '✅' : '❌',
                    count($result['errors']),
                ];
            })->toArray()
        );

        $totalNetworks = count($testResults);
        $successfulNetworks = collect($testResults)->filter(function ($result) {
            return $result['explorer_creation'] && empty($result['errors']);
        })->count();

        $this->info("🎯 Success Rate: {$successfulNetworks}/{$totalNetworks} networks");
    }

    private function hasFailures(array $testResults): bool
    {
        return collect($testResults)->contains(function ($result) {
            return !$result['explorer_creation'] || !empty($result['errors']);
        });
    }
}