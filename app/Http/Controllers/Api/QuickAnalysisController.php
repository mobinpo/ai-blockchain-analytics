<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuickAnalysisRequest;
use App\Services\QuickAnalysisService;
use App\Services\SourceCodeService;
use App\Services\ContractValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

final class QuickAnalysisController extends Controller
{
    public function __construct(
        private readonly QuickAnalysisService $quickAnalysisService,
        private readonly SourceCodeService $sourceCodeService,
        private readonly ContractValidationService $validationService
    ) {
    }

    /**
     * Get quick contract information for validation
     */
    public function getQuickInfo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid contract address format',
                'errors' => $validator->errors()
            ], 400);
        }

        $address = $request->input('address');
        $cacheKey = "quick_info:{$address}";

        try {
            // Check cache first
            $cachedInfo = Cache::get($cacheKey);
            if ($cachedInfo) {
                return response()->json([
                    'success' => true,
                    'data' => $cachedInfo
                ]);
            }

            // Validate contract and detect network
            $contractInfo = $this->validationService->validateAndDetectNetwork($address);

            if (!$contractInfo['exists']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contract not found on any supported network'
                ], 404);
            }

            $responseData = [
                'network' => $contractInfo['network'],
                'contractInfo' => [
                    'name' => $contractInfo['name'] ?? 'Unknown Contract',
                    'verified' => $contractInfo['verified'] ?? false,
                    'compiler' => $contractInfo['compiler'] ?? null,
                    'optimization' => $contractInfo['optimization'] ?? null,
                    'network' => $contractInfo['network']
                ]
            ];

            // Cache for 5 minutes
            Cache::put($cacheKey, $responseData, 300);

            return response()->json([
                'success' => true,
                'data' => $responseData
            ]);

        } catch (\Exception $e) {
            Log::error('Quick info error', [
                'address' => $address,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch contract information'
            ], 500);
        }
    }

    /**
     * Perform quick contract analysis
     */
    public function quickAnalyze(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'contract_address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/',
            'network' => 'nullable|string|in:ethereum,bsc,polygon,arbitrum,optimism,avalanche',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request parameters',
                'errors' => $validator->errors()
            ], 400);
        }

        $contractAddress = $request->input('contract_address');
        $network = $request->input('network', 'ethereum');

        // Rate limiting for quick analysis
        $rateLimitKey = "quick_analysis:" . $request->ip();
        $attempts = Cache::get($rateLimitKey, 0);
        
        if ($attempts >= 10) {
            return response()->json([
                'success' => false,
                'message' => 'Rate limit exceeded. Please try again later.'
            ], 429);
        }

        Cache::put($rateLimitKey, $attempts + 1, 3600); // 1 hour window

        try {
            // Check if analysis already exists
            $existingAnalysis = $this->quickAnalysisService->getExistingAnalysis($contractAddress, $network);
            
            if ($existingAnalysis) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'analysis_id' => $existingAnalysis['id'],
                        'security_score' => $existingAnalysis['security_score'],
                        'critical_issues' => $existingAnalysis['critical_issues'],
                        'high_issues' => $existingAnalysis['high_issues'],
                        'medium_issues' => $existingAnalysis['medium_issues'],
                        'functions_count' => $existingAnalysis['functions_count'],
                        'lines_of_code' => $existingAnalysis['lines_of_code'],
                        'verified' => $existingAnalysis['verified'],
                        'cached' => true,
                        'completed_at' => $existingAnalysis['completed_at']
                    ]
                ]);
            }

            // Perform quick analysis
            $analysisResult = $this->quickAnalysisService->performQuickAnalysis(
                $contractAddress,
                $network
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'analysis_id' => $analysisResult['analysis_id'],
                    'security_score' => $analysisResult['security_score'],
                    'critical_issues' => $analysisResult['critical_issues'],
                    'high_issues' => $analysisResult['high_issues'],
                    'medium_issues' => $analysisResult['medium_issues'],
                    'functions_count' => $analysisResult['functions_count'],
                    'lines_of_code' => $analysisResult['lines_of_code'],
                    'verified' => $analysisResult['verified'],
                    'cached' => false,
                    'processing_time' => $analysisResult['processing_time']
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Quick analysis error', [
                'contract_address' => $contractAddress,
                'network' => $network,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Analysis failed. Please try again later.',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get supported networks
     */
    public function getSupportedNetworks(): JsonResponse
    {
        $networks = [
            'ethereum' => [
                'name' => 'Ethereum',
                'chain_id' => 1,
                'explorer' => 'https://etherscan.io',
                'icon' => '/images/networks/ethereum.png',
                'native_token' => 'ETH'
            ],
            'bsc' => [
                'name' => 'Binance Smart Chain',
                'chain_id' => 56,
                'explorer' => 'https://bscscan.com',
                'icon' => '/images/networks/bsc.png',
                'native_token' => 'BNB'
            ],
            'polygon' => [
                'name' => 'Polygon',
                'chain_id' => 137,
                'explorer' => 'https://polygonscan.com',
                'icon' => '/images/networks/polygon.png',
                'native_token' => 'MATIC'
            ],
            'arbitrum' => [
                'name' => 'Arbitrum One',
                'chain_id' => 42161,
                'explorer' => 'https://arbiscan.io',
                'icon' => '/images/networks/arbitrum.png',
                'native_token' => 'ETH'
            ],
            'optimism' => [
                'name' => 'Optimism',
                'chain_id' => 10,
                'explorer' => 'https://optimistic.etherscan.io',
                'icon' => '/images/networks/optimism.png',
                'native_token' => 'ETH'
            ],
            'avalanche' => [
                'name' => 'Avalanche C-Chain',
                'chain_id' => 43114,
                'explorer' => 'https://snowtrace.io',
                'icon' => '/images/networks/avalanche.png',
                'native_token' => 'AVAX'
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $networks
        ]);
    }

    /**
     * Get popular contracts for examples
     */
    public function getPopularContracts(): JsonResponse
    {
        $popularContracts = [
            [
                'name' => 'Uniswap V2 Factory',
                'address' => '0x5C69bEe701ef814a2B6a3EDD4B1652CB9cc5aA6f',
                'network' => 'ethereum',
                'category' => 'DeFi',
                'description' => 'Decentralized exchange factory contract'
            ],
            [
                'name' => 'USDC Token',
                'address' => '0xA0b86a33E6417c7e4E6b42b0Db8FC0a41F34a3B4',
                'network' => 'ethereum',
                'category' => 'Token',
                'description' => 'USD Coin stablecoin contract'
            ],
            [
                'name' => 'PancakeSwap Factory',
                'address' => '0xcA143Ce32Fe78f1f7019d7d551a6402fC5350c73',
                'network' => 'bsc',
                'category' => 'DeFi',
                'description' => 'BSC decentralized exchange factory'
            ],
            [
                'name' => 'AAVE Protocol',
                'address' => '0x7d2768dE32b0b80b7a3454c06BdAc94A69DDc7A9',
                'network' => 'ethereum',
                'category' => 'DeFi',
                'description' => 'Lending protocol contract'
            ],
            [
                'name' => 'Compound Finance',
                'address' => '0x3d9819210A31b4961b30EF54bE2aeD79B9c9Cd3B',
                'network' => 'ethereum',
                'category' => 'DeFi',
                'description' => 'Compound comptroller contract'
            ],
            [
                'name' => 'OpenSea Registry',
                'address' => '0xa5409ec958C83C3f309868babACA7c86DCB077c1',
                'network' => 'ethereum',
                'category' => 'NFT',
                'description' => 'OpenSea marketplace registry'
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $popularContracts
        ]);
    }

    /**
     * Get analysis status for streaming updates
     */
    public function getAnalysisStatus(string $analysisId): JsonResponse
    {
        try {
            $status = $this->quickAnalysisService->getAnalysisStatus($analysisId);

            if (!$status) {
                return response()->json([
                    'success' => false,
                    'message' => 'Analysis not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $status
            ]);

        } catch (\Exception $e) {
            Log::error('Analysis status error', [
                'analysis_id' => $analysisId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get analysis status'
            ], 500);
        }
    }

    /**
     * Cancel ongoing analysis
     */
    public function cancelAnalysis(string $analysisId): JsonResponse
    {
        try {
            $cancelled = $this->quickAnalysisService->cancelAnalysis($analysisId);

            if (!$cancelled) {
                return response()->json([
                    'success' => false,
                    'message' => 'Analysis not found or cannot be cancelled'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Analysis cancelled successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Analysis cancellation error', [
                'analysis_id' => $analysisId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel analysis'
            ], 500);
        }
    }
}
