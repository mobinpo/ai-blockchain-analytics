#!/bin/bash

# =============================================================================
# Quick Artillery Load Test Launcher for Docker
# =============================================================================
# This script makes it easy to run the 500 concurrent analysis load test

set -euo pipefail

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
PURPLE='\033[0;35m'
NC='\033[0m'

echo -e "${PURPLE}🎯 AI Blockchain Analytics - Artillery Load Test Launcher${NC}"
echo -e "${PURPLE}================================================================${NC}"
echo

# Check if Docker Compose is running
echo -e "${BLUE}[STEP 1]${NC} Checking Docker Compose services..."
if ! docker compose ps --services --filter status=running | grep -q "app"; then
    echo -e "${RED}❌ Docker Compose 'app' service is not running${NC}"
    echo -e "${YELLOW}Please start your Docker services first:${NC}"
    echo "  docker compose up -d"
    exit 1
fi
echo -e "${GREEN}✅ Docker Compose services are running${NC}"
echo

# Check if the app is responding
echo -e "${BLUE}[STEP 2]${NC} Checking application health..."
if ! curl -s --connect-timeout 10 "http://localhost:8003" > /dev/null; then
    echo -e "${RED}❌ Application is not responding on port 8003${NC}"
    echo -e "${YELLOW}Please ensure your Laravel app is running inside the container${NC}"
    exit 1
fi
echo -e "${GREEN}✅ Application is responding on port 8003${NC}"
echo

# Show test overview
echo -e "${BLUE}[STEP 3]${NC} Test Overview:"
echo "  🎯 Target: 500 concurrent AI blockchain analyses"
echo "  ⏱️  Duration: ~20 minutes"
echo "  🚀 Phases: Warmup → Ramp-up → Sustained Load → Cool-down"
echo "  📊 Scenarios: Sentiment Analysis, Verification, PDF Generation, Dashboard"
echo

# Ask for confirmation
echo -e "${YELLOW}⚠️  This test will generate significant load on your system${NC}"
read -p "Continue with the load test? (y/N): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${BLUE}Load test cancelled${NC}"
    exit 0
fi

echo
echo -e "${BLUE}[STEP 4]${NC} Starting Artillery load test..."
echo -e "${GREEN}Running command: docker compose exec app bash /var/www/load-tests/run-docker-500-test.sh${NC}"
echo

# Run the load test
docker compose exec app bash /var/www/load-tests/run-docker-500-test.sh

echo
echo -e "${GREEN}🎉 Artillery load test completed!${NC}"
echo
echo -e "${BLUE}📋 To copy reports to your host machine:${NC}"
echo "  docker compose cp app:/var/www/load-tests/reports ./"
echo
echo -e "${BLUE}📊 To view real-time Docker stats during future tests:${NC}"
echo "  docker stats"
echo 