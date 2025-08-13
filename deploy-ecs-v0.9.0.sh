#!/bin/bash

# AI Blockchain Analytics v0.9.0 - AWS ECS Deployment Script
# Deploys RoadRunner application with RDS PostgreSQL and ElastiCache Redis

set -e

# Configuration
CLUSTER_NAME="${CLUSTER_NAME:-ai-blockchain-analytics}"
SERVICE_NAME="${SERVICE_NAME:-ai-blockchain-analytics-app}"
TASK_FAMILY="${TASK_FAMILY:-ai-blockchain-analytics}"
VERSION="v0.9.0"
AWS_REGION="${AWS_REGION:-us-east-1}"
DOMAIN="${DOMAIN:-analytics.yourcompany.com}"
VPC_ID="${VPC_ID:-}"
SUBNET_IDS="${SUBNET_IDS:-}"
SECURITY_GROUP_ID="${SECURITY_GROUP_ID:-}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

success() {
    echo -e "${GREEN}✅ $1${NC}"
}

warn() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

error() {
    echo -e "${RED}❌ $1${NC}"
    exit 1
}

banner() {
    echo ""
    echo "╔══════════════════════════════════════════════════════════════╗"
    echo "║                                                              ║"
    echo "║       AI Blockchain Analytics Platform v0.9.0               ║"
    echo "║                AWS ECS Deployment                           ║"
    echo "║                                                              ║"
    echo "╚══════════════════════════════════════════════════════════════╝"
    echo ""
}

check_prerequisites() {
    log "Checking prerequisites..."
    
    # Check AWS CLI
    if ! command -v aws &> /dev/null; then
        error "AWS CLI is not installed or not in PATH"
    fi
    
    # Check AWS credentials
    if ! aws sts get-caller-identity &> /dev/null; then
        error "AWS credentials not configured or invalid"
    fi
    
    # Check Docker
    if ! command -v docker &> /dev/null; then
        error "Docker is not installed or not in PATH"
    fi
    
    # Check if running on correct git tag
    current_tag=$(git describe --tags --exact-match 2>/dev/null || echo "")
    if [[ "$current_tag" != "v0.9.0" ]]; then
        warn "Not on v0.9.0 tag. Current: ${current_tag:-'No tag'}"
        read -p "Continue anyway? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            error "Deployment cancelled"
        fi
    fi
    
    # Get AWS account ID
    AWS_ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
    ECR_REGISTRY="${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com"
    
    success "Prerequisites check passed"
    log "AWS Account: $AWS_ACCOUNT_ID"
    log "AWS Region: $AWS_REGION"
    log "ECR Registry: $ECR_REGISTRY"
}

create_ecr_repository() {
    log "Creating ECR repository..."
    
    REPO_NAME="ai-blockchain-analytics"
    
    # Check if repository exists
    if aws ecr describe-repositories --repository-names $REPO_NAME --region $AWS_REGION &> /dev/null; then
        log "ECR repository already exists"
    else
        aws ecr create-repository \
            --repository-name $REPO_NAME \
            --region $AWS_REGION \
            --image-scanning-configuration scanOnPush=true
        success "ECR repository created"
    fi
    
    # Set lifecycle policy
    cat > ecr-lifecycle-policy.json <<EOF
{
    "rules": [
        {
            "rulePriority": 1,
            "description": "Keep last 10 production images",
            "selection": {
                "tagStatus": "tagged",
                "tagPrefixList": ["v"],
                "countType": "imageCountMoreThan",
                "countNumber": 10
            },
            "action": {
                "type": "expire"
            }
        },
        {
            "rulePriority": 2,
            "description": "Delete untagged images older than 1 day",
            "selection": {
                "tagStatus": "untagged",
                "countType": "sinceImagePushed",
                "countUnit": "days",
                "countNumber": 1
            },
            "action": {
                "type": "expire"
            }
        }
    ]
}
EOF
    
    aws ecr put-lifecycle-policy \
        --repository-name $REPO_NAME \
        --lifecycle-policy-text file://ecr-lifecycle-policy.json \
        --region $AWS_REGION
    
    rm ecr-lifecycle-policy.json
    
    IMAGE_URI="${ECR_REGISTRY}/${REPO_NAME}:${VERSION}"
    success "ECR repository configured"
}

build_and_push_image() {
    log "Building and pushing RoadRunner application image..."
    
    # Login to ECR
    aws ecr get-login-password --region $AWS_REGION | docker login --username AWS --password-stdin $ECR_REGISTRY
    
    # Build image
    log "Building image: $IMAGE_URI"
    docker build -f Dockerfile.production -t $IMAGE_URI .
    
    # Tag as latest
    docker tag $IMAGE_URI "${ECR_REGISTRY}/${REPO_NAME}:latest"
    
    # Push images
    log "Pushing images..."
    docker push $IMAGE_URI
    docker push "${ECR_REGISTRY}/${REPO_NAME}:latest"
    
    success "Image built and pushed: $IMAGE_URI"
}

create_vpc_resources() {
    log "Creating VPC resources..."
    
    if [[ -z "$VPC_ID" ]]; then
        # Create VPC
        VPC_ID=$(aws ec2 create-vpc \
            --cidr-block 10.0.0.0/16 \
            --tag-specifications "ResourceType=vpc,Tags=[{Key=Name,Value=${CLUSTER_NAME}-vpc}]" \
            --query 'Vpc.VpcId' \
            --output text \
            --region $AWS_REGION)
        
        log "Created VPC: $VPC_ID"
        
        # Enable DNS hostnames
        aws ec2 modify-vpc-attribute --vpc-id $VPC_ID --enable-dns-hostnames --region $AWS_REGION
        
        # Create Internet Gateway
        IGW_ID=$(aws ec2 create-internet-gateway \
            --tag-specifications "ResourceType=internet-gateway,Tags=[{Key=Name,Value=${CLUSTER_NAME}-igw}]" \
            --query 'InternetGateway.InternetGatewayId' \
            --output text \
            --region $AWS_REGION)
        
        aws ec2 attach-internet-gateway --vpc-id $VPC_ID --internet-gateway-id $IGW_ID --region $AWS_REGION
        
        # Create public subnets
        SUBNET_1=$(aws ec2 create-subnet \
            --vpc-id $VPC_ID \
            --cidr-block 10.0.1.0/24 \
            --availability-zone "${AWS_REGION}a" \
            --tag-specifications "ResourceType=subnet,Tags=[{Key=Name,Value=${CLUSTER_NAME}-public-1}]" \
            --query 'Subnet.SubnetId' \
            --output text \
            --region $AWS_REGION)
        
        SUBNET_2=$(aws ec2 create-subnet \
            --vpc-id $VPC_ID \
            --cidr-block 10.0.2.0/24 \
            --availability-zone "${AWS_REGION}b" \
            --tag-specifications "ResourceType=subnet,Tags=[{Key=Name,Value=${CLUSTER_NAME}-public-2}]" \
            --query 'Subnet.SubnetId' \
            --output text \
            --region $AWS_REGION)
        
        # Create private subnets
        PRIVATE_SUBNET_1=$(aws ec2 create-subnet \
            --vpc-id $VPC_ID \
            --cidr-block 10.0.3.0/24 \
            --availability-zone "${AWS_REGION}a" \
            --tag-specifications "ResourceType=subnet,Tags=[{Key=Name,Value=${CLUSTER_NAME}-private-1}]" \
            --query 'Subnet.SubnetId' \
            --output text \
            --region $AWS_REGION)
        
        PRIVATE_SUBNET_2=$(aws ec2 create-subnet \
            --vpc-id $VPC_ID \
            --cidr-block 10.0.4.0/24 \
            --availability-zone "${AWS_REGION}b" \
            --tag-specifications "ResourceType=subnet,Tags=[{Key=Name,Value=${CLUSTER_NAME}-private-2}]" \
            --query 'Subnet.SubnetId' \
            --output text \
            --region $AWS_REGION)
        
        SUBNET_IDS="$SUBNET_1,$SUBNET_2"
        PRIVATE_SUBNET_IDS="$PRIVATE_SUBNET_1,$PRIVATE_SUBNET_2"
        
        # Create route table for public subnets
        ROUTE_TABLE_ID=$(aws ec2 create-route-table \
            --vpc-id $VPC_ID \
            --tag-specifications "ResourceType=route-table,Tags=[{Key=Name,Value=${CLUSTER_NAME}-public-rt}]" \
            --query 'RouteTable.RouteTableId' \
            --output text \
            --region $AWS_REGION)
        
        aws ec2 create-route --route-table-id $ROUTE_TABLE_ID --destination-cidr-block 0.0.0.0/0 --gateway-id $IGW_ID --region $AWS_REGION
        aws ec2 associate-route-table --subnet-id $SUBNET_1 --route-table-id $ROUTE_TABLE_ID --region $AWS_REGION
        aws ec2 associate-route-table --subnet-id $SUBNET_2 --route-table-id $ROUTE_TABLE_ID --region $AWS_REGION
        
        # Enable auto-assign public IP for public subnets
        aws ec2 modify-subnet-attribute --subnet-id $SUBNET_1 --map-public-ip-on-launch --region $AWS_REGION
        aws ec2 modify-subnet-attribute --subnet-id $SUBNET_2 --map-public-ip-on-launch --region $AWS_REGION
        
        success "VPC resources created"
    else
        log "Using existing VPC: $VPC_ID"
    fi
}

create_security_groups() {
    log "Creating security groups..."
    
    # ALB Security Group
    ALB_SG_ID=$(aws ec2 create-security-group \
        --group-name "${CLUSTER_NAME}-alb-sg" \
        --description "Security group for ALB" \
        --vpc-id $VPC_ID \
        --tag-specifications "ResourceType=security-group,Tags=[{Key=Name,Value=${CLUSTER_NAME}-alb-sg}]" \
        --query 'GroupId' \
        --output text \
        --region $AWS_REGION)
    
    # Allow HTTP and HTTPS traffic
    aws ec2 authorize-security-group-ingress \
        --group-id $ALB_SG_ID \
        --protocol tcp \
        --port 80 \
        --cidr 0.0.0.0/0 \
        --region $AWS_REGION
    
    aws ec2 authorize-security-group-ingress \
        --group-id $ALB_SG_ID \
        --protocol tcp \
        --port 443 \
        --cidr 0.0.0.0/0 \
        --region $AWS_REGION
    
    # ECS Security Group
    ECS_SG_ID=$(aws ec2 create-security-group \
        --group-name "${CLUSTER_NAME}-ecs-sg" \
        --description "Security group for ECS tasks" \
        --vpc-id $VPC_ID \
        --tag-specifications "ResourceType=security-group,Tags=[{Key=Name,Value=${CLUSTER_NAME}-ecs-sg}]" \
        --query 'GroupId' \
        --output text \
        --region $AWS_REGION)
    
    # Allow traffic from ALB
    aws ec2 authorize-security-group-ingress \
        --group-id $ECS_SG_ID \
        --protocol tcp \
        --port 8080 \
        --source-group $ALB_SG_ID \
        --region $AWS_REGION
    
    # RDS Security Group
    RDS_SG_ID=$(aws ec2 create-security-group \
        --group-name "${CLUSTER_NAME}-rds-sg" \
        --description "Security group for RDS" \
        --vpc-id $VPC_ID \
        --tag-specifications "ResourceType=security-group,Tags=[{Key=Name,Value=${CLUSTER_NAME}-rds-sg}]" \
        --query 'GroupId' \
        --output text \
        --region $AWS_REGION)
    
    # Allow PostgreSQL traffic from ECS
    aws ec2 authorize-security-group-ingress \
        --group-id $RDS_SG_ID \
        --protocol tcp \
        --port 5432 \
        --source-group $ECS_SG_ID \
        --region $AWS_REGION
    
    # ElastiCache Security Group
    REDIS_SG_ID=$(aws ec2 create-security-group \
        --group-name "${CLUSTER_NAME}-redis-sg" \
        --description "Security group for ElastiCache Redis" \
        --vpc-id $VPC_ID \
        --tag-specifications "ResourceType=security-group,Tags=[{Key=Name,Value=${CLUSTER_NAME}-redis-sg}]" \
        --query 'GroupId' \
        --output text \
        --region $AWS_REGION)
    
    # Allow Redis traffic from ECS
    aws ec2 authorize-security-group-ingress \
        --group-id $REDIS_SG_ID \
        --protocol tcp \
        --port 6379 \
        --source-group $ECS_SG_ID \
        --region $AWS_REGION
    
    SECURITY_GROUP_ID=$ECS_SG_ID
    
    success "Security groups created"
}

create_rds_instance() {
    log "Creating RDS PostgreSQL instance..."
    
    # Create DB subnet group
    aws rds create-db-subnet-group \
        --db-subnet-group-name "${CLUSTER_NAME}-db-subnet-group" \
        --db-subnet-group-description "Subnet group for ${CLUSTER_NAME} database" \
        --subnet-ids ${PRIVATE_SUBNET_IDS//,/ } \
        --tags Key=Name,Value="${CLUSTER_NAME}-db-subnet-group" \
        --region $AWS_REGION
    
    # Generate random password
    DB_PASSWORD=$(openssl rand -base64 32 | tr -d "=+/" | cut -c1-25)
    
    # Create RDS instance
    aws rds create-db-instance \
        --db-instance-identifier "${CLUSTER_NAME}-db" \
        --db-instance-class db.t3.micro \
        --engine postgres \
        --engine-version 15.4 \
        --master-username ai_blockchain_user \
        --master-user-password "$DB_PASSWORD" \
        --allocated-storage 20 \
        --storage-type gp2 \
        --storage-encrypted \
        --vpc-security-group-ids $RDS_SG_ID \
        --db-subnet-group-name "${CLUSTER_NAME}-db-subnet-group" \
        --db-name ai_blockchain_analytics \
        --backup-retention-period 7 \
        --multi-az \
        --auto-minor-version-upgrade \
        --deletion-protection \
        --tags Key=Name,Value="${CLUSTER_NAME}-db" \
        --region $AWS_REGION
    
    log "Waiting for RDS instance to be available..."
    aws rds wait db-instance-available --db-instance-identifier "${CLUSTER_NAME}-db" --region $AWS_REGION
    
    # Get RDS endpoint
    DB_ENDPOINT=$(aws rds describe-db-instances \
        --db-instance-identifier "${CLUSTER_NAME}-db" \
        --query 'DBInstances[0].Endpoint.Address' \
        --output text \
        --region $AWS_REGION)
    
    success "RDS instance created: $DB_ENDPOINT"
    
    # Store password in AWS Secrets Manager
    aws secretsmanager create-secret \
        --name "${CLUSTER_NAME}/database" \
        --description "Database credentials for ${CLUSTER_NAME}" \
        --secret-string "{\"username\":\"ai_blockchain_user\",\"password\":\"$DB_PASSWORD\",\"engine\":\"postgres\",\"host\":\"$DB_ENDPOINT\",\"port\":5432,\"dbname\":\"ai_blockchain_analytics\"}" \
        --region $AWS_REGION
    
    success "Database credentials stored in Secrets Manager"
}

create_elasticache_cluster() {
    log "Creating ElastiCache Redis cluster..."
    
    # Create cache subnet group
    aws elasticache create-cache-subnet-group \
        --cache-subnet-group-name "${CLUSTER_NAME}-cache-subnet-group" \
        --cache-subnet-group-description "Cache subnet group for ${CLUSTER_NAME}" \
        --subnet-ids ${PRIVATE_SUBNET_IDS//,/ } \
        --region $AWS_REGION
    
    # Generate Redis auth token
    REDIS_AUTH_TOKEN=$(openssl rand -base64 32 | tr -d "=+/" | cut -c1-32)
    
    # Create Redis cluster
    aws elasticache create-cache-cluster \
        --cache-cluster-id "${CLUSTER_NAME}-redis" \
        --cache-node-type cache.t3.micro \
        --engine redis \
        --engine-version 7.0 \
        --num-cache-nodes 1 \
        --cache-parameter-group default.redis7 \
        --cache-subnet-group-name "${CLUSTER_NAME}-cache-subnet-group" \
        --security-group-ids $REDIS_SG_ID \
        --auth-token "$REDIS_AUTH_TOKEN" \
        --transit-encryption-enabled \
        --at-rest-encryption-enabled \
        --tags Key=Name,Value="${CLUSTER_NAME}-redis" \
        --region $AWS_REGION
    
    log "Waiting for ElastiCache cluster to be available..."
    aws elasticache wait cache-cluster-available --cache-cluster-id "${CLUSTER_NAME}-redis" --region $AWS_REGION
    
    # Get Redis endpoint
    REDIS_ENDPOINT=$(aws elasticache describe-cache-clusters \
        --cache-cluster-id "${CLUSTER_NAME}-redis" \
        --show-cache-node-info \
        --query 'CacheClusters[0].CacheNodes[0].Endpoint.Address' \
        --output text \
        --region $AWS_REGION)
    
    success "ElastiCache cluster created: $REDIS_ENDPOINT"
    
    # Store Redis credentials in Secrets Manager
    aws secretsmanager create-secret \
        --name "${CLUSTER_NAME}/redis" \
        --description "Redis credentials for ${CLUSTER_NAME}" \
        --secret-string "{\"host\":\"$REDIS_ENDPOINT\",\"port\":6379,\"auth_token\":\"$REDIS_AUTH_TOKEN\"}" \
        --region $AWS_REGION
    
    success "Redis credentials stored in Secrets Manager"
}

create_ecs_cluster() {
    log "Creating ECS cluster..."
    
    aws ecs create-cluster \
        --cluster-name $CLUSTER_NAME \
        --capacity-providers FARGATE \
        --default-capacity-provider-strategy capacityProvider=FARGATE,weight=1 \
        --tags key=Name,value=$CLUSTER_NAME \
        --region $AWS_REGION
    
    success "ECS cluster created: $CLUSTER_NAME"
}

create_iam_roles() {
    log "Creating IAM roles..."
    
    # ECS Task Execution Role
    cat > ecs-task-execution-role-trust-policy.json <<EOF
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Principal": {
                "Service": "ecs-tasks.amazonaws.com"
            },
            "Action": "sts:AssumeRole"
        }
    ]
}
EOF
    
    aws iam create-role \
        --role-name "${CLUSTER_NAME}-task-execution-role" \
        --assume-role-policy-document file://ecs-task-execution-role-trust-policy.json \
        --region $AWS_REGION
    
    aws iam attach-role-policy \
        --role-name "${CLUSTER_NAME}-task-execution-role" \
        --policy-arn arn:aws:iam::aws:policy/service-role/AmazonECSTaskExecutionRolePolicy \
        --region $AWS_REGION
    
    # Custom policy for Secrets Manager access
    cat > secrets-manager-policy.json <<EOF
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "secretsmanager:GetSecretValue"
            ],
            "Resource": [
                "arn:aws:secretsmanager:${AWS_REGION}:${AWS_ACCOUNT_ID}:secret:${CLUSTER_NAME}/*"
            ]
        }
    ]
}
EOF
    
    aws iam create-policy \
        --policy-name "${CLUSTER_NAME}-secrets-access" \
        --policy-document file://secrets-manager-policy.json \
        --region $AWS_REGION
    
    aws iam attach-role-policy \
        --role-name "${CLUSTER_NAME}-task-execution-role" \
        --policy-arn "arn:aws:iam::${AWS_ACCOUNT_ID}:policy/${CLUSTER_NAME}-secrets-access" \
        --region $AWS_REGION
    
    # ECS Task Role (for application permissions)
    aws iam create-role \
        --role-name "${CLUSTER_NAME}-task-role" \
        --assume-role-policy-document file://ecs-task-execution-role-trust-policy.json \
        --region $AWS_REGION
    
    # Clean up temp files
    rm ecs-task-execution-role-trust-policy.json secrets-manager-policy.json
    
    TASK_EXECUTION_ROLE_ARN="arn:aws:iam::${AWS_ACCOUNT_ID}:role/${CLUSTER_NAME}-task-execution-role"
    TASK_ROLE_ARN="arn:aws:iam::${AWS_ACCOUNT_ID}:role/${CLUSTER_NAME}-task-role"
    
    success "IAM roles created"
}

create_application_load_balancer() {
    log "Creating Application Load Balancer..."
    
    # Create ALB
    ALB_ARN=$(aws elbv2 create-load-balancer \
        --name "${CLUSTER_NAME}-alb" \
        --subnets ${SUBNET_IDS//,/ } \
        --security-groups $ALB_SG_ID \
        --scheme internet-facing \
        --type application \
        --ip-address-type ipv4 \
        --tags Key=Name,Value="${CLUSTER_NAME}-alb" \
        --query 'LoadBalancers[0].LoadBalancerArn' \
        --output text \
        --region $AWS_REGION)
    
    # Get ALB DNS name
    ALB_DNS=$(aws elbv2 describe-load-balancers \
        --load-balancer-arns $ALB_ARN \
        --query 'LoadBalancers[0].DNSName' \
        --output text \
        --region $AWS_REGION)
    
    # Create target group
    TARGET_GROUP_ARN=$(aws elbv2 create-target-group \
        --name "${CLUSTER_NAME}-tg" \
        --protocol HTTP \
        --port 8080 \
        --vpc-id $VPC_ID \
        --target-type ip \
        --health-check-path /api/health \
        --health-check-interval-seconds 30 \
        --health-check-timeout-seconds 10 \
        --healthy-threshold-count 2 \
        --unhealthy-threshold-count 5 \
        --tags Key=Name,Value="${CLUSTER_NAME}-tg" \
        --query 'TargetGroups[0].TargetGroupArn' \
        --output text \
        --region $AWS_REGION)
    
    # Create listener
    aws elbv2 create-listener \
        --load-balancer-arn $ALB_ARN \
        --protocol HTTP \
        --port 80 \
        --default-actions Type=forward,TargetGroupArn=$TARGET_GROUP_ARN \
        --region $AWS_REGION
    
    success "Application Load Balancer created: $ALB_DNS"
}

create_task_definition() {
    log "Creating ECS task definition..."
    
    cat > task-definition.json <<EOF
{
    "family": "$TASK_FAMILY",
    "networkMode": "awsvpc",
    "requiresCompatibilities": ["FARGATE"],
    "cpu": "1024",
    "memory": "2048",
    "executionRoleArn": "$TASK_EXECUTION_ROLE_ARN",
    "taskRoleArn": "$TASK_ROLE_ARN",
    "containerDefinitions": [
        {
            "name": "app",
            "image": "$IMAGE_URI",
            "portMappings": [
                {
                    "containerPort": 8080,
                    "protocol": "tcp"
                }
            ],
            "essential": true,
            "environment": [
                {"name": "APP_NAME", "value": "AI Blockchain Analytics"},
                {"name": "APP_ENV", "value": "production"},
                {"name": "APP_DEBUG", "value": "false"},
                {"name": "APP_URL", "value": "https://$DOMAIN"},
                {"name": "LOG_CHANNEL", "value": "stack"},
                {"name": "LOG_LEVEL", "value": "info"},
                {"name": "DB_CONNECTION", "value": "pgsql"},
                {"name": "DB_HOST", "value": "$DB_ENDPOINT"},
                {"name": "DB_PORT", "value": "5432"},
                {"name": "DB_DATABASE", "value": "ai_blockchain_analytics"},
                {"name": "DB_USERNAME", "value": "ai_blockchain_user"},
                {"name": "CACHE_DRIVER", "value": "redis"},
                {"name": "SESSION_DRIVER", "value": "redis"},
                {"name": "QUEUE_CONNECTION", "value": "redis"},
                {"name": "REDIS_HOST", "value": "$REDIS_ENDPOINT"},
                {"name": "REDIS_PORT", "value": "6379"},
                {"name": "MAIL_MAILER", "value": "mailgun"},
                {"name": "MAILGUN_DOMAIN", "value": "$DOMAIN"},
                {"name": "MAILGUN_ENDPOINT", "value": "api.mailgun.net"},
                {"name": "ONBOARDING_ENABLED", "value": "true"},
                {"name": "ONBOARDING_FROM_EMAIL", "value": "welcome@$DOMAIN"},
                {"name": "SANCTUM_STATEFUL_DOMAINS", "value": "$DOMAIN"},
                {"name": "SESSION_SECURE_COOKIE", "value": "true"},
                {"name": "TELESCOPE_ENABLED", "value": "false"}
            ],
            "secrets": [
                {
                    "name": "APP_KEY",
                    "valueFrom": "arn:aws:secretsmanager:${AWS_REGION}:${AWS_ACCOUNT_ID}:secret:${CLUSTER_NAME}/app:APP_KEY::"
                },
                {
                    "name": "DB_PASSWORD",
                    "valueFrom": "arn:aws:secretsmanager:${AWS_REGION}:${AWS_ACCOUNT_ID}:secret:${CLUSTER_NAME}/database:password::"
                },
                {
                    "name": "REDIS_PASSWORD",
                    "valueFrom": "arn:aws:secretsmanager:${AWS_REGION}:${AWS_ACCOUNT_ID}:secret:${CLUSTER_NAME}/redis:auth_token::"
                }
            ],
            "logConfiguration": {
                "logDriver": "awslogs",
                "options": {
                    "awslogs-group": "/ecs/${CLUSTER_NAME}",
                    "awslogs-region": "$AWS_REGION",
                    "awslogs-stream-prefix": "app"
                }
            },
            "healthCheck": {
                "command": ["CMD-SHELL", "curl -f http://localhost:8080/api/health || exit 1"],
                "interval": 30,
                "timeout": 10,
                "retries": 3,
                "startPeriod": 60
            }
        }
    ]
}
EOF
    
    # Create CloudWatch log group
    aws logs create-log-group \
        --log-group-name "/ecs/${CLUSTER_NAME}" \
        --region $AWS_REGION || true
    
    # Register task definition
    TASK_DEFINITION_ARN=$(aws ecs register-task-definition \
        --cli-input-json file://task-definition.json \
        --query 'taskDefinition.taskDefinitionArn' \
        --output text \
        --region $AWS_REGION)
    
    rm task-definition.json
    
    success "Task definition created: $TASK_DEFINITION_ARN"
}

create_app_secrets() {
    log "Creating application secrets..."
    
    # Generate app key
    APP_KEY="base64:$(openssl rand -base64 32)"
    
    # Create app secrets
    aws secretsmanager create-secret \
        --name "${CLUSTER_NAME}/app" \
        --description "Application secrets for ${CLUSTER_NAME}" \
        --secret-string "{\"APP_KEY\":\"$APP_KEY\"}" \
        --region $AWS_REGION
    
    success "Application secrets created"
}

create_ecs_service() {
    log "Creating ECS service..."
    
    cat > service-definition.json <<EOF
{
    "serviceName": "$SERVICE_NAME",
    "cluster": "$CLUSTER_NAME",
    "taskDefinition": "$TASK_DEFINITION_ARN",
    "desiredCount": 2,
    "launchType": "FARGATE",
    "networkConfiguration": {
        "awsvpcConfiguration": {
            "subnets": [${SUBNET_IDS//,/\",\"}],
            "securityGroups": ["$SECURITY_GROUP_ID"],
            "assignPublicIp": "ENABLED"
        }
    },
    "loadBalancers": [
        {
            "targetGroupArn": "$TARGET_GROUP_ARN",
            "containerName": "app",
            "containerPort": 8080
        }
    ],
    "healthCheckGracePeriodSeconds": 300,
    "deploymentConfiguration": {
        "maximumPercent": 200,
        "minimumHealthyPercent": 50,
        "deploymentCircuitBreaker": {
            "enable": true,
            "rollback": true
        }
    }
}
EOF
    
    aws ecs create-service \
        --cli-input-json file://service-definition.json \
        --region $AWS_REGION
    
    rm service-definition.json
    
    success "ECS service created"
}

run_migrations() {
    log "Running database migrations..."
    
    # Wait for service to be stable
    log "Waiting for service to be stable..."
    aws ecs wait services-stable --cluster $CLUSTER_NAME --services $SERVICE_NAME --region $AWS_REGION
    
    # Get task ARN
    TASK_ARN=$(aws ecs list-tasks \
        --cluster $CLUSTER_NAME \
        --service-name $SERVICE_NAME \
        --query 'taskArns[0]' \
        --output text \
        --region $AWS_REGION)
    
    if [[ "$TASK_ARN" != "None" ]]; then
        log "Running migrations in task: $TASK_ARN"
        
        # Run migrations
        aws ecs execute-command \
            --cluster $CLUSTER_NAME \
            --task $TASK_ARN \
            --container app \
            --interactive \
            --command "php artisan migrate --force" \
            --region $AWS_REGION
        
        # Seed famous contracts
        aws ecs execute-command \
            --cluster $CLUSTER_NAME \
            --task $TASK_ARN \
            --container app \
            --interactive \
            --command "php artisan db:seed --class=FamousContractsSeeder --force" \
            --region $AWS_REGION
        
        # Optimize application
        aws ecs execute-command \
            --cluster $CLUSTER_NAME \
            --task $TASK_ARN \
            --container app \
            --interactive \
            --command "php artisan config:cache && php artisan route:cache && php artisan view:cache" \
            --region $AWS_REGION
        
        success "Database setup completed"
    else
        warn "No running tasks found. Migrations will run on first deployment."
    fi
}

verify_deployment() {
    log "Verifying deployment..."
    
    # Check service status
    aws ecs describe-services \
        --cluster $CLUSTER_NAME \
        --services $SERVICE_NAME \
        --region $AWS_REGION
    
    # Test ALB health
    log "Testing load balancer health..."
    if curl -f "http://$ALB_DNS/api/health" > /dev/null 2>&1; then
        success "Load balancer health check passed"
    else
        warn "Load balancer health check failed"
    fi
    
    success "Deployment verification completed"
}

cleanup_on_error() {
    if [[ $? -ne 0 ]]; then
        error "Deployment failed. Check CloudWatch logs: /ecs/${CLUSTER_NAME}"
    fi
}

main() {
    trap cleanup_on_error ERR
    
    banner
    
    log "Starting AWS ECS deployment for AI Blockchain Analytics v0.9.0"
    log "Domain: $DOMAIN"
    log "Cluster: $CLUSTER_NAME"
    log "Region: $AWS_REGION"
    
    check_prerequisites
    create_ecr_repository
    build_and_push_image
    create_vpc_resources
    create_security_groups
    create_rds_instance
    create_elasticache_cluster
    create_ecs_cluster
    create_iam_roles
    create_application_load_balancer
    create_app_secrets
    create_task_definition
    create_ecs_service
    
    # Wait for deployment
    log "Waiting for deployment to complete..."
    sleep 60
    
    run_migrations
    verify_deployment
    
    success "🎉 AWS ECS deployment completed successfully!"
    
    echo ""
    echo "📋 Deployment Summary:"
    echo "• Cluster: $CLUSTER_NAME"
    echo "• Service: $SERVICE_NAME"
    echo "• Load Balancer: $ALB_DNS"
    echo "• Database: $DB_ENDPOINT"
    echo "• Redis: $REDIS_ENDPOINT"
    echo "• Region: $AWS_REGION"
    echo ""
    echo "🔍 Useful commands:"
    echo "• Check service: aws ecs describe-services --cluster $CLUSTER_NAME --services $SERVICE_NAME --region $AWS_REGION"
    echo "• View logs: aws logs tail /ecs/${CLUSTER_NAME} --follow --region $AWS_REGION"
    echo "• Scale service: aws ecs update-service --cluster $CLUSTER_NAME --service $SERVICE_NAME --desired-count 4 --region $AWS_REGION"
    echo ""
    echo "🌐 Your application should be available at: http://$ALB_DNS"
    echo "🔒 Configure DNS and SSL certificate for: https://$DOMAIN"
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --domain)
            DOMAIN="$2"
            shift 2
            ;;
        --cluster)
            CLUSTER_NAME="$2"
            shift 2
            ;;
        --region)
            AWS_REGION="$2"
            shift 2
            ;;
        --vpc-id)
            VPC_ID="$2"
            shift 2
            ;;
        --subnets)
            SUBNET_IDS="$2"
            shift 2
            ;;
        -h|--help)
            echo "Usage: $0 [OPTIONS]"
            echo "Options:"
            echo "  --domain DOMAIN        Set the application domain (default: analytics.yourcompany.com)"
            echo "  --cluster CLUSTER      Set the ECS cluster name (default: ai-blockchain-analytics)"
            echo "  --region REGION        Set the AWS region (default: us-east-1)"
            echo "  --vpc-id VPC_ID        Use existing VPC (optional)"
            echo "  --subnets SUBNET_IDS   Comma-separated subnet IDs (optional)"
            echo "  -h, --help            Show this help message"
            exit 0
            ;;
        *)
            error "Unknown option: $1"
            ;;
    esac
done

# Run main function
main "$@"
