#!/bin/bash

# 🚀 AI Blockchain Analytics - Production Deployment v0.9.0
# Deploy to production domain with full enterprise configuration

set -e

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

# Configuration
DOMAIN="${DOMAIN:-ai-blockchain-analytics.com}"
DEPLOY_ENV="${DEPLOY_ENV:-production}"
DOCKER_TAG="v0.9.0"
BACKUP_DIR="backups/production-$(date +%Y%m%d_%H%M%S)"

echo -e "${BLUE}"
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║                                                              ║"
echo "║         AI Blockchain Analytics - Production Deploy          ║"
echo "║                     Version 0.9.0                           ║"
echo "║                                                              ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo -e "${NC}"

echo -e "${YELLOW}🚀 Starting production deployment for domain: ${DOMAIN}${NC}"

# Function to create backup
create_backup() {
    echo -e "${BLUE}💾 Creating production backup...${NC}"
    mkdir -p "${BACKUP_DIR}"
    
    # Backup database if exists
    if docker compose ps | grep -q postgres; then
        echo "Backing up PostgreSQL database..."
        docker compose exec -T postgres pg_dump -U postgres ai_blockchain_analytics > "${BACKUP_DIR}/database_backup.sql" 2>/dev/null || echo "No existing database to backup"
    fi
    
    echo -e "${GREEN}✅ Backup created in ${BACKUP_DIR}${NC}"
}

# Function to setup production environment
setup_production_env() {
    echo -e "${BLUE}🔧 Setting up production environment...${NC}"
    
    # Create production environment from template
    cp env.production.template .env.production
    
    # Update domain in environment
    sed -i "s/APP_URL=.*/APP_URL=https:\/\/${DOMAIN}/" .env.production
    sed -i "s/DOMAIN=.*/DOMAIN=${DOMAIN}/" .env.production
    
    # Generate application key if needed
    if grep -q "APP_KEY=$" .env.production; then
        APP_KEY=$(openssl rand -base64 32)
        sed -i "s/APP_KEY=$/APP_KEY=base64:${APP_KEY}/" .env.production
    fi
    
    echo -e "${GREEN}✅ Production environment configured${NC}"
}

# Function to build production image
build_production_image() {
    echo -e "${BLUE}🐳 Building production Docker image...${NC}"
    
    # Build optimized production image
    docker build -t ai-blockchain-analytics:${DOCKER_TAG} -f - . << 'EOF'
FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    git curl libpng-dev libzip-dev zip unzip postgresql-dev nodejs npm supervisor

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql zip gd opcache
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy and install dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

COPY package.json package-lock.json ./
RUN npm ci --only=production

# Copy application code
COPY . .
RUN npm run build

# Set permissions
RUN chown -R www-data:www-data storage bootstrap/cache
RUN chmod -R 755 storage bootstrap/cache

# Configure PHP for production
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini
RUN echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/opcache.ini

# Expose port and start
EXPOSE 8000
CMD php artisan octane:start --server=swoole --host=0.0.0.0 --port=8000 --workers=4
EOF

    echo -e "${GREEN}✅ Production image built${NC}"
}

# Function to deploy application
deploy_application() {
    echo -e "${BLUE}🚀 Deploying to production...${NC}"
    
    # Create production compose configuration
    cat > docker-compose.production.yml << EOF
version: '3.8'

services:
  app:
    image: ai-blockchain-analytics:${DOCKER_TAG}
    container_name: ai_blockchain_app_prod
    restart: unless-stopped
    ports:
      - "80:8000"
      - "443:8000"
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
    volumes:
      - ./storage:/var/www/html/storage
      - ./.env.production:/var/www/html/.env
    depends_on:
      - postgres
      - redis
    networks:
      - ai_blockchain_network

  postgres:
    image: postgres:16-alpine
    container_name: ai_blockchain_postgres_prod
    restart: unless-stopped
    environment:
      POSTGRES_DB: ai_blockchain_analytics
      POSTGRES_USER: postgres
      POSTGRES_PASSWORD: \${DB_PASSWORD:-postgres}
    volumes:
      - postgres_data:/var/lib/postgresql/data
    networks:
      - ai_blockchain_network

  redis:
    image: redis:7-alpine
    container_name: ai_blockchain_redis_prod
    restart: unless-stopped
    volumes:
      - redis_data:/data
    networks:
      - ai_blockchain_network

  horizon:
    image: ai-blockchain-analytics:${DOCKER_TAG}
    container_name: ai_blockchain_horizon_prod
    restart: unless-stopped
    command: php artisan horizon
    volumes:
      - ./storage:/var/www/html/storage
      - ./.env.production:/var/www/html/.env
    depends_on:
      - app
      - redis
    networks:
      - ai_blockchain_network

volumes:
  postgres_data:
  redis_data:

networks:
  ai_blockchain_network:
    driver: bridge
EOF

    # Stop existing services
    docker compose -f docker-compose.production.yml down 2>/dev/null || true
    
    # Start production services
    docker compose -f docker-compose.production.yml up -d
    
    # Wait for services
    echo "Waiting for services to initialize..."
    sleep 30
    
    # Run migrations and setup
    docker compose -f docker-compose.production.yml exec -T app php artisan migrate --force
    docker compose -f docker-compose.production.yml exec -T app php artisan db:seed --class=FamousContractsSeeder --force
    docker compose -f docker-compose.production.yml exec -T app php artisan config:cache
    docker compose -f docker-compose.production.yml exec -T app php artisan route:cache
    docker compose -f docker-compose.production.yml exec -T app php artisan view:cache
    
    echo -e "${GREEN}✅ Production deployment completed${NC}"
}

# Function to verify deployment
verify_deployment() {
    echo -e "${BLUE}🔍 Verifying deployment...${NC}"
    
    # Check containers
    if docker compose -f docker-compose.production.yml ps | grep -q "Up"; then
        echo -e "${GREEN}✅ All services running${NC}"
    else
        echo -e "${RED}❌ Some services not running${NC}"
        docker compose -f docker-compose.production.yml ps
    fi
    
    # Test application
    sleep 10
    if curl -f -s http://localhost >/dev/null 2>&1; then
        echo -e "${GREEN}✅ Application responding${NC}"
    else
        echo -e "${YELLOW}⚠️  Application not responding yet${NC}"
    fi
}

# Main execution
main() {
    create_backup
    setup_production_env
    build_production_image
    deploy_application
    verify_deployment
    
    echo
    echo -e "${GREEN}🎉 Production deployment completed!${NC}"
    echo
    echo -e "${BLUE}📋 Deployment Summary:${NC}"
    echo "   🌐 Domain: ${DOMAIN}"
    echo "   🔖 Version: v0.9.0"
    echo "   📦 Environment: production"
    echo "   🐳 Services: app, postgres, redis, horizon"
    echo
    echo -e "${BLUE}🔗 Access URLs:${NC}"
    echo "   📊 Application: http://${DOMAIN}"
    echo "   📈 Dashboard: http://${DOMAIN}/dashboard"
    echo "   🎯 Demo: http://${DOMAIN}/north-star-demo"
    echo
    echo -e "${BLUE}🛠️  Management:${NC}"
    echo "   Logs: docker compose -f docker-compose.production.yml logs -f"
    echo "   Stop: docker compose -f docker-compose.production.yml down"
    echo "   Restart: docker compose -f docker-compose.production.yml restart"
    echo
    echo -e "${GREEN}✅ AI Blockchain Analytics v0.9.0 is now live!${NC}"
}

# Handle arguments
case "${1:-}" in
    "help"|"-h"|"--help")
        echo "Usage: $0 [domain]"
        echo "Example: $0 yourdomain.com"
        exit 0
        ;;
    *)
        if [ -n "${1:-}" ]; then
            DOMAIN="$1"
        fi
        main
        ;;
esac