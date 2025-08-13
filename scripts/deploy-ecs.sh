#!/bin/bash
set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
AWS_REGION="${AWS_REGION:-us-east-1}"
AWS_ACCOUNT_ID="${AWS_ACCOUNT_ID:-$(aws sts get-caller-identity --query Account --output text)}"
ECR_REPOSITORY="${ECR_REPOSITORY:-ai-blockchain-analytics}"
CLUSTER_NAME="${CLUSTER_NAME:-ai-blockchain-analytics-cluster}"
IMAGE_TAG="${IMAGE_TAG:-latest}"

echo -e "${BLUE}🚀 Deploying AI Blockchain Analytics to AWS ECS${NC}"
echo -e "${BLUE}============================================${NC}"

# Function to print status
print_status() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Check prerequisites
echo -e "${YELLOW}🔍 Checking prerequisites...${NC}"
    
# Check if AWS CLI is available and configured
    if ! command -v aws &> /dev/null; then
    print_error "AWS CLI is not installed or not in PATH"
    exit 1
fi

# Check AWS credentials
if ! aws sts get-caller-identity &> /dev/null; then
    print_error "AWS credentials not configured or invalid"
    exit 1
fi

# Check if docker is available
if ! command -v docker &> /dev/null; then
    print_error "docker is not installed or not in PATH"
    exit 1
fi

print_status "Prerequisites check passed"

# Create ECR repository if it doesn't exist
echo -e "${YELLOW}📦 Setting up ECR repository...${NC}"
aws ecr describe-repositories --repository-names $ECR_REPOSITORY --region $AWS_REGION &> /dev/null || \
    aws ecr create-repository --repository-name $ECR_REPOSITORY --region $AWS_REGION
print_status "ECR repository ready"
    
    # Login to ECR
echo -e "${YELLOW}🔐 Logging into ECR...${NC}"
aws ecr get-login-password --region $AWS_REGION | docker login --username AWS --password-stdin $AWS_ACCOUNT_ID.dkr.ecr.$AWS_REGION.amazonaws.com
print_status "ECR login successful"

# Build and push Docker image
echo -e "${YELLOW}🔨 Building and pushing Docker image...${NC}"
docker build -f Dockerfile.production -t $ECR_REPOSITORY:$IMAGE_TAG .
docker tag $ECR_REPOSITORY:$IMAGE_TAG $AWS_ACCOUNT_ID.dkr.ecr.$AWS_REGION.amazonaws.com/$ECR_REPOSITORY:$IMAGE_TAG
docker push $AWS_ACCOUNT_ID.dkr.ecr.$AWS_REGION.amazonaws.com/$ECR_REPOSITORY:$IMAGE_TAG
print_status "Docker image built and pushed"

# Update task definitions with correct image URI
echo -e "${YELLOW}🔄 Updating task definitions...${NC}"
IMAGE_URI="$AWS_ACCOUNT_ID.dkr.ecr.$AWS_REGION.amazonaws.com/$ECR_REPOSITORY:$IMAGE_TAG"

# Update app task definition
sed -i "s|ACCOUNT_ID.dkr.ecr.REGION.amazonaws.com/ai-blockchain-analytics:latest|$IMAGE_URI|g" ecs/task-definitions/app.json
sed -i "s|ACCOUNT_ID|$AWS_ACCOUNT_ID|g" ecs/task-definitions/app.json
sed -i "s|REGION|$AWS_REGION|g" ecs/task-definitions/app.json

# Update worker task definition
sed -i "s|ACCOUNT_ID.dkr.ecr.REGION.amazonaws.com/ai-blockchain-analytics:latest|$IMAGE_URI|g" ecs/task-definitions/worker.json
sed -i "s|ACCOUNT_ID|$AWS_ACCOUNT_ID|g" ecs/task-definitions/worker.json
sed -i "s|REGION|$AWS_REGION|g" ecs/task-definitions/worker.json

print_status "Task definitions updated"

# Create or update ECS cluster
echo -e "${YELLOW}🏗️  Setting up ECS cluster...${NC}"
aws ecs describe-clusters --clusters $CLUSTER_NAME --region $AWS_REGION &> /dev/null || \
    aws ecs create-cluster --cluster-name $CLUSTER_NAME --capacity-providers FARGATE FARGATE_SPOT --default-capacity-provider-strategy capacityProvider=FARGATE,weight=1 capacityProvider=FARGATE_SPOT,weight=3 --region $AWS_REGION
print_status "ECS cluster ready"

# Create CloudWatch log groups
echo -e "${YELLOW}📝 Setting up CloudWatch log groups...${NC}"
aws logs create-log-group --log-group-name "/ecs/ai-blockchain-analytics-app" --region $AWS_REGION 2>/dev/null || true
aws logs create-log-group --log-group-name "/ecs/ai-blockchain-analytics-worker" --region $AWS_REGION 2>/dev/null || true
print_status "CloudWatch log groups created"

# Register task definitions
echo -e "${YELLOW}📋 Registering task definitions...${NC}"
APP_TASK_DEF_ARN=$(aws ecs register-task-definition --cli-input-json file://ecs/task-definitions/app.json --region $AWS_REGION --query 'taskDefinition.taskDefinitionArn' --output text)
WORKER_TASK_DEF_ARN=$(aws ecs register-task-definition --cli-input-json file://ecs/task-definitions/worker.json --region $AWS_REGION --query 'taskDefinition.taskDefinitionArn' --output text)
print_status "Task definitions registered"

# Wait for RDS and ElastiCache to be available (assumes they're already created)
echo -e "${YELLOW}⏳ Checking database and cache availability...${NC}"
print_warning "Ensure RDS PostgreSQL and ElastiCache Redis instances are running and accessible"

# Create or update ECS services
echo -e "${YELLOW}🚀 Deploying ECS services...${NC}"

# Check if app service exists
if aws ecs describe-services --cluster $CLUSTER_NAME --services ai-blockchain-analytics-app --region $AWS_REGION &> /dev/null; then
    echo -e "${YELLOW}🔄 Updating app service...${NC}"
    aws ecs update-service --cluster $CLUSTER_NAME --service ai-blockchain-analytics-app --task-definition $APP_TASK_DEF_ARN --region $AWS_REGION
else
    echo -e "${YELLOW}🆕 Creating app service...${NC}"
    # Update service definition with actual task definition ARN
    sed -i "s|\"taskDefinition\": \"ai-blockchain-analytics-app\"|\"taskDefinition\": \"$APP_TASK_DEF_ARN\"|g" ecs/services/app-service.json
    aws ecs create-service --cli-input-json file://ecs/services/app-service.json --region $AWS_REGION
fi

# Check if worker service exists
if aws ecs describe-services --cluster $CLUSTER_NAME --services ai-blockchain-analytics-worker --region $AWS_REGION &> /dev/null; then
    echo -e "${YELLOW}🔄 Updating worker service...${NC}"
    aws ecs update-service --cluster $CLUSTER_NAME --service ai-blockchain-analytics-worker --task-definition $WORKER_TASK_DEF_ARN --region $AWS_REGION
else
    echo -e "${YELLOW}🆕 Creating worker service...${NC}"
    # Update service definition with actual task definition ARN
    sed -i "s|\"taskDefinition\": \"ai-blockchain-analytics-worker\"|\"taskDefinition\": \"$WORKER_TASK_DEF_ARN\"|g" ecs/services/worker-service.json
    aws ecs create-service --cli-input-json file://ecs/services/worker-service.json --region $AWS_REGION
fi

print_status "ECS services deployed"

# Wait for services to stabilize
echo -e "${YELLOW}⏳ Waiting for services to stabilize...${NC}"
aws ecs wait services-stable --cluster $CLUSTER_NAME --services ai-blockchain-analytics-app --region $AWS_REGION
aws ecs wait services-stable --cluster $CLUSTER_NAME --services ai-blockchain-analytics-worker --region $AWS_REGION
print_status "Services are stable"

# Run database migrations
echo -e "${YELLOW}🔄 Running database migrations...${NC}"
TASK_ARN=$(aws ecs list-tasks --cluster $CLUSTER_NAME --service-name ai-blockchain-analytics-app --region $AWS_REGION --query 'taskArns[0]' --output text)
if [ "$TASK_ARN" != "None" ]; then
    aws ecs execute-command --cluster $CLUSTER_NAME --task $TASK_ARN --container ai-blockchain-analytics-app --interactive --command "php artisan migrate --force" --region $AWS_REGION
    print_status "Database migrations completed"
else
    print_warning "No running tasks found. Please run migrations manually after deployment."
fi

# Display deployment status
echo -e "${BLUE}📊 Deployment Status${NC}"
echo -e "${BLUE}===================${NC}"
aws ecs describe-services --cluster $CLUSTER_NAME --services ai-blockchain-analytics-app ai-blockchain-analytics-worker --region $AWS_REGION --query 'services[*].[serviceName,status,runningCount,pendingCount]' --output table

# Get load balancer DNS name (if configured)
echo -e "${BLUE}🌍 Access Information${NC}"
echo -e "${BLUE}====================${NC}"
echo "Cluster: $CLUSTER_NAME"
echo "Region: $AWS_REGION"
echo "App Task Definition: $APP_TASK_DEF_ARN"
echo "Worker Task Definition: $WORKER_TASK_DEF_ARN"

# Show useful commands
echo -e "${BLUE}📝 Useful Commands${NC}"
echo -e "${BLUE}=================${NC}"
echo "View logs: aws logs tail /ecs/ai-blockchain-analytics-app --follow --region $AWS_REGION"
echo "Scale app: aws ecs update-service --cluster $CLUSTER_NAME --service ai-blockchain-analytics-app --desired-count 5 --region $AWS_REGION"
echo "Execute commands: aws ecs execute-command --cluster $CLUSTER_NAME --task <TASK_ARN> --container ai-blockchain-analytics-app --interactive --command bash --region $AWS_REGION"
echo "Monitor services: aws ecs describe-services --cluster $CLUSTER_NAME --services ai-blockchain-analytics-app --region $AWS_REGION"

print_status "Deployment completed successfully! 🎉"

# Security reminder
echo -e "${YELLOW}🔒 Security Reminders${NC}"
echo -e "${YELLOW}===================${NC}"
echo "1. Update secrets in AWS Systems Manager Parameter Store"
echo "2. Configure proper IAM roles and policies"
echo "3. Set up VPC, subnets, and security groups"
echo "4. Configure Application Load Balancer with SSL/TLS"
echo "5. Set up RDS PostgreSQL and ElastiCache Redis instances"
echo "6. Configure CloudWatch alarms and monitoring"