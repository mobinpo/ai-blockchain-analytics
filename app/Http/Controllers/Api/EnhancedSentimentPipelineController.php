<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EnhancedSentimentPipelineService;
use App\Models\DailySentimentAggregate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Enhanced Sentiment Pipeline API Controller
 * 
 * Provides API endpoints for text → Google Cloud NLP → daily aggregates pipeline
 */
final class EnhancedSentimentPipelineController extends Controller
{
    public function __construct(
        private readonly EnhancedSentimentPipelineService $pipelineService
    ) {
    }

    /**
     * Process text data through sentiment pipeline
     * 
     * @route POST /api/sentiment/process
     */
    public function processText(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'texts' => 'required|array|min:1|max:1000',
            'texts.*' => 'required|string|min:10|max:5000',
            'platform' => 'nullable|string|max:50',
            'keyword' => 'nullable|string|max:100',
            'language' => 'nullable|string|size:2',
            'processing_mode' => 'nullable|in:immediate,batched,queued,auto',
            'enable_entities' => 'nullable|boolean',
            'enable_classification' => 'nullable|boolean',
            'auto_aggregate' => 'nullable|boolean',
            'priority' => 'nullable|in:low,normal,high,urgent',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $options = [
                'platform' => $request->input('platform', 'api'),
                'keyword' => $request->input('keyword'),
                'language' => $request->input('language', 'en'),
                'processing_mode' => $request->input('processing_mode', 'auto'),
                'enable_entities' => $request->boolean('enable_entities', true),
                'enable_classification' => $request->boolean('enable_classification', true),
                'auto_aggregate' => $request->boolean('auto_aggregate', true),
                'priority' => $request->input('priority', 'normal'),
            ];

            $result = $this->pipelineService->processTextPipeline(
                $request->input('texts'),
                $options
            );

            return response()->json([
                'status' => 'success',
                'data' => $result,
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Sentiment processing API error', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Processing failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Process text and generate daily aggregates for specific date
     * 
     * @route POST /api/sentiment/process-and-aggregate
     */
    public function processAndAggregate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'texts' => 'required|array|min:1|max:1000',
            'texts.*' => 'required|string|min:10|max:5000',
            'target_date' => 'nullable|date|before_or_equal:today',
            'platform' => 'nullable|string|max:50',
            'keyword' => 'nullable|string|max:100',
            'language' => 'nullable|string|size:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $targetDate = $request->input('target_date') 
                ? Carbon::parse($request->input('target_date'))
                : Carbon::today();

            $options = [
                'platform' => $request->input('platform', 'api'),
                'keyword' => $request->input('keyword'),
                'language' => $request->input('language', 'en'),
                'processing_mode' => 'immediate', // Force immediate for aggregate endpoint
            ];

            $result = $this->pipelineService->processAndAggregate(
                $request->input('texts'),
                $targetDate,
                $options
            );

            return response()->json([
                'status' => 'success',
                'data' => $result,
                'meta' => [
                    'target_date' => $targetDate->toDateString(),
                    'timestamp' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Sentiment process and aggregate API error', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Processing and aggregation failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Queue multiple batches for processing
     * 
     * @route POST /api/sentiment/queue-batches
     */
    public function queueBatches(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'batches' => 'required|array|min:1|max:10',
            'batches.*' => 'required|array|min:1|max:100',
            'batches.*.*' => 'required|string|min:10|max:5000',
            'platform' => 'nullable|string|max:50',
            'keyword' => 'nullable|string|max:100',
            'priority' => 'nullable|in:low,normal,high,urgent',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $globalOptions = [
                'platform' => $request->input('platform', 'api'),
                'keyword' => $request->input('keyword'),
                'priority' => $request->input('priority', 'normal'),
                'auto_aggregate' => true,
            ];

            $result = $this->pipelineService->queueMultipleBatches(
                $request->input('batches'),
                $globalOptions
            );

            return response()->json([
                'status' => 'success',
                'data' => $result,
                'meta' => [
                    'timestamp' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Queue batches API error', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Batch queuing failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get daily sentiment aggregates
     * 
     * @route GET /api/sentiment/aggregates/daily
     */
    public function getDailyAggregates(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date|before_or_equal:today',
            'end_date' => 'nullable|date|before_or_equal:today|after_or_equal:start_date',
            'platform' => 'nullable|string|max:50',
            'keyword' => 'nullable|string|max:100',
            'limit' => 'nullable|integer|min:1|max:365',
            'sort' => 'nullable|in:date,sentiment,posts,platform',
            'order' => 'nullable|in:asc,desc',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $query = DailySentimentAggregate::query();

            // Apply date filters
            if ($request->has('start_date') || $request->has('end_date')) {
                $startDate = $request->input('start_date') 
                    ? Carbon::parse($request->input('start_date'))
                    : Carbon::now()->subDays(30);
                
                $endDate = $request->input('end_date')
                    ? Carbon::parse($request->input('end_date'))
                    : Carbon::today();

                $query->dateRange($startDate, $endDate);
            } else {
                // Default to last 30 days
                $query->dateRange(Carbon::now()->subDays(30), Carbon::today());
            }

            // Apply platform filter
            if ($request->has('platform')) {
                $query->forPlatform($request->input('platform'));
            }

            // Apply keyword filter
            if ($request->has('keyword')) {
                $query->forKeyword($request->input('keyword'));
            }

            // Apply sorting
            $sortField = $request->input('sort', 'date');
            $sortOrder = $request->input('order', 'desc');
            
            $sortColumn = match ($sortField) {
                'sentiment' => 'avg_sentiment_score',
                'posts' => 'total_posts',
                'platform' => 'platform',
                default => 'date'
            };

            $query->orderBy($sortColumn, $sortOrder);

            // Apply limit
            $limit = $request->input('limit', 100);
            $aggregates = $query->limit($limit)->get();

            // Transform data for API response
            $transformedAggregates = $aggregates->map(function ($aggregate) {
                return [
                    'id' => $aggregate->id,
                    'date' => $aggregate->aggregate_date->toDateString(),
                    'platform' => $aggregate->platform,
                    'keyword' => $aggregate->keyword,
                    'metrics' => [
                        'total_posts' => $aggregate->total_posts,
                        'analyzed_posts' => $aggregate->analyzed_posts,
                        'processing_rate' => $aggregate->processing_rate,
                    ],
                    'sentiment' => [
                        'average_score' => (float) $aggregate->avg_sentiment_score,
                        'average_magnitude' => (float) $aggregate->avg_magnitude,
                        'label' => $aggregate->sentiment_label,
                        'distribution' => $aggregate->getSentimentDistribution(),
                        'percentages' => $aggregate->getSentimentPercentages(),
                    ],
                    'keywords' => $aggregate->getTopKeywords(10),
                    'processed_at' => $aggregate->processed_at?->toISOString(),
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'aggregates' => $transformedAggregates,
                    'count' => $aggregates->count(),
                    'filters_applied' => [
                        'platform' => $request->input('platform'),
                        'keyword' => $request->input('keyword'),
                        'date_range' => [
                            'start' => $request->input('start_date'),
                            'end' => $request->input('end_date'),
                        ],
                    ],
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Get daily aggregates API error', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve aggregates',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Generate daily aggregates for date range
     * 
     * @route POST /api/sentiment/aggregates/generate
     */
    public function generateAggregates(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date|before_or_equal:today',
            'end_date' => 'required|date|before_or_equal:today|after_or_equal:start_date',
            'platform' => 'nullable|string|max:50',
            'keyword' => 'nullable|string|max:100',
            'force_regenerate' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $startDate = Carbon::parse($request->input('start_date'));
            $endDate = Carbon::parse($request->input('end_date'));

            $options = [
                'platform' => $request->input('platform'),
                'keyword' => $request->input('keyword'),
                'force_regenerate' => $request->boolean('force_regenerate', false),
            ];

            $result = $this->pipelineService->generateDailyAggregates(
                $startDate,
                $endDate,
                $options
            );

            return response()->json([
                'status' => 'success',
                'data' => $result,
                'meta' => [
                    'timestamp' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Generate aggregates API error', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Aggregate generation failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get pipeline performance metrics
     * 
     * @route GET /api/sentiment/performance
     */
    public function getPerformance(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => 'nullable|date|before_or_equal:today',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $date = $request->input('date') 
                ? Carbon::parse($request->input('date'))
                : Carbon::today();

            $metrics = $this->pipelineService->getPerformanceMetrics($date);

            return response()->json([
                'status' => 'success',
                'data' => $metrics,
                'meta' => [
                    'date' => $date->toDateString(),
                    'timestamp' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Get performance API error', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve performance metrics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get pipeline status
     * 
     * @route GET /api/sentiment/status
     */
    public function getStatus(): JsonResponse
    {
        try {
            $status = $this->pipelineService->getPipelineStatus();

            return response()->json([
                'status' => 'success',
                'data' => $status,
            ]);

        } catch (\Exception $e) {
            Log::error('Get pipeline status API error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve pipeline status',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Estimate processing cost
     * 
     * @route POST /api/sentiment/estimate-cost
     */
    public function estimateCost(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'texts' => 'required|array|min:1|max:10000',
            'texts.*' => 'required|string|min:10|max:5000',
            'enable_entities' => 'nullable|boolean',
            'enable_classification' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $options = [
                'enable_entities' => $request->boolean('enable_entities', true),
                'enable_classification' => $request->boolean('enable_classification', true),
            ];

            $estimate = $this->pipelineService->estimateProcessingCost(
                $request->input('texts'),
                $options
            );

            return response()->json([
                'status' => 'success',
                'data' => $estimate,
                'meta' => [
                    'timestamp' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Estimate cost API error', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Cost estimation failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get sentiment trends for a platform/keyword
     * 
     * @route GET /api/sentiment/trends
     */
    public function getTrends(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date|before_or_equal:today',
            'end_date' => 'nullable|date|before_or_equal:today|after_or_equal:start_date',
            'platform' => 'nullable|string|max:50',
            'keyword' => 'nullable|string|max:100',
            'interval' => 'nullable|in:daily,weekly,monthly',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $startDate = $request->input('start_date') 
                ? Carbon::parse($request->input('start_date'))
                : Carbon::now()->subDays(30);
            
            $endDate = $request->input('end_date')
                ? Carbon::parse($request->input('end_date'))
                : Carbon::today();

            $query = DailySentimentAggregate::dateRange($startDate, $endDate);

            if ($request->has('platform')) {
                $query->forPlatform($request->input('platform'));
            }

            if ($request->has('keyword')) {
                $query->forKeyword($request->input('keyword'));
            }

            $aggregates = $query->orderBy('aggregate_date')->get();

            // Calculate trends
            $trends = $aggregates->map(function ($aggregate) {
                return [
                    'date' => $aggregate->aggregate_date->toDateString(),
                    'sentiment_score' => (float) $aggregate->avg_sentiment_score,
                    'magnitude' => (float) $aggregate->avg_magnitude,
                    'post_count' => $aggregate->total_posts,
                    'positive_percentage' => (float) $aggregate->positive_percentage,
                    'negative_percentage' => (float) $aggregate->negative_percentage,
                    'platform' => $aggregate->platform,
                    'keyword' => $aggregate->keyword,
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'trends' => $trends,
                    'summary' => [
                        'period' => [
                            'start' => $startDate->toDateString(),
                            'end' => $endDate->toDateString(),
                            'days' => $startDate->diffInDays($endDate) + 1,
                        ],
                        'overall_sentiment' => $aggregates->avg('avg_sentiment_score'),
                        'total_posts' => $aggregates->sum('total_posts'),
                        'sentiment_volatility' => $aggregates->avg('avg_magnitude'),
                    ],
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Get trends API error', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve trends',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
