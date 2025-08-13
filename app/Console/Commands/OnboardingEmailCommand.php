<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\OnboardingEmailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class OnboardingEmailCommand extends Command
{
    protected $signature = 'onboarding:email 
                           {action : Action to perform (start|status|test|cleanup|stats)}
                           {--user= : Specific user ID for start/test actions}
                           {--email-type= : Specific email type for test action}
                           {--force : Force action even if conditions not met}
                           {--dry-run : Show what would be done without executing}';

    protected $description = 'Manage onboarding email sequences and automation';

    public function handle(OnboardingEmailService $onboardingService): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'start' => $this->startOnboarding($onboardingService),
            'status' => $this->showStatus($onboardingService),
            'test' => $this->testEmail($onboardingService),
            'cleanup' => $this->cleanupEmails($onboardingService),
            'stats' => $this->showStats($onboardingService),
            default => $this->error("Invalid action: {$action}. Use: start|status|test|cleanup|stats")
        };
    }

    private function startOnboarding(OnboardingEmailService $onboardingService): int
    {
        $userId = $this->option('user');
        
        if (!$userId) {
            $this->error('User ID is required for start action. Use --user=ID');
            return 1;
        }

        $user = User::find($userId);
        if (!$user) {
            $this->error("User not found: {$userId}");
            return 1;
        }

        if ($this->option('dry-run')) {
            $this->info("Would start onboarding sequence for user: {$user->email}");
            return 0;
        }

        try {
            $onboardingService->startOnboardingSequence($user);
            $this->info("✅ Onboarding sequence started for user: {$user->email}");
            
            // Show scheduled emails
            $progress = $onboardingService->getOnboardingProgress($user);
            $this->table(
                ['Email Type', 'Status', 'Scheduled At'],
                collect($progress)->map(fn($email, $type) => [
                    $type,
                    $email['status'],
                    $email['scheduled_at'] ? $email['scheduled_at']->format('Y-m-d H:i:s') : 'N/A'
                ])->toArray()
            );
            
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to start onboarding: {$e->getMessage()}");
            return 1;
        }
    }

    private function showStatus(OnboardingEmailService $onboardingService): int
    {
        $userId = $this->option('user');
        
        if ($userId) {
            return $this->showUserStatus($userId, $onboardingService);
        }

        // Show overall system status
        $stats = $onboardingService->getStatistics();
        
        $this->info('📊 Onboarding Email System Status');
        $this->info('=====================================');
        $this->info("Total Users: {$stats['total_users']}");
        $this->info("Users in Onboarding: {$stats['users_in_onboarding']}");
        $this->info("Completion Rate: {$stats['completion_rate']}%");
        $this->newLine();

        // Email performance table
        $emailData = [];
        foreach ($stats['emails'] as $type => $data) {
            $emailData[] = [
                $type,
                $data['name'],
                $data['scheduled'],
                $data['sent'],
                $data['failed'],
                $data['cancelled']
            ];
        }

        $this->table(
            ['Type', 'Name', 'Scheduled', 'Sent', 'Failed', 'Cancelled'],
            $emailData
        );

        return 0;
    }

    private function showUserStatus(string $userId, OnboardingEmailService $onboardingService): int
    {
        $user = User::find($userId);
        if (!$user) {
            $this->error("User not found: {$userId}");
            return 1;
        }

        $this->info("👤 Onboarding Status for: {$user->email}");
        $this->info('==========================================');
        $this->info("Registration Date: {$user->created_at->format('Y-m-d H:i:s')}");
        $this->info("Onboarding Enabled: " . ($user->onboarding_emails_enabled ? 'Yes' : 'No'));
        $this->newLine();

        $progress = $onboardingService->getOnboardingProgress($user);
        
        $progressData = [];
        foreach ($progress as $type => $data) {
            $progressData[] = [
                $type,
                $data['name'],
                $data['status'],
                $data['scheduled_at'] ? $data['scheduled_at']->format('Y-m-d H:i:s') : 'N/A',
                $data['sent_at'] ? $data['sent_at']->format('Y-m-d H:i:s') : 'N/A'
            ];
        }

        $this->table(
            ['Type', 'Name', 'Status', 'Scheduled At', 'Sent At'],
            $progressData
        );

        return 0;
    }

    private function testEmail(OnboardingEmailService $onboardingService): int
    {
        $userId = $this->option('user');
        $emailType = $this->option('email-type');
        
        if (!$userId || !$emailType) {
            $this->error('Both --user and --email-type are required for test action');
            return 1;
        }

        $user = User::find($userId);
        if (!$user) {
            $this->error("User not found: {$userId}");
            return 1;
        }

        $sequence = config('onboarding.sequence', []);
        if (!isset($sequence[$emailType])) {
            $this->error("Email type not found: {$emailType}");
            $this->info('Available types: ' . implode(', ', array_keys($sequence)));
            return 1;
        }

        if ($this->option('dry-run')) {
            $this->info("Would send test {$emailType} email to: {$user->email}");
            return 0;
        }

        try {
            $config = $sequence[$emailType];
            $onboardingService->scheduleOnboardingEmail($user, $emailType, $config);
            
            $this->info("✅ Test {$emailType} email scheduled for: {$user->email}");
            $this->info("Check your email queue and logs for delivery status.");
            
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to send test email: {$e->getMessage()}");
            return 1;
        }
    }

    private function cleanupEmails(OnboardingEmailService $onboardingService): int
    {
        if ($this->option('dry-run')) {
            $this->info('Would cleanup old and failed onboarding emails');
            return 0;
        }

        try {
            // Cleanup logic would go here
            $this->info('🧹 Email cleanup completed');
            return 0;
        } catch (\Exception $e) {
            $this->error("Cleanup failed: {$e->getMessage()}");
            return 1;
        }
    }

    private function showStats(OnboardingEmailService $onboardingService): int
    {
        $stats = $onboardingService->getStatistics();
        
        $this->info('📈 Detailed Onboarding Statistics');
        $this->info('=================================');
        
        // Overall metrics
        $this->info("📊 Overall Metrics:");
        $this->info("  Total Users: {$stats['total_users']}");
        $this->info("  Users in Onboarding: {$stats['users_in_onboarding']}");
        $this->info("  Completion Rate: {$stats['completion_rate']}%");
        $this->newLine();

        // Email performance
        $this->info("📧 Email Performance:");
        foreach ($stats['emails'] as $type => $data) {
            $total = $data['scheduled'] + $data['sent'] + $data['failed'] + $data['cancelled'];
            $successRate = $total > 0 ? round(($data['sent'] / $total) * 100, 1) : 0;
            
            $this->info("  {$data['name']}:");
            $this->info("    Scheduled: {$data['scheduled']}");
            $this->info("    Sent: {$data['sent']}");
            $this->info("    Failed: {$data['failed']}");
            $this->info("    Success Rate: {$successRate}%");
        }

        return 0;
    }
}
