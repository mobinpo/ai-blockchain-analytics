<?php

declare(strict_types=1);

namespace App\Services\SocialCrawler;

use Illuminate\Support\Facades\Log;

class KeywordEngine
{
    /**
     * Match keywords in content
     */
    public function matchKeywords(string $content, array $keywords, array $options = []): array
    {
        $matches = [];
        $caseSensitive = $options['case_sensitive'] ?? false;
        $wholeWords = $options['whole_words'] ?? false;
        
        $searchContent = $caseSensitive ? $content : strtolower($content);
        
        foreach ($keywords as $keyword) {
            $searchKeyword = $caseSensitive ? $keyword : strtolower($keyword);
            
            if ($wholeWords) {
                $pattern = '/\b' . preg_quote($searchKeyword, '/') . '\b/';
                $matchCount = preg_match_all($pattern, $searchContent, $positions, PREG_OFFSET_CAPTURE);
            } else {
                $matchCount = substr_count($searchContent, $searchKeyword);
                $positions = [];
                
                if ($matchCount > 0) {
                    $offset = 0;
                    while (($pos = strpos($searchContent, $searchKeyword, $offset)) !== false) {
                        $positions[0][] = [$searchKeyword, $pos];
                        $offset = $pos + 1;
                    }
                }
            }
            
            if ($matchCount > 0) {
                $matches[] = [
                    'keyword' => $keyword,
                    'count' => $matchCount,
                    'positions' => isset($positions[0]) ? array_column($positions[0], 1) : [],
                    'confidence' => $this->calculateConfidence($keyword, $content, $matchCount)
                ];
            }
        }
        
        return $matches;
    }
    
    /**
     * Calculate confidence score for keyword match
     */
    private function calculateConfidence(string $keyword, string $content, int $matchCount): float
    {
        $contentLength = strlen($content);
        $keywordLength = strlen($keyword);
        
        // Base confidence based on match frequency
        $frequency = $matchCount / max(1, $contentLength / 100);
        $baseConfidence = min(0.8, $frequency * 0.2);
        
        // Boost for longer keywords (more specific)
        $lengthBoost = min(0.2, $keywordLength / 50);
        
        // Boost for exact word boundaries
        $boundaryBoost = 0;
        if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $content)) {
            $boundaryBoost = 0.1;
        }
        
        return min(1.0, $baseConfidence + $lengthBoost + $boundaryBoost);
    }
    
    /**
     * Extract entities from content
     */
    public function extractEntities(string $content): array
    {
        $entities = [];
        
        // Extract cryptocurrency symbols (simplified)
        $cryptoPattern = '/\b(BTC|ETH|USDT|USDC|BNB|XRP|ADA|SOL|DOGE|AVAX|DOT|MATIC|LINK|UNI|LTC|ALGO)\b/i';
        if (preg_match_all($cryptoPattern, $content, $matches)) {
            $entities['cryptocurrencies'] = array_unique($matches[0]);
        }
        
        // Extract wallet addresses (simplified)
        $addressPattern = '/\b0x[a-fA-F0-9]{40}\b/';
        if (preg_match_all($addressPattern, $content, $matches)) {
            $entities['addresses'] = array_unique($matches[0]);
        }
        
        // Extract URLs
        $urlPattern = '/https?:\/\/[^\s]+/';
        if (preg_match_all($urlPattern, $content, $matches)) {
            $entities['urls'] = array_unique($matches[0]);
        }
        
        // Extract hashtags
        $hashtagPattern = '/#[a-zA-Z0-9_]+/';
        if (preg_match_all($hashtagPattern, $content, $matches)) {
            $entities['hashtags'] = array_unique($matches[0]);
        }
        
        // Extract mentions
        $mentionPattern = '/@[a-zA-Z0-9_]+/';
        if (preg_match_all($mentionPattern, $content, $matches)) {
            $entities['mentions'] = array_unique($matches[0]);
        }
        
        return $entities;
    }
    
    /**
     * Calculate sentiment score (basic implementation)
     */
    public function calculateSentiment(string $content): float
    {
        $positiveWords = [
            'good', 'great', 'excellent', 'amazing', 'awesome', 'bullish', 'moon', 'pump',
            'gain', 'profit', 'up', 'rise', 'surge', 'breakout', 'hodl', 'buy', 'long'
        ];
        
        $negativeWords = [
            'bad', 'terrible', 'awful', 'bearish', 'dump', 'crash', 'loss', 'down',
            'fall', 'drop', 'sell', 'short', 'fud', 'scam', 'rug', 'hack', 'exploit'
        ];
        
        $content = strtolower($content);
        $words = str_word_count($content, 1);
        
        $positiveCount = 0;
        $negativeCount = 0;
        
        foreach ($words as $word) {
            if (in_array($word, $positiveWords)) {
                $positiveCount++;
            } elseif (in_array($word, $negativeWords)) {
                $negativeCount++;
            }
        }
        
        $totalSentimentWords = $positiveCount + $negativeCount;
        
        if ($totalSentimentWords === 0) {
            return 0.0; // Neutral
        }
        
        // Calculate sentiment score between -1 and 1
        return ($positiveCount - $negativeCount) / max(1, count($words) / 10);
    }
    
    /**
     * Filter content based on rules
     */
    public function filterContent(string $content, array $rules): bool
    {
        foreach ($rules as $rule) {
            $type = $rule['type'] ?? 'contains';
            $value = $rule['value'] ?? '';
            $caseSensitive = $rule['case_sensitive'] ?? false;
            
            $searchContent = $caseSensitive ? $content : strtolower($content);
            $searchValue = $caseSensitive ? $value : strtolower($value);
            
            switch ($type) {
                case 'contains':
                    if (strpos($searchContent, $searchValue) !== false) {
                        return false; // Filter out
                    }
                    break;
                    
                case 'not_contains':
                    if (strpos($searchContent, $searchValue) === false) {
                        return false; // Filter out
                    }
                    break;
                    
                case 'regex':
                    if (preg_match($value, $content)) {
                        return false; // Filter out
                    }
                    break;
                    
                case 'min_length':
                    if (strlen($content) < (int)$value) {
                        return false; // Filter out
                    }
                    break;
                    
                case 'max_length':
                    if (strlen($content) > (int)$value) {
                        return false; // Filter out
                    }
                    break;
            }
        }
        
        return true; // Keep content
    }
    
    /**
     * Get keyword suggestions based on content
     */
    public function suggestKeywords(string $content, int $limit = 10): array
    {
        // Simple keyword extraction based on word frequency
        $content = strtolower($content);
        $content = preg_replace('/[^a-z0-9\s]/', ' ', $content);
        $words = str_word_count($content, 1);
        
        // Filter out common stop words
        $stopWords = [
            'the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with',
            'by', 'from', 'up', 'about', 'into', 'through', 'during', 'before',
            'after', 'above', 'below', 'between', 'among', 'this', 'that', 'these',
            'those', 'i', 'me', 'my', 'myself', 'we', 'our', 'ours', 'ourselves',
            'you', 'your', 'yours', 'yourself', 'yourselves', 'he', 'him', 'his',
            'himself', 'she', 'her', 'hers', 'herself', 'it', 'its', 'itself',
            'they', 'them', 'their', 'theirs', 'themselves', 'what', 'which',
            'who', 'whom', 'whose', 'this', 'that', 'these', 'those', 'am', 'is',
            'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had',
            'having', 'do', 'does', 'did', 'doing', 'will', 'would', 'could',
            'should', 'may', 'might', 'must', 'can', 'shall', 'a', 'an'
        ];
        
        $words = array_filter($words, function($word) use ($stopWords) {
            return strlen($word) > 2 && !in_array($word, $stopWords);
        });
        
        $wordCounts = array_count_values($words);
        arsort($wordCounts);
        
        return array_slice(array_keys($wordCounts), 0, $limit);
    }
}