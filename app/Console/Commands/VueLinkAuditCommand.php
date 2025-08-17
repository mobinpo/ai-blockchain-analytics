<?php

namespace App\Console\Commands;

use App\Support\Fuzzy;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class VueLinkAuditCommand extends Command
{
    protected $signature = 'link:audit-vue
                          {--fix : Automatically apply fixes}
                          {--base=http://localhost:8003 : Base URL for your application}
                          {--preview : Show what would be fixed without applying}';

    protected $description = 'Find Vue components with buttons/links that have no actions and suggest route connections';

    protected Collection $routes;
    protected array $findings = [];
    protected array $suggestions = [];

    public function handle(): int
    {
        $this->displayHeader();
        
        // Build route index
        $this->info('📋 Building route index...');
        $this->buildRouteIndex();
        $this->info("✅ Found {$this->routes->count()} routes");

        // Scan Vue components
        $this->info('🔍 Scanning Vue components...');
        $this->scanVueComponents();
        $this->info("✅ Found " . count($this->findings) . " components with actionless buttons/links");

        // Generate suggestions
        $this->info('💡 Generating route suggestions...');
        $this->generateSuggestions();
        $this->info("✅ Generated " . count($this->suggestions) . " suggestions");

        // Display results
        $this->displayResults();

        // Apply fixes if requested
        if ($this->option('fix') && !empty($this->suggestions)) {
            if ($this->confirm('Apply suggested fixes?')) {
                return $this->applyFixes();
            }
        } elseif ($this->option('preview')) {
            $this->previewFixes();
        }

        return empty($this->findings) ? Command::SUCCESS : Command::FAILURE;
    }

    protected function displayHeader(): void
    {
        $this->info('
╔══════════════════════════════════════════════════════════════╗
║                    🎯 Vue Link Auditor                      ║
║                                                              ║
║  Find Vue buttons/links without actions                     ║
║  Connect them to your Laravel routes                        ║
╚══════════════════════════════════════════════════════════════╝
        ');
    }

    protected function buildRouteIndex(): void
    {
        $this->routes = collect(Route::getRoutes())->map(function ($route) {
            return [
                'name' => $route->getName(),
                'uri' => $route->uri(),
                'methods' => $route->methods(),
                'action' => $route->getActionName(),
            ];
        })->filter(function ($route) {
            // Only GET routes that have names
            return !empty($route['name']) && 
                   in_array('GET', $route['methods']) && 
                   !str_starts_with($route['uri'], 'api/') &&
                   !str_contains($route['uri'], '{') &&
                   !str_starts_with($route['name'], 'horizon.') &&
                   !str_starts_with($route['name'], 'telescope.');
        })->values();
    }

    protected function scanVueComponents(): void
    {
        $vueFiles = File::allFiles(resource_path('js'));
        
        foreach ($vueFiles as $file) {
            if ($file->getExtension() === 'vue') {
                $this->scanVueFile($file->getPathname());
            }
        }
    }

    protected function scanVueFile(string $filePath): void
    {
        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);
        
        foreach ($lines as $lineNumber => $line) {
            // Find buttons without @click or actions
            $this->findActionlessButtons($filePath, $lineNumber + 1, $line);
            
            // Find links without href or with placeholder href
            $this->findActionlessLinks($filePath, $lineNumber + 1, $line);
            
            // Find router-link without proper to attribute
            $this->findActionlessRouterLinks($filePath, $lineNumber + 1, $line);
        }
    }

    protected function findActionlessButtons(string $file, int $lineNumber, string $line): void
    {
        // Pattern for buttons without @click, @submit, or other actions
        if (preg_match('/<button(?![^>]*@(?:click|submit|keydown|keyup))[^>]*>([^<]*)<\/button>/', $line, $matches)) {
            $buttonText = trim(strip_tags($matches[1]));
            
            if (!empty($buttonText) && !$this->hasAction($line)) {
                $this->findings[] = [
                    'type' => 'actionless_button',
                    'file' => $file,
                    'line' => $lineNumber,
                    'content' => trim($line),
                    'button_text' => $buttonText,
                    'issue' => "Button '{$buttonText}' has no action (@click, @submit, etc.)",
                ];
            }
        }
    }

    protected function findActionlessLinks(string $file, int $lineNumber, string $line): void
    {
        // Find <a> tags without href or with placeholder href
        if (preg_match('/<a(?![^>]*href=)[^>]*>([^<]*)<\/a>|<a[^>]*href=["\'](?:#|javascript:void\(0\)|)["\'][^>]*>([^<]*)<\/a>/', $line, $matches)) {
            $linkText = trim(strip_tags(isset($matches[1]) ? $matches[1] : (isset($matches[2]) ? $matches[2] : '')));
            
            if (!empty($linkText)) {
                $this->findings[] = [
                    'type' => 'actionless_link',
                    'file' => $file,
                    'line' => $lineNumber,
                    'content' => trim($line),
                    'link_text' => $linkText,
                    'issue' => "Link '{$linkText}' has no href or placeholder href",
                ];
            }
        }
    }

    protected function findActionlessRouterLinks(string $file, int $lineNumber, string $line): void
    {
        // Find router-link without proper 'to' attribute
        if (preg_match('/<router-link(?![^>]*:to=)[^>]*>([^<]*)<\/router-link>/', $line, $matches)) {
            $linkText = trim(strip_tags($matches[1]));
            
            if (!empty($linkText)) {
                $this->findings[] = [
                    'type' => 'actionless_router_link',
                    'file' => $file,
                    'line' => $lineNumber,
                    'content' => trim($line),
                    'link_text' => $linkText,
                    'issue' => "Router-link '{$linkText}' has no :to attribute",
                ];
            }
        }
    }

    protected function hasAction(string $line): bool
    {
        return preg_match('/@(?:click|submit|keydown|keyup|focus|blur)=/', $line) ||
               str_contains($line, 'v-on:') ||
               str_contains($line, 'type="submit"');
    }

    protected function generateSuggestions(): void
    {
        foreach ($this->findings as $finding) {
            $suggestion = $this->suggestRoute($finding);
            if ($suggestion) {
                $this->suggestions[] = array_merge($finding, ['suggestion' => $suggestion]);
            }
        }
    }

    protected function suggestRoute(array $finding): ?array
    {
        $text = isset($finding['button_text']) ? $finding['button_text'] : (isset($finding['link_text']) ? $finding['link_text'] : '');
        
        if (empty($text)) {
            return null;
        }

        // Best matching route based on text
        $bestRoute = null;
        $bestScore = 0;

        foreach ($this->routes as $route) {
            $score = $this->calculateMatchScore($text, $route);
            
            if ($score > $bestScore && $score > 0.3) {
                $bestScore = $score;
                $bestRoute = $route;
            }
        }

        if ($bestRoute) {
            return [
                'route' => $bestRoute,
                'confidence' => $bestScore,
                'action_type' => $this->determineActionType($finding),
            ];
        }

        return null;
    }

    protected function calculateMatchScore(string $text, array $route): float
    {
        $textWords = $this->extractWords($text);
        $routeNameWords = $this->extractWords($route['name']);
        $routeUriWords = $this->extractWords($route['uri']);

        $nameScore = $this->calculateWordsMatch($textWords, $routeNameWords);
        $uriScore = $this->calculateWordsMatch($textWords, $routeUriWords);
        
        // Direct text matching
        $directScore = Fuzzy::jaroWinkler(strtolower($text), strtolower($route['name']));
        
        return max($nameScore * 0.4, $uriScore * 0.3, $directScore * 0.6);
    }

    protected function extractWords(string $text): array
    {
        $text = strtolower(preg_replace('/[^a-zA-Z0-9\s]/', ' ', $text));
        return array_filter(explode(' ', $text));
    }

    protected function calculateWordsMatch(array $words1, array $words2): float
    {
        if (empty($words1) || empty($words2)) {
            return 0;
        }

        $matches = 0;
        foreach ($words1 as $word1) {
            foreach ($words2 as $word2) {
                if (Fuzzy::jaroWinkler($word1, $word2) > 0.8) {
                    $matches++;
                    break;
                }
            }
        }

        return $matches / max(count($words1), count($words2));
    }

    protected function determineActionType(array $finding): string
    {
        switch ($finding['type']) {
            case 'actionless_button':
                return '@click';
            case 'actionless_link':
                return ':href';
            case 'actionless_router_link':
                return ':to';
            default:
                return '@click';
        }
    }

    protected function displayResults(): void
    {
        if (empty($this->findings)) {
            $this->info('🎉 No actionless buttons or links found! Your Vue components are well-connected.');
            return;
        }

        $this->newLine();
        $this->info('📊 Vue Link Audit Results');
        $this->line('═══════════════════════════════════════════════════════');

        $groupedFindings = collect($this->findings)->groupBy('type');

        foreach ($groupedFindings as $type => $findings) {
            $this->line("<fg=yellow>" . ucfirst(str_replace('_', ' ', $type)) . " ({$findings->count()})</>");
            
            foreach ($findings as $finding) {
                $this->line("  📁 " . $this->getRelativePath($finding['file']) . ":{$finding['line']}");
                $text = isset($finding['button_text']) ? $finding['button_text'] : (isset($finding['link_text']) ? $finding['link_text'] : '');
                $this->line("     💬 Text: \"{$text}\"");
                
                $suggestion = collect($this->suggestions)->firstWhere('file', $finding['file']);
                if ($suggestion && isset($suggestion['suggestion'])) {
                    $route = $suggestion['suggestion']['route'];
                    $confidence = round($suggestion['suggestion']['confidence'] * 100);
                    $this->line("     💡 Suggested: route('{$route['name']}') [{$confidence}% confidence]");
                } else {
                    $this->line("     ❌ No suitable route found");
                }
                $this->newLine();
            }
        }

        // Summary
        $withSuggestions = count($this->suggestions);
        $this->line("Summary: {$withSuggestions}/" . count($this->findings) . " have route suggestions");
    }

    protected function previewFixes(): void
    {
        $this->info('🔍 Preview of suggested fixes:');
        $this->line('═══════════════════════════════════════════════════════');

        foreach ($this->suggestions as $suggestion) {
            $route = $suggestion['suggestion']['route'];
            $actionType = $suggestion['suggestion']['action_type'];
            $confidence = round($suggestion['suggestion']['confidence'] * 100);
            
            $this->line("<fg=cyan>File:</> " . $this->getRelativePath($suggestion['file']) . ":{$suggestion['line']}");
            $this->line("<fg=yellow>Before:</> " . trim($suggestion['content']));
            
            $after = $this->generateFixedLine($suggestion);
            $this->line("<fg=green>After:</> " . $after);
            $this->line("<fg=blue>Route:</> {$route['name']} ({$route['uri']}) [{$confidence}% confidence]");
            $this->newLine();
        }
    }

    protected function applyFixes(): int
    {
        $applied = 0;
        $backupDir = storage_path('app/link-audit/vue-backups/' . date('Y-m-d_H-i-s'));
        
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        foreach ($this->suggestions as $suggestion) {
            if ($suggestion['suggestion']['confidence'] >= 0.5) {
                if ($this->applyFix($suggestion, $backupDir)) {
                    $applied++;
                }
            }
        }

        $this->info("✅ Applied {$applied} fixes with confidence >= 50%");
        $this->line("📁 Backups saved to: {$backupDir}");

        return Command::SUCCESS;
    }

    protected function applyFix(array $suggestion, string $backupDir): bool
    {
        $file = $suggestion['file'];
        $line = $suggestion['line'];
        $newContent = $this->generateFixedLine($suggestion);

        // Create backup
        $relativePath = str_replace(base_path(), '', $file);
        $backupFile = $backupDir . $relativePath;
        $backupFileDir = dirname($backupFile);
        
        if (!is_dir($backupFileDir)) {
            mkdir($backupFileDir, 0755, true);
        }
        copy($file, $backupFile);

        // Apply fix
        $lines = file($file);
        $lines[$line - 1] = $newContent . "\n";
        
        return file_put_contents($file, implode('', $lines)) !== false;
    }

    protected function generateFixedLine(array $suggestion): string
    {
        $route = $suggestion['suggestion']['route'];
        $actionType = $suggestion['suggestion']['action_type'];
        $content = $suggestion['content'];

        switch ($suggestion['type']) {
            case 'actionless_button':
                // Add @click with router push
                if (preg_match('/(<button[^>]*)(>)/', $content, $matches)) {
                    return $matches[1] . " @click=\"\$router.push({name: '{$route['name']}'})\"" . $matches[2] . substr($content, strlen($matches[0]));
                }
                break;
                
            case 'actionless_link':
                // Add href with route helper
                if (preg_match('/(<a[^>]*)(>)/', $content, $matches)) {
                    return $matches[1] . " :href=\"route('{$route['name']}')\"" . $matches[2] . substr($content, strlen($matches[0]));
                }
                break;
                
            case 'actionless_router_link':
                // Add :to attribute
                if (preg_match('/(<router-link[^>]*)(>)/', $content, $matches)) {
                    return $matches[1] . " :to=\"{name: '{$route['name']}'}\"" . $matches[2] . substr($content, strlen($matches[0]));
                }
                break;
        }

        return $content;
    }

    protected function getRelativePath(string $file): string
    {
        return str_replace(base_path() . '/', '', $file);
    }
}