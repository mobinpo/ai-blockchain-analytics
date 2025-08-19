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
        'contract_address',
        'network',
        'model',
        'analysis_options',
        'triggered_by',
        'user_id',
        'analysis_type',
        'status',
        'progress',
        'current_step',
        'risk_score',
        'findings',
        'findings_count',
        'recommendations',
        'raw_response',
        'tokens_used',
        'processing_time_ms',
        'analysis_date',
        'started_at',
        'completed_at',
        'error_message',
        'analyzer_version',
        'execution_time_ms',
        'confidence_score',
        'metadata',
    ];

    protected $casts = [
        'risk_score' => 'integer',
        'findings' => 'array',
        'recommendations' => 'array',
        'analysis_options' => 'array',
        'analysis_date' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'execution_time_ms' => 'integer',
        'processing_time_ms' => 'integer',
        'confidence_score' => 'decimal:2',
        'metadata' => 'array',
        'progress' => 'integer',
        'findings_count' => 'integer',
        'tokens_used' => 'integer',
    ];

    /**
     * Scope for filtering by contract address and network
     */
    public function scopeForContract($query, string $contractAddress, string $network)
    {
        return $query->where('contract_address', strtolower($contractAddress))
                    ->where('network', strtolower($network));
    }

    /**
     * Get analysis summary data for API responses
     */
    public function getAnalysisSummary(): array
    {
        return [
            'id' => $this->id,
            'contract_address' => $this->contract_address,
            'network' => $this->network,
            'status' => $this->status,
            'progress' => $this->progress,
            'current_step' => $this->current_step,
            'findings_count' => $this->findings_count,
            'risk_score' => $this->risk_score,
            'created_at' => $this->created_at,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
        ];
    }

    /**
     * Get severity counts from findings
     */
    public function getSeverityCounts(): array
    {
        $counts = [
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'info' => 0
        ];

        if (is_array($this->findings)) {
            foreach ($this->findings as $finding) {
                $severity = strtolower($finding['severity'] ?? 'info');
                if (isset($counts[$severity])) {
                    $counts[$severity]++;
                }
            }
        }

        return $counts;
    }

    /**
     * Get risk score (alias for existing risk_score)
     */
    public function getRiskScore(): int
    {
        return $this->risk_score ?? 0;
    }

    /**
     * Get unique categories from findings
     */
    public function getUniqueCategories(): array
    {
        $categories = [];
        
        if (is_array($this->findings)) {
            foreach ($this->findings as $finding) {
                $category = $finding['category'] ?? 'Other';
                if (!in_array($category, $categories)) {
                    $categories[] = $category;
                }
            }
        }

        return $categories;
    }

    /**
     * Get duration in seconds
     */
    public function getDuration(): ?int
    {
        if ($this->started_at && $this->completed_at) {
            return $this->started_at->diffInSeconds($this->completed_at);
        }
        return null;
    }

    /**
     * Get analytics data for statistics
     */
    public static function getAnalyticsData(int $days = 30): array
    {
        $since = now()->subDays($days);
        
        return [
            'total_analyses' => self::where('created_at', '>=', $since)->count(),
            'completed_analyses' => self::where('created_at', '>=', $since)->where('status', 'completed')->count(),
            'failed_analyses' => self::where('created_at', '>=', $since)->where('status', 'failed')->count(),
            'pending_analyses' => self::where('status', 'pending')->count(),
            'processing_analyses' => self::where('status', 'processing')->count(),
            'avg_processing_time' => self::where('created_at', '>=', $since)
                ->where('status', 'completed')
                ->whereNotNull('processing_time_ms')
                ->avg('processing_time_ms'),
            'total_findings' => self::where('created_at', '>=', $since)->sum('findings_count'),
            'networks' => self::where('created_at', '>=', $since)
                ->whereNotNull('network')
                ->groupBy('network')
                ->selectRaw('network, count(*) as count')
                ->pluck('count', 'network')
                ->toArray(),
        ];
    }

    /**
     * Get the contract this analysis belongs to
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(FamousContract::class, 'contract_id');
    }

    /**
     * Get the user who triggered this analysis
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
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