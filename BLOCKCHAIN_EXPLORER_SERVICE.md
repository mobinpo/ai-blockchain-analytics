# 🔍 Blockchain Explorer Service

A comprehensive service to fetch verified Solidity source code via Etherscan/BscScan APIs and other blockchain explorers.

## 🚀 Features

- **Multi-Network Support**: Ethereum, BSC, Polygon, Arbitrum, Optimism, Avalanche, Fantom
- **Contract Source Code**: Fetch verified Solidity source with multi-file support
- **Contract ABI**: Get contract Application Binary Interface
- **Contract Creation**: Retrieve contract deployment information
- **Verification Check**: Quick verification status check
- **Bulk Operations**: Fetch multiple contracts at once
- **Rate Limiting**: Built-in rate limiting and retry logic
- **PostgreSQL Caching**: Built-in caching to avoid API rate limits
- **Error Handling**: Comprehensive error handling and validation

## 📋 Supported Networks

| Network | Explorer | Config Key |
|---------|----------|------------|
| Ethereum | Etherscan | `etherscan` |
| BSC | BscScan | `bscscan` |
| Polygon | PolygonScan | `polygonscan` |
| Arbitrum | Arbiscan | `arbiscan` |
| Optimism | Optimistic Etherscan | `optimistic_etherscan` |
| Avalanche | Snowtrace | `snowtrace` |
| Fantom | FtmScan | `ftmscan` |

## ⚙️ Configuration

### 1. Environment Variables

Add these to your `.env` file:

```env
# Blockchain Explorer API Keys
ETHERSCAN_API_KEY=your_etherscan_api_key_here
BSCSCAN_API_KEY=your_bscscan_api_key_here
POLYGONSCAN_API_KEY=your_polygonscan_api_key_here
ARBISCAN_API_KEY=your_arbiscan_api_key_here
OPTIMISTIC_ETHERSCAN_API_KEY=your_optimistic_etherscan_api_key_here
SNOWTRACE_API_KEY=your_snowtrace_api_key_here
FTMSCAN_API_KEY=your_ftmscan_api_key_here

# Optional: Custom Settings
BLOCKCHAIN_EXPLORER_TIMEOUT=30
BLOCKCHAIN_EXPLORER_RETRY_ATTEMPTS=3
BLOCKCHAIN_EXPLORER_RETRY_DELAY=1000
BLOCKCHAIN_EXPLORER_CACHE_TTL=3600
```

### 2. Get API Keys

- **Etherscan**: https://etherscan.io/apis
- **BscScan**: https://bscscan.com/apis
- **PolygonScan**: https://polygonscan.com/apis
- **Arbiscan**: https://arbiscan.io/apis
- **Optimistic Etherscan**: https://optimistic.etherscan.io/apis
- **Snowtrace**: https://snowtrace.io/apis
- **FtmScan**: https://ftmscan.com/apis

## 💻 Usage Examples

### Basic Usage

```php
use App\Services\BlockchainExplorerService;

$explorer = new BlockchainExplorerService();

// Fetch contract source code
$source = $explorer->getContractSource('ethereum', '0xA0b86a33E6e4a1a0e0f3e2C5B7a0db0a39b0a0a0');

// Check if contract is verified
$isVerified = $explorer->isContractVerified('bsc', '0x...');

// Get contract ABI
$abi = $explorer->getContractAbi('polygon', '0x...');
```

### Advanced Usage

```php
// Bulk fetch multiple contracts
$addresses = [
    '0xA0b86a33E6e4a1a0e0f3e2C5B7a0db0a39b0a0a0',
    '0xB1c97b44D9a3F4c6B8e9E2F5C8a1eb1b48c1b1b1',
];

$results = $explorer->bulkGetContractSources('ethereum', $addresses);

// Get contract creation details
$creation = $explorer->getContractCreation('arbitrum', '0x...');

// Get supported networks
$networks = $explorer->getSupportedNetworks();
```

### Response Format

#### Contract Source Code Response

```php
[
    'network' => 'ethereum',
    'contract_address' => '0x...',
    'contract_name' => 'MyContract',
    'compiler_version' => 'v0.8.19+commit.7dd6d404',
    'optimization_used' => true,
    'optimization_runs' => 200,
    'constructor_arguments' => '...',
    'evm_version' => 'default',
    'library' => '',
    'license_type' => 'MIT',
    'proxy' => false,
    'implementation' => null,
    'source_code' => '...', // Raw source code
    'parsed_sources' => [
        'MyContract.sol' => '...',
        'interfaces/IERC20.sol' => '...',
        // Multiple files for complex contracts
    ],
    'abi' => [...], // Parsed ABI array
    'fetched_at' => '2025-08-02T09:30:00.000000Z',
    'is_verified' => true,
]
```

#### Contract ABI Response

```php
[
    'network' => 'ethereum',
    'contract_address' => '0x...',
    'abi' => [
        [
            'type' => 'function',
            'name' => 'transfer',
            'inputs' => [...],
            'outputs' => [...],
        ],
        // ... more ABI entries
    ],
    'fetched_at' => '2025-08-02T09:30:00.000000Z',
]
```

## 🧪 Testing

### Using the Test Command

```bash
# Test contract source code
php artisan blockchain:test ethereum 0xA0b86a33E6e4a1a0e0f3e2C5B7a0db0a39b0a0a0 --action=source

# Test contract ABI
php artisan blockchain:test bsc 0x... --action=abi

# Test contract creation
php artisan blockchain:test polygon 0x... --action=creation

# Check if verified
php artisan blockchain:test arbitrum 0x... --action=verified

# List supported networks
php artisan blockchain:test ethereum 0x... --action=networks
```

### Example Test Commands

```bash
# Test with Uniswap V2 Router (Ethereum)
php artisan blockchain:test ethereum 0x7a250d5630B4cF539739dF2C5dAcb4c659F2488D

# Test with PancakeSwap Router (BSC)
php artisan blockchain:test bsc 0x10ED43C718714eb63d5aA57B78B54704E256024E

# Test with QuickSwap Router (Polygon)
php artisan blockchain:test polygon 0xa5E0829CaCEd8fFDD4De3c43696c57F7D7A678ff
```

## 🔧 Integration with AI Analysis

### Service Integration

```php
use App\Services\BlockchainExplorerService;
use App\Services\OpenAiAuditService;

class ContractAnalysisService
{
    public function __construct(
        private BlockchainExplorerService $explorer,
        private OpenAiAuditService $aiAudit
    ) {}

    public function analyzeContract(string $network, string $address): array
    {
        // 1. Fetch contract source
        $contract = $this->explorer->getContractSource($network, $address);
        
        if (!$contract['is_verified']) {
            throw new InvalidArgumentException('Contract is not verified');
        }

        // 2. Analyze with AI
        $analysis = [];
        foreach ($contract['parsed_sources'] as $filename => $sourceCode) {
            $analysis[$filename] = $this->aiAudit->auditTransaction($sourceCode);
        }

        return [
            'contract' => $contract,
            'ai_analysis' => $analysis,
            'analyzed_at' => now(),
        ];
    }
}
```

### Database Storage

```php
// Store in your Analysis model
$analysis = Analysis::create([
    'project_id' => $project->id,
    'contract_address' => $contractAddress,
    'network' => $network,
    'source_code' => json_encode($contract['parsed_sources']),
    'abi' => json_encode($contract['abi']),
    'compiler_version' => $contract['compiler_version'],
    'optimization_used' => $contract['optimization_used'],
    'is_verified' => $contract['is_verified'],
    'analysis_data' => json_encode($aiAnalysis),
]);
```

## 🔒 Security Considerations

1. **API Key Security**: Store API keys in environment variables, never commit them
2. **Rate Limiting**: Respect explorer API rate limits
3. **Input Validation**: Always validate contract addresses
4. **Error Handling**: Handle API failures gracefully
5. **Caching**: Cache results to reduce API calls

## 📊 Rate Limits

| Explorer | Free Tier | Pro Tier |
|----------|-----------|----------|
| Etherscan | 5 req/sec | 20+ req/sec |
| BscScan | 5 req/sec | 20+ req/sec |
| PolygonScan | 5 req/sec | 20+ req/sec |
| Others | 5 req/sec | Varies |

## 🛠️ Configuration Options

```php
// config/blockchain_explorers.php
return [
    'etherscan' => [
        'api_key' => env('ETHERSCAN_API_KEY'),
        'api_url' => 'https://api.etherscan.io/api',
        'rate_limit' => 5, // requests per second
        'timeout' => 30,
    ],
    
    'default_timeout' => 30,
    'default_retry_attempts' => 3,
    'default_retry_delay' => 1000,
    'cache_ttl' => 3600,
];
```

## 🚨 Error Handling

The service throws `InvalidArgumentException` for:
- Unsupported networks
- Invalid contract addresses
- Missing API keys
- API failures
- Contract not found/verified

```php
try {
    $source = $explorer->getContractSource('ethereum', '0x...');
} catch (InvalidArgumentException $e) {
    // Handle error
    Log::error('Contract fetch failed', [
        'error' => $e->getMessage(),
        'address' => '0x...',
    ]);
}
```

## 🗄️ PostgreSQL Caching System

The service automatically caches all API responses in PostgreSQL to avoid hitting rate limits:

### Cache Management Commands

```bash
# View cache statistics
php artisan cache:contracts stats

# Clear cache for specific contract
php artisan cache:contracts clear --network=ethereum --address=0x...

# Clear specific cache type for a contract
php artisan cache:contracts clear --network=ethereum --address=0x... --type=source

# Clean up expired cache entries
php artisan cache:contracts cleanup

# Force refresh contract data (bypass cache)
php artisan cache:contracts refresh --network=ethereum --address=0x...
```

### Cache Features

- **Automatic Caching**: All API responses are automatically cached
- **TTL Support**: Configurable cache expiration (default: 1 hour)
- **Cache Types**: Separate caching for source, abi, and creation data
- **Smart Retrieval**: Cache-first approach with automatic fallback to API
- **Statistics**: Detailed cache statistics by network and type

## ✅ Ready for Production

Your BlockchainExplorerService is production-ready with:

- ✅ Multi-network support (7 major chains)
- ✅ Comprehensive error handling
- ✅ Rate limiting and retry logic
- ✅ Proper configuration management
- ✅ Test command for validation
- ✅ Documentation and examples
- ✅ Integration ready for AI analysis
- ✅ PostgreSQL caching system with management commands

Perfect for fetching verified Solidity source code for your AI blockchain analytics platform! 🚀