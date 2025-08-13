#!/bin/bash

# =============================================================================
# AI Blockchain Analytics - 500 Concurrent Load Test Runner
# =============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
REPORTS_DIR="$SCRIPT_DIR/reports"
TEST_NAME="concurrent_500_$TIMESTAMP"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m'

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
    echo -e "${PURPLE}$(printf '%.0s=' {1..60})${NC}"
}

# Pre-flight checks
pre_flight_checks() {
    log_header "🚀 Pre-flight Checks"
    
    # Check if Artillery is installed
    if ! command -v artillery &> /dev/null; then
        log_error "Artillery is not installed. Installing..."
        cd "$PROJECT_ROOT"
        npm install --save-dev artillery
    else
        log_success "Artillery is installed"
    fi
    
    # Check if Laravel app is running
    if ! curl -s --connect-timeout 5 "http://localhost:8000" > /dev/null; then
        log_warning "Laravel app is not responding on localhost:8000"
        log_info "Please start your Laravel application first"
        exit 1
    else
        log_success "Laravel app is running"
    fi
    
    # Check test data exists
    if [ ! -f "$SCRIPT_DIR/data/contract-samples.json" ]; then
        log_error "Test data file not found: $SCRIPT_DIR/data/contract-samples.json"
        exit 1
    fi
    log_success "Test data file found"
    
    # Create reports directory
    mkdir -p "$REPORTS_DIR"
    log_success "Reports directory ready: $REPORTS_DIR"
    
    # System resource check
    log_info "System Resources:"
    echo "  CPU Cores: $(nproc)"
    echo "  Memory: $(free -h | awk '/^Mem:/ {print $2}')"
    echo "  File descriptor limit: $(ulimit -n)"
}

# Run the load test
run_load_test() {
    log_header "🔥 Running 500 Concurrent Analysis Load Test"
    
    local config_file="$SCRIPT_DIR/concurrent-500.yml"
    local output_file="$REPORTS_DIR/${TEST_NAME}.json"
    local html_report="$REPORTS_DIR/${TEST_NAME}.html"
    
    log_info "Test Configuration: $config_file"
    log_info "Output File: $output_file"
    
    log_info "Starting load test - targeting 500 concurrent analyses..."
    log_warning "This test will run for approximately 15 minutes"
    
    cd "$SCRIPT_DIR"
    
    # Run Artillery with comprehensive reporting
    if artillery run "$config_file" --output "$output_file"; then
        log_success "Load test completed successfully!"
        
        # Generate HTML report
        if artillery report --output "$html_report" "$output_file"; then
            log_success "HTML report generated: $html_report"
        fi
        
        # Generate summary
        generate_summary "$output_file"
        
    else
        log_error "Load test failed!"
        exit 1
    fi
}

# Generate test summary
generate_summary() {
    local json_file="$1"
    local summary_file="$REPORTS_DIR/${TEST_NAME}_summary.md"
    
    log_header "📊 Generating Test Summary"
    
    cat > "$summary_file" << EOF
# AI Blockchain Analytics - Load Test Summary
## 500 Concurrent Analysis Load Test Results

**Test Run:** $TEST_NAME  
**Date:** $(date)  
**Target:** 500 concurrent smart contract analyses  

## Test Configuration
- **Ramp-up:** 240 seconds to reach 500 concurrent users
- **Sustained Load:** 600 seconds at 500 concurrent users  
- **Cool-down:** 60 seconds scale down
- **Total Test Time:** ~15 minutes

## Performance Thresholds
- Target Request Rate: >400 req/sec
- 95th Percentile Response Time: <8000ms  
- 99th Percentile Response Time: <15000ms
- Success Rate: >80%
- Error Rate: <10%

## Files Generated
- JSON Report: \`$json_file\`
- HTML Report: \`${json_file%.json}.html\`
- Summary: \`$summary_file\`

## Commands to View Results
\`\`\`bash
# View HTML report
open ${html_report}

# View JSON data
cat ${json_file}
\`\`\`
EOF

    log_success "Test summary generated: $summary_file"
}

# Main execution
main() {
    log_header "🎯 AI Blockchain Analytics - 500 Concurrent Load Test"
    
    echo "This test will:"
    echo "• Scale to 500 concurrent smart contract analyses"
    echo "• Run sustained load for 10 minutes"
    echo "• Generate comprehensive performance reports"
    echo
    
    read -p "Continue with load test? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log_info "Load test cancelled by user"
        exit 0
    fi
    
    pre_flight_checks
    run_load_test
    
    log_success "🎉 Load test completed successfully!"
    log_info "Check the reports directory for detailed results: $REPORTS_DIR"
}

# Execute main function
main "$@"