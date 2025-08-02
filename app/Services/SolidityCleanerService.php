<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class SolidityCleanerService
{
    private const IMPORT_PATTERN = '/^import\s+(?:[\{\}\s\w,*]*\s+from\s+)?["\']([^"\']+)["\']\s*;/m';
    private const SINGLE_LINE_COMMENT_PATTERN = '/\/\/.*$/m';
    private const MULTI_LINE_COMMENT_PATTERN = '/\/\*[\s\S]*?\*\//';
    private const NATSPEC_COMMENT_PATTERN = '/\/\*\*[\s\S]*?\*\//';
    
    /**
     * Clean Solidity source code for optimal prompt input
     */
    public function cleanForPrompt(string $sourceCode): string
    {
        if (empty(trim($sourceCode))) {
            throw new InvalidArgumentException('Source code cannot be empty');
        }

        $cleaned = $sourceCode;
        
        // Remove comments first (preserves string literals)
        $cleaned = $this->removeComments($cleaned);
        
        // Remove excessive whitespace
        $cleaned = $this->normalizeWhitespace($cleaned);
        
        // Remove empty lines
        $cleaned = $this->removeEmptyLines($cleaned);
        
        return trim($cleaned);
    }

    /**
     * Clean and flatten Solidity source code with imports
     */
    public function cleanAndFlatten(array $sourceFiles): string
    {
        if (empty($sourceFiles)) {
            throw new InvalidArgumentException('Source files cannot be empty');
        }

        $mainContract = '';
        $imports = [];
        $dependencies = [];
        
        // Process each file
        foreach ($sourceFiles as $filename => $content) {
            $cleaned = $this->cleanForPrompt($content);
            
            // Extract imports
            $fileImports = $this->extractImports($content);
            $imports = array_merge($imports, $fileImports);
            
            // Remove import statements from cleaned content
            $cleanedWithoutImports = $this->removeImports($cleaned);
            
            if ($this->isMainContract($filename, $cleanedWithoutImports)) {
                $mainContract = $cleanedWithoutImports;
            } else {
                $dependencies[$filename] = $cleanedWithoutImports;
            }
        }

        // Build flattened output
        $output = [];
        
        // Add SPDX license if found
        $license = $this->extractLicense($mainContract);
        if ($license) {
            $output[] = $license;
        }
        
        // Add pragma statements
        $pragmas = $this->extractPragmas($mainContract);
        if (!empty($pragmas)) {
            $output = array_merge($output, $pragmas);
        }
        
        // Add unique imports (flattened)
        $flattenedImports = $this->flattenImports($imports);
        if (!empty($flattenedImports)) {
            $output = array_merge($output, $flattenedImports);
        }
        
        // Add dependencies first
        foreach ($dependencies as $filename => $content) {
            $output[] = "// File: {$filename}";
            $output[] = $this->removeMetadata($content);
        }
        
        // Add main contract
        if ($mainContract) {
            $output[] = $this->removeMetadata($mainContract);
        }
        
        return implode("\n\n", array_filter($output));
    }

    /**
     * Extract imports for dependency analysis
     */
    public function extractImports(string $sourceCode): array
    {
        preg_match_all(self::IMPORT_PATTERN, $sourceCode, $matches);
        return $matches[1] ?? [];
    }

    /**
     * Get cleaned source size statistics
     */
    public function getCleaningStats(string $original, string $cleaned): array
    {
        return [
            'original_size' => strlen($original),
            'cleaned_size' => strlen($cleaned),
            'reduction_bytes' => strlen($original) - strlen($cleaned),
            'reduction_percentage' => round((1 - strlen($cleaned) / strlen($original)) * 100, 2),
            'original_lines' => substr_count($original, "\n") + 1,
            'cleaned_lines' => substr_count($cleaned, "\n") + 1,
        ];
    }

    /**
     * Check if code contains specific Solidity patterns
     */
    public function analyzeCode(string $sourceCode): array
    {
        return [
            'has_comments' => $this->hasComments($sourceCode),
            'has_imports' => $this->hasImports($sourceCode),
            'has_interfaces' => preg_match('/\binterface\s+\w+/i', $sourceCode) === 1,
            'has_libraries' => preg_match('/\blibrary\s+\w+/i', $sourceCode) === 1,
            'has_contracts' => preg_match('/\bcontract\s+\w+/i', $sourceCode) === 1,
            'has_abstract_contracts' => preg_match('/\babstract\s+contract\s+\w+/i', $sourceCode) === 1,
            'pragma_versions' => $this->extractPragmas($sourceCode),
            'imports' => $this->extractImports($sourceCode),
            'estimated_tokens' => $this->estimateTokenCount($sourceCode),
        ];
    }

    private function removeComments(string $code): string
    {
        // Remove multi-line comments first (including NatSpec)
        $code = preg_replace(self::NATSPEC_COMMENT_PATTERN, '', $code);
        $code = preg_replace(self::MULTI_LINE_COMMENT_PATTERN, '', $code);
        
        // Remove single-line comments but preserve string literals
        $lines = explode("\n", $code);
        $cleaned = [];
        
        foreach ($lines as $line) {
            $inString = false;
            $stringChar = null;
            $result = '';
            
            for ($i = 0; $i < strlen($line); $i++) {
                $char = $line[$i];
                $nextChar = isset($line[$i + 1]) ? $line[$i + 1] : '';
                
                if (!$inString && ($char === '"' || $char === "'")) {
                    $inString = true;
                    $stringChar = $char;
                    $result .= $char;
                } elseif ($inString && $char === $stringChar && $line[$i - 1] !== '\\') {
                    $inString = false;
                    $stringChar = null;
                    $result .= $char;
                } elseif (!$inString && $char === '/' && $nextChar === '/') {
                    // Found comment outside string, stop processing this line
                    break;
                } else {
                    $result .= $char;
                }
            }
            
            $cleaned[] = rtrim($result);
        }
        
        return implode("\n", $cleaned);
    }

    private function normalizeWhitespace(string $code): string
    {
        // Replace multiple spaces with single space
        $code = preg_replace('/[ \t]+/', ' ', $code);
        
        // Normalize line endings
        $code = preg_replace('/\r\n|\r/', "\n", $code);
        
        return $code;
    }

    private function removeEmptyLines(string $code): string
    {
        $lines = explode("\n", $code);
        $nonEmptyLines = array_filter($lines, fn($line) => trim($line) !== '');
        return implode("\n", $nonEmptyLines);
    }

    private function removeImports(string $code): string
    {
        return preg_replace(self::IMPORT_PATTERN, '', $code);
    }

    private function extractLicense(string $code): ?string
    {
        if (preg_match('/^\/\/ SPDX-License-Identifier:.*$/m', $code, $matches)) {
            return $matches[0];
        }
        return null;
    }

    private function extractPragmas(string $code): array
    {
        preg_match_all('/^\s*pragma\s+[^;]+;/m', $code, $matches);
        return array_unique($matches[0] ?? []);
    }

    private function flattenImports(array $imports): array
    {
        $flattened = [];
        $unique = array_unique($imports);
        
        foreach ($unique as $import) {
            // Skip relative imports as they'll be flattened
            if (!str_starts_with($import, './') && !str_starts_with($import, '../')) {
                $flattened[] = "import \"{$import}\";";
            }
        }
        
        return $flattened;
    }

    private function removeMetadata(string $code): string
    {
        // Remove pragma and SPDX license as they'll be at the top
        $code = preg_replace('/^\/\/ SPDX-License-Identifier:.*$/m', '', $code);
        $code = preg_replace('/^\s*pragma\s+[^;]+;/m', '', $code);
        
        return trim($code);
    }

    private function isMainContract(string $filename, string $content): bool
    {
        // Heuristics to determine main contract file
        $contractCount = preg_match_all('/\bcontract\s+\w+/i', $content);
        $hasMain = preg_match('/\bmain\b/i', $filename) === 1;
        $isNotInterface = preg_match('/\binterface\b/i', $filename) !== 1;
        $isNotLibrary = preg_match('/\blibrary\b/i', $filename) !== 1;
        
        return $contractCount > 0 && ($hasMain || ($isNotInterface && $isNotLibrary));
    }

    private function hasComments(string $code): bool
    {
        return preg_match(self::SINGLE_LINE_COMMENT_PATTERN, $code) === 1 ||
               preg_match(self::MULTI_LINE_COMMENT_PATTERN, $code) === 1;
    }

    private function hasImports(string $code): bool
    {
        return preg_match(self::IMPORT_PATTERN, $code) === 1;
    }

    private function estimateTokenCount(string $code): int
    {
        // Rough estimation: average 4 characters per token
        return (int) ceil(strlen($code) / 4);
    }
}