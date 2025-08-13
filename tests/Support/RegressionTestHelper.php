<?php

namespace Tests\Support;

use App\Models\Analysis;

/**
 * Helper utilities for vulnerability regression testing
 */
class RegressionTestHelper
{
    /**
     * Vulnerability severity score mappings
     */
    public const SEVERITY_SCORES = [
        'critical' => ['min' => 70, 'max' => 100],
        'high' => ['min' => 50, 'max' => 85],
        'medium' => ['min' => 25, 'max' => 60],
        'low' => ['min' => 10, 'max' => 40],
    ];

    /**
     * Expected minimum findings count by severity
     */
    public const MIN_FINDINGS_BY_SEVERITY = [
        'critical' => 2,
        'high' => 1,
        'medium' => 1,
        'low' => 1,
    ];

    /**
     * OWASP Top 10 2021 mappings to SWC categories
     */
    public const OWASP_TO_SWC_MAPPING = [
        'A01:2021-Broken Access Control' => ['SWC-115', 'SWC-105'],
        'A02:2021-Cryptographic Failures' => ['SWC-116', 'SWC-120'],
        'A03:2021-Injection' => ['SWC-103'],
        'A04:2021-Insecure Design' => ['SWC-114'],
        'A05:2021-Security Misconfiguration' => ['SWC-106'],
        'A06:2021-Vulnerable Components' => ['SWC-102'],
        'A07:2021-Authentication Failures' => ['SWC-115'],
        'A08:2021-Data Integrity Failures' => ['SWC-104'],
        'A09:2021-Logging and Monitoring' => ['SWC-109'],
        'A10:2021-SSRF' => ['SWC-107'],
    ];

    /**
     * Validate vulnerability detection result
     */
    public static function validateDetection($analysis, array $contract): array
    {
        $validation = [
            'is_completed' => $analysis->status === 'completed',
            'has_structured_result' => !empty($analysis->structured_result),
            'has_findings' => ($analysis->findings_count ?? 0) > 0,
            'meets_risk_threshold' => false,
            'has_expected_keywords' => false,
            'meets_minimum_findings' => false,
            'overall_detected' => false,
            'keyword_match_rate' => 0,
        ];

        // Check risk score threshold
        $severity = strtolower($contract['severity']);
        $riskScore = $analysis->risk_score ?? 0;
        
        // Handle severity mapping with fallback
        if (isset(self::SEVERITY_SCORES[$severity])) {
            $minScore = self::SEVERITY_SCORES[$severity]['min'];
            $validation['meets_risk_threshold'] = $riskScore >= $minScore;
        } else {
            // Fallback for unknown severity
            $validation['meets_risk_threshold'] = $riskScore >= 20;
        }

        // Check minimum findings count
        $findingsCount = $analysis->findings_count ?? 0;
        if (isset(self::MIN_FINDINGS_BY_SEVERITY[$severity])) {
            $minFindings = self::MIN_FINDINGS_BY_SEVERITY[$severity];
            $validation['meets_minimum_findings'] = $findingsCount >= $minFindings;
        } else {
            // Fallback for unknown severity
            $validation['meets_minimum_findings'] = $findingsCount >= 1;
        }

        // Check for expected keywords
        if (isset($contract['expected_findings']) && !empty($contract['expected_findings'])) {
            $analysisText = strtolower($analysis->raw_openai_response ?? '');
            $keywordMatches = 0;
            
            foreach ($contract['expected_findings'] as $expectedFinding) {
                if (stripos($analysisText, strtolower($expectedFinding)) !== false) {
                    $keywordMatches++;
                }
            }
            
            $validation['has_expected_keywords'] = $keywordMatches > 0;
            $validation['keyword_match_rate'] = count($contract['expected_findings']) > 0 
                ? $keywordMatches / count($contract['expected_findings'])
                : 0;
        } else {
            $validation['has_expected_keywords'] = true; // No keywords to check
            $validation['keyword_match_rate'] = 1.0;
        }

        // Overall detection logic
        $validation['overall_detected'] = $validation['is_completed'] 
            && $validation['has_structured_result']
            && $validation['has_findings']
            && $validation['meets_risk_threshold']
            && $validation['has_expected_keywords'];

        return $validation;
    }

    /**
     * Generate test metrics summary
     */
    public static function generateMetrics(array $results): array
    {
        $total = count($results);
        $detected = array_sum(array_column($results, 'detected'));
        $totalRiskScore = array_sum(array_column($results, 'risk_score'));
        $totalFindings = array_sum(array_column($results, 'findings_count'));

        // Severity breakdown
        $severityStats = [];
        foreach (['critical', 'high', 'medium', 'low'] as $severity) {
            $severityResults = array_filter($results, fn($r) => $r['severity'] === $severity);
            $severityStats[$severity] = [
                'total' => count($severityResults),
                'detected' => array_sum(array_column($severityResults, 'detected')),
                'avg_risk_score' => count($severityResults) > 0 
                    ? array_sum(array_column($severityResults, 'risk_score')) / count($severityResults) 
                    : 0,
            ];
        }

        return [
            'total_contracts' => $total,
            'detected_count' => $detected,
            'detection_rate' => $total > 0 ? ($detected / $total) * 100 : 0,
            'average_risk_score' => $total > 0 ? $totalRiskScore / $total : 0,
            'total_findings' => $totalFindings,
            'average_findings' => $total > 0 ? $totalFindings / $total : 0,
            'severity_breakdown' => $severityStats,
            'pass_threshold' => 70.0, // Minimum detection rate to pass
        ];
    }

    /**
     * Format test results for display
     */
    public static function formatResults(array $results, array $metrics): string
    {
        $output = "\n" . str_repeat("=", 80) . "\n";
        $output .= "🔍 VULNERABILITY REGRESSION TEST RESULTS\n";
        $output .= str_repeat("=", 80) . "\n\n";

        // Individual test results
        foreach ($results as $key => $result) {
            $status = $result['detected'] ? '✅ DETECTED' : '❌ MISSED';
            $severity = strtoupper($result['severity']);
            
            $output .= sprintf(
                "%-50s [%8s] %s\n",
                $result['contract_name'],
                $severity,
                $status
            );
            
            $output .= sprintf(
                "    Risk Score: %3d%% | Findings: %2d | Expected: %s\n",
                $result['risk_score'],
                $result['findings_count'],
                implode(', ', array_slice($result['expected_findings'], 0, 2))
            );
            
            if (isset($result['validation_details'])) {
                $details = $result['validation_details'];
                $output .= sprintf(
                    "    Validation: Risk✓%s | Keywords✓%s | Findings✓%s\n",
                    $details['meets_risk_threshold'] ? '✅' : '❌',
                    $details['has_expected_keywords'] ? '✅' : '❌',
                    $details['has_findings'] ? '✅' : '❌'
                );
            }
            
            $output .= "\n";
        }

        // Summary metrics
        $output .= str_repeat("-", 80) . "\n";
        $output .= "📊 SUMMARY METRICS\n";
        $output .= str_repeat("-", 80) . "\n";
        
        $output .= sprintf("Detection Rate:     %.1f%% (%d/%d)\n", 
            $metrics['detection_rate'], 
            $metrics['detected_count'], 
            $metrics['total_contracts']
        );
        
        $output .= sprintf("Average Risk Score: %.1f%%\n", $metrics['average_risk_score']);
        $output .= sprintf("Total Findings:     %d (avg: %.1f per contract)\n", 
            $metrics['total_findings'], 
            $metrics['average_findings']
        );

        // Severity breakdown
        $output .= "\n🎯 SEVERITY BREAKDOWN\n";
        foreach ($metrics['severity_breakdown'] as $severity => $stats) {
            if ($stats['total'] > 0) {
                $rate = ($stats['detected'] / $stats['total']) * 100;
                $output .= sprintf(
                    "%-8s: %.1f%% (%d/%d) - Avg Risk: %.1f%%\n",
                    strtoupper($severity),
                    $rate,
                    $stats['detected'],
                    $stats['total'],
                    $stats['avg_risk_score']
                );
            }
        }

        // Pass/Fail status
        $output .= "\n🏆 TEST RESULT\n";
        $passed = $metrics['detection_rate'] >= $metrics['pass_threshold'];
        $output .= sprintf(
            "Status: %s (Threshold: %.1f%%, Achieved: %.1f%%)\n",
            $passed ? '✅ PASSED' : '❌ FAILED',
            $metrics['pass_threshold'],
            $metrics['detection_rate']
        );

        $output .= str_repeat("=", 80) . "\n";

        return $output;
    }

    /**
     * Export results to JSON for CI/CD integration
     */
    public static function exportToJson(array $results, array $metrics): array
    {
        return [
            'timestamp' => now()->toISOString(),
            'version' => '1.0',
            'test_suite' => 'vulnerability_regression',
            'environment' => config('app.env'),
            'metrics' => $metrics,
            'results' => $results,
            'configuration' => [
                'total_contracts' => count($results),
                'detection_threshold' => $metrics['pass_threshold'],
                'timeout_seconds' => 30,
                'simulation_mode' => !config('regression.use_real_api', false),
            ],
            'summary' => [
                'passed' => $metrics['detection_rate'] >= $metrics['pass_threshold'],
                'detection_rate' => round($metrics['detection_rate'], 2),
                'average_risk_score' => round($metrics['average_risk_score'], 2),
                'test_coverage' => [
                    'critical' => $metrics['severity_breakdown']['critical']['total'],
                    'high' => $metrics['severity_breakdown']['high']['total'],
                    'medium' => $metrics['severity_breakdown']['medium']['total'],
                    'low' => $metrics['severity_breakdown']['low']['total'],
                ],
            ],
        ];
    }

    /**
     * Validate contract code structure
     */
    public static function validateContractStructure(string $code): array
    {
        $validation = [
            'has_pragma' => false,
            'has_contract_declaration' => false,
            'has_functions' => false,
            'estimated_complexity' => 'low',
            'line_count' => 0,
        ];

        $lines = explode("\n", $code);
        $validation['line_count'] = count($lines);

        // Basic structure checks
        if (preg_match('/pragma\s+solidity/', $code)) {
            $validation['has_pragma'] = true;
        }

        if (preg_match('/contract\s+\w+/', $code)) {
            $validation['has_contract_declaration'] = true;
        }

        if (preg_match('/function\s+\w+/', $code)) {
            $validation['has_functions'] = true;
        }

        // Complexity estimation
        $functionCount = preg_match_all('/function\s+\w+/', $code);
        $modifierCount = preg_match_all('/modifier\s+\w+/', $code);
        $complexityScore = $functionCount + $modifierCount;

        $validation['estimated_complexity'] = match(true) {
            $complexityScore >= 10 => 'high',
            $complexityScore >= 5 => 'medium',
            default => 'low'
        };

        return $validation;
    }

    /**
     * Get vulnerability pattern mappings for simulation
     */
    public static function getVulnerabilityPatterns(): array
    {
        return [
            'reentrancy' => [
                'patterns' => ['call{value:', '.call(', 'external call', 'msg.sender.call'],
                'severity_mapping' => 'critical',
                'swc_id' => 'SWC-107',
            ],
            'integer_overflow' => [
                'patterns' => ['+=', '*', 'SafeMath', 'unchecked', 'pragma solidity ^0.7'],
                'severity_mapping' => 'high',
                'swc_id' => 'SWC-101',
            ],
            'access_control' => [
                'patterns' => ['tx.origin', 'selfdestruct', 'onlyOwner', 'public'],
                'severity_mapping' => 'critical',
                'swc_id' => 'SWC-115',
            ],
            'unchecked_calls' => [
                'patterns' => ['.call(', 'send(', 'transfer(', 'external'],
                'severity_mapping' => 'high',
                'swc_id' => 'SWC-104',
            ],
            'timestamp_dependence' => [
                'patterns' => ['block.timestamp', 'now', 'block.number'],
                'severity_mapping' => 'medium',
                'swc_id' => 'SWC-116',
            ],
            'weak_randomness' => [
                'patterns' => ['keccak256', 'blockhash', 'block.difficulty'],
                'severity_mapping' => 'high',
                'swc_id' => 'SWC-120',
            ],
            'dos_vulnerability' => [
                'patterns' => ['for (', 'while', 'unbounded', '.length'],
                'severity_mapping' => 'medium',
                'swc_id' => 'SWC-113',
            ],
            'delegatecall' => [
                'patterns' => ['delegatecall', 'proxy', 'implementation'],
                'severity_mapping' => 'critical',
                'swc_id' => 'SWC-112',
            ],
            'front_running' => [
                'patterns' => ['commit', 'reveal', 'price', 'oracle'],
                'severity_mapping' => 'medium',
                'swc_id' => 'SWC-114',
            ],
            'signature_replay' => [
                'patterns' => ['ecrecover', 'signature', 'nonce', 'domain'],
                'severity_mapping' => 'high',
                'swc_id' => 'SWC-121',
            ],
        ];
    }
}