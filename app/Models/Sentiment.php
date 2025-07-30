<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sentiment extends Model
{
    use HasFactory;

    protected $fillable = [
        'analysis_id',
        'score',
        'magnitude',
        'details',
    ];

    protected $casts = [
        'details' => 'array',
        'score' => 'decimal:2',
        'magnitude' => 'decimal:2',
    ];

    public function analysis()
    {
        return $this->belongsTo(Analysis::class);
    }

    public function scopePositive($query)
    {
        return $query->where('score', '>', 0);
    }

    public function scopeNegative($query)
    {
        return $query->where('score', '<', 0);
    }

    public function scopeNeutral($query)
    {
        return $query->where('score', 0);
    }

    public function getSentimentLabelAttribute(): string
    {
        if ($this->score > 0.1) {
            return 'Positive';
        } elseif ($this->score < -0.1) {
            return 'Negative';
        } else {
            return 'Neutral';
        }
    }

    public function isPositive(): bool
    {
        return $this->score > 0.1;
    }

    public function isNegative(): bool
    {
        return $this->score < -0.1;
    }

    public function isNeutral(): bool
    {
        return abs($this->score) <= 0.1;
    }
} 