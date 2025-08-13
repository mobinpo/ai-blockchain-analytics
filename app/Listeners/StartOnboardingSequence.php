<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Jobs\ProcessOnboardingSequenceJob;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

final class StartOnboardingSequence implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(Registered $event): void
    {
        $user = $event->user;
        
        // Skip onboarding for admin users or test accounts
        if ($this->shouldSkipOnboarding($user)) {
            Log::info('Skipping onboarding for user', [
                'user_id' => $user->id,
                'email' => $user->email,
                'reason' => 'admin_or_test_account'
            ]);
            return;
        }

        // Dispatch onboarding sequence job with a 5-minute delay
        // to allow user to complete email verification first
        ProcessOnboardingSequenceJob::dispatch($user->id)
            ->delay(now()->addMinutes(5));

        Log::info('Onboarding sequence triggered for new user', [
            'user_id' => $user->id,
            'email' => $user->email
        ]);
    }

    private function shouldSkipOnboarding($user): bool
    {
        // Skip for admin accounts
        if (str_contains($user->email, 'admin@') || str_contains($user->email, 'test@')) {
            return true;
        }

        // Skip for demo/system accounts
        if (in_array($user->email, [
            'demo@blockchain-analytics.com',
            'live-analysis@blockchain-analytics.com',
            'system@blockchain-analytics.com'
        ])) {
            return true;
        }

        return false;
    }
}