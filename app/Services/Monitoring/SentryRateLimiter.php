<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Sentry\Event;
use Carbon\Carbon;

class SentryRateLimiter
{
    private const CACHE_PREFIX = 'sentry_rate_limit:';
    
    /**
     * Determine if a Sentry event should be sent based on rate limits.
     */
    public function shouldSend(Event $event): bool
    {
        if (!app()->environment('production')) {
            return true;
        }

        $maxPerMinute = config('sentry.ai_blockchain.rate_limiting.max_events_per_minute', 60);
        $maxPerHour = config('sentry.ai_blockchain.rate_limiting.max_events_per_hour', 200);

        // Check per-minute rate limit
        if (!$this->checkRateLimit('minute', $maxPerMinute)) {
            Log::warning('Sentry rate limit exceeded for minute', [
                'max_per_minute' => $maxPerMinute,
                'event_type' => $event->getType()
            ]);
            return false;
        }

        // Check per-hour rate limit
        if (!$this->checkRateLimit('hour', $maxPerHour)) {
            Log::warning('Sentry rate limit exceeded for hour', [
                'max_per_hour' => $maxPerHour,
                'event_type' => $event->getType()
            ]);
            return false;
        }

        // Check exception-specific rate limits
        if ($event->getType() === 'error') {
            $exceptionClass = $this->getExceptionClass($event);
            if ($exceptionClass && !$this->checkExceptionRateLimit($exceptionClass)) {
                return false;
            }
        }

        // Increment counters
        $this->incrementCounter('minute');
        $this->incrementCounter('hour');

        return true;
    }

    /**
     * Check rate limit for a given time window.
     */
    private function checkRateLimit(string $window, int $maxEvents): bool
    {
        $cacheKey = self::CACHE_PREFIX . $window;
        $current = Cache::get($cacheKey, 0);
        
        return $current < $maxEvents;
    }

    /**
     * Increment the counter for a given time window.
     */
    private function incrementCounter(string $window): void
    {
        $cacheKey = self::CACHE_PREFIX . $window;
        $ttl = match ($window) {
            'minute' => now()->addMinute(),
            'hour' => now()->addHour(),
            default => now()->addMinute(),
        };

        Cache::put($cacheKey, Cache::get($cacheKey, 0) + 1, $ttl);
    }

    /**
     * Check rate limit for specific exception types.
     */
    private function checkExceptionRateLimit(string $exceptionClass): bool
    {
        $cacheKey = self::CACHE_PREFIX . 'exception:' . md5($exceptionClass);
        $maxSameExceptionPerHour = 20; // Limit same exception type

        $current = Cache::get($cacheKey, 0);
        
        if ($current >= $maxSameExceptionPerHour) {
            Log::warning('Sentry exception rate limit exceeded', [
                'exception_class' => $exceptionClass,
                'current_count' => $current,
                'max_allowed' => $maxSameExceptionPerHour
            ]);
            return false;
        }

        Cache::put($cacheKey, $current + 1, now()->addHour());
        return true;
    }

    /**
     * Extract exception class from Sentry event.
     */
    private function getExceptionClass(Event $event): ?string
    {
        $exceptions = $event->getExceptions();
        return !empty($exceptions) ? $exceptions[0]->getType() : null;
    }

    /**
     * Get current rate limit statistics.
     */
    public function getStats(): array
    {
        return [
            'events_this_minute' => Cache::get(self::CACHE_PREFIX . 'minute', 0),
            'events_this_hour' => Cache::get(self::CACHE_PREFIX . 'hour', 0),
            'rate_limits' => [
                'max_per_minute' => config('sentry.ai_blockchain.rate_limiting.max_events_per_minute', 60),
                'max_per_hour' => config('sentry.ai_blockchain.rate_limiting.max_events_per_hour', 200),
            ],
        ];
    }

    /**
     * Reset rate limit counters (for testing/maintenance).
     */
    public function reset(): void
    {
        Cache::forget(self::CACHE_PREFIX . 'minute');
        Cache::forget(self::CACHE_PREFIX . 'hour');
        
        Log::info('Sentry rate limit counters reset');
    }
}