#!/bin/bash

# AI Blockchain Analytics - Load Test Execution Script
# Executes Artillery load tests with comprehensive monitoring

set -e  # Exit on any error

echo "🚀 AI Blockchain Analytics - Load Test Runner"
echo "=============================================="
echo ""

# Configuration
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
RESULTS_DIR="load-tests/results"
LOGS_DIR="load-tests/logs"

# Create directories
mkdir -p "$RESULTS_DIR"
mkdir -p "$LOGS_DIR"

# Test configurations
declare -A TESTS=(
    ["standard"]="artillery-config.yml"
    ["stress"]="stress-test-500-concurrent.yml"
)

# Function to check prerequisites
check_prerequisites() {
    echo "🔍 Checking prerequisites..."
    
    # Check if Artillery is installed
    if ! command -v artillery &> /dev/null; then
        echo "❌ Artillery is not installed. Please install with: npm install -g artillery"
        exit 1
    fi
    
    # Check if application is running
    if ! curl -s http://localhost:8003/api/verification-badge/levels > /dev/null; then
        echo "❌ Application is not running on localhost:8003"
        echo "   Please start the application with: docker compose up -d"
        exit 1
    fi
    
    # Check if processor file exists
    if [ ! -f "load-tests/artillery-processor.js" ]; then
        echo "❌ Artillery processor file not found"
        exit 1
    fi
    
    echo "✅ All prerequisites met"
    echo ""
}

# Function to run system health check
health_check() {
    echo "🏥 Running system health check..."
    
    # Check API endpoints
    local endpoints=(
        "/api/verification-badge/levels"
        "/api/solidity-cleaner/options"
        "/api/sentiment/timeline?token=ethereum&timeframe=7d"
    )
    
    for endpoint in "${endpoints[@]}"; do
        echo -n "   Testing $endpoint: "
        if curl -s "http://localhost:8003$endpoint" > /dev/null; then
            echo "✅ OK"
        else
            echo "❌ FAILED"
            return 1
        fi
    done
    
    # Check Docker containers
    echo -n "   Docker containers: "
    if docker compose ps | grep -q "Up"; then
        echo "✅ Running"
    else
        echo "❌ Not all containers are up"
        return 1
    fi
    
    echo "✅ System health check passed"
    echo ""
}

# Function to start performance monitoring
start_monitoring() {
    echo "📊 Starting performance monitoring..."
    
    # Start performance monitor in background
    ./load-tests/monitor-performance.sh > "$LOGS_DIR/performance_monitor_${TIMESTAMP}.log" 2>&1 &
    MONITOR_PID=$!
    
    # Give monitor time to start
    sleep 5
    
    echo "✅ Performance monitoring started (PID: $MONITOR_PID)"
    echo ""
}

# Function to stop performance monitoring
stop_monitoring() {
    echo "🛑 Stopping performance monitoring..."
    
    if [ ! -z "$MONITOR_PID" ]; then
        kill $MONITOR_PID 2>/dev/null || true
        wait $MONITOR_PID 2>/dev/null || true
    fi
    
    echo "✅ Performance monitoring stopped"
    echo ""
}

# Function to run a specific test
run_test() {
    local test_name="$1"
    local config_file="$2"
    local output_file="$RESULTS_DIR/${test_name}_${TIMESTAMP}"
    
    echo "🎯 Running $test_name load test..."
    echo "   Config: load-tests/$config_file"
    echo "   Output: $output_file"
    echo ""
    
    # Change to load-tests directory
    cd load-tests
    
    # Run Artillery test
    artillery run "$config_file" \
        --output "${output_file}.json" \
        --config '{"http":{"timeout":30}}' \
        2>&1 | tee "${output_file}.log"
    
    # Generate HTML report
    if [ -f "${output_file}.json" ]; then
        artillery report "${output_file}.json" \
            --output "${output_file}.html"
        echo "📊 HTML report generated: ${output_file}.html"
    fi
    
    # Return to root directory
    cd ..
    
    echo "✅ $test_name test completed"
    echo ""
}

# Function to analyze results
analyze_results() {
    echo "📈 Analyzing test results..."
    
    local latest_result=$(ls -t "$RESULTS_DIR"/*.json 2>/dev/null | head -1)
    
    if [ -z "$latest_result" ]; then
        echo "❌ No test results found"
        return 1
    fi
    
    echo "📊 Latest result: $latest_result"
    echo ""
    
    # Extract key metrics using jq
    if command -v jq &> /dev/null; then
        echo "🔍 Key Metrics:"
        echo "   Scenarios: $(jq -r '.aggregate.scenariosCompleted' "$latest_result" 2>/dev/null || echo "N/A")"
        echo "   Requests: $(jq -r '.aggregate.requestsCompleted' "$latest_result" 2>/dev/null || echo "N/A")"
        echo "   Errors: $(jq -r '.aggregate.errors' "$latest_result" 2>/dev/null || echo "N/A")"
        echo "   Min Response: $(jq -r '.aggregate.latency.min' "$latest_result" 2>/dev/null || echo "N/A")ms"
        echo "   Max Response: $(jq -r '.aggregate.latency.max' "$latest_result" 2>/dev/null || echo "N/A")ms"
        echo "   Median Response: $(jq -r '.aggregate.latency.median' "$latest_result" 2>/dev/null || echo "N/A")ms"
        echo "   95th Percentile: $(jq -r '.aggregate.latency.p95' "$latest_result" 2>/dev/null || echo "N/A")ms"
        echo "   99th Percentile: $(jq -r '.aggregate.latency.p99' "$latest_result" 2>/dev/null || echo "N/A")ms"
    else
        echo "💡 Install 'jq' for detailed metrics analysis"
        echo "   Result file: $latest_result"
    fi
    
    echo ""
}

# Function to display help
show_help() {
    echo "AI Blockchain Analytics Load Test Runner"
    echo ""
    echo "Usage: $0 [OPTIONS] [TEST_TYPE]"
    echo ""
    echo "TEST_TYPE:"
    echo "  standard  - Standard load test (gradual ramp-up)"
    echo "  stress    - Stress test with 500 concurrent users"
    echo "  all       - Run all tests sequentially"
    echo ""
    echo "OPTIONS:"
    echo "  --no-monitor    Skip performance monitoring"
    echo "  --quick         Run quick test (shorter duration)"
    echo "  --help          Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 stress                    # Run stress test with monitoring"
    echo "  $0 standard --no-monitor     # Run standard test without monitoring"
    echo "  $0 all                       # Run all tests"
    echo ""
}

# Function to cleanup on exit
cleanup() {
    echo ""
    echo "🧹 Cleaning up..."
    stop_monitoring
    echo "✅ Cleanup completed"
    exit 0
}

# Set up signal handlers
trap cleanup SIGINT SIGTERM

# Parse command line arguments
ENABLE_MONITORING=true
TEST_TYPE=""
QUICK_MODE=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --no-monitor)
            ENABLE_MONITORING=false
            shift
            ;;
        --quick)
            QUICK_MODE=true
            shift
            ;;
        --help)
            show_help
            exit 0
            ;;
        standard|stress|all)
            TEST_TYPE="$1"
            shift
            ;;
        *)
            echo "❌ Unknown option: $1"
            show_help
            exit 1
            ;;
    esac
done

# Default to stress test if no type specified
if [ -z "$TEST_TYPE" ]; then
    TEST_TYPE="stress"
fi

# Main execution
echo "🎯 Test Configuration:"
echo "   Type: $TEST_TYPE"
echo "   Monitoring: $ENABLE_MONITORING"
echo "   Quick Mode: $QUICK_MODE"
echo "   Timestamp: $TIMESTAMP"
echo ""

# Check prerequisites
check_prerequisites

# Run health check
health_check

# Start monitoring if enabled
if [ "$ENABLE_MONITORING" = true ]; then
    start_monitoring
fi

# Execute tests
case $TEST_TYPE in
    "standard")
        run_test "standard" "${TESTS[standard]}"
        ;;
    "stress")
        run_test "stress" "${TESTS[stress]}"
        ;;
    "all")
        run_test "standard" "${TESTS[standard]}"
        sleep 30  # Brief pause between tests
        run_test "stress" "${TESTS[stress]}"
        ;;
    *)
        echo "❌ Invalid test type: $TEST_TYPE"
        show_help
        exit 1
        ;;
esac

# Stop monitoring
if [ "$ENABLE_MONITORING" = true ]; then
    stop_monitoring
fi

# Analyze results
analyze_results

# Final summary
echo "🎉 Load testing completed successfully!"
echo ""
echo "📁 Results saved to:"
echo "   Directory: $RESULTS_DIR"
echo "   Logs: $LOGS_DIR"
echo ""
echo "💡 Next steps:"
echo "   1. Review HTML reports in $RESULTS_DIR"
echo "   2. Analyze performance logs in $LOGS_DIR"
echo "   3. Check application logs: docker compose logs app"
echo "   4. Monitor system metrics for bottlenecks"
echo ""