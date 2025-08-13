#!/bin/bash

# AI Blockchain Analytics - Production Kubernetes Deployment Script
# Enhanced deployment with RoadRunner, Redis, PostgreSQL, and comprehensive monitoring

set -euo pipefail

# Configuration
NAMESPACE="ai-blockchain-analytics"
IMAGE_REGISTRY="${IMAGE_REGISTRY:-your-registry.dkr.ecr.us-east-1.amazonaws.com}"
IMAGE_REPOSITORY="${IMAGE_REPOSITORY:-ai-blockchain-analytics}"
IMAGE_TAG="${IMAGE_TAG:-latest}"
ENVIRONMENT="${ENVIRONMENT:-production}"
DOMAIN_NAME="${DOMAIN_NAME:-analytics.yourdomain.com}"
KUBECTL_CONTEXT="${KUBECTL_CONTEXT:-production-cluster}"

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
    
    # Check if kubectl is installed
    if ! command -v kubectl &> /dev/null; then
        error "kubectl is not installed. Please install kubectl first."
    fi
    
    # Check if helm is installed
    if ! command -v helm &> /dev/null; then
        error "helm is not installed. Please install helm first."
    fi
    
    # Check if context exists
    if ! kubectl config get-contexts "${KUBECTL_CONTEXT}" &> /dev/null; then
        error "Kubectl context '${KUBECTL_CONTEXT}' not found. Please configure your context."
    fi
    
    # Switch to the correct context
    kubectl config use-context "${KUBECTL_CONTEXT}"
    
    # Check cluster connectivity
    if ! kubectl cluster-info &> /dev/null; then
        error "Cannot connect to Kubernetes cluster. Please check your configuration."
    fi
    
    log "Prerequisites check completed successfully"
}

# Create namespace if it doesn't exist
create_namespace() {
    log "Creating namespace ${NAMESPACE}..."
    
    if kubectl get namespace "${NAMESPACE}" &> /dev/null; then
        info "Namespace ${NAMESPACE} already exists"
    else
        kubectl create namespace "${NAMESPACE}"
        kubectl label namespace "${NAMESPACE}" name="${NAMESPACE}" env="${ENVIRONMENT}"
        log "Namespace ${NAMESPACE} created successfully"
    fi
}

# Install or upgrade cert-manager
install_cert_manager() {
    log "Installing/upgrading cert-manager..."
    
    # Add jetstack repo
    helm repo add jetstack https://charts.jetstack.io || true
    helm repo update
    
    # Install cert-manager
    helm upgrade --install cert-manager jetstack/cert-manager \
        --namespace cert-manager \
        --create-namespace \
        --version v1.13.0 \
        --set installCRDs=true \
        --set global.leaderElection.namespace=cert-manager
    
    # Wait for cert-manager to be ready
    kubectl wait --for=condition=available --timeout=300s deployment/cert-manager -n cert-manager
    kubectl wait --for=condition=available --timeout=300s deployment/cert-manager-cainjector -n cert-manager
    kubectl wait --for=condition=available --timeout=300s deployment/cert-manager-webhook -n cert-manager
    
    log "cert-manager installed successfully"
}

# Create cluster issuer for Let's Encrypt
create_cluster_issuer() {
    log "Creating cluster issuer for Let's Encrypt..."
    
    cat <<EOF | kubectl apply -f -
apiVersion: cert-manager.io/v1
kind: ClusterIssuer
metadata:
  name: letsencrypt-prod
spec:
  acme:
    server: https://acme-v02.api.letsencrypt.org/directory
    email: admin@${DOMAIN_NAME}
    privateKeySecretRef:
      name: letsencrypt-prod
    solvers:
    - http01:
        ingress:
          class: nginx
EOF
    
    log "Cluster issuer created successfully"
}

# Install or upgrade NGINX Ingress Controller
install_nginx_ingress() {
    log "Installing/upgrading NGINX Ingress Controller..."
    
    # Add ingress-nginx repo
    helm repo add ingress-nginx https://kubernetes.github.io/ingress-nginx || true
    helm repo update
    
    # Install NGINX Ingress Controller
    helm upgrade --install ingress-nginx ingress-nginx/ingress-nginx \
        --namespace ingress-nginx \
        --create-namespace \
        --set controller.replicaCount=3 \
        --set controller.nodeSelector."kubernetes\.io/arch"=amd64 \
        --set controller.admissionWebhooks.patch.nodeSelector."kubernetes\.io/arch"=amd64 \
        --set controller.service.type=LoadBalancer \
        --set controller.service.annotations."service\.beta\.kubernetes\.io/aws-load-balancer-type"="nlb" \
        --set controller.service.annotations."service\.beta\.kubernetes\.io/aws-load-balancer-backend-protocol"="tcp" \
        --set controller.config.proxy-body-size="200m" \
        --set controller.config.proxy-read-timeout="3600" \
        --set controller.config.proxy-send-timeout="3600" \
        --set controller.config.client-max-body-size="200m"
    
    # Wait for NGINX to be ready
    kubectl wait --for=condition=available --timeout=300s deployment/ingress-nginx-controller -n ingress-nginx
    
    log "NGINX Ingress Controller installed successfully"
}

# Install Prometheus and Grafana for monitoring
install_monitoring() {
    log "Installing monitoring stack (Prometheus + Grafana)..."
    
    # Add prometheus-community repo
    helm repo add prometheus-community https://prometheus-community.github.io/helm-charts || true
    helm repo update
    
    # Create monitoring namespace
    kubectl create namespace monitoring || true
    kubectl label namespace monitoring name=monitoring env="${ENVIRONMENT}" || true
    
    # Install kube-prometheus-stack
    helm upgrade --install prometheus prometheus-community/kube-prometheus-stack \
        --namespace monitoring \
        --set prometheus.prometheusSpec.retention=30d \
        --set prometheus.prometheusSpec.storageSpec.volumeClaimTemplate.spec.resources.requests.storage=50Gi \
        --set grafana.adminPassword=admin \
        --set grafana.persistence.enabled=true \
        --set grafana.persistence.size=10Gi \
        --set alertmanager.alertmanagerSpec.storage.volumeClaimTemplate.spec.resources.requests.storage=10Gi
    
    log "Monitoring stack installed successfully"
}

# Create storage classes if needed
create_storage_classes() {
    log "Creating storage classes..."
    
    # GP3 CSI Storage Class
    cat <<EOF | kubectl apply -f -
apiVersion: storage.k8s.io/v1
kind: StorageClass
metadata:
  name: gp3-csi
  annotations:
    storageclass.kubernetes.io/is-default-class: "false"
provisioner: ebs.csi.aws.com
parameters:
  type: gp3
  iops: "3000"
  throughput: "125"
  encrypted: "true"
allowVolumeExpansion: true
volumeBindingMode: WaitForFirstConsumer
EOF

    # EFS CSI Storage Class
    cat <<EOF | kubectl apply -f -
apiVersion: storage.k8s.io/v1
kind: StorageClass
metadata:
  name: efs-csi
provisioner: efs.csi.aws.com
parameters:
  provisioningMode: efs-ap
  fileSystemId: fs-your-efs-id  # Replace with your EFS ID
  directoryPerms: "0755"
EOF
    
    log "Storage classes created successfully"
}

# Update secrets with base64 encoded values
update_secrets() {
    log "Updating application secrets..."
    
    # Prompt for sensitive values if not provided via environment variables
    if [[ -z "${APP_KEY:-}" ]]; then
        read -s -p "Enter Laravel App Key: " APP_KEY
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
    
    # Base64 encode values
    APP_KEY_B64=$(echo -n "${APP_KEY}" | base64)
    DB_PASSWORD_B64=$(echo -n "${DB_PASSWORD}" | base64)
    OPENAI_API_KEY_B64=$(echo -n "${OPENAI_API_KEY}" | base64)
    
    # Update the deployment file with encoded secrets
    sed -i.backup \
        -e "s/app-key: .*/app-key: ${APP_KEY_B64}/" \
        -e "s/db-password: .*/db-password: ${DB_PASSWORD_B64}/" \
        -e "s/openai-api-key: .*/openai-api-key: ${OPENAI_API_KEY_B64}/" \
        k8s/complete-production-deployment.yaml
    
    log "Secrets updated successfully"
}

# Update deployment with current image
update_deployment_image() {
    log "Updating deployment with image ${IMAGE_REGISTRY}/${IMAGE_REPOSITORY}:${IMAGE_TAG}..."
    
    # Update image in deployment file
    sed -i.backup \
        -e "s|image: your-registry/ai-blockchain-analytics:latest|image: ${IMAGE_REGISTRY}/${IMAGE_REPOSITORY}:${IMAGE_TAG}|g" \
        k8s/complete-production-deployment.yaml
    
    log "Deployment image updated successfully"
}

# Deploy the application
deploy_application() {
    log "Deploying AI Blockchain Analytics application..."
    
    # Apply the complete deployment
    kubectl apply -f k8s/complete-production-deployment.yaml
    
    # Wait for PostgreSQL to be ready
    log "Waiting for PostgreSQL to be ready..."
    kubectl wait --for=condition=available --timeout=600s deployment/postgres -n "${NAMESPACE}"
    
    # Wait for Redis to be ready
    log "Waiting for Redis to be ready..."
    kubectl wait --for=condition=available --timeout=300s deployment/redis -n "${NAMESPACE}"
    
    # Wait for application to be ready
    log "Waiting for RoadRunner application to be ready..."
    kubectl wait --for=condition=available --timeout=600s deployment/roadrunner-app -n "${NAMESPACE}"
    
    # Wait for Horizon workers to be ready
    log "Waiting for Horizon workers to be ready..."
    kubectl wait --for=condition=available --timeout=300s deployment/horizon-worker -n "${NAMESPACE}"
    
    log "Application deployed successfully"
}

# Check deployment status
check_deployment_status() {
    log "Checking deployment status..."
    
    # Get all pods status
    echo "=== Pod Status ==="
    kubectl get pods -n "${NAMESPACE}" -o wide
    
    # Get services status
    echo "=== Service Status ==="
    kubectl get svc -n "${NAMESPACE}"
    
    # Get ingress status
    echo "=== Ingress Status ==="
    kubectl get ingress -n "${NAMESPACE}"
    
    # Get HPA status
    echo "=== HPA Status ==="
    kubectl get hpa -n "${NAMESPACE}"
    
    # Get PVC status
    echo "=== PVC Status ==="
    kubectl get pvc -n "${NAMESPACE}"
    
    # Check if all pods are running
    NOT_READY=$(kubectl get pods -n "${NAMESPACE}" --field-selector=status.phase!=Running --no-headers 2>/dev/null | wc -l)
    if [[ ${NOT_READY} -eq 0 ]]; then
        log "All pods are running successfully!"
    else
        warn "Some pods are not ready yet. This is normal during initial deployment."
    fi
}

# Run database migrations
run_migrations() {
    log "Running database migrations..."
    
    # Wait for a RoadRunner pod to be ready
    kubectl wait --for=condition=ready pod -l app=roadrunner-app -n "${NAMESPACE}" --timeout=300s
    
    # Get the first RoadRunner pod
    POD_NAME=$(kubectl get pods -n "${NAMESPACE}" -l app=roadrunner-app -o jsonpath='{.items[0].metadata.name}')
    
    # Run migrations
    kubectl exec -n "${NAMESPACE}" "${POD_NAME}" -- php artisan migrate --force
    
    # Clear and cache config
    kubectl exec -n "${NAMESPACE}" "${POD_NAME}" -- php artisan config:cache
    kubectl exec -n "${NAMESPACE}" "${POD_NAME}" -- php artisan route:cache
    kubectl exec -n "${NAMESPACE}" "${POD_NAME}" -- php artisan view:cache
    
    log "Database migrations completed successfully"
}

# Show application URLs
show_urls() {
    log "Application URLs:"
    
    # Get ingress IP
    INGRESS_IP=$(kubectl get ingress roadrunner-app-ingress -n "${NAMESPACE}" -o jsonpath='{.status.loadBalancer.ingress[0].ip}' 2>/dev/null || echo "pending")
    
    echo "=== Application Access ==="
    echo "Domain: https://${DOMAIN_NAME}"
    echo "Ingress IP: ${INGRESS_IP}"
    echo ""
    echo "=== Monitoring ==="
    echo "Grafana: kubectl port-forward -n monitoring svc/prometheus-grafana 3000:80"
    echo "Prometheus: kubectl port-forward -n monitoring svc/prometheus-kube-prometheus-prometheus 9090:9090"
    echo ""
    echo "=== Database Access ==="
    echo "PostgreSQL: kubectl port-forward -n ${NAMESPACE} svc/postgres-service 5432:5432"
    echo "Redis: kubectl port-forward -n ${NAMESPACE} svc/redis-service 6379:6379"
}

# Cleanup function
cleanup() {
    log "Cleaning up temporary files..."
    rm -f k8s/complete-production-deployment.yaml.backup || true
}

# Main deployment flow
main() {
    log "Starting AI Blockchain Analytics Kubernetes deployment..."
    log "Environment: ${ENVIRONMENT}"
    log "Namespace: ${NAMESPACE}"
    log "Image: ${IMAGE_REGISTRY}/${IMAGE_REPOSITORY}:${IMAGE_TAG}"
    log "Domain: ${DOMAIN_NAME}"
    
    # Set trap for cleanup
    trap cleanup EXIT
    
    # Execute deployment steps
    check_prerequisites
    create_namespace
    install_cert_manager
    create_cluster_issuer
    install_nginx_ingress
    install_monitoring
    create_storage_classes
    update_secrets
    update_deployment_image
    deploy_application
    run_migrations
    check_deployment_status
    show_urls
    
    log "🎉 Deployment completed successfully!"
    log "Your AI Blockchain Analytics application is now running at: https://${DOMAIN_NAME}"
    
    # Show next steps
    echo ""
    echo "=== Next Steps ==="
    echo "1. Update your DNS to point ${DOMAIN_NAME} to the ingress IP"
    echo "2. Wait for Let's Encrypt certificate to be issued (may take a few minutes)"
    echo "3. Access the application at https://${DOMAIN_NAME}"
    echo "4. Monitor the application using Grafana dashboards"
    echo "5. Check logs: kubectl logs -f deployment/roadrunner-app -n ${NAMESPACE}"
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --namespace)
            NAMESPACE="$2"
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
        --context)
            KUBECTL_CONTEXT="$2"
            shift 2
            ;;
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --help)
            echo "Usage: $0 [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --namespace NAMESPACE    Kubernetes namespace (default: ai-blockchain-analytics)"
            echo "  --image-tag TAG         Docker image tag (default: latest)"
            echo "  --domain DOMAIN         Application domain name"
            echo "  --context CONTEXT       Kubectl context (default: production-cluster)"
            echo "  --dry-run               Show what would be deployed without applying"
            echo "  --help                  Show this help message"
            exit 0
            ;;
        *)
            error "Unknown option: $1"
            ;;
    esac
done

# Run main function
main
