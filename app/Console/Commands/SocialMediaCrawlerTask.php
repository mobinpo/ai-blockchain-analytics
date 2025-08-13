<?php

namespace App\Console\Commands;

use App\Services\SocialMediaCrawlerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SocialMediaCrawlerTask extends Command
{
    protected $signature = 'social-media:crawl 
                            {--platform= : Specific platform to crawl (twitter,reddit,telegram)}
                            {--rule= : Specific rule ID to execute}
                            {--dry-run : Run without saving data}';

    protected $description = 'Crawl social media platforms for blockchain-related content based on configured rules';

    protected SocialMediaCrawlerService $crawlerService;

    public function __construct(SocialMediaCrawlerService $crawlerService)
    {
        parent::__construct();
        $this->crawlerService = $crawlerService;
    }

    public function handle()
    {
        $this->info('🚀 Starting Social Media Crawler...');
        $startTime = now();

        try {
            $results = $this->crawlerService->crawlAll();
            
            $this->displayResults($results);
            
            $duration = now()->diffInSeconds($startTime);
            $this->info("✅ Crawling completed in {$duration} seconds");
            
            Log::info('Social media crawling completed', [
                'duration' => $duration,
                'results_summary' => $this->getSummary($results)
            ]);

        } catch (\Exception $e) {
            $this->error('❌ Crawler failed: ' . $e->getMessage());
            Log::error('Social media crawler error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }

    protected function displayResults(array $results): void
    {
        $this->newLine();
        $this->info('📊 Crawling Results:');
        
        foreach ($results as $platform => $platformResults) {
            $this->line("  <fg=cyan>{$platform}</>");
            
            foreach ($platformResults as $result) {
                if (isset($result['error'])) {
                    $this->line("    ❌ {$result['rule']}: {$result['error']}");
                } else {
                    $this->line("    ✅ {$result['rule']}: {$result['posts_found']} posts found");
                }
            }
        }
    }

    protected function getSummary(array $results): array
    {
        $summary = [
            'total_posts' => 0,
            'platforms' => [],
            'rules_executed' => 0,
            'errors' => 0
        ];

        foreach ($results as $platform => $platformResults) {
            $platformPosts = 0;
            foreach ($platformResults as $result) {
                $summary['rules_executed']++;
                if (isset($result['error'])) {
                    $summary['errors']++;
                } else {
                    $platformPosts += $result['posts_found'];
                }
            }
            $summary['platforms'][$platform] = $platformPosts;
            $summary['total_posts'] += $platformPosts;
        }

        return $summary;
    }
}
