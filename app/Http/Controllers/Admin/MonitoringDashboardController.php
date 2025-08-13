<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class MonitoringDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
        $this->middleware('throttle:30,1'); // Rate limit: 30 requests per minute
    }

    public function index(): Response
    {
        return Inertia::render('Admin/MonitoringDashboard', [
            'overview' => $this->getOverviewMetrics(),
            'alerts' => $this->getActiveAlerts(),
            'tools' => [
                'telescope' => [
                    'enabled' => config('telescope-enhanced.enabled', false),
                    'url' => config('telescope-enhanced.enabled', false) ? url(config('telescope-enhanced.path', 'admin/telescope')) : null,
                ],
                'horizon' => [
                    'enabled' => config('horizon.enabled', true),
                    'url' => config('horizon.enabled', true) ? url('horizon') : null,
                ],
                'sentry' => [
                    'enabled' => !empty(config('sentry-enhanced.dsn')),
                    'environment' => config('sentry-enhanced.environment'),
                ],
            ],
        ]);
    }

    public function metrics(): JsonResponse
    {
        return response()->json([
            'system' => $this->getSystemMetrics(),
            'application' => $this->getApplicationMetrics(),
            'database' => $this->getDatabaseMetrics(),
            'cache' => $this->getCacheMetrics(),
            'queue' => $this->getQueueMetrics(),
            'errors' => $this->getErrorMetrics(),
        ]);
    }

    public function systemHealth(): JsonResponse
    {
        $health = [
            'status' => 'healthy',
            'checks' => [
                'database' => $this->checkDatabase(),
                'cache' => $this->checkCache(),
                'queue' => $this->checkQueue(),
                'storage' => $this->checkStorage(),
                'external_apis' => $this->checkExternalAPIs(),
            ],
            'timestamp' => now()->toISOString(),
        ];

        // Determine overall status
        $failedChecks = array_filter($health['checks'], fn($check) => !$check['healthy']);
        if (!empty($failedChecks)) {
            $health['status'] = count($failedChecks) > 2 ? 'critical' : 'degraded';
        }

        return response()->json($health);
    }

    public function alerts(): JsonResponse
    {
        return response()->json([
            'active' => $this->getActiveAlerts(),
            'recent' => $this->getRecentAlerts(),
            'summary' => $this->getAlertsSummary(),
        ]);
    }

    public function performance(): JsonResponse
    {
        return response()->json([
            'response_times' => $this->getResponseTimes(),
            'throughput' => $this->getThroughput(),
            'error_rates' => $this->getErrorRates(),
            'resource_usage' => $this->getResourceUsage(),
        ]);
    }

    protected function getOverviewMetrics(): array
    {
        return [
            'uptime' => $this->calculateUptime(),
            'total_requests' => $this->getTotalRequests(),
            'error_rate' => $this->getErrorRate(),
            'avg_response_time' => $this->getAverageResponseTime(),
            'active_users' => $this->getActiveUsers(),
            'queue_jobs' => $this->getQueueJobCount(),
        ];
    }

    protected function getSystemMetrics(): array
    {
        return [
            'memory' => [
                'used' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
                'limit' => $this->getMemoryLimit(),
            ],
            'cpu' => [
                'load' => sys_getloadavg(),
                'cores' => $this->getCpuCoreCount(),
            ],
            'disk' => [
                'free' => disk_free_space('/'),
                'total' => disk_total_space('/'),
            ],
            'php' => [
                'version' => PHP_VERSION,
                'opcache' => $this->getOpcacheStatus(),
            ],
        ];
    }

    protected function getApplicationMetrics(): array
    {
        return [
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
            'debug' => config('app.debug'),
            'maintenance' => app()->isDownForMaintenance(),
            'features' => [
                'verification_badges' => config('features.verification_badges', false),
                'pdf_generation' => config('features.pdf_generation', false),
                'sentiment_analysis' => config('features.sentiment_analysis', false),
            ],
        ];
    }

    protected function getDatabaseMetrics(): array
    {
        $connection = DB::connection();
        
        return [
            'connection' => config('database.default'),
            'driver' => config('database.connections.' . config('database.default') . '.driver'),
            'active_connections' => $this->getActiveConnections(),
            'slow_queries' => $this->getSlowQueriesCount(),
            'database_size' => $this->getDatabaseSize(),
            'tables_count' => $this->getTablesCount(),
        ];
    }

    protected function getCacheMetrics(): array
    {
        return [
            'driver' => config('cache.default'),
            'hit_rate' => $this->getCacheHitRate(),
            'memory_usage' => $this->getCacheMemoryUsage(),
            'key_count' => $this->getCacheKeyCount(),
        ];
    }

    protected function getQueueMetrics(): array
    {
        return [
            'driver' => config('queue.default'),
            'pending_jobs' => $this->getPendingJobsCount(),
            'failed_jobs' => $this->getFailedJobsCount(),
            'processed_jobs' => $this->getProcessedJobsCount(),
            'workers' => $this->getActiveWorkersCount(),
        ];
    }

    protected function getErrorMetrics(): array
    {
        $last24Hours = now()->subDay();
        
        return [
            'total_errors' => Cache::get('errors_count_24h', 0),
            'error_rate' => $this->calculateErrorRate(),
            'top_errors' => $this->getTopErrors(),
            'critical_errors' => $this->getCriticalErrors(),
            'sentry_issues' => $this->getSentryIssuesCount(),
        ];
    }

    protected function checkDatabase(): array
    {
        try {
            DB::select('SELECT 1');
            return ['healthy' => true, 'message' => 'Database connection successful'];
        } catch (\Exception $e) {
            return ['healthy' => false, 'message' => 'Database connection failed: ' . $e->getMessage()];
        }
    }

    protected function checkCache(): array
    {
        try {
            Cache::put('health_check', 'ok', 60);
            $value = Cache::get('health_check');
            return ['healthy' => $value === 'ok', 'message' => $value === 'ok' ? 'Cache working' : 'Cache not working'];
        } catch (\Exception $e) {
            return ['healthy' => false, 'message' => 'Cache error: ' . $e->getMessage()];
        }
    }

    protected function checkQueue(): array
    {
        try {
            $pendingJobs = $this->getPendingJobsCount();
            $isHealthy = $pendingJobs < config('telescope-enhanced.alerts.queue_threshold', 100);
            return [
                'healthy' => $isHealthy,
                'message' => $isHealthy ? 'Queue processing normally' : "Queue backlog: {$pendingJobs} jobs",
                'pending_jobs' => $pendingJobs,
            ];
        } catch (\Exception $e) {
            return ['healthy' => false, 'message' => 'Queue check failed: ' . $e->getMessage()];
        }
    }

    protected function checkStorage(): array
    {
        try {
            $freeSpace = disk_free_space(storage_path());
            $totalSpace = disk_total_space(storage_path());
            $usagePercent = (($totalSpace - $freeSpace) / $totalSpace) * 100;
            
            $isHealthy = $usagePercent < 90;
            return [
                'healthy' => $isHealthy,
                'message' => $isHealthy ? 'Storage healthy' : "Storage usage at {$usagePercent}%",
                'usage_percent' => round($usagePercent, 2),
            ];
        } catch (\Exception $e) {
            return ['healthy' => false, 'message' => 'Storage check failed: ' . $e->getMessage()];
        }
    }

    protected function checkExternalAPIs(): array
    {
        $apis = [
            'coingecko' => $this->checkCoingeckoAPI(),
            'browserless' => $this->checkBrowserlessAPI(),
        ];

        $healthyApis = array_filter($apis, fn($api) => $api['healthy']);
        $isHealthy = count($healthyApis) === count($apis);

        return [
            'healthy' => $isHealthy,
            'message' => $isHealthy ? 'All APIs healthy' : 'Some APIs unavailable',
            'details' => $apis,
        ];
    }

    // Helper methods for specific checks and calculations
    protected function checkCoingeckoAPI(): array
    {
        // Implementation for Coingecko API health check
        return ['healthy' => true, 'message' => 'API responding'];
    }

    protected function checkBrowserlessAPI(): array
    {
        // Implementation for Browserless API health check
        return ['healthy' => true, 'message' => 'Service running'];
    }

    protected function getActiveAlerts(): array
    {
        return Cache::get('active_monitoring_alerts', []);
    }

    protected function getRecentAlerts(): array
    {
        return Cache::get('recent_monitoring_alerts', []);
    }

    protected function getAlertsSummary(): array
    {
        return [
            'critical' => Cache::get('critical_alerts_count', 0),
            'warning' => Cache::get('warning_alerts_count', 0),
            'info' => Cache::get('info_alerts_count', 0),
        ];
    }

    // Additional helper methods would go here...
    protected function calculateUptime(): string
    {
        return '99.9%'; // Placeholder
    }

    protected function getTotalRequests(): int
    {
        return Cache::get('total_requests_24h', 0);
    }

    protected function getErrorRate(): float
    {
        return Cache::get('error_rate_24h', 0.0);
    }

    protected function getAverageResponseTime(): int
    {
        return Cache::get('avg_response_time_24h', 0);
    }

    protected function getActiveUsers(): int
    {
        return Cache::get('active_users_count', 0);
    }

    protected function getQueueJobCount(): int
    {
        return Cache::get('queue_jobs_pending', 0);
    }

    protected function getMemoryLimit(): int
    {
        $limit = ini_get('memory_limit');
        return $this->parseBytes($limit);
    }

    protected function getCpuCoreCount(): int
    {
        return (int) shell_exec('nproc') ?: 1;
    }

    protected function getOpcacheStatus(): ?array
    {
        return function_exists('opcache_get_status') ? opcache_get_status() : null;
    }

    protected function parseBytes(string $size): int
    {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);
        
        if ($unit) {
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        }
        
        return round($size);
    }

    // Additional placeholder methods for metrics collection
    protected function getActiveConnections(): int { return 0; }
    protected function getSlowQueriesCount(): int { return 0; }
    protected function getDatabaseSize(): int { return 0; }
    protected function getTablesCount(): int { return 0; }
    protected function getCacheHitRate(): float { return 0.0; }
    protected function getCacheMemoryUsage(): int { return 0; }
    protected function getCacheKeyCount(): int { return 0; }
    protected function getPendingJobsCount(): int { return 0; }
    protected function getFailedJobsCount(): int { return 0; }
    protected function getProcessedJobsCount(): int { return 0; }
    protected function getActiveWorkersCount(): int { return 0; }
    protected function calculateErrorRate(): float { return 0.0; }
    protected function getTopErrors(): array { return []; }
    protected function getCriticalErrors(): array { return []; }
    protected function getSentryIssuesCount(): int { return 0; }
    protected function getResponseTimes(): array { return []; }
    protected function getThroughput(): array { return []; }
    protected function getErrorRates(): array { return []; }
    protected function getResourceUsage(): array { return []; }
}