<?php

declare(strict_types=1);

namespace App\Services\SentimentPipeline;

use App\Models\DailySentimentAggregate;
use App\Models\SentimentBatchDocument;
use App\Models\SocialMediaPost;
use App\Models\KeywordMatch;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class DailySentimentAggregateService
{
    protected array $config;

    public function __construct()
    {
        $this->config = config('sentiment_pipeline.aggregation', []);
    }

    public function generateDailyAggregates(Carbon $date): array
    {
        Log::info('Starting daily sentiment aggregation', [
            'date' => $date->toDateString(),
        ]);

        $startTime = microtime(true);
        $results = [];

        // Generate aggregates for different combinations
        $platforms = $this->getPlatforms();
        $categories = $this->getKeywordCategories();
        $languages = $this->getLanguages();

        foreach ($platforms as $platform) {
            foreach ($categories as $category) {
                foreach ($languages as $language) {
                    // Full day aggregate
                    $aggregate = $this->generateAggregate($date, $platform, $category, null, $language);
                    if ($aggregate) {
                        $results[] = $aggregate;
                    }

                    // Hourly aggregates (if enabled)
                    if ($this->config['generate_hourly'] ?? false) {
                        for ($hour = 0; $hour < 24; $hour++) {
                            $hourlyAggregate = $this->generateAggregate($date, $platform, $category, $hour, $language);
                            if ($hourlyAggregate) {
                                $results[] = $hourlyAggregate;
                            }
                        }
                    }
                }
            }
        }

        $processingTime = microtime(true) - $startTime;

        Log::info('Daily sentiment aggregation completed', [
            'date' => $date->toDateString(),
            'aggregates_created' => count($results),
            'processing_time' => round($processingTime, 2),
        ]);

        return $results;
    }

    protected function generateAggregate(
        Carbon $date,
        string $platform,
        string $category,
        ?int $timeBucket,
        string $language
    ): ?DailySentimentAggregate {
        
        $data = $this->calculateAggregateData($date, $platform, $category, $timeBucket, $language);
        
        if (!$data || $data['total_posts'] === 0) {
            return null;
        }

        // Add comparison metrics
        $data = array_merge($data, $this->calculateComparisonMetrics($date, $platform, $category, $language));

        // Create or update aggregate
        return DailySentimentAggregate::createOrUpdateAggregate($data);
    }

    protected function calculateAggregateData(
        Carbon $date,
        string $platform,
        string $category,
        ?int $timeBucket,
        string $language
    ): ?array {
        
        // Build base query for sentiment documents
        $documentsQuery = $this->buildDocumentsQuery($date, $platform, $category, $timeBucket, $language);
        
        $documents = $documentsQuery->get();
        
        if ($documents->isEmpty()) {
            return null;
        }

        // Get source posts for additional metrics
        $posts = $this->getSourcePosts($documents, $platform, $category, $timeBucket);

        // Calculate sentiment metrics
        $sentimentMetrics = $this->calculateSentimentMetrics($documents);
        
        // Calculate volume and engagement metrics
        $volumeMetrics = $this->calculateVolumeMetrics($posts);
        
        // Calculate entity and keyword metrics
        $contentMetrics = $this->calculateContentMetrics($documents, $posts);

        return [
            'aggregate_date' => $date->toDateString(),
            'platform' => $platform,
            'keyword_category' => $category,
            'time_bucket' => $timeBucket,
            'language' => $language,
            
            // Volume metrics
            'total_posts' => $volumeMetrics['total_posts'],
            'processed_posts' => $documents->count(),
            'total_engagement' => $volumeMetrics['total_engagement'],
            
            // Sentiment metrics
            'average_sentiment' => $sentimentMetrics['average_sentiment'],
            'weighted_sentiment' => $sentimentMetrics['weighted_sentiment'],
            'average_magnitude' => $sentimentMetrics['average_magnitude'],
            'sentiment_volatility' => $sentimentMetrics['volatility'],
            
            // Sentiment distribution
            'very_positive_count' => $sentimentMetrics['very_positive_count'],
            'positive_count' => $sentimentMetrics['positive_count'],
            'neutral_count' => $sentimentMetrics['neutral_count'],
            'negative_count' => $sentimentMetrics['negative_count'],
            'very_negative_count' => $sentimentMetrics['very_negative_count'],
            
            // Content analysis
            'top_keywords' => $contentMetrics['top_keywords'],
            'top_entities' => $contentMetrics['top_entities'],
            'language_distribution' => $contentMetrics['language_distribution'],
        ];
    }

    protected function buildDocumentsQuery(
        Carbon $date,
        string $platform,
        string $category,
        ?int $timeBucket,
        string $language
    ): \Illuminate\Database\Eloquent\Builder {
        
        $query = SentimentBatchDocument::whereHas('batch', function($q) use ($date) {
            $q->where('processing_date', $date->toDateString());
        })
        ->where('processing_status', 'completed')
        ->whereNotNull('sentiment_score');

        // Filter by language
        if ($language !== 'all') {
            $query->where('detected_language', $language);
        }

        // Filter by platform and category through source posts
        if ($platform !== 'all' || $category !== 'all' || $timeBucket !== null) {
            // Since sourceModel() is dynamic, we need to filter differently
            // For now, we'll use the source_type to determine the relationship
            $query->where(function($q) use ($platform, $category, $timeBucket, $date) {
                $q->where('source_type', 'social_media_post')
                  ->whereHas('socialMediaPost', function($subQ) use ($platform, $category, $timeBucket, $date) {
                      if ($platform !== 'all') {
                          $subQ->where('platform', $platform);
                      }

                      if ($category !== 'all') {
                          $subQ->whereHas('keywordMatches', function($kq) use ($category) {
                              $kq->where('category', $category);
                          });
                      }

                      if ($timeBucket !== null) {
                          $startTime = $date->copy()->setHour($timeBucket)->setMinute(0)->setSecond(0);
                          $endTime = $startTime->copy()->addHour();
                          $subQ->whereBetween('published_at', [$startTime, $endTime]);
                      }
                  });
            });
        }

        return $query;
    }

    protected function getSourcePosts($documents, string $platform, string $category, ?int $timeBucket)
    {
        $sourceIds = $documents->where('source_type', 'social_media_post')
                              ->pluck('source_id')
                              ->unique();

        if ($sourceIds->isEmpty()) {
            return collect();
        }

        $query = SocialMediaPost::whereIn('id', $sourceIds);

        return $query->get();
    }

    protected function calculateSentimentMetrics($documents): array
    {
        $sentiments = $documents->pluck('sentiment_score')->filter();
        $magnitudes = $documents->pluck('magnitude')->filter();
        
        if ($sentiments->isEmpty()) {
            return $this->getEmptySentimentMetrics();
        }

        // Calculate basic sentiment metrics
        $averageSentiment = round($sentiments->avg(), 3);
        $averageMagnitude = round($magnitudes->avg(), 3);
        
        // Calculate weighted sentiment (you might want to weight by engagement)
        $weightedSentiment = $averageSentiment; // Simplified for now
        
        // Calculate volatility (standard deviation)
        $variance = $sentiments->map(function($score) use ($averageSentiment) {
            return pow($score - $averageSentiment, 2);
        })->avg();
        $volatility = round(sqrt($variance), 3);

        // Count sentiment distribution
        $distribution = $this->categorizeSentiments($sentiments);

        return [
            'average_sentiment' => $averageSentiment,
            'weighted_sentiment' => $weightedSentiment,
            'average_magnitude' => $averageMagnitude,
            'volatility' => $volatility,
            'very_positive_count' => $distribution['very_positive'],
            'positive_count' => $distribution['positive'],
            'neutral_count' => $distribution['neutral'],
            'negative_count' => $distribution['negative'],
            'very_negative_count' => $distribution['very_negative'],
        ];
    }

    protected function calculateVolumeMetrics($posts): array
    {
        $totalPosts = $posts->count();
        $totalEngagement = $posts->sum(function($post) {
            return ($post->likes_count ?? 0) + 
                   ($post->retweets_count ?? 0) + 
                   ($post->replies_count ?? 0) + 
                   ($post->comments_count ?? 0);
        });

        return [
            'total_posts' => $totalPosts,
            'total_engagement' => $totalEngagement,
        ];
    }

    protected function calculateContentMetrics($documents, $posts): array
    {
        // Extract top keywords from posts
        $topKeywords = $this->extractTopKeywords($posts);
        
        // Extract top entities from sentiment analysis
        $topEntities = $this->extractTopEntities($documents);
        
        // Calculate language distribution
        $languageDistribution = $this->calculateLanguageDistribution($documents);

        return [
            'top_keywords' => $topKeywords,
            'top_entities' => $topEntities,
            'language_distribution' => $languageDistribution,
        ];
    }

    protected function calculateComparisonMetrics(
        Carbon $date,
        string $platform,
        string $category,
        string $language
    ): array {
        
        // Get previous day's data
        $previousDay = $this->getPreviousDayAggregate($date->copy()->subDay(), $platform, $category, $language);
        
        // Get data from 7 days ago
        $weekAgo = $this->getPreviousDayAggregate($date->copy()->subDays(7), $platform, $category, $language);

        $metrics = [];

        if ($previousDay) {
            $metrics['sentiment_change_1d'] = $this->calculatePercentageChange(
                $previousDay->average_sentiment,
                $previousDay->average_sentiment // This would be current day's sentiment
            );
            
            $metrics['volume_change_1d'] = $this->calculatePercentageChange(
                $previousDay->total_posts,
                $previousDay->total_posts // This would be current day's volume
            );
        }

        if ($weekAgo) {
            $metrics['sentiment_change_7d'] = $this->calculatePercentageChange(
                $weekAgo->average_sentiment,
                $weekAgo->average_sentiment // This would be current day's sentiment
            );
        }

        return $metrics;
    }

    protected function categorizeSentiments($sentiments): array
    {
        $distribution = [
            'very_positive' => 0,
            'positive' => 0,
            'neutral' => 0,
            'negative' => 0,
            'very_negative' => 0,
        ];

        foreach ($sentiments as $score) {
            match(true) {
                $score > 0.6 => $distribution['very_positive']++,
                $score > 0.2 => $distribution['positive']++,
                $score > -0.2 => $distribution['neutral']++,
                $score > -0.6 => $distribution['negative']++,
                default => $distribution['very_negative']++
            };
        }

        return $distribution;
    }

    protected function extractTopKeywords($posts, int $limit = 10): array
    {
        $keywordCounts = [];

        foreach ($posts as $post) {
            $matches = $post->keywordMatches ?? collect();
            foreach ($matches as $match) {
                $keyword = $match->keyword ?? '';
                if (!empty($keyword)) {
                    $keywordCounts[$keyword] = ($keywordCounts[$keyword] ?? 0) + 1;
                }
            }
        }

        arsort($keywordCounts);
        return array_slice($keywordCounts, 0, $limit, true);
    }

    protected function extractTopEntities($documents, int $limit = 10): array
    {
        $entityCounts = [];

        foreach ($documents as $document) {
            $entities = $document->entities ?? [];
            foreach ($entities as $entity) {
                $name = $entity['name'] ?? '';
                $type = $entity['type'] ?? 'UNKNOWN';
                $salience = $entity['salience'] ?? 0;

                $key = $name . '|' . $type;
                if (!isset($entityCounts[$key])) {
                    $entityCounts[$key] = [
                        'name' => $name,
                        'type' => $type,
                        'count' => 0,
                        'total_salience' => 0,
                    ];
                }
                
                $entityCounts[$key]['count']++;
                $entityCounts[$key]['total_salience'] += $salience;
            }
        }

        // Sort by count and take top entities
        uasort($entityCounts, function($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        return array_slice(array_values($entityCounts), 0, $limit);
    }

    protected function calculateLanguageDistribution($documents): array
    {
        $languages = $documents->pluck('detected_language')->filter();
        $total = $languages->count();

        if ($total === 0) {
            return ['en' => 100.0];
        }

        $distribution = [];
        foreach ($languages->countBy() as $lang => $count) {
            $distribution[$lang] = round(($count / $total) * 100, 2);
        }

        return $distribution;
    }

    protected function getPreviousDayAggregate(Carbon $date, string $platform, string $category, string $language): ?DailySentimentAggregate
    {
        return DailySentimentAggregate::forDate($date)
            ->forPlatform($platform)
            ->forCategory($category)
            ->forLanguage($language)
            ->fullDay()
            ->first();
    }

    protected function calculatePercentageChange(?float $oldValue, ?float $newValue): ?float
    {
        if ($oldValue === null || $newValue === null || $oldValue == 0) {
            return null;
        }

        return round((($newValue - $oldValue) / $oldValue) * 100, 2);
    }

    protected function getEmptySentimentMetrics(): array
    {
        return [
            'average_sentiment' => 0.0,
            'weighted_sentiment' => 0.0,
            'average_magnitude' => 0.0,
            'volatility' => 0.0,
            'very_positive_count' => 0,
            'positive_count' => 0,
            'neutral_count' => 0,
            'negative_count' => 0,
            'very_negative_count' => 0,
        ];
    }

    protected function getPlatforms(): array
    {
        return ['all', 'twitter', 'reddit', 'telegram'];
    }

    protected function getKeywordCategories(): array
    {
        return ['all', 'blockchain', 'security', 'contracts', 'defi'];
    }

    protected function getLanguages(): array
    {
        return ['all', 'en', 'es', 'fr', 'de'];
    }

    public function aggregateMultipleDays(Carbon $startDate, Carbon $endDate): array
    {
        $results = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dayResults = $this->generateDailyAggregates($currentDate);
            $results = array_merge($results, $dayResults);
            $currentDate->addDay();
        }

        return $results;
    }

    public function cleanupOldAggregates(int $daysToKeep = 90): int
    {
        $cutoffDate = now()->subDays($daysToKeep);
        
        $deletedCount = DailySentimentAggregate::where('aggregate_date', '<', $cutoffDate->toDateString())
            ->delete();

        if ($deletedCount > 0) {
            Log::info('Cleaned up old sentiment aggregates', [
                'deleted_count' => $deletedCount,
                'cutoff_date' => $cutoffDate->toDateString(),
            ]);
        }

        return $deletedCount;
    }
}