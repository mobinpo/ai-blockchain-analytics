#!/bin/bash

# AI Blockchain Analytics - Mono-repo Setup Script
# This script sets up the development environment for the mono-repo

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

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

# Check if required tools are installed
check_requirements() {
    print_status "Checking requirements..."
    
    # Check Docker
    if ! command -v docker &> /dev/null; then
        print_error "Docker is not installed. Please install Docker first."
        exit 1
    fi
    
    # Check Docker Compose
    if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null; then
        print_error "Docker Compose is not installed. Please install Docker Compose first."
        exit 1
    fi
    
    # Check Node.js
    if ! command -v node &> /dev/null; then
        print_warning "Node.js is not installed. Frontend development will not be available."
    else
        NODE_VERSION=$(node --version)
        print_status "Node.js version: $NODE_VERSION"
    fi
    
    # Check PHP (optional for local development)
    if command -v php &> /dev/null; then
        PHP_VERSION=$(php --version | head -n 1)
        print_status "PHP version: $PHP_VERSION"
    fi
    
    print_success "Requirements check completed"
}

# Setup environment file
setup_environment() {
    print_status "Setting up environment file..."
    
    if [ ! -f .env ]; then
        if [ -f .env.example ]; then
            cp .env.example .env
            print_success "Environment file created from .env.example"
        else
            print_error ".env.example file not found"
            exit 1
        fi
    else
        print_warning ".env file already exists, skipping..."
    fi
}

# Start Docker services
start_docker_services() {
    print_status "Starting Docker services..."
    
    # Build and start containers
    docker-compose up -d --build
    
    # Wait for services to be ready
    print_status "Waiting for services to be ready..."
    sleep 10
    
    # Check if PostgreSQL is ready
    until docker-compose exec -T postgres pg_isready -U postgres; do
        print_status "Waiting for PostgreSQL..."
        sleep 2
    done
    
    # Check if Redis is ready
    until docker-compose exec -T redis redis-cli ping; do
        print_status "Waiting for Redis..."
        sleep 2
    done
    
    print_success "Docker services are running"
}

# Install PHP dependencies
install_php_dependencies() {
    print_status "Installing PHP dependencies..."
    
    if docker-compose ps app | grep -q "Up"; then
        docker-compose exec app composer install --optimize-autoloader
        print_success "PHP dependencies installed"
    else
        print_error "App container is not running"
        exit 1
    fi
}

# Setup Laravel application
setup_laravel() {
    print_status "Setting up Laravel application..."
    
    # Generate application key
    docker-compose exec app php artisan key:generate --force
    
    # Run database migrations
    docker-compose exec app php artisan migrate --force
    
    # Seed the database
    docker-compose exec app php artisan db:seed --force
    
    # Clear and cache configuration
    docker-compose exec app php artisan config:cache
    docker-compose exec app php artisan route:cache
    docker-compose exec app php artisan view:cache
    
    print_success "Laravel application setup completed"
}

# Install Node.js dependencies
install_node_dependencies() {
    print_status "Installing Node.js dependencies..."
    
    if command -v npm &> /dev/null; then
        npm install
        print_success "Node.js dependencies installed"
    else
        print_warning "npm not found, skipping Node.js dependencies"
    fi
}

# Build frontend assets
build_frontend() {
    print_status "Building frontend assets..."
    
    if command -v npm &> /dev/null; then
        npm run build
        print_success "Frontend assets built"
    else
        print_warning "npm not found, skipping frontend build"
    fi
}

# Run tests
run_tests() {
    print_status "Running tests..."
    
    # Run PHP tests
    docker-compose exec app vendor/bin/phpunit --testdox
    
    # Run frontend tests if available
    if command -v npm &> /dev/null && npm run | grep -q "test"; then
        npm run test
    fi
    
    print_success "Tests completed"
}

# Setup Git hooks (optional)
setup_git_hooks() {
    print_status "Setting up Git hooks..."
    
    if [ -d .git ]; then
        # Create pre-commit hook
        cat > .git/hooks/pre-commit << 'EOF'
#!/bin/bash
echo "Running pre-commit checks..."

# Run PHP CS Fixer
docker-compose exec app vendor/bin/pint --test

# Run Psalm
docker-compose exec app vendor/bin/psalm --no-cache

echo "Pre-commit checks passed!"
EOF
        chmod +x .git/hooks/pre-commit
        print_success "Git hooks setup completed"
    else
        print_warning "Not a Git repository, skipping Git hooks"
    fi
}

# Display success message and next steps
show_completion_message() {
    echo ""
    print_success "🎉 Mono-repo setup completed successfully!"
    echo ""
    echo -e "${BLUE}Next steps:${NC}"
    echo "1. Start development server: ${YELLOW}npm run dev${NC}"
    echo "2. Access the application: ${YELLOW}http://localhost:8000${NC}"
    echo "3. View logs: ${YELLOW}docker-compose logs -f app${NC}"
    echo "4. Run tests: ${YELLOW}composer test${NC} or ${YELLOW}npm test${NC}"
    echo "5. Access database: ${YELLOW}docker-compose exec postgres psql -U postgres -d ai_blockchain_analytics${NC}"
    echo ""
    echo -e "${BLUE}Useful commands:${NC}"
    echo "- Stop services: ${YELLOW}docker-compose down${NC}"
    echo "- Restart services: ${YELLOW}docker-compose restart${NC}"
    echo "- View running containers: ${YELLOW}docker-compose ps${NC}"
    echo "- Clean up: ${YELLOW}docker-compose down -v${NC}"
    echo ""
}

# Main execution
main() {
    echo -e "${BLUE}"
    echo "================================="
    echo "AI Blockchain Analytics Setup"
    echo "================================="
    echo -e "${NC}"
    
    # Parse command line arguments
    SKIP_TESTS=false
    SKIP_FRONTEND=false
    
    while [[ $# -gt 0 ]]; do
        case $1 in
            --skip-tests)
                SKIP_TESTS=true
                shift
                ;;
            --skip-frontend)
                SKIP_FRONTEND=true
                shift
                ;;
            --help|-h)
                echo "Usage: $0 [OPTIONS]"
                echo "Options:"
                echo "  --skip-tests      Skip running tests"
                echo "  --skip-frontend   Skip frontend setup"
                echo "  --help, -h        Show this help message"
                exit 0
                ;;
            *)
                print_error "Unknown option: $1"
                exit 1
                ;;
        esac
    done
    
    # Run setup steps
    check_requirements
    setup_environment
    start_docker_services
    install_php_dependencies
    setup_laravel
    
    if [ "$SKIP_FRONTEND" = false ]; then
        install_node_dependencies
        build_frontend
    fi
    
    if [ "$SKIP_TESTS" = false ]; then
        run_tests
    fi
    
    setup_git_hooks
    show_completion_message
}

# Run main function
main "$@"