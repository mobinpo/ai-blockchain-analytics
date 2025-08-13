<?php

declare(strict_types=1);

namespace App\Services\SocialCrawler\Crawlers;

use App\Services\SocialCrawler\CrawlerInterface;
use App\Services\SocialCrawler\RateLimitManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class TwitterCrawler implements CrawlerInterface
{
    private ?string $bearerToken;
    private string $apiVersion;
    private RateLimitManager $rateLimitManager;
    private array $config;

    public function __construct(RateLimitManager $rateLimitManager)
    {
        $this->bearerToken = config('social_crawler.twitter.bearer_token');
        $this->apiVersion = config('social_crawler.twitter.api_version', '2');
        $this->rateLimitManager = $rateLimitManager;
        $this->config = config('social_crawler.twitter', []);

        if (empty($this->bearerToken)) {
            Log::warning('Twitter Bearer Token not configured, Twitter crawler will be disabled');
        }
    }

    public function crawl(array $params): array
    {
        if (empty($this->bearerToken)) {
            Log::warning('Twitter crawl skipped: no bearer token configured');
            return [];
        }

        $keywords = $params['keywords'] ?? [];
        $maxResults = min($params['max_results'] ?? 100, 100); // Twitter API limit
        $since = $params['since'] ?? now()->subHour();

        if (empty($keywords)) {
            return [];
        }

        // Build search query
        $query = $this->buildSearchQuery($keywords);
        
        return $this->searchTweets($query, [
            'max_results' => $maxResults,
            'start_time' => Carbon::parse($since)->toISOString(),
            'expansions' => 'author_id,attachments.media_keys',
            'tweet.fields' => 'created_at,public_metrics,context_annotations,lang,reply_settings',
            'user.fields' => 'username,name,verified,public_metrics',
            'media.fields' => 'url,preview_image_url,type'
        ]);
    }

    public function search(array $params): array
    {
        $keywords = $params['keywords'] ?? [];
        $maxResults = min($params['max_results'] ?? 50, 100);
        $since = $params['since'] ?? now()->subHours(24);

        $query = $this->buildSearchQuery($keywords);
        
        return $this->searchTweets($query, [
            'max_results' => $maxResults,
            'start_time' => Carbon::parse($since)->toISOString(),
            'expansions' => 'author_id',
            'tweet.fields' => 'created_at,public_metrics,lang',
            'user.fields' => 'username,name,verified'
        ]);
    }

    public function getUserPosts(string $username, array $params = []): array
    {
        $maxResults = min($params['max_results'] ?? 20, 100);
        $since = $params['since'] ?? now()->subHours(24);

        // Get user ID first
        $userId = $this->getUserId($username);
        if (!$userId) {
            throw new \Exception("User not found: {$username}");
        }

        return $this->getUserTweets($userId, [
            'max_results' => $maxResults,
            'start_time' => Carbon::parse($since)->toISOString(),
            'tweet.fields' => 'created_at,public_metrics,context_annotations,lang',
            'expansions' => 'attachments.media_keys',
            'media.fields' => 'url,preview_image_url,type'
        ]);
    }

    private function searchTweets(string $query, array $params = []): array
    {
        $endpoint = 'tweets/search/recent';
        
        if (!$this->rateLimitManager->canMakeRequest('twitter', $endpoint)) {
            Log::warning('Twitter API rate limit exceeded', ['endpoint' => $endpoint]);
            return [];
        }

        $url = "https://api.twitter.com/{$this->apiVersion}/{$endpoint}";
        
        $requestParams = array_merge([
            'query' => $query,
            'max_results' => 10,
        ], $params);

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->bearerToken}",
                'User-Agent' => 'BlockchainAnalyticsCrawler/1.0'
            ])->get($url, $requestParams);

            $this->rateLimitManager->recordRequest('twitter', $endpoint);

            if (!$response->successful()) {
                $this->handleApiError($response, $endpoint);
                return [];
            }

            $data = $response->json();
            
            return $this->transformTweets($data);

        } catch (\Exception $e) {
            Log::error('Twitter API request failed', [
                'endpoint' => $endpoint,
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    private function getUserTweets(string $userId, array $params = []): array
    {
        $endpoint = "users/{$userId}/tweets";
        
        if (!$this->rateLimitManager->canMakeRequest('twitter', $endpoint)) {
            Log::warning('Twitter API rate limit exceeded', ['endpoint' => $endpoint]);
            return [];
        }

        $url = "https://api.twitter.com/{$this->apiVersion}/{$endpoint}";

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->bearerToken}",
            ])->get($url, $params);

            $this->rateLimitManager->recordRequest('twitter', $endpoint);

            if (!$response->successful()) {
                $this->handleApiError($response, $endpoint);
                return [];
            }

            $data = $response->json();
            
            return $this->transformTweets($data);

        } catch (\Exception $e) {
            Log::error('Twitter user tweets request failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    private function getUserId(string $username): ?string
    {
        $cacheKey = "twitter_user_id_{$username}";
        
        return Cache::remember($cacheKey, 3600, function () use ($username) {
            $endpoint = 'users/by/username/' . $username;
            $url = "https://api.twitter.com/{$this->apiVersion}/{$endpoint}";

            try {
                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$this->bearerToken}",
                ])->get($url);

                if ($response->successful()) {
                    $data = $response->json();
                    return $data['data']['id'] ?? null;
                }
                
                return null;
                
            } catch (\Exception $e) {
                Log::error('Failed to get Twitter user ID', [
                    'username' => $username,
                    'error' => $e->getMessage()
                ]);
                
                return null;
            }
        });
    }

    private function buildSearchQuery(array $keywords): string
    {
        // Escape special characters and build OR query
        $escapedKeywords = array_map(function ($keyword) {
            // Escape Twitter search operators
            return '"' . str_replace(['"', '\\'], ['\"', '\\\\'], $keyword) . '"';
        }, $keywords);

        $query = '(' . implode(' OR ', $escapedKeywords) . ')';
        
        // Add filters to exclude retweets and get original content only
        $query .= ' -is:retweet lang:en';
        
        // Filter out likely spam/low quality content
        $query .= ' has:links OR has:media OR min_replies:1';

        return $query;
    }

    private function transformTweets(array $data): array
    {
        $tweets = $data['data'] ?? [];
        $users = $this->indexUsers($data['includes']['users'] ?? []);
        $media = $this->indexMedia($data['includes']['media'] ?? []);
        
        $transformedTweets = [];

        foreach ($tweets as $tweet) {
            $author = $users[$tweet['author_id']] ?? null;
            $tweetMedia = $this->getTweetMedia($tweet, $media);

            $transformedTweets[] = [
                'external_id' => $tweet['id'],
                'source_url' => "https://twitter.com/" . ($author['username'] ?? 'unknown') . "/status/{$tweet['id']}",
                'author_username' => $author['username'] ?? null,
                'author_display_name' => $author['name'] ?? null,
                'author_id' => $tweet['author_id'],
                'content' => $tweet['text'],
                'media_urls' => $tweetMedia,
                'engagement_count' => $tweet['public_metrics']['like_count'] ?? 0,
                'share_count' => $tweet['public_metrics']['retweet_count'] ?? 0,
                'comment_count' => $tweet['public_metrics']['reply_count'] ?? 0,
                'published_at' => $tweet['created_at'],
                'language' => $tweet['lang'] ?? null,
                'is_verified_author' => $author['verified'] ?? false,
                'author_followers' => $author['public_metrics']['followers_count'] ?? 0,
                'context_annotations' => $tweet['context_annotations'] ?? [],
            ];
        }

        return $transformedTweets;
    }

    private function indexUsers(array $users): array
    {
        $indexed = [];
        foreach ($users as $user) {
            $indexed[$user['id']] = $user;
        }
        return $indexed;
    }

    private function indexMedia(array $media): array
    {
        $indexed = [];
        foreach ($media as $item) {
            $indexed[$item['media_key']] = $item;
        }
        return $indexed;
    }

    private function getTweetMedia(array $tweet, array $mediaIndex): array
    {
        $mediaUrls = [];
        
        if (isset($tweet['attachments']['media_keys'])) {
            foreach ($tweet['attachments']['media_keys'] as $mediaKey) {
                $mediaItem = $mediaIndex[$mediaKey] ?? null;
                if ($mediaItem) {
                    $mediaUrls[] = $mediaItem['url'] ?? $mediaItem['preview_image_url'] ?? null;
                }
            }
        }
        
        return array_filter($mediaUrls);
    }

    private function handleApiError($response, string $endpoint): void
    {
        $statusCode = $response->status();
        $body = $response->json();
        $error = $body['detail'] ?? $body['title'] ?? 'Unknown error';

        Log::error('Twitter API error', [
            'endpoint' => $endpoint,
            'status_code' => $statusCode,
            'error' => $error,
            'response_body' => $body
        ]);

        if ($statusCode === 429) {
            // Rate limit exceeded
            $this->rateLimitManager->handleRateLimit('twitter', $endpoint, $response->headers());
        }

        throw new \Exception("Twitter API error ({$statusCode}): {$error}");
    }

    public function testConnection(): bool
    {
        try {
            $url = "https://api.twitter.com/{$this->apiVersion}/tweets/search/recent";
            
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->bearerToken}",
            ])->get($url, [
                'query' => 'test',
                'max_results' => 10
            ]);

            return $response->successful();
            
        } catch (\Exception $e) {
            Log::error('Twitter connection test failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function getRateLimitStatus(): array
    {
        return $this->rateLimitManager->getStatus('twitter');
    }

    public function getPlatformName(): string
    {
        return 'twitter';
    }

    /**
     * Get trending topics from Twitter
     */
    public function getTrendingTopics(int $woeid = 1): array // 1 = worldwide
    {
        $endpoint = 'trends/place';
        $url = "https://api.twitter.com/1.1/{$endpoint}.json";

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->bearerToken}",
            ])->get($url, ['id' => $woeid]);

            if ($response->successful()) {
                $data = $response->json();
                return $data[0]['trends'] ?? [];
            }
            
            return [];
            
        } catch (\Exception $e) {
            Log::error('Failed to get Twitter trending topics', [
                'woeid' => $woeid,
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }

    /**
     * Get tweet by ID with full details
     */
    public function getTweet(string $tweetId): ?array
    {
        $endpoint = "tweets/{$tweetId}";
        $url = "https://api.twitter.com/{$this->apiVersion}/{$endpoint}";

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->bearerToken}",
            ])->get($url, [
                'expansions' => 'author_id,attachments.media_keys',
                'tweet.fields' => 'created_at,public_metrics,context_annotations,lang',
                'user.fields' => 'username,name,verified,public_metrics',
                'media.fields' => 'url,preview_image_url,type'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $transformed = $this->transformTweets($data);
                return $transformed[0] ?? null;
            }
            
            return null;
            
        } catch (\Exception $e) {
            Log::error('Failed to get specific tweet', [
                'tweet_id' => $tweetId,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }
}