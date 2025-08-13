<?php

namespace App\Services\CrawlerMicroService\Platforms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Exception;

class TelegramCrawler implements PlatformCrawlerInterface
{
    private array $config;
    private string $botToken;
    private array $channels;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->botToken = $config['bot_token'];
        $this->channels = $config['target_channels'] ?? [];
        
        if (empty($this->botToken)) {
            throw new Exception('Telegram Bot Token is required');
        }
    }

    /**
     * Search Telegram channels by keywords
     */
    public function searchByKeywords(array $keywords, int $maxResults = 50): array
    {
        $posts = [];
        
        try {
            Log::info('Searching Telegram channels', [
                'keywords' => $keywords,
                'channels' => $this->channels,
                'max_results' => $maxResults
            ]);
            
            // Search each configured channel
            foreach ($this->channels as $channel) {
                $channelPosts = $this->getChannelMessages($channel, $keywords, $maxResults);
                $posts = array_merge($posts, $channelPosts);
                
                // Rate limiting between channels
                sleep(1);
            }
            
            // Remove duplicates and apply filters
            $posts = $this->removeDuplicates($posts);
            $posts = $this->applyFilters($posts);
            
            Log::info('Telegram search completed', [
                'posts_found' => count($posts),
                'channels_searched' => count($this->channels)
            ]);
            
        } catch (Exception $e) {
            Log::error('Telegram search failed', [
                'error' => $e->getMessage(),
                'keywords' => $keywords
            ]);
            throw $e;
        }
        
        return $posts;
    }

    /**
     * Get messages from a specific Telegram channel
     */
    public function getChannelMessages(string $channel, array $keywords = [], int $limit = 50): array
    {
        $posts = [];
        
        try {
            // For Bot API, we can only get updates, not historical channel messages
            // This is a limitation of the Bot API vs MTProto
            // In a real implementation, you'd need to use MTProto client or Telegram's paid API
            
            // Get channel info first
            $channelInfo = $this->getChannelInfo($channel);
            if (!$channelInfo) {
                Log::warning("Could not get info for channel: {$channel}");
                return [];
            }
            
            // Use getUpdates to get recent messages (limited functionality)
            $updates = $this->getUpdates();
            
            foreach ($updates as $update) {
                if (isset($update['channel_post'])) {
                    $message = $update['channel_post'];
                    
                    // Check if message is from our target channel
                    if ($this->isFromTargetChannel($message, $channel)) {
                        $post = $this->processMessage($message, $keywords);
                        if ($post) {
                            $posts[] = $post;
                        }
                    }
                }
                
                if (count($posts) >= $limit) {
                    break;
                }
            }
            
        } catch (Exception $e) {
            Log::error('Failed to get Telegram channel messages', [
                'channel' => $channel,
                'error' => $e->getMessage()
            ]);
        }
        
        return $posts;
    }

    /**
     * Get channel information
     */
    private function getChannelInfo(string $channel): ?array
    {
        try {
            $response = $this->makeRequest('getChat', [
                'chat_id' => $channel
            ]);
            
            return $response['result'] ?? null;
            
        } catch (Exception $e) {
            Log::warning('Failed to get channel info', [
                'channel' => $channel,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get updates from Telegram Bot API
     */
    private function getUpdates(int $offset = 0, int $limit = 100): array
    {
        try {
            $response = $this->makeRequest('getUpdates', [
                'offset' => $offset,
                'limit' => $limit,
                'timeout' => 10,
                'allowed_updates' => ['channel_post', 'message']
            ]);
            
            return $response['result'] ?? [];
            
        } catch (Exception $e) {
            Log::error('Failed to get Telegram updates', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Check if message is from target channel
     */
    private function isFromTargetChannel(array $message, string $targetChannel): bool
    {
        $chat = $message['chat'] ?? [];
        
        // Check by username
        if (isset($chat['username'])) {
            $channelUsername = '@' . $chat['username'];
            return $channelUsername === $targetChannel;
        }
        
        // Check by title (less reliable)
        if (isset($chat['title'])) {
            return stripos($chat['title'], str_replace('@', '', $targetChannel)) !== false;
        }
        
        return false;
    }

    /**
     * Process raw Telegram message
     */
    private function processMessage(array $message, array $keywords = []): ?array
    {
        try {
            $content = $this->extractMessageContent($message);
            
            if (empty($content)) {
                return null;
            }
            
            // Check keyword matching if keywords provided
            if (!empty($keywords)) {
                $matchedKeywords = $this->findMatchingKeywords($content, $keywords);
                if (empty($matchedKeywords)) {
                    return null;
                }
            } else {
                $matchedKeywords = [];
            }
            
            $chat = $message['chat'] ?? [];
            $channelUsername = isset($chat['username']) ? '@' . $chat['username'] : null;
            
            return [
                'id' => (string)$message['message_id'],
                'platform' => 'telegram',
                'content' => $content,
                'author' => $chat['title'] ?? $chat['first_name'] ?? 'Unknown',
                'channel' => $channelUsername,
                'created_at' => Carbon::createFromTimestamp($message['date'])->toISOString(),
                'url' => $this->buildMessageUrl($chat, $message['message_id']),
                'metrics' => [
                    'views' => $message['views'] ?? 0,
                    'forwards' => $message['forward_from_message_id'] ? 1 : 0
                ],
                'message_type' => $this->determineMessageType($message),
                'keywords_matched' => $matchedKeywords,
                'entities' => $this->extractEntities($message),
                'media' => $this->extractMediaInfo($message),
                'raw_data' => $message
            ];
            
        } catch (Exception $e) {
            Log::error('Failed to process Telegram message', [
                'message_id' => $message['message_id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Extract content from message
     */
    private function extractMessageContent(array $message): string
    {
        $content = '';
        
        // Text content
        if (isset($message['text'])) {
            $content = $message['text'];
        } elseif (isset($message['caption'])) {
            $content = $message['caption'];
        }
        
        // Handle forwarded messages
        if (isset($message['forward_from_chat'])) {
            $forwardInfo = "Forwarded from: " . ($message['forward_from_chat']['title'] ?? 'Unknown');
            $content = $forwardInfo . "\n\n" . $content;
        }
        
        return trim($content);
    }

    /**
     * Determine message type
     */
    private function determineMessageType(array $message): string
    {
        if (isset($message['photo'])) return 'photo';
        if (isset($message['video'])) return 'video';
        if (isset($message['document'])) return 'document';
        if (isset($message['audio'])) return 'audio';
        if (isset($message['voice'])) return 'voice';
        if (isset($message['video_note'])) return 'video_note';
        if (isset($message['sticker'])) return 'sticker';
        if (isset($message['animation'])) return 'animation';
        if (isset($message['poll'])) return 'poll';
        if (isset($message['venue'])) return 'venue';
        if (isset($message['location'])) return 'location';
        if (isset($message['contact'])) return 'contact';
        
        return 'text';
    }

    /**
     * Extract entities from message
     */
    private function extractEntities(array $message): array
    {
        $entities = [
            'hashtags' => [],
            'mentions' => [],
            'urls' => [],
            'cashtags' => [],
            'bot_commands' => []
        ];
        
        if (isset($message['entities'])) {
            $text = $message['text'] ?? $message['caption'] ?? '';
            
            foreach ($message['entities'] as $entity) {
                $entityText = mb_substr($text, $entity['offset'], $entity['length']);
                
                switch ($entity['type']) {
                    case 'hashtag':
                        $entities['hashtags'][] = $entityText;
                        break;
                    case 'mention':
                        $entities['mentions'][] = $entityText;
                        break;
                    case 'url':
                        $entities['urls'][] = $entityText;
                        break;
                    case 'text_link':
                        $entities['urls'][] = $entity['url'];
                        break;
                    case 'bot_command':
                        $entities['bot_commands'][] = $entityText;
                        break;
                    case 'cashtag':
                        $entities['cashtags'][] = $entityText;
                        break;
                }
            }
        }
        
        return $entities;
    }

    /**
     * Extract media information
     */
    private function extractMediaInfo(array $message): array
    {
        $media = [];
        
        if (isset($message['photo'])) {
            $photos = $message['photo'];
            $largestPhoto = end($photos); // Get the largest size
            $media['photo'] = [
                'file_id' => $largestPhoto['file_id'],
                'width' => $largestPhoto['width'],
                'height' => $largestPhoto['height'],
                'file_size' => $largestPhoto['file_size'] ?? null
            ];
        }
        
        if (isset($message['video'])) {
            $media['video'] = [
                'file_id' => $message['video']['file_id'],
                'width' => $message['video']['width'],
                'height' => $message['video']['height'],
                'duration' => $message['video']['duration'],
                'file_size' => $message['video']['file_size'] ?? null
            ];
        }
        
        if (isset($message['document'])) {
            $media['document'] = [
                'file_id' => $message['document']['file_id'],
                'file_name' => $message['document']['file_name'] ?? null,
                'mime_type' => $message['document']['mime_type'] ?? null,
                'file_size' => $message['document']['file_size'] ?? null
            ];
        }
        
        return $media;
    }

    /**
     * Build message URL
     */
    private function buildMessageUrl(array $chat, int $messageId): string
    {
        if (isset($chat['username'])) {
            return "https://t.me/{$chat['username']}/{$messageId}";
        }
        
        return "https://t.me/c/{$chat['id']}/{$messageId}";
    }

    /**
     * Find matching keywords in text
     */
    private function findMatchingKeywords(string $text, array $keywords): array
    {
        $textLower = strtolower($text);
        $matched = [];
        
        foreach ($keywords as $keyword) {
            if (stripos($textLower, strtolower($keyword)) !== false) {
                $matched[] = $keyword;
            }
        }
        
        return $matched;
    }

    /**
     * Apply filters to posts
     */
    private function applyFilters(array $posts): array
    {
        $filtered = [];
        $filters = $this->config['filters'];
        
        foreach ($posts as $post) {
            // Skip if doesn't meet minimum view threshold
            if ($post['metrics']['views'] < $filters['min_views']) {
                continue;
            }
            
            // Skip if doesn't meet minimum forwards threshold
            if ($post['metrics']['forwards'] < $filters['min_forwards']) {
                continue;
            }
            
            // Skip if media not included and media required
            if (!$filters['include_media'] && !empty($post['media'])) {
                continue;
            }
            
            // Skip if links not included and links present
            if (!$filters['include_links'] && !empty($post['entities']['urls'])) {
                continue;
            }
            
            $filtered[] = $post;
        }
        
        return $filtered;
    }

    /**
     * Remove duplicate posts
     */
    private function removeDuplicates(array $posts): array
    {
        $seen = [];
        $unique = [];
        
        foreach ($posts as $post) {
            $contentHash = hash('sha256', $post['content'] . $post['channel']);
            
            if (!isset($seen[$contentHash])) {
                $seen[$contentHash] = true;
                $unique[] = $post;
            }
        }
        
        return $unique;
    }

    /**
     * Make authenticated request to Telegram Bot API
     */
    private function makeRequest(string $method, array $params = []): array
    {
        $url = str_replace('{token}', $this->botToken, $this->config['endpoints']['api']) . '/' . $method;
        
        $response = Http::timeout(30)->post($url, $params);
        
        if (!$response->successful()) {
            $errorData = $response->json();
            $errorMessage = $errorData['description'] ?? 'Unknown Telegram API error';
            
            Log::error('Telegram API request failed', [
                'method' => $method,
                'status' => $response->status(),
                'error' => $errorMessage,
                'params' => $params
            ]);
            
            throw new Exception("Telegram API error: {$errorMessage} (HTTP {$response->status()})");
        }
        
        $data = $response->json();
        
        if (!($data['ok'] ?? false)) {
            $errorMessage = $data['description'] ?? 'Telegram API returned error';
            throw new Exception("Telegram API error: {$errorMessage}");
        }
        
        return $data;
    }

    /**
     * Health check for Telegram Bot API
     */
    public function healthCheck(): array
    {
        try {
            $response = $this->makeRequest('getMe');
            
            return [
                'status' => 'healthy',
                'bot_info' => $response['result'] ?? null
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get file download URL
     */
    public function getFileUrl(string $fileId): ?string
    {
        try {
            $response = $this->makeRequest('getFile', ['file_id' => $fileId]);
            
            if (isset($response['result']['file_path'])) {
                $filePath = $response['result']['file_path'];
                return str_replace('{token}', $this->botToken, $this->config['endpoints']['file_api']) . '/' . $filePath;
            }
            
        } catch (Exception $e) {
            Log::error('Failed to get Telegram file URL', [
                'file_id' => $fileId,
                'error' => $e->getMessage()
            ]);
        }
        
        return null;
    }

    /**
     * Send message to channel (if bot is admin)
     */
    public function sendMessage(string $chatId, string $text, array $options = []): ?array
    {
        try {
            $params = array_merge([
                'chat_id' => $chatId,
                'text' => $text
            ], $options);
            
            $response = $this->makeRequest('sendMessage', $params);
            
            return $response['result'] ?? null;
            
        } catch (Exception $e) {
            Log::error('Failed to send Telegram message', [
                'chat_id' => $chatId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get chat member count (if accessible)
     */
    public function getChatMemberCount(string $chatId): ?int
    {
        try {
            $response = $this->makeRequest('getChatMemberCount', ['chat_id' => $chatId]);
            
            return $response['result'] ?? null;
            
        } catch (Exception $e) {
            Log::warning('Failed to get chat member count', [
                'chat_id' => $chatId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Set webhook for receiving updates
     */
    public function setWebhook(string $url, array $options = []): bool
    {
        try {
            $params = array_merge(['url' => $url], $options);
            $response = $this->makeRequest('setWebhook', $params);
            
            return $response['ok'] ?? false;
            
        } catch (Exception $e) {
            Log::error('Failed to set Telegram webhook', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Delete webhook
     */
    public function deleteWebhook(): bool
    {
        try {
            $response = $this->makeRequest('deleteWebhook');
            return $response['ok'] ?? false;
            
        } catch (Exception $e) {
            Log::error('Failed to delete Telegram webhook', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}