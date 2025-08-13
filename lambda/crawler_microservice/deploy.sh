#!/bin/bash

# Social Media Crawler Micro-Service Deployment Script
# Supports both Python Lambda and Docker deployment

set -e

# Configuration
STAGE=${1:-dev}
REGION=${2:-us-east-1}
SERVICE_NAME="social-crawler-microservice"

echo "🚀 Deploying Social Media Crawler Micro-Service"
echo "Stage: $STAGE"
echo "Region: $REGION"
echo "----------------------------------------"

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

# Check prerequisites
check_prerequisites() {
    print_status "Checking prerequisites..."
    
    # Check if serverless is installed
    if ! command -v serverless &> /dev/null; then
        print_error "Serverless framework not found. Installing..."
        npm install -g serverless
    fi
    
    # Check if AWS CLI is configured
    if ! aws sts get-caller-identity &> /dev/null; then
        print_error "AWS CLI not configured. Please run 'aws configure'"
        exit 1
    fi
    
    # Check if Docker is running (for packaging)
    if ! docker info &> /dev/null; then
        print_warning "Docker not running. Some packaging features may not work."
    fi
    
    print_success "Prerequisites check completed"
}

# Install dependencies
install_dependencies() {
    print_status "Installing Python dependencies..."
    
    # Create virtual environment if it doesn't exist
    if [ ! -d "venv" ]; then
        python3 -m venv venv
    fi
    
    # Activate virtual environment
    source venv/bin/activate
    
    # Install dependencies
    pip install --upgrade pip
    pip install -r requirements.txt
    
    # Install serverless plugins
    if [ ! -d "node_modules" ]; then
        npm init -y
    fi
    npm install --save-dev serverless-python-requirements serverless-prune-plugin
    
    print_success "Dependencies installed"
}

# Validate environment variables
validate_environment() {
    print_status "Validating environment variables..."
    
    required_vars=(
        "TWITTER_BEARER_TOKEN"
        "REDDIT_CLIENT_ID" 
        "REDDIT_CLIENT_SECRET"
        "REDDIT_USERNAME"
        "REDDIT_PASSWORD"
    )
    
    missing_vars=()
    
    for var in "${required_vars[@]}"; do
        if [ -z "${!var}" ]; then
            missing_vars+=("$var")
        fi
    done
    
    if [ ${#missing_vars[@]} -gt 0 ]; then
        print_warning "Missing environment variables:"
        printf '%s\n' "${missing_vars[@]}"
        print_warning "Some features may not work properly"
    else
        print_success "Environment validation completed"
    fi
}

# Deploy infrastructure
deploy_infrastructure() {
    print_status "Deploying infrastructure..."
    
    # Deploy the serverless application
    serverless deploy \
        --stage $STAGE \
        --region $REGION \
        --verbose
    
    print_success "Infrastructure deployed"
}

# Run tests
run_tests() {
    print_status "Running tests..."
    
    # Activate virtual environment
    source venv/bin/activate
    
    # Install test dependencies
    pip install pytest pytest-asyncio pytest-mock
    
    # Run tests if test file exists
    if [ -f "test_main.py" ]; then
        python -m pytest test_main.py -v
        print_success "Tests passed"
    else
        print_warning "No tests found, skipping test execution"
    fi
}

# Post-deployment configuration
post_deployment() {
    print_status "Post-deployment configuration..."
    
    # Get API endpoint
    API_ENDPOINT=$(serverless info --stage $STAGE --region $REGION | grep -o 'https://[^/]*\.execute-api\.[^/]*\.amazonaws\.com')
    
    if [ -n "$API_ENDPOINT" ]; then
        print_success "API Endpoint: $API_ENDPOINT"
        
        # Test health endpoint
        print_status "Testing health endpoint..."
        
        if curl -f -s "$API_ENDPOINT/health" > /dev/null; then
            print_success "Health check passed"
        else
            print_warning "Health check failed"
        fi
        
        # Save endpoint to file
        echo "$API_ENDPOINT" > .api_endpoint
        
    else
        print_error "Could not retrieve API endpoint"
    fi
    
    # Print deployment summary
    echo ""
    echo "📊 Deployment Summary"
    echo "===================="
    echo "Service: $SERVICE_NAME"
    echo "Stage: $STAGE"
    echo "Region: $REGION"
    echo "API Endpoint: ${API_ENDPOINT:-'Not available'}"
    echo ""
    echo "Next steps:"
    echo "1. Configure API keys in AWS Systems Manager Parameter Store"
    echo "2. Set up monitoring and alerting"
    echo "3. Test the crawler with sample keyword rules"
    echo ""
}

# Cleanup function
cleanup() {
    print_status "Cleaning up temporary files..."
    
    # Remove temporary files
    rm -f .serverless/*.zip
    
    print_success "Cleanup completed"
}

# Deploy monitoring
deploy_monitoring() {
    print_status "Setting up monitoring..."
    
    # Create CloudWatch dashboard
    aws cloudwatch put-dashboard \
        --dashboard-name "${SERVICE_NAME}-${STAGE}" \
        --dashboard-body file://monitoring/dashboard.json \
        --region $REGION 2>/dev/null || print_warning "Could not create CloudWatch dashboard"
    
    # Set up alarms
    aws cloudwatch put-metric-alarm \
        --alarm-name "${SERVICE_NAME}-${STAGE}-errors" \
        --alarm-description "High error rate for crawler service" \
        --metric-name Errors \
        --namespace AWS/Lambda \
        --statistic Sum \
        --period 300 \
        --threshold 10 \
        --comparison-operator GreaterThanThreshold \
        --evaluation-periods 2 \
        --region $REGION 2>/dev/null || print_warning "Could not create error alarm"
    
    print_success "Monitoring setup completed"
}

# Main deployment flow
main() {
    echo "Starting deployment process..."
    
    # Change to script directory
    cd "$(dirname "$0")"
    
    # Run deployment steps
    check_prerequisites
    install_dependencies
    validate_environment
    
    # Optional: run tests before deployment
    if [ "$RUN_TESTS" = "true" ]; then
        run_tests
    fi
    
    deploy_infrastructure
    deploy_monitoring
    post_deployment
    cleanup
    
    print_success "🎉 Deployment completed successfully!"
}

# Handle script arguments
case "${1:-deploy}" in
    "deploy")
        main
        ;;
    "remove")
        print_status "Removing service..."
        serverless remove --stage $STAGE --region $REGION
        print_success "Service removed"
        ;;
    "test")
        check_prerequisites
        install_dependencies
        run_tests
        ;;
    "info")
        serverless info --stage $STAGE --region $REGION
        ;;
    "logs")
        serverless logs -f crawl --stage $STAGE --region $REGION -t
        ;;
    *)
        echo "Usage: $0 [deploy|remove|test|info|logs] [stage] [region]"
        echo ""
        echo "Commands:"
        echo "  deploy  - Deploy the service (default)"
        echo "  remove  - Remove the service"
        echo "  test    - Run tests"
        echo "  info    - Show service information"
        echo "  logs    - Show real-time logs"
        echo ""
        echo "Arguments:"
        echo "  stage   - Deployment stage (default: dev)"
        echo "  region  - AWS region (default: us-east-1)"
        echo ""
        echo "Environment variables:"
        echo "  RUN_TESTS=true - Run tests before deployment"
        exit 1
        ;;
esac