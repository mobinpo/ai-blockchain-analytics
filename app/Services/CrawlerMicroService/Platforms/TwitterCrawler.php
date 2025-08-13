<?php

namespace App\Services\CrawlerMicroService\Platforms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Services\CrawlerMicroService\CrawlerCacheManager;
use Carbon\Carbon;
use Exception;

class TwitterCrawler implements PlatformCrawlerInterface
{
    private array $config;
    private string $bearerToken;
    private array $rateLimitStatus;
    private ?CrawlerCacheManager $cacheManager = null;

    public function __construct(array $config, ?CrawlerCacheManager $cacheManager = null)
    {
        $this->config = $config;
        $this->bearerToken = $config['bearer_token'];
        $this->rateLimitStatus = [];
        $this->cacheManager = $cacheManager;
        
        if (empty($this->bearerToken)) {
            throw new Exception('Twitter Bearer Token is required');
        }
    }

    /**
     * Search Twitter by keywords with intelligent caching
     */
    public function searchByKeywords(array $keywords, int $maxResults = 100): array
    {
        $posts = [];
        
        try {
            // Build search query
            $query = $this->buildSearchQuery($keywords);
            
            Log::info('Searching Twitter', [
                'query' => $query,
                'max_results' => $maxResults
            ]);
            
            // Try cache-first approach if cache manager is available
            if ($this->cacheManager) {
                $filters = [
                    'max_results' => $maxResults,
                    'tweet_fields' => $this->config['search_params']['tweet_fields'],
                    'user_fields' => $this->config['search_params']['user_fields'],
                ];
                
                $cachedResult = $this->cacheManager->searchWithCache(
                    'twitter',
                    $query,
                    $filters,
                    'medium', // Default priority
                    true      // Allow stale data
                );
                
                if ($cachedResult !== null) {
                    Log::info('Twitter search served from cache', [
                        'query' => $query,
                        'from_cache' => $cachedResult['from_cache'],
                        'cache_fresh' => $cachedResult['cache_fresh'] ?? false,
                    ]);
                    
                    return [
                        'status' => 'success',
                        'posts' => $cachedResult['posts'],
                        'platform' => 'twitter',
                        'query' => $query,
                        'from_cache' => true,
                        'total_results' => count($cachedResult['posts']),
                    ];
                }
            }
            
            // Cache miss or no cache manager - make API request
            $response = $this->makeRequest('search', [
                'query' => $query,
                'max_results' => min($maxResults, $this->config['search_params']['max_results']),
                'tweet.fields' => $this->config['search_params']['tweet_fields'],
                'user.fields' => $this->config['search_params']['user_fields'],
                'expansions' => $this->config['search_params']['expansions']
            ]);
            
            if (isset($response['data'])) {
                $posts = $this->processTweets($response['data'], $response['includes'] ?? []);
                
                // Apply additional filters
                $posts = $this->applyFilters($posts);
                
                // Cache the results if cache manager is available
                if ($this->cacheManager && !empty($posts)) {
                    $filters = [
                        'max_results' => $maxResults,
                        'tweet_fields' => $this->config['search_params']['tweet_fields'],
                        'user_fields' => $this->config['search_params']['user_fields'],
                    ];
                    
                    $this->cacheManager->cacheSearchResults(
                        'twitter',
                        $query,
                        $filters,
                        $posts,
                        'medium' // Default priority
                    );
                    
                    Log::info('Twitter search results cached', [
                        'query' => $query,
                        'posts_cached' => count($posts),
                    ]);
                }
            }
            
            Log::info('Twitter search completed', [
                'posts_found' => count($posts),
                'rate_limit_remaining' => $this->rateLimitStatus['remaining'] ?? 'unknown',
                'from_cache' => false,
            ]);
            
        } catch (Exception $e) {
            Log::error('Twitter search failed', [
                'error' => $e->getMessage(),
                'keywords' => $keywords
            ]);
            throw $e;
        }
        
        return [
            'status' => 'success',
            'posts' => $posts,
            'platform' => 'twitter',
            'query' => $query ?? implode(' ', $keywords),
            'from_cache' => false,
            'total_results' => count($posts),
        ];
    }

    /**
     * Get tweets from specific users with caching
     */
    public function getUserTweets(array $userIds, int $maxResults = 100): array
    {
        $posts = [];
        
        foreach ($userIds as $userId) {
            try {
                // Try cache first if cache manager is available
                if ($this->cacheManager) {
                    $cachedTimeline = $this->cacheManager->getTimelineWithCache(
                        'twitter',
                        $userId,
                        'medium'
                    );
                    
                    if ($cachedTimeline !== null) {
                        Log::info('Twitter user timeline served from cache', [
                            'user_id' => $userId,
                            'from_cache' => $cachedTimeline['from_cache'],
                        ]);
                        
                        $posts = array_merge($posts, $cachedTimeline['posts']);
                        continue; // Skip API call for this user
                    }
                }
                
                // Cache miss - make API call
                $endpoint = str_replace('{id}', $userId, $this->config['endpoints']['user_tweets']);
                
                $response = $this->makeRequest('user_tweets', [
                    'max_results' => min($maxResults, 100),
                    'tweet.fields' => $this->config['search_params']['tweet_fields'],
                    'user.fields' => $this->config['search_params']['user_fields'],
                    'expansions' => $this->config['search_params']['expansions']
                ], $endpoint);
                
                if (isset($response['data'])) {
                    $userPosts = $this->processTweets($response['data'], $response['includes'] ?? []);
                    $posts = array_merge($posts, $userPosts);
                    
                    // Cache the timeline results
                    if ($this->cacheManager && !empty($userPosts)) {
                        $this->cacheManager->bulkCacheResults([
                            [
                                'platform' => 'twitter',
                                'type' => 'timeline',
                                'data' => $userPosts,
                                'params' => ['user_id' => $userId],
                                'priority' => 'medium',
                            ]
                        ]);
                        
                        Log::info('Twitter user timeline cached', [
                            'user_id' => $userId,
                            'posts_cached' => count($userPosts),
                        ]);
                    }
                }
                
                // Respect rate limits between user requests
                $this->handleRateLimit();
                
            } catch (Exception $e) {
                Log::error('Failed to get user tweets', [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $posts;
    }

    /**
     * Build search query from keywords
     */
    private function buildSearchQuery(array $keywords): string
    {
        $queryParts = [];
        
        foreach ($keywords as $keyword) {
            // Handle phrases vs single words
            if (str_contains($keyword, ' ')) {
                $queryParts[] = '"' . $keyword . '"';
            } else {
                $queryParts[] = $keyword;
            }
        }
        
        $query = implode(' OR ', $queryParts);
        
        // Add filters from config
        $filters = $this->config['filters'];
        
        if ($filters['exclude_replies']) {
            $query .= ' -is:reply';
        }
        
        if ($filters['exclude_retweets']) {
            $query .= ' -is:retweet';
        }
        
        if ($filters['verified_only']) {
            $query .= ' from:verified';
        }
        
        if ($filters['language']) {
            $query .= ' lang:' . $filters['language'];
        }
        
        return $query;
    }

    /**
     * Make authenticated request to Twitter API
     */
    private function makeRequest(string $endpoint, array $params, string $customUrl = null): array
    {
        $url = $customUrl ?? $this->config['endpoints'][$endpoint];
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->bearerToken,
            'User-Agent' => 'AI_Blockchain_Analytics/1.0'
        ])->timeout(30)->get($url, $params);
        
        // Update rate limit status
        $this->updateRateLimitStatus($response->headers());
        
        if (!$response->successful()) {
            $errorData = $response->json();
            $errorMessage = $errorData['detail'] ?? $errorData['title'] ?? 'Unknown Twitter API error';
            
            Log::error('Twitter API request failed', [
                'status' => $response->status(),
                'error' => $errorMessage,
                'url' => $url,
                'params' => $params
            ]);
            
            throw new Exception("Twitter API error: {$errorMessage} (HTTP {$response->status()})");
        }
        
        return $response->json();
    }

    /**
     * Process raw tweet data
     */
    private function processTweets(array $tweets, array $includes = []): array
    {
        $processedPosts = [];
        
        // Create lookup maps for includes
        $userLookup = [];
        $tweetLookup = [];
        
        if (isset($includes['users'])) {
            foreach ($includes['users'] as $user) {
                $userLookup[$user['id']] = $user;
            }
        }
        
        if (isset($includes['tweets'])) {
            foreach ($includes['tweets'] as $tweet) {
                $tweetLookup[$tweet['id']] = $tweet;
            }
        }
        
        foreach ($tweets as $tweet) {
            try {
                $author = $userLookup[$tweet['author_id']] ?? null;
                
                $processedPost = [
                    'id' => $tweet['id'],
                    'platform' => 'twitter',
                    'content' => $tweet['text'],
                    'author_id' => $tweet['author_id'],
                    'author_username' => $author['username'] ?? null,
                    'author_name' => $author['name'] ?? null,
                    'author_verified' => $author['verified'] ?? false,
                    'author_followers' => $author['public_metrics']['followers_count'] ?? 0,
                    'created_at' => Carbon::parse($tweet['created_at'])->toISOString(),
                    'metrics' => [
                        'retweets' => $tweet['public_metrics']['retweet_count'] ?? 0,
                        'likes' => $tweet['public_metrics']['like_count'] ?? 0,
                        'replies' => $tweet['public_metrics']['reply_count'] ?? 0,
                        'quotes' => $tweet['public_metrics']['quote_count'] ?? 0
                    ],
                    'url' => "https://twitter.com/{$author['username'] ?? 'user'}/status/{$tweet['id']}",
                    'language' => $tweet['lang'] ?? 'unknown',
                    'entities' => $this->extractEntities($tweet),
                    'referenced_tweets' => $this->processReferencedTweets($tweet, $tweetLookup),
                    'context_annotations' => $tweet['context_annotations'] ?? [],
                    'raw_data' => $tweet
                ];
                
                $processedPosts[] = $processedPost;
                
            } catch (Exception $e) {
                Log::warning('Failed to process tweet', [
                    'tweet_id' => $tweet['id'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $processedPosts;
    }

    /**
     * Extract entities from tweet
     */
    private function extractEntities(array $tweet): array
    {
        $entities = [
            'hashtags' => [],
            'mentions' => [],
            'urls' => [],
            'cashtags' => []
        ];
        
        if (isset($tweet['entities'])) {
            $tweetEntities = $tweet['entities'];
            
            if (isset($tweetEntities['hashtags'])) {
                foreach ($tweetEntities['hashtags'] as $hashtag) {
                    $entities['hashtags'][] = $hashtag['tag'];
                }
            }
            
            if (isset($tweetEntities['mentions'])) {
                foreach ($tweetEntities['mentions'] as $mention) {
                    $entities['mentions'][] = [
                        'username' => $mention['username'],
                        'id' => $mention['id']
                    ];
                }
            }
            
            if (isset($tweetEntities['urls'])) {
                foreach ($tweetEntities['urls'] as $url) {
                    $entities['urls'][] = [
                        'url' => $url['url'],
                        'expanded_url' => $url['expanded_url'] ?? null,
                        'display_url' => $url['display_url'] ?? null
                    ];
                }
            }
            
            if (isset($tweetEntities['cashtags'])) {
                foreach ($tweetEntities['cashtags'] as $cashtag) {
                    $entities['cashtags'][] = $cashtag['tag'];
                }
            }
        }
        
        return $entities;
    }

    /**
     * Process referenced tweets (retweets, quotes, replies)
     */
    private function processReferencedTweets(array $tweet, array $tweetLookup): array
    {
        $referenced = [];
        
        if (isset($tweet['referenced_tweets'])) {
            foreach ($tweet['referenced_tweets'] as $ref) {
                $referencedTweet = $tweetLookup[$ref['id']] ?? null;
                
                $referenced[] = [
                    'id' => $ref['id'],
                    'type' => $ref['type'], // retweeted, quoted, replied_to
                    'text' => $referencedTweet['text'] ?? null,
                    'author_id' => $referencedTweet['author_id'] ?? null
                ];
            }
        }
        
        return $referenced;
    }

    /**
     * Apply additional filters to posts
     */
    private function applyFilters(array $posts): array
    {
        $filtered = [];
        $filters = $this->config['filters'];
        
        foreach ($posts as $post) {
            // Skip if doesn't meet minimum engagement thresholds
            if ($post['metrics']['retweets'] < $filters['min_retweets']) {
                continue;
            }
            
            if ($post['metrics']['likes'] < $filters['min_likes']) {
                continue;
            }
            
            // Skip if verified only filter is enabled and author is not verified
            if ($filters['verified_only'] && !$post['author_verified']) {
                continue;
            }
            
            $filtered[] = $post;
        }
        
        return $filtered;
    }

    /**
     * Update rate limit status from response headers
     */
    private function updateRateLimitStatus(array $headers): void
    {
        $rateLimitHeaders = [
            'remaining' => $headers['x-rate-limit-remaining'][0] ?? null,
            'limit' => $headers['x-rate-limit-limit'][0] ?? null,
            'reset' => $headers['x-rate-limit-reset'][0] ?? null
        ];
        
        $this->rateLimitStatus = array_filter($rateLimitHeaders);
        
        // Cache rate limit status using cache manager if available
        if (!empty($this->rateLimitStatus)) {
            if ($this->cacheManager) {
                $this->cacheManager->cacheCrawlerRateLimit('twitter', $this->rateLimitStatus);
            } else {
                // Fallback to Laravel cache
                Cache::put('twitter_rate_limit', $this->rateLimitStatus, 900); // 15 minutes
            }
        }
    }

    /**
     * Handle rate limiting
     */
    private function handleRateLimit(): void
    {
        if (isset($this->rateLimitStatus['remaining']) && (int)$this->rateLimitStatus['remaining'] < 10) {
            $resetTime = (int)($this->rateLimitStatus['reset'] ?? time() + 900);
            $sleepTime = max(0, $resetTime - time() + 5); // Add 5 second buffer
            
            Log::info('Twitter rate limit approached, sleeping', [
                'remaining' => $this->rateLimitStatus['remaining'],
                'sleep_time' => $sleepTime
            ]);
            
            if ($sleepTime > 0 && $sleepTime < 900) { // Don't sleep more than 15 minutes
                sleep($sleepTime);
            }
        }
    }

    /**
     * Health check for Twitter API
     */
    public function healthCheck(): array
    {
        try {
            // Make a simple request to check connectivity
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->bearerToken
            ])->timeout(10)->get($this->config['endpoints']['search'], [
                'query' => 'test',
                'max_results' => 10
            ]);
            
            return [
                'status' => $response->successful() ? 'healthy' : 'unhealthy',
                'response_time' => $response->transferStats?->getTransferTime() ?? null,
                'rate_limit' => $this->rateLimitStatus
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get current rate limit status
     */
    public function getRateLimitStatus(): array
    {
        return Cache::get('twitter_rate_limit', $this->rateLimitStatus);
    }

    /**
     * Search for trending topics
     */
    public function getTrendingTopics(string $woeid = '1'): array
    {
        // This would require Twitter API v1.1 trends endpoint
        // For now, return empty array as v2 API doesn't have trends endpoint
        return [];
    }

    /**
     * Get user information
     */
    public function getUserInfo(string $username): ?array
    {
        try {
            $response = $this->makeRequest('user_by_username', [
                'user.fields' => $this->config['search_params']['user_fields']
            ], "https://api.twitter.com/2/users/by/username/{$username}");
            
            return $response['data'] ?? null;
            
        } catch (Exception $e) {
            Log::error('Failed to get Twitter user info', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}