<?php

declare(strict_types=1);

namespace App\Services\SocialCrawler\Crawlers;

use App\Services\SocialCrawler\CrawlerInterface;
use App\Services\SocialCrawler\RateLimitManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class TelegramCrawler implements CrawlerInterface
{
    private ?string $botToken;
    private RateLimitManager $rateLimitManager;
    private array $config;
    private array $monitoredChannels;

    public function __construct(RateLimitManager $rateLimitManager)
    {
        $this->botToken = config('social_crawler.telegram.bot_token');
        $this->rateLimitManager = $rateLimitManager;
        $this->config = config('social_crawler.telegram', []);
        $this->monitoredChannels = $this->config['monitored_channels'] ?? [];

        if (empty($this->botToken)) {
            Log::warning('Telegram Bot Token not configured, Telegram crawler will be disabled');
        }
    }

    public function crawl(array $params): array
    {
        if (empty($this->botToken)) {
            Log::warning('Telegram crawl skipped: no bot token configured');
            return [];
        }

        $keywords = $params['keywords'] ?? [];
        $maxResults = $params['max_results'] ?? 100;
        $since = $params['since'] ?? now()->subHour();
        $channels = $params['channels'] ?? $this->monitoredChannels;

        if (empty($keywords) || empty($channels)) {
            Log::warning('Telegram crawl requires keywords and channels', [
                'keywords_count' => count($keywords),
                'channels_count' => count($channels)
            ]);
            return [];
        }

        $allPosts = [];

        foreach ($channels as $channel) {
            if (count($allPosts) >= $maxResults) {
                break;
            }

            try {
                $posts = $this->getChannelPosts($channel, [
                    'keywords' => $keywords,
                    'limit' => min(50, ceil($maxResults / count($channels))),
                    'since' => $since
                ]);

                $allPosts = array_merge($allPosts, $posts);

            } catch (\Exception $e) {
                Log::error('Failed to crawl Telegram channel', [
                    'channel' => $channel,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }

        // Remove duplicates and sort by date
        $uniquePosts = $this->removeDuplicates($allPosts);
        usort($uniquePosts, function ($a, $b) {
            return strtotime($b['published_at']) - strtotime($a['published_at']);
        });

        return array_slice($uniquePosts, 0, $maxResults);
    }

    public function search(array $params): array
    {
        // Telegram Bot API doesn't have a search endpoint
        // We need to crawl channels and filter content
        return $this->crawl($params);
    }

    public function getUserPosts(string $username, array $params = []): array
    {
        // For Telegram, "username" would be a channel username
        $maxResults = $params['max_results'] ?? 20;
        $since = $params['since'] ?? now()->subHours(24);

        return $this->getChannelPosts($username, [
            'limit' => $maxResults,
            'since' => $since
        ]);
    }

    private function getChannelPosts(string $channelUsername, array $params = []): array
    {
        $endpoint = 'getUpdates';
        
        if (!$this->rateLimitManager->canMakeRequest('telegram', $endpoint)) {
            Log::warning('Telegram API rate limit exceeded', ['endpoint' => $endpoint]);
            return [];
        }

        // For Telegram Bot API, we need to use a different approach
        // We'll get channel info first, then recent messages
        $channelInfo = $this->getChannelInfo($channelUsername);
        
        if (!$channelInfo) {
            Log::warning('Could not get Telegram channel info', ['channel' => $channelUsername]);
            return [];
        }

        $chatId = $channelInfo['id'];
        
        // Note: Telegram Bot API has limitations for reading channel messages
        // For a production system, you might need to use Telegram Client API (MTProto)
        // or have the bot added as admin to the channels
        
        return $this->getRecentMessages($chatId, $params);
    }

    private function getChannelInfo(string $channelUsername): ?array
    {
        $cacheKey = "telegram_channel_info_{$channelUsername}";
        
        return Cache::remember($cacheKey, 3600, function () use ($channelUsername) {
            $url = "https://api.telegram.org/bot{$this->botToken}/getChat";
            
            try {
                $response = Http::get($url, [
                    'chat_id' => $channelUsername
                ]);

                $this->rateLimitManager->recordRequest('telegram', 'getChat');

                if ($response->successful()) {
                    $data = $response->json();
                    return $data['result'] ?? null;
                }
                
                return null;
                
            } catch (\Exception $e) {
                Log::error('Failed to get Telegram channel info', [
                    'channel' => $channelUsername,
                    'error' => $e->getMessage()
                ]);
                
                return null;
            }
        });
    }

    private function getRecentMessages(string $chatId, array $params = []): array
    {
        $keywords = $params['keywords'] ?? [];
        $limit = $params['limit'] ?? 20;
        $since = $params['since'] ?? null;

        // For bot API, we can only get updates or use getChatHistory if available
        // This is a simplified implementation - for production, consider using Telegram Client API
        
        $messages = $this->getChatHistory($chatId, $limit);
        $posts = [];

        foreach ($messages as $message) {
            // Skip if message is too old
            if ($since && Carbon::createFromTimestamp($message['date'])->lt(Carbon::parse($since))) {
                continue;
            }

            // Check if message contains any keywords
            if (!empty($keywords)) {
                $messageText = strtolower($message['text'] ?? '');
                $hasKeyword = false;
                
                foreach ($keywords as $keyword) {
                    if (str_contains($messageText, strtolower($keyword))) {
                        $hasKeyword = true;
                        break;
                    }
                }
                
                if (!$hasKeyword) {
                    continue;
                }
            }

            $posts[] = $this->transformMessage($message, $chatId);
        }

        return $posts;
    }

    private function getChatHistory(string $chatId, int $limit): array
    {
        // Note: This method requires special bot permissions or Telegram Client API
        // For demonstration, we'll return empty array
        // In production, you'd use telethon (Python) or MadelineProto (PHP) for this
        
        Log::info('Getting Telegram chat history would require Client API', [
            'chat_id' => $chatId,
            'limit' => $limit
        ]);
        
        return [];
    }

    private function transformMessage(array $message, string $chatId): array
    {
        $mediaUrls = $this->extractMediaUrls($message);
        $messageText = $this->getMessageText($message);
        
        // Generate external URL (may not be accessible without Client API)
        $messageId = $message['message_id'] ?? null;
        $channelUsername = $this->getCachedChannelUsername($chatId);
        $sourceUrl = $channelUsername && $messageId ? 
            "https://t.me/{$channelUsername}/{$messageId}" : 
            "https://t.me/c/{$chatId}/{$messageId}";

        return [
            'external_id' => $messageId,
            'source_url' => $sourceUrl,
            'author_username' => $message['from']['username'] ?? null,
            'author_display_name' => $this->getDisplayName($message['from'] ?? []),
            'author_id' => $message['from']['id'] ?? null,
            'content' => $messageText,
            'media_urls' => $mediaUrls,
            'engagement_count' => 0, // Telegram doesn't provide like/reaction counts via Bot API
            'share_count' => $message['forward_date'] ? 1 : 0,
            'comment_count' => 0, // Would need to count replies
            'published_at' => Carbon::createFromTimestamp($message['date'])->toISOString(),
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'is_forwarded' => isset($message['forward_date']),
            'forward_from' => $message['forward_from']['username'] ?? null,
            'reply_to_message' => $message['reply_to_message']['message_id'] ?? null,
        ];
    }

    private function getMessageText(array $message): string
    {
        $text = $message['text'] ?? $message['caption'] ?? '';
        
        // Add any entities (mentions, hashtags, etc.)
        $entities = $message['entities'] ?? $message['caption_entities'] ?? [];
        
        foreach ($entities as $entity) {
            if (in_array($entity['type'], ['mention', 'hashtag', 'url'])) {
                $entityText = substr($text, $entity['offset'], $entity['length']);
                // You could enhance this to make links clickable, etc.
            }
        }
        
        return $text;
    }

    private function extractMediaUrls(array $message): array
    {
        $urls = [];
        
        // Photos
        if (isset($message['photo'])) {
            $photo = end($message['photo']); // Get largest size
            if (isset($photo['file_id'])) {
                $fileUrl = $this->getFileUrl($photo['file_id']);
                if ($fileUrl) {
                    $urls[] = $fileUrl;
                }
            }
        }
        
        // Videos
        if (isset($message['video']['file_id'])) {
            $fileUrl = $this->getFileUrl($message['video']['file_id']);
            if ($fileUrl) {
                $urls[] = $fileUrl;
            }
        }
        
        // Documents (might be images/videos)
        if (isset($message['document']['file_id'])) {
            $mimeType = $message['document']['mime_type'] ?? '';
            if (str_starts_with($mimeType, 'image/') || str_starts_with($mimeType, 'video/')) {
                $fileUrl = $this->getFileUrl($message['document']['file_id']);
                if ($fileUrl) {
                    $urls[] = $fileUrl;
                }
            }
        }
        
        return $urls;
    }

    private function getFileUrl(string $fileId): ?string
    {
        $url = "https://api.telegram.org/bot{$this->botToken}/getFile";
        
        try {
            $response = Http::get($url, ['file_id' => $fileId]);
            
            if ($response->successful()) {
                $data = $response->json();
                $filePath = $data['result']['file_path'] ?? null;
                
                if ($filePath) {
                    return "https://api.telegram.org/file/bot{$this->botToken}/{$filePath}";
                }
            }
            
            return null;
            
        } catch (\Exception $e) {
            Log::error('Failed to get Telegram file URL', [
                'file_id' => $fileId,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    private function getDisplayName(array $user): ?string
    {
        if (empty($user)) {
            return null;
        }
        
        $firstName = $user['first_name'] ?? '';
        $lastName = $user['last_name'] ?? '';
        
        return trim($firstName . ' ' . $lastName) ?: ($user['username'] ?? null);
    }

    private function getCachedChannelUsername(string $chatId): ?string
    {
        // Try to get from cache or make API call
        $cacheKey = "telegram_chat_username_{$chatId}";
        
        return Cache::get($cacheKey);
    }

    private function removeDuplicates(array $posts): array
    {
        $seen = [];
        $unique = [];
        
        foreach ($posts as $post) {
            $id = $post['external_id'] . '_' . $post['chat_id'];
            if (!isset($seen[$id])) {
                $seen[$id] = true;
                $unique[] = $post;
            }
        }
        
        return $unique;
    }

    public function testConnection(): bool
    {
        try {
            $url = "https://api.telegram.org/bot{$this->botToken}/getMe";
            $response = Http::get($url);

            if ($response->successful()) {
                $data = $response->json();
                return $data['ok'] ?? false;
            }
            
            return false;
            
        } catch (\Exception $e) {
            Log::error('Telegram connection test failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function getRateLimitStatus(): array
    {
        return $this->rateLimitManager->getStatus('telegram');
    }

    public function getPlatformName(): string
    {
        return 'telegram';
    }

    /**
     * Get bot information
     */
    public function getBotInfo(): ?array
    {
        try {
            $url = "https://api.telegram.org/bot{$this->botToken}/getMe";
            $response = Http::get($url);

            if ($response->successful()) {
                $data = $response->json();
                return $data['result'] ?? null;
            }
            
            return null;
            
        } catch (\Exception $e) {
            Log::error('Failed to get Telegram bot info', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get webhook info (for debugging)
     */
    public function getWebhookInfo(): ?array
    {
        try {
            $url = "https://api.telegram.org/bot{$this->botToken}/getWebhookInfo";
            $response = Http::get($url);

            if ($response->successful()) {
                $data = $response->json();
                return $data['result'] ?? null;
            }
            
            return null;
            
        } catch (\Exception $e) {
            Log::error('Failed to get Telegram webhook info', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Set webhook URL for receiving updates
     */
    public function setWebhook(string $url): bool
    {
        try {
            $apiUrl = "https://api.telegram.org/bot{$this->botToken}/setWebhook";
            $response = Http::post($apiUrl, ['url' => $url]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['ok'] ?? false;
            }
            
            return false;
            
        } catch (\Exception $e) {
            Log::error('Failed to set Telegram webhook', [
                'webhook_url' => $url,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Process webhook update (for real-time monitoring)
     */
    public function processWebhookUpdate(array $update): ?array
    {
        if (!isset($update['message'])) {
            return null;
        }

        $message = $update['message'];
        $chatId = $message['chat']['id'] ?? null;
        
        if (!$chatId) {
            return null;
        }

        // Check if this is from a monitored channel
        $chatInfo = $message['chat'];
        $isChannel = $chatInfo['type'] === 'channel';
        $channelUsername = $chatInfo['username'] ?? null;
        
        if ($isChannel && $channelUsername && in_array('@' . $channelUsername, $this->monitoredChannels)) {
            return $this->transformMessage($message, $chatId);
        }

        return null;
    }

    /**
     * Get monitored channels configuration
     */
    public function getMonitoredChannels(): array
    {
        return $this->monitoredChannels;
    }

    /**
     * Add channel to monitoring list
     */
    public function addMonitoredChannel(string $channelUsername): bool
    {
        $channelUsername = str_starts_with($channelUsername, '@') ? $channelUsername : '@' . $channelUsername;
        
        if (!in_array($channelUsername, $this->monitoredChannels)) {
            $this->monitoredChannels[] = $channelUsername;
            
            // In a real application, you'd save this to database or config
            Log::info('Added Telegram channel to monitoring', ['channel' => $channelUsername]);
            
            return true;
        }
        
        return false;
    }

    /**
     * Remove channel from monitoring list
     */
    public function removeMonitoredChannel(string $channelUsername): bool
    {
        $channelUsername = str_starts_with($channelUsername, '@') ? $channelUsername : '@' . $channelUsername;
        $index = array_search($channelUsername, $this->monitoredChannels);
        
        if ($index !== false) {
            unset($this->monitoredChannels[$index]);
            $this->monitoredChannels = array_values($this->monitoredChannels); // Re-index
            
            Log::info('Removed Telegram channel from monitoring', ['channel' => $channelUsername]);
            
            return true;
        }
        
        return false;
    }
}