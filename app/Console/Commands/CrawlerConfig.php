<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CrawlerKeywordRule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

final class CrawlerConfig extends Command
{
    protected $signature = 'crawler:config
                           {action : Action to perform (show,test,validate,rules,export)}
                           {--platform= : Specific platform to configure (twitter,reddit,telegram)}
                           {--export-format=json : Export format for configuration}
                           {--fix : Auto-fix configuration issues}';

    protected $description = 'Manage crawler configuration and keyword rules';

    public function handle(): int
    {
        $action = $this->argument('action');

        return match($action) {
            'show' => $this->showConfiguration(),
            'test' => $this->testConfiguration(),
            'validate' => $this->validateConfiguration(),
            'rules' => $this->manageKeywordRules(),
            'export' => $this->exportConfiguration(),
            default => $this->displayHelp()
        };
    }

    private function showConfiguration(): int
    {
        $this->info('🔧 CRAWLER CONFIGURATION');
        $this->newLine();

        $config = config('crawler_microservice');
        $platform = $this->option('platform');

        if ($platform) {
            $this->showPlatformConfig($platform, $config);
        } else {
            $this->showAllConfigurations($config);
        }

        return Command::SUCCESS;
    }

    private function showAllConfigurations(array $config): void
    {
        // System settings
        $this->info('🖥️  System Settings');
        $this->table(['Setting', 'Value'], [
            ['Enabled', $config['enabled'] ? '✅ Yes' : '❌ No'],
            ['Deployment Mode', ucfirst($config['deployment_mode'])],
            ['Schedule Interval', $config['schedule']['interval'] . ' seconds'],
            ['Max Execution Time', $config['schedule']['max_execution_time'] . ' seconds'],
            ['Concurrent Workers', $config['schedule']['concurrent_workers']]
        ]);
        $this->newLine();

        // Platform overview
        $this->info('🌐 Platform Status');
        $platformData = [];
        foreach (['twitter', 'reddit', 'telegram'] as $platform) {
            $platformConfig = $config[$platform];
            $enabled = $platformConfig['enabled'] ? '✅' : '❌';
            $configured = $this->isPlatformConfigured($platform, $platformConfig) ? '✅' : '❌';
            
            $platformData[] = [
                ucfirst($platform),
                $enabled,
                $configured,
                $this->getRateLimitInfo($platformConfig)
            ];
        }
        $this->table(['Platform', 'Enabled', 'Configured', 'Rate Limit'], $platformData);
        $this->newLine();

        // Performance settings
        $this->info('⚡ Performance Settings');
        $this->table(['Setting', 'Value'], [
            ['Octane Enabled', $config['performance']['octane']['enabled'] ? 'Yes' : 'No'],
            ['Octane Workers', $config['performance']['octane']['workers']],
            ['Caching Enabled', $config['performance']['caching']['enabled'] ? 'Yes' : 'No'],
            ['Cache TTL', $config['performance']['caching']['ttl_minutes'] . ' minutes'],
            ['Connection Pooling', $config['performance']['optimization']['connection_pooling'] ? 'Yes' : 'No']
        ]);
        $this->newLine();

        // Storage settings
        $this->info('💾 Storage Configuration');
        $this->table(['Setting', 'Value'], [
            ['Store Raw Data', $config['storage']['store_raw_data'] ? 'Yes' : 'No'],
            ['Compress Raw Data', $config['storage']['compress_raw_data'] ? 'Yes' : 'No'],
            ['Retention Days', $config['storage']['retention_days']],
            ['Batch Insert Size', $config['storage']['database']['batch_insert_size']],
            ['File Storage', $config['storage']['file_storage']['enabled'] ? 'Enabled' : 'Disabled']
        ]);
    }

    private function showPlatformConfig(string $platform, array $config): void
    {
        if (!isset($config[$platform])) {
            $this->error("❌ Platform '{$platform}' not found in configuration");
            return;
        }

        $platformConfig = $config[$platform];
        $this->info("🔧 {$platform} Configuration");
        $this->newLine();

        // Basic settings
        $this->info('📋 Basic Settings');
        $basicData = [
            ['Enabled', $platformConfig['enabled'] ? '✅ Yes' : '❌ No'],
            ['Configured', $this->isPlatformConfigured($platform, $platformConfig) ? '✅ Yes' : '❌ No']
        ];

        // Platform-specific settings
        switch ($platform) {
            case 'twitter':
                $basicData[] = ['API Version', $platformConfig['api_version']];
                $basicData[] = ['Bearer Token', $this->maskCredential($platformConfig['bearer_token'])];
                $basicData[] = ['Max Results', $platformConfig['search_params']['max_results']];
                break;
                
            case 'reddit':
                $basicData[] = ['Client ID', $this->maskCredential($platformConfig['client_id'])];
                $basicData[] = ['User Agent', $platformConfig['user_agent']];
                $basicData[] = ['Target Subreddits', count($platformConfig['target_subreddits'])];
                break;
                
            case 'telegram':
                $basicData[] = ['Bot Token', $this->maskCredential($platformConfig['bot_token'])];
                $basicData[] = ['Target Channels', count($platformConfig['target_channels'])];
                break;
        }

        $this->table(['Setting', 'Value'], $basicData);
        $this->newLine();

        // Filters
        if (isset($platformConfig['filters'])) {
            $this->info('🔍 Filters');
            $filterData = [];
            foreach ($platformConfig['filters'] as $filter => $value) {
                $filterData[] = [ucwords(str_replace('_', ' ', $filter)), is_bool($value) ? ($value ? 'Yes' : 'No') : $value];
            }
            $this->table(['Filter', 'Value'], $filterData);
        }
    }

    private function testConfiguration(): int
    {
        $this->info('🧪 TESTING CRAWLER CONFIGURATION');
        $this->newLine();

        $platform = $this->option('platform');
        $results = [];

        if ($platform) {
            $results[$platform] = $this->testPlatform($platform);
        } else {
            foreach (['twitter', 'reddit', 'telegram'] as $p) {
                $results[$p] = $this->testPlatform($p);
            }
        }

        $this->displayTestResults($results);

        return Command::SUCCESS;
    }

    private function testPlatform(string $platform): array
    {
        $this->line("Testing {$platform}...");
        
        try {
            $config = config("crawler_microservice.{$platform}");
            
            if (!$config['enabled']) {
                return ['status' => 'disabled', 'message' => 'Platform is disabled'];
            }

            if (!$this->isPlatformConfigured($platform, $config)) {
                return ['status' => 'not_configured', 'message' => 'Missing required credentials'];
            }

            // Create crawler instance and test
            $crawlerClass = "App\\Services\\CrawlerMicroService\\Platforms\\" . ucfirst($platform) . "Crawler";
            
            if (!class_exists($crawlerClass)) {
                return ['status' => 'error', 'message' => 'Crawler class not found'];
            }

            $crawler = new $crawlerClass($config);
            $healthCheck = $crawler->healthCheck();

            return [
                'status' => $healthCheck['status'] === 'healthy' ? 'success' : 'error',
                'message' => $healthCheck['error'] ?? 'Connection successful',
                'details' => $healthCheck
            ];

        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function displayTestResults(array $results): void
    {
        $this->info('📊 Test Results');
        
        $testData = [];
        foreach ($results as $platform => $result) {
            $icon = match($result['status']) {
                'success' => '✅',
                'disabled' => '⚪',
                'not_configured' => '⚠️',
                'error' => '❌',
                default => '❓'
            };
            
            $testData[] = [
                ucfirst($platform),
                $icon . ' ' . ucfirst($result['status']),
                $result['message']
            ];
        }

        $this->table(['Platform', 'Status', 'Message'], $testData);

        $successCount = count(array_filter($results, fn($r) => $r['status'] === 'success'));
        $totalCount = count($results);
        
        $this->newLine();
        if ($successCount === $totalCount) {
            $this->info("🎉 All platforms tested successfully! ({$successCount}/{$totalCount})");
        } else {
            $this->warn("⚠️  {$successCount}/{$totalCount} platforms working correctly");
        }
    }

    private function validateConfiguration(): int
    {
        $this->info('✅ VALIDATING CRAWLER CONFIGURATION');
        $this->newLine();

        $config = config('crawler_microservice');
        $issues = [];
        $warnings = [];

        // System validation
        if (!$config['enabled']) {
            $warnings[] = 'Crawler system is disabled';
        }

        // Platform validation
        foreach (['twitter', 'reddit', 'telegram'] as $platform) {
            $platformConfig = $config[$platform];
            
            if ($platformConfig['enabled'] && !$this->isPlatformConfigured($platform, $platformConfig)) {
                $issues[] = "{$platform}: Missing required credentials";
            }
        }

        // Performance validation
        if ($config['performance']['octane']['enabled'] && !extension_loaded('swoole')) {
            $issues[] = 'Octane enabled but Swoole extension not loaded';
        }

        // Storage validation
        if ($config['storage']['file_storage']['enabled']) {
            $storagePath = storage_path($config['storage']['file_storage']['path']);
            if (!File::isDirectory($storagePath) && !File::makeDirectory($storagePath, 0755, true)) {
                $issues[] = 'Cannot create storage directory: ' . $storagePath;
            }
        }

        // Display results
        if (empty($issues) && empty($warnings)) {
            $this->info('✅ Configuration is valid - no issues found!');
        } else {
            if (!empty($issues)) {
                $this->error('❌ Configuration Issues Found:');
                foreach ($issues as $issue) {
                    $this->line("   • {$issue}");
                }
                $this->newLine();
            }
            
            if (!empty($warnings)) {
                $this->warn('⚠️  Configuration Warnings:');
                foreach ($warnings as $warning) {
                    $this->line("   • {$warning}");
                }
            }
        }

        if ($this->option('fix') && !empty($issues)) {
            $this->info('🔧 Auto-fixing issues...');
            $this->autoFixIssues($issues);
        }

        return empty($issues) ? Command::SUCCESS : Command::FAILURE;
    }

    private function manageKeywordRules(): int
    {
        $this->info('🔑 KEYWORD RULES MANAGEMENT');
        $this->newLine();

        $rules = CrawlerKeywordRule::all();
        
        if ($rules->isEmpty()) {
            $this->warn('⚠️  No keyword rules found');
            
            if ($this->confirm('Create default keyword rules?', true)) {
                CrawlerKeywordRule::createDefaults();
                $this->info('✅ Default keyword rules created');
                $rules = CrawlerKeywordRule::all();
            }
        }

        if ($rules->isNotEmpty()) {
            $this->displayKeywordRules($rules);
        }

        return Command::SUCCESS;
    }

    private function displayKeywordRules($rules): void
    {
        $this->info('📋 Active Keyword Rules (' . $rules->count() . ')');
        
        $ruleData = [];
        foreach ($rules as $rule) {
            $status = $rule->is_active ? '✅ Active' : '❌ Inactive';
            $platforms = implode(', ', $rule->platforms);
            $keywords = count($rule->keywords) . ' keywords';
            
            $ruleData[] = [
                $rule->id,
                $rule->name,
                $keywords,
                $platforms,
                ucfirst($rule->priority),
                $status,
                $rule->updated_at->diffForHumans()
            ];
        }

        $this->table(['ID', 'Name', 'Keywords', 'Platforms', 'Priority', 'Status', 'Updated'], $ruleData);
    }

    private function exportConfiguration(): int
    {
        $format = $this->option('export-format');
        $config = config('crawler_microservice');
        
        // Remove sensitive data
        $exportConfig = $this->sanitizeConfigForExport($config);
        
        $filename = "crawler_config_" . now()->format('Y-m-d_H-i-s') . ".{$format}";
        $path = storage_path("app/config_exports/{$filename}");
        
        // Ensure directory exists
        $directory = dirname($path);
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        switch ($format) {
            case 'json':
                File::put($path, json_encode($exportConfig, JSON_PRETTY_PRINT));
                break;
            case 'yaml':
                if (function_exists('yaml_emit')) {
                    File::put($path, yaml_emit($exportConfig));
                } else {
                    $this->error('YAML extension not available');
                    return Command::FAILURE;
                }
                break;
            default:
                $this->error("Unsupported export format: {$format}");
                return Command::FAILURE;
        }

        $this->info("📄 Configuration exported to: {$path}");
        return Command::SUCCESS;
    }

    private function displayHelp(): int
    {
        $this->info('🔧 CRAWLER CONFIGURATION MANAGEMENT');
        $this->newLine();
        
        $this->info('Available actions:');
        $this->table(['Action', 'Description'], [
            ['show', 'Display current configuration'],
            ['test', 'Test platform connections'],
            ['validate', 'Validate configuration'],
            ['rules', 'Manage keyword rules'],
            ['export', 'Export configuration to file']
        ]);
        
        $this->newLine();
        $this->info('Examples:');
        $this->line('  php artisan crawler:config show --platform=twitter');
        $this->line('  php artisan crawler:config test');
        $this->line('  php artisan crawler:config validate --fix');
        $this->line('  php artisan crawler:config export --export-format=json');

        return Command::SUCCESS;
    }

    private function isPlatformConfigured(string $platform, array $config): bool
    {
        return match($platform) {
            'twitter' => !empty($config['bearer_token']),
            'reddit' => !empty($config['client_id']) && !empty($config['client_secret']),
            'telegram' => !empty($config['bot_token']),
            default => false
        };
    }

    private function getRateLimitInfo(array $config): string
    {
        $rateLimit = $config['rate_limit'] ?? null;
        if (!$rateLimit) return 'Not configured';
        
        return ($rateLimit['requests_per_hour'] ?? 'N/A') . '/hour';
    }

    private function maskCredential(?string $credential): string
    {
        if (empty($credential)) return '❌ Not set';
        
        $length = strlen($credential);
        if ($length <= 8) return str_repeat('*', $length);
        
        return substr($credential, 0, 4) . str_repeat('*', $length - 8) . substr($credential, -4);
    }

    private function sanitizeConfigForExport(array $config): array
    {
        // Remove sensitive credentials
        $sensitiveKeys = [
            'bearer_token', 'api_key', 'api_secret', 'access_token', 'access_token_secret',
            'client_id', 'client_secret', 'password', 'bot_token', 'api_hash'
        ];

        return $this->recursivelyRemoveKeys($config, $sensitiveKeys);
    }

    private function recursivelyRemoveKeys(array $array, array $keysToRemove): array
    {
        foreach ($array as $key => $value) {
            if (in_array($key, $keysToRemove)) {
                $array[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $array[$key] = $this->recursivelyRemoveKeys($value, $keysToRemove);
            }
        }
        
        return $array;
    }

    private function autoFixIssues(array $issues): void
    {
        foreach ($issues as $issue) {
            if (str_contains($issue, 'storage directory')) {
                // Try to create missing directories
                $config = config('crawler_microservice');
                $storagePath = storage_path($config['storage']['file_storage']['path']);
                if (File::makeDirectory($storagePath, 0755, true)) {
                    $this->info("✅ Created storage directory: {$storagePath}");
                }
            }
        }
    }
}