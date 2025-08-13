#!/bin/bash

# Enhanced AI Blockchain Analytics Kubernetes Deployment Script
# Features: RoadRunner optimization, advanced monitoring, security hardening
# Usage: ./deploy-enhanced.sh [environment] [component] [action]

set -euo pipefail

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_NAME="ai-blockchain-analytics"
NAMESPACE="ai-blockchain-analytics"
REGISTRY="${REGISTRY:-your-registry}"
HELM_TIMEOUT="${HELM_TIMEOUT:-600s}"
BUILD_CONTEXT="${BUILD_CONTEXT:-../}"

# Performance and resource settings
declare -A ENVIRONMENT_SETTINGS=(
    [development_replicas]=1
    [development_cpu_request]=250m
    [development_memory_request]=512Mi
    [development_cpu_limit]=1000m
    [development_memory_limit]=2Gi
    [development_rr_workers]=4
    
    [staging_replicas]=2
    [staging_cpu_request]=500m
    [staging_memory_request]=1Gi
    [staging_cpu_limit]=1500m
    [staging_memory_limit]=3Gi
    [staging_rr_workers]=8
    
    [production_replicas]=3
    [production_cpu_request]=1000m
    [production_memory_request]=2Gi
    [production_cpu_limit]=2000m
    [production_memory_limit]=4Gi
    [production_rr_workers]=12
)

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

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
    local duration=$1
    local message=$2
    
    echo -n "$message"
    for ((i=0; i<duration; i++)); do
        echo -n "."
        sleep 1
    done
    echo " Done!"
}

# Check if required tools are installed
check_dependencies() {
    log_step "Checking dependencies..."
    
    local deps=("kubectl" "docker" "helm" "jq" "curl")
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
    log_debug "kubectl version: $(kubectl version --client --short)"
    log_debug "docker version: $(docker --version)"
    log_debug "helm version: $(helm version --short)"
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

# Check kubectl context and cluster connectivity
check_kubectl_context() {
    log_step "Checking kubectl context and cluster connectivity..."
    
    local current_context
    current_context=$(kubectl config current-context 2>/dev/null || echo "none")
    log_info "Current kubectl context: $current_context"
    
    if [[ "$current_context" == "none" ]]; then
        log_error "No kubectl context set. Please configure kubectl and try again."
        exit 1
    fi
    
    # Verify cluster connectivity with timeout
    if ! timeout 30 kubectl cluster-info &> /dev/null; then
        log_error "Cannot connect to Kubernetes cluster or connection timeout"
        exit 1
    fi
    
    # Check cluster version
    local k8s_version
    k8s_version=$(kubectl version --output=json 2>/dev/null | jq -r '.serverVersion.gitVersion' || echo "unknown")
    log_info "Kubernetes cluster version: $k8s_version"
    
    # Check if we have sufficient permissions
    if ! kubectl auth can-i create deployments --namespace="$NAMESPACE" &> /dev/null; then
        log_warning "May not have sufficient permissions to create deployments in namespace $NAMESPACE"
    fi
    
    log_success "Kubectl context and cluster connectivity verified"
}

# Build optimized Docker image with multi-stage build
build_and_push_image() {
    local environment=$1
    local tag=${2:-latest}
    local context_path=${3:-$BUILD_CONTEXT}
    
    log_step "Building optimized Docker image for $environment environment..."
    
    # Generate build ID for tracking
    local build_id="build-$(date +%Y%m%d%H%M%S)-$(git rev-parse --short HEAD 2>/dev/null || echo 'no-git')"
    local full_tag="$tag-$environment"
    
    # Build arguments for optimization
    local build_args=(
        "--target=production"
        "--build-arg=BUILD_ENV=$environment"
        "--build-arg=BUILD_ID=$build_id"
        "--build-arg=NODE_ENV=production"
        "--build-arg=PHP_OPCACHE_VALIDATE_TIMESTAMPS=0"
        "--build-arg=ROADRUNNER_VERSION=2023.3.7"
        "--build-arg=PHP_VERSION=8.3"
        "--label=org.opencontainers.image.created=$(date -u +'%Y-%m-%dT%H:%M:%SZ')"
        "--label=org.opencontainers.image.source=https://github.com/your-repo/ai-blockchain-analytics"
        "--label=org.opencontainers.image.version=$tag"
        "--label=org.opencontainers.image.revision=$(git rev-parse HEAD 2>/dev/null || echo 'unknown')"
        "--label=build.environment=$environment"
        "--label=build.id=$build_id"
    )
    
    # Use BuildKit for improved caching and performance
    export DOCKER_BUILDKIT=1
    export BUILDKIT_PROGRESS=plain
    
    # Build with cache mount for dependencies
    docker build \
        "${build_args[@]}" \
        --cache-from="$REGISTRY/$PROJECT_NAME:cache-$environment" \
        --tag="$PROJECT_NAME:$full_tag" \
        --tag="$REGISTRY/$PROJECT_NAME:$full_tag" \
        --tag="$REGISTRY/$PROJECT_NAME:latest-$environment" \
        "$context_path"
    
    log_success "Docker image built successfully: $PROJECT_NAME:$full_tag"
    
    # Security scan if available
    if command -v docker &> /dev/null && docker version --format '{{.Server.Experimental}}' 2>/dev/null | grep -q true; then
        log_info "Running security scan..."
        docker scout cves "$PROJECT_NAME:$full_tag" --format sarif --output /tmp/docker-scout-$build_id.sarif || log_warning "Security scan failed or not available"
    fi
    
    # Push images
    log_info "Pushing images to registry..."
    docker push "$REGISTRY/$PROJECT_NAME:$full_tag"
    docker push "$REGISTRY/$PROJECT_NAME:latest-$environment"
    
    # Save cache layer
    docker tag "$PROJECT_NAME:$full_tag" "$REGISTRY/$PROJECT_NAME:cache-$environment"
    docker push "$REGISTRY/$PROJECT_NAME:cache-$environment" || log_warning "Failed to push cache layer"
    
    log_success "Images pushed to registry successfully"
    
    # Return the full image URI
    echo "$REGISTRY/$PROJECT_NAME:$full_tag"
}

# Create namespace with enhanced labels and annotations
ensure_namespace() {
    log_step "Ensuring namespace exists with proper configuration..."
    
    if kubectl get namespace "$NAMESPACE" &> /dev/null; then
        log_info "Namespace $NAMESPACE already exists"
        # Update labels if needed
        kubectl label namespace "$NAMESPACE" \
            app.kubernetes.io/name="$PROJECT_NAME" \
            app.kubernetes.io/instance="$PROJECT_NAME" \
            app.kubernetes.io/managed-by="deploy-script" \
            --overwrite
    else
        log_info "Creating namespace $NAMESPACE..."
        
        # Create namespace with comprehensive labels and annotations
        cat <<EOF | kubectl apply -f -
apiVersion: v1
kind: Namespace
metadata:
  name: $NAMESPACE
  labels:
    app.kubernetes.io/name: $PROJECT_NAME
    app.kubernetes.io/instance: $PROJECT_NAME
    app.kubernetes.io/managed-by: deploy-script
    name: $NAMESPACE
    monitoring: enabled
    network-policy: enabled
  annotations:
    deployment.kubernetes.io/created-by: deploy-enhanced.sh
    deployment.kubernetes.io/created-at: "$(date -u +'%Y-%m-%dT%H:%M:%SZ')"
    deployment.kubernetes.io/version: "2.0"
EOF
        log_success "Namespace created with enhanced configuration"
    fi
    
    # Set default resource quotas for non-production environments
    if [[ "$1" != "production" ]]; then
        log_info "Applying resource quotas for $1 environment..."
        cat <<EOF | kubectl apply -f -
apiVersion: v1
kind: ResourceQuota
metadata:
  name: compute-quota
  namespace: $NAMESPACE
spec:
  hard:
    requests.cpu: "10"
    requests.memory: 20Gi
    limits.cpu: "20"
    limits.memory: 40Gi
    persistentvolumeclaims: "10"
    pods: "20"
    services: "10"
    secrets: "20"
    configmaps: "20"
EOF
    fi
}

# Install and configure Helm charts with comprehensive monitoring
install_helm_charts() {
    local environment=$1
    
    log_step "Installing Helm charts for $environment environment..."
    
    # Add Helm repositories
    local repos=(
        "ingress-nginx|https://kubernetes.github.io/ingress-nginx"
        "cert-manager|https://charts.jetstack.io"
        "prometheus-community|https://prometheus-community.github.io/helm-charts"
        "grafana|https://grafana.github.io/helm-charts"
        "elastic|https://helm.elastic.co"
        "jaegertracing|https://jaegertracing.github.io/helm-charts"
        "metrics-server|https://kubernetes-sigs.github.io/metrics-server/"
    )
    
    for repo in "${repos[@]}"; do
        IFS='|' read -r name url <<< "$repo"
        if ! helm repo list | grep -q "^$name"; then
            log_info "Adding Helm repository: $name"
            helm repo add "$name" "$url"
        fi
    done
    
    log_info "Updating Helm repositories..."
    helm repo update
    
    # Install NGINX Ingress Controller with enhanced configuration
    if ! helm list -n ingress-nginx | grep -q nginx-ingress; then
        log_info "Installing NGINX Ingress Controller..."
        kubectl create namespace ingress-nginx --dry-run=client -o yaml | kubectl apply -f -
        kubectl label namespace ingress-nginx name=ingress-nginx --overwrite
        
        helm install nginx-ingress ingress-nginx/ingress-nginx \
            --namespace ingress-nginx \
            --set controller.replicaCount=2 \
            --set controller.resources.requests.cpu=250m \
            --set controller.resources.requests.memory=512Mi \
            --set controller.resources.limits.cpu=500m \
            --set controller.resources.limits.memory=1Gi \
            --set controller.metrics.enabled=true \
            --set controller.metrics.serviceMonitor.enabled=true \
            --set controller.podSecurityContext.runAsNonRoot=true \
            --set controller.config.use-gzip=true \
            --set controller.config.enable-brotli=true \
            --set controller.config.gzip-level=6 \
            --timeout="$HELM_TIMEOUT" \
            --wait
    fi
    
    # Install cert-manager for TLS certificate management
    if ! helm list -n cert-manager | grep -q cert-manager; then
        log_info "Installing cert-manager..."
        kubectl create namespace cert-manager --dry-run=client -o yaml | kubectl apply -f -
        kubectl label namespace cert-manager name=cert-manager --overwrite
        
        helm install cert-manager cert-manager/cert-manager \
            --namespace cert-manager \
            --set installCRDs=true \
            --set global.leaderElection.namespace=cert-manager \
            --set prometheus.enabled=true \
            --set webhook.securePort=10260 \
            --timeout="$HELM_TIMEOUT" \
            --wait
    fi
    
    # Install metrics-server if not present
    if ! kubectl get deployment metrics-server -n kube-system &> /dev/null; then
        log_info "Installing metrics-server..."
        helm install metrics-server metrics-server/metrics-server \
            --namespace kube-system \
            --set args[0]="--cert-dir=/tmp" \
            --set args[1]="--secure-port=4443" \
            --set args[2]="--kubelet-preferred-address-types=InternalIP,ExternalIP,Hostname" \
            --set args[3]="--kubelet-use-node-status-port" \
            --set args[4]="--metric-resolution=15s" \
            --timeout="$HELM_TIMEOUT" \
            --wait
    fi
    
    # Install comprehensive monitoring stack for staging and production
    if [[ "$environment" != "development" ]]; then
        if ! helm list -n monitoring | grep -q kube-prometheus-stack; then
            log_info "Installing Prometheus monitoring stack..."
            kubectl create namespace monitoring --dry-run=client -o yaml | kubectl apply -f -
            kubectl label namespace monitoring name=monitoring --overwrite
            
            # Create custom values for monitoring
            cat > /tmp/prometheus-values.yaml <<EOF
prometheus:
  prometheusSpec:
    retention: 30d
    retentionSize: 50GB
    resources:
      requests:
        memory: 2Gi
        cpu: 500m
      limits:
        memory: 4Gi
        cpu: 1000m
    storageSpec:
      volumeClaimTemplate:
        spec:
          storageClassName: gp3
          accessModes: ["ReadWriteOnce"]
          resources:
            requests:
              storage: 100Gi
    additionalScrapeConfigs:
      - job_name: 'roadrunner-metrics'
        static_configs:
          - targets: ['roadrunner-app-enhanced-service.$NAMESPACE.svc.cluster.local:2112']
        scrape_interval: 15s
        metrics_path: /metrics

grafana:
  enabled: true
  adminPassword: $(openssl rand -base64 32)
  persistence:
    enabled: true
    size: 10Gi
    storageClassName: gp3
  resources:
    requests:
      memory: 512Mi
      cpu: 250m
    limits:
      memory: 1Gi
      cpu: 500m
  grafana.ini:
    server:
      root_url: https://grafana.$environment.yourdomain.com
    security:
      admin_user: admin
      cookie_secure: true
      cookie_samesite: strict

alertmanager:
  enabled: true
  alertmanagerSpec:
    resources:
      requests:
        memory: 256Mi
        cpu: 100m
      limits:
        memory: 512Mi
        cpu: 200m
    storage:
      volumeClaimTemplate:
        spec:
          storageClassName: gp3
          accessModes: ["ReadWriteOnce"]
          resources:
            requests:
              storage: 10Gi

nodeExporter:
  enabled: true

kubeStateMetrics:
  enabled: true
EOF
            
            helm install kube-prometheus-stack prometheus-community/kube-prometheus-stack \
                --namespace monitoring \
                --values /tmp/prometheus-values.yaml \
                --timeout="$HELM_TIMEOUT" \
                --wait
                
            rm /tmp/prometheus-values.yaml
        fi
        
        # Install Jaeger for distributed tracing
        if ! helm list -n tracing | grep -q jaeger; then
            log_info "Installing Jaeger for distributed tracing..."
            kubectl create namespace tracing --dry-run=client -o yaml | kubectl apply -f -
            kubectl label namespace tracing name=tracing --overwrite
            
            helm install jaeger jaegertracing/jaeger \
                --namespace tracing \
                --set provisionDataStore.cassandra=false \
                --set provisionDataStore.elasticsearch=true \
                --set storage.type=elasticsearch \
                --set elasticsearch.replicas=1 \
                --set elasticsearch.minimumMasterNodes=1 \
                --timeout="$HELM_TIMEOUT" \
                --wait
        fi
    fi
    
    log_success "Helm charts installed successfully"
}

# Apply Kubernetes manifests with environment-specific configurations
apply_manifests() {
    local component=$1
    local image_uri=$2
    local environment=$3
    
    log_step "Applying Kubernetes manifests for $component in $environment environment..."
    
    # Create temporary directory for processed manifests
    local temp_dir
    temp_dir=$(mktemp -d)
    trap "rm -rf $temp_dir" EXIT
    
    # Get environment-specific settings
    local replicas="${ENVIRONMENT_SETTINGS[${environment}_replicas]}"
    local cpu_request="${ENVIRONMENT_SETTINGS[${environment}_cpu_request]}"
    local memory_request="${ENVIRONMENT_SETTINGS[${environment}_memory_request]}"
    local cpu_limit="${ENVIRONMENT_SETTINGS[${environment}_cpu_limit]}"
    local memory_limit="${ENVIRONMENT_SETTINGS[${environment}_memory_limit]}"
    local rr_workers="${ENVIRONMENT_SETTINGS[${environment}_rr_workers]}"
    
    case $component in
        "namespace")
            ensure_namespace "$environment"
            ;;
        "secrets")
            apply_secrets "$environment"
            ;;
        "postgres")
            apply_postgres_manifest "$environment" "$temp_dir"
            ;;
        "redis")
            apply_redis_manifest "$environment" "$temp_dir"
            ;;
        "app"|"roadrunner")
            apply_roadrunner_manifest "$environment" "$image_uri" "$temp_dir" \
                "$replicas" "$cpu_request" "$memory_request" "$cpu_limit" "$memory_limit" "$rr_workers"
            ;;
        "worker"|"horizon")
            apply_horizon_manifest "$environment" "$image_uri" "$temp_dir" \
                "$cpu_request" "$memory_request" "$cpu_limit" "$memory_limit"
            ;;
        "ingress")
            apply_ingress_manifest "$environment" "$temp_dir"
            ;;
        "monitoring")
            apply_monitoring_manifests "$environment" "$temp_dir"
            ;;
        "all")
            apply_manifests "namespace" "$image_uri" "$environment"
            apply_manifests "secrets" "$image_uri" "$environment"
            apply_manifests "postgres" "$image_uri" "$environment"
            apply_manifests "redis" "$image_uri" "$environment"
            apply_manifests "app" "$image_uri" "$environment"
            apply_manifests "worker" "$image_uri" "$environment"
            apply_manifests "ingress" "$image_uri" "$environment"
            apply_manifests "monitoring" "$image_uri" "$environment"
            ;;
        *)
            log_error "Unknown component: $component"
            exit 1
            ;;
    esac
    
    log_success "Manifests applied for $component"
}

# Apply secrets with enhanced security
apply_secrets() {
    local environment=$1
    
    log_info "Applying secrets for $environment environment..."
    
    # Generate secure random passwords if they don't exist
    local postgres_password db_password redis_password app_key jwt_secret
    
    if kubectl get secret app-secrets -n "$NAMESPACE" &> /dev/null; then
        log_info "Secrets already exist, updating if necessary..."
        postgres_password=$(kubectl get secret app-secrets -n "$NAMESPACE" -o jsonpath='{.data.POSTGRES_PASSWORD}' | base64 -d)
        db_password=$(kubectl get secret app-secrets -n "$NAMESPACE" -o jsonpath='{.data.DB_PASSWORD}' | base64 -d)
        redis_password=$(kubectl get secret app-secrets -n "$NAMESPACE" -o jsonpath='{.data.REDIS_PASSWORD}' | base64 -d)
        app_key=$(kubectl get secret app-secrets -n "$NAMESPACE" -o jsonpath='{.data.APP_KEY}' | base64 -d)
        jwt_secret=$(kubectl get secret app-secrets -n "$NAMESPACE" -o jsonpath='{.data.JWT_SECRET}' | base64 -d)
    else
        log_info "Generating new secrets..."
        postgres_password=$(openssl rand -base64 32)
        db_password="$postgres_password"
        redis_password=$(openssl rand -base64 32)
        app_key="base64:$(openssl rand -base64 32)"
        jwt_secret=$(openssl rand -base64 64)
    fi
    
    # Create comprehensive secrets
    kubectl create secret generic app-secrets \
        --namespace="$NAMESPACE" \
        --from-literal=APP_KEY="$app_key" \
        --from-literal=DB_PASSWORD="$db_password" \
        --from-literal=POSTGRES_PASSWORD="$postgres_password" \
        --from-literal=REDIS_PASSWORD="$redis_password" \
        --from-literal=JWT_SECRET="$jwt_secret" \
        --from-literal=STRIPE_SECRET="${STRIPE_SECRET:-sk_test_placeholder}" \
        --from-literal=GOOGLE_CREDENTIALS="${GOOGLE_CREDENTIALS:-{}}" \
        --from-literal=AWS_ACCESS_KEY_ID="${AWS_ACCESS_KEY_ID:-placeholder}" \
        --from-literal=AWS_SECRET_ACCESS_KEY="${AWS_SECRET_ACCESS_KEY:-placeholder}" \
        --from-literal=SENTRY_DSN="${SENTRY_DSN:-}" \
        --dry-run=client -o yaml | kubectl apply -f -
    
    # Create TLS secrets if certificates are provided
    if [[ -f "$SCRIPT_DIR/certs/tls.crt" && -f "$SCRIPT_DIR/certs/tls.key" ]]; then
        log_info "Creating TLS secret from provided certificates..."
        kubectl create secret tls app-tls \
            --namespace="$NAMESPACE" \
            --cert="$SCRIPT_DIR/certs/tls.crt" \
            --key="$SCRIPT_DIR/certs/tls.key" \
            --dry-run=client -o yaml | kubectl apply -f -
    fi
    
    log_success "Secrets applied successfully"
}

# Apply PostgreSQL with enhanced configuration
apply_postgres_manifest() {
    local environment=$1
    local temp_dir=$2
    
    log_info "Applying PostgreSQL manifest for $environment..."
    
    # Determine storage class and size based on environment
    local storage_class storage_size replicas
    case $environment in
        "development")
            storage_class="standard"
            storage_size="10Gi"
            replicas=1
            ;;
        "staging")
            storage_class="gp3"
            storage_size="50Gi"
            replicas=1
            ;;
        "production")
            storage_class="io2"
            storage_size="200Gi"
            replicas=1
            ;;
    esac
    
    # Process PostgreSQL manifest with environment-specific values
    sed \
        -e "s|{{STORAGE_CLASS}}|$storage_class|g" \
        -e "s|{{STORAGE_SIZE}}|$storage_size|g" \
        -e "s|{{REPLICAS}}|$replicas|g" \
        -e "s|{{ENVIRONMENT}}|$environment|g" \
        "$SCRIPT_DIR/postgres.yaml" > "$temp_dir/postgres.yaml"
    
    kubectl apply -f "$temp_dir/postgres.yaml"
}

# Apply Redis with enhanced configuration
apply_redis_manifest() {
    local environment=$1
    local temp_dir=$2
    
    log_info "Applying Redis manifest for $environment..."
    
    # Determine Redis configuration based on environment
    local maxmemory replicas
    case $environment in
        "development")
            maxmemory="256mb"
            replicas=1
            ;;
        "staging")
            maxmemory="1gb"
            replicas=1
            ;;
        "production")
            maxmemory="4gb"
            replicas=1
            ;;
    esac
    
    # Process Redis manifest with environment-specific values
    sed \
        -e "s|{{MAXMEMORY}}|$maxmemory|g" \
        -e "s|{{REPLICAS}}|$replicas|g" \
        -e "s|{{ENVIRONMENT}}|$environment|g" \
        "$SCRIPT_DIR/redis.yaml" > "$temp_dir/redis.yaml"
    
    kubectl apply -f "$temp_dir/redis.yaml"
}

# Apply RoadRunner application manifest with optimizations
apply_roadrunner_manifest() {
    local environment=$1
    local image_uri=$2
    local temp_dir=$3
    local replicas=$4
    local cpu_request=$5
    local memory_request=$6
    local cpu_limit=$7
    local memory_limit=$8
    local rr_workers=$9
    
    log_info "Applying RoadRunner application manifest for $environment..."
    
    # Process the enhanced RoadRunner deployment
    sed \
        -e "s|your-registry/ai-blockchain-analytics:latest|$image_uri|g" \
        -e "s|replicas: 3|replicas: $replicas|g" \
        -e "s|cpu: \"1000m\"|cpu: \"$cpu_request\"|g" \
        -e "s|memory: \"2Gi\"|memory: \"$memory_request\"|g" \
        -e "s|cpu: \"2000m\"|cpu: \"$cpu_limit\"|g" \
        -e "s|memory: \"4Gi\"|memory: \"$memory_limit\"|g" \
        -e "s|RR_WORKERS:-12|RR_WORKERS:-$rr_workers|g" \
        -e "s|{{ENVIRONMENT}}|$environment|g" \
        "$SCRIPT_DIR/enhanced-roadrunner-deployment.yaml" > "$temp_dir/roadrunner.yaml"
    
    kubectl apply -f "$temp_dir/roadrunner.yaml"
}

# Apply Horizon worker manifest
apply_horizon_manifest() {
    local environment=$1
    local image_uri=$2
    local temp_dir=$3
    local cpu_request=$4
    local memory_request=$5
    local cpu_limit=$6
    local memory_limit=$7
    
    log_info "Applying Horizon worker manifest for $environment..."
    
    # Process Horizon worker deployment
    sed \
        -e "s|your-registry/ai-blockchain-analytics:latest|$image_uri|g" \
        -e "s|cpu: \"500m\"|cpu: \"$cpu_request\"|g" \
        -e "s|memory: \"1Gi\"|memory: \"$memory_request\"|g" \
        -e "s|cpu: \"1000m\"|cpu: \"$cpu_limit\"|g" \
        -e "s|memory: \"2Gi\"|memory: \"$memory_limit\"|g" \
        -e "s|{{ENVIRONMENT}}|$environment|g" \
        "$SCRIPT_DIR/horizon-worker.yaml" > "$temp_dir/horizon.yaml"
    
    kubectl apply -f "$temp_dir/horizon.yaml"
}

# Apply ingress manifest with environment-specific configuration
apply_ingress_manifest() {
    local environment=$1
    local temp_dir=$2
    
    log_info "Applying ingress manifest for $environment..."
    
    # Determine domain and TLS configuration based on environment
    local domain tls_enabled
    case $environment in
        "development")
            domain="dev.ai-blockchain.local"
            tls_enabled="false"
            ;;
        "staging")
            domain="staging.ai-blockchain.yourcompany.com"
            tls_enabled="true"
            ;;
        "production")
            domain="ai-blockchain.yourcompany.com"
            tls_enabled="true"
            ;;
    esac
    
    # Process ingress manifest
    sed \
        -e "s|{{DOMAIN}}|$domain|g" \
        -e "s|{{TLS_ENABLED}}|$tls_enabled|g" \
        -e "s|{{ENVIRONMENT}}|$environment|g" \
        "$SCRIPT_DIR/ingress.yaml" > "$temp_dir/ingress.yaml"
    
    kubectl apply -f "$temp_dir/ingress.yaml"
}

# Apply monitoring manifests
apply_monitoring_manifests() {
    local environment=$1
    local temp_dir=$2
    
    log_info "Applying monitoring manifests for $environment..."
    
    # Apply ServiceMonitor for Prometheus
    cat > "$temp_dir/servicemonitor.yaml" <<EOF
apiVersion: monitoring.coreos.com/v1
kind: ServiceMonitor
metadata:
  name: roadrunner-app-metrics
  namespace: $NAMESPACE
  labels:
    app: roadrunner-app-enhanced
    release: kube-prometheus-stack
spec:
  selector:
    matchLabels:
      app: roadrunner-app-enhanced
  endpoints:
  - port: metrics
    interval: 15s
    path: /metrics
EOF
    
    kubectl apply -f "$temp_dir/servicemonitor.yaml"
}

# Wait for deployment with enhanced status checking
wait_for_deployment() {
    local deployment_name=$1
    local timeout=${2:-600}
    local namespace=${3:-$NAMESPACE}
    
    log_step "Waiting for deployment $deployment_name to be ready (timeout: ${timeout}s)..."
    
    # Show initial status
    kubectl get deployment "$deployment_name" -n "$namespace" || true
    
    # Wait for deployment with progress indication
    local start_time
    start_time=$(date +%s)
    
    while true; do
        local current_time
        current_time=$(date +%s)
        local elapsed=$((current_time - start_time))
        
        if [[ $elapsed -gt $timeout ]]; then
            log_error "Deployment $deployment_name failed to become ready within $timeout seconds"
            break
        fi
        
        # Check deployment status
        local ready_replicas available_replicas desired_replicas
        ready_replicas=$(kubectl get deployment "$deployment_name" -n "$namespace" -o jsonpath='{.status.readyReplicas}' 2>/dev/null || echo "0")
        available_replicas=$(kubectl get deployment "$deployment_name" -n "$namespace" -o jsonpath='{.status.availableReplicas}' 2>/dev/null || echo "0")
        desired_replicas=$(kubectl get deployment "$deployment_name" -n "$namespace" -o jsonpath='{.spec.replicas}' 2>/dev/null || echo "1")
        
        if [[ "$ready_replicas" == "$desired_replicas" && "$available_replicas" == "$desired_replicas" ]]; then
            log_success "Deployment $deployment_name is ready ($ready_replicas/$desired_replicas replicas)"
            return 0
        fi
        
        # Show progress
        log_info "Deployment $deployment_name status: $ready_replicas/$desired_replicas ready, $available_replicas available (${elapsed}s elapsed)"
        
        sleep 10
    done
    
    # Show debugging information on failure
    log_error "Deployment $deployment_name failed to become ready"
    log_info "Deployment status:"
    kubectl describe deployment "$deployment_name" -n "$namespace" || true
    
    log_info "Pod status:"
    kubectl get pods -n "$namespace" -l app="$deployment_name" || true
    
    log_info "Recent events:"
    kubectl get events -n "$namespace" --sort-by='.lastTimestamp' --field-selector involvedObject.name="$deployment_name" | tail -10 || true
    
    return 1
}

# Run database migrations with enhanced error handling
run_migrations() {
    local image_uri=$1
    local environment=$2
    
    log_step "Running database migrations for $environment environment..."
    
    # Wait for PostgreSQL to be ready
    log_info "Waiting for PostgreSQL to be ready..."
    kubectl wait --for=condition=ready pod -l app=postgres -n "$NAMESPACE" --timeout=300s
    
    # Create migration job
    local job_name="migration-$(date +%s)"
    local migration_job_yaml="
apiVersion: batch/v1
kind: Job
metadata:
  name: $job_name
  namespace: $NAMESPACE
  labels:
    app: migration
    component: database
    environment: $environment
spec:
  ttlSecondsAfterFinished: 300
  backoffLimit: 3
  template:
    metadata:
      labels:
        app: migration
        component: database
        environment: $environment
    spec:
      restartPolicy: Never
      securityContext:
        runAsNonRoot: true
        runAsUser: 1000
        fsGroup: 1000
      containers:
      - name: migration
        image: $image_uri
        command: 
        - /bin/sh
        - -c
        - |
          set -e
          echo 'Waiting for database connection...'
          until php artisan migrate:status --env=$environment; do
            echo 'Database not ready, waiting 5 seconds...'
            sleep 5
          done
          echo 'Running migrations...'
          php artisan migrate --force --env=$environment
          echo 'Running seeders for $environment...'
          if [[ '$environment' != 'production' ]]; then
            php artisan db:seed --force --env=$environment
          fi
          echo 'Migration completed successfully'
        envFrom:
        - configMapRef:
            name: app-config
        - secretRef:
            name: app-secrets
        env:
        - name: APP_ENV
          value: $environment
        - name: CONTAINER_ROLE
          value: migration
        resources:
          requests:
            memory: '512Mi'
            cpu: '250m'
          limits:
            memory: '1Gi'
            cpu: '500m'
        securityContext:
          allowPrivilegeEscalation: false
          readOnlyRootFilesystem: false
          capabilities:
            drop:
            - ALL
"
    
    # Apply migration job
    echo "$migration_job_yaml" | kubectl apply -f -
    
    # Wait for job completion with detailed monitoring
    log_info "Monitoring migration job progress..."
    local start_time
    start_time=$(date +%s)
    
    while true; do
        local job_status
        job_status=$(kubectl get job "$job_name" -n "$NAMESPACE" -o jsonpath='{.status.conditions[?(@.type=="Complete")].status}' 2>/dev/null || echo "")
        
        if [[ "$job_status" == "True" ]]; then
            log_success "Database migrations completed successfully"
            break
        fi
        
        local job_failed
        job_failed=$(kubectl get job "$job_name" -n "$NAMESPACE" -o jsonpath='{.status.conditions[?(@.type=="Failed")].status}' 2>/dev/null || echo "")
        
        if [[ "$job_failed" == "True" ]]; then
            log_error "Database migrations failed"
            log_info "Job logs:"
            kubectl logs job/"$job_name" -n "$NAMESPACE" --tail=50
            return 1
        fi
        
        local current_time
        current_time=$(date +%s)
        local elapsed=$((current_time - start_time))
        
        if [[ $elapsed -gt 600 ]]; then
            log_error "Migration job timed out after 10 minutes"
            kubectl logs job/"$job_name" -n "$NAMESPACE" --tail=50
            return 1
        fi
        
        log_info "Migration job running... (${elapsed}s elapsed)"
        sleep 10
    done
    
    # Show migration logs for verification
    log_info "Migration job logs:"
    kubectl logs job/"$job_name" -n "$NAMESPACE" --tail=20
    
    # Clean up migration job
    kubectl delete job "$job_name" -n "$NAMESPACE"
}

# Comprehensive health check
perform_health_check() {
    local environment=$1
    
    log_step "Performing comprehensive health check for $environment environment..."
    
    # Check deployment status
    local deployments=("roadrunner-app-enhanced" "postgres" "redis")
    if [[ "$environment" != "development" ]]; then
        deployments+=("horizon-worker")
    fi
    
    for deployment in "${deployments[@]}"; do
        if kubectl get deployment "$deployment" -n "$NAMESPACE" &> /dev/null; then
            local ready_replicas desired_replicas
            ready_replicas=$(kubectl get deployment "$deployment" -n "$NAMESPACE" -o jsonpath='{.status.readyReplicas}' || echo "0")
            desired_replicas=$(kubectl get deployment "$deployment" -n "$NAMESPACE" -o jsonpath='{.spec.replicas}' || echo "1")
            
            if [[ "$ready_replicas" == "$desired_replicas" ]]; then
                log_success "$deployment: $ready_replicas/$desired_replicas replicas ready"
            else
                log_warning "$deployment: $ready_replicas/$desired_replicas replicas ready"
            fi
        else
            log_warning "$deployment: deployment not found"
        fi
    done
    
    # Check service endpoints
    log_info "Checking service endpoints..."
    kubectl get endpoints -n "$NAMESPACE"
    
    # Check persistent volumes
    log_info "Checking persistent volumes..."
    kubectl get pvc -n "$NAMESPACE"
    
    # Test application endpoint if available
    local app_service_ip
    app_service_ip=$(kubectl get service roadrunner-app-enhanced-service -n "$NAMESPACE" -o jsonpath='{.spec.clusterIP}' 2>/dev/null || echo "")
    
    if [[ -n "$app_service_ip" ]]; then
        log_info "Testing application health endpoint..."
        if kubectl run curl-test --image=curlimages/curl:7.85.0 --rm -i --restart=Never -n "$NAMESPACE" -- \
            curl -s -f "http://$app_service_ip/health" -m 10; then
            log_success "Application health check passed"
        else
            log_warning "Application health check failed or endpoint not ready"
        fi
    fi
    
    # Check for any failed pods
    local failed_pods
    failed_pods=$(kubectl get pods -n "$NAMESPACE" --field-selector=status.phase=Failed -o name 2>/dev/null || echo "")
    
    if [[ -n "$failed_pods" ]]; then
        log_warning "Failed pods detected:"
        for pod in $failed_pods; do
            log_warning "  $pod"
            kubectl logs "$pod" -n "$NAMESPACE" --tail=10 || true
        done
    else
        log_success "No failed pods detected"
    fi
    
    log_success "Health check completed"
}

# Show comprehensive deployment status
show_deployment_status() {
    local environment=$1
    
    log_step "Deployment Status Summary for $environment environment:"
    echo ""
    
    # Show namespace info
    log_info "Namespace: $NAMESPACE"
    kubectl get namespace "$NAMESPACE" -o wide
    echo ""
    
    # Show all resources
    log_info "All Resources:"
    kubectl get all -n "$NAMESPACE" -o wide
    echo ""
    
    # Show persistent volumes
    log_info "Persistent Volume Claims:"
    kubectl get pvc -n "$NAMESPACE" -o wide
    echo ""
    
    # Show ingress
    log_info "Ingress:"
    kubectl get ingress -n "$NAMESPACE" -o wide
    echo ""
    
    # Show horizontal pod autoscalers
    log_info "Horizontal Pod Autoscalers:"
    kubectl get hpa -n "$NAMESPACE" -o wide
    echo ""
    
    # Show secrets (without sensitive data)
    log_info "Secrets:"
    kubectl get secrets -n "$NAMESPACE"
    echo ""
    
    # Show configmaps
    log_info "ConfigMaps:"
    kubectl get configmaps -n "$NAMESPACE"
    echo ""
    
    # Show events
    log_info "Recent Events:"
    kubectl get events -n "$NAMESPACE" --sort-by='.lastTimestamp' | tail -20
    echo ""
    
    # Show resource usage if metrics-server is available
    if kubectl top nodes &> /dev/null; then
        log_info "Node Resource Usage:"
        kubectl top nodes
        echo ""
        
        log_info "Pod Resource Usage:"
        kubectl top pods -n "$NAMESPACE" 2>/dev/null || log_warning "Pod metrics not available"
        echo ""
    fi
    
    # Show application URLs
    local ingress_ip
    ingress_ip=$(kubectl get ingress -n "$NAMESPACE" -o jsonpath='{.items[0].status.loadBalancer.ingress[0].ip}' 2>/dev/null || echo "")
    
    if [[ -n "$ingress_ip" ]]; then
        log_success "Application accessible at: http://$ingress_ip"
    else
        log_info "Ingress IP not yet assigned or not available"
    fi
}

# Rollback deployment
rollback_deployment() {
    local deployment_name=$1
    local revision=${2:-}
    
    log_step "Rolling back deployment: $deployment_name"
    
    if [[ -n "$revision" ]]; then
        kubectl rollout undo deployment/"$deployment_name" -n "$NAMESPACE" --to-revision="$revision"
    else
        kubectl rollout undo deployment/"$deployment_name" -n "$NAMESPACE"
    fi
    
    wait_for_deployment "$deployment_name"
    
    log_success "Rollback completed for $deployment_name"
}

# Main deployment function
deploy() {
    local environment=${1:-production}
    local component=${2:-all}
    local action=${3:-deploy}
    local image_tag=${4:-$(date +%Y%m%d%H%M%S)}
    
    log_step "Starting enhanced Kubernetes deployment..."
    log_info "Environment: $environment"
    log_info "Component: $component"
    log_info "Action: $action"
    log_info "Image tag: $image_tag"
    log_info "Namespace: $NAMESPACE"
    log_info "Registry: $REGISTRY"
    
    # Validate inputs
    validate_environment "$environment"
    check_dependencies
    check_kubectl_context
    
    # Ensure namespace exists
    ensure_namespace "$environment"
    
    case $action in
        "deploy")
            # Build and push image for app components
            local image_uri=""
            if [[ "$component" == "all" || "$component" == "app" || "$component" == "worker" || "$component" == "roadrunner" || "$component" == "horizon" ]]; then
                image_uri=$(build_and_push_image "$environment" "$image_tag")
            fi
            
            # Install Helm charts for infrastructure
            if [[ "$component" == "all" || "$component" == "infrastructure" ]]; then
                install_helm_charts "$environment"
            fi
            
            # Apply Kubernetes manifests
            apply_manifests "$component" "$image_uri" "$environment"
            
            # Wait for infrastructure components
            if [[ "$component" == "all" || "$component" == "postgres" ]]; then
                wait_for_deployment "postgres" 300
            fi
            
            if [[ "$component" == "all" || "$component" == "redis" ]]; then
                wait_for_deployment "redis" 300
            fi
            
            # Run migrations and deploy app
            if [[ "$component" == "all" || "$component" == "app" || "$component" == "roadrunner" ]]; then
                run_migrations "$image_uri" "$environment"
                wait_for_deployment "roadrunner-app-enhanced" 600
            fi
            
            # Deploy worker
            if [[ "$component" == "all" || "$component" == "worker" || "$component" == "horizon" ]]; then
                wait_for_deployment "horizon-worker" 300
            fi
            
            # Perform health check
            perform_health_check "$environment"
            
            log_success "Deployment completed successfully!"
            ;;
            
        "rollback")
            rollback_deployment "$component" "$image_tag"
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
Enhanced AI Blockchain Analytics Kubernetes Deployment Script

Usage: $0 [environment] [component] [action] [image_tag]

Arguments:
  environment  Target environment (development, staging, production)
               Default: production
  
  component    Component to deploy:
               - all: Deploy all components (default)
               - infrastructure: Helm charts and infrastructure
               - app/roadrunner: Application server
               - worker/horizon: Queue worker
               - postgres: PostgreSQL database
               - redis: Redis cache
               - ingress: Ingress controller and rules
               - monitoring: Monitoring stack
               - secrets: Kubernetes secrets
               - namespace: Create/update namespace
  
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
  $0 production roadrunner-app-enhanced rollback  # Rollback production app
  $0 staging status                          # Show staging deployment status
  $0 production health                       # Perform production health check

Environment Variables:
  REGISTRY                Docker registry URL (default: your-registry)
  HELM_TIMEOUT           Helm operation timeout (default: 600s)
  BUILD_CONTEXT          Docker build context path (default: ../)
  DEBUG                  Enable debug logging (default: false)
  STRIPE_SECRET          Stripe secret key for payments
  GOOGLE_CREDENTIALS     Google service account credentials (JSON)
  AWS_ACCESS_KEY_ID      AWS access key
  AWS_SECRET_ACCESS_KEY  AWS secret key
  SENTRY_DSN            Sentry error tracking DSN

Prerequisites:
  - kubectl configured with appropriate cluster access
  - docker with BuildKit support
  - helm 3.x
  - jq for JSON processing
  - curl for health checks

Features:
  - Enhanced RoadRunner configuration with metrics
  - Comprehensive monitoring with Prometheus and Grafana
  - Security hardening with NetworkPolicies and PodSecurityPolicies
  - Automatic SSL certificate management with cert-manager
  - Horizontal Pod Autoscaling based on CPU, memory, and custom metrics
  - Distributed tracing with Jaeger
  - Log aggregation with Fluent Bit
  - Health checks and graceful shutdowns
  - Rolling updates with zero downtime
  - Resource quotas and limits
  - Multi-environment support with environment-specific configurations

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
valid_components="all|infrastructure|app|roadrunner|worker|horizon|postgres|redis|ingress|monitoring|secrets|namespace"
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

