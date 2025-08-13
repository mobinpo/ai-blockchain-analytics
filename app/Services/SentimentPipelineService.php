<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SentimentBatch;
use App\Models\SentimentBatchDocument;
use App\Models\DailySentimentAggregate;
use App\Models\SocialMediaPost;
use App\Jobs\ProcessSentimentBatch;
use App\Jobs\GenerateDailySentimentAggregates;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Exception;

final class SentimentPipelineService
{
    public function __construct(
        private GoogleSentimentService $googleSentiment,
        private PostgresCacheService $cache
    ) {}

    /**
     * Main pipeline method: Process text through Google Cloud NLP and store daily aggregates
     */
    public function processTextPipeline(
        array $textData,
        array $options = []
    ): array {
        $startTime = microtime(true);
        
        try {
            Log::info('Starting sentiment pipeline processing', [
                'text_count' => count($textData),
                'options' => $options
            ]);

            // Step 1: Prepare and validate text data
            $preparedData = $this->prepareTextData($textData, $options);
            
            // Step 2: Process through Google Cloud NLP in batches
            $sentimentResults = $this->processThroughGoogleNLP($preparedData, $options);
            
            // Step 3: Store individual results
            $this->storeIndividualResults($sentimentResults, $options);
            
            // Step 4: Generate and store daily aggregates
            $aggregates = $this->generateDailyAggregates($sentimentResults, $options);
            
            $processingTime = microtime(true) - $startTime;
            
            $result = [
                'status' => 'success',
                'processed_count' => count($sentimentResults),
                'daily_aggregates' => count($aggregates),
                'processing_time' => round($processingTime, 3),
                'cost_estimate' => $this->calculateProcessingCost($sentimentResults),
                'batch_info' => $this->getBatchInfo($sentimentResults)
            ];

            Log::info('Sentiment pipeline completed successfully', $result);
            
            return $result;

        } catch (Exception $e) {
            Log::error('Sentiment pipeline failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Process text through Google Cloud NLP in optimized batches
     */
    private function processThroughGoogleNLP(array $preparedData, array $options): array
    {
        $batchSize = $options['batch_size'] ?? 25;
        $results = [];
        
        // Create batch record for tracking
        $batch = SentimentBatch::create([
            'processing_date' => now()->toDateString(),
            'batch_id' => 'pipeline_' . uniqid(),
            'status' => 'processing',
            'total_documents' => count($preparedData),
            'started_at' => now()
        ]);

        try {
            $batches = array_chunk($preparedData, $batchSize);
            
            foreach ($batches as $batchIndex => $batchData) {
                Log::info('Processing NLP batch', [
                    'batch_id' => $batch->batch_id,
                    'batch_index' => $batchIndex,
                    'batch_size' => count($batchData)
                ]);

                $batchResults = $this->processNLPBatch($batchData, $batch, $batchIndex);
                $results = array_merge($results, $batchResults);
                
                // Rate limiting between batches
                if ($batchIndex < count($batches) - 1) {
                    usleep(100000); // 100ms delay
                }
            }

            $batch->markAsCompleted([
                'total_processed' => count($results),
                'processing_batches' => count($batches)
            ]);

        } catch (Exception $e) {
            $batch->markAsFailed(['error' => $e->getMessage()]);
            throw $e;
        }

        return $results;
    }

    /**
     * Process a single batch through Google Cloud NLP
     */
    private function processNLPBatch(array $batchData, SentimentBatch $batch, int $batchIndex): array
    {
        $results = [];

        foreach ($batchData as $index => $textItem) {
            try {
                // Create batch document record
                $document = SentimentBatchDocument::create([
                    'sentiment_batch_id' => $batch->id,
                    'document_text' => $textItem['text'],
                    'document_metadata' => $textItem['metadata'] ?? [],
                    'status' => 'processing'
                ]);

                // Analyze sentiment using Google Cloud NLP
                $analysis = $this->googleSentiment->analyzeComprehensive($textItem['text']);
                
                // Extract sentiment data
                $sentimentData = $this->extractSentimentData($analysis);
                
                // Update document with results
                $document->update([
                    'status' => 'completed',
                    'sentiment_score' => $sentimentData['score'],
                    'sentiment_magnitude' => $sentimentData['magnitude'],
                    'sentiment_category' => $sentimentData['category'],
                    'processing_results' => $analysis,
                    'processed_at' => now()
                ]);

                $results[] = array_merge($textItem, [
                    'sentiment_data' => $sentimentData,
                    'full_analysis' => $analysis,
                    'document_id' => $document->id
                ]);

                $batch->incrementProcessed();

            } catch (Exception $e) {
                Log::error('Failed to process text item in batch', [
                    'batch_id' => $batch->batch_id,
                    'batch_index' => $batchIndex,
                    'item_index' => $index,
                    'error' => $e->getMessage()
                ]);

                $batch->incrementFailed();
                
                // Store failed item with error info
                $results[] = array_merge($textItem, [
                    'sentiment_data' => null,
                    'error' => $e->getMessage(),
                    'status' => 'failed'
                ]);
            }
        }

        return $results;
    }

    /**
     * Generate and store daily sentiment aggregates
     */
    private function generateDailyAggregates(array $sentimentResults, array $options): array
    {
        $aggregates = [];
        $groupedData = $this->groupDataForAggregation($sentimentResults, $options);

        foreach ($groupedData as $groupKey => $groupData) {
            $aggregateData = $this->calculateAggregateMetrics($groupData);
            
            $aggregate = DailySentimentAggregate::updateOrCreate(
                [
                    'aggregate_date' => $aggregateData['date'],
                    'platform' => $aggregateData['platform'],
                    'keyword_category' => $aggregateData['category'],
                    'language' => $aggregateData['language']
                ],
                $aggregateData['metrics']
            );

            $aggregates[] = $aggregate;
        }

        Log::info('Daily aggregates generated', [
            'aggregates_count' => count($aggregates),
            'groups_processed' => count($groupedData)
        ]);

        return $aggregates;
    }

    /**
     * Group sentiment results for aggregation
     */
    private function groupDataForAggregation(array $sentimentResults, array $options): array
    {
        $grouped = [];
        $date = $options['date'] ?? now()->toDateString();

        foreach ($sentimentResults as $result) {
            if (!isset($result['sentiment_data']) || $result['sentiment_data'] === null) {
                continue; // Skip failed analyses
            }

            $metadata = $result['metadata'] ?? [];
            $platform = $metadata['platform'] ?? 'unknown';
            $category = $metadata['category'] ?? 'general';
            $language = $metadata['language'] ?? 'en';

            $groupKey = "{$date}:{$platform}:{$category}:{$language}";

            if (!isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'date' => $date,
                    'platform' => $platform,
                    'category' => $category,
                    'language' => $language,
                    'items' => []
                ];
            }

            $grouped[$groupKey]['items'][] = $result;
        }

        return $grouped;
    }

    /**
     * Calculate aggregate metrics from grouped data
     */
    private function calculateAggregateMetrics(array $groupData): array
    {
        $items = $groupData['items'];
        $sentimentScores = [];
        $magnitudes = [];
        $categoryCounts = [
            'very_positive' => 0,
            'positive' => 0,
            'neutral' => 0,
            'negative' => 0,
            'very_negative' => 0
        ];

        foreach ($items as $item) {
            $sentiment = $item['sentiment_data'];
            $sentimentScores[] = $sentiment['score'];
            $magnitudes[] = $sentiment['magnitude'];
            
            $category = $sentiment['category'];
            if (isset($categoryCounts[$category])) {
                $categoryCounts[$category]++;
            }
        }

        $totalPosts = count($items);
        $averageSentiment = $totalPosts > 0 ? array_sum($sentimentScores) / $totalPosts : 0;
        $averageMagnitude = $totalPosts > 0 ? array_sum($magnitudes) / $totalPosts : 0;
        
        // Calculate weighted sentiment (considering engagement if available)
        $weightedSentiment = $this->calculateWeightedSentiment($items);
        
        // Calculate sentiment volatility
        $sentimentVolatility = $totalPosts > 1 ? $this->calculateStandardDeviation($sentimentScores) : 0;

        return [
            'date' => $groupData['date'],
            'platform' => $groupData['platform'],
            'category' => $groupData['category'],
            'language' => $groupData['language'],
            'metrics' => [
                'total_posts' => $totalPosts,
                'processed_posts' => $totalPosts,
                'average_sentiment' => round($averageSentiment, 3),
                'weighted_sentiment' => round($weightedSentiment, 3),
                'average_magnitude' => round($averageMagnitude, 3),
                'very_positive_count' => $categoryCounts['very_positive'],
                'positive_count' => $categoryCounts['positive'],
                'neutral_count' => $categoryCounts['neutral'],
                'negative_count' => $categoryCounts['negative'],
                'very_negative_count' => $categoryCounts['very_negative'],
                'sentiment_volatility' => round($sentimentVolatility, 3),
                'top_keywords' => $this->extractTopKeywords($items),
                'top_entities' => $this->extractTopEntities($items),
                'total_engagement' => $this->calculateTotalEngagement($items)
            ]
        ];
    }

    /**
     * Prepare and validate text data for processing
     */
    private function prepareTextData(array $textData, array $options): array
    {
        $prepared = [];

        foreach ($textData as $index => $item) {
            // Handle different input formats
            if (is_string($item)) {
                $text = $item;
                $metadata = [];
            } elseif (is_array($item) && isset($item['text'])) {
                $text = $item['text'];
                $metadata = $item['metadata'] ?? $item;
                unset($metadata['text']);
            } else {
                Log::warning('Invalid text data format', ['index' => $index, 'item' => $item]);
                continue;
            }

            // Validate text
            if (empty(trim($text)) || strlen($text) < 3) {
                Log::warning('Text too short or empty', ['index' => $index]);
                continue;
            }

            // Text preprocessing
            $text = $this->preprocessText($text);

            $prepared[] = [
                'text' => $text,
                'metadata' => array_merge([
                    'original_index' => $index,
                    'processed_at' => now()->toISOString(),
                    'platform' => $options['platform'] ?? 'api',
                    'category' => $options['category'] ?? 'general',
                    'language' => $options['language'] ?? 'en'
                ], $metadata)
            ];
        }

        Log::info('Text data prepared', [
            'original_count' => count($textData),
            'prepared_count' => count($prepared)
        ]);

        return $prepared;
    }

    /**
     * Extract sentiment data from Google NLP analysis
     */
    private function extractSentimentData(array $analysis): array
    {
        $documentSentiment = $analysis['documentSentiment'] ?? [];
        
        $score = $documentSentiment['score'] ?? 0;
        $magnitude = $documentSentiment['magnitude'] ?? 0;
        
        return [
            'score' => (float) $score,
            'magnitude' => (float) $magnitude,
            'category' => $this->categorizeSentiment($score, $magnitude),
            'confidence' => $this->calculateConfidence($score, $magnitude)
        ];
    }

    /**
     * Categorize sentiment based on score and magnitude
     */
    private function categorizeSentiment(float $score, float $magnitude): string
    {
        if ($magnitude < 0.1) {
            return 'neutral';
        }
        
        if ($score > 0.6) {
            return 'very_positive';
        } elseif ($score > 0.2) {
            return 'positive';
        } elseif ($score < -0.6) {
            return 'very_negative';
        } elseif ($score < -0.2) {
            return 'negative';
        }
        
        return 'neutral';
    }

    /**
     * Calculate confidence score
     */
    private function calculateConfidence(float $score, float $magnitude): float
    {
        return min(abs($score) + ($magnitude * 0.5), 1.0);
    }

    /**
     * Preprocess text for better analysis
     */
    private function preprocessText(string $text): string
    {
        // Remove URLs
        $text = preg_replace('/https?:\/\/[^\s]+/', '', $text);
        
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Trim
        $text = trim($text);
        
        return $text;
    }

    /**
     * Store individual sentiment results
     */
    private function storeIndividualResults(array $sentimentResults, array $options): void
    {
        // This could store to a separate table for individual post sentiment
        // For now, we rely on SentimentBatchDocument for storage
        Log::info('Individual results stored via batch documents', [
            'results_count' => count($sentimentResults)
        ]);
    }

    /**
     * Calculate weighted sentiment considering engagement
     */
    private function calculateWeightedSentiment(array $items): float
    {
        $totalWeight = 0;
        $weightedSum = 0;

        foreach ($items as $item) {
            $sentiment = $item['sentiment_data']['score'];
            $weight = $item['metadata']['engagement'] ?? 1;
            
            $totalWeight += $weight;
            $weightedSum += $sentiment * $weight;
        }

        return $totalWeight > 0 ? $weightedSum / $totalWeight : 0;
    }

    /**
     * Calculate standard deviation for volatility
     */
    private function calculateStandardDeviation(array $values): float
    {
        $count = count($values);
        if ($count <= 1) return 0;

        $mean = array_sum($values) / $count;
        $squaredDiffs = array_map(fn($value) => pow($value - $mean, 2), $values);
        
        return sqrt(array_sum($squaredDiffs) / $count);
    }

    /**
     * Extract top keywords from analysis results
     */
    private function extractTopKeywords(array $items): array
    {
        $keywords = [];
        
        foreach ($items as $item) {
            if (isset($item['full_analysis']['entities'])) {
                foreach ($item['full_analysis']['entities'] as $entity) {
                    $name = $entity['name'] ?? '';
                    if (!empty($name)) {
                        $keywords[$name] = ($keywords[$name] ?? 0) + 1;
                    }
                }
            }
        }

        arsort($keywords);
        return array_slice($keywords, 0, 10, true);
    }

    /**
     * Extract top entities from analysis results
     */
    private function extractTopEntities(array $items): array
    {
        $entities = [];
        
        foreach ($items as $item) {
            if (isset($item['full_analysis']['entities'])) {
                foreach ($item['full_analysis']['entities'] as $entity) {
                    $type = $entity['type'] ?? 'OTHER';
                    $entities[$type] = ($entities[$type] ?? 0) + 1;
                }
            }
        }

        arsort($entities);
        return $entities;
    }

    /**
     * Calculate total engagement from items
     */
    private function calculateTotalEngagement(array $items): int
    {
        $total = 0;
        
        foreach ($items as $item) {
            $total += $item['metadata']['engagement'] ?? 0;
        }

        return $total;
    }

    /**
     * Calculate processing cost estimate
     */
    private function calculateProcessingCost(array $results): float
    {
        $textUnits = 0;
        
        foreach ($results as $result) {
            $textLength = strlen($result['text'] ?? '');
            $textUnits += ceil($textLength / 1000); // Google charges per 1000 characters
        }

        // Google Cloud NLP pricing: ~$0.0005 per unit for sentiment analysis
        return $textUnits * 0.0005;
    }

    /**
     * Get batch processing information
     */
    private function getBatchInfo(array $results): array
    {
        $successful = 0;
        $failed = 0;

        foreach ($results as $result) {
            if (isset($result['sentiment_data']) && $result['sentiment_data'] !== null) {
                $successful++;
            } else {
                $failed++;
            }
        }

        return [
            'total' => count($results),
            'successful' => $successful,
            'failed' => $failed,
            'success_rate' => count($results) > 0 ? round(($successful / count($results)) * 100, 2) : 0
        ];
    }

    /**
     * Queue processing for large datasets
     */
    public function queueTextPipeline(array $textData, array $options = []): string
    {
        $batchId = 'queued_' . uniqid();
        
        // Create batch record
        $batch = SentimentBatch::create([
            'processing_date' => now()->toDateString(),
            'batch_id' => $batchId,
            'status' => 'queued',
            'total_documents' => count($textData)
        ]);

        // Dispatch job
        ProcessSentimentBatch::dispatch($batch->id, $textData, $options)
            ->onQueue($options['queue'] ?? 'sentiment-analysis');

        return $batchId;
    }

    /**
     * Get processing status for queued batch
     */
    public function getBatchStatus(string $batchId): array
    {
        $batch = SentimentBatch::where('batch_id', $batchId)->first();
        
        if (!$batch) {
            return ['status' => 'not_found'];
        }

        return [
            'status' => $batch->status,
            'progress' => $batch->progress_percentage,
            'total_documents' => $batch->total_documents,
            'processed_documents' => $batch->processed_documents,
            'failed_documents' => $batch->failed_documents,
            'started_at' => $batch->started_at,
            'completed_at' => $batch->completed_at,
            'processing_stats' => $batch->processing_stats
        ];
    }
} 