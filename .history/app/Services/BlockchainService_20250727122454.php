<?php

namespace App\Services;

use Web3\Web3;

class BlockchainService
{
    protected Web3 $web3;

    public function __construct()
    {
        $this->web3 = new Web3(config('services.evm.rpc_url'));
    }

    public function getLatestBlock(): mixed
    {
        $result = null;
        $this->web3->eth()->blockNumber(function ($err, $blockNumber) use (&$result) {
            if ($err !== null) {
                throw new \RuntimeException($err->getMessage());
            }
            $result = $blockNumber;
        });

        return $result;
    }
} 