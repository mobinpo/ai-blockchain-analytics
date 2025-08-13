<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\OpenAiStreamingJob;
use App\Models\OpenAiJobResult;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

class OpenAiBatchProcessor extends Command
{
    protected $signature = 'openai:batch 
                           {file : Path to input file (JSON or text)}
                           {--type=security_analysis : Job type for all items}
                           {--model=gpt-4 : OpenAI model to use}
                           {--priority=normal : Job priority}
                           {--batch-size=10 : Number of concurrent jobs}
                           {--delay=2 : Delay between batches in seconds}
                           {--format=json : Input format (json|text|csv)}
                           {--prompt-template= : Template for prompts with {{input}} placeholder}
                           {--dry-run : Show what would be processed without executing}
                           {--monitor : Monitor batch progress after dispatch}';

    protected $description = 'Process multiple OpenAI jobs in batches from file input';

    public function handle(): int
    {
        $this->displayHeader();

        $filePath = $this->argument('file');
        
        if (!file_exists($filePath)) {
            $this->error("❌ File not found: {$filePath}");
            return Command::FAILURE;
        }

        try {
            $items = $this->parseInputFile($filePath);
            
            if ($items->isEmpty()) {
                $this->error('❌ No items found in input file');
                return Command::FAILURE;
            }

            $this->info("📄 Loaded {$items->count()} items from: {$filePath}");
            $this->newLine();

            if ($this->option('dry-run')) {
                return $this->showDryRun($items);
            }

            return $this->processBatch($items);

        } catch (\Exception $e) {
            $this->error("❌ Error processing file: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function displayHeader(): void
    {
        $this->info('🔄 OpenAI Batch Processor');
        $this->newLine();
    }

    private function parseInputFile(string $filePath): Collection
    {
        $format = $this->option('format');
        $content = file_get_contents($filePath);

        return match($format) {
            'json' => $this->parseJsonFile($content),
            'csv' => $this->parseCsvFile($content),
            'text' => $this->parseTextFile($content),
            default => throw new \InvalidArgumentException("Unsupported format: {$format}")
        };
    }

    private function parseJsonFile(string $content): Collection
    {
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON format');
        }

        // Handle different JSON structures
        if (isset($data['items'])) {
            return collect($data['items']);
        }

        if (isset($data[0])) {
            return collect($data);
        }

        // Single item
        return collect([$data]);
    }

    private function parseCsvFile(string $content): Collection
    {
        $lines = array_filter(explode("\n", trim($content)));
        $header = str_getcsv(array_shift($lines));
        
        return collect($lines)->map(function ($line) use ($header) {
            $values = str_getcsv($line);
            return array_combine($header, $values);
        });
    }

    private function parseTextFile(string $content): Collection
    {
        $lines = array_filter(explode("\n", trim($content)));
        
        return collect($lines)->map(function ($line, $index) {
            return [
                'id' => $index + 1,
                'content' => trim($line)
            ];
        });
    }

    private function showDryRun(Collection $items): int
    {
        $this->warn('🔍 DRY RUN MODE - Jobs will not be executed');
        $this->newLine();

        $batchSize = (int) $this->option('batch-size');
        $batches = $items->chunk($batchSize);
        
        $this->info("📊 Batch Configuration:");
        $this->table(
            ['Setting', 'Value'],
            [
                ['Total Items', $items->count()],
                ['Batch Size', $batchSize],
                ['Number of Batches', $batches->count()],
                ['Job Type', $this->option('type')],
                ['Model', $this->option('model')],
                ['Priority', $this->option('priority')],
                ['Delay Between Batches', $this->option('delay') . 's'],
            ]
        );

        $this->newLine();
        $this->info('📋 Sample Items:');
        
        $sampleItems = $items->take(5)->map(function ($item, $index) {
            $prompt = $this->buildPrompt($item);
            return [
                $index + 1,
                substr($this->getItemIdentifier($item), 0, 30) . '...',
                substr($prompt, 0, 60) . '...'
            ];
        });

        $this->table(
            ['#', 'Identifier', 'Prompt Preview'],
            $sampleItems->toArray()
        );

        if ($items->count() > 5) {
            $this->info("... and " . ($items->count() - 5) . " more items");
        }

        return Command::SUCCESS;
    }

    private function processBatch(Collection $items): int
    {
        $batchSize = (int) $this->option('batch-size');
        $delay = (int) $this->option('delay');
        $batches = $items->chunk($batchSize);
        
        $this->info("🚀 Starting batch processing:");
        $this->info("  - Total items: {$items->count()}");
        $this->info("  - Batches: {$batches->count()}");
        $this->info("  - Batch size: {$batchSize}");
        $this->newLine();

        $batchId = 'batch_' . Str::random(8);
        $totalJobs = 0;
        $jobIds = [];

        foreach ($batches as $batchNumber => $batch) {
            $this->info("📦 Processing batch " . ($batchNumber + 1) . "/" . $batches->count());
            
            $batchJobIds = $this->processBatchItems($batch, $batchId, $batchNumber + 1);
            $jobIds = array_merge($jobIds, $batchJobIds);
            $totalJobs += count($batchJobIds);
            
            $this->info("  ✅ Dispatched " . count($batchJobIds) . " jobs");

            // Delay between batches (except for the last one)
            if ($batchNumber < $batches->count() - 1) {
                $this->info("  ⏳ Waiting {$delay}s before next batch...");
                sleep($delay);
            }
        }

        $this->newLine();
        $this->info("🎉 Batch processing complete!");
        $this->info("  - Total jobs dispatched: {$totalJobs}");
        $this->info("  - Batch ID: {$batchId}");

        // Store batch metadata
        $this->storeBatchMetadata($batchId, $jobIds, $items->count());

        if ($this->option('monitor')) {
            $this->newLine();
            return $this->monitorBatchProgress($batchId, $jobIds);
        }

        $this->newLine();
        $this->info("💡 Monitor progress with: php artisan openai:monitor --live");
        $this->info("💡 Check batch status with: php artisan openai:batch-status {$batchId}");

        return Command::SUCCESS;
    }

    private function processBatchItems(Collection $items, string $batchId, int $batchNumber): array
    {
        $jobIds = [];
        
        foreach ($items as $index => $item) {
            $jobId = $this->createJobForItem($item, $batchId, $batchNumber, $index + 1);
            $jobIds[] = $jobId;
        }

        return $jobIds;
    }

    private function createJobForItem($item, string $batchId, int $batchNumber, int $itemIndex): string
    {
        $jobId = "{$batchId}_b{$batchNumber}_i{$itemIndex}";
        $prompt = $this->buildPrompt($item);
        
        $config = [
            'model' => $this->option('model'),
            'priority' => $this->option('priority'),
            'max_tokens' => 2000,
            'temperature' => 0.7,
        ];

        $metadata = [
            'batch_id' => $batchId,
            'batch_number' => $batchNumber,
            'item_index' => $itemIndex,
            'item_identifier' => $this->getItemIdentifier($item),
            'created_via' => 'batch_processor',
        ];

        $job = new OpenAiStreamingJob(
            prompt: $prompt,
            jobId: $jobId,
            config: $config,
            metadata: $metadata,
            jobType: $this->option('type')
        );

        dispatch($job);
        
        return $jobId;
    }

    private function buildPrompt($item): string
    {
        $template = $this->option('prompt-template');
        
        if ($template) {
            $content = $this->getItemContent($item);
            return str_replace('{{input}}', $content, $template);
        }

        // Default prompt based on job type
        $content = $this->getItemContent($item);
        $jobType = $this->option('type');

        return match($jobType) {
            'security_analysis' => "Analyze the following smart contract code for security vulnerabilities:\n\n{$content}",
            'code_analysis' => "Review the following code for quality issues and improvements:\n\n{$content}",
            'sentiment_analysis' => "Analyze the sentiment of the following text:\n\n{$content}",
            default => $content
        };
    }

    private function getItemContent($item): string
    {
        if (is_string($item)) {
            return $item;
        }

        if (is_array($item)) {
            return $item['content'] ?? $item['text'] ?? $item['code'] ?? json_encode($item);
        }

        return (string) $item;
    }

    private function getItemIdentifier($item): string
    {
        if (is_array($item)) {
            return $item['id'] ?? $item['name'] ?? $item['title'] ?? 'item';
        }

        return substr((string) $item, 0, 20);
    }

    private function storeBatchMetadata(string $batchId, array $jobIds, int $totalItems): void
    {
        $metadata = [
            'batch_id' => $batchId,
            'total_items' => $totalItems,
            'total_jobs' => count($jobIds),
            'job_ids' => $jobIds,
            'created_at' => now()->toISOString(),
            'configuration' => [
                'type' => $this->option('type'),
                'model' => $this->option('model'),
                'priority' => $this->option('priority'),
                'batch_size' => $this->option('batch-size'),
                'delay' => $this->option('delay'),
            ]
        ];

        // Store in cache for monitoring
        cache()->put("batch_metadata_{$batchId}", $metadata, 86400); // 24 hours
    }

    private function monitorBatchProgress(string $batchId, array $jobIds): int
    {
        $this->info("📊 Monitoring batch progress for: {$batchId}");
        $this->info("Press Ctrl+C to stop monitoring");
        $this->newLine();

        $maxIterations = 120; // 10 minutes max
        $iteration = 0;

        while ($iteration < $maxIterations) {
            $iteration++;
            
            $stats = $this->getBatchStats($jobIds);
            
            // Clear screen and display current status
            if ($iteration > 1) {
                $this->output->write("\033[2J\033[H"); // Clear screen
            }
            
            $this->displayBatchStats($batchId, $stats, $iteration);
            
            // Check if all jobs are completed
            if ($stats['completed'] + $stats['failed'] >= $stats['total']) {
                $this->newLine();
                $this->info("🎉 All batch jobs completed!");
                $this->displayFinalBatchSummary($stats);
                break;
            }

            sleep(5); // Update every 5 seconds
        }

        return Command::SUCCESS;
    }

    private function getBatchStats(array $jobIds): array
    {
        $jobs = OpenAiJobResult::whereIn('job_id', $jobIds)->get();
        
        return [
            'total' => count($jobIds),
            'completed' => $jobs->where('status', 'completed')->count(),
            'failed' => $jobs->where('status', 'failed')->count(),
            'processing' => $jobs->where('status', 'processing')->count(),
            'pending' => count($jobIds) - $jobs->count(),
            'success_rate' => $jobs->count() > 0 ? round(($jobs->where('status', 'completed')->count() / $jobs->count()) * 100, 1) : 0,
            'total_tokens' => $jobs->where('status', 'completed')->sum(function ($job) {
                return $job->token_usage['total_tokens'] ?? 0;
            }),
            'total_cost' => $jobs->where('status', 'completed')->sum(function ($job) {
                return $job->token_usage['estimated_cost_usd'] ?? 0;
            }),
        ];
    }

    private function displayBatchStats(string $batchId, array $stats, int $iteration): void
    {
        $timestamp = now()->format('H:i:s');
        $this->info("📊 Batch Monitor #{$iteration} - {$timestamp}");
        $this->info("Batch ID: {$batchId}");
        $this->newLine();

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
        $this->info("📈 Performance Metrics:");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Success Rate', $stats['success_rate'] . '%'],
                ['Total Tokens', number_format($stats['total_tokens'])],
                ['Estimated Cost', '$' . number_format($stats['total_cost'], 4)],
                ['Progress', $stats['completed'] + $stats['failed'] . '/' . $stats['total']],
            ]
        );
    }

    private function displayFinalBatchSummary(array $stats): void
    {
        $this->newLine();
        $this->info("📋 Final Batch Summary:");
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Jobs', $stats['total']],
                ['Successful', $stats['completed'] . ' (' . round(($stats['completed'] / $stats['total']) * 100, 1) . '%)'],
                ['Failed', $stats['failed'] . ' (' . round(($stats['failed'] / $stats['total']) * 100, 1) . '%)'],
                ['Total Tokens', number_format($stats['total_tokens'])],
                ['Total Cost', '$' . number_format($stats['total_cost'], 4)],
                ['Avg Cost per Job', '$' . number_format($stats['total_cost'] / max($stats['completed'], 1), 4)],
            ]
        );
    }
}