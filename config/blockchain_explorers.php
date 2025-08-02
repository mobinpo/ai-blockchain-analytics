<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Blockchain Explorer API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for various blockchain explorer APIs like Etherscan,
    | BscScan, PolygonScan, etc. These are used to fetch verified contract
    | source code, ABIs, and other contract information.
    |
    */

    'etherscan' => [
        'api_key' => env('ETHERSCAN_API_KEY'),
        'api_url' => env('ETHERSCAN_API_URL', 'https://api.etherscan.io/api'),
        'rate_limit' => env('ETHERSCAN_RATE_LIMIT', 5), // requests per second
        'timeout' => env('ETHERSCAN_TIMEOUT', 30),
    ],

    'bscscan' => [
        'api_key' => env('BSCSCAN_API_KEY'),
        'api_url' => env('BSCSCAN_API_URL', 'https://api.bscscan.com/api'),
        'rate_limit' => env('BSCSCAN_RATE_LIMIT', 5),
        'timeout' => env('BSCSCAN_TIMEOUT', 30),
    ],

    'polygonscan' => [
        'api_key' => env('POLYGONSCAN_API_KEY'),
        'api_url' => env('POLYGONSCAN_API_URL', 'https://api.polygonscan.com/api'),
        'rate_limit' => env('POLYGONSCAN_RATE_LIMIT', 5),
        'timeout' => env('POLYGONSCAN_TIMEOUT', 30),
    ],

    'arbiscan' => [
        'api_key' => env('ARBISCAN_API_KEY'),
        'api_url' => env('ARBISCAN_API_URL', 'https://api.arbiscan.io/api'),
        'rate_limit' => env('ARBISCAN_RATE_LIMIT', 5),
        'timeout' => env('ARBISCAN_TIMEOUT', 30),
    ],

    'optimistic_etherscan' => [
        'api_key' => env('OPTIMISTIC_ETHERSCAN_API_KEY'),
        'api_url' => env('OPTIMISTIC_ETHERSCAN_API_URL', 'https://api-optimistic.etherscan.io/api'),
        'rate_limit' => env('OPTIMISTIC_ETHERSCAN_RATE_LIMIT', 5),
        'timeout' => env('OPTIMISTIC_ETHERSCAN_TIMEOUT', 30),
    ],

    'snowtrace' => [
        'api_key' => env('SNOWTRACE_API_KEY'),
        'api_url' => env('SNOWTRACE_API_URL', 'https://api.snowtrace.io/api'),
        'rate_limit' => env('SNOWTRACE_RATE_LIMIT', 5),
        'timeout' => env('SNOWTRACE_TIMEOUT', 30),
    ],

    'ftmscan' => [
        'api_key' => env('FTMSCAN_API_KEY'),
        'api_url' => env('FTMSCAN_API_URL', 'https://api.ftmscan.com/api'),
        'rate_limit' => env('FTMSCAN_RATE_LIMIT', 5),
        'timeout' => env('FTMSCAN_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    */

    'default_timeout' => env('BLOCKCHAIN_EXPLORER_TIMEOUT', 30),
    'default_retry_attempts' => env('BLOCKCHAIN_EXPLORER_RETRY_ATTEMPTS', 3),
    'default_retry_delay' => env('BLOCKCHAIN_EXPLORER_RETRY_DELAY', 1000), // milliseconds
    'cache_ttl' => env('BLOCKCHAIN_EXPLORER_CACHE_TTL', 3600), // seconds (1 hour)

    /*
    |--------------------------------------------------------------------------
    | Network Mappings
    |--------------------------------------------------------------------------
    |
    | Map network identifiers to their explorer configurations
    |
    */

    'networks' => [
        'ethereum' => 'etherscan',
        'bsc' => 'bscscan',
        'polygon' => 'polygonscan',
        'arbitrum' => 'arbiscan',
        'optimism' => 'optimistic_etherscan',
        'avalanche' => 'snowtrace',
        'fantom' => 'ftmscan',
    ],
];