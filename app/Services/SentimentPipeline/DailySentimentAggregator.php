<?php

declare(strict_types=1);

namespace App\Services\SentimentPipeline;

use App\Models\SocialMediaPost;
use App\Models\SentimentBatchDocument;
use App\Models\DailySentimentAggregate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class DailySentimentAggregator
{
    private array $config;

    public function __construct()
    {
        $this->config = config('sentiment_pipeline.aggregation', []);
    }

    /**
     * Generate daily aggregates for a specific date
     */
    public function generateDailyAggregates(string $date = null): array
    {
        $targetDate = $date ? Carbon::parse($date) : Carbon::yesterday();
        $dateStr = $targetDate->format('Y-m-d');
        
        Log::info('Generating daily sentiment aggregates', ['date' => $dateStr]);

        try {
            DB::beginTransaction();
            
            // Clear existing aggregates for the date
            $this->clearExistingAggregates($dateStr);
            
            // Generate aggregates by platform
            $platformAggregates = $this->generatePlatformAggregates($targetDate);
            
            // Generate aggregates by category
            $categoryAggregates = $this->generateCategoryAggregates($targetDate);
            
            // Generate aggregates by keyword
            $keywordAggregates = $this->generateKeywordAggregates($targetDate);
            
            // Generate overall daily summary
            $overallAggregate = $this->generateOverallAggregate($targetDate);
            
            DB::commit();
            
            $result = [
                'date' => $dateStr,
                'platform_aggregates' => count($platformAggregates),
                'category_aggregates' => count($categoryAggregates),
                'keyword_aggregates' => count($keywordAggregates),
                'overall_aggregate' => $overallAggregate ? 1 : 0,
                'total_aggregates' => count($platformAggregates) + count($categoryAggregates) + count($keywordAggregates) + ($overallAggregate ? 1 : 0)
            ];
            
            Log::info('Daily sentiment aggregates generated successfully', $result);
            
            return $result;

        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to generate daily sentiment aggregates', [
                'date' => $dateStr,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Generate aggregates by platform (Twitter, Reddit, Telegram)
     */
    private function generatePlatformAggregates(Carbon $date): array
    {
        $aggregates = [];
        $platforms = ['twitter', 'reddit', 'telegram'];
        
        foreach ($platforms as $platform) {
            Log::info('Generating platform aggregate', ['platform' => $platform, 'date' => $date->format('Y-m-d')]);
            
            $data = $this->getPlatformData($platform, $date);
            
            if ($data['total_posts'] > 0) {
                $aggregate = DailySentimentAggregate::create([
                    'date' => $date->format('Y-m-d'),
                    'platform' => $platform,
                    'category' => 'all',
                    'total_posts' => $data['total_posts'],
                    'positive_posts' => $data['positive_posts'],
                    'negative_posts' => $data['negative_posts'],
                    'neutral_posts' => $data['neutral_posts'],
                    'avg_sentiment_score' => $data['avg_sentiment_score'],
                    'sentiment_magnitude' => $data['sentiment_magnitude'],
                    'sentiment_volatility' => $data['sentiment_volatility'],
                    'top_keywords' => json_encode($data['top_keywords']),
                    'engagement_metrics' => json_encode($data['engagement_metrics']),
                    'hourly_distribution' => json_encode($data['hourly_distribution']),
                    'metadata' => [
                        'aggregation_type' => 'platform',
                        'generated_at' => now()->toISOString(),
                        'data_source' => 'social_media_posts'
                    ]
                ]);
                
                $aggregates[] = $aggregate;
            }
        }
        
        return $aggregates;
    }

    /**
     * Generate aggregates by category (security, defi, nft, etc.)
     */
    private function generateCategoryAggregates(Carbon $date): array
    {
        $aggregates = [];
        $categories = $this->getAvailableCategories($date);
        
        foreach ($categories as $category) {
            Log::info('Generating category aggregate', ['category' => $category, 'date' => $date->format('Y-m-d')]);
            
            $data = $this->getCategoryData($category, $date);
            
            if ($data['total_posts'] > 0) {
                $aggregate = DailySentimentAggregate::create([
                    'date' => $date->format('Y-m-d'),
                    'platform' => 'all',
                    'category' => $category,
                    'total_posts' => $data['total_posts'],
                    'positive_posts' => $data['positive_posts'],
                    'negative_posts' => $data['negative_posts'],
                    'neutral_posts' => $data['neutral_posts'],
                    'avg_sentiment_score' => $data['avg_sentiment_score'],
                    'sentiment_magnitude' => $data['sentiment_magnitude'],
                    'sentiment_volatility' => $data['sentiment_volatility'],
                    'top_keywords' => json_encode($data['top_keywords']),
                    'engagement_metrics' => json_encode($data['engagement_metrics']),
                    'hourly_distribution' => json_encode($data['hourly_distribution']),
                    'metadata' => [
                        'aggregation_type' => 'category',
                        'generated_at' => now()->toISOString(),
                        'data_source' => 'social_media_posts',
                        'platforms_included' => $data['platforms_included']
                    ]
                ]);
                
                $aggregates[] = $aggregate;
            }
        }
        
        return $aggregates;
    }

    /**
     * Generate aggregates by top keywords
     */
    private function generateKeywordAggregates(Carbon $date): array
    {
        $aggregates = [];
        $topKeywords = $this->getTopKeywords($date, 20); // Top 20 keywords
        
        foreach ($topKeywords as $keywordData) {
            $keyword = $keywordData['keyword'];
            
            Log::info('Generating keyword aggregate', ['keyword' => $keyword, 'date' => $date->format('Y-m-d')]);
            
            $data = $this->getKeywordData($keyword, $date);
            
            if ($data['total_posts'] > 0) {
                $aggregate = DailySentimentAggregate::create([
                    'date' => $date->format('Y-m-d'),
                    'platform' => 'all',
                    'category' => 'keyword',
                    'keyword' => $keyword,
                    'total_posts' => $data['total_posts'],
                    'positive_posts' => $data['positive_posts'],
                    'negative_posts' => $data['negative_posts'],
                    'neutral_posts' => $data['neutral_posts'],
                    'avg_sentiment_score' => $data['avg_sentiment_score'],
                    'sentiment_magnitude' => $data['sentiment_magnitude'],
                    'sentiment_volatility' => $data['sentiment_volatility'],
                    'top_keywords' => json_encode([$keyword]),
                    'engagement_metrics' => json_encode($data['engagement_metrics']),
                    'hourly_distribution' => json_encode($data['hourly_distribution']),
                    'metadata' => [
                        'aggregation_type' => 'keyword',
                        'generated_at' => now()->toISOString(),
                        'data_source' => 'social_media_posts',
                        'keyword_frequency' => $keywordData['frequency'],
                        'platforms_included' => $data['platforms_included']
                    ]
                ]);
                
                $aggregates[] = $aggregate;
            }
        }
        
        return $aggregates;
    }

    /**
     * Generate overall daily aggregate
     */
    private function generateOverallAggregate(Carbon $date): ?DailySentimentAggregate
    {
        Log::info('Generating overall daily aggregate', ['date' => $date->format('Y-m-d')]);
        
        $data = $this->getOverallData($date);
        
        if ($data['total_posts'] > 0) {
            return DailySentimentAggregate::create([
                'date' => $date->format('Y-m-d'),
                'platform' => 'all',
                'category' => 'all',
                'total_posts' => $data['total_posts'],
                'positive_posts' => $data['positive_posts'],
                'negative_posts' => $data['negative_posts'],
                'neutral_posts' => $data['neutral_posts'],
                'avg_sentiment_score' => $data['avg_sentiment_score'],
                'sentiment_magnitude' => $data['sentiment_magnitude'],
                'sentiment_volatility' => $data['sentiment_volatility'],
                'top_keywords' => json_encode($data['top_keywords']),
                'engagement_metrics' => json_encode($data['engagement_metrics']),
                'hourly_distribution' => json_encode($data['hourly_distribution']),
                'metadata' => [
                    'aggregation_type' => 'overall',
                    'generated_at' => now()->toISOString(),
                    'data_source' => 'social_media_posts',
                    'platforms_included' => $data['platforms_included'],
                    'categories_included' => $data['categories_included']
                ]
            ]);
        }
        
        return null;
    }

    /**
     * Get platform-specific data for aggregation
     */
    private function getPlatformData(string $platform, Carbon $date): array
    {
        $startDate = $date->startOfDay();
        $endDate = $date->copy()->endOfDay();
        
        // Get posts from social media posts table
        $posts = SocialMediaPost::where('platform', $platform)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('sentiment_score')
            ->get();
        
        // Get additional data from processed batch documents
        $batchDocuments = SentimentBatchDocument::whereHas('batch', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('started_at', [$startDate, $endDate]);
            })
            ->where('status', 'completed')
            ->whereNotNull('sentiment_score')
            ->get();
        
        return $this->processDataCollection($posts, $batchDocuments, ['platform' => $platform]);
    }

    /**
     * Get category-specific data
     */
    private function getCategoryData(string $category, Carbon $date): array
    {
        $startDate = $date->startOfDay();
        $endDate = $date->copy()->endOfDay();
        
        // This would be based on keyword matching or content categorization
        $posts = SocialMediaPost::whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('sentiment_score')
            ->where(function ($query) use ($category) {
                // Match posts by keywords or metadata
                $query->whereJsonContains('keywords_matched', $category)
                      ->orWhereJsonContains('metadata->category', $category);
            })
            ->get();
        
        $batchDocuments = collect(); // Placeholder for batch documents
        
        return $this->processDataCollection($posts, $batchDocuments, ['category' => $category]);
    }

    /**
     * Get keyword-specific data
     */
    private function getKeywordData(string $keyword, Carbon $date): array
    {
        $startDate = $date->startOfDay();
        $endDate = $date->copy()->endOfDay();
        
        $posts = SocialMediaPost::whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('sentiment_score')
            ->where(function ($query) use ($keyword) {
                $query->whereJsonContains('keywords_matched', $keyword)
                      ->orWhere('content', 'LIKE', "%{$keyword}%");
            })
            ->get();
        
        $batchDocuments = collect(); // Placeholder
        
        return $this->processDataCollection($posts, $batchDocuments, ['keyword' => $keyword]);
    }

    /**
     * Get overall data for the date
     */
    private function getOverallData(Carbon $date): array
    {
        $startDate = $date->startOfDay();
        $endDate = $date->copy()->endOfDay();
        
        $posts = SocialMediaPost::whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('sentiment_score')
            ->get();
        
        $batchDocuments = SentimentBatchDocument::whereHas('batch', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('started_at', [$startDate, $endDate]);
            })
            ->where('status', 'completed')
            ->whereNotNull('sentiment_score')
            ->get();
        
        return $this->processDataCollection($posts, $batchDocuments, ['type' => 'overall']);
    }

    /**
     * Process collection of posts and documents for aggregation
     */
    private function processDataCollection($posts, $batchDocuments, array $context): array
    {
        $allScores = [];
        $hourlyDistribution = array_fill(0, 24, 0);
        $platformCounts = [];
        $categoryKeywords = [];
        $engagementMetrics = [];
        
        // Process social media posts
        foreach ($posts as $post) {
            $allScores[] = (float) $post->sentiment_score;
            
            $hour = Carbon::parse($post->created_at)->hour;
            $hourlyDistribution[$hour]++;
            
            $platformCounts[$post->platform] = ($platformCounts[$post->platform] ?? 0) + 1;
            
            if ($post->keywords_matched) {
                $keywords = is_array($post->keywords_matched) ? $post->keywords_matched : json_decode($post->keywords_matched, true);
                if ($keywords) {
                    foreach ($keywords as $keyword) {
                        $categoryKeywords[$keyword] = ($categoryKeywords[$keyword] ?? 0) + 1;
                    }
                }
            }
            
            // Collect engagement metrics
            $metrics = is_array($post->metrics) ? $post->metrics : json_decode($post->metrics ?? '{}', true);
            foreach ($metrics as $metric => $value) {
                if (is_numeric($value)) {
                    $engagementMetrics[$metric] = ($engagementMetrics[$metric] ?? 0) + $value;
                }
            }
        }
        
        // Process batch documents
        foreach ($batchDocuments as $doc) {
            if ($doc->sentiment_score !== null) {
                $allScores[] = (float) $doc->sentiment_score;
                
                $hour = Carbon::parse($doc->created_at)->hour;
                $hourlyDistribution[$hour]++;
            }
        }
        
        if (empty($allScores)) {
            return [
                'total_posts' => 0,
                'positive_posts' => 0,
                'negative_posts' => 0,
                'neutral_posts' => 0,
                'avg_sentiment_score' => 0.0,
                'sentiment_magnitude' => 0.0,
                'sentiment_volatility' => 0.0,
                'top_keywords' => [],
                'engagement_metrics' => [],
                'hourly_distribution' => $hourlyDistribution,
                'platforms_included' => [],
                'categories_included' => []
            ];
        }
        
        // Calculate sentiment distribution
        $positive = count(array_filter($allScores, fn($score) => $score > 0.2));
        $negative = count(array_filter($allScores, fn($score) => $score < -0.2));
        $neutral = count($allScores) - $positive - $negative;
        
        // Calculate statistics
        $avgScore = array_sum($allScores) / count($allScores);
        $magnitude = array_sum(array_map('abs', $allScores)) / count($allScores);
        $volatility = $this->calculateVolatility($allScores);
        
        // Get top keywords
        arsort($categoryKeywords);
        $topKeywords = array_keys(array_slice($categoryKeywords, 0, 10, true));
        
        return [
            'total_posts' => count($allScores),
            'positive_posts' => $positive,
            'negative_posts' => $negative,
            'neutral_posts' => $neutral,
            'avg_sentiment_score' => round($avgScore, 4),
            'sentiment_magnitude' => round($magnitude, 4),
            'sentiment_volatility' => round($volatility, 4),
            'top_keywords' => $topKeywords,
            'engagement_metrics' => $engagementMetrics,
            'hourly_distribution' => $hourlyDistribution,
            'platforms_included' => array_keys($platformCounts),
            'categories_included' => array_keys($categoryKeywords)
        ];
    }

    /**
     * Calculate sentiment volatility (standard deviation)
     */
    private function calculateVolatility(array $scores): float
    {
        if (count($scores) < 2) return 0.0;
        
        $mean = array_sum($scores) / count($scores);
        $variance = array_sum(array_map(fn($score) => pow($score - $mean, 2), $scores)) / count($scores);
        
        return sqrt($variance);
    }

    /**
     * Clear existing aggregates for the date
     */
    private function clearExistingAggregates(string $date): void
    {
        DailySentimentAggregate::where('date', $date)->delete();
        Log::info('Cleared existing aggregates for date', ['date' => $date]);
    }

    /**
     * Get available categories for the date
     */
    private function getAvailableCategories(Carbon $date): array
    {
        // This would query your data to find available categories
        return ['security', 'defi', 'nft', 'bitcoin', 'ethereum', 'general'];
    }

    /**
     * Get top keywords for the date
     */
    private function getTopKeywords(Carbon $date, int $limit): array
    {
        $startDate = $date->startOfDay();
        $endDate = $date->copy()->endOfDay();
        
        // Mock implementation - in real implementation, you'd query your data
        $mockKeywords = [
            ['keyword' => 'blockchain', 'frequency' => rand(50, 200)],
            ['keyword' => 'bitcoin', 'frequency' => rand(30, 150)],
            ['keyword' => 'ethereum', 'frequency' => rand(25, 120)],
            ['keyword' => 'defi', 'frequency' => rand(20, 100)],
            ['keyword' => 'smart contract', 'frequency' => rand(15, 80)],
            ['keyword' => 'security', 'frequency' => rand(10, 60)],
            ['keyword' => 'nft', 'frequency' => rand(8, 50)],
            ['keyword' => 'cryptocurrency', 'frequency' => rand(12, 70)]
        ];
        
        // Sort by frequency
        usort($mockKeywords, fn($a, $b) => $b['frequency'] - $a['frequency']);
        
        return array_slice($mockKeywords, 0, $limit);
    }

    /**
     * Get aggregation summary for a date range
     */
    public function getAggregationSummary(string $startDate, string $endDate): array
    {
        $aggregates = DailySentimentAggregate::whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get();
        
        $summary = [
            'date_range' => ['start' => $startDate, 'end' => $endDate],
            'total_aggregates' => $aggregates->count(),
            'by_platform' => [],
            'by_category' => [],
            'sentiment_trends' => []
        ];
        
        // Group by platform
        $summary['by_platform'] = $aggregates->groupBy('platform')->map(function ($group) {
            return [
                'count' => $group->count(),
                'avg_sentiment' => $group->avg('avg_sentiment_score'),
                'total_posts' => $group->sum('total_posts')
            ];
        });
        
        // Group by category
        $summary['by_category'] = $aggregates->groupBy('category')->map(function ($group) {
            return [
                'count' => $group->count(),
                'avg_sentiment' => $group->avg('avg_sentiment_score'),
                'total_posts' => $group->sum('total_posts')
            ];
        });
        
        // Calculate sentiment trends by date
        $summary['sentiment_trends'] = $aggregates->where('platform', 'all')
            ->where('category', 'all')
            ->mapWithKeys(function ($aggregate) {
                return [$aggregate->date => [
                    'avg_sentiment' => $aggregate->avg_sentiment_score,
                    'total_posts' => $aggregate->total_posts,
                    'positive_posts' => $aggregate->positive_posts,
                    'negative_posts' => $aggregate->negative_posts
                ]];
            });
        
        return $summary;
    }

    /**
     * Schedule daily aggregation
     */
    public function scheduleDaily(): void
    {
        // This would be called by a scheduled job
        $this->generateDailyAggregates();
    }
}