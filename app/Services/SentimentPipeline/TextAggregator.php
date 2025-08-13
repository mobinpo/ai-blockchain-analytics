<?php

namespace App\Services\SentimentPipeline;

use App\Models\SocialMediaPost;
use App\Models\SentimentBatch;
use App\Models\SentimentBatchDocument;
use App\Models\TextPreprocessingCache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class TextAggregator
{
    protected array $config;
    protected TextPreprocessor $preprocessor;

    public function __construct(TextPreprocessor $preprocessor)
    {
        $this->config = config('sentiment_pipeline');
        $this->preprocessor = $preprocessor;
    }

    public function createDailyBatch(Carbon $date): SentimentBatch
    {
        $batchId = $this->generateBatchId($date);
        
        Log::info('Creating sentiment batch for date', [
            'date' => $date->toDateString(),
            'batch_id' => $batchId,
        ]);

        // Check if batch already exists
        $existingBatch = SentimentBatch::where('batch_id', $batchId)->first();
        if ($existingBatch) {
            Log::info('Batch already exists', ['batch_id' => $batchId]);
            return $existingBatch;
        }

        // Create new batch
        $batch = SentimentBatch::create([
            'processing_date' => $date,
            'batch_id' => $batchId,
            'status' => 'pending',
            'total_documents' => 0,
        ]);

        // Aggregate text data for the date
        $this->aggregateTextData($batch, $date);

        return $batch;
    }

    public function aggregateTextData(SentimentBatch $batch, Carbon $date): int
    {
        $startTime = microtime(true);
        $totalDocuments = 0;

        // Process social media posts
        $totalDocuments += $this->aggregateSocialMediaPosts($batch, $date);

        // Update batch with total document count
        $batch->update(['total_documents' => $totalDocuments]);

        $processingTime = round(microtime(true) - $startTime, 2);
        
        Log::info('Text aggregation completed', [
            'batch_id' => $batch->batch_id,
            'total_documents' => $totalDocuments,
            'processing_time_seconds' => $processingTime,
        ]);

        return $totalDocuments;
    }

    protected function aggregateSocialMediaPosts(SentimentBatch $batch, Carbon $date): int
    {
        $query = SocialMediaPost::whereDate('published_at', $date)
            ->whereNull('sentiment_score') // Only process posts without sentiment
            ->orderBy('published_at');

        $chunkSize = $this->config['batch_processing']['chunk_size'] ?? 50;
        $totalProcessed = 0;

        $query->chunk($chunkSize, function ($posts) use ($batch, &$totalProcessed) {
            foreach ($posts as $post) {
                if ($this->shouldProcessPost($post)) {
                    $document = $this->createBatchDocument($batch, $post);
                    if ($document) {
                        $totalProcessed++;
                    }
                }
            }
        });

        return $totalProcessed;
    }

    protected function shouldProcessPost(SocialMediaPost $post): bool
    {
        $content = $post->content;
        $config = $this->config['aggregation'];

        // Check minimum length
        if (strlen($content) < $config['min_text_length']) {
            return false;
        }

        // Check maximum length
        if (strlen($content) > $config['max_text_length']) {
            return false;
        }

        // Check exclude patterns
        foreach ($config['exclude_patterns'] as $pattern) {
            if (preg_match($pattern, $content)) {
                return false;
            }
        }

        // Only process posts with keyword matches (if available)
        if (empty($post->matched_keywords)) {
            return false;
        }

        return true;
    }

    protected function createBatchDocument(SentimentBatch $batch, SocialMediaPost $post): ?SentimentBatchDocument
    {
        try {
            // Preprocess the text
            $processedText = $this->preprocessor->processText($post->content);
            
            if (empty(trim($processedText))) {
                Log::debug('Skipping post with empty processed text', ['post_id' => $post->id]);
                return null;
            }

            // Create batch document
            $document = SentimentBatchDocument::create([
                'sentiment_batch_id' => $batch->id,
                'source_type' => 'social_media_post',
                'source_id' => $post->id,
                'processed_text' => $processedText,
                'processing_status' => 'pending',
            ]);

            return $document;

        } catch (\Exception $e) {
            Log::error('Failed to create batch document', [
                'batch_id' => $batch->batch_id,
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function generateBatchId(Carbon $date): string
    {
        return 'sentiment_' . $date->format('Y_m_d') . '_' . substr(md5($date->timestamp), 0, 8);
    }

    public function getPendingBatches(): \Illuminate\Database\Eloquent\Collection
    {
        return SentimentBatch::pending()
            ->where('total_documents', '>', 0)
            ->orderBy('processing_date')
            ->get();
    }

    public function getBatchStats(SentimentBatch $batch): array
    {
        $documents = $batch->documents();
        
        return [
            'total_documents' => $batch->total_documents,
            'processed_documents' => $batch->processed_documents,
            'failed_documents' => $batch->failed_documents,
            'pending_documents' => $batch->total_documents - $batch->processed_documents,
            'success_rate' => $batch->success_rate,
            'progress_percentage' => $batch->progress_percentage,
            'processing_duration' => $batch->duration,
            'estimated_cost' => $this->estimateProcessingCost($batch),
            'language_distribution' => $this->getLanguageDistribution($batch),
            'source_distribution' => $this->getSourceDistribution($batch),
        ];
    }

    protected function estimateProcessingCost(SentimentBatch $batch): float
    {
        // Google Cloud NLP pricing: ~$1 per 1000 text records
        $baseRate = 0.001; // $0.001 per document
        return round($batch->total_documents * $baseRate, 4);
    }

    protected function getLanguageDistribution(SentimentBatch $batch): array
    {
        return $batch->documents()
            ->whereNotNull('detected_language')
            ->selectRaw('detected_language, count(*) as count')
            ->groupBy('detected_language')
            ->pluck('count', 'detected_language')
            ->toArray();
    }

    protected function getSourceDistribution(SentimentBatch $batch): array
    {
        return $batch->documents()
            ->selectRaw('source_type, count(*) as count')
            ->groupBy('source_type')
            ->pluck('count', 'source_type')
            ->toArray();
    }

    public function cleanupOldBatches(): int
    {
        $cleanupDays = $this->config['batch_processing']['cleanup_after_days'] ?? 7;
        $cutoffDate = now()->subDays($cleanupDays);

        $deletedCount = SentimentBatch::where('status', 'completed')
            ->where('completed_at', '<', $cutoffDate)
            ->delete();

        if ($deletedCount > 0) {
            Log::info('Cleaned up old sentiment batches', [
                'deleted_count' => $deletedCount,
                'cutoff_date' => $cutoffDate->toDateString(),
            ]);
        }

        return $deletedCount;
    }

    public function retryFailedDocuments(SentimentBatch $batch): int
    {
        $failedDocuments = $batch->documents()->failed()->get();
        $retriedCount = 0;

        foreach ($failedDocuments as $document) {
            $document->update([
                'processing_status' => 'pending',
                'error_details' => null,
            ]);
            $retriedCount++;
        }

        // Reset batch counters
        $batch->update([
            'failed_documents' => 0,
            'processed_documents' => $batch->processed_documents - $retriedCount,
            'status' => 'pending',
        ]);

        Log::info('Retried failed documents', [
            'batch_id' => $batch->batch_id,
            'retried_count' => $retriedCount,
        ]);

        return $retriedCount;
    }
}