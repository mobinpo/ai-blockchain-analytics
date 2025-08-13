<?php

namespace App\Services;

use App\Models\SocialMediaPost;
use App\Models\DailySentimentAggregate;
use App\Services\GoogleCloudNLPService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class SentimentPipelineProcessor
{
    protected GoogleCloudNLPService $nlpService;
    protected array $config;

    public function __construct(GoogleCloudNLPService $nlpService)
    {
        $this->nlpService = $nlpService;
        $this->config = config('services.google_language', []);
    }

    /**
     * Process sentiment analysis pipeline for a specific date
     */
    public function processDailySentiment(Carbon $date = null): array
    {
        $date = $date ?: Carbon::yesterday();
        
        Log::info('Starting daily sentiment processing', [
            'date' => $date->toDateString(),
            'timestamp' => now()
        ]);

        $startTime = microtime(true);
        $results = [];

        // Process each platform separately
        $platforms = ['twitter', 'reddit', 'telegram'];
        
        foreach ($platforms as $platform) {
            try {
                $platformResult = $this->processPlatformSentiment($platform, $date);
                $results[$platform] = $platformResult;
                
                Log::info("Completed {$platform} sentiment processing", [
                    'date' => $date->toDateString(),
                    'posts_processed' => $platformResult['posts_processed'],
                    'aggregates_created' => $platformResult['aggregates_created']
                ]);

            } catch (\Exception $e) {
                Log::error("Failed to process {$platform} sentiment", [
                    'date' => $date->toDateString(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                $results[$platform] = [
                    'error' => $e->getMessage(),
                    'posts_processed' => 0,
                    'aggregates_created' => 0
                ];
            }
        }

        $processingTime = round(microtime(true) - $startTime, 2);
        
        Log::info('Daily sentiment processing completed', [
            'date' => $date->toDateString(),
            'processing_time' => $processingTime . 's',
            'results' => $results
        ]);

        return [
            'date' => $date->toDateString(),
            'processing_time' => $processingTime,
            'platforms' => $results,
            'total_posts_processed' => collect($results)->sum('posts_processed'),
            'total_aggregates_created' => collect($results)->sum('aggregates_created')
        ];
    }

    /**
     * Process sentiment for a specific platform and date
     */
    protected function processPlatformSentiment(string $platform, Carbon $date): array
    {
        // Get posts for the specific platform and date
        $posts = SocialMediaPost::where('platform', $platform)
            ->whereDate('platform_created_at', $date)
            ->whereNull('sentiment_score') // Only unprocessed posts
            ->orderBy('engagement_count', 'desc') // Process high-engagement posts first
            ->get();

        if ($posts->isEmpty()) {
            return [
                'posts_processed' => 0,
                'aggregates_created' => 0,
                'message' => 'No posts to process'
            ];
        }

        Log::info("Processing {$platform} posts", [
            'date' => $date->toDateString(),
            'post_count' => $posts->count()
        ]);

        // Process sentiment in batches
        $batchSize = 50;
        $processedCount = 0;
        $sentimentResults = [];

        foreach ($posts->chunk($batchSize) as $batch) {
            $batchResults = $this->nlpService->processSocialMediaTexts($batch);
            $sentimentResults = array_merge($sentimentResults, $batchResults);
            
            // Update posts with sentiment data
            $this->updatePostsSentiment($batchResults);
            
            $processedCount += count($batchResults);
            
            Log::debug("Processed batch", [
                'platform' => $platform,
                'batch_size' => count($batchResults),
                'total_processed' => $processedCount
            ]);

            // Rate limiting
            usleep(500000); // 0.5 second delay between batches
        }

        // Create daily aggregates
        $aggregatesCreated = $this->createDailyAggregates($platform, $date, $sentimentResults);

        return [
            'posts_processed' => $processedCount,
            'aggregates_created' => $aggregatesCreated,
            'sentiment_stats' => $this->calculateSentimentStats($sentimentResults)
        ];
    }

    /**
     * Update posts with sentiment analysis results
     */
    protected function updatePostsSentiment(array $sentimentResults): void
    {
        foreach ($sentimentResults as $result) {
            if (!isset($result['post_id']) || $result['error']) {
                continue;
            }

            SocialMediaPost::where('id', $result['post_id'])
                ->update([
                    'sentiment_score' => $result['sentiment_score'],
                    'metadata' => DB::raw("metadata || '" . json_encode([
                        'sentiment_magnitude' => $result['sentiment_magnitude'],
                        'sentiment_label' => $result['sentiment_label'],
                        'processed_at' => $result['processed_at']
                    ]) . "'::jsonb")
                ]);
        }
    }

    /**
     * Create daily sentiment aggregates
     */
    protected function createDailyAggregates(string $platform, Carbon $date, array $sentimentResults): int
    {
        $aggregatesCreated = 0;

        // Overall platform aggregate
        $overallAggregate = $this->calculateAggregate($sentimentResults, $platform, $date);
        DailySentimentAggregate::updateOrCreate(
            [
                'date' => $date->toDateString(),
                'platform' => $platform,
                'keyword' => null
            ],
            $overallAggregate
        );
        $aggregatesCreated++;

        // Keyword-specific aggregates
        $keywordGroups = $this->groupResultsByKeyword($sentimentResults);
        
        foreach ($keywordGroups as $keyword => $results) {
            if (count($results) < 5) continue; // Skip keywords with less than 5 posts
            
            $keywordAggregate = $this->calculateAggregate($results, $platform, $date, $keyword);
            DailySentimentAggregate::updateOrCreate(
                [
                    'date' => $date->toDateString(),
                    'platform' => $platform,
                    'keyword' => $keyword
                ],
                $keywordAggregate
            );
            $aggregatesCreated++;
        }

        return $aggregatesCreated;
    }

    /**
     * Calculate aggregate data from sentiment results
     */
    protected function calculateAggregate(array $results, string $platform, Carbon $date, string $keyword = null): array
    {
        $validResults = collect($results)->where('error', null)->where('sentiment_score', '!=', null);
        $totalPosts = count($results);
        $analyzedPosts = $validResults->count();

        if ($analyzedPosts === 0) {
            return [
                'total_posts' => $totalPosts,
                'analyzed_posts' => 0,
                'avg_sentiment_score' => null,
                'avg_magnitude' => null,
                'positive_count' => 0,
                'negative_count' => 0,
                'neutral_count' => 0,
                'unknown_count' => $totalPosts,
                'positive_percentage' => 0,
                'negative_percentage' => 0,
                'neutral_percentage' => 0,
                'hourly_distribution' => [],
                'top_keywords' => [],
                'metadata' => ['processing_error' => 'No valid sentiment results'],
                'processed_at' => now()
            ];
        }

        // Calculate sentiment distribution
        $sentimentCounts = $validResults->countBy('sentiment_label');
        $positiveCount = $sentimentCounts['positive'] ?? 0;
        $negativeCount = $sentimentCounts['negative'] ?? 0;
        $neutralCount = $sentimentCounts['neutral'] ?? 0;
        $unknownCount = $totalPosts - $analyzedPosts;

        // Calculate percentages
        $positivePercentage = $analyzedPosts > 0 ? ($positiveCount / $analyzedPosts) * 100 : 0;
        $negativePercentage = $analyzedPosts > 0 ? ($negativeCount / $analyzedPosts) * 100 : 0;
        $neutralPercentage = $analyzedPosts > 0 ? ($neutralCount / $analyzedPosts) * 100 : 0;

        // Extract hourly distribution and keywords
        $hourlyDistribution = $this->calculateHourlyDistribution($results, $date);
        $topKeywords = $this->extractTopKeywords($results);

        return [
            'total_posts' => $totalPosts,
            'analyzed_posts' => $analyzedPosts,
            'avg_sentiment_score' => round($validResults->avg('sentiment_score'), 4),
            'avg_magnitude' => round($validResults->avg('sentiment_magnitude'), 4),
            'positive_count' => $positiveCount,
            'negative_count' => $negativeCount,
            'neutral_count' => $neutralCount,
            'unknown_count' => $unknownCount,
            'positive_percentage' => round($positivePercentage, 2),
            'negative_percentage' => round($negativePercentage, 2),
            'neutral_percentage' => round($neutralPercentage, 2),
            'hourly_distribution' => $hourlyDistribution,
            'top_keywords' => $topKeywords,
            'metadata' => [
                'platform' => $platform,
                'keyword' => $keyword,
                'min_sentiment' => $validResults->min('sentiment_score'),
                'max_sentiment' => $validResults->max('sentiment_score'),
                'sentiment_std_dev' => $this->calculateStandardDeviation($validResults->pluck('sentiment_score')->toArray())
            ],
            'processed_at' => now()
        ];
    }

    /**
     * Group sentiment results by matched keywords
     */
    protected function groupResultsByKeyword(array $results): array
    {
        $groups = [];

        foreach ($results as $result) {
            if (!isset($result['post_id'])) continue;

            // Get the post to access matched keywords
            $post = SocialMediaPost::find($result['post_id']);
            if (!$post || !$post->matched_keywords) continue;

            foreach ($post->matched_keywords as $keyword) {
                if (!isset($groups[$keyword])) {
                    $groups[$keyword] = [];
                }
                $groups[$keyword][] = $result;
            }
        }

        return $groups;
    }

    /**
     * Calculate hourly sentiment distribution
     */
    protected function calculateHourlyDistribution(array $results, Carbon $date): array
    {
        $hourlyData = [];

        foreach ($results as $result) {
            if (!isset($result['post_id']) || $result['error']) continue;

            $post = SocialMediaPost::find($result['post_id']);
            if (!$post) continue;

            $hour = Carbon::parse($post->platform_created_at)->hour;
            
            if (!isset($hourlyData[$hour])) {
                $hourlyData[$hour] = [
                    'count' => 0,
                    'positive' => 0,
                    'negative' => 0,
                    'neutral' => 0,
                    'avg_sentiment' => 0
                ];
            }

            $hourlyData[$hour]['count']++;
            
            if ($result['sentiment_label'] === 'positive') $hourlyData[$hour]['positive']++;
            elseif ($result['sentiment_label'] === 'negative') $hourlyData[$hour]['negative']++;
            else $hourlyData[$hour]['neutral']++;
        }

        // Calculate averages for each hour
        foreach ($hourlyData as $hour => &$data) {
            if ($data['count'] > 0) {
                $data['avg_sentiment'] = round(
                    ($data['positive'] - $data['negative']) / $data['count'], 3
                );
            }
        }

        return $hourlyData;
    }

    /**
     * Extract top keywords from sentiment results
     */
    protected function extractTopKeywords(array $results): array
    {
        $keywordCounts = [];

        foreach ($results as $result) {
            if (!isset($result['post_id'])) continue;

            $post = SocialMediaPost::find($result['post_id']);
            if (!$post || !$post->matched_keywords) continue;

            foreach ($post->matched_keywords as $keyword) {
                $keywordCounts[$keyword] = ($keywordCounts[$keyword] ?? 0) + 1;
            }
        }

        arsort($keywordCounts);
        return array_slice($keywordCounts, 0, 20, true);
    }

    /**
     * Calculate sentiment statistics
     */
    protected function calculateSentimentStats(array $results): array
    {
        $validResults = collect($results)->where('error', null)->where('sentiment_score', '!=', null);
        
        if ($validResults->isEmpty()) {
            return [
                'total' => count($results),
                'processed' => 0,
                'avg_sentiment' => 0,
                'distribution' => ['positive' => 0, 'negative' => 0, 'neutral' => 0]
            ];
        }

        $distribution = $validResults->countBy('sentiment_label');

        return [
            'total' => count($results),
            'processed' => $validResults->count(),
            'avg_sentiment' => round($validResults->avg('sentiment_score'), 4),
            'distribution' => [
                'positive' => $distribution['positive'] ?? 0,
                'negative' => $distribution['negative'] ?? 0,
                'neutral' => $distribution['neutral'] ?? 0
            ]
        ];
    }

    /**
     * Calculate standard deviation
     */
    protected function calculateStandardDeviation(array $values): float
    {
        if (empty($values)) return 0;

        $mean = array_sum($values) / count($values);
        $squaredDifferences = array_map(function ($value) use ($mean) {
            return pow($value - $mean, 2);
        }, $values);

        $variance = array_sum($squaredDifferences) / count($values);
        return round(sqrt($variance), 4);
    }

    /**
     * Process sentiment for specific posts (manual trigger)
     */
    public function processPostsSentiment(Collection $posts): array
    {
        Log::info('Processing manual sentiment analysis', [
            'post_count' => $posts->count()
        ]);

        $results = $this->nlpService->processSocialMediaTexts($posts);
        $this->updatePostsSentiment($results);

        return [
            'posts_processed' => count($results),
            'sentiment_stats' => $this->calculateSentimentStats($results),
            'timestamp' => now()
        ];
    }

    /**
     * Get processing pipeline status
     */
    public function getPipelineStatus(): array
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        // Check recent processing activity
        $todayProcessed = SocialMediaPost::whereDate('updated_at', $today)
            ->whereNotNull('sentiment_score')
            ->count();

        $yesterdayProcessed = SocialMediaPost::whereDate('updated_at', $yesterday)
            ->whereNotNull('sentiment_score')
            ->count();

        // Check pending posts
        $pendingPosts = SocialMediaPost::whereNull('sentiment_score')
            ->whereDate('platform_created_at', '>=', $yesterday)
            ->count();

        // Check recent aggregates
        $recentAggregates = DailySentimentAggregate::where('processed_at', '>=', $yesterday)
            ->count();

        // Service health
        $serviceHealth = $this->nlpService->getServiceHealth();

        return [
            'service_status' => $serviceHealth['status'],
            'posts_processed_today' => $todayProcessed,
            'posts_processed_yesterday' => $yesterdayProcessed,
            'pending_posts' => $pendingPosts,
            'recent_aggregates' => $recentAggregates,
            'last_check' => now(),
            'google_nlp_status' => $serviceHealth
        ];
    }
}