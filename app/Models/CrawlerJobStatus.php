<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrawlerJobStatus extends Model
{
    use HasFactory;

    protected $table = 'crawler_job_status';

    protected $fillable = [
        'platform',
        'job_type',
        'last_run_at',
        'next_run_at',
        'posts_collected',
        'last_error',
        'status',
    ];

    protected $casts = [
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
        'last_error' => 'array',
    ];

    public function scopeByPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    public function scopeDue($query)
    {
        return $query->where('next_run_at', '<=', now())
                    ->whereNotIn('status', ['running']);
    }

    public function getLastErrorMessageAttribute(): ?string
    {
        return $this->last_error['message'] ?? null;
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->next_run_at && $this->next_run_at->isPast() && $this->status !== 'running';
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'completed' => 'green',
            'running' => 'blue',
            'failed' => 'red',
            'pending' => 'yellow',
            default => 'gray'
        };
    }
}