<?php

declare(strict_types=1);

namespace App\Services\CrawlerMicroService;

use App\Models\SocialMediaPost;
use App\Models\CrawlerJobStatus;
use App\Services\SocialCrawler\TwitterCrawler;
use App\Services\SocialCrawler\RedditCrawler;
use App\Services\SocialCrawler\TelegramCrawler;
use App\Services\SocialCrawler\KeywordMatcher;
use App\Services\SocialCrawler\SentimentAnalyzer;
use App\Services\Concerns\UsesProxy;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

final class CrawlerOrchestrator
{
    use UsesProxy;

    private KeywordMatcher $keywordMatcher;
    private SentimentAnalyzer $sentimentAnalyzer;
    private array $config;

    public function __construct(
        KeywordMatcher $keywordMatcher,
        SentimentAnalyzer $sentimentAnalyzer
    ) {
        $this->keywordMatcher = $keywordMatcher;
        $this->sentimentAnalyzer = $sentimentAnalyzer;
        $this->config = config('crawler_microservice', []);
    }

    /**
     * Execute crawling job with keyword rules.
     */
    public function executeCrawlJob(array $jobConfig): array
    {
        $jobId = $jobConfig['job_id'] ?? uniqid('crawl_');
        $platforms = $jobConfig['platforms'] ?? ['twitter', 'reddit', 'telegram'];
        $keywordRules = $jobConfig['keyword_rules'] ?? [];
        $maxPosts = $jobConfig['max_posts'] ?? 100;
        $priority = $jobConfig['priority'] ?? 'normal';

        Log::info('Starting crawler micro-service job', [
            'job_id' => $jobId,
            'platforms' => $platforms,
            'keyword_rules' => count($keywordRules),
            'max_posts' => $maxPosts,
            'priority' => $priority
        ]);

        // Create job status record
        $jobStatus = $this->createJobStatus($jobId, $jobConfig);

        $results = [
            'job_id' => $jobId,
            'started_at' => now()->toISOString(),
            'platforms' => [],
            'total_posts' => 0,
            'total_matches' => 0,
            'errors' => []
        ];

        try {
            foreach ($platforms as $platform) {
                if ($this->isPlatformEnabled($platform)) {
                    $platformResult = $this->crawlPlatform($platform, $keywordRules, $maxPosts);
                    $results['platforms'][$platform] = $platformResult;
                    $results['total_posts'] += $platformResult['posts_found'];
                    $results['total_matches'] += $platformResult['keyword_matches'];
                } else {
                    $results['platforms'][$platform] = [
                        'status' => 'disabled',
                        'posts_found' => 0,
                        'keyword_matches' => 0
                    ];
                }
            }

            $this->updateJobStatus($jobStatus, 'completed', $results);
            
            Log::info('Crawler job completed successfully', [
                'job_id' => $jobId,
                'total_posts' => $results['total_posts'],
                'total_matches' => $results['total_matches']
            ]);

        } catch (\Exception $e) {
            $results['errors'][] = $e->getMessage();
            $this->updateJobStatus($jobStatus, 'failed', $results, $e->getMessage());
            
            Log::error('Crawler job failed', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        $results['completed_at'] = now()->toISOString();
        return $results;
    }

    /**
     * Crawl specific platform with keyword rules.
     */
    private function crawlPlatform(string $platform, array $keywordRules, int $maxPosts): array
    {
        $startTime = microtime(true);
        
        try {
            $crawler = $this->getCrawlerForPlatform($platform);
            $posts = [];
            $keywordMatches = 0;

            // Extract keywords from rules
            $keywords = $this->extractKeywords($keywordRules);
            
            if (empty($keywords)) {
                throw new \Exception("No keywords provided for {$platform}");
            }

            Log::debug("Crawling {$platform}", [
                'keywords' => $keywords,
                'max_posts' => $maxPosts
            ]);

            // Perform crawling based on platform
            switch ($platform) {
                case 'twitter':
                    $posts = $this->crawlTwitter($crawler, $keywords, $maxPosts);
                    break;
                case 'reddit':
                    $posts = $this->crawlReddit($crawler, $keywords, $maxPosts);
                    break;
                case 'telegram':
                    $posts = $this->crawlTelegram($crawler, $keywords, $maxPosts);
                    break;
                default:
                    throw new \Exception("Unsupported platform: {$platform}");
            }

            // Apply keyword rules and sentiment analysis
            foreach ($posts as &$post) {
                $matches = $this->keywordMatcher->findMatches($post['content'], $keywordRules);
                $post['keyword_matches'] = $matches;
                
                if (!empty($matches)) {
                    $keywordMatches++;
                    
                    // Perform sentiment analysis
                    $sentiment = $this->sentimentAnalyzer->analyze($post['content']);
                    $post['sentiment'] = $sentiment;
                    
                    // Store in database
                    $this->storeSocialMediaPost($post, $platform);
                }
            }

            $duration = round((microtime(true) - $startTime) * 1000);

            return [
                'status' => 'success',
                'posts_found' => count($posts),
                'keyword_matches' => $keywordMatches,
                'processing_time_ms' => $duration,
                'rate_limit_remaining' => $crawler->getRateLimitRemaining ?? null
            ];

        } catch (\Exception $e) {
            Log::error("Failed to crawl {$platform}", [
                'error' => $e->getMessage(),
                'keywords' => $keywords ?? []
            ]);

            return [
                'status' => 'error',
                'posts_found' => 0,
                'keyword_matches' => 0,
                'error' => $e->getMessage(),
                'processing_time_ms' => round((microtime(true) - $startTime) * 1000)
            ];
        }
    }

    /**
     * Get crawler instance for platform.
     */
    private function getCrawlerForPlatform(string $platform): object
    {
        return match ($platform) {
            'twitter' => new TwitterCrawler($this->keywordMatcher, $this->sentimentAnalyzer),
            'reddit' => new RedditCrawler($this->keywordMatcher, $this->sentimentAnalyzer),
            'telegram' => new TelegramCrawler($this->keywordMatcher, $this->sentimentAnalyzer),
            default => throw new \Exception("Unsupported platform: {$platform}")
        };
    }

    /**
     * Crawl Twitter with keywords.
     */
    private function crawlTwitter(TwitterCrawler $crawler, array $keywords, int $maxPosts): array
    {
        $posts = [];
        $postsPerKeyword = max(1, intval($maxPosts / count($keywords)));
        
        foreach ($keywords as $keyword) {
            try {
                $keywordPosts = $crawler->searchByKeyword($keyword, $postsPerKeyword);
                $posts = array_merge($posts, $keywordPosts);
                
                if (count($posts) >= $maxPosts) {
                    break;
                }
                
                // Rate limiting
                $this->respectRateLimit('twitter');
                
            } catch (\Exception $e) {
                Log::warning("Twitter search failed for keyword: {$keyword}", [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return array_slice($posts, 0, $maxPosts);
    }

    /**
     * Crawl Reddit with keywords.
     */
    private function crawlReddit(RedditCrawler $crawler, array $keywords, int $maxPosts): array
    {
        $posts = [];
        $subreddits = $this->config['reddit']['subreddits'] ?? ['cryptocurrency', 'defi', 'ethereum'];
        
        foreach ($subreddits as $subreddit) {
            foreach ($keywords as $keyword) {
                try {
                    $keywordPosts = $crawler->searchInSubreddit($keyword, $subreddit, 25);
                    $posts = array_merge($posts, $keywordPosts);
                    
                    if (count($posts) >= $maxPosts) {
                        break 2;
                    }
                    
                    $this->respectRateLimit('reddit');
                    
                } catch (\Exception $e) {
                    Log::warning("Reddit search failed", [
                        'subreddit' => $subreddit,
                        'keyword' => $keyword,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        return array_slice($posts, 0, $maxPosts);
    }

    /**
     * Crawl Telegram with keywords.
     */
    private function crawlTelegram(TelegramCrawler $crawler, array $keywords, int $maxPosts): array
    {
        $posts = [];
        $channels = $this->config['telegram']['channels'] ?? [];
        
        if (empty($channels)) {
            throw new \Exception('No Telegram channels configured');
        }

        foreach ($channels as $channel) {
            try {
                $channelPosts = $crawler->crawlChannel($channel, $maxPosts);
                
                // Filter posts by keywords
                foreach ($channelPosts as $post) {
                    $content = $post['content'] ?? '';
                    foreach ($keywords as $keyword) {
                        if (stripos($content, $keyword) !== false) {
                            $posts[] = $post;
                            break;
                        }
                    }
                    
                    if (count($posts) >= $maxPosts) {
                        break 2;
                    }
                }
                
                $this->respectRateLimit('telegram');
                
            } catch (\Exception $e) {
                Log::warning("Telegram crawl failed for channel: {$channel}", [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return array_slice($posts, 0, $maxPosts);
    }

    /**
     * Extract keywords from keyword rules.
     */
    private function extractKeywords(array $keywordRules): array
    {
        $keywords = [];
        
        foreach ($keywordRules as $rule) {
            if (is_string($rule)) {
                $keywords[] = $rule;
            } elseif (is_array($rule) && isset($rule['keyword'])) {
                $keywords[] = $rule['keyword'];
            } elseif (is_array($rule) && isset($rule['keywords'])) {
                $keywords = array_merge($keywords, $rule['keywords']);
            }
        }

        return array_unique(array_filter($keywords));
    }

    /**
     * Store social media post in database.
     */
    private function storeSocialMediaPost(array $postData, string $platform): void
    {
        try {
            SocialMediaPost::create([
                'platform' => $platform,
                'post_id' => $postData['id'] ?? uniqid(),
                'author' => $postData['author'] ?? 'unknown',
                'content' => $postData['content'] ?? '',
                'url' => $postData['url'] ?? null,
                'posted_at' => isset($postData['created_at']) 
                    ? Carbon::parse($postData['created_at']) 
                    : now(),
                'metrics' => json_encode($postData['metrics'] ?? []),
                'sentiment_score' => $postData['sentiment']['score'] ?? null,
                'sentiment_label' => $postData['sentiment']['label'] ?? null,
                'keyword_matches' => json_encode($postData['keyword_matches'] ?? []),
                'raw_data' => json_encode($postData),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to store social media post', [
                'platform' => $platform,
                'post_id' => $postData['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Create job status record.
     */
    private function createJobStatus(string $jobId, array $config): CrawlerJobStatus
    {
        return CrawlerJobStatus::create([
            'job_id' => $jobId,
            'status' => 'running',
            'config' => json_encode($config),
            'started_at' => now(),
            'progress' => 0
        ]);
    }

    /**
     * Update job status.
     */
    private function updateJobStatus(
        CrawlerJobStatus $jobStatus, 
        string $status, 
        array $results, 
        ?string $error = null
    ): void {
        $jobStatus->update([
            'status' => $status,
            'completed_at' => now(),
            'results' => json_encode($results),
            'error_message' => $error,
            'progress' => 100
        ]);
    }

    /**
     * Check if platform is enabled.
     */
    private function isPlatformEnabled(string $platform): bool
    {
        return $this->config['platforms'][$platform]['enabled'] ?? true;
    }

    /**
     * Respect platform rate limits.
     */
    private function respectRateLimit(string $platform): void
    {
        $delays = [
            'twitter' => 1000, // 1 second
            'reddit' => 2000,  // 2 seconds
            'telegram' => 500  // 0.5 seconds
        ];

        $delay = $delays[$platform] ?? 1000;
        usleep($delay * 1000); // Convert to microseconds
    }

    /**
     * Get job status.
     */
    public function getJobStatus(string $jobId): ?array
    {
        $job = CrawlerJobStatus::where('job_id', $jobId)->first();
        
        if (!$job) {
            return null;
        }

        return [
            'job_id' => $job->job_id,
            'status' => $job->status,
            'progress' => $job->progress,
            'started_at' => $job->started_at?->toISOString(),
            'completed_at' => $job->completed_at?->toISOString(),
            'results' => $job->results ? json_decode($job->results, true) : null,
            'error_message' => $job->error_message
        ];
    }

    /**
     * Get platform statistics.
     */
    public function getPlatformStats(): array
    {
        $stats = [];
        
        $platforms = ['twitter', 'reddit', 'telegram'];
        
        foreach ($platforms as $platform) {
            $stats[$platform] = [
                'enabled' => $this->isPlatformEnabled($platform),
                'total_posts' => SocialMediaPost::where('platform', $platform)->count(),
                'posts_today' => SocialMediaPost::where('platform', $platform)
                    ->whereDate('created_at', today())
                    ->count(),
                'last_crawl' => SocialMediaPost::where('platform', $platform)
                    ->latest('created_at')
                    ->value('created_at')?->toISOString()
            ];
        }

        return $stats;
    }
}