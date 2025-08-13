<?php

declare(strict_types=1);

namespace App\Services\CrawlerMicroService\Engine;

use App\Models\CrawlerKeywordRule;
use App\Models\KeywordMatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Advanced keyword matching engine with fuzzy matching, stemming, and contextual analysis
 */
class KeywordEngine
{
    private array $config;
    private array $stopWords;
    private array $synonymMap;

    public function __construct()
    {
        $this->config = config('crawler_microservice.keyword_engine', []);
        $this->initializeStopWords();
        $this->initializeSynonymMap();
    }

    /**
     * Match content against keyword rules
     */
    public function matchContent(string $content, Collection $rules): array
    {
        $matches = [];
        $normalizedContent = $this->normalizeContent($content);
        $contentTokens = $this->tokenizeContent($normalizedContent);
        
        foreach ($rules as $rule) {
            $ruleMatches = $this->matchRule($contentTokens, $normalizedContent, $rule);
            if (!empty($ruleMatches)) {
                $matches[] = [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'priority' => $rule->priority,
                    'matches' => $ruleMatches,
                    'match_score' => $this->calculateMatchScore($ruleMatches),
                    'confidence' => $this->calculateConfidence($ruleMatches, $contentTokens)
                ];
            }
        }

        return $this->sortMatchesByRelevance($matches);
    }

    /**
     * Match single rule against content
     */
    public function matchRule(array $contentTokens, string $content, CrawlerKeywordRule $rule): array
    {
        $matches = [];
        $keywords = $rule->keywords ?? [];
        $conditions = $rule->conditions ?? [];

        foreach ($keywords as $keyword) {
            $keywordMatches = $this->findKeywordMatches($keyword, $contentTokens, $content);
            
            if (!empty($keywordMatches)) {
                // Apply rule conditions
                if ($this->applyRuleConditions($keywordMatches, $conditions, $content)) {
                    $matches = array_merge($matches, $keywordMatches);
                }
            }
        }

        return $this->deduplicateMatches($matches);
    }

    /**
     * Find matches for a specific keyword
     */
    private function findKeywordMatches(string $keyword, array $contentTokens, string $content): array
    {
        $matches = [];
        $normalizedKeyword = $this->normalizeKeyword($keyword);
        $keywordTokens = $this->tokenizeContent($normalizedKeyword);

        // Exact phrase matching
        if (count($keywordTokens) > 1) {
            $phraseMatches = $this->findPhraseMatches($keywordTokens, $contentTokens, $content);
            $matches = array_merge($matches, $phraseMatches);
        }

        // Individual keyword token matching
        foreach ($keywordTokens as $token) {
            $tokenMatches = $this->findTokenMatches($token, $contentTokens, $content);
            $matches = array_merge($matches, $tokenMatches);
        }

        // Fuzzy matching if enabled
        if ($this->config['matching_options']['fuzzy_matching'] ?? false) {
            $fuzzyMatches = $this->findFuzzyMatches($keyword, $content);
            $matches = array_merge($matches, $fuzzyMatches);
        }

        // Synonym matching
        $synonymMatches = $this->findSynonymMatches($keyword, $contentTokens, $content);
        $matches = array_merge($matches, $synonymMatches);

        return $matches;
    }

    /**
     * Find exact phrase matches
     */
    private function findPhraseMatches(array $keywordTokens, array $contentTokens, string $content): array
    {
        $matches = [];
        $phraseLength = count($keywordTokens);
        
        for ($i = 0; $i <= count($contentTokens) - $phraseLength; $i++) {
            $contentSlice = array_slice($contentTokens, $i, $phraseLength);
            
            if ($this->arraysMatch($keywordTokens, $contentSlice)) {
                $matches[] = [
                    'keyword' => implode(' ', $keywordTokens),
                    'matched_text' => implode(' ', $contentSlice),
                    'match_type' => 'phrase',
                    'position' => $i,
                    'confidence' => 0.95,
                    'context' => $this->extractContext($content, implode(' ', $contentSlice))
                ];
            }
        }

        return $matches;
    }

    /**
     * Find individual token matches
     */
    private function findTokenMatches(string $token, array $contentTokens, string $content): array
    {
        $matches = [];
        
        foreach ($contentTokens as $index => $contentToken) {
            if ($this->tokensMatch($token, $contentToken)) {
                $matches[] = [
                    'keyword' => $token,
                    'matched_text' => $contentToken,
                    'match_type' => 'token',
                    'position' => $index,
                    'confidence' => $this->calculateTokenConfidence($token, $contentToken),
                    'context' => $this->extractContext($content, $contentToken)
                ];
            }
        }

        return $matches;
    }

    /**
     * Find fuzzy matches using Levenshtein distance
     */
    private function findFuzzyMatches(string $keyword, string $content): array
    {
        $matches = [];
        $threshold = $this->config['matching_options']['fuzzy_threshold'] ?? 0.8;
        $words = preg_split('/\s+/', $content);

        foreach ($words as $word) {
            $similarity = $this->calculateSimilarity($keyword, $word);
            
            if ($similarity >= $threshold) {
                $matches[] = [
                    'keyword' => $keyword,
                    'matched_text' => $word,
                    'match_type' => 'fuzzy',
                    'position' => null,
                    'confidence' => $similarity,
                    'context' => $this->extractContext($content, $word)
                ];
            }
        }

        return $matches;
    }

    /**
     * Find synonym matches
     */
    private function findSynonymMatches(string $keyword, array $contentTokens, string $content): array
    {
        $matches = [];
        $synonyms = $this->getSynonyms($keyword);
        
        foreach ($synonyms as $synonym) {
            $synonymMatches = $this->findTokenMatches($synonym, $contentTokens, $content);
            foreach ($synonymMatches as $match) {
                $match['match_type'] = 'synonym';
                $match['original_keyword'] = $keyword;
                $match['confidence'] *= 0.8; // Reduce confidence for synonym matches
                $matches[] = $match;
            }
        }

        return $matches;
    }

    /**
     * Apply rule conditions to filter matches
     */
    private function applyRuleConditions(array $matches, array $conditions, string $content): bool
    {
        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            if (!$this->evaluateCondition($condition, $matches, $content)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate individual condition
     */
    private function evaluateCondition(array $condition, array $matches, string $content): bool
    {
        $type = $condition['type'] ?? '';
        
        return match ($type) {
            'min_matches' => count($matches) >= ($condition['value'] ?? 1),
            'max_matches' => count($matches) <= ($condition['value'] ?? 100),
            'context_required' => $this->hasRequiredContext($matches, $condition['context'] ?? []),
            'exclude_words' => !$this->containsExcludedWords($content, $condition['words'] ?? []),
            'min_confidence' => $this->getAverageConfidence($matches) >= ($condition['value'] ?? 0.5),
            'content_length' => strlen($content) >= ($condition['min_length'] ?? 0) && 
                               strlen($content) <= ($condition['max_length'] ?? 10000),
            default => true
        };
    }

    /**
     * Calculate match score for sorting
     */
    private function calculateMatchScore(array $matches): float
    {
        if (empty($matches)) {
            return 0.0;
        }

        $totalScore = 0;
        foreach ($matches as $match) {
            $confidence = $match['confidence'];
            $typeMultiplier = match ($match['match_type']) {
                'phrase' => 1.0,
                'token' => 0.8,
                'synonym' => 0.6,
                'fuzzy' => 0.4,
                default => 0.5
            };
            
            $totalScore += $confidence * $typeMultiplier;
        }

        return round($totalScore / count($matches), 3);
    }

    /**
     * Calculate confidence score
     */
    private function calculateConfidence(array $matches, array $contentTokens): float
    {
        if (empty($matches) || empty($contentTokens)) {
            return 0.0;
        }

        $totalConfidence = 0;
        foreach ($matches as $match) {
            $totalConfidence += $match['confidence'];
        }

        // Normalize by content length and match count
        $averageConfidence = $totalConfidence / count($matches);
        $coverageBonus = min(count($matches) / count($contentTokens), 0.5);
        
        return round(min($averageConfidence + $coverageBonus, 1.0), 3);
    }

    /**
     * Normalize content for matching
     */
    private function normalizeContent(string $content): string
    {
        // Convert to lowercase if not case sensitive
        if (!($this->config['matching_options']['case_sensitive'] ?? false)) {
            $content = strtolower($content);
        }

        // Remove extra whitespace
        $content = preg_replace('/\s+/', ' ', trim($content));
        
        // Remove punctuation but preserve hashtags and mentions
        $content = preg_replace('/[^\w\s#@]/', ' ', $content);
        
        return $content;
    }

    /**
     * Normalize keyword for matching
     */
    private function normalizeKeyword(string $keyword): string
    {
        return $this->normalizeContent($keyword);
    }

    /**
     * Tokenize content into words
     */
    private function tokenizeContent(string $content): array
    {
        $tokens = preg_split('/\s+/', trim($content));
        $tokens = array_filter($tokens, fn($token) => strlen($token) >= ($this->config['matching_options']['min_word_length'] ?? 2));
        
        // Remove stop words if configured
        if ($this->config['matching_options']['remove_stop_words'] ?? false) {
            $tokens = array_filter($tokens, fn($token) => !in_array($token, $this->stopWords));
        }

        // Apply stemming if enabled
        if ($this->config['matching_options']['stemming_enabled'] ?? false) {
            $tokens = array_map([$this, 'stemWord'], $tokens);
        }

        return array_values($tokens);
    }

    /**
     * Check if arrays match (for phrase matching)
     */
    private function arraysMatch(array $array1, array $array2): bool
    {
        if (count($array1) !== count($array2)) {
            return false;
        }

        for ($i = 0; $i < count($array1); $i++) {
            if (!$this->tokensMatch($array1[$i], $array2[$i])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if two tokens match
     */
    private function tokensMatch(string $token1, string $token2): bool
    {
        if ($token1 === $token2) {
            return true;
        }

        // Stemming comparison
        if ($this->config['matching_options']['stemming_enabled'] ?? false) {
            return $this->stemWord($token1) === $this->stemWord($token2);
        }

        return false;
    }

    /**
     * Calculate similarity between two strings
     */
    private function calculateSimilarity(string $str1, string $str2): float
    {
        $maxLength = max(strlen($str1), strlen($str2));
        if ($maxLength === 0) {
            return 1.0;
        }

        $distance = levenshtein($str1, $str2);
        return 1.0 - ($distance / $maxLength);
    }

    /**
     * Calculate token confidence
     */
    private function calculateTokenConfidence(string $keyword, string $token): float
    {
        if ($keyword === $token) {
            return 1.0;
        }

        return $this->calculateSimilarity($keyword, $token);
    }

    /**
     * Extract context around matched text
     */
    private function extractContext(string $content, string $matchedText): string
    {
        $contextLength = 100; // Characters before and after
        $position = stripos($content, $matchedText);
        
        if ($position === false) {
            return '';
        }

        $start = max(0, $position - $contextLength);
        $end = min(strlen($content), $position + strlen($matchedText) + $contextLength);
        
        return substr($content, $start, $end - $start);
    }

    /**
     * Get synonyms for a keyword
     */
    private function getSynonyms(string $keyword): array
    {
        return $this->synonymMap[strtolower($keyword)] ?? [];
    }

    /**
     * Sort matches by relevance
     */
    private function sortMatchesByRelevance(array $matches): array
    {
        usort($matches, function ($a, $b) {
            // Sort by match score, then by priority
            $scoreCompare = $b['match_score'] <=> $a['match_score'];
            if ($scoreCompare !== 0) {
                return $scoreCompare;
            }

            $priorityMap = ['urgent' => 4, 'high' => 3, 'normal' => 2, 'low' => 1];
            $aPriority = $priorityMap[$a['priority']] ?? 2;
            $bPriority = $priorityMap[$b['priority']] ?? 2;
            
            return $bPriority <=> $aPriority;
        });

        return $matches;
    }

    /**
     * Remove duplicate matches
     */
    private function deduplicateMatches(array $matches): array
    {
        $unique = [];
        $seen = [];
        
        foreach ($matches as $match) {
            $key = $match['keyword'] . '|' . $match['matched_text'] . '|' . $match['match_type'];
            
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $match;
            }
        }

        return $unique;
    }

    /**
     * Check if matches have required context
     */
    private function hasRequiredContext(array $matches, array $requiredContext): bool
    {
        foreach ($matches as $match) {
            $context = strtolower($match['context'] ?? '');
            
            foreach ($requiredContext as $contextWord) {
                if (stripos($context, strtolower($contextWord)) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if content contains excluded words
     */
    private function containsExcludedWords(string $content, array $excludedWords): bool
    {
        $normalizedContent = strtolower($content);
        
        foreach ($excludedWords as $word) {
            if (stripos($normalizedContent, strtolower($word)) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get average confidence of matches
     */
    private function getAverageConfidence(array $matches): float
    {
        if (empty($matches)) {
            return 0.0;
        }

        $total = array_sum(array_column($matches, 'confidence'));
        return $total / count($matches);
    }

    /**
     * Simple word stemming (basic implementation)
     */
    private function stemWord(string $word): string
    {
        // Basic English stemming rules
        $word = strtolower($word);
        
        // Remove common suffixes
        $suffixes = ['ing', 'ed', 'er', 'est', 'ly', 'tion', 'sion', 'ness', 'ment'];
        
        foreach ($suffixes as $suffix) {
            if (Str::endsWith($word, $suffix) && strlen($word) > strlen($suffix) + 2) {
                return substr($word, 0, -strlen($suffix));
            }
        }

        return $word;
    }

    /**
     * Initialize stop words
     */
    private function initializeStopWords(): void
    {
        $this->stopWords = [
            'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from', 'has', 'he',
            'in', 'is', 'it', 'its', 'of', 'on', 'that', 'the', 'to', 'was', 'were',
            'will', 'with', 'the', 'this', 'but', 'they', 'have', 'had', 'what', 'said',
            'each', 'which', 'she', 'do', 'how', 'their', 'if', 'up', 'out', 'many',
            'then', 'them', 'these', 'so', 'some', 'her', 'would', 'make', 'like',
            'him', 'into', 'time', 'two', 'more', 'go', 'no', 'way', 'could', 'my',
            'than', 'first', 'been', 'call', 'who', 'oil', 'sit', 'now', 'find',
            'long', 'down', 'day', 'did', 'get', 'come', 'made', 'may', 'part'
        ];
    }

    /**
     * Initialize synonym mapping
     */
    private function initializeSynonymMap(): void
    {
        $this->synonymMap = [
            'hack' => ['exploit', 'attack', 'breach', 'compromise'],
            'vulnerability' => ['weakness', 'flaw', 'bug', 'security issue'],
            'smart contract' => ['contract', 'dapp', 'decentralized application'],
            'cryptocurrency' => ['crypto', 'digital currency', 'coin', 'token'],
            'blockchain' => ['distributed ledger', 'dlt', 'chain'],
            'ethereum' => ['eth', 'ether'],
            'bitcoin' => ['btc', 'satoshi'],
            'defi' => ['decentralized finance', 'yield farming', 'liquidity mining'],
            'nft' => ['non-fungible token', 'digital collectible'],
            'dao' => ['decentralized autonomous organization']
        ];
    }
}