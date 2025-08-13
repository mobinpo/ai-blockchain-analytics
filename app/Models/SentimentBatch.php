<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SentimentBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'processing_date',
        'batch_id',
        'status',
        'total_documents',
        'processed_documents',
        'failed_documents',
        'processing_stats',
        'error_details',
        'started_at',
        'completed_at',
        'processing_cost',
    ];

    protected $casts = [
        'processing_date' => 'date',
        'processing_stats' => 'array',
        'error_details' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'processing_cost' => 'decimal:4',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(SentimentBatchDocument::class);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function getProgressPercentageAttribute(): int
    {
        if ($this->total_documents === 0) {
            return 0;
        }

        return (int) (($this->processed_documents / $this->total_documents) * 100);
    }

    public function getSuccessRateAttribute(): float
    {
        if ($this->processed_documents === 0) {
            return 0.0;
        }

        $successful = $this->processed_documents - $this->failed_documents;
        return round(($successful / $this->processed_documents) * 100, 2);
    }

    public function getDurationAttribute(): ?int
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }

        return $this->completed_at->diffInSeconds($this->started_at);
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'completed' => 'green',
            'processing' => 'blue',
            'failed' => 'red',
            'pending' => 'yellow',
            default => 'gray'
        };
    }

    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(array $stats = []): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'processing_stats' => array_merge($this->processing_stats ?? [], $stats),
        ]);
    }

    public function markAsFailed(array $errorDetails = []): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_details' => $errorDetails,
        ]);
    }

    public function incrementProcessed(): void
    {
        $this->increment('processed_documents');
    }

    public function incrementFailed(): void
    {
        $this->increment('failed_documents');
        $this->increment('processed_documents');
    }
}