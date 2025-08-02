<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ContractCache;
use App\Services\BlockchainExplorerService;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class ManageContractCache extends Command
{
    protected $signature = 'cache:contracts 
                           {action : Action to perform (stats|clear|cleanup|refresh)}
                           {--network= : Network name for clear/refresh actions}
                           {--address= : Contract address for clear/refresh actions}
                           {--type= : Cache type for clear action (source|abi|creation)}';
    
    protected $description = 'Manage contract cache (PostgreSQL)';

    public function handle(BlockchainExplorerService $explorerService): int
    {
        $action = $this->argument('action');

        try {
            match ($action) {
                'stats' => $this->showStats(),
                'clear' => $this->clearCache($explorerService),
                'cleanup' => $this->cleanupExpired(),
                'refresh' => $this->refreshCache($explorerService),
                default => $this->error("Unknown action: {$action}. Available: stats|clear|cleanup|refresh")
            };

            return self::SUCCESS;

        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    private function showStats(): void
    {
        $this->info('📊 Contract Cache Statistics');
        $this->newLine();

        $stats = ContractCache::getStats();

        // Overview
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Entries', $stats['total_entries']],
                ['Valid Entries', $stats['valid_entries']],
                ['Expired Entries', $stats['expired_entries']],
            ]
        );

        // By Network
        if (!empty($stats['by_network'])) {
            $this->newLine();
            $this->info('📡 By Network:');
            $networkData = [];
            foreach ($stats['by_network'] as $network => $count) {
                $networkData[] = [ucfirst($network), $count];
            }
            $this->table(['Network', 'Count'], $networkData);
        }

        // By Type
        if (!empty($stats['by_type'])) {
            $this->newLine();
            $this->info('🏷️ By Cache Type:');
            $typeData = [];
            foreach ($stats['by_type'] as $type => $count) {
                $typeData[] = [ucfirst($type), $count];
            }
            $this->table(['Type', 'Count'], $typeData);
        }

        // Timestamps
        if ($stats['oldest_entry'] || $stats['newest_entry']) {
            $this->newLine();
            $this->info('📅 Cache Age:');
            $this->table(
                ['Metric', 'Timestamp'],
                [
                    ['Oldest Entry', $stats['oldest_entry'] ?? 'N/A'],
                    ['Newest Entry', $stats['newest_entry'] ?? 'N/A'],
                ]
            );
        }

        $this->newLine();
        $this->comment('💡 Use "cache:contracts cleanup" to remove expired entries');
    }

    private function clearCache(BlockchainExplorerService $explorerService): void
    {
        $network = $this->option('network');
        $address = $this->option('address');
        $type = $this->option('type');

        if (!$network || !$address) {
            throw new InvalidArgumentException('Network and address are required for clear action. Use --network and --address options.');
        }

        if ($type) {
            // Clear specific cache type
            $deleted = ContractCache::forContract($network, $address)
                                  ->ofType($type)
                                  ->delete();
            
            $this->info("🗑️ Cleared {$deleted} {$type} cache entries for {$address} on {$network}");
        } else {
            // Clear all cache types for the contract
            $deleted = $explorerService->clearContractCache($network, $address);
            
            if ($deleted) {
                $this->info("🗑️ Cleared all cache entries for {$address} on {$network}");
            } else {
                $this->warn("⚠️ No cache entries found for {$address} on {$network}");
            }
        }
    }

    private function cleanupExpired(): void
    {
        $this->info('🧹 Cleaning up expired cache entries...');
        
        $deleted = ContractCache::cleanupExpired();
        
        if ($deleted > 0) {
            $this->info("✅ Cleaned up {$deleted} expired cache entries");
        } else {
            $this->info('✨ No expired entries found');
        }
    }

    private function refreshCache(BlockchainExplorerService $explorerService): void
    {
        $network = $this->option('network');
        $address = $this->option('address');

        if (!$network || !$address) {
            throw new InvalidArgumentException('Network and address are required for refresh action. Use --network and --address options.');
        }

        $this->info("🔄 Refreshing cache for {$address} on {$network}...");

        try {
            $result = $explorerService->refreshContractSource($network, $address);
            
            $this->info('✅ Cache refreshed successfully');
            $this->table(
                ['Property', 'Value'],
                [
                    ['Contract Name', $result['contract_name']],
                    ['Is Verified', $result['is_verified'] ? 'Yes' : 'No'],
                    ['Compiler Version', $result['compiler_version']],
                    ['Fetched At', $result['fetched_at']],
                ]
            );

        } catch (InvalidArgumentException $e) {
            $this->error("❌ Failed to refresh cache: {$e->getMessage()}");
            throw $e;
        }
    }
}
