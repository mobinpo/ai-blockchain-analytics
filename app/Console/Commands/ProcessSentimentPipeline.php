<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SentimentPipelineService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Exception;

class ProcessSentimentPipeline extends Command
{
    protected $signature = 'sentiment:process-pipeline 
                           {--file= : Path to JSON file containing text data}
                           {--text= : Single text to process}
                           {--batch-size=25 : Batch size for processing}
                           {--platform=api : Platform identifier}
                           {--category=general : Category for aggregation}
                           {--language=en : Language code}
                           {--queue : Queue the processing instead of running synchronously}
                           {--simulate : Simulate without actual Google NLP calls}';

    protected $description = 'Process text through Google Cloud NLP sentiment analysis and store daily aggregates';

    public function handle(SentimentPipelineService $pipelineService): int
    {
        $this->info('🔄 Starting Sentiment Pipeline Processing');

        try {
            // Get text data
            $textData = $this->getTextData();
            
            if (empty($textData)) {
                $this->error('❌ No text data provided');
                return 1;
            }

            // Prepare options
            $options = [
                'batch_size' => (int) $this->option('batch-size'),
                'platform' => $this->option('platform'),
                'category' => $this->option('category'),
                'language' => $this->option('language'),
                'simulate' => $this->option('simulate'),
                'date' => now()->toDateString()
            ];

            $this->info("📝 Processing " . count($textData) . " text items");
            $this->displayOptions($options);

            // Process pipeline
            if ($this->option('queue')) {
                $batchId = $pipelineService->queueTextPipeline($textData, $options);
                $this->info("✅ Pipeline queued successfully");
                $this->info("📋 Batch ID: {$batchId}");
                $this->info("🔍 Check status with: php artisan sentiment:batch-status {$batchId}");
            } else {
                $this->withProgressBar($textData, function () use ($pipelineService, $textData, $options) {
                    return $pipelineService->processTextPipeline($textData, $options);
                });

                $result = $pipelineService->processTextPipeline($textData, $options);
                $this->newLine(2);
                $this->displayResults($result);
            }

            return 0;

        } catch (Exception $e) {
            $this->error("❌ Pipeline processing failed: {$e->getMessage()}");
            
            if ($this->getOutput()->isVerbose()) {
                $this->error($e->getTraceAsString());
            }
            
            return 1;
        }
    }

    private function getTextData(): array
    {
        // From file
        if ($file = $this->option('file')) {
            if (!File::exists($file)) {
                throw new Exception("File not found: {$file}");
            }

            $content = File::get($file);
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid JSON in file: " . json_last_error_msg());
            }

            return $data;
        }

        // From single text option
        if ($text = $this->option('text')) {
            return [$text];
        }

        // Interactive input
        return $this->getInteractiveTextData();
    }

    private function getInteractiveTextData(): array
    {
        $this->info('💬 Enter text data interactively');
        $texts = [];

        while (true) {
            $text = $this->ask('Enter text (empty to finish)');
            
            if (empty($text)) {
                break;
            }

            $texts[] = $text;
            $this->info("✅ Added text #{count($texts)}");
        }

        return $texts;
    }

    private function displayOptions(array $options): void
    {
        $this->info('⚙️ Processing Options:');
        $this->table(
            ['Option', 'Value'],
            [
                ['Batch Size', $options['batch_size']],
                ['Platform', $options['platform']],
                ['Category', $options['category']],
                ['Language', $options['language']],
                ['Date', $options['date']],
                ['Simulate', $options['simulate'] ? 'Yes' : 'No']
            ]
        );
    }

    private function displayResults(array $result): void
    {
        $this->info('📊 Processing Results:');
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Status', $result['status']],
                ['Processed Count', $result['processed_count']],
                ['Daily Aggregates', $result['daily_aggregates']],
                ['Processing Time', "{$result['processing_time']}s"],
                ['Cost Estimate', '$' . number_format($result['cost_estimate'], 4)],
                ['Success Rate', "{$result['batch_info']['success_rate']}%"]
            ]
        );

        // Show batch info
        $batchInfo = $result['batch_info'];
        $this->info('🔍 Batch Information:');
        $this->line("  Total: {$batchInfo['total']}");
        $this->line("  ✅ Successful: {$batchInfo['successful']}");
        $this->line("  ❌ Failed: {$batchInfo['failed']}");
    }
} 