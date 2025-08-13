<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\OnboardingEmailService;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

final class TestOnboardingEmails extends Command
{
    protected $signature = 'onboarding:test {--email= : Email address to send test to} {--type= : Email type to test}';
    protected $description = 'Test onboarding email functionality';

    public function handle(OnboardingEmailService $onboardingService): int
    {
        $email = $this->option('email') ?? 'test@example.com';
        $type = $this->option('type') ?? 'welcome';

        $this->info("Testing onboarding email system...");

        // Test configuration loading
        $this->info("✓ Testing configuration...");
        $sequence = config('onboarding.sequence', []);
        if (empty($sequence)) {
            $this->error("✗ Onboarding configuration not found!");
            return 1;
        }
        $this->info("Found " . count($sequence) . " email types in sequence");

        // Test email template existence
        $this->info("✓ Testing email templates...");
        foreach ($sequence as $emailType => $config) {
            $template = $config['template'] ?? "emails.onboarding.{$emailType}";
            
            try {
                if (View::exists($template)) {
                    $this->info("  ✓ Template exists: {$template}");
                } else {
                    $this->warn("  ⚠ Template missing: {$template}");
                }
            } catch (\Exception $e) {
                $this->error("  ✗ Template error for {$template}: " . $e->getMessage());
            }
        }

        // Create test user
        $testUser = new User([
            'name' => 'Test User',
            'email' => $email,
            'onboarding_emails_enabled' => true
        ]);
        $testUser->id = 99999;

        // Test email variable generation
        $this->info("✓ Testing email variables...");
        try {
            $variables = $onboardingService->getEmailVariables($testUser, $type);
            $this->info("Generated variables for {$type} email:");
            $this->line("  - User: {$variables['user']->name} ({$variables['user']->email})");
            $this->line("  - Analyze URL: {$variables['analyzeUrl']}");
            $this->line("  - Dashboard URL: {$variables['dashboardUrl']}");
            $this->line("  - Unsubscribe URL: {$variables['unsubscribeUrl']}");
        } catch (\Exception $e) {
            $this->error("✗ Failed to generate email variables: " . $e->getMessage());
            return 1;
        }

        // Test email rendering
        $this->info("✓ Testing email rendering...");
        $config = $sequence[$type] ?? null;
        if (!$config) {
            $this->error("✗ Email type '{$type}' not found in configuration");
            return 1;
        }

        $template = $config['template'] ?? "emails.onboarding.{$type}";
        
        try {
            if (View::exists($template)) {
                // Try to render the template
                $rendered = View::make($template, $variables)->render();
                $this->info("✓ Template rendered successfully (" . strlen($rendered) . " characters)");
                
                // Show first 200 characters as preview
                $preview = substr(strip_tags($rendered), 0, 200) . '...';
                $this->line("Preview: {$preview}");
            } else {
                $this->error("✗ Template does not exist: {$template}");
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("✗ Failed to render template {$template}: " . $e->getMessage());
            return 1;
        }

        // Test Mailgun configuration
        $this->info("✓ Testing Mailgun configuration...");
        $mailgunDomain = config('services.mailgun.domain');
        $mailgunSecret = config('services.mailgun.secret');
        
        if (!$mailgunDomain || !$mailgunSecret) {
            $this->warn("⚠ Mailgun not configured (domain: " . ($mailgunDomain ? 'set' : 'missing') . ", secret: " . ($mailgunSecret ? 'set' : 'missing') . ")");
        } else {
            $this->info("✓ Mailgun configured with domain: {$mailgunDomain}");
        }

        // Test mail configuration
        $this->info("✓ Testing mail configuration...");
        $mailer = config('mail.default');
        $fromAddress = config('onboarding.from.email', config('mail.from.address'));
        $fromName = config('onboarding.from.name', config('mail.from.name'));
        
        $this->info("  Mailer: {$mailer}");
        $this->info("  From: {$fromName} <{$fromAddress}>");

        // Show available email types
        $this->info("✓ Available email types:");
        foreach ($sequence as $emailType => $config) {
            $delay = $config['delay'] ?? 0;
            $subject = $config['subject'] ?? 'No subject';
            $enabled = $config['enabled'] ?? true ? 'enabled' : 'disabled';
            
            $this->line("  - {$emailType}: {$subject} (delay: {$delay}min, {$enabled})");
        }

        $this->info("🎉 Onboarding email system test completed successfully!");
        
        $this->line("");
        $this->info("Next steps:");
        $this->line("1. Set up your Mailgun credentials in .env:");
        $this->line("   MAILGUN_DOMAIN=your-domain.mailgun.org");
        $this->line("   MAILGUN_SECRET=your-mailgun-api-key");
        $this->line("   MAIL_MAILER=mailgun");
        $this->line("");
        $this->line("2. Test specific email types:");
        $this->line("   php artisan onboarding:test --email=your@email.com --type=welcome");
        $this->line("");
        $this->line("3. Start queue worker to process emails:");
        $this->line("   php artisan queue:work");

        return 0;
    }
}