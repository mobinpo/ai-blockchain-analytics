#!/bin/bash

# AI Blockchain Analytics - Universal Deployment Script
# Supports both Kubernetes and ECS deployments with RoadRunner optimization
# Usage: ./deploy.sh [platform] [environment] [action]

set -euo pipefail

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_NAME="ai-blockchain-analytics"

# Parse arguments
PLATFORM=${1:-}
ENVIRONMENT=${2:-production}
ACTION=${3:-deploy}

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m'

# Logging functions
log_info() {
    echo -e "[$(date +'%Y-%m-%d %H:%M:%S')] ${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "[$(date +'%Y-%m-%d %H:%M:%S')] ${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "[$(date +'%Y-%m-%d %H:%M:%S')] ${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "[$(date +'%Y-%m-%d %H:%M:%S')] ${RED}[ERROR]${NC} $1"
}

log_step() {
    echo -e "[$(date +'%Y-%m-%d %H:%M:%S')] ${CYAN}[STEP]${NC} $1"
}

# Error handling
error_exit() {
    log_error "$1"
    exit 1
}

# Show usage
show_usage() {
    echo ""
    echo "🚀 AI Blockchain Analytics - Universal Deployment Script"
    echo "========================================================"
    echo ""
    echo "Usage: $0 [platform] [environment] [action]"
    echo ""
    echo "Platforms:"
    echo "  k8s, kubernetes    Deploy to Kubernetes cluster"
    echo "  ecs, aws           Deploy to AWS ECS"
    echo ""
    echo "Environments:"
    echo "  development        Development environment"
    echo "  staging            Staging environment"
    echo "  production         Production environment (default)"
    echo ""
    echo "Actions:"
    echo "  deploy             Full deployment (default)"
    echo "  build              Build and push container images only"
    echo "  verify             Verify existing deployment"
    echo "  cleanup            Clean up deployment resources"
    echo "  status             Show deployment status"
    echo ""
    echo "Examples:"
    echo "  $0 k8s production deploy     # Deploy to Kubernetes production"
    echo "  $0 ecs staging build         # Build and push images for ECS staging"
    echo "  $0 kubernetes development    # Deploy to Kubernetes development"
    echo "  $0 aws production verify     # Verify AWS ECS production deployment"
    echo ""
    echo "Environment Variables:"
    echo "  AWS_REGION                   AWS region (default: us-east-1)"
    echo "  AWS_ACCOUNT_ID               AWS account ID (auto-detected)"
    echo "  KUBE_CONTEXT                 Kubernetes context (current context)"
    echo "  DOCKER_REGISTRY              Docker registry URL"
    echo "  DEBUG                        Enable debug logging (true/false)"
    echo ""
}

# Detect platform if not specified
detect_platform() {
    if [[ -z "$PLATFORM" ]]; then
        log_step "Auto-detecting deployment platform..."
        
        # Check for Kubernetes
        if command -v kubectl &> /dev/null && kubectl cluster-info > /dev/null 2>&1; then
            PLATFORM="k8s"
            log_info "Detected Kubernetes platform"
        # Check for AWS CLI
        elif command -v aws &> /dev/null && aws sts get-caller-identity > /dev/null 2>&1; then
            PLATFORM="ecs"
            log_info "Detected AWS ECS platform"
        else
            error_exit "Could not auto-detect platform. Please specify 'k8s' or 'ecs'"
        fi
    fi
}

# Validate platform
validate_platform() {
    case $PLATFORM in
        k8s|kubernetes)
            PLATFORM="k8s"
            ;;
        ecs|aws)
            PLATFORM="ecs"
            ;;
        *)
            error_exit "Invalid platform: $PLATFORM. Must be 'k8s' or 'ecs'"
            ;;
    esac
}

# Validate environment
validate_environment() {
    case $ENVIRONMENT in
        development|dev)
            ENVIRONMENT="development"
            ;;
        staging|stage)
            ENVIRONMENT="staging"
            ;;
        production|prod)
            ENVIRONMENT="production"
            ;;
        *)
            error_exit "Invalid environment: $ENVIRONMENT. Must be development, staging, or production"
            ;;
    esac
}

# Check prerequisites
check_prerequisites() {
    log_step "Checking prerequisites for $PLATFORM deployment..."
    
    case $PLATFORM in
        k8s)
            if ! command -v kubectl &> /dev/null; then
                error_exit "kubectl is not installed"
            fi
            
            if ! kubectl cluster-info > /dev/null 2>&1; then
                error_exit "kubectl is not configured or cluster is unreachable"
            fi
            
            log_info "✅ Kubernetes prerequisites satisfied"
            ;;
        ecs)
            if ! command -v aws &> /dev/null; then
                error_exit "AWS CLI is not installed"
            fi
            
            if ! aws sts get-caller-identity > /dev/null 2>&1; then
                error_exit "AWS credentials are not configured"
            fi
            
            if ! command -v jq &> /dev/null; then
                error_exit "jq is not installed"
            fi
            
            log_info "✅ AWS ECS prerequisites satisfied"
            ;;
    esac
    
    # Check Docker for build operations
    if [[ "$ACTION" == "deploy" || "$ACTION" == "build" ]]; then
        if ! command -v docker &> /dev/null; then
            log_warning "Docker is not installed - image building will be skipped"
        else
            log_info "✅ Docker is available for image building"
        fi
    fi
}

# Pre-deployment checks
pre_deployment_checks() {
    log_step "Running pre-deployment checks..."
    
    # Check if required configuration files exist
    case $PLATFORM in
        k8s)
            if [[ ! -f "$SCRIPT_DIR/k8s/enhanced-roadrunner-deployment.yaml" ]]; then
                error_exit "Kubernetes deployment configuration not found"
            fi
            
            if [[ ! -f "$SCRIPT_DIR/k8s/deploy-roadrunner-enhanced.sh" ]]; then
                error_exit "Kubernetes deployment script not found"
            fi
            ;;
        ecs)
            if [[ ! -f "$SCRIPT_DIR/ecs/enhanced-roadrunner-deployment.sh" ]]; then
                error_exit "ECS deployment script not found"
            fi
            
            if [[ ! -f "$SCRIPT_DIR/docker/Dockerfile.roadrunner" ]]; then
                error_exit "RoadRunner Dockerfile not found"
            fi
            ;;
    esac
    
    # Check environment-specific configurations
    local env_file="$SCRIPT_DIR/.env.$ENVIRONMENT"
    if [[ -f "$env_file" ]]; then
        log_info "✅ Environment configuration found: $env_file"
    else
        log_warning "Environment configuration not found: $env_file"
    fi
    
    log_success "Pre-deployment checks completed"
}

# Deploy to Kubernetes
deploy_kubernetes() {
    log_step "Deploying to Kubernetes ($ENVIRONMENT)..."
    
    local k8s_script="$SCRIPT_DIR/k8s/deploy-roadrunner-enhanced.sh"
    local kube_context="${KUBE_CONTEXT:-$(kubectl config current-context)}"
    
    log_info "Using Kubernetes context: $kube_context"
    log_info "Executing: $k8s_script $ENVIRONMENT $kube_context ai-blockchain-analytics $ACTION"
    
    # Execute Kubernetes deployment script
    bash "$k8s_script" "$ENVIRONMENT" "$kube_context" "ai-blockchain-analytics" "$ACTION"
    
    log_success "Kubernetes deployment completed"
}

# Deploy to ECS
deploy_ecs() {
    log_step "Deploying to AWS ECS ($ENVIRONMENT)..."
    
    local ecs_script="$SCRIPT_DIR/ecs/enhanced-roadrunner-deployment.sh"
    
    log_info "AWS Region: ${AWS_REGION:-us-east-1}"
    log_info "Executing: $ecs_script $ENVIRONMENT $ACTION"
    
    # Execute ECS deployment script
    bash "$ecs_script" "$ENVIRONMENT" "$ACTION"
    
    log_success "ECS deployment completed"
}

# Show deployment status
show_status() {
    log_step "Checking deployment status..."
    
    case $PLATFORM in
        k8s)
            local namespace="ai-blockchain-analytics"
            echo ""
            echo "📊 Kubernetes Status ($ENVIRONMENT):"
            echo "=================================="
            
            if kubectl get namespace "$namespace" > /dev/null 2>&1; then
                echo ""
                echo "Deployments:"
                kubectl get deployments -n "$namespace" 2>/dev/null || echo "  No deployments found"
                
                echo ""
                echo "Services:"
                kubectl get services -n "$namespace" 2>/dev/null || echo "  No services found"
                
                echo ""
                echo "Pods:"
                kubectl get pods -n "$namespace" 2>/dev/null || echo "  No pods found"
                
                echo ""
                echo "Ingress:"
                kubectl get ingress -n "$namespace" 2>/dev/null || echo "  No ingress found"
            else
                echo "  Namespace '$namespace' not found"
            fi
            ;;
        ecs)
            local cluster_name="ai-blockchain-cluster-${ENVIRONMENT}"
            echo ""
            echo "📊 ECS Status ($ENVIRONMENT):"
            echo "========================="
            
            if aws ecs describe-clusters --clusters "$cluster_name" --region "${AWS_REGION:-us-east-1}" > /dev/null 2>&1; then
                echo ""
                echo "Cluster:"
                aws ecs describe-clusters --clusters "$cluster_name" --region "${AWS_REGION:-us-east-1}" --query 'clusters[0].[clusterName,status,runningTasksCount,pendingTasksCount,activeServicesCount]' --output table
                
                echo ""
                echo "Services:"
                aws ecs list-services --cluster "$cluster_name" --region "${AWS_REGION:-us-east-1}" --query 'serviceArns' --output table 2>/dev/null || echo "  No services found"
                
                echo ""
                echo "Tasks:"
                aws ecs list-tasks --cluster "$cluster_name" --region "${AWS_REGION:-us-east-1}" --query 'taskArns' --output table 2>/dev/null || echo "  No tasks found"
            else
                echo "  Cluster '$cluster_name' not found"
            fi
            ;;
    esac
}

# Generate deployment report
generate_report() {
    log_step "Generating deployment report..."
    
    local report_file="deployment-report-${PLATFORM}-${ENVIRONMENT}-$(date +%Y%m%d-%H%M%S).md"
    
    cat > "$report_file" <<EOF
# AI Blockchain Analytics - Deployment Report

**Platform:** $PLATFORM  
**Environment:** $ENVIRONMENT  
**Action:** $ACTION  
**Date:** $(date)  
**User:** $(whoami)  

## Configuration

- **Script Directory:** $SCRIPT_DIR
- **Project Name:** $PROJECT_NAME
EOF

    case $PLATFORM in
        k8s)
            cat >> "$report_file" <<EOF
- **Kubernetes Context:** $(kubectl config current-context)
- **Kubernetes Namespace:** ai-blockchain-analytics

## Kubernetes Resources

### Deployments
\`\`\`
$(kubectl get deployments -n ai-blockchain-analytics 2>/dev/null || echo "No deployments found")
\`\`\`

### Services
\`\`\`
$(kubectl get services -n ai-blockchain-analytics 2>/dev/null || echo "No services found")
\`\`\`

### Pods
\`\`\`
$(kubectl get pods -n ai-blockchain-analytics 2>/dev/null || echo "No pods found")
\`\`\`
EOF
            ;;
        ecs)
            cat >> "$report_file" <<EOF
- **AWS Region:** ${AWS_REGION:-us-east-1}
- **AWS Account:** ${AWS_ACCOUNT_ID:-$(aws sts get-caller-identity --query Account --output text 2>/dev/null || echo "Unknown")}
- **ECS Cluster:** ai-blockchain-cluster-${ENVIRONMENT}

## ECS Resources

### Cluster Status
\`\`\`
$(aws ecs describe-clusters --clusters "ai-blockchain-cluster-${ENVIRONMENT}" --region "${AWS_REGION:-us-east-1}" --query 'clusters[0]' --output table 2>/dev/null || echo "Cluster not found")
\`\`\`

### Services
\`\`\`
$(aws ecs list-services --cluster "ai-blockchain-cluster-${ENVIRONMENT}" --region "${AWS_REGION:-us-east-1}" --output table 2>/dev/null || echo "No services found")
\`\`\`
EOF
            ;;
    esac
    
    cat >> "$report_file" <<EOF

## Deployment Summary

- ✅ Prerequisites checked
- ✅ Platform validated: $PLATFORM
- ✅ Environment validated: $ENVIRONMENT
- ✅ Action executed: $ACTION

## Next Steps

1. Verify the deployment is working correctly
2. Run health checks on all services
3. Monitor logs for any issues
4. Update DNS records if necessary
5. Notify team of deployment completion

---
*Generated by AI Blockchain Analytics Universal Deployment Script*
EOF
    
    log_success "Deployment report generated: $report_file"
}

# Main execution
main() {
    # Show header
    echo ""
    echo "🚀 AI Blockchain Analytics - Universal Deployment Script"
    echo "========================================================"
    echo ""
    
    # Show usage if no arguments
    if [[ $# -eq 0 ]]; then
        show_usage
        exit 0
    fi
    
    # Handle help
    if [[ "$1" == "-h" || "$1" == "--help" || "$1" == "help" ]]; then
        show_usage
        exit 0
    fi
    
    # Detect platform if not specified
    detect_platform
    
    # Validate inputs
    validate_platform
    validate_environment
    
    # Show configuration
    log_info "Platform: $PLATFORM"
    log_info "Environment: $ENVIRONMENT"
    log_info "Action: $ACTION"
    echo ""
    
    # Check prerequisites
    check_prerequisites
    
    # Execute action
    case $ACTION in
        deploy)
            pre_deployment_checks
            case $PLATFORM in
                k8s)
                    deploy_kubernetes
                    ;;
                ecs)
                    deploy_ecs
                    ;;
            esac
            show_status
            generate_report
            ;;
        build)
            case $PLATFORM in
                k8s)
                    deploy_kubernetes
                    ;;
                ecs)
                    deploy_ecs
                    ;;
            esac
            ;;
        verify)
            case $PLATFORM in
                k8s)
                    deploy_kubernetes
                    ;;
                ecs)
                    deploy_ecs
                    ;;
            esac
            ;;
        cleanup)
            case $PLATFORM in
                k8s)
                    deploy_kubernetes
                    ;;
                ecs)
                    deploy_ecs
                    ;;
            esac
            ;;
        status)
            show_status
            ;;
        *)
            error_exit "Invalid action: $ACTION. Must be deploy, build, verify, cleanup, or status"
            ;;
    esac
    
    echo ""
    log_success "🎉 Universal deployment script completed successfully!"
    echo ""
}

# Execute main function
main "$@"
