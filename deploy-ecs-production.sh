#!/bin/bash

# AI Blockchain Analytics - Production ECS Deployment Script
# Enhanced deployment with RoadRunner, RDS PostgreSQL, ElastiCache Redis

set -euo pipefail

# Configuration
STACK_NAME="${STACK_NAME:-ai-blockchain-analytics-production}"
REGION="${AWS_REGION:-us-east-1}"
ENVIRONMENT="${ENVIRONMENT:-production}"
IMAGE_TAG="${IMAGE_TAG:-latest}"
DOMAIN_NAME="${DOMAIN_NAME:-analytics.yourdomain.com}"
VPC_ID="${VPC_ID:-}"
PRIVATE_SUBNET_IDS="${PRIVATE_SUBNET_IDS:-}"
PUBLIC_SUBNET_IDS="${PUBLIC_SUBNET_IDS:-}"
CERTIFICATE_ARN="${CERTIFICATE_ARN:-}"
ECR_REPOSITORY="${ECR_REPOSITORY:-}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}"
}

warn() {
    echo -e "${YELLOW}[$(date +'%Y-%m-%d %H:%M:%S')] WARNING: $1${NC}"
}

error() {
    echo -e "${RED}[$(date +'%Y-%m-%d %H:%M:%S')] ERROR: $1${NC}"
    exit 1
}

info() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')] INFO: $1${NC}"
}

# Check prerequisites
check_prerequisites() {
    log "Checking prerequisites..."
    
    # Check if AWS CLI is installed
    if ! command -v aws &> /dev/null; then
        error "AWS CLI is not installed. Please install AWS CLI first."
    fi
    
    # Check if jq is installed
    if ! command -v jq &> /dev/null; then
        error "jq is not installed. Please install jq first."
    fi
    
    # Check AWS credentials
    if ! aws sts get-caller-identity &> /dev/null; then
        error "AWS credentials not configured. Please run 'aws configure' first."
    fi
    
    # Get AWS account ID
    ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
    
    log "Prerequisites check completed successfully"
    log "AWS Account ID: ${ACCOUNT_ID}"
    log "AWS Region: ${REGION}"
}

# Validate required parameters
validate_parameters() {
    log "Validating deployment parameters..."
    
    if [[ -z "${VPC_ID}" ]]; then
        error "VPC_ID is required. Please set VPC_ID environment variable."
    fi
    
    if [[ -z "${PRIVATE_SUBNET_IDS}" ]]; then
        error "PRIVATE_SUBNET_IDS is required. Please set PRIVATE_SUBNET_IDS environment variable."
    fi
    
    if [[ -z "${PUBLIC_SUBNET_IDS}" ]]; then
        error "PUBLIC_SUBNET_IDS is required. Please set PUBLIC_SUBNET_IDS environment variable."
    fi
    
    if [[ -z "${CERTIFICATE_ARN}" ]]; then
        error "CERTIFICATE_ARN is required. Please set CERTIFICATE_ARN environment variable."
    fi
    
    if [[ -z "${ECR_REPOSITORY}" ]]; then
        error "ECR_REPOSITORY is required. Please set ECR_REPOSITORY environment variable."
    fi
    
    log "Parameter validation completed successfully"
}

# Create ECR repository if it doesn't exist
create_ecr_repository() {
    log "Creating ECR repository if it doesn't exist..."
    
    if aws ecr describe-repositories --repository-names ai-blockchain-analytics --region "${REGION}" &> /dev/null; then
        info "ECR repository already exists"
    else
        aws ecr create-repository \
            --repository-name ai-blockchain-analytics \
            --region "${REGION}" \
            --image-tag-mutability MUTABLE \
            --image-scanning-configuration scanOnPush=true
        log "ECR repository created successfully"
    fi
    
    # Get repository URI
    ECR_REPOSITORY=$(aws ecr describe-repositories \
        --repository-names ai-blockchain-analytics \
        --region "${REGION}" \
        --query 'repositories[0].repositoryUri' \
        --output text)
    
    log "ECR Repository URI: ${ECR_REPOSITORY}"
}

# Create SSM parameters for secrets
create_ssm_parameters() {
    log "Creating SSM parameters for application secrets..."
    
    # Function to create or update SSM parameter
    create_or_update_parameter() {
        local param_name="$1"
        local param_value="$2"
        local param_type="${3:-SecureString}"
        
        if aws ssm get-parameter --name "${param_name}" --region "${REGION}" &> /dev/null; then
            aws ssm put-parameter \
                --name "${param_name}" \
                --value "${param_value}" \
                --type "${param_type}" \
                --overwrite \
                --region "${REGION}" > /dev/null
            info "Updated parameter: ${param_name}"
        else
            aws ssm put-parameter \
                --name "${param_name}" \
                --value "${param_value}" \
                --type "${param_type}" \
                --region "${REGION}" > /dev/null
            log "Created parameter: ${param_name}"
        fi
    }
    
    # Prompt for sensitive values if not provided via environment variables
    if [[ -z "${APP_KEY:-}" ]]; then
        read -s -p "Enter Laravel App Key (base64 format): " APP_KEY
        echo
    fi
    
    if [[ -z "${DB_PASSWORD:-}" ]]; then
        read -s -p "Enter Database Password: " DB_PASSWORD
        echo
    fi
    
    if [[ -z "${OPENAI_API_KEY:-}" ]]; then
        read -s -p "Enter OpenAI API Key: " OPENAI_API_KEY
        echo
    fi
    
    if [[ -z "${ETHERSCAN_API_KEY:-}" ]]; then
        read -s -p "Enter Etherscan API Key: " ETHERSCAN_API_KEY
        echo
    fi
    
    if [[ -z "${VERIFICATION_SECRET_KEY:-}" ]]; then
        VERIFICATION_SECRET_KEY=$(openssl rand -base64 32)
        info "Generated random verification secret key"
    fi
    
    if [[ -z "${VERIFICATION_HMAC_KEY:-}" ]]; then
        VERIFICATION_HMAC_KEY=$(openssl rand -base64 32)
        info "Generated random verification HMAC key"
    fi
    
    # Create parameters
    create_or_update_parameter "/ai-blockchain-analytics/app-key" "${APP_KEY}"
    create_or_update_parameter "/ai-blockchain-analytics/db-password" "${DB_PASSWORD}"
    create_or_update_parameter "/ai-blockchain-analytics/openai-api-key" "${OPENAI_API_KEY}"
    create_or_update_parameter "/ai-blockchain-analytics/etherscan-api-key" "${ETHERSCAN_API_KEY}"
    create_or_update_parameter "/ai-blockchain-analytics/verification-secret-key" "${VERIFICATION_SECRET_KEY}"
    create_or_update_parameter "/ai-blockchain-analytics/verification-hmac-key" "${VERIFICATION_HMAC_KEY}"
    
    log "SSM parameters created successfully"
}

# Build and push Docker image
build_and_push_image() {
    log "Building and pushing Docker image..."
    
    # Login to ECR
    aws ecr get-login-password --region "${REGION}" | docker login --username AWS --password-stdin "${ECR_REPOSITORY}"
    
    # Build image
    log "Building Docker image..."
    docker build -f docker/Dockerfile.roadrunner -t "${ECR_REPOSITORY}:${IMAGE_TAG}" .
    
    # Tag as latest if not already
    if [[ "${IMAGE_TAG}" != "latest" ]]; then
        docker tag "${ECR_REPOSITORY}:${IMAGE_TAG}" "${ECR_REPOSITORY}:latest"
    fi
    
    # Push image
    log "Pushing Docker image..."
    docker push "${ECR_REPOSITORY}:${IMAGE_TAG}"
    
    if [[ "${IMAGE_TAG}" != "latest" ]]; then
        docker push "${ECR_REPOSITORY}:latest"
    fi
    
    log "Docker image pushed successfully"
    log "Image URI: ${ECR_REPOSITORY}:${IMAGE_TAG}"
}

# Update CloudFormation template with current values
update_cloudformation_template() {
    log "Updating CloudFormation template..."
    
    # Create temporary template file
    TEMP_TEMPLATE="/tmp/ai-blockchain-analytics-template.json"
    cp ecs/complete-production-deployment.json "${TEMP_TEMPLATE}"
    
    # Replace placeholders in the template
    sed -i.backup \
        -e "s/your-account\.dkr\.ecr\.us-east-1\.amazonaws\.com\/ai-blockchain-analytics/${ECR_REPOSITORY//\//\\/}/g" \
        -e "s/\${ImageTag}/${IMAGE_TAG}/g" \
        -e "s/\${AWS::Region}/${REGION}/g" \
        -e "s/\${AWS::AccountId}/${ACCOUNT_ID}/g" \
        "${TEMP_TEMPLATE}"
    
    log "CloudFormation template updated successfully"
}

# Deploy CloudFormation stack
deploy_cloudformation_stack() {
    log "Deploying CloudFormation stack: ${STACK_NAME}..."
    
    # Check if stack exists
    if aws cloudformation describe-stacks --stack-name "${STACK_NAME}" --region "${REGION}" &> /dev/null; then
        OPERATION="update-stack"
        log "Updating existing stack..."
    else
        OPERATION="create-stack"
        log "Creating new stack..."
    fi
    
    # Deploy stack
    aws cloudformation "${OPERATION}" \
        --stack-name "${STACK_NAME}" \
        --template-body file:///tmp/ai-blockchain-analytics-template.json \
        --parameters \
            ParameterKey=VpcId,ParameterValue="${VPC_ID}" \
            ParameterKey=PrivateSubnetIds,ParameterValue="${PRIVATE_SUBNET_IDS}" \
            ParameterKey=PublicSubnetIds,ParameterValue="${PUBLIC_SUBNET_IDS}" \
            ParameterKey=CertificateArn,ParameterValue="${CERTIFICATE_ARN}" \
            ParameterKey=DomainName,ParameterValue="${DOMAIN_NAME}" \
            ParameterKey=ImageTag,ParameterValue="${IMAGE_TAG}" \
        --capabilities CAPABILITY_IAM \
        --region "${REGION}" \
        --tags \
            Key=Environment,Value="${ENVIRONMENT}" \
            Key=Application,Value=ai-blockchain-analytics \
            Key=ManagedBy,Value=cloudformation
    
    log "CloudFormation stack deployment initiated"
}

# Wait for CloudFormation stack to complete
wait_for_stack_completion() {
    log "Waiting for CloudFormation stack to complete..."
    
    aws cloudformation wait stack-${OPERATION%-stack}-complete \
        --stack-name "${STACK_NAME}" \
        --region "${REGION}"
    
    # Check stack status
    STACK_STATUS=$(aws cloudformation describe-stacks \
        --stack-name "${STACK_NAME}" \
        --region "${REGION}" \
        --query 'Stacks[0].StackStatus' \
        --output text)
    
    if [[ "${STACK_STATUS}" == *"COMPLETE"* ]]; then
        log "CloudFormation stack deployed successfully"
    else
        error "CloudFormation stack deployment failed with status: ${STACK_STATUS}"
    fi
}

# Run database migrations
run_database_migrations() {
    log "Running database migrations..."
    
    # Get cluster name
    CLUSTER_NAME=$(aws cloudformation describe-stacks \
        --stack-name "${STACK_NAME}" \
        --region "${REGION}" \
        --query 'Stacks[0].Outputs[?OutputKey==`ClusterName`].OutputValue' \
        --output text)
    
    # Get service name
    SERVICE_NAME="ai-blockchain-analytics-roadrunner"
    
    # Wait for service to be stable
    log "Waiting for ECS service to be stable..."
    aws ecs wait services-stable \
        --cluster "${CLUSTER_NAME}" \
        --services "${SERVICE_NAME}" \
        --region "${REGION}"
    
    # Get a running task
    TASK_ARN=$(aws ecs list-tasks \
        --cluster "${CLUSTER_NAME}" \
        --service-name "${SERVICE_NAME}" \
        --region "${REGION}" \
        --query 'taskArns[0]' \
        --output text)
    
    if [[ "${TASK_ARN}" == "None" || -z "${TASK_ARN}" ]]; then
        error "No running tasks found for migration"
    fi
    
    # Run migrations
    log "Executing database migrations..."
    aws ecs execute-command \
        --cluster "${CLUSTER_NAME}" \
        --task "${TASK_ARN}" \
        --container "roadrunner-app" \
        --command "php artisan migrate --force" \
        --interactive \
        --region "${REGION}"
    
    # Cache configuration
    log "Caching application configuration..."
    aws ecs execute-command \
        --cluster "${CLUSTER_NAME}" \
        --task "${TASK_ARN}" \
        --container "roadrunner-app" \
        --command "php artisan config:cache" \
        --interactive \
        --region "${REGION}"
    
    log "Database migrations completed successfully"
}

# Check deployment status
check_deployment_status() {
    log "Checking deployment status..."
    
    # Get stack outputs
    OUTPUTS=$(aws cloudformation describe-stacks \
        --stack-name "${STACK_NAME}" \
        --region "${REGION}" \
        --query 'Stacks[0].Outputs')
    
    echo "=== CloudFormation Stack Outputs ==="
    echo "${OUTPUTS}" | jq -r '.[] | "\(.OutputKey): \(.OutputValue)"'
    
    # Get ECS cluster status
    CLUSTER_NAME=$(echo "${OUTPUTS}" | jq -r '.[] | select(.OutputKey=="ClusterName") | .OutputValue')
    
    echo ""
    echo "=== ECS Service Status ==="
    aws ecs describe-services \
        --cluster "${CLUSTER_NAME}" \
        --services ai-blockchain-analytics-roadrunner ai-blockchain-analytics-horizon \
        --region "${REGION}" \
        --query 'services[].[serviceName,status,runningCount,desiredCount,pendingCount]' \
        --output table
    
    echo ""
    echo "=== ECS Task Status ==="
    aws ecs list-tasks \
        --cluster "${CLUSTER_NAME}" \
        --region "${REGION}" \
        --query 'taskArns[]' \
        --output text | xargs -I {} aws ecs describe-tasks \
        --cluster "${CLUSTER_NAME}" \
        --tasks {} \
        --region "${REGION}" \
        --query 'tasks[].[taskDefinitionArn,lastStatus,healthStatus,createdAt]' \
        --output table
    
    echo ""
    echo "=== RDS Database Status ==="
    DB_ENDPOINT=$(echo "${OUTPUTS}" | jq -r '.[] | select(.OutputKey=="DatabaseEndpoint") | .OutputValue')
    aws rds describe-db-instances \
        --db-instance-identifier ai-blockchain-analytics-postgres \
        --region "${REGION}" \
        --query 'DBInstances[0].[DBInstanceStatus,Engine,EngineVersion,AllocatedStorage,DBInstanceClass]' \
        --output table
    
    echo ""
    echo "=== ElastiCache Redis Status ==="
    REDIS_ENDPOINT=$(echo "${OUTPUTS}" | jq -r '.[] | select(.OutputKey=="RedisEndpoint") | .OutputValue')
    aws elasticache describe-replication-groups \
        --replication-group-id ai-blockchain-analytics-redis \
        --region "${REGION}" \
        --query 'ReplicationGroups[0].[Status,Engine,EngineVersion,NumCacheClusters,CacheNodeType]' \
        --output table
}

# Show application URLs and information
show_application_info() {
    log "Application deployment information:"
    
    # Get stack outputs
    OUTPUTS=$(aws cloudformation describe-stacks \
        --stack-name "${STACK_NAME}" \
        --region "${REGION}" \
        --query 'Stacks[0].Outputs')
    
    LOAD_BALANCER_URL=$(echo "${OUTPUTS}" | jq -r '.[] | select(.OutputKey=="LoadBalancerURL") | .OutputValue')
    DB_ENDPOINT=$(echo "${OUTPUTS}" | jq -r '.[] | select(.OutputKey=="DatabaseEndpoint") | .OutputValue')
    REDIS_ENDPOINT=$(echo "${OUTPUTS}" | jq -r '.[] | select(.OutputKey=="RedisEndpoint") | .OutputValue')
    EFS_ID=$(echo "${OUTPUTS}" | jq -r '.[] | select(.OutputKey=="EFSFileSystemId") | .OutputValue')
    CLUSTER_NAME=$(echo "${OUTPUTS}" | jq -r '.[] | select(.OutputKey=="ClusterName") | .OutputValue')
    
    echo "=== Application Access ==="
    echo "Application URL: ${LOAD_BALANCER_URL}"
    echo "Custom Domain: https://${DOMAIN_NAME}"
    echo ""
    echo "=== Infrastructure ==="
    echo "ECS Cluster: ${CLUSTER_NAME}"
    echo "Database Endpoint: ${DB_ENDPOINT}"
    echo "Redis Endpoint: ${REDIS_ENDPOINT}"
    echo "EFS File System: ${EFS_ID}"
    echo ""
    echo "=== Monitoring ==="
    echo "CloudWatch Logs: /ecs/ai-blockchain-analytics"
    echo "ECS Console: https://console.aws.amazon.com/ecs/home?region=${REGION}#/clusters/${CLUSTER_NAME}/services"
    echo "RDS Console: https://console.aws.amazon.com/rds/home?region=${REGION}"
    echo "ElastiCache Console: https://console.aws.amazon.com/elasticache/home?region=${REGION}"
}

# Cleanup function
cleanup() {
    log "Cleaning up temporary files..."
    rm -f /tmp/ai-blockchain-analytics-template.json || true
    rm -f /tmp/ai-blockchain-analytics-template.json.backup || true
}

# Main deployment flow
main() {
    log "Starting AI Blockchain Analytics ECS deployment..."
    log "Stack Name: ${STACK_NAME}"
    log "Environment: ${ENVIRONMENT}"
    log "Region: ${REGION}"
    log "Domain: ${DOMAIN_NAME}"
    log "Image Tag: ${IMAGE_TAG}"
    
    # Set trap for cleanup
    trap cleanup EXIT
    
    # Execute deployment steps
    check_prerequisites
    validate_parameters
    create_ecr_repository
    create_ssm_parameters
    build_and_push_image
    update_cloudformation_template
    deploy_cloudformation_stack
    wait_for_stack_completion
    run_database_migrations
    check_deployment_status
    show_application_info
    
    log "🎉 ECS deployment completed successfully!"
    log "Your AI Blockchain Analytics application is now running!"
    
    # Show next steps
    echo ""
    echo "=== Next Steps ==="
    echo "1. Update your DNS to point ${DOMAIN_NAME} to the load balancer"
    echo "2. Access the application at https://${DOMAIN_NAME}"
    echo "3. Monitor the application using CloudWatch"
    echo "4. Check ECS tasks and services in the AWS Console"
    echo "5. Scale the application by updating the ECS service desired count"
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --stack-name)
            STACK_NAME="$2"
            shift 2
            ;;
        --image-tag)
            IMAGE_TAG="$2"
            shift 2
            ;;
        --domain)
            DOMAIN_NAME="$2"
            shift 2
            ;;
        --region)
            REGION="$2"
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
        --skip-build)
            SKIP_BUILD=true
            shift
            ;;
        --help)
            echo "Usage: $0 [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --stack-name NAME       CloudFormation stack name (default: ai-blockchain-analytics-production)"
            echo "  --image-tag TAG         Docker image tag (default: latest)"
            echo "  --domain DOMAIN         Application domain name"
            echo "  --region REGION         AWS region (default: us-east-1)"
            echo "  --vpc-id VPC_ID         VPC ID for deployment"
            echo "  --private-subnets IDS   Comma-separated private subnet IDs"
            echo "  --public-subnets IDS    Comma-separated public subnet IDs"
            echo "  --certificate-arn ARN   ACM certificate ARN for HTTPS"
            echo "  --skip-build            Skip Docker image build and push"
            echo "  --help                  Show this help message"
            echo ""
            echo "Environment Variables:"
            echo "  APP_KEY                 Laravel application key"
            echo "  DB_PASSWORD             Database password"
            echo "  OPENAI_API_KEY          OpenAI API key"
            echo "  ETHERSCAN_API_KEY       Etherscan API key"
            echo "  VERIFICATION_SECRET_KEY Verification secret key (auto-generated if not set)"
            echo "  VERIFICATION_HMAC_KEY   Verification HMAC key (auto-generated if not set)"
            exit 0
            ;;
        *)
            error "Unknown option: $1"
            ;;
    esac
done

# Run main function
main
