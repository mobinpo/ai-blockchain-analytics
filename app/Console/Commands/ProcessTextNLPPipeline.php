<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SentimentPipeline\GoogleCloudBatchProcessor;
use App\Jobs\ProcessTextThroughNLPPipeline;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ProcessTextNLPPipeline extends Command
{
    protected $signature = 'nlp:process-text 
                           {--file= : Path to text file (one text per line)}
                           {--text= : Single text to process}
                           {--platform=general : Platform source}
                           {--category=general : Category for grouping}
                           {--language=en : Language code}
                           {--async : Process asynchronously via queue}
                           {--chunk-size=100 : Chunk size for large batches}
                           {--aggregates : Generate daily aggregates}';

    protected $description = 'Process text through Google Cloud NLP pipeline to daily aggregates';

    public function handle(GoogleCloudBatchProcessor $processor): int
    {
        $this->info('🤖 Google Cloud NLP Pipeline');
        $this->line('Text → Sentiment Analysis → Daily Aggregates');
        $this->newLine();

        // Get texts to process
        $texts = $this->getTextsToProcess();
        if (empty($texts)) {
            $this->error('No texts provided to process');
            return Command::FAILURE;
        }

        $metadata = [
            'platform' => $this->option('platform'),
            'keyword_category' => $this->option('category'),
            'language' => $this->option('language'),
            'batch_name' => 'cli_batch_' . now()->format('Y-m-d_H-i-s'),
            'description' => 'CLI initiated NLP pipeline processing',
            'chunk_size' => (int) $this->option('chunk-size'),
            'source' => 'cli_command'
        ];

        $this->line("Processing {$this->count($texts)} texts...");
        $this->line("Platform: {$metadata['platform']}");
        $this->line("Category: {$metadata['keyword_category']}");
        $this->line("Language: {$metadata['language']}");
        $this->newLine();

        if ($this->option('async')) {
            return $this->processAsync($texts, $metadata);
        } else {
            return $this->processSync($processor, $texts, $metadata);
        }
    }

    private function getTextsToProcess(): array
    {
        $texts = [];

        // Single text option
        if ($this->option('text')) {
            $texts[] = $this->option('text');
        }

        // File option
        if ($this->option('file')) {
            $filePath = $this->option('file');
            
            if (!file_exists($filePath)) {
                $this->error("File not found: {$filePath}");
                return [];
            }

            $fileTexts = array_filter(
                array_map('trim', file($filePath, FILE_IGNORE_NEW_LINES)),
                fn($text) => !empty($text)
            );

            $texts = array_merge($texts, $fileTexts);
        }

        // Interactive input if no options provided
        if (empty($texts)) {
            $this->line('No --text or --file provided. Enter texts interactively:');
            $this->line('(Enter empty line to finish)');
            $this->newLine();

            while (true) {
                $text = $this->ask('Enter text');
                if (empty($text)) {
                    break;
                }
                $texts[] = $text;
            }
        }

        return $texts;
    }

    private function processSync(
        GoogleCloudBatchProcessor $processor,
        array $texts,
        array $metadata
    ): int {
        $this->line('🔄 Processing synchronously...');
        
        $bar = $this->output->createProgressBar(1);
        $bar->start();

        try {
            $chunkSize = (int) $this->option('chunk-size');
            
            if (count($texts) > $chunkSize) {
                $result = $processor->processLargeBatch($texts, $metadata, $chunkSize);
                $bar->advance();
                
                $this->displayLargeBatchResults($result);
            } else {
                $result = $processor->processTextToDailyAggregates(
                    $texts,
                    $metadata,
                    $this->option('aggregates')
                );
                $bar->advance();
                
                $this->displayResults($result);
            }

            $bar->finish();
            $this->newLine(2);
            $this->info('✅ Processing completed successfully!');
            
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $bar->finish();
            $this->newLine(2);
            $this->error('❌ Processing failed: ' . $e->getMessage());
            
            return Command::FAILURE;
        }
    }

    private function processAsync(array $texts, array $metadata): int
    {
        $this->line('⚡ Dispatching to queue for async processing...');

        try {
            $chunkSize = (int) $this->option('chunk-size');
            
            if (count($texts) > $chunkSize) {
                // Process large batches in chunks
                $chunks = array_chunk($texts, $chunkSize);
                
                foreach ($chunks as $index => $chunk) {
                    $chunkMetadata = array_merge($metadata, [
                        'batch_name' => $metadata['batch_name'] . "_chunk_" . ($index + 1),
                        'chunk_number' => $index + 1,
                        'total_chunks' => count($chunks)
                    ]);

                    ProcessTextThroughNLPPipeline::dispatch(
                        $chunk,
                        $chunkMetadata,
                        $this->option('aggregates')
                    );
                }

                $this->info("✅ Dispatched {$this->count($chunks)} jobs to queue");
                $this->line("Total texts: {$this->count($texts)}");
                
            } else {
                ProcessTextThroughNLPPipeline::dispatch(
                    $texts,
                    $metadata,
                    $this->option('aggregates')
                );

                $this->info('✅ Job dispatched to queue');
                $this->line("Texts queued: {$this->count($texts)}");
            }

            $this->newLine();
            $this->line('📊 Monitor progress with:');
            $this->line('  php artisan queue:work');
            $this->line('  php artisan horizon (if using Horizon)');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Failed to dispatch job: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function displayResults(array $result): void
    {
        $this->newLine();
        $this->info('📊 Processing Results:');
        $this->line("Batch ID: {$result['batch_id']}");
        $this->line("Processed: {$result['processed_count']} texts");
        $this->line("Daily Aggregates: {$this->count($result['daily_aggregates'])}");
        $this->line("Execution Time: {$result['execution_time_ms']}ms");
        
        if (!empty($result['sentiment_results'])) {
            $this->newLine();
            $this->line('🎯 Sentiment Summary:');
            
            $sentiments = array_column($result['sentiment_results'], 'sentiment_label');
            $counts = array_count_values($sentiments);
            
            foreach ($counts as $sentiment => $count) {
                $icon = match($sentiment) {
                    'positive' => '😊',
                    'negative' => '😞', 
                    'neutral' => '😐',
                    default => '❓'
                };
                $this->line("  {$icon} {$sentiment}: {$count}");
            }
        }
    }

    private function displayLargeBatchResults(array $result): void
    {
        $this->newLine();
        $this->info('📊 Large Batch Results:');
        $this->line("Total Chunks: {$result['total_chunks_processed']}");
        
        $totalProcessed = 0;
        $totalAggregates = 0;
        $totalTime = 0;

        foreach ($result['chunk_results'] as $chunkResult) {
            $totalProcessed += $chunkResult['processed_count'];
            $totalAggregates += count($chunkResult['daily_aggregates']);
            $totalTime += $chunkResult['execution_time_ms'];
        }

        $this->line("Total Processed: {$totalProcessed} texts");
        $this->line("Total Aggregates: {$totalAggregates}");
        $this->line("Total Time: {$totalTime}ms");
    }

    private function count(array $items): int
    {
        return count($items);
    }
}