#!/bin/bash

# =============================================================================
# AI Blockchain Analytics - Docker 500 Concurrent Analysis Load Test Runner
# =============================================================================
# This script runs comprehensive load tests from within Docker container
# targeting 500 concurrent AI blockchain analyses

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
REPORTS_DIR="$SCRIPT_DIR/reports"
TEST_NAME="ai_analysis_500_concurrent_docker_$TIMESTAMP"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m'

# Test configuration
CONFIG_FILE="$SCRIPT_DIR/ai-blockchain-500-concurrent.yml"
OUTPUT_FILE="$REPORTS_DIR/${TEST_NAME}.json"
HTML_REPORT="$REPORTS_DIR/${TEST_NAME}.html"
SUMMARY_FILE="$REPORTS_DIR/${TEST_NAME}_summary.md"

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

log_header() {
    echo -e "${PURPLE}$1${NC}"
    echo -e "${PURPLE}$(printf '%.0s=' {1..80})${NC}"
}

log_step() {
    echo -e "${CYAN}[STEP]${NC} $1"
}

# Check if running inside Docker container
check_docker_environment() {
    if [ ! -f /.dockerenv ]; then
        log_error "This script should be run inside the Docker container"
        log_info "Run this command from your host machine:"
        log_info "  docker compose exec app bash /var/www/load-tests/run-docker-500-test.sh"
        exit 1
    fi
    log_success "Running inside Docker container"
}

# Install Artillery if not present
install_artillery() {
    log_step "Checking Artillery installation"
    
    if ! command -v artillery &> /dev/null; then
        log_warning "Artillery not found. Installing..."
        npm install -g artillery@latest
        npm install -g @artilleryio/plugin-metrics-by-endpoint
        log_success "Artillery installed successfully"
    else
        log_success "Artillery is available: $(artillery version)"
    fi
}

# Pre-flight checks for Docker environment
pre_flight_checks() {
    log_header "🚀 Docker Container Pre-flight Checks"
    
    check_docker_environment
    install_artillery
    
    # Check Laravel app (internal port 8000)
    log_step "Checking Laravel application availability (internal)"
    if ! curl -s --connect-timeout 10 "http://localhost:8000" > /dev/null; then
        log_error "Laravel app is not responding on localhost:8000 (internal)"
        log_info "Make sure Laravel is running inside the container"
        exit 1
    else
        log_success "Laravel app is responding internally"
    fi
    
    # Check external access (port 8003)
    log_step "Checking external access (port 8003)"
    if ! curl -s --connect-timeout 10 "http://localhost:8003" > /dev/null; then
        log_warning "External port 8003 may not be accessible from container"
        log_info "This is normal - Artillery will target localhost:8003 from host perspective"
    fi
    
    # Test critical endpoints from internal perspective
    log_step "Testing critical API endpoints (internal)"
    
    endpoints=(
        "GET:/:200,404"
        "POST:/api/sentiment/analyze:200,422,500"
        "GET:/api/verification/status/test:200,404,500"
        "POST:/api/pdf/generate:200,422,500"
    )
    
    for endpoint_test in "${endpoints[@]}"; do
        IFS=':' read -r method path expected_codes <<< "$endpoint_test"
        
        if [ "$method" = "GET" ]; then
            response_code=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost:8000$path" || echo "000")
        else
            response_code=$(curl -s -o /dev/null -w "%{http_code}" \
                -X "$method" \
                -H "Content-Type: application/json" \
                -d '{"test": true}' \
                "http://localhost:8000$path" || echo "000")
        fi
        
        if [[ "$expected_codes" == *"$response_code"* ]]; then
            log_success "✓ $method $path → $response_code"
        else
            log_warning "⚠ $method $path → $response_code (expected: $expected_codes)"
        fi
    done
    
    # Check test configuration file
    if [ ! -f "$CONFIG_FILE" ]; then
        log_error "Test configuration file not found: $CONFIG_FILE"
        exit 1
    fi
    log_success "Test configuration file found"
    
    # Create reports directory
    mkdir -p "$REPORTS_DIR"
    log_success "Reports directory ready: $REPORTS_DIR"
    
    # Container resource check
    log_step "Container Resources Assessment"
    cpu_cores=$(nproc)
    total_mem=$(free -h | awk '/^Mem:/ {print $2}')
    available_mem=$(free -h | awk '/^Mem:/ {print $7}')
    
    echo "  🔹 Container CPU Cores: $cpu_cores"
    echo "  🔹 Container Total Memory: $total_mem"
    echo "  🔹 Container Available Memory: $available_mem"
    
    log_success "Pre-flight checks completed"
}

# Run the load test
run_load_test() {
    log_header "🔥 Running AI Blockchain 500 Concurrent Analysis Load Test (Docker)"
    
    log_info "Test Configuration:"
    echo "  📁 Config File: $CONFIG_FILE"
    echo "  📊 Output File: $OUTPUT_FILE"
    echo "  📈 HTML Report: $HTML_REPORT"
    echo "  📋 Summary File: $SUMMARY_FILE"
    echo "  🎯 Target: http://localhost:8003 (external Docker port)"
    echo
    
    log_info "Test Profile:"
    echo "  🎯 Target: 500 concurrent AI blockchain analyses"
    echo "  ⏱️  Duration: ~20 minutes total"
    echo "  🚀 Ramp-up: 7.5 minutes to reach 500 concurrent"
    echo "  💪 Sustained: 10 minutes at 500 concurrent load"
    echo "  📉 Cool-down: 90 seconds scale down"
    echo
    
    log_info "Test Scenarios:"
    echo "  📈 60% - Sentiment Analysis Pipeline"
    echo "  🔐 25% - Verification Analysis"
    echo "  📄 10% - PDF Report Generation"
    echo "  📊  5% - Dashboard Data Requests"
    echo
    
    log_warning "⚠️  This is a high-intensity test that will stress your system"
    log_warning "⚠️  Monitor Docker container and host resources"
    
    echo
    log_info "🚀 Starting AI Blockchain Analytics load test from Docker container..."
    
    cd "$SCRIPT_DIR"
    
    # Run Artillery with comprehensive reporting
    if artillery run "$CONFIG_FILE" --output "$OUTPUT_FILE"; then
        log_success "✅ Load test completed successfully!"
        
        # Generate HTML report
        if artillery report --output "$HTML_REPORT" "$OUTPUT_FILE"; then
            log_success "📈 HTML report generated: $HTML_REPORT"
        fi
        
        # Generate comprehensive summary
        generate_summary "$OUTPUT_FILE"
        
        return 0
        
    else
        log_error "❌ Load test failed!"
        return 1
    fi
}

# Generate comprehensive test summary
generate_summary() {
    local json_file="$1"
    
    log_header "📊 Generating Comprehensive Test Summary"
    
    # Extract key metrics from JSON (basic parsing)
    local total_requests=$(grep -o '"count":[0-9]*' "$json_file" | head -1 | cut -d':' -f2 || echo "N/A")
    local total_errors=$(grep -o '"errors":[0-9]*' "$json_file" | head -1 | cut -d':' -f2 || echo "N/A")
    
    cat > "$SUMMARY_FILE" << EOF
# AI Blockchain Analytics - Docker Load Test Results
## 500 Concurrent Analysis Performance Report

**Test Execution:** $TEST_NAME  
**Timestamp:** $(date)  
**Target Load:** 500 concurrent AI blockchain analyses  
**Test Duration:** ~20 minutes  
**Environment:** Docker Container  
**Target Port:** localhost:8003 (external mapping)

---

## 📋 Test Configuration

### Load Profile
- **Warmup:** 30 seconds at 10 req/sec
- **Ramp Phase 1:** 120 seconds scaling 10→100 req/sec  
- **Ramp Phase 2:** 180 seconds scaling 100→300 req/sec
- **Ramp Phase 3:** 120 seconds scaling 300→500 req/sec
- **Sustained Load:** 600 seconds at 500 req/sec
- **Cool Down:** 90 seconds scaling 500→0 req/sec

### Traffic Distribution
- **Sentiment Analysis Pipeline:** 60% of requests
- **Verification Analysis:** 25% of requests  
- **PDF Report Generation:** 10% of requests
- **Dashboard Data:** 5% of requests

---

## 🎯 Performance Targets

| Metric | Target | Status |
|--------|--------|---------|
| Request Rate | >300 req/sec | TBD |
| Median Response Time | <3000ms | TBD |
| 95th Percentile | <12000ms | TBD |
| 99th Percentile | <25000ms | TBD |
| Success Rate | >70% | TBD |
| Error Rate | <5% | TBD |

---

## 📊 Test Results Summary

**Total Requests:** ${total_requests}  
**Total Errors:** ${total_errors}  
**Error Rate:** TBD%  
**Average RPS:** TBD  

*(Detailed metrics available in JSON and HTML reports)*

---

## 📁 Generated Files

- **JSON Report:** \`$OUTPUT_FILE\`
- **HTML Report:** \`$HTML_REPORT\`
- **Summary Report:** \`$SUMMARY_FILE\`

---

## 🔧 Analysis Commands

\`\`\`bash
# View detailed HTML report (copy to host and open)
docker compose cp app:$HTML_REPORT ./

# Parse JSON metrics
docker compose exec app cat "$OUTPUT_FILE" | jq '.aggregate'

# Quick performance check
docker compose exec app grep -E "(min|max|mean|p95|p99)" "$OUTPUT_FILE"
\`\`\`

---

## 💡 Docker-Specific Recommendations

### Container Performance
- Monitor Docker container resource limits
- Check host system resources during load test
- Verify network bridge performance
- Review container memory and CPU allocation

### Infrastructure Scaling  
- Consider increasing Docker container resources
- Monitor host Docker daemon performance
- Review network bridge configuration
- Evaluate container orchestration for scaling

### Monitoring Setup
- Set up container-specific monitoring
- Monitor both container and host metrics
- Configure log aggregation for containers
- Create alerting for container resource limits

---

**Test Environment:** Docker Container  
**Host System:** $(uname -a)  
**Generated:** $(date)
EOF

    log_success "📋 Comprehensive test summary generated: $SUMMARY_FILE"
}

# Main execution
main() {
    log_header "🎯 AI Blockchain Analytics - 500 Concurrent Docker Load Test"
    
    echo "🐳 This test will run from within the Docker container and simulate:"
    echo "   • 500 concurrent users performing AI blockchain analyses"
    echo "   • Sentiment analysis on cryptocurrency data"
    echo "   • Verification workflows for blockchain data"
    echo "   • PDF report generation for analysis results"
    echo "   • Dashboard data requests and API calls"
    echo
    echo "📈 Expected system impact:"
    echo "   • High CPU and memory usage in Docker container"
    echo "   • Increased network traffic through Docker bridge"
    echo "   • Database and Redis load within container network"
    echo "   • Artillery process running inside container"
    echo
    
    pre_flight_checks
    
    if run_load_test; then
        log_header "🎉 Docker Load Test Completed Successfully!"
        echo
        log_success "✅ Test execution finished"
        log_success "✅ Reports generated in container: $REPORTS_DIR"
        echo
        log_info "📋 Next Steps:"
        echo "   1. Copy reports to host: docker compose cp app:$REPORTS_DIR ./"
        echo "   2. Review the HTML report for detailed metrics"
        echo "   3. Analyze container and host resource usage"
        echo "   4. Identify performance bottlenecks"
        echo "   5. Optimize Docker and application configuration"
        echo
        log_info "🔧 Copy reports to host:"
        echo "   docker compose cp app:$HTML_REPORT ./"
        echo "   docker compose cp app:$OUTPUT_FILE ./"
        echo "   docker compose cp app:$SUMMARY_FILE ./"
        
    else
        log_header "❌ Docker Load Test Failed"
        log_error "Check the logs above for error details"
        log_info "Review container logs and host system resources"
        exit 1
    fi
}

# Execute main function
main "$@" 