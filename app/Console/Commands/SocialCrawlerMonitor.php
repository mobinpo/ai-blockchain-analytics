<?php

namespace App\Console\Commands;

use App\Services\SocialCrawler\CrawlerErrorHandler;
use App\Services\SocialCrawler\RateLimitManager;
use App\Models\CrawlerJobStatus;
use Illuminate\Console\Command;

class SocialCrawlerMonitor extends Command
{
    protected $signature = 'social:monitor
                          {--platform= : Monitor specific platform}
                          {--errors : Show error statistics}
                          {--rates : Show rate limit status}
                          {--health : Show overall health status}';

    protected $description = 'Monitor social media crawler health, errors, and rate limits';

    protected CrawlerErrorHandler $errorHandler;
    protected RateLimitManager $rateLimitManager;

    public function __construct(CrawlerErrorHandler $errorHandler, RateLimitManager $rateLimitManager)
    {
        parent::__construct();
        $this->errorHandler = $errorHandler;
        $this->rateLimitManager = $rateLimitManager;
    }

    public function handle(): int
    {
        $platform = $this->option('platform');
        $showErrors = $this->option('errors');
        $showRates = $this->option('rates');
        $showHealth = $this->option('health') || (!$showErrors && !$showRates);

        if ($showHealth) {
            $this->showHealthStatus($platform);
        }

        if ($showErrors) {
            $this->newLine();
            $this->showErrorStatistics($platform);
        }

        if ($showRates) {
            $this->newLine();
            $this->showRateLimitStatus($platform);
        }

        return 0;
    }

    protected function showHealthStatus(?string $platform): void
    {
        $this->info('Social Crawler Health Status');
        $this->line(str_repeat('=', 60));

        $platforms = $platform ? [$platform] : ['twitter', 'reddit', 'telegram'];
        
        foreach ($platforms as $plat) {
            $this->showPlatformHealth($plat);
            $this->newLine();
        }
    }

    protected function showPlatformHealth(string $platform): void
    {
        $this->line("<fg=cyan>Platform: {$platform}</>");
        $this->line(str_repeat('-', 30));

        // Job status
        $jobs = CrawlerJobStatus::byPlatform($platform)->get();
        $totalJobs = $jobs->count();
        $runningJobs = $jobs->where('status', 'running')->count();
        $failedJobs = $jobs->where('status', 'failed')->count();
        $overdueJobs = $jobs->filter(fn($job) => $job->is_overdue)->count();

        $this->line("Jobs: {$totalJobs} total, {$runningJobs} running, {$failedJobs} failed, {$overdueJobs} overdue");

        // Error statistics
        $errorStats = $this->errorHandler->getErrorStats($platform, 24);
        $errorRate = round($errorStats['error_rate'], 2);
        $consecutiveFailures = $errorStats['consecutive_failures'];

        $errorColor = match(true) {
            $errorRate > 10 => 'red',
            $errorRate > 5 => 'yellow',
            default => 'green',
        };

        $this->line("Errors: <fg={$errorColor}>{$errorStats['total_errors']} in 24h (rate: {$errorRate}/hr, consecutive: {$consecutiveFailures})</>");

        // Rate limit status
        $rateLimitStatus = $this->rateLimitManager->getRateLimitStatus($platform);
        $hasLimits = !empty($rateLimitStatus);
        
        if ($hasLimits) {
            $limitedEndpoints = array_filter($rateLimitStatus, fn($status) => $status['is_limited']);
            $limitCount = count($limitedEndpoints);
            
            $limitColor = $limitCount > 0 ? 'red' : 'green';
            $limitText = $limitCount > 0 ? "{$limitCount} endpoints limited" : 'No limits active';
            
            $this->line("Rate Limits: <fg={$limitColor}>{$limitText}</>");
        } else {
            $this->line("Rate Limits: <fg=gray>Not configured</>");
        }

        // Overall health score
        $healthScore = $this->calculateHealthScore($platform, $errorStats, $rateLimitStatus, $jobs->toArray());
        $healthColor = match(true) {
            $healthScore >= 80 => 'green',
            $healthScore >= 60 => 'yellow',
            default => 'red',
        };

        $this->line("Health Score: <fg={$healthColor}>{$healthScore}%</>");
    }

    protected function showErrorStatistics(?string $platform): void
    {
        $this->info('Error Statistics (Last 24 Hours)');
        $this->line(str_repeat('=', 60));

        $platforms = $platform ? [$platform] : ['twitter', 'reddit', 'telegram'];

        foreach ($platforms as $plat) {
            $stats = $this->errorHandler->getErrorStats($plat, 24);
            
            if ($stats['total_errors'] === 0) {
                $this->line("<fg=green>{$plat}: No errors</>");
                continue;
            }

            $this->line("<fg=cyan>{$plat}:</>");
            $this->line("  Total Errors: {$stats['total_errors']}");
            $this->line("  Error Rate: " . round($stats['error_rate'], 2) . "/hour");
            $this->line("  Consecutive Failures: {$stats['consecutive_failures']}");

            if (!empty($stats['most_common_errors'])) {
                $this->line("  Most Common Errors:");
                foreach (array_slice($stats['most_common_errors'], 0, 3, true) as $error => $count) {
                    $shortError = substr($error, 0, 50) . (strlen($error) > 50 ? '...' : '');
                    $this->line("    {$count}x: {$shortError}");
                }
            }

            $this->newLine();
        }
    }

    protected function showRateLimitStatus(?string $platform): void
    {
        $this->info('Rate Limit Status');
        $this->line(str_repeat('=', 60));

        $platforms = $platform ? [$platform] : ['twitter', 'reddit', 'telegram'];

        foreach ($platforms as $plat) {
            $status = $this->rateLimitManager->getRateLimitStatus($plat);
            
            if (empty($status)) {
                $this->line("<fg=gray>{$plat}: No rate limits configured</>");
                continue;
            }

            $this->line("<fg=cyan>{$plat}:</>");
            
            foreach ($status as $endpoint => $endpointStatus) {
                $limit = $endpointStatus['limit'];
                $remaining = $endpointStatus['remaining'];
                $resetTime = $endpointStatus['reset_time'];
                $isLimited = $endpointStatus['is_limited'];

                $statusColor = $isLimited ? 'red' : ($remaining < $limit * 0.2 ? 'yellow' : 'green');
                $statusText = $isLimited ? 'LIMITED' : 'OK';
                
                $resetText = $resetTime ? $resetTime->diffForHumans() : 'N/A';
                
                $this->line("  {$endpoint}: <fg={$statusColor}>{$statusText}</> ({$remaining}/{$limit} remaining, resets {$resetText})");
            }

            $this->newLine();
        }
    }

    protected function calculateHealthScore(string $platform, array $errorStats, array $rateLimitStatus, array $jobs): int
    {
        $score = 100;

        // Deduct points for errors
        $errorRate = $errorStats['error_rate'];
        if ($errorRate > 0) {
            $score -= min(30, $errorRate * 2); // Max 30 points for errors
        }

        // Deduct points for consecutive failures
        $consecutiveFailures = $errorStats['consecutive_failures'];
        if ($consecutiveFailures > 0) {
            $score -= min(20, $consecutiveFailures * 4); // Max 20 points
        }

        // Deduct points for rate limiting
        $limitedEndpoints = array_filter($rateLimitStatus, fn($status) => $status['is_limited']);
        $score -= count($limitedEndpoints) * 10; // 10 points per limited endpoint

        // Deduct points for failed/overdue jobs
        $failedJobs = array_filter($jobs, fn($job) => $job['status'] === 'failed');
        $overdueJobs = array_filter($jobs, fn($job) => isset($job['is_overdue']) && $job['is_overdue']);
        
        $score -= (count($failedJobs) * 5) + (count($overdueJobs) * 3);

        return max(0, (int) $score);
    }
}