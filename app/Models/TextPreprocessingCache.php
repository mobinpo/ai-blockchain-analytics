<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class TextPreprocessingCache extends Model
{
    use HasFactory;

    protected $table = 'text_preprocessing_cache';

    protected $fillable = [
        'content_hash',
        'original_text',
        'processed_text',
        'detected_language',
        'preprocessing_steps',
        'last_used_at',
    ];

    protected $casts = [
        'preprocessing_steps' => 'array',
        'last_used_at' => 'datetime',
    ];

    public function scopeOlderThan($query, \Carbon\Carbon $date)
    {
        return $query->where('last_used_at', '<', $date);
    }

    public function getOriginalLengthAttribute(): int
    {
        return strlen($this->original_text);
    }

    public function getProcessedLengthAttribute(): int
    {
        return strlen($this->processed_text);
    }

    public function getCompressionRatioAttribute(): float
    {
        if ($this->original_length === 0) {
            return 0.0;
        }

        return round((1 - ($this->processed_length / $this->original_length)) * 100, 2);
    }
}