<?php

namespace App\Console\Commands;

use App\Services\SolidityCleanerService;
use App\Services\SourceCodeFetchingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanSolidityCode extends Command
{
    protected $signature = 'solidity:clean 
                          {--contract= : Contract address to fetch and clean}
                          {--network=ethereum : Network for contract fetching}
                          {--file= : Local Solidity file to clean}
                          {--input= : Direct Solidity code input}
                          {--output= : Output file path}
                          {--format=solidity : Export format (solidity, json, markdown, xml, plain, prompt, findings)}
                          {--level=standard : Optimization level (minimal, standard, aggressive, prompt)}
                          {--model=gpt-4 : AI model for token estimation (gpt-4, gpt-3.5, claude, gemini)}
                          {--multi-file : Process multiple files and flatten}
                          {--analyze : Show comprehensive analysis with recommendations}
                          {--findings : Generate AI findings prompt with schema}
                          {--schema=prompt-engineered : Schema type for findings (simple, prompt-engineered, owasp-style)}
                          {--prompt : Use prompt-optimized cleaning options (alias for --level=prompt)}
                          {--preserve-natspec : Keep NatSpec comments}
                          {--keep-license : Keep SPDX license}
                          {--no-flatten : Disable import flattening}
                          {--demo : Run demo with sample Solidity code}
                          {--stats : Show cleaning statistics}';

    protected $description = 'Clean Solidity code for prompt input by removing comments and flattening imports';

    private SolidityCleanerService $cleanerService;
    private SourceCodeFetchingService $fetchingService;

    public function __construct(
        SolidityCleanerService $cleanerService,
        SourceCodeFetchingService $fetchingService
    ) {
        parent::__construct();
        $this->cleanerService = $cleanerService;
        $this->fetchingService = $fetchingService;
    }

    public function handle(): int
    {
        $this->info('🧹 Solidity Code Cleaner for Prompt Optimization');
        $this->newLine();

        if ($this->option('demo')) {
            return $this->runDemo();
        }

        if ($this->option('analyze')) {
            return $this->runAnalysis();
        }

        // Get source code from various inputs
        $sourceCode = $this->getSourceCode();
        if (!$sourceCode) {
            $this->error('❌ No source code provided. Use --contract, --file, or --input');
            return Command::FAILURE;
        }

        // Process based on options
        if ($this->option('multi-file')) {
            $result = $this->processMultipleFiles($sourceCode);
        } else {
            $result = $this->processSingleFile($sourceCode);
        }

        // Display results
        $this->displayResults($result);

        // Handle special formats
        if ($this->option('findings')) {
            return $this->generateFindingsPrompt($result);
        }

        // Export in requested format
        $format = $this->option('format');
        
        if ($format === 'findings') {
            $exported = $this->cleanerService->createFindingsPrompt($result);
        } else {
            $exported = $this->cleanerService->exportFormatted($result, $format);
        }

        // Save output if requested
        if ($outputFile = $this->option('output')) {
            $this->saveOutput($exported, $outputFile);
        } elseif ($format !== 'solidity') {
            $this->newLine();
            $this->info("📤 Exported as {$format}:");
            $this->line($exported);
        }

        return Command::SUCCESS;
    }

    private function getSourceCode(): ?string
    {
        // Option 1: Fetch from contract
        if ($contract = $this->option('contract')) {
            $network = $this->option('network');
            $this->info("📥 Fetching source code for contract: {$contract} ({$network})");
            
            try {
                $sourceData = $this->fetchingService->fetchSourceCode($contract, $network);
                
                if (empty($sourceData['contracts'])) {
                    $this->error('❌ No source code found for contract');
                    return null;
                }

                // Use the main contract or first available
                $mainContract = $sourceData['main_contract'] ?? array_key_first($sourceData['contracts']);
                $sourceCode = $sourceData['contracts'][$mainContract]['source'] ?? '';
                
                $this->info("✅ Fetched source code ({$sourceData['statistics']['total_lines']} lines)");
                return $sourceCode;

            } catch (\Exception $e) {
                $this->error("❌ Failed to fetch contract source: {$e->getMessage()}");
                return null;
            }
        }

        // Option 2: Read from file
        if ($file = $this->option('file')) {
            if (!file_exists($file)) {
                $this->error("❌ File not found: {$file}");
                return null;
            }

            $sourceCode = file_get_contents($file);
            $this->info("📄 Read source code from file: {$file}");
            return $sourceCode;
        }

        // Option 3: Direct input
        if ($input = $this->option('input')) {
            return $input;
        }

        return null;
    }

    private function buildCleaningOptions(): array
    {
        if ($this->option('prompt')) {
            $options = [
                'strip_comments' => true,
                'flatten_imports' => true,
                'remove_empty_lines' => true,
                'normalize_whitespace' => true,
                'preserve_natspec' => false,
                'keep_spdx_license' => false,
                'keep_pragma' => true,
                'sort_imports' => true,
                'compact_functions' => true,
                'preserve_line_breaks_in_functions' => false
            ];
        } else {
            $options = [
                'strip_comments' => true,
                'flatten_imports' => !$this->option('no-flatten'),
                'remove_empty_lines' => true,
                'normalize_whitespace' => true,
                'preserve_natspec' => $this->option('preserve-natspec'),
                'keep_spdx_license' => $this->option('keep-license'),
                'keep_pragma' => true,
                'sort_imports' => true,
                'compact_functions' => true,
                'preserve_line_breaks_in_functions' => false
            ];
        }

        return $options;
    }

    private function displayResults(array $result): void
    {
        $stats = $result['statistics'];
        $metadata = $result['metadata'];

        // Show cleaning statistics with token estimates
        $model = $this->option('model');
        $tokens = $this->cleanerService->estimateTokens($result['cleaned_code'], $model);

        $this->newLine();
        $this->info('📊 Cleaning Results:');
        $this->table(
            ['Metric', 'Before', 'After', 'Reduction'],
            [
                [
                    'Lines',
                    number_format($stats['original_lines']),
                    number_format($stats['cleaned_lines']),
                    $stats['lines_reduction_percent'] . '%'
                ],
                [
                    'Characters',
                    number_format($stats['original_size']),
                    number_format($stats['cleaned_size']),
                    $stats['size_reduction_percent'] . '%'
                ],
                [
                    "Est. Tokens ({$model})",
                    'N/A',
                    number_format($tokens['estimated_tokens']),
                    'N/A'
                ]
            ]
        );

        // Show metadata if available
        if (!empty($metadata['contracts']) || !empty($metadata['functions'])) {
            $this->newLine();
            $this->info('📋 Code Analysis:');
            $this->table(
                ['Element', 'Count'],
                [
                    ['Contracts/Interfaces/Libraries', count($metadata['contracts'])],
                    ['Functions', count($metadata['functions'])],
                    ['Events', count($metadata['events'])],
                    ['Pragma Statements', count($metadata['pragma_statements'])],
                    ['License', $metadata['license_identifier'] ?? 'None']
                ]
            );
        }

        // Show cleaned code if not too large
        if ($stats['cleaned_lines'] <= 50) {
            $this->newLine();
            $this->info('🧹 Cleaned Code:');
            $this->line('```solidity');
            $this->line($result['cleaned_code']);
            $this->line('```');
        } else {
            $this->newLine();
            $this->comment("💡 Cleaned code is {$stats['cleaned_lines']} lines. Use --output to save to file.");
        }

        // Show optimization suggestions
        $this->newLine();
        $this->info('💡 Optimization Summary:');
        if ($stats['lines_reduction_percent'] > 50) {
            $this->line('🟢 Excellent size reduction - perfect for prompt input');
        } elseif ($stats['lines_reduction_percent'] > 30) {
            $this->line('🟡 Good size reduction - suitable for prompt input');
        } else {
            $this->line('🔴 Limited reduction - code was already compact');
        }

        if ($this->option('stats')) {
            $this->showDetailedStats($stats);
        }
    }

    private function showDetailedStats(array $stats): void
    {
        $this->newLine();
        $this->info('📈 Detailed Statistics:');
        
        $this->table(
            ['Cleaning Step', 'Status'],
            [
                ['Comments Stripped', $stats['cleaning_options']['strip_comments'] ? '✅ Yes' : '❌ No'],
                ['Imports Flattened', $stats['cleaning_options']['flatten_imports'] ? '✅ Yes' : '❌ No'],
                ['Empty Lines Removed', $stats['cleaning_options']['remove_empty_lines'] ? '✅ Yes' : '❌ No'],
                ['Whitespace Normalized', $stats['cleaning_options']['normalize_whitespace'] ? '✅ Yes' : '❌ No'],
                ['Functions Compacted', $stats['cleaning_options']['compact_functions'] ? '✅ Yes' : '❌ No'],
                ['NatSpec Preserved', $stats['cleaning_options']['preserve_natspec'] ? '✅ Yes' : '❌ No'],
                ['SPDX License Kept', $stats['cleaning_options']['keep_spdx_license'] ? '✅ Yes' : '❌ No']
            ]
        );

        $this->newLine();
        $this->comment('💾 Size reduction: ' . number_format($stats['size_removed']) . ' characters saved');
        $this->comment('📏 Line reduction: ' . number_format($stats['lines_removed']) . ' lines removed');
    }

    private function saveOutput(string $cleanedCode, string $outputFile): void
    {
        try {
            file_put_contents($outputFile, $cleanedCode);
            $this->info("💾 Cleaned code saved to: {$outputFile}");
        } catch (\Exception $e) {
            $this->error("❌ Failed to save output: {$e->getMessage()}");
        }
    }

    private function runDemo(): int
    {
        $this->info('🎭 Solidity Cleaner Demo');
        $this->newLine();

        $sampleCode = $this->getSampleSolidityCode();
        
        $this->info('📄 Sample Solidity Code:');
        $this->line('```solidity');
        $this->line($sampleCode);
        $this->line('```');

        $this->newLine();
        $this->info('🧹 Cleaning with prompt-optimized settings...');

        $result = $this->cleanerService->cleanForPrompt($sampleCode);
        
        $this->displayResults($result);

        $this->newLine();
        $this->comment('💡 This demonstrates how the cleaner optimizes Solidity code for AI prompt input');
        $this->comment('💡 Use --contract=<address> to clean real contract source code');

        return Command::SUCCESS;
    }

    private function getSampleSolidityCode(): string
    {
        return '// SPDX-License-Identifier: MIT
pragma solidity ^0.8.0;

import "@openzeppelin/contracts/token/ERC20/ERC20.sol";
import "@openzeppelin/contracts/access/Ownable.sol";

/**
 * @title SampleToken
 * @dev A sample ERC20 token with basic functionality
 */
contract SampleToken is ERC20, Ownable {
    
    uint256 public constant MAX_SUPPLY = 1000000 * 10**18;
    
    // Mapping to track frozen accounts
    mapping(address => bool) public frozenAccounts;
    
    /**
     * @dev Constructor that gives msg.sender all existing tokens
     */
    constructor() ERC20("SampleToken", "STK") {
        _mint(msg.sender, MAX_SUPPLY);
    }
    
    /**
     * @dev Freeze an account to prevent transfers
     * @param account The account to freeze
     */
    function freezeAccount(address account) public onlyOwner {
        frozenAccounts[account] = true;
        
        // Emit event for frozen account
        emit AccountFrozen(account);
    }
    
    /**
     * @dev Unfreeze an account
     */
    function unfreezeAccount(address account) public onlyOwner {
        frozenAccounts[account] = false;
        emit AccountUnfrozen(account);
    }
    
    // Events for account freezing
    event AccountFrozen(address indexed account);
    event AccountUnfrozen(address indexed account);
    
    /**
     * @dev Override transfer to check for frozen accounts
     */
    function transfer(address to, uint256 amount) public override returns (bool) {
        require(!frozenAccounts[msg.sender], "Account is frozen");
        require(!frozenAccounts[to], "Recipient account is frozen");
        
        return super.transfer(to, amount);
    }
    
    
    // Emergency function to mint additional tokens
    function emergencyMint(address to, uint256 amount) public onlyOwner {
        require(totalSupply() + amount <= MAX_SUPPLY, "Would exceed max supply");
        _mint(to, amount);
    }
}';
    }

    private function processSingleFile(string $sourceCode): array
    {
        $level = $this->option('prompt') ? 'prompt' : $this->option('level');
        
        if (in_array($level, $this->cleanerService::getOptimizationLevels())) {
            return $this->cleanerService->cleanWithLevel($sourceCode, $level);
        } else {
            $options = $this->buildCleaningOptions();
            return $this->cleanerService->cleanSourceCode($sourceCode, $options);
        }
    }

    private function processMultipleFiles(string $input): array
    {
        // For simplicity, treat as single file for now
        // In a real implementation, you'd parse multiple file paths
        $level = $this->option('prompt') ? 'prompt' : $this->option('level');
        
        if (in_array($level, $this->cleanerService::getOptimizationLevels())) {
            return $this->cleanerService->cleanWithLevel($input, $level);
        } else {
            $options = $this->buildCleaningOptions();
            return $this->cleanerService->cleanSourceCode($input, $options);
        }
    }

    private function runAnalysis(): int
    {
        $this->info('🔍 Comprehensive Solidity Analysis');
        $this->newLine();

        $sourceCode = $this->getSourceCode();
        if (!$sourceCode) {
            $this->error('❌ No source code provided for analysis');
            return Command::FAILURE;
        }

        $analysis = $this->cleanerService->analyzeForPromptOptimization($sourceCode);

        $this->displayAnalysisResults($analysis);

        return Command::SUCCESS;
    }

    private function displayAnalysisResults(array $analysis): void
    {
        $this->info('📊 Optimization Level Comparison:');
        $this->newLine();

        $tableData = [];
        foreach ($analysis['analysis_results'] as $level => $result) {
            $tableData[] = [
                ucfirst($level),
                number_format($result['statistics']['cleaned_lines']),
                number_format($result['statistics']['cleaned_size']),
                $result['statistics']['size_reduction_percent'] . '%',
                number_format($result['tokens']['estimated_tokens'])
            ];
        }

        $this->table(
            ['Level', 'Lines', 'Characters', 'Reduction', 'Est. Tokens'],
            $tableData
        );

        $this->newLine();
        $this->info('💡 Recommendations:');
        foreach ($analysis['recommendations'] as $recommendation) {
            $this->line("   {$recommendation}");
        }

        $best = $analysis['best_for_prompts'];
        $this->newLine();
        $this->info("🎯 Best for AI Prompts: {$best['recommended_level']} ({$best['token_count']} tokens)");
        $this->comment("   {$best['reasoning']}");

        // Show model-specific token estimates
        $this->newLine();
        $this->info('🤖 Token Estimates by AI Model:');
        $promptResult = $analysis['analysis_results']['prompt'];
        
        $modelData = [];
        foreach ($this->cleanerService::getTokenModels() as $model) {
            $tokens = $this->cleanerService->estimateTokens($promptResult['cleaned_code'], $model);
            $modelData[] = [
                strtoupper($model),
                number_format($tokens['estimated_tokens']),
                $tokens['token_ratio'] . ':1'
            ];
        }

        $this->table(['Model', 'Est. Tokens', 'Char:Token'], $modelData);
    }

    private function generateFindingsPrompt(array $result): int
    {
        $schemaType = $this->option('schema');
        $this->info("🔍 Generating AI Findings Prompt (Schema: {$schemaType})");
        $this->newLine();

        $prompt = $this->cleanerService->createFindingsPrompt($result, [
            'schema_type' => $schemaType,
            'focus_areas' => ['reentrancy', 'access_control', 'overflow', 'gas_optimization'],
            'max_findings' => 10
        ]);
        
        $this->info('📋 AI-Ready Findings Prompt:');
        $this->newLine();
        $this->line($prompt);

        // Save to file if output specified
        if ($outputFile = $this->option('output')) {
            $this->saveOutput($prompt, $outputFile);
            $this->newLine();
            $this->info("💾 Findings prompt saved to: {$outputFile}");
        }

        $this->newLine();
        $this->comment('💡 Copy this prompt to your AI model to generate security findings');
        $this->comment("💡 The AI will return findings in the {$schemaType} JSON schema format");
        $this->comment('💡 Available schemas: simple, prompt-engineered, owasp-style');

        return Command::SUCCESS;
    }
}