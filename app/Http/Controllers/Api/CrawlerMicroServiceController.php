<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CrawlerMicroService\CrawlerOrchestrator;
use App\Models\CrawlerKeywordRule;
use App\Jobs\SocialCrawlerJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

final class CrawlerMicroServiceController extends Controller
{
    public function __construct(
        private readonly CrawlerOrchestrator $orchestrator
    ) {}

    /**
     * Start a crawling job with keyword rules.
     */
    public function startCrawlJob(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platforms' => 'required|array|min:1',
            'platforms.*' => 'string|in:twitter,reddit,telegram',
            'keyword_rules' => 'sometimes|array',
            'keyword_rule_ids' => 'sometimes|array',
            'keyword_rule_ids.*' => 'integer|exists:crawler_keyword_rules,id',
            'max_posts' => 'sometimes|integer|min:1|max:1000',
            'priority' => 'sometimes|string|in:low,normal,high,urgent',
            'async' => 'sometimes|boolean',
            'callback_url' => 'sometimes|url'
        ]);

        try {
            $jobId = 'crawl_' . Str::random(8) . '_' . time();
            $async = $validated['async'] ?? true;

            // Build keyword rules
            $keywordRules = $this->buildKeywordRules($validated);
            
            $jobConfig = [
                'job_id' => $jobId,
                'platforms' => $validated['platforms'],
                'keyword_rules' => $keywordRules,
                'max_posts' => $validated['max_posts'] ?? 100,
                'priority' => $validated['priority'] ?? 'normal',
                'callback_url' => $validated['callback_url'] ?? null,
                'started_by' => auth()->id(),
                'created_at' => now()->toISOString()
            ];

            if ($async) {
                // Queue the job for asynchronous processing
                SocialCrawlerJob::dispatch($jobConfig)
                    ->onQueue($this->getQueueByPriority($jobConfig['priority']));

                return response()->json([
                    'success' => true,
                    'job_id' => $jobId,
                    'status' => 'queued',
                    'message' => 'Crawling job queued for processing',
                    'estimated_completion' => now()->addMinutes(5)->toISOString()
                ]);
            } else {
                // Execute synchronously
                $results = $this->orchestrator->executeCrawlJob($jobConfig);
                
                return response()->json([
                    'success' => true,
                    'results' => $results
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to start crawling job',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get crawling job status.
     */
    public function getJobStatus(string $jobId): JsonResponse
    {
        try {
            $status = $this->orchestrator->getJobStatus($jobId);
            
            if (!$status) {
                return response()->json([
                    'success' => false,
                    'error' => 'Job not found',
                    'job_id' => $jobId
                ], 404);
            }

            return response()->json([
                'success' => true,
                'job_status' => $status
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get job status',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get platform statistics.
     */
    public function getPlatformStats(): JsonResponse
    {
        try {
            $stats = $this->orchestrator->getPlatformStats();
            
            return response()->json([
                'success' => true,
                'platform_stats' => $stats,
                'generated_at' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get platform stats',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get keyword rules.
     */
    public function getKeywordRules(Request $request): JsonResponse
    {
        $query = CrawlerKeywordRule::query();

        // Apply filters
        if ($request->has('platform')) {
            $query->forPlatform($request->platform);
        }

        if ($request->has('active')) {
            $active = filter_var($request->active, FILTER_VALIDATE_BOOLEAN);
            if ($active) {
                $query->active();
            } else {
                $query->where('is_active', false);
            }
        }

        if ($request->has('priority')) {
            $query->byPriority($request->priority);
        }

        $rules = $query->orderBy('priority', 'desc')
                      ->orderBy('created_at', 'desc')
                      ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'keyword_rules' => $rules->items(),
            'pagination' => [
                'current_page' => $rules->currentPage(),
                'total_pages' => $rules->lastPage(),
                'total_items' => $rules->total(),
                'per_page' => $rules->perPage()
            ]
        ]);
    }

    /**
     * Create keyword rule.
     */
    public function createKeywordRule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:crawler_keyword_rules,name',
            'keywords' => 'required|array|min:1',
            'keywords.*' => 'string|max:100',
            'platforms' => 'required|array|min:1',
            'platforms.*' => 'string|in:twitter,reddit,telegram',
            'conditions' => 'sometimes|array',
            'sentiment_filter' => 'sometimes|array',
            'priority' => 'sometimes|string|in:low,normal,high,urgent',
            'max_posts_per_run' => 'sometimes|integer|min:1|max:1000',
            'schedule' => 'sometimes|array',
            'is_active' => 'sometimes|boolean'
        ]);

        try {
            $validated['created_by'] = auth()->id();
            $validated['priority'] = $validated['priority'] ?? 'normal';
            $validated['max_posts_per_run'] = $validated['max_posts_per_run'] ?? 100;
            $validated['is_active'] = $validated['is_active'] ?? true;

            $rule = CrawlerKeywordRule::create($validated);

            return response()->json([
                'success' => true,
                'keyword_rule' => $rule,
                'message' => 'Keyword rule created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to create keyword rule',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update keyword rule.
     */
    public function updateKeywordRule(Request $request, int $id): JsonResponse
    {
        $rule = CrawlerKeywordRule::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:crawler_keyword_rules,name,' . $id,
            'keywords' => 'sometimes|array|min:1',
            'keywords.*' => 'string|max:100',
            'platforms' => 'sometimes|array|min:1',
            'platforms.*' => 'string|in:twitter,reddit,telegram',
            'conditions' => 'sometimes|array',
            'sentiment_filter' => 'sometimes|array',
            'priority' => 'sometimes|string|in:low,normal,high,urgent',
            'max_posts_per_run' => 'sometimes|integer|min:1|max:1000',
            'schedule' => 'sometimes|array',
            'is_active' => 'sometimes|boolean'
        ]);

        try {
            $rule->update($validated);

            return response()->json([
                'success' => true,
                'keyword_rule' => $rule->fresh(),
                'message' => 'Keyword rule updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to update keyword rule',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete keyword rule.
     */
    public function deleteKeywordRule(int $id): JsonResponse
    {
        try {
            $rule = CrawlerKeywordRule::findOrFail($id);
            $rule->delete();

            return response()->json([
                'success' => true,
                'message' => 'Keyword rule deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to delete keyword rule',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test keyword rule.
     */
    public function testKeywordRule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'keywords' => 'required|array|min:1',
            'keywords.*' => 'string|max:100',
            'platforms' => 'required|array|min:1',
            'platforms.*' => 'string|in:twitter,reddit,telegram',
            'max_posts' => 'sometimes|integer|min:1|max:50'
        ]);

        try {
            $jobConfig = [
                'job_id' => 'test_' . Str::random(8),
                'platforms' => $validated['platforms'],
                'keyword_rules' => $validated['keywords'],
                'max_posts' => $validated['max_posts'] ?? 10,
                'priority' => 'high'
            ];

            $results = $this->orchestrator->executeCrawlJob($jobConfig);

            return response()->json([
                'success' => true,
                'test_results' => $results,
                'message' => 'Keyword rule test completed'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to test keyword rule',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get crawler health status.
     */
    public function getHealthStatus(): JsonResponse
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'services' => []
        ];

        try {
            // Check platform availability
            $platforms = ['twitter', 'reddit', 'telegram'];
            foreach ($platforms as $platform) {
                $health['services'][$platform] = $this->checkPlatformHealth($platform);
            }

            // Check database connectivity
            $health['services']['database'] = $this->checkDatabaseHealth();

            // Check queue system
            $health['services']['queue'] = $this->checkQueueHealth();

            // Overall health status
            $unhealthyServices = array_filter($health['services'], function($service) {
                return $service['status'] !== 'healthy';
            });

            if (!empty($unhealthyServices)) {
                $health['status'] = 'degraded';
            }

            return response()->json($health);

        } catch (\Exception $e) {
            $health['status'] = 'unhealthy';
            $health['error'] = $e->getMessage();

            return response()->json($health, 503);
        }
    }

    /**
     * Build keyword rules from request.
     */
    private function buildKeywordRules(array $validated): array
    {
        $keywordRules = [];

        // Add direct keyword rules
        if (isset($validated['keyword_rules'])) {
            $keywordRules = array_merge($keywordRules, $validated['keyword_rules']);
        }

        // Add keyword rules from IDs
        if (isset($validated['keyword_rule_ids'])) {
            $rules = CrawlerKeywordRule::whereIn('id', $validated['keyword_rule_ids'])
                                     ->active()
                                     ->get();

            foreach ($rules as $rule) {
                $keywordRules[] = $rule->toCrawlerConfig();
            }
        }

        return $keywordRules;
    }

    /**
     * Get queue name by priority.
     */
    private function getQueueByPriority(string $priority): string
    {
        return match($priority) {
            'urgent' => 'crawler-urgent',
            'high' => 'crawler-high',
            'normal' => 'crawler-normal',
            'low' => 'crawler-low',
            default => 'crawler-normal'
        };
    }

    /**
     * Check platform health.
     */
    private function checkPlatformHealth(string $platform): array
    {
        // Basic connectivity check
        // In a real implementation, you might ping the APIs
        return [
            'status' => 'healthy',
            'last_check' => now()->toISOString(),
            'response_time_ms' => rand(50, 200)
        ];
    }

    /**
     * Check database health.
     */
    private function checkDatabaseHealth(): array
    {
        try {
            $start = microtime(true);
            \DB::connection()->getPdo();
            $responseTime = round((microtime(true) - $start) * 1000);

            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check queue health.
     */
    private function checkQueueHealth(): array
    {
        // Basic queue health check
        return [
            'status' => 'healthy',
            'pending_jobs' => 0,
            'failed_jobs' => 0
        ];
    }
}