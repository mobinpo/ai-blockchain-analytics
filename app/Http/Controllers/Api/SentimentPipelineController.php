<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SentimentPipelineService;
use App\Models\DailySentimentAggregate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Exception;

final class SentimentPipelineController extends Controller
{
    public function __construct(
        private SentimentPipelineService $pipelineService
    ) {}

    /**
     * Process text through sentiment pipeline
     */
    public function processText(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'texts' => 'required|array|min:1|max:1000',
                'texts.*' => 'required|string|min:3|max:5000',
                'options' => 'array',
                'options.platform' => 'string|max:50',
                'options.category' => 'string|max:50',
                'options.language' => 'string|size:2',
                'options.batch_size' => 'integer|min:1|max:100',
                'queue' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $validator->errors()
                ], 422);
            }

            $texts = $request->input('texts');
            $options = $request->input('options', []);
            $queue = $request->boolean('queue', false);

            // Set default options
            $options = array_merge([
                'platform' => 'api',
                'category' => 'general',
                'language' => 'en',
                'batch_size' => 25
            ], $options);

            if ($queue) {
                $batchId = $this->pipelineService->queueTextPipeline($texts, $options);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Text processing queued successfully',
                    'batch_id' => $batchId,
                    'status_url' => route('api.sentiment-pipeline.status', ['batchId' => $batchId])
                ]);
            }

            $result = $this->pipelineService->processTextPipeline($texts, $options);

            return response()->json([
                'success' => true,
                'result' => $result
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Processing failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get batch processing status
     */
    public function getBatchStatus(string $batchId): JsonResponse
    {
        try {
            $status = $this->pipelineService->getBatchStatus($batchId);

            return response()->json([
                'success' => true,
                'status' => $status
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get batch status',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get daily sentiment aggregates
     */
    public function getDailyAggregates(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'date' => 'date_format:Y-m-d',
                'start_date' => 'date_format:Y-m-d',
                'end_date' => 'date_format:Y-m-d|after_or_equal:start_date',
                'platform' => 'string|max:50',
                'category' => 'string|max:50',
                'language' => 'string|size:2',
                'limit' => 'integer|min:1|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $validator->errors()
                ], 422);
            }

            $query = DailySentimentAggregate::query()->fullDay();

            // Date filters
            if ($request->has('date')) {
                $query->forDate(Carbon::parse($request->input('date')));
            } elseif ($request->has('start_date') && $request->has('end_date')) {
                $query->dateRange(
                    Carbon::parse($request->input('start_date')),
                    Carbon::parse($request->input('end_date'))
                );
            } else {
                // Default to last 7 days
                $query->dateRange(Carbon::now()->subDays(7), Carbon::now());
            }

            // Optional filters
            if ($request->filled('platform')) {
                $query->forPlatform($request->input('platform'));
            }

            if ($request->filled('category')) {
                $query->forCategory($request->input('category'));
            }

            if ($request->filled('language')) {
                $query->forLanguage($request->input('language'));
            }

            $limit = $request->input('limit', 100);
            $aggregates = $query->orderBy('aggregate_date', 'desc')
                              ->limit($limit)
                              ->get();

            return response()->json([
                'success' => true,
                'data' => $aggregates,
                'count' => $aggregates->count(),
                'filters_applied' => array_filter([
                    'date' => $request->input('date'),
                    'start_date' => $request->input('start_date'),
                    'end_date' => $request->input('end_date'),
                    'platform' => $request->input('platform'),
                    'category' => $request->input('category'),
                    'language' => $request->input('language')
                ])
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get daily aggregates',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sentiment trends and statistics
     */
    public function getSentimentTrends(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'days' => 'integer|min:1|max:365',
                'platform' => 'string|max:50',
                'category' => 'string|max:50'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $validator->errors()
                ], 422);
            }

            $days = $request->input('days', 30);
            $startDate = Carbon::now()->subDays($days);
            
            $query = DailySentimentAggregate::query()
                ->fullDay()
                ->dateRange($startDate, Carbon::now())
                ->orderBy('aggregate_date', 'asc');

            if ($request->filled('platform')) {
                $query->forPlatform($request->input('platform'));
            }

            if ($request->filled('category')) {
                $query->forCategory($request->input('category'));
            }

            $aggregates = $query->get();

            // Calculate trends
            $trends = [
                'daily_sentiment' => [],
                'sentiment_distribution' => [
                    'very_positive' => 0,
                    'positive' => 0,
                    'neutral' => 0,
                    'negative' => 0,
                    'very_negative' => 0
                ],
                'total_posts' => 0,
                'average_sentiment' => 0,
                'sentiment_volatility' => 0,
                'trending_keywords' => [],
                'trending_entities' => []
            ];

            $sentimentScores = [];
            $allKeywords = [];
            $allEntities = [];

            foreach ($aggregates as $aggregate) {
                $trends['daily_sentiment'][] = [
                    'date' => $aggregate->aggregate_date->format('Y-m-d'),
                    'sentiment' => $aggregate->average_sentiment,
                    'posts' => $aggregate->total_posts,
                    'volatility' => $aggregate->sentiment_volatility
                ];

                $trends['sentiment_distribution']['very_positive'] += $aggregate->very_positive_count;
                $trends['sentiment_distribution']['positive'] += $aggregate->positive_count;
                $trends['sentiment_distribution']['neutral'] += $aggregate->neutral_count;
                $trends['sentiment_distribution']['negative'] += $aggregate->negative_count;
                $trends['sentiment_distribution']['very_negative'] += $aggregate->very_negative_count;

                $trends['total_posts'] += $aggregate->total_posts;
                $sentimentScores[] = $aggregate->average_sentiment;

                // Collect keywords and entities
                if ($aggregate->top_keywords) {
                    foreach ($aggregate->top_keywords as $keyword => $count) {
                        $allKeywords[$keyword] = ($allKeywords[$keyword] ?? 0) + $count;
                    }
                }

                if ($aggregate->top_entities) {
                    foreach ($aggregate->top_entities as $entity => $count) {
                        $allEntities[$entity] = ($allEntities[$entity] ?? 0) + $count;
                    }
                }
            }

            // Calculate averages and trends
            if (!empty($sentimentScores)) {
                $trends['average_sentiment'] = round(array_sum($sentimentScores) / count($sentimentScores), 3);
                
                if (count($sentimentScores) > 1) {
                    $mean = $trends['average_sentiment'];
                    $squaredDiffs = array_map(fn($score) => pow($score - $mean, 2), $sentimentScores);
                    $trends['sentiment_volatility'] = round(sqrt(array_sum($squaredDiffs) / count($squaredDiffs)), 3);
                }
            }

            // Top trending keywords and entities
            arsort($allKeywords);
            arsort($allEntities);
            
            $trends['trending_keywords'] = array_slice($allKeywords, 0, 20, true);
            $trends['trending_entities'] = array_slice($allEntities, 0, 20, true);

            return response()->json([
                'success' => true,
                'trends' => $trends,
                'period' => [
                    'days' => $days,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => Carbon::now()->format('Y-m-d')
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get sentiment trends',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pipeline configuration and limits
     */
    public function getConfiguration(): JsonResponse
    {
        try {
            $config = [
                'limits' => [
                    'max_texts_per_request' => 1000,
                    'max_text_length' => 5000,
                    'min_text_length' => 3,
                    'max_batch_size' => 100,
                    'default_batch_size' => 25
                ],
                'supported_languages' => [
                    'en' => 'English',
                    'es' => 'Spanish',
                    'fr' => 'French',
                    'de' => 'German',
                    'it' => 'Italian',
                    'pt' => 'Portuguese',
                    'ru' => 'Russian',
                    'ja' => 'Japanese',
                    'ko' => 'Korean',
                    'zh' => 'Chinese'
                ],
                'sentiment_categories' => [
                    'very_positive' => 'Very Positive (> 0.6)',
                    'positive' => 'Positive (0.2 to 0.6)',
                    'neutral' => 'Neutral (-0.2 to 0.2)',
                    'negative' => 'Negative (-0.6 to -0.2)',
                    'very_negative' => 'Very Negative (< -0.6)'
                ],
                'cost_estimate' => [
                    'per_1000_chars' => 0.0005,
                    'currency' => 'USD',
                    'note' => 'Google Cloud NLP API pricing'
                ]
            ];

            return response()->json([
                'success' => true,
                'configuration' => $config
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get configuration',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}