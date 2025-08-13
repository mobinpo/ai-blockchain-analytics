<?php

declare(strict_types=1);

namespace App\Services\SentimentPipeline;

use App\Models\SocialMediaPost;
use App\Models\SentimentBatch;
use App\Models\SentimentBatchDocument;
use App\Models\DailySentimentAggregate;
use App\Services\GoogleSentimentService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Carbon\Carbon;
use Exception;

class BatchSentimentProcessor
{
    private GoogleSentimentService $sentimentService;
    private array $config;

    public function __construct(GoogleSentimentService $sentimentService)
    {
        $this->sentimentService = $sentimentService;
        $this->config = config('sentiment_pipeline', []);
    }

    /**
     * Process social media posts from crawler in batches
     */
    public function processPostsBatch(array $posts, array $options = []): array
    {
        $startTime = microtime(true);
        $batchId = uniqid('sentiment_batch_');
        
        Log::info('Starting batch sentiment processing', [
            'batch_id' => $batchId,
            'posts_count' => count($posts),
            'options' => $options
        ]);

        try {
            // Create batch record
            $batch = $this->createSentimentBatch($batchId, count($posts), $options);
            
            // Filter and prepare posts for processing
            $validPosts = $this->filterValidPosts($posts);
            
            if (empty($validPosts)) {
                throw new Exception('No valid posts found for sentiment analysis');
            }

            // Create batch documents
            $documents = $this->createBatchDocuments($batch, $validPosts);
            
            // Process documents in chunks
            $processedResults = $this->processDocuments($documents);
            
            // Update batch status
            $this->updateBatchStatus($batch, 'completed', $processedResults);
            
            // Generate daily aggregates
            if ($options['generate_aggregates'] ?? true) {
                $aggregates = $this->generateDailyAggregates($validPosts, $processedResults);
            }

            $executionTime = microtime(true) - $startTime;
            
            $result = [
                'success' => true,
                'batch_id' => $batchId,
                'posts_processed' => $processedResults['processed'],
                'posts_failed' => $processedResults['failed'],
                'execution_time' => $executionTime,
                'cost_estimate' => $processedResults['total_cost'],
                'aggregates_generated' => $aggregates ?? [],
                'sentiment_distribution' => $processedResults['sentiment_distribution'] ?? []
            ];

            Log::info('Batch sentiment processing completed', $result);
            
            return $result;

        } catch (Exception $e) {
            Log::error('Batch sentiment processing failed', [
                'batch_id' => $batchId,
                'error' => $e->getMessage(),
                'execution_time' => microtime(true) - $startTime
            ]);

            if (isset($batch)) {
                $this->updateBatchStatus($batch, 'failed', ['error' => $e->getMessage()]);
            }

            throw $e;
        }
    }

    /**
     * Process texts directly (bypassing post structure)
     */
    public function processTextBatch(array $texts, array $metadata = []): array
    {
        $batchId = uniqid('text_batch_');
        $startTime = microtime(true);
        
        Log::info('Processing text batch for sentiment analysis', [
            'batch_id' => $batchId,
            'texts_count' => count($texts)
        ]);

        try {
            $results = [];
            $totalCost = 0.0;
            $sentimentDistribution = [
                'positive' => 0,
                'negative' => 0,
                'neutral' => 0
            ];

            // Process texts in chunks to respect rate limits
            $chunks = array_chunk($texts, $this->config['batch_size'] ?? 25);
            
            foreach ($chunks as $chunkIndex => $chunk) {
                Log::info('Processing text chunk', [
                    'batch_id' => $batchId,
                    'chunk_index' => $chunkIndex,
                    'chunk_size' => count($chunk)
                ]);

                $chunkResults = $this->processTextChunk($chunk, $chunkIndex);
                $results = array_merge($results, $chunkResults['results']);
                $totalCost += $chunkResults['cost'];
                
                // Update sentiment distribution
                foreach ($chunkResults['sentiment_distribution'] as $sentiment => $count) {
                    $sentimentDistribution[$sentiment] += $count;
                }

                // Rate limiting between chunks
                if (count($chunks) > 1) {
                    $delay = $this->config['chunk_delay_ms'] ?? 1000;
                    usleep($delay * 1000);
                }
            }

            $executionTime = microtime(true) - $startTime;

            $summary = [
                'batch_id' => $batchId,
                'texts_processed' => count($results),
                'execution_time' => $executionTime,
                'cost_estimate' => $totalCost,
                'sentiment_distribution' => $sentimentDistribution,
                'results' => $results
            ];

            Log::info('Text batch processing completed', $summary);
            
            return $summary;

        } catch (Exception $e) {
            Log::error('Text batch processing failed', [
                'batch_id' => $batchId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Generate daily sentiment aggregates from processed posts
     */
    public function generateDailyAggregates(array $posts, array $processingResults): array
    {
        $aggregates = [];
        $date = Carbon::now()->format('Y-m-d');
        
        Log::info('Generating daily sentiment aggregates', [
            'date' => $date,
            'posts_count' => count($posts)
        ]);

        try {
            // Group posts by platform and keyword categories
            $groupedData = $this->groupPostsForAggregation($posts, $processingResults);
            
            foreach ($groupedData as $groupKey => $groupData) {
                $aggregate = $this->createOrUpdateDailyAggregate([
                    'date' => $date,
                    'platform' => $groupData['platform'],
                    'category' => $groupData['category'],
                    'total_posts' => count($groupData['posts']),
                    'sentiment_scores' => $groupData['sentiment_scores'],
                    'keywords' => $groupData['keywords']
                ]);
                
                $aggregates[] = $aggregate;
            }

            Log::info('Daily aggregates generated', [
                'date' => $date,
                'aggregates_created' => count($aggregates)
            ]);
            
            return $aggregates;

        } catch (Exception $e) {
            Log::error('Failed to generate daily aggregates', [
                'date' => $date,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Queue batch processing for background execution
     */
    public function queueBatchProcessing(array $posts, array $options = []): string
    {
        $batchId = uniqid('queued_batch_');
        
        Log::info('Queueing batch sentiment processing', [
            'batch_id' => $batchId,
            'posts_count' => count($posts)
        ]);

        // Create initial batch record
        $batch = $this->createSentimentBatch($batchId, count($posts), array_merge($options, [
            'queued' => true,
            'queued_at' => now()
        ]));

        // Dispatch job to high priority queue
        Queue::pushOn(
            $this->config['queue_name'] ?? 'sentiment-analysis',
            new \App\Jobs\ProcessSentimentBatch($batch->id, $posts, $options)
        );

        return $batchId;
    }

    /**
     * Get batch processing status and results
     */
    public function getBatchStatus(string $batchId): ?array
    {
        $batch = SentimentBatch::where('batch_id', $batchId)->first();
        
        if (!$batch) {
            return null;
        }

        $documents = SentimentBatchDocument::where('batch_id', $batch->id)->get();
        
        return [
            'batch_id' => $batchId,
            'status' => $batch->status,
            'total_documents' => $batch->total_documents,
            'processed_documents' => $documents->where('status', 'completed')->count(),
            'failed_documents' => $documents->where('status', 'failed')->count(),
            'pending_documents' => $documents->where('status', 'pending')->count(),
            'started_at' => $batch->started_at,
            'completed_at' => $batch->completed_at,
            'results' => $batch->results,
            'execution_time' => $batch->completed_at ? 
                $batch->started_at->diffInSeconds($batch->completed_at) : null
        ];
    }

    /**
     * Create sentiment batch record
     */
    private function createSentimentBatch(string $batchId, int $totalDocuments, array $options): SentimentBatch
    {
        return SentimentBatch::create([
            'batch_id' => $batchId,
            'status' => 'processing',
            'total_documents' => $totalDocuments,
            'processed_documents' => 0,
            'failed_documents' => 0,
            'started_at' => now(),
            'config' => [
                'processor_version' => '1.0',
                'options' => $options,
                'google_nlp_config' => [
                    'sentiment_analysis' => true,
                    'entity_analysis' => $this->config['enable_entity_analysis'] ?? true,
                    'classification' => $this->config['enable_classification'] ?? true
                ]
            ]
        ]);
    }

    /**
     * Filter posts that are valid for sentiment analysis
     */
    private function filterValidPosts(array $posts): array
    {
        $valid = [];
        
        foreach ($posts as $post) {
            // Check if post has enough text content
            $text = $this->extractTextFromPost($post);
            
            if (strlen($text) >= ($this->config['min_text_length'] ?? 10) &&
                strlen($text) <= ($this->config['max_text_length'] ?? 5000)) {
                $valid[] = $post;
            }
        }
        
        Log::info('Posts filtered for sentiment analysis', [
            'total_posts' => count($posts),
            'valid_posts' => count($valid),
            'filtered_out' => count($posts) - count($valid)
        ]);
        
        return $valid;
    }

    /**
     * Create batch documents from posts
     */
    private function createBatchDocuments(SentimentBatch $batch, array $posts): array
    {
        $documents = [];
        
        foreach ($posts as $index => $post) {
            $document = SentimentBatchDocument::create([
                'batch_id' => $batch->id,
                'document_index' => $index,
                'original_text' => json_encode($post),
                'processed_text' => $this->extractTextFromPost($post),
                'metadata' => [
                    'platform' => $post['platform'] ?? 'unknown',
                    'post_id' => $post['id'] ?? null,
                    'author' => $post['author'] ?? null,
                    'created_at' => $post['created_at'] ?? null
                ],
                'status' => 'pending'
            ]);
            
            $documents[] = $document;
        }
        
        return $documents;
    }

    /**
     * Process documents using Google Cloud NLP
     */
    private function processDocuments(array $documents): array
    {
        return $this->sentimentService->processBatchDocuments($documents);
    }

    /**
     * Process a chunk of texts
     */
    private function processTextChunk(array $texts, int $chunkIndex): array
    {
        $results = [];
        $cost = 0.0;
        $sentimentDistribution = ['positive' => 0, 'negative' => 0, 'neutral' => 0];
        
        foreach ($texts as $textIndex => $text) {
            try {
                $analysis = $this->sentimentService->analyze($text);
                $sentimentScore = $analysis['documentSentiment']['score'] ?? 0.0;
                
                $result = [
                    'text_index' => $textIndex,
                    'chunk_index' => $chunkIndex,
                    'sentiment_score' => $sentimentScore,
                    'sentiment_magnitude' => $analysis['documentSentiment']['magnitude'] ?? 0.0,
                    'sentiment_label' => $this->categorizeSentiment($sentimentScore),
                    'language' => $analysis['language'] ?? 'unknown',
                    'processed_at' => now()->toISOString()
                ];
                
                $results[] = $result;
                $cost += $this->config['cost_per_request'] ?? 0.001;
                
                // Update sentiment distribution
                $sentiment = $this->categorizeSentiment($sentimentScore);
                if (in_array($sentiment, ['positive', 'very_positive'])) {
                    $sentimentDistribution['positive']++;
                } elseif (in_array($sentiment, ['negative', 'very_negative'])) {
                    $sentimentDistribution['negative']++;
                } else {
                    $sentimentDistribution['neutral']++;
                }
                
                // Rate limiting between requests
                $delay = $this->config['request_delay_ms'] ?? 100;
                if ($delay > 0) {
                    usleep($delay * 1000);
                }
                
            } catch (Exception $e) {
                Log::error('Failed to process text in chunk', [
                    'chunk_index' => $chunkIndex,
                    'text_index' => $textIndex,
                    'error' => $e->getMessage()
                ]);
                
                $results[] = [
                    'text_index' => $textIndex,
                    'chunk_index' => $chunkIndex,
                    'error' => $e->getMessage(),
                    'processed_at' => now()->toISOString()
                ];
            }
        }
        
        return [
            'results' => $results,
            'cost' => $cost,
            'sentiment_distribution' => $sentimentDistribution
        ];
    }

    /**
     * Update batch status
     */
    private function updateBatchStatus(SentimentBatch $batch, string $status, array $results): void
    {
        $batch->update([
            'status' => $status,
            'completed_at' => now(),
            'processed_documents' => $results['processed'] ?? 0,
            'failed_documents' => $results['failed'] ?? 0,
            'results' => $results
        ]);
    }

    /**
     * Group posts for aggregation
     */
    private function groupPostsForAggregation(array $posts, array $processingResults): array
    {
        $groups = [];
        
        foreach ($posts as $index => $post) {
            $platform = $post['platform'] ?? 'unknown';
            $category = $this->determinePostCategory($post);
            $groupKey = "{$platform}_{$category}";
            
            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'platform' => $platform,
                    'category' => $category,
                    'posts' => [],
                    'sentiment_scores' => [],
                    'keywords' => []
                ];
            }
            
            $groups[$groupKey]['posts'][] = $post;
            
            // Add sentiment score if available
            if (isset($processingResults['processed'])) {
                // Extract sentiment score from results
                $sentimentScore = $this->extractSentimentScoreFromResults($processingResults, $index);
                if ($sentimentScore !== null) {
                    $groups[$groupKey]['sentiment_scores'][] = $sentimentScore;
                }
            }
            
            // Add keywords
            if (!empty($post['keywords_matched'])) {
                $groups[$groupKey]['keywords'] = array_unique(
                    array_merge($groups[$groupKey]['keywords'], $post['keywords_matched'])
                );
            }
        }
        
        return $groups;
    }

    /**
     * Create or update daily aggregate
     */
    private function createOrUpdateDailyAggregate(array $data): DailySentimentAggregate
    {
        $aggregate = DailySentimentAggregate::updateOrCreate(
            [
                'date' => $data['date'],
                'platform' => $data['platform'],
                'category' => $data['category']
            ],
            [
                'total_posts' => DB::raw('total_posts + ' . $data['total_posts']),
                'positive_posts' => DB::raw('positive_posts + ' . $this->countPositivePosts($data['sentiment_scores'])),
                'negative_posts' => DB::raw('negative_posts + ' . $this->countNegativePosts($data['sentiment_scores'])),
                'neutral_posts' => DB::raw('neutral_posts + ' . $this->countNeutralPosts($data['sentiment_scores'])),
                'avg_sentiment_score' => $this->calculateAverageSentiment($data['sentiment_scores']),
                'sentiment_magnitude' => $this->calculateAverageMagnitude($data['sentiment_scores']),
                'top_keywords' => json_encode(array_slice($data['keywords'], 0, 10)),
                'updated_at' => now()
            ]
        );
        
        return $aggregate;
    }

    /**
     * Extract text from post array
     */
    private function extractTextFromPost(array $post): string
    {
        if (!empty($post['content'])) {
            return $post['content'];
        } elseif (!empty($post['text'])) {
            return $post['text'];
        } elseif (!empty($post['title'])) {
            $text = $post['title'];
            if (!empty($post['body'])) {
                $text .= "\n\n" . $post['body'];
            }
            return $text;
        }
        
        return '';
    }

    /**
     * Categorize sentiment score
     */
    private function categorizeSentiment(float $score): string
    {
        if ($score >= 0.6) return 'very_positive';
        if ($score >= 0.2) return 'positive';
        if ($score >= -0.2) return 'neutral';
        if ($score >= -0.6) return 'negative';
        return 'very_negative';
    }

    /**
     * Determine post category based on content/keywords
     */
    private function determinePostCategory(array $post): string
    {
        $keywords = $post['keywords_matched'] ?? [];
        
        foreach ($keywords as $keyword) {
            $keyword = strtolower($keyword);
            
            if (str_contains($keyword, 'security') || str_contains($keyword, 'hack') || str_contains($keyword, 'vulnerability')) {
                return 'security';
            }
            if (str_contains($keyword, 'defi') || str_contains($keyword, 'yield') || str_contains($keyword, 'liquidity')) {
                return 'defi';
            }
            if (str_contains($keyword, 'nft') || str_contains($keyword, 'metaverse')) {
                return 'nft';
            }
            if (str_contains($keyword, 'bitcoin') || str_contains($keyword, 'btc')) {
                return 'bitcoin';
            }
            if (str_contains($keyword, 'ethereum') || str_contains($keyword, 'eth')) {
                return 'ethereum';
            }
        }
        
        return 'general';
    }

    /**
     * Extract sentiment score from processing results
     */
    private function extractSentimentScoreFromResults(array $results, int $index): ?float
    {
        // This would depend on the structure of processing results
        // For now, return a placeholder
        return null;
    }

    /**
     * Count posts by sentiment
     */
    private function countPositivePosts(array $scores): int
    {
        return count(array_filter($scores, fn($score) => $score > 0.2));
    }

    private function countNegativePosts(array $scores): int
    {
        return count(array_filter($scores, fn($score) => $score < -0.2));
    }

    private function countNeutralPosts(array $scores): int
    {
        return count(array_filter($scores, fn($score) => $score >= -0.2 && $score <= 0.2));
    }

    /**
     * Calculate average sentiment
     */
    private function calculateAverageSentiment(array $scores): float
    {
        if (empty($scores)) return 0.0;
        return array_sum($scores) / count($scores);
    }

    private function calculateAverageMagnitude(array $scores): float
    {
        if (empty($scores)) return 0.0;
        return array_sum(array_map('abs', $scores)) / count($scores);
    }
}