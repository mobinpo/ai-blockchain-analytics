<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\OnboardingEmailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

final class TestOnboardingFlow extends Command
{
    protected $signature = 'onboarding:test 
                          {--email= : Email address to test with}
                          {--dry-run : Show what would be sent without actually sending}';

    protected $description = 'Test the onboarding email flow';

    public function handle(OnboardingEmailService $onboardingService): int
    {
        $email = $this->option('email') ?: 'test@example.com';
        $isDryRun = $this->option('dry-run');

        $this->info("Testing onboarding flow for: {$email}");

        if ($isDryRun) {
            $this->warn("DRY RUN MODE - No emails will be sent");
            Mail::fake();
        }

        // Create or find test user
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'onboarding_emails_enabled' => true,
            ]
        );

        $this->info("User found/created: {$user->name} ({$user->id})");

        // Test onboarding sequence configuration
        $sequence = config('onboarding.sequence', []);
        if (empty($sequence)) {
            $this->error('No onboarding sequence configured!');
            return Command::FAILURE;
        }

        $this->info('Configured email sequence:');
        foreach ($sequence as $type => $config) {
            $delay = $config['delay'] ?? 0;
            $enabled = $config['enabled'] ?? false;
            $status = $enabled ? '✅' : '❌';
            
            $this->line("  {$status} {$type}: {$config['subject']} (delay: {$delay}m)");
        }

        // Check Mailgun configuration
        $this->newLine();
        $this->checkMailConfiguration();

        // Start onboarding sequence
        $this->newLine();
        $this->info('Starting onboarding sequence...');
        
        try {
            $onboardingService->startOnboardingSequence($user);
            $this->info('✅ Onboarding sequence started successfully!');
            
            if ($isDryRun) {
                $this->info('📧 Emails that would be sent:');
                $this->showScheduledEmails($user);
            } else {
                $this->info('📧 Check your queue and email logs for delivery status');
                $this->info('Run: php artisan queue:work to process emails');
            }
            
        } catch (\Exception $e) {
            $this->error("Failed to start onboarding sequence: {$e->getMessage()}");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function checkMailConfiguration(): void
    {
        $this->info('Mail Configuration:');
        
        $mailer = config('mail.default');
        $this->line("  Mailer: {$mailer}");
        
        if ($mailer === 'mailgun') {
            $domain = config('services.mailgun.domain');
            $secret = config('services.mailgun.secret');
            
            $this->line("  Domain: " . ($domain ? "✅ {$domain}" : "❌ Not configured"));
            $this->line("  Secret: " . ($secret ? "✅ Configured" : "❌ Not configured"));
        }
        
        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name');
        
        $this->line("  From: {$fromName} <{$fromAddress}>");
        
        // Check onboarding specific settings
        $onboardingFrom = config('onboarding.from.email');
        if ($onboardingFrom) {
            $this->line("  Onboarding From: {$onboardingFrom}");
        }
    }

    private function showScheduledEmails(User $user): void
    {
        $sequence = config('onboarding.sequence', []);
        
        foreach ($sequence as $type => $config) {
            if (!($config['enabled'] ?? true)) {
                continue;
            }
            
            $delay = $config['delay'] ?? 0;
            $sendAt = now()->addMinutes($delay);
            
            $this->line("  📬 {$config['subject']}");
            $this->line("     Type: {$type}");
            $this->line("     Template: {$config['template']}");
            $this->line("     Send at: {$sendAt->format('Y-m-d H:i:s')}");
            $this->newLine();
        }
    }
}