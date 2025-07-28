#!/bin/bash

# Development setup script for Docker environment

echo "🚀 Setting up AI Blockchain Analytics development environment..."

# Build and start services
echo "📦 Building and starting Docker services..."
docker compose up --build -d

# Wait for services to be ready
echo "⏳ Waiting for services to be ready..."
sleep 10

# Check if services are healthy
echo "🔍 Checking service health..."
docker compose ps

# Install dependencies if not already installed
echo "📚 Installing dependencies..."
docker compose exec app composer install --no-interaction

# Generate application key if needed
echo "🔑 Setting up application key..."
docker compose exec app php artisan key:generate --force

# Run migrations
echo "🗄️ Running database migrations..."
docker compose exec app php artisan migrate --force

# Show running services
echo "✅ Development environment is ready!"
echo ""
echo "🌐 Application: http://localhost:8000"
echo "📧 MailHog: http://localhost:8025"
echo "📊 Vite Dev Server: http://localhost:5173"
echo ""
echo "🔧 Useful commands:"
echo "  docker compose logs -f app     # View app logs"
echo "  docker compose exec app bash  # SSH into app container"
echo "  docker compose down           # Stop all services"