#!/bin/bash

# Social Media Crawler Lambda Deployment Script

set -e

echo "🚀 Deploying Social Media Crawler Lambda..."

# Check if serverless is installed
if ! command -v serverless &> /dev/null; then
    echo "❌ Serverless Framework not found. Installing..."
    npm install -g serverless
fi

# Check if required environment variables are set
required_vars=("TWITTER_BEARER_TOKEN" "AWS_ACCESS_KEY_ID" "AWS_SECRET_ACCESS_KEY")
for var in "${required_vars[@]}"; do
    if [[ -z "${!var}" ]]; then
        echo "⚠️  Warning: $var environment variable not set"
    fi
done

# Set default stage if not provided
STAGE=${1:-dev}
echo "📦 Deploying to stage: $STAGE"

# Create .env file for local testing
cat > .env << EOF
TWITTER_BEARER_TOKEN=${TWITTER_BEARER_TOKEN}
TELEGRAM_BOT_TOKEN=${TELEGRAM_BOT_TOKEN}
POSTS_TABLE=social-media-crawler-${STAGE}-posts
RULES_TABLE=social-media-crawler-${STAGE}-rules
STAGE=${STAGE}
EOF

# Install serverless plugins
echo "📦 Installing Serverless plugins..."
npm install serverless-python-requirements serverless-plugin-warmup

# Deploy to AWS
echo "🚀 Deploying to AWS..."
serverless deploy --stage $STAGE --verbose

# Create sample crawler rules
echo "📋 Creating sample crawler rules..."
aws dynamodb put-item \
    --table-name "social-media-crawler-${STAGE}-rules" \
    --item '{
        "id": {"S": "crypto-general"},
        "name": {"S": "General Crypto Keywords"},
        "platforms": {"L": [{"S": "twitter"}, {"S": "reddit"}]},
        "keywords": {"L": [{"S": "bitcoin"}, {"S": "ethereum"}, {"S": "blockchain"}, {"S": "defi"}, {"S": "cryptocurrency"}]},
        "hashtags": {"L": [{"S": "#btc"}, {"S": "#eth"}, {"S": "#defi"}, {"S": "#crypto"}]},
        "engagement_threshold": {"N": "5"},
        "active": {"BOOL": true},
        "priority": {"N": "1"},
        "filters": {"M": {
            "subreddits": {"L": [{"S": "cryptocurrency"}, {"S": "bitcoin"}, {"S": "ethereum"}, {"S": "defi"}]}
        }}
    }' \
    --region us-east-1 || echo "⚠️  Rule creation failed or rule already exists"

aws dynamodb put-item \
    --table-name "social-media-crawler-${STAGE}-rules" \
    --item '{
        "id": {"S": "nft-trends"},
        "name": {"S": "NFT Trends"},
        "platforms": {"L": [{"S": "twitter"}]},
        "keywords": {"L": [{"S": "NFT"}, {"S": "OpenSea"}, {"S": "mint"}, {"S": "collection"}]},
        "hashtags": {"L": [{"S": "#NFT"}, {"S": "#OpenSea"}, {"S": "#mint"}]},
        "engagement_threshold": {"N": "10"},
        "active": {"BOOL": true},
        "priority": {"N": "2"},
        "filters": {"M": {}}
    }' \
    --region us-east-1 || echo "⚠️  Rule creation failed or rule already exists"

echo "✅ Deployment completed successfully!"
echo "📊 Lambda Functions:"
echo "   - Scheduled Crawler: social-media-crawler-${STAGE}-crawl"
echo "   - On-demand Crawler: social-media-crawler-${STAGE}-crawl-on-demand"
echo "🗄️  DynamoDB Tables:"
echo "   - Posts: social-media-crawler-${STAGE}-posts"
echo "   - Rules: social-media-crawler-${STAGE}-rules"
echo "📈 CloudWatch Dashboard: social-media-crawler-${STAGE}-dashboard"

# Display useful commands
echo ""
echo "🔧 Useful commands:"
echo "   Test locally: python social_media_crawler.py"
echo "   Invoke remote: serverless invoke -f crawl --stage $STAGE"
echo "   View logs: serverless logs -f crawl --stage $STAGE"
echo "   Remove deployment: serverless remove --stage $STAGE"