<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\VerifiedSourceFetcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestVerifiedSourceFetching extends Command
{
    protected $signature = 'test:verified-source 
                           {address : Contract address to fetch}
                           {--network=ethereum : Network (ethereum, bsc, polygon)}
                           {--force : Force refresh from API}
                           {--verify-only : Only check verification status}
                           {--batch= : Comma-separated list of addresses}
                           {--multi-chain : Search across all networks}
                           {--metadata-only : Get only contract metadata}
                           {--analyze : Analyze source code structure}
                           {--stats : Show cache statistics}';

    protected $description = 'Test verified Solidity source code fetching via Etherscan/BscScan APIs';

    private VerifiedSourceFetcher $fetcher;

    public function __construct(VerifiedSourceFetcher $fetcher)
    {
        parent::__construct();
        $this->fetcher = $fetcher;
    }

    public function handle(): int
    {
        $this->info('🔍 Verified Source Code Fetching Test');
        $this->newLine();

        try {
            if ($this->option('stats')) {
                return $this->showCacheStatistics();
            }

            if ($this->option('batch')) {
                return $this->handleBatchFetch();
            }

            if ($this->option('multi-chain')) {
                return $this->handleMultiChainSearch();
            }

            $address = $this->argument('address');
            $network = $this->option('network');

            if (!$this->validateAddress($address)) {
                $this->error("❌ Invalid contract address: {$address}");
                return self::FAILURE;
            }

            if ($this->option('verify-only')) {
                return $this->handleVerificationCheck($address, $network);
            }

            if ($this->option('metadata-only')) {
                return $this->handleMetadataFetch($address, $network);
            }

            return $this->handleSourceFetch($address, $network);

        } catch (\Exception $e) {
            $this->error("❌ Command failed: {$e->getMessage()}");
            if ($this->option('verbose')) {
                $this->line($e->getTraceAsString());
            }
            return self::FAILURE;
        }
    }

    private function handleSourceFetch(string $address, string $network): int
    {
        $this->info("📥 Fetching verified source for: {$address}");
        $this->info("🌐 Network: {$network}");
        $this->newLine();

        $startTime = microtime(true);

        try {
            $result = $this->fetcher->fetchVerifiedSource(
                $address, 
                $network, 
                $this->option('force')
            );

            $fetchTime = round((microtime(true) - $startTime) * 1000);
            
            $this->displaySourceResult($result, $fetchTime);
            
            if ($this->option('analyze')) {
                $this->analyzeSourceStructure($result);
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $fetchTime = round((microtime(true) - $startTime) * 1000);
            $this->error("❌ Failed to fetch source ({$fetchTime}ms): {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    private function handleVerificationCheck(string $address, string $network): int
    {
        $this->info("🔍 Checking verification status:");
        $this->info("📍 Contract: {$address}");
        $this->info("🌐 Network: {$network}");
        $this->newLine();

        try {
            $isVerified = $this->fetcher->isVerified($address, $network);
            
            if ($isVerified) {
                $this->info("✅ Contract is verified on {$network}");
                
                // Get metadata if verified
                $metadata = $this->fetcher->getContractMetadata($address, $network);
                $this->displayMetadata($metadata);
            } else {
                $this->warn("❌ Contract is not verified on {$network}");
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Verification check failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    private function handleMetadataFetch(string $address, string $network): int
    {
        $this->info("📋 Fetching contract metadata:");
        $this->info("📍 Contract: {$address}");
        $this->info("🌐 Network: {$network}");
        $this->newLine();

        try {
            $metadata = $this->fetcher->getContractMetadata($address, $network);
            $this->displayMetadata($metadata);
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Metadata fetch failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    private function handleBatchFetch(): int
    {
        $addresses = array_map('trim', explode(',', $this->option('batch')));
        $network = $this->option('network');
        
        $this->info("📦 Batch fetching " . count($addresses) . " contracts");
        $this->info("🌐 Network: {$network}");
        $this->newLine();

        $progressBar = $this->output->createProgressBar(count($addresses));
        $progressBar->start();

        try {
            $result = $this->fetcher->batchFetchVerified($addresses, $network, true);
            
            $progressBar->finish();
            $this->newLine(2);
            
            $this->displayBatchResults($result);
            
            return count($result['failed']) === 0 ? self::SUCCESS : self::FAILURE;

        } catch (\Exception $e) {
            $progressBar->finish();
            $this->newLine();
            $this->error("❌ Batch fetch failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    private function handleMultiChainSearch(): int
    {
        $address = $this->argument('address');
        
        $this->info("🔗 Multi-chain verification search:");
        $this->info("📍 Contract: {$address}");
        $this->newLine();

        try {
            $result = $this->fetcher->findVerifiedAcrossChains($address);
            $this->displayMultiChainResults($result);
            
            $foundAny = !empty($result['verified_on']);
            return $foundAny ? self::SUCCESS : self::FAILURE;

        } catch (\Exception $e) {
            $this->error("❌ Multi-chain search failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    private function showCacheStatistics(): int
    {
        $this->info("📊 Verified Source Cache Statistics");
        $this->newLine();

        try {
            $stats = $this->fetcher->getCacheStatistics();
            
            // Database cache stats
            if (isset($stats['database_cache'])) {
                $db = $stats['database_cache'];
                $this->table(
                    ['Metric', 'Value'],
                    [
                        ['Total Cached Contracts', number_format($db['total_contracts'])],
                        ['Verified Contracts', number_format($db['verified_contracts'])],
                        ['Networks', implode(', ', $db['networks'] ?? [])],
                        ['Oldest Entry', $db['oldest_entry'] ?? 'N/A'],
                        ['Newest Entry', $db['newest_entry'] ?? 'N/A'],
                    ]
                );
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Failed to get statistics: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    private function displaySourceResult(array $result, int $fetchTime): void
    {
        $this->info("✅ Source code fetched successfully ({$fetchTime}ms)");
        $this->newLine();

        $this->table(
            ['Property', 'Value'],
            [
                ['Contract Address', $result['contract_address']],
                ['Network', $result['network']],
                ['Contract Name', $result['contract_name']],
                ['Compiler Version', $result['compiler_version']],
                ['Optimization', $result['optimization_used'] ? 'Yes' : 'No'],
                ['Optimization Runs', $result['optimization_runs']],
                ['License', $result['license_type']],
                ['Is Proxy', $result['is_proxy'] ? 'Yes' : 'No'],
                ['Main Contract', $result['main_contract'] ?? 'N/A'],
            ]
        );

        if (isset($result['statistics'])) {
            $stats = $result['statistics'];
            $this->newLine();
            $this->info("📊 Source Statistics:");
            
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Source Files', $stats['total_files']],
                    ['Total Lines', number_format($stats['total_lines'])],
                    ['Functions', $stats['functions_count']],
                    ['Events', $stats['events_count']],
                    ['Modifiers', $stats['modifiers_count']],
                    ['Estimated Size', $stats['estimated_size_kb'] . ' KB'],
                ]
            );
        }

        if (!empty($result['source_files'])) {
            $this->newLine();
            $this->info("📄 Source Files:");
            foreach (array_keys($result['source_files']) as $filename) {
                $this->line("  • {$filename}");
            }
        }
    }

    private function displayMetadata(array $metadata): void
    {
        $this->table(
            ['Property', 'Value'],
            [
                ['Contract Name', $metadata['contract_name']],
                ['Compiler Version', $metadata['compiler_version']],
                ['Optimization Used', $metadata['optimization_used'] ? 'Yes' : 'No'],
                ['Optimization Runs', $metadata['optimization_runs']],
                ['EVM Version', $metadata['evm_version']],
                ['License Type', $metadata['license_type']],
                ['Is Proxy', $metadata['is_proxy'] ? 'Yes' : 'No'],
                ['Implementation', $metadata['implementation_address'] ?? 'N/A'],
                ['Is Verified', $metadata['is_verified'] ? 'Yes' : 'No'],
                ['Has Constructor Args', $metadata['has_constructor_args'] ? 'Yes' : 'No'],
                ['Explorer URL', $metadata['explorer_url']],
            ]
        );
    }

    private function displayBatchResults(array $result): void
    {
        $summary = $result['summary'];
        
        $this->info("📊 Batch Results:");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Contracts', $summary['total']],
                ['Successful', $summary['successful']],
                ['Failed', $summary['failed']],
                ['Skipped (Unverified)', $summary['skipped']],
                ['Success Rate', $summary['success_rate'] . '%'],
            ]
        );

        if (!empty($result['successful'])) {
            $this->newLine();
            $this->info("✅ Successfully Fetched:");
            foreach (array_keys($result['successful']) as $address) {
                $this->line("  • {$address}");
            }
        }

        if (!empty($result['failed'])) {
            $this->newLine();
            $this->warn("❌ Failed:");
            foreach ($result['failed'] as $address => $error) {
                $this->line("  • {$address}: {$error}");
            }
        }

        if (!empty($result['skipped'])) {
            $this->newLine();
            $this->warn("⏭️ Skipped (Unverified):");
            foreach ($result['skipped'] as $address) {
                $this->line("  • {$address}");
            }
        }
    }

    private function displayMultiChainResults(array $result): void
    {
        $this->table(
            ['Network', 'Verified', 'Explorer URL'],
            collect($result['networks'])->map(function ($data, $network) {
                return [
                    $network,
                    $data['verified'] ? '✅ Yes' : '❌ No',
                    $data['url'] ?? ($data['error'] ?? 'N/A')
                ];
            })->toArray()
        );

        if (!empty($result['verified_on'])) {
            $this->newLine();
            $this->info("✅ Contract verified on: " . implode(', ', $result['verified_on']));
        } else {
            $this->newLine();
            $this->warn("❌ Contract not verified on any supported network");
        }
    }

    private function analyzeSourceStructure(array $sourceData): void
    {
        $this->newLine();
        $this->info("🔍 Analyzing Source Structure...");
        
        try {
            $analysis = $this->fetcher->analyzeSourceStructure($sourceData);
            
            $this->newLine();
            $this->info("📈 Complexity Metrics:");
            $metrics = $analysis['complexity_metrics'];
            
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total Contracts', $metrics['total_contracts']],
                    ['Unique Imports', $metrics['unique_imports']],
                    ['External Dependencies', $metrics['external_dependencies']],
                    ['Average File Size', number_format($metrics['average_file_size']) . ' bytes'],
                    ['Total Lines', number_format($metrics['total_lines'])],
                ]
            );

            if (!empty($analysis['contracts'])) {
                $this->newLine();
                $this->info("📋 Contracts Found:");
                foreach ($analysis['contracts'] as $contract) {
                    $this->line("  • {$contract}");
                }
            }

            if (!empty($analysis['pragma_versions'])) {
                $this->newLine();
                $this->info("🔧 Solidity Versions:");
                foreach ($analysis['pragma_versions'] as $version) {
                    $this->line("  • {$version}");
                }
            }

        } catch (\Exception $e) {
            $this->error("❌ Analysis failed: {$e->getMessage()}");
        }
    }

    private function validateAddress(string $address): bool
    {
        return preg_match('/^0x[a-fA-F0-9]{40}$/', $address) === 1;
    }
}