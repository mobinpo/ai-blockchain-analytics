#!/usr/bin/env node

/**
 * Demo Server for Artillery Load Testing
 * Simulates AI Blockchain Analytics API endpoints for load testing
 */

const express = require('express');
const app = express();
const port = 8003;

// Middleware
app.use(express.json());
app.use((req, res, next) => {
    // Add CORS headers
    res.header('Access-Control-Allow-Origin', '*');
    res.header('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization');
    res.header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    
    // Log requests
    console.log(`${new Date().toISOString()} ${req.method} ${req.path}`);
    next();
});

// Simulate processing delays
const simulateDelay = (min = 50, max = 500) => {
    return new Promise(resolve => {
        const delay = Math.floor(Math.random() * (max - min + 1)) + min;
        setTimeout(resolve, delay);
    });
};

// Generate realistic response data
const generateJobId = () => `job_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
const generateVerificationId = () => `ver_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
const generateTaskId = () => `task_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;

// Routes

// Health check
app.get(['/', '/health'], async (req, res) => {
    await simulateDelay(10, 50);
    res.json({
        status: 'healthy',
        timestamp: new Date().toISOString(),
        version: '2.0.0',
        environment: 'load-testing'
    });
});

// Sentiment Analysis Pipeline
app.post('/api/sentiment/analyze', async (req, res) => {
    await simulateDelay(100, 2000);
    
    const { symbol, analysis_type, priority } = req.body;
    
    // Simulate occasional errors (5% chance)
    if (Math.random() < 0.05) {
        return res.status(500).json({
            error: 'Analysis service temporarily unavailable',
            code: 'SERVICE_UNAVAILABLE',
            retry_after: 30
        });
    }
    
    // Simulate rate limiting (2% chance)
    if (Math.random() < 0.02) {
        return res.status(429).json({
            error: 'Rate limit exceeded',
            code: 'RATE_LIMIT_EXCEEDED',
            retry_after: 60
        });
    }
    
    const jobId = generateJobId();
    res.status(202).json({
        job_id: jobId,
        status: 'queued',
        symbol: symbol,
        analysis_type: analysis_type,
        priority: priority,
        estimated_completion: new Date(Date.now() + 30000).toISOString(),
        queue_position: Math.floor(Math.random() * 50) + 1
    });
});

app.get('/api/sentiment/status/:jobId', async (req, res) => {
    await simulateDelay(50, 200);
    
    const statuses = ['queued', 'processing', 'completed', 'failed'];
    const status = statuses[Math.floor(Math.random() * statuses.length)];
    
    res.json({
        job_id: req.params.jobId,
        status: status,
        progress: Math.floor(Math.random() * 100),
        created_at: new Date(Date.now() - 60000).toISOString(),
        updated_at: new Date().toISOString()
    });
});

app.get('/api/sentiment/results/:jobId', async (req, res) => {
    await simulateDelay(100, 500);
    
    res.json({
        job_id: req.params.jobId,
        status: 'completed',
        results: {
            sentiment_score: (Math.random() - 0.5) * 2,
            confidence: Math.random(),
            volume: Math.floor(Math.random() * 10000) + 100,
            sources_analyzed: Math.floor(Math.random() * 50) + 10,
            keywords: ['bullish', 'moon', 'hodl'],
            timestamp: new Date().toISOString()
        }
    });
});

// Verification endpoints
app.get('/verification', async (req, res) => {
    await simulateDelay(50, 200);
    res.json({ status: 'available', verification_types: ['basic', 'enhanced', 'premium'] });
});

app.post('/api/verification/submit', async (req, res) => {
    await simulateDelay(200, 1000);
    
    const verificationId = generateVerificationId();
    res.status(201).json({
        verification_id: verificationId,
        status: 'submitted',
        estimated_completion: new Date(Date.now() + 60000).toISOString()
    });
});

app.get('/api/verification/status/:verificationId', async (req, res) => {
    await simulateDelay(50, 200);
    
    res.json({
        verification_id: req.params.verificationId,
        status: 'processing',
        progress: Math.floor(Math.random() * 100),
        stage: 'validation'
    });
});

app.post('/api/verification/generate-badge', async (req, res) => {
    await simulateDelay(300, 800);
    
    res.json({
        badge_id: `badge_${Date.now()}`,
        verification_id: req.body.verification_id,
        badge_url: `https://badges.ai-blockchain.com/badge_${Date.now()}.png`,
        expires_at: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString()
    });
});

// PDF Generation endpoints
app.post('/api/pdf/generate', async (req, res) => {
    await simulateDelay(500, 2000);
    
    const taskId = generateTaskId();
    res.status(202).json({
        task_id: taskId,
        status: 'queued',
        estimated_time: Math.floor(Math.random() * 120) + 30,
        file_size_estimate: Math.floor(Math.random() * 5000) + 1000
    });
});

app.get('/api/pdf/status/:taskId', async (req, res) => {
    await simulateDelay(50, 200);
    
    res.json({
        task_id: req.params.taskId,
        status: 'processing',
        progress: Math.floor(Math.random() * 100),
        file_size: Math.floor(Math.random() * 5000) + 1000
    });
});

app.get('/api/pdf/download/:taskId', async (req, res) => {
    await simulateDelay(100, 500);
    
    // Simulate file download
    res.setHeader('Content-Type', 'application/pdf');
    res.setHeader('Content-Disposition', `attachment; filename="report_${req.params.taskId}.pdf"`);
    res.send(Buffer.from('PDF content simulation'));
});

// Dashboard endpoints
app.get('/dashboard', async (req, res) => {
    await simulateDelay(100, 300);
    res.json({ message: 'Dashboard loaded', user_count: Math.floor(Math.random() * 1000) });
});

app.get('/api/dashboard/metrics', async (req, res) => {
    await simulateDelay(100, 500);
    
    res.json({
        metrics: {
            total_analyses: Math.floor(Math.random() * 100000) + 10000,
            active_jobs: Math.floor(Math.random() * 500) + 50,
            completed_today: Math.floor(Math.random() * 1000) + 100,
            success_rate: 0.95 + Math.random() * 0.04,
            avg_processing_time: Math.floor(Math.random() * 5000) + 1000
        },
        timestamp: new Date().toISOString()
    });
});

app.get('/api/analyses/recent', async (req, res) => {
    await simulateDelay(50, 300);
    
    const analyses = [];
    const limit = parseInt(req.query.limit) || 10;
    
    for (let i = 0; i < limit; i++) {
        analyses.push({
            id: `analysis_${Date.now()}_${i}`,
            symbol: ['BTC', 'ETH', 'ADA'][Math.floor(Math.random() * 3)],
            type: 'sentiment',
            status: 'completed',
            created_at: new Date(Date.now() - Math.random() * 3600000).toISOString()
        });
    }
    
    res.json({ analyses, total: limit });
});

app.get('/api/sentiment/live-feed', async (req, res) => {
    await simulateDelay(100, 400);
    
    const feed = [];
    for (let i = 0; i < 20; i++) {
        feed.push({
            id: `feed_${Date.now()}_${i}`,
            symbol: ['BTC', 'ETH', 'ADA', 'SOL'][Math.floor(Math.random() * 4)],
            sentiment: (Math.random() - 0.5) * 2,
            volume: Math.floor(Math.random() * 1000) + 100,
            timestamp: new Date().toISOString()
        });
    }
    
    res.json({ feed, last_updated: new Date().toISOString() });
});

app.get('/api/system/performance', async (req, res) => {
    await simulateDelay(50, 200);
    
    res.json({
        queue_depth: Math.floor(Math.random() * 100) + 10,
        processing_rate: Math.floor(Math.random() * 100) + 50,
        cpu_usage: Math.random() * 80 + 10,
        memory_usage: Math.random() * 70 + 20,
        response_time_avg: Math.floor(Math.random() * 2000) + 200
    });
});

// Batch operations
app.post('/api/batch/analyze', async (req, res) => {
    await simulateDelay(500, 1500);
    
    const batchId = req.body.batch_id || generateJobId();
    res.status(202).json({
        batch_id: batchId,
        status: 'processing',
        total_analyses: req.body.analyses?.length || 0,
        estimated_completion: new Date(Date.now() + 120000).toISOString()
    });
});

app.get('/api/batch/status/:batchId', async (req, res) => {
    await simulateDelay(50, 200);
    
    res.json({
        batch_id: req.params.batchId,
        status: 'processing',
        completed: Math.floor(Math.random() * 10),
        total: 10,
        progress: Math.floor(Math.random() * 100)
    });
});

app.get('/api/cache/performance', async (req, res) => {
    await simulateDelay(100, 300);
    
    res.json({
        cache_hit_rate: 0.8 + Math.random() * 0.15,
        cache_size: Math.floor(Math.random() * 1000) + 500,
        operations_per_second: Math.floor(Math.random() * 10000) + 1000
    });
});

// Catch-all for undefined routes
app.use('*', (req, res) => {
    res.status(404).json({
        error: 'Endpoint not found',
        message: `${req.method} ${req.originalUrl} is not available`,
        available_endpoints: [
            'GET /health',
            'POST /api/sentiment/analyze',
            'GET /api/sentiment/status/:jobId',
            'POST /api/verification/submit',
            'POST /api/pdf/generate',
            'GET /api/dashboard/metrics'
        ]
    });
});

// Error handling
app.use((err, req, res, next) => {
    console.error(err.stack);
    res.status(500).json({
        error: 'Internal server error',
        message: 'Something went wrong!',
        timestamp: new Date().toISOString()
    });
});

// Start server
app.listen(port, () => {
    console.log(`🚀 AI Blockchain Analytics Demo Server running on http://localhost:${port}`);
    console.log(`📊 Ready for Artillery load testing with 500 concurrent users`);
    console.log(`🎯 Available endpoints:`);
    console.log(`   • GET  /health - Health check`);
    console.log(`   • POST /api/sentiment/analyze - Sentiment analysis`);
    console.log(`   • POST /api/verification/submit - Verification requests`);
    console.log(`   • POST /api/pdf/generate - PDF generation`);
    console.log(`   • GET  /api/dashboard/metrics - Dashboard metrics`);
    console.log(`\n🔄 Starting load test with: ./load-tests/run-enhanced-500-test.sh\n`);
});

// Graceful shutdown
process.on('SIGTERM', () => {
    console.log('\n🛑 Received SIGTERM, shutting down gracefully...');
    process.exit(0);
});

process.on('SIGINT', () => {
    console.log('\n🛑 Received SIGINT, shutting down gracefully...');
    process.exit(0);
});
