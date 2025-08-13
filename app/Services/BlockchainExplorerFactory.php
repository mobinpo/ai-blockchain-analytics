<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\BlockchainExplorerInterface;
use App\Services\Explorers\{
    EtherscanExplorer,
    BscscanExplorer,
    PolygonscanExplorer,
    ArbiscanExplorer,
    OptimisticEtherscanExplorer,
    SnowtraceExplorer,
    FtmscanExplorer
};
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

final class BlockchainExplorerFactory
{
    private const EXPLORER_MAP = [
        'ethereum' => EtherscanExplorer::class,
        'bsc' => BscscanExplorer::class,
        'polygon' => PolygonscanExplorer::class,
        'arbitrum' => ArbiscanExplorer::class,
        'optimism' => OptimisticEtherscanExplorer::class,
        'avalanche' => SnowtraceExplorer::class,
        'fantom' => FtmscanExplorer::class,
    ];

    private const NETWORK_METADATA = [
        'ethereum' => [
            'name' => 'Ethereum',
            'chain_id' => 1,
            'native_currency' => 'ETH',
            'block_time' => 12,
            'finality_blocks' => 12,
        ],
        'bsc' => [
            'name' => 'BNB Smart Chain',
            'chain_id' => 56,
            'native_currency' => 'BNB',
            'block_time' => 3,
            'finality_blocks' => 15,
        ],
        'polygon' => [
            'name' => 'Polygon',
            'chain_id' => 137,
            'native_currency' => 'MATIC',
            'block_time' => 2,
            'finality_blocks' => 128,
        ],
        'arbitrum' => [
            'name' => 'Arbitrum One',
            'chain_id' => 42161,
            'native_currency' => 'ETH',
            'block_time' => 0.3,
            'finality_blocks' => 1,
        ],
        'optimism' => [
            'name' => 'Optimism',
            'chain_id' => 10,
            'native_currency' => 'ETH',
            'block_time' => 2,
            'finality_blocks' => 1,
        ],
        'avalanche' => [
            'name' => 'Avalanche C-Chain',
            'chain_id' => 43114,
            'native_currency' => 'AVAX',
            'block_time' => 2,
            'finality_blocks' => 1,
        ],
        'fantom' => [
            'name' => 'Fantom Opera',
            'chain_id' => 250,
            'native_currency' => 'FTM',
            'block_time' => 1,
            'finality_blocks' => 1,
        ],
    ];

    private const CONFIG_MAP = [
        'ethereum' => 'etherscan',
        'bsc' => 'bscscan',
        'polygon' => 'polygonscan',
        'arbitrum' => 'arbiscan',
        'optimism' => 'optimistic_etherscan',
        'avalanche' => 'snowtrace',
        'fantom' => 'ftmscan',
    ];

    /**
     * Create explorer instance for specific network with fallback support
     */
    public static function create(string $network): BlockchainExplorerInterface
    {
        return self::createWithFallback($network, true);
    }

    /**
     * Create explorer with health monitoring and automatic failover
     */
    public static function createWithHealthCheck(string $network): BlockchainExplorerInterface
    {
        $explorer = self::createWithFallback($network, true);
        
        // Record successful creation
        self::recordExplorerHealth($network, true);
        
        return $explorer;
    }

    /**
     * Create explorer with fallback support
     */
    public static function createWithFallback(string $network, bool $enableFallback = true): BlockchainExplorerInterface
    {
        if (!isset(self::EXPLORER_MAP[$network])) {
            throw new InvalidArgumentException(
                "Unsupported network: {$network}. Supported networks: " . 
                implode(', ', array_keys(self::EXPLORER_MAP))
            );
        }

        try {
            $explorer = self::createDirectly($network);
            
            if ($enableFallback && self::isExplorerHealthy($network)) {
                return $explorer;
            } elseif (!$enableFallback) {
                return $explorer;
            }
        } catch (\Exception $e) {
            Log::warning("Primary explorer failed for {$network}: {$e->getMessage()}");
        }

        if ($enableFallback) {
            $fallbackNetwork = self::getFallbackNetwork($network);
            if ($fallbackNetwork) {
                Log::info("Using fallback explorer {$fallbackNetwork} for {$network}");
                return self::createDirectly($fallbackNetwork);
            }
        }

        throw new InvalidArgumentException(
            "Failed to create explorer for {$network} and no fallback available"
        );
    }

    /**
     * Create explorer directly without fallback
     */
    private static function createDirectly(string $network): BlockchainExplorerInterface
    {
        $explorerClass = self::EXPLORER_MAP[$network];
        $configKey = self::CONFIG_MAP[$network];
        $config = config("blockchain_explorers.{$configKey}");

        if (!$config) {
            throw new InvalidArgumentException(
                "Configuration not found for {$network}. Please check config/blockchain_explorers.php"
            );
        }

        return new $explorerClass($config);
    }

    /**
     * Check if explorer is healthy with detailed health metrics
     */
    private static function isExplorerHealthy(string $network): bool
    {
        $healthConfig = config('blockchain_explorers.health_check');
        
        if (!$healthConfig['enabled']) {
            return true;
        }

        $cacheKey = $healthConfig['cache_key_prefix'] . $network;
        $healthStatus = Cache::get($cacheKey);

        if ($healthStatus === null) {
            return true; // No health data means assume healthy
        }

        // Check multiple health criteria
        $isHealthy = $healthStatus['failures'] < $healthConfig['failure_threshold'] &&
                    $healthStatus['success_rate'] > 0.8 && // At least 80% success rate
                    $healthStatus['avg_response_time'] < 10000; // Less than 10s average response

        Log::debug("Explorer health check for {$network}", [
            'failures' => $healthStatus['failures'],
            'success_rate' => $healthStatus['success_rate'],
            'avg_response_time' => $healthStatus['avg_response_time'],
            'is_healthy' => $isHealthy
        ]);

        return $isHealthy;
    }

    /**
     * Record explorer health status
     */
    public static function recordExplorerHealth(string $network, bool $success, int $responseTime = null): void
    {
        $healthConfig = config('blockchain_explorers.health_check');
        
        if (!$healthConfig['enabled']) {
            return;
        }

        $cacheKey = $healthConfig['cache_key_prefix'] . $network;
        $currentHealth = Cache::get($cacheKey, [
            'failures' => 0,
            'successes' => 0,
            'total_requests' => 0,
            'success_rate' => 1.0,
            'avg_response_time' => 1000,
            'last_success' => null,
            'last_failure' => null,
            'response_times' => []
        ]);

        $currentHealth['total_requests']++;
        
        if ($success) {
            $currentHealth['successes']++;
            $currentHealth['last_success'] = now()->toISOString();
            
            if ($responseTime) {
                $currentHealth['response_times'][] = $responseTime;
                // Keep only last 10 response times for averaging
                $currentHealth['response_times'] = array_slice($currentHealth['response_times'], -10);
                $currentHealth['avg_response_time'] = array_sum($currentHealth['response_times']) / count($currentHealth['response_times']);
            }
        } else {
            $currentHealth['failures']++;
            $currentHealth['last_failure'] = now()->toISOString();
        }

        $currentHealth['success_rate'] = $currentHealth['successes'] / $currentHealth['total_requests'];

        // Cache health data for interval period
        Cache::put($cacheKey, $currentHealth, $healthConfig['interval']);

        Log::debug("Updated health status for {$network}", [
            'success' => $success,
            'response_time' => $responseTime,
            'new_success_rate' => $currentHealth['success_rate'],
            'total_failures' => $currentHealth['failures']
        ]);
    }

    /**
     * Get fallback network for primary network with intelligent selection
     */
    private static function getFallbackNetwork(string $network): ?string
    {
        $fallbacks = config('blockchain_explorers.fallbacks', []);
        $networkFallbacks = $fallbacks[$network] ?? [];

        // Sort fallbacks by health score
        $healthyFallbacks = [];
        foreach ($networkFallbacks as $fallbackNetwork) {
            if (self::isNetworkSupported($fallbackNetwork)) {
                $healthScore = self::getExplorerHealthScore($fallbackNetwork);
                if ($healthScore > 0.5) { // Only consider reasonably healthy explorers
                    $healthyFallbacks[] = [
                        'network' => $fallbackNetwork,
                        'health_score' => $healthScore
                    ];
                }
            }
        }

        // Sort by health score descending
        usort($healthyFallbacks, fn($a, $b) => $b['health_score'] <=> $a['health_score']);

        return $healthyFallbacks[0]['network'] ?? null;
    }

    /**
     * Get explorer health score (0.0 to 1.0)
     */
    public static function getExplorerHealthScore(string $network): float
    {
        $healthConfig = config('blockchain_explorers.health_check');
        
        if (!$healthConfig['enabled']) {
            return 1.0; // Assume perfect health if monitoring disabled
        }

        $cacheKey = $healthConfig['cache_key_prefix'] . $network;
        $healthStatus = Cache::get($cacheKey);

        if (!$healthStatus) {
            return 0.9; // Default good health for new explorers
        }

        // Calculate composite health score
        $successRateScore = $healthStatus['success_rate'] ?? 0.5;
        $failureScore = max(0, 1 - ($healthStatus['failures'] / max($healthConfig['failure_threshold'], 1)));
        $responseTimeScore = min(1, 5000 / max($healthStatus['avg_response_time'] ?? 5000, 1000)); // 1.0 for <1s, decreases as response time increases

        return ($successRateScore * 0.5) + ($failureScore * 0.3) + ($responseTimeScore * 0.2);
    }

    /**
     * Get all supported networks
     */
    public static function getSupportedNetworks(): array
    {
        return array_keys(self::EXPLORER_MAP);
    }

    /**
     * Get network configuration mapping
     */
    public static function getNetworkConfigs(): array
    {
        return self::CONFIG_MAP;
    }

    /**
     * Check if network is supported
     */
    public static function isNetworkSupported(string $network): bool
    {
        return isset(self::EXPLORER_MAP[$network]);
    }

    /**
     * Get explorer class for network
     */
    public static function getExplorerClass(string $network): string
    {
        if (!isset(self::EXPLORER_MAP[$network])) {
            throw new InvalidArgumentException("Unsupported network: {$network}");
        }

        return self::EXPLORER_MAP[$network];
    }

    /**
     * Get configuration key for network
     */
    public static function getConfigKey(string $network): string
    {
        if (!isset(self::CONFIG_MAP[$network])) {
            throw new InvalidArgumentException("Unknown network: {$network}");
        }

        return self::CONFIG_MAP[$network];
    }

    /**
     * Create multiple explorers for different networks
     */
    public static function createMultiple(array $networks): array
    {
        $explorers = [];
        
        foreach ($networks as $network) {
            try {
                $explorers[$network] = self::create($network);
            } catch (InvalidArgumentException $e) {
                // Log warning but continue with other networks
                \Log::warning("Failed to create explorer for {$network}: {$e->getMessage()}");
            }
        }

        return $explorers;
    }

    /**
     * Get comprehensive network information with explorer details and health status
     */
    public static function getNetworkInfo(): array
    {
        $networks = [];
        
        foreach (self::EXPLORER_MAP as $network => $explorerClass) {
            $configKey = self::CONFIG_MAP[$network];
            $config = config("blockchain_explorers.{$configKey}");
            $metadata = self::NETWORK_METADATA[$network] ?? [];
            $healthScore = self::getExplorerHealthScore($network);
            
            $networks[$network] = [
                'network_id' => $network,
                'name' => $metadata['name'] ?? ucfirst($network),
                'chain_id' => $metadata['chain_id'] ?? null,
                'native_currency' => $metadata['native_currency'] ?? 'ETH',
                'block_time' => $metadata['block_time'] ?? null,
                'finality_blocks' => $metadata['finality_blocks'] ?? null,
                'explorer_class' => $explorerClass,
                'config_key' => $configKey,
                'api_url' => $config['api_url'] ?? 'Not configured',
                'configured' => !empty($config['api_key']),
                'rate_limit' => $config['rate_limit'] ?? 5,
                'timeout' => $config['timeout'] ?? 30,
                'health_score' => $healthScore,
                'health_status' => self::getHealthStatusLabel($healthScore),
                'is_healthy' => self::isExplorerHealthy($network),
                'fallbacks' => config("blockchain_explorers.fallbacks.{$network}", []),
                'last_checked' => self::getLastHealthCheck($network)
            ];
        }

        return $networks;
    }

    /**
     * Get health status label from score
     */
    private static function getHealthStatusLabel(float $score): string
    {
        return match(true) {
            $score >= 0.9 => 'Excellent',
            $score >= 0.7 => 'Good',
            $score >= 0.5 => 'Fair',
            $score >= 0.3 => 'Poor',
            default => 'Critical'
        };
    }

    /**
     * Get last health check timestamp
     */
    private static function getLastHealthCheck(string $network): ?string
    {
        $healthConfig = config('blockchain_explorers.health_check');
        $cacheKey = $healthConfig['cache_key_prefix'] . $network;
        $healthStatus = Cache::get($cacheKey);
        
        return $healthStatus['last_success'] ?? $healthStatus['last_failure'] ?? null;
    }

    /**
     * Create explorer with custom configuration
     */
    public static function createWithConfig(string $network, array $config): BlockchainExplorerInterface
    {
        if (!isset(self::EXPLORER_MAP[$network])) {
            throw new InvalidArgumentException("Unsupported network: {$network}");
        }

        $explorerClass = self::EXPLORER_MAP[$network];
        return new $explorerClass($config);
    }

    /**
     * Get all configured explorers
     */
    public static function getAllConfiguredExplorers(): array
    {
        $explorers = [];
        
        foreach (self::getSupportedNetworks() as $network) {
            try {
                $explorer = self::create($network);
                if ($explorer->isConfigured()) {
                    $explorers[$network] = $explorer;
                }
            } catch (InvalidArgumentException) {
                // Skip unconfigured explorers
                continue;
            }
        }

        return $explorers;
    }

    /**
     * Validate explorer configuration for network with comprehensive checks
     */
    public static function validateConfiguration(string $network): array
    {
        $issues = [];
        $warnings = [];
        
        if (!self::isNetworkSupported($network)) {
            $issues[] = "Network '{$network}' is not supported";
            return [
                'valid' => false, 
                'issues' => $issues, 
                'warnings' => $warnings,
                'supported_networks' => self::getSupportedNetworks()
            ];
        }

        $configKey = self::getConfigKey($network);
        $config = config("blockchain_explorers.{$configKey}");

        if (!$config) {
            $issues[] = "Configuration section '{$configKey}' not found";
        } else {
            // Required fields
            if (empty($config['api_key'])) {
                $issues[] = "API key not configured for {$network}";
            }
            if (empty($config['api_url'])) {
                $issues[] = "API URL not configured for {$network}";
            }
            
            // Optional but recommended fields
            if (empty($config['rate_limit'])) {
                $warnings[] = "Rate limit not configured for {$network}, using default";
            }
            if (empty($config['timeout'])) {
                $warnings[] = "Timeout not configured for {$network}, using default";
            }
            
            // Validate API URL format
            if (!empty($config['api_url']) && !filter_var($config['api_url'], FILTER_VALIDATE_URL)) {
                $issues[] = "Invalid API URL format for {$network}";
            }
            
            // Check rate limit is reasonable
            if (isset($config['rate_limit']) && ($config['rate_limit'] < 1 || $config['rate_limit'] > 50)) {
                $warnings[] = "Rate limit for {$network} seems unusual: {$config['rate_limit']} req/s";
            }
        }

        // Test explorer creation if configuration looks valid
        $canCreate = false;
        $createError = null;
        if (empty($issues)) {
            try {
                $explorer = self::createDirectly($network);
                $canCreate = $explorer->isConfigured();
            } catch (\Exception $e) {
                $createError = $e->getMessage();
                $issues[] = "Cannot create explorer instance: {$createError}";
            }
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'warnings' => $warnings,
            'config_key' => $configKey ?? null,
            'config' => $config ?? null,
            'can_create_explorer' => $canCreate,
            'create_error' => $createError,
            'health_score' => self::getExplorerHealthScore($network),
            'fallbacks_available' => count(config("blockchain_explorers.fallbacks.{$network}", [])),
            'network_metadata' => self::NETWORK_METADATA[$network] ?? null
        ];
    }

    /**
     * Get comprehensive system health report
     */
    public static function getSystemHealthReport(): array
    {
        $networks = self::getSupportedNetworks();
        $report = [
            'timestamp' => now()->toISOString(),
            'total_networks' => count($networks),
            'healthy_networks' => 0,
            'unhealthy_networks' => 0,
            'configured_networks' => 0,
            'unconfigured_networks' => 0,
            'average_health_score' => 0,
            'network_details' => [],
            'recommendations' => []
        ];

        $totalHealthScore = 0;
        foreach ($networks as $network) {
            $validation = self::validateConfiguration($network);
            $healthScore = self::getExplorerHealthScore($network);
            $isHealthy = self::isExplorerHealthy($network);
            
            $networkDetail = [
                'network' => $network,
                'configured' => $validation['valid'],
                'healthy' => $isHealthy,
                'health_score' => $healthScore,
                'issues' => $validation['issues'],
                'warnings' => $validation['warnings']
            ];
            
            $report['network_details'][$network] = $networkDetail;
            
            if ($validation['valid']) {
                $report['configured_networks']++;
                if ($isHealthy) {
                    $report['healthy_networks']++;
                } else {
                    $report['unhealthy_networks']++;
                    $report['recommendations'][] = "Consider checking {$network} explorer - health score: {$healthScore}";
                }
            } else {
                $report['unconfigured_networks']++;
                $report['recommendations'][] = "Configure {$network} explorer: " . implode(', ', $validation['issues']);
            }
            
            $totalHealthScore += $healthScore;
        }
        
        $report['average_health_score'] = round($totalHealthScore / count($networks), 3);
        
        // Add system-wide recommendations
        if ($report['average_health_score'] < 0.7) {
            $report['recommendations'][] = 'System health is below recommended threshold - consider investigating API issues';
        }
        
        if ($report['configured_networks'] < count($networks) * 0.5) {
            $report['recommendations'][] = 'Less than 50% of supported networks are configured - consider adding more API keys';
        }
        
        return $report;
    }

    /**
     * Switch to best available explorer for network
     */
    public static function switchToBestExplorer(string $network): BlockchainExplorerInterface
    {
        // First try primary network
        if (self::isExplorerHealthy($network)) {
            try {
                return self::createDirectly($network);
            } catch (\Exception $e) {
                Log::warning("Primary explorer failed for {$network}, trying fallbacks: {$e->getMessage()}");
                self::recordExplorerHealth($network, false);
            }
        }
        
        // Try fallbacks in order of health score
        $fallbackNetwork = self::getFallbackNetwork($network);
        if ($fallbackNetwork) {
            Log::info("Switching to fallback explorer {$fallbackNetwork} for {$network}");
            return self::createDirectly($fallbackNetwork);
        }
        
        // Last resort - try to create primary anyway
        Log::warning("No healthy fallbacks available for {$network}, attempting primary explorer");
        return self::createDirectly($network);
    }
}