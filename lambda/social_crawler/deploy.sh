#!/bin/bash

# Social Media Crawler Lambda Deployment Script
# Deploys the serverless social media crawler to AWS

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
STAGE="${1:-dev}"
REGION="${2:-us-east-1}"
PROFILE="${3:-default}"

echo -e "${BLUE}🚀 Deploying Social Media Crawler Lambda${NC}"
echo -e "${BLUE}Stage: ${STAGE}${NC}"
echo -e "${BLUE}Region: ${REGION}${NC}"
echo -e "${BLUE}AWS Profile: ${PROFILE}${NC}"
echo ""

# Check prerequisites
echo -e "${YELLOW}📋 Checking prerequisites...${NC}"

# Check if AWS CLI is installed
if ! command -v aws &> /dev/null; then
    echo -e "${RED}❌ AWS CLI is not installed${NC}"
    exit 1
fi

# Check if Serverless Framework is installed
if ! command -v serverless &> /dev/null; then
    echo -e "${RED}❌ Serverless Framework is not installed${NC}"
    echo -e "${YELLOW}💡 Install with: npm install -g serverless${NC}"
    exit 1
fi

# Check if Docker is running (for python requirements)
if ! docker info &> /dev/null; then
    echo -e "${YELLOW}⚠️  Docker is not running. Python dependencies may not build correctly.${NC}"
    echo -e "${YELLOW}💡 Start Docker to ensure proper dependency packaging.${NC}"
fi

echo -e "${GREEN}✅ Prerequisites check passed${NC}"
echo ""

# Install Serverless plugins
echo -e "${YELLOW}📦 Installing Serverless plugins...${NC}"
npm install serverless-python-requirements serverless-plugin-warmup

# Configure AWS credentials
echo -e "${YELLOW}🔑 Setting up AWS credentials...${NC}"
export AWS_PROFILE=$PROFILE

# Test AWS connectivity
if ! aws sts get-caller-identity --profile $PROFILE &> /dev/null; then
    echo -e "${RED}❌ AWS credentials not properly configured for profile: $PROFILE${NC}"
    echo -e "${YELLOW}💡 Run: aws configure --profile $PROFILE${NC}"
    exit 1
fi

echo -e "${GREEN}✅ AWS credentials verified${NC}"

# Setup SSM parameters (if not exists)
echo -e "${YELLOW}🔧 Setting up SSM parameters...${NC}"

# Function to create SSM parameter if it doesn't exist
create_ssm_param() {
    local param_name=$1
    local param_value=$2
    local param_type=$3
    
    if aws ssm get-parameter --name "$param_name" --profile $PROFILE &> /dev/null; then
        echo -e "${BLUE}📋 Parameter $param_name already exists${NC}"
    else
        echo -e "${YELLOW}🆕 Creating parameter $param_name${NC}"
        echo -e "${YELLOW}💡 Please enter value for $param_name:${NC}"
        read -s user_input
        aws ssm put-parameter \
            --name "$param_name" \
            --value "$user_input" \
            --type "$param_type" \
            --profile $PROFILE
        echo -e "${GREEN}✅ Created parameter $param_name${NC}"
    fi
}

# Create required SSM parameters
echo -e "${YELLOW}🔑 Setting up API credentials in SSM Parameter Store...${NC}"

create_ssm_param "/social-crawler/$STAGE/twitter/bearer-token" "" "SecureString"
create_ssm_param "/social-crawler/$STAGE/reddit/client-id" "" "String"
create_ssm_param "/social-crawler/$STAGE/reddit/client-secret" "" "SecureString"
create_ssm_param "/social-crawler/$STAGE/reddit/username" "" "String"
create_ssm_param "/social-crawler/$STAGE/reddit/password" "" "SecureString"
create_ssm_param "/social-crawler/$STAGE/telegram/bot-token" "" "SecureString"

echo -e "${YELLOW}💡 Please enter your database URL:${NC}"
read -s database_url
aws ssm put-parameter \
    --name "/social-crawler/$STAGE/database/url" \
    --value "$database_url" \
    --type "SecureString" \
    --overwrite \
    --profile $PROFILE

echo -e "${GREEN}✅ SSM parameters configured${NC}"
echo ""

# Deploy the service
echo -e "${YELLOW}🚀 Deploying serverless service...${NC}"

serverless deploy \
    --stage $STAGE \
    --region $REGION \
    --aws-profile $PROFILE \
    --verbose

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Deployment successful!${NC}"
else
    echo -e "${RED}❌ Deployment failed${NC}"
    exit 1
fi

echo ""

# Get deployment info
echo -e "${YELLOW}📊 Getting deployment information...${NC}"
STACK_NAME="social-media-crawler-$STAGE"

# Get API Gateway endpoint
API_ENDPOINT=$(aws cloudformation describe-stacks \
    --stack-name $STACK_NAME \
    --query 'Stacks[0].Outputs[?OutputKey==`HttpApiUrl`].OutputValue' \
    --output text \
    --profile $PROFILE 2>/dev/null || echo "Not available")

# Get function names
CRAWLER_FUNCTION=$(aws cloudformation describe-stacks \
    --stack-name $STACK_NAME \
    --query 'Stacks[0].Outputs[?OutputKey==`CrawlerLambdaFunction`].OutputValue' \
    --output text \
    --profile $PROFILE 2>/dev/null || echo "social-media-crawler-$STAGE")

echo ""
echo -e "${GREEN}🎉 Deployment Complete!${NC}"
echo ""
echo -e "${BLUE}📋 Deployment Summary:${NC}"
echo -e "${BLUE}  Stage: $STAGE${NC}"
echo -e "${BLUE}  Region: $REGION${NC}"
echo -e "${BLUE}  API Endpoint: $API_ENDPOINT${NC}"
echo -e "${BLUE}  Crawler Function: $CRAWLER_FUNCTION${NC}"
echo ""
echo -e "${BLUE}🔧 Available Endpoints:${NC}"
echo -e "${BLUE}  POST $API_ENDPOINT/crawl${NC}"
echo -e "${BLUE}  GET  $API_ENDPOINT/health${NC}"
echo -e "${BLUE}  GET  $API_ENDPOINT/keywords${NC}"
echo -e "${BLUE}  POST $API_ENDPOINT/keywords${NC}"
echo ""

# Test deployment
echo -e "${YELLOW}🧪 Testing deployment...${NC}"

# Test health check
echo -e "${YELLOW}🔍 Testing health check...${NC}"
if curl -s -f "$API_ENDPOINT/health" > /dev/null; then
    echo -e "${GREEN}✅ Health check passed${NC}"
else
    echo -e "${YELLOW}⚠️  Health check failed - may need time to warm up${NC}"
fi

# Test manual crawl (optional)
echo -e "${YELLOW}💡 To test manual crawl:${NC}"
echo -e "${BLUE}curl -X POST $API_ENDPOINT/crawl -H 'Content-Type: application/json' -d '{\"platforms\": [\"twitter\"], \"platform_options\": {\"twitter\": {\"keywords\": [\"blockchain\"]}}}'${NC}"
echo ""

# Schedule information
echo -e "${YELLOW}⏰ Scheduled crawling:${NC}"
echo -e "${BLUE}  The crawler is scheduled to run every 30 minutes${NC}"
echo -e "${BLUE}  Check CloudWatch Logs for execution details${NC}"
echo ""

# Monitoring
echo -e "${YELLOW}📊 Monitoring:${NC}"
echo -e "${BLUE}  CloudWatch Logs: /aws/lambda/social-media-crawler-$STAGE${NC}"
echo -e "${BLUE}  CloudWatch Alarms: social-crawler-errors-$STAGE${NC}"
echo -e "${BLUE}  SQS Queue: social-crawler-jobs-$STAGE${NC}"
echo ""

echo -e "${GREEN}🎯 Deployment complete! The social media crawler is now running.${NC}"

# Save deployment info
cat > deployment-info.json << EOF
{
  "stage": "$STAGE",
  "region": "$REGION",
  "api_endpoint": "$API_ENDPOINT",
  "crawler_function": "$CRAWLER_FUNCTION",
  "deployed_at": "$(date -u +"%Y-%m-%dT%H:%M:%SZ")",
  "stack_name": "$STACK_NAME"
}
EOF

echo -e "${BLUE}💾 Deployment info saved to deployment-info.json${NC}"
