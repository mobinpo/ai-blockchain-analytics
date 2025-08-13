#!/bin/bash

# =============================================================================
# AI Blockchain Analytics - 500 Concurrent Analysis Load Test Runner
# =============================================================================
# This script runs comprehensive load tests targeting 500 concurrent
# AI blockchain analyses including sentiment analysis, verification, and PDF generation

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
REPORTS_DIR="$SCRIPT_DIR/reports"
TEST_NAME="ai_analysis_500_concurrent_$TIMESTAMP"

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

# System monitoring
MONITOR_PID=""
MONITOR_LOG="$REPORTS_DIR/${TEST_NAME}_system_monitor.log"

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

# System resource monitoring
start_system_monitor() {
    log_step "Starting system resource monitoring"
    
    (
        while true; do
            echo "$(date '+%Y-%m-%d %H:%M:%S')," \
                 "$(cat /proc/loadavg | cut -d' ' -f1-3)," \
                 "$(free | grep '^Mem:' | awk '{printf "%.1f", $3/$2 * 100.0}')," \
                 "$(iostat -c 1 1 | tail -n +4 | head -n 1 | awk '{print $1}')," \
                 "$(ss -tun | wc -l)"
            sleep 5
        done
    ) > "$MONITOR_LOG" &
    
    MONITOR_PID=$!
    log_success "System monitoring started (PID: $MONITOR_PID)"
}

stop_system_monitor() {
    if [ ! -z "$MONITOR_PID" ]; then
        kill $MONITOR_PID 2>/dev/null || true
        log_success "System monitoring stopped"
    fi
}

# Pre-flight checks
pre_flight_checks() {
    log_header "🚀 AI Blockchain Analytics - Pre-flight Checks"
    
    # Check if Artillery is installed
    if ! command -v artillery &> /dev/null; then
        log_warning "Artillery is not installed. Installing..."
        cd "$PROJECT_ROOT"
        npm install --save-dev artillery@latest
        npm install --save-dev @artilleryio/plugin-metrics-by-endpoint
    else
        log_success "Artillery is installed: $(artillery version)"
    fi
    
    # Check if Laravel app is running
    log_step "Checking Laravel application availability"
    if ! curl -s --connect-timeout 10 "http://localhost:8000" > /dev/null; then
        log_error "Laravel app is not responding on localhost:8000"
        log_info "Please start your Laravel application first:"
        log_info "  php artisan serve --host=0.0.0.0 --port=8000"
        log_info "  or"
        log_info "  php artisan octane:start --host=0.0.0.0 --port=8000"
        exit 1
    else
        log_success "Laravel app is responding"
    fi
    
    # Test critical endpoints
    log_step "Testing critical API endpoints"
    
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
    
    # Check test data
    if [ ! -f "$SCRIPT_DIR/data/ai-analysis-samples.json" ]; then
        log_error "Test data file not found: $SCRIPT_DIR/data/ai-analysis-samples.json"
        exit 1
    fi
    log_success "Test data file found"
    
    # Create reports directory
    mkdir -p "$REPORTS_DIR"
    log_success "Reports directory ready: $REPORTS_DIR"
    
    # System resource check
    log_step "System Resources Assessment"
    cpu_cores=$(nproc)
    total_mem=$(free -h | awk '/^Mem:/ {print $2}')
    available_mem=$(free -h | awk '/^Mem:/ {print $7}')
    file_limit=$(ulimit -n)
    max_processes=$(ulimit -u)
    
    echo "  🔹 CPU Cores: $cpu_cores"
    echo "  🔹 Total Memory: $total_mem"
    echo "  🔹 Available Memory: $available_mem"
    echo "  🔹 File descriptor limit: $file_limit"
    echo "  🔹 Process limit: $max_processes"
    
    # Recommendations
    if [ "$file_limit" -lt 10000 ]; then
        log_warning "File descriptor limit is low. Consider: ulimit -n 10000"
    fi
    
    if [ "$cpu_cores" -lt 4 ]; then
        log_warning "CPU cores are limited. Performance may be impacted."
    fi
    
    log_success "Pre-flight checks completed"
}

# Run the load test
run_load_test() {
    log_header "🔥 Running AI Blockchain 500 Concurrent Analysis Load Test"
    
    log_info "Test Configuration:"
    echo "  📁 Config File: $CONFIG_FILE"
    echo "  📊 Output File: $OUTPUT_FILE"
    echo "  📈 HTML Report: $HTML_REPORT"
    echo "  📋 Summary File: $SUMMARY_FILE"
    echo
    
    log_info "Test Profile:"
    echo "  🎯 Target: 500 concurrent AI blockchain analyses"
    echo "  ⏱️  Duration: ~20 minutes total"
    echo "  🚀 Ramp-up: 7.5 minutes to reach 500 concurrent"
    echo "  💪 Sustained: 8 minutes at 500 concurrent load"
    echo "  📉 Cool-down: 90 seconds scale down"
    echo
    
    log_info "Test Scenarios:"
    echo "  📈 60% - Sentiment Analysis Pipeline"
    echo "  🔐 25% - Verification Analysis"
    echo "  📄 10% - PDF Report Generation"
    echo "  📊  5% - Dashboard Data Requests"
    echo
    
    log_warning "⚠️  This is a high-intensity test that will stress your system"
    log_warning "⚠️  Monitor system resources during the test"
    
    echo
    read -p "Continue with load test? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log_info "Load test cancelled by user"
        exit 0
    fi
    
    # Start system monitoring
    start_system_monitor
    
    log_info "🚀 Starting AI Blockchain Analytics load test..."
    
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
        
        # Stop monitoring
        stop_system_monitor
        
        return 0
        
    else
        log_error "❌ Load test failed!"
        stop_system_monitor
        return 1
    fi
}

# Generate comprehensive test summary
generate_summary() {
    local json_file="$1"
    
    log_header "📊 Generating Comprehensive Test Summary"
    
    # Extract key metrics from JSON (basic parsing)
    local total_requests=$(grep -o '"count":[0-9]*' "$json_file" | head -1 | cut -d':' -f2)
    local total_errors=$(grep -o '"errors":[0-9]*' "$json_file" | head -1 | cut -d':' -f2)
    
    cat > "$SUMMARY_FILE" << EOF
# AI Blockchain Analytics - Load Test Results
## 500 Concurrent Analysis Performance Report

**Test Execution:** $TEST_NAME  
**Timestamp:** $(date)  
**Target Load:** 500 concurrent AI blockchain analyses  
**Test Duration:** ~20 minutes

---

## 📋 Test Configuration

### Load Profile
- **Warmup:** 30 seconds at 10 req/sec
- **Ramp Phase 1:** 120 seconds scaling 10→100 req/sec  
- **Ramp Phase 2:** 180 seconds scaling 100→300 req/sec
- **Ramp Phase 3:** 120 seconds scaling 300→500 req/sec
- **Sustained Load:** 480 seconds at 500 req/sec
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

**Total Requests:** ${total_requests:-"N/A"}  
**Total Errors:** ${total_errors:-"N/A"}  
**Error Rate:** TBD%  
**Average RPS:** TBD  

*(Detailed metrics available in JSON and HTML reports)*

---

## 📁 Generated Files

- **JSON Report:** \`$OUTPUT_FILE\`
- **HTML Report:** \`$HTML_REPORT\`
- **System Monitor:** \`$MONITOR_LOG\`
- **Summary Report:** \`$SUMMARY_FILE\`

---

## 🔧 Analysis Commands

\`\`\`bash
# View detailed HTML report
open "$HTML_REPORT"

# Parse JSON metrics
cat "$OUTPUT_FILE" | jq '.aggregate'

# View system resource usage
head -20 "$MONITOR_LOG"

# Quick performance check
grep -E "(min|max|mean|p95|p99)" "$OUTPUT_FILE"
\`\`\`

---

## 💡 Recommendations

### Performance Optimization
- Monitor RoadRunner worker pool utilization
- Check Redis memory usage and connection pooling
- Verify PostgreSQL query performance and indexing
- Review Laravel queue processing capacity

### Infrastructure Scaling  
- Consider horizontal scaling for >500 concurrent users
- Monitor database connection limits
- Evaluate CDN for static assets
- Review load balancer configuration

### Monitoring Setup
- Implement Sentry error tracking in production
- Configure Telescope performance monitoring
- Set up Grafana dashboards for real-time metrics
- Create alerting rules for performance thresholds

---

**Test Environment:** $(uname -a)  
**Generated:** $(date)
EOF

    log_success "📋 Comprehensive test summary generated: $SUMMARY_FILE"
}

# Cleanup function
cleanup() {
    log_header "🧹 Cleanup"
    stop_system_monitor
    log_success "Cleanup completed"
}

# Set trap for cleanup
trap cleanup EXIT

# Main execution
main() {
    log_header "🎯 AI Blockchain Analytics - 500 Concurrent Load Test"
    
    echo "🤖 This test will simulate 500 concurrent users performing:"
    echo "   • Sentiment analysis on cryptocurrency data"
    echo "   • Verification workflows for blockchain data"
    echo "   • PDF report generation for analysis results"
    echo "   • Dashboard data requests and API calls"
    echo
    echo "📈 Expected system impact:"
    echo "   • High CPU utilization during AI processing"
    echo "   • Increased memory usage for concurrent analyses"
    echo "   • Database query load for data retrieval"
    echo "   • Network traffic for API communications"
    echo
    
    pre_flight_checks
    
    if run_load_test; then
        log_header "🎉 Load Test Completed Successfully!"
        echo
        log_success "✅ Test execution finished"
        log_success "✅ Reports generated in: $REPORTS_DIR"
        log_success "✅ System monitoring data collected"
        echo
        log_info "📋 Next Steps:"
        echo "   1. Review the HTML report for detailed metrics"
        echo "   2. Analyze system resource usage patterns"
        echo "   3. Identify performance bottlenecks"
        echo "   4. Optimize application and infrastructure"
        echo "   5. Plan for production scaling requirements"
        echo
        log_info "🌐 Open HTML report: file://$HTML_REPORT"
        
    else
        log_header "❌ Load Test Failed"
        log_error "Check the logs above for error details"
        log_info "Review system resources and application logs"
        exit 1
    fi
}

# Execute main function
main "$@"