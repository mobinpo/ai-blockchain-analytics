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
            
            if ($existingAnalysis && is_array($existingAnalysis) && isset($existingAnalysis['id'])) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'analysis_id' => $existingAnalysis['id'],
                        'security_score' => $existingAnalysis['security_score'] ?? 0,
                        'critical_issues' => $existingAnalysis['critical_issues'] ?? 0,
                        'high_issues' => $existingAnalysis['high_issues'] ?? 0,
                        'medium_issues' => $existingAnalysis['medium_issues'] ?? 0,
                        'functions_count' => $existingAnalysis['functions_count'] ?? 0,
                        'lines_of_code' => $existingAnalysis['lines_of_code'] ?? 0,
                        'verified' => $existingAnalysis['verified'] ?? false,
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

            // Provide specific error messages for common issues
            $errorMessage = $e->getMessage();
            $statusCode = 500;
            
            if (str_contains($errorMessage, 'source code is not verified') || 
                str_contains($errorMessage, 'unverified contract')) {
                $statusCode = 422; // Unprocessable Entity
                $errorMessage = 'This contract\'s source code is not verified on the blockchain explorer. We can only analyze contracts with verified source code for security purposes.';
            } elseif (str_contains($errorMessage, 'EOA') || str_contains($errorMessage, 'wallet address')) {
                $statusCode = 422;
                $errorMessage = 'This appears to be a wallet address (EOA), not a smart contract. Please provide a valid smart contract address.';
            }

            return response()->json([
                'success' => false,
                'message' => $errorMessage,
                'error_type' => str_contains($e->getMessage(), 'verified') ? 'unverified_contract' : 'analysis_error',
                'suggestions' => str_contains($e->getMessage(), 'verified') ? [
                    'Please verify the contract source code on Etherscan first',
                    'Try a different contract that has verified source code',
                    'Check if the address is correct'
                ] : null,
                'debug_error' => app()->environment('local') ? $e->getMessage() : null
            ], $statusCode);
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
     * Get popular contracts for examples (from recent analyses)
     */
    public function getPopularContracts(): JsonResponse
    {
        try {
            // Get most analyzed contracts from database
            $popularContracts = \DB::table('analyses')
                ->join('projects', 'analyses.project_id', '=', 'projects.id')
                ->select([
                    'projects.name',
                    'analyses.target_address as address',
                    'projects.blockchain_network as network',
                    'projects.description',
                    \DB::raw('COUNT(*) as analysis_count')
                ])
                ->where('analyses.target_type', 'contract')
                ->where('analyses.status', 'completed')
                ->whereNotNull('projects.name')
                ->whereNotNull('analyses.target_address')
                ->groupBy(['projects.name', 'analyses.target_address', 'projects.blockchain_network', 'projects.description'])
                ->orderBy('analysis_count', 'desc')
                ->limit(6)
                ->get()
                ->map(function ($contract) {
                    return [
                        'name' => $contract->name,
                        'address' => $contract->address,
                        'network' => $contract->network ?? 'ethereum',
                        'category' => $this->guessCategory($contract->name, $contract->description),
                        'description' => $contract->description ?? 'Smart contract analysis project',
                        'analysis_count' => $contract->analysis_count
                    ];
                })
                ->toArray();

            // If no real contracts found, return empty array instead of hardcoded examples
            return response()->json([
                'success' => true,
                'data' => $popularContracts
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch popular contracts', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch popular contracts',
                'data' => []
            ], 500);
        }
    }

    /**
     * Guess contract category based on name and description
     */
    private function guessCategory(string $name, ?string $description): string
    {
        $text = strtolower($name . ' ' . ($description ?? ''));
        
        if (str_contains($text, 'token') || str_contains($text, 'erc20') || str_contains($text, 'coin')) {
            return 'Token';
        }
        if (str_contains($text, 'nft') || str_contains($text, 'erc721') || str_contains($text, 'collectible')) {
            return 'NFT';
        }
        if (str_contains($text, 'defi') || str_contains($text, 'swap') || str_contains($text, 'dex') || 
            str_contains($text, 'lending') || str_contains($text, 'liquidity')) {
            return 'DeFi';
        }
        if (str_contains($text, 'gaming') || str_contains($text, 'game')) {
            return 'Gaming';
        }
        
        return 'Contract';
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
