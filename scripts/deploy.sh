#!/bin/bash

# AI Blockchain Analytics - Deployment Script
# This script handles deployment to different environments

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
APP_NAME="ai-blockchain-analytics"
DOCKER_REGISTRY="ghcr.io"
IMAGE_NAME="${DOCKER_REGISTRY}/mobin/${APP_NAME}"

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Show usage information
show_usage() {
    echo "Usage: $0 [ENVIRONMENT] [OPTIONS]"
    echo ""
    echo "Environments:"
    echo "  staging     Deploy to staging environment"
    echo "  production  Deploy to production environment"
    echo "  local       Deploy locally for testing"
    echo ""
    echo "Options:"
    echo "  --tag TAG           Specific image tag to deploy (default: latest)"
    echo "  --skip-tests        Skip running tests before deployment"
    echo "  --skip-backup       Skip database backup (production only)"
    echo "  --dry-run          Show what would be deployed without actually deploying"
    echo "  --help, -h         Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 staging"
    echo "  $0 production --tag v1.2.3"
    echo "  $0 staging --skip-tests --dry-run"
}

# Check if required tools are available
check_requirements() {
    print_status "Checking deployment requirements..."
    
    # Check Docker
    if ! command -v docker &> /dev/null; then
        print_error "Docker is not installed"
        exit 1
    fi
    
    # Check Docker Compose
    if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null; then
        print_error "Docker Compose is not installed"
        exit 1
    fi
    
    print_success "Requirements check passed"
}

# Run tests before deployment
run_tests() {
    if [ "$SKIP_TESTS" = true ]; then
        print_warning "Skipping tests as requested"
        return 0
    fi
    
    print_status "Running tests before deployment..."
    
    # Start CI environment
    docker-compose -f docker-compose.ci.yml up -d postgres redis
    
    # Wait for services
    sleep 5
    
    # Run tests
    if docker-compose -f docker-compose.ci.yml run --rm app vendor/bin/phpunit; then
        print_success "All tests passed"
    else
        print_error "Tests failed, aborting deployment"
        docker-compose -f docker-compose.ci.yml down -v
        exit 1
    fi
    
    # Cleanup
    docker-compose -f docker-compose.ci.yml down -v
}

# Build Docker image
build_image() {
    print_status "Building Docker image..."
    
    # Build the image
    docker build -t "${IMAGE_NAME}:${TAG}" .
    
    # Tag as latest if deploying latest
    if [ "$TAG" = "latest" ]; then
        docker tag "${IMAGE_NAME}:${TAG}" "${IMAGE_NAME}:latest"
    fi
    
    print_success "Docker image built: ${IMAGE_NAME}:${TAG}"
}

# Push image to registry
push_image() {
    if [ "$DRY_RUN" = true ]; then
        print_status "[DRY RUN] Would push image: ${IMAGE_NAME}:${TAG}"
        return 0
    fi
    
    print_status "Pushing image to registry..."
    
    # Push the image
    docker push "${IMAGE_NAME}:${TAG}"
    
    if [ "$TAG" = "latest" ]; then
        docker push "${IMAGE_NAME}:latest"
    fi
    
    print_success "Image pushed to registry"
}

# Backup database (production only)
backup_database() {
    if [ "$ENVIRONMENT" != "production" ] || [ "$SKIP_BACKUP" = true ]; then
        return 0
    fi
    
    print_status "Creating database backup..."
    
    BACKUP_FILE="backup_$(date +%Y%m%d_%H%M%S).sql"
    
    if [ "$DRY_RUN" = true ]; then
        print_status "[DRY RUN] Would create backup: $BACKUP_FILE"
        return 0
    fi
    
    # Create backup (assuming PostgreSQL)
    docker-compose exec -T postgres pg_dump -U postgres ai_blockchain_analytics > "backups/$BACKUP_FILE"
    
    print_success "Database backup created: $BACKUP_FILE"
}

# Deploy to staging environment
deploy_staging() {
    print_status "Deploying to staging environment..."
    
    if [ "$DRY_RUN" = true ]; then
        print_status "[DRY RUN] Would deploy to staging with image: ${IMAGE_NAME}:${TAG}"
        return 0
    fi
    
    # Update docker-compose for staging
    export IMAGE_TAG="$TAG"
    
    # Pull latest image
    docker-compose -f docker-compose.staging.yml pull app
    
    # Run database migrations
    docker-compose -f docker-compose.staging.yml run --rm app php artisan migrate --force
    
    # Restart services
    docker-compose -f docker-compose.staging.yml up -d --force-recreate app
    
    # Clear caches
    docker-compose -f docker-compose.staging.yml exec -T app php artisan config:cache
    docker-compose -f docker-compose.staging.yml exec -T app php artisan route:cache
    docker-compose -f docker-compose.staging.yml exec -T app php artisan view:cache
    
    print_success "Staging deployment completed"
}

# Deploy to production environment
deploy_production() {
    print_status "Deploying to production environment..."
    
    if [ "$DRY_RUN" = true ]; then
        print_status "[DRY RUN] Would deploy to production with image: ${IMAGE_NAME}:${TAG}"
        return 0
    fi
    
    # Backup database
    backup_database
    
    # Update docker-compose for production
    export IMAGE_TAG="$TAG"
    
    # Pull latest image
    docker-compose -f docker-compose.prod.yml pull app
    
    # Run database migrations
    docker-compose -f docker-compose.prod.yml run --rm app php artisan migrate --force
    
    # Deploy with zero-downtime strategy
    docker-compose -f docker-compose.prod.yml up -d --force-recreate app
    
    # Clear caches
    docker-compose -f docker-compose.prod.yml exec -T app php artisan config:cache
    docker-compose -f docker-compose.prod.yml exec -T app php artisan route:cache
    docker-compose -f docker-compose.prod.yml exec -T app php artisan view:cache
    
    # Run any post-deployment tasks
    docker-compose -f docker-compose.prod.yml exec -T app php artisan queue:restart
    
    print_success "Production deployment completed"
}

# Deploy locally for testing
deploy_local() {
    print_status "Deploying locally for testing..."
    
    if [ "$DRY_RUN" = true ]; then
        print_status "[DRY RUN] Would deploy locally with image: ${IMAGE_NAME}:${TAG}"
        return 0
    fi
    
    # Use local docker-compose
    export IMAGE_TAG="$TAG"
    
    # Build and start services
    docker-compose up -d --build
    
    # Run migrations
    docker-compose exec app php artisan migrate --force
    
    print_success "Local deployment completed"
    print_status "Application available at: http://localhost:8000"
}

# Verify deployment
verify_deployment() {
    if [ "$DRY_RUN" = true ]; then
        print_status "[DRY RUN] Would verify deployment"
        return 0
    fi
    
    print_status "Verifying deployment..."
    
    # Define health check URL based on environment
    case "$ENVIRONMENT" in
        staging)
            HEALTH_URL="https://staging.ai-blockchain-analytics.com/health"
            ;;
        production)
            HEALTH_URL="https://ai-blockchain-analytics.com/health"
            ;;
        local)
            HEALTH_URL="http://localhost:8000/health"
            ;;
    esac
    
    # Wait for application to be ready
    sleep 10
    
    # Check health endpoint (if it exists)
    if command -v curl &> /dev/null; then
        if curl -f -s "$HEALTH_URL" > /dev/null; then
            print_success "Health check passed"
        else
            print_warning "Health check failed or endpoint not available"
        fi
    fi
    
    print_success "Deployment verification completed"
}

# Rollback deployment
rollback_deployment() {
    print_status "Rolling back deployment..."
    
    if [ "$DRY_RUN" = true ]; then
        print_status "[DRY RUN] Would rollback deployment"
        return 0
    fi
    
    # This would typically involve deploying the previous version
    # Implementation depends on your versioning strategy
    print_warning "Rollback functionality not implemented yet"
    print_status "Manual rollback required - deploy previous version tag"
}

# Main deployment function
main() {
    echo -e "${BLUE}"
    echo "====================================="
    echo "AI Blockchain Analytics Deployment"
    echo "====================================="
    echo -e "${NC}"
    
    # Default values
    ENVIRONMENT=""
    TAG="latest"
    SKIP_TESTS=false
    SKIP_BACKUP=false
    DRY_RUN=false
    
    # Parse arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            staging|production|local)
                ENVIRONMENT=$1
                shift
                ;;
            --tag)
                TAG="$2"
                shift 2
                ;;
            --skip-tests)
                SKIP_TESTS=true
                shift
                ;;
            --skip-backup)
                SKIP_BACKUP=true
                shift
                ;;
            --dry-run)
                DRY_RUN=true
                shift
                ;;
            --rollback)
                ROLLBACK=true
                shift
                ;;
            --help|-h)
                show_usage
                exit 0
                ;;
            *)
                print_error "Unknown argument: $1"
                show_usage
                exit 1
                ;;
        esac
    done
    
    # Validate environment
    if [ -z "$ENVIRONMENT" ]; then
        print_error "Environment is required"
        show_usage
        exit 1
    fi
    
    # Handle rollback
    if [ "$ROLLBACK" = true ]; then
        rollback_deployment
        exit 0
    fi
    
    print_status "Deploying to: $ENVIRONMENT"
    print_status "Image tag: $TAG"
    
    if [ "$DRY_RUN" = true ]; then
        print_warning "DRY RUN MODE - No actual changes will be made"
    fi
    
    # Run deployment steps
    check_requirements
    run_tests
    build_image
    push_image
    
    # Deploy to specific environment
    case "$ENVIRONMENT" in
        staging)
            deploy_staging
            ;;
        production)
            deploy_production
            ;;
        local)
            deploy_local
            ;;
    esac
    
    verify_deployment
    
    print_success "🚀 Deployment completed successfully!"
    
    # Show post-deployment information
    echo ""
    print_status "Post-deployment checklist:"
    echo "1. Monitor application logs"
    echo "2. Check error rates and performance metrics"
    echo "3. Verify critical functionality"
    echo "4. Update monitoring dashboards"
    echo ""
}

# Create backup directory if it doesn't exist
mkdir -p backups

# Run main function
main "$@"