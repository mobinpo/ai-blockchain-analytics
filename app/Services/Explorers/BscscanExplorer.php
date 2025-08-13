<?php

declare(strict_types=1);

namespace App\Services\Explorers;

final class BscscanExplorer extends AbstractBlockchainExplorer
{
    public function getName(): string
    {
        return 'bscscan';
    }

    public function getNetwork(): string
    {
        return 'bsc';
    }

    public function getContractUrl(string $contractAddress): string
    {
        return "https://bscscan.com/address/{$contractAddress}";
    }

    public function getChainId(): int
    {
        return 56;
    }

    public function getNativeCurrency(): string
    {
        return 'BNB';
    }

    public function getExplorerUrl(): string
    {
        return 'https://bscscan.com';
    }

    public function getAvailableEndpoints(): array
    {
        return array_merge(parent::getAvailableEndpoints(), [
            'bep20_token_supply' => [
                'module' => 'stats',
                'action' => 'tokensupply',
                'description' => 'Get BEP20 token total supply',
            ],
            'bnb_price' => [
                'module' => 'stats',
                'action' => 'bnbprice',
                'description' => 'Get current BNB price',
            ],
            'gas_oracle' => [
                'module' => 'gastracker',
                'action' => 'gasoracle',
                'description' => 'Get current gas prices on BSC',
            ],
        ]);
    }

    /**
     * Get current BNB price
     */
    public function getBnbPrice(): array
    {
        $response = $this->makeRequest('api', [
            'module' => 'stats',
            'action' => 'bnbprice',
        ]);

        if ($response['status'] !== '1') {
            throw new \InvalidArgumentException("Failed to fetch BNB price: {$response['message']}");
        }

        return [
            'network' => $this->getNetwork(),
            'bnb_btc' => (float) $response['result']['bnbbtc'],
            'bnb_usd' => (float) $response['result']['bnbusd'],
            'bnb_usd_timestamp' => $response['result']['bnbusd_timestamp'],
            'fetched_at' => now()->toISOString(),
            'explorer' => $this->getName(),
        ];
    }

    /**
     * Get current gas prices on BSC
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