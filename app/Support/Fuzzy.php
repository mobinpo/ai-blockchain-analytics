<?php

namespace App\Support;

class Fuzzy
{
    /**
     * Calculate Jaro-Winkler similarity between two strings
     */
    public static function jaroWinkler(string $str1, string $str2): float
    {
        $jaro = self::jaro($str1, $str2);
        
        if ($jaro < 0.7) {
            return $jaro;
        }
        
        // Calculate common prefix (up to 4 characters)
        $prefix = 0;
        $maxPrefix = min(4, min(strlen($str1), strlen($str2)));
        
        for ($i = 0; $i < $maxPrefix; $i++) {
            if ($str1[$i] === $str2[$i]) {
                $prefix++;
            } else {
                break;
            }
        }
        
        return $jaro + (0.1 * $prefix * (1 - $jaro));
    }
    
    /**
     * Calculate Jaro similarity between two strings
     */
    public static function jaro(string $str1, string $str2): float
    {
        $len1 = strlen($str1);
        $len2 = strlen($str2);
        
        if ($len1 === 0 && $len2 === 0) {
            return 1.0;
        }
        
        if ($len1 === 0 || $len2 === 0) {
            return 0.0;
        }
        
        $matchWindow = intval(max($len1, $len2) / 2) - 1;
        if ($matchWindow < 1) {
            $matchWindow = 0;
        }
        
        $str1Matches = array_fill(0, $len1, false);
        $str2Matches = array_fill(0, $len2, false);
        
        $matches = 0;
        $transpositions = 0;
        
        // Identify matches
        for ($i = 0; $i < $len1; $i++) {
            $start = max(0, $i - $matchWindow);
            $end = min($i + $matchWindow + 1, $len2);
            
            for ($j = $start; $j < $end; $j++) {
                if ($str2Matches[$j] || $str1[$i] !== $str2[$j]) {
                    continue;
                }
                
                $str1Matches[$i] = true;
                $str2Matches[$j] = true;
                $matches++;
                break;
            }
        }
        
        if ($matches === 0) {
            return 0.0;
        }
        
        // Count transpositions
        $k = 0;
        for ($i = 0; $i < $len1; $i++) {
            if (!$str1Matches[$i]) {
                continue;
            }
            
            while (!$str2Matches[$k]) {
                $k++;
            }
            
            if ($str1[$i] !== $str2[$k]) {
                $transpositions++;
            }
            
            $k++;
        }
        
        return ($matches / $len1 + $matches / $len2 + ($matches - $transpositions / 2) / $matches) / 3;
    }
    
    /**
     * Calculate Levenshtein distance between two strings
     */
    public static function levenshtein(string $str1, string $str2): int
    {
        $len1 = strlen($str1);
        $len2 = strlen($str2);
        
        if ($len1 === 0) {
            return $len2;
        }
        
        if ($len2 === 0) {
            return $len1;
        }
        
        $matrix = [];
        
        // Initialize first row and column
        for ($i = 0; $i <= $len1; $i++) {
            $matrix[$i][0] = $i;
        }
        
        for ($j = 0; $j <= $len2; $j++) {
            $matrix[0][$j] = $j;
        }
        
        // Fill the matrix
        for ($i = 1; $i <= $len1; $i++) {
            for ($j = 1; $j <= $len2; $j++) {
                $cost = ($str1[$i - 1] === $str2[$j - 1]) ? 0 : 1;
                
                $matrix[$i][$j] = min(
                    $matrix[$i - 1][$j] + 1,     // deletion
                    $matrix[$i][$j - 1] + 1,     // insertion
                    $matrix[$i - 1][$j - 1] + $cost // substitution
                );
            }
        }
        
        return $matrix[$len1][$len2];
    }
    
    /**
     * Calculate Levenshtein similarity (normalized between 0 and 1)
     */
    public static function levenshteinSimilarity(string $str1, string $str2): float
    {
        $maxLen = max(strlen($str1), strlen($str2));
        
        if ($maxLen === 0) {
            return 1.0;
        }
        
        $distance = self::levenshtein($str1, $str2);
        
        return 1.0 - ($distance / $maxLen);
    }
    
    /**
     * Calculate combined similarity score for route matching
     */
    public static function routeSimilarity(
        string $targetName,
        string $targetUri,
        string $candidateName,
        string $candidateUri,
        string $linkText = '',
        array $weights = []
    ): float {
        $weights = array_merge([
            'name_similarity' => 0.6,
            'uri_similarity' => 0.3,
            'link_text_similarity' => 0.1,
        ], $weights);
        
        $nameSimilarity = self::jaroWinkler(
            strtolower($targetName),
            strtolower($candidateName)
        );
        
        $uriSimilarity = self::jaroWinkler(
            strtolower(trim($targetUri, '/')),
            strtolower(trim($candidateUri, '/'))
        );
        
        $linkTextSimilarity = 0.0;
        if (!empty($linkText) && !empty($candidateName)) {
            $linkTextSimilarity = self::jaroWinkler(
                strtolower($linkText),
                strtolower(str_replace(['.', '_', '-'], ' ', $candidateName))
            );
        }
        
        return ($weights['name_similarity'] * $nameSimilarity) +
               ($weights['uri_similarity'] * $uriSimilarity) +
               ($weights['link_text_similarity'] * $linkTextSimilarity);
    }
    
    /**
     * Find the best matching route from a list of candidates
     */
    public static function findBestRoute(
        string $targetPath,
        array $routes,
        string $linkText = '',
        float $minimumScore = 0.5
    ): ?array {
        $bestScore = 0.0;
        $bestRoute = null;
        
        $targetName = self::pathToName($targetPath);
        
        foreach ($routes as $route) {
            $score = self::routeSimilarity(
                $targetName,
                $targetPath,
                $route['name'] ?? '',
                $route['uri'] ?? '',
                $linkText
            );
            
            if ($score > $bestScore && $score >= $minimumScore) {
                $bestScore = $score;
                $bestRoute = array_merge($route, ['similarity_score' => $score]);
            }
        }
        
        return $bestRoute;
    }
    
    /**
     * Convert a path to a probable route name
     */
    private static function pathToName(string $path): string
    {
        $path = trim($path, '/');
        $segments = explode('/', $path);
        
        // Convert common patterns
        $name = implode('.', array_map(function ($segment) {
            return str_replace(['-', '_'], '.', strtolower($segment));
        }, $segments));
        
        // Handle common suffixes
        if (empty($name)) {
            $name = 'home';
        }
        
        return $name;
    }
    
    /**
     * Extract meaningful words from a string for comparison
     */
    public static function extractWords(string $text): array
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
        $words = array_filter(explode(' ', $text));
        
        // Remove common stop words
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'];
        
        return array_diff($words, $stopWords);
    }
}