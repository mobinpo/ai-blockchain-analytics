<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class SocialPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_id',
        'platform',
        'source_url',
        'author_username',
        'author_display_name',
        'author_id',
        'content',
        'media_urls',
        'engagement_count',
        'share_count',
        'comment_count',
        'published_at',
        'raw_data',
        'matched_keywords',
        'sentiment_analysis',
        'relevance_score',
        'is_processed',
        'is_relevant',
    ];

    protected $casts = [
        'media_urls' => 'array',
        'published_at' => 'datetime',
        'raw_data' => 'array',
        'matched_keywords' => 'array',
        'sentiment_analysis' => 'array',
        'relevance_score' => 'decimal:3',
        'is_processed' => 'boolean',
        'is_relevant' => 'boolean',
        'engagement_count' => 'integer',
        'share_count' => 'integer',
        'comment_count' => 'integer',
    ];

    public function keywordMatches(): HasMany
    {
        return $this->hasMany(KeywordMatch::class);
    }

    public function matchedRules(): BelongsToMany
    {
        return $this->belongsToMany(KeywordRule::class, 'keyword_matches')
            ->withPivot(['matched_keyword', 'match_count', 'confidence_score'])
            ->withTimestamps();
    }

    // Scopes for filtering
    public function scopePlatform(Builder $query, string $platform): Builder
    {
        return $query->where('platform', $platform);
    }

    public function scopeRelevant(Builder $query, bool $relevant = true): Builder
    {
        return $query->where('is_relevant', $relevant);
    }

    public function scopeProcessed(Builder $query, bool $processed = true): Builder
    {
        return $query->where('is_processed', $processed);
    }

    public function scopeByAuthor(Builder $query, string $username): Builder
    {
        return $query->where('author_username', $username);
    }

    public function scopePublishedBetween(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->whereBetween('published_at', [$start, $end]);
    }

    public function scopeHighEngagement(Builder $query, int $threshold = 100): Builder
    {
        return $query->where('engagement_count', '>=', $threshold);
    }

    public function scopeWithKeywords(Builder $query, array $keywords): Builder
    {
        return $query->whereHas('keywordMatches', function (Builder $subQuery) use ($keywords) {
            $subQuery->whereIn('matched_keyword', $keywords);
        });
    }

    public function scopeRelevanceScore(Builder $query, float $minScore = 0.5): Builder
    {
        return $query->where('relevance_score', '>=', $minScore);
    }

    // Accessors and mutators
    public function getSentimentAttribute(): ?string
    {
        if (!$this->sentiment_analysis) {
            return null;
        }

        $scores = $this->sentiment_analysis;
        $maxScore = max($scores);
        
        return array_search($maxScore, $scores, true);
    }

    public function getSentimentScoreAttribute(): ?float
    {
        if (!$this->sentiment_analysis) {
            return null;
        }

        // Convert to -1 to 1 scale (negative, neutral, positive)
        $positive = $this->sentiment_analysis['positive'] ?? 0;
        $negative = $this->sentiment_analysis['negative'] ?? 0;
        
        return $positive - $negative;
    }

    public function getTotalEngagementAttribute(): int
    {
        return $this->engagement_count + $this->share_count + $this->comment_count;
    }

    public function getEngagementRateAttribute(): float
    {
        // Simple engagement rate calculation
        $followerEstimate = $this->estimateFollowerCount();
        
        return $followerEstimate > 0 ? ($this->total_engagement / $followerEstimate) : 0;
    }

    public function hasMedia(): bool
    {
        return !empty($this->media_urls);
    }

    public function getMediaCount(): int
    {
        return count($this->media_urls ?? []);
    }

    public function containsKeywords(array $keywords, bool $caseSensitive = false): bool
    {
        $content = $caseSensitive ? $this->content : strtolower($this->content);
        
        foreach ($keywords as $keyword) {
            $searchKeyword = $caseSensitive ? $keyword : strtolower($keyword);
            if (str_contains($content, $searchKeyword)) {
                return true;
            }
        }
        
        return false;
    }

    public function extractMentions(): array
    {
        preg_match_all('/@(\w+)/', $this->content, $matches);
        return $matches[1] ?? [];
    }

    public function extractHashtags(): array
    {
        preg_match_all('/#(\w+)/', $this->content, $matches);
        return $matches[1] ?? [];
    }

    public function extractUrls(): array
    {
        preg_match_all('/https?:\/\/[^\s]+/', $this->content, $matches);
        return $matches[0] ?? [];
    }

    // Utility methods
    public function markAsProcessed(bool $processed = true): bool
    {
        return $this->update(['is_processed' => $processed]);
    }

    public function setRelevance(bool $relevant, float $score = null): bool
    {
        $data = ['is_relevant' => $relevant];
        
        if ($score !== null) {
            $data['relevance_score'] = $score;
        }
        
        return $this->update($data);
    }

    public function addSentimentAnalysis(array $analysis): bool
    {
        return $this->update(['sentiment_analysis' => $analysis]);
    }

    public function incrementEngagement(string $type, int $count = 1): bool
    {
        $field = match($type) {
            'like', 'upvote', 'reaction' => 'engagement_count',
            'share', 'retweet' => 'share_count',
            'comment', 'reply' => 'comment_count',
            default => null
        };
        
        if (!$field) {
            return false;
        }
        
        return $this->increment($field, $count);
    }

    // Platform-specific methods
    public function getTwitterUrl(): ?string
    {
        if ($this->platform !== 'twitter') {
            return null;
        }
        
        return "https://twitter.com/{$this->author_username}/status/{$this->external_id}";
    }

    public function getRedditUrl(): ?string
    {
        if ($this->platform !== 'reddit') {
            return null;
        }
        
        return $this->source_url;
    }

    public function getTelegramUrl(): ?string
    {
        if ($this->platform !== 'telegram') {
            return null;
        }
        
        return $this->source_url;
    }

    // Analytics methods
    public static function getTrendingKeywords(string $platform = null, int $days = 7): array
    {
        $query = self::query()
            ->selectRaw('
                keyword_matches.matched_keyword as keyword,
                COUNT(*) as mention_count,
                AVG(social_posts.engagement_count) as avg_engagement,
                AVG(social_posts.relevance_score) as avg_relevance
            ')
            ->join('keyword_matches', 'social_posts.id', '=', 'keyword_matches.social_post_id')
            ->where('social_posts.published_at', '>=', now()->subDays($days))
            ->groupBy('keyword_matches.matched_keyword')
            ->orderByDesc('mention_count');
            
        if ($platform) {
            $query->where('social_posts.platform', $platform);
        }
        
        return $query->limit(50)->get()->toArray();
    }

    public static function getEngagementStats(string $platform = null, int $days = 7): array
    {
        $query = self::query()
            ->selectRaw('
                DATE(published_at) as date,
                COUNT(*) as post_count,
                AVG(engagement_count) as avg_engagement,
                MAX(engagement_count) as max_engagement,
                SUM(engagement_count) as total_engagement
            ')
            ->where('published_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date');
            
        if ($platform) {
            $query->where('platform', $platform);
        }
        
        return $query->get()->toArray();
    }

    private function estimateFollowerCount(): int
    {
        // Simple heuristic based on engagement patterns
        // In real implementation, this could be stored separately
        return max(100, $this->engagement_count * 10);
    }

    // Search functionality
    public function scopeFullTextSearch(Builder $query, string $searchTerm): Builder
    {
        return $query->whereRaw(
            "MATCH(content, author_username) AGAINST(? IN NATURAL LANGUAGE MODE)",
            [$searchTerm]
        );
    }

    public function scopeAdvancedSearch(Builder $query, array $criteria): Builder
    {
        if (isset($criteria['keywords'])) {
            $query->whereHas('keywordMatches', function (Builder $q) use ($criteria) {
                $q->whereIn('matched_keyword', (array) $criteria['keywords']);
            });
        }
        
        if (isset($criteria['authors'])) {
            $query->whereIn('author_username', (array) $criteria['authors']);
        }
        
        if (isset($criteria['platforms'])) {
            $query->whereIn('platform', (array) $criteria['platforms']);
        }
        
        if (isset($criteria['min_engagement'])) {
            $query->where('engagement_count', '>=', $criteria['min_engagement']);
        }
        
        if (isset($criteria['date_from'])) {
            $query->where('published_at', '>=', $criteria['date_from']);
        }
        
        if (isset($criteria['date_to'])) {
            $query->where('published_at', '<=', $criteria['date_to']);
        }
        
        if (isset($criteria['sentiment'])) {
            $query->whereNotNull('sentiment_analysis')
                ->whereRaw("JSON_EXTRACT(sentiment_analysis, '$.{$criteria['sentiment']}') > 0.5");
        }
        
        return $query;
    }
}