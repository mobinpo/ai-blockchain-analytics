<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Analysis extends Model
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

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function findings()
    {
        return $this->hasMany(Finding::class);
    }

    public function sentiments()
    {
        return $this->hasMany(Sentiment::class);
    }

    public function scopeByEngine($query, string $engine)
    {
        return $query->where('engine', $engine);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
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