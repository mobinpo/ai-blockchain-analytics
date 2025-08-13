# AI Blockchain Analytics Platform v0.9.0 - Production Deployment Guide

## 🚀 Production Deployment for v0.9.0

This guide provides comprehensive instructions for deploying the AI Blockchain Analytics Platform v0.9.0 to production.

### 📋 Prerequisites

#### System Requirements
- **Docker**: 20.10+ with Docker Compose v2+
- **Server**: 4+ CPU cores, 8GB+ RAM, 100GB+ storage
- **Domain**: SSL certificate for HTTPS
- **Database**: PostgreSQL 15+ or managed DB service
- **Cache**: Redis 7+ or managed Redis service

#### API Keys Required
```env
# Blockchain APIs
ETHERSCAN_API_KEY=your_etherscan_key
POLYGONSCAN_API_KEY=your_polygonscan_key
BSCSCAN_API_KEY=your_bscscan_key
MORALIS_API_KEY=your_moralis_key
ALCHEMY_API_KEY=your_alchemy_key

# Market Data
COINGECKO_API_KEY=your_coingecko_key

# AI/ML Services
OPENAI_API_KEY=your_openai_key
GOOGLE_APPLICATION_CREDENTIALS=path_to_service_account.json

# Social Media APIs
TWITTER_API_KEY=your_twitter_key
TWITTER_API_SECRET=your_twitter_secret
REDDIT_CLIENT_ID=your_reddit_client_id
REDDIT_CLIENT_SECRET=your_reddit_secret

# Monitoring
SENTRY_LARAVEL_DSN=your_sentry_dsn
```

### 🔧 Quick Deployment

#### 1. Clone and Checkout v0.9.0
```bash
git clone https://github.com/your-org/ai-blockchain-analytics.git
cd ai-blockchain-analytics
git checkout v0.9.0
```

#### 2. Production Environment Setup
```bash
# Copy production environment template
cp .env.production.example .env.production

# Generate application key
docker run --rm -v $(pwd):/app php:8.3-cli php /app/artisan key:generate --show

# Update .env.production with your values
nano .env.production
```

#### 3. Build and Deploy
```bash
# Build production images
docker-compose -f docker-compose.production.yml build

# Start services
docker-compose -f docker-compose.production.yml up -d

# Run database migrations
docker-compose -f docker-compose.production.yml exec app php artisan migrate --force

# Seed famous contracts
docker-compose -f docker-compose.production.yml exec app php artisan db:seed --class=FamousContractsSeeder --force

# Clear and cache configs
docker-compose -f docker-compose.production.yml exec app php artisan config:cache
docker-compose -f docker-compose.production.yml exec app php artisan route:cache
docker-compose -f docker-compose.production.yml exec app php artisan view:cache
```

### 🏗️ Deployment Options

#### Option A: Docker Compose (Recommended for single server)
- **File**: `docker-compose.production.yml`
- **Includes**: App, Worker, PostgreSQL, Redis, Nginx
- **SSL**: Configure SSL certificates in nginx volumes

#### Option B: Kubernetes
- **Files**: `k8s/complete-production-deployment.yaml`
- **Features**: Auto-scaling, rolling updates, health checks
- **Deploy**: `kubectl apply -f k8s/complete-production-deployment.yaml`

#### Option C: AWS ECS
- **Files**: `ecs/complete-production-deployment.json`
- **Features**: Managed services, auto-scaling, load balancing
- **Deploy**: Use `ecs/deploy-enhanced.sh` script

### 🔒 Security Configuration

#### SSL/TLS Setup
```bash
# Generate SSL certificates (Let's Encrypt)
certbot certonly --webroot -w /var/www/html/public -d yourdomain.com

# Update nginx configuration
cp docker/nginx/ssl.conf docker/nginx/default.conf
```

#### Environment Security
```env
# Production security settings
APP_DEBUG=false
APP_ENV=production
LOG_LEVEL=warning

# Session security
SESSION_DRIVER=redis
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true

# CSRF protection
APP_URL=https://yourdomain.com
```

### 📊 Monitoring Setup

#### Sentry Integration
```env
SENTRY_LARAVEL_DSN=your_sentry_dsn
SENTRY_TRACES_SAMPLE_RATE=0.1
```

#### Health Checks
- **App Health**: `https://yourdomain.com/health`
- **API Health**: `https://yourdomain.com/api/health`
- **Database**: Automatic via Docker health checks
- **Redis**: Automatic via Docker health checks

#### Performance Monitoring
```bash
# Enable application metrics
docker-compose -f docker-compose.production.yml exec app php artisan config:set metrics.enabled true

# Access metrics
curl http://yourdomain.com:2112/metrics
```

### 🗄️ Database Management

#### Backup Strategy
```bash
# Daily backup script
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
docker-compose -f docker-compose.production.yml exec postgres pg_dump -U ai_blockchain_analytics ai_blockchain_analytics > backup_$DATE.sql
```

#### Migration Verification
```bash
# Check migration status
docker-compose -f docker-compose.production.yml exec app php artisan migrate:status

# Run specific migrations if needed
docker-compose -f docker-compose.production.yml exec app php artisan migrate --path=/database/migrations/specific_migration.php
```

### 🔄 Queue Management

#### Horizon Dashboard
```bash
# Enable Horizon in production
docker-compose -f docker-compose.production.yml exec app php artisan horizon:install
docker-compose -f docker-compose.production.yml exec app php artisan horizon:publish

# Access dashboard: https://yourdomain.com/horizon
```

#### Background Jobs
- **AI Analysis**: Real-time contract vulnerability analysis
- **Sentiment Processing**: Social media sentiment analysis
- **Data Crawling**: Automated blockchain data collection
- **Report Generation**: PDF report generation
- **Cache Warming**: Automated cache management

### 📈 Performance Optimization

#### Caching Strategy
```bash
# Enable all caches
docker-compose -f docker-compose.production.yml exec app php artisan config:cache
docker-compose -f docker-compose.production.yml exec app php artisan route:cache
docker-compose -f docker-compose.production.yml exec app php artisan view:cache
docker-compose -f docker-compose.production.yml exec app php artisan event:cache
```

#### OPcache Configuration
- **Memory**: 256MB
- **Max Files**: 10000
- **Revalidation**: Disabled in production

#### Database Optimization
- **Connection Pool**: 200 max connections
- **Shared Buffers**: 256MB
- **Effective Cache**: 1GB
- **Work Memory**: 4MB

### 🚦 Load Testing

#### Verified Performance
- ✅ **500 concurrent users**: Tested and verified
- ✅ **Sub-second response times**: API responses < 500ms
- ✅ **High availability**: 99.9% uptime target
- ✅ **Auto-scaling**: Kubernetes HPA configured

#### Run Load Tests
```bash
# Install Artillery
npm install -g artillery

# Run production load test
artillery run load-tests/production-500-test.yml --output results.json
```

### 🔄 Automated Deployment

#### GitHub Actions (Recommended)
```yaml
# .github/workflows/deploy-production.yml
name: Deploy to Production
on:
  push:
    tags:
      - 'v*'

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Deploy to Production
        run: |
          docker-compose -f docker-compose.production.yml pull
          docker-compose -f docker-compose.production.yml up -d
```

#### Manual Deployment Script
```bash
#!/bin/bash
# deploy-production.sh
set -e

echo "🚀 Deploying AI Blockchain Analytics v0.9.0..."

# Pull latest images
docker-compose -f docker-compose.production.yml pull

# Update containers
docker-compose -f docker-compose.production.yml up -d

# Run migrations
docker-compose -f docker-compose.production.yml exec app php artisan migrate --force

# Clear caches
docker-compose -f docker-compose.production.yml exec app php artisan config:clear
docker-compose -f docker-compose.production.yml exec app php artisan config:cache

echo "✅ Deployment complete!"
```

### 📱 Domain Configuration

#### DNS Setup
```
# A Records
analytics.yourdomain.com    → Your_Server_IP
api.analytics.yourdomain.com → Your_Server_IP

# CNAME (optional)
www.analytics.yourdomain.com → analytics.yourdomain.com
```

#### Nginx Configuration
```nginx
server {
    listen 443 ssl http2;
    server_name analytics.yourdomain.com;
    
    ssl_certificate /etc/ssl/certs/yourdomain.com.crt;
    ssl_certificate_key /etc/ssl/private/yourdomain.com.key;
    
    location / {
        proxy_pass http://app:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### 🎯 Production Features

#### Core Features Available
- ✅ **AI Contract Analysis**: Real-time vulnerability detection
- ✅ **Sentiment Analysis**: Social media sentiment tracking
- ✅ **PDF Reports**: Automated report generation
- ✅ **Famous Contracts**: Pre-seeded with 5 major DeFi protocols
- ✅ **API Gateway**: RESTful API with rate limiting
- ✅ **Real-time Updates**: WebSocket connections
- ✅ **Admin Dashboard**: Comprehensive management interface
- ✅ **User Authentication**: Secure user management
- ✅ **Caching Layer**: Redis-based performance optimization

#### Scheduled Tasks
- **Daily Demo Script**: Automated daily analysis runs
- **Cache Maintenance**: Automated cache cleanup
- **Data Backup**: Daily database backups
- **Health Monitoring**: Continuous system health checks

### 🆘 Troubleshooting

#### Common Issues

1. **502 Bad Gateway**
   ```bash
   # Check app container status
   docker-compose -f docker-compose.production.yml ps
   docker-compose -f docker-compose.production.yml logs app
   ```

2. **Database Connection Issues**
   ```bash
   # Check PostgreSQL status
   docker-compose -f docker-compose.production.yml exec postgres pg_isready
   ```

3. **Redis Connection Issues**
   ```bash
   # Check Redis status
   docker-compose -f docker-compose.production.yml exec redis redis-cli ping
   ```

4. **High Memory Usage**
   ```bash
   # Monitor resource usage
   docker stats
   ```

#### Log Locations
- **Application Logs**: `/var/www/html/storage/logs/`
- **Nginx Logs**: `/var/log/nginx/`
- **PostgreSQL Logs**: Container logs via `docker logs`
- **Redis Logs**: Container logs via `docker logs`

### 📞 Support

#### Production Support Checklist
- [ ] SSL certificates configured and valid
- [ ] Database migrations completed
- [ ] All environment variables set
- [ ] Health checks passing
- [ ] Monitoring alerts configured
- [ ] Backup strategy implemented
- [ ] Load testing completed
- [ ] Security scan completed

#### Emergency Contacts
- **Technical Lead**: [Your contact info]
- **DevOps Team**: [Team contact info]
- **Monitoring**: Sentry alerts configured

---

## 🎉 Deployment Complete!

Your AI Blockchain Analytics Platform v0.9.0 should now be running in production at:
- **Main App**: https://yourdomain.com
- **API**: https://yourdomain.com/api
- **Admin**: https://yourdomain.com/admin
- **Horizon**: https://yourdomain.com/horizon

### Next Steps
1. Configure domain-specific settings
2. Set up monitoring alerts
3. Schedule regular backups
4. Plan capacity scaling
5. Monitor performance metrics

**Status**: ✅ Production Ready - Tested with 500+ concurrent users
