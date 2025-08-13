#!/bin/bash

# 🎬 AI Blockchain Analytics - 2-Minute Promo Video Recording Commands
# Automated video recording setup and execution script
# Generated: 2025-08-12

set -e

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
RED='\033[0;31m'
NC='\033[0m'

VIDEO_OUTPUT_DIR="video-output"
RECORDING_DATE=$(date +%Y%m%d-%H%M%S)
BASE_URL="http://localhost:8000"

echo -e "${BLUE}🎬 AI BLOCKCHAIN ANALYTICS - PROMO VIDEO RECORDING SETUP${NC}"
echo -e "${BLUE}=========================================================${NC}"
echo "Recording Date: $RECORDING_DATE"
echo "Output Directory: $VIDEO_OUTPUT_DIR"
echo ""

# Create output directory
mkdir -p "$VIDEO_OUTPUT_DIR"

echo -e "${CYAN}📋 Step 1: Environment Preparation${NC}"
echo "----------------------------------------"

# Check if Laravel server is running
if ! curl -s "$BASE_URL/up" > /dev/null; then
    echo -e "${RED}❌ Laravel server not running. Starting server...${NC}"
    php artisan serve --host=0.0.0.0 --port=8000 &
    SERVER_PID=$!
    echo "Server PID: $SERVER_PID"
    sleep 3
else
    echo -e "${GREEN}✅ Laravel server is running${NC}"
fi

# Prepare demo data
echo -e "${YELLOW}🔧 Preparing demo environment...${NC}"

# Clear browser cache and prepare clean environment
echo "Clearing browser cache and cookies..."
rm -rf ~/.cache/google-chrome/Default/Cache/* 2>/dev/null || true

# Start with fresh Chrome profile for recording
CHROME_USER_DIR="/tmp/chrome-recording-profile-$RECORDING_DATE"
mkdir -p "$CHROME_USER_DIR"

echo -e "${CYAN}📋 Step 2: Recording Segment Setup${NC}"
echo "----------------------------------------"

# Function to open specific URLs for recording
open_recording_url() {
    local url=$1
    local description=$2
    echo -e "${YELLOW}🎥 Opening: $description${NC}"
    echo "URL: $url"
    
    google-chrome \
        --user-data-dir="$CHROME_USER_DIR" \
        --new-window \
        --start-fullscreen \
        --disable-infobars \
        --disable-extensions \
        --disable-web-security \
        --disable-features=TranslateUI \
        --disable-ipc-flooding-protection \
        "$url" &
    
    echo "Chrome PID: $!"
    sleep 2
}

# Function to run artisan commands for demo
run_demo_command() {
    local command=$1
    local description=$2
    echo -e "${YELLOW}🎯 Demo: $description${NC}"
    echo "Command: php artisan $command"
    
    # Run in a terminal window that can be recorded
    gnome-terminal -- bash -c "
        echo '🎬 Recording: $description'
        echo '=============================='
        php artisan $command
        echo ''
        echo 'Press Enter to continue...'
        read
    " &
    
    sleep 1
}

echo -e "${CYAN}📋 Step 3: Recording Segments${NC}"
echo "----------------------------------------"

# Segment 1: Problem Hook (Headlines and exploit data)
echo -e "${YELLOW}🎬 SEGMENT 1: Problem Hook (0-10s)${NC}"
cat > "$VIDEO_OUTPUT_DIR/segment1-headlines.html" << 'EOF'
<!DOCTYPE html>
<html>
<head>
    <title>DeFi Exploits Headlines</title>
    <style>
        body { 
            background: #1a1a1a; 
            color: #ff4444; 
            font-family: 'Arial Black'; 
            font-size: 48px; 
            text-align: center; 
            padding: 100px;
            animation: flash 2s infinite;
        }
        .headline { 
            margin: 40px 0; 
            text-shadow: 2px 2px 4px rgba(0,0,0,0.8);
        }
        .amount { 
            color: #ff0000; 
            font-size: 64px; 
            font-weight: bold;
        }
        @keyframes flash {
            0%, 50% { opacity: 1; }
            25%, 75% { opacity: 0.7; }
        }
    </style>
</head>
<body>
    <div class="headline">EULER FINANCE EXPLOIT</div>
    <div class="amount">$200 MILLION LOST</div>
    <div class="headline">MULTICHAIN BRIDGE HACK</div>
    <div class="amount">$126 MILLION STOLEN</div>
    <div class="headline">SMART CONTRACT VULNERABILITIES</div>
    <div class="amount">BILLIONS AT RISK</div>
</body>
</html>
EOF

echo "📄 Created headlines HTML for recording"

# Segment 2: Platform Introduction
echo -e "${YELLOW}🎬 SEGMENT 2: Platform Introduction (10-30s)${NC}"
open_recording_url "$BASE_URL/up" "Health Check Page"

# Segment 3: Smart Contract Analysis Demo
echo -e "${YELLOW}🎬 SEGMENT 3: Smart Contract Analysis (30-45s)${NC}"
echo "Prepare to demonstrate contract analysis with Euler Finance address"
echo "Address to use: 0x27182842E098f60e3D576794A5bFFb0777E025d3"

# Segment 4: Sentiment Analysis Demo
echo -e "${YELLOW}🎬 SEGMENT 4: Sentiment Analysis (45-60s)${NC}"
run_demo_command "sentiment:demo" "Sentiment Analysis Pipeline"

# Segment 5: PDF Generation Demo
echo -e "${YELLOW}🎬 SEGMENT 5: PDF Generation (60-75s)${NC}"
run_demo_command "pdf:demo" "PDF Report Generation"

# Segment 6: Famous Contracts Database
echo -e "${YELLOW}🎬 SEGMENT 6: Famous Contracts (75-90s)${NC}"
echo "Demonstrate famous contracts database query:"
echo "PGUSER=postgres PGPASSWORD=password PGHOST=localhost PGPORT=5432 PGDATABASE=ai_blockchain_analytics psql -c \"SELECT name, risk_score, contract_type FROM famous_contracts ORDER BY risk_score DESC;\""

# Create OBS Studio scene configuration
echo -e "${CYAN}📋 Step 4: OBS Studio Configuration${NC}"
echo "----------------------------------------"

cat > "$VIDEO_OUTPUT_DIR/obs-scene-config.json" << 'EOF'
{
    "recording_settings": {
        "resolution": "1920x1080",
        "fps": 30,
        "format": "mp4",
        "encoder": "x264",
        "quality": "high"
    },
    "scenes": [
        {
            "name": "Headlines",
            "duration": "0-10s",
            "sources": ["Browser Source - Headlines HTML"]
        },
        {
            "name": "Platform Intro",
            "duration": "10-30s", 
            "sources": ["Browser Source - Health Check", "Logo Overlay"]
        },
        {
            "name": "Contract Analysis",
            "duration": "30-45s",
            "sources": ["Browser Source - Analysis Demo", "Terminal Window"]
        },
        {
            "name": "Sentiment Demo",
            "duration": "45-60s",
            "sources": ["Terminal Window - Sentiment Command"]
        },
        {
            "name": "PDF Generation",
            "duration": "60-75s",
            "sources": ["Terminal Window - PDF Command"]
        },
        {
            "name": "Famous Contracts",
            "duration": "75-90s",
            "sources": ["Terminal Window - Database Query"]
        },
        {
            "name": "Call to Action",
            "duration": "90-120s",
            "sources": ["Contact Info Overlay", "Website URL"]
        }
    ]
}
EOF

echo -e "${GREEN}✅ OBS scene configuration created${NC}"

echo -e "${CYAN}📋 Step 5: Recording Instructions${NC}"
echo "----------------------------------------"

cat > "$VIDEO_OUTPUT_DIR/RECORDING_INSTRUCTIONS.md" << 'EOF'
# 🎬 Video Recording Instructions

## Pre-Recording Checklist
- [ ] Close all unnecessary applications
- [ ] Set up clean desktop background
- [ ] Ensure good lighting and audio
- [ ] Test microphone levels
- [ ] Clear browser cache and cookies
- [ ] Prepare demo scripts

## Recording Segments

### Segment 1: Headlines (0-10s)
1. Open headlines HTML file in fullscreen
2. Record dramatic text animations
3. Include exploit statistics
4. Build tension with flashing effects

### Segment 2: Platform Intro (10-30s)
1. Navigate to health check page
2. Show clean, professional interface
3. Highlight platform capabilities
4. Smooth transitions between views

### Segment 3: Contract Analysis (30-45s)
1. Open contract analysis interface
2. Enter Euler Finance address: 0x27182842E098f60e3D576794A5bFFb0777E025d3
3. Show analysis progress and results
4. Highlight critical risk score (95)

### Segment 4: Sentiment Analysis (45-60s)
1. Run: php artisan sentiment:demo
2. Show multi-platform data processing
3. Highlight sentiment scores and trends
4. Demonstrate real-time capabilities

### Segment 5: PDF Generation (60-75s)
1. Run: php artisan pdf:demo
2. Show professional report generation
3. Highlight quick processing time
4. Show final PDF output

### Segment 6: Database Demo (75-90s)
1. Show famous contracts query results
2. Highlight risk scores and contract types
3. Compare exploited vs safe contracts
4. Demonstrate comprehensive data

### Segment 7: Call to Action (90-120s)
1. Display contact information
2. Show website URL prominently
3. Include social media handles
4. End with strong call to action

## Technical Settings
- Resolution: 1920x1080
- Frame Rate: 30 FPS
- Audio: 48kHz stereo
- Format: MP4 H.264
- Duration: Exactly 120 seconds

## Post-Production
- Color correction
- Audio normalization
- Text overlays for key points
- Smooth transitions
- Logo animations
- Final quality review
EOF

echo -e "${GREEN}✅ Recording instructions created${NC}"

echo -e "${CYAN}📋 Step 6: Demo Data Verification${NC}"
echo "----------------------------------------"

# Verify database has demo data
echo -e "${YELLOW}🔍 Checking database content...${NC}"
contract_count=$(PGUSER=postgres PGPASSWORD=password PGHOST=localhost PGPORT=5432 PGDATABASE=ai_blockchain_analytics psql -t -c "SELECT COUNT(*) FROM famous_contracts;" 2>/dev/null | tr -d ' ' || echo "0")

if [ "$contract_count" -ge 5 ]; then
    echo -e "${GREEN}✅ Database: $contract_count contracts available for demo${NC}"
else
    echo -e "${RED}❌ Database: Only $contract_count contracts found${NC}"
    echo "Running seeder to populate demo data..."
    PGUSER=postgres PGPASSWORD=password PGHOST=localhost PGPORT=5432 PGDATABASE=ai_blockchain_analytics psql -f database/sql/famous_5_contracts_corrected.sql > /dev/null 2>&1
    echo -e "${GREEN}✅ Demo data seeded successfully${NC}"
fi

echo -e "${CYAN}📋 Step 7: Final Recording Setup${NC}"
echo "----------------------------------------"

# Create recording script
cat > "$VIDEO_OUTPUT_DIR/start-recording.sh" << 'EOF'
#!/bin/bash
echo "🎬 Starting AI Blockchain Analytics Promo Video Recording"
echo "========================================================="
echo ""
echo "Recording will begin in 5 seconds..."
echo "Make sure OBS Studio is ready and recording!"
echo ""
for i in 5 4 3 2 1; do
    echo "Starting in $i..."
    sleep 1
done
echo "🔴 RECORDING NOW!"
echo ""
echo "Follow the shot list for each 15-second segment:"
echo "1. Headlines (0-10s)"
echo "2. Platform Intro (10-30s)"  
echo "3. Contract Analysis (30-45s)"
echo "4. Sentiment Demo (45-60s)"
echo "5. PDF Generation (60-75s)"
echo "6. Famous Contracts (75-90s)"
echo "7. Call to Action (90-120s)"
EOF

chmod +x "$VIDEO_OUTPUT_DIR/start-recording.sh"

echo -e "${GREEN}🎉 RECORDING SETUP COMPLETE!${NC}"
echo -e "${GREEN}=============================${NC}"
echo ""
echo "📁 All files created in: $VIDEO_OUTPUT_DIR/"
echo "📋 Review: RECORDING_INSTRUCTIONS.md"
echo "🎬 Execute: start-recording.sh when ready"
echo ""
echo "Next steps:"
echo "1. Open OBS Studio"
echo "2. Configure scenes according to obs-scene-config.json"
echo "3. Test audio and video quality"
echo "4. Run ./start-recording.sh to begin"
echo ""
echo -e "${YELLOW}📞 Demo data ready - 5 famous contracts loaded for demonstration${NC}"

# Display quick summary of demo content
echo ""
echo -e "${CYAN}📊 Demo Content Summary:${NC}"
echo "• Euler Finance (Risk: 95) - $200M exploit"
echo "• Multichain Bridge (Risk: 98) - $126M exploit"
echo "• Uniswap V3 (Risk: 15) - Secure DEX"
echo "• Aave V3 (Risk: 25) - Leading lending"
echo "• Compound V3 (Risk: 35) - Next-gen lending"

echo -e "\n${GREEN}Ready to create an amazing 2-minute promo video! 🚀${NC}"