<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

final class ApiCache extends Model
{
    use HasFactory;

    protected $table = 'api_cache';

    protected $fillable = [
        'cache_key',
        'api_source',
        'endpoint',
        'resource_type',
        'resource_id',
        'request_params',
        'response_data',
        'response_hash',
        'expires_at',
        'hit_count',
        'last_accessed_at',
        'response_size',
        'status',
        'api_call_cost',
        'cache_efficiency',
        'metadata',
    ];

    protected $casts = [
        'request_params' => 'array',
        'response_data' => 'array',
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'hit_count' => 'integer',
        'response_size' => 'integer',
        'api_call_cost' => 'integer',
        'cache_efficiency' => 'decimal:2',
    ];

    /**
     * Scope to get only valid (non-expired) cache entries.
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now())
                    ->where('status', 'active');
    }

    /**
     * Scope to get expired cache entries.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<=', now())
                    ->orWhere('status', 'expired');
    }

    /**
     * Scope to filter by API source.
     */
    public function scopeForApiSource(Builder $query, string $apiSource): Builder
    {
        return $query->where('api_source', $apiSource);
    }

    /**
     * Scope to filter by resource type.
     */
    public function scopeForResourceType(Builder $query, string $resourceType): Builder
    {
        return $query->where('resource_type', $resourceType);
    }

    /**
     * Scope to filter by resource ID.
     */
    public function scopeForResource(Builder $query, string $resourceId): Builder
    {
        return $query->where('resource_id', $resourceId);
    }

    /**
     * Scope to get active cache entries.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Check if the cache entry is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast() || $this->status === 'expired';
    }

    /**
     * Check if the cache entry is valid (not expired).
     */
    public function isValid(): bool
    {
        return !$this->isExpired() && $this->status === 'active';
    }

    /**
     * Increment hit count and update last accessed time.
     */
    public function recordHit(): void
    {
        $this->increment('hit_count');
        $this->update([
            'last_accessed_at' => now(),
            'cache_efficiency' => $this->calculateEfficiency()
        ]);
    }

    /**
     * Calculate cache efficiency based on hits vs age.
     */
    private function calculateEfficiency(): float
    {
        $ageInHours = now()->diffInHours($this->created_at);
        $efficiency = $ageInHours > 0 ? ($this->hit_count / $ageInHours) : $this->hit_count;
        return round(min($efficiency * 10, 100), 2); // Scale to 0-100
    }

    /**
     * Generate response hash for integrity checking.
     */
    public function generateResponseHash(): string
    {
        return hash('sha256', is_string($this->response_data) ? 
            $this->response_data : 
            json_encode($this->response_data)
        );
    }

    /**
     * Verify response data integrity.
     */
    public function verifyIntegrity(): bool
    {
        return $this->response_hash === $this->generateResponseHash();
    }

    /**
     * Mark cache entry as invalidated.
     */
    public function invalidate(): void
    {
        $this->update(['status' => 'invalidated']);
    }

    /**
     * Generate a cache key for the given parameters.
     */
    public static function generateKey(
        string $apiSource, 
        string $endpoint, 
        array $params = [], 
        ?string $resourceId = null
    ): string {
        $paramString = empty($params) ? '' : md5(json_encode(ksort($params) ? $params : $params));
        $components = [$apiSource, $endpoint, $paramString];
        if ($resourceId) {
            $components[] = $resourceId;
        }
        return implode(':', $components);
    }

    /**
     * Create or update a cache entry.
     */
    public static function store(
        string $apiSource,
        string $endpoint,
        string $resourceType,
        mixed $responseData,
        array $params = [],
        ?string $resourceId = null,
        int $ttlSeconds = 3600,
        array $metadata = [],
        int $apiCallCost = 1
    ): self {
        $cacheKey = self::generateKey($apiSource, $endpoint, $params, $resourceId);
        $responseDataJson = is_string($responseData) ? $responseData : json_encode($responseData);
        $responseHash = hash('sha256', $responseDataJson);
        $responseSize = strlen($responseDataJson);
        $expiresAt = now()->addSeconds($ttlSeconds);

        return self::updateOrCreate(
            ['cache_key' => $cacheKey],
            [
                'api_source' => $apiSource,
                'endpoint' => $endpoint,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'request_params' => $params,
                'response_data' => $responseData,
                'response_hash' => $responseHash,
                'response_size' => $responseSize,
                'metadata' => $metadata,
                'expires_at' => $expiresAt,
                'api_call_cost' => $apiCallCost,
                'status' => 'active',
                'hit_count' => 0,
                'last_accessed_at' => now(),
                'cache_efficiency' => 0,
            ]
        );
    }

    /**
     * Retrieve a valid cache entry.
     */
    public static function retrieve(
        string $apiSource, 
        string $endpoint, 
        array $params = [], 
        ?string $resourceId = null
    ): ?self {
        $cacheKey = self::generateKey($apiSource, $endpoint, $params, $resourceId);
        
        $cache = self::where('cache_key', $cacheKey)
            ->valid()
            ->first();

        if ($cache && $cache->verifyIntegrity()) {
            $cache->recordHit();
            return $cache;
        }

        // If integrity check fails, invalidate the cache
        if ($cache) {
            $cache->invalidate();
        }

        return null;
    }

    /**
     * Clean up expired cache entries.
     */
    public static function cleanup(): int
    {
        return self::expired()->delete();
    }

    /**
     * Bulk invalidate cache by criteria.
     */
    public static function invalidateBy(array $criteria): int
    {
        $query = self::query();
        
        foreach ($criteria as $field => $value) {
            if (in_array($field, ['api_source', 'resource_type', 'resource_id', 'endpoint'])) {
                $query->where($field, $value);
            }
        }
        
        return $query->update(['status' => 'invalidated']);
    }

    /**
     * Get cache statistics.
     */
    public static function getStats(): array
    {
        $totalHits = self::sum('hit_count');
        $totalRequests = self::count() + $totalHits; // Approximate total requests
        
        return [
            'total_entries' => self::count(),
            'valid_entries' => self::valid()->count(),
            'expired_entries' => self::expired()->count(),
            'active_entries' => self::active()->count(),
            'api_sources' => self::distinct('api_source')->pluck('api_source')->toArray(),
            'resource_types' => self::distinct('resource_type')->pluck('resource_type')->toArray(),
            'total_hits' => $totalHits,
            'cache_hit_ratio' => $totalRequests > 0 ? round((float)($totalHits / $totalRequests) * 100, 2) : 0,
            'most_accessed' => self::orderBy('hit_count', 'desc')->limit(10)
                ->get(['cache_key', 'hit_count', 'api_source', 'resource_type']),
            'cache_size_mb' => round((float)(self::sum('response_size')) / 1024 / 1024, 2),
            'api_cost_saved' => self::sum('api_call_cost') * self::sum('hit_count'),
            'efficiency_avg' => round((float)(self::where('hit_count', '>', 0)->avg('cache_efficiency') ?? 0), 2),
        ];
    }

    /**
     * Get cache entries for specific API source with statistics.
     */
    public static function getStatsForApiSource(string $apiSource): array
    {
        $baseQuery = self::forApiSource($apiSource);
        
        return [
            'api_source' => $apiSource,
            'total_entries' => $baseQuery->count(),
            'valid_entries' => $baseQuery->valid()->count(),
            'total_hits' => $baseQuery->sum('hit_count'),
            'cache_size_mb' => round((float)($baseQuery->sum('response_size')) / 1024 / 1024, 2),
            'resource_types' => $baseQuery->distinct()->pluck('resource_type')->toArray(),
            'most_accessed' => self::forApiSource($apiSource)
                ->orderBy('hit_count', 'desc')
                ->limit(5)
                ->get(['cache_key', 'hit_count', 'resource_type', 'resource_id']),
        ];
    }
}