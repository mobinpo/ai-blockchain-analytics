<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\GoogleCloudNLPBatchJob;
use App\Models\DailySentimentAggregate;
use App\Services\SentimentPipeline\GoogleCloudBatchProcessor;
use App\Services\SentimentPipeline\DailySentimentAggregator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Carbon\Carbon;

/**
 * Enhanced Sentiment Pipeline Service
 * 
 * Orchestrates the complete text → Google Cloud NLP → Daily Aggregates pipeline
 * with advanced batching, monitoring, and error handling capabilities.
 */
final class EnhancedSentimentPipelineService
{
    private GoogleCloudBatchProcessor $batchProcessor;
    private DailySentimentAggregator $aggregator;
    private array $config;

    public function __construct(
        GoogleCloudBatchProcessor $batchProcessor,
        DailySentimentAggregator $aggregator
    ) {
        $this->batchProcessor = $batchProcessor;
        $this->aggregator = $aggregator;
        $this->config = config('sentiment_pipeline', []);
    }

    /**
     * Process text data through complete sentiment pipeline
     */
    public function processTextPipeline(
        array $textData,
        array $options = []
    ): array {
        $startTime = microtime(true);
        
        // Merge options with defaults
        $options = $this->mergeDefaultOptions($options);
        
        Log::info('Starting enhanced sentiment pipeline', [
            'text_count' => count($textData),
            'platform' => $options['platform'],
            'keyword' => $options['keyword'],
            'processing_mode' => $options['processing_mode'],
        ]);

        try {
            // Validate input data
            $validatedData = $this->validateAndPreprocessText($textData);
            
            if (empty($validatedData)) {
                return $this->createEmptyResult('No valid text data provided');
            }

            // Choose processing strategy based on volume and mode
            $result = match ($options['processing_mode']) {
                'immediate' => $this->processImmediate($validatedData, $options),
                'batched' => $this->processBatched($validatedData, $options),
                'queued' => $this->processQueued($validatedData, $options),
                default => $this->processAuto($validatedData, $options)
            };

            $result['processing_time'] = microtime(true) - $startTime;
            
            Log::info('Enhanced sentiment pipeline completed', [
                'result' => $result,
                'processing_time' => $result['processing_time'],
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Enhanced sentiment pipeline failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'text_count' => count($textData),
                'options' => $options,
            ]);
            
            return $this->createErrorResult($e->getMessage());
        }
    }

    /**
     * Process text data and automatically generate daily aggregates
     */
    public function processAndAggregate(
        array $textData,
        Carbon $targetDate = null,
        array $options = []
    ): array {
        $targetDate = $targetDate ?: Carbon::today();
        
        // Add aggregation options
        $options = array_merge($options, [
            'auto_aggregate' => true,
            'process_date' => $targetDate,
            'update_existing_aggregates' => true,
        ]);

        return $this->processTextPipeline($textData, $options);
    }

    /**
     * Queue multiple text batches for processing
     */
    public function queueMultipleBatches(
        array $batchedTextData,
        array $globalOptions = []
    ): array {
        $queuedJobs = [];
        $totalTexts = 0;

        foreach ($batchedTextData as $batchIndex => $textBatch) {
            $batchOptions = array_merge($globalOptions, [
                'batch_index' => $batchIndex,
                'processing_mode' => 'queued',
            ]);

            $job = new GoogleCloudNLPBatchJob($textBatch, $batchOptions);
            
            // Set priority based on batch size and urgency
            $priority = $this->calculateJobPriority($textBatch, $batchOptions);
            $job->onQueue($priority);

            $dispatchedJob = dispatch($job);
            
            $queuedJobs[] = [
                'job_id' => $dispatchedJob->getJobId(),
                'batch_index' => $batchIndex,
                'text_count' => count($textBatch),
                'queue' => $priority,
                'estimated_processing_time' => $this->estimateProcessingTime(count($textBatch)),
            ];

            $totalTexts += count($textBatch);
        }

        Log::info('Queued multiple sentiment processing batches', [
            'total_batches' => count($batchedTextData),
            'total_texts' => $totalTexts,
            'queued_jobs' => count($queuedJobs),
        ]);

        return [
            'status' => 'queued',
            'total_batches' => count($batchedTextData),
            'total_texts' => $totalTexts,
            'queued_jobs' => $queuedJobs,
            'estimated_completion' => now()->addSeconds(
                max(array_column($queuedJobs, 'estimated_processing_time'))
            ),
        ];
    }

    /**
     * Generate comprehensive daily aggregates for a date range
     */
    public function generateDailyAggregates(
        Carbon $startDate,
        Carbon $endDate,
        array $options = []
    ): array {
        $results = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            Log::info('Generating daily aggregates', [
                'date' => $current->toDateString(),
            ]);

            try {
                $dailyResult = $this->aggregator->generateDailyAggregates($current, $options);
                $results[$current->toDateString()] = $dailyResult;
                
            } catch (\Exception $e) {
                Log::error('Failed to generate daily aggregates', [
                    'date' => $current->toDateString(),
                    'error' => $e->getMessage(),
                ]);
                
                $results[$current->toDateString()] = [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }

            $current->addDay();
        }

        return [
            'date_range' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'processed_days' => count($results),
            'successful_days' => count(array_filter($results, fn($r) => ($r['status'] ?? '') !== 'failed')),
            'failed_days' => count(array_filter($results, fn($r) => ($r['status'] ?? '') === 'failed')),
            'results' => $results,
        ];
    }

    /**
     * Get pipeline performance metrics
     */
    public function getPerformanceMetrics(Carbon $date = null): array
    {
        $date = $date ?: Carbon::today();
        $cacheKey = "nlp_pipeline_metrics:" . $date->format('Y-m-d');
        
        $metrics = Cache::get($cacheKey, []);
        
        if (empty($metrics)) {
            // Generate metrics from database
            $metrics = $this->calculatePerformanceMetrics($date);
            Cache::put($cacheKey, $metrics, 300); // Cache for 5 minutes
        }

        return $metrics;
    }

    /**
     * Get current pipeline status
     */
    public function getPipelineStatus(): array
    {
        return [
            'timestamp' => now()->toISOString(),
            'google_nlp_status' => $this->checkGoogleNLPStatus(),
            'queue_status' => $this->checkQueueStatus(),
            'database_status' => $this->checkDatabaseStatus(),
            'recent_activity' => $this->getRecentActivity(),
            'performance_summary' => $this->getPerformanceSummary(),
        ];
    }

    /**
     * Estimate cost for processing text data
     */
    public function estimateProcessingCost(array $textData, array $options = []): array
    {
        $textCount = count($textData);
        
        // Google Cloud NLP pricing (approximate)
        $baseAnalysisPrice = 0.001; // $1 per 1000 requests
        $entityAnalysisPrice = 0.0005; // $0.50 per 1000 requests
        $classificationPrice = 0.002; // $2 per 1000 requests

        $baseCost = $textCount * $baseAnalysisPrice;
        $entityCost = ($options['enable_entities'] ?? true) ? $textCount * $entityAnalysisPrice : 0;
        $classificationCost = ($options['enable_classification'] ?? true) ? $textCount * $classificationPrice : 0;

        $totalCost = $baseCost + $entityCost + $classificationCost;

        return [
            'text_count' => $textCount,
            'breakdown' => [
                'sentiment_analysis' => round($baseCost, 4),
                'entity_analysis' => round($entityCost, 4),
                'classification' => round($classificationCost, 4),
            ],
            'total_estimated_cost' => round($totalCost, 4),
            'currency' => 'USD',
            'disclaimer' => 'Estimates based on Google Cloud NLP standard pricing. Actual costs may vary.',
        ];
    }

    // Private helper methods

    private function mergeDefaultOptions(array $options): array
    {
        return array_merge([
            'platform' => 'general',
            'keyword' => null,
            'language' => 'en',
            'processing_mode' => 'auto', // immediate, batched, queued, auto
            'batch_size' => 25,
            'enable_entities' => true,
            'enable_classification' => true,
            'auto_aggregate' => true,
            'cost_limit' => 100.0,
            'priority' => 'normal',
        ], $options);
    }

    private function validateAndPreprocessText(array $textData): array
    {
        $validated = [];
        
        foreach ($textData as $index => $text) {
            if (is_string($text) && trim($text) !== '') {
                $cleaned = $this->cleanText($text);
                if (strlen($cleaned) > 10) { // Minimum viable text length
                    $validated[] = [
                        'original_index' => $index,
                        'text' => $cleaned,
                        'length' => strlen($cleaned),
                    ];
                }
            }
        }

        return $validated;
    }

    private function cleanText(string $text): string
    {
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', trim($text));
        
        // Remove URLs (optional)
        $text = preg_replace('/https?:\/\/[^\s]+/', '[URL]', $text);
        
        // Remove email addresses (optional)
        $text = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '[EMAIL]', $text);
        
        return $text;
    }

    private function processImmediate(array $textData, array $options): array
    {
        $nlpResults = $this->batchProcessor->processBatch($textData, $options);
        
        if ($options['auto_aggregate']) {
            $aggregateResults = $this->aggregator->aggregateResults(
                $nlpResults['results'] ?? [],
                $options
            );
            $nlpResults['aggregation'] = $aggregateResults;
        }

        return $nlpResults;
    }

    private function processBatched(array $textData, array $options): array
    {
        $batchSize = $options['batch_size'];
        $batches = array_chunk($textData, $batchSize);
        $allResults = [];

        foreach ($batches as $batchIndex => $batch) {
            Log::info("Processing batch {$batchIndex}", ['size' => count($batch)]);
            
            $batchResults = $this->batchProcessor->processBatch($batch, $options);
            $allResults[] = $batchResults;
            
            // Small delay between batches to avoid rate limiting
            if (count($batches) > 1 && $batchIndex < count($batches) - 1) {
                sleep(1);
            }
        }

        return $this->mergeBatchResults($allResults);
    }

    private function processQueued(array $textData, array $options): array
    {
        $job = new GoogleCloudNLPBatchJob($textData, $options);
        $priority = $this->calculateJobPriority($textData, $options);
        
        $dispatchedJob = dispatch($job->onQueue($priority));

        return [
            'status' => 'queued',
            'job_id' => $dispatchedJob->getJobId(),
            'queue' => $priority,
            'text_count' => count($textData),
            'estimated_processing_time' => $this->estimateProcessingTime(count($textData)),
        ];
    }

    private function processAuto(array $textData, array $options): array
    {
        $textCount = count($textData);
        
        // Auto-decide based on volume
        if ($textCount <= 10) {
            return $this->processImmediate($textData, $options);
        } elseif ($textCount <= 100) {
            return $this->processBatched($textData, $options);
        } else {
            return $this->processQueued($textData, $options);
        }
    }

    private function calculateJobPriority(array $textData, array $options): string
    {
        $textCount = count($textData);
        $priority = $options['priority'] ?? 'normal';
        
        return match ($priority) {
            'urgent' => 'high',
            'high' => 'high',
            'low' => 'low',
            default => $textCount > 100 ? 'low' : 'default'
        };
    }

    private function estimateProcessingTime(int $textCount): int
    {
        // Estimate based on Google Cloud NLP typical response times
        $baseTimePerText = 0.1; // 100ms per text
        $overhead = 5; // 5 seconds overhead
        
        return (int) ceil(($textCount * $baseTimePerText) + $overhead);
    }

    private function mergeBatchResults(array $batchResults): array
    {
        $merged = [
            'processed_count' => 0,
            'failed_count' => 0,
            'total_cost' => 0.0,
            'processing_time' => 0.0,
            'results' => [],
            'errors' => [],
        ];

        foreach ($batchResults as $result) {
            $merged['processed_count'] += $result['processed_count'] ?? 0;
            $merged['failed_count'] += $result['failed_count'] ?? 0;
            $merged['total_cost'] += $result['total_cost'] ?? 0.0;
            $merged['processing_time'] += $result['processing_time'] ?? 0.0;
            $merged['results'] = array_merge($merged['results'], $result['results'] ?? []);
            $merged['errors'] = array_merge($merged['errors'], $result['errors'] ?? []);
        }

        return $merged;
    }

    private function createEmptyResult(string $message): array
    {
        return [
            'status' => 'empty',
            'message' => $message,
            'processed_count' => 0,
            'failed_count' => 0,
            'total_cost' => 0.0,
            'processing_time' => 0.0,
        ];
    }

    private function createErrorResult(string $error): array
    {
        return [
            'status' => 'error',
            'error' => $error,
            'processed_count' => 0,
            'failed_count' => 0,
            'total_cost' => 0.0,
            'processing_time' => 0.0,
        ];
    }

    private function checkGoogleNLPStatus(): array
    {
        try {
            // Test Google Cloud NLP connectivity
            $testResult = $this->batchProcessor->healthCheck();
            
            return [
                'status' => 'healthy',
                'response_time' => $testResult['response_time'] ?? 0,
                'api_quota_remaining' => $testResult['quota_remaining'] ?? 'unknown',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkQueueStatus(): array
    {
        try {
            $queueConnection = Queue::connection();
            $sentimentJobsCount = $queueConnection->size('sentiment');
            
            return [
                'status' => 'healthy',
                'sentiment_queue_size' => $sentimentJobsCount,
                'failed_jobs' => $this->getFailedJobsCount(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkDatabaseStatus(): array
    {
        try {
            $recentAggregates = DailySentimentAggregate::where('processed_at', '>=', now()->subDay())
                ->count();
            
            return [
                'status' => 'healthy',
                'recent_aggregates_count' => $recentAggregates,
                'last_aggregate_date' => DailySentimentAggregate::latest('date')->value('date'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function getRecentActivity(): array
    {
        // Get activity from cache/logs for last 24 hours
        $cacheKey = 'sentiment_pipeline_activity_24h';
        
        return Cache::get($cacheKey, [
            'total_texts_processed' => 0,
            'successful_aggregations' => 0,
            'failed_operations' => 0,
            'average_processing_time' => 0,
        ]);
    }

    private function getPerformanceSummary(): array
    {
        return [
            'today' => $this->getPerformanceMetrics(Carbon::today()),
            'yesterday' => $this->getPerformanceMetrics(Carbon::yesterday()),
            'weekly_trend' => $this->calculateWeeklyTrend(),
        ];
    }

    private function calculatePerformanceMetrics(Carbon $date): array
    {
        $cacheKey = "performance_metrics:" . $date->format('Y-m-d');
        
        return Cache::remember($cacheKey, 300, function () use ($date) {
            $aggregates = DailySentimentAggregate::forDate($date)->get();
            
            return [
                'total_aggregates' => $aggregates->count(),
                'total_posts_analyzed' => $aggregates->sum('analyzed_posts'),
                'average_sentiment' => round($aggregates->avg('avg_sentiment_score') ?? 0, 3),
                'platforms_covered' => $aggregates->pluck('platform')->unique()->count(),
                'processing_rate' => $this->calculateProcessingRate($aggregates),
            ];
        });
    }

    private function calculateProcessingRate(Collection $aggregates): float
    {
        $totalPosts = $aggregates->sum('total_posts');
        $analyzedPosts = $aggregates->sum('analyzed_posts');
        
        return $totalPosts > 0 ? round(($analyzedPosts / $totalPosts) * 100, 2) : 0.0;
    }

    private function calculateWeeklyTrend(): array
    {
        $weekStart = Carbon::now()->startOfWeek();
        $dailyMetrics = [];
        
        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->copy()->addDays($i);
            $dailyMetrics[] = $this->getPerformanceMetrics($date);
        }
        
        return [
            'daily_metrics' => $dailyMetrics,
            'trend_direction' => $this->analyzeTrend($dailyMetrics),
        ];
    }

    private function analyzeTrend(array $dailyMetrics): string
    {
        if (count($dailyMetrics) < 2) {
            return 'insufficient_data';
        }
        
        $recent = end($dailyMetrics)['total_posts_analyzed'] ?? 0;
        $previous = $dailyMetrics[count($dailyMetrics) - 2]['total_posts_analyzed'] ?? 0;
        
        if ($recent > $previous * 1.1) {
            return 'increasing';
        } elseif ($recent < $previous * 0.9) {
            return 'decreasing';
        } else {
            return 'stable';
        }
    }

    private function getFailedJobsCount(): int
    {
        try {
            return DB::table('failed_jobs')
                ->where('payload', 'LIKE', '%GoogleCloudNLPBatchJob%')
                ->where('failed_at', '>=', now()->subDay())
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }
}
