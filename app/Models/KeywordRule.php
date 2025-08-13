<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;

class KeywordRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'keywords',
        'exclude_keywords',
        'match_type',
        'case_sensitive',
        'priority',
        'platforms',
        'active',
        'metadata',
    ];

    protected $casts = [
        'keywords' => 'array',
        'exclude_keywords' => 'array',
        'platforms' => 'array',
        'case_sensitive' => 'boolean',
        'active' => 'boolean',
        'metadata' => 'array',
        'priority' => 'integer',
    ];

    public function keywordMatches(): HasMany
    {
        return $this->hasMany(KeywordMatch::class);
    }

    public function matchedPosts(): BelongsToMany
    {
        return $this->belongsToMany(SocialPost::class, 'keyword_matches')
            ->withPivot(['matched_keyword', 'match_count', 'confidence_score'])
            ->withTimestamps();
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopePlatform(Builder $query, string $platform): Builder
    {
        return $query->whereJsonContains('platforms', $platform);
    }

    public function scopePriority(Builder $query, int $minPriority = 1): Builder
    {
        return $query->where('priority', '>=', $minPriority);
    }

    public function scopeByMatchType(Builder $query, string $matchType): Builder
    {
        return $query->where('match_type', $matchType);
    }

    // Match checking methods
    public function matches(string $content, string $platform = null): array
    {
        if (!$this->active) {
            return [];
        }

        if ($platform && !$this->appliesToPlatform($platform)) {
            return [];
        }

        $searchContent = $this->case_sensitive ? $content : strtolower($content);
        $matches = [];

        // Check exclusions first
        if ($this->hasExclusions() && $this->matchesExclusions($searchContent)) {
            return [];
        }

        // Apply matching logic based on match type
        switch ($this->match_type) {
            case 'any':
                $matches = $this->matchAny($searchContent);
                break;
            case 'all':
                $matches = $this->matchAll($searchContent);
                break;
            case 'exact':
                $matches = $this->matchExact($searchContent);
                break;
            case 'regex':
                $matches = $this->matchRegex($searchContent);
                break;
        }

        return $matches;
    }

    protected function matchAny(string $content): array
    {
        $matches = [];
        
        foreach ($this->getProcessedKeywords() as $keyword) {
            $positions = $this->findKeywordPositions($content, $keyword);
            if (!empty($positions)) {
                $matches[] = [
                    'keyword' => $keyword,
                    'positions' => $positions,
                    'count' => count($positions),
                    'confidence' => $this->calculateConfidence($keyword, $content)
                ];
            }
        }
        
        return $matches;
    }

    protected function matchAll(string $content): array
    {
        $allMatches = [];
        $keywords = $this->getProcessedKeywords();
        
        foreach ($keywords as $keyword) {
            $positions = $this->findKeywordPositions($content, $keyword);
            if (empty($positions)) {
                return []; // All keywords must match
            }
            
            $allMatches[] = [
                'keyword' => $keyword,
                'positions' => $positions,
                'count' => count($positions),
                'confidence' => $this->calculateConfidence($keyword, $content)
            ];
        }
        
        return $allMatches;
    }

    protected function matchExact(string $content): array
    {
        $matches = [];
        
        foreach ($this->getProcessedKeywords() as $phrase) {
            if (str_contains($content, $phrase)) {
                $positions = $this->findKeywordPositions($content, $phrase);
                $matches[] = [
                    'keyword' => $phrase,
                    'positions' => $positions,
                    'count' => count($positions),
                    'confidence' => 1.0 // Exact matches have full confidence
                ];
            }
        }
        
        return $matches;
    }

    protected function matchRegex(string $content): array
    {
        $matches = [];
        
        foreach ($this->getProcessedKeywords() as $pattern) {
            try {
                if (preg_match_all("/$pattern/", $content, $regexMatches, PREG_OFFSET_CAPTURE)) {
                    $positions = array_map(fn($match) => $match[1], $regexMatches[0]);
                    $matches[] = [
                        'keyword' => $pattern,
                        'positions' => $positions,
                        'count' => count($positions),
                        'confidence' => 0.9 // High confidence for regex matches
                    ];
                }
            } catch (\Exception $e) {
                // Invalid regex pattern, skip
                continue;
            }
        }
        
        return $matches;
    }

    protected function matchesExclusions(string $content): bool
    {
        if (!$this->exclude_keywords) {
            return false;
        }

        foreach ($this->getProcessedExcludeKeywords() as $excludeKeyword) {
            if (str_contains($content, $excludeKeyword)) {
                return true;
            }
        }
        
        return false;
    }

    protected function findKeywordPositions(string $content, string $keyword): array
    {
        $positions = [];
        $offset = 0;
        
        while (($pos = strpos($content, $keyword, $offset)) !== false) {
            $positions[] = $pos;
            $offset = $pos + 1;
        }
        
        return $positions;
    }

    protected function calculateConfidence(string $keyword, string $content): float
    {
        // Base confidence
        $confidence = 0.7;
        
        // Boost for longer keywords (more specific)
        $confidence += min(0.2, strlen($keyword) / 100);
        
        // Boost for word boundaries (not part of another word)
        if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/', $content)) {
            $confidence += 0.2;
        }
        
        // Boost for keyword frequency
        $frequency = substr_count($content, $keyword);
        $confidence += min(0.1, $frequency * 0.02);
        
        return min(1.0, $confidence);
    }

    protected function getProcessedKeywords(): array
    {
        $keywords = $this->keywords ?? [];
        
        if (!$this->case_sensitive) {
            $keywords = array_map('strtolower', $keywords);
        }
        
        return array_filter($keywords);
    }

    protected function getProcessedExcludeKeywords(): array
    {
        $keywords = $this->exclude_keywords ?? [];
        
        if (!$this->case_sensitive) {
            $keywords = array_map('strtolower', $keywords);
        }
        
        return array_filter($keywords);
    }

    public function appliesToPlatform(string $platform): bool
    {
        return in_array($platform, $this->platforms ?? []);
    }

    public function hasExclusions(): bool
    {
        return !empty($this->exclude_keywords);
    }

    // Analytics methods
    public function getMatchCount(int $days = 30): int
    {
        return $this->keywordMatches()
            ->whereHas('socialPost', function (Builder $query) use ($days) {
                $query->where('published_at', '>=', now()->subDays($days));
            })
            ->count();
    }

    public function getTopMatchedKeywords(int $limit = 10): array
    {
        return $this->keywordMatches()
            ->selectRaw('matched_keyword, COUNT(*) as count')
            ->groupBy('matched_keyword')
            ->orderByDesc('count')
            ->limit($limit)
            ->pluck('count', 'matched_keyword')
            ->toArray();
    }

    public function getMatchedPostsByPlatform(int $days = 30): array
    {
        return $this->matchedPosts()
            ->selectRaw('platform, COUNT(*) as count')
            ->where('published_at', '>=', now()->subDays($days))
            ->groupBy('platform')
            ->pluck('count', 'platform')
            ->toArray();
    }

    public function getAverageConfidence(): float
    {
        return $this->keywordMatches()
            ->avg('confidence_score') ?? 0.0;
    }

    // Utility methods
    public function activate(): bool
    {
        return $this->update(['active' => true]);
    }

    public function deactivate(): bool
    {
        return $this->update(['active' => false]);
    }

    public function addKeyword(string $keyword): bool
    {
        $keywords = $this->keywords ?? [];
        
        if (!in_array($keyword, $keywords)) {
            $keywords[] = $keyword;
            return $this->update(['keywords' => $keywords]);
        }
        
        return false;
    }

    public function removeKeyword(string $keyword): bool
    {
        $keywords = $this->keywords ?? [];
        $filtered = array_filter($keywords, fn($k) => $k !== $keyword);
        
        if (count($filtered) !== count($keywords)) {
            return $this->update(['keywords' => array_values($filtered)]);
        }
        
        return false;
    }

    public function addExcludeKeyword(string $keyword): bool
    {
        $excludeKeywords = $this->exclude_keywords ?? [];
        
        if (!in_array($keyword, $excludeKeywords)) {
            $excludeKeywords[] = $keyword;
            return $this->update(['exclude_keywords' => $excludeKeywords]);
        }
        
        return false;
    }

    public function addPlatform(string $platform): bool
    {
        $platforms = $this->platforms ?? [];
        
        if (!in_array($platform, $platforms)) {
            $platforms[] = $platform;
            return $this->update(['platforms' => $platforms]);
        }
        
        return false;
    }

    public function removePlatform(string $platform): bool
    {
        $platforms = $this->platforms ?? [];
        $filtered = array_filter($platforms, fn($p) => $p !== $platform);
        
        if (count($filtered) !== count($platforms)) {
            return $this->update(['platforms' => array_values($filtered)]);
        }
        
        return false;
    }

    public function updatePriority(int $priority): bool
    {
        return $this->update(['priority' => max(1, min(10, $priority))]);
    }

    // Validation methods
    public function validateKeywords(): array
    {
        $errors = [];
        
        if (empty($this->keywords)) {
            $errors[] = 'Keywords array cannot be empty';
        }
        
        if ($this->match_type === 'regex') {
            foreach ($this->keywords as $pattern) {
                if (@preg_match("/$pattern/", '') === false) {
                    $errors[] = "Invalid regex pattern: $pattern";
                }
            }
        }
        
        return $errors;
    }

    public function isValid(): bool
    {
        return empty($this->validateKeywords());
    }

    // Export/Import methods
    public function exportRule(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'keywords' => $this->keywords,
            'exclude_keywords' => $this->exclude_keywords,
            'match_type' => $this->match_type,
            'case_sensitive' => $this->case_sensitive,
            'priority' => $this->priority,
            'platforms' => $this->platforms,
            'metadata' => $this->metadata,
        ];
    }

    public static function importRule(array $ruleData): self
    {
        return self::create($ruleData);
    }

    // Testing methods
    public function testAgainstContent(string $content, string $platform = null): array
    {
        $matches = $this->matches($content, $platform);
        
        return [
            'rule_name' => $this->name,
            'rule_id' => $this->id,
            'matches_found' => count($matches),
            'matches' => $matches,
            'applies_to_platform' => $platform ? $this->appliesToPlatform($platform) : true,
            'excluded' => $this->hasExclusions() ? $this->matchesExclusions(
                $this->case_sensitive ? $content : strtolower($content)
            ) : false
        ];
    }
}