<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VerificationBadgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

final class VerificationBadgeController extends Controller
{
    public function __construct(
        private readonly VerificationBadgeService $verificationBadgeService
    ) {}

    /**
     * Generate a new verification badge
     */
    public function generate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'contract_address' => 'sometimes|string|regex:/^0x[a-fA-F0-9]{40}$/',
            'project_name' => 'sometimes|string|max:100',
            'verification_data' => 'sometimes|array',
            'verification_data.contract_verified' => 'sometimes|boolean',
            'verification_data.audit_passed' => 'sometimes|boolean',
            'verification_data.kyc_completed' => 'sometimes|boolean',
            'verification_data.team_verified' => 'sometimes|boolean',
            'verification_data.social_verified' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input parameters',
                'errors' => $validator->errors()
            ], 422);
        }

        // Rate limiting check
        $key = 'badge-generation:' . ($request->ip() ?? 'unknown');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many badge generation attempts. Please try again later.',
                'retry_after' => RateLimiter::availableIn($key)
            ], 429);
        }

        RateLimiter::hit($key, 60); // 1 minute window

        try {
            $userId = Auth::id() ?? $request->ip() ?? 'anonymous';
            $contractAddress = $request->input('contract_address');
            $projectName = $request->input('project_name');
            $verificationData = $request->input('verification_data', []);

            // Add basic verification data
            $verificationData['email_verified'] = Auth::check();
            $verificationData['ip_address'] = $request->ip();
            $verificationData['user_agent'] = $request->userAgent();
            $verificationData['timestamp'] = now()->toISOString();

            $badge = $this->verificationBadgeService->generateVerifiedBadge(
                $userId,
                $contractAddress,
                $projectName,
                $verificationData
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'badge_id' => $badge['badge_id'],
                    'signed_url' => $badge['signed_url'],
                    'verification_level' => $badge['level'],
                    'badge_type' => $badge['type'],
                    'expires_at' => $badge['expires_at'],
                    'qr_code_url' => config('app.url') . '/verification/qr-code/' . $badge['badge_id'],
                    'embed_code' => $this->generateEmbedCode($badge),
                    'verification_url' => config('app.url') . '/verification/verify/' . $badge['badge_id'] . '/' . $badge['signature']
                ],
                'message' => 'Verification badge generated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate verification badge',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify a badge using badge ID and signature
     */
    public function verify(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required_without_all:badge_id,signature|string',
            'badge_id' => 'required_without:token|string', 
            'signature' => 'required_without:token|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Token or badge ID with signature is required',
                'errors' => $validator->errors()
            ], 422);
        }

        // Rate limiting for verification attempts
        $key = 'badge-verification:' . $request->input('badge_id');
        if (RateLimiter::tooManyAttempts($key, 50)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many verification attempts for this badge',
                'retry_after' => RateLimiter::availableIn($key)
            ], 429);
        }

        RateLimiter::hit($key, 300); // 5 minute window

        try {
            $token = $request->input('token') ?? $request->input('badge_id');
            if (!$token) {
                throw new \Exception('Token or badge_id is required');
            }
            
            $result = $this->verificationBadgeService->verifyBadge($token);

            if (!$result['valid']) {
                throw new \Exception($result['error'] ?? 'Invalid badge');
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'verified' => true,
                    'badge_type' => $result['badge_type'] ?? 'unknown',
                    'entity_type' => $result['entity_type'] ?? 'unknown',
                    'entity_id' => $result['entity_id'] ?? 'unknown',
                    'metadata' => $result['metadata'] ?? [],
                    'issued_at' => $result['issued_at'],
                    'expires_at' => $result['expires_at'],
                    'verified_at' => $result['verification_time'],
                    'from_cache' => $result['from_cache'] ?? false
                ],
                'message' => 'Badge verified successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Badge verification failed',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Verify badge via signed URL
     */
    public function verifyUrl(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|url'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid URL provided',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->verificationBadgeService->verifySignedUrl($request->input('url'));

            return response()->json([
                'success' => true,
                'data' => [
                    'verified' => true,
                    'verification_method' => 'signed_url',
                    'badge_data' => $this->sanitizeVerificationData($result['badge_data']),
                    'verified_at' => $result['verification_timestamp']
                ],
                'message' => 'Signed URL verified successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'URL verification failed',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get verification levels and requirements
     */
    public function levels(): JsonResponse
    {
        $levels = $this->verificationBadgeService->getVerificationLevels();

        return response()->json([
            'success' => true,
            'data' => [
                'levels' => $levels,
                'requirements' => [
                    'basic' => ['Email verification'],
                    'bronze' => ['Email verification', 'Contract verification'],
                    'silver' => ['Bronze requirements', 'Team/Social verification'],
                    'gold' => ['Silver requirements', 'KYC completion', 'Security audit']
                ],
                'scoring' => [
                    'contract_verified' => 20,
                    'audit_passed' => 30,
                    'kyc_completed' => 25,
                    'team_verified' => 15,
                    'social_verified' => 10
                ]
            ]
        ]);
    }

    /**
     * Get badge statistics (public endpoint)
     */
    public function stats(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'badge_id' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $stats = $this->verificationBadgeService->getBadgeStatistics($request->input('badge_id'));

            return response()->json([
                'success' => true,
                'data' => [
                    'badge_id' => $stats['badge_id'],
                    'verification_count' => $stats['verification_count'],
                    'is_active' => $stats['is_active'],
                    'verification_level' => $stats['verification_level'],
                    'badge_type' => $stats['badge_type'],
                    'days_until_expiry' => $stats['days_until_expiry']
                    // Note: Sensitive timestamps are excluded from public stats
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve badge statistics',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Generate embed code for badge
     */
    public function embedCode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'badge_id' => 'required|string',
            'signature' => 'required|string',
            'format' => 'sometimes|string|in:html,iframe,widget'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Verify badge first
            $result = $this->verificationBadgeService->verifyBadge(
                $request->input('badge_id'),
                $request->input('signature')
            );

            $format = $request->input('format', 'html');
            $embedCode = $this->generateEmbedCode($result['badge_data'], $format);

            return response()->json([
                'success' => true,
                'data' => [
                    'embed_code' => $embedCode,
                    'format' => $format,
                    'badge_id' => $request->input('badge_id')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate embed code',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Generate embed code for a badge
     */
    private function generateEmbedCode(array $badgeData, string $format = 'html'): string
    {
        $badgeUrl = config('app.url') . '/verification/badge-widget?' . http_build_query([
            'badge_id' => $badgeData['badge_id'],
            'signature' => $badgeData['signature']
        ]);

        $verifyUrl = $badgeData['signed_url'];
        $level = $badgeData['level'];
        $type = $badgeData['type'];

        return match ($format) {
            'iframe' => "<iframe src=\"{$badgeUrl}\" width=\"200\" height=\"80\" frameborder=\"0\" title=\"Verification Badge\"></iframe>",
            
            'widget' => "<div class=\"verification-badge-widget\" data-badge-id=\"{$badgeData['badge_id']}\"></div>
<script src=\"" . asset('js/verification-badge.js') . "\"></script>",
            
            default => "<div class=\"verification-badge verification-{$level}\">
    <a href=\"{$verifyUrl}\" target=\"_blank\" rel=\"noopener\">
        <img src=\"" . asset("images/badges/{$level}.svg") . "\" alt=\"{$type} - {$level}\" width=\"100\" height=\"100\">
        <span class=\"badge-text\">Verified</span>
    </a>
</div>"
        };
    }

    /**
     * Sanitize verification data for public display
     */
    private function sanitizeVerificationData(array $verificationData): array
    {
        // Remove sensitive information from public response
        $publicData = $verificationData;
        unset(
            $publicData['ip_address'],
            $publicData['user_agent'],
            $publicData['internal_notes']
        );

        return $publicData;
    }
}
