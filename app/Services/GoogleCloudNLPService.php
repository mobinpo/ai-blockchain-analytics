<?php

namespace App\Services;

use Google\Cloud\Language\V1\Document;
use Google\Cloud\Language\V1\Document\Type;
use Google\Cloud\Language\V1\LanguageServiceClient;
use Google\Cloud\Language\V1\AnalyzeSentimentRequest;
use Google\Cloud\Language\V1\Sentiment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class GoogleCloudNLPService
{
    protected ?LanguageServiceClient $client = null;
    protected array $config;

    public function __construct()
    {
        $this->config = config('services.google_language', []);
        
        // Initialize Google Cloud Language client only if class exists
        if (class_exists(LanguageServiceClient::class)) {
            $this->client = new LanguageServiceClient([
                'keyFilePath' => $this->config['credentials'] ?? null,
                'projectId' => $this->config['project_id'] ?? null,
            ]);
        } else {
            Log::warning('Google Cloud Language SDK not available. Some sentiment analysis features may be limited.');
        }
    }

    /**
     * Process batch sentiment analysis for multiple texts
     */
    public function analyzeBatchSentiment(array $texts, string $language = 'en'): array
    {
        if (!$this->client) {
            Log::warning('Google Cloud Language client not available for batch sentiment analysis');
            return [];
        }

        $results = [];
        $processedCount = 0;

        Log::info('Starting batch sentiment analysis', [
            'total_texts' => count($texts),
            'language' => $language
        ]);

        foreach (array_chunk($texts, 25) as $batch) { // Process in chunks of 25
            try {
                $batchResults = $this->processBatch($batch, $language);
                $results = array_merge($results, $batchResults);
                $processedCount += count($batchResults);
                
                Log::debug('Processed batch', [
                    'batch_size' => count($batchResults),
                    'total_processed' => $processedCount
                ]);

                // Rate limiting - sleep between batches to avoid quota issues
                if (count($texts) > 25) {
                    usleep(200000); // 200ms delay between batches
                }

            } catch (\Exception $e) {
                Log::error('Batch processing error', [
                    'error' => $e->getMessage(),
                    'batch_size' => count($batch)
                ]);
                
                // Add failed results with null sentiment
                foreach ($batch as $text) {
                    $results[] = [
                        'text' => $text,
                        'sentiment_score' => null,
                        'sentiment_magnitude' => null,
                        'sentiment_label' => 'unknown',
                        'error' => $e->getMessage()
                    ];
                }
            }
        }

        Log::info('Batch sentiment analysis completed', [
            'total_processed' => $processedCount,
            'success_rate' => ($processedCount / count($texts)) * 100
        ]);

        return $results;
    }

    /**
     * Process a single batch of texts
     */
    protected function processBatch(array $texts, string $language): array
    {
        $results = [];

        foreach ($texts as $text) {
            if (empty(trim($text))) {
                $results[] = [
                    'text' => $text,
                    'sentiment_score' => 0.0,
                    'sentiment_magnitude' => 0.0,
                    'sentiment_label' => 'neutral',
                    'error' => null
                ];
                continue;
            }

            try {
                $sentiment = $this->analyzeSingleText($text, $language);
                
                $results[] = [
                    'text' => $text,
                    'sentiment_score' => $sentiment->getScore(),
                    'sentiment_magnitude' => $sentiment->getMagnitude(),
                    'sentiment_label' => $this->getSentimentLabel($sentiment->getScore()),
                    'error' => null
                ];

            } catch (\Exception $e) {
                Log::warning('Single text analysis failed', [
                    'text_length' => strlen($text),
                    'error' => $e->getMessage()
                ]);

                $results[] = [
                    'text' => $text,
                    'sentiment_score' => null,
                    'sentiment_magnitude' => null,
                    'sentiment_label' => 'unknown',
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Analyze sentiment for a single text
     */
    protected function analyzeSingleText(string $text, string $language): Sentiment
    {
        // Create document
        $document = new Document();
        $document->setContent($text);
        $document->setType(Type::PLAIN_TEXT);
        $document->setLanguage($language);

        // Create request
        $request = new AnalyzeSentimentRequest();
        $request->setDocument($document);
        $request->setEncodingType('UTF8');

        // Analyze sentiment
        $response = $this->client->analyzeSentiment($request);
        return $response->getDocumentSentiment();
    }

    /**
     * Convert sentiment score to human-readable label
     */
    protected function getSentimentLabel(float $score): string
    {
        return match (true) {
            $score >= 0.25 => 'positive',
            $score <= -0.25 => 'negative',
            default => 'neutral'
        };
    }

    /**
     * Process texts from social media posts
     */
    public function processSocialMediaTexts(Collection $posts): array
    {
        $texts = $posts->pluck('content')->filter()->values()->toArray();
        
        if (empty($texts)) {
            return [];
        }

        Log::info('Processing social media texts for sentiment', [
            'post_count' => $posts->count(),
            'text_count' => count($texts)
        ]);

        $sentimentResults = $this->analyzeBatchSentiment($texts);
        
        // Map results back to posts
        $results = [];
        foreach ($posts as $index => $post) {
            $sentimentData = $sentimentResults[$index] ?? null;
            
            $results[] = [
                'post_id' => $post->id,
                'platform' => $post->platform,
                'original_text' => $post->content,
                'sentiment_score' => $sentimentData['sentiment_score'] ?? null,
                'sentiment_magnitude' => $sentimentData['sentiment_magnitude'] ?? null,
                'sentiment_label' => $sentimentData['sentiment_label'] ?? 'unknown',
                'processed_at' => now(),
                'error' => $sentimentData['error'] ?? null
            ];
        }

        return $results;
    }

    /**
     * Analyze sentiment for blockchain-related keywords
     */
    public function analyzeKeywordSentiment(array $keywords, Collection $posts): array
    {
        $results = [];

        foreach ($keywords as $keyword) {
            $keywordPosts = $posts->filter(function ($post) use ($keyword) {
                return stripos($post->content, $keyword) !== false;
            });

            if ($keywordPosts->isEmpty()) {
                $results[$keyword] = [
                    'keyword' => $keyword,
                    'post_count' => 0,
                    'avg_sentiment' => 0,
                    'sentiment_distribution' => ['positive' => 0, 'negative' => 0, 'neutral' => 0]
                ];
                continue;
            }

            $sentimentResults = $this->processSocialMediaTexts($keywordPosts);
            
            // Calculate aggregates
            $sentiments = collect($sentimentResults)
                ->where('sentiment_score', '!=', null)
                ->pluck('sentiment_score');

            $sentimentLabels = collect($sentimentResults)
                ->where('sentiment_label', '!=', 'unknown')
                ->countBy('sentiment_label');

            $results[$keyword] = [
                'keyword' => $keyword,
                'post_count' => $keywordPosts->count(),
                'avg_sentiment' => $sentiments->avg() ?: 0,
                'sentiment_distribution' => [
                    'positive' => $sentimentLabels['positive'] ?? 0,
                    'negative' => $sentimentLabels['negative'] ?? 0,
                    'neutral' => $sentimentLabels['neutral'] ?? 0
                ],
                'processed_at' => now()
            ];
        }

        return $results;
    }

    /**
     * Get service health and quota information
     */
    public function getServiceHealth(): array
    {
        try {
            // Test with a simple text
            $testText = "This is a test message for Google Cloud NLP service health check.";
            $sentiment = $this->analyzeSingleText($testText, 'en');

            return [
                'status' => 'healthy',
                'test_sentiment_score' => $sentiment->getScore(),
                'test_sentiment_label' => $this->getSentimentLabel($sentiment->getScore()),
                'timestamp' => now(),
                'service' => 'Google Cloud Natural Language API'
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'timestamp' => now(),
                'service' => 'Google Cloud Natural Language API'
            ];
        }
    }

    /**
     * Get processing statistics
     */
    public function getProcessingStats(array $results): array
    {
        $total = count($results);
        $successful = collect($results)->where('error', null)->count();
        $failed = $total - $successful;

        $sentimentCounts = collect($results)
            ->where('error', null)
            ->countBy('sentiment_label');

        return [
            'total_processed' => $total,
            'successful' => $successful,
            'failed' => $failed,
            'success_rate' => $total > 0 ? ($successful / $total) * 100 : 0,
            'sentiment_distribution' => [
                'positive' => $sentimentCounts['positive'] ?? 0,
                'negative' => $sentimentCounts['negative'] ?? 0,
                'neutral' => $sentimentCounts['neutral'] ?? 0,
                'unknown' => $sentimentCounts['unknown'] ?? 0,
            ]
        ];
    }
}