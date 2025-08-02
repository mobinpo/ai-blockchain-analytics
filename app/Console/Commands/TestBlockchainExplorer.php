<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\BlockchainExplorerService;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class TestBlockchainExplorer extends Command
{
    protected $signature = 'blockchain:test {network} {address} {--action=source}';
    
    protected $description = 'Test blockchain explorer API integration';

    public function handle(BlockchainExplorerService $explorerService): int
    {
        $network = $this->argument('network');
        $address = $this->argument('address');
        $action = $this->option('action');

        $this->info("Testing {$action} for contract {$address} on {$network}...");

        try {
            match ($action) {
                'source' => $this->testSourceCode($explorerService, $network, $address),
                'abi' => $this->testAbi($explorerService, $network, $address),
                'creation' => $this->testCreation($explorerService, $network, $address),
                'verified' => $this->testVerified($explorerService, $network, $address),
                'networks' => $this->testNetworks($explorerService),
                default => $this->error("Unknown action: {$action}")
            };

            return self::SUCCESS;

        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    private function testSourceCode(BlockchainExplorerService $service, string $network, string $address): void
    {
        $this->info('Fetching contract source code...');
        $result = $service->getContractSource($network, $address);
        
        $this->table(
            ['Property', 'Value'],
            [
                ['Network', $result['network']],
                ['Contract Name', $result['contract_name']],
                ['Compiler Version', $result['compiler_version']],
                ['Optimization', $result['optimization_used'] ? 'Yes' : 'No'],
                ['License', $result['license_type']],
                ['Is Verified', $result['is_verified'] ? 'Yes' : 'No'],
                ['Files Count', count($result['parsed_sources'])],
            ]
        );

        if ($result['is_verified']) {
            $this->info('Source files:');
            foreach ($result['parsed_sources'] as $filename => $content) {
                $lines = substr_count($content, "\n") + 1;
                $this->line("  • {$filename} ({$lines} lines)");
            }
        }
    }

    private function testAbi(BlockchainExplorerService $service, string $network, string $address): void
    {
        $this->info('Fetching contract ABI...');
        $result = $service->getContractAbi($network, $address);
        
        $functionCount = count($result['abi']);
        $this->info("ABI contains {$functionCount} functions/events");
        
        $functions = array_filter($result['abi'], fn($item) => $item['type'] === 'function');
        $events = array_filter($result['abi'], fn($item) => $item['type'] === 'event');
        
        $this->table(
            ['Type', 'Count'],
            [
                ['Functions', count($functions)],
                ['Events', count($events)],
                ['Total', $functionCount],
            ]
        );
    }

    private function testCreation(BlockchainExplorerService $service, string $network, string $address): void
    {
        $this->info('Fetching contract creation info...');
        $result = $service->getContractCreation($network, $address);
        
        $this->table(
            ['Property', 'Value'],
            [
                ['Creator', $result['creator_address']],
                ['Creation Tx', $result['creation_tx_hash']],
                ['Network', $result['network']],
            ]
        );
    }

    private function testVerified(BlockchainExplorerService $service, string $network, string $address): void
    {
        $this->info('Checking if contract is verified...');
        $isVerified = $service->isContractVerified($network, $address);
        
        $status = $isVerified ? '✅ VERIFIED' : '❌ NOT VERIFIED';
        $this->line("Status: {$status}");
    }

    private function testNetworks(BlockchainExplorerService $service): void
    {
        $this->info('Supported networks:');
        $networks = $service->getSupportedNetworks();
        
        $this->table(
            ['ID', 'Name', 'API URL'],
            array_map(fn($network) => [
                $network['id'],
                $network['name'],
                $network['api_url'],
            ], $networks)
        );
    }
}