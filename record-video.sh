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
