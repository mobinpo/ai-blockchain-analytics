#!/bin/bash

# ECS Deployment Script for AI Blockchain Analytics
# Usage: ./deploy.sh [environment] [region] [account-id]

set -e

# Default values
ENVIRONMENT=${1:-production}
REGION=${2:-us-east-1}
ACCOUNT_ID=${3:-YOUR_ACCOUNT}
CLUSTER_NAME="ai-blockchain-analytics-cluster"
SERVICE_NAME="ai-blockchain-analytics"
TASK_FAMILY="ai-blockchain-analytics"

echo "🚀 Starting ECS deployment for AI Blockchain Analytics"
echo "📍 Environment: $ENVIRONMENT"
echo "🌍 Region: $REGION"
echo "🏠 Account: $ACCOUNT_ID"
echo

# Check if AWS CLI is configured
if ! aws sts get-caller-identity > /dev/null 2>&1; then
    echo "❌ AWS CLI is not configured or credentials are invalid"
    exit 1
fi

echo "✅ AWS CLI configured"

# Build and push Docker image
echo "🔨 Building Docker image..."
REPO_URI="$ACCOUNT_ID.dkr.ecr.$REGION.amazonaws.com/ai-blockchain-analytics"

# Get ECR login token
echo "🔑 Logging into ECR..."
aws ecr get-login-password --region $REGION | docker login --username AWS --password-stdin $REPO_URI

# Build image with build args for RoadRunner
echo "🏗️ Building production image with RoadRunner..."
docker build \
    --build-arg APP_ENV=$ENVIRONMENT \
    --build-arg BUILD_TARGET=production \
    -t ai-blockchain-analytics:latest \
    -f Dockerfile .

# Tag and push
IMAGE_TAG=$(date +%Y%m%d-%H%M%S)
docker tag ai-blockchain-analytics:latest $REPO_URI:latest
docker tag ai-blockchain-analytics:latest $REPO_URI:$IMAGE_TAG

echo "📤 Pushing images to ECR..."
docker push $REPO_URI:latest
docker push $REPO_URI:$IMAGE_TAG

echo "✅ Images pushed successfully"

# Update task definition with new image URI
echo "📝 Updating task definition..."
sed -i "s|YOUR_ACCOUNT|$ACCOUNT_ID|g" ecs/task-definition.json
sed -i "s|us-east-1|$REGION|g" ecs/task-definition.json

# Register new task definition
NEW_TASK_DEF=$(aws ecs register-task-definition \
    --cli-input-json file://ecs/task-definition.json \
    --region $REGION \
    --query 'taskDefinition.taskDefinitionArn' \
    --output text)

echo "✅ New task definition registered: $NEW_TASK_DEF"

# Check if cluster exists
if ! aws ecs describe-clusters --clusters $CLUSTER_NAME --region $REGION > /dev/null 2>&1; then
    echo "🏗️ Creating ECS cluster..."
    aws ecs create-cluster \
        --cluster-name $CLUSTER_NAME \
        --capacity-providers FARGATE \
        --default-capacity-provider-strategy capacityProvider=FARGATE,weight=1 \
        --region $REGION \
        --tags key=Environment,value=$ENVIRONMENT key=Project,value=ai-blockchain-analytics
    echo "✅ Cluster created"
else
    echo "✅ Cluster already exists"
fi

# Check if service exists
if aws ecs describe-services --cluster $CLUSTER_NAME --services $SERVICE_NAME --region $REGION | grep -q "ACTIVE\|RUNNING"; then
    echo "🔄 Updating existing service..."
    aws ecs update-service \
        --cluster $CLUSTER_NAME \
        --service $SERVICE_NAME \
        --task-definition $NEW_TASK_DEF \
        --region $REGION \
        --force-new-deployment
    echo "✅ Service updated"
else
    echo "🆕 Creating new service..."
    
    # Update service configuration with current values
    sed -i "s|subnet-12345678|$(aws ec2 describe-subnets --region $REGION --query 'Subnets[0].SubnetId' --output text)|g" ecs/service.json
    sed -i "s|subnet-87654321|$(aws ec2 describe-subnets --region $REGION --query 'Subnets[1].SubnetId' --output text)|g" ecs/service.json
    sed -i "s|YOUR_ACCOUNT|$ACCOUNT_ID|g" ecs/service.json
    
    aws ecs create-service \
        --cli-input-json file://ecs/service.json \
        --region $REGION
    echo "✅ Service created"
fi

# Wait for deployment to stabilize
echo "⏳ Waiting for service to stabilize..."
aws ecs wait services-stable \
    --cluster $CLUSTER_NAME \
    --services $SERVICE_NAME \
    --region $REGION

# Get service status
echo "📊 Deployment Status:"
aws ecs describe-services \
    --cluster $CLUSTER_NAME \
    --services $SERVICE_NAME \
    --region $REGION \
    --query 'services[0].{ServiceName:serviceName,TaskDefinition:taskDefinition,RunningCount:runningCount,PendingCount:pendingCount,DesiredCount:desiredCount}' \
    --output table

# Get load balancer DNS name (if configured)
TARGET_GROUP_ARN=$(aws ecs describe-services \
    --cluster $CLUSTER_NAME \
    --services $SERVICE_NAME \
    --region $REGION \
    --query 'services[0].loadBalancers[0].targetGroupArn' \
    --output text)

if [ "$TARGET_GROUP_ARN" != "None" ]; then
    LB_DNS=$(aws elbv2 describe-load-balancers \
        --region $REGION \
        --query "LoadBalancers[?contains(TargetGroups[].TargetGroupArn, '$TARGET_GROUP_ARN')].DNSName" \
        --output text 2>/dev/null || echo "Not found")
    
    if [ "$LB_DNS" != "Not found" ] && [ -n "$LB_DNS" ]; then
        echo "🌍 Load Balancer URL: https://$LB_DNS"
    fi
fi

echo
echo "🎉 Deployment completed successfully!"
echo "📋 Summary:"
echo "   • Cluster: $CLUSTER_NAME"
echo "   • Service: $SERVICE_NAME" 
echo "   • Task Definition: $NEW_TASK_DEF"
echo "   • Image: $REPO_URI:$IMAGE_TAG"
echo
echo "🔍 To check logs:"
echo "   aws logs tail /ecs/ai-blockchain-analytics --region $REGION --follow"
echo
echo "🛠️ To connect to container:"
echo "   aws ecs execute-command --cluster $CLUSTER_NAME --task <task-id> --container laravel-app --interactive --command /bin/bash"