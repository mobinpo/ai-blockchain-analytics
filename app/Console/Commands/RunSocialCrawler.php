<?php

namespace App\Console\Commands;

use App\Jobs\SocialCrawlerJob;
use App\Models\CrawlerJobStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunSocialCrawler extends Command
{
    protected $signature = 'social:crawl
                          {--platform= : Specific platform to crawl (twitter, reddit, telegram)}
                          {--type= : Type of crawl (keywords, hashtags, users, subreddits, channels)}
                          {--keywords= : Comma-separated keywords to search}
                          {--force : Force run even if not due}
                          {--dry-run : Show what would be crawled without actually running}';

    protected $description = 'Run social media crawler for specified platforms';

    public function handle(): int
    {
        if (!config('social_crawler.enabled', true)) {
            $this->info('Social crawler is disabled');
            return 0;
        }

        $platform = $this->option('platform');
        $type = $this->option('type');
        $keywords = $this->option('keywords') ? explode(',', $this->option('keywords')) : null;
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        if ($platform && $type) {
            return $this->runSpecificCrawl($platform, $type, $keywords, $force, $dryRun);
        }

        return $this->runScheduledCrawls($force, $dryRun);
    }

    protected function runSpecificCrawl(string $platform, string $type, ?array $keywords, bool $force, bool $dryRun): int
    {
        $this->info("Running {$platform} crawler - {$type}");

        if ($dryRun) {
            $this->line("Would run: {$platform} {$type}" . ($keywords ? ' with keywords: ' . implode(', ', $keywords) : ''));
            return 0;
        }

        $parameters = [];
        if ($keywords) {
            $parameters['keywords'] = array_map('trim', $keywords);
        }

        try {
            SocialCrawlerJob::dispatch($platform, $type, $parameters);
            $this->info("Queued {$platform} crawler job");
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to queue crawler job: {$e->getMessage()}");
            return 1;
        }
    }

    protected function runScheduledCrawls(bool $force, bool $dryRun): int
    {
        $this->info('Running scheduled social media crawls...');

        $platforms = ['twitter', 'reddit', 'telegram'];
        $crawlTypes = $this->getCrawlTypes();
        
        $totalJobs = 0;

        foreach ($platforms as $platform) {
            foreach ($crawlTypes[$platform] as $type) {
                $jobStatus = CrawlerJobStatus::where('platform', $platform)
                    ->where('job_type', $type)
                    ->first();

                if (!$force && $jobStatus && !$jobStatus->is_overdue) {
                    $nextRun = $jobStatus->next_run_at ? $jobStatus->next_run_at->diffForHumans() : 'unknown';
                    $this->line("Skipping {$platform}:{$type} - next run {$nextRun}");
                    continue;
                }

                if ($dryRun) {
                    $this->line("Would run: {$platform} {$type}");
                    continue;
                }

                try {
                    SocialCrawlerJob::dispatch($platform, $type);
                    $this->info("Queued {$platform}:{$type}");
                    $totalJobs++;
                } catch (\Exception $e) {
                    $this->error("Failed to queue {$platform}:{$type} - {$e->getMessage()}");
                }
            }
        }

        if ($dryRun) {
            $this->info("Dry run completed - would have queued jobs for crawling");
        } else {
            $this->info("Queued {$totalJobs} crawler jobs");
        }

        return 0;
    }

    protected function getCrawlTypes(): array
    {
        return [
            'twitter' => ['keywords', 'hashtags', 'users'],
            'reddit' => ['keywords', 'subreddits'],
            'telegram' => ['keywords', 'channels'],
        ];
    }
}