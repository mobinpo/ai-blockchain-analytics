<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\OpenAiJobResult;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class OpenAiBatchStatus extends Command
{
    protected $signature = 'openai:batch-status 
                           {batch-id : The batch ID to check}
                           {--detailed : Show detailed job information}
                           {--export= : Export results to file (json|csv)}';

    protected $description = 'Check the status of a batch processing operation';

    public function handle(): int
    {
        $batchId = $this->argument('batch-id');
        
        $this->displayHeader($batchId);

        $metadata = Cache::get("batch_metadata_{$batchId}");
        
        if (!$metadata) {
            $this->error("❌ Batch metadata not found for: {$batchId}");
            $this->info("💡 Batch may be older than 24 hours or doesn't exist");
            return Command::FAILURE;
        }

        $this->displayBatchInfo($metadata);
        $this->newLine();

        $stats = $this->getBatchStats($metadata['job_ids']);
        $this->displayBatchStats($stats);
        
        if ($this->option('detailed')) {
            $this->newLine();
            $this->displayDetailedJobs($metadata['job_ids']);
        }

        if ($exportFormat = $this->option('export')) {
            $this->exportResults($batchId, $metadata, $stats, $exportFormat);
        }

        return Command::SUCCESS;
    }

    private function displayHeader(string $batchId): void
    {
        $this->info("📊 Batch Status Report");
        $this->info("Batch ID: {$batchId}");
        $this->newLine();
    }

    private function displayBatchInfo(array $metadata): void
    {
        $this->info('📋 Batch Information:');
        
        $this->table(
            ['Property', 'Value'],
            [
                ['Batch ID', $metadata['batch_id']],
                ['Total Items', $metadata['total_items']],
                ['Total Jobs', $metadata['total_jobs']],
                ['Created At', $metadata['created_at']],
                ['Job Type', $metadata['configuration']['type']],
                ['Model', $metadata['configuration']['model']],
                ['Priority', $metadata['configuration']['priority']],
                ['Batch Size', $metadata['configuration']['batch_size']],
            ]
        );
    }

    private function getBatchStats(array $jobIds): array
    {
        $jobs = OpenAiJobResult::whereIn('job_id', $jobIds)->get();
        
        $stats = [
            'total' => count($jobIds),
            'completed' => $jobs->where('status', 'completed')->count(),
            'failed' => $jobs->where('status', 'failed')->count(),
            'processing' => $jobs->where('status', 'processing')->count(),
            'pending' => count($jobIds) - $jobs->count(),
        ];

        $completedJobs = $jobs->where('status', 'completed');
        
        $stats['success_rate'] = $jobs->count() > 0 ? round(($stats['completed'] / $jobs->count()) * 100, 1) : 0;
        $stats['total_tokens'] = $completedJobs->sum(function ($job) {
            return $job->token_usage['total_tokens'] ?? 0;
        });
        $stats['total_cost'] = $completedJobs->sum(function ($job) {
            return $job->token_usage['estimated_cost_usd'] ?? 0;
        });
        $stats['avg_processing_time'] = $completedJobs->avg('processing_time_ms') / 1000;
        $stats['min_processing_time'] = $completedJobs->min('processing_time_ms') / 1000;
        $stats['max_processing_time'] = $completedJobs->max('processing_time_ms') / 1000;

        return $stats;
    }

    private function displayBatchStats(array $stats): void
    {
        $this->info('📊 Batch Statistics:');
        
        // Status breakdown
        $this->table(
            ['Status', 'Count', 'Percentage'],
            [
                ['✅ Completed', $stats['completed'], round(($stats['completed'] / $stats['total']) * 100, 1) . '%'],
                ['🔄 Processing', $stats['processing'], round(($stats['processing'] / $stats['total']) * 100, 1) . '%'],
                ['⏳ Pending', $stats['pending'], round(($stats['pending'] / $stats['total']) * 100, 1) . '%'],
                ['❌ Failed', $stats['failed'], round(($stats['failed'] / $stats['total']) * 100, 1) . '%'],
            ]
        );

        $this->newLine();
        
        // Performance metrics
        $this->table(
            ['Metric', 'Value'],
            [
                ['Success Rate', $stats['success_rate'] . '%'],
                ['Total Tokens', number_format($stats['total_tokens'])],
                ['Estimated Cost', '$' . number_format($stats['total_cost'], 4)],
                ['Avg Cost per Job', '$' . number_format($stats['total_cost'] / max($stats['completed'], 1), 4)],
                ['Avg Processing Time', round($stats['avg_processing_time'], 2) . 's'],
                ['Min Processing Time', round($stats['min_processing_time'], 2) . 's'],
                ['Max Processing Time', round($stats['max_processing_time'], 2) . 's'],
            ]
        );
    }

    private function displayDetailedJobs(array $jobIds): void
    {
        $this->info('🔍 Detailed Job Information:');
        
        $jobs = OpenAiJobResult::whereIn('job_id', $jobIds)
            ->orderBy('created_at')
            ->get();

        if ($jobs->isEmpty()) {
            $this->warn('No job records found in database yet');
            return;
        }

        $tableData = $jobs->map(function ($job) {
            $metadata = $job->metadata ?? [];
            return [
                substr($job->job_id, -12), // Last 12 chars
                $metadata['batch_number'] ?? 'N/A',
                $metadata['item_index'] ?? 'N/A',
                $job->status,
                $job->getTotalTokens(),
                '$' . number_format($job->getEstimatedCost(), 4),
                round($job->getProcessingDurationSeconds(), 1) . 's',
                $job->created_at->format('H:i:s'),
            ];
        })->toArray();

        $this->table(
            ['Job ID (suffix)', 'Batch#', 'Item#', 'Status', 'Tokens', 'Cost', 'Duration', 'Created'],
            $tableData
        );

        // Failed jobs details
        $failedJobs = $jobs->where('status', 'failed');
        if ($failedJobs->isNotEmpty()) {
            $this->newLine();
            $this->error('❌ Failed Jobs Details:');
            
            foreach ($failedJobs as $job) {
                $this->line("Job: {$job->job_id}");
                $this->line("Error: " . substr($job->error_message, 0, 100) . '...');
                $this->line("Time: " . $job->failed_at?->format('Y-m-d H:i:s'));
                $this->newLine();
            }
        }
    }

    private function exportResults(string $batchId, array $metadata, array $stats, string $format): void
    {
        $this->info("📤 Exporting results as {$format}...");
        
        $filename = "batch_report_{$batchId}." . $format;
        
        try {
            $jobs = OpenAiJobResult::whereIn('job_id', $metadata['job_ids'])->get();
            
            if ($format === 'json') {
                $this->exportAsJson($filename, $metadata, $stats, $jobs);
            } elseif ($format === 'csv') {
                $this->exportAsCsv($filename, $jobs);
            } else {
                $this->error("❌ Unsupported export format: {$format}");
                return;
            }
            
            $this->info("✅ Results exported to: {$filename}");
            
        } catch (\Exception $e) {
            $this->error("❌ Export failed: " . $e->getMessage());
        }
    }

    private function exportAsJson(string $filename, array $metadata, array $stats, $jobs): void
    {
        $data = [
            'batch_metadata' => $metadata,
            'statistics' => $stats,
            'jobs' => $jobs->map(function ($job) {
                return [
                    'job_id' => $job->job_id,
                    'status' => $job->status,
                    'job_type' => $job->job_type,
                    'created_at' => $job->created_at?->toISOString(),
                    'completed_at' => $job->completed_at?->toISOString(),
                    'processing_time_ms' => $job->processing_time_ms,
                    'token_usage' => $job->token_usage,
                    'metadata' => $job->metadata,
                    'error_message' => $job->error_message,
                ];
            })->toArray(),
            'exported_at' => now()->toISOString(),
        ];

        file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
    }

    private function exportAsCsv(string $filename, $jobs): void
    {
        $handle = fopen($filename, 'w');
        
        // CSV headers
        fputcsv($handle, [
            'Job ID',
            'Status', 
            'Job Type',
            'Batch Number',
            'Item Index',
            'Created At',
            'Completed At',
            'Processing Time (s)',
            'Total Tokens',
            'Estimated Cost',
            'Error Message'
        ]);

        // CSV data
        foreach ($jobs as $job) {
            $metadata = $job->metadata ?? [];
            fputcsv($handle, [
                $job->job_id,
                $job->status,
                $job->job_type,
                $metadata['batch_number'] ?? '',
                $metadata['item_index'] ?? '',
                $job->created_at?->toISOString(),
                $job->completed_at?->toISOString(),
                round($job->getProcessingDurationSeconds(), 2),
                $job->getTotalTokens(),
                $job->getEstimatedCost(),
                $job->error_message ?? ''
            ]);
        }

        fclose($handle);
    }
}