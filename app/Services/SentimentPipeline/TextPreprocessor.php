<?php

declare(strict_types=1);

namespace App\Services\SentimentPipeline;

use App\Models\TextPreprocessingCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class TextPreprocessor
{
    protected array $config;

    public function __construct()
    {
        $this->config = config('sentiment_pipeline.preprocessing', []);
    }

    public function processText(string $text): string
    {
        // Check cache first
        $contentHash = hash('sha256', $text);
        $cached = $this->getCachedProcessedText($contentHash);
        
        if ($cached) {
            return $cached;
        }

        $processedText = $this->performTextProcessing($text);
        
        // Cache the result
        $this->cacheProcessedText($contentHash, $text, $processedText);
        
        return $processedText;
    }

    protected function performTextProcessing(string $text): string
    {
        $steps = [];
        $processedText = $text;

        // 1. Remove URLs
        if ($this->config['remove_urls'] ?? true) {
            $processedText = preg_replace('/https?:\/\/[^\s]+/i', '', $processedText);
            $steps[] = 'remove_urls';
        }

        // 2. Remove email addresses
        if ($this->config['remove_emails'] ?? true) {
            $processedText = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '', $processedText);
            $steps[] = 'remove_emails';
        }

        // 3. Remove mentions and hashtags (for social media)
        if ($this->config['clean_social_markers'] ?? true) {
            $processedText = preg_replace('/@\w+|#\w+/', '', $processedText);
            $steps[] = 'clean_social_markers';
        }

        // 4. Remove excessive whitespace
        if ($this->config['normalize_whitespace'] ?? true) {
            $processedText = preg_replace('/\s+/', ' ', $processedText);
            $processedText = trim($processedText);
            $steps[] = 'normalize_whitespace';
        }

        // 5. Remove special characters (but keep basic punctuation)
        if ($this->config['remove_special_chars'] ?? true) {
            $processedText = preg_replace('/[^\w\s\.,!?;:()-]/', '', $processedText);
            $steps[] = 'remove_special_chars';
        }

        // 6. Convert to lowercase (optional)
        if ($this->config['to_lowercase'] ?? false) {
            $processedText = strtolower($processedText);
            $steps[] = 'to_lowercase';
        }

        // 7. Remove very short words (less than 2 characters)
        if ($this->config['remove_short_words'] ?? true) {
            $words = explode(' ', $processedText);
            $words = array_filter($words, function($word) {
                return strlen(trim($word)) >= 2;
            });
            $processedText = implode(' ', $words);
            $steps[] = 'remove_short_words';
        }

        // 8. Remove common stop words (basic implementation)
        if ($this->config['remove_stopwords'] ?? false) {
            $processedText = $this->removeStopwords($processedText);
            $steps[] = 'remove_stopwords';
        }

        // 9. Trim and ensure minimum length
        $processedText = trim($processedText);
        
        // Log preprocessing steps for debugging
        if ($this->config['log_steps'] ?? false) {
            Log::debug('Text preprocessing completed', [
                'original_length' => strlen($text),
                'processed_length' => strlen($processedText),
                'steps' => $steps,
            ]);
        }

        return $processedText;
    }

    protected function removeStopwords(string $text): string
    {
        $stopwords = [
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by',
            'is', 'are', 'was', 'were', 'be', 'been', 'have', 'has', 'had', 'do', 'does', 'did',
            'will', 'would', 'should', 'could', 'can', 'may', 'might', 'must', 'shall',
            'this', 'that', 'these', 'those', 'i', 'you', 'he', 'she', 'it', 'we', 'they',
            'me', 'him', 'her', 'us', 'them', 'my', 'your', 'his', 'her', 'its', 'our', 'their'
        ];

        $words = explode(' ', strtolower($text));
        $filteredWords = array_filter($words, function($word) use ($stopwords) {
            return !in_array(trim($word), $stopwords);
        });

        return implode(' ', $filteredWords);
    }

    protected function getCachedProcessedText(string $contentHash): ?string
    {
        try {
            $cached = TextPreprocessingCache::where('content_hash', $contentHash)->first();
            
            if ($cached) {
                // Update last used timestamp
                $cached->update(['last_used_at' => now()]);
                return $cached->processed_text;
            }
        } catch (\Exception $e) {
            Log::warning('Failed to retrieve cached processed text', [
                'content_hash' => $contentHash,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    protected function cacheProcessedText(string $contentHash, string $originalText, string $processedText): void
    {
        try {
            TextPreprocessingCache::updateOrCreate(
                ['content_hash' => $contentHash],
                [
                    'original_text' => $originalText,
                    'processed_text' => $processedText,
                    'last_used_at' => now(),
                ]
            );
        } catch (\Exception $e) {
            Log::warning('Failed to cache processed text', [
                'content_hash' => $contentHash,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function detectLanguage(string $text): ?string
    {
        // Basic language detection - you could integrate with Google Cloud Translation API
        // or use a more sophisticated language detection library
        
        // Simple heuristic based on character patterns
        if (preg_match('/[\x{0080}-\x{FFFF}]/u', $text)) {
            // Contains non-ASCII characters, might be non-English
            // This is very basic - consider using proper language detection
            return null; // Unknown
        }

        return 'en'; // Default to English
    }

    public function cleanupOldCache(): int
    {
        $cleanupDays = $this->config['cache_cleanup_days'] ?? 30;
        $cutoffDate = now()->subDays($cleanupDays);

        $deletedCount = TextPreprocessingCache::where('last_used_at', '<', $cutoffDate)->delete();

        if ($deletedCount > 0) {
            Log::info('Cleaned up old text preprocessing cache', [
                'deleted_count' => $deletedCount,
                'cutoff_date' => $cutoffDate->toDateString(),
            ]);
        }

        return $deletedCount;
    }

    public function getProcessingStats(): array
    {
        return [
            'total_cached_items' => TextPreprocessingCache::count(),
            'cache_size_mb' => $this->getCacheSizeInMB(),
            'oldest_cache_entry' => TextPreprocessingCache::min('created_at'),
            'newest_cache_entry' => TextPreprocessingCache::max('created_at'),
        ];
    }

    protected function getCacheSizeInMB(): float
    {
        $totalSize = TextPreprocessingCache::sum(\DB::raw('LENGTH(original_text) + LENGTH(processed_text)'));
        return round($totalSize / (1024 * 1024), 2);
    }
}