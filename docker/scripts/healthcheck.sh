#!/bin/bash

# AI Blockchain Analytics - Health Check Script
# Comprehensive health check for RoadRunner and application

set -e

# Configuration
HEALTH_URL="http://localhost:8080/health"
READY_URL="http://localhost:8080/ready"
METRICS_URL="http://localhost:2112/metrics"
STATUS_URL="http://localhost:2114/health"
TIMEOUT=10
MAX_RETRIES=3

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Logging functions
log_info() {
    echo -e "[INFO] $1" >&2
}

log_error() {
    echo -e "${RED}[ERROR] $1${NC}" >&2
}

log_success() {
    echo -e "${GREEN}[SUCCESS] $1${NC}" >&2
}

log_warning() {
    echo -e "${YELLOW}[WARNING] $1${NC}" >&2
}

# Check if RoadRunner process is running
check_roadrunner_process() {
    if pgrep -x "rr" > /dev/null; then
        log_success "RoadRunner process is running"
        return 0
    else
        log_error "RoadRunner process not found"
        return 1
    fi
}

# Check HTTP endpoint with retries
check_endpoint() {
    local url="$1"
    local description="$2"
    local expected_status="${3:-200}"
    local retries=0

    while [ $retries -lt $MAX_RETRIES ]; do
        if curl -f -s -o /dev/null --max-time $TIMEOUT "$url"; then
            local status_code=$(curl -s -o /dev/null -w "%{http_code}" --max-time $TIMEOUT "$url")
            if [ "$status_code" = "$expected_status" ]; then
                log_success "$description endpoint is healthy (HTTP $status_code)"
                return 0
            else
                log_warning "$description endpoint returned HTTP $status_code (expected $expected_status)"
            fi
        else
            log_warning "$description endpoint check failed (attempt $((retries + 1))/$MAX_RETRIES)"
        fi
        
        retries=$((retries + 1))
        if [ $retries -lt $MAX_RETRIES ]; then
            sleep 2
        fi
    done

    log_error "$description endpoint is unhealthy after $MAX_RETRIES attempts"
    return 1
}

# Check application health endpoint
check_application_health() {
    log_info "Checking application health endpoint..."
    check_endpoint "$HEALTH_URL" "Application health"
}

# Check application readiness
check_application_readiness() {
    log_info "Checking application readiness endpoint..."
    check_endpoint "$READY_URL" "Application readiness"
}

# Check RoadRunner metrics endpoint
check_metrics() {
    log_info "Checking RoadRunner metrics endpoint..."
    check_endpoint "$METRICS_URL" "RoadRunner metrics"
}

# Check RoadRunner status endpoint
check_roadrunner_status() {
    log_info "Checking RoadRunner status endpoint..."
    check_endpoint "$STATUS_URL" "RoadRunner status"
}

# Check database connectivity (if accessible)
check_database() {
    log_info "Checking database connectivity..."
    
    # Try to connect using environment variables
    if [ -n "$DB_HOST" ] && [ -n "$DB_DATABASE" ] && [ -n "$DB_USERNAME" ]; then
        if command -v pg_isready > /dev/null 2>&1; then
            if pg_isready -h "$DB_HOST" -p "${DB_PORT:-5432}" -U "$DB_USERNAME" -d "$DB_DATABASE" -t $TIMEOUT; then
                log_success "Database connectivity verified"
                return 0
            else
                log_error "Database connectivity failed"
                return 1
            fi
        else
            log_warning "pg_isready not available, skipping database check"
            return 0
        fi
    else
        log_warning "Database connection parameters not set, skipping database check"
        return 0
    fi
}

# Check Redis connectivity (if accessible)
check_redis() {
    log_info "Checking Redis connectivity..."
    
    if [ -n "$REDIS_HOST" ]; then
        if command -v redis-cli > /dev/null 2>&1; then
            if timeout $TIMEOUT redis-cli -h "$REDIS_HOST" -p "${REDIS_PORT:-6379}" ping | grep -q "PONG"; then
                log_success "Redis connectivity verified"
                return 0
            else
                log_error "Redis connectivity failed"
                return 1
            fi
        else
            log_warning "redis-cli not available, skipping Redis check"
            return 0
        fi
    else
        log_warning "Redis connection parameters not set, skipping Redis check"
        return 0
    fi
}

# Check disk space
check_disk_space() {
    log_info "Checking disk space..."
    
    # Check if storage directory has at least 100MB free
    local available=$(df /app/storage 2>/dev/null | awk 'NR==2 {print $4}' || echo "0")
    local required=102400  # 100MB in KB
    
    if [ "$available" -gt "$required" ]; then
        log_success "Sufficient disk space available ($((available / 1024))MB)"
        return 0
    else
        log_error "Insufficient disk space (available: $((available / 1024))MB, required: $((required / 1024))MB)"
        return 1
    fi
}

# Check memory usage
check_memory() {
    log_info "Checking memory usage..."
    
    if command -v free > /dev/null 2>&1; then
        local mem_usage=$(free | awk 'NR==2{printf "%.1f", $3*100/$2}')
        local mem_usage_int=${mem_usage%.*}
        
        if [ "$mem_usage_int" -lt 90 ]; then
            log_success "Memory usage is acceptable (${mem_usage}%)"
            return 0
        else
            log_warning "High memory usage detected (${mem_usage}%)"
            return 1
        fi
    else
        log_warning "Memory check not available"
        return 0
    fi
}

# Main health check function
main() {
    local exit_code=0
    local checks_passed=0
    local total_checks=0

    echo "Starting comprehensive health check..."
    echo "========================================"

    # Core checks (critical)
    local critical_checks=(
        "check_roadrunner_process"
        "check_application_health"
        "check_application_readiness"
    )

    # Optional checks (warnings only)
    local optional_checks=(
        "check_roadrunner_status"
        "check_metrics"
        "check_database"
        "check_redis"
        "check_disk_space"
        "check_memory"
    )

    # Run critical checks
    for check in "${critical_checks[@]}"; do
        total_checks=$((total_checks + 1))
        if $check; then
            checks_passed=$((checks_passed + 1))
        else
            exit_code=1
        fi
        echo
    done

    # Run optional checks
    for check in "${optional_checks[@]}"; do
        total_checks=$((total_checks + 1))
        if $check; then
            checks_passed=$((checks_passed + 1))
        else
            log_warning "Optional check failed: $check"
        fi
        echo
    done

    # Summary
    echo "========================================"
    echo "Health check summary: $checks_passed/$total_checks checks passed"

    if [ $exit_code -eq 0 ]; then
        log_success "All critical health checks passed"
    else
        log_error "One or more critical health checks failed"
    fi

    exit $exit_code
}

# Execute main function
main "$@"