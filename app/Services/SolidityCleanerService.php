<?php

declare(strict_types=1);

namespace App\Services;

final class SolidityCleanerService
{
    /**
     * Quick clean for prompt input (most aggressive cleaning)
     */
    public function quickCleanForPrompt(string $sourceCode): string
    {
        $options = [
            'strip_comments' => true,
            'flatten_imports' => true,
            'remove_empty_lines' => true,
            'minify_whitespace' => true,
        ];

        $result = $this->cleanSolidityCode($sourceCode, $options);
        return $result['cleaned_code'];
    }

    /**
     * Clean Solidity code with configurable options
     */
    public function cleanSolidityCode(string $sourceCode, array $options = []): array
    {
        $options = array_merge([
            'strip_comments' => true,
            'flatten_imports' => true,
            'remove_empty_lines' => true,
            'preserve_natspec' => false,
            'include_pragma' => true,
            'minify_whitespace' => false,
        ], $options);

        $originalSize = strlen($sourceCode);
        $startTime = microtime(true);

        $cleanedCode = $sourceCode;
        $statistics = [
            'original_size' => $originalSize,
            'original_lines' => substr_count($sourceCode, "\n") + 1,
            'comments_removed' => 0,
            'imports_flattened' => 0,
            'empty_lines_removed' => 0,
        ];

        // Strip comments
        if ($options['strip_comments']) {
            $statistics['comments_removed'] = $this->countComments($cleanedCode);
            $cleanedCode = $this->stripComments($cleanedCode, $options['preserve_natspec']);
        }

        // Flatten imports
        if ($options['flatten_imports']) {
            $importResult = $this->flattenImports($cleanedCode);
            $cleanedCode = $importResult['code'];
            $statistics['imports_flattened'] = $importResult['count'];
        }

        // Remove empty lines
        if ($options['remove_empty_lines']) {
            $beforeLines = substr_count($cleanedCode, "\n") + 1;
            $cleanedCode = $this->removeEmptyLines($cleanedCode);
            $afterLines = substr_count($cleanedCode, "\n") + 1;
            $statistics['empty_lines_removed'] = $beforeLines - $afterLines;
        }

        // Minify whitespace
        if ($options['minify_whitespace']) {
            $cleanedCode = $this->minifyWhitespace($cleanedCode);
        }

        // Preserve pragma if requested
        if ($options['include_pragma']) {
            $cleanedCode = $this->ensurePragma($cleanedCode, $sourceCode);
        }

        $statistics['cleaned_size'] = strlen($cleanedCode);
        $statistics['cleaned_lines'] = substr_count($cleanedCode, "\n") + 1;
        $statistics['compression_ratio'] = round((1 - (strlen($cleanedCode) / $originalSize)) * 100, 2);
        $statistics['processing_time_ms'] = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'original_code' => $sourceCode,
            'cleaned_code' => $cleanedCode,
            'statistics' => $statistics,
        ];
    }

    /**
     * Strip comments from Solidity code
     */
    private function stripComments(string $code, bool $preserveNatspec = false): string
    {
        if ($preserveNatspec) {
            // Keep /// and /** */ comments (NatSpec) but remove regular comments
            $code = preg_replace('/(?<!\/\/\/)\/\/(?!\/)[^\r\n]*/', '', $code);
            $code = preg_replace('/\/\*(?!\*)[\s\S]*?\*\//', '', $code);
        } else {
            // Remove all comments
            $code = preg_replace('/\/\/[^\r\n]*/', '', $code);
            $code = preg_replace('/\/\*[\s\S]*?\*\//', '', $code);
        }

        return $code;
    }

    /**
     * Flatten imports by removing import statements
     */
    private function flattenImports(string $code): array
    {
        // Count imports
        preg_match_all('/import\s+.*?;/s', $code, $matches);
        $importCount = count($matches[0]);

        // Remove import statements
        $cleanedCode = preg_replace('/import\s+.*?;\s*\n?/s', '', $code);

        return [
            'code' => $cleanedCode,
            'count' => $importCount,
        ];
    }

    /**
     * Remove empty lines and excessive whitespace
     */
    private function removeEmptyLines(string $code): string
    {
        // Remove completely empty lines
        $code = preg_replace('/^\s*\n/m', '', $code);
        // Remove multiple consecutive newlines
        $code = preg_replace('/\n{3,}/', "\n\n", $code);
        // Remove trailing whitespace from lines
        $code = preg_replace('/[ \t]+$/m', '', $code);
        
        return trim($code);
    }

    /**
     * Minify whitespace while preserving code structure
     */
    private function minifyWhitespace(string $code): string
    {
        // Preserve string literals
        $strings = [];
        $stringIndex = 0;
        
        // Extract string literals to preserve them
        $code = preg_replace_callback('/"([^"\\\\]|\\\\.)*"/', function($matches) use (&$strings, &$stringIndex) {
            $placeholder = "___STRING_{$stringIndex}___";
            $strings[$placeholder] = $matches[0];
            $stringIndex++;
            return $placeholder;
        }, $code);

        // Minify whitespace
        $code = preg_replace('/\s+/', ' ', $code);
        $code = preg_replace('/\s*([{}();,])\s*/', '$1', $code);
        $code = preg_replace('/;\s*}/', ';}', $code);

        // Restore string literals
        foreach ($strings as $placeholder => $original) {
            $code = str_replace($placeholder, $original, $code);
        }

        return trim($code);
    }

    /**
     * Count comments in the original code
     */
    private function countComments(string $code): int
    {
        $singleLineComments = preg_match_all('/\/\/[^\r\n]*/', $code);
        $multiLineComments = preg_match_all('/\/\*[\s\S]*?\*\//', $code);
        
        return ($singleLineComments ?: 0) + ($multiLineComments ?: 0);
    }

    /**
     * Ensure pragma statement is preserved
     */
    private function ensurePragma(string $cleanedCode, string $originalCode): string
    {
        // Check if pragma exists in cleaned code
        if (!preg_match('/pragma\s+solidity/', $cleanedCode)) {
            // Extract pragma from original code
            if (preg_match('/pragma\s+solidity\s+[^;]+;/', $originalCode, $matches)) {
                $pragma = $matches[0];
                // Prepend pragma to cleaned code
                $cleanedCode = $pragma . "\n\n" . ltrim($cleanedCode);
            }
        }

        return $cleanedCode;
    }

    /**
     * Get default cleaning options
     */
    public function getDefaultOptions(): array
    {
        return [
            'strip_comments' => true,
            'flatten_imports' => true,
            'remove_empty_lines' => true,
            'preserve_natspec' => false,
            'include_pragma' => true,
            'minify_whitespace' => false,
        ];
    }
}
