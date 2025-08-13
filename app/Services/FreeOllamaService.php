<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Free Ollama Local LLM Service
 * Replaces OpenAI API with local Ollama models
 */
final class FreeOllamaService
{
    private string $ollamaUrl;
    private string $model;
    private int $timeout;

    public function __construct()
    {
        $this->ollamaUrl = config('services.ollama.url', 'http://localhost:11434');
        $this->model = config('services.ollama.model', 'codellama:13b-instruct');
        $this->timeout = config('services.ollama.timeout', 120);
    }

    /**
     * Analyze smart contract code for security vulnerabilities
     */
    public function analyzeSmartContract(string $contractCode): array
    {
        $cacheKey = 'ollama_analysis_' . md5($contractCode);
        
        return Cache::remember($cacheKey, 3600, function () use ($contractCode) {
            try {
                $prompt = $this->buildSecurityAnalysisPrompt($contractCode);
                $response = $this->generateCompletion($prompt);
                
                return $this->parseSecurityAnalysis($response);
                
            } catch (\Exception $e) {
                Log::error('Ollama analysis failed', [
                    'error' => $e->getMessage(),
                    'model' => $this->model
                ]);
                
                return $this->getFallbackAnalysis();
            }
        });
    }

    /**
     * Generate completion using Ollama API
     */
    private function generateCompletion(string $prompt): string
    {
        $response = Http::timeout($this->timeout)
            ->post($this->ollamaUrl . '/api/generate', [
                'model' => $this->model,
                'prompt' => $prompt,
                'stream' => false,
                'options' => [
                    'temperature' => 0.1,
                    'top_p' => 0.9,
                    'max_tokens' => 2048
                ]
            ]);

        if (!$response->successful()) {
            throw new \Exception('Ollama API request failed: ' . $response->body());
        }

        $data = $response->json();
        return $data['response'] ?? '';
    }

    /**
     * Build security analysis prompt for smart contracts
     */
    private function buildSecurityAnalysisPrompt(string $contractCode): string
    {
        return <<<PROMPT
You are a senior blockchain security auditor. Analyze this Solidity smart contract for security vulnerabilities.

ANALYZE FOR THESE VULNERABILITIES:
1. Reentrancy attacks
2. Integer overflow/underflow
3. Access control issues
4. Unchecked external calls
5. Timestamp dependence
6. Weak randomness
7. Denial of service
8. Front-running vulnerabilities
9. Logic errors
10. Gas optimization issues

CONTRACT CODE:
```solidity
{$contractCode}
```

RESPONSE FORMAT (JSON):
{
  "vulnerabilities": [
    {
      "type": "vulnerability_type",
      "severity": "CRITICAL|HIGH|MEDIUM|LOW",
      "line": 123,
      "description": "Description of the issue",
      "recommendation": "How to fix it"
    }
  ],
  "security_score": 85,
  "gas_optimization": {
    "score": 75,
    "suggestions": ["suggestion1", "suggestion2"]
  },
  "overall_assessment": "Overall security assessment"
}

Return ONLY valid JSON, no other text.
PROMPT;
    }

    /**
     * Parse security analysis response
     */
    private function parseSecurityAnalysis(string $response): array
    {
        // Try to extract JSON from response
        $jsonStart = strpos($response, '{');
        $jsonEnd = strrpos($response, '}');
        
        if ($jsonStart !== false && $jsonEnd !== false) {
            $jsonString = substr($response, $jsonStart, $jsonEnd - $jsonStart + 1);
            $parsed = json_decode($jsonString, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                return $this->normalizeAnalysisFormat($parsed);
            }
        }

        // Fallback: parse text response
        return $this->parseTextResponse($response);
    }

    /**
     * Normalize analysis format to match expected structure
     */
    private function normalizeAnalysisFormat(array $data): array
    {
        return [
            'success' => true,
            'analysis_method' => 'ollama_local',
            'model_used' => $this->model,
            'findings' => $data['vulnerabilities'] ?? [],
            'security_score' => $data['security_score'] ?? 75,
            'gas_score' => $data['gas_optimization']['score'] ?? 70,
            'recommendations' => $data['gas_optimization']['suggestions'] ?? [],
            'overall_assessment' => $data['overall_assessment'] ?? 'Analysis completed using local LLM',
            'cost' => 0.00, // FREE!
            'processing_time' => microtime(true) - LARAVEL_START
        ];
    }

    /**
     * Parse text response when JSON parsing fails
     */
    private function parseTextResponse(string $response): array
    {
        $vulnerabilities = [];
        $lines = explode("\n", $response);
        
        foreach ($lines as $line) {
            if (stripos($line, 'vulnerability') !== false || 
                stripos($line, 'issue') !== false ||
                stripos($line, 'problem') !== false) {
                
                $severity = 'MEDIUM';
                if (stripos($line, 'critical') !== false) $severity = 'CRITICAL';
                if (stripos($line, 'high') !== false) $severity = 'HIGH';
                if (stripos($line, 'low') !== false) $severity = 'LOW';
                
                $vulnerabilities[] = [
                    'type' => 'detected_issue',
                    'severity' => $severity,
                    'description' => trim($line),
                    'line' => null,
                    'recommendation' => 'Review and validate this finding'
                ];
            }
        }

        return [
            'success' => true,
            'analysis_method' => 'ollama_text_parsing',
            'model_used' => $this->model,
            'findings' => $vulnerabilities,
            'security_score' => count($vulnerabilities) > 0 ? 60 : 80,
            'gas_score' => 70,
            'recommendations' => ['Manual review recommended', 'Consider additional testing'],
            'overall_assessment' => 'Free local analysis completed',
            'cost' => 0.00,
            'processing_time' => 0
        ];
    }

    /**
     * Fallback analysis when Ollama is unavailable
     */
    private function getFallbackAnalysis(): array
    {
        return [
            'success' => false,
            'analysis_method' => 'basic_pattern_matching',
            'model_used' => 'fallback',
            'findings' => [
                [
                    'type' => 'service_unavailable',
                    'severity' => 'INFO',
                    'description' => 'Local LLM service unavailable. Please check Ollama installation.',
                    'line' => null,
                    'recommendation' => 'Install Ollama and pull a code analysis model'
                ]
            ],
            'security_score' => 50,
            'gas_score' => 50,
            'recommendations' => [
                'Install Ollama: curl -fsSL https://ollama.ai/install.sh | sh',
                'Pull CodeLlama model: ollama pull codellama:13b-instruct'
            ],
            'overall_assessment' => 'Basic analysis only - install Ollama for full features',
            'cost' => 0.00,
            'processing_time' => 0
        ];
    }

    /**
     * Check if Ollama service is available
     */
    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(5)->get($this->ollamaUrl . '/api/tags');
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get available models
     */
    public function getAvailableModels(): array
    {
        try {
            $response = Http::timeout(10)->get($this->ollamaUrl . '/api/tags');
            
            if ($response->successful()) {
                $data = $response->json();
                return collect($data['models'] ?? [])
                    ->pluck('name')
                    ->toArray();
            }
        } catch (\Exception $e) {
            Log::warning('Could not fetch Ollama models', ['error' => $e->getMessage()]);
        }

        return [];
    }
}
