<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SecurityFindingSchemaValidator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class TestSecurityFindingSchema extends Command
{
    protected $signature = 'security:test-schema 
                          {--schema=v5 : Schema version to test}
                          {--examples : Test with example findings}
                          {--create-template : Create a template finding}
                          {--validate-file= : Path to JSON file with findings to validate}
                          {--report : Generate detailed validation report}';

    protected $description = 'Test and validate security finding JSON schema with comprehensive quality scoring';

    public function handle(): int
    {
        $schemaVersion = $this->option('schema');
        $this->info("🔍 Testing Security Finding Schema {$schemaVersion}");

        try {
            $validator = new SecurityFindingSchemaValidator($schemaVersion);
            
            // Show schema statistics
            $this->displaySchemaStats($validator);
            
            if ($this->option('create-template')) {
                return $this->createTemplate($validator);
            }
            
            if ($this->option('examples')) {
                return $this->testExampleFindings($validator);
            }
            
            if ($filePath = $this->option('validate-file')) {
                return $this->validateFile($validator, $filePath);
            }
            
            // Default: run comprehensive tests
            return $this->runComprehensiveTests($validator);

        } catch (\Exception $e) {
            $this->error("❌ Schema validation failed: {$e->getMessage()}");
            Log::error('Schema validation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    private function displaySchemaStats(SecurityFindingSchemaValidator $validator): void
    {
        $stats = $validator->getSchemaStatistics();
        
        $this->info("\n📊 Schema Statistics:");
        $this->line("  Version: {$stats['schema_version']}");
        $this->line("  Required Fields: {$stats['required_fields']}");
        $this->line("  Total Properties: {$stats['total_properties']}");
        $this->line("  Complexity: {$stats['schema_complexity']}");
        $this->newLine();
    }

    private function createTemplate(SecurityFindingSchemaValidator $validator): int
    {
        $this->info("🛠️ Creating prompt-optimized template...");
        
        $template = $validator->createPromptOptimizedTemplate([
            'severity' => 'HIGH',
            'category' => 'SWC-107-Reentrancy',
            'title' => 'Re-entrancy vulnerability in withdrawal function enables fund drainage'
        ]);
        
        $this->line("Template Finding:");
        $this->line(json_encode($template, JSON_PRETTY_PRINT));
        
        // Validate the template
        $validation = $validator->validateFinding($template);
        $this->displayValidationResult($validation, 'Template');
        
        return Command::SUCCESS;
    }

    private function testExampleFindings(SecurityFindingSchemaValidator $validator): int
    {
        $this->info("📋 Testing example security findings...");
        
        $examplesPath = base_path('schemas/security-finding-examples-v5.json');
        if (!file_exists($examplesPath)) {
            $this->error("Example findings file not found: {$examplesPath}");
            return Command::FAILURE;
        }
        
        $examples = json_decode(file_get_contents($examplesPath), true);
        if (!$examples) {
            $this->error("Failed to parse example findings JSON");
            return Command::FAILURE;
        }
        
        return $this->validateFindings($validator, $examples);
    }

    private function validateFile(SecurityFindingSchemaValidator $validator, string $filePath): int
    {
        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return Command::FAILURE;
        }
        
        $findings = json_decode(file_get_contents($filePath), true);
        if (!$findings) {
            $this->error("Failed to parse JSON file: {$filePath}");
            return Command::FAILURE;
        }
        
        if (!is_array($findings)) {
            $findings = [$findings]; // Single finding
        }
        
        return $this->validateFindings($validator, $findings);
    }

    private function runComprehensiveTests(SecurityFindingSchemaValidator $validator): int
    {
        $this->info("🧪 Running comprehensive schema tests...");
        
        // Test 1: Valid minimal finding
        $this->line("\n1️⃣ Testing minimal valid finding...");
        $minimalFinding = $this->createMinimalFinding();
        $validation = $validator->validateFinding($minimalFinding);
        $this->displayValidationResult($validation, 'Minimal Finding');
        
        // Test 2: Complete finding
        $this->line("\n2️⃣ Testing complete finding...");
        $completeFinding = $this->createCompleteFinding();
        $validation = $validator->validateFinding($completeFinding);
        $this->displayValidationResult($validation, 'Complete Finding');
        
        // Test 3: Invalid finding
        $this->line("\n3️⃣ Testing invalid finding...");
        $invalidFinding = $this->createInvalidFinding();
        $validation = $validator->validateFinding($invalidFinding);
        $this->displayValidationResult($validation, 'Invalid Finding');
        
        // Test 4: Batch validation
        $this->line("\n4️⃣ Testing batch validation...");
        $findings = [$minimalFinding, $completeFinding, $invalidFinding];
        $batchResults = $validator->validateFindings($findings);
        $this->displayBatchResults($batchResults);
        
        return Command::SUCCESS;
    }

    private function validateFindings(SecurityFindingSchemaValidator $validator, array $findings): int
    {
        $results = $validator->validateFindings($findings);
        
        $this->displayBatchResults($results);
        
        if ($this->option('report')) {
            $report = $validator->generateValidationReport($results);
            $reportFile = storage_path('logs/schema-validation-report.md');
            file_put_contents($reportFile, $report);
            $this->info("📄 Detailed report saved to: {$reportFile}");
        }
        
        return $results['valid_findings'] === $results['total_findings'] 
            ? Command::SUCCESS 
            : Command::FAILURE;
    }

    private function displayValidationResult(array $validation, string $context): void
    {
        $status = $validation['valid'] ? '✅' : '❌';
        $this->line("  {$status} {$context}: " . 
                   ($validation['valid'] ? 'VALID' : 'INVALID'));
        
        $this->line("    Quality Score: {$validation['quality_score']}%");
        $this->line("    Completeness: {$validation['completeness_score']}%");
        $this->line("    Validation Time: {$validation['validation_time_ms']}ms");
        
        if (!empty($validation['errors'])) {
            $this->line("    Errors:");
            foreach ($validation['errors'] as $error) {
                $this->line("      - {$error['property']}: {$error['message']}");
            }
        }
        
        // Show quality breakdown for high-quality findings
        if ($validation['quality_score'] >= 80) {
            $breakdown = $validation['quality_breakdown'];
            $this->line("    Quality Breakdown:");
            $this->line("      - Title: {$breakdown['title_quality']}%");
            $this->line("      - Description: {$breakdown['description_depth']}%");
            $this->line("      - Location: {$breakdown['location_completeness']}%");
            $this->line("      - Recommendations: {$breakdown['recommendation_actionability']}%");
        }
    }

    private function displayBatchResults(array $results): void
    {
        $this->info("\n📊 Batch Validation Results:");
        $this->line("  Total Findings: {$results['total_findings']}");
        $this->line("  Valid: {$results['valid_findings']}");
        $this->line("  Invalid: {$results['invalid_findings']}");
        $this->line("  Average Quality Score: {$results['average_quality_score']}%");
        
        $this->line("\n🏆 Quality Distribution:");
        foreach ($results['quality_distribution'] as $level => $count) {
            $icon = match($level) {
                'excellent' => '🥇',
                'good' => '🥈',
                'fair' => '🥉',
                'poor' => '❌'
            };
            $this->line("    {$icon} " . ucfirst($level) . ": {$count}");
        }
        
        if (!empty($results['validation_errors'])) {
            $this->warn("\n⚠️  Validation Errors Found:");
            foreach ($results['validation_errors'] as $error) {
                $this->line("  Finding {$error['finding_id']}:");
                foreach ($error['errors'] as $validationError) {
                    $this->line("    - {$validationError['property']}: {$validationError['message']}");
                }
            }
        }
    }

    private function createMinimalFinding(): array
    {
        return [
            'id' => 'FIND-' . strtoupper(str_replace('-', '', (string) \Illuminate\Support\Str::uuid())),
            'severity' => 'MEDIUM',
            'title' => 'Access control bypass in administrative function allows unauthorized privilege escalation',
            'category' => 'A01:2021-Broken Access Control',
            'description' => 'The administrative function lacks proper access control validation, allowing any user to invoke privileged operations. An attacker can call the function directly to gain administrative privileges, bypassing the intended authorization mechanisms. This vulnerability affects the core security model and enables complete system compromise through unauthorized administrative access.',
            'confidence' => 'HIGH',
            'location' => [
                'line' => 42,
                'function' => 'adminFunction',
                'contract' => 'AdminContract'
            ],
            'recommendation' => [
                'immediate_action' => 'DISABLE_FUNCTION',
                'summary' => 'Add proper access control modifier to restrict function access',
                'detailed_steps' => [
                    [
                        'step' => 1,
                        'action' => 'Add onlyOwner modifier to function',
                        'verification' => 'Function requires owner privileges'
                    ]
                ]
            ],
            'ai_metadata' => [
                'model' => 'gpt-4',
                'analysis_version' => '5.0.0',
                'detection_method' => 'LLM_ANALYSIS'
            ]
        ];
    }

    private function createCompleteFinding(): array
    {
        $finding = $this->createMinimalFinding();
        
        // Add all optional fields
        $finding['risk_metrics'] = [
            'cvss_v3' => [
                'score' => 7.5,
                'vector' => 'CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:N'
            ],
            'exploitability' => [
                'ease' => 'EASY',
                'attack_complexity' => 'LOW'
            ],
            'business_impact' => [
                'financial' => ['direct_loss' => 'HIGH'],
                'operational' => 'SEVERE'
            ]
        ];
        
        $finding['blockchain_context'] = [
            'networks' => ['ETHEREUM', 'POLYGON'],
            'evm_specifics' => [
                'solidity_version' => '^0.8.0',
                'optimization_enabled' => true
            ]
        ];
        
        $finding['tags'] = ['access-control', 'privilege-escalation', 'critical-path'];
        
        return $finding;
    }

    private function createInvalidFinding(): array
    {
        return [
            'id' => 'INVALID-ID',
            'severity' => 'INVALID_SEVERITY',
            'title' => 'Short', // Too short
            'category' => 'INVALID-CATEGORY',
            'description' => 'Too short description', // Too short
            'confidence' => 'INVALID_CONFIDENCE',
            'location' => [
                'line' => 'invalid', // Should be integer
                // Missing required fields
            ],
            'recommendation' => [
                // Missing required fields
            ],
            'ai_metadata' => [
                // Missing required fields
            ]
        ];
    }
}