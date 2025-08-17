<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\BlockchainExplorerInterface;
use App\Services\BlockchainExplorerFactory;
use App\Models\ApiUsageTracking;
use App\Services\Explorers\EtherscanExplorer;
use App\Services\Explorers\BscscanExplorer;
use App\Services\Explorers\PolygonscanExplorer;
use App\Services\Explorers\ArbiscanExplorer;
use App\Services\Explorers\OptimisticEtherscanExplorer;
use App\Services\Explorers\FtmscanExplorer;
use App\Services\Explorers\SnowtraceExplorer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use Carbon\Carbon;

/**
 * Advanced Multi-Chain Explorer Manager with intelligent switching
 * 
 * Features:
 * - Automatic explorer selection based on health and performance
 * - Load balancing across multiple API keys
 * - Intelligent failover with circuit breaker pattern
 * - Performance analytics and optimization
 * - Rate limit management across chains
 * - Smart retry logic with exponential backoff
 */
final class MultiChainExplorerManager
{
    private array $explorerInstances = [];
    private array $performanceMetrics = [];
    
    // Circuit breaker states
    private const CIRCUIT_CLOSED = 'closed';      // Normal operation
    private const CIRCUIT_OPEN = 'open';          // Failing, don't try
    private const CIRCUIT_HALF_OPEN = 'half_open'; // Testing if recovered
    
    // Performance thresholds
    private const PERFORMANCE_THRESHOLDS = [
        'response_time_ms' => 5000,    // 5 seconds max
        'success_rate' => 0.85,        // 85% success rate min
        'failure_count' => 5,          // Max consecutive failures
        'circuit_timeout' => 300       // 5 minutes circuit open time
    ];

    // Supported networks with priorities
    private const NETWORK_PRIORITIES = [
        'ethereum' => 1,    // Highest priority
        'bsc' => 2,
        'polygon' => 3,
        'arbitrum' => 4,
        'optimism' => 5,
        'avalanche' => 6,
        'fantom' => 7
    ];

    /**
     * Get the best available explorer for a network with intelligent selection
     */
    public function getBestExplorer(string $network): BlockchainExplorerInterface
    {
        // Check circuit breaker state
        if ($this->isCircuitOpen($network)) {
            Log::warning("Circuit breaker open for {$network}, attempting fallback");
            return $this->getFallbackExplorer($network);
        }

        // Try to get cached healthy explorer
        $cachedExplorer = $this->getCachedHealthyExplorer($network);
        if ($cachedExplorer) {
            return $cachedExplorer;
        }

        // Create new explorer with health check
        try {
            $explorer = $this->createExplorerWithHealthCheck($network);
            $this->cacheHealthyExplorer($network, $explorer);
            return $explorer;
        } catch (\Exception $e) {
            Log::error("Failed to create explorer for {$network}: {$e->getMessage()}");
            $this->recordCircuitBreakerFailure($network);
            
            // Try fallback
            return $this->getFallbackExplorer($network);
        }
    }

    /**
     * Execute operation with automatic retry and failover
     */
    public function executeWithRetry(
        string $network,
        callable $operation,
        int $maxRetries = 3,
        int $baseDelayMs = 1000
    ): mixed {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxRetries) {
            try {
                $explorer = $this->getBestExplorer($network);
                $startTime = microtime(true);
                
                $result = $operation($explorer);
                
                $responseTime = (microtime(true) - $startTime) * 1000;
                $this->recordOperationSuccess($network, $responseTime);
                
                return $result;

            } catch (\Exception $e) {
                $lastException = $e;
                $attempt++;
                
                Log::warning("Operation failed for {$network}, attempt {$attempt}/{$maxRetries}", [
                    'error' => $e->getMessage(),
                    'network' => $network
                ]);
                
                $this->recordOperationFailure($network, $e);
                
                // Exponential backoff with jitter
                if ($attempt < $maxRetries) {
                    $delay = $baseDelayMs * (2 ** ($attempt - 1));
                    $jitter = rand(0, (int)($delay * 0.1));
                    usleep(($delay + $jitter) * 1000);
                }
                
                // Switch to different explorer for next attempt
                $this->invalidateExplorerCache($network);
            }
        }

        // All retries failed
        $this->recordCircuitBreakerFailure($network);
        throw $lastException;
    }

    /**
     * Get explorers for multiple networks with load balancing
     */
    public function getMultiChainExplorers(array $networks): array
    {
        $explorers = [];
        
        // Sort networks by priority to ensure important ones are processed first
        $sortedNetworks = $this->sortNetworksByPriority($networks);
        
        foreach ($sortedNetworks as $network) {
            try {
                $explorers[$network] = $this->getBestExplorer($network);
            } catch (\Exception $e) {
                Log::error("Failed to get explorer for {$network}: {$e->getMessage()}");
                // Continue with other networks
            }
        }
        
        return $explorers;
    }

    /**
     * Execute operation across multiple chains with intelligent distribution
     */
    public function executeMultiChain(
        array $networks,
        callable $operation,
        bool $failFast = false
    ): array {
        $results = [];
        $errors = [];
        
        $sortedNetworks = $this->sortNetworksByPriority($networks);
        
        foreach ($sortedNetworks as $network) {
            try {
                $result = $this->executeWithRetry($network, $operation);
                $results[$network] = $result;
                
            } catch (\Exception $e) {
                $errors[$network] = $e->getMessage();
                
                if ($failFast) {
                    break;
                }
            }
        }
        
        return [
            'successful' => $results,
            'failed' => $errors,
            'summary' => [
                'total_networks' => count($networks),
                'successful_count' => count($results),
                'failed_count' => count($errors),
                'success_rate' => count($networks) > 0 ? (count($results) / count($networks)) * 100 : 0
            ]
        ];
    }

    /**
     * Get comprehensive performance analytics
     */
    public function getPerformanceAnalytics(int $hours = 24): array
    {
        $startTime = now()->subHours($hours);
        
        $analytics = [];
        foreach (array_keys(self::NETWORK_PRIORITIES) as $network) {
            $analytics[$network] = $this->getNetworkPerformanceMetrics($network, $startTime);
        }
        
        // Calculate system-wide metrics
        $systemMetrics = $this->calculateSystemMetrics($analytics);
        
        return [
            'period_hours' => $hours,
            'start_time' => $startTime->toISOString(),
            'end_time' => now()->toISOString(),
            'network_metrics' => $analytics,
            'system_metrics' => $systemMetrics,
            'recommendations' => $this->generatePerformanceRecommendations($analytics)
        ];
    }

    /**
     * Optimize explorer selection based on current performance
     */
    public function optimizeExplorerSelection(): array
    {
        $optimizations = [];
        
        foreach (array_keys(self::NETWORK_PRIORITIES) as $network) {
            $metrics = $this->getNetworkPerformanceMetrics($network);
            
            // Close circuit breakers for recovered networks
            if ($this->isCircuitOpen($network) && $metrics['recent_success_rate'] > 0.9) {
                $this->closeCircuitBreaker($network);
                $optimizations[] = "Closed circuit breaker for {$network} - performance recovered";
            }
            
            // Open circuit breakers for failing networks
            if (!$this->isCircuitOpen($network) && $metrics['recent_success_rate'] < 0.5) {
                $this->openCircuitBreaker($network);
                $optimizations[] = "Opened circuit breaker for {$network} - poor performance detected";
            }
            
            // Clear cache for slow networks
            if ($metrics['avg_response_time'] > self::PERFORMANCE_THRESHOLDS['response_time_ms']) {
                $this->invalidateExplorerCache($network);
                $optimizations[] = "Cleared cache for {$network} - high response times detected";
            }
        }
        
        return $optimizations;
    }

    /**
     * Get network status with health indicators
     */
    public function getNetworkStatus(): array
    {
        $status = [];
        
        foreach (array_keys(self::NETWORK_PRIORITIES) as $network) {
            $healthScore = BlockchainExplorerFactory::getExplorerHealthScore($network);
            $circuitState = $this->getCircuitBreakerState($network);
            $metrics = $this->getNetworkPerformanceMetrics($network, now()->subHour());
            
            $status[$network] = [
                'network' => $network,
                'priority' => self::NETWORK_PRIORITIES[$network],
                'health_score' => $healthScore,
                'health_status' => $this->getHealthStatusFromScore($healthScore),
                'circuit_breaker' => $circuitState,
                'is_available' => $circuitState !== self::CIRCUIT_OPEN,
                'avg_response_time' => $metrics['avg_response_time'],
                'success_rate' => $metrics['success_rate'],
                'total_requests' => $metrics['total_requests'],
                'recent_failures' => $metrics['recent_failures'],
                'last_success' => $metrics['last_success'],
                'last_failure' => $metrics['last_failure'],
                'recommendations' => $this->getNetworkRecommendations($network, $metrics)
            ];
        }
        
        return $status;
    }

    /**
     * Force refresh all explorer instances
     */
    public function refreshAllExplorers(): array
    {
        $refreshed = [];
        
        foreach (array_keys(self::NETWORK_PRIORITIES) as $network) {
            try {
                $this->invalidateExplorerCache($network);
                $explorer = $this->createExplorerWithHealthCheck($network);
                $this->cacheHealthyExplorer($network, $explorer);
                $refreshed[] = $network;
            } catch (\Exception $e) {
                Log::error("Failed to refresh explorer for {$network}: {$e->getMessage()}");
            }
        }
        
        return $refreshed;
    }

    private function createExplorerWithHealthCheck(string $network): BlockchainExplorerInterface
    {
        $explorer = BlockchainExplorerFactory::createWithHealthCheck($network);
        
        // Test the explorer with a simple call
        try {
            $startTime = microtime(true);
            
            // Simple health check - try to get API status or basic info
            if (method_exists($explorer, 'getApiStatus')) {
                $explorer->getApiStatus();
            }
            
            $responseTime = (microtime(true) - $startTime) * 1000;
            $this->recordHealthCheck($network, true, (int) round($responseTime));
            
        } catch (\Exception $e) {
            $this->recordHealthCheck($network, false);
            throw $e;
        }
        
        return $explorer;
    }

    private function getCachedHealthyExplorer(string $network): ?BlockchainExplorerInterface
    {
        $cacheKey = "healthy_explorer_{$network}";
        return Cache::get($cacheKey);
    }

    private function cacheHealthyExplorer(string $network, BlockchainExplorerInterface $explorer): void
    {
        $cacheKey = "healthy_explorer_{$network}";
        Cache::put($cacheKey, $explorer, 300); // 5 minutes cache
    }

    private function invalidateExplorerCache(string $network): void
    {
        $cacheKey = "healthy_explorer_{$network}";
        Cache::forget($cacheKey);
    }

    private function getFallbackExplorer(string $network): BlockchainExplorerInterface
    {
        // Try fallback networks in order of health
        $fallbacks = config("blockchain_explorers.fallbacks.{$network}", []);
        
        foreach ($fallbacks as $fallbackNetwork) {
            if (!$this->isCircuitOpen($fallbackNetwork)) {
                try {
                    Log::info("Using fallback {$fallbackNetwork} for {$network}");
                    return $this->createExplorerWithHealthCheck($fallbackNetwork);
                } catch (\Exception $e) {
                    Log::warning("Fallback {$fallbackNetwork} also failed: {$e->getMessage()}");
                    continue;
                }
            }
        }
        
        // Last resort - try original network anyway
        Log::warning("All fallbacks exhausted for {$network}, attempting original");
        return BlockchainExplorerFactory::create($network);
    }

    private function isCircuitOpen(string $network): bool
    {
        $state = $this->getCircuitBreakerState($network);
        
        if ($state === self::CIRCUIT_OPEN) {
            // Check if timeout has passed to transition to half-open
            $openTime = Cache::get("circuit_open_time_{$network}");
            if ($openTime && now()->diffInSeconds($openTime) > self::PERFORMANCE_THRESHOLDS['circuit_timeout']) {
                $this->setCircuitBreakerState($network, self::CIRCUIT_HALF_OPEN);
                return false;
            }
            return true;
        }
        
        return false;
    }

    private function getCircuitBreakerState(string $network): string
    {
        return Cache::get("circuit_state_{$network}", self::CIRCUIT_CLOSED);
    }

    private function setCircuitBreakerState(string $network, string $state): void
    {
        Cache::put("circuit_state_{$network}", $state, 3600); // 1 hour
        
        if ($state === self::CIRCUIT_OPEN) {
            Cache::put("circuit_open_time_{$network}", now(), 3600);
        }
        
        Log::info("Circuit breaker for {$network} changed to {$state}");
    }

    private function recordCircuitBreakerFailure(string $network): void
    {
        $failureCount = Cache::get("circuit_failures_{$network}", 0);
        $failureCount++;
        
        Cache::put("circuit_failures_{$network}", $failureCount, 600); // 10 minutes
        
        if ($failureCount >= self::PERFORMANCE_THRESHOLDS['failure_count']) {
            $this->openCircuitBreaker($network);
        }
    }

    private function openCircuitBreaker(string $network): void
    {
        $this->setCircuitBreakerState($network, self::CIRCUIT_OPEN);
        Cache::forget("circuit_failures_{$network}");
    }

    private function closeCircuitBreaker(string $network): void
    {
        $this->setCircuitBreakerState($network, self::CIRCUIT_CLOSED);
        Cache::forget("circuit_failures_{$network}");
        Cache::forget("circuit_open_time_{$network}");
    }

    private function recordOperationSuccess(string $network, float $responseTime): void
    {
        // Record in API usage tracking
        ApiUsageTracking::recordSuccess(
            $network,
            $network . 'scan',
            'multi_chain_operation',
            (int) round($responseTime),
            null,
            ['managed_by' => 'MultiChainExplorerManager']
        );
        
        // Reset circuit breaker failures on success
        if ($this->getCircuitBreakerState($network) === self::CIRCUIT_HALF_OPEN) {
            $this->closeCircuitBreaker($network);
        }
        
        BlockchainExplorerFactory::recordExplorerHealth($network, true, (int) round($responseTime));
    }

    private function recordOperationFailure(string $network, \Exception $exception): void
    {
        // Record in API usage tracking
        ApiUsageTracking::recordFailure(
            $network,
            $network . 'scan',
            'multi_chain_operation',
            get_class($exception),
            $exception->getMessage(),
            null,
            null,
            ['managed_by' => 'MultiChainExplorerManager']
        );
        
        BlockchainExplorerFactory::recordExplorerHealth($network, false);
    }

    private function recordHealthCheck(string $network, bool $success, int $responseTime = null): void
    {
        BlockchainExplorerFactory::recordExplorerHealth($network, $success, $responseTime);
    }

    private function sortNetworksByPriority(array $networks): array
    {
        usort($networks, function ($a, $b) {
            $priorityA = self::NETWORK_PRIORITIES[$a] ?? 999;
            $priorityB = self::NETWORK_PRIORITIES[$b] ?? 999;
            return $priorityA <=> $priorityB;
        });
        
        return $networks;
    }

    private function getNetworkPerformanceMetrics(string $network, Carbon $since = null): array
    {
        $since = $since ?? now()->subDay();
        
        // Get metrics from API usage tracking
        $metrics = ApiUsageTracking::where('network', $network)
            ->where('created_at', '>=', $since)
            ->selectRaw('
                COUNT(*) as total_requests,
                COUNT(CASE WHEN success = true THEN 1 END) as successful_requests,
                AVG(CASE WHEN response_time_ms IS NOT NULL THEN response_time_ms END) as avg_response_time,
                MAX(created_at) as last_success,
                MAX(CASE WHEN success = false THEN created_at END) as last_failure
            ')
            ->first();
        
        $totalRequests = $metrics->total_requests ?? 0;
        $successfulRequests = $metrics->successful_requests ?? 0;
        
        return [
            'total_requests' => $totalRequests,
            'successful_requests' => $successfulRequests,
            'failed_requests' => $totalRequests - $successfulRequests,
            'success_rate' => $totalRequests > 0 ? ($successfulRequests / $totalRequests) : 1.0,
            'recent_success_rate' => $this->getRecentSuccessRate($network),
            'avg_response_time' => round($metrics->avg_response_time ?? 0, 2),
            'last_success' => $metrics->last_success,
            'last_failure' => $metrics->last_failure,
            'recent_failures' => $this->getRecentFailureCount($network),
            'circuit_state' => $this->getCircuitBreakerState($network)
        ];
    }

    private function getRecentSuccessRate(string $network): float
    {
        // Get success rate for last 10 requests
        $recent = ApiUsageTracking::where('network', $network)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
            
        if ($recent->isEmpty()) {
            return 1.0;
        }
        
        $successful = $recent->where('success', true)->count();
        return $successful / $recent->count();
    }

    private function getRecentFailureCount(string $network): int
    {
        return Cache::get("circuit_failures_{$network}", 0);
    }

    private function calculateSystemMetrics(array $networkAnalytics): array
    {
        $totalRequests = 0;
        $totalSuccessful = 0;
        $totalResponseTime = 0;
        $networkCount = 0;
        
        foreach ($networkAnalytics as $metrics) {
            $totalRequests += $metrics['total_requests'];
            $totalSuccessful += $metrics['successful_requests'];
            $totalResponseTime += $metrics['avg_response_time'];
            $networkCount++;
        }
        
        return [
            'total_requests' => $totalRequests,
            'total_successful' => $totalSuccessful,
            'overall_success_rate' => $totalRequests > 0 ? ($totalSuccessful / $totalRequests) : 1.0,
            'average_response_time' => $networkCount > 0 ? ($totalResponseTime / $networkCount) : 0,
            'active_networks' => $networkCount,
            'healthy_networks' => collect($networkAnalytics)->where('success_rate', '>', 0.8)->count(),
            'degraded_networks' => collect($networkAnalytics)->where('success_rate', '<=', 0.8)->count()
        ];
    }

    private function generatePerformanceRecommendations(array $networkAnalytics): array
    {
        $recommendations = [];
        
        foreach ($networkAnalytics as $network => $metrics) {
            if ($metrics['success_rate'] < 0.8) {
                $recommendations[] = "Consider investigating {$network} - success rate is {$metrics['success_rate']}";
            }
            
            if ($metrics['avg_response_time'] > 3000) {
                $recommendations[] = "High response times for {$network} - consider caching more aggressively";
            }
            
            if ($metrics['recent_failures'] > 3) {
                $recommendations[] = "Frequent failures detected for {$network} - check API key and network status";
            }
        }
        
        return $recommendations;
    }

    private function getNetworkRecommendations(string $network, array $metrics): array
    {
        $recommendations = [];
        
        if ($metrics['success_rate'] < 0.9) {
            $recommendations[] = 'Success rate below 90% - investigate API issues';
        }
        
        if ($metrics['avg_response_time'] > 2000) {
            $recommendations[] = 'High response times - consider request optimization';
        }
        
        if ($this->isCircuitOpen($network)) {
            $recommendations[] = 'Circuit breaker open - network temporarily unavailable';
        }
        
        return $recommendations;
    }

    private function getHealthStatusFromScore(float $score): string
    {
        return match (true) {
            $score >= 0.9 => 'excellent',
            $score >= 0.7 => 'good',
            $score >= 0.5 => 'fair',
            $score >= 0.3 => 'poor',
            default => 'critical'
        };
    }
}