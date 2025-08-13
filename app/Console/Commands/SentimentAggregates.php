<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SentimentPipeline\DailySentimentAggregateService;
use App\Models\DailySentimentAggregate;
use Illuminate\Console\Command;
use Carbon\Carbon;

final class SentimentAggregates extends Command
{
    protected $signature = 'sentiment:aggregates
                           {--date= : Show aggregates for specific date (Y-m-d format, or "today", "yesterday")}
                           {--platform= : Filter by platform}
                           {--category= : Filter by keyword category}
                           {--range= : Show date range (7d, 30d, 90d)}
                           {--generate : Generate aggregates for the specified date}
                           {--format=table : Output format (table, json, csv, chart)}
                           {--export= : Export to file (provide file path)}
                           {--detailed : Show detailed breakdown}';

    protected $description = 'View and manage daily sentiment aggregates';

    public function handle(): int
    {
        $this->displayHeader();
        
        try {
            if ($this->option('generate')) {
                return $this->generateAggregates();
            }
            
            $date = $this->parseDate();
            $range = $this->option('range');
            
            if ($range) {
                return $this->showDateRange($date, $range);
            } else {
                return $this->showSingleDate($date);
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function displayHeader(): void
    {
        $this->info('📈 DAILY SENTIMENT AGGREGATES');
        $this->info('Comprehensive analysis of sentiment data trends');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->newLine();
    }

    private function parseDate(): Carbon
    {
        $dateOption = $this->option('date');
        
        if (!$dateOption) {
            return today();
        }
        
        return match($dateOption) {
            'today' => today(),
            'yesterday' => yesterday(),
            default => Carbon::parse($dateOption)
        };
    }

    private function generateAggregates(): int
    {
        $date = $this->parseDate();
        $platform = $this->option('platform');
        
        $this->info("🔄 Generating aggregates for {$date->toDateString()}...");
        
        $aggregateService = app(DailySentimentAggregateService::class);
        
        $progressBar = $this->output->createProgressBar(100);
        $progressBar->setFormat('🔄 Generating: %current%/%max% [%bar%] %percent:3s%% - %message%');
        $progressBar->start();
        
        try {
            $progressBar->setMessage('Initializing...');
            $progressBar->advance(10);
            
            $options = [
                'force_regenerate' => true,
                'include_hourly' => $this->option('detailed'),
                'platform' => $platform
            ];
            
            $progressBar->setMessage('Processing sentiment data...');
            $progressBar->advance(30);
            
            $results = $aggregateService->generateDailyAggregates($date, $options);
            
            $progressBar->setMessage('Calculating trends...');
            $progressBar->advance(30);
            
            $progressBar->setMessage('Finalizing aggregates...');
            $progressBar->advance(30);
            
            $progressBar->finish();
            $this->newLine(2);
            
            $this->info('✅ Aggregates generated successfully');
            $this->displayGenerationResults($results);
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $progressBar->finish();
            $this->newLine();
            $this->error('❌ Failed to generate aggregates: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function showSingleDate(Carbon $date): int
    {
        $platform = $this->option('platform');
        $category = $this->option('category');
        $format = $this->option('format');
        
        $this->info("📊 Sentiment Aggregates for {$date->toDateString()}");
        $this->newLine();
        
        $query = DailySentimentAggregate::forDate($date)->fullDay();
        
        if ($platform) {
            $query->forPlatform($platform);
        }
        
        if ($category) {
            $query->forCategory($category);
        }
        
        $aggregates = $query->get();
        
        if ($aggregates->isEmpty()) {
            $this->warn('No aggregates found for the specified criteria');
            $this->newLine();
            $this->line('💡 Generate aggregates with: sentiment:aggregates --generate --date=' . $date->toDateString());
            return Command::SUCCESS;
        }
        
        switch ($format) {
            case 'json':
                $this->displayAsJson($aggregates);
                break;
            case 'csv':
                $this->displayAsCsv($aggregates);
                break;
            case 'chart':
                $this->displayAsChart($aggregates);
                break;
            default:
                $this->displayAsTable($aggregates);
                break;
        }
        
        if ($this->option('detailed')) {
            $this->displayDetailedAnalysis($aggregates);
        }
        
        $this->displaySummaryStats($aggregates);
        
        return Command::SUCCESS;
    }

    private function showDateRange(Carbon $startDate, string $range): int
    {
        $days = match($range) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            default => 7
        };
        
        $endDate = $startDate->copy()->addDays($days - 1);
        
        $this->info("📊 Sentiment Trends: {$startDate->toDateString()} to {$endDate->toDateString()}");
        $this->newLine();
        
        $platform = $this->option('platform');
        $query = DailySentimentAggregate::dateRange($startDate, $endDate)->fullDay();
        
        if ($platform) {
            $query->forPlatform($platform);
        }
        
        $aggregates = $query->orderBy('aggregate_date')->get();
        
        if ($aggregates->isEmpty()) {
            $this->warn('No aggregates found for the specified date range');
            return Command::SUCCESS;
        }
        
        $this->displayTrendAnalysis($aggregates, $range);
        
        return Command::SUCCESS;
    }

    private function displayAsTable($aggregates): void
    {
        $tableData = [];
        
        foreach ($aggregates as $aggregate) {
            $tableData[] = [
                $aggregate->platform ?? 'all',
                $aggregate->keyword_category ?? 'general',
                number_format($aggregate->total_posts),
                number_format($aggregate->processed_posts),
                round($aggregate->average_sentiment, 3),
                $aggregate->sentiment_label,
                round($aggregate->average_magnitude, 3),
                number_format($aggregate->total_engagement ?? 0)
            ];
        }
        
        $this->table([
            'Platform',
            'Category', 
            'Total Posts',
            'Processed',
            'Avg Sentiment',
            'Label',
            'Magnitude',
            'Engagement'
        ], $tableData);
    }

    private function displayAsJson($aggregates): void
    {
        $data = $aggregates->map(function ($aggregate) {
            return [
                'date' => $aggregate->aggregate_date->toDateString(),
                'platform' => $aggregate->platform,
                'category' => $aggregate->keyword_category,
                'total_posts' => $aggregate->total_posts,
                'processed_posts' => $aggregate->processed_posts,
                'average_sentiment' => $aggregate->average_sentiment,
                'sentiment_label' => $aggregate->sentiment_label,
                'average_magnitude' => $aggregate->average_magnitude,
                'sentiment_distribution' => $aggregate->getSentimentDistribution(),
                'sentiment_percentages' => $aggregate->getSentimentPercentages(),
                'top_keywords' => $aggregate->getTopKeywords(5),
                'engagement_rate' => $aggregate->engagement_rate,
                'processing_rate' => $aggregate->processing_rate
            ];
        });
        
        $this->line(json_encode($data, JSON_PRETTY_PRINT));
    }

    private function displayAsCsv($aggregates): void
    {
        // CSV header
        $this->line('"Date","Platform","Category","Total Posts","Processed Posts","Avg Sentiment","Sentiment Label","Magnitude","Engagement"');
        
        foreach ($aggregates as $aggregate) {
            $this->line(sprintf(
                '"%s","%s","%s","%d","%d","%.3f","%s","%.3f","%d"',
                $aggregate->aggregate_date->toDateString(),
                $aggregate->platform ?? 'all',
                $aggregate->keyword_category ?? 'general',
                $aggregate->total_posts,
                $aggregate->processed_posts,
                $aggregate->average_sentiment,
                $aggregate->sentiment_label,
                $aggregate->average_magnitude,
                $aggregate->total_engagement ?? 0
            ));
        }
    }

    private function displayAsChart($aggregates): void
    {
        $this->info('📊 Sentiment Distribution Chart');
        $this->newLine();
        
        foreach ($aggregates as $aggregate) {
            $platform = $aggregate->platform ?? 'all';
            $this->line("📱 {$platform}:");
            
            $distribution = $aggregate->getSentimentPercentages();
            
            foreach ($distribution as $sentiment => $percentage) {
                $barLength = (int) ($percentage / 2); // Scale down for display
                $bar = str_repeat('█', $barLength);
                $color = $this->getSentimentColor($sentiment);
                
                $this->line(sprintf(
                    "   %s %s %s (%.1f%%)",
                    $this->getSentimentEmoji($sentiment),
                    str_pad(ucfirst(str_replace('_', ' ', $sentiment)), 15),
                    $bar,
                    $percentage
                ));
            }
            $this->newLine();
        }
    }

    private function displayDetailedAnalysis($aggregates): void
    {
        $this->newLine();
        $this->info('🔍 Detailed Analysis');
        
        foreach ($aggregates as $aggregate) {
            $platform = $aggregate->platform ?? 'all';
            $this->line("📱 Platform: {$platform}");
            
            // Sentiment breakdown
            $this->line('   📊 Sentiment Breakdown:');
            $distribution = $aggregate->getSentimentDistribution();
            foreach ($distribution as $sentiment => $count) {
                if ($count > 0) {
                    $this->line("      {$this->getSentimentEmoji($sentiment)} " . 
                              ucfirst(str_replace('_', ' ', $sentiment)) . ": " . 
                              number_format($count));
                }
            }
            
            // Top keywords
            $keywords = $aggregate->getTopKeywords(5);
            if (!empty($keywords)) {
                $this->line('   🔑 Top Keywords:');
                foreach ($keywords as $keyword => $count) {
                    $this->line("      • {$keyword}: {$count}");
                }
            }
            
            // Engagement metrics
            if ($aggregate->total_engagement > 0) {
                $this->line('   📈 Engagement:');
                $this->line("      Total: " . number_format($aggregate->total_engagement));
                $this->line("      Rate: " . $aggregate->engagement_rate . " per post");
            }
            
            $this->newLine();
        }
    }

    private function displaySummaryStats($aggregates): void
    {
        $this->newLine();
        $this->info('📊 Summary Statistics');
        
        $totalPosts = $aggregates->sum('total_posts');
        $totalProcessed = $aggregates->sum('processed_posts');
        $avgSentiment = $aggregates->avg('average_sentiment');
        $avgMagnitude = $aggregates->avg('average_magnitude');
        $totalEngagement = $aggregates->sum('total_engagement');
        
        $summaryData = [
            ['Total Posts', number_format($totalPosts)],
            ['Processed Posts', number_format($totalProcessed)],
            ['Processing Rate', $totalPosts > 0 ? round(($totalProcessed / $totalPosts) * 100, 1) . '%' : '0%'],
            ['Average Sentiment', round($avgSentiment, 3)],
            ['Average Magnitude', round($avgMagnitude, 3)],
            ['Sentiment Label', $this->getSentimentLabel($avgSentiment)],
            ['Total Engagement', number_format($totalEngagement)],
            ['Platforms Covered', $aggregates->whereNotNull('platform')->pluck('platform')->unique()->count()]
        ];
        
        $this->table(['Metric', 'Value'], $summaryData);
    }

    private function displayTrendAnalysis($aggregates, string $range): void
    {
        $this->info("📈 Trend Analysis ({$range})");
        $this->newLine();
        
        // Group by date for trend analysis
        $trendData = $aggregates->groupBy(function($item) {
            return $item->aggregate_date->toDateString();
        });
        
        $this->line('📅 Daily Sentiment Trends:');
        $this->newLine();
        
        $tableData = [];
        $previousSentiment = null;
        
        foreach ($trendData as $date => $dayAggregates) {
            $avgSentiment = $dayAggregates->avg('average_sentiment');
            $totalPosts = $dayAggregates->sum('total_posts');
            
            $trend = '→';
            if ($previousSentiment !== null) {
                $change = $avgSentiment - $previousSentiment;
                $trend = $change > 0.05 ? '↗️' : ($change < -0.05 ? '↘️' : '→');
            }
            
            $tableData[] = [
                $date,
                number_format($totalPosts),
                round($avgSentiment, 3),
                $this->getSentimentLabel($avgSentiment),
                $trend
            ];
            
            $previousSentiment = $avgSentiment;
        }
        
        $this->table(['Date', 'Posts', 'Avg Sentiment', 'Label', 'Trend'], $tableData);
        
        // Calculate overall trend
        $firstSentiment = $aggregates->first()->average_sentiment;
        $lastSentiment = $aggregates->last()->average_sentiment;
        $overallChange = $lastSentiment - $firstSentiment;
        
        $this->newLine();
        $this->line("📊 Overall Trend: " . ($overallChange > 0 ? "↗️ Positive" : ($overallChange < 0 ? "↘️ Negative" : "→ Stable")));
        $this->line("📈 Change: " . sprintf("%+.3f", $overallChange));
    }

    private function displayGenerationResults(array $results): void
    {
        $this->newLine();
        $this->info('📋 Generation Results:');
        
        $resultData = [
            ['Generated Aggregates', $results['aggregates_created'] ?? 0],
            ['Updated Aggregates', $results['aggregates_updated'] ?? 0],
            ['Processing Time', isset($results['processing_time']) ? round($results['processing_time'], 2) . 's' : 'N/A'],
            ['Source Data Points', $results['source_data_count'] ?? 0],
            ['Platforms Processed', isset($results['platforms']) ? implode(', ', $results['platforms']) : 'N/A']
        ];
        
        $this->table(['Metric', 'Value'], $resultData);
    }

    private function getSentimentEmoji(string $sentiment): string
    {
        return match($sentiment) {
            'very_positive' => '😍',
            'positive' => '😊',
            'neutral' => '😐',
            'negative' => '😞',
            'very_negative' => '😡',
            default => '❓'
        };
    }

    private function getSentimentColor(string $sentiment): string
    {
        return match($sentiment) {
            'very_positive' => 'green',
            'positive' => 'lime',
            'neutral' => 'gray',
            'negative' => 'orange',
            'very_negative' => 'red',
            default => 'white'
        };
    }

    private function getSentimentLabel(float $score): string
    {
        return match(true) {
            $score > 0.6 => 'Very Positive',
            $score > 0.2 => 'Positive',
            $score > -0.2 => 'Neutral',
            $score > -0.6 => 'Negative',
            default => 'Very Negative'
        };
    }
}