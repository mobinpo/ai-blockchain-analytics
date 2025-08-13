#!/bin/bash

# =============================================================================
# AI Blockchain Analytics - Performance Monitor
# Real-time monitoring during load tests
# =============================================================================

TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
MONITOR_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/reports"
MONITOR_FILE="$MONITOR_DIR/performance_monitor_$TIMESTAMP.log"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

log() {
    echo "$(date '+%H:%M:%S') - $1" | tee -a "$MONITOR_FILE"
}

monitor_loop() {
    echo "=== Performance Monitor Started at $(date) ===" | tee -a "$MONITOR_FILE"
    
    while true; do
        clear
        echo -e "${GREEN}🔍 AI Blockchain Analytics - Performance Monitor${NC}"
        echo "=================================================================="
        echo "Time: $(date)"
        echo "Log File: $MONITOR_FILE"
        echo
        
        # CPU Usage
        echo -e "${YELLOW}CPU Usage:${NC}"
        cpu_usage=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | sed 's/%us,//')
        echo "  Usage: $cpu_usage%"
        
        # Memory Usage  
        echo -e "${YELLOW}Memory Usage:${NC}"
        free -h | grep -E "(Mem:|Swap:)" | while read line; do
            echo "  $line"
        done
        
        # Load Average
        echo -e "${YELLOW}Load Average:${NC}"
        uptime | awk -F'load average:' '{print "  " $2}'
        
        # Network Connections
        echo -e "${YELLOW}Network Connections:${NC}"
        connections=$(ss -tuln | wc -l)
        echo "  Total: $connections"
        
        # Laravel App Status
        echo -e "${YELLOW}Application Status:${NC}"
        if curl -s --connect-timeout 2 "http://localhost:8000" > /dev/null; then
            echo -e "  Laravel: ${GREEN}✓ Running${NC}"
        else
            echo -e "  Laravel: ${RED}✗ Not Responding${NC}"
        fi
        
        # Disk Usage
        echo -e "${YELLOW}Disk Usage:${NC}"
        df -h / | tail -1 | awk '{print "  Root: " $3 " used, " $4 " available (" $5 " full)"}'
        
        # Log metrics
        {
            echo "=== $(date) ==="
            echo "CPU: $cpu_usage%"
            echo "Memory: $(free -m | awk 'NR==2{printf "%.1f%%", $3*100/$2 }')"
            echo "Load: $(uptime | awk -F'load average:' '{print $2}' | tr -d ' ')"
            echo "Connections: $connections"
            echo "Laravel: $(curl -s -o /dev/null -w "%{http_code}" --connect-timeout 2 "http://localhost:8000" 2>/dev/null || echo "FAILED")"
            echo
        } >> "$MONITOR_FILE"
        
        sleep 5
    done
}

# Trap Ctrl+C
trap 'echo -e "\n${GREEN}Monitoring stopped.${NC}"; exit 0' INT

# Start monitoring
mkdir -p "$MONITOR_DIR"
monitor_loop