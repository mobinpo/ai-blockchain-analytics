<?php

namespace App\Services\SentimentPipeline;

use App\Services\GoogleSentimentService;
use App\Services\CrawlerSentimentIntegration;
use App\Models\SocialMediaPost;
use App\Models\SentimentBatch;
use App\Models\SentimentBatchDocument;
use App\Models\DailySentimentAggregate;
use App\Models\TextPreprocessingCache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Exception;

class SentimentPipelineService
{
    private GoogleSentimentService $sentimentService;
    private CrawlerSentimentIntegration $crawlerIntegration;
    private array $config;

    public function __construct(
        GoogleSentimentService $sentimentService,
        CrawlerSentimentIntegration $crawlerIntegration
    ) {
        $this->sentimentService = $sentimentService;
        $this->crawlerIntegration = $crawlerIntegration;
        $this->config = config('sentiment_pipeline');
    }

    /**
     * Process text through complete sentiment pipeline
     */
    public function processTextPipeline(array $textData, array $options = []): array
    {
        $pipelineStartTime = microtime(true);
        $results = [
            'processed_count' => 0,
            'failed_count' => 0,
            'batch_id' => null,
            'processing_time' => 0,
            'cost_estimate' => 0.0,
            'sentiment_summary' => [],
            'errors' => []
        ];

        try {
            Log::info('Starting sentiment pipeline processing', [
                'text_count' => count($textData),
                'options' => $options
            ]);

            // Step 1: Create sentiment batch for tracking
            $batch = $this->createSentimentBatch($textData, $options);
            $results['batch_id'] = $batch->id;

            // Step 2: Preprocess and validate text data
            $preprocessedData = $this->preprocessTextData($textData, $batch);
            
            // Step 3: Create batch documents
            $documents = $this->createBatchDocuments($batch, $preprocessedData);

            // Step 4: Process through Google NLP API
            $nlpResults = $this->sentimentService->processBatchDocuments($documents->toArray());
            
            // Step 5: Update results
            $results['processed_count'] = $nlpResults['processed'];
            $results['failed_count'] = $nlpResults['failed'];
            $results['cost_estimate'] = $nlpResults['total_cost'];
            $results['errors'] = $nlpResults['errors'] ?? [];

            // Step 6: Update batch status
            $batch->update([
                'status' => $nlpResults['failed'] > 0 ? 'completed_with_errors' : 'completed',
                'processed_documents' => $nlpResults['processed'],
                'failed_documents' => $nlpResults['failed'],
                'total_cost' => $nlpResults['total_cost'],
                'processing_time' => $nlpResults['processing_time'],
                'completed_at' => now()
            ]);

            // Step 7: Generate sentiment summary
            $results['sentiment_summary'] = $this->generateSentimentSummary($documents);

            // Step 8: Trigger daily aggregation if needed
            if ($options['trigger_aggregation'] ?? false) {
                $this->scheduleAggregation();
            }

            $results['processing_time'] = microtime(true) - $pipelineStartTime;

            Log::info('Sentiment pipeline processing completed', $results);

        } catch (Exception $e) {
            Log::error('Sentiment pipeline processing failed', [
                'error' => $e->getMessage(),
                'text_count' => count($textData),
                'batch_id' => $results['batch_id']
            ]);

            // Update batch with error status
            if (isset ($batch)) {
                $batch->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'failed_at' => now()
                ]);
            }

            throw $e;
        }

        return $results;
    }

    /**
     * Process social media posts from crawler
     */
    public function processCrawlerData(array $crawlerResults, array $options = []): array
    {
        $results = [
            'processed_posts' => 0,
            'failed_posts' => 0,
            'sentiment_analysis_results' => [],
            'aggregation_triggered' => false,
            'processing_time' => 0,
            'platforms_processed' => [],
            'errors' => []
        ];

        $startTime = microtime(true);

        try {
            Log::info('Processing crawler data through sentiment pipeline', [
                'crawler_results_count' => count($crawlerResults),
                'options' => $options
            ]);

            // Extract social media posts from crawler results
            $posts = $this->extractPostsFromCrawlerResults($crawlerResults);
            
            if (empty($posts)) {
                Log::warning('No posts found in crawler results');
                return $results;
            }

            // Group posts by platform for processing
            $postsByPlatform = collect($posts)->groupBy('platform');
            
            foreach ($postsByPlatform as $platform => $platformPosts) {
                try {
                    Log::info("Processing {$platform} posts", [
                        'post_count' => count($platformPosts)
                    ]);

                    // Process platform posts through sentiment analysis
                    $platformResults = $this->sentimentService->processSocialMediaPosts($platformPosts->toArray());
                    
                    $results['processed_posts'] += $platformResults['processed'];
                    $results['failed_posts'] += $platformResults['failed'];
                    $results['sentiment_analysis_results'][$platform] = [
                        'processed' => $platformResults['processed'],
                        'failed' => $platformResults['failed'],
                        'sentiment_distribution' => $platformResults['sentiment_distribution'],
                        'cost' => $platformResults['total_cost'],
                        'processing_time' => $platformResults['processing_time']
                    ];
                    $results['platforms_processed'][] = $platform;
                    $results['errors'] = array_merge($results['errors'], $platformResults['errors']);

                    // Store processed results
                    $this->storeProcessedPosts($platformResults, $platform, $options);

                } catch (Exception $e) {
                    Log::error("Failed to process {$platform} posts", [
                        'error' => $e->getMessage(),
                        'post_count' => count($platformPosts)
                    ]);
                    
                    $results['errors'][] = [
                        'platform' => $platform,
                        'error' => $e->getMessage(),
                        'post_count' => count($platformPosts)
                    ];
                }
            }

            // Trigger daily aggregation if enough data processed
            if ($results['processed_posts'] >= ($options['aggregation_threshold'] ?? 100)) {
                $this->scheduleAggregation();
                $results['aggregation_triggered'] = true;
            }

            $results['processing_time'] = microtime(true) - $startTime;

            Log::info('Crawler data sentiment processing completed', $results);

        } catch (Exception $e) {
            Log::error('Failed to process crawler data through sentiment pipeline', [
                'error' => $e->getMessage(),
                'crawler_results_count' => count($crawlerResults)
            ]);
            throw $e;
        }

        return $results;
    }

    /**
     * Create a sentiment batch for tracking
     */
    private function createSentimentBatch(array $textData, array $options): SentimentBatch
    {
        return SentimentBatch::create([
            'name' => $options['batch_name'] ?? 'Text Processing Batch',
            'description' => $options['description'] ?? 'Automated sentiment analysis batch',
            'total_documents' => count($textData),
            'source_type' => $options['source_type'] ?? 'manual',
            'source_metadata' => $options['metadata'] ?? [],
            'configuration' => [
                'preprocessing_options' => $options['preprocessing'] ?? [],
                'analysis_options' => $options['analysis'] ?? [],
                'aggregation_enabled' => $options['trigger_aggregation'] ?? false
            ],
            'status' => 'processing',
            'created_at' => now(),
            'started_at' => now()
        ]);
    }

    /**
     * Preprocess text data before analysis
     */
    private function preprocessTextData(array $textData, SentimentBatch $batch): array
    {
        $preprocessed = [];
        $preprocessingConfig = $this->config['preprocessing'];

        foreach ($textData as $index => $item) {
            try {
                // Extract text content
                $text = $this->extractTextContent($item);
                
                if (empty($text)) {
                    Log::debug('Skipping empty text item', ['index' => $index]);
                    continue;
                }

                // Check if already cached
                $cacheKey = 'preprocessed_text_' . hash('sha256', $text);
                $preprocessedText = null;
                
                if ($preprocessingConfig['cache_cleanup_days'] > 0) {
                    $preprocessedText = Cache::get($cacheKey);
                }

                if (!$preprocessedText) {
                    // Apply preprocessing steps
                    $preprocessedText = $this->applyTextPreprocessing($text, $preprocessingConfig);
                    
                    // Cache the result
                    if ($preprocessingConfig['cache_cleanup_days'] > 0) {
                        Cache::put($cacheKey, $preprocessedText, now()->addDays($preprocessingConfig['cache_cleanup_days']));
                    }
                }

                // Validate processed text
                if ($this->validateProcessedText($preprocessedText, $preprocessingConfig)) {
                    $preprocessed[] = [
                        'original_index' => $index,
                        'original_text' => $text,
                        'processed_text' => $preprocessedText,
                        'text_length' => strlen($preprocessedText),
                        'language' => $this->detectLanguage($preprocessedText),
                        'preprocessing_metadata' => [
                            'preprocessing_time' => microtime(true),
                            'cache_hit' => Cache::has($cacheKey)
                        ]
                    ];
                }

            } catch (Exception $e) {
                Log::warning('Failed to preprocess text item', [
                    'index' => $index,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Text preprocessing completed', [
            'batch_id' => $batch->id,
            'original_count' => count($textData),
            'preprocessed_count' => count($preprocessed)
        ]);

        return $preprocessed;
    }

    /**
     * Extract text content from various input formats
     */
    private function extractTextContent($item): string
    {
        if (is_string($item)) {
            return $item;
        }

        if (is_array($item)) {
            return $item['text'] ?? $item['content'] ?? $item['title'] ?? '';
        }

        if (is_object($item)) {
            return $item->text ?? $item->content ?? $item->title ?? '';
        }

        return '';
    }

    /**
     * Apply text preprocessing steps
     */
    private function applyTextPreprocessing(string $text, array $config): string
    {
        $processed = $text;

        // Remove URLs
        if ($config['remove_urls']) {
            $processed = preg_replace('/https?:\/\/[^\s]+/', '', $processed);
        }

        // Remove emails
        if ($config['remove_emails']) {
            $processed = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '', $processed);
        }

        // Clean social markers
        if ($config['clean_social_markers']) {
            $processed = preg_replace('/@\w+/', '', $processed); // Remove mentions
            $processed = preg_replace('/#\w+/', '', $processed); // Remove hashtags
        }

        // Normalize whitespace
        if ($config['normalize_whitespace']) {
            $processed = preg_replace('/\s+/', ' ', $processed);
        }

        // Remove special characters
        if ($config['remove_special_chars']) {
            $processed = preg_replace('/[^\w\s.,!?-]/', '', $processed);
        }

        // Convert to lowercase
        if ($config['to_lowercase']) {
            $processed = strtolower($processed);
        }

        // Remove short words
        if ($config['remove_short_words']) {
            $processed = preg_replace('/\b\w{1,2}\b/', '', $processed);
        }

        return trim($processed);
    }

    /**
     * Validate processed text
     */
    private function validateProcessedText(string $text, array $config): bool
    {
        $length = strlen($text);
        
        return $length >= $config['min_text_length'] && 
               $length <= $config['max_text_length'];
    }

    /**
     * Detect language of text (simplified)
     */
    private function detectLanguage(string $text): string
    {
        // Simple heuristic-based language detection
        $englishWords = ['the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'];
        $textLower = strtolower($text);
        $englishScore = 0;

        foreach ($englishWords as $word) {
            if (strpos($textLower, ' ' . $word . ' ') !== false) {
                $englishScore++;
            }
        }

        return $englishScore >= 2 ? 'en' : 'unknown';
    }

    /**
     * Create batch documents for processing
     */
    private function createBatchDocuments(SentimentBatch $batch, array $preprocessedData): Collection
    {
        $documents = collect();

        foreach ($preprocessedData as $data) {
            $document = SentimentBatchDocument::create([
                'sentiment_batch_id' => $batch->id,
                'original_text' => $data['original_text'],
                'processed_text' => $data['processed_text'],
                'text_length' => $data['text_length'],
                'detected_language' => $data['language'],
                'processing_metadata' => $data['preprocessing_metadata'],
                'status' => 'pending',
                'created_at' => now()
            ]);

            $documents->push($document);
        }

        return $documents;
    }

    /**
     * Generate sentiment summary from processed documents
     */
    private function generateSentimentSummary(Collection $documents): array
    {
        $summary = [
            'total_documents' => $documents->count(),
            'sentiment_distribution' => [
                'positive' => 0,
                'negative' => 0,
                'neutral' => 0
            ],
            'average_sentiment_score' => 0.0,
            'average_magnitude' => 0.0,
            'language_distribution' => [],
            'processing_stats' => []
        ];

        $completedDocuments = $documents->where('status', 'completed');
        
        if ($completedDocuments->isEmpty()) {
            return $summary;
        }

        // Calculate sentiment distribution
        $sentimentScores = [];
        $magnitudes = [];
        $languages = [];

        foreach ($completedDocuments as $document) {
            $score = $document->sentiment_score ?? 0;
            $magnitude = $document->magnitude ?? 0;
            $language = $document->detected_language ?? 'unknown';

            $sentimentScores[] = $score;
            $magnitudes[] = $magnitude;
            $languages[] = $language;

            // Categorize sentiment
            if ($score >= 0.2) {
                $summary['sentiment_distribution']['positive']++;
            } elseif ($score <= -0.2) {
                $summary['sentiment_distribution']['negative']++;
            } else {
                $summary['sentiment_distribution']['neutral']++;
            }
        }

        // Calculate averages
        $summary['average_sentiment_score'] = count($sentimentScores) > 0 ? array_sum($sentimentScores) / count($sentimentScores) : 0;
        $summary['average_magnitude'] = count($magnitudes) > 0 ? array_sum($magnitudes) / count($magnitudes) : 0;

        // Language distribution
        $summary['language_distribution'] = array_count_values($languages);

        return $summary;
    }

    /**
     * Extract posts from crawler results
     */
    private function extractPostsFromCrawlerResults(array $crawlerResults): array
    {
        $posts = [];

        foreach ($crawlerResults as $result) {
            if (isset($result['posts']) && is_array($result['posts'])) {
                $posts = array_merge($posts, $result['posts']);
            } elseif (isset($result['data']) && is_array($result['data'])) {
                $posts = array_merge($posts, $result['data']);
            } else {
                // Treat the result itself as a post
                $posts[] = $result;
            }
        }

        return $posts;
    }

    /**
     * Store processed posts results
     */
    private function storeProcessedPosts(array $results, string $platform, array $options): void
    {
        // This would typically store results in a database
        // For now, we'll cache the results
        $cacheKey = "processed_posts_{$platform}_" . date('Y-m-d-H');
        $existingData = Cache::get($cacheKey, []);
        
        $existingData[] = [
            'timestamp' => now()->toISOString(),
            'platform' => $platform,
            'processed_count' => $results['processed'],
            'failed_count' => $results['failed'],
            'sentiment_distribution' => $results['sentiment_distribution'],
            'cost' => $results['total_cost'],
            'options' => $options
        ];

        Cache::put($cacheKey, $existingData, now()->addDays(7));
    }

    /**
     * Schedule daily aggregation
     */
    private function scheduleAggregation(): void
    {
        Queue::push(new \App\Jobs\GenerateDailySentimentAggregates());
        
        Log::info('Daily sentiment aggregation scheduled');
    }

    /**
     * Get pipeline status and statistics
     */
    public function getPipelineStatus(): array
    {
        return [
            'active_batches' => SentimentBatch::where('status', 'processing')->count(),
            'completed_batches_today' => SentimentBatch::whereDate('completed_at', today())->count(),
            'total_documents_processed_today' => SentimentBatchDocument::whereDate('created_at', today())
                ->where('status', 'completed')->count(),
            'failed_documents_today' => SentimentBatchDocument::whereDate('created_at', today())
                ->where('status', 'failed')->count(),
            'google_nlp_usage' => $this->sentimentService->getUsageStatistics(),
            'last_aggregation' => DailySentimentAggregate::latest('date')->first()?->date,
            'pipeline_health' => $this->checkPipelineHealth()
        ];
    }

    /**
     * Check pipeline health
     */
    private function checkPipelineHealth(): array
    {
        $health = [
            'status' => 'healthy',
            'issues' => []
        ];

        // Check Google NLP service health
        $nlpHealth = $this->sentimentService->healthCheck();
        if ($nlpHealth['status'] !== 'healthy') {
            $health['status'] = 'degraded';
            $health['issues'][] = 'Google NLP service is unhealthy: ' . ($nlpHealth['error'] ?? 'Unknown error');
        }

        // Check for processing delays
        $oldestPendingBatch = SentimentBatch::where('status', 'processing')
            ->where('created_at', '<', now()->subHours(24))
            ->first();
            
        if ($oldestPendingBatch) {
            $health['status'] = 'degraded';
            $health['issues'][] = 'Processing batch delayed more than 24 hours';
        }

        // Check failure rate
        $todayBatches = SentimentBatch::whereDate('created_at', today());
        $totalBatches = $todayBatches->count();
        $failedBatches = $todayBatches->where('status', 'failed')->count();
        
        if ($totalBatches > 0 && ($failedBatches / $totalBatches) > 0.1) {
            $health['status'] = 'unhealthy';
            $health['issues'][] = 'High failure rate: ' . round(($failedBatches / $totalBatches) * 100, 1) . '%';
        }

        return $health;
    }

    /**
     * Clean up old processing data
     */
    public function cleanupOldData(): array
    {
        $cleanupResults = [
            'batches_cleaned' => 0,
            'documents_cleaned' => 0,
            'cache_entries_cleaned' => 0
        ];

        // Clean up old completed batches
        $oldBatches = SentimentBatch::where('completed_at', '<', now()->subDays($this->config['retention']['keep_batches_days']))->get();
        
        foreach ($oldBatches as $batch) {
            $batch->documents()->delete();
            $batch->delete();
            $cleanupResults['batches_cleaned']++;
        }

        // Clean up old preprocessing cache
        TextPreprocessingCache::where('created_at', '<', now()->subDays($this->config['preprocessing']['cache_cleanup_days']))->delete();

        Log::info('Sentiment pipeline cleanup completed', $cleanupResults);

        return $cleanupResults;
    }
}