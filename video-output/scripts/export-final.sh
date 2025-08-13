#!/bin/bash

# Export final video in multiple formats

INPUT_FILE="video-output/final/promo-video-with-effects.mp4"
OUTPUT_DIR="video-output/final"

echo "📤 Exporting final video formats..."

# High quality version (YouTube/Web)
ffmpeg -i "$INPUT_FILE" \
       -c:v libx264 -preset slow -crf 18 \
       -c:a aac -b:a 192k \
       -movflags +faststart \
       "$OUTPUT_DIR/ai-blockchain-analytics-promo-hq.mp4"

# Mobile optimized version
ffmpeg -i "$INPUT_FILE" \
       -vf scale=1280:720 \
       -c:v libx264 -preset fast -crf 23 \
       -c:a aac -b:a 128k \
       -movflags +faststart \
       "$OUTPUT_DIR/ai-blockchain-analytics-promo-mobile.mp4"

# Social media version (60 seconds)
ffmpeg -i "$INPUT_FILE" \
       -t 60 \
       -c:v libx264 -preset fast -crf 23 \
       -c:a aac -b:a 128k \
       -movflags +faststart \
       "$OUTPUT_DIR/ai-blockchain-analytics-promo-social.mp4"

# GIF preview (first 10 seconds)
ffmpeg -i "$INPUT_FILE" \
       -t 10 -vf scale=800:450 \
       -r 15 \
       "$OUTPUT_DIR/ai-blockchain-analytics-preview.gif"

echo "✅ Export complete!"
echo "Files available in: $OUTPUT_DIR"
echo "   - ai-blockchain-analytics-promo-hq.mp4 (Full quality)"
echo "   - ai-blockchain-analytics-promo-mobile.mp4 (Mobile optimized)"
echo "   - ai-blockchain-analytics-promo-social.mp4 (60s social media)"
echo "   - ai-blockchain-analytics-preview.gif (Preview GIF)"
