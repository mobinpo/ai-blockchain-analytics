#!/bin/bash

# =============================================================================
# AI Blockchain Analytics - Performance Monitoring During Load Tests
# =============================================================================
# This script monitors system resources and application performance during
# the 500 concurrent analyses load test

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
MONITOR_DIR="$SCRIPT_DIR/../reports/monitoring_$TIMESTAMP"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m'

# Configuration
SAMPLE_INTERVAL=5  # seconds between samples
MONITOR_DURATION=1800  # 30 minutes max
APP_PORT=8000
DB_PORT=5432
REDIS_PORT=6379

mkdir -p "$MONITOR_DIR"

log_info() {
    echo -e "${BLUE}[$(date '+%H:%M:%S')]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[$(date '+%H:%M:%S')]${NC} $1"
}

log_error() {
    echo -e "${RED}[$(date '+%H:%M:%S')]${NC} $1"
}

# System resource monitoring
monitor_system_resources() {
    local output_file="$MONITOR_DIR/system_resources.csv"
    
    # CSV header
    echo "timestamp,load_1m,load_5m,load_15m,cpu_user,cpu_system,cpu_idle,mem_total,mem_used,mem_available,swap_used,disk_usage" > "$output_file"
    
    log_info "📊 Starting system resource monitoring → $output_file"
    
    while true; do
        local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
        
        # Load averages
        local load_avg=$(cat /proc/loadavg | cut -d' ' -f1-3)
        local load_1m=$(echo $load_avg | cut -d' ' -f1)
        local load_5m=$(echo $load_avg | cut -d' ' -f2)  
        local load_15m=$(echo $load_avg | cut -d' ' -f3)
        
        # CPU stats
        local cpu_stats=$(top -bn1 | grep "Cpu(s)" | awk '{print $2,$4,$8}' | sed 's/%us,//;s/%sy,//;s/%id,//')
        local cpu_user=$(echo $cpu_stats | cut -d' ' -f1)
        local cpu_system=$(echo $cpu_stats | cut -d' ' -f2)
        local cpu_idle=$(echo $cpu_stats | cut -d' ' -f3)
        
        # Memory stats (MB)
        local mem_stats=$(free -m | grep '^Mem:')
        local mem_total=$(echo $mem_stats | awk '{print $2}')
        local mem_used=$(echo $mem_stats | awk '{print $3}')
        local mem_available=$(echo $mem_stats | awk '{print $7}')
        
        # Swap usage (MB)
        local swap_used=$(free -m | grep '^Swap:' | awk '{print $3}')
        
        # Disk usage percentage
        local disk_usage=$(df -h / | tail -1 | awk '{print $5}' | sed 's/%//')
        
        echo "$timestamp,$load_1m,$load_5m,$load_15m,$cpu_user,$cpu_system,$cpu_idle,$mem_total,$mem_used,$mem_available,$swap_used,$disk_usage" >> "$output_file"
        
        sleep $SAMPLE_INTERVAL
    done
}

# Network connection monitoring
monitor_network() {
    local output_file="$MONITOR_DIR/network_connections.csv"
    
    echo "timestamp,total_connections,established,listen,time_wait,close_wait,app_connections,db_connections,redis_connections" > "$output_file"
    
    log_info "🌐 Starting network monitoring → $output_file"
    
    while true; do
        local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
        
        # Total connections
        local total_connections=$(ss -tun | wc -l)
        
        # Connection states
        local established=$(ss -tun state established | wc -l)
        local listen=$(ss -tln | wc -l)
        local time_wait=$(ss -tun state time-wait | wc -l)
        local close_wait=$(ss -tun state close-wait | wc -l)
        
        # Application specific connections
        local app_connections=$(ss -tun | grep ":$APP_PORT" | wc -l)
        local db_connections=$(ss -tun | grep ":$DB_PORT" | wc -l)
        local redis_connections=$(ss -tun | grep ":$REDIS_PORT" | wc -l)
        
        echo "$timestamp,$total_connections,$established,$listen,$time_wait,$close_wait,$app_connections,$db_connections,$redis_connections" >> "$output_file"
        
        sleep $SAMPLE_INTERVAL
    done
}

# PHP-FPM/RoadRunner process monitoring
monitor_php_processes() {
    local output_file="$MONITOR_DIR/php_processes.csv"
    
    echo "timestamp,php_processes,roadrunner_processes,total_php_memory,avg_php_memory,max_php_memory" > "$output_file"
    
    log_info "🐘 Starting PHP process monitoring → $output_file"
    
    while true; do
        local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
        
        # Count PHP processes
        local php_processes=$(pgrep -c php || echo "0")
        local roadrunner_processes=$(pgrep -c rr || echo "0")
        
        # Memory usage by PHP processes (in MB)
        local php_memory_stats=""
        if [ "$php_processes" -gt 0 ]; then
            php_memory_stats=$(ps -C php -o rss --no-headers | awk '
                BEGIN { total=0; count=0; max=0 }
                { 
                    mem=$1/1024; 
                    total+=mem; 
                    count++; 
                    if(mem>max) max=mem 
                }
                END { 
                    if(count>0) 
                        printf "%.1f,%.1f,%.1f", total, total/count, max
                    else 
                        print "0.0,0.0,0.0"
                }')
        else
            php_memory_stats="0.0,0.0,0.0"
        fi
        
        echo "$timestamp,$php_processes,$roadrunner_processes,$php_memory_stats" >> "$output_file"
        
        sleep $SAMPLE_INTERVAL
    done
}

# Database monitoring (PostgreSQL)
monitor_database() {
    local output_file="$MONITOR_DIR/database_stats.csv"
    
    echo "timestamp,active_connections,idle_connections,total_connections,queries_per_sec,commits_per_sec,rollbacks_per_sec" > "$output_file"
    
    log_info "🗄️  Starting database monitoring → $output_file"
    
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
                COUNT(*) as total
            FROM pg_stat_activity 
            WHERE datname = 'ai_blockchain_analytics';" 2>/dev/null || echo "0 0 0")
        
        local active_conn=$(echo $connection_stats | awk '{print $1}')
        local idle_conn=$(echo $connection_stats | awk '{print $2}')
        local total_conn=$(echo $connection_stats | awk '{print $3}')
        
        # Query stats
        local db_stats=$(psql -h localhost -U postgres -d ai_blockchain_analytics -t -c "
            SELECT 
                xact_commit,
                xact_rollback,
                tup_returned + tup_fetched + tup_inserted + tup_updated + tup_deleted as queries
            FROM pg_stat_database 
            WHERE datname = 'ai_blockchain_analytics';" 2>/dev/null || echo "0 0 0")
        
        local commits=$(echo $db_stats | awk '{print $1}')
        local rollbacks=$(echo $db_stats | awk '{print $2}')
        local queries=$(echo $db_stats | awk '{print $3}')
        
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
        
        echo "$timestamp,$active_conn,$idle_conn,$total_conn,$queries_per_sec,$commits_per_sec,$rollbacks_per_sec" >> "$output_file"
        
        # Store current values for next iteration
        prev_queries=$queries
        prev_commits=$commits
        prev_rollbacks=$rollbacks
        prev_timestamp=$current_timestamp
        
        sleep $SAMPLE_INTERVAL
    done
}

# Application response time monitoring
monitor_app_response() {
    local output_file="$MONITOR_DIR/app_response_times.csv"
    
    echo "timestamp,health_check_ms,api_sentiment_ms,api_verification_ms,dashboard_ms,sentry_errors,telescope_entries" > "$output_file"
    
    log_info "⚡ Starting application response monitoring → $output_file"
    
    while true; do
        local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
        
        # Health check endpoint
        local health_time=$(curl -s -w "%{time_total}" -o /dev/null "http://localhost:$APP_PORT/" 2>/dev/null || echo "0")
        health_time=$(echo "$health_time * 1000" | bc -l 2>/dev/null || echo "0")
        
        # API endpoints with realistic payloads
        local sentiment_time=$(curl -s -w "%{time_total}" -o /dev/null \
            -X POST \
            -H "Content-Type: application/json" \
            -d '{"symbol":"BTC","analysis_type":"sentiment","priority":"medium"}' \
            "http://localhost:$APP_PORT/api/sentiment/analyze" 2>/dev/null || echo "0")
        sentiment_time=$(echo "$sentiment_time * 1000" | bc -l 2>/dev/null || echo "0")
        
        local verification_time=$(curl -s -w "%{time_total}" -o /dev/null \
            "http://localhost:$APP_PORT/api/verification/status/test" 2>/dev/null || echo "0")
        verification_time=$(echo "$verification_time * 1000" | bc -l 2>/dev/null || echo "0")
        
        local dashboard_time=$(curl -s -w "%{time_total}" -o /dev/null \
            "http://localhost:$APP_PORT/dashboard" 2>/dev/null || echo "0")
        dashboard_time=$(echo "$dashboard_time * 1000" | bc -l 2>/dev/null || echo "0")
        
        # Monitor Sentry error count (if Redis is available)
        local sentry_errors=$(redis-cli get "sentry:error_count" 2>/dev/null || echo "0")
        
        # Monitor Telescope entries count
        local telescope_entries=$(psql -h localhost -U postgres -d ai_blockchain_analytics -t -c \
            "SELECT COUNT(*) FROM telescope_entries WHERE created_at > NOW() - INTERVAL '1 minute';" 2>/dev/null | xargs || echo "0")
        
        echo "$timestamp,$health_time,$sentiment_time,$verification_time,$dashboard_time,$sentry_errors,$telescope_entries" >> "$output_file"
        
        sleep $SAMPLE_INTERVAL
    done
}

# Real-time monitoring display
show_realtime_monitor() {
    log_info "🖥️  Starting real-time monitoring display"
    
    while true; do
        clear
        echo -e "${BLUE}===========================================${NC}"
        echo -e "${BLUE} AI Blockchain Analytics - Live Monitor ${NC}"
        echo -e "${BLUE}===========================================${NC}"
        echo
        
        # System load
        echo -e "${GREEN}System Load:${NC}"
        uptime
        echo
        
        # Memory usage
        echo -e "${GREEN}Memory Usage:${NC}"
        free -h
        echo
        
        # Top processes by CPU
        echo -e "${GREEN}Top CPU Processes:${NC}"
        ps aux --sort=-%cpu | head -6
        echo
        
        # Network connections
        echo -e "${GREEN}Network Connections:${NC}"
        echo "Total: $(ss -tun | wc -l)"
        echo "Established: $(ss -tun state established | wc -l)"
        echo "App Port ($APP_PORT): $(ss -tun | grep ":$APP_PORT" | wc -l)"
        echo
        
        # Application health
        echo -e "${GREEN}Application Health:${NC}"
        if curl -s --connect-timeout 2 "http://localhost:$APP_PORT/" > /dev/null; then
            echo -e "${GREEN}✓ Application responsive${NC}"
        else
            echo -e "${RED}✗ Application not responding${NC}"
        fi
        
        echo
        echo -e "${YELLOW}Press Ctrl+C to stop monitoring${NC}"
        echo -e "${BLUE}Data being logged to: $MONITOR_DIR${NC}"
        
        sleep 3
    done
}

# Cleanup function
cleanup() {
    echo
    log_info "🧹 Stopping performance monitoring"
    
    # Kill all background monitoring processes
    jobs -p | xargs -r kill 2>/dev/null || true
    
    log_info "📁 Monitoring data saved to: $MONITOR_DIR"
    log_info "🔧 Generate summary report with: ./analyze_performance.sh $MONITOR_DIR"
}

# Set trap for cleanup
trap cleanup EXIT

# Main execution
main() {
    echo -e "${BLUE}🔍 AI Blockchain Analytics - Performance Monitor${NC}"
    echo "   📊 Monitoring system resources during load test"
    echo "   🌐 Tracking network connections and response times"
    echo "   🗄️  Recording database performance metrics"
    echo "   📁 Data will be saved to: $MONITOR_DIR"
    echo
    
    # Start background monitoring processes
    monitor_system_resources &
    monitor_network &
    monitor_php_processes &
    monitor_database &
    monitor_app_response &
    
    log_info "✅ All monitoring processes started"
    
    # Show real-time display
    show_realtime_monitor
}

# Execute main function
main "$@"