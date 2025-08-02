<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'user_id',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function analyses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Analysis::class);
    }

    public function findings(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(Finding::class, Analysis::class);
    }

    public function sentiments(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(Sentiment::class, Analysis::class);
    }

    public function scopeForUser($query, int $userId): mixed
    {
        return $query->where('user_id', $userId);
    }

    public function getLatestAnalysisAttribute(): ?Analysis
    {
        return $this->analyses()->latest()->first();
    }

    public function getCriticalFindingsCountAttribute(): int
    {
        return $this->findings()->where('severity', 'critical')->count();
    }

    public function getAverageSentimentAttribute(): ?float
    {
        return $this->sentiments()->avg('score');
    }
} 