<?php

namespace App\Services\SocialCrawler;

use Google\Cloud\Language\V1\LanguageServiceClient;
use Google\Cloud\Language\V1\Document;
use Google\Cloud\Language\V1\Document\Type;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SentimentAnalyzer
{
    protected ?LanguageServiceClient $client;
    protected bool $useCloudService;

    public function __construct()
    {
        $this->useCloudService = config('social_crawler.processing.enable_sentiment_analysis', true);
        
        if ($this->useCloudService) {
            try {
                if (class_exists(LanguageServiceClient::class)) {
                    $this->client = new LanguageServiceClient();
                } else {
                    Log::warning('Google Cloud Language SDK not installed, using basic sentiment analysis');
                    $this->client = null;
                    $this->useCloudService = false;
                }
            } catch (\Exception $e) {
                Log::warning('Google Cloud Language service not available, using basic sentiment analysis');
                $this->client = null;
                $this->useCloudService = false;
            }
        }
    }

    public function analyze(string $text): array
    {
        // Cache sentiment analysis to avoid duplicate API calls
        $cacheKey = 'sentiment_' . md5($text);
        
        return Cache::remember($cacheKey, 3600, function () use ($text) {
            if ($this->useCloudService && $this->client) {
                return $this->analyzeWithGoogleCloud($text);
            }
            
            return $this->analyzeBasic($text);
        });
    }

    protected function analyzeWithGoogleCloud(string $text): array
    {
        try {
            $document = new Document();
            $document->setContent($text);
            $document->setType(Type::PLAIN_TEXT);

            $response = $this->client->analyzeSentiment($document);
            $sentiment = $response->getDocumentSentiment();
            
            $score = $sentiment->getScore();
            $magnitude = $sentiment->getMagnitude();
            
            return [
                'score' => round($score, 2),
                'magnitude' => round($magnitude, 2),
                'label' => $this->scoresToLabel($score, $magnitude),
                'method' => 'google_cloud'
            ];
            
        } catch (\Exception $e) {
            Log::error('Google Cloud sentiment analysis failed', [
                'error' => $e->getMessage(),
                'text_length' => strlen($text)
            ]);
            
            return $this->analyzeBasic($text);
        }
    }

    protected function analyzeBasic(string $text): array
    {
        $positiveWords = [
            'good', 'great', 'excellent', 'amazing', 'awesome', 'fantastic', 'love',
            'like', 'best', 'perfect', 'wonderful', 'brilliant', 'outstanding',
            'bullish', 'moon', 'pump', 'gains', 'profit', 'win', 'success'
        ];

        $negativeWords = [
            'bad', 'terrible', 'awful', 'hate', 'worst', 'horrible', 'disgusting',
            'disappointing', 'failed', 'broken', 'useless', 'bearish', 'crash',
            'dump', 'loss', 'scam', 'hack', 'exploit', 'rugpull', 'rug', 'dead'
        ];

        $text = strtolower($text);
        $words = str_word_count($text, 1);
        
        $positiveCount = 0;
        $negativeCount = 0;
        
        foreach ($words as $word) {
            if (in_array($word, $positiveWords)) {
                $positiveCount++;
            } elseif (in_array($word, $negativeWords)) {
                $negativeCount++;
            }
        }
        
        $totalWords = count($words);
        $score = 0;
        
        if ($totalWords > 0) {
            $score = ($positiveCount - $negativeCount) / $totalWords;
            $score = max(-1, min(1, $score)); // Clamp between -1 and 1
        }
        
        return [
            'score' => round($score, 2),
            'magnitude' => round(abs($score), 2),
            'label' => $this->scoresToLabel($score, abs($score)),
            'method' => 'basic',
            'positive_words' => $positiveCount,
            'negative_words' => $negativeCount,
            'total_words' => $totalWords
        ];
    }

    protected function scoresToLabel(float $score, float $magnitude): string
    {
        if ($magnitude < 0.1) {
            return 'neutral';
        }
        
        if ($score > 0.1) {
            return $score > 0.5 ? 'very_positive' : 'positive';
        } elseif ($score < -0.1) {
            return $score < -0.5 ? 'very_negative' : 'negative';
        }
        
        return 'neutral';
    }

    public function batchAnalyze(array $texts): array
    {
        $results = [];
        
        foreach ($texts as $index => $text) {
            $results[$index] = $this->analyze($text);
            
            // Add small delay for API rate limiting
            if ($this->useCloudService) {
                usleep(50000); // 50ms
            }
        }
        
        return $results;
    }

    public function isNegativeSentiment(float $score, float $threshold = -0.3): bool
    {
        return $score <= $threshold;
    }

    public function isPositiveSentiment(float $score, float $threshold = 0.3): bool
    {
        return $score >= $threshold;
    }
}