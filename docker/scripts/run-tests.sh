#!/bin/bash

# CI test runner script

set -e

echo "🧪 Running AI Blockchain Analytics test suite..."

# Start CI services
echo "🚀 Starting CI services..."
docker-compose -f docker-compose.ci.yml up -d postgres redis

# Wait for services to be ready
echo "⏳ Waiting for database to be ready..."
timeout 60 bash -c 'until docker-compose -f docker-compose.ci.yml exec -T postgres pg_isready -U postgres -d testing; do sleep 2; done'

echo "⏳ Waiting for Redis to be ready..."
timeout 30 bash -c 'until docker-compose -f docker-compose.ci.yml exec -T redis redis-cli ping | grep -q PONG; do sleep 1; done'

# Build CI image if needed
echo "🔨 Building CI image..."
docker-compose -f docker-compose.ci.yml build app

echo "🗄️ Running database migrations..."
docker-compose -f docker-compose.ci.yml run --rm app php artisan migrate --force --seed

echo "🎨 Running code style checks (Pint)..."
docker-compose -f docker-compose.ci.yml run --rm app vendor/bin/pint --test --verbose

echo "🔍 Running static analysis (Psalm)..."
docker-compose -f docker-compose.ci.yml run --rm app vendor/bin/psalm --no-cache --show-info=false

echo "🏗️ Building frontend assets..."
docker-compose -f docker-compose.ci.yml run --rm app npm run build

echo "🧪 Running PHPUnit tests..."
docker-compose -f docker-compose.ci.yml run --rm app vendor/bin/phpunit --testdox --colors=always

echo "🧹 Cleaning up..."
docker-compose -f docker-compose.ci.yml down -v

echo "✅ All tests passed successfully!"