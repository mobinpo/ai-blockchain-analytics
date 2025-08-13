<?php

namespace App\Services;

use App\Models\CrawlerRule;
use App\Models\SocialMediaPost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SocialMediaCrawlerService
{
    protected array $platforms;

    public function __construct()
    {
        $this->platforms = [
            'twitter' => new TwitterCrawler(),
            'reddit' => new RedditCrawler(),
            'telegram' => new TelegramCrawler(),
        ];
    }

    public function crawlAll(): array
    {
        $results = [];
        $rules = CrawlerRule::active()->orderBy('priority')->get();

        foreach ($rules as $rule) {
            foreach ($rule->platforms as $platform) {
                if (isset($this->platforms[$platform])) {
                    try {
                        $posts = $this->crawlPlatform($platform, $rule);
                        $results[$platform][] = [
                            'rule' => $rule->name,
                            'posts_found' => count($posts),
                            'posts' => $posts
                        ];
                    } catch (\Exception $e) {
                        Log::error("Crawler error for {$platform}: " . $e->getMessage());
                        $results[$platform][] = [
                            'rule' => $rule->name,
                            'error' => $e->getMessage()
                        ];
                    }
                }
            }
        }

        return $results;
    }

    public function crawlPlatform(string $platform, CrawlerRule $rule): array
    {
        $crawler = $this->platforms[$platform];
        $posts = $crawler->search($rule);
        
        $savedPosts = [];
        foreach ($posts as $postData) {
            if ($this->shouldSavePost($postData, $rule)) {
                $post = $this->savePost($postData, $rule);
                if ($post) {
                    $savedPosts[] = $post;
                }
            }
        }

        return $savedPosts;
    }

    protected function shouldSavePost(array $postData, CrawlerRule $rule): bool
    {
        // Check if post already exists
        $exists = SocialMediaPost::where('platform_id', $postData['id'])
            ->where('platform', $postData['platform'])
            ->exists();

        if ($exists) {
            return false;
        }

        // Check engagement threshold
        if ($postData['engagement'] < $rule->engagement_threshold) {
            return false;
        }

        // Check sentiment threshold if set
        if ($rule->sentiment_threshold !== null && isset($postData['sentiment_score'])) {
            if ($postData['sentiment_score'] < $rule->sentiment_threshold) {
                return false;
            }
        }

        return true;
    }

    protected function savePost(array $postData, CrawlerRule $rule): ?SocialMediaPost
    {
        try {
            $matchedKeywords = $rule->getMatchedKeywords($postData['content']);
            
            return SocialMediaPost::create([
                'platform' => $postData['platform'],
                'platform_id' => $postData['id'],
                'author' => $postData['author'],
                'content' => $postData['content'],
                'matched_keywords' => $matchedKeywords,
                'metadata' => $postData['metadata'] ?? [],
                'sentiment_score' => $postData['sentiment_score'] ?? null,
                'engagement_count' => $postData['engagement'] ?? 0,
                'platform_created_at' => $postData['created_at'],
                'crawled_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error("Error saving post: " . $e->getMessage());
            return null;
        }
    }
}

class TwitterCrawler
{
    protected string $baseUrl = 'https://api.twitter.com/2';
    protected ?string $bearerToken;

    public function __construct()
    {
        $this->bearerToken = config('services.twitter.bearer_token');
    }

    public function search(CrawlerRule $rule): array
    {
        $posts = [];
        
        // Search by keywords
        foreach ($rule->keywords as $keyword) {
            $query = $this->buildSearchQuery($keyword, $rule);
            $response = $this->makeRequest('/tweets/search/recent', [
                'query' => $query,
                'tweet.fields' => 'created_at,public_metrics,context_annotations,author_id',
                'user.fields' => 'username,verified',
                'expansions' => 'author_id',
                'max_results' => 100
            ]);

            if ($response && isset($response['data'])) {
                foreach ($response['data'] as $tweet) {
                    $posts[] = $this->formatPost($tweet, $response['includes']['users'] ?? []);
                }
            }
        }

        // Search specific accounts if configured
        if ($rule->accounts) {
            foreach ($rule->accounts as $username) {
                $userPosts = $this->getUserTweets($username, $rule);
                $posts = array_merge($posts, $userPosts);
            }
        }

        return $posts;
    }

    protected function buildSearchQuery(string $keyword, CrawlerRule $rule): string
    {
        $query = "\"{$keyword}\"";
        
        // Add hashtags if configured
        if ($rule->hashtags) {
            foreach ($rule->hashtags as $hashtag) {
                $query .= " OR {$hashtag}";
            }
        }

        // Add language and other filters
        $query .= ' lang:en -is:retweet';
        
        return $query;
    }

    protected function makeRequest(string $endpoint, array $params = []): ?array
    {
        if (!$this->bearerToken) {
            Log::warning('Twitter bearer token not configured');
            return null;
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->bearerToken}",
        ])->get($this->baseUrl . $endpoint, $params);

        if ($response->successful()) {
            return $response->json();
        }

        Log::error("Twitter API error: " . $response->body());
        return null;
    }

    protected function formatPost(array $tweet, array $users = []): array
    {
        $author = $this->findUser($tweet['author_id'], $users);
        
        return [
            'platform' => 'twitter',
            'id' => $tweet['id'],
            'content' => $tweet['text'],
            'author' => $author['username'] ?? 'unknown',
            'created_at' => $tweet['created_at'],
            'engagement' => ($tweet['public_metrics']['like_count'] ?? 0) + 
                          ($tweet['public_metrics']['retweet_count'] ?? 0),
            'metadata' => [
                'likes' => $tweet['public_metrics']['like_count'] ?? 0,
                'retweets' => $tweet['public_metrics']['retweet_count'] ?? 0,
                'replies' => $tweet['public_metrics']['reply_count'] ?? 0,
                'author_verified' => $author['verified'] ?? false,
                'context_annotations' => $tweet['context_annotations'] ?? [],
            ]
        ];
    }

    protected function findUser(string $userId, array $users): ?array
    {
        foreach ($users as $user) {
            if ($user['id'] === $userId) {
                return $user;
            }
        }
        return null;
    }

    protected function getUserTweets(string $username, CrawlerRule $rule): array
    {
        // Implementation for getting user tweets
        return [];
    }
}

class RedditCrawler
{
    protected string $baseUrl = 'https://www.reddit.com';
    protected string $userAgent;

    public function __construct()
    {
        $this->userAgent = config('app.name', 'AI-Blockchain-Crawler') . '/1.0';
    }

    public function search(CrawlerRule $rule): array
    {
        $posts = [];
        
        foreach ($rule->keywords as $keyword) {
            $subreddits = $rule->filters['subreddits'] ?? ['cryptocurrency', 'bitcoin', 'ethereum', 'defi'];
            
            foreach ($subreddits as $subreddit) {
                $response = $this->makeRequest("/r/{$subreddit}/search.json", [
                    'q' => $keyword,
                    'sort' => 'new',
                    'limit' => 25,
                    't' => 'day' // last day
                ]);

                if ($response && isset($response['data']['children'])) {
                    foreach ($response['data']['children'] as $child) {
                        $post = $child['data'];
                        if ($this->matchesKeywords($post['title'] . ' ' . $post['selftext'], $rule->keywords)) {
                            $posts[] = $this->formatPost($post);
                        }
                    }
                }
            }
        }

        return $posts;
    }

    protected function makeRequest(string $endpoint, array $params = []): ?array
    {
        $response = Http::withHeaders([
            'User-Agent' => $this->userAgent,
        ])->get($this->baseUrl . $endpoint, $params);

        if ($response->successful()) {
            return $response->json();
        }

        Log::error("Reddit API error: " . $response->body());
        return null;
    }

    protected function formatPost(array $post): array
    {
        return [
            'platform' => 'reddit',
            'id' => $post['id'],
            'content' => $post['title'] . "\n\n" . $post['selftext'],
            'author' => $post['author'],
            'created_at' => date('Y-m-d H:i:s', $post['created_utc']),
            'engagement' => $post['score'] + $post['num_comments'],
            'metadata' => [
                'subreddit' => $post['subreddit'],
                'score' => $post['score'],
                'upvote_ratio' => $post['upvote_ratio'],
                'num_comments' => $post['num_comments'],
                'url' => $post['url'],
                'permalink' => "https://reddit.com" . $post['permalink'],
            ]
        ];
    }

    protected function matchesKeywords(string $text, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }
}

class TelegramCrawler
{
    protected ?string $botToken;
    protected string $baseUrl;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token');
        $this->baseUrl = "https://api.telegram.org/bot{$this->botToken}";
    }

    public function search(CrawlerRule $rule): array
    {
        // Note: Telegram Bot API has limited search capabilities
        // This would typically require channel monitoring or webhook setup
        $posts = [];
        
        $channels = $rule->filters['channels'] ?? [];
        foreach ($channels as $channelId) {
            $messages = $this->getChannelMessages($channelId, $rule);
            $posts = array_merge($posts, $messages);
        }

        return $posts;
    }

    protected function getChannelMessages(string $channelId, CrawlerRule $rule): array
    {
        // Implementation would depend on having access to channel messages
        // This is a simplified example
        return [];
    }

    protected function makeRequest(string $method, array $params = []): ?array
    {
        if (!$this->botToken) {
            Log::warning('Telegram bot token not configured');
            return null;
        }

        $response = Http::post($this->baseUrl . "/{$method}", $params);

        if ($response->successful()) {
            return $response->json();
        }

        Log::error("Telegram API error: " . $response->body());
        return null;
    }

    protected function formatPost(array $message): array
    {
        return [
            'platform' => 'telegram',
            'id' => $message['message_id'],
            'content' => $message['text'] ?? '',
            'author' => $message['from']['username'] ?? $message['from']['first_name'] ?? 'unknown',
            'created_at' => date('Y-m-d H:i:s', $message['date']),
            'engagement' => $message['views'] ?? 0,
            'metadata' => [
                'chat_id' => $message['chat']['id'],
                'chat_title' => $message['chat']['title'] ?? null,
                'forward_from' => $message['forward_from'] ?? null,
                'views' => $message['views'] ?? 0,
            ]
        ];
    }
}