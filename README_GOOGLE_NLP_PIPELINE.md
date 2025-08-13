# 🤖 Quick Start - Google Cloud NLP Pipeline

**Streamlined: Text → Google Cloud NLP (batch sentiment) → Daily aggregates**

## 🚀 Instant Usage

### CLI Commands
```bash
# Process single text
docker compose exec app php artisan nlp:process-text \
  --text="Bitcoin is going to the moon!" \
  --platform=twitter --category=crypto

# Process from file (one text per line)
docker compose exec app php artisan nlp:process-text \
  --file=texts.txt --platform=reddit --category=blockchain --async

# Interactive mode
docker compose exec app php artisan nlp:process-text
```

### API Endpoints
```bash
# Process multiple texts
POST /api/google-nlp/process-texts
{
  "texts": ["Text 1", "Text 2"],
  "platform": "twitter",
  "category": "crypto",
  "async": false
}

# Get daily aggregates
GET /api/google-nlp/daily-aggregates?start_date=2025-01-01&end_date=2025-01-31
```

### Queue Processing
```bash
# Start queue worker for async processing
docker compose exec app php artisan queue:work
```

## 📊 What You Get

**Individual Results:**
- Sentiment score (-1 to +1)
- Sentiment magnitude (0 to 1) 
- Sentiment label (positive/negative/neutral/mixed)
- Processing metadata

**Daily Aggregates:**
- Total documents processed
- Average sentiment scores
- Sentiment distribution counts
- Min/max sentiment ranges
- Platform/category breakdowns

## ⚙️ Setup Required

1. **Google Cloud Project** with Natural Language API enabled
2. **Service Account** JSON credentials
3. **Environment Variables:**
   ```env
   GOOGLE_CLOUD_PROJECT_ID=your-project-id
   GOOGLE_APPLICATION_CREDENTIALS=/path/to/credentials.json
   ```

## 🎯 Perfect For

- **Social Media Monitoring** - Analyze tweets, posts, comments
- **News Sentiment** - Track market sentiment from headlines
- **Customer Feedback** - Process reviews and support tickets
- **Crypto Analysis** - Monitor blockchain/crypto discussions

## 📁 Files Created

- `app/Services/SentimentPipeline/GoogleCloudBatchProcessor.php` - Main processor
- `app/Jobs/ProcessTextThroughNLPPipeline.php` - Async job
- `app/Console/Commands/ProcessTextNLPPipeline.php` - CLI command
- `app/Http/Controllers/Api/GoogleCloudNLPController.php` - API endpoints
- `GOOGLE_CLOUD_NLP_PIPELINE_GUIDE.md` - Full documentation
- `demo_google_nlp_pipeline.php` - Interactive demo

## 🧪 Test Demo

```bash
# Run interactive demo
docker compose exec app php demo_google_nlp_pipeline.php
```

**Ready for production sentiment analysis at scale!** 🚀