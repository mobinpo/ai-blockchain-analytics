<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TestPromptOptimizedSecurity extends Command
{
    protected $signature = 'security:test-prompt-optimized 
                           {--example= : Example to test (reentrancy,overflow,flashloan,gas)}
                           {--validate : Validate against schema}
                           {--generate= : Generate new example}
                           {--ai-format : Show AI-optimized format}';

    protected $description = 'Test and demonstrate the prompt-optimized OWASP security schema';

    public function handle(): int
    {
        $this->displayHeader();

        $example = $this->option('example') ?: 'reentrancy';
        $validate = $this->option('validate');
        $generate = $this->option('generate');
        $aiFormat = $this->option('ai-format');

        try {
            if ($generate) {
                return $this->generateExample($example);
            }

            if ($validate) {
                return $this->validateSchema();
            }

            if ($aiFormat) {
                return $this->showAIFormat();
            }

            return $this->demonstrateExample($example);

        } catch (\Exception $e) {
            $this->error('❌ Test failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function displayHeader(): void
    {
        $this->info('🛡️ Prompt-Optimized OWASP Security Schema Demo');
        $this->info('Enhanced JSON schema for AI-powered security analysis');
        $this->newLine();
    }

    private function demonstrateExample(string $example): int
    {
        $this->info("📊 Demonstrating: {$example} vulnerability example");
        $this->newLine();

        // Load examples
        $examplesPath = base_path('examples/prompt-optimized-findings.json');
        if (!File::exists($examplesPath)) {
            $this->error("Examples file not found: {$examplesPath}");
            return Command::FAILURE;
        }

        $examples = json_decode(File::get($examplesPath), true);
        if (!$examples) {
            $this->error('Failed to parse examples JSON');
            return Command::FAILURE;
        }

        // Find matching example
        $targetExample = $this->findExample($examples, $example);
        if (!$targetExample) {
            $this->error("Example '{$example}' not found");
            $this->showAvailableExamples($examples);
            return Command::FAILURE;
        }

        // Display the example
        $this->displayExample($targetExample);
        $this->displayEnhancements($targetExample);
        $this->displayAIOptimizations($targetExample);

        return Command::SUCCESS;
    }

    private function findExample(array $examples, string $type): ?array
    {
        foreach ($examples as $example) {
            $category = strtolower($example['category'] ?? '');
            $title = strtolower($example['title'] ?? '');
            
            switch ($type) {
                case 'reentrancy':
                    if (str_contains($category, 're-entrancy') || str_contains($title, 're-entrancy') || 
                        str_contains($category, 'reentrancy') || str_contains($title, 'reentrancy')) {
                        return $example;
                    }
                    break;
                case 'overflow':
                    if (str_contains($category, 'overflow') || str_contains($title, 'overflow')) {
                        return $example;
                    }
                    break;
                case 'flashloan':
                    if (str_contains($category, 'flash') || str_contains($title, 'flash')) {
                        return $example;
                    }
                    break;
                case 'gas':
                    if (str_contains($category, 'gas') || str_contains($title, 'gas')) {
                        return $example;
                    }
                    break;
            }
        }
        
        return null;
    }

    private function displayExample(array $example): void
    {
        $this->info('🔍 Vulnerability Details:');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $this->table(
            ['Property', 'Value'],
            [
                ['ID', $example['id']],
                ['Severity', $this->colorize($example['severity'], $example['severity'])],
                ['Title', $example['title']],
                ['Category', $example['category']],
                ['Line', $example['line']],
                ['File', $example['location']['file'] ?? 'N/A'],
                ['Function', $example['location']['function'] ?? 'N/A'],
                ['Contract', $example['location']['contract'] ?? 'N/A'],
            ]
        );

        // Display structured recommendation
        $this->newLine();
        $this->info('💡 Structured Recommendation:');
        $recommendation = $example['recommendation'];
        
        $this->line("Summary: {$recommendation['summary']}");
        $this->newLine();
        
        $this->line('Detailed Steps:');
        foreach ($recommendation['detailed_steps'] as $i => $step) {
            $this->line("  " . ($i + 1) . ". {$step}");
        }

        if (!empty($recommendation['code_changes'])) {
            $this->newLine();
            $this->line('Code Changes:');
            foreach ($recommendation['code_changes'] as $change) {
                $this->line("  • {$change['action']}: {$change['explanation']}");
                if (!empty($change['old_code'])) {
                    $this->line("    Before: {$change['old_code']}");
                }
                if (!empty($change['new_code'])) {
                    $this->line("    After: {$change['new_code']}");
                }
            }
        }

        $this->newLine();
        $this->line("Estimated Time: {$recommendation['estimated_time']}");
    }

    private function displayEnhancements(array $example): void
    {
        $this->newLine();
        $this->info('🚀 Schema Enhancements:');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        // Enhanced Impact Assessment
        if (!empty($example['impact'])) {
            $impact = $example['impact'];
            $this->line("Impact Assessment:");
            $this->line("  • Primary: {$impact['primary']}");
            
            if (!empty($impact['financial_estimate'])) {
                $min = number_format($impact['financial_estimate']['min_usd']);
                $max = number_format($impact['financial_estimate']['max_usd']);
                $confidence = $impact['financial_estimate']['confidence'];
                $this->line("  • Financial: \${$min} - \${$max} ({$confidence} confidence)");
            }
            
            $this->line("  • Affected Users: {$impact['affected_users']}");
        }

        // Attack Scenario
        if (!empty($example['attack_scenario'])) {
            $this->newLine();
            $this->line("Attack Scenario:");
            $scenario = $example['attack_scenario'];
            
            foreach ($scenario['step_by_step'] as $step) {
                $actor = $this->colorizeActor($step['actor']);
                $this->line("  {$step['step']}. [{$actor}] {$step['action']}");
                if (!empty($step['technical_detail'])) {
                    $this->line("     → {$step['technical_detail']}");
                }
            }
            
            $this->line("  Skill Level: {$scenario['skill_level']}");
        }

        // Confidence Metrics
        if (!empty($example['confidence_metrics'])) {
            $this->newLine();
            $this->line("AI Confidence Metrics:");
            $confidence = $example['confidence_metrics'];
            
            $this->line("  • Overall: {$confidence['overall_confidence']}");
            $this->line("  • False Positive Risk: {$confidence['false_positive_risk']}");
            $this->line("  • Pattern Match: " . round($confidence['pattern_match_score'] * 100) . '%');
            $this->line("  • Context Relevance: " . round($confidence['context_relevance'] * 100) . '%');
        }
    }

    private function displayAIOptimizations(array $example): void
    {
        $this->newLine();
        $this->info('🤖 AI Optimization Features:');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        // Standards Compliance
        if (!empty($example['standards_compliance'])) {
            $standards = $example['standards_compliance'];
            $this->line("Standards Mapping:");
            
            if (!empty($standards['cvss_score'])) {
                $this->line("  • CVSS Score: {$standards['cvss_score']}/10.0");
            }
            if (!empty($standards['swc_id'])) {
                $this->line("  • SWC ID: {$standards['swc_id']}");
            }
            if (!empty($standards['cwe_id'])) {
                $this->line("  • CWE ID: {$standards['cwe_id']}");
            }
        }

        // AI Metadata
        if (!empty($example['ai_metadata'])) {
            $ai = $example['ai_metadata'];
            $this->newLine();
            $this->line("AI Analysis Metadata:");
            $this->line("  • Model: {$ai['model_name']}");
            $this->line("  • Version: {$ai['analysis_version']}");
            
            if (!empty($ai['tokens_used']['total'])) {
                $this->line("  • Tokens Used: " . number_format($ai['tokens_used']['total']));
            }
            
            if (!empty($ai['processing_time_ms'])) {
                $this->line("  • Processing Time: {$ai['processing_time_ms']}ms");
            }
        }

        // Blockchain Context
        if (!empty($example['blockchain_context'])) {
            $blockchain = $example['blockchain_context'];
            $this->newLine();
            $this->line("Blockchain Context:");
            
            if (!empty($blockchain['networks'])) {
                $this->line("  • Networks: " . implode(', ', $blockchain['networks']));
            }
            
            if (!empty($blockchain['gas_implications'])) {
                $gas = $blockchain['gas_implications'];
                if (!empty($gas['exploitation_cost'])) {
                    $this->line("  • Exploitation Cost: " . number_format($gas['exploitation_cost']) . " gas");
                }
                if (!empty($gas['optimization_savings'])) {
                    $this->line("  • Optimization Savings: " . number_format($gas['optimization_savings']) . " gas");
                }
            }
        }

        // Tags
        if (!empty($example['tags'])) {
            $this->newLine();
            $this->line("Tags: " . implode(', ', $example['tags']));
        }
    }

    private function validateSchema(): int
    {
        $this->info('🔍 Validating prompt-optimized schema...');
        
        $schemaPath = base_path('schemas/security-finding-prompt-optimized.json');
        $examplesPath = base_path('examples/prompt-optimized-findings.json');
        
        if (!File::exists($schemaPath)) {
            $this->error("Schema file not found: {$schemaPath}");
            return Command::FAILURE;
        }
        
        if (!File::exists($examplesPath)) {
            $this->error("Examples file not found: {$examplesPath}");
            return Command::FAILURE;
        }
        
        $schema = json_decode(File::get($schemaPath), true);
        $examples = json_decode(File::get($examplesPath), true);
        
        if (!$schema || !$examples) {
            $this->error('Failed to parse JSON files');
            return Command::FAILURE;
        }
        
        $this->line("✅ Schema loaded successfully");
        $this->line("✅ Examples loaded successfully (" . count($examples) . " findings)");
        
        // Basic validation
        $required = $schema['required'] ?? [];
        $validCount = 0;
        
        foreach ($examples as $i => $example) {
            $missing = [];
            foreach ($required as $field) {
                if (!isset($example[$field])) {
                    $missing[] = $field;
                }
            }
            
            if (empty($missing)) {
                $validCount++;
            } else {
                $this->warn("Example {$i}: Missing fields: " . implode(', ', $missing));
            }
        }
        
        $this->info("✅ {$validCount}/" . count($examples) . " examples pass basic validation");
        
        return Command::SUCCESS;
    }

    private function generateExample(string $type): int
    {
        $this->info("🔧 Generating new {$type} example...");
        
        $template = match($type) {
            'reentrancy' => $this->getReentrancyTemplate(),
            'overflow' => $this->getOverflowTemplate(),
            'access' => $this->getAccessControlTemplate(),
            default => $this->getGenericTemplate($type)
        };
        
        $this->line(json_encode($template, JSON_PRETTY_PRINT));
        
        return Command::SUCCESS;
    }

    private function showAIFormat(): int
    {
        $this->info('🤖 AI-Optimized Format Example:');
        $this->newLine();
        
        $prompt = $this->getAIPromptTemplate();
        $this->line($prompt);
        
        return Command::SUCCESS;
    }

    private function showAvailableExamples(array $examples): void
    {
        $this->newLine();
        $this->info('Available examples:');
        
        foreach ($examples as $example) {
            $this->line("  • {$example['id']}: {$example['title']} ({$example['category']})");
        }
    }

    private function colorize(string $text, string $severity): string
    {
        return match($severity) {
            'CRITICAL' => "<error>{$text}</error>",
            'HIGH' => "<fg=red>{$text}</>",
            'MEDIUM' => "<fg=yellow>{$text}</>",
            'LOW' => "<fg=blue>{$text}</>",
            'INFO' => "<fg=green>{$text}</>",
            default => $text
        };
    }

    private function colorizeActor(string $actor): string
    {
        return match($actor) {
            'ATTACKER' => "<fg=red>{$actor}</>",
            'VICTIM' => "<fg=yellow>{$actor}</>",
            'CONTRACT' => "<fg=blue>{$actor}</>",
            'EXTERNAL_SERVICE' => "<fg=green>{$actor}</>",
            default => $actor
        };
    }

    private function getReentrancyTemplate(): array
    {
        return [
            "severity" => "HIGH",
            "title" => "Re-entrancy vulnerability",
            "line" => 125,
            "recommendation" => "Implement checks-effects-interactions pattern and add ReentrancyGuard modifier to prevent recursive calls during external interactions.",
            "category" => "Re-entrancy",
            "description" => "External call executed before state update enables classic re-entrancy attack vector",
            "impact" => "FUND_DRAINAGE",
            "exploitability" => "EASY",
            "confidence" => "HIGH"
        ];
    }

    private function getOverflowTemplate(): array
    {
        return [
            "severity" => "MEDIUM", 
            "title" => "Integer overflow in calculation",
            "line" => 89,
            "recommendation" => "Use SafeMath library or upgrade to Solidity 0.8+ with built-in overflow protection for all arithmetic operations.",
            "category" => "Integer Overflow/Underflow",
            "description" => "Arithmetic operation can overflow when processing large values without bounds checking",
            "impact" => "FINANCIAL_LOSS",
            "exploitability" => "MODERATE",
            "confidence" => "HIGH"
        ];
    }

    private function getAccessControlTemplate(): array
    {
        return [
            "severity" => "HIGH",
            "title" => "Unauthorized access via tx.origin",
            "line" => 67,
            "recommendation" => "Replace tx.origin with msg.sender for authorization checks and implement proper access control patterns.",
            "category" => "Access Control",
            "description" => "Function uses tx.origin for authorization which can be bypassed through intermediary contracts",
            "impact" => "UNAUTHORIZED_ACCESS",
            "exploitability" => "EASY",
            "confidence" => "HIGH"
        ];
    }

    private function getGenericTemplate(string $type): array
    {
        return [
            "severity" => "MEDIUM",
            "title" => ucfirst($type) . " vulnerability",
            "line" => 100,
            "recommendation" => "Implement appropriate security measures for {$type} vulnerability type.",
            "category" => "Other",
            "description" => "Security issue of type {$type} detected in smart contract code",
            "impact" => "MINIMAL",
            "exploitability" => "MODERATE", 
            "confidence" => "MEDIUM"
        ];
    }

    private function getAIPromptTemplate(): string
    {
        return <<<PROMPT
You are an expert blockchain security auditor. Analyze the following Solidity code and return findings using the prompt-optimized OWASP schema.

ANALYSIS REQUIREMENTS:
- Use severity levels: CRITICAL, HIGH, MEDIUM, LOW, INFO
- Provide structured recommendations with step-by-step guidance
- Include confidence metrics and false positive risk assessment
- Map to OWASP/SWC standards where applicable
- Consider DeFi-specific attack vectors (flash loans, oracle manipulation, etc.)

CODE TO ANALYZE:
```solidity
function withdraw(uint amount) public {
    require(balances[msg.sender] >= amount);
    msg.sender.call{value: amount}("");
    balances[msg.sender] -= amount;
}
```

RESPONSE FORMAT: JSON following schemas/security-finding-prompt-optimized.json

FOCUS AREAS:
- Re-entrancy vulnerabilities (SWC-107)
- Integer overflow/underflow (SWC-101)
- Access control issues (SWC-115)
- Flash loan attack vectors
- Oracle manipulation risks
- Gas optimization opportunities

Expected response: Complete JSON object with all required fields populated.
PROMPT;
    }
}