<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\BlockchainExplorerService;
use App\Services\SolidityCleanerService;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class TestSolidityCleaner extends Command
{
    protected $signature = 'solidity:clean {network} {address} {--action=clean} {--output=}';
    
    protected $description = 'Test Solidity code cleaner with blockchain contracts';

    public function handle(
        BlockchainExplorerService $explorerService,
        SolidityCleanerService $cleanerService
    ): int {
        $network = $this->argument('network');
        $address = $this->argument('address');
        $action = $this->option('action');
        $outputFile = $this->option('output');

        $this->info("Testing Solidity cleaner for {$address} on {$network}...");

        try {
            // Fetch contract source
            $contract = $explorerService->getContractSource($network, $address);
            
            if (!$contract['is_verified']) {
                $this->error('❌ Contract is not verified');
                return self::FAILURE;
            }

            match ($action) {
                'clean' => $this->testCleaning($cleanerService, $contract, $outputFile),
                'flatten' => $this->testFlattening($cleanerService, $contract, $outputFile),
                'analyze' => $this->testAnalysis($cleanerService, $contract),
                'stats' => $this->testStats($cleanerService, $contract),
                default => $this->error("Unknown action: {$action}. Available: clean|flatten|analyze|stats")
            };

            return self::SUCCESS;

        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    private function testCleaning(SolidityCleanerService $cleaner, array $contract, ?string $outputFile): void
    {
        $this->info('🧹 Testing code cleaning...');
        
        $originalSource = $contract['source_code'];
        $cleanedSource = $cleaner->cleanForPrompt($originalSource);
        
        $stats = $cleaner->getCleaningStats($originalSource, $cleanedSource);
        
        $this->table(
            ['Metric', 'Original', 'Cleaned', 'Reduction'],
            [
                ['Size (bytes)', number_format($stats['original_size']), number_format($stats['cleaned_size']), "{$stats['reduction_percentage']}%"],
                ['Lines', $stats['original_lines'], $stats['cleaned_lines'], $stats['original_lines'] - $stats['cleaned_lines']],
            ]
        );

        if ($outputFile) {
            file_put_contents($outputFile, $cleanedSource);
            $this->info("✅ Cleaned source saved to: {$outputFile}");
        } else {
            $this->newLine();
            $this->info('📄 Cleaned Source Preview (first 500 chars):');
            $this->line(substr($cleanedSource, 0, 500) . '...');
        }
    }

    private function testFlattening(SolidityCleanerService $cleaner, array $contract, ?string $outputFile): void
    {
        $this->info('🔗 Testing code flattening...');
        
        $sourceFiles = $contract['parsed_sources'];
        $flattened = $cleaner->cleanAndFlatten($sourceFiles);
        
        $originalTotal = array_sum(array_map('strlen', $sourceFiles));
        $stats = $cleaner->getCleaningStats(implode("\n", $sourceFiles), $flattened);
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Input Files', count($sourceFiles)],
                ['Original Total Size', number_format($originalTotal) . ' bytes'],
                ['Flattened Size', number_format(strlen($flattened)) . ' bytes'],
                ['Reduction', "{$stats['reduction_percentage']}%"],
            ]
        );

        if ($outputFile) {
            file_put_contents($outputFile, $flattened);
            $this->info("✅ Flattened source saved to: {$outputFile}");
        } else {
            $this->newLine();
            $this->info('📄 Flattened Source Preview (first 500 chars):');
            $this->line(substr($flattened, 0, 500) . '...');
        }
    }

    private function testAnalysis(SolidityCleanerService $cleaner, array $contract): void
    {
        $this->info('🔍 Analyzing contract source...');
        
        $sourceCode = $contract['source_code'];
        $analysis = $cleaner->analyzeCode($sourceCode);
        
        $this->table(
            ['Feature', 'Present'],
            [
                ['Comments', $analysis['has_comments'] ? '✅ Yes' : '❌ No'],
                ['Imports', $analysis['has_imports'] ? '✅ Yes' : '❌ No'],
                ['Interfaces', $analysis['has_interfaces'] ? '✅ Yes' : '❌ No'],
                ['Libraries', $analysis['has_libraries'] ? '✅ Yes' : '❌ No'],
                ['Contracts', $analysis['has_contracts'] ? '✅ Yes' : '❌ No'],
                ['Abstract Contracts', $analysis['has_abstract_contracts'] ? '✅ Yes' : '❌ No'],
            ]
        );

        if (!empty($analysis['pragma_versions'])) {
            $this->newLine();
            $this->info('🔧 Pragma Versions:');
            foreach ($analysis['pragma_versions'] as $pragma) {
                $this->line("  • {$pragma}");
            }
        }

        if (!empty($analysis['imports'])) {
            $this->newLine();
            $this->info('📦 Imports (' . count($analysis['imports']) . ' total):');
            foreach (array_slice($analysis['imports'], 0, 5) as $import) {
                $this->line("  • {$import}");
            }
            if (count($analysis['imports']) > 5) {
                $this->comment("  ... and " . (count($analysis['imports']) - 5) . " more");
            }
        }

        $this->newLine();
        $this->info("📊 Estimated token count: ~{$analysis['estimated_tokens']} tokens");
    }

    private function testStats(SolidityCleanerService $cleaner, array $contract): void
    {
        $this->info('📊 Contract cleaning statistics...');
        
        $sourceFiles = $contract['parsed_sources'];
        $originalSource = $contract['source_code'];
        
        // Test cleaning
        $cleanedSource = $cleaner->cleanForPrompt($originalSource);
        $cleanStats = $cleaner->getCleaningStats($originalSource, $cleanedSource);
        
        // Test flattening
        $flattened = $cleaner->cleanAndFlatten($sourceFiles);
        $flattenStats = $cleaner->getCleaningStats(implode("\n", $sourceFiles), $flattened);
        
        $this->table(
            ['Operation', 'Original Size', 'Processed Size', 'Reduction'],
            [
                [
                    'Clean Only',
                    number_format($cleanStats['original_size']) . ' bytes',
                    number_format($cleanStats['cleaned_size']) . ' bytes',
                    "{$cleanStats['reduction_percentage']}%"
                ],
                [
                    'Clean + Flatten',
                    number_format($flattenStats['original_size']) . ' bytes',
                    number_format($flattenStats['cleaned_size']) . ' bytes',
                    "{$flattenStats['reduction_percentage']}%"
                ],
            ]
        );

        $this->newLine();
        $this->comment('💡 Use --output=file.sol to save processed code to file');
        $this->comment('💡 Use --action=analyze for detailed code analysis');
    }
}
