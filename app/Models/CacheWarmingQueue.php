<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

final class CacheWarmingQueue extends Model
{
    protected $table = 'cache_warming_queue';

    protected $fillable = [
        'network',
        'contract_address',
        'cache_type',
        'priority',
        'status',
        'scheduled_at',
        'started_at',
        'completed_at',
        'retry_count',
        'error_message',
        'metadata'
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'retry_count' => 'integer',
        'metadata' => 'array'
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_CRITICAL = 'critical';

    public const CACHE_TYPE_SOURCE = 'source';
    public const CACHE_TYPE_ABI = 'abi';
    public const CACHE_TYPE_CREATION = 'creation';

    /**
     * Queue contract for cache warming
     */
    public static function queueContract(
        string $network,
        string $contractAddress,
        string $cacheType,
        string $priority = self::PRIORITY_MEDIUM,
        ?Carbon $scheduledAt = null,
        ?array $metadata = null
    ): self {
        return self::updateOrCreate(
            [
                'network' => $network,
                'contract_address' => strtolower($contractAddress),
                'cache_type' => $cacheType
            ],
            [
                'priority' => $priority,
                'status' => self::STATUS_PENDING,
                'scheduled_at' => $scheduledAt ?? now(),
                'retry_count' => 0,
                'error_message' => null,
                'metadata' => $metadata
            ]
        );
    }

    /**
     * Queue multiple contracts for warming
     */
    public static function queueMultipleContracts(
        array $contracts,
        string $network,
        array $cacheTypes = [self::CACHE_TYPE_SOURCE],
        string $priority = self::PRIORITY_MEDIUM
    ): int {
        $queued = 0;
        
        foreach ($contracts as $contractAddress) {
            foreach ($cacheTypes as $cacheType) {
                self::queueContract($network, $contractAddress, $cacheType, $priority);
                $queued++;
            }
        }
        
        return $queued;
    }

    /**
     * Get next batch of contracts to process
     */
    public static function getNextBatch(int $batchSize = 10): array
    {
        return self::where('status', self::STATUS_PENDING)
            ->where(function (Builder $query) {
                $query->whereNull('scheduled_at')
                      ->orWhere('scheduled_at', '<=', now());
            })
            ->orderBy('priority', 'desc')
            ->orderBy('scheduled_at')
            ->limit($batchSize)
            ->get()
            ->toArray();
    }

    /**
     * Mark item as processing
     */
    public function markAsProcessing(): bool
    {
        return $this->update([
            'status' => self::STATUS_PROCESSING,
            'started_at' => now()
        ]);
    }

    /**
     * Mark item as completed
     */
    public function markAsCompleted(): bool
    {
        return $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now()
        ]);
    }

    /**
     * Mark item as failed and potentially retry
     */
    public function markAsFailed(string $errorMessage, bool $shouldRetry = true): bool
    {
        $maxRetries = 3;
        $this->increment('retry_count');
        
        if ($shouldRetry && $this->retry_count < $maxRetries) {
            // Schedule retry with exponential backoff
            $retryDelay = pow(2, $this->retry_count) * 5; // 5, 10, 20 minutes
            
            return $this->update([
                'status' => self::STATUS_PENDING,
                'scheduled_at' => now()->addMinutes($retryDelay),
                'error_message' => $errorMessage,
                'started_at' => null
            ]);
        } else {
            return $this->update([
                'status' => self::STATUS_FAILED,
                'error_message' => $errorMessage,
                'completed_at' => now()
            ]);
        }
    }

    /**
     * Get queue statistics
     */
    public static function getQueueStats(): array
    {
        $total = self::count();
        $pending = self::where('status', self::STATUS_PENDING)->count();
        $processing = self::where('status', self::STATUS_PROCESSING)->count();
        $completed = self::where('status', self::STATUS_COMPLETED)->count();
        $failed = self::where('status', self::STATUS_FAILED)->count();
        
        $byPriority = self::selectRaw('priority, COUNT(*) as count')
            ->where('status', self::STATUS_PENDING)
            ->groupBy('priority')
            ->pluck('count', 'priority');
            
        $byNetwork = self::selectRaw('network, COUNT(*) as count')
            ->where('status', self::STATUS_PENDING)
            ->groupBy('network')
            ->pluck('count', 'network');

        $averageProcessingTime = self::where('status', self::STATUS_COMPLETED)
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (completed_at - started_at))) as avg_seconds')
            ->value('avg_seconds');

        return [
            'total_items' => $total,
            'pending' => $pending,
            'processing' => $processing,
            'completed' => $completed,
            'failed' => $failed,
            'success_rate' => $total > 0 ? (($completed / $total) * 100) : 0,
            'by_priority' => $byPriority->toArray(),
            'by_network' => $byNetwork->toArray(),
            'average_processing_time_seconds' => round($averageProcessingTime ?? 0, 2),
            'estimated_queue_time' => self::estimateQueueTime()
        ];
    }

    /**
     * Estimate total queue processing time
     */
    private static function estimateQueueTime(): array
    {
        $pending = self::where('status', self::STATUS_PENDING)->count();
        $averageTime = self::where('status', self::STATUS_COMPLETED)
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (completed_at - started_at))) as avg_seconds')
            ->value('avg_seconds') ?? 30; // Default 30 seconds per item

        $estimatedSeconds = $pending * $averageTime;
        
        return [
            'total_seconds' => round($estimatedSeconds),
            'hours' => round($estimatedSeconds / 3600, 1),
            'pending_items' => $pending
        ];
    }

    /**
     * Clean up old completed/failed items
     */
    public static function cleanupOldItems(int $daysToKeep = 7): int
    {
        $cutoffDate = now()->subDays($daysToKeep);
        
        return self::whereIn('status', [self::STATUS_COMPLETED, self::STATUS_FAILED])
            ->where('completed_at', '<', $cutoffDate)
            ->delete();
    }

    /**
     * Reset stuck processing items
     */
    public static function resetStuckItems(int $timeoutMinutes = 30): int
    {
        $timeoutTime = now()->subMinutes($timeoutMinutes);
        
        return self::where('status', self::STATUS_PROCESSING)
            ->where('started_at', '<', $timeoutTime)
            ->update([
                'status' => self::STATUS_PENDING,
                'started_at' => null
            ]);
    }

    /**
     * Pause/resume queue processing
     */
    public static function pauseQueue(): bool
    {
        return cache()->put('cache_warming_paused', true, now()->addHours(24));
    }

    public static function resumeQueue(): bool
    {
        return cache()->forget('cache_warming_paused');
    }

    public static function isQueuePaused(): bool
    {
        return cache()->has('cache_warming_paused');
    }

    /**
     * Get priority order for sorting
     */
    public function getPriorityOrder(): int
    {
        return match ($this->priority) {
            self::PRIORITY_CRITICAL => 1,
            self::PRIORITY_HIGH => 2,
            self::PRIORITY_MEDIUM => 3,
            self::PRIORITY_LOW => 4,
            default => 5
        };
    }

    /**
     * Scope for pending items ready to process
     */
    public function scopeReadyToProcess(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where(function (Builder $q) {
                $q->whereNull('scheduled_at')
                  ->orWhere('scheduled_at', '<=', now());
            });
    }

    /**
     * Scope for high priority items
     */
    public function scopeHighPriority(Builder $query): Builder
    {
        return $query->whereIn('priority', [self::PRIORITY_HIGH, self::PRIORITY_CRITICAL]);
    }
}