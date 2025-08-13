<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Free Sentiment Analysis Service
 * Replaces Google Cloud NLP with open-source alternatives
 */
final class FreeSentimentAnalyzer
{
    private array $positiveWords;
    private array $negativeWords;
    private array $intensifiers;
    private array $negations;

    public function __construct()
    {
        $this->loadSentimentLexicons();
    }

    /**
     * Analyze sentiment of text using VADER-like algorithm
     */
    public function analyzeSentiment(string $text): array
    {
        $cacheKey = 'free_sentiment_' . md5($text);
        
        return Cache::remember($cacheKey, 1800, function () use ($text) {
            $analysis = $this->performSentimentAnalysis($text);
            
            return [
                'success' => true,
                'method' => 'free_vader_algorithm',
                'sentiment_score' => $analysis['compound'],
                'magnitude' => $analysis['magnitude'],
                'positive' => $analysis['positive'],
                'negative' => $analysis['negative'],
                'neutral' => $analysis['neutral'],
                'classification' => $this->classifySentiment($analysis['compound']),
                'confidence' => $this->calculateConfidence($analysis),
                'entities' => $this->extractEntities($text),
                'keywords' => $this->extractKeywords($text),
                'cost' => 0.00, // FREE!
                'processing_time' => microtime(true) - LARAVEL_START
            ];
        });
    }

    /**
     * Batch analyze multiple texts
     */
    public function batchAnalyze(array $texts): array
    {
        $results = [];
        
        foreach ($texts as $index => $text) {
            $results[$index] = $this->analyzeSentiment($text);
        }

        return [
            'success' => true,
            'method' => 'free_batch_analysis',
            'results' => $results,
            'total_processed' => count($texts),
            'cost' => 0.00,
            'average_processing_time' => 0
        ];
    }

    /**
     * Perform VADER-like sentiment analysis
     */
    private function performSentimentAnalysis(string $text): array
    {
        $text = $this->preprocessText($text);
        $words = $this->tokenizeText($text);
        
        $scores = [
            'positive' => 0.0,
            'negative' => 0.0,
            'neutral' => 0.0
        ];

        $wordCount = 0;
        $intensity = 1.0;

        for ($i = 0; $i < count($words); $i++) {
            $word = strtolower($words[$i]);
            
            // Check for negations
            if ($this->isNegation($word)) {
                $intensity = -1.0;
                continue;
            }

            // Check for intensifiers
            if ($this->isIntensifier($word)) {
                $intensity *= 1.5;
                continue;
            }

            // Get word sentiment
            $wordSentiment = $this->getWordSentiment($word);
            
            if ($wordSentiment !== 0) {
                $adjustedSentiment = $wordSentiment * $intensity;
                
                if ($adjustedSentiment > 0) {
                    $scores['positive'] += $adjustedSentiment;
                } else {
                    $scores['negative'] += abs($adjustedSentiment);
                }
                
                $wordCount++;
            } else {
                $scores['neutral'] += 0.1;
            }

            // Reset intensity after processing sentiment word
            if ($wordSentiment !== 0) {
                $intensity = 1.0;
            }
        }

        // Normalize scores
        $total = $scores['positive'] + $scores['negative'] + $scores['neutral'];
        
        if ($total > 0) {
            $scores['positive'] = $scores['positive'] / $total;
            $scores['negative'] = $scores['negative'] / $total;
            $scores['neutral'] = $scores['neutral'] / $total;
        }

        // Calculate compound score (similar to VADER)
        $compound = $this->calculateCompoundScore($scores['positive'], $scores['negative']);
        
        return [
            'positive' => round($scores['positive'], 3),
            'negative' => round($scores['negative'], 3),
            'neutral' => round($scores['neutral'], 3),
            'compound' => round($compound, 3),
            'magnitude' => round(abs($compound), 3)
        ];
    }

    /**
     * Calculate compound sentiment score
     */
    private function calculateCompoundScore(float $positive, float $negative): float
    {
        $score = $positive - $negative;
        
        // Normalize to -1 to 1 range
        $normalizedScore = $score / sqrt(($score * $score) + 1);
        
        return $normalizedScore;
    }

    /**
     * Classify sentiment based on compound score
     */
    private function classifySentiment(float $compound): string
    {
        if ($compound >= 0.5) return 'VERY_POSITIVE';
        if ($compound >= 0.1) return 'POSITIVE';
        if ($compound <= -0.5) return 'VERY_NEGATIVE';
        if ($compound <= -0.1) return 'NEGATIVE';
        return 'NEUTRAL';
    }

    /**
     * Calculate confidence score
     */
    private function calculateConfidence(array $analysis): float
    {
        $magnitude = $analysis['magnitude'];
        $maxScore = max($analysis['positive'], $analysis['negative'], $analysis['neutral']);
        
        return round(($magnitude + $maxScore) / 2, 3);
    }

    /**
     * Extract entities from text (simple implementation)
     */
    private function extractEntities(string $text): array
    {
        $entities = [];
        
        // Extract mentions (@username)
        preg_match_all('/@([a-zA-Z0-9_]+)/', $text, $mentions);
        foreach ($mentions[1] as $mention) {
            $entities[] = [
                'type' => 'PERSON',
                'name' => '@' . $mention,
                'salience' => 0.5
            ];
        }

        // Extract hashtags (#hashtag)
        preg_match_all('/#([a-zA-Z0-9_]+)/', $text, $hashtags);
        foreach ($hashtags[1] as $hashtag) {
            $entities[] = [
                'type' => 'OTHER',
                'name' => '#' . $hashtag,
                'salience' => 0.3
            ];
        }

        // Extract crypto symbols ($BTC, $ETH, etc.)
        preg_match_all('/\$([A-Z]{2,10})/', $text, $cryptos);
        foreach ($cryptos[1] as $crypto) {
            $entities[] = [
                'type' => 'CONSUMER_GOOD',
                'name' => '$' . $crypto,
                'salience' => 0.8
            ];
        }

        return $entities;
    }

    /**
     * Extract keywords from text
     */
    private function extractKeywords(string $text): array
    {
        $text = $this->preprocessText($text);
        $words = $this->tokenizeText($text);
        
        // Remove common stop words
        $stopWords = ['the', 'is', 'at', 'which', 'on', 'and', 'a', 'an', 'as', 'are', 'was', 'were', 'been', 'be', 'to', 'of', 'for', 'with', 'by', 'in', 'it', 'this', 'that'];
        
        $keywords = array_diff($words, $stopWords);
        $wordFreq = array_count_values($keywords);
        
        // Sort by frequency and take top keywords
        arsort($wordFreq);
        $topKeywords = array_slice(array_keys($wordFreq), 0, 10);
        
        return array_map(function($keyword) use ($wordFreq) {
            return [
                'word' => $keyword,
                'frequency' => $wordFreq[$keyword],
                'sentiment' => $this->getWordSentiment($keyword)
            ];
        }, $topKeywords);
    }

    /**
     * Preprocess text for analysis
     */
    private function preprocessText(string $text): string
    {
        // Convert to lowercase
        $text = strtolower($text);
        
        // Handle contractions
        $contractions = [
            "n't" => " not",
            "'re" => " are",
            "'ve" => " have",
            "'ll" => " will",
            "'d" => " would",
            "'m" => " am"
        ];
        
        foreach ($contractions as $contraction => $expansion) {
            $text = str_replace($contraction, $expansion, $text);
        }
        
        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }

    /**
     * Tokenize text into words
     */
    private function tokenizeText(string $text): array
    {
        return preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * Get sentiment score for a word
     */
    private function getWordSentiment(string $word): float
    {
        if (in_array($word, $this->positiveWords)) {
            return 1.0;
        }
        
        if (in_array($word, $this->negativeWords)) {
            return -1.0;
        }
        
        return 0.0;
    }

    /**
     * Check if word is a negation
     */
    private function isNegation(string $word): bool
    {
        return in_array($word, $this->negations);
    }

    /**
     * Check if word is an intensifier
     */
    private function isIntensifier(string $word): bool
    {
        return in_array($word, $this->intensifiers);
    }

    /**
     * Load sentiment lexicons
     */
    private function loadSentimentLexicons(): void
    {
        $this->positiveWords = [
            'amazing', 'awesome', 'brilliant', 'excellent', 'fantastic', 'great', 'incredible',
            'outstanding', 'perfect', 'wonderful', 'good', 'nice', 'love', 'like', 'happy',
            'excited', 'thrilled', 'satisfied', 'pleased', 'delighted', 'impressed', 'glad',
            'bullish', 'moon', 'pump', 'gains', 'profit', 'success', 'win', 'winner',
            'diamond', 'hands', 'hodl', 'buying', 'accumulating', 'strong', 'solid'
        ];

        $this->negativeWords = [
            'awful', 'terrible', 'horrible', 'bad', 'worst', 'hate', 'dislike', 'angry',
            'disappointed', 'frustrated', 'upset', 'sad', 'unhappy', 'worried', 'concerned',
            'bearish', 'dump', 'crash', 'scam', 'rug', 'loss', 'losses', 'fail', 'failure',
            'weak', 'selling', 'panic', 'fear', 'fud', 'dead', 'rekt', 'liquidated'
        ];

        $this->intensifiers = [
            'very', 'extremely', 'incredibly', 'absolutely', 'totally', 'completely',
            'really', 'quite', 'highly', 'super', 'ultra', 'mega'
        ];

        $this->negations = [
            'not', 'no', 'never', 'none', 'nothing', 'neither', 'nowhere', 'nobody',
            "can't", "won't", "shouldn't", "wouldn't", "couldn't", "doesn't", "don't",
            "isn't", "aren't", "wasn't", "weren't", "haven't", "hasn't", "hadn't"
        ];
    }
}
