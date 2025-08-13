#!/bin/bash

# AI Blockchain Analytics - Video Editing Pipeline
# Combines all scenes into final promotional video

echo "🎞️ Starting video editing pipeline..."

INPUT_DIR="video-output/raw-footage"
OUTPUT_DIR="video-output/final"
TEMP_DIR="video-output/temp"

# Create temp directory
mkdir -p "$TEMP_DIR"

# Scene order and timing
SCENES=(
    "hook:0:10"
    "solution:10:30" 
    "analysis_demo:30:45"
    "sentiment_demo:45:60"
    "reporting_demo:60:75"
    "famous_contracts:75:90"
    "proof_points:90:110"
    "call_to_action:110:120"
)

echo "📝 Creating scene list..."
SCENE_LIST="$TEMP_DIR/scenes.txt"
> "$SCENE_LIST"

for scene_info in "${SCENES[@]}"; do
    IFS=':' read -r scene start end <<< "$scene_info"
    
    # Find the latest recording for this scene
    SCENE_FILE=$(ls -t "$INPUT_DIR"/${scene}_*.mp4 2>/dev/null | head -n1)
    
    if [ -n "$SCENE_FILE" ]; then
        echo "file '$SCENE_FILE'" >> "$SCENE_LIST"
        echo "✅ Added scene: $scene ($SCENE_FILE)"
    else
        echo "❌ Missing scene: $scene"
    fi
done

echo ""
echo "🔧 Combining scenes..."

# Combine all scenes
ffmpeg -f concat -safe 0 -i "$SCENE_LIST" \
       -c copy \
       -avoid_negative_ts make_zero \
       "$OUTPUT_DIR/promo-video-raw.mp4"

echo "✅ Raw video created: $OUTPUT_DIR/promo-video-raw.mp4"

# Add transitions and effects
echo "✨ Adding transitions and effects..."

ffmpeg -i "$OUTPUT_DIR/promo-video-raw.mp4" \
       -vf "fade=in:0:30,fade=out:3570:30" \
       -c:a copy \
       "$OUTPUT_DIR/promo-video-with-effects.mp4"

echo "✅ Video with effects: $OUTPUT_DIR/promo-video-with-effects.mp4"

# Cleanup
rm -rf "$TEMP_DIR"

echo "🎉 Video editing complete!"
