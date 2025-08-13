<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ContractCache;
use App\Services\Concerns\UsesProxy;
use App\Services\PostgresCacheService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class BlockchainExplorerService
{
    use UsesProxy;
    
    private const SUPPORTED_NETWORKS = [
        'ethereum' => [
            'config_key' => 'etherscan',
            'name' => 'Ethereum Mainnet',
        ],
        'bsc' => [
            'config_key' => 'bscscan',
            'name' => 'Binance Smart Chain',
        ],
        'polygon' => [
            'config_key' => 'polygonscan',
            'name' => 'Polygon',
        ],
        'arbitrum' => [
            'config_key' => 'arbiscan',
            'name' => 'Arbitrum One',
        ],
        'optimism' => [
            'config_key' => 'optimistic_etherscan',
            'name' => 'Optimism',
        ],
        'avalanche' => [
            'config_key' => 'snowtrace',
            'name' => 'Avalanche C-Chain',
        ],
        'fantom' => [
            'config_key' => 'ftmscan',
            'name' => 'Fantom',
        ],
    ];

    public function __construct(
        protected PostgresCacheService $cache,
        private readonly int $timeout = 30,
        private readonly int $retryAttempts = 3,
        private readonly int $retryDelay = 1000
    ) {
    }

    /**
     * Fetch verified contract source code from blockchain explorer (with caching)
     */
    public function getContractSource(string $network, string $contractAddress): array
    {
        $this->validateNetwork($network);
        $this->validateAddress($contractAddress);

        $params = [
            'network' => $network,
            'contract_address' => strtolower($contractAddress),
        ];

        return $this->cache->remember(
            'blockchain',
            'contract_source',
            $params,
            function () use ($network, $contractAddress) {
                // Check legacy cache first for backward compatibility
                $cached = ContractCache::getForContract($network, $contractAddress, 'source');
                if ($cached) {
                    Log::info('Contract source served from legacy cache', [
                        'network' => $network,
                        'address' => $contractAddress,
                        'cache_age' => now()->diffInMinutes($cached->fetched_at) . ' minutes',
                    ]);
                    return $cached->toServiceResponse();
                }

                // Fetch from API
                $networkConfig = $this->getNetworkConfig($network);
                
                $response = $this->makeApiRequest($networkConfig['api_url'], [
                    'module' => 'contract',
                    'action' => 'getsourcecode',
                    'address' => $contractAddress,
                    'apikey' => $networkConfig['api_key'],
                ]);

                $result = $this->parseSourceCodeResponse($response, $network, $contractAddress);

                // Cache the result in legacy system too
                $this->cacheContractData($network, $contractAddress, 'source', $result);

                Log::info('Contract source fetched from API and cached', [
                    'network' => $network,
                    'address' => $contractAddress,
                    'is_verified' => $result['is_verified'],
                ]);

                return $result;
            },
            $this->getContractCacheTTL($network, 'source')
        );
    }

    /**
     * Get contract ABI from blockchain explorer (with caching)
     */
    public function getContractAbi(string $network, string $contractAddress): array
    {
        $this->validateNetwork($network);
        $this->validateAddress($contractAddress);

        $params = [
            'network' => $network,
            'contract_address' => strtolower($contractAddress),
        ];

        return $this->cache->remember(
            'blockchain',
            'contract_abi',
            $params,
            function () use ($network, $contractAddress) {
                // Check legacy cache first
                $cached = ContractCache::getForContract($network, $contractAddress, 'abi');
                if ($cached) {
                    Log::info('Contract ABI served from legacy cache', [
                        'network' => $network,
                        'address' => $contractAddress,
                    ]);
                    return $cached->toServiceResponse();
                }

                // Fetch from API
                $networkConfig = $this->getNetworkConfig($network);

                $response = $this->makeApiRequest($networkConfig['api_url'], [
                    'module' => 'contract',
                    'action' => 'getabi',
                    'address' => $contractAddress,
                    'apikey' => $networkConfig['api_key'],
                ]);

                if ($response['status'] !== '1') {
                    throw new InvalidArgumentException(
                        "Failed to fetch ABI: {$response['message']} (Contract: {$contractAddress})"
                    );
                }

                $result = [
                    'network' => $network,
                    'contract_address' => $contractAddress,
                    'abi' => json_decode($response['result'], true),
                    'fetched_at' => now()->toISOString(),
                ];

                // Cache in legacy system too
                $this->cacheContractData($network, $contractAddress, 'abi', $result);

                Log::info('Contract ABI fetched from API and cached', [
                    'network' => $network,
                    'address' => $contractAddress,
                ]);

                return $result;
            },
            $this->getContractCacheTTL($network, 'abi')
        );
    }

    /**
     * Get contract creation transaction details (with caching)
     */
    public function getContractCreation(string $network, string $contractAddress): array
    {
        $this->validateNetwork($network);
        $this->validateAddress($contractAddress);

        // Check cache first
        $cached = ContractCache::getForContract($network, $contractAddress, 'creation');
        if ($cached) {
            Log::info('Contract creation served from cache', [
                'network' => $network,
                'address' => $contractAddress,
            ]);
            return $cached->toServiceResponse();
        }

        // Fetch from API
        $networkConfig = $this->getNetworkConfig($network);

        $response = $this->makeApiRequest($networkConfig['api_url'], [
            'module' => 'contract',
            'action' => 'getcontractcreation',
            'contractaddresses' => $contractAddress,
            'apikey' => $networkConfig['api_key'],
        ]);

        if ($response['status'] !== '1') {
            throw new InvalidArgumentException(
                "Failed to fetch contract creation: {$response['message']} (Contract: {$contractAddress})"
            );
        }

        $apiResult = $response['result'][0] ?? null;
        if (!$apiResult) {
            throw new InvalidArgumentException("No creation data found for contract: {$contractAddress}");
        }

        $result = [
            'network' => $network,
            'contract_address' => $contractAddress,
            'creator_address' => $apiResult['contractCreator'],
            'creation_tx_hash' => $apiResult['txHash'],
            'fetched_at' => now()->toISOString(),
        ];

        // Cache the result
        $this->cacheContractData($network, $contractAddress, 'creation', $result);

        Log::info('Contract creation fetched from API and cached', [
            'network' => $network,
            'address' => $contractAddress,
        ]);

        return $result;
    }

    /**
     * Check if contract is verified on the blockchain explorer
     */
    public function isContractVerified(string $network, string $contractAddress): bool
    {
        try {
            $source = $this->getContractSource($network, $contractAddress);
            return !empty($source['source_code']) && $source['source_code'] !== 'Contract source code not verified';
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Get list of supported networks
     */
    public function getSupportedNetworks(): array
    {
        $networks = [];
        
        foreach (self::SUPPORTED_NETWORKS as $networkId => $networkInfo) {
            $configKey = $networkInfo['config_key'];
            $config = config("blockchain_explorers.{$configKey}");
            
            $networks[] = [
                'id' => $networkId,
                'name' => $networkInfo['name'],
                'api_url' => $config['api_url'] ?? 'Not configured',
                'api_key_required' => !empty($config['api_key']),
                'config_key' => $configKey,
            ];
        }
        
        return $networks;
    }

    /**
     * Bulk fetch contract sources for multiple addresses
     */
    public function bulkGetContractSources(string $network, array $contractAddresses): array
    {
        $results = [];
        
        foreach ($contractAddresses as $address) {
            try {
                $results[$address] = $this->getContractSource($network, $address);
            } catch (InvalidArgumentException $e) {
                $results[$address] = [
                    'error' => $e->getMessage(),
                    'contract_address' => $address,
                    'network' => $network,
                ];
                
                Log::warning("Failed to fetch contract source", [
                    'network' => $network,
                    'address' => $address,
                    'error' => $e->getMessage(),
                ]);
            }
            
            // Add delay between requests to avoid rate limiting
            if (count($contractAddresses) > 1) {
                usleep($this->retryDelay * 1000); // Convert to microseconds
            }
        }

        return $results;
    }

    private function validateNetwork(string $network): void
    {
        if (!array_key_exists($network, self::SUPPORTED_NETWORKS)) {
            throw new InvalidArgumentException(
                "Unsupported network: {$network}. Supported networks: " . implode(', ', array_keys(self::SUPPORTED_NETWORKS))
            );
        }
    }

    private function validateAddress(string $address): void
    {
        if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
            throw new InvalidArgumentException("Invalid Ethereum address format: {$address}");
        }
    }

    private function getNetworkConfig(string $network): array
    {
        $networkInfo = self::SUPPORTED_NETWORKS[$network];
        $configKey = $networkInfo['config_key'];
        $config = config("blockchain_explorers.{$configKey}");

        if (empty($config['api_key'])) {
            throw new InvalidArgumentException(
                "API key not configured for {$networkInfo['name']}. Please set {$configKey} API key in your environment."
            );
        }

        return [
            'api_url' => $config['api_url'],
            'api_key' => $config['api_key'],
            'rate_limit' => $config['rate_limit'] ?? 5,
            'timeout' => $config['timeout'] ?? $this->timeout,
        ];
    }

    /**
     * Cache contract data in PostgreSQL
     */
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

    /**
     * Clear cache for specific contract
     */
    public function clearContractCache(string $network, string $contractAddress): bool
    {
        $this->validateNetwork($network);
        $this->validateAddress($contractAddress);

        $deleted = ContractCache::forContract($network, $contractAddress)->delete();
        
        Log::info('Contract cache cleared', [
            'network' => $network,
            'address' => $contractAddress,
            'entries_deleted' => $deleted,
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

    private function makeApiRequest(string $url, array $params): array
    {
        $response = $this->getHttpClient()
            ->timeout($this->timeout)
            ->retry($this->retryAttempts, $this->retryDelay)
            ->get($url, $params);

        if (!$response->successful()) {
            throw new InvalidArgumentException(
                "API request failed with status {$response->status()}: {$response->body()}"
            );
        }

        $data = $response->json();

        if (!isset($data['status'])) {
            throw new InvalidArgumentException("Invalid API response format");
        }

        return $data;
    }

    private function parseSourceCodeResponse(array $response, string $network, string $contractAddress): array
    {
        if ($response['status'] !== '1') {
            throw new InvalidArgumentException(
                "Failed to fetch source code: {$response['message']} (Contract: {$contractAddress})"
            );
        }

        $result = $response['result'][0] ?? null;
        if (!$result) {
            throw new InvalidArgumentException("No source code found for contract: {$contractAddress}");
        }

        // Handle different source code formats
        $sourceCode = $result['SourceCode'];
        $parsedSources = [];

        if (str_starts_with($sourceCode, '{{') || str_starts_with($sourceCode, '{')) {
            // Multi-file source (JSON format)
            $sourceJson = $sourceCode;
            if (str_starts_with($sourceCode, '{{')) {
                $sourceJson = substr($sourceCode, 1, -1); // Remove outer braces
            }
            
            $decoded = json_decode($sourceJson, true);
            if ($decoded && isset($decoded['sources'])) {
                foreach ($decoded['sources'] as $filename => $fileData) {
                    $parsedSources[$filename] = $fileData['content'] ?? $fileData;
                }
            } else {
                $parsedSources['main.sol'] = $sourceCode;
            }
        } else {
            // Single file source
            $contractName = $result['ContractName'] ?: 'Contract';
            $parsedSources["{$contractName}.sol"] = $sourceCode;
        }

        return [
            'network' => $network,
            'contract_address' => $contractAddress,
            'contract_name' => $result['ContractName'] ?: 'Unknown',
            'compiler_version' => $result['CompilerVersion'] ?: 'Unknown',
            'optimization_used' => $result['OptimizationUsed'] === '1',
            'optimization_runs' => (int) ($result['Runs'] ?: 0),
            'constructor_arguments' => $result['ConstructorArguments'] ?: '',
            'evm_version' => $result['EVMVersion'] ?: 'default',
            'library' => $result['Library'] ?: '',
            'license_type' => $result['LicenseType'] ?: 'Unknown',
            'proxy' => $result['Proxy'] === '1',
            'implementation' => $result['Implementation'] ?: null,
            'swarm_source' => $result['SwarmSource'] ?: '',
            'source_code' => $sourceCode,
            'parsed_sources' => $parsedSources,
            'abi' => $result['ABI'] ? json_decode($result['ABI'], true) : null,
            'fetched_at' => now()->toISOString(),
            'is_verified' => !empty($sourceCode) && $sourceCode !== 'Contract source code not verified',
        ];
    }

    /**
     * Get cache TTL based on network and data type
     */
    private function getContractCacheTTL(string $network, string $dataType): int
    {
        // Cache times based on network and data stability
        $baseTTLs = [
            'source' => 86400 * 7,    // 7 days (source code doesn't change)
            'abi' => 86400 * 7,       // 7 days (ABI doesn't change)
            'creation' => 86400 * 30, // 30 days (creation data never changes)
        ];

        // Network-specific multipliers (some networks are more stable)
        $networkMultipliers = [
            'ethereum' => 1.0,
            'bsc' => 0.9,
            'polygon' => 0.8,
            'arbitrum' => 0.8,
            'optimism' => 0.8,
            'avalanche' => 0.7,
            'fantom' => 0.7,
        ];

        $baseTTL = $baseTTLs[$dataType] ?? 86400; // Default 24 hours
        $multiplier = $networkMultipliers[$network] ?? 0.5;

        return (int) ($baseTTL * $multiplier);
    }
}