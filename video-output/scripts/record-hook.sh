#!/bin/bash

# Scene: Problem Hook
# Duration: 10 seconds

echo "🎬 Recording: Problem Hook"
echo "Duration: 10 seconds"
echo ""

OUTPUT_FILE="video-output/raw-footage/hook_$(date +%Y%m%d_%H%M%S).mp4"

# Scene-specific instructions
echo "📋 Scene Instructions:"
echo "   News headlines about DeFi exploits"
echo ""
echo "🎯 Visual Elements:"
echo "   - headlines"
echo "   - exploit_notifications"
echo "   - dollar_amounts"


echo ""
echo "🎤 Audio: urgent_professional_tone"
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
       -t 10 \
       "$OUTPUT_FILE"

echo "✅ Scene recorded: $OUTPUT_FILE"
