<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SentimentBatchDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'sentiment_batch_id',
        'source_type',
        'source_id',
        'processed_text',
        'detected_language',
        'sentiment_score',
        'magnitude',
        'entities',
        'categories',
        'processing_status',
        'error_details',
    ];

    protected $casts = [
        'sentiment_score' => 'decimal:2',
        'magnitude' => 'decimal:2',
        'entities' => 'array',
        'categories' => 'array',
        'error_details' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(SentimentBatch::class, 'sentiment_batch_id');
    }

    public function socialMediaPost(): BelongsTo
    {
        return $this->belongsTo(SocialMediaPost::class, 'source_id');
    }

    public function sourceModel()
    {
        return match($this->source_type) {
            'social_media_post' => $this->belongsTo(SocialMediaPost::class, 'source_id'),
            default => null
        };
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('processing_status', $status);
    }

    public function scopeProcessed($query)
    {
        return $query->where('processing_status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('processing_status', 'failed');
    }

    public function getSentimentLabelAttribute(): string
    {
        if (is_null($this->sentiment_score)) {
            return 'unknown';
        }

        return match(true) {
            $this->sentiment_score > 0.6 => 'very_positive',
            $this->sentiment_score > 0.2 => 'positive',
            $this->sentiment_score > -0.2 => 'neutral',
            $this->sentiment_score > -0.6 => 'negative',
            default => 'very_negative'
        };
    }

    public function getSentimentColorAttribute(): string
    {
        return match($this->sentiment_label) {
            'very_positive' => 'green',
            'positive' => 'lime',
            'neutral' => 'gray',
            'negative' => 'orange',
            'very_negative' => 'red',
            default => 'gray'
        };
    }

    public function markAsCompleted(float $sentimentScore, float $magnitude, array $additionalData = []): void
    {
        $this->update(array_merge([
            'processing_status' => 'completed',
            'sentiment_score' => $sentimentScore,
            'magnitude' => $magnitude,
        ], $additionalData));
    }

    public function markAsFailed(array $errorDetails): void
    {
        $this->update([
            'processing_status' => 'failed',
            'error_details' => $errorDetails,
        ]);
    }

    public function getEntitiesCountAttribute(): int
    {
        return count($this->entities ?? []);
    }

    public function getCategoriesCountAttribute(): int
    {
        return count($this->categories ?? []);
    }
}