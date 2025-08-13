#!/bin/bash

# AI Blockchain Analytics - Demo Monitoring Script
# Monitors the daily demo execution and sends alerts

CURRENT_DIR=$(pwd)
LOG_DIR="$CURRENT_DIR/storage/logs"
TODAY=$(date +%Y-%m-%d)

echo "🔍 AI Blockchain Analytics - Demo Monitoring"
echo "==========================================="

# Check if today's demo ran
if [ -f "$LOG_DIR/daily-demo-$TODAY.json" ]; then
    echo "✅ Today's demo completed successfully"
    
    # Extract key metrics from the demo results
    if command -v jq &> /dev/null; then
        TASKS_COMPLETED=$(jq -r '.tasks_completed // "N/A"' "$LOG_DIR/daily-demo-$TODAY.json")
        TASKS_FAILED=$(jq -r '.tasks_failed // "N/A"' "$LOG_DIR/daily-demo-$TODAY.json")
        EXECUTION_TIME=$(jq -r '.performance_metrics.execution_time // "N/A"' "$LOG_DIR/daily-demo-$TODAY.json")
        
        echo "📊 Demo Metrics:"
        echo "   • Tasks Completed: $TASKS_COMPLETED"
        echo "   • Tasks Failed: $TASKS_FAILED"
        echo "   • Execution Time: $EXECUTION_TIME"
        
        if [ "$TASKS_FAILED" != "0" ] && [ "$TASKS_FAILED" != "N/A" ]; then
            echo "⚠️  Warning: Some tasks failed during demo execution"
        fi
    else
        echo "📄 Demo results file exists but jq not available for parsing"
    fi
else
    echo "❌ Today's demo has not run yet or failed"
    echo "📅 Expected file: $LOG_DIR/daily-demo-$TODAY.json"
fi

# Check recent log files
echo ""
echo "📋 Recent demo files:"
ls -la "$LOG_DIR"/daily-demo-*.json 2>/dev/null | tail -5 || echo "   No demo files found"

# Check disk usage
echo ""
echo "💾 Storage usage:"
du -sh "$LOG_DIR" 2>/dev/null || echo "   Unable to check storage usage"

# Check for errors in recent logs
echo ""
echo "🚨 Recent errors (last 24 hours):"
find "$LOG_DIR" -name "*.log" -mtime -1 -exec grep -l "ERROR\|FAILED\|Exception" {} \; 2>/dev/null | head -3 | while read file; do
    echo "   📄 $file"
    grep "ERROR\|FAILED\|Exception" "$file" | tail -2 | sed 's/^/      /'
done || echo "   No recent errors found"

