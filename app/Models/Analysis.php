<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class Analysis extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'engine',
        'status',
        'payload',
        'job_id',
        'openai_model',
        'token_limit',
        'temperature',
        'tokens_used',
        'tokens_streamed',
        'streaming_started_at',
        'streaming_completed_at',
        'streaming_metadata',
        'raw_openai_response',
        'structured_result',
        'stream_duration_ms',
        'tokens_per_second',
        'stream_interruptions',
        'analysis_type',
        'target_type',
        'target_address',
        'configuration',
        'version',
        'priority',
        'started_at',
        'completed_at',
        'failed_at',
        'duration_seconds',
        'error_message',
        'error_details',
        'findings_count',
        'critical_findings_count',
        'high_findings_count',
        'sentiment_score',
        'risk_score',
        'gas_analyzed',
        'transactions_analyzed',
        'contracts_analyzed',
        'bytes_analyzed',
        'metadata',
        'tags',
        'triggered_by',
        'triggered_by_user_id',
        'verified',
        'verified_by_user_id',
        'verified_at',
        'verification_notes',
        'archived',
        'archived_at',
        'expires_at'
    ];

    protected $casts = [
        'payload' => 'array',
        'streaming_metadata' => 'array',
        'structured_result' => 'array',
        'configuration' => 'array',
        'error_details' => 'array',
        'metadata' => 'array',
        'tags' => 'array',
        'streaming_started_at' => 'datetime',
        'streaming_completed_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'verified_at' => 'datetime',
        'archived_at' => 'datetime',
        'expires_at' => 'datetime',
        'verified' => 'boolean',
        'archived' => 'boolean',
    ];

    public function project(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function findings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Finding::class);
    }

    public function sentiments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Sentiment::class);
    }

    public function scopeByEngine($query, string $engine): mixed
    {
        return $query->where('engine', $engine);
    }

    public function scopeByStatus($query, string $status): mixed
    {
        return $query->where('status', $status);
    }

    public function scopeCompleted($query): mixed
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query): mixed
    {
        return $query->where('status', 'pending');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isStreaming(): bool
    {
        return $this->streaming_started_at && !$this->streaming_completed_at;
    }

    public function getStreamingProgress(): ?float
    {
        if (!$this->token_limit || !$this->tokens_streamed) {
            return null;
        }
        
        return min(100, ($this->tokens_streamed / $this->token_limit) * 100);
    }

    public function getStreamingDuration(): ?int
    {
        if (!$this->streaming_started_at) {
            return null;
        }
        
        $end = $this->streaming_completed_at ?? now();
        return $this->streaming_started_at->diffInSeconds($end);
    }

    public function hasOpenAiResults(): bool
    {
        return !empty($this->raw_openai_response) || !empty($this->structured_result);
    }

    public function scopeStreaming($query): mixed
    {
        return $query->whereNotNull('streaming_started_at')
                    ->whereNull('streaming_completed_at');
    }

    public function scopeByJobId($query, string $jobId): mixed
    {
        return $query->where('job_id', $jobId);
    }

    public function scopeOpenAi($query): mixed
    {
        return $query->where('engine', 'openai');
    }

    public function getResultSummary(): ?string
    {
        if ($this->structured_result && isset($this->structured_result['summary'])) {
            return $this->structured_result['summary'];
        }
        
        if ($this->raw_openai_response) {
            return \Illuminate\Support\Str::limit($this->raw_openai_response, 200);
        }
        
        return null;
    }

    public function getSecurityFindings(): array
    {
        return $this->structured_result['findings'] ?? [];
    }

    public function getRecommendations(): array
    {
        return $this->structured_result['recommendations'] ?? [];
    }
} 