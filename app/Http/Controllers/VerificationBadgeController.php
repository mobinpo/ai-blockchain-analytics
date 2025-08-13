<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\VerificationBadgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Verification Badge Controller
 * 
 * Handles verification badge generation, display, and validation
 */
final class VerificationBadgeController extends Controller
{
    public function __construct(
        private readonly VerificationBadgeService $verificationService
    ) {}

    /**
     * Generate a verification badge
     */
    public function generateBadge(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'entity_type' => 'required|string|in:contract,user,analysis',
            'entity_id' => 'required|string|max:255',
            'badge_type' => 'sometimes|string|max:100',
            'metadata' => 'sometimes|array',
            'expires_hours' => 'sometimes|integer|min:1|max:8760' // Max 1 year
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $entityType = $request->input('entity_type');
            $entityId = $request->input('entity_id');
            $badgeType = $request->input('badge_type', 'verified');
            $metadata = $request->input('metadata', []);
            $expiresHours = $request->input('expires_hours', 24);

            $expiresAt = now()->addHours($expiresHours);

            // Generate badge based on entity type
            $badgeData = match ($entityType) {
                'contract' => $this->verificationService->generateContractBadge(
                    $entityId,
                    $metadata['network'] ?? 'ethereum',
                    $metadata['analysis_results'] ?? [],
                    $badgeType
                ),
                'user' => $this->verificationService->generateUserBadge(
                    $entityId,
                    $metadata['credentials'] ?? [],
                    $badgeType
                ),
                'analysis' => $this->verificationService->generateAnalysisBadge(
                    $entityId,
                    $metadata,
                    $badgeType
                ),
                default => $this->verificationService->generateBadgeUrl(
                    $entityType,
                    $entityId,
                    $metadata,
                    $expiresAt,
                    $badgeType
                )
            };

            return response()->json([
                'success' => true,
                'message' => 'Verification badge generated successfully',
                'data' => $badgeData
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate verification badge', [
                'entity_type' => $request->input('entity_type'),
                'entity_id' => $request->input('entity_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate verification badge',
                'error' => app()->isProduction() ? 'Internal server error' : $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify a badge token
     */
    public function verifyBadge(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $token = $request->input('token');
            
            // Check if badge is revoked
            $revokedCheck = $this->verificationService->isBadgeRevoked($token);
            if ($revokedCheck['revoked']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Badge has been revoked',
                    'data' => [
                        'valid' => false,
                        'error' => 'Badge revoked',
                        'error_code' => 'REVOKED',
                        'revoked_at' => $revokedCheck['revoked_at'],
                        'reason' => $revokedCheck['reason']
                    ]
                ]);
            }

            // Verify the badge
            $verificationResult = $this->verificationService->verifyBadgeToken($token);

            $statusCode = $verificationResult['valid'] ? 200 : 400;

            return response()->json([
                'success' => $verificationResult['valid'],
                'message' => $verificationResult['valid'] ? 'Badge is valid' : 'Badge verification failed',
                'data' => $verificationResult
            ], $statusCode);

        } catch (\Exception $e) {
            Log::error('Badge verification error', [
                'token_preview' => substr($request->input('token', ''), 0, 16) . '...',
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Verification failed',
                'data' => [
                    'valid' => false,
                    'error' => 'System error during verification',
                    'error_code' => 'SYSTEM_ERROR'
                ]
            ], 500);
        }
    }

    /**
     * Display verification badge (web view)
     */
    public function showBadge(Request $request, string $token): InertiaResponse|Response
    {
        try {
            // Verify the badge
            $verificationResult = $this->verificationService->verifyBadgeToken($token);
            
            if (!$verificationResult['valid']) {
                return Inertia::render('Verification/Invalid', [
                    'error' => $verificationResult['error'] ?? 'Invalid badge',
                    'error_code' => $verificationResult['error_code'] ?? 'INVALID'
                ]);
            }

            return Inertia::render('Verification/Badge', [
                'badge' => $verificationResult,
                'token' => $token,
                'verification_url' => url('/verification/verify/' . urlencode($token))
            ]);

        } catch (\Exception $e) {
            Log::error('Error displaying verification badge', [
                'token_preview' => substr($token, 0, 16) . '...',
                'error' => $e->getMessage()
            ]);

            return Inertia::render('Verification/Invalid', [
                'error' => 'System error',
                'error_code' => 'SYSTEM_ERROR'
            ]);
        }
    }

    /**
     * Display verification results (web view)
     */
    public function showVerification(Request $request, string $token): InertiaResponse
    {
        try {
            // Check if badge is revoked
            $revokedCheck = $this->verificationService->isBadgeRevoked($token);
            if ($revokedCheck['revoked']) {
                return Inertia::render('Verification/Revoked', [
                    'revoked_at' => $revokedCheck['revoked_at'],
                    'reason' => $revokedCheck['reason']
                ]);
            }

            // Verify the badge
            $verificationResult = $this->verificationService->verifyBadgeToken($token);

            return Inertia::render('Verification/Result', [
                'result' => $verificationResult,
                'token' => $token,
                'badge_url' => url('/verification/badge/' . urlencode($token))
            ]);

        } catch (\Exception $e) {
            Log::error('Error showing verification result', [
                'token_preview' => substr($token, 0, 16) . '...',
                'error' => $e->getMessage()
            ]);

            return Inertia::render('Verification/Invalid', [
                'error' => 'System error',
                'error_code' => 'SYSTEM_ERROR'
            ]);
        }
    }

    /**
     * Revoke a verification badge
     */
    public function revokeBadge(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|max:2048',
            'reason' => 'sometimes|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $token = $request->input('token');
            $reason = $request->input('reason', 'No reason provided');

            $revoked = $this->verificationService->revokeBadge($token, $reason);

            if ($revoked) {
                return response()->json([
                    'success' => true,
                    'message' => 'Badge revoked successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to revoke badge'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Failed to revoke badge', [
                'token_preview' => substr($request->input('token', ''), 0, 16) . '...',
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to revoke badge'
            ], 500);
        }
    }

    /**
     * Get badge statistics
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $statistics = $this->verificationService->getBadgeStatistics();

            return response()->json([
                'success' => true,
                'data' => $statistics
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get badge statistics', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics'
            ], 500);
        }
    }

    /**
     * Generate badge for contract analysis
     */
    public function generateContractBadge(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'contract_address' => 'required|string|max:42',
            'network' => 'sometimes|string|max:50',
            'analysis_results' => 'sometimes|array',
            'badge_type' => 'sometimes|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $contractAddress = $request->input('contract_address');
            $network = $request->input('network', 'ethereum');
            $analysisResults = $request->input('analysis_results', []);
            $badgeType = $request->input('badge_type', 'security_verified');

            $badgeData = $this->verificationService->generateContractBadge(
                $contractAddress,
                $network,
                $analysisResults,
                $badgeType
            );

            return response()->json([
                'success' => true,
                'message' => 'Contract verification badge generated successfully',
                'data' => $badgeData
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate contract badge', [
                'contract_address' => $request->input('contract_address'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate contract badge',
                'error' => app()->isProduction() ? 'Internal server error' : $e->getMessage()
            ], 500);
        }
    }

    /**
     * Embed verification badge (for external sites)
     */
    public function embedBadge(Request $request, string $token): Response
    {
        try {
            // Set cache headers for embed
            $cacheKey = 'badge_embed:' . hash('sha256', $token);
            $cached = Cache::get($cacheKey);
            
            if ($cached) {
                return response($cached['content'])
                    ->header('Content-Type', 'image/svg+xml')
                    ->header('Cache-Control', 'public, max-age=300')
                    ->header('X-Content-Type-Options', 'nosniff');
            }

            // Verify the badge
            $verificationResult = $this->verificationService->verifyBadgeToken($token);
            
            $svg = $this->generateBadgeSvg($verificationResult);
            
            // Cache the SVG for 5 minutes
            Cache::put($cacheKey, ['content' => $svg], 300);

            return response($svg)
                ->header('Content-Type', 'image/svg+xml')
                ->header('Cache-Control', 'public, max-age=300')
                ->header('X-Content-Type-Options', 'nosniff');

        } catch (\Exception $e) {
            Log::error('Error generating badge embed', [
                'token_preview' => substr($token, 0, 16) . '...',
                'error' => $e->getMessage()
            ]);

            // Return error badge
            $errorSvg = $this->generateErrorBadgeSvg();
            return response($errorSvg)
                ->header('Content-Type', 'image/svg+xml')
                ->header('Cache-Control', 'no-cache');
        }
    }

    /**
     * Generate SVG badge
     */
    private function generateBadgeSvg(array $verificationResult): string
    {
        if (!$verificationResult['valid']) {
            return $this->generateErrorBadgeSvg($verificationResult['error'] ?? 'Invalid');
        }

        $badgeType = $verificationResult['badge_type'];
        $entityType = $verificationResult['entity_type'];
        
        // Determine badge color and text
        [$color, $text] = $this->getBadgeStyle($badgeType, $verificationResult);

        return $this->createSvgBadge($text, $color, $entityType);
    }

    /**
     * Get badge style based on type and verification level
     */
    private function getBadgeStyle(string $badgeType, array $result): array
    {
        $verificationLevel = $result['metadata']['verification_level'] ?? 'unknown';
        
        return match ($badgeType) {
            'security_verified' => match ($verificationLevel) {
                'high' => ['#10B981', 'Security Verified ✓'],
                'medium' => ['#F59E0B', 'Security Checked ⚠'],
                'low' => ['#EF4444', 'Security Risk ⚠'],
                default => ['#6B7280', 'Security Unknown']
            },
            'developer_verified' => match ($verificationLevel) {
                'high' => ['#3B82F6', 'Developer Verified ✓'],
                'medium' => ['#8B5CF6', 'Developer Checked ✓'],
                default => ['#6B7280', 'Developer Unknown']
            },
            'analysis_verified' => ['#059669', 'Analysis Verified ✓'],
            default => ['#374151', 'Verified ✓']
        };
    }

    /**
     * Create SVG badge
     */
    private function createSvgBadge(string $text, string $color, string $entityType): string
    {
        $width = strlen($text) * 8 + 20;
        $height = 20;
        
        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$height}" viewBox="0 0 {$width} {$height}">
    <defs>
        <linearGradient id="grad" x1="0%" y1="0%" x2="0%" y2="100%">
            <stop offset="0%" style="stop-color:{$color};stop-opacity:0.9" />
            <stop offset="100%" style="stop-color:{$color};stop-opacity:1" />
        </linearGradient>
    </defs>
    <rect width="{$width}" height="{$height}" rx="3" ry="3" fill="url(#grad)" />
    <text x="10" y="14" font-family="Verdana,sans-serif" font-size="11" fill="white">{$text}</text>
</svg>
SVG;
    }

    /**
     * Generate error badge SVG
     */
    private function generateErrorBadgeSvg(string $error = 'Invalid'): string
    {
        return $this->createSvgBadge("❌ {$error}", '#EF4444', 'error');
    }
}
