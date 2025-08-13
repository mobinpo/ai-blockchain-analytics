<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

final class OpenAiJobResult extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'job_id',
        'job_type',
        'user_id',
        'status',
        'prompt',
        'response',
        'parsed_response',
        'config',
        'metadata',
        'token_usage',
        'processing_time_ms',
        'streaming_stats',
        'error_message',
        'started_at',
        'completed_at',
        'failed_at',
        'attempts_made'
    ];

    protected $casts = [
        'config' => 'array',
        'metadata' => 'array',
        'parsed_response' => 'array',
        'token_usage' => 'array',
        'streaming_stats' => 'array',
        'processing_time_ms' => 'integer',
        'attempts_made' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime'
    ];

    protected $dates = [
        'started_at',
        'completed_at', 
        'failed_at',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    /**
     * Get the user that owns the job result
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: Get results by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Get results by job type
     */
    public function scopeByJobType($query, string $jobType)
    {
        return $query->where('job_type', $jobType);
    }

    /**
     * Scope: Get recent results
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope: Get completed jobs
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope: Get failed jobs
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope: Get processing jobs
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    /**
     * Check if job is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if job is processing
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Check if job has failed
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Get total tokens used
     */
    public function getTotalTokens(): int
    {
        return $this->token_usage['total_tokens'] ?? 0;
    }

    /**
     * Get estimated cost
     */
    public function getEstimatedCost(): float
    {
        return $this->token_usage['estimated_cost_usd'] ?? 0.0;
    }

    /**
     * Get processing duration in seconds
     */
    public function getProcessingDurationSeconds(): float
    {
        if (!$this->processing_time_ms) {
            return 0.0;
        }
        
        return round($this->processing_time_ms / 1000, 2);
    }

    /**
     * Get tokens per second rate
     */
    public function getTokensPerSecond(): float
    {
        return $this->token_usage['tokens_per_second'] ?? 0.0;
    }

    /**
     * Get success rate percentage
     */
    public function getSuccessRate(): float
    {
        return $this->metadata['success_rate'] ?? 0.0;
    }

    /**
     * Get model used for the job
     */
    public function getModel(): string
    {
        return $this->config['model'] ?? 'unknown';
    }

    /**
     * Get job priority
     */
    public function getPriority(): string
    {
        return $this->config['priority'] ?? 'normal';
    }

    /**
     * Get findings count (for security analysis jobs)
     */
    public function getFindingsCount(): int
    {
        if ($this->job_type !== 'security_analysis' || !$this->parsed_response) {
            return 0;
        }

        return count($this->parsed_response['findings'] ?? []);
    }

    /**
     * Get severity breakdown (for security analysis jobs)
     */
    public function getSeverityBreakdown(): array
    {
        if ($this->job_type !== 'security_analysis' || !$this->parsed_response) {
            return [];
        }

        $breakdown = [
            'CRITICAL' => 0,
            'HIGH' => 0, 
            'MEDIUM' => 0,
            'LOW' => 0,
            'INFO' => 0
        ];

        foreach ($this->parsed_response['findings'] ?? [] as $finding) {
            $severity = $finding['severity'] ?? 'UNKNOWN';
            if (isset($breakdown[$severity])) {
                $breakdown[$severity]++;
            }
        }

        return $breakdown;
    }

    /**
     * Get response summary
     */
    public function getResponseSummary(): array
    {
        return [
            'job_id' => $this->job_id,
            'status' => $this->status,
            'job_type' => $this->job_type,
            'model' => $this->getModel(),
            'total_tokens' => $this->getTotalTokens(),
            'processing_time_seconds' => $this->getProcessingDurationSeconds(),
            'tokens_per_second' => $this->getTokensPerSecond(),
            'estimated_cost_usd' => $this->getEstimatedCost(),
            'success_rate' => $this->getSuccessRate(),
            'response_size_bytes' => strlen($this->response ?? ''),
            'has_structured_response' => !empty($this->parsed_response),
            'created_at' => $this->created_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString()
        ];
    }

    /**
     * Get streaming performance metrics
     */
    public function getStreamingMetrics(): array
    {
        $stats = $this->streaming_stats ?? [];
        
        return [
            'tokens_received' => $stats['tokens_received'] ?? 0,
            'streaming_efficiency' => $this->token_usage['streaming_efficiency'] ?? 0.0,
            'first_token_latency_ms' => $stats['first_token_time_ms'] ?? null,
            'last_token_time' => $stats['updated_at'] ?? null,
            'total_streaming_time_ms' => $stats['processing_time_ms'] ?? 0,
            'average_token_interval_ms' => $this->calculateAverageTokenInterval(),
            'streaming_consistency_score' => $this->calculateStreamingConsistency()
        ];
    }

    /**
     * Calculate average time between tokens
     */
    private function calculateAverageTokenInterval(): float
    {
        $stats = $this->streaming_stats ?? [];
        $totalTokens = $stats['tokens_received'] ?? 0;
        $totalTime = $stats['processing_time_ms'] ?? 0;

        if ($totalTokens <= 1 || $totalTime <= 0) {
            return 0.0;
        }

        return round($totalTime / $totalTokens, 2);
    }

    /**
     * Calculate streaming consistency score (0-1)
     */
    private function calculateStreamingConsistency(): float
    {
        // This could be enhanced with actual token timing data
        // For now, return a basic score based on completion status
        return match($this->status) {
            'completed' => 0.95,
            'processing' => 0.5,
            'failed' => 0.0,
            default => 0.0
        };
    }

    /**
     * Export job data for analysis
     */
    public function toAnalysisArray(): array
    {
        return [
            'job_metadata' => [
                'job_id' => $this->job_id,
                'job_type' => $this->job_type,
                'status' => $this->status,
                'user_id' => $this->user_id,
                'created_at' => $this->created_at?->toISOString(),
                'completed_at' => $this->completed_at?->toISOString()
            ],
            'prompt_data' => [
                'prompt' => $this->prompt,
                'prompt_length' => strlen($this->prompt ?? ''),
                'config' => $this->config
            ],
            'response_data' => [
                'response' => $this->response,
                'response_length' => strlen($this->response ?? ''),
                'parsed_response' => $this->parsed_response,
                'has_structured_data' => !empty($this->parsed_response)
            ],
            'performance_metrics' => [
                'token_usage' => $this->token_usage,
                'processing_time_ms' => $this->processing_time_ms,
                'streaming_stats' => $this->streaming_stats,
                'streaming_metrics' => $this->getStreamingMetrics()
            ],
            'quality_metrics' => [
                'success_rate' => $this->getSuccessRate(),
                'findings_count' => $this->getFindingsCount(),
                'severity_breakdown' => $this->getSeverityBreakdown(),
                'error_message' => $this->error_message
            ]
        ];
    }

    /**
     * Create a new OpenAI job result
     */
    public static function createForJob(
        string $jobId,
        string $jobType,
        string $prompt,
        array $config = [],
        ?int $userId = null
    ): self {
        return self::create([
            'job_id' => $jobId,
            'job_type' => $jobType,
            'user_id' => $userId,
            'status' => 'pending',
            'prompt' => $prompt,
            'config' => $config,
            'metadata' => [
                'created_via' => 'api',
                'created_at' => now()->toISOString()
            ]
        ]);
    }
}