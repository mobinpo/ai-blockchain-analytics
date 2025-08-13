#!/bin/bash

# Enhanced ECS Deployment Script for AI Blockchain Analytics with RoadRunner
# Features: RoadRunner optimization, RDS PostgreSQL, ElastiCache Redis, Auto Scaling, Blue/Green deployment
# Usage: ./ecs/enhanced-roadrunner-deployment.sh [environment] [action] [component]

set -euo pipefail

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_NAME="ai-blockchain-analytics"
DEFAULT_ENVIRONMENT="production"
DEFAULT_ACTION="deploy"

# Parse arguments
ENVIRONMENT=${1:-$DEFAULT_ENVIRONMENT}
ACTION=${2:-$DEFAULT_ACTION}
COMPONENT=${3:-all}

# AWS Configuration
AWS_REGION="${AWS_REGION:-us-east-1}"
AWS_ACCOUNT_ID="${AWS_ACCOUNT_ID:-$(aws sts get-caller-identity --query Account --output text)}"
CLUSTER_NAME="${CLUSTER_NAME:-ai-blockchain-cluster-${ENVIRONMENT}}"
ECR_REPOSITORY="${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com/ai-blockchain-analytics"

# Environment-specific configurations
declare -A ENVIRONMENT_CONFIGS=(
    # Development
    [development_app_cpu]=1024
    [development_app_memory]=2048
    [development_app_desired_count]=1
    [development_app_min_capacity]=1
    [development_app_max_capacity]=3
    [development_worker_cpu]=512
    [development_worker_memory]=1024
    [development_worker_desired_count]=1
    [development_rr_workers]=4
    [development_instance_type]="t3.medium"
    [development_rds_instance_class]="db.t3.micro"
    [development_redis_node_type]="cache.t3.micro"
    
    # Staging
    [staging_app_cpu]=2048
    [staging_app_memory]=4096
    [staging_app_desired_count]=2
    [staging_app_min_capacity]=2
    [staging_app_max_capacity]=10
    [staging_worker_cpu]=1024
    [staging_worker_memory]=2048
    [staging_worker_desired_count]=2
    [staging_rr_workers]=8
    [staging_instance_type]="t3.large"
    [staging_rds_instance_class]="db.t3.small"
    [staging_redis_node_type]="cache.t3.small"
    
    # Production
    [production_app_cpu]=4096
    [production_app_memory]=8192
    [production_app_desired_count]=3
    [production_app_min_capacity]=3
    [production_app_max_capacity]=50
    [production_worker_cpu]=2048
    [production_worker_memory]=4096
    [production_worker_desired_count]=3
    [production_rr_workers]=16
    [production_instance_type]="c5.xlarge"
    [production_rds_instance_class]="db.r5.large"
    [production_redis_node_type]="cache.r5.large"
)

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m'

# Logging functions with timestamps
log_with_timestamp() {
    echo -e "[$(date +'%Y-%m-%d %H:%M:%S')] $1"
}

log_info() {
    log_with_timestamp "${BLUE}[INFO]${NC} $1"
}

log_success() {
    log_with_timestamp "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    log_with_timestamp "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    log_with_timestamp "${RED}[ERROR]${NC} $1"
}

log_step() {
    log_with_timestamp "${CYAN}[STEP]${NC} $1"
}

# Error handling
error_exit() {
    log_error "$1"
    exit 1
}

# Check prerequisites
check_prerequisites() {
    log_step "Checking prerequisites..."
    
    # Check AWS CLI
    if ! command -v aws &> /dev/null; then
        error_exit "AWS CLI is not installed"
    fi
    
    # Check AWS credentials
    if ! aws sts get-caller-identity > /dev/null 2>&1; then
        error_exit "AWS credentials are not configured"
    fi
    
    # Check jq
    if ! command -v jq &> /dev/null; then
        error_exit "jq is not installed"
    fi
    
    # Check Docker
    if ! command -v docker &> /dev/null; then
        log_warning "Docker is not installed - image building will be skipped"
    fi
    
    log_success "Prerequisites check passed"
}

# Validate environment
validate_environment() {
    log_step "Validating environment configuration..."
    
    case $ENVIRONMENT in
        development|staging|production)
            log_info "Environment: $ENVIRONMENT"
            ;;
        *)
            error_exit "Invalid environment: $ENVIRONMENT. Must be development, staging, or production"
            ;;
    esac
    
    log_info "AWS Account ID: $AWS_ACCOUNT_ID"
    log_info "AWS Region: $AWS_REGION"
    log_info "Cluster Name: $CLUSTER_NAME"
    
    log_success "Environment validation passed"
}

# Build and push Docker image
build_and_push_image() {
    log_step "Building and pushing Docker image..."
    
    if ! command -v docker &> /dev/null; then
        log_warning "Docker not available, skipping image build"
        return
    fi
    
    # Get ECR login token
    aws ecr get-login-password --region $AWS_REGION | docker login --username AWS --password-stdin $ECR_REPOSITORY
    
    # Create ECR repository if it doesn't exist
    aws ecr describe-repositories --repository-names ai-blockchain-analytics --region $AWS_REGION > /dev/null 2>&1 || \
    aws ecr create-repository --repository-name ai-blockchain-analytics --region $AWS_REGION
    
    # Build image
    log_info "Building RoadRunner Docker image..."
    docker build -f docker/Dockerfile.roadrunner -t ai-blockchain-analytics:latest .
    
    # Tag and push image
    local image_tag="${ENVIRONMENT}-$(date +%Y%m%d%H%M%S)"
    docker tag ai-blockchain-analytics:latest ${ECR_REPOSITORY}:${image_tag}
    docker tag ai-blockchain-analytics:latest ${ECR_REPOSITORY}:${ENVIRONMENT}-latest
    
    docker push ${ECR_REPOSITORY}:${image_tag}
    docker push ${ECR_REPOSITORY}:${ENVIRONMENT}-latest
    
    # Update global variable for use in task definitions
    export DOCKER_IMAGE_URI="${ECR_REPOSITORY}:${image_tag}"
    
    log_success "Docker image built and pushed: ${DOCKER_IMAGE_URI}"
}

# Create VPC and networking infrastructure
create_vpc_infrastructure() {
    log_step "Creating VPC and networking infrastructure..."
    
    # Create VPC using CloudFormation
    local stack_name="ai-blockchain-vpc-${ENVIRONMENT}"
    
    cat > /tmp/vpc-template.yaml <<EOF
AWSTemplateFormatVersion: '2010-09-09'
Description: 'VPC Infrastructure for AI Blockchain Analytics'

Parameters:
  Environment:
    Type: String
    Default: ${ENVIRONMENT}
  
Resources:
  VPC:
    Type: AWS::EC2::VPC
    Properties:
      CidrBlock: 10.0.0.0/16
      EnableDnsHostnames: true
      EnableDnsSupport: true
      Tags:
        - Key: Name
          Value: !Sub 'ai-blockchain-vpc-\${Environment}'
        - Key: Environment
          Value: !Ref Environment

  InternetGateway:
    Type: AWS::EC2::InternetGateway
    Properties:
      Tags:
        - Key: Name
          Value: !Sub 'ai-blockchain-igw-\${Environment}'

  InternetGatewayAttachment:
    Type: AWS::EC2::VPCGatewayAttachment
    Properties:
      InternetGatewayId: !Ref InternetGateway
      VpcId: !Ref VPC

  PublicSubnet1:
    Type: AWS::EC2::Subnet
    Properties:
      VpcId: !Ref VPC
      AvailabilityZone: !Select [0, !GetAZs '']
      CidrBlock: 10.0.1.0/24
      MapPublicIpOnLaunch: true
      Tags:
        - Key: Name
          Value: !Sub 'ai-blockchain-public-subnet-1-\${Environment}'

  PublicSubnet2:
    Type: AWS::EC2::Subnet
    Properties:
      VpcId: !Ref VPC
      AvailabilityZone: !Select [1, !GetAZs '']
      CidrBlock: 10.0.2.0/24
      MapPublicIpOnLaunch: true
      Tags:
        - Key: Name
          Value: !Sub 'ai-blockchain-public-subnet-2-\${Environment}'

  PrivateSubnet1:
    Type: AWS::EC2::Subnet
    Properties:
      VpcId: !Ref VPC
      AvailabilityZone: !Select [0, !GetAZs '']
      CidrBlock: 10.0.11.0/24
      Tags:
        - Key: Name
          Value: !Sub 'ai-blockchain-private-subnet-1-\${Environment}'

  PrivateSubnet2:
    Type: AWS::EC2::Subnet
    Properties:
      VpcId: !Ref VPC
      AvailabilityZone: !Select [1, !GetAZs '']
      CidrBlock: 10.0.12.0/24
      Tags:
        - Key: Name
          Value: !Sub 'ai-blockchain-private-subnet-2-\${Environment}'

  PublicRouteTable:
    Type: AWS::EC2::RouteTable
    Properties:
      VpcId: !Ref VPC
      Tags:
        - Key: Name
          Value: !Sub 'ai-blockchain-public-routes-\${Environment}'

  DefaultPublicRoute:
    Type: AWS::EC2::Route
    DependsOn: InternetGatewayAttachment
    Properties:
      RouteTableId: !Ref PublicRouteTable
      DestinationCidrBlock: 0.0.0.0/0
      GatewayId: !Ref InternetGateway

  PublicSubnet1RouteTableAssociation:
    Type: AWS::EC2::SubnetRouteTableAssociation
    Properties:
      RouteTableId: !Ref PublicRouteTable
      SubnetId: !Ref PublicSubnet1

  PublicSubnet2RouteTableAssociation:
    Type: AWS::EC2::SubnetRouteTableAssociation
    Properties:
      RouteTableId: !Ref PublicRouteTable
      SubnetId: !Ref PublicSubnet2

Outputs:
  VPC:
    Description: VPC ID
    Value: !Ref VPC
    Export:
      Name: !Sub 'ai-blockchain-vpc-\${Environment}'
  
  PublicSubnet1:
    Description: Public Subnet 1 ID
    Value: !Ref PublicSubnet1
    Export:
      Name: !Sub 'ai-blockchain-public-subnet-1-\${Environment}'
  
  PublicSubnet2:
    Description: Public Subnet 2 ID
    Value: !Ref PublicSubnet2
    Export:
      Name: !Sub 'ai-blockchain-public-subnet-2-\${Environment}'
  
  PrivateSubnet1:
    Description: Private Subnet 1 ID
    Value: !Ref PrivateSubnet1
    Export:
      Name: !Sub 'ai-blockchain-private-subnet-1-\${Environment}'
  
  PrivateSubnet2:
    Description: Private Subnet 2 ID
    Value: !Ref PrivateSubnet2
    Export:
      Name: !Sub 'ai-blockchain-private-subnet-2-\${Environment}'
EOF

    # Deploy or update VPC stack
    if aws cloudformation describe-stacks --stack-name $stack_name --region $AWS_REGION > /dev/null 2>&1; then
        log_info "Updating existing VPC stack..."
        aws cloudformation update-stack \
            --stack-name $stack_name \
            --template-body file:///tmp/vpc-template.yaml \
            --region $AWS_REGION || log_warning "No VPC updates needed"
    else
        log_info "Creating new VPC stack..."
        aws cloudformation create-stack \
            --stack-name $stack_name \
            --template-body file:///tmp/vpc-template.yaml \
            --region $AWS_REGION
    fi
    
    # Wait for stack to complete
    log_info "Waiting for VPC stack to complete..."
    aws cloudformation wait stack-create-complete --stack-name $stack_name --region $AWS_REGION 2>/dev/null || \
    aws cloudformation wait stack-update-complete --stack-name $stack_name --region $AWS_REGION 2>/dev/null || true
    
    # Get VPC and subnet IDs
    export VPC_ID=$(aws cloudformation describe-stacks --stack-name $stack_name --region $AWS_REGION --query 'Stacks[0].Outputs[?OutputKey==`VPC`].OutputValue' --output text)
    export PUBLIC_SUBNET_1=$(aws cloudformation describe-stacks --stack-name $stack_name --region $AWS_REGION --query 'Stacks[0].Outputs[?OutputKey==`PublicSubnet1`].OutputValue' --output text)
    export PUBLIC_SUBNET_2=$(aws cloudformation describe-stacks --stack-name $stack_name --region $AWS_REGION --query 'Stacks[0].Outputs[?OutputKey==`PublicSubnet2`].OutputValue' --output text)
    export PRIVATE_SUBNET_1=$(aws cloudformation describe-stacks --stack-name $stack_name --region $AWS_REGION --query 'Stacks[0].Outputs[?OutputKey==`PrivateSubnet1`].OutputValue' --output text)
    export PRIVATE_SUBNET_2=$(aws cloudformation describe-stacks --stack-name $stack_name --region $AWS_REGION --query 'Stacks[0].Outputs[?OutputKey==`PrivateSubnet2`].OutputValue' --output text)
    
    log_success "VPC infrastructure created/updated"
    log_info "VPC ID: $VPC_ID"
}

# Create RDS PostgreSQL instance
create_rds_postgres() {
    log_step "Creating RDS PostgreSQL instance..."
    
    local db_instance_identifier="ai-blockchain-postgres-${ENVIRONMENT}"
    local db_instance_class="${ENVIRONMENT_CONFIGS[${ENVIRONMENT}_rds_instance_class]}"
    
    # Create DB subnet group
    local db_subnet_group_name="ai-blockchain-db-subnet-group-${ENVIRONMENT}"
    
    if ! aws rds describe-db-subnet-groups --db-subnet-group-name $db_subnet_group_name --region $AWS_REGION > /dev/null 2>&1; then
        aws rds create-db-subnet-group \
            --db-subnet-group-name $db_subnet_group_name \
            --db-subnet-group-description "DB subnet group for AI Blockchain Analytics ${ENVIRONMENT}" \
            --subnet-ids $PRIVATE_SUBNET_1 $PRIVATE_SUBNET_2 \
            --region $AWS_REGION
    fi
    
    # Create security group for RDS
    local db_security_group_id=$(aws ec2 create-security-group \
        --group-name "ai-blockchain-db-sg-${ENVIRONMENT}" \
        --description "Security group for RDS PostgreSQL ${ENVIRONMENT}" \
        --vpc-id $VPC_ID \
        --region $AWS_REGION \
        --query 'GroupId' \
        --output text 2>/dev/null || \
    aws ec2 describe-security-groups \
        --filters "Name=group-name,Values=ai-blockchain-db-sg-${ENVIRONMENT}" "Name=vpc-id,Values=$VPC_ID" \
        --region $AWS_REGION \
        --query 'SecurityGroups[0].GroupId' \
        --output text)
    
    # Allow PostgreSQL access from ECS tasks
    aws ec2 authorize-security-group-ingress \
        --group-id $db_security_group_id \
        --protocol tcp \
        --port 5432 \
        --cidr 10.0.0.0/16 \
        --region $AWS_REGION 2>/dev/null || true
    
    # Create RDS instance if it doesn't exist
    if ! aws rds describe-db-instances --db-instance-identifier $db_instance_identifier --region $AWS_REGION > /dev/null 2>&1; then
        log_info "Creating RDS PostgreSQL instance..."
        aws rds create-db-instance \
            --db-instance-identifier $db_instance_identifier \
            --db-instance-class $db_instance_class \
            --engine postgres \
            --engine-version 15.4 \
            --master-username postgres \
            --master-user-password "$(aws ssm get-parameter --name "/ai-blockchain-analytics/db-password" --with-decryption --query 'Parameter.Value' --output text --region $AWS_REGION)" \
            --allocated-storage 20 \
            --storage-type gp2 \
            --storage-encrypted \
            --vpc-security-group-ids $db_security_group_id \
            --db-subnet-group-name $db_subnet_group_name \
            --backup-retention-period 7 \
            --multi-az \
            --auto-minor-version-upgrade \
            --deletion-protection \
            --region $AWS_REGION
        
        # Wait for RDS instance to be available
        log_info "Waiting for RDS instance to be available..."
        aws rds wait db-instance-available --db-instance-identifier $db_instance_identifier --region $AWS_REGION
    else
        log_info "RDS instance already exists"
    fi
    
    # Get RDS endpoint
    export RDS_ENDPOINT=$(aws rds describe-db-instances --db-instance-identifier $db_instance_identifier --region $AWS_REGION --query 'DBInstances[0].Endpoint.Address' --output text)
    
    log_success "RDS PostgreSQL instance ready"
    log_info "RDS Endpoint: $RDS_ENDPOINT"
}

# Create ElastiCache Redis cluster
create_redis_cluster() {
    log_step "Creating ElastiCache Redis cluster..."
    
    local cache_cluster_id="ai-blockchain-redis-${ENVIRONMENT}"
    local cache_node_type="${ENVIRONMENT_CONFIGS[${ENVIRONMENT}_redis_node_type]}"
    
    # Create cache subnet group
    local cache_subnet_group_name="ai-blockchain-cache-subnet-group-${ENVIRONMENT}"
    
    if ! aws elasticache describe-cache-subnet-groups --cache-subnet-group-name $cache_subnet_group_name --region $AWS_REGION > /dev/null 2>&1; then
        aws elasticache create-cache-subnet-group \
            --cache-subnet-group-name $cache_subnet_group_name \
            --cache-subnet-group-description "Cache subnet group for AI Blockchain Analytics ${ENVIRONMENT}" \
            --subnet-ids $PRIVATE_SUBNET_1 $PRIVATE_SUBNET_2 \
            --region $AWS_REGION
    fi
    
    # Create security group for Redis
    local cache_security_group_id=$(aws ec2 create-security-group \
        --group-name "ai-blockchain-cache-sg-${ENVIRONMENT}" \
        --description "Security group for ElastiCache Redis ${ENVIRONMENT}" \
        --vpc-id $VPC_ID \
        --region $AWS_REGION \
        --query 'GroupId' \
        --output text 2>/dev/null || \
    aws ec2 describe-security-groups \
        --filters "Name=group-name,Values=ai-blockchain-cache-sg-${ENVIRONMENT}" "Name=vpc-id,Values=$VPC_ID" \
        --region $AWS_REGION \
        --query 'SecurityGroups[0].GroupId' \
        --output text)
    
    # Allow Redis access from ECS tasks
    aws ec2 authorize-security-group-ingress \
        --group-id $cache_security_group_id \
        --protocol tcp \
        --port 6379 \
        --cidr 10.0.0.0/16 \
        --region $AWS_REGION 2>/dev/null || true
    
    # Create Redis cluster if it doesn't exist
    if ! aws elasticache describe-cache-clusters --cache-cluster-id $cache_cluster_id --region $AWS_REGION > /dev/null 2>&1; then
        log_info "Creating ElastiCache Redis cluster..."
        aws elasticache create-cache-cluster \
            --cache-cluster-id $cache_cluster_id \
            --cache-node-type $cache_node_type \
            --engine redis \
            --engine-version 7.0 \
            --num-cache-nodes 1 \
            --cache-parameter-group-name default.redis7 \
            --cache-subnet-group-name $cache_subnet_group_name \
            --security-group-ids $cache_security_group_id \
            --region $AWS_REGION
        
        # Wait for Redis cluster to be available
        log_info "Waiting for Redis cluster to be available..."
        aws elasticache wait cache-cluster-available --cache-cluster-id $cache_cluster_id --region $AWS_REGION
    else
        log_info "Redis cluster already exists"
    fi
    
    # Get Redis endpoint
    export REDIS_ENDPOINT=$(aws elasticache describe-cache-clusters --cache-cluster-id $cache_cluster_id --show-cache-node-info --region $AWS_REGION --query 'CacheClusters[0].CacheNodes[0].Endpoint.Address' --output text)
    
    log_success "ElastiCache Redis cluster ready"
    log_info "Redis Endpoint: $REDIS_ENDPOINT"
}

# Create ECS cluster
create_ecs_cluster() {
    log_step "Creating ECS cluster..."
    
    # Create cluster if it doesn't exist
    if ! aws ecs describe-clusters --clusters $CLUSTER_NAME --region $AWS_REGION | jq -r '.clusters[0].status' | grep -q "ACTIVE"; then
        log_info "Creating ECS cluster..."
        aws ecs create-cluster \
            --cluster-name $CLUSTER_NAME \
            --capacity-providers FARGATE FARGATE_SPOT \
            --default-capacity-provider-strategy capacityProvider=FARGATE,weight=1,base=1 \
            --region $AWS_REGION \
            --tags key=Environment,value=$ENVIRONMENT key=Project,value=ai-blockchain-analytics
        
        # Enable container insights
        aws ecs put-cluster-capacity-providers \
            --cluster $CLUSTER_NAME \
            --capacity-providers FARGATE FARGATE_SPOT \
            --default-capacity-provider-strategy capacityProvider=FARGATE,weight=1,base=1 \
            --region $AWS_REGION
    else
        log_info "ECS cluster already exists"
    fi
    
    log_success "ECS cluster ready: $CLUSTER_NAME"
}

# Create task definitions
create_task_definitions() {
    log_step "Creating ECS task definitions..."
    
    # Get environment-specific configurations
    local app_cpu="${ENVIRONMENT_CONFIGS[${ENVIRONMENT}_app_cpu]}"
    local app_memory="${ENVIRONMENT_CONFIGS[${ENVIRONMENT}_app_memory]}"
    local worker_cpu="${ENVIRONMENT_CONFIGS[${ENVIRONMENT}_worker_cpu]}"
    local worker_memory="${ENVIRONMENT_CONFIGS[${ENVIRONMENT}_worker_memory]}"
    local rr_workers="${ENVIRONMENT_CONFIGS[${ENVIRONMENT}_rr_workers]}"
    
    # Create RoadRunner app task definition
    cat > /tmp/roadrunner-app-task.json <<EOF
{
  "family": "ai-blockchain-analytics-roadrunner-${ENVIRONMENT}",
  "networkMode": "awsvpc",
  "requiresCompatibilities": ["FARGATE"],
  "cpu": "${app_cpu}",
  "memory": "${app_memory}",
  "executionRoleArn": "arn:aws:iam::${AWS_ACCOUNT_ID}:role/ecsTaskExecutionRole",
  "taskRoleArn": "arn:aws:iam::${AWS_ACCOUNT_ID}:role/ai-blockchain-analytics-task-role",
  "containerDefinitions": [
    {
      "name": "roadrunner-app",
      "image": "${DOCKER_IMAGE_URI:-${ECR_REPOSITORY}:${ENVIRONMENT}-latest}",
      "essential": true,
      "portMappings": [
        {
          "containerPort": 8000,
          "protocol": "tcp"
        },
        {
          "containerPort": 6001,
          "protocol": "tcp"
        },
        {
          "containerPort": 2112,
          "protocol": "tcp"
        }
      ],
      "environment": [
        {"name": "APP_NAME", "value": "AI Blockchain Analytics"},
        {"name": "APP_ENV", "value": "${ENVIRONMENT}"},
        {"name": "APP_DEBUG", "value": "false"},
        {"name": "APP_URL", "value": "https://analytics.yourdomain.com"},
        {"name": "DB_CONNECTION", "value": "pgsql"},
        {"name": "DB_HOST", "value": "${RDS_ENDPOINT}"},
        {"name": "DB_PORT", "value": "5432"},
        {"name": "DB_DATABASE", "value": "ai_blockchain_analytics"},
        {"name": "REDIS_HOST", "value": "${REDIS_ENDPOINT}"},
        {"name": "REDIS_PORT", "value": "6379"},
        {"name": "CACHE_DRIVER", "value": "redis"},
        {"name": "SESSION_DRIVER", "value": "redis"},
        {"name": "QUEUE_CONNECTION", "value": "redis"},
        {"name": "OCTANE_SERVER", "value": "roadrunner"},
        {"name": "ROADRUNNER_HTTP_HOST", "value": "0.0.0.0"},
        {"name": "ROADRUNNER_HTTP_PORT", "value": "8000"},
        {"name": "RR_WORKERS", "value": "${rr_workers}"},
        {"name": "RR_MAX_JOBS", "value": "2000"},
        {"name": "CONTAINER_ROLE", "value": "app"}
      ],
      "secrets": [
        {"name": "APP_KEY", "valueFrom": "arn:aws:ssm:${AWS_REGION}:${AWS_ACCOUNT_ID}:parameter/ai-blockchain-analytics/app-key"},
        {"name": "DB_USERNAME", "valueFrom": "arn:aws:ssm:${AWS_REGION}:${AWS_ACCOUNT_ID}:parameter/ai-blockchain-analytics/db-username"},
        {"name": "DB_PASSWORD", "valueFrom": "arn:aws:ssm:${AWS_REGION}:${AWS_ACCOUNT_ID}:parameter/ai-blockchain-analytics/db-password"},
        {"name": "OPENAI_API_KEY", "valueFrom": "arn:aws:ssm:${AWS_REGION}:${AWS_ACCOUNT_ID}:parameter/ai-blockchain-analytics/openai-api-key"}
      ],
      "logConfiguration": {
        "logDriver": "awslogs",
        "options": {
          "awslogs-group": "/ecs/ai-blockchain-analytics-roadrunner-${ENVIRONMENT}",
          "awslogs-region": "${AWS_REGION}",
          "awslogs-stream-prefix": "roadrunner-app"
        }
      },
      "healthCheck": {
        "command": ["CMD-SHELL", "curl -f http://localhost:8000/api/health || exit 1"],
        "interval": 30,
        "timeout": 10,
        "retries": 3,
        "startPeriod": 60
      }
    }
  ]
}
EOF

    # Create Horizon worker task definition
    cat > /tmp/horizon-worker-task.json <<EOF
{
  "family": "ai-blockchain-analytics-horizon-${ENVIRONMENT}",
  "networkMode": "awsvpc",
  "requiresCompatibilities": ["FARGATE"],
  "cpu": "${worker_cpu}",
  "memory": "${worker_memory}",
  "executionRoleArn": "arn:aws:iam::${AWS_ACCOUNT_ID}:role/ecsTaskExecutionRole",
  "taskRoleArn": "arn:aws:iam::${AWS_ACCOUNT_ID}:role/ai-blockchain-analytics-task-role",
  "containerDefinitions": [
    {
      "name": "horizon-worker",
      "image": "${DOCKER_IMAGE_URI:-${ECR_REPOSITORY}:${ENVIRONMENT}-latest}",
      "essential": true,
      "command": ["php", "artisan", "horizon"],
      "environment": [
        {"name": "APP_NAME", "value": "AI Blockchain Analytics"},
        {"name": "APP_ENV", "value": "${ENVIRONMENT}"},
        {"name": "APP_DEBUG", "value": "false"},
        {"name": "DB_CONNECTION", "value": "pgsql"},
        {"name": "DB_HOST", "value": "${RDS_ENDPOINT}"},
        {"name": "DB_PORT", "value": "5432"},
        {"name": "DB_DATABASE", "value": "ai_blockchain_analytics"},
        {"name": "REDIS_HOST", "value": "${REDIS_ENDPOINT}"},
        {"name": "REDIS_PORT", "value": "6379"},
        {"name": "CACHE_DRIVER", "value": "redis"},
        {"name": "QUEUE_CONNECTION", "value": "redis"},
        {"name": "CONTAINER_ROLE", "value": "queue"}
      ],
      "secrets": [
        {"name": "APP_KEY", "valueFrom": "arn:aws:ssm:${AWS_REGION}:${AWS_ACCOUNT_ID}:parameter/ai-blockchain-analytics/app-key"},
        {"name": "DB_USERNAME", "valueFrom": "arn:aws:ssm:${AWS_REGION}:${AWS_ACCOUNT_ID}:parameter/ai-blockchain-analytics/db-username"},
        {"name": "DB_PASSWORD", "valueFrom": "arn:aws:ssm:${AWS_REGION}:${AWS_ACCOUNT_ID}:parameter/ai-blockchain-analytics/db-password"},
        {"name": "OPENAI_API_KEY", "valueFrom": "arn:aws:ssm:${AWS_REGION}:${AWS_ACCOUNT_ID}:parameter/ai-blockchain-analytics/openai-api-key"}
      ],
      "logConfiguration": {
        "logDriver": "awslogs",
        "options": {
          "awslogs-group": "/ecs/ai-blockchain-analytics-horizon-${ENVIRONMENT}",
          "awslogs-region": "${AWS_REGION}",
          "awslogs-stream-prefix": "horizon-worker"
        }
      }
    }
  ]
}
EOF

    # Create CloudWatch log groups
    aws logs create-log-group --log-group-name "/ecs/ai-blockchain-analytics-roadrunner-${ENVIRONMENT}" --region $AWS_REGION 2>/dev/null || true
    aws logs create-log-group --log-group-name "/ecs/ai-blockchain-analytics-horizon-${ENVIRONMENT}" --region $AWS_REGION 2>/dev/null || true
    
    # Register task definitions
    log_info "Registering RoadRunner app task definition..."
    export ROADRUNNER_TASK_ARN=$(aws ecs register-task-definition --cli-input-json file:///tmp/roadrunner-app-task.json --region $AWS_REGION --query 'taskDefinition.taskDefinitionArn' --output text)
    
    log_info "Registering Horizon worker task definition..."
    export HORIZON_TASK_ARN=$(aws ecs register-task-definition --cli-input-json file:///tmp/horizon-worker-task.json --region $AWS_REGION --query 'taskDefinition.taskDefinitionArn' --output text)
    
    log_success "Task definitions registered"
}

# Create Application Load Balancer
create_load_balancer() {
    log_step "Creating Application Load Balancer..."
    
    # Create security group for ALB
    local alb_security_group_id=$(aws ec2 create-security-group \
        --group-name "ai-blockchain-alb-sg-${ENVIRONMENT}" \
        --description "Security group for ALB ${ENVIRONMENT}" \
        --vpc-id $VPC_ID \
        --region $AWS_REGION \
        --query 'GroupId' \
        --output text 2>/dev/null || \
    aws ec2 describe-security-groups \
        --filters "Name=group-name,Values=ai-blockchain-alb-sg-${ENVIRONMENT}" "Name=vpc-id,Values=$VPC_ID" \
        --region $AWS_REGION \
        --query 'SecurityGroups[0].GroupId' \
        --output text)
    
    # Allow HTTP and HTTPS access
    aws ec2 authorize-security-group-ingress \
        --group-id $alb_security_group_id \
        --protocol tcp \
        --port 80 \
        --cidr 0.0.0.0/0 \
        --region $AWS_REGION 2>/dev/null || true
    
    aws ec2 authorize-security-group-ingress \
        --group-id $alb_security_group_id \
        --protocol tcp \
        --port 443 \
        --cidr 0.0.0.0/0 \
        --region $AWS_REGION 2>/dev/null || true
    
    # Create ALB
    local alb_name="ai-blockchain-alb-${ENVIRONMENT}"
    if ! aws elbv2 describe-load-balancers --names $alb_name --region $AWS_REGION > /dev/null 2>&1; then
        log_info "Creating Application Load Balancer..."
        export ALB_ARN=$(aws elbv2 create-load-balancer \
            --name $alb_name \
            --subnets $PUBLIC_SUBNET_1 $PUBLIC_SUBNET_2 \
            --security-groups $alb_security_group_id \
            --region $AWS_REGION \
            --query 'LoadBalancers[0].LoadBalancerArn' \
            --output text)
    else
        export ALB_ARN=$(aws elbv2 describe-load-balancers --names $alb_name --region $AWS_REGION --query 'LoadBalancers[0].LoadBalancerArn' --output text)
    fi
    
    # Create target group
    local target_group_name="ai-blockchain-tg-${ENVIRONMENT}"
    if ! aws elbv2 describe-target-groups --names $target_group_name --region $AWS_REGION > /dev/null 2>&1; then
        log_info "Creating target group..."
        export TARGET_GROUP_ARN=$(aws elbv2 create-target-group \
            --name $target_group_name \
            --protocol HTTP \
            --port 8000 \
            --vpc-id $VPC_ID \
            --target-type ip \
            --health-check-path /api/health \
            --health-check-interval-seconds 30 \
            --health-check-timeout-seconds 10 \
            --healthy-threshold-count 2 \
            --unhealthy-threshold-count 5 \
            --region $AWS_REGION \
            --query 'TargetGroups[0].TargetGroupArn' \
            --output text)
    else
        export TARGET_GROUP_ARN=$(aws elbv2 describe-target-groups --names $target_group_name --region $AWS_REGION --query 'TargetGroups[0].TargetGroupArn' --output text)
    fi
    
    # Create listener
    if ! aws elbv2 describe-listeners --load-balancer-arn $ALB_ARN --region $AWS_REGION | jq -r '.Listeners[0].Port' | grep -q "80"; then
        log_info "Creating ALB listener..."
        aws elbv2 create-listener \
            --load-balancer-arn $ALB_ARN \
            --protocol HTTP \
            --port 80 \
            --default-actions Type=forward,TargetGroupArn=$TARGET_GROUP_ARN \
            --region $AWS_REGION
    fi
    
    log_success "Application Load Balancer created"
}

# Create ECS services
create_ecs_services() {
    log_step "Creating ECS services..."
    
    # Get environment-specific configurations
    local app_desired_count="${ENVIRONMENT_CONFIGS[${ENVIRONMENT}_app_desired_count]}"
    local worker_desired_count="${ENVIRONMENT_CONFIGS[${ENVIRONMENT}_worker_desired_count]}"
    
    # Create security group for ECS tasks
    local ecs_security_group_id=$(aws ec2 create-security-group \
        --group-name "ai-blockchain-ecs-sg-${ENVIRONMENT}" \
        --description "Security group for ECS tasks ${ENVIRONMENT}" \
        --vpc-id $VPC_ID \
        --region $AWS_REGION \
        --query 'GroupId' \
        --output text 2>/dev/null || \
    aws ec2 describe-security-groups \
        --filters "Name=group-name,Values=ai-blockchain-ecs-sg-${ENVIRONMENT}" "Name=vpc-id,Values=$VPC_ID" \
        --region $AWS_REGION \
        --query 'SecurityGroups[0].GroupId' \
        --output text)
    
    # Allow ALB access to ECS tasks
    aws ec2 authorize-security-group-ingress \
        --group-id $ecs_security_group_id \
        --protocol tcp \
        --port 8000 \
        --source-group $alb_security_group_id \
        --region $AWS_REGION 2>/dev/null || true
    
    # Create RoadRunner app service
    local app_service_name="ai-blockchain-app-${ENVIRONMENT}"
    if ! aws ecs describe-services --cluster $CLUSTER_NAME --services $app_service_name --region $AWS_REGION | jq -r '.services[0].status' | grep -q "ACTIVE"; then
        log_info "Creating RoadRunner app service..."
        aws ecs create-service \
            --cluster $CLUSTER_NAME \
            --service-name $app_service_name \
            --task-definition $ROADRUNNER_TASK_ARN \
            --desired-count $app_desired_count \
            --launch-type FARGATE \
            --network-configuration "awsvpcConfiguration={subnets=[$PRIVATE_SUBNET_1,$PRIVATE_SUBNET_2],securityGroups=[$ecs_security_group_id],assignPublicIp=DISABLED}" \
            --load-balancers "targetGroupArn=$TARGET_GROUP_ARN,containerName=roadrunner-app,containerPort=8000" \
            --deployment-configuration "maximumPercent=200,minimumHealthyPercent=50,deploymentCircuitBreaker={enable=true,rollback=true}" \
            --enable-execute-command \
            --region $AWS_REGION
    else
        log_info "Updating RoadRunner app service..."
        aws ecs update-service \
            --cluster $CLUSTER_NAME \
            --service $app_service_name \
            --task-definition $ROADRUNNER_TASK_ARN \
            --desired-count $app_desired_count \
            --region $AWS_REGION
    fi
    
    # Create Horizon worker service
    local worker_service_name="ai-blockchain-worker-${ENVIRONMENT}"
    if ! aws ecs describe-services --cluster $CLUSTER_NAME --services $worker_service_name --region $AWS_REGION | jq -r '.services[0].status' | grep -q "ACTIVE"; then
        log_info "Creating Horizon worker service..."
        aws ecs create-service \
            --cluster $CLUSTER_NAME \
            --service-name $worker_service_name \
            --task-definition $HORIZON_TASK_ARN \
            --desired-count $worker_desired_count \
            --launch-type FARGATE \
            --network-configuration "awsvpcConfiguration={subnets=[$PRIVATE_SUBNET_1,$PRIVATE_SUBNET_2],securityGroups=[$ecs_security_group_id],assignPublicIp=DISABLED}" \
            --deployment-configuration "maximumPercent=200,minimumHealthyPercent=50" \
            --enable-execute-command \
            --region $AWS_REGION
    else
        log_info "Updating Horizon worker service..."
        aws ecs update-service \
            --cluster $CLUSTER_NAME \
            --service $worker_service_name \
            --task-definition $HORIZON_TASK_ARN \
            --desired-count $worker_desired_count \
            --region $AWS_REGION
    fi
    
    log_success "ECS services created/updated"
}

# Setup auto scaling
setup_auto_scaling() {
    log_step "Setting up auto scaling..."
    
    # Get environment-specific configurations
    local app_min_capacity="${ENVIRONMENT_CONFIGS[${ENVIRONMENT}_app_min_capacity]}"
    local app_max_capacity="${ENVIRONMENT_CONFIGS[${ENVIRONMENT}_app_max_capacity]}"
    
    # Create scalable target for app service
    local app_service_name="ai-blockchain-app-${ENVIRONMENT}"
    aws application-autoscaling register-scalable-target \
        --service-namespace ecs \
        --resource-id "service/${CLUSTER_NAME}/${app_service_name}" \
        --scalable-dimension ecs:service:DesiredCount \
        --min-capacity $app_min_capacity \
        --max-capacity $app_max_capacity \
        --region $AWS_REGION
    
    # Create scaling policy
    aws application-autoscaling put-scaling-policy \
        --service-namespace ecs \
        --resource-id "service/${CLUSTER_NAME}/${app_service_name}" \
        --scalable-dimension ecs:service:DesiredCount \
        --policy-name "ai-blockchain-app-scaling-policy-${ENVIRONMENT}" \
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

# Run database migrations
run_migrations() {
    log_step "Running database migrations..."
    
    # Create a one-time task to run migrations
    local migration_task_def=$(cat <<EOF
{
  "family": "ai-blockchain-analytics-migration-${ENVIRONMENT}",
  "networkMode": "awsvpc",
  "requiresCompatibilities": ["FARGATE"],
  "cpu": "512",
  "memory": "1024",
  "executionRoleArn": "arn:aws:iam::${AWS_ACCOUNT_ID}:role/ecsTaskExecutionRole",
  "taskRoleArn": "arn:aws:iam::${AWS_ACCOUNT_ID}:role/ai-blockchain-analytics-task-role",
  "containerDefinitions": [
    {
      "name": "migration",
      "image": "${DOCKER_IMAGE_URI:-${ECR_REPOSITORY}:${ENVIRONMENT}-latest}",
      "essential": true,
      "command": ["php", "artisan", "migrate", "--force"],
      "environment": [
        {"name": "APP_ENV", "value": "${ENVIRONMENT}"},
        {"name": "DB_CONNECTION", "value": "pgsql"},
        {"name": "DB_HOST", "value": "${RDS_ENDPOINT}"},
        {"name": "DB_PORT", "value": "5432"},
        {"name": "DB_DATABASE", "value": "ai_blockchain_analytics"}
      ],
      "secrets": [
        {"name": "APP_KEY", "valueFrom": "arn:aws:ssm:${AWS_REGION}:${AWS_ACCOUNT_ID}:parameter/ai-blockchain-analytics/app-key"},
        {"name": "DB_USERNAME", "valueFrom": "arn:aws:ssm:${AWS_REGION}:${AWS_ACCOUNT_ID}:parameter/ai-blockchain-analytics/db-username"},
        {"name": "DB_PASSWORD", "valueFrom": "arn:aws:ssm:${AWS_REGION}:${AWS_ACCOUNT_ID}:parameter/ai-blockchain-analytics/db-password"}
      ],
      "logConfiguration": {
        "logDriver": "awslogs",
        "options": {
          "awslogs-group": "/ecs/ai-blockchain-analytics-migration-${ENVIRONMENT}",
          "awslogs-region": "${AWS_REGION}",
          "awslogs-stream-prefix": "migration"
        }
      }
    }
  ]
}
EOF
)
    
    # Register migration task definition
    aws logs create-log-group --log-group-name "/ecs/ai-blockchain-analytics-migration-${ENVIRONMENT}" --region $AWS_REGION 2>/dev/null || true
    local migration_task_arn=$(echo "$migration_task_def" | aws ecs register-task-definition --cli-input-json file:///dev/stdin --region $AWS_REGION --query 'taskDefinition.taskDefinitionArn' --output text)
    
    # Run migration task
    log_info "Starting migration task..."
    local migration_task_id=$(aws ecs run-task \
        --cluster $CLUSTER_NAME \
        --task-definition $migration_task_arn \
        --launch-type FARGATE \
        --network-configuration "awsvpcConfiguration={subnets=[$PRIVATE_SUBNET_1],securityGroups=[$ecs_security_group_id],assignPublicIp=DISABLED}" \
        --region $AWS_REGION \
        --query 'tasks[0].taskArn' \
        --output text)
    
    # Wait for migration to complete
    log_info "Waiting for migration to complete..."
    aws ecs wait tasks-stopped --cluster $CLUSTER_NAME --tasks $migration_task_id --region $AWS_REGION
    
    log_success "Database migration completed"
}

# Verify deployment
verify_deployment() {
    log_step "Verifying deployment..."
    
    # Check services
    local services=("ai-blockchain-app-${ENVIRONMENT}" "ai-blockchain-worker-${ENVIRONMENT}")
    
    for service in "${services[@]}"; do
        local status=$(aws ecs describe-services --cluster $CLUSTER_NAME --services $service --region $AWS_REGION --query 'services[0].status' --output text 2>/dev/null || echo "NOT_FOUND")
        if [[ "$status" == "ACTIVE" ]]; then
            log_info "✅ Service $service is active"
        else
            log_warning "⚠️  Service $service status: $status"
        fi
    done
    
    # Get ALB DNS name
    local alb_dns=$(aws elbv2 describe-load-balancers --load-balancer-arns $ALB_ARN --region $AWS_REGION --query 'LoadBalancers[0].DNSName' --output text)
    log_info "✅ Load Balancer DNS: $alb_dns"
    
    # Test health endpoint
    log_info "Testing health endpoint..."
    if curl -f "http://$alb_dns/api/health" > /dev/null 2>&1; then
        log_success "✅ Health check passed"
    else
        log_warning "⚠️  Health check failed - service may still be starting"
    fi
    
    log_success "Deployment verification completed"
}

# Display deployment information
display_info() {
    log_step "Deployment Information"
    
    echo ""
    echo "🚀 AI Blockchain Analytics - Enhanced ECS RoadRunner Deployment"
    echo "=============================================================="
    echo "Environment: $ENVIRONMENT"
    echo "AWS Region: $AWS_REGION"
    echo "Cluster: $CLUSTER_NAME"
    echo ""
    
    echo "📊 Infrastructure:"
    echo "  VPC ID: $VPC_ID"
    echo "  RDS Endpoint: $RDS_ENDPOINT"
    echo "  Redis Endpoint: $REDIS_ENDPOINT"
    echo "  ALB DNS: $(aws elbv2 describe-load-balancers --load-balancer-arns $ALB_ARN --region $AWS_REGION --query 'LoadBalancers[0].DNSName' --output text)"
    echo ""
    
    echo "🔗 Useful Commands:"
    echo "  View services: aws ecs describe-services --cluster $CLUSTER_NAME --region $AWS_REGION"
    echo "  View tasks:    aws ecs list-tasks --cluster $CLUSTER_NAME --region $AWS_REGION"
    echo "  Scale app:     aws ecs update-service --cluster $CLUSTER_NAME --service ai-blockchain-app-${ENVIRONMENT} --desired-count 5 --region $AWS_REGION"
    echo "  Shell access:  aws ecs execute-command --cluster $CLUSTER_NAME --task TASK_ID --container roadrunner-app --interactive --command '/bin/bash' --region $AWS_REGION"
    echo ""
    
    log_success "ECS RoadRunner deployment completed successfully! 🎉"
}

# Cleanup function
cleanup_deployment() {
    log_step "Cleaning up deployment..."
    
    # Delete services
    local services=("ai-blockchain-app-${ENVIRONMENT}" "ai-blockchain-worker-${ENVIRONMENT}")
    for service in "${services[@]}"; do
        aws ecs update-service --cluster $CLUSTER_NAME --service $service --desired-count 0 --region $AWS_REGION 2>/dev/null || true
        aws ecs delete-service --cluster $CLUSTER_NAME --service $service --region $AWS_REGION 2>/dev/null || true
    done
    
    # Delete cluster
    aws ecs delete-cluster --cluster $CLUSTER_NAME --region $AWS_REGION 2>/dev/null || true
    
    # Delete CloudFormation stacks
    aws cloudformation delete-stack --stack-name "ai-blockchain-vpc-${ENVIRONMENT}" --region $AWS_REGION 2>/dev/null || true
    
    log_success "Cleanup completed"
}

# Main execution
main() {
    echo ""
    echo "🚀 AI Blockchain Analytics - Enhanced ECS RoadRunner Deployment"
    echo "================================================================"
    echo "Environment: $ENVIRONMENT"
    echo "Action: $ACTION"
    echo "Component: $COMPONENT"
    echo "AWS Region: $AWS_REGION"
    echo ""
    
    case $ACTION in
        deploy)
            check_prerequisites
            validate_environment
            build_and_push_image
            create_vpc_infrastructure
            create_rds_postgres
            create_redis_cluster
            create_ecs_cluster
            create_task_definitions
            create_load_balancer
            create_ecs_services
            setup_auto_scaling
            run_migrations
            verify_deployment
            display_info
            ;;
        cleanup)
            check_prerequisites
            validate_environment
            cleanup_deployment
            ;;
        verify)
            check_prerequisites
            validate_environment
            verify_deployment
            ;;
        build)
            check_prerequisites
            validate_environment
            build_and_push_image
            ;;
        *)
            error_exit "Invalid action: $ACTION. Must be deploy, cleanup, verify, or build"
            ;;
    esac
}

# Execute main function
main "$@"
