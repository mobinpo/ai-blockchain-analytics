#!/bin/bash

# AI Blockchain Analytics v0.9.0 - Kubernetes Deployment Script
# Deploys RoadRunner application with PostgreSQL and Redis

set -e

# Configuration
NAMESPACE="ai-blockchain-analytics"
APP_NAME="ai-blockchain-analytics"
VERSION="v0.9.0"
DOMAIN="${DOMAIN:-analytics.yourcompany.com}"
DB_PASSWORD="${DB_PASSWORD:-$(openssl rand -base64 32)}"
REDIS_PASSWORD="${REDIS_PASSWORD:-$(openssl rand -base64 32)}"
APP_KEY="${APP_KEY:-base64:$(openssl rand -base64 32)}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

success() {
    echo -e "${GREEN}✅ $1${NC}"
}

warn() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

error() {
    echo -e "${RED}❌ $1${NC}"
    exit 1
}

banner() {
    echo ""
    echo "╔══════════════════════════════════════════════════════════════╗"
    echo "║                                                              ║"
    echo "║       AI Blockchain Analytics Platform v0.9.0               ║"
    echo "║              Kubernetes Deployment                          ║"
    echo "║                                                              ║"
    echo "╚══════════════════════════════════════════════════════════════╝"
    echo ""
}

check_prerequisites() {
    log "Checking prerequisites..."
    
    # Check kubectl
    if ! command -v kubectl &> /dev/null; then
        error "kubectl is not installed or not in PATH"
    fi
    
    # Check cluster connection
    if ! kubectl cluster-info &> /dev/null; then
        error "Cannot connect to Kubernetes cluster"
    fi
    
    # Check if running on correct git tag
    current_tag=$(git describe --tags --exact-match 2>/dev/null || echo "")
    if [[ "$current_tag" != "v0.9.0" ]]; then
        warn "Not on v0.9.0 tag. Current: ${current_tag:-'No tag'}"
        read -p "Continue anyway? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            error "Deployment cancelled"
        fi
    fi
    
    success "Prerequisites check passed"
}

create_namespace() {
    log "Creating namespace: $NAMESPACE"
    
    cat <<EOF | kubectl apply -f -
apiVersion: v1
kind: Namespace
metadata:
  name: $NAMESPACE
  labels:
    app: $APP_NAME
    version: $VERSION
EOF
    
    success "Namespace created"
}

create_secrets() {
    log "Creating application secrets..."
    
    # Generate JWT secret if not provided
    JWT_SECRET="${JWT_SECRET:-$(openssl rand -base64 64)}"
    
    # Create main application secret
    kubectl create secret generic app-secrets \
        --from-literal=APP_KEY="$APP_KEY" \
        --from-literal=DB_PASSWORD="$DB_PASSWORD" \
        --from-literal=REDIS_PASSWORD="$REDIS_PASSWORD" \
        --from-literal=JWT_SECRET="$JWT_SECRET" \
        --from-literal=MAILGUN_SECRET="${MAILGUN_SECRET:-}" \
        --from-literal=SENTRY_DSN="${SENTRY_DSN:-}" \
        -n $NAMESPACE \
        --dry-run=client -o yaml | kubectl apply -f -
    
    success "Secrets created"
}

deploy_postgresql() {
    log "Deploying PostgreSQL..."
    
    cat <<EOF | kubectl apply -f -
# PostgreSQL ConfigMap
apiVersion: v1
kind: ConfigMap
metadata:
  name: postgres-config
  namespace: $NAMESPACE
data:
  POSTGRES_DB: "ai_blockchain_analytics"
  POSTGRES_USER: "ai_blockchain_user"
  PGDATA: "/var/lib/postgresql/data/pgdata"
---
# PostgreSQL PVC
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
      storage: 20Gi
  storageClassName: gp2
---
# PostgreSQL Deployment
apiVersion: apps/v1
kind: Deployment
metadata:
  name: postgres
  namespace: $NAMESPACE
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
    spec:
      containers:
      - name: postgres
        image: postgres:15-alpine
        ports:
        - containerPort: 5432
        env:
        - name: POSTGRES_DB
          valueFrom:
            configMapKeyRef:
              name: postgres-config
              key: POSTGRES_DB
        - name: POSTGRES_USER
          valueFrom:
            configMapKeyRef:
              name: postgres-config
              key: POSTGRES_USER
        - name: POSTGRES_PASSWORD
          valueFrom:
            secretKeyRef:
              name: app-secrets
              key: DB_PASSWORD
        - name: PGDATA
          valueFrom:
            configMapKeyRef:
              name: postgres-config
              key: PGDATA
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
        livenessProbe:
          exec:
            command:
            - pg_isready
            - -U
            - ai_blockchain_user
            - -d
            - ai_blockchain_analytics
          initialDelaySeconds: 30
          periodSeconds: 10
        readinessProbe:
          exec:
            command:
            - pg_isready
            - -U
            - ai_blockchain_user
            - -d
            - ai_blockchain_analytics
          initialDelaySeconds: 5
          periodSeconds: 5
      volumes:
      - name: postgres-storage
        persistentVolumeClaim:
          claimName: postgres-pvc
---
# PostgreSQL Service
apiVersion: v1
kind: Service
metadata:
  name: postgres
  namespace: $NAMESPACE
spec:
  selector:
    app: postgres
  ports:
  - port: 5432
    targetPort: 5432
  type: ClusterIP
EOF
    
    success "PostgreSQL deployed"
}

deploy_redis() {
    log "Deploying Redis..."
    
    cat <<EOF | kubectl apply -f -
# Redis ConfigMap
apiVersion: v1
kind: ConfigMap
metadata:
  name: redis-config
  namespace: $NAMESPACE
data:
  redis.conf: |
    maxmemory 256mb
    maxmemory-policy allkeys-lru
    save 900 1
    save 300 10
    save 60 10000
    rdbcompression yes
    rdbchecksum yes
    requirepass REDIS_PASSWORD_PLACEHOLDER
---
# Redis PVC
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
      storage: 5Gi
  storageClassName: gp2
---
# Redis Deployment
apiVersion: apps/v1
kind: Deployment
metadata:
  name: redis
  namespace: $NAMESPACE
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
    spec:
      initContainers:
      - name: config-setup
        image: redis:7-alpine
        command: ['sh', '-c']
        args:
        - |
          cp /tmp/redis/redis.conf /usr/local/etc/redis/redis.conf
          sed -i "s/REDIS_PASSWORD_PLACEHOLDER/\$REDIS_PASSWORD/" /usr/local/etc/redis/redis.conf
        env:
        - name: REDIS_PASSWORD
          valueFrom:
            secretKeyRef:
              name: app-secrets
              key: REDIS_PASSWORD
        volumeMounts:
        - name: redis-config
          mountPath: /tmp/redis
        - name: redis-config-final
          mountPath: /usr/local/etc/redis
      containers:
      - name: redis
        image: redis:7-alpine
        command: ['redis-server', '/usr/local/etc/redis/redis.conf']
        ports:
        - containerPort: 6379
        volumeMounts:
        - name: redis-storage
          mountPath: /data
        - name: redis-config-final
          mountPath: /usr/local/etc/redis
        resources:
          requests:
            memory: "256Mi"
            cpu: "100m"
          limits:
            memory: "512Mi"
            cpu: "500m"
        livenessProbe:
          exec:
            command:
            - redis-cli
            - -a
            - "\$(REDIS_PASSWORD)"
            - ping
          initialDelaySeconds: 30
          periodSeconds: 10
          env:
          - name: REDIS_PASSWORD
            valueFrom:
              secretKeyRef:
                name: app-secrets
                key: REDIS_PASSWORD
        readinessProbe:
          exec:
            command:
            - redis-cli
            - -a
            - "\$(REDIS_PASSWORD)"
            - ping
          initialDelaySeconds: 5
          periodSeconds: 5
          env:
          - name: REDIS_PASSWORD
            valueFrom:
              secretKeyRef:
                name: app-secrets
                key: REDIS_PASSWORD
      volumes:
      - name: redis-storage
        persistentVolumeClaim:
          claimName: redis-pvc
      - name: redis-config
        configMap:
          name: redis-config
      - name: redis-config-final
        emptyDir: {}
---
# Redis Service
apiVersion: v1
kind: Service
metadata:
  name: redis
  namespace: $NAMESPACE
spec:
  selector:
    app: redis
  ports:
  - port: 6379
    targetPort: 6379
  type: ClusterIP
EOF
    
    success "Redis deployed"
}

build_and_push_image() {
    log "Building and pushing RoadRunner application image..."
    
    # Use the production Dockerfile
    if [[ ! -f "Dockerfile.production" ]]; then
        error "Dockerfile.production not found"
    fi
    
    # Build image
    IMAGE_TAG="${DOCKER_REGISTRY:-your-registry.com}/${APP_NAME}:${VERSION}"
    
    log "Building image: $IMAGE_TAG"
    docker build -f Dockerfile.production -t "$IMAGE_TAG" .
    
    log "Pushing image: $IMAGE_TAG"
    docker push "$IMAGE_TAG"
    
    success "Image built and pushed: $IMAGE_TAG"
    echo "IMAGE_TAG=$IMAGE_TAG" > .env.k8s
}

deploy_application() {
    log "Deploying RoadRunner application..."
    
    # Load image tag
    if [[ -f ".env.k8s" ]]; then
        source .env.k8s
    else
        IMAGE_TAG="${DOCKER_REGISTRY:-your-registry.com}/${APP_NAME}:${VERSION}"
    fi
    
    cat <<EOF | kubectl apply -f -
# Application ConfigMap
apiVersion: v1
kind: ConfigMap
metadata:
  name: app-config
  namespace: $NAMESPACE
data:
  APP_NAME: "AI Blockchain Analytics"
  APP_ENV: "production"
  APP_DEBUG: "false"
  APP_URL: "https://$DOMAIN"
  LOG_CHANNEL: "stack"
  LOG_LEVEL: "info"
  
  DB_CONNECTION: "pgsql"
  DB_HOST: "postgres"
  DB_PORT: "5432"
  DB_DATABASE: "ai_blockchain_analytics"
  DB_USERNAME: "ai_blockchain_user"
  
  CACHE_DRIVER: "redis"
  SESSION_DRIVER: "redis"
  QUEUE_CONNECTION: "redis"
  
  REDIS_HOST: "redis"
  REDIS_PORT: "6379"
  
  MAIL_MAILER: "mailgun"
  MAILGUN_DOMAIN: "$DOMAIN"
  MAILGUN_ENDPOINT: "api.mailgun.net"
  
  ONBOARDING_ENABLED: "true"
  ONBOARDING_FROM_EMAIL: "welcome@$DOMAIN"
  
  SANCTUM_STATEFUL_DOMAINS: "$DOMAIN"
  SESSION_SECURE_COOKIE: "true"
  
  TELESCOPE_ENABLED: "false"
---
# Application Deployment
apiVersion: apps/v1
kind: Deployment
metadata:
  name: app
  namespace: $NAMESPACE
spec:
  replicas: 3
  strategy:
    type: RollingUpdate
    rollingUpdate:
      maxSurge: 1
      maxUnavailable: 0
  selector:
    matchLabels:
      app: $APP_NAME
      tier: app
  template:
    metadata:
      labels:
        app: $APP_NAME
        tier: app
        version: $VERSION
    spec:
      initContainers:
      - name: wait-for-db
        image: postgres:15-alpine
        command: ['sh', '-c']
        args:
        - |
          until pg_isready -h postgres -p 5432 -U ai_blockchain_user; do
            echo "Waiting for database..."
            sleep 2
          done
          echo "Database is ready!"
      - name: wait-for-redis
        image: redis:7-alpine
        command: ['sh', '-c']
        args:
        - |
          until redis-cli -h redis -p 6379 -a "\$REDIS_PASSWORD" ping; do
            echo "Waiting for Redis..."
            sleep 2
          done
          echo "Redis is ready!"
        env:
        - name: REDIS_PASSWORD
          valueFrom:
            secretKeyRef:
              name: app-secrets
              key: REDIS_PASSWORD
      containers:
      - name: app
        image: $IMAGE_TAG
        ports:
        - containerPort: 8080
          name: http
        env:
        - name: APP_KEY
          valueFrom:
            secretKeyRef:
              name: app-secrets
              key: APP_KEY
        - name: DB_PASSWORD
          valueFrom:
            secretKeyRef:
              name: app-secrets
              key: DB_PASSWORD
        - name: REDIS_PASSWORD
          valueFrom:
            secretKeyRef:
              name: app-secrets
              key: REDIS_PASSWORD
        - name: MAILGUN_SECRET
          valueFrom:
            secretKeyRef:
              name: app-secrets
              key: MAILGUN_SECRET
        - name: SENTRY_LARAVEL_DSN
          valueFrom:
            secretKeyRef:
              name: app-secrets
              key: SENTRY_DSN
        envFrom:
        - configMapRef:
            name: app-config
        resources:
          requests:
            memory: "512Mi"
            cpu: "250m"
          limits:
            memory: "1Gi"
            cpu: "1000m"
        livenessProbe:
          httpGet:
            path: /api/health
            port: 8080
          initialDelaySeconds: 60
          periodSeconds: 30
          timeoutSeconds: 10
        readinessProbe:
          httpGet:
            path: /api/health
            port: 8080
          initialDelaySeconds: 30
          periodSeconds: 10
          timeoutSeconds: 5
        lifecycle:
          preStop:
            exec:
              command: ["/bin/sh", "-c", "sleep 15"]
---
# Application Service
apiVersion: v1
kind: Service
metadata:
  name: app-service
  namespace: $NAMESPACE
spec:
  selector:
    app: $APP_NAME
    tier: app
  ports:
  - port: 80
    targetPort: 8080
    protocol: TCP
  type: ClusterIP
---
# Horizontal Pod Autoscaler
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: app-hpa
  namespace: $NAMESPACE
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: app
  minReplicas: 3
  maxReplicas: 10
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
    
    success "Application deployed"
}

deploy_worker() {
    log "Deploying Laravel Horizon worker..."
    
    # Load image tag
    if [[ -f ".env.k8s" ]]; then
        source .env.k8s
    else
        IMAGE_TAG="${DOCKER_REGISTRY:-your-registry.com}/${APP_NAME}:${VERSION}"
    fi
    
    cat <<EOF | kubectl apply -f -
# Worker Deployment
apiVersion: apps/v1
kind: Deployment
metadata:
  name: worker
  namespace: $NAMESPACE
spec:
  replicas: 2
  strategy:
    type: RollingUpdate
    rollingUpdate:
      maxSurge: 1
      maxUnavailable: 0
  selector:
    matchLabels:
      app: $APP_NAME
      tier: worker
  template:
    metadata:
      labels:
        app: $APP_NAME
        tier: worker
        version: $VERSION
    spec:
      containers:
      - name: worker
        image: $IMAGE_TAG
        command: ["php", "artisan", "horizon"]
        env:
        - name: APP_KEY
          valueFrom:
            secretKeyRef:
              name: app-secrets
              key: APP_KEY
        - name: DB_PASSWORD
          valueFrom:
            secretKeyRef:
              name: app-secrets
              key: DB_PASSWORD
        - name: REDIS_PASSWORD
          valueFrom:
            secretKeyRef:
              name: app-secrets
              key: REDIS_PASSWORD
        - name: MAILGUN_SECRET
          valueFrom:
            secretKeyRef:
              name: app-secrets
              key: MAILGUN_SECRET
        envFrom:
        - configMapRef:
            name: app-config
        resources:
          requests:
            memory: "256Mi"
            cpu: "100m"
          limits:
            memory: "512Mi"
            cpu: "500m"
        livenessProbe:
          exec:
            command:
            - php
            - artisan
            - horizon:status
          initialDelaySeconds: 60
          periodSeconds: 30
        lifecycle:
          preStop:
            exec:
              command: ["php", "artisan", "horizon:terminate"]
EOF
    
    success "Worker deployed"
}

deploy_scheduler() {
    log "Deploying Laravel scheduler..."
    
    # Load image tag
    if [[ -f ".env.k8s" ]]; then
        source .env.k8s
    else
        IMAGE_TAG="${DOCKER_REGISTRY:-your-registry.com}/${APP_NAME}:${VERSION}"
    fi
    
    cat <<EOF | kubectl apply -f -
# Scheduler Deployment
apiVersion: apps/v1
kind: Deployment
metadata:
  name: scheduler
  namespace: $NAMESPACE
spec:
  replicas: 1
  strategy:
    type: Recreate
  selector:
    matchLabels:
      app: $APP_NAME
      tier: scheduler
  template:
    metadata:
      labels:
        app: $APP_NAME
        tier: scheduler
        version: $VERSION
    spec:
      containers:
      - name: scheduler
        image: $IMAGE_TAG
        command: ["php", "artisan", "schedule:work", "--verbose"]
        env:
        - name: APP_KEY
          valueFrom:
            secretKeyRef:
              name: app-secrets
              key: APP_KEY
        - name: DB_PASSWORD
          valueFrom:
            secretKeyRef:
              name: app-secrets
              key: DB_PASSWORD
        - name: REDIS_PASSWORD
          valueFrom:
            secretKeyRef:
              name: app-secrets
              key: REDIS_PASSWORD
        - name: MAILGUN_SECRET
          valueFrom:
            secretKeyRef:
              name: app-secrets
              key: MAILGUN_SECRET
        envFrom:
        - configMapRef:
            name: app-config
        resources:
          requests:
            memory: "128Mi"
            cpu: "50m"
          limits:
            memory: "256Mi"
            cpu: "200m"
EOF
    
    success "Scheduler deployed"
}

deploy_ingress() {
    log "Deploying ingress..."
    
    cat <<EOF | kubectl apply -f -
# Ingress
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: app-ingress
  namespace: $NAMESPACE
  annotations:
    kubernetes.io/ingress.class: "nginx"
    cert-manager.io/cluster-issuer: "letsencrypt-prod"
    nginx.ingress.kubernetes.io/ssl-redirect: "true"
    nginx.ingress.kubernetes.io/force-ssl-redirect: "true"
    nginx.ingress.kubernetes.io/proxy-body-size: "10m"
    nginx.ingress.kubernetes.io/proxy-connect-timeout: "60"
    nginx.ingress.kubernetes.io/proxy-send-timeout: "60"
    nginx.ingress.kubernetes.io/proxy-read-timeout: "60"
spec:
  tls:
  - hosts:
    - $DOMAIN
    secretName: app-tls
  rules:
  - host: $DOMAIN
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
    
    success "Ingress deployed"
}

run_migrations() {
    log "Running database migrations..."
    
    # Wait for application pods to be ready
    kubectl wait --for=condition=ready pod -l app=$APP_NAME,tier=app -n $NAMESPACE --timeout=300s
    
    # Get first app pod
    APP_POD=$(kubectl get pods -l app=$APP_NAME,tier=app -n $NAMESPACE -o jsonpath='{.items[0].metadata.name}')
    
    log "Running migrations in pod: $APP_POD"
    kubectl exec -n $NAMESPACE $APP_POD -- php artisan migrate --force
    
    log "Seeding famous contracts..."
    kubectl exec -n $NAMESPACE $APP_POD -- php artisan db:seed --class=FamousContractsSeeder --force
    
    log "Optimizing application..."
    kubectl exec -n $NAMESPACE $APP_POD -- php artisan config:cache
    kubectl exec -n $NAMESPACE $APP_POD -- php artisan route:cache
    kubectl exec -n $NAMESPACE $APP_POD -- php artisan view:cache
    
    success "Database setup completed"
}

verify_deployment() {
    log "Verifying deployment..."
    
    # Check all pods are running
    kubectl get pods -n $NAMESPACE
    
    # Check services
    kubectl get services -n $NAMESPACE
    
    # Check ingress
    kubectl get ingress -n $NAMESPACE
    
    # Test application health
    log "Testing application health..."
    kubectl wait --for=condition=ready pod -l app=$APP_NAME,tier=app -n $NAMESPACE --timeout=300s
    
    APP_POD=$(kubectl get pods -l app=$APP_NAME,tier=app -n $NAMESPACE -o jsonpath='{.items[0].metadata.name}')
    
    if kubectl exec -n $NAMESPACE $APP_POD -- curl -f http://localhost:8080/api/health > /dev/null 2>&1; then
        success "Application health check passed"
    else
        warn "Application health check failed"
    fi
    
    success "Deployment verification completed"
}

cleanup_on_error() {
    if [[ $? -ne 0 ]]; then
        error "Deployment failed. Check logs with: kubectl logs -n $NAMESPACE -l app=$APP_NAME"
    fi
}

main() {
    trap cleanup_on_error ERR
    
    banner
    
    log "Starting Kubernetes deployment for AI Blockchain Analytics v0.9.0"
    log "Domain: $DOMAIN"
    log "Namespace: $NAMESPACE"
    
    check_prerequisites
    create_namespace
    create_secrets
    deploy_postgresql
    deploy_redis
    
    # Build and push image (comment out if using pre-built image)
    if [[ "${SKIP_BUILD:-false}" != "true" ]]; then
        build_and_push_image
    fi
    
    deploy_application
    deploy_worker
    deploy_scheduler
    deploy_ingress
    
    # Wait for services to be ready
    log "Waiting for services to be ready..."
    sleep 30
    
    run_migrations
    verify_deployment
    
    success "🎉 Kubernetes deployment completed successfully!"
    
    echo ""
    echo "📋 Deployment Summary:"
    echo "• Namespace: $NAMESPACE"
    echo "• Domain: https://$DOMAIN"
    echo "• Application: 3 replicas with auto-scaling"
    echo "• Workers: 2 Horizon workers"
    echo "• Scheduler: 1 Laravel scheduler"
    echo "• Database: PostgreSQL with 20GB storage"
    echo "• Cache: Redis with 5GB storage"
    echo ""
    echo "🔍 Useful commands:"
    echo "• Check pods: kubectl get pods -n $NAMESPACE"
    echo "• Check logs: kubectl logs -n $NAMESPACE -l app=$APP_NAME"
    echo "• Scale app: kubectl scale deployment app --replicas=5 -n $NAMESPACE"
    echo "• Access shell: kubectl exec -it -n $NAMESPACE \$(kubectl get pod -l app=$APP_NAME,tier=app -n $NAMESPACE -o jsonpath='{.items[0].metadata.name}') -- /bin/bash"
    echo ""
    echo "🌐 Your application should be available at: https://$DOMAIN"
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --domain)
            DOMAIN="$2"
            shift 2
            ;;
        --namespace)
            NAMESPACE="$2"
            shift 2
            ;;
        --skip-build)
            SKIP_BUILD="true"
            shift
            ;;
        --registry)
            DOCKER_REGISTRY="$2"
            shift 2
            ;;
        -h|--help)
            echo "Usage: $0 [OPTIONS]"
            echo "Options:"
            echo "  --domain DOMAIN        Set the application domain (default: analytics.yourcompany.com)"
            echo "  --namespace NAMESPACE  Set the Kubernetes namespace (default: ai-blockchain-analytics)"
            echo "  --skip-build          Skip building and pushing Docker image"
            echo "  --registry REGISTRY   Set Docker registry (default: your-registry.com)"
            echo "  -h, --help            Show this help message"
            exit 0
            ;;
        *)
            error "Unknown option: $1"
            ;;
    esac
done

# Run main function
main "$@"
