<?php

declare(strict_types=1);

namespace App\Services\Explorers;

final class ArbiscanExplorer extends AbstractBlockchainExplorer
{
    public function getName(): string
    {
        return 'arbiscan';
    }

    public function getNetwork(): string
    {
        return 'arbitrum';
    }

    public function getContractUrl(string $contractAddress): string
    {
        return "https://arbiscan.io/address/{$contractAddress}";
    }

    public function getChainId(): int
    {
        return 42161;
    }

    public function getNativeCurrency(): string
    {
        return 'ETH';
    }

    public function getExplorerUrl(): string
    {
        return 'https://arbiscan.io';
    }

    public function getAvailableEndpoints(): array
    {
        return array_merge(parent::getAvailableEndpoints(), [
            'eth_price' => [
                'module' => 'stats',
                'action' => 'ethprice',
                'description' => 'Get current ETH price on Arbitrum',
            ],
        ]);
    }

    /**
     * Get current ETH price on Arbitrum
     */
    public function getEthPrice(): array
    {
        $response = $this->makeRequest('api', [
            'module' => 'stats',
            'action' => 'ethprice',
        ]);

        if ($response['status'] !== '1') {
            throw new \InvalidArgumentException("Failed to fetch ETH price: {$response['message']}");
        }

        return [
            'network' => $this->getNetwork(),
            'eth_btc' => (float) $response['result']['ethbtc'],
            'eth_usd' => (float) $response['result']['ethusd'],
            'eth_usd_timestamp' => $response['result']['ethusd_timestamp'],
            'fetched_at' => now()->toISOString(),
            'explorer' => $this->getName(),
        ];
    }
}