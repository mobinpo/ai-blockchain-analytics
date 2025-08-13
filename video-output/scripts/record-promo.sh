#!/bin/bash

# AI Blockchain Analytics - Promotional Video Recording Script
# Generated: 2025-08-12T08:33:58.774828Z

echo "🎬 Starting AI Blockchain Analytics Promo Video Recording"
echo "=================================================="

# Configuration
RESOLUTION="1920x1080"
FPS="30"
DURATION="120"
OUTPUT_DIR="video-output"

# Create output directories
mkdir -p "$OUTPUT_DIR/raw-footage"
mkdir -p "$OUTPUT_DIR/final"

# Check for required tools
command -v ffmpeg >/dev/null 2>&1 || { echo "❌ ffmpeg required but not installed. Aborting." >&2; exit 1; }

echo "✅ Configuration loaded"
echo "   Resolution: $RESOLUTION"
echo "   FPS: $FPS"
echo "   Duration: $DURATION seconds"
echo ""

# Record each scene
SCENES=("hook" "solution" "analysis_demo" "sentiment_demo" "reporting_demo" "famous_contracts" "proof_points" "call_to_action")

for scene in "${SCENES[@]}"; do
    echo "🎥 Recording scene: $scene"
    echo "Press ENTER when ready to start recording $scene..."
    read -r
    
    # Start scene-specific recording
    ./record-$scene.sh
    
    echo "✅ Scene $scene recorded"
    echo ""
done

echo "🎉 All scenes recorded successfully!"
echo "Next steps:"
echo "1. Review raw footage in $OUTPUT_DIR/raw-footage/"
echo "2. Run post-production pipeline"
echo "3. Generate final video"
