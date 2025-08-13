# 🚀 GitHub Mono-Repo Setup Guide

## Laravel 12 + Octane AI Blockchain Analytics Platform

This guide will help you set up the complete mono-repo on GitHub with all features implemented.

## 📋 Prerequisites

- GitHub account
- Git configured locally
- Docker & Docker Compose
- Node.js 20+ and PHP 8.3+

## 🔧 Repository Setup

### 1. Create GitHub Repository

```bash
# Create a new repository on GitHub named: ai-blockchain-analytics
# Make it public or private as needed
# Don't initialize with README (we already have one)
```

### 2. Connect Local Repository to GitHub

```bash
# Add GitHub remote (replace with your username)
git remote add origin https://github.com/YOUR_USERNAME/ai-blockchain-analytics.git

# Verify remote
git remote -v

# Push main branch
git branch -M main
git push -u origin main
```

### 3. Setup GitHub Secrets

Add these secrets in GitHub Settings > Secrets and variables > Actions:

#### **Required Secrets:**
```
AWS_ACCESS_KEY_ID          # For Lambda deployment
AWS_SECRET_ACCESS_KEY      # For Lambda deployment  
AWS_DEFAULT_REGION         # e.g., us-east-1

OPENAI_API_KEY             # For AI analysis
GOOGLE_CLOUD_CREDENTIALS   # For NLP sentiment analysis

TWITTER_BEARER_TOKEN       # For Twitter/X crawler
REDDIT_CLIENT_ID           # For Reddit crawler
REDDIT_CLIENT_SECRET       # For Reddit crawler
TELEGRAM_BOT_TOKEN         # For Telegram crawler

COINGECKO_API_KEY          # For price data (optional)

DB_PASSWORD                # PostgreSQL password
REDIS_PASSWORD             # Redis password (if using auth)

STRIPE_SECRET_KEY          # For billing
STRIPE_WEBHOOK_SECRET      # For webhook verification
```

#### **Optional Secrets:**
```
BROWSERLESS_TOKEN          # For PDF generation
SENTRY_DSN                 # For error tracking
SLACK_WEBHOOK_URL          # For notifications
```

### 4. Configure Branch Protection

Go to Settings > Branches and set up protection rules for `main`:

- ✅ Require pull request reviews
- ✅ Require status checks to pass
- ✅ Require up-to-date branches
- ✅ Include administrators

## 🏗️ Project Structure Overview

```
ai-blockchain-analytics/
├── 📁 .github/workflows/     # CI/CD pipelines
├── 📁 app/                   # Laravel application
│   ├── 📁 Console/Commands/  # 60+ Artisan commands
│   ├── 📁 Services/          # Business logic services
│   ├── 📁 Models/           # Eloquent models
│   └── 📁 Http/Controllers/ # API & web controllers
├── 📁 resources/js/         # Vue.js frontend
│   ├── 📁 Components/       # Reusable Vue components
│   └── 📁 Pages/           # Inertia.js pages
├── 📁 lambda/              # AWS Lambda functions
├── 📁 docker/              # Docker configuration
├── 📁 tests/               # Comprehensive test suite
└── 📁 docs/                # Documentation files
```

## 🚀 Deployment Options

### **Option 1: Docker Deployment (Recommended)**

```bash
# Clone and setup
git clone https://github.com/YOUR_USERNAME/ai-blockchain-analytics.git
cd ai-blockchain-analytics

# Copy environment file
cp .env.example .env

# Configure environment variables
nano .env

# Start with Docker
docker-compose up -d

# Install dependencies
docker-compose exec app composer install
docker-compose exec app npm install

# Setup Laravel
docker-compose exec app php artisan key:generate
docker-compose exec app php artisan migrate --seed
docker-compose exec app npm run build
```

### **Option 2: Laravel Octane (High Performance)**

```bash
# After basic setup above
docker-compose exec app php artisan octane:start --host=0.0.0.0 --port=8000
```

### **Option 3: AWS Lambda (Serverless)**

```bash
# Deploy crawler microservice
cd lambda/crawler_microservice
./deploy.sh
```

## 📊 Features Included

### ✅ **AI-Powered Analysis**
- Smart contract security analysis (OWASP compliance)
- Real-time vulnerability detection
- Advanced prompt engineering

### ✅ **Social Media Intelligence**
- Twitter/X, Reddit, Telegram crawlers
- Keyword-based filtering
- Rate limiting and proxy support

### ✅ **Sentiment Analysis Pipeline**
- Google Cloud NLP integration
- Batch processing for cost optimization
- Daily aggregation and trends

### ✅ **Data Visualization**
- Vue.js sentiment vs price charts
- Real-time dashboards
- Interactive analytics

### ✅ **PDF Generation**
- Browserless rendering (preferred)
- DomPDF fallback
- Vue component to PDF conversion

### ✅ **Security Features**
- Verification badges with HMAC signing
- Anti-spoofing protection
- Rate limiting and security headers

### ✅ **Developer Experience**
- Comprehensive test suite
- Regression testing
- CI/CD pipeline
- Docker containerization

## 🔄 CI/CD Pipeline

The GitHub Actions pipeline automatically:

1. **Tests** - PHPUnit, Frontend tests, Security scans
2. **Builds** - Docker images, Assets compilation
3. **Deploys** - Staging and production environments
4. **Monitors** - Health checks and performance metrics

## 📈 Usage Examples

### **Start Crawler System:**
```bash
php artisan crawler:start --platforms=twitter,reddit --keywords="bitcoin,ethereum"
```

### **Run Sentiment Pipeline:**
```bash
php artisan sentiment:process --batch-size=100
```

### **Generate PDF Reports:**
```bash
php artisan pdf:generate --type=sentiment --date=today
```

### **Monitor System:**
```bash
php artisan system:dashboard
```

## 🎯 Quick Start Commands

```bash
# Demo the complete system
php artisan demo:north-star

# Run comprehensive tests
./scripts/run-regression-suite.sh

# Monitor all services
php artisan system:status
```

## 📚 Documentation

- [Crawler Microservice Guide](CRAWLER_MICROSERVICE_GUIDE.md)
- [Sentiment Pipeline Guide](SENTIMENT_PIPELINE_GUIDE.md)
- [PDF Generation Guide](VUE_PDF_GENERATION_COMPLETE.md)
- [Verification System Guide](VERIFICATION_BADGE_SYSTEM_COMPLETE.md)
- [Deployment Guide](DOCKER.md)

## 🆘 Support

For issues and questions:

1. Check the [comprehensive documentation](README.md)
2. Run diagnostic commands: `php artisan system:diagnostic`
3. View logs: `docker-compose logs -f app`
4. Open GitHub issue with full error details

## 🎉 Success Metrics

After setup, you should have:

- ✅ All tests passing
- ✅ Docker containers running
- ✅ CI/CD pipeline green
- ✅ Real-time sentiment analysis
- ✅ Smart contract analysis working
- ✅ PDF generation functional
- ✅ Verification system operational

---

**🚀 Your Laravel 12 + Octane AI Blockchain Analytics Platform is ready for production!**