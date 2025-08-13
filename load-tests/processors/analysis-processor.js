/**
 * Artillery Data Processor for AI Blockchain Analytics Load Testing
 * 
 * Generates realistic test data for blockchain analytics scenarios
 */

const crypto = require('crypto');

/**
 * Generate a UUID v4
 */
function generateUuid() {
    return crypto.randomUUID();
}

/**
 * Generate a realistic timestamp within the last 30 days
 */
function generateTimestamp() {
    const now = Date.now();
    const thirtyDaysAgo = now - (30 * 24 * 60 * 60 * 1000);
    return Math.floor(Math.random() * (now - thirtyDaysAgo) + thirtyDaysAgo);
}

/**
 * Generate a realistic contract address
 */
function generateContractAddress() {
    const chars = 'abcdef0123456789';
    let result = '0x';
    for (let i = 0; i < 40; i++) {
        result += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return result;
}

/**
 * Generate realistic sentiment data
 */
function generateSentimentData() {
    const sentiments = ['bullish', 'bearish', 'neutral', 'uncertain'];
    const confidence = Math.random();
    const score = (Math.random() - 0.5) * 2; // -1 to 1
    
    return {
        sentiment: sentiments[Math.floor(Math.random() * sentiments.length)],
        score: parseFloat(score.toFixed(3)),
        confidence: parseFloat(confidence.toFixed(3)),
        volume: Math.floor(Math.random() * 10000) + 100,
        sources_count: Math.floor(Math.random() * 50) + 10
    };
}

/**
 * Generate realistic market data
 */
function generateMarketData() {
    const basePrice = Math.random() * 100000 + 1000; // $1K to $100K
    const priceChange = (Math.random() - 0.5) * 0.2; // -10% to +10%
    
    return {
        price: parseFloat(basePrice.toFixed(2)),
        price_change_24h: parseFloat((basePrice * priceChange).toFixed(2)),
        price_change_percentage_24h: parseFloat((priceChange * 100).toFixed(2)),
        volume_24h: Math.floor(Math.random() * 1000000000) + 1000000, // $1M to $1B
        market_cap: Math.floor(Math.random() * 100000000000) + 1000000000, // $1B to $100B
        circulating_supply: Math.floor(Math.random() * 1000000000) + 1000000,
        total_supply: Math.floor(Math.random() * 2000000000) + 1000000000
    };
}

/**
 * Generate realistic analysis request with varying complexity
 */
function generateAnalysisRequest(context, events, done) {
    const complexity = ['simple', 'standard', 'advanced', 'comprehensive'];
    const priorities = ['low', 'medium', 'high', 'critical'];
    const symbols = ['BTC', 'ETH', 'ADA', 'SOL', 'MATIC', 'AVAX', 'DOT', 'LINK', 'UNI', 'AAVE'];
    const timeframes = ['5m', '15m', '1h', '4h', '1d', '7d', '30d'];
    const sources = ['twitter', 'reddit', 'telegram', 'news', 'onchain', 'technical'];
    
    // Set variables for use in the test
    context.vars.uuid = generateUuid();
    context.vars.timestamp = generateTimestamp();
    context.vars.contract_address = generateContractAddress();
    context.vars.complexity = complexity[Math.floor(Math.random() * complexity.length)];
    context.vars.selected_symbol = symbols[Math.floor(Math.random() * symbols.length)];
    context.vars.selected_timeframe = timeframes[Math.floor(Math.random() * timeframes.length)];
    context.vars.selected_priority = priorities[Math.floor(Math.random() * priorities.length)];
    
    // Generate realistic data based on complexity
    const complexityLevel = context.vars.complexity;
    switch (complexityLevel) {
        case 'simple':
            context.vars.batch_size = Math.floor(Math.random() * 100) + 50;
            context.vars.source_count = 2;
            context.vars.processing_timeout = 60;
            break;
        case 'standard':
            context.vars.batch_size = Math.floor(Math.random() * 300) + 100;
            context.vars.source_count = 3;
            context.vars.processing_timeout = 120;
            break;
        case 'advanced':
            context.vars.batch_size = Math.floor(Math.random() * 600) + 300;
            context.vars.source_count = 4;
            context.vars.processing_timeout = 240;
            break;
        case 'comprehensive':
            context.vars.batch_size = Math.floor(Math.random() * 1000) + 500;
            context.vars.source_count = 6;
            context.vars.processing_timeout = 300;
            break;
    }
    
    // Select random sources
    const selectedSources = [];
    const shuffledSources = sources.sort(() => 0.5 - Math.random());
    for (let i = 0; i < context.vars.source_count; i++) {
        selectedSources.push(shuffledSources[i]);
    }
    context.vars.selected_sources = selectedSources;
    
    // Generate sentiment and market data
    context.vars.sentiment_data = generateSentimentData();
    context.vars.market_data = generateMarketData();
    
    // Generate realistic processing metrics
    context.vars.expected_processing_time = Math.floor(Math.random() * context.vars.processing_timeout) + 10;
    context.vars.estimated_cost = parseFloat((context.vars.batch_size * 0.001 * (context.vars.source_count / 2)).toFixed(4));
    
    return done();
}

/**
 * Simulate processing delays based on system load
 */
function simulateProcessingDelay(context, events, done) {
    const baseDelay = 1000; // 1 second base
    const loadFactor = Math.random() * 2; // 0-2x multiplier
    const complexityMultiplier = {
        'simple': 1,
        'standard': 1.5,
        'advanced': 2,
        'comprehensive': 3
    };
    
    const complexity = context.vars.complexity || 'standard';
    const totalDelay = baseDelay * loadFactor * complexityMultiplier[complexity];
    
    context.vars.processing_delay = Math.floor(totalDelay);
    context.vars.retry_count = Math.floor(Math.random() * 3); // 0-2 retries
    
    return done();
}

/**
 * Generate realistic error scenarios
 */
function generateErrorScenario(context, events, done) {
    const errorTypes = [
        'rate_limit_exceeded',
        'api_quota_exceeded',
        'processing_timeout',
        'insufficient_data',
        'service_unavailable',
        'invalid_symbol',
        'network_error'
    ];
    
    // 10% chance of triggering error scenario
    if (Math.random() < 0.1) {
        context.vars.error_type = errorTypes[Math.floor(Math.random() * errorTypes.length)];
        context.vars.should_retry = Math.random() < 0.7; // 70% of errors should be retried
        context.vars.retry_delay = Math.floor(Math.random() * 5000) + 1000; // 1-5 seconds
    } else {
        context.vars.error_type = null;
        context.vars.should_retry = false;
    }
    
    return done();
}

/**
 * Generate realistic verification scenario
 */
function generateVerificationScenario(context, events, done) {
    const verificationLevels = ['basic', 'standard', 'enhanced', 'premium', 'enterprise'];
    const verificationTypes = ['identity', 'contract', 'analysis', 'comprehensive'];
    const securityLevels = ['low', 'medium', 'high', 'maximum'];
    
    context.vars.verification_level = verificationLevels[Math.floor(Math.random() * verificationLevels.length)];
    context.vars.verification_type = verificationTypes[Math.floor(Math.random() * verificationTypes.length)];
    context.vars.security_level = securityLevels[Math.floor(Math.random() * securityLevels.length)];
    
    // Generate verification-specific data
    context.vars.verification_id = `ver_${generateUuid().substring(0, 8)}`;
    context.vars.expiry_hours = Math.floor(Math.random() * 168) + 24; // 1-7 days
    context.vars.verification_cost = parseFloat((Math.random() * 100 + 10).toFixed(2));
    
    return done();
}

/**
 * Generate realistic PDF generation parameters
 */
function generatePdfScenario(context, events, done) {
    const reportTypes = ['summary', 'detailed', 'comprehensive', 'executive', 'technical'];
    const formats = ['standard', 'premium', 'executive', 'presentation'];
    const chartTypes = ['line', 'candlestick', 'volume', 'sentiment', 'correlation', 'heatmap'];
    
    context.vars.report_type = reportTypes[Math.floor(Math.random() * reportTypes.length)];
    context.vars.report_format = formats[Math.floor(Math.random() * formats.length)];
    
    // Select random chart types
    const numCharts = Math.floor(Math.random() * 4) + 2; // 2-5 charts
    const selectedCharts = [];
    const shuffledCharts = chartTypes.sort(() => 0.5 - Math.random());
    for (let i = 0; i < numCharts; i++) {
        selectedCharts.push(shuffledCharts[i]);
    }
    context.vars.selected_charts = selectedCharts;
    
    // Estimate file size and processing time
    const baseSize = 1024; // 1MB base
    const chartMultiplier = selectedCharts.length * 0.5;
    const formatMultiplier = {
        'standard': 1,
        'premium': 1.5,
        'executive': 2,
        'presentation': 2.5
    };
    
    context.vars.estimated_file_size = Math.floor(baseSize * chartMultiplier * formatMultiplier[context.vars.report_format]);
    context.vars.estimated_processing_time = Math.floor(selectedCharts.length * 5 + 10); // seconds
    
    return done();
}

/**
 * Log performance metrics for analysis
 */
function logPerformanceMetrics(context, events, done) {
    const metrics = {
        timestamp: new Date().toISOString(),
        user_id: context.vars.$uuid || 'anonymous',
        scenario: context.scenario?.name || 'unknown',
        phase: context.vars.$phase || 'unknown',
        request_count: context.vars.$loopCount || 1,
        processing_time: context.vars.processing_delay || 0,
        complexity: context.vars.complexity || 'unknown',
        symbol: context.vars.selected_symbol || 'unknown',
        batch_size: context.vars.batch_size || 0,
        error_occurred: !!context.vars.error_type,
        error_type: context.vars.error_type || null
    };
    
    // Log metrics (in a real scenario, this would be sent to monitoring system)
    console.log(`[METRICS] ${JSON.stringify(metrics)}`);
    
    return done();
}

/**
 * Main processor exports
 */
module.exports = {
    generateAnalysisRequest,
    simulateProcessingDelay,
    generateErrorScenario,
    generateVerificationScenario,
    generatePdfScenario,
    logPerformanceMetrics,
    
    // Utility functions
    generateUuid,
    generateTimestamp,
    generateContractAddress,
    generateSentimentData,
    generateMarketData
};
