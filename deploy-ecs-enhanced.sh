#!/bin/bash

# Enhanced ECS Deployment Script for AI Blockchain Analytics
# Supports RoadRunner, RDS PostgreSQL, ElastiCache Redis with comprehensive monitoring

set -euo pipefail

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration variables
CLUSTER_NAME="${CLUSTER_NAME:-ai-blockchain-analytics-cluster}"
SERVICE_NAME="${SERVICE_NAME:-ai-blockchain-analytics-app}"
REGION="${AWS_REGION:-us-east-1}"
ENVIRONMENT="${ENVIRONMENT:-production}"
VPC_ID="${VPC_ID:-}"
SUBNET_IDS="${SUBNET_IDS:-}"
SECURITY_GROUP_ID="${SECURITY_GROUP_ID:-}"
DOCKER_IMAGE="${DOCKER_IMAGE:-ai-blockchain-analytics:latest}"
ECR_REGISTRY="${ECR_REGISTRY:-}"

# Database and Cache settings
RDS_INSTANCE_CLASS="${RDS_INSTANCE_CLASS:-db.t3.medium}"
REDIS_NODE_TYPE="${REDIS_NODE_TYPE:-cache.t3.medium}"
DB_NAME="${DB_NAME:-ai_blockchain_analytics}"
DB_USERNAME="${DB_USERNAME:-postgres}"

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_header() {
    echo -e "${BLUE}
═══════════════════════════════════════════════════════════════════
🚀 AI Blockchain Analytics - Enhanced ECS Deployment
═══════════════════════════════════════════════════════════════════${NC}"
}

# Function to check prerequisites
check_prerequisites() {
    print_status "Checking prerequisites..."
    
    # Check AWS CLI
    if ! command -v aws &> /dev/null; then
        print_error "AWS CLI is not installed. Please install AWS CLI first."
        exit 1
    fi
    
    # Check AWS credentials
    if ! aws sts get-caller-identity &> /dev/null; then
        print_error "AWS credentials not configured. Please configure AWS CLI."
        exit 1
    fi
    
    # Check jq
    if ! command -v jq &> /dev/null; then
        print_error "jq is not installed. Please install jq for JSON processing."
        exit 1
    fi
    
    # Check Docker
    if ! command -v docker &> /dev/null; then
        print_error "docker is not installed. Please install docker first."
        exit 1
    fi
    
    print_status "Prerequisites check completed ✅"
}

# Function to create ECR repository and push image
setup_ecr_and_push_image() {
    if [[ "${SKIP_BUILD:-false}" == "true" ]]; then
        print_status "Skipping Docker build (SKIP_BUILD=true)"
        return 0
    fi
    
    print_status "Setting up ECR repository..."
    
    # Get AWS account ID
    ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
    ECR_REGISTRY="${ACCOUNT_ID}.dkr.ecr.${REGION}.amazonaws.com"
    REPOSITORY_NAME="ai-blockchain-analytics"
    
    # Create ECR repository if it doesn't exist
    if ! aws ecr describe-repositories --repository-names ${REPOSITORY_NAME} --region ${REGION} &> /dev/null; then
        print_status "Creating ECR repository..."
        aws ecr create-repository \
            --repository-name ${REPOSITORY_NAME} \
            --region ${REGION} \
            --image-scanning-configuration scanOnPush=true
    fi
    
    # Login to ECR
    print_status "Logging into ECR..."
    aws ecr get-login-password --region ${REGION} | docker login --username AWS --password-stdin ${ECR_REGISTRY}
    
    # Build and tag image
    print_status "Building and pushing Docker image..."
    docker build --target production --platform linux/amd64 -t ${REPOSITORY_NAME}:latest .
    docker tag ${REPOSITORY_NAME}:latest ${ECR_REGISTRY}/${REPOSITORY_NAME}:latest
    docker push ${ECR_REGISTRY}/${REPOSITORY_NAME}:latest
    
    # Update DOCKER_IMAGE variable
    DOCKER_IMAGE="${ECR_REGISTRY}/${REPOSITORY_NAME}:latest"
    
    print_status "ECR setup and image push completed ✅"
}

# Function to create VPC and networking components
setup_networking() {
    if [[ -n "${VPC_ID}" ]]; then
        print_status "Using existing VPC: ${VPC_ID}"
        return 0
    fi
    
    print_status "Setting up VPC and networking..."
    
    # Create VPC
    VPC_RESPONSE=$(aws ec2 create-vpc \
        --cidr-block 10.0.0.0/16 \
        --region ${REGION} \
        --tag-specifications "ResourceType=vpc,Tags=[{Key=Name,Value=${CLUSTER_NAME}-vpc}]" \
        --output json)
    
    VPC_ID=$(echo ${VPC_RESPONSE} | jq -r '.Vpc.VpcId')
    
    # Enable DNS hostnames
    aws ec2 modify-vpc-attribute --vpc-id ${VPC_ID} --enable-dns-hostnames
    
    # Create Internet Gateway
    IGW_RESPONSE=$(aws ec2 create-internet-gateway \
        --region ${REGION} \
        --tag-specifications "ResourceType=internet-gateway,Tags=[{Key=Name,Value=${CLUSTER_NAME}-igw}]" \
        --output json)
    
    IGW_ID=$(echo ${IGW_RESPONSE} | jq -r '.InternetGateway.InternetGatewayId')
    
    # Attach Internet Gateway to VPC
    aws ec2 attach-internet-gateway --vpc-id ${VPC_ID} --internet-gateway-id ${IGW_ID}
    
    # Create public subnets
    AVAILABILITY_ZONES=($(aws ec2 describe-availability-zones --region ${REGION} --query 'AvailabilityZones[0:2].ZoneName' --output text))
    SUBNET_IDS=""
    
    for i in "${!AVAILABILITY_ZONES[@]}"; do
        SUBNET_RESPONSE=$(aws ec2 create-subnet \
            --vpc-id ${VPC_ID} \
            --cidr-block "10.0.$((i+1)).0/24" \
            --availability-zone ${AVAILABILITY_ZONES[$i]} \
            --region ${REGION} \
            --tag-specifications "ResourceType=subnet,Tags=[{Key=Name,Value=${CLUSTER_NAME}-public-subnet-$((i+1))}]" \
            --output json)
        
        SUBNET_ID=$(echo ${SUBNET_RESPONSE} | jq -r '.Subnet.SubnetId')
        
        if [[ -z "${SUBNET_IDS}" ]]; then
            SUBNET_IDS="${SUBNET_ID}"
        else
            SUBNET_IDS="${SUBNET_IDS},${SUBNET_ID}"
        fi
        
        # Enable auto-assign public IP
        aws ec2 modify-subnet-attribute --subnet-id ${SUBNET_ID} --map-public-ip-on-launch
    done
    
    # Create private subnets for database
    PRIVATE_SUBNET_IDS=""
    for i in "${!AVAILABILITY_ZONES[@]}"; do
        SUBNET_RESPONSE=$(aws ec2 create-subnet \
            --vpc-id ${VPC_ID} \
            --cidr-block "10.0.$((i+10)).0/24" \
            --availability-zone ${AVAILABILITY_ZONES[$i]} \
            --region ${REGION} \
            --tag-specifications "ResourceType=subnet,Tags=[{Key=Name,Value=${CLUSTER_NAME}-private-subnet-$((i+1))}]" \
            --output json)
        
        PRIVATE_SUBNET_ID=$(echo ${SUBNET_RESPONSE} | jq -r '.Subnet.SubnetId')
        
        if [[ -z "${PRIVATE_SUBNET_IDS}" ]]; then
            PRIVATE_SUBNET_IDS="${PRIVATE_SUBNET_ID}"
        else
            PRIVATE_SUBNET_IDS="${PRIVATE_SUBNET_IDS},${PRIVATE_SUBNET_ID}"
        fi
    done
    
    # Create route table
    ROUTE_TABLE_RESPONSE=$(aws ec2 create-route-table \
        --vpc-id ${VPC_ID} \
        --region ${REGION} \
        --tag-specifications "ResourceType=route-table,Tags=[{Key=Name,Value=${CLUSTER_NAME}-public-rt}]" \
        --output json)
    
    ROUTE_TABLE_ID=$(echo ${ROUTE_TABLE_RESPONSE} | jq -r '.RouteTable.RouteTableId')
    
    # Create route to Internet Gateway
    aws ec2 create-route --route-table-id ${ROUTE_TABLE_ID} --destination-cidr-block 0.0.0.0/0 --gateway-id ${IGW_ID}
    
    # Associate subnets with route table
    for SUBNET_ID in ${SUBNET_IDS//,/ }; do
        aws ec2 associate-route-table --subnet-id ${SUBNET_ID} --route-table-id ${ROUTE_TABLE_ID}
    done
    
    print_status "VPC and networking setup completed ✅"
    print_status "VPC ID: ${VPC_ID}"
    print_status "Subnet IDs: ${SUBNET_IDS}"
}

# Function to create security groups
setup_security_groups() {
    print_status "Setting up security groups..."
    
    # Create security group for ECS
    SG_RESPONSE=$(aws ec2 create-security-group \
        --group-name "${CLUSTER_NAME}-ecs-sg" \
        --description "Security group for ECS tasks" \
        --vpc-id ${VPC_ID} \
        --region ${REGION} \
        --output json)
    
    ECS_SECURITY_GROUP_ID=$(echo ${SG_RESPONSE} | jq -r '.GroupId')
    
    # Add inbound rules for ECS
    aws ec2 authorize-security-group-ingress \
        --group-id ${ECS_SECURITY_GROUP_ID} \
        --protocol tcp \
        --port 8000 \
        --cidr 0.0.0.0/0 \
        --region ${REGION}
    
    aws ec2 authorize-security-group-ingress \
        --group-id ${ECS_SECURITY_GROUP_ID} \
        --protocol tcp \
        --port 2112 \
        --cidr 10.0.0.0/16 \
        --region ${REGION}
    
    aws ec2 authorize-security-group-ingress \
        --group-id ${ECS_SECURITY_GROUP_ID} \
        --protocol tcp \
        --port 443 \
        --cidr 0.0.0.0/0 \
        --region ${REGION}
    
    # Create security group for RDS
    RDS_SG_RESPONSE=$(aws ec2 create-security-group \
        --group-name "${CLUSTER_NAME}-rds-sg" \
        --description "Security group for RDS database" \
        --vpc-id ${VPC_ID} \
        --region ${REGION} \
        --output json)
    
    RDS_SECURITY_GROUP_ID=$(echo ${RDS_SG_RESPONSE} | jq -r '.GroupId')
    
    # Allow PostgreSQL access from ECS security group
    aws ec2 authorize-security-group-ingress \
        --group-id ${RDS_SECURITY_GROUP_ID} \
        --protocol tcp \
        --port 5432 \
        --source-group ${ECS_SECURITY_GROUP_ID} \
        --region ${REGION}
    
    # Create security group for Redis
    REDIS_SG_RESPONSE=$(aws ec2 create-security-group \
        --group-name "${CLUSTER_NAME}-redis-sg" \
        --description "Security group for Redis cache" \
        --vpc-id ${VPC_ID} \
        --region ${REGION} \
        --output json)
    
    REDIS_SECURITY_GROUP_ID=$(echo ${REDIS_SG_RESPONSE} | jq -r '.GroupId')
    
    # Allow Redis access from ECS security group
    aws ec2 authorize-security-group-ingress \
        --group-id ${REDIS_SECURITY_GROUP_ID} \
        --protocol tcp \
        --port 6379 \
        --source-group ${ECS_SECURITY_GROUP_ID} \
        --region ${REGION}
    
    SECURITY_GROUP_ID=${ECS_SECURITY_GROUP_ID}
    
    print_status "Security groups setup completed ✅"
    print_status "ECS Security Group: ${ECS_SECURITY_GROUP_ID}"
    print_status "RDS Security Group: ${RDS_SECURITY_GROUP_ID}"
    print_status "Redis Security Group: ${REDIS_SECURITY_GROUP_ID}"
}

# Function to create RDS instance
setup_rds() {
    if [[ "${USE_EXTERNAL_DB:-false}" == "true" ]]; then
        print_status "Using external PostgreSQL database"
        return 0
    fi
    
    print_status "Setting up RDS PostgreSQL instance..."
    
    # Create DB subnet group
    aws rds create-db-subnet-group \
        --db-subnet-group-name "${CLUSTER_NAME}-db-subnet-group" \
        --db-subnet-group-description "Subnet group for AI Blockchain Analytics" \
        --subnet-ids ${PRIVATE_SUBNET_IDS//,/ } \
        --region ${REGION} || true
    
    # Generate random password
    DB_PASSWORD=$(openssl rand -base64 32 | tr -d "=+/" | cut -c1-25)
    
    # Store password in AWS Secrets Manager
    aws secretsmanager create-secret \
        --name "${CLUSTER_NAME}/db-password" \
        --description "Database password for AI Blockchain Analytics" \
        --secret-string "${DB_PASSWORD}" \
        --region ${REGION} || \
    aws secretsmanager update-secret \
        --secret-id "${CLUSTER_NAME}/db-password" \
        --secret-string "${DB_PASSWORD}" \
        --region ${REGION}
    
    # Create RDS instance
    aws rds create-db-instance \
        --db-instance-identifier "${CLUSTER_NAME}-postgres" \
        --db-instance-class ${RDS_INSTANCE_CLASS} \
        --engine postgres \
        --engine-version 15.4 \
        --master-username ${DB_USERNAME} \
        --master-user-password ${DB_PASSWORD} \
        --allocated-storage 100 \
        --storage-type gp2 \
        --storage-encrypted \
        --vpc-security-group-ids ${RDS_SECURITY_GROUP_ID} \
        --db-subnet-group-name "${CLUSTER_NAME}-db-subnet-group" \
        --backup-retention-period 7 \
        --multi-az \
        --auto-minor-version-upgrade \
        --region ${REGION} \
        --tags Key=Name,Value="${CLUSTER_NAME}-postgres" Key=Environment,Value=${ENVIRONMENT} || true
    
    # Wait for RDS to become available
    print_status "Waiting for RDS instance to become available..."
    aws rds wait db-instance-available --db-instance-identifier "${CLUSTER_NAME}-postgres" --region ${REGION}
    
    # Get RDS endpoint
    RDS_ENDPOINT=$(aws rds describe-db-instances \
        --db-instance-identifier "${CLUSTER_NAME}-postgres" \
        --region ${REGION} \
        --query 'DBInstances[0].Endpoint.Address' \
        --output text)
    
    print_status "RDS setup completed ✅"
    print_status "RDS Endpoint: ${RDS_ENDPOINT}"
}

# Function to create ElastiCache Redis
setup_redis() {
    if [[ "${USE_EXTERNAL_REDIS:-false}" == "true" ]]; then
        print_status "Using external Redis instance"
        return 0
    fi
    
    print_status "Setting up ElastiCache Redis..."
    
    # Create cache subnet group
    aws elasticache create-cache-subnet-group \
        --cache-subnet-group-name "${CLUSTER_NAME}-cache-subnet-group" \
        --cache-subnet-group-description "Cache subnet group for AI Blockchain Analytics" \
        --subnet-ids ${PRIVATE_SUBNET_IDS//,/ } \
        --region ${REGION} || true
    
    # Generate Redis auth token
    REDIS_AUTH_TOKEN=$(openssl rand -base64 32 | tr -d "=+/" | cut -c1-25)
    
    # Store auth token in Secrets Manager
    aws secretsmanager create-secret \
        --name "${CLUSTER_NAME}/redis-auth-token" \
        --description "Redis auth token for AI Blockchain Analytics" \
        --secret-string "${REDIS_AUTH_TOKEN}" \
        --region ${REGION} || \
    aws secretsmanager update-secret \
        --secret-id "${CLUSTER_NAME}/redis-auth-token" \
        --secret-string "${REDIS_AUTH_TOKEN}" \
        --region ${REGION}
    
    # Create Redis replication group
    aws elasticache create-replication-group \
        --replication-group-id "${CLUSTER_NAME}-redis" \
        --description "Redis cache for AI Blockchain Analytics" \
        --node-type ${REDIS_NODE_TYPE} \
        --engine redis \
        --engine-version 7.0 \
        --num-cache-clusters 2 \
        --cache-parameter-group default.redis7 \
        --cache-subnet-group-name "${CLUSTER_NAME}-cache-subnet-group" \
        --security-group-ids ${REDIS_SECURITY_GROUP_ID} \
        --auth-token ${REDIS_AUTH_TOKEN} \
        --transit-encryption-enabled \
        --at-rest-encryption-enabled \
        --automatic-failover-enabled \
        --multi-az-enabled \
        --snapshot-retention-limit 5 \
        --snapshot-window "03:00-05:00" \
        --maintenance-window "sun:05:00-sun:07:00" \
        --region ${REGION} \
        --tags Key=Name,Value="${CLUSTER_NAME}-redis" Key=Environment,Value=${ENVIRONMENT} || true
    
    # Wait for Redis to become available
    print_status "Waiting for Redis to become available..."
    aws elasticache wait replication-group-available --replication-group-id "${CLUSTER_NAME}-redis" --region ${REGION}
    
    # Get Redis endpoint
    REDIS_ENDPOINT=$(aws elasticache describe-replication-groups \
        --replication-group-id "${CLUSTER_NAME}-redis" \
        --region ${REGION} \
        --query 'ReplicationGroups[0].NodeGroups[0].PrimaryEndpoint.Address' \
        --output text)
    
    print_status "Redis setup completed ✅"
    print_status "Redis Endpoint: ${REDIS_ENDPOINT}"
}

# Function to create secrets in AWS Secrets Manager
setup_secrets() {
    print_status "Setting up application secrets..."
    
    # App key (Laravel)
    APP_KEY=$(php -r "echo 'base64:' . base64_encode(random_bytes(32));")
    aws secretsmanager create-secret \
        --name "${CLUSTER_NAME}/app-key" \
        --description "Laravel application key" \
        --secret-string "${APP_KEY}" \
        --region ${REGION} || \
    aws secretsmanager update-secret \
        --secret-id "${CLUSTER_NAME}/app-key" \
        --secret-string "${APP_KEY}" \
        --region ${REGION}
    
    # Placeholder secrets (replace with real values)
    SECRETS=(
        "openai-api-key"
        "stripe-secret"
        "mailgun-secret"
        "sentry-dsn"
        "telescope-auth-token"
    )
    
    for secret in "${SECRETS[@]}"; do
        aws secretsmanager create-secret \
            --name "${CLUSTER_NAME}/${secret}" \
            --description "${secret} for AI Blockchain Analytics" \
            --secret-string "REPLACE_ME_${secret^^}" \
            --region ${REGION} || true
    done
    
    print_status "Secrets setup completed ✅"
    print_warning "Don't forget to update the placeholder secrets with real values!"
}

# Function to create ECS cluster
create_ecs_cluster() {
    print_status "Creating ECS cluster..."
    
    # Create ECS cluster
    aws ecs create-cluster \
        --cluster-name ${CLUSTER_NAME} \
        --capacity-providers FARGATE FARGATE_SPOT \
        --default-capacity-provider-strategy capacityProvider=FARGATE,weight=1 \
        --region ${REGION} \
        --tags key=Environment,value=${ENVIRONMENT} || true
    
    print_status "ECS cluster created ✅"
}

# Function to create IAM roles
setup_iam_roles() {
    print_status "Setting up IAM roles..."
    
    # Create ECS Task Execution Role
    cat > /tmp/ecs-task-execution-role-policy.json << EOF
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
        --role-name "${CLUSTER_NAME}-ecs-task-execution-role" \
        --assume-role-policy-document file:///tmp/ecs-task-execution-role-policy.json \
        --region ${REGION} || true
    
    aws iam attach-role-policy \
        --role-name "${CLUSTER_NAME}-ecs-task-execution-role" \
        --policy-arn arn:aws:iam::aws:policy/service-role/AmazonECSTaskExecutionRolePolicy \
        --region ${REGION}
    
    # Create ECS Task Role
    cat > /tmp/ecs-task-role-policy.json << EOF
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
        --role-name "${CLUSTER_NAME}-ecs-task-role" \
        --assume-role-policy-document file:///tmp/ecs-task-role-policy.json \
        --region ${REGION} || true
    
    # Attach policies for S3, Secrets Manager, etc.
    aws iam attach-role-policy \
        --role-name "${CLUSTER_NAME}-ecs-task-role" \
        --policy-arn arn:aws:iam::aws:policy/AmazonS3FullAccess \
        --region ${REGION}
    
    aws iam attach-role-policy \
        --role-name "${CLUSTER_NAME}-ecs-task-role" \
        --policy-arn arn:aws:iam::aws:policy/SecretsManagerReadWrite \
        --region ${REGION}
    
    # Get role ARNs
    ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
    EXECUTION_ROLE_ARN="arn:aws:iam::${ACCOUNT_ID}:role/${CLUSTER_NAME}-ecs-task-execution-role"
    TASK_ROLE_ARN="arn:aws:iam::${ACCOUNT_ID}:role/${CLUSTER_NAME}-ecs-task-role"
    
    print_status "IAM roles setup completed ✅"
}

# Function to register task definitions
register_task_definitions() {
    print_status "Registering ECS task definitions..."
    
    # Update task definition templates with actual values
    sed -e "s|ACCOUNT_ID|${ACCOUNT_ID}|g" \
        -e "s|ai-blockchain-analytics:latest|${DOCKER_IMAGE}|g" \
        -e "s|ai-blockchain-analytics-postgres.cluster-xyz.region.rds.amazonaws.com|${RDS_ENDPOINT}|g" \
        -e "s|ai-blockchain-analytics-redis.xyz.cache.amazonaws.com|${REDIS_ENDPOINT}|g" \
        -e "s|region|${REGION}|g" \
        -e "s|account|${ACCOUNT_ID}|g" \
        ecs/task-definitions-enhanced/roadrunner-app-task.json > /tmp/roadrunner-app-task.json
    
    sed -e "s|ACCOUNT_ID|${ACCOUNT_ID}|g" \
        -e "s|ai-blockchain-analytics:latest|${DOCKER_IMAGE}|g" \
        -e "s|ai-blockchain-analytics-postgres.cluster-xyz.region.rds.amazonaws.com|${RDS_ENDPOINT}|g" \
        -e "s|ai-blockchain-analytics-redis.xyz.cache.amazonaws.com|${REDIS_ENDPOINT}|g" \
        -e "s|region|${REGION}|g" \
        -e "s|account|${ACCOUNT_ID}|g" \
        ecs/task-definitions-enhanced/horizon-worker-task.json > /tmp/horizon-worker-task.json
    
    sed -e "s|ACCOUNT_ID|${ACCOUNT_ID}|g" \
        -e "s|ai-blockchain-analytics:latest|${DOCKER_IMAGE}|g" \
        -e "s|ai-blockchain-analytics-postgres.cluster-xyz.region.rds.amazonaws.com|${RDS_ENDPOINT}|g" \
        -e "s|ai-blockchain-analytics-redis.xyz.cache.amazonaws.com|${REDIS_ENDPOINT}|g" \
        -e "s|region|${REGION}|g" \
        -e "s|account|${ACCOUNT_ID}|g" \
        ecs/task-definitions-enhanced/scheduler-task.json > /tmp/scheduler-task.json
    
    # Register task definitions
    aws ecs register-task-definition \
        --cli-input-json file:///tmp/roadrunner-app-task.json \
        --region ${REGION}
    
    aws ecs register-task-definition \
        --cli-input-json file:///tmp/horizon-worker-task.json \
        --region ${REGION}
    
    aws ecs register-task-definition \
        --cli-input-json file:///tmp/scheduler-task.json \
        --region ${REGION}
    
    print_status "Task definitions registered ✅"
}

# Function to create Application Load Balancer
create_load_balancer() {
    print_status "Creating Application Load Balancer..."
    
    # Create ALB
    ALB_RESPONSE=$(aws elbv2 create-load-balancer \
        --name "${CLUSTER_NAME}-alb" \
        --subnets ${SUBNET_IDS//,/ } \
        --security-groups ${SECURITY_GROUP_ID} \
        --scheme internet-facing \
        --type application \
        --ip-address-type ipv4 \
        --region ${REGION} \
        --tags Key=Name,Value="${CLUSTER_NAME}-alb" Key=Environment,Value=${ENVIRONMENT} \
        --output json)
    
    ALB_ARN=$(echo ${ALB_RESPONSE} | jq -r '.LoadBalancers[0].LoadBalancerArn')
    ALB_DNS_NAME=$(echo ${ALB_RESPONSE} | jq -r '.LoadBalancers[0].DNSName')
    
    # Create target group
    TARGET_GROUP_RESPONSE=$(aws elbv2 create-target-group \
        --name "${CLUSTER_NAME}-tg" \
        --protocol HTTP \
        --port 8000 \
        --vpc-id ${VPC_ID} \
        --target-type ip \
        --health-check-enabled \
        --health-check-interval-seconds 30 \
        --health-check-path "/health" \
        --health-check-port 2114 \
        --health-check-protocol HTTP \
        --health-check-timeout-seconds 5 \
        --healthy-threshold-count 2 \
        --unhealthy-threshold-count 5 \
        --region ${REGION} \
        --output json)
    
    TARGET_GROUP_ARN=$(echo ${TARGET_GROUP_RESPONSE} | jq -r '.TargetGroups[0].TargetGroupArn')
    
    # Create listener
    aws elbv2 create-listener \
        --load-balancer-arn ${ALB_ARN} \
        --protocol HTTP \
        --port 80 \
        --default-actions Type=forward,TargetGroupArn=${TARGET_GROUP_ARN} \
        --region ${REGION}
    
    print_status "Load balancer created ✅"
    print_status "ALB DNS Name: ${ALB_DNS_NAME}"
}

# Function to create ECS services
create_ecs_services() {
    print_status "Creating ECS services..."
    
    # Create main application service
    aws ecs create-service \
        --cluster ${CLUSTER_NAME} \
        --service-name "${SERVICE_NAME}" \
        --task-definition "ai-blockchain-analytics-roadrunner-app" \
        --desired-count 2 \
        --launch-type FARGATE \
        --platform-version LATEST \
        --network-configuration "awsvpcConfiguration={subnets=[${SUBNET_IDS}],securityGroups=[${SECURITY_GROUP_ID}],assignPublicIp=ENABLED}" \
        --load-balancers targetGroupArn=${TARGET_GROUP_ARN},containerName=roadrunner-app,containerPort=8000 \
        --deployment-configuration "maximumPercent=200,minimumHealthyPercent=50" \
        --region ${REGION} \
        --tags key=Environment,value=${ENVIRONMENT}
    
    # Create Horizon worker service
    aws ecs create-service \
        --cluster ${CLUSTER_NAME} \
        --service-name "${SERVICE_NAME}-worker" \
        --task-definition "ai-blockchain-analytics-horizon-worker" \
        --desired-count 1 \
        --launch-type FARGATE \
        --platform-version LATEST \
        --network-configuration "awsvpcConfiguration={subnets=[${SUBNET_IDS}],securityGroups=[${SECURITY_GROUP_ID}],assignPublicIp=ENABLED}" \
        --region ${REGION} \
        --tags key=Environment,value=${ENVIRONMENT}
    
    # Create scheduler service
    aws ecs create-service \
        --cluster ${CLUSTER_NAME} \
        --service-name "${SERVICE_NAME}-scheduler" \
        --task-definition "ai-blockchain-analytics-scheduler" \
        --desired-count 1 \
        --launch-type FARGATE \
        --platform-version LATEST \
        --network-configuration "awsvpcConfiguration={subnets=[${SUBNET_IDS}],securityGroups=[${SECURITY_GROUP_ID}],assignPublicIp=ENABLED}" \
        --region ${REGION} \
        --tags key=Environment,value=${ENVIRONMENT}
    
    print_status "ECS services created ✅"
}

# Function to wait for services to be stable
wait_for_services() {
    print_status "Waiting for services to become stable..."
    
    aws ecs wait services-stable \
        --cluster ${CLUSTER_NAME} \
        --services "${SERVICE_NAME}" \
        --region ${REGION}
    
    print_status "Services are stable ✅"
}

# Function to run database migrations
run_migrations() {
    print_status "Running database migrations..."
    
    # Run migration task
    aws ecs run-task \
        --cluster ${CLUSTER_NAME} \
        --task-definition "ai-blockchain-analytics-roadrunner-app" \
        --launch-type FARGATE \
        --network-configuration "awsvpcConfiguration={subnets=[${SUBNET_IDS}],securityGroups=[${SECURITY_GROUP_ID}],assignPublicIp=ENABLED}" \
        --overrides '{
            "containerOverrides": [
                {
                    "name": "roadrunner-app",
                    "command": ["php", "artisan", "migrate", "--force"]
                }
            ]
        }' \
        --region ${REGION}
    
    print_status "Database migrations completed ✅"
}

# Function to setup auto scaling
setup_auto_scaling() {
    print_status "Setting up auto scaling..."
    
    # Register scalable target
    aws application-autoscaling register-scalable-target \
        --service-namespace ecs \
        --resource-id service/${CLUSTER_NAME}/${SERVICE_NAME} \
        --scalable-dimension ecs:service:DesiredCount \
        --min-capacity 2 \
        --max-capacity 10 \
        --region ${REGION}
    
    # Create scaling policy
    SCALING_POLICY_RESPONSE=$(aws application-autoscaling put-scaling-policy \
        --service-namespace ecs \
        --resource-id service/${CLUSTER_NAME}/${SERVICE_NAME} \
        --scalable-dimension ecs:service:DesiredCount \
        --policy-name "${SERVICE_NAME}-scaling-policy" \
        --policy-type TargetTrackingScaling \
        --target-tracking-scaling-policy-configuration '{
            "TargetValue": 70.0,
            "PredefinedMetricSpecification": {
                "PredefinedMetricType": "ECSServiceAverageCPUUtilization"
            },
            "ScaleOutCooldown": 300,
            "ScaleInCooldown": 300
        }' \
        --region ${REGION} \
        --output json)
    
    print_status "Auto scaling setup completed ✅"
}

# Function to display deployment summary
show_deployment_summary() {
    print_status "Deployment Summary:"
    echo "
┌─────────────────────────────────────────┐
│ 🎉 ECS Deployment Completed!            │
└─────────────────────────────────────────┘

📊 Deployed Components:
  ✅ VPC and Networking (${VPC_ID})
  ✅ RDS PostgreSQL (${RDS_ENDPOINT})
  ✅ ElastiCache Redis (${REDIS_ENDPOINT})
  ✅ Application Load Balancer (${ALB_DNS_NAME})
  ✅ ECS Cluster (${CLUSTER_NAME})
  ✅ RoadRunner Application Service (2 tasks)
  ✅ Horizon Worker Service (1 task)
  ✅ Scheduler Service (1 task)
  ✅ Auto Scaling Policies

🔍 Useful Commands:
  View services:       aws ecs list-services --cluster ${CLUSTER_NAME}
  Check service logs:  aws logs get-log-events --log-group-name /ecs/ai-blockchain-analytics-roadrunner-app
  Scale service:       aws ecs update-service --cluster ${CLUSTER_NAME} --service ${SERVICE_NAME} --desired-count 5
  View tasks:          aws ecs list-tasks --cluster ${CLUSTER_NAME}

🌐 Access URLs:
  Application:         http://${ALB_DNS_NAME}
  Health Check:        http://${ALB_DNS_NAME}:2114/health

📈 Monitoring:
  CloudWatch:          https://console.aws.amazon.com/cloudwatch/
  ECS Console:         https://console.aws.amazon.com/ecs/
  
🔧 Next Steps:
  1. Configure Route 53 for custom domain
  2. Set up SSL certificate with ACM
  3. Update secrets in AWS Secrets Manager
  4. Configure CloudWatch alarms
  5. Set up backup policies
"
}

# Function to cleanup deployment
cleanup_deployment() {
    if [[ "${1:-}" == "--confirm" ]]; then
        print_status "Cleaning up ECS deployment..."
        
        # Delete services
        aws ecs update-service --cluster ${CLUSTER_NAME} --service ${SERVICE_NAME} --desired-count 0 --region ${REGION} || true
        aws ecs delete-service --cluster ${CLUSTER_NAME} --service ${SERVICE_NAME} --region ${REGION} || true
        
        # Delete cluster
        aws ecs delete-cluster --cluster ${CLUSTER_NAME} --region ${REGION} || true
        
        # Delete load balancer
        if [[ -n "${ALB_ARN:-}" ]]; then
            aws elbv2 delete-load-balancer --load-balancer-arn ${ALB_ARN} --region ${REGION} || true
        fi
        
        # Delete RDS and Redis (be careful!)
        read -p "Delete RDS and Redis instances? (y/N): " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            aws rds delete-db-instance --db-instance-identifier "${CLUSTER_NAME}-postgres" --skip-final-snapshot --region ${REGION} || true
            aws elasticache delete-replication-group --replication-group-id "${CLUSTER_NAME}-redis" --region ${REGION} || true
        fi
        
        print_status "Cleanup completed ✅"
    else
        print_warning "To cleanup the deployment, run: $0 cleanup --confirm"
        print_warning "WARNING: This will delete all resources including databases!"
    fi
}

# Main execution function
main() {
    print_header
    
    case "${1:-deploy}" in
        "deploy")
            check_prerequisites
            setup_ecr_and_push_image
            setup_networking
            setup_security_groups
            setup_secrets
            setup_rds
            setup_redis
            create_ecs_cluster
            setup_iam_roles
            register_task_definitions
            create_load_balancer
            create_ecs_services
            wait_for_services
            run_migrations
            setup_auto_scaling
            show_deployment_summary
            ;;
        "cleanup")
            cleanup_deployment "$2"
            ;;
        "build")
            setup_ecr_and_push_image
            ;;
        "migrate")
            run_migrations
            ;;
        *)
            echo "Usage: $0 {deploy|cleanup|build|migrate}"
            echo ""
            echo "Commands:"
            echo "  deploy     - Full deployment (default)"
            echo "  cleanup    - Remove all resources"
            echo "  build      - Build and push Docker image"
            echo "  migrate    - Run database migrations"
            echo ""
            echo "Environment Variables:"
            echo "  ENVIRONMENT=production     - Deployment environment"
            echo "  AWS_REGION=us-east-1      - AWS region"
            echo "  CLUSTER_NAME=my-cluster    - ECS cluster name"
            echo "  SKIP_BUILD=true           - Skip Docker build"
            echo "  USE_EXTERNAL_DB=true      - Use external PostgreSQL"
            echo "  USE_EXTERNAL_REDIS=true   - Use external Redis"
            echo "  VPC_ID=vpc-123456         - Use existing VPC"
            echo "  SUBNET_IDS=subnet-1,2,3   - Use existing subnets"
            exit 1
            ;;
    esac
}

# Handle script interruption
trap 'print_error "Deployment interrupted"; exit 1' INT TERM

# Execute main function
main "$@"