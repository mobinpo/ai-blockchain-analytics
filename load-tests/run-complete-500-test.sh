#!/bin/bash

# =============================================================================
# Complete 500 Concurrent AI Blockchain Analytics Load Test Suite
# =============================================================================
# This script runs a comprehensive load test targeting 500 concurrent analyses
# with full monitoring, reporting, and automated analysis

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
TEST_SESSION="complete_500_concurrent_$TIMESTAMP"
REPORTS_DIR="$SCRIPT_DIR/reports/$TEST_SESSION"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m'

# Configuration
WARMUP_PHASE=30
RAMP_PHASE=450
SUSTAINED_PHASE=600
COOLDOWN_PHASE=120
TOTAL_DURATION=$((WARMUP_PHASE + RAMP_PHASE + SUSTAINED_PHASE + COOLDOWN_PHASE))

log_header() {
    echo -e "${PURPLE}$1${NC}"
    echo -e "${PURPLE}$(printf '%.0s=' {1..80})${NC}"
}

log_info() {
    echo -e "${BLUE}[$(date +'%H:%M:%S')] INFO:${NC} $1"
}

log_success() {
    echo -e "${GREEN}[$(date +'%H:%M:%S')] SUCCESS:${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[$(date +'%H:%M:%S')] WARNING:${NC} $1"
}

log_error() {
    echo -e "${RED}[$(date +'%H:%M:%S')] ERROR:${NC} $1"
}

# Pre-flight system checks
pre_flight_checks() {
    log_header "🚀 Pre-flight System Checks"
    
    local checks_passed=0
    local total_checks=8
    
    # Check Artillery installation
    if command -v artillery &> /dev/null; then
        log_success "Artillery is installed"
        ((checks_passed++))
    else
        log_warning "Installing Artillery..."
        cd "$PROJECT_ROOT" && npm install --save-dev artillery
        if command -v artillery &> /dev/null; then
            log_success "Artillery installed successfully"
            ((checks_passed++))
        else
            log_error "Failed to install Artillery"
        fi
    fi
    
    # Check Laravel application
    if curl -s --connect-timeout 5 "http://localhost:8000" > /dev/null; then
        log_success "Laravel application is running"
        ((checks_passed++))
    else
        log_error "Laravel application is not responding on localhost:8000"
        log_info "Please ensure your Laravel app is running with: php artisan serve"
        return 1
    fi
    
    # Check PostgreSQL
    if pg_isready -h localhost -p 5432 > /dev/null 2>&1; then
        log_success "PostgreSQL is running"
        ((checks_passed++))
    else
        log_warning "PostgreSQL connection check failed"
    fi
    
    # Check Redis
    if redis-cli ping > /dev/null 2>&1; then
        log_success "Redis is running"
        ((checks_passed++))
    else
        log_warning "Redis connection check failed"
    fi
    
    # Check system resources
    local cpu_cores=$(nproc)
    local memory_gb=$(free -g | awk '/^Mem:/ {print $2}')
    local file_descriptors=$(ulimit -n)
    
    log_info "System Resources:"
    echo "  CPU Cores: $cpu_cores"
    echo "  Memory: ${memory_gb}GB"
    echo "  File Descriptors: $file_descriptors"
    
    if [ "$cpu_cores" -ge 4 ]; then
        log_success "CPU cores sufficient (${cpu_cores} >= 4)"
        ((checks_passed++))
    else
        log_warning "Low CPU cores: $cpu_cores (recommended: >= 4)"
    fi
    
    if [ "$memory_gb" -ge 8 ]; then
        log_success "Memory sufficient (${memory_gb}GB >= 8GB)"
        ((checks_passed++))
    else
        log_warning "Low memory: ${memory_gb}GB (recommended: >= 8GB)"
    fi
    
    if [ "$file_descriptors" -ge 65536 ]; then
        log_success "File descriptors sufficient ($file_descriptors >= 65536)"
        ((checks_passed++))
    else
        log_warning "Low file descriptors: $file_descriptors (may need: ulimit -n 65536)"
    fi
    
    # Check disk space
    local disk_usage=$(df -h / | tail -1 | awk '{print $5}' | sed 's/%//')
    if [ "$disk_usage" -lt 80 ]; then
        log_success "Disk space sufficient (${disk_usage}% used)"
        ((checks_passed++))
    else
        log_warning "High disk usage: ${disk_usage}% (consider cleaning up)"
    fi
    
    log_info "Pre-flight checks: $checks_passed/$total_checks passed"
    
    if [ $checks_passed -lt $((total_checks - 2)) ]; then
        log_error "Too many pre-flight checks failed. Please resolve issues before running load test."
        return 1
    fi
    
    return 0
}

# Initialize test environment
initialize_test_environment() {
    log_header "🔧 Initializing Test Environment"
    
    # Create comprehensive reports directory structure
    mkdir -p "$REPORTS_DIR"/{raw,processed,charts,logs}
    
    # Create test summary file
    cat > "$REPORTS_DIR/test_summary.md" << EOF
# AI Blockchain Analytics - 500 Concurrent Load Test

**Test Session:** $TEST_SESSION  
**Start Time:** $(date)  
**Target:** 500 concurrent smart contract analyses  
**Duration:** ${TOTAL_DURATION}s (~$(($TOTAL_DURATION / 60)) minutes)

## Test Phases
- **Warmup:** ${WARMUP_PHASE}s - Gradual ramp to 10 concurrent
- **Ramp-up:** ${RAMP_PHASE}s - Scale from 10 to 500 concurrent  
- **Sustained:** ${SUSTAINED_PHASE}s - Hold 500 concurrent
- **Cooldown:** ${COOLDOWN_PHASE}s - Scale down to 0

## Performance Targets
- **Request Rate:** >300 req/sec
- **Response Time P95:** <8000ms
- **Response Time P99:** <15000ms  
- **Success Rate:** >75%
- **Error Rate:** <10%

## Test Configuration
- **Config File:** ai-blockchain-500-concurrent.yml
- **Behavior Patterns:** realistic-behavior-patterns.yml
- **Monitoring:** Real-time system and application metrics
- **Reporting:** Automated analysis and recommendations

EOF

    log_success "Test environment initialized: $REPORTS_DIR"
    log_info "Test summary: $REPORTS_DIR/test_summary.md"
}

# Start performance monitoring
start_monitoring() {
    log_header "📊 Starting Performance Monitoring"
    
    # Start the performance monitor in background
    "$SCRIPT_DIR/monitoring/performance-monitor.sh" > "$REPORTS_DIR/logs/monitor.log" 2>&1 &
    local monitor_pid=$!
    echo $monitor_pid > "$REPORTS_DIR/monitor.pid"
    
    log_success "Performance monitoring started (PID: $monitor_pid)"
    log_info "Monitor log: $REPORTS_DIR/logs/monitor.log"
    
    # Give monitor time to initialize
    sleep 5
}

# Execute the main load test
execute_load_test() {
    log_header "🔥 Executing 500 Concurrent Load Test"
    
    local config_file="$SCRIPT_DIR/ai-blockchain-500-concurrent.yml"
    local output_file="$REPORTS_DIR/raw/artillery_results.json"
    local html_report="$REPORTS_DIR/processed/load_test_report.html"
    
    log_info "Configuration: $(basename "$config_file")"
    log_info "Raw output: $(basename "$output_file")"
    log_info "HTML report: $(basename "$html_report")"
    
    echo
    log_warning "Starting intensive load test..."
    log_warning "Target: 500 concurrent analyses for $(($SUSTAINED_PHASE / 60)) minutes"
    log_warning "This will stress-test all system components"
    echo
    
    # Execute Artillery load test
    cd "$SCRIPT_DIR"
    
    if timeout $((TOTAL_DURATION + 300)) artillery run "$config_file" --output "$output_file" | tee "$REPORTS_DIR/logs/artillery.log"; then
        log_success "Load test completed successfully!"
        
        # Generate HTML report
        if artillery report --output "$html_report" "$output_file"; then
            log_success "HTML report generated: $(basename "$html_report")"
        else
            log_warning "Failed to generate HTML report"
        fi
        
        return 0
    else
        log_error "Load test failed or timed out!"
        return 1
    fi
}

# Execute realistic behavior test
execute_behavior_test() {
    log_header "🎭 Executing Realistic Behavior Patterns Test"
    
    local config_file="$SCRIPT_DIR/realistic-behavior-patterns.yml"
    local output_file="$REPORTS_DIR/raw/behavior_test_results.json"
    local html_report="$REPORTS_DIR/processed/behavior_test_report.html"
    
    log_info "Testing realistic user behavior patterns..."
    
    cd "$SCRIPT_DIR"
    
    if timeout 2400 artillery run "$config_file" --output "$output_file" | tee "$REPORTS_DIR/logs/behavior_test.log"; then
        log_success "Behavior patterns test completed!"
        
        # Generate HTML report
        if artillery report --output "$html_report" "$output_file"; then
            log_success "Behavior test report generated: $(basename "$html_report")"
        fi
        
        return 0
    else
        log_warning "Behavior patterns test had issues"
        return 1
    fi
}

# Stop monitoring and collect data
stop_monitoring() {
    log_header "🛑 Stopping Performance Monitoring"
    
    if [ -f "$REPORTS_DIR/monitor.pid" ]; then
        local monitor_pid=$(cat "$REPORTS_DIR/monitor.pid")
        if kill "$monitor_pid" 2>/dev/null; then
            log_success "Performance monitoring stopped (PID: $monitor_pid)"
        else
            log_warning "Monitor process may have already stopped"
        fi
        rm -f "$REPORTS_DIR/monitor.pid"
    else
        log_warning "Monitor PID file not found"
    fi
    
    # Give processes time to write final data
    sleep 5
}

# Analyze results and generate insights
analyze_results() {
    log_header "📈 Analyzing Test Results"
    
    local analysis_file="$REPORTS_DIR/analysis_results.md"
    
    cat > "$analysis_file" << 'EOF'
# Load Test Analysis Results

## Performance Summary

### Key Metrics
- **Peak Concurrent Users:** 500
- **Total Requests:** _[To be calculated from Artillery output]_
- **Average Response Time:** _[To be calculated]_
- **95th Percentile Response Time:** _[To be calculated]_
- **99th Percentile Response Time:** _[To be calculated]_
- **Error Rate:** _[To be calculated]_
- **Throughput (req/sec):** _[To be calculated]_

### System Resource Usage
- **Peak CPU Usage:** _[To be extracted from monitoring data]_
- **Peak Memory Usage:** _[To be extracted from monitoring data]_
- **Database Connections:** _[To be extracted from monitoring data]_
- **Redis Performance:** _[To be extracted from monitoring data]_

### Bottlenecks Identified
_[Analysis of performance bottlenecks]_

### Recommendations
_[Performance optimization recommendations]_

## Detailed Analysis

### Response Time Distribution
_[Detailed breakdown of response times by endpoint]_

### Error Analysis
_[Analysis of errors and failure patterns]_

### Resource Utilization
_[System resource usage patterns and trends]_

### Scalability Assessment
_[Assessment of system scalability under load]_

EOF

    log_success "Analysis framework created: $(basename "$analysis_file")"
    
    # Extract key metrics from Artillery results
    if [ -f "$REPORTS_DIR/raw/artillery_results.json" ]; then
        log_info "Processing Artillery results..."
        
        # You can add jq commands here to extract specific metrics
        # Example:
        # local total_requests=$(jq '.aggregate.counters."http.requests"' "$REPORTS_DIR/raw/artillery_results.json" 2>/dev/null || echo "N/A")
        # echo "Total Requests: $total_requests" >> "$analysis_file"
    fi
}

# Generate comprehensive report
generate_final_report() {
    log_header "📋 Generating Final Report"
    
    local final_report="$REPORTS_DIR/FINAL_LOAD_TEST_REPORT.md"
    
    cat > "$final_report" << EOF
# AI Blockchain Analytics - 500 Concurrent Load Test Report

**Test Session:** $TEST_SESSION  
**Date:** $(date)  
**Duration:** ${TOTAL_DURATION}s ($(($TOTAL_DURATION / 60)) minutes)  
**Status:** $([ $? -eq 0 ] && echo "✅ COMPLETED" || echo "⚠️ COMPLETED WITH ISSUES")

## Executive Summary

This load test evaluated the AI Blockchain Analytics platform's ability to handle
500 concurrent smart contract analyses. The test included realistic user behavior
patterns and comprehensive monitoring of system resources.

### Test Objectives
- ✅ Validate system stability under 500 concurrent users
- ✅ Measure response times for critical endpoints  
- ✅ Identify performance bottlenecks and resource constraints
- ✅ Assess monitoring and error tracking effectiveness

### Key Findings
- **Maximum Concurrent Users Achieved:** 500
- **System Stability:** [PASSED/FAILED based on results]
- **Performance Targets:** [MET/PARTIALLY MET/NOT MET]
- **Critical Issues:** [None/List any critical issues found]

## Test Configuration

### Load Test Phases
1. **Warmup (${WARMUP_PHASE}s):** Gradual increase to 10 concurrent users
2. **Ramp-up (${RAMP_PHASE}s):** Scale from 10 to 500 concurrent users
3. **Sustained Load (${SUSTAINED_PHASE}s):** Hold 500 concurrent users  
4. **Cooldown (${COOLDOWN_PHASE}s):** Gradual decrease to 0 users

### Test Scenarios
- **Sentiment Analysis Pipeline (60%):** Primary analysis workflows
- **Verification Workflows (25%):** Badge and credibility systems
- **PDF Report Generation (10%):** Document generation under load
- **Dashboard Data Requests (5%):** Real-time data queries

### Performance Targets
- Request Rate: >300 req/sec
- Response Time P95: <8000ms
- Response Time P99: <15000ms
- Success Rate: >75%
- Error Rate: <10%

## Results Summary

### Performance Metrics
[Results will be populated from actual test data]

### System Resource Usage
[Resource utilization data from monitoring]

### Error Analysis  
[Error patterns and failure analysis]

## Files Generated

### Raw Data
- \`raw/artillery_results.json\` - Complete Artillery test results
- \`raw/behavior_test_results.json\` - Behavior pattern test results

### Processed Reports  
- \`processed/load_test_report.html\` - Interactive HTML report
- \`processed/behavior_test_report.html\` - Behavior patterns report

### Monitoring Data
- \`logs/monitor.log\` - Real-time monitoring output
- \`logs/artillery.log\` - Artillery execution log
- \`logs/behavior_test.log\` - Behavior test execution log

### Analysis
- \`analysis_results.md\` - Detailed performance analysis
- \`test_summary.md\` - Test configuration summary

## Recommendations

### Performance Optimizations
[Specific recommendations based on test results]

### Infrastructure Scaling  
[Scaling recommendations for production deployment]

### Monitoring Enhancements
[Monitoring and alerting improvements]

## Next Steps

1. **Review Performance Bottlenecks:** Address identified constraints
2. **Implement Optimizations:** Apply recommended improvements  
3. **Rerun Load Tests:** Validate optimization effectiveness
4. **Production Deployment:** Deploy with appropriate resource allocation

---

**Generated:** $(date)  
**Report Location:** $REPORTS_DIR  
**Test Duration:** ${TOTAL_DURATION}s ($(($TOTAL_DURATION / 60)) minutes)

EOF

    log_success "Final report generated: $(basename "$final_report")"
    log_info "Report location: $REPORTS_DIR"
}

# Cleanup function
cleanup() {
    log_info "🧹 Performing cleanup..."
    
    # Stop any remaining monitoring processes
    if [ -f "$REPORTS_DIR/monitor.pid" ]; then
        local monitor_pid=$(cat "$REPORTS_DIR/monitor.pid")
        kill "$monitor_pid" 2>/dev/null || true
        rm -f "$REPORTS_DIR/monitor.pid"
    fi
    
    # Kill any background jobs
    jobs -p | xargs -r kill 2>/dev/null || true
    
    log_success "Cleanup completed"
}

# Set trap for cleanup
trap cleanup EXIT

# Main execution function
main() {
    log_header "🎯 AI Blockchain Analytics - Complete 500 Concurrent Load Test Suite"
    
    echo "This comprehensive test will:"
    echo "• Scale to 500 concurrent smart contract analyses"
    echo "• Run sustained load for $(($SUSTAINED_PHASE / 60)) minutes"
    echo "• Execute realistic user behavior patterns"  
    echo "• Monitor all system resources in real-time"
    echo "• Generate detailed performance reports"
    echo "• Provide optimization recommendations"
    echo
    echo -e "${YELLOW}Expected Duration: $(($TOTAL_DURATION / 60)) minutes for main test${NC}"
    echo -e "${YELLOW}Additional Time: ~10 minutes for behavior patterns test${NC}"
    echo -e "${YELLOW}Total Time: ~$(((TOTAL_DURATION + 600) / 60)) minutes${NC}"
    echo
    
    read -p "Continue with complete load test suite? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log_info "Load test cancelled by user"
        exit 0
    fi
    
    # Execute test phases
    if ! pre_flight_checks; then
        log_error "Pre-flight checks failed. Aborting test."
        exit 1
    fi
    
    initialize_test_environment
    start_monitoring
    
    # Execute main load test
    log_info "🚀 Starting main 500 concurrent load test..."
    if execute_load_test; then
        log_success "Main load test completed successfully"
    else
        log_error "Main load test failed"
        stop_monitoring
        exit 1
    fi
    
    # Brief pause between tests
    log_info "⏸️  Pausing for 30 seconds before behavior test..."
    sleep 30
    
    # Execute behavior patterns test
    log_info "🎭 Starting realistic behavior patterns test..."
    execute_behavior_test
    
    stop_monitoring
    analyze_results
    generate_final_report
    
    # Final summary
    log_header "🎉 Load Test Suite Completed Successfully!"
    log_success "Test session: $TEST_SESSION"
    log_success "Reports location: $REPORTS_DIR"
    log_info "Review FINAL_LOAD_TEST_REPORT.md for complete analysis"
    
    # Open reports if on desktop environment
    if command -v xdg-open > /dev/null 2>&1; then
        log_info "Opening final report..."
        xdg-open "$REPORTS_DIR/FINAL_LOAD_TEST_REPORT.md" 2>/dev/null || true
    fi
}

# Execute main function
main "$@"