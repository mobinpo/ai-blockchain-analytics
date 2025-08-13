<?php

declare(strict_types=1);

namespace App\Services\Crawlers;

use App\Models\CrawlerRule;
use App\Models\SocialMediaPost;
use App\Services\ApiCacheService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

final class TwitterCrawlerService extends BaseCrawlerService
{
    private const BASE_URL = 'https://api.twitter.com/2';
    private const RATE_LIMITS = [
        'search' => ['requests' => 300, 'window' => 900], // 300 requests per 15 minutes
        'user_timeline' => ['requests' => 1500, 'window' => 900], // 1500 requests per 15 minutes
        'user_lookup' => ['requests' => 300, 'window' => 900], // 300 requests per 15 minutes
    ];

    public function __construct(
        private readonly ApiCacheService $cacheService
    ) {
        parent::__construct();
    }

    /**
     * Crawl Twitter based on crawler rules.
     */
    public function crawl(CrawlerRule $rule): array
    {
        $this->validateTwitterCredentials();

        $results = [
            'platform' => 'twitter',
            'rule_id' => $rule->id,
            'posts_found' => 0,
            'posts_processed' => 0,
            'posts_stored' => 0,
            'errors' => [],
            'rate_limit_status' => [],
            'execution_time' => 0,
        ];

        $startTime = microtime(true);

        try {
            // Get Twitter-specific configuration
            $config = $rule->getPlatformConfig('twitter');
            $maxResults = min($config['max_results'] ?? 100, $rule->getRemainingHourlyQuota());

            if ($maxResults <= 0) {
                $results['errors'][] = 'Rate limit quota exhausted';
                return $results;
            }

            // Build search query from keywords and hashtags
            $query = $this->buildSearchQuery($rule);
            if (empty($query)) {
                $results['errors'][] = 'No valid search query could be built from rule criteria';
                return $results;
            }

            // Perform search
            $searchResults = $this->searchTweets($query, $maxResults, $config);
            $results['posts_found'] = count($searchResults['data'] ?? []);
            $results['rate_limit_status'] = $searchResults['rate_limit'] ?? [];

            // Process and store tweets
            if (!empty($searchResults['data'])) {
                $processed = $this->processTweets($searchResults['data'], $rule, $searchResults['includes'] ?? []);
                $results['posts_processed'] = $processed['processed'];
                $results['posts_stored'] = $processed['stored'];
                $results['errors'] = array_merge($results['errors'], $processed['errors']);
            }

            // Update rule statistics
            $rule->updateCrawlStats([
                'posts_found' => $results['posts_found'],
                'posts_processed' => $results['posts_processed'],
                'platform' => 'twitter',
                'query' => $query,
                'timestamp' => now()->toISOString(),
            ]);

            Log::info('Twitter crawl completed', [
                'rule_id' => $rule->id,
                'posts_found' => $results['posts_found'],
                'posts_stored' => $results['posts_stored'],
            ]);

        } catch (\Exception $e) {
            $results['errors'][] = $e->getMessage();
            Log::error('Twitter crawl failed', [
                'rule_id' => $rule->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        $results['execution_time'] = round(microtime(true) - $startTime, 2);
        return $results;
    }

    /**
     * Search tweets using Twitter API v2.
     */
    private function searchTweets(string $query, int $maxResults = 100, array $config = []): array
    {
        $params = [
            'query' => $query,
            'max_results' => min($maxResults, 100), // API limit
            'tweet.fields' => implode(',', [
                'id', 'text', 'author_id', 'created_at', 'lang', 'public_metrics',
                'context_annotations', 'entities', 'geo', 'in_reply_to_user_id',
                'referenced_tweets', 'reply_settings', 'source'
            ]),
            'user.fields' => implode(',', [
                'id', 'name', 'username', 'description', 'public_metrics',
                'verified', 'profile_image_url', 'location', 'created_at'
            ]),
            'expansions' => 'author_id,referenced_tweets.id,in_reply_to_user_id',
        ];

        // Add optional parameters
        if (isset($config['since_id'])) {
            $params['since_id'] = $config['since_id'];
        }
        if (isset($config['until_id'])) {
            $params['until_id'] = $config['until_id'];
        }
        if (isset($config['start_time'])) {
            $params['start_time'] = $config['start_time'];
        }
        if (isset($config['end_time'])) {
            $params['end_time'] = $config['end_time'];
        }

        // Check cache first
        $cacheKey = 'twitter_search_' . md5($query . serialize($params));
        $cached = $this->cacheService->cacheOrRetrieve(
            'twitter',
            'tweets/search/recent',
            'search_results',
            fn() => $this->makeTwitterApiCall('tweets/search/recent', $params),
            $params,
            $cacheKey,
            300 // 5 minutes cache for search results
        );

        return $cached;
    }

    /**
     * Get user timeline tweets.
     */
    public function getUserTimeline(string $userId, int $maxResults = 100, array $config = []): array
    {
        $params = [
            'max_results' => min($maxResults, 100),
            'tweet.fields' => 'id,text,author_id,created_at,lang,public_metrics,context_annotations,entities',
            'user.fields' => 'id,name,username,description,public_metrics,verified',
        ];

        // Add optional parameters
        if (isset($config['since_id'])) {
            $params['since_id'] = $config['since_id'];
        }
        if (isset($config['pagination_token'])) {
            $params['pagination_token'] = $config['pagination_token'];
        }

        $cacheKey = "user_timeline_{$userId}_" . md5(serialize($params));
        return $this->cacheService->cacheOrRetrieve(
            'twitter',
            "users/{$userId}/tweets",
            'user_timeline',
            fn() => $this->makeTwitterApiCall("users/{$userId}/tweets", $params),
            $params,
            $cacheKey,
            600 // 10 minutes cache for user timelines
        );
    }

    /**
     * Get user information by username or ID.
     */
    public function getUser(string $identifier, bool $byUsername = true): array
    {
        $params = [
            'user.fields' => implode(',', [
                'id', 'name', 'username', 'description', 'public_metrics',
                'verified', 'profile_image_url', 'location', 'created_at',
                'protected', 'url', 'entities'
            ]),
        ];

        $endpoint = $byUsername ? "users/by/username/{$identifier}" : "users/{$identifier}";
        $cacheKey = "user_info_{$identifier}";

        return $this->cacheService->cacheOrRetrieve(
            'twitter',
            $endpoint,
            'user_info',
            fn() => $this->makeTwitterApiCall($endpoint, $params),
            $params,
            $cacheKey,
            3600 // 1 hour cache for user info
        );
    }

    /**
     * Process and store tweets.
     */
    private function processTweets(array $tweets, CrawlerRule $rule, array $includes = []): array
    {
        $results = [
            'processed' => 0,
            'stored' => 0,
            'errors' => [],
        ];

        $users = $this->mapUsersById($includes['users'] ?? []);

        foreach ($tweets as $tweet) {
            try {
                $results['processed']++;

                // Get author information
                $author = $users[$tweet['author_id']] ?? null;
                
                // Prepare metadata for content matching
                $metadata = [
                    'engagement' => $this->calculateEngagement($tweet),
                    'follower_count' => $author['public_metrics']['followers_count'] ?? 0,
                    'sentiment' => null, // To be calculated by sentiment analysis service
                    'language' => $tweet['lang'] ?? 'unknown',
                    'author_verified' => $author['verified'] ?? false,
                    'retweet_count' => $tweet['public_metrics']['retweet_count'] ?? 0,
                    'like_count' => $tweet['public_metrics']['like_count'] ?? 0,
                    'reply_count' => $tweet['public_metrics']['reply_count'] ?? 0,
                ];

                // Check if tweet matches rule criteria
                if (!$rule->matchesContent($tweet['text'], $metadata)) {
                    continue;
                }

                // Store the tweet
                $post = $this->storeTweet($tweet, $author, $rule, $metadata);
                if ($post) {
                    $results['stored']++;
                }

            } catch (\Exception $e) {
                $results['errors'][] = "Failed to process tweet {$tweet['id']}: " . $e->getMessage();
                Log::error('Tweet processing failed', [
                    'tweet_id' => $tweet['id'],
                    'rule_id' => $rule->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Store tweet as social media post.
     */
    private function storeTweet(array $tweet, ?array $author, CrawlerRule $rule, array $metadata): ?SocialMediaPost
    {
        // Check if post already exists
        $existing = SocialMediaPost::where('platform', 'twitter')
            ->where('external_id', $tweet['id'])
            ->first();

        if ($existing) {
            return $existing;
        }

        // Extract entities and media
        $entities = $this->extractEntities($tweet);
        $urls = $this->extractTweetUrls($tweet);

        return SocialMediaPost::create([
            'platform' => 'twitter',
            'external_id' => $tweet['id'],
            'post_type' => $this->determineTweetType($tweet),
            'content' => $tweet['text'],
            'author_username' => $author['username'] ?? null,
            'author_display_name' => $author['name'] ?? null,
            'author_id' => $tweet['author_id'],
            'author_followers' => $author['public_metrics']['followers_count'] ?? 0,
            'author_verified' => $author['verified'] ?? false,
            'engagement_metrics' => [
                'likes' => $tweet['public_metrics']['like_count'] ?? 0,
                'retweets' => $tweet['public_metrics']['retweet_count'] ?? 0,
                'replies' => $tweet['public_metrics']['reply_count'] ?? 0,
                'quotes' => $tweet['public_metrics']['quote_count'] ?? 0,
            ],
            'metadata' => array_merge($metadata, [
                'tweet_created_at' => $tweet['created_at'],
                'language' => $tweet['lang'],
                'source' => $tweet['source'] ?? null,
                'entities' => $entities,
                'urls' => $urls,
                'context_annotations' => $tweet['context_annotations'] ?? [],
                'geo' => $tweet['geo'] ?? null,
            ]),
            'matched_keywords' => $rule->getMatchedKeywords($tweet['text']),
            'matched_hashtags' => $rule->getMatchedHashtags($tweet['text']),
            'posted_at' => $tweet['created_at'],
            'crawler_rule_id' => $rule->id,
            'sentiment_score' => null, // To be filled by sentiment analysis
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

        // Add hashtags
        if (!empty($rule->hashtags)) {
            $hashtags = array_map(function ($hashtag) {
                return ltrim($hashtag, '#');
            }, $rule->hashtags);
            $hashtagQuery = implode(' OR #', $hashtags);
            $queryParts[] = "(#{$hashtagQuery})";
        }

        // Add specific accounts
        if (!empty($rule->accounts)) {
            $accounts = array_map(function ($account) {
                $account = ltrim($account, '@');
                return "from:{$account}";
            }, $rule->accounts);
            $queryParts[] = '(' . implode(' OR ', $accounts) . ')';
        }

        // Add exclude keywords
        if (!empty($rule->exclude_keywords)) {
            foreach ($rule->exclude_keywords as $exclude) {
                $queryParts[] = "-{$exclude}";
            }
        }

        // Add language filter
        if ($rule->language && $rule->language !== 'all') {
            $queryParts[] = "lang:{$rule->language}";
        }

        // Add engagement filters
        $config = $rule->getPlatformConfig('twitter');
        if (isset($config['min_retweets']) && $config['min_retweets'] > 0) {
            $queryParts[] = "min_retweets:{$config['min_retweets']}";
        }
        if (isset($config['min_faves']) && $config['min_faves'] > 0) {
            $queryParts[] = "min_faves:{$config['min_faves']}";
        }
        if (isset($config['min_replies']) && $config['min_replies'] > 0) {
            $queryParts[] = "min_replies:{$config['min_replies']}";
        }

        // Exclude retweets if specified
        if ($config['exclude_retweets'] ?? false) {
            $queryParts[] = '-is:retweet';
        }

        // Exclude replies if specified
        if ($config['exclude_replies'] ?? false) {
            $queryParts[] = '-is:reply';
        }

        return implode(' ', $queryParts);
    }

    /**
     * Make authenticated API call to Twitter.
     */
    private function makeTwitterApiCall(string $endpoint, array $params = []): array
    {
        $bearerToken = config('services.twitter.bearer_token');
        if (!$bearerToken) {
            throw new \Exception('Twitter Bearer Token not configured');
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$bearerToken}",
            'Content-Type' => 'application/json',
        ])
        ->timeout(30)
        ->retry(3, 1000)
        ->get(self::BASE_URL . '/' . $endpoint, $params);

        if (!$response->successful()) {
            $error = $response->json()['errors'][0]['message'] ?? 'Unknown Twitter API error';
            throw new \Exception("Twitter API error: {$error} (HTTP {$response->status()})");
        }

        $data = $response->json();
        
        // Add rate limit information
        $data['rate_limit'] = [
            'limit' => $response->header('x-rate-limit-limit'),
            'remaining' => $response->header('x-rate-limit-remaining'),
            'reset' => $response->header('x-rate-limit-reset'),
        ];

        return $data;
    }

    /**
     * Validate Twitter API credentials.
     */
    private function validateTwitterCredentials(): void
    {
        $bearerToken = config('services.twitter.bearer_token');
        if (!$bearerToken) {
            throw new \Exception('Twitter Bearer Token not configured. Please set TWITTER_BEARER_TOKEN in your environment.');
        }

        // Optionally validate the token with a test request
        // This could be cached to avoid repeated validation
    }

    /**
     * Calculate engagement score for a tweet.
     */
    private function calculateEngagement(array $tweet): int
    {
        $metrics = $tweet['public_metrics'] ?? [];
        return ($metrics['like_count'] ?? 0) + 
               ($metrics['retweet_count'] ?? 0) + 
               ($metrics['reply_count'] ?? 0) + 
               ($metrics['quote_count'] ?? 0);
    }

    /**
     * Map users array by user ID for quick lookup.
     */
    private function mapUsersById(array $users): array
    {
        $mapped = [];
        foreach ($users as $user) {
            $mapped[$user['id']] = $user;
        }
        return $mapped;
    }

    /**
     * Determine tweet type based on content and structure.
     */
    private function determineTweetType(array $tweet): string
    {
        if (isset($tweet['referenced_tweets'])) {
            foreach ($tweet['referenced_tweets'] as $ref) {
                if ($ref['type'] === 'retweeted') {
                    return 'retweet';
                }
                if ($ref['type'] === 'quoted') {
                    return 'quote';
                }
                if ($ref['type'] === 'replied_to') {
                    return 'reply';
                }
            }
        }

        return 'original';
    }

    /**
     * Extract entities from tweet.
     */
    private function extractEntities(array $tweet): array
    {
        $entities = [];
        
        if (isset($tweet['entities'])) {
            $entities['hashtags'] = array_column($tweet['entities']['hashtags'] ?? [], 'tag');
            $entities['mentions'] = array_column($tweet['entities']['mentions'] ?? [], 'username');
            $entities['urls'] = array_column($tweet['entities']['urls'] ?? [], 'expanded_url');
            $entities['cashtags'] = array_column($tweet['entities']['cashtags'] ?? [], 'tag');
        }

        return $entities;
    }

    /**
     * Extract URLs from tweet entities.
     */
    private function extractTweetUrls(array $tweet): array
    {
        $urls = [];
        
        if (isset($tweet['entities']['urls'])) {
            foreach ($tweet['entities']['urls'] as $url) {
                $urls[] = [
                    'url' => $url['url'],
                    'expanded_url' => $url['expanded_url'],
                    'display_url' => $url['display_url'],
                    'title' => $url['title'] ?? null,
                    'description' => $url['description'] ?? null,
                ];
            }
        }

        return $urls;
    }

    /**
     * Get rate limit status for different endpoints.
     */
    public function getRateLimitStatus(): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer " . config('services.twitter.bearer_token'),
            ])->get(self::BASE_URL . '/application/rate_limit_status');

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::error('Failed to get Twitter rate limit status', ['error' => $e->getMessage()]);
        }

        return [];
    }

    /**
     * Check if we can make requests without hitting rate limits.
     */
    public function canMakeRequest(string $endpoint = 'search'): bool
    {
        $limits = self::RATE_LIMITS[$endpoint] ?? ['requests' => 100, 'window' => 900];
        
        // This would need to be implemented with a rate limiting store
        // For now, return true and rely on API rate limit headers
        return true;
    }

    /**
     * Stream tweets in real-time (requires different API access level).
     */
    public function streamTweets(CrawlerRule $rule, callable $callback): void
    {
        // This would implement Twitter's streaming API
        // Requires elevated API access
        throw new \Exception('Real-time streaming requires Twitter API v2 elevated access');
    }
}
