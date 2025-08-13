<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SocialCrawler\SocialCrawlerService;
use App\Models\KeywordRule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SocialCrawlerCommand extends Command
{
    protected $signature = 'social:crawl 
                          {--platforms=* : Platforms to crawl (twitter,reddit,telegram)}
                          {--keywords=* : Keywords to search for}
                          {--max-results=100 : Maximum results per platform}
                          {--hours-back=1 : Hours to look back}
                          {--dry-run : Run without storing results}';

    protected $description = 'Crawl social media platforms for blockchain-related content';

    public function __construct(private SocialCrawlerService $crawlerService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('🚀 Starting Social Media Crawler...');
        
        $platforms = $this->option('platforms') ?: ['twitter', 'reddit', 'telegram'];
        $keywords = $this->option('keywords') ?: $this->getDefaultKeywords();
        $maxResults = (int) $this->option('max-results');
        $hoursBack = (int) $this->option('hours-back');
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('🧪 Running in DRY RUN mode - no data will be stored');
        }

        $this->info("📊 Configuration:");
        $this->line("  Platforms: " . implode(', ', $platforms));
        $this->line("  Keywords: " . implode(', ', array_slice($keywords, 0, 5)) . 
                   (count($keywords) > 5 ? '...' : ''));
        $this->line("  Max Results: {$maxResults}");
        $this->line("  Hours Back: {$hoursBack}");

        $startTime = microtime(true);
        
        try {
            $results = $this->crawlerService->runScheduledCrawl();
            
            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2);

            $this->displayResults($results, $executionTime);
            
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Crawler failed: {$e->getMessage()}");
            Log::error('Social crawler command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return self::FAILURE;
        }
    }

    private function getDefaultKeywords(): array
    {
        $activeRules = KeywordRule::active()->get();
        
        if ($activeRules->isEmpty()) {
            return config('social_crawler.default_keywords', [
                'blockchain', 'cryptocurrency', 'bitcoin', 'ethereum', 'defi'
            ]);
        }

        return $activeRules->pluck('keywords')
            ->flatten()
            ->unique()
            ->values()
            ->toArray();
    }

    private function displayResults(array $results, float $executionTime): void
    {
        $this->newLine();
        $this->info("✅ Crawl completed in {$executionTime}ms");
        $this->newLine();

        $totalPosts = 0;
        $totalErrors = 0;

        foreach ($results as $platform => $result) {
            if (isset($result['success']) && $result['success']) {
                $posts = $result['posts_processed'] ?? 0;
                $matches = $result['keyword_matches'] ?? 0;
                $totalPosts += $posts;

                $this->line("📱 <info>{$platform}</info>:");
                $this->line("  Posts found: {$result['posts_found']}");
                $this->line("  Posts processed: {$posts}");
                $this->line("  Keyword matches: {$matches}");
                
                if (isset($result['job_id'])) {
                    $this->line("  Job ID: {$result['job_id']}");
                }
            } else {
                $totalErrors++;
                $error = $result['error'] ?? 'Unknown error';
                $this->line("❌ <error>{$platform}</error>: {$error}");
            }
            $this->newLine();
        }

        $this->line("<info>Summary:</info>");
        $this->line("  Total posts processed: {$totalPosts}");
        if ($totalErrors > 0) {
            $this->line("  <error>Platforms with errors: {$totalErrors}</error>");
        }

        if ($totalPosts > 0) {
            $this->info("🎉 Successfully crawled {$totalPosts} posts!");
        } else {
            $this->warn("⚠️  No posts were processed. Check your configuration and API credentials.");
        }
    }
}