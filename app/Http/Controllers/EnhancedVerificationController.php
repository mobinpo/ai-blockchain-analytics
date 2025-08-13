<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\EnhancedVerificationBadgeService;
use App\Models\VerificationBadge;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Exception;

/**
 * Enhanced Verification Controller
 * 
 * Handles secure verification badge operations with SHA-256 + HMAC protection
 */
final class EnhancedVerificationController extends Controller
{
    public function __construct(
        private readonly EnhancedVerificationBadgeService $verificationService
    ) {}

    /**
     * Generate secure verification URL
     */
    public function generateVerificationUrl(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'contract_address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/',
                'metadata' => 'sometimes|array',
                'metadata.project_name' => 'sometimes|string|max:100',
                'metadata.website' => 'sometimes|url|max:200',
                'metadata.description' => 'sometimes|string|max:500',
                'metadata.category' => 'sometimes|string|max:50',
                'metadata.tags' => 'sometimes|array|max:10',
                'metadata.tags.*' => 'string|max:30',
                'options' => 'sometimes|array',
                'options.lifetime' => 'sometimes|integer|min:300|max:7200' // 5 minutes to 2 hours
            ]);

            $contractAddress = $validated['contract_address'];
            $metadata = $validated['metadata'] ?? [];
            $options = $validated['options'] ?? [];
            $userId = (string) auth()->id();

            // Check if contract is already verified
            $existingVerification = VerificationBadge::findActiveForContract($contractAddress);
            if ($existingVerification) {
                return response()->json([
                    'success' => false,
                    'error' => 'Contract is already verified',
                    'existing_verification' => $existingVerification->getStatusArray()
                ], 409);
            }

            $result = $this->verificationService->generateSecureVerificationUrl(
                $contractAddress,
                $userId,
                $metadata,
                $options
            );

            return response()->json([
                'success' => true,
                'message' => 'Secure verification URL generated successfully',
                'data' => $result
            ]);

        } catch (Exception $e) {
            Log::error('Verification URL generation failed', [
                'user_id' => auth()->id(),
                'contract_address' => $request->input('contract_address'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Verify signed URL and create badge
     */
    public function verifySignedUrl(Request $request, string $token): \Inertia\Response|Response
    {
        try {
            $result = $this->verificationService->verifySecureUrl($token);

            if ($result['success']) {
                return Inertia::render('Verification/Success', [
                    'verification' => $result['verification'],
                    'badge_data' => $result['badge_data'],
                    'security_features' => $result['security_features']
                ]);
            } else {
                return Inertia::render('Verification/Error', [
                    'error' => $result['message'] ?? 'Verification failed'
                ]);
            }

        } catch (Exception $e) {
            Log::warning('Verification attempt failed', [
                'token_preview' => substr($token, 0, 20) . '...',
                'error' => $e->getMessage(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return Inertia::render('Verification/Error', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get verification status for a contract
     */
    public function getVerificationStatus(Request $request, string $contractAddress): JsonResponse
    {
        try {
            $status = $this->verificationService->getVerificationStatus($contractAddress);

            return response()->json([
                'success' => true,
                'data' => $status
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get badge HTML for embedding
     */
    public function getBadgeHtml(Request $request, string $contractAddress): Response
    {
        try {
            $verification = VerificationBadge::findActiveForContract($contractAddress);

            if (!$verification) {
                return response('<!-- No verification badge available -->', 200)
                    ->header('Content-Type', 'text/html');
            }

            $badgeHtml = $this->verificationService->generateEnhancedBadgeHtml($verification);

            return response($badgeHtml, 200)
                ->header('Content-Type', 'text/html')
                ->header('Cache-Control', 'public, max-age=3600');

        } catch (Exception $e) {
            return response('<!-- Badge generation error -->', 500)
                ->header('Content-Type', 'text/html');
        }
    }

    /**
     * List user's verified contracts
     */
    public function getUserVerifications(Request $request): JsonResponse
    {
        try {
            $userId = (string) auth()->id();
            
            $verifications = VerificationBadge::where('user_id', $userId)
                ->active()
                ->verified()
                ->orderBy('verified_at', 'desc')
                ->get();

            $verificationData = $verifications->map(function ($verification) {
                return array_merge($verification->getStatusArray(), [
                    'badge_data' => $this->verificationService->generateBadgeData($verification),
                    'verification_age' => $verification->verification_age
                ]);
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'verifications' => $verificationData,
                    'total_count' => $verifications->count(),
                    'active_count' => $verifications->where('is_active', true)->count()
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Revoke verification badge
     */
    public function revokeVerification(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'contract_address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/',
                'reason' => 'sometimes|string|max:200'
            ]);

            $contractAddress = $validated['contract_address'];
            $reason = $validated['reason'] ?? 'Revoked by user';
            $userId = (string) auth()->id();

            $verification = VerificationBadge::where('contract_address', strtolower($contractAddress))
                ->where('user_id', $userId)
                ->active()
                ->first();

            if (!$verification) {
                return response()->json([
                    'success' => false,
                    'error' => 'No active verification found for this contract'
                ], 404);
            }

            $verification->revoke($reason);

            Log::info('Verification badge revoked', [
                'contract_address' => $contractAddress,
                'user_id' => $userId,
                'reason' => $reason
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Verification badge revoked successfully'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Batch verify multiple contracts
     */
    public function batchGenerateUrls(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'contracts' => 'required|array|min:1|max:10',
                'contracts.*.contract_address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/',
                'contracts.*.metadata' => 'sometimes|array',
                'options' => 'sometimes|array'
            ]);

            $contracts = $validated['contracts'];
            $options = $validated['options'] ?? [];
            $userId = (string) auth()->id();
            $results = [];
            $errors = [];

            foreach ($contracts as $index => $contractData) {
                try {
                    $contractAddress = $contractData['contract_address'];
                    $metadata = $contractData['metadata'] ?? [];

                    // Check if already verified
                    $existing = VerificationBadge::findActiveForContract($contractAddress);
                    if ($existing) {
                        $errors[] = [
                            'index' => $index,
                            'contract_address' => $contractAddress,
                            'error' => 'Already verified'
                        ];
                        continue;
                    }

                    $result = $this->verificationService->generateSecureVerificationUrl(
                        $contractAddress,
                        $userId,
                        $metadata,
                        $options
                    );

                    $results[] = [
                        'index' => $index,
                        'contract_address' => $contractAddress,
                        'result' => $result
                    ];

                } catch (Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'contract_address' => $contractData['contract_address'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'successful' => $results,
                    'errors' => $errors,
                    'total_processed' => count($contracts),
                    'successful_count' => count($results),
                    'error_count' => count($errors)
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get verification statistics
     */
    public function getVerificationStats(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();

            $stats = [
                'total_verifications' => VerificationBadge::where('user_id', $userId)->count(),
                'active_verifications' => VerificationBadge::where('user_id', $userId)->active()->count(),
                'revoked_verifications' => VerificationBadge::where('user_id', $userId)->whereNotNull('revoked_at')->count(),
                'expired_verifications' => VerificationBadge::where('user_id', $userId)
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<', now())
                    ->count(),
                'recent_verifications' => VerificationBadge::where('user_id', $userId)
                    ->where('verified_at', '>=', now()->subDays(30))
                    ->count(),
                'verification_methods' => VerificationBadge::where('user_id', $userId)
                    ->selectRaw('verification_method, COUNT(*) as count')
                    ->groupBy('verification_method')
                    ->pluck('count', 'verification_method')
                    ->toArray()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Demo page for verification badge system
     */
    public function demo(): \Inertia\Response
    {
        $sampleContracts = [
            [
                'address' => '0x1234567890123456789012345678901234567890',
                'name' => 'Sample DeFi Protocol',
                'description' => 'A decentralized finance protocol for lending and borrowing',
                'website' => 'https://example-defi.com',
                'category' => 'DeFi',
                'verified' => false
            ],
            [
                'address' => '0x0987654321098765432109876543210987654321',
                'name' => 'NFT Marketplace',
                'description' => 'A marketplace for trading non-fungible tokens',
                'website' => 'https://example-nft.com',
                'category' => 'NFT',
                'verified' => true
            ]
        ];

        return Inertia::render('Demo/VerificationBadge', [
            'sample_contracts' => $sampleContracts,
            'security_features' => [
                'signature_algorithm' => 'SHA-256 + HMAC',
                'multi_layer_protection' => true,
                'anti_spoofing' => true,
                'replay_protection' => true,
                'ip_binding' => true,
                'user_agent_binding' => true
            ]
        ]);
    }

    /**
     * Management page for user's verification badges
     */
    public function manage(): \Inertia\Response|Response
    {
        try {
            $userId = (string) auth()->id();
            
            $verifications = VerificationBadge::where('user_id', $userId)
                ->orderBy('verified_at', 'desc')
                ->get()
                ->map(fn($v) => $v->getStatusArray());

            $stats = [
                'total' => $verifications->count(),
                'active' => $verifications->where('is_active', true)->count(),
                'revoked' => $verifications->where('is_revoked', true)->count(),
                'expired' => $verifications->where('is_expired', true)->count()
            ];

            return Inertia::render('Verification/Manage', [
                'verifications' => $verifications,
                'stats' => $stats
            ]);

        } catch (Exception $e) {
            return Inertia::render('Verification/Error', [
                'error' => 'Failed to load verification data: ' . $e->getMessage()
            ]);
        }
    }
}