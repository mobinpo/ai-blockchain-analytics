<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

final class ContractAnalysis extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'analysis_type',
        'status',
        'risk_score',
        'findings',
        'recommendations',
        'analysis_date',
        'analyzer_version',
        'execution_time_ms',
        'confidence_score',
        'metadata',
    ];

    protected $casts = [
        'risk_score' => 'integer',
        'findings' => 'array',
        'recommendations' => 'array',
        'analysis_date' => 'datetime',
        'execution_time_ms' => 'integer',
        'confidence_score' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * Get the contract this analysis belongs to
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(FamousContract::class, 'contract_id');
    }

    /**
     * Get the risk level as a string
     */
    protected function riskLevel(): Attribute
    {
        return Attribute::make(
            get: fn () => match (true) {
                $this->risk_score >= 80 => 'Critical',
                $this->risk_score >= 60 => 'High',
                $this->risk_score >= 40 => 'Medium',
                $this->risk_score >= 20 => 'Low',
                default => 'Very Low'
            }
        );
    }

    /**
     * Get the analysis type formatted
     */
    protected function analysisTypeFormatted(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->analysis_type) {
                'security_audit' => 'Security Audit',
                'gas_optimization' => 'Gas Optimization',
                'vulnerability_scan' => 'Vulnerability Scan',
                'code_quality' => 'Code Quality',
                default => ucwords(str_replace('_', ' ', $this->analysis_type))
            }
        );
    }

    /**
     * Get the execution time formatted
     */
    protected function executionTimeFormatted(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->execution_time_ms ? 
                ($this->execution_time_ms > 1000 ? 
                    round($this->execution_time_ms / 1000, 2) . 's' : 
                    $this->execution_time_ms . 'ms') : 
                'N/A'
        );
    }

    /**
     * Get the confidence level as a string
     */
    protected function confidenceLevel(): Attribute
    {
        return Attribute::make(
            get: fn () => match (true) {
                $this->confidence_score >= 90 => 'Very High',
                $this->confidence_score >= 80 => 'High',
                $this->confidence_score >= 70 => 'Medium',
                $this->confidence_score >= 60 => 'Low',
                default => 'Very Low'
            }
        );
    }

    /**
     * Get the number of findings
     */
    protected function findingsCount(): Attribute
    {
        return Attribute::make(
            get: fn () => is_array($this->findings) ? count($this->findings) : 0
        );
    }

    /**
     * Get the number of recommendations
     */
    protected function recommendationsCount(): Attribute
    {
        return Attribute::make(
            get: fn () => is_array($this->recommendations) ? count($this->recommendations) : 0
        );
    }

    /**
     * Check if analysis is completed
     */
    protected function isCompleted(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status === 'completed'
        );
    }

    /**
     * Check if analysis failed
     */
    protected function isFailed(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status === 'failed'
        );
    }

    /**
     * Check if analysis is running
     */
    protected function isRunning(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status === 'running'
        );
    }

    /**
     * Scope for filtering by analysis type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('analysis_type', $type);
    }

    /**
     * Scope for filtering by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for completed analyses
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for failed analyses
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for running analyses
     */
    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    /**
     * Scope for recent analyses
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('analysis_date', '>=', now()->subDays($days));
    }

    /**
     * Scope for high confidence analyses
     */
    public function scopeHighConfidence($query, float $threshold = 80.0)
    {
        return $query->where('confidence_score', '>=', $threshold);
    }

    /**
     * Scope for critical risk analyses
     */
    public function scopeCriticalRisk($query)
    {
        return $query->where('risk_score', '>=', 80);
    }
}