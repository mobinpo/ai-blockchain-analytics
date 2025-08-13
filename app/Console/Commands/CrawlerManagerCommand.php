<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CrawlerRule;
use App\Services\CrawlerQueueService;
use App\Services\CrawlerOctaneService;
use Illuminate\Console\Command;

final class CrawlerManagerCommand extends Command
{
    protected $signature = 'crawler:manage 
                            {action : Action: start, stop, status, queue, octane, rules}
                            {--mode= : Mode: scheduled, priority, realtime, batch}
                            {--platform= : Platform: twitter, reddit, telegram}
                            {--rules= : Comma-separated rule IDs}
                            {--create-sample : Create sample crawler rules}';

    protected $description = 'Manage the social media crawler micro-service system';

    public function __construct(
        private readonly CrawlerQueueService $queueService,
        private readonly CrawlerOctaneService $octaneService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'start' => $this->handleStartCrawling(),
            'stop' => $this->handleStopCrawling(),
            'status' => $this->handleShowStatus(),
            'queue' => $this->handleQueueManagement(),
            'octane' => $this->handleOctaneManagement(),
            'rules' => $this->handleRulesManagement(),
            default => $this->error("Invalid action: {$action}") ?: self::FAILURE,
        };
    }

    private function handleStartCrawling(): int
    {
        $mode = $this->option('mode') ?? 'scheduled';
        $ruleIds = $this->parseRuleIds();

        $this->info("🚀 Starting {$mode} crawling...");

        $results = match ($mode) {
            'scheduled' => $this->queueService->startScheduledCrawling(),
            'priority' => $this->queueService->startHighPriorityCrawling($ruleIds),
            'realtime' => $this->queueService->startRealTimeCrawling(),
            'batch' => $this->octaneService->startBatchCrawl(),
            default => ['status' => 'error', 'message' => "Invalid mode: {$mode}"],
        };

        $this->displayResults($results);
        return self::SUCCESS;
    }

    private function handleStopCrawling(): int
    {
        $mode = $this->option('mode') ?? 'queue';
        $ruleIds = $this->parseRuleIds();

        if ($mode === 'octane') {
            $results = $this->octaneService->stopAllTasks();
            $this->info("Stopped {$results['tasks_stopped']} Octane tasks");
        } else {
            $results = $this->queueService->pauseCrawling($ruleIds);
            $this->info("Paused {$results['rules_affected']} rules");
        }

        return self::SUCCESS;
    }

    private function handleShowStatus(): int
    {
        $this->info("📊 Crawler System Status");
        
        $queueStatus = $this->queueService->getQueueStatus();
        $this->displayQueueStatus($queueStatus);

        $this->displayRulesSummary();
        return self::SUCCESS;
    }

    private function handleQueueManagement(): int
    {
        $this->displayQueueStatus($this->queueService->getQueueStatus());
        return self::SUCCESS;
    }

    private function handleOctaneManagement(): int
    {
        $status = $this->octaneService->getTaskStatus();
        $this->displayOctaneStatus($status);
        return self::SUCCESS;
    }

    private function handleRulesManagement(): int
    {
        if ($this->option('create-sample')) {
            return $this->createSampleRules();
        }

        $this->displayRulesTable();
        return self::SUCCESS;
    }

    private function createSampleRules(): int
    {
        $this->info("🎯 Creating sample crawler rules...");

        $sampleRules = [
            [
                'name' => 'Bitcoin Discussion Tracker',
                'description' => 'Track Bitcoin-related discussions',
                'platforms' => ['twitter', 'reddit'],
                'keywords' => ['bitcoin', 'BTC'],
                'priority' => 2,
                'max_posts_per_hour' => 200,
                'crawl_interval_minutes' => 10,
                'created_by' => 'system',
            ],
            [
                'name' => 'Ethereum DeFi Monitor',
                'description' => 'Monitor Ethereum and DeFi ecosystem',
                'platforms' => ['twitter', 'reddit'],
                'keywords' => ['ethereum', 'defi'],
                'priority' => 1,
                'max_posts_per_hour' => 150,
                'crawl_interval_minutes' => 15,
                'created_by' => 'system',
            ],
        ];

        $created = 0;
        foreach ($sampleRules as $ruleData) {
            try {
                CrawlerRule::create($ruleData);
                $created++;
                $this->line("✅ Created: {$ruleData['name']}");
            } catch (\Exception $e) {
                $this->warn("❌ Failed: {$ruleData['name']}");
            }
        }

        $this->info("Created {$created} sample rules");
        return self::SUCCESS;
    }

    private function displayResults(array $results): void
    {
        $status = $results['status'] ?? 'unknown';
        $this->line("Status: {$status}");

        if (isset($results['tasks_dispatched'])) {
            $this->line("Tasks dispatched: {$results['tasks_dispatched']}");
        }
    }

    private function displayQueueStatus(array $status): void
    {
        $summary = $status['summary'] ?? [];
        $this->line("Total pending jobs: " . ($summary['total_pending_jobs'] ?? 0));
        $this->line("Overall status: " . ($summary['overall_status'] ?? 'unknown'));
    }

    private function displayOctaneStatus(array $status): void
    {
        $this->line("Active tasks: {$status['active_tasks']}");
        $this->line("Memory usage: {$status['memory_usage_mb']} MB");
    }

    private function displayRulesSummary(): void
    {
        $total = CrawlerRule::count();
        $active = CrawlerRule::where('active', true)->count();
        
        $this->line("Total rules: {$total}");
        $this->line("Active rules: {$active}");
    }

    private function displayRulesTable(): void
    {
        $rules = CrawlerRule::orderBy('priority')->get();

        if ($rules->isEmpty()) {
            $this->warn("No crawler rules found. Use --create-sample to create sample rules.");
            return;
        }

        $tableData = $rules->map(function ($rule) {
            return [
                $rule->id,
                $rule->name,
                implode(', ', $rule->platforms),
                $rule->priority,
                $rule->active ? '✅' : '❌',
            ];
        })->toArray();

        $this->table(['ID', 'Name', 'Platforms', 'Priority', 'Active'], $tableData);
    }

    private function parseRuleIds(): ?array
    {
        $rules = $this->option('rules');
        return $rules ? array_map('intval', explode(',', $rules)) : null;
    }
}