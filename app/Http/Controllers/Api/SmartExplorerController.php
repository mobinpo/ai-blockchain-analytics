<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ChainDetectorService;
use App\Services\SmartChainSwitchingService;
use App\Services\BlockchainExplorerFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Smart Explorer API Controller
 * 
 * Provides intelligent blockchain explorer functionality with automatic
 * chain detection, smart switching, and optimal explorer selection.
 */
final class SmartExplorerController extends Controller
{
    public function __construct(
        private readonly ChainDetectorService $chainDetector,
        private readonly SmartChainSwitchingService $smartSwitching
    ) {}

    /**
     * Detect which blockchain networks a contract exists on
     */
    public function detectChain(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'contract_address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/',
            'use_cache' => 'sometimes|boolean',
            'include_details' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $contractAddress = strtolower($validator->validated()['contract_address']);
        $useCache = $validator->validated()['use_cache'] ?? true;
        $includeDetails = $validator->validated()['include_details'] ?? false;

        try {
            $startTime = microtime(true);
            
            if (!$useCache) {
                $this->chainDetector->clearDetectionCache($contractAddress);
            }
            
            $results = $this->chainDetector->detectChain($contractAddress);
            $detectionTime = round((microtime(true) - $startTime) * 1000, 2);

            $response = [
                'success' => true,
                'data' => [
                    'contract_address' => $contractAddress,
                    'found_on_networks' => $results['found_on'],
                    'total_networks_checked' => $results['total_networks_checked'],
                    'detection_time_ms' => $detectionTime,
                    'cached_result' => $useCache && isset($results['detected_at']),
                ],
                'meta' => [
                    'api_version' => '2.0',
                    'timestamp' => now()->toISOString()
                ]
            ];

            if ($includeDetails) {
                $response['data']['detailed_results'] = $results['detection_results'];
                $response['data']['errors'] = $results['errors'];
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('Chain detection failed', [
                'contract_address' => $contractAddress,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Chain detection failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get contract source code with intelligent explorer selection
     */
    public function getContractSource(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'contract_address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/',
            'preferred_network' => 'sometimes|string|in:ethereum,bsc,polygon,arbitrum,optimism,avalanche,fantom',
            'include_raw_response' => 'sometimes|boolean',
            'max_retries' => 'sometimes|integer|min:1|max:5'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $contractAddress = strtolower($data['contract_address']);
        $preferredNetwork = $data['preferred_network'] ?? null;
        $includeRawResponse = $data['include_raw_response'] ?? false;
        $maxRetries = $data['max_retries'] ?? 3;

        try {
            $startTime = microtime(true);
            
            $result = $this->smartSwitching->executeWithSmartSwitching(
                $contractAddress,
                function ($explorer, $network) use ($contractAddress) {
                    return $explorer->getContractSource($contractAddress);
                },
                [
                    'preferred_network' => $preferredNetwork,
                    'max_retries' => $maxRetries
                ]
            );

            $totalTime = round((microtime(true) - $startTime) * 1000, 2);

            $response = [
                'success' => true,
                'data' => [
                    'contract_address' => $contractAddress,
                    'network_used' => $result['network_used'],
                    'explorer_used' => $result['explorer_used'],
                    'source_code' => $result['result'],
                    'performance' => [
                        'attempts_made' => $result['attempts_made'],
                        'response_time_ms' => $result['response_time_ms'],
                        'total_time_ms' => $totalTime,
                        'explorer_switched' => $result['switched_explorer']
                    ]
                ],
                'meta' => [
                    'api_version' => '2.0',
                    'timestamp' => now()->toISOString()
                ]
            ];

            if ($includeRawResponse) {
                $response['data']['raw_response'] = $result;
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('Smart contract source retrieval failed', [
                'contract_address' => $contractAddress,
                'preferred_network' => $preferredNetwork,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve contract source',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check contract verification status across all networks
     */
    public function checkVerification(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'contract_address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/',
            'include_details' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $contractAddress = strtolower($validator->validated()['contract_address']);
        $includeDetails = $validator->validated()['include_details'] ?? false;

        try {
            $startTime = microtime(true);
            $results = $this->smartSwitching->getVerificationStatus($contractAddress);
            $checkTime = round((microtime(true) - $startTime) * 1000, 2);

            $response = [
                'success' => true,
                'data' => [
                    'contract_address' => $contractAddress,
                    'is_verified' => $results['is_verified'],
                    'verified_networks' => $results['verified_networks'] ?? [],
                    'fastest_verified_network' => $results['fastest_verified_network'] ?? null,
                    'recommendation' => $results['recommendation'],
                    'check_time_ms' => $checkTime
                ],
                'meta' => [
                    'api_version' => '2.0',
                    'timestamp' => now()->toISOString()
                ]
            ];

            if ($includeDetails && isset($results['verification_details'])) {
                $response['data']['verification_details'] = $results['verification_details'];
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('Contract verification check failed', [
                'contract_address' => $contractAddress,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Verification check failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get optimal explorer for a contract with reasoning
     */
    public function getOptimalExplorer(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'contract_address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/',
            'preferred_network' => 'sometimes|string|in:ethereum,bsc,polygon,arbitrum,optimism,avalanche,fantom',
            'include_scoring' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $contractAddress = strtolower($data['contract_address']);
        $preferredNetwork = $data['preferred_network'] ?? null;
        $includeScoring = $data['include_scoring'] ?? false;

        try {
            $startTime = microtime(true);
            
            $explorerInfo = $this->smartSwitching->getOptimalExplorer(
                $contractAddress,
                $preferredNetwork
            );
            
            $selectionTime = round((microtime(true) - $startTime) * 1000, 2);

            $response = [
                'success' => true,
                'data' => [
                    'contract_address' => $contractAddress,
                    'optimal_network' => $explorerInfo['network'],
                    'explorer_name' => $explorerInfo['explorer']->getName(),
                    'explorer_url' => $explorerInfo['explorer']->getContractUrl($contractAddress),
                    'selection_reason' => $explorerInfo['selection_reason'] ?? 'Optimal choice',
                    'health_score' => $explorerInfo['health_score'] ?? null,
                    'selection_time_ms' => $selectionTime
                ],
                'meta' => [
                    'api_version' => '2.0',
                    'timestamp' => now()->toISOString()
                ]
            ];

            if ($includeScoring && isset($explorerInfo['network_scores'])) {
                $response['data']['network_scores'] = $explorerInfo['network_scores'];
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('Optimal explorer selection failed', [
                'contract_address' => $contractAddress,
                'preferred_network' => $preferredNetwork,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Optimal explorer selection failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get system health and statistics
     */
    public function getSystemStats(): JsonResponse
    {
        try {
            $stats = $this->smartSwitching->getChainSwitchingStats();
            
            return response()->json([
                'success' => true,
                'data' => $stats,
                'meta' => [
                    'api_version' => '2.0',
                    'timestamp' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve system stats', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve system statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get supported networks and their configurations
     */
    public function getSupportedNetworks(): JsonResponse
    {
        try {
            $networks = BlockchainExplorerFactory::getNetworkInfo();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'supported_networks' => array_keys($networks),
                    'network_details' => $networks,
                    'total_networks' => count($networks)
                ],
                'meta' => [
                    'api_version' => '2.0',
                    'timestamp' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve supported networks',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear caches for a specific contract
     */
    public function clearCache(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'contract_address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/',
            'cache_type' => 'sometimes|string|in:detection,switching,all'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $contractAddress = strtolower($validator->validated()['contract_address']);
        $cacheType = $validator->validated()['cache_type'] ?? 'all';

        try {
            $cleared = [];

            if ($cacheType === 'detection' || $cacheType === 'all') {
                $cleared['detection'] = $this->chainDetector->clearDetectionCache($contractAddress);
            }

            if ($cacheType === 'switching' || $cacheType === 'all') {
                $cleared['switching'] = $this->smartSwitching->clearSwitchingCache($contractAddress);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'contract_address' => $contractAddress,
                    'cache_type' => $cacheType,
                    'cleared' => $cleared
                ],
                'meta' => [
                    'api_version' => '2.0',
                    'timestamp' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batch process multiple contracts
     */
    public function batchProcess(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'contracts' => 'required|array|min:1|max:10',
            'contracts.*' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/',
            'operation' => 'required|string|in:detect,verify,source',
            'preferred_network' => 'sometimes|string|in:ethereum,bsc,polygon,arbitrum,optimism,avalanche,fantom'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $contracts = array_map('strtolower', $data['contracts']);
        $operation = $data['operation'];
        $preferredNetwork = $data['preferred_network'] ?? null;

        try {
            $startTime = microtime(true);
            $results = [];

            foreach ($contracts as $contractAddress) {
                try {
                    $result = match ($operation) {
                        'detect' => $this->chainDetector->detectChain($contractAddress),
                        'verify' => $this->smartSwitching->getVerificationStatus($contractAddress),
                        'source' => $this->smartSwitching->getContractSource($contractAddress, $preferredNetwork)
                    };

                    $results[$contractAddress] = [
                        'success' => true,
                        'data' => $result
                    ];
                } catch (\Exception $e) {
                    $results[$contractAddress] = [
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }

            $totalTime = round((microtime(true) - $startTime) * 1000, 2);

            return response()->json([
                'success' => true,
                'data' => [
                    'operation' => $operation,
                    'total_contracts' => count($contracts),
                    'successful' => count(array_filter($results, fn($r) => $r['success'])),
                    'failed' => count(array_filter($results, fn($r) => !$r['success'])),
                    'results' => $results,
                    'total_time_ms' => $totalTime
                ],
                'meta' => [
                    'api_version' => '2.0',
                    'timestamp' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Batch processing failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}