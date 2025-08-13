<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\ChainDetectorService;
use App\Services\SmartChainSwitchingService;
use App\Services\EnhancedVerificationBadgeService;
use App\Services\VerificationBadgeService;
use App\Services\OnboardingEmailService;
use App\Providers\SentryServiceProvider;
use App\Listeners\StartUserOnboardingSequence;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Laravel\Telescope\Telescope;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register smart chain services as singletons
        $this->app->singleton(ChainDetectorService::class);
        $this->app->singleton(SmartChainSwitchingService::class);
        
        // Register enhanced verification service as singleton
        $this->app->singleton(EnhancedVerificationBadgeService::class);
        
        // Register verification badge service as singleton
        $this->app->singleton(VerificationBadgeService::class, function ($app) {
            return new VerificationBadgeService(
                config('app.verification_signing_key') ?: config('app.key')
            );
        });
        
        // Register onboarding email service as singleton
        $this->app->singleton(OnboardingEmailService::class);
        
        // Register Sentry service provider for enhanced error tracking
        if ($this->app->bound('sentry')) {
            $this->app->register(SentryServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        
        // Register event listeners only if not in artisan command context
        if (!app()->runningInConsole() || app()->runningUnitTests()) {
            Event::listen(Registered::class, StartUserOnboardingSequence::class);
        }
    }
}
