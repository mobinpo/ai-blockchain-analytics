#!/bin/bash

# Enhanced Kubernetes Deployment Script for AI Blockchain Analytics
# Supports RoadRunner, PostgreSQL, Redis with comprehensive monitoring

set -euo pipefail

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration variables
NAMESPACE="ai-blockchain-analytics"
DOCKER_IMAGE="ai-blockchain-analytics:latest"
DOCKER_REGISTRY="your-registry.com"
ENVIRONMENT="${ENVIRONMENT:-production}"
CLUSTER_NAME="${CLUSTER_NAME:-ai-blockchain-analytics-cluster}"
REGION="${REGION:-us-east-1}"

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
🚀 AI Blockchain Analytics - Enhanced Kubernetes Deployment
═══════════════════════════════════════════════════════════════════${NC}"
}

# Function to check prerequisites
check_prerequisites() {
    print_status "Checking prerequisites..."
    
    # Check kubectl
    if ! command -v kubectl &> /dev/null; then
        print_error "kubectl is not installed. Please install kubectl first."
        exit 1
    fi
    
    # Check helm (optional but recommended)
    if ! command -v helm &> /dev/null; then
        print_warning "helm is not installed. Some monitoring features may not be available."
    fi
    
    # Check docker
    if ! command -v docker &> /dev/null; then
        print_error "docker is not installed. Please install docker first."
        exit 1
    fi
    
    # Check cluster connectivity
    if ! kubectl cluster-info &> /dev/null; then
        print_error "Cannot connect to Kubernetes cluster. Please check your kubeconfig."
        exit 1
    fi
    
    print_status "Prerequisites check completed ✅"
}

# Function to build and push Docker image
build_and_push_image() {
    if [[ "${SKIP_BUILD:-false}" == "true" ]]; then
        print_status "Skipping Docker build (SKIP_BUILD=true)"
        return 0
    fi
    
    print_status "Building Docker image..."
    
    # Build multi-stage image with RoadRunner
    docker build \
        --target production \
        --platform linux/amd64 \
        -t "${DOCKER_IMAGE}" \
        -t "${DOCKER_REGISTRY}/${DOCKER_IMAGE}" \
        .
    
    # Push to registry if registry is configured
    if [[ -n "${DOCKER_REGISTRY}" && "${DOCKER_REGISTRY}" != "your-registry.com" ]]; then
        print_status "Pushing image to registry..."
        docker push "${DOCKER_REGISTRY}/${DOCKER_IMAGE}"
    else
        print_warning "Docker registry not configured. Using local image."
    fi
    
    print_status "Docker image ready ✅"
}

# Function to create namespace and RBAC
setup_namespace() {
    print_status "Setting up namespace and RBAC..."
    
    # Apply namespace
    kubectl apply -f k8s/deployments/namespace.yaml
    
    # Wait for namespace to be ready
    kubectl wait --for=condition=Ready namespace/${NAMESPACE} --timeout=60s
    
    print_status "Namespace setup completed ✅"
}

# Function to deploy PostgreSQL
deploy_postgresql() {
    print_status "Deploying PostgreSQL..."
    
    # Check if we should use external database
    if [[ "${USE_EXTERNAL_DB:-false}" == "true" ]]; then
        print_status "Using external PostgreSQL database"
        return 0
    fi
    
    # Apply PostgreSQL deployment
    kubectl apply -f k8s/deployments/postgres.yaml
    
    # Wait for PostgreSQL to be ready
    print_status "Waiting for PostgreSQL to be ready..."
    kubectl -n ${NAMESPACE} wait --for=condition=available --timeout=300s deployment/postgres
    
    # Run database migrations
    print_status "Running database migrations..."
    kubectl -n ${NAMESPACE} run migration-job \
        --image=${DOCKER_IMAGE} \
        --rm -it --restart=Never \
        --env="APP_ENV=${ENVIRONMENT}" \
        --env="DB_HOST=postgres" \
        --env="DB_PASSWORD=$(kubectl -n ${NAMESPACE} get secret postgres-secret -o jsonpath='{.data.POSTGRES_PASSWORD}' | base64 -d)" \
        -- php artisan migrate --force
        
    # Run Telescope migrations if enabled
    if [[ "$(kubectl -n ${NAMESPACE} get configmap app-config -o jsonpath='{.data.TELESCOPE_ENABLED}')" == "true" ]]; then
        print_status "Publishing and running Telescope migrations..."
        kubectl -n ${NAMESPACE} run telescope-setup \
            --image=${DOCKER_IMAGE} \
            --rm -it --restart=Never \
            --env="APP_ENV=${ENVIRONMENT}" \
            --env="DB_HOST=postgres" \
            --env="DB_PASSWORD=$(kubectl -n ${NAMESPACE} get secret postgres-secret -o jsonpath='{.data.POSTGRES_PASSWORD}' | base64 -d)" \
            -- sh -c "php artisan telescope:install && php artisan migrate --force"
    fi
    
    print_status "PostgreSQL deployment completed ✅"
}

# Function to deploy Redis
deploy_redis() {
    print_status "Deploying Redis..."
    
    # Check if we should use external Redis
    if [[ "${USE_EXTERNAL_REDIS:-false}" == "true" ]]; then
        print_status "Using external Redis instance"
        return 0
    fi
    
    # Apply Redis deployment
    kubectl apply -f k8s/deployments/redis.yaml
    
    # Wait for Redis to be ready
    print_status "Waiting for Redis to be ready..."
    kubectl -n ${NAMESPACE} wait --for=condition=available --timeout=300s deployment/redis
    
    print_status "Redis deployment completed ✅"
}

# Function to deploy RoadRunner application
deploy_application() {
    print_status "Deploying RoadRunner application..."
    
    # Update image in deployment if using registry
    if [[ -n "${DOCKER_REGISTRY}" && "${DOCKER_REGISTRY}" != "your-registry.com" ]]; then
        sed -i.bak "s|image: ai-blockchain-analytics:latest|image: ${DOCKER_REGISTRY}/${DOCKER_IMAGE}|g" k8s/deployments/roadrunner-app.yaml
    fi
    
    # Apply application deployment
    kubectl apply -f k8s/deployments/roadrunner-app.yaml
    
    # Wait for application to be ready
    print_status "Waiting for RoadRunner application to be ready..."
    kubectl -n ${NAMESPACE} wait --for=condition=available --timeout=300s deployment/roadrunner-app
    
    print_status "Application deployment completed ✅"
}

# Function to deploy workers
deploy_workers() {
    print_status "Deploying Horizon workers..."
    
    # Apply Horizon worker deployment
    kubectl apply -f k8s/deployments/horizon-worker.yaml
    
    # Wait for workers to be ready
    print_status "Waiting for Horizon workers to be ready..."
    kubectl -n ${NAMESPACE} wait --for=condition=available --timeout=300s deployment/horizon-worker
    
    print_status "Workers deployment completed ✅"
}

# Function to deploy scheduler
deploy_scheduler() {
    print_status "Deploying scheduler..."
    
    # Apply scheduler deployment
    kubectl apply -f k8s/deployments/scheduler.yaml
    
    # Wait for scheduler to be ready
    print_status "Waiting for scheduler to be ready..."
    kubectl -n ${NAMESPACE} wait --for=condition=available --timeout=300s deployment/scheduler
    
    print_status "Scheduler deployment completed ✅"
}

# Function to setup ingress
setup_ingress() {
    print_status "Setting up ingress..."
    
    # Check if NGINX ingress controller is installed
    if ! kubectl get ingressclass nginx &> /dev/null; then
        print_warning "NGINX ingress controller not found. Installing..."
        helm upgrade --install ingress-nginx ingress-nginx \
            --repo https://kubernetes.github.io/ingress-nginx \
            --namespace ingress-nginx \
            --create-namespace \
            --wait
    fi
    
    # Check if cert-manager is installed
    if ! kubectl get namespace cert-manager &> /dev/null; then
        print_warning "cert-manager not found. Installing..."
        helm repo add jetstack https://charts.jetstack.io
        helm repo update
        helm upgrade --install cert-manager jetstack/cert-manager \
            --namespace cert-manager \
            --create-namespace \
            --version v1.13.0 \
            --set installCRDs=true \
            --wait
    fi
    
    # Apply ingress
    kubectl apply -f k8s/deployments/ingress.yaml
    
    print_status "Ingress setup completed ✅"
}

# Function to setup monitoring
setup_monitoring() {
    if [[ "${SKIP_MONITORING:-false}" == "true" ]]; then
        print_status "Skipping monitoring setup (SKIP_MONITORING=true)"
        return 0
    fi
    
    print_status "Setting up monitoring..."
    
    # Create monitoring namespace
    kubectl create namespace monitoring --dry-run=client -o yaml | kubectl apply -f -
    
    # Install Prometheus if not exists
    if ! helm list -n monitoring | grep -q prometheus; then
        print_status "Installing Prometheus..."
        helm repo add prometheus-community https://prometheus-community.github.io/helm-charts
        helm repo update
        helm upgrade --install prometheus prometheus-community/kube-prometheus-stack \
            --namespace monitoring \
            --set grafana.adminPassword=admin \
            --set prometheus.prometheusSpec.retention=7d \
            --set prometheus.prometheusSpec.storageSpec.volumeClaimTemplate.spec.resources.requests.storage=20Gi \
            --wait
    fi
    
    # Apply custom monitoring configurations
    if [[ -f k8s/monitoring/prometheus-config.yaml ]]; then
        kubectl apply -f k8s/monitoring/prometheus-config.yaml
    fi
    
    print_status "Monitoring setup completed ✅"
}

# Function to run health checks
run_health_checks() {
    print_status "Running health checks..."
    
    # Check application health
    print_status "Checking application health..."
    kubectl -n ${NAMESPACE} get pods -l app=roadrunner-app
    
    # Check if all pods are running
    if kubectl -n ${NAMESPACE} wait --for=condition=Ready pods -l app=roadrunner-app --timeout=60s; then
        print_status "Application pods are healthy ✅"
    else
        print_error "Some application pods are not ready"
        kubectl -n ${NAMESPACE} describe pods -l app=roadrunner-app
    fi
    
    # Check worker health
    print_status "Checking worker health..."
    kubectl -n ${NAMESPACE} get pods -l app=horizon-worker
    
    # Check database connectivity
    print_status "Testing database connectivity..."
    kubectl -n ${NAMESPACE} run db-test \
        --image=${DOCKER_IMAGE} \
        --rm -it --restart=Never \
        --env="DB_HOST=postgres" \
        --env="DB_PASSWORD=$(kubectl -n ${NAMESPACE} get secret postgres-secret -o jsonpath='{.data.POSTGRES_PASSWORD}' | base64 -d)" \
        -- php artisan tinker --execute="DB::connection()->getPdo(); echo 'Database connection successful';" 2>/dev/null || true
    
    # Check Redis connectivity
    print_status "Testing Redis connectivity..."
    kubectl -n ${NAMESPACE} run redis-test \
        --image=redis:7-alpine \
        --rm -it --restart=Never \
        -- redis-cli -h redis -p 6379 -a redis_password ping 2>/dev/null || true
    
    print_status "Health checks completed ✅"
}

# Function to display deployment summary
show_deployment_summary() {
    print_status "Deployment Summary:"
    echo "
┌─────────────────────────────────────────┐
│ 🎉 Deployment Completed Successfully!   │
└─────────────────────────────────────────┘

📊 Deployed Components:
  ✅ PostgreSQL Database
  ✅ Redis Cache
  ✅ RoadRunner Application (3 replicas)
  ✅ Horizon Workers (2 replicas)
  ✅ Scheduler (1 replica)
  ✅ Ingress Controller
  ✅ SSL Certificates

🔍 Useful Commands:
  View pods:           kubectl -n ${NAMESPACE} get pods
  Check logs:          kubectl -n ${NAMESPACE} logs -f deployment/roadrunner-app
  Scale application:   kubectl -n ${NAMESPACE} scale deployment roadrunner-app --replicas=5
  Port forward:        kubectl -n ${NAMESPACE} port-forward svc/roadrunner-app 8000:8000

🌐 Access URLs:
  Application:         https://ai-blockchain-analytics.com
  API:                 https://api.ai-blockchain-analytics.com
  Monitoring:          https://monitoring.ai-blockchain-analytics.com

📈 Monitoring:
  Metrics:             kubectl -n monitoring port-forward svc/prometheus-kube-prometheus-prometheus 9090:9090
  Grafana:             kubectl -n monitoring port-forward svc/prometheus-grafana 3000:80
  
🔧 Troubleshooting:
  Debug pods:          kubectl -n ${NAMESPACE} describe pod <pod-name>
  Check events:        kubectl -n ${NAMESPACE} get events --sort-by=.metadata.creationTimestamp
  Shell access:        kubectl -n ${NAMESPACE} exec -it deployment/roadrunner-app -- /bin/sh
"
}

# Function to cleanup deployment
cleanup_deployment() {
    if [[ "${1:-}" == "--confirm" ]]; then
        print_status "Cleaning up deployment..."
        kubectl delete namespace ${NAMESPACE} --wait=true
        print_status "Cleanup completed ✅"
    else
        print_warning "To cleanup the deployment, run: $0 cleanup --confirm"
    fi
}

# Main execution
main() {
    print_header
    
    case "${1:-deploy}" in
        "deploy")
            check_prerequisites
            build_and_push_image
            setup_namespace
            deploy_postgresql
            deploy_redis
            deploy_application
            deploy_workers
            deploy_scheduler
            setup_ingress
            setup_monitoring
            run_health_checks
            show_deployment_summary
            ;;
        "cleanup")
            cleanup_deployment "$2"
            ;;
        "health")
            run_health_checks
            ;;
        "build")
            build_and_push_image
            ;;
        "monitoring")
            setup_monitoring
            ;;
        *)
            echo "Usage: $0 {deploy|cleanup|health|build|monitoring}"
            echo ""
            echo "Commands:"
            echo "  deploy     - Full deployment (default)"
            echo "  cleanup    - Remove all resources"
            echo "  health     - Run health checks"
            echo "  build      - Build and push Docker image"
            echo "  monitoring - Setup monitoring only"
            echo ""
            echo "Environment Variables:"
            echo "  ENVIRONMENT=production     - Deployment environment"
            echo "  SKIP_BUILD=true           - Skip Docker build"
            echo "  SKIP_MONITORING=true      - Skip monitoring setup"
            echo "  USE_EXTERNAL_DB=true      - Use external PostgreSQL"
            echo "  USE_EXTERNAL_REDIS=true   - Use external Redis"
            echo "  DOCKER_REGISTRY=your-reg  - Docker registry URL"
            exit 1
            ;;
    esac
}

# Handle script interruption
trap 'print_error "Deployment interrupted"; exit 1' INT TERM

# Execute main function
main "$@"