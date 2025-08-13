<?php

declare(strict_types=1);

namespace App\Services\Crawlers;

use App\Models\CrawlerRule;
use App\Models\SocialMediaPost;
use App\Services\ApiCacheService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class TelegramCrawlerService extends BaseCrawlerService
{
    private const BASE_URL = 'https://api.telegram.org';
    private const RATE_LIMIT_DELAY = 1; // 1 second between requests

    public function __construct(
        private readonly ApiCacheService $cacheService
    ) {
        parent::__construct();
    }

    /**
     * Crawl Telegram based on crawler rules.
     */
    public function crawl(CrawlerRule $rule): array
    {
        $this->validateTelegramCredentials();

        $results = [
            'platform' => 'telegram',
            'rule_id' => $rule->id,
            'posts_found' => 0,
            'posts_processed' => 0,
            'posts_stored' => 0,
            'errors' => [],
            'channels_crawled' => [],
            'execution_time' => 0,
        ];

        $startTime = microtime(true);

        try {
            $config = $rule->getPlatformConfig('telegram');
            $maxResults = min($config['max_results'] ?? 100, $rule->getRemainingHourlyQuota());

            if ($maxResults <= 0) {
                $results['errors'][] = 'Rate limit quota exhausted';
                return $results;
            }

            // Get channels/groups to monitor from config
            $channels = $config['channels'] ?? [];
            if (empty($channels)) {
                $results['errors'][] = 'No Telegram channels configured for crawling';
                return $results;
            }

            $allMessages = [];
            $crawledChannels = [];

            // Crawl each configured channel
            foreach ($channels as $channel) {
                try {
                    $messages = $this->crawlChannel($channel, $rule, $maxResults);
                    $allMessages = array_merge($allMessages, $messages);
                    $crawledChannels[] = $channel;

                    // Rate limiting
                    sleep(self::RATE_LIMIT_DELAY);

                } catch (\Exception $e) {
                    $results['errors'][] = "Failed to crawl channel {$channel}: " . $e->getMessage();
                    Log::warning("Failed to crawl Telegram channel: {$channel}", [
                        'error' => $e->getMessage(),
                        'rule_id' => $rule->id,
                    ]);
                }
            }

            $results['posts_found'] = count($allMessages);
            $results['channels_crawled'] = $crawledChannels;

            // Process and store messages
            if (!empty($allMessages)) {
                $processed = $this->processMessages($allMessages, $rule);
                $results['posts_processed'] = $processed['processed'];
                $results['posts_stored'] = $processed['stored'];
                $results['errors'] = array_merge($results['errors'], $processed['errors']);
            }

            // Update rule statistics
            $rule->updateCrawlStats([
                'posts_found' => $results['posts_found'],
                'posts_processed' => $results['posts_processed'],
                'platform' => 'telegram',
                'channels' => $results['channels_crawled'],
                'timestamp' => now()->toISOString(),
            ]);

            Log::info('Telegram crawl completed', [
                'rule_id' => $rule->id,
                'posts_found' => $results['posts_found'],
                'posts_stored' => $results['posts_stored'],
            ]);

        } catch (\Exception $e) {
            $results['errors'][] = $e->getMessage();
            Log::error('Telegram crawl failed', [
                'rule_id' => $rule->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        $results['execution_time'] = round(microtime(true) - $startTime, 2);
        return $results;
    }

    /**
     * Crawl a specific Telegram channel.
     */
    private function crawlChannel(string $channel, CrawlerRule $rule, int $maxResults): array
    {
        $config = $rule->getPlatformConfig('telegram');
        
        // For public channels, we can use the public API without bot token
        if ($this->isPublicChannel($channel)) {
            return $this->crawlPublicChannel($channel, $config, $maxResults);
        }

        // For private channels/groups, we need bot API (limited functionality)
        return $this->crawlWithBot($channel, $config, $maxResults);
    }

    /**
     * Crawl public Telegram channel using web scraping approach.
     */
    private function crawlPublicChannel(string $channel, array $config, int $maxResults): array
    {
        // Clean channel name
        $channelName = ltrim($channel, '@');
        
        try {
            // Use Telegram's public preview (this is a simplified approach)
            // In production, you might want to use a more robust scraping method
            $cacheKey = "telegram_channel_{$channelName}";
            
            $messages = $this->cacheService->cacheOrRetrieve(
                'telegram',
                "channel/{$channelName}",
                'channel_messages',
                fn() => $this->scrapePublicChannel($channelName, $maxResults),
                ['channel' => $channelName, 'limit' => $maxResults],
                $cacheKey,
                600 // 10 minutes cache
            );

            return $messages;

        } catch (\Exception $e) {
            Log::error("Failed to crawl public Telegram channel: {$channel}", [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Crawl using Telegram Bot API (limited to channels where bot is admin).
     */
    private function crawlWithBot(string $channel, array $config, int $maxResults): array
    {
        $botToken = config('services.telegram.bot_token');
        if (!$botToken) {
            throw new \Exception('Telegram Bot Token not configured');
        }

        try {
            // Get chat info first
            $chatInfo = $this->getBotApiCall($botToken, 'getChat', ['chat_id' => $channel]);
            
            // Get recent messages (Bot API has limitations here)
            // This is more suitable for channels where the bot is an admin
            $updates = $this->getBotApiCall($botToken, 'getUpdates', [
                'offset' => -$maxResults,
                'limit' => min($maxResults, 100),
            ]);

            return $this->extractMessagesFromUpdates($updates['result'] ?? [], $channel);

        } catch (\Exception $e) {
            Log::error("Failed to crawl Telegram channel with bot: {$channel}", [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Scrape public Telegram channel (simplified approach).
     */
    private function scrapePublicChannel(string $channelName, int $maxResults): array
    {
        // This is a simplified implementation
        // In production, you'd want to use a proper scraping library or service
        
        $url = "https://t.me/s/{$channelName}";
        
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; CrawlerBot/1.0)',
            ])->timeout(30)->get($url);

            if (!$response->successful()) {
                throw new \Exception("Failed to access channel: HTTP {$response->status()}");
            }

            // Parse HTML to extract messages (simplified)
            $html = $response->body();
            return $this->parseChannelHtml($html, $channelName, $maxResults);

        } catch (\Exception $e) {
            throw new \Exception("Failed to scrape Telegram channel: " . $e->getMessage());
        }
    }

    /**
     * Parse Telegram channel HTML to extract messages.
     */
    private function parseChannelHtml(string $html, string $channelName, int $maxResults): array
    {
        $messages = [];
        
        // This is a very basic HTML parsing approach
        // In production, use a proper HTML parser like DOMDocument or Goutte
        
        preg_match_all('/<div class="tgme_widget_message_text.*?">(.*?)<\/div>/s', $html, $matches);
        
        foreach ($matches[1] as $index => $messageText) {
            if (count($messages) >= $maxResults) {
                break;
            }

            // Clean HTML tags
            $cleanText = strip_tags($messageText);
            $cleanText = html_entity_decode($cleanText);
            $cleanText = trim($cleanText);

            if (empty($cleanText)) {
                continue;
            }

            $messages[] = [
                'id' => $channelName . '_' . $index . '_' . time(),
                'text' => $cleanText,
                'date' => time(), // Simplified - would need to parse actual date
                'chat' => [
                    'title' => $channelName,
                    'username' => $channelName,
                    'type' => 'channel',
                ],
                'views' => 0, // Not available from scraping
                'forwards' => 0, // Not available from scraping
            ];
        }

        return $messages;
    }

    /**
     * Make Telegram Bot API call.
     */
    private function getBotApiCall(string $botToken, string $method, array $params = []): array
    {
        $response = Http::timeout(30)
            ->retry(3, 1000)
            ->post(self::BASE_URL . "/bot{$botToken}/{$method}", $params);

        if (!$response->successful()) {
            throw new \Exception("Telegram Bot API error: HTTP {$response->status()}");
        }

        $data = $response->json();
        
        if (!$data['ok']) {
            throw new \Exception("Telegram API error: " . ($data['description'] ?? 'Unknown error'));
        }

        return $data;
    }

    /**
     * Extract messages from Bot API updates.
     */
    private function extractMessagesFromUpdates(array $updates, string $targetChannel): array
    {
        $messages = [];
        
        foreach ($updates as $update) {
            if (isset($update['channel_post'])) {
                $message = $update['channel_post'];
                
                // Check if message is from target channel
                if (isset($message['chat']['username']) && 
                    $message['chat']['username'] === ltrim($targetChannel, '@')) {
                    $messages[] = $message;
                }
            }
        }

        return $messages;
    }

    /**
     * Process and store Telegram messages.
     */
    private function processMessages(array $messages, CrawlerRule $rule): array
    {
        $results = [
            'processed' => 0,
            'stored' => 0,
            'errors' => [],
        ];

        foreach ($messages as $message) {
            try {
                $results['processed']++;

                // Extract text content
                $content = $message['text'] ?? '';
                if (empty($content)) {
                    continue;
                }

                // Prepare metadata for content matching
                $metadata = [
                    'engagement' => ($message['views'] ?? 0) + ($message['forwards'] ?? 0),
                    'views' => $message['views'] ?? 0,
                    'forwards' => $message['forwards'] ?? 0,
                    'channel' => $message['chat']['title'] ?? $message['chat']['username'] ?? '',
                    'channel_type' => $message['chat']['type'] ?? 'channel',
                ];

                // Check if message matches rule criteria
                if (!$rule->matchesContent($content, $metadata)) {
                    continue;
                }

                // Store the message
                $post = $this->storeMessage($message, $rule, $metadata);
                if ($post) {
                    $results['stored']++;
                }

            } catch (\Exception $e) {
                $results['errors'][] = "Failed to process message {$message['id']}: " . $e->getMessage();
                Log::error('Telegram message processing failed', [
                    'message_id' => $message['id'],
                    'rule_id' => $rule->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Store Telegram message as social media post.
     */
    private function storeMessage(array $message, CrawlerRule $rule, array $metadata): ?SocialMediaPost
    {
        // Check if message already exists
        $existing = SocialMediaPost::where('platform', 'telegram')
            ->where('external_id', $message['id'])
            ->first();

        if ($existing) {
            return $existing;
        }

        // Determine message type
        $messageType = 'text';
        if (isset($message['photo'])) {
            $messageType = 'photo';
        } elseif (isset($message['video'])) {
            $messageType = 'video';
        } elseif (isset($message['document'])) {
            $messageType = 'document';
        } elseif (isset($message['audio'])) {
            $messageType = 'audio';
        } elseif (isset($message['sticker'])) {
            $messageType = 'sticker';
        }

        // Extract additional content
        $content = $message['text'] ?? '';
        if (isset($message['caption'])) {
            $content .= ' ' . $message['caption'];
        }

        return SocialMediaPost::create([
            'platform' => 'telegram',
            'external_id' => (string) $message['id'],
            'post_type' => $messageType,
            'content' => $this->cleanText($content),
            'author_username' => $message['chat']['username'] ?? null,
            'author_display_name' => $message['chat']['title'] ?? $message['chat']['username'] ?? null,
            'author_id' => (string) $message['chat']['id'],
            'author_followers' => 0, // Not available via API
            'author_verified' => false,
            'engagement_metrics' => [
                'views' => $message['views'] ?? 0,
                'forwards' => $message['forwards'] ?? 0,
                'replies' => 0, // Not available
            ],
            'metadata' => array_merge($metadata, [
                'channel_type' => $message['chat']['type'] ?? 'channel',
                'channel_id' => $message['chat']['id'],
                'message_id' => $message['message_id'] ?? $message['id'],
                'date' => $message['date'] ?? time(),
                'edit_date' => $message['edit_date'] ?? null,
                'media_group_id' => $message['media_group_id'] ?? null,
                'entities' => $message['entities'] ?? [],
            ]),
            'matched_keywords' => $rule->getMatchedKeywords($content),
            'matched_hashtags' => $this->extractHashtags($content),
            'posted_at' => isset($message['date']) ? 
                \Carbon\Carbon::createFromTimestamp($message['date']) : 
                now(),
            'crawler_rule_id' => $rule->id,
            'sentiment_score' => null,
            'processing_status' => 'pending',
        ]);
    }

    /**
     * Check if channel is public.
     */
    private function isPublicChannel(string $channel): bool
    {
        // Public channels typically have usernames and can be accessed via t.me/channelname
        return !str_starts_with($channel, '-') && // Private groups/channels start with -
               !is_numeric($channel); // Numeric IDs are typically private
    }

    /**
     * Validate Telegram API credentials.
     */
    private function validateTelegramCredentials(): void
    {
        // For public channel scraping, no credentials needed
        // For bot API, check if bot token is configured
        $botToken = config('services.telegram.bot_token');
        
        if (!$botToken) {
            Log::warning('Telegram Bot Token not configured. Only public channel scraping will be available.');
        }
    }

    /**
     * Get channel information.
     */
    public function getChannelInfo(string $channel): array
    {
        $botToken = config('services.telegram.bot_token');
        if (!$botToken) {
            throw new \Exception('Telegram Bot Token required for channel info');
        }

        $cacheKey = "telegram_channel_info_{$channel}";
        
        return $this->cacheService->cacheOrRetrieve(
            'telegram',
            "getChat/{$channel}",
            'channel_info',
            fn() => $this->getBotApiCall($botToken, 'getChat', ['chat_id' => $channel]),
            ['chat_id' => $channel],
            $cacheKey,
            3600 // 1 hour cache
        );
    }

    /**
     * Search for public channels (limited functionality).
     */
    public function searchChannels(string $query): array
    {
        // Telegram doesn't provide a search API for channels
        // This would need to be implemented using external services
        // or web scraping of Telegram directory sites
        
        return [
            'query' => $query,
            'results' => [],
            'note' => 'Channel search requires external services or manual channel list',
        ];
    }

    /**
     * Set up webhook for real-time message processing.
     */
    public function setupWebhook(string $webhookUrl): array
    {
        $botToken = config('services.telegram.bot_token');
        if (!$botToken) {
            throw new \Exception('Telegram Bot Token required for webhook setup');
        }

        return $this->getBotApiCall($botToken, 'setWebhook', [
            'url' => $webhookUrl,
            'allowed_updates' => ['message', 'channel_post'],
        ]);
    }

    /**
     * Remove webhook.
     */
    public function removeWebhook(): array
    {
        $botToken = config('services.telegram.bot_token');
        if (!$botToken) {
            throw new \Exception('Telegram Bot Token required');
        }

        return $this->getBotApiCall($botToken, 'deleteWebhook');
    }

    /**
     * Process webhook update for real-time crawling.
     */
    public function processWebhookUpdate(array $update, CrawlerRule $rule): array
    {
        try {
            $message = null;
            
            if (isset($update['message'])) {
                $message = $update['message'];
            } elseif (isset($update['channel_post'])) {
                $message = $update['channel_post'];
            }

            if (!$message) {
                return ['processed' => false, 'reason' => 'No valid message in update'];
            }

            // Check if message matches rule criteria
            $content = $message['text'] ?? $message['caption'] ?? '';
            $metadata = [
                'channel' => $message['chat']['title'] ?? $message['chat']['username'] ?? '',
                'channel_type' => $message['chat']['type'] ?? 'private',
            ];

            if (!$rule->matchesContent($content, $metadata)) {
                return ['processed' => false, 'reason' => 'Message does not match rule criteria'];
            }

            // Store the message
            $post = $this->storeMessage($message, $rule, $metadata);
            
            return [
                'processed' => true,
                'post_id' => $post?->id,
                'message' => 'Message processed and stored successfully',
            ];

        } catch (\Exception $e) {
            Log::error('Telegram webhook processing failed', [
                'update_id' => $update['update_id'] ?? null,
                'rule_id' => $rule->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'processed' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
