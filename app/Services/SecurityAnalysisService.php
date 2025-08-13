<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

final class SecurityAnalysisService
{
    /**
     * Perform comprehensive security analysis
     */
    public function performSecurityAnalysis(string $sourceCode): array
    {
        try {
            $results = [
                'security_score' => $this->calculateSecurityScore($sourceCode),
                'vulnerabilities' => $this->scanForVulnerabilities($sourceCode),
                'gas_optimization' => $this->analyzeGasOptimization($sourceCode),
                'code_quality' => $this->assessCodeQuality($sourceCode),
                'recommendations' => $this->generateRecommendations($sourceCode)
            ];

            return [
                'success' => true,
                'data' => $results
            ];

        } catch (\Exception $e) {
            Log::error('Security analysis failed', [
                'error' => $e->getMessage(),
                'code_length' => strlen($sourceCode)
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Calculate overall security score
     */
    private function calculateSecurityScore(string $sourceCode): int
    {
        $baseScore = 100;
        $vulnerabilities = $this->scanForVulnerabilities($sourceCode);

        // Deduct points for vulnerabilities
        $baseScore -= $vulnerabilities['critical'] * 20;
        $baseScore -= $vulnerabilities['high'] * 10;
        $baseScore -= $vulnerabilities['medium'] * 5;
        $baseScore -= $vulnerabilities['low'] * 2;

        return max(0, min(100, $baseScore));
    }

    /**
     * Scan for security vulnerabilities
     */
    private function scanForVulnerabilities(string $sourceCode): array
    {
        $vulnerabilities = [
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'details' => []
        ];

        // Critical vulnerability patterns
        $criticalPatterns = [
            '/tx\.origin/i' => 'Use of tx.origin for authorization',
            '/selfdestruct\s*\(/i' => 'Use of selfdestruct',
            '/suicide\s*\(/i' => 'Deprecated suicide function',
        ];

        // High severity patterns
        $highPatterns = [
            '/\.call\s*\(/i' => 'Low-level call usage',
            '/delegatecall\s*\(/i' => 'Delegatecall usage',
            '/assembly\s*\{/i' => 'Inline assembly usage',
        ];

        // Medium severity patterns
        $mediumPatterns = [
            '/block\.timestamp/i' => 'Timestamp dependence',
            '/block\.number/i' => 'Block number dependence',
            '/msg\.value/i' => 'Ether handling',
        ];

        // Low severity patterns
        $lowPatterns = [
            '/pragma\s+solidity\s+\^/i' => 'Floating pragma version',
            '/\.send\s*\(/i' => 'Use of send() method',
        ];

        // Scan for patterns
        $this->scanPatterns($sourceCode, $criticalPatterns, 'critical', $vulnerabilities);
        $this->scanPatterns($sourceCode, $highPatterns, 'high', $vulnerabilities);
        $this->scanPatterns($sourceCode, $mediumPatterns, 'medium', $vulnerabilities);
        $this->scanPatterns($sourceCode, $lowPatterns, 'low', $vulnerabilities);

        return $vulnerabilities;
    }

    /**
     * Scan for specific patterns
     */
    private function scanPatterns(string $sourceCode, array $patterns, string $severity, array &$vulnerabilities): void
    {
        foreach ($patterns as $pattern => $description) {
            $matches = preg_match_all($pattern, $sourceCode, $matchDetails, PREG_OFFSET_CAPTURE);
            if ($matches > 0) {
                $vulnerabilities[$severity] += $matches;
                $vulnerabilities['details'][] = [
                    'severity' => $severity,
                    'description' => $description,
                    'pattern' => $pattern,
                    'occurrences' => $matches,
                    'locations' => $matchDetails[0] ?? []
                ];
            }
        }
    }

    /**
     * Analyze gas optimization opportunities
     */
    private function analyzeGasOptimization(string $sourceCode): array
    {
        $optimizations = [];

        // Check for common gas optimization patterns
        if (preg_match_all('/for\s*\(/i', $sourceCode)) {
            $optimizations[] = [
                'type' => 'loop_optimization',
                'description' => 'Consider loop optimizations to reduce gas costs',
                'impact' => 'medium'
            ];
        }

        if (preg_match_all('/string\s+memory/i', $sourceCode)) {
            $optimizations[] = [
                'type' => 'string_storage',
                'description' => 'Consider using bytes32 instead of string for gas efficiency',
                'impact' => 'low'
            ];
        }

        return $optimizations;
    }

    /**
     * Assess code quality
     */
    private function assessCodeQuality(string $sourceCode): array
    {
        $quality = [
            'score' => 75, // Base score
            'metrics' => []
        ];

        // Check for documentation
        $commentLines = preg_match_all('/\/\*[\s\S]*?\*\/|\/\/.*$/m', $sourceCode);
        $totalLines = substr_count($sourceCode, "\n");
        
        if ($totalLines > 0) {
            $documentationRatio = $commentLines / $totalLines;
            $quality['metrics']['documentation'] = [
                'ratio' => $documentationRatio,
                'score' => min(100, $documentationRatio * 200)
            ];
        }

        // Check for function complexity
        $functionCount = preg_match_all('/function\s+\w+\s*\(/i', $sourceCode);
        $quality['metrics']['function_count'] = $functionCount;

        return $quality;
    }

    /**
     * Generate security recommendations
     */
    private function generateRecommendations(string $sourceCode): array
    {
        $recommendations = [];

        // Check for common issues and suggest fixes
        if (preg_match('/tx\.origin/i', $sourceCode)) {
            $recommendations[] = [
                'type' => 'security',
                'priority' => 'high',
                'title' => 'Replace tx.origin with msg.sender',
                'description' => 'Using tx.origin for authorization can be exploited through phishing attacks.'
            ];
        }

        if (preg_match('/\.call\s*\(/i', $sourceCode)) {
            $recommendations[] = [
                'type' => 'security',
                'priority' => 'medium',
                'title' => 'Review low-level calls',
                'description' => 'Low-level calls should be carefully reviewed for reentrancy vulnerabilities.'
            ];
        }

        return $recommendations;
    }

    /**
     * Quick security scan (for fast analysis)
     */
    public function quickSecurityScan(string $sourceCode): array
    {
        $cacheKey = 'quick_security:' . md5($sourceCode);
        
        return Cache::remember($cacheKey, 3600, function () use ($sourceCode) {
            return [
                'security_score' => $this->calculateSecurityScore($sourceCode),
                'vulnerability_count' => $this->countVulnerabilities($sourceCode),
                'risk_level' => $this->assessRiskLevel($sourceCode)
            ];
        });
    }

    /**
     * Count total vulnerabilities
     */
    private function countVulnerabilities(string $sourceCode): array
    {
        $vulnerabilities = $this->scanForVulnerabilities($sourceCode);
        
        return [
            'critical' => $vulnerabilities['critical'],
            'high' => $vulnerabilities['high'],
            'medium' => $vulnerabilities['medium'],
            'low' => $vulnerabilities['low'],
            'total' => $vulnerabilities['critical'] + $vulnerabilities['high'] + 
                      $vulnerabilities['medium'] + $vulnerabilities['low']
        ];
    }

    /**
     * Assess overall risk level
     */
    private function assessRiskLevel(string $sourceCode): string
    {
        $vulnerabilities = $this->countVulnerabilities($sourceCode);
        
        if ($vulnerabilities['critical'] > 0) {
            return 'critical';
        }
        
        if ($vulnerabilities['high'] > 2) {
            return 'high';
        }
        
        if ($vulnerabilities['medium'] > 5) {
            return 'medium';
        }
        
        return 'low';
    }
}
