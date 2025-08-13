#!/bin/bash

# Enhanced AWS ECS Deployment Script
# AI Blockchain Analytics with RoadRunner, Redis, PostgreSQL
# Author: AI Assistant
# Version: 2.0

set -euo pipefail

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
STACK_NAME="${STACK_NAME:-ai-blockchain-analytics}"
ENVIRONMENT="${ENVIRONMENT:-production}"
AWS_REGION="${AWS_REGION:-us-east-1}"
ECR_REPOSITORY="${ECR_REPOSITORY:-}"
IMAGE_TAG="${IMAGE_TAG:-latest}"
DOMAIN_NAME="${DOMAIN_NAME:-analytics.yourdomain.com}"

# CloudFormation template
TEMPLATE_FILE="ecs/enhanced-cloudformation.yaml"

# Required AWS CLI tools
REQUIRED_TOOLS=("aws" "jq" "docker")

# Deployment phases
PHASES=(
    "preflight"
    "build"
    "infrastructure"
    "database"
    "application"
    "validation"
    "cleanup"
)

# Logging functions
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}"
}

error() {
    echo -e "${RED}[ERROR] $1${NC}" >&2
}

warning() {
    echo -e "${YELLOW}[WARNING] $1${NC}"
}

info() {
    echo -e "${BLUE}[INFO] $1${NC}"
}

# Check if required tools are installed
check_prerequisites() {
    log "Checking prerequisites..."
    
    for tool in "${REQUIRED_TOOLS[@]}"; do
        if ! command -v "$tool" &> /dev/null; then
            error "$tool is not installed or not in PATH"
            exit 1
        fi
    done
    
    # Check AWS CLI configuration
    if ! aws sts get-caller-identity &> /dev/null; then
        error "AWS CLI is not configured or credentials are invalid"
        exit 1
    fi
    
    # Check if ECR repository is provided
    if [[ -z "$ECR_REPOSITORY" ]]; then
        error "ECR_REPOSITORY environment variable is required"
        exit 1
    fi
    
    # Validate region
    if ! aws ec2 describe-regions --region-names "$AWS_REGION" &> /dev/null; then
        error "Invalid AWS region: $AWS_REGION"
        exit 1
    fi
    
    log "Prerequisites check completed successfully"
}

# Get or create ECR repository
setup_ecr_repository() {
    log "Setting up ECR repository..."
    
    local repository_name
    repository_name=$(basename "$ECR_REPOSITORY")
    
    # Check if repository exists
    if aws ecr describe-repositories --repository-names "$repository_name" --region "$AWS_REGION" &> /dev/null; then
        info "ECR repository $repository_name already exists"
    else
        info "Creating ECR repository: $repository_name"
        aws ecr create-repository \
            --repository-name "$repository_name" \
            --region "$AWS_REGION" \
            --image-scanning-configuration scanOnPush=true \
            --encryption-configuration encryptionType=AES256
    fi
    
    # Get repository URI
    ECR_REPOSITORY=$(aws ecr describe-repositories \
        --repository-names "$repository_name" \
        --region "$AWS_REGION" \
        --query 'repositories[0].repositoryUri' \
        --output text)
    
    log "ECR repository setup completed: $ECR_REPOSITORY"
}

# Build and push Docker image
build_and_push_image() {
    log "Building and pushing Docker image..."
    
    # Login to ECR
    aws ecr get-login-password --region "$AWS_REGION" | \
        docker login --username AWS --password-stdin "$ECR_REPOSITORY"
    
    # Build image with multiple tags
    docker build \
        -t "${ECR_REPOSITORY}:${IMAGE_TAG}" \
        -t "${ECR_REPOSITORY}:latest" \
        -f docker/Dockerfile .
    
    # Push images
    docker push "${ECR_REPOSITORY}:${IMAGE_TAG}"
    docker push "${ECR_REPOSITORY}:latest"
    
    log "Docker image built and pushed successfully"
}

# Get VPC and subnet information
get_vpc_info() {
    log "Getting VPC and subnet information..."
    
    # Get default VPC if not specified
    if [[ -z "${VPC_ID:-}" ]]; then
        VPC_ID=$(aws ec2 describe-vpcs \
            --filters "Name=isDefault,Values=true" \
            --query 'Vpcs[0].VpcId' \
            --output text \
            --region "$AWS_REGION")
        
        if [[ "$VPC_ID" == "None" ]]; then
            error "No default VPC found. Please specify VPC_ID environment variable"
            exit 1
        fi
        
        info "Using default VPC: $VPC_ID"
    fi
    
    # Get private subnets
    if [[ -z "${PRIVATE_SUBNET_IDS:-}" ]]; then
        PRIVATE_SUBNET_IDS=$(aws ec2 describe-subnets \
            --filters "Name=vpc-id,Values=$VPC_ID" "Name=tag:Name,Values=*private*" \
            --query 'Subnets[].SubnetId' \
            --output text \
            --region "$AWS_REGION" | tr '\t' ',')
        
        if [[ -z "$PRIVATE_SUBNET_IDS" ]]; then
            # Fallback to all subnets
            PRIVATE_SUBNET_IDS=$(aws ec2 describe-subnets \
                --filters "Name=vpc-id,Values=$VPC_ID" \
                --query 'Subnets[].SubnetId' \
                --output text \
                --region "$AWS_REGION" | tr '\t' ',')
        fi
        
        info "Using private subnets: $PRIVATE_SUBNET_IDS"
    fi
    
    # Get public subnets
    if [[ -z "${PUBLIC_SUBNET_IDS:-}" ]]; then
        PUBLIC_SUBNET_IDS=$(aws ec2 describe-subnets \
            --filters "Name=vpc-id,Values=$VPC_ID" "Name=tag:Name,Values=*public*" \
            --query 'Subnets[].SubnetId' \
            --output text \
            --region "$AWS_REGION" | tr '\t' ',')
        
        if [[ -z "$PUBLIC_SUBNET_IDS" ]]; then
            # Fallback to all subnets
            PUBLIC_SUBNET_IDS="$PRIVATE_SUBNET_IDS"
        fi
        
        info "Using public subnets: $PUBLIC_SUBNET_IDS"
    fi
}

# Get or create SSL certificate
get_ssl_certificate() {
    log "Getting SSL certificate..."
    
    # Check if certificate exists
    CERTIFICATE_ARN=$(aws acm list-certificates \
        --region "$AWS_REGION" \
        --query "CertificateSummaryList[?DomainName=='$DOMAIN_NAME'].CertificateArn" \
        --output text)
    
    if [[ -z "$CERTIFICATE_ARN" || "$CERTIFICATE_ARN" == "None" ]]; then
        warning "No SSL certificate found for $DOMAIN_NAME"
        info "Please create an SSL certificate in ACM for $DOMAIN_NAME"
        info "You can use: aws acm request-certificate --domain-name $DOMAIN_NAME --validation-method DNS"
        
        # Use a placeholder certificate ARN
        CERTIFICATE_ARN="arn:aws:acm:${AWS_REGION}:${AWS_ACCOUNT_ID}:certificate/placeholder"
        warning "Using placeholder certificate ARN. Update manually after certificate creation."
    else
        info "Using SSL certificate: $CERTIFICATE_ARN"
    fi
}

# Generate random passwords
generate_passwords() {
    log "Generating secure passwords..."
    
    DB_PASSWORD="${DB_PASSWORD:-$(openssl rand -base64 32 | tr -d '=+/' | cut -c1-25)}"
    REDIS_PASSWORD="${REDIS_PASSWORD:-$(openssl rand -base64 32 | tr -d '=+/' | cut -c1-25)}"
    
    # Store passwords securely
    cat > ".env.ecs.${ENVIRONMENT}" <<EOF
DB_PASSWORD=${DB_PASSWORD}
REDIS_PASSWORD=${REDIS_PASSWORD}
ECR_REPOSITORY=${ECR_REPOSITORY}
VPC_ID=${VPC_ID}
PRIVATE_SUBNET_IDS=${PRIVATE_SUBNET_IDS}
PUBLIC_SUBNET_IDS=${PUBLIC_SUBNET_IDS}
CERTIFICATE_ARN=${CERTIFICATE_ARN}
EOF
    
    warning "Passwords stored in .env.ecs.${ENVIRONMENT} - keep this file secure!"
}

# Deploy CloudFormation stack
deploy_infrastructure() {
    log "Deploying infrastructure with CloudFormation..."
    
    # Get AWS account ID
    AWS_ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
    
    # Prepare parameters
    local parameters=(
        "ParameterKey=Environment,ParameterValue=$ENVIRONMENT"
        "ParameterKey=VpcId,ParameterValue=$VPC_ID"
        "ParameterKey=PrivateSubnetIds,ParameterValue=$PRIVATE_SUBNET_IDS"
        "ParameterKey=PublicSubnetIds,ParameterValue=$PUBLIC_SUBNET_IDS"
        "ParameterKey=ECRRepository,ParameterValue=$ECR_REPOSITORY"
        "ParameterKey=ImageTag,ParameterValue=$IMAGE_TAG"
        "ParameterKey=DomainName,ParameterValue=$DOMAIN_NAME"
        "ParameterKey=CertificateArn,ParameterValue=$CERTIFICATE_ARN"
        "ParameterKey=DatabasePassword,ParameterValue=$DB_PASSWORD"
        "ParameterKey=RedisPassword,ParameterValue=$REDIS_PASSWORD"
    )
    
    # Check if stack exists
    if aws cloudformation describe-stacks --stack-name "$STACK_NAME" --region "$AWS_REGION" &> /dev/null; then
        info "Updating existing CloudFormation stack: $STACK_NAME"
        
        aws cloudformation update-stack \
            --stack-name "$STACK_NAME" \
            --template-body "file://$TEMPLATE_FILE" \
            --parameters "${parameters[@]}" \
            --capabilities CAPABILITY_IAM CAPABILITY_NAMED_IAM \
            --region "$AWS_REGION"
        
        # Wait for update to complete
        info "Waiting for stack update to complete..."
        aws cloudformation wait stack-update-complete \
            --stack-name "$STACK_NAME" \
            --region "$AWS_REGION"
    else
        info "Creating new CloudFormation stack: $STACK_NAME"
        
        aws cloudformation create-stack \
            --stack-name "$STACK_NAME" \
            --template-body "file://$TEMPLATE_FILE" \
            --parameters "${parameters[@]}" \
            --capabilities CAPABILITY_IAM CAPABILITY_NAMED_IAM \
            --enable-termination-protection \
            --region "$AWS_REGION"
        
        # Wait for creation to complete
        info "Waiting for stack creation to complete..."
        aws cloudformation wait stack-create-complete \
            --stack-name "$STACK_NAME" \
            --region "$AWS_REGION"
    fi
    
    log "Infrastructure deployment completed successfully"
}

# Run database migrations
run_database_migrations() {
    log "Running database migrations..."
    
    # Get cluster name
    local cluster_name
    cluster_name=$(aws cloudformation describe-stacks \
        --stack-name "$STACK_NAME" \
        --region "$AWS_REGION" \
        --query 'Stacks[0].Outputs[?OutputKey==`ClusterName`].OutputValue' \
        --output text)
    
    # Get task definition ARN
    local task_def_arn
    task_def_arn=$(aws ecs describe-task-definition \
        --task-definition "${STACK_NAME}-app" \
        --region "$AWS_REGION" \
        --query 'taskDefinition.taskDefinitionArn' \
        --output text)
    
    # Get subnet and security group
    local subnet_id
    subnet_id=$(echo "$PRIVATE_SUBNET_IDS" | cut -d',' -f1)
    
    local security_group
    security_group=$(aws ec2 describe-security-groups \
        --filters "Name=group-name,Values=${STACK_NAME}-ecs-sg" \
        --region "$AWS_REGION" \
        --query 'SecurityGroups[0].GroupId' \
        --output text)
    
    # Run migration task
    info "Starting migration task..."
    local task_arn
    task_arn=$(aws ecs run-task \
        --cluster "$cluster_name" \
        --task-definition "$task_def_arn" \
        --launch-type FARGATE \
        --network-configuration "awsvpcConfiguration={subnets=[$subnet_id],securityGroups=[$security_group],assignPublicIp=DISABLED}" \
        --overrides '{
            "containerOverrides": [{
                "name": "roadrunner-app",
                "command": ["php", "artisan", "migrate", "--force"]
            }]
        }' \
        --region "$AWS_REGION" \
        --query 'tasks[0].taskArn' \
        --output text)
    
    # Wait for migration to complete
    info "Waiting for migration task to complete..."
    aws ecs wait tasks-stopped \
        --cluster "$cluster_name" \
        --tasks "$task_arn" \
        --region "$AWS_REGION"
    
    # Check migration task exit code
    local exit_code
    exit_code=$(aws ecs describe-tasks \
        --cluster "$cluster_name" \
        --tasks "$task_arn" \
        --region "$AWS_REGION" \
        --query 'tasks[0].containers[0].exitCode' \
        --output text)
    
    if [[ "$exit_code" == "0" ]]; then
        log "Database migrations completed successfully"
    else
        error "Database migrations failed with exit code: $exit_code"
        return 1
    fi
}

# Update ECS services
update_services() {
    log "Updating ECS services..."
    
    # Get cluster name
    local cluster_name
    cluster_name=$(aws cloudformation describe-stacks \
        --stack-name "$STACK_NAME" \
        --region "$AWS_REGION" \
        --query 'Stacks[0].Outputs[?OutputKey==`ClusterName`].OutputValue' \
        --output text)
    
    # Update app service
    info "Updating app service..."
    aws ecs update-service \
        --cluster "$cluster_name" \
        --service "${STACK_NAME}-app" \
        --force-new-deployment \
        --region "$AWS_REGION" > /dev/null
    
    # Update worker service
    info "Updating worker service..."
    aws ecs update-service \
        --cluster "$cluster_name" \
        --service "${STACK_NAME}-worker" \
        --force-new-deployment \
        --region "$AWS_REGION" > /dev/null
    
    # Wait for services to be stable
    info "Waiting for services to stabilize..."
    aws ecs wait services-stable \
        --cluster "$cluster_name" \
        --services "${STACK_NAME}-app" "${STACK_NAME}-worker" \
        --region "$AWS_REGION"
    
    log "ECS services updated successfully"
}

# Validate deployment
validate_deployment() {
    log "Validating deployment..."
    
    # Get stack outputs
    local outputs
    outputs=$(aws cloudformation describe-stacks \
        --stack-name "$STACK_NAME" \
        --region "$AWS_REGION" \
        --query 'Stacks[0].Outputs' \
        --output json)
    
    local app_url
    app_url=$(echo "$outputs" | jq -r '.[] | select(.OutputKey=="ApplicationURL") | .OutputValue')
    
    local alb_dns
    alb_dns=$(echo "$outputs" | jq -r '.[] | select(.OutputKey=="LoadBalancerDNS") | .OutputValue')
    
    # Test ALB health
    info "Testing load balancer health..."
    if curl -s -o /dev/null -w "%{http_code}" "http://$alb_dns/health" | grep -q "200"; then
        log "Load balancer health check: OK"
    else
        warning "Load balancer health check failed - this might be expected if health endpoint is not implemented"
    fi
    
    # Check ECS service status
    info "Checking ECS service status..."
    local cluster_name
    cluster_name=$(echo "$outputs" | jq -r '.[] | select(.OutputKey=="ClusterName") | .OutputValue')
    
    local app_service_status
    app_service_status=$(aws ecs describe-services \
        --cluster "$cluster_name" \
        --services "${STACK_NAME}-app" \
        --region "$AWS_REGION" \
        --query 'services[0].runningCount' \
        --output text)
    
    local worker_service_status
    worker_service_status=$(aws ecs describe-services \
        --cluster "$cluster_name" \
        --services "${STACK_NAME}-worker" \
        --region "$AWS_REGION" \
        --query 'services[0].runningCount' \
        --output text)
    
    info "App service running tasks: $app_service_status"
    info "Worker service running tasks: $worker_service_status"
    
    # Check RDS status
    info "Checking RDS status..."
    local db_status
    db_status=$(aws rds describe-db-instances \
        --db-instance-identifier "${STACK_NAME}-postgres" \
        --region "$AWS_REGION" \
        --query 'DBInstances[0].DBInstanceStatus' \
        --output text)
    
    info "Database status: $db_status"
    
    # Check ElastiCache status
    info "Checking ElastiCache status..."
    local redis_status
    redis_status=$(aws elasticache describe-replication-groups \
        --replication-group-id "${STACK_NAME}-redis" \
        --region "$AWS_REGION" \
        --query 'ReplicationGroups[0].Status' \
        --output text)
    
    info "Redis status: $redis_status"
    
    log "Deployment validation completed"
}

# Cleanup function
cleanup_deployment() {
    if [[ "${CLEANUP:-false}" == "true" ]]; then
        warning "Cleaning up deployment..."
        
        # Delete CloudFormation stack
        aws cloudformation delete-stack \
            --stack-name "$STACK_NAME" \
            --region "$AWS_REGION"
        
        # Wait for deletion
        info "Waiting for stack deletion to complete..."
        aws cloudformation wait stack-delete-complete \
            --stack-name "$STACK_NAME" \
            --region "$AWS_REGION"
        
        log "Cleanup completed"
    fi
}

# Main deployment function
deploy() {
    local phases_to_run=("${@:-${PHASES[@]}}")
    
    log "Starting enhanced ECS deployment for $STACK_NAME"
    log "Environment: $ENVIRONMENT"
    log "Region: $AWS_REGION"
    log "ECR Repository: $ECR_REPOSITORY"
    log "Image Tag: $IMAGE_TAG"
    
    for phase in "${phases_to_run[@]}"; do
        case $phase in
            "preflight")
                check_prerequisites
                setup_ecr_repository
                get_vpc_info
                get_ssl_certificate
                generate_passwords
                ;;
            "build")
                build_and_push_image
                ;;
            "infrastructure")
                deploy_infrastructure
                ;;
            "database")
                run_database_migrations
                ;;
            "application")
                update_services
                ;;
            "validation")
                validate_deployment
                ;;
            "cleanup")
                cleanup_deployment
                ;;
            *)
                error "Unknown phase: $phase"
                exit 1
                ;;
        esac
    done
    
    log "Deployment completed successfully!"
    
    # Show deployment summary
    echo
    info "=== DEPLOYMENT SUMMARY ==="
    echo "Stack Name: $STACK_NAME"
    echo "Environment: $ENVIRONMENT"
    echo "Region: $AWS_REGION"
    echo "Application URL: https://$DOMAIN_NAME"
    echo "ECR Repository: $ECR_REPOSITORY"
    echo
    info "To check status: aws ecs list-services --cluster ${STACK_NAME}-cluster --region $AWS_REGION"
    info "To view logs: aws logs tail /ecs/$STACK_NAME/app --follow --region $AWS_REGION"
    info "To scale services: aws ecs update-service --cluster ${STACK_NAME}-cluster --service ${STACK_NAME}-app --desired-count 5 --region $AWS_REGION"
    echo
}

# Handle script termination
trap cleanup_deployment EXIT

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --environment|-e)
            ENVIRONMENT="$2"
            shift 2
            ;;
        --region|-r)
            AWS_REGION="$2"
            shift 2
            ;;
        --stack-name|-s)
            STACK_NAME="$2"
            shift 2
            ;;
        --ecr-repository)
            ECR_REPOSITORY="$2"
            shift 2
            ;;
        --image-tag|-t)
            IMAGE_TAG="$2"
            shift 2
            ;;
        --domain-name|-d)
            DOMAIN_NAME="$2"
            shift 2
            ;;
        --vpc-id)
            VPC_ID="$2"
            shift 2
            ;;
        --private-subnets)
            PRIVATE_SUBNET_IDS="$2"
            shift 2
            ;;
        --public-subnets)
            PUBLIC_SUBNET_IDS="$2"
            shift 2
            ;;
        --certificate-arn)
            CERTIFICATE_ARN="$2"
            shift 2
            ;;
        --cleanup)
            CLEANUP="true"
            shift
            ;;
        --skip-build)
            PHASES=("${PHASES[@]/build}")
            shift
            ;;
        --help|-h)
            cat <<EOF
Enhanced AWS ECS Deployment Script

Usage: $0 [OPTIONS] [PHASES...]

OPTIONS:
    -e, --environment ENV       Set environment (default: production)
    -r, --region REGION         Set AWS region (default: us-east-1)
    -s, --stack-name NAME       Set CloudFormation stack name (default: ai-blockchain-analytics)
    --ecr-repository REPO       Set ECR repository URI (required)
    -t, --image-tag TAG         Set Docker image tag (default: latest)
    -d, --domain-name DOMAIN    Set domain name (default: analytics.yourdomain.com)
    --vpc-id VPC                Set VPC ID (uses default VPC if not specified)
    --private-subnets SUBNETS   Comma-separated private subnet IDs
    --public-subnets SUBNETS    Comma-separated public subnet IDs
    --certificate-arn ARN       SSL certificate ARN
    --cleanup                   Clean up deployment on exit
    --skip-build                Skip Docker image building
    -h, --help                  Show this help message

PHASES:
    preflight       Check prerequisites and setup
    build           Build and push Docker image
    infrastructure  Deploy CloudFormation stack
    database        Run database migrations
    application     Update ECS services
    validation      Validate deployment
    cleanup         Clean up resources

EXAMPLES:
    # Full deployment
    $0 --ecr-repository 123456789012.dkr.ecr.us-east-1.amazonaws.com/ai-blockchain-analytics

    # Deploy only application
    $0 application --ecr-repository REPO_URI

    # Deploy with custom domain
    $0 --domain-name myapp.example.com --ecr-repository REPO_URI

    # Cleanup deployment
    $0 --cleanup

REQUIRED ENVIRONMENT VARIABLES:
    ECR_REPOSITORY             ECR repository URI (or use --ecr-repository)

OPTIONAL ENVIRONMENT VARIABLES:
    AWS_REGION                 AWS region
    STACK_NAME                 CloudFormation stack name
    ENVIRONMENT                Deployment environment
    DOMAIN_NAME                Application domain name
    VPC_ID                     VPC ID
    PRIVATE_SUBNET_IDS         Private subnet IDs
    PUBLIC_SUBNET_IDS          Public subnet IDs
    CERTIFICATE_ARN            SSL certificate ARN
    DB_PASSWORD                Database password (auto-generated if not set)
    REDIS_PASSWORD             Redis password (auto-generated if not set)

EOF
            exit 0
            ;;
        *)
            # Remaining arguments are phases
            break
            ;;
    esac
done

# Validate required parameters
if [[ -z "$ECR_REPOSITORY" ]]; then
    error "ECR repository is required. Use --ecr-repository or set ECR_REPOSITORY environment variable"
    exit 1
fi

# Run deployment
deploy "$@"