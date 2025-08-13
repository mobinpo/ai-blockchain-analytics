<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\OnboardingEmailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

final class TestOnboardingEmail extends Command
{
    protected $signature = 'onboarding:test {email} {--type=welcome} {--send-immediately}';
    protected $description = 'Test onboarding email flow for a specific email address';

    public function handle(OnboardingEmailService $onboardingService): int
    {
        $email = $this->argument('email');
        $emailType = $this->option('type');
        $sendImmediately = $this->option('send-immediately');

        // Find or create a test user
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'onboarding_emails_enabled' => true,
            ]
        );

        $this->info("Testing onboarding email for user: {$user->name} ({$user->email})");

        if ($sendImmediately) {
            // Send email immediately for testing
            $this->info("Sending {$emailType} email immediately...");
            
            try {
                $variables = $onboardingService->getEmailVariables($user, $emailType);
                $config = config("onboarding.sequence.{$emailType}");
                
                if (!$config) {
                    $this->error("Email type '{$emailType}' not found in configuration");
                    return 1;
                }

                $template = $config['template'] ?? "emails.onboarding.{$emailType}";
                $subject = $config['subject'] ?? "AI Blockchain Analytics - {$emailType}";

                // Check if we're in a proper email environment
                if (config('mail.default') === 'array') {
                    $this->warn('Mail driver is set to "array" - email will not be actually sent');
                }

                Mail::send($template, $variables, function ($message) use ($subject, $user) {
                    $message->to($user->email, $user->name)
                        ->subject($subject)
                        ->from(
                            config('onboarding.from.email', config('mail.from.address')),
                            config('onboarding.from.name', config('mail.from.name'))
                        );

                    if ($replyTo = config('onboarding.reply_to')) {
                        $message->replyTo($replyTo);
                    }

                    // Add Mailgun tracking headers
                    if (config('onboarding.analytics.track_opens')) {
                        $message->getHeaders()->addTextHeader('X-Mailgun-Track', 'yes');
                        $message->getHeaders()->addTextHeader('X-Mailgun-Track-Opens', 'yes');
                    }

                    if (config('onboarding.analytics.track_clicks')) {
                        $message->getHeaders()->addTextHeader('X-Mailgun-Track-Clicks', 'yes');
                    }

                    // Add campaign tracking
                    $message->getHeaders()->addTextHeader('X-Mailgun-Campaign-Id', "onboarding-{$emailType}");
                    $message->getHeaders()->addTextHeader('X-Mailgun-Tag', ['onboarding', $emailType, 'test']);
                });

                $this->info("✅ Email sent successfully!");
                
                // Display email variables for debugging
                $this->line("\n📧 Email Variables:");
                $this->table(
                    ['Variable', 'Value'],
                    collect($variables)->except(['user'])->map(function ($value, $key) {
                        return [$key, is_string($value) ? $value : json_encode($value)];
                    })->toArray()
                );

            } catch (\Exception $e) {
                $this->error("❌ Failed to send email: {$e->getMessage()}");
                return 1;
            }
        } else {
            // Test the full onboarding sequence
            $this->info("Starting full onboarding sequence...");
            
            $onboardingService->startOnboardingSequence($user);
            
            $progress = $onboardingService->getOnboardingProgress($user);
            
            $this->line("\n📋 Onboarding Sequence Progress:");
            $headers = ['Email Type', 'Status', 'Scheduled At', 'Delay (minutes)'];
            $rows = [];
            
            foreach ($progress as $type => $info) {
                $rows[] = [
                    $type,
                    $info['status'],
                    $info['scheduled_at'] ? $info['scheduled_at']->format('Y-m-d H:i:s') : 'Not scheduled',
                    $info['delay_minutes']
                ];
            }
            
            $this->table($headers, $rows);
            
            $this->info("✅ Onboarding sequence initiated successfully!");
        }

        // Show configuration summary
        $this->line("\n⚙️  Configuration Summary:");
        $this->line("Onboarding Enabled: " . (config('onboarding.enabled') ? '✅ Yes' : '❌ No'));
        $this->line("Mail Driver: " . config('mail.default'));
        $this->line("From Email: " . config('onboarding.from.email'));
        $this->line("Webhook Tracking: " . (config('onboarding.webhooks.enabled') ? '✅ Enabled' : '❌ Disabled'));

        return 0;
    }
}