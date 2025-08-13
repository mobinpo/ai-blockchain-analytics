#!/bin/bash
# Quick health check for daily demo script

LOG_FILE="/var/log/ai-blockchain-analytics/demo-daily.log"
LAST_RUN=$(grep "Daily demo script completed successfully" "$LOG_FILE" | tail -1)

if [[ -n "$LAST_RUN" ]]; then
    echo "✅ Last successful run: $LAST_RUN"
    exit 0
else
    echo "❌ No recent successful runs found"
    exit 1
fi
