<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\VerificationBadge;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;

/**
 * Enhanced Verification Badge Service
 * 
 * Provides cryptographically secure badge generation and verification
 * using SHA-256 + HMAC with additional anti-spoofing measures
 */
final class EnhancedVerificationBadgeService
{
    private string $secretKey;
    private string $hmacKey;
    private int $urlLifetime;
    private string $algorithm;
    private int $maxVerificationAttempts;
    private array $securityConfig;

    public function __construct()
    {
        $this->secretKey = config('verification.secret_key') ?: hash('sha256', config('app.key'));
        $this->hmacKey = config('verification.hmac_key') ?: hash('sha256', config('app.key') . 'HMAC');
        $this->urlLifetime = config('verification.url_lifetime', 3600); // 1 hour
        $this->algorithm = 'sha256';
        $this->maxVerificationAttempts = config('verification.max_attempts', 5);
        
        $this->securityConfig = [
            'require_ip_binding' => config('verification.require_ip_binding', true),
            'require_user_agent_binding' => config('verification.require_user_agent_binding', true),
            'enable_rate_limiting' => config('verification.enable_rate_limiting', true),
            'enable_nonce_tracking' => config('verification.enable_nonce_tracking', true),
            'signature_version' => 'v3.0'
        ];
    }

    /**
     * Generate a cryptographically secure verification URL with enhanced security
     */
    public function generateSecureVerificationUrl(
        string $contractAddress,
        string $userId,
        array $metadata = [],
        array $options = []
    ): array {
        try {
            // Input validation and sanitization
            $contractAddress = $this->sanitizeContractAddress($contractAddress);
            $this->validateUserId($userId);
            
            // Check rate limiting
            if ($this->securityConfig['enable_rate_limiting']) {
                $this->checkRateLimit($userId, $contractAddress);
            }

            $timestamp = Carbon::now()->timestamp;
            $expires = $timestamp + ($options['lifetime'] ?? $this->urlLifetime);
            $nonce = $this->generateSecureNonce();
            $ipAddress = request()->ip();
            $userAgent = request()->userAgent();

            // Create enhanced payload with multiple security layers
            $payload = [
                'contract_address' => $contractAddress,
                'user_id' => $userId,
                'timestamp' => $timestamp,
                'expires' => $expires,
                'nonce' => $nonce,
                'metadata' => $this->sanitizeMetadata($metadata),
                'security' => [
                    'version' => $this->securityConfig['signature_version'],
                    'ip_hash' => $this->securityConfig['require_ip_binding'] ? hash('sha256', $ipAddress . $this->secretKey) : null,
                    'ua_hash' => $this->securityConfig['require_user_agent_binding'] ? hash('sha256', $userAgent . $this->secretKey) : null,
                    'entropy' => bin2hex(random_bytes(16)),
                    'checksum' => null // Will be calculated after payload is complete
                ]
            ];

            // Calculate payload checksum for integrity
            $payload['security']['checksum'] = $this->calculatePayloadChecksum($payload);

            // Generate multi-layer signature
            $signature = $this->generateEnhancedSignature($payload);
            
            // Create verification token with additional encoding layers
            $tokenData = [
                'payload' => $payload,
                'signature' => $signature,
                'hmac' => hash_hmac('sha256', json_encode($payload), $this->hmacKey)
            ];

            $token = $this->encodeSecureToken($tokenData);

            // Store verification attempt with comprehensive tracking
            $this->storeVerificationAttempt($contractAddress, $userId, $token, $nonce, [
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'metadata' => $metadata,
                'security_level' => 'enhanced',
                'expires_at' => Carbon::createFromTimestamp($expires)
            ]);

            $verificationUrl = route('verification.verify', ['token' => $token]);

            Log::info('Enhanced verification URL generated', [
                'contract_address' => $contractAddress,
                'user_id' => $userId,
                'nonce' => $nonce,
                'security_version' => $this->securityConfig['signature_version'],
                'expires_at' => Carbon::createFromTimestamp($expires)->toISOString()
            ]);

            return [
                'success' => true,
                'verification_url' => $verificationUrl,
                'token' => $token,
                'nonce' => $nonce,
                'expires_at' => Carbon::createFromTimestamp($expires)->toISOString(),
                'expires_in' => $expires - $timestamp,
                'security_level' => 'enhanced',
                'signature_version' => $this->securityConfig['signature_version']
            ];

        } catch (Exception $e) {
            Log::error('Enhanced verification URL generation failed', [
                'contract_address' => $contractAddress ?? 'unknown',
                'user_id' => $userId ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Verify a signed URL with enhanced security checks
     */
    public function verifySecureUrl(string $token): array
    {
        try {
            // Decode and validate token structure
            $tokenData = $this->decodeSecureToken($token);
            
            if (!isset($tokenData['payload'], $tokenData['signature'], $tokenData['hmac'])) {
                throw new Exception('Invalid token structure - missing required components');
            }

            $payload = $tokenData['payload'];
            $providedSignature = $tokenData['signature'];
            $providedHmac = $tokenData['hmac'];

            // Multi-layer signature verification
            $this->verifyEnhancedSignature($payload, $providedSignature);
            
            // HMAC verification
            $expectedHmac = hash_hmac('sha256', json_encode($payload), $this->hmacKey);
            if (!hash_equals($expectedHmac, $providedHmac)) {
                throw new Exception('HMAC verification failed - token integrity compromised');
            }

            // Comprehensive security checks
            $this->performComprehensiveSecurityChecks($payload, $token);

            // Process verification and create badge
            $result = $this->processEnhancedVerification($payload);

            // Mark token as used to prevent replay attacks
            $this->markTokenAsUsed($token, $payload['nonce']);

            Log::info('Enhanced verification completed successfully', [
                'contract_address' => $payload['contract_address'],
                'user_id' => $payload['user_id'],
                'nonce' => $payload['nonce']
            ]);

            return $result;

        } catch (Exception $e) {
            Log::warning('Enhanced verification failed', [
                'token_preview' => substr($token, 0, 20) . '...',
                'error' => $e->getMessage(),
                'ip_address' => request()->ip()
            ]);

            throw $e;
        }
    }

    /**
     * Generate enhanced multi-layer signature
     */
    private function generateEnhancedSignature(array $payload): string
    {
        // Create canonical representation
        $canonicalString = $this->createCanonicalString($payload);
        
        // Layer 1: Basic HMAC
        $layer1 = hash_hmac($this->algorithm, $canonicalString, $this->secretKey);
        
        // Layer 2: Add timestamp and nonce binding
        $layer2 = hash_hmac($this->algorithm, $layer1 . $payload['timestamp'] . $payload['nonce'], $this->hmacKey);
        
        // Layer 3: Add contract address binding
        $layer3 = hash_hmac($this->algorithm, $layer2 . $payload['contract_address'], $this->secretKey);
        
        // Final signature with version prefix
        return $this->securityConfig['signature_version'] . ':' . $layer3;
    }

    /**
     * Verify enhanced signature with all layers
     */
    private function verifyEnhancedSignature(array $payload, string $providedSignature): void
    {
        $expectedSignature = $this->generateEnhancedSignature($payload);
        
        if (!hash_equals($expectedSignature, $providedSignature)) {
            throw new Exception('Enhanced signature verification failed - URL may have been tampered with');
        }

        // Verify signature version
        $signatureParts = explode(':', $providedSignature, 2);
        if (count($signatureParts) !== 2 || $signatureParts[0] !== $this->securityConfig['signature_version']) {
            throw new Exception('Invalid or unsupported signature version');
        }
    }

    /**
     * Perform comprehensive security checks
     */
    private function performComprehensiveSecurityChecks(array $payload, string $token): void
    {
        // Check expiration
        if (Carbon::now()->timestamp > $payload['expires']) {
            throw new Exception('Verification URL has expired');
        }

        // Verify payload integrity
        $expectedChecksum = $this->calculatePayloadChecksum($payload);
        if ($payload['security']['checksum'] !== $expectedChecksum) {
            throw new Exception('Payload integrity check failed');
        }

        // Check nonce uniqueness (prevent replay attacks)
        if ($this->securityConfig['enable_nonce_tracking'] && $this->isNonceUsed($payload['nonce'])) {
            throw new Exception('Nonce already used - replay attack detected');
        }

        // IP address binding verification
        if ($this->securityConfig['require_ip_binding'] && $payload['security']['ip_hash']) {
            $currentIpHash = hash('sha256', request()->ip() . $this->secretKey);
            if (!hash_equals($payload['security']['ip_hash'], $currentIpHash)) {
                throw new Exception('IP address verification failed - request from different IP');
            }
        }

        // User agent binding verification
        if ($this->securityConfig['require_user_agent_binding'] && $payload['security']['ua_hash']) {
            $currentUaHash = hash('sha256', request()->userAgent() . $this->secretKey);
            if (!hash_equals($payload['security']['ua_hash'], $currentUaHash)) {
                throw new Exception('User agent verification failed - request from different client');
            }
        }

        // Check if verification attempt exists and is valid
        $this->verifyVerificationAttempt($payload['contract_address'], $payload['user_id'], $payload['nonce']);
    }

    /**
     * Process enhanced verification and create badge
     */
    private function processEnhancedVerification(array $payload): array
    {
        $contractAddress = $payload['contract_address'];
        $userId = $payload['user_id'];
        $metadata = $payload['metadata'] ?? [];

        // Check if already verified
        $existingVerification = VerificationBadge::findActiveForContract($contractAddress);
        if ($existingVerification) {
            throw new Exception('Contract is already verified');
        }

        // Create enhanced verification record
        $verification = VerificationBadge::createVerification([
            'contract_address' => $contractAddress,
            'user_id' => $userId,
            'verification_token' => $payload['nonce'],
            'verified_at' => now(),
            'verification_method' => 'enhanced_signed_url',
            'metadata' => array_merge($metadata, [
                'security_version' => $this->securityConfig['signature_version'],
                'verification_timestamp' => $payload['timestamp'],
                'security_features' => [
                    'multi_layer_signature' => true,
                    'hmac_verification' => true,
                    'nonce_tracking' => $this->securityConfig['enable_nonce_tracking'],
                    'ip_binding' => $this->securityConfig['require_ip_binding'],
                    'user_agent_binding' => $this->securityConfig['require_user_agent_binding']
                ]
            ]),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'expires_at' => null // Permanent verification
        ]);

        // Generate secure badge
        $badgeHtml = $this->generateEnhancedBadgeHtml($verification);
        $badgeData = $this->generateBadgeData($verification);

        // Cache verification for quick access
        $cacheKey = "enhanced_verification:{$contractAddress}";
        Cache::put($cacheKey, [
            'verification' => $verification->toArray(),
            'badge_html' => $badgeHtml,
            'badge_data' => $badgeData
        ], now()->addDays(365));

        return [
            'success' => true,
            'message' => 'Contract successfully verified with enhanced security',
            'verification' => $verification->toArray(),
            'badge_html' => $badgeHtml,
            'badge_data' => $badgeData,
            'security_features' => [
                'signature_version' => $this->securityConfig['signature_version'],
                'multi_layer_protection' => true,
                'anti_spoofing' => true,
                'replay_protection' => true
            ]
        ];
    }

    /**
     * Generate enhanced badge HTML with security indicators
     */
    private function generateEnhancedBadgeHtml(VerificationBadge $verification): string
    {
        $contractAddress = $verification->contract_address;
        $projectName = $verification->project_name ?: 'Smart Contract';
        $verifiedAt = $verification->verified_at->format('M d, Y');
        $truncatedAddress = $verification->truncated_address;

        return <<<HTML
<div class="enhanced-verification-badge verified" 
     data-contract="{$contractAddress}" 
     data-security-version="{$this->securityConfig['signature_version']}"
     data-verified-at="{$verification->verified_at->toISOString()}">
    <div class="badge-container">
        <div class="badge-icon-container">
            <svg class="badge-icon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M10 0L12.5 4L17.5 3L16.5 8L20 10L16.5 12L17.5 17L12.5 16L10 20L7.5 16L2.5 17L3.5 12L0 10L3.5 8L2.5 3L7.5 4L10 0Z" fill="#10B981"/>
                <path d="M6 10L8.5 12.5L14 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="16" cy="4" r="3" fill="#3B82F6"/>
                <path d="M14.5 4L15.5 4.5L17.5 2.5" stroke="white" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <div class="security-indicator"></div>
        </div>
        <span class="badge-text">Enhanced Verified</span>
    </div>
    <div class="badge-tooltip">
        <div class="tooltip-header">
            <strong>{$projectName}</strong>
            <span class="security-level">🛡️ Enhanced Security</span>
        </div>
        <div class="tooltip-content">
            <div class="tooltip-row">
                <span class="label">Contract:</span>
                <span class="value">{$truncatedAddress}</span>
            </div>
            <div class="tooltip-row">
                <span class="label">Verified:</span>
                <span class="value">{$verifiedAt}</span>
            </div>
            <div class="tooltip-row">
                <span class="label">Method:</span>
                <span class="value">SHA-256 + HMAC</span>
            </div>
        </div>
        <div class="security-features">
            <div class="feature">✓ Multi-layer Signature</div>
            <div class="feature">✓ Anti-spoofing Protection</div>
            <div class="feature">✓ Replay Attack Prevention</div>
        </div>
    </div>
</div>
HTML;
    }

    /**
     * Generate badge data for API responses
     */
    private function generateBadgeData(VerificationBadge $verification): array
    {
        return [
            'contract_address' => $verification->contract_address,
            'is_verified' => true,
            'verification_method' => 'enhanced_signed_url',
            'verified_at' => $verification->verified_at->toISOString(),
            'project_name' => $verification->project_name,
            'security_level' => 'enhanced',
            'security_features' => [
                'signature_algorithm' => 'SHA-256 + HMAC',
                'multi_layer_protection' => true,
                'anti_spoofing' => true,
                'replay_protection' => true,
                'ip_binding' => $this->securityConfig['require_ip_binding'],
                'user_agent_binding' => $this->securityConfig['require_user_agent_binding']
            ],
            'badge_version' => $this->securityConfig['signature_version']
        ];
    }

    /**
     * Helper methods for security operations
     */
    private function sanitizeContractAddress(string $address): string
    {
        $address = strtolower(trim($address));
        if (!preg_match('/^0x[a-f0-9]{40}$/i', $address)) {
            throw new Exception('Invalid contract address format');
        }
        return $address;
    }

    private function validateUserId(string $userId): void
    {
        if (empty($userId) || strlen($userId) > 255) {
            throw new Exception('Invalid user ID');
        }
    }

    private function sanitizeMetadata(array $metadata): array
    {
        // Remove potentially dangerous keys and limit size
        $allowed = ['project_name', 'website', 'description', 'category', 'tags'];
        $sanitized = [];
        
        foreach ($allowed as $key) {
            if (isset($metadata[$key])) {
                $sanitized[$key] = substr(strip_tags((string)$metadata[$key]), 0, 500);
            }
        }
        
        return $sanitized;
    }

    private function generateSecureNonce(): string
    {
        return bin2hex(random_bytes(32)) . '_' . Carbon::now()->timestamp;
    }

    private function calculatePayloadChecksum(array $payload): string
    {
        $temp = $payload;
        unset($temp['security']['checksum']); // Remove checksum field itself
        return hash('sha256', json_encode($temp, JSON_SORT_KEYS));
    }

    private function createCanonicalString(array $payload): string
    {
        ksort($payload);
        $parts = [];
        
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                ksort($value);
                $value = json_encode($value, JSON_SORT_KEYS);
            }
            $parts[] = $key . '=' . $value;
        }
        
        return implode('&', $parts);
    }

    private function encodeSecureToken(array $tokenData): string
    {
        $json = json_encode($tokenData);
        $encoded = base64_encode($json);
        
        // Add additional encoding layer for obfuscation
        return str_replace(['+', '/', '='], ['-', '_', ''], $encoded);
    }

    private function decodeSecureToken(string $token): array
    {
        // Reverse additional encoding
        $token = str_replace(['-', '_'], ['+', '/'], $token);
        $token = str_pad($token, strlen($token) % 4, '=', STR_PAD_RIGHT);
        
        $decoded = base64_decode($token);
        if (!$decoded) {
            throw new Exception('Invalid token encoding');
        }
        
        $data = json_decode($decoded, true);
        if (!$data) {
            throw new Exception('Invalid token JSON structure');
        }
        
        return $data;
    }

    private function checkRateLimit(string $userId, string $contractAddress): void
    {
        $key = "verification_attempts:{$userId}:{$contractAddress}";
        $attempts = Cache::get($key, 0);
        
        if ($attempts >= $this->maxVerificationAttempts) {
            throw new Exception('Too many verification attempts. Please try again later.');
        }
        
        Cache::put($key, $attempts + 1, now()->addHour());
    }

    private function storeVerificationAttempt(string $contractAddress, string $userId, string $token, string $nonce, array $metadata): void
    {
        $key = "verification_attempt:{$nonce}";
        Cache::put($key, [
            'contract_address' => $contractAddress,
            'user_id' => $userId,
            'token' => $token,
            'created_at' => now(),
            'metadata' => $metadata
        ], now()->addHours(2));
    }

    private function verifyVerificationAttempt(string $contractAddress, string $userId, string $nonce): void
    {
        $key = "verification_attempt:{$nonce}";
        $attempt = Cache::get($key);
        
        if (!$attempt) {
            throw new Exception('Verification attempt not found or expired');
        }
        
        if ($attempt['contract_address'] !== $contractAddress || $attempt['user_id'] !== $userId) {
            throw new Exception('Verification attempt mismatch');
        }
    }

    private function isNonceUsed(string $nonce): bool
    {
        return Cache::has("used_nonce:{$nonce}");
    }

    private function markTokenAsUsed(string $token, string $nonce): void
    {
        Cache::put("used_nonce:{$nonce}", true, now()->addDays(30));
        Cache::put("used_token:" . hash('sha256', $token), true, now()->addDays(30));
    }

    /**
     * Get verification status for a contract
     */
    public function getVerificationStatus(string $contractAddress): array
    {
        $contractAddress = $this->sanitizeContractAddress($contractAddress);
        
        // Check cache first
        $cacheKey = "enhanced_verification:{$contractAddress}";
        $cached = Cache::get($cacheKey);
        
        if ($cached) {
            return array_merge($cached['badge_data'], ['cached' => true]);
        }
        
        // Check database
        $verification = VerificationBadge::findActiveForContract($contractAddress);
        
        if (!$verification) {
            return [
                'contract_address' => $contractAddress,
                'is_verified' => false,
                'verification_method' => null,
                'security_level' => null
            ];
        }
        
        return $this->generateBadgeData($verification);
    }
}