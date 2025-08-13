#!/bin/bash
set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🔧 AI Blockchain Analytics Environment Setup${NC}"
echo -e "${BLUE}============================================${NC}"

# Function to print status
print_status() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Check if running as root
if [ "$EUID" -eq 0 ]; then
    print_error "Please don't run this script as root"
    exit 1
fi

# Detect platform
PLATFORM=""
if [[ "$OSTYPE" == "linux-gnu"* ]]; then
    PLATFORM="linux"
elif [[ "$OSTYPE" == "darwin"* ]]; then
    PLATFORM="macos"
elif [[ "$OSTYPE" == "msys" ]]; then
    PLATFORM="windows"
else
    print_error "Unsupported platform: $OSTYPE"
    exit 1
fi

echo -e "${YELLOW}🖥️  Detected platform: $PLATFORM${NC}"

# Install Docker
install_docker() {
    echo -e "${YELLOW}🐳 Installing Docker...${NC}"
    
    if command -v docker &> /dev/null; then
        print_status "Docker already installed: $(docker --version)"
        return
    fi
    
    case $PLATFORM in
        "linux")
            # Install Docker on Linux
            curl -fsSL https://get.docker.com -o get-docker.sh
            sh get-docker.sh
            sudo usermod -aG docker $USER
            rm get-docker.sh
            ;;
        "macos")
            print_warning "Please install Docker Desktop for Mac from https://docker.com/products/docker-desktop"
            ;;
        "windows")
            print_warning "Please install Docker Desktop for Windows from https://docker.com/products/docker-desktop"
            ;;
    esac
    
    print_status "Docker installation completed"
}

# Install kubectl
install_kubectl() {
    echo -e "${YELLOW}☸️  Installing kubectl...${NC}"
    
    if command -v kubectl &> /dev/null; then
        print_status "kubectl already installed: $(kubectl version --client --short)"
        return
    fi
    
    case $PLATFORM in
        "linux")
            curl -LO "https://dl.k8s.io/release/$(curl -L -s https://dl.k8s.io/release/stable.txt)/bin/linux/amd64/kubectl"
            sudo install -o root -g root -m 0755 kubectl /usr/local/bin/kubectl
            rm kubectl
            ;;
        "macos")
            curl -LO "https://dl.k8s.io/release/$(curl -L -s https://dl.k8s.io/release/stable.txt)/bin/darwin/amd64/kubectl"
            chmod +x ./kubectl
            sudo mv ./kubectl /usr/local/bin/kubectl
            ;;
        "windows")
            print_warning "Please install kubectl from https://kubernetes.io/docs/tasks/tools/install-kubectl-windows/"
            ;;
    esac
    
    print_status "kubectl installation completed"
}

# Install AWS CLI
install_aws_cli() {
    echo -e "${YELLOW}🚀 Installing AWS CLI...${NC}"
    
    if command -v aws &> /dev/null; then
        print_status "AWS CLI already installed: $(aws --version)"
        return
    fi
    
    case $PLATFORM in
        "linux")
            curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o "awscliv2.zip"
            unzip awscliv2.zip
            sudo ./aws/install
            rm -rf aws awscliv2.zip
            ;;
        "macos")
            curl "https://awscli.amazonaws.com/AWSCLIV2.pkg" -o "AWSCLIV2.pkg"
            sudo installer -pkg AWSCLIV2.pkg -target /
            rm AWSCLIV2.pkg
            ;;
        "windows")
            print_warning "Please install AWS CLI from https://aws.amazon.com/cli/"
            ;;
    esac
    
    print_status "AWS CLI installation completed"
}

# Install Helm
install_helm() {
    echo -e "${YELLOW}⎈ Installing Helm...${NC}"
    
    if command -v helm &> /dev/null; then
        print_status "Helm already installed: $(helm version --short)"
        return
    fi
    
    curl https://raw.githubusercontent.com/helm/helm/main/scripts/get-helm-3 | bash
    print_status "Helm installation completed"
}

# Setup environment file
setup_env_file() {
    echo -e "${YELLOW}📝 Setting up environment file...${NC}"
    
    if [ ! -f .env ]; then
        if [ -f .env.example ]; then
            cp .env.example .env
            print_status "Created .env from .env.example"
        else
            cat > .env << EOF
# AI Blockchain Analytics Environment Configuration

# Application
APP_NAME="AI Blockchain Analytics"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://analytics.yourcompany.com
APP_KEY=

# Database
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=ai_blockchain_analytics
DB_USERNAME=ai_blockchain_user
DB_PASSWORD=

# Redis
REDIS_HOST=redis
REDIS_PASSWORD=
REDIS_PORT=6379
REDIS_DB=0

# Cache & Session
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# External APIs
COINGECKO_API_KEY=
ETHERSCAN_API_KEY=
MORALIS_API_KEY=

# Social Media APIs
TWITTER_API_KEY=
TWITTER_API_SECRET=
REDDIT_CLIENT_ID=
REDDIT_CLIENT_SECRET=

# Monitoring
SENTRY_LARAVEL_DSN=
TELESCOPE_ENABLED=false

# PDF Generation
BROWSERLESS_ENABLED=false
BROWSERLESS_URL=https://chrome.browserless.io
BROWSERLESS_TOKEN=
EOF
            print_status "Created basic .env file"
        fi
    else
        print_status ".env file already exists"
    fi
    
    print_warning "Please update .env file with your actual configuration values"
}

# Generate application key
generate_app_key() {
    echo -e "${YELLOW}🔑 Generating application key...${NC}"
    
    if command -v php &> /dev/null && [ -f artisan ]; then
        php artisan key:generate
        print_status "Application key generated"
    else
        APP_KEY="base64:$(openssl rand -base64 32)"
        sed -i "s/APP_KEY=/APP_KEY=$APP_KEY/" .env
        print_status "Application key set to: $APP_KEY"
    fi
}

# Create necessary directories
create_directories() {
    echo -e "${YELLOW}📁 Creating necessary directories...${NC}"
    
    mkdir -p storage/logs
    mkdir -p storage/framework/cache
    mkdir -p storage/framework/sessions
    mkdir -p storage/framework/views
    mkdir -p storage/app/public/pdfs
    mkdir -p bootstrap/cache
    
    # Set permissions if on Unix-like system
    if [[ "$PLATFORM" != "windows" ]]; then
        chmod -R 775 storage bootstrap/cache
    fi
    
    print_status "Directories created and permissions set"
}

# Validate Docker Compose
validate_docker_compose() {
    echo -e "${YELLOW}🔍 Validating Docker Compose configuration...${NC}"
    
    if [ -f docker-compose.production.yml ]; then
        if command -v docker-compose &> /dev/null; then
            docker-compose -f docker-compose.production.yml config > /dev/null
            print_status "Docker Compose configuration is valid"
        else
            print_warning "docker-compose not found, skipping validation"
        fi
    else
        print_error "docker-compose.production.yml not found"
    fi
}

# Validate Kubernetes manifests
validate_k8s_manifests() {
    echo -e "${YELLOW}🔍 Validating Kubernetes manifests...${NC}"
    
    if command -v kubectl &> /dev/null; then
        for manifest in k8s/**/*.yaml; do
            if [ -f "$manifest" ]; then
                kubectl apply --dry-run=client -f "$manifest" > /dev/null 2>&1
                if [ $? -eq 0 ]; then
                    echo -e "${GREEN}✓${NC} $manifest"
                else
                    echo -e "${RED}✗${NC} $manifest"
                fi
            fi
        done
        print_status "Kubernetes manifests validation completed"
    else
        print_warning "kubectl not found, skipping K8s validation"
    fi
}

# Main installation menu
main_menu() {
    echo -e "${BLUE}📋 What would you like to set up?${NC}"
    echo "1) Complete setup (recommended)"
    echo "2) Docker only"
    echo "3) Kubernetes tools only"
    echo "4) AWS tools only"
    echo "5) Environment configuration only"
    echo "6) Validate configuration"
    echo "7) Exit"
    
    read -p "Choose an option [1-7]: " choice
    
    case $choice in
        1)
            install_docker
            install_kubectl
            install_aws_cli
            install_helm
            setup_env_file
            generate_app_key
            create_directories
            validate_docker_compose
            validate_k8s_manifests
            ;;
        2)
            install_docker
            setup_env_file
            create_directories
            validate_docker_compose
            ;;
        3)
            install_kubectl
            install_helm
            validate_k8s_manifests
            ;;
        4)
            install_aws_cli
            ;;
        5)
            setup_env_file
            generate_app_key
            create_directories
            ;;
        6)
            validate_docker_compose
            validate_k8s_manifests
            ;;
        7)
            echo -e "${BLUE}👋 Goodbye!${NC}"
            exit 0
            ;;
        *)
            print_error "Invalid option. Please choose 1-7."
            main_menu
            ;;
    esac
}

# Check for required dependencies
check_dependencies() {
    echo -e "${YELLOW}🔍 Checking system dependencies...${NC}"
    
    # Check for curl
    if ! command -v curl &> /dev/null; then
        print_error "curl is required but not installed"
        exit 1
    fi
    
    # Check for unzip (Linux)
    if [[ "$PLATFORM" == "linux" ]] && ! command -v unzip &> /dev/null; then
        print_error "unzip is required but not installed. Please install it first."
        exit 1
    fi
    
    print_status "System dependencies check passed"
}

# Show post-installation instructions
show_post_install() {
    echo -e "${BLUE}🎉 Setup completed successfully!${NC}"
    echo -e "${BLUE}==============================${NC}"
    echo ""
    echo -e "${YELLOW}📝 Next Steps:${NC}"
    echo "1. Update .env file with your actual configuration values"
    echo "2. For Docker deployment: docker-compose -f docker-compose.production.yml up -d"
    echo "3. For Kubernetes deployment: ./scripts/deploy-k8s.sh"
    echo "4. For AWS ECS deployment: ./scripts/deploy-ecs.sh"
    echo ""
    echo -e "${YELLOW}📚 Documentation:${NC}"
    echo "- Read DEPLOYMENT_GUIDE.md for detailed instructions"
    echo "- Check PDF_GENERATION_COMPLETE.md for PDF features"
    echo "- Review CRAWLER_MICROSERVICE_COMPLETE.md for crawler setup"
    echo ""
    echo -e "${YELLOW}🔧 Configuration Files:${NC}"
    echo "- .env: Application environment variables"
    echo "- k8s/: Kubernetes manifests"
    echo "- ecs/: ECS task definitions and services"
    echo "- docker-compose.production.yml: Docker Compose setup"
    echo ""
    echo -e "${GREEN}🚀 Happy deploying!${NC}"
}

# Main execution
main() {
    check_dependencies
    main_menu
    show_post_install
}

# Run main function
main "$@"
