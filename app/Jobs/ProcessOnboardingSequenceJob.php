<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\OnboardingEmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class ProcessOnboardingSequenceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        private readonly int $userId
    ) {
        $this->onQueue('emails');
    }

    public function handle(OnboardingEmailService $onboardingService): void
    {
        try {
            $user = User::find($this->userId);
            
            if (!$user) {
                Log::warning('User not found for onboarding sequence', [
                    'user_id' => $this->userId
                ]);
                return;
            }

            $onboardingService->scheduleOnboardingSequence($user);

            Log::info('Onboarding sequence scheduled successfully', [
                'user_id' => $user->id,
                'user_email' => $user->email
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process onboarding sequence', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Onboarding sequence job failed permanently', [
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);
    }
}