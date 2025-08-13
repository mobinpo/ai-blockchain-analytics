<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SolidityCleanerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class SolidityCleanerController extends Controller
{
    public function __construct(
        private readonly SolidityCleanerService $solidityCleanerService
    ) {}

    /**
     * Clean Solidity code for prompt input
     */
    public function clean(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'source_code' => 'required|string|max:500000', // 500KB limit
            'options' => 'sometimes|array',
            'options.strip_comments' => 'sometimes|boolean',
            'options.flatten_imports' => 'sometimes|boolean',
            'options.remove_empty_lines' => 'sometimes|boolean',
            'options.preserve_natspec' => 'sometimes|boolean',
            'options.include_pragma' => 'sometimes|boolean',
            'options.minify_whitespace' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input parameters',
                'errors' => $validator->errors()
            ], 422);
        }

        $sourceCode = $request->input('source_code');
        $options = $request->input('options', []);

        try {
            $result = $this->solidityCleanerService->cleanSolidityCode($sourceCode, $options);

            return response()->json([
                'success' => true,
                'data' => [
                    'cleaned_code' => $result['cleaned_code'],
                    'statistics' => $result['statistics'],
                    'original_size' => strlen($sourceCode),
                    'cleaned_size' => strlen($result['cleaned_code']),
                    'compression_ratio' => $result['statistics']['compression_ratio'] . '%',
                ],
                'message' => 'Solidity code cleaned successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clean Solidity code',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Quick clean for prompt input (aggressive cleaning)
     */
    public function quickClean(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'source_code' => 'required|string|max:500000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input parameters',
                'errors' => $validator->errors()
            ], 422);
        }

        $sourceCode = $request->input('source_code');

        try {
            $cleanedCode = $this->solidityCleanerService->quickCleanForPrompt($sourceCode);

            $originalSize = strlen($sourceCode);
            $cleanedSize = strlen($cleanedCode);
            $compressionRatio = round((1 - ($cleanedSize / $originalSize)) * 100, 2);

            return response()->json([
                'success' => true,
                'data' => [
                    'cleaned_code' => $cleanedCode,
                    'original_size' => $originalSize,
                    'cleaned_size' => $cleanedSize,
                    'compression_ratio' => $compressionRatio . '%',
                    'size_reduction' => $originalSize - $cleanedSize,
                ],
                'message' => 'Solidity code cleaned for prompt input'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clean Solidity code',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get default cleaning options
     */
    public function options(): JsonResponse
    {
        $defaultOptions = $this->solidityCleanerService->getDefaultOptions();

        return response()->json([
            'success' => true,
            'data' => [
                'default_options' => $defaultOptions,
                'option_descriptions' => [
                    'strip_comments' => 'Remove all comments from the code',
                    'flatten_imports' => 'Remove import statements',
                    'remove_empty_lines' => 'Remove empty lines and excessive whitespace',
                    'preserve_natspec' => 'Keep NatSpec comments (/// and /** */)',
                    'include_pragma' => 'Preserve pragma solidity statement',
                    'minify_whitespace' => 'Minimize whitespace while preserving structure',
                ],
                'presets' => [
                    'prompt_input' => [
                        'description' => 'Aggressive cleaning for AI prompt input',
                        'options' => [
                            'strip_comments' => true,
                            'flatten_imports' => true,
                            'remove_empty_lines' => true,
                            'preserve_natspec' => false,
                            'include_pragma' => true,
                            'minify_whitespace' => true,
                        ]
                    ],
                    'documentation' => [
                        'description' => 'Clean while preserving readability',
                        'options' => [
                            'strip_comments' => true,
                            'flatten_imports' => true,
                            'remove_empty_lines' => true,
                            'preserve_natspec' => true,
                            'include_pragma' => true,
                            'minify_whitespace' => false,
                        ]
                    ],
                    'analysis' => [
                        'description' => 'Preserve structure for analysis',
                        'options' => [
                            'strip_comments' => true,
                            'flatten_imports' => false,
                            'remove_empty_lines' => true,
                            'preserve_natspec' => false,
                            'include_pragma' => true,
                            'minify_whitespace' => false,
                        ]
                    ]
                ]
            ]
        ]);
    }

    /**
     * Clean with preset configuration
     */
    public function cleanWithPreset(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'source_code' => 'required|string|max:500000',
            'preset' => 'required|string|in:prompt_input,documentation,analysis',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input parameters',
                'errors' => $validator->errors()
            ], 422);
        }

        $sourceCode = $request->input('source_code');
        $preset = $request->input('preset');

        // Get preset options
        $presets = [
            'prompt_input' => [
                'strip_comments' => true,
                'flatten_imports' => true,
                'remove_empty_lines' => true,
                'preserve_natspec' => false,
                'include_pragma' => true,
                'minify_whitespace' => true,
            ],
            'documentation' => [
                'strip_comments' => true,
                'flatten_imports' => true,
                'remove_empty_lines' => true,
                'preserve_natspec' => true,
                'include_pragma' => true,
                'minify_whitespace' => false,
            ],
            'analysis' => [
                'strip_comments' => true,
                'flatten_imports' => false,
                'remove_empty_lines' => true,
                'preserve_natspec' => false,
                'include_pragma' => true,
                'minify_whitespace' => false,
            ]
        ];

        try {
            $options = $presets[$preset];
            $result = $this->solidityCleanerService->cleanSolidityCode($sourceCode, $options);

            return response()->json([
                'success' => true,
                'data' => [
                    'cleaned_code' => $result['cleaned_code'],
                    'statistics' => $result['statistics'],
                    'preset_used' => $preset,
                    'options_applied' => $options,
                ],
                'message' => "Solidity code cleaned with '{$preset}' preset"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clean Solidity code',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate Solidity syntax (basic check)
     */
    public function validate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'source_code' => 'required|string|max:500000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input parameters',
                'errors' => $validator->errors()
            ], 422);
        }

        $sourceCode = $request->input('source_code');

        try {
            $validation = $this->validateSyntax($sourceCode);

            return response()->json([
                'success' => true,
                'data' => $validation,
                'message' => $validation['is_valid'] ? 'Solidity syntax is valid' : 'Solidity syntax issues detected'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate Solidity syntax',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Basic Solidity syntax validation
     */
    private function validateSyntax(string $code): array
    {
        $errors = [];
        $warnings = [];

        // Check for balanced braces
        $braceCount = substr_count($code, '{') - substr_count($code, '}');
        if ($braceCount !== 0) {
            $errors[] = 'Unbalanced braces detected';
        }

        // Check for balanced parentheses
        $parenCount = substr_count($code, '(') - substr_count($code, ')');
        if ($parenCount !== 0) {
            $errors[] = 'Unbalanced parentheses detected';
        }

        // Check for pragma statement
        if (!preg_match('/pragma\s+solidity/', $code)) {
            $warnings[] = 'No pragma statement found';
        }

        // Check for contract/interface/library declaration
        if (!preg_match('/\b(contract|interface|library)\s+\w+/', $code)) {
            $warnings[] = 'No contract, interface, or library declaration found';
        }

        // Check for unclosed strings
        if (preg_match_all('/"/', $code) % 2 !== 0) {
            $errors[] = 'Unclosed string literal detected';
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'score' => empty($errors) ? (empty($warnings) ? 100 : 85) : 0,
        ];
    }
}
