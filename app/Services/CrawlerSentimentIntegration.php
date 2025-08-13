<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\CrawlerMicroService\CrawlerOrchestrator;
use App\Services\SentimentPipeline\TextAggregator;
use App\Services\SentimentPipeline\SentimentBatchProcessor;
use App\Services\SentimentPipeline\DailySentimentAggregateService;
use App\Services\GoogleSentimentService;
use App\Models\SocialMediaPost;
use App\Models\SentimentBatch;
use App\Models\CrawlerKeywordRule;
use App\Jobs\SentimentPipelineJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Carbon\Carbon;

final class CrawlerSentimentIntegration
{
    public function __construct(
        private readonly CrawlerOrchestrator $crawler,
        private readonly TextAggregator $textAggregator,
        private readonly SentimentBatchProcessor $batchProcessor,
        private readonly DailySentimentAggregateService $aggregateService,
        private readonly GoogleSentimentService $nlpService
    ) {}

    /**
     * Complete pipeline: Crawl → Process → Analyze → Aggregate
     */
    public function executePipeline(array $pipelineConfig): array
    {
        $startTime = microtime(true);
        $pipelineId = 'pipeline_' . uniqid();
        
        Log::info('Starting complete crawler-sentiment pipeline', [
            'pipeline_id' => $pipelineId,
            'config' => $pipelineConfig
        ]);

        $results = [
            'pipeline_id' => $pipelineId,
            'started_at' => now()->toISOString(),
            'phases' => [],
            'summary' => []
        ];

        try {
            // Phase 1: Crawl social media platforms
            $results['phases']['crawling'] = $this->executeCrawlingPhase($pipelineConfig);
            
            // Phase 2: Aggregate collected text into batches
            $results['phases']['text_aggregation'] = $this->executeTextAggregationPhase($pipelineConfig);
            
            // Phase 3: Process sentiment analysis with Google Cloud NLP
            $results['phases']['sentiment_analysis'] = $this->executeSentimentAnalysisPhase($pipelineConfig);
            
            // Phase 4: Generate daily aggregates
            $results['phases']['daily_aggregation'] = $this->executeDailyAggregationPhase($pipelineConfig);
            
            // Generate pipeline summary
            $results['summary'] = $this->generatePipelineSummary($results);
            $results['status'] = 'completed';
            
        } catch (\Exception $e) {
            Log::error('Pipeline execution failed', [
                'pipeline_id' => $pipelineId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $results['status'] = 'failed';
            $results['error'] = $e->getMessage();
        }
        
        $results['completed_at'] = now()->toISOString();
        $results['total_duration_ms'] = round((microtime(true) - $startTime) * 1000);
        
        return $results;
    }

    /**
     * Phase 1: Execute social media crawling
     */
    private function executeCrawlingPhase(array $config): array
    {
        Log::info('Starting crawling phase');
        $startTime = microtime(true);
        
        $crawlConfig = [
            'job_id' => 'crawl_' . uniqid(),
            'platforms' => $config['platforms'] ?? ['twitter', 'reddit'],
            'keyword_rules' => $config['keyword_rules'] ?? [],
            'keyword_rule_ids' => $config['keyword_rule_ids'] ?? [],
            'max_posts' => $config['max_posts'] ?? 500,
            'priority' => $config['priority'] ?? 'high'
        ];

        $crawlResults = $this->crawler->executeCrawlJob($crawlConfig);
        
        $duration = round((microtime(true) - $startTime) * 1000);
        
        return [
            'status' => $crawlResults['status'] ?? 'unknown',
            'job_id' => $crawlResults['job_id'],
            'posts_collected' => $crawlResults['total_posts'] ?? 0,
            'keyword_matches' => $crawlResults['total_matches'] ?? 0,
            'platforms_used' => array_keys($crawlResults['platforms'] ?? []),
            'duration_ms' => $duration,
            'errors' => $crawlResults['errors'] ?? []
        ];
    }

    /**
     * Phase 2: Aggregate text data into sentiment batches
     */
    private function executeTextAggregationPhase(array $config): array
    {
        Log::info('Starting text aggregation phase');
        $startTime = microtime(true);
        
        $date = isset($config['date']) ? Carbon::parse($config['date']) : Carbon::today();
        
        // Create daily batch
        $batch = $this->textAggregator->createDailyBatch($date);
        
        // Count documents in batch
        $documentsCount = $batch->documents()->count();
        
        $duration = round((microtime(true) - $startTime) * 1000);
        
        return [
            'status' => 'completed',
            'batch_id' => $batch->batch_id,
            'date' => $date->toDateString(),
            'documents_aggregated' => $documentsCount,
            'batch_status' => $batch->status,
            'duration_ms' => $duration
        ];
    }

    /**
     * Phase 3: Process sentiment analysis with Google Cloud NLP
     */
    private function executeSentimentAnalysisPhase(array $config): array
    {
        Log::info('Starting sentiment analysis phase');
        $startTime = microtime(true);
        
        $date = isset($config['date']) ? Carbon::parse($config['date']) : Carbon::today();
        $batchId = 'batch_' . $date->format('Y_m_d');
        
        // Get pending batches
        $pendingBatches = SentimentBatch::where('status', 'pending')
            ->orWhere('status', 'processing')
            ->get();
        
        $processedBatches = 0;
        $totalDocuments = 0;
        $errors = [];
        
        foreach ($pendingBatches as $batch) {
            try {
                // Process batch with Google Cloud NLP
                $batchResults = $this->batchProcessor->processBatch($batch);
                
                $processedBatches++;
                $totalDocuments += $batchResults['documents_processed'] ?? 0;
                
                Log::info('Batch processed successfully', [
                    'batch_id' => $batch->batch_id,
                    'documents' => $batchResults['documents_processed'] ?? 0
                ]);
                
            } catch (\Exception $e) {
                $errors[] = "Batch {$batch->batch_id}: " . $e->getMessage();
                Log::error('Batch processing failed', [
                    'batch_id' => $batch->batch_id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $duration = round((microtime(true) - $startTime) * 1000);
        
        return [
            'status' => empty($errors) ? 'completed' : 'completed_with_errors',
            'batches_processed' => $processedBatches,
            'total_documents_analyzed' => $totalDocuments,
            'nlp_provider' => 'Google Cloud Natural Language',
            'duration_ms' => $duration,
            'errors' => $errors
        ];
    }

    /**
     * Phase 4: Generate daily sentiment aggregates
     */
    private function executeDailyAggregationPhase(array $config): array
    {
        Log::info('Starting daily aggregation phase');
        $startTime = microtime(true);
        
        $date = isset($config['date']) ? Carbon::parse($config['date']) : Carbon::today();
        
        // Generate daily aggregates
        $aggregates = $this->aggregateService->generateDailyAggregates($date);
        
        $duration = round((microtime(true) - $startTime) * 1000);
        
        // Calculate aggregate statistics
        $platformBreakdown = [];
        $categoryBreakdown = [];
        $totalPosts = 0;
        
        foreach ($aggregates as $aggregate) {
            $platform = $aggregate->platform ?? 'unknown';
            $category = $aggregate->category ?? 'general';
            $posts = $aggregate->total_posts ?? 0;
            
            $platformBreakdown[$platform] = ($platformBreakdown[$platform] ?? 0) + $posts;
            $categoryBreakdown[$category] = ($categoryBreakdown[$category] ?? 0) + $posts;
            $totalPosts += $posts;
        }
        
        return [
            'status' => 'completed',
            'date' => $date->toDateString(),
            'aggregates_generated' => count($aggregates),
            'total_posts_aggregated' => $totalPosts,
            'platform_breakdown' => $platformBreakdown,
            'category_breakdown' => $categoryBreakdown,
            'duration_ms' => $duration
        ];
    }

    /**
     * Queue-based asynchronous pipeline execution
     */
    public function queuePipeline(array $pipelineConfig, string $queue = 'sentiment-pipeline'): string
    {
        $pipelineId = 'async_pipeline_' . uniqid();
        
        Log::info('Queueing complete sentiment pipeline', [
            'pipeline_id' => $pipelineId,
            'queue' => $queue
        ]);
        
        // Queue the pipeline job
        SentimentPipelineJob::dispatch($pipelineConfig)
            ->onQueue($queue)
            ->delay(now()->addSeconds(5)); // Small delay to ensure data consistency
        
        return $pipelineId;
    }

    /**
     * Scheduled pipeline execution for automated daily processing
     */
    public function executeScheduledPipeline(?Carbon $date = null): array
    {
        $date = $date ?? Carbon::yesterday(); // Process previous day by default
        
        Log::info('Executing scheduled sentiment pipeline', [
            'date' => $date->toDateString()
        ]);
        
        // Get active keyword rules for scheduled execution
        $activeRules = CrawlerKeywordRule::active()
            ->where(function($query) use ($date) {
                $query->whereNull('schedule')
                      ->orWhereRaw('JSON_EXTRACT(schedule, "$.interval_minutes") IS NOT NULL');
            })
            ->get();
        
        $pipelineConfig = [
            'date' => $date->toDateString(),
            'platforms' => ['twitter', 'reddit', 'telegram'],
            'keyword_rule_ids' => $activeRules->pluck('id')->toArray(),
            'max_posts' => 1000,
            'priority' => 'normal',
            'scheduled' => true
        ];
        
        return $this->executePipeline($pipelineConfig);
    }

    /**
     * Process existing social media posts through sentiment pipeline
     */
    public function processExistingPosts(array $filters = []): array
    {
        Log::info('Processing existing posts through sentiment pipeline', $filters);
        
        $query = SocialMediaPost::query();
        
        // Apply filters
        if (isset($filters['platform'])) {
            $query->where('platform', $filters['platform']);
        }
        
        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        
        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
        
        // Only process posts without sentiment analysis
        $query->whereNull('sentiment_score');
        
        $posts = $query->limit($filters['limit'] ?? 500)->get();
        
        if ($posts->isEmpty()) {
            return [
                'status' => 'completed',
                'posts_processed' => 0,
                'message' => 'No posts found matching criteria'
            ];
        }
        
        // Create temporary batch for existing posts
        $batch = SentimentBatch::create([
            'batch_id' => 'existing_posts_' . uniqid(),
            'processing_date' => Carbon::today(),
            'status' => 'pending',
            'total_documents' => $posts->count(),
            'metadata' => json_encode(['type' => 'existing_posts', 'filters' => $filters])
        ]);
        
        // Add posts to batch
        foreach ($posts as $post) {
            $batch->documents()->create([
                'source_type' => 'social_media_post',
                'source_id' => $post->id,
                'original_text' => $post->content,
                'processing_status' => 'pending'
            ]);
        }
        
        // Process the batch
        $results = $this->batchProcessor->processBatch($batch);
        
        return [
            'status' => 'completed',
            'batch_id' => $batch->batch_id,
            'posts_processed' => $posts->count(),
            'sentiment_analysis_results' => $results
        ];
    }

    /**
     * Generate comprehensive pipeline summary
     */
    private function generatePipelineSummary(array $results): array
    {
        $crawling = $results['phases']['crawling'] ?? [];
        $aggregation = $results['phases']['text_aggregation'] ?? [];
        $sentiment = $results['phases']['sentiment_analysis'] ?? [];
        $daily = $results['phases']['daily_aggregation'] ?? [];
        
        return [
            'total_posts_collected' => $crawling['posts_collected'] ?? 0,
            'keyword_matches' => $crawling['keyword_matches'] ?? 0,
            'documents_analyzed' => $sentiment['total_documents_analyzed'] ?? 0,
            'daily_aggregates_created' => $daily['aggregates_generated'] ?? 0,
            'platforms_processed' => $crawling['platforms_used'] ?? [],
            'processing_chain' => [
                'crawling' => $crawling['duration_ms'] ?? 0,
                'text_aggregation' => $aggregation['duration_ms'] ?? 0,
                'sentiment_analysis' => $sentiment['duration_ms'] ?? 0,
                'daily_aggregation' => $daily['duration_ms'] ?? 0
            ],
            'success_rate' => $this->calculateSuccessRate($results),
            'data_quality_score' => $this->calculateDataQualityScore($results)
        ];
    }

    /**
     * Calculate pipeline success rate
     */
    private function calculateSuccessRate(array $results): float
    {
        $phases = ['crawling', 'text_aggregation', 'sentiment_analysis', 'daily_aggregation'];
        $successfulPhases = 0;
        
        foreach ($phases as $phase) {
            $phaseStatus = $results['phases'][$phase]['status'] ?? 'failed';
            if (in_array($phaseStatus, ['completed', 'completed_with_errors'])) {
                $successfulPhases++;
            }
        }
        
        return round(($successfulPhases / count($phases)) * 100, 2);
    }

    /**
     * Calculate data quality score based on match rates and processing success
     */
    private function calculateDataQualityScore(array $results): float
    {
        $crawling = $results['phases']['crawling'] ?? [];
        $sentiment = $results['phases']['sentiment_analysis'] ?? [];
        
        $postsCollected = $crawling['posts_collected'] ?? 0;
        $keywordMatches = $crawling['keyword_matches'] ?? 0;
        $documentsAnalyzed = $sentiment['total_documents_analyzed'] ?? 0;
        
        if ($postsCollected === 0) {
            return 0.0;
        }
        
        // Factor in keyword match rate and sentiment analysis coverage
        $matchRate = $keywordMatches / $postsCollected;
        $analysisRate = $postsCollected > 0 ? $documentsAnalyzed / $postsCollected : 0;
        
        $qualityScore = (($matchRate * 0.6) + ($analysisRate * 0.4)) * 100;
        
        return round(min($qualityScore, 100), 2);
    }

    /**
     * Get pipeline status and metrics
     */
    public function getPipelineStatus(string $pipelineId): ?array
    {
        // This would typically query a pipeline tracking table
        // For now, return current system status
        
        return [
            'pipeline_id' => $pipelineId,
            'current_status' => 'running',
            'phases_completed' => ['crawling', 'text_aggregation'],
            'current_phase' => 'sentiment_analysis',
            'estimated_completion' => now()->addMinutes(10)->toISOString(),
            'metrics' => [
                'posts_in_queue' => SocialMediaPost::whereNull('sentiment_score')->count(),
                'pending_batches' => SentimentBatch::where('status', 'pending')->count(),
                'processing_batches' => SentimentBatch::where('status', 'processing')->count()
            ]
        ];
    }
}