<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\BlockchainExplorerInterface;
use App\Services\BlockchainExplorerFactory;
use App\Services\ChainDetectorService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Smart Chain Switching Service
 * 
 * Intelligently switches between blockchain explorers based on:
 * - Automatic chain detection
 * - Explorer health and performance
 * - Fallback mechanisms
 * - Load balancing across multiple API keys
 */
final class SmartChainSwitchingService
{
    private const CACHE_PREFIX = 'smart_chain_switch:';
    private const PERFORMANCE_CACHE_TTL = 1800; // 30 minutes
    private const SWITCH_COOLDOWN = 300; // 5 minutes cooldown between switches

    public function __construct(
        private readonly ChainDetectorService $chainDetector
    ) {}

    /**
     * Get the optimal explorer for a contract with intelligent switching
     */
    public function getOptimalExplorer(
        string $contractAddress, 
        ?string $preferredNetwork = null,
        array $options = []
    ): array {
        $this->validateAddress($contractAddress);
        
        $cacheKey = self::CACHE_PREFIX . 'optimal:' . strtolower($contractAddress) . ':' . ($preferredNetwork ?? 'auto');
        
        return Cache::remember($cacheKey, self::PERFORMANCE_CACHE_TTL, function () use ($contractAddress, $preferredNetwork, $options) {
            return $this->determineOptimalExplorer($contractAddress, $preferredNetwork, $options);
        });
    }

    /**
     * Execute an operation with automatic explorer switching and retry logic
     */
    public function executeWithSmartSwitching(
        string $contractAddress,
        callable $operation,
        array $options = []
    ): mixed {
        $maxRetries = $options['max_retries'] ?? 3;
        $preferredNetwork = $options['preferred_network'] ?? null;
        $fallbackNetworks = $options['fallback_networks'] ?? [];
        
        $attempts = 0;
        $lastException = null;
        
        // Get initial explorer selection
        $explorerInfo = $this->getOptimalExplorer($contractAddress, $preferredNetwork, $options);
        $currentNetwork = $explorerInfo['network'];
        $explorer = $explorerInfo['explorer'];
        
        while ($attempts < $maxRetries) {
            $attempts++;
            $startTime = microtime(true);
            
            try {
                Log::debug("Executing operation with explorer", [
                    'attempt' => $attempts,
                    'network' => $currentNetwork,
                    'explorer' => $explorer->getName(),
                    'contract' => $contractAddress
                ]);
                
                $result = $operation($explorer, $currentNetwork);
                
                // Record successful operation
                $responseTime = round((microtime(true) - $startTime) * 1000);
                BlockchainExplorerFactory::recordExplorerHealth($currentNetwork, true, $responseTime);
                
                return [
                    'result' => $result,
                    'network_used' => $currentNetwork,
                    'explorer_used' => $explorer->getName(),
                    'attempts_made' => $attempts,
                    'response_time_ms' => $responseTime,
                    'switched_explorer' => $attempts > 1
                ];
                
            } catch (\Exception $e) {
                $responseTime = round((microtime(true) - $startTime) * 1000);
                BlockchainExplorerFactory::recordExplorerHealth($currentNetwork, false, $responseTime);
                
                $lastException = $e;
                
                Log::warning("Operation failed, attempting to switch explorer", [
                    'attempt' => $attempts,
                    'network' => $currentNetwork,
                    'error' => $e->getMessage(),
                    'contract' => $contractAddress
                ]);
                
                // If not the last attempt, try to switch to a better explorer
                if ($attempts < $maxRetries) {
                    $switchResult = $this->switchToAlternativeExplorer(
                        $contractAddress, 
                        $currentNetwork, 
                        $fallbackNetworks
                    );
                    
                    if ($switchResult) {
                        $currentNetwork = $switchResult['network'];
                        $explorer = $switchResult['explorer'];
                        
                        Log::info("Switched to alternative explorer", [
                            'new_network' => $currentNetwork,
                            'new_explorer' => $explorer->getName(),
                            'reason' => 'Previous explorer failed'
                        ]);
                    } else {
                        Log::error("No alternative explorer available", [
                            'contract' => $contractAddress,
                            'failed_network' => $currentNetwork
                        ]);
                        break;
                    }
                }
            }
        }
        
        throw new InvalidArgumentException(
            "Operation failed after {$attempts} attempts. Last error: " . $lastException?->getMessage()
        );
    }

    /**
     * Get contract source with intelligent chain detection and switching
     */
    public function getContractSource(string $contractAddress, ?string $preferredNetwork = null): array
    {
        return $this->executeWithSmartSwitching(
            $contractAddress,
            function (BlockchainExplorerInterface $explorer, string $network) use ($contractAddress) {
                return $explorer->getContractSource($contractAddress);
            },
            ['preferred_network' => $preferredNetwork]
        );
    }

    /**
     * Check contract verification status across multiple chains with smart switching
     */
    public function getVerificationStatus(string $contractAddress): array
    {
        $detectionResults = $this->chainDetector->findVerifiedContract($contractAddress);
        
        if (empty($detectionResults['verified_on'])) {
            // If no verified contracts found, try to get basic contract info from any chain
            $basicInfo = $this->executeWithSmartSwitching(
                $contractAddress,
                function (BlockchainExplorerInterface $explorer, string $network) use ($contractAddress) {
                    return [
                        'exists' => $explorer->isContractVerified($contractAddress),
                        'network' => $network,
                        'explorer' => $explorer->getName()
                    ];
                }
            );
            
            return [
                'contract_address' => $contractAddress,
                'is_verified' => false,
                'verified_networks' => [],
                'available_on' => [$basicInfo['network_used']],
                'recommendation' => 'Contract exists but is not verified on any supported network'
            ];
        }
        
        return [
            'contract_address' => $contractAddress,
            'is_verified' => true,
            'verified_networks' => array_column($detectionResults['verified_on'], 'network'),
            'fastest_verified_network' => $detectionResults['fastest_verified_network'],
            'verification_details' => $detectionResults['verified_on'],
            'recommendation' => "Use {$detectionResults['fastest_verified_network']} for best performance"
        ];
    }

    /**
     * Determine the optimal explorer based on multiple factors
     */
    private function determineOptimalExplorer(
        string $contractAddress, 
        ?string $preferredNetwork, 
        array $options
    ): array {
        // First, detect which chains the contract exists on
        $detectionResults = $this->chainDetector->detectChain($contractAddress);
        
        if (empty($detectionResults['found_on'])) {
            throw new InvalidArgumentException("Contract not found on any supported network: {$contractAddress}");
        }
        
        $availableNetworks = $detectionResults['found_on'];
        
        // If preferred network is specified and available, check its health
        if ($preferredNetwork && in_array($preferredNetwork, $availableNetworks)) {
            $healthScore = BlockchainExplorerFactory::getExplorerHealthScore($preferredNetwork);
            
            // Use preferred network if it's reasonably healthy
            if ($healthScore > 0.5) {
                $explorer = BlockchainExplorerFactory::create($preferredNetwork);
                return [
                    'network' => $preferredNetwork,
                    'explorer' => $explorer,
                    'selection_reason' => 'Preferred network with acceptable health',
                    'health_score' => $healthScore,
                    'detection_results' => $detectionResults
                ];
            }
        }
        
        // Score all available networks based on multiple factors
        $networkScores = [];
        foreach ($availableNetworks as $network) {
            $networkScores[$network] = $this->calculateNetworkScore($network, $detectionResults, $options);
        }
        
        // Sort by score (highest first)
        arsort($networkScores);
        
        $optimalNetwork = array_key_first($networkScores);
        $explorer = BlockchainExplorerFactory::create($optimalNetwork);
        
        return [
            'network' => $optimalNetwork,
            'explorer' => $explorer,
            'selection_reason' => 'Highest composite score',
            'network_scores' => $networkScores,
            'detection_results' => $detectionResults
        ];
    }

    /**
     * Calculate a composite score for a network based on multiple factors
     */
    private function calculateNetworkScore(string $network, array $detectionResults, array $options): float
    {
        $score = 0;
        
        // Health score (0-1, weight: 40%)
        $healthScore = BlockchainExplorerFactory::getExplorerHealthScore($network);
        $score += $healthScore * 0.4;
        
        // Response time score (weight: 30%)
        $responseTime = $detectionResults['detection_results'][$network]['response_time_ms'] ?? 5000;
        $responseTimeScore = max(0, 1 - ($responseTime / 10000)); // Normalize to 0-1, 10s = 0
        $score += $responseTimeScore * 0.3;
        
        // Verification status (weight: 20%)
        $isVerified = $detectionResults['detection_results'][$network]['verified'] ?? false;
        $verificationScore = $isVerified ? 1 : 0.5;
        $score += $verificationScore * 0.2;
        
        // Network priority (weight: 10%)
        $networkPriorities = [
            'ethereum' => 1.0,
            'bsc' => 0.9,
            'polygon' => 0.8,
            'arbitrum' => 0.7,
            'optimism' => 0.7,
            'avalanche' => 0.6,
            'fantom' => 0.5
        ];
        $priorityScore = $networkPriorities[$network] ?? 0.3;
        $score += $priorityScore * 0.1;
        
        return round($score, 3);
    }

    /**
     * Switch to an alternative explorer when the current one fails
     */
    private function switchToAlternativeExplorer(
        string $contractAddress,
        string $failedNetwork,
        array $fallbackNetworks = []
    ): ?array {
        // Get detection results
        $detectionResults = $this->chainDetector->getCachedDetection($contractAddress);
        
        if (!$detectionResults) {
            // Perform quick re-detection if no cached results
            $detectionResults = $this->chainDetector->detectChain($contractAddress);
        }
        
        $availableNetworks = array_diff($detectionResults['found_on'], [$failedNetwork]);
        
        // Add fallback networks if specified
        if (!empty($fallbackNetworks)) {
            $availableNetworks = array_merge($availableNetworks, $fallbackNetworks);
            $availableNetworks = array_unique($availableNetworks);
        }
        
        if (empty($availableNetworks)) {
            return null;
        }
        
        // Find the best alternative based on health scores
        $bestNetwork = null;
        $bestScore = 0;
        
        foreach ($availableNetworks as $network) {
            if (!BlockchainExplorerFactory::isNetworkSupported($network)) {
                continue;
            }
            
            $healthScore = BlockchainExplorerFactory::getExplorerHealthScore($network);
            
            if ($healthScore > $bestScore) {
                $bestScore = $healthScore;
                $bestNetwork = $network;
            }
        }
        
        if (!$bestNetwork) {
            return null;
        }
        
        try {
            $explorer = BlockchainExplorerFactory::create($bestNetwork);
            return [
                'network' => $bestNetwork,
                'explorer' => $explorer,
                'health_score' => $bestScore
            ];
        } catch (\Exception $e) {
            Log::error("Failed to create alternative explorer", [
                'network' => $bestNetwork,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get comprehensive chain switching statistics
     */
    public function getChainSwitchingStats(): array
    {
        $networks = BlockchainExplorerFactory::getSupportedNetworks();
        $systemHealth = BlockchainExplorerFactory::getSystemHealthReport();
        $detectorStats = $this->chainDetector->getDetectionStats();
        
        return [
            'timestamp' => now()->toISOString(),
            'system_health' => $systemHealth,
            'detector_stats' => $detectorStats,
            'switching_configuration' => [
                'performance_cache_ttl' => self::PERFORMANCE_CACHE_TTL,
                'switch_cooldown' => self::SWITCH_COOLDOWN,
                'supported_networks' => $networks
            ],
            'recommendations' => $this->generateRecommendations($systemHealth)
        ];
    }

    /**
     * Generate system recommendations based on health metrics
     */
    private function generateRecommendations(array $systemHealth): array
    {
        $recommendations = [];
        
        if ($systemHealth['average_health_score'] < 0.7) {
            $recommendations[] = 'System health is below optimal - consider checking API configurations';
        }
        
        if ($systemHealth['unhealthy_networks'] > 2) {
            $recommendations[] = 'Multiple networks are unhealthy - review API keys and rate limits';
        }
        
        if ($systemHealth['configured_networks'] < 5) {
            $recommendations[] = 'Consider configuring more blockchain explorers for better redundancy';
        }
        
        $recommendations[] = 'Smart switching is active - the system will automatically use the best available explorer';
        
        return $recommendations;
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
     * Clear all switching caches for a contract
     */
    public function clearSwitchingCache(string $contractAddress): bool
    {
        $contractAddress = strtolower($contractAddress);
        $cacheKey = self::CACHE_PREFIX . 'optimal:' . $contractAddress;
        
        // Clear all variations of the cache key
        $cleared = 0;
        $patterns = [
            $cacheKey . ':auto',
            $cacheKey . ':ethereum',
            $cacheKey . ':bsc',
            $cacheKey . ':polygon',
            $cacheKey . ':arbitrum',
            $cacheKey . ':optimism',
            $cacheKey . ':avalanche',
            $cacheKey . ':fantom'
        ];
        
        foreach ($patterns as $pattern) {
            if (Cache::forget($pattern)) {
                $cleared++;
            }
        }
        
        // Also clear chain detection cache
        $this->chainDetector->clearDetectionCache($contractAddress);
        
        return $cleared > 0;
    }
}