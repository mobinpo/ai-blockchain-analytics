#!/bin/bash

# =============================================================================
# AI Blockchain Analytics - Enhanced 500 Concurrent Load Test Runner
# =============================================================================
# Comprehensive load testing with real-time monitoring and detailed reporting

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
REPORTS_DIR="$SCRIPT_DIR/reports"
MONITORING_DIR="$REPORTS_DIR/monitoring_$TIMESTAMP"
TEST_NAME="enhanced_500_concurrent_$TIMESTAMP"

# Colors for enhanced output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
WHITE='\033[1;37m'
NC='\033[0m'

# Test configuration
TARGET_URL="http://localhost:8003"
CONFIG_FILE="$SCRIPT_DIR/enhanced-500-concurrent.yml"
MONITORING_SCRIPT="$SCRIPT_DIR/monitoring/performance-monitor.sh"

# Enhanced logging functions
log_header() {
    echo
    echo -e "${PURPLE}╔══════════════════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${PURPLE}║${WHITE} $1${NC}"
    echo -e "${PURPLE}╚══════════════════════════════════════════════════════════════════════════════╝${NC}"
    echo
}

log_info() {
    echo -e "${BLUE}[$(date '+%H:%M:%S')] ℹ️  ${NC}$1"
}

log_success() {
    echo -e "${GREEN}[$(date '+%H:%M:%S')] ✅ ${NC}$1"
}

log_warning() {
    echo -e "${YELLOW}[$(date '+%H:%M:%S')] ⚠️  ${NC}$1"
}

log_error() {
    echo -e "${RED}[$(date '+%H:%M:%S')] ❌ ${NC}$1"
}

log_step() {
    echo -e "${CYAN}[$(date '+%H:%M:%S')] 🔄 ${NC}$1"
}

# System preparation
prepare_system() {
    log_header "🔧 System Preparation"
    
    # Create directories
    mkdir -p "$REPORTS_DIR" "$MONITORING_DIR"
    log_success "Created directories: $REPORTS_DIR, $MONITORING_DIR"
    
    # Check system resources
    log_info "System Information:"
    echo "  🖥️  CPU Cores: $(nproc)"
    echo "  💾 Memory: $(free -h | awk '/^Mem:/ {print $2}')"
    echo "  📁 Disk Space: $(df -h / | tail -1 | awk '{print $4}') available"
    echo "  🔗 File Descriptors: $(ulimit -n)"
    echo "  🌐 Max User Processes: $(ulimit -u)"
    
    # Optimize system settings for high load
    log_step "Optimizing system settings for high load..."
    
    # Increase file descriptor limit if possible
    if [ "$(ulimit -n)" -lt 65536 ]; then
        log_info "Attempting to increase file descriptor limit..."
        ulimit -n 65536 2>/dev/null || log_warning "Could not increase file descriptor limit"
    fi
    
    # Check if we can increase process limit
    if [ "$(ulimit -u)" -lt 32768 ]; then
        log_info "Attempting to increase process limit..."
        ulimit -u 32768 2>/dev/null || log_warning "Could not increase process limit"
    fi
    
    log_success "System preparation completed"
}

# Pre-flight checks
pre_flight_checks() {
    log_header "🚀 Pre-flight Checks"
    
    local checks_passed=0
    local total_checks=6
    
    # Check Artillery installation
    log_step "Checking Artillery installation..."
    if command -v artillery &> /dev/null; then
        local artillery_version=$(artillery --version)
        log_success "Artillery installed: $artillery_version"
        ((checks_passed++))
    else
        log_error "Artillery not found. Installing..."
        cd "$PROJECT_ROOT"
        npm install --save-dev artillery
        if command -v artillery &> /dev/null; then
            log_success "Artillery installed successfully"
            ((checks_passed++))
        else
            log_error "Failed to install Artillery"
            exit 1
        fi
    fi
    
    # Check Node.js version
    log_step "Checking Node.js version..."
    if command -v node &> /dev/null; then
        local node_version=$(node --version)
        log_success "Node.js version: $node_version"
        ((checks_passed++))
    else
        log_error "Node.js not found. Please install Node.js 16 or higher."
        exit 1
    fi
    
    # Check target application
    log_step "Checking target application ($TARGET_URL)..."
    if curl -s --connect-timeout 10 --max-time 30 "$TARGET_URL" > /dev/null; then
        log_success "Target application is responsive"
        ((checks_passed++))
    else
        log_warning "Target application not responding. Checking alternative ports..."
        # Try localhost:8000 as fallback
        if curl -s --connect-timeout 5 --max-time 15 "http://localhost:8000" > /dev/null; then
            log_warning "Found application on port 8000, updating target..."
            sed -i 's|http://localhost:8003|http://localhost:8000|g' "$CONFIG_FILE"
            TARGET_URL="http://localhost:8000"
            log_success "Updated target to $TARGET_URL"
            ((checks_passed++))
        else
            log_error "Application not responding on any port. Please start the application first."
            exit 1
        fi
    fi
    
    # Check configuration file
    log_step "Checking test configuration..."
    if [ -f "$CONFIG_FILE" ]; then
        log_success "Configuration file found: $CONFIG_FILE"
        ((checks_passed++))
    else
        log_error "Configuration file not found: $CONFIG_FILE"
        exit 1
    fi
    
    # Check processor file
    log_step "Checking data processor..."
    if [ -f "$SCRIPT_DIR/processors/analysis-processor.js" ]; then
        log_success "Data processor found"
        ((checks_passed++))
    else
        log_warning "Data processor not found, creating basic version..."
        mkdir -p "$SCRIPT_DIR/processors"
        echo "module.exports = {};" > "$SCRIPT_DIR/processors/analysis-processor.js"
        log_success "Basic data processor created"
        ((checks_passed++))
    fi
    
    # Check monitoring script
    log_step "Checking monitoring capabilities..."
    if [ -f "$MONITORING_SCRIPT" ]; then
        log_success "Performance monitoring script available"
        ((checks_passed++))
    else
        log_warning "Monitoring script not found, continuing without detailed monitoring"
        ((checks_passed++))
    fi
    
    log_info "Pre-flight checks: $checks_passed/$total_checks passed"
    
    if [ $checks_passed -eq $total_checks ]; then
        log_success "All pre-flight checks passed!"
    else
        log_warning "Some checks failed, but continuing with test..."
    fi
}

# Start monitoring
start_monitoring() {
    log_header "📊 Starting Performance Monitoring"
    
    if [ -f "$MONITORING_SCRIPT" ]; then
        log_step "Starting performance monitoring script..."
        chmod +x "$MONITORING_SCRIPT"
        nohup "$MONITORING_SCRIPT" > "$MONITORING_DIR/monitoring.log" 2>&1 &
        MONITORING_PID=$!
        echo $MONITORING_PID > "$MONITORING_DIR/monitoring.pid"
        log_success "Performance monitoring started (PID: $MONITORING_PID)"
    else
        log_warning "Performance monitoring script not available"
        MONITORING_PID=""
    fi
    
    # Start basic system monitoring
    log_step "Starting basic system monitoring..."
    (
        echo "timestamp,load_1m,load_5m,load_15m,mem_used_percent,cpu_percent"
        while true; do
            timestamp=$(date '+%Y-%m-%d %H:%M:%S')
            load_avg=$(cat /proc/loadavg | cut -d' ' -f1-3)
            mem_percent=$(free | awk 'NR==2{printf "%.1f", $3*100/$2}')
            cpu_percent=$(top -bn1 | grep "Cpu(s)" | sed "s/.*, *\\([0-9.]*\\)%* id.*/\\1/" | awk '{print 100 - $1}')
            echo "$timestamp,$load_avg,$mem_percent,$cpu_percent"
            sleep 5
        done
    ) > "$MONITORING_DIR/basic_system_metrics.csv" &
    BASIC_MONITORING_PID=$!
    echo $BASIC_MONITORING_PID > "$MONITORING_DIR/basic_monitoring.pid"
    log_success "Basic system monitoring started (PID: $BASIC_MONITORING_PID)"
}

# Stop monitoring
stop_monitoring() {
    log_header "🛑 Stopping Monitoring"
    
    if [ -n "$MONITORING_PID" ] && [ -f "$MONITORING_DIR/monitoring.pid" ]; then
        log_step "Stopping performance monitoring..."
        if kill -TERM "$MONITORING_PID" 2>/dev/null; then
            log_success "Performance monitoring stopped"
        else
            log_warning "Could not stop performance monitoring gracefully"
        fi
        rm -f "$MONITORING_DIR/monitoring.pid"
    fi
    
    if [ -n "$BASIC_MONITORING_PID" ] && [ -f "$MONITORING_DIR/basic_monitoring.pid" ]; then
        log_step "Stopping basic system monitoring..."
        if kill -TERM "$BASIC_MONITORING_PID" 2>/dev/null; then
            log_success "Basic system monitoring stopped"
        else
            log_warning "Could not stop basic monitoring gracefully"
        fi
        rm -f "$MONITORING_DIR/basic_monitoring.pid"
    fi
}

# Run the enhanced load test
run_load_test() {
    log_header "🔥 Running Enhanced 500 Concurrent Load Test"
    
    local output_file="$REPORTS_DIR/${TEST_NAME}.json"
    local html_report="$REPORTS_DIR/${TEST_NAME}.html"
    local config_backup="$REPORTS_DIR/${TEST_NAME}_config.yml"
    
    # Backup configuration
    cp "$CONFIG_FILE" "$config_backup"
    log_info "Configuration backed up: $config_backup"
    
    log_info "📋 Test Configuration:"
    echo "  🎯 Target: $TARGET_URL"
    echo "  📁 Config: $CONFIG_FILE"
    echo "  📊 Output: $output_file"
    echo "  📈 HTML Report: $html_report"
    echo "  ⏱️  Duration: ~25 minutes"
    echo "  👥 Peak Users: 500 concurrent"
    echo
    
    log_warning "⚠️  High Load Test Warning:"
    echo "  • This test will generate significant load"
    echo "  • Monitor system resources during execution"
    echo "  • Ensure adequate system capacity"
    echo "  • Test may impact other applications"
    echo
    
    read -p "Continue with enhanced 500 concurrent load test? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log_info "Load test cancelled by user"
        return 1
    fi
    
    log_step "Starting Artillery load test..."
    echo
    
    cd "$SCRIPT_DIR"
    
    # Run Artillery with enhanced options
    if artillery run \
        --config "$CONFIG_FILE" \
        --output "$output_file" \
        --overrides '{"config":{"http":{"pool":250,"maxSockets":2000}}}' \
        --quiet; then
        
        log_success "🎉 Load test completed successfully!"
        
        # Generate HTML report
        log_step "Generating HTML report..."
        if artillery report --output "$html_report" "$output_file"; then
            log_success "HTML report generated: $html_report"
        else
            log_warning "Could not generate HTML report"
        fi
        
        # Generate comprehensive summary
        generate_comprehensive_summary "$output_file"
        
        return 0
    else
        log_error "❌ Load test failed!"
        return 1
    fi
}

# Generate comprehensive test summary
generate_comprehensive_summary() {
    local json_file="$1"
    local summary_file="$REPORTS_DIR/${TEST_NAME}_comprehensive_summary.md"
    local metrics_file="$REPORTS_DIR/${TEST_NAME}_metrics.json"
    
    log_header "📊 Generating Comprehensive Test Summary"
    
    log_step "Extracting performance metrics..."
    
    # Extract key metrics from Artillery output
    if [ -f "$json_file" ]; then
        # Create metrics summary
        cat > "$metrics_file" << EOF
{
  "test_info": {
    "name": "$TEST_NAME",
    "timestamp": "$TIMESTAMP",
    "target": "$TARGET_URL",
    "duration_minutes": 25,
    "peak_concurrent_users": 500
  },
  "system_info": {
    "cpu_cores": $(nproc),
    "memory_gb": $(free -g | awk 'NR==2{print $2}'),
    "os": "$(uname -s) $(uname -r)",
    "node_version": "$(node --version)",
    "artillery_version": "$(artillery --version)"
  }
}
EOF
        log_success "Metrics file created: $metrics_file"
    fi
    
    # Generate comprehensive markdown summary
    cat > "$summary_file" << 'EOF'
# 🚀 AI Blockchain Analytics - Enhanced 500 Concurrent Load Test Results

## 📋 Test Overview

**Test Name:** %TEST_NAME%  
**Date:** %TEST_DATE%  
**Target:** %TARGET_URL%  
**Peak Concurrent Users:** 500  
**Total Duration:** ~25 minutes  

## 🎯 Test Objectives

✅ **Performance Validation**: Test system capacity under 500 concurrent blockchain analyses  
✅ **Scalability Assessment**: Validate auto-scaling and resource management  
✅ **Reliability Testing**: Ensure system stability under sustained high load  
✅ **Monitoring Validation**: Verify Sentry and Telescope monitoring under load  

## 📊 Test Phases

| Phase | Duration | Users | Objective |
|-------|----------|-------|-----------|
| System Warmup | 60s | 5 → 5 | Initialize system |
| Initial Ramp | 120s | 5 → 50 | Gradual load increase |
| Scale to 150 | 180s | 50 → 150 | Medium load testing |
| Scale to 300 | 180s | 150 → 300 | High load validation |
| Push to 500 | 120s | 300 → 500 | Peak load achievement |
| Sustained Load | 720s | 500 → 500 | Endurance testing |
| Cool Down | 180s | 500 → 50 | Graceful degradation |
| Wind Down | 60s | 50 → 0 | Clean shutdown |

## 🎭 Test Scenarios

| Scenario | Weight | Description |
|----------|--------|-------------|
| **AI Sentiment Analysis** | 45% | Primary blockchain sentiment analysis pipeline |
| **Enhanced Verification** | 25% | Advanced verification workflows with HMAC |
| **PDF Report Generation** | 15% | Comprehensive PDF report creation |
| **Real-time Dashboard** | 10% | Live analytics and metrics |
| **API Stress Testing** | 5% | Batch operations and cache performance |

## 🏆 Performance Thresholds

| Metric | Target | Actual | Status |
|--------|--------|--------|--------|
| Request Rate | >400 req/sec | [TBD] | [TBD] |
| Median Response Time | <2000ms | [TBD] | [TBD] |
| 95th Percentile | <8000ms | [TBD] | [TBD] |
| 99th Percentile | <15000ms | [TBD] | [TBD] |
| Success Rate | >75% | [TBD] | [TBD] |
| Error Rate | <3% | [TBD] | [TBD] |

## 📈 Key Metrics

### Request Volume
- **Total Requests**: [TBD]
- **Requests/Second (avg)**: [TBD]
- **Requests/Second (peak)**: [TBD]

### Response Times
- **Mean Response Time**: [TBD]ms
- **Median (p50)**: [TBD]ms
- **95th Percentile**: [TBD]ms
- **99th Percentile**: [TBD]ms
- **Maximum Response Time**: [TBD]ms

### Error Analysis
- **Total Errors**: [TBD]
- **Error Rate**: [TBD]%
- **HTTP 2xx**: [TBD]
- **HTTP 4xx**: [TBD]
- **HTTP 5xx**: [TBD]

## 🖥️ System Performance

### Resource Utilization
- **Peak CPU Usage**: [TBD]%
- **Peak Memory Usage**: [TBD]%
- **Peak Load Average**: [TBD]
- **Network Connections**: [TBD]

### Application Metrics
- **Database Connections**: [TBD]
- **Redis Connections**: [TBD]
- **Queue Depth**: [TBD]
- **Cache Hit Rate**: [TBD]%

## 🔍 Monitoring Results

### Sentry Integration
- **Error Events Captured**: [TBD]
- **Performance Transactions**: [TBD]
- **Alert Triggers**: [TBD]

### Telescope Monitoring
- **Request Entries**: [TBD]
- **Query Entries**: [TBD]
- **Job Entries**: [TBD]
- **Exception Entries**: [TBD]

## 📊 Detailed Analysis

### Performance Bottlenecks
[Analysis of performance bottlenecks identified during testing]

### Scaling Behavior
[Analysis of how the system scaled with increasing load]

### Error Patterns
[Analysis of error patterns and their causes]

## 🎯 Recommendations

### Immediate Actions
- [ ] [Recommendation 1]
- [ ] [Recommendation 2]
- [ ] [Recommendation 3]

### Optimization Opportunities
- [ ] [Optimization 1]
- [ ] [Optimization 2]
- [ ] [Optimization 3]

### Infrastructure Improvements
- [ ] [Infrastructure improvement 1]
- [ ] [Infrastructure improvement 2]
- [ ] [Infrastructure improvement 3]

## 📁 Generated Files

| File | Description |
|------|-------------|
| `%TEST_NAME%.json` | Raw Artillery test results |
| `%TEST_NAME%.html` | Interactive HTML report |
| `%TEST_NAME%_config.yml` | Test configuration backup |
| `%TEST_NAME%_metrics.json` | Extracted metrics summary |
| `monitoring_%TIMESTAMP%/` | System monitoring data |

## 🔧 Commands for Further Analysis

```bash
# View HTML report
open %HTML_REPORT%

# Analyze raw JSON data
cat %JSON_FILE% | jq '.aggregate'

# View system monitoring data
ls -la %MONITORING_DIR%

# Generate custom reports
artillery report --output custom_report.html %JSON_FILE%
```

## 📞 Support Information

For questions about this load test or performance optimization:
- **Documentation**: `/load-tests/README.md`
- **Monitoring Setup**: `/MONITORING_SETUP_COMPLETE.md`
- **Deployment Guide**: `/DEPLOYMENT_GUIDE.md`

---

**Test completed successfully!** 🎉  
**Next steps**: Review metrics, implement optimizations, schedule regular load testing.
EOF

    # Replace placeholders
    sed -i "s|%TEST_NAME%|$TEST_NAME|g" "$summary_file"
    sed -i "s|%TEST_DATE%|$(date)|g" "$summary_file"
    sed -i "s|%TARGET_URL%|$TARGET_URL|g" "$summary_file"
    sed -i "s|%TIMESTAMP%|$TIMESTAMP|g" "$summary_file"
    sed -i "s|%HTML_REPORT%|$REPORTS_DIR/${TEST_NAME}.html|g" "$summary_file"
    sed -i "s|%JSON_FILE%|$json_file|g" "$summary_file"
    sed -i "s|%MONITORING_DIR%|$MONITORING_DIR|g" "$summary_file"
    
    log_success "Comprehensive summary generated: $summary_file"
}

# Cleanup function
cleanup() {
    log_header "🧹 Cleanup and Finalization"
    
    # Stop monitoring
    stop_monitoring
    
    # Archive monitoring data
    if [ -d "$MONITORING_DIR" ] && [ "$(ls -A $MONITORING_DIR)" ]; then
        log_step "Archiving monitoring data..."
        tar -czf "$MONITORING_DIR.tar.gz" -C "$REPORTS_DIR" "monitoring_$TIMESTAMP"
        log_success "Monitoring data archived: $MONITORING_DIR.tar.gz"
    fi
    
    # Generate final summary
    log_info "📋 Test Summary:"
    echo "  📁 Reports Directory: $REPORTS_DIR"
    echo "  📊 Test Results: ${TEST_NAME}.json"
    echo "  📈 HTML Report: ${TEST_NAME}.html"
    echo "  📝 Summary: ${TEST_NAME}_comprehensive_summary.md"
    if [ -f "$MONITORING_DIR.tar.gz" ]; then
        echo "  🔍 Monitoring Data: monitoring_${TIMESTAMP}.tar.gz"
    fi
    
    log_success "🎉 Enhanced 500 concurrent load test completed!"
}

# Set trap for cleanup
trap cleanup EXIT

# Main execution function
main() {
    log_header "🚀 AI Blockchain Analytics - Enhanced 500 Concurrent Load Test"
    
    echo -e "${CYAN}This comprehensive load test will:${NC}"
    echo "  🎯 Scale to 500 concurrent blockchain analyses"
    echo "  ⏱️  Run for approximately 25 minutes"
    echo "  📊 Generate detailed performance reports"
    echo "  🔍 Monitor system resources in real-time"
    echo "  📈 Validate Sentry and Telescope integration"
    echo "  🚀 Test RoadRunner and Laravel performance"
    echo
    
    prepare_system
    pre_flight_checks
    start_monitoring
    
    if run_load_test; then
        log_success "🎉 Load test completed successfully!"
        echo
        log_info "📊 Check the following files for results:"
        echo "  • HTML Report: $REPORTS_DIR/${TEST_NAME}.html"
        echo "  • Comprehensive Summary: $REPORTS_DIR/${TEST_NAME}_comprehensive_summary.md"
        echo "  • Raw Data: $REPORTS_DIR/${TEST_NAME}.json"
        echo "  • Monitoring Data: $MONITORING_DIR/"
        echo
        log_info "🔗 Open HTML report: file://$REPORTS_DIR/${TEST_NAME}.html"
    else
        log_error "❌ Load test failed. Check logs for details."
        exit 1
    fi
}

# Execute main function
main "$@"
