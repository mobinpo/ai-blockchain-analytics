<?php

declare(strict_types=1);

namespace App\Services\Explorers;

final class SnowtraceExplorer extends AbstractBlockchainExplorer
{
    public function getName(): string
    {
        return 'snowtrace';
    }

    public function getNetwork(): string
    {
        return 'avalanche';
    }

    public function getContractUrl(string $contractAddress): string
    {
        return "https://snowtrace.io/address/{$contractAddress}";
    }

    public function getAvailableEndpoints(): array
    {
        return array_merge(parent::getAvailableEndpoints(), [
            'avax_price' => [
                'module' => 'stats',
                'action' => 'avaxprice',
                'description' => 'Get current AVAX price',
            ],
        ]);
    }

    /**
     * Get current AVAX price
     */
    public function getAvaxPrice(): array
    {
        $response = $this->makeRequest('api', [
            'module' => 'stats',
            'action' => 'avaxprice',
        ]);

        if ($response['status'] !== '1') {
            throw new \InvalidArgumentException("Failed to fetch AVAX price: {$response['message']}");
        }

        return [
            'network' => $this->getNetwork(),
            'avax_btc' => (float) $response['result']['avaxbtc'],
            'avax_usd' => (float) $response['result']['avaxusd'],
            'avax_usd_timestamp' => $response['result']['avaxusd_timestamp'],
            'fetched_at' => now()->toISOString(),
            'explorer' => $this->getName(),
        ];
    }
}