<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Enhanced Online Sentiment Analysis Service
 * Combines free local analysis with free online services
 */
final class EnhancedOnlineSentimentService
{
    private FreeSentimentAnalyzer $localAnalyzer;
    private array $onlineServices;
    private int $timeout;

    public function __construct(FreeSentimentAnalyzer $localAnalyzer)
    {
        $this->localAnalyzer = $localAnalyzer;
        $this->timeout = 30;
        
        // Free online sentiment APIs
        $this->onlineServices = [
            'meaningcloud' => [
                'url' => 'https://api.meaningcloud.com/sentiment-2.1',
                'key' => config('services.meaningcloud.api_key', env('MEANINGCLOUD_API_KEY', '')),
                'free_limit' => '20,000 requests/month',
                'enabled' => true
            ],
            'text_processing' => [
                'url' => 'http://text-processing.com/api/sentiment/',
                'key' => null, // No API key required
                'free_limit' => '1,000 requests/day',
                'enabled' => true
            ],
            'sentiment140' => [
                'url' => 'http://www.sentiment140.com/api/classify',
                'key' => null, // No API key required
                'free_limit' => 'unlimited',
                'enabled' => true
            ],
            'paralleldots' => [
                'url' => 'https://apis.paralleldots.com/v4/sentiment',
                'key' => config('services.paralleldots.api_key', env('PARALLELDOTS_API_KEY', '')),
                'free_limit' => '1,000 requests/month',
                'enabled' => false // Enable if you have API key
            ]
        ];
    }

    /**
     * Analyze sentiment using multiple online and local sources
     */
    public function analyzeSentiment(string $text): array
    {
        $cacheKey = 'enhanced_sentiment_' . md5($text);
        
        return Cache::remember($cacheKey, 1800, function () use ($text) {
            $results = [];
            
            // 1. Local VADER analysis (always available)
            $localResult = $this->localAnalyzer->analyzeSentiment($text);
            $results['local_vader'] = $localResult;
            
            // 2. Try online services
            $onlineResults = $this->getOnlineAnalysis($text);
            $results = array_merge($results, $onlineResults);
            
            // 3. Aggregate results
            $aggregated = $this->aggregateResults($results);
            
            return [
                'success' => true,
                'method' => 'enhanced_multi_source',
                'sentiment_score' => $aggregated['sentiment_score'],
                'magnitude' => $aggregated['magnitude'],
                'confidence' => $aggregated['confidence'],
                'classification' => $aggregated['classification'],
                'individual_results' => $results,
                'sources_used' => array_keys($results),
                'entities' => $localResult['entities'] ?? [],
                'keywords' => $localResult['keywords'] ?? [],
                'cost' => 0.00, // All free services
                'processing_time' => microtime(true) - LARAVEL_START
            ];
        });
    }

    /**
     * Get analysis from online services
     */
    private function getOnlineAnalysis(string $text): array
    {
        $results = [];
        
        // Try Text-Processing.com (no API key required)
        try {
            $textProcessingResult = $this->analyzeWithTextProcessing($text);
            if ($textProcessingResult) {
                $results['text_processing'] = $textProcessingResult;
            }
        } catch (\Exception $e) {
            Log::warning('Text-Processing.com failed', ['error' => $e->getMessage()]);
        }

        // Try Sentiment140 (no API key required)
        try {
            $sentiment140Result = $this->analyzeWithSentiment140($text);
            if ($sentiment140Result) {
                $results['sentiment140'] = $sentiment140Result;
            }
        } catch (\Exception $e) {
            Log::warning('Sentiment140 failed', ['error' => $e->getMessage()]);
        }

        // Try MeaningCloud (if API key available)
        if (!empty($this->onlineServices['meaningcloud']['key'])) {
            try {
                $meaningCloudResult = $this->analyzeWithMeaningCloud($text);
                if ($meaningCloudResult) {
                    $results['meaningcloud'] = $meaningCloudResult;
                }
            } catch (\Exception $e) {
                Log::warning('MeaningCloud failed', ['error' => $e->getMessage()]);
            }
        }

        // Try ParallelDots (if API key available and enabled)
        if ($this->onlineServices['paralleldots']['enabled'] && 
            !empty($this->onlineServices['paralleldots']['key'])) {
            try {
                $parallelDotsResult = $this->analyzeWithParallelDots($text);
                if ($parallelDotsResult) {
                    $results['paralleldots'] = $parallelDotsResult;
                }
            } catch (\Exception $e) {
                Log::warning('ParallelDots failed', ['error' => $e->getMessage()]);
            }
        }

        return $results;
    }

    /**
     * Analyze with Text-Processing.com (FREE, no API key)
     */
    private function analyzeWithTextProcessing(string $text): ?array
    {
        $response = Http::timeout($this->timeout)
            ->asForm()
            ->post($this->onlineServices['text_processing']['url'], [
                'text' => $text
            ]);

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();
        
        // Convert to our standard format
        $sentimentScore = 0;
        if ($data['label'] === 'pos') {
            $sentimentScore = $data['probability']['pos'] ?? 0.5;
        } elseif ($data['label'] === 'neg') {
            $sentimentScore = -($data['probability']['neg'] ?? 0.5);
        }

        return [
            'sentiment_score' => $sentimentScore,
            'classification' => strtoupper($data['label'] ?? 'neutral'),
            'confidence' => max($data['probability']['pos'] ?? 0, $data['probability']['neg'] ?? 0),
            'service' => 'text_processing'
        ];
    }

    /**
     * Analyze with Sentiment140 (FREE, no API key)
     */
    private function analyzeWithSentiment140(string $text): ?array
    {
        $response = Http::timeout($this->timeout)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($this->onlineServices['sentiment140']['url'], [
                'data' => [
                    ['text' => $text]
                ]
            ]);

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();
        
        if (!isset($data['data'][0]['polarity'])) {
            return null;
        }

        $polarity = $data['data'][0]['polarity'];
        
        // Convert Sentiment140 scale (0=negative, 2=neutral, 4=positive) to -1 to 1
        $sentimentScore = 0;
        $classification = 'NEUTRAL';
        
        if ($polarity == 0) {
            $sentimentScore = -0.8;
            $classification = 'NEGATIVE';
        } elseif ($polarity == 4) {
            $sentimentScore = 0.8;
            $classification = 'POSITIVE';
        }

        return [
            'sentiment_score' => $sentimentScore,
            'classification' => $classification,
            'confidence' => abs($sentimentScore),
            'service' => 'sentiment140'
        ];
    }

    /**
     * Analyze with MeaningCloud (FREE tier: 20,000 requests/month)
     */
    private function analyzeWithMeaningCloud(string $text): ?array
    {
        $response = Http::timeout($this->timeout)
            ->asForm()
            ->post($this->onlineServices['meaningcloud']['url'], [
                'key' => $this->onlineServices['meaningcloud']['key'],
                'txt' => $text,
                'lang' => 'en'
            ]);

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();
        
        if (!isset($data['score_tag'])) {
            return null;
        }

        // Convert MeaningCloud scale
        $scoreTag = $data['score_tag'];
        $sentimentScore = 0;
        $classification = 'NEUTRAL';
        
        switch ($scoreTag) {
            case 'P+':
                $sentimentScore = 0.9;
                $classification = 'VERY_POSITIVE';
                break;
            case 'P':
                $sentimentScore = 0.6;
                $classification = 'POSITIVE';
                break;
            case 'N':
                $sentimentScore = -0.6;
                $classification = 'NEGATIVE';
                break;
            case 'N+':
                $sentimentScore = -0.9;
                $classification = 'VERY_NEGATIVE';
                break;
            default:
                $sentimentScore = 0;
                $classification = 'NEUTRAL';
        }

        return [
            'sentiment_score' => $sentimentScore,
            'classification' => $classification,
            'confidence' => abs($sentimentScore),
            'service' => 'meaningcloud'
        ];
    }

    /**
     * Analyze with ParallelDots (FREE tier: 1,000 requests/month)
     */
    private function analyzeWithParallelDots(string $text): ?array
    {
        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'Content-Type' => 'application/json'
            ])
            ->post($this->onlineServices['paralleldots']['url'], [
                'api_key' => $this->onlineServices['paralleldots']['key'],
                'text' => $text
            ]);

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();
        
        if (!isset($data['sentiment'])) {
            return null;
        }

        $sentiment = $data['sentiment'];
        $positive = $sentiment['positive'] ?? 0;
        $negative = $sentiment['negative'] ?? 0;
        $neutral = $sentiment['neutral'] ?? 0;
        
        // Calculate sentiment score
        $sentimentScore = $positive - $negative;
        
        $classification = 'NEUTRAL';
        if ($sentimentScore > 0.1) {
            $classification = 'POSITIVE';
        } elseif ($sentimentScore < -0.1) {
            $classification = 'NEGATIVE';
        }

        return [
            'sentiment_score' => $sentimentScore,
            'classification' => $classification,
            'confidence' => max($positive, $negative, $neutral),
            'service' => 'paralleldots'
        ];
    }

    /**
     * Aggregate results from multiple sources
     */
    private function aggregateResults(array $results): array
    {
        $scores = [];
        $confidences = [];
        $classifications = [];
        
        foreach ($results as $source => $result) {
            if (isset($result['sentiment_score'])) {
                $scores[] = $result['sentiment_score'];
                $confidences[] = $result['confidence'] ?? 0.5;
                $classifications[] = $result['classification'] ?? 'NEUTRAL';
            }
        }

        if (empty($scores)) {
            return [
                'sentiment_score' => 0,
                'magnitude' => 0,
                'confidence' => 0,
                'classification' => 'NEUTRAL'
            ];
        }

        // Weighted average (give more weight to higher confidence results)
        $weightedSum = 0;
        $weightSum = 0;
        
        for ($i = 0; $i < count($scores); $i++) {
            $weight = $confidences[$i] ?? 0.5;
            $weightedSum += $scores[$i] * $weight;
            $weightSum += $weight;
        }
        
        $avgSentiment = $weightSum > 0 ? $weightedSum / $weightSum : 0;
        $avgConfidence = array_sum($confidences) / count($confidences);
        
        // Determine final classification
        $finalClassification = 'NEUTRAL';
        if ($avgSentiment > 0.5) {
            $finalClassification = 'VERY_POSITIVE';
        } elseif ($avgSentiment > 0.1) {
            $finalClassification = 'POSITIVE';
        } elseif ($avgSentiment < -0.5) {
            $finalClassification = 'VERY_NEGATIVE';
        } elseif ($avgSentiment < -0.1) {
            $finalClassification = 'NEGATIVE';
        }

        return [
            'sentiment_score' => round($avgSentiment, 3),
            'magnitude' => round(abs($avgSentiment), 3),
            'confidence' => round($avgConfidence, 3),
            'classification' => $finalClassification
        ];
    }

    /**
     * Get service availability status
     */
    public function getServiceStatus(): array
    {
        $status = [];
        
        foreach ($this->onlineServices as $name => $config) {
            $status[$name] = [
                'enabled' => $config['enabled'],
                'requires_api_key' => !is_null($config['key']),
                'has_api_key' => !empty($config['key']),
                'free_limit' => $config['free_limit'],
                'available' => $config['enabled'] && (is_null($config['key']) || !empty($config['key']))
            ];
        }

        return $status;
    }

    /**
     * Batch analyze multiple texts
     */
    public function batchAnalyze(array $texts): array
    {
        $results = [];
        
        foreach ($texts as $index => $text) {
            $results[$index] = $this->analyzeSentiment($text);
            
            // Add small delay to respect rate limits
            if (count($texts) > 10) {
                usleep(100000); // 0.1 second delay
            }
        }

        return [
            'success' => true,
            'method' => 'enhanced_batch_analysis',
            'results' => $results,
            'total_processed' => count($texts),
            'cost' => 0.00
        ];
    }
}
