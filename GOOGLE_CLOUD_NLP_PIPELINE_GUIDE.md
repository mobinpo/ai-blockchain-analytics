# 🤖 Google Cloud NLP Pipeline Guide

**Streamlined Pipeline: Text → Google Cloud NLP (batch sentiment) → Daily aggregates**

## 🎯 Overview

This pipeline provides a complete solution for processing text through Google Cloud NLP for sentiment analysis and automatically storing daily aggregates. Perfect for analyzing social media posts, news articles, or any text data at scale.

## 🏗️ Architecture

```
📝 Input Text → 🤖 Google Cloud NLP → 💾 Batch Storage → 📊 Daily Aggregates
```

### Core Components

1. **`GoogleCloudBatchProcessor`** - Main processing engine
2. **`ProcessTextThroughNLPPipeline`** - Async job for queue processing  
3. **`ProcessTextNLPPipeline`** - CLI command interface
4. **`GoogleCloudNLPController`** - REST API endpoints
5. **Existing Models** - `SentimentBatch`, `SentimentBatchDocument`, `DailySentimentAggregate`

## 🚀 Quick Start

### 1. **CLI Processing**

```bash
# Process single text
docker compose exec app php artisan nlp:process-text --text="Bitcoin is going to the moon! 🚀"

# Process from file (one text per line)
docker compose exec app php artisan nlp:process-text --file=texts.txt --platform=twitter --category=crypto

# Process asynchronously with custom settings
docker compose exec app php artisan nlp:process-text \
  --file=large_dataset.txt \
  --platform=reddit \
  --category=blockchain \
  --language=en \
  --async \
  --chunk-size=50 \
  --aggregates

# Interactive mode (no file/text specified)
docker compose exec app php artisan nlp:process-text
```

### 2. **API Processing**

```bash
# Process multiple texts
curl -X POST "http://localhost:8003/api/google-nlp/process-texts" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "texts": [
      "Bitcoin is revolutionary technology!",
      "Crypto markets are very volatile today",
      "DeFi protocols are changing finance"
    ],
    "platform": "twitter",
    "category": "cryptocurrency",
    "language": "en",
    "async": false,
    "generate_aggregates": true
  }'

# Process single text
curl -X POST "http://localhost:8003/api/google-nlp/process-single" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "text": "Ethereum 2.0 staking rewards are amazing!",
    "platform": "reddit",
    "category": "ethereum"
  }'

# Get batch status
curl "http://localhost:8003/api/google-nlp/batch/123/status" \
  -H "Authorization: Bearer YOUR_TOKEN"

# Get daily aggregates
curl "http://localhost:8003/api/google-nlp/daily-aggregates?start_date=2025-01-01&end_date=2025-01-31&platform=twitter" \
  -H "Authorization: Bearer YOUR_TOKEN"

# Check health
curl "http://localhost:8003/api/google-nlp/health" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 3. **Queue Processing**

```bash
# Start queue worker for async processing
docker compose exec app php artisan queue:work

# Or use Horizon for better monitoring
docker compose exec app php artisan horizon
```

## 📊 Processing Flow

### Synchronous Processing
```php
use App\Services\SentimentPipeline\GoogleCloudBatchProcessor;

$processor = app(GoogleCloudBatchProcessor::class);

$texts = [
    "Bitcoin is the future of money!",
    "Crypto regulations are concerning",
    "DeFi yields are attractive"
];

$metadata = [
    'platform' => 'twitter',
    'keyword_category' => 'cryptocurrency',
    'language' => 'en',
    'batch_name' => 'crypto_sentiment_analysis'
];

$result = $processor->processTextToDailyAggregates($texts, $metadata, true);

// Result contains:
// - batch_id: Database ID for tracking
// - processed_count: Number of texts processed
// - sentiment_results: Individual sentiment scores
// - daily_aggregates: Generated aggregate records
// - execution_time_ms: Processing time
```

### Asynchronous Processing
```php
use App\Jobs\ProcessTextThroughNLPPipeline;

ProcessTextThroughNLPPipeline::dispatch($texts, $metadata, true);
```

### Large Batch Processing
```php
// For processing thousands of texts efficiently
$result = $processor->processLargeBatch($texts, $metadata, $chunkSize = 100);
```

## 📈 Daily Aggregates

The pipeline automatically generates daily aggregates with these metrics:

```json
{
  "date": "2025-01-15",
  "platform": "twitter", 
  "category": "cryptocurrency",
  "total_documents": 150,
  "avg_sentiment_score": 0.23,
  "avg_magnitude": 0.67,
  "positive_count": 85,
  "negative_count": 35,
  "neutral_count": 25,
  "mixed_count": 5,
  "min_sentiment": -0.8,
  "max_sentiment": 0.9
}
```

### Querying Aggregates

```php
use Carbon\Carbon;

$processor = app(GoogleCloudBatchProcessor::class);

// Get aggregates for date range
$aggregates = $processor->getDailyAggregates(
    Carbon::parse('2025-01-01'),
    Carbon::parse('2025-01-31'),
    'twitter',      // platform filter
    'cryptocurrency' // category filter
);

// Via API
GET /api/google-nlp/daily-aggregates?start_date=2025-01-01&end_date=2025-01-31&platform=twitter&category=crypto
```

## ⚙️ Configuration

### Environment Variables

```env
# Google Cloud NLP
GOOGLE_CLOUD_PROJECT_ID=your-project-id
GOOGLE_APPLICATION_CREDENTIALS=/path/to/credentials.json

# Queue Configuration
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Rate Limiting
GOOGLE_NLP_RATE_LIMIT=1000  # requests per minute
GOOGLE_NLP_BATCH_SIZE=25    # texts per batch
```

### Google Cloud Setup

1. **Create Google Cloud Project**
2. **Enable Cloud Natural Language API**
3. **Create Service Account** with Language AI permissions
4. **Download JSON credentials** and set path in env
5. **Set project ID** in environment variables

## 📊 Monitoring & Analytics

### Batch Status Tracking

```php
$status = $processor->getBatchStatus($batchId);

// Returns:
// - batch_id, name, status
// - total_documents, processed_documents, failed_documents  
// - progress_percentage
// - started_at, completed_at
// - processing_summary with success rates
```

### Health Monitoring

```bash
# Check pipeline health
curl "http://localhost:8003/api/google-nlp/health"

# Response:
{
  "success": true,
  "data": {
    "status": "healthy",
    "timestamp": "2025-01-15T10:30:00Z",
    "google_cloud_nlp": "available",
    "database": "connected", 
    "queue": "running"
  }
}
```

### Performance Metrics

The system tracks:
- **Processing times** per batch
- **Success/failure rates** 
- **API quota usage**
- **Queue processing times**
- **Daily aggregate generation**

## 🔧 Advanced Usage

### Custom Metadata

```php
$metadata = [
    'platform' => 'custom_source',
    'keyword_category' => 'specific_topic',
    'language' => 'en',
    'batch_name' => 'analysis_2025_01_15',
    'description' => 'Weekly sentiment analysis',
    'source' => 'automated_crawler',
    'custom_field' => 'additional_data'
];
```

### Error Handling

```php
try {
    $result = $processor->processTextToDailyAggregates($texts, $metadata);
} catch (\Exception $e) {
    Log::error('NLP processing failed', [
        'error' => $e->getMessage(),
        'text_count' => count($texts)
    ]);
    
    // Fallback processing or notification
}
```

### Batch Size Optimization

```php
// For rate limit compliance
$smallBatches = $processor->processLargeBatch($texts, $metadata, 25);

// For faster processing (if quota allows)
$largeBatches = $processor->processLargeBatch($texts, $metadata, 100);
```

## 📋 API Reference

### Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/google-nlp/process-texts` | Process multiple texts |
| `POST` | `/api/google-nlp/process-single` | Process single text |
| `GET` | `/api/google-nlp/batch/{id}/status` | Get batch status |
| `GET` | `/api/google-nlp/daily-aggregates` | Get daily aggregates |
| `GET` | `/api/google-nlp/health` | Health check |

### Request/Response Examples

#### Process Texts
```json
// Request
{
  "texts": ["Text 1", "Text 2"],
  "platform": "twitter",
  "category": "crypto",
  "language": "en",
  "async": false,
  "generate_aggregates": true
}

// Response
{
  "success": true,
  "message": "Texts processed successfully",
  "data": {
    "batch_id": 123,
    "processed_count": 2,
    "execution_time_ms": 1250,
    "sentiment_summary": {
      "total_analyzed": 2,
      "sentiment_distribution": {
        "positive": 1,
        "neutral": 1
      },
      "average_score": 0.15
    },
    "aggregates_created": 1
  }
}
```

## 🚨 Error Handling

### Common Issues

1. **Google Cloud NLP not available**
   - Check credentials and project setup
   - Verify API is enabled
   - Check network connectivity

2. **Rate limit exceeded**
   - Reduce batch size
   - Add delays between batches
   - Check quota limits

3. **Queue processing fails**
   - Ensure Redis is running
   - Check queue worker status
   - Monitor job failures

### Logging

All operations are logged with context:

```php
Log::info('NLP pipeline started', [
    'text_count' => count($texts),
    'platform' => $metadata['platform'],
    'batch_name' => $metadata['batch_name']
]);

Log::error('Processing failed', [
    'error' => $exception->getMessage(),
    'batch_id' => $batchId
]);
```

## 🎯 Use Cases

### 1. **Social Media Monitoring**
```bash
# Process Twitter mentions
php artisan nlp:process-text \
  --file=twitter_mentions.txt \
  --platform=twitter \
  --category=brand_mentions \
  --async
```

### 2. **News Sentiment Analysis**
```bash
# Process news articles
php artisan nlp:process-text \
  --file=news_headlines.txt \
  --platform=news \
  --category=market_news \
  --language=en
```

### 3. **Customer Feedback Analysis**
```bash
# Process customer reviews
php artisan nlp:process-text \
  --file=customer_reviews.txt \
  --platform=reviews \
  --category=product_feedback
```

### 4. **Cryptocurrency Sentiment**
```bash
# Process crypto discussions
php artisan nlp:process-text \
  --file=crypto_discussions.txt \
  --platform=reddit \
  --category=cryptocurrency \
  --chunk-size=50
```

## 📊 Integration Examples

### With Existing Sentiment Pipeline

```php
// Use with existing sentiment analysis
$existingPipeline = app(SentimentPipelineService::class);
$googleCloudProcessor = app(GoogleCloudBatchProcessor::class);

// Process through both for comparison
$existingResults = $existingPipeline->processTexts($texts);
$googleResults = $googleCloudProcessor->processTextToDailyAggregates($texts);
```

### With Crawler Integration

```php
// Process crawler results through NLP
$crawlerResults = $crawlerService->crawlPlatform('twitter', $keywords);
$texts = array_column($crawlerResults, 'content');

ProcessTextThroughNLPPipeline::dispatch($texts, [
    'platform' => 'twitter',
    'keyword_category' => 'cryptocurrency',
    'source' => 'automated_crawler'
]);
```

## 🔄 Maintenance

### Cleanup Old Data

```php
// Clean up old batches (older than 30 days)
SentimentBatch::where('created_at', '<', now()->subDays(30))->delete();

// Clean up old aggregates (older than 1 year)  
DailySentimentAggregate::where('date', '<', now()->subYear())->delete();
```

### Monitor Queue Health

```bash
# Check queue status
php artisan queue:monitor

# Restart failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush
```

---

## 🎉 Summary

This Google Cloud NLP Pipeline provides:

✅ **Streamlined Processing** - Text → NLP → Aggregates in one flow  
✅ **Batch Processing** - Handle thousands of texts efficiently  
✅ **Async Support** - Queue-based processing for large datasets  
✅ **Daily Aggregates** - Automatic statistical summaries  
✅ **REST API** - Easy integration with external systems  
✅ **CLI Commands** - Command-line processing tools  
✅ **Monitoring** - Health checks and status tracking  
✅ **Error Handling** - Robust error handling and logging  

**Perfect for sentiment analysis at scale!** 🚀