<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SourceCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SourceCodeController extends Controller
{
    public function __construct(
        private readonly SourceCodeService $sourceCodeService
    ) {
    }

    /**
     * Fetch verified Solidity source code for a contract
     */
    public function fetchSourceCode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'contract_address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/',
            'network' => 'nullable|string|in:ethereum,bsc,polygon,arbitrum,optimism,avalanche',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        try {
            $sourceCode = $this->sourceCodeService->fetchSourceCode(
                $request->string('contract_address')->toString(),
                $request->string('network')->toString() ?: null
            );

            Log::info('Source code fetched successfully', [
                'contract' => $request->string('contract_address')->toString(),
                'network' => $sourceCode['network'] ?? 'auto-detected',
                'verified' => $sourceCode['is_verified'] ?? false,
            ]);

            return response()->json([
                'success' => true,
                'data' => $sourceCode,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch source code', [
                'contract' => $request->string('contract_address')->toString(),
                'network' => $request->string('network')->toString(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch source code',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fetch contract ABI
     */
    public function fetchContractAbi(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'contract_address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/',
            'network' => 'nullable|string|in:ethereum,bsc,polygon,arbitrum,optimism,avalanche',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        try {
            $abi = $this->sourceCodeService->fetchContractAbi(
                $request->string('contract_address')->toString(),
                $request->string('network')->toString() ?: null
            );

            return response()->json([
                'success' => true,
                'data' => $abi,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch contract ABI', [
                'contract' => $request->string('contract_address')->toString(),
                'network' => $request->string('network')->toString(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch contract ABI',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get contract creation information
     */
    public function getContractCreation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'contract_address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/',
            'network' => 'nullable|string|in:ethereum,bsc,polygon,arbitrum,optimism,avalanche',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        try {
            $creation = $this->sourceCodeService->getContractCreation(
                $request->string('contract_address')->toString(),
                $request->string('network')->toString() ?: null
            );

            return response()->json([
                'success' => true,
                'data' => $creation,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch contract creation info', [
                'contract' => $request->string('contract_address')->toString(),
                'network' => $request->string('network')->toString(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch contract creation info',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if contract is verified
     */
    public function checkVerificationStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'contract_address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/',
            'network' => 'nullable|string|in:ethereum,bsc,polygon,arbitrum,optimism,avalanche',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        try {
            $verification = $this->sourceCodeService->isContractVerified(
                $request->string('contract_address')->toString(),
                $request->string('network')->toString() ?: null
            );

            return response()->json([
                'success' => true,
                'data' => $verification,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to check verification status', [
                'contract' => $request->string('contract_address')->toString(),
                'network' => $request->string('network')->toString(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to check verification status',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search contracts by source code patterns
     */
    public function searchByPattern(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'addresses' => 'required|array|min:1|max:20',
            'addresses.*' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/',
            'pattern' => 'required|string|min:3|max:200',
            'network' => 'nullable|string|in:ethereum,bsc,polygon,arbitrum,optimism,avalanche',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        try {
            $results = $this->sourceCodeService->searchBySourcePattern(
                $request->input('addresses'),
                $request->string('pattern')->toString(),
                $request->string('network')->toString() ?: null
            );

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to search by pattern', [
                'addresses_count' => count($request->input('addresses', [])),
                'pattern' => $request->string('pattern')->toString(),
                'network' => $request->string('network')->toString(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to search by pattern',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Extract function signatures from contract
     */
    public function extractFunctionSignatures(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'contract_address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/',
            'network' => 'nullable|string|in:ethereum,bsc,polygon,arbitrum,optimism,avalanche',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        try {
            $functions = $this->sourceCodeService->extractFunctionSignatures(
                $request->string('contract_address')->toString(),
                $request->string('network')->toString() ?: null
            );

            return response()->json([
                'success' => true,
                'data' => $functions,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to extract function signatures', [
                'contract' => $request->string('contract_address')->toString(),
                'network' => $request->string('network')->toString(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to extract function signatures',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get comprehensive contract information
     */
    public function getContractInfo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'contract_address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/',
            'network' => 'nullable|string|in:ethereum,bsc,polygon,arbitrum,optimism,avalanche',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        try {
            $info = $this->sourceCodeService->getContractInfo(
                $request->string('contract_address')->toString(),
                $request->string('network')->toString() ?: null
            );

            return response()->json([
                'success' => true,
                'data' => $info,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get contract info', [
                'contract' => $request->string('contract_address')->toString(),
                'network' => $request->string('network')->toString(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to get contract info',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Batch fetch multiple contract source codes
     */
    public function batchFetchSourceCode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'contracts' => 'required|array|min:1|max:10',
            'contracts.*.address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/',
            'contracts.*.network' => 'nullable|string|in:ethereum,bsc,polygon,arbitrum,optimism,avalanche',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        $results = [];
        $errors = [];

        foreach ($request->input('contracts') as $index => $contract) {
            try {
                $sourceCode = $this->sourceCodeService->fetchSourceCode(
                    $contract['address'],
                    $contract['network'] ?? null
                );

                $results[] = [
                    'index' => $index,
                    'address' => $contract['address'],
                    'success' => true,
                    'data' => $sourceCode,
                ];

            } catch (\Exception $e) {
                $errors[] = [
                    'index' => $index,
                    'address' => $contract['address'],
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        Log::info('Batch source code fetch completed', [
            'total_requests' => count($request->input('contracts')),
            'successful' => count($results),
            'failed' => count($errors),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'total_requests' => count($request->input('contracts')),
                'successful' => count($results),
                'failed' => count($errors),
                'results' => array_merge($results, $errors),
            ],
        ]);
    }

    /**
     * Get supported networks
     */
    public function getSupportedNetworks(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'networks' => [
                    ['id' => 'ethereum', 'name' => 'Ethereum', 'explorer' => 'Etherscan'],
                    ['id' => 'bsc', 'name' => 'Binance Smart Chain', 'explorer' => 'BscScan'],
                    ['id' => 'polygon', 'name' => 'Polygon', 'explorer' => 'PolygonScan'],
                    ['id' => 'arbitrum', 'name' => 'Arbitrum', 'explorer' => 'Arbiscan'],
                    ['id' => 'optimism', 'name' => 'Optimism', 'explorer' => 'Optimistic Etherscan'],
                    ['id' => 'avalanche', 'name' => 'Avalanche C-Chain', 'explorer' => 'SnowTrace'],
                ],
                'total_networks' => 6,
                'auto_detection_available' => true,
            ],
        ]);
    }
}