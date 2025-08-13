<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialMediaPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'platform',
        'platform_id', 
        'author_username',
        'author_id',
        'content',
        'metadata',
        'url',
        'published_at',
        'engagement_score',
        'sentiment_score',
        'sentiment_label',
        'matched_keywords',
    ];

    protected $casts = [
        'metadata' => 'array',
        'matched_keywords' => 'array',
        'published_at' => 'datetime',
        'sentiment_score' => 'decimal:2',
    ];

    public function keywordMatches(): HasMany
    {
        return $this->hasMany(KeywordMatch::class);
    }

    public function scopeByPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopeRecentPosts($query, int $hours = 24)
    {
        return $query->where('published_at', '>=', now()->subHours($hours));
    }

    public function scopeWithSentiment($query, string $sentiment)
    {
        return $query->where('sentiment_label', $sentiment);
    }

    public function scopeByKeyword($query, string $keyword)
    {
        return $query->whereJsonContains('matched_keywords', $keyword);
    }

    public function getEngagementLevelAttribute(): string
    {
        return match (true) {
            $this->engagement_score >= 1000 => 'viral',
            $this->engagement_score >= 100 => 'high',
            $this->engagement_score >= 10 => 'medium',
            default => 'low'
        };
    }

    public function getSentimentColorAttribute(): string
    {
        return match ($this->sentiment_label) {
            'positive' => 'green',
            'negative' => 'red',
            'neutral' => 'gray',
            default => 'gray'
        };
    }
}