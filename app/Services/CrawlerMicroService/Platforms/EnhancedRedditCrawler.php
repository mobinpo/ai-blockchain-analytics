<?php

declare(strict_types=1);

namespace App\Services\CrawlerMicroService\Platforms;

use App\Models\SocialMediaPost;
use App\Models\KeywordMatch;
use App\Services\CrawlerMicroService\Engine\AdvancedKeywordEngine;
use App\Services\Concerns\UsesProxy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

final class EnhancedRedditCrawler implements PlatformCrawlerInterface
{
    use UsesProxy;

    private string $clientId;
    private string $clientSecret;
    private string $username;
    private string $password;
    private string $userAgent;
    private ?string $accessToken = null;
    private AdvancedKeywordEngine $keywordEngine;
    private array $config;
    private array $rateLimits;

    public function __construct(AdvancedKeywordEngine $keywordEngine, array $config = [])
    {
        $this->keywordEngine = $keywordEngine;
        $this->config = array_merge([
            'client_id' => config('services.reddit.client_id'),
            'client_secret' => config('services.reddit.client_secret'),
            'username' => config('services.reddit.username'),
            'password' => config('services.reddit.password'),
            'user_agent' => config('services.reddit.user_agent', 'AIBlockchainAnalytics/1.0'),
            'rate_limit_per_minute' => 60,
            'max_posts_per_subreddit' => 100,
            'sort_methods' => ['hot', 'new', 'top'],
            'time_filters' => ['day', 'week', 'month'],
            'include_comments' => false,
            'min_score' => 0,
            'exclude_nsfw' => true,
        ], $config);

        $this->clientId = $this->config['client_id'];
        $this->clientSecret = $this->config['client_secret'];
        $this->username = $this->config['username'];
        $this->password = $this->config['password'];
        $this->userAgent = $this->config['user_agent'];
        
        $this->rateLimits = [
            'per_minute' => $this->config['rate_limit_per_minute'],
        ];

        if (!$this->clientId || !$this->clientSecret) {
            throw new Exception('Reddit API credentials not configured');
        }
    }

    /**
     * Main crawl method for Reddit
     */
    public function crawl(array $options = []): array
    {
        if (!$this->authenticate()) {
            throw new Exception('Reddit authentication failed');
        }

        $subreddits = $options['subreddits'] ?? $this->getDefaultSubreddits();
        $keywords = $options['keywords'] ?? null;
        $results = [];

        Log::info('Starting Reddit crawl', [
            'subreddits' => count($subreddits),
            'keywords' => $keywords ? count($keywords) : 'auto-detect'
        ]);

        foreach ($subreddits as $subreddit) {
            if (!$this->canMakeRequest()) {
                Log::warning('Reddit rate limit reached, skipping remaining subreddits');
                break;
            }

            try {
                $subredditResults = $this->crawlSubreddit($subreddit, $keywords);
                $results[$subreddit] = $subredditResults;
                
                $this->updateRateLimit();
                sleep(1); // Reddit requires 1 second between requests

            } catch (Exception $e) {
                Log::error('Reddit subreddit crawl failed', [
                    'subreddit' => $subreddit,
                    'error' => $e->getMessage()
                ]);
                $results[$subreddit] = ['error' => $e->getMessage(), 'posts' => []];
            }
        }

        return $results;
    }

    /**
     * Search for content by keywords
     */
    public function searchByKeywords(array $keywords, array $channels = null): array
    {
        if (!$this->authenticate()) {
            throw new Exception('Reddit authentication failed');
        }

        $subreddits = $channels ?? $this->getDefaultSubreddits();
        $results = [];

        foreach ($keywords as $keyword) {
            $keywordResults = [];
            
            foreach ($subreddits as $subreddit) {
                if (!$this->canMakeRequest()) {
                    break;
                }

                try {
                    $posts = $this->searchInSubreddit($keyword, $subreddit);
                    $processed = $this->processPosts($posts, "search:{$keyword} in r/{$subreddit}");
                    
                    $keywordResults[$subreddit] = [
                        'posts' => $processed,
                        'count' => count($processed),
                    ];

                    $this->updateRateLimit();
                    sleep(1);

                } catch (Exception $e) {
                    Log::error('Reddit keyword search failed', [
                        'keyword' => $keyword,
                        'subreddit' => $subreddit,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $results[$keyword] = $keywordResults;
        }

        return $results;
    }

    /**
     * Crawl a specific subreddit
     */
    public function crawlSubreddit(string $subreddit, ?array $keywords = null): array
    {
        $allPosts = [];
        $sortMethods = $this->config['sort_methods'];
        
        foreach ($sortMethods as $sortMethod) {
            try {
                $posts = $this->getSubredditPosts($subreddit, $sortMethod);
                $processed = $this->processPosts($posts, "r/{$subreddit}:{$sortMethod}");
                $allPosts = array_merge($allPosts, $processed);

            } catch (Exception $e) {
                Log::error('Failed to crawl subreddit with sort method', [
                    'subreddit' => $subreddit,
                    'sort_method' => $sortMethod,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Remove duplicates by post ID
        $uniquePosts = [];
        $seenIds = [];
        
        foreach ($allPosts as $post) {
            $postId = $post['platform_id'] ?? null;
            if ($postId && !in_array($postId, $seenIds)) {
                $uniquePosts[] = $post;
                $seenIds[] = $postId;
            }
        }

        return [
            'subreddit_info' => $this->getSubredditInfo($subreddit),
            'posts' => $uniquePosts,
            'stats' => [
                'total_processed' => count($uniquePosts),
                'sort_methods_used' => $sortMethods,
                'keyword_matches' => array_sum(array_column($uniquePosts, 'match_count')),
            ]
        ];
    }

    /**
     * Authenticate with Reddit API
     */
    private function authenticate(): bool
    {
        $cacheKey = 'reddit_access_token';
        
        $this->accessToken = Cache::get($cacheKey);
        if ($this->accessToken) {
            return true;
        }

        try {
            $response = $this->getHttpClient()
                ->withBasicAuth($this->clientId, $this->clientSecret)
                ->withHeaders(['User-Agent' => $this->userAgent])
                ->asForm()
                ->post('https://www.reddit.com/api/v1/access_token', [
                    'grant_type' => 'password',
                    'username' => $this->username,
                    'password' => $this->password,
                ]);

            if (!$response->successful()) {
                throw new Exception('Reddit authentication failed: ' . $response->body());
            }

            $data = $response->json();
            $this->accessToken = $data['access_token'];
            
            // Cache token for 50 minutes (Reddit tokens expire in 1 hour)
            Cache::put($cacheKey, $this->accessToken, 3000);
            
            Log::info('Reddit authentication successful');
            return true;
            
        } catch (Exception $e) {
            Log::error('Reddit authentication error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get subreddit information
     */
    private function getSubredditInfo(string $subreddit): ?array
    {
        try {
            $response = $this->makeApiRequest("r/{$subreddit}/about");
            
            if (isset($response['data'])) {
                $data = $response['data'];
                return [
                    'name' => $data['display_name'],
                    'title' => $data['title'],
                    'description' => $data['public_description'],
                    'subscribers' => $data['subscribers'],
                    'active_users' => $data['active_user_count'] ?? null,
                    'created' => $data['created_utc'],
                    'nsfw' => $data['over18'] ?? false,
                ];
            }

        } catch (Exception $e) {
            Log::warning('Failed to get subreddit info', [
                'subreddit' => $subreddit,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * Get posts from subreddit
     */
    private function getSubredditPosts(string $subreddit, string $sortMethod = 'hot', int $limit = null): array
    {
        $limit = $limit ?? $this->config['max_posts_per_subreddit'];
        
        $params = [
            'limit' => min($limit, 100),
            'raw_json' => 1,
        ];

        // Add time filter for relevant sort methods
        if (in_array($sortMethod, ['top', 'controversial'])) {
            $params['t'] = $this->config['time_filters'][0] ?? 'day';
        }

        $response = $this->makeApiRequest("r/{$subreddit}/{$sortMethod}", $params);
        
        return $response;
    }

    /**
     * Search within a specific subreddit
     */
    private function searchInSubreddit(string $query, string $subreddit, int $limit = 50): array
    {
        $params = [
            'q' => $query,
            'limit' => min($limit, 100),
            'sort' => 'relevance',
            'restrict_sr' => 'true',
            'raw_json' => 1,
        ];

        $response = $this->makeApiRequest("r/{$subreddit}/search", $params);
        
        return $response;
    }

    /**
     * Process Reddit posts
     */
    private function processPosts(array $apiResponse, string $source): array
    {
        if (!isset($apiResponse['data']['children']) || empty($apiResponse['data']['children'])) {
            return [];
        }

        $posts = $apiResponse['data']['children'];
        $processed = [];

        foreach ($posts as $postWrapper) {
            try {
                $post = $postWrapper['data'];
                
                // Skip removed, deleted, or filtered posts
                if ($this->shouldSkipPost($post)) {
                    continue;
                }

                $content = $this->extractContent($post);
                if (!$content) {
                    continue;
                }

                // Match against keyword rules
                $matches = $this->keywordEngine->matchContent($content, 'reddit', [
                    'engagement_score' => $this->calculateEngagementScore($post),
                    'subreddit' => $post['subreddit'],
                    'score' => $post['score'],
                    'created_utc' => $post['created_utc'],
                ]);

                if (empty($matches)) {
                    continue; // Skip if no keyword matches
                }

                $socialPost = $this->createSocialMediaPost($post, $source, $content, $matches);
                $this->storeKeywordMatches($socialPost, $matches);
                
                $processed[] = array_merge($socialPost->toArray(), [
                    'match_count' => count($matches),
                    'matches' => $matches,
                ]);

                // Check for alerts
                if ($this->keywordEngine->shouldTriggerAlert($matches, 'reddit')) {
                    $this->triggerAlert($socialPost, $matches);
                }

            } catch (Exception $e) {
                Log::error('Error processing Reddit post', [
                    'post_id' => $post['id'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $processed;
    }

    /**
     * Check if post should be skipped
     */
    private function shouldSkipPost(array $post): bool
    {
        // Skip removed or deleted posts
        if (($post['removed_by_category'] ?? false) || $post['author'] === '[deleted]') {
            return true;
        }

        // Skip NSFW if configured
        if ($this->config['exclude_nsfw'] && ($post['over_18'] ?? false)) {
            return true;
        }

        // Skip low-score posts
        if (($post['score'] ?? 0) < $this->config['min_score']) {
            return true;
        }

        // Skip posts without content
        if (empty($post['title']) && empty($post['selftext'])) {
            return true;
        }

        return false;
    }

    /**
     * Extract content from post
     */
    private function extractContent(array $post): ?string
    {
        $content = $post['title'] ?? '';
        
        // Add selftext if available
        if (!empty($post['selftext'])) {
            $content .= "\n\n" . $post['selftext'];
        }

        // Clean and validate content
        $content = trim($content);
        
        return $content ?: null;
    }

    /**
     * Create social media post from Reddit post
     */
    private function createSocialMediaPost(array $post, string $source, string $content, array $matches): SocialMediaPost
    {
        $metadata = [
            'subreddit' => $post['subreddit'],
            'title' => $post['title'],
            'url' => $post['url'] ?? null,
            'score' => $post['score'],
            'upvote_ratio' => $post['upvote_ratio'] ?? null,
            'num_comments' => $post['num_comments'],
            'gilded' => $post['gilded'] ?? 0,
            'awards' => $post['total_awards_received'] ?? 0,
            'flair_text' => $post['link_flair_text'],
            'nsfw' => $post['over_18'] ?? false,
            'source' => $source,
            'post_hint' => $post['post_hint'] ?? null,
            'is_self' => $post['is_self'] ?? false,
            'domain' => $post['domain'] ?? null,
            'thumbnail' => $post['thumbnail'] ?? null,
            'created_utc' => $post['created_utc'],
            'num_crossposts' => $post['num_crossposts'] ?? 0,
            'distinguished' => $post['distinguished'] ?? null,
            'stickied' => $post['stickied'] ?? false,
        ];

        $engagementScore = $this->calculateEngagementScore($post);

        return SocialMediaPost::updateOrCreate(
            [
                'platform' => 'reddit',
                'platform_id' => $post['id']
            ],
            [
                'author_username' => $post['author'],
                'author_id' => $post['author_fullname'] ?? null,
                'content' => $content,
                'metadata' => $metadata,
                'url' => 'https://reddit.com' . $post['permalink'],
                'published_at' => date('Y-m-d H:i:s', $post['created_utc']),
                'engagement_score' => $engagementScore,
                'sentiment_score' => 0, // Will be calculated later if needed
                'sentiment_label' => 'neutral',
                'matched_keywords' => array_column($matches, 'keyword'),
            ]
        );
    }

    /**
     * Calculate engagement score for Reddit post
     */
    private function calculateEngagementScore(array $post): int
    {
        $score = max(0, $post['score'] ?? 0);
        $comments = $post['num_comments'] ?? 0;
        $awards = $post['total_awards_received'] ?? 0;
        $gilded = $post['gilded'] ?? 0;
        $crossposts = $post['num_crossposts'] ?? 0;

        // Weighted engagement score for Reddit
        $engagementScore = $score + 
                          ($comments * 2) + 
                          ($awards * 5) + 
                          ($gilded * 10) + 
                          ($crossposts * 3);

        // Boost for high upvote ratio
        $upvoteRatio = $post['upvote_ratio'] ?? 0.5;
        if ($upvoteRatio > 0.8) {
            $engagementScore = (int) ($engagementScore * 1.2);
        }

        return $engagementScore;
    }

    /**
     * Store keyword matches
     */
    private function storeKeywordMatches(SocialMediaPost $post, array $matches): void
    {
        foreach ($matches as $match) {
            KeywordMatch::create([
                'social_media_post_id' => $post->id,
                'keyword' => $match['keyword'],
                'keyword_category' => $match['category'],
                'match_count' => 1,
                'priority' => $match['priority'],
                'context' => $match['context'] ?? null,
                'position' => $match['position'] ?? null,
                'score' => $match['score'] ?? 0,
            ]);
        }
    }

    /**
     * Make API request to Reddit
     */
    private function makeApiRequest(string $endpoint, array $params = []): array
    {
        $url = "https://oauth.reddit.com/{$endpoint}";
        
        $response = $this->getHttpClient()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'User-Agent' => $this->userAgent,
            ])
            ->timeout(30)
            ->get($url, $params);

        if (!$response->successful()) {
            throw new Exception("Reddit API error: " . $response->body());
        }

        return $response->json();
    }

    /**
     * Get default subreddits to crawl
     */
    private function getDefaultSubreddits(): array
    {
        return config('crawler_microservice.reddit.subreddits', [
            'cryptocurrency',
            'ethereum',
            'bitcoin',
            'defi',
            'ethfinance',
            'ethtrader',
            'smartcontracts',
            'web3',
            'nft',
            'solidity',
        ]);
    }

    /**
     * Check rate limiting
     */
    private function canMakeRequest(): bool
    {
        $key = 'reddit_rate_limit_' . date('Y-m-d-H-i');
        $currentCount = Cache::get($key, 0);
        return $currentCount < $this->rateLimits['per_minute'];
    }

    /**
     * Update rate limit counter
     */
    private function updateRateLimit(): void
    {
        $key = 'reddit_rate_limit_' . date('Y-m-d-H-i');
        Cache::increment($key, 1);
        Cache::put($key, Cache::get($key), 60); // 1 minute TTL
    }

    /**
     * Trigger alert for critical matches
     */
    private function triggerAlert(SocialMediaPost $post, array $matches): void
    {
        Log::alert('Critical keyword alert triggered', [
            'platform' => 'reddit',
            'post_id' => $post->id,
            'subreddit' => $post->metadata['subreddit'] ?? null,
            'matches' => array_column($matches, 'keyword'),
            'content' => substr($post->content, 0, 200) . '...',
        ]);
    }
}
