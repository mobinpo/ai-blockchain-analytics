#!/bin/bash
set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
NAMESPACE="ai-blockchain-analytics"
DOCKER_REGISTRY="${DOCKER_REGISTRY:-your-registry.com}"
IMAGE_TAG="${IMAGE_TAG:-latest}"
KUBECONFIG="${KUBECONFIG:-~/.kube/config}"

echo -e "${BLUE}🚀 Deploying AI Blockchain Analytics to Kubernetes${NC}"
echo -e "${BLUE}===============================================${NC}"

# Function to print status
print_status() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Check prerequisites
echo -e "${YELLOW}🔍 Checking prerequisites...${NC}"

# Check if kubectl is available
if ! command -v kubectl &> /dev/null; then
    print_error "kubectl is not installed or not in PATH"
    exit 1
fi

# Check if docker is available
if ! command -v docker &> /dev/null; then
    print_error "docker is not installed or not in PATH"
    exit 1
fi

# Check if kubeconfig is accessible
if ! kubectl cluster-info &> /dev/null; then
    print_error "Cannot connect to Kubernetes cluster. Check your kubeconfig."
    exit 1
fi

print_status "Prerequisites check passed"

# Build and push Docker image
echo -e "${YELLOW}🔨 Building and pushing Docker image...${NC}"
docker build -f Dockerfile.production -t "${DOCKER_REGISTRY}/ai-blockchain-analytics:${IMAGE_TAG}" .
docker push "${DOCKER_REGISTRY}/ai-blockchain-analytics:${IMAGE_TAG}"
print_status "Docker image built and pushed"

# Update image references in manifests
echo -e "${YELLOW}🔄 Updating image references...${NC}"
find k8s/ -name "*.yaml" -exec sed -i "s|ai-blockchain-analytics:latest|${DOCKER_REGISTRY}/ai-blockchain-analytics:${IMAGE_TAG}|g" {} \;
print_status "Image references updated"

# Create namespace
echo -e "${YELLOW}📦 Creating namespace...${NC}"
kubectl apply -f k8s/namespace.yaml
print_status "Namespace created/updated"

# Deploy storage classes and PVCs
echo -e "${YELLOW}💾 Setting up storage...${NC}"
kubectl apply -f k8s/app/pvc.yaml
kubectl apply -f k8s/postgres/pvc.yaml
kubectl apply -f k8s/redis/pvc.yaml
print_status "Storage configured"

# Deploy PostgreSQL
echo -e "${YELLOW}🐘 Deploying PostgreSQL...${NC}"
kubectl apply -f k8s/postgres/configmap.yaml
kubectl apply -f k8s/postgres/deployment.yaml
kubectl apply -f k8s/postgres/service.yaml

# Wait for PostgreSQL to be ready
echo -e "${YELLOW}⏳ Waiting for PostgreSQL to be ready...${NC}"
kubectl wait --for=condition=available --timeout=300s deployment/postgres -n $NAMESPACE
print_status "PostgreSQL deployed and ready"

# Deploy Redis
echo -e "${YELLOW}🔴 Deploying Redis...${NC}"
kubectl apply -f k8s/redis/configmap.yaml
kubectl apply -f k8s/redis/deployment.yaml
kubectl apply -f k8s/redis/service.yaml

# Wait for Redis to be ready
echo -e "${YELLOW}⏳ Waiting for Redis to be ready...${NC}"
kubectl wait --for=condition=available --timeout=300s deployment/redis -n $NAMESPACE
print_status "Redis deployed and ready"

# Deploy application secrets and config
echo -e "${YELLOW}🔐 Deploying secrets and configuration...${NC}"
print_warning "Please update secrets in k8s/app/secret.yaml before deploying to production!"
kubectl apply -f k8s/app/secret.yaml
kubectl apply -f k8s/app/configmap.yaml
print_status "Secrets and configuration deployed"

# Deploy application
echo -e "${YELLOW}🚀 Deploying application...${NC}"
kubectl apply -f k8s/app/deployment.yaml
kubectl apply -f k8s/app/service.yaml

# Wait for application to be ready
echo -e "${YELLOW}⏳ Waiting for application to be ready...${NC}"
kubectl wait --for=condition=available --timeout=600s deployment/ai-blockchain-analytics-app -n $NAMESPACE
print_status "Application deployed and ready"

# Deploy ingress
echo -e "${YELLOW}🌐 Setting up ingress...${NC}"
print_warning "Please update hostnames and certificates in k8s/app/ingress.yaml"
kubectl apply -f k8s/app/ingress.yaml
print_status "Ingress configured"

# Run database migrations
echo -e "${YELLOW}🔄 Running database migrations...${NC}"
kubectl exec -n $NAMESPACE deployment/ai-blockchain-analytics-app -- php artisan migrate --force
print_status "Database migrations completed"

# Clear and warm caches
echo -e "${YELLOW}🔥 Optimizing application...${NC}"
kubectl exec -n $NAMESPACE deployment/ai-blockchain-analytics-app -- php artisan config:cache
kubectl exec -n $NAMESPACE deployment/ai-blockchain-analytics-app -- php artisan route:cache
kubectl exec -n $NAMESPACE deployment/ai-blockchain-analytics-app -- php artisan view:cache
kubectl exec -n $NAMESPACE deployment/ai-blockchain-analytics-app -- php artisan cache:maintenance --warm
print_status "Application optimized"

# Display deployment status
echo -e "${BLUE}📊 Deployment Status${NC}"
echo -e "${BLUE}===================${NC}"
kubectl get pods -n $NAMESPACE
echo ""
kubectl get services -n $NAMESPACE
echo ""
kubectl get ingress -n $NAMESPACE

# Get application URL
INGRESS_IP=$(kubectl get ingress ai-blockchain-analytics-ingress -n $NAMESPACE -o jsonpath='{.status.loadBalancer.ingress[0].ip}')
if [ -n "$INGRESS_IP" ]; then
    echo -e "${GREEN}🌍 Application available at: http://$INGRESS_IP${NC}"
else
    echo -e "${YELLOW}⏳ Ingress IP not yet assigned. Check with: kubectl get ingress -n $NAMESPACE${NC}"
fi

# Show useful commands
echo -e "${BLUE}📝 Useful Commands${NC}"
echo -e "${BLUE}=================${NC}"
echo "View logs: kubectl logs -f deployment/ai-blockchain-analytics-app -n $NAMESPACE"
echo "Scale app: kubectl scale deployment ai-blockchain-analytics-app --replicas=5 -n $NAMESPACE"
echo "Port forward: kubectl port-forward service/ai-blockchain-analytics-service 8080:80 -n $NAMESPACE"
echo "Execute commands: kubectl exec -it deployment/ai-blockchain-analytics-app -n $NAMESPACE -- bash"
echo "Monitor resources: kubectl top pods -n $NAMESPACE"

print_status "Deployment completed successfully! 🎉"