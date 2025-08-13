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

final class EnhancedTwitterCrawler implements PlatformCrawlerInterface
{
    use UsesProxy;

    private string $bearerToken;
    private AdvancedKeywordEngine $keywordEngine;
    private array $config;
    private array $rateLimits;

    public function __construct(AdvancedKeywordEngine $keywordEngine, array $config = [])
    {
        $this->keywordEngine = $keywordEngine;
        $this->config = array_merge([
            'bearer_token' => config('services.twitter.bearer_token'),
            'rate_limit_per_15min' => 300,
            'max_results_per_request' => 100,
            'tweet_fields' => 'created_at,author_id,public_metrics,context_annotations,entities,lang,possibly_sensitive,source,referenced_tweets',
            'user_fields' => 'username,name,verified,public_metrics,description,created_at',
            'expansions' => 'author_id,referenced_tweets.id,referenced_tweets.id.author_id',
            'exclude_retweets' => true,
            'exclude_replies' => false,
            'languages' => ['en'],
        ], $config);

        $this->bearerToken = $this->config['bearer_token'];
        $this->rateLimits = [
            'search' => $this->config['rate_limit_per_15min'],
            'user_timeline' => 75, // Twitter API v2 limit
        ];

        if (!$this->bearerToken) {
            throw new Exception('Twitter bearer token not configured');
        }
    }

    /**
     * Main crawl method for Twitter
     */
    public function crawl(array $options = []): array
    {
        $methods = $options['methods'] ?? ['keywords', 'hashtags', 'users'];
        $results = [];

        Log::info('Starting Twitter crawl', [
            'methods' => $methods,
            'options' => $options
        ]);

        // Crawl by keywords
        if (in_array('keywords', $methods)) {
            $keywords = $options['keywords'] ?? $this->keywordEngine->getHighPriorityKeywords('twitter', 20);
            $results['keywords'] = $this->crawlByKeywords(array_column($keywords, 'keyword'));
        }

        // Crawl by hashtags
        if (in_array('hashtags', $methods)) {
            $hashtags = $options['hashtags'] ?? $this->getDefaultHashtags();
            $results['hashtags'] = $this->crawlByHashtags($hashtags);
        }

        // Crawl user timelines
        if (in_array('users', $methods)) {
            $users = $options['users'] ?? $this->getDefaultUsers();
            $results['users'] = $this->crawlUserTimelines($users);
        }

        return $results;
    }

    /**
     * Search for tweets by keywords
     */
    public function searchByKeywords(array $keywords, array $channels = null): array
    {
        return $this->crawlByKeywords($keywords);
    }

    /**
     * Crawl tweets by keywords with advanced search operators
     */
    public function crawlByKeywords(array $keywords): array
    {
        $results = [];
        
        foreach ($keywords as $keyword) {
            if (!$this->canMakeRequest('search')) {
                Log::warning('Twitter search rate limit reached');
                break;
            }

            try {
                $query = $this->buildSearchQuery($keyword);
                $tweets = $this->searchTweets($query);
                $processedTweets = $this->processTweets($tweets, "keyword:{$keyword}");
                
                $results[$keyword] = [
                    'query' => $query,
                    'total_fetched' => count($tweets['data'] ?? []),
                    'posts' => $processedTweets,
                ];

                $this->updateRateLimit('search');
                usleep(200000); // 200ms delay between requests

            } catch (Exception $e) {
                Log::error('Twitter keyword search failed', [
                    'keyword' => $keyword,
                    'error' => $e->getMessage()
                ]);
                $results[$keyword] = ['error' => $e->getMessage(), 'posts' => []];
            }
        }

        return $results;
    }

    /**
     * Crawl tweets by hashtags
     */
    public function crawlByHashtags(array $hashtags): array
    {
        $results = [];
        
        foreach ($hashtags as $hashtag) {
            if (!$this->canMakeRequest('search')) {
                break;
            }

            try {
                $query = '#' . ltrim($hashtag, '#');
                $tweets = $this->searchTweets($query);
                $processedTweets = $this->processTweets($tweets, "hashtag:{$hashtag}");
                
                $results[$hashtag] = [
                    'query' => $query,
                    'total_fetched' => count($tweets['data'] ?? []),
                    'posts' => $processedTweets,
                ];

                $this->updateRateLimit('search');
                usleep(200000);

            } catch (Exception $e) {
                Log::error('Twitter hashtag crawl failed', [
                    'hashtag' => $hashtag,
                    'error' => $e->getMessage()
                ]);
                $results[$hashtag] = ['error' => $e->getMessage(), 'posts' => []];
            }
        }

        return $results;
    }

    /**
     * Crawl user timelines
     */
    public function crawlUserTimelines(array $usernames): array
    {
        $results = [];
        
        foreach ($usernames as $username) {
            if (!$this->canMakeRequest('user_timeline')) {
                Log::warning('Twitter user timeline rate limit reached');
                break;
            }

            try {
                $tweets = $this->getUserTimeline($username);
                $processedTweets = $this->processTweets($tweets, "user:{$username}");
                
                $results[$username] = [
                    'total_fetched' => count($tweets['data'] ?? []),
                    'posts' => $processedTweets,
                ];

                $this->updateRateLimit('user_timeline');
                usleep(800000); // 800ms delay for user timeline API

            } catch (Exception $e) {
                Log::error('Twitter user timeline crawl failed', [
                    'username' => $username,
                    'error' => $e->getMessage()
                ]);
                $results[$username] = ['error' => $e->getMessage(), 'posts' => []];
            }
        }

        return $results;
    }

    /**
     * Build advanced search query
     */
    private function buildSearchQuery(string $keyword): string
    {
        $query = $keyword;

        // Exclude retweets
        if ($this->config['exclude_retweets']) {
            $query .= ' -is:retweet';
        }

        // Exclude replies if configured
        if ($this->config['exclude_replies']) {
            $query .= ' -is:reply';
        }

        // Language filter
        if (!empty($this->config['languages'])) {
            $langQuery = implode(' OR ', array_map(fn($lang) => "lang:{$lang}", $this->config['languages']));
            $query .= " ({$langQuery})";
        }

        // Add quality filters
        $query .= ' -is:nullcast'; // Exclude promotional content
        
        return $query;
    }

    /**
     * Search tweets using Twitter API v2
     */
    private function searchTweets(string $query, int $maxResults = null): array
    {
        $maxResults = $maxResults ?? $this->config['max_results_per_request'];
        
        $params = [
            'query' => $query,
            'max_results' => min($maxResults, 100),
            'tweet.fields' => $this->config['tweet_fields'],
            'user.fields' => $this->config['user_fields'],
            'expansions' => $this->config['expansions'],
        ];

        $response = $this->makeApiRequest('tweets/search/recent', $params);
        
        if (!isset($response['data'])) {
            return ['data' => [], 'includes' => []];
        }

        return $response;
    }

    /**
     * Get user timeline tweets
     */
    private function getUserTimeline(string $username, int $maxResults = 50): array
    {
        // First get user ID
        $userResponse = $this->makeApiRequest("users/by/username/{$username}", [
            'user.fields' => $this->config['user_fields']
        ]);

        if (!isset($userResponse['data']['id'])) {
            throw new Exception("User not found: {$username}");
        }

        $userId = $userResponse['data']['id'];

        // Get user tweets
        $params = [
            'max_results' => min($maxResults, 100),
            'tweet.fields' => $this->config['tweet_fields'],
            'expansions' => 'referenced_tweets.id',
        ];

        if ($this->config['exclude_retweets']) {
            $params['exclude'] = 'retweets';
        }

        $response = $this->makeApiRequest("users/{$userId}/tweets", $params);
        
        if (!isset($response['data'])) {
            return ['data' => [], 'includes' => []];
        }

        return $response;
    }

    /**
     * Process tweets and create social media posts
     */
    private function processTweets(array $apiResponse, string $source): array
    {
        if (!isset($apiResponse['data']) || empty($apiResponse['data'])) {
            return [];
        }

        $tweets = $apiResponse['data'];
        $includes = $apiResponse['includes'] ?? [];
        $users = $includes['users'] ?? [];
        $userMap = collect($users)->keyBy('id')->toArray();
        
        $processed = [];

        foreach ($tweets as $tweet) {
            try {
                // Skip non-English tweets if configured
                if (!empty($this->config['languages']) && 
                    isset($tweet['lang']) && 
                    !in_array($tweet['lang'], $this->config['languages'])) {
                    continue;
                }

                // Skip sensitive content if needed
                if ($tweet['possibly_sensitive'] ?? false) {
                    continue;
                }

                $text = $tweet['text'];
                $user = $userMap[$tweet['author_id']] ?? null;

                // Match against keyword rules
                $matches = $this->keywordEngine->matchContent($text, 'twitter', [
                    'engagement_score' => $this->calculateEngagementScore($tweet['public_metrics'] ?? []),
                    'tweet_id' => $tweet['id'],
                    'author_id' => $tweet['author_id'],
                    'created_at' => $tweet['created_at'],
                ]);

                if (empty($matches)) {
                    continue; // Skip if no keyword matches
                }

                $post = $this->createSocialMediaPost($tweet, $user, $source, $matches);
                $this->storeKeywordMatches($post, $matches);
                
                $processed[] = array_merge($post->toArray(), [
                    'match_count' => count($matches),
                    'matches' => $matches,
                ]);

                // Check for alerts
                if ($this->keywordEngine->shouldTriggerAlert($matches, 'twitter')) {
                    $this->triggerAlert($post, $matches);
                }

            } catch (Exception $e) {
                Log::error('Error processing Twitter tweet', [
                    'tweet_id' => $tweet['id'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $processed;
    }

    /**
     * Create social media post from tweet
     */
    private function createSocialMediaPost(array $tweet, ?array $user, string $source, array $matches): SocialMediaPost
    {
        $metrics = $tweet['public_metrics'] ?? [];
        $entities = $tweet['entities'] ?? [];
        
        $metadata = [
            'retweet_count' => $metrics['retweet_count'] ?? 0,
            'like_count' => $metrics['like_count'] ?? 0,
            'reply_count' => $metrics['reply_count'] ?? 0,
            'quote_count' => $metrics['quote_count'] ?? 0,
            'impression_count' => $metrics['impression_count'] ?? 0,
            'hashtags' => $this->extractHashtags($entities),
            'mentions' => $this->extractMentions($entities),
            'urls' => $this->extractUrls($entities),
            'source' => $tweet['source'] ?? null,
            'language' => $tweet['lang'] ?? null,
            'context_annotations' => $tweet['context_annotations'] ?? [],
            'referenced_tweets' => $tweet['referenced_tweets'] ?? [],
            'search_source' => $source,
            'user_info' => $user ? [
                'username' => $user['username'],
                'name' => $user['name'],
                'verified' => $user['verified'] ?? false,
                'public_metrics' => $user['public_metrics'] ?? [],
                'description' => $user['description'] ?? null,
            ] : null,
        ];

        $engagementScore = $this->calculateEngagementScore($metrics);

        return SocialMediaPost::updateOrCreate(
            [
                'platform' => 'twitter',
                'platform_id' => $tweet['id']
            ],
            [
                'author_username' => $user['username'] ?? null,
                'author_id' => $tweet['author_id'],
                'content' => $tweet['text'],
                'metadata' => $metadata,
                'url' => "https://twitter.com/{$user['username']}/status/{$tweet['id']}",
                'published_at' => $tweet['created_at'],
                'engagement_score' => $engagementScore,
                'sentiment_score' => 0, // Will be calculated later if needed
                'sentiment_label' => 'neutral',
                'matched_keywords' => array_column($matches, 'keyword'),
            ]
        );
    }

    /**
     * Extract hashtags from entities
     */
    private function extractHashtags(array $entities): array
    {
        $hashtags = [];
        if (isset($entities['hashtags'])) {
            foreach ($entities['hashtags'] as $hashtag) {
                $hashtags[] = '#' . $hashtag['tag'];
            }
        }
        return $hashtags;
    }

    /**
     * Extract mentions from entities
     */
    private function extractMentions(array $entities): array
    {
        $mentions = [];
        if (isset($entities['mentions'])) {
            foreach ($entities['mentions'] as $mention) {
                $mentions[] = '@' . $mention['username'];
            }
        }
        return $mentions;
    }

    /**
     * Extract URLs from entities
     */
    private function extractUrls(array $entities): array
    {
        $urls = [];
        if (isset($entities['urls'])) {
            foreach ($entities['urls'] as $url) {
                $urls[] = [
                    'url' => $url['url'],
                    'expanded_url' => $url['expanded_url'] ?? null,
                    'display_url' => $url['display_url'] ?? null,
                    'title' => $url['title'] ?? null,
                    'description' => $url['description'] ?? null,
                ];
            }
        }
        return $urls;
    }

    /**
     * Calculate engagement score for tweet
     */
    private function calculateEngagementScore(array $metrics): int
    {
        $likes = $metrics['like_count'] ?? 0;
        $retweets = $metrics['retweet_count'] ?? 0;
        $replies = $metrics['reply_count'] ?? 0;
        $quotes = $metrics['quote_count'] ?? 0;

        // Weighted engagement score
        return ($likes * 1) + ($retweets * 3) + ($replies * 2) + ($quotes * 2);
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
     * Make API request to Twitter
     */
    private function makeApiRequest(string $endpoint, array $params = []): array
    {
        $url = "https://api.twitter.com/2/{$endpoint}";
        
        $response = $this->getHttpClient()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->bearerToken,
            ])
            ->timeout(30)
            ->get($url, $params);

        if (!$response->successful()) {
            throw new Exception("Twitter API error: " . $response->body());
        }

        return $response->json();
    }

    /**
     * Get default hashtags to crawl
     */
    private function getDefaultHashtags(): array
    {
        return config('crawler_microservice.twitter.hashtags', [
            'blockchain',
            'cryptocurrency',
            'defi',
            'nft',
            'ethereum',
            'bitcoin',
            'smartcontracts',
            'web3',
        ]);
    }

    /**
     * Get default users to crawl
     */
    private function getDefaultUsers(): array
    {
        return config('crawler_microservice.twitter.users', [
            'ethereum',
            'VitalikButerin',
            'uniswap',
            'aave',
            'compoundfinance',
            'MakerDAO',
        ]);
    }

    /**
     * Check rate limiting
     */
    private function canMakeRequest(string $limitType): bool
    {
        $key = "twitter_rate_limit_{$limitType}_" . date('Y-m-d-H-') . floor(date('i') / 15) * 15;
        $currentCount = Cache::get($key, 0);
        $limit = $this->rateLimits[$limitType];
        
        return $currentCount < $limit;
    }

    /**
     * Update rate limit counter
     */
    private function updateRateLimit(string $limitType): void
    {
        $key = "twitter_rate_limit_{$limitType}_" . date('Y-m-d-H-') . floor(date('i') / 15) * 15;
        Cache::increment($key, 1);
        Cache::put($key, Cache::get($key), 900); // 15 minutes TTL
    }

    /**
     * Trigger alert for critical matches
     */
    private function triggerAlert(SocialMediaPost $post, array $matches): void
    {
        Log::alert('Critical keyword alert triggered', [
            'platform' => 'twitter',
            'post_id' => $post->id,
            'author' => $post->author_username,
            'matches' => array_column($matches, 'keyword'),
            'content' => substr($post->content, 0, 200) . '...',
        ]);
    }
}
