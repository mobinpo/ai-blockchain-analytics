<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

/**
 * Enhanced Telescope Service Provider
 * 
 * Provides production-ready Telescope configuration with advanced
 * access control, data filtering, and AI Blockchain Analytics monitoring
 */
final class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Only register Telescope in appropriate environments
        if (!$this->shouldRegisterTelescope()) {
            return;
        }

        parent::register();

        // Register custom monitoring services
        $this->registerCustomMonitoring();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (!$this->shouldRegisterTelescope()) {
            return;
        }

        parent::boot();

        // Configure data filtering and privacy
        $this->configureDataFiltering();
        
        // Setup access logging
        $this->setupAccessLogging();
    }

    /**
     * Determine if Telescope should be registered.
     */
    protected function shouldRegisterTelescope(): bool
    {
        // Check master monitoring switch
        if (!config('monitoring.enabled', true)) {
            return false;
        }

        // Check Telescope specific configuration
        if (!config('monitoring.telescope.enabled', true)) {
            return false;
        }

        // In production, only register if explicitly enabled
        if (app()->environment('production')) {
            return config('monitoring.telescope.production.enabled', false);
        }

        return true;
    }

    /**
     * Configure the Telescope authorization services.
     */
    protected function authorization(): void
    {
        $this->gate();

        Telescope::auth(function ($request) {
            return $this->authorizeTelescopeAccess($request);
        });
    }

    /**
     * Configure the Telescope gate.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function ($user = null) {
            // In production, use strict authorization
            if (app()->environment('production')) {
                return $this->authorizeProductionUser($user);
            }

            // In non-production, be more permissive but still require auth
            return $user !== null;
        });
    }

    /**
     * Authorize Telescope access for the request.
     */
    protected function authorizeTelescopeAccess($request): bool
    {
        // Check if monitoring is enabled
        if (!config('monitoring.enabled', true)) {
            return false;
        }

        // Production authorization
        if (app()->environment('production')) {
            return $this->authorizeProductionAccess($request);
        }

        // Non-production authorization
        return $this->authorizeNonProductionAccess($request);
    }

    /**
     * Authorize production access with strict controls.
     */
    protected function authorizeProductionAccess($request): bool
    {
        // Check if production access is enabled
        if (!config('monitoring.telescope.production.enabled', false)) {
            Log::warning('Telescope production access attempt when disabled', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            return false;
        }

        // Check IP whitelist
        $allowedIPs = config('monitoring.telescope.production.allowed_ips', []);
        if (!empty($allowedIPs) && !$this->isAllowedIP($request->ip(), $allowedIPs)) {
            Log::warning('Telescope access denied: IP not whitelisted', [
                'ip' => $request->ip(),
                'allowed_ips' => $allowedIPs,
            ]);
            return false;
        }

        // Require authentication
        if (!Auth::check()) {
            return false;
        }

        // Check user authorization
        $user = Auth::user();
        $allowedEmails = config('monitoring.telescope.production.allowed_emails', []);
        
        if (!empty($allowedEmails) && !in_array($user->email, $allowedEmails)) {
            Log::warning('Telescope access denied: User email not authorized', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
            ]);
            return false;
        }

        // Check custom gate
        if (!Gate::allows('viewTelescope', $user)) {
            return false;
        }

        // Log successful access
        Log::info('Telescope production access granted', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
        ]);

        return true;
    }

    /**
     * Authorize non-production access.
     */
    protected function authorizeNonProductionAccess($request): bool
    {
        // In local development, allow without authentication
        if (app()->environment('local')) {
            return true;
        }

        // In staging, require authentication
        if (!Auth::check()) {
            return false;
        }

        // Check custom gate
        return Gate::allows('viewTelescope', Auth::user());
    }

    /**
     * Authorize production user access.
     */
    protected function authorizeProductionUser($user): bool
    {
        if (!$user) {
            return false;
        }

        // Check allowed emails
        $allowedEmails = config('monitoring.telescope.production.allowed_emails', []);
        if (!empty($allowedEmails)) {
            return in_array($user->email, $allowedEmails);
        }

        // Check if user has admin role (if roles are implemented)
        if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            return true;
        }

        // Check telescope_access flag on user model
        if (isset($user->telescope_access) && $user->telescope_access) {
            return true;
        }

        return false;
    }

    /**
     * Check if IP is in allowed list.
     */
    protected function isAllowedIP(string $ip, array $allowedIPs): bool
    {
        foreach ($allowedIPs as $allowedIP) {
            if ($this->ipInRange($ip, $allowedIP)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if IP is in range (supports CIDR).
     */
    protected function ipInRange(string $ip, string $range): bool
    {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }

        [$subnet, $bits] = explode('/', $range);
        
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ip = ip2long($ip);
            $subnet = ip2long($subnet);
            $mask = -1 << (32 - $bits);
            $subnet &= $mask;
            return ($ip & $mask) === $subnet;
        }

        return false;
    }

    /**
     * Configure data filtering for privacy and security.
     */
    protected function configureDataFiltering(): void
    {
        Telescope::filter(function (IncomingEntry $entry) {
            // Skip in production based on sampling rate
            if (app()->environment('production')) {
                $samplingRate = config('monitoring.telescope.performance.sampling_rate', 0.1);
                if (mt_rand() / mt_getrandmax() > $samplingRate) {
                    return false;
                }
            }

            // Filter based on entry type and configuration
            return $this->shouldIncludeEntry($entry);
        });

        // Hide sensitive data
        Telescope::hideRequestParameters(['password', 'password_confirmation', 'token', 'api_key']);
        Telescope::hideRequestHeaders(['authorization', 'x-api-key', 'x-auth-token']);
    }

    /**
     * Determine if an entry should be included.
     */
    protected function shouldIncludeEntry(IncomingEntry $entry): bool
    {
        // Always include exceptions and important events
        if (in_array($entry->type, ['exception', 'job', 'mail', 'notification'])) {
            return true;
        }

        // Filter requests based on configuration
        if ($entry->type === 'request') {
            return $this->shouldIncludeRequest($entry);
        }

        // Filter queries based on configuration
        if ($entry->type === 'query') {
            return $this->shouldIncludeQuery($entry);
        }

        // Filter cache events in production
        if ($entry->type === 'cache' && app()->environment('production')) {
            return false;
        }

        return true;
    }

    /**
     * Determine if a request entry should be included.
     */
    protected function shouldIncludeRequest(IncomingEntry $entry): bool
    {
        $uri = $entry->content['uri'] ?? '';

        // Skip health check endpoints
        $skipPatterns = [
            '/up', '/health', '/ping', '/metrics', '/status',
            '/telescope', '/horizon', '/_debugbar',
            '/favicon.ico', '/robots.txt', '/.well-known'
        ];

        foreach ($skipPatterns as $pattern) {
            if (str_contains($uri, $pattern)) {
                return false;
            }
        }

        // In production, only include API requests and errors
        if (app()->environment('production')) {
            $statusCode = $entry->content['response_status'] ?? 200;
            return str_contains($uri, '/api/') || $statusCode >= 400;
        }

        return true;
    }

    /**
     * Determine if a query entry should be included.
     */
    protected function shouldIncludeQuery(IncomingEntry $entry): bool
    {
        // In production, only include slow queries
        if (app()->environment('production')) {
            $time = $entry->content['time'] ?? 0;
            $slowThreshold = 100; // milliseconds
            return $time > $slowThreshold;
        }

        return true;
    }

    /**
     * Setup access logging.
     */
    protected function setupAccessLogging(): void
    {
        if (!config('monitoring.telescope.production.log_access_attempts', true)) {
            return;
        }

        // Additional access logging can be implemented here
    }

    /**
     * Register custom monitoring services.
     */
    protected function registerCustomMonitoring(): void
    {
        // Register custom Telescope commands if needed
        if ($this->app->runningInConsole()) {
            $this->commands([
                // Custom pruning commands could be added here
            ]);
        }
    }
}
