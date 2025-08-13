<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

final class FamousContract extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'network',
        'contract_type',
        'description',
        'deployment_date',
        'total_value_locked',
        'transaction_count',
        'creator_address',
        'is_verified',
        'risk_score',
        'security_features',
        'vulnerabilities',
        'audit_firms',
        'gas_optimization',
        'code_quality',
        'exploit_details',
        'metadata',
    ];

    protected $casts = [
        'deployment_date' => 'date',
        'is_verified' => 'boolean',
        'total_value_locked' => 'decimal:0',
        'transaction_count' => 'integer',
        'risk_score' => 'integer',
        'security_features' => 'array',
        'vulnerabilities' => 'array',
        'audit_firms' => 'array',
        'exploit_details' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Get all analyses for this contract
     */
    public function analyses(): HasMany
    {
        return $this->hasMany(ContractAnalysis::class, 'contract_id');
    }

    /**
     * Get the latest analysis for this contract
     */
    public function latestAnalysis(): HasMany
    {
        return $this->hasMany(ContractAnalysis::class, 'contract_id')
                   ->latest('analysis_date');
    }

    /**
     * Get security analyses only
     */
    public function securityAnalyses(): HasMany
    {
        return $this->hasMany(ContractAnalysis::class, 'contract_id')
                   ->where('analysis_type', 'security_audit');
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
     * Get the TVL formatted as currency
     */
    protected function tvlFormatted(): Attribute
    {
        return Attribute::make(
            get: fn () => '$' . number_format($this->total_value_locked / 1e9, 2) . 'B'
        );
    }

    /**
     * Get the transaction count formatted
     */
    protected function transactionCountFormatted(): Attribute
    {
        return Attribute::make(
            get: fn () => number_format($this->transaction_count)
        );
    }

    /**
     * Check if contract has been exploited
     */
    protected function isExploited(): Attribute
    {
        return Attribute::make(
            get: fn () => !empty($this->exploit_details)
        );
    }

    /**
     * Get exploit amount if exploited
     */
    protected function exploitAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->exploit_details['amount_stolen'] ?? null
        );
    }

    /**
     * Get formatted exploit amount
     */
    protected function exploitAmountFormatted(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->exploit_amount ? 
                '$' . number_format($this->exploit_amount / 1e6) . 'M' : 
                null
        );
    }

    /**
     * Check if contract is audited
     */
    protected function isAudited(): Attribute
    {
        return Attribute::make(
            get: fn () => !empty($this->audit_firms)
        );
    }

    /**
     * Get security score (inverse of risk score)
     */
    protected function securityScore(): Attribute
    {
        return Attribute::make(
            get: fn () => 100 - $this->risk_score
        );
    }

    /**
     * Scope for filtering by network
     */
    public function scopeByNetwork($query, string $network)
    {
        return $query->where('network', $network);
    }

    /**
     * Scope for filtering by contract type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('contract_type', $type);
    }

    /**
     * Scope for filtering by risk level
     */
    public function scopeByRiskLevel($query, string $level)
    {
        return match($level) {
            'critical' => $query->where('risk_score', '>=', 80),
            'high' => $query->whereBetween('risk_score', [60, 79]),
            'medium' => $query->whereBetween('risk_score', [40, 59]),
            'low' => $query->whereBetween('risk_score', [20, 39]),
            'very_low' => $query->where('risk_score', '<', 20),
            default => $query->where('risk_score', '>=', 0)
        };
    }

    /**
     * Scope for exploited contracts
     */
    public function scopeExploited($query)
    {
        return $query->whereNotNull('exploit_details');
    }

    /**
     * Scope for verified contracts
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope for audited contracts
     */
    public function scopeAudited($query)
    {
        return $query->whereNotNull('audit_firms');
    }
}
