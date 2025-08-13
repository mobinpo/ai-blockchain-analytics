<?php

declare(strict_types=1);

namespace App\Services\SocialCrawler\Crawlers;

use App\Services\SocialCrawler\CrawlerInterface;
use App\Services\SocialCrawler\RateLimitManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class RedditCrawler implements CrawlerInterface
{
    private ?string $clientId;
    private ?string $clientSecret;
    private string $userAgent;
    private RateLimitManager $rateLimitManager;
    private ?string $accessToken = null;

    public function __construct(RateLimitManager $rateLimitManager)
    {
        $this->clientId = config('social_crawler.reddit.client_id');
        $this->clientSecret = config('social_crawler.reddit.client_secret');
        $this->userAgent = config('social_crawler.reddit.user_agent', 'BlockchainAnalyticsCrawler/1.0');
        $this->rateLimitManager = $rateLimitManager;

        if (empty($this->clientId) || empty($this->clientSecret)) {
            Log::warning('Reddit API credentials not configured, Reddit crawler will be disabled');
        }
    }

    public function crawl(array $params): array
    {
        if (empty($this->clientId) || empty($this->clientSecret)) {
            Log::warning('Reddit crawl skipped: no API credentials configured');
            return [];
        }

        $keywords = $params['keywords'] ?? [];
        $maxResults = min($params['max_results'] ?? 100, 100);
        $since = $params['since'] ?? now()->subHour();
        $subreddits = $params['subreddits'] ?? $this->getDefaultSubreddits();

        if (empty($keywords)) {
            return [];
        }

        $this->ensureAuthenticated();
        
        $allPosts = [];

        foreach ($keywords as $keyword) {
            $posts = $this->searchPosts($keyword, [
                'limit' => min(25, ceil($maxResults / count($keywords))),
                'sort' => 'new',
                'restrict_sr' => false, // Search all subreddits
                'since' => $since
            ]);

            $allPosts = array_merge($allPosts, $posts);
            
            if (count($allPosts) >= $maxResults) {
                break;
            }
        }

        // Also search in specific crypto-related subreddits
        foreach ($subreddits as $subreddit) {
            if (count($allPosts) >= $maxResults) {
                break;
            }

            $posts = $this->getSubredditPosts($subreddit, [
                'limit' => 10,
                'sort' => 'new',
                'keywords' => $keywords,
                'since' => $since
            ]);

            $allPosts = array_merge($allPosts, $posts);
        }

        // Remove duplicates and limit results
        $uniquePosts = $this->removeDuplicates($allPosts);
        
        return array_slice($uniquePosts, 0, $maxResults);
    }

    public function search(array $params): array
    {
        $keywords = $params['keywords'] ?? [];
        $maxResults = min($params['max_results'] ?? 50, 100);
        $since = $params['since'] ?? now()->subHours(24);
        $sort = $params['sort'] ?? 'relevance';

        $this->ensureAuthenticated();
        
        $allPosts = [];

        foreach ($keywords as $keyword) {
            $posts = $this->searchPosts($keyword, [
                'limit' => min(25, ceil($maxResults / count($keywords))),
                'sort' => $sort,
                'since' => $since
            ]);

            $allPosts = array_merge($allPosts, $posts);
        }

        return array_slice($this->removeDuplicates($allPosts), 0, $maxResults);
    }

    public function getUserPosts(string $username, array $params = []): array
    {
        $maxResults = min($params['max_results'] ?? 20, 100);
        $since = $params['since'] ?? now()->subHours(24);
        $sort = $params['sort'] ?? 'new';

        $this->ensureAuthenticated();

        return $this->getUserSubmissions($username, [
            'limit' => $maxResults,
            'sort' => $sort,
            'since' => $since
        ]);
    }

    private function searchPosts(string $query, array $params = []): array
    {
        $endpoint = 'search';
        
        if (!$this->rateLimitManager->canMakeRequest('reddit', $endpoint)) {
            Log::warning('Reddit API rate limit exceeded', ['endpoint' => $endpoint]);
            return [];
        }

        $url = 'https://oauth.reddit.com/search';
        
        $requestParams = array_merge([
            'q' => $query,
            'type' => 'link',
            'sort' => 'new',
            'limit' => 25,
            'restrict_sr' => false
        ], $params);

        // Remove custom params that Reddit doesn't recognize
        $since = $requestParams['since'] ?? null;
        unset($requestParams['since']);

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->accessToken}",
                'User-Agent' => $this->userAgent
            ])->get($url, $requestParams);

            $this->rateLimitManager->recordRequest('reddit', $endpoint);

            if (!$response->successful()) {
                $this->handleApiError($response, $endpoint);
                return [];
            }

            $data = $response->json();
            $posts = $this->transformPosts($data);

            // Filter by date if specified
            if ($since) {
                $sinceTimestamp = Carbon::parse($since)->timestamp;
                $posts = array_filter($posts, function ($post) use ($sinceTimestamp) {
                    return Carbon::parse($post['published_at'])->timestamp >= $sinceTimestamp;
                });
            }

            return array_values($posts);

        } catch (\Exception $e) {
            Log::error('Reddit search request failed', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    private function getSubredditPosts(string $subreddit, array $params = []): array
    {
        $endpoint = "r/{$subreddit}/new";
        
        if (!$this->rateLimitManager->canMakeRequest('reddit', $endpoint)) {
            Log::warning('Reddit API rate limit exceeded', ['endpoint' => $endpoint]);
            return [];
        }

        $url = "https://oauth.reddit.com/r/{$subreddit}/new";
        
        $requestParams = [
            'limit' => $params['limit'] ?? 25,
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->accessToken}",
                'User-Agent' => $this->userAgent
            ])->get($url, $requestParams);

            $this->rateLimitManager->recordRequest('reddit', $endpoint);

            if (!$response->successful()) {
                $this->handleApiError($response, $endpoint);
                return [];
            }

            $data = $response->json();
            $posts = $this->transformPosts($data);

            // Filter by keywords and date
            $keywords = $params['keywords'] ?? [];
            $since = $params['since'] ?? null;

            if (!empty($keywords) || $since) {
                $posts = array_filter($posts, function ($post) use ($keywords, $since) {
                    // Check date filter
                    if ($since && Carbon::parse($post['published_at'])->lt(Carbon::parse($since))) {
                        return false;
                    }

                    // Check keyword filter
                    if (!empty($keywords)) {
                        $content = strtolower($post['content'] . ' ' . ($post['title'] ?? ''));
                        foreach ($keywords as $keyword) {
                            if (str_contains($content, strtolower($keyword))) {
                                return true;
                            }
                        }
                        return false;
                    }

                    return true;
                });
            }

            return array_values($posts);

        } catch (\Exception $e) {
            Log::error('Reddit subreddit request failed', [
                'subreddit' => $subreddit,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    private function getUserSubmissions(string $username, array $params = []): array
    {
        $endpoint = "user/{$username}/submitted";
        
        if (!$this->rateLimitManager->canMakeRequest('reddit', $endpoint)) {
            Log::warning('Reddit API rate limit exceeded', ['endpoint' => $endpoint]);
            return [];
        }

        $url = "https://oauth.reddit.com/user/{$username}/submitted";
        
        $requestParams = [
            'limit' => $params['limit'] ?? 25,
            'sort' => $params['sort'] ?? 'new',
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->accessToken}",
                'User-Agent' => $this->userAgent
            ])->get($url, $requestParams);

            $this->rateLimitManager->recordRequest('reddit', $endpoint);

            if (!$response->successful()) {
                $this->handleApiError($response, $endpoint);
                return [];
            }

            $data = $response->json();
            $posts = $this->transformPosts($data);

            // Filter by date if specified
            $since = $params['since'] ?? null;
            if ($since) {
                $sinceTimestamp = Carbon::parse($since)->timestamp;
                $posts = array_filter($posts, function ($post) use ($sinceTimestamp) {
                    return Carbon::parse($post['published_at'])->timestamp >= $sinceTimestamp;
                });
            }

            return array_values($posts);

        } catch (\Exception $e) {
            Log::error('Reddit user submissions request failed', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    private function transformPosts(array $data): array
    {
        $posts = [];
        $children = $data['data']['children'] ?? [];

        foreach ($children as $child) {
            $post = $child['data'];
            
            // Skip if it's not a post (could be a comment)
            if ($child['kind'] !== 't3') {
                continue;
            }

            $mediaUrls = $this->extractMediaUrls($post);
            
            $posts[] = [
                'external_id' => $post['id'],
                'source_url' => 'https://reddit.com' . $post['permalink'],
                'author_username' => $post['author'],
                'author_display_name' => $post['author'],
                'author_id' => $post['author_fullname'] ?? null,
                'content' => $this->getPostContent($post),
                'title' => $post['title'] ?? null,
                'media_urls' => $mediaUrls,
                'engagement_count' => $post['score'] ?? 0,
                'share_count' => 0, // Reddit doesn't provide share count
                'comment_count' => $post['num_comments'] ?? 0,
                'published_at' => Carbon::createFromTimestamp($post['created_utc'])->toISOString(),
                'subreddit' => $post['subreddit'],
                'subreddit_subscribers' => $post['subreddit_subscribers'] ?? 0,
                'is_nsfw' => $post['over_18'] ?? false,
                'is_stickied' => $post['stickied'] ?? false,
                'flair' => $post['link_flair_text'] ?? null,
                'upvote_ratio' => $post['upvote_ratio'] ?? null,
                'awards_count' => count($post['all_awardings'] ?? []),
            ];
        }

        return $posts;
    }

    private function getPostContent(array $post): string
    {
        $content = $post['title'] ?? '';
        
        if (!empty($post['selftext'])) {
            $content .= "\n\n" . $post['selftext'];
        } elseif (!empty($post['url']) && !str_contains($post['url'], 'reddit.com')) {
            $content .= "\n\n" . $post['url'];
        }

        return trim($content);
    }

    private function extractMediaUrls(array $post): array
    {
        $urls = [];
        
        // Image/video URL
        if (!empty($post['url'])) {
            $url = $post['url'];
            
            // Check if it's a media URL
            if ($this->isMediaUrl($url)) {
                $urls[] = $url;
            }
        }

        // Reddit gallery
        if (isset($post['is_gallery']) && $post['is_gallery']) {
            $gallery = $post['media_metadata'] ?? [];
            foreach ($gallery as $item) {
                if (isset($item['s']['u'])) {
                    $urls[] = html_entity_decode($item['s']['u']);
                }
            }
        }

        // Video preview
        if (isset($post['preview']['images'][0]['source']['url'])) {
            $urls[] = html_entity_decode($post['preview']['images'][0]['source']['url']);
        }

        return array_unique($urls);
    }

    private function isMediaUrl(string $url): bool
    {
        $mediaExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm'];
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        
        return in_array($extension, $mediaExtensions) || 
               str_contains($url, 'i.redd.it') || 
               str_contains($url, 'v.redd.it') ||
               str_contains($url, 'imgur.com') ||
               str_contains($url, 'gfycat.com');
    }

    private function removeDuplicates(array $posts): array
    {
        $seen = [];
        $unique = [];
        
        foreach ($posts as $post) {
            $id = $post['external_id'];
            if (!isset($seen[$id])) {
                $seen[$id] = true;
                $unique[] = $post;
            }
        }
        
        return $unique;
    }

    private function ensureAuthenticated(): void
    {
        if ($this->accessToken && $this->isTokenValid()) {
            return;
        }

        $this->authenticate();
    }

    private function authenticate(): void
    {
        $url = 'https://www.reddit.com/api/v1/access_token';
        
        try {
            $response = Http::asForm()
                ->withBasicAuth($this->clientId, $this->clientSecret)
                ->withHeaders(['User-Agent' => $this->userAgent])
                ->post($url, [
                    'grant_type' => 'client_credentials'
                ]);

            if (!$response->successful()) {
                throw new \Exception('Reddit authentication failed: ' . $response->body());
            }

            $data = $response->json();
            $this->accessToken = $data['access_token'];
            
            // Cache the token (expires in 1 hour by default)
            Cache::put('reddit_access_token', $this->accessToken, 3500); // 58 minutes
            
            Log::info('Reddit API authenticated successfully');

        } catch (\Exception $e) {
            Log::error('Reddit authentication failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function isTokenValid(): bool
    {
        // Simple check - try to make a basic API call
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->accessToken}",
                'User-Agent' => $this->userAgent
            ])->get('https://oauth.reddit.com/api/v1/me');

            return $response->successful();
            
        } catch (\Exception $e) {
            return false;
        }
    }

    private function handleApiError($response, string $endpoint): void
    {
        $statusCode = $response->status();
        $body = $response->json();
        $error = $body['message'] ?? $body['error'] ?? 'Unknown error';

        Log::error('Reddit API error', [
            'endpoint' => $endpoint,
            'status_code' => $statusCode,
            'error' => $error,
            'response_body' => $body
        ]);

        if ($statusCode === 429) {
            $this->rateLimitManager->handleRateLimit('reddit', $endpoint, $response->headers());
        } elseif ($statusCode === 401) {
            // Token expired, clear it
            $this->accessToken = null;
            Cache::forget('reddit_access_token');
        }

        throw new \Exception("Reddit API error ({$statusCode}): {$error}");
    }

    private function getDefaultSubreddits(): array
    {
        return [
            'cryptocurrency',
            'CryptoCurrency',
            'bitcoin',
            'ethereum',
            'defi',
            'NFT',
            'CryptoMarkets',
            'BlockChain',
            'CryptoCurrencyTrading',
            'altcoin',
            'CryptoTechnology',
            'ethtrader',
            'bitcoinmarkets',
            'cryptomoonshots',
            'web3',
        ];
    }

    public function testConnection(): bool
    {
        try {
            $this->ensureAuthenticated();
            
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->accessToken}",
                'User-Agent' => $this->userAgent
            ])->get('https://oauth.reddit.com/api/v1/me');

            return $response->successful();
            
        } catch (\Exception $e) {
            Log::error('Reddit connection test failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function getRateLimitStatus(): array
    {
        return $this->rateLimitManager->getStatus('reddit');
    }

    public function getPlatformName(): string
    {
        return 'reddit';
    }

    /**
     * Get subreddit information
     */
    public function getSubredditInfo(string $subreddit): ?array
    {
        $this->ensureAuthenticated();
        
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->accessToken}",
                'User-Agent' => $this->userAgent
            ])->get("https://oauth.reddit.com/r/{$subreddit}/about");

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'] ?? null;
            }
            
            return null;
            
        } catch (\Exception $e) {
            Log::error('Failed to get subreddit info', [
                'subreddit' => $subreddit,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Get post comments
     */
    public function getPostComments(string $subreddit, string $postId, int $limit = 10): array
    {
        $this->ensureAuthenticated();
        
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->accessToken}",
                'User-Agent' => $this->userAgent
            ])->get("https://oauth.reddit.com/r/{$subreddit}/comments/{$postId}", [
                'limit' => $limit,
                'depth' => 1
            ]);

            if ($response->successful()) {
                $data = $response->json();
                // Reddit returns an array with post data at [0] and comments at [1]
                return $this->transformComments($data[1] ?? []);
            }
            
            return [];
            
        } catch (\Exception $e) {
            Log::error('Failed to get post comments', [
                'subreddit' => $subreddit,
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }

    private function transformComments(array $commentsData): array
    {
        $comments = [];
        $children = $commentsData['data']['children'] ?? [];

        foreach ($children as $child) {
            if ($child['kind'] !== 't1') { // t1 = comment
                continue;
            }

            $comment = $child['data'];
            
            $comments[] = [
                'id' => $comment['id'],
                'author' => $comment['author'],
                'content' => $comment['body'],
                'score' => $comment['score'],
                'created_at' => Carbon::createFromTimestamp($comment['created_utc'])->toISOString(),
                'is_submitter' => $comment['is_submitter'] ?? false,
            ];
        }

        return $comments;
    }
}