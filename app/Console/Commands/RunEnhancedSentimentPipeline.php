<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EnhancedSentimentPipelineService;
use App\Models\DailySentimentAggregate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Enhanced Sentiment Pipeline Management Command
 * 
 * Provides CLI interface for text → Google Cloud NLP → daily aggregates pipeline
 */
class RunEnhancedSentimentPipeline extends Command
{
    protected $signature = 'sentiment:enhanced-pipeline
                           {--mode=auto : Processing mode (immediate|batched|queued|auto)}
                           {--platform=cli : Platform identifier}
                           {--keyword= : Keyword for categorization}
                           {--date= : Target date for aggregation (YYYY-MM-DD)}
                           {--input= : Input file path with text data}
                           {--sample : Use sample data for demonstration}
                           {--aggregate : Auto-generate daily aggregates}
                           {--monitor : Show pipeline monitoring information}
                           {--status : Show pipeline health status}
                           {--cost : Show cost estimation}';

    protected $description = 'Run enhanced sentiment pipeline with Google Cloud NLP and daily aggregation';

    private EnhancedSentimentPipelineService $pipelineService;

    public function __construct(EnhancedSentimentPipelineService $pipelineService)
    {
        parent::__construct();
        $this->pipelineService = $pipelineService;
    }

    public function handle(): int
    {
        $this->info('🧠 Enhanced Sentiment Pipeline - Text → Google Cloud NLP → Daily Aggregates');
        $this->line('=======================================================================');

        try {
            // Handle different command modes
            if ($this->option('status')) {
                return $this->showPipelineStatus();
            }

            if ($this->option('monitor')) {
                return $this->showMonitoring();
            }

            if ($this->option('cost')) {
                return $this->showCostEstimation();
            }

            // Main pipeline execution
            return $this->runPipeline();

        } catch (\Exception $e) {
            $this->error('Pipeline execution failed: ' . $e->getMessage());
            Log::error('Enhanced sentiment pipeline command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }

    private function runPipeline(): int
    {
        $this->info('Starting enhanced sentiment pipeline...');

        // Get text data
        $textData = $this->getTextData();
        if (empty($textData)) {
            $this->error('No text data provided. Use --input, --sample, or pipe data to stdin.');
            return 1;
        }

        $this->info('📝 Text data loaded: ' . count($textData) . ' items');

        // Prepare processing options
        $options = [
            'platform' => $this->option('platform'),
            'keyword' => $this->option('keyword'),
            'processing_mode' => $this->option('mode'),
            'auto_aggregate' => $this->option('aggregate'),
        ];

        if ($this->option('date')) {
            $options['process_date'] = Carbon::parse($this->option('date'));
        }

        // Show cost estimation first
        if (count($textData) > 10) {
            $costEstimate = $this->pipelineService->estimateProcessingCost($textData, $options);
            $this->warn('💰 Estimated cost: $' . $costEstimate['total_estimated_cost']);
            
            if (!$this->confirm('Continue with processing?')) {
                $this->info('Processing cancelled by user.');
                return 0;
            }
        }

        // Process the pipeline
        $startTime = microtime(true);
        $this->info('🚀 Processing through sentiment pipeline...');
        
        $progressBar = $this->output->createProgressBar(100);
        $progressBar->start();

        $results = $this->pipelineService->processTextPipeline($textData, $options);

        $progressBar->finish();
        $this->newLine(2);

        // Display results
        $this->displayResults($results, microtime(true) - $startTime);

        return 0;
    }

    private function getTextData(): array
    {
        // Sample data for demonstration
        if ($this->option('sample')) {
            return $this->getSampleData();
        }

        // From input file
        if ($inputFile = $this->option('input')) {
            return $this->loadFromFile($inputFile);
        }

        // From stdin
        if (!posix_isatty(STDIN)) {
            return $this->loadFromStdin();
        }

        return [];
    }

    private function getSampleData(): array
    {
        return [
            "Bitcoin is showing incredible growth potential and the technology is revolutionary!",
            "Ethereum's smart contracts are transforming DeFi in amazing ways.",
            "The new DeFi protocol launched successfully with great user satisfaction.",
            "Another crypto hack happened today, losing millions. Very concerning.",
            "The market is crashing and altcoins are down 50%. Disappointing.",
            "Gas fees on Ethereum are extremely high and uneconomical.",
            "Bitcoin price moved sideways with average trading volume.",
            "Fed announced new digital asset policies and CBDC developments.",
            "Ethereum developers continue working on scheduled upgrades.",
            "While Bitcoin shows promise, volatility makes it risky for investors.",
        ];
    }

    private function loadFromFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            $this->error("Input file not found: {$filePath}");
            return [];
        }

        $content = file_get_contents($filePath);
        
        // Try to parse as JSON first
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Otherwise split by lines
        return array_filter(explode("\n", $content), fn($line) => trim($line) !== '');
    }

    private function loadFromStdin(): array
    {
        $input = '';
        while (!feof(STDIN)) {
            $input .= fread(STDIN, 1024);
        }

        // Try JSON first
        $decoded = json_decode($input, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Split by lines
        return array_filter(explode("\n", $input), fn($line) => trim($line) !== '');
    }

    private function displayResults(array $results, float $totalTime): void
    {
        $this->info('✅ Pipeline execution completed!');
        $this->newLine();

        // Processing Summary
        $this->table(
            ['Metric', 'Value'],
            [
                ['Processing Mode', $results['processing_mode'] ?? 'N/A'],
                ['Total Processing Time', round($totalTime, 2) . 's'],
                ['Texts Processed', $results['processed_count'] ?? 0],
                ['Failed Texts', $results['failed_count'] ?? 0],
                ['Success Rate', round((($results['processed_count'] ?? 0) / max(1, ($results['processed_count'] ?? 0) + ($results['failed_count'] ?? 0))) * 100, 1) . '%'],
                ['Estimated Cost', '$' . ($results['total_cost'] ?? 0)],
            ]
        );

        // Sentiment Analysis Results
        if (isset($results['sentiment_summary'])) {
            $this->newLine();
            $this->info('📊 Sentiment Analysis Summary:');
            
            $sentiment = $results['sentiment_summary'];
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Average Sentiment', round($sentiment['avg_score'] ?? 0, 3)],
                    ['Average Magnitude', round($sentiment['avg_magnitude'] ?? 0, 3)],
                    ['Positive Texts', ($sentiment['positive_count'] ?? 0) . ' (' . round(($sentiment['positive_percentage'] ?? 0), 1) . '%)'],
                    ['Negative Texts', ($sentiment['negative_count'] ?? 0) . ' (' . round(($sentiment['negative_percentage'] ?? 0), 1) . '%)'],
                    ['Neutral Texts', ($sentiment['neutral_count'] ?? 0) . ' (' . round(($sentiment['neutral_percentage'] ?? 0), 1) . '%)'],
                ]
            );
        }

        // Aggregation Results
        if (isset($results['aggregation'])) {
            $this->newLine();
            $this->info('📈 Daily Aggregation Results:');
            $aggregation = $results['aggregation'];
            
            $this->line("• Date: {$aggregation['date']}");
            $this->line("• Platform: {$aggregation['platform']}");
            $this->line("• Keyword: " . ($aggregation['keyword'] ?? 'N/A'));
            $this->line("• Aggregates Created: " . count($aggregation['created'] ?? []));
            $this->line("• Aggregates Updated: " . count($aggregation['updated'] ?? []));
        }

        // Queue Information
        if (isset($results['job_id'])) {
            $this->newLine();
            $this->info('🔄 Queued Job Information:');
            $this->line("• Job ID: {$results['job_id']}");
            $this->line("• Queue: {$results['queue']}");
            $this->line("• Estimated Completion: {$results['estimated_completion']}");
        }

        // Show errors if any
        if (!empty($results['errors'])) {
            $this->newLine();
            $this->warn('⚠️ Processing Errors:');
            foreach ($results['errors'] as $error) {
                $this->line("• {$error}");
            }
        }
    }

    private function showPipelineStatus(): int
    {
        $this->info('📊 Pipeline Health Status');
        $this->line('=======================');

        $status = $this->pipelineService->getPipelineStatus();

        // System Status
        $this->table(
            ['Component', 'Status', 'Details'],
            [
                [
                    'Google Cloud NLP',
                    $status['google_nlp_status']['status'],
                    'Response: ' . ($status['google_nlp_status']['response_time'] ?? 0) . 'ms'
                ],
                [
                    'Queue System',
                    $status['queue_status']['status'],
                    'Sentiment Queue: ' . ($status['queue_status']['sentiment_queue_size'] ?? 0) . ' jobs'
                ],
                [
                    'Database',
                    $status['database_status']['status'],
                    'Recent Aggregates: ' . ($status['database_status']['recent_aggregates_count'] ?? 0)
                ],
            ]
        );

        // Recent Activity
        $this->newLine();
        $this->info('📈 Recent Activity (24h):');
        $activity = $status['recent_activity'];
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Texts Processed', $activity['total_texts_processed'] ?? 0],
                ['Successful Aggregations', $activity['successful_aggregations'] ?? 0],
                ['Failed Operations', $activity['failed_operations'] ?? 0],
                ['Average Processing Time', round($activity['average_processing_time'] ?? 0, 3) . 's'],
            ]
        );

        return 0;
    }

    private function showMonitoring(): int
    {
        $this->info('📊 Pipeline Monitoring Dashboard');
        $this->line('==============================');

        $performance = $this->pipelineService->getPerformanceMetrics();
        
        // Today's Performance
        $this->info('📅 Today\'s Performance:');
        $today = $performance['today'] ?? [];
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Aggregates', $today['total_aggregates'] ?? 0],
                ['Posts Analyzed', $today['total_posts_analyzed'] ?? 0],
                ['Average Sentiment', round($today['average_sentiment'] ?? 0, 3)],
                ['Platforms Covered', $today['platforms_covered'] ?? 0],
                ['Processing Rate', round($today['processing_rate'] ?? 0, 1) . '%'],
            ]
        );

        // Weekly Trend
        $this->newLine();
        $this->info('📈 Weekly Trend Analysis:');
        $weekly = $performance['weekly_trend'] ?? [];
        
        if (isset($weekly['trend_direction'])) {
            $trendIcon = match($weekly['trend_direction']) {
                'increasing' => '📈',
                'decreasing' => '📉',
                'stable' => '➡️',
                default => '❓'
            };
            
            $this->line("{$trendIcon} Trend Direction: {$weekly['trend_direction']}");
        }

        return 0;
    }

    private function showCostEstimation(): int
    {
        $this->info('💰 Cost Estimation Calculator');
        $this->line('============================');

        $textCount = (int) $this->ask('Number of texts to process', '100');
        $enableEntities = $this->confirm('Enable entity analysis?', true);
        $enableClassification = $this->confirm('Enable content classification?', true);

        $sampleTexts = array_fill(0, $textCount, 'Sample text for cost estimation purposes.');
        
        $estimate = $this->pipelineService->estimateProcessingCost($sampleTexts, [
            'enable_entities' => $enableEntities,
            'enable_classification' => $enableClassification,
        ]);

        $this->table(
            ['Service', 'Cost'],
            [
                ['Sentiment Analysis', '$' . $estimate['breakdown']['sentiment_analysis']],
                ['Entity Analysis', '$' . $estimate['breakdown']['entity_analysis']],
                ['Classification', '$' . $estimate['breakdown']['classification']],
                ['TOTAL', '$' . $estimate['total_estimated_cost']],
            ]
        );

        $this->newLine();
        $this->info('📊 Projections:');
        $this->line('• Daily (10x): $' . round($estimate['total_estimated_cost'] * 10, 4));
        $this->line('• Weekly (70x): $' . round($estimate['total_estimated_cost'] * 70, 4));
        $this->line('• Monthly (300x): $' . round($estimate['total_estimated_cost'] * 300, 4));

        return 0;
    }
}
