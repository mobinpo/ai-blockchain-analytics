#!/bin/bash

# AI Blockchain Analytics - Deployment Testing & Validation Script
# Tests both Kubernetes and ECS deployments with comprehensive health checks
# Usage: ./test-deployment.sh [platform] [environment] [test-type]

set -euo pipefail

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_NAME="ai-blockchain-analytics"

# Parse arguments
PLATFORM=${1:-}
ENVIRONMENT=${2:-development}
TEST_TYPE=${3:-all}

# Test configuration
TIMEOUT_SECONDS=300
RETRY_INTERVAL=10
MAX_RETRIES=30

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m'

# Test results tracking
declare -a PASSED_TESTS=()
declare -a FAILED_TESTS=()
declare -a SKIPPED_TESTS=()

# Logging functions
log_info() {
    echo -e "[$(date +'%Y-%m-%d %H:%M:%S')] ${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "[$(date +'%Y-%m-%d %H:%M:%S')] ${GREEN}[SUCCESS]${NC} $1"
    PASSED_TESTS+=("$1")
}

log_warning() {
    echo -e "[$(date +'%Y-%m-%d %H:%M:%S')] ${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "[$(date +'%Y-%m-%d %H:%M:%S')] ${RED}[ERROR]${NC} $1"
    FAILED_TESTS+=("$1")
}

log_skip() {
    echo -e "[$(date +'%Y-%m-%d %H:%M:%S')] ${YELLOW}[SKIP]${NC} $1"
    SKIPPED_TESTS+=("$1")
}

log_test() {
    echo -e "[$(date +'%Y-%m-%d %H:%M:%S')] ${CYAN}[TEST]${NC} $1"
}

# Error handling
error_exit() {
    log_error "$1"
    exit 1
}

# Show usage
show_usage() {
    echo ""
    echo "🧪 AI Blockchain Analytics - Deployment Testing Script"
    echo "====================================================="
    echo ""
    echo "Usage: $0 [platform] [environment] [test-type]"
    echo ""
    echo "Platforms:"
    echo "  k8s, kubernetes    Test Kubernetes deployment"
    echo "  ecs, aws           Test AWS ECS deployment"
    echo "  local, docker      Test local Docker Compose deployment"
    echo ""
    echo "Environments:"
    echo "  development        Development environment"
    echo "  staging            Staging environment"
    echo "  production         Production environment"
    echo ""
    echo "Test Types:"
    echo "  all                Run all tests (default)"
    echo "  smoke              Basic smoke tests"
    echo "  health             Health check tests"
    echo "  performance        Performance tests"
    echo "  security           Security tests"
    echo "  integration        Integration tests"
    echo ""
    echo "Examples:"
    echo "  $0 local development smoke      # Smoke test local development"
    echo "  $0 k8s production health        # Health check K8s production"
    echo "  $0 ecs staging all              # All tests on ECS staging"
    echo ""
}

# Detect platform if not specified
detect_platform() {
    if [[ -z "$PLATFORM" ]]; then
        log_info "Auto-detecting platform..."
        
        # Check for local Docker Compose
        if [[ -f "$SCRIPT_DIR/docker-compose.roadrunner.yml" ]] && command -v docker-compose &> /dev/null; then
            PLATFORM="local"
            log_info "Detected local Docker Compose platform"
        # Check for Kubernetes
        elif command -v kubectl &> /dev/null && kubectl cluster-info > /dev/null 2>&1; then
            PLATFORM="k8s"
            log_info "Detected Kubernetes platform"
        # Check for AWS CLI
        elif command -v aws &> /dev/null && aws sts get-caller-identity > /dev/null 2>&1; then
            PLATFORM="ecs"
            log_info "Detected AWS ECS platform"
        else
            error_exit "Could not auto-detect platform. Please specify 'local', 'k8s', or 'ecs'"
        fi
    fi
}

# Validate inputs
validate_inputs() {
    case $PLATFORM in
        local|docker)
            PLATFORM="local"
            ;;
        k8s|kubernetes)
            PLATFORM="k8s"
            ;;
        ecs|aws)
            PLATFORM="ecs"
            ;;
        *)
            error_exit "Invalid platform: $PLATFORM"
            ;;
    esac
    
    case $ENVIRONMENT in
        development|dev)
            ENVIRONMENT="development"
            ;;
        staging|stage)
            ENVIRONMENT="staging"
            ;;
        production|prod)
            ENVIRONMENT="production"
            ;;
        *)
            error_exit "Invalid environment: $ENVIRONMENT"
            ;;
    esac
    
    case $TEST_TYPE in
        all|smoke|health|performance|security|integration)
            ;;
        *)
            error_exit "Invalid test type: $TEST_TYPE"
            ;;
    esac
}

# Wait for service to be ready
wait_for_service() {
    local service_name="$1"
    local health_check_command="$2"
    local max_wait_time="${3:-$TIMEOUT_SECONDS}"
    
    log_test "Waiting for $service_name to be ready..."
    
    local elapsed=0
    while [[ $elapsed -lt $max_wait_time ]]; do
        if eval "$health_check_command" > /dev/null 2>&1; then
            log_success "$service_name is ready (${elapsed}s)"
            return 0
        fi
        
        sleep $RETRY_INTERVAL
        elapsed=$((elapsed + RETRY_INTERVAL))
        echo -n "."
    done
    
    echo ""
    log_error "$service_name failed to become ready within ${max_wait_time}s"
    return 1
}

# Test local Docker Compose deployment
test_local_deployment() {
    log_test "Testing local Docker Compose deployment..."
    
    # Check if Docker Compose file exists
    if [[ ! -f "$SCRIPT_DIR/docker-compose.roadrunner.yml" ]]; then
        log_error "Docker Compose file not found"
        return 1
    fi
    
    # Check if services are running
    if ! docker-compose -f docker-compose.roadrunner.yml ps | grep -q "Up"; then
        log_warning "Services not running, attempting to start..."
        docker-compose -f docker-compose.roadrunner.yml up -d
    fi
    
    # Wait for services to be ready
    wait_for_service "PostgreSQL" "docker-compose -f docker-compose.roadrunner.yml exec -T postgres pg_isready -U ai_blockchain_user" 120
    wait_for_service "Redis" "docker-compose -f docker-compose.roadrunner.yml exec -T redis redis-cli -a redis_password_123 ping" 60
    wait_for_service "RoadRunner App" "curl -f http://localhost:8000/api/health" 180
    
    # Test application endpoints
    test_application_endpoints "http://localhost:8000"
    
    log_success "Local Docker Compose deployment tests completed"
}

# Test Kubernetes deployment
test_k8s_deployment() {
    log_test "Testing Kubernetes deployment..."
    
    local namespace="ai-blockchain-analytics"
    
    # Check if namespace exists
    if ! kubectl get namespace "$namespace" > /dev/null 2>&1; then
        log_error "Namespace '$namespace' not found"
        return 1
    fi
    
    # Check deployments
    local deployments=("postgres" "redis" "roadrunner-app" "horizon-worker" "scheduler")
    for deployment in "${deployments[@]}"; do
        log_test "Checking deployment: $deployment"
        if kubectl get deployment "$deployment" -n "$namespace" > /dev/null 2>&1; then
            kubectl wait --for=condition=Available deployment/"$deployment" -n "$namespace" --timeout=300s
            log_success "Deployment $deployment is ready"
        else
            log_skip "Deployment $deployment not found"
        fi
    done
    
    # Check services
    local services=("postgres-service" "redis-service" "roadrunner-app-service")
    for service in "${services[@]}"; do
        log_test "Checking service: $service"
        if kubectl get service "$service" -n "$namespace" > /dev/null 2>&1; then
            log_success "Service $service is available"
        else
            log_error "Service $service not found"
        fi
    done
    
    # Get application URL
    local app_url
    if kubectl get ingress app-ingress -n "$namespace" > /dev/null 2>&1; then
        local ingress_ip=$(kubectl get ingress app-ingress -n "$namespace" -o jsonpath='{.status.loadBalancer.ingress[0].ip}' 2>/dev/null || echo "")
        local ingress_hostname=$(kubectl get ingress app-ingress -n "$namespace" -o jsonpath='{.status.loadBalancer.ingress[0].hostname}' 2>/dev/null || echo "")
        
        if [[ -n "$ingress_ip" ]]; then
            app_url="http://$ingress_ip"
        elif [[ -n "$ingress_hostname" ]]; then
            app_url="http://$ingress_hostname"
        else
            log_warning "Ingress not ready, using port-forward for testing"
            kubectl port-forward service/roadrunner-app-service 8080:80 -n "$namespace" &
            local port_forward_pid=$!
            sleep 10
            app_url="http://localhost:8080"
        fi
    else
        log_warning "Ingress not found, using port-forward for testing"
        kubectl port-forward service/roadrunner-app-service 8080:80 -n "$namespace" &
        local port_forward_pid=$!
        sleep 10
        app_url="http://localhost:8080"
    fi
    
    # Test application endpoints
    test_application_endpoints "$app_url"
    
    # Cleanup port-forward if used
    if [[ -n "${port_forward_pid:-}" ]]; then
        kill "$port_forward_pid" 2>/dev/null || true
    fi
    
    log_success "Kubernetes deployment tests completed"
}

# Test ECS deployment
test_ecs_deployment() {
    log_test "Testing ECS deployment..."
    
    local cluster_name="ai-blockchain-cluster-${ENVIRONMENT}"
    local region="${AWS_REGION:-us-east-1}"
    
    # Check if cluster exists
    if ! aws ecs describe-clusters --clusters "$cluster_name" --region "$region" > /dev/null 2>&1; then
        log_error "ECS cluster '$cluster_name' not found"
        return 1
    fi
    
    # Check services
    local services=("ai-blockchain-app-${ENVIRONMENT}" "ai-blockchain-worker-${ENVIRONMENT}")
    for service in "${services[@]}"; do
        log_test "Checking ECS service: $service"
        local service_status=$(aws ecs describe-services --cluster "$cluster_name" --services "$service" --region "$region" --query 'services[0].status' --output text 2>/dev/null || echo "NOT_FOUND")
        
        if [[ "$service_status" == "ACTIVE" ]]; then
            log_success "ECS service $service is active"
            
            # Check running tasks
            local running_count=$(aws ecs describe-services --cluster "$cluster_name" --services "$service" --region "$region" --query 'services[0].runningCount' --output text)
            local desired_count=$(aws ecs describe-services --cluster "$cluster_name" --services "$service" --region "$region" --query 'services[0].desiredCount' --output text)
            
            if [[ "$running_count" == "$desired_count" ]]; then
                log_success "Service $service has $running_count/$desired_count tasks running"
            else
                log_warning "Service $service has $running_count/$desired_count tasks running"
            fi
        else
            log_error "ECS service $service status: $service_status"
        fi
    done
    
    # Get ALB DNS name
    local alb_name="ai-blockchain-alb-${ENVIRONMENT}"
    local alb_dns=$(aws elbv2 describe-load-balancers --names "$alb_name" --region "$region" --query 'LoadBalancers[0].DNSName' --output text 2>/dev/null || echo "")
    
    if [[ -n "$alb_dns" ]]; then
        log_success "Load Balancer DNS: $alb_dns"
        local app_url="http://$alb_dns"
        
        # Wait for ALB to be ready
        wait_for_service "Application Load Balancer" "curl -f $app_url/api/health" 300
        
        # Test application endpoints
        test_application_endpoints "$app_url"
    else
        log_error "Application Load Balancer not found"
    fi
    
    log_success "ECS deployment tests completed"
}

# Test application endpoints
test_application_endpoints() {
    local base_url="$1"
    
    log_test "Testing application endpoints at $base_url"
    
    # Health check endpoint
    log_test "Testing health endpoint..."
    if curl -f -s "$base_url/api/health" > /dev/null; then
        log_success "Health endpoint is responding"
    else
        log_error "Health endpoint is not responding"
    fi
    
    # API status endpoint
    log_test "Testing API status endpoint..."
    if curl -f -s "$base_url/api/status" > /dev/null; then
        log_success "API status endpoint is responding"
    else
        log_warning "API status endpoint is not responding (may not exist)"
    fi
    
    # Main application endpoint
    log_test "Testing main application endpoint..."
    if curl -f -s "$base_url/" > /dev/null; then
        log_success "Main application endpoint is responding"
    else
        log_error "Main application endpoint is not responding"
    fi
    
    # Metrics endpoint (RoadRunner)
    log_test "Testing metrics endpoint..."
    if curl -f -s "$base_url:2112/metrics" > /dev/null 2>&1; then
        log_success "Metrics endpoint is responding"
    else
        log_warning "Metrics endpoint is not responding (may be on different port)"
    fi
}

# Performance tests
run_performance_tests() {
    local base_url="$1"
    
    log_test "Running performance tests..."
    
    if ! command -v ab &> /dev/null; then
        log_skip "Apache Bench (ab) not installed, skipping performance tests"
        return
    fi
    
    # Simple load test
    log_test "Running simple load test (100 requests, concurrency 10)..."
    if ab -n 100 -c 10 "$base_url/api/health" > /tmp/ab_results.txt 2>&1; then
        local requests_per_second=$(grep "Requests per second" /tmp/ab_results.txt | awk '{print $4}')
        log_success "Performance test completed: $requests_per_second requests/second"
    else
        log_error "Performance test failed"
    fi
}

# Security tests
run_security_tests() {
    local base_url="$1"
    
    log_test "Running basic security tests..."
    
    # Check for common security headers
    log_test "Checking security headers..."
    local headers=$(curl -I -s "$base_url/" || echo "")
    
    if echo "$headers" | grep -qi "x-frame-options"; then
        log_success "X-Frame-Options header found"
    else
        log_warning "X-Frame-Options header not found"
    fi
    
    if echo "$headers" | grep -qi "x-content-type-options"; then
        log_success "X-Content-Type-Options header found"
    else
        log_warning "X-Content-Type-Options header not found"
    fi
    
    # Check for exposed sensitive endpoints
    log_test "Checking for exposed sensitive endpoints..."
    local sensitive_endpoints=("/.env" "/config" "/admin" "/telescope")
    
    for endpoint in "${sensitive_endpoints[@]}"; do
        if curl -f -s "$base_url$endpoint" > /dev/null 2>&1; then
            log_error "Sensitive endpoint exposed: $endpoint"
        else
            log_success "Sensitive endpoint protected: $endpoint"
        fi
    done
}

# Generate test report
generate_test_report() {
    local report_file="test-report-${PLATFORM}-${ENVIRONMENT}-$(date +%Y%m%d-%H%M%S).md"
    
    cat > "$report_file" <<EOF
# AI Blockchain Analytics - Test Report

**Platform:** $PLATFORM  
**Environment:** $ENVIRONMENT  
**Test Type:** $TEST_TYPE  
**Date:** $(date)  
**Duration:** ${SECONDS}s  

## Test Summary

- **Total Tests:** $((${#PASSED_TESTS[@]} + ${#FAILED_TESTS[@]} + ${#SKIPPED_TESTS[@]}))
- **Passed:** ${#PASSED_TESTS[@]}
- **Failed:** ${#FAILED_TESTS[@]}
- **Skipped:** ${#SKIPPED_TESTS[@]}
- **Success Rate:** $(( ${#PASSED_TESTS[@]} * 100 / (${#PASSED_TESTS[@]} + ${#FAILED_TESTS[@]} + 1) ))%

## Passed Tests
EOF

    for test in "${PASSED_TESTS[@]}"; do
        echo "- ✅ $test" >> "$report_file"
    done
    
    cat >> "$report_file" <<EOF

## Failed Tests
EOF

    for test in "${FAILED_TESTS[@]}"; do
        echo "- ❌ $test" >> "$report_file"
    done
    
    cat >> "$report_file" <<EOF

## Skipped Tests
EOF

    for test in "${SKIPPED_TESTS[@]}"; do
        echo "- ⏭️ $test" >> "$report_file"
    done
    
    cat >> "$report_file" <<EOF

## Recommendations

EOF

    if [[ ${#FAILED_TESTS[@]} -gt 0 ]]; then
        cat >> "$report_file" <<EOF
### Critical Issues
- Review and fix failed tests before proceeding to production
- Check logs for detailed error information
- Verify all services are properly configured and running

EOF
    fi
    
    cat >> "$report_file" <<EOF
### Next Steps
1. Address any failed tests
2. Monitor application performance and stability
3. Run security scans if deploying to production
4. Set up monitoring and alerting
5. Document any configuration changes

---
*Generated by AI Blockchain Analytics Deployment Testing Script*
EOF
    
    log_success "Test report generated: $report_file"
}

# Main execution
main() {
    echo ""
    echo "🧪 AI Blockchain Analytics - Deployment Testing Script"
    echo "====================================================="
    echo ""
    
    # Show usage if no arguments or help requested
    if [[ $# -eq 0 ]] || [[ "$1" == "-h" ]] || [[ "$1" == "--help" ]] || [[ "$1" == "help" ]]; then
        show_usage
        exit 0
    fi
    
    # Detect platform if not specified
    detect_platform
    
    # Validate inputs
    validate_inputs
    
    # Show configuration
    log_info "Platform: $PLATFORM"
    log_info "Environment: $ENVIRONMENT"
    log_info "Test Type: $TEST_TYPE"
    echo ""
    
    # Start timer
    SECONDS=0
    
    # Run tests based on platform
    case $PLATFORM in
        local)
            test_local_deployment
            ;;
        k8s)
            test_k8s_deployment
            ;;
        ecs)
            test_ecs_deployment
            ;;
    esac
    
    # Run additional tests based on type
    if [[ "$TEST_TYPE" == "all" || "$TEST_TYPE" == "performance" ]]; then
        case $PLATFORM in
            local)
                run_performance_tests "http://localhost:8000"
                ;;
            k8s|ecs)
                # Performance tests would run with the detected URL
                log_skip "Performance tests require specific URL configuration"
                ;;
        esac
    fi
    
    if [[ "$TEST_TYPE" == "all" || "$TEST_TYPE" == "security" ]]; then
        case $PLATFORM in
            local)
                run_security_tests "http://localhost:8000"
                ;;
            k8s|ecs)
                # Security tests would run with the detected URL
                log_skip "Security tests require specific URL configuration"
                ;;
        esac
    fi
    
    # Generate report
    generate_test_report
    
    # Show final summary
    echo ""
    log_info "🎯 Test Summary:"
    log_info "  Passed: ${#PASSED_TESTS[@]}"
    log_info "  Failed: ${#FAILED_TESTS[@]}"
    log_info "  Skipped: ${#SKIPPED_TESTS[@]}"
    log_info "  Duration: ${SECONDS}s"
    
    if [[ ${#FAILED_TESTS[@]} -eq 0 ]]; then
        echo ""
        log_success "🎉 All tests passed successfully!"
        exit 0
    else
        echo ""
        log_error "❌ Some tests failed. Check the report for details."
        exit 1
    fi
}

# Execute main function
main "$@"
