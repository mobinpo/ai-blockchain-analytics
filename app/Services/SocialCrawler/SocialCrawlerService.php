<?php

declare(strict_types=1);

namespace App\Services\SocialCrawler;

use App\Models\SocialPost;
use App\Models\KeywordRule;
use App\Models\KeywordMatch;
use App\Models\CrawlerConfig;
use App\Models\CrawlerJob;
use App\Services\SocialCrawler\Crawlers\TwitterCrawler;
use App\Services\SocialCrawler\Crawlers\RedditCrawler;
use App\Services\SocialCrawler\Crawlers\TelegramCrawler;
use App\Services\SocialCrawler\KeywordEngine;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class SocialCrawlerService
{
    private array $crawlers;
    private KeywordEngine $keywordEngine;

    public function __construct(
        TwitterCrawler $twitterCrawler,
        RedditCrawler $redditCrawler,
        TelegramCrawler $telegramCrawler,
        KeywordEngine $keywordEngine
    ) {
        $this->crawlers = [
            'twitter' => $twitterCrawler,
            'reddit' => $redditCrawler,
            'telegram' => $telegramCrawler,
        ];
        
        $this->keywordEngine = $keywordEngine;
    }

    /**
     * Run scheduled crawling for all enabled platforms
     */
    public function runScheduledCrawl(): array
    {
        $results = [];
        $configs = CrawlerConfig::where('enabled', true)
            ->where(function ($query) {
                $query->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', now());
            })
            ->get();

        foreach ($configs as $config) {
            try {
                $result = $this->crawlPlatform($config);
                $results[$config->platform] = $result;
                
                // Update next run time
                $config->update([
                    'last_run_at' => now(),
                    'next_run_at' => now()->addHour(),
                ]);
                
            } catch (\Exception $e) {
                Log::error("Crawl failed for {$config->platform}", [
                    'config_id' => $config->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                $results[$config->platform] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Crawl specific platform with configuration
     */
    public function crawlPlatform(CrawlerConfig $config): array
    {
        $crawler = $this->getCrawler($config->platform);
        
        // Create crawler job
        $job = CrawlerJob::create([
            'crawler_config_id' => $config->id,
            'job_type' => 'scheduled',
            'parameters' => [
                'keywords' => $this->getActiveKeywords($config->platform),
                'max_results' => $config->max_results_per_run,
            ],
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            // Get active keyword rules for this platform
            $keywordRules = KeywordRule::active()
                ->platform($config->platform)
                ->orderByDesc('priority')
                ->get();

            if ($keywordRules->isEmpty()) {
                throw new \Exception("No active keyword rules found for {$config->platform}");
            }

            // Extract all keywords for search
            $searchKeywords = $keywordRules->pluck('keywords')
                ->flatten()
                ->unique()
                ->values()
                ->toArray();

            // Perform crawl
            $crawlResults = $crawler->crawl([
                'keywords' => $searchKeywords,
                'max_results' => $config->max_results_per_run,
                'since' => $config->last_run_at ?? now()->subHour(),
            ]);

            $processedPosts = [];
            $matchCount = 0;

            foreach ($crawlResults as $postData) {
                $post = $this->processPost($postData, $config->platform, $keywordRules);
                if ($post) {
                    $processedPosts[] = $post->id;
                    $matchCount += $post->keywordMatches()->count();
                }
            }

            // Update job with success
            $job->update([
                'status' => 'completed',
                'completed_at' => now(),
                'posts_found' => count($crawlResults),
                'posts_processed' => count($processedPosts),
                'execution_stats' => [
                    'keyword_matches' => $matchCount,
                    'processing_time_ms' => now()->diffInMilliseconds($job->started_at),
                ],
            ]);

            return [
                'success' => true,
                'posts_found' => count($crawlResults),
                'posts_processed' => count($processedPosts),
                'keyword_matches' => $matchCount,
                'job_id' => $job->id,
            ];

        } catch (\Exception $e) {
            $job->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_details' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            ]);

            throw $e;
        }
    }

    /**
     * Process a single post from crawler results
     */
    protected function processPost(array $postData, string $platform, Collection $keywordRules): ?SocialPost
    {
        // Check if post already exists
        $existingPost = SocialPost::where('external_id', $postData['external_id'])
            ->where('platform', $platform)
            ->first();

        if ($existingPost) {
            // Update engagement metrics if they've changed
            if ($existingPost->engagement_count !== $postData['engagement_count']) {
                $existingPost->update([
                    'engagement_count' => $postData['engagement_count'],
                    'share_count' => $postData['share_count'] ?? 0,
                    'comment_count' => $postData['comment_count'] ?? 0,
                ]);
            }
            return $existingPost;
        }

        // Create new post
        $post = SocialPost::create([
            'external_id' => $postData['external_id'],
            'platform' => $platform,
            'source_url' => $postData['source_url'],
            'author_username' => $postData['author_username'] ?? null,
            'author_display_name' => $postData['author_display_name'] ?? null,
            'author_id' => $postData['author_id'] ?? null,
            'content' => $postData['content'],
            'media_urls' => $postData['media_urls'] ?? [],
            'engagement_count' => $postData['engagement_count'] ?? 0,
            'share_count' => $postData['share_count'] ?? 0,
            'comment_count' => $postData['comment_count'] ?? 0,
            'published_at' => Carbon::parse($postData['published_at']),
            'raw_data' => $postData,
            'is_processed' => false,
        ]);

        // Apply keyword matching
        $this->applyKeywordMatching($post, $keywordRules);

        // Calculate initial relevance score
        $post->update([
            'relevance_score' => $this->calculateRelevanceScore($post),
            'is_processed' => true,
        ]);

        return $post;
    }

    /**
     * Apply keyword matching to a post
     */
    protected function applyKeywordMatching(SocialPost $post, Collection $keywordRules): void
    {
        $allMatches = [];
        
        foreach ($keywordRules as $rule) {
            $matches = $rule->matches($post->content, $post->platform);
            
            foreach ($matches as $match) {
                // Create keyword match record
                KeywordMatch::create([
                    'social_post_id' => $post->id,
                    'keyword_rule_id' => $rule->id,
                    'matched_keyword' => $match['keyword'],
                    'match_count' => $match['count'],
                    'match_positions' => $match['positions'],
                    'confidence_score' => $match['confidence'],
                ]);
                
                $allMatches[] = $match['keyword'];
            }
        }

        // Store matched keywords in post for quick access
        if (!empty($allMatches)) {
            $post->update(['matched_keywords' => array_unique($allMatches)]);
        }
    }

    /**
     * Calculate relevance score for a post
     */
    protected function calculateRelevanceScore(SocialPost $post): float
    {
        $score = 0.1; // Base score

        // Keyword match score (0.0 - 0.4)
        $keywordCount = $post->keywordMatches()->count();
        $score += min(0.4, $keywordCount * 0.1);

        // Engagement score (0.0 - 0.3)
        $engagementScore = min(0.3, $post->total_engagement / 1000);
        $score += $engagementScore;

        // Recency score (0.0 - 0.2)
        $hoursSincePublished = $post->published_at->diffInHours(now());
        $recencyScore = max(0, 0.2 - ($hoursSincePublished / 100));
        $score += $recencyScore;

        // Content quality score (0.0 - 0.1)
        $contentLength = strlen($post->content);
        $qualityScore = min(0.1, $contentLength / 2800); // ~280 chars = 0.01
        $score += $qualityScore;

        return min(1.0, $score);
    }

    /**
     * Get active keywords for platform
     */
    protected function getActiveKeywords(string $platform): array
    {
        return KeywordRule::active()
            ->platform($platform)
            ->get()
            ->pluck('keywords')
            ->flatten()
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Get crawler instance for platform
     */
    protected function getCrawler(string $platform): CrawlerInterface
    {
        if (!isset($this->crawlers[$platform])) {
            throw new \InvalidArgumentException("Crawler not available for platform: {$platform}");
        }

        return $this->crawlers[$platform];
    }

    /**
     * Search for specific keywords across platforms
     */
    public function searchKeywords(array $keywords, array $platforms = null, array $options = []): array
    {
        $platforms = $platforms ?? array_keys($this->crawlers);
        $results = [];

        foreach ($platforms as $platform) {
            try {
                $crawler = $this->getCrawler($platform);
                $config = CrawlerConfig::where('platform', $platform)
                    ->where('enabled', true)
                    ->first();

                if (!$config) {
                    continue;
                }

                $crawlResults = $crawler->search([
                    'keywords' => $keywords,
                    'max_results' => $options['max_results'] ?? 50,
                    'since' => $options['since'] ?? now()->subHours(24),
                ]);

                $results[$platform] = [
                    'posts_found' => count($crawlResults),
                    'posts' => array_map(function ($postData) use ($platform) {
                        return $this->processPost($postData, $platform, collect());
                    }, $crawlResults)
                ];

            } catch (\Exception $e) {
                Log::error("Keyword search failed for {$platform}", [
                    'keywords' => $keywords,
                    'error' => $e->getMessage()
                ]);
                
                $results[$platform] = [
                    'posts_found' => 0,
                    'posts' => [],
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Monitor specific accounts across platforms
     */
    public function monitorAccounts(array $accounts): array
    {
        $results = [];

        foreach ($accounts as $account) {
            $platform = $account['platform'];
            $username = $account['username'];

            try {
                $crawler = $this->getCrawler($platform);
                $posts = $crawler->getUserPosts($username, [
                    'max_results' => 20,
                    'since' => now()->subHours(24),
                ]);

                $keywordRules = KeywordRule::active()
                    ->platform($platform)
                    ->get();

                $processedPosts = [];
                foreach ($posts as $postData) {
                    $post = $this->processPost($postData, $platform, $keywordRules);
                    if ($post) {
                        $processedPosts[] = $post;
                    }
                }

                $results[$platform][$username] = [
                    'posts_found' => count($posts),
                    'posts_processed' => count($processedPosts),
                    'posts' => $processedPosts
                ];

            } catch (\Exception $e) {
                Log::error("Account monitoring failed", [
                    'platform' => $platform,
                    'username' => $username,
                    'error' => $e->getMessage()
                ]);

                $results[$platform][$username] = [
                    'posts_found' => 0,
                    'posts_processed' => 0,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Get trending topics from recent posts
     */
    public function getTrendingTopics(string $platform = null, int $hours = 24): array
    {
        $query = SocialPost::query()
            ->where('published_at', '>=', now()->subHours($hours))
            ->where('is_processed', true);

        if ($platform) {
            $query->where('platform', $platform);
        }

        $posts = $query->with('keywordMatches')->get();
        
        $keywordCounts = [];
        $keywordEngagement = [];

        foreach ($posts as $post) {
            foreach ($post->keywordMatches as $match) {
                $keyword = $match->matched_keyword;
                $keywordCounts[$keyword] = ($keywordCounts[$keyword] ?? 0) + 1;
                $keywordEngagement[$keyword] = ($keywordEngagement[$keyword] ?? 0) + $post->total_engagement;
            }
        }

        // Sort by mention count and engagement
        $trending = collect($keywordCounts)
            ->map(function ($count, $keyword) use ($keywordEngagement) {
                return [
                    'keyword' => $keyword,
                    'mention_count' => $count,
                    'total_engagement' => $keywordEngagement[$keyword] ?? 0,
                    'avg_engagement' => $count > 0 ? ($keywordEngagement[$keyword] ?? 0) / $count : 0,
                    'trend_score' => $count * log($keywordEngagement[$keyword] ?? 1 + 1)
                ];
            })
            ->sortByDesc('trend_score')
            ->values()
            ->take(50)
            ->toArray();

        return $trending;
    }

    /**
     * Get crawler statistics
     */
    public function getStatistics(int $days = 7): array
    {
        $startDate = now()->subDays($days);

        $stats = [
            'total_posts' => SocialPost::where('created_at', '>=', $startDate)->count(),
            'posts_by_platform' => SocialPost::selectRaw('platform, COUNT(*) as count')
                ->where('created_at', '>=', $startDate)
                ->groupBy('platform')
                ->pluck('count', 'platform')
                ->toArray(),
            'keyword_matches' => KeywordMatch::whereHas('socialPost', function ($query) use ($startDate) {
                $query->where('created_at', '>=', $startDate);
            })->count(),
            'avg_relevance_score' => SocialPost::where('created_at', '>=', $startDate)
                ->avg('relevance_score'),
            'top_keywords' => KeywordMatch::selectRaw('matched_keyword, COUNT(*) as count')
                ->whereHas('socialPost', function ($query) use ($startDate) {
                    $query->where('created_at', '>=', $startDate);
                })
                ->groupBy('matched_keyword')
                ->orderByDesc('count')
                ->limit(20)
                ->get()
                ->toArray(),
            'crawler_jobs' => [
                'completed' => CrawlerJob::where('created_at', '>=', $startDate)
                    ->where('status', 'completed')
                    ->count(),
                'failed' => CrawlerJob::where('created_at', '>=', $startDate)
                    ->where('status', 'failed')
                    ->count(),
                'avg_processing_time' => CrawlerJob::where('created_at', '>=', $startDate)
                    ->where('status', 'completed')
                    ->whereNotNull('started_at')
                    ->whereNotNull('completed_at')
                    ->selectRaw('AVG(TIMESTAMPDIFF(MICROSECOND, started_at, completed_at) / 1000) as avg_ms')
                    ->value('avg_ms') ?? 0
            ]
        ];

        return $stats;
    }
}