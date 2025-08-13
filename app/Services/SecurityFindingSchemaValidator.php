<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

final class SecurityFindingSchemaValidator
{
    private array $schema;
    private string $schemaVersion;

    public function __construct(string $schemaVersion = 'v5')
    {
        $this->schemaVersion = $schemaVersion;
        $this->schema = $this->loadSchema($schemaVersion);
    }

    /**
     * Load JSON schema from file.
     */
    private function loadSchema(string $version): array
    {
        $schemaPath = base_path("schemas/security-finding-{$version}-comprehensive.json");
        
        if (!file_exists($schemaPath)) {
            throw new \Exception("Schema file not found: {$schemaPath}");
        }

        $schemaContent = file_get_contents($schemaPath);
        $schema = json_decode($schemaContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON in schema file: " . json_last_error_msg());
        }

        return $schema;
    }

    /**
     * Validate a single security finding against the schema.
     */
    public function validateFinding(array $finding): array
    {
        $startTime = microtime(true);

        // Basic validation
        $errors = $this->validateBasicStructure($finding);
        
        // Calculate quality metrics
        $qualityMetrics = $this->calculateQualityScore($finding);
        $completenessScore = $this->calculateCompletenessScore($finding);

        $validationTime = (microtime(true) - $startTime) * 1000;
        $isValid = empty($errors);

        $result = [
            'valid' => $isValid,
            'errors' => $errors,
            'quality_score' => $qualityMetrics['overall_score'],
            'quality_breakdown' => $qualityMetrics,
            'completeness_score' => $completenessScore,
            'validation_time_ms' => round($validationTime, 2),
            'schema_version' => $this->schemaVersion,
            'validated_at' => Carbon::now()->toISOString(),
        ];

        // Log validation result
        Log::info('Security finding validation completed', [
            'finding_id' => $finding['id'] ?? 'unknown',
            'valid' => $isValid,
            'quality_score' => $qualityMetrics['overall_score'],
            'error_count' => count($errors),
            'validation_time_ms' => $validationTime,
        ]);

        return $result;
    }

    /**
     * Validate basic structure against schema requirements.
     */
    private function validateBasicStructure(array $finding): array
    {
        $errors = [];
        $required = $this->schema['required'] ?? [];

        // Check required fields
        foreach ($required as $field) {
            if (!isset($finding[$field]) || empty($finding[$field])) {
                $errors[] = [
                    'property' => $field,
                    'message' => "Required field '{$field}' is missing or empty"
                ];
            }
        }

        // Validate specific fields
        if (isset($finding['id'])) {
            if (!preg_match('/^FIND-[0-9A-F]{32}$/', $finding['id'])) {
                $errors[] = [
                    'property' => 'id',
                    'message' => 'ID must follow pattern FIND-XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX (32 hex characters)'
                ];
            }
        }

        if (isset($finding['severity'])) {
            $validSeverities = ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW', 'INFO', 'GAS_OPTIMIZATION'];
            if (!in_array($finding['severity'], $validSeverities)) {
                $errors[] = [
                    'property' => 'severity',
                    'message' => 'Severity must be one of: ' . implode(', ', $validSeverities)
                ];
            }
        }

        if (isset($finding['title'])) {
            $titleLength = strlen($finding['title']);
            if ($titleLength < 20 || $titleLength > 200) {
                $errors[] = [
                    'property' => 'title',
                    'message' => 'Title must be between 20 and 200 characters'
                ];
            }
        }

        if (isset($finding['description'])) {
            $descLength = strlen($finding['description']);
            if ($descLength < 50 || $descLength > 2000) {
                $errors[] = [
                    'property' => 'description',
                    'message' => 'Description must be between 50 and 2000 characters'
                ];
            }
        }

        return $errors;
    }

    /**
     * Validate multiple findings.
     */
    public function validateFindings(array $findings): array
    {
        $results = [
            'total_findings' => count($findings),
            'valid_findings' => 0,
            'invalid_findings' => 0,
            'average_quality_score' => 0,
            'validation_errors' => [],
            'quality_distribution' => [
                'excellent' => 0, // 90-100
                'good' => 0,      // 70-89
                'fair' => 0,      // 50-69
                'poor' => 0       // 0-49
            ],
            'findings_validation' => []
        ];

        $totalQualityScore = 0;

        foreach ($findings as $index => $finding) {
            $validation = $this->validateFinding($finding);
            
            $results['findings_validation'][$index] = $validation;
            
            if ($validation['valid']) {
                $results['valid_findings']++;
            } else {
                $results['invalid_findings']++;
                $results['validation_errors'][] = [
                    'finding_index' => $index,
                    'finding_id' => $finding['id'] ?? 'unknown',
                    'errors' => $validation['errors']
                ];
            }

            $qualityScore = $validation['quality_score'];
            $totalQualityScore += $qualityScore;

            // Categorize quality
            if ($qualityScore >= 90) {
                $results['quality_distribution']['excellent']++;
            } elseif ($qualityScore >= 70) {
                $results['quality_distribution']['good']++;
            } elseif ($qualityScore >= 50) {
                $results['quality_distribution']['fair']++;
            } else {
                $results['quality_distribution']['poor']++;
            }
        }

        $results['average_quality_score'] = count($findings) > 0 
            ? round($totalQualityScore / count($findings), 2) 
            : 0;

        return $results;
    }

    /**
     * Calculate quality score for a finding.
     */
    private function calculateQualityScore(array $finding): array
    {
        $scores = [
            'title_quality' => $this->scoreTitleQuality($finding['title'] ?? ''),
            'description_depth' => $this->scoreDescriptionDepth($finding['description'] ?? ''),
            'location_completeness' => $this->scoreLocationCompleteness($finding['location'] ?? []),
            'recommendation_actionability' => $this->scoreRecommendationActionability($finding['recommendation'] ?? []),
            'risk_assessment_completeness' => $this->scoreRiskAssessment($finding['risk_metrics'] ?? []),
            'ai_metadata_completeness' => $this->scoreAiMetadata($finding['ai_metadata'] ?? []),
            'blockchain_context_relevance' => $this->scoreBlockchainContext($finding['blockchain_context'] ?? []),
        ];

        // Weighted average
        $weights = [
            'title_quality' => 0.10,
            'description_depth' => 0.20,
            'location_completeness' => 0.15,
            'recommendation_actionability' => 0.25,
            'risk_assessment_completeness' => 0.15,
            'ai_metadata_completeness' => 0.10,
            'blockchain_context_relevance' => 0.05,
        ];

        $overallScore = 0;
        foreach ($scores as $metric => $score) {
            $overallScore += $score * $weights[$metric];
        }

        return array_merge($scores, ['overall_score' => round($overallScore, 2)]);
    }

    /**
     * Score title quality.
     */
    private function scoreTitleQuality(string $title): float
    {
        $score = 0;

        // Length check (20-200 chars)
        if (strlen($title) >= 20 && strlen($title) <= 200) {
            $score += 30;
        }

        // Descriptive pattern check
        if (preg_match('/\b(in|enables?|allows?|bypass|manipulation|drainage)\b/i', $title)) {
            $score += 25;
        }

        // Technical terms
        if (preg_match('/\b(re-?entrancy|overflow|underflow|access control|oracle|flash loan)\b/i', $title)) {
            $score += 25;
        }

        // Proper capitalization
        if (preg_match('/^[A-Z][^.]*[^.]$/', $title)) {
            $score += 20;
        }

        return min(100, $score);
    }

    /**
     * Score description depth.
     */
    private function scoreDescriptionDepth(string $description): float
    {
        $score = 0;

        // Length check (50-2000 chars)
        if (strlen($description) >= 50 && strlen($description) <= 2000) {
            $score += 25;
        }

        // Technical explanation
        if (preg_match_all('/\b(function|contract|vulnerability|attack|exploit|bypass)\b/i', $description) >= 3) {
            $score += 25;
        }

        // Impact description
        if (preg_match('/\b(fund|drain|loss|compromise|unauthorized|malicious)\b/i', $description)) {
            $score += 25;
        }

        // Step-by-step explanation
        if (preg_match('/\b(first|then|finally|step|process|execution)\b/i', $description)) {
            $score += 25;
        }

        return min(100, $score);
    }

    /**
     * Score location completeness.
     */
    private function scoreLocationCompleteness(array $location): float
    {
        $score = 0;
        $requiredFields = ['line', 'function', 'contract'];
        $optionalFields = ['code_snippet', 'affected_variables', 'control_flow'];

        foreach ($requiredFields as $field) {
            if (!empty($location[$field])) {
                $score += 20;
            }
        }

        foreach ($optionalFields as $field) {
            if (!empty($location[$field])) {
                $score += 13.33;
            }
        }

        return min(100, $score);
    }

    /**
     * Score recommendation actionability.
     */
    private function scoreRecommendationActionability(array $recommendation): float
    {
        $score = 0;

        // Immediate action specified
        if (!empty($recommendation['immediate_action'])) {
            $score += 20;
        }

        // Summary provided
        if (!empty($recommendation['summary']) && strlen($recommendation['summary']) >= 20) {
            $score += 20;
        }

        // Detailed steps provided
        if (!empty($recommendation['detailed_steps']) && is_array($recommendation['detailed_steps'])) {
            $steps = $recommendation['detailed_steps'];
            if (count($steps) >= 2) {
                $score += 30;
            }

            // Check step quality
            $stepScore = 0;
            foreach ($steps as $step) {
                if (is_array($step) && !empty($step['action']) && !empty($step['verification'])) {
                    $stepScore += 5;
                }
            }
            $score += min(20, $stepScore);
        }

        // Secure pattern provided
        if (!empty($recommendation['secure_pattern'])) {
            $score += 10;
        }

        return min(100, $score);
    }

    /**
     * Score risk assessment completeness.
     */
    private function scoreRiskAssessment(array $riskMetrics): float
    {
        $score = 0;

        // CVSS score
        if (!empty($riskMetrics['cvss_v3']['score'])) {
            $score += 30;
        }

        // Exploitability assessment
        if (!empty($riskMetrics['exploitability']['ease'])) {
            $score += 25;
        }

        // Business impact
        if (!empty($riskMetrics['business_impact']['financial'])) {
            $score += 25;
        }

        // Attack complexity
        if (!empty($riskMetrics['exploitability']['attack_complexity'])) {
            $score += 20;
        }

        return min(100, $score);
    }

    /**
     * Score AI metadata completeness.
     */
    private function scoreAiMetadata(array $aiMetadata): float
    {
        $score = 0;
        $requiredFields = ['model', 'analysis_version', 'detection_method'];

        foreach ($requiredFields as $field) {
            if (!empty($aiMetadata[$field])) {
                $score += 25;
            }
        }

        // Confidence scoring
        if (!empty($aiMetadata['confidence_scoring'])) {
            $score += 15;
        }

        // Processing info
        if (!empty($aiMetadata['processing_info'])) {
            $score += 10;
        }

        return min(100, $score);
    }

    /**
     * Score blockchain context relevance.
     */
    private function scoreBlockchainContext(array $blockchainContext): float
    {
        $score = 0;

        // Networks specified
        if (!empty($blockchainContext['networks'])) {
            $score += 30;
        }

        // EVM specifics
        if (!empty($blockchainContext['evm_specifics'])) {
            $score += 25;
        }

        // Gas analysis
        if (!empty($blockchainContext['gas_analysis'])) {
            $score += 25;
        }

        // DeFi context
        if (!empty($blockchainContext['defi_context'])) {
            $score += 20;
        }

        return min(100, $score);
    }

    /**
     * Calculate completeness score.
     */
    private function calculateCompletenessScore(array $finding): float
    {
        $requiredFields = [
            'id', 'severity', 'title', 'category', 'description', 
            'location', 'recommendation', 'confidence', 'ai_metadata'
        ];
        
        $optionalFields = [
            'risk_metrics', 'blockchain_context', 'attack_vector', 
            'related_findings', 'compliance', 'quality_metrics', 'tags'
        ];

        $score = 0;
        $maxScore = (count($requiredFields) * 10) + (count($optionalFields) * 5);

        // Required fields (10 points each)
        foreach ($requiredFields as $field) {
            if (!empty($finding[$field])) {
                $score += 10;
            }
        }

        // Optional fields (5 points each)
        foreach ($optionalFields as $field) {
            if (!empty($finding[$field])) {
                $score += 5;
            }
        }

        return round(($score / $maxScore) * 100, 2);
    }

    /**
     * Create a template finding with optimal structure for AI prompts.
     */
    public function createPromptOptimizedTemplate(array $params = []): array
    {
        $template = [
            'id' => 'FIND-' . strtoupper(str_replace('-', '', (string) \Illuminate\Support\Str::uuid())),
            'severity' => $params['severity'] ?? 'MEDIUM',
            'title' => $params['title'] ?? '[Vulnerability Type] in [Function] enables [Impact]',
            'category' => $params['category'] ?? 'CUSTOM-001-Business Logic Flaw',
            'description' => 'WHAT: Describe the vulnerability type and mechanism.\nWHERE: Identify exact location (function, line, contract).\nHOW: Explain the exploitation mechanism step-by-step.\nIMPACT: Describe potential consequences and affected parties.',
            'confidence' => $params['confidence'] ?? 'MEDIUM',
            'location' => [
                'line' => 1,
                'function' => 'functionName',
                'contract' => 'ContractName',
                'code_snippet' => 'function example() { /* vulnerable code */ }',
                'affected_variables' => []
            ],
            'recommendation' => [
                'immediate_action' => 'MONITOR_CLOSELY',
                'summary' => 'High-level approach to fix the vulnerability',
                'detailed_steps' => [
                    [
                        'step' => 1,
                        'action' => 'Specific action to take',
                        'code_example' => '// Secure implementation',
                        'verification' => 'How to verify the fix',
                        'estimated_effort' => 'MODERATE'
                    ]
                ],
                'estimated_fix_time' => 'HOURS'
            ],
            'ai_metadata' => [
                'model' => 'gpt-4',
                'analysis_version' => '5.0.0',
                'detection_method' => 'LLM_ANALYSIS',
                'processing_info' => [
                    'created_at' => Carbon::now()->toISOString()
                ]
            ],
            'status' => 'OPEN',
            'tags' => ['ai-generated', 'needs-review']
        ];

        return $template;
    }

    /**
     * Generate validation report.
     */
    public function generateValidationReport(array $validationResults): string
    {
        $report = "# Security Finding Schema Validation Report\n\n";
        $report .= "**Schema Version:** {$this->schemaVersion}\n";
        $report .= "**Generated:** " . Carbon::now()->format('Y-m-d H:i:s') . "\n\n";

        $report .= "## Summary\n\n";
        $report .= "- **Total Findings:** {$validationResults['total_findings']}\n";
        $report .= "- **Valid Findings:** {$validationResults['valid_findings']}\n";
        $report .= "- **Invalid Findings:** {$validationResults['invalid_findings']}\n";
        $report .= "- **Average Quality Score:** {$validationResults['average_quality_score']}%\n\n";

        $report .= "## Quality Distribution\n\n";
        foreach ($validationResults['quality_distribution'] as $level => $count) {
            $report .= "- **" . ucfirst($level) . ":** {$count} findings\n";
        }

        if (!empty($validationResults['validation_errors'])) {
            $report .= "\n## Validation Errors\n\n";
            foreach ($validationResults['validation_errors'] as $error) {
                $report .= "### Finding {$error['finding_id']}\n";
                foreach ($error['errors'] as $validationError) {
                    $report .= "- **{$validationError['property']}:** {$validationError['message']}\n";
                }
                $report .= "\n";
            }
        }

        return $report;
    }

    /**
     * Get schema statistics.
     */
    public function getSchemaStatistics(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'required_fields' => count($this->schema['required'] ?? []),
            'total_properties' => count($this->schema['properties'] ?? []),
            'schema_complexity' => $this->calculateSchemaComplexity(),
        ];
    }

    /**
     * Calculate schema complexity score.
     */
    private function calculateSchemaComplexity(): string
    {
        $properties = count($this->schema['properties'] ?? []);
        $required = count($this->schema['required'] ?? []);
        
        if ($properties > 20 && $required > 8) {
            return 'HIGH';
        } elseif ($properties > 10 && $required > 5) {
            return 'MEDIUM';
        } else {
            return 'LOW';
        }
    }
}