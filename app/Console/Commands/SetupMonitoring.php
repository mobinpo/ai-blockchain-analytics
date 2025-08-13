<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Telescope\TelescopeServiceProvider;

final class SetupMonitoring extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'monitoring:setup 
                            {--sentry : Setup Sentry configuration}
                            {--telescope : Setup Telescope configuration}
                            {--all : Setup all monitoring tools}';

    /**
     * The console command description.
     */
    protected $description = 'Setup monitoring tools (Sentry and Telescope) for AI Blockchain Analytics';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔧 Setting up monitoring tools for AI Blockchain Analytics...');

        $setupSentry = $this->option('sentry') || $this->option('all');
        $setupTelescope = $this->option('telescope') || $this->option('all');

        if (!$setupSentry && !$setupTelescope) {
            $setupSentry = $this->confirm('Setup Sentry error tracking?', true);
            $setupTelescope = $this->confirm('Setup Laravel Telescope debugging?', true);
        }

        if ($setupSentry) {
            $this->setupSentry();
        }

        if ($setupTelescope) {
            $this->setupTelescope();
        }

        $this->displaySummary($setupSentry, $setupTelescope);

        return Command::SUCCESS;
    }

    /**
     * Setup Sentry error tracking.
     */
    protected function setupSentry(): void
    {
        $this->info('📊 Setting up Sentry error tracking...');

        // Check if Sentry is properly configured
        $sentryDsn = config('sentry.dsn');
        
        if (empty($sentryDsn) || str_contains($sentryDsn, 'your-sentry-dsn')) {
            $this->warn('⚠️  Sentry DSN not configured!');
            $this->line('Please set SENTRY_LARAVEL_DSN in your environment variables.');
            $this->line('Get your DSN from: https://sentry.io/settings/projects/');
            return;
        }

        // Test Sentry connection
        try {
            \Sentry\captureMessage('Sentry setup test from AI Blockchain Analytics');
            $this->info('✅ Sentry connection successful!');
        } catch (\Exception $e) {
            $this->error('❌ Sentry connection failed: ' . $e->getMessage());
            return;
        }

        // Display Sentry configuration
        $this->table(['Setting', 'Value'], [
            ['DSN', $this->maskSensitive($sentryDsn)],
            ['Environment', config('sentry.environment')],
            ['Traces Sample Rate', config('sentry.traces_sample_rate')],
            ['Profiles Sample Rate', config('sentry.profiles_sample_rate')],
            ['Track Blockchain Ops', config('sentry.ai_blockchain.track_blockchain_operations') ? 'Yes' : 'No'],
            ['Track Sentiment Analysis', config('sentry.ai_blockchain.track_sentiment_analysis') ? 'Yes' : 'No'],
            ['Track Crawler Ops', config('sentry.ai_blockchain.track_crawler_operations') ? 'Yes' : 'No'],
        ]);

        $this->info('📈 Sentry setup completed!');
    }

    /**
     * Setup Laravel Telescope debugging.
     */
    protected function setupTelescope(): void
    {
        $this->info('🔭 Setting up Laravel Telescope...');

        // Check if Telescope is enabled
        if (!config('telescope.enabled')) {
            $this->warn('⚠️  Telescope is disabled in configuration.');
            return;
        }

        // Install Telescope assets if needed
        if (!file_exists(public_path('vendor/telescope'))) {
            $this->info('📦 Publishing Telescope assets...');
            $this->call('telescope:install');
        }

        // Run Telescope migrations
        $this->info('🗄️  Running Telescope migrations...');
        try {
            $this->call('migrate', ['--path' => 'vendor/laravel/telescope/database/migrations']);
            $this->info('✅ Telescope migrations completed!');
        } catch (\Exception $e) {
            $this->error('❌ Telescope migration failed: ' . $e->getMessage());
            return;
        }

        // Check production restrictions
        $productionEnabled = config('telescope.ai_blockchain.production_enabled');
        $allowedIps = config('telescope.ai_blockchain.production_restrictions.allowed_ips');
        $allowedUsers = config('telescope.ai_blockchain.production_restrictions.allowed_users');

        $this->table(['Setting', 'Value'], [
            ['Enabled', config('telescope.enabled') ? 'Yes' : 'No'],
            ['Path', '/' . config('telescope.path')],
            ['Driver', config('telescope.driver')],
            ['Production Enabled', $productionEnabled ? 'Yes' : 'No'],
            ['Allowed IPs', empty($allowedIps) ? 'None' : implode(', ', $allowedIps)],
            ['Allowed Users', empty($allowedUsers) ? 'None' : implode(', ', $allowedUsers)],
            ['Sampling Rate', config('telescope.ai_blockchain.performance.sampling_rate')],
            ['Retention Hours', config('telescope.ai_blockchain.retention.hours')],
        ]);

        // Show security warnings for production
        if (app()->environment('production')) {
            if ($productionEnabled) {
                $this->warn('⚠️  Telescope is enabled in PRODUCTION!');
                $this->line('Security recommendations:');
                $this->line('  • Restrict access by IP address');
                $this->line('  • Limit access to specific users');
                $this->line('  • Enable auto-disable timer');
                $this->line('  • Use low sampling rate');
            } else {
                $this->info('🔒 Telescope is safely disabled in production.');
            }
        }

        $this->info('🔭 Telescope setup completed!');
    }

    /**
     * Display setup summary.
     */
    protected function displaySummary(bool $sentrySetup, bool $telescopeSetup): void
    {
        $this->newLine();
        $this->info('📋 Monitoring Setup Summary');
        $this->line('================================');

        if ($sentrySetup) {
            $this->line('✅ Sentry Error Tracking');
            $this->line('   • Real-time error monitoring');
            $this->line('   • Performance tracking');
            $this->line('   • Custom AI/Blockchain operation tracking');
            $this->line('   • Dashboard: https://sentry.io/');
        }

        if ($telescopeSetup) {
            $telescopePath = config('telescope.path', 'telescope');
            $this->line('✅ Laravel Telescope');
            $this->line('   • Request debugging');
            $this->line('   • Query performance monitoring');
            $this->line('   • Job and queue monitoring');
            $this->line("   • Dashboard: //$telescopePath");
        }

        $this->newLine();
        $this->info('🚀 Monitoring is now active!');
        
        if (app()->environment('production')) {
            $this->warn('🔐 Remember to secure your monitoring tools in production!');
        }

        // Show next steps
        $this->newLine();
        $this->info('📚 Next Steps:');
        
        if ($sentrySetup) {
            $this->line('• Configure Sentry alerts and integrations');
            $this->line('• Set up error notification channels');
            $this->line('• Review and adjust sampling rates');
        }
        
        if ($telescopeSetup) {
            $this->line('• Configure Telescope watchers for your needs');
            $this->line('• Set up data retention policies');
            $this->line('• Secure access in production environments');
        }
    }

    /**
     * Mask sensitive information for display.
     */
    protected function maskSensitive(string $value): string
    {
        if (strlen($value) <= 10) {
            return str_repeat('*', strlen($value));
        }
        
        return substr($value, 0, 10) . str_repeat('*', strlen($value) - 20) . substr($value, -10);
    }
}