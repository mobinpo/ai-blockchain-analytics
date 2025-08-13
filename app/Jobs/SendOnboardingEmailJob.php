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

final class SendOnboardingEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $maxExceptions = 2;

    public function __construct(
        private readonly int $userId,
        private readonly string $emailType,
        private readonly array $additionalData = []
    ) {
        $this->onQueue('emails');
    }

    public function handle(OnboardingEmailService $onboardingService): void
    {
        try {
            $user = User::find($this->userId);
            
            if (!$user) {
                Log::warning('User not found for onboarding email', [
                    'user_id' => $this->userId,
                    'email_type' => $this->emailType
                ]);
                return;
            }

            // Check if user is still eligible for onboarding emails
            if (!$onboardingService->isEligibleForEmail($user, $this->emailType)) {
                Log::info('User not eligible for onboarding email', [
                    'user_id' => $user->id,
                    'email_type' => $this->emailType,
                    'reason' => 'eligibility_check_failed'
                ]);
                return;
            }

            $onboardingService->sendEmail($user, $this->emailType, $this->additionalData);

            Log::info('Onboarding email sent successfully', [
                'user_id' => $user->id,
                'email_type' => $this->emailType,
                'user_email' => $user->email
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send onboarding email', [
                'user_id' => $this->userId,
                'email_type' => $this->emailType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Onboarding email job failed permanently', [
            'user_id' => $this->userId,
            'email_type' => $this->emailType,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);
    }

    public function retryUntil(): \DateTime
    {
        return now()->addHours(6);
    }
}