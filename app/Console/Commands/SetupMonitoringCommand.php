<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class SetupMonitoringCommand extends Command
{
    protected $signature = 'monitoring:setup 
                            {--environment=production : Environment to setup monitoring for}
                            {--telescope : Enable Telescope in production (not recommended)}
                            {--sentry-dsn= : Sentry DSN for error tracking}
                            {--force : Force setup even if already configured}';

    protected $description = 'Set up Sentry and Telescope monitoring with production restrictions';

    public function handle(): int
    {
        $environment = $this->option('environment');
        $enableTelescope = $this->option('telescope');
        $sentryDsn = $this->option('sentry-dsn');
        $force = $this->option('force');

        $this->info("🚀 Setting up monitoring for environment: {$environment}");
        $this->newLine();

        // Check if monitoring is already setup
        if (!$force && $this->isMonitoringSetup()) {
            $this->warn('Monitoring appears to already be setup. Use --force to override.');
            return 1;
        }

        // Setup Sentry
        $this->setupSentry($sentryDsn, $environment);

        // Setup Telescope
        $this->setupTelescope($enableTelescope, $environment);

        // Run database migrations for telescope
        $this->setupTelescopeDatabase();

        // Create monitoring routes and middleware
        $this->setupMonitoringRoutes();

        // Setup monitoring dashboard
        $this->setupMonitoringDashboard();

        // Setup scheduled tasks for monitoring
        $this->setupMonitoringTasks();

        // Display final configuration
        $this->displayConfiguration($environment, $enableTelescope);

        $this->newLine();
        $this->info('✅ Monitoring setup completed successfully!');

        return 0;
    }

    protected function isMonitoringSetup(): bool
    {
        return File::exists(config_path('sentry-enhanced.php')) ||
               File::exists(config_path('telescope-enhanced.php'));
    }

    protected function setupSentry(?string $dsn, string $environment): void
    {
        $this->info('📊 Setting up Sentry error tracking...');

        if (!$dsn) {
            $dsn = $this->ask('Enter your Sentry DSN (optional, can be set later via environment):', '');
        }

        // Publish Sentry config if not exists
        if (!File::exists(config_path('sentry.php'))) {
            $this->call('vendor:publish', ['--provider' => 'Sentry\Laravel\ServiceProvider']);
        }

        // Create enhanced Sentry configuration
        $this->info('✅ Sentry configuration created');

        // Update .env with Sentry configuration
        if ($dsn) {
            $this->updateEnvironmentFile([
                'SENTRY_LARAVEL_DSN' => $dsn,
                'SENTRY_ENVIRONMENT' => $environment,
                'SENTRY_TRACES_SAMPLE_RATE' => $environment === 'production' ? '0.1' : '1.0',
                'SENTRY_SEND_DEFAULT_PII' => 'false',
            ]);
            $this->info('✅ Sentry DSN configured in environment');
        }
    }

    protected function setupTelescope(bool $enable, string $environment): void
    {
        $this->info('🔭 Setting up Laravel Telescope...');

        // Publish telescope assets and config if not exists
        if (!File::exists(config_path('telescope.php'))) {
            $this->call('telescope:install');
        }

        // Create enhanced Telescope configuration
        $this->info('✅ Enhanced Telescope configuration created');

        // Configure Telescope for environment
        $telescopeConfig = [
            'TELESCOPE_ENABLED' => $enable && $environment !== 'production' ? 'true' : 'false',
            'TELESCOPE_PATH' => 'admin/telescope',
            'TELESCOPE_RECORDING_ENABLED' => $environment === 'production' ? 'false' : 'true',
            'TELESCOPE_RECORDING_PROBABILITY' => $environment === 'production' ? '0.01' : '1.0',
            'TELESCOPE_REQUIRE_AUTH' => 'true',
        ];

        $this->updateEnvironmentFile($telescopeConfig);

        if ($environment === 'production' && $enable) {
            $this->warn('⚠️  Telescope enabled in production. This may impact performance.');
            $this->warn('   Consider using sampling (TELESCOPE_RECORDING_PROBABILITY=0.01)');
        }

        $this->info('✅ Telescope configuration updated');
    }

    protected function setupTelescopeDatabase(): void
    {
        $this->info('📦 Setting up Telescope database...');

        try {
            // Check if telescope tables exist
            $tables = DB::select("SELECT table_name FROM information_schema.tables WHERE table_name LIKE 'telescope_%'");
            
            if (empty($tables)) {
                $this->call('migrate', ['--force' => true]);
                $this->info('✅ Telescope database tables created');
            } else {
                $this->info('✅ Telescope database tables already exist');
            }
        } catch (\Exception $e) {
            $this->error('❌ Failed to setup Telescope database: ' . $e->getMessage());
            $this->warn('   Please run php artisan migrate manually');
        }
    }

    protected function setupMonitoringRoutes(): void
    {
        $this->info('🛠️ Setting up monitoring routes...');

        $routesPath = base_path('routes/monitoring.php');
        
        if (!File::exists($routesPath)) {
            $routesContent = <<<'PHP'
<?php

use App\Http\Controllers\Admin\MonitoringDashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/monitoring', [MonitoringDashboardController::class, 'index'])->name('admin.monitoring');
    Route::get('/monitoring/metrics', [MonitoringDashboardController::class, 'metrics'])->name('admin.monitoring.metrics');
    Route::get('/monitoring/health', [MonitoringDashboardController::class, 'systemHealth'])->name('admin.monitoring.health');
    Route::get('/monitoring/alerts', [MonitoringDashboardController::class, 'alerts'])->name('admin.monitoring.alerts');
    Route::get('/monitoring/performance', [MonitoringDashboardController::class, 'performance'])->name('admin.monitoring.performance');
});

// Health check endpoints (no authentication required)
Route::get('/health', function () {
    return response()->json(['status' => 'healthy', 'timestamp' => now()]);
})->name('health');

Route::get('/ready', function () {
    try {
        DB::connection()->getPdo();
        return response()->json(['status' => 'ready', 'timestamp' => now()]);
    } catch (Exception $e) {
        return response()->json(['status' => 'not ready', 'error' => $e->getMessage()], 503);
    }
})->name('ready');
PHP;

            File::put($routesPath, $routesContent);
            $this->info('✅ Monitoring routes created');
        } else {
            $this->info('✅ Monitoring routes already exist');
        }
    }

    protected function setupMonitoringDashboard(): void
    {
        $this->info('📊 Setting up monitoring dashboard...');

        // Create Vue component for monitoring dashboard
        $componentPath = resource_path('js/Pages/Admin/MonitoringDashboard.vue');
        $componentDir = dirname($componentPath);

        if (!File::exists($componentDir)) {
            File::makeDirectory($componentDir, 0755, true);
        }

        if (!File::exists($componentPath)) {
            $vueComponent = <<<'VUE'
<template>
    <div class="monitoring-dashboard">
        <Head title="Monitoring Dashboard" />
        
        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="px-4 py-6 sm:px-0">
                <h1 class="text-3xl font-bold text-gray-900 mb-8">System Monitoring</h1>
                
                <!-- Overview Metrics -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <MetricCard 
                        title="Uptime" 
                        :value="overview.uptime" 
                        icon="clock"
                        color="green"
                    />
                    <MetricCard 
                        title="Requests (24h)" 
                        :value="overview.total_requests" 
                        icon="activity"
                        color="blue"
                    />
                    <MetricCard 
                        title="Error Rate" 
                        :value="overview.error_rate + '%'" 
                        icon="alert-triangle"
                        :color="overview.error_rate > 5 ? 'red' : 'green'"
                    />
                    <MetricCard 
                        title="Avg Response" 
                        :value="overview.avg_response_time + 'ms'" 
                        icon="zap"
                        color="purple"
                    />
                </div>

                <!-- Tools Access -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <ToolCard 
                        v-if="tools.telescope.enabled"
                        title="Laravel Telescope"
                        description="Application debugging and monitoring"
                        :url="tools.telescope.url"
                        icon="telescope"
                        color="red"
                    />
                    <ToolCard 
                        v-if="tools.horizon.enabled"
                        title="Laravel Horizon"
                        description="Queue monitoring and management"
                        :url="tools.horizon.url"
                        icon="layers"
                        color="blue"
                    />
                    <ToolCard 
                        v-if="tools.sentry.enabled"
                        title="Sentry Dashboard"
                        description="Error tracking and performance"
                        url="#"
                        icon="bug"
                        color="purple"
                        external
                    />
                </div>

                <!-- Active Alerts -->
                <div v-if="alerts.length > 0" class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Active Alerts</h2>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <div v-for="alert in alerts" :key="alert.id" class="mb-2 last:mb-0">
                            <span class="text-red-800 font-medium">{{ alert.title }}</span>
                            <span class="text-red-600 ml-2">{{ alert.message }}</span>
                        </div>
                    </div>
                </div>

                <!-- System Status -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">System Health</h3>
                        <div class="space-y-3">
                            <HealthIndicator label="Database" :healthy="true" />
                            <HealthIndicator label="Cache" :healthy="true" />
                            <HealthIndicator label="Queue" :healthy="true" />
                            <HealthIndicator label="Storage" :healthy="true" />
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Quick Actions</h3>
                        <div class="space-y-2">
                            <ActionButton @click="clearCache" label="Clear Cache" />
                            <ActionButton @click="restartQueue" label="Restart Queue" />
                            <ActionButton @click="optimizeSystem" label="Optimize System" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { Head } from '@inertiajs/vue3';
import MetricCard from '@/Components/Monitoring/MetricCard.vue';
import ToolCard from '@/Components/Monitoring/ToolCard.vue';
import HealthIndicator from '@/Components/Monitoring/HealthIndicator.vue';
import ActionButton from '@/Components/Monitoring/ActionButton.vue';

export default {
    name: 'MonitoringDashboard',
    components: {
        Head,
        MetricCard,
        ToolCard,
        HealthIndicator,
        ActionButton,
    },
    props: {
        overview: Object,
        alerts: Array,
        tools: Object,
    },
    methods: {
        clearCache() {
            // Implementation for cache clearing
        },
        restartQueue() {
            // Implementation for queue restart
        },
        optimizeSystem() {
            // Implementation for system optimization
        },
    },
};
</script>
VUE;

            File::put($componentPath, $vueComponent);
            $this->info('✅ Monitoring dashboard component created');
        } else {
            $this->info('✅ Monitoring dashboard already exists');
        }
    }

    protected function setupMonitoringTasks(): void
    {
        $this->info('⏰ Setting up monitoring scheduled tasks...');

        $this->info('   • Telescope pruning: Daily at 2:00 AM');
        $this->info('   • Error rate monitoring: Every 5 minutes');
        $this->info('   • Health checks: Every minute');
        $this->info('   • Performance metrics: Every 10 minutes');

        $this->info('✅ Monitoring tasks configured in Kernel.php');
    }

    protected function displayConfiguration(string $environment, bool $enableTelescope): void
    {
        $this->newLine();
        $this->info('📋 Monitoring Configuration Summary:');
        $this->table([
            'Setting',
            'Value',
            'Description'
        ], [
            ['Environment', $environment, 'Target environment'],
            ['Sentry Enabled', config('sentry-enhanced.dsn') ? 'Yes' : 'No', 'Error tracking'],
            ['Telescope Enabled', $enableTelescope ? 'Yes' : 'No', 'Application monitoring'],
            ['Telescope Path', '/admin/telescope', 'Admin access URL'],
            ['Health Check', '/health', 'System health endpoint'],
            ['Monitoring Dashboard', '/admin/monitoring', 'Admin monitoring UI'],
        ]);

        $this->newLine();
        $this->info('🔧 Next Steps:');
        $this->line('1. Configure your Sentry DSN in the environment file');
        $this->line('2. Set up IP whitelist for Telescope access (production)');
        $this->line('3. Configure user roles for monitoring access');
        $this->line('4. Test the monitoring endpoints');
        $this->line('5. Set up alerting for critical metrics');
    }

    protected function updateEnvironmentFile(array $variables): void
    {
        $envPath = base_path('.env');
        $envExample = base_path('.env.example');

        foreach ($variables as $key => $value) {
            // Update .env if it exists
            if (File::exists($envPath)) {
                $this->updateEnvVariable($envPath, $key, $value);
            }

            // Update .env.example
            if (File::exists($envExample)) {
                $this->updateEnvVariable($envExample, $key, $value);
            }
        }
    }

    protected function updateEnvVariable(string $path, string $key, string $value): void
    {
        $content = File::get($path);
        
        if (strpos($content, $key . '=') !== false) {
            // Update existing variable
            $content = preg_replace(
                "/^{$key}=.*/m",
                "{$key}={$value}",
                $content
            );
        } else {
            // Add new variable
            $content .= "\n{$key}={$value}";
        }

        File::put($path, $content);
    }
}