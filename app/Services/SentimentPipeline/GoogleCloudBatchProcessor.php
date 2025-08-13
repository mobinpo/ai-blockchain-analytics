<?php

declare(strict_types=1);

namespace App\Services\SentimentPipeline;

use App\Services\GoogleCloudNLPService;
use App\Models\SentimentBatch;
use App\Models\SentimentBatchDocument;
use App\Models\DailySentimentAggregate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Enhanced Google Cloud NLP Batch Processor
 * 
 * Streamlined pipeline: Text → Google Cloud NLP (batch sentiment) → Daily aggregates
 */
final class GoogleCloudBatchProcessor
{
    public function __construct(
        private readonly GoogleCloudNLPService $nlpService,
        private readonly DailySentimentAggregateService $aggregateService
    ) {}

    /**
     * Process text array through complete pipeline
     */
    public function processTextToDailyAggregates(
        array $texts,
        array $metadata = [],
        bool $generateAggregates = true
    ): array {
        $startTime = microtime(true);
        
        Log::info('Starting text → NLP → aggregates pipeline', [
            'text_count' => count($texts),
            'generate_aggregates' => $generateAggregates,
            'metadata' => $metadata
        ]);

        // Step 1: Create sentiment batch
        $batch = $this->createSentimentBatch($texts, $metadata);
        
        // Step 2: Process through Google Cloud NLP
        $sentimentResults = $this->processBatchSentiment($batch, $texts);
        
        // Step 3: Store individual results
        $this->storeBatchResults($batch, $sentimentResults);
        
        // Step 4: Generate daily aggregates
        $aggregates = [];
        if ($generateAggregates) {
            $aggregates = $this->generateDailyAggregates($batch, $sentimentResults);
        }
        
        // Step 5: Update batch status
        $this->completeBatch($batch, $sentimentResults);
        
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);
        
        Log::info('Pipeline completed', [
            'batch_id' => $batch->id,
            'processed_count' => count($sentimentResults),
            'aggregates_created' => count($aggregates),
            'execution_time_ms' => $executionTime
        ]);

        return [
            'batch_id' => $batch->id,
            'processed_count' => count($sentimentResults),
            'sentiment_results' => $sentimentResults,
            'daily_aggregates' => $aggregates,
            'execution_time_ms' => $executionTime,
            'success' => true
        ];
    }

    /**
     * Process large text batches efficiently
     */
    public function processLargeBatch(
        array $texts,
        array $metadata = [],
        int $chunkSize = 100
    ): array {
        $allResults = [];
        $totalChunks = ceil(count($texts) / $chunkSize);
        
        Log::info('Processing large batch', [
            'total_texts' => count($texts),
            'chunk_size' => $chunkSize,
            'total_chunks' => $totalChunks
        ]);

        foreach (array_chunk($texts, $chunkSize) as $index => $chunk) {
            $chunkNumber = $index + 1;
            
            Log::info("Processing chunk {$chunkNumber}/{$totalChunks}", [
                'chunk_size' => count($chunk)
            ]);

            $chunkMetadata = array_merge($metadata, [
                'chunk_number' => $chunkNumber,
                'total_chunks' => $totalChunks,
                'parent_batch' => $metadata['batch_name'] ?? 'large_batch_' . time()
            ]);

            $result = $this->processTextToDailyAggregates($chunk, $chunkMetadata, true);
            $allResults[] = $result;

            // Small delay between chunks to avoid rate limits
            if ($chunkNumber < $totalChunks) {
                usleep(500000); // 500ms delay
            }
        }

        return [
            'total_chunks_processed' => count($allResults),
            'chunk_results' => $allResults,
            'success' => true
        ];
    }

    /**
     * Create sentiment batch record
     */
    private function createSentimentBatch(array $texts, array $metadata): SentimentBatch
    {
        return SentimentBatch::create([
            'processing_date' => now()->toDateString(),
            'batch_id' => 'google_nlp_' . time() . '_' . rand(1000, 9999),
            'status' => 'processing',
            'total_documents' => count($texts),
            'processed_documents' => 0,
            'failed_documents' => 0,
            'processing_stats' => [
                'processor' => 'GoogleCloudBatchProcessor',
                'nlp_provider' => 'google_cloud',
                'chunk_size' => $metadata['chunk_size'] ?? 25,
                'platform' => $metadata['platform'] ?? 'general',
                'keyword_category' => $metadata['keyword_category'] ?? 'general',
                'language' => $metadata['language'] ?? 'en',
                'metadata' => $metadata
            ],
            'started_at' => now(),
        ]);
    }

    /**
     * Process batch through Google Cloud NLP
     */
    private function processBatchSentiment(SentimentBatch $batch, array $texts): array
    {
        Log::info('Processing batch through Google Cloud NLP', [
            'batch_id' => $batch->id,
            'text_count' => count($texts)
        ]);

        try {
            $results = $this->nlpService->analyzeBatchSentiment(
                $texts,
                $batch->language ?? 'en'
            );

            Log::info('Google Cloud NLP processing completed', [
                'batch_id' => $batch->id,
                'results_count' => count($results)
            ]);

            return $results;

        } catch (\Exception $e) {
            Log::error('Google Cloud NLP processing failed', [
                'batch_id' => $batch->id,
                'error' => $e->getMessage()
            ]);

            // Return empty results with error info
            return array_map(fn($text) => [
                'text' => $text,
                'sentiment_score' => null,
                'sentiment_magnitude' => null,
                'sentiment_label' => 'unknown',
                'error' => $e->getMessage()
            ], $texts);
        }
    }

    /**
     * Store batch results in database
     */
    private function storeBatchResults(SentimentBatch $batch, array $results): void
    {
        Log::info('Storing batch results', [
            'batch_id' => $batch->id,
            'results_count' => count($results)
        ]);

        $documents = [];
        $successCount = 0;
        $failureCount = 0;

        foreach ($results as $index => $result) {
            $isSuccess = $result['sentiment_score'] !== null && $result['error'] === null;
            
            if ($isSuccess) {
                $successCount++;
            } else {
                $failureCount++;
            }

            $documents[] = [
                'sentiment_batch_id' => $batch->id,
                'document_index' => $index,
                'original_text' => $result['text'],
                'processed_text' => $result['text'], // Could add preprocessing here
                'sentiment_score' => $result['sentiment_score'],
                'sentiment_magnitude' => $result['sentiment_magnitude'],
                'sentiment_label' => $result['sentiment_label'],
                'confidence_score' => $result['sentiment_magnitude'] ?? 0.0,
                'language' => $batch->language,
                'processing_status' => $isSuccess ? 'completed' : 'failed',
                'error_message' => $result['error'],
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Bulk insert for efficiency
        SentimentBatchDocument::insert($documents);

        // Update batch counts
        $batch->update([
            'processed_documents' => $successCount,
            'failed_documents' => $failureCount,
        ]);

        Log::info('Batch results stored', [
            'batch_id' => $batch->id,
            'success_count' => $successCount,
            'failure_count' => $failureCount
        ]);
    }

    /**
     * Generate daily aggregates from batch results
     */
    private function generateDailyAggregates(SentimentBatch $batch, array $results): array
    {
        Log::info('Generating daily aggregates', [
            'batch_id' => $batch->id
        ]);

        $aggregates = [];
        $today = Carbon::today();

        // Group results by platform/category for aggregation
        $groupedResults = $this->groupResultsForAggregation($batch, $results);

        foreach ($groupedResults as $groupKey => $groupResults) {
            $aggregateData = $this->calculateAggregateMetrics($groupResults);
            
            $aggregate = $this->aggregateService->createOrUpdateAggregate(
                $today,
                $batch->platform ?? 'general',
                $batch->keyword_category ?? 'general',
                $batch->language ?? 'en',
                $aggregateData
            );

            if ($aggregate) {
                $aggregates[] = $aggregate;
                
                Log::debug('Created daily aggregate', [
                    'date' => $today->toDateString(),
                    'platform' => $batch->platform,
                    'category' => $batch->keyword_category,
                    'document_count' => $aggregateData['total_documents']
                ]);
            }
        }

        Log::info('Daily aggregates generated', [
            'batch_id' => $batch->id,
            'aggregates_count' => count($aggregates)
        ]);

        return $aggregates;
    }

    /**
     * Group results for aggregation
     */
    private function groupResultsForAggregation(SentimentBatch $batch, array $results): array
    {
        // For now, group all results together
        // Could be enhanced to group by different criteria
        return [
            'main_group' => array_filter($results, fn($r) => $r['error'] === null)
        ];
    }

    /**
     * Calculate aggregate metrics from results
     */
    private function calculateAggregateMetrics(array $results): array
    {
        if (empty($results)) {
            return [
                'total_documents' => 0,
                'avg_sentiment_score' => 0.0,
                'avg_magnitude' => 0.0,
                'positive_count' => 0,
                'negative_count' => 0,
                'neutral_count' => 0,
                'mixed_count' => 0
            ];
        }

        $scores = array_column($results, 'sentiment_score');
        $magnitudes = array_column($results, 'sentiment_magnitude');
        
        $sentimentCounts = [
            'positive' => 0,
            'negative' => 0,
            'neutral' => 0,
            'mixed' => 0
        ];

        foreach ($results as $result) {
            $label = $result['sentiment_label'] ?? 'neutral';
            if (isset($sentimentCounts[$label])) {
                $sentimentCounts[$label]++;
            }
        }

        return [
            'total_documents' => count($results),
            'avg_sentiment_score' => count($scores) > 0 ? array_sum($scores) / count($scores) : 0.0,
            'avg_magnitude' => count($magnitudes) > 0 ? array_sum($magnitudes) / count($magnitudes) : 0.0,
            'positive_count' => $sentimentCounts['positive'],
            'negative_count' => $sentimentCounts['negative'],
            'neutral_count' => $sentimentCounts['neutral'],
            'mixed_count' => $sentimentCounts['mixed'],
            'min_sentiment' => count($scores) > 0 ? min($scores) : 0.0,
            'max_sentiment' => count($scores) > 0 ? max($scores) : 0.0,
        ];
    }

    /**
     * Complete batch processing
     */
    private function completeBatch(SentimentBatch $batch, array $results): void
    {
        $successCount = count(array_filter($results, fn($r) => $r['error'] === null));
        $failureCount = count($results) - $successCount;
        
        $status = $failureCount === 0 ? 'completed' : 
                 ($successCount === 0 ? 'failed' : 'partially_completed');

        $batch->update([
            'status' => $status,
            'processed_documents' => $successCount,
            'failed_documents' => $failureCount,
            'completed_at' => now(),
            'processing_summary' => [
                'total_processed' => count($results),
                'success_count' => $successCount,
                'failure_count' => $failureCount,
                'success_rate' => count($results) > 0 ? ($successCount / count($results)) * 100 : 0
            ]
        ]);

        Log::info('Batch processing completed', [
            'batch_id' => $batch->id,
            'status' => $status,
            'success_count' => $successCount,
            'failure_count' => $failureCount
        ]);
    }

    /**
     * Get batch processing status
     */
    public function getBatchStatus(int $batchId): array
    {
        $batch = SentimentBatch::with(['documents'])->find($batchId);
        
        if (!$batch) {
            return ['error' => 'Batch not found'];
        }

        return [
            'batch_id' => $batch->id,
            'name' => $batch->name,
            'status' => $batch->status,
            'total_documents' => $batch->total_documents,
            'processed_documents' => $batch->processed_documents,
            'failed_documents' => $batch->failed_documents,
            'progress_percentage' => $batch->total_documents > 0 
                ? ($batch->processed_documents / $batch->total_documents) * 100 
                : 0,
            'started_at' => $batch->started_at,
            'completed_at' => $batch->completed_at,
            'processing_summary' => $batch->processing_summary,
        ];
    }

    /**
     * Get daily aggregates for date range
     */
    public function getDailyAggregates(
        Carbon $startDate,
        Carbon $endDate,
        ?string $platform = null,
        ?string $category = null
    ): array {
        $query = DailySentimentAggregate::whereBetween('date', [
            $startDate->toDateString(),
            $endDate->toDateString()
        ]);

        if ($platform) {
            $query->where('platform', $platform);
        }

        if ($category) {
            $query->where('keyword_category', $category);
        }

        return $query->orderBy('date')
                    ->get()
                    ->map(fn($aggregate) => [
                        'date' => $aggregate->date,
                        'platform' => $aggregate->platform,
                        'category' => $aggregate->keyword_category,
                        'total_documents' => $aggregate->total_documents,
                        'avg_sentiment_score' => $aggregate->avg_sentiment_score,
                        'positive_count' => $aggregate->positive_count,
                        'negative_count' => $aggregate->negative_count,
                        'neutral_count' => $aggregate->neutral_count,
                    ])
                    ->toArray();
    }
}