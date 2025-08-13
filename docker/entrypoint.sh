#!/bin/bash
set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}🚀 Starting AI Blockchain Analytics with RoadRunner${NC}"

# Wait for database to be ready
echo -e "${YELLOW}⏳ Waiting for database connection...${NC}"
while ! php artisan db:monitor --max-tries=1 > /dev/null 2>&1; do
    echo -e "${YELLOW}Database not ready, waiting 2 seconds...${NC}"
    sleep 2
done
echo -e "${GREEN}✅ Database connection established${NC}"

# Wait for Redis to be ready
echo -e "${YELLOW}⏳ Waiting for Redis connection...${NC}"
while ! timeout 2 bash -c "</dev/tcp/${REDIS_HOST}/${REDIS_PORT}" > /dev/null 2>&1; do
    echo -e "${YELLOW}Redis not ready, waiting 2 seconds...${NC}"
    sleep 2
done
echo -e "${GREEN}✅ Redis connection established${NC}"

# Generate application key if not exists
if [ -z "$APP_KEY" ]; then
    echo -e "${YELLOW}🔑 Generating application key...${NC}"
    php artisan key:generate --force
fi

# Run database migrations
echo -e "${YELLOW}🔄 Running database migrations...${NC}"
php artisan migrate --force

# Clear and cache configurations
echo -e "${YELLOW}⚡ Optimizing application...${NC}"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Set up storage link
if [ ! -L "public/storage" ]; then
    echo -e "${YELLOW}🔗 Creating storage link...${NC}"
    php artisan storage:link
fi

# Warm up caches
echo -e "${YELLOW}🔥 Warming up caches...${NC}"
php artisan cache:maintenance --warm > /dev/null 2>&1 || echo "Cache warming completed (with warnings)"

# Set proper permissions
echo -e "${YELLOW}🔒 Setting permissions...${NC}"
chmod -R 775 storage bootstrap/cache

# Create necessary directories
mkdir -p storage/logs
mkdir -p storage/framework/{cache,sessions,views}
mkdir -p storage/app/public/pdfs

echo -e "${GREEN}🎉 Application ready! Starting RoadRunner...${NC}"

# Execute the main command
exec "$@"
