<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class Finding extends Model
{
    use HasFactory;

    protected $fillable = [
        'analysis_id',
        'severity',
        'title',
        'description',
        'line',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'line' => 'integer',
    ];

    public const SEVERITIES = [
        'critical' => 'Critical',
        'high' => 'High',
        'medium' => 'Medium',
        'low' => 'Low',
        'info' => 'Info'
    ];

    public function analysis(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Analysis::class);
    }

    public function scopeBySeverity($query, string $severity): mixed
    {
        return $query->where('severity', $severity);
    }

    public function scopeCritical($query): mixed
    {
        return $query->where('severity', 'critical');
    }

    public function scopeHigh($query): mixed
    {
        return $query->where('severity', 'high');
    }

    public function getSeverityLabelAttribute(): string
    {
        return self::SEVERITIES[$this->severity] ?? ucfirst($this->severity);
    }

    public function isCritical(): bool
    {
        return $this->severity === 'critical';
    }

    public function isHighSeverity(): bool
    {
        return in_array($this->severity, ['critical', 'high']);
    }
} 