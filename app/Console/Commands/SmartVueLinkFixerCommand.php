<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SmartVueLinkFixerCommand extends Command
{
    protected $signature = 'link:fix-vue 
                          {--apply : Apply the fixes}
                          {--show-context : Show more context around each issue}';

    protected $description = 'Smart Vue link fixer with context-aware route suggestions';

    protected array $contextualSuggestions = [
        // Explorer/Blockchain related
        'ExplorerSearchDemo.vue' => [
            'View' => "() => window.open(`https://etherscan.io/tx/\${tx.hash}`, '_blank')",
            'context' => 'Blockchain transaction viewer'
        ],
        
        // Dashboard security findings
        'Dashboard.vue' => [
            'View Details' => "showSecurityDetails(finding)",
            'context' => 'Security findings table'
        ],
        
        // Test page
        'CssTest.vue' => [
            'Action Button' => "\$router.push({name: 'dashboard'})",
            'context' => 'CSS test page'
        ],
        
        // Verification badge demo
        'BadgeDemo.vue' => [
            'View Details' => "showBadgeDetails(badge)",
            'context' => 'Verification badge demo'
        ]
    ];

    public function handle(): int
    {
        $this->displayHeader();
        
        $fixes = $this->findAndSuggestFixes();
        
        if (empty($fixes)) {
            $this->info('🎉 No actionless buttons found that need fixing!');
            return Command::SUCCESS;
        }
        
        $this->displaySuggestions($fixes);
        
        if ($this->option('apply')) {
            return $this->applyFixes($fixes);
        }
        
        $this->info("\n💡 Run with --apply to implement these fixes");
        return Command::SUCCESS;
    }

    protected function displayHeader(): void
    {
        $this->info('
╔══════════════════════════════════════════════════════════════╗
║                🎯 Smart Vue Link Fixer                      ║
║                                                              ║
║  Context-aware fixes for actionless Vue buttons/links       ║
╚══════════════════════════════════════════════════════════════╝
        ');
    }

    protected function findAndSuggestFixes(): array
    {
        $fixes = [];
        
        // Known issues from previous audit
        $knownIssues = [
            [
                'file' => 'resources/js/Components/Demo/Demos/ExplorerSearchDemo.vue',
                'line' => 184,
                'text' => 'View',
                'element' => '<button class="text-sm text-brand-500 hover:text-indigo-800">View</button>'
            ],
            [
                'file' => 'resources/js/Pages/CssTest.vue', 
                'line' => 72,
                'text' => 'Action Button',
                'element' => '<button class="btn btn-primary">Action Button</button>'
            ],
            [
                'file' => 'resources/js/Pages/Dashboard.vue',
                'line' => 392,
                'text' => 'View Details', 
                'element' => '<button class="text-brand-500 hover:text-indigo-900 font-medium">View Details</button>'
            ],
            [
                'file' => 'resources/js/Pages/Verification/BadgeDemo.vue',
                'line' => 204,
                'text' => 'View Details',
                'element' => '<button class="text-blue-600 hover:text-blue-500 text-sm">View Details</button>'
            ]
        ];
        
        foreach ($knownIssues as $issue) {
            $component = basename($issue['file']);
            $suggestion = $this->getContextualSuggestion($component, $issue['text']);
            
            if ($suggestion) {
                $fixes[] = array_merge($issue, ['suggestion' => $suggestion]);
            }
        }
        
        return $fixes;
    }

    protected function getContextualSuggestion(string $component, string $text): ?array
    {
        if (!isset($this->contextualSuggestions[$component])) {
            return null;
        }
        
        $componentSuggestions = $this->contextualSuggestions[$component];
        
        if (isset($componentSuggestions[$text])) {
            return [
                'action' => $componentSuggestions[$text],
                'context' => $componentSuggestions['context'],
                'type' => $this->determineActionType($componentSuggestions[$text])
            ];
        }
        
        return null;
    }

    protected function determineActionType(string $action): string
    {
        if (str_contains($action, 'window.open')) {
            return 'external_link';
        } elseif (str_contains($action, '$router.push')) {
            return 'router_navigation';
        } elseif (str_contains($action, '(') && str_contains($action, ')')) {
            return 'method_call';
        }
        
        return 'custom_action';
    }

    protected function displaySuggestions(array $fixes): void
    {
        $this->info("🔍 Found " . count($fixes) . " actionless buttons with smart suggestions:");
        $this->line("═══════════════════════════════════════════════════════");
        
        foreach ($fixes as $i => $fix) {
            $this->line("\n<fg=cyan>" . ($i + 1) . ". " . basename($fix['file']) . ":{$fix['line']}</>");
            $this->line("   📝 Text: \"{$fix['text']}\"");
            $this->line("   📂 Context: {$fix['suggestion']['context']}");
            $this->line("   🔧 Type: {$fix['suggestion']['type']}");
            
            $this->line("\n   <fg=red>Before:</>");
            $this->line("   " . $fix['element']);
            
            $this->line("\n   <fg=green>After:</>");
            $this->line("   " . $this->generateFixedElement($fix));
            
            if ($this->option('show-context')) {
                $this->showFileContext($fix['file'], $fix['line']);
            }
        }
    }

    protected function generateFixedElement(array $fix): string
    {
        $element = $fix['element'];
        $action = $fix['suggestion']['action'];
        
        // Insert @click before the closing >
        if (preg_match('/^(.*>)(.*)(<\/button>)$/', $element, $matches)) {
            $beforeClosing = $matches[1];
            $content = $matches[2]; 
            $closing = $matches[3];
            
            // Remove the last > and add @click
            $beforeClosing = rtrim($beforeClosing, '>');
            return $beforeClosing . " @click=\"{$action}\">" . $content . $closing;
        }
        
        return $element;
    }

    protected function showFileContext(string $file, int $line): void
    {
        $content = file_get_contents(base_path($file));
        $lines = explode("\n", $content);
        
        $start = max(0, $line - 4);
        $end = min(count($lines) - 1, $line + 3);
        
        $this->line("\n   📋 File context:");
        for ($i = $start; $i <= $end; $i++) {
            $lineNum = $i + 1;
            $prefix = $lineNum === $line ? "   → " : "     ";
            $color = $lineNum === $line ? "fg=yellow" : "fg=gray";
            $this->line($prefix . "<{$color}>{$lineNum}: " . trim($lines[$i]) . "</>");
        }
    }

    protected function applyFixes(array $fixes): int
    {
        $applied = 0;
        $backupDir = storage_path('app/vue-fixes-backup-' . date('Y-m-d_H-i-s'));
        
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        foreach ($fixes as $fix) {
            if ($this->applyFix($fix, $backupDir)) {
                $applied++;
                $this->line("✅ Fixed: " . basename($fix['file']) . ":{$fix['line']}");
            } else {
                $this->line("❌ Failed: " . basename($fix['file']) . ":{$fix['line']}");
            }
        }
        
        $this->info("\n🎉 Applied {$applied}/" . count($fixes) . " fixes");
        $this->line("📁 Backups saved to: {$backupDir}");
        
        if ($applied > 0) {
            $this->info("\n📝 Next steps:");
            $this->line("1. Test the fixed buttons in your application");
            $this->line("2. Add any missing methods to your Vue components");
            $this->line("3. Consider adding proper error handling");
        }
        
        return Command::SUCCESS;
    }

    protected function applyFix(array $fix, string $backupDir): bool
    {
        $filePath = base_path($fix['file']);
        
        // Create backup
        $backupPath = $backupDir . '/' . basename($fix['file']);
        copy($filePath, $backupPath);
        
        // Read file
        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);
        
        // Apply fix
        $oldLine = $lines[$fix['line'] - 1];
        $newLine = str_replace($fix['element'], $this->generateFixedElement($fix), $oldLine);
        
        if ($oldLine !== $newLine) {
            $lines[$fix['line'] - 1] = $newLine;
            $newContent = implode("\n", $lines);
            
            return file_put_contents($filePath, $newContent) !== false;
        }
        
        return false;
    }
}