<?php

declare(strict_types=1);

namespace App\Services\SocialCrawler;

use App\Models\ApiRateLimit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RateLimitManager
{
    private array $platformLimits;
    private array $currentCounts;

    public function __construct()
    {
        $this->platformLimits = config('social_crawler.rate_limits', []);
        $this->currentCounts = [];
    }

    /**
     * Check if we can make a request to the given platform/endpoint
     */
    public function canMakeRequest(string $platform, string $endpoint): bool
    {
        $limits = $this->getPlatformLimits($platform);
        $endpointLimit = $limits['endpoints'][$endpoint] ?? $limits['default'] ?? null;

        if (!$endpointLimit) {
            return true; // No limit configured
        }

        $currentCount = $this->getCurrentCount($platform, $endpoint);
        $maxRequests = $endpointLimit['requests'];
        $windowMinutes = $endpointLimit['window_minutes'];

        // Check if we're within the limit
        if ($currentCount >= $maxRequests) {
            $resetTime = $this->getResetTime($platform, $endpoint);
            if ($resetTime && now()->lt($resetTime)) {
                return false;
            }
            
            // Window expired, reset counter
            $this->resetCount($platform, $endpoint);
        }

        return true;
    }

    /**
     * Record a successful API request
     */
    public function recordRequest(string $platform, string $endpoint): void
    {
        $this->incrementCount($platform, $endpoint);
        
        // Also record in database for analytics
        $this->recordInDatabase($platform, $endpoint, true);
    }

    /**
     * Handle rate limit response from API
     */
    public function handleRateLimit(string $platform, string $endpoint, array $headers = []): void
    {
        $resetTime = $this->extractResetTime($platform, $headers);
        
        // Mark as exceeded
        $this->setRateLimitExceeded($platform, $endpoint, $resetTime);
        
        // Record failed request
        $this->recordInDatabase($platform, $endpoint, false);
        
        Log::warning('Rate limit exceeded', [
            'platform' => $platform,
            'endpoint' => $endpoint,
            'reset_time' => $resetTime?->toISOString(),
        ]);
    }

    /**
     * Get current rate limit status for platform
     */
    public function getStatus(string $platform): array
    {
        $limits = $this->getPlatformLimits($platform);
        $status = [
            'platform' => $platform,
            'endpoints' => [],
            'global_limit' => $limits['default'] ?? null,
        ];

        foreach ($limits['endpoints'] ?? [] as $endpoint => $limit) {
            $currentCount = $this->getCurrentCount($platform, $endpoint);
            $resetTime = $this->getResetTime($platform, $endpoint);
            
            $status['endpoints'][$endpoint] = [
                'current_requests' => $currentCount,
                'max_requests' => $limit['requests'],
                'window_minutes' => $limit['window_minutes'],
                'reset_time' => $resetTime?->toISOString(),
                'requests_remaining' => max(0, $limit['requests'] - $currentCount),
                'is_exceeded' => $currentCount >= $limit['requests'],
            ];
        }

        return $status;
    }

    /**
     * Get platform-specific limits configuration
     */
    private function getPlatformLimits(string $platform): array
    {
        return $this->platformLimits[$platform] ?? [
            'default' => ['requests' => 100, 'window_minutes' => 60]
        ];
    }

    /**
     * Get current request count for platform/endpoint
     */
    private function getCurrentCount(string $platform, string $endpoint): int
    {
        $cacheKey = $this->getCacheKey($platform, $endpoint);
        return Cache::get($cacheKey, 0);
    }

    /**
     * Increment request count
     */
    private function incrementCount(string $platform, string $endpoint): void
    {
        $cacheKey = $this->getCacheKey($platform, $endpoint);
        $limits = $this->getPlatformLimits($platform);
        $endpointLimit = $limits['endpoints'][$endpoint] ?? $limits['default'];
        $windowMinutes = $endpointLimit['window_minutes'] ?? 60;
        
        $currentCount = Cache::get($cacheKey, 0);
        Cache::put($cacheKey, $currentCount + 1, now()->addMinutes($windowMinutes));
        
        // Also set reset time
        $resetCacheKey = $this->getResetCacheKey($platform, $endpoint);
        if (!Cache::has($resetCacheKey)) {
            Cache::put($resetCacheKey, now()->addMinutes($windowMinutes), now()->addMinutes($windowMinutes));
        }
    }

    /**
     * Reset request count for endpoint
     */
    private function resetCount(string $platform, string $endpoint): void
    {
        $cacheKey = $this->getCacheKey($platform, $endpoint);
        $resetCacheKey = $this->getResetCacheKey($platform, $endpoint);
        
        Cache::forget($cacheKey);
        Cache::forget($resetCacheKey);
    }

    /**
     * Get reset time for endpoint
     */
    private function getResetTime(string $platform, string $endpoint): ?Carbon
    {
        $resetCacheKey = $this->getResetCacheKey($platform, $endpoint);
        $resetTime = Cache::get($resetCacheKey);
        
        return $resetTime ? Carbon::parse($resetTime) : null;
    }

    /**
     * Mark rate limit as exceeded with reset time
     */
    private function setRateLimitExceeded(string $platform, string $endpoint, ?Carbon $resetTime): void
    {
        $cacheKey = $this->getCacheKey($platform, $endpoint);
        $resetCacheKey = $this->getResetCacheKey($platform, $endpoint);
        
        $limits = $this->getPlatformLimits($platform);
        $endpointLimit = $limits['endpoints'][$endpoint] ?? $limits['default'];
        $maxRequests = $endpointLimit['requests'] ?? 100;
        
        // Set count to max to block further requests
        $ttl = $resetTime ? now()->diffInMinutes($resetTime) : 60;
        Cache::put($cacheKey, $maxRequests, now()->addMinutes($ttl));
        
        if ($resetTime) {
            Cache::put($resetCacheKey, $resetTime, $resetTime);
        }
    }

    /**
     * Extract reset time from API response headers
     */
    private function extractResetTime(string $platform, array $headers): ?Carbon
    {
        switch ($platform) {
            case 'twitter':
                // Twitter uses x-rate-limit-reset (Unix timestamp)
                $resetHeader = $headers['x-rate-limit-reset'][0] ?? null;
                if ($resetHeader) {
                    return Carbon::createFromTimestamp((int) $resetHeader);
                }
                break;
                
            case 'reddit':
                // Reddit uses x-ratelimit-reset (seconds until reset)
                $resetHeader = $headers['x-ratelimit-reset'][0] ?? null;
                if ($resetHeader) {
                    return now()->addSeconds((int) $resetHeader);
                }
                break;
                
            case 'telegram':
                // Telegram uses retry-after header (seconds to wait)
                $retryAfter = $headers['retry-after'][0] ?? null;
                if ($retryAfter) {
                    return now()->addSeconds((int) $retryAfter);
                }
                break;
        }

        return null;
    }

    /**
     * Record request in database for analytics
     */
    private function recordInDatabase(string $platform, string $endpoint, bool $success): void
    {
        try {
            $limits = $this->getPlatformLimits($platform);
            $endpointLimit = $limits['endpoints'][$endpoint] ?? $limits['default'];
            $windowMinutes = $endpointLimit['window_minutes'] ?? 60;
            
            $windowStart = now()->startOfHour();
            $windowEnd = $windowStart->copy()->addMinutes($windowMinutes);
            
            $apiLimit = ApiRateLimit::firstOrCreate([
                'platform' => $platform,
                'endpoint' => $endpoint,
                'api_key_hash' => $this->getApiKeyHash($platform),
                'window_start' => $windowStart,
            ], [
                'requests_made' => 0,
                'requests_limit' => $endpointLimit['requests'] ?? 100,
                'window_end' => $windowEnd,
                'is_exceeded' => false,
            ]);

            $apiLimit->increment('requests_made');
            
            if (!$success || $apiLimit->requests_made >= $apiLimit->requests_limit) {
                $apiLimit->update([
                    'is_exceeded' => true,
                    'reset_at' => $this->extractResetTime($platform, []) ?? $windowEnd,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to record rate limit in database', [
                'platform' => $platform,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get hashed API key for identification (without exposing actual key)
     */
    private function getApiKeyHash(string $platform): string
    {
        $apiKey = match ($platform) {
            'twitter' => config('social_crawler.twitter.bearer_token'),
            'reddit' => config('social_crawler.reddit.client_id'),
            'telegram' => config('social_crawler.telegram.bot_token'),
            default => 'unknown',
        };

        return hash('sha256', $apiKey ?? 'no-key');
    }

    /**
     * Get cache key for request count
     */
    private function getCacheKey(string $platform, string $endpoint): string
    {
        return "rate_limit:{$platform}:{$endpoint}:count";
    }

    /**
     * Get cache key for reset time
     */
    private function getResetCacheKey(string $platform, string $endpoint): string
    {
        return "rate_limit:{$platform}:{$endpoint}:reset";
    }

    /**
     * Clear all rate limit data for platform
     */
    public function clearPlatformLimits(string $platform): void
    {
        $limits = $this->getPlatformLimits($platform);
        
        foreach (array_keys($limits['endpoints'] ?? []) as $endpoint) {
            $this->resetCount($platform, $endpoint);
        }
        
        Log::info('Cleared rate limits for platform', ['platform' => $platform]);
    }

    /**
     * Get remaining requests for endpoint
     */
    public function getRemainingRequests(string $platform, string $endpoint): int
    {
        $limits = $this->getPlatformLimits($platform);
        $endpointLimit = $limits['endpoints'][$endpoint] ?? $limits['default'];
        
        if (!$endpointLimit) {
            return -1; // Unlimited
        }
        
        $maxRequests = $endpointLimit['requests'];
        $currentCount = $this->getCurrentCount($platform, $endpoint);
        
        return max(0, $maxRequests - $currentCount);
    }

    /**
     * Get time until rate limit resets
     */
    public function getTimeUntilReset(string $platform, string $endpoint): ?int
    {
        $resetTime = $this->getResetTime($platform, $endpoint);
        
        if (!$resetTime) {
            return null;
        }
        
        return max(0, now()->diffInSeconds($resetTime));
    }

    /**
     * Estimate when next request can be made
     */
    public function getNextAvailableTime(string $platform, string $endpoint): ?Carbon
    {
        if ($this->canMakeRequest($platform, $endpoint)) {
            return now();
        }
        
        return $this->getResetTime($platform, $endpoint);
    }

    /**
     * Get rate limit statistics for analytics
     */
    public function getStatistics(int $days = 7): array
    {
        $startDate = now()->subDays($days);
        
        return [
            'total_requests' => ApiRateLimit::where('created_at', '>=', $startDate)
                ->sum('requests_made'),
            'exceeded_limits' => ApiRateLimit::where('created_at', '>=', $startDate)
                ->where('is_exceeded', true)
                ->count(),
            'by_platform' => ApiRateLimit::selectRaw('platform, SUM(requests_made) as total_requests, COUNT(*) as windows')
                ->where('created_at', '>=', $startDate)
                ->groupBy('platform')
                ->get()
                ->toArray(),
            'by_endpoint' => ApiRateLimit::selectRaw('platform, endpoint, SUM(requests_made) as total_requests, AVG(requests_made) as avg_requests')
                ->where('created_at', '>=', $startDate)
                ->groupBy(['platform', 'endpoint'])
                ->orderByDesc('total_requests')
                ->limit(20)
                ->get()
                ->toArray(),
        ];
    }
}