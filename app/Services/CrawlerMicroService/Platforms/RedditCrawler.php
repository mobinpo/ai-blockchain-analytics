<?php

namespace App\Services\CrawlerMicroService\Platforms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Services\CrawlerMicroService\CrawlerCacheManager;
use Carbon\Carbon;
use Exception;

class RedditCrawler implements PlatformCrawlerInterface
{
    private array $config;
    private ?string $accessToken = null;
    private array $rateLimitStatus;
    private ?CrawlerCacheManager $cacheManager = null;

    public function __construct(array $config, ?CrawlerCacheManager $cacheManager = null)
    {
        $this->config = $config;
        $this->rateLimitStatus = [];
        $this->cacheManager = $cacheManager;
        
        if (empty($config['client_id']) || empty($config['client_secret'])) {
            throw new Exception('Reddit API credentials are required');
        }
    }

    /**
     * Search Reddit by keywords with intelligent caching
     */
    public function searchByKeywords(array $keywords, int $maxResults = 100): array
    {
        $posts = [];
        $query = implode(' OR ', $keywords);
        
        try {
            // Try cache-first approach if cache manager is available
            if ($this->cacheManager) {
                $filters = [
                    'max_results' => $maxResults,
                    'subreddits' => $this->config['target_subreddits'],
                    'sort' => $this->config['default_sort'] ?? 'new',
                ];
                
                $cachedResult = $this->cacheManager->searchWithCache(
                    'reddit',
                    $query,
                    $filters,
                    'medium', // Default priority
                    true      // Allow stale data
                );
                
                if ($cachedResult !== null) {
                    Log::info('Reddit search served from cache', [
                        'query' => $query,
                        'from_cache' => $cachedResult['from_cache'],
                        'cache_fresh' => $cachedResult['cache_fresh'] ?? false,
                    ]);
                    
                    return [
                        'status' => 'success',
                        'posts' => $cachedResult['posts'],
                        'platform' => 'reddit',
                        'query' => $query,
                        'from_cache' => true,
                        'total_results' => count($cachedResult['posts']),
                    ];
                }
            }
            
            // Cache miss - make API calls
            // Ensure we have a valid access token
            $this->ensureAuthenticated();
            
            // Search across all configured subreddits
            foreach ($this->config['target_subreddits'] as $subreddit) {
                $subredditPosts = $this->searchInSubreddit($subreddit, $keywords, $maxResults);
                $posts = array_merge($posts, $subredditPosts);
                
                // Respect rate limits
                $this->handleRateLimit();
            }
            
            // Also do a general search
            $generalPosts = $this->generalSearch($keywords, $maxResults);
            $posts = array_merge($posts, $generalPosts);
            
            // Remove duplicates and apply filters
            $posts = $this->removeDuplicates($posts);
            $posts = $this->applyFilters($posts);
            
            // Cache the results if cache manager is available
            if ($this->cacheManager && !empty($posts)) {
                $filters = [
                    'max_results' => $maxResults,
                    'subreddits' => $this->config['target_subreddits'],
                    'sort' => $this->config['default_sort'] ?? 'new',
                ];
                
                $this->cacheManager->cacheSearchResults(
                    'reddit',
                    $query,
                    $filters,
                    $posts,
                    'medium' // Default priority
                );
                
                Log::info('Reddit search results cached', [
                    'query' => $query,
                    'posts_cached' => count($posts),
                ]);
            }
            
            Log::info('Reddit search completed', [
                'posts_found' => count($posts),
                'keywords' => $keywords,
                'from_cache' => false,
            ]);
            
        } catch (Exception $e) {
            Log::error('Reddit search failed', [
                'error' => $e->getMessage(),
                'keywords' => $keywords
            ]);
            throw $e;
        }
        
        return [
            'status' => 'success',
            'posts' => $posts,
            'platform' => 'reddit',
            'query' => $query,
            'from_cache' => false,
            'total_results' => count($posts),
        ];
    }

    /**
     * Search in specific subreddit
     */
    private function searchInSubreddit(string $subreddit, array $keywords, int $maxResults): array
    {
        $posts = [];
        
        try {
            // Build search query
            $query = implode(' OR ', $keywords);
            
            $response = $this->makeAuthenticatedRequest('GET', $this->config['endpoints']['search'], [
                'q' => $query,
                'subreddit' => $subreddit,
                'limit' => min($maxResults, $this->config['search_params']['limit']),
                'sort' => $this->config['search_params']['sort'],
                't' => $this->config['search_params']['time'],
                'type' => $this->config['search_params']['type']
            ]);
            
            if (isset($response['data']['children'])) {
                foreach ($response['data']['children'] as $child) {
                    $postData = $child['data'];
                    $posts[] = $this->processRedditPost($postData);
                }
            }
            
        } catch (Exception $e) {
            Log::warning('Failed to search subreddit', [
                'subreddit' => $subreddit,
                'error' => $e->getMessage()
            ]);
        }
        
        return $posts;
    }

    /**
     * General Reddit search
     */
    private function generalSearch(array $keywords, int $maxResults): array
    {
        $posts = [];
        
        try {
            $query = implode(' OR ', $keywords);
            
            $response = $this->makeAuthenticatedRequest('GET', $this->config['endpoints']['search'], [
                'q' => $query,
                'limit' => min($maxResults, $this->config['search_params']['limit']),
                'sort' => $this->config['search_params']['sort'],
                't' => $this->config['search_params']['time'],
                'type' => $this->config['search_params']['type']
            ]);
            
            if (isset($response['data']['children'])) {
                foreach ($response['data']['children'] as $child) {
                    $postData = $child['data'];
                    $posts[] = $this->processRedditPost($postData);
                }
            }
            
        } catch (Exception $e) {
            Log::warning('Failed general Reddit search', [
                'error' => $e->getMessage(),
                'keywords' => $keywords
            ]);
        }
        
        return $posts;
    }

    /**
     * Get posts from specific subreddit
     */
    public function getSubredditPosts(string $subreddit, string $sort = 'new', int $limit = 25): array
    {
        $posts = [];
        
        try {
            $this->ensureAuthenticated();
            
            $endpoint = str_replace('{subreddit}', $subreddit, $this->config['endpoints']['subreddit_posts']);
            
            $response = $this->makeAuthenticatedRequest('GET', $endpoint, [
                'limit' => $limit,
                'sort' => $sort
            ]);
            
            if (isset($response['data']['children'])) {
                foreach ($response['data']['children'] as $child) {
                    $postData = $child['data'];
                    $posts[] = $this->processRedditPost($postData);
                }
            }
            
        } catch (Exception $e) {
            Log::error('Failed to get subreddit posts', [
                'subreddit' => $subreddit,
                'error' => $e->getMessage()
            ]);
        }
        
        return $posts;
    }

    /**
     * Process raw Reddit post data
     */
    private function processRedditPost(array $postData): array
    {
        return [
            'id' => $postData['id'],
            'platform' => 'reddit',
            'title' => $postData['title'] ?? '',
            'content' => $this->extractContent($postData),
            'author' => $postData['author'] ?? '[deleted]',
            'subreddit' => $postData['subreddit'] ?? '',
            'created_at' => Carbon::createFromTimestamp($postData['created_utc'])->toISOString(),
            'url' => 'https://reddit.com' . $postData['permalink'],
            'external_url' => $postData['url'] ?? null,
            'metrics' => [
                'score' => $postData['score'] ?? 0,
                'upvote_ratio' => $postData['upvote_ratio'] ?? 0,
                'num_comments' => $postData['num_comments'] ?? 0,
                'awards' => $postData['total_awards_received'] ?? 0
            ],
            'post_type' => $this->determinePostType($postData),
            'is_nsfw' => $postData['over_18'] ?? false,
            'is_spoiler' => $postData['spoiler'] ?? false,
            'is_stickied' => $postData['stickied'] ?? false,
            'flair' => [
                'author_flair_text' => $postData['author_flair_text'] ?? null,
                'link_flair_text' => $postData['link_flair_text'] ?? null
            ],
            'media_metadata' => $this->extractMediaMetadata($postData),
            'raw_data' => $postData
        ];
    }

    /**
     * Extract content from Reddit post
     */
    private function extractContent(array $postData): string
    {
        $content = '';
        
        // Start with title
        if (!empty($postData['title'])) {
            $content .= $postData['title'] . "\n\n";
        }
        
        // Add selftext if it's a text post
        if (!empty($postData['selftext'])) {
            $content .= $postData['selftext'];
        }
        
        // If it's a link post, include the URL
        if (!empty($postData['url']) && $postData['url'] !== $postData['permalink']) {
            $content .= "\n\nLink: " . $postData['url'];
        }
        
        return trim($content);
    }

    /**
     * Determine post type
     */
    private function determinePostType(array $postData): string
    {
        if (!empty($postData['selftext'])) {
            return 'text';
        }
        
        if (isset($postData['post_hint'])) {
            return $postData['post_hint']; // image, video, link, etc.
        }
        
        if (!empty($postData['url'])) {
            if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $postData['url'])) {
                return 'image';
            }
            if (preg_match('/\.(mp4|webm|mov)$/i', $postData['url'])) {
                return 'video';
            }
            return 'link';
        }
        
        return 'unknown';
    }

    /**
     * Extract media metadata
     */
    private function extractMediaMetadata(array $postData): array
    {
        $metadata = [];
        
        if (isset($postData['preview']['images'])) {
            $metadata['images'] = [];
            foreach ($postData['preview']['images'] as $image) {
                $metadata['images'][] = [
                    'url' => $image['source']['url'] ?? null,
                    'width' => $image['source']['width'] ?? null,
                    'height' => $image['source']['height'] ?? null
                ];
            }
        }
        
        if (isset($postData['media']['reddit_video'])) {
            $metadata['video'] = [
                'url' => $postData['media']['reddit_video']['fallback_url'] ?? null,
                'duration' => $postData['media']['reddit_video']['duration'] ?? null,
                'width' => $postData['media']['reddit_video']['width'] ?? null,
                'height' => $postData['media']['reddit_video']['height'] ?? null
            ];
        }
        
        return $metadata;
    }

    /**
     * Apply filters to posts
     */
    private function applyFilters(array $posts): array
    {
        $filtered = [];
        $filters = $this->config['filters'];
        
        foreach ($posts as $post) {
            // Skip if score is too low
            if ($post['metrics']['score'] < $filters['min_score']) {
                continue;
            }
            
            // Skip if not enough comments
            if ($post['metrics']['num_comments'] < $filters['min_comments']) {
                continue;
            }
            
            // Skip NSFW if not allowed
            if (!$filters['nsfw'] && $post['is_nsfw']) {
                continue;
            }
            
            // Skip spoilers if not allowed
            if (!$filters['spoiler'] && $post['is_spoiler']) {
                continue;
            }
            
            $filtered[] = $post;
        }
        
        return $filtered;
    }

    /**
     * Remove duplicate posts
     */
    private function removeDuplicates(array $posts): array
    {
        $seen = [];
        $unique = [];
        
        foreach ($posts as $post) {
            if (!isset($seen[$post['id']])) {
                $seen[$post['id']] = true;
                $unique[] = $post;
            }
        }
        
        return $unique;
    }

    /**
     * Ensure we have a valid access token
     */
    private function ensureAuthenticated(): void
    {
        if ($this->accessToken && $this->isTokenValid()) {
            return;
        }
        
        $this->authenticate();
    }

    /**
     * Authenticate with Reddit API
     */
    private function authenticate(): void
    {
        try {
            $response = Http::asForm()
                ->withBasicAuth($this->config['client_id'], $this->config['client_secret'])
                ->withHeaders([
                    'User-Agent' => $this->config['user_agent']
                ])
                ->post($this->config['endpoints']['oauth'], [
                    'grant_type' => 'password',
                    'username' => $this->config['username'],
                    'password' => $this->config['password']
                ]);
            
            if (!$response->successful()) {
                throw new Exception('Reddit authentication failed: ' . $response->body());
            }
            
            $data = $response->json();
            $this->accessToken = $data['access_token'];
            
            // Cache the token
            Cache::put('reddit_access_token', [
                'token' => $this->accessToken,
                'expires_at' => now()->addSeconds($data['expires_in'] - 60) // Subtract 60s buffer
            ], $data['expires_in'] - 60);
            
            Log::info('Reddit authentication successful');
            
        } catch (Exception $e) {
            Log::error('Reddit authentication failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Check if current token is valid
     */
    private function isTokenValid(): bool
    {
        $tokenData = Cache::get('reddit_access_token');
        
        if (!$tokenData) {
            return false;
        }
        
        if (Carbon::now()->isAfter($tokenData['expires_at'])) {
            return false;
        }
        
        $this->accessToken = $tokenData['token'];
        return true;
    }

    /**
     * Make authenticated request to Reddit API
     */
    private function makeAuthenticatedRequest(string $method, string $endpoint, array $params = []): array
    {
        if (!$this->accessToken) {
            throw new Exception('No valid Reddit access token');
        }
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken,
            'User-Agent' => $this->config['user_agent']
        ])->timeout(30);
        
        if ($method === 'GET') {
            $response = $response->get($endpoint, $params);
        } else {
            $response = $response->send($method, $endpoint, ['json' => $params]);
        }
        
        // Update rate limit status
        $this->updateRateLimitStatus($response->headers());
        
        if (!$response->successful()) {
            $errorData = $response->json();
            $errorMessage = $errorData['message'] ?? 'Unknown Reddit API error';
            
            Log::error('Reddit API request failed', [
                'status' => $response->status(),
                'error' => $errorMessage,
                'endpoint' => $endpoint,
                'params' => $params
            ]);
            
            // If unauthorized, try to re-authenticate
            if ($response->status() === 401) {
                $this->accessToken = null;
                Cache::forget('reddit_access_token');
                throw new Exception('Reddit authentication expired, please retry');
            }
            
            throw new Exception("Reddit API error: {$errorMessage} (HTTP {$response->status()})");
        }
        
        return $response->json();
    }

    /**
     * Update rate limit status from response headers
     */
    private function updateRateLimitStatus(array $headers): void
    {
        $rateLimitHeaders = [
            'remaining' => $headers['x-ratelimit-remaining'][0] ?? null,
            'used' => $headers['x-ratelimit-used'][0] ?? null,
            'reset' => $headers['x-ratelimit-reset'][0] ?? null
        ];
        
        $this->rateLimitStatus = array_filter($rateLimitHeaders);
        
        // Cache rate limit status
        if (!empty($this->rateLimitStatus)) {
            Cache::put('reddit_rate_limit', $this->rateLimitStatus, 900); // 15 minutes
        }
    }

    /**
     * Handle rate limiting
     */
    private function handleRateLimit(): void
    {
        if (isset($this->rateLimitStatus['remaining']) && (int)$this->rateLimitStatus['remaining'] < 5) {
            $resetTime = (int)($this->rateLimitStatus['reset'] ?? time() + 60);
            $sleepTime = max(0, $resetTime - time() + 5); // Add 5 second buffer
            
            Log::info('Reddit rate limit approached, sleeping', [
                'remaining' => $this->rateLimitStatus['remaining'],
                'sleep_time' => $sleepTime
            ]);
            
            if ($sleepTime > 0 && $sleepTime < 300) { // Don't sleep more than 5 minutes
                sleep($sleepTime);
            }
        }
    }

    /**
     * Health check for Reddit API
     */
    public function healthCheck(): array
    {
        try {
            $this->ensureAuthenticated();
            
            // Make a simple request to check connectivity
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'User-Agent' => $this->config['user_agent']
            ])->timeout(10)->get('https://oauth.reddit.com/api/v1/me');
            
            return [
                'status' => $response->successful() ? 'healthy' : 'unhealthy',
                'authenticated' => !empty($this->accessToken),
                'rate_limit' => $this->rateLimitStatus
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'authenticated' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get current rate limit status
     */
    public function getRateLimitStatus(): array
    {
        return Cache::get('reddit_rate_limit', $this->rateLimitStatus);
    }

    /**
     * Get post comments
     */
    public function getPostComments(string $subreddit, string $postId, int $limit = 50): array
    {
        try {
            $this->ensureAuthenticated();
            
            $endpoint = str_replace(['{subreddit}', '{post_id}'], [$subreddit, $postId], $this->config['endpoints']['post_comments']);
            
            $response = $this->makeAuthenticatedRequest('GET', $endpoint, [
                'limit' => $limit,
                'sort' => 'top'
            ]);
            
            $comments = [];
            
            if (isset($response[1]['data']['children'])) {
                foreach ($response[1]['data']['children'] as $child) {
                    if ($child['kind'] === 't1') { // Comment
                        $commentData = $child['data'];
                        $comments[] = [
                            'id' => $commentData['id'],
                            'body' => $commentData['body'] ?? '',
                            'author' => $commentData['author'] ?? '[deleted]',
                            'score' => $commentData['score'] ?? 0,
                            'created_at' => Carbon::createFromTimestamp($commentData['created_utc'])->toISOString(),
                            'is_submitter' => $commentData['is_submitter'] ?? false,
                            'stickied' => $commentData['stickied'] ?? false
                        ];
                    }
                }
            }
            
            return $comments;
            
        } catch (Exception $e) {
            Log::error('Failed to get post comments', [
                'subreddit' => $subreddit,
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}