<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * SentryContext Middleware
 * 
 * Enhances Sentry error reporting with blockchain-specific context
 * and user information for the AI Blockchain Analytics platform v0.9.0
 */
final class SentryContext
{
    /**
     * Handle an incoming request and add Sentry context
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->bound('sentry') && config('sentry.dsn')) {
            \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($request): void {
                $this->addRequestContext($scope, $request);
                $this->addUserContext($scope);
                $this->addBlockchainContext($scope, $request);
                $this->addPerformanceContext($scope, $request);
                $this->addApplicationContext($scope);
            });
        }

        return $next($request);
    }

    /**
     * Add request-specific context to Sentry
     */
    private function addRequestContext(\Sentry\State\Scope $scope, Request $request): void
    {
        $scope->setTag('route', $request->route()?->getName() ?? 'unknown');
        $scope->setTag('method', $request->method());
        $scope->setTag('request_path', $request->path());
        
        // Add API endpoint context
        if ($request->is('api/*')) {
            $scope->setTag('api_endpoint', true);
            $scope->setTag('api_version', $request->segment(2) ?? 'v1');
        }

        // Add session information (privacy-safe)
        if ($request->hasSession()) {
            $scope->setExtra('session_id', substr($request->session()->getId(), 0, 8));
        }

        // Enhanced request context
        $scope->setContext('request', [
            'user_agent' => $request->userAgent(),
            'ip' => env('SENTRY_INCLUDE_USER_IP', false) ? $request->ip() : 'hidden',
            'referer_host' => $request->header('referer') ? parse_url($request->header('referer'), PHP_URL_HOST) : null,
            'content_type' => $request->header('content-type'),
            'accept' => $request->header('accept'),
        ]);
    }

    /**
     * Add user context to Sentry (privacy-compliant)
     */
    private function addUserContext(\Sentry\State\Scope $scope): void
    {
        if (Auth::check()) {
            $user = Auth::user();
            
            $scope->setUser([
                'id' => (string) $user->id,
                'username' => $user->name ?? 'unknown',
                'email' => env('SENTRY_INCLUDE_USER_EMAIL', false) 
                    ? $user->email 
                    : 'hidden',
                'subscription_active' => method_exists($user, 'subscribed') ? $user->subscribed() : null,
                'created_at' => $user->created_at?->toISOString(),
            ]);

            // Add user role information if available
            if (method_exists($user, 'hasRole')) {
                $scope->setTag('user_role', $user->getRoleNames()->first() ?? 'user');
            }
        } else {
            $scope->setUser(['id' => 'anonymous']);
        }
    }

    /**
     * Add blockchain-specific context to Sentry
     */
    private function addBlockchainContext(\Sentry\State\Scope $scope, Request $request): void
    {
        $blockchainContext = [];

        // Contract address (truncated for privacy)
        if ($request->has('contract_address')) {
            $contractAddress = $request->get('contract_address');
            $blockchainContext['contract_address'] = substr($contractAddress, 0, 10) . '...';
            $scope->setTag('has_contract_address', true);
        }

        // Blockchain network
        if ($request->has('network') || $request->has('blockchain')) {
            $network = $request->get('network') ?? $request->get('blockchain');
            $scope->setTag('blockchain_network', $network);
            $blockchainContext['network'] = $network;
        }

        // Transaction hash (if present, truncated)
        if ($request->has('tx_hash') || $request->has('transaction_hash')) {
            $txHash = $request->get('tx_hash') ?? $request->get('transaction_hash');
            $blockchainContext['transaction_hash'] = substr($txHash, 0, 10) . '...';
        }

        // Analysis type
        if ($request->has('analysis_type')) {
            $scope->setTag('analysis_type', $request->get('analysis_type'));
            $blockchainContext['analysis_type'] = $request->get('analysis_type');
        }

        // Token information
        if ($request->has('token_address')) {
            $scope->setTag('token_analysis', true);
            $blockchainContext['token_analysis'] = true;
        }

        if (!empty($blockchainContext)) {
            $scope->setContext('blockchain', $blockchainContext);
        }
    }

    /**
     * Add performance and system context
     */
    private function addPerformanceContext(\Sentry\State\Scope $scope, Request $request): void
    {
        $memoryUsage = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        $executionTime = defined('LARAVEL_START') ? microtime(true) - LARAVEL_START : 0;

        $scope->setContext('performance', [
            'memory_usage_mb' => round($memoryUsage / 1024 / 1024, 2),
            'peak_memory_mb' => round($peakMemory / 1024 / 1024, 2),
            'execution_time_ms' => round($executionTime * 1000, 2),
        ]);

        // Request size (for API requests)
        if ($request->isMethod('POST') || $request->isMethod('PUT') || $request->isMethod('PATCH')) {
            $contentLength = $request->server('CONTENT_LENGTH');
            if ($contentLength) {
                $scope->setExtra('request_size_bytes', (int) $contentLength);
            }
        }

        // Database connection info
        try {
            $connectionName = config('database.default');
            $scope->setTag('db_connection', $connectionName);
        } catch (\Exception $e) {
            // Ignore database connection errors for Sentry context
        }
    }

    /**
     * Add application-specific context
     */
    private function addApplicationContext(\Sentry\State\Scope $scope): void
    {
        $scope->setContext('application', [
            'environment' => app()->environment(),
            'version' => 'v0.9.0',
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'cache_driver' => config('cache.default'),
            'session_driver' => config('session.driver'),
            'octane_enabled' => class_exists('\Laravel\Octane\Facades\Octane'),
        ]);

        // Environment-specific tags
        $scope->setTag('app_env', app()->environment());
        $scope->setTag('app_version', 'v0.9.0');

        // Queue information (if processing jobs)
        if (app()->runningInConsole() && isset($_SERVER['argv'])) {
            $command = implode(' ', $_SERVER['argv']);
            if (str_contains($command, 'queue:work')) {
                $scope->setTag('queue_worker', true);
            }
        }

        // Add deployment information if available
        if ($deploymentId = config('app.deployment_id')) {
            $scope->setTag('deployment_id', $deploymentId);
        }
    }
}