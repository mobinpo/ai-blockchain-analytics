<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;

final class OWASPSecurityAnalyzer
{
    use Concerns\UsesProxy;

    private string $openaiApiKey;
    private string $model;
    private array $config;

    public function __construct()
    {
        $this->openaiApiKey = config('services.openai.api_key');
        $this->model = config('services.openai.model', 'gpt-4');
        $this->config = config('services.openai', []);
    }

    /**
     * Analyze smart contract code using OWASP-style security finding schema.
     */
    public function analyzeContract(
        string $sourceCode,
        string $contractName = 'Contract',
        array $focusAreas = ['Re-entrancy', 'Access Control', 'Integer Overflow']
    ): array {
        $cacheKey = 'owasp_analysis_' . md5($sourceCode . implode(',', $focusAreas));
        
        return Cache::remember($cacheKey, 300, function () use ($sourceCode, $contractName, $focusAreas) {
            return $this->performAnalysis($sourceCode, $contractName, $focusAreas);
        });
    }

    /**
     * Perform the actual OpenAI analysis with OWASP-style prompt.
     */
    private function performAnalysis(string $sourceCode, string $contractName, array $focusAreas): array
    {
        $prompt = $this->buildOWASPPrompt($sourceCode, $contractName, $focusAreas);
        
        try {
            $startTime = microtime(true);
            
            $response = $this->getHttpClient()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->openaiApiKey,
                    'Content-Type' => 'application/json',
                ])
                ->timeout(120)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $this->model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $this->getSystemPrompt()
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.1,
                    'max_tokens' => 4000,
                ]);

            $processingTime = (microtime(true) - $startTime) * 1000;

            if (!$response->successful()) {
                throw new \Exception('OpenAI API request failed: ' . $response->body());
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? '';
            
            $findings = $this->parseFindings($content);
            $this->enrichFindings($findings, $processingTime, $data['usage'] ?? []);

            Log::info('OWASP security analysis completed', [
                'contract' => $contractName,
                'findings_count' => count($findings),
                'processing_time_ms' => $processingTime,
                'tokens_used' => $data['usage']['total_tokens'] ?? 0
            ]);

            return $findings;

        } catch (\Exception $e) {
            Log::error('OWASP security analysis failed', [
                'error' => $e->getMessage(),
                'contract' => $contractName
            ]);
            
            throw $e;
        }
    }

    /**
     * Build OWASP-style analysis prompt.
     */
    private function buildOWASPPrompt(string $sourceCode, string $contractName, array $focusAreas): string
    {
        $focusAreasText = implode(', ', $focusAreas);
        
        return <<<PROMPT
Analyze this Solidity smart contract for security vulnerabilities using OWASP-style security finding format.

CONTRACT NAME: {$contractName}
FOCUS AREAS: {$focusAreasText}

REQUIRED OUTPUT FORMAT:
Return a JSON array of security findings. Each finding MUST include these required fields:
- "severity": One of CRITICAL|HIGH|MEDIUM|LOW|INFO
- "title": Brief vulnerability name (e.g., "Re-entrancy", "Integer Overflow")  
- "line": Line number where vulnerability is found
- "recommendation": Clear, actionable remediation guidance

OPTIONAL FIELDS (include when relevant):
- "category": Security classification
- "function": Function name containing vulnerability
- "contract": Contract name (use "{$contractName}")
- "description": Technical details of the vulnerability
- "impact": Primary impact type (FUND_DRAINAGE, UNAUTHORIZED_ACCESS, etc.)
- "exploitability": How easily exploitable (TRIVIAL, EASY, MODERATE, DIFFICULT, THEORETICAL)
- "confidence": Your confidence level (HIGH, MEDIUM, LOW)
- "code_snippet": Relevant vulnerable code excerpt (max 3 lines)
- "fix_example": Example of secure implementation
- "attack_vector": Brief description of exploitation method
- "cvss_score": Risk score 0.0-10.0
- "swc_id": Smart Contract Weakness ID (e.g., "SWC-107")
- "blockchain_networks": Applicable networks ["ETHEREUM", "POLYGON", etc.]
- "gas_impact": Gas-related information if applicable
- "tags": Relevant classification tags

ANALYSIS FOCUS:
1. Re-entrancy vulnerabilities (external calls before state changes)
2. Access control issues (tx.origin, missing modifiers)
3. Integer overflow/underflow (unsafe math operations)
4. Input validation (unchecked parameters, return values)
5. Business logic flaws
6. Gas optimization opportunities
7. Flash loan attack vectors
8. Oracle manipulation possibilities

SEVERITY GUIDELINES:
- CRITICAL: Fund drainage, complete system compromise
- HIGH: Unauthorized access, significant financial loss
- MEDIUM: Denial of service, data integrity issues  
- LOW: Gas inefficiencies, minor security concerns
- INFO: Code quality, documentation issues

Return ONLY the JSON array, no other text.

CONTRACT CODE:
```solidity
{$sourceCode}
```
PROMPT;
    }

    /**
     * Get system prompt for security analysis.
     */
    private function getSystemPrompt(): string
    {
        return <<<SYSTEM
You are a senior blockchain security auditor specializing in smart contract vulnerability detection. 

Your expertise includes:
- OWASP security standards and risk assessment
- Smart Contract Weakness Classification (SWC) registry
- DeFi protocol security patterns
- Ethereum Virtual Machine (EVM) security
- Common attack vectors and historical incidents

Analyze code systematically and provide accurate, actionable security findings in the specified OWASP-style JSON format. Focus on real vulnerabilities that could lead to financial loss or system compromise.

Be precise with line numbers and provide clear, implementable recommendations.
SYSTEM;
    }

    /**
     * Parse JSON findings from OpenAI response.
     */
    private function parseFindings(string $content): array
    {
        // Clean up the response content
        $content = trim($content);
        $content = preg_replace('/^```json\s*/', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);
        
        try {
            $findings = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            
            if (!is_array($findings)) {
                throw new \Exception('Response is not an array');
            }
            
            return $findings;
            
        } catch (\JsonException $e) {
            Log::warning('Failed to parse JSON findings', [
                'content' => $content,
                'error' => $e->getMessage()
            ]);
            
            // Try to extract JSON from markdown or other formatting
            return $this->extractJsonFromResponse($content);
        }
    }

    /**
     * Extract JSON from response with various formatting.
     */
    private function extractJsonFromResponse(string $content): array
    {
        // Try to find JSON array in the response
        if (preg_match('/\[.*\]/s', $content, $matches)) {
            try {
                return json_decode($matches[0], true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                // Continue to fallback
            }
        }
        
        // Return empty array as fallback
        Log::error('Could not extract valid JSON from OpenAI response', [
            'content' => substr($content, 0, 500)
        ]);
        
        return [];
    }

    /**
     * Enrich findings with metadata.
     */
    private function enrichFindings(array &$findings, float $processingTime, array $usage): void
    {
        foreach ($findings as &$finding) {
            // Add unique ID if not present
            if (!isset($finding['id'])) {
                $finding['id'] = 'FIND-' . strtoupper(Str::random(8));
            }
            
            // Add AI metadata
            $finding['ai_model'] = $this->model;
            $finding['analysis_version'] = '1.0.0';
            $finding['tokens_used'] = $usage['total_tokens'] ?? 0;
            $finding['created_at'] = Carbon::now()->toISOString();
            
            // Set default status
            if (!isset($finding['status'])) {
                $finding['status'] = 'OPEN';
            }
            
            // Validate required fields
            $this->validateFinding($finding);
        }
    }

    /**
     * Validate finding has required OWASP fields.
     */
    private function validateFinding(array $finding): void
    {
        $required = ['severity', 'title', 'line', 'recommendation'];
        
        foreach ($required as $field) {
            if (!isset($finding[$field]) || empty($finding[$field])) {
                Log::warning('Finding missing required field', [
                    'field' => $field,
                    'finding_id' => $finding['id'] ?? 'unknown'
                ]);
            }
        }
        
        // Validate severity
        $validSeverities = ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW', 'INFO'];
        if (!in_array($finding['severity'] ?? '', $validSeverities)) {
            Log::warning('Invalid severity level', [
                'severity' => $finding['severity'] ?? 'null',
                'finding_id' => $finding['id'] ?? 'unknown'
            ]);
        }
    }

    /**
     * Generate security summary from findings.
     */
    public function generateSummary(array $findings): array
    {
        $summary = [
            'total_findings' => count($findings),
            'severity_breakdown' => [
                'CRITICAL' => 0,
                'HIGH' => 0,
                'MEDIUM' => 0,
                'LOW' => 0,
                'INFO' => 0
            ],
            'categories' => [],
            'remediation_priority' => [],
            'estimated_fix_effort' => 'UNKNOWN'
        ];

        foreach ($findings as $finding) {
            $severity = $finding['severity'] ?? 'UNKNOWN';
            if (isset($summary['severity_breakdown'][$severity])) {
                $summary['severity_breakdown'][$severity]++;
            }
            
            $category = $finding['category'] ?? 'Other';
            $summary['categories'][$category] = ($summary['categories'][$category] ?? 0) + 1;
            
            if (isset($finding['remediation_priority'])) {
                $priority = $finding['remediation_priority'];
                $summary['remediation_priority'][$priority] = ($summary['remediation_priority'][$priority] ?? 0) + 1;
            }
        }

        // Calculate overall risk score
        $riskScore = 
            $summary['severity_breakdown']['CRITICAL'] * 10 +
            $summary['severity_breakdown']['HIGH'] * 7 +
            $summary['severity_breakdown']['MEDIUM'] * 4 +
            $summary['severity_breakdown']['LOW'] * 1;
            
        $summary['overall_risk_score'] = $riskScore;
        $summary['risk_level'] = $this->getRiskLevel($riskScore);

        return $summary;
    }

    /**
     * Get risk level based on score.
     */
    private function getRiskLevel(int $score): string
    {
        if ($score >= 30) return 'CRITICAL';
        if ($score >= 20) return 'HIGH';
        if ($score >= 10) return 'MEDIUM';
        if ($score >= 5) return 'LOW';
        return 'MINIMAL';
    }

    /**
     * Get supported vulnerability categories.
     */
    public static function getSupportedCategories(): array
    {
        return [
            'Re-entrancy',
            'Integer Overflow/Underflow',
            'Access Control',
            'Input Validation',
            'Business Logic',
            'Cryptographic Issues',
            'Information Disclosure',
            'Denial of Service',
            'Front-running/MEV',
            'Oracle Manipulation',
            'Flash Loan Attack',
            'Gas Optimization',
            'Code Quality',
            'Upgrade Pattern Issue',
            'Proxy Vulnerability',
            'Timelock Bypass',
            'Governance Attack',
            'Cross-Chain Issue',
            'NFT Vulnerability',
            'DeFi Protocol Issue',
            'Other'
        ];
    }
}