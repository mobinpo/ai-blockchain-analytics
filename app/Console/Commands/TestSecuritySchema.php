<?php

namespace App\Console\Commands;

use App\Services\SecurityFindingValidator;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TestSecuritySchema extends Command
{
    protected $signature = 'test:security-schema 
                          {--validate : Test schema validation with sample data}
                          {--generate : Generate sample findings using the schema}
                          {--stats : Show schema statistics}
                          {--template : Create finding templates}
                          {--export : Export schema examples}';

    protected $description = 'Test and validate the security finding JSON schema';

    private SecurityFindingValidator $validator;

    public function __construct(SecurityFindingValidator $validator)
    {
        parent::__construct();
        $this->validator = $validator;
    }

    public function handle(): int
    {
        $this->info('🔒 Security Finding Schema Testing Tool');
        $this->newLine();

        if ($this->option('stats')) {
            return $this->showSchemaStats();
        }

        if ($this->option('template')) {
            return $this->createTemplates();
        }

        if ($this->option('generate')) {
            return $this->generateSampleFindings();
        }

        if ($this->option('export')) {
            return $this->exportSchemaExamples();
        }

        if ($this->option('validate')) {
            return $this->testValidation();
        }

        // Default: show overview
        return $this->showOverview();
    }

    private function showSchemaStats(): int
    {
        $this->info('📊 Security Finding Schema Statistics');
        $this->newLine();

        try {
            $stats = $this->validator->getSchemaStats();

            $this->table(
                ['Property', 'Value'],
                [
                    ['Schema Version', $stats['schema_version']],
                    ['Supported Categories', $stats['supported_categories']],
                    ['Supported Severities', $stats['supported_severities']],
                    ['Category Mappings', $stats['category_mappings']],
                    ['Validation Rules', $stats['validation_rules']]
                ]
            );

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to get schema stats: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function createTemplates(): int
    {
        $this->info('📝 Creating Security Finding Templates');
        $this->newLine();

        // Create basic template
        $basicTemplate = $this->validator->createFindingTemplate();
        $this->info('✅ Basic Template Created:');
        $this->line(json_encode($basicTemplate, JSON_PRETTY_PRINT));
        $this->newLine();

        // Create reentrancy example
        $reentrancyTemplate = $this->validator->createFindingTemplate([
            'severity' => 'HIGH',
            'category' => 'REENTRANCY',
            'title' => 'Reentrancy vulnerability in withdraw function',
            'description' => 'The withdraw function updates the user balance after sending Ether, allowing for reentrancy attacks where an attacker can recursively call withdraw before their balance is updated.',
            'location' => [
                'contract' => 'Bank',
                'function' => 'withdraw',
                'line' => 125
            ],
            'recommendation' => [
                'summary' => 'Implement checks-effects-interactions pattern to prevent reentrancy',
                'detailed_steps' => [
                    'Update the user balance before making the external call',
                    'Consider using OpenZeppelin ReentrancyGuard modifier',
                    'Validate the call return value and handle failures properly'
                ]
            ],
            'confidence' => 'HIGH',
            'impact' => [
                'financial' => 'CRITICAL',
                'operational' => 'HIGH',
                'reputational' => 'HIGH'
            ]
        ]);

        $this->info('✅ Reentrancy Template Created:');
        $this->line(json_encode($reentrancyTemplate, JSON_PRETTY_PRINT));

        return Command::SUCCESS;
    }

    private function generateSampleFindings(): int
    {
        $this->info('🎲 Generating Sample Security Findings');
        $this->newLine();

        $sampleFindings = [
            // Reentrancy vulnerability
            [
                'id' => strtoupper(Str::uuid()->toString()),
                'severity' => 'HIGH',
                'category' => 'REENTRANCY',
                'title' => 'Classic reentrancy in withdraw function',
                'description' => 'The withdraw function sends Ether before updating the user balance, creating a classic reentrancy vulnerability that could allow an attacker to drain contract funds.',
                'location' => [
                    'contract' => 'VulnerableBank',
                    'function' => 'withdraw',
                    'line' => 42,
                    'code_snippet' => 'payable(msg.sender).transfer(amount);\nbalances[msg.sender] -= amount;'
                ],
                'recommendation' => [
                    'summary' => 'Apply checks-effects-interactions pattern',
                    'detailed_steps' => [
                        'Update balance before external call',
                        'Use ReentrancyGuard modifier',
                        'Consider using pull payment pattern'
                    ],
                    'code_fix' => 'balances[msg.sender] -= amount;\npayable(msg.sender).transfer(amount);'
                ],
                'confidence' => 'HIGH',
                'impact' => [
                    'financial' => 'CRITICAL',
                    'operational' => 'HIGH',
                    'reputational' => 'HIGH'
                ]
            ],
            // Access control issue
            [
                'id' => strtoupper(Str::uuid()->toString()),
                'severity' => 'MEDIUM',
                'category' => 'ACCESS_CONTROL',
                'title' => 'Missing access control on sensitive function',
                'description' => 'The mint function lacks proper access control modifiers, allowing any user to mint unlimited tokens.',
                'location' => [
                    'contract' => 'SimpleToken',
                    'function' => 'mint',
                    'line' => 67
                ],
                'recommendation' => [
                    'summary' => 'Add appropriate access control modifiers',
                    'detailed_steps' => [
                        'Add onlyOwner modifier to mint function',
                        'Consider implementing role-based access control',
                        'Add proper authorization checks'
                    ]
                ],
                'confidence' => 'HIGH',
                'impact' => [
                    'financial' => 'HIGH',
                    'operational' => 'MEDIUM',
                    'reputational' => 'MEDIUM'
                ]
            ],
            // Integer overflow
            [
                'id' => strtoupper(Str::uuid()->toString()),
                'severity' => 'MEDIUM',
                'category' => 'ARITHMETIC',
                'title' => 'Potential integer overflow in calculation',
                'description' => 'The price calculation uses unsafe arithmetic operations that could result in integer overflow, leading to incorrect token pricing.',
                'location' => [
                    'contract' => 'TokenSale',
                    'function' => 'calculatePrice',
                    'line' => 158
                ],
                'recommendation' => [
                    'summary' => 'Use SafeMath library for arithmetic operations',
                    'detailed_steps' => [
                        'Import OpenZeppelin SafeMath library',
                        'Replace arithmetic operations with SafeMath functions',
                        'Add overflow checks for critical calculations'
                    ]
                ],
                'confidence' => 'MEDIUM',
                'impact' => [
                    'financial' => 'MEDIUM',
                    'operational' => 'MEDIUM',
                    'reputational' => 'LOW'
                ]
            ]
        ];

        $this->info("Generated " . count($sampleFindings) . " sample findings:");
        $this->newLine();

        foreach ($sampleFindings as $index => $finding) {
            $validation = $this->validator->validateFinding($finding);
            
            $status = $validation['valid'] ? '✅ Valid' : '❌ Invalid';
            $this->line('#' . ($index + 1) . ': ' . $finding['title'] . ' - ' . $status);
            
            if (!$validation['valid']) {
                $this->error('Validation errors: ' . json_encode($validation['errors']));
            }
            
            if (!empty($validation['warnings'])) {
                $this->warn('Warnings: ' . count($validation['warnings']) . ' issues');
                foreach ($validation['warnings'] as $warning) {
                    $this->line("  - {$warning['type']}: {$warning['message']}");
                }
            }

            if ($validation['valid']) {
                $this->line("  Quality Score: {$validation['quality_score']}/1.0");
                if (isset($validation['enhanced_finding']['remediation_priority']['priority_score'])) {
                    $this->line("  Priority Score: {$validation['enhanced_finding']['remediation_priority']['priority_score']}/100");
                }
            }

            $this->newLine();
        }

        return Command::SUCCESS;
    }

    private function testValidation(): int
    {
        $this->info('🧪 Testing Schema Validation');
        $this->newLine();

        // Test valid finding
        $validFinding = $this->validator->createFindingTemplate([
            'severity' => 'HIGH',
            'category' => 'REENTRANCY',
            'title' => 'Test reentrancy vulnerability',
            'description' => 'This is a test finding to validate the schema validation works correctly with all required fields present.',
            'confidence' => 'HIGH'
        ]);

        $this->info('1. Testing valid finding...');
        $result = $this->validator->validateFinding($validFinding);
        $this->line($result['valid'] ? '✅ Valid finding passed' : '❌ Valid finding failed');
        
        if (!$result['valid']) {
            $this->error('Unexpected validation failure: ' . json_encode($result['errors']));
        }

        // Test invalid finding (missing required fields)
        $this->info('2. Testing invalid finding (missing required fields)...');
        $invalidFinding = [
            'title' => 'Incomplete finding',
            'severity' => 'HIGH'
            // Missing other required fields
        ];

        $result = $this->validator->validateFinding($invalidFinding);
        $this->line(!$result['valid'] ? '✅ Invalid finding correctly rejected' : '❌ Invalid finding incorrectly accepted');

        if ($result['valid']) {
            $this->error('Validation should have failed but passed');
        } else {
            $this->info('Validation errors found: ' . count($result['errors']));
            foreach ($result['errors'] as $error) {
                $this->line("  - {$error['property']}: {$error['message']}");
            }
        }

        // Test finding with invalid enum values
        $this->info('3. Testing finding with invalid enum values...');
        $invalidEnumFinding = $this->validator->createFindingTemplate([
            'severity' => 'SUPER_HIGH', // Invalid severity
            'category' => 'UNKNOWN_CATEGORY', // Invalid category
        ]);

        $result = $this->validator->validateFinding($invalidEnumFinding);
        $this->line(!$result['valid'] ? '✅ Invalid enum values correctly rejected' : '❌ Invalid enum values incorrectly accepted');

        return Command::SUCCESS;
    }

    private function exportSchemaExamples(): int
    {
        $this->info('📤 Exporting Schema Examples');
        $this->newLine();

        $examples = [
            'reentrancy_high' => [
                'id' => '12345678-1234-1234-1234-123456789012',
                'severity' => 'HIGH',
                'category' => 'REENTRANCY',
                'subcategory' => 'Classic Reentrancy',
                'title' => 'Reentrancy vulnerability in withdraw function',
                'description' => 'The withdraw function updates the user\'s balance after sending Ether, allowing for reentrancy attacks where an attacker can recursively call withdraw before their balance is updated, potentially draining the contract.',
                'location' => [
                    'contract' => 'Bank',
                    'function' => 'withdraw',
                    'line' => 125,
                    'code_snippet' => 'msg.sender.call{value: amount}("");\nbalances[msg.sender] -= amount;',
                    'file_path' => 'contracts/Bank.sol'
                ],
                'recommendation' => [
                    'summary' => 'Implement checks-effects-interactions pattern to prevent reentrancy',
                    'detailed_steps' => [
                        'Update the user\'s balance before making the external call',
                        'Consider using OpenZeppelin\'s ReentrancyGuard modifier',
                        'Validate the call return value and handle failures properly'
                    ],
                    'code_fix' => 'balances[msg.sender] -= amount;\n(bool success, ) = msg.sender.call{value: amount}("");\nrequire(success, "Transfer failed");',
                    'references' => [
                        [
                            'title' => 'Ethereum Smart Contract Best Practices - Reentrancy',
                            'url' => 'https://consensys.github.io/smart-contract-best-practices/attacks/reentrancy/'
                        ]
                    ]
                ],
                'confidence' => 'HIGH',
                'impact' => [
                    'financial' => 'CRITICAL',
                    'operational' => 'HIGH',
                    'reputational' => 'HIGH',
                    'description' => 'Attacker could drain all contract funds through recursive calls',
                    'affected_functions' => ['withdraw', 'emergencyWithdraw'],
                    'attack_scenario' => 'Attacker deploys malicious contract with fallback function that calls withdraw again, draining contract funds'
                ],
                'technical_details' => [
                    'vulnerability_type' => 'IMPLEMENTATION_BUG',
                    'attack_vector' => 'EXTERNAL_CALL',
                    'prerequisites' => ['Contract must have Ether balance', 'Attacker needs some initial deposit'],
                    'gas_impact' => [
                        'gas_cost_increase' => 50000,
                        'dos_potential' => false
                    ]
                ],
                'compliance' => [
                    'swc_id' => 'SWC-107',
                    'cwe_id' => 841,
                    'standards_violated' => ['CEI Pattern', 'Secure Coding Practices']
                ],
                'metadata' => [
                    'detected_by' => 'Claude-4 Security Analysis v1.0',
                    'detection_timestamp' => '2025-01-01T12:00:00Z',
                    'analysis_version' => '1.0.0',
                    'false_positive_likelihood' => 0.05,
                    'tags' => ['reentrancy', 'external-call', 'state-change']
                ],
                'remediation_priority' => [
                    'priority_score' => 95,
                    'estimated_effort' => 'EASY',
                    'estimated_time' => '2 hours',
                    'dependencies' => []
                ]
            ]
        ];

        $exportPath = storage_path('app/security-schema-examples.json');
        file_put_contents($exportPath, json_encode($examples, JSON_PRETTY_PRINT));

        $this->info("✅ Schema examples exported to: {$exportPath}");
        $this->info("📊 Exported ". count($examples) . " example findings");

        return Command::SUCCESS;
    }

    private function showOverview(): int
    {
        $this->info('📋 Security Finding Schema Overview');
        $this->newLine();

        $this->table(
            ['Component', 'Description'],
            [
                ['Schema File', 'schemas/security-finding-schema.json'],
                ['Validator Service', 'App\\Services\\SecurityFindingValidator'],
                ['Supported Format', 'JSON with comprehensive validation'],
                ['OWASP Compliance', 'Follows OWASP methodology'],
                ['Categories Supported', '25+ vulnerability types'],
                ['Severity Levels', 'CRITICAL, HIGH, MEDIUM, LOW, INFO']
            ]
        );

        $this->newLine();
        $this->info('🎯 Available Test Options:');
        $this->table(
            ['Option', 'Description'],
            [
                ['--stats', 'Show detailed schema statistics'],
                ['--template', 'Generate finding templates'],
                ['--generate', 'Create sample security findings'],
                ['--validate', 'Test schema validation'],
                ['--export', 'Export schema examples to file']
            ]
        );

        $this->newLine();
        $this->comment('💡 Example usage:');
        $this->line('  php artisan test:security-schema --validate');
        $this->line('  php artisan test:security-schema --generate');

        return Command::SUCCESS;
    }
}