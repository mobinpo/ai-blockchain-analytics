<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Telescope\Telescope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TelescopeManageCommand extends Command
{
    protected $signature = 'telescope:manage 
                           {action : The action to perform (enable|disable|status|clean|stats)}
                           {--hours=24 : Hours to keep data when cleaning}
                           {--force : Force action in production}';

    protected $description = 'Manage Laravel Telescope with production-safe operations';

    public function handle(): int
    {
        $action = $this->argument('action');
        
        // Production safety check
        if (app()->isProduction() && !$this->option('force')) {
            if (!in_array($action, ['status', 'clean', 'stats'])) {
                $this->error('   Production environment detected. Use --force to enable/disable Telescope in production.');
                $this->info('=¡ Safe actions: status, clean, stats');
                return self::FAILURE;
            }
        }

        return match ($action) {
            'enable' => $this->enableTelescope(),
            'disable' => $this->disableTelescope(),
            'status' => $this->showStatus(),
            'clean' => $this->cleanData(),
            'stats' => $this->showStats(),
            default => $this->showHelp(),
        };
    }

    private function enableTelescope(): int
    {
        if (app()->isProduction()) {
            $this->warn('   Enabling Telescope in PRODUCTION environment!');
            $this->warn('= Access will be restricted to authorized users only.');
            
            if (!$this->confirm('Are you sure you want to enable Telescope in production?')) {
                $this->info('Operation cancelled.');
                return self::SUCCESS;
            }
        }

        // Enable via cache for runtime
        Cache::put('telescope.enabled', true, 3600); // 1 hour
        
        $this->info(' Telescope enabled' . (app()->isProduction() ? ' in production with restrictions' : ''));
        $this->info('= Access: ' . url('/telescope'));
        
        if (app()->isProduction()) {
            $this->warn('ð Auto-disable in 1 hour for security');
            $this->info('=¡ Set TELESCOPE_FORCE_ENABLE=true in .env for persistent access');
        }

        Log::info('Telescope enabled via artisan command', [
            'environment' => app()->environment(),
            'user' => $this->option('force') ? 'forced' : 'interactive'
        ]);

        return self::SUCCESS;
    }

    private function disableTelescope(): int
    {
        Cache::forget('telescope.enabled');
        
        $this->info('= Telescope disabled');
        
        Log::info('Telescope disabled via artisan command', [
            'environment' => app()->environment()
        ]);

        return self::SUCCESS;
    }

    private function showStatus(): int
    {
        $configEnabled = config('telescope.enabled');
        $cacheEnabled = Cache::get('telescope.enabled', false);
        $forceEnabled = env('TELESCOPE_FORCE_ENABLE', false);
        
        $this->info('=Ê Telescope Status');
        $this->line('==================');
        
        $this->line('Environment: ' . app()->environment());
        $this->line('Config Enabled: ' . ($configEnabled ? ' Yes' : 'L No'));
        $this->line('Cache Enabled: ' . ($cacheEnabled ? ' Yes (temporary)' : 'L No'));
        $this->line('Force Enabled: ' . ($forceEnabled ? ' Yes' : 'L No'));
        
        $actuallyEnabled = $configEnabled || $cacheEnabled || $forceEnabled;
        $this->line('Actually Enabled: ' . ($actuallyEnabled ? ' Yes' : 'L No'));
        
        if ($actuallyEnabled) {
            $this->line('Access URL: ' . url('/telescope'));
            
            if (app()->isProduction()) {
                $this->warn('   Running in production - access is restricted');
                $allowedEmails = env('TELESCOPE_ALLOWED_EMAILS', '');
                if ($allowedEmails) {
                    $this->line('Allowed emails: ' . $allowedEmails);
                }
            }
        }

        return self::SUCCESS;
    }

    private function cleanData(): int
    {
        $hours = (int) $this->option('hours');
        
        $this->info(">ù Cleaning Telescope data older than {$hours} hours...");
        
        try {
            $this->call('telescope:prune', ['--hours' => $hours]);
            $this->info(' Telescope data cleaned successfully');
        } catch (\Exception $e) {
            $this->error('L Failed to clean data: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function showStats(): int
    {
        $this->info('=È Telescope Statistics');
        $this->line('=====================');
        
        try {
            // Get entry counts by type
            $repository = app(\Laravel\Telescope\Contracts\EntriesRepository::class);
            
            if (method_exists($repository, 'countByType')) {
                $counts = $repository->countByType();
                
                foreach ($counts as $type => $count) {
                    $this->line(ucfirst($type) . ': ' . number_format($count));
                }
            } else {
                $this->info('=Ê Statistics not available with current storage driver');
            }
            
            // Show disk usage if database driver
            if (config('telescope.driver') === 'database') {
                $this->line('');
                $this->info('=¾ Database Usage:');
                $this->call('db:show', ['--counts' => true]);
            }
            
        } catch (\Exception $e) {
            $this->error('L Failed to get statistics: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function showHelp(): int
    {
        $this->error('L Invalid action. Available actions:');
        $this->line('');
        $this->line('=Ë Available Commands:');
        $this->line('  enable   - Enable Telescope (restricted in production)');
        $this->line('  disable  - Disable Telescope');
        $this->line('  status   - Show current status');
        $this->line('  clean    - Clean old data (--hours=24)');
        $this->line('  stats    - Show statistics');
        $this->line('');
        $this->line('= Production Safety:');
        $this->line('  Use --force to enable/disable in production');
        $this->line('  Status, clean, and stats are always safe');

        return self::FAILURE;
    }
}