<?php

namespace App\Services\Monitoring;

use Sentry\Event;
use Sentry\EventHint;
use Sentry\State\Scope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class SentryErrorProcessor
{
    public function __invoke(Event $event, ?EventHint $hint): ?Event
    {
        // Add custom blockchain analytics context
        $this->addBlockchainContext($event);
        
        // Add performance metrics
        $this->addPerformanceMetrics($event);
        
        // Add user context with privacy protection
        $this->addUserContext($event);
        
        // Add application-specific tags
        $this->addCustomTags($event);
        
        // Add environment context
        $this->addEnvironmentContext($event);
        
        // Filter sensitive data
        $this->filterSensitiveData($event);
        
        return $event;
    }

    protected function addBlockchainContext(Event $event): void
    {
        $context = [];

        // Add blockchain-specific information if available
        if (request()->has('contract_address')) {
            $context['contract_address'] = request('contract_address');
        }

        if (request()->has('chain_id')) {
            $context['chain_id'] = request('chain_id');
        }

        // Add current processing pipeline context
        if (Cache::has('current_sentiment_batch')) {
            $context['sentiment_batch_id'] = Cache::get('current_sentiment_batch');
        }

        if (Cache::has('current_nlp_pipeline')) {
            $context['nlp_pipeline'] = Cache::get('current_nlp_pipeline');
        }

        if (!empty($context)) {
            $event->setContext('blockchain', $context);
        }
    }

    protected function addPerformanceMetrics(Event $event): void
    {
        $metrics = [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'execution_time' => microtime(true) - LARAVEL_START,
        ];

        // Add database query count if available
        if (defined('DB_QUERY_COUNT')) {
            $metrics['db_query_count'] = DB_QUERY_COUNT;
        }

        // Add Redis operations count if available
        if (defined('REDIS_OPERATIONS_COUNT')) {
            $metrics['redis_operations_count'] = REDIS_OPERATIONS_COUNT;
        }

        $event->setContext('performance', $metrics);
    }

    protected function addUserContext(Event $event): void
    {
        if (Auth::check()) {
            $user = Auth::user();
            
            // Add user context with privacy protection
            $userContext = [
                'id' => $user->id,
                'created_at' => $user->created_at?->toISOString(),
            ];

            // Hash email for privacy in production
            if (app()->environment('production')) {
                $userContext['email_hash'] = hash('sha256', $user->email ?? '');
            } else {
                $userContext['email'] = $user->email;
            }

            // Add role information if available
            if (method_exists($user, 'getRoleNames')) {
                $userContext['roles'] = $user->getRoleNames()->toArray();
            } elseif (isset($user->role)) {
                $userContext['role'] = $user->role;
            }

            // Add verification status if available
            if (isset($user->verified_contracts_count)) {
                $userContext['verified_contracts_count'] = $user->verified_contracts_count;
            }

            $event->setUser($userContext);
        }
    }

    protected function addCustomTags(Event $event): void
    {
        $tags = [];

        // Add request-specific tags
        if (request()->route()) {
            $tags['route'] = request()->route()->getName() ?: 'unnamed';
            $tags['controller'] = request()->route()->getActionName();
        }

        // Add feature flags
        $tags['feature_pdf_generation'] = config('features.pdf_generation', false) ? 'enabled' : 'disabled';
        $tags['feature_verification_badges'] = config('features.verification_badges', false) ? 'enabled' : 'disabled';
        $tags['feature_sentiment_analysis'] = config('features.sentiment_analysis', false) ? 'enabled' : 'disabled';

        // Add deployment information
        if (config('app.version')) {
            $tags['app_version'] = config('app.version');
        }

        if (env('GIT_COMMIT_SHA')) {
            $tags['git_commit'] = substr(env('GIT_COMMIT_SHA'), 0, 8);
        }

        // Add container information if running in container
        if (env('CONTAINER_ROLE')) {
            $tags['container_role'] = env('CONTAINER_ROLE');
        }

        foreach ($tags as $key => $value) {
            $event->setTag($key, (string) $value);
        }
    }

    protected function addEnvironmentContext(Event $event): void
    {
        $context = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'timezone' => config('app.timezone'),
            'locale' => app()->getLocale(),
        ];

        // Add server information
        if (function_exists('gethostname')) {
            $context['hostname'] = gethostname();
        }

        // Add database information (without credentials)
        $context['database'] = [
            'connection' => config('database.default'),
            'driver' => config('database.connections.' . config('database.default') . '.driver'),
        ];

        // Add cache information
        $context['cache'] = [
            'default' => config('cache.default'),
            'driver' => config('cache.stores.' . config('cache.default') . '.driver'),
        ];

        // Add queue information
        $context['queue'] = [
            'default' => config('queue.default'),
            'driver' => config('queue.connections.' . config('queue.default') . '.driver'),
        ];

        $event->setContext('environment', $context);
    }

    protected function filterSensitiveData(Event $event): void
    {
        $sensitiveKeys = [
            'password', 'passwd', 'secret', 'api_key', 'token', 'key',
            'authorization', 'auth', 'x-api-key', 'x-auth-token',
            'credit_card', 'cc_number', 'ssn', 'social_security_number',
            'private_key', 'wallet_key', 'seed_phrase', 'mnemonic',
            'database_url', 'redis_url', 'mail_password',
        ];

        // Filter request data
        $request = $event->getRequest();
        if ($request && isset($request['data'])) {
            $request['data'] = $this->filterArray($request['data'], $sensitiveKeys);
            $event->setRequest($request);
        }

        // Filter extra data
        $extra = $event->getExtra();
        if ($extra) {
            $extra = $this->filterArray($extra, $sensitiveKeys);
            $event->setExtra($extra);
        }

        // Filter context data
        $contexts = $event->getContext();
        if ($contexts) {
            foreach ($contexts as $contextName => $contextData) {
                if (is_array($contextData)) {
                    $contexts[$contextName] = $this->filterArray($contextData, $sensitiveKeys);
                }
            }
            $event->setContext($contexts);
        }
    }

    protected function filterArray(array $data, array $sensitiveKeys): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->filterArray($value, $sensitiveKeys);
            } elseif ($this->isSensitiveKey($key, $sensitiveKeys)) {
                $data[$key] = '[Filtered]';
            }
        }

        return $data;
    }

    protected function isSensitiveKey(string $key, array $sensitiveKeys): bool
    {
        $key = strtolower($key);
        
        foreach ($sensitiveKeys as $sensitiveKey) {
            if (strpos($key, $sensitiveKey) !== false) {
                return true;
            }
        }

        return false;
    }
}