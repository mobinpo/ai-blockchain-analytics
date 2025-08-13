#!/bin/bash

# Vulnerability Regression Test Suite Runner
# This script runs the comprehensive vulnerability regression tests

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
RESULTS_DIR="regression_results"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
EXPORT_FILE="${RESULTS_DIR}/regression_${TIMESTAMP}.json"

# Create results directory
mkdir -p $RESULTS_DIR

echo -e "${BLUE}🔍 AI Blockchain Analytics - Vulnerability Regression Test Suite${NC}"
echo -e "${BLUE}=================================================================${NC}"
echo ""

# Check environment
echo -e "${YELLOW}📋 Environment Check${NC}"
if [ ! -f ".env" ]; then
    echo -e "${RED}❌ .env file not found${NC}"
    exit 1
fi

if [ ! -f "vendor/autoload.php" ]; then
    echo -e "${RED}❌ Vendor dependencies not installed. Run: composer install${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Environment ready${NC}"
echo ""

# Parse command line arguments
USE_REAL_API=false
SPECIFIC_TEST=""
VERBOSE=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --real-api)
            USE_REAL_API=true
            shift
            ;;
        --test=*)
            SPECIFIC_TEST="${1#*=}"
            shift
            ;;
        --verbose|-v)
            VERBOSE=true
            shift
            ;;
        --help|-h)
            echo "Usage: $0 [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --real-api         Use real OpenAI API instead of simulation"
            echo "  --test=TEST_NAME   Run specific test only"
            echo "  --verbose, -v      Enable verbose output"
            echo "  --help, -h         Show this help message"
            echo ""
            echo "Examples:"
            echo "  $0                              # Run all tests with simulation"
            echo "  $0 --real-api                   # Run all tests with real API"
            echo "  $0 --test=reentrancy_basic     # Run specific test"
            echo "  $0 --verbose                    # Run with detailed output"
            exit 0
            ;;
        *)
            echo -e "${RED}❌ Unknown option: $1${NC}"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

# Display configuration
echo -e "${YELLOW}⚙️  Test Configuration${NC}"
echo "Mode: $([ "$USE_REAL_API" = true ] && echo "Real API" || echo "Simulation")"
echo "Specific Test: $([ -n "$SPECIFIC_TEST" ] && echo "$SPECIFIC_TEST" || echo "All tests")"
echo "Verbose: $([ "$VERBOSE" = true ] && echo "Yes" || echo "No")"
echo "Results: $EXPORT_FILE"
echo ""

# Run database migrations
echo -e "${YELLOW}🗄️  Database Setup${NC}"
php artisan migrate:fresh --seed --env=testing --quiet
echo -e "${GREEN}✅ Database ready${NC}"
echo ""

# Run the regression tests
echo -e "${YELLOW}🧪 Running Regression Tests${NC}"
echo ""

if [ "$USE_REAL_API" = true ]; then
    # Check for OpenAI API key
    if [ -z "${OPENAI_API_KEY}" ]; then
        echo -e "${RED}❌ OPENAI_API_KEY environment variable not set${NC}"
        echo "Required for real API mode"
        exit 1
    fi
    echo -e "${BLUE}🔗 Using real OpenAI API${NC}"
fi

# Choose test runner based on parameters
if [ -n "$SPECIFIC_TEST" ]; then
    # Run specific test via artisan command
    echo -e "${BLUE}🎯 Running specific test: $SPECIFIC_TEST${NC}"
    if [ "$USE_REAL_API" = true ]; then
        php artisan vulnerability:regression --test="$SPECIFIC_TEST" --real-api --export="$EXPORT_FILE"
    else
        php artisan vulnerability:regression --test="$SPECIFIC_TEST" --export="$EXPORT_FILE"
    fi
else
    # Run comprehensive test suite
    echo -e "${BLUE}🔍 Running comprehensive test suite${NC}"
    
    # Set environment variables for PHPUnit
    export REGRESSION_USE_REAL_API=$USE_REAL_API
    export REGRESSION_TIMEOUT=30
    export REGRESSION_MIN_DETECTION_RATE=70
    
    # Run PHPUnit tests
    if [ "$VERBOSE" = true ]; then
        ./vendor/bin/phpunit --configuration phpunit.regression.xml --verbose
    else
        ./vendor/bin/phpunit --configuration phpunit.regression.xml
    fi
    
    # Also run the artisan command for additional reporting
    echo ""
    echo -e "${BLUE}📊 Generating detailed report${NC}"
    if [ "$USE_REAL_API" = true ]; then
        php artisan vulnerability:regression --real-api --export="$EXPORT_FILE"
    else
        php artisan vulnerability:regression --export="$EXPORT_FILE"
    fi
fi

# Check test results
PHPUNIT_EXIT_CODE=$?

echo ""
echo -e "${YELLOW}📈 Test Results Summary${NC}"

if [ -f "$EXPORT_FILE" ]; then
    # Parse JSON results if available
    DETECTION_RATE=$(php -r "
        \$data = json_decode(file_get_contents('$EXPORT_FILE'), true);
        echo isset(\$data['detection_rate']) ? \$data['detection_rate'] : 'N/A';
    ")
    
    TOTAL_CONTRACTS=$(php -r "
        \$data = json_decode(file_get_contents('$EXPORT_FILE'), true);
        echo isset(\$data['total_contracts']) ? \$data['total_contracts'] : 'N/A';
    ")
    
    echo "Detection Rate: $DETECTION_RATE%"
    echo "Total Contracts: $TOTAL_CONTRACTS"
    echo "Results File: $EXPORT_FILE"
    
    # Determine pass/fail
    if [ "$DETECTION_RATE" != "N/A" ]; then
        DETECTION_INT=$(echo "$DETECTION_RATE" | cut -d'.' -f1)
        if [ "$DETECTION_INT" -ge 70 ]; then
            echo -e "${GREEN}🎉 Tests PASSED (Detection rate: $DETECTION_RATE%)${NC}"
            EXIT_CODE=0
        else
            echo -e "${RED}❌ Tests FAILED (Detection rate: $DETECTION_RATE% < 70%)${NC}"
            EXIT_CODE=1
        fi
    else
        echo -e "${YELLOW}⚠️  Could not determine test results${NC}"
        EXIT_CODE=$PHPUNIT_EXIT_CODE
    fi
else
    echo -e "${YELLOW}⚠️  Results file not generated${NC}"
    EXIT_CODE=$PHPUNIT_EXIT_CODE
fi

echo ""
echo -e "${YELLOW}📁 Output Files${NC}"
echo "Results JSON: $EXPORT_FILE"
echo "Test Logs: Check Laravel logs for detailed analysis output"

# Display usage examples
echo ""
echo -e "${YELLOW}💡 Next Steps${NC}"
echo "View results: cat $EXPORT_FILE | jq"
echo "Analyze logs: tail -f storage/logs/laravel.log"
echo "Re-run specific test: $0 --test=reentrancy_basic"
if [ "$USE_REAL_API" = false ]; then
    echo "Run with real API: $0 --real-api"
fi

echo ""
echo -e "${BLUE}🏁 Regression test suite completed${NC}"

exit $EXIT_CODE