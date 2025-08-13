<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Telescope\TelescopeServiceProvider;
use App\Services\Monitoring\SentryRateLimiter;
use App\Services\Monitoring\SentryDataScrubber;

class MonitoringServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register Sentry services
        $this->registerSentryServices();

        // Register Telescope only in non-production environments or when explicitly enabled
        if ($this->shouldRegisterTelescope()) {
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Register Sentry-related services.
     */
    private function registerSentryServices(): void
    {
        // Register Sentry main service
        if ($this->app->environment('production', 'staging') && config('sentry.dsn')) {
            $this->app->register(\Sentry\Laravel\ServiceProvider::class);
        }

        // Register custom Sentry services
        $this->app->singleton('sentry.rate_limiter', function () {
            return new SentryRateLimiter();
        });

        $this->app->singleton('sentry.data_scrubber', function () {
            return new SentryDataScrubber();
        });

        // Create aliases for easier access
        $this->app->alias('sentry.rate_limiter', SentryRateLimiter::class);
        $this->app->alias('sentry.data_scrubber', SentryDataScrubber::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Configure Sentry
        if ($this->app->bound('sentry')) {
            \Sentry\configureScope(function (\Sentry\State\Scope $scope): void {
                $scope->setTag('environment', app()->environment());
                $scope->setTag('version', config('app.version', '1.0.0'));
            });
        }

        // Configure Telescope authorization
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            $this->configureTelescopeAuthorization();
        }
    }

    /**
     * Determine if Telescope should be registered.
     */
    private function shouldRegisterTelescope(): bool
    {
        // Never register in production unless explicitly enabled
        if ($this->app->environment('production') && !config('telescope.enabled', false)) {
            return false;
        }

        // Always register in local/testing environments
        if ($this->app->environment(['local', 'testing'])) {
            return true;
        }

        // Register in staging if enabled
        if ($this->app->environment('staging') && config('telescope.enabled', true)) {
            return true;
        }

        return false;
    }

    /**
     * Configure Telescope authorization.
     */
    private function configureTelescopeAuthorization(): void
    {
        \Laravel\Telescope\Telescope::auth(function ($request) {
            // In production, only allow specific users or IPs
            if (app()->environment('production')) {
                return $this->authorizeProductionAccess($request);
            }

            // In staging, allow authenticated users with admin role
            if (app()->environment('staging')) {
                return $request->user() && $request->user()->hasRole('admin');
            }

            // In local/testing, allow all access
            return app()->environment(['local', 'testing']);
        });

        // Configure Telescope to only record data when needed
        \Laravel\Telescope\Telescope::filter(function (\Laravel\Telescope\IncomingEntry $entry) {
            // Don't record in production unless debugging is enabled
            if (app()->environment('production') && !config('app.debug', false)) {
                return false;
            }

            // Filter out noise in all environments
            if ($entry->type === 'request') {
                return !str_contains($entry->content['uri'], 'telescope') &&
                       !str_contains($entry->content['uri'], 'livewire') &&
                       !str_contains($entry->content['uri'], '_ignition');
            }

            return true;
        });
    }

    /**
     * Authorize production access to Telescope.
     */
    private function authorizeProductionAccess($request): bool
    {
        // Check for specific admin users
        if ($request->user() && in_array($request->user()->email, config('telescope.admin_emails', []))) {
            return true;
        }

        // Check for allowed IP addresses
        $allowedIps = config('telescope.allowed_ips', []);
        if (!empty($allowedIps) && in_array($request->ip(), $allowedIps)) {
            return true;
        }

        // Check for debug token
        $debugToken = config('telescope.debug_token');
        if ($debugToken && $request->header('X-Debug-Token') === $debugToken) {
            return true;
        }

        return false;
    }
}