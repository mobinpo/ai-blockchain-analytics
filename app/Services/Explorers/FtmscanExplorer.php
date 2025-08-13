<?php

declare(strict_types=1);

namespace App\Services\Explorers;

final class FtmscanExplorer extends AbstractBlockchainExplorer
{
    public function getName(): string
    {
        return 'ftmscan';
    }

    public function getNetwork(): string
    {
        return 'fantom';
    }

    public function getContractUrl(string $contractAddress): string
    {
        return "https://ftmscan.com/address/{$contractAddress}";
    }

    public function getChainId(): int
    {
        return 250;
    }

    public function getNativeCurrency(): string
    {
        return 'FTM';
    }

    public function getExplorerUrl(): string
    {
        return 'https://ftmscan.com';
    }

    public function getAvailableEndpoints(): array
    {
        return array_merge(parent::getAvailableEndpoints(), [
            'ftm_price' => [
                'module' => 'stats',
                'action' => 'ftmprice',
                'description' => 'Get current FTM price',
            ],
        ]);
    }

    /**
     * Get current FTM price
     */
    public function getFtmPrice(): array
    {
        $response = $this->makeRequest('api', [
            'module' => 'stats',
            'action' => 'ftmprice',
        ]);

        if ($response['status'] !== '1') {
            throw new \InvalidArgumentException("Failed to fetch FTM price: {$response['message']}");
        }

        return [
            'network' => $this->getNetwork(),
            'ftm_btc' => (float) $response['result']['ftmbtc'],
            'ftm_usd' => (float) $response['result']['ftmusd'],
            'ftm_usd_timestamp' => $response['result']['ftmusd_timestamp'],
            'fetched_at' => now()->toISOString(),
            'explorer' => $this->getName(),
        ];
    }
}