#!/bin/bash

# AI Blockchain Analytics - Production Kubernetes Deployment Script
# Comprehensive production deployment with RoadRunner, Redis, and PostgreSQL

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
NAMESPACE="ai-blockchain-analytics-production"
DOCKER_REGISTRY="${DOCKER_REGISTRY:-your-registry.com}"
IMAGE_TAG="${IMAGE_TAG:-latest}"
CLUSTER_NAME="${CLUSTER_NAME:-production}"
DOMAIN="${DOMAIN:-ai-blockchain-analytics.example.com}"
DRY_RUN="${DRY_RUN:-false}"
SKIP_BUILD="${SKIP_BUILD:-false}"
FORCE="${FORCE:-false}"

# Print banner
print_banner() {
    echo -e "${CYAN}"
    echo "═══════════════════════════════════════════════════════════════════"
    echo "   🚀 AI Blockchain Analytics - Production K8s Deployment"
    echo "   ☸️  RoadRunner + Redis + PostgreSQL"
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
  --registry REGISTRY    Docker registry URL (default: your-registry.com)
  --tag TAG             Docker image tag (default: latest)
  --namespace NAMESPACE  Kubernetes namespace (default: ai-blockchain-analytics-production)
  --domain DOMAIN       Application domain (default: ai-blockchain-analytics.example.com)
  --cluster CLUSTER     Cluster name (default: production)
  --skip-build          Skip building Docker images
  --dry-run            Show what would be done without executing
  --force              Force deployment without confirmation
  --help               Show this help message

ENVIRONMENT VARIABLES:
  DOCKER_REGISTRY       Default Docker registry
  IMAGE_TAG            Default image tag
  KUBECONFIG           Kubernetes configuration file

EXAMPLES:
  # Deploy with custom registry and tag
  ./deploy-production.sh --registry my-registry.com --tag v1.2.3

  # Dry run to see what would be deployed
  ./deploy-production.sh --dry-run

  # Deploy to custom domain
  ./deploy-production.sh --domain my-analytics.com

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
            --namespace)
                NAMESPACE="$2"
                shift 2
                ;;
            --domain)
                DOMAIN="$2"
                shift 2
                ;;
            --cluster)
                CLUSTER_NAME="$2"
                shift 2
                ;;
            --skip-build)
                SKIP_BUILD="true"
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
}

# Check prerequisites
check_prerequisites() {
    log_step "Checking prerequisites..."

    # Check required tools
    command -v kubectl >/dev/null 2>&1 || { log_error "kubectl is required but not installed"; exit 1; }
    command -v docker >/dev/null 2>&1 || { log_error "Docker is required but not installed"; exit 1; }
    command -v envsubst >/dev/null 2>&1 || { log_error "envsubst is required but not installed"; exit 1; }

    # Check Kubernetes cluster access
    if ! kubectl cluster-info >/dev/null 2>&1; then
        log_error "No Kubernetes cluster access. Please check your kubeconfig."
        exit 1
    fi

    local context=$(kubectl config current-context)
    log_info "Current Kubernetes context: $context"

    # Warning for production deployment
    if [[ "$FORCE" != "true" && "$DRY_RUN" != "true" ]]; then
        echo -e "${YELLOW}"
        echo "⚠️  WARNING: This will deploy to PRODUCTION environment!"
        echo "   Cluster: $CLUSTER_NAME"
        echo "   Namespace: $NAMESPACE"
        echo "   Registry: $DOCKER_REGISTRY"
        echo "   Tag: $IMAGE_TAG"
        echo "   Domain: $DOMAIN"
        echo -e "${NC}"
        read -p "Are you sure you want to continue? (yes/no): " confirm
        if [[ "$confirm" != "yes" ]]; then
            log_info "Deployment cancelled"
            exit 0
        fi
    fi

    log_success "Prerequisites check passed"
}

# Build and push Docker images
build_and_push_images() {
    if [[ "$SKIP_BUILD" == "true" ]]; then
        log_info "Skipping image build (--skip-build specified)"
        return
    fi

    log_step "Building and pushing Docker images..."

    local images=(
        "ai-blockchain-analytics"
        "ai-blockchain-analytics-worker"
        "ai-blockchain-analytics-scheduler"
    )

    if [[ "$DRY_RUN" == "true" ]]; then
        for image in "${images[@]}"; do
            log_info "[DRY RUN] Would build and push: ${DOCKER_REGISTRY}/${image}:${IMAGE_TAG}"
        done
        return
    fi

    # Build and push each image
    for image in "${images[@]}"; do
        local full_image="${DOCKER_REGISTRY}/${image}:${IMAGE_TAG}"
        log_info "Building: $full_image"
        
        case $image in
            "*-worker")
                docker build --target worker -t "$full_image" .
                ;;
            "*-scheduler")
                docker build --target scheduler -t "$full_image" .
                ;;
            *)
                docker build --target production -t "$full_image" .
                ;;
        esac
        
        log_info "Pushing: $full_image"
        docker push "$full_image"
    done

    log_success "All images built and pushed successfully"
}

# Create namespace and basic resources
create_namespace() {
    log_step "Creating namespace and basic resources..."

    if [[ "$DRY_RUN" == "true" ]]; then
        log_info "[DRY RUN] Would create namespace: $NAMESPACE"
        return
    fi

    # Create namespace
    kubectl create namespace "$NAMESPACE" --dry-run=client -o yaml | kubectl apply -f -

    # Label namespace
    kubectl label namespace "$NAMESPACE" \
        name="$NAMESPACE" \
        environment="production" \
        app="ai-blockchain-analytics" \
        --overwrite

    log_success "Namespace created: $NAMESPACE"
}

# Deploy secrets (placeholder - should be managed securely)
deploy_secrets() {
    log_step "Deploying secrets..."

    if [[ "$DRY_RUN" == "true" ]]; then
        log_info "[DRY RUN] Would deploy application secrets"
        return
    fi

    # Warning about secrets
    log_warning "Using placeholder secrets - UPDATE THESE IN PRODUCTION!"
    
    # Create app secrets (you should replace these with real values)
    kubectl create secret generic app-secrets \
        --namespace="$NAMESPACE" \
        --from-literal=APP_KEY="base64:$(openssl rand -base64 32)" \
        --from-literal=DB_USERNAME="postgres" \
        --from-literal=DB_PASSWORD="secure_password_change_me" \
        --from-literal=JWT_SECRET="$(openssl rand -base64 64)" \
        --from-literal=GOOGLE_CLOUD_API_KEY="" \
        --from-literal=ETHERSCAN_API_KEY="" \
        --from-literal=BSCSCAN_API_KEY="" \
        --dry-run=client -o yaml | kubectl apply -f -

    log_success "Secrets deployed (remember to update with real values!)"
}

# Deploy the application
deploy_application() {
    log_step "Deploying application manifests..."

    if [[ "$DRY_RUN" == "true" ]]; then
        log_info "[DRY RUN] Would deploy application to: $NAMESPACE"
        return
    fi

    # Substitute environment variables in the deployment manifest
    export NAMESPACE DOCKER_REGISTRY IMAGE_TAG DOMAIN
    envsubst < k8s/production-deployment.yaml > /tmp/deployment.yaml

    # Apply the deployment
    kubectl apply -f /tmp/deployment.yaml

    # Clean up temp file
    rm -f /tmp/deployment.yaml

    log_success "Application manifests deployed"
}

# Wait for deployments
wait_for_deployments() {
    log_step "Waiting for deployments to be ready..."

    if [[ "$DRY_RUN" == "true" ]]; then
        log_info "[DRY RUN] Would wait for deployments to be ready"
        return
    fi

    local deployments=("postgres" "redis" "app" "worker" "scheduler")

    for deployment in "${deployments[@]}"; do
        log_info "Waiting for deployment: $deployment"
        
        if ! kubectl wait --for=condition=available --timeout=600s \
            deployment/"$deployment" -n "$NAMESPACE"; then
            log_error "Deployment $deployment failed to become ready"
            
            # Show logs for debugging
            log_info "Recent logs for $deployment:"
            kubectl logs deployment/"$deployment" -n "$NAMESPACE" --tail=20 || true
            
            exit 1
        fi
    done

    log_success "All deployments are ready"
}

# Run post-deployment tasks
run_post_deployment() {
    log_step "Running post-deployment tasks..."

    if [[ "$DRY_RUN" == "true" ]]; then
        log_info "[DRY RUN] Would run post-deployment tasks"
        return
    fi

    # Wait a moment for the app to be fully ready
    sleep 10

    # Run database migrations
    log_info "Running database migrations..."
    kubectl exec deployment/app -n "$NAMESPACE" -- php artisan migrate --force

    # Clear and warm up caches
    log_info "Clearing and warming up caches..."
    kubectl exec deployment/app -n "$NAMESPACE" -- php artisan config:clear
    kubectl exec deployment/app -n "$NAMESPACE" -- php artisan route:clear
    kubectl exec deployment/app -n "$NAMESPACE" -- php artisan view:clear
    kubectl exec deployment/app -n "$NAMESPACE" -- php artisan config:cache
    kubectl exec deployment/app -n "$NAMESPACE" -- php artisan route:cache
    kubectl exec deployment/app -n "$NAMESPACE" -- php artisan view:cache

    # Restart Horizon to ensure it picks up new jobs
    log_info "Restarting Horizon workers..."
    kubectl rollout restart deployment/worker -n "$NAMESPACE"

    log_success "Post-deployment tasks completed"
}

# Show deployment status
show_status() {
    log_step "Deployment status:"

    echo -e "\n${CYAN}Namespace: $NAMESPACE${NC}"
    kubectl get all -n "$NAMESPACE" 2>/dev/null || log_warning "Namespace not found"

    echo -e "\n${CYAN}Persistent Volumes:${NC}"
    kubectl get pv,pvc -n "$NAMESPACE" 2>/dev/null || true

    echo -e "\n${CYAN}Ingress:${NC}"
    kubectl get ingress -n "$NAMESPACE" 2>/dev/null || true

    echo -e "\n${CYAN}Recent Events:${NC}"
    kubectl get events -n "$NAMESPACE" --sort-by='.lastTimestamp' | tail -10 2>/dev/null || true

    if [[ "$DRY_RUN" != "true" ]]; then
        echo -e "\n${GREEN}Application URL: https://$DOMAIN${NC}"
        echo -e "${GREEN}Health Check: https://$DOMAIN/health${NC}"
        echo -e "${GREEN}API Documentation: https://$DOMAIN/api/docs${NC}"
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
                kubectl delete namespace "$NAMESPACE" --ignore-not-found=true
                log_info "Rollback completed"
            fi
        fi
    fi
}

# Main execution flow
main() {
    print_banner
    parse_arguments "$@"
    check_prerequisites

    # Set trap for cleanup on failure
    trap cleanup_on_failure EXIT

    # Show configuration
    log_info "Deployment Configuration:"
    log_info "  Registry: $DOCKER_REGISTRY"
    log_info "  Tag: $IMAGE_TAG"
    log_info "  Namespace: $NAMESPACE"
    log_info "  Domain: $DOMAIN"
    log_info "  Cluster: $CLUSTER_NAME"
    echo

    # Execute deployment steps
    build_and_push_images
    create_namespace
    deploy_secrets
    deploy_application
    wait_for_deployments
    run_post_deployment
    show_status

    # Remove trap on successful completion
    trap - EXIT

    echo -e "\n${GREEN}🎉 Production deployment completed successfully!${NC}"
    echo -e "${GREEN}🚀 Application is available at: https://$DOMAIN${NC}"
}

# Execute main function
main "$@"
