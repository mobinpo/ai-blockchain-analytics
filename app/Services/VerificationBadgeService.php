<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Verification Badge Service
 * 
 * Handles creation and validation of secure verification badges
 * using SHA-256 + HMAC signatures to prevent spoofing
 */
final class VerificationBadgeService
{
    private const ALGORITHM = 'sha256';
    private const DEFAULT_EXPIRY_HOURS = 24;
    private const CACHE_PREFIX = 'verification_badge:';
    private const MAX_SIGNATURE_LENGTH = 128;
    
    private string $secretKey;
    private string $appUrl;

    public function __construct()
    {
        $this->secretKey = Config::get('app.key') . '_verification_salt';
        $this->appUrl = Config::get('app.url');
        
        if (empty($this->secretKey)) {
            throw new \RuntimeException('Application key must be set for verification badge service');
        }
    }

    /**
     * Generate a verified badge URL with HMAC signature
     */
    public function generateBadgeUrl(
        string $entityType,
        string $entityId,
        array $metadata = [],
        ?Carbon $expiresAt = null,
        string $badgeType = 'verified'
    ): array {
        $expiresAt = $expiresAt ?: now()->addHours(self::DEFAULT_EXPIRY_HOURS);
        
        // Create payload
        $payload = [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'badge_type' => $badgeType,
            'metadata' => $metadata,
            'issued_at' => now()->timestamp,
            'expires_at' => $expiresAt->timestamp,
            'nonce' => Str::random(16)
        ];

        // Generate signature
        $signature = $this->generateSignature($payload);
        
        // Create verification token
        $token = $this->encodePayload($payload, $signature);
        
        // Generate URLs
        $badgeUrl = $this->generateBadgeDisplayUrl($token);
        $verificationUrl = $this->generateVerificationUrl($token);
        
        // Cache the verification data
        $this->cacheVerificationData($token, $payload, $expiresAt);
        
        Log::info('Verification badge generated', [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'badge_type' => $badgeType,
            'expires_at' => $expiresAt->toISOString(),
            'token_preview' => substr($token, 0, 16) . '...'
        ]);

        return [
            'badge_url' => $badgeUrl,
            'verification_url' => $verificationUrl,
            'token' => $token,
            'expires_at' => $expiresAt->toISOString(),
            'metadata' => [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'badge_type' => $badgeType,
                'issued_at' => now()->toISOString(),
                'signature_algorithm' => self::ALGORITHM
            ]
        ];
    }

    /**
     * Verify a badge token and return validation result
     */
    public function verifyBadgeToken(string $token): array
    {
        try {
            // Check cache first
            $cached = $this->getCachedVerificationData($token);
            if ($cached) {
                return $this->validateCachedData($cached);
            }

            // Decode and validate token
            $decoded = $this->decodePayload($token);
            if (!$decoded) {
                return $this->createErrorResponse('Invalid token format', 'INVALID_FORMAT');
            }

            [$payload, $signature] = $decoded;

            // Verify signature
            if (!$this->verifySignature($payload, $signature)) {
                Log::warning('Verification badge signature validation failed', [
                    'token_preview' => substr($token, 0, 16) . '...',
                    'entity_type' => $payload['entity_type'] ?? 'unknown',
                    'entity_id' => $payload['entity_id'] ?? 'unknown'
                ]);
                
                return $this->createErrorResponse('Invalid signature', 'INVALID_SIGNATURE');
            }

            // Check expiry
            if ($this->isExpired($payload)) {
                return $this->createErrorResponse('Badge has expired', 'EXPIRED');
            }

            // Validate payload structure
            $validationResult = $this->validatePayloadStructure($payload);
            if (!$validationResult['valid']) {
                return $this->createErrorResponse($validationResult['error'], 'INVALID_PAYLOAD');
            }

            Log::info('Verification badge validated successfully', [
                'entity_type' => $payload['entity_type'],
                'entity_id' => $payload['entity_id'],
                'badge_type' => $payload['badge_type']
            ]);

            return [
                'valid' => true,
                'entity_type' => $payload['entity_type'],
                'entity_id' => $payload['entity_id'],
                'badge_type' => $payload['badge_type'],
                'metadata' => $payload['metadata'] ?? [],
                'issued_at' => Carbon::createFromTimestamp($payload['issued_at'])->toISOString(),
                'expires_at' => Carbon::createFromTimestamp($payload['expires_at'])->toISOString(),
                'verification_time' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            Log::error('Verification badge validation error', [
                'token_preview' => substr($token, 0, 16) . '...',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->createErrorResponse('Verification failed', 'SYSTEM_ERROR');
        }
    }

    /**
     * Generate badge for a smart contract
     */
    public function generateContractBadge(
        string $contractAddress,
        string $network = 'ethereum',
        array $analysisResults = [],
        string $badgeType = 'security_verified'
    ): array {
        $metadata = [
            'network' => $network,
            'analysis_results' => $analysisResults,
            'verification_level' => $this->calculateVerificationLevel($analysisResults),
            'analysis_timestamp' => now()->toISOString()
        ];

        return $this->generateBadgeUrl(
            'contract',
            $contractAddress,
            $metadata,
            now()->addDays(30), // Contracts verified for 30 days
            $badgeType
        );
    }

    /**
     * Generate badge for a user/developer
     */
    public function generateUserBadge(
        string $userId,
        array $credentials = [],
        string $badgeType = 'developer_verified'
    ): array {
        $metadata = [
            'credentials' => $credentials,
            'verification_level' => $this->calculateUserVerificationLevel($credentials),
            'verification_timestamp' => now()->toISOString()
        ];

        return $this->generateBadgeUrl(
            'user',
            $userId,
            $metadata,
            now()->addMonths(6), // User verification valid for 6 months
            $badgeType
        );
    }

    /**
     * Generate badge for an analysis result
     */
    public function generateAnalysisBadge(
        string $analysisId,
        array $analysisData = [],
        string $badgeType = 'analysis_verified'
    ): array {
        $metadata = [
            'analysis_data' => $analysisData,
            'confidence_score' => $analysisData['confidence_score'] ?? 0,
            'analysis_engine' => $analysisData['engine'] ?? 'ai_blockchain_analytics',
            'analysis_timestamp' => now()->toISOString()
        ];

        return $this->generateBadgeUrl(
            'analysis',
            $analysisId,
            $metadata,
            now()->addDays(7), // Analysis verification valid for 7 days
            $badgeType
        );
    }

    /**
     * Revoke a verification badge
     */
    public function revokeBadge(string $token, string $reason = ''): bool
    {
        try {
            $cacheKey = self::CACHE_PREFIX . hash('sha256', $token);
            
            // Mark as revoked in cache
            Cache::put($cacheKey . ':revoked', [
                'revoked_at' => now()->toISOString(),
                'reason' => $reason
            ], now()->addDays(30));

            // Remove from active cache
            Cache::forget($cacheKey);

            Log::info('Verification badge revoked', [
                'token_preview' => substr($token, 0, 16) . '...',
                'reason' => $reason
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to revoke verification badge', [
                'token_preview' => substr($token, 0, 16) . '...',
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Check if a badge has been revoked
     */
    public function isBadgeRevoked(string $token): array
    {
        $cacheKey = self::CACHE_PREFIX . hash('sha256', $token) . ':revoked';
        $revokedData = Cache::get($cacheKey);

        if ($revokedData) {
            return [
                'revoked' => true,
                'revoked_at' => $revokedData['revoked_at'],
                'reason' => $revokedData['reason']
            ];
        }

        return ['revoked' => false];
    }

    /**
     * Get badge statistics
     */
    public function getBadgeStatistics(): array
    {
        // This would typically query a database for comprehensive stats
        // For now, we'll return cache-based statistics
        
        return [
            'total_issued' => $this->getCacheStatistic('total_issued'),
            'active_badges' => $this->getCacheStatistic('active_badges'),
            'expired_badges' => $this->getCacheStatistic('expired_badges'),
            'revoked_badges' => $this->getCacheStatistic('revoked_badges'),
            'badge_types' => [
                'security_verified' => $this->getCacheStatistic('type_security_verified'),
                'developer_verified' => $this->getCacheStatistic('type_developer_verified'),
                'analysis_verified' => $this->getCacheStatistic('type_analysis_verified')
            ]
        ];
    }

    /**
     * Get verification statistics (alias for getBadgeStatistics)
     */
    public function getVerificationStats(): array
    {
        return $this->getBadgeStatistics();
    }

    /**
     * Get statistics (alias for getBadgeStatistics)
     */
    public function getStatistics(): array
    {
        return $this->getBadgeStatistics();
    }

    /**
     * Generate verification URL for contract (controller compatible method)
     */
    public function generateContractVerificationUrl(string $contractAddress, ?string $projectName = null, array $metadata = [], int $expiryHours = 24): array
    {
        return $this->generateBadgeUrl(
            'smart_contract',
            $contractAddress,
            array_merge($metadata, ['project_name' => $projectName]),
            now()->addHours($expiryHours),
            'verified'
        );
    }

    /**
     * Generate badge HTML
     */
    public function generateBadgeHtml(string $contractAddress, ?string $projectName = null, array $options = []): string
    {
        $size = $options['size'] ?? 'medium';
        $showDetails = $options['show_details'] ?? true;
        
        $badgeUrl = $this->generateContractVerificationUrl($contractAddress, $projectName, [], 24)['badge_url'];
        
        return sprintf(
            '<div class="verification-badge verification-badge-%s" data-contract="%s">
                <img src="data:image/svg+xml;base64,%s" alt="Verified Contract" />
                %s
            </div>',
            $size,
            htmlspecialchars($contractAddress),
            base64_encode($this->generateBadgeSvg($projectName ?? $contractAddress, $size)),
            $showDetails ? '<span class="badge-details">' . htmlspecialchars($projectName ?? 'Smart Contract') . '</span>' : ''
        );
    }

    /**
     * Generate badge SVG
     */
    private function generateBadgeSvg(string $text, string $size = 'medium'): string
    {
        $width = $size === 'large' ? 200 : ($size === 'small' ? 120 : 160);
        $height = $size === 'large' ? 40 : ($size === 'small' ? 24 : 32);
        
        return sprintf(
            '<svg width="%d" height="%d" xmlns="http://www.w3.org/2000/svg">
                <rect width="%d" height="%d" fill="#28a745" rx="4"/>
                <text x="50%%" y="50%%" dominant-baseline="middle" text-anchor="middle" fill="white" font-family="Arial, sans-serif" font-size="12">✓ %s</text>
            </svg>',
            $width, $height, $width, $height, htmlspecialchars(substr($text, 0, 20))
        );
    }

    /**
     * Batch generate verifications
     */
    public function batchGenerateVerifications(array $contracts): array
    {
        $results = [];
        
        foreach ($contracts as $contract) {
            try {
                $result = $this->generateContractVerificationUrl(
                    $contract['address'],
                    $contract['project_name'] ?? null,
                    $contract['metadata'] ?? [],
                    $contract['expiry_hours'] ?? 24
                );
                $results[] = array_merge(['success' => true], $result);
            } catch (\Exception $e) {
                $results[] = [
                    'success' => false,
                    'address' => $contract['address'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }

    /**
     * Verify a badge (alias for verifyBadgeToken)
     */
    public function verifyBadge(string $token): array
    {
        return $this->verifyBadgeToken($token);
    }

    /**
     * Verify a signed URL
     */
    public function verifySignedUrl(string $url): array
    {
        try {
            $parsedUrl = parse_url($url);
            parse_str($parsedUrl['query'] ?? '', $queryParams);
            
            if (!isset($queryParams['token'])) {
                return [
                    'success' => false,
                    'valid' => false,
                    'error' => 'No verification token found'
                ];
            }
            
            $result = $this->verifyBadge($queryParams['token']);
            
            if ($result['valid']) {
                return [
                    'success' => true,
                    'valid' => true,
                    'contract_address' => $result['payload']['entity_id'] ?? '',
                    'project_name' => $result['payload']['metadata']['project_name'] ?? 'Smart Contract',
                    'verified_at' => date('Y-m-d H:i:s', $result['payload']['issued_at'] ?? time()),
                    'expires_at' => date('Y-m-d H:i:s', $result['payload']['expires_at'] ?? time()),
                    'metadata' => $result['payload']['metadata'] ?? []
                ];
            } else {
                return [
                    'success' => false,
                    'valid' => false,
                    'error' => $result['error'] ?? 'Invalid verification'
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'valid' => false,
                'error' => 'Failed to verify URL: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generate HMAC signature for payload
     */
    private function generateSignature(array $payload): string
    {
        $data = $this->canonicalizePayload($payload);
        return hash_hmac(self::ALGORITHM, $data, $this->secretKey);
    }

    /**
     * Verify HMAC signature
     */
    private function verifySignature(array $payload, string $signature): bool
    {
        $expectedSignature = $this->generateSignature($payload);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Canonicalize payload for consistent signing
     */
    private function canonicalizePayload(array $payload): string
    {
        // Sort payload recursively for consistent ordering
        $this->recursiveSort($payload);
        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Recursively sort array for consistent canonicalization
     */
    private function recursiveSort(array &$array): void
    {
        ksort($array);
        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->recursiveSort($value);
            }
        }
    }

    /**
     * Encode payload with signature into a token
     */
    private function encodePayload(array $payload, string $signature): string
    {
        $data = [
            'payload' => $payload,
            'signature' => $signature
        ];

        return base64_encode(json_encode($data));
    }

    /**
     * Decode token into payload and signature
     */
    private function decodePayload(string $token): ?array
    {
        try {
            $decoded = base64_decode($token, true);
            if ($decoded === false) {
                return null;
            }

            $data = json_decode($decoded, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }

            if (!isset($data['payload']) || !isset($data['signature'])) {
                return null;
            }

            // Validate signature length to prevent attacks
            if (strlen($data['signature']) > self::MAX_SIGNATURE_LENGTH) {
                return null;
            }

            return [$data['payload'], $data['signature']];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if payload is expired
     */
    private function isExpired(array $payload): bool
    {
        if (!isset($payload['expires_at'])) {
            return true;
        }

        return now()->timestamp > $payload['expires_at'];
    }

    /**
     * Validate payload structure
     */
    private function validatePayloadStructure(array $payload): array
    {
        $required = ['entity_type', 'entity_id', 'badge_type', 'issued_at', 'expires_at', 'nonce'];
        
        foreach ($required as $field) {
            if (!isset($payload[$field])) {
                return ['valid' => false, 'error' => "Missing required field: {$field}"];
            }
        }

        // Validate entity types
        $validEntityTypes = ['contract', 'user', 'analysis'];
        if (!in_array($payload['entity_type'], $validEntityTypes)) {
            return ['valid' => false, 'error' => 'Invalid entity type'];
        }

        // Validate timestamps
        if (!is_numeric($payload['issued_at']) || !is_numeric($payload['expires_at'])) {
            return ['valid' => false, 'error' => 'Invalid timestamp format'];
        }

        return ['valid' => true];
    }

    /**
     * Generate badge display URL
     */
    private function generateBadgeDisplayUrl(string $token): string
    {
        return $this->appUrl . '/verification/badge/' . urlencode($token);
    }

    /**
     * Generate verification URL
     */
    private function generateVerificationUrl(string $token): string
    {
        return $this->appUrl . '/verification/verify/' . urlencode($token);
    }

    /**
     * Cache verification data
     */
    private function cacheVerificationData(string $token, array $payload, Carbon $expiresAt): void
    {
        $cacheKey = self::CACHE_PREFIX . hash('sha256', $token);
        $ttl = $expiresAt->diffInSeconds(now());
        
        Cache::put($cacheKey, [
            'payload' => $payload,
            'cached_at' => now()->toISOString()
        ], $ttl);
    }

    /**
     * Get cached verification data
     */
    private function getCachedVerificationData(string $token): ?array
    {
        $cacheKey = self::CACHE_PREFIX . hash('sha256', $token);
        return Cache::get($cacheKey);
    }

    /**
     * Validate cached data
     */
    private function validateCachedData(array $cached): array
    {
        $payload = $cached['payload'];
        
        if ($this->isExpired($payload)) {
            return $this->createErrorResponse('Badge has expired', 'EXPIRED');
        }

        return [
            'valid' => true,
            'entity_type' => $payload['entity_type'],
            'entity_id' => $payload['entity_id'],
            'badge_type' => $payload['badge_type'],
            'metadata' => $payload['metadata'] ?? [],
            'issued_at' => Carbon::createFromTimestamp($payload['issued_at'])->toISOString(),
            'expires_at' => Carbon::createFromTimestamp($payload['expires_at'])->toISOString(),
            'verification_time' => now()->toISOString(),
            'from_cache' => true
        ];
    }

    /**
     * Create error response
     */
    private function createErrorResponse(string $message, string $code): array
    {
        return [
            'valid' => false,
            'error' => $message,
            'error_code' => $code,
            'verification_time' => now()->toISOString()
        ];
    }

    /**
     * Calculate verification level based on analysis results
     */
    private function calculateVerificationLevel(array $analysisResults): string
    {
        $score = $analysisResults['security_score'] ?? 0;
        
        if ($score >= 90) return 'high';
        if ($score >= 70) return 'medium';
        if ($score >= 50) return 'low';
        
        return 'unverified';
    }

    /**
     * Calculate user verification level based on credentials
     */
    private function calculateUserVerificationLevel(array $credentials): string
    {
        $score = 0;
        
        if (isset($credentials['github_verified'])) $score += 20;
        if (isset($credentials['email_verified'])) $score += 20;
        if (isset($credentials['kyc_verified'])) $score += 30;
        if (isset($credentials['contracts_deployed']) && $credentials['contracts_deployed'] > 0) $score += 30;
        
        if ($score >= 80) return 'high';
        if ($score >= 50) return 'medium';
        if ($score >= 20) return 'low';
        
        return 'unverified';
    }

    /**
     * Get cache-based statistic
     */
    private function getCacheStatistic(string $key): int
    {
        return (int) Cache::get('badge_stats:' . $key, 0);
    }

    /**
     * Increment cache statistic
     */
    private function incrementCacheStatistic(string $key): void
    {
        $current = $this->getCacheStatistic($key);
        Cache::put('badge_stats:' . $key, $current + 1, now()->addDays(30));
    }
}