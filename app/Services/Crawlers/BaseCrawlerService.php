<?php

declare(strict_types=1);

namespace App\Services\Crawlers;

use App\Models\CrawlerRule;
use Illuminate\Support\Facades\Log;

abstract class BaseCrawlerService
{
    protected array $rateLimitState = [];
    protected array $errorCounts = [];

    public function __construct()
    {
        // Initialize any common crawler settings
    }

    /**
     * Main crawl method that each platform must implement.
     */
    abstract public function crawl(CrawlerRule $rule): array;

    /**
     * Validate that the service can run with current configuration.
     */
    public function validateConfiguration(): array
    {
        return [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
        ];
    }

    /**
     * Get the platform name this crawler handles.
     */
    public function getPlatform(): string
    {
        $className = class_basename(static::class);
        return strtolower(str_replace('CrawlerService', '', $className));
    }

    /**
     * Check if this crawler can handle the specified rule.
     */
    public function canHandleRule(CrawlerRule $rule): bool
    {
        return in_array($this->getPlatform(), $rule->platforms ?? []);
    }

    /**
     * Get health status of this crawler.
     */
    public function getHealthStatus(): array
    {
        $platform = $this->getPlatform();
        
        return [
            'platform' => $platform,
            'status' => 'healthy',
            'last_check' => now()->toISOString(),
            'rate_limit_status' => $this->rateLimitState[$platform] ?? null,
            'error_count_24h' => $this->errorCounts[$platform] ?? 0,
            'configuration_valid' => $this->validateConfiguration()['valid'],
        ];
    }

    /**
     * Handle rate limiting with exponential backoff.
     */
    protected function handleRateLimit(string $platform, int $resetTime = null): void
    {
        $waitTime = $resetTime ? max(0, $resetTime - time()) : 60;
        
        Log::warning("Rate limit hit for {$platform}, waiting {$waitTime} seconds", [
            'platform' => $platform,
            'reset_time' => $resetTime,
            'wait_time' => $waitTime,
        ]);

        if ($waitTime > 0 && $waitTime <= 900) { // Max 15 minutes
            sleep($waitTime);
        }
    }

    /**
     * Record error for monitoring.
     */
    protected function recordError(string $platform, string $error, array $context = []): void
    {
        $this->errorCounts[$platform] = ($this->errorCounts[$platform] ?? 0) + 1;
        
        Log::error("Crawler error for {$platform}: {$error}", array_merge($context, [
            'platform' => $platform,
            'error_count' => $this->errorCounts[$platform],
        ]));
    }

    /**
     * Clean and normalize text content.
     */
    protected function cleanText(string $text): string
    {
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Remove control characters
        $text = preg_replace('/[\x00-\x1F\x7F]/', '', $text);
        
        // Trim
        return trim($text);
    }

    /**
     * Extract hashtags from text.
     */
    protected function extractHashtags(string $text): array
    {
        preg_match_all('/#(\w+)/', $text, $matches);
        return array_unique($matches[1] ?? []);
    }

    /**
     * Extract mentions from text.
     */
    protected function extractMentions(string $text): array
    {
        preg_match_all('/@(\w+)/', $text, $matches);
        return array_unique($matches[1] ?? []);
    }

    /**
     * Extract URLs from text.
     */
    protected function extractUrls(string $text): array
    {
        preg_match_all('/https?:\/\/[^\s]+/', $text, $matches);
        return array_unique($matches[0] ?? []);
    }

    /**
     * Calculate content score based on engagement and other factors.
     */
    protected function calculateContentScore(array $metrics, array $weights = []): float
    {
        $defaultWeights = [
            'likes' => 1.0,
            'shares' => 2.0,
            'comments' => 1.5,
            'views' => 0.1,
        ];

        $weights = array_merge($defaultWeights, $weights);
        $score = 0.0;

        foreach ($weights as $metric => $weight) {
            $score += ($metrics[$metric] ?? 0) * $weight;
        }

        return $score;
    }

    /**
     * Determine if content is likely spam.
     */
    protected function isLikelySpam(string $content, array $metadata = []): bool
    {
        // Check for excessive repetition
        $words = explode(' ', strtolower($content));
        $wordCount = array_count_values($words);
        $maxRepetition = max($wordCount);
        
        if ($maxRepetition > 5 && count($words) > 10) {
            return true;
        }

        // Check for excessive caps
        $capsRatio = strlen(preg_replace('/[^A-Z]/', '', $content)) / strlen($content);
        if ($capsRatio > 0.5 && strlen($content) > 20) {
            return true;
        }

        // Check for excessive punctuation
        $punctuationCount = preg_match_all('/[!?]{3,}/', $content);
        if ($punctuationCount > 2) {
            return true;
        }

        // Check for known spam patterns
        $spamPatterns = [
            '/\b(buy now|click here|limited time|act fast)\b/i',
            '/\b(make money|get rich|earn \$\d+)\b/i',
            '/\b(free gift|no cost|risk free)\b/i',
        ];

        foreach ($spamPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize platform-specific data to common format.
     */
    protected function normalizePostData(array $rawData, string $platform): array
    {
        return [
            'platform' => $platform,
            'external_id' => $rawData['id'] ?? null,
            'content' => $this->cleanText($rawData['content'] ?? $rawData['text'] ?? ''),
            'author_username' => $rawData['author']['username'] ?? $rawData['username'] ?? null,
            'author_display_name' => $rawData['author']['name'] ?? $rawData['display_name'] ?? null,
            'author_id' => $rawData['author']['id'] ?? $rawData['author_id'] ?? null,
            'posted_at' => $rawData['created_at'] ?? $rawData['timestamp'] ?? now(),
            'engagement_metrics' => $this->normalizeEngagementMetrics($rawData, $platform),
            'metadata' => $this->extractMetadata($rawData, $platform),
        ];
    }

    /**
     * Normalize engagement metrics across platforms.
     */
    protected function normalizeEngagementMetrics(array $rawData, string $platform): array
    {
        switch ($platform) {
            case 'twitter':
                return [
                    'likes' => $rawData['public_metrics']['like_count'] ?? 0,
                    'shares' => $rawData['public_metrics']['retweet_count'] ?? 0,
                    'comments' => $rawData['public_metrics']['reply_count'] ?? 0,
                    'quotes' => $rawData['public_metrics']['quote_count'] ?? 0,
                ];

            case 'reddit':
                return [
                    'upvotes' => $rawData['ups'] ?? 0,
                    'downvotes' => $rawData['downs'] ?? 0,
                    'score' => $rawData['score'] ?? 0,
                    'comments' => $rawData['num_comments'] ?? 0,
                ];

            case 'telegram':
                return [
                    'views' => $rawData['views'] ?? 0,
                    'forwards' => $rawData['forwards'] ?? 0,
                    'replies' => $rawData['replies'] ?? 0,
                ];

            default:
                return [];
        }
    }

    /**
     * Extract platform-specific metadata.
     */
    protected function extractMetadata(array $rawData, string $platform): array
    {
        $common = [
            'platform' => $platform,
            'language' => $rawData['lang'] ?? $rawData['language'] ?? 'unknown',
            'extracted_at' => now()->toISOString(),
        ];

        switch ($platform) {
            case 'twitter':
                return array_merge($common, [
                    'tweet_type' => $this->determineTweetType($rawData),
                    'source' => $rawData['source'] ?? null,
                    'geo' => $rawData['geo'] ?? null,
                    'context_annotations' => $rawData['context_annotations'] ?? [],
                ]);

            case 'reddit':
                return array_merge($common, [
                    'subreddit' => $rawData['subreddit'] ?? null,
                    'post_type' => $rawData['is_self'] ? 'text' : 'link',
                    'nsfw' => $rawData['over_18'] ?? false,
                    'stickied' => $rawData['stickied'] ?? false,
                ]);

            case 'telegram':
                return array_merge($common, [
                    'channel_id' => $rawData['chat']['id'] ?? null,
                    'channel_title' => $rawData['chat']['title'] ?? null,
                    'message_type' => $rawData['content_type'] ?? 'text',
                ]);

            default:
                return $common;
        }
    }

    /**
     * Determine tweet type from raw data.
     */
    private function determineTweetType(array $tweetData): string
    {
        if (isset($tweetData['referenced_tweets'])) {
            foreach ($tweetData['referenced_tweets'] as $ref) {
                return $ref['type'] ?? 'original';
            }
        }

        return 'original';
    }

    /**
     * Get recommended crawl frequency based on rule activity.
     */
    protected function getRecommendedCrawlFrequency(CrawlerRule $rule): int
    {
        $stats = $rule->last_crawl_stats ?? [];
        $postsFound = $stats['posts_found'] ?? 0;
        $avgEngagement = $stats['avg_engagement'] ?? 0;

        // High activity = more frequent crawling
        if ($postsFound > 100 || $avgEngagement > 1000) {
            return 5; // Every 5 minutes
        }

        if ($postsFound > 50 || $avgEngagement > 500) {
            return 15; // Every 15 minutes
        }

        if ($postsFound > 10 || $avgEngagement > 100) {
            return 60; // Every hour
        }

        return 240; // Every 4 hours for low activity
    }

    /**
     * Prepare crawler statistics for reporting.
     */
    protected function prepareCrawlStats(array $results, CrawlerRule $rule): array
    {
        return [
            'platform' => $this->getPlatform(),
            'rule_id' => $rule->id,
            'rule_name' => $rule->name,
            'execution_time' => $results['execution_time'] ?? 0,
            'posts_found' => $results['posts_found'] ?? 0,
            'posts_processed' => $results['posts_processed'] ?? 0,
            'posts_stored' => $results['posts_stored'] ?? 0,
            'error_count' => count($results['errors'] ?? []),
            'success_rate' => $this->calculateSuccessRate($results),
            'efficiency_score' => $this->calculateEfficiencyScore($results),
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Calculate success rate from crawl results.
     */
    private function calculateSuccessRate(array $results): float
    {
        $total = $results['posts_found'] ?? 0;
        $processed = $results['posts_processed'] ?? 0;

        return $total > 0 ? round(($processed / $total) * 100, 2) : 0;
    }

    /**
     * Calculate efficiency score from crawl results.
     */
    private function calculateEfficiencyScore(array $results): float
    {
        $processed = $results['posts_processed'] ?? 0;
        $stored = $results['posts_stored'] ?? 0;
        $executionTime = $results['execution_time'] ?? 1;

        $storageRate = $processed > 0 ? ($stored / $processed) : 0;
        $processingSpeed = $processed / $executionTime;

        return round(($storageRate * 50) + (min($processingSpeed, 10) * 5), 2);
    }
}
