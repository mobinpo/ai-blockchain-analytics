#!/bin/bash

# Development setup script for Docker environment

set -e

echo "🚀 Setting up AI Blockchain Analytics development environment..."

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker is not running. Please start Docker first."
    exit 1
fi

# Copy environment file if it doesn't exist
if [ ! -f .env ]; then
    echo "📄 Creating .env file from .env.example..."
    cp .env.example .env
fi

# Build and start services
echo "🔨 Building Docker images..."
docker-compose build --no-cache

echo "🚀 Starting services..."
docker-compose up -d

# Wait for services to be ready
echo "⏳ Waiting for services to be ready..."
timeout 60 bash -c 'until docker-compose exec -T postgres pg_isready -U postgres; do sleep 2; done'
timeout 30 bash -c 'until docker-compose exec -T redis redis-cli ping | grep -q PONG; do sleep 1; done'

# Install dependencies and setup Laravel
echo "📦 Installing PHP dependencies..."
docker-compose exec app composer install --no-interaction

echo "🔑 Generating application key..."
docker-compose exec app php artisan key:generate --force

echo "🗄️ Running database migrations..."
docker-compose exec app php artisan migrate --force

echo "🌱 Seeding database..."
docker-compose exec app php artisan db:seed --force

echo "🏗️ Building frontend assets..."
docker-compose exec vite npm run build

echo "✅ Development environment is ready!"
echo ""
echo "🌐 Application: http://localhost:8000"
echo "📧 MailHog: http://localhost:8025"
echo "🔍 Redis: localhost:6379"
echo "🐘 PostgreSQL: localhost:5432"
echo ""
echo "📋 Useful commands:"
echo "  docker-compose logs -f app      # View application logs"
echo "  docker-compose exec app bash    # Access application container"
echo "  docker-compose down             # Stop all services"
echo "  docker-compose down -v          # Stop services and remove volumes"