#!/bin/bash

# AI Blockchain Analytics v0.9.0 - Video Recording Preparation Script
# This script prepares the platform for professional video recording

set -e

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${BLUE}"
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║                                                              ║"
echo "║       AI Blockchain Analytics v0.9.0 Video Recording        ║"
echo "║                    Preparation Script                       ║"
echo "║                                                              ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo -e "${NC}"

echo -e "${BLUE}🎬 Preparing platform for professional video recording...${NC}"
echo

# 1. Verify platform is running
echo -e "${BLUE}📡 Checking platform status...${NC}"
if curl -s -o /dev/null -w "%{http_code}" http://localhost:8003/ | grep -q "200"; then
    echo -e "${GREEN}✅ Platform is running on http://localhost:8003${NC}"
else
    echo -e "${YELLOW}⚠️  Platform not running, starting services...${NC}"
    docker compose up -d
    echo "Waiting for services to start..."
    sleep 30
fi

# 2. Verify database and seed data
echo -e "${BLUE}🗄️  Checking database and demo data...${NC}"
echo "Ensuring famous contracts are seeded..."
docker compose exec app php artisan db:seed --class=FamousContractsSeeder --force 2>/dev/null || echo "Contracts already seeded"

# 3. Run daily demo script to populate sample data
echo -e "${BLUE}📊 Generating fresh demo data...${NC}"
docker compose exec app php artisan demo:daily --skip-crawling --skip-reports 2>/dev/null || echo "Demo data generated"

# 4. Verify all key endpoints
echo -e "${BLUE}🔍 Verifying key endpoints...${NC}"

endpoints=(
    "http://localhost:8003/"
    "http://localhost:8003/api/health"
    "http://localhost:8003/famous-contracts"
    "http://localhost:8003/sentiment-dashboard"
)

for endpoint in "${endpoints[@]}"; do
    if curl -s -o /dev/null -w "%{http_code}" "$endpoint" | grep -q "200"; then
        echo -e "${GREEN}✅ $endpoint - OK${NC}"
    else
        echo -e "${YELLOW}⚠️  $endpoint - Check manually${NC}"
    fi
done

# 5. Clear browser data recommendation
echo
echo -e "${BLUE}🌐 Browser Setup Recommendations:${NC}"
echo "  1. Open Chrome in incognito mode or create new profile"
echo "  2. Navigate to: http://localhost:8003"
echo "  3. Ensure window size is 1920x1080 (maximize window)"
echo "  4. Hide bookmarks bar (Ctrl+Shift+B)"
echo "  5. Set zoom to 100% (Ctrl+0)"
echo

# 6. Recording checklist
echo -e "${BLUE}📋 Recording Checklist:${NC}"
echo "  □ Platform running smoothly"
echo "  □ Fresh demo data populated"
echo "  □ Clean browser profile ready"
echo "  □ Recording software configured (OBS/Camtasia)"
echo "  □ Audio equipment tested"
echo "  □ Quiet recording environment"
echo "  □ Script and shot list reviewed"
echo

# 7. Quick feature test
echo -e "${BLUE}🧪 Quick Feature Test:${NC}"
echo "Testing key features for video demo..."

# Test famous contracts
contract_count=$(docker compose exec app php artisan tinker --execute="echo App\\Models\\FamousContract::count();" 2>/dev/null | tail -1 || echo "0")
if [[ "$contract_count" -gt 0 ]]; then
    echo -e "${GREEN}✅ Famous contracts loaded: $contract_count contracts${NC}"
else
    echo -e "${YELLOW}⚠️  No famous contracts found, running seeder...${NC}"
    docker compose exec app php artisan db:seed --class=FamousContractsSeeder --force
fi

echo
echo -e "${GREEN}🎉 PLATFORM READY FOR VIDEO RECORDING! 🎉${NC}"
echo
echo -e "${BLUE}📚 Next Steps:${NC}"
echo "  1. Review PROMO_VIDEO_SCRIPT_v0.9.0.md for narration"
echo "  2. Follow VIDEO_SHOT_LIST_v0.9.0.md for scene-by-scene recording"
echo "  3. Use VIDEO_PRODUCTION_GUIDE_v0.9.0.md for technical setup"
echo "  4. Start recording at: http://localhost:8003"
echo
echo -e "${BLUE}🎯 Key URLs for Recording:${NC}"
echo "  🏠 Main Dashboard: http://localhost:8003/"
echo "  🔍 Contract Analyzer: http://localhost:8003/analyze"
echo "  📊 Sentiment Dashboard: http://localhost:8003/sentiment-dashboard"
echo "  🏆 Famous Contracts: http://localhost:8003/famous-contracts"
echo "  📈 Admin/Monitoring: http://localhost:8003/admin"
echo
echo -e "${GREEN}🚀 Ready to create an amazing promo video! 🎬${NC}"
