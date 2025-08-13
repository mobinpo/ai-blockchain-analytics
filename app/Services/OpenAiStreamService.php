<?php

namespace App\Services;

use OpenAI;
use OpenAI\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use App\Events\TokenStreamed;
use App\Services\SecurityFindingValidator;

class OpenAiStreamService
{
    private string $model;
    private int $maxTokens;
    private float $temperature;
    private ?Client $client;

    public function __construct(
        string $model = 'gpt-4',
        int $maxTokens = 2000,
        float $temperature = 0.7
    ) {
        $this->model = $model;
        $this->maxTokens = $maxTokens;
        $this->temperature = $temperature;
        $apiKey = config('services.openai.key');
        if ($apiKey) {
            $this->client = OpenAI::client($apiKey);
        } else {
            Log::warning('OpenAI API key not configured - service will operate in simulation mode');
            $this->client = null;
        }
    }

    /**
     * Stream security analysis with real-time token updates
     */
    public function streamSecurityAnalysis(
        string $prompt,
        string $analysisId,
        array $context = []
    ): string {
        $fullResponse = '';
        $tokenCount = 0;
        $startTime = microtime(true);
        
        try {
            Log::info("Starting OpenAI security analysis stream", [
                'analysis_id' => $analysisId,
                'model' => $this->model,
                'max_tokens' => $this->maxTokens
            ]);
            
            // Initialize streaming cache
            Cache::put("openai_stream_{$analysisId}", [
                'status' => 'streaming',
                'tokens_received' => 0,
                'content' => '',
                'started_at' => now()->toISOString(),
                'context' => $context
            ], 3600);

            $stream = $this->client->chat()->createStreamed([
                'model' => $this->model,
                'messages' => $this->buildMessages($prompt, $context),
                'max_tokens' => $this->maxTokens,
                'temperature' => $this->temperature,
                'stream' => true,
            ]);

            foreach ($stream as $response) {
                $delta = $response->choices[0]->delta ?? null;
                
                if ($delta && isset($delta->content)) {
                    $content = $delta->content;
                    $fullResponse .= $content;
                    $tokenCount++;
                    
                    // Update cache with new token
                    Cache::put("openai_stream_{$analysisId}", [
                        'status' => 'streaming',
                        'tokens_received' => $tokenCount,
                        'content' => $fullResponse,
                        'last_token' => $content,
                        'updated_at' => now()->toISOString(),
                        'context' => $context
                    ], 3600);
                    
                    // Broadcast token to real-time listeners
                    Event::dispatch(new TokenStreamed($analysisId, $content, $tokenCount, $fullResponse, [
                        'model' => $this->model,
                        'processing_time_ms' => (microtime(true) - $startTime) * 1000
                    ]));
                    
                    // Log progress every 10 tokens
                    if ($tokenCount % 10 === 0) {
                        Log::debug("OpenAI streaming progress for analysis {$analysisId}: {$tokenCount} tokens");
                    }
                }
                
                // Check for finish reason
                if (isset($response->choices[0]->finishReason)) {
                    Log::info("OpenAI stream completed for analysis {$analysisId}. Reason: {$response->choices[0]->finishReason}");
                    break;
                }
            }
            
            // Mark as completed
            $endTime = microtime(true);
            $processingTimeMs = ($endTime - $startTime) * 1000;
            
            Cache::put("openai_stream_{$analysisId}", [
                'status' => 'completed',
                'tokens_received' => $tokenCount,
                'content' => $fullResponse,
                'completed_at' => now()->toISOString(),
                'processing_time_ms' => $processingTimeMs,
                'finish_reason' => $response->choices[0]->finishReason ?? 'unknown',
                'context' => $context
            ], 3600);
            
            Log::info("OpenAI stream finished for analysis {$analysisId}. Total tokens: {$tokenCount}, Time: {$processingTimeMs}ms");
            
        } catch (\Exception $e) {
            $endTime = microtime(true);
            $processingTimeMs = ($endTime - $startTime) * 1000;
            
            Log::error("OpenAI streaming error for analysis {$analysisId}: " . $e->getMessage());
            
            Cache::put("openai_stream_{$analysisId}", [
                'status' => 'failed',
                'tokens_received' => $tokenCount,
                'content' => $fullResponse,
                'error' => $e->getMessage(),
                'failed_at' => now()->toISOString(),
                'processing_time_ms' => $processingTimeMs,
                'context' => $context
            ], 3600);
            
            throw $e;
        }
        
        return $fullResponse;
    }

    /**
     * Get streaming status for a job
     */
    public function getStreamStatus(string $jobId): ?array
    {
        return Cache::get("openai_stream_{$jobId}");
    }

    /**
     * Build messages array for OpenAI API
     */
    private function buildMessages(string $prompt, array $context): array
    {
        $messages = [];
        
        // Add system message if provided in context
        if (!empty($context['system_prompt'])) {
            $messages[] = [
                'role' => 'system',
                'content' => $context['system_prompt']
            ];
        }
        
        // Add conversation history if provided
        if (!empty($context['history'])) {
            foreach ($context['history'] as $message) {
                $messages[] = [
                    'role' => $message['role'],
                    'content' => $message['content']
                ];
            }
        }
        
        // Add the main prompt
        $messages[] = [
            'role' => 'user',
            'content' => $prompt
        ];
        
        return $messages;
    }

    /**
     * Analyze blockchain code with streaming
     */
    public function analyzeBlockchainCode(
        string $code,
        string $jobId,
        string $analysisType = 'security'
    ): string {
        $systemPrompt = $this->getAnalysisSystemPrompt($analysisType);
        $structuredPrompt = $this->getStructuredAnalysisPrompt($code, $analysisType);
        
        return $this->streamCompletion($structuredPrompt, $jobId, [
            'system_prompt' => $systemPrompt
        ]);
    }

    /**
     * Analyze blockchain code and return structured findings
     */
    public function analyzeBlockchainCodeStructured(
        string $code,
        string $jobId,
        string $analysisType = 'security'
    ): array {
        $response = $this->analyzeBlockchainCode($code, $jobId, $analysisType);
        
        $validator = new SecurityFindingValidator();
        $findings = $validator->parseOpenAiResponse($response);
        
        // Validate all findings
        $validationResult = $validator->validateFindings($findings);
        
        Log::info("Structured analysis completed for job {$jobId}", [
            'total_findings' => count($findings),
            'valid_findings' => $validationResult['valid_count'],
            'invalid_findings' => $validationResult['invalid_count'],
            'success_rate' => $validationResult['summary']['success_rate']
        ]);
        
        return [
            'findings' => array_column($validationResult['findings'], 'normalized'),
            'validation_summary' => $validationResult['summary'],
            'raw_response' => $response
        ];
    }

    /**
     * Get structured analysis prompt with JSON schema requirements
     */
    private function getStructuredAnalysisPrompt(string $code, string $analysisType): string
    {
        $schemaExample = $this->getSchemaExample();
        
        return "Analyze the following smart contract code and provide structured findings in JSON format.

**Smart Contract Code:**
```solidity
{$code}
```

**Analysis Requirements:**
1. Identify all security vulnerabilities, code quality issues, and optimization opportunities
2. For each finding, provide structured data following the JSON schema below
3. Include severity (CRITICAL/HIGH/MEDIUM/LOW/INFO), location, and detailed recommendations
4. Ensure all findings are actionable and specific

**JSON Schema Format:**
Return your findings as a JSON array of objects, where each finding follows this structure:

```json
{$schemaExample}
```

**Output Format:**
Return ONLY valid JSON in the following format:
```json
{
  \"findings\": [
    // Array of finding objects following the schema above
  ]
}
```

Focus on " . match($analysisType) {
            'security' => 'security vulnerabilities, access control issues, and potential exploits',
            'gas' => 'gas optimization opportunities and efficiency improvements', 
            'code_quality' => 'code quality issues, best practices, and maintainability concerns',
            default => 'comprehensive analysis including security, gas, and quality issues'
        } . ". Provide specific line numbers and actionable recommendations for each finding.";
    }

    /**
     * Get system prompt for different analysis types
     */
    private function getAnalysisSystemPrompt(string $analysisType): string
    {
        return match ($analysisType) {
            'security' => 'You are an expert blockchain security auditor specializing in smart contract vulnerabilities. You have extensive knowledge of OWASP Top 10, common smart contract attack vectors, and security best practices. Analyze code thoroughly and provide actionable security findings with precise severity ratings.',
            'gas' => 'You are a gas optimization expert with deep knowledge of EVM internals. Identify gas inefficiencies, optimization opportunities, and provide specific recommendations to reduce transaction costs.',
            'code_quality' => 'You are a smart contract code quality expert. Focus on code maintainability, readability, best practices, and development standards. Provide recommendations for improving code structure and documentation.',
            default => 'You are a comprehensive blockchain code auditor with expertise in security, gas optimization, and code quality. Provide thorough analysis across all these dimensions.'
        };
    }

    /**
     * Get example schema for structured output
     */
    private function getSchemaExample(): string
    {
        return '{
  "id": "F7A8B2C3-4D5E-6F7A-8B9C-0D1E2F3A4B5C",
  "severity": "HIGH",
  "title": "Re-entrancy vulnerability in withdraw function",
  "category": "Re-entrancy",
  "description": "The withdraw function performs external call before state change...",
  "confidence": "HIGH",
  "location": {
    "line": 125,
    "function": "withdraw",
    "contract": "VulnerableBank",
    "code_snippet": "msg.sender.call{value: amount}(\"\");"
  },
  "recommendation": {
    "summary": "Implement checks-effects-interactions pattern",
    "detailed_steps": [
      "Move state changes before external calls",
      "Add reentrancy guard modifier"
    ],
    "code_fix": "balances[msg.sender] -= amount; // State change first"
  },
  "risk_assessment": {
    "exploitability": "EASY",
    "impact": {
      "confidentiality": "NONE",
      "integrity": "HIGH", 
      "availability": "HIGH",
      "financial": "HIGH"
    }
  },
  "blockchain_specific": {
    "network_specific": ["ETHEREUM", "POLYGON"]
  },
  "tags": ["reentrancy", "external-call", "critical"]
}';
    }

    /**
     * Configure service parameters
     */
    public function configure(array $config): self
    {
        $this->model = $config['model'] ?? $this->model;
        $this->maxTokens = $config['max_tokens'] ?? $this->maxTokens;
        $this->temperature = $config['temperature'] ?? $this->temperature;
        
        return $this;
    }
}