<?php

namespace App\Console\Commands;

use App\Models\CrawlerJobStatus;
use App\Models\SocialMediaPost;
use App\Models\KeywordMatch;
use Illuminate\Console\Command;

class SocialCrawlerStatus extends Command
{
    protected $signature = 'social:status
                          {--platform= : Show status for specific platform}
                          {--recent= : Show posts from last N hours (default: 24)}';

    protected $description = 'Show social media crawler status and recent activity';

    public function handle(): int
    {
        $platform = $this->option('platform');
        $hours = (int) $this->option('recent', 24);

        $this->showJobStatus($platform);
        $this->newLine();
        $this->showRecentActivity($platform, $hours);
        $this->newLine();
        $this->showKeywordStats($platform, $hours);

        return 0;
    }

    protected function showJobStatus(?string $platform): void
    {
        $this->info('Crawler Job Status');
        $this->line(str_repeat('=', 50));

        $query = CrawlerJobStatus::query();
        if ($platform) {
            $query->where('platform', $platform);
        }

        $jobs = $query->orderBy('platform')->orderBy('job_type')->get();

        if ($jobs->isEmpty()) {
            $this->warn('No crawler jobs found');
            return;
        }

        $headers = ['Platform', 'Type', 'Status', 'Last Run', 'Next Run', 'Posts', 'Error'];
        $rows = [];

        foreach ($jobs as $job) {
            $rows[] = [
                $job->platform,
                $job->job_type,
                "<fg={$job->status_color}>{$job->status}</>",
                $job->last_run_at ? $job->last_run_at->diffForHumans() : 'Never',
                $job->next_run_at ? $job->next_run_at->diffForHumans() : 'N/A',
                number_format($job->posts_collected ?? 0),
                $job->last_error_message ? substr($job->last_error_message, 0, 30) . '...' : '-',
            ];
        }

        $this->table($headers, $rows);
    }

    protected function showRecentActivity(?string $platform, int $hours): void
    {
        $this->info("Recent Activity (Last {$hours} hours)");
        $this->line(str_repeat('=', 50));

        $query = SocialMediaPost::query()
            ->where('created_at', '>=', now()->subHours($hours));

        if ($platform) {
            $query->where('platform', $platform);
        }

        $posts = $query->orderBy('created_at', 'desc')->limit(10)->get();

        if ($posts->isEmpty()) {
            $this->warn('No recent posts found');
            return;
        }

        $headers = ['Platform', 'Author', 'Content', 'Sentiment', 'Keywords', 'Time'];
        $rows = [];

        foreach ($posts as $post) {
            $rows[] = [
                $post->platform,
                $post->author_username ?? 'Unknown',
                substr($post->content, 0, 50) . '...',
                "<fg={$post->sentiment_color}>{$post->sentiment_label}</>",
                count($post->matched_keywords ?? []),
                $post->created_at->diffForHumans(),
            ];
        }

        $this->table($headers, $rows);

        // Summary stats
        $totalPosts = SocialMediaPost::query()
            ->where('created_at', '>=', now()->subHours($hours))
            ->when($platform, fn($q) => $q->where('platform', $platform))
            ->count();

        $platformStats = SocialMediaPost::query()
            ->where('created_at', '>=', now()->subHours($hours))
            ->when($platform, fn($q) => $q->where('platform', $platform))
            ->selectRaw('platform, count(*) as count')
            ->groupBy('platform')
            ->pluck('count', 'platform')
            ->toArray();

        $this->newLine();
        $this->info("Total posts collected: " . number_format($totalPosts));
        
        foreach ($platformStats as $plat => $count) {
            $this->line("  {$plat}: " . number_format($count));
        }
    }

    protected function showKeywordStats(?string $platform, int $hours): void
    {
        $this->info("Top Keywords (Last {$hours} hours)");
        $this->line(str_repeat('=', 50));

        $query = KeywordMatch::query()
            ->where('created_at', '>=', now()->subHours($hours));

        if ($platform) {
            $query->whereHas('socialMediaPost', fn($q) => $q->where('platform', $platform));
        }

        $keywords = $query
            ->selectRaw('keyword, keyword_category, priority, count(*) as mentions, sum(match_count) as total_matches')
            ->groupBy('keyword', 'keyword_category', 'priority')
            ->orderByDesc('mentions')
            ->limit(15)
            ->get();

        if ($keywords->isEmpty()) {
            $this->warn('No keyword matches found');
            return;
        }

        $headers = ['Keyword', 'Category', 'Priority', 'Posts', 'Total Matches'];
        $rows = [];

        foreach ($keywords as $keyword) {
            $priorityColor = match($keyword->priority) {
                'critical' => 'red',
                'high' => 'yellow',
                'medium' => 'blue',
                default => 'white'
            };

            $rows[] = [
                $keyword->keyword,
                $keyword->keyword_category,
                "<fg={$priorityColor}>{$keyword->priority}</>",
                number_format($keyword->mentions),
                number_format($keyword->total_matches),
            ];
        }

        $this->table($headers, $rows);

        // Show critical alerts
        $criticalCount = KeywordMatch::query()
            ->where('created_at', '>=', now()->subHours($hours))
            ->where('priority', 'critical')
            ->when($platform, fn($q) => $q->whereHas('socialMediaPost', fn($sq) => $sq->where('platform', $platform)))
            ->count();

        if ($criticalCount > 0) {
            $this->newLine();
            $this->warn("⚠️  {$criticalCount} critical keyword matches in the last {$hours} hours");
        }
    }
}