#!/bin/bash
# Run full comprehensive demo
echo "🚀 Running Full AI Blockchain Analytics Demo..."
docker compose exec app php artisan demo:daily --detailed --output-file=storage/logs/manual-demo-$(date +%Y-%m-%d-%H-%M).json
