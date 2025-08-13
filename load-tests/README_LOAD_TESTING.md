# AI Blockchain Analytics - 500 Concurrent Load Testing Suite

## Overview

This comprehensive load testing suite validates the AI Blockchain Analytics platform's ability to handle **500 concurrent smart contract analyses** under realistic conditions. The tests include multiple user behavior patterns, comprehensive monitoring, and automated analysis.

## Quick Start

### Prerequisites

1. **System Requirements:**
   - CPU: 4+ cores recommended
   - RAM: 8GB+ recommended  
   - File descriptors: `ulimit -n 65536`
   - Available ports: 8000 (app), 5432 (postgres), 6379 (redis)

2. **Software Dependencies:**
   ```bash
   # Install Artillery (if not already installed)
   npm install -g artillery
   
   # Install system monitoring tools
   sudo apt-get install sysstat bc
   ```

3. **Application Setup:**
   ```bash
   # Start Laravel application
   php artisan serve --port=8000
   
   # Ensure PostgreSQL is running
   sudo systemctl start postgresql
   
   # Ensure Redis is running  
   sudo systemctl start redis
   ```

### Running the Complete Test Suite

```bash
# Execute the full 500 concurrent test suite (~25 minutes)
./run-complete-500-test.sh
```

### Running Individual Tests

```bash
# Basic 500 concurrent test (~15 minutes)
./run-500-concurrent.sh

# Realistic behavior patterns test (~20 minutes)  
artillery run realistic-behavior-patterns.yml

# Performance monitoring only
./monitoring/performance-monitor.sh
```

## Test Configurations

### 1. Main Load Test (`ai-blockchain-500-concurrent.yml`)

**Target:** 500 concurrent analyses  
**Duration:** ~15 minutes  
**Phases:**
- Warmup: 30s (10 concurrent)
- Ramp to 100: 2 minutes 
- Scale to 300: 3 minutes
- Push to 500: 2 minutes  
- Sustained 500: 10 minutes
- Cool down: 1.5 minutes

**Scenarios:**
- **Sentiment Analysis (60%):** Primary analysis workflows
- **Verification (25%):** Badge and credibility systems  
- **PDF Generation (10%):** Report generation under load
- **Dashboard (5%):** Real-time data queries

### 2. Realistic Behavior Patterns (`realistic-behavior-patterns.yml`)

**Target:** Simulates real-world usage  
**Duration:** ~20 minutes  
**User Types:**
- **Power Users (15%):** Heavy analysis, multiple concurrent requests
- **Regular Traders (40%):** Standard daily analysis patterns
- **Casual Users (30%):** Light research and browsing
- **Verification Users (10%):** Badge system focus
- **API Heavy Users (5%):** Automated trading systems

### 3. Performance Monitoring (`monitoring/performance-monitor.sh`)

**Real-time tracking of:**
- System resources (CPU, memory, load, disk)
- Network connections and states
- PHP/RoadRunner processes
- Database connections and query performance
- Application response times
- Sentry error tracking
- Telescope monitoring entries

## Performance Targets

### Response Time Targets
- **P50 (Median):** < 3,000ms
- **P95:** < 8,000ms
- **P99:** < 15,000ms  
- **Timeout:** 180s max

### Throughput Targets
- **Request Rate:** > 300 req/sec
- **Success Rate:** > 75%
- **Error Rate:** < 10%

### System Resource Limits
- **CPU Usage:** < 90% sustained
- **Memory Usage:** < 85% sustained
- **Database Connections:** < 200 concurrent
- **Queue Backlog:** < 2,000 jobs

## Test Scenarios Explained

### Sentiment Analysis Pipeline (Primary - 60% of traffic)

```yaml
- post:
    url: "/api/sentiment/analyze"
    json:
      symbol: "BTC"
      analysis_type: "comprehensive"
      priority: "high"
      timeframe: "7d"
      sources: ["twitter", "reddit", "news"]
```

**Tests:** Core analysis functionality under heavy load

### Verification Workflow (25% of traffic)

```yaml
- post:
    url: "/api/verification/submit"
    json:
      type: "crypto_expert"
      verification_level: "enhanced"
```

**Tests:** Badge system scalability and authentication flow

### PDF Generation (10% of traffic)

```yaml  
- post:
    url: "/api/pdf/generate"
    json:
      report_type: "sentiment_analysis"
      include_charts: true
      format: "comprehensive"
```

**Tests:** Resource-intensive document generation under load

### Dashboard Data (5% of traffic)

```yaml
- get:
    url: "/api/dashboard/metrics"
    qs:
      timeframe: "7d"
      symbols: "BTC,ETH"
```

**Tests:** Real-time data aggregation and caching effectiveness

## Monitoring and Analysis

### Real-Time Dashboard

The performance monitor provides a live dashboard showing:

```
═══════════════════════════════════════════════════════════════
                AI Blockchain Analytics - Performance Monitor
                              500 Concurrent Load Test  
═══════════════════════════════════════════════════════════════

📊 System Resources                    🚀 Application Metrics
CPU Usage:      45.2%                  PHP Processes:   24
Memory Usage:   67.8% (5.4GB)          Queue Size:      156
Load Average:   2.1, 1.8, 1.2          Failed Jobs:     0
Disk I/O:       R:245 W:89 KB/s        Response Time:   1,245ms

🗄️  Database & Cache
PostgreSQL Connections:  87
Redis Memory Usage:      234MB
Redis Clients:           45
Redis Ops/sec:           1,247
```

### Generated Reports

After test completion, the following reports are generated:

1. **FINAL_LOAD_TEST_REPORT.md** - Executive summary and recommendations
2. **load_test_report.html** - Interactive Artillery report with charts
3. **behavior_test_report.html** - User behavior pattern analysis
4. **analysis_results.md** - Detailed performance analysis
5. **CSV Data Files** - Raw monitoring data for further analysis

### Key Metrics Tracked

**Application Metrics:**
- Request rates and response times by endpoint
- Error rates and failure patterns  
- Queue performance and job processing
- Cache hit rates and effectiveness

**System Metrics:**
- CPU, memory, and disk utilization
- Network connections and bandwidth
- Database query performance
- Redis operations and memory usage

**User Experience Metrics:**
- End-to-end workflow completion rates
- User type behavior patterns
- Peak concurrent user handling
- Error recovery and graceful degradation

## Troubleshooting

### Common Issues

**1. Connection Refused Errors**
```bash
# Check application is running
curl http://localhost:8000
# Expected: HTML response

# Check database
pg_isready -h localhost -p 5432
# Expected: accepting connections

# Check Redis  
redis-cli ping
# Expected: PONG
```

**2. File Descriptor Limits**
```bash
# Check current limit
ulimit -n

# Increase limit (temporary)
ulimit -n 65536

# Permanent increase (add to ~/.bashrc)
echo "ulimit -n 65536" >> ~/.bashrc
```

**3. High Memory Usage**
```bash
# Monitor memory during test
watch -n 1 free -h

# Check for memory leaks
ps aux --sort=-%mem | head -10

# Clear caches if needed
php artisan cache:clear
php artisan config:clear
```

**4. Database Connection Issues**
```bash
# Check PostgreSQL connections
psql -h localhost -U postgres -d ai_blockchain_analytics -c "
  SELECT count(*) as connections 
  FROM pg_stat_activity 
  WHERE datname = 'ai_blockchain_analytics';"

# Check for long-running queries
psql -h localhost -U postgres -d ai_blockchain_analytics -c "
  SELECT query, state, query_start 
  FROM pg_stat_activity 
  WHERE state = 'active' 
  ORDER BY query_start;"
```

### Performance Optimization Tips

**1. Application Level:**
```bash
# Enable OPcache
php -m | grep -i opcache

# Clear and warm caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

**2. Database Optimization:**
```sql
-- Check slow queries
SELECT query, mean_time, calls 
FROM pg_stat_statements 
ORDER BY mean_time DESC 
LIMIT 10;

-- Check index usage
SELECT schemaname, tablename, indexname, idx_tup_read, idx_tup_fetch
FROM pg_stat_user_indexes
ORDER BY idx_tup_read DESC;
```

**3. Redis Optimization:**
```bash
# Check Redis performance
redis-cli info stats | grep instantaneous_ops_per_sec

# Monitor memory usage  
redis-cli info memory | grep used_memory_human

# Check slow queries
redis-cli slowlog get 10
```

## Interpreting Results

### Success Criteria

✅ **PASSED** if:
- 95% of requests complete within target response times
- Error rate remains below 10%  
- System remains stable throughout test
- No critical resource exhaustion

⚠️ **ISSUES** if:
- Response times exceed targets but system remains stable
- Error rate between 10-20%
- Some resource constraints but no failures

❌ **FAILED** if:
- Error rate exceeds 20%
- System crashes or becomes unresponsive  
- Critical resource exhaustion (OOM, connection limits)

### Scaling Recommendations

Based on test results:

**< 70% resource utilization:** System can handle more load
**70-85% utilization:** Consider scaling for production safety margin  
**> 85% utilization:** Immediate scaling required for production use

## Production Deployment Considerations

### Infrastructure Sizing

Based on 500 concurrent user test results:

```yaml
# Kubernetes Resource Requests/Limits
resources:
  requests:
    cpu: "2000m"      # 2 CPU cores minimum
    memory: "4Gi"     # 4GB RAM minimum  
  limits:
    cpu: "4000m"      # 4 CPU cores maximum
    memory: "8Gi"     # 8GB RAM maximum

# Database Sizing  
postgresql:
  instance_class: "db.t3.xlarge"  # 4 vCPU, 16GB RAM
  max_connections: 200
  shared_buffers: "4GB"

# Redis Sizing
redis:
  node_type: "cache.m5.large"     # 2 vCPU, 6.38GB RAM  
  memory_policy: "allkeys-lru"
```

### Monitoring Alerts

Set up alerts for:
- Response time P95 > 5,000ms
- Error rate > 5%
- CPU usage > 80% for 5+ minutes
- Memory usage > 80%
- Database connections > 150
- Queue size > 1,000 jobs

## Files Structure

```
load-tests/
├── README_LOAD_TESTING.md              # This file
├── ai-blockchain-500-concurrent.yml    # Main load test config
├── realistic-behavior-patterns.yml     # User behavior simulation
├── run-complete-500-test.sh            # Complete test suite
├── run-500-concurrent.sh               # Basic load test runner  
├── monitoring/
│   └── performance-monitor.sh          # Real-time monitoring
├── data/
│   └── contract-samples.json           # Test data
└── reports/                            # Generated reports
    └── [timestamp]/
        ├── raw/                        # Raw test data
        ├── processed/                  # HTML reports  
        ├── logs/                       # Execution logs
        └── FINAL_LOAD_TEST_REPORT.md   # Summary report
```

## Support

For issues or questions:
1. Check troubleshooting section above
2. Review generated log files in `reports/[timestamp]/logs/`
3. Examine monitoring data for resource bottlenecks
4. Check application logs: `tail -f storage/logs/laravel.log`

---

**Ready to test 500 concurrent AI blockchain analyses? Run:**
```bash
./run-complete-500-test.sh
```