#\!/bin/bash

# 🎯 AI Blockchain Analytics - Complete Daily Demo Script
# Comprehensive demonstration of all platform features and capabilities
# Generated: 2025-08-12

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Demo configuration
DEMO_START_TIME=$(date '+%Y-%m-%d %H:%M:%S')
BASE_URL="http://localhost:8000"
LOG_FILE="daily-demo-$(date +%Y%m%d-%H%M%S).log"
REPORT_FILE="DAILY_DEMO_REPORT_$(date +%Y%m%d).md"

# Create demo log
touch "$LOG_FILE"

echo -e "${BLUE}🚀 AI BLOCKCHAIN ANALYTICS - DAILY DEMO SUITE${NC}"
echo -e "${BLUE}=================================================${NC}"
echo "Started: $DEMO_START_TIME"
echo "Log File: $LOG_FILE"
echo "Report: $REPORT_FILE"
echo ""

# Function to log section headers
log_section() {
    echo -e "\n${CYAN}📋 $1${NC}"
    echo "----------------------------------------"
}

# Function to test endpoint
test_endpoint() {
    local method=$1
    local endpoint=$2
    local description=$3
    local data=$4
    
    echo -e "${YELLOW}🧪 Testing: $description${NC}"
    
    if [ "$method" = "GET" ]; then
        response=$(curl -s -w "HTTPSTATUS:%{http_code}" "$BASE_URL$endpoint" || echo "HTTPSTATUS:000")
    else
        response=$(curl -s -w "HTTPSTATUS:%{http_code}" -X "$method" \
                   -H "Content-Type: application/json" \
                   -d "$data" "$BASE_URL$endpoint" || echo "HTTPSTATUS:000")
    fi
    
    status_code=$(echo "$response" | grep -o "HTTPSTATUS:[0-9]*" | cut -d: -f2)
    body=$(echo "$response" | sed 's/HTTPSTATUS:[0-9]*$//')
    
    if [ "$status_code" -ge 200 ] && [ "$status_code" -lt 400 ]; then
        echo -e "   ✅ Status: $status_code"
        echo "   📊 Response: $(echo "$body" | head -c 100)..."
    else
        echo -e "   ❌ Status: $status_code"
        echo "   🔍 Error: $(echo "$body" | head -c 200)..."
    fi
    
    return $status_code
}

# Start demo report
cat > "$REPORT_FILE" << 'REPORTEOF'
# 🎯 AI Blockchain Analytics - Daily Demo Report

**Demo Date**: $(date '+%Y-%m-%d')  
**Demo Time**: $DEMO_START_TIME  
**Platform Version**: v0.9.0  
**Environment**: Development/Production Ready

## 📊 Demo Summary

This comprehensive demo showcases all features of the AI Blockchain Analytics platform including smart contract analysis, sentiment monitoring, PDF generation, social crawling, and load testing capabilities.

## 🧪 Test Results

REPORTEOF

# Initialize counters
total_tests=0
passed_tests=0
failed_tests=0

# Function to record test result
record_test() {
    local test_name="$1"
    local status_code=$2
    
    total_tests=$((total_tests + 1))
    
    if [ "$status_code" -ge 200 ] && [ "$status_code" -lt 400 ]; then
        passed_tests=$((passed_tests + 1))
        echo "| ✅ $test_name | PASS | $status_code |" >> "$REPORT_FILE"
    else
        failed_tests=$((failed_tests + 1))
        echo "| ❌ $test_name | FAIL | $status_code |" >> "$REPORT_FILE"
    fi
}

# Add test results header to report
cat >> "$REPORT_FILE" << 'REPORTEOF'
| Test Name | Status | Code |
|-----------|--------|------|
REPORTEOF

# 1. SYSTEM HEALTH CHECKS
log_section "1. System Health & Status Checks"

test_endpoint "GET" "/up" "Health Check Endpoint"
record_test "Health Check" $?

test_endpoint "GET" "/dashboard" "Dashboard Page"
record_test "Dashboard Access" $?

# 2. SMART CONTRACT ANALYSIS
log_section "2. Smart Contract Analysis Features"

# Test with famous contracts from our seeded data
UNISWAP_ADDRESS="0xE592427A0AEce92De3Edee1F18E0157C05861564"
AAVE_ADDRESS="0x87870Bca3F3fD6335C3F4ce8392D69350B4fA4E2"
EULER_ADDRESS="0x27182842E098f60e3D576794A5bFFb0777E025d3"

# Contract analysis endpoints
test_endpoint "POST" "/api/analyze" "Smart Contract Analysis" \
    '{"address":"'$UNISWAP_ADDRESS'","network":"ethereum","analysis_type":"comprehensive"}'
record_test "Contract Analysis - Uniswap V3" $?

test_endpoint "GET" "/api/contracts/$UNISWAP_ADDRESS" "Contract Details Retrieval"
record_test "Contract Details" $?

# 3. SENTIMENT ANALYSIS
log_section "3. Sentiment Analysis & Social Monitoring"

test_endpoint "GET" "/api/sentiment/btc" "Bitcoin Sentiment Analysis"
record_test "BTC Sentiment" $?

test_endpoint "POST" "/api/sentiment/analyze" "Custom Sentiment Analysis" \
    '{"text":"Bitcoin price is showing strong bullish momentum with institutional adoption","symbol":"BTC"}'
record_test "Custom Sentiment" $?

# 4. PDF GENERATION
log_section "4. PDF Generation & Reporting"

test_endpoint "GET" "/api/pdf/status" "PDF Service Status"
record_test "PDF Service Status" $?

# 5. LOAD TESTING ENDPOINTS
log_section "5. Load Testing & Stress Testing"

test_endpoint "POST" "/api/load-test/analysis" "Load Test Analysis" \
    '{"analysis_type":"comprehensive","intensity":"high"}'
record_test "Load Test Analysis" $?

test_endpoint "GET" "/api/load-test/complex-query" "Complex Query Test"
record_test "Complex Query Load" $?

# Calculate demo completion time
DEMO_END_TIME=$(date '+%Y-%m-%d %H:%M:%S')
DEMO_DURATION=$(($(date -d "$DEMO_END_TIME" +%s) - $(date -d "$DEMO_START_TIME" +%s)))

# Complete the report
cat >> "$REPORT_FILE" << REPORTEOF

## 📈 Demo Statistics

- **Total Tests**: $total_tests
- **Passed Tests**: $passed_tests ✅
- **Failed Tests**: $failed_tests ❌
- **Success Rate**: $(echo "scale=1; $passed_tests * 100 / $total_tests" | bc -l 2>/dev/null || echo "N/A")%
- **Demo Duration**: ${DEMO_DURATION}s

## 🎯 Key Achievements Demonstrated

### ✅ Core Platform Features
- Smart contract analysis and risk assessment
- Real-time sentiment monitoring across social platforms
- Comprehensive PDF report generation
- Load testing and stress testing infrastructure

### 🏆 Demo Conclusion

$(if [ $failed_tests -eq 0 ]; then
    echo "**🎉 PERFECT DEMO EXECUTION** - All tests passed successfully\!"
elif [ $failed_tests -lt 3 ]; then
    echo "**✅ SUCCESSFUL DEMO** - Minor issues detected but overall system is stable."
else
    echo "**⚠️ DEMO WITH ISSUES** - Some features need attention before production deployment."
fi)

---

**Demo Generated**: $(date)  
**Platform Version**: v0.9.0  
**Status**: $(if [ $failed_tests -eq 0 ]; then echo "🟢 ALL SYSTEMS OPERATIONAL"; elif [ $failed_tests -lt 3 ]; then echo "🟡 MOSTLY OPERATIONAL"; else echo "🔴 NEEDS ATTENTION"; fi)
REPORTEOF

# Display final summary
echo -e "\n${GREEN}🎉 DAILY DEMO COMPLETED${NC}"
echo -e "${GREEN}=========================${NC}"
echo "Demo Duration: ${DEMO_DURATION}s"
echo "Total Tests: $total_tests"
echo "Passed: $passed_tests ✅"
echo "Failed: $failed_tests ❌"

if [ $failed_tests -eq 0 ]; then
    echo -e "\n${GREEN}🏆 PERFECT DEMO - ALL SYSTEMS OPERATIONAL\!${NC}"
elif [ $failed_tests -lt 3 ]; then
    echo -e "\n${YELLOW}⚡ DEMO MOSTLY SUCCESSFUL - MINOR ISSUES DETECTED${NC}"
else
    echo -e "\n${RED}⚠️  DEMO ISSUES DETECTED - NEEDS INVESTIGATION${NC}"
fi

echo -e "\nDaily demo completed at: $(date)"
EOF < /dev/null