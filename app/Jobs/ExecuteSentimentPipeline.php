<?php

namespace App\Jobs;

use App\Services\SentimentPipeline\SentimentPipelineService;
use App\Services\GoogleSentimentService;
use App\Services\CrawlerSentimentIntegration;
use App\Services\CrawlerMicroService\CrawlerOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class ExecuteSentimentPipeline implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 7200; // 2 hours timeout for complete pipeline
    public int $tries = 2;
    public int $backoff = 600; // 10 minutes between retries

    private array $pipelineConfig;
    private string $pipelineType;

    /**
     * Create a new job instance.
     */
    public function __construct(array $pipelineConfig = [], string $pipelineType = 'full')
    {
        $this->pipelineConfig = $pipelineConfig;
        $this->pipelineType = $pipelineType;
        
        // Use sentiment processing queue
        $this->onQueue(config('sentiment_pipeline.queue.default_queue', 'sentiment-processing'));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = microtime(true);
        
        try {
            Log::info('Starting sentiment pipeline execution', [
                'job_id' => $this->job->getJobId(),
                'pipeline_type' => $this->pipelineType,
                'config' => $this->pipelineConfig
            ]);

            $results = match($this->pipelineType) {
                'full' => $this->executeFullPipeline(),
                'crawler_only' => $this->executeCrawlerPipeline(),
                'sentiment_only' => $this->executeSentimentAnalysis(),
                'text_processing' => $this->executeTextProcessing(),
                default => throw new Exception("Unknown pipeline type: {$this->pipelineType}")
            };

            $executionTime = microtime(true) - $startTime;
            
            Log::info('Sentiment pipeline execution completed successfully', [
                'job_id' => $this->job->getJobId(),
                'pipeline_type' => $this->pipelineType,
                'execution_time_seconds' => round($executionTime, 2),
                'results_summary' => $this->summarizeResults($results)
            ]);

        } catch (Exception $e) {
            $executionTime = microtime(true) - $startTime;
            
            Log::error('Sentiment pipeline execution failed', [
                'job_id' => $this->job->getJobId(),
                'pipeline_type' => $this->pipelineType,
                'error' => $e->getMessage(),
                'execution_time_seconds' => round($executionTime, 2),
                'attempt' => $this->attempts()
            ]);

            throw $e;
        }
    }

    /**
     * Execute complete pipeline: Crawl → NLP → Aggregate
     */
    private function executeFullPipeline(): array
    {
        $crawlerIntegration = app(CrawlerSentimentIntegration::class);
        
        $pipelineConfig = array_merge([
            'platforms' => ['twitter', 'reddit', 'telegram'],
            'keyword_rules' => $this->getDefaultKeywordRules(),
            'max_posts' => 500,
            'priority' => 'high',
            'trigger_aggregation' => true
        ], $this->pipelineConfig);

        return $crawlerIntegration->executeCrawlAndSentimentPipeline($pipelineConfig);
    }

    /**
     * Execute crawler pipeline only
     */
    private function executeCrawlerPipeline(): array
    {
        $crawler = app(CrawlerOrchestrator::class);
        
        $crawlConfig = array_merge([
            'job_id' => 'pipeline_crawl_' . uniqid(),
            'platforms' => ['twitter', 'reddit'],
            'keyword_rules' => $this->getDefaultKeywordRules(),
            'max_posts' => 200,
            'priority' => 'normal'
        ], $this->pipelineConfig);

        return $crawler->executeCrawlJob($crawlConfig);
    }

    /**
     * Execute sentiment analysis on existing data
     */
    private function executeSentimentAnalysis(): array
    {
        $sentimentService = app(GoogleSentimentService::class);
        $crawlerIntegration = app(CrawlerSentimentIntegration::class);
        $pipelineService = new SentimentPipelineService($sentimentService, $crawlerIntegration);

        // Get recent posts without sentiment analysis
        $posts = $this->getUnanalyzedPosts();
        
        if (empty($posts)) {
            return [
                'status' => 'completed',
                'message' => 'No posts found for sentiment analysis',
                'processed_count' => 0
            ];
        }

        $textData = array_map(function($post) {
            return [
                'text' => $post['content'] ?? $post['text'] ?? '',
                'id' => $post['id'] ?? null,
                'platform' => $post['platform'] ?? 'unknown',
                'metadata' => $post
            ];
        }, $posts);

        $pipelineOptions = array_merge([
            'source_type' => 'existing_posts',
            'batch_name' => 'Sentiment Analysis Batch - ' . date('Y-m-d H:i:s'),
            'trigger_aggregation' => true
        ], $this->pipelineConfig);

        return $pipelineService->processTextPipeline($textData, $pipelineOptions);
    }

    /**
     * Execute text processing pipeline with custom text data
     */
    private function executeTextProcessing(): array
    {
        $sentimentService = app(GoogleSentimentService::class);
        $crawlerIntegration = app(CrawlerSentimentIntegration::class);
        $pipelineService = new SentimentPipelineService($sentimentService, $crawlerIntegration);

        $textData = $this->pipelineConfig['text_data'] ?? [];
        
        if (empty($textData)) {
            throw new Exception('Text data is required for text processing pipeline');
        }

        $pipelineOptions = array_merge([
            'source_type' => 'custom_text',
            'batch_name' => 'Custom Text Processing - ' . date('Y-m-d H:i:s'),
            'trigger_aggregation' => false
        ], $this->pipelineConfig);

        return $pipelineService->processTextPipeline($textData, $pipelineOptions);
    }

    /**
     * Get unanalyzed posts from database
     */
    private function getUnanalyzedPosts(): array
    {
        // This would typically query the database for posts without sentiment scores
        // For now, return an empty array as a placeholder
        
        $limit = $this->pipelineConfig['limit'] ?? 100;
        $date_from = $this->pipelineConfig['date_from'] ?? Carbon::yesterday()->toDateString();
        
        // Example query structure (you would implement the actual database query)
        /*
        return SocialMediaPost::whereNull('sentiment_score')
            ->where('created_at', '>=', $date_from)
            ->limit($limit)
            ->get()
            ->toArray();
        */
        
        return [];
    }

    /**
     * Get default keyword rules for crawling
     */
    private function getDefaultKeywordRules(): array
    {
        return [
            'blockchain security',
            'smart contract vulnerability', 
            'crypto hack',
            'defi exploit',
            'ethereum security',
            'bitcoin security',
            'web3 security'
        ];
    }

    /**
     * Summarize pipeline results for logging
     */
    private function summarizeResults(array $results): array
    {
        $summary = [
            'status' => $results['status'] ?? 'unknown',
            'total_items_processed' => 0,
            'processing_time' => $results['pipeline_execution_time'] ?? $results['processing_time'] ?? 0
        ];

        // Extract different metrics based on pipeline type
        switch ($this->pipelineType) {
            case 'full':
                $summary['total_items_processed'] = $results['total_posts_processed'] ?? 0;
                $summary['aggregation_triggered'] = $results['aggregation_triggered'] ?? false;
                $summary['platforms_processed'] = count($results['crawl_results']['platforms_crawled'] ?? []);
                break;
                
            case 'crawler_only':
                $summary['total_items_processed'] = $results['total_posts_collected'] ?? 0;
                $summary['platforms_crawled'] = count($results['platform_results'] ?? []);
                break;
                
            case 'sentiment_only':
            case 'text_processing':
                $summary['total_items_processed'] = $results['processed_count'] ?? 0;
                $summary['failed_count'] = $results['failed_count'] ?? 0;
                $summary['batch_id'] = $results['batch_id'] ?? null;
                break;
        }

        return $summary;
    }

    /**
     * Handle job failure
     */
    public function failed(Exception $exception): void
    {
        Log::error('Sentiment pipeline job failed permanently', [
            'job_id' => $this->job?->getJobId(),
            'pipeline_type' => $this->pipelineType,
            'error' => $exception->getMessage(),
            'attempts_made' => $this->attempts(),
            'config' => $this->pipelineConfig
        ]);
    }
}