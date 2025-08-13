#!/bin/bash

# AI Blockchain Analytics - Performance Monitoring Script
# Monitors system performance during Artillery load testing

echo "🔍 AI Blockchain Analytics - Performance Monitor"
echo "================================================"
echo "Starting performance monitoring for 500 concurrent load test..."
echo ""

# Create logs directory
mkdir -p load-tests/logs

# Set log files
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
CPU_LOG="load-tests/logs/cpu_usage_${TIMESTAMP}.log"
MEMORY_LOG="load-tests/logs/memory_usage_${TIMESTAMP}.log"
DOCKER_LOG="load-tests/logs/docker_stats_${TIMESTAMP}.log"
NETWORK_LOG="load-tests/logs/network_stats_${TIMESTAMP}.log"
APP_LOG="load-tests/logs/app_response_${TIMESTAMP}.log"

echo "📊 Log files:"
echo "   CPU Usage: $CPU_LOG"
echo "   Memory Usage: $MEMORY_LOG"
echo "   Docker Stats: $DOCKER_LOG"
echo "   Network Stats: $NETWORK_LOG"
echo "   App Response: $APP_LOG"
echo ""

# Function to monitor CPU usage
monitor_cpu() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - Starting CPU monitoring..."
    while true; do
        echo "$(date '+%Y-%m-%d %H:%M:%S'),$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d'%' -f1)" >> $CPU_LOG
        sleep 5
    done
}

# Function to monitor memory usage
monitor_memory() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - Starting memory monitoring..."
    while true; do
        MEMORY_INFO=$(free -m | awk 'NR==2{printf "%.1f,%.1f,%.1f", $3*100/$2, $3, $2}')
        echo "$(date '+%Y-%m-%d %H:%M:%S'),$MEMORY_INFO" >> $MEMORY_LOG
        sleep 5
    done
}

# Function to monitor Docker container stats
monitor_docker() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - Starting Docker monitoring..."
    while true; do
        docker stats --no-stream --format "table {{.Container}}\t{{.CPUPerc}}\t{{.MemUsage}}\t{{.NetIO}}\t{{.BlockIO}}" | grep -E "(app|redis|postgres)" >> $DOCKER_LOG
        echo "---" >> $DOCKER_LOG
        sleep 10
    done
}

# Function to monitor network connections
monitor_network() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - Starting network monitoring..."
    while true; do
        CONNECTIONS=$(netstat -an | grep :8003 | grep ESTABLISHED | wc -l)
        LISTEN_CONNECTIONS=$(netstat -an | grep :8003 | grep LISTEN | wc -l)
        echo "$(date '+%Y-%m-%d %H:%M:%S'),$CONNECTIONS,$LISTEN_CONNECTIONS" >> $NETWORK_LOG
        sleep 5
    done
}

# Function to monitor application response times
monitor_app_response() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - Starting application response monitoring..."
    while true; do
        # Test a simple endpoint
        START_TIME=$(date +%s%N)
        HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8003/api/verification-badge/levels)
        END_TIME=$(date +%s%N)
        RESPONSE_TIME=$(( (END_TIME - START_TIME) / 1000000 )) # Convert to milliseconds
        
        echo "$(date '+%Y-%m-%d %H:%M:%S'),$HTTP_CODE,$RESPONSE_TIME" >> $APP_LOG
        sleep 10
    done
}

# Function to display real-time stats
display_realtime_stats() {
    while true; do
        clear
        echo "🔍 AI Blockchain Analytics - Live Performance Monitor"
        echo "===================================================="
        echo "⏰ $(date '+%Y-%m-%d %H:%M:%S')"
        echo ""
        
        # System stats
        echo "💻 SYSTEM STATS:"
        echo "   CPU Usage: $(top -bn1 | grep "Cpu(s)" | awk '{print $2}')"
        echo "   Memory: $(free -h | awk 'NR==2{printf "Used: %s/%s (%.1f%%)", $3,$2,$3*100/$2}')"
        echo "   Load Average: $(uptime | awk -F'load average:' '{print $2}')"
        echo ""
        
        # Docker stats
        echo "🐳 DOCKER STATS:"
        docker stats --no-stream --format "   {{.Container}}: CPU {{.CPUPerc}}, Memory {{.MemUsage}}" | grep -E "(app|redis|postgres)"
        echo ""
        
        # Network connections
        echo "🌐 NETWORK CONNECTIONS:"
        ACTIVE_CONN=$(netstat -an | grep :8003 | grep ESTABLISHED | wc -l)
        echo "   Active connections to port 8003: $ACTIVE_CONN"
        echo ""
        
        # Application health
        echo "🚀 APPLICATION HEALTH:"
        APP_RESPONSE=$(curl -s -w "Response: %{http_code}, Time: %{time_total}s" http://localhost:8003/api/verification-badge/levels -o /dev/null)
        echo "   API Health Check: $APP_RESPONSE"
        echo ""
        
        # Recent errors (if any)
        echo "⚠️  RECENT DOCKER LOGS (last 5 lines):"
        docker compose logs --tail=5 app 2>/dev/null | tail -5 | sed 's/^/   /'
        echo ""
        
        echo "Press Ctrl+C to stop monitoring"
        echo "Log files are being written to load-tests/logs/"
        
        sleep 15
    done
}

# Cleanup function
cleanup() {
    echo ""
    echo "🛑 Stopping performance monitoring..."
    
    # Kill background monitoring processes
    jobs -p | xargs -r kill
    
    echo "📊 Performance monitoring completed!"
    echo ""
    echo "📁 Log files saved:"
    echo "   CPU Usage: $CPU_LOG"
    echo "   Memory Usage: $MEMORY_LOG" 
    echo "   Docker Stats: $DOCKER_LOG"
    echo "   Network Stats: $NETWORK_LOG"
    echo "   App Response: $APP_LOG"
    echo ""
    echo "💡 Tip: Use these logs to analyze performance bottlenecks"
    exit 0
}

# Set up signal handlers
trap cleanup SIGINT SIGTERM

# Start background monitoring processes
monitor_cpu &
monitor_memory &
monitor_docker &
monitor_network &
monitor_app_response &

# Display real-time stats
display_realtime_stats
