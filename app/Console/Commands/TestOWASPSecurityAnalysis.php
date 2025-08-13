<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\OWASPSecurityAnalyzer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TestOWASPSecurityAnalysis extends Command
{
    protected $signature = 'security:test-owasp-analysis 
                           {--file= : Path to Solidity file to analyze}
                           {--contract=TestContract : Contract name}
                           {--focus=* : Focus areas for analysis}
                           {--output= : Output file for results}';

    protected $description = 'Test OWASP-style security analysis on smart contract code';

    public function handle(): int
    {
        $filePath = $this->option('file');
        $contractName = $this->option('contract') ?? 'TestContract';
        $focusAreas = $this->option('focus') ?: ['Re-entrancy', 'Access Control', 'Integer Overflow'];
        $outputFile = $this->option('output');

        // Use example vulnerable contract if no file specified
        $sourceCode = $filePath ? $this->loadSourceCode($filePath) : $this->getExampleVulnerableContract();
        
        if (empty($sourceCode)) {
            $this->error('No source code to analyze');
            return Command::FAILURE;
        }

        $this->info('🔍 Starting OWASP-style security analysis...');
        $this->info("Contract: {$contractName}");
        $this->info('Focus Areas: ' . implode(', ', $focusAreas));
        $this->newLine();

        try {
            $analyzer = new OWASPSecurityAnalyzer();
            
            $this->info('🤖 Analyzing with AI...');
            $startTime = microtime(true);
            
            $findings = $analyzer->analyzeContract($sourceCode, $contractName, $focusAreas);
            $summary = $analyzer->generateSummary($findings);
            
            $duration = round((microtime(true) - $startTime) * 1000);
            
            $this->info("✅ Analysis completed in {$duration}ms");
            $this->newLine();

            // Display summary
            $this->displaySummary($summary);
            $this->newLine();

            // Display findings
            $this->displayFindings($findings);

            // Save results if output file specified
            if ($outputFile) {
                $this->saveResults($findings, $summary, $outputFile);
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Analysis failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function loadSourceCode(string $filePath): string
    {
        if (!File::exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return '';
        }

        return File::get($filePath);
    }

    private function getExampleVulnerableContract(): string
    {
        return <<<SOLIDITY
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.0;

contract VulnerableBank {
    mapping(address => uint256) public balances;
    address public owner;

    constructor() {
        owner = msg.sender;
    }

    modifier onlyOwner() {
        require(tx.origin == owner, "Not authorized"); // SWC-115: tx.origin vulnerability
        _;
    }

    function deposit() public payable {
        balances[msg.sender] += msg.value;
    }

    function withdraw(uint256 amount) public {
        require(balances[msg.sender] >= amount, "Insufficient balance");
        
        // SWC-107: Reentrancy vulnerability - external call before state change
        (bool success, ) = msg.sender.call{value: amount}("");
        require(success, "Transfer failed");
        
        balances[msg.sender] -= amount; // State change after external call
    }

    function calculateReward(uint256 amount, uint256 rate) public pure returns (uint256) {
        // SWC-101: Integer overflow vulnerability
        return amount * rate * 1000; // No overflow protection
    }

    function adminWithdraw(uint256 amount) public onlyOwner {
        // Uses vulnerable tx.origin check from modifier
        payable(owner).transfer(amount);
    }

    function batchTransfer(address[] memory recipients, uint256[] memory amounts) public {
        require(recipients.length == amounts.length, "Array length mismatch");
        
        for (uint256 i = 0; i < recipients.length; i++) { // Gas inefficient: i++
            // Unchecked return value
            payable(recipients[i]).send(amounts[i]);
        }
    }

    function generateRandom() public view returns (uint256) {
        // SWC-120: Weak randomness using block properties
        return uint256(keccak256(abi.encodePacked(block.timestamp, block.difficulty))) % 100;
    }

    function emergencyStop() public onlyOwner {
        // Missing event emission for critical state change
        selfdestruct(payable(owner));
    }
}
SOLIDITY;
    }

    private function displaySummary(array $summary): void
    {
        $this->info('📊 Security Analysis Summary');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Findings', $summary['total_findings']],
                ['Overall Risk Level', $summary['risk_level']],
                ['Risk Score', $summary['overall_risk_score']],
            ]
        );

        // Severity breakdown
        $this->info('🚨 Severity Breakdown:');
        foreach ($summary['severity_breakdown'] as $severity => $count) {
            if ($count > 0) {
                $emoji = match($severity) {
                    'CRITICAL' => '🔴',
                    'HIGH' => '🟠',
                    'MEDIUM' => '🟡',
                    'LOW' => '🔵',
                    'INFO' => '⚪',
                    default => '⚫'
                };
                $this->line("  {$emoji} {$severity}: {$count}");
            }
        }

        // Top categories
        if (!empty($summary['categories'])) {
            $this->info('📋 Top Vulnerability Categories:');
            $topCategories = array_slice(
                array_map(
                    fn($category, $count) => ['category' => $category, 'count' => $count],
                    array_keys($summary['categories']),
                    array_values($summary['categories'])
                ),
                0,
                5
            );
            
            foreach ($topCategories as $item) {
                $this->line("  • {$item['category']}: {$item['count']}");
            }
        }
    }

    private function displayFindings(array $findings): void
    {
        if (empty($findings)) {
            $this->info('✅ No security vulnerabilities found!');
            return;
        }

        $this->info('🔍 Security Findings:');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        foreach ($findings as $index => $finding) {
            $severityEmoji = match($finding['severity'] ?? 'UNKNOWN') {
                'CRITICAL' => '🔴',
                'HIGH' => '🟠',
                'MEDIUM' => '🟡',
                'LOW' => '🔵',
                'INFO' => '⚪',
                default => '⚫'
            };

            $this->newLine();
            $this->line("<options=bold>{$severityEmoji} Finding #" . ($index + 1) . ": {$finding['title']}</>");
            $this->line("Severity: <fg=red>{$finding['severity']}</>");
            $this->line("Line: {$finding['line']}");
            
            if (isset($finding['function'])) {
                $this->line("Function: {$finding['function']}");
            }
            
            if (isset($finding['category'])) {
                $this->line("Category: {$finding['category']}");
            }

            if (isset($finding['description'])) {
                $this->line("Description: {$finding['description']}");
            }

            if (isset($finding['code_snippet'])) {
                $this->line("Code:");
                $this->line("  " . str_replace("\n", "\n  ", $finding['code_snippet']));
            }

            $this->line("Recommendation:");
            $this->line("  " . $finding['recommendation']);

            if (isset($finding['cvss_score'])) {
                $this->line("CVSS Score: {$finding['cvss_score']}");
            }

            if (isset($finding['confidence'])) {
                $this->line("Confidence: {$finding['confidence']}");
            }

            if (isset($finding['tags']) && is_array($finding['tags'])) {
                $this->line("Tags: " . implode(', ', $finding['tags']));
            }
        }
    }

    private function saveResults(array $findings, array $summary, string $outputFile): void
    {
        $results = [
            'analysis_timestamp' => now()->toISOString(),
            'summary' => $summary,
            'findings' => $findings,
            'schema_version' => '1.0.0'
        ];

        $json = json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        if (File::put($outputFile, $json)) {
            $this->info("💾 Results saved to: {$outputFile}");
        } else {
            $this->error("❌ Failed to save results to: {$outputFile}");
        }
    }
}