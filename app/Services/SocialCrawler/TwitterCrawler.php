<?php

namespace App\Services\SocialCrawler;

use App\Models\SocialMediaPost;
use App\Models\KeywordMatch;
use App\Services\Concerns\UsesProxy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TwitterCrawler
{
    use UsesProxy;
    
    protected string $bearerToken;
    protected KeywordMatcher $keywordMatcher;
    protected SentimentAnalyzer $sentimentAnalyzer;
    protected array $rateLimits;

    public function __construct(KeywordMatcher $keywordMatcher, SentimentAnalyzer $sentimentAnalyzer)
    {
        $this->bearerToken = config('social_crawler.apis.twitter.bearer_token');
        $this->keywordMatcher = $keywordMatcher;
        $this->sentimentAnalyzer = $sentimentAnalyzer;
        $this->rateLimits = config('social_crawler.apis.twitter.rate_limit', 300);
    }

    public function crawlByKeywords(array $keywords = null): array
    {
        if (!$this->bearerToken) {
            throw new \Exception('Twitter bearer token not configured');
        }

        $keywords = $keywords ?? $this->keywordMatcher->getHighPriorityKeywords();
        $results = [];

        foreach ($keywords as $keyword) {
            if (!$this->canMakeRequest()) {
                Log::warning('Twitter rate limit reached, skipping remaining keywords');
                break;
            }

            try {
                $tweets = $this->searchTweets($keyword);
                $processed = $this->processTweets($tweets, $keyword);
                $results[$keyword] = $processed;
                
                $this->updateRateLimit();
                
                // Sleep to avoid rate limiting
                usleep(100000); // 100ms delay
                
            } catch (\Exception $e) {
                Log::error('Twitter crawl error for keyword: ' . $keyword, [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    public function crawlHashtags(array $hashtags = null): array
    {
        $hashtags = $hashtags ?? config('social_crawler.channels.twitter.hashtags', []);
        $results = [];

        foreach ($hashtags as $hashtag) {
            if (!$this->canMakeRequest()) {
                break;
            }

            try {
                $tweets = $this->searchTweets($hashtag);
                $processed = $this->processTweets($tweets, $hashtag);
                $results[$hashtag] = $processed;
                
                $this->updateRateLimit();
                usleep(100000);
                
            } catch (\Exception $e) {
                Log::error('Twitter hashtag crawl error: ' . $hashtag, [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    public function crawlUserTimelines(array $usernames = null): array
    {
        $usernames = $usernames ?? config('social_crawler.channels.twitter.users', []);
        $results = [];

        foreach ($usernames as $username) {
            if (!$this->canMakeRequest()) {
                break;
            }

            try {
                $tweets = $this->getUserTimeline($username);
                $processed = $this->processTweets($tweets, "user:$username");
                $results[$username] = $processed;
                
                $this->updateRateLimit();
                usleep(100000);
                
            } catch (\Exception $e) {
                Log::error('Twitter user timeline crawl error: ' . $username, [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    protected function searchTweets(string $query, int $maxResults = 100): array
    {
        $response = $this->getHttpClient()->withHeaders([
            'Authorization' => 'Bearer ' . $this->bearerToken,
        ])->get('https://api.twitter.com/2/tweets/search/recent', [
            'query' => $query . ' -is:retweet lang:en',
            'max_results' => min($maxResults, 100),
            'tweet.fields' => 'created_at,author_id,public_metrics,context_annotations,entities',
            'user.fields' => 'username,name,verified,public_metrics',
            'expansions' => 'author_id',
        ]);

        if (!$response->successful()) {
            throw new \Exception('Twitter API request failed: ' . $response->body());
        }

        return $response->json();
    }

    protected function getUserTimeline(string $username, int $maxResults = 50): array
    {
        // First get user ID
        $userResponse = $this->getHttpClient()->withHeaders([
            'Authorization' => 'Bearer ' . $this->bearerToken,
        ])->get("https://api.twitter.com/2/users/by/username/{$username}");

        if (!$userResponse->successful()) {
            throw new \Exception("Failed to get user ID for {$username}");
        }

        $userId = $userResponse->json()['data']['id'];

        // Get user tweets
        $response = $this->getHttpClient()->withHeaders([
            'Authorization' => 'Bearer ' . $this->bearerToken,
        ])->get("https://api.twitter.com/2/users/{$userId}/tweets", [
            'max_results' => min($maxResults, 100),
            'tweet.fields' => 'created_at,author_id,public_metrics,context_annotations,entities',
            'exclude' => 'retweets,replies',
        ]);

        if (!$response->successful()) {
            throw new \Exception('Twitter user timeline request failed: ' . $response->body());
        }

        return $response->json();
    }

    protected function processTweets(array $apiResponse, string $searchTerm): array
    {
        if (!isset($apiResponse['data'])) {
            return [];
        }

        $tweets = $apiResponse['data'];
        $users = $apiResponse['includes']['users'] ?? [];
        $userMap = collect($users)->keyBy('id')->toArray();
        
        $processed = [];

        foreach ($tweets as $tweet) {
            try {
                $user = $userMap[$tweet['author_id']] ?? null;
                $keywordMatches = $this->keywordMatcher->matchKeywords($tweet['text']);
                
                if (empty($keywordMatches)) {
                    continue; // Skip if no keywords match
                }

                $sentimentData = $this->analyzeSentiment($tweet['text'], $keywordMatches);
                
                $post = $this->storeSocialMediaPost([
                    'platform' => 'twitter',
                    'platform_id' => $tweet['id'],
                    'author_username' => $user['username'] ?? null,
                    'author_id' => $tweet['author_id'],
                    'content' => $tweet['text'],
                    'metadata' => [
                        'retweet_count' => $tweet['public_metrics']['retweet_count'] ?? 0,
                        'like_count' => $tweet['public_metrics']['like_count'] ?? 0,
                        'reply_count' => $tweet['public_metrics']['reply_count'] ?? 0,
                        'quote_count' => $tweet['public_metrics']['quote_count'] ?? 0,
                        'hashtags' => $this->extractHashtags($tweet),
                        'mentions' => $this->extractMentions($tweet),
                        'search_term' => $searchTerm,
                    ],
                    'url' => "https://twitter.com/{$user['username']}/status/{$tweet['id']}",
                    'published_at' => $tweet['created_at'],
                    'engagement_score' => $this->calculateEngagementScore($tweet['public_metrics'] ?? []),
                    'sentiment_score' => $sentimentData['score'],
                    'sentiment_label' => $sentimentData['label'],
                    'matched_keywords' => array_column($keywordMatches, 'keyword'),
                ]);

                $this->storeKeywordMatches($post, $keywordMatches);
                $processed[] = $post;

                // Check for alerts
                if ($this->keywordMatcher->shouldTriggerAlert($keywordMatches, 'twitter')) {
                    $this->triggerAlert($post, $keywordMatches);
                }

            } catch (\Exception $e) {
                Log::error('Error processing Twitter post', [
                    'tweet_id' => $tweet['id'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $processed;
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

    protected function calculateEngagementScore(array $metrics): int
    {
        $likes = $metrics['like_count'] ?? 0;
        $retweets = $metrics['retweet_count'] ?? 0;
        $replies = $metrics['reply_count'] ?? 0;
        $quotes = $metrics['quote_count'] ?? 0;

        // Weighted engagement score
        return ($likes * 1) + ($retweets * 3) + ($replies * 2) + ($quotes * 2);
    }

    protected function extractHashtags(array $tweet): array
    {
        $hashtags = [];
        if (isset($tweet['entities']['hashtags'])) {
            foreach ($tweet['entities']['hashtags'] as $hashtag) {
                $hashtags[] = '#' . $hashtag['tag'];
            }
        }
        return $hashtags;
    }

    protected function extractMentions(array $tweet): array
    {
        $mentions = [];
        if (isset($tweet['entities']['mentions'])) {
            foreach ($tweet['entities']['mentions'] as $mention) {
                $mentions[] = '@' . $mention['username'];
            }
        }
        return $mentions;
    }

    protected function canMakeRequest(): bool
    {
        $key = 'twitter_rate_limit_' . date('Y-m-d-H');
        $currentCount = Cache::get($key, 0);
        return $currentCount < $this->rateLimits;
    }

    protected function updateRateLimit(): void
    {
        $key = 'twitter_rate_limit_' . date('Y-m-d-H');
        Cache::increment($key, 1);
        Cache::put($key, Cache::get($key), 3600); // 1 hour TTL
    }

    protected function triggerAlert(SocialMediaPost $post, array $matches): void
    {
        // Implementation would integrate with your alert system
        Log::alert('Critical keyword alert triggered', [
            'platform' => 'twitter',
            'post_id' => $post->id,
            'matches' => $matches,
            'content' => $post->content,
        ]);
    }
}