<?php

namespace App\Services\SocialCrawler;

use App\Models\SocialMediaPost;
use App\Models\KeywordMatch;
use App\Services\Concerns\UsesProxy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TelegramCrawler
{
    use UsesProxy;
    
    protected string $botToken;
    protected KeywordMatcher $keywordMatcher;
    protected SentimentAnalyzer $sentimentAnalyzer;
    protected int $rateLimit;
    protected array $channels;

    public function __construct(KeywordMatcher $keywordMatcher, SentimentAnalyzer $sentimentAnalyzer)
    {
        $config = config('social_crawler.apis.telegram');
        $this->botToken = $config['bot_token'];
        $this->rateLimit = $config['rate_limit'] ?? 20;
        $this->keywordMatcher = $keywordMatcher;
        $this->sentimentAnalyzer = $sentimentAnalyzer;
        $this->channels = config('social_crawler.channels.telegram.channels', []);
    }

    public function crawlChannels(array $channels = null): array
    {
        if (!$this->botToken) {
            throw new \Exception('Telegram bot token not configured');
        }

        $channels = $channels ?? $this->channels;
        $results = [];

        foreach ($channels as $channel) {
            if (!$this->canMakeRequest()) {
                Log::warning('Telegram rate limit reached, skipping remaining channels');
                break;
            }

            try {
                $messages = $this->getChannelMessages($channel);
                $processed = $this->processMessages($messages, $channel);
                $results[$channel] = $processed;
                
                $this->updateRateLimit();
                sleep(3); // Telegram has strict rate limits
                
            } catch (\Exception $e) {
                Log::error('Telegram crawl error for channel: ' . $channel, [
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
            $channelResults = $this->crawlChannels();
            
            // Filter results by keyword matches
            $filteredResults = [];
            foreach ($channelResults as $channel => $posts) {
                $keywordPosts = array_filter($posts, function($post) use ($keyword) {
                    return stripos($post['content'], $keyword) !== false;
                });
                
                if (!empty($keywordPosts)) {
                    $filteredResults[$channel] = $keywordPosts;
                }
            }
            
            $results[$keyword] = $filteredResults;
        }

        return $results;
    }

    protected function getChannelMessages(string $channel, int $limit = 50): array
    {
        // Remove @ if present
        $channel = ltrim($channel, '@');
        
        // Try to get channel info first
        $chatInfo = $this->getChatInfo($channel);
        if (!$chatInfo) {
            throw new \Exception("Unable to access Telegram channel: {$channel}");
        }

        // Get recent messages using getUpdates (limited approach)
        // Note: For production, you'd want to use MTProto client library
        // This is a simplified implementation using bot API
        $response = $this->getHttpClient()->get("https://api.telegram.org/bot{$this->botToken}/getUpdates", [
            'allowed_updates' => ['channel_post'],
            'limit' => min($limit, 100),
        ]);

        if (!$response->successful()) {
            throw new \Exception('Telegram API request failed: ' . $response->body());
        }

        $data = $response->json();
        
        if (!$data['ok']) {
            throw new \Exception('Telegram API error: ' . ($data['description'] ?? 'Unknown error'));
        }

        // Filter messages from the specific channel
        $channelMessages = array_filter($data['result'], function($update) use ($channel) {
            return isset($update['channel_post']) && 
                   isset($update['channel_post']['chat']['username']) &&
                   strtolower($update['channel_post']['chat']['username']) === strtolower($channel);
        });

        return array_values($channelMessages);
    }

    protected function getChatInfo(string $channel): ?array
    {
        try {
            $response = $this->getHttpClient()->get("https://api.telegram.org/bot{$this->botToken}/getChat", [
                'chat_id' => "@{$channel}"
            ]);

            if ($response->successful() && $response->json()['ok']) {
                return $response->json()['result'];
            }
        } catch (\Exception $e) {
            Log::debug('Could not get Telegram chat info', [
                'channel' => $channel,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    protected function processMessages(array $updates, string $channel): array
    {
        $processed = [];

        foreach ($updates as $update) {
            try {
                if (!isset($update['channel_post'])) {
                    continue;
                }

                $message = $update['channel_post'];
                
                // Skip if no text content
                if (!isset($message['text']) || empty(trim($message['text']))) {
                    continue;
                }

                $content = $message['text'];
                $keywordMatches = $this->keywordMatcher->matchKeywords($content);
                
                // Only process if keywords match (unless configured otherwise)
                $keywordsOnly = config('social_crawler.channels.telegram.keywords_only', true);
                if ($keywordsOnly && empty($keywordMatches)) {
                    continue;
                }

                $sentimentData = $this->analyzeSentiment($content, $keywordMatches);
                
                $post = $this->storeSocialMediaPost([
                    'platform' => 'telegram',
                    'platform_id' => $message['message_id'],
                    'author_username' => $message['chat']['username'] ?? $channel,
                    'author_id' => $message['chat']['id'],
                    'content' => $content,
                    'metadata' => [
                        'channel' => $channel,
                        'chat_type' => $message['chat']['type'],
                        'chat_title' => $message['chat']['title'] ?? null,
                        'forward_from' => $message['forward_from']['username'] ?? null,
                        'forward_date' => $message['forward_date'] ?? null,
                        'reply_to_message' => isset($message['reply_to_message']),
                        'entities' => $message['entities'] ?? [],
                        'views' => $message['views'] ?? 0,
                        'edit_date' => $message['edit_date'] ?? null,
                    ],
                    'url' => $this->buildMessageUrl($channel, $message['message_id']),
                    'published_at' => date('Y-m-d H:i:s', $message['date']),
                    'engagement_score' => $this->calculateEngagementScore($message),
                    'sentiment_score' => $sentimentData['score'],
                    'sentiment_label' => $sentimentData['label'],
                    'matched_keywords' => array_column($keywordMatches, 'keyword'),
                ]);

                if (!empty($keywordMatches)) {
                    $this->storeKeywordMatches($post, $keywordMatches);
                }
                
                $processed[] = $post;

                // Check for alerts
                if ($this->keywordMatcher->shouldTriggerAlert($keywordMatches, 'telegram')) {
                    $this->triggerAlert($post, $keywordMatches);
                }

            } catch (\Exception $e) {
                Log::error('Error processing Telegram message', [
                    'message_id' => $message['message_id'] ?? 'unknown',
                    'channel' => $channel,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $processed;
    }

    protected function buildMessageUrl(string $channel, int $messageId): string
    {
        return "https://t.me/{$channel}/{$messageId}";
    }

    protected function storeSocialMediaPost(array $data): SocialMediaPost
    {
        // Create unique platform_id for Telegram
        $platformId = $data['author_username'] . '_' . $data['platform_id'];
        
        return SocialMediaPost::updateOrCreate(
            ['platform_id' => $platformId],
            array_merge($data, ['platform_id' => $platformId])
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

    protected function calculateEngagementScore(array $message): int
    {
        $views = $message['views'] ?? 0;
        $forwards = $message['forwards'] ?? 0;
        $replies = isset($message['reply_to_message']) ? 1 : 0;
        
        // Telegram engagement calculation
        return ($views / 10) + ($forwards * 5) + ($replies * 2);
    }

    protected function canMakeRequest(): bool
    {
        $key = 'telegram_rate_limit_' . date('Y-m-d-H-i');
        $currentCount = Cache::get($key, 0);
        return $currentCount < $this->rateLimit;
    }

    protected function updateRateLimit(): void
    {
        $key = 'telegram_rate_limit_' . date('Y-m-d-H-i');
        Cache::increment($key, 1);
        Cache::put($key, Cache::get($key), 60); // 1 minute TTL
    }

    protected function triggerAlert(SocialMediaPost $post, array $matches): void
    {
        Log::alert('Critical keyword alert triggered', [
            'platform' => 'telegram',
            'post_id' => $post->id,
            'channel' => $post->metadata['channel'] ?? null,
            'matches' => $matches,
            'content' => substr($post->content, 0, 200) . '...',
        ]);
    }

    /**
     * Set webhook for receiving updates (optional, for real-time monitoring)
     */
    public function setWebhook(string $webhookUrl): bool
    {
        try {
            $response = $this->getHttpClient()->post("https://api.telegram.org/bot{$this->botToken}/setWebhook", [
                'url' => $webhookUrl,
                'allowed_updates' => ['channel_post', 'message'],
            ]);

            $data = $response->json();
            return $data['ok'] ?? false;
            
        } catch (\Exception $e) {
            Log::error('Failed to set Telegram webhook', [
                'error' => $e->getMessage(),
                'webhook_url' => $webhookUrl
            ]);
            return false;
        }
    }

    /**
     * Process webhook update (for real-time processing)
     */
    public function processWebhookUpdate(array $update): ?SocialMediaPost
    {
        if (!isset($update['channel_post']) && !isset($update['message'])) {
            return null;
        }

        $message = $update['channel_post'] ?? $update['message'];
        
        if (!isset($message['text']) || empty(trim($message['text']))) {
            return null;
        }

        $channel = $message['chat']['username'] ?? 'unknown';
        $processed = $this->processMessages([$update], $channel);
        
        return $processed[0] ?? null;
    }
}