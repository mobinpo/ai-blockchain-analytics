<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SecurityFindingValidator;
use App\Services\SolidityCleanerService;
use App\Services\OpenAiStreamService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AnalyzeVulnerableContract extends Command
{
    protected $signature = 'analyze:vulnerable 
                            {contract : Contract name to analyze}
                            {--model=gpt-4 : OpenAI model to use}
                            {--save-results : Save results to file}
                            {--show-raw : Show raw OpenAI response}';

    protected $description = 'Analyze a specific vulnerable contract for testing purposes';

    public function __construct(
        private readonly SecurityFindingValidator $validator,
        private readonly SolidityCleanerService $cleaner
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $contractName = $this->argument('contract');
        $model = $this->option('model');
        
        $this->info("🔍 Analyzing Vulnerable Contract: {$contractName}");
        $this->line("Model: {$model}");
        $this->newLine();

        try {
            // Load and extract contract source
            $sourceCode = $this->getContractSource($contractName);
            if (empty($sourceCode)) {
                $this->error("Contract '{$contractName}' not found in vulnerable contracts suite");
                return 1;
            }

            $this->line("📄 Contract source loaded (" . strlen($sourceCode) . " characters)");

            // Clean source code for analysis
            $cleaningResult = $this->cleaner->cleanForPrompt($sourceCode);
            $cleanedCode = $cleaningResult['cleaned_code'];
            
            $this->line("🧹 Source code cleaned: {$cleaningResult['original_size']} → {$cleaningResult['cleaned_size']} characters");

            // Build analysis prompt
            $prompt = $this->buildPrompt($cleanedCode, $contractName);
            
            if ($this->output->isVerbose()) {
                $this->line("📝 Generated prompt:");
                $this->line($prompt);
                $this->newLine();
            }

            // Analyze with OpenAI
            $this->line("🤖 Starting AI analysis...");
            
            $streamService = new OpenAiStreamService($model, 2000, 0.1);
            $analysisId = 'cli_' . $contractName . '_' . time();
            
            $response = $streamService->streamSecurityAnalysis($prompt, $analysisId);
            
            if ($this->option('show-raw')) {
                $this->newLine();
                $this->line("📋 Raw OpenAI Response:");
                $this->line($response);
                $this->newLine();
            }

            // Parse and validate findings
            $this->line("🔍 Parsing security findings...");
            $findings = $this->validator->parseOpenAiResponse($response);

            // Display results
            $this->displayResults($contractName, $findings, $cleaningResult);

            // Save results if requested
            if ($this->option('save-results')) {
                $this->saveResults($contractName, $findings, $response);
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Analysis failed: {$e->getMessage()}");
            
            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }
            
            return 1;
        }
    }

    private function getContractSource(string $contractName): string
    {
        $contractsPath = base_path('tests/Contracts/VulnerableContracts.sol');
        
        if (!File::exists($contractsPath)) {
            throw new \Exception("Vulnerable contracts file not found");
        }

        $fullSource = File::get($contractsPath);
        
        // Extract specific contract
        $pattern = "/contract {$contractName}.*?(?=(?:contract|\Z))/s";
        preg_match($pattern, $fullSource, $matches);
        
        return $matches[0] ?? '';
    }

    private function buildPrompt(string $cleanedCode, string $contractName): string
    {
        $schemaPath = base_path('schemas/security-finding-v3.json');
        $schema = json_decode(File::get($schemaPath), true);
        $exampleFinding = $schema['examples'][0] ?? [];

        $prompt = "You are an expert smart contract security auditor. Analyze the following Solidity contract for security vulnerabilities.\n\n";
        
        $prompt .= "CONTRACT: {$contractName}\n";
        $prompt .= "NOTE: This is a deliberately vulnerable test contract. Identify ALL security issues.\n\n";

        $prompt .= "ANALYSIS REQUIREMENTS:\n";
        $prompt .= "- Identify ALL security vulnerabilities, no matter how minor\n";
        $prompt .= "- Focus on critical issues: reentrancy, access control, integer overflow, etc.\n";
        $prompt .= "- Use OWASP Top 10 and SWC Registry classifications\n";
        $prompt .= "- Provide specific line numbers and detailed explanations\n";
        $prompt .= "- Include actionable remediation recommendations\n\n";

        $prompt .= "OUTPUT FORMAT:\n";
        $prompt .= "Return findings as a JSON array following this structure:\n";
        $prompt .= "```json\n" . json_encode($exampleFinding, JSON_PRETTY_PRINT) . "\n```\n\n";

        $prompt .= "CONTRACT CODE:\n";
        $prompt .= "```solidity\n{$cleanedCode}\n```\n\n";

        $prompt .= "Provide comprehensive security analysis with detailed findings in JSON format.";

        return $prompt;
    }

    private function displayResults(string $contractName, array $findings, array $cleaningResult): void
    {
        $this->newLine();
        $this->info("📊 Analysis Results for {$contractName}");
        
        // Summary table
        $this->table(['Metric', 'Value'], [
            ['Total Findings', count($findings)],
            ['Critical', $this->countBySeverity($findings, 'CRITICAL')],
            ['High', $this->countBySeverity($findings, 'HIGH')],
            ['Medium', $this->countBySeverity($findings, 'MEDIUM')],
            ['Low', $this->countBySeverity($findings, 'LOW')],
            ['Info', $this->countBySeverity($findings, 'INFO')],
            ['Source Size (Original)', $cleaningResult['original_size'] . ' chars'],
            ['Source Size (Cleaned)', $cleaningResult['cleaned_size'] . ' chars'],
            ['Compression Ratio', round((1 - $cleaningResult['cleaned_size'] / $cleaningResult['original_size']) * 100, 1) . '%']
        ]);

        if (empty($findings)) {
            $this->warn("⚠️  No vulnerabilities detected. This may indicate an issue with the analysis.");
            return;
        }

        // Detailed findings
        $this->newLine();
        $this->info("🔍 Detailed Findings:");
        
        foreach ($findings as $index => $finding) {
            $severity = $finding['severity'] ?? 'UNKNOWN';
            $title = $finding['title'] ?? 'Untitled Finding';
            $category = $finding['category'] ?? 'Unknown Category';
            $line = $finding['location']['line'] ?? 'N/A';
            $description = $finding['description'] ?? 'No description provided';
            
            $severityColor = match($severity) {
                'CRITICAL' => 'red',
                'HIGH' => 'yellow',
                'MEDIUM' => 'blue',
                'LOW' => 'cyan',
                default => 'white'
            };
            
            $this->newLine();
            $this->line("Finding " . ($index + 1) . ":");
            $this->line("  <fg={$severityColor}>[{$severity}]</> {$title}");
            $this->line("  📂 Category: {$category}");
            $this->line("  📍 Location: Line {$line}");
            $this->line("  📝 Description: " . $this->truncateText($description, 100));
            
            if (!empty($finding['recommendation']['summary'])) {
                $this->line("  💡 Recommendation: " . $this->truncateText($finding['recommendation']['summary'], 100));
            }
        }

        // Category breakdown
        $categories = array_count_values(array_column($findings, 'category'));
        arsort($categories);
        
        $this->newLine();
        $this->info("📈 Vulnerability Categories:");
        foreach ($categories as $category => $count) {
            $this->line("  • {$category}: {$count}");
        }
    }

    private function countBySeverity(array $findings, string $severity): int
    {
        return count(array_filter($findings, fn($f) => ($f['severity'] ?? '') === $severity));
    }

    private function truncateText(string $text, int $length): string
    {
        return strlen($text) > $length ? substr($text, 0, $length) . '...' : $text;
    }

    private function saveResults(string $contractName, array $findings, string $rawResponse): void
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "analysis_{$contractName}_{$timestamp}.json";
        $filepath = storage_path("app/analysis_results/{$filename}");
        
        // Ensure directory exists
        $directory = dirname($filepath);
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $results = [
            'contract_name' => $contractName,
            'timestamp' => now()->toISOString(),
            'model' => $this->option('model'),
            'findings_count' => count($findings),
            'findings' => $findings,
            'raw_response' => $rawResponse,
            'summary' => [
                'total_findings' => count($findings),
                'by_severity' => [
                    'CRITICAL' => $this->countBySeverity($findings, 'CRITICAL'),
                    'HIGH' => $this->countBySeverity($findings, 'HIGH'),
                    'MEDIUM' => $this->countBySeverity($findings, 'MEDIUM'),
                    'LOW' => $this->countBySeverity($findings, 'LOW'),
                    'INFO' => $this->countBySeverity($findings, 'INFO')
                ],
                'categories' => array_count_values(array_column($findings, 'category'))
            ]
        ];

        File::put($filepath, json_encode($results, JSON_PRETTY_PRINT));
        
        $this->info("💾 Results saved to: {$filepath}");
    }
}