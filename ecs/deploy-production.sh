#!/bin/bash

# AI Blockchain Analytics - Production ECS Deployment Script
# Comprehensive production deployment with RoadRunner, RDS PostgreSQL, and ElastiCache Redis

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m'

# Configuration
PROJECT_NAME="ai-blockchain-analytics"
AWS_REGION="${AWS_REGION:-us-east-1}"
AWS_ACCOUNT_ID="${AWS_ACCOUNT_ID:-}"
DOCKER_REGISTRY="${DOCKER_REGISTRY:-${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com}"
IMAGE_TAG="${IMAGE_TAG:-latest}"
CLUSTER_NAME="${CLUSTER_NAME:-${PROJECT_NAME}-production}"
VPC_ID="${VPC_ID:-}"
SUBNET_IDS="${SUBNET_IDS:-}"
SECURITY_GROUP_ID="${SECURITY_GROUP_ID:-}"
RDS_ENDPOINT="${RDS_ENDPOINT:-}"
ELASTICACHE_ENDPOINT="${ELASTICACHE_ENDPOINT:-}"
DRY_RUN="${DRY_RUN:-false}"
SKIP_BUILD="${SKIP_BUILD:-false}"
SKIP_INFRASTRUCTURE="${SKIP_INFRASTRUCTURE:-false}"
FORCE="${FORCE:-false}"

# Print banner
print_banner() {
    echo -e "${CYAN}"
    echo "═══════════════════════════════════════════════════════════════════"
    echo "   🚀 AI Blockchain Analytics - Production ECS Deployment"
    echo "   ☁️  RoadRunner + RDS PostgreSQL + ElastiCache Redis"
    echo "═══════════════════════════════════════════════════════════════════"
    echo -e "${NC}"
}

# Logging functions
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

log_step() {
    echo -e "${PURPLE}[STEP]${NC} $1"
}

# Show usage
show_usage() {
    cat << 'EOF'
Usage: ./deploy-production.sh [OPTIONS]

OPTIONS:
  --registry REGISTRY       Docker registry URL (default: ECR)
  --tag TAG                Docker image tag (default: latest)
  --region REGION          AWS region (default: us-east-1)
  --account-id ID          AWS account ID (required)
  --cluster CLUSTER        ECS cluster name (default: ai-blockchain-analytics-production)
  --vpc-id VPC_ID          VPC ID for ECS tasks
  --subnet-ids SUBNETS     Comma-separated subnet IDs
  --security-group-id SG   Security group ID
  --rds-endpoint ENDPOINT  RDS PostgreSQL endpoint
  --redis-endpoint ENDPOINT ElastiCache Redis endpoint
  --skip-build             Skip building Docker images
  --skip-infrastructure    Skip creating infrastructure
  --dry-run               Show what would be done without executing
  --force                 Force deployment without confirmation
  --help                  Show this help message

ENVIRONMENT VARIABLES:
  AWS_REGION              Default AWS region
  AWS_ACCOUNT_ID          Default AWS account ID
  DOCKER_REGISTRY         Default Docker registry
  IMAGE_TAG              Default image tag

EXAMPLES:
  # Deploy with auto-detected settings
  ./deploy-production.sh --account-id 123456789012

  # Deploy with custom settings
  ./deploy-production.sh \
    --account-id 123456789012 \
    --region us-west-2 \
    --tag v1.2.3 \
    --vpc-id vpc-12345 \
    --subnet-ids subnet-12345,subnet-67890

  # Dry run to see what would be deployed
  ./deploy-production.sh --account-id 123456789012 --dry-run

EOF
}

# Parse command line arguments
parse_arguments() {
    while [[ $# -gt 0 ]]; do
        case $1 in
            --registry)
                DOCKER_REGISTRY="$2"
                shift 2
                ;;
            --tag)
                IMAGE_TAG="$2"
                shift 2
                ;;
            --region)
                AWS_REGION="$2"
                shift 2
                ;;
            --account-id)
                AWS_ACCOUNT_ID="$2"
                shift 2
                ;;
            --cluster)
                CLUSTER_NAME="$2"
                shift 2
                ;;
            --vpc-id)
                VPC_ID="$2"
                shift 2
                ;;
            --subnet-ids)
                SUBNET_IDS="$2"
                shift 2
                ;;
            --security-group-id)
                SECURITY_GROUP_ID="$2"
                shift 2
                ;;
            --rds-endpoint)
                RDS_ENDPOINT="$2"
                shift 2
                ;;
            --redis-endpoint)
                ELASTICACHE_ENDPOINT="$2"
                shift 2
                ;;
            --skip-build)
                SKIP_BUILD="true"
                shift
                ;;
            --skip-infrastructure)
                SKIP_INFRASTRUCTURE="true"
                shift
                ;;
            --dry-run)
                DRY_RUN="true"
                shift
                ;;
            --force)
                FORCE="true"
                shift
                ;;
            -h|--help)
                show_usage
                exit 0
                ;;
            *)
                log_error "Unknown argument: $1"
                show_usage
                exit 1
                ;;
        esac
    done

    # Validate required arguments
    if [[ -z "$AWS_ACCOUNT_ID" ]]; then
        log_error "AWS Account ID is required (--account-id or AWS_ACCOUNT_ID env var)"
        exit 1
    fi

    # Set defaults
    if [[ -z "$DOCKER_REGISTRY" ]]; then
        DOCKER_REGISTRY="${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com"
    fi
}

# Check prerequisites
check_prerequisites() {
    log_step "Checking prerequisites..."

    # Check required tools
    command -v aws >/dev/null 2>&1 || { log_error "AWS CLI is required but not installed"; exit 1; }
    command -v docker >/dev/null 2>&1 || { log_error "Docker is required but not installed"; exit 1; }
    command -v jq >/dev/null 2>&1 || { log_error "jq is required but not installed"; exit 1; }
    command -v envsubst >/dev/null 2>&1 || { log_error "envsubst is required but not installed"; exit 1; }

    # Check AWS credentials
    if ! aws sts get-caller-identity >/dev/null 2>&1; then
        log_error "AWS credentials not configured or invalid"
        exit 1
    fi

    # Auto-detect AWS Account ID if not provided
    if [[ -z "$AWS_ACCOUNT_ID" ]]; then
        AWS_ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
        log_info "Auto-detected AWS Account ID: $AWS_ACCOUNT_ID"
    fi

    # Warning for production deployment
    if [[ "$FORCE" != "true" && "$DRY_RUN" != "true" ]]; then
        echo -e "${YELLOW}"
        echo "⚠️  WARNING: This will deploy to PRODUCTION environment in AWS!"
        echo "   Account: $AWS_ACCOUNT_ID"
        echo "   Region: $AWS_REGION"
        echo "   Cluster: $CLUSTER_NAME"
        echo "   Registry: $DOCKER_REGISTRY"
        echo "   Tag: $IMAGE_TAG"
        echo -e "${NC}"
        read -p "Are you sure you want to continue? (yes/no): " confirm
        if [[ "$confirm" != "yes" ]]; then
            log_info "Deployment cancelled"
            exit 0
        fi
    fi

    log_success "Prerequisites check passed"
}

# Auto-detect infrastructure settings
auto_detect_infrastructure() {
    log_step "Auto-detecting infrastructure settings..."

    if [[ "$SKIP_INFRASTRUCTURE" == "true" ]]; then
        log_info "Skipping infrastructure detection (--skip-infrastructure specified)"
        return
    fi

    # Auto-detect VPC if not provided
    if [[ -z "$VPC_ID" ]]; then
        VPC_ID=$(aws ec2 describe-vpcs \
            --filters "Name=is-default,Values=true" \
            --query 'Vpcs[0].VpcId' \
            --output text \
            --region "$AWS_REGION" 2>/dev/null || echo "")
        
        if [[ -n "$VPC_ID" && "$VPC_ID" != "None" ]]; then
            log_info "Auto-detected VPC: $VPC_ID"
        else
            log_warning "Could not auto-detect VPC ID"
        fi
    fi

    # Auto-detect subnets if not provided
    if [[ -z "$SUBNET_IDS" && -n "$VPC_ID" ]]; then
        SUBNET_IDS=$(aws ec2 describe-subnets \
            --filters "Name=vpc-id,Values=$VPC_ID" \
            --query 'Subnets[?MapPublicIpOnLaunch==`true`].SubnetId' \
            --output text \
            --region "$AWS_REGION" 2>/dev/null | tr '\t' ',' || echo "")
        
        if [[ -n "$SUBNET_IDS" ]]; then
            log_info "Auto-detected subnets: $SUBNET_IDS"
        else
            log_warning "Could not auto-detect subnet IDs"
        fi
    fi

    # Check for existing RDS instance
    if [[ -z "$RDS_ENDPOINT" ]]; then
        RDS_ENDPOINT=$(aws rds describe-db-instances \
            --query "DBInstances[?contains(DBInstanceIdentifier, '$PROJECT_NAME')].Endpoint.Address" \
            --output text \
            --region "$AWS_REGION" 2>/dev/null | head -1 || echo "")
        
        if [[ -n "$RDS_ENDPOINT" && "$RDS_ENDPOINT" != "None" ]]; then
            log_info "Found RDS instance: $RDS_ENDPOINT"
        else
            log_warning "No RDS instance found for $PROJECT_NAME"
        fi
    fi

    # Check for existing ElastiCache cluster
    if [[ -z "$ELASTICACHE_ENDPOINT" ]]; then
        ELASTICACHE_ENDPOINT=$(aws elasticache describe-cache-clusters \
            --query "CacheClusters[?contains(CacheClusterId, '$PROJECT_NAME')].RedisConfiguration.PrimaryEndpoint.Address" \
            --output text \
            --region "$AWS_REGION" 2>/dev/null | head -1 || echo "")
        
        if [[ -n "$ELASTICACHE_ENDPOINT" && "$ELASTICACHE_ENDPOINT" != "None" ]]; then
            log_info "Found ElastiCache cluster: $ELASTICACHE_ENDPOINT"
        else
            log_warning "No ElastiCache cluster found for $PROJECT_NAME"
        fi
    fi

    log_success "Infrastructure detection completed"
}

# Create ECR repositories
create_ecr_repositories() {
    log_step "Creating ECR repositories..."

    local repositories=(
        "$PROJECT_NAME"
        "${PROJECT_NAME}-worker"
        "${PROJECT_NAME}-scheduler"
    )

    if [[ "$DRY_RUN" == "true" ]]; then
        for repo in "${repositories[@]}"; do
            log_info "[DRY RUN] Would create ECR repository: $repo"
        done
        return
    fi

    for repo in "${repositories[@]}"; do
        if aws ecr describe-repositories --repository-names "$repo" --region "$AWS_REGION" >/dev/null 2>&1; then
            log_info "ECR repository already exists: $repo"
        else
            log_info "Creating ECR repository: $repo"
            aws ecr create-repository \
                --repository-name "$repo" \
                --region "$AWS_REGION" \
                --image-scanning-configuration scanOnPush=true \
                --encryption-configuration encryptionType=AES256 >/dev/null
        fi
    done

    log_success "ECR repositories ready"
}

# Build and push Docker images
build_and_push_images() {
    if [[ "$SKIP_BUILD" == "true" ]]; then
        log_info "Skipping image build (--skip-build specified)"
        return
    fi

    log_step "Building and pushing Docker images..."

    if [[ "$DRY_RUN" == "true" ]]; then
        log_info "[DRY RUN] Would build and push images to: $DOCKER_REGISTRY"
        return
    fi

    # Login to ECR
    aws ecr get-login-password --region "$AWS_REGION" | \
        docker login --username AWS --password-stdin "$DOCKER_REGISTRY"

    # Build and push each image
    local images=(
        "ai-blockchain-analytics:production"
        "ai-blockchain-analytics-worker:worker"
        "ai-blockchain-analytics-scheduler:scheduler"
    )

    for image_config in "${images[@]}"; do
        local image_name=$(echo "$image_config" | cut -d: -f1)
        local target=$(echo "$image_config" | cut -d: -f2)
        local full_image="${DOCKER_REGISTRY}/${image_name}:${IMAGE_TAG}"
        
        log_info "Building: $full_image (target: $target)"
        docker build --target "$target" -t "$full_image" .
        
        log_info "Pushing: $full_image"
        docker push "$full_image"
    done

    log_success "All images built and pushed successfully"
}

# Create secrets in AWS Secrets Manager
create_secrets() {
    log_step "Creating secrets in AWS Secrets Manager..."

    if [[ "$DRY_RUN" == "true" ]]; then
        log_info "[DRY RUN] Would create secrets in AWS Secrets Manager"
        return
    fi

    local secrets=(
        "ai-blockchain-analytics/app-key:$(openssl rand -base64 32)"
        "ai-blockchain-analytics/jwt-secret:$(openssl rand -base64 64)"
        "ai-blockchain-analytics/db-credentials:{\"username\":\"postgres\",\"password\":\"$(openssl rand -base64 24)\"}"
        "ai-blockchain-analytics/google-cloud-api-key:"
        "ai-blockchain-analytics/etherscan-api-key:"
        "ai-blockchain-analytics/bscscan-api-key:"
    )

    for secret_config in "${secrets[@]}"; do
        local secret_name=$(echo "$secret_config" | cut -d: -f1)
        local secret_value=$(echo "$secret_config" | cut -d: -f2-)
        
        if aws secretsmanager describe-secret --secret-id "$secret_name" --region "$AWS_REGION" >/dev/null 2>&1; then
            log_info "Secret already exists: $secret_name"
        else
            log_info "Creating secret: $secret_name"
            aws secretsmanager create-secret \
                --name "$secret_name" \
                --description "AI Blockchain Analytics secret" \
                --secret-string "$secret_value" \
                --region "$AWS_REGION" >/dev/null
        fi
    done

    log_warning "Remember to update secrets with real values!"
    log_success "Secrets created in AWS Secrets Manager"
}

# Create ECS cluster
create_ecs_cluster() {
    log_step "Creating ECS cluster..."

    if [[ "$DRY_RUN" == "true" ]]; then
        log_info "[DRY RUN] Would create ECS cluster: $CLUSTER_NAME"
        return
    fi

    if aws ecs describe-clusters --clusters "$CLUSTER_NAME" --region "$AWS_REGION" >/dev/null 2>&1; then
        log_info "ECS cluster already exists: $CLUSTER_NAME"
    else
        log_info "Creating ECS cluster: $CLUSTER_NAME"
        aws ecs create-cluster \
            --cluster-name "$CLUSTER_NAME" \
            --capacity-providers FARGATE FARGATE_SPOT \
            --default-capacity-provider-strategy \
                capacityProvider=FARGATE,weight=1 \
                capacityProvider=FARGATE_SPOT,weight=1,base=0 \
            --tags \
                key=Environment,value=production \
                key=Application,value="$PROJECT_NAME" \
            --region "$AWS_REGION" >/dev/null
    fi

    log_success "ECS cluster ready: $CLUSTER_NAME"
}

# Register task definitions
register_task_definitions() {
    log_step "Registering ECS task definitions..."

    local task_definitions=(
        "production-task-definition.json"
        "worker-task-definition.json"
        "scheduler-task-definition.json"
    )

    if [[ "$DRY_RUN" == "true" ]]; then
        for task_def in "${task_definitions[@]}"; do
            log_info "[DRY RUN] Would register task definition: $task_def"
        done
        return
    fi

    for task_def in "${task_definitions[@]}"; do
        local task_def_path="ecs/$task_def"
        
        if [[ ! -f "$task_def_path" ]]; then
            log_error "Task definition file not found: $task_def_path"
            continue
        fi

        log_info "Registering task definition: $task_def"
        
        # Substitute environment variables
        export AWS_ACCOUNT_ID AWS_REGION DOCKER_REGISTRY IMAGE_TAG
        export RDS_ENDPOINT ELASTICACHE_ENDPOINT
        
        envsubst < "$task_def_path" > "/tmp/${task_def}"
        
        # Register the task definition
        aws ecs register-task-definition \
            --cli-input-json "file:///tmp/${task_def}" \
            --region "$AWS_REGION" >/dev/null
            
        # Clean up temp file
        rm -f "/tmp/${task_def}"
    done

    log_success "Task definitions registered"
}

# Create or update ECS services
create_ecs_services() {
    log_step "Creating ECS services..."

    local services=(
        "${PROJECT_NAME}-app:3"
        "${PROJECT_NAME}-worker:2"
        "${PROJECT_NAME}-scheduler:1"
    )

    if [[ "$DRY_RUN" == "true" ]]; then
        for service_config in "${services[@]}"; do
            local service_name=$(echo "$service_config" | cut -d: -f1)
            log_info "[DRY RUN] Would create ECS service: $service_name"
        done
        return
    fi

    for service_config in "${services[@]}"; do
        local service_name=$(echo "$service_config" | cut -d: -f1)
        local desired_count=$(echo "$service_config" | cut -d: -f2)
        local task_definition="${service_name}"
        
        # Check if service already exists
        if aws ecs describe-services \
            --cluster "$CLUSTER_NAME" \
            --services "$service_name" \
            --region "$AWS_REGION" \
            --query 'services[?status==`ACTIVE`]' \
            --output text | grep -q "$service_name"; then
            
            log_info "Updating existing service: $service_name"
            aws ecs update-service \
                --cluster "$CLUSTER_NAME" \
                --service "$service_name" \
                --task-definition "$task_definition" \
                --desired-count "$desired_count" \
                --region "$AWS_REGION" >/dev/null
        else
            log_info "Creating new service: $service_name"
            
            # Create service configuration
            local network_config=""
            if [[ -n "$SUBNET_IDS" && -n "$SECURITY_GROUP_ID" ]]; then
                network_config="awsvpcConfiguration={subnets=[$SUBNET_IDS],securityGroups=[$SECURITY_GROUP_ID],assignPublicIp=ENABLED}"
            elif [[ -n "$SUBNET_IDS" ]]; then
                network_config="awsvpcConfiguration={subnets=[$SUBNET_IDS],assignPublicIp=ENABLED}"
            fi
            
            aws ecs create-service \
                --cluster "$CLUSTER_NAME" \
                --service-name "$service_name" \
                --task-definition "$task_definition" \
                --desired-count "$desired_count" \
                --launch-type FARGATE \
                ${network_config:+--network-configuration "$network_config"} \
                --enable-execute-command \
                --tags \
                    key=Environment,value=production \
                    key=Application,value="$PROJECT_NAME" \
                --region "$AWS_REGION" >/dev/null
        fi
    done

    log_success "ECS services created/updated"
}

# Wait for services to be stable
wait_for_services() {
    log_step "Waiting for ECS services to be stable..."

    local services=(
        "${PROJECT_NAME}-app"
        "${PROJECT_NAME}-worker"
        "${PROJECT_NAME}-scheduler"
    )

    if [[ "$DRY_RUN" == "true" ]]; then
        log_info "[DRY RUN] Would wait for services to be stable"
        return
    fi

    for service in "${services[@]}"; do
        log_info "Waiting for service to be stable: $service"
        
        if ! aws ecs wait services-stable \
            --cluster "$CLUSTER_NAME" \
            --services "$service" \
            --region "$AWS_REGION"; then
            
            log_error "Service failed to become stable: $service"
            
            # Show recent events for debugging
            log_info "Recent events for $service:"
            aws ecs describe-services \
                --cluster "$CLUSTER_NAME" \
                --services "$service" \
                --region "$AWS_REGION" \
                --query 'services[0].events[:5]' \
                --output table || true
                
            continue
        fi
    done

    log_success "All services are stable"
}

# Run post-deployment tasks
run_post_deployment() {
    log_step "Running post-deployment tasks..."

    if [[ "$DRY_RUN" == "true" ]]; then
        log_info "[DRY RUN] Would run post-deployment tasks"
        return
    fi

    # Get a running task ARN for the app service
    local task_arn=$(aws ecs list-tasks \
        --cluster "$CLUSTER_NAME" \
        --service-name "${PROJECT_NAME}-app" \
        --desired-status RUNNING \
        --query 'taskArns[0]' \
        --output text \
        --region "$AWS_REGION" 2>/dev/null || echo "")

    if [[ -n "$task_arn" && "$task_arn" != "None" ]]; then
        log_info "Running database migrations..."
        aws ecs execute-command \
            --cluster "$CLUSTER_NAME" \
            --task "$task_arn" \
            --container "app" \
            --command "php artisan migrate --force" \
            --interactive \
            --region "$AWS_REGION" || log_warning "Migration command failed"

        log_info "Clearing and caching configurations..."
        aws ecs execute-command \
            --cluster "$CLUSTER_NAME" \
            --task "$task_arn" \
            --container "app" \
            --command "php artisan config:cache && php artisan route:cache && php artisan view:cache" \
            --interactive \
            --region "$AWS_REGION" || log_warning "Cache commands failed"
    else
        log_warning "No running app tasks found, skipping post-deployment tasks"
    fi

    log_success "Post-deployment tasks completed"
}

# Show deployment status
show_status() {
    log_step "Deployment status:"

    echo -e "\n${CYAN}ECS Cluster: $CLUSTER_NAME${NC}"
    aws ecs describe-clusters --clusters "$CLUSTER_NAME" --region "$AWS_REGION" \
        --query 'clusters[0].[clusterName,status,runningTasksCount,pendingTasksCount,activeServicesCount]' \
        --output table 2>/dev/null || log_warning "Cluster not found"

    echo -e "\n${CYAN}ECS Services:${NC}"
    aws ecs list-services --cluster "$CLUSTER_NAME" --region "$AWS_REGION" \
        --query 'serviceArns' --output table 2>/dev/null || true

    echo -e "\n${CYAN}Running Tasks:${NC}"
    aws ecs list-tasks --cluster "$CLUSTER_NAME" --region "$AWS_REGION" \
        --desired-status RUNNING --query 'taskArns' --output table 2>/dev/null || true

    if [[ "$DRY_RUN" != "true" ]]; then
        echo -e "\n${GREEN}Deployment Configuration:${NC}"
        echo -e "${GREEN}  Account: $AWS_ACCOUNT_ID${NC}"
        echo -e "${GREEN}  Region: $AWS_REGION${NC}"
        echo -e "${GREEN}  Cluster: $CLUSTER_NAME${NC}"
        echo -e "${GREEN}  Registry: $DOCKER_REGISTRY${NC}"
        echo -e "${GREEN}  Tag: $IMAGE_TAG${NC}"
        
        if [[ -n "$RDS_ENDPOINT" ]]; then
            echo -e "${GREEN}  Database: $RDS_ENDPOINT${NC}"
        fi
        
        if [[ -n "$ELASTICACHE_ENDPOINT" ]]; then
            echo -e "${GREEN}  Redis: $ELASTICACHE_ENDPOINT${NC}"
        fi
    fi
}

# Cleanup function for failed deployments
cleanup_on_failure() {
    if [[ "$?" -ne 0 ]]; then
        log_error "Deployment failed!"
        
        if [[ "$FORCE" != "true" ]]; then
            echo -e "${RED}"
            read -p "Do you want to rollback? (yes/no): " rollback
            echo -e "${NC}"
            
            if [[ "$rollback" == "yes" ]]; then
                log_info "Rolling back deployment..."
                
                # Scale services to 0
                aws ecs update-service --cluster "$CLUSTER_NAME" --service "${PROJECT_NAME}-app" --desired-count 0 --region "$AWS_REGION" >/dev/null 2>&1 || true
                aws ecs update-service --cluster "$CLUSTER_NAME" --service "${PROJECT_NAME}-worker" --desired-count 0 --region "$AWS_REGION" >/dev/null 2>&1 || true
                aws ecs update-service --cluster "$CLUSTER_NAME" --service "${PROJECT_NAME}-scheduler" --desired-count 0 --region "$AWS_REGION" >/dev/null 2>&1 || true
                
                log_info "Services scaled to 0. Delete manually if needed."
            fi
        fi
    fi
}

# Main execution flow
main() {
    print_banner
    parse_arguments "$@"
    check_prerequisites
    auto_detect_infrastructure

    # Set trap for cleanup on failure
    trap cleanup_on_failure EXIT

    # Show configuration
    log_info "Deployment Configuration:"
    log_info "  Account: $AWS_ACCOUNT_ID"
    log_info "  Region: $AWS_REGION"
    log_info "  Cluster: $CLUSTER_NAME"
    log_info "  Registry: $DOCKER_REGISTRY"
    log_info "  Tag: $IMAGE_TAG"
    if [[ -n "$VPC_ID" ]]; then
        log_info "  VPC: $VPC_ID"
    fi
    if [[ -n "$SUBNET_IDS" ]]; then
        log_info "  Subnets: $SUBNET_IDS"
    fi
    if [[ -n "$RDS_ENDPOINT" ]]; then
        log_info "  RDS: $RDS_ENDPOINT"
    fi
    if [[ -n "$ELASTICACHE_ENDPOINT" ]]; then
        log_info "  Redis: $ELASTICACHE_ENDPOINT"
    fi
    echo

    # Execute deployment steps
    create_ecr_repositories
    build_and_push_images
    create_secrets
    create_ecs_cluster
    register_task_definitions
    create_ecs_services
    wait_for_services
    run_post_deployment
    show_status

    # Remove trap on successful completion
    trap - EXIT

    echo -e "\n${GREEN}🎉 Production ECS deployment completed successfully!${NC}"
    echo -e "${GREEN}🚀 Check AWS Console for service status and logs${NC}"
}

# Execute main function
main "$@"
