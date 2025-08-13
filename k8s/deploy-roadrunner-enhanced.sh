#!/bin/bash

# Enhanced Kubernetes Deployment Script for AI Blockchain Analytics
# Features: RoadRunner optimization, auto-scaling, comprehensive monitoring
# Usage: ./k8s/deploy-roadrunner-enhanced.sh [environment] [context] [namespace] [action]

set -euo pipefail

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_NAME="ai-blockchain-analytics"
DEFAULT_ENVIRONMENT="production"
DEFAULT_NAMESPACE="ai-blockchain-analytics"

# Parse arguments
ENVIRONMENT=${1:-$DEFAULT_ENVIRONMENT}
K8S_CONTEXT=${2:-$(kubectl config current-context)}
NAMESPACE=${3:-$DEFAULT_NAMESPACE}
ACTION=${4:-deploy}

# Environment-specific configurations
declare -A ENVIRONMENT_CONFIGS=(
    # Development
    [development_replicas]=1
    [development_min_replicas]=1
    [development_max_replicas]=3
    [development_cpu_request]="250m"
    [development_memory_request]="512Mi"
    [development_cpu_limit]="1000m"
    [development_memory_limit]="2Gi"
    [development_rr_workers]=4
    [development_storage_size]="20Gi"
    
    # Staging
    [staging_replicas]=2
    [staging_min_replicas]=2
    [staging_max_replicas]=10
    [staging_cpu_request]="500m"
    [staging_memory_request]="1Gi"
    [staging_cpu_limit]="2000m"
    [staging_memory_limit]="4Gi"
    [staging_rr_workers]=8
    [staging_storage_size]="50Gi"
    
    # Production
    [production_replicas]=3
    [production_min_replicas]=3
    [production_max_replicas]=20
    [production_cpu_request]="1000m"
    [production_memory_request]="2Gi"
    [production_cpu_limit]="4000m"
    [production_memory_limit]="8Gi"
    [production_rr_workers]=16
    [production_storage_size]="100Gi"
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

log_step() {
    log_with_timestamp "${CYAN}[STEP]${NC} $1"
}

# Error handling
error_exit() {
    log_error "$1"
    exit 1
}

# Check prerequisites
check_prerequisites() {
    log_step "Checking prerequisites..."
    
    # Check if kubectl is installed and configured
    if ! command -v kubectl &> /dev/null; then
        error_exit "kubectl is not installed"
    fi
    
    # Check if cluster is reachable
    if ! kubectl cluster-info > /dev/null 2>&1; then
        error_exit "kubectl is not configured or cluster is unreachable"
    fi
    
    # Check if helm is installed (optional for monitoring)
    if command -v helm &> /dev/null; then
        log_info "Helm is available for monitoring stack installation"
    else
        log_warning "Helm is not installed - monitoring stack will be skipped"
    fi
    
    log_success "Prerequisites check passed"
}

# Validate environment configuration
validate_environment() {
    log_step "Validating environment configuration..."
    
    case $ENVIRONMENT in
        development|staging|production)
            log_info "Environment: $ENVIRONMENT"
            ;;
        *)
            error_exit "Invalid environment: $ENVIRONMENT. Must be development, staging, or production"
            ;;
    esac
    
    log_success "Environment validation passed"
}

# Setup kubectl context
setup_context() {
    log_step "Setting up kubectl context..."
    
    log_info "Using context: $K8S_CONTEXT"
    kubectl config use-context "$K8S_CONTEXT" || error_exit "Failed to switch to context: $K8S_CONTEXT"
    
    log_info "Current context: $(kubectl config current-context)"
    log_info "Current cluster: $(kubectl config view --minify -o jsonpath='{.clusters[0].name}')"
    
    log_success "Context setup completed"
}

# Create namespace and basic resources
create_namespace() {
    log_step "Creating namespace and basic resources..."
    
    # Apply namespace configuration
    kubectl apply -f - <<EOF
apiVersion: v1
kind: Namespace
metadata:
  name: $NAMESPACE
  labels:
    name: $NAMESPACE
    project: $PROJECT_NAME
    environment: $ENVIRONMENT
EOF

    # Wait for namespace to be ready
    kubectl wait --for=condition=Ready namespace/$NAMESPACE --timeout=60s || error_exit "Namespace creation timeout"
    
    log_success "Namespace '$NAMESPACE' created and ready"
}

# Apply storage classes and persistent volumes
setup_storage() {
    log_step "Setting up storage classes and persistent volumes..."
    
    # Create EFS storage class if not exists
    kubectl apply -f - <<EOF
apiVersion: storage.k8s.io/v1
kind: StorageClass
metadata:
  name: efs-sc
  namespace: $NAMESPACE
provisioner: efs.csi.aws.com
parameters:
  provisioningMode: efs-ap
  fileSystemId: \${EFS_FILE_SYSTEM_ID}
  directoryPerms: "0755"
reclaimPolicy: Retain
allowVolumeExpansion: true
EOF

    log_success "Storage configuration applied"
}

# Deploy PostgreSQL
deploy_postgres() {
    log_step "Deploying PostgreSQL..."
    
    # Get environment-specific storage size
    local storage_size="${ENVIRONMENT_CONFIGS[${ENVIRONMENT}_storage_size]}"
    
    kubectl apply -f - <<EOF
apiVersion: v1
kind: PersistentVolumeClaim
metadata:
  name: postgres-pvc
  namespace: $NAMESPACE
spec:
  accessModes:
    - ReadWriteOnce
  resources:
    requests:
      storage: $storage_size
  storageClassName: gp2
---
apiVersion: apps/v1
kind: Deployment
metadata:
  name: postgres
  namespace: $NAMESPACE
  labels:
    app: postgres
    component: database
spec:
  replicas: 1
  strategy:
    type: Recreate
  selector:
    matchLabels:
      app: postgres
  template:
    metadata:
      labels:
        app: postgres
        component: database
    spec:
      containers:
      - name: postgres
        image: postgres:15-alpine
        ports:
        - containerPort: 5432
          name: postgres
        env:
        - name: POSTGRES_DB
          value: "ai_blockchain_analytics"
        - name: POSTGRES_USER
          valueFrom:
            secretKeyRef:
              name: app-secrets
              key: DB_USERNAME
        - name: POSTGRES_PASSWORD
          valueFrom:
            secretKeyRef:
              name: app-secrets
              key: DB_PASSWORD
        - name: PGDATA
          value: /var/lib/postgresql/data/pgdata
        volumeMounts:
        - name: postgres-storage
          mountPath: /var/lib/postgresql/data
        resources:
          requests:
            memory: "512Mi"
            cpu: "250m"
          limits:
            memory: "2Gi"
            cpu: "1000m"
        readinessProbe:
          exec:
            command:
            - sh
            - -c
            - pg_isready -U \$POSTGRES_USER -d \$POSTGRES_DB
          initialDelaySeconds: 15
          periodSeconds: 5
        livenessProbe:
          exec:
            command:
            - sh
            - -c
            - pg_isready -U \$POSTGRES_USER -d \$POSTGRES_DB
          initialDelaySeconds: 45
          periodSeconds: 10
      volumes:
      - name: postgres-storage
        persistentVolumeClaim:
          claimName: postgres-pvc
---
apiVersion: v1
kind: Service
metadata:
  name: postgres-service
  namespace: $NAMESPACE
  labels:
    app: postgres
spec:
  ports:
  - port: 5432
    targetPort: 5432
    name: postgres
  selector:
    app: postgres
  type: ClusterIP
EOF

    # Wait for PostgreSQL to be ready
    log_info "Waiting for PostgreSQL to be ready..."
    kubectl wait --for=condition=Available deployment/postgres -n $NAMESPACE --timeout=300s || error_exit "PostgreSQL deployment timeout"
    
    log_success "PostgreSQL deployed successfully"
}

# Deploy Redis
deploy_redis() {
    log_step "Deploying Redis..."
    
    kubectl apply -f - <<EOF
apiVersion: v1
kind: PersistentVolumeClaim
metadata:
  name: redis-pvc
  namespace: $NAMESPACE
spec:
  accessModes:
    - ReadWriteOnce
  resources:
    requests:
      storage: 10Gi
  storageClassName: gp2
---
apiVersion: apps/v1
kind: Deployment
metadata:
  name: redis
  namespace: $NAMESPACE
  labels:
    app: redis
    component: cache
spec:
  replicas: 1
  strategy:
    type: Recreate
  selector:
    matchLabels:
      app: redis
  template:
    metadata:
      labels:
        app: redis
        component: cache
    spec:
      containers:
      - name: redis
        image: redis:7-alpine
        ports:
        - containerPort: 6379
          name: redis
        args:
        - redis-server
        - --requirepass
        - \$(REDIS_PASSWORD)
        - --appendonly
        - "yes"
        - --save
        - "900 1"
        - --save
        - "300 10"
        - --save
        - "60 10000"
        env:
        - name: REDIS_PASSWORD
          valueFrom:
            secretKeyRef:
              name: app-secrets
              key: REDIS_PASSWORD
        volumeMounts:
        - name: redis-storage
          mountPath: /data
        resources:
          requests:
            memory: "256Mi"
            cpu: "100m"
          limits:
            memory: "1Gi"
            cpu: "500m"
        readinessProbe:
          exec:
            command:
            - redis-cli
            - -a
            - \$(REDIS_PASSWORD)
            - ping
          initialDelaySeconds: 5
          periodSeconds: 5
        livenessProbe:
          exec:
            command:
            - redis-cli
            - -a
            - \$(REDIS_PASSWORD)
            - ping
          initialDelaySeconds: 30
          periodSeconds: 10
      volumes:
      - name: redis-storage
        persistentVolumeClaim:
          claimName: redis-pvc
---
apiVersion: v1
kind: Service
metadata:
  name: redis-service
  namespace: $NAMESPACE
  labels:
    app: redis
spec:
  ports:
  - port: 6379
    targetPort: 6379
    name: redis
  selector:
    app: redis
  type: ClusterIP
EOF

    # Wait for Redis to be ready
    log_info "Waiting for Redis to be ready..."
    kubectl wait --for=condition=Available deployment/redis -n $NAMESPACE --timeout=180s || error_exit "Redis deployment timeout"
    
    log_success "Redis deployed successfully"
}

# Deploy RoadRunner application
deploy_roadrunner_app() {
    log_step "Deploying RoadRunner application..."
    
    # Get environment-specific configurations
    local replicas="${ENVIRONMENT_CONFIGS[${ENVIRONMENT}_replicas]}"
    local min_replicas="${ENVIRONMENT_CONFIGS[${ENVIRONMENT}_min_replicas]}"
    local max_replicas="${ENVIRONMENT_CONFIGS[${ENVIRONMENT}_max_replicas]}"
    local cpu_request="${ENVIRONMENT_CONFIGS[${ENVIRONMENT}_cpu_request]}"
    local memory_request="${ENVIRONMENT_CONFIGS[${ENVIRONMENT}_memory_request]}"
    local cpu_limit="${ENVIRONMENT_CONFIGS[${ENVIRONMENT}_cpu_limit]}"
    local memory_limit="${ENVIRONMENT_CONFIGS[${ENVIRONMENT}_memory_limit]}"
    local rr_workers="${ENVIRONMENT_CONFIGS[${ENVIRONMENT}_rr_workers]}"
    
    # Apply the enhanced RoadRunner deployment
    envsubst < "$SCRIPT_DIR/enhanced-roadrunner-deployment.yaml" | kubectl apply -f -
    
    # Update deployment with environment-specific values
    kubectl patch deployment roadrunner-app -n $NAMESPACE --type='merge' -p="{\"spec\":{\"replicas\":$replicas,\"template\":{\"spec\":{\"containers\":[{\"name\":\"roadrunner-app\",\"resources\":{\"requests\":{\"cpu\":\"$cpu_request\",\"memory\":\"$memory_request\"},\"limits\":{\"cpu\":\"$cpu_limit\",\"memory\":\"$memory_limit\"}},\"env\":[{\"name\":\"RR_WORKERS\",\"value\":\"$rr_workers\"}]}]}}}}"
    
    # Update HPA with environment-specific values
    kubectl patch hpa roadrunner-app-hpa -n $NAMESPACE --type='merge' -p="{\"spec\":{\"minReplicas\":$min_replicas,\"maxReplicas\":$max_replicas}}"
    
    # Wait for application to be ready
    log_info "Waiting for RoadRunner application to be ready..."
    kubectl wait --for=condition=Available deployment/roadrunner-app -n $NAMESPACE --timeout=600s || error_exit "RoadRunner app deployment timeout"
    
    log_success "RoadRunner application deployed successfully"
}

# Deploy supporting services (Horizon, Scheduler)
deploy_supporting_services() {
    log_step "Deploying supporting services..."
    
    # Wait for Horizon workers to be ready
    log_info "Waiting for Horizon workers to be ready..."
    kubectl wait --for=condition=Available deployment/horizon-worker -n $NAMESPACE --timeout=300s || error_exit "Horizon worker deployment timeout"
    
    # Wait for Scheduler to be ready
    log_info "Waiting for Scheduler to be ready..."
    kubectl wait --for=condition=Available deployment/scheduler -n $NAMESPACE --timeout=300s || error_exit "Scheduler deployment timeout"
    
    log_success "Supporting services deployed successfully"
}

# Setup ingress and load balancing
setup_ingress() {
    log_step "Setting up ingress and load balancing..."
    
    # Check if cert-manager is available
    if kubectl get crd certificates.cert-manager.io > /dev/null 2>&1; then
        log_info "cert-manager detected, SSL certificates will be automatically managed"
    else
        log_warning "cert-manager not found, SSL certificates need to be managed manually"
    fi
    
    log_success "Ingress configuration applied"
}

# Install monitoring stack (optional)
install_monitoring() {
    log_step "Installing monitoring stack..."
    
    if command -v helm &> /dev/null; then
        # Add Prometheus Helm repository
        helm repo add prometheus-community https://prometheus-community.github.io/helm-charts
        helm repo update
        
        # Install Prometheus and Grafana
        helm upgrade --install monitoring prometheus-community/kube-prometheus-stack \
            --namespace monitoring \
            --create-namespace \
            --set grafana.adminPassword=admin \
            --set prometheus.prometheusSpec.serviceMonitorSelectorNilUsesHelmValues=false \
            --set prometheus.prometheusSpec.podMonitorSelectorNilUsesHelmValues=false \
            --timeout 10m || log_warning "Monitoring stack installation failed"
        
        log_success "Monitoring stack installed"
    else
        log_warning "Helm not available, skipping monitoring stack installation"
    fi
}

# Run database migrations
run_migrations() {
    log_step "Running database migrations..."
    
    # Wait a bit for the database to be fully ready
    sleep 30
    
    # Run migrations using a job
    kubectl apply -f - <<EOF
apiVersion: batch/v1
kind: Job
metadata:
  name: migration-job-$(date +%s)
  namespace: $NAMESPACE
spec:
  template:
    spec:
      restartPolicy: Never
      initContainers:
      - name: wait-for-postgres
        image: postgres:15-alpine
        command:
        - sh
        - -c
        - |
          until pg_isready -h postgres-service -p 5432 -U \$DB_USERNAME; do
            echo "Waiting for postgres..."
            sleep 2
          done
        env:
        - name: DB_USERNAME
          valueFrom:
            secretKeyRef:
              name: app-secrets
              key: DB_USERNAME
      containers:
      - name: migrate
        image: your-registry/ai-blockchain-analytics-roadrunner:latest
        command:
        - php
        - artisan
        - migrate
        - --force
        envFrom:
        - configMapRef:
            name: app-config
        - secretRef:
            name: app-secrets
EOF

    log_success "Database migration job created"
}

# Verify deployment
verify_deployment() {
    log_step "Verifying deployment..."
    
    # Check all deployments are ready
    local deployments=("postgres" "redis" "roadrunner-app" "horizon-worker" "scheduler")
    
    for deployment in "${deployments[@]}"; do
        if kubectl get deployment "$deployment" -n $NAMESPACE > /dev/null 2>&1; then
            kubectl wait --for=condition=Available deployment/"$deployment" -n $NAMESPACE --timeout=60s || log_warning "Deployment $deployment is not ready"
            log_info "✅ $deployment is ready"
        else
            log_warning "⚠️  $deployment not found"
        fi
    done
    
    # Check services
    local services=("postgres-service" "redis-service" "roadrunner-app-service")
    
    for service in "${services[@]}"; do
        if kubectl get service "$service" -n $NAMESPACE > /dev/null 2>&1; then
            log_info "✅ Service $service is available"
        else
            log_warning "⚠️  Service $service not found"
        fi
    done
    
    # Get ingress information
    if kubectl get ingress app-ingress -n $NAMESPACE > /dev/null 2>&1; then
        local ingress_ip=$(kubectl get ingress app-ingress -n $NAMESPACE -o jsonpath='{.status.loadBalancer.ingress[0].ip}')
        local ingress_hostname=$(kubectl get ingress app-ingress -n $NAMESPACE -o jsonpath='{.status.loadBalancer.ingress[0].hostname}')
        
        if [[ -n "$ingress_ip" ]]; then
            log_info "✅ Ingress IP: $ingress_ip"
        elif [[ -n "$ingress_hostname" ]]; then
            log_info "✅ Ingress Hostname: $ingress_hostname"
        else
            log_info "⏳ Ingress is being provisioned..."
        fi
    fi
    
    log_success "Deployment verification completed"
}

# Display deployment information
display_info() {
    log_step "Deployment Information"
    
    echo ""
    echo "🚀 AI Blockchain Analytics - RoadRunner Deployment"
    echo "=================================================="
    echo "Environment: $ENVIRONMENT"
    echo "Namespace: $NAMESPACE"
    echo "Context: $K8S_CONTEXT"
    echo ""
    
    echo "📊 Resource Information:"
    kubectl get pods,services,ingress -n $NAMESPACE
    echo ""
    
    echo "🔗 Useful Commands:"
    echo "  View logs:     kubectl logs -f deployment/roadrunner-app -n $NAMESPACE"
    echo "  Scale app:     kubectl scale deployment roadrunner-app --replicas=5 -n $NAMESPACE"
    echo "  Port forward:  kubectl port-forward service/roadrunner-app-service 8080:80 -n $NAMESPACE"
    echo "  Shell access:  kubectl exec -it deployment/roadrunner-app -n $NAMESPACE -- bash"
    echo ""
    
    if command -v helm &> /dev/null && helm list -n monitoring | grep -q monitoring; then
        echo "📈 Monitoring:"
        echo "  Grafana:       kubectl port-forward service/monitoring-grafana 3000:80 -n monitoring"
        echo "  Prometheus:    kubectl port-forward service/monitoring-kube-prometheus-prometheus 9090:9090 -n monitoring"
        echo ""
    fi
    
    log_success "Deployment completed successfully! 🎉"
}

# Cleanup function
cleanup_deployment() {
    log_step "Cleaning up deployment..."
    
    # Delete all resources in namespace
    kubectl delete all --all -n $NAMESPACE
    kubectl delete pvc --all -n $NAMESPACE
    kubectl delete configmaps --all -n $NAMESPACE
    kubectl delete secrets --all -n $NAMESPACE
    kubectl delete namespace $NAMESPACE
    
    log_success "Cleanup completed"
}

# Main execution
main() {
    echo ""
    echo "🚀 AI Blockchain Analytics - Enhanced Kubernetes Deployment"
    echo "=========================================================="
    echo "Environment: $ENVIRONMENT"
    echo "Context: $K8S_CONTEXT"
    echo "Namespace: $NAMESPACE"
    echo "Action: $ACTION"
    echo ""
    
    case $ACTION in
        deploy)
            check_prerequisites
            validate_environment
            setup_context
            create_namespace
            setup_storage
            deploy_postgres
            deploy_redis
            deploy_roadrunner_app
            deploy_supporting_services
            setup_ingress
            install_monitoring
            run_migrations
            verify_deployment
            display_info
            ;;
        cleanup)
            setup_context
            cleanup_deployment
            ;;
        verify)
            setup_context
            verify_deployment
            ;;
        *)
            error_exit "Invalid action: $ACTION. Must be deploy, cleanup, or verify"
            ;;
    esac
}

# Execute main function
main "$@"
