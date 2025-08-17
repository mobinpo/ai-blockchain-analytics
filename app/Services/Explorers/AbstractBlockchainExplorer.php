<?php

declare(strict_types=1);

namespace App\Services\Explorers;

use App\Contracts\BlockchainExplorerInterface;
use App\Services\Concerns\UsesProxy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

abstract class AbstractBlockchainExplorer implements BlockchainExplorerInterface
{
    use UsesProxy;
    
    protected readonly string $apiKey;
    protected readonly string $apiUrl;
    protected readonly int $rateLimit;
    protected readonly int $timeout;
    protected readonly int $retryAttempts;
    protected readonly int $retryDelay;

    public function __construct(array $config)
    {
        $this->apiKey = $config['api_key'] ?? throw new InvalidArgumentException('API key is required');
        $this->apiUrl = $config['api_url'] ?? throw new InvalidArgumentException('API URL is required');
        $this->rateLimit = $config['rate_limit'] ?? 5;
        $this->timeout = $config['timeout'] ?? 30;
        $this->retryAttempts = $config['retry_attempts'] ?? 3;
        $this->retryDelay = $config['retry_delay'] ?? 1000;
    }

    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->apiUrl);
    }

    public function getRateLimit(): int
    {
        return $this->rateLimit;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Default implementation for getting chain ID
     */
    abstract public function getChainId(): int;

    /**
     * Default implementation for getting native currency
     */
    abstract public function getNativeCurrency(): string;

    /**
     * Default implementation for getting explorer URL
     */
    abstract public function getExplorerUrl(): string;

    /**
     * Default implementation for address detection and validation
     */
    public function detectAndValidateAddress(string $address): array
    {
        $isValid = $this->validateAddress($address);
        
        return [
            'is_valid' => $isValid,
            'format' => $isValid ? 'ethereum' : 'unknown',
            'network' => $this->getNetwork(),
            'chain_id' => $this->getChainId(),
            'normalized_address' => $isValid ? strtolower($address) : null,
        ];
    }

    /**
     * Default implementation for getting transactions
     */
    public function getTransaction(string $txHash): array
    {
        $response = $this->makeRequest('api', [
            'module' => 'transaction',
            'action' => 'gettxreceiptstatus',
            'txhash' => $txHash,
        ]);

        return [
            'network' => $this->getNetwork(),
            'tx_hash' => $txHash,
            'status' => $response['result']['status'] ?? 'unknown',
            'explorer' => $this->getName(),
            'fetched_at' => now()->toISOString(),
        ];
    }

    /**
     * Default implementation for getting block details
     */
    public function getBlock(string $blockIdentifier): array
    {
        $response = $this->makeRequest('api', [
            'module' => 'proxy',
            'action' => 'eth_getBlockByNumber',
            'tag' => $blockIdentifier,
            'boolean' => 'true',
        ]);

        return [
            'network' => $this->getNetwork(),
            'block' => $response['result'] ?? [],
            'explorer' => $this->getName(),
            'fetched_at' => now()->toISOString(),
        ];
    }

    /**
     * Default implementation for getting account details
     */
    public function getAccount(string $address): array
    {
        $balanceResponse = $this->makeRequest('api', [
            'module' => 'account',
            'action' => 'balance',
            'address' => $address,
            'tag' => 'latest',
        ]);

        $txCountResponse = $this->makeRequest('api', [
            'module' => 'proxy',
            'action' => 'eth_getTransactionCount',
            'address' => $address,
            'tag' => 'latest',
        ]);

        return [
            'network' => $this->getNetwork(),
            'address' => $address,
            'balance_wei' => $balanceResponse['result'] ?? '0',
            'balance_ether' => $this->weiToEther($balanceResponse['result'] ?? '0'),
            'transaction_count' => hexdec($txCountResponse['result'] ?? '0x0'),
            'explorer' => $this->getName(),
            'fetched_at' => now()->toISOString(),
        ];
    }

    /**
     * Default implementation for getting contract transactions
     */
    public function getContractTransactions(string $contractAddress, int $limit = 100): array
    {
        $response = $this->makeRequest('api', [
            'module' => 'account',
            'action' => 'txlist',
            'address' => $contractAddress,
            'startblock' => '0',
            'endblock' => '99999999',
            'page' => '1',
            'offset' => (string) $limit,
            'sort' => 'desc',
        ]);

        return [
            'network' => $this->getNetwork(),
            'contract_address' => $contractAddress,
            'transactions' => $response['result'] ?? [],
            'limit' => $limit,
            'explorer' => $this->getName(),
            'fetched_at' => now()->toISOString(),
        ];
    }

    /**
     * Default implementation for getting gas prices
     */
    public function getGasPrice(): array
    {
        $response = $this->makeRequest('api', [
            'module' => 'gastracker',
            'action' => 'gasoracle',
        ]);

        return [
            'network' => $this->getNetwork(),
            'gas_prices' => $response['result'] ?? [],
            'currency' => $this->getNativeCurrency(),
            'explorer' => $this->getName(),
            'fetched_at' => now()->toISOString(),
        ];
    }

    /**
     * Helper method to convert Wei to Ether
     */
    protected function weiToEther(string $wei): string
    {
        if (empty($wei) || $wei === '0') {
            return '0';
        }

        // Convert to float for division, then format
        $ether = bcdiv($wei, '1000000000000000000', 18);
        return rtrim(rtrim($ether, '0'), '.');
    }







    /**
     * Default implementation for checking if contract is verified
     */
    public function isContractVerified(string $contractAddress): bool
    {
        try {
            $source = $this->getContractSource($contractAddress);
            return !empty($source['source_code']) && 
                   $source['source_code'] !== 'Contract source code not verified';
        } catch (\Exception $e) {
            Log::debug("Contract verification check failed: " . $e->getMessage(), [
                'address' => $contractAddress,
                'explorer' => $this->getName()
            ]);
            return false;
        }
    }

    /**
     * Default implementation for address validation
     */
    public function validateAddress(string $address): bool
    {
        // Standard Ethereum address validation (40 hex chars + 0x prefix)
        return preg_match('/^0x[a-fA-F0-9]{40}$/', $address) === 1;
    }

    /**
     * Default implementation for making API requests
     */
    public function makeRequest(string $endpoint, array $params = []): array
    {
        // If endpoint is 'api' and apiUrl already ends with '/api', use apiUrl as-is
        if ($endpoint === 'api' && str_ends_with(rtrim($this->apiUrl, '/'), '/api')) {
            $url = rtrim($this->apiUrl, '/');
        } else {
            $url = rtrim($this->apiUrl, '/') . '/' . ltrim($endpoint, '/');
        }
        
        // Add API key to params
        $params['apikey'] = $this->apiKey;

        $attempts = 0;
        $lastException = null;

        while ($attempts < $this->retryAttempts) {
            try {
                Log::debug("Making API request to {$this->getName()}", [
                    'url' => $url,
                    'params' => array_merge($params, ['apikey' => '[REDACTED]']),
                    'attempt' => $attempts + 1
                ]);

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
                
                if (!is_array($data)) {
                    throw new InvalidArgumentException('Invalid JSON response from API');
                }

                // Check for API-specific error responses
                if (isset($data['status']) && $data['status'] === '0' && isset($data['message'])) {
                    throw new InvalidArgumentException("API Error: {$data['message']}");
                }

                Log::debug("Successful API request to blockchain explorer", [
                    'explorer' => $this->getName(),
                    'response_size' => strlen($response->body())
                ]);

                return $data;

            } catch (\Exception $e) {
                $lastException = $e;
                $attempts++;

                Log::warning("API request failed, attempt {$attempts}/{$this->retryAttempts}", [
                    'explorer' => $this->getName(),
                    'url' => $url,
                    'error' => $e->getMessage()
                ]);

                if ($attempts < $this->retryAttempts) {
                    usleep($this->retryDelay * 1000); // Convert to microseconds
                }
            }
        }

        Log::error("All API request attempts failed", [
            'explorer' => $this->getName(),
            'url' => $url,
            'attempts' => $attempts,
            'error' => $lastException->getMessage()
        ]);

        throw new InvalidArgumentException("API request failed after {$attempts} attempts: " . $lastException->getMessage());
    }

    public function getContractSource(string $contractAddress): array
    {
        if (!$this->validateAddress($contractAddress)) {
            throw new InvalidArgumentException("Invalid contract address format: {$contractAddress}");
        }

        $response = $this->makeRequest('api', [
            'module' => 'contract',
            'action' => 'getsourcecode',
            'address' => $contractAddress,
        ]);

        return $this->parseSourceCodeResponse($response, $contractAddress);
    }

    public function getContractAbi(string $contractAddress): array
    {
        if (!$this->validateAddress($contractAddress)) {
            throw new InvalidArgumentException("Invalid contract address format: {$contractAddress}");
        }

        $response = $this->makeRequest('api', [
            'module' => 'contract',
            'action' => 'getabi',
            'address' => $contractAddress,
        ]);

        if ($response['status'] !== '1') {
            throw new InvalidArgumentException(
                "Failed to fetch ABI: {$response['message']} (Contract: {$contractAddress})"
            );
        }

        return [
            'network' => $this->getNetwork(),
            'contract_address' => $contractAddress,
            'abi' => json_decode($response['result'], true),
            'fetched_at' => now()->toISOString(),
            'explorer' => $this->getName(),
        ];
    }

    public function getContractCreation(string $contractAddress): array
    {
        if (!$this->validateAddress($contractAddress)) {
            throw new InvalidArgumentException("Invalid contract address format: {$contractAddress}");
        }

        $response = $this->makeRequest('api', [
            'module' => 'contract',
            'action' => 'getcontractcreation',
            'contractaddresses' => $contractAddress,
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

        return [
            'network' => $this->getNetwork(),
            'contract_address' => $contractAddress,
            'creator_address' => $apiResult['contractCreator'],
            'creation_tx_hash' => $apiResult['txHash'],
            'fetched_at' => now()->toISOString(),
            'explorer' => $this->getName(),
        ];
    }

    public function getContractUrl(string $contractAddress): string
    {
        $baseUrl = str_replace('/api', '', $this->apiUrl);
        return "{$baseUrl}/address/{$contractAddress}";
    }

    /**
     * Get available API endpoints for this explorer
     */
    public function getAvailableEndpoints(): array
    {
        return [
            'contract_source' => [
                'module' => 'contract',
                'action' => 'getsourcecode',
                'description' => 'Get verified contract source code',
            ],
            'contract_abi' => [
                'module' => 'contract',
                'action' => 'getabi',
                'description' => 'Get contract ABI',
            ],
            'contract_creation' => [
                'module' => 'contract',
                'action' => 'getcontractcreation',
                'description' => 'Get contract creation transaction',
            ],
            'account_balance' => [
                'module' => 'account',
                'action' => 'balance',
                'description' => 'Get account balance',
            ],
            'account_transactions' => [
                'module' => 'account',
                'action' => 'txlist',
                'description' => 'Get account transactions',
            ],
        ];
    }


    protected function parseSourceCodeResponse(array $response, string $contractAddress): array
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
            'network' => $this->getNetwork(),
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
            'explorer' => $this->getName(),
        ];
    }
}