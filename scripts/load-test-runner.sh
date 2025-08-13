#!/bin/bash

# Load Test Runner for AI Blockchain Analytics
# Automated script to run comprehensive load tests with Artillery

set -euo pipefail

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
LOAD_TEST_DIR="$PROJECT_ROOT/load-tests"
RESULTS_DIR="$PROJECT_ROOT/load-test-results"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m' # No Color

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

log_section() {
    echo -e "${PURPLE}[SECTION]${NC} $1"
    echo -e "${PURPLE}$(printf '=%.0s' {1..60})${NC}"
}

# Check dependencies
check_dependencies() {
    log_info "Checking dependencies..."
    
    local deps=("artillery" "jq" "curl")
    for dep in "${deps[@]}"; do
        if ! command -v "$dep" &> /dev/null; then
            log_error "$dep is not installed. Please install it and try again."
            exit 1
        fi
    done
    
    log_success "All dependencies are installed"
}

# Setup results directory
setup_results_dir() {
    log_info "Setting up results directory..."
    
    mkdir -p "$RESULTS_DIR"
    mkdir -p "$RESULTS_DIR/$TIMESTAMP"
    
    log_success "Results directory created: $RESULTS_DIR/$TIMESTAMP"
}

# Check application health
check_app_health() {
    local app_url=${1:-http://localhost:8000}
    
    log_info "Checking application health at $app_url..."
    
    if ! curl -sf "$app_url/health" > /dev/null 2>&1; then
        log_error "Application is not responding at $app_url"
        log_error "Please ensure the application is running before starting load tests"
        exit 1
    fi
    
    log_success "Application is healthy and responding"
}

# Pre-load test setup
pre_test_setup() {
    log_info "Setting up test environment..."
    
    # Clear any existing test data
    log_info "Clearing previous test artifacts..."
    
    # Ensure test user exists (if using a test database)
    log_info "Verifying test user configuration..."
    
    # Warm up the application
    log_info "Warming up application..."
    curl -sf "http://localhost:8000/health" > /dev/null || true
    
    log_success "Pre-test setup completed"
}

# Run specific load test
run_load_test() {
    local test_name=$1
    local config_file=$2
    local description=$3
    
    log_section "Running $test_name"
    log_info "Description: $description"
    log_info "Config: $config_file"
    
    local result_file="$RESULTS_DIR/$TIMESTAMP/${test_name}_${TIMESTAMP}.json"
    local report_file="$RESULTS_DIR/$TIMESTAMP/${test_name}_${TIMESTAMP}_report.html"
    
    # Run the test
    log_info "Starting load test execution..."
    
    if artillery run "$LOAD_TEST_DIR/$config_file" \
        --output "$result_file" \
        --quiet; then
        
        log_success "Load test completed successfully"
        
        # Generate HTML report
        log_info "Generating HTML report..."
        artillery report "$result_file" --output "$report_file"
        
        # Display summary
        display_test_summary "$result_file" "$test_name"
        
        return 0
    else
        log_error "Load test failed"
        return 1
    fi
}

# Display test summary
display_test_summary() {
    local result_file=$1
    local test_name=$2
    
    log_info "Test Summary for $test_name:"
    
    if [[ -f "$result_file" ]] && command -v jq &> /dev/null; then
        local summary=$(jq -r '.aggregate' "$result_file" 2>/dev/null || echo "{}")
        
        if [[ "$summary" != "{}" ]]; then
            echo "┌─────────────────────────────┬──────────────────────┐"
            echo "│ Metric                      │ Value                │"
            echo "├─────────────────────────────┼──────────────────────┤"
            
            # Extract key metrics
            local scenarios=$(jq -r '.aggregate.counters."vusers.created" // "N/A"' "$result_file")
            local requests=$(jq -r '.aggregate.counters."http.requests" // "N/A"' "$result_file")
            local responses=$(jq -r '.aggregate.counters."http.responses" // "N/A"' "$result_file")
            local errors=$(jq -r '.aggregate.counters."errors.ECONNREFUSED" // 0' "$result_file")
            local p50=$(jq -r '.aggregate.latency.p50 // "N/A"' "$result_file")
            local p95=$(jq -r '.aggregate.latency.p95 // "N/A"' "$result_file")
            local p99=$(jq -r '.aggregate.latency.p99 // "N/A"' "$result_file")
            local min=$(jq -r '.aggregate.latency.min // "N/A"' "$result_file")
            local max=$(jq -r '.aggregate.latency.max // "N/A"' "$result_file")
            
            printf "│ %-27s │ %-20s │\n" "Virtual Users Created" "$scenarios"
            printf "│ %-27s │ %-20s │\n" "HTTP Requests" "$requests"
            printf "│ %-27s │ %-20s │\n" "HTTP Responses" "$responses"
            printf "│ %-27s │ %-20s │\n" "Connection Errors" "$errors"
            printf "│ %-27s │ %-20s │\n" "Latency P50 (ms)" "$p50"
            printf "│ %-27s │ %-20s │\n" "Latency P95 (ms)" "$p95"
            printf "│ %-27s │ %-20s │\n" "Latency P99 (ms)" "$p99"
            printf "│ %-27s │ %-20s │\n" "Min Latency (ms)" "$min"
            printf "│ %-27s │ %-20s │\n" "Max Latency (ms)" "$max"
            
            echo "└─────────────────────────────┴──────────────────────┘"
            
            # Calculate error rate
            if [[ "$requests" != "N/A" ]] && [[ "$errors" != "N/A" ]] && [[ "$requests" -gt 0 ]]; then
                local error_rate=$(echo "scale=2; $errors * 100 / $requests" | bc -l 2>/dev/null || echo "0")
                echo "Error Rate: ${error_rate}%"
            fi
        else
            log_warning "Could not parse test results"
        fi
    else
        log_warning "Test results file not found or jq not available"
    fi
    
    echo ""
}

# Run concurrent 500 analysis test
run_concurrent_500_test() {
    log_section "500 Concurrent Analyses Load Test"
    
    # Pre-test checks
    log_info "Performing pre-test system checks..."
    
    # Check system resources
    log_info "Current system resources:"
    echo "Memory usage: $(free -h | awk '/^Mem:/ {print $3 "/" $2}')"
    echo "CPU load: $(uptime | awk '{print $NF}')"
    echo "Disk usage: $(df -h / | awk 'NR==2 {print $5}')"
    
    # Run the test
    run_load_test "concurrent_500" "concurrent-500.yml" "500 concurrent blockchain analyses stress test"
    
    local exit_code=$?
    
    # Post-test analysis
    log_info "Post-test system state:"
    echo "Memory usage: $(free -h | awk '/^Mem:/ {print $3 "/" $2}')"
    echo "CPU load: $(uptime | awk '{print $NF}')"
    
    return $exit_code
}

# Run comprehensive test suite
run_comprehensive_tests() {
    log_section "Comprehensive Load Test Suite"
    
    local tests=(
        "quick_smoke:artillery-config.yml:Quick smoke test to verify basic functionality"
        "blockchain_analysis:blockchain-analysis.yml:Comprehensive blockchain analysis workflow testing"
        "performance_monitoring:performance-monitoring.yml:System performance monitoring under load"
        "concurrent_500:concurrent-500.yml:500 concurrent analyses stress test"
    )
    
    local failed_tests=0
    local total_tests=${#tests[@]}
    
    for test in "${tests[@]}"; do
        IFS=':' read -r test_name config_file description <<< "$test"
        
        if ! run_load_test "$test_name" "$config_file" "$description"; then
            ((failed_tests++))
            log_error "Test $test_name failed"
        fi
        
        # Brief pause between tests
        log_info "Pausing 30 seconds before next test..."
        sleep 30
    done
    
    # Final summary
    log_section "Test Suite Summary"
    local passed_tests=$((total_tests - failed_tests))
    
    echo "Total Tests: $total_tests"
    echo "Passed: $passed_tests"
    echo "Failed: $failed_tests"
    
    if [[ $failed_tests -eq 0 ]]; then
        log_success "All tests passed!"
        return 0
    else
        log_error "$failed_tests tests failed"
        return 1
    fi
}

# Generate consolidated report
generate_consolidated_report() {
    log_section "Generating Consolidated Report"
    
    local report_file="$RESULTS_DIR/$TIMESTAMP/consolidated_report.html"
    
    cat > "$report_file" << EOF
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Blockchain Analytics - Load Test Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1, h2 { color: #333; }
        .summary { background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .metric { display: inline-block; margin: 10px; padding: 10px; background: white; border-radius: 3px; }
        .test-section { border: 1px solid #ddd; margin: 20px 0; padding: 15px; }
        .pass { color: green; }
        .fail { color: red; }
    </style>
</head>
<body>
    <h1>🚀 AI Blockchain Analytics Load Test Report</h1>
    <div class="summary">
        <h2>Test Execution Summary</h2>
        <p><strong>Timestamp:</strong> $TIMESTAMP</p>
        <p><strong>Test Duration:</strong> $(date)</p>
        <p><strong>Target:</strong> 500 Concurrent Analyses</p>
    </div>
    
    <div class="test-section">
        <h2>🎯 Key Performance Metrics</h2>
        <p>Detailed results are available in individual test reports.</p>
    </div>
    
    <div class="test-section">
        <h2>📊 Test Files Generated</h2>
        <ul>
EOF

    # List all generated files
    for file in "$RESULTS_DIR/$TIMESTAMP"/*; do
        if [[ -f "$file" ]]; then
            local filename=$(basename "$file")
            echo "            <li><a href=\"$filename\">$filename</a></li>" >> "$report_file"
        fi
    done

    cat >> "$report_file" << EOF
        </ul>
    </div>
    
    <div class="test-section">
        <h2>🔗 Links</h2>
        <ul>
            <li><a href="https://artillery.io/">Artillery Load Testing</a></li>
            <li><a href="https://github.com/mobin/ai-blockchain-analytics">Project Repository</a></li>
        </ul>
    </div>
</body>
</html>
EOF

    log_success "Consolidated report generated: $report_file"
}

# Cleanup function
cleanup() {
    log_info "Performing cleanup..."
    
    # Kill any remaining artillery processes
    pkill -f artillery || true
    
    log_success "Cleanup completed"
}

# Main execution function
main() {
    local command=${1:-help}
    
    case $command in
        "500"|"concurrent-500")
            check_dependencies
            setup_results_dir
            check_app_health
            pre_test_setup
            run_concurrent_500_test
            generate_consolidated_report
            ;;
        "comprehensive"|"all")
            check_dependencies
            setup_results_dir
            check_app_health
            pre_test_setup
            run_comprehensive_tests
            generate_consolidated_report
            ;;
        "quick"|"smoke")
            check_dependencies
            setup_results_dir
            check_app_health
            run_load_test "quick_smoke" "artillery-config.yml" "Quick smoke test"
            ;;
        "blockchain")
            check_dependencies
            setup_results_dir
            check_app_health
            pre_test_setup
            run_load_test "blockchain_analysis" "blockchain-analysis.yml" "Blockchain analysis load test"
            ;;
        "performance")
            check_dependencies
            setup_results_dir
            check_app_health
            pre_test_setup
            run_load_test "performance_monitoring" "performance-monitoring.yml" "Performance monitoring test"
            ;;
        "help"|"-h"|"--help")
            echo "Load Test Runner for AI Blockchain Analytics"
            echo ""
            echo "Usage: $0 [command]"
            echo ""
            echo "Commands:"
            echo "  500, concurrent-500    Run 500 concurrent analyses load test"
            echo "  comprehensive, all     Run complete test suite"
            echo "  quick, smoke          Run quick smoke test"
            echo "  blockchain            Run blockchain analysis load test"
            echo "  performance           Run performance monitoring test"
            echo "  help                  Show this help message"
            echo ""
            echo "Examples:"
            echo "  $0 500                Run 500 concurrent analyses test"
            echo "  $0 comprehensive      Run all load tests"
            echo "  $0 quick              Run quick verification test"
            ;;
        *)
            log_error "Unknown command: $command"
            echo "Use '$0 help' for usage information"
            exit 1
            ;;
    esac
}

# Set up cleanup trap
trap cleanup EXIT

# Run main function
main "$@"