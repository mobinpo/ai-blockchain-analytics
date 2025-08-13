#!/bin/bash

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
NAMESPACE="ai-blockchain-analytics"
DOCKER_REGISTRY="${DOCKER_REGISTRY:-your-registry}"
IMAGE_TAG="${IMAGE_TAG:-latest}"
KUBECTL="${KUBECTL:-kubectl}"
KUSTOMIZE="${KUSTOMIZE:-kustomize}"

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
    
    if ! command -v kubectl &> /dev/null; then
        log_error "kubectl is not installed"
        exit 1
    fi
    
    if ! command -v docker &> /dev/null; then
        log_error "docker is not installed"
        exit 1
    fi
    
    # Test kubectl connection
    if ! kubectl cluster-info &> /dev/null; then
        log_error "Cannot connect to Kubernetes cluster"
        exit 1
    fi
    
    log_success "Prerequisites check passed"
}

# Build and push Docker image
build_and_push_image() {
    log_info "Building and pushing Docker image..."
    
    # Build RoadRunner image
    docker build -f Dockerfile.roadrunner -t "${DOCKER_REGISTRY}/ai-blockchain-analytics:${IMAGE_TAG}" .
    
    # Push image
    docker push "${DOCKER_REGISTRY}/ai-blockchain-analytics:${IMAGE_TAG}"
    
    log_success "Image built and pushed: ${DOCKER_REGISTRY}/ai-blockchain-analytics:${IMAGE_TAG}"
}

# Update image references in manifests
update_image_references() {
    log_info "Updating image references in manifests..."
    
    # Update app deployment
    sed -i.bak "s|your-registry/ai-blockchain-analytics:latest|${DOCKER_REGISTRY}/ai-blockchain-analytics:${IMAGE_TAG}|g" \
        k8s/app/app-deployment.yaml
    
    log_success "Image references updated"
}

# Deploy database
deploy_database() {
    log_info "Deploying PostgreSQL database..."
    
    kubectl apply -f k8s/postgres/postgres-secret.yaml
    kubectl apply -f k8s/postgres/postgres-pvc.yaml
    kubectl apply -f k8s/postgres/postgres-deployment.yaml
    
    # Wait for database to be ready
    log_info "Waiting for PostgreSQL to be ready..."
    kubectl wait --for=condition=ready pod -l app=postgres -n $NAMESPACE --timeout=300s
    
    log_success "PostgreSQL deployed successfully"
}

# Deploy cache
deploy_cache() {
    log_info "Deploying Redis cache..."
    
    kubectl apply -f k8s/redis/redis-deployment.yaml
    
    # Wait for Redis to be ready
    log_info "Waiting for Redis to be ready..."
    kubectl wait --for=condition=ready pod -l app=redis -n $NAMESPACE --timeout=300s
    
    log_success "Redis deployed successfully"
}

# Deploy application
deploy_application() {
    log_info "Deploying application..."
    
    kubectl apply -f k8s/app/app-secret.yaml
    kubectl apply -f k8s/app/app-deployment.yaml
    kubectl apply -f k8s/app/app-hpa.yaml
    kubectl apply -f k8s/app/app-ingress.yaml
    
    # Wait for application to be ready
    log_info "Waiting for application to be ready..."
    kubectl wait --for=condition=ready pod -l app=ai-blockchain-analytics,component=web -n $NAMESPACE --timeout=600s
    
    log_success "Application deployed successfully"
}

# Run database migrations
run_migrations() {
    log_info "Running database migrations..."
    
    # Get a running app pod
    APP_POD=$(kubectl get pods -n $NAMESPACE -l app=ai-blockchain-analytics,component=web -o jsonpath='{.items[0].metadata.name}')
    
    if [ -n "$APP_POD" ]; then
        kubectl exec -n $NAMESPACE $APP_POD -- php artisan migrate --force
        kubectl exec -n $NAMESPACE $APP_POD -- php artisan config:cache
        kubectl exec -n $NAMESPACE $APP_POD -- php artisan route:cache
        kubectl exec -n $NAMESPACE $APP_POD -- php artisan view:cache
        
        log_success "Database migrations completed"
    else
        log_warning "No running app pod found, skipping migrations"
    fi
}

# Setup monitoring
setup_monitoring() {
    log_info "Setting up monitoring..."
    
    # Create monitoring namespace if it doesn't exist
    kubectl create namespace monitoring --dry-run=client -o yaml | kubectl apply -f -
    
    # Deploy ServiceMonitor for Prometheus
    cat <<EOF | kubectl apply -f -
apiVersion: monitoring.coreos.com/v1
kind: ServiceMonitor
metadata:
  name: ai-blockchain-analytics
  namespace: monitoring
  labels:
    app: ai-blockchain-analytics
spec:
  selector:
    matchLabels:
      app: ai-blockchain-analytics
  endpoints:
  - port: metrics
    path: /metrics
    interval: 30s
  namespaceSelector:
    matchNames:
    - $NAMESPACE
EOF
    
    log_success "Monitoring setup completed"
}

# Verify deployment
verify_deployment() {
    log_info "Verifying deployment..."
    
    # Check pod status
    kubectl get pods -n $NAMESPACE
    
    # Check service status
    kubectl get services -n $NAMESPACE
    
    # Check ingress status
    kubectl get ingress -n $NAMESPACE
    
    # Test health endpoint
    log_info "Testing health endpoint..."
    
    # Port forward to test locally
    kubectl port-forward -n $NAMESPACE svc/ai-blockchain-analytics-service 8080:80 &
    PF_PID=$!
    sleep 5
    
    if curl -f http://localhost:8080/api/health; then
        log_success "Health check passed"
    else
        log_warning "Health check failed"
    fi
    
    kill $PF_PID 2>/dev/null || true
    
    log_success "Deployment verification completed"
}

# Cleanup function
cleanup() {
    log_info "Cleaning up..."
    
    # Restore original manifests
    if [ -f k8s/app/app-deployment.yaml.bak ]; then
        mv k8s/app/app-deployment.yaml.bak k8s/app/app-deployment.yaml
    fi
    
    # Kill any remaining background processes
    pkill -f "kubectl port-forward" 2>/dev/null || true
}

# Main deployment function
main() {
    log_info "Starting Kubernetes deployment for AI Blockchain Analytics..."
    
    # Set trap for cleanup
    trap cleanup EXIT
    
    # Parse arguments
    SKIP_BUILD=false
    SKIP_MIGRATIONS=false
    
    while [[ $# -gt 0 ]]; do
        case $1 in
            --skip-build)
                SKIP_BUILD=true
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
            --registry)
                DOCKER_REGISTRY="$2"
                shift 2
                ;;
            -h|--help)
                echo "Usage: $0 [OPTIONS]"
                echo "Options:"
                echo "  --skip-build         Skip Docker image build and push"
                echo "  --skip-migrations    Skip database migrations"
                echo "  --image-tag TAG      Docker image tag (default: latest)"
                echo "  --registry REG       Docker registry (default: your-registry)"
                echo "  -h, --help          Show this help message"
                exit 0
                ;;
            *)
                log_error "Unknown option: $1"
                exit 1
                ;;
        esac
    done
    
    # Run deployment steps
    check_prerequisites
    
    # Create namespace
    kubectl create namespace $NAMESPACE --dry-run=client -o yaml | kubectl apply -f -
    
    if [ "$SKIP_BUILD" = false ]; then
        build_and_push_image
        update_image_references
    fi
    
    deploy_database
    deploy_cache
    deploy_application
    
    if [ "$SKIP_MIGRATIONS" = false ]; then
        run_migrations
    fi
    
    setup_monitoring
    verify_deployment
    
    log_success "Kubernetes deployment completed successfully!"
    
    # Display useful information
    echo ""
    echo "=== Deployment Information ==="
    echo "Namespace: $NAMESPACE"
    echo "Image: ${DOCKER_REGISTRY}/ai-blockchain-analytics:${IMAGE_TAG}"
    echo ""
    echo "=== Useful Commands ==="
    echo "View pods: kubectl get pods -n $NAMESPACE"
    echo "View logs: kubectl logs -f deployment/ai-blockchain-analytics-app -n $NAMESPACE"
    echo "Scale app: kubectl scale deployment/ai-blockchain-analytics-app --replicas=5 -n $NAMESPACE"
    echo "Port forward: kubectl port-forward -n $NAMESPACE svc/ai-blockchain-analytics-service 8080:80"
    echo ""
    echo "=== Access Information ==="
    INGRESS_IP=$(kubectl get ingress -n $NAMESPACE ai-blockchain-analytics-ingress -o jsonpath='{.status.loadBalancer.ingress[0].ip}' 2>/dev/null || echo "Pending...")
    echo "Ingress IP: $INGRESS_IP"
    echo "Health Check: curl http://$INGRESS_IP/api/health"
    echo ""
}

# Run main function with all arguments
main "$@"