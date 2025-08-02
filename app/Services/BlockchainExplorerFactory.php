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
     * Create explorer instance for specific network
     */
    public static function create(string $network): BlockchainExplorerInterface
    {
        if (!isset(self::EXPLORER_MAP[$network])) {
            throw new InvalidArgumentException(
                "Unsupported network: {$network}. Supported networks: " . 
                implode(', ', array_keys(self::EXPLORER_MAP))
            );
        }

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
     * Get network information with explorer details
     */
    public static function getNetworkInfo(): array
    {
        $networks = [];
        
        foreach (self::EXPLORER_MAP as $network => $explorerClass) {
            $configKey = self::CONFIG_MAP[$network];
            $config = config("blockchain_explorers.{$configKey}");
            
            $networks[$network] = [
                'name' => ucfirst($network),
                'explorer_class' => $explorerClass,
                'config_key' => $configKey,
                'api_url' => $config['api_url'] ?? 'Not configured',
                'configured' => !empty($config['api_key']),
                'rate_limit' => $config['rate_limit'] ?? 5,
            ];
        }

        return $networks;
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
     * Validate explorer configuration for network
     */
    public static function validateConfiguration(string $network): array
    {
        $issues = [];
        
        if (!self::isNetworkSupported($network)) {
            $issues[] = "Network '{$network}' is not supported";
            return ['valid' => false, 'issues' => $issues];
        }

        $configKey = self::getConfigKey($network);
        $config = config("blockchain_explorers.{$configKey}");

        if (!$config) {
            $issues[] = "Configuration section '{$configKey}' not found";
        } else {
            if (empty($config['api_key'])) {
                $issues[] = "API key not configured for {$network}";
            }
            if (empty($config['api_url'])) {
                $issues[] = "API URL not configured for {$network}";
            }
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'config_key' => $configKey ?? null,
            'config' => $config ?? null,
        ];
    }
}