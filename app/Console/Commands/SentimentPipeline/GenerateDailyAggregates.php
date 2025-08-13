<?php

declare(strict_types=1);

namespace App\Console\Commands\SentimentPipeline;

use App\Services\SentimentPipeline\DailySentimentAggregateService;
use Carbon\Carbon;
use Illuminate\Console\Command;

final class GenerateDailyAggregates extends Command
{
    protected $signature = 'sentiment:generate-aggregates 
                           {date? : Date to generate aggregates for (YYYY-MM-DD format, defaults to yesterday)}
                           {--start-date= : Start date for date range (YYYY-MM-DD)}
                           {--end-date= : End date for date range (YYYY-MM-DD)}
                           {--force : Regenerate existing aggregates}';

    protected $description = 'Generate daily sentiment aggregates from processed sentiment data';

    public function handle(DailySentimentAggregateService $aggregateService): int
    {
        if ($this->option('start-date') && $this->option('end-date')) {
            return $this->generateAggregatesForDateRange($aggregateService);
        }

        $dateString = $this->argument('date') ?? now()->subDay()->toDateString();
        
        try {
            $date = Carbon::createFromFormat('Y-m-d', $dateString);
        } catch (\Exception $e) {
            $this->error("Invalid date format. Please use YYYY-MM-DD format.");
            return 1;
        }

        return $this->generateAggregatesForDate($date, $aggregateService);
    }

    protected function generateAggregatesForDate(Carbon $date, DailySentimentAggregateService $aggregateService): int
    {
        $this->info("Generating sentiment aggregates for date: {$date->toDateString()}");

        // Check if aggregates already exist
        if (!$this->option('force') && $this->aggregatesExist($date)) {
            if (!$this->confirm("Aggregates already exist for {$date->toDateString()}. Regenerate?")) {
                $this->info("Skipping aggregate generation.");
                return 0;
            }
        }

        try {
            $startTime = microtime(true);
            
            $aggregates = $aggregateService->generateDailyAggregates($date);
            
            $processingTime = microtime(true) - $startTime;

            $this->info("✅ Daily aggregates generated successfully!");
            
            $this->displayAggregateStats($aggregates, $processingTime);

            return 0;

        } catch (\Exception $e) {
            $this->error("Failed to generate aggregates: {$e->getMessage()}");
            return 1;
        }
    }

    protected function generateAggregatesForDateRange(DailySentimentAggregateService $aggregateService): int
    {
        try {
            $startDate = Carbon::createFromFormat('Y-m-d', $this->option('start-date'));
            $endDate = Carbon::createFromFormat('Y-m-d', $this->option('end-date'));
        } catch (\Exception $e) {
            $this->error("Invalid date format. Please use YYYY-MM-DD format.");
            return 1;
        }

        if ($startDate->gt($endDate)) {
            $this->error("Start date must be before or equal to end date.");
            return 1;
        }

        $totalDays = $startDate->diffInDays($endDate) + 1;
        
        $this->info("Generating aggregates for date range: {$startDate->toDateString()} to {$endDate->toDateString()}");
        $this->info("Total days to process: {$totalDays}");

        if ($totalDays > 30 && !$this->confirm("This will process {$totalDays} days. Continue?")) {
            return 0;
        }

        $progressBar = $this->output->createProgressBar($totalDays);
        $progressBar->start();

        $processed = 0;
        $failed = 0;
        $totalAggregates = 0;

        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            try {
                $aggregates = $aggregateService->generateDailyAggregates($currentDate);
                $totalAggregates += count($aggregates);
                $processed++;
                
            } catch (\Exception $e) {
                $failed++;
                $this->newLine();
                $this->error("Failed to process {$currentDate->toDateString()}: {$e->getMessage()}");
            }

            $progressBar->advance();
            $currentDate->addDay();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("📊 Processing Summary:");
        $this->info("Processed: {$processed} days");
        $this->info("Failed: {$failed} days");
        $this->info("Total aggregates created: {$totalAggregates}");

        return $failed > 0 ? 1 : 0;
    }

    protected function aggregatesExist(Carbon $date): bool
    {
        return \App\Models\DailySentimentAggregate::forDate($date)->exists();
    }

    protected function displayAggregateStats(array $aggregates, float $processingTime): void
    {
        $this->table(['Metric', 'Value'], [
            ['Aggregates Created', count($aggregates)],
            ['Processing Time', number_format($processingTime, 2) . ' seconds'],
            ['Platforms Processed', $this->countUnique($aggregates, 'platform')],
            ['Categories Processed', $this->countUnique($aggregates, 'keyword_category')],
            ['Languages Processed', $this->countUnique($aggregates, 'language')],
        ]);

        // Show sample of created aggregates
        if (!empty($aggregates)) {
            $this->info("\n📋 Sample Aggregates:");
            
            $sampleData = [];
            foreach (array_slice($aggregates, 0, 5) as $aggregate) {
                $sampleData[] = [
                    $aggregate->platform,
                    $aggregate->keyword_category ?? 'all',
                    $aggregate->total_posts,
                    number_format($aggregate->average_sentiment, 3),
                    $aggregate->sentiment_label,
                ];
            }

            $this->table(
                ['Platform', 'Category', 'Posts', 'Avg Sentiment', 'Label'],
                $sampleData
            );
        }
    }

    protected function countUnique(array $aggregates, string $field): int
    {
        $values = array_map(function($aggregate) use ($field) {
            return $aggregate->$field;
        }, $aggregates);

        return count(array_unique($values));
    }
}