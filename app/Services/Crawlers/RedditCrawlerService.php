<?php

declare(strict_types=1);

namespace App\Services\Crawlers;

use App\Models\CrawlerRule;
use App\Models\SocialMediaPost;
use App\Services\ApiCacheService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class RedditCrawlerService extends BaseCrawlerService
{
    private const BASE_URL = 'https://www.reddit.com';
    private const OAUTH_URL = 'https://oauth.reddit.com';
    private const RATE_LIMIT_DELAY = 2; // 2 seconds between requests per Reddit API rules

    public function __construct(
        private readonly ApiCacheService $cacheService
    ) {
        parent::__construct();
    }

    /**
     * Crawl Reddit based on crawler rules.
     */
    public function crawl(CrawlerRule $rule): array
    {
        $this->validateRedditCredentials();

        $results = [
            'platform' => 'reddit',
            'rule_id' => $rule->id,
            'posts_found' => 0,
            'posts_processed' => 0,
            'posts_stored' => 0,
            'errors' => [],
            'subreddits_crawled' => [],
            'execution_time' => 0,
        ];

        $startTime = microtime(true);

        try {
            $config = $rule->getPlatformConfig('reddit');
            $maxResults = min($config['max_results'] ?? 100, $rule->getRemainingHourlyQuota());

            if ($maxResults <= 0) {
                $results['errors'][] = 'Rate limit quota exhausted';
                return $results;
            }

            // Get access token
            $accessToken = $this->getAccessToken();

            // Determine search strategy
            $strategy = $config['strategy'] ?? 'search';
            
            switch ($strategy) {
                case 'search':
                    $searchResults = $this->searchReddit($rule, $accessToken, $maxResults);
                    break;
                case 'subreddits':
                    $searchResults = $this->crawlSubreddits($rule, $accessToken, $maxResults);
                    break;
                case 'users':
                    $searchResults = $this->crawlUsers($rule, $accessToken, $maxResults);
                    break;
                default:
                    $searchResults = $this->searchReddit($rule, $accessToken, $maxResults);
            }

            $results['posts_found'] = count($searchResults['posts']);
            $results['subreddits_crawled'] = $searchResults['subreddits'];

            // Process and store posts
            if (!empty($searchResults['posts'])) {
                $processed = $this->processPosts($searchResults['posts'], $rule);
                $results['posts_processed'] = $processed['processed'];
                $results['posts_stored'] = $processed['stored'];
                $results['errors'] = array_merge($results['errors'], $processed['errors']);
            }

            // Update rule statistics
            $rule->updateCrawlStats([
                'posts_found' => $results['posts_found'],
                'posts_processed' => $results['posts_processed'],
                'platform' => 'reddit',
                'strategy' => $strategy,
                'subreddits' => $results['subreddits_crawled'],
                'timestamp' => now()->toISOString(),
            ]);

            Log::info('Reddit crawl completed', [
                'rule_id' => $rule->id,
                'posts_found' => $results['posts_found'],
                'posts_stored' => $results['posts_stored'],
            ]);

        } catch (\Exception $e) {
            $results['errors'][] = $e->getMessage();
            Log::error('Reddit crawl failed', [
                'rule_id' => $rule->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        $results['execution_time'] = round(microtime(true) - $startTime, 2);
        return $results;
    }

    /**
     * Search Reddit using search API.
     */
    private function searchReddit(CrawlerRule $rule, string $accessToken, int $maxResults): array
    {
        $query = $this->buildSearchQuery($rule);
        $config = $rule->getPlatformConfig('reddit');
        
        $params = [
            'q' => $query,
            'limit' => min($maxResults, 100),
            'sort' => $config['sort'] ?? 'relevance',
            'type' => $config['type'] ?? 'link',
            't' => $config['time'] ?? 'day', // day, week, month, year, all
        ];

        if (isset($config['after'])) {
            $params['after'] = $config['after'];
        }

        $cacheKey = 'reddit_search_' . md5(serialize($params));
        $response = $this->cacheService->cacheOrRetrieve(
            'reddit',
            'search',
            'search_results',
            fn() => $this->makeRedditApiCall('search', $params, $accessToken),
            $params,
            $cacheKey,
            300 // 5 minutes cache
        );

        return [
            'posts' => $this->extractPostsFromResponse($response),
            'subreddits' => $this->extractSubredditsFromResponse($response),
        ];
    }

    /**
     * Crawl specific subreddits.
     */
    private function crawlSubreddits(CrawlerRule $rule, string $accessToken, int $maxResults): array
    {
        $config = $rule->getPlatformConfig('reddit');
        $subreddits = $config['subreddits'] ?? [];
        
        if (empty($subreddits)) {
            // Default crypto subreddits if none specified
            $subreddits = ['cryptocurrency', 'bitcoin', 'ethereum', 'defi', 'altcoin'];
        }

        $allPosts = [];
        $crawledSubreddits = [];
        $postsPerSubreddit = max(1, intval($maxResults / count($subreddits)));

        foreach ($subreddits as $subreddit) {
            try {
                $params = [
                    'limit' => $postsPerSubreddit,
                    'sort' => $config['sort'] ?? 'hot',
                    't' => $config['time'] ?? 'day',
                ];

                $cacheKey = "reddit_subreddit_{$subreddit}_" . md5(serialize($params));
                $response = $this->cacheService->cacheOrRetrieve(
                    'reddit',
                    "r/{$subreddit}/" . ($config['sort'] ?? 'hot'),
                    'subreddit_posts',
                    fn() => $this->makeRedditApiCall("r/{$subreddit}/" . ($config['sort'] ?? 'hot'), $params, $accessToken),
                    $params,
                    $cacheKey,
                    600 // 10 minutes cache
                );

                $posts = $this->extractPostsFromResponse($response);
                $allPosts = array_merge($allPosts, $posts);
                $crawledSubreddits[] = $subreddit;

                // Rate limiting
                sleep(self::RATE_LIMIT_DELAY);

            } catch (\Exception $e) {
                Log::warning("Failed to crawl subreddit: {$subreddit}", [
                    'error' => $e->getMessage(),
                    'rule_id' => $rule->id,
                ]);
            }
        }

        return [
            'posts' => array_slice($allPosts, 0, $maxResults),
            'subreddits' => $crawledSubreddits,
        ];
    }

    /**
     * Crawl specific Reddit users.
     */
    private function crawlUsers(CrawlerRule $rule, string $accessToken, int $maxResults): array
    {
        $config = $rule->getPlatformConfig('reddit');
        $users = $config['users'] ?? [];
        
        $allPosts = [];
        $postsPerUser = max(1, intval($maxResults / max(1, count($users))));

        foreach ($users as $username) {
            try {
                $params = [
                    'limit' => $postsPerUser,
                    'sort' => $config['sort'] ?? 'new',
                    't' => $config['time'] ?? 'week',
                ];

                $cacheKey = "reddit_user_{$username}_" . md5(serialize($params));
                $response = $this->cacheService->cacheOrRetrieve(
                    'reddit',
                    "user/{$username}/submitted",
                    'user_posts',
                    fn() => $this->makeRedditApiCall("user/{$username}/submitted", $params, $accessToken),
                    $params,
                    $cacheKey,
                    1800 // 30 minutes cache
                );

                $posts = $this->extractPostsFromResponse($response);
                $allPosts = array_merge($allPosts, $posts);

                // Rate limiting
                sleep(self::RATE_LIMIT_DELAY);

            } catch (\Exception $e) {
                Log::warning("Failed to crawl user: {$username}", [
                    'error' => $e->getMessage(),
                    'rule_id' => $rule->id,
                ]);
            }
        }

        return [
            'posts' => array_slice($allPosts, 0, $maxResults),
            'subreddits' => array_unique(array_column($allPosts, 'subreddit')),
        ];
    }

    /**
     * Process and store Reddit posts.
     */
    private function processPosts(array $posts, CrawlerRule $rule): array
    {
        $results = [
            'processed' => 0,
            'stored' => 0,
            'errors' => [],
        ];

        foreach ($posts as $post) {
            try {
                $results['processed']++;

                // Prepare metadata for content matching
                $metadata = [
                    'engagement' => $post['score'] ?? 0,
                    'follower_count' => 0, // Reddit doesn't expose follower counts
                    'sentiment' => null,
                    'language' => 'en', // Reddit is primarily English
                    'upvotes' => $post['ups'] ?? 0,
                    'downvotes' => $post['downs'] ?? 0,
                    'num_comments' => $post['num_comments'] ?? 0,
                    'subreddit' => $post['subreddit'] ?? '',
                    'nsfw' => $post['over_18'] ?? false,
                ];

                // Combine title and selftext for content matching
                $content = $post['title'] ?? '';
                if (!empty($post['selftext'])) {
                    $content .= ' ' . $post['selftext'];
                }

                // Check if post matches rule criteria
                if (!$rule->matchesContent($content, $metadata)) {
                    continue;
                }

                // Store the post
                $socialPost = $this->storePost($post, $rule, $metadata);
                if ($socialPost) {
                    $results['stored']++;
                }

            } catch (\Exception $e) {
                $results['errors'][] = "Failed to process post {$post['id']}: " . $e->getMessage();
                Log::error('Reddit post processing failed', [
                    'post_id' => $post['id'],
                    'rule_id' => $rule->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Store Reddit post as social media post.
     */
    private function storePost(array $post, CrawlerRule $rule, array $metadata): ?SocialMediaPost
    {
        // Check if post already exists
        $existing = SocialMediaPost::where('platform', 'reddit')
            ->where('external_id', $post['id'])
            ->first();

        if ($existing) {
            return $existing;
        }

        // Determine post type
        $postType = 'text';
        if (!empty($post['url']) && $post['url'] !== $post['permalink']) {
            $postType = 'link';
        }
        if (!empty($post['post_hint'])) {
            $postType = match ($post['post_hint']) {
                'image' => 'image',
                'video' => 'video',
                'link' => 'link',
                default => $postType,
            };
        }

        // Prepare content
        $content = $post['title'] ?? '';
        if (!empty($post['selftext']) && $post['selftext'] !== '[removed]') {
            $content .= "\n\n" . $post['selftext'];
        }

        return SocialMediaPost::create([
            'platform' => 'reddit',
            'external_id' => $post['id'],
            'post_type' => $postType,
            'content' => $this->cleanText($content),
            'author_username' => $post['author'] ?? null,
            'author_display_name' => $post['author'] ?? null,
            'author_id' => $post['author'] ?? null,
            'author_followers' => 0, // Reddit doesn't expose this
            'author_verified' => false,
            'engagement_metrics' => [
                'score' => $post['score'] ?? 0,
                'upvotes' => $post['ups'] ?? 0,
                'downvotes' => $post['downs'] ?? 0,
                'comments' => $post['num_comments'] ?? 0,
                'upvote_ratio' => $post['upvote_ratio'] ?? 0,
            ],
            'metadata' => array_merge($metadata, [
                'subreddit' => $post['subreddit'] ?? null,
                'permalink' => $post['permalink'] ?? null,
                'url' => $post['url'] ?? null,
                'domain' => $post['domain'] ?? null,
                'flair_text' => $post['link_flair_text'] ?? null,
                'nsfw' => $post['over_18'] ?? false,
                'stickied' => $post['stickied'] ?? false,
                'locked' => $post['locked'] ?? false,
                'archived' => $post['archived'] ?? false,
                'gilded' => $post['gilded'] ?? 0,
                'awards' => $post['total_awards_received'] ?? 0,
            ]),
            'matched_keywords' => $rule->getMatchedKeywords($content),
            'matched_hashtags' => [], // Reddit doesn't use hashtags
            'posted_at' => isset($post['created_utc']) ? 
                \Carbon\Carbon::createFromTimestamp($post['created_utc']) : 
                now(),
            'crawler_rule_id' => $rule->id,
            'sentiment_score' => null,
            'processing_status' => 'pending',
        ]);
    }

    /**
     * Build search query from rule criteria.
     */
    private function buildSearchQuery(CrawlerRule $rule): string
    {
        $queryParts = [];

        // Add keywords
        if (!empty($rule->keywords)) {
            $keywords = array_map(function ($keyword) {
                return strpos($keyword, ' ') !== false ? "\"{$keyword}\"" : $keyword;
            }, $rule->keywords);
            $queryParts[] = '(' . implode(' OR ', $keywords) . ')';
        }

        // Add specific subreddits
        $config = $rule->getPlatformConfig('reddit');
        if (!empty($config['subreddits'])) {
            $subreddits = array_map(function ($sub) {
                return "subreddit:{$sub}";
            }, $config['subreddits']);
            $queryParts[] = '(' . implode(' OR ', $subreddits) . ')';
        }

        // Add specific authors
        if (!empty($rule->accounts)) {
            $authors = array_map(function ($author) {
                return "author:{$author}";
            }, $rule->accounts);
            $queryParts[] = '(' . implode(' OR ', $authors) . ')';
        }

        // Add exclude keywords
        if (!empty($rule->exclude_keywords)) {
            foreach ($rule->exclude_keywords as $exclude) {
                $queryParts[] = "NOT {$exclude}";
            }
        }

        // Add NSFW filter
        if ($config['exclude_nsfw'] ?? true) {
            $queryParts[] = 'NOT nsfw:1';
        }

        return implode(' ', $queryParts);
    }

    /**
     * Make authenticated API call to Reddit.
     */
    private function makeRedditApiCall(string $endpoint, array $params = [], string $accessToken = null): array
    {
        $headers = [
            'User-Agent' => config('app.name') . '/1.0 by ' . config('services.reddit.username'),
        ];

        if ($accessToken) {
            $headers['Authorization'] = "Bearer {$accessToken}";
            $baseUrl = self::OAUTH_URL;
        } else {
            $baseUrl = self::BASE_URL;
            $endpoint .= '.json';
        }

        $response = Http::withHeaders($headers)
            ->timeout(30)
            ->retry(3, 1000)
            ->get("{$baseUrl}/{$endpoint}", $params);

        if (!$response->successful()) {
            throw new \Exception("Reddit API error: HTTP {$response->status()}");
        }

        return $response->json();
    }

    /**
     * Get OAuth access token for Reddit API.
     */
    private function getAccessToken(): string
    {
        $clientId = config('services.reddit.client_id');
        $clientSecret = config('services.reddit.client_secret');
        $username = config('services.reddit.username');
        $password = config('services.reddit.password');

        if (!$clientId || !$clientSecret) {
            throw new \Exception('Reddit API credentials not configured');
        }

        // Check cache first
        $cacheKey = 'reddit_access_token';
        $cached = $this->cacheService->cacheOrRetrieve(
            'reddit',
            'access_token',
            'oauth_token',
            fn() => $this->requestAccessToken($clientId, $clientSecret, $username, $password),
            [],
            $cacheKey,
            3300 // 55 minutes (tokens last 1 hour)
        );

        return $cached['access_token'];
    }

    /**
     * Request new access token from Reddit.
     */
    private function requestAccessToken(string $clientId, string $clientSecret, string $username, string $password): array
    {
        $response = Http::withBasicAuth($clientId, $clientSecret)
            ->withHeaders([
                'User-Agent' => config('app.name') . '/1.0 by ' . $username,
            ])
            ->asForm()
            ->post('https://www.reddit.com/api/v1/access_token', [
                'grant_type' => 'password',
                'username' => $username,
                'password' => $password,
            ]);

        if (!$response->successful()) {
            throw new \Exception("Failed to get Reddit access token: HTTP {$response->status()}");
        }

        $data = $response->json();
        
        if (isset($data['error'])) {
            throw new \Exception("Reddit OAuth error: {$data['error']}");
        }

        return $data;
    }

    /**
     * Extract posts from Reddit API response.
     */
    private function extractPostsFromResponse(array $response): array
    {
        if (isset($response['data']['children'])) {
            return array_map(fn($child) => $child['data'], $response['data']['children']);
        }

        if (isset($response['data']['dist'])) {
            return array_map(fn($child) => $child['data'], $response['data']['children'] ?? []);
        }

        return [];
    }

    /**
     * Extract subreddits from Reddit API response.
     */
    private function extractSubredditsFromResponse(array $response): array
    {
        $posts = $this->extractPostsFromResponse($response);
        return array_unique(array_filter(array_column($posts, 'subreddit')));
    }

    /**
     * Validate Reddit API credentials.
     */
    private function validateRedditCredentials(): void
    {
        $required = ['client_id', 'client_secret', 'username', 'password'];
        
        foreach ($required as $field) {
            if (!config("services.reddit.{$field}")) {
                throw new \Exception("Reddit {$field} not configured. Please set REDDIT_" . strtoupper($field) . " in your environment.");
            }
        }
    }

    /**
     * Get subreddit information.
     */
    public function getSubredditInfo(string $subreddit, string $accessToken = null): array
    {
        $cacheKey = "reddit_subreddit_info_{$subreddit}";
        
        return $this->cacheService->cacheOrRetrieve(
            'reddit',
            "r/{$subreddit}/about",
            'subreddit_info',
            fn() => $this->makeRedditApiCall("r/{$subreddit}/about", [], $accessToken),
            [],
            $cacheKey,
            3600 // 1 hour cache
        );
    }

    /**
     * Get trending subreddits for auto-discovery.
     */
    public function getTrendingSubreddits(): array
    {
        $cacheKey = 'reddit_trending_subreddits';
        
        return $this->cacheService->cacheOrRetrieve(
            'reddit',
            'subreddits/popular',
            'trending_subreddits',
            fn() => $this->makeRedditApiCall('subreddits/popular', ['limit' => 50]),
            [],
            $cacheKey,
            3600 // 1 hour cache
        );
    }
}
