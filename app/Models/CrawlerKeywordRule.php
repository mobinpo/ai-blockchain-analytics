<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class CrawlerKeywordRule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'keywords',
        'platforms',
        'rule_type',
        'priority',
        'is_active',
        'category',
        'sentiment_filter',
        'language_filter',
        'date_range_start',
        'date_range_end',
        'min_engagement',
        'max_results',
        'regex_pattern',
        'exclude_keywords',
        'user_filters',
        'content_filters',
        'schedule_config',
        'webhook_url',
        'notification_settings',
        'metadata'
    ];

    protected $casts = [
        'keywords' => 'array',
        'platforms' => 'array',
        'conditions' => 'array',
        'sentiment_filter' => 'array',
        'is_active' => 'boolean',
        'schedule' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get posts collected by this rule.
     */
    public function posts(): HasMany
    {
        return $this->hasMany(SocialMediaPost::class, 'keyword_rule_id');
    }

    /**
     * Scope for active rules.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for rules by platform.
     */
    public function scopeForPlatform($query, string $platform)
    {
        return $query->whereJsonContains('platforms', $platform);
    }

    /**
     * Scope for rules by priority.
     */
    public function scopeByPriority($query, string $priority = 'high')
    {
        return $query->where('priority', $priority);
    }

    /**
     * Check if rule should run now based on schedule.
     */
    public function shouldRunNow(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $schedule = $this->schedule;
        
        if (empty($schedule)) {
            return true; // Run immediately if no schedule
        }

        $now = Carbon::now();
        
        // Check if we're within the scheduled time window
        if (isset($schedule['hours'])) {
            $currentHour = $now->hour;
            if (!in_array($currentHour, $schedule['hours'])) {
                return false;
            }
        }

        if (isset($schedule['days_of_week'])) {
            $currentDay = $now->dayOfWeek; // 0 = Sunday, 6 = Saturday
            if (!in_array($currentDay, $schedule['days_of_week'])) {
                return false;
            }
        }

        if (isset($schedule['interval_minutes'])) {
            $lastRun = $this->updated_at;
            $minutesSinceLastRun = $lastRun->diffInMinutes($now);
            
            if ($minutesSinceLastRun < $schedule['interval_minutes']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get formatted keywords for display.
     */
    public function getFormattedKeywordsAttribute(): string
    {
        return implode(', ', $this->keywords ?? []);
    }

    /**
     * Get formatted platforms for display.
     */
    public function getFormattedPlatformsAttribute(): string
    {
        return implode(', ', $this->platforms ?? []);
    }

    /**
     * Get rule configuration for crawler.
     */
    public function toCrawlerConfig(): array
    {
        return [
            'rule_id' => $this->id,
            'name' => $this->name,
            'keywords' => $this->keywords,
            'platforms' => $this->platforms,
            'conditions' => $this->conditions ?? [],
            'sentiment_filter' => $this->sentiment_filter ?? [],
            'max_posts' => $this->max_posts_per_run ?? 100,
            'priority' => $this->priority ?? 'normal'
        ];
    }

    /**
     * Create default keyword rules.
     */
    public static function createDefaults(): void
    {
        $defaultRules = [
            [
                'name' => 'Blockchain Security',
                'keywords' => ['smart contract', 'vulnerability', 'hack', 'exploit', 'security audit'],
                'platforms' => ['twitter', 'reddit'],
                'priority' => 'high',
                'max_posts_per_run' => 200,
                'conditions' => [
                    'min_engagement' => 10,
                    'exclude_retweets' => true
                ],
                'sentiment_filter' => [
                    'include' => ['negative', 'neutral', 'positive']
                ],
                'schedule' => [
                    'interval_minutes' => 30
                ]
            ],
            [
                'name' => 'DeFi Protocols',
                'keywords' => ['defi', 'yield farming', 'liquidity pool', 'flashloan', 'uniswap', 'aave'],
                'platforms' => ['twitter', 'reddit', 'telegram'],
                'priority' => 'medium',
                'max_posts_per_run' => 150,
                'conditions' => [
                    'min_followers' => 100
                ],
                'schedule' => [
                    'interval_minutes' => 60
                ]
            ],
            [
                'name' => 'Cryptocurrency News',
                'keywords' => ['bitcoin', 'ethereum', 'crypto', 'blockchain', 'nft'],
                'platforms' => ['twitter', 'reddit'],
                'priority' => 'low',
                'max_posts_per_run' => 100,
                'schedule' => [
                    'hours' => [9, 12, 15, 18], // 4 times a day
                    'days_of_week' => [1, 2, 3, 4, 5] // Weekdays only
                ]
            ]
        ];

        foreach ($defaultRules as $rule) {
            $rule['is_active'] = true;
            $rule['created_by'] = 'system';
            $rule['created_at'] = now();
            $rule['updated_at'] = now();
            
            self::create($rule);
        }
    }
}