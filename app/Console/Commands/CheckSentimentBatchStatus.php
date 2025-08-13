<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SentimentPipelineService;
use App\Models\SentimentBatch;
use Illuminate\Console\Command;

class CheckSentimentBatchStatus extends Command
{
    protected $signature = 'sentiment:batch-status 
                           {batch-id? : Specific batch ID to check}
                           {--all : Show all recent batches}
                           {--limit=10 : Number of batches to show}
                           {--status= : Filter by status (pending, processing, completed, failed)}';

    protected $description = 'Check the status of sentiment processing batches';

    public function handle(SentimentPipelineService $pipelineService): int
    {
        $batchId = $this->argument('batch-id');

        if ($batchId) {
            return $this->showSpecificBatch($pipelineService, $batchId);
        }

        if ($this->option('all')) {
            return $this->showAllBatches();
        }

        $this->error('❌ Please provide a batch ID or use --all to show recent batches');
        $this->info('💡 Usage: php artisan sentiment:batch-status [batch-id] or --all');
        
        return 1;
    }

    private function showSpecificBatch(SentimentPipelineService $pipelineService, string $batchId): int
    {
        $this->info("🔍 Checking status for batch: {$batchId}");

        $status = $pipelineService->getBatchStatus($batchId);

        if ($status['status'] === 'not_found') {
            $this->error("❌ Batch not found: {$batchId}");
            return 1;
        }

        $this->displayBatchStatus($status);
        return 0;
    }

    private function showAllBatches(): int
    {
        $limit = (int) $this->option('limit');
        $statusFilter = $this->option('status');

        $query = SentimentBatch::orderBy('created_at', 'desc');

        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }

        $batches = $query->limit($limit)->get();

        if ($batches->isEmpty()) {
            $this->info('📭 No batches found');
            return 0;
        }

        $this->info("📋 Recent Sentiment Processing Batches (Last {$limit})");
        
        $tableData = [];
        foreach ($batches as $batch) {
            $tableData[] = [
                $batch->batch_id,
                $this->getStatusIcon($batch->status) . ' ' . ucfirst($batch->status),
                $batch->total_documents ?? 0,
                $batch->processed_documents ?? 0,
                $batch->failed_documents ?? 0,
                $batch->progress_percentage . '%',
                $batch->created_at->format('Y-m-d H:i:s'),
                $batch->duration ? $this->formatDuration($batch->duration) : '-'
            ];
        }

        $this->table(
            ['Batch ID', 'Status', 'Total', 'Processed', 'Failed', 'Progress', 'Created', 'Duration'],
            $tableData
        );

        return 0;
    }

    private function displayBatchStatus(array $status): void
    {
        $this->info('📊 Batch Status Details:');
        
        $tableData = [
            ['Status', $this->getStatusIcon($status['status']) . ' ' . ucfirst($status['status'])],
            ['Progress', $status['progress'] . '%'],
            ['Total Documents', $status['total_documents']],
            ['Processed', $status['processed_documents']],
            ['Failed', $status['failed_documents']],
        ];

        if ($status['started_at']) {
            $tableData[] = ['Started At', $status['started_at']];
        }

        if ($status['completed_at']) {
            $tableData[] = ['Completed At', $status['completed_at']];
            
            $startTime = \Carbon\Carbon::parse($status['started_at']);
            $endTime = \Carbon\Carbon::parse($status['completed_at']);
            $duration = $endTime->diffInSeconds($startTime);
            
            $tableData[] = ['Duration', $this->formatDuration($duration)];
        }

        $this->table(['Property', 'Value'], $tableData);

        // Show processing stats if available
        if (!empty($status['processing_stats'])) {
            $this->newLine();
            $this->info('📈 Processing Statistics:');
            
            $stats = $status['processing_stats'];
            $statsTable = [];
            
            foreach ($stats as $key => $value) {
                $statsTable[] = [ucfirst(str_replace('_', ' ', $key)), $value];
            }
            
            $this->table(['Statistic', 'Value'], $statsTable);
        }
    }

    private function getStatusIcon(string $status): string
    {
        return match($status) {
            'completed' => '✅',
            'processing' => '🔄',
            'failed' => '❌',
            'pending' => '⏳',
            'queued' => '📋',
            default => '❓'
        };
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes < 60) {
            return "{$minutes}m {$remainingSeconds}s";
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        return "{$hours}h {$remainingMinutes}m {$remainingSeconds}s";
    }
} 