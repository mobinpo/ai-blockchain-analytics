# 🔗 Blockchain Explorer Abstraction Layer

A flexible abstraction layer that provides a unified interface for interacting with different blockchain explorers across multiple networks (Ethereum, BSC, Polygon, Arbitrum, Optimism, Avalanche, Fantom).

## 🚀 Features

- **Unified Interface**: Single API for all blockchain explorers
- **Multi-Chain Support**: 7 major blockchain networks
- **Factory Pattern**: Easy explorer instantiation and management
- **Configuration Validation**: Automatic validation of API keys and settings
- **Chain-Specific Features**: Access to explorer-specific functionality
- **Rate Limiting**: Built-in rate limit awareness
- **Error Handling**: Comprehensive error handling and validation
- **Extensible Design**: Easy to add new explorers and networks

## 🏗️ Architecture

### Core Components

1. **`BlockchainExplorerInterface`** - Contract defining the standard interface
2. **`AbstractBlockchainExplorer`** - Base implementation with common functionality  
3. **Chain-Specific Explorers** - Implementations for each network
4. **`BlockchainExplorerFactory`** - Factory for creating explorer instances
5. **`BlockchainExplorerServiceV2`** - High-level service with caching

### Supported Networks & Explorers

| Network   | Explorer              | API Base URL                           | Special Features       |
|-----------|-----------------------|----------------------------------------|----------------------|
| Ethereum  | Etherscan             | https://api.etherscan.io/api          | Gas prices, ETH price |
| BSC       | BscScan               | https://api.bscscan.com/api           | BNB price, BEP20 tokens |
| Polygon   | PolygonScan           | https://api.polygonscan.com/api       | MATIC price, gas prices |
| Arbitrum  | Arbiscan              | https://api.arbiscan.io/api           | ETH price |
| Optimism  | Optimistic Etherscan  | https://api-optimistic.etherscan.io/api | ETH price |
| Avalanche | Snowtrace             | https://api.snowtrace.io/api          | AVAX price |
| Fantom    | FtmScan               | https://api.ftmscan.com/api           | FTM price |

## 💻 Usage Examples

### Basic Explorer Creation

```php
use App\Services\BlockchainExplorerFactory;

// Create specific explorer
$ethExplorer = BlockchainExplorerFactory::create('ethereum');
$bscExplorer = BlockchainExplorerFactory::create('bsc');
$polygonExplorer = BlockchainExplorerFactory::create('polygon');

// Get explorer info
echo $ethExplorer->getName(); // 'etherscan'
echo $ethExplorer->getNetwork(); // 'ethereum'
echo $ethExplorer->getApiUrl(); // 'https://api.etherscan.io/api'
```

### Contract Data Retrieval

```php
// Get contract source code
$contractAddress = '0x7a250d5630B4cF539739dF2C5dAcb4c659F2488D';
$explorer = BlockchainExplorerFactory::create('ethereum');

$source = $explorer->getContractSource($contractAddress);
echo $source['contract_name']; // 'UniswapV2Router02'
echo $source['is_verified']; // true
print_r($source['parsed_sources']); // Array of source files

// Get contract ABI
$abi = $explorer->getContractAbi($contractAddress);
print_r($abi['abi']); // Parsed ABI array

// Get contract creation info
$creation = $explorer->getContractCreation($contractAddress);
echo $creation['creator_address']; // Creator's address
echo $creation['creation_tx_hash']; // Deployment transaction hash
```

### Multi-Network Operations

```php
// Create explorers for multiple networks
$networks = ['ethereum', 'bsc', 'polygon'];
$explorers = BlockchainExplorerFactory::createMultiple($networks);

foreach ($explorers as $network => $explorer) {
    echo "Network: {$network}, Explorer: {$explorer->getName()}\n";
    
    if ($explorer->isConfigured()) {
        $verified = $explorer->isContractVerified($contractAddress);
        echo "Contract verified: " . ($verified ? 'Yes' : 'No') . "\n";
    }
}
```

### Using the High-Level Service

```php
use App\Services\BlockchainExplorerServiceV2;

$service = new BlockchainExplorerServiceV2(new BlockchainExplorerFactory());

// Get contract with caching
$contract = $service->getContractSource('ethereum', $contractAddress);

// Get cleaned source for AI
$cleaned = $service->getCleanedContractSource('ethereum', $contractAddress);
echo $cleaned['cleaned_source_code'];

// Get flattened source
$flattened = $service->getFlattenedContractSource('ethereum', $contractAddress);
echo $flattened['flattened_source_code'];
```

### Chain-Specific Features

```php
// Ethereum: Get gas prices and ETH price
$ethExplorer = BlockchainExplorerFactory::create('ethereum');
$gasPrices = $ethExplorer->getGasPrices();
$ethPrice = $ethExplorer->getEthPrice();

echo "Fast gas: {$gasPrices['fast_gas_price']} gwei\n";
echo "ETH price: \${$ethPrice['eth_usd']}\n";

// BSC: Get BNB price and token supply
$bscExplorer = BlockchainExplorerFactory::create('bsc');
$bnbPrice = $bscExplorer->getBnbPrice();
$tokenSupply = $bscExplorer->getTokenSupply($tokenAddress);

echo "BNB price: \${$bnbPrice['bnb_usd']}\n";
echo "Token supply: {$tokenSupply['total_supply']}\n";

// Polygon: Get MATIC price
$polygonExplorer = BlockchainExplorerFactory::create('polygon');
$maticPrice = $polygonExplorer->getMaticPrice();
echo "MATIC price: \${$maticPrice['matic_usd']}\n";
```

## 🧪 Testing Commands

### Explorer Information

```bash
# Get basic explorer info
php artisan explorer:test ethereum 0x... --action=info

# Test configuration
php artisan explorer:test ethereum 0x... --action=config

# List all supported networks
php artisan explorer:test ethereum 0x... --action=networks
```

### Contract Testing

```bash
# Test contract source retrieval
php artisan explorer:test ethereum 0x7a250d5630B4cF539739dF2C5dAcb4c659F2488D --action=source

# Test contract ABI
php artisan explorer:test ethereum 0x7a250d5630B4cF539739dF2C5dAcb4c659F2488D --action=abi

# Test contract creation info
php artisan explorer:test ethereum 0x7a250d5630B4cF539739dF2C5dAcb4c659F2488D --action=creation

# Check if contract is verified
php artisan explorer:test ethereum 0x7a250d5630B4cF539739dF2C5dAcb4c659F2488D --action=verified
```

### Chain-Specific Features

```bash
# Test price data and chain-specific features
php artisan explorer:test ethereum 0x... --action=prices
php artisan explorer:test bsc 0x... --action=prices
php artisan explorer:test polygon 0x... --action=prices
```

## ⚙️ Configuration Management

### Validation

```php
// Validate single network configuration
$validation = BlockchainExplorerFactory::validateConfiguration('ethereum');

if (!$validation['valid']) {
    foreach ($validation['issues'] as $issue) {
        echo "Issue: {$issue}\n";
    }
}

// Get all configured explorers
$explorers = BlockchainExplorerFactory::getAllConfiguredExplorers();
echo "Configured networks: " . implode(', ', array_keys($explorers)) . "\n";
```

### Network Information

```php
// Get comprehensive network info
$networks = BlockchainExplorerFactory::getNetworkInfo();

foreach ($networks as $network => $info) {
    echo "Network: {$network}\n";
    echo "Name: {$info['name']}\n";
    echo "Config Key: {$info['config_key']}\n";
    echo "Configured: " . ($info['configured'] ? 'Yes' : 'No') . "\n";
    echo "Rate Limit: {$info['rate_limit']} req/sec\n\n";
}
```

## 🔧 Extending the Abstraction Layer

### Adding a New Explorer

1. **Create Explorer Class**:

```php
<?php

declare(strict_types=1);

namespace App\Services\Explorers;

final class NewExplorer extends AbstractBlockchainExplorer
{
    public function getName(): string
    {
        return 'new_explorer';
    }

    public function getNetwork(): string
    {
        return 'new_network';
    }

    public function getContractUrl(string $contractAddress): string
    {
        return "https://new-explorer.com/address/{$contractAddress}";
    }

    // Add chain-specific methods
    public function getCustomData(): array
    {
        $response = $this->makeRequest('api', [
            'module' => 'custom',
            'action' => 'data',
        ]);

        return $response['result'];
    }
}
```

2. **Update Factory**:

```php
// In BlockchainExplorerFactory
private const EXPLORER_MAP = [
    // ... existing explorers
    'new_network' => NewExplorer::class,
];

private const CONFIG_MAP = [
    // ... existing configs
    'new_network' => 'new_explorer_config',
];
```

3. **Add Configuration**:

```php
// In config/blockchain_explorers.php
'new_explorer_config' => [
    'api_key' => env('NEW_EXPLORER_API_KEY'),
    'api_url' => 'https://api.new-explorer.com/api',
    'rate_limit' => 10,
    'timeout' => 30,
],
```

### Custom Explorer with Different API Format

```php
final class CustomExplorer extends AbstractBlockchainExplorer
{
    // Override methods for different API format
    protected function parseSourceCodeResponse(array $response, string $contractAddress): array
    {
        // Custom parsing logic for this explorer's response format
        return [
            'network' => $this->getNetwork(),
            'contract_address' => $contractAddress,
            // ... custom field mapping
        ];
    }

    public function makeRequest(string $endpoint, array $params = []): array
    {
        // Custom request handling if needed
        // e.g., different authentication method
        return parent::makeRequest($endpoint, $params);
    }
}
```

## 📊 Performance & Rate Limiting

### Automatic Rate Limiting

```php
// The abstraction layer automatically handles rate limiting
$explorer = BlockchainExplorerFactory::create('ethereum');
echo "Rate limit: {$explorer->getRateLimit()} requests per second\n";

// Bulk operations respect rate limits
$addresses = ['0x...', '0x...', '0x...'];
$service = new BlockchainExplorerServiceV2(new BlockchainExplorerFactory());
$results = $service->bulkGetContractSources('ethereum', $addresses);
// Automatically adds delays between requests
```

### Custom Configuration

```php
// Create explorer with custom config
$customConfig = [
    'api_key' => 'your_api_key',
    'api_url' => 'https://api.etherscan.io/api',
    'rate_limit' => 20, // 20 req/sec for pro plan
    'timeout' => 60,    // Longer timeout
    'retry_attempts' => 5,
    'retry_delay' => 2000,
];

$explorer = BlockchainExplorerFactory::createWithConfig('ethereum', $customConfig);
```

## 🔒 Security & Error Handling

### API Key Protection

```php
// API keys are automatically redacted in logs
$explorer = BlockchainExplorerFactory::create('ethereum');
$explorer->makeRequest('api', ['module' => 'contract', 'action' => 'getsourcecode']);
// Log shows: ['apikey' => '[REDACTED]']
```

### Comprehensive Error Handling

```php
try {
    $explorer = BlockchainExplorerFactory::create('ethereum');
    $source = $explorer->getContractSource($contractAddress);
} catch (InvalidArgumentException $e) {
    if (str_contains($e->getMessage(), 'API key not configured')) {
        // Handle missing API key
    } elseif (str_contains($e->getMessage(), 'Invalid contract address')) {
        // Handle invalid address
    } elseif (str_contains($e->getMessage(), 'Failed to fetch source code')) {
        // Handle API errors
    }
}
```

## ✅ Production Ready

The Blockchain Explorer Abstraction Layer is production-ready with:

- ✅ **Unified Interface**: Consistent API across all explorers
- ✅ **Multi-Chain Support**: 7 major blockchain networks
- ✅ **Factory Pattern**: Clean instantiation and management
- ✅ **Configuration Validation**: Automatic validation and error reporting
- ✅ **Rate Limiting**: Built-in rate limit awareness and handling
- ✅ **Chain-Specific Features**: Access to unique explorer functionality
- ✅ **Comprehensive Testing**: Full test coverage with CLI commands
- ✅ **Extensible Design**: Easy to add new explorers and networks
- ✅ **Error Handling**: Robust error handling and logging
- ✅ **Caching Integration**: Works seamlessly with PostgreSQL caching
- ✅ **Security**: API key protection and input validation

Perfect for building multi-chain blockchain applications with a unified explorer interface! 🚀

## 🔄 Migration from Legacy Service

### Gradual Migration

```php
// Current usage (legacy)
$legacyService = new BlockchainExplorerService();
$contract = $legacyService->getContractSource('ethereum', $address);

// New usage (abstraction layer)
$newService = new BlockchainExplorerServiceV2(new BlockchainExplorerFactory());
$contract = $newService->getContractSource('ethereum', $address);

// Response format is identical - no changes needed in consuming code!
```

### Benefits of Migration

1. **Better Organization**: Clear separation of concerns
2. **Easier Testing**: Mock individual explorers easily
3. **Better Error Handling**: Explorer-specific error messages
4. **Extensibility**: Add new explorers without modifying core service
5. **Performance**: Better rate limit handling per explorer
6. **Maintainability**: Cleaner, more organized code structure

The abstraction layer provides a future-proof foundation for multi-chain blockchain explorer integration! 🎯