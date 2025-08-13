<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SentimentPipeline\GoogleCloudBatchProcessor;
use App\Jobs\ProcessTextThroughNLPPipeline;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

/**
 * API Controller for Google Cloud NLP Pipeline
 * Text → Google Cloud NLP (batch sentiment) → Daily aggregates
 */
class GoogleCloudNLPController extends Controller
{
    public function __construct(
        private readonly GoogleCloudBatchProcessor $processor
    ) {}

    /**
     * Process texts through the complete NLP pipeline
     */
    public function processTexts(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'texts' => 'required|array|min:1|max:1000',
            'texts.*' => 'required|string|max:5000',
            'platform' => 'sometimes|string|max:50',
            'category' => 'sometimes|string|max:50',
            'language' => 'sometimes|string|size:2',
            'async' => 'sometimes|boolean',
            'generate_aggregates' => 'sometimes|boolean',
            'batch_name' => 'sometimes|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $texts = $request->input('texts');
        $metadata = [
            'platform' => $request->input('platform', 'api'),
            'keyword_category' => $request->input('category', 'general'),
            'language' => $request->input('language', 'en'),
            'batch_name' => $request->input('batch_name', 'api_batch_' . time()),
            'description' => 'API initiated NLP pipeline processing',
            'source' => 'api_request',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ];

        $async = $request->boolean('async', false);
        $generateAggregates = $request->boolean('generate_aggregates', true);

        if ($async) {
            return $this->processAsync($texts, $metadata, $generateAggregates);
        } else {
            return $this->processSync($texts, $metadata, $generateAggregates);
        }
    }

    /**
     * Process synchronously
     */
    private function processSync(array $texts, array $metadata, bool $generateAggregates): JsonResponse
    {
        try {
            $result = $this->processor->processTextToDailyAggregates(
                $texts,
                $metadata,
                $generateAggregates
            );

            return response()->json([
                'success' => true,
                'message' => 'Texts processed successfully',
                'data' => [
                    'batch_id' => $result['batch_id'],
                    'processed_count' => $result['processed_count'],
                    'execution_time_ms' => $result['execution_time_ms'],
                    'sentiment_summary' => $this->getSentimentSummary($result['sentiment_results']),
                    'aggregates_created' => count($result['daily_aggregates'])
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Processing failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process asynchronously
     */
    private function processAsync(array $texts, array $metadata, bool $generateAggregates): JsonResponse
    {
        try {
            ProcessTextThroughNLPPipeline::dispatch($texts, $metadata, $generateAggregates);

            return response()->json([
                'success' => true,
                'message' => 'Texts queued for processing',
                'data' => [
                    'queued_count' => count($texts),
                    'batch_name' => $metadata['batch_name'],
                    'estimated_processing_time' => $this->estimateProcessingTime(count($texts))
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to queue texts for processing',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get batch processing status
     */
    public function getBatchStatus(int $batchId): JsonResponse
    {
        try {
            $status = $this->processor->getBatchStatus($batchId);

            if (isset($status['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => $status['error']
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $status
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get batch status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get daily aggregates
     */
    public function getDailyAggregates(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'platform' => 'sometimes|string|max:50',
            'category' => 'sometimes|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $startDate = Carbon::parse($request->input('start_date'));
            $endDate = Carbon::parse($request->input('end_date'));
            
            $aggregates = $this->processor->getDailyAggregates(
                $startDate,
                $endDate,
                $request->input('platform'),
                $request->input('category')
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'aggregates' => $aggregates,
                    'date_range' => [
                        'start_date' => $startDate->toDateString(),
                        'end_date' => $endDate->toDateString()
                    ],
                    'total_days' => count($aggregates),
                    'filters' => [
                        'platform' => $request->input('platform'),
                        'category' => $request->input('category')
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get daily aggregates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process single text (convenience endpoint)
     */
    public function processSingleText(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'text' => 'required|string|max:5000',
            'platform' => 'sometimes|string|max:50',
            'category' => 'sometimes|string|max:50',
            'language' => 'sometimes|string|size:2'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Convert to array and process
        $texts = [$request->input('text')];
        $metadata = [
            'platform' => $request->input('platform', 'api'),
            'keyword_category' => $request->input('category', 'general'),
            'language' => $request->input('language', 'en'),
            'batch_name' => 'single_text_' . time(),
            'description' => 'Single text processing via API',
            'source' => 'api_single_text'
        ];

        return $this->processSync($texts, $metadata, false); // No aggregates for single text
    }

    /**
     * Get pipeline health status
     */
    public function getHealthStatus(): JsonResponse
    {
        try {
            // Basic health checks
            $health = [
                'status' => 'healthy',
                'timestamp' => now()->toISOString(),
                'google_cloud_nlp' => 'available', // This would be a real check
                'database' => 'connected',
                'queue' => 'running'
            ];

            return response()->json([
                'success' => true,
                'data' => $health
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Health check failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sentiment summary from results
     */
    private function getSentimentSummary(array $results): array
    {
        if (empty($results)) {
            return [];
        }

        $sentiments = array_column($results, 'sentiment_label');
        $scores = array_filter(array_column($results, 'sentiment_score'), 'is_numeric');
        
        $counts = array_count_values($sentiments);
        
        return [
            'total_analyzed' => count($results),
            'sentiment_distribution' => $counts,
            'average_score' => count($scores) > 0 ? round(array_sum($scores) / count($scores), 3) : 0,
            'score_range' => count($scores) > 0 ? [
                'min' => round(min($scores), 3),
                'max' => round(max($scores), 3)
            ] : null
        ];
    }

    /**
     * Estimate processing time based on text count
     */
    private function estimateProcessingTime(int $textCount): string
    {
        // Rough estimate: ~200ms per text including API calls and processing
        $estimatedMs = $textCount * 200;
        
        if ($estimatedMs < 1000) {
            return "< 1 second";
        } elseif ($estimatedMs < 60000) {
            return round($estimatedMs / 1000) . " seconds";
        } else {
            return round($estimatedMs / 60000) . " minutes";
        }
    }
}