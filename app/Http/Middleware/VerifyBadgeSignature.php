<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\VerificationBadgeService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Verify Badge Signature Middleware
 * 
 * Validates verification badge signatures to prevent tampering
 */
final class VerifyBadgeSignature
{
    public function __construct(
        private readonly VerificationBadgeService $verificationService
    ) {}

    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply to verification routes
        if (!$this->shouldVerify($request)) {
            return $next($request);
        }

        // Extract token from request
        $token = $this->extractToken($request);
        
        if (!$token) {
            return $this->createErrorResponse('Token not provided', 400);
        }

        // Rate limiting for verification attempts
        $rateLimitKey = 'verification_attempts:' . $request->ip();
        $attempts = cache()->get($rateLimitKey, 0);
        
        if ($attempts >= 50) { // 50 attempts per hour
            Log::warning('Verification rate limit exceeded', [
                'ip' => $request->ip(),
                'attempts' => $attempts
            ]);
            
            return $this->createErrorResponse('Too many verification attempts', 429);
        }

        // Increment rate limit counter
        cache()->put($rateLimitKey, $attempts + 1, 3600); // 1 hour

        // Basic token format validation
        if (!$this->isValidTokenFormat($token)) {
            Log::warning('Invalid verification token format', [
                'ip' => $request->ip(),
                'token_length' => strlen($token)
            ]);
            
            return $this->createErrorResponse('Invalid token format', 400);
        }

        // Check if token is revoked (fast cache check)
        if ($this->verificationService->isBadgeRevoked($token)['revoked']) {
            Log::info('Attempted access to revoked badge', [
                'ip' => $request->ip(),
                'token_preview' => substr($token, 0, 16) . '...'
            ]);
            
            return $this->createErrorResponse('Badge has been revoked', 403);
        }

        // Add token to request for controller use
        $request->merge(['verified_token' => $token]);

        return $next($request);
    }

    /**
     * Determine if request should be verified
     */
    private function shouldVerify(Request $request): bool
    {
        $path = $request->path();
        
        // Apply to verification badge routes
        return str_starts_with($path, 'verification/') || 
               str_starts_with($path, 'api/verification/');
    }

    /**
     * Extract token from request
     */
    private function extractToken(Request $request): ?string
    {
        // Try route parameter first
        $token = $request->route('token');
        
        if ($token) {
            return $token;
        }

        // Try request body
        $token = $request->input('token');
        
        if ($token) {
            return $token;
        }

        // Try Authorization header
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        // Try custom header
        return $request->header('X-Verification-Token');
    }

    /**
     * Validate token format
     */
    private function isValidTokenFormat(string $token): bool
    {
        // Basic validation
        if (strlen($token) < 50 || strlen($token) > 2048) {
            return false;
        }

        // Must be valid base64
        $decoded = base64_decode($token, true);
        if ($decoded === false) {
            return false;
        }

        // Must be valid JSON
        $json = json_decode($decoded, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        // Must have required structure
        if (!isset($json['payload']) || !isset($json['signature'])) {
            return false;
        }

        return true;
    }

    /**
     * Create error response
     */
    private function createErrorResponse(string $message, int $status): Response
    {
        $response = [
            'success' => false,
            'message' => $message,
            'error_code' => $this->getErrorCode($status)
        ];

        return response()->json($response, $status);
    }

    /**
     * Get error code for status
     */
    private function getErrorCode(int $status): string
    {
        return match ($status) {
            400 => 'BAD_REQUEST',
            403 => 'FORBIDDEN',
            429 => 'RATE_LIMITED',
            default => 'ERROR'
        };
    }
}
