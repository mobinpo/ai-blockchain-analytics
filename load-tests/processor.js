/**
 * Artillery Load Test Processor for AI Blockchain Analytics
 * Provides custom functions for realistic load testing scenarios
 */

const crypto = require('crypto');
const fs = require('fs');

// Performance metrics tracking
const metrics = {
    analysisRequests: 0,
    sentimentRequests: 0,
    verificationRequests: 0,
    pdfRequests: 0,
    errors: {},
    responseTimes: [],
    startTime: Date.now()
};

// Realistic blockchain contract addresses for testing
const realContractAddresses = [
    "0xdAC17F958D2ee523a2206206994597C13D831ec7",  // USDT
    "0xA0b86a33E6441a8ba6a2ed3bBD8B9B68b2b1b6A",  // USDC
    "0x6B175474E89094C44Da98b954EedeAC495271d0F",  // DAI
    "0x2260FAC5E5542a773Aa44fBCfeDf7C193bc2C599",  // WBTC
    "0xC02aaA39b223FE8D0A0e5C4F27eAD9083C756Cc2",  // WETH
    "0x1f9840a85d5aF5bf1D1762F925BDADdC4201F984",  // UNI
    "0x514910771AF9Ca656af840dff83E8264EcF986CA",  // LINK
    "0x95aD61b0a150d79219dCF64E1E6Cc01f0B64C4cE",  // SHIB
    "0x7D1AfA7B718fb893dB30A3aBc0Cfc608AaCfeBB0",  // MATIC
    "0xBB0E17EF65F82Ab018d8EDd776e8DD940327B28b",  // AXS
];

// Chain IDs for multi-chain testing
const chainIds = ["1", "56", "137", "250", "43114", "42161", "10"];

// User agents for realistic browser simulation
const userAgents = [
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/121.0",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:109.0) Gecko/20100101 Firefox/121.0"
];

/**
 * Generate a realistic contract address
 */
function generateRealisticContractAddress(context, events, done) {
    const address = realContractAddresses[Math.floor(Math.random() * realContractAddresses.length)];
    context.vars.contractAddress = address;
    return done();
}

/**
 * Generate random chain ID
 */
function generateRandomChain(context, events, done) {
    const chainId = chainIds[Math.floor(Math.random() * chainIds.length)];
    context.vars.chainId = chainId;
    return done();
}

/**
 * Generate random user agent
 */
function generateUserAgent(context, events, done) {
    const userAgent = userAgents[Math.floor(Math.random() * userAgents.length)];
    context.vars.userAgent = userAgent;
    return done();
}

/**
 * Generate unique user ID for testing
 */
function generateUserId(context, events, done) {
    const userId = `load-test-user-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
    context.vars.userId = userId;
    return done();
}

/**
 * Generate realistic project metadata for verification
 */
function generateProjectMetadata(context, events, done) {
    const projectNames = [
        "DeFi Protocol", "NFT Marketplace", "Yield Farming", "Staking Platform",
        "DEX Aggregator", "Lending Protocol", "Insurance Platform", "Gaming Token"
    ];
    
    const descriptions = [
        "Decentralized finance protocol for yield optimization",
        "Next-generation NFT marketplace with advanced features",
        "High-yield farming platform with auto-compounding",
        "Secure staking platform with flexible terms",
        "Multi-chain DEX aggregator for best prices",
        "Peer-to-peer lending with collateralized loans",
        "Decentralized insurance for DeFi protocols",
        "Gaming ecosystem with play-to-earn mechanics"
    ];

    const metadata = {
        project_name: projectNames[Math.floor(Math.random() * projectNames.length)],
        description: descriptions[Math.floor(Math.random() * descriptions.length)],
        website: `https://example-${Math.random().toString(36).substr(2, 8)}.com`,
        twitter: `@example_${Math.random().toString(36).substr(2, 6)}`,
        category: ["DeFi", "NFT", "Gaming", "Infrastructure"][Math.floor(Math.random() * 4)]
    };

    context.vars.projectMetadata = metadata;
    return done();
}

/**
 * Generate sentiment analysis parameters
 */
function generateSentimentParams(context, events, done) {
    const timeframes = ["1d", "7d", "30d"];
    const sources = [
        ["twitter", "reddit"],
        ["twitter", "news"],
        ["reddit", "news"],
        ["twitter", "reddit", "news"]
    ];

    context.vars.timeframe = timeframes[Math.floor(Math.random() * timeframes.length)];
    context.vars.sources = sources[Math.floor(Math.random() * sources.length)];
    return done();
}

/**
 * Validate response times and track metrics
 */
function trackResponseTime(requestParams, response, context, events, done) {
    const responseTime = response.timings ? response.timings.response : 0;
    metrics.responseTimes.push(responseTime);
    
    // Track metrics by request type
    if (requestParams.url.includes('analyze')) {
        metrics.analysisRequests++;
    } else if (requestParams.url.includes('sentiment')) {
        metrics.sentimentRequests++;
    } else if (requestParams.url.includes('verification')) {
        metrics.verificationRequests++;
    } else if (requestParams.url.includes('pdf')) {
        metrics.pdfRequests++;
    }

    // Log slow responses
    if (responseTime > 5000) {
        console.log(`   Slow response: ${requestParams.url} - ${responseTime}ms`);
    }

    return done();
}

/**
 * Handle errors and track error rates
 */
function handleError(requestParams, response, context, events, done) {
    const statusCode = response.statusCode;
    const url = requestParams.url;

    if (statusCode >= 400) {
        if (!metrics.errors[statusCode]) {
            metrics.errors[statusCode] = 0;
        }
        metrics.errors[statusCode]++;

        console.log(`L Error ${statusCode} on ${url}`);

        // Log specific error details for 5xx errors
        if (statusCode >= 500) {
            console.log(`=¨ Server error details:`, {
                url: url,
                statusCode: statusCode,
                responseTime: response.timings ? response.timings.response : 0,
                body: response.body ? response.body.substring(0, 200) : 'No body'
            });
        }
    }

    return done();
}

/**
 * Validate analysis response structure
 */
function validateAnalysisResponse(requestParams, response, context, events, done) {
    if (response.statusCode === 200) {
        try {
            const body = JSON.parse(response.body);
            
            // Check required fields for analysis response
            if (!body.analysis_id) {
                console.log('   Missing analysis_id in response');
            }
            
            if (!body.contract_address) {
                console.log('   Missing contract_address in response');
            }
            
            if (!body.processing_time) {
                console.log('   Missing processing_time in response');
            }

            // Store analysis ID for follow-up requests
            context.vars.analysisId = body.analysis_id;
            
        } catch (e) {
            console.log('L Invalid JSON response for analysis request');
        }
    }
    
    return done();
}

/**
 * Validate sentiment response structure
 */
function validateSentimentResponse(requestParams, response, context, events, done) {
    if (response.statusCode === 200) {
        try {
            const body = JSON.parse(response.body);
            
            if (!body.batch_id) {
                console.log('   Missing batch_id in sentiment response');
            }
            
            context.vars.batchId = body.batch_id;
            
        } catch (e) {
            console.log('L Invalid JSON response for sentiment request');
        }
    }
    
    return done();
}

/**
 * Validate verification response
 */
function validateVerificationResponse(requestParams, response, context, events, done) {
    if (response.statusCode === 200) {
        try {
            const body = JSON.parse(response.body);
            
            if (!body.verification_url) {
                console.log('   Missing verification_url in response');
            } else {
                context.vars.verificationUrl = body.verification_url;
            }
            
        } catch (e) {
            console.log('L Invalid JSON response for verification request');
        }
    }
    
    return done();
}

/**
 * Generate custom headers with authentication simulation
 */
function generateAuthHeaders(context, events, done) {
    // Simulate different types of authentication
    const authTypes = ['bearer', 'api-key', 'session'];
    const authType = authTypes[Math.floor(Math.random() * authTypes.length)];
    
    switch (authType) {
        case 'bearer':
            context.vars.authHeader = `Bearer test-token-${Math.random().toString(36)}`;
            break;
        case 'api-key':
            context.vars.authHeader = `API-Key test-key-${Math.random().toString(36)}`;
            break;
        default:
            context.vars.authHeader = '';
    }
    
    return done();
}

/**
 * Print performance summary
 */
function printSummary() {
    const duration = (Date.now() - metrics.startTime) / 1000;
    const avgResponseTime = metrics.responseTimes.length > 0 
        ? metrics.responseTimes.reduce((a, b) => a + b) / metrics.responseTimes.length 
        : 0;
    
    const p95 = metrics.responseTimes.length > 0 
        ? metrics.responseTimes.sort((a, b) => a - b)[Math.floor(metrics.responseTimes.length * 0.95)]
        : 0;

    console.log('\n=€ Load Test Performance Summary:');
    console.log('=====================================');
    console.log(`Total Duration: ${duration.toFixed(2)}s`);
    console.log(`Analysis Requests: ${metrics.analysisRequests}`);
    console.log(`Sentiment Requests: ${metrics.sentimentRequests}`);
    console.log(`Verification Requests: ${metrics.verificationRequests}`);
    console.log(`PDF Requests: ${metrics.pdfRequests}`);
    console.log(`Average Response Time: ${avgResponseTime.toFixed(2)}ms`);
    console.log(`95th Percentile: ${p95.toFixed(2)}ms`);
    console.log(`Error Summary:`, metrics.errors);
    
    // Save detailed metrics to file
    const detailedMetrics = {
        summary: {
            duration,
            totalRequests: metrics.analysisRequests + metrics.sentimentRequests + 
                          metrics.verificationRequests + metrics.pdfRequests,
            avgResponseTime,
            p95ResponseTime: p95,
            errors: metrics.errors
        },
        breakdown: {
            analysisRequests: metrics.analysisRequests,
            sentimentRequests: metrics.sentimentRequests,
            verificationRequests: metrics.verificationRequests,
            pdfRequests: metrics.pdfRequests
        },
        responseTimes: metrics.responseTimes,
        timestamp: new Date().toISOString()
    };
    
    fs.writeFileSync(
        `load-test-metrics-${Date.now()}.json`, 
        JSON.stringify(detailedMetrics, null, 2)
    );
}

// Cleanup function to run at the end of tests
process.on('SIGINT', printSummary);
process.on('SIGTERM', printSummary);
process.on('exit', printSummary);

module.exports = {
    generateRealisticContractAddress,
    generateRandomChain,
    generateUserAgent,
    generateUserId,
    generateProjectMetadata,
    generateSentimentParams,
    trackResponseTime,
    handleError,
    validateAnalysisResponse,
    validateSentimentResponse,
    validateVerificationResponse,
    generateAuthHeaders,
    printSummary
};