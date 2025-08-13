<?php

declare(strict_types=1);

namespace App\Services\Explorers;

final class PolygonscanExplorer extends AbstractBlockchainExplorer
{
    public function getName(): string
    {
        return 'polygonscan';
    }

    public function getNetwork(): string
    {
        return 'polygon';
    }

    public function getContractUrl(string $contractAddress): string
    {
        return "https://polygonscan.com/address/{$contractAddress}";
    }

    public function getChainId(): int
    {
        return 137;
    }

    public function getNativeCurrency(): string
    {
        return 'MATIC';
    }

    public function getExplorerUrl(): string
    {
        return 'https://polygonscan.com';
    }

    public function getAvailableEndpoints(): array
    {
        return array_merge(parent::getAvailableEndpoints(), [
            'token_supply' => [
                'module' => 'stats',
                'action' => 'tokensupply',
                'description' => 'Get token total supply',
            ],
            'matic_price' => [
                'module' => 'stats',
                'action' => 'maticprice',
                'description' => 'Get current MATIC price',
            ],
            'gas_oracle' => [
                'module' => 'gastracker',
                'action' => 'gasoracle',
                'description' => 'Get current gas prices on Polygon',
            ],
        ]);
    }

    /**
     * Get current MATIC price
     */
    public function getMaticPrice(): array
    {
        $response = $this->makeRequest('api', [
            'module' => 'stats',
            'action' => 'maticprice',
        ]);

        if ($response['status'] !== '1') {
            throw new \InvalidArgumentException("Failed to fetch MATIC price: {$response['message']}");
        }

        return [
            'network' => $this->getNetwork(),
            'matic_btc' => (float) $response['result']['maticbtc'],
            'matic_usd' => (float) $response['result']['maticusd'],
            'matic_usd_timestamp' => $response['result']['maticusd_timestamp'],
            'fetched_at' => now()->toISOString(),
            'explorer' => $this->getName(),
        ];
    }

    /**
     * Get current gas prices on Polygon
     */
    public function getGasPrices(): array
    {
        $response = $this->makeRequest('api', [
            'module' => 'gastracker',
            'action' => 'gasoracle',
        ]);

        if ($response['status'] !== '1') {
            throw new \InvalidArgumentException("Failed to fetch gas prices: {$response['message']}");
        }

        return [
            'network' => $this->getNetwork(),
            'safe_gas_price' => (int) $response['result']['SafeGasPrice'],
            'standard_gas_price' => (int) $response['result']['StandardGasPrice'],
            'fast_gas_price' => (int) $response['result']['FastGasPrice'],
            'fetched_at' => now()->toISOString(),
            'explorer' => $this->getName(),
        ];
    }
}