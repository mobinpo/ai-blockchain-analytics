<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\JsonResponse;
use App\Services\SubscriptionPlanService;

class SubscriptionMiddleware
{
    public function __construct(
        private SubscriptionPlanService $planService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $action = null): Response
    {
        $user = $request->user();

        if (!$user) {
            return $this->unauthorized('Authentication required');
        }

        // For specific actions, check if user can perform them
        if ($action) {
            if (!$this->canPerformAction($user, $action)) {
                return $this->forbidden($action);
            }
        } else {
            // General subscription check
            if (!$user->subscribed() && !$user->onTrial()) {
                return $this->requiresSubscription();
            }
        }

        return $next($request);
    }

    /**
     * Check if user can perform a specific action
     */
    private function canPerformAction($user, string $action): bool
    {
        // Free tier users can perform limited actions
        if (!$user->subscribed() && !$user->onTrial()) {
            $currentUsage = $user->getCurrentUsage();
            $freeLimits = $this->planService->getFreeTierLimits();

            return match($action) {
                'analysis' => $currentUsage->analysis_count < $freeLimits['analysis_limit'],
                'api_call' => $currentUsage->api_calls_count < $freeLimits['api_calls_limit'],
                'project_create' => $user->projects()->count() < $freeLimits['projects_limit'],
                default => false
            };
        }

        // Check subscription limits
        return $user->canPerformAction($action);
    }

    /**
     * Return unauthorized response
     */
    private function unauthorized(string $message): JsonResponse
    {
        return response()->json([
            'error' => 'Unauthorized',
            'message' => $message,
            'code' => 'UNAUTHORIZED'
        ], 401);
    }

    /**
     * Return forbidden response for subscription required
     */
    private function requiresSubscription(): JsonResponse
    {
        return response()->json([
            'error' => 'Subscription Required',
            'message' => 'This feature requires an active subscription. Please upgrade your plan to continue.',
            'code' => 'SUBSCRIPTION_REQUIRED',
            'upgrade_url' => route('billing.plans'),
        ], 402); // 402 Payment Required
    }

    /**
     * Return forbidden response for action limit exceeded
     */
    private function forbidden(string $action): JsonResponse
    {
        $user = request()->user();
        $currentUsage = $user->getCurrentUsage();
        
        $planName = $user->subscribed() ? $user->subscription()->name : 'free';
        $limits = $user->subscribed() ? 
            $user->getSubscriptionLimits($user->subscription()->name) : 
            $this->planService->getFreeTierLimits();

        $limitKey = $action . '_limit';
        $currentCount = match($action) {
            'analysis' => $currentUsage->analysis_count,
            'api_call' => $currentUsage->api_calls_count,
            'project_create' => $user->projects()->count(),
            default => 0
        };

        return response()->json([
            'error' => 'Limit Exceeded',
            'message' => "You have reached your {$action} limit for this billing period.",
            'code' => 'LIMIT_EXCEEDED',
            'details' => [
                'action' => $action,
                'current_usage' => $currentCount,
                'limit' => $limits[$limitKey] ?? 0,
                'plan' => $planName,
                'period_end' => $currentUsage->period_end->format('Y-m-d'),
            ],
            'upgrade_url' => route('billing.plans'),
        ], 402); // 402 Payment Required
    }
}
