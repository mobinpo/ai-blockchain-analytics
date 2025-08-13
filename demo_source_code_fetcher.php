<?php

/**
 * Source Code Fetcher Demo Script
 * 
 * This script demonstrates the Source Code Fetching Service for blockchain explorers.
 * It shows how to fetch verified Solidity source code from Etherscan, BscScan, and other explorers.
 */

require_once 'bootstrap/app.php';

use App\Services\SourceCodeService;
use App\Services\MultiChainExplorerManager;

echo "🔍 Source Code Fetcher Demo\n";
echo "============================\n\n";

try {
    // Initialize the service (in a real Laravel app, this would be dependency injected)
    $explorerManager = new MultiChainExplorerManager();
    $sourceCodeService = new SourceCodeService($explorerManager);

    // Demo 1: Fetch source code for USDT contract (Ethereum)
    echo "📋 Demo 1: Fetching USDT contract source code\n";
    echo "Contract: 0xdAC17F958D2ee523a2206206994597C13D831ec7 (Ethereum)\n";
    echo "Network: Ethereum (auto-detected)\n\n";
    
    $usdtAddress = '0xdAC17F958D2ee523a2206206994597C13D831ec7';
    
    // Check verification status first
    echo "⏳ Checking verification status...\n";
    $verification = $sourceCodeService->isContractVerified($usdtAddress, 'ethereum');
    
    if ($verification['is_verified']) {
        echo "✅ Contract is verified on {$verification['network']} via {$verification['explorer']}\n\n";
        
        // Fetch full source code
        echo "⏳ Fetching source code...\n";
        $sourceCode = $sourceCodeService->fetchSourceCode($usdtAddress, 'ethereum');
        
        echo "📄 Results:\n";
        echo "  • Contract Name: {$sourceCode['contract_name']}\n";
        echo "  • Compiler: {$sourceCode['compiler_version']}\n";
        echo "  • Optimization: " . ($sourceCode['optimization_used'] ? 'Enabled' : 'Disabled') . "\n";
        echo "  • Source Files: " . count($sourceCode['parsed_sources']) . "\n";
        echo "  • License: {$sourceCode['license_type']}\n";
        echo "  • Total Lines: {$sourceCode['source_stats']['total_lines']}\n";
        echo "  • Explorer URL: {$sourceCode['explorer_info']['web_url']}\n\n";
        
    } else {
        echo "❌ Contract is not verified\n\n";
    }

    // Demo 2: Get comprehensive contract information
    echo "📊 Demo 2: Comprehensive contract information\n";
    echo "⏳ Fetching comprehensive info...\n";
    
    $info = $sourceCodeService->getContractInfo($usdtAddress, 'ethereum');
    
    if (!isset($info['error'])) {
        echo "📈 Basic Info:\n";
        echo "  • Name: {$info['basic_info']['name']}\n";
        echo "  • Verified: " . ($info['basic_info']['is_verified'] ? 'Yes' : 'No') . "\n";
        echo "  • Proxy: " . ($info['basic_info']['is_proxy'] ? 'Yes' : 'No') . "\n\n";
        
        echo "🔧 Compilation Info:\n";
        echo "  • Compiler: {$info['compilation_info']['compiler_version']}\n";
        echo "  • Optimization: " . ($info['compilation_info']['optimization_used'] ? 'Yes' : 'No') . "\n";
        echo "  • EVM Version: {$info['compilation_info']['evm_version']}\n\n";
        
        echo "📊 Source Stats:\n";
        echo "  • Files: {$info['source_info']['source_files']}\n";
        echo "  • Lines: {$info['source_info']['total_lines']}\n";
        echo "  • Functions: {$info['function_info']['total_functions']}\n\n";
    }

    // Demo 3: Extract function signatures
    echo "🔧 Demo 3: Function signature extraction\n";
    echo "⏳ Extracting functions...\n";
    
    $functions = $sourceCodeService->extractFunctionSignatures($usdtAddress, 'ethereum');
    
    echo "📋 Function Signatures (first 5):\n";
    foreach (array_slice($functions['functions'], 0, 5) as $func) {
        echo "  • " . trim($func) . "\n";
    }
    echo "  ... and " . max(0, $functions['total_functions'] - 5) . " more functions\n\n";

    // Demo 4: Multi-network verification check
    echo "🌐 Demo 4: Multi-network verification check\n";
    echo "⏳ Checking across all supported networks...\n";
    
    $multiVerification = $sourceCodeService->isContractVerified($usdtAddress);
    
    echo "🔍 Verification Results:\n";
    if (isset($multiVerification['verification_status'])) {
        foreach ($multiVerification['verification_status'] as $status) {
            $icon = $status['is_verified'] ? '✅' : '❌';
            echo "  {$icon} {$status['network']}: " . ($status['is_verified'] ? 'Verified' : 'Not found') . "\n";
        }
        echo "\n📊 Overall: " . ($multiVerification['has_verified_contract'] ? 'Contract is verified' : 'Contract not verified') . "\n\n";
    }

    // Demo 5: Pattern search (if contract is verified)
    if ($verification['is_verified']) {
        echo "🔍 Demo 5: Pattern search in source code\n";
        echo "⏳ Searching for 'transfer' patterns...\n";
        
        $searchResults = $sourceCodeService->searchBySourcePattern([$usdtAddress], 'transfer', 'ethereum');
        
        echo "📋 Search Results:\n";
        echo "  • Pattern: {$searchResults['pattern']}\n";
        echo "  • Contracts checked: {$searchResults['total_checked']}\n";
        echo "  • Matches found: {$searchResults['matches_found']}\n";
        
        if ($searchResults['matches_found'] > 0) {
            echo "  • Sample matches:\n";
            foreach ($searchResults['results'] as $result) {
                $matchCount = count($result['matches']);
                echo "    - {$result['contract_name']}: {$matchCount} matches\n";
            }
        }
        echo "\n";
    }

    echo "✅ Demo completed successfully!\n\n";
    
    // Usage examples
    echo "💡 API Usage Examples:\n";
    echo "========================\n\n";
    
    echo "1. Basic source code fetching:\n";
    echo "   GET /api/source-code/fetch?contract_address=0x123...&network=ethereum\n\n";
    
    echo "2. Get contract ABI:\n";
    echo "   GET /api/source-code/abi?contract_address=0x123...\n\n";
    
    echo "3. Check verification status:\n";
    echo "   GET /api/source-code/verify?contract_address=0x123...\n\n";
    
    echo "4. Extract function signatures:\n";
    echo "   GET /api/source-code/functions?contract_address=0x123...\n\n";
    
    echo "5. Pattern search:\n";
    echo "   POST /api/source-code/search\n";
    echo "   Body: {\"addresses\": [\"0x123...\"], \"pattern\": \"transfer\"}\n\n";
    
    echo "6. Batch processing:\n";
    echo "   POST /api/source-code/batch\n";
    echo "   Body: {\"contracts\": [{\"address\": \"0x123...\", \"network\": \"ethereum\"}]}\n\n";
    
    echo "📚 Available Networks:\n";
    echo "  • ethereum (Etherscan)\n";
    echo "  • bsc (BscScan)\n";
    echo "  • polygon (PolygonScan)\n";
    echo "  • arbitrum (Arbiscan)\n";
    echo "  • optimism (Optimistic Etherscan)\n";
    echo "  • avalanche (SnowTrace)\n\n";
    
    echo "🎯 CLI Commands:\n";
    echo "  • php artisan test:source-code --demo\n";
    echo "  • php artisan test:source-code --contract=0x123...\n";
    echo "  • php artisan test:source-code --batch\n";
    echo "  • php artisan test:source-code --pattern=transfer\n\n";
    
    echo "🔧 Service Features:\n";
    echo "  ✅ Multi-chain support with auto-detection\n";
    echo "  ✅ Comprehensive source code parsing\n";
    echo "  ✅ Function signature extraction\n";
    echo "  ✅ Pattern search across source files\n";
    echo "  ✅ Contract verification status\n";
    echo "  ✅ Creation transaction info\n";
    echo "  ✅ ABI extraction\n";
    echo "  ✅ Caching for performance\n";
    echo "  ✅ Batch processing\n";
    echo "  ✅ RESTful API endpoints\n\n";

} catch (Exception $e) {
    echo "❌ Demo failed: {$e->getMessage()}\n";
    echo "💡 Make sure your blockchain explorer API keys are configured in .env\n\n";
    
    echo "Required environment variables:\n";
    echo "  • ETHERSCAN_API_KEY\n";
    echo "  • BSCSCAN_API_KEY\n";
    echo "  • POLYGONSCAN_API_KEY\n";
    echo "  • ARBISCAN_API_KEY\n";
    echo "  • OPTIMISMSCAN_API_KEY\n";
    echo "  • SNOWTRACE_API_KEY\n\n";
}

echo "📖 For more information, see the API documentation and test commands.\n";