/**
 * Performance Monitor for Artillery Load Tests
 * Real-time monitoring and reporting for AI Blockchain Analytics
 */

const fs = require('fs');
const path = require('path');
const { exec } = require('child_process');

class PerformanceMonitor {
    constructor() {
        this.metrics = {
            startTime: Date.now(),
            endTime: null,
            requests: {
                total: 0,
                successful: 0,
                failed: 0,
                byEndpoint: {},
                byStatusCode: {}
            },
            responseTime: {
                min: Infinity,
                max: 0,
                avg: 0,
                median: 0,
                p95: 0,
                p99: 0,
                samples: []
            },
            throughput: {
                rps: 0,
                peakRps: 0,
                samples: []
            },
            errors: {
                byType: {},
                byEndpoint: {},
                details: []
            },
            systemMetrics: {
                cpu: [],
                memory: [],
                network: []
            },
            customMetrics: {
                analysisRequests: 0,
                sentimentRequests: 0,
                verificationRequests: 0,
                pdfRequests: 0,
                concurrentUsers: 0
            }
        };
        
        this.intervals = [];
        this.isMonitoring = false;
    }

    start() {
        this.isMonitoring = true;
        this.metrics.startTime = Date.now();
        
        console.log('=€ Starting performance monitoring...');
        
        // Monitor system metrics every 5 seconds
        this.intervals.push(setInterval(() => {
            this.collectSystemMetrics();
        }, 5000));
        
        // Calculate throughput every second
        this.intervals.push(setInterval(() => {
            this.calculateThroughput();
        }, 1000));
        
        // Real-time dashboard update every 10 seconds
        this.intervals.push(setInterval(() => {
            this.displayRealTimeStats();
        }, 10000));
    }

    stop() {
        this.isMonitoring = false;
        this.metrics.endTime = Date.now();
        
        this.intervals.forEach(interval => clearInterval(interval));
        this.intervals = [];
        
        console.log('=Ñ Stopping performance monitoring...');
        this.generateFinalReport();
    }

    recordRequest(endpoint, responseTime, statusCode, error = null) {
        this.metrics.requests.total++;
        
        // Track by endpoint
        if (!this.metrics.requests.byEndpoint[endpoint]) {
            this.metrics.requests.byEndpoint[endpoint] = {
                total: 0,
                successful: 0,
                failed: 0,
                avgResponseTime: 0,
                responseTimes: []
            };
        }
        this.metrics.requests.byEndpoint[endpoint].total++;
        this.metrics.requests.byEndpoint[endpoint].responseTimes.push(responseTime);
        
        // Track by status code
        if (!this.metrics.requests.byStatusCode[statusCode]) {
            this.metrics.requests.byStatusCode[statusCode] = 0;
        }
        this.metrics.requests.byStatusCode[statusCode]++;
        
        // Track success/failure
        if (statusCode >= 200 && statusCode < 400) {
            this.metrics.requests.successful++;
            this.metrics.requests.byEndpoint[endpoint].successful++;
        } else {
            this.metrics.requests.failed++;
            this.metrics.requests.byEndpoint[endpoint].failed++;
        }
        
        // Record response time
        this.metrics.responseTime.samples.push(responseTime);
        this.metrics.responseTime.min = Math.min(this.metrics.responseTime.min, responseTime);
        this.metrics.responseTime.max = Math.max(this.metrics.responseTime.max, responseTime);
        
        // Record errors
        if (error || statusCode >= 400) {
            this.recordError(endpoint, statusCode, error);
        }
        
        // Track custom metrics
        this.trackCustomMetrics(endpoint);
    }

    trackCustomMetrics(endpoint) {
        if (endpoint.includes('analyze')) {
            this.metrics.customMetrics.analysisRequests++;
        } else if (endpoint.includes('sentiment')) {
            this.metrics.customMetrics.sentimentRequests++;
        } else if (endpoint.includes('verification')) {
            this.metrics.customMetrics.verificationRequests++;
        } else if (endpoint.includes('pdf')) {
            this.metrics.customMetrics.pdfRequests++;
        }
    }

    recordError(endpoint, statusCode, error) {
        const errorType = this.categorizeError(statusCode, error);
        
        if (!this.metrics.errors.byType[errorType]) {
            this.metrics.errors.byType[errorType] = 0;
        }
        this.metrics.errors.byType[errorType]++;
        
        if (!this.metrics.errors.byEndpoint[endpoint]) {
            this.metrics.errors.byEndpoint[endpoint] = 0;
        }
        this.metrics.errors.byEndpoint[endpoint]++;
        
        this.metrics.errors.details.push({
            timestamp: Date.now(),
            endpoint,
            statusCode,
            error: error ? error.message : null,
            type: errorType
        });
        
        // Keep only last 100 error details
        if (this.metrics.errors.details.length > 100) {
            this.metrics.errors.details.shift();
        }
    }

    categorizeError(statusCode, error) {
        if (statusCode >= 500) return 'server_error';
        if (statusCode === 429) return 'rate_limit';
        if (statusCode === 422) return 'validation_error';
        if (statusCode === 404) return 'not_found';
        if (statusCode >= 400) return 'client_error';
        if (error && error.code === 'ECONNREFUSED') return 'connection_error';
        if (error && error.code === 'ETIMEDOUT') return 'timeout_error';
        return 'unknown_error';
    }

    collectSystemMetrics() {
        // Collect CPU usage
        exec('top -bn1 | grep "Cpu(s)" | awk \'{print $2}\' | awk -F\'%\' \'{print $1}\'', (error, stdout) => {
            if (!error) {
                const cpuUsage = parseFloat(stdout.trim());
                this.metrics.systemMetrics.cpu.push({
                    timestamp: Date.now(),
                    usage: cpuUsage
                });
            }
        });
        
        // Collect memory usage
        exec('free -m | grep Mem | awk \'{print ($3/$2)*100}\'', (error, stdout) => {
            if (!error) {
                const memoryUsage = parseFloat(stdout.trim());
                this.metrics.systemMetrics.memory.push({
                    timestamp: Date.now(),
                    usage: memoryUsage
                });
            }
        });
        
        // Keep only last 100 samples
        ['cpu', 'memory', 'network'].forEach(metric => {
            if (this.metrics.systemMetrics[metric].length > 100) {
                this.metrics.systemMetrics[metric] = this.metrics.systemMetrics[metric].slice(-100);
            }
        });
    }

    calculateThroughput() {
        const now = Date.now();
        const oneSecondAgo = now - 1000;
        
        // Count requests in the last second
        const recentRequests = this.metrics.responseTime.samples.filter((_, index) => {
            // Approximate timestamp based on when samples were added
            const sampleTime = this.metrics.startTime + (index * 1000 / this.metrics.requests.total);
            return sampleTime >= oneSecondAgo;
        });
        
        const currentRps = recentRequests.length;
        this.metrics.throughput.rps = currentRps;
        this.metrics.throughput.peakRps = Math.max(this.metrics.throughput.peakRps, currentRps);
        
        this.metrics.throughput.samples.push({
            timestamp: now,
            rps: currentRps
        });
        
        // Keep only last 300 samples (5 minutes)
        if (this.metrics.throughput.samples.length > 300) {
            this.metrics.throughput.samples.shift();
        }
    }

    calculatePercentiles() {
        const samples = this.metrics.responseTime.samples.sort((a, b) => a - b);
        const length = samples.length;
        
        if (length === 0) return;
        
        this.metrics.responseTime.avg = samples.reduce((sum, val) => sum + val, 0) / length;
        this.metrics.responseTime.median = samples[Math.floor(length * 0.5)];
        this.metrics.responseTime.p95 = samples[Math.floor(length * 0.95)];
        this.metrics.responseTime.p99 = samples[Math.floor(length * 0.99)];
    }

    displayRealTimeStats() {
        this.calculatePercentiles();
        
        const duration = (Date.now() - this.metrics.startTime) / 1000;
        const successRate = ((this.metrics.requests.successful / this.metrics.requests.total) * 100).toFixed(2);
        
        console.clear();
        console.log('PPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPP');
        console.log('=€ AI BLOCKCHAIN ANALYTICS - LOAD TEST DASHBOARD');
        console.log('PPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPP');
        console.log(`ñ  Duration: ${Math.floor(duration / 60)}m ${Math.floor(duration % 60)}s`);
        console.log(`=Ê Total Requests: ${this.metrics.requests.total}`);
        console.log(` Success Rate: ${successRate}%`);
        console.log(`=% Current RPS: ${this.metrics.throughput.rps}`);
        console.log(`=È Peak RPS: ${this.metrics.throughput.peakRps}`);
        console.log('');
        
        console.log('=Ê RESPONSE TIMES:');
        console.log(`   Min: ${this.metrics.responseTime.min}ms`);
        console.log(`   Avg: ${Math.round(this.metrics.responseTime.avg)}ms`);
        console.log(`   Med: ${Math.round(this.metrics.responseTime.median)}ms`);
        console.log(`   P95: ${Math.round(this.metrics.responseTime.p95)}ms`);
        console.log(`   P99: ${Math.round(this.metrics.responseTime.p99)}ms`);
        console.log(`   Max: ${this.metrics.responseTime.max}ms`);
        console.log('');
        
        console.log('<¯ CUSTOM METRICS:');
        console.log(`   Analysis Requests: ${this.metrics.customMetrics.analysisRequests}`);
        console.log(`   Sentiment Requests: ${this.metrics.customMetrics.sentimentRequests}`);
        console.log(`   Verification Requests: ${this.metrics.customMetrics.verificationRequests}`);
        console.log(`   PDF Requests: ${this.metrics.customMetrics.pdfRequests}`);
        console.log('');
        
        // Show top errors if any
        if (this.metrics.requests.failed > 0) {
            console.log('L ERROR SUMMARY:');
            Object.entries(this.metrics.errors.byType).forEach(([type, count]) => {
                console.log(`   ${type}: ${count}`);
            });
            console.log('');
        }
        
        // Show system metrics if available
        const latestCpu = this.metrics.systemMetrics.cpu[this.metrics.systemMetrics.cpu.length - 1];
        const latestMemory = this.metrics.systemMetrics.memory[this.metrics.systemMetrics.memory.length - 1];
        
        if (latestCpu || latestMemory) {
            console.log('=¥  SYSTEM METRICS:');
            if (latestCpu) console.log(`   CPU Usage: ${latestCpu.usage.toFixed(1)}%`);
            if (latestMemory) console.log(`   Memory Usage: ${latestMemory.usage.toFixed(1)}%`);
            console.log('');
        }
        
        console.log('PPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPP');
    }

    generateFinalReport() {
        this.calculatePercentiles();
        
        const duration = (this.metrics.endTime - this.metrics.startTime) / 1000;
        const avgRps = this.metrics.requests.total / duration;
        
        const report = {
            summary: {
                testDuration: `${Math.floor(duration / 60)}m ${Math.floor(duration % 60)}s`,
                totalRequests: this.metrics.requests.total,
                successfulRequests: this.metrics.requests.successful,
                failedRequests: this.metrics.requests.failed,
                successRate: ((this.metrics.requests.successful / this.metrics.requests.total) * 100).toFixed(2) + '%',
                averageRps: avgRps.toFixed(2),
                peakRps: this.metrics.throughput.peakRps
            },
            performance: {
                responseTime: {
                    min: this.metrics.responseTime.min,
                    average: Math.round(this.metrics.responseTime.avg),
                    median: Math.round(this.metrics.responseTime.median),
                    p95: Math.round(this.metrics.responseTime.p95),
                    p99: Math.round(this.metrics.responseTime.p99),
                    max: this.metrics.responseTime.max
                }
            },
            endpointBreakdown: {},
            customMetrics: this.metrics.customMetrics,
            errors: {
                total: this.metrics.requests.failed,
                byType: this.metrics.errors.byType,
                byEndpoint: this.metrics.errors.byEndpoint,
                recentErrors: this.metrics.errors.details.slice(-10) // Last 10 errors
            },
            systemMetrics: {
                cpu: this.calculateAverageMetric(this.metrics.systemMetrics.cpu),
                memory: this.calculateAverageMetric(this.metrics.systemMetrics.memory)
            },
            timestamp: new Date().toISOString()
        };
        
        // Calculate endpoint breakdown
        Object.entries(this.metrics.requests.byEndpoint).forEach(([endpoint, data]) => {
            const avgResponseTime = data.responseTimes.reduce((sum, val) => sum + val, 0) / data.responseTimes.length;
            report.endpointBreakdown[endpoint] = {
                requests: data.total,
                successRate: ((data.successful / data.total) * 100).toFixed(2) + '%',
                averageResponseTime: Math.round(avgResponseTime)
            };
        });
        
        // Save detailed report
        const reportFilename = `load-test-report-${Date.now()}.json`;
        fs.writeFileSync(reportFilename, JSON.stringify(report, null, 2));
        
        // Generate human-readable summary
        this.generateHumanReadableReport(report);
        
        console.log('\n=Ê FINAL PERFORMANCE REPORT');
        console.log('PPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPP');
        console.log(`=Á Detailed report saved: ${reportFilename}`);
        console.log(`ñ  Test Duration: ${report.summary.testDuration}`);
        console.log(`=Ê Total Requests: ${report.summary.totalRequests}`);
        console.log(` Success Rate: ${report.summary.successRate}`);
        console.log(`=% Average RPS: ${report.summary.averageRps}`);
        console.log(`=È Peak RPS: ${report.summary.peakRps}`);
        console.log(`¡ P95 Response Time: ${report.performance.responseTime.p95}ms`);
        console.log(`¡ P99 Response Time: ${report.performance.responseTime.p99}ms`);
        
        if (report.summary.failedRequests > 0) {
            console.log(`L Failed Requests: ${report.summary.failedRequests}`);
            console.log('   Top Errors:', Object.entries(report.errors.byType).slice(0, 3));
        }
        
        console.log('PPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPP');
    }

    generateHumanReadableReport(report) {
        const content = `
# Load Test Report - AI Blockchain Analytics
Generated: ${new Date().toISOString()}

## Summary
- **Test Duration**: ${report.summary.testDuration}
- **Total Requests**: ${report.summary.totalRequests}
- **Success Rate**: ${report.summary.successRate}
- **Average RPS**: ${report.summary.averageRps}
- **Peak RPS**: ${report.summary.peakRps}

## Performance Metrics
- **Min Response Time**: ${report.performance.responseTime.min}ms
- **Average Response Time**: ${report.performance.responseTime.average}ms
- **Median Response Time**: ${report.performance.responseTime.median}ms
- **95th Percentile**: ${report.performance.responseTime.p95}ms
- **99th Percentile**: ${report.performance.responseTime.p99}ms
- **Max Response Time**: ${report.performance.responseTime.max}ms

## Custom Blockchain Analytics Metrics
- **Analysis Requests**: ${report.customMetrics.analysisRequests}
- **Sentiment Requests**: ${report.customMetrics.sentimentRequests}
- **Verification Requests**: ${report.customMetrics.verificationRequests}
- **PDF Generation Requests**: ${report.customMetrics.pdfRequests}

## Endpoint Performance
${Object.entries(report.endpointBreakdown).map(([endpoint, stats]) => 
    `- **${endpoint}**: ${stats.requests} requests, ${stats.successRate} success rate, ${stats.averageResponseTime}ms avg`
).join('\n')}

## Error Analysis
${report.errors.total > 0 ? `
- **Total Errors**: ${report.errors.total}
- **Error Types**: ${Object.entries(report.errors.byType).map(([type, count]) => `${type}: ${count}`).join(', ')}
` : '- **No errors detected** '}

## System Resource Usage
${report.systemMetrics.cpu ? `- **Average CPU Usage**: ${report.systemMetrics.cpu.toFixed(1)}%` : ''}
${report.systemMetrics.memory ? `- **Average Memory Usage**: ${report.systemMetrics.memory.toFixed(1)}%` : ''}

---
*Report generated by Artillery Performance Monitor for AI Blockchain Analytics*
        `.trim();
        
        const summaryFilename = `load-test-summary-${Date.now()}.md`;
        fs.writeFileSync(summaryFilename, content);
        console.log(`=Ä Human-readable summary: ${summaryFilename}`);
    }

    calculateAverageMetric(samples) {
        if (samples.length === 0) return null;
        return samples.reduce((sum, sample) => sum + sample.usage, 0) / samples.length;
    }
}

// Export for use in other modules
module.exports = PerformanceMonitor;

// If running directly, start monitoring
if (require.main === module) {
    const monitor = new PerformanceMonitor();
    monitor.start();
    
    // Stop monitoring on process termination
    process.on('SIGINT', () => monitor.stop());
    process.on('SIGTERM', () => monitor.stop());
}