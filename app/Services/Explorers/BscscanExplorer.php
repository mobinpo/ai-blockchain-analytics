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

    public function getAvailableEndpoints(): array
    {
        return array_merge(parent::getAvailableEndpoints(), [
            'bnb_price' => [
                'module' => 'stats',
                'action' => 'bnbprice',
                'description' => 'Get current BNB price',
            ],
            'bep20_token_supply' => [
                'module' => 'stats',
                'action' => 'tokensupply',
                'description' => 'Get BEP20 token total supply',
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
     * Get BEP20 token supply
     */
    public function getTokenSupply(string $contractAddress): array
    {
        if (!$this->validateAddress($contractAddress)) {
            throw new \InvalidArgumentException("Invalid contract address format: {$contractAddress}");
        }

        $response = $this->makeRequest('api', [
            'module' => 'stats',
            'action' => 'tokensupply',
            'contractaddress' => $contractAddress,
        ]);

        if ($response['status'] !== '1') {
            throw new \InvalidArgumentException("Failed to fetch token supply: {$response['message']}");
        }

        return [
            'network' => $this->getNetwork(),
            'contract_address' => $contractAddress,
            'total_supply' => $response['result'],
            'fetched_at' => now()->toISOString(),
            'explorer' => $this->getName(),
        ];
    }
}