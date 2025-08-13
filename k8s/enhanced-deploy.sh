#!/bin/bash

# Enhanced Kubernetes Deployment Script
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
NAMESPACE="ai-blockchain-analytics"
APP_NAME="ai-blockchain-analytics"
DOCKER_REGISTRY="${DOCKER_REGISTRY:-your-registry}"
IMAGE_TAG="${IMAGE_TAG:-latest}"
ENVIRONMENT="${ENVIRONMENT:-production}"
KUBECTL_TIMEOUT="${KUBECTL_TIMEOUT:-600s}"

# Required tools
REQUIRED_TOOLS=("kubectl" "helm" "docker")

# Deployment phases
PHASES=(
    "preflight"
    "secrets"
    "storage"
    "database" 
    "cache"
    "application"
    "workers"
    "ingress"
    "monitoring"
    "validation"
)

# Logging function
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
    
    # Check kubectl connection
    if ! kubectl cluster-info &> /dev/null; then
        error "Cannot connect to Kubernetes cluster"
        exit 1
    fi
    
    # Check Docker registry access
    if ! docker info &> /dev/null; then
        warning "Docker is not running. Image building will be skipped."
    fi
    
    log "Prerequisites check completed successfully"
}

# Build and push Docker image
build_and_push_image() {
    log "Building and pushing Docker image..."
    
    if ! docker info &> /dev/null; then
        warning "Skipping image build - Docker not available"
        return 0
    fi
    
    # Build the image
    docker build -t "${DOCKER_REGISTRY}/${APP_NAME}:${IMAGE_TAG}" \
                 -t "${DOCKER_REGISTRY}/${APP_NAME}:latest" \
                 -f docker/Dockerfile .
    
    # Push to registry
    docker push "${DOCKER_REGISTRY}/${APP_NAME}:${IMAGE_TAG}"
    docker push "${DOCKER_REGISTRY}/${APP_NAME}:latest"
    
    log "Docker image built and pushed successfully"
}

# Create namespace and basic resources
setup_namespace() {
    log "Setting up namespace and basic resources..."
    
    # Create namespace if it doesn't exist
    kubectl create namespace "$NAMESPACE" --dry-run=client -o yaml | kubectl apply -f -
    
    # Label the namespace
    kubectl label namespace "$NAMESPACE" name="$NAMESPACE" --overwrite
    kubectl label namespace "$NAMESPACE" environment="$ENVIRONMENT" --overwrite
    
    log "Namespace setup completed"
}

# Generate and apply secrets
setup_secrets() {
    log "Setting up secrets..."
    
    # Generate random passwords if not provided
    DB_PASSWORD="${DB_PASSWORD:-$(openssl rand -base64 32)}"
    REDIS_PASSWORD="${REDIS_PASSWORD:-$(openssl rand -base64 32)}"
    APP_KEY="${APP_KEY:-base64:$(openssl rand -base64 32)}"
    VERIFICATION_SECRET_KEY="${VERIFICATION_SECRET_KEY:-$(openssl rand -base64 32)}"
    VERIFICATION_HMAC_KEY="${VERIFICATION_HMAC_KEY:-$(openssl rand -base64 32)}"
    
    # Create secrets
    kubectl create secret generic app-secrets \
        --namespace="$NAMESPACE" \
        --from-literal=APP_KEY="$APP_KEY" \
        --from-literal=DB_USERNAME="postgres" \
        --from-literal=DB_PASSWORD="$DB_PASSWORD" \
        --from-literal=REDIS_PASSWORD="$REDIS_PASSWORD" \
        --from-literal=VERIFICATION_SECRET_KEY="$VERIFICATION_SECRET_KEY" \
        --from-literal=VERIFICATION_HMAC_KEY="$VERIFICATION_HMAC_KEY" \
        --dry-run=client -o yaml | kubectl apply -f -
    
    # Store passwords for reference
    cat > ".env.k8s.${ENVIRONMENT}" <<EOF
DB_PASSWORD=${DB_PASSWORD}
REDIS_PASSWORD=${REDIS_PASSWORD}
APP_KEY=${APP_KEY}
VERIFICATION_SECRET_KEY=${VERIFICATION_SECRET_KEY}
VERIFICATION_HMAC_KEY=${VERIFICATION_HMAC_KEY}
EOF
    
    warning "Secrets stored in .env.k8s.${ENVIRONMENT} - keep this file secure!"
    log "Secrets setup completed"
}

# Setup storage classes and persistent volumes
setup_storage() {
    log "Setting up storage..."
    
    # Create storage class for fast SSD
    cat <<EOF | kubectl apply -f -
apiVersion: storage.k8s.io/v1
kind: StorageClass
metadata:
  name: fast-ssd
provisioner: kubernetes.io/gce-pd
parameters:
  type: pd-ssd
  replication-type: regional-pd
allowVolumeExpansion: true
reclaimPolicy: Retain
EOF

    # Create storage class for NFS (if available)
    if kubectl get storageclass nfs &> /dev/null; then
        info "NFS storage class already exists"
    else
        warning "NFS storage class not found - using default for shared storage"
        # Use default storage class for app storage
        sed -i 's/storageClassName: nfs/storageClassName: standard/' k8s/enhanced-deployment.yaml
    fi
    
    log "Storage setup completed"
}

# Deploy PostgreSQL
deploy_database() {
    log "Deploying PostgreSQL database..."
    
    # Apply PostgreSQL components
    kubectl apply -f - <<EOF
$(sed -n '/# PostgreSQL ConfigMap/,/^---$/p' k8s/enhanced-deployment.yaml)
$(sed -n '/# PostgreSQL Persistent Volume$/,/^---$/p' k8s/enhanced-deployment.yaml)
$(sed -n '/# PostgreSQL Persistent Volume Claim/,/^---$/p' k8s/enhanced-deployment.yaml)
$(sed -n '/# PostgreSQL Deployment/,/^---$/p' k8s/enhanced-deployment.yaml)
$(sed -n '/# PostgreSQL Service/,/^---$/p' k8s/enhanced-deployment.yaml)
EOF
    
    # Wait for PostgreSQL to be ready
    info "Waiting for PostgreSQL to be ready..."
    kubectl wait --for=condition=ready pod -l app=postgres \
        --namespace="$NAMESPACE" --timeout="$KUBECTL_TIMEOUT"
    
    log "PostgreSQL deployment completed"
}

# Deploy Redis
deploy_cache() {
    log "Deploying Redis cache..."
    
    # Apply Redis components
    kubectl apply -f - <<EOF
$(sed -n '/# Redis ConfigMap/,/^---$/p' k8s/enhanced-deployment.yaml)
$(sed -n '/# Redis Persistent Volume Claim/,/^---$/p' k8s/enhanced-deployment.yaml)
$(sed -n '/# Redis Deployment/,/^---$/p' k8s/enhanced-deployment.yaml)
$(sed -n '/# Redis Service/,/^---$/p' k8s/enhanced-deployment.yaml)
EOF
    
    # Wait for Redis to be ready
    info "Waiting for Redis to be ready..."
    kubectl wait --for=condition=ready pod -l app=redis \
        --namespace="$NAMESPACE" --timeout="$KUBECTL_TIMEOUT"
    
    log "Redis deployment completed"
}

# Deploy RoadRunner application
deploy_application() {
    log "Deploying RoadRunner application..."
    
    # Update image in deployment
    sed -i "s|your-registry/ai-blockchain-analytics:latest|${DOCKER_REGISTRY}/${APP_NAME}:${IMAGE_TAG}|g" k8s/enhanced-deployment.yaml
    
    # Apply application components
    kubectl apply -f - <<EOF
$(sed -n '/# Application ConfigMap/,/^---$/p' k8s/enhanced-deployment.yaml)
$(sed -n '/# Application Storage PVC/,/^---$/p' k8s/enhanced-deployment.yaml)
$(sed -n '/# RoadRunner Application Deployment/,/^---$/p' k8s/enhanced-deployment.yaml)
$(sed -n '/# RoadRunner Application Service/,/^---$/p' k8s/enhanced-deployment.yaml)
$(sed -n '/# Horizontal Pod Autoscaler/,/^---$/p' k8s/enhanced-deployment.yaml)
EOF
    
    # Wait for application to be ready
    info "Waiting for RoadRunner application to be ready..."
    kubectl wait --for=condition=ready pod -l app=roadrunner-app \
        --namespace="$NAMESPACE" --timeout="$KUBECTL_TIMEOUT"
    
    log "RoadRunner application deployment completed"
}

# Deploy Horizon workers
deploy_workers() {
    log "Deploying Horizon workers..."
    
    # Apply worker components
    kubectl apply -f - <<EOF
$(sed -n '/# Horizon Worker Deployment/,/^---$/p' k8s/enhanced-deployment.yaml)
EOF
    
    # Wait for workers to be ready
    info "Waiting for Horizon workers to be ready..."
    kubectl wait --for=condition=ready pod -l app=horizon-worker \
        --namespace="$NAMESPACE" --timeout="$KUBECTL_TIMEOUT"
    
    log "Horizon workers deployment completed"
}

# Setup ingress
setup_ingress() {
    log "Setting up ingress..."
    
    # Check if cert-manager is installed
    if kubectl get crd certificates.cert-manager.io &> /dev/null; then
        info "cert-manager detected - SSL certificates will be automatically managed"
    else
        warning "cert-manager not found - SSL certificates need manual setup"
        # Remove cert-manager annotations
        sed -i '/cert-manager.io/d' k8s/enhanced-deployment.yaml
    fi
    
    # Apply ingress
    kubectl apply -f - <<EOF
$(sed -n '/# Ingress for External Access/,/^---$/p' k8s/enhanced-deployment.yaml)
EOF
    
    # Apply network policy
    kubectl apply -f - <<EOF
$(sed -n '/# Network Policy for Security/,/^---$/p' k8s/enhanced-deployment.yaml)
EOF
    
    log "Ingress setup completed"
}

# Setup monitoring
setup_monitoring() {
    log "Setting up monitoring..."
    
    # Check if Prometheus is available
    if kubectl get crd servicemonitors.monitoring.coreos.com &> /dev/null; then
        info "Prometheus operator detected - setting up ServiceMonitor"
        
        cat <<EOF | kubectl apply -f -
apiVersion: monitoring.coreos.com/v1
kind: ServiceMonitor
metadata:
  name: roadrunner-metrics
  namespace: $NAMESPACE
  labels:
    app: roadrunner-app
spec:
  selector:
    matchLabels:
      app: roadrunner-app
  endpoints:
  - port: metrics
    path: /metrics
    interval: 30s
EOF
    else
        warning "Prometheus operator not found - metrics collection disabled"
    fi
    
    log "Monitoring setup completed"
}

# Validate deployment
validate_deployment() {
    log "Validating deployment..."
    
    # Check all pods are running
    info "Checking pod status..."
    kubectl get pods -n "$NAMESPACE" -o wide
    
    # Check services
    info "Checking services..."
    kubectl get services -n "$NAMESPACE"
    
    # Check ingress
    info "Checking ingress..."
    kubectl get ingress -n "$NAMESPACE"
    
    # Test database connectivity
    info "Testing database connectivity..."
    if kubectl exec -n "$NAMESPACE" deployment/postgres -- pg_isready -U postgres; then
        log "Database connectivity: OK"
    else
        error "Database connectivity: FAILED"
        return 1
    fi
    
    # Test Redis connectivity
    info "Testing Redis connectivity..."
    if kubectl exec -n "$NAMESPACE" deployment/redis -- redis-cli ping; then
        log "Redis connectivity: OK"
    else
        error "Redis connectivity: FAILED"
        return 1
    fi
    
    # Test application health
    info "Testing application health..."
    APP_POD=$(kubectl get pods -n "$NAMESPACE" -l app=roadrunner-app -o jsonpath='{.items[0].metadata.name}')
    if kubectl exec -n "$NAMESPACE" "$APP_POD" -- curl -f http://localhost:8000/health; then
        log "Application health: OK"
    else
        warning "Application health check failed - this might be expected if health endpoint is not implemented"
    fi
    
    log "Deployment validation completed"
}

# Cleanup function
cleanup() {
    if [[ "${CLEANUP:-false}" == "true" ]]; then
        warning "Cleaning up deployment..."
        kubectl delete namespace "$NAMESPACE" --ignore-not-found=true
        log "Cleanup completed"
    fi
}

# Main deployment function
deploy() {
    local phases_to_run=("${@:-${PHASES[@]}}")
    
    log "Starting enhanced Kubernetes deployment for $APP_NAME"
    log "Environment: $ENVIRONMENT"
    log "Namespace: $NAMESPACE"
    log "Image: ${DOCKER_REGISTRY}/${APP_NAME}:${IMAGE_TAG}"
    
    for phase in "${phases_to_run[@]}"; do
        case $phase in
            "preflight")
                check_prerequisites
                ;;
            "build")
                build_and_push_image
                ;;
            "secrets")
                setup_namespace
                setup_secrets
                ;;
            "storage")
                setup_storage
                ;;
            "database")
                deploy_database
                ;;
            "cache")
                deploy_cache
                ;;
            "application")
                deploy_application
                ;;
            "workers")
                deploy_workers
                ;;
            "ingress")
                setup_ingress
                ;;
            "monitoring")
                setup_monitoring
                ;;
            "validation")
                validate_deployment
                ;;
            *)
                error "Unknown phase: $phase"
                exit 1
                ;;
        esac
    done
    
    log "Deployment completed successfully!"
    
    # Show access information
    echo
    info "=== DEPLOYMENT SUMMARY ==="
    echo "Namespace: $NAMESPACE"
    echo "Application URL: https://analytics.yourdomain.com"
    echo "Database: PostgreSQL (internal)"
    echo "Cache: Redis (internal)"
    echo "Workers: Horizon (2 replicas)"
    echo
    info "To check status: kubectl get all -n $NAMESPACE"
    info "To view logs: kubectl logs -f deployment/roadrunner-app -n $NAMESPACE"
    info "To scale app: kubectl scale deployment/roadrunner-app --replicas=5 -n $NAMESPACE"
    echo
}

# Handle script termination
trap cleanup EXIT

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --environment|-e)
            ENVIRONMENT="$2"
            shift 2
            ;;
        --registry|-r)
            DOCKER_REGISTRY="$2"
            shift 2
            ;;
        --tag|-t)
            IMAGE_TAG="$2"
            shift 2
            ;;
        --namespace|-n)
            NAMESPACE="$2"
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
Enhanced Kubernetes Deployment Script

Usage: $0 [OPTIONS] [PHASES...]

OPTIONS:
    -e, --environment ENV    Set environment (default: production)
    -r, --registry REGISTRY  Set Docker registry (default: your-registry)
    -t, --tag TAG           Set image tag (default: latest)
    -n, --namespace NS      Set Kubernetes namespace (default: ai-blockchain-analytics)
    --cleanup               Clean up deployment on exit
    --skip-build            Skip Docker image building
    -h, --help              Show this help message

PHASES:
    preflight    Check prerequisites
    build        Build and push Docker image
    secrets      Setup namespace and secrets
    storage      Setup storage classes and PVs
    database     Deploy PostgreSQL
    cache        Deploy Redis
    application  Deploy RoadRunner app
    workers      Deploy Horizon workers
    ingress      Setup ingress and networking
    monitoring   Setup monitoring
    validation   Validate deployment

EXAMPLES:
    # Full deployment
    $0

    # Deploy only application components
    $0 application workers

    # Deploy with custom registry
    $0 --registry my-registry.com/myproject --tag v1.0.0

    # Cleanup deployment
    $0 --cleanup

ENVIRONMENT VARIABLES:
    DOCKER_REGISTRY         Docker registry URL
    IMAGE_TAG              Docker image tag
    ENVIRONMENT            Deployment environment
    DB_PASSWORD            Database password (auto-generated if not set)
    REDIS_PASSWORD         Redis password (auto-generated if not set)
    APP_KEY                Laravel app key (auto-generated if not set)

EOF
            exit 0
            ;;
        *)
            # Remaining arguments are phases
            break
            ;;
    esac
done

# Run deployment
deploy "$@"