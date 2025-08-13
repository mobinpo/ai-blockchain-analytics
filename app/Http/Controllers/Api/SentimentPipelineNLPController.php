<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SentimentPipelineProcessor;
use App\Services\GoogleCloudNLPService;
use App\Models\DailySentimentAggregate;
use App\Models\SocialMediaPost;
use App\Jobs\ProcessDailySentiment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class SentimentPipelineNLPController extends Controller
{
    protected SentimentPipelineProcessor $processor;
    protected GoogleCloudNLPService $nlpService;

    public function __construct(SentimentPipelineProcessor $processor, GoogleCloudNLPService $nlpService)
    {
        $this->processor = $processor;
        $this->nlpService = $nlpService;
    }

    /**
     * Get daily sentiment aggregates
     */
    public function getDailyAggregates(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => 'nullable|date',
            'platform' => 'nullable|in:twitter,reddit,telegram',
            'keyword' => 'nullable|string|max:100',
            'days' => 'nullable|integer|min:1|max:90'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = DailySentimentAggregate::query();

            if ($request->date) {
                $query->whereDate('date', $request->date);
            } else {
                $days = $request->get('days', 7);
                $query->where('date', '>=', Carbon::now()->subDays($days)->toDateString());
            }

            if ($request->platform) {
                $query->where('platform', $request->platform);
            }

            if ($request->keyword) {
                $query->where('keyword', $request->keyword);
            }

            $aggregates = $query->orderBy('aggregate_date', 'desc')
                ->orderBy('platform')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $aggregates,
                'summary' => [
                    'total_records' => $aggregates->count(),
                    'date_range' => [
                        'from' => $aggregates->min('date'),
                        'to' => $aggregates->max('date')
                    ],
                    'platforms' => $aggregates->pluck('platform')->unique()->values(),
                    'avg_sentiment' => round($aggregates->avg('avg_sentiment_score'), 4)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching daily aggregates', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch daily aggregates'
            ], 500);
        }
    }

    /**
     * Trigger daily sentiment processing
     */
    public function processDailySentiment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => 'nullable|date',
            'queue' => 'nullable|boolean',
            'platform' => 'nullable|in:twitter,reddit,telegram'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $date = $request->date ? Carbon::parse($request->date) : Carbon::yesterday();
            $useQueue = $request->get('queue', true);

            Log::info('API triggered sentiment processing', [
                'date' => $date->toDateString(),
                'queue' => $useQueue,
                'platform' => $request->platform,
                'user_id' => auth()->id()
            ]);

            if ($useQueue) {
                ProcessDailySentiment::dispatch($date);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Sentiment processing job queued successfully',
                    'data' => [
                        'date' => $date->toDateString(),
                        'queued_at' => now(),
                        'estimated_completion' => now()->addMinutes(30)
                    ]
                ]);
            } else {
                // Synchronous processing
                $results = $this->processor->processDailySentiment($date);

                return response()->json([
                    'success' => true,
                    'message' => 'Sentiment processing completed successfully',
                    'data' => $results
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error processing daily sentiment', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to process daily sentiment'
            ], 500);
        }
    }

    /**
     * Process sentiment for specific posts
     */
    public function processPostsSentiment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'post_ids' => 'required|array|min:1|max:100',
            'post_ids.*' => 'integer|exists:social_media_posts,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $posts = SocialMediaPost::whereIn('id', $request->post_ids)->get();
            
            Log::info('API triggered manual sentiment processing', [
                'post_count' => $posts->count(),
                'user_id' => auth()->id()
            ]);

            $results = $this->processor->processPostsSentiment($posts);

            return response()->json([
                'success' => true,
                'message' => 'Posts sentiment processing completed',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Error processing posts sentiment', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to process posts sentiment'
            ], 500);
        }
    }

    /**
     * Get pipeline status
     */
    public function getPipelineStatus(): JsonResponse
    {
        try {
            $status = $this->processor->getPipelineStatus();

            return response()->json([
                'success' => true,
                'data' => $status
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting pipeline status', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to get pipeline status'
            ], 500);
        }
    }

    /**
     * Get Google Cloud NLP service health
     */
    public function getNLPServiceHealth(): JsonResponse
    {
        try {
            $health = $this->nlpService->getServiceHealth();

            return response()->json([
                'success' => true,
                'data' => $health
            ]);

        } catch (\Exception $e) {
            Log::error('Error checking NLP service health', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to check NLP service health',
                'data' => [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'timestamp' => now()
                ]
            ], 500);
        }
    }

    /**
     * Get sentiment trends
     */
    public function getSentimentTrends(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'days' => 'nullable|integer|min:1|max:90',
            'platform' => 'nullable|in:twitter,reddit,telegram',
            'keyword' => 'nullable|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $days = $request->get('days', 30);
            $startDate = Carbon::now()->subDays($days);
            $endDate = Carbon::now();

            $query = DailySentimentAggregate::where('date', '>=', $startDate->toDateString())
                ->where('date', '<=', $endDate->toDateString())
                ->orderBy('aggregate_date');

            if ($request->platform) {
                $query->where('platform', $request->platform);
            }

            if ($request->keyword) {
                $query->where('keyword', $request->keyword);
            }

            $aggregates = $query->get();

            // Group by date and calculate daily averages
            $trends = $aggregates->groupBy('date')->map(function ($dayAggregates) {
                return [
                    'date' => $dayAggregates->first()->date,
                    'avg_sentiment' => round($dayAggregates->avg('avg_sentiment_score'), 4),
                    'total_posts' => $dayAggregates->sum('total_posts'),
                    'positive_percentage' => round($dayAggregates->avg('positive_percentage'), 2),
                    'negative_percentage' => round($dayAggregates->avg('negative_percentage'), 2),
                    'neutral_percentage' => round($dayAggregates->avg('neutral_percentage'), 2),
                    'platforms' => $dayAggregates->pluck('platform')->unique()->values()
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'trends' => $trends,
                    'summary' => [
                        'period' => [
                            'from' => $startDate->toDateString(),
                            'to' => $endDate->toDateString(),
                            'days' => $days
                        ],
                        'overall_avg_sentiment' => round($aggregates->avg('avg_sentiment_score'), 4),
                        'total_posts' => $aggregates->sum('total_posts'),
                        'total_records' => $aggregates->count()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching sentiment trends', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch sentiment trends'
            ], 500);
        }
    }

    /**
     * Get keyword sentiment analysis
     */
    public function getKeywordSentiment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'keywords' => 'required|array|min:1|max:10',
            'keywords.*' => 'string|max:100',
            'days' => 'nullable|integer|min:1|max:90',
            'platform' => 'nullable|in:twitter,reddit,telegram'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $keywords = $request->keywords;
            $days = $request->get('days', 7);
            $startDate = Carbon::now()->subDays($days);

            $results = [];

            foreach ($keywords as $keyword) {
                $query = DailySentimentAggregate::where('keyword', $keyword)
                    ->where('date', '>=', $startDate->toDateString());

                if ($request->platform) {
                    $query->where('platform', $request->platform);
                }

                $aggregates = $query->orderBy('aggregate_date', 'desc')->get();

                $results[$keyword] = [
                    'keyword' => $keyword,
                    'record_count' => $aggregates->count(),
                    'avg_sentiment' => round($aggregates->avg('avg_sentiment_score'), 4),
                    'total_posts' => $aggregates->sum('total_posts'),
                    'positive_percentage' => round($aggregates->avg('positive_percentage'), 2),
                    'negative_percentage' => round($aggregates->avg('negative_percentage'), 2),
                    'neutral_percentage' => round($aggregates->avg('neutral_percentage'), 2),
                    'recent_data' => $aggregates->take(7)->values()
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'keywords' => $results,
                    'period' => [
                        'days' => $days,
                        'from' => $startDate->toDateString(),
                        'to' => Carbon::now()->toDateString()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching keyword sentiment', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch keyword sentiment'
            ], 500);
        }
    }
}