<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\BlockchainExplorerFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * Intelligent Chain Detection Service
 * 
 * Automatically detects which blockchain network a contract address belongs to
 * by analyzing contract data across multiple explorers simultaneously.
 */
final class ChainDetectorService
{
    private const CACHE_PREFIX = 'chain_detector:';
    private const CACHE_TTL = 3600; // 1 hour
    private const DETECTION_TIMEOUT = 10; // seconds per request
    private const PARALLEL_CHECKS = 3; // Number of simultaneous checks

    /**
     * Detect which chain(s) a contract address exists on
     */
    public function detectChain(string $contractAddress): array
    {
        $this->validateAddress($contractAddress);
        $contractAddress = strtolower($contractAddress);
        
        $cacheKey = self::CACHE_PREFIX . $contractAddress;
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($contractAddress) {
            return $this->performChainDetection($contractAddress);
        });
    }

    /**
     * Detect the most likely primary chain for a contract
     */
    public function detectPrimaryChain(string $contractAddress): ?string
    {
        $results = $this->detectChain($contractAddress);
        
        if (empty($results['found_on'])) {
            return null;
        }

        // Priority order: Ethereum, BSC, Polygon, then others
        $priorityChains = ['ethereum', 'bsc', 'polygon', 'arbitrum', 'optimism', 'avalanche', 'fantom'];
        
        foreach ($priorityChains as $chain) {
            if (in_array($chain, $results['found_on'])) {
                return $chain;
            }
        }

        // Return first found if no priority match
        return $results['found_on'][0] ?? null;
    }

    /**
     * Check if a contract exists on a specific chain
     */
    public function existsOnChain(string $contractAddress, string $network): bool
    {
        try {
            $explorer = BlockchainExplorerFactory::create($network);
            $result = $explorer->getContractSource($contractAddress);
            
            return isset($result['is_verified']) || 
                   isset($result['contract_address']) ||
                   !empty($result['source_code']);
        } catch (\Exception $e) {
            Log::debug("Contract check failed on {$network}", [
                'address' => $contractAddress,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get the best explorer for a contract address with automatic detection
     */
    public function getBestExplorer(string $contractAddress, ?string $preferredNetwork = null): array
    {
        $detection = $this->detectChain($contractAddress);
        
        if (empty($detection['found_on'])) {
            throw new InvalidArgumentException("Contract not found on any supported network: {$contractAddress}");
        }

        // If preferred network is specified and available, use it
        if ($preferredNetwork && in_array($preferredNetwork, $detection['found_on'])) {
            $explorer = BlockchainExplorerFactory::create($preferredNetwork);
            return [
                'network' => $preferredNetwork,
                'explorer' => $explorer,
                'detection_results' => $detection
            ];
        }

        // Otherwise, use the primary detected chain
        $primaryChain = $this->detectPrimaryChain($contractAddress);
        $explorer = BlockchainExplorerFactory::create($primaryChain);

        return [
            'network' => $primaryChain,
            'explorer' => $explorer,
            'detection_results' => $detection
        ];
    }

    /**
     * Perform parallel chain detection across all supported networks
     */
    private function performChainDetection(string $contractAddress): array
    {
        $networks = BlockchainExplorerFactory::getSupportedNetworks();
        $results = [];
        $foundOn = [];
        $errors = [];

        Log::info("Starting chain detection", [
            'address' => $contractAddress,
            'networks_to_check' => count($networks)
        ]);

        // Group networks for parallel processing
        $networkChunks = array_chunk($networks, self::PARALLEL_CHECKS);

        foreach ($networkChunks as $chunk) {
            $promises = [];
            
            foreach ($chunk as $network) {
                $promises[$network] = $this->createDetectionPromise($network, $contractAddress);
            }

            // Wait for all promises in this chunk to complete
            foreach ($promises as $network => $promise) {
                try {
                    $result = $promise();
                    $results[$network] = $result;
                    
                    if ($result['exists']) {
                        $foundOn[] = $network;
                        Log::debug("Contract found on {$network}", [
                            'address' => $contractAddress,
                            'verified' => $result['verified']
                        ]);
                    }
                } catch (\Exception $e) {
                    $errors[$network] = $e->getMessage();
                    Log::debug("Detection failed for {$network}", [
                        'address' => $contractAddress,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        return [
            'contract_address' => $contractAddress,
            'found_on' => $foundOn,
            'total_networks_checked' => count($networks),
            'successful_checks' => count($results),
            'failed_checks' => count($errors),
            'detection_results' => $results,
            'errors' => $errors,
            'detected_at' => now()->toISOString(),
            'cache_ttl' => self::CACHE_TTL
        ];
    }

    /**
     * Create a detection promise for a specific network
     */
    private function createDetectionPromise(string $network, string $contractAddress): callable
    {
        return function () use ($network, $contractAddress) {
            $startTime = microtime(true);
            
            try {
                $explorer = BlockchainExplorerFactory::create($network);
                
                // Quick check: try to get basic contract info
                $result = $explorer->getContractSource($contractAddress);
                
                $exists = !empty($result['contract_address']) || 
                         !empty($result['source_code']) ||
                         isset($result['is_verified']);
                
                $verified = $result['is_verified'] ?? false;
                
                $responseTime = round((microtime(true) - $startTime) * 1000, 2);
                
                // Record health metrics
                BlockchainExplorerFactory::recordExplorerHealth($network, true, (int)$responseTime);
                
                return [
                    'exists' => $exists,
                    'verified' => $verified,
                    'response_time_ms' => $responseTime,
                    'explorer_name' => $explorer->getName(),
                    'chain_id' => $explorer->getChainId(),
                    'contract_url' => $exists ? $explorer->getContractUrl($contractAddress) : null
                ];
            } catch (\Exception $e) {
                $responseTime = round((microtime(true) - $startTime) * 1000, 2);
                
                // Record failure
                BlockchainExplorerFactory::recordExplorerHealth($network, false, (int)$responseTime);
                
                throw $e;
            }
        };
    }

    /**
     * Validate Ethereum-style address format
     */
    private function validateAddress(string $address): void
    {
        if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
            throw new InvalidArgumentException("Invalid contract address format: {$address}");
        }
    }

    /**
     * Get cached detection results without performing new detection
     */
    public function getCachedDetection(string $contractAddress): ?array
    {
        $contractAddress = strtolower($contractAddress);
        $cacheKey = self::CACHE_PREFIX . $contractAddress;
        
        return Cache::get($cacheKey);
    }

    /**
     * Clear detection cache for a specific address
     */
    public function clearDetectionCache(string $contractAddress): bool
    {
        $contractAddress = strtolower($contractAddress);
        $cacheKey = self::CACHE_PREFIX . $contractAddress;
        
        return Cache::forget($cacheKey);
    }

    /**
     * Get chain detection statistics
     */
    public function getDetectionStats(): array
    {
        $networks = BlockchainExplorerFactory::getSupportedNetworks();
        $stats = [
            'total_supported_networks' => count($networks),
            'network_health_scores' => [],
            'average_system_health' => 0,
            'detection_cache_info' => [
                'ttl_seconds' => self::CACHE_TTL,
                'timeout_per_request' => self::DETECTION_TIMEOUT,
                'parallel_checks' => self::PARALLEL_CHECKS
            ]
        ];

        $totalHealth = 0;
        foreach ($networks as $network) {
            $healthScore = BlockchainExplorerFactory::getExplorerHealthScore($network);
            $stats['network_health_scores'][$network] = $healthScore;
            $totalHealth += $healthScore;
        }

        $stats['average_system_health'] = round($totalHealth / count($networks), 3);

        return $stats;
    }

    /**
     * Perform a smart contract verification check across all chains
     */
    public function findVerifiedContract(string $contractAddress): array
    {
        $detection = $this->detectChain($contractAddress);
        $verifiedOn = [];

        foreach ($detection['detection_results'] as $network => $result) {
            if ($result['exists'] && $result['verified']) {
                $verifiedOn[] = [
                    'network' => $network,
                    'response_time_ms' => $result['response_time_ms'],
                    'contract_url' => $result['contract_url'],
                    'explorer_name' => $result['explorer_name']
                ];
            }
        }

        // Sort by response time (fastest first)
        usort($verifiedOn, fn($a, $b) => $a['response_time_ms'] <=> $b['response_time_ms']);

        return [
            'contract_address' => $contractAddress,
            'verified_on' => $verifiedOn,
            'total_verified_networks' => count($verifiedOn),
            'fastest_verified_network' => $verifiedOn[0]['network'] ?? null,
            'detection_summary' => $detection
        ];
    }
}