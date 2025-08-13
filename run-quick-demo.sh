#!/bin/bash
# Run quick demo (skip time-consuming tasks)
echo "⚡ Running Quick AI Blockchain Analytics Demo..."
docker compose exec app php artisan demo:daily --skip-crawling --skip-reports --detailed --output-file=storage/logs/quick-demo-$(date +%Y-%m-%d-%H-%M).json
