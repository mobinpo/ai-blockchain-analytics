#!/bin/bash

# Kubernetes Deployment Script for AI Blockchain Analytics
# Usage: ./k8s/deploy.sh [environment] [context] [namespace]

set -e

# Default values
ENVIRONMENT=${1:-production}
K8S_CONTEXT=${2:-$(kubectl config current-context)}
NAMESPACE=${3:-ai-blockchain-analytics}

echo "🚀 Starting Kubernetes deployment for AI Blockchain Analytics"
echo "📍 Environment: $ENVIRONMENT"
echo "🔧 Context: $K8S_CONTEXT"
echo "📦 Namespace: $NAMESPACE"
echo

# Check if kubectl is configured
if ! kubectl cluster-info > /dev/null 2>&1; then
    echo "❌ kubectl is not configured or cluster is unreachable"
    exit 1
fi

echo "✅ kubectl configured"

# Use the specified context
kubectl config use-context $K8S_CONTEXT

# Apply namespace and configurations first
echo "📦 Creating namespace and configurations..."
kubectl apply -f k8s/namespace.yaml

# Wait for namespace to be ready
kubectl wait --for=condition=Ready namespace/$NAMESPACE --timeout=60s

echo "✅ Namespace and configurations applied"

# Apply persistent storage
echo "💾 Setting up persistent storage..."
kubectl apply -f k8s/postgres.yaml
kubectl apply -f k8s/redis.yaml

echo "✅ Storage configurations applied"

# Wait for PostgreSQL to be ready
echo "⏳ Waiting for PostgreSQL to be ready..."
kubectl wait --for=condition=Ready pod -l app=postgres -n $NAMESPACE --timeout=300s

# Wait for Redis to be ready
echo "⏳ Waiting for Redis to be ready..."
kubectl wait --for=condition=Ready pod -l app=redis -n $NAMESPACE --timeout=180s

echo "✅ Database services are ready"

# Apply application deployments
echo "🚀 Deploying application..."
kubectl apply -f k8s/app.yaml

# Wait for application to be ready
echo "⏳ Waiting for application to be ready..."
kubectl wait --for=condition=Available deployment/laravel-app -n $NAMESPACE --timeout=300s
kubectl wait --for=condition=Available deployment/laravel-queue-worker -n $NAMESPACE --timeout=300s
kubectl wait --for=condition=Available deployment/laravel-scheduler -n $NAMESPACE --timeout=300s

echo "✅ Application deployments are ready"

# Apply ingress and additional services
echo "🌐 Setting up ingress and additional services..."
kubectl apply -f k8s/ingress.yaml

# Wait for browserless to be ready
echo "⏳ Waiting for Browserless to be ready..."
kubectl wait --for=condition=Available deployment/browserless -n $NAMESPACE --timeout=180s

echo "✅ All services deployed successfully"

# Get deployment status
echo "📊 Deployment Status:"
kubectl get deployments -n $NAMESPACE -o wide

echo
echo "📋 Pod Status:"
kubectl get pods -n $NAMESPACE -o wide

echo
echo "🔗 Services:"
kubectl get services -n $NAMESPACE -o wide

# Get ingress information
echo
echo "🌍 Ingress Information:"
kubectl get ingress -n $NAMESPACE -o wide

# Check if load balancer is available
INGRESS_IP=$(kubectl get ingress laravel-app-ingress -n $NAMESPACE -o jsonpath='{.status.loadBalancer.ingress[0].ip}' 2>/dev/null || echo "")
INGRESS_HOSTNAME=$(kubectl get ingress laravel-app-ingress -n $NAMESPACE -o jsonpath='{.status.loadBalancer.ingress[0].hostname}' 2>/dev/null || echo "")

if [ -n "$INGRESS_IP" ]; then
    echo "🌍 Load Balancer IP: $INGRESS_IP"
elif [ -n "$INGRESS_HOSTNAME" ]; then
    echo "🌍 Load Balancer Hostname: $INGRESS_HOSTNAME"
else
    echo "⏳ Load Balancer is still provisioning..."
fi

echo
echo "🎉 Deployment completed successfully!"
echo "📋 Summary:"
echo "   • Namespace: $NAMESPACE"
echo "   • Context: $K8S_CONTEXT"
echo "   • Environment: $ENVIRONMENT"
echo

echo "🔍 Useful commands:"
echo "   • Check pods: kubectl get pods -n $NAMESPACE"
echo "   • View logs: kubectl logs -f deployment/laravel-app -n $NAMESPACE"
echo "   • Port forward: kubectl port-forward service/laravel-app-service 8000:80 -n $NAMESPACE"
echo "   • Shell access: kubectl exec -it deployment/laravel-app -n $NAMESPACE -- /bin/bash"
echo "   • Check ingress: kubectl describe ingress laravel-app-ingress -n $NAMESPACE"
echo

# Run post-deployment checks
echo "🔍 Running post-deployment health checks..."

# Check if application pods are healthy
APP_READY=$(kubectl get deployment laravel-app -n $NAMESPACE -o jsonpath='{.status.readyReplicas}')
APP_DESIRED=$(kubectl get deployment laravel-app -n $NAMESPACE -o jsonpath='{.spec.replicas}')

if [ "$APP_READY" = "$APP_DESIRED" ]; then
    echo "✅ Application pods are healthy ($APP_READY/$APP_DESIRED)"
else
    echo "⚠️  Application pods not fully ready ($APP_READY/$APP_DESIRED)"
fi

# Check queue workers
QUEUE_READY=$(kubectl get deployment laravel-queue-worker -n $NAMESPACE -o jsonpath='{.status.readyReplicas}')
QUEUE_DESIRED=$(kubectl get deployment laravel-queue-worker -n $NAMESPACE -o jsonpath='{.spec.replicas}')

if [ "$QUEUE_READY" = "$QUEUE_DESIRED" ]; then
    echo "✅ Queue workers are healthy ($QUEUE_READY/$QUEUE_DESIRED)"
else
    echo "⚠️  Queue workers not fully ready ($QUEUE_READY/$QUEUE_DESIRED)"
fi

# Test application health endpoint (if port forwarding works)
echo "🏥 Testing application health..."
if kubectl port-forward service/laravel-app-service 18000:80 -n $NAMESPACE >/dev/null 2>&1 &
then
    PF_PID=$!
    sleep 3
    
    if curl -f http://localhost:18000/health >/dev/null 2>&1; then
        echo "✅ Application health check passed"
    else
        echo "⚠️  Application health check failed"
    fi
    
    kill $PF_PID 2>/dev/null || true
fi

echo
echo "🚀 Kubernetes deployment completed!"