<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use JsonSchema\Validator;
use JsonSchema\Constraints\Constraint;

class SecurityFindingValidator
{
    private object $schema;
    private Validator $validator;

    public function __construct()
    {
        $this->loadSchema();
        $this->validator = new Validator();
    }

    /**
     * Validate a security finding against the schema
     */
    public function validate(array $finding): array
    {
        $data = json_decode(json_encode($finding)); // Convert arrays to objects recursively
        $this->validator->validate($data, $this->schema, Constraint::CHECK_MODE_COERCE_TYPES);

        return [
            'valid' => $this->validator->isValid(),
            'errors' => $this->formatErrors($this->validator->getErrors()),
            'normalized' => $this->normalizeFinding($finding)
        ];
    }

    /**
     * Validate multiple findings
     */
    public function validateFindings(array $findings): array
    {
        $results = [
            'valid_count' => 0,
            'invalid_count' => 0,
            'findings' => [],
            'summary' => []
        ];

        foreach ($findings as $index => $finding) {
            $validation = $this->validate($finding);
            
            if ($validation['valid']) {
                $results['valid_count']++;
            } else {
                $results['invalid_count']++;
                Log::warning("Invalid security finding at index {$index}", [
                    'errors' => $validation['errors'],
                    'finding' => $finding
                ]);
            }
            
            $results['findings'][$index] = $validation;
        }

        $results['summary'] = [
            'total' => count($findings),
            'valid' => $results['valid_count'],
            'invalid' => $results['invalid_count'],
            'success_rate' => count($findings) > 0 ? 
                round(($results['valid_count'] / count($findings)) * 100, 2) : 0
        ];

        return $results;
    }

    /**
     * Create a valid finding template
     */
    public function createTemplate(array $overrides = []): array
    {
        $template = [
            'id' => $this->generateFindingId(),
            'severity' => 'MEDIUM',
            'title' => 'Security Finding',
            'category' => 'Other',
            'description' => 'A security vulnerability was detected in the smart contract.',
            'confidence' => 'MEDIUM',
            'location' => [
                'line' => 1
            ],
            'recommendation' => [
                'summary' => 'Review and fix the identified security issue.'
            ],
            'ai_metadata' => [
                'model' => 'gpt-4',
                'analysis_version' => '2.1.0',
                'detection_method' => 'LLM_ANALYSIS'
            ],
            'status' => 'OPEN',
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString()
        ];

        return array_merge($template, $overrides);
    }

    /**
     * Parse OpenAI response into structured findings
     */
    public function parseOpenAiResponse(string $response): array
    {
        $findings = [];
        
        // Try to extract JSON blocks first
        if (preg_match_all('/```json\s*(.*?)\s*```/s', $response, $matches)) {
            foreach ($matches[1] as $jsonBlock) {
                try {
                    $parsed = json_decode(trim($jsonBlock), true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                        if (isset($parsed['findings']) && is_array($parsed['findings'])) {
                            $findings = array_merge($findings, $parsed['findings']);
                        } elseif ($this->looksLikeFinding($parsed)) {
                            $findings[] = $parsed;
                        }
                    } else {
                        Log::warning('JSON decode error in OpenAI response', [
                            'json' => substr($jsonBlock, 0, 200),
                            'error' => json_last_error_msg()
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to parse JSON block in OpenAI response', [
                        'json' => substr($jsonBlock, 0, 200),
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        // Also try to find standalone JSON objects (improved pattern for nested objects)
        if (preg_match_all('/\{(?:[^{}]|{[^}]*})*"severity"(?:[^{}]|{[^}]*})*\}/s', $response, $matches)) {
            foreach ($matches[0] as $jsonObject) {
                try {
                    $parsed = json_decode($jsonObject, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($parsed) && $this->looksLikeFinding($parsed)) {
                        $findings[] = $parsed;
                    }
                } catch (\Exception $e) {
                    // Ignore parsing errors for standalone objects
                }
            }
        }

        // If no JSON found, try to parse text-based findings
        if (empty($findings)) {
            $findings = $this->parseTextFindings($response);
        }

        // Validate and normalize all findings
        $validatedFindings = [];
        foreach ($findings as $finding) {
            $validation = $this->validate($finding);
            if ($validation['valid']) {
                $validatedFindings[] = $validation['normalized'];
            } else {
                // Try to fix common issues and re-validate
                $fixed = $this->attemptFix($finding);
                $revalidation = $this->validate($fixed);
                if ($revalidation['valid']) {
                    $validatedFindings[] = $revalidation['normalized'];
                }
            }
        }

        return $validatedFindings;
    }

    /**
     * Load the JSON schema
     */
    private function loadSchema(string $version = 'v4'): void
    {
        $schemaPath = base_path("schemas/security-finding-{$version}-prompt-optimized.json");
        
        // Fallback chain for schema versions
        if (!file_exists($schemaPath)) {
            $fallbacks = [
                base_path("schemas/security-finding-{$version}.json"),
                base_path('schemas/security-finding-v3.json'),
                base_path('schemas/security-finding-v2.json')
            ];
            
            foreach ($fallbacks as $fallback) {
                if (file_exists($fallback)) {
                    $schemaPath = $fallback;
                    break;
                }
            }
        }
        
        if (!file_exists($schemaPath)) {
            throw new \RuntimeException("Security finding schema not found at: {$schemaPath}");
        }

        $schemaContent = file_get_contents($schemaPath);
        $this->schema = json_decode($schemaContent);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Invalid JSON in security finding schema: " . json_last_error_msg());
        }
        
        Log::info("Loaded security finding schema", ['version' => $version, 'path' => $schemaPath]);
    }

    /**
     * Format validation errors
     */
    private function formatErrors(array $errors): array
    {
        $formatted = [];
        
        foreach ($errors as $error) {
            $formatted[] = [
                'property' => $error['property'],
                'message' => $error['message'],
                'constraint' => $error['constraint'] ?? null
            ];
        }

        return $formatted;
    }

    /**
     * Normalize finding data
     */
    private function normalizeFinding(array $finding): array
    {
        // Ensure required fields have defaults
        $normalized = $finding;

        if (!isset($normalized['id'])) {
            $normalized['id'] = $this->generateFindingId();
        }

        if (!isset($normalized['created_at'])) {
            $normalized['created_at'] = now()->toISOString();
        }

        if (!isset($normalized['updated_at'])) {
            $normalized['updated_at'] = now()->toISOString();
        }

        if (!isset($normalized['status'])) {
            $normalized['status'] = 'OPEN';
        }

        // Normalize severity
        if (isset($normalized['severity'])) {
            $normalized['severity'] = strtoupper($normalized['severity']);
        }

        // Normalize confidence
        if (isset($normalized['confidence'])) {
            $normalized['confidence'] = strtoupper($normalized['confidence']);
        }

        return $normalized;
    }

    /**
     * Generate a finding ID
     */
    private function generateFindingId(): string
    {
        $uuid = \Illuminate\Support\Str::uuid()->toString();
        return strtoupper($uuid);
    }

    /**
     * Check if array looks like a finding
     */
    private function looksLikeFinding(array $data): bool
    {
        $requiredFields = ['severity', 'title', 'description'];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Parse text-based findings from OpenAI response
     */
    private function parseTextFindings(string $response): array
    {
        $findings = [];
        
        // Pattern to match findings in text format
        $patterns = [
            '/(?:^|\n)(?:##?\s*)?(?:Finding\s*#?\d*:?\s*)?(.+?)(?:\n|$).*?(?:Severity|Priority):\s*(\w+).*?(?:Description|Details?):\s*(.+?)(?:\n(?:Recommendation|Fix|Solution):\s*(.+?))?(?=\n(?:##|Finding|$)|\Z)/mis',
            '/(?:^|\n)(?:\*\*)?(.+?)(?:\*\*)?\s*\((\w+)\)\s*[:\-]\s*(.+?)(?:\n.*?(?:Fix|Recommendation):\s*(.+?))?(?=\n\*\*|\n[A-Z]|\Z)/mis'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $response, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $title = trim($match[1] ?? '');
                    $severity = strtoupper(trim($match[2] ?? 'MEDIUM'));
                    $description = trim($match[3] ?? '');
                    $recommendation = trim($match[4] ?? '');

                    if ($title && $description) {
                        $finding = $this->createTemplate([
                            'title' => $title,
                            'severity' => in_array($severity, ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW', 'INFO']) ? $severity : 'MEDIUM',
                            'description' => $description,
                            'category' => $this->inferCategory($title . ' ' . $description),
                            'confidence' => 'MEDIUM'
                        ]);

                        if ($recommendation) {
                            $finding['recommendation']['summary'] = $recommendation;
                        }

                        $findings[] = $finding;
                    }
                }
                break; // Use first successful pattern
            }
        }

        return $findings;
    }

    /**
     * Attempt to fix common validation issues
     */
    private function attemptFix(array $finding): array
    {
        $fixed = $finding;

        // Fix missing required fields
        if (!isset($fixed['id'])) {
            $fixed['id'] = $this->generateFindingId();
        }

        if (!isset($fixed['severity'])) {
            $fixed['severity'] = 'MEDIUM';
        }

        if (!isset($fixed['title'])) {
            $fixed['title'] = 'Security Finding';
        }

        if (!isset($fixed['category'])) {
            $fixed['category'] = 'Other';
        }

        if (!isset($fixed['description'])) {
            $fixed['description'] = 'A security issue was detected.';
        }

        if (!isset($fixed['confidence'])) {
            $fixed['confidence'] = 'MEDIUM';
        }

        // Fix invalid enums
        $severityMap = ['VERY_HIGH' => 'CRITICAL', 'VERY_LOW' => 'LOW'];
        if (isset($fixed['severity']) && isset($severityMap[$fixed['severity']])) {
            $fixed['severity'] = $severityMap[$fixed['severity']];
        }

        // Ensure minimum string lengths
        if (isset($fixed['title']) && strlen($fixed['title']) < 10) {
            $fixed['title'] = $fixed['title'] . ' vulnerability detected';
        }

        if (isset($fixed['description']) && strlen($fixed['description']) < 20) {
            $fixed['description'] = $fixed['description'] . ' This requires immediate attention and review.';
        }

        return $fixed;
    }

    /**
     * Infer category from finding text
     */
    private function inferCategory(string $text): string
    {
        $text = strtolower($text);
        
        $categoryMap = [
            'reentrancy' => 'SWC-107-Reentrancy',
            'reentrant' => 'SWC-107-Reentrancy',
            'overflow' => 'SWC-101-Integer Overflow and Underflow',
            'underflow' => 'SWC-101-Integer Overflow and Underflow',
            'access control' => 'A01:2021-Broken Access Control',
            'authentication' => 'A07:2021-Identification and Authentication Failures',
            'authorization' => 'A01:2021-Broken Access Control',
            'injection' => 'A03:2021-Injection',
            'crypto' => 'A02:2021-Cryptographic Failures',
            'randomness' => 'SWC-120-Weak Sources of Randomness from Chain Attributes',
            'timestamp' => 'SWC-116-Block values as a proxy for time',
            'gas' => 'Gas Optimization',
            'dos' => 'SWC-113-DoS with Failed Call',
            'denial of service' => 'SWC-113-DoS with Failed Call',
            'front.?run' => 'SWC-114-Transaction Order Dependence',
            'mev' => 'Front-running/MEV',
            'input' => 'Unvalidated Input',
            'validation' => 'Unvalidated Input',
            'delegatecall' => 'SWC-112-Delegatecall to Untrusted Callee',
            'tx.origin' => 'SWC-115-Authorization through tx.origin',
            'signature' => 'SWC-117-Signature Malleability',
            'oracle' => 'Oracle Manipulation',
            'flash.?loan' => 'Flash Loan Attack',
            'sandwich' => 'Sandwich Attack',
            'slippage' => 'Slippage Attack',
            'governance' => 'Governance Attack',
            'proxy' => 'Proxy Pattern Vulnerability',
            'upgrade' => 'Upgrade Pattern Issue',
            'multisig' => 'Multisig Vulnerability',
            'timelock' => 'Timelock Bypass'
        ];

        foreach ($categoryMap as $pattern => $category) {
            if (preg_match('/' . $pattern . '/i', $text)) {
                return $category;
            }
        }

        return 'Other';
    }

    /**
     * Get schema statistics
     */
    public function getSchemaStats(): array
    {
        $schemaVersion = $this->schema->title ?? 'Unknown';
        $version = preg_match('/v(\d+\.\d+)/', $schemaVersion, $matches) ? $matches[1] : '3.0';
        
        return [
            'version' => $version,
            'title' => $schemaVersion,
            'required_fields' => count($this->schema->required ?? []),
            'optional_fields' => count((array)$this->schema->properties) - count($this->schema->required ?? []),
            'total_fields' => count((array)$this->schema->properties),
            'severity_levels' => $this->schema->properties->severity->enum ?? [],
            'categories' => count($this->schema->properties->category->enum ?? []),
            'category_types' => [
                'owasp_top_10' => count(array_filter($this->schema->properties->category->enum ?? [], fn($cat) => str_starts_with($cat, 'A'))),
                'swc_registry' => count(array_filter($this->schema->properties->category->enum ?? [], fn($cat) => str_starts_with($cat, 'SWC-'))),
                'blockchain_specific' => count(array_filter($this->schema->properties->category->enum ?? [], fn($cat) => !str_starts_with($cat, 'A') && !str_starts_with($cat, 'SWC-')))
            ],
            'schema_size' => strlen(json_encode($this->schema)),
            'examples_count' => count($this->schema->examples ?? [])
        ];
    }

    /**
     * Create enhanced finding template with v3 schema support
     */
    public function createV3Template(array $overrides = []): array
    {
        $template = [
            'id' => $this->generateFindingId(),
            'severity' => 'MEDIUM',
            'title' => 'Security Finding Detected',
            'category' => 'Other',
            'description' => 'A security vulnerability was detected in the smart contract that requires review and remediation.',
            'confidence' => 'MEDIUM',
            'location' => [
                'line' => 1,
                'function' => 'unknown',
                'contract' => 'Contract'
            ],
            'recommendation' => [
                'summary' => 'Review and remediate the identified security issue following best practices.',
                'detailed_steps' => [
                    'Analyze the vulnerability in detail',
                    'Implement appropriate security controls',
                    'Test the fix thoroughly',
                    'Document the changes'
                ]
            ],
            'risk_assessment' => [
                'exploitability' => 'MODERATE',
                'impact' => [
                    'confidentiality' => 'LOW',
                    'integrity' => 'LOW',
                    'availability' => 'LOW',
                    'financial' => 'LOW'
                ],
                'likelihood' => 'MEDIUM'
            ],
            'blockchain_specific' => [
                'gas_impact' => [
                    'estimated_gas' => 0,
                    'gas_optimization' => false
                ],
                'token_standard' => 'N/A',
                'network_specific' => ['ETHEREUM']
            ],
            'ai_metadata' => [
                'model' => 'gpt-4',
                'analysis_version' => '3.1.0',
                'detection_method' => 'LLM_ANALYSIS',
                'prompt_version' => 'v3.0',
                'false_positive_probability' => 0.1
            ],
            'compliance' => [
                'standards' => ['OWASP_TOP_10', 'SMART_CONTRACT_WEAKNESS_CLASSIFICATION']
            ],
            'status' => 'OPEN',
            'tags' => ['security', 'automated-detection'],
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
            'version' => '3.0.0'
        ];

        return array_merge($template, $overrides);
    }
}