<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SecureVerificationBadgeService;
use App\Models\VerificationBadge;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;

/**
 * Secure Verification Badge API Controller
 * 
 * Provides REST API endpoints for secure "Get Verified" badge system
 * with SHA-256 + HMAC authentication and anti-spoofing protection
 */
final class SecureVerificationController extends Controller
{
    public function __construct(
        private readonly SecureVerificationBadgeService $badgeService
    ) {
    }

    /**
     * Generate a secure verification badge with cryptographic signatures
     * 
     * @route POST /api/verification/generate-secure-badge
     */
    public function generateSecureBadge(Request $request): JsonResponse
    {
        // Rate limiting
        $key = 'generate-badge:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return response()->json([
                'success' => false,
                'error' => 'Too many badge generation attempts. Please try again later.',
                'retry_after' => RateLimiter::availableIn($key),
            ], 429);
        }

        $validator = Validator::make($request->all(), [
            'contract_address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/',
            'user_id' => 'required|string|max:255',
            'metadata' => 'nullable|array',
            'metadata.project_name' => 'nullable|string|max:100',
            'metadata.website' => 'nullable|url|max:255',
            'metadata.description' => 'nullable|string|max:500',
            'metadata.verification_level' => 'nullable|in:basic,standard,premium,enterprise',
            'metadata.tags' => 'nullable|array|max:10',
            'metadata.tags.*' => 'string|max:50',
            'options' => 'nullable|array',
            'options.custom_expiry_hours' => 'nullable|integer|min:1|max:168', // Max 1 week
            'options.require_ip_binding' => 'nullable|boolean',
            'options.enable_embed' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            RateLimiter::hit($key);
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        try {
            $contractAddress = $request->input('contract_address');
            $userId = $request->input('user_id');
            $metadata = $request->input('metadata', []);
            $options = $request->input('options', []);

            // Check if badge already exists for this contract
            $existingBadge = VerificationBadge::where('contract_address', strtolower($contractAddress))
                ->whereNull('revoked_at')
                ->where(function ($query) {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->first();

            if ($existingBadge) {
                return response()->json([
                    'success' => false,
                    'error' => 'Active verification badge already exists for this contract',
                    'existing_badge' => [
                        'id' => $existingBadge->id,
                        'verified_at' => $existingBadge->verified_at,
                        'expires_at' => $existingBadge->expires_at,
                    ],
                ], 409);
            }

            // Generate secure badge
            $result = $this->badgeService->generateSecureVerificationBadge(
                $contractAddress,
                $userId,
                $metadata,
                $options
            );

            RateLimiter::clear($key); // Clear rate limit on success

            Log::info('Secure verification badge generated via API', [
                'contract_address' => $contractAddress,
                'user_id' => $userId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json($result, 201);

        } catch (\Exception $e) {
            RateLimiter::hit($key);
            
            Log::error('Secure badge generation failed', [
                'error' => $e->getMessage(),
                'contract_address' => $request->input('contract_address'),
                'user_id' => $request->input('user_id'),
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Badge generation failed',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Verify a secure badge token with comprehensive validation
     * 
     * @route POST /api/verification/verify-secure-badge
     */
    public function verifySecureBadge(Request $request): JsonResponse
    {
        // Rate limiting for verification attempts
        $key = 'verify-badge:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 50)) {
            return response()->json([
                'success' => false,
                'error' => 'Too many verification attempts. Please try again later.',
                'retry_after' => RateLimiter::availableIn($key),
            ], 429);
        }

        $validator = Validator::make($request->all(), [
            'token' => 'required|string|min:50',
            'include_metadata' => 'nullable|boolean',
            'format' => 'nullable|in:json,minimal,detailed',
        ]);

        if ($validator->fails()) {
            RateLimiter::hit($key);
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        try {
            $token = $request->input('token');
            $format = $request->input('format', 'json');
            $includeMetadata = $request->boolean('include_metadata', true);

            // Verification context
            $context = [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toISOString(),
            ];

            $result = $this->badgeService->verifySecureBadge($token, $context);

            RateLimiter::hit($key, 1); // Count attempt but don't block on success

            // Format response based on requested format
            $response = match ($format) {
                'minimal' => $this->formatMinimalResponse($result),
                'detailed' => $this->formatDetailedResponse($result, $includeMetadata),
                default => $this->formatStandardResponse($result, $includeMetadata)
            };

            $statusCode = $result['success'] ? 200 : 400;

            return response()->json($response, $statusCode);

        } catch (\Exception $e) {
            RateLimiter::hit($key);
            
            Log::error('Secure badge verification failed', [
                'error' => $e->getMessage(),
                'token_preview' => substr($request->input('token', ''), 0, 16) . '...',
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Verification failed',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get badge display HTML/JSON for embedding
     * 
     * @route GET /api/verification/badge-display/{token}
     */
    public function getBadgeDisplay(Request $request, string $token): JsonResponse
    {
        $validator = Validator::make(array_merge($request->all(), ['token' => $token]), [
            'token' => 'required|string|min:50',
            'format' => 'nullable|in:html,json,both',
            'theme' => 'nullable|in:light,dark,auto,minimal,detailed',
            'size' => 'nullable|in:small,medium,large,xl',
            'show_details' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid parameters',
                'details' => $validator->errors(),
            ], 422);
        }

        try {
            $options = [
                'format' => $request->input('format', 'both'),
                'theme' => $request->input('theme', 'light'),
                'size' => $request->input('size', 'medium'),
                'show_details' => $request->boolean('show_details', true),
            ];

            $display = $this->badgeService->generateBadgeDisplay($token, $options);

            return response()->json([
                'success' => $display['valid'],
                'data' => $display,
                'cache_headers' => [
                    'Cache-Control' => 'public, max-age=300', // 5 minutes
                    'ETag' => hash('sha256', json_encode($display)),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Badge display generation failed', [
                'error' => $e->getMessage(),
                'token_preview' => substr($token, 0, 16) . '...',
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Display generation failed',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Revoke a verification badge
     * 
     * @route POST /api/verification/revoke-badge
     */
    public function revokeBadge(Request $request): JsonResponse
    {
        // Rate limiting
        $key = 'revoke-badge:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'success' => false,
                'error' => 'Too many revocation attempts. Please try again later.',
                'retry_after' => RateLimiter::availableIn($key),
            ], 429);
        }

        $validator = Validator::make($request->all(), [
            'contract_address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/',
            'reason' => 'nullable|string|max:255',
            'user_id' => 'required|string|max:255', // For authorization
        ]);

        if ($validator->fails()) {
            RateLimiter::hit($key);
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        try {
            $contractAddress = $request->input('contract_address');
            $reason = $request->input('reason', 'Manual revocation via API');
            $userId = $request->input('user_id');

            // Verify the user has permission to revoke this badge
            $badge = VerificationBadge::where('contract_address', strtolower($contractAddress))
                ->where('user_id', $userId)
                ->whereNull('revoked_at')
                ->first();

            if (!$badge) {
                return response()->json([
                    'success' => false,
                    'error' => 'No revokable badge found for this contract and user',
                ], 404);
            }

            $result = $this->badgeService->revokeBadge($contractAddress, $reason);

            RateLimiter::clear($key); // Clear rate limit on success

            Log::info('Verification badge revoked via API', [
                'contract_address' => $contractAddress,
                'user_id' => $userId,
                'reason' => $reason,
                'ip_address' => $request->ip(),
            ]);

            return response()->json($result);

        } catch (\Exception $e) {
            RateLimiter::hit($key);
            
            Log::error('Badge revocation failed', [
                'error' => $e->getMessage(),
                'contract_address' => $request->input('contract_address'),
                'user_id' => $request->input('user_id'),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Revocation failed',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get verification statistics and analytics
     * 
     * @route GET /api/verification/stats
     */
    public function getVerificationStats(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'period' => 'nullable|in:hour,day,week,month,year',
            'include_details' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid parameters',
                'details' => $validator->errors(),
            ], 422);
        }

        try {
            $period = $request->input('period', 'day');
            $includeDetails = $request->boolean('include_details', false);

            $stats = $this->generateVerificationStats($period, $includeDetails);

            return response()->json([
                'success' => true,
                'data' => $stats,
                'meta' => [
                    'period' => $period,
                    'generated_at' => now()->toISOString(),
                    'cache_expires_at' => now()->addMinutes(15)->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Verification stats generation failed', [
                'error' => $e->getMessage(),
                'period' => $request->input('period'),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Stats generation failed',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get supported verification levels and their requirements
     * 
     * @route GET /api/verification/levels
     */
    public function getVerificationLevels(): JsonResponse
    {
        $levels = [
            'basic' => [
                'name' => 'Basic Verification',
                'description' => 'Standard contract verification with basic security',
                'features' => ['Contract ownership verification', 'Basic metadata', '24-hour validity'],
                'security_level' => 'standard',
                'badge_color' => 'blue',
            ],
            'standard' => [
                'name' => 'Standard Verification',
                'description' => 'Enhanced verification with additional security measures',
                'features' => ['All basic features', 'Enhanced security', 'Extended validity', 'Custom metadata'],
                'security_level' => 'enhanced',
                'badge_color' => 'green',
            ],
            'premium' => [
                'name' => 'Premium Verification',
                'description' => 'Premium verification with maximum security and features',
                'features' => ['All standard features', 'Priority support', 'Advanced analytics', 'Custom branding'],
                'security_level' => 'premium',
                'badge_color' => 'gold',
            ],
            'enterprise' => [
                'name' => 'Enterprise Verification',
                'description' => 'Enterprise-grade verification with white-label options',
                'features' => ['All premium features', 'White-label badges', 'API access', 'SLA guarantees'],
                'security_level' => 'military_grade',
                'badge_color' => 'platinum',
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'levels' => $levels,
                'default_level' => 'standard',
                'security_info' => [
                    'signature_algorithm' => 'SHA-256 + HMAC',
                    'anti_spoofing' => true,
                    'replay_protection' => true,
                    'rate_limiting' => true,
                ],
            ],
        ]);
    }

    // Private helper methods

    private function formatMinimalResponse(array $result): array
    {
        return [
            'verified' => $result['success'],
            'valid' => $result['success'],
            'code' => $result['code'] ?? 'UNKNOWN',
        ];
    }

    private function formatStandardResponse(array $result, bool $includeMetadata): array
    {
        $response = [
            'success' => $result['success'],
            'verified' => $result['success'],
            'message' => $result['message'],
            'code' => $result['code'],
        ];

        if ($result['success'] && isset($result['data']['payload'])) {
            $payload = $result['data']['payload'];
            $response['verification'] = [
                'contract_address' => $payload['contract_address'],
                'verified_at' => $payload['issued_at'],
                'expires_at' => $payload['expires_at'],
                'security_level' => 'military_grade',
            ];

            if ($includeMetadata && isset($payload['metadata'])) {
                $response['verification']['metadata'] = $payload['metadata'];
            }
        }

        return $response;
    }

    private function formatDetailedResponse(array $result, bool $includeMetadata): array
    {
        $response = $this->formatStandardResponse($result, $includeMetadata);
        
        if ($result['success']) {
            $response['security_info'] = [
                'signature_algorithm' => 'SHA-256 + HMAC',
                'signature_version' => 'v4.0',
                'security_level' => 'military_grade',
                'anti_spoofing_enabled' => true,
                'replay_protection_enabled' => true,
                'processing_time_ms' => $result['data']['processing_time_ms'] ?? null,
            ];
        }

        return $response;
    }

    private function generateVerificationStats(string $period, bool $includeDetails): array
    {
        $startDate = match ($period) {
            'hour' => now()->subHour(),
            'day' => now()->subDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'year' => now()->subYear(),
            default => now()->subDay()
        };

        $baseQuery = VerificationBadge::where('created_at', '>=', $startDate);

        $stats = [
            'period' => $period,
            'total_badges' => $baseQuery->count(),
            'active_badges' => $baseQuery->whereNull('revoked_at')
                ->where(function ($query) {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })->count(),
            'revoked_badges' => $baseQuery->whereNotNull('revoked_at')->count(),
            'expired_badges' => $baseQuery->where('expires_at', '<=', now())->count(),
        ];

        if ($includeDetails) {
            $stats['verification_methods'] = $baseQuery->select('verification_method')
                ->groupBy('verification_method')
                ->selectRaw('verification_method, count(*) as count')
                ->pluck('count', 'verification_method')
                ->toArray();

            $stats['verification_levels'] = $baseQuery->whereJsonContains('metadata->verification_level', ['basic', 'standard', 'premium', 'enterprise'])
                ->selectRaw('JSON_EXTRACT(metadata, "$.verification_level") as level, count(*) as count')
                ->groupBy('level')
                ->pluck('count', 'level')
                ->toArray();
        }

        return $stats;
    }
}
