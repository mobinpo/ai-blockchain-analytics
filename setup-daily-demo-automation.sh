#!/bin/bash

# =============================================================================
# AI Blockchain Analytics - Daily Demo Automation Setup
# =============================================================================
# This script sets up automated daily demo execution with multiple scheduling options

set -e

echo "🚀 AI Blockchain Analytics - Daily Demo Automation Setup"
echo "========================================================"

# Function to check if running in Docker
check_environment() {
    if [ -f "docker-compose.yml" ]; then
        echo "📦 Docker environment detected"
        DOCKER_ENV=true
        PHP_COMMAND="docker compose exec app php"
    else
        echo "🖥️  Local environment detected"
        DOCKER_ENV=false
        PHP_COMMAND="php"
    fi
}

# Function to create cron jobs
setup_cron_jobs() {
    echo ""
    echo "⏰ Setting up cron jobs for automated demo execution..."
    
    # Create temporary cron file
    CRON_FILE="/tmp/ai-blockchain-cron"
    
    # Get current directory
    CURRENT_DIR=$(pwd)
    
    cat > $CRON_FILE << EOF
# AI Blockchain Analytics - Automated Demo Scripts
# Generated on $(date)

# Daily Demo Script - Run every day at 9:00 AM
0 9 * * * cd $CURRENT_DIR && $PHP_COMMAND artisan demo:daily --output-file=storage/logs/daily-demo-\$(date +\%Y-\%m-\%d).json >> storage/logs/cron-demo.log 2>&1

# Weekly Demo Script - Run every Monday at 10:00 AM  
0 10 * * 1 cd $CURRENT_DIR && $PHP_COMMAND artisan demo:daily --detailed --output-file=storage/logs/weekly-demo-\$(date +\%Y-\%m-\%d).json >> storage/logs/cron-demo.log 2>&1

# Health Check Demo - Run every 6 hours
0 */6 * * * cd $CURRENT_DIR && $PHP_COMMAND artisan demo:daily --skip-crawling --skip-reports --skip-famous --output-file=storage/logs/health-check-\$(date +\%Y-\%m-\%d-\%H).json >> storage/logs/cron-health.log 2>&1

# Marketing Demo - Run every day at 2:00 PM (for demo purposes)
0 14 * * * cd $CURRENT_DIR && $PHP_COMMAND artisan demo:daily --detailed --output-file=storage/logs/marketing-demo-\$(date +\%Y-\%m-\%d).json >> storage/logs/cron-marketing.log 2>&1

# Log Cleanup - Run every Sunday at 2:00 AM
0 2 * * 0 cd $CURRENT_DIR && find storage/logs -name "daily-demo-*.json" -mtime +30 -delete && find storage/logs -name "*.log" -mtime +7 -delete

EOF
    
    echo "✅ Cron jobs configuration created at: $CRON_FILE"
    echo ""
    echo "📋 Configured schedules:"
    echo "   • Daily Demo: Every day at 9:00 AM"
    echo "   • Weekly Demo: Every Monday at 10:00 AM"
    echo "   • Health Check: Every 6 hours"
    echo "   • Marketing Demo: Every day at 2:00 PM"
    echo "   • Log Cleanup: Every Sunday at 2:00 AM"
    echo ""
    
    # Install cron jobs
    if command -v crontab &> /dev/null; then
        echo "🔧 Installing cron jobs..."
        crontab $CRON_FILE
        echo "✅ Cron jobs installed successfully!"
        
        echo ""
        echo "📅 Current cron jobs:"
        crontab -l | grep -A 20 "AI Blockchain Analytics" || echo "No cron jobs found"
    else
        echo "⚠️  crontab not available. Manual installation required:"
        echo "   1. Copy the contents of $CRON_FILE"
        echo "   2. Run 'crontab -e' and paste the contents"
    fi
    
    # Keep the cron file for reference
    cp $CRON_FILE cron-jobs-backup.txt
    echo "📄 Cron jobs backup saved to: cron-jobs-backup.txt"
}

# Function to create systemd timer (alternative to cron)
setup_systemd_timer() {
    echo ""
    echo "⚙️  Creating systemd timer configuration..."
    
    # Create systemd service file
    cat > /tmp/ai-blockchain-demo.service << EOF
[Unit]
Description=AI Blockchain Analytics Daily Demo
After=network.target

[Service]
Type=oneshot
User=$(whoami)
WorkingDirectory=$CURRENT_DIR
ExecStart=$PHP_COMMAND artisan demo:daily --output-file=storage/logs/daily-demo-\$(date +\%Y-\%m-\%d).json
StandardOutput=append:$CURRENT_DIR/storage/logs/systemd-demo.log
StandardError=append:$CURRENT_DIR/storage/logs/systemd-demo.log

[Install]
WantedBy=multi-user.target
EOF

    # Create systemd timer file
    cat > /tmp/ai-blockchain-demo.timer << EOF
[Unit]
Description=Run AI Blockchain Analytics Demo Daily
Requires=ai-blockchain-demo.service

[Timer]
OnCalendar=daily
Persistent=true

[Install]
WantedBy=timers.target
EOF

    echo "✅ Systemd service and timer files created:"
    echo "   • Service: /tmp/ai-blockchain-demo.service"
    echo "   • Timer: /tmp/ai-blockchain-demo.timer"
    echo ""
    echo "📋 To install systemd timer:"
    echo "   sudo cp /tmp/ai-blockchain-demo.* /etc/systemd/system/"
    echo "   sudo systemctl daemon-reload"
    echo "   sudo systemctl enable ai-blockchain-demo.timer"
    echo "   sudo systemctl start ai-blockchain-demo.timer"
}

# Function to create monitoring script
create_monitoring_script() {
    echo ""
    echo "📊 Creating demo monitoring script..."
    
    cat > monitor-daily-demo.sh << 'EOF'
#!/bin/bash

# AI Blockchain Analytics - Demo Monitoring Script
# Monitors the daily demo execution and sends alerts

CURRENT_DIR=$(pwd)
LOG_DIR="$CURRENT_DIR/storage/logs"
TODAY=$(date +%Y-%m-%d)

echo "🔍 AI Blockchain Analytics - Demo Monitoring"
echo "==========================================="

# Check if today's demo ran
if [ -f "$LOG_DIR/daily-demo-$TODAY.json" ]; then
    echo "✅ Today's demo completed successfully"
    
    # Extract key metrics from the demo results
    if command -v jq &> /dev/null; then
        TASKS_COMPLETED=$(jq -r '.tasks_completed // "N/A"' "$LOG_DIR/daily-demo-$TODAY.json")
        TASKS_FAILED=$(jq -r '.tasks_failed // "N/A"' "$LOG_DIR/daily-demo-$TODAY.json")
        EXECUTION_TIME=$(jq -r '.performance_metrics.execution_time // "N/A"' "$LOG_DIR/daily-demo-$TODAY.json")
        
        echo "📊 Demo Metrics:"
        echo "   • Tasks Completed: $TASKS_COMPLETED"
        echo "   • Tasks Failed: $TASKS_FAILED"
        echo "   • Execution Time: $EXECUTION_TIME"
        
        if [ "$TASKS_FAILED" != "0" ] && [ "$TASKS_FAILED" != "N/A" ]; then
            echo "⚠️  Warning: Some tasks failed during demo execution"
        fi
    else
        echo "📄 Demo results file exists but jq not available for parsing"
    fi
else
    echo "❌ Today's demo has not run yet or failed"
    echo "📅 Expected file: $LOG_DIR/daily-demo-$TODAY.json"
fi

# Check recent log files
echo ""
echo "📋 Recent demo files:"
ls -la "$LOG_DIR"/daily-demo-*.json 2>/dev/null | tail -5 || echo "   No demo files found"

# Check disk usage
echo ""
echo "💾 Storage usage:"
du -sh "$LOG_DIR" 2>/dev/null || echo "   Unable to check storage usage"

# Check for errors in recent logs
echo ""
echo "🚨 Recent errors (last 24 hours):"
find "$LOG_DIR" -name "*.log" -mtime -1 -exec grep -l "ERROR\|FAILED\|Exception" {} \; 2>/dev/null | head -3 | while read file; do
    echo "   📄 $file"
    grep "ERROR\|FAILED\|Exception" "$file" | tail -2 | sed 's/^/      /'
done || echo "   No recent errors found"

EOF

    chmod +x monitor-daily-demo.sh
    echo "✅ Monitoring script created: monitor-daily-demo.sh"
}

# Function to create quick demo runners
create_demo_runners() {
    echo ""
    echo "🎮 Creating quick demo runner scripts..."
    
    # Full demo runner
    cat > run-full-demo.sh << EOF
#!/bin/bash
# Run full comprehensive demo
echo "🚀 Running Full AI Blockchain Analytics Demo..."
$PHP_COMMAND artisan demo:daily --detailed --output-file=storage/logs/manual-demo-\$(date +%Y-%m-%d-%H-%M).json
EOF
    
    # Quick demo runner (for presentations)
    cat > run-quick-demo.sh << EOF
#!/bin/bash
# Run quick demo (skip time-consuming tasks)
echo "⚡ Running Quick AI Blockchain Analytics Demo..."
$PHP_COMMAND artisan demo:daily --skip-crawling --skip-reports --detailed --output-file=storage/logs/quick-demo-\$(date +%Y-%m-%d-%H-%M).json
EOF
    
    # Health check runner
    cat > run-health-check.sh << EOF
#!/bin/bash
# Run system health check demo
echo "🔧 Running System Health Check Demo..."
$PHP_COMMAND artisan demo:daily --skip-analysis --skip-crawling --skip-reports --skip-onboarding --skip-famous --detailed
EOF
    
    # Marketing demo runner
    cat > run-marketing-demo.sh << EOF
#!/bin/bash
# Run marketing/presentation demo
echo "🎯 Running Marketing Demo..."
$PHP_COMMAND artisan demo:daily --detailed --output-file=storage/logs/marketing-demo-\$(date +%Y-%m-%d-%H-%M).json
echo ""
echo "🎉 Demo completed! Results saved for marketing use."
echo "📄 Use the generated JSON file for metrics and reporting."
EOF
    
    chmod +x run-*.sh
    
    echo "✅ Demo runner scripts created:"
    echo "   • run-full-demo.sh - Complete comprehensive demo"
    echo "   • run-quick-demo.sh - Fast demo for presentations"
    echo "   • run-health-check.sh - System health verification"
    echo "   • run-marketing-demo.sh - Marketing/presentation demo"
}

# Function to setup log rotation
setup_log_rotation() {
    echo ""
    echo "🔄 Setting up log rotation..."
    
    cat > /tmp/ai-blockchain-logrotate << EOF
$CURRENT_DIR/storage/logs/*.log {
    daily
    rotate 30
    compress
    delaycompress
    missingok
    notifempty
    copytruncate
}

$CURRENT_DIR/storage/logs/daily-demo-*.json {
    weekly
    rotate 12
    compress
    delaycompress
    missingok
    notifempty
}
EOF
    
    echo "✅ Log rotation configuration created at: /tmp/ai-blockchain-logrotate"
    echo "📋 To install log rotation:"
    echo "   sudo cp /tmp/ai-blockchain-logrotate /etc/logrotate.d/ai-blockchain"
    echo "   sudo chmod 644 /etc/logrotate.d/ai-blockchain"
}

# Main execution
main() {
    echo ""
    echo "🎯 Starting automation setup..."
    
    check_environment
    setup_cron_jobs
    setup_systemd_timer
    create_monitoring_script
    create_demo_runners
    setup_log_rotation
    
    echo ""
    echo "🎉 Daily Demo Automation Setup Complete!"
    echo "========================================"
    echo ""
    echo "✅ What's been configured:"
    echo "   📅 Automated cron jobs for daily/weekly/health demos"
    echo "   ⚙️  Systemd timer configuration (alternative to cron)"
    echo "   📊 Demo monitoring and alerting script"
    echo "   🎮 Quick demo runner scripts for different scenarios"
    echo "   🔄 Log rotation to manage disk space"
    echo ""
    echo "🚀 Next Steps:"
    echo "   1. Verify cron jobs: crontab -l"
    echo "   2. Test manual demo: ./run-quick-demo.sh"
    echo "   3. Monitor execution: ./monitor-daily-demo.sh"
    echo "   4. Check logs: tail -f storage/logs/cron-demo.log"
    echo ""
    echo "📋 Available commands:"
    echo "   • $PHP_COMMAND artisan demo:daily --help"
    echo "   • ./run-full-demo.sh"
    echo "   • ./run-quick-demo.sh"
    echo "   • ./run-health-check.sh"
    echo "   • ./run-marketing-demo.sh"
    echo "   • ./monitor-daily-demo.sh"
    echo ""
    echo "🎯 Your platform will now automatically showcase its capabilities daily!"
}

# Execute main function
main
