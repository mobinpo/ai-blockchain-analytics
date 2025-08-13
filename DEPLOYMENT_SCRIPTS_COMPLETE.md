# 🚀 AI Blockchain Analytics - Complete Deployment Scripts

## Overview

I've created comprehensive deployment scripts for both **Kubernetes (K8s)** and **AWS ECS** with optimized RoadRunner containers, Redis, and PostgreSQL. The system includes intelligent platform detection, environment-specific configurations, and comprehensive testing.

## 📋 What's Been Created

### ✅ **Universal Deployment Script**
- **File**: `deploy.sh`
- **Purpose**: Main entry point that auto-detects platform and orchestrates deployments
- **Features**: Platform detection, environment validation, comprehensive logging

### ✅ **Kubernetes Deployment**
- **Files**: 
  - `k8s/enhanced-roadrunner-deployment.yaml` - Complete K8s manifests
  - `k8s/deploy-roadrunner-enhanced.sh` - Kubernetes deployment script
- **Features**: Auto-scaling, monitoring, persistent storage, ingress

### ✅ **ECS Deployment**
- **Files**: 
  - `ecs/enhanced-roadrunner-deployment.sh` - Complete ECS deployment script
- **Features**: Fargate, RDS PostgreSQL, ElastiCache Redis, auto-scaling, ALB

### ✅ **Local Development**
- **File**: `docker-compose.roadrunner.yml`
- **Purpose**: Local RoadRunner testing with all services

### ✅ **Testing & Validation**
- **File**: `test-deployment.sh`
- **Purpose**: Comprehensive testing of all deployment configurations

### ✅ **Monitoring Setup**
- **File**: `monitoring/prometheus.yml`
- **Purpose**: Prometheus configuration for RoadRunner metrics

## 🚀 Quick Start

### 1. **Deploy to Kubernetes**
```bash
# Auto-detect and deploy to Kubernetes production
./deploy.sh k8s production deploy

# Deploy to Kubernetes staging
./deploy.sh kubernetes staging deploy

# Deploy to Kubernetes development
./deploy.sh k8s development deploy
```

### 2. **Deploy to AWS ECS**
```bash
# Deploy to ECS production
./deploy.sh ecs production deploy

# Deploy to ECS staging  
./deploy.sh aws staging deploy

# Build and push images only
./deploy.sh ecs production build
```

### 3. **Local Development**
```bash
# Start local RoadRunner environment
docker-compose -f docker-compose.roadrunner.yml up -d

# View logs
docker-compose -f docker-compose.roadrunner.yml logs -f app

# Stop environment
docker-compose -f docker-compose.roadrunner.yml down
```

### 4. **Test Deployment**
```bash
# Test all platforms
./test-deployment.sh

# Test Kubernetes only
./test-deployment.sh k8s production

# Test ECS prerequisites
./test-deployment.sh ecs production prerequisites

# Test local setup
./test-deployment.sh local development
```

## 📊 Architecture Overview

### **Kubernetes Architecture**
```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Ingress       │    │  Load Balancer  │    │   Auto Scaler   │
│   (nginx)       │    │     (ALB)       │    │     (HPA)       │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│  RoadRunner     │    │    Horizon      │    │   Scheduler     │
│  App Pods       │    │  Worker Pods    │    │    Pod          │
│  (3 replicas)   │    │  (2 replicas)   │    │  (1 replica)    │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         └───────────────────────┼───────────────────────┘
                                 │
         ┌─────────────────┐    ┌─────────────────┐
         │   PostgreSQL    │    │     Redis       │
         │     Pod         │    │     Pod         │
         │ (Persistent)    │    │ (Persistent)    │
         └─────────────────┘    └─────────────────┘
```

### **ECS Architecture**
```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│ Application     │    │   Auto Scaling  │    │   CloudWatch    │
│ Load Balancer   │    │    Groups       │    │   Monitoring    │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│  RoadRunner     │    │    Horizon      │    │   Scheduler     │
│  ECS Service    │    │  ECS Service    │    │  ECS Service    │
│ (Fargate Tasks) │    │ (Fargate Tasks) │    │ (Fargate Tasks) │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         └───────────────────────┼───────────────────────┘
                                 │
         ┌─────────────────┐    ┌─────────────────┐
         │   RDS           │    │  ElastiCache    │
         │  PostgreSQL     │    │     Redis       │
         │  (Multi-AZ)     │    │  (Clustered)    │
         └─────────────────┘    └─────────────────┘
```

## 🔧 Configuration Details

### **Environment-Specific Settings**

#### Development
- **K8s**: 1 replica, 512Mi memory, 250m CPU
- **ECS**: 1 task, t3.medium instances, basic monitoring

#### Staging  
- **K8s**: 2 replicas, 1Gi memory, 500m CPU
- **ECS**: 2 tasks, t3.large instances, enhanced monitoring

#### Production
- **K8s**: 3+ replicas, 2Gi+ memory, 1000m+ CPU, auto-scaling
- **ECS**: 3+ tasks, c5.xlarge+ instances, full monitoring, multi-AZ

### **RoadRunner Configuration**
- **Workers**: 4 (dev), 8 (staging), 16 (production)
- **Max Jobs**: 1000 (dev), 2000+ (production)
- **Metrics**: Exposed on port 2112
- **Health Checks**: /api/health endpoint

### **Database Configuration**
- **PostgreSQL**: Version 15, persistent storage, automated backups
- **Redis**: Version 7, persistence enabled, password protected

## 📈 Monitoring & Observability

### **Kubernetes Monitoring**
- **Prometheus**: Metrics collection from RoadRunner
- **Grafana**: Dashboards for visualization
- **ServiceMonitor**: Automatic metrics discovery
- **Alerts**: CPU, memory, and error rate alerts

### **ECS Monitoring**
- **CloudWatch**: Container insights enabled
- **Application Load Balancer**: Health checks and metrics
- **Auto Scaling**: CPU and memory-based scaling
- **X-Ray**: Distributed tracing (optional)

## 🛡️ Security Features

### **Kubernetes Security**
- **RBAC**: Role-based access control
- **Network Policies**: Traffic isolation
- **Pod Security**: Security contexts and policies
- **Secrets Management**: Kubernetes secrets

### **ECS Security**
- **IAM Roles**: Task and execution roles
- **VPC**: Private subnets for tasks
- **Security Groups**: Network access control
- **Parameter Store**: Secure secret management

## 🔄 Deployment Strategies

### **Rolling Updates**
- **Kubernetes**: Rolling update with configurable surge/unavailable
- **ECS**: Blue/green deployment with circuit breaker

### **Zero Downtime**
- **Health Checks**: Application and infrastructure level
- **Graceful Shutdown**: Proper signal handling
- **Load Balancer**: Traffic routing during updates

## 📋 Prerequisites

### **Kubernetes**
```bash
# Required tools
kubectl >= 1.20
helm >= 3.0 (optional, for monitoring)
docker >= 20.0

# Cluster requirements
- Kubernetes cluster with ingress controller
- Storage class for persistent volumes
- Sufficient resources (2+ nodes recommended)
```

### **ECS**
```bash
# Required tools
aws-cli >= 2.0
jq >= 1.6
docker >= 20.0

# AWS requirements
- ECS permissions
- ECR permissions  
- VPC with public/private subnets
- RDS and ElastiCache permissions
```

## 🚀 Usage Examples

### **Complete Production Deployment**
```bash
# 1. Test the deployment configuration
./test-deployment.sh k8s production

# 2. Deploy to Kubernetes production
./deploy.sh k8s production deploy

# 3. Verify deployment
./deploy.sh k8s production verify

# 4. Check status
./deploy.sh k8s production status
```

### **ECS with Custom Settings**
```bash
# Set environment variables
export AWS_REGION=us-west-2
export AWS_ACCOUNT_ID=123456789012

# Deploy to ECS
./deploy.sh ecs production deploy

# Scale the application
aws ecs update-service \
  --cluster ai-blockchain-cluster-production \
  --service ai-blockchain-app-production \
  --desired-count 5
```

### **Local Development Workflow**
```bash
# 1. Start local environment
docker-compose -f docker-compose.roadrunner.yml up -d

# 2. Run migrations
docker-compose -f docker-compose.roadrunner.yml exec app php artisan migrate

# 3. Access application
curl http://localhost:8000/api/health

# 4. View RoadRunner metrics
curl http://localhost:2112/metrics

# 5. Stop environment
docker-compose -f docker-compose.roadrunner.yml down
```

## 🔍 Troubleshooting

### **Common Issues**

#### Kubernetes
```bash
# Check pod status
kubectl get pods -n ai-blockchain-analytics

# View logs
kubectl logs -f deployment/roadrunner-app -n ai-blockchain-analytics

# Debug networking
kubectl exec -it deployment/roadrunner-app -n ai-blockchain-analytics -- bash
```

#### ECS
```bash
# Check service status
aws ecs describe-services --cluster ai-blockchain-cluster-production

# View logs
aws logs tail /ecs/ai-blockchain-analytics-roadrunner-production

# Debug tasks
aws ecs execute-command --cluster CLUSTER --task TASK_ID --container roadrunner-app --interactive --command "/bin/bash"
```

## 📊 Performance Tuning

### **RoadRunner Optimization**
- **Workers**: Adjust based on CPU cores and workload
- **Memory**: Monitor and adjust container limits
- **Max Jobs**: Tune based on request patterns

### **Database Optimization**
- **Connection Pooling**: Configure appropriate pool sizes
- **Indexing**: Ensure proper database indexes
- **Monitoring**: Track query performance

### **Scaling Configuration**
- **HPA/Auto Scaling**: Tune CPU/memory thresholds
- **Resource Limits**: Set appropriate requests and limits
- **Load Testing**: Validate scaling behavior

## ✅ **System Status: PRODUCTION READY**

The deployment scripts are fully implemented, tested, and ready for production use. The system provides:

- ✅ **Dual Platform Support** (Kubernetes & ECS)
- ✅ **RoadRunner Optimization** with PHP 8.3
- ✅ **Auto-scaling & High Availability**
- ✅ **Comprehensive Monitoring**
- ✅ **Security Best Practices**
- ✅ **Environment-specific Configurations**
- ✅ **Testing & Validation Scripts**
- ✅ **Complete Documentation**

## 🎯 Next Steps

1. **Choose Platform**: Kubernetes for on-premises/multi-cloud, ECS for AWS-native
2. **Test Configuration**: Run `./test-deployment.sh` to validate setup
3. **Deploy**: Use `./deploy.sh [platform] [environment] deploy`
4. **Monitor**: Set up alerts and dashboards
5. **Scale**: Adjust resources based on actual usage

---

**Generated by AI Blockchain Analytics Platform**  
**Enhanced Deployment Scripts v2.0.0**  
**Implementation Date: August 11, 2025**
