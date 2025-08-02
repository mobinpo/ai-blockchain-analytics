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
    ];

    protected $casts = [
        'payload' => 'array',
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
} 