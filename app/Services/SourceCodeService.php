<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\BlockchainExplorerInterface;
use App\Services\MultiChainExplorerManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class SourceCodeService
{
    public function __construct(
        private readonly MultiChainExplorerManager $explorerManager
    ) {
    }

    /**
     * Fetch verified Solidity source code from blockchain explorers
     */
    public function fetchSourceCode(string $contractAddress, ?string $network = null): array
    {
        $cacheKey = "source_code:{$network}:{$contractAddress}";
        
        return Cache::remember($cacheKey, 3600, function () use ($contractAddress, $network) {
            if ($network) {
                return $this->fetchFromSpecificNetwork($contractAddress, $network);
            }

            return $this->fetchFromAutoDetectedNetwork($contractAddress);
        });
    }

    /**
     * Fetch source code from a specific network
     */
    public function fetchFromSpecificNetwork(string $contractAddress, string $network): array
    {
        $explorer = $this->explorerManager->getExplorer($network);
        
        if (!$explorer) {
            throw new InvalidArgumentException("Unsupported network: {$network}");
        }

        try {
            $sourceData = $explorer->getContractSource($contractAddress);
            
            // Enhance the response with additional metadata
            return $this->enhanceSourceCodeResponse($sourceData, $explorer);
            
        } catch (\Exception $e) {
            Log::error("Failed to fetch source code from {$network}", [
                'contract' => $contractAddress,
                'network' => $network,
                'error' => $e->getMessage()
            ]);
            
            throw new InvalidArgumentException(
                "Failed to fetch source code from {$network}: " . $e->getMessage()
            );
        }
    }

    /**
     * Fetch source code by auto-detecting the network
     */
    public function fetchFromAutoDetectedNetwork(string $contractAddress): array
    {
        $detectedChains = $this->explorerManager->detectChainForAddress($contractAddress);
        
        if (empty($detectedChains['matches'])) {
            throw new InvalidArgumentException("Could not detect network for address: {$contractAddress}");
        }

        $errors = [];
        
        foreach ($detectedChains['matches'] as $chainData) {
            try {
                return $this->fetchFromSpecificNetwork($contractAddress, $chainData['network']);
            } catch (\Exception $e) {
                $errors[$chainData['network']] = $e->getMessage();
                Log::debug("Failed to fetch from {$chainData['network']}, trying next", [
                    'contract' => $contractAddress,
                    'error' => $e->getMessage()
                ]);
            }
        }

        throw new InvalidArgumentException(
            "Failed to fetch source code from any detected network. Errors: " . json_encode($errors)
        );
    }

    /**
     * Fetch ABI for a verified contract
     */
    public function fetchContractAbi(string $contractAddress, ?string $network = null): array
    {
        $cacheKey = "contract_abi:{$network}:{$contractAddress}";
        
        return Cache::remember($cacheKey, 3600, function () use ($contractAddress, $network) {
            if ($network) {
                $explorer = $this->explorerManager->getExplorer($network);
            } else {
                $detectedChains = $this->explorerManager->detectChainForAddress($contractAddress);
                $explorer = $this->explorerManager->getExplorer($detectedChains['best_match']['network'] ?? 'ethereum');
            }

            if (!$explorer) {
                throw new InvalidArgumentException("Could not determine explorer for contract");
            }

            return $explorer->getContractAbi($contractAddress);
        });
    }

    /**
     * Get contract creation information
     */
    public function getContractCreation(string $contractAddress, ?string $network = null): array
    {
        $cacheKey = "contract_creation:{$network}:{$contractAddress}";
        
        return Cache::remember($cacheKey, 7200, function () use ($contractAddress, $network) {
            if ($network) {
                $explorer = $this->explorerManager->getExplorer($network);
            } else {
                $detectedChains = $this->explorerManager->detectChainForAddress($contractAddress);
                $explorer = $this->explorerManager->getExplorer($detectedChains['best_match']['network'] ?? 'ethereum');
            }

            if (!$explorer) {
                throw new InvalidArgumentException("Could not determine explorer for contract");
            }

            return $explorer->getContractCreation($contractAddress);
        });
    }

    /**
     * Check if a contract is verified on any supported network
     */
    public function isContractVerified(string $contractAddress, ?string $network = null): array
    {
        if ($network) {
            $explorer = $this->explorerManager->getExplorer($network);
            return [
                'is_verified' => $explorer?->isContractVerified($contractAddress) ?? false,
                'network' => $network,
                'explorer' => $explorer?->getName(),
            ];
        }

        // Check across multiple networks
        $detectedChains = $this->explorerManager->detectChainForAddress($contractAddress);
        $results = [];
        
        foreach ($detectedChains['matches'] as $chainData) {
            $explorer = $this->explorerManager->getExplorer($chainData['network']);
            if ($explorer) {
                $isVerified = $explorer->isContractVerified($contractAddress);
                $results[] = [
                    'network' => $chainData['network'],
                    'explorer' => $explorer->getName(),
                    'is_verified' => $isVerified,
                ];
                
                if ($isVerified) {
                    break; // Found verified contract, no need to check others
                }
            }
        }

        return [
            'address' => $contractAddress,
            'verification_status' => $results,
            'has_verified_contract' => collect($results)->some(fn($result) => $result['is_verified']),
        ];
    }

    /**
     * Search for contracts by source code patterns
     */
    public function searchBySourcePattern(array $addresses, string $pattern, ?string $network = null): array
    {
        $results = [];
        
        foreach ($addresses as $address) {
            try {
                $sourceData = $this->fetchSourceCode($address, $network);
                
                if ($this->containsPattern($sourceData, $pattern)) {
                    $results[] = [
                        'address' => $address,
                        'network' => $sourceData['network'],
                        'contract_name' => $sourceData['contract_name'],
                        'matches' => $this->findPatternMatches($sourceData, $pattern),
                    ];
                }
            } catch (\Exception $e) {
                Log::debug("Failed to search pattern in {$address}: " . $e->getMessage());
            }
        }

        return [
            'pattern' => $pattern,
            'total_checked' => count($addresses),
            'matches_found' => count($results),
            'results' => $results,
        ];
    }

    /**
     * Extract function signatures from contract source
     */
    public function extractFunctionSignatures(string $contractAddress, ?string $network = null): array
    {
        $sourceData = $this->fetchSourceCode($contractAddress, $network);
        $functions = [];

        foreach ($sourceData['parsed_sources'] as $filename => $source) {
            $functions = array_merge($functions, $this->parseFunctionSignatures($source));
        }

        return [
            'contract_address' => $contractAddress,
            'network' => $sourceData['network'],
            'contract_name' => $sourceData['contract_name'],
            'functions' => array_unique($functions, SORT_REGULAR),
            'total_functions' => count($functions),
        ];
    }

    /**
     * Get comprehensive contract information
     */
    public function getContractInfo(string $contractAddress, ?string $network = null): array
    {
        try {
            $sourceData = $this->fetchSourceCode($contractAddress, $network);
            $creationData = $this->getContractCreation($contractAddress, $network);
            $functions = $this->extractFunctionSignatures($contractAddress, $network);
            
            return [
                'basic_info' => [
                    'address' => $contractAddress,
                    'network' => $sourceData['network'],
                    'name' => $sourceData['contract_name'],
                    'is_verified' => $sourceData['is_verified'],
                    'is_proxy' => $sourceData['proxy'],
                ],
                'compilation_info' => [
                    'compiler_version' => $sourceData['compiler_version'],
                    'optimization_used' => $sourceData['optimization_used'],
                    'optimization_runs' => $sourceData['optimization_runs'],
                    'evm_version' => $sourceData['evm_version'],
                    'license_type' => $sourceData['license_type'],
                ],
                'source_info' => [
                    'source_files' => count($sourceData['parsed_sources']),
                    'total_lines' => $this->countSourceLines($sourceData['parsed_sources']),
                    'has_libraries' => !empty($sourceData['library']),
                ],
                'creation_info' => $creationData,
                'function_info' => [
                    'total_functions' => $functions['total_functions'],
                    'public_functions' => count(array_filter($functions['functions'], fn($f) => str_contains($f, 'public'))),
                    'external_functions' => count(array_filter($functions['functions'], fn($f) => str_contains($f, 'external'))),
                ],
                'fetched_at' => now()->toISOString(),
            ];
        } catch (\Exception $e) {
            return [
                'address' => $contractAddress,
                'network' => $network,
                'error' => $e->getMessage(),
                'is_verified' => false,
                'fetched_at' => now()->toISOString(),
            ];
        }
    }

    /**
     * Enhance source code response with additional metadata
     */
    private function enhanceSourceCodeResponse(array $sourceData, BlockchainExplorerInterface $explorer): array
    {
        $sourceData['explorer_info'] = [
            'name' => $explorer->getName(),
            'web_url' => $explorer->getContractUrl($sourceData['contract_address']),
            'chain_id' => $explorer->getChainId(),
            'native_currency' => $explorer->getNativeCurrency(),
        ];

        $sourceData['source_stats'] = [
            'total_files' => count($sourceData['parsed_sources']),
            'total_lines' => $this->countSourceLines($sourceData['parsed_sources']),
            'file_sizes' => array_map('strlen', $sourceData['parsed_sources']),
        ];

        return $sourceData;
    }

    /**
     * Check if source code contains a pattern
     */
    private function containsPattern(array $sourceData, string $pattern): bool
    {
        foreach ($sourceData['parsed_sources'] as $source) {
            if (preg_match("/{$pattern}/i", $source)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Find pattern matches in source code
     */
    private function findPatternMatches(array $sourceData, string $pattern): array
    {
        $matches = [];
        
        foreach ($sourceData['parsed_sources'] as $filename => $source) {
            preg_match_all("/{$pattern}/i", $source, $fileMatches, PREG_OFFSET_CAPTURE);
            if (!empty($fileMatches[0])) {
                $matches[$filename] = $fileMatches[0];
            }
        }

        return $matches;
    }

    /**
     * Parse function signatures from Solidity source code
     */
    private function parseFunctionSignatures(string $source): array
    {
        $functions = [];
        
        // Match function declarations
        preg_match_all(
            '/function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\([^)]*\)\s*(?:public|external|internal|private)?\s*(?:view|pure|payable)?\s*(?:returns\s*\([^)]*\))?\s*[{;]/i',
            $source,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $functions[] = trim($match[0], ' {;');
        }

        return $functions;
    }

    /**
     * Count total lines in all source files
     */
    private function countSourceLines(array $sources): int
    {
        $totalLines = 0;
        foreach ($sources as $source) {
            $totalLines += substr_count($source, "\n") + 1;
        }
        return $totalLines;
    }
}