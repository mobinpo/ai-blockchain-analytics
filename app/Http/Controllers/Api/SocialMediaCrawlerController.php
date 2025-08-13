<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrawlerRule;
use App\Models\SocialMediaPost;
use App\Services\SocialMediaCrawlerService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SocialMediaCrawlerController extends Controller
{
    protected SocialMediaCrawlerService $crawlerService;

    public function __construct(SocialMediaCrawlerService $crawlerService)
    {
        $this->crawlerService = $crawlerService;
    }

    public function index(Request $request): JsonResponse
    {
        $posts = SocialMediaPost::query()
            ->when($request->platform, fn($q) => $q->byPlatform($request->platform))
            ->when($request->keyword, fn($q) => $q->byKeyword($request->keyword))
            ->when($request->sentiment, fn($q) => $q->withSentiment($request->sentiment))
            ->when($request->hours, fn($q) => $q->recentPosts((int)$request->hours))
            ->with('keywordMatches')
            ->orderBy('platform_created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $posts
        ]);
    }

    public function crawl(Request $request): JsonResponse
    {
        try {
            Log::info('Manual crawler triggered via API');
            
            $results = $this->crawlerService->crawlAll();
            
            $summary = [
                'total_platforms' => count($results),
                'total_posts' => 0,
                'by_platform' => []
            ];
            
            foreach ($results as $platform => $platformResults) {
                $platformTotal = 0;
                foreach ($platformResults as $result) {
                    if (isset($result['posts_found'])) {
                        $platformTotal += $result['posts_found'];
                    }
                }
                $summary['by_platform'][$platform] = $platformTotal;
                $summary['total_posts'] += $platformTotal;
            }

            return response()->json([
                'success' => true,
                'message' => 'Crawling completed successfully',
                'data' => $results,
                'summary' => $summary
            ]);

        } catch (\Exception $e) {
            Log::error('API crawler error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function rules(): JsonResponse
    {
        $rules = CrawlerRule::active()->orderBy('priority')->get();
        
        return response()->json([
            'success' => true,
            'data' => $rules
        ]);
    }

    public function createRule(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'platforms' => 'required|array|min:1',
            'platforms.*' => 'in:twitter,reddit,telegram',
            'keywords' => 'required|array|min:1',
            'keywords.*' => 'string|max:100',
            'hashtags' => 'nullable|array',
            'hashtags.*' => 'string|max:50',
            'accounts' => 'nullable|array',
            'accounts.*' => 'string|max:50',
            'sentiment_threshold' => 'nullable|integer|between:-100,100',
            'engagement_threshold' => 'integer|min:0',
            'priority' => 'integer|between:1,3',
            'filters' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $rule = CrawlerRule::create($validator->validated());
            
            Log::info('New crawler rule created', ['rule_id' => $rule->id, 'name' => $rule->name]);
            
            return response()->json([
                'success' => true,
                'message' => 'Crawler rule created successfully',
                'data' => $rule
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating crawler rule: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to create crawler rule'
            ], 500);
        }
    }

    public function updateRule(Request $request, CrawlerRule $rule): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'platforms' => 'array|min:1',
            'platforms.*' => 'in:twitter,reddit,telegram',
            'keywords' => 'array|min:1',
            'keywords.*' => 'string|max:100',
            'hashtags' => 'nullable|array',
            'accounts' => 'nullable|array',
            'sentiment_threshold' => 'nullable|integer|between:-100,100',
            'engagement_threshold' => 'integer|min:0',
            'active' => 'boolean',
            'priority' => 'integer|between:1,3',
            'filters' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $rule->update($validator->validated());
            
            Log::info('Crawler rule updated', ['rule_id' => $rule->id]);
            
            return response()->json([
                'success' => true,
                'message' => 'Crawler rule updated successfully',
                'data' => $rule->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating crawler rule: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to update crawler rule'
            ], 500);
        }
    }

    public function deleteRule(CrawlerRule $rule): JsonResponse
    {
        try {
            $rule->delete();
            
            Log::info('Crawler rule deleted', ['rule_id' => $rule->id]);
            
            return response()->json([
                'success' => true,
                'message' => 'Crawler rule deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting crawler rule: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to delete crawler rule'
            ], 500);
        }
    }

    public function stats(): JsonResponse
    {
        $stats = [
            'total_posts' => SocialMediaPost::count(),
            'posts_today' => SocialMediaPost::recentPosts(24)->count(),
            'posts_by_platform' => SocialMediaPost::selectRaw('platform, count(*) as count')
                ->groupBy('platform')
                ->pluck('count', 'platform'),
            'sentiment_distribution' => SocialMediaPost::selectRaw('
                    CASE 
                        WHEN sentiment_score > 0.1 THEN "positive"
                        WHEN sentiment_score < -0.1 THEN "negative" 
                        ELSE "neutral"
                    END as sentiment,
                    count(*) as count
                ')
                ->whereNotNull('sentiment_score')
                ->groupBy('sentiment')
                ->pluck('count', 'sentiment'),
            'active_rules' => CrawlerRule::active()->count(),
            'total_rules' => CrawlerRule::count()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
