#!/bin/bash

# =============================================================================
# 500 Concurrent Analysis Load Test Monitor
# =============================================================================
# Monitors system performance during Artillery load testing

set -euo pipefail

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
PURPLE='\033[0;35m'
NC='\033[0m'

# Configuration
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
RESULTS_DIR="./load-test-results-${TIMESTAMP}"
MONITOR_INTERVAL=5
LOG_FILE="${RESULTS_DIR}/system_monitor.log"
METRICS_FILE="${RESULTS_DIR}/performance_metrics.csv"

# Create results directory
mkdir -p "${RESULTS_DIR}"

echo -e "${PURPLE}🎯 AI Blockchain Analytics - 500 Concurrent Load Test Monitor${NC}"
echo -e "${PURPLE}================================================================${NC}"
echo
echo -e "${BLUE}📊 Results will be saved to: ${RESULTS_DIR}${NC}"
echo -e "${BLUE}📝 Monitor interval: ${MONITOR_INTERVAL} seconds${NC}"
echo

# Initialize CSV metrics file
echo "timestamp,cpu_usage,memory_usage,load_avg,disk_io_read,disk_io_write,network_rx,network_tx,docker_app_cpu,docker_app_memory,postgres_connections,redis_memory" > "${METRICS_FILE}"

# Function to get Docker container stats
get_docker_stats() {
    local container_name="$1"
    docker stats --no-stream --format "table {{.CPUPerc}}\t{{.MemUsage}}" "${container_name}" 2>/dev/null | tail -n 1 || echo "0.00%\t0B / 0B"
}

# Function to get PostgreSQL connection count
get_postgres_connections() {
    docker compose exec -T postgres psql -U ai_blockchain_user -d ai_blockchain_analytics -c "SELECT count(*) FROM pg_stat_activity;" 2>/dev/null | grep -E "^\s*[0-9]+\s*$" | tr -d ' ' || echo "0"
}

# Function to get Redis memory usage
get_redis_memory() {
    docker compose exec -T redis redis-cli info memory 2>/dev/null | grep "used_memory_human:" | cut -d: -f2 | tr -d '\r' || echo "0B"
}

# Function to collect system metrics
collect_metrics() {
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    local cpu_usage=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d'%' -f1)
    local memory_usage=$(free | grep Mem | awk '{printf "%.2f", $3/$2 * 100.0}')
    local load_avg=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | tr -d ',')
    
    # Disk I/O
    local disk_stats=$(iostat -d 1 1 2>/dev/null | tail -n +4 | head -n 1 | awk '{print $3","$4}' || echo "0,0")
    
    # Network stats
    local network_stats=$(cat /proc/net/dev | grep -E "(eth0|ens|enp)" | head -n 1 | awk '{print $2","$10}' || echo "0,0")
    
    # Docker container stats
    local app_stats=$(get_docker_stats "ai_blockchain_app")
    local app_cpu=$(echo "${app_stats}" | awk '{print $1}' | tr -d '%')
    local app_memory=$(echo "${app_stats}" | awk '{print $2}' | cut -d'/' -f1)
    
    # Database metrics
    local postgres_connections=$(get_postgres_connections)
    local redis_memory=$(get_redis_memory)
    
    # Write to CSV
    echo "${timestamp},${cpu_usage},${memory_usage},${load_avg},${disk_stats},${network_stats},${app_cpu},${app_memory},${postgres_connections},${redis_memory}" >> "${METRICS_FILE}"
    
    # Log to console and file
    local log_message="[${timestamp}] CPU: ${cpu_usage}% | Memory: ${memory_usage}% | Load: ${load_avg} | App CPU: ${app_cpu}% | PG Connections: ${postgres_connections}"
    echo -e "${GREEN}${log_message}${NC}"
    echo "${log_message}" >> "${LOG_FILE}"
}

# Function to run Artillery test
run_artillery_test() {
    echo -e "${BLUE}🚀 Starting Artillery 500 concurrent analysis test...${NC}"
    
    # Start monitoring in background
    (
        while true; do
            collect_metrics
            sleep ${MONITOR_INTERVAL}
        done
    ) &
    MONITOR_PID=$!
    
    # Run Artillery test
    echo -e "${YELLOW}Running Artillery test with optimized configuration...${NC}"
    artillery run --output "${RESULTS_DIR}/artillery_results.json" artillery-optimized-500-analyses.yml 2>&1 | tee "${RESULTS_DIR}/artillery_output.log"
    
    # Stop monitoring
    kill $MONITOR_PID 2>/dev/null || true
    
    echo -e "${GREEN}✅ Artillery test completed!${NC}"
}

# Function to generate summary report
generate_summary() {
    echo -e "${BLUE}📊 Generating performance summary...${NC}"
    
    cat > "${RESULTS_DIR}/test_summary.md" << EOF
# 500 Concurrent Analysis Load Test Results

## Test Configuration
- **Test Date**: $(date)
- **Target Load**: 500 concurrent analyses
- **Test Duration**: ~15 minutes
- **Artillery Configuration**: artillery-optimized-500-analyses.yml

## System Information
- **CPU**: $(nproc) cores
- **Memory**: $(free -h | grep Mem | awk '{print $2}')
- **Docker Containers**: $(docker ps --format "table {{.Names}}" | tail -n +2 | wc -l) running

## Performance Metrics Summary
EOF

    # Add performance statistics if metrics file exists
    if [[ -f "${METRICS_FILE}" ]]; then
        echo "### CPU Usage" >> "${RESULTS_DIR}/test_summary.md"
        echo "- Average: $(awk -F',' 'NR>1 {sum+=$2; count++} END {if(count>0) print sum/count"%"; else print "N/A"}' "${METRICS_FILE}")" >> "${RESULTS_DIR}/test_summary.md"
        echo "- Peak: $(awk -F',' 'NR>1 {if($2>max) max=$2} END {print max"%"}' "${METRICS_FILE}")" >> "${RESULTS_DIR}/test_summary.md"
        
        echo "" >> "${RESULTS_DIR}/test_summary.md"
        echo "### Memory Usage" >> "${RESULTS_DIR}/test_summary.md"
        echo "- Average: $(awk -F',' 'NR>1 {sum+=$3; count++} END {if(count>0) print sum/count"%"; else print "N/A"}' "${METRICS_FILE}")" >> "${RESULTS_DIR}/test_summary.md"
        echo "- Peak: $(awk -F',' 'NR>1 {if($3>max) max=$3} END {print max"%"}' "${METRICS_FILE}")" >> "${RESULTS_DIR}/test_summary.md"
        
        echo "" >> "${RESULTS_DIR}/test_summary.md"
        echo "### Database Connections" >> "${RESULTS_DIR}/test_summary.md"
        echo "- Average: $(awk -F',' 'NR>1 {sum+=$10; count++} END {if(count>0) print sum/count; else print "N/A"}' "${METRICS_FILE}")" >> "${RESULTS_DIR}/test_summary.md"
        echo "- Peak: $(awk -F',' 'NR>1 {if($10>max) max=$10} END {print max}' "${METRICS_FILE}")" >> "${RESULTS_DIR}/test_summary.md"
    fi
    
    echo "" >> "${RESULTS_DIR}/test_summary.md"
    echo "## Files Generated" >> "${RESULTS_DIR}/test_summary.md"
    echo "- \`artillery_results.json\` - Detailed Artillery test results" >> "${RESULTS_DIR}/test_summary.md"
    echo "- \`artillery_output.log\` - Artillery console output" >> "${RESULTS_DIR}/test_summary.md"
    echo "- \`performance_metrics.csv\` - System performance data" >> "${RESULTS_DIR}/test_summary.md"
    echo "- \`system_monitor.log\` - System monitoring log" >> "${RESULTS_DIR}/test_summary.md"
    echo "- \`test_summary.md\` - This summary report" >> "${RESULTS_DIR}/test_summary.md"
    
    echo -e "${GREEN}📄 Summary report generated: ${RESULTS_DIR}/test_summary.md${NC}"
}

# Function to cleanup and prepare
prepare_test() {
    echo -e "${BLUE}🔧 Preparing system for load test...${NC}"
    
    # Clear Laravel caches
    echo "Clearing Laravel caches..."
    docker compose exec app php artisan cache:clear >/dev/null 2>&1 || true
    docker compose exec app php artisan config:clear >/dev/null 2>&1 || true
    docker compose exec app php artisan route:clear >/dev/null 2>&1 || true
    
    # Warm up the application
    echo "Warming up application..."
    curl -s "http://localhost:8003/api/cache/stats" >/dev/null 2>&1 || true
    curl -s "http://localhost:8003/api/sentiment-charts/coins" >/dev/null 2>&1 || true
    
    echo -e "${GREEN}✅ System prepared for testing${NC}"
}

# Main execution
main() {
    echo -e "${YELLOW}⚠️  This will run a high-load test on your system${NC}"
    read -p "Continue with 500 concurrent analysis test? (y/N): " -n 1 -r
    echo
    
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo -e "${BLUE}Test cancelled${NC}"
        exit 0
    fi
    
    prepare_test
    run_artillery_test
    generate_summary
    
    echo
    echo -e "${GREEN}🎉 Load test completed successfully!${NC}"
    echo -e "${BLUE}📊 Results saved to: ${RESULTS_DIR}${NC}"
    echo
    echo -e "${PURPLE}📋 Quick Summary:${NC}"
    echo "  - View detailed results: cat ${RESULTS_DIR}/test_summary.md"
    echo "  - Analyze performance data: cat ${RESULTS_DIR}/performance_metrics.csv"
    echo "  - Check Artillery output: cat ${RESULTS_DIR}/artillery_output.log"
    echo
}

# Check dependencies
if ! command -v artillery &> /dev/null; then
    echo -e "${RED}❌ Artillery not found. Please install: npm install -g artillery${NC}"
    exit 1
fi

if ! command -v iostat &> /dev/null; then
    echo -e "${YELLOW}⚠️  iostat not found. Disk I/O metrics will be limited${NC}"
fi

# Run main function
main "$@"
