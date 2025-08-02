<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

final class ContractCache extends Model
{
    use HasFactory;

    protected $table = 'contract_cache';

    protected $fillable = [
        'network',
        'contract_address',
        'cache_type',
        'contract_name',
        'compiler_version',
        'optimization_used',
        'optimization_runs',
        'constructor_arguments',
        'evm_version',
        'library',
        'license_type',
        'proxy',
        'implementation',
        'swarm_source',
        'source_code',
        'parsed_sources',
        'abi',
        'is_verified',
        'creator_address',
        'creation_tx_hash',
        'metadata',
        'fetched_at',
        'expires_at',
    ];

    protected $casts = [
        'optimization_used' => 'boolean',
        'optimization_runs' => 'integer',
        'proxy' => 'boolean',
        'is_verified' => 'boolean',
        'parsed_sources' => 'array',
        'abi' => 'array',
        'metadata' => 'array',
        'fetched_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public const CACHE_TYPES = [
        'source' => 'Contract Source Code',
        'abi' => 'Contract ABI',
        'creation' => 'Contract Creation',
    ];

    /**
     * Scope to filter by network and address
     */
    public function scopeForContract(Builder $query, string $network, string $address): Builder
    {
        return $query->where('network', $network)
                    ->where('contract_address', $address);
    }

    /**
     * Scope to filter by cache type
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('cache_type', $type);
    }

    /**
     * Scope to get non-expired cache entries
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope to get expired cache entries
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now());
    }

    /**
     * Check if cache entry is still valid
     */
    public function isValid(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    /**
     * Check if cache entry is expired
     */
    public function isExpired(): bool
    {
        return !$this->isValid();
    }

    /**
     * Set expiration time based on TTL in seconds
     */
    public function setExpirationFromTtl(int $ttlSeconds): void
    {
        $this->expires_at = now()->addSeconds($ttlSeconds);
    }

    /**
     * Get cache entry for specific contract and type
     */
    public static function getForContract(string $network, string $address, string $type): ?self
    {
        return self::forContract($network, $address)
                  ->ofType($type)
                  ->valid()
                  ->first();
    }

    /**
     * Store contract data in cache
     */
    public static function storeContractData(
        string $network,
        string $address,
        string $type,
        array $data,
        int $ttlSeconds = 3600
    ): self {
        $cache = self::updateOrCreate(
            [
                'network' => $network,
                'contract_address' => $address,
                'cache_type' => $type,
            ],
            array_merge($data, [
                'fetched_at' => now(),
                'expires_at' => now()->addSeconds($ttlSeconds),
            ])
        );

        return $cache;
    }

    /**
     * Clean up expired cache entries
     */
    public static function cleanupExpired(): int
    {
        return self::expired()->delete();
    }

    /**
     * Get cache statistics
     */
    public static function getStats(): array
    {
        $total = self::count();
        $expired = self::expired()->count();
        $valid = self::valid()->count();
        
        $byNetwork = self::selectRaw('network, count(*) as count')
                        ->groupBy('network')
                        ->pluck('count', 'network')
                        ->toArray();
                        
        $byType = self::selectRaw('cache_type, count(*) as count')
                     ->groupBy('cache_type')
                     ->pluck('count', 'cache_type')
                     ->toArray();

        return [
            'total_entries' => $total,
            'valid_entries' => $valid,
            'expired_entries' => $expired,
            'by_network' => $byNetwork,
            'by_type' => $byType,
            'oldest_entry' => self::oldest('created_at')->value('created_at'),
            'newest_entry' => self::latest('created_at')->value('created_at'),
        ];
    }

    /**
     * Convert cache entry back to service response format
     */
    public function toServiceResponse(): array
    {
        $baseResponse = [
            'network' => $this->network,
            'contract_address' => $this->contract_address,
            'fetched_at' => $this->fetched_at->toISOString(),
            'cached' => true,
            'cache_expires_at' => $this->expires_at?->toISOString(),
        ];

        return match ($this->cache_type) {
            'source' => array_merge($baseResponse, [
                'contract_name' => $this->contract_name,
                'compiler_version' => $this->compiler_version,
                'optimization_used' => $this->optimization_used,
                'optimization_runs' => $this->optimization_runs,
                'constructor_arguments' => $this->constructor_arguments,
                'evm_version' => $this->evm_version,
                'library' => $this->library,
                'license_type' => $this->license_type,
                'proxy' => $this->proxy,
                'implementation' => $this->implementation,
                'swarm_source' => $this->swarm_source,
                'source_code' => $this->source_code,
                'parsed_sources' => $this->parsed_sources,
                'abi' => $this->abi,
                'is_verified' => $this->is_verified,
            ]),
            'abi' => array_merge($baseResponse, [
                'abi' => $this->abi,
            ]),
            'creation' => array_merge($baseResponse, [
                'creator_address' => $this->creator_address,
                'creation_tx_hash' => $this->creation_tx_hash,
            ]),
            default => $baseResponse,
        };
    }
}