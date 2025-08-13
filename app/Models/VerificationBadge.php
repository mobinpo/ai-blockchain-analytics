<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

final class VerificationBadge extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_address',
        'user_id',
        'verification_token',
        'verified_at',
        'verification_method',
        'metadata',
        'ip_address',
        'user_agent',
        'revoked_at',
        'revoked_reason',
        'expires_at'
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'revoked_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array'
    ];

    /**
     * Check if the verification badge is currently active
     */
    public function isActive(): bool
    {
        return !$this->isRevoked() && !$this->isExpired();
    }

    /**
     * Check if the verification has been revoked
     */
    public function isRevoked(): bool
    {
        return !is_null($this->revoked_at);
    }

    /**
     * Check if the verification has expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Revoke the verification badge
     */
    public function revoke(string $reason = null): bool
    {
        return $this->update([
            'revoked_at' => now(),
            'revoked_reason' => $reason
        ]);
    }

    /**
     * Get the project name from metadata
     */
    public function getProjectNameAttribute(): ?string
    {
        return $this->metadata['project_name'] ?? null;
    }

    /**
     * Get the website URL from metadata
     */
    public function getWebsiteAttribute(): ?string
    {
        return $this->metadata['website'] ?? null;
    }

    /**
     * Get the description from metadata
     */
    public function getDescriptionAttribute(): ?string
    {
        return $this->metadata['description'] ?? null;
    }

    /**
     * Scope for active verifications only
     */
    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at')
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    });
    }

    /**
     * Scope for specific contract address
     */
    public function scopeForContract($query, string $contractAddress)
    {
        return $query->where('contract_address', strtolower($contractAddress));
    }

    /**
     * Scope for specific user
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for verified badges only
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('verified_at');
    }

    /**
     * Get the verification status as an array
     */
    public function getStatusArray(): array
    {
        return [
            'is_verified' => !is_null($this->verified_at),
            'is_active' => $this->isActive(),
            'is_revoked' => $this->isRevoked(),
            'is_expired' => $this->isExpired(),
            'contract_address' => $this->contract_address,
            'verified_at' => $this->verified_at?->toISOString(),
            'verification_method' => $this->verification_method,
            'metadata' => $this->metadata,
            'project_name' => $this->project_name,
            'website' => $this->website,
            'description' => $this->description
        ];
    }

    /**
     * Get truncated contract address for display
     */
    public function getTruncatedAddressAttribute(): string
    {
        $addr = $this->contract_address;
        return strlen($addr) > 10 ? substr($addr, 0, 6) . '...' . substr($addr, -4) : $addr;
    }

    /**
     * Get verification age in human readable format
     */
    public function getVerificationAgeAttribute(): ?string
    {
        if (!$this->verified_at) return null;
        return $this->verified_at->diffForHumans();
    }

    /**
     * Generate badge HTML for this verification
     */
    public function getBadgeHtml(): string
    {
        if (!$this->isActive()) return '';

        $tooltipData = [
            'project' => $this->project_name ?: 'Contract Verified',
            'address' => $this->truncated_address,
            'verified' => $this->verified_at->format('M d, Y'),
            'method' => $this->verification_method ?: 'Signed URL'
        ];

        return view('verification.badge', [
            'verification' => $this,
            'tooltip' => $tooltipData
        ])->render();
    }

    /**
     * Find active verification for a contract
     */
    public static function findActiveForContract(string $contractAddress): ?self
    {
        return static::forContract($contractAddress)
                    ->active()
                    ->verified()
                    ->latest('verified_at')
                    ->first();
    }

    /**
     * Create a new verification record
     */
    public static function createVerification(array $data): self
    {
        return static::create([
            'contract_address' => strtolower($data['contract_address']),
            'user_id' => $data['user_id'],
            'verification_token' => $data['verification_token'] ?? null,
            'verified_at' => $data['verified_at'] ?? now(),
            'verification_method' => $data['verification_method'] ?? 'signed_url',
            'metadata' => $data['metadata'] ?? [],
            'ip_address' => $data['ip_address'] ?? request()->ip(),
            'user_agent' => $data['user_agent'] ?? request()->userAgent(),
            'expires_at' => $data['expires_at'] ?? null
        ]);
    }
}