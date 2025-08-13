#!/bin/bash

# =============================================================================
# Daily Demo Script Monitoring and Management
# =============================================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
LOG_DIR="$PROJECT_ROOT/storage/logs"
DEMO_LOG="$LOG_DIR/demo-daily.log"
HEALTH_LOG="$LOG_DIR/demo-health.log"
MONITOR_LOG="$LOG_DIR/demo-monitor.log"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$MONITOR_LOG"
}

# Function to check if demo script is scheduled
check_scheduler_status() {
    echo -e "${BLUE}📅 Checking Laravel Scheduler Status...${NC}"
    
    cd "$PROJECT_ROOT"
    
    # Check if scheduler is configured
    if docker compose exec app php artisan schedule:list | grep -q "demo:daily"; then
        echo -e "${GREEN}✅ Daily demo script is scheduled${NC}"
        
        # Show schedule details
        echo -e "${YELLOW}📋 Schedule Configuration:${NC}"
        docker compose exec app php artisan schedule:list | grep "demo:daily"
        
        return 0
    else
        echo -e "${RED}❌ Daily demo script is NOT scheduled${NC}"
        return 1
    fi
}

# Function to check recent demo execution
check_recent_execution() {
    echo -e "${BLUE}📊 Checking Recent Demo Execution...${NC}"
    
    if [[ -f "$DEMO_LOG" ]]; then
        echo -e "${GREEN}✅ Demo log file exists${NC}"
        
        # Check last execution
        LAST_RUN=$(tail -20 "$DEMO_LOG" | grep "Daily demo script completed" | tail -1)
        if [[ -n "$LAST_RUN" ]]; then
            echo -e "${GREEN}✅ Last successful run: $LAST_RUN${NC}"
        else
            echo -e "${YELLOW}⚠️  No recent successful runs found${NC}"
        fi
        
        # Check for errors
        ERROR_COUNT=$(tail -100 "$DEMO_LOG" | grep -c "ERROR\|FAILED\|Exception" || true)
        if [[ $ERROR_COUNT -gt 0 ]]; then
            echo -e "${RED}❌ Found $ERROR_COUNT errors in recent logs${NC}"
            echo -e "${YELLOW}Recent errors:${NC}"
            tail -100 "$DEMO_LOG" | grep "ERROR\|FAILED\|Exception" | tail -5
        else
            echo -e "${GREEN}✅ No errors in recent logs${NC}"
        fi
    else
        echo -e "${YELLOW}⚠️  Demo log file not found at $DEMO_LOG${NC}"
    fi
}

# Function to run demo script manually
run_demo_now() {
    echo -e "${BLUE}🚀 Running Daily Demo Script Now...${NC}"
    
    cd "$PROJECT_ROOT"
    
    # Run with detailed output
    if docker compose exec app php artisan demo:daily --detailed; then
        echo -e "${GREEN}✅ Demo script completed successfully${NC}"
        log "Manual demo script execution completed successfully"
        return 0
    else
        echo -e "${RED}❌ Demo script failed${NC}"
        log "Manual demo script execution failed"
        return 1
    fi
}

# Function to check system health
check_system_health() {
    echo -e "${BLUE}🏥 Checking System Health...${NC}"
    
    cd "$PROJECT_ROOT"
    
    # Check if containers are running
    if docker compose ps | grep -q "Up"; then
        echo -e "${GREEN}✅ Docker containers are running${NC}"
    else
        echo -e "${RED}❌ Docker containers are not running${NC}"
        return 1
    fi
    
    # Check database connection
    if docker compose exec app php artisan tinker --execute="DB::connection()->getPdo(); echo 'Database connected';" 2>/dev/null | grep -q "Database connected"; then
        echo -e "${GREEN}✅ Database connection is healthy${NC}"
    else
        echo -e "${RED}❌ Database connection failed${NC}"
        return 1
    fi
    
    # Check Redis connection
    if docker compose exec app php artisan tinker --execute="Cache::store('redis')->put('health_check', 'ok', 60); echo 'Redis connected';" 2>/dev/null | grep -q "Redis connected"; then
        echo -e "${GREEN}✅ Redis connection is healthy${NC}"
    else
        echo -e "${RED}❌ Redis connection failed${NC}"
        return 1
    fi
    
    # Check queue workers
    if docker compose exec app php artisan queue:monitor | grep -q "No failed jobs"; then
        echo -e "${GREEN}✅ Queue system is healthy${NC}"
    else
        echo -e "${YELLOW}⚠️  Queue system may have issues${NC}"
    fi
}

# Function to show demo statistics
show_demo_statistics() {
    echo -e "${BLUE}📈 Demo Script Statistics...${NC}"
    
    cd "$PROJECT_ROOT"
    
    # Get statistics from database
    docker compose exec app php artisan tinker --execute="
    echo '📊 DAILY DEMO STATISTICS:';
    echo '========================';
    
    // Analysis statistics
    \$totalAnalyses = App\\Models\\Analysis::count();
    \$todayAnalyses = App\\Models\\Analysis::whereDate('created_at', today())->count();
    echo 'Total Analyses: ' . \$totalAnalyses;
    echo 'Today Analyses: ' . \$todayAnalyses;
    
    // User statistics
    \$totalUsers = App\\Models\\User::count();
    \$todayUsers = App\\Models\\User::whereDate('created_at', today())->count();
    echo 'Total Users: ' . \$totalUsers;
    echo 'Today Users: ' . \$todayUsers;
    
    // Famous contracts
    \$famousContracts = App\\Models\\FamousContract::count();
    echo 'Famous Contracts: ' . \$famousContracts;
    
    // Cache statistics
    echo 'Cache Status: ' . (Cache::get('demo_last_run') ? 'Active' : 'Inactive');
    " 2>/dev/null || echo "Could not retrieve statistics"
}

# Function to setup cron job (for production)
setup_production_cron() {
    echo -e "${BLUE}⚙️  Setting up Production Cron Job...${NC}"
    
    CRON_ENTRY="0 3 * * * cd $PROJECT_ROOT && docker compose exec app php artisan schedule:run >> $LOG_DIR/cron.log 2>&1"
    
    echo -e "${YELLOW}Add this to your crontab:${NC}"
    echo "$CRON_ENTRY"
    echo ""
    echo -e "${YELLOW}To add automatically, run:${NC}"
    echo "echo '$CRON_ENTRY' | crontab -"
}

# Main menu function
show_menu() {
    echo -e "${BLUE}🎯 Daily Demo Script Manager${NC}"
    echo -e "${BLUE}=============================${NC}"
    echo ""
    echo "1. Check scheduler status"
    echo "2. Check recent execution"
    echo "3. Run demo script now"
    echo "4. Check system health"
    echo "5. Show demo statistics"
    echo "6. Setup production cron"
    echo "7. Monitor logs (live)"
    echo "8. Exit"
    echo ""
    read -p "Select option (1-8): " choice
    
    case $choice in
        1) check_scheduler_status ;;
        2) check_recent_execution ;;
        3) run_demo_now ;;
        4) check_system_health ;;
        5) show_demo_statistics ;;
        6) setup_production_cron ;;
        7) monitor_logs ;;
        8) exit 0 ;;
        *) echo -e "${RED}Invalid option${NC}" ;;
    esac
}

# Function to monitor logs in real-time
monitor_logs() {
    echo -e "${BLUE}📋 Monitoring Demo Logs (Ctrl+C to exit)...${NC}"
    echo ""
    
    # Create log file if it doesn't exist
    touch "$DEMO_LOG"
    
    # Monitor the log file
    tail -f "$DEMO_LOG"
}

# Main execution
main() {
    log "Demo monitoring script started"
    
    # If no arguments, show menu
    if [[ $# -eq 0 ]]; then
        while true; do
            show_menu
            echo ""
            echo -e "${YELLOW}Press Enter to continue...${NC}"
            read
            clear
        done
    fi
    
    # Handle command line arguments
    case "$1" in
        "status") check_scheduler_status ;;
        "health") check_system_health ;;
        "run") run_demo_now ;;
        "stats") show_demo_statistics ;;
        "logs") monitor_logs ;;
        "setup-cron") setup_production_cron ;;
        *)
            echo "Usage: $0 [status|health|run|stats|logs|setup-cron]"
            echo "  status     - Check scheduler configuration"
            echo "  health     - Check system health"
            echo "  run        - Run demo script now"
            echo "  stats      - Show demo statistics"
            echo "  logs       - Monitor logs in real-time"
            echo "  setup-cron - Show production cron setup"
            echo ""
            echo "Run without arguments for interactive menu"
            ;;
    esac
}

# Execute main function with all arguments
main "$@"
