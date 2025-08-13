#!/bin/bash
# Run marketing/presentation demo
echo "🎯 Running Marketing Demo..."
docker compose exec app php artisan demo:daily --detailed --output-file=storage/logs/marketing-demo-$(date +%Y-%m-%d-%H-%M).json
echo ""
echo "🎉 Demo completed! Results saved for marketing use."
echo "📄 Use the generated JSON file for metrics and reporting."
