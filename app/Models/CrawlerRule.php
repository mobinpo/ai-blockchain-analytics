<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

final class CrawlerRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'active',
        'priority',
        'platforms',
        'platform_configs',
        'keywords',
        'hashtags',
        'accounts',
        'exclude_keywords',
        'sentiment_threshold',
        'engagement_threshold',
        'follower_threshold',
        'language',
        'filters',
        'geofencing',
        'start_date',
        'end_date',
        'max_posts_per_hour',
        'crawl_interval_minutes',
        'real_time',
        'total_posts_found',
        'total_posts_processed',
        'last_crawl_at',
        'last_crawl_stats',
        'performance_metrics',
        'user_id',
        'created_by',
    ];

    protected $casts = [
        'platforms' => 'array',
        'platform_configs' => 'array',
        'keywords' => 'array',
        'hashtags' => 'array',
        'accounts' => 'array',
        'exclude_keywords' => 'array',
        'filters' => 'array',
        'geofencing' => 'array',
        'last_crawl_stats' => 'array',
        'performance_metrics' => 'array',
        'active' => 'boolean',
        'real_time' => 'boolean',
        'sentiment_threshold' => 'decimal:2',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'last_crawl_at' => 'datetime',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function socialMediaPosts(): HasMany
    {
        return $this->hasMany(SocialMediaPost::class);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeByPlatform(Builder $query, string $platform): Builder
    {
        return $query->whereJsonContains('platforms', $platform);
    }

    public function scopeHighPriority(Builder $query): Builder
    {
        return $query->where('priority', '<=', 3)->orderBy('priority');
    }

    public function scopeDueCrawl(Builder $query): Builder
    {
        return $query->where('active', true)
            ->where(function ($q) {
                $q->whereNull('last_crawl_at')
                  ->orWhereRaw("last_crawl_at + (crawl_interval_minutes || ' minutes')::interval < NOW()");
            });
    }

    public function scopeInTimeWindow(Builder $query): Builder
    {
        $now = now();
        return $query->where(function ($q) use ($now) {
            $q->whereNull('start_date')->orWhere('start_date', '<=', $now);
        })->where(function ($q) use ($now) {
            $q->whereNull('end_date')->orWhere('end_date', '>=', $now);
        });
    }

    public function scopeForRealTime(Builder $query): Builder
    {
        return $query->where('real_time', true);
    }

    public function scopeForBatch(Builder $query): Builder
    {
        return $query->where('real_time', false);
    }

    // Content matching methods
    public function matchesContent(string $text, array $metadata = []): bool
    {
        // Check for excluded keywords first
        if ($this->hasExcludedKeywords($text)) {
            return false;
        }

        // Check if keywords match
        if (!$this->matchesKeywords($text)) {
            return false;
        }

        // Check engagement threshold
        if ($this->engagement_threshold && isset($metadata['engagement'])) {
            if ($metadata['engagement'] < $this->engagement_threshold) {
                return false;
            }
        }

        // Check follower threshold
        if ($this->follower_threshold && isset($metadata['follower_count'])) {
            if ($metadata['follower_count'] < $this->follower_threshold) {
                return false;
            }
        }

        // Check sentiment threshold
        if ($this->sentiment_threshold && isset($metadata['sentiment'])) {
            if ($metadata['sentiment'] < $this->sentiment_threshold) {
                return false;
            }
        }

        // Check language
        if ($this->language !== 'all' && isset($metadata['language'])) {
            if ($metadata['language'] !== $this->language) {
                return false;
            }
        }

        // Apply custom filters
        if ($this->filters && !$this->applyCustomFilters($text, $metadata)) {
            return false;
        }

        return true;
    }

    public function matchesKeywords(string $text): bool
    {
        if (empty($this->keywords)) {
            return true;
        }

        $text = strtolower($text);
        foreach ($this->keywords as $keyword) {
            if (strpos($text, strtolower($keyword)) !== false) {
                return true;
            }
        }
        return false;
    }

    public function hasExcludedKeywords(string $text): bool
    {
        if (empty($this->exclude_keywords)) {
            return false;
        }

        $text = strtolower($text);
        foreach ($this->exclude_keywords as $excluded) {
            if (strpos($text, strtolower($excluded)) !== false) {
                return true;
            }
        }
        return false;
    }

    public function getMatchedKeywords(string $text): array
    {
        $matched = [];
        if (empty($this->keywords)) {
            return $matched;
        }

        $text = strtolower($text);
        foreach ($this->keywords as $keyword) {
            if (strpos($text, strtolower($keyword)) !== false) {
                $matched[] = $keyword;
            }
        }
        return $matched;
    }

    public function getMatchedHashtags(string $text): array
    {
        $matched = [];
        if (empty($this->hashtags)) {
            return $matched;
        }

        $text = strtolower($text);
        foreach ($this->hashtags as $hashtag) {
            $tag = strtolower(ltrim($hashtag, '#'));
            if (strpos($text, "#{$tag}") !== false || strpos($text, $tag) !== false) {
                $matched[] = $hashtag;
            }
        }
        return $matched;
    }

    // Rate limiting methods
    public function canCrawlNow(): bool
    {
        if (!$this->active) {
            return false;
        }

        // Check time window
        $now = now();
        if ($this->start_date && $now->lt($this->start_date)) {
            return false;
        }
        if ($this->end_date && $now->gt($this->end_date)) {
            return false;
        }

        // Check crawl interval
        if ($this->last_crawl_at) {
            $nextCrawl = $this->last_crawl_at->addMinutes($this->crawl_interval_minutes);
            if ($now->lt($nextCrawl)) {
                return false;
            }
        }

        // Check hourly rate limit
        return $this->canCrawlWithinRateLimit();
    }

    public function canCrawlWithinRateLimit(): bool
    {
        if (!$this->max_posts_per_hour) {
            return true;
        }

        $oneHourAgo = now()->subHour();
        $recentPosts = $this->socialMediaPosts()
            ->where('created_at', '>=', $oneHourAgo)
            ->count();

        return $recentPosts < $this->max_posts_per_hour;
    }

    public function getRemainingHourlyQuota(): int
    {
        if (!$this->max_posts_per_hour) {
            return PHP_INT_MAX;
        }

        $oneHourAgo = now()->subHour();
        $recentPosts = $this->socialMediaPosts()
            ->where('created_at', '>=', $oneHourAgo)
            ->count();

        return max(0, $this->max_posts_per_hour - $recentPosts);
    }

    // Statistics and performance
    public function updateCrawlStats(array $stats): void
    {
        $this->update([
            'last_crawl_at' => now(),
            'last_crawl_stats' => $stats,
            'total_posts_found' => $this->total_posts_found + ($stats['posts_found'] ?? 0),
            'total_posts_processed' => $this->total_posts_processed + ($stats['posts_processed'] ?? 0),
        ]);
    }

    public function updatePerformanceMetrics(array $metrics): void
    {
        $existing = $this->performance_metrics ?? [];
        $updated = array_merge($existing, [
            'last_updated' => now()->toISOString(),
            'metrics' => $metrics,
        ]);

        $this->update(['performance_metrics' => $updated]);
    }

    public function getEfficiencyScore(): float
    {
        if ($this->total_posts_found === 0) {
            return 0.0;
        }

        return round(($this->total_posts_processed / $this->total_posts_found) * 100, 2);
    }

    public function getPlatformConfig(string $platform): array
    {
        return $this->platform_configs[$platform] ?? [];
    }

    // Custom filter application
    private function applyCustomFilters(string $text, array $metadata): bool
    {
        if (empty($this->filters)) {
            return true;
        }

        foreach ($this->filters as $filter) {
            $type = $filter['type'] ?? '';
            $value = $filter['value'] ?? '';
            $operator = $filter['operator'] ?? 'equals';

            switch ($type) {
                case 'text_length':
                    $textLength = strlen($text);
                    if (!$this->compareValues($textLength, $value, $operator)) {
                        return false;
                    }
                    break;

                case 'word_count':
                    $wordCount = str_word_count($text);
                    if (!$this->compareValues($wordCount, $value, $operator)) {
                        return false;
                    }
                    break;

                case 'metadata':
                    $field = $filter['field'] ?? '';
                    $metaValue = $metadata[$field] ?? null;
                    if ($metaValue === null || !$this->compareValues($metaValue, $value, $operator)) {
                        return false;
                    }
                    break;

                case 'regex':
                    if (!preg_match($value, $text)) {
                        return false;
                    }
                    break;
            }
        }

        return true;
    }

    private function compareValues($actual, $expected, string $operator): bool
    {
        return match ($operator) {
            'equals' => $actual == $expected,
            'not_equals' => $actual != $expected,
            'greater_than' => $actual > $expected,
            'less_than' => $actual < $expected,
            'greater_equal' => $actual >= $expected,
            'less_equal' => $actual <= $expected,
            'contains' => strpos(strtolower($actual), strtolower($expected)) !== false,
            'not_contains' => strpos(strtolower($actual), strtolower($expected)) === false,
            default => true,
        };
    }

    // Platform-specific methods
    public function supportsTwitter(): bool
    {
        return in_array('twitter', $this->platforms ?? []);
    }

    public function supportsReddit(): bool
    {
        return in_array('reddit', $this->platforms ?? []);
    }

    public function supportsTelegram(): bool
    {
        return in_array('telegram', $this->platforms ?? []);
    }
}
