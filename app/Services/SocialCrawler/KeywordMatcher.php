<?php

namespace App\Services\SocialCrawler;

class KeywordMatcher
{
    protected array $keywordRules;

    public function __construct()
    {
        $this->keywordRules = config('social_crawler.keywords', []);
    }

    public function matchKeywords(string $content): array
    {
        $matches = [];
        $contentLower = strtolower($content);

        foreach ($this->keywordRules as $category => $rules) {
            foreach ($rules['terms'] as $term) {
                $termLower = strtolower($term);
                $matchCount = substr_count($contentLower, $termLower);
                
                if ($matchCount > 0) {
                    $matches[] = [
                        'keyword' => $term,
                        'category' => $category,
                        'match_count' => $matchCount,
                        'priority' => $rules['priority'] ?? 'medium',
                        'sentiment_analysis' => $rules['sentiment_analysis'] ?? false,
                    ];
                }
            }
        }

        return $matches;
    }

    public function shouldTriggerAlert(array $matches, string $platform): bool
    {
        $criticalMatches = array_filter($matches, fn($match) => $match['priority'] === 'critical');
        
        if (empty($criticalMatches)) {
            return false;
        }

        // Check if we've exceeded alert thresholds
        $alertConfig = config('social_crawler.alerts', []);
        
        if (!$alertConfig['enabled']) {
            return false;
        }

        foreach ($criticalMatches as $match) {
            if ($match['category'] === 'security') {
                $threshold = $alertConfig['thresholds']['security_mentions'] ?? 5;
                $recentCount = $this->getRecentMatchCount($match['keyword'], $platform);
                
                if ($recentCount >= $threshold) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function getRecentMatchCount(string $keyword, string $platform): int
    {
        return \App\Models\KeywordMatch::query()
            ->where('keyword', $keyword)
            ->whereHas('socialMediaPost', fn($query) => $query->where('platform', $platform))
            ->where('created_at', '>=', now()->subHour())
            ->sum('match_count');
    }

    public function getHighPriorityKeywords(): array
    {
        $highPriority = [];
        
        foreach ($this->keywordRules as $category => $rules) {
            if (in_array($rules['priority'], ['high', 'critical'])) {
                $highPriority = array_merge($highPriority, $rules['terms']);
            }
        }

        return $highPriority;
    }

    public function getCriticalKeywords(): array
    {
        $critical = [];
        
        foreach ($this->keywordRules as $category => $rules) {
            if ($rules['priority'] === 'critical') {
                $critical = array_merge($critical, $rules['terms']);
            }
        }

        return $critical;
    }
}