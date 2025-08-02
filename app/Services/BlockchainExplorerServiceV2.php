<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\BlockchainExplorerInterface;
use App\Models\ContractCache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class BlockchainExplorerServiceV2
{
    public function __construct(
        private readonly BlockchainExplorerFactory $factory
    ) {
    }

    /**
     * Fetch verified contract source code from blockchain explorer (with caching)
     */
    public function getContractSource(string $network, string $contractAddress): array
    {
        $explorer = $this->getExplorer($network);

        // Check cache first
        $cached = ContractCache::getForContract($network, $contractAddress, 'source');
        if ($cached) {
            Log::info('Contract source served from cache', [
                'network' => $network,
                'address' => $contractAddress,
                'cache_age' => now()->diffInMinutes($cached->fetched_at) . ' minutes',
                'explorer' => $explorer->getName(),
            ]);
            return $cached->toServiceResponse();
        }

        // Fetch from API using explorer
        $result = $explorer->getContractSource($contractAddress);

        // Cache the result
        $this->cacheContractData($network, $contractAddress, 'source', $result);

        Log::info('Contract source fetched from API and cached', [
            'network' => $network,
            'address' => $contractAddress,
            'is_verified' => $result['is_verified'],
            'explorer' => $explorer->getName(),
        ]);

        return $result;
    }

    /**
     * Get contract ABI from blockchain explorer (with caching)
     */
    public function getContractAbi(string $network, string $contractAddress): array
    {
        $explorer = $this->getExplorer($network);

        // Check cache first
        $cached = ContractCache::getForContract($network, $contractAddress, 'abi');
        if ($cached) {
            Log::info('Contract ABI served from cache', [
                'network' => $network,
                'address' => $contractAddress,
                'explorer' => $explorer->getName(),
            ]);
            return $cached->toServiceResponse();
        }

        // Fetch from API using explorer
        $result = $explorer->getContractAbi($contractAddress);

        // Cache the result
        $this->cacheContractData($network, $contractAddress, 'abi', $result);

        Log::info('Contract ABI fetched from API and cached', [
            'network' => $network,
            'address' => $contractAddress,
            'explorer' => $explorer->getName(),
        ]);

        return $result;
    }

    /**
     * Get contract creation transaction details (with caching)
     */
    public function getContractCreation(string $network, string $contractAddress): array
    {
        $explorer = $this->getExplorer($network);

        // Check cache first
        $cached = ContractCache::getForContract($network, $contractAddress, 'creation');
        if ($cached) {
            Log::info('Contract creation served from cache', [
                'network' => $network,
                'address' => $contractAddress,
                'explorer' => $explorer->getName(),
            ]);
            return $cached->toServiceResponse();
        }

        // Fetch from API using explorer
        $result = $explorer->getContractCreation($contractAddress);

        // Cache the result
        $this->cacheContractData($network, $contractAddress, 'creation', $result);

        Log::info('Contract creation fetched from API and cached', [
            'network' => $network,
            'address' => $contractAddress,
            'explorer' => $explorer->getName(),
        ]);

        return $result;
    }

    /**
     * Check if contract is verified on the blockchain explorer
     */
    public function isContractVerified(string $network, string $contractAddress): bool
    {
        $explorer = $this->getExplorer($network);
        return $explorer->isContractVerified($contractAddress);
    }

    /**
     * Get list of supported networks with their explorers
     */
    public function getSupportedNetworks(): array
    {
        return BlockchainExplorerFactory::getNetworkInfo();
    }

    /**
     * Bulk fetch contract sources for multiple addresses
     */
    public function bulkGetContractSources(string $network, array $contractAddresses): array
    {
        $explorer = $this->getExplorer($network);
        $results = [];
        
        foreach ($contractAddresses as $address) {
            try {
                $results[$address] = $this->getContractSource($network, $address);
            } catch (InvalidArgumentException $e) {
                $results[$address] = [
                    'error' => $e->getMessage(),
                    'contract_address' => $address,
                    'network' => $network,
                    'explorer' => $explorer->getName(),
                ];
                
                Log::warning("Failed to fetch contract source", [
                    'network' => $network,
                    'address' => $address,
                    'error' => $e->getMessage(),
                    'explorer' => $explorer->getName(),
                ]);
            }
            
            // Add delay between requests to respect rate limiting
            if (count($contractAddresses) > 1) {
                usleep(1000000 / $explorer->getRateLimit()); // Convert to microseconds
            }
        }

        return $results;
    }

    /**
     * Get cleaned contract source optimized for AI prompt input
     */
    public function getCleanedContractSource(string $network, string $contractAddress): array
    {
        $contract = $this->getContractSource($network, $contractAddress);
        
        if (!$contract['is_verified']) {
            throw new InvalidArgumentException("Contract {$contractAddress} is not verified");
        }

        $cleaner = app(\App\Services\SolidityCleanerService::class);
        
        // Clean the main source code
        $cleanedSource = $cleaner->cleanForPrompt($contract['source_code']);
        
        // Get cleaning statistics
        $stats = $cleaner->getCleaningStats($contract['source_code'], $cleanedSource);
        
        return array_merge($contract, [
            'cleaned_source_code' => $cleanedSource,
            'cleaning_stats' => $stats,
            'is_cleaned' => true,
        ]);
    }

    /**
     * Get flattened contract source with all imports resolved
     */
    public function getFlattenedContractSource(string $network, string $contractAddress): array
    {
        $contract = $this->getContractSource($network, $contractAddress);
        
        if (!$contract['is_verified']) {
            throw new InvalidArgumentException("Contract {$contractAddress} is not verified");
        }

        $cleaner = app(\App\Services\SolidityCleanerService::class);
        
        // Flatten all source files
        $flattened = $cleaner->cleanAndFlatten($contract['parsed_sources']);
        
        // Get flattening statistics
        $originalTotal = implode("\n", $contract['parsed_sources']);
        $stats = $cleaner->getCleaningStats($originalTotal, $flattened);
        
        return array_merge($contract, [
            'flattened_source_code' => $flattened,
            'flattening_stats' => $stats,
            'is_flattened' => true,
        ]);
    }

    /**
     * Get contract source analysis for AI processing
     */
    public function getContractAnalysis(string $network, string $contractAddress): array
    {
        $contract = $this->getContractSource($network, $contractAddress);
        
        if (!$contract['is_verified']) {
            throw new InvalidArgumentException("Contract {$contractAddress} is not verified");
        }

        $cleaner = app(\App\Services\SolidityCleanerService::class);
        
        // Analyze the source code
        $analysis = $cleaner->analyzeCode($contract['source_code']);
        
        return array_merge($contract, [
            'source_analysis' => $analysis,
            'is_analyzed' => true,
        ]);
    }

    /**
     * Clear cache for specific contract
     */
    public function clearContractCache(string $network, string $contractAddress): bool
    {
        $explorer = $this->getExplorer($network);

        $deleted = ContractCache::forContract($network, $contractAddress)->delete();
        
        Log::info('Contract cache cleared', [
            'network' => $network,
            'address' => $contractAddress,
            'entries_deleted' => $deleted,
            'explorer' => $explorer->getName(),
        ]);

        return $deleted > 0;
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        return ContractCache::getStats();
    }

    /**
     * Clean up expired cache entries
     */
    public function cleanupExpiredCache(): int
    {
        $deleted = ContractCache::cleanupExpired();
        
        Log::info('Expired cache entries cleaned up', [
            'entries_deleted' => $deleted,
        ]);

        return $deleted;
    }

    /**
     * Force refresh contract data (bypass cache)
     */
    public function refreshContractSource(string $network, string $contractAddress): array
    {
        // Clear existing cache
        $this->clearContractCache($network, $contractAddress);
        
        // Fetch fresh data
        return $this->getContractSource($network, $contractAddress);
    }

    /**
     * Get explorer instance for specific network
     */
    public function getExplorer(string $network): BlockchainExplorerInterface
    {
        return BlockchainExplorerFactory::create($network);
    }

    /**
     * Get explorer-specific features (like gas prices, token prices)
     */
    public function getExplorerSpecificData(string $network, string $feature): array
    {
        $explorer = $this->getExplorer($network);

        return match ($feature) {
            'gas_prices' => $this->getGasPrices($explorer),
            'native_token_price' => $this->getNativeTokenPrice($explorer),
            'available_endpoints' => $explorer->getAvailableEndpoints(),
            default => throw new InvalidArgumentException("Unknown feature: {$feature}")
        };
    }

    /**
     * Validate network configuration
     */
    public function validateNetworkConfiguration(string $network): array
    {
        return BlockchainExplorerFactory::validateConfiguration($network);
    }

    /**
     * Get all configured explorers
     */
    public function getConfiguredExplorers(): array
    {
        return BlockchainExplorerFactory::getAllConfiguredExplorers();
    }

    private function cacheContractData(string $network, string $address, string $type, array $data): void
    {
        $ttl = config('blockchain_explorers.cache_ttl', 3600);
        
        try {
            ContractCache::storeContractData($network, $address, $type, $data, $ttl);
        } catch (\Exception $e) {
            Log::warning('Failed to cache contract data', [
                'network' => $network,
                'address' => $address,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function getGasPrices(BlockchainExplorerInterface $explorer): array
    {
        if (method_exists($explorer, 'getGasPrices')) {
            return $explorer->getGasPrices();
        }

        throw new InvalidArgumentException("Gas prices not available for {$explorer->getName()}");
    }

    private function getNativeTokenPrice(BlockchainExplorerInterface $explorer): array
    {
        $methodMap = [
            'etherscan' => 'getEthPrice',
            'bscscan' => 'getBnbPrice',
            'polygonscan' => 'getMaticPrice',
            'arbiscan' => 'getEthPrice',
            'optimistic_etherscan' => 'getEthPrice',
            'snowtrace' => 'getAvaxPrice',
            'ftmscan' => 'getFtmPrice',
        ];

        $method = $methodMap[$explorer->getName()] ?? null;
        
        if ($method && method_exists($explorer, $method)) {
            return $explorer->{$method}();
        }

        throw new InvalidArgumentException("Native token price not available for {$explorer->getName()}");
    }
}