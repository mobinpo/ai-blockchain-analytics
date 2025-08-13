<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SourceCodeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestSourceCodeFetching extends Command
{
    protected $signature = 'test:source-code 
                            {--contract= : Contract address to test}
                            {--network= : Network to test (ethereum, bsc, polygon, etc.)}
                            {--demo : Run with demo contracts}
                            {--batch : Test batch fetching}
                            {--pattern= : Search for a pattern in source code}';

    protected $description = 'Test source code fetching from blockchain explorers';

    public function __construct(
        private readonly SourceCodeService $sourceCodeService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('🔍 Testing Source Code Fetching Service');
        $this->newLine();

        if ($this->option('demo')) {
            return $this->runDemoTests();
        }

        if ($this->option('batch')) {
            return $this->testBatchFetching();
        }

        if ($this->option('pattern')) {
            return $this->testPatternSearch();
        }

        $contractAddress = $this->option('contract');
        $network = $this->option('network');

        if (!$contractAddress) {
            $contractAddress = $this->ask('Enter contract address (0x...)', '0xdAC17F958D2ee523a2206206994597C13D831ec7');
        }

        if (!$network) {
            $network = $this->choice(
                'Select network (leave empty for auto-detection)',
                ['auto-detect', 'ethereum', 'bsc', 'polygon', 'arbitrum', 'optimism', 'avalanche'],
                'auto-detect'
            );
        }

        if ($network === 'auto-detect') {
            $network = null;
        }

        return $this->testSingleContract($contractAddress, $network);
    }

    private function testSingleContract(string $contractAddress, ?string $network): int
    {
        $this->info("Testing contract: {$contractAddress}");
        if ($network) {
            $this->info("Network: {$network}");
        } else {
            $this->info("Network: Auto-detection enabled");
        }
        $this->newLine();

        try {
            // Test verification status
            $this->info('1. Checking verification status...');
            $verification = $this->sourceCodeService->isContractVerified($contractAddress, $network);
            
            if (is_array($verification) && isset($verification['verification_status'])) {
                // Multi-network response
                foreach ($verification['verification_status'] as $status) {
                    $icon = $status['is_verified'] ? '✅' : '❌';
                    $this->line("   {$icon} {$status['network']}: " . ($status['is_verified'] ? 'Verified' : 'Not verified'));
                }
                $hasVerified = $verification['has_verified_contract'];
            } else {
                // Single network response
                $hasVerified = $verification['is_verified'] ?? false;
                $icon = $hasVerified ? '✅' : '❌';
                $networkName = $verification['network'] ?? 'Unknown';
                $this->line("   {$icon} {$networkName}: " . ($hasVerified ? 'Verified' : 'Not verified'));
            }

            if (!$hasVerified) {
                $this->warn('⚠️  Contract is not verified. Source code fetching may fail.');
                return Command::SUCCESS;
            }

            $this->newLine();

            // Test source code fetching
            $this->info('2. Fetching source code...');
            $sourceCode = $this->sourceCodeService->fetchSourceCode($contractAddress, $network);
            
            $this->line("   📝 Contract Name: {$sourceCode['contract_name']}");
            $this->line("   🔗 Network: {$sourceCode['network']}");
            $this->line("   💻 Compiler: {$sourceCode['compiler_version']}");
            $this->line("   ⚡ Optimization: " . ($sourceCode['optimization_used'] ? 'Enabled' : 'Disabled'));
            $this->line("   📄 Source Files: " . count($sourceCode['parsed_sources']));
            $this->line("   📜 License: {$sourceCode['license_type']}");
            $this->line("   🔗 Proxy Contract: " . ($sourceCode['proxy'] ? 'Yes' : 'No'));
            
            $this->newLine();

            // Test function extraction
            $this->info('3. Extracting function signatures...');
            $functions = $this->sourceCodeService->extractFunctionSignatures($contractAddress, $network);
            
            $this->line("   🔧 Total Functions: {$functions['total_functions']}");
            if ($functions['total_functions'] > 0) {
                $this->line("   First 5 functions:");
                foreach (array_slice($functions['functions'], 0, 5) as $func) {
                    $this->line("     • " . trim($func));
                }
                if ($functions['total_functions'] > 5) {
                    $this->line("     ... and " . ($functions['total_functions'] - 5) . " more");
                }
            }
            
            $this->newLine();

            // Test comprehensive info
            $this->info('4. Getting comprehensive contract info...');
            $info = $this->sourceCodeService->getContractInfo($contractAddress, $network);
            
            if (!isset($info['error'])) {
                $this->line("   📊 Source Stats:");
                $this->line("     • Total Lines: {$info['source_info']['total_lines']}");
                $this->line("     • Source Files: {$info['source_info']['source_files']}");
                $this->line("     • Has Libraries: " . ($info['source_info']['has_libraries'] ? 'Yes' : 'No'));
                
                $this->line("   👤 Creation Info:");
                $this->line("     • Creator: {$info['creation_info']['creator_address']}");
                $this->line("     • TX Hash: {$info['creation_info']['creation_tx_hash']}");
                
                $this->line("   🔧 Function Stats:");
                $this->line("     • Total: {$info['function_info']['total_functions']}");
                $this->line("     • Public: {$info['function_info']['public_functions']}");
                $this->line("     • External: {$info['function_info']['external_functions']}");
            }

            $this->newLine();
            $this->info('✅ All tests completed successfully!');

            // Offer to show source code
            if ($this->confirm('Would you like to see a preview of the source code?', false)) {
                $this->showSourceCodePreview($sourceCode);
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
            Log::error('Source code fetching test failed', [
                'contract' => $contractAddress,
                'network' => $network,
                'error' => $e->getMessage(),
            ]);
            return Command::FAILURE;
        }
    }

    private function runDemoTests(): int
    {
        $this->info('🎯 Running demo tests with popular verified contracts...');
        $this->newLine();

        $demoContracts = [
            // USDT on Ethereum
            [
                'address' => '0xdAC17F958D2ee523a2206206994597C13D831ec7',
                'network' => 'ethereum',
                'name' => 'USDT (Ethereum)',
            ],
            // USDC on Ethereum  
            [
                'address' => '0xA0b86a33E6411c4e212648bc91934b8e09e83A5f',
                'network' => 'ethereum', 
                'name' => 'USDC (Ethereum)',
            ],
            // PancakeSwap Router on BSC
            [
                'address' => '0x10ED43C718714eb63d5aA57B78B54704E256024E',
                'network' => 'bsc',
                'name' => 'PancakeSwap Router (BSC)',
            ],
        ];

        $successful = 0;
        $total = count($demoContracts);

        foreach ($demoContracts as $contract) {
            $this->info("Testing: {$contract['name']}");
            $this->line("Address: {$contract['address']}");
            $this->line("Network: {$contract['network']}");
            
            try {
                $sourceCode = $this->sourceCodeService->fetchSourceCode(
                    $contract['address'],
                    $contract['network']
                );

                $this->line("✅ Success - {$sourceCode['contract_name']} ({$sourceCode['compiler_version']})");
                $successful++;

            } catch (\Exception $e) {
                $this->line("❌ Failed - {$e->getMessage()}");
            }
            
            $this->newLine();
        }

        $this->info("Demo Test Results: {$successful}/{$total} contracts fetched successfully");
        
        return $successful === $total ? Command::SUCCESS : Command::FAILURE;
    }

    private function testBatchFetching(): int
    {
        $this->info('📦 Testing batch source code fetching...');
        $this->newLine();

        $contracts = [
            ['address' => '0xdAC17F958D2ee523a2206206994597C13D831ec7', 'network' => 'ethereum'],
            ['address' => '0xA0b86a33E6411c4e212648bc91934b8e09e83A5f', 'network' => 'ethereum'],
            ['address' => '0x10ED43C718714eb63d5aA57B78B54704E256024E', 'network' => 'bsc'],
        ];

        $results = [];
        $errors = [];

        foreach ($contracts as $index => $contract) {
            try {
                $this->line("Fetching {$contract['address']} on {$contract['network']}...");
                
                $sourceCode = $this->sourceCodeService->fetchSourceCode(
                    $contract['address'],
                    $contract['network'] ?? null
                );

                $results[] = [
                    'index' => $index,
                    'address' => $contract['address'],
                    'success' => true,
                    'name' => $sourceCode['contract_name'],
                    'network' => $sourceCode['network'],
                ];

            } catch (\Exception $e) {
                $errors[] = [
                    'index' => $index,
                    'address' => $contract['address'],
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $this->newLine();
        $this->info("Batch Results:");
        $this->line("✅ Successful: " . count($results));
        $this->line("❌ Failed: " . count($errors));

        foreach ($results as $result) {
            $this->line("  • {$result['name']} ({$result['network']})");
        }

        foreach ($errors as $error) {
            $this->line("  ❌ {$error['address']}: {$error['error']}");
        }

        return count($errors) === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private function testPatternSearch(): int
    {
        $pattern = $this->option('pattern') ?: $this->ask('Enter pattern to search for', 'transfer');
        
        $this->info("🔍 Testing pattern search for: {$pattern}");
        $this->newLine();

        $addresses = [
            '0xdAC17F958D2ee523a2206206994597C13D831ec7', // USDT
            '0xA0b86a33E6411c4e212648bc91934b8e09e83A5f', // USDC
        ];

        try {
            $results = $this->sourceCodeService->searchBySourcePattern($addresses, $pattern, 'ethereum');
            
            $this->line("Search Results:");
            $this->line("Pattern: {$results['pattern']}");
            $this->line("Contracts checked: {$results['total_checked']}");
            $this->line("Matches found: {$results['matches_found']}");
            $this->newLine();

            foreach ($results['results'] as $result) {
                $this->line("✅ {$result['contract_name']} ({$result['address']})");
                $this->line("   Network: {$result['network']}");
                $this->line("   Match count: " . count($result['matches']));
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Pattern search failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function showSourceCodePreview(array $sourceCode): void
    {
        $this->newLine();
        $this->info('📄 Source Code Preview:');
        $this->newLine();

        foreach ($sourceCode['parsed_sources'] as $filename => $source) {
            $this->line("📁 {$filename}");
            $this->line(str_repeat('-', 50));
            
            // Show first 20 lines
            $lines = explode("\n", $source);
            $preview = array_slice($lines, 0, 20);
            
            foreach ($preview as $lineNum => $line) {
                $this->line(sprintf('%3d: %s', $lineNum + 1, $line));
            }
            
            if (count($lines) > 20) {
                $this->line('... (' . (count($lines) - 20) . ' more lines)');
            }
            
            $this->newLine();
            
            // Only show first file in interactive mode
            break;
        }
    }
}