# 🚀 Deployment Guide: Kubernetes & ECS with RoadRunner

## Overview

This guide provides comprehensive deployment options for the AI Blockchain Analytics platform using:

- **🐳 Docker** with RoadRunner for high-performance PHP
- **☸️ Kubernetes (K8s)** for scalable orchestration
- **🚀 AWS ECS** for managed container services
- **🐘 PostgreSQL** for primary database
- **🔴 Redis** for caching, sessions, and queues

## 🏗️ Architecture

### Container Strategy
- **RoadRunner**: High-performance PHP application server
- **Multi-stage builds**: Optimized production images
- **Health checks**: Automated service monitoring
- **Resource limits**: Efficient resource utilization

### Infrastructure Components
- **Application Layer**: Laravel with RoadRunner (3 replicas)
- **Worker Layer**: Queue processors (2 replicas)
- **Database**: PostgreSQL 15 with performance tuning
- **Cache**: Redis 7.2 with persistence
- **Load Balancer**: Nginx/ALB with SSL termination
- **Storage**: Persistent volumes for data and logs

## 🐳 Docker Configuration

### Production Dockerfile Features
```dockerfile
# Multi-stage build for optimization
FROM php:8.3-fpm-alpine AS base
# Install system dependencies
# Configure PHP extensions (Redis, PostgreSQL, etc.)
# Install RoadRunner binary
# Production optimizations (OPcache, etc.)
```

### Key Optimizations
- **OPcache JIT**: Enabled for PHP 8.3+ performance
- **Memory limits**: 512MB per container
- **Health checks**: HTTP endpoint monitoring
- **Security**: Non-root user, minimal privileges
- **Asset optimization**: Built and compressed frontend

## ☸️ Kubernetes Deployment

### Prerequisites
```bash
# Required tools
kubectl >= 1.24
docker >= 20.10
kubernetes cluster (EKS, GKE, AKS, or self-managed)

# AWS specific (for EKS)
aws-cli >= 2.0
eksctl (optional)
```

### Deployment Steps

#### 1. **Quick Deployment**
```bash
# Clone repository
git clone <repository-url>
cd ai-blockchain-analytics

# Configure environment
export DOCKER_REGISTRY="your-registry.com"
export IMAGE_TAG="v1.0.0"

# Deploy everything
./scripts/deploy-k8s.sh
```

#### 2. **Manual Step-by-Step**
```bash
# Build and push image
docker build -f Dockerfile.production -t your-registry.com/ai-blockchain-analytics:v1.0.0 .
docker push your-registry.com/ai-blockchain-analytics:v1.0.0

# Create namespace and resources
kubectl apply -f k8s/namespace.yaml

# Deploy storage
kubectl apply -f k8s/app/pvc.yaml
kubectl apply -f k8s/postgres/pvc.yaml
kubectl apply -f k8s/redis/pvc.yaml

# Deploy PostgreSQL
kubectl apply -f k8s/postgres/configmap.yaml
kubectl apply -f k8s/postgres/deployment.yaml
kubectl apply -f k8s/postgres/service.yaml

# Deploy Redis
kubectl apply -f k8s/redis/configmap.yaml
kubectl apply -f k8s/redis/deployment.yaml
kubectl apply -f k8s/redis/service.yaml

# Deploy application
kubectl apply -f k8s/app/secret.yaml    # ⚠️ Update secrets first!
kubectl apply -f k8s/app/configmap.yaml
kubectl apply -f k8s/app/deployment.yaml
kubectl apply -f k8s/app/service.yaml
kubectl apply -f k8s/app/ingress.yaml

# Run migrations
kubectl exec -n ai-blockchain-analytics deployment/ai-blockchain-analytics-app -- php artisan migrate --force
```

### Kubernetes Resources

#### **Namespace & Security**
```yaml
# Namespace with resource quotas and limits
apiVersion: v1
kind: Namespace
metadata:
  name: ai-blockchain-analytics
```

#### **Application Deployment**
```yaml
# 3 replicas with rolling updates
replicas: 3
strategy:
  type: RollingUpdate
  rollingUpdate:
    maxSurge: 1
    maxUnavailable: 1
```

#### **Storage Configuration**
- **EFS**: Shared storage for file uploads (`ReadWriteMany`)
- **EBS GP3**: Database storage with encryption (`ReadWriteOnce`)
- **Performance**: 4000 IOPS, 250 MB/s throughput

#### **Ingress & Load Balancing**
```yaml
# AWS Load Balancer Controller
kubernetes.io/ingress.class: alb
alb.ingress.kubernetes.io/scheme: internet-facing
alb.ingress.kubernetes.io/ssl-redirect: '443'
```

### Monitoring & Observability
```bash
# View deployment status
kubectl get all -n ai-blockchain-analytics

# Monitor logs
kubectl logs -f deployment/ai-blockchain-analytics-app -n ai-blockchain-analytics

# Resource usage
kubectl top pods -n ai-blockchain-analytics

# Execute commands
kubectl exec -it deployment/ai-blockchain-analytics-app -n ai-blockchain-analytics -- bash
```

## 🚀 AWS ECS Deployment

### Prerequisites
```bash
# Required tools
aws-cli >= 2.0
docker >= 20.10
ecs-cli (optional)

# AWS account with appropriate permissions
# ECR repository for container images
# VPC with public/private subnets
# RDS PostgreSQL instance
# ElastiCache Redis cluster
```

### Deployment Steps

#### 1. **Quick Deployment**
```bash
# Configure environment
export AWS_REGION="us-east-1"
export AWS_ACCOUNT_ID="123456789012"
export CLUSTER_NAME="ai-blockchain-analytics-cluster"

# Deploy everything
./scripts/deploy-ecs.sh
```

#### 2. **Manual Step-by-Step**
```bash
# Create ECR repository
aws ecr create-repository --repository-name ai-blockchain-analytics --region us-east-1

# Build and push image
aws ecr get-login-password --region us-east-1 | docker login --username AWS --password-stdin ACCOUNT_ID.dkr.ecr.us-east-1.amazonaws.com
docker build -f Dockerfile.production -t ai-blockchain-analytics:latest .
docker tag ai-blockchain-analytics:latest ACCOUNT_ID.dkr.ecr.us-east-1.amazonaws.com/ai-blockchain-analytics:latest
docker push ACCOUNT_ID.dkr.ecr.us-east-1.amazonaws.com/ai-blockchain-analytics:latest

# Create ECS cluster
aws ecs create-cluster --cluster-name ai-blockchain-analytics-cluster

# Register task definitions
aws ecs register-task-definition --cli-input-json file://ecs/task-definitions/app.json
aws ecs register-task-definition --cli-input-json file://ecs/task-definitions/worker.json

# Create services
aws ecs create-service --cli-input-json file://ecs/services/app-service.json
aws ecs create-service --cli-input-json file://ecs/services/worker-service.json
```

### ECS Task Definitions

#### **Application Task**
```json
{
  "family": "ai-blockchain-analytics-app",
  "requiresCompatibilities": ["FARGATE"],
  "cpu": "1024",
  "memory": "2048",
  "networkMode": "awsvpc"
}
```

#### **Worker Task**
```json
{
  "family": "ai-blockchain-analytics-worker",
  "requiresCompatibilities": ["FARGATE"],
  "cpu": "512",
  "memory": "1024"
}
```

### ECS Services

#### **Capacity Providers**
- **FARGATE**: Baseline capacity (1 instance)
- **FARGATE_SPOT**: Cost-optimized scaling (3x weight)

#### **Service Discovery**
- **Cloud Map**: DNS-based service discovery
- **Load Balancer**: Application Load Balancer integration
- **Health Checks**: ELB target group health monitoring

### AWS Resources Required

#### **Networking**
```bash
# VPC with public and private subnets
# Security groups for app, database, and cache
# NAT Gateway for private subnet internet access
# Application Load Balancer with target groups
```

#### **Database & Cache**
```bash
# RDS PostgreSQL 15 (Multi-AZ for production)
# ElastiCache Redis 7 (Cluster mode enabled)
# Parameter Store for secrets management
# CloudWatch for logging and monitoring
```

## 🐳 Docker Compose (Development/Testing)

### Quick Start
```bash
# Copy environment file
cp .env.example .env

# Update environment variables
nano .env

# Start all services
docker-compose -f docker-compose.production.yml up -d

# Run migrations
docker-compose exec app php artisan migrate

# View logs
docker-compose logs -f app
```

### Services Included
```yaml
services:
  app:        # Laravel with RoadRunner
  worker:     # Queue worker
  postgres:   # PostgreSQL 15
  redis:      # Redis 7.2
  nginx:      # Reverse proxy (optional)
```

## 🔧 Configuration Guide

### Environment Variables

#### **Required Secrets**
```bash
# Application
APP_KEY=base64:generated_key_here
JWT_SECRET=your_jwt_secret

# Database
DB_USERNAME=ai_blockchain_user
DB_PASSWORD=secure_password_here

# External APIs
COINGECKO_API_KEY=your_coingecko_key
ETHERSCAN_API_KEY=your_etherscan_key
SENTRY_LARAVEL_DSN=your_sentry_dsn
```

#### **Kubernetes Secrets**
```bash
# Create secrets
kubectl create secret generic ai-blockchain-analytics-secrets \
  --from-literal=APP_KEY="base64:your_key_here" \
  --from-literal=DB_PASSWORD="your_password" \
  --namespace=ai-blockchain-analytics
```

#### **AWS Systems Manager**
```bash
# Store secrets in Parameter Store
aws ssm put-parameter --name "/ai-blockchain-analytics/app-key" --value "base64:your_key" --type "SecureString"
aws ssm put-parameter --name "/ai-blockchain-analytics/db-password" --value "your_password" --type "SecureString"
```

### Performance Tuning

#### **RoadRunner Configuration**
```yaml
# .rr.yaml
http:
  pool:
    num_workers: 4        # Adjust based on CPU cores
    max_jobs: 1000        # Maximum jobs per worker
    supervisor:
      max_worker_memory: 512  # Memory limit per worker
```

#### **PostgreSQL Optimization**
```sql
-- Production settings
shared_buffers = 256MB
effective_cache_size = 1GB
work_mem = 4MB
maintenance_work_mem = 64MB
max_connections = 200
```

#### **Redis Configuration**
```conf
# Memory management
maxmemory 512mb
maxmemory-policy allkeys-lru

# Persistence
save 900 1
appendonly yes
appendfsync everysec
```

## 📊 Monitoring & Observability

### Metrics Collection
```bash
# RoadRunner metrics (Prometheus format)
curl http://localhost:2112/metrics

# Application health check
curl http://localhost:8080/health

# Database monitoring
kubectl exec -it postgres-pod -- psql -c "SELECT * FROM pg_stat_activity;"
```

### Log Aggregation
```bash
# Kubernetes
kubectl logs -f deployment/ai-blockchain-analytics-app

# ECS
aws logs tail /ecs/ai-blockchain-analytics-app --follow

# Docker Compose
docker-compose logs -f app
```

### Alerts & Notifications
- **High CPU/Memory usage**
- **Database connection failures**
- **Queue job failures**
- **Application errors (Sentry)**
- **Health check failures**

## 🔒 Security Best Practices

### Container Security
```dockerfile
# Non-root user
USER www-data

# Security context
securityContext:
  allowPrivilegeEscalation: false
  runAsNonRoot: true
  capabilities:
    drop: ["ALL"]
```

### Network Security
```yaml
# Kubernetes Network Policies
# Security Groups (AWS)
# Private subnets for databases
# WAF for public endpoints
```

### Secrets Management
```bash
# Kubernetes: Use external-secrets-operator
# AWS: Systems Manager Parameter Store
# Encryption at rest and in transit
# Regular secret rotation
```

## 🚨 Troubleshooting

### Common Issues

#### **Pod/Container Won't Start**
```bash
# Check logs
kubectl logs pod-name
docker logs container-name

# Check events
kubectl describe pod pod-name

# Resource constraints
kubectl top pods
```

#### **Database Connection Issues**
```bash
# Test connectivity
kubectl exec -it app-pod -- php artisan db:monitor

# Check service endpoints
kubectl get endpoints

# Verify secrets
kubectl get secret ai-blockchain-analytics-secrets -o yaml
```

#### **Performance Problems**
```bash
# Monitor resources
kubectl top pods
docker stats

# Check RoadRunner metrics
curl http://localhost:2112/metrics

# Database performance
kubectl exec -it postgres-pod -- psql -c "SELECT * FROM pg_stat_statements;"
```

## 🎯 Production Checklist

### Pre-Deployment
- [ ] Update all secrets and API keys
- [ ] Configure SSL certificates
- [ ] Set up monitoring and alerting
- [ ] Configure backup strategies
- [ ] Review resource limits and requests
- [ ] Test disaster recovery procedures

### Post-Deployment
- [ ] Verify all services are healthy
- [ ] Run database migrations
- [ ] Test application functionality
- [ ] Monitor logs for errors
- [ ] Validate metrics collection
- [ ] Perform load testing

### Maintenance
- [ ] Regular security updates
- [ ] Database backups and restoration tests
- [ ] Resource usage monitoring
- [ ] Cost optimization reviews
- [ ] Performance tuning
- [ ] Documentation updates

---

## 🎉 **DEPLOYMENT OPTIONS SUMMARY**

### ☸️ **Kubernetes (Recommended for Scalability)**
✅ **Auto-scaling**: HPA and VPA support  
✅ **High Availability**: Multi-zone deployment  
✅ **Rich Ecosystem**: Helm charts, operators  
✅ **Portability**: Works on any K8s cluster  

### 🚀 **AWS ECS (Recommended for AWS-native)**
✅ **Managed Service**: Less operational overhead  
✅ **AWS Integration**: Native ALB, RDS, ElastiCache  
✅ **Cost Optimization**: Fargate Spot instances  
✅ **Security**: IAM integration, VPC networking  

### 🐳 **Docker Compose (Development/Testing)**
✅ **Simple Setup**: One command deployment  
✅ **Local Development**: Fast iteration cycles  
✅ **Resource Efficient**: Minimal overhead  
✅ **Easy Debugging**: Direct container access  

Choose the deployment method that best fits your infrastructure requirements, team expertise, and operational preferences! 🚀