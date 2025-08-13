<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Multi-AI Analysis Service
 * Uses multiple free/trial AI services for smart contract analysis
 */
final class MultiAIAnalysisService
{
    private array $aiServices;
    private int $timeout;
    private HuggingFaceAIService $huggingFace;

    public function __construct(HuggingFaceAIService $huggingFace)
    {
        $this->huggingFace = $huggingFace;
        $this->timeout = 60;
        
        // AI services with free tiers/trials
        $this->aiServices = [
            'huggingface' => [
                'service' => $huggingFace,
                'free_limit' => '30,000 chars/month',
                'enabled' => true
            ],
            'claude' => [
                'url' => 'https://api.anthropic.com/v1/messages',
                'key' => env('ANTHROPIC_API_KEY', ''),
                'free_limit' => '$5 free credit',
                'enabled' => !empty(env('ANTHROPIC_API_KEY', ''))
            ],
            'cohere' => [
                'url' => 'https://api.cohere.ai/v1/generate',
                'key' => env('COHERE_API_KEY', ''),
                'free_limit' => '100 requests/minute',
                'enabled' => !empty(env('COHERE_API_KEY', ''))
            ],
            'ai21' => [
                'url' => 'https://api.ai21.com/studio/v1/j2-ultra/complete',
                'key' => env('AI21_API_KEY', ''),
                'free_limit' => '$10 free credit',
                'enabled' => !empty(env('AI21_API_KEY', ''))
            ],
            'replicate' => [
                'url' => 'https://api.replicate.com/v1/predictions',
                'key' => env('REPLICATE_API_TOKEN', ''),
                'free_limit' => '$10 free credit',
                'enabled' => !empty(env('REPLICATE_API_TOKEN', ''))
            ]
        ];
    }

    /**
     * Analyze smart contract using multiple AI services
     */
    public function analyzeSmartContract(string $contractCode): array
    {
        $cacheKey = 'multi_ai_analysis_' . md5($contractCode);
        
        return Cache::remember($cacheKey, 3600, function () use ($contractCode) {
            $results = [];
            $aggregatedFindings = [];
            
            try {
                // 1. Always use Hugging Face (primary free service)
                $hfResult = $this->huggingFace->analyzeSmartContract($contractCode);
                $results['huggingface'] = $hfResult;
                
                if (isset($hfResult['findings'])) {
                    $aggregatedFindings = array_merge($aggregatedFindings, $hfResult['findings']);
                }

                // 2. Try Claude if API key available
                if ($this->aiServices['claude']['enabled']) {
                    $claudeResult = $this->analyzeWithClaude($contractCode);
                    if ($claudeResult) {
                        $results['claude'] = $claudeResult;
                        if (isset($claudeResult['findings'])) {
                            $aggregatedFindings = array_merge($aggregatedFindings, $claudeResult['findings']);
                        }
                    }
                }

                // 3. Try Cohere if API key available
                if ($this->aiServices['cohere']['enabled']) {
                    $cohereResult = $this->analyzeWithCohere($contractCode);
                    if ($cohereResult) {
                        $results['cohere'] = $cohereResult;
                        if (isset($cohereResult['findings'])) {
                            $aggregatedFindings = array_merge($aggregatedFindings, $cohereResult['findings']);
                        }
                    }
                }

                // 4. Try AI21 if API key available
                if ($this->aiServices['ai21']['enabled']) {
                    $ai21Result = $this->analyzeWithAI21($contractCode);
                    if ($ai21Result) {
                        $results['ai21'] = $ai21Result;
                        if (isset($ai21Result['findings'])) {
                            $aggregatedFindings = array_merge($aggregatedFindings, $ai21Result['findings']);
                        }
                    }
                }

                // Aggregate all results
                $finalResult = $this->aggregateAnalysisResults($results, $aggregatedFindings);
                
                return $finalResult;
                
            } catch (\Exception $e) {
                Log::error('Multi-AI analysis failed', [
                    'error' => $e->getMessage(),
                    'contract_length' => strlen($contractCode)
                ]);
                
                return $this->getFallbackAnalysis($contractCode);
            }
        });
    }

    /**
     * Analyze with Claude (Anthropic)
     */
    private function analyzeWithClaude(string $contractCode): ?array
    {
        try {
            // Truncate code to stay within free tier limits
            $maxLength = 4000; // Conservative limit for free tier
            $codeSnippet = strlen($contractCode) > $maxLength 
                ? substr($contractCode, 0, $maxLength) . '...'
                : $contractCode;

            $prompt = $this->buildClaudePrompt($codeSnippet);

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'x-api-key' => $this->aiServices['claude']['key'],
                    'content-type' => 'application/json',
                    'anthropic-version' => '2023-06-01'
                ])
                ->post($this->aiServices['claude']['url'], [
                    'model' => 'claude-3-haiku-20240307', // Cheapest model
                    'max_tokens' => 1000,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ]
                ]);

            if (!$response->successful()) {
                Log::warning('Claude API request failed', ['status' => $response->status()]);
                return null;
            }

            $data = $response->json();
            return $this->parseClaudeResponse($data);

        } catch (\Exception $e) {
            Log::warning('Claude analysis failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Analyze with Cohere
     */
    private function analyzeWithCohere(string $contractCode): ?array
    {
        try {
            $maxLength = 2000; // Conservative limit
            $codeSnippet = strlen($contractCode) > $maxLength 
                ? substr($contractCode, 0, $maxLength) . '...'
                : $contractCode;

            $prompt = "Analyze this Solidity smart contract for security vulnerabilities:\n\n" . $codeSnippet;

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->aiServices['cohere']['key'],
                    'Content-Type' => 'application/json'
                ])
                ->post($this->aiServices['cohere']['url'], [
                    'model' => 'command-light', // Free tier model
                    'prompt' => $prompt,
                    'max_tokens' => 500,
                    'temperature' => 0.1
                ]);

            if (!$response->successful()) {
                Log::warning('Cohere API request failed', ['status' => $response->status()]);
                return null;
            }

            $data = $response->json();
            return $this->parseCohereResponse($data);

        } catch (\Exception $e) {
            Log::warning('Cohere analysis failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Analyze with AI21 Studio
     */
    private function analyzeWithAI21(string $contractCode): ?array
    {
        try {
            $maxLength = 2000;
            $codeSnippet = strlen($contractCode) > $maxLength 
                ? substr($contractCode, 0, $maxLength) . '...'
                : $contractCode;

            $prompt = "Analyze this Solidity code for security issues:\n\n" . $codeSnippet;

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->aiServices['ai21']['key'],
                    'Content-Type' => 'application/json'
                ])
                ->post($this->aiServices['ai21']['url'], [
                    'prompt' => $prompt,
                    'maxTokens' => 300,
                    'temperature' => 0.1
                ]);

            if (!$response->successful()) {
                Log::warning('AI21 API request failed', ['status' => $response->status()]);
                return null;
            }

            $data = $response->json();
            return $this->parseAI21Response($data);

        } catch (\Exception $e) {
            Log::warning('AI21 analysis failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Build Claude-specific prompt
     */
    private function buildClaudePrompt(string $contractCode): string
    {
        return <<<PROMPT
You are a blockchain security expert. Analyze this Solidity smart contract for security vulnerabilities.

Focus on these critical issues:
1. Reentrancy attacks
2. Integer overflow/underflow
3. Access control problems
4. Unchecked external calls
5. Gas optimization issues

Contract code:
```solidity
{$contractCode}
```

Respond with a JSON object containing:
{
  "vulnerabilities": [
    {
      "type": "vulnerability_name",
      "severity": "CRITICAL|HIGH|MEDIUM|LOW",
      "description": "Issue description",
      "recommendation": "How to fix"
    }
  ],
  "security_score": 85,
  "summary": "Overall assessment"
}

Provide only valid JSON, no other text.
PROMPT;
    }

    /**
     * Parse Claude response
     */
    private function parseClaudeResponse(array $data): array
    {
        if (!isset($data['content'][0]['text'])) {
            return ['findings' => [], 'service' => 'claude', 'success' => false];
        }

        $text = $data['content'][0]['text'];
        
        // Try to extract JSON from response
        $jsonStart = strpos($text, '{');
        $jsonEnd = strrpos($text, '}');
        
        if ($jsonStart !== false && $jsonEnd !== false) {
            $jsonString = substr($text, $jsonStart, $jsonEnd - $jsonStart + 1);
            $parsed = json_decode($jsonString, true);
            
            if (json_last_error() === JSON_ERROR_NONE && isset($parsed['vulnerabilities'])) {
                return [
                    'findings' => $parsed['vulnerabilities'],
                    'security_score' => $parsed['security_score'] ?? 75,
                    'summary' => $parsed['summary'] ?? 'Analysis completed',
                    'service' => 'claude',
                    'success' => true
                ];
            }
        }

        // Fallback to text parsing
        return $this->parseTextForVulnerabilities($text, 'claude');
    }

    /**
     * Parse Cohere response
     */
    private function parseCohereResponse(array $data): array
    {
        if (!isset($data['generations'][0]['text'])) {
            return ['findings' => [], 'service' => 'cohere', 'success' => false];
        }

        $text = $data['generations'][0]['text'];
        return $this->parseTextForVulnerabilities($text, 'cohere');
    }

    /**
     * Parse AI21 response
     */
    private function parseAI21Response(array $data): array
    {
        if (!isset($data['completions'][0]['data']['text'])) {
            return ['findings' => [], 'service' => 'ai21', 'success' => false];
        }

        $text = $data['completions'][0]['data']['text'];
        return $this->parseTextForVulnerabilities($text, 'ai21');
    }

    /**
     * Parse text for vulnerability keywords
     */
    private function parseTextForVulnerabilities(string $text, string $service): array
    {
        $vulnerabilities = [];
        $lines = explode("\n", $text);
        
        $vulnKeywords = [
            'reentrancy' => 'HIGH',
            'overflow' => 'MEDIUM',
            'underflow' => 'MEDIUM',
            'access control' => 'HIGH',
            'external call' => 'MEDIUM',
            'tx.origin' => 'HIGH',
            'selfdestruct' => 'MEDIUM',
            'delegatecall' => 'HIGH',
            'timestamp' => 'LOW',
            'randomness' => 'MEDIUM'
        ];
        
        foreach ($lines as $line) {
            foreach ($vulnKeywords as $keyword => $severity) {
                if (stripos($line, $keyword) !== false) {
                    $vulnerabilities[] = [
                        'type' => str_replace(' ', '_', $keyword),
                        'severity' => $severity,
                        'description' => trim($line),
                        'recommendation' => "Review {$keyword} implementation",
                        'source' => $service
                    ];
                }
            }
        }

        return [
            'findings' => $vulnerabilities,
            'security_score' => count($vulnerabilities) > 0 ? 70 : 85,
            'summary' => "Analysis completed by {$service}",
            'service' => $service,
            'success' => true
        ];
    }

    /**
     * Aggregate results from multiple AI services
     */
    private function aggregateAnalysisResults(array $results, array $allFindings): array
    {
        $servicesUsed = array_keys($results);
        $totalFindings = count($allFindings);
        
        // Calculate average security score
        $scores = [];
        foreach ($results as $result) {
            if (isset($result['security_score'])) {
                $scores[] = $result['security_score'];
            }
        }
        
        $avgSecurityScore = !empty($scores) ? array_sum($scores) / count($scores) : 75;
        
        // Deduplicate similar findings
        $uniqueFindings = $this->deduplicateFindings($allFindings);
        
        return [
            'success' => true,
            'analysis_method' => 'multi_ai_consensus',
            'services_used' => $servicesUsed,
            'individual_results' => $results,
            'findings' => $uniqueFindings,
            'security_score' => round($avgSecurityScore),
            'gas_score' => 75, // Default gas score
            'recommendations' => $this->generateConsolidatedRecommendations($uniqueFindings),
            'overall_assessment' => "Analysis completed using " . count($servicesUsed) . " AI services",
            'consensus_strength' => $this->calculateConsensusStrength($results),
            'cost' => 0.00, // Using free tiers
            'processing_time' => microtime(true) - LARAVEL_START
        ];
    }

    /**
     * Deduplicate similar findings
     */
    private function deduplicateFindings(array $findings): array
    {
        $unique = [];
        $seen = [];
        
        foreach ($findings as $finding) {
            $key = strtolower($finding['type'] ?? '') . '_' . strtolower($finding['severity'] ?? '');
            
            if (!in_array($key, $seen)) {
                $unique[] = $finding;
                $seen[] = $key;
            }
        }
        
        return $unique;
    }

    /**
     * Calculate consensus strength
     */
    private function calculateConsensusStrength(array $results): float
    {
        if (count($results) < 2) {
            return 0.5; // Low confidence with single source
        }
        
        $scores = [];
        foreach ($results as $result) {
            if (isset($result['security_score'])) {
                $scores[] = $result['security_score'];
            }
        }
        
        if (count($scores) < 2) {
            return 0.5;
        }
        
        // Calculate standard deviation
        $mean = array_sum($scores) / count($scores);
        $variance = array_sum(array_map(function($x) use ($mean) { return pow($x - $mean, 2); }, $scores)) / count($scores);
        $stdDev = sqrt($variance);
        
        // Higher consensus = lower standard deviation
        return max(0, min(1, 1 - ($stdDev / 50))); // Normalize to 0-1
    }

    /**
     * Generate consolidated recommendations
     */
    private function generateConsolidatedRecommendations(array $findings): array
    {
        $recommendations = [
            'Use latest Solidity version (^0.8.0) for built-in overflow protection',
            'Implement proper access controls with OpenZeppelin contracts',
            'Follow the checks-effects-interactions pattern'
        ];
        
        // Add specific recommendations based on findings
        foreach ($findings as $finding) {
            $type = $finding['type'] ?? '';
            
            if (stripos($type, 'reentrancy') !== false) {
                $recommendations[] = 'Use OpenZeppelin ReentrancyGuard modifier';
            }
            
            if (stripos($type, 'access') !== false) {
                $recommendations[] = 'Implement role-based access control';
            }
            
            if (stripos($type, 'external') !== false) {
                $recommendations[] = 'Validate all external call return values';
            }
        }
        
        return array_unique($recommendations);
    }

    /**
     * Get fallback analysis
     */
    private function getFallbackAnalysis(string $contractCode): array
    {
        // Use Hugging Face as fallback
        return $this->huggingFace->analyzeSmartContract($contractCode);
    }

    /**
     * Get service status
     */
    public function getServiceStatus(): array
    {
        $status = [];
        
        foreach ($this->aiServices as $name => $config) {
            if ($name === 'huggingface') {
                $status[$name] = [
                    'enabled' => true,
                    'available' => $this->huggingFace->isAvailable(),
                    'free_limit' => $config['free_limit']
                ];
            } else {
                $status[$name] = [
                    'enabled' => $config['enabled'],
                    'requires_api_key' => true,
                    'has_api_key' => !empty($config['key']),
                    'free_limit' => $config['free_limit']
                ];
            }
        }
        
        return $status;
    }
}
