<?php

namespace App\Services\SocialCrawler;

use App\Models\SocialMediaPost;
use App\Models\KeywordMatch;
use App\Services\Concerns\UsesProxy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class RedditCrawler
{
    use UsesProxy;
    
    protected string $clientId;
    protected string $clientSecret;
    protected string $username;
    protected string $password;
    protected string $userAgent;
    protected ?string $accessToken = null;
    protected KeywordMatcher $keywordMatcher;
    protected SentimentAnalyzer $sentimentAnalyzer;
    protected int $rateLimit;

    public function __construct(KeywordMatcher $keywordMatcher, SentimentAnalyzer $sentimentAnalyzer)
    {
        $config = config('social_crawler.apis.reddit');
        $this->clientId = $config['client_id'];
        $this->clientSecret = $config['client_secret'];
        $this->username = $config['username'];
        $this->password = $config['password'];
        $this->userAgent = $config['user_agent'];
        $this->rateLimit = $config['rate_limit'] ?? 60;
        $this->keywordMatcher = $keywordMatcher;
        $this->sentimentAnalyzer = $sentimentAnalyzer;
    }

    public function crawlSubreddits(array $subreddits = null): array
    {
        if (!$this->authenticate()) {
            throw new \Exception('Reddit authentication failed');
        }

        $subreddits = $subreddits ?? config('social_crawler.channels.reddit.subreddits', []);
        $results = [];

        foreach ($subreddits as $subreddit) {
            if (!$this->canMakeRequest()) {
                Log::warning('Reddit rate limit reached, skipping remaining subreddits');
                break;
            }

            try {
                $posts = $this->getSubredditPosts($subreddit);
                $processed = $this->processPosts($posts, "r/$subreddit");
                $results[$subreddit] = $processed;
                
                $this->updateRateLimit();
                sleep(1); // Reddit requires 1 second between requests
                
            } catch (\Exception $e) {
                Log::error('Reddit crawl error for subreddit: ' . $subreddit, [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    public function searchPosts(string $query, array $subreddits = null): array
    {
        if (!$this->authenticate()) {
            throw new \Exception('Reddit authentication failed');
        }

        $subreddits = $subreddits ?? config('social_crawler.channels.reddit.subreddits', []);
        $results = [];

        foreach ($subreddits as $subreddit) {
            if (!$this->canMakeRequest()) {
                break;
            }

            try {
                $posts = $this->searchInSubreddit($query, $subreddit);
                $processed = $this->processPosts($posts, "search:$query in r/$subreddit");
                $results["$subreddit:$query"] = $processed;
                
                $this->updateRateLimit();
                sleep(1);
                
            } catch (\Exception $e) {
                Log::error('Reddit search error', [
                    'query' => $query,
                    'subreddit' => $subreddit,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    public function crawlByKeywords(array $keywords = null): array
    {
        $keywords = $keywords ?? $this->keywordMatcher->getHighPriorityKeywords();
        $results = [];

        foreach ($keywords as $keyword) {
            $searchResults = $this->searchPosts($keyword);
            $results[$keyword] = $searchResults;
        }

        return $results;
    }

    protected function authenticate(): bool
    {
        if ($this->accessToken && $this->isTokenValid()) {
            return true;
        }

        try {
            $response = $this->getHttpClient()->withBasicAuth($this->clientId, $this->clientSecret)
                ->withHeaders(['User-Agent' => $this->userAgent])
                ->asForm()
                ->post('https://www.reddit.com/api/v1/access_token', [
                    'grant_type' => 'password',
                    'username' => $this->username,
                    'password' => $this->password,
                ]);

            if (!$response->successful()) {
                throw new \Exception('Reddit authentication failed: ' . $response->body());
            }

            $data = $response->json();
            $this->accessToken = $data['access_token'];
            
            // Cache token for 50 minutes (Reddit tokens expire in 1 hour)
            Cache::put('reddit_access_token', $this->accessToken, 3000);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Reddit authentication error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    protected function isTokenValid(): bool
    {
        return Cache::has('reddit_access_token');
    }

    protected function getSubredditPosts(string $subreddit, string $sort = 'hot', int $limit = 25): array
    {
        $sortType = config('social_crawler.channels.reddit.sort_by.0', 'hot');
        $timeFilter = config('social_crawler.channels.reddit.time_filter', 'day');

        $url = "https://oauth.reddit.com/r/{$subreddit}/{$sortType}";
        
        $params = [
            'limit' => min($limit, 100),
            'raw_json' => 1,
        ];

        if (in_array($sortType, ['top', 'controversial'])) {
            $params['t'] = $timeFilter;
        }

        $response = $this->getHttpClient()->withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken,
            'User-Agent' => $this->userAgent,
        ])->get($url, $params);

        if (!$response->successful()) {
            throw new \Exception("Reddit API request failed for r/{$subreddit}: " . $response->body());
        }

        return $response->json();
    }

    protected function searchInSubreddit(string $query, string $subreddit, int $limit = 25): array
    {
        $response = $this->getHttpClient()->withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken,
            'User-Agent' => $this->userAgent,
        ])->get('https://oauth.reddit.com/r/' . $subreddit . '/search', [
            'q' => $query,
            'limit' => min($limit, 100),
            'sort' => 'new',
            'restrict_sr' => true,
            'raw_json' => 1,
        ]);

        if (!$response->successful()) {
            throw new \Exception("Reddit search failed: " . $response->body());
        }

        return $response->json();
    }

    protected function processPosts(array $apiResponse, string $source): array
    {
        if (!isset($apiResponse['data']['children'])) {
            return [];
        }

        $posts = $apiResponse['data']['children'];
        $processed = [];

        foreach ($posts as $postWrapper) {
            try {
                $post = $postWrapper['data'];
                
                // Skip if removed or deleted
                if ($post['removed_by_category'] ?? false || $post['author'] === '[deleted]') {
                    continue;
                }

                $content = $this->extractContent($post);
                $keywordMatches = $this->keywordMatcher->matchKeywords($content);
                
                if (empty($keywordMatches)) {
                    continue; // Skip if no keywords match
                }

                $sentimentData = $this->analyzeSentiment($content, $keywordMatches);
                
                $socialPost = $this->storeSocialMediaPost([
                    'platform' => 'reddit',
                    'platform_id' => $post['id'],
                    'author_username' => $post['author'],
                    'author_id' => $post['author_fullname'] ?? null,
                    'content' => $content,
                    'metadata' => [
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
                    ],
                    'url' => 'https://reddit.com' . $post['permalink'],
                    'published_at' => date('Y-m-d H:i:s', $post['created_utc']),
                    'engagement_score' => $this->calculateEngagementScore($post),
                    'sentiment_score' => $sentimentData['score'],
                    'sentiment_label' => $sentimentData['label'],
                    'matched_keywords' => array_column($keywordMatches, 'keyword'),
                ]);

                $this->storeKeywordMatches($socialPost, $keywordMatches);
                $processed[] = $socialPost;

                // Check for alerts
                if ($this->keywordMatcher->shouldTriggerAlert($keywordMatches, 'reddit')) {
                    $this->triggerAlert($socialPost, $keywordMatches);
                }

            } catch (\Exception $e) {
                Log::error('Error processing Reddit post', [
                    'post_id' => $post['id'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $processed;
    }

    protected function extractContent(array $post): string
    {
        $content = $post['title'];
        
        if (!empty($post['selftext'])) {
            $content .= "\n\n" . $post['selftext'];
        }
        
        return trim($content);
    }

    protected function storeSocialMediaPost(array $data): SocialMediaPost
    {
        return SocialMediaPost::updateOrCreate(
            ['platform_id' => $data['platform_id']],
            $data
        );
    }

    protected function storeKeywordMatches(SocialMediaPost $post, array $matches): void
    {
        foreach ($matches as $match) {
            KeywordMatch::create([
                'social_media_post_id' => $post->id,
                'keyword' => $match['keyword'],
                'keyword_category' => $match['category'],
                'match_count' => $match['match_count'],
                'priority' => $match['priority'],
            ]);
        }
    }

    protected function analyzeSentiment(string $text, array $keywordMatches): array
    {
        $needsAnalysis = collect($keywordMatches)->contains('sentiment_analysis', true);
        
        if (!$needsAnalysis) {
            return ['score' => 0, 'label' => 'neutral'];
        }

        return $this->sentimentAnalyzer->analyze($text);
    }

    protected function calculateEngagementScore(array $post): int
    {
        $score = $post['score'] ?? 0;
        $comments = $post['num_comments'] ?? 0;
        $awards = $post['total_awards_received'] ?? 0;
        $gilded = $post['gilded'] ?? 0;

        // Weighted engagement score for Reddit
        return max(0, $score) + ($comments * 2) + ($awards * 5) + ($gilded * 10);
    }

    protected function canMakeRequest(): bool
    {
        $key = 'reddit_rate_limit_' . date('Y-m-d-H-i');
        $currentCount = Cache::get($key, 0);
        return $currentCount < $this->rateLimit;
    }

    protected function updateRateLimit(): void
    {
        $key = 'reddit_rate_limit_' . date('Y-m-d-H-i');
        Cache::increment($key, 1);
        Cache::put($key, Cache::get($key), 60); // 1 minute TTL
    }

    protected function triggerAlert(SocialMediaPost $post, array $matches): void
    {
        Log::alert('Critical keyword alert triggered', [
            'platform' => 'reddit',
            'post_id' => $post->id,
            'subreddit' => $post->metadata['subreddit'] ?? null,
            'matches' => $matches,
            'content' => substr($post->content, 0, 200) . '...',
        ]);
    }
}