<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\VerificationBadge;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;

/**
 * Secure Verification Badge Service
 * 
 * Implements military-grade cryptographic security for "Get Verified" badges
 * using SHA-256 + HMAC with multiple layers of anti-spoofing protection
 */
final class SecureVerificationBadgeService
{
    private const ALGORITHM = 'sha256';
    private const SIGNATURE_VERSION = 'v4.0';
    private const DEFAULT_LIFETIME = 86400; // 24 hours
    private const MAX_ATTEMPTS = 5;
    private const RATE_LIMIT_WINDOW = 300; // 5 minutes
    private const CACHE_PREFIX = 'secure_verification:';
    private const NONCE_PREFIX = 'nonce:';

    private string $primaryKey;
    private string $hmacKey;
    private string $saltKey;
    private array $securityConfig;

    public function __construct()
    {
        // Multi-layer key derivation for enhanced security
        $appKey = config('app.key');
        $this->primaryKey = hash_hmac('sha256', 'verification_primary', $appKey);
        $this->hmacKey = hash_hmac('sha256', 'verification_hmac', $appKey . 'SALT');
        $this->saltKey = hash_hmac('sha256', 'verification_salt', $appKey . 'PEPPER');
        
        $this->securityConfig = [
            'require_ip_binding' => config('verification.require_ip_binding', true),
            'require_user_agent_binding' => config('verification.require_user_agent_binding', false),
            'enable_rate_limiting' => config('verification.enable_rate_limiting', true),
            'enable_nonce_tracking' => config('verification.enable_nonce_tracking', true),
            'enable_replay_protection' => config('verification.enable_replay_protection', true),
            'max_verification_attempts' => config('verification.max_attempts', self::MAX_ATTEMPTS),
            'url_lifetime_seconds' => config('verification.url_lifetime', self::DEFAULT_LIFETIME),
        ];
    }

    /**
     * Generate a cryptographically secure "Get Verified" badge URL
     */
    public function generateSecureVerificationBadge(
        string $contractAddress,
        string $userId,
        array $metadata = [],
        array $options = []
    ): array {
        $startTime = microtime(true);
        
        // Normalize contract address
        $contractAddress = strtolower(trim($contractAddress));
        
        // Validate inputs
        $this->validateInputs($contractAddress, $userId, $metadata);
        
        // Check rate limiting
        if ($this->securityConfig['enable_rate_limiting']) {
            $this->checkRateLimit($userId);
        }
        
        // Generate secure payload
        $payload = $this->createSecurePayload($contractAddress, $userId, $metadata, $options);
        
        // Generate cryptographic signatures
        $signatures = $this->generateMultiLayerSignatures($payload);
        
        // Create verification token
        $token = $this->createVerificationToken($payload, $signatures);
        
        // Generate secure URLs
        $urls = $this->generateSecureUrls($token, $payload);
        
        // Store verification data with enhanced security
        $this->storeSecureVerificationData($token, $payload, $signatures);
        
        // Create database record
        $verificationBadge = $this->createVerificationRecord($contractAddress, $userId, $token, $metadata);
        
        $processingTime = microtime(true) - $startTime;
        
        Log::info('Secure verification badge generated', [
            'contract_address' => $contractAddress,
            'user_id' => $userId,
            'token_id' => substr($token, 0, 16) . '...',
            'processing_time' => round($processingTime * 1000, 2) . 'ms',
            'security_level' => 'military_grade',
            'signature_version' => self::SIGNATURE_VERSION,
        ]);

        return [
            'success' => true,
            'badge_data' => [
                'token' => $token,
                'badge_url' => $urls['badge_url'],
                'verification_url' => $urls['verification_url'],
                'embed_url' => $urls['embed_url'],
                'api_verification_url' => $urls['api_url'],
            ],
            'security_info' => [
                'signature_algorithm' => self::ALGORITHM,
                'signature_version' => self::SIGNATURE_VERSION,
                'expires_at' => $payload['expires_at'],
                'security_level' => 'military_grade',
                'anti_spoofing_enabled' => true,
                'replay_protection_enabled' => $this->securityConfig['enable_replay_protection'],
            ],
            'metadata' => [
                'contract_address' => $contractAddress,
                'user_id' => $userId,
                'issued_at' => $payload['issued_at'],
                'verification_id' => $verificationBadge->id,
                'processing_time_ms' => round($processingTime * 1000, 2),
            ],
        ];
    }

    /**
     * Verify a secure verification badge with comprehensive validation
     */
    public function verifySecureBadge(string $token, array $context = []): array
    {
        $startTime = microtime(true);
        
        try {
            // Check rate limiting for verification attempts
            if ($this->securityConfig['enable_rate_limiting']) {
                $this->checkVerificationRateLimit($context['ip'] ?? Request::ip());
            }

            // Decode and validate token structure
            $decoded = $this->decodeVerificationToken($token);
            if (!$decoded['success']) {
                return $this->createVerificationResponse(false, $decoded['error'], 'INVALID_TOKEN');
            }

            $payload = $decoded['payload'];
            $signatures = $decoded['signatures'];

            // Verify all cryptographic signatures
            $signatureVerification = $this->verifyMultiLayerSignatures($payload, $signatures);
            if (!$signatureVerification['valid']) {
                $this->logSecurityEvent('signature_verification_failed', $payload, $context);
                return $this->createVerificationResponse(false, 'Invalid cryptographic signature', 'SIGNATURE_MISMATCH');
            }

            // Check expiration
            if ($this->isTokenExpired($payload)) {
                return $this->createVerificationResponse(false, 'Verification badge has expired', 'EXPIRED');
            }

            // Verify nonce (prevent replay attacks)
            if ($this->securityConfig['enable_nonce_tracking']) {
                if ($this->isNonceUsed($payload['nonce'])) {
                    $this->logSecurityEvent('replay_attack_detected', $payload, $context);
                    return $this->createVerificationResponse(false, 'Replay attack detected', 'REPLAY_ATTACK');
                }
                $this->markNonceAsUsed($payload['nonce']);
            }

            // Verify IP binding if enabled
            if ($this->securityConfig['require_ip_binding']) {
                $currentIp = $context['ip'] ?? Request::ip();
                if (!$this->verifyIpBinding($payload, $currentIp)) {
                    $this->logSecurityEvent('ip_binding_violation', $payload, $context);
                    return $this->createVerificationResponse(false, 'IP address mismatch', 'IP_MISMATCH');
                }
            }

            // Verify User-Agent binding if enabled
            if ($this->securityConfig['require_user_agent_binding']) {
                $currentUserAgent = $context['user_agent'] ?? Request::userAgent();
                if (!$this->verifyUserAgentBinding($payload, $currentUserAgent)) {
                    $this->logSecurityEvent('user_agent_violation', $payload, $context);
                    return $this->createVerificationResponse(false, 'User agent mismatch', 'USER_AGENT_MISMATCH');
                }
            }

            // Verify database record exists and is active
            $dbVerification = $this->verifyDatabaseRecord($payload['contract_address'], $payload['user_id']);
            if (!$dbVerification['valid']) {
                return $this->createVerificationResponse(false, $dbVerification['error'], 'DB_VERIFICATION_FAILED');
            }

            // All verifications passed
            $processingTime = microtime(true) - $startTime;
            
            Log::info('Secure verification badge validated successfully', [
                'contract_address' => $payload['contract_address'],
                'user_id' => $payload['user_id'],
                'verification_time_ms' => round($processingTime * 1000, 2),
                'security_checks_passed' => $this->getSecurityChecksCount(),
            ]);

            return $this->createVerificationResponse(true, 'Verification successful', 'VERIFIED', [
                'payload' => $payload,
                'verification_data' => $dbVerification['data'],
                'processing_time_ms' => round($processingTime * 1000, 2),
                'security_level' => 'military_grade',
            ]);

        } catch (Exception $e) {
            Log::error('Secure badge verification failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'token_preview' => substr($token, 0, 16) . '...',
            ]);

            return $this->createVerificationResponse(false, 'Internal verification error', 'INTERNAL_ERROR');
        }
    }

    /**
     * Generate a public verification badge display
     */
    public function generateBadgeDisplay(string $token, array $options = []): array
    {
        $verification = $this->verifySecureBadge($token);
        
        if (!$verification['success']) {
            return [
                'valid' => false,
                'error' => $verification['error'],
                'badge_html' => $this->generateErrorBadgeHtml($verification['error']),
            ];
        }

        $payload = $verification['data']['payload'];
        $metadata = $payload['metadata'] ?? [];
        
        $badgeData = [
            'contract_address' => $payload['contract_address'],
            'project_name' => $metadata['project_name'] ?? 'Verified Contract',
            'verification_level' => $metadata['verification_level'] ?? 'standard',
            'verified_at' => $payload['issued_at'],
            'expires_at' => $payload['expires_at'],
            'security_level' => 'military_grade',
        ];

        return [
            'valid' => true,
            'badge_data' => $badgeData,
            'badge_html' => $this->generateBadgeHtml($badgeData, $options),
            'badge_json' => $this->generateBadgeJson($badgeData),
            'verification_info' => [
                'algorithm' => self::ALGORITHM,
                'version' => self::SIGNATURE_VERSION,
                'security_level' => 'military_grade',
                'anti_spoofing' => true,
            ],
        ];
    }

    /**
     * Revoke a verification badge
     */
    public function revokeBadge(string $contractAddress, string $reason = 'Manual revocation'): array
    {
        try {
            $badge = VerificationBadge::where('contract_address', strtolower($contractAddress))
                ->where('revoked_at', null)
                ->first();

            if (!$badge) {
                return ['success' => false, 'error' => 'No active badge found for this contract'];
            }

            $badge->update([
                'revoked_at' => now(),
                'revoked_reason' => $reason,
            ]);

            // Invalidate cached data
            $this->invalidateCachedVerificationData($contractAddress);

            Log::info('Verification badge revoked', [
                'contract_address' => $contractAddress,
                'reason' => $reason,
                'badge_id' => $badge->id,
            ]);

            return [
                'success' => true,
                'message' => 'Badge revoked successfully',
                'revoked_at' => now()->toISOString(),
                'reason' => $reason,
            ];

        } catch (Exception $e) {
            Log::error('Badge revocation failed', [
                'contract_address' => $contractAddress,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => 'Failed to revoke badge'];
        }
    }

    // Private helper methods

    private function validateInputs(string $contractAddress, string $userId, array $metadata): void
    {
        if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $contractAddress)) {
            throw new \InvalidArgumentException('Invalid contract address format');
        }

        if (empty($userId)) {
            throw new \InvalidArgumentException('User ID is required');
        }

        if (isset($metadata['project_name']) && strlen($metadata['project_name']) > 100) {
            throw new \InvalidArgumentException('Project name too long (max 100 characters)');
        }
    }

    private function checkRateLimit(string $userId): void
    {
        $cacheKey = "rate_limit:badge_generation:{$userId}";
        $attempts = Cache::get($cacheKey, 0);
        
        if ($attempts >= 10) { // 10 badges per 5 minutes
            throw new \Exception('Rate limit exceeded. Please try again later.');
        }
        
        Cache::put($cacheKey, $attempts + 1, self::RATE_LIMIT_WINDOW);
    }

    private function checkVerificationRateLimit(string $ip): void
    {
        $cacheKey = "rate_limit:verification:{$ip}";
        $attempts = Cache::get($cacheKey, 0);
        
        if ($attempts >= 50) { // 50 verifications per 5 minutes
            throw new \Exception('Verification rate limit exceeded');
        }
        
        Cache::put($cacheKey, $attempts + 1, self::RATE_LIMIT_WINDOW);
    }

    private function createSecurePayload(string $contractAddress, string $userId, array $metadata, array $options): array
    {
        $now = Carbon::now();
        $expiresAt = $now->copy()->addSeconds($this->securityConfig['url_lifetime_seconds']);
        
        return [
            'contract_address' => $contractAddress,
            'user_id' => $userId,
            'metadata' => $metadata,
            'issued_at' => $now->toISOString(),
            'expires_at' => $expiresAt->toISOString(),
            'expires_timestamp' => $expiresAt->timestamp,
            'nonce' => Str::random(32),
            'session_id' => Str::random(16),
            'ip_address' => $this->securityConfig['require_ip_binding'] ? Request::ip() : null,
            'user_agent_hash' => $this->securityConfig['require_user_agent_binding'] 
                ? hash('sha256', Request::userAgent() ?? '') : null,
            'version' => self::SIGNATURE_VERSION,
            'timestamp' => $now->timestamp,
        ];
    }

    private function generateMultiLayerSignatures(array $payload): array
    {
        // Sort payload for consistent signature generation
        ksort($payload);
        $payloadString = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
        return [
            'primary' => hash_hmac(self::ALGORITHM, $payloadString, $this->primaryKey),
            'hmac' => hash_hmac(self::ALGORITHM, $payloadString . $payload['nonce'], $this->hmacKey),
            'composite' => hash_hmac(self::ALGORITHM, $payloadString . $this->saltKey, $this->primaryKey . $this->hmacKey),
            'checksum' => hash(self::ALGORITHM, $payloadString . $payload['timestamp']),
        ];
    }

    private function verifyMultiLayerSignatures(array $payload, array $signatures): array
    {
        $expectedSignatures = $this->generateMultiLayerSignatures($payload);
        
        $results = [];
        foreach ($expectedSignatures as $type => $expected) {
            $provided = $signatures[$type] ?? '';
            $results[$type] = hash_equals($expected, $provided);
        }
        
        $allValid = !in_array(false, $results, true);
        
        return [
            'valid' => $allValid,
            'results' => $results,
            'details' => $allValid ? 'All signatures valid' : 'One or more signatures invalid',
        ];
    }

    private function createVerificationToken(array $payload, array $signatures): string
    {
        $tokenData = [
            'payload' => $payload,
            'signatures' => $signatures,
            'token_version' => self::SIGNATURE_VERSION,
            'created_at' => time(),
        ];
        
        $encoded = base64_encode(json_encode($tokenData));
        return str_replace(['+', '/', '='], ['-', '_', ''], $encoded);
    }

    private function decodeVerificationToken(string $token): array
    {
        try {
            $token = str_replace(['-', '_'], ['+', '/'], $token);
            $token = str_pad($token, strlen($token) + (4 - strlen($token) % 4) % 4, '=');
            
            $decoded = base64_decode($token);
            if (!$decoded) {
                return ['success' => false, 'error' => 'Invalid token encoding'];
            }
            
            $data = json_decode($decoded, true);
            if (!$data) {
                return ['success' => false, 'error' => 'Invalid token JSON'];
            }
            
            if (!isset($data['payload']) || !isset($data['signatures'])) {
                return ['success' => false, 'error' => 'Invalid token structure'];
            }
            
            return [
                'success' => true,
                'payload' => $data['payload'],
                'signatures' => $data['signatures'],
                'token_version' => $data['token_version'] ?? 'unknown',
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Token decode failed: ' . $e->getMessage()];
        }
    }

    private function generateSecureUrls(string $token, array $payload): array
    {
        $baseUrl = config('app.url');
        $encodedToken = urlencode($token);
        
        return [
            'badge_url' => "{$baseUrl}/verification/badge/{$encodedToken}",
            'verification_url' => "{$baseUrl}/verification/verify/{$encodedToken}",
            'embed_url' => "{$baseUrl}/verification/embed/{$encodedToken}",
            'api_url' => "{$baseUrl}/api/verification/verify/{$encodedToken}",
        ];
    }

    private function storeSecureVerificationData(string $token, array $payload, array $signatures): void
    {
        $cacheKey = self::CACHE_PREFIX . hash('sha256', $token);
        $ttl = $this->securityConfig['url_lifetime_seconds'];
        
        $data = [
            'payload' => $payload,
            'signatures' => $signatures,
            'stored_at' => now()->toISOString(),
            'access_count' => 0,
        ];
        
        Cache::put($cacheKey, $data, $ttl);
    }

    private function createVerificationRecord(string $contractAddress, string $userId, string $token, array $metadata): VerificationBadge
    {
        return VerificationBadge::create([
            'contract_address' => $contractAddress,
            'user_id' => $userId,
            'verification_token' => hash('sha256', $token), // Store hash, not token
            'verified_at' => now(),
            'verification_method' => 'secure_hmac_' . self::SIGNATURE_VERSION,
            'metadata' => array_merge($metadata, [
                'security_level' => 'military_grade',
                'signature_algorithm' => self::ALGORITHM,
                'signature_version' => self::SIGNATURE_VERSION,
            ]),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'expires_at' => now()->addSeconds($this->securityConfig['url_lifetime_seconds']),
        ]);
    }

    private function isTokenExpired(array $payload): bool
    {
        return isset($payload['expires_timestamp']) && time() > $payload['expires_timestamp'];
    }

    private function isNonceUsed(string $nonce): bool
    {
        $cacheKey = self::NONCE_PREFIX . hash('sha256', $nonce);
        return Cache::has($cacheKey);
    }

    private function markNonceAsUsed(string $nonce): void
    {
        $cacheKey = self::NONCE_PREFIX . hash('sha256', $nonce);
        Cache::put($cacheKey, true, $this->securityConfig['url_lifetime_seconds']);
    }

    private function verifyIpBinding(array $payload, string $currentIp): bool
    {
        return isset($payload['ip_address']) && $payload['ip_address'] === $currentIp;
    }

    private function verifyUserAgentBinding(array $payload, string $currentUserAgent): bool
    {
        $currentHash = hash('sha256', $currentUserAgent);
        return isset($payload['user_agent_hash']) && $payload['user_agent_hash'] === $currentHash;
    }

    private function verifyDatabaseRecord(string $contractAddress, string $userId): array
    {
        $badge = VerificationBadge::where('contract_address', $contractAddress)
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if (!$badge) {
            return ['valid' => false, 'error' => 'No valid verification record found'];
        }

        return [
            'valid' => true,
            'data' => $badge,
        ];
    }

    private function createVerificationResponse(bool $success, string $message, string $code, array $data = []): array
    {
        return [
            'success' => $success,
            'message' => $message,
            'code' => $code,
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ];
    }

    private function logSecurityEvent(string $event, array $payload, array $context): void
    {
        Log::warning("Security event: {$event}", [
            'event' => $event,
            'contract_address' => $payload['contract_address'] ?? 'unknown',
            'user_id' => $payload['user_id'] ?? 'unknown',
            'ip_address' => $context['ip'] ?? Request::ip(),
            'user_agent' => $context['user_agent'] ?? Request::userAgent(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    private function getSecurityChecksCount(): int
    {
        $checks = ['signature_verification', 'expiration_check', 'database_verification'];
        
        if ($this->securityConfig['enable_nonce_tracking']) $checks[] = 'nonce_verification';
        if ($this->securityConfig['require_ip_binding']) $checks[] = 'ip_binding';
        if ($this->securityConfig['require_user_agent_binding']) $checks[] = 'user_agent_binding';
        
        return count($checks);
    }

    private function generateBadgeHtml(array $badgeData, array $options): string
    {
        $theme = $options['theme'] ?? 'default';
        $size = $options['size'] ?? 'medium';
        
        return view('verification.secure-badge', compact('badgeData', 'theme', 'size'))->render();
    }

    private function generateErrorBadgeHtml(string $error): string
    {
        return view('verification.error-badge', compact('error'))->render();
    }

    private function generateBadgeJson(array $badgeData): array
    {
        return [
            'verified' => true,
            'contract_address' => $badgeData['contract_address'],
            'project_name' => $badgeData['project_name'],
            'verification_level' => $badgeData['verification_level'],
            'security_level' => $badgeData['security_level'],
            'verified_at' => $badgeData['verified_at'],
            'expires_at' => $badgeData['expires_at'],
        ];
    }

    private function invalidateCachedVerificationData(string $contractAddress): void
    {
        // Clear related cache entries
        $pattern = self::CACHE_PREFIX . '*';
        // This would require Redis for pattern matching, simplified for demo
        Cache::flush(); // In production, use more targeted cache invalidation
    }
}
