<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

final class DailySentimentAggregate extends Model
{
    use HasFactory;

    protected $table = 'daily_sentiment_aggregates';

    protected $fillable = [
        'aggregate_date',
        'platform',
        'keyword_category',
        'total_posts',
        'analyzed_posts',
        'avg_sentiment_score',
        'avg_magnitude',
        'positive_count',
        'negative_count',
        'neutral_count',
        'unknown_count',
        'positive_percentage',
        'negative_percentage',
        'neutral_percentage',
        'hourly_distribution',
        'top_keywords',
        'metadata',
        'processed_at',
    ];

    protected $casts = [
        'aggregate_date' => 'date',
        'total_posts' => 'integer',
        'analyzed_posts' => 'integer',
        'avg_sentiment_score' => 'decimal:4',
        'avg_magnitude' => 'decimal:4',
        'positive_count' => 'integer',
        'negative_count' => 'integer',
        'neutral_count' => 'integer',
        'unknown_count' => 'integer',
        'positive_percentage' => 'decimal:2',
        'negative_percentage' => 'decimal:2',
        'neutral_percentage' => 'decimal:2',
        'hourly_distribution' => 'array',
        'top_keywords' => 'array',
        'metadata' => 'array',
        'processed_at' => 'datetime',
    ];

    // Scopes
    public function scopeForDate($query, Carbon $date)
    {
        return $query->where('aggregate_date', $date->toDateString());
    }

    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopeForKeyword($query, string $keyword)
    {
        return $query->where('keyword_category', $keyword);
    }


    public function scopeDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('aggregate_date', [
            $startDate->toDateString(),
            $endDate->toDateString()
        ]);
    }

    public function scopePositiveSentiment($query)
    {
        return $query->where('avg_sentiment_score', '>', 0.1);
    }

    public function scopeNegativeSentiment($query)
    {
        return $query->where('avg_sentiment_score', '<', -0.1);
    }

    public function scopeHighEngagement($query, int $threshold = 1000)
    {
        return $query->where('total_posts', '>=', $threshold);
    }

    // Accessors
    public function getSentimentLabelAttribute(): string
    {
        $sentiment = $this->avg_sentiment_score;
        
        return match(true) {
            $sentiment > 0.6 => 'very_positive',
            $sentiment > 0.2 => 'positive',
            $sentiment > -0.2 => 'neutral',
            $sentiment > -0.6 => 'negative',
            default => 'very_negative'
        };
    }

    public function getSentimentColorAttribute(): string
    {
        return match($this->sentiment_label) {
            'very_positive' => 'green',
            'positive' => 'lime',
            'neutral' => 'gray',
            'negative' => 'orange',
            'very_negative' => 'red',
            default => 'gray'
        };
    }

    public function getEngagementRateAttribute(): float
    {
        return 0.0; // No engagement data in current schema
    }

    public function getProcessingRateAttribute(): float
    {
        if ($this->total_posts === 0) {
            return 0.0;
        }

        return round(($this->analyzed_posts / $this->total_posts) * 100, 2);
    }

    public function getTotalSentimentPostsAttribute(): int
    {
        return $this->positive_count + $this->neutral_count + 
               $this->negative_count + $this->unknown_count;
    }

    public function getPositivePercentageAttribute(): float
    {
        return (float) $this->positive_percentage;
    }

    public function getNegativePercentageAttribute(): float
    {
        return (float) $this->negative_percentage;
    }

    public function getNeutralPercentageAttribute(): float
    {
        return (float) $this->neutral_percentage;
    }

    public function getIsHighVolumeAttribute(): bool
    {
        // Consider high volume if more than 100 posts per day
        return $this->total_posts > 100;
    }

    public function getIsVolatileAttribute(): bool
    {
        // Consider volatile based on sentiment magnitude
        return $this->avg_magnitude > 0.3;
    }

    public function getIsAnomalousAttribute(): bool
    {
        // Consider anomalous based on extreme sentiment scores
        return abs($this->avg_sentiment_score ?? 0) > 0.8;
    }

    // Helper methods
    public function getSentimentDistribution(): array
    {
        return [
            'positive' => $this->positive_count,
            'neutral' => $this->neutral_count,
            'negative' => $this->negative_count,
            'unknown' => $this->unknown_count,
        ];
    }

    public function getSentimentPercentages(): array
    {
        return [
            'positive' => (float) $this->positive_percentage,
            'neutral' => (float) $this->neutral_percentage,
            'negative' => (float) $this->negative_percentage,
            'unknown' => 100.0 - $this->positive_percentage - $this->neutral_percentage - $this->negative_percentage,
        ];
    }

    public function getTopKeywords(int $limit = 10): array
    {
        $keywords = $this->top_keywords ?? [];
        
        // Sort by count and take top N
        arsort($keywords);
        
        return array_slice($keywords, 0, $limit, true);
    }

    public function getTopEntities(int $limit = 10): array
    {
        // No entities data in current schema
        return [];
    }

    public function getLanguageBreakdown(): array
    {
        // No language distribution in current schema
        return ['en' => 100.0];
    }

    // Static methods for aggregation
    public static function createOrUpdateAggregate(array $data): self
    {
        $uniqueFields = [
            'aggregate_date',
            'platform',
            'keyword_category'
        ];

        $whereClause = array_filter(array_intersect_key($data, array_flip($uniqueFields)));

        return static::updateOrCreate($whereClause, $data);
    }

    public static function getDateRangeStats(Carbon $startDate, Carbon $endDate, string $platform = 'all'): array
    {
        $query = static::dateRange($startDate, $endDate);
        
        if ($platform !== 'all') {
            $query->forPlatform($platform);
        }

        $aggregates = $query->get();

        return [
            'total_days' => $aggregates->count(),
            'total_posts' => $aggregates->sum('total_posts'),
            'analyzed_posts' => $aggregates->sum('analyzed_posts'),
            'average_sentiment' => round($aggregates->avg('avg_sentiment_score') ?? 0, 3),
            'average_magnitude' => round($aggregates->avg('avg_magnitude') ?? 0, 3),
            'sentiment_range' => [
                'min' => $aggregates->min('avg_sentiment_score'),
                'max' => $aggregates->max('avg_sentiment_score'),
            ],
            'volume_range' => [
                'min' => $aggregates->min('total_posts'),
                'max' => $aggregates->max('total_posts'),
            ],
            'high_volume_days' => $aggregates->where('total_posts', '>', 100)->count(),
            'volatile_days' => $aggregates->where('avg_magnitude', '>', 0.3)->count(),
        ];
    }
}