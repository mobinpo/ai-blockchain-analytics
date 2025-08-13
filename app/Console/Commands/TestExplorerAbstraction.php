<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\BlockchainExplorerFactory;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class TestExplorerAbstraction extends Command
{
    protected $signature = 'explorer:test {network} {address} {--action=info}';
    
    protected $description = 'Test blockchain explorer abstraction layer';

    public function handle(): int
    {
        $network = $this->argument('network');
        $address = $this->argument('address');
        $action = $this->option('action');

        $this->info("Testing {$network} explorer with address {$address}...");

        try {
            match ($action) {
                'info' => $this->testExplorerInfo($network),
                'source' => $this->testContractSource($network, $address),
                'abi' => $this->testContractAbi($network, $address),
                'creation' => $this->testContractCreation($network, $address),
                'verified' => $this->testContractVerified($network, $address),
                'networks' => $this->testSupportedNetworks(),
                'config' => $this->testConfiguration($network),
                'prices' => $this->testPrices($network),
                default => $this->error("Unknown action: {$action}. Available: info|source|abi|creation|verified|networks|config|prices")
            };

            return self::SUCCESS;

        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    private function testExplorerInfo(string $network): void
    {
        $explorer = BlockchainExplorerFactory::create($network);
        
        $this->info("🔍 Explorer Information");
        $this->table(
            ['Property', 'Value'],
            [
                ['Explorer Name', $explorer->getName()],
                ['Network', $explorer->getNetwork()],
                ['API URL', $explorer->getApiUrl()],
                ['Is Configured', $explorer->isConfigured() ? '✅ Yes' : '❌ No'],
                ['Rate Limit', $explorer->getRateLimit() . ' req/sec'],
                ['Timeout', $explorer->getTimeout() . ' seconds'],
                ['Contract URL', $explorer->getContractUrl('0x0000000000000000000000000000000000000000')],
            ]
        );

        $endpoints = $explorer->getAvailableEndpoints();
        if (!empty($endpoints)) {
            $this->newLine();
            $this->info("📡 Available Endpoints:");
            foreach ($endpoints as $endpoint => $info) {
                $this->line("  • {$endpoint}: {$info['description']}");
            }
        }
    }

    private function testContractSource(string $network, string $address): void
    {
        $explorer = BlockchainExplorerFactory::create($network);
        
        $this->info("📄 Testing contract source retrieval...");
        $result = $explorer->getContractSource($address);
        
        $this->table(
            ['Property', 'Value'],
            [
                ['Contract Name', $result['contract_name']],
                ['Is Verified', $result['is_verified'] ? '✅ Yes' : '❌ No'],
                ['Compiler Version', $result['compiler_version']],
                ['Optimization Used', $result['optimization_used'] ? 'Yes' : 'No'],
                ['License Type', $result['license_type']],
                ['Source Files', count($result['parsed_sources'])],
                ['Explorer', $result['explorer']],
            ]
        );

        if ($result['is_verified'] && !empty($result['parsed_sources'])) {
            $this->newLine();
            $this->info("📁 Source Files:");
            foreach ($result['parsed_sources'] as $filename => $content) {
                $lines = substr_count($content, "\n") + 1;
                $this->line("  • {$filename} ({$lines} lines)");
            }
        }
    }

    private function testContractAbi(string $network, string $address): void
    {
        $explorer = BlockchainExplorerFactory::create($network);
        
        $this->info("🔧 Testing contract ABI retrieval...");
        $result = $explorer->getContractAbi($address);
        
        $functions = array_filter($result['abi'], fn($item) => $item['type'] === 'function');
        $events = array_filter($result['abi'], fn($item) => $item['type'] === 'event');
        
        $this->table(
            ['Type', 'Count'],
            [
                ['Functions', count($functions)],
                ['Events', count($events)],
                ['Total ABI Entries', count($result['abi'])],
            ]
        );

        $this->info("Explorer: {$result['explorer']}");
    }

    private function testContractCreation(string $network, string $address): void
    {
        $explorer = BlockchainExplorerFactory::create($network);
        
        $this->info("🏗️ Testing contract creation retrieval...");
        $result = $explorer->getContractCreation($address);
        
        $this->table(
            ['Property', 'Value'],
            [
                ['Creator Address', $result['creator_address']],
                ['Creation Tx Hash', $result['creation_tx_hash']],
                ['Network', $result['network']],
                ['Explorer', $result['explorer']],
            ]
        );
    }

    private function testContractVerified(string $network, string $address): void
    {
        $explorer = BlockchainExplorerFactory::create($network);
        
        $this->info("✅ Testing contract verification check...");
        $isVerified = $explorer->isContractVerified($address);
        
        $status = $isVerified ? '✅ VERIFIED' : '❌ NOT VERIFIED';
        $this->line("Status: {$status}");
        $this->line("Explorer: {$explorer->getName()}");
    }

    private function testSupportedNetworks(): void
    {
        $this->info("🌐 Supported Networks:");
        $networks = BlockchainExplorerFactory::getNetworkInfo();
        
        $tableData = [];
        foreach ($networks as $network => $info) {
            $tableData[] = [
                $network,
                $info['name'],
                $info['config_key'],
                $info['configured'] ? '✅ Yes' : '❌ No',
                $info['rate_limit'] . ' req/sec',
            ];
        }
        
        $this->table(
            ['Network ID', 'Name', 'Config Key', 'Configured', 'Rate Limit'],
            $tableData
        );
    }

    private function testConfiguration(string $network): void
    {
        $this->info("⚙️ Testing configuration for {$network}...");
        $validation = BlockchainExplorerFactory::validateConfiguration($network);
        
        $this->table(
            ['Property', 'Value'],
            [
                ['Valid', $validation['valid'] ? '✅ Yes' : '❌ No'],
                ['Config Key', $validation['config_key'] ?? 'N/A'],
            ]
        );

        if (!empty($validation['issues'])) {
            $this->newLine();
            $this->error("⚠️ Configuration Issues:");
            foreach ($validation['issues'] as $issue) {
                $this->line("  • {$issue}");
            }
        } else {
            $this->newLine();
            $this->info("✅ Configuration is valid!");
        }
    }

    private function testPrices(string $network): void
    {
        $explorer = BlockchainExplorerFactory::create($network);
        
        $this->info("💰 Testing price data for {$network}...");
        
        try {
            // Test native token price
            $priceMethod = match ($explorer->getName()) {
                'etherscan', 'arbiscan', 'optimistic_etherscan' => 'getEthPrice',
                'bscscan' => 'getBnbPrice',
                'polygonscan' => 'getMaticPrice',
                'snowtrace' => 'getAvaxPrice',
                'ftmscan' => 'getFtmPrice',
                default => null
            };

            if ($priceMethod && method_exists($explorer, $priceMethod)) {
                $prices = $explorer->{$priceMethod}();
                
                $this->table(
                    ['Token', 'USD Price', 'BTC Price'],
                    [[
                        strtoupper($explorer->getNetwork()),
                        '$' . number_format($prices[array_key_first(array_filter(array_keys($prices), fn($k) => str_ends_with($k, '_usd')))], 4),
                        number_format($prices[array_key_first(array_filter(array_keys($prices), fn($k) => str_ends_with($k, '_btc')))], 8) . ' BTC'
                    ]]
                );
            } else {
                $this->warn("Price data not available for {$network}");
            }

            // Test gas prices if available
            if (method_exists($explorer, 'getGasPrices')) {
                $gasPrices = $explorer->getGasPrices();
                
                $this->newLine();
                $this->info("⛽ Gas Prices:");
                $this->table(
                    ['Type', 'Price (Gwei)'],
                    [
                        ['Safe', $gasPrices['safe_gas_price']],
                        ['Standard', $gasPrices['standard_gas_price']],
                        ['Fast', $gasPrices['fast_gas_price']],
                    ]
                );
            }

        } catch (InvalidArgumentException $e) {
            $this->warn("Price data not available: {$e->getMessage()}");
        }
    }
}
