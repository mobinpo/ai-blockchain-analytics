#!/bin/bash

# =============================================================================
# Enhanced 500 Concurrent Analysis Load Test - Real-Time Monitoring
# =============================================================================
# Advanced monitoring script for tracking system performance during
# 500 concurrent blockchain analysis load test

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
MONITOR_DIR="$SCRIPT_DIR/../reports/500_concurrent_$TIMESTAMP"
LOG_FILE="$MONITOR_DIR/monitor.log"

# Colors for terminal output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
WHITE='\033[1;37m'
NC='\033[0m'

# Configuration
SAMPLE_INTERVAL=3
APP_PORT=8003
DB_PORT=5432
REDIS_PORT=6379
MONITORING_PORT=9090

# Application endpoints to monitor
ENDPOINTS=(
    "/"
    "/api/health"
    "/api/sentiment/analyze"
    "/api/contracts/analyze"
    "/api/enhanced-verification/submit"
    "/api/enhanced-pdf/generate"
    "/dashboard"
)

# Performance thresholds
CPU_WARNING_THRESHOLD=70
CPU_CRITICAL_THRESHOLD=85
MEMORY_WARNING_THRESHOLD=80
MEMORY_CRITICAL_THRESHOLD=90
RESPONSE_TIME_WARNING=5000
RESPONSE_TIME_CRITICAL=10000

mkdir -p "$MONITOR_DIR"

# Initialize log
echo "# Enhanced 500 Concurrent Analysis Load Test Monitoring Log" > "$LOG_FILE"
echo "# Started: $(date)" >> "$LOG_FILE"
echo "# Target: http://localhost:$APP_PORT" >> "$LOG_FILE"
echo "" >> "$LOG_FILE"

log_info() {
    local msg="[$(date '+%H:%M:%S')] INFO: $1"
    echo -e "${BLUE}$msg${NC}"
    echo "$msg" >> "$LOG_FILE"
}

log_warning() {
    local msg="[$(date '+%H:%M:%S')] WARNING: $1"
    echo -e "${YELLOW}$msg${NC}"
    echo "$msg" >> "$LOG_FILE"
}

log_error() {
    local msg="[$(date '+%H:%M:%S')] ERROR: $1"
    echo -e "${RED}$msg${NC}"
    echo "$msg" >> "$LOG_FILE"
}

log_success() {
    local msg="[$(date '+%H:%M:%S')] SUCCESS: $1"
    echo -e "${GREEN}$msg${NC}"
    echo "$msg" >> "$LOG_FILE"
}

# System metrics collection
collect_system_metrics() {
    local output_file="$MONITOR_DIR/system_metrics.csv"
    
    # CSV header
    if [ ! -f "$output_file" ]; then
        echo "timestamp,load_1m,load_5m,load_15m,cpu_user,cpu_system,cpu_idle,cpu_iowait,mem_total,mem_used,mem_available,mem_cached,swap_used,disk_usage,disk_io_read,disk_io_write,network_rx,network_tx" > "$output_file"
    fi
    
    while true; do
        local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
        
        # Load averages
        local load_stats=$(cat /proc/loadavg)
        local load_1m=$(echo $load_stats | cut -d' ' -f1)
        local load_5m=$(echo $load_stats | cut -d' ' -f2)
        local load_15m=$(echo $load_stats | cut -d' ' -f3)
        
        # CPU stats - enhanced with iowait
        local cpu_line=$(grep '^cpu ' /proc/stat)
        local cpu_user=$(echo $cpu_line | awk '{print $2}')
        local cpu_system=$(echo $cpu_line | awk '{print $4}')
        local cpu_idle=$(echo $cpu_line | awk '{print $5}')
        local cpu_iowait=$(echo $cpu_line | awk '{print $6}')
        
        # Memory stats (MB) - enhanced
        local mem_stats=$(cat /proc/meminfo)
        local mem_total=$(echo "$mem_stats" | grep '^MemTotal:' | awk '{print int($2/1024)}')
        local mem_available=$(echo "$mem_stats" | grep '^MemAvailable:' | awk '{print int($2/1024)}')
        local mem_cached=$(echo "$mem_stats" | grep '^Cached:' | awk '{print int($2/1024)}')
        local mem_used=$((mem_total - mem_available))
        local swap_used=$(echo "$mem_stats" | grep '^SwapTotal:\|^SwapFree:' | awk 'NR==1{total=$2} NR==2{free=$2} END{print int((total-free)/1024)}')
        
        # Disk usage and I/O
        local disk_usage=$(df -h / | tail -1 | awk '{print $5}' | sed 's/%//')
        local disk_io_stats=$(cat /proc/diskstats | grep ' sda ' | head -1)
        local disk_io_read=$(echo "$disk_io_stats" | awk '{print $6}')
        local disk_io_write=$(echo "$disk_io_stats" | awk '{print $10}')
        
        # Network stats
        local network_stats=$(cat /proc/net/dev | grep 'eth0\|ens\|enp' | head -1)
        local network_rx=$(echo "$network_stats" | awk '{print $2}')
        local network_tx=$(echo "$network_stats" | awk '{print $10}')
        
        echo "$timestamp,$load_1m,$load_5m,$load_15m,$cpu_user,$cpu_system,$cpu_idle,$cpu_iowait,$mem_total,$mem_used,$mem_available,$mem_cached,$swap_used,$disk_usage,$disk_io_read,$disk_io_write,$network_rx,$network_tx" >> "$output_file"
        
        # Alert on high resource usage
        local cpu_usage=$((100 - cpu_idle))
        local mem_usage=$(echo "scale=0; ($mem_used * 100) / $mem_total" | bc -l)
        
        if [ "$cpu_usage" -gt "$CPU_CRITICAL_THRESHOLD" ]; then
            log_error "CPU usage critical: ${cpu_usage}%"
        elif [ "$cpu_usage" -gt "$CPU_WARNING_THRESHOLD" ]; then
            log_warning "CPU usage high: ${cpu_usage}%"
        fi
        
        if [ "$mem_usage" -gt "$MEMORY_CRITICAL_THRESHOLD" ]; then
            log_error "Memory usage critical: ${mem_usage}%"
        elif [ "$mem_usage" -gt "$MEMORY_WARNING_THRESHOLD" ]; then
            log_warning "Memory usage high: ${mem_usage}%"
        fi
        
        sleep $SAMPLE_INTERVAL
    done
}

# Application performance monitoring
monitor_application_performance() {
    local output_file="$MONITOR_DIR/app_performance.csv"
    
    if [ ! -f "$output_file" ]; then
        echo "timestamp,endpoint,response_time_ms,status_code,body_size,connection_time,ssl_time,ttfb,total_time" > "$output_file"
    fi
    
    while true; do
        local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
        
        for endpoint in "${ENDPOINTS[@]}"; do
            local url="http://localhost:$APP_PORT$endpoint"
            local temp_file=$(mktemp)
            
            # Detailed curl timing
            local curl_output=$(curl -s -w "%{http_code},%{size_download},%{time_connect},%{time_appconnect},%{time_pretransfer},%{time_starttransfer},%{time_total}" \
                -o "$temp_file" \
                --max-time 30 \
                --connect-timeout 10 \
                "$url" 2>/dev/null || echo "000,0,0,0,0,0,30")
            
            local status_code=$(echo "$curl_output" | cut -d',' -f1)
            local body_size=$(echo "$curl_output" | cut -d',' -f2)
            local connection_time=$(echo "$curl_output" | cut -d',' -f3 | awk '{print int($1*1000)}')
            local ssl_time=$(echo "$curl_output" | cut -d',' -f4 | awk '{print int($1*1000)}')
            local ttfb=$(echo "$curl_output" | cut -d',' -f6 | awk '{print int($1*1000)}')
            local total_time=$(echo "$curl_output" | cut -d',' -f7 | awk '{print int($1*1000)}')
            
            echo "$timestamp,$endpoint,$total_time,$status_code,$body_size,$connection_time,$ssl_time,$ttfb,$total_time" >> "$output_file"
            
            # Alert on slow responses
            if [ "$total_time" -gt "$RESPONSE_TIME_CRITICAL" ]; then
                log_error "Slow response: $endpoint - ${total_time}ms"
            elif [ "$total_time" -gt "$RESPONSE_TIME_WARNING" ]; then
                log_warning "Slow response: $endpoint - ${total_time}ms"
            fi
            
            # Alert on error status codes
            if [[ "$status_code" =~ ^5 ]]; then
                log_error "Server error: $endpoint - HTTP $status_code"
            elif [[ "$status_code" =~ ^4 ]]; then
                log_warning "Client error: $endpoint - HTTP $status_code"
            fi
            
            rm -f "$temp_file"
        done
        
        sleep $SAMPLE_INTERVAL
    done
}

# Database monitoring (enhanced)
monitor_database() {
    local output_file="$MONITOR_DIR/database_metrics.csv"
    
    if [ ! -f "$output_file" ]; then
        echo "timestamp,active_connections,idle_connections,total_connections,max_connections,queries_per_sec,commits_per_sec,rollbacks_per_sec,cache_hit_ratio,deadlocks,temp_files,checkpoint_sync_time" > "$output_file"
    fi
    
    local prev_queries=0
    local prev_commits=0
    local prev_rollbacks=0
    local prev_timestamp=0
    
    while true; do
        local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
        local current_timestamp=$(date +%s)
        
        # Connection stats
        local connection_stats=$(psql -h localhost -U postgres -d ai_blockchain_analytics -t -c "
            SELECT 
                COUNT(CASE WHEN state = 'active' THEN 1 END) as active,
                COUNT(CASE WHEN state = 'idle' THEN 1 END) as idle,
                COUNT(*) as total,
                (SELECT setting FROM pg_settings WHERE name = 'max_connections') as max_conn
            FROM pg_stat_activity 
            WHERE datname = 'ai_blockchain_analytics';" 2>/dev/null || echo "0 0 0 0")
        
        local active_conn=$(echo $connection_stats | awk '{print $1}')
        local idle_conn=$(echo $connection_stats | awk '{print $2}')
        local total_conn=$(echo $connection_stats | awk '{print $3}')
        local max_conn=$(echo $connection_stats | awk '{print $4}')
        
        # Query and transaction stats
        local db_stats=$(psql -h localhost -U postgres -d ai_blockchain_analytics -t -c "
            SELECT 
                xact_commit,
                xact_rollback,
                tup_returned + tup_fetched + tup_inserted + tup_updated + tup_deleted as queries,
                blks_hit::float / (blks_hit + blks_read)::float * 100 as cache_hit_ratio,
                deadlocks,
                temp_files,
                checkpoint_sync_time
            FROM pg_stat_database 
            WHERE datname = 'ai_blockchain_analytics';" 2>/dev/null || echo "0 0 0 0 0 0 0")
        
        local commits=$(echo $db_stats | awk '{print $1}')
        local rollbacks=$(echo $db_stats | awk '{print $2}')
        local queries=$(echo $db_stats | awk '{print $3}')
        local cache_hit_ratio=$(echo $db_stats | awk '{print $4}')
        local deadlocks=$(echo $db_stats | awk '{print $5}')
        local temp_files=$(echo $db_stats | awk '{print $6}')
        local checkpoint_sync_time=$(echo $db_stats | awk '{print $7}')
        
        # Calculate per-second rates
        local queries_per_sec=0
        local commits_per_sec=0
        local rollbacks_per_sec=0
        
        if [ $prev_timestamp -gt 0 ]; then
            local time_diff=$((current_timestamp - prev_timestamp))
            if [ $time_diff -gt 0 ]; then
                queries_per_sec=$(echo "scale=2; ($queries - $prev_queries) / $time_diff" | bc -l 2>/dev/null || echo "0")
                commits_per_sec=$(echo "scale=2; ($commits - $prev_commits) / $time_diff" | bc -l 2>/dev/null || echo "0")
                rollbacks_per_sec=$(echo "scale=2; ($rollbacks - $prev_rollbacks) / $time_diff" | bc -l 2>/dev/null || echo "0")
            fi
        fi
        
        echo "$timestamp,$active_conn,$idle_conn,$total_conn,$max_conn,$queries_per_sec,$commits_per_sec,$rollbacks_per_sec,$cache_hit_ratio,$deadlocks,$temp_files,$checkpoint_sync_time" >> "$output_file"
        
        # Alerts
        local connection_usage=$(echo "scale=0; ($total_conn * 100) / $max_conn" | bc -l)
        if [ "$connection_usage" -gt "80" ]; then
            log_warning "High database connection usage: ${connection_usage}%"
        fi
        
        if [ "$deadlocks" -gt "0" ]; then
            log_error "Database deadlocks detected: $deadlocks"
        fi
        
        # Store current values
        prev_queries=$queries
        prev_commits=$commits
        prev_rollbacks=$rollbacks
        prev_timestamp=$current_timestamp
        
        sleep $SAMPLE_INTERVAL
    done
}

# Container monitoring (Docker)
monitor_containers() {
    local output_file="$MONITOR_DIR/container_metrics.csv"
    
    if [ ! -f "$output_file" ]; then
        echo "timestamp,container_name,cpu_percent,memory_usage,memory_limit,memory_percent,network_rx,network_tx,block_io_read,block_io_write,status" > "$output_file"
    fi
    
    while true; do
        local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
        
        # Get container stats
        docker stats --no-stream --format "table {{.Container}},{{.CPUPerc}},{{.MemUsage}},{{.MemPerc}},{{.NetIO}},{{.BlockIO}}" | tail -n +2 | while IFS=',' read -r container cpu_percent mem_usage mem_percent net_io block_io; do
            # Parse memory usage (e.g., "123.4MiB / 2GiB")
            local mem_used=$(echo "$mem_usage" | cut -d'/' -f1 | sed 's/[^0-9.]//g')
            local mem_limit=$(echo "$mem_usage" | cut -d'/' -f2 | sed 's/[^0-9.]//g')
            
            # Parse network I/O (e.g., "1.2MB / 3.4MB")
            local network_rx=$(echo "$net_io" | cut -d'/' -f1 | sed 's/[^0-9.]//g')
            local network_tx=$(echo "$net_io" | cut -d'/' -f2 | sed 's/[^0-9.]//g')
            
            # Parse block I/O (e.g., "5.6MB / 7.8MB")
            local block_io_read=$(echo "$block_io" | cut -d'/' -f1 | sed 's/[^0-9.]//g')
            local block_io_write=$(echo "$block_io" | cut -d'/' -f2 | sed 's/[^0-9.]//g')
            
            # Get container status
            local status=$(docker inspect --format='{{.State.Status}}' "$container" 2>/dev/null || echo "unknown")
            
            echo "$timestamp,$container,$cpu_percent,$mem_used,$mem_limit,$mem_percent,$network_rx,$network_tx,$block_io_read,$block_io_write,$status" >> "$output_file"
        done
        
        sleep $SAMPLE_INTERVAL
    done
}

# Real-time dashboard
show_realtime_dashboard() {
    while true; do
        clear
        echo -e "${WHITE}═══════════════════════════════════════════════════════════════════════${NC}"
        echo -e "${WHITE}    🚀 AI Blockchain Analytics - 500 Concurrent Load Test Monitor    ${NC}"
        echo -e "${WHITE}═══════════════════════════════════════════════════════════════════════${NC}"
        echo
        
        # Current time and test duration
        local current_time=$(date '+%H:%M:%S')
        local test_start_time=$(stat -c %Y "$MONITOR_DIR" 2>/dev/null || date +%s)
        local current_timestamp=$(date +%s)
        local test_duration=$((current_timestamp - test_start_time))
        local duration_formatted=$(printf '%02d:%02d:%02d' $((test_duration/3600)) $((test_duration%3600/60)) $((test_duration%60)))
        
        echo -e "${CYAN}⏰ Current Time: $current_time     📊 Test Duration: $duration_formatted${NC}"
        echo
        
        # System overview
        echo -e "${GREEN}🖥️  SYSTEM OVERVIEW${NC}"
        echo "─────────────────────"
        
        # Load average
        local load_avg=$(cat /proc/loadavg | cut -d' ' -f1-3)
        echo -e "📈 Load Average: ${YELLOW}$load_avg${NC}"
        
        # Memory usage
        local mem_info=$(free -h | grep '^Mem:')
        local mem_used=$(echo $mem_info | awk '{print $3}')
        local mem_total=$(echo $mem_info | awk '{print $2}')
        local mem_percent=$(free | grep '^Mem:' | awk '{printf "%.1f", $3/$2*100}')
        echo -e "🧠 Memory: ${YELLOW}$mem_used${NC} / ${CYAN}$mem_total${NC} (${YELLOW}$mem_percent%${NC})"
        
        # CPU usage (simplified)
        local cpu_usage=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | sed 's/%us,//')
        echo -e "⚡ CPU Usage: ${YELLOW}$cpu_usage%${NC}"
        echo
        
        # Application health
        echo -e "${GREEN}🔍 APPLICATION HEALTH${NC}"
        echo "─────────────────────────"
        
        for endpoint in "/" "/api/health" "/dashboard"; do
            local url="http://localhost:$APP_PORT$endpoint"
            local response=$(curl -s --connect-timeout 3 --max-time 5 -w "%{http_code},%{time_total}" -o /dev/null "$url" 2>/dev/null || echo "000,5.000")
            local status_code=$(echo "$response" | cut -d',' -f1)
            local response_time=$(echo "$response" | cut -d',' -f2 | awk '{print int($1*1000)}')
            
            local status_icon="❌"
            local status_color="$RED"
            if [ "$status_code" = "200" ]; then
                status_icon="✅"
                status_color="$GREEN"
            elif [[ "$status_code" =~ ^[23] ]]; then
                status_icon="⚠️"
                status_color="$YELLOW"
            fi
            
            printf "%-20s %s ${status_color}HTTP %s${NC} (${CYAN}%dms${NC})\n" "$endpoint" "$status_icon" "$status_code" "$response_time"
        done
        echo
        
        # Container status
        echo -e "${GREEN}🐳 CONTAINER STATUS${NC}"
        echo "──────────────────────"
        docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}" | tail -n +2 | while read line; do
            echo "  $line"
        done
        echo
        
        # Database connections
        echo -e "${GREEN}🗄️  DATABASE STATUS${NC}"
        echo "────────────────────"
        local db_connections=$(psql -h localhost -U postgres -d ai_blockchain_analytics -t -c "SELECT COUNT(*) FROM pg_stat_activity WHERE datname = 'ai_blockchain_analytics';" 2>/dev/null | xargs || echo "N/A")
        local db_active=$(psql -h localhost -U postgres -d ai_blockchain_analytics -t -c "SELECT COUNT(*) FROM pg_stat_activity WHERE datname = 'ai_blockchain_analytics' AND state = 'active';" 2>/dev/null | xargs || echo "N/A")
        echo -e "📊 Total Connections: ${YELLOW}$db_connections${NC}"
        echo -e "⚡ Active Queries: ${YELLOW}$db_active${NC}"
        echo
        
        # Instructions
        echo -e "${PURPLE}📁 Data Location: $MONITOR_DIR${NC}"
        echo -e "${YELLOW}Press Ctrl+C to stop monitoring${NC}"
        
        sleep 5
    done
}

# Cleanup function
cleanup() {
    echo
    log_info "🧹 Stopping monitoring processes..."
    
    # Kill all background processes
    jobs -p | xargs -r kill 2>/dev/null || true
    
    # Generate final summary
    generate_summary_report
    
    log_success "📊 Monitoring completed. Data saved to: $MONITOR_DIR"
    log_info "📋 Summary report available at: $MONITOR_DIR/summary_report.md"
}

# Generate summary report
generate_summary_report() {
    local summary_file="$MONITOR_DIR/summary_report.md"
    
    cat > "$summary_file" << EOF
# 500 Concurrent Analysis Load Test - Monitoring Summary

**Test Date**: $(date)
**Test Duration**: $((($(date +%s) - $(stat -c %Y "$MONITOR_DIR")) / 60)) minutes
**Monitoring Interval**: ${SAMPLE_INTERVAL} seconds

## 📊 Data Files Generated

- \`system_metrics.csv\` - System resource usage (CPU, memory, disk, network)
- \`app_performance.csv\` - Application endpoint response times and status codes
- \`database_metrics.csv\` - Database connection and query statistics
- \`container_metrics.csv\` - Docker container resource usage
- \`monitor.log\` - Detailed monitoring log with alerts and warnings

## 🎯 Key Metrics Collected

### System Resources
- CPU usage (user, system, idle, iowait)
- Memory usage (total, used, available, cached)
- Disk usage and I/O operations
- Network traffic (RX/TX)
- System load averages (1m, 5m, 15m)

### Application Performance
- Response times for all monitored endpoints
- HTTP status codes and error rates
- Connection times and TTFB (Time to First Byte)
- Request/response sizes

### Database Performance
- Active and idle connections
- Query execution rates
- Cache hit ratios
- Deadlock detection
- Checkpoint synchronization times

### Container Metrics
- CPU and memory usage per container
- Network and block I/O per container
- Container health status

## 🚨 Alerts and Thresholds

- **CPU Warning**: >70%, **Critical**: >85%
- **Memory Warning**: >80%, **Critical**: >90%
- **Response Time Warning**: >5s, **Critical**: >10s
- **Database Connection Warning**: >80% of max_connections

## 📈 Analysis Recommendations

1. Review system_metrics.csv for resource bottlenecks
2. Analyze app_performance.csv for slow endpoints
3. Check database_metrics.csv for connection pool issues
4. Monitor container_metrics.csv for resource constraints

## 🔧 Next Steps

- Generate detailed performance graphs
- Identify optimization opportunities
- Scale testing to higher concurrency levels
- Implement performance improvements
EOF

    log_success "📋 Summary report generated: $summary_file"
}

# Set trap for cleanup
trap cleanup EXIT

# Main execution
main() {
    echo -e "${WHITE}🚀 Enhanced 500 Concurrent Analysis Load Test Monitoring${NC}"
    echo -e "${CYAN}   📊 Real-time system and application performance tracking${NC}"
    echo -e "${CYAN}   🔍 Comprehensive metrics collection and alerting${NC}"
    echo -e "${CYAN}   📁 Data will be saved to: $MONITOR_DIR${NC}"
    echo
    
    log_info "🎬 Starting monitoring processes..."
    
    # Start background monitoring processes
    collect_system_metrics &
    monitor_application_performance &
    monitor_database &
    monitor_containers &
    
    log_success "✅ All monitoring processes started successfully"
    echo
    
    # Show real-time dashboard
    show_realtime_dashboard
}

# Execute main function
main "$@"
