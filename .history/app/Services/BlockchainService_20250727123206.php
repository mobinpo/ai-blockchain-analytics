<?php

namespace App\Services;

use Web3\Web3;

class BlockchainService
{
    private Web3 $web3;

    public function __construct()
    {
        $this->web3 = new Web3(config('services.evm.rpc_url'));
    }

    /**
     * Get the balance of an address in Wei.
     */
    public function getBalance(string $address): string
    {
        $balance = '';
        $this->web3->eth()->getBalance($address, function ($err, $result) use (&$balance) {
            if ($err !== null) {
                throw new \RuntimeException($err->getMessage());
            }
            $balance = $result;
        });
        return $balance;
    }
} 