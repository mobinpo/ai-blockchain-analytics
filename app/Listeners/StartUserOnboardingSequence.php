<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\OnboardingEmailService;
use App\Services\LiveAnalyzerOnboardingService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

final class StartUserOnboardingSequence implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private readonly OnboardingEmailService $onboardingService,
        private readonly LiveAnalyzerOnboardingService $liveAnalyzerService
    ) {}

    public function handle(Registered $event): void
    {
        $user = $event->user;

        Log::info("User registered: {$user->id} - {$user->email}");

        // Check if user has previous live analyzer usage
        $sessionId = session()->getId();
        $cacheKey = "live_analysis_{$sessionId}";
        $previousAnalyses = Cache::get($cacheKey, []);

        if (!empty($previousAnalyses)) {
            // User used live analyzer before registering - use specialized onboarding
            Log::info("Starting live analyzer onboarding for user with previous usage", [
                'user_id' => $user->id,
                'previous_analyses' => count($previousAnalyses)
            ]);
            
            $this->liveAnalyzerService->startLiveAnalyzerOnboarding($user);
        } else {
            // Standard onboarding sequence
            $this->onboardingService->startOnboardingSequence($user);
        }
    }

    public function failed(Registered $event, \Throwable $exception): void
    {
        Log::error("Failed to start onboarding sequence for user {$event->user->id}: {$exception->getMessage()}");
    }
}
