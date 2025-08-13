<?php

declare(strict_types=1);

namespace App\Services\Explorers;

final class EtherscanExplorer extends AbstractBlockchainExplorer
{
    public function getName(): string
    {
        return 'etherscan';
    }

    public function getNetwork(): string
    {
        return 'ethereum';
    }

    public function getChainId(): int
    {
        return 1; // Ethereum mainnet
    }

    public function getNativeCurrency(): string
    {
        return 'ETH';
    }

    public function getExplorerUrl(): string
    {
        return 'https://etherscan.io';
    }

    public function getContractUrl(string $contractAddress): string
    {
        return "https://etherscan.io/address/{$contractAddress}";
    }

    public function getAvailableEndpoints(): array
    {
        return array_merge(parent::getAvailableEndpoints(), [
            'token_supply' => [
                'module' => 'stats',
                'action' => 'tokensupply',
                'description' => 'Get ERC20 token total supply',
            ],
            'gas_oracle' => [
                'module' => 'gastracker',
                'action' => 'gasoracle',
                'description' => 'Get current gas prices',
            ],
            'eth_price' => [
                'module' => 'stats',
                'action' => 'ethprice',
                'description' => 'Get current ETH price',
            ],
        ]);
    }

    /**
     * Get current gas prices from Etherscan
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

    /**
     * Get current ETH price
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