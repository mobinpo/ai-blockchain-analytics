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
        'fetched_from_api',
        'last_api_fetch',
        'api_fetch_count',
        'cache_priority',
        'cache_quality_score',
        'cache_metrics',
        'source_complete',
        'abi_complete',
        'source_file_count',
        'source_line_count',
        'next_refresh_at',
        'refresh_strategy',
        'error_count',
        'last_error_at',
        'last_error_message'
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
        'fetched_from_api' => 'boolean',
        'last_api_fetch' => 'datetime',
        'api_fetch_count' => 'integer',
        'cache_quality_score' => 'float',
        'cache_metrics' => 'array',
        'source_complete' => 'boolean',
        'abi_complete' => 'boolean',
        'source_file_count' => 'integer',
        'source_line_count' => 'integer',
        'next_refresh_at' => 'datetime',
        'error_count' => 'integer',
        'last_error_at' => 'datetime'
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
     * Store contract data with enhanced caching features
     */
    public static function storeContractDataEnhanced(
        string $network,
        string $address,
        string $type,
        array $data,
        int $ttlSeconds = 3600,
        string $priority = 'medium',
        bool $fromApi = true
    ): self {
        $cacheData = array_merge($data, [
            'fetched_at' => now(),
            'expires_at' => now()->addSeconds($ttlSeconds),
            'fetched_from_api' => $fromApi,
            'last_api_fetch' => $fromApi ? now() : null,
            'cache_priority' => $priority,
            'cache_quality_score' => self::calculateQualityScore($data, $type),
            'cache_metrics' => self::generateCacheMetrics($data, $type),
            'next_refresh_at' => self::calculateNextRefresh($data, $type)
        ]);

        if ($fromApi) {
            $existing = self::where('network', $network)
                ->where('contract_address', $address)
                ->where('cache_type', $type)
                ->first();
                
            if ($existing) {
                $cacheData['api_fetch_count'] = $existing->api_fetch_count + 1;
            } else {
                $cacheData['api_fetch_count'] = 1;
            }
        }

        $cache = self::updateOrCreate(
            [
                'network' => $network,
                'contract_address' => $address,
                'cache_type' => $type,
            ],
            $cacheData
        );

        // Record analytics
        if (class_exists('App\Models\ContractCacheAnalytics')) {
            if ($fromApi) {
                \App\Models\ContractCacheAnalytics::recordCacheMiss($network, $type, $address);
            }
        }

        return $cache;
    }

    /**
     * Get cached data with analytics tracking
     */
    public static function getCachedData(string $network, string $address, string $type): ?array
    {
        $cached = self::where('network', $network)
            ->where('contract_address', $address)
            ->where('cache_type', $type)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->first();

        if ($cached) {
            // Record cache hit
            if (class_exists('App\Models\ContractCacheAnalytics')) {
                \App\Models\ContractCacheAnalytics::recordCacheHit($network, $type, $address);
            }
            
            return $cached->toServiceResponse();
        }

        // Record cache miss
        if (class_exists('App\Models\ContractCacheAnalytics')) {
            \App\Models\ContractCacheAnalytics::recordCacheMiss($network, $type, $address);
        }

        return null;
    }

    /**
     * Calculate cache quality score
     */
    private static function calculateQualityScore(array $data, string $type): float
    {
        $score = 1.0;
        
        switch ($type) {
            case 'source':
                // Higher score for verified contracts with complete source
                if (!($data['is_verified'] ?? false)) $score -= 0.3;
                if (empty($data['source_code'] ?? '')) $score -= 0.4;
                if (empty($data['abi'] ?? [])) $score -= 0.2;
                if (empty($data['compiler_version'] ?? '')) $score -= 0.1;
                break;
                
            case 'abi':
                if (empty($data['abi'] ?? [])) $score -= 0.5;
                break;
                
            case 'creation':
                if (empty($data['creator_address'] ?? '')) $score -= 0.3;
                if (empty($data['creation_tx_hash'] ?? '')) $score -= 0.3;
                break;
        }
        
        return max(0.0, min(1.0, $score));
    }

    /**
     * Generate cache metrics
     */
    private static function generateCacheMetrics(array $data, string $type): array
    {
        $metrics = [
            'data_size_bytes' => strlen(json_encode($data)),
            'created_at' => now()->toISOString()
        ];
        
        if ($type === 'source') {
            $sourceCode = $data['source_code'] ?? '';
            $metrics['source_length'] = strlen($sourceCode);
            $metrics['source_lines'] = substr_count($sourceCode, "\n") + 1;
            $metrics['has_multiple_files'] = count($data['parsed_sources'] ?? []) > 1;
        }
        
        return $metrics;
    }

    /**
     * Calculate next refresh time based on contract importance
     */
    private static function calculateNextRefresh(array $data, string $type): ?\Carbon\Carbon
    {
        // High-value contracts refresh more frequently
        $refreshStrategy = 'monthly'; // Default
        
        if ($type === 'source') {
            $sourceCode = $data['source_code'] ?? '';
            $hasProxy = $data['proxy'] ?? false;
            $lineCount = substr_count($sourceCode, "\n") + 1;
            
            if ($hasProxy || $lineCount > 1000) {
                $refreshStrategy = 'weekly';
            } elseif ($lineCount > 500) {
                $refreshStrategy = 'monthly';
            } else {
                $refreshStrategy = 'quarterly';
            }
        }
        
        return match ($refreshStrategy) {
            'weekly' => now()->addWeek(),
            'monthly' => now()->addMonth(),
            'quarterly' => now()->addMonths(3),
            'yearly' => now()->addYear(),
            default => null
        };
    }

    /**
     * Get contracts needing refresh
     */
    public static function getContractsNeedingRefresh(int $limit = 100): array
    {
        return self::where('next_refresh_at', '<=', now())
            ->where('refresh_strategy', '!=', 'never')
            ->orderBy('cache_priority', 'desc')
            ->orderBy('next_refresh_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Record cache error
     */
    public function recordError(string $errorMessage): void
    {
        $this->increment('error_count');
        $this->update([
            'last_error_at' => now(),
            'last_error_message' => $errorMessage
        ]);
    }

    /**
     * Get cache efficiency stats
     */
    public static function getCacheEfficiencyStats(): array
    {
        $total = self::count();
        $expired = self::where('expires_at', '<=', now())->count();
        $errorCount = self::where('error_count', '>', 0)->count();
        
        $avgQuality = self::avg('cache_quality_score') ?? 0;
        $totalApiCalls = self::sum('api_fetch_count') ?? 0;
        
        $sizeStats = self::selectRaw('
            cache_type,
            COUNT(*) as count,
            AVG(cache_quality_score) as avg_quality,
            SUM(api_fetch_count) as total_api_calls
        ')
        ->groupBy('cache_type')
        ->get()
        ->mapWithKeys(function ($stat) {
            return [$stat->cache_type => [
                'count' => $stat->count,
                'avg_quality' => round($stat->avg_quality, 2),
                'total_api_calls' => $stat->total_api_calls
            ]];
        });

        return [
            'total_entries' => $total,
            'active_entries' => $total - $expired,
            'expired_entries' => $expired,
            'error_entries' => $errorCount,
            'average_quality_score' => round($avgQuality, 2),
            'total_api_calls_saved' => max(0, $totalApiCalls - $total), // Calls saved by caching
            'cache_types' => $sizeStats->toArray(),
            'refresh_queue_size' => self::where('next_refresh_at', '<=', now())->count()
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
            'cache_quality_score' => $this->cache_quality_score,
            'api_fetch_count' => $this->api_fetch_count,
            'fetched_from_api' => $this->fetched_from_api
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
                'source_complete' => $this->source_complete,
                'source_file_count' => $this->source_file_count,
                'source_line_count' => $this->source_line_count
            ]),
            'abi' => array_merge($baseResponse, [
                'abi' => $this->abi,
                'abi_complete' => $this->abi_complete
            ]),
            'creation' => array_merge($baseResponse, [
                'creator_address' => $this->creator_address,
                'creation_tx_hash' => $this->creation_tx_hash,
            ]),
            default => $baseResponse,
        };
    }
}