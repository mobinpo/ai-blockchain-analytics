#!/bin/bash

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration - UPDATE THESE VALUES
AWS_REGION="${AWS_REGION:-us-east-1}"
AWS_ACCOUNT_ID="${AWS_ACCOUNT_ID:-your-account-id}"
ECR_REPOSITORY="${ECR_REPOSITORY:-ai-blockchain-analytics}"
ECS_CLUSTER_NAME="${ECS_CLUSTER_NAME:-ai-blockchain-analytics-cluster}"
IMAGE_TAG="${IMAGE_TAG:-latest}"
VPC_ID="${VPC_ID:-vpc-xxxxxxxxx}"
SUBNET_IDS="${SUBNET_IDS:-subnet-xxxxxxxxx,subnet-yyyyyyyyy}"
SECURITY_GROUP_ID="${SECURITY_GROUP_ID:-sg-xxxxxxxxx}"

# Helper functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check prerequisites
check_prerequisites() {
    log_info "Checking prerequisites..."
    
    if ! command -v aws &> /dev/null; then
        log_error "AWS CLI is not installed"
        exit 1
    fi
    
    if ! command -v docker &> /dev/null; then
        log_error "Docker is not installed"
        exit 1
    fi
    
    if ! command -v jq &> /dev/null; then
        log_error "jq is not installed"
        exit 1
    fi
    
    # Test AWS credentials
    if ! aws sts get-caller-identity &> /dev/null; then
        log_error "AWS credentials not configured"
        exit 1
    fi
    
    log_success "Prerequisites check passed"
}

# Create ECR repository if it doesn't exist
create_ecr_repository() {
    log_info "Creating ECR repository if it doesn't exist..."
    
    aws ecr describe-repositories --repository-names $ECR_REPOSITORY --region $AWS_REGION &>/dev/null || {
        log_info "Creating ECR repository: $ECR_REPOSITORY"
        aws ecr create-repository --repository-name $ECR_REPOSITORY --region $AWS_REGION
        
        # Set lifecycle policy
        aws ecr put-lifecycle-policy --repository-name $ECR_REPOSITORY --region $AWS_REGION --lifecycle-policy-text '{
            "rules": [
                {
                    "rulePriority": 1,
                    "description": "Keep last 10 images",
                    "selection": {
                        "tagStatus": "any",
                        "countType": "imageCountMoreThan",
                        "countNumber": 10
                    },
                    "action": {
                        "type": "expire"
                    }
                }
            ]
        }'
    }
    
    log_success "ECR repository ready"
}

# Build and push Docker image to ECR
build_and_push_image() {
    log_info "Building and pushing Docker image to ECR..."
    
    # Get ECR login token
    aws ecr get-login-password --region $AWS_REGION | docker login --username AWS --password-stdin $AWS_ACCOUNT_ID.dkr.ecr.$AWS_REGION.amazonaws.com
    
    # Build image
    docker build -f Dockerfile.roadrunner -t $ECR_REPOSITORY:$IMAGE_TAG .
    
    # Tag for ECR
    docker tag $ECR_REPOSITORY:$IMAGE_TAG $AWS_ACCOUNT_ID.dkr.ecr.$AWS_REGION.amazonaws.com/$ECR_REPOSITORY:$IMAGE_TAG
    
    # Push to ECR
    docker push $AWS_ACCOUNT_ID.dkr.ecr.$AWS_REGION.amazonaws.com/$ECR_REPOSITORY:$IMAGE_TAG
    
    log_success "Image pushed to ECR: $AWS_ACCOUNT_ID.dkr.ecr.$AWS_REGION.amazonaws.com/$ECR_REPOSITORY:$IMAGE_TAG"
}

# Create ECS cluster
create_ecs_cluster() {
    log_info "Creating ECS cluster..."
    
    # Check if cluster exists
    if aws ecs describe-clusters --clusters $ECS_CLUSTER_NAME --region $AWS_REGION --query 'clusters[0].status' --output text 2>/dev/null | grep -q "ACTIVE"; then
        log_info "ECS cluster $ECS_CLUSTER_NAME already exists"
    else
        log_info "Creating ECS cluster: $ECS_CLUSTER_NAME"
        aws ecs create-cluster \
            --cluster-name $ECS_CLUSTER_NAME \
            --capacity-providers FARGATE \
            --default-capacity-provider-strategy capacityProvider=FARGATE,weight=1 \
            --region $AWS_REGION
        
        # Enable container insights
        aws ecs put-cluster-attributes \
            --cluster $ECS_CLUSTER_NAME \
            --attributes name=containerInsights,value=enabled \
            --region $AWS_REGION
    fi
    
    log_success "ECS cluster ready"
}

# Create CloudWatch log group
create_log_group() {
    log_info "Creating CloudWatch log group..."
    
    aws logs create-log-group --log-group-name "/ecs/ai-blockchain-analytics" --region $AWS_REGION 2>/dev/null || {
        log_info "Log group already exists"
    }
    
    # Set retention policy
    aws logs put-retention-policy \
        --log-group-name "/ecs/ai-blockchain-analytics" \
        --retention-in-days 30 \
        --region $AWS_REGION
    
    log_success "CloudWatch log group ready"
}

# Create RDS PostgreSQL instance
create_rds_instance() {
    log_info "Creating RDS PostgreSQL instance..."
    
    # Check if RDS instance exists
    if aws rds describe-db-instances --db-instance-identifier ai-blockchain-analytics-postgres --region $AWS_REGION &>/dev/null; then
        log_info "RDS instance already exists"
    else
        log_info "Creating RDS PostgreSQL instance..."
        
        # Create DB subnet group
        aws rds create-db-subnet-group \
            --db-subnet-group-name ai-blockchain-analytics-subnet-group \
            --db-subnet-group-description "AI Blockchain Analytics DB Subnet Group" \
            --subnet-ids $(echo $SUBNET_IDS | tr ',' ' ') \
            --region $AWS_REGION 2>/dev/null || true
        
        # Create RDS instance
        aws rds create-db-instance \
            --db-instance-identifier ai-blockchain-analytics-postgres \
            --db-instance-class db.t3.medium \
            --engine postgres \
            --engine-version 16.1 \
            --allocated-storage 100 \
            --storage-type gp3 \
            --master-username aiblockchainanalytics \
            --master-user-password supersecurepassword \
            --db-name ai_blockchain_analytics \
            --vpc-security-group-ids $SECURITY_GROUP_ID \
            --db-subnet-group-name ai-blockchain-analytics-subnet-group \
            --backup-retention-period 7 \
            --storage-encrypted \
            --region $AWS_REGION
        
        log_info "Waiting for RDS instance to be available..."
        aws rds wait db-instance-available --db-instance-identifier ai-blockchain-analytics-postgres --region $AWS_REGION
    fi
    
    log_success "RDS PostgreSQL instance ready"
}

# Create ElastiCache Redis cluster
create_redis_cluster() {
    log_info "Creating ElastiCache Redis cluster..."
    
    # Check if Redis cluster exists
    if aws elasticache describe-cache-clusters --cache-cluster-id ai-blockchain-analytics-redis --region $AWS_REGION &>/dev/null; then
        log_info "Redis cluster already exists"
    else
        log_info "Creating Redis cluster..."
        
        # Create cache subnet group
        aws elasticache create-cache-subnet-group \
            --cache-subnet-group-name ai-blockchain-analytics-cache-subnet-group \
            --cache-subnet-group-description "AI Blockchain Analytics Cache Subnet Group" \
            --subnet-ids $(echo $SUBNET_IDS | tr ',' ' ') \
            --region $AWS_REGION 2>/dev/null || true
        
        # Create Redis cluster
        aws elasticache create-cache-cluster \
            --cache-cluster-id ai-blockchain-analytics-redis \
            --cache-node-type cache.t3.medium \
            --engine redis \
            --num-cache-nodes 1 \
            --cache-subnet-group-name ai-blockchain-analytics-cache-subnet-group \
            --security-group-ids $SECURITY_GROUP_ID \
            --region $AWS_REGION
        
        log_info "Waiting for Redis cluster to be available..."
        aws elasticache wait cache-cluster-available --cache-cluster-ids ai-blockchain-analytics-redis --region $AWS_REGION
    fi
    
    log_success "ElastiCache Redis cluster ready"
}

# Update task definitions with actual values
update_task_definitions() {
    log_info "Updating task definitions..."
    
    # Get RDS endpoint
    RDS_ENDPOINT=$(aws rds describe-db-instances --db-instance-identifier ai-blockchain-analytics-postgres --region $AWS_REGION --query 'DBInstances[0].Endpoint.Address' --output text)
    
    # Get Redis endpoint
    REDIS_ENDPOINT=$(aws elasticache describe-cache-clusters --cache-cluster-id ai-blockchain-analytics-redis --show-cache-node-info --region $AWS_REGION --query 'CacheClusters[0].CacheNodes[0].Endpoint.Address' --output text)
    
    # Update task definitions
    for task_file in ecs/task-definition-*.json; do
        log_info "Updating $task_file..."
        
        # Create temporary file with substitutions
        sed "s/YOUR_ACCOUNT/$AWS_ACCOUNT_ID/g; s/YOUR_REGION/$AWS_REGION/g; s/ai-blockchain-analytics-postgres\.cluster-xxx\.us-east-1\.rds\.amazonaws\.com/$RDS_ENDPOINT/g; s/ai-blockchain-analytics-redis\.xxx\.cache\.amazonaws\.com/$REDIS_ENDPOINT/g" "$task_file" > "${task_file}.tmp"
        
        mv "${task_file}.tmp" "$task_file"
    done
    
    log_success "Task definitions updated"
}

# Create AWS Secrets Manager secrets
create_secrets() {
    log_info "Creating AWS Secrets Manager secrets..."
    
    # App key
    aws secretsmanager create-secret \
        --name "ai-blockchain-analytics/app-key" \
        --description "Laravel application key" \
        --secret-string "base64:$(openssl rand -base64 32)" \
        --region $AWS_REGION 2>/dev/null || true
    
    # Database password
    aws secretsmanager create-secret \
        --name "ai-blockchain-analytics/db-password" \
        --description "Database password" \
        --secret-string "supersecurepassword" \
        --region $AWS_REGION 2>/dev/null || true
    
    # Redis password (if using auth)
    aws secretsmanager create-secret \
        --name "ai-blockchain-analytics/redis-password" \
        --description "Redis password" \
        --secret-string "" \
        --region $AWS_REGION 2>/dev/null || true
    
    # Add other secrets as needed
    log_info "Remember to update the following secrets with actual values:"
    echo "- ai-blockchain-analytics/mailgun-secret"
    echo "- ai-blockchain-analytics/openai-api-key" 
    echo "- ai-blockchain-analytics/sentry-dsn"
    
    log_success "Secrets created (update with actual values)"
}

# Register task definitions
register_task_definitions() {
    log_info "Registering ECS task definitions..."
    
    for task_file in ecs/task-definition-*.json; do
        task_name=$(basename "$task_file" .json)
        log_info "Registering $task_name..."
        
        aws ecs register-task-definition \
            --cli-input-json "file://$task_file" \
            --region $AWS_REGION
    done
    
    log_success "Task definitions registered"
}

# Create ECS services
create_ecs_services() {
    log_info "Creating ECS services..."
    
    # Create ALB target group for app service
    create_target_group
    
    # App service
    aws ecs create-service \
        --cluster $ECS_CLUSTER_NAME \
        --service-name ai-blockchain-analytics-app \
        --task-definition ai-blockchain-analytics-app:1 \
        --desired-count 2 \
        --launch-type FARGATE \
        --platform-version LATEST \
        --network-configuration "awsvpcConfiguration={subnets=[$SUBNET_IDS],securityGroups=[$SECURITY_GROUP_ID],assignPublicIp=ENABLED}" \
        --load-balancers "targetGroupArn=$TARGET_GROUP_ARN,containerName=ai-blockchain-analytics-app,containerPort=8000" \
        --enable-logging \
        --region $AWS_REGION 2>/dev/null || log_info "App service already exists"
    
    # Worker service
    aws ecs create-service \
        --cluster $ECS_CLUSTER_NAME \
        --service-name ai-blockchain-analytics-worker \
        --task-definition ai-blockchain-analytics-worker:1 \
        --desired-count 2 \
        --launch-type FARGATE \
        --platform-version LATEST \
        --network-configuration "awsvpcConfiguration={subnets=[$SUBNET_IDS],securityGroups=[$SECURITY_GROUP_ID],assignPublicIp=ENABLED}" \
        --enable-logging \
        --region $AWS_REGION 2>/dev/null || log_info "Worker service already exists"
    
    # Scheduler service
    aws ecs create-service \
        --cluster $ECS_CLUSTER_NAME \
        --service-name ai-blockchain-analytics-scheduler \
        --task-definition ai-blockchain-analytics-scheduler:1 \
        --desired-count 1 \
        --launch-type FARGATE \
        --platform-version LATEST \
        --network-configuration "awsvpcConfiguration={subnets=[$SUBNET_IDS],securityGroups=[$SECURITY_GROUP_ID],assignPublicIp=ENABLED}" \
        --enable-logging \
        --region $AWS_REGION 2>/dev/null || log_info "Scheduler service already exists"
    
    log_success "ECS services created"
}

# Create ALB target group
create_target_group() {
    log_info "Creating Application Load Balancer target group..."
    
    TARGET_GROUP_ARN=$(aws elbv2 create-target-group \
        --name ai-blockchain-analytics-tg \
        --protocol HTTP \
        --port 8000 \
        --vpc-id $VPC_ID \
        --target-type ip \
        --health-check-path /api/health \
        --health-check-interval-seconds 30 \
        --healthy-threshold-count 2 \
        --unhealthy-threshold-count 3 \
        --region $AWS_REGION \
        --query 'TargetGroups[0].TargetGroupArn' \
        --output text 2>/dev/null) || {
        
        # Get existing target group ARN
        TARGET_GROUP_ARN=$(aws elbv2 describe-target-groups \
            --names ai-blockchain-analytics-tg \
            --region $AWS_REGION \
            --query 'TargetGroups[0].TargetGroupArn' \
            --output text)
    }
    
    log_success "Target group ready: $TARGET_GROUP_ARN"
}

# Run database migrations
run_migrations() {
    log_info "Running database migrations..."
    
    # Run a one-time task for migrations
    aws ecs run-task \
        --cluster $ECS_CLUSTER_NAME \
        --task-definition ai-blockchain-analytics-app:1 \
        --launch-type FARGATE \
        --platform-version LATEST \
        --network-configuration "awsvpcConfiguration={subnets=[$SUBNET_IDS],securityGroups=[$SECURITY_GROUP_ID],assignPublicIp=ENABLED}" \
        --overrides '{
            "containerOverrides": [
                {
                    "name": "ai-blockchain-analytics-app",
                    "command": ["php", "artisan", "migrate", "--force"]
                }
            ]
        }' \
        --region $AWS_REGION
    
    log_success "Migration task started"
}

# Setup auto scaling
setup_auto_scaling() {
    log_info "Setting up auto scaling..."
    
    # Register scalable targets
    aws application-autoscaling register-scalable-target \
        --service-namespace ecs \
        --resource-id service/$ECS_CLUSTER_NAME/ai-blockchain-analytics-app \
        --scalable-dimension ecs:service:DesiredCount \
        --min-capacity 2 \
        --max-capacity 10 \
        --region $AWS_REGION 2>/dev/null || true
    
    # Create scaling policy
    aws application-autoscaling put-scaling-policy \
        --policy-name ai-blockchain-analytics-cpu-scaling \
        --service-namespace ecs \
        --resource-id service/$ECS_CLUSTER_NAME/ai-blockchain-analytics-app \
        --scalable-dimension ecs:service:DesiredCount \
        --policy-type TargetTrackingScaling \
        --target-tracking-scaling-policy-configuration '{
            "TargetValue": 70.0,
            "PredefinedMetricSpecification": {
                "PredefinedMetricType": "ECSServiceAverageCPUUtilization"
            },
            "ScaleOutCooldown": 300,
            "ScaleInCooldown": 300
        }' \
        --region $AWS_REGION
    
    log_success "Auto scaling configured"
}

# Verify deployment
verify_deployment() {
    log_info "Verifying ECS deployment..."
    
    # List services
    aws ecs list-services --cluster $ECS_CLUSTER_NAME --region $AWS_REGION
    
    # Check service status
    aws ecs describe-services \
        --cluster $ECS_CLUSTER_NAME \
        --services ai-blockchain-analytics-app ai-blockchain-analytics-worker ai-blockchain-analytics-scheduler \
        --region $AWS_REGION \
        --query 'services[*].{Name:serviceName,Status:status,Running:runningCount,Desired:desiredCount}'
    
    log_success "Deployment verification completed"
}

# Cleanup function
cleanup() {
    log_info "Cleaning up temporary files..."
    
    # Restore original task definitions if backups exist
    for task_file in ecs/task-definition-*.json; do
        if [ -f "${task_file}.bak" ]; then
            mv "${task_file}.bak" "$task_file"
        fi
    done
}

# Main deployment function
main() {
    log_info "Starting ECS deployment for AI Blockchain Analytics..."
    
    # Set trap for cleanup
    trap cleanup EXIT
    
    # Parse arguments
    SKIP_BUILD=false
    SKIP_INFRASTRUCTURE=false
    SKIP_MIGRATIONS=false
    
    while [[ $# -gt 0 ]]; do
        case $1 in
            --skip-build)
                SKIP_BUILD=true
                shift
                ;;
            --skip-infrastructure)
                SKIP_INFRASTRUCTURE=true
                shift
                ;;
            --skip-migrations)
                SKIP_MIGRATIONS=true
                shift
                ;;
            --image-tag)
                IMAGE_TAG="$2"
                shift 2
                ;;
            --cluster-name)
                ECS_CLUSTER_NAME="$2"
                shift 2
                ;;
            -h|--help)
                echo "Usage: $0 [OPTIONS]"
                echo "Options:"
                echo "  --skip-build            Skip Docker image build and push"
                echo "  --skip-infrastructure   Skip RDS and Redis creation"
                echo "  --skip-migrations       Skip database migrations"
                echo "  --image-tag TAG         Docker image tag (default: latest)"
                echo "  --cluster-name NAME     ECS cluster name"
                echo "  -h, --help             Show this help message"
                exit 0
                ;;
            *)
                log_error "Unknown option: $1"
                exit 1
                ;;
        esac
    done
    
    # Backup original task definitions
    for task_file in ecs/task-definition-*.json; do
        cp "$task_file" "${task_file}.bak"
    done
    
    # Run deployment steps
    check_prerequisites
    create_ecr_repository
    
    if [ "$SKIP_BUILD" = false ]; then
        build_and_push_image
    fi
    
    create_ecs_cluster
    create_log_group
    create_secrets
    
    if [ "$SKIP_INFRASTRUCTURE" = false ]; then
        create_rds_instance
        create_redis_cluster
    fi
    
    update_task_definitions
    register_task_definitions
    create_ecs_services
    setup_auto_scaling
    
    if [ "$SKIP_MIGRATIONS" = false ]; then
        run_migrations
    fi
    
    verify_deployment
    
    log_success "ECS deployment completed successfully!"
    
    # Display useful information
    echo ""
    echo "=== Deployment Information ==="
    echo "Cluster: $ECS_CLUSTER_NAME"
    echo "Region: $AWS_REGION"
    echo "Image: $AWS_ACCOUNT_ID.dkr.ecr.$AWS_REGION.amazonaws.com/$ECR_REPOSITORY:$IMAGE_TAG"
    echo ""
    echo "=== Useful Commands ==="
    echo "View services: aws ecs list-services --cluster $ECS_CLUSTER_NAME --region $AWS_REGION"
    echo "View tasks: aws ecs list-tasks --cluster $ECS_CLUSTER_NAME --region $AWS_REGION"
    echo "View logs: aws logs tail /ecs/ai-blockchain-analytics --follow --region $AWS_REGION"
    echo "Scale service: aws ecs update-service --cluster $ECS_CLUSTER_NAME --service ai-blockchain-analytics-app --desired-count 5 --region $AWS_REGION"
    echo ""
}

# Run main function with all arguments
main "$@"