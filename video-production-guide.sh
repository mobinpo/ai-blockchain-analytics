#!/bin/bash

# 🎬 AI Blockchain Analytics - 2-Minute Promo Video Production Script
# This script helps set up and record the promotional video

set -e

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${BLUE}"
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║                                                              ║"
echo "║           AI Blockchain Analytics Video Production           ║"
echo "║                  2-Minute Promo Setup                       ║"
echo "║                                                              ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo -e "${NC}"

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

echo -e "${YELLOW}🎬 Setting up video production environment...${NC}"

# 1. Ensure platform is running optimally
echo -e "${BLUE}📋 Step 1: Platform Setup${NC}"
echo "Starting Docker services..."
docker compose up -d

echo "Waiting for services to be healthy..."
sleep 10

echo "Running demo data seeder..."
docker compose exec app php artisan db:seed --class=FamousContractsSeeder

echo "Clearing caches for optimal performance..."
docker compose exec app php artisan config:clear
docker compose exec app php artisan route:clear
docker compose exec app php artisan view:clear

# 2. Verify all endpoints are working
echo -e "${BLUE}📋 Step 2: Endpoint Verification${NC}"
echo "Testing main application..."
curl -s -o /dev/null -w "%{http_code}" http://localhost:8000 || echo -e "${RED}⚠️  Main app not responding${NC}"

echo "Testing API endpoints..."
curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/api/health || echo -e "${RED}⚠️  API not responding${NC}"

# 3. Set up recording environment
echo -e "${BLUE}📋 Step 3: Recording Environment${NC}"

# Check for screen recording tools
if command_exists ffmpeg; then
    echo -e "${GREEN}✅ FFmpeg found - ready for screen recording${NC}"
else
    echo -e "${RED}❌ FFmpeg not found. Install with: sudo apt install ffmpeg${NC}"
fi

if command_exists obs-studio; then
    echo -e "${GREEN}✅ OBS Studio found - professional recording ready${NC}"
else
    echo -e "${YELLOW}⚠️  OBS Studio not found. Install for best results: sudo apt install obs-studio${NC}"
fi

# 4. Browser setup instructions
echo -e "${BLUE}📋 Step 4: Browser Configuration${NC}"
echo "🌐 Browser Setup Instructions:"
echo "   1. Open Chrome/Chromium in full-screen mode"
echo "   2. Set resolution to 1920x1080"
echo "   3. Hide bookmarks bar (Ctrl+Shift+B)"
echo "   4. Close unnecessary tabs"
echo "   5. Disable notifications temporarily"

# 5. Generate recording checklist
echo -e "${BLUE}📋 Step 5: Recording Checklist Generated${NC}"

cat > recording-checklist.md << 'EOF'
# 🎬 Video Recording Checklist

## Pre-Recording Setup
- [ ] Docker services running and healthy
- [ ] Demo data seeded with famous contracts
- [ ] Browser in full-screen mode (1920x1080)
- [ ] System notifications disabled
- [ ] Microphone tested and configured
- [ ] Backup recording method ready

## Recording Segments (120 seconds total)

### Segment 1: Opening Hook (0-15s)
- [ ] Navigate to http://localhost:8000
- [ ] Show landing page with live analyzer
- [ ] Click Uniswap V3 Router contract
- [ ] **Narration**: "What if you could analyze any smart contract in seconds?"

### Segment 2: Problem Statement (15-30s)
- [ ] Show DeFi exploit statistics
- [ ] Highlight major losses (BSC Hub $570M, Euler $197M)
- [ ] **Narration**: "DeFi exploits cost over $3 billion in 2023..."

### Segment 3: Solution Demo (30-60s)
- [ ] Return to analyzer
- [ ] Paste Aave V3: 0x87870Bca3F3fD6335C3F4ce8392D69350B4fA4E2
- [ ] Show real-time analysis progress
- [ ] Display security report with risk score
- [ ] **Narration**: "Our AI instantly analyzes contracts..."

### Segment 4: Feature Showcase (60-90s)
- [ ] Navigate to /dashboard
- [ ] Show sentiment analysis chart
- [ ] Demonstrate multi-chain explorer
- [ ] Generate PDF report
- [ ] **Narration**: "Real-time sentiment from 5M+ sources..."

### Segment 5: Trust Signals (90-110s)
- [ ] Show live statistics (15,247 contracts analyzed)
- [ ] Highlight accuracy rate (98.7%)
- [ ] Display analysis speed metrics
- [ ] **Narration**: "Join thousands of developers..."

### Segment 6: Call to Action (110-120s)
- [ ] Return to landing page
- [ ] Highlight "Try Now - 100% Free"
- [ ] Show one-click analysis capability
- [ ] **Narration**: "Ready to secure your smart contracts?"

## Post-Recording
- [ ] Review footage for smooth transitions
- [ ] Check audio quality and sync
- [ ] Verify all text is readable
- [ ] Confirm no sensitive data visible
EOF

echo "📝 Recording checklist created: recording-checklist.md"

# 6. Create video file naming structure
echo -e "${BLUE}📋 Step 6: File Organization${NC}"
mkdir -p video-assets/{raw-footage,audio,final}
echo "📁 Created video-assets directory structure"

# 7. Generate FFmpeg recording command
echo -e "${BLUE}📋 Step 7: Recording Commands${NC}"

cat > record-video.sh << 'EOF'
#!/bin/bash
# Screen recording with FFmpeg
# Usage: ./record-video.sh [output-name]

OUTPUT_NAME=${1:-"ai-blockchain-promo-raw"}
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

echo "🎬 Starting screen recording..."
echo "Press Ctrl+C to stop recording"

ffmpeg -f x11grab -r 60 -s 1920x1080 -i :0.0 \
       -f pulse -ac 2 -i default \
       -c:v libx264 -preset ultrafast -crf 18 \
       -c:a aac -b:a 128k \
       "video-assets/raw-footage/${OUTPUT_NAME}_${TIMESTAMP}.mp4"

echo "✅ Recording saved to video-assets/raw-footage/"
EOF

chmod +x record-video.sh

# 8. Create OBS Studio scene configuration
cat > obs-scene-config.txt << 'EOF'
# OBS Studio Scene Configuration for AI Blockchain Analytics Promo

## Scene 1: Browser Capture
- Source: Browser Source or Window Capture
- Resolution: 1920x1080
- Frame Rate: 60 FPS
- Audio: Desktop Audio + Microphone

## Scene 2: Mobile View (if needed)
- Source: Browser Developer Tools (mobile view)
- Resolution: 375x812 (iPhone X size)

## Recording Settings
- Format: MP4
- Encoder: Hardware (NVENC) or Software (x264)
- Quality: High (CRF 18-20)
- Audio: 48kHz, Stereo

## Filters
- Noise Suppression: On microphone
- Compressor: On microphone  
- Color Correction: Slight contrast boost
EOF

echo "📋 OBS configuration guide created: obs-scene-config.txt"

# 9. Final setup verification
echo -e "${BLUE}📋 Step 8: Final Verification${NC}"

# Test famous contracts endpoint
echo "🧪 Testing famous contracts..."
RESPONSE=$(curl -s "http://localhost:8000/api/contracts/famous" | jq -r '.data[0].address' 2>/dev/null || echo "API_ERROR")

if [ "$RESPONSE" != "API_ERROR" ] && [ "$RESPONSE" != "null" ]; then
    echo -e "${GREEN}✅ Famous contracts API working${NC}"
else
    echo -e "${RED}❌ Famous contracts API issue - check logs${NC}"
fi

# Test demo analysis endpoint
echo "🧪 Testing demo analysis..."
ANALYSIS_RESPONSE=$(curl -s -X POST "http://localhost:8000/api/contracts/analyze-demo" \
    -H "Content-Type: application/json" \
    -d '{"input":"0xE592427A0AEce92De3Edee1F18E0157C05861564","network":"ethereum"}' | jq -r '.success' 2>/dev/null || echo "false")

if [ "$ANALYSIS_RESPONSE" = "true" ]; then
    echo -e "${GREEN}✅ Demo analysis API working${NC}"
else
    echo -e "${RED}❌ Demo analysis API issue - check implementation${NC}"
fi

echo
echo -e "${GREEN}🎉 Video production environment ready!${NC}"
echo
echo -e "${BLUE}📋 Next Steps:${NC}"
echo "1. Review recording-checklist.md for detailed shooting script"
echo "2. Use ./record-video.sh to start FFmpeg recording"
echo "3. Or configure OBS Studio using obs-scene-config.txt"
echo "4. Follow the 6-segment recording plan"
echo "5. Edit final video using your preferred editing software"
echo
echo -e "${BLUE}🎯 Key URLs for Recording:${NC}"
echo "   Landing Page: http://localhost:8000"
echo "   Dashboard: http://localhost:8000/dashboard"
echo "   North Star Demo: http://localhost:8000/north-star-demo"
echo
echo -e "${BLUE}📞 Support:${NC}"
echo "   If issues occur, run: docker compose logs"
echo "   For API problems, check: storage/logs/laravel.log"
echo
echo -e "${YELLOW}⏱️  Target: 2 minutes total runtime${NC}"
echo -e "${YELLOW}🎬 Ready to create an amazing promo video!${NC}"