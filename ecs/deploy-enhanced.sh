#!/bin/bash

# Enhanced AI Blockchain Analytics ECS Deployment Script
# Features: RoadRunner optimization, comprehensive monitoring, auto-scaling
# Usage: ./deploy-enhanced.sh [environment] [component] [action]

set -euo pipefail

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_NAME="ai-blockchain-analytics"
AWS_REGION="${AWS_REGION:-us-east-1}"
AWS_ACCOUNT_ID="${AWS_ACCOUNT_ID:-}"
CLUSTER_NAME="${CLUSTER_NAME:-ai-blockchain-cluster}"
ECR_REPOSITORY=""
BUILD_CONTEXT="${BUILD_CONTEXT:-../}"

# Environment-specific configurations
declare -A ENVIRONMENT_CONFIGS=(
    # Development
    [development_cpu]=1024
    [development_memory]=2048
    [development_desired_count]=1
    [development_min_capacity]=1
    [development_max_capacity]=3
    [development_rr_workers]=4
    [development_instance_type]="t3.medium"
    
    # Staging
    [staging_cpu]=2048
    [staging_memory]=4096
    [staging_desired_count]=2
    [staging_min_capacity]=2
    [staging_max_capacity]=10
    [staging_rr_workers]=8
    [staging_instance_type]="t3.large"
    
    # Production
    [production_cpu]=4096
    [production_memory]=8192
    [production_desired_count]=3
    [production_min_capacity]=3
    [production_max_capacity]=50
    [production_rr_workers]=16
    [production_instance_type]="c5.xlarge"
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

log_debug() {
    if [[ "${DEBUG:-false}" == "true" ]]; then
        log_with_timestamp "${PURPLE}[DEBUG]${NC} $1"
    fi
}

log_step() {
    log_with_timestamp "${CYAN}[STEP]${NC} $1"
}

# Progress indicator
show_progress() {
    local pid=$1
    local message=$2
    
    echo -n "$message"
    while kill -0 $pid 2>/dev/null; do
        echo -n "."
        sleep 1
    done
    echo " Done!"
}

# Check if required tools are installed
check_dependencies() {
    log_step "Checking dependencies..."
    
    local deps=("aws" "docker" "jq" "curl")
    local missing_deps=()
    
    for dep in "${deps[@]}"; do
        if ! command -v "$dep" &> /dev/null; then
            missing_deps+=("$dep")
        fi
    done
    
    if [[ ${#missing_deps[@]} -gt 0 ]]; then
        log_error "Missing dependencies: ${missing_deps[*]}"
        log_info "Please install the missing dependencies and try again."
        exit 1
    fi
    
    # Check versions
    log_debug "aws-cli version: $(aws --version)"
    log_debug "docker version: $(docker --version)"
    log_debug "jq version: $(jq --version)"
    
    log_success "All dependencies are installed"
}

# Validate environment
validate_environment() {
    local environment=$1
    
    if [[ ! "$environment" =~ ^(development|staging|production)$ ]]; then
        log_error "Invalid environment: $environment"
        log_info "Valid environments: development, staging, production"
        exit 1
    fi
}

# Check AWS credentials and setup
check_aws_credentials() {
    log_step "Checking AWS credentials and setup..."
    
    if ! aws sts get-caller-identity &> /dev/null; then
        log_error "AWS credentials not configured or invalid"
        log_info "Please configure AWS credentials using 'aws configure' or environment variables"
        exit 1
    fi
    
    # Get AWS account ID if not provided
    if [[ -z "$AWS_ACCOUNT_ID" ]]; then
        AWS_ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
    fi
    
    # Set ECR repository URI
    ECR_REPOSITORY="$AWS_ACCOUNT_ID.dkr.ecr.$AWS_REGION.amazonaws.com/$PROJECT_NAME"
    
    log_success "AWS credentials verified for account: $AWS_ACCOUNT_ID"
    log_info "Using region: $AWS_REGION"
    log_info "ECR repository: $ECR_REPOSITORY"
    
    # Verify required AWS services are available
    log_info "Verifying AWS services availability..."
    
    if ! aws ecs describe-clusters --cluster "$CLUSTER_NAME" --region "$AWS_REGION" &> /dev/null; then
        log_warning "ECS cluster '$CLUSTER_NAME' not found. It will be created during infrastructure deployment."
    fi
    
    # Check if we have necessary permissions
    local required_permissions=(
        "ecs:CreateCluster"
        "ecs:CreateService" 
        "ecs:UpdateService"
        "ecs:RegisterTaskDefinition"
        "ecr:CreateRepository"
        "ecr:GetAuthorizationToken"
        "ssm:GetParameter"
        "ssm:PutParameter"
        "logs:CreateLogGroup"
        "iam:PassRole"
    )
    
    log_info "Checking IAM permissions..."
    for permission in "${required_permissions[@]}"; do
        if ! aws iam simulate-principal-policy \
            --policy-source-arn "$(aws sts get-caller-identity --query Arn --output text)" \
            --action-names "$permission" \
            --resource-arns "*" \
            --query 'EvaluationResults[0].EvalDecision' \
            --output text 2>/dev/null | grep -q "allowed"; then
            log_warning "May not have permission for: $permission"
        fi
    done
}

# Build and push optimized Docker image
build_and_push_image() {
    local environment=$1
    local tag=${2:-latest}
    local context_path=${3:-$BUILD_CONTEXT}
    
    log_step "Building optimized Docker image for $environment environment..."
    
    # Generate build metadata
    local build_id="build-$(date +%Y%m%d%H%M%S)-$(git rev-parse --short HEAD 2>/dev/null || echo 'no-git')"
    local full_tag="$tag-$environment"
    local git_commit=$(git rev-parse HEAD 2>/dev/null || echo 'unknown')
    local git_branch=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo 'unknown')
    
    # Build arguments for optimization
    local build_args=(
        "--target=production"
        "--build-arg=BUILD_ENV=$environment"
        "--build-arg=BUILD_ID=$build_id"
        "--build-arg=NODE_ENV=production"
        "--build-arg=PHP_OPCACHE_VALIDATE_TIMESTAMPS=0"
        "--build-arg=ROADRUNNER_VERSION=2023.3.7"
        "--build-arg=PHP_VERSION=8.3"
        "--platform=linux/amd64"
        "--label=org.opencontainers.image.created=$(date -u +'%Y-%m-%dT%H:%M:%SZ')"
        "--label=org.opencontainers.image.source=https://github.com/your-repo/ai-blockchain-analytics"
        "--label=org.opencontainers.image.version=$tag"
        "--label=org.opencontainers.image.revision=$git_commit"
        "--label=build.environment=$environment"
        "--label=build.id=$build_id"
        "--label=build.branch=$git_branch"
    )
    
    # Use BuildKit for improved performance
    export DOCKER_BUILDKIT=1
    export BUILDKIT_PROGRESS=plain
    
    log_info "Building Docker image with metadata..."
    log_debug "Build ID: $build_id"
    log_debug "Git commit: $git_commit"
    log_debug "Git branch: $git_branch"
    
    # Build with cache optimization
    docker build \
        "${build_args[@]}" \
        --cache-from="$ECR_REPOSITORY:cache-$environment" \
        --tag="$PROJECT_NAME:$full_tag" \
        --tag="$ECR_REPOSITORY:$full_tag" \
        --tag="$ECR_REPOSITORY:latest-$environment" \
        "$context_path"
    
    log_success "Docker image built successfully: $PROJECT_NAME:$full_tag"
    
    # Login to ECR
    log_info "Authenticating with Amazon ECR..."
    aws ecr get-login-password --region "$AWS_REGION" | \
        docker login --username AWS --password-stdin "$ECR_REPOSITORY"
    
    # Create ECR repository if it doesn't exist
    if ! aws ecr describe-repositories --repository-names "$PROJECT_NAME" --region "$AWS_REGION" &> /dev/null; then
        log_info "Creating ECR repository..."
        aws ecr create-repository \
            --repository-name "$PROJECT_NAME" \
            --region "$AWS_REGION" \
            --image-scanning-configuration scanOnPush=true \
            --encryption-configuration encryptionType=AES256 \
            --tags Key=Project,Value=ai-blockchain-analytics Key=Environment,Value="$environment"
        
        # Set lifecycle policy
        aws ecr put-lifecycle-policy \
            --repository-name "$PROJECT_NAME" \
            --region "$AWS_REGION" \
            --lifecycle-policy-text '{
                "rules": [
                    {
                        "rulePriority": 1,
                        "description": "Keep last 10 production images",
                        "selection": {
                            "tagStatus": "tagged",
                            "tagPrefixList": ["latest-production"],
                            "countType": "imageCountMoreThan",
                            "countNumber": 10
                        },
                        "action": {
                            "type": "expire"
                        }
                    },
                    {
                        "rulePriority": 2,
                        "description": "Keep last 5 staging images",
                        "selection": {
                            "tagStatus": "tagged",
                            "tagPrefixList": ["latest-staging"],
                            "countType": "imageCountMoreThan",
                            "countNumber": 5
                        },
                        "action": {
                            "type": "expire"
                        }
                    },
                    {
                        "rulePriority": 3,
                        "description": "Expire untagged images older than 1 day",
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
            }'
    fi
    
    # Push images
    log_info "Pushing images to ECR..."
    docker push "$ECR_REPOSITORY:$full_tag"
    docker push "$ECR_REPOSITORY:latest-$environment"
    
    # Push cache layer
    docker tag "$PROJECT_NAME:$full_tag" "$ECR_REPOSITORY:cache-$environment"
    docker push "$ECR_REPOSITORY:cache-$environment" || log_warning "Failed to push cache layer"
    
    # Scan image for vulnerabilities
    log_info "Starting ECR image scan..."
    aws ecr start-image-scan \
        --repository-name "$PROJECT_NAME" \
        --image-id imageTag="$full_tag" \
        --region "$AWS_REGION" || log_warning "Failed to start image scan"
    
    log_success "Images pushed to ECR successfully"
    
    # Return the full image URI
    echo "$ECR_REPOSITORY:$full_tag"
}

# Setup and validate SSM parameters
setup_ssm_parameters() {
    local environment=$1
    
    log_step "Setting up SSM parameters for $environment environment..."
    
    # Define parameter structure with environment prefix
    local param_prefix="/ai-blockchain/$environment"
    
    declare -A parameters=(
        ["app-key"]="${APP_KEY:-base64:$(openssl rand -base64 32)}"
        ["db-host"]="${DB_HOST:-ai-blockchain-postgres.$environment.amazonaws.com}"
        ["db-port"]="${DB_PORT:-5432}"
        ["db-database"]="${DB_DATABASE:-ai_blockchain_$environment}"
        ["db-username"]="${DB_USERNAME:-ai_blockchain_user}"
        ["db-password"]="${DB_PASSWORD:-$(openssl rand -base64 32)}"
        ["redis-host"]="${REDIS_HOST:-ai-blockchain-redis.$environment.amazonaws.com}"
        ["redis-port"]="${REDIS_PORT:-6379}"
        ["redis-password"]="${REDIS_PASSWORD:-$(openssl rand -base64 32)}"
        ["stripe-secret"]="${STRIPE_SECRET:-sk_test_placeholder}"
        ["sentry-dsn"]="${SENTRY_DSN:-}"
        ["google-credentials"]="${GOOGLE_CREDENTIALS:-{}}"
        ["jwt-secret"]="${JWT_SECRET:-$(openssl rand -base64 64)}"
        ["openai-api-key"]="${OPENAI_API_KEY:-sk-placeholder}"
        ["coingecko-api-key"]="${COINGECKO_API_KEY:-placeholder}"
    )
    
    # Create or update parameters
    for param_name in "${!parameters[@]}"; do
        local param_path="$param_prefix/$param_name"
        local param_value="${parameters[$param_name]}"
        
        if aws ssm get-parameter --name "$param_path" --region "$AWS_REGION" &> /dev/null; then
            log_info "Parameter $param_path already exists"
            
            # Update parameter if value is different (for non-secret parameters)
            if [[ ! "$param_name" =~ (password|secret|key|credentials) ]]; then
                current_value=$(aws ssm get-parameter --name "$param_path" --region "$AWS_REGION" --query 'Parameter.Value' --output text)
                if [[ "$current_value" != "$param_value" ]]; then
                    log_info "Updating parameter $param_path"
                    aws ssm put-parameter \
                        --name "$param_path" \
                        --value "$param_value" \
                        --type "String" \
                        --overwrite \
                        --region "$AWS_REGION" \
                        --tags Key=Project,Value=ai-blockchain-analytics Key=Environment,Value="$environment"
                fi
            fi
        else
            log_info "Creating parameter $param_path"
            
            # Determine parameter type
            local param_type="String"
            if [[ "$param_name" =~ (password|secret|key) ]]; then
                param_type="SecureString"
            fi
            
            aws ssm put-parameter \
                --name "$param_path" \
                --value "$param_value" \
                --type "$param_type" \
                --region "$AWS_REGION" \
                --tags Key=Project,Value=ai-blockchain-analytics Key=Environment,Value="$environment"
        fi
    done
    
    log_success "SSM parameters setup completed"
}

# Deploy infrastructure using CloudFormation/Terraform
deploy_infrastructure() {
    local environment=$1
    
    log_step "Deploying infrastructure for $environment environment..."
    
    # Check if Terraform configuration exists and is preferred
    if [[ -d "$SCRIPT_DIR/terraform" ]]; then
        deploy_terraform_infrastructure "$environment"
    else
        deploy_cloudformation_infrastructure "$environment"
    fi
}

# Deploy infrastructure using Terraform
deploy_terraform_infrastructure() {
    local environment=$1
    
    log_info "Using Terraform for infrastructure deployment..."
    
    cd "$SCRIPT_DIR/terraform"
    
    # Initialize Terraform
    terraform init -upgrade
    
    # Select or create workspace
    if terraform workspace list | grep -q "$environment"; then
        terraform workspace select "$environment"
    else
        terraform workspace new "$environment"
    fi
    
    # Plan deployment
    local plan_file="$environment.tfplan"
    terraform plan \
        -var-file="environments/$environment.tfvars" \
        -var="aws_region=$AWS_REGION" \
        -var="aws_account_id=$AWS_ACCOUNT_ID" \
        -var="project_name=$PROJECT_NAME" \
        -out="$plan_file"
    
    # Ask for confirmation in interactive mode
    if [[ -t 0 ]]; then
        echo
        read -p "Apply Terraform plan? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            log_info "Infrastructure deployment cancelled"
            cd - > /dev/null
            return 0
        fi
    fi
    
    # Apply changes
    terraform apply "$plan_file"
    
    # Output important values
    log_info "Infrastructure deployment completed. Key outputs:"
    terraform output
    
    cd - > /dev/null
    log_success "Terraform infrastructure deployment completed"
}

# Deploy infrastructure using CloudFormation
deploy_cloudformation_infrastructure() {
    local environment=$1
    
    log_info "Using CloudFormation for infrastructure deployment..."
    
    local stack_name="ai-blockchain-$environment"
    local template_file="$SCRIPT_DIR/enhanced-cloudformation.yaml"
    
    if [[ ! -f "$template_file" ]]; then
        template_file="$SCRIPT_DIR/cloudformation-template.yaml"
    fi
    
    # Check if stack exists
    if aws cloudformation describe-stacks --stack-name "$stack_name" --region "$AWS_REGION" &> /dev/null; then
        log_info "Updating existing CloudFormation stack: $stack_name"
        
        aws cloudformation update-stack \
            --stack-name "$stack_name" \
            --template-body "file://$template_file" \
            --parameters \
                ParameterKey=Environment,ParameterValue="$environment" \
                ParameterKey=ProjectName,ParameterValue="$PROJECT_NAME" \
                ParameterKey=DesiredCount,ParameterValue="${ENVIRONMENT_CONFIGS[${environment}_desired_count]}" \
                ParameterKey=MinCapacity,ParameterValue="${ENVIRONMENT_CONFIGS[${environment}_min_capacity]}" \
                ParameterKey=MaxCapacity,ParameterValue="${ENVIRONMENT_CONFIGS[${environment}_max_capacity]}" \
                ParameterKey=InstanceType,ParameterValue="${ENVIRONMENT_CONFIGS[${environment}_instance_type]}" \
            --capabilities CAPABILITY_IAM CAPABILITY_NAMED_IAM \
            --region "$AWS_REGION" \
            --tags Key=Project,Value=ai-blockchain-analytics Key=Environment,Value="$environment"
        
        log_info "Waiting for stack update to complete..."
        aws cloudformation wait stack-update-complete \
            --stack-name "$stack_name" \
            --region "$AWS_REGION"
    else
        log_info "Creating new CloudFormation stack: $stack_name"
        
        aws cloudformation create-stack \
            --stack-name "$stack_name" \
            --template-body "file://$template_file" \
            --parameters \
                ParameterKey=Environment,ParameterValue="$environment" \
                ParameterKey=ProjectName,ParameterValue="$PROJECT_NAME" \
                ParameterKey=DesiredCount,ParameterValue="${ENVIRONMENT_CONFIGS[${environment}_desired_count]}" \
                ParameterKey=MinCapacity,ParameterValue="${ENVIRONMENT_CONFIGS[${environment}_min_capacity]}" \
                ParameterKey=MaxCapacity,ParameterValue="${ENVIRONMENT_CONFIGS[${environment}_max_capacity]}" \
                ParameterKey=InstanceType,ParameterValue="${ENVIRONMENT_CONFIGS[${environment}_instance_type]}" \
            --capabilities CAPABILITY_IAM CAPABILITY_NAMED_IAM \
            --region "$AWS_REGION" \
            --tags Key=Project,Value=ai-blockchain-analytics Key=Environment,Value="$environment"
        
        log_info "Waiting for stack creation to complete..."
        aws cloudformation wait stack-create-complete \
            --stack-name "$stack_name" \
            --region "$AWS_REGION"
    fi
    
    # Get stack outputs
    log_info "CloudFormation stack outputs:"
    aws cloudformation describe-stacks \
        --stack-name "$stack_name" \
        --region "$AWS_REGION" \
        --query 'Stacks[0].Outputs' \
        --output table
    
    log_success "CloudFormation infrastructure deployment completed"
}

# Register optimized ECS task definition
register_task_definition() {
    local component=$1
    local image_uri=$2
    local environment=$3
    
    log_step "Registering ECS task definition for $component in $environment..."
    
    local task_def_file="$SCRIPT_DIR/task-definitions/$component.json"
    
    # Use optimized version if available
    if [[ -f "$SCRIPT_DIR/task-definitions/${component}-optimized.json" ]]; then
        task_def_file="$SCRIPT_DIR/task-definitions/${component}-optimized.json"
    fi
    
    if [[ ! -f "$task_def_file" ]]; then
        log_error "Task definition file not found: $task_def_file"
        exit 1
    fi
    
    # Get environment-specific configurations
    local cpu="${ENVIRONMENT_CONFIGS[${environment}_cpu]}"
    local memory="${ENVIRONMENT_CONFIGS[${environment}_memory]}"
    local rr_workers="${ENVIRONMENT_CONFIGS[${environment}_rr_workers]}"
    
    # Create temporary file with replacements
    local temp_file
    temp_file=$(mktemp)
    
    # Replace placeholders in task definition
    sed \
        -e "s|YOUR_ACCOUNT_ID|$AWS_ACCOUNT_ID|g" \
        -e "s|YOUR_REGION|$AWS_REGION|g" \
        -e "s|YOUR_ACCOUNT_ID.dkr.ecr.YOUR_REGION.amazonaws.com/ai-blockchain-analytics:latest|$image_uri|g" \
        -e "s|\"cpu\": \"2048\"|\"cpu\": \"$cpu\"|g" \
        -e "s|\"memory\": \"4096\"|\"memory\": \"$memory\"|g" \
        -e "s|\"RR_WORKERS\", \"value\": \"16\"|\"RR_WORKERS\", \"value\": \"$rr_workers\"|g" \
        -e "s|production|$environment|g" \
        "$task_def_file" > "$temp_file"
    
    # Update SSM parameter references for environment
    jq --arg env "$environment" '
        .containerDefinitions[0].secrets = [
            .containerDefinitions[0].secrets[] | 
            .valueFrom |= sub("/ai-blockchain/"; "/ai-blockchain/" + $env + "/")
        ]
    ' "$temp_file" > "${temp_file}.json" && mv "${temp_file}.json" "$temp_file"
    
    # Validate task definition JSON
    if ! jq . "$temp_file" > /dev/null; then
        log_error "Invalid JSON in processed task definition"
        cat "$temp_file"
        rm "$temp_file"
        exit 1
    fi
    
    # Register task definition
    local task_def_arn
    task_def_arn=$(aws ecs register-task-definition \
        --cli-input-json "file://$temp_file" \
        --region "$AWS_REGION" \
        --query 'taskDefinition.taskDefinitionArn' \
        --output text)
    
    rm "$temp_file"
    
    if [[ -z "$task_def_arn" ]]; then
        log_error "Failed to register task definition"
        exit 1
    fi
    
    log_success "Task definition registered: $task_def_arn"
    echo "$task_def_arn"
}

# Create or update ECS service with auto-scaling
create_or_update_ecs_service() {
    local service_name=$1
    local task_def_arn=$2
    local environment=$3
    local cluster_name=${4:-$CLUSTER_NAME}
    
    log_step "Creating or updating ECS service: $service_name..."
    
    # Get environment-specific configurations
    local desired_count="${ENVIRONMENT_CONFIGS[${environment}_desired_count]}"
    local min_capacity="${ENVIRONMENT_CONFIGS[${environment}_min_capacity]}"
    local max_capacity="${ENVIRONMENT_CONFIGS[${environment}_max_capacity]}"
    
    # Check if service exists
    if aws ecs describe-services \
        --cluster "$cluster_name" \
        --services "$service_name" \
        --region "$AWS_REGION" \
        --query 'services[0].serviceName' \
        --output text 2>/dev/null | grep -q "$service_name"; then
        
        log_info "Updating existing ECS service: $service_name"
        
        # Update service
        aws ecs update-service \
            --cluster "$cluster_name" \
            --service "$service_name" \
            --task-definition "$task_def_arn" \
            --desired-count "$desired_count" \
            --region "$AWS_REGION" \
            --no-cli-pager
    else
        log_info "Creating new ECS service: $service_name"
        
        # Get subnet and security group IDs from CloudFormation stack
        local stack_name="ai-blockchain-$environment"
        local subnet_ids security_group_id target_group_arn
        
        subnet_ids=$(aws cloudformation describe-stacks \
            --stack-name "$stack_name" \
            --region "$AWS_REGION" \
            --query 'Stacks[0].Outputs[?OutputKey==`PrivateSubnetIds`].OutputValue' \
            --output text 2>/dev/null || echo "")
        
        security_group_id=$(aws cloudformation describe-stacks \
            --stack-name "$stack_name" \
            --region "$AWS_REGION" \
            --query 'Stacks[0].Outputs[?OutputKey==`EcsSecurityGroupId`].OutputValue' \
            --output text 2>/dev/null || echo "")
        
        target_group_arn=$(aws cloudformation describe-stacks \
            --stack-name "$stack_name" \
            --region "$AWS_REGION" \
            --query 'Stacks[0].Outputs[?OutputKey==`TargetGroupArn`].OutputValue' \
            --output text 2>/dev/null || echo "")
        
        # Create service configuration
        local service_config='{
            "serviceName": "'$service_name'",
            "cluster": "'$cluster_name'",
            "taskDefinition": "'$task_def_arn'",
            "desiredCount": '$desired_count',
            "launchType": "FARGATE",
            "platformVersion": "LATEST",
            "networkConfiguration": {
                "awsvpcConfiguration": {
                    "subnets": ['$(echo "$subnet_ids" | sed 's/,/","/g' | sed 's/^/"/' | sed 's/$/"/')'],
                    "securityGroups": ["'$security_group_id'"],
                    "assignPublicIp": "DISABLED"
                }
            },
            "deploymentConfiguration": {
                "maximumPercent": 200,
                "minimumHealthyPercent": 50,
                "deploymentCircuitBreaker": {
                    "enable": true,
                    "rollback": true
                }
            },
            "healthCheckGracePeriodSeconds": 300,
            "enableExecuteCommand": true,
            "propagateTags": "SERVICE",
            "tags": [
                {
                    "key": "Project",
                    "value": "ai-blockchain-analytics"
                },
                {
                    "key": "Environment",
                    "value": "'$environment'"
                },
                {
                    "key": "Component",
                    "value": "'$service_name'"
                }
            ]
        }'
        
        # Add load balancer configuration if target group exists
        if [[ -n "$target_group_arn" && "$service_name" == *"app"* ]]; then
            service_config=$(echo "$service_config" | jq '.loadBalancers = [{
                "targetGroupArn": "'$target_group_arn'",
                "containerName": "roadrunner-app",
                "containerPort": 8000
            }]')
        fi
        
        # Create service
        aws ecs create-service \
            --cli-input-json "$service_config" \
            --region "$AWS_REGION" \
            --no-cli-pager
    fi
    
    # Wait for service to stabilize
    log_info "Waiting for service to stabilize..."
    aws ecs wait services-stable \
        --cluster "$cluster_name" \
        --services "$service_name" \
        --region "$AWS_REGION"
    
    # Setup auto-scaling
    setup_service_autoscaling "$service_name" "$cluster_name" "$environment"
    
    log_success "Service $service_name created/updated successfully"
}

# Setup ECS service auto-scaling
setup_service_autoscaling() {
    local service_name=$1
    local cluster_name=$2
    local environment=$3
    
    log_info "Setting up auto-scaling for service: $service_name"
    
    local min_capacity="${ENVIRONMENT_CONFIGS[${environment}_min_capacity]}"
    local max_capacity="${ENVIRONMENT_CONFIGS[${environment}_max_capacity]}"
    local resource_id="service/$cluster_name/$service_name"
    
    # Register scalable target
    aws application-autoscaling register-scalable-target \
        --service-namespace ecs \
        --resource-id "$resource_id" \
        --scalable-dimension ecs:service:DesiredCount \
        --min-capacity "$min_capacity" \
        --max-capacity "$max_capacity" \
        --region "$AWS_REGION" \
        --role-arn "arn:aws:iam::$AWS_ACCOUNT_ID:role/application-autoscaling-ecs-service" || log_warning "Failed to register scalable target"
    
    # Create scaling policies
    local scale_up_policy_arn scale_down_policy_arn
    
    # Scale up policy
    scale_up_policy_arn=$(aws application-autoscaling put-scaling-policy \
        --service-namespace ecs \
        --resource-id "$resource_id" \
        --scalable-dimension ecs:service:DesiredCount \
        --policy-name "$service_name-scale-up" \
        --policy-type StepScaling \
        --step-scaling-policy-configuration '{
            "AdjustmentType": "ChangeInCapacity",
            "Cooldown": 300,
            "MetricAggregationType": "Average",
            "StepAdjustments": [
                {
                    "MetricIntervalLowerBound": 0,
                    "ScalingAdjustment": 2
                }
            ]
        }' \
        --region "$AWS_REGION" \
        --query 'PolicyARN' \
        --output text 2>/dev/null || echo "")
    
    # Scale down policy
    scale_down_policy_arn=$(aws application-autoscaling put-scaling-policy \
        --service-namespace ecs \
        --resource-id "$resource_id" \
        --scalable-dimension ecs:service:DesiredCount \
        --policy-name "$service_name-scale-down" \
        --policy-type StepScaling \
        --step-scaling-policy-configuration '{
            "AdjustmentType": "ChangeInCapacity",
            "Cooldown": 300,
            "MetricAggregationType": "Average",
            "StepAdjustments": [
                {
                    "MetricIntervalUpperBound": 0,
                    "ScalingAdjustment": -1
                }
            ]
        }' \
        --region "$AWS_REGION" \
        --query 'PolicyARN' \
        --output text 2>/dev/null || echo "")
    
    # Create CloudWatch alarms
    if [[ -n "$scale_up_policy_arn" ]]; then
        aws cloudwatch put-metric-alarm \
            --alarm-name "$service_name-cpu-high-$environment" \
            --alarm-description "Scale up when CPU is high" \
            --metric-name CPUUtilization \
            --namespace AWS/ECS \
            --statistic Average \
            --period 300 \
            --threshold 70 \
            --comparison-operator GreaterThanThreshold \
            --evaluation-periods 2 \
            --alarm-actions "$scale_up_policy_arn" \
            --dimensions Name=ServiceName,Value="$service_name" Name=ClusterName,Value="$cluster_name" \
            --region "$AWS_REGION" || log_warning "Failed to create scale-up alarm"
    fi
    
    if [[ -n "$scale_down_policy_arn" ]]; then
        aws cloudwatch put-metric-alarm \
            --alarm-name "$service_name-cpu-low-$environment" \
            --alarm-description "Scale down when CPU is low" \
            --metric-name CPUUtilization \
            --namespace AWS/ECS \
            --statistic Average \
            --period 300 \
            --threshold 30 \
            --comparison-operator LessThanThreshold \
            --evaluation-periods 5 \
            --alarm-actions "$scale_down_policy_arn" \
            --dimensions Name=ServiceName,Value="$service_name" Name=ClusterName,Value="$cluster_name" \
            --region "$AWS_REGION" || log_warning "Failed to create scale-down alarm"
    fi
    
    log_success "Auto-scaling setup completed for $service_name"
}

# Run database migrations with enhanced error handling
run_migrations() {
    local cluster_name=$1
    local task_def_arn=$2
    local environment=$3
    
    log_step "Running database migrations for $environment environment..."
    
    # Get network configuration from CloudFormation
    local stack_name="ai-blockchain-$environment"
    local subnet_id security_group_id
    
    subnet_id=$(aws cloudformation describe-stacks \
        --stack-name "$stack_name" \
        --region "$AWS_REGION" \
        --query 'Stacks[0].Outputs[?OutputKey==`PrivateSubnetIds`].OutputValue' \
        --output text 2>/dev/null | cut -d',' -f1)
    
    security_group_id=$(aws cloudformation describe-stacks \
        --stack-name "$stack_name" \
        --region "$AWS_REGION" \
        --query 'Stacks[0].Outputs[?OutputKey==`EcsSecurityGroupId`].OutputValue' \
        --output text 2>/dev/null)
    
    if [[ -z "$subnet_id" || -z "$security_group_id" ]]; then
        log_warning "Could not retrieve network configuration from CloudFormation. Using default values."
        subnet_id=$(aws ec2 describe-subnets \
            --filters "Name=tag:Name,Values=*private*" \
            --region "$AWS_REGION" \
            --query 'Subnets[0].SubnetId' \
            --output text 2>/dev/null || echo "")
        
        security_group_id=$(aws ec2 describe-security-groups \
            --filters "Name=group-name,Values=*ecs*" \
            --region "$AWS_REGION" \
            --query 'SecurityGroups[0].GroupId' \
            --output text 2>/dev/null || echo "")
    fi
    
    # Run migration task
    local task_arn
    task_arn=$(aws ecs run-task \
        --cluster "$cluster_name" \
        --task-definition "$task_def_arn" \
        --launch-type FARGATE \
        --network-configuration "awsvpcConfiguration={subnets=[$subnet_id],securityGroups=[$security_group_id],assignPublicIp=DISABLED}" \
        --overrides '{
            "containerOverrides": [{
                "name": "roadrunner-app",
                "command": ["/bin/sh", "-c", "
                    set -e
                    echo \"Waiting for database connection...\"
                    until php artisan migrate:status --env='$environment'; do
                        echo \"Database not ready, waiting 10 seconds...\"
                        sleep 10
                    done
                    echo \"Running migrations...\"
                    php artisan migrate --force --env='$environment'
                    if [[ \"'$environment'\" != \"production\" ]]; then
                        echo \"Running seeders for '$environment'...\"
                        php artisan db:seed --force --env='$environment'
                    fi
                    echo \"Migration completed successfully\"
                "]
            }]
        }' \
        --region "$AWS_REGION" \
        --query 'tasks[0].taskArn' \
        --output text)
    
    if [[ -z "$task_arn" ]]; then
        log_error "Failed to start migration task"
        exit 1
    fi
    
    log_info "Migration task started: $task_arn"
    
    # Wait for task to complete with detailed monitoring
    local start_time
    start_time=$(date +%s)
    
    while true; do
        local task_status
        task_status=$(aws ecs describe-tasks \
            --cluster "$cluster_name" \
            --tasks "$task_arn" \
            --region "$AWS_REGION" \
            --query 'tasks[0].lastStatus' \
            --output text)
        
        if [[ "$task_status" == "STOPPED" ]]; then
            break
        fi
        
        local current_time
        current_time=$(date +%s)
        local elapsed=$((current_time - start_time))
        
        if [[ $elapsed -gt 1200 ]]; then  # 20 minutes timeout
            log_error "Migration task timed out after 20 minutes"
            aws ecs stop-task \
                --cluster "$cluster_name" \
                --task "$task_arn" \
                --reason "Timeout" \
                --region "$AWS_REGION"
            exit 1
        fi
        
        log_info "Migration task running... Status: $task_status (${elapsed}s elapsed)"
        sleep 15
    done
    
    # Check exit code
    local exit_code
    exit_code=$(aws ecs describe-tasks \
        --cluster "$cluster_name" \
        --tasks "$task_arn" \
        --region "$AWS_REGION" \
        --query 'tasks[0].containers[0].exitCode' \
        --output text)
    
    # Show logs
    log_info "Migration task logs:"
    local log_group="/ecs/ai-blockchain-analytics"
    local log_stream
    log_stream=$(aws ecs describe-tasks \
        --cluster "$cluster_name" \
        --tasks "$task_arn" \
        --region "$AWS_REGION" \
        --query 'tasks[0].containers[0].taskArn' \
        --output text | sed 's|.*/||')
    
    aws logs get-log-events \
        --log-group-name "$log_group" \
        --log-stream-name "roadrunner-app/$log_stream" \
        --region "$AWS_REGION" \
        --query 'events[].message' \
        --output text 2>/dev/null || log_warning "Could not retrieve migration logs"
    
    if [[ "$exit_code" != "0" ]]; then
        log_error "Migration failed with exit code: $exit_code"
        exit 1
    fi
    
    log_success "Database migrations completed successfully"
}

# Comprehensive health check
perform_health_check() {
    local environment=$1
    local cluster_name=${2:-$CLUSTER_NAME}
    
    log_step "Performing comprehensive health check for $environment environment..."
    
    # Check ECS cluster status
    local cluster_status
    cluster_status=$(aws ecs describe-clusters \
        --clusters "$cluster_name" \
        --region "$AWS_REGION" \
        --query 'clusters[0].status' \
        --output text 2>/dev/null || echo "NOT_FOUND")
    
    if [[ "$cluster_status" == "ACTIVE" ]]; then
        log_success "ECS cluster '$cluster_name' is active"
    else
        log_error "ECS cluster '$cluster_name' is not active: $cluster_status"
        return 1
    fi
    
    # Check services
    local services=("roadrunner-app" "horizon-worker")
    
    for service in "${services[@]}"; do
        local service_status running_count desired_count
        
        service_status=$(aws ecs describe-services \
            --cluster "$cluster_name" \
            --services "$service" \
            --region "$AWS_REGION" \
            --query 'services[0].status' \
            --output text 2>/dev/null || echo "NOT_FOUND")
        
        if [[ "$service_status" == "ACTIVE" ]]; then
            running_count=$(aws ecs describe-services \
                --cluster "$cluster_name" \
                --services "$service" \
                --region "$AWS_REGION" \
                --query 'services[0].runningCount' \
                --output text)
            
            desired_count=$(aws ecs describe-services \
                --cluster "$cluster_name" \
                --services "$service" \
                --region "$AWS_REGION" \
                --query 'services[0].desiredCount' \
                --output text)
            
            if [[ "$running_count" == "$desired_count" ]]; then
                log_success "$service: $running_count/$desired_count tasks running"
            else
                log_warning "$service: $running_count/$desired_count tasks running"
            fi
        else
            log_warning "$service: Service not active or not found"
        fi
    done
    
    # Check RDS instance
    local db_instance_id="ai-blockchain-$environment"
    local db_status
    
    db_status=$(aws rds describe-db-instances \
        --db-instance-identifier "$db_instance_id" \
        --region "$AWS_REGION" \
        --query 'DBInstances[0].DBInstanceStatus' \
        --output text 2>/dev/null || echo "NOT_FOUND")
    
    if [[ "$db_status" == "available" ]]; then
        log_success "RDS instance '$db_instance_id' is available"
    else
        log_warning "RDS instance '$db_instance_id' status: $db_status"
    fi
    
    # Check ElastiCache cluster
    local redis_cluster_id="ai-blockchain-redis-$environment"
    local redis_status
    
    redis_status=$(aws elasticache describe-cache-clusters \
        --cache-cluster-id "$redis_cluster_id" \
        --region "$AWS_REGION" \
        --query 'CacheClusters[0].CacheClusterStatus' \
        --output text 2>/dev/null || echo "NOT_FOUND")
    
    if [[ "$redis_status" == "available" ]]; then
        log_success "ElastiCache cluster '$redis_cluster_id' is available"
    else
        log_warning "ElastiCache cluster '$redis_cluster_id' status: $redis_status"
    fi
    
    # Test application endpoint if ALB is available
    local alb_dns
    alb_dns=$(aws elbv2 describe-load-balancers \
        --names "ai-blockchain-alb-$environment" \
        --region "$AWS_REGION" \
        --query 'LoadBalancers[0].DNSName' \
        --output text 2>/dev/null || echo "")
    
    if [[ -n "$alb_dns" && "$alb_dns" != "None" ]]; then
        log_info "Testing application health endpoint..."
        if curl -s -f -m 10 "http://$alb_dns/health" > /dev/null; then
            log_success "Application health check passed"
        else
            log_warning "Application health check failed or endpoint not ready"
        fi
    else
        log_info "Load balancer not found or not ready"
    fi
    
    log_success "Health check completed"
}

# Show comprehensive deployment status
show_deployment_status() {
    local environment=$1
    local cluster_name=${2:-$CLUSTER_NAME}
    
    log_step "Deployment Status Summary for $environment environment:"
    echo ""
    
    # ECS Cluster information
    log_info "ECS Cluster: $cluster_name"
    aws ecs describe-clusters \
        --clusters "$cluster_name" \
        --include CONFIGURATIONS,TAGS \
        --region "$AWS_REGION" \
        --output table \
        --query 'clusters[0].{Name:clusterName,Status:status,Tasks:runningTasksCount,Services:activeServicesCount}' 2>/dev/null || log_warning "Could not retrieve cluster information"
    echo ""
    
    # Services status
    log_info "ECS Services:"
    aws ecs list-services \
        --cluster "$cluster_name" \
        --region "$AWS_REGION" \
        --query 'serviceArns' \
        --output text | tr '\t' '\n' | while read -r service_arn; do
        if [[ -n "$service_arn" ]]; then
            local service_name
            service_name=$(basename "$service_arn")
            aws ecs describe-services \
                --cluster "$cluster_name" \
                --services "$service_name" \
                --region "$AWS_REGION" \
                --query 'services[0].{Name:serviceName,Status:status,Running:runningCount,Desired:desiredCount,TaskDefinition:taskDefinition}' \
                --output table 2>/dev/null
        fi
    done
    echo ""
    
    # Task information
    log_info "Running Tasks:"
    aws ecs list-tasks \
        --cluster "$cluster_name" \
        --region "$AWS_REGION" \
        --query 'taskArns' \
        --output text | tr '\t' '\n' | head -10 | while read -r task_arn; do
        if [[ -n "$task_arn" ]]; then
            aws ecs describe-tasks \
                --cluster "$cluster_name" \
                --tasks "$task_arn" \
                --region "$AWS_REGION" \
                --query 'tasks[0].{TaskArn:taskArn,Status:lastStatus,HealthStatus:healthStatus,CreatedAt:createdAt}' \
                --output table 2>/dev/null
        fi
    done
    echo ""
    
    # Load Balancer information
    log_info "Load Balancer:"
    aws elbv2 describe-load-balancers \
        --names "ai-blockchain-alb-$environment" \
        --region "$AWS_REGION" \
        --query 'LoadBalancers[0].{Name:LoadBalancerName,DNS:DNSName,State:State.Code,Type:Type}' \
        --output table 2>/dev/null || log_info "Load balancer not found"
    echo ""
    
    # Auto Scaling information
    log_info "Auto Scaling Targets:"
    aws application-autoscaling describe-scalable-targets \
        --service-namespace ecs \
        --region "$AWS_REGION" \
        --query 'ScalableTargets[?contains(ResourceId,`'$cluster_name'`)].{ResourceId:ResourceId,MinCapacity:MinCapacity,MaxCapacity:MaxCapacity,DesiredCapacity:DesiredCapacity}' \
        --output table 2>/dev/null || log_info "No auto scaling targets found"
    echo ""
    
    # CloudWatch Alarms
    log_info "CloudWatch Alarms:"
    aws cloudwatch describe-alarms \
        --alarm-name-prefix "$cluster_name" \
        --region "$AWS_REGION" \
        --query 'MetricAlarms[].{Name:AlarmName,State:StateValue,Reason:StateReason}' \
        --output table 2>/dev/null || log_info "No alarms found"
    echo ""
    
    # Application URL
    local alb_dns
    alb_dns=$(aws elbv2 describe-load-balancers \
        --names "ai-blockchain-alb-$environment" \
        --region "$AWS_REGION" \
        --query 'LoadBalancers[0].DNSName' \
        --output text 2>/dev/null || echo "")
    
    if [[ -n "$alb_dns" && "$alb_dns" != "None" ]]; then
        log_success "Application URL: https://$alb_dns"
    else
        log_info "Application URL not yet available"
    fi
}

# Main deployment function
deploy() {
    local environment=${1:-production}
    local component=${2:-all}
    local action=${3:-deploy}
    local image_tag=${4:-$(date +%Y%m%d%H%M%S)}
    
    log_step "Starting enhanced ECS deployment..."
    log_info "Environment: $environment"
    log_info "Component: $component"
    log_info "Action: $action"
    log_info "Image tag: $image_tag"
    log_info "AWS Region: $AWS_REGION"
    log_info "ECS Cluster: $CLUSTER_NAME"
    
    # Validate inputs
    validate_environment "$environment"
    check_dependencies
    check_aws_credentials
    
    case $action in
        "deploy")
            # Setup SSM parameters
            setup_ssm_parameters "$environment"
            
            # Deploy infrastructure if requested
            if [[ "$component" == "all" || "$component" == "infrastructure" ]]; then
                deploy_infrastructure "$environment"
            fi
            
            # Build and push image for app components
            local image_uri=""
            if [[ "$component" == "all" || "$component" == "app" || "$component" == "worker" || "$component" == "roadrunner" || "$component" == "horizon" ]]; then
                image_uri=$(build_and_push_image "$environment" "$image_tag")
            fi
            
            # Deploy application components
            if [[ "$component" == "all" || "$component" == "app" || "$component" == "roadrunner" ]]; then
                # Register and deploy app
                local app_task_def_arn
                app_task_def_arn=$(register_task_definition "roadrunner-app-optimized" "$image_uri" "$environment")
                
                # Run migrations
                run_migrations "$CLUSTER_NAME" "$app_task_def_arn" "$environment"
                
                # Create/update service
                create_or_update_ecs_service "roadrunner-app" "$app_task_def_arn" "$environment"
            fi
            
            if [[ "$component" == "all" || "$component" == "worker" || "$component" == "horizon" ]]; then
                # Register and deploy worker
                local worker_task_def_arn
                worker_task_def_arn=$(register_task_definition "horizon-worker" "$image_uri" "$environment")
                create_or_update_ecs_service "horizon-worker" "$worker_task_def_arn" "$environment"
            fi
            
            # Perform health check
            perform_health_check "$environment"
            
            log_success "Deployment completed successfully!"
            ;;
            
        "rollback")
            # Implement rollback logic
            log_info "Rolling back $component in $environment..."
            
            # Get previous task definition
            local previous_task_def
            previous_task_def=$(aws ecs describe-services \
                --cluster "$CLUSTER_NAME" \
                --services "$component" \
                --region "$AWS_REGION" \
                --query 'services[0].deployments[1].taskDefinition' \
                --output text)
            
            if [[ -n "$previous_task_def" && "$previous_task_def" != "None" ]]; then
                aws ecs update-service \
                    --cluster "$CLUSTER_NAME" \
                    --service "$component" \
                    --task-definition "$previous_task_def" \
                    --region "$AWS_REGION" \
                    --no-cli-pager
                
                aws ecs wait services-stable \
                    --cluster "$CLUSTER_NAME" \
                    --services "$component" \
                    --region "$AWS_REGION"
                
                log_success "Rollback completed for $component"
            else
                log_error "No previous task definition found for rollback"
                exit 1
            fi
            ;;
            
        "status")
            show_deployment_status "$environment"
            ;;
            
        "health")
            perform_health_check "$environment"
            ;;
            
        *)
            log_error "Unknown action: $action"
            exit 1
            ;;
    esac
    
    # Show final status
    show_deployment_status "$environment"
}

# Show usage information
usage() {
    cat <<EOF
Enhanced AI Blockchain Analytics ECS Deployment Script

Usage: $0 [environment] [component] [action] [image_tag]

Arguments:
  environment  Target environment (development, staging, production)
               Default: production
  
  component    Component to deploy:
               - all: Deploy all components (default)
               - infrastructure: CloudFormation/Terraform infrastructure
               - app/roadrunner: Application server
               - worker/horizon: Queue worker
  
  action      Action to perform:
               - deploy: Deploy components (default)
               - rollback: Rollback deployment
               - status: Show deployment status
               - health: Perform health check
  
  image_tag   Docker image tag (default: timestamp)

Examples:
  $0 production all deploy                    # Full production deployment
  $0 staging app deploy v1.2.3              # Deploy staging app with specific tag
  $0 development infrastructure deploy        # Deploy only infrastructure for dev
  $0 production roadrunner-app rollback      # Rollback production app
  $0 staging status                          # Show staging deployment status
  $0 production health                       # Perform production health check

Environment Variables:
  AWS_REGION            AWS region (default: us-east-1)
  AWS_ACCOUNT_ID        AWS account ID (auto-detected if not set)
  CLUSTER_NAME          ECS cluster name (default: ai-blockchain-cluster)
  BUILD_CONTEXT         Docker build context path (default: ../)
  DEBUG                 Enable debug logging (default: false)
  
  # Application Configuration
  APP_KEY              Laravel application key
  DB_HOST              Database hostname
  DB_PASSWORD          Database password
  REDIS_HOST           Redis hostname
  REDIS_PASSWORD       Redis password
  STRIPE_SECRET        Stripe secret key
  SENTRY_DSN          Sentry error tracking DSN
  GOOGLE_CREDENTIALS   Google service account credentials (JSON)

Prerequisites:
  - AWS CLI configured with appropriate permissions
  - Docker with BuildKit support
  - jq for JSON processing
  - curl for health checks
  - Git for build metadata

Features:
  - Optimized RoadRunner configuration with performance tuning
  - Auto-scaling based on CPU utilization and custom metrics
  - Comprehensive monitoring with CloudWatch and X-Ray
  - Zero-downtime deployments with health checks
  - Enhanced security with IAM roles and VPC configuration
  - Automated SSL certificate management
  - Database migrations with error handling
  - Log aggregation with CloudWatch Logs and Fluent Bit
  - Infrastructure as Code with CloudFormation/Terraform
  - Multi-environment support with environment-specific configurations
  - Cost optimization with Spot instances and resource sizing

EOF
    exit 1
}

# Parse command line arguments
if [[ "${1:-}" =~ ^(-h|--help)$ ]]; then
    usage
fi

environment=${1:-production}
component=${2:-all}
action=${3:-deploy}
image_tag=${4:-}

# Validate component
valid_components="all|infrastructure|app|roadrunner|worker|horizon"
if [[ ! "$component" =~ ^($valid_components)$ ]]; then
    log_error "Invalid component: $component"
    log_info "Valid components: ${valid_components//|/, }"
    exit 1
fi

# Validate action
valid_actions="deploy|rollback|status|health"
if [[ ! "$action" =~ ^($valid_actions)$ ]]; then
    log_error "Invalid action: $action"
    log_info "Valid actions: ${valid_actions//|/, }"
    exit 1
fi

# Start deployment
deploy "$environment" "$component" "$action" "$image_tag"

