# 🕷️ Crawler Micro-Service Deployment Guide

## 🎯 **Complete Implementation Summary**

**Status**: ✅ **FULLY IMPLEMENTED** with dual deployment architecture

Your crawler micro-service is **production-ready** with both **Laravel Octane** and **Python Lambda** implementations that pull data from Twitter/X, Reddit, and Telegram using sophisticated keyword rules.

## 🚀 **Quick Start**

### **Option 1: Laravel Octane Deployment (Recommended)**

#### **1. Install Dependencies**
```bash
# Install Octane
composer require laravel/octane

# Install Swoole (for high performance)
composer require laravel/octane spiral/roadrunner

# Configure Octane
php artisan octane:install --server=swoole
```

#### **2. Configure API Credentials**
```bash
# Edit .env file
CRAWLER_ENABLED=true
CRAWLER_DEPLOYMENT_MODE=octane

# Twitter/X API v2
TWITTER_ENABLED=true
TWITTER_BEARER_TOKEN=your_bearer_token
TWITTER_API_KEY=your_api_key
TWITTER_API_SECRET=your_api_secret

# Reddit API
REDDIT_ENABLED=true
REDDIT_CLIENT_ID=your_client_id
REDDIT_CLIENT_SECRET=your_client_secret
REDDIT_USERNAME=your_username
REDDIT_PASSWORD=your_password

# Telegram Bot API
TELEGRAM_ENABLED=true
TELEGRAM_BOT_TOKEN=your_bot_token
TELEGRAM_CHANNELS=@cryptonews,@ethereum,@defi_news
```

#### **3. Test Configuration**
```bash
# Test API connections
php artisan crawler:config test

# View current configuration
php artisan crawler:config show

# Test crawler functionality
php artisan crawler:test --platforms=twitter,reddit --keywords="blockchain,defi" --dry-run
```

#### **4. Start Octane Server**
```bash
# Start with crawler workers
php artisan octane:start --workers=4 --task-workers=2 --port=8000

# Or with Docker
docker-compose up -d octane-crawler
```

#### **5. Run Crawler**
```bash
# Start crawling
php artisan crawler:start

# Monitor status
php artisan crawler:status

# View analytics
php artisan crawler:dashboard
```

### **Option 2: Python Lambda Deployment**

#### **1. Prepare Lambda Environment**
```bash
cd lambda/crawler_microservice

# Install Serverless Framework
npm install -g serverless

# Install Python dependencies
pip install -r requirements.txt
```

#### **2. Configure Environment Variables**
```bash
# Set environment variables
export TWITTER_BEARER_TOKEN=your_bearer_token
export REDDIT_CLIENT_ID=your_client_id
export REDDIT_CLIENT_SECRET=your_client_secret
export TELEGRAM_BOT_TOKEN=your_bot_token
export DYNAMODB_TABLE=social-media-posts
export SNS_TOPIC_ARN=arn:aws:sns:region:account:topic
```

#### **3. Deploy to AWS Lambda**
```bash
# Deploy using Serverless
serverless deploy --stage prod --region us-east-1

# Or use deployment script
./deploy.sh production us-east-1
```

#### **4. Test Lambda Function**
```bash
# Test via AWS CLI
aws lambda invoke \
  --function-name social-crawler-microservice-prod-crawler \
  --payload '{"platforms":["twitter","reddit"],"keyword_rules":["blockchain","defi"],"max_posts":20}' \
  response.json

# View response
cat response.json
```

## 🛠️ **System Architecture**

### **Laravel Octane Implementation**
```
┌─────────────────────────────────────────────────────────────────┐
│                    Laravel Octane Crawler                      │
├─────────────────────────────────────────────────────────────────┤
│ ┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐   │
│ │   Twitter API   │ │   Reddit API    │ │  Telegram API   │   │
│ │     Crawler     │ │    Crawler      │ │    Crawler      │   │
│ └─────────────────┘ └─────────────────┘ └─────────────────┘   │
│            │                 │                 │              │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │         Octane Concurrent Task Processing                   │ │
│ │    Octane::concurrently($tasks) - Parallel Execution       │ │
│ └─────────────────────────────────────────────────────────────┘ │
│            │                                                   │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │              Keyword Rule Engine                           │ │
│ │  • CrawlerKeywordRule Model                               │ │
│ │  • Scheduling & Filtering                                 │ │
│ │  • Sentiment Analysis Integration                         │ │
│ └─────────────────────────────────────────────────────────────┘ │
│            │                                                   │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │              PostgreSQL Storage                            │ │
│ │  • SocialMediaPost Model                                  │ │
│ │  • CrawlerJobStatus Tracking                              │ │
│ │  • KeywordMatch Records                                   │ │
│ └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

### **Python Lambda Implementation**
```
┌─────────────────────────────────────────────────────────────────┐
│                     AWS Lambda Crawler                         │
├─────────────────────────────────────────────────────────────────┤
│ ┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐   │
│ │   Tweepy API    │ │    PRAW API     │ │  Telegram Bot   │   │
│ │   (Twitter)     │ │   (Reddit)      │ │      API        │   │
│ └─────────────────┘ └─────────────────┘ └─────────────────┘   │
│            │                 │                 │              │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │         asyncio.gather() - Async Processing                 │ │
│ │    Concurrent async/await execution with aiohttp           │ │
│ └─────────────────────────────────────────────────────────────┘ │
│            │                                                   │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │                Job Configuration                           │ │
│ │  • CrawlJob dataclass                                     │ │
│ │  • Environment-based config                              │ │
│ │  • Proxy support (SOCKS5)                                │ │
│ └─────────────────────────────────────────────────────────────┘ │
│            │                                                   │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │              AWS Services Integration                      │ │
│ │  • DynamoDB for post storage                              │ │
│ │  • SNS for notifications                                  │ │
│ │  • CloudWatch for monitoring                              │ │
│ └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

## 📊 **Feature Comparison**

| Feature | Laravel Octane | Python Lambda | Notes |
|---------|---------------|---------------|-------|
| **Deployment** | Server-based | Serverless | Octane = persistent, Lambda = event-driven |
| **Concurrency** | Octane tasks | asyncio | Both support parallel processing |
| **Cost Model** | Fixed server costs | Pay-per-execution | Lambda more cost-effective for intermittent use |
| **Latency** | Lower (persistent) | Higher (cold starts) | Octane better for real-time scenarios |
| **Scalability** | Horizontal workers | Auto-scaling | Lambda scales automatically |
| **Database** | PostgreSQL | DynamoDB | Octane uses relational, Lambda uses NoSQL |
| **Monitoring** | Laravel dashboard | CloudWatch | Both have comprehensive monitoring |
| **API Integration** | Native Laravel | AWS SDK | Both support all required APIs |

## 🔧 **Configuration Examples**

### **Keyword Rules Configuration**
```php
// Create via Artisan command
php artisan tinker

// Create keyword rules
CrawlerKeywordRule::create([
    'name' => 'Blockchain Security',
    'keywords' => ['smart contract', 'vulnerability', 'hack', 'exploit'],
    'platforms' => ['twitter', 'reddit'],
    'priority' => 'high',
    'is_active' => true,
    'max_results' => 200,
    'sentiment_filter' => ['negative', 'neutral'],
    'schedule_config' => [
        'interval_minutes' => 30
    ]
]);

CrawlerKeywordRule::create([
    'name' => 'DeFi Protocols', 
    'keywords' => ['defi', 'yield farming', 'liquidity pool', 'uniswap'],
    'platforms' => ['twitter', 'reddit', 'telegram'],
    'priority' => 'medium',
    'is_active' => true,
    'max_results' => 150,
    'schedule_config' => [
        'hours' => [9, 12, 15, 18], // 4 times daily
        'days_of_week' => [1, 2, 3, 4, 5] // Weekdays only
    ]
]);
```

### **Rate Limiting Configuration**
```php
// config/crawler_microservice.php
'rate_limits' => [
    'twitter' => [
        'requests_per_minute' => 300,
        'burst_limit' => 450,
        'cooldown_seconds' => 60,
    ],
    'reddit' => [
        'requests_per_minute' => 100,
        'burst_limit' => 150,
        'cooldown_seconds' => 60,
    ],
    'telegram' => [
        'requests_per_minute' => 30,
        'burst_limit' => 45,
        'cooldown_seconds' => 120,
    ],
],
```

### **Platform-Specific Settings**
```php
'twitter' => [
    'enabled' => true,
    'api_version' => 'v2',
    'search_params' => [
        'max_results' => 100,
        'tweet_fields' => 'created_at,author_id,public_metrics,context_annotations',
    ],
    'filters' => [
        'min_retweets' => 5,
        'verified_only' => false,
        'exclude_retweets' => true,
        'language' => 'en',
    ],
],

'reddit' => [
    'enabled' => true,
    'target_subreddits' => [
        'CryptoCurrency', 'ethereum', 'defi', 'NFT', 'Bitcoin',
        'altcoin', 'smartcontracts', 'blockchain', 'web3'
    ],
    'search_params' => [
        'limit' => 100,
        'sort' => 'new',
        'time' => 'day',
    ],
],

'telegram' => [
    'enabled' => true,
    'target_channels' => [
        '@cryptonews', '@ethereum', '@defi_news', '@nftcommunity',
        '@blockchain_news', '@altcoins', '@cryptotrading'
    ],
],
```

## 📈 **Monitoring & Analytics**

### **Available Metrics**
- **Posts per hour** by platform
- **Keyword match rates**
- **API quota usage**
- **Response times** and latency
- **Error rates** and retry statistics
- **Sentiment analysis** results
- **Cache hit rates**
- **Worker performance**

### **Monitoring Commands**
```bash
# Live analytics dashboard
php artisan crawler:dashboard

# System health check
php artisan crawler:status

# Platform-specific monitoring
php artisan social:monitor

# Performance analysis
php artisan social:status
```

### **API Monitoring Endpoints**
```bash
# System status
GET /api/crawler/status

# Job monitoring
GET /api/crawler/jobs/{id}/status

# Platform health
GET /api/crawler/platforms/health

# Analytics data
GET /api/crawler/analytics?period=24h
```

## 🔧 **Troubleshooting**

### **Common Issues**

#### **API Authentication Failures**
```bash
# Test API credentials
php artisan crawler:config test --platform=twitter
php artisan crawler:config test --platform=reddit  
php artisan crawler:config test --platform=telegram
```

#### **Rate Limit Issues**
```bash
# Check current rate limit status
php artisan social:monitor

# Adjust rate limits in config
# config/crawler_microservice.php
'rate_limits' => [
    'twitter' => ['requests_per_minute' => 200], // Reduce from 300
]
```

#### **Database Connection Issues**
```bash
# Run migrations
php artisan migrate

# Check database connection
php artisan tinker
>>> DB::connection()->getPdo();
```

#### **Octane Performance Issues**
```bash
# Restart Octane workers
php artisan octane:reload

# Increase worker count
php artisan octane:start --workers=8 --task-workers=4
```

### **Lambda-Specific Issues**

#### **Cold Start Optimization**
```python
# Use provisioned concurrency
# serverless.yml
functions:
  crawler:
    provisionedConcurrency: 2
```

#### **Memory/Timeout Issues**
```yaml
# Increase Lambda resources
provider:
  memorySize: 2048  # Increase from 1024
  timeout: 900      # Max 15 minutes
```

## 🚀 **Production Deployment Checklist**

### **Pre-Deployment**
- [ ] Configure all API credentials
- [ ] Test rate limits and quotas
- [ ] Set up monitoring and alerting
- [ ] Configure proxy if needed
- [ ] Run comprehensive tests
- [ ] Set up database (Octane) or DynamoDB (Lambda)

### **Security**
- [ ] Rotate API keys regularly
- [ ] Use environment variables for secrets
- [ ] Configure SOCKS5 proxy for network restrictions
- [ ] Enable rate limiting protection
- [ ] Set up content filtering

### **Performance**
- [ ] Configure appropriate worker counts
- [ ] Enable caching (Redis recommended)
- [ ] Set up monitoring dashboards
- [ ] Configure auto-scaling (Lambda) or load balancing (Octane)
- [ ] Optimize database queries and indexes

### **Monitoring**
- [ ] Set up alerting for API failures
- [ ] Monitor rate limit thresholds
- [ ] Track job success rates
- [ ] Monitor system resource usage
- [ ] Set up log aggregation

## 📞 **Support & Documentation**

### **Available Commands**
```bash
# Full command list
php artisan list | grep crawler
php artisan list | grep social

# Get help for any command
php artisan crawler:start --help
php artisan social:crawl --help
```

### **Configuration Files**
- `config/crawler_microservice.php` - Main crawler configuration
- `config/social_crawler.php` - Platform-specific settings
- `lambda/crawler_microservice/serverless.yml` - Lambda deployment config
- `lambda/crawler_microservice/requirements.txt` - Python dependencies

### **Log Files**
- Laravel logs: `storage/logs/laravel.log`
- Crawler-specific: `storage/logs/crawler.log`
- Lambda logs: CloudWatch Logs

## 🎉 **Success!**

Your crawler micro-service is **fully operational** and ready for production deployment with:

✅ **Dual Architecture**: Both Octane and Lambda implementations  
✅ **Multi-Platform Support**: Twitter/X, Reddit, Telegram  
✅ **Advanced Keyword Engine**: Sophisticated filtering and scheduling  
✅ **High Performance**: Concurrent/async processing  
✅ **Production Ready**: Monitoring, rate limiting, error handling  
✅ **Scalable Design**: Auto-scaling and load balancing  
✅ **Comprehensive Management**: CLI tools and APIs  

**Choose your deployment method and start crawling social media with intelligent keyword rules!** 🕷️🚀✨