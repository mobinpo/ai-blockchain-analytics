<?php

declare(strict_types=1);

namespace App\Services\SentimentPipeline;

use App\Models\SentimentBatch;
use App\Models\SentimentBatchDocument;
use App\Services\GoogleSentimentService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

final class SentimentBatchProcessor
{
    protected GoogleSentimentService $sentimentService;
    protected array $config;

    public function __construct(GoogleSentimentService $sentimentService)
    {
        $this->sentimentService = $sentimentService;
        $this->config = config('sentiment_pipeline.batch_processing', []);
    }

    public function processBatch(SentimentBatch $batch): array
    {
        $startTime = microtime(true);
        
        Log::info('Starting batch processing', [
            'batch_id' => $batch->batch_id,
            'total_documents' => $batch->total_documents,
        ]);

        // Mark batch as processing
        $batch->markAsProcessing();

        try {
            $results = $this->processDocuments($batch);
            
            // Update batch with final results
            $this->updateBatchWithResults($batch, $results);
            
            // Mark as completed
            $batch->markAsCompleted($results);
            
            $totalTime = microtime(true) - $startTime;
            
            Log::info('Batch processing completed', [
                'batch_id' => $batch->batch_id,
                'processed' => $results['processed'],
                'failed' => $results['failed'],
                'total_time' => round($totalTime, 2),
                'success_rate' => $results['success_rate'],
            ]);

            return $results;

        } catch (\Exception $e) {
            Log::error('Batch processing failed', [
                'batch_id' => $batch->batch_id,
                'error' => $e->getMessage(),
            ]);

            $batch->markAsFailed([
                'error' => $e->getMessage(),
                'failed_at' => now()->toISOString(),
            ]);

            throw $e;
        }
    }

    protected function processDocuments(SentimentBatch $batch): array
    {
        $chunkSize = $this->config['processing_chunk_size'] ?? 10;
        $maxRetries = $this->config['max_retries'] ?? 3;
        
        $results = [
            'processed' => 0,
            'failed' => 0,
            'total_cost' => 0.0,
            'processing_time' => 0.0,
            'chunks_processed' => 0,
        ];

        $query = $batch->documents()->where('processing_status', 'pending');
        
        $query->chunk($chunkSize, function ($documents) use (&$results, $batch, $maxRetries) {
            $chunkResults = $this->processDocumentChunk($documents->all(), $maxRetries);
            
            // Aggregate results
            $results['processed'] += $chunkResults['processed'];
            $results['failed'] += $chunkResults['failed'];
            $results['total_cost'] += $chunkResults['total_cost'];
            $results['processing_time'] += $chunkResults['processing_time'];
            $results['chunks_processed']++;

            // Update batch counters in real-time
            $this->updateBatchCounters($batch);

            Log::debug('Processed document chunk', [
                'batch_id' => $batch->batch_id,
                'chunk' => $results['chunks_processed'],
                'chunk_processed' => $chunkResults['processed'],
                'chunk_failed' => $chunkResults['failed'],
            ]);
        });

        // Calculate final metrics
        $results['success_rate'] = $this->calculateSuccessRate($results);
        $results['cost_per_document'] = $this->calculateCostPerDocument($results);
        
        return $results;
    }

    protected function processDocumentChunk(array $documents, int $maxRetries): array
    {
        $results = [
            'processed' => 0,
            'failed' => 0,
            'total_cost' => 0.0,
            'processing_time' => 0.0,
        ];

        foreach ($documents as $document) {
            $retryCount = 0;
            $processed = false;

            while ($retryCount <= $maxRetries && !$processed) {
                try {
                    $documentStartTime = microtime(true);
                    
                    $success = $this->sentimentService->processBatchDocument($document);
                    
                    $documentTime = microtime(true) - $documentStartTime;
                    $results['processing_time'] += $documentTime;
                    
                    if ($success) {
                        $results['processed']++;
                        $results['total_cost'] += $this->estimateDocumentCost($document);
                        $processed = true;
                    } else {
                        $retryCount++;
                        if ($retryCount > $maxRetries) {
                            $results['failed']++;
                        }
                    }

                } catch (\Exception $e) {
                    $retryCount++;
                    
                    Log::warning('Document processing attempt failed', [
                        'document_id' => $document->id,
                        'retry_count' => $retryCount,
                        'error' => $e->getMessage(),
                    ]);

                    if ($retryCount > $maxRetries) {
                        $results['failed']++;
                        
                        // Mark document as failed if max retries exceeded
                        $document->markAsFailed([
                            'error' => $e->getMessage(),
                            'max_retries_exceeded' => true,
                            'retry_count' => $retryCount - 1,
                        ]);
                    } else {
                        // Add delay before retry
                        sleep($this->config['retry_delay_seconds'] ?? 2);
                    }
                }
            }
        }

        return $results;
    }

    protected function updateBatchCounters(SentimentBatch $batch): void
    {
        // Get fresh counts from database
        $processedCount = $batch->documents()->where('processing_status', 'completed')->count();
        $failedCount = $batch->documents()->where('processing_status', 'failed')->count();

        $batch->update([
            'processed_documents' => $processedCount,
            'failed_documents' => $failedCount,
        ]);
    }

    protected function updateBatchWithResults(SentimentBatch $batch, array $results): void
    {
        $batch->update([
            'processing_cost' => $results['total_cost'],
            'processing_stats' => [
                'total_processing_time' => $results['processing_time'],
                'success_rate' => $results['success_rate'],
                'cost_per_document' => $results['cost_per_document'],
                'chunks_processed' => $results['chunks_processed'],
                'completed_at' => now()->toISOString(),
            ],
        ]);
    }

    protected function calculateSuccessRate(array $results): float
    {
        $total = $results['processed'] + $results['failed'];
        if ($total === 0) {
            return 0.0;
        }

        return round(($results['processed'] / $total) * 100, 2);
    }

    protected function calculateCostPerDocument(array $results): float
    {
        $total = $results['processed'] + $results['failed'];
        if ($total === 0) {
            return 0.0;
        }

        return round($results['total_cost'] / $total, 4);
    }

    protected function estimateDocumentCost(SentimentBatchDocument $document): float
    {
        // Google Cloud NLP pricing estimates
        $textLength = strlen($document->processed_text);
        
        // Base sentiment analysis cost
        $baseCost = 0.001;
        
        // Additional costs based on text length and features
        if ($textLength > 1000) {
            $baseCost *= 1.5; // Longer texts cost more
        }
        
        return $baseCost;
    }

    public function retryFailedDocuments(SentimentBatch $batch): array
    {
        $failedDocuments = $batch->documents()->failed()->get();
        
        if ($failedDocuments->isEmpty()) {
            return [
                'message' => 'No failed documents to retry',
                'retried_count' => 0,
            ];
        }

        Log::info('Retrying failed documents', [
            'batch_id' => $batch->batch_id,
            'failed_count' => $failedDocuments->count(),
        ]);

        // Reset failed documents to pending
        foreach ($failedDocuments as $document) {
            $document->update([
                'processing_status' => 'pending',
                'error_details' => null,
            ]);
        }

        // Reset batch status to pending for reprocessing
        $batch->update([
            'status' => 'pending',
            'failed_documents' => 0,
        ]);

        // Process the batch again
        return $this->processBatch($batch);
    }

    public function getBatchProcessingStats(): array
    {
        return [
            'total_batches' => SentimentBatch::count(),
            'pending_batches' => SentimentBatch::pending()->count(),
            'processing_batches' => SentimentBatch::where('status', 'processing')->count(),
            'completed_batches' => SentimentBatch::completed()->count(),
            'failed_batches' => SentimentBatch::failed()->count(),
            'total_documents' => SentimentBatchDocument::count(),
            'pending_documents' => SentimentBatchDocument::where('processing_status', 'pending')->count(),
            'processed_documents' => SentimentBatchDocument::where('processing_status', 'completed')->count(),
            'failed_documents' => SentimentBatchDocument::where('processing_status', 'failed')->count(),
            'total_estimated_cost' => SentimentBatch::sum('processing_cost'),
            'average_success_rate' => $this->getAverageSuccessRate(),
        ];
    }

    protected function getAverageSuccessRate(): float
    {
        $completedBatches = SentimentBatch::completed()
            ->whereNotNull('processing_stats')
            ->get();

        if ($completedBatches->isEmpty()) {
            return 0.0;
        }

        $totalSuccessRate = $completedBatches->sum(function ($batch) {
            return $batch->processing_stats['success_rate'] ?? 0;
        });

        return round($totalSuccessRate / $completedBatches->count(), 2);
    }

    public function cleanupCompletedBatches(int $daysOld = 30): int
    {
        $cutoffDate = now()->subDays($daysOld);
        
        $deletedCount = SentimentBatch::completed()
            ->where('completed_at', '<', $cutoffDate)
            ->delete();

        if ($deletedCount > 0) {
            Log::info('Cleaned up old completed batches', [
                'deleted_count' => $deletedCount,
                'cutoff_date' => $cutoffDate->toDateString(),
            ]);
        }

        return $deletedCount;
    }
}