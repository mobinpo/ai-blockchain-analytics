# AI Blockchain Analytics - Load Testing Suite

## 🎯 Overview

Comprehensive Artillery-based load testing suite designed to test 500 concurrent smart contract analyses for the AI Blockchain Analytics platform.

## 📁 Directory Structure

```
load-tests/
├── artillery-config.yml          # Main Artillery configuration (gradual ramp-up)
├── concurrent-500.yml            # Optimized config for 500 concurrent users
├── run-500-concurrent.sh         # Main test execution script
├── data/
│   └── contract-samples.json     # Realistic smart contract test data
├── scripts/
│   └── monitor-performance.sh    # Real-time performance monitoring
├── reports/                      # Generated test reports (created during tests)
└── README.md                     # This file
```

## 🚀 Quick Start

### 1. Prerequisites
```bash
# Install Node.js dependencies
npm install

# Ensure Laravel application is running
php artisan serve --host=0.0.0.0 --port=8000
```

### 2. Run the Load Test
```bash
# Navigate to load tests directory
cd load-tests

# Execute 500 concurrent analysis test
./run-500-concurrent.sh
```

### 3. Monitor Performance (Optional)
```bash
# In a separate terminal, run performance monitor
./scripts/monitor-performance.sh
```

## ⚡ Test Configurations

### Main Configuration (`artillery-config.yml`)
- **Gradual ramp-up approach**
- 5 phases over ~20 minutes
- Realistic user behavior simulation
- Comprehensive monitoring

### High-Throughput Configuration (`concurrent-500.yml`)
- **Optimized for 500 concurrent users**
- Quick scaling to target load
- 15-minute total test duration
- Maximum throughput testing

## 📊 Test Scenarios

### Smart Contract Analysis Workflow (70% weight)
- Full security audit pipeline
- Vulnerability scanning
- Gas optimization analysis
- Compliance checking

### Quick Contract Scan (20% weight)
- Basic security checks
- Fast analysis turnaround
- Lightweight processing

### Bulk Analysis (10% weight)
- Multiple contract processing
- Batch operations
- High-volume scenarios

## 🔧 Performance Thresholds

| Metric | Target | Critical |
|--------|--------|----------|
| Request Rate | >450 req/sec | >400 req/sec |
| 95th Percentile Response Time | <5000ms | <8000ms |
| 99th Percentile Response Time | <10000ms | <15000ms |
| Success Rate | >85% | >80% |
| Error Rate | <5% | <10% |

## 📈 Test Data

### Contract Sample Data
- 15 realistic smart contracts
- Multiple blockchain networks (Ethereum, Polygon, BSC)
- Various contract types (tokens, DeFi, NFTs)
- Different analysis priorities
- Authentic Solidity source code

### Blockchain Networks Tested
- **Ethereum Mainnet** (Chain ID: 1)
- **Polygon** (Chain ID: 137)  
- **Binance Smart Chain** (Chain ID: 56)

## 🖥️ System Requirements

### Recommended System Specs
- **CPU:** 4+ cores
- **RAM:** 8GB+ available
- **Network:** Stable connection
- **File Descriptors:** 65536+ (ulimit -n)

### Laravel Application Requirements
- **Database:** Optimized connections
- **Cache:** Redis/Memcached configured
- **Queue:** Workers running
- **Logs:** Sufficient disk space

## 📋 Test Execution Phases

### Phase 1: Warm-up (60-240 seconds)
- Gradual scaling from 0 to 250 users
- System preparation and cache warming
- Initial performance baseline

### Phase 2: Target Load (240 seconds)
- Scale to 500 concurrent users
- Reach peak performance testing
- Monitor for bottlenecks

### Phase 3: Sustained Load (600 seconds)
- Maintain 500 concurrent analyses
- Stress test system stability
- Long-duration performance validation

### Phase 4: Cool-down (60-120 seconds)
- Graceful scaling down
- System recovery monitoring
- Resource cleanup

## 📊 Generated Reports

### Automatic Report Generation
- **JSON Report:** Raw performance metrics
- **HTML Report:** Visual performance dashboard
- **Summary Report:** Executive summary with key findings
- **Performance Log:** Real-time system metrics

### Report Contents
- Request/response statistics
- Latency percentiles (p50, p95, p99)
- Error rates and types
- System resource utilization
- Timeline performance graphs

## 🔍 Monitoring Features

### Real-time Metrics
- CPU and memory usage
- Network connections
- Application response status
- Load averages
- Disk utilization

### Performance Tracking
- Request rate monitoring
- Response time trends
- Error rate analysis
- System health checks

## 🐛 Troubleshooting

### Common Issues

#### High Response Times
```bash
# Check database performance
php artisan horizon:status
mysql -e "SHOW PROCESSLIST;"

# Monitor system resources
htop
iostat -x 1
```

#### Connection Errors
```bash
# Increase system limits
ulimit -n 65536
echo 'net.core.somaxconn = 65536' >> /etc/sysctl.conf
sysctl -p
```

#### Memory Issues
```bash
# Check Laravel memory usage
php artisan route:cache
php artisan config:cache
php artisan view:cache

# Monitor memory consumption
watch free -h
```

## 🎛️ Customization

### Modify Test Parameters
```yaml
# In concurrent-500.yml
phases:
  - duration: 240      # Ramp-up time
    arrivalRate: 250   # Starting rate
    rampTo: 500       # Target concurrent users
```

### Add Custom Scenarios
```yaml
scenarios:
  - name: "Custom Analysis Flow"
    weight: 30
    flow:
      - post:
          url: "/api/custom-analysis"
          json:
            # Custom request data
```

### Adjust Performance Thresholds
```yaml
ensure:
  - http.request_rate > 400
  - http.response_time.p95 < 8000
  - http.codes.200 > 80
```

## 📝 Best Practices

### Before Testing
1. **Prepare Infrastructure**
   - Scale database connections
   - Configure caching layers
   - Optimize application code

2. **System Preparation**
   - Increase file descriptor limits
   - Tune TCP/IP settings
   - Monitor disk space

3. **Application Readiness**
   - Clear and warm caches
   - Start queue workers
   - Enable error logging

### During Testing
1. **Monitor Resources**
   - Watch CPU/memory usage
   - Monitor database performance
   - Check error logs

2. **Performance Validation**
   - Verify response accuracy
   - Monitor error rates
   - Check system stability

### After Testing
1. **Analysis**
   - Review detailed reports
   - Identify bottlenecks
   - Plan optimizations

2. **Documentation**
   - Record findings
   - Update configurations
   - Share results with team

## 🔧 Advanced Configuration

### Environment Variables
```bash
# Custom target URL
export ARTILLERY_TARGET="https://your-staging-server.com"

# Custom test duration
export TEST_DURATION=1800  # 30 minutes

# Custom concurrent users
export MAX_CONCURRENT=1000
```

### AWS CloudWatch Integration
```yaml
plugins:
  cloudwatch:
    namespace: 'AI-Blockchain-Analytics/LoadTest'
    region: 'us-east-1'
    enabled: true
```

### Custom Reporting
```bash
# Generate custom reports
artillery report --output custom-report.html results.json

# Extract specific metrics
jq '.aggregate.http.response_time' results.json
```

## 📞 Support

### Getting Help
- Check Laravel logs: `tail -f storage/logs/laravel.log`
- Monitor system resources: `htop`, `iostat`
- Review Artillery documentation: https://artillery.io/docs

### Performance Optimization Resources
- Laravel Performance: https://laravel.com/docs/deployment#optimization
- Database Tuning: https://dev.mysql.com/doc/refman/8.0/en/optimization.html
- System Tuning: https://www.kernel.org/doc/Documentation/networking/scaling.txt

---

## 🎯 Example Commands

```bash
# Run basic load test
./run-500-concurrent.sh

# Run with custom configuration
artillery run artillery-config.yml --output custom-results.json

# Generate HTML report from existing results
artillery report --output report.html results.json

# Monitor performance during test
./scripts/monitor-performance.sh

# Check Artillery version and plugins
artillery --version
artillery run --help
```

---

**Created for AI Blockchain Analytics Platform**  
**Target: 500 Concurrent Smart Contract Analyses**