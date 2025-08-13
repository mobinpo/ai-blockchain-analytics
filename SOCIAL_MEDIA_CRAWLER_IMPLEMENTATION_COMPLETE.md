# Social Media Crawler Micro-Service Implementation Complete

## Overview

Successfully implemented a comprehensive social media crawler micro-service that can run as either a Laravel Octane task or Python Lambda function to crawl Twitter/X, Reddit, and Telegram with configurable keyword rules.

## Architecture

### 🏗️ Dual Implementation
- **Laravel Octane Task**: For integrated server-side crawling
- **Python Lambda**: For serverless, scalable AWS deployment
- **Shared Database**: PostgreSQL with standardized schemas

### 🔧 Core Components

#### 1. Database Schema
```sql
-- Social Media Posts Table
CREATE TABLE social_media_posts (
    id BIGSERIAL PRIMARY KEY,
    platform VARCHAR(20) NOT NULL,
    platform_id VARCHAR NOT NULL UNIQUE,
    author VARCHAR NOT NULL,
    content TEXT NOT NULL,
    matched_keywords JSON NOT NULL,
    metadata JSON,
    sentiment_score DECIMAL(3,2),
    engagement_count INT DEFAULT 0,
    platform_created_at TIMESTAMP NOT NULL,
    crawled_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Crawler Rules Table
CREATE TABLE crawler_rules (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR NOT NULL,
    platforms JSON NOT NULL,
    keywords JSON NOT NULL,
    hashtags JSON,
    accounts JSON,
    sentiment_threshold INT,
    engagement_threshold INT DEFAULT 0,
    active BOOLEAN DEFAULT true,
    priority INT DEFAULT 1,
    filters JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### 2. Laravel Implementation

**Files Created:**
- `app/Services/SocialMediaCrawlerService.php` - Main crawler service
- `app/Console/Commands/SocialMediaCrawlerTask.php` - Octane task
- `app/Models/SocialMediaPost.php` - Post model with relationships
- `app/Models/CrawlerRule.php` - Rules model with scopes
- `app/Http/Controllers/Api/SocialMediaCrawlerController.php` - API controller
- `database/migrations/*_create_social_media_posts_table.php` - Database schema
- `database/migrations/*_create_crawler_rules_table.php` - Rules schema

**Key Features:**
```php
// Execute crawling
php artisan social-media:crawl

// Platform-specific crawling
php artisan social-media:crawl --platform=twitter

// API endpoints
GET /api/social-media - List posts
POST /api/social-media/crawl - Manual trigger
GET /api/social-media/rules - Manage rules
POST /api/social-media/rules - Create rule
```

#### 3. Python Lambda Implementation

**Files Created:**
- `lambda/social_media_crawler.py` - Async crawler with aiohttp
- `lambda/serverless.yml` - AWS deployment configuration
- `lambda/requirements.txt` - Python dependencies
- `lambda/deploy.sh` - Deployment script

**Lambda Features:**
- Async/await pattern for concurrent API calls
- DynamoDB for serverless data storage
- Scheduled execution (30-minute intervals)
- CloudWatch monitoring and dashboards
- Auto-scaling based on demand

## 🔌 Platform Integrations

### Twitter/X API v2
```python
# Search recent tweets
url = 'https://api.twitter.com/2/tweets/search/recent'
params = {
    'query': 'bitcoin OR ethereum -is:retweet lang:en',
    'tweet.fields': 'created_at,public_metrics,context_annotations',
    'max_results': 100
}
```

**Features:**
- Bearer token authentication
- Advanced search queries with operators
- Engagement metrics (likes, retweets, replies)
- Context annotations for topic detection
- Rate limiting compliance

### Reddit API
```python
# Search subreddits
url = f'https://www.reddit.com/r/{subreddit}/search.json'
params = {
    'q': keyword,
    'sort': 'new',
    'limit': 25,
    't': 'day'  # last day
}
```

**Features:**
- Public JSON API (no authentication required)
- Subreddit-specific searches
- Post and comment crawling
- Score and engagement metrics
- Time-based filtering

### Telegram API
```python
# Bot API for channel monitoring
url = f'https://api.telegram.org/bot{bot_token}/getUpdates'
```

**Features:**
- Bot API integration
- Channel monitoring (requires permissions)
- Message forwarding detection
- View counts and engagement

## 📊 Keyword Rules Engine

### Rule Configuration
```json
{
    "name": "Crypto General",
    "platforms": ["twitter", "reddit"],
    "keywords": ["bitcoin", "ethereum", "defi", "blockchain"],
    "hashtags": ["#btc", "#eth", "#defi"],
    "accounts": ["@VitalikButerin", "@elonmusk"],
    "sentiment_threshold": -20,
    "engagement_threshold": 5,
    "priority": 1,
    "filters": {
        "subreddits": ["cryptocurrency", "bitcoin", "ethereum"]
    }
}
```

### Matching Logic
- Case-insensitive keyword matching
- Hashtag extraction and matching
- Account/username filtering
- Sentiment-based filtering
- Engagement thresholds
- Priority-based execution

## 🚀 Deployment Options

### Laravel Octane Deployment
```bash
# Start Octane server
php artisan octane:start --server=roadrunner --port=8000

# Schedule crawler
php artisan schedule:run
# Or manual execution
php artisan social-media:crawl
```

### AWS Lambda Deployment
```bash
# Deploy to AWS
cd lambda
./deploy.sh production

# Monitor via CloudWatch
aws logs tail --follow /aws/lambda/social-media-crawler-production-crawl
```

### Docker Deployment
```yaml
# docker-compose.yml addition
services:
  social-crawler:
    build: .
    command: php artisan social-media:crawl
    volumes:
      - .:/app
    depends_on:
      - postgres
      - redis
```

## 📈 Performance & Monitoring

### Metrics Tracked
- Posts crawled per platform
- Keyword match rates
- API rate limit usage
- Processing latency
- Error rates by platform
- Storage utilization

### Monitoring Dashboards
```javascript
// CloudWatch Dashboard (Lambda)
{
  "widgets": [
    {
      "type": "metric",
      "properties": {
        "metrics": [
          ["AWS/Lambda", "Invocations", "FunctionName", "social-media-crawler"],
          ["AWS/Lambda", "Errors", "FunctionName", "social-media-crawler"],
          ["AWS/Lambda", "Duration", "FunctionName", "social-media-crawler"]
        ]
      }
    }
  ]
}
```

### Error Handling
- Exponential backoff for API failures
- Dead letter queues for failed jobs
- Circuit breaker pattern for platform outages
- Automatic retry with jitter
- Comprehensive logging and alerting

## 🔧 Configuration

### Environment Variables
```bash
# Twitter API
TWITTER_BEARER_TOKEN=your_bearer_token
TWITTER_API_KEY=your_api_key
TWITTER_API_SECRET=your_api_secret

# Reddit API (optional)
REDDIT_CLIENT_ID=your_client_id
REDDIT_CLIENT_SECRET=your_client_secret

# Telegram API
TELEGRAM_BOT_TOKEN=your_bot_token
TELEGRAM_WEBHOOK_URL=your_webhook_url

# AWS (for Lambda)
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=us-east-1
```

### Laravel Configuration
```php
// config/services.php
'twitter' => [
    'bearer_token' => env('TWITTER_BEARER_TOKEN'),
    'api_key' => env('TWITTER_API_KEY'),
    'api_secret' => env('TWITTER_API_SECRET'),
],
'reddit' => [
    'client_id' => env('REDDIT_CLIENT_ID'),
    'client_secret' => env('REDDIT_CLIENT_SECRET'),
    'user_agent' => env('REDDIT_USER_AGENT', 'AI-Blockchain-Crawler/1.0'),
],
'telegram' => [
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'webhook_url' => env('TELEGRAM_WEBHOOK_URL'),
],
```

## 📋 API Endpoints

### Social Media Posts
```http
# List posts with filtering
GET /api/social-media?platform=twitter&keyword=bitcoin&hours=24

# Get crawler statistics
GET /api/social-media/stats

# Manual crawling trigger
POST /api/social-media/crawl
```

### Crawler Rules Management
```http
# List active rules
GET /api/social-media/rules

# Create new rule
POST /api/social-media/rules
{
    "name": "DeFi Trends",
    "platforms": ["twitter", "reddit"],
    "keywords": ["defi", "yield farming", "liquidity"],
    "engagement_threshold": 10
}

# Update rule
PUT /api/social-media/rules/{id}

# Delete rule
DELETE /api/social-media/rules/{id}
```

## 🎯 Usage Examples

### Laravel Command
```bash
# Full crawling session
php artisan social-media:crawl

# Platform-specific crawling
php artisan social-media:crawl --platform=reddit

# Dry run mode
php artisan social-media:crawl --dry-run

# Specific rule execution
php artisan social-media:crawl --rule=5
```

### API Integration
```javascript
// JavaScript client example
const response = await fetch('/api/social-media/crawl', {
    method: 'POST',
    headers: {
        'Authorization': 'Bearer ' + token,
        'Content-Type': 'application/json'
    }
});

const result = await response.json();
console.log(`Crawled ${result.summary.total_posts} posts`);
```

### Lambda Testing
```bash
# Local testing
cd lambda
python social_media_crawler.py

# Remote invocation
serverless invoke -f crawl --stage production

# View logs
serverless logs -f crawl --stage production --tail
```

## 🔐 Security Considerations

### API Security
- Bearer token authentication for Twitter
- Rate limiting compliance (15-minute windows)
- User-Agent string identification
- Request throttling and circuit breakers
- Secure credential storage

### Data Protection
- No PII storage beyond usernames
- GDPR compliance for EU users
- Data retention policies
- Encrypted data transmission
- Audit logging for access

### Platform Compliance
- Twitter API Terms of Service adherence
- Reddit API usage guidelines
- Telegram Bot API best practices
- Respect for platform rate limits
- Ethical crawling practices

## 📚 Next Steps

### Enhancements
1. **Sentiment Integration**: Connect with existing sentiment pipeline
2. **Real-time Streaming**: WebSocket updates for live data
3. **ML-based Filtering**: Advanced content classification
4. **Multi-language Support**: International keyword matching
5. **Visual Content**: Image and video analysis

### Scaling Options
1. **Microservice Architecture**: Separate crawlers per platform
2. **Kubernetes Deployment**: Container orchestration
3. **Event-driven Processing**: SQS/SNS integration
4. **Caching Layer**: Redis for hot data
5. **Data Lake**: S3 for long-term storage

### Monitoring Improvements
1. **Custom Metrics**: Business KPIs tracking
2. **Alerting Rules**: Proactive issue detection
3. **Performance Profiling**: Bottleneck identification
4. **Cost Optimization**: Resource usage analysis
5. **SLA Monitoring**: Service level compliance

## ✅ Implementation Status

All core components implemented and tested:

- ✅ Database schemas and migrations
- ✅ Laravel Octane task implementation
- ✅ Python Lambda function
- ✅ Twitter/X API integration
- ✅ Reddit API integration
- ✅ Telegram API integration (framework)
- ✅ Keyword rules engine
- ✅ API controllers and routes
- ✅ AWS deployment configuration
- ✅ Monitoring and logging
- ✅ Error handling and resilience
- ✅ Documentation and examples

The social media crawler micro-service is production-ready and can be deployed immediately to either Laravel Octane or AWS Lambda environments.