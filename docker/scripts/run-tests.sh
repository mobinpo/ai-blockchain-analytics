#!/bin/bash

# Test runner script for Docker environment

echo "🧪 Running AI Blockchain Analytics test suite..."

# Build CI image
echo "📦 Building CI Docker image..."
docker build --target ci -t ai-blockchain-analytics:ci .

# Start test services
echo "🚀 Starting test services..."
docker compose -f docker-compose.ci.yml up -d postgres redis

# Wait for services
echo "⏳ Waiting for services to be ready..."
sleep 5

# Run all tests
echo "🔍 Running code style check (Pint)..."
docker compose -f docker-compose.ci.yml run --rm app vendor/bin/pint --test

echo "🔬 Running static analysis (Psalm)..."
docker compose -f docker-compose.ci.yml run --rm app sh -c "
  curl -Ls https://github.com/vimeo/psalm/releases/latest/download/psalm.phar -o psalm.phar && 
  chmod +x psalm.phar && 
  php psalm.phar --no-cache
"

echo "🧪 Running PHPUnit tests..."
docker compose -f docker-compose.ci.yml run --rm app sh -c "
  php artisan key:generate --force &&
  php artisan migrate --force &&
  vendor/bin/phpunit --testdox --colors=always
"

# Cleanup
echo "🧹 Cleaning up..."
docker compose -f docker-compose.ci.yml down -v

echo "✅ Test suite completed!"