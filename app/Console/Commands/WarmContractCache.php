<?php

namespace App\Console\Commands;

use App\Models\CacheWarmingQueue;
use App\Models\ContractCache;
use App\Models\ApiUsageTracking;
use App\Services\SourceCodeFetchingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WarmContractCache extends Command
{
    protected $signature = 'cache:warm-contracts 
                          {--batch-size=10 : Number of contracts to process in each batch}
                          {--network= : Specific network to process}
                          {--priority= : Specific priority to process (high, medium, low)}
                          {--max-runtime=300 : Maximum runtime in seconds}
                          {--dry-run : Show what would be processed without executing}
                          {--pause : Pause the warming queue}
                          {--resume : Resume the warming queue}
                          {--status : Show queue status}';

    protected $description = 'Warm contract cache by processing queued contracts';

    private SourceCodeFetchingService $fetchingService;

    public function __construct(SourceCodeFetchingService $fetchingService)
    {
        parent::__construct();
        $this->fetchingService = $fetchingService;
    }

    public function handle(): int
    {
        $this->info('🔥 Contract Cache Warming Service');
        $this->newLine();

        // Handle queue control commands
        if ($this->option('pause')) {
            return $this->pauseQueue();
        }

        if ($this->option('resume')) {
            return $this->resumeQueue();
        }

        if ($this->option('status')) {
            return $this->showQueueStatus();
        }

        // Check if queue is paused
        if (CacheWarmingQueue::isQueuePaused()) {
            $this->warn('⏸️ Cache warming queue is currently paused');
            $this->comment('Use --resume to resume processing');
            return Command::SUCCESS;
        }

        $batchSize = (int) $this->option('batch-size');
        $maxRuntime = (int) $this->option('max-runtime');
        $dryRun = $this->option('dry-run');
        
        $startTime = time();
        $processed = 0;
        $errors = 0;

        $this->info("Starting cache warming process");
        $this->info("📊 Batch size: {$batchSize}");
        $this->info("⏱️ Max runtime: {$maxRuntime} seconds");
        $this->info("🔍 Dry run: " . ($dryRun ? 'Yes' : 'No'));
        $this->newLine();

        // Reset any stuck processing items
        $resetCount = CacheWarmingQueue::resetStuckItems();
        if ($resetCount > 0) {
            $this->info("🔄 Reset {$resetCount} stuck processing items");
        }

        // Main processing loop
        while ((time() - $startTime) < $maxRuntime) {
            $batch = $this->getNextBatch($batchSize);
            
            if (empty($batch)) {
                $this->info('✅ No more contracts to process');
                break;
            }

            $this->info("📦 Processing batch of " . count($batch) . " contracts");
            
            foreach ($batch as $item) {
                if ((time() - $startTime) >= $maxRuntime) {
                    $this->warn("⏰ Reached maximum runtime limit");
                    break 2;
                }

                $result = $this->processQueueItem($item, $dryRun);
                
                if ($result) {
                    $processed++;
                    $this->line("✅ {$item['contract_address']} ({$item['network']})");
                } else {
                    $errors++;
                    $this->line("❌ {$item['contract_address']} ({$item['network']})");
                }
            }

            // Small delay to respect rate limits
            if (!$dryRun) {
                usleep(500000); // 500ms between batches
            }
        }

        $this->newLine();
        $this->displaySummary($processed, $errors, time() - $startTime);

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function getNextBatch(int $batchSize): array
    {
        $query = CacheWarmingQueue::readyToProcess()
            ->orderBy('priority', 'desc')
            ->orderBy('scheduled_at')
            ->limit($batchSize);

        // Apply filters if specified
        if ($network = $this->option('network')) {
            $query->where('network', $network);
        }

        if ($priority = $this->option('priority')) {
            $query->where('priority', $priority);
        }

        return $query->get()->toArray();
    }

    private function processQueueItem(array $item, bool $dryRun): bool
    {
        $queueItem = CacheWarmingQueue::find($item['id']);
        
        if (!$queueItem) {
            return false;
        }

        if ($dryRun) {
            $this->line("Would process: {$item['contract_address']} ({$item['network']}) - Priority: {$item['priority']}");
            return true;
        }

        try {
            // Mark as processing
            $queueItem->markAsProcessing();

            // Check if already cached and fresh
            $cached = ContractCache::getCachedData(
                $item['network'],
                $item['contract_address'],
                $item['cache_type']
            );

            if ($cached && !$this->needsRefresh($cached)) {
                $this->comment("  Already cached and fresh, skipping");
                $queueItem->markAsCompleted();
                return true;
            }

            // Fetch the source code
            $sourceData = $this->fetchingService->fetchSourceCode(
                $item['contract_address'],
                $item['network'],
                true // Force refresh
            );

            if (!empty($sourceData)) {
                $queueItem->markAsCompleted();
                
                Log::info("Successfully warmed cache for contract", [
                    'address' => $item['contract_address'],
                    'network' => $item['network'],
                    'priority' => $item['priority'],
                    'lines' => $sourceData['statistics']['total_lines'] ?? 0
                ]);
                
                return true;
            } else {
                throw new \Exception('Empty source data returned');
            }

        } catch (\Exception $e) {
            $queueItem->markAsFailed($e->getMessage());
            
            Log::error("Failed to warm cache for contract", [
                'address' => $item['contract_address'],
                'network' => $item['network'],
                'error' => $e->getMessage(),
                'retry_count' => $queueItem->retry_count
            ]);
            
            return false;
        }
    }

    private function needsRefresh(array $cached): bool
    {
        // Check cache age and quality
        $cacheAge = now()->diffInHours($cached['fetched_at'] ?? now());
        $quality = $cached['cache_quality_score'] ?? 1.0;
        
        // Refresh if cache is old or low quality
        return $cacheAge > 24 || $quality < 0.7;
    }

    private function pauseQueue(): int
    {
        CacheWarmingQueue::pauseQueue();
        $this->info('⏸️ Cache warming queue has been paused');
        return Command::SUCCESS;
    }

    private function resumeQueue(): int
    {
        CacheWarmingQueue::resumeQueue();
        $this->info('▶️ Cache warming queue has been resumed');
        return Command::SUCCESS;
    }

    private function showQueueStatus(): int
    {
        $stats = CacheWarmingQueue::getQueueStats();
        
        $this->info('📊 Cache Warming Queue Status');
        $this->newLine();
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Items', $stats['total_items']],
                ['Pending', $stats['pending']],
                ['Processing', $stats['processing']],
                ['Completed', $stats['completed']],
                ['Failed', $stats['failed']],
                ['Success Rate', round($stats['success_rate'], 1) . '%'],
                ['Avg Processing Time', round($stats['average_processing_time_seconds'], 1) . 's'],
                ['Queue Status', CacheWarmingQueue::isQueuePaused() ? '⏸️ Paused' : '▶️ Running']
            ]
        );

        if (!empty($stats['by_priority'])) {
            $this->newLine();
            $this->info('📈 Pending by Priority:');
            foreach ($stats['by_priority'] as $priority => $count) {
                $this->line("  {$priority}: {$count}");
            }
        }

        if (!empty($stats['by_network'])) {
            $this->newLine();
            $this->info('🌐 Pending by Network:');
            foreach ($stats['by_network'] as $network => $count) {
                $this->line("  {$network}: {$count}");
            }
        }

        $estimate = $stats['estimated_queue_time'];
        if ($estimate['pending_items'] > 0) {
            $this->newLine();
            $this->info("⏱️ Estimated completion time: {$estimate['hours']} hours ({$estimate['pending_items']} items)");
        }

        return Command::SUCCESS;
    }

    private function displaySummary(int $processed, int $errors, int $runtime): void
    {
        $this->info('📋 Cache Warming Summary');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Processed', $processed],
                ['Errors', $errors],
                ['Success Rate', $processed > 0 ? round(($processed / ($processed + $errors)) * 100, 1) . '%' : '0%'],
                ['Runtime', $runtime . 's'],
                ['Avg Time per Contract', $processed > 0 ? round($runtime / $processed, 1) . 's' : 'N/A']
            ]
        );

        // Show recent API usage
        $apiStats = ApiUsageTracking::getCurrentRateLimitStatus('ethereum', 'etherscan');
        $this->newLine();
        $this->info('🌐 API Usage Status:');
        $this->line("  Requests last hour: {$apiStats['requests_last_hour']}");
        $this->line("  Requests last minute: {$apiStats['requests_last_minute']}");
        $this->line("  Errors last hour: {$apiStats['errors_last_hour']}");
        $this->line("  Status: " . ($apiStats['estimated_safe'] ? '✅ Safe' : '⚠️ Approaching limits'));
    }
}