<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\OpenAiJobResult;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OpenAiJobCleanup extends Command
{
    protected $signature = 'openai:cleanup 
                           {--days=30 : Delete completed jobs older than X days}
                           {--failed-days=7 : Delete failed jobs older than X days}
                           {--cache : Clean up streaming cache entries}
                           {--dry-run : Show what would be deleted without deleting}
                           {--force : Skip confirmation prompts}';

    protected $description = 'Cleanup old OpenAI job results and cache entries';

    public function handle(): int
    {
        $this->displayHeader();

        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        
        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No data will be deleted');
            $this->newLine();
        }

        // Clean up completed jobs
        $this->cleanupCompletedJobs($dryRun, $force);
        
        // Clean up failed jobs
        $this->cleanupFailedJobs($dryRun, $force);
        
        // Clean up cache entries
        if ($this->option('cache')) {
            $this->cleanupCacheEntries($dryRun, $force);
        }
        
        // Show statistics
        $this->showStatistics();

        return Command::SUCCESS;
    }

    private function displayHeader(): void
    {
        $this->info('🧹 OpenAI Job Cleanup Tool');
        $this->newLine();
    }

    private function cleanupCompletedJobs(bool $dryRun, bool $force): void
    {
        $days = (int) $this->option('days');
        $cutoffDate = now()->subDays($days);
        
        $query = OpenAiJobResult::where('status', 'completed')
            ->where('completed_at', '<', $cutoffDate);
        
        $count = $query->count();
        
        if ($count === 0) {
            $this->info("✅ No completed jobs older than {$days} days found");
            return;
        }

        $this->info("📊 Found {$count} completed jobs older than {$days} days");
        
        if ($dryRun) {
            $this->displayJobBreakdown($query->get());
            return;
        }

        if (!$force && !$this->confirm("Delete {$count} completed jobs?")) {
            $this->info('❌ Cleanup cancelled');
            return;
        }

        DB::transaction(function () use ($query, $count) {
            $deleted = $query->delete();
            $this->info("✅ Deleted {$deleted} completed jobs");
        });
    }

    private function cleanupFailedJobs(bool $dryRun, bool $force): void
    {
        $days = (int) $this->option('failed-days');
        $cutoffDate = now()->subDays($days);
        
        $query = OpenAiJobResult::where('status', 'failed')
            ->where('failed_at', '<', $cutoffDate);
        
        $count = $query->count();
        
        if ($count === 0) {
            $this->info("✅ No failed jobs older than {$days} days found");
            return;
        }

        $this->warn("📊 Found {$count} failed jobs older than {$days} days");
        
        if ($dryRun) {
            $this->displayJobBreakdown($query->get());
            return;
        }

        if (!$force && !$this->confirm("Delete {$count} failed jobs?")) {
            $this->info('❌ Cleanup cancelled');
            return;
        }

        DB::transaction(function () use ($query, $count) {
            $deleted = $query->delete();
            $this->info("✅ Deleted {$deleted} failed jobs");
        });
    }

    private function cleanupCacheEntries(bool $dryRun, bool $force): void
    {
        $this->info('🗑️ Cleaning up streaming cache entries...');
        
        try {
            $pattern = "openai_stream_*";
            $redis = Cache::getRedis();
            $keys = $redis->keys($pattern);
            
            $expiredKeys = [];
            $activeKeys = [];
            
            foreach ($keys as $key) {
                $data = Cache::get(str_replace(config('cache.prefix') . ':', '', $key));
                
                if (!$data) {
                    $expiredKeys[] = $key;
                    continue;
                }
                
                // Check if the job still exists and is not completed
                $jobId = str_replace('openai_stream_', '', str_replace(config('cache.prefix') . ':', '', $key));
                $job = OpenAiJobResult::where('job_id', $jobId)->first();
                
                if (!$job || in_array($job->status, ['completed', 'failed'])) {
                    $expiredKeys[] = $key;
                } else {
                    $activeKeys[] = $key;
                }
            }
            
            $this->info("📊 Found " . count($keys) . " cache entries:");
            $this->info("  - Active: " . count($activeKeys));
            $this->info("  - Expired/Orphaned: " . count($expiredKeys));
            
            if (empty($expiredKeys)) {
                $this->info("✅ No expired cache entries to clean");
                return;
            }
            
            if ($dryRun) {
                $this->info("Would delete " . count($expiredKeys) . " expired cache entries");
                return;
            }
            
            if (!$force && !$this->confirm("Delete " . count($expiredKeys) . " expired cache entries?")) {
                $this->info('❌ Cache cleanup cancelled');
                return;
            }
            
            $deleted = 0;
            foreach ($expiredKeys as $key) {
                $cleanKey = str_replace(config('cache.prefix') . ':', '', $key);
                if (Cache::forget($cleanKey)) {
                    $deleted++;
                }
            }
            
            $this->info("✅ Deleted {$deleted} cache entries");
            
        } catch (\Exception $e) {
            $this->error("❌ Error cleaning cache: " . $e->getMessage());
        }
    }

    private function displayJobBreakdown($jobs): void
    {
        $breakdown = $jobs->groupBy('job_type')->map(function ($typeJobs, $type) {
            return [
                'type' => $type,
                'count' => $typeJobs->count(),
                'oldest' => $typeJobs->min('created_at'),
                'newest' => $typeJobs->max('created_at'),
                'total_tokens' => $typeJobs->sum(function ($job) {
                    return $job->token_usage['total_tokens'] ?? 0;
                }),
                'total_cost' => $typeJobs->sum(function ($job) {
                    return $job->token_usage['estimated_cost_usd'] ?? 0;
                })
            ];
        });

        $this->newLine();
        $this->info('📋 Breakdown by job type:');
        
        $tableData = $breakdown->map(function ($item) {
            return [
                $item['type'],
                $item['count'],
                Carbon::parse($item['oldest'])->format('Y-m-d'),
                Carbon::parse($item['newest'])->format('Y-m-d'),
                number_format($item['total_tokens']),
                '$' . number_format($item['total_cost'], 4)
            ];
        })->toArray();

        $this->table(
            ['Job Type', 'Count', 'Oldest', 'Newest', 'Total Tokens', 'Total Cost'],
            $tableData
        );
    }

    private function showStatistics(): void
    {
        $this->newLine();
        $this->info('📊 Current Database Statistics:');
        
        $stats = OpenAiJobResult::selectRaw('
            status,
            COUNT(*) as count,
            MIN(created_at) as oldest,
            MAX(created_at) as newest,
            AVG(processing_time_ms) as avg_processing_time
        ')
        ->groupBy('status')
        ->get();

        $tableData = $stats->map(function ($stat) {
            return [
                $stat->status,
                number_format($stat->count),
                Carbon::parse($stat->oldest)->format('Y-m-d'),
                Carbon::parse($stat->newest)->format('Y-m-d'),
                $stat->avg_processing_time ? round($stat->avg_processing_time / 1000, 1) . 's' : 'N/A'
            ];
        })->toArray();

        $this->table(
            ['Status', 'Count', 'Oldest', 'Newest', 'Avg Duration'],
            $tableData
        );

        // Database size information
        $totalSize = DB::select("
            SELECT 
                table_name AS 'table',
                ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'size_mb'
            FROM information_schema.TABLES 
            WHERE table_schema = DATABASE() 
            AND table_name = 'open_ai_job_results'
        ");

        if (!empty($totalSize)) {
            $this->newLine();
            $this->info("💾 Table size: {$totalSize[0]->size_mb} MB");
        }
    }
}