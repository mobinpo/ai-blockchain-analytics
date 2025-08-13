<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Cache;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\IncomingEntry;
use Sentry\Laravel\Integration;
use Sentry\State\Scope;
use App\Services\Monitoring\SentryDataScrubber;
use App\Services\Monitoring\SentryRateLimiter;
use Exception;

/**
 * Enhanced Monitoring Service Provider
 * 
 * Integrates Sentry and Telescope with AI Blockchain Analytics specific features:
 * - Production-safe configurations
 * - Custom error grouping and tagging
 * - Performance monitoring
 * - Data scrubbing for sensitive information
 * - Rate limiting and sampling
 */
final class EnhancedMonitoringServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register enhanced monitoring services
        $this->app->singleton(SentryDataScrubber::class);
        $this->app->singleton(SentryRateLimiter::class);
        
        // Configure Sentry with enhanced settings
        $this->configureSentry();
        
        // Configure Telescope with production restrictions
        $this->configureTelescope();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->setupSentryIntegration();
        $this->setupTelescopeIntegration();
        $this->setupPerformanceMonitoring();
        $this->setupCustomLogging();
        $this->setupErrorReporting();
    }

    /**
     * Configure Sentry with enhanced AI Blockchain Analytics settings.
     */
    protected function configureSentry(): void
    {
        if (!$this->app->bound('sentry')) {
            return;
        }

        \Sentry\init([
            'dsn' => config('sentry.dsn'),
            'environment' => config('sentry.environment'),
            'release' => config('sentry.release'),
            'sample_rate' => config('sentry.sample_rate'),
            'traces_sample_rate' => config('sentry.traces_sample_rate'),
            'profiles_sample_rate' => config('sentry.profiles_sample_rate'),
            
            // Enhanced configuration
            'before_send' => [$this, 'beforeSendSentry'],
            'before_send_transaction' => [$this, 'beforeSendTransaction'],
            
            // Custom integrations
            'integrations' => [
                new \Sentry\Integration\RequestIntegration(),
                new \Sentry\Integration\TransactionIntegration(),
                new \Sentry\Integration\FrameContextifierIntegration(),
            ],
            
            // Performance
            'max_breadcrumbs' => $this->app->environment('production') ? 50 : 100,
            'attach_stacktrace' => !$this->app->environment('production'),
            'send_default_pii' => false,
            
            // Custom options
            'context_lines' => $this->app->environment('production') ? 3 : 5,
            'enable_compression' => true,
            'http_proxy' => env('SENTRY_HTTP_PROXY'),
            
            // AI Blockchain Analytics specific
            'tags' => $this->getDefaultSentryTags(),
            'user' => $this->getSentryUserContext(),
            'extra' => $this->getSentryExtraContext(),
        ]);
    }

    /**
     * Configure Telescope with production restrictions.
     */
    protected function configureTelescope(): void
    {
        if (!class_exists(Telescope::class)) {
            return;
        }

        // Production restrictions
        if ($this->app->environment('production')) {
            $this->applyProductionRestrictions();
        }

        // Custom filtering
        Telescope::filter([$this, 'telescopeFilter']);
        
        // Custom tagging
        Telescope::tag([$this, 'telescopeTag']);
        
        // Data scrubbing
        Telescope::hideRequestParameters([
            'password',
            'password_confirmation',
            'token',
            'secret',
            'api_key',
            'stripe_secret',
            'google_credentials',
        ]);
        
        Telescope::hideRequestHeaders([
            'authorization',
            'x-api-key',
            'x-auth-token',
            'cookie',
        ]);
    }

    /**
     * Setup Sentry integration with enhanced features.
     */
    protected function setupSentryIntegration(): void
    {
        if (!$this->app->bound('sentry')) {
            return;
        }

        // Add user context
        \Sentry\configureScope(function (Scope $scope): void {
            $scope->setContext('application', [
                'name' => 'AI Blockchain Analytics',
                'version' => config('app.version', 'unknown'),
                'environment' => config('app.env'),
                'debug' => config('app.debug'),
                'url' => config('app.url'),
            ]);

            $scope->setContext('server', [
                'name' => gethostname(),
                'container_role' => env('CONTAINER_ROLE', 'app'),
                'deployment_id' => env('DEPLOYMENT_ID', 'unknown'),
                'region' => env('AWS_DEFAULT_REGION', 'unknown'),
            ]);

            // Add request context if available
            if (request()) {
                $scope->setContext('request', [
                    'url' => request()->fullUrl(),
                    'method' => request()->method(),
                    'user_agent' => request()->userAgent(),
                    'ip' => request()->ip(),
                    'route' => optional(request()->route())->getName(),
                ]);
            }
        });

        // Setup error handling
        $this->app->singleton('sentry.error_handler', function () {
            return new class {
                public function handle(Exception $exception, array $context = []): void
                {
                    $rateLimiter = app(SentryRateLimiter::class);
                    
                    if (!$rateLimiter->allowError($exception)) {
                        return;
                    }

                    $scrubber = app(SentryDataScrubber::class);
                    $scrubbedContext = $scrubber->scrub($context);

                    \Sentry\captureException($exception, $scrubbedContext);
                }
            };
        });
    }

    /**
     * Setup Telescope integration.
     */
    protected function setupTelescopeIntegration(): void
    {
        if (!class_exists(Telescope::class)) {
            return;
        }

        // Integration with Sentry
        if (config('telescope.ai_blockchain.integrations.sentry.enabled')) {
            Telescope::filter(function (IncomingEntry $entry) {
                if ($entry->type === 'exception' && config('telescope.ai_blockchain.integrations.sentry.send_exceptions')) {
                    $this->sendTelescopeExceptionToSentry($entry);
                }
                return true;
            });
        }

        // Custom data retention
        if ($this->app->environment('production')) {
            $this->setupTelescopeDataRetention();
        }
    }

    /**
     * Setup performance monitoring.
     */
    protected function setupPerformanceMonitoring(): void
    {
        // Database query monitoring
        DB::listen(function ($query) {
            $time = $query->time;
            $threshold = config('sentry.ai_blockchain.performance.slow_query_threshold', 1000);
            
            if ($time > $threshold) {
                Log::warning('Slow query detected', [
                    'sql' => $query->sql,
                    'time' => $time,
                    'bindings' => $this->app->environment('production') ? '***' : $query->bindings,
                ]);

                if ($this->app->bound('sentry')) {
                    \Sentry\addBreadcrumb([
                        'message' => 'Slow query detected',
                        'category' => 'query',
                        'level' => 'warning',
                        'data' => [
                            'sql' => $query->sql,
                            'time' => $time,
                        ],
                    ]);
                }
            }
        });

        // Job monitoring
        Queue::failing(function ($connection, $job, $data) {
            Log::error('Job failed', [
                'connection' => $connection,
                'job' => $job->resolveName(),
                'data' => $data,
                'attempts' => $job->attempts(),
            ]);

            if ($this->app->bound('sentry')) {
                \Sentry\captureMessage('Job failed: ' . $job->resolveName(), 'error');
            }
        });

        // Memory monitoring
        if (config('sentry.ai_blockchain.performance.track_memory_usage')) {
            register_shutdown_function(function () {
                $memoryUsage = memory_get_peak_usage(true) / 1024 / 1024; // MB
                $threshold = config('sentry.ai_blockchain.performance.memory_threshold', 128);
                
                if ($memoryUsage > $threshold) {
                    Log::warning('High memory usage detected', [
                        'memory_usage' => $memoryUsage,
                        'threshold' => $threshold,
                    ]);
                }
            });
        }
    }

    /**
     * Setup custom logging integration.
     */
    protected function setupCustomLogging(): void
    {
        // Extend default logger with Sentry integration
        Log::listen(function ($level, $message, $context) {
            if (!$this->app->bound('sentry') || !in_array($level, ['error', 'critical', 'alert', 'emergency'])) {
                return;
            }

            $rateLimiter = app(SentryRateLimiter::class);
            if (!$rateLimiter->allowLog($level, $message)) {
                return;
            }

            $scrubber = app(SentryDataScrubber::class);
            $scrubbedContext = $scrubber->scrub($context);

            \Sentry\addBreadcrumb([
                'message' => $message,
                'category' => 'log',
                'level' => $level,
                'data' => $scrubbedContext,
            ]);

            if ($level === 'error') {
                \Sentry\captureMessage($message, $level, $scrubbedContext);
            }
        });
    }

    /**
     * Setup enhanced error reporting.
     */
    protected function setupErrorReporting(): void
    {
        // Custom exception handler
        $this->app->singleton(\Illuminate\Contracts\Debug\ExceptionHandler::class, function ($app) {
            return new class($app) extends \App\Exceptions\Handler {
                public function report(\Throwable $exception): void
                {
                    // Rate limiting
                    $rateLimiter = app(SentryRateLimiter::class);
                    if (!$rateLimiter->allowException($exception)) {
                        parent::report($exception);
                        return;
                    }

                    // Custom grouping
                    $this->addCustomGrouping($exception);
                    
                    // Enhanced context
                    $this->addEnhancedContext($exception);
                    
                    parent::report($exception);
                }

                protected function addCustomGrouping(\Throwable $exception): void
                {
                    $rules = config('sentry.ai_blockchain.error_grouping.rules', []);
                    
                    foreach ($rules as $rule) {
                        if (preg_match($rule['pattern'], $exception->getMessage())) {
                            \Sentry\configureScope(function (Scope $scope) use ($rule): void {
                                $scope->setFingerprint([$rule['grouping_key']]);
                            });
                            break;
                        }
                    }
                }

                protected function addEnhancedContext(\Throwable $exception): void
                {
                    \Sentry\configureScope(function (Scope $scope) use ($exception): void {
                        $scope->setContext('exception_details', [
                            'class' => get_class($exception),
                            'file' => $exception->getFile(),
                            'line' => $exception->getLine(),
                            'trace_hash' => md5($exception->getTraceAsString()),
                        ]);

                        // Add database context if available
                        if (app()->bound('db')) {
                            try {
                                $scope->setContext('database', [
                                    'connection' => config('database.default'),
                                    'query_count' => DB::getQueryLog() ? count(DB::getQueryLog()) : 0,
                                ]);
                            } catch (\Exception $e) {
                                // Ignore database context errors
                            }
                        }

                        // Add cache context
                        $scope->setContext('cache', [
                            'store' => config('cache.default'),
                        ]);

                        // Add queue context
                        $scope->setContext('queue', [
                            'connection' => config('queue.default'),
                        ]);
                    });
                }
            };
        });
    }

    /**
     * Sentry before send callback.
     */
    public function beforeSendSentry(array $event, ?array $hint = null): ?array
    {
        $rateLimiter = app(SentryRateLimiter::class);
        
        if (!$rateLimiter->allowEvent()) {
            return null;
        }

        $scrubber = app(SentryDataScrubber::class);
        return $scrubber->scrubEvent($event);
    }

    /**
     * Sentry before send transaction callback.
     */
    public function beforeSendTransaction(array $transaction): ?array
    {
        // Skip transactions for ignored paths
        $ignoredPaths = config('sentry.ignore_transactions', []);
        $transactionName = $transaction['transaction'] ?? '';
        
        foreach ($ignoredPaths as $path) {
            if (fnmatch($path, $transactionName)) {
                return null;
            }
        }

        return $transaction;
    }

    /**
     * Telescope filter callback.
     */
    public function telescopeFilter(IncomingEntry $entry): bool
    {
        if ($this->app->environment('local')) {
            return true;
        }

        // Apply sampling in production
        if ($this->app->environment('production')) {
            $samplingRate = config('telescope.ai_blockchain.performance.sampling_rate', 0.1);
            if (mt_rand() / mt_getrandmax() > $samplingRate) {
                return false;
            }

            // Only record important entries in production
            return match ($entry->type) {
                'exception' => true,
                'log' => isset($entry->content['level']) && $entry->content['level'] >= 400,
                'query' => isset($entry->content['time']) && $entry->content['time'] > 1000,
                'request' => in_array($entry->content['response_status'] ?? 200, [500, 502, 503, 504]),
                'job' => isset($entry->content['failed']) && $entry->content['failed'],
                default => false,
            };
        }

        return true;
    }

    /**
     * Telescope tag callback.
     */
    public function telescopeTag(IncomingEntry $entry): array
    {
        $tags = [];

        // Auto tags
        $autoTags = config('telescope.ai_blockchain.tagging.auto_tags', []);
        $tags = array_merge($tags, array_values($autoTags));

        // Request tags
        if ($entry->type === 'request') {
            $uri = $entry->content['uri'] ?? '';
            $requestTags = config('telescope.ai_blockchain.tagging.request_tags', []);
            
            foreach ($requestTags as $tag => $callback) {
                if (is_callable($callback) && $callback($entry)) {
                    $tags[] = $tag;
                }
            }
        }

        return array_filter($tags);
    }

    /**
     * Apply production restrictions for Telescope.
     */
    protected function applyProductionRestrictions(): void
    {
        // Disable Telescope if not explicitly enabled in production
        if (!config('telescope.ai_blockchain.production_enabled')) {
            config(['telescope.enabled' => false]);
            return;
        }

        // Apply memory limits
        $memoryLimit = config('telescope.ai_blockchain.performance.memory_limit');
        if ($memoryLimit) {
            ini_set('memory_limit', $memoryLimit);
        }
    }

    /**
     * Setup Telescope data retention for production.
     */
    protected function setupTelescopeDataRetention(): void
    {
        $this->app->booted(function () {
            $retentionHours = config('telescope.ai_blockchain.retention.hours', 24);
            $retentionLimit = config('telescope.ai_blockchain.retention.limit', 1000);

            // Schedule cleanup
            if (class_exists(\Laravel\Telescope\Storage\DatabaseEntriesRepository::class)) {
                $repository = app(\Laravel\Telescope\Storage\DatabaseEntriesRepository::class);
                
                // Clean up old entries
                $repository->prune(
                    \Carbon\Carbon::now()->subHours($retentionHours)
                );
            }
        });
    }

    /**
     * Send Telescope exception to Sentry.
     */
    protected function sendTelescopeExceptionToSentry(IncomingEntry $entry): void
    {
        if (!$this->app->bound('sentry')) {
            return;
        }

        $exceptionData = $entry->content;
        
        \Sentry\withScope(function (Scope $scope) use ($exceptionData): void {
            $scope->setTag('source', 'telescope');
            $scope->setContext('telescope_entry', $exceptionData);
            
            \Sentry\captureMessage(
                'Exception captured by Telescope: ' . ($exceptionData['class'] ?? 'Unknown'),
                'error'
            );
        });
    }

    /**
     * Get default Sentry tags.
     */
    protected function getDefaultSentryTags(): array
    {
        return config('sentry.ai_blockchain.auto_tagging.tags', []);
    }

    /**
     * Get Sentry user context.
     */
    protected function getSentryUserContext(): array
    {
        if (!auth()->check()) {
            return [];
        }

        $user = auth()->user();
        
        return [
            'id' => $user->id,
            'email' => config('sentry.ai_blockchain.enhanced_context.include_user_ip') ? $user->email : null,
            'ip_address' => config('sentry.ai_blockchain.enhanced_context.include_user_ip') ? request()->ip() : null,
        ];
    }

    /**
     * Get Sentry extra context.
     */
    protected function getSentryExtraContext(): array
    {
        $context = [];

        if (request() && config('sentry.ai_blockchain.enhanced_context.include_request_headers')) {
            $context['request_headers'] = request()->headers->all();
        }

        if (session() && config('sentry.ai_blockchain.enhanced_context.include_session_data')) {
            $context['session_data'] = session()->all();
        }

        return $context;
    }
}
