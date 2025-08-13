#!/bin/bash
# Run system health check demo
echo "🔧 Running System Health Check Demo..."
docker compose exec app php artisan demo:daily --skip-analysis --skip-crawling --skip-reports --skip-onboarding --skip-famous --detailed
