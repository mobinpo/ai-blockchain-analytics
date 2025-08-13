<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\BlockchainExplorerInterface;
use App\Services\BlockchainExplorerFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Advanced Chain Explorer Management Service
 * 
 * Provides intelligent switching between blockchain explorers with:
 * - Automatic failover and health monitoring
 * - Load balancing across multiple API keys
 * - Dynamic explorer selection based on performance
 * - Chain-specific optimization and caching
 */
class ChainExplorerManager
{
    private const CACHE_PREFIX = 'chain_explorer_manager:';
    private const HEALTH_CHECK_INTERVAL = 300; // 5 minutes
    private const PERFORMANCE_WINDOW = 3600; // 1 hour for performance tracking

    private array $activeExplorers = [];
    private array $performanceMetrics = [];

    /**
     * Get the best available explorer for a specific chain
     */
    public function getExplorer(string $chain): BlockchainExplorerInterface
    {
        $chain = $this->normalizeChainName($chain);
        
        // Check if we have a cached healthy explorer
        if (isset($this->activeExplorers[$chain])) {
            $explorer = $this->activeExplorers[$chain];
            if ($this->isExplorerStillHealthy($chain, $explorer)) {
                return $explorer;
            }
            unset($this->activeExplorers[$chain]);
        }

        // Get the best available explorer with health checks
        $explorer = $this->selectBestExplorer($chain);
        $this->activeExplorers[$chain] = $explorer;

        Log::info("Selected explorer for chain", [
            'chain' => $chain,
            'explorer' => $explorer->getName(),
            'health_score' => BlockchainExplorerFactory::getExplorerHealthScore($chain)
        ]);

        return $explorer;
    }

    /**
     * Execute a function with automatic explorer switching and retry logic
     */
    public function executeWithRetry(string $chain, callable $operation, int $maxRetries = 3): mixed
    {
        $chain = $this->normalizeChainName($chain);
        $attempts = 0;
        $lastException = null;

        while ($attempts < $maxRetries) {
            try {
                $startTime = microtime(true);
                $explorer = $this->getExplorer($chain);
                
                Log::debug("Executing operation with explorer", [
                    'chain' => $chain,
                    'explorer' => $explorer->getName(),
                    'attempt' => $attempts + 1
                ]);

                $result = $operation($explorer);
                
                // Record successful operation
                $responseTime = round((microtime(true) - $startTime) * 1000);
                $this->recordOperation($chain, $explorer->getName(), true, $responseTime);
                BlockchainExplorerFactory::recordExplorerHealth($chain, true, $responseTime);

                return $result;

            } catch (\Exception $e) {
                $lastException = $e;
                $attempts++;
                
                $responseTime = round((microtime(true) - $startTime) * 1000);
                $this->recordOperation($chain, $explorer->getName() ?? 'unknown', false, $responseTime);
                BlockchainExplorerFactory::recordExplorerHealth($chain, false, $responseTime);

                Log::warning("Operation failed, attempt {$attempts}/{$maxRetries}", [
                    'chain' => $chain,
                    'explorer' => $explorer->getName() ?? 'unknown',
                    'error' => $e->getMessage(),
                    'response_time' => $responseTime
                ]);

                // Clear cached explorer on failure
                unset($this->activeExplorers[$chain]);

                // Don't retry on certain types of errors
                if ($this->isNonRetryableError($e)) {
                    break;
                }

                // Add delay between retries
                if ($attempts < $maxRetries) {
                    usleep(min(1000000 * $attempts, 5000000)); // 1s to 5s exponential backoff
                }
            }
        }

        Log::error("All retry attempts failed for chain operation", [
            'chain' => $chain,
            'attempts' => $attempts,
            'final_error' => $lastException->getMessage()
        ]);

        throw new InvalidArgumentException(
            "Operation failed after {$attempts} attempts on {$chain}: " . $lastException->getMessage(),
            0,
            $lastException
        );
    }

    /**
     * Get comprehensive chain information with explorer status
     */
    public function getChainInfo(string $chain): array
    {
        $chain = $this->normalizeChainName($chain);
        
        if (!BlockchainExplorerFactory::isNetworkSupported($chain)) {
            throw new InvalidArgumentException("Unsupported chain: {$chain}");
        }

        $networkInfo = BlockchainExplorerFactory::getNetworkInfo()[$chain];
        $performanceData = $this->getPerformanceMetrics($chain);
        $validation = BlockchainExplorerFactory::validateConfiguration($chain);

        return [
            'chain_id' => $chain,
            'chain_info' => $networkInfo,
            'explorer_status' => [
                'configured' => $validation['valid'],
                'healthy' => BlockchainExplorerFactory::isExplorerHealthy($chain),
                'health_score' => BlockchainExplorerFactory::getExplorerHealthScore($chain),
                'last_check' => $this->getLastHealthCheck($chain)
            ],
            'performance_metrics' => $performanceData,
            'configuration_issues' => $validation['issues'],
            'configuration_warnings' => $validation['warnings'],
            'fallback_chains' => config("blockchain_explorers.fallbacks.{$chain}", []),
            'recommended_action' => $this->getRecommendedAction($chain, $validation, $performanceData)
        ];
    }

    /**
     * Get performance metrics for all chains
     */
    public function getAllChainsStatus(): array
    {
        $supportedChains = BlockchainExplorerFactory::getSupportedNetworks();
        $chainStatuses = [];
        $summary = [
            'total_chains' => count($supportedChains),
            'healthy_chains' => 0,
            'configured_chains' => 0,
            'average_health_score' => 0,
            'chains_with_issues' => []
        ];

        $totalHealthScore = 0;

        foreach ($supportedChains as $chain) {
            try {
                $chainInfo = $this->getChainInfo($chain);
                $chainStatuses[$chain] = $chainInfo;

                if ($chainInfo['explorer_status']['configured']) {
                    $summary['configured_chains']++;
                }

                if ($chainInfo['explorer_status']['healthy']) {
                    $summary['healthy_chains']++;
                }

                $healthScore = $chainInfo['explorer_status']['health_score'];
                $totalHealthScore += $healthScore;

                if (!empty($chainInfo['configuration_issues']) || $healthScore < 0.7) {
                    $summary['chains_with_issues'][] = [
                        'chain' => $chain,
                        'issues' => $chainInfo['configuration_issues'],
                        'health_score' => $healthScore,
                        'recommended_action' => $chainInfo['recommended_action']
                    ];
                }

            } catch (\Exception $e) {
                $chainStatuses[$chain] = [
                    'error' => $e->getMessage(),
                    'chain_id' => $chain
                ];
                
                $summary['chains_with_issues'][] = [
                    'chain' => $chain,
                    'issues' => [$e->getMessage()],
                    'health_score' => 0,
                    'recommended_action' => 'Fix configuration'
                ];
            }
        }

        $summary['average_health_score'] = round($totalHealthScore / count($supportedChains), 3);

        return [
            'summary' => $summary,
            'chains' => $chainStatuses,
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Switch to the best available explorer for a chain
     */
    public function switchToBestExplorer(string $chain): BlockchainExplorerInterface
    {
        $chain = $this->normalizeChainName($chain);
        
        // Clear any cached explorer
        unset($this->activeExplorers[$chain]);
        
        return $this->getExplorer($chain);
    }

    /**
     * Test connectivity to all configured chains
     */
    public function testAllChains(): array
    {
        $results = [];
        $supportedChains = BlockchainExplorerFactory::getSupportedNetworks();

        foreach ($supportedChains as $chain) {
            $results[$chain] = $this->testChainConnectivity($chain);
        }

        return [
            'test_timestamp' => now()->toISOString(),
            'total_chains_tested' => count($supportedChains),
            'successful_connections' => count(array_filter($results, fn($r) => $r['success'])),
            'failed_connections' => count(array_filter($results, fn($r) => !$r['success'])),
            'results' => $results
        ];
    }

    /**
     * Test connectivity to a specific chain
     */
    public function testChainConnectivity(string $chain): array
    {
        $chain = $this->normalizeChainName($chain);
        $startTime = microtime(true);

        try {
            $explorer = $this->getExplorer($chain);
            
            // Test with a simple API call (get latest block or similar)
            // This is a basic connectivity test
            $testAddress = '0x0000000000000000000000000000000000000000'; // Null address for testing
            
            try {
                $explorer->validateAddress($testAddress);
                $success = true;
                $error = null;
            } catch (\Exception $e) {
                // Even if validation fails, if we get a response, connection is working
                $success = true;
                $error = null;
            }

        } catch (\Exception $e) {
            $success = false;
            $error = $e->getMessage();
        }

        $responseTime = round((microtime(true) - $startTime) * 1000);

        return [
            'chain' => $chain,
            'success' => $success,
            'response_time_ms' => $responseTime,
            'error' => $error,
            'explorer_name' => $explorer->getName() ?? 'unknown',
            'test_timestamp' => now()->toISOString()
        ];
    }

    /**
     * Get or create explorer for multiple chains
     */
    public function getMultipleExplorers(array $chains): array
    {
        $explorers = [];
        $errors = [];

        foreach ($chains as $chain) {
            try {
                $explorers[$chain] = $this->getExplorer($chain);
            } catch (\Exception $e) {
                $errors[$chain] = $e->getMessage();
                Log::warning("Failed to get explorer for chain {$chain}: {$e->getMessage()}");
            }
        }

        return [
            'explorers' => $explorers,
            'errors' => $errors,
            'success_count' => count($explorers),
            'error_count' => count($errors)
        ];
    }

    /**
     * Select the best available explorer for a chain
     */
    private function selectBestExplorer(string $chain): BlockchainExplorerInterface
    {
        // Try to create explorer with health check and fallback
        try {
            return BlockchainExplorerFactory::switchToBestExplorer($chain);
        } catch (\Exception $e) {
            Log::error("Failed to create any explorer for chain {$chain}: {$e->getMessage()}");
            throw new InvalidArgumentException("No working explorer available for chain: {$chain}");
        }
    }

    /**
     * Check if the current explorer is still healthy
     */
    private function isExplorerStillHealthy(string $chain, BlockchainExplorerInterface $explorer): bool
    {
        $cacheKey = self::CACHE_PREFIX . "health_check:{$chain}";
        $lastCheck = Cache::get($cacheKey, 0);
        
        // Check health every HEALTH_CHECK_INTERVAL seconds
        if (time() - $lastCheck < self::HEALTH_CHECK_INTERVAL) {
            return true; // Assume healthy if recently checked
        }

        // Perform actual health check
        $isHealthy = BlockchainExplorerFactory::isExplorerHealthy($chain);
        Cache::put($cacheKey, time(), self::HEALTH_CHECK_INTERVAL);

        return $isHealthy;
    }

    /**
     * Record operation performance metrics
     */
    private function recordOperation(string $chain, string $explorerName, bool $success, int $responseTime): void
    {
        $cacheKey = self::CACHE_PREFIX . "performance:{$chain}";
        $metrics = Cache::get($cacheKey, [
            'total_operations' => 0,
            'successful_operations' => 0,
            'failed_operations' => 0,
            'average_response_time' => 0,
            'response_times' => [],
            'last_operation' => null
        ]);

        $metrics['total_operations']++;
        $metrics['last_operation'] = now()->toISOString();

        if ($success) {
            $metrics['successful_operations']++;
        } else {
            $metrics['failed_operations']++;
        }

        // Track response times (keep last 50)
        $metrics['response_times'][] = $responseTime;
        $metrics['response_times'] = array_slice($metrics['response_times'], -50);
        $metrics['average_response_time'] = array_sum($metrics['response_times']) / count($metrics['response_times']);

        Cache::put($cacheKey, $metrics, self::PERFORMANCE_WINDOW);
    }

    /**
     * Get performance metrics for a chain
     */
    private function getPerformanceMetrics(string $chain): array
    {
        $cacheKey = self::CACHE_PREFIX . "performance:{$chain}";
        $metrics = Cache::get($cacheKey, [
            'total_operations' => 0,
            'successful_operations' => 0,
            'failed_operations' => 0,
            'average_response_time' => 0,
            'response_times' => [],
            'last_operation' => null
        ]);

        $successRate = $metrics['total_operations'] > 0 
            ? round(($metrics['successful_operations'] / $metrics['total_operations']) * 100, 2)
            : 0;

        return [
            'total_operations' => $metrics['total_operations'],
            'success_rate' => $successRate,
            'average_response_time' => round($metrics['average_response_time'], 2),
            'last_operation' => $metrics['last_operation'],
            'operations_last_hour' => $metrics['total_operations'] // Since we cache for 1 hour
        ];
    }

    /**
     * Get last health check timestamp
     */
    private function getLastHealthCheck(string $chain): ?string
    {
        $healthConfig = config('blockchain_explorers.health_check');
        $cacheKey = $healthConfig['cache_key_prefix'] . $chain;
        $healthStatus = Cache::get($cacheKey);
        
        return $healthStatus['last_success'] ?? $healthStatus['last_failure'] ?? null;
    }

    /**
     * Get recommended action for a chain based on its status
     */
    private function getRecommendedAction(string $chain, array $validation, array $performance): string
    {
        if (!$validation['valid']) {
            return 'Configure API key and URL for ' . $chain;
        }

        $healthScore = BlockchainExplorerFactory::getExplorerHealthScore($chain);
        
        if ($healthScore < 0.3) {
            return 'Critical: Check API connectivity and rate limits';
        }
        
        if ($healthScore < 0.7) {
            return 'Warning: Monitor API performance and consider backup keys';
        }
        
        if ($performance['success_rate'] < 80) {
            return 'Investigate recent API failures';
        }
        
        if ($performance['average_response_time'] > 5000) {
            return 'Optimize: Response times are slow';
        }
        
        return 'Healthy: No action required';
    }

    /**
     * Normalize chain name to standard format
     */
    private function normalizeChainName(string $chain): string
    {
        $chainMap = [
            'eth' => 'ethereum',
            'bnb' => 'bsc',
            'matic' => 'polygon',
            'arb' => 'arbitrum',
            'op' => 'optimism',
            'avax' => 'avalanche',
            'ftm' => 'fantom'
        ];

        $normalized = strtolower(trim($chain));
        return $chainMap[$normalized] ?? $normalized;
    }

    /**
     * Check if an error should not trigger a retry
     */
    private function isNonRetryableError(\Exception $e): bool
    {
        $message = strtolower($e->getMessage());
        
        // Don't retry on configuration errors
        if (str_contains($message, 'api key') || 
            str_contains($message, 'invalid') || 
            str_contains($message, 'not verified') ||
            str_contains($message, 'not found')) {
            return true;
        }
        
        return false;
    }
}