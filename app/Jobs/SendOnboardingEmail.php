<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\OnboardingEmailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Exception;

final class SendOnboardingEmail implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;
    public int $maxExceptions = 3;
    public int $backoff = 60; // seconds

    public function __construct(
        public User $user,
        public string $emailType,
        public array $config
    ) {
        $this->onQueue(config('onboarding.queue.queue_name', 'default'));
    }

    public function handle(OnboardingEmailService $onboardingService): void
    {
        // Check if user still wants onboarding emails
        if (!$this->user->onboarding_emails_enabled) {
            Log::info("User {$this->user->id} has disabled onboarding emails, skipping {$this->emailType}");
            return;
        }

        // Check if email was already sent
        if ($onboardingService->hasEmailBeenSent($this->user, $this->emailType)) {
            Log::info("Email {$this->emailType} already sent to user {$this->user->id}, skipping");
            return;
        }

        try {
            $variables = $onboardingService->getEmailVariables($this->user, $this->emailType);
            $template = $this->config['template'] ?? "emails.onboarding.{$this->emailType}";
            $subject = $this->config['subject'] ?? "AI Blockchain Analytics - {$this->emailType}";

            // Send the email using Laravel Mail with enhanced Mailgun integration
            Mail::send($template, $variables, function ($message) use ($subject) {
                $message->to($this->user->email, $this->user->name)
                    ->subject($subject)
                    ->from(
                        config('onboarding.from_email', config('mail.from.address')),
                        config('onboarding.from_name', config('mail.from.name'))
                    );

                // Reply-to for support
                $message->replyTo(
                    config('onboarding.content.support_email', config('mail.from.address')),
                    config('onboarding.from_name', config('mail.from.name')) . ' Support'
                );

                // Enhanced Mailgun tracking headers
                if (config('onboarding.mailgun.tracking', true)) {
                    $message->getHeaders()->addTextHeader('X-Mailgun-Track', 'yes');
                }

                if (config('onboarding.mailgun.open_tracking', true)) {
                    $message->getHeaders()->addTextHeader('X-Mailgun-Track-Opens', 'yes');
                }

                if (config('onboarding.mailgun.click_tracking', true)) {
                    $message->getHeaders()->addTextHeader('X-Mailgun-Track-Clicks', 'yes');
                }

                // Campaign and segmentation
                $message->getHeaders()->addTextHeader('X-Mailgun-Campaign-Id', "onboarding-{$this->emailType}-v0.9.0");
                
                // Multiple tags for better analytics
                $tags = array_merge(
                    config('onboarding.mailgun.tags', []),
                    [$this->emailType, 'onboarding', 'automated']
                );
                $message->getHeaders()->addTextHeader('X-Mailgun-Tag', implode(',', $tags));

                // User segmentation for analytics
                $userSegment = $this->getUserSegment();
                if ($userSegment) {
                    $message->getHeaders()->addTextHeader('X-Mailgun-Variables', json_encode([
                        'user_id' => $this->user->id,
                        'email_type' => $this->emailType,
                        'user_segment' => $userSegment,
                        'registration_date' => $this->user->created_at->format('Y-m-d'),
                        'platform_version' => 'v0.9.0'
                    ]));
                }

                // Priority setting
                $priority = $this->config['priority'] ?? 'normal';
                if ($priority === 'high' || $priority === 'critical') {
                    $message->priority(\Swift_Message::PRIORITY_HIGH);
                }

                // Test mode for development
                if (config('onboarding.mailgun.test_mode', false)) {
                    $message->getHeaders()->addTextHeader('X-Mailgun-Drop-Message', 'yes');
                }

                // Delivery time optimization
                if (config('onboarding.delivery.respect_user_timezone', true)) {
                    $optimalTime = $this->calculateOptimalDeliveryTime();
                    if ($optimalTime) {
                        $message->getHeaders()->addTextHeader('X-Mailgun-Deliver-By', $optimalTime);
                    }
                }
            });

            // Mark as sent
            $onboardingService->markEmailAsSent($this->user, $this->emailType);

            Log::info("Successfully sent {$this->emailType} email to user {$this->user->id}");

        } catch (Exception $e) {
            Log::error("Failed to send {$this->emailType} email to user {$this->user->id}: {$e->getMessage()}");
            
            $onboardingService->markEmailAsFailed($this->user, $this->emailType, $e->getMessage());
            
            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    public function failed(Exception $exception): void
    {
        Log::error("Onboarding email {$this->emailType} permanently failed for user {$this->user->id}: {$exception->getMessage()}");
        
        app(OnboardingEmailService::class)->markEmailAsFailed(
            $this->user,
            $this->emailType,
            "Permanently failed after {$this->tries} attempts: {$exception->getMessage()}"
        );
    }

    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(config('onboarding.queue.retry_delay', 30));
    }

    /**
     * Determine user segment for analytics and personalization
     */
    private function getUserSegment(): string
    {
        // Segment based on user activity and registration time
        $daysSinceRegistration = $this->user->created_at->diffInDays(now());
        $hasAnalyzedContracts = $this->user->projects()->where('status', '!=', 'draft')->exists();
        $analysisCount = $this->user->projects()->where('status', '!=', 'draft')->count();

        if ($daysSinceRegistration < 1) {
            return $hasAnalyzedContracts ? 'new_active' : 'new_inactive';
        } elseif ($daysSinceRegistration < 7) {
            return $hasAnalyzedContracts ? 'week1_active' : 'week1_inactive';
        } elseif ($daysSinceRegistration < 30) {
            if ($analysisCount >= 10) return 'power_user';
            if ($analysisCount >= 3) return 'regular_user';
            return $hasAnalyzedContracts ? 'occasional_user' : 'dormant_user';
        }

        if ($analysisCount >= 20) return 'enterprise_user';
        if ($analysisCount >= 5) return 'engaged_user';
        return $hasAnalyzedContracts ? 'returning_user' : 'inactive_user';
    }

    /**
     * Calculate optimal delivery time based on user timezone and preferences
     */
    private function calculateOptimalDeliveryTime(): ?string
    {
        $optimalHours = config('onboarding.delivery.optimal_send_hours', [9, 10, 11, 14, 15, 16]);
        $avoidWeekends = config('onboarding.delivery.avoid_weekends', false);
        
        $now = now();
        $deliveryTime = $now->copy();

        // If current time is not optimal, schedule for next optimal time
        if (!in_array($now->hour, $optimalHours)) {
            // Find next optimal hour
            $nextOptimalHour = null;
            foreach ($optimalHours as $hour) {
                if ($hour > $now->hour) {
                    $nextOptimalHour = $hour;
                    break;
                }
            }
            
            if ($nextOptimalHour) {
                $deliveryTime->hour($nextOptimalHour)->minute(0)->second(0);
            } else {
                // Schedule for first optimal hour tomorrow
                $deliveryTime->addDay()->hour($optimalHours[0])->minute(0)->second(0);
            }
        }

        // Avoid weekends if configured
        if ($avoidWeekends && $deliveryTime->isWeekend()) {
            $deliveryTime->next(\Carbon\Carbon::MONDAY)->hour($optimalHours[0])->minute(0)->second(0);
        }

        // Don't schedule too far in the future (max 24 hours)
        if ($deliveryTime->diffInHours($now) > 24) {
            return null;
        }

        return $deliveryTime->toRfc3339String();
    }
}
