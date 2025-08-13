# 🚀 AI Blockchain Analytics - Complete Deployment Guide

## 📋 Overview

Comprehensive deployment solution for the AI Blockchain Analytics platform featuring:

- **🏃‍♂️ RoadRunner**: High-performance PHP application server
- **☸️ Kubernetes**: Container orchestration for scalable deployments
- **🚀 AWS ECS**: Managed container service with Fargate
- **🐘 PostgreSQL**: Primary database (self-hosted or RDS)
- **🔴 Redis**: Caching, sessions, and queues (self-hosted or ElastiCache)
- **📊 Monitoring**: Built-in metrics, health checks, and logging

## 🎯 Deployment Options

### 1. **Unified Deployment Script** (Recommended)

The main deployment entry point that supports all platforms:

```bash
# Deploy to Kubernetes production
./deploy-unified.sh k8s production deploy --registry your-registry.com --tag v1.2.3

# Deploy to AWS ECS production
./deploy-unified.sh ecs production deploy --account-id 123456789012 --tag v1.2.3

# Local development deployment
./deploy-unified.sh local development deploy

# Check deployment status
./deploy-unified.sh k8s production status

# Scale to 5 replicas
./deploy-unified.sh k8s production scale --replicas 5
```

### 2. **Kubernetes Deployment**

Production-ready Kubernetes deployment with auto-scaling:

```bash
# Direct Kubernetes deployment
cd k8s
./deploy-production.sh --registry your-registry.com --tag v1.2.3

# Custom domain deployment
./deploy-production.sh --domain your-analytics.com --tag v1.2.3

# Dry run to preview changes
./deploy-production.sh --dry-run
```

### 3. **AWS ECS Deployment**

Managed container service with AWS infrastructure:

```bash
# Direct ECS deployment
cd ecs
./deploy-production.sh --account-id 123456789012 --tag v1.2.3

# Custom VPC and subnets
./deploy-production.sh \
    --account-id 123456789012 \
    --vpc-id vpc-12345 \
    --subnet-ids subnet-12345,subnet-67890 \
    --tag v1.2.3

# Skip building images (use existing)
./deploy-production.sh --account-id 123456789012 --skip-build
```

## 🏗️ Architecture Components

### **RoadRunner Application Server**

- **High Performance**: Up to 10x faster than traditional PHP-FPM
- **Memory Efficient**: Persistent application state across requests
- **HTTP/2 Support**: Modern protocol with multiplexing
- **Built-in Metrics**: Prometheus-compatible metrics endpoint
- **Health Checks**: Comprehensive application and server health monitoring

**Configuration Files:**
- `.rr.yaml` - Production configuration
- `.rr.dev.yaml` - Development configuration
- `psr-worker.php` - PSR-7 worker implementation

### **Container Architecture**

```
┌─────────────────────┐    ┌─────────────────────┐    ┌─────────────────────┐
│   Web Application   │    │     Workers         │    │     Scheduler       │
│                     │    │                     │    │                     │
│ ┌─────────────────┐ │    │ ┌─────────────────┐ │    │ ┌─────────────────┐ │
│ │   RoadRunner    │ │    │ │   Horizon       │ │    │ │   Schedule      │ │
│ │   (4 workers)   │ │    │ │   (Queue Proc.) │ │    │ │   (Cron Jobs)   │ │
│ └─────────────────┘ │    │ └─────────────────┘ │    │ └─────────────────┘ │
│ ┌─────────────────┐ │    │ ┌─────────────────┐ │    │                     │
│ │     Nginx       │ │    │ │  Queue Workers  │ │    │                     │
│ │  (Load Balancer)│ │    │ │  (Background)   │ │    │                     │
│ └─────────────────┘ │    │ └─────────────────┘ │    │                     │
└─────────────────────┘    └─────────────────────┘    └─────────────────────┘
           │                           │                           │
           └─────────────┬─────────────┴─────────────┬─────────────┘
                         │                           │
                ┌─────────────────┐        ┌─────────────────┐
                │   PostgreSQL    │        │      Redis      │
                │   (Database)    │        │   (Cache/Queue) │
                └─────────────────┘        └─────────────────┘
```

### **Multi-Stage Docker Build**

The `Dockerfile.roadrunner` provides optimized multi-stage builds:

1. **Base Stage**: PHP 8.3 with extensions
2. **Frontend Stage**: Node.js for asset compilation
3. **RoadRunner Stage**: Binary installation
4. **Production Stage**: Optimized production image
5. **Worker Stage**: Specialized for background jobs
6. **Scheduler Stage**: Lightweight for cron tasks
7. **Development Stage**: Debug tools and hot reload

## 📊 Monitoring & Observability

### **Built-in Health Checks**

- **Application Health**: `/health` endpoint
- **Readiness Check**: `/ready` endpoint
- **RoadRunner Status**: `:2114/health` endpoint
- **Metrics**: `:2112/metrics` (Prometheus format)

### **Comprehensive Health Script**

The `docker/scripts/healthcheck.sh` provides:

- RoadRunner process monitoring
- HTTP endpoint validation with retries
- Database connectivity checks
- Redis connectivity verification
- Disk space monitoring
- Memory usage tracking
- Detailed logging and error reporting

### **Logging Configuration**

- **Production**: Structured JSON logs with rotation
- **Development**: Console logs with debug information
- **AWS CloudWatch**: Centralized logging for ECS deployments
- **Kubernetes**: Pod logs with persistent storage

## 🔐 Security Features

### **Container Security**

- **Non-root User**: All containers run as user `1000:1000`
- **Minimal Privileges**: Dropped capabilities (`CAP_DROP: ALL`)
- **Read-only Root**: Where possible
- **Security Headers**: HSTS, CSP, X-Frame-Options
- **Network Policies**: Kubernetes network isolation

### **AWS Security**

- **IAM Roles**: Least privilege access
- **Secrets Manager**: Encrypted secret storage
- **VPC Security**: Private subnets and security groups
- **SSL/TLS**: End-to-end encryption

### **Application Security**

- **Rate Limiting**: API and web endpoint protection
- **CORS Configuration**: Secure cross-origin requests
- **Input Validation**: Comprehensive request validation
- **Authentication**: JWT and session-based auth

## 🚀 Performance Optimizations

### **RoadRunner Configuration**

**Production Settings:**
- 4 workers with max 8 for auto-scaling
- 64 max jobs per worker
- 60-second job timeouts
- Memory limit: 512MB per worker
- HTTP/2 enabled
- Gzip compression (level 6)

**Development Settings:**
- 2 workers for faster startup
- 16 max jobs per worker
- 30-second timeouts
- Hot reload enabled
- Debug logging

### **Database Optimizations**

**PostgreSQL:**
- Connection pooling
- Query optimization
- Index strategies
- Persistent volumes for data

**Redis:**
- Memory optimization (2GB limit)
- LRU eviction policy
- Persistence with AOF
- Connection pooling

### **Container Optimizations**

- **Multi-stage builds**: Smaller production images
- **Layer caching**: Efficient build process
- **Resource limits**: CPU and memory constraints
- **Health checks**: Fast failure detection

## 📈 Scaling & High Availability

### **Kubernetes Scaling**

**Horizontal Pod Autoscaler (HPA):**
- Min replicas: 3
- Max replicas: 10
- CPU threshold: 70%
- Memory threshold: 80%
- Scale-down stabilization: 5 minutes

**Vertical Pod Autoscaler (VPA):**
- Automatic resource recommendation
- Memory and CPU optimization

**Pod Disruption Budget:**
- Minimum available: 2 pods
- Ensures high availability during updates

### **ECS Scaling**

**Auto Scaling:**
- Target CPU utilization: 70%
- Scale-out cooldown: 300 seconds
- Scale-in cooldown: 300 seconds
- Fargate Spot instances for cost optimization

**Service Discovery:**
- AWS Cloud Map integration
- Load balancer health checks
- Cross-AZ distribution

### **Database High Availability**

**Kubernetes:**
- StatefulSets for ordered deployment
- Persistent volume claims
- Backup and restore procedures

**AWS RDS:**
- Multi-AZ deployment
- Automated backups
- Read replicas for scaling
- Performance Insights

## 🛠️ Configuration Management

### **Environment Variables**

**Required for All Deployments:**
```bash
# Application
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...

# Database
DB_CONNECTION=pgsql
DB_HOST=postgres-service
DB_PORT=5432
DB_DATABASE=ai_blockchain_analytics
DB_USERNAME=postgres
DB_PASSWORD=secure_password

# Cache & Queue
REDIS_HOST=redis-service
REDIS_PORT=6379
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# RoadRunner
RR_HTTP_ADDRESS=:8080
RR_HTTP_NUM_WORKERS=4
RR_HTTP_MAX_JOBS=64
```

**AWS-Specific Variables:**
```bash
AWS_ACCOUNT_ID=123456789012
AWS_REGION=us-east-1
RDS_ENDPOINT=db-cluster.cluster-xxx.us-east-1.rds.amazonaws.com
ELASTICACHE_ENDPOINT=redis-cluster.xxx.cache.amazonaws.com
```

**Kubernetes-Specific Variables:**
```bash
DOCKER_REGISTRY=your-registry.com
IMAGE_TAG=v1.2.3
NAMESPACE=ai-blockchain-analytics-production
DOMAIN=ai-analytics.example.com
```

### **Secrets Management**

**Kubernetes Secrets:**
```bash
kubectl create secret generic app-secrets \
    --from-literal=APP_KEY="$(openssl rand -base64 32)" \
    --from-literal=JWT_SECRET="$(openssl rand -base64 64)" \
    --from-literal=DB_PASSWORD="secure_password" \
    --namespace ai-blockchain-analytics-production
```

**AWS Secrets Manager:**
```bash
aws secretsmanager create-secret \
    --name "ai-blockchain-analytics/app-key" \
    --secret-string "$(openssl rand -base64 32)"
```

## 🔧 Troubleshooting

### **Common Issues**

**Build Failures:**
```bash
# Clear Docker cache
docker system prune -a

# Rebuild without cache
docker build --no-cache -t image:tag .
```

**Deployment Failures:**
```bash
# Check Kubernetes events
kubectl get events --sort-by='.lastTimestamp' -n namespace

# Check ECS service events
aws ecs describe-services --cluster cluster-name --services service-name
```

**Health Check Failures:**
```bash
# Test health endpoint
curl -f http://localhost:8080/health

# Check RoadRunner status
curl http://localhost:2114/health

# View application logs
kubectl logs deployment/app -n namespace
```

### **Performance Issues**

**High Memory Usage:**
```bash
# Check RoadRunner metrics
curl http://localhost:2112/metrics | grep memory

# Adjust worker memory limits
# Edit .rr.yaml: max_worker_memory: 256
```

**Slow Database Queries:**
```bash
# Enable query logging
# Check for missing indexes
# Optimize N+1 queries
```

**Queue Backlog:**
```bash
# Scale workers
kubectl scale deployment worker --replicas=5

# Check Horizon status
php artisan horizon:status
```

### **Debug Mode**

**Enable Debug Logging:**
```bash
# Kubernetes
kubectl set env deployment/app APP_DEBUG=true LOG_LEVEL=debug

# ECS
# Update task definition with environment variables
```

## 🚀 Deployment Commands Reference

### **Quick Start Commands**

```bash
# 1. Clone repository
git clone <repository-url>
cd ai-blockchain-analytics

# 2. Set environment variables
export DOCKER_REGISTRY="your-registry.com"
export IMAGE_TAG="v1.2.3"
export AWS_ACCOUNT_ID="123456789012"

# 3. Deploy to Kubernetes
./deploy-unified.sh k8s production deploy

# 4. Deploy to ECS
./deploy-unified.sh ecs production deploy

# 5. Local development
./deploy-unified.sh local development deploy
```

### **Management Commands**

```bash
# Check status
./deploy-unified.sh k8s production status
./deploy-unified.sh ecs production status

# Scale application
./deploy-unified.sh k8s production scale --replicas 5

# View logs
./deploy-unified.sh k8s production logs
./deploy-unified.sh ecs production logs

# Update deployment
./deploy-unified.sh k8s production deploy --tag v1.2.4

# Destroy deployment
./deploy-unified.sh k8s production destroy --force
```

### **Maintenance Commands**

```bash
# Clear caches
kubectl exec deployment/app -- php artisan cache:clear
kubectl exec deployment/app -- php artisan config:cache

# Run migrations
kubectl exec deployment/app -- php artisan migrate --force

# Restart workers
kubectl rollout restart deployment/worker

# Backup database
kubectl exec deployment/postgres -- pg_dump database > backup.sql
```

## 📚 Additional Resources

### **Documentation Files**

- `README.md` - Project overview and setup
- `DEPLOYMENT_GUIDE.md` - This comprehensive guide
- `k8s/production-deployment.yaml` - Complete Kubernetes manifests
- `ecs/production-task-definition.json` - ECS task definitions
- `.rr.yaml` - RoadRunner production configuration
- `docker/nginx/nginx.conf` - Nginx configuration

### **Monitoring Dashboards**

- **Grafana**: Custom dashboards for RoadRunner metrics
- **Prometheus**: Metrics collection and alerting
- **AWS CloudWatch**: ECS container insights
- **Kubernetes Dashboard**: Cluster monitoring

### **Best Practices**

1. **Use specific image tags** (not `latest`) for production
2. **Monitor resource usage** and set appropriate limits
3. **Implement backup strategies** for databases
4. **Test deployments** in staging environments first
5. **Use secrets management** for sensitive data
6. **Enable monitoring and alerting** for all components
7. **Document configuration changes** and deployment procedures
8. **Implement CI/CD pipelines** for automated deployments

---

## 🎉 **DEPLOYMENT COMPLETE!**

✅ **Unified deployment script** supporting K8s, ECS, and local development  
✅ **RoadRunner optimization** with production-ready configuration  
✅ **Container orchestration** with auto-scaling and high availability  
✅ **Infrastructure as Code** with complete manifest files  
✅ **Security hardening** with best practices implementation  
✅ **Comprehensive monitoring** with health checks and metrics  
✅ **Performance optimization** for high-traffic production workloads  

**🚀 Choose your deployment method and scale with confidence!**