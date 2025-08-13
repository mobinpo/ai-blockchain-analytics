<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class BlockchainCacheService
{
    private const ETHERSCAN_BASE_URL = 'https://api.etherscan.io/api';
    private const MORALIS_BASE_URL = 'https://deep-index.moralis.io/api/v2';
    
    private const RATE_LIMITS = [
        'etherscan' => 200, // milliseconds between requests
        'moralis' => 100,   // milliseconds between requests
    ];

    public function __construct(
        private readonly ApiCacheService $cacheService
    ) {}

    /**
     * Get smart contract source code and ABI from Etherscan.
     */
    public function getContractSourceCode(string $contractAddress, string $network = 'ethereum'): array
    {
        $params = [
            'module' => 'contract',
            'action' => 'getsourcecode',
            'address' => $contractAddress,
            'apikey' => config('services.etherscan.api_key'),
        ];

        return $this->cacheService->cacheOrRetrieve(
            'etherscan',
            'contract/getsourcecode',
            'contract_source',
            fn() => $this->makeEtherscanCall($params, $network),
            $params,
            strtolower($contractAddress),
            86400, // 24 hours TTL - contract source rarely changes
            [
                'contract_address' => $contractAddress,
                'network' => $network,
                'data_type' => 'source_code',
            ]
        );
    }

    /**
     * Get contract ABI only (faster than full source code).
     */
    public function getContractABI(string $contractAddress, string $network = 'ethereum'): array
    {
        $params = [
            'module' => 'contract',
            'action' => 'getabi',
            'address' => $contractAddress,
            'apikey' => config('services.etherscan.api_key'),
        ];

        return $this->cacheService->cacheOrRetrieve(
            'etherscan',
            'contract/getabi',
            'contract_abi',
            fn() => $this->makeEtherscanCall($params, $network),
            $params,
            strtolower($contractAddress),
            86400, // 24 hours TTL
            [
                'contract_address' => $contractAddress,
                'network' => $network,
                'data_type' => 'abi',
            ]
        );
    }

    /**
     * Get contract creation transaction and creator.
     */
    public function getContractCreation(string $contractAddress, string $network = 'ethereum'): array
    {
        $params = [
            'module' => 'contract',
            'action' => 'getcontractcreation',
            'contractaddresses' => $contractAddress,
            'apikey' => config('services.etherscan.api_key'),
        ];

        return $this->cacheService->cacheOrRetrieve(
            'etherscan',
            'contract/getcontractcreation',
            'contract_creation',
            fn() => $this->makeEtherscanCall($params, $network),
            $params,
            strtolower($contractAddress),
            86400 * 7, // 7 days TTL - creation info never changes
            [
                'contract_address' => $contractAddress,
                'network' => $network,
                'data_type' => 'creation_info',
            ]
        );
    }

    /**
     * Get account balance (ETH or token).
     */
    public function getAccountBalance(
        string $address, 
        string $network = 'ethereum',
        ?string $contractAddress = null,
        string $tag = 'latest'
    ): array {
        if ($contractAddress) {
            // ERC-20 token balance
            $params = [
                'module' => 'account',
                'action' => 'tokenbalance',
                'contractaddress' => $contractAddress,
                'address' => $address,
                'tag' => $tag,
                'apikey' => config('services.etherscan.api_key'),
            ];
            $resourceId = strtolower($address) . '_' . strtolower($contractAddress);
        } else {
            // ETH balance
            $params = [
                'module' => 'account',
                'action' => 'balance',
                'address' => $address,
                'tag' => $tag,
                'apikey' => config('services.etherscan.api_key'),
            ];
            $resourceId = strtolower($address) . '_eth';
        }

        return $this->cacheService->cacheOrRetrieve(
            'etherscan',
            'account/balance',
            'account_balance',
            fn() => $this->makeEtherscanCall($params, $network),
            $params,
            $resourceId,
            300, // 5 minutes TTL for balance data
            [
                'address' => $address,
                'network' => $network,
                'token_contract' => $contractAddress,
                'balance_type' => $contractAddress ? 'erc20' : 'native',
            ]
        );
    }

    /**
     * Get transaction details.
     */
    public function getTransaction(string $txHash, string $network = 'ethereum'): array
    {
        $params = [
            'module' => 'proxy',
            'action' => 'eth_getTransactionByHash',
            'txhash' => $txHash,
            'apikey' => config('services.etherscan.api_key'),
        ];

        return $this->cacheService->cacheOrRetrieve(
            'etherscan',
            'proxy/eth_getTransactionByHash',
            'transaction',
            fn() => $this->makeEtherscanCall($params, $network),
            $params,
            strtolower($txHash),
            86400 * 30, // 30 days TTL - transaction data is immutable
            [
                'tx_hash' => $txHash,
                'network' => $network,
                'data_type' => 'transaction_details',
            ]
        );
    }

    /**
     * Get transaction receipt.
     */
    public function getTransactionReceipt(string $txHash, string $network = 'ethereum'): array
    {
        $params = [
            'module' => 'proxy',
            'action' => 'eth_getTransactionReceipt',
            'txhash' => $txHash,
            'apikey' => config('services.etherscan.api_key'),
        ];

        return $this->cacheService->cacheOrRetrieve(
            'etherscan',
            'proxy/eth_getTransactionReceipt',
            'transaction_receipt',
            fn() => $this->makeEtherscanCall($params, $network),
            $params,
            strtolower($txHash),
            86400 * 30, // 30 days TTL - receipt data is immutable
            [
                'tx_hash' => $txHash,
                'network' => $network,
                'data_type' => 'transaction_receipt',
            ]
        );
    }

    /**
     * Get internal transactions for an address.
     */
    public function getInternalTransactions(
        string $address, 
        int $startBlock = 0, 
        int $endBlock = 99999999,
        int $page = 1,
        int $offset = 10000,
        string $network = 'ethereum'
    ): array {
        $params = [
            'module' => 'account',
            'action' => 'txlistinternal',
            'address' => $address,
            'startblock' => $startBlock,
            'endblock' => $endBlock,
            'page' => $page,
            'offset' => min($offset, 10000), // API limit
            'sort' => 'desc',
            'apikey' => config('services.etherscan.api_key'),
        ];

        $resourceId = strtolower($address) . "_internal_{$page}_{$offset}";

        return $this->cacheService->cacheOrRetrieve(
            'etherscan',
            'account/txlistinternal',
            'internal_transactions',
            fn() => $this->makeEtherscanCall($params, $network),
            $params,
            $resourceId,
            1800, // 30 minutes TTL
            [
                'address' => $address,
                'network' => $network,
                'page' => $page,
                'offset' => $offset,
                'data_type' => 'internal_transactions',
            ]
        );
    }

    /**
     * Get ERC-20 token transfers for an address.
     */
    public function getTokenTransfers(
        string $address,
        ?string $contractAddress = null,
        int $startBlock = 0,
        int $endBlock = 99999999,
        int $page = 1,
        int $offset = 10000,
        string $network = 'ethereum'
    ): array {
        $params = [
            'module' => 'account',
            'action' => 'tokentx',
            'address' => $address,
            'startblock' => $startBlock,
            'endblock' => $endBlock,
            'page' => $page,
            'offset' => min($offset, 10000),
            'sort' => 'desc',
            'apikey' => config('services.etherscan.api_key'),
        ];

        if ($contractAddress) {
            $params['contractaddress'] = $contractAddress;
        }

        $resourceId = strtolower($address) . "_tokens_{$page}_{$offset}";
        if ($contractAddress) {
            $resourceId .= '_' . strtolower($contractAddress);
        }

        return $this->cacheService->cacheOrRetrieve(
            'etherscan',
            'account/tokentx',
            'token_transfers',
            fn() => $this->makeEtherscanCall($params, $network),
            $params,
            $resourceId,
            1800, // 30 minutes TTL
            [
                'address' => $address,
                'network' => $network,
                'token_contract' => $contractAddress,
                'page' => $page,
                'offset' => $offset,
                'data_type' => 'token_transfers',
            ]
        );
    }

    /**
     * Get NFT transfers using Moralis API.
     */
    public function getNFTTransfers(
        string $address,
        ?string $tokenAddress = null,
        int $limit = 100,
        ?string $cursor = null,
        string $network = 'eth'
    ): array {
        $params = [
            'limit' => min($limit, 100),
            'disable_total' => 'true',
        ];

        if ($tokenAddress) {
            $params['token_addresses'] = [$tokenAddress];
        }

        if ($cursor) {
            $params['cursor'] = $cursor;
        }

        $resourceId = strtolower($address);
        if ($tokenAddress) {
            $resourceId .= '_' . strtolower($tokenAddress);
        }

        return $this->cacheService->cacheOrRetrieve(
            'moralis',
            "{$address}/nft/transfers",
            'nft_transfers',
            fn() => $this->makeMoralisCall("{$address}/nft/transfers", $params, $network),
            $params,
            $resourceId,
            1800, // 30 minutes TTL
            [
                'address' => $address,
                'network' => $network,
                'token_address' => $tokenAddress,
                'limit' => $limit,
                'data_type' => 'nft_transfers',
            ]
        );
    }

    /**
     * Get NFT metadata.
     */
    public function getNFTMetadata(
        string $tokenAddress,
        string $tokenId,
        string $network = 'eth'
    ): array {
        $endpoint = "nft/{$tokenAddress}/{$tokenId}";
        $params = ['format' => 'decimal'];

        $resourceId = strtolower($tokenAddress) . '_' . $tokenId;

        return $this->cacheService->cacheOrRetrieve(
            'moralis',
            $endpoint,
            'nft_metadata',
            fn() => $this->makeMoralisCall($endpoint, $params, $network),
            $params,
            $resourceId,
            86400 * 7, // 7 days TTL - NFT metadata rarely changes
            [
                'token_address' => $tokenAddress,
                'token_id' => $tokenId,
                'network' => $network,
                'data_type' => 'nft_metadata',
            ]
        );
    }

    /**
     * Batch warm cache for multiple contracts.
     */
    public function warmContractData(array $contractAddresses, string $network = 'ethereum'): int
    {
        $warmed = 0;
        
        foreach ($contractAddresses as $address) {
            try {
                // Warm contract ABI
                $this->cacheService->warmCache(
                    'etherscan',
                    'contract/getabi',
                    'contract_abi',
                    fn() => $this->makeEtherscanCall([
                        'module' => 'contract',
                        'action' => 'getabi',
                        'address' => $address,
                        'apikey' => config('services.etherscan.api_key'),
                    ], $network),
                    [],
                    strtolower($address)
                );
                
                $warmed++;
                
                // Rate limiting
                usleep(self::RATE_LIMITS['etherscan'] * 1000);
                
            } catch (\Exception $e) {
                Log::warning("Failed to warm cache for contract: {$address}", [
                    'error' => $e->getMessage(),
                    'network' => $network,
                ]);
            }
        }
        
        Log::info("Warmed cache for {$warmed} contracts on {$network}");
        
        return $warmed;
    }

    /**
     * Make Etherscan API call with error handling.
     */
    private function makeEtherscanCall(array $params, string $network = 'ethereum'): array
    {
        $baseUrl = $this->getEtherscanUrl($network);
        
        Log::debug("Etherscan API call", [
            'network' => $network,
            'module' => $params['module'] ?? 'unknown',
            'action' => $params['action'] ?? 'unknown',
        ]);
        
        $response = Http::timeout(30)
            ->retry(3, 1000)
            ->get($baseUrl, $params);
        
        if (!$response->successful()) {
            throw new \Exception("Etherscan API error: HTTP {$response->status()}");
        }
        
        $data = $response->json();
        
        if (isset($data['status']) && $data['status'] === '0') {
            throw new \Exception("Etherscan API error: " . ($data['message'] ?? 'Unknown error'));
        }
        
        return $data;
    }

    /**
     * Make Moralis API call with error handling.
     */
    private function makeMoralisCall(string $endpoint, array $params = [], string $network = 'eth'): array
    {
        $url = self::MORALIS_BASE_URL . '/' . ltrim($endpoint, '/');
        
        Log::debug("Moralis API call", [
            'endpoint' => $endpoint,
            'network' => $network,
        ]);
        
        $response = Http::withHeaders([
            'X-API-Key' => config('services.moralis.api_key'),
        ])
        ->timeout(30)
        ->retry(3, 1000)
        ->get($url, array_merge($params, ['chain' => $network]));
        
        if (!$response->successful()) {
            throw new \Exception("Moralis API error: HTTP {$response->status()}");
        }
        
        return $response->json();
    }

    /**
     * Get appropriate Etherscan URL for network.
     */
    private function getEtherscanUrl(string $network): string
    {
        return match ($network) {
            'ethereum' => self::ETHERSCAN_BASE_URL,
            'goerli' => 'https://api-goerli.etherscan.io/api',
            'sepolia' => 'https://api-sepolia.etherscan.io/api',
            'polygon' => 'https://api.polygonscan.com/api',
            'bsc' => 'https://api.bscscan.com/api',
            'arbitrum' => 'https://api.arbiscan.io/api',
            'optimism' => 'https://api-optimistic.etherscan.io/api',
            default => self::ETHERSCAN_BASE_URL,
        };
    }

    /**
     * Get cache statistics for blockchain APIs.
     */
    public function getCacheStatistics(): array
    {
        $etherscanStats = $this->cacheService->getStatisticsForApiSource('etherscan');
        $moralisStats = $this->cacheService->getStatisticsForApiSource('moralis');
        
        return [
            'etherscan' => $etherscanStats,
            'moralis' => $moralisStats,
            'total_blockchain_calls_saved' => $etherscanStats['total_hits'] + $moralisStats['total_hits'],
            'estimated_cost_saved' => [
                'etherscan' => $etherscanStats['total_hits'] * 0.0001, // Estimate cost per call
                'moralis' => $moralisStats['total_hits'] * 0.001,
            ],
        ];
    }
}
