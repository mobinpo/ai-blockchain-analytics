#!/bin/bash

# AI Blockchain Analytics - Unified Deployment Script
# Supports both Kubernetes (k8s) and AWS ECS with RoadRunner, Redis, and PostgreSQL
# Author: AI Blockchain Analytics Team
# Version: 2.0

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" &> /dev/null && pwd)"
PROJECT_NAME="ai-blockchain-analytics"
DOCKER_REGISTRY="${DOCKER_REGISTRY:-}"
IMAGE_TAG="${IMAGE_TAG:-latest}"
ENVIRONMENT="${ENVIRONMENT:-production}"
REGION="${AWS_REGION:-us-east-1}"
AWS_ACCOUNT_ID="${AWS_ACCOUNT_ID:-}"

# Print banner
print_banner() {
    echo -e "${CYAN}"
    echo "═══════════════════════════════════════════════════════════════════"
    echo "   🚀 AI Blockchain Analytics - Unified Deployment Script v2.0"
    echo "   ☸️  Kubernetes & 🚀 ECS with RoadRunner, Redis, PostgreSQL"
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
Usage: ./deploy-unified.sh [PLATFORM] [ENVIRONMENT] [ACTION] [OPTIONS]

PLATFORMS:
  k8s, kubernetes     Deploy to Kubernetes cluster
  ecs, aws           Deploy to AWS ECS with Fargate
  local, docker      Deploy locally with Docker Compose

ENVIRONMENTS:
  production         Production environment with high availability
  staging            Staging environment for testing
  development        Development environment with debugging enabled

ACTIONS:
  deploy             Full deployment (build, push, deploy)
  build              Build Docker images only
  push               Push images to registry only
  apply              Apply manifests/configurations only
  destroy            Destroy all resources
  status             Check deployment status
  logs               Show application logs
  scale              Scale application replicas

OPTIONS:
  --registry REGISTRY    Docker registry URL
  --tag TAG             Docker image tag (default: latest)
  --region REGION       AWS region (default: us-east-1)
  --account-id ID       AWS account ID (required for ECS)
  --replicas COUNT      Number of application replicas
  --skip-build          Skip building Docker images
  --skip-tests          Skip running tests
  --dry-run            Show what would be done without executing
  --force              Force deployment without confirmation
  --debug              Enable debug logging

EXAMPLES:
  # Deploy to Kubernetes production
  ./deploy-unified.sh k8s production deploy

  # Deploy to ECS staging with custom registry
  ./deploy-unified.sh ecs staging deploy --registry my-registry.com --tag v1.2.3

  # Build and push images only
  ./deploy-unified.sh k8s production build --tag v1.2.3

  # Check deployment status
  ./deploy-unified.sh k8s production status

  # Scale application to 5 replicas
  ./deploy-unified.sh k8s production scale --replicas 5

  # Local development deployment
  ./deploy-unified.sh local development deploy

ENVIRONMENT VARIABLES:
  DOCKER_REGISTRY      Default Docker registry
  IMAGE_TAG           Default image tag
  AWS_REGION          Default AWS region
  AWS_ACCOUNT_ID      Default AWS account ID
  KUBECONFIG          Kubernetes configuration file
  AWS_PROFILE         AWS CLI profile

EOF
}

# Parse command line arguments
parse_arguments() {
    PLATFORM=""
    ENVIRONMENT=""
    ACTION=""
    REPLICAS="3"
    SKIP_BUILD=""
    SKIP_TESTS=""
    DRY_RUN=""
    FORCE=""
    DEBUG=""

    while [[ $# -gt 0 ]]; do
        case $1 in
            k8s|kubernetes)
                PLATFORM="k8s"
                shift
                ;;
            ecs|aws)
                PLATFORM="ecs"
                shift
                ;;
            local|docker)
                PLATFORM="local"
                shift
                ;;
            production|staging|development)
                ENVIRONMENT="$1"
                shift
                ;;
            deploy|build|push|apply|destroy|status|logs|scale)
                ACTION="$1"
                shift
                ;;
            --registry)
                DOCKER_REGISTRY="$2"
                shift 2
                ;;
            --tag)
                IMAGE_TAG="$2"
                shift 2
                ;;
            --region)
                REGION="$2"
                shift 2
                ;;
            --account-id)
                AWS_ACCOUNT_ID="$2"
                shift 2
                ;;
            --replicas)
                REPLICAS="$2"
                shift 2
                ;;
            --skip-build)
                SKIP_BUILD="true"
                shift
                ;;
            --skip-tests)
                SKIP_TESTS="true"
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
            --debug)
                DEBUG="true"
                set -x
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

    # Validate required arguments
    if [[ -z "$PLATFORM" ]]; then
        log_error "Platform is required (k8s, ecs, or local)"
        show_usage
        exit 1
    fi

    if [[ -z "$ENVIRONMENT" ]]; then
        log_error "Environment is required (production, staging, or development)"
        show_usage
        exit 1
    fi

    if [[ -z "$ACTION" ]]; then
        log_error "Action is required (deploy, build, push, apply, destroy, status, logs, scale)"
        show_usage
        exit 1
    fi
}

# Check prerequisites
check_prerequisites() {
    log_step "Checking prerequisites for $PLATFORM deployment..."

    # Common prerequisites
    command -v docker >/dev/null 2>&1 || { log_error "Docker is required but not installed"; exit 1; }
    command -v git >/dev/null 2>&1 || { log_error "Git is required but not installed"; exit 1; }

    case $PLATFORM in
        "k8s")
            command -v kubectl >/dev/null 2>&1 || { log_error "kubectl is required for Kubernetes deployment"; exit 1; }
            kubectl cluster-info >/dev/null 2>&1 || { log_error "No Kubernetes cluster access"; exit 1; }
            log_success "Kubernetes cluster access verified"
            ;;
        "ecs")
            command -v aws >/dev/null 2>&1 || { log_error "AWS CLI is required for ECS deployment"; exit 1; }
            aws sts get-caller-identity >/dev/null 2>&1 || { log_error "AWS credentials not configured"; exit 1; }
            
            if [[ -z "$AWS_ACCOUNT_ID" ]]; then
                AWS_ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
                log_info "Auto-detected AWS Account ID: $AWS_ACCOUNT_ID"
            fi
            
            log_success "AWS credentials verified"
            ;;
        "local")
            command -v docker-compose >/dev/null 2>&1 || command -v docker >/dev/null 2>&1 || { log_error "Docker Compose is required for local deployment"; exit 1; }
            log_success "Docker Compose available"
            ;;
    esac
}

# Build Docker images
build_images() {
    if [[ "$SKIP_BUILD" == "true" ]]; then
        log_info "Skipping image build (--skip-build specified)"
        return
    fi

    log_step "Building Docker images with RoadRunner..."

    # Set registry prefix
    local registry_prefix=""
    if [[ -n "$DOCKER_REGISTRY" ]]; then
        registry_prefix="${DOCKER_REGISTRY}/"
    fi

    # Build main application image with RoadRunner
    local app_image="${registry_prefix}${PROJECT_NAME}:${IMAGE_TAG}"
    
    if [[ "$DRY_RUN" == "true" ]]; then
        log_info "[DRY RUN] Would build: $app_image"
        return
    fi

    log_info "Building application image: $app_image"
    docker build \
        --target production \
        --tag "$app_image" \
        --build-arg ENVIRONMENT="$ENVIRONMENT" \
        --build-arg BUILD_DATE="$(date -u +'%Y-%m-%dT%H:%M:%SZ')" \
        --build-arg VCS_REF="$(git rev-parse --short HEAD)" \
        .

    # Build worker image (optimized for background tasks)
    local worker_image="${registry_prefix}${PROJECT_NAME}-worker:${IMAGE_TAG}"
    log_info "Building worker image: $worker_image"
    docker build \
        --target worker \
        --tag "$worker_image" \
        --build-arg ENVIRONMENT="$ENVIRONMENT" \
        --build-arg BUILD_DATE="$(date -u +'%Y-%m-%dT%H:%M:%SZ')" \
        --build-arg VCS_REF="$(git rev-parse --short HEAD)" \
        .

    # Build scheduler image (lightweight for cron tasks)
    local scheduler_image="${registry_prefix}${PROJECT_NAME}-scheduler:${IMAGE_TAG}"
    log_info "Building scheduler image: $scheduler_image"
    docker build \
        --target scheduler \
        --tag "$scheduler_image" \
        --build-arg ENVIRONMENT="$ENVIRONMENT" \
        --build-arg BUILD_DATE="$(date -u +'%Y-%m-%dT%H:%M:%SZ')" \
        --build-arg VCS_REF="$(git rev-parse --short HEAD)" \
        .

    log_success "All images built successfully"
}

# Push images to registry
push_images() {
    if [[ -z "$DOCKER_REGISTRY" ]]; then
        log_warning "No registry specified, skipping push"
        return
    fi

    log_step "Pushing images to $DOCKER_REGISTRY..."

    local images=(
        "${DOCKER_REGISTRY}/${PROJECT_NAME}:${IMAGE_TAG}"
        "${DOCKER_REGISTRY}/${PROJECT_NAME}-worker:${IMAGE_TAG}"
        "${DOCKER_REGISTRY}/${PROJECT_NAME}-scheduler:${IMAGE_TAG}"
    )

    if [[ "$DRY_RUN" == "true" ]]; then
        for image in "${images[@]}"; do
            log_info "[DRY RUN] Would push: $image"
        done
        return
    fi

    for image in "${images[@]}"; do
        log_info "Pushing: $image"
        docker push "$image"
    done

    log_success "All images pushed successfully"
}

# Deploy to Kubernetes
deploy_kubernetes() {
    log_step "Deploying to Kubernetes cluster..."

    local namespace="${PROJECT_NAME}-${ENVIRONMENT}"
    local k8s_dir="$SCRIPT_DIR/k8s"

    if [[ "$DRY_RUN" == "true" ]]; then
        log_info "[DRY RUN] Would deploy to Kubernetes namespace: $namespace"
        return
    fi

    # Create namespace if it doesn't exist
    kubectl create namespace "$namespace" --dry-run=client -o yaml | kubectl apply -f -

    # Apply configurations in order
    log_info "Applying Kubernetes manifests..."

    # 1. ConfigMaps and Secrets
    log_info "Creating ConfigMaps and Secrets..."
    
    # Create app configuration
    kubectl create configmap "${PROJECT_NAME}-config" \
        --namespace="$namespace" \
        --from-literal=APP_ENV="$ENVIRONMENT" \
        --from-literal=APP_DEBUG="false" \
        --from-literal=APP_URL="https://${PROJECT_NAME}-${ENVIRONMENT}.example.com" \
        --from-literal=DB_CONNECTION="pgsql" \
        --from-literal=DB_HOST="${PROJECT_NAME}-postgres" \
        --from-literal=DB_PORT="5432" \
        --from-literal=DB_DATABASE="$PROJECT_NAME" \
        --from-literal=REDIS_HOST="${PROJECT_NAME}-redis" \
        --from-literal=REDIS_PORT="6379" \
        --from-literal=CACHE_DRIVER="redis" \
        --from-literal=SESSION_DRIVER="redis" \
        --from-literal=QUEUE_CONNECTION="redis" \
        --dry-run=client -o yaml | kubectl apply -f -

    # 2. PostgreSQL
    log_info "Deploying PostgreSQL..."
    cat > "$k8s_dir/postgres-deployment.yaml" << 'EOF'
apiVersion: v1
kind: PersistentVolumeClaim
metadata:
  name: postgres-pvc
spec:
  accessModes:
    - ReadWriteOnce
  resources:
    requests:
      storage: 20Gi
---
apiVersion: apps/v1
kind: Deployment
metadata:
  name: postgres
  labels:
    app: postgres
spec:
  replicas: 1
  selector:
    matchLabels:
      app: postgres
  template:
    metadata:
      labels:
        app: postgres
    spec:
      containers:
      - name: postgres
        image: postgres:15-alpine
        env:
        - name: POSTGRES_DB
          value: "ai_blockchain_analytics"
        - name: POSTGRES_USER
          value: "postgres"
        - name: POSTGRES_PASSWORD
          value: "secure_password"
        - name: POSTGRES_INITDB_ARGS
          value: "--auth-host=scram-sha-256"
        ports:
        - containerPort: 5432
        volumeMounts:
        - name: postgres-storage
          mountPath: /var/lib/postgresql/data
        resources:
          requests:
            memory: 512Mi
            cpu: 250m
          limits:
            memory: 2Gi
            cpu: 1000m
        livenessProbe:
          exec:
            command:
            - pg_isready
            - -U
            - postgres
          initialDelaySeconds: 30
          periodSeconds: 10
        readinessProbe:
          exec:
            command:
            - pg_isready
            - -U
            - postgres
          initialDelaySeconds: 5
          periodSeconds: 5
      volumes:
      - name: postgres-storage
        persistentVolumeClaim:
          claimName: postgres-pvc
---
apiVersion: v1
kind: Service
metadata:
  name: ai-blockchain-analytics-postgres
  labels:
    app: postgres
spec:
  ports:
  - port: 5432
    targetPort: 5432
  selector:
    app: postgres
EOF

    kubectl apply -f "$k8s_dir/postgres-deployment.yaml" -n "$namespace"

    # 3. Redis
    log_info "Deploying Redis..."
    cat > "$k8s_dir/redis-deployment.yaml" << 'EOF'
apiVersion: v1
kind: PersistentVolumeClaim
metadata:
  name: redis-pvc
spec:
  accessModes:
    - ReadWriteOnce
  resources:
    requests:
      storage: 5Gi
---
apiVersion: apps/v1
kind: Deployment
metadata:
  name: redis
  labels:
    app: redis
spec:
  replicas: 1
  selector:
    matchLabels:
      app: redis
  template:
    metadata:
      labels:
        app: redis
    spec:
      containers:
      - name: redis
        image: redis:7.2-alpine
        command:
        - redis-server
        - --appendonly
        - "yes"
        - --maxmemory
        - "512mb"
        - --maxmemory-policy
        - "allkeys-lru"
        ports:
        - containerPort: 6379
        volumeMounts:
        - name: redis-storage
          mountPath: /data
        resources:
          requests:
            memory: 256Mi
            cpu: 100m
          limits:
            memory: 512Mi
            cpu: 500m
        livenessProbe:
          tcpSocket:
            port: 6379
          initialDelaySeconds: 30
          periodSeconds: 10
        readinessProbe:
          exec:
            command:
            - redis-cli
            - ping
          initialDelaySeconds: 5
          periodSeconds: 5
      volumes:
      - name: redis-storage
        persistentVolumeClaim:
          claimName: redis-pvc
---
apiVersion: v1
kind: Service
metadata:
  name: ai-blockchain-analytics-redis
  labels:
    app: redis
spec:
  ports:
  - port: 6379
    targetPort: 6379
  selector:
    app: redis
EOF

    kubectl apply -f "$k8s_dir/redis-deployment.yaml" -n "$namespace"

    # Wait for databases to be ready
    log_info "Waiting for databases to be ready..."
    kubectl wait --for=condition=available --timeout=300s deployment/postgres -n "$namespace"
    kubectl wait --for=condition=available --timeout=300s deployment/redis -n "$namespace"

    # 4. Application with RoadRunner
    log_info "Deploying application with RoadRunner..."
    cat > "$k8s_dir/app-deployment.yaml" << EOF
apiVersion: apps/v1
kind: Deployment
metadata:
  name: app
  labels:
    app: ai-blockchain-analytics
    component: app
spec:
  replicas: $REPLICAS
  selector:
    matchLabels:
      app: ai-blockchain-analytics
      component: app
  template:
    metadata:
      labels:
        app: ai-blockchain-analytics
        component: app
    spec:
      containers:
      - name: app
        image: ${DOCKER_REGISTRY:+$DOCKER_REGISTRY/}$PROJECT_NAME:$IMAGE_TAG
        ports:
        - containerPort: 8080
          name: http
        env:
        - name: RR_HTTP_ADDRESS
          value: ":8080"
        - name: RR_HTTP_NUM_WORKERS
          value: "4"
        - name: RR_HTTP_MAX_JOBS
          value: "64"
        - name: DB_HOST
          value: "ai-blockchain-analytics-postgres"
        - name: REDIS_HOST
          value: "ai-blockchain-analytics-redis"
        envFrom:
        - configMapRef:
            name: ${PROJECT_NAME}-config
        resources:
          requests:
            memory: 512Mi
            cpu: 250m
          limits:
            memory: 1Gi
            cpu: 1000m
        livenessProbe:
          httpGet:
            path: /health
            port: 8080
          initialDelaySeconds: 30
          periodSeconds: 10
        readinessProbe:
          httpGet:
            path: /ready
            port: 8080
          initialDelaySeconds: 10
          periodSeconds: 5
        command: ["/usr/local/bin/rr", "serve", "-c", "/app/.rr.yaml"]
---
apiVersion: v1
kind: Service
metadata:
  name: app-service
  labels:
    app: ai-blockchain-analytics
    component: app
spec:
  selector:
    app: ai-blockchain-analytics
    component: app
  ports:
  - port: 80
    targetPort: 8080
    protocol: TCP
  type: ClusterIP
---
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: app-hpa
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: app
  minReplicas: $REPLICAS
  maxReplicas: $((REPLICAS * 3))
  metrics:
  - type: Resource
    resource:
      name: cpu
      target:
        type: Utilization
        averageUtilization: 70
  - type: Resource
    resource:
      name: memory
      target:
        type: Utilization
        averageUtilization: 80
EOF

    kubectl apply -f "$k8s_dir/app-deployment.yaml" -n "$namespace"

    # 5. Workers and Scheduler
    log_info "Deploying workers and scheduler..."
    cat > "$k8s_dir/workers-deployment.yaml" << EOF
apiVersion: apps/v1
kind: Deployment
metadata:
  name: horizon-worker
  labels:
    app: ai-blockchain-analytics
    component: worker
spec:
  replicas: 2
  selector:
    matchLabels:
      app: ai-blockchain-analytics
      component: worker
  template:
    metadata:
      labels:
        app: ai-blockchain-analytics
        component: worker
    spec:
      containers:
      - name: worker
        image: ${DOCKER_REGISTRY:+$DOCKER_REGISTRY/}$PROJECT_NAME-worker:$IMAGE_TAG
        env:
        - name: DB_HOST
          value: "ai-blockchain-analytics-postgres"
        - name: REDIS_HOST
          value: "ai-blockchain-analytics-redis"
        envFrom:
        - configMapRef:
            name: ${PROJECT_NAME}-config
        resources:
          requests:
            memory: 256Mi
            cpu: 100m
          limits:
            memory: 512Mi
            cpu: 500m
        command: ["php", "artisan", "horizon"]
---
apiVersion: apps/v1
kind: Deployment
metadata:
  name: scheduler
  labels:
    app: ai-blockchain-analytics
    component: scheduler
spec:
  replicas: 1
  selector:
    matchLabels:
      app: ai-blockchain-analytics
      component: scheduler
  template:
    metadata:
      labels:
        app: ai-blockchain-analytics
        component: scheduler
    spec:
      containers:
      - name: scheduler
        image: ${DOCKER_REGISTRY:+$DOCKER_REGISTRY/}$PROJECT_NAME-scheduler:$IMAGE_TAG
        env:
        - name: DB_HOST
          value: "ai-blockchain-analytics-postgres"
        - name: REDIS_HOST
          value: "ai-blockchain-analytics-redis"
        envFrom:
        - configMapRef:
            name: ${PROJECT_NAME}-config
        resources:
          requests:
            memory: 128Mi
            cpu: 50m
          limits:
            memory: 256Mi
            cpu: 200m
        command: ["php", "artisan", "schedule:work"]
EOF

    kubectl apply -f "$k8s_dir/workers-deployment.yaml" -n "$namespace"

    # 6. Ingress
    if [[ "$ENVIRONMENT" != "development" ]]; then
        log_info "Creating Ingress..."
        cat > "$k8s_dir/ingress.yaml" << EOF
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: app-ingress
  annotations:
    kubernetes.io/ingress.class: "nginx"
    cert-manager.io/cluster-issuer: "letsencrypt-prod"
    nginx.ingress.kubernetes.io/ssl-redirect: "true"
    nginx.ingress.kubernetes.io/force-ssl-redirect: "true"
spec:
  tls:
  - hosts:
    - ${PROJECT_NAME}-${ENVIRONMENT}.example.com
    secretName: ${PROJECT_NAME}-tls
  rules:
  - host: ${PROJECT_NAME}-${ENVIRONMENT}.example.com
    http:
      paths:
      - path: /
        pathType: Prefix
        backend:
          service:
            name: app-service
            port:
              number: 80
EOF

        kubectl apply -f "$k8s_dir/ingress.yaml" -n "$namespace"
    fi

    log_success "Kubernetes deployment completed!"
    log_info "Namespace: $namespace"
    log_info "Check status with: kubectl get all -n $namespace"
}

# Deploy to ECS
deploy_ecs() {
    log_step "Deploying to AWS ECS with Fargate..."

    if [[ -z "$AWS_ACCOUNT_ID" ]]; then
        log_error "AWS_ACCOUNT_ID is required for ECS deployment"
        exit 1
    fi

    local cluster_name="${PROJECT_NAME}-${ENVIRONMENT}"
    local registry="${AWS_ACCOUNT_ID}.dkr.ecr.${REGION}.amazonaws.com"

    if [[ "$DRY_RUN" == "true" ]]; then
        log_info "[DRY RUN] Would deploy to ECS cluster: $cluster_name"
        return
    fi

    # Create ECR repositories if they don't exist
    log_info "Creating ECR repositories..."
    for repo in "$PROJECT_NAME" "${PROJECT_NAME}-worker" "${PROJECT_NAME}-scheduler"; do
        aws ecr describe-repositories --repository-names "$repo" --region "$REGION" >/dev/null 2>&1 || \
        aws ecr create-repository --repository-name "$repo" --region "$REGION" >/dev/null
    done

    # Login to ECR
    aws ecr get-login-password --region "$REGION" | docker login --username AWS --password-stdin "$registry"

    # Tag and push images
    log_info "Pushing images to ECR..."
    local images=(
        "$PROJECT_NAME"
        "${PROJECT_NAME}-worker"
        "${PROJECT_NAME}-scheduler"
    )

    for image in "${images[@]}"; do
        docker tag "${image}:${IMAGE_TAG}" "${registry}/${image}:${IMAGE_TAG}"
        docker push "${registry}/${image}:${IMAGE_TAG}"
    done

    # Create ECS cluster
    log_info "Creating ECS cluster: $cluster_name"
    aws ecs create-cluster --cluster-name "$cluster_name" --capacity-providers FARGATE --region "$REGION" >/dev/null 2>&1 || true

    # Create task definition
    log_info "Registering ECS task definition..."
    cat > "/tmp/task-definition.json" << EOF
{
  "family": "${PROJECT_NAME}-app",
  "networkMode": "awsvpc",
  "requiresCompatibilities": ["FARGATE"],
  "cpu": "1024",
  "memory": "2048",
  "executionRoleArn": "arn:aws:iam::${AWS_ACCOUNT_ID}:role/ecsTaskExecutionRole",
  "taskRoleArn": "arn:aws:iam::${AWS_ACCOUNT_ID}:role/ecsTaskRole",
  "containerDefinitions": [
    {
      "name": "app",
      "image": "${registry}/${PROJECT_NAME}:${IMAGE_TAG}",
      "portMappings": [
        {
          "containerPort": 8080,
          "protocol": "tcp"
        }
      ],
      "environment": [
        {"name": "APP_ENV", "value": "${ENVIRONMENT}"},
        {"name": "RR_HTTP_ADDRESS", "value": ":8080"},
        {"name": "RR_HTTP_NUM_WORKERS", "value": "4"},
        {"name": "RR_HTTP_MAX_JOBS", "value": "64"}
      ],
      "logConfiguration": {
        "logDriver": "awslogs",
        "options": {
          "awslogs-group": "/ecs/${PROJECT_NAME}",
          "awslogs-region": "${REGION}",
          "awslogs-stream-prefix": "app"
        }
      },
      "healthCheck": {
        "command": ["CMD-SHELL", "curl -f http://localhost:8080/health || exit 1"],
        "interval": 30,
        "timeout": 5,
        "retries": 3
      }
    }
  ]
}
EOF

    aws ecs register-task-definition --cli-input-json file:///tmp/task-definition.json --region "$REGION" >/dev/null

    # Create service
    log_info "Creating ECS service..."
    aws ecs create-service \
        --cluster "$cluster_name" \
        --service-name "${PROJECT_NAME}-app" \
        --task-definition "${PROJECT_NAME}-app" \
        --desired-count "$REPLICAS" \
        --launch-type FARGATE \
        --network-configuration "awsvpcConfiguration={subnets=[subnet-12345,subnet-67890],securityGroups=[sg-12345],assignPublicIp=ENABLED}" \
        --region "$REGION" >/dev/null 2>&1 || true

    log_success "ECS deployment completed!"
    log_info "Cluster: $cluster_name"
    log_info "Region: $REGION"
    log_info "Check status with: aws ecs describe-services --cluster $cluster_name --services ${PROJECT_NAME}-app"
}

# Deploy locally
deploy_local() {
    log_step "Deploying locally with Docker Compose..."

    local compose_file="docker-compose.roadrunner.yml"

    if [[ "$DRY_RUN" == "true" ]]; then
        log_info "[DRY RUN] Would deploy locally using: $compose_file"
        return
    fi

    # Create docker-compose file for RoadRunner
    cat > "$compose_file" << EOF
version: '3.8'

services:
  app:
    build:
      context: .
      target: production
      args:
        ENVIRONMENT: ${ENVIRONMENT}
    ports:
      - "8080:8080"
    environment:
      - APP_ENV=${ENVIRONMENT}
      - RR_HTTP_ADDRESS=:8080
      - RR_HTTP_NUM_WORKERS=4
      - RR_HTTP_MAX_JOBS=64
      - DB_HOST=postgres
      - REDIS_HOST=redis
    depends_on:
      - postgres
      - redis
    volumes:
      - ./storage/logs:/app/storage/logs
    command: ["/usr/local/bin/rr", "serve", "-c", "/app/.rr.yaml"]
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8080/health"]
      interval: 30s
      timeout: 10s
      retries: 3

  worker:
    build:
      context: .
      target: worker
    environment:
      - APP_ENV=${ENVIRONMENT}
      - DB_HOST=postgres
      - REDIS_HOST=redis
    depends_on:
      - postgres
      - redis
    volumes:
      - ./storage/logs:/app/storage/logs
    command: ["php", "artisan", "horizon"]

  scheduler:
    build:
      context: .
      target: scheduler
    environment:
      - APP_ENV=${ENVIRONMENT}
      - DB_HOST=postgres
      - REDIS_HOST=redis
    depends_on:
      - postgres
      - redis
    volumes:
      - ./storage/logs:/app/storage/logs
    command: ["php", "artisan", "schedule:work"]

  postgres:
    image: postgres:15-alpine
    environment:
      POSTGRES_DB: ai_blockchain_analytics
      POSTGRES_USER: postgres
      POSTGRES_PASSWORD: password
    ports:
      - "5432:5432"
    volumes:
      - postgres_data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U postgres"]
      interval: 10s
      timeout: 5s
      retries: 5

  redis:
    image: redis:7.2-alpine
    command: redis-server --appendonly yes --maxmemory 512mb --maxmemory-policy allkeys-lru
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5

  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
    volumes:
      - ./docker/nginx/nginx.conf:/etc/nginx/nginx.conf:ro
    depends_on:
      - app

volumes:
  postgres_data:
  redis_data:
EOF

    log_info "Starting local deployment..."
    docker-compose -f "$compose_file" up --build -d

    log_success "Local deployment completed!"
    log_info "Application: http://localhost:8080"
    log_info "Logs: docker-compose -f $compose_file logs -f"
}

# Check deployment status
check_status() {
    log_step "Checking deployment status..."

    case $PLATFORM in
        "k8s")
            local namespace="${PROJECT_NAME}-${ENVIRONMENT}"
            echo -e "\n${CYAN}Kubernetes Status:${NC}"
            kubectl get all -n "$namespace" 2>/dev/null || log_warning "Namespace $namespace not found"
            ;;
        "ecs")
            local cluster_name="${PROJECT_NAME}-${ENVIRONMENT}"
            echo -e "\n${CYAN}ECS Status:${NC}"
            aws ecs describe-services --cluster "$cluster_name" --services "${PROJECT_NAME}-app" --region "$REGION" 2>/dev/null || log_warning "ECS service not found"
            ;;
        "local")
            echo -e "\n${CYAN}Local Status:${NC}"
            docker-compose -f "docker-compose.roadrunner.yml" ps 2>/dev/null || log_warning "Local deployment not found"
            ;;
    esac
}

# Scale deployment
scale_deployment() {
    log_step "Scaling deployment to $REPLICAS replicas..."

    case $PLATFORM in
        "k8s")
            local namespace="${PROJECT_NAME}-${ENVIRONMENT}"
            kubectl scale deployment app --replicas="$REPLICAS" -n "$namespace"
            log_success "Kubernetes deployment scaled to $REPLICAS replicas"
            ;;
        "ecs")
            local cluster_name="${PROJECT_NAME}-${ENVIRONMENT}"
            aws ecs update-service --cluster "$cluster_name" --service "${PROJECT_NAME}-app" --desired-count "$REPLICAS" --region "$REGION"
            log_success "ECS service scaled to $REPLICAS replicas"
            ;;
        "local")
            log_warning "Scaling not applicable for local deployment"
            ;;
    esac
}

# Destroy deployment
destroy_deployment() {
    if [[ "$FORCE" != "true" ]]; then
        echo -e "${RED}"
        read -p "Are you sure you want to destroy the $ENVIRONMENT deployment? (yes/no): " confirm
        echo -e "${NC}"
        if [[ "$confirm" != "yes" ]]; then
            log_info "Destruction cancelled"
            return
        fi
    fi

    log_step "Destroying deployment..."

    case $PLATFORM in
        "k8s")
            local namespace="${PROJECT_NAME}-${ENVIRONMENT}"
            kubectl delete namespace "$namespace" --ignore-not-found=true
            log_success "Kubernetes deployment destroyed"
            ;;
        "ecs")
            local cluster_name="${PROJECT_NAME}-${ENVIRONMENT}"
            # Delete service first
            aws ecs update-service --cluster "$cluster_name" --service "${PROJECT_NAME}-app" --desired-count 0 --region "$REGION" 2>/dev/null || true
            aws ecs delete-service --cluster "$cluster_name" --service "${PROJECT_NAME}-app" --region "$REGION" 2>/dev/null || true
            # Delete cluster
            aws ecs delete-cluster --cluster "$cluster_name" --region "$REGION" 2>/dev/null || true
            log_success "ECS deployment destroyed"
            ;;
        "local")
            docker-compose -f "docker-compose.roadrunner.yml" down -v
            log_success "Local deployment destroyed"
            ;;
    esac
}

# Show logs
show_logs() {
    log_step "Showing application logs..."

    case $PLATFORM in
        "k8s")
            local namespace="${PROJECT_NAME}-${ENVIRONMENT}"
            kubectl logs -f deployment/app -n "$namespace"
            ;;
        "ecs")
            log_info "Use AWS CloudWatch to view ECS logs"
            log_info "Log group: /ecs/${PROJECT_NAME}"
            ;;
        "local")
            docker-compose -f "docker-compose.roadrunner.yml" logs -f app
            ;;
    esac
}

# Main execution flow
main() {
    print_banner
    parse_arguments "$@"
    check_prerequisites

    # Show configuration
    log_info "Configuration:"
    log_info "  Platform: $PLATFORM"
    log_info "  Environment: $ENVIRONMENT"
    log_info "  Action: $ACTION"
    log_info "  Image Tag: $IMAGE_TAG"
    log_info "  Replicas: $REPLICAS"
    if [[ -n "$DOCKER_REGISTRY" ]]; then
        log_info "  Registry: $DOCKER_REGISTRY"
    fi
    if [[ "$PLATFORM" == "ecs" ]]; then
        log_info "  Region: $REGION"
        log_info "  Account ID: $AWS_ACCOUNT_ID"
    fi
    echo

    case $ACTION in
        "deploy")
            build_images
            push_images
            case $PLATFORM in
                "k8s") deploy_kubernetes ;;
                "ecs") deploy_ecs ;;
                "local") deploy_local ;;
            esac
            ;;
        "build")
            build_images
            ;;
        "push")
            push_images
            ;;
        "apply")
            case $PLATFORM in
                "k8s") deploy_kubernetes ;;
                "ecs") deploy_ecs ;;
                "local") deploy_local ;;
            esac
            ;;
        "status")
            check_status
            ;;
        "scale")
            scale_deployment
            ;;
        "destroy")
            destroy_deployment
            ;;
        "logs")
            show_logs
            ;;
    esac

    log_success "Operation completed successfully!"
}

# Execute main function
main "$@"
