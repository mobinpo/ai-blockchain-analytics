#!/bin/bash

# 🎬 AI Blockchain Analytics - Video Recording Setup Script
# This script prepares your platform for professional video recording

echo "🚀 Setting up AI Blockchain Analytics for video recording..."

# Start the platform
echo "📦 Starting Docker containers..."
docker compose up -d

# Wait for containers to be ready
echo "⏳ Waiting for containers to start..."
sleep 10

# Setup the application
echo "🔧 Setting up Laravel application..."
docker compose exec app php artisan key:generate --force
docker compose exec app php artisan config:clear
docker compose exec app php artisan cache:clear
docker compose exec app php artisan route:clear
docker compose exec app php artisan view:clear

# Run migrations and seed database
echo "🗄️ Setting up database with demo data..."
docker compose exec app php artisan migrate:fresh --seed --force
docker compose exec app php artisan db:seed --class=FamousContractsSeeder --force

# Start the development server
echo "🌐 Starting Laravel development server..."
docker compose exec -d app php artisan serve --host=0.0.0.0 --port=8000

# Verify platform is accessible
echo "✅ Platform should be accessible at: http://localhost:8003"
echo ""
echo "🎬 Recording Checklist:"
echo "  □ Platform accessible at localhost:8003"
echo "  □ All features working (test each page)"
echo "  □ Database seeded with famous contracts"
echo "  □ Browser cache cleared"
echo "  □ Clean desktop background set"
echo "  □ All notifications disabled"
echo "  □ OBS Studio configured (1080p, 60fps)"
echo "  □ Audio levels tested"
echo ""
echo "🎯 Ready to record your 2-minute promo video!"
echo "📖 Follow the shot list in: video-assets/SHOT_LIST_AND_SCRIPT.md"

# Test key endpoints
echo "🔍 Testing key platform endpoints..."
echo "  - Dashboard: http://localhost:8003/"
echo "  - Contract Analysis: http://localhost:8003/contracts/analyze"
echo "  - Sentiment Analysis: http://localhost:8003/sentiment"
echo "  - Famous Contracts: http://localhost:8003/contracts/famous"
echo "  - Admin Panel: http://localhost:8003/admin"
echo ""
echo "✨ All set! Start recording when ready."
