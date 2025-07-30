<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function analyses()
    {
        return $this->hasMany(Analysis::class);
    }

    public function findings()
    {
        return $this->hasManyThrough(Finding::class, Analysis::class);
    }

    public function sentiments()
    {
        return $this->hasManyThrough(Sentiment::class, Analysis::class);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function getLatestAnalysisAttribute()
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