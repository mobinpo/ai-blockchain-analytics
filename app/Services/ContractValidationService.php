<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\MultiChainExplorerManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

final class ContractValidationService
{
    public function __construct(
        private readonly MultiChainExplorerManager $explorerManager
    ) {
    }

    /**
     * Validate contract and detect network
     */
    public function validateAndDetectNetwork(string $contractAddress): array
    {
        $cacheKey = "contract_validation:{$contractAddress}";
        
        // Check cache first
        $cachedResult = Cache::get($cacheKey);
        if ($cachedResult) {
            return $cachedResult;
        }

        $networks = ['ethereum', 'bsc', 'polygon', 'arbitrum', 'optimism', 'avalanche'];
        $result = [
            'exists' => false,
            'network' => null,
            'name' => null,
            'verified' => false,
            'compiler' => null,
            'optimization' => null
        ];

        foreach ($networks as $network) {
            try {
                $contractInfo = $this->checkContractOnNetwork($contractAddress, $network);
                
                if ($contractInfo['exists']) {
                    $result = [
                        'exists' => true,
                        'network' => $network,
                        'name' => $contractInfo['name'],
                        'verified' => $contractInfo['verified'],
                        'compiler' => $contractInfo['compiler'],
                        'optimization' => $contractInfo['optimization']
                    ];
                    break; // Found on this network
                }
            } catch (\Exception $e) {
                Log::warning("Network check failed for {$network}", [
                    'address' => $contractAddress,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }

        // Cache result for 1 hour
        Cache::put($cacheKey, $result, 3600);

        return $result;
    }

    /**
     * Check if contract exists on specific network
     */
    private function checkContractOnNetwork(string $contractAddress, string $network): array
    {
        try {
            $explorer = $this->explorerManager->getBestExplorer($network);
            
            // Try to get contract source code to verify existence
            $sourceData = $explorer->getContractSource($contractAddress);
            
            if ($sourceData['is_verified'] && !empty($sourceData['source_code'])) {
                return [
                    'exists' => true,
                    'name' => $sourceData['contract_name'] ?? 'Unknown Contract',
                    'verified' => true,
                    'compiler' => $sourceData['compiler_version'] ?? null,
                    'optimization' => $sourceData['optimization_used'] ?? null
                ];
            }

            // If no source code, try to check if it's a contract by getting bytecode
            $isContract = $this->checkBytecode($contractAddress, $network);
            
            return [
                'exists' => $isContract,
                'name' => $isContract ? 'Unverified Contract' : null,
                'verified' => false,
                'compiler' => null,
                'optimization' => null
            ];

        } catch (\Exception $e) {
            Log::error("Contract check failed on {$network}", [
                'address' => $contractAddress,
                'error' => $e->getMessage()
            ]);
            
            return [
                'exists' => false,
                'name' => null,
                'verified' => false,
                'compiler' => null,
                'optimization' => null
            ];
        }
    }

    /**
     * Check contract bytecode to verify it's a contract
     */
    private function checkBytecode(string $contractAddress, string $network): bool
    {
        $endpoints = [
            'ethereum' => 'https://api.etherscan.io/api',
            'bsc' => 'https://api.bscscan.com/api',
            'polygon' => 'https://api.polygonscan.com/api',
            'arbitrum' => 'https://api.arbiscan.io/api',
            'optimism' => 'https://api-optimistic.etherscan.io/api',
            'avalanche' => 'https://api.snowtrace.io/api'
        ];

        $apiKeys = [
            'ethereum' => config('services.etherscan.api_key'),
            'bsc' => config('services.bscscan.api_key'),
            'polygon' => config('services.polygonscan.api_key'),
            'arbitrum' => config('services.arbiscan.api_key'),
            'optimism' => config('services.optimism.api_key'),
            'avalanche' => config('services.snowtrace.api_key')
        ];

        if (!isset($endpoints[$network])) {
            return false;
        }

        try {
            $response = Http::timeout(10)->get($endpoints[$network], [
                'module' => 'proxy',
                'action' => 'eth_getCode',
                'address' => $contractAddress,
                'tag' => 'latest',
                'apikey' => $apiKeys[$network] ?? ''
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $bytecode = $data['result'] ?? '0x';
                
                // Contract exists if bytecode is not empty (more than just '0x')
                return strlen($bytecode) > 2;
            }

            return false;
        } catch (\Exception $e) {
            Log::warning("Bytecode check failed for {$network}", [
                'address' => $contractAddress,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Validate contract address format
     */
    public function isValidAddress(string $address): bool
    {
        return preg_match('/^0x[a-fA-F0-9]{40}$/', $address) === 1;
    }

    /**
     * Get network information
     */
    public function getNetworkInfo(string $network): ?array
    {
        $networks = [
            'ethereum' => [
                'name' => 'Ethereum',
                'chain_id' => 1,
                'explorer' => 'https://etherscan.io',
                'api' => 'https://api.etherscan.io/api',
                'native_token' => 'ETH',
                'icon' => '/images/networks/ethereum.png'
            ],
            'bsc' => [
                'name' => 'Binance Smart Chain',
                'chain_id' => 56,
                'explorer' => 'https://bscscan.com',
                'api' => 'https://api.bscscan.com/api',
                'native_token' => 'BNB',
                'icon' => '/images/networks/bsc.png'
            ],
            'polygon' => [
                'name' => 'Polygon',
                'chain_id' => 137,
                'explorer' => 'https://polygonscan.com',
                'api' => 'https://api.polygonscan.com/api',
                'native_token' => 'MATIC',
                'icon' => '/images/networks/polygon.png'
            ],
            'arbitrum' => [
                'name' => 'Arbitrum One',
                'chain_id' => 42161,
                'explorer' => 'https://arbiscan.io',
                'api' => 'https://api.arbiscan.io/api',
                'native_token' => 'ETH',
                'icon' => '/images/networks/arbitrum.png'
            ],
            'optimism' => [
                'name' => 'Optimism',
                'chain_id' => 10,
                'explorer' => 'https://optimistic.etherscan.io',
                'api' => 'https://api-optimistic.etherscan.io/api',
                'native_token' => 'ETH',
                'icon' => '/images/networks/optimism.png'
            ],
            'avalanche' => [
                'name' => 'Avalanche C-Chain',
                'chain_id' => 43114,
                'explorer' => 'https://snowtrace.io',
                'api' => 'https://api.snowtrace.io/api',
                'native_token' => 'AVAX',
                'icon' => '/images/networks/avalanche.png'
            ]
        ];

        return $networks[$network] ?? null;
    }

    /**
     * Detect network from contract patterns
     */
    public function detectNetworkFromAddress(string $contractAddress): ?string
    {
        // This is a simplified approach - in reality, you might check
        // known contract addresses or use other heuristics
        
        $knownContracts = [
            // Ethereum
            '0x5C69bEe701ef814a2B6a3EDD4B1652CB9cc5aA6f' => 'ethereum', // Uniswap V2 Factory
            '0xA0b86a33E6417c7e4E6b42b0Db8FC0a41F34a3B4' => 'ethereum', // USDC
            '0x7d2768dE32b0b80b7a3454c06BdAc94A69DDc7A9' => 'ethereum', // AAVE
            
            // BSC
            '0xcA143Ce32Fe78f1f7019d7d551a6402fC5350c73' => 'bsc', // PancakeSwap Factory
            '0x73feaa1eE314F8c655E354234017bE2193C9E24E' => 'bsc', // PancakeSwap Router
            
            // Polygon
            '0x5757371414417b8C6CAad45bAeF941aBc7d3Ab32' => 'polygon', // QuickSwap Factory
        ];

        $lowerAddress = strtolower($contractAddress);
        
        foreach ($knownContracts as $address => $network) {
            if (strtolower($address) === $lowerAddress) {
                return $network;
            }
        }

        return null; // Unknown, will need to check all networks
    }

    /**
     * Bulk validate multiple contracts
     */
    public function bulkValidate(array $contractAddresses): array
    {
        $results = [];

        foreach ($contractAddresses as $address) {
            if (!$this->isValidAddress($address)) {
                $results[$address] = [
                    'valid' => false,
                    'error' => 'Invalid address format'
                ];
                continue;
            }

            try {
                $validation = $this->validateAndDetectNetwork($address);
                $results[$address] = [
                    'valid' => $validation['exists'],
                    'network' => $validation['network'],
                    'verified' => $validation['verified'],
                    'name' => $validation['name']
                ];
            } catch (\Exception $e) {
                $results[$address] = [
                    'valid' => false,
                    'error' => 'Validation failed'
                ];
            }
        }

        return $results;
    }
}
