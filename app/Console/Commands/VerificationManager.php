<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\VerificationBadgeService;
use App\Models\VerificationBadge;
use Illuminate\Console\Command;

final class VerificationManager extends Command
{
    protected $signature = 'verification:manage
                           {action : Action to perform (list, revoke, stats, cleanup)}
                           {--contract= : Contract address for specific actions}
                           {--reason= : Reason for revocation}
                           {--days=30 : Number of days for cleanup}
                           {--force : Force action without confirmation}';

    protected $description = 'Manage verification badges (list, revoke, stats, cleanup)';

    public function handle(): int
    {
        $action = $this->argument('action');

        return match($action) {
            'list' => $this->listVerifications(),
            'revoke' => $this->revokeVerification(),
            'stats' => $this->displayStats(),
            'cleanup' => $this->cleanupExpired(),
            default => $this->invalidAction($action)
        };
    }

    private function listVerifications(): int
    {
        $this->info('📋 VERIFIED CONTRACTS LIST');
        $this->info('═══════════════════════════════════════');
        $this->newLine();

        $contractFilter = $this->option('contract');
        
        $query = VerificationBadge::verified()->active();
        
        if ($contractFilter) {
            $query->forContract($contractFilter);
        }

        $verifications = $query->orderBy('verified_at', 'desc')->get();

        if ($verifications->isEmpty()) {
            $this->warn('No verified contracts found.');
            return Command::SUCCESS;
        }

        $tableData = [];
        foreach ($verifications as $verification) {
            $tableData[] = [
                $verification->truncated_address,
                $verification->project_name ?: 'N/A',
                $verification->verification_age,
                $verification->verification_method,
                $verification->isActive() ? '✅ Active' : '❌ Inactive'
            ];
        }

        $this->table([
            'Contract Address',
            'Project Name', 
            'Verified',
            'Method',
            'Status'
        ], $tableData);

        $this->newLine();
        $this->info("Total: {$verifications->count()} verified contracts");

        return Command::SUCCESS;
    }

    private function revokeVerification(): int
    {
        $contractAddress = $this->option('contract');
        
        if (!$contractAddress) {
            $this->error('❌ Contract address is required for revocation');
            $this->line('Usage: verification:manage revoke --contract=0x123... --reason="Reason"');
            return Command::FAILURE;
        }

        $verification = VerificationBadge::findActiveForContract($contractAddress);
        
        if (!$verification) {
            $this->error("❌ No active verification found for contract: {$contractAddress}");
            return Command::FAILURE;
        }

        $this->info('🔍 Verification Found:');
        $this->table(['Property', 'Value'], [
            ['Contract', $verification->contract_address],
            ['Project', $verification->project_name ?: 'N/A'],
            ['Verified', $verification->verification_age],
            ['Method', $verification->verification_method],
            ['User ID', $verification->user_id]
        ]);

        $reason = $this->option('reason') ?: 'Manual revocation';

        if (!$this->option('force')) {
            if (!$this->confirm('Are you sure you want to revoke this verification?')) {
                $this->info('Revocation cancelled.');
                return Command::SUCCESS;
            }
        }

        if ($verification->revoke($reason)) {
            $this->info('✅ Verification revoked successfully');
            $this->line("Reason: {$reason}");
        } else {
            $this->error('❌ Failed to revoke verification');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function displayStats(): int
    {
        $this->info('📊 VERIFICATION STATISTICS');
        $this->info('══════════════════════════════════');
        $this->newLine();

        $totalVerified = VerificationBadge::verified()->count();
        $activeVerified = VerificationBadge::verified()->active()->count();
        $revokedCount = VerificationBadge::verified()->whereNotNull('revoked_at')->count();
        $expiredCount = VerificationBadge::verified()->where('expires_at', '<', now())->count();

        // Time-based stats
        $todayCount = VerificationBadge::verified()
            ->whereDate('verified_at', today())
            ->count();
            
        $weekCount = VerificationBadge::verified()
            ->where('verified_at', '>=', now()->subWeek())
            ->count();
            
        $monthCount = VerificationBadge::verified()
            ->where('verified_at', '>=', now()->subMonth())
            ->count();

        $this->table(['Metric', 'Count'], [
            ['Total Verified', $totalVerified],
            ['Currently Active', $activeVerified],
            ['Revoked', $revokedCount],
            ['Expired', $expiredCount],
            ['Verified Today', $todayCount],
            ['Verified This Week', $weekCount],
            ['Verified This Month', $monthCount]
        ]);

        // Method breakdown
        $this->newLine();
        $this->line('📈 Verification Methods:');
        
        $methods = VerificationBadge::verified()
            ->selectRaw('verification_method, COUNT(*) as count')
            ->groupBy('verification_method')
            ->get();

        foreach ($methods as $method) {
            $this->line("   {$method->verification_method}: {$method->count}");
        }

        // Recent activity
        $this->newLine();
        $this->line('🕒 Recent Verifications (Last 5):');
        
        $recent = VerificationBadge::verified()
            ->latest('verified_at')
            ->limit(5)
            ->get();

        foreach ($recent as $verification) {
            $this->line("   {$verification->truncated_address} - {$verification->verification_age}");
        }

        return Command::SUCCESS;
    }

    private function cleanupExpired(): int
    {
        $days = (int) $this->option('days');
        $cutoffDate = now()->subDays($days);

        $this->info("🧹 CLEANUP EXPIRED VERIFICATIONS");
        $this->info("Removing verifications older than {$days} days");
        $this->info('═══════════════════════════════════════════');
        $this->newLine();

        // Find expired verifications
        $expiredQuery = VerificationBadge::where(function($query) use ($cutoffDate) {
            $query->where('expires_at', '<', now())
                  ->orWhere('created_at', '<', $cutoffDate);
        });

        $expiredCount = $expiredQuery->count();

        if ($expiredCount === 0) {
            $this->info('✅ No expired verifications found to clean up.');
            return Command::SUCCESS;
        }

        $this->warn("Found {$expiredCount} expired verification(s) to remove.");

        if (!$this->option('force')) {
            if (!$this->confirm('Proceed with cleanup?')) {
                $this->info('Cleanup cancelled.');
                return Command::SUCCESS;
            }
        }

        $deleted = $expiredQuery->delete();

        $this->info("✅ Cleaned up {$deleted} expired verification(s)");
        
        return Command::SUCCESS;
    }

    private function invalidAction(string $action): int
    {
        $this->error("❌ Invalid action: {$action}");
        $this->newLine();
        $this->line('Available actions:');
        $this->line('  list    - List all verified contracts');
        $this->line('  revoke  - Revoke a specific verification');
        $this->line('  stats   - Display verification statistics');
        $this->line('  cleanup - Clean up expired verifications');
        $this->newLine();
        $this->line('Examples:');
        $this->line('  verification:manage list');
        $this->line('  verification:manage revoke --contract=0x123... --reason="Security issue"');
        $this->line('  verification:manage stats');
        $this->line('  verification:manage cleanup --days=30 --force');

        return Command::FAILURE;
    }
}