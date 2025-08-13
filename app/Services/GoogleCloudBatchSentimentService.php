<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DailySentimentAggregate;
use App\Models\SentimentBatch;
use App\Models\SentimentBatchDocument;
use Google\Cloud\Language\V1\Document;
use Google\Cloud\Language\V1\Document\Type;
use Google\Cloud\Language\V1\LanguageServiceClient;
use Google\Cloud\Language\V1\AnalyzeSentimentRequest;
use Google\Cloud\Language\V1\AnalyzeEntitySentimentRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

final class GoogleCloudBatchSentimentService
{
    private ?LanguageServiceClient $client = null;
    private array $config;
    private int $requestCount = 0;
    private float $lastRequestTime = 0;

    public function __construct()
    {
        $this->config = config('sentiment_pipeline.google_nlp', []);
        $this->initializeClient();
    }

    private function initializeClient(): void
    {
        try {
            if (class_exists(LanguageServiceClient::class)) {
                $credentials = $this->config['credentials_path'] ?? env('GOOGLE_APPLICATION_CREDENTIALS');
                
                if ($credentials && file_exists($credentials)) {
                    $this->client = new LanguageServiceClient([
                        'keyFilePath' => $credentials,
                        'projectId' => $this->config['project_id'] ?? env('GOOGLE_CLOUD_PROJECT_ID'),
                    ]);
                    
                    Log::info('Google Cloud Language client initialized successfully');
                } else {
                    Log::warning('Google Cloud credentials not found, using simulation mode');
                }
            } else {
                Log::warning('Google Cloud Language SDK not available');
            }
        } catch (Exception $e) {
            Log::error('Failed to initialize Google Cloud client', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Process batch of texts through Google Cloud NLP and store daily aggregates
     */
    public function processBatchWithDailyAggregates(
        array $texts,
        string $platform = 'general',
        string $keyword = null,
        Carbon $targetDate = null
    ): array {
        $targetDate = $targetDate ?? Carbon::today();
        $startTime = microtime(true);

        Log::info('Starting batch sentiment processing with daily aggregates', [
            'text_count' => count($texts),
            'platform' => $platform,
            'keyword' => $keyword,
            'target_date' => $targetDate->toDateString()
        ]);

        try {
            // Step 1: Process texts through Google Cloud NLP in batches
            $sentimentResults = $this->processBatchSentiment($texts);

            // Step 2: Store individual results if needed
            $this->storeIndividualResults($sentimentResults, $platform, $keyword);

            // Step 3: Generate and store daily aggregates
            $aggregateResult = $this->generateAndStoreDailyAggregates(
                $sentimentResults,
                $platform,
                $keyword,
                $targetDate
            );

            $processingTime = microtime(true) - $startTime;

            $result = [
                'status' => 'success',
                'processed_count' => count($sentimentResults),
                'processing_time' => round($processingTime, 3),
                'platform' => $platform,
                'keyword' => $keyword,
                'target_date' => $targetDate->toDateString(),
                'aggregate_created' => $aggregateResult['created'],
                'aggregate_updated' => $aggregateResult['updated'],
                'cost_estimate' => $this->calculateCostEstimate($sentimentResults),
                'sentiment_summary' => $this->generateSentimentSummary($sentimentResults)
            ];

            Log::info('Batch sentiment processing completed successfully', $result);

            return $result;

        } catch (Exception $e) {
            Log::error('Batch sentiment processing failed', [
                'error' => $e->getMessage(),
                'platform' => $platform,
                'text_count' => count($texts)
            ]);

            throw $e;
        }
    }

    /**
     * Process texts through Google Cloud NLP in optimized batches
     */
    private function processBatchSentiment(array $texts): array
    {
        if (!$this->client) {
            return $this->simulateSentimentAnalysis($texts);
        }

        $results = [];
        $batchSize = $this->config['batch_size'] ?? 25;
        $chunks = array_chunk($texts, $batchSize);

        foreach ($chunks as $chunkIndex => $chunk) {
            Log::info("Processing batch chunk {$chunkIndex}", [
                'chunk_size' => count($chunk),
                'total_chunks' => count($chunks)
            ]);

            foreach ($chunk as $index => $text) {
                try {
                    $this->enforceRateLimit();
                    
                    $sentiment = $this->analyzeSingleText($text);
                    $results[] = [
                        'text' => $text,
                        'sentiment_score' => $sentiment['score'],
                        'sentiment_magnitude' => $sentiment['magnitude'],
                        'sentiment_category' => $this->categorizeSentiment($sentiment['score']),
                        'confidence' => $sentiment['confidence'] ?? 0.8,
                        'language' => $sentiment['language'] ?? 'en',
                        'entities' => $sentiment['entities'] ?? [],
                        'processed_at' => now()->toISOString()
                    ];

                    $this->requestCount++;

                } catch (Exception $e) {
                    Log::warning('Failed to analyze text', [
                        'error' => $e->getMessage(),
                        'text_preview' => substr($text, 0, 100)
                    ]);

                    // Add fallback result
                    $results[] = [
                        'text' => $text,
                        'sentiment_score' => 0.0,
                        'sentiment_magnitude' => 0.0,
                        'sentiment_category' => 'neutral',
                        'confidence' => 0.0,
                        'language' => 'en',
                        'entities' => [],
                        'error' => $e->getMessage(),
                        'processed_at' => now()->toISOString()
                    ];
                }
            }

            // Brief pause between chunks to avoid overwhelming the API
            if ($chunkIndex < count($chunks) - 1) {
                usleep(100000); // 100ms pause
            }
        }

        return $results;
    }

    /**
     * Analyze single text through Google Cloud NLP
     */
    private function analyzeSingleText(string $text): array
    {
        // Create document
        $document = new Document([
            'content' => $text,
            'type' => Type::PLAIN_TEXT,
        ]);

        // Analyze sentiment
        $response = $this->client->analyzeSentiment([
            'document' => $document,
            'encoding_type' => 'UTF8'
        ]);

        $sentiment = $response->getDocumentSentiment();

        return [
            'score' => $sentiment->getScore(),
            'magnitude' => $sentiment->getMagnitude(),
            'confidence' => 0.8, // Google doesn't provide confidence, using default
            'language' => $response->getLanguage() ?? 'en'
        ];
    }

    /**
     * Generate and store daily aggregates from sentiment results
     */
    private function generateAndStoreDailyAggregates(
        array $sentimentResults,
        string $platform,
        ?string $keyword,
        Carbon $targetDate
    ): array {
        $aggregateData = $this->calculateAggregateMetrics($sentimentResults);

        // Check if aggregate already exists
        $existing = DailySentimentAggregate::where('date', $targetDate->toDateString())
            ->where('platform', $platform)
            ->where('keyword', $keyword)
            ->first();

        $aggregateRecord = [
            'date' => $targetDate->toDateString(),
            'platform' => $platform,
            'keyword' => $keyword,
            'total_posts' => count($sentimentResults),
            'analyzed_posts' => count(array_filter($sentimentResults, fn($r) => !isset($r['error']))),
            'avg_sentiment_score' => $aggregateData['avg_sentiment'],
            'avg_magnitude' => $aggregateData['avg_magnitude'],
            'positive_count' => $aggregateData['positive_count'],
            'negative_count' => $aggregateData['negative_count'],
            'neutral_count' => $aggregateData['neutral_count'],
            'unknown_count' => $aggregateData['unknown_count'],
            'positive_percentage' => $aggregateData['positive_percentage'],
            'negative_percentage' => $aggregateData['negative_percentage'],
            'neutral_percentage' => $aggregateData['neutral_percentage'],
            'hourly_distribution' => $this->generateHourlyDistribution($sentimentResults),
            'top_keywords' => $this->extractTopKeywords($sentimentResults),
            'metadata' => [
                'processing_time' => microtime(true),
                'api_requests' => $this->requestCount,
                'cost_estimate' => $this->calculateCostEstimate($sentimentResults),
                'language_distribution' => $this->calculateLanguageDistribution($sentimentResults),
                'confidence_average' => $aggregateData['avg_confidence']
            ],
            'processed_at' => now()
        ];

        if ($existing) {
            $existing->update($aggregateRecord);
            $result = ['created' => false, 'updated' => true, 'aggregate_id' => $existing->id];
        } else {
            $newAggregate = DailySentimentAggregate::create($aggregateRecord);
            $result = ['created' => true, 'updated' => false, 'aggregate_id' => $newAggregate->id];
        }

        Log::info('Daily sentiment aggregate processed', [
            'date' => $targetDate->toDateString(),
            'platform' => $platform,
            'keyword' => $keyword,
            'action' => $existing ? 'updated' : 'created',
            'metrics' => $aggregateData
        ]);

        return $result;
    }

    /**
     * Calculate aggregate metrics from sentiment results
     */
    private function calculateAggregateMetrics(array $results): array
    {
        $validResults = array_filter($results, fn($r) => !isset($r['error']));
        $total = count($validResults);

        if ($total === 0) {
            return [
                'avg_sentiment' => 0.0,
                'avg_magnitude' => 0.0,
                'avg_confidence' => 0.0,
                'positive_count' => 0,
                'negative_count' => 0,
                'neutral_count' => 0,
                'unknown_count' => count($results),
                'positive_percentage' => 0.0,
                'negative_percentage' => 0.0,
                'neutral_percentage' => 0.0
            ];
        }

        $sentimentSum = array_sum(array_column($validResults, 'sentiment_score'));
        $magnitudeSum = array_sum(array_column($validResults, 'sentiment_magnitude'));
        $confidenceSum = array_sum(array_column($validResults, 'confidence'));

        $positive = count(array_filter($validResults, fn($r) => $r['sentiment_category'] === 'positive'));
        $negative = count(array_filter($validResults, fn($r) => $r['sentiment_category'] === 'negative'));
        $neutral = count(array_filter($validResults, fn($r) => $r['sentiment_category'] === 'neutral'));
        $unknown = count($results) - $total;

        return [
            'avg_sentiment' => round($sentimentSum / $total, 4),
            'avg_magnitude' => round($magnitudeSum / $total, 4),
            'avg_confidence' => round($confidenceSum / $total, 4),
            'positive_count' => $positive,
            'negative_count' => $negative,
            'neutral_count' => $neutral,
            'unknown_count' => $unknown,
            'positive_percentage' => round(($positive / $total) * 100, 2),
            'negative_percentage' => round(($negative / $total) * 100, 2),
            'neutral_percentage' => round(($neutral / $total) * 100, 2)
        ];
    }

    /**
     * Categorize sentiment based on score
     */
    private function categorizeSentiment(float $score): string
    {
        if ($score > 0.2) {
            return 'positive';
        } elseif ($score < -0.2) {
            return 'negative';
        } else {
            return 'neutral';
        }
    }

    /**
     * Generate hourly distribution of sentiment
     */
    private function generateHourlyDistribution(array $results): array
    {
        $distribution = array_fill(0, 24, ['positive' => 0, 'negative' => 0, 'neutral' => 0]);
        
        foreach ($results as $result) {
            $hour = (int) date('H', strtotime($result['processed_at']));
            $category = $result['sentiment_category'];
            
            if (isset($distribution[$hour][$category])) {
                $distribution[$hour][$category]++;
            }
        }

        return $distribution;
    }

    /**
     * Extract top keywords from text results
     */
    private function extractTopKeywords(array $results, int $limit = 10): array
    {
        $keywords = [];
        
        foreach ($results as $result) {
            if (isset($result['entities'])) {
                foreach ($result['entities'] as $entity) {
                    $name = strtolower($entity['name'] ?? '');
                    if (strlen($name) > 2) {
                        $keywords[$name] = ($keywords[$name] ?? 0) + 1;
                    }
                }
            }
        }

        arsort($keywords);
        return array_slice($keywords, 0, $limit, true);
    }

    /**
     * Calculate language distribution
     */
    private function calculateLanguageDistribution(array $results): array
    {
        $languages = [];
        
        foreach ($results as $result) {
            $lang = $result['language'] ?? 'unknown';
            $languages[$lang] = ($languages[$lang] ?? 0) + 1;
        }

        return $languages;
    }

    /**
     * Store individual sentiment results (optional)
     */
    private function storeIndividualResults(array $results, string $platform, ?string $keyword): void
    {
        // This can be used to store individual results in a separate table if needed
        // For now, we focus on daily aggregates to reduce storage overhead
        Log::debug('Individual results processed', [
            'count' => count($results),
            'platform' => $platform,
            'keyword' => $keyword
        ]);
    }

    /**
     * Generate sentiment summary
     */
    private function generateSentimentSummary(array $results): array
    {
        $metrics = $this->calculateAggregateMetrics($results);
        
        return [
            'overall_sentiment' => $this->getSentimentLabel($metrics['avg_sentiment']),
            'sentiment_strength' => $this->getSentimentStrength($metrics['avg_magnitude']),
            'dominant_category' => $this->getDominantCategory($metrics),
            'confidence_level' => $this->getConfidenceLevel($metrics['avg_confidence'])
        ];
    }

    private function getSentimentLabel(float $score): string
    {
        if ($score > 0.6) return 'very_positive';
        if ($score > 0.2) return 'positive';
        if ($score > -0.2) return 'neutral';
        if ($score > -0.6) return 'negative';
        return 'very_negative';
    }

    private function getSentimentStrength(float $magnitude): string
    {
        if ($magnitude > 1.5) return 'very_strong';
        if ($magnitude > 1.0) return 'strong';
        if ($magnitude > 0.5) return 'moderate';
        return 'weak';
    }

    private function getDominantCategory(array $metrics): string
    {
        $max = max($metrics['positive_count'], $metrics['negative_count'], $metrics['neutral_count']);
        
        if ($max === $metrics['positive_count']) return 'positive';
        if ($max === $metrics['negative_count']) return 'negative';
        return 'neutral';
    }

    private function getConfidenceLevel(float $confidence): string
    {
        if ($confidence > 0.8) return 'high';
        if ($confidence > 0.6) return 'medium';
        return 'low';
    }

    /**
     * Enforce rate limiting for Google Cloud API
     */
    private function enforceRateLimit(): void
    {
        $now = microtime(true);
        $minInterval = 60 / ($this->config['requests_per_minute'] ?? 600); // seconds between requests
        
        if ($this->lastRequestTime > 0) {
            $elapsed = $now - $this->lastRequestTime;
            if ($elapsed < $minInterval) {
                usleep((int)(($minInterval - $elapsed) * 1000000)); // Convert to microseconds
            }
        }
        
        $this->lastRequestTime = microtime(true);
    }

    /**
     * Calculate cost estimate for API usage
     */
    private function calculateCostEstimate(array $results): float
    {
        $costPerRequest = $this->config['sentiment_analysis_cost'] ?? 0.001;
        $validResults = count(array_filter($results, fn($r) => !isset($r['error'])));
        
        return round($validResults * $costPerRequest, 4);
    }

    /**
     * Simulate sentiment analysis when Google Cloud client is not available
     */
    private function simulateSentimentAnalysis(array $texts): array
    {
        Log::info('Using simulated sentiment analysis (Google Cloud client not available)');
        
        $results = [];
        
        foreach ($texts as $text) {
            // Simple sentiment simulation based on text patterns
            $score = $this->simulateSentimentScore($text);
            $magnitude = abs($score) + (rand(0, 50) / 100);
            
            $results[] = [
                'text' => $text,
                'sentiment_score' => $score,
                'sentiment_magnitude' => $magnitude,
                'sentiment_category' => $this->categorizeSentiment($score),
                'confidence' => 0.5, // Lower confidence for simulated results
                'language' => 'en',
                'entities' => [],
                'simulated' => true,
                'processed_at' => now()->toISOString()
            ];
        }
        
        return $results;
    }

    /**
     * Simple sentiment score simulation
     */
    private function simulateSentimentScore(string $text): float
    {
        $text = strtolower($text);
        $positive_words = ['good', 'great', 'excellent', 'amazing', 'love', 'best', 'awesome', 'fantastic'];
        $negative_words = ['bad', 'terrible', 'awful', 'hate', 'worst', 'horrible', 'disgusting'];
        
        $positive_count = 0;
        $negative_count = 0;
        
        foreach ($positive_words as $word) {
            $positive_count += substr_count($text, $word);
        }
        
        foreach ($negative_words as $word) {
            $negative_count += substr_count($text, $word);
        }
        
        $score = ($positive_count - $negative_count) * 0.2;
        return max(-1.0, min(1.0, $score + (rand(-20, 20) / 100))); // Add some randomness
    }
}
