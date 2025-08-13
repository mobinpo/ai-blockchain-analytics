#!/bin/bash

# AI Blockchain Analytics Platform v0.9.0 - Production Readiness Verification
# This script verifies that the application is ready for production deployment

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Counters
TOTAL_CHECKS=0
PASSED_CHECKS=0
FAILED_CHECKS=0

# Functions
check_start() {
    TOTAL_CHECKS=$((TOTAL_CHECKS + 1))
    echo -n "  Checking $1... "
}

check_pass() {
    PASSED_CHECKS=$((PASSED_CHECKS + 1))
    echo -e "${GREEN}✅ PASS${NC}"
}

check_fail() {
    FAILED_CHECKS=$((FAILED_CHECKS + 1))
    echo -e "${RED}❌ FAIL${NC}"
    if [[ -n "$1" ]]; then
        echo -e "    ${RED}$1${NC}"
    fi
}

check_warn() {
    echo -e "${YELLOW}⚠️  WARNING${NC}"
    if [[ -n "$1" ]]; then
        echo -e "    ${YELLOW}$1${NC}"
    fi
}

log() {
    echo -e "${BLUE}$1${NC}"
}

# Banner
echo -e "${BLUE}"
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║                                                              ║"
echo "║       AI Blockchain Analytics Platform v0.9.0               ║"
echo "║            Production Readiness Verification                 ║"
echo "║                                                              ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo -e "${NC}"

log "🔍 Running production readiness checks..."
echo

# Check 1: Git tag verification
log "📋 Version Control Checks"
check_start "Git tag v0.9.0"
if git describe --tags --exact-match 2>/dev/null | grep -q "v0.9.0"; then
    check_pass
else
    check_fail "Not on v0.9.0 tag. Current: $(git describe --tags 2>/dev/null || echo 'No tags')"
fi

check_start "Clean working directory"
# Exclude monitoring files that are continuously updated
UNCOMMITTED=$(git status --porcelain | grep -v "load-tests/monitoring_500_test.csv")
if [[ -z "$UNCOMMITTED" ]]; then
    check_pass
else
    check_fail "Working directory has uncommitted changes (excluding monitoring files)"
fi

echo

# Check 2: File structure verification
log "📁 File Structure Checks"
check_start "Production Docker files"
if [[ -f "docker-compose.production.yml" ]] && [[ -f "Dockerfile.production" ]]; then
    check_pass
else
    check_fail "Missing production Docker files"
fi

check_start "Environment template"
if [[ -f "env.production.template" ]]; then
    check_pass
else
    check_fail "Missing production environment template"
fi

check_start "Deployment scripts"
if [[ -f "deploy-production-v0.9.0.sh" ]] && [[ -x "deploy-production-v0.9.0.sh" ]]; then
    check_pass
else
    check_fail "Missing or non-executable deployment script"
fi

check_start "K8s deployment files"
if [[ -f "k8s/complete-production-deployment.yaml" ]]; then
    check_pass
else
    check_warn "K8s deployment files not found (optional)"
fi

check_start "ECS deployment files"
if [[ -f "ecs/complete-production-deployment.json" ]]; then
    check_pass
else
    check_warn "ECS deployment files not found (optional)"
fi

echo

# Check 3: Application structure
log "🏗️  Application Structure Checks"
check_start "Laravel framework files"
if [[ -f "artisan" ]] && [[ -f "composer.json" ]] && [[ -d "app" ]]; then
    check_pass
else
    check_fail "Missing core Laravel files"
fi

check_start "Database migrations"
if [[ -d "database/migrations" ]] && [[ $(ls database/migrations/*.php 2>/dev/null | wc -l) -gt 0 ]]; then
    check_pass
else
    check_fail "No database migrations found"
fi

check_start "Seeders"
if [[ -f "database/seeders/FamousContractsSeeder.php" ]]; then
    check_pass
else
    check_fail "Missing FamousContractsSeeder"
fi

check_start "Console commands"
if [[ -f "app/Console/Commands/DailyDemoScript.php" ]]; then
    check_pass
else
    check_fail "Missing DailyDemoScript command"
fi

echo

# Check 4: Dependencies verification
log "📦 Dependencies Checks"
check_start "Composer dependencies"
if [[ -f "composer.lock" ]]; then
    check_pass
else
    check_fail "Missing composer.lock file"
fi

check_start "NPM dependencies"
if [[ -f "package-lock.json" ]]; then
    check_pass
else
    check_fail "Missing package-lock.json file"
fi

echo

# Check 5: Configuration verification
log "⚙️  Configuration Checks"
check_start "Laravel config files"
required_configs=("app.php" "database.php" "cache.php" "queue.php" "session.php")
missing_configs=()
for config in "${required_configs[@]}"; do
    if [[ ! -f "config/$config" ]]; then
        missing_configs+=("$config")
    fi
done

if [[ ${#missing_configs[@]} -eq 0 ]]; then
    check_pass
else
    check_fail "Missing config files: ${missing_configs[*]}"
fi

check_start "Environment variables template"
if [[ -f "env.production.template" ]]; then
    # Check for critical environment variables
    critical_vars=("APP_KEY=" "DB_PASSWORD=" "REDIS_PASSWORD=" "OPENAI_API_KEY=" "ETHERSCAN_API_KEY=")
    missing_vars=()
    for var in "${critical_vars[@]}"; do
        if ! grep -q "^$var" env.production.template; then
            missing_vars+=("$var")
        fi
    done
    
    if [[ ${#missing_vars[@]} -eq 0 ]]; then
        check_pass
    else
        check_fail "Missing critical environment variables: ${missing_vars[*]}"
    fi
else
    check_fail "Environment template not found"
fi

echo

# Check 6: Security verification
log "🔒 Security Checks"
check_start "Production security settings in template"
if [[ -f "env.production.template" ]]; then
    security_issues=()
    
    if grep -q "APP_DEBUG=true" env.production.template; then
        security_issues+=("APP_DEBUG should be false")
    fi
    
    if grep -q "APP_ENV=local" env.production.template; then
        security_issues+=("APP_ENV should be production")
    fi
    
    if ! grep -q "SESSION_SECURE_COOKIE=true" env.production.template; then
        security_issues+=("SESSION_SECURE_COOKIE should be true")
    fi
    
    if [[ ${#security_issues[@]} -eq 0 ]]; then
        check_pass
    else
        check_fail "Security issues: ${security_issues[*]}"
    fi
else
    check_fail "No environment template to check"
fi

check_start "Sensitive files in .gitignore"
if [[ -f ".gitignore" ]]; then
    if grep -q ".env" .gitignore && grep -q "storage/logs" .gitignore; then
        check_pass
    else
        check_fail "Sensitive files not properly ignored"
    fi
else
    check_fail "Missing .gitignore file"
fi

echo

# Check 7: Load testing verification
log "⚡ Performance Checks"
check_start "Load test results"
if [[ -f "ARTILLERY_LOAD_TESTING_COMPLETE.md" ]] || [[ -f "LOAD_TESTING_SUCCESS_SUMMARY.md" ]]; then
    check_pass
else
    check_warn "No load testing results found"
fi

check_start "Performance documentation"
if [[ -f "ARTILLERY_500_CONCURRENT_ANALYSIS_SUMMARY.md" ]]; then
    check_pass
else
    check_warn "No 500 concurrent user testing documentation"
fi

echo

# Check 8: Documentation verification
log "📚 Documentation Checks"
check_start "Production deployment guide"
if [[ -f "PRODUCTION_DEPLOYMENT_v0.9.0.md" ]]; then
    check_pass
else
    check_fail "Missing production deployment guide"
fi

check_start "README file"
if [[ -f "README.md" ]]; then
    check_pass
else
    check_warn "Missing README.md file"
fi

echo

# Check 9: Monitoring setup
log "📊 Monitoring Checks"
check_start "Sentry configuration"
if grep -q "SENTRY_LARAVEL_DSN" env.production.template 2>/dev/null; then
    check_pass
else
    check_fail "Missing Sentry configuration"
fi

check_start "Health check endpoints"
if [[ -f "routes/api.php" ]]; then
    if grep -q "health" routes/api.php || grep -q "health" routes/web.php; then
        check_pass
    else
        check_fail "No health check endpoints found"
    fi
else
    check_fail "Missing routes files"
fi

echo

# Final summary
echo -e "${BLUE}╔══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║                    VERIFICATION SUMMARY                     ║${NC}"
echo -e "${BLUE}╚══════════════════════════════════════════════════════════════╝${NC}"
echo
echo "Total Checks: $TOTAL_CHECKS"
echo -e "Passed: ${GREEN}$PASSED_CHECKS${NC}"
echo -e "Failed: ${RED}$FAILED_CHECKS${NC}"
echo

if [[ $FAILED_CHECKS -eq 0 ]]; then
    echo -e "${GREEN}🎉 ALL CHECKS PASSED! 🎉${NC}"
    echo -e "${GREEN}The application is ready for production deployment.${NC}"
    echo
    echo -e "${BLUE}Next steps:${NC}"
    echo "1. Copy env.production.template to .env and configure production values"
    echo "2. Run ./deploy-production-v0.9.0.sh to deploy"
    echo "3. Configure your domain and SSL certificates"
    echo "4. Set up monitoring and alerting"
    echo "5. Test the production deployment"
    exit 0
else
    echo -e "${RED}❌ PRODUCTION READINESS FAILED ❌${NC}"
    echo -e "${RED}Please fix the failed checks before deploying to production.${NC}"
    exit 1
fi
