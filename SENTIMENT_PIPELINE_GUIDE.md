# Sentiment Pipeline Guide

A comprehensive text → Google Cloud NLP (batch sentiment) → daily aggregates pipeline for analyzing sentiment in social media posts and other text content.

## Overview

The sentiment pipeline processes text content through the following stages:

1. **Text Aggregation**: Collect and preprocess text from various sources
2. **Batch Creation**: Group texts into batches for efficient processing
3. **Sentiment Analysis**: Process batches using Google Cloud NLP API
4. **Daily Aggregation**: Calculate and store daily sentiment summaries
5. **Monitoring & Alerts**: Track pipeline performance and detect anomalies

## Architecture

### Core Components

#### Services
- **`TextPreprocessor`**: Cleans and normalizes text for analysis
- **`TextAggregator`**: Groups texts into processing batches
- **`GoogleSentimentService`**: Interfaces with Google Cloud NLP API
- **`SentimentBatchProcessor`**: Orchestrates batch processing
- **`DailySentimentAggregateService`**: Generates daily summary statistics

#### Models
- **`SentimentBatch`**: Tracks batch processing status and metadata
- **`SentimentBatchDocument`**: Individual text documents within batches
- **`DailySentimentAggregate`**: Daily sentiment summaries by platform/category
- **`TextPreprocessingCache`**: Caches preprocessed text to avoid reprocessing

#### Jobs
- **`SentimentPipelineJob`**: Queue-based batch processing
- **`SentimentPipelineBulkJob`**: Process multiple batches efficiently

## Setup

### Prerequisites

1. **Google Cloud NLP API**: Set up Google Cloud project and enable Natural Language API
2. **Credentials**: Download service account key and configure path in environment
3. **Queue Worker**: Configure Laravel Horizon or queue workers for background processing

### Environment Configuration

```bash
# Google Cloud NLP
GOOGLE_APPLICATION_CREDENTIALS=/path/to/service-account-key.json

# Queue Configuration
QUEUE_CONNECTION=redis
HORIZON_REDIS_CONNECTION=default
```

### Configuration

Edit `config/sentiment_pipeline.php` to customize:
- Text preprocessing rules
- Batch processing settings
- Google NLP API options
- Cost management limits
- Performance optimizations

## Usage

### Basic Workflow

#### 1. Create Sentiment Batch

```bash
# Create batch for yesterday (default)
php artisan sentiment:create-batch

# Create batch for specific date
php artisan sentiment:create-batch 2024-01-15

# Dry run to see what would be created
php artisan sentiment:create-batch 2024-01-15 --dry-run
```

#### 2. Process Sentiment Batch

```bash
# Process specific batch synchronously
php artisan sentiment:process-batch 123

# Process all pending batches
php artisan sentiment:process-batch --all

# Queue processing for background execution
php artisan sentiment:process-batch 123 --queue --aggregates
```

#### 3. Generate Daily Aggregates

```bash
# Generate aggregates for yesterday
php artisan sentiment:generate-aggregates

# Generate for specific date
php artisan sentiment:generate-aggregates 2024-01-15

# Generate for date range
php artisan sentiment:generate-aggregates --start-date=2024-01-01 --end-date=2024-01-15
```

#### 4. Monitor Pipeline Status

```bash
# Basic status overview
php artisan sentiment:status

# Detailed statistics
php artisan sentiment:status --detailed

# Recent activity (last 7 days)
php artisan sentiment:status --recent-days=7

# Perform data cleanup
php artisan sentiment:status --cleanup
```

### Programmatic Usage

#### Creating and Processing Batches

```php
use App\Services\SentimentPipeline\TextAggregator;
use App\Services\SentimentPipeline\SentimentBatchProcessor;
use Carbon\Carbon;

// Create a batch
$aggregator = app(TextAggregator::class);
$batch = $aggregator->createDailyBatch(Carbon::yesterday());

// Process the batch
$processor = app(SentimentBatchProcessor::class);
$results = $processor->processBatch($batch);

// Queue processing instead
SentimentPipelineJob::dispatch($batch->id, true); // true = generate aggregates
```

#### Accessing Results

```php
use App\Models\DailySentimentAggregate;
use Carbon\Carbon;

// Get daily aggregates
$aggregates = DailySentimentAggregate::forDate(Carbon::yesterday())
    ->forPlatform('twitter')
    ->forCategory('blockchain')
    ->fullDay()
    ->get();

// Get sentiment distribution
foreach ($aggregates as $aggregate) {
    $distribution = $aggregate->getSentimentDistribution();
    $percentages = $aggregate->getSentimentPercentages();
    $topKeywords = $aggregate->getTopKeywords(10);
}

// Get date range statistics
$stats = DailySentimentAggregate::getDateRangeStats(
    Carbon::now()->subDays(7),
    Carbon::now(),
    'twitter'
);
```

### Queue-Based Processing

#### Setup Horizon

```bash
# Install and configure Horizon
composer require laravel/horizon
php artisan horizon:install
php artisan horizon:publish
```

#### Configure Queues

```php
// config/horizon.php
'environments' => [
    'production' => [
        'sentiment-processing' => [
            'connection' => 'redis',
            'queue' => ['sentiment-processing'],
            'balance' => 'auto',
            'maxProcesses' => 3,
            'maxTime' => 60,
            'maxJobs' => 1000,
            'memory' => 512,
            'tries' => 3,
        ],
    ],
],
```

#### Start Processing

```bash
# Start Horizon
php artisan horizon

# Monitor in another terminal
php artisan horizon:status
```

## Data Flow

### 1. Text Input Sources

- **Social Media Posts**: Twitter, Reddit, Telegram content
- **News Articles**: Blockchain and crypto news
- **User Comments**: Comments on various platforms
- **Custom Text**: Any text content via API

### 2. Preprocessing Pipeline

```
Raw Text → Clean URLs/Emails → Remove Special Chars → Normalize Whitespace → Cache
```

### 3. Batch Processing

```
Preprocessed Text → Group by Date → Create Batch → Queue for Processing
```

### 4. Sentiment Analysis

```
Batch → Google NLP API → Extract Sentiment + Entities + Categories → Store Results
```

### 5. Daily Aggregation

```
Processed Documents → Group by Platform/Category → Calculate Metrics → Store Aggregates
```

## API Endpoints

### Create Batch via API

```http
POST /api/sentiment/batches
Content-Type: application/json

{
    "date": "2024-01-15",
    "process_immediately": false
}
```

### Process Batch

```http
POST /api/sentiment/batches/{id}/process
Content-Type: application/json

{
    "generate_aggregates": true
}
```

### Get Aggregates

```http
GET /api/sentiment/aggregates?date=2024-01-15&platform=twitter&category=blockchain
```

## Performance Considerations

### Optimization Tips

1. **Batch Size**: Keep batches between 50-200 documents for optimal processing
2. **Rate Limiting**: Google NLP has rate limits; adjust delay in configuration
3. **Caching**: Text preprocessing cache reduces redundant work
4. **Queue Workers**: Use multiple queue workers for parallel processing
5. **Database Indexing**: Ensure proper indexes on date and status columns

### Cost Management

```php
// Monitor costs
$totalCost = SentimentBatch::sum('processing_cost');
$dailyCost = SentimentBatch::whereDate('created_at', today())->sum('processing_cost');

// Set budget limits in config
'cost_management' => [
    'daily_budget_limit' => 50.0,
    'monthly_budget_limit' => 1000.0,
],
```

### Scaling Considerations

- **Horizontal Scaling**: Deploy multiple queue workers across servers
- **Database Optimization**: Consider read replicas for analytics queries
- **Caching**: Use Redis for frequently accessed aggregates
- **Archiving**: Move old data to cold storage to maintain performance

## Monitoring

### Key Metrics to Track

- **Processing Success Rate**: Should be > 95%
- **Average Processing Time**: Monitor for performance degradation
- **API Costs**: Track daily/monthly spending
- **Queue Depth**: Ensure queues don't back up
- **Error Rates**: Monitor and alert on failures

### Health Checks

```bash
# Check pipeline health
php artisan sentiment:status --detailed

# View recent failures
php artisan queue:failed

# Monitor queue status
php artisan horizon:status
```

### Alerting

Configure alerts for:
- High failure rates (> 10%)
- Processing delays (> 24 hours)
- Budget exceeded
- Unusual sentiment spikes
- Queue backlog

## Troubleshooting

### Common Issues

#### Google NLP API Errors

```bash
# Check credentials
export GOOGLE_APPLICATION_CREDENTIALS=/path/to/key.json
gcloud auth application-default print-access-token

# Test API access
php artisan test:google-nlp
```

#### Queue Processing Issues

```bash
# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear stuck jobs
php artisan queue:flush
```

#### Database Performance

```sql
-- Check for missing indexes
EXPLAIN SELECT * FROM sentiment_batch_documents WHERE processing_status = 'pending';

-- Monitor slow queries
SET slow_query_log = 1;
```

### Error Recovery

#### Batch Processing Failures

```php
// Retry failed batch
$batch = SentimentBatch::find($id);
$processor = app(SentimentBatchProcessor::class);
$results = $processor->retryFailedDocuments($batch);
```

#### Data Corruption

```bash
# Recreate batch from source data
php artisan sentiment:create-batch 2024-01-15 --force

# Regenerate aggregates
php artisan sentiment:generate-aggregates 2024-01-15 --force
```

## Best Practices

1. **Regular Monitoring**: Check pipeline status daily
2. **Incremental Processing**: Process recent data first, backfill historical data
3. **Error Handling**: Always implement retry logic with exponential backoff
4. **Cost Control**: Set and monitor budget limits
5. **Data Validation**: Validate text quality before processing
6. **Performance Testing**: Test with various batch sizes to find optimal configuration
7. **Backup Strategy**: Regular backups of processed sentiment data
8. **Documentation**: Keep track of configuration changes and their impact

## Integration Examples

### With Laravel Scheduler

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Create daily batches
    $schedule->command('sentiment:create-batch')
             ->daily()
             ->at('02:00');

    // Process pending batches
    $schedule->command('sentiment:process-batch --all --queue')
             ->hourly();

    // Generate aggregates
    $schedule->command('sentiment:generate-aggregates')
             ->daily()
             ->at('06:00');

    // Cleanup old data
    $schedule->command('sentiment:status --cleanup')
             ->weekly();
}
```

### With Custom Controllers

```php
class SentimentController extends Controller
{
    public function createBatch(Request $request)
    {
        $date = Carbon::parse($request->date);
        $aggregator = app(TextAggregator::class);
        $batch = $aggregator->createDailyBatch($date);
        
        if ($request->process_immediately) {
            SentimentPipelineJob::dispatch($batch->id, true);
        }
        
        return response()->json(['batch_id' => $batch->id]);
    }
}
```

This pipeline provides a robust, scalable solution for processing text sentiment at scale while maintaining cost control and monitoring capabilities.