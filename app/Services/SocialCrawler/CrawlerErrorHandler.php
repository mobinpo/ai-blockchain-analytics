<?php

namespace App\Services\SocialCrawler;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class CrawlerErrorHandler
{
    protected array $retryableErrors = [
        'twitter' => [
            'rate limit exceeded',
            'timeout',
            'temporarily unavailable',
            'internal server error',
            'bad gateway',
            'service unavailable',
            'gateway timeout',
        ],
        'reddit' => [
            'rate limit exceeded',
            'timeout',
            'service unavailable',
            'internal server error',
            'bad gateway',
            'gateway timeout',
        ],
        'telegram' => [
            'too many requests',
            'timeout',
            'internal server error',
            'bad gateway',
            'service unavailable',
            'flood wait',
        ],
    ];

    protected array $alertThresholds = [
        'error_rate' => 0.5, // 50% error rate
        'consecutive_failures' => 5,
        'errors_per_hour' => 50,
    ];

    public function handleError(\Throwable $error, string $platform, string $operation, array $context = []): bool
    {
        $errorData = [
            'platform' => $platform,
            'operation' => $operation,
            'message' => $error->getMessage(),
            'code' => $error->getCode(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'context' => $context,
            'timestamp' => now(),
        ];

        $this->recordError($errorData);

        $isRetryable = $this->isRetryableError($error, $platform);
        $shouldAlert = $this->shouldTriggerAlert($platform, $operation);

        Log::error('Social crawler error', array_merge($errorData, [
            'retryable' => $isRetryable,
            'should_alert' => $shouldAlert,
        ]));

        if ($shouldAlert) {
            $this->triggerAlert($errorData);
        }

        return $isRetryable;
    }

    public function isRetryableError(\Throwable $error, string $platform): bool
    {
        $message = strtolower($error->getMessage());
        $retryableMessages = $this->retryableErrors[$platform] ?? [];

        foreach ($retryableMessages as $retryableMessage) {
            if (str_contains($message, strtolower($retryableMessage))) {
                return true;
            }
        }

        // Check HTTP status codes for retryable errors
        $code = $error->getCode();
        $retryableCodes = [429, 500, 502, 503, 504, 408, 520, 521, 522, 523, 524];
        
        return in_array($code, $retryableCodes);
    }

    public function getRetryDelay(int $attempt, string $platform, \Throwable $error = null): int
    {
        // Special handling for rate limit errors
        if ($error && $this->isRateLimitError($error)) {
            return $this->getRateLimitDelay($error, $platform);
        }

        // Exponential backoff with platform-specific base delays
        $baseDelay = match($platform) {
            'twitter' => 60,   // 1 minute
            'reddit' => 60,    // 1 minute  
            'telegram' => 180, // 3 minutes
            default => 60,
        };

        $delay = $baseDelay * pow(2, min($attempt - 1, 6)); // Max 2^6 = 64x base delay
        $jitter = rand(0, $delay * 0.1); // Add 10% jitter

        return (int) min($delay + $jitter, 3600); // Max 1 hour delay
    }

    public function recordError(array $errorData): void
    {
        $platform = $errorData['platform'];
        $operation = $errorData['operation'];
        
        // Increment error counters
        $this->incrementErrorCounter($platform, $operation);
        
        // Store recent errors for analysis
        $this->storeRecentError($errorData);
    }

    public function getErrorStats(string $platform, int $hours = 24): array
    {
        $key = "crawler_errors:{$platform}:stats";
        
        return Cache::remember($key, 300, function () use ($platform, $hours) {
            $errors = $this->getRecentErrors($platform, $hours);
            
            $stats = [
                'total_errors' => count($errors),
                'error_rate' => 0,
                'consecutive_failures' => 0,
                'most_common_errors' => [],
                'error_trend' => [],
            ];

            if (empty($errors)) {
                return $stats;
            }

            // Calculate error rate (errors per hour)
            $stats['error_rate'] = count($errors) / $hours;

            // Count consecutive failures
            $stats['consecutive_failures'] = $this->getConsecutiveFailures($platform);

            // Most common error messages
            $errorMessages = array_column($errors, 'message');
            $errorCounts = array_count_values($errorMessages);
            arsort($errorCounts);
            $stats['most_common_errors'] = array_slice($errorCounts, 0, 5, true);

            // Error trend (errors per hour)
            $stats['error_trend'] = $this->calculateErrorTrend($errors, $hours);

            return $stats;
        });
    }

    protected function isRateLimitError(\Throwable $error): bool
    {
        $message = strtolower($error->getMessage());
        $rateLimitKeywords = ['rate limit', 'too many requests', 'flood wait'];
        
        foreach ($rateLimitKeywords as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }

        return $error->getCode() === 429;
    }

    protected function getRateLimitDelay(\Throwable $error, string $platform): int
    {
        $message = $error->getMessage();
        
        // Try to extract delay from error message
        if (preg_match('/wait (\d+) seconds?/i', $message, $matches)) {
            return (int) $matches[1];
        }
        
        if (preg_match('/retry after (\d+)/i', $message, $matches)) {
            return (int) $matches[1];
        }

        // Default delays by platform
        return match($platform) {
            'twitter' => 900,  // 15 minutes
            'reddit' => 600,   // 10 minutes
            'telegram' => 1800, // 30 minutes
            default => 600,
        };
    }

    protected function incrementErrorCounter(string $platform, string $operation): void
    {
        $hourKey = "crawler_errors:{$platform}:{$operation}:" . date('Y-m-d-H');
        $dayKey = "crawler_errors:{$platform}:{$operation}:" . date('Y-m-d');
        
        Cache::increment($hourKey, 1);
        Cache::increment($dayKey, 1);
        
        // Set TTL for cleanup
        Cache::put($hourKey, Cache::get($hourKey), 3600);
        Cache::put($dayKey, Cache::get($dayKey), 86400);
    }

    protected function storeRecentError(array $errorData): void
    {
        $key = "crawler_recent_errors:{$errorData['platform']}";
        $errors = Cache::get($key, []);
        
        array_unshift($errors, $errorData);
        $errors = array_slice($errors, 0, 100); // Keep last 100 errors
        
        Cache::put($key, $errors, 86400); // 24 hours
    }

    protected function getRecentErrors(string $platform, int $hours): array
    {
        $key = "crawler_recent_errors:{$platform}";
        $errors = Cache::get($key, []);
        
        $cutoff = now()->subHours($hours);
        
        return array_filter($errors, function($error) use ($cutoff) {
            return \Carbon\Carbon::parse($error['timestamp'])->isAfter($cutoff);
        });
    }

    protected function getConsecutiveFailures(string $platform): int
    {
        $key = "crawler_consecutive_failures:{$platform}";
        return Cache::get($key, 0);
    }

    protected function resetConsecutiveFailures(string $platform): void
    {
        $key = "crawler_consecutive_failures:{$platform}";
        Cache::forget($key);
    }

    protected function incrementConsecutiveFailures(string $platform): int
    {
        $key = "crawler_consecutive_failures:{$platform}";
        $count = Cache::increment($key, 1);
        Cache::put($key, $count, 86400); // 24 hours TTL
        
        return $count;
    }

    protected function shouldTriggerAlert(string $platform, string $operation): bool
    {
        $stats = $this->getErrorStats($platform, 1); // Last hour
        
        // Check various alert conditions
        if ($stats['error_rate'] >= $this->alertThresholds['error_rate']) {
            return true;
        }
        
        if ($stats['consecutive_failures'] >= $this->alertThresholds['consecutive_failures']) {
            return true;
        }

        if ($stats['total_errors'] >= $this->alertThresholds['errors_per_hour']) {
            return true;
        }

        return false;
    }

    protected function triggerAlert(array $errorData): void
    {
        $platform = $errorData['platform'];
        $stats = $this->getErrorStats($platform, 1);

        $alertData = [
            'platform' => $platform,
            'operation' => $errorData['operation'],
            'error_message' => $errorData['message'],
            'error_rate' => $stats['error_rate'],
            'consecutive_failures' => $stats['consecutive_failures'],
            'total_errors_last_hour' => $stats['total_errors'],
            'timestamp' => $errorData['timestamp'],
        ];

        Log::alert('Social crawler error threshold exceeded', $alertData);

        // Store alert to prevent spam
        $alertKey = "crawler_alert_sent:{$platform}:" . date('Y-m-d-H');
        if (!Cache::has($alertKey)) {
            Cache::put($alertKey, true, 3600); // 1 hour cooldown
            
            // Here you would integrate with your notification system
            // Example: Notification::route('slack', config('alerts.slack.webhook'))
            //              ->notify(new CrawlerErrorAlert($alertData));
        }
    }

    protected function calculateErrorTrend(array $errors, int $hours): array
    {
        $trend = [];
        $now = now();
        
        for ($i = $hours - 1; $i >= 0; $i--) {
            $hourStart = $now->copy()->subHours($i);
            $hourEnd = $hourStart->copy()->addHour();
            
            $hourlyErrors = array_filter($errors, function($error) use ($hourStart, $hourEnd) {
                $errorTime = \Carbon\Carbon::parse($error['timestamp']);
                return $errorTime->between($hourStart, $hourEnd);
            });
            
            $trend[$hourStart->format('H:00')] = count($hourlyErrors);
        }
        
        return $trend;
    }

    public function onSuccessfulCrawl(string $platform): void
    {
        $this->resetConsecutiveFailures($platform);
    }

    public function onFailedCrawl(string $platform): void
    {
        $this->incrementConsecutiveFailures($platform);
    }
}