<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DailySentimentAggregate;
use App\Services\SentimentPipeline\GoogleCloudBatchProcessor;
use App\Services\SentimentPipeline\DailySentimentAggregator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Enhanced Google Cloud NLP Batch Processing Job
 * 
 * Processes text data through Google Cloud Natural Language API in batches
 * and automatically generates daily sentiment aggregates.
 */
class GoogleCloudNLPBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes
    public int $tries = 3;
    public int $maxExceptions = 3;

    protected array $textData;
    protected array $options;
    protected Carbon $processDate;

    /**
     * Create a new job instance.
     */
    public function __construct(array $textData, array $options = [])
    {
        $this->textData = $textData;
        $this->options = array_merge([
            'platform' => 'general',
            'keyword' => null,
            'language' => 'en',
            'auto_aggregate' => true,
            'batch_size' => 25,
            'enable_entities' => true,
            'enable_classification' => true,
            'cost_limit' => 100.0, // $100 USD limit
        ], $options);
        
        $this->processDate = $options['process_date'] ?? Carbon::now();
        
        // Set queue based on priority
        $this->onQueue($options['priority'] ?? 'sentiment');
    }

    /**
     * Execute the job.
     */
    public function handle(
        GoogleCloudBatchProcessor $batchProcessor,
        DailySentimentAggregator $aggregator
    ): void {
        $jobStartTime = microtime(true);
        
        Log::info('Starting Google Cloud NLP batch processing job', [
            'text_count' => count($this->textData),
            'platform' => $this->options['platform'],
            'keyword' => $this->options['keyword'],
            'process_date' => $this->processDate->toDateString(),
            'job_id' => $this->job->getJobId(),
        ]);

        try {
            // Step 1: Process text through Google Cloud NLP
            $nlpResults = $this->processTextThroughNLP($batchProcessor);
            
            // Step 2: Validate and clean results
            $validatedResults = $this->validateNLPResults($nlpResults);
            
            // Step 3: Generate daily aggregates if enabled
            if ($this->options['auto_aggregate']) {
                $aggregateResults = $this->generateDailyAggregates($aggregator, $validatedResults);
            }
            
            // Step 4: Log completion metrics
            $this->logCompletionMetrics($nlpResults, $aggregateResults ?? [], $jobStartTime);
            
            Log::info('Google Cloud NLP batch processing completed successfully', [
                'processed_count' => $nlpResults['processed_count'],
                'failed_count' => $nlpResults['failed_count'],
                'total_cost' => $nlpResults['total_cost'],
                'processing_time' => microtime(true) - $jobStartTime,
            ]);

        } catch (\Exception $e) {
            Log::error('Google Cloud NLP batch processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'text_count' => count($this->textData),
                'platform' => $this->options['platform'],
            ]);
            
            throw $e;
        }
    }

    /**
     * Process text data through Google Cloud NLP API
     */
    private function processTextThroughNLP(GoogleCloudBatchProcessor $processor): array
    {
        $processingOptions = [
            'batch_size' => $this->options['batch_size'],
            'enable_sentiment' => true,
            'enable_entities' => $this->options['enable_entities'],
            'enable_classification' => $this->options['enable_classification'],
            'cost_limit' => $this->options['cost_limit'],
            'platform' => $this->options['platform'],
            'keyword' => $this->options['keyword'],
        ];

        return $processor->processBatch($this->textData, $processingOptions);
    }

    /**
     * Validate and clean NLP results
     */
    private function validateNLPResults(array $nlpResults): array
    {
        $validated = [
            'valid_results' => [],
            'invalid_results' => [],
            'total_valid' => 0,
            'total_invalid' => 0,
        ];

        foreach ($nlpResults['results'] ?? [] as $result) {
            if ($this->isValidSentimentResult($result)) {
                $validated['valid_results'][] = $this->enrichResult($result);
                $validated['total_valid']++;
            } else {
                $validated['invalid_results'][] = $result;
                $validated['total_invalid']++;
                
                Log::warning('Invalid sentiment result detected', [
                    'result' => $result,
                    'validation_errors' => $this->getValidationErrors($result),
                ]);
            }
        }

        return $validated;
    }

    /**
     * Check if sentiment result is valid
     */
    private function isValidSentimentResult(array $result): bool
    {
        // Check required fields
        if (!isset($result['sentiment'])) {
            return false;
        }

        $sentiment = $result['sentiment'];
        
        // Validate sentiment score and magnitude
        if (!isset($sentiment['score']) || !isset($sentiment['magnitude'])) {
            return false;
        }

        // Validate score range (-1 to 1)
        if ($sentiment['score'] < -1 || $sentiment['score'] > 1) {
            return false;
        }

        // Validate magnitude range (0 to infinity, but typically 0-1)
        if ($sentiment['magnitude'] < 0) {
            return false;
        }

        return true;
    }

    /**
     * Enrich result with additional metadata
     */
    private function enrichResult(array $result): array
    {
        $enriched = $result;
        
        // Add sentiment classification
        $score = $result['sentiment']['score'];
        $enriched['sentiment_classification'] = $this->classifySentiment($score);
        
        // Add processing metadata
        $enriched['processed_at'] = now()->toISOString();
        $enriched['platform'] = $this->options['platform'];
        $enriched['keyword'] = $this->options['keyword'];
        $enriched['language'] = $this->detectLanguage($result);
        
        // Add confidence metrics
        $enriched['confidence_score'] = $this->calculateConfidence($result);
        
        return $enriched;
    }

    /**
     * Classify sentiment based on score
     */
    private function classifySentiment(float $score): string
    {
        return match (true) {
            $score >= 0.6 => 'very_positive',
            $score >= 0.2 => 'positive',
            $score >= -0.2 => 'neutral',
            $score >= -0.6 => 'negative',
            default => 'very_negative'
        };
    }

    /**
     * Detect language from result
     */
    private function detectLanguage(array $result): string
    {
        // Extract from Google Cloud NLP result if available
        if (isset($result['language'])) {
            return $result['language'];
        }
        
        // Default to configured language
        return $this->options['language'];
    }

    /**
     * Calculate confidence score based on magnitude and other factors
     */
    private function calculateConfidence(array $result): float
    {
        $magnitude = $result['sentiment']['magnitude'] ?? 0;
        $score = abs($result['sentiment']['score'] ?? 0);
        
        // Confidence increases with higher magnitude and more extreme scores
        $confidence = min(1.0, ($magnitude * 0.7) + ($score * 0.3));
        
        return round($confidence, 3);
    }

    /**
     * Get validation errors for result
     */
    private function getValidationErrors(array $result): array
    {
        $errors = [];
        
        if (!isset($result['sentiment'])) {
            $errors[] = 'Missing sentiment data';
        } else {
            $sentiment = $result['sentiment'];
            
            if (!isset($sentiment['score'])) {
                $errors[] = 'Missing sentiment score';
            } elseif ($sentiment['score'] < -1 || $sentiment['score'] > 1) {
                $errors[] = 'Invalid sentiment score range';
            }
            
            if (!isset($sentiment['magnitude'])) {
                $errors[] = 'Missing sentiment magnitude';
            } elseif ($sentiment['magnitude'] < 0) {
                $errors[] = 'Invalid sentiment magnitude';
            }
        }
        
        return $errors;
    }

    /**
     * Generate daily aggregates from processed results
     */
    private function generateDailyAggregates(
        DailySentimentAggregator $aggregator,
        array $validatedResults
    ): array {
        $aggregationOptions = [
            'date' => $this->processDate,
            'platform' => $this->options['platform'],
            'keyword' => $this->options['keyword'],
            'update_existing' => true,
            'include_hourly' => true,
            'extract_keywords' => true,
            'calculate_trends' => true,
        ];

        return $aggregator->aggregateResults($validatedResults['valid_results'], $aggregationOptions);
    }

    /**
     * Log completion metrics
     */
    private function logCompletionMetrics(
        array $nlpResults,
        array $aggregateResults,
        float $jobStartTime
    ): void {
        $metrics = [
            'processing_time' => microtime(true) - $jobStartTime,
            'text_processed' => count($this->textData),
            'nlp_success' => $nlpResults['processed_count'] ?? 0,
            'nlp_failures' => $nlpResults['failed_count'] ?? 0,
            'total_cost' => $nlpResults['total_cost'] ?? 0.0,
            'aggregates_created' => count($aggregateResults['created'] ?? []),
            'aggregates_updated' => count($aggregateResults['updated'] ?? []),
            'platform' => $this->options['platform'],
            'process_date' => $this->processDate->toDateString(),
        ];

        // Store metrics in cache for monitoring
        $cacheKey = "nlp_batch_metrics:" . $this->processDate->format('Y-m-d') . ":" . $this->options['platform'];
        $existingMetrics = cache($cacheKey, []);
        $existingMetrics[] = $metrics;
        
        cache([$cacheKey => $existingMetrics], 3600); // Cache for 1 hour

        Log::info('NLP batch processing metrics', $metrics);
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Google Cloud NLP batch job failed permanently', [
            'error' => $exception->getMessage(),
            'text_count' => count($this->textData),
            'platform' => $this->options['platform'],
            'keyword' => $this->options['keyword'],
            'attempts' => $this->attempts(),
        ]);

        // Optionally send alert or notification
        // This could integrate with monitoring services
    }

    /**
     * Calculate the number of seconds to wait before retrying
     */
    public function backoff(): array
    {
        return [30, 60, 120]; // 30s, 1m, 2m
    }

    /**
     * Get unique tags for this job
     */
    public function tags(): array
    {
        return [
            'sentiment',
            'google-nlp',
            'batch-processing',
            'platform:' . $this->options['platform'],
            'date:' . $this->processDate->toDateString(),
        ];
    }
}
