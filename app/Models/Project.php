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
        'blockchain_network',
        'project_type',
        'contract_addresses',
        'main_contract_address',
        'token_address',
        'token_symbol',
        'metadata',
        'website_url',
        'github_url',
        'social_links',
        'analyses_count',
        'critical_findings_count',
        'average_sentiment_score',
        'last_analyzed_at',
        'status',
        'is_public',
        'monitoring_enabled',
        'alert_settings',
        'risk_level',
        'risk_score',
        'risk_updated_at',
        'tags',
        'category',
    ];

    protected $casts = [
        'contract_addresses' => 'array',
        'metadata' => 'array',
        'social_links' => 'array',
        'alert_settings' => 'array',
        'tags' => 'array',
        'last_analyzed_at' => 'datetime',
        'risk_updated_at' => 'datetime',
        'is_public' => 'boolean',
        'monitoring_enabled' => 'boolean',
        'average_sentiment_score' => 'decimal:2',
        'risk_score' => 'decimal:2',
    ];

    public function getNetworkAttribute(): ?string
    {
        return $this->blockchain_network;
    }

    public function getContractAddressAttribute(): ?string
    {
        return $this->main_contract_address;
    }

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