<?php

declare(strict_types=1);

namespace App\Services\CrawlerMicroService\Engine;

use App\Models\CrawlerKeywordRule;
use App\Models\SocialMediaPost;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

final class AdvancedKeywordEngine
{
    private array $compiledRules = [];
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'cache_duration' => 3600,
            'case_sensitive' => false,
            'use_regex' => true,
            'sentiment_weight' => 0.3,
            'engagement_weight' => 0.4,
            'keyword_density_weight' => 0.3,
        ], $config);

        $this->loadKeywordRules();
    }

    /**
     * Load and compile keyword rules from database
     */
    private function loadKeywordRules(): void
    {
        $cacheKey = 'crawler_keyword_rules_compiled';
        
        $this->compiledRules = Cache::remember($cacheKey, $this->config['cache_duration'], function () {
            $rules = CrawlerKeywordRule::where('is_active', true)
                ->orderByDesc('priority')
                ->get();

            $compiled = [];
            foreach ($rules as $rule) {
                $compiled[] = $this->compileRule($rule);
            }

            return $compiled;
        });
    }

    /**
     * Compile a keyword rule for efficient matching
     */
    private function compileRule(CrawlerKeywordRule $rule): array
    {
        $keywords = is_string($rule->keywords) ? json_decode($rule->keywords, true) : $rule->keywords;
        
        return [
            'id' => $rule->id,
            'name' => $rule->name,
            'keywords' => $keywords,
            'platforms' => $rule->platforms ?? ['twitter', 'reddit', 'telegram'],
            'priority' => $rule->priority,
            'category' => $rule->category,
            'match_type' => $rule->match_type ?? 'any', // any, all, exact, regex
            'case_sensitive' => $rule->case_sensitive ?? false,
            'context_radius' => $rule->context_radius ?? 50, // characters around match
            'min_engagement' => $rule->min_engagement ?? 0,
            'sentiment_filter' => $rule->sentiment_filter, // positive, negative, neutral, any
            'date_range' => $rule->date_range,
            'exclusions' => $rule->exclusions ?? [],
            'triggers' => $rule->triggers ?? [],
            'compiled_regex' => $this->compileRegex($keywords, $rule),
        ];
    }

    /**
     * Compile keywords into optimized regex patterns
     */
    private function compileRegex(array $keywords, CrawlerKeywordRule $rule): ?string
    {
        if (!$this->config['use_regex'] || empty($keywords)) {
            return null;
        }

        $patterns = [];
        foreach ($keywords as $keyword) {
            if (is_array($keyword)) {
                // Complex keyword with modifiers
                $pattern = $this->buildComplexPattern($keyword);
            } else {
                // Simple string keyword
                $pattern = $this->escapeKeyword($keyword);
            }
            
            if ($pattern) {
                $patterns[] = $pattern;
            }
        }

        if (empty($patterns)) {
            return null;
        }

        $flags = 'u'; // Unicode support
        if (!($rule->case_sensitive ?? false)) {
            $flags .= 'i'; // Case insensitive
        }

        $combinedPattern = match($rule->match_type ?? 'any') {
            'all' => '(?=.*' . implode(')(?=.*', $patterns) . ')',
            'exact' => '^(' . implode('|', $patterns) . ')$',
            'regex' => implode('|', $patterns),
            default => '(' . implode('|', $patterns) . ')'
        };

        return '/' . $combinedPattern . '/' . $flags;
    }

    /**
     * Build complex pattern from keyword configuration
     */
    private function buildComplexPattern(array $keywordConfig): string
    {
        $keyword = $keywordConfig['term'] ?? '';
        $modifiers = $keywordConfig['modifiers'] ?? [];

        $pattern = $this->escapeKeyword($keyword);

        // Apply modifiers
        if (in_array('word_boundary', $modifiers)) {
            $pattern = '\b' . $pattern . '\b';
        }

        if (in_array('starts_with', $modifiers)) {
            $pattern = '^' . $pattern;
        }

        if (in_array('ends_with', $modifiers)) {
            $pattern = $pattern . '$';
        }

        if (isset($modifiers['proximity'])) {
            // Proximity matching: words within N words of each other
            $proximity = $modifiers['proximity'];
            $words = explode(' ', $keyword);
            if (count($words) > 1) {
                $proximityPattern = implode('(?:\W+\w+){0,' . $proximity . '}\W+', array_map([$this, 'escapeKeyword'], $words));
                $pattern = $proximityPattern;
            }
        }

        return $pattern;
    }

    /**
     * Escape keyword for regex use
     */
    private function escapeKeyword(string $keyword): string
    {
        return preg_quote($keyword, '/');
    }

    /**
     * Match content against all active keyword rules
     */
    public function matchContent(string $content, string $platform = null, array $metadata = []): array
    {
        if (empty($this->compiledRules)) {
            $this->loadKeywordRules();
        }

        $matches = [];
        $contentLower = $this->config['case_sensitive'] ? $content : mb_strtolower($content);

        foreach ($this->compiledRules as $rule) {
            // Platform filter
            if ($platform && !in_array($platform, $rule['platforms'])) {
                continue;
            }

            // Date range filter
            if ($rule['date_range'] && !$this->isWithinDateRange($rule['date_range'])) {
                continue;
            }

            // Check exclusions
            if ($this->hasExclusions($content, $rule['exclusions'])) {
                continue;
            }

            $ruleMatches = $this->matchRule($content, $contentLower, $rule, $metadata);
            if (!empty($ruleMatches)) {
                $matches = array_merge($matches, $ruleMatches);
            }
        }

        // Sort by priority and score
        usort($matches, function ($a, $b) {
            if ($a['priority'] === $b['priority']) {
                return $b['score'] <=> $a['score'];
            }
            return $b['priority'] <=> $a['priority'];
        });

        return $matches;
    }

    /**
     * Match content against a specific rule
     */
    private function matchRule(string $content, string $contentLower, array $rule, array $metadata): array
    {
        $matches = [];
        $searchContent = $rule['case_sensitive'] ? $content : $contentLower;

        if ($rule['compiled_regex']) {
            // Regex matching
            if (preg_match_all($rule['compiled_regex'], $searchContent, $regexMatches, PREG_OFFSET_CAPTURE)) {
                foreach ($regexMatches[0] as $match) {
                    $matches[] = $this->createMatch($rule, $match[0], $match[1], $content, $metadata);
                }
            }
        } else {
            // Simple string matching
            foreach ($rule['keywords'] as $keyword) {
                $keywordText = is_array($keyword) ? $keyword['term'] : $keyword;
                $searchKeyword = $rule['case_sensitive'] ? $keywordText : mb_strtolower($keywordText);
                
                $pos = 0;
                while (($pos = mb_strpos($searchContent, $searchKeyword, $pos)) !== false) {
                    $matches[] = $this->createMatch($rule, $keywordText, $pos, $content, $metadata);
                    $pos += mb_strlen($searchKeyword);
                }
            }
        }

        return $matches;
    }

    /**
     * Create a match result
     */
    private function createMatch(array $rule, string $matchedText, int $position, string $fullContent, array $metadata): array
    {
        $context = $this->extractContext($fullContent, $position, $rule['context_radius']);
        $density = $this->calculateKeywordDensity($fullContent, $matchedText);
        $score = $this->calculateMatchScore($rule, $matchedText, $density, $metadata);

        return [
            'rule_id' => $rule['id'],
            'rule_name' => $rule['name'],
            'keyword' => $matchedText,
            'category' => $rule['category'],
            'priority' => $rule['priority'],
            'position' => $position,
            'context' => $context,
            'density' => $density,
            'score' => $score,
            'triggers' => $rule['triggers'],
            'metadata' => [
                'match_type' => $rule['match_type'],
                'case_sensitive' => $rule['case_sensitive'],
                'full_rule' => $rule,
            ],
        ];
    }

    /**
     * Extract context around a match
     */
    private function extractContext(string $content, int $position, int $radius): string
    {
        $start = max(0, $position - $radius);
        $length = $radius * 2;
        
        $context = mb_substr($content, $start, $length);
        
        // Clean up context
        $context = trim($context);
        if ($start > 0) {
            $context = '...' . $context;
        }
        if ($start + $length < mb_strlen($content)) {
            $context = $context . '...';
        }

        return $context;
    }

    /**
     * Calculate keyword density in content
     */
    private function calculateKeywordDensity(string $content, string $keyword): float
    {
        $wordCount = str_word_count($content);
        if ($wordCount === 0) {
            return 0.0;
        }

        $keywordCount = substr_count(mb_strtolower($content), mb_strtolower($keyword));
        return ($keywordCount / $wordCount) * 100;
    }

    /**
     * Calculate match score based on various factors
     */
    private function calculateMatchScore(array $rule, string $matchedText, float $density, array $metadata): float
    {
        $score = $rule['priority'] / 10; // Base score from priority

        // Keyword density factor
        $densityScore = min($density * 10, 5); // Max 5 points for density
        $score += $densityScore * $this->config['keyword_density_weight'];

        // Engagement factor
        $engagement = $metadata['engagement_score'] ?? 0;
        $engagementScore = min(log($engagement + 1) * 2, 10); // Logarithmic scale, max 10
        $score += $engagementScore * $this->config['engagement_weight'];

        // Sentiment factor
        $sentiment = $metadata['sentiment_score'] ?? 0;
        $sentimentScore = abs($sentiment) * 5; // Stronger sentiment = higher score
        $score += $sentimentScore * $this->config['sentiment_weight'];

        // Length factor (longer keywords are more specific)
        $lengthScore = min(mb_strlen($matchedText) / 10, 2); // Max 2 points
        $score += $lengthScore;

        return round($score, 2);
    }

    /**
     * Check if content has exclusion terms
     */
    private function hasExclusions(string $content, array $exclusions): bool
    {
        if (empty($exclusions)) {
            return false;
        }

        $searchContent = $this->config['case_sensitive'] ? $content : mb_strtolower($content);
        
        foreach ($exclusions as $exclusion) {
            $searchExclusion = $this->config['case_sensitive'] ? $exclusion : mb_strtolower($exclusion);
            if (mb_strpos($searchContent, $searchExclusion) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if current time is within rule's date range
     */
    private function isWithinDateRange(?array $dateRange): bool
    {
        if (!$dateRange) {
            return true;
        }

        $now = now();
        $start = isset($dateRange['start']) ? \Carbon\Carbon::parse($dateRange['start']) : null;
        $end = isset($dateRange['end']) ? \Carbon\Carbon::parse($dateRange['end']) : null;

        if ($start && $now->lt($start)) {
            return false;
        }

        if ($end && $now->gt($end)) {
            return false;
        }

        return true;
    }

    /**
     * Get high priority keywords for focused crawling
     */
    public function getHighPriorityKeywords(string $platform = null, int $limit = 50): array
    {
        $keywords = [];
        
        foreach ($this->compiledRules as $rule) {
            if ($platform && !in_array($platform, $rule['platforms'])) {
                continue;
            }

            if ($rule['priority'] >= 8) { // High priority threshold
                foreach ($rule['keywords'] as $keyword) {
                    $keywordText = is_array($keyword) ? $keyword['term'] : $keyword;
                    $keywords[] = [
                        'keyword' => $keywordText,
                        'priority' => $rule['priority'],
                        'category' => $rule['category'],
                        'rule_id' => $rule['id'],
                    ];
                }
            }
        }

        // Sort by priority and limit
        usort($keywords, fn($a, $b) => $b['priority'] <=> $a['priority']);
        
        return array_slice($keywords, 0, $limit);
    }

    /**
     * Check if matches should trigger alerts
     */
    public function shouldTriggerAlert(array $matches, string $platform = null): bool
    {
        foreach ($matches as $match) {
            $triggers = $match['triggers'] ?? [];
            
            if (empty($triggers)) {
                continue;
            }

            // Check trigger conditions
            foreach ($triggers as $trigger) {
                if ($this->evaluateTrigger($trigger, $match, $platform)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Evaluate a trigger condition
     */
    private function evaluateTrigger(array $trigger, array $match, ?string $platform): bool
    {
        $type = $trigger['type'] ?? 'score_threshold';

        return match($type) {
            'score_threshold' => $match['score'] >= ($trigger['threshold'] ?? 5),
            'priority_threshold' => $match['priority'] >= ($trigger['threshold'] ?? 8),
            'platform_specific' => $platform === ($trigger['platform'] ?? null),
            'density_threshold' => $match['density'] >= ($trigger['threshold'] ?? 1.0),
            'immediate' => true,
            default => false
        };
    }

    /**
     * Get statistics about keyword matching
     */
    public function getMatchingStats(array $matches): array
    {
        if (empty($matches)) {
            return [
                'total_matches' => 0,
                'unique_rules' => 0,
                'avg_score' => 0,
                'top_categories' => [],
                'priority_distribution' => [],
            ];
        }

        $ruleIds = array_unique(array_column($matches, 'rule_id'));
        $scores = array_column($matches, 'score');
        $categories = array_count_values(array_column($matches, 'category'));
        $priorities = array_count_values(array_column($matches, 'priority'));

        arsort($categories);
        arsort($priorities);

        return [
            'total_matches' => count($matches),
            'unique_rules' => count($ruleIds),
            'avg_score' => round(array_sum($scores) / count($scores), 2),
            'max_score' => max($scores),
            'min_score' => min($scores),
            'top_categories' => array_slice($categories, 0, 5, true),
            'priority_distribution' => $priorities,
        ];
    }

    /**
     * Refresh keyword rules cache
     */
    public function refreshRules(): void
    {
        Cache::forget('crawler_keyword_rules_compiled');
        $this->loadKeywordRules();
        
        Log::info('Crawler keyword rules refreshed', [
            'total_rules' => count($this->compiledRules)
        ]);
    }
}
