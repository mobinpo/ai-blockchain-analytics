// Load test processor for Artillery
// Provides custom functions and monitoring during load tests

const fs = require('fs');
const path = require('path');

let testResults = {
    startTime: Date.now(),
    requests: [],
    errors: [],
    performance: {},
    systemMetrics: []
};

// Initialize logging
function initializeLogging() {
    const logDir = path.join(__dirname, 'results');
    if (!fs.existsSync(logDir)) {
        fs.mkdirSync(logDir, { recursive: true });
    }
}

// Log request start time
function logRequestStart(requestParams, context, ee, next) {
    context.vars.requestStartTime = Date.now();
    return next();
}

// Log request completion and metrics  
function logRequestEnd(requestParams, response, context, ee, next) {
    const endTime = Date.now();
    const duration = endTime - context.vars.requestStartTime;
    
    const requestData = {
        url: requestParams.url,
        method: requestParams.method || 'GET',
        statusCode: response.statusCode,
        duration: duration,
        timestamp: endTime,
        contentLength: response.headers['content-length'] || 0,
        responseTime: duration
    };
    
    testResults.requests.push(requestData);
    
    // Log errors
    if (response.statusCode >= 400) {
        testResults.errors.push({
            ...requestData,
            error: `HTTP ${response.statusCode}`,
            body: response.body ? response.body.substring(0, 500) : ''
        });
    }
    
    // Capture system metrics from response headers
    if (response.headers['x-response-time']) {
        context.vars.server_response_time = response.headers['x-response-time'];
    }
    
    if (response.headers['x-memory-usage']) {
        context.vars.server_memory = response.headers['x-memory-usage'];
    }
    
    return next();
}

// Monitor system performance
function monitorSystem(context, events, done) {
    const interval = setInterval(() => {
        const now = Date.now();
        const recentRequests = testResults.requests.filter(r => 
            now - r.timestamp < 10000 // Last 10 seconds
        );
        
        if (recentRequests.length > 0) {
            const avgResponseTime = recentRequests.reduce((sum, r) => sum + r.duration, 0) / recentRequests.length;
            const errorRate = testResults.errors.filter(e => 
                now - e.timestamp < 10000
            ).length / recentRequests.length;
            
            const systemMetric = {
                timestamp: now,
                avgResponseTime: avgResponseTime,
                errorRate: errorRate,
                requestsPerSecond: recentRequests.length / 10,
                totalRequests: testResults.requests.length,
                totalErrors: testResults.errors.length
            };
            
            testResults.systemMetrics.push(systemMetric);
            
            // Console output for real-time monitoring
            console.log(`[${new Date().toISOString()}] RPS: ${systemMetric.requestsPerSecond.toFixed(1)}, Avg Response: ${avgResponseTime.toFixed(0)}ms, Error Rate: ${(errorRate * 100).toFixed(1)}%`);
            
            // Alert on high error rate
            if (errorRate > 0.05) { // 5% error rate
                console.warn(`⚠️  HIGH ERROR RATE: ${(errorRate * 100).toFixed(1)}% - Consider stopping test`);
            }
            
            // Alert on high response time
            if (avgResponseTime > 3000) { // 3 second response time
                console.warn(`⚠️  HIGH RESPONSE TIME: ${avgResponseTime.toFixed(0)}ms - Performance degraded`);
            }
        }
    }, 10000); // Every 10 seconds
    
    events.on('done', () => {
        clearInterval(interval);
        generateReport();
        done();
    });
}

// Generate comprehensive test report
function generateReport() {
    const endTime = Date.now();
    const totalDuration = endTime - testResults.startTime;
    
    const summary = {
        testDuration: totalDuration,
        totalRequests: testResults.requests.length,
        totalErrors: testResults.errors.length,
        errorRate: testResults.errors.length / testResults.requests.length,
        avgResponseTime: testResults.requests.reduce((sum, r) => sum + r.duration, 0) / testResults.requests.length,
        maxResponseTime: Math.max(...testResults.requests.map(r => r.duration)),
        minResponseTime: Math.min(...testResults.requests.map(r => r.duration)),
        requestsPerSecond: testResults.requests.length / (totalDuration / 1000)
    };
    
    // Calculate percentiles
    const sortedTimes = testResults.requests.map(r => r.duration).sort((a, b) => a - b);
    summary.p50 = sortedTimes[Math.floor(sortedTimes.length * 0.5)];
    summary.p95 = sortedTimes[Math.floor(sortedTimes.length * 0.95)];
    summary.p99 = sortedTimes[Math.floor(sortedTimes.length * 0.99)];
    
    // Group errors by type
    const errorsByType = {};
    testResults.errors.forEach(error => {
        const key = `${error.method} ${error.url} - ${error.statusCode}`;
        errorsByType[key] = (errorsByType[key] || 0) + 1;
    });
    
    // Create detailed report
    const report = {
        summary,
        errorsByType,
        systemMetrics: testResults.systemMetrics,
        recommendations: generateRecommendations(summary),
        timestamp: new Date().toISOString()
    };
    
    // Write report to file
    const reportPath = path.join(__dirname, 'results', `load-test-report-${Date.now()}.json`);
    fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));
    
    // Write CSV for analysis
    const csvPath = path.join(__dirname, 'results', `requests-${Date.now()}.csv`);
    const csvContent = 'timestamp,url,method,statusCode,duration,contentLength\n' + 
        testResults.requests.map(r => 
            `${r.timestamp},${r.url},${r.method},${r.statusCode},${r.duration},${r.contentLength}`
        ).join('\n');
    fs.writeFileSync(csvPath, csvContent);
    
    console.log('\n📊 LOAD TEST SUMMARY');
    console.log('====================');
    console.log(`Duration: ${(totalDuration / 1000).toFixed(1)}s`);
    console.log(`Total Requests: ${summary.totalRequests}`);
    console.log(`Error Rate: ${(summary.errorRate * 100).toFixed(2)}%`);
    console.log(`Avg Response Time: ${summary.avgResponseTime.toFixed(0)}ms`);
    console.log(`P95 Response Time: ${summary.p95}ms`);
    console.log(`P99 Response Time: ${summary.p99}ms`);
    console.log(`Requests/Second: ${summary.requestsPerSecond.toFixed(1)}`);
    console.log(`Report saved: ${reportPath}`);
    
    if (Object.keys(errorsByType).length > 0) {
        console.log('\n❌ TOP ERRORS:');
        Object.entries(errorsByType)
            .sort(([,a], [,b]) => b - a)
            .slice(0, 5)
            .forEach(([error, count]) => {
                console.log(`  ${count}x ${error}`);
            });
    }
    
    console.log('\n💡 RECOMMENDATIONS:');
    report.recommendations.forEach(rec => console.log(`  • ${rec}`));
}

// Generate performance recommendations
function generateRecommendations(summary) {
    const recommendations = [];
    
    if (summary.errorRate > 0.01) {
        recommendations.push(`High error rate (${(summary.errorRate * 100).toFixed(1)}%) - investigate failing endpoints`);
    }
    
    if (summary.p95 > 2000) {
        recommendations.push(`95th percentile response time is ${summary.p95}ms - consider caching or optimization`);
    }
    
    if (summary.avgResponseTime > 1000) {
        recommendations.push(`Average response time is ${summary.avgResponseTime.toFixed(0)}ms - investigate slow queries`);
    }
    
    if (summary.requestsPerSecond < 50) {
        recommendations.push(`Low throughput (${summary.requestsPerSecond.toFixed(1)} RPS) - consider scaling resources`);
    }
    
    if (summary.maxResponseTime > 10000) {
        recommendations.push(`Maximum response time is ${summary.maxResponseTime}ms - check for timeouts`);
    }
    
    if (recommendations.length === 0) {
        recommendations.push('Performance looks good! Consider increasing load to find limits');
    }
    
    return recommendations;
}

// Custom validation functions
function validateResponseTime(response, context, ee, next) {
    if (context.vars.server_response_time) {
        const responseTime = parseInt(context.vars.server_response_time);
        if (responseTime > 5000) {
            console.warn(`Slow response detected: ${responseTime}ms for ${response.request.uri.href}`);
        }
    }
    return next();
}

function validateMemoryUsage(response, context, ee, next) {
    if (context.vars.server_memory) {
        const memoryUsage = parseFloat(context.vars.server_memory);
        if (memoryUsage > 85) {
            console.warn(`High memory usage detected: ${memoryUsage}% for ${response.request.uri.href}`);
        }
    }
    return next();
}

// Initialize when processor loads
initializeLogging();

// Export functions for use in Artillery config
module.exports = {
    logRequestStart,
    logRequestEnd,
    monitorSystem,
    validateResponseTime,
    validateMemoryUsage
};