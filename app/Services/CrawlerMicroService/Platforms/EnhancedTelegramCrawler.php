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

final class EnhancedTelegramCrawler implements PlatformCrawlerInterface
{
    use UsesProxy;

    private string $botToken;
    private AdvancedKeywordEngine $keywordEngine;
    private array $config;
    private array $rateLimits;

    public function __construct(AdvancedKeywordEngine $keywordEngine, array $config = [])
    {
        $this->keywordEngine = $keywordEngine;
        $this->config = array_merge([
            'bot_token' => config('services.telegram.bot_token'),
            'rate_limit_per_minute' => 20,
            'rate_limit_per_second' => 1,
            'max_message_age_hours' => 24,
            'include_media' => true,
            'include_forwards' => false,
            'include_replies' => true,
        ], $config);

        $this->botToken = $this->config['bot_token'];
        $this->rateLimits = [
            'per_minute' => $this->config['rate_limit_per_minute'],
            'per_second' => $this->config['rate_limit_per_second'],
        ];

        if (!$this->botToken) {
            throw new Exception('Telegram bot token not configured');
        }
    }

    /**
     * Crawl multiple Telegram channels
     */
    public function crawl(array $options = []): array
    {
        $channels = $options['channels'] ?? $this->getDefaultChannels();
        $keywords = $options['keywords'] ?? null;
        $results = [];

        Log::info('Starting Telegram crawl', [
            'channels' => count($channels),
            'keywords' => $keywords ? count($keywords) : 'auto-detect'
        ]);

        foreach ($channels as $channel) {
            if (!$this->canMakeRequest('per_minute')) {
                Log::warning('Telegram rate limit reached, skipping remaining channels');
                break;
            }

            try {
                $channelResults = $this->crawlChannel($channel, $keywords);
                $results[$channel] = $channelResults;
                
                $this->updateRateLimit('per_minute');
                $this->respectRateLimit('per_second');
                
            } catch (Exception $e) {
                Log::error('Telegram channel crawl failed', [
                    'channel' => $channel,
                    'error' => $e->getMessage()
                ]);
                $results[$channel] = ['error' => $e->getMessage(), 'posts' => []];
            }
        }

        return $results;
    }

    /**
     * Crawl a specific Telegram channel
     */
    public function crawlChannel(string $channelUsername, ?array $keywords = null): array
    {
        $channelInfo = $this->getChannelInfo($channelUsername);
        if (!$channelInfo) {
            throw new Exception("Could not access channel: @{$channelUsername}");
        }

        $messages = $this->getChannelMessages($channelUsername);
        $processedPosts = $this->processMessages($messages, $channelUsername, $keywords);

        return [
            'channel_info' => $channelInfo,
            'posts' => $processedPosts,
            'stats' => [
                'total_fetched' => count($messages),
                'total_processed' => count($processedPosts),
                'keyword_matches' => array_sum(array_column($processedPosts, 'match_count')),
            ]
        ];
    }

    /**
     * Search for messages containing specific keywords
     */
    public function searchByKeywords(array $keywords, array $channels = null): array
    {
        $channels = $channels ?? $this->getDefaultChannels();
        $results = [];

        foreach ($channels as $channel) {
            if (!$this->canMakeRequest('per_minute')) {
                break;
            }

            try {
                $messages = $this->getChannelMessages($channel);
                $filteredMessages = $this->filterMessagesByKeywords($messages, $keywords);
                $processedPosts = $this->processMessages($filteredMessages, $channel, $keywords);
                
                $results[$channel] = [
                    'total_messages' => count($messages),
                    'filtered_messages' => count($filteredMessages),
                    'posts' => $processedPosts,
                ];

                $this->updateRateLimit('per_minute');
                $this->respectRateLimit('per_second');

            } catch (Exception $e) {
                Log::error('Telegram keyword search failed', [
                    'channel' => $channel,
                    'keywords' => $keywords,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    /**
     * Get channel information
     */
    private function getChannelInfo(string $channelUsername): ?array
    {
        try {
            $response = $this->makeApiRequest('getChat', [
                'chat_id' => '@' . ltrim($channelUsername, '@')
            ]);

            if (!$response['ok']) {
                return null;
            }

            $chat = $response['result'];
            return [
                'id' => $chat['id'],
                'title' => $chat['title'] ?? $channelUsername,
                'username' => $chat['username'] ?? $channelUsername,
                'description' => $chat['description'] ?? null,
                'member_count' => $chat['members_count'] ?? null,
                'type' => $chat['type'],
            ];

        } catch (Exception $e) {
            Log::warning('Failed to get Telegram channel info', [
                'channel' => $channelUsername,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get recent messages from a channel
     */
    private function getChannelMessages(string $channelUsername, int $limit = 100): array
    {
        $cacheKey = "telegram_messages_{$channelUsername}_" . date('Y-m-d-H');
        
        return Cache::remember($cacheKey, 1800, function () use ($channelUsername, $limit) {
            try {
                // Use getUpdates method with offset to get recent messages
                $response = $this->makeApiRequest('getUpdates', [
                    'allowed_updates' => ['channel_post'],
                    'limit' => min($limit, 100),
                ]);

                if (!$response['ok']) {
                    throw new Exception('Failed to get channel updates: ' . json_encode($response));
                }

                $updates = $response['result'];
                $messages = [];
                $cutoffTime = now()->subHours($this->config['max_message_age_hours'])->timestamp;

                foreach ($updates as $update) {
                    if (isset($update['channel_post'])) {
                        $message = $update['channel_post'];
                        
                        // Check if message is from the target channel
                        if (isset($message['chat']['username']) && 
                            strtolower($message['chat']['username']) === strtolower(ltrim($channelUsername, '@'))) {
                            
                            // Filter by age
                            if ($message['date'] >= $cutoffTime) {
                                $messages[] = $message;
                            }
                        }
                    }
                }

                return $messages;

            } catch (Exception $e) {
                Log::error('Failed to fetch Telegram messages', [
                    'channel' => $channelUsername,
                    'error' => $e->getMessage()
                ]);
                return [];
            }
        });
    }

    /**
     * Filter messages by keywords
     */
    private function filterMessagesByKeywords(array $messages, array $keywords): array
    {
        if (empty($keywords)) {
            return $messages;
        }

        $filtered = [];
        $keywordPattern = '/' . implode('|', array_map('preg_quote', $keywords)) . '/i';

        foreach ($messages as $message) {
            $text = $this->extractMessageText($message);
            if ($text && preg_match($keywordPattern, $text)) {
                $filtered[] = $message;
            }
        }

        return $filtered;
    }

    /**
     * Process messages and create social media posts
     */
    private function processMessages(array $messages, string $channelUsername, ?array $searchKeywords = null): array
    {
        $processed = [];

        foreach ($messages as $message) {
            try {
                $text = $this->extractMessageText($message);
                if (!$text) {
                    continue; // Skip messages without text
                }

                // Match against keyword rules
                $matches = $this->keywordEngine->matchContent($text, 'telegram', [
                    'channel' => $channelUsername,
                    'message_id' => $message['message_id'],
                    'date' => $message['date'],
                ]);

                if (empty($matches) && $searchKeywords) {
                    // If no rule matches but we have search keywords, create basic match
                    $basicMatches = $this->createBasicMatches($text, $searchKeywords);
                    $matches = array_merge($matches, $basicMatches);
                }

                if (empty($matches)) {
                    continue; // Skip if no keyword matches
                }

                $post = $this->createSocialMediaPost($message, $channelUsername, $text, $matches);
                $this->storeKeywordMatches($post, $matches);
                
                $processed[] = array_merge($post->toArray(), [
                    'match_count' => count($matches),
                    'matches' => $matches,
                ]);

                // Check for alerts
                if ($this->keywordEngine->shouldTriggerAlert($matches, 'telegram')) {
                    $this->triggerAlert($post, $matches);
                }

            } catch (Exception $e) {
                Log::error('Error processing Telegram message', [
                    'message_id' => $message['message_id'] ?? 'unknown',
                    'channel' => $channelUsername,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $processed;
    }

    /**
     * Extract text content from a message
     */
    private function extractMessageText(array $message): ?string
    {
        $text = '';

        // Main text
        if (isset($message['text'])) {
            $text = $message['text'];
        } elseif (isset($message['caption'])) {
            $text = $message['caption'];
        }

        // Handle message entities (mentions, hashtags, etc.)
        if (isset($message['entities'])) {
            $text = $this->processMessageEntities($text, $message['entities']);
        }

        return trim($text) ?: null;
    }

    /**
     * Process message entities for better text extraction
     */
    private function processMessageEntities(string $text, array $entities): string
    {
        // Sort entities by offset to process them correctly
        usort($entities, fn($a, $b) => $a['offset'] <=> $b['offset']);

        $processedText = $text;
        $offset = 0;

        foreach ($entities as $entity) {
            $entityText = mb_substr($text, $entity['offset'], $entity['length']);
            
            // Add context for different entity types
            $replacement = match($entity['type']) {
                'mention' => $entityText . ' (user)',
                'hashtag' => $entityText . ' (hashtag)',
                'url' => $entityText . ' (link)',
                'text_link' => $entityText . ' (' . ($entity['url'] ?? 'link') . ')',
                default => $entityText
            };

            if ($replacement !== $entityText) {
                $processedText = mb_substr($processedText, 0, $entity['offset'] + $offset) . 
                               $replacement . 
                               mb_substr($processedText, $entity['offset'] + $entity['length'] + $offset);
                $offset += mb_strlen($replacement) - mb_strlen($entityText);
            }
        }

        return $processedText;
    }

    /**
     * Create basic keyword matches for search terms
     */
    private function createBasicMatches(string $text, array $keywords): array
    {
        $matches = [];
        
        foreach ($keywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                $matches[] = [
                    'rule_id' => null,
                    'rule_name' => 'Search Term',
                    'keyword' => $keyword,
                    'category' => 'search',
                    'priority' => 5,
                    'position' => stripos($text, $keyword),
                    'context' => substr($text, max(0, stripos($text, $keyword) - 50), 100),
                    'density' => substr_count(strtolower($text), strtolower($keyword)) / str_word_count($text) * 100,
                    'score' => 5.0,
                    'triggers' => [],
                ];
            }
        }

        return $matches;
    }

    /**
     * Create social media post from Telegram message
     */
    private function createSocialMediaPost(array $message, string $channelUsername, string $text, array $matches): SocialMediaPost
    {
        $metadata = [
            'channel' => $channelUsername,
            'message_id' => $message['message_id'],
            'chat_id' => $message['chat']['id'],
            'chat_title' => $message['chat']['title'] ?? $channelUsername,
            'has_media' => $this->hasMedia($message),
            'media_type' => $this->getMediaType($message),
            'views' => $message['views'] ?? null,
            'edit_date' => $message['edit_date'] ?? null,
            'forward_from' => $this->getForwardInfo($message),
            'reply_to' => $message['reply_to_message']['message_id'] ?? null,
            'entities' => $message['entities'] ?? [],
        ];

        // Calculate engagement score
        $engagementScore = $this->calculateEngagementScore($message);

        return SocialMediaPost::updateOrCreate(
            [
                'platform' => 'telegram',
                'platform_id' => $channelUsername . '_' . $message['message_id']
            ],
            [
                'author_username' => $channelUsername,
                'author_id' => (string) $message['chat']['id'],
                'content' => $text,
                'metadata' => $metadata,
                'url' => $this->buildMessageUrl($channelUsername, $message['message_id']),
                'published_at' => date('Y-m-d H:i:s', $message['date']),
                'engagement_score' => $engagementScore,
                'sentiment_score' => 0, // Will be calculated later if needed
                'sentiment_label' => 'neutral',
                'matched_keywords' => array_column($matches, 'keyword'),
            ]
        );
    }

    /**
     * Check if message has media
     */
    private function hasMedia(array $message): bool
    {
        $mediaFields = ['photo', 'video', 'document', 'audio', 'voice', 'sticker', 'animation'];
        
        foreach ($mediaFields as $field) {
            if (isset($message[$field])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get media type from message
     */
    private function getMediaType(array $message): ?string
    {
        $mediaFields = ['photo', 'video', 'document', 'audio', 'voice', 'sticker', 'animation'];
        
        foreach ($mediaFields as $field) {
            if (isset($message[$field])) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Get forward information
     */
    private function getForwardInfo(array $message): ?array
    {
        if (!$this->config['include_forwards'] || !isset($message['forward_from'])) {
            return null;
        }

        return [
            'from_user' => $message['forward_from']['username'] ?? null,
            'from_chat' => $message['forward_from_chat']['title'] ?? null,
            'date' => $message['forward_date'] ?? null,
        ];
    }

    /**
     * Calculate engagement score for Telegram message
     */
    private function calculateEngagementScore(array $message): int
    {
        $score = 0;
        
        // Views (if available)
        if (isset($message['views'])) {
            $score += min($message['views'] / 100, 10); // Max 10 points for views
        }

        // Media bonus
        if ($this->hasMedia($message)) {
            $score += 2;
        }

        // Forward bonus
        if (isset($message['forward_from'])) {
            $score += 1;
        }

        // Reply bonus
        if (isset($message['reply_to_message'])) {
            $score += 1;
        }

        return (int) $score;
    }

    /**
     * Build message URL
     */
    private function buildMessageUrl(string $channelUsername, int $messageId): string
    {
        return "https://t.me/{$channelUsername}/{$messageId}";
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
     * Make API request to Telegram Bot API
     */
    private function makeApiRequest(string $method, array $params = []): array
    {
        $url = "https://api.telegram.org/bot{$this->botToken}/{$method}";
        
        $response = $this->getHttpClient()
            ->timeout(30)
            ->post($url, $params);

        if (!$response->successful()) {
            throw new Exception("Telegram API error: " . $response->body());
        }

        return $response->json();
    }

    /**
     * Get default channels to crawl
     */
    private function getDefaultChannels(): array
    {
        return config('crawler_microservice.telegram.channels', [
            'blockchain',
            'cryptocurrency',
            'defi',
            'ethereum',
            'bitcoin',
        ]);
    }

    /**
     * Check rate limiting
     */
    private function canMakeRequest(string $limitType): bool
    {
        $key = "telegram_rate_limit_{$limitType}_" . date($limitType === 'per_minute' ? 'Y-m-d-H-i' : 'Y-m-d-H-i-s');
        $currentCount = Cache::get($key, 0);
        $limit = $this->rateLimits[$limitType];
        
        return $currentCount < $limit;
    }

    /**
     * Update rate limit counter
     */
    private function updateRateLimit(string $limitType): void
    {
        $key = "telegram_rate_limit_{$limitType}_" . date($limitType === 'per_minute' ? 'Y-m-d-H-i' : 'Y-m-d-H-i-s');
        $ttl = $limitType === 'per_minute' ? 60 : 1;
        
        Cache::increment($key, 1);
        Cache::put($key, Cache::get($key), $ttl);
    }

    /**
     * Respect rate limiting
     */
    private function respectRateLimit(string $limitType): void
    {
        if ($limitType === 'per_second') {
            sleep(1); // Always wait 1 second between requests
        }
    }

    /**
     * Trigger alert for critical matches
     */
    private function triggerAlert(SocialMediaPost $post, array $matches): void
    {
        Log::alert('Critical keyword alert triggered', [
            'platform' => 'telegram',
            'post_id' => $post->id,
            'channel' => $post->metadata['channel'] ?? null,
            'matches' => array_column($matches, 'keyword'),
            'content' => substr($post->content, 0, 200) . '...',
        ]);
    }
}
