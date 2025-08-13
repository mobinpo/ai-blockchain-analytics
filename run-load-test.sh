#!/bin/bash

# AI Blockchain Analytics - Artillery Load Test Runner
# Targets 500 concurrent analyses with comprehensive monitoring

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_ROOT="/home/mobin/PhpstormProjects/ai_blockchain_analytics"
DOCKER_COMPOSE_FILE="$PROJECT_ROOT/docker-compose.yml"
ARTILLERY_CONFIG="$PROJECT_ROOT/artillery-load-test.yml"
RESULTS_DIR="$PROJECT_ROOT/load-test-results"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
TEST_RESULTS_FILE="$RESULTS_DIR/load-test-$TIMESTAMP.json"
TEST_LOG_FILE="$RESULTS_DIR/load-test-$TIMESTAMP.log"

# Create results directory
mkdir -p "$RESULTS_DIR"

echo -e "${BLUE}🚀 AI Blockchain Analytics Load Test - 500 Concurrent Analyses${NC}"
echo -e "${BLUE}================================================================${NC}"
echo -e "Start time: $(date)"
echo -e "Target: 500 concurrent analyses"
echo -e "Configuration: $ARTILLERY_CONFIG"
echo -e "Results will be saved to: $RESULTS_DIR"
echo ""

# Function to check if services are healthy
check_services() {
    echo -e "${YELLOW}🔍 Checking application services...${NC}"
    
    # Check if Docker containers are running
    if ! docker-compose -f "$DOCKER_COMPOSE_FILE" ps | grep -q "Up"; then
        echo -e "${RED}❌ Docker services are not running. Starting services...${NC}"
        docker-compose -f "$DOCKER_COMPOSE_FILE" up -d
        echo -e "${YELLOW}⏳ Waiting for services to be ready...${NC}"
        sleep 30
    fi
    
    # Check application health
    echo -e "${YELLOW}🏥 Checking application health...${NC}"
    if curl -s -f "http://localhost:8003/api/health" > /dev/null; then
        echo -e "${GREEN}✅ Application is healthy${NC}"
    else
        echo -e "${RED}❌ Application health check failed${NC}"
        echo -e "${YELLOW}📋 Application logs:${NC}"
        docker-compose -f "$DOCKER_COMPOSE_FILE" logs --tail=20 app
        exit 1
    fi
    
    # Check load test endpoints
    echo -e "${YELLOW}🔧 Checking load test endpoints...${NC}"
    if curl -s -f "http://localhost:8003/api/load-test/health" > /dev/null; then
        echo -e "${GREEN}✅ Load test endpoints are ready${NC}"
    else
        echo -e "${RED}❌ Load test endpoints not available${NC}"
        exit 1
    fi
}

# Function to monitor system resources during test
monitor_system() {
    echo -e "${YELLOW}📊 Starting system monitoring...${NC}"
    
    # Create monitoring log
    MONITOR_LOG="$RESULTS_DIR/system-monitor-$TIMESTAMP.log"
    
    # Background process to monitor Docker container stats
    {
        echo "timestamp,container,cpu_percent,memory_usage,memory_limit,memory_percent,network_io"
        while true; do
            docker stats --no-stream --format "table {{.Container}}\t{{.CPUPerc}}\t{{.MemUsage}}\t{{.MemPerc}}\t{{.NetIO}}" | \
            tail -n +2 | \
            while IFS=$'\t' read -r container cpu mem_usage mem_percent net_io; do
                echo "$(date -Iseconds),$container,$cpu,$mem_usage,$mem_percent,$net_io"
            done
            sleep 5
        done
    } > "$MONITOR_LOG" &
    
    MONITOR_PID=$!
    echo "System monitoring started (PID: $MONITOR_PID)"
    return $MONITOR_PID
}

# Function to run pre-test checks
pre_test_checks() {
    echo -e "${YELLOW}🧪 Running pre-test checks...${NC}"
    
    # Test a few endpoints manually to ensure they work
    echo "Testing analysis endpoint..."
    curl -s -X POST "http://localhost:8003/api/load-test/analysis" \
         -H "Content-Type: application/json" \
         -d '{
           "contract_address": "0x1234567890123456789012345678901234567890",
           "analysis_type": "security_audit",
           "priority": "medium"
         }' | jq '.' > /dev/null
    
    echo "Testing sentiment endpoint..."
    curl -s -X POST "http://localhost:8003/api/load-test/sentiment" \
         -H "Content-Type: application/json" \
         -d '{
           "texts": ["Bitcoin is showing strong performance"],
           "platforms": ["twitter"]
         }' | jq '.' > /dev/null
    
    echo -e "${GREEN}✅ Pre-test checks passed${NC}"
}

# Function to run the actual load test
run_load_test() {
    echo -e "${YELLOW}🎯 Starting Artillery load test...${NC}"
    echo -e "${BLUE}Target: 500 concurrent analyses${NC}"
    echo -e "${BLUE}Duration: ~8 minutes (warmup + ramp-up + peak + ramp-down)${NC}"
    echo ""
    
    # Run Artillery with output capture
    cd "$PROJECT_ROOT"
    
    if command -v npx &> /dev/null; then
        npx artillery run \
            --output "$TEST_RESULTS_FILE" \
            "$ARTILLERY_CONFIG" | tee "$TEST_LOG_FILE"
    else
        echo -e "${RED}❌ npx not found. Installing artillery globally...${NC}"
        npm install -g artillery
        artillery run \
            --output "$TEST_RESULTS_FILE" \
            "$ARTILLERY_CONFIG" | tee "$TEST_LOG_FILE"
    fi
}

# Function to generate test report
generate_report() {
    echo -e "${YELLOW}📈 Generating test report...${NC}"
    
    if command -v npx &> /dev/null && [ -f "$TEST_RESULTS_FILE" ]; then
        REPORT_FILE="$RESULTS_DIR/report-$TIMESTAMP.html"
        npx artillery report "$TEST_RESULTS_FILE" --output "$REPORT_FILE"
        echo -e "${GREEN}✅ HTML report generated: $REPORT_FILE${NC}"
    fi
    
    # Create summary report
    SUMMARY_FILE="$RESULTS_DIR/summary-$TIMESTAMP.txt"
    {
        echo "=== AI Blockchain Analytics Load Test Summary ==="
        echo "Test Date: $(date)"
        echo "Target: 500 concurrent analyses"
        echo "Duration: ~8 minutes"
        echo ""
        echo "=== Test Configuration ==="
        echo "- Warmup: 30s @ 10 RPS"
        echo "- Ramp-up: 120s @ 10-100 RPS"
        echo "- Peak load: 300s @ 100 RPS (500 concurrent)"
        echo "- Ramp-down: 60s @ 100-10 RPS"
        echo ""
        echo "=== Results Files ==="
        echo "- Raw results: $TEST_RESULTS_FILE"
        echo "- Test log: $TEST_LOG_FILE"
        echo "- System monitor: $RESULTS_DIR/system-monitor-$TIMESTAMP.log"
        if [ -f "$REPORT_FILE" ]; then
            echo "- HTML report: $REPORT_FILE"
        fi
        echo ""
        echo "=== Quick Stats ==="
        if [ -f "$TEST_LOG_FILE" ]; then
            echo "Last 20 lines of test output:"
            tail -20 "$TEST_LOG_FILE"
        fi
    } > "$SUMMARY_FILE"
    
    echo -e "${GREEN}✅ Summary report generated: $SUMMARY_FILE${NC}"
}

# Function to cleanup
cleanup() {
    echo -e "${YELLOW}🧹 Cleaning up...${NC}"
    
    if [ ! -z "$MONITOR_PID" ]; then
        kill $MONITOR_PID 2>/dev/null || true
        echo "System monitoring stopped"
    fi
    
    # Show final Docker container status
    echo -e "${YELLOW}📊 Final container status:${NC}"
    docker-compose -f "$DOCKER_COMPOSE_FILE" ps
}

# Trap to ensure cleanup on exit
trap cleanup EXIT

# Main execution flow
main() {
    echo -e "${BLUE}Starting load test execution...${NC}"
    
    # Step 1: Check services
    check_services
    
    # Step 2: Run pre-test checks
    pre_test_checks
    
    # Step 3: Start monitoring
    monitor_system
    MONITOR_PID=$?
    
    # Step 4: Run the load test
    run_load_test
    
    # Step 5: Generate reports
    generate_report
    
    echo ""
    echo -e "${GREEN}🎉 Load test completed successfully!${NC}"
    echo -e "${GREEN}Results saved to: $RESULTS_DIR${NC}"
    echo -e "${BLUE}================================================================${NC}"
}

# Check if configuration file exists
if [ ! -f "$ARTILLERY_CONFIG" ]; then
    echo -e "${RED}❌ Artillery configuration file not found: $ARTILLERY_CONFIG${NC}"
    exit 1
fi

# Check if Docker Compose file exists
if [ ! -f "$DOCKER_COMPOSE_FILE" ]; then
    echo -e "${RED}❌ Docker Compose file not found: $DOCKER_COMPOSE_FILE${NC}"
    exit 1
fi

# Run main function
main
