#!/bin/bash

# Demo Load Test for AI Blockchain Analytics
# Shows Artillery configuration and execution without requiring running server

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m' # No Color

# Logging functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_section() {
    echo -e "${PURPLE}[SECTION]${NC} $1"
    echo -e "${PURPLE}$(printf '=%.0s' {1..60})${NC}"
}

# Display Artillery configuration overview
show_artillery_config() {
    log_section "🚀 Artillery Load Testing Configuration"
    
    echo "Target: 500 Concurrent Blockchain Analyses"
    echo "Test Configurations Available:"
    echo ""
    
    echo "1. artillery-config.yml - Main comprehensive test"
    echo "   • Phases: Warm-up → Ramp-up → Sustained → Peak → Cool-down"
    echo "   • Duration: ~11 minutes total"
    echo "   • Max RPS: 100 requests/second"
    echo ""
    
    echo "2. concurrent-500.yml - 500 concurrent analyses"
    echo "   • Phases: 10→100→300→500→600→50 concurrent users"
    echo "   • Duration: ~13 minutes total"
    echo "   • Peak: 600 concurrent users (stress test)"
    echo ""
    
    echo "3. blockchain-analysis.yml - AI analysis focused"
    echo "   • Smart contract security analysis (40% weight)"
    echo "   • AI vulnerability scanning (25% weight)"
    echo "   • Sentiment analysis pipeline (20% weight)"
    echo "   • PDF report generation (10% weight)"
    echo "   • Verification badge generation (5% weight)"
    echo ""
    
    echo "4. performance-monitoring.yml - System metrics"
    echo "   • Database performance testing"
    echo "   • Memory usage monitoring"
    echo "   • CPU performance testing"
    echo "   • Network I/O performance"
    echo "   • Cache performance testing"
    echo ""
}

# Show test scenarios
show_test_scenarios() {
    log_section "🎯 Load Test Scenarios"
    
    echo "Primary Blockchain Analysis Scenario (70% weight):"
    echo "┌─────────────────────────────────────────────────────┐"
    echo "│ 1. Authenticate with test credentials              │"
    echo "│ 2. Submit blockchain analysis request               │"
    echo "│    • Random contract address                       │"
    echo "│    • Analysis type: security/vulnerability/gas     │"
    echo "│    • Priority: low/medium/high/critical            │"
    echo "│ 3. Check analysis status                           │"
    echo "│ 4. Retrieve results when completed                 │"
    echo "└─────────────────────────────────────────────────────┘"
    echo ""
    
    echo "Secondary Scenarios:"
    echo "• Real-time Analysis Monitoring (20%)"
    echo "• System Health Monitoring (10%)"
    echo ""
    
    echo "Test Data Used:"
    echo "• Real smart contract addresses (Compound, Aave, Uniswap, etc.)"
    echo "• Varied analysis complexity levels"
    echo "• Realistic processing times based on operation type"
    echo "• Mock vulnerability detection results"
    echo ""
}

# Show performance expectations
show_performance_targets() {
    log_section "📊 Performance Targets & Thresholds"
    
    echo "Performance Expectations for 500 Concurrent Users:"
    echo ""
    
    echo "Response Time Targets:"
    echo "• P50 (50th percentile): < 1,000ms"
    echo "• P95 (95th percentile): < 5,000ms"
    echo "• P99 (99th percentile): < 10,000ms"
    echo "• Maximum response time: 10 seconds"
    echo ""
    
    echo "Throughput Targets:"
    echo "• Peak RPS: 500 requests/second"
    echo "• Sustained RPS: 300 requests/second"
    echo "• Error rate: < 5%"
    echo "• Connection pool: 500 connections"
    echo ""
    
    echo "System Resource Monitoring:"
    echo "• Memory usage tracking"
    echo "• CPU load monitoring"
    echo "• Database performance"
    echo "• Redis cache performance"
    echo "• Queue processing rates"
    echo ""
}

# Show load test execution commands
show_execution_commands() {
    log_section "⚡ Load Test Execution Commands"
    
    echo "Quick Start Commands:"
    echo ""
    
    echo "# Install Artillery dependencies"
    echo "npm install"
    echo ""
    
    echo "# Run 500 concurrent analyses test"
    echo "npm run load-test:500"
    echo ""
    
    echo "# Use automated script for comprehensive testing"
    echo "./scripts/load-test-runner.sh 500"
    echo ""
    
    echo "# Run comprehensive test suite"
    echo "./scripts/load-test-runner.sh comprehensive"
    echo ""
    
    echo "# Quick smoke test"
    echo "npm run load-test:quick"
    echo ""
    
    echo "# Direct Artillery commands"
    echo "artillery run load-tests/concurrent-500.yml"
    echo "artillery run load-tests/blockchain-analysis.yml"
    echo "artillery run load-tests/performance-monitoring.yml"
    echo ""
}

# Show available endpoints
show_test_endpoints() {
    log_section "🔗 Load Test Endpoints"
    
    echo "Health & Monitoring:"
    echo "• GET  /api/health                    - Application health check"
    echo "• GET  /api/pdf/engine-info          - PDF engine status"
    echo ""
    
    echo "Load Testing Simulation:"
    echo "• POST /api/load-test/analysis       - Simulate blockchain analysis"
    echo "• GET  /api/load-test/analysis/{id}/status - Check analysis status"
    echo "• POST /api/load-test/sentiment      - Simulate sentiment analysis"
    echo "• GET  /api/load-test/complex-query  - Complex database queries"
    echo "• POST /api/load-test/cpu-intensive  - CPU stress testing"
    echo ""
    
    echo "Production Endpoints (also tested):"
    echo "• POST /api/analyses                 - Real blockchain analysis"
    echo "• GET  /api/sentiment-chart/data     - Sentiment vs price data"
    echo "• POST /pdf/dashboard                - PDF generation"
    echo "• POST /verification/generate        - Verification badges"
    echo ""
}

# Show sample test results
show_sample_results() {
    log_section "📈 Sample Test Results"
    
    echo "Example Output from 500 Concurrent Test:"
    echo ""
    
    cat << 'EOF'
┌─────────────────────────────┬──────────────────────┐
│ Metric                      │ Value                │
├─────────────────────────────┼──────────────────────┤
│ Virtual Users Created       │ 15,000               │
│ HTTP Requests               │ 45,000               │
│ HTTP Responses              │ 44,775               │
│ Connection Errors           │ 25                   │
│ Latency P50 (ms)           │ 850                  │
│ Latency P95 (ms)           │ 2,400                │
│ Latency P99 (ms)           │ 4,200                │
│ Min Latency (ms)           │ 120                  │
│ Max Latency (ms)           │ 8,500                │
└─────────────────────────────┴──────────────────────┘
Error Rate: 0.5%

✅ All performance thresholds met!
🚀 500 concurrent analyses successfully completed!
EOF
    
    echo ""
    echo "Generated Reports:"
    echo "• HTML performance report"
    echo "• JSON raw results"
    echo "• CloudWatch metrics (if configured)"
    echo "• Consolidated summary report"
    echo ""
}

# Show monitoring integration
show_monitoring_integration() {
    log_section "🔍 Monitoring & Integration"
    
    echo "Real-time Monitoring:"
    echo "• Sentry error tracking during load tests"
    echo "• Laravel Telescope debugging (development)"
    echo "• CloudWatch metrics publishing"
    echo "• Custom performance metrics"
    echo ""
    
    echo "Automated Reporting:"
    echo "• Per-endpoint performance breakdown"
    echo "• System resource usage graphs"
    echo "• Error analysis and classification"
    echo "• Performance trend analysis"
    echo ""
    
    echo "CI/CD Integration:"
    echo "• GitHub Actions workflow ready"
    echo "• Automated regression testing"
    echo "• Performance baseline validation"
    echo "• Alert thresholds for degradation"
    echo ""
}

# Main demonstration
main() {
    local section=${1:-all}
    
    case $section in
        "config"|"configuration")
            show_artillery_config
            ;;
        "scenarios")
            show_test_scenarios
            ;;
        "targets"|"performance")
            show_performance_targets
            ;;
        "commands"|"execution")
            show_execution_commands
            ;;
        "endpoints")
            show_test_endpoints
            ;;
        "results"|"sample")
            show_sample_results
            ;;
        "monitoring")
            show_monitoring_integration
            ;;
        "all")
            show_artillery_config
            echo ""
            show_test_scenarios
            echo ""
            show_performance_targets
            echo ""
            show_execution_commands
            echo ""
            show_test_endpoints
            echo ""
            show_sample_results
            echo ""
            show_monitoring_integration
            
            echo ""
            log_success "🎉 Artillery Load Testing Demo Complete!"
            echo ""
            echo "Your AI Blockchain Analytics platform is equipped with:"
            echo "✅ 500 concurrent analysis load testing"
            echo "✅ Comprehensive performance monitoring"
            echo "✅ Realistic blockchain analysis scenarios"
            echo "✅ Automated test execution and reporting"
            echo "✅ Production-ready monitoring integration"
            echo ""
            echo "Ready to validate your platform under extreme load! 🚀"
            ;;
        "help"|"-h"|"--help")
            echo "Load Test Demo for AI Blockchain Analytics"
            echo ""
            echo "Usage: $0 [section]"
            echo ""
            echo "Sections:"
            echo "  config        Show Artillery configuration overview"
            echo "  scenarios     Show test scenarios and data"
            echo "  targets       Show performance targets and thresholds"
            echo "  commands      Show execution commands"
            echo "  endpoints     Show available test endpoints"
            echo "  results       Show sample test results"
            echo "  monitoring    Show monitoring integration"
            echo "  all           Show complete demonstration (default)"
            echo "  help          Show this help message"
            ;;
        *)
            log_warning "Unknown section: $section"
            echo "Use '$0 help' for available sections"
            exit 1
            ;;
    esac
}

# Run main function
main "$@"