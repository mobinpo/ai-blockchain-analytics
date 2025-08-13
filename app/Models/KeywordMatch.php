<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KeywordMatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'social_post_id',
        'keyword_rule_id',
        'matched_keyword',
        'match_count',
        'match_positions',
        'confidence_score',
    ];

    protected $casts = [
        'match_positions' => 'array',
        'confidence_score' => 'decimal:3',
        'match_count' => 'integer',
    ];

    public function socialPost(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class);
    }

    public function keywordRule(): BelongsTo
    {
        return $this->belongsTo(KeywordRule::class);
    }
}