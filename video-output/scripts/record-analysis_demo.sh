#!/bin/bash

# Scene: Contract Analysis Demo
# Duration: 15 seconds

echo "🎬 Recording: Contract Analysis Demo"
echo "Duration: 15 seconds"
echo ""

OUTPUT_FILE="video-output/raw-footage/analysis_demo_$(date +%Y%m%d_%H%M%S).mp4"

# Scene-specific instructions
echo "📋 Scene Instructions:"
echo "   Live contract analysis demonstration"
echo ""
echo "🎯 Visual Elements:"
echo "   - contract_input"
echo "   - analysis_progress"
echo "   - risk_score"
echo "   - findings"


echo ""
echo "🎤 Audio: technical_explanation"
echo ""
echo "Press ENTER to start recording in 3 seconds..."
read -r

echo "Starting in 3..."
sleep 1
echo "2..."
sleep 1
echo "1..."
sleep 1
echo "🔴 RECORDING!"

# Use ffmpeg to record screen (adjust input source as needed)
# This is a template - adjust for your specific recording setup
ffmpeg -f x11grab -s 1920x1080 -r 30 -i :0.0 \
       -f pulse -i default \
       -c:v libx264 -preset ultrafast -crf 18 \
       -c:a aac -b:a 192k \
       -t 15 \
       "$OUTPUT_FILE"

echo "✅ Scene recorded: $OUTPUT_FILE"
