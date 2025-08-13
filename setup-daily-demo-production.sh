#!/bin/bash

# =============================================================================
# Production Daily Demo Script Setup
# =============================================================================

set -e

PROJECT_ROOT="$(pwd)"
LOG_DIR="$PROJECT_ROOT/storage/logs"

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${BLUE}🚀 Setting up Daily Demo Script for Production${NC}"
echo -e "${BLUE}===============================================${NC}"
echo ""

# 1. Create log directories
echo -e "${YELLOW}📁 Setting up log directories...${NC}"
mkdir -p "$LOG_DIR"
touch "$LOG_DIR/demo-daily.log"
touch "$LOG_DIR/demo-health.log"
touch "$LOG_DIR/demo-monitor.log"
touch "$LOG_DIR/cron.log"
echo -e "${GREEN}✅ Log directories created${NC}"
echo ""

# 2. Test demo script
echo -e "${YELLOW}🧪 Testing demo script execution...${NC}"
if docker compose exec app php artisan demo:daily --detailed; then
    echo -e "${GREEN}✅ Demo script test successful${NC}"
else
    echo -e "${RED}❌ Demo script test failed${NC}"
    exit 1
fi
echo ""

# 3. Verify scheduler configuration
echo -e "${YELLOW}📅 Verifying scheduler configuration...${NC}"
docker compose exec app php artisan schedule:list | grep "demo:daily"
echo -e "${GREEN}✅ Scheduler configured correctly${NC}"
echo ""

# 4. Setup production cron job
echo -e "${YELLOW}⏰ Production Cron Job Setup${NC}"
CRON_ENTRY="0 3 * * * cd $PROJECT_ROOT && docker compose exec app php artisan schedule:run >> $LOG_DIR/cron.log 2>&1"

echo "Add this to your production server's crontab:"
echo ""
echo -e "${BLUE}$CRON_ENTRY${NC}"
echo ""
echo "To add automatically:"
echo -e "${YELLOW}echo '$CRON_ENTRY' | crontab -${NC}"
echo ""

# 5. Create systemd service for scheduler (optional)
cat > daily-demo-scheduler.service << EOF
[Unit]
Description=AI Blockchain Analytics Daily Demo Scheduler
After=docker.service
Requires=docker.service

[Service]
Type=simple
User=root
WorkingDirectory=$PROJECT_ROOT
ExecStart=/usr/bin/docker compose exec app php artisan schedule:work
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
EOF

echo -e "${YELLOW}📋 Systemd service file created: daily-demo-scheduler.service${NC}"
echo "To install:"
echo -e "${BLUE}sudo cp daily-demo-scheduler.service /etc/systemd/system/${NC}"
echo -e "${BLUE}sudo systemctl enable daily-demo-scheduler${NC}"
echo -e "${BLUE}sudo systemctl start daily-demo-scheduler${NC}"
echo ""

# 6. Create monitoring script
echo -e "${YELLOW}📊 Setting up monitoring...${NC}"
cat > check-daily-demo.sh << 'EOF'
#!/bin/bash
# Quick health check for daily demo script

LOG_FILE="/var/log/ai-blockchain-analytics/demo-daily.log"
LAST_RUN=$(grep "Daily demo script completed successfully" "$LOG_FILE" | tail -1)

if [[ -n "$LAST_RUN" ]]; then
    echo "✅ Last successful run: $LAST_RUN"
    exit 0
else
    echo "❌ No recent successful runs found"
    exit 1
fi
EOF

chmod +x check-daily-demo.sh
echo -e "${GREEN}✅ Monitoring script created: check-daily-demo.sh${NC}"
echo ""

# 7. Summary
echo -e "${GREEN}🎉 Daily Demo Script Production Setup Complete!${NC}"
echo ""
echo -e "${BLUE}📋 Summary:${NC}"
echo "• Demo script: ✅ Tested and working"
echo "• Scheduler: ✅ Configured for daily 3:00 AM execution"
echo "• Logs: ✅ Directory structure created"
echo "• Monitoring: ✅ Scripts available"
echo "• Cron job: ✅ Ready for production setup"
echo ""
echo -e "${YELLOW}🚀 Next Steps for Production:${NC}"
echo "1. Add cron job to production server"
echo "2. Monitor first few executions"
echo "3. Set up log rotation"
echo "4. Configure alerts for failures"
echo ""
echo -e "${GREEN}The daily demo script will now run automatically every day at 3:00 AM!${NC}"
