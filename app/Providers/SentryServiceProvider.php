<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Sentry\Laravel\Integration;
use Sentry\State\Scope;
use Sentry\Tracing\TransactionContext;
use Sentry\SentrySdk;

final class SentryServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register custom Sentry integrations
        $this->registerCustomIntegrations();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (!$this->app->bound('sentry')) {
            return;
        }

        $this->configureSentryScope();
        $this->setupPerformanceMonitoring();
        $this->setupErrorEnrichment();
        $this->setupCustomTags();
    }

    /**
     * Configure Sentry scope with custom data.
     */
    protected function configureSentryScope(): void
    {
        Integration::configureScope(function (Scope $scope): void {
            // Add custom tags from configuration
            $customTags = config('monitoring.sentry.custom_tags', []);
            foreach ($customTags as $key => $value) {
                if ($value !== null) {
                    $scope->setTag($key, (string) $value);
                }
            }

            // Add deployment information
            $scope->setTag('php_version', PHP_VERSION);
            $scope->setTag('laravel_version', app()->version());
            
            // Add server information
            $scope->setContext('server', [
                'name' => gethostname(),
                'php_sapi' => php_sapi_name(),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
            ]);
        });
    }

    /**
     * Setup performance monitoring for AI Blockchain Analytics operations.
     */
    protected function setupPerformanceMonitoring(): void
    {
        if (!config('monitoring.sentry.performance.monitor_api_requests', true)) {
            return;
        }

        // Monitor slow database queries
        if (config('monitoring.sentry.error_tracking.capture_slow_queries', true)) {
            $threshold = config('monitoring.sentry.error_tracking.slow_query_threshold', 2000);
            
            DB::listen(function (QueryExecuted $query) use ($threshold) {
                if ($query->time > $threshold) {
                    $this->reportSlowQuery($query);
                }
            });
        }

        // Monitor API requests with simpler approach
        $this->app->booted(function () {
            if ($this->app->runningInConsole()) {
                return;
            }

            try {
                $this->setupRequestContext();
            } catch (\Exception $e) {
                Log::warning('Failed to setup Sentry request context', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        });
    }

    /**
     * Setup error enrichment with additional context.
     */
    protected function setupErrorEnrichment(): void
    {
        $this->app['events']->listen('*', function ($event, $payload) {
            if (strpos(class_basename($event), 'Exception') !== false) {
                $this->enrichErrorContext($event, $payload);
            }
        });

        // Capture failed jobs if configured
        if (config('monitoring.sentry.error_tracking.capture_failed_jobs', true)) {
            $this->captureFailedJobs();
        }
    }

    /**
     * Setup custom tags based on request context.
     */
    protected function setupCustomTags(): void
    {
        $this->app->booted(function () {
            if ($this->app->runningInConsole()) {
                return;
            }

            Integration::configureScope(function (Scope $scope): void {
                $request = request();
                
                // Add route-based tags
                if ($request->route()) {
                    $routeName = $request->route()->getName();
                    $scope->setTag('route_name', $routeName ?: 'unnamed');
                    
                    // AI Blockchain Analytics specific route tags
                    if (str_contains($request->path(), 'api/')) {
                        $scope->setTag('request_type', 'api');
                    }
                    
                    if (str_contains($request->path(), 'verification-badge/')) {
                        $scope->setTag('feature', 'verification');
                    }
                    
                    if (str_contains($request->path(), 'sentiment/')) {
                        $scope->setTag('feature', 'sentiment_analysis');
                    }
                    
                    if (str_contains($request->path(), 'solidity-cleaner/')) {
                        $scope->setTag('feature', 'solidity_cleaner');
                    }
                }
                
                // Add user context if configured
                if (config('monitoring.sentry.context.include_user_context', true) && Auth::check()) {
                    $scope->setUser([
                        'id' => Auth::id(),
                        'email' => Auth::user()->email,
                        'subscription_tier' => Auth::user()->subscription_tier ?? 'free',
                    ]);
                }
            });
        });
    }

    /**
     * Register custom Sentry integrations.
     */
    protected function registerCustomIntegrations(): void
    {
        // Register blockchain operation monitoring
        $this->app->singleton('sentry.blockchain_monitor', function () {
            return new class {
                public function trackOperation(string $operation, array $context = []): void
                {
                    try {
                        if (!config('monitoring.sentry.performance.monitor_blockchain_operations', true)) {
                            return;
                        }

                        $transactionContext = new TransactionContext();
                        $transactionContext->setName("blockchain.{$operation}");
                        $transactionContext->setOp('blockchain_operation');
                        
                        $transaction = SentrySdk::getCurrentHub()->startTransaction($transactionContext);

                        // Use setData() for transaction metadata (requires array)
                        $transactionData = ['operation_type' => $operation];
                        foreach ($context as $key => $value) {
                            $transactionData[$key] = (string) $value;
                        }
                        $transaction->setData($transactionData);

                        // Also set tags using scope for better filtering
                        Integration::configureScope(function (Scope $scope) use ($operation, $context): void {
                            $scope->setTag('blockchain_operation', $operation);
                            foreach ($context as $key => $value) {
                                $scope->setTag("blockchain_{$key}", (string) $value);
                            }
                        });

                        SentrySdk::getCurrentHub()->setSpan($transaction);
                    } catch (\Exception $e) {
                        Log::warning('Failed to track blockchain operation in Sentry', [
                            'operation' => $operation,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            };
        });

        // Register AI operation monitoring
        $this->app->singleton('sentry.ai_monitor', function () {
            return new class {
                public function trackAIOperation(string $operation, array $context = []): void
                {
                    try {
                        if (!config('monitoring.sentry.performance.monitor_ai_operations', true)) {
                            return;
                        }

                        $transactionContext = new TransactionContext();
                        $transactionContext->setName("ai.{$operation}");
                        $transactionContext->setOp('ai_operation');
                        
                        $transaction = SentrySdk::getCurrentHub()->startTransaction($transactionContext);

                        // Use setData() for transaction metadata (requires array)
                        $transactionData = ['ai_operation' => $operation];
                        foreach ($context as $key => $value) {
                            $transactionData[$key] = (string) $value;
                        }
                        $transaction->setData($transactionData);

                        // Also set tags using scope for better filtering
                        Integration::configureScope(function (Scope $scope) use ($operation, $context): void {
                            $scope->setTag('ai_operation', $operation);
                            foreach ($context as $key => $value) {
                                $scope->setTag("ai_{$key}", (string) $value);
                            }
                        });

                        SentrySdk::getCurrentHub()->setSpan($transaction);
                    } catch (\Exception $e) {
                        Log::warning('Failed to track AI operation in Sentry', [
                            'operation' => $operation,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            };
        });
    }

    /**
     * Report slow database queries to Sentry.
     */
    protected function reportSlowQuery(QueryExecuted $query): void
    {
        Integration::configureScope(function (Scope $scope) use ($query): void {
            $scope->setExtra('slow_query', [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'time' => $query->time,
                'connection' => $query->connectionName,
            ]);
        });

        app('sentry')->captureMessage("Slow query detected: {$query->time}ms");
    }

    /**
     * Setup request context with simpler approach.
     */
    protected function setupRequestContext(): void
    {
        try {
            $request = request();
            
            // Safety check for request availability
            if (!$request) {
                return;
            }
            
            $method = $request->getMethod();
            $uri = $request->getRequestUri();
        } catch (\Exception $e) {
            // If we can't get request info, just skip
            return;
        }
        
        // Skip certain routes that might cause issues
        if ($this->shouldSkipRouteContext($uri)) {
            return;
        }
        
        // Use only scope-based tagging for simplicity and reliability
        Integration::configureScope(function (Scope $scope) use ($method, $uri): void {
            $scope->setTag('http_method', $method);
            $scope->setTag('request_uri', $uri);
            
            // Add AI Blockchain Analytics specific context
            if (str_contains($uri, 'api/')) {
                $scope->setTag('request_category', 'api');
                
                // Add feature-specific tags
                if (str_contains($uri, 'contracts/analyze')) {
                    $scope->setTag('feature', 'contract_analysis');
                } elseif (str_contains($uri, 'sentiment')) {
                    $scope->setTag('feature', 'sentiment_analysis');
                } elseif (str_contains($uri, 'verification-badge')) {
                    $scope->setTag('feature', 'verification');
                }
            }
            
            // Only set request context if we have a request instance
            if (request() instanceof \Illuminate\Http\Request) {
                $currentRequest = request();
                $scope->setContext('request', [
                    'method' => $method,
                    'uri' => $uri,
                    'user_agent' => $currentRequest->userAgent(),
                    'ip' => $currentRequest->ip(),
                ]);
            }
        });
    }

    /**
     * Check if we should skip context setup for certain routes.
     */
    protected function shouldSkipRouteContext(string $uri): bool
    {
        $skipPatterns = [
            '/_debugbar',
            '/telescope',
            '/horizon',
            '/favicon.ico',
            '/robots.txt',
            '/.well-known'
        ];

        foreach ($skipPatterns as $pattern) {
            if (str_contains($uri, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Enrich error context with additional information.
     */
    protected function enrichErrorContext($event, $payload): void
    {
        Integration::configureScope(function (Scope $scope) use ($event, $payload): void {
            $scope->setContext('event', [
                'class' => get_class($event),
                'payload_count' => is_array($payload) ? count($payload) : 0,
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true),
            ]);
        });
    }

    /**
     * Setup failed job capture.
     */
    protected function captureFailedJobs(): void
    {
        $this->app['events']->listen(\Illuminate\Queue\Events\JobFailed::class, function ($event) {
            Integration::configureScope(function (Scope $scope) use ($event): void {
                $scope->setContext('failed_job', [
                    'job' => $event->job->getName(),
                    'queue' => $event->job->getQueue(),
                    'connection' => $event->connectionName,
                    'exception_message' => $event->exception->getMessage(),
                    'attempts' => method_exists($event->job, 'attempts') ? $event->job->attempts() : 'unknown',
                ]);
            });

            app('sentry')->captureException($event->exception);
        });
    }
}