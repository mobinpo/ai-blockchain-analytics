<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\ApiCacheService;
use App\Services\CoinGeckoCacheService;
use App\Services\BlockchainCacheService;
use Illuminate\Foundation\Application;

/**
 * PostgreSQL Cache System Test Script
 * 
 * This script demonstrates the comprehensive caching system that helps avoid API limits
 * by storing frequently accessed data in PostgreSQL with intelligent TTL management.
 */

echo "🚀 POSTGRESQL CACHE SYSTEM TEST\n";
echo "=====================================\n\n";

// Initialize Laravel application
$app = new Application(dirname(__DIR__));
$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);
$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);
$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Get services
$cacheService = $app->make(ApiCacheService::class);
$coinGeckoService = $app->make(CoinGeckoCacheService::class);
$blockchainService = $app->make(BlockchainCacheService::class);

echo "1. 📊 INITIAL CACHE STATISTICS\n";
echo "==============================\n";
$stats = $cacheService->getStatistics();
displayStats($stats);

echo "\n2. 🏥 CACHE HEALTH CHECK\n";
echo "========================\n";
$health = $cacheService->healthCheck();
echo "Status: " . ($health['status'] === 'healthy' ? '✅ Healthy' : '⚠️  Needs Attention') . "\n";
if (!empty($health['issues'])) {
    echo "Issues:\n";
    foreach ($health['issues'] as $issue) {
        echo "  • $issue\n";
    }
}
if (!empty($health['recommendations'])) {
    echo "Recommendations:\n";
    foreach ($health['recommendations'] as $rec) {
        echo "  • $rec\n";
    }
}

echo "\n3. 🪙 TESTING COINGECKO CACHE\n";
echo "=============================\n";

// Test CoinGecko price caching
$testCoins = ['bitcoin', 'ethereum', 'cardano'];
echo "Testing price caching for: " . implode(', ', $testCoins) . "\n";

$startTime = microtime(true);
try {
    $prices1 = $coinGeckoService->getCurrentPrice($testCoins, 'usd', true, true, true);
    $firstCallTime = microtime(true) - $startTime;
    echo "✅ First call (API): " . round($firstCallTime * 1000, 2) . "ms\n";
    
    $startTime = microtime(true);
    $prices2 = $coinGeckoService->getCurrentPrice($testCoins, 'usd', true, true, true);
    $secondCallTime = microtime(true) - $startTime;
    echo "✅ Second call (Cache): " . round($secondCallTime * 1000, 2) . "ms\n";
    
    $speedup = round($firstCallTime / $secondCallTime, 1);
    echo "🚀 Cache speedup: {$speedup}x faster\n";
    
    echo "Sample price data:\n";
    foreach ($testCoins as $coin) {
        if (isset($prices2[$coin]['usd'])) {
            echo "  • $coin: $" . number_format($prices2[$coin]['usd'], 2) . "\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n4. ⛓️  TESTING BLOCKCHAIN CACHE\n";
echo "===============================\n";

// Test blockchain data caching
$testContract = '0xA0b86a33E6441f8C166768C8248906dEF09B2860'; // Uniswap V3 Router
echo "Testing contract ABI caching for: $testContract\n";

$startTime = microtime(true);
try {
    $abi1 = $blockchainService->getContractABI($testContract);
    $firstCallTime = microtime(true) - $startTime;
    echo "✅ First call (API): " . round($firstCallTime * 1000, 2) . "ms\n";
    
    $startTime = microtime(true);
    $abi2 = $blockchainService->getContractABI($testContract);
    $secondCallTime = microtime(true) - $startTime;
    echo "✅ Second call (Cache): " . round($secondCallTime * 1000, 2) . "ms\n";
    
    $speedup = round($firstCallTime / $secondCallTime, 1);
    echo "🚀 Cache speedup: {$speedup}x faster\n";
    
    if (isset($abi2['result']) && is_string($abi2['result'])) {
        $abiArray = json_decode($abi2['result'], true);
        if (is_array($abiArray)) {
            echo "📋 ABI contains " . count($abiArray) . " function/event definitions\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n5. 🧹 TESTING CACHE CLEANUP\n";
echo "============================\n";

// Test cleanup functionality
echo "Performing cache cleanup...\n";
$cleanupStats = $cacheService->cleanup(false);
echo "✅ Cleanup completed:\n";
echo "  • Deleted entries: " . $cleanupStats['deleted'] . "\n";
echo "  • Space freed: " . $cleanupStats['size_freed_mb'] . " MB\n";

echo "\n6. 🔥 TESTING CACHE WARMING\n";
echo "============================\n";

// Test cache warming
echo "Warming CoinGecko cache with popular coins...\n";
try {
    $warmed = $coinGeckoService->warmPopularCoins(['bitcoin', 'ethereum', 'binancecoin']);
    echo "✅ Warmed cache for $warmed coins\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n7. 📈 BATCH CACHE TESTING\n";
echo "=========================\n";

// Test batch operations
$batchData = [
    [
        'api_source' => 'test',
        'endpoint' => 'demo/price',
        'resource_type' => 'price',
        'response_data' => ['price' => 100, 'currency' => 'USD'],
        'resource_id' => 'demo-token',
        'ttl' => 300,
    ],
    [
        'api_source' => 'test',
        'endpoint' => 'demo/volume',
        'resource_type' => 'volume',
        'response_data' => ['volume_24h' => 1000000],
        'resource_id' => 'demo-token',
        'ttl' => 600,
    ],
];

echo "Batch caching test data...\n";
$batchResults = $cacheService->cacheBatch($batchData);
echo "✅ Cached " . count($batchResults) . " items in batch\n";

echo "\n8. 🔍 CACHE INVALIDATION TEST\n";
echo "==============================\n";

// Test invalidation
echo "Testing cache invalidation...\n";
$invalidated = $cacheService->invalidate(['api_source' => 'test']);
echo "✅ Invalidated $invalidated test cache entries\n";

echo "\n9. 📊 FINAL CACHE STATISTICS\n";
echo "============================\n";
$finalStats = $cacheService->getStatistics();
displayStats($finalStats);

echo "\n10. 💰 COST SAVINGS ANALYSIS\n";
echo "=============================\n";

$coinGeckoStats = $coinGeckoService->getRateLimitStatus();
$blockchainStats = $blockchainService->getCacheStatistics();

echo "CoinGecko Performance:\n";
echo "  • Hit Ratio: {$coinGeckoStats['cache_hit_ratio']}%\n";
echo "  • API Calls Saved: " . number_format($coinGeckoStats['total_api_calls_saved']) . "\n";
echo "  • Estimated Cost Saved: $" . number_format($coinGeckoStats['estimated_cost_saved'], 2) . "\n";

echo "\nBlockchain APIs Performance:\n";
echo "  • Etherscan Hits: " . number_format($blockchainStats['etherscan']['total_hits']) . "\n";
echo "  • Moralis Hits: " . number_format($blockchainStats['moralis']['total_hits']) . "\n";
echo "  • Total Calls Saved: " . number_format($blockchainStats['total_blockchain_calls_saved']) . "\n";
echo "  • Etherscan Cost Saved: $" . number_format($blockchainStats['estimated_cost_saved']['etherscan'], 4) . "\n";
echo "  • Moralis Cost Saved: $" . number_format($blockchainStats['estimated_cost_saved']['moralis'], 3) . "\n";

echo "\n✅ POSTGRESQL CACHE SYSTEM TEST COMPLETED!\n";
echo "==========================================\n\n";

echo "🎯 KEY BENEFITS:\n";
echo "• Dramatic API call reduction (up to 90%+ hit ratio possible)\n";
echo "• Significant cost savings on external API usage\n";
echo "• Improved response times (cache hits are 10-100x faster)\n";
echo "• Automatic TTL management based on data type\n";
echo "• Comprehensive monitoring and health checks\n";
echo "• Flexible invalidation and warming strategies\n";
echo "• PostgreSQL JSONB indexes for fast querying\n";
echo "• Data integrity verification with response hashes\n\n";

echo "🚀 Access the admin interface at: /admin/cache\n";
echo "🔧 Run maintenance with: php artisan cache:maintenance --all\n\n";

/**
 * Display cache statistics in a formatted way.
 */
function displayStats(array $stats): void
{
    echo "📊 Cache Overview:\n";
    echo "  • Total Entries: " . number_format($stats['total_entries']) . "\n";
    echo "  • Valid Entries: " . number_format($stats['valid_entries']) . "\n";
    echo "  • Expired Entries: " . number_format($stats['expired_entries']) . "\n";
    echo "  • Hit Ratio: {$stats['cache_hit_ratio']}%\n";
    echo "  • Cache Size: {$stats['cache_size_mb']} MB\n";
    echo "  • API Calls Saved: " . number_format($stats['api_cost_saved']) . "\n";
    echo "  • Average Efficiency: {$stats['efficiency_avg']}%\n";
    
    if (!empty($stats['api_sources'])) {
        echo "  • API Sources: " . implode(', ', $stats['api_sources']) . "\n";
    }
    
    if (!empty($stats['resource_types'])) {
        echo "  • Resource Types: " . implode(', ', $stats['resource_types']) . "\n";
    }
}
