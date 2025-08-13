# OpenAI Streaming Analysis Guide

## Overview

This guide covers the comprehensive OpenAI streaming analysis system implemented in the AI Blockchain Analytics Platform. The system provides real-time token streaming for contract security analysis using Laravel Horizon job workers and Server-Sent Events (SSE).

## Architecture Components

### 1. Core Services

#### OpenAiStreamService (`app/Services/OpenAiStreamService.php`)
- **Purpose**: Handles OpenAI API streaming with real-time token processing
- **Key Features**:
  - Token-level streaming with real-time caching
  - Event broadcasting for live updates
  - Structured security finding analysis
  - Performance monitoring and error handling

```php
// Example usage
$streamService = new OpenAiStreamService('gpt-4', 2000, 0.7);
$result = $streamService->streamSecurityAnalysis($prompt, $analysisId, $context);
```

#### StreamingAnalysisJob (`app/Jobs/StreamingAnalysisJob.php`)
- **Purpose**: Horizon job worker for asynchronous contract analysis
- **Queue**: `streaming-analysis`
- **Features**:
  - Source code fetching and cleaning
  - OpenAI streaming integration
  - Result storage and validation
  - Comprehensive error handling

### 2. Controller Layer

#### StreamingAnalysisController (`app/Http/Controllers/StreamingAnalysisController.php`)
- **Endpoints**:
  - `POST /api/streaming/start` - Start new analysis
  - `GET /api/streaming/{id}/status` - Get analysis status
  - `GET /api/streaming/{id}/results` - Get completed results
  - `GET /api/streaming/{id}/stream` - Server-Sent Events endpoint
  - `POST /api/streaming/{id}/cancel` - Cancel analysis
  - `GET /api/streaming/` - List analyses

### 3. Real-Time Features

#### Event Broadcasting
- **TokenStreamed Event**: Broadcasts each token as it's received
- **Channel**: `analysis.{analysisId}`
- **Event Type**: `token.streamed`

#### Server-Sent Events (SSE)
- Real-time progress updates
- Token count and content streaming
- Status change notifications
- Automatic timeout handling

### 4. Database Schema

#### Enhanced Analyses Table
```sql
-- Streaming-specific fields
job_id UUID -- Unique job tracking ID
openai_model VARCHAR -- GPT model used
token_limit INTEGER -- Maximum tokens
temperature FLOAT -- OpenAI temperature
tokens_used INTEGER -- Actual tokens consumed
tokens_streamed INTEGER -- Tokens received via streaming
streaming_started_at TIMESTAMP
streaming_completed_at TIMESTAMP
streaming_metadata JSON -- Streaming performance data
raw_openai_response TEXT -- Full response
structured_result JSON -- Parsed findings
```

#### ContractAnalysis Table
```sql
-- Detailed analysis storage
contract_address VARCHAR(42)
network VARCHAR(50)
status ENUM('pending', 'processing', 'completed', 'failed')
source_metadata JSON -- Contract info
raw_response LONGTEXT -- OpenAI response
findings JSON -- Structured security findings
findings_count INTEGER
```

## Usage Examples

### 1. Starting a Streaming Analysis

```bash
# Via API
curl -X POST /api/streaming/start \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "contract_address": "0xA0b86a33E6417c54bE6f6F91D6B20b5e5C82D6b1",
    "network": "ethereum",
    "analysis_type": "security",
    "model": "gpt-4",
    "max_tokens": 2000,
    "temperature": 0.7
  }'
```

### 2. Monitoring via Server-Sent Events

```javascript
// Frontend JavaScript
const eventSource = new EventSource(`/api/streaming/${analysisId}/stream`);

eventSource.addEventListener('token_update', (event) => {
  const data = JSON.parse(event.data);
  console.log(`Received ${data.new_tokens} new tokens`);
  // Update UI with streaming content
});

eventSource.addEventListener('complete', (event) => {
  const data = JSON.parse(event.data);
  console.log(`Analysis completed with ${data.findings_count} findings`);
  eventSource.close();
});
```

### 3. Testing with CLI

```bash
# Run synchronous test
php artisan test:streaming-analysis \
  --contract=0xA0b86a33E6417c54bE6f6F91D6B20b5e5C82D6b1 \
  --network=ethereum \
  --model=gpt-4 \
  --type=security \
  --sync

# Queue asynchronous job
php artisan test:streaming-analysis \
  --contract=0xA0b86a33E6417c54bE6f6F91D6B20b5e5C82D6b1 \
  --network=ethereum

# Check status
php artisan test:streaming-analysis \
  --status-check \
  --analysis-id=12345
```

## Configuration

### 1. Horizon Queue Configuration

```php
// config/horizon.php
'environments' => [
    'production' => [
        'streaming-analysis' => [
            'connection' => 'redis',
            'queue' => ['streaming-analysis'],
            'balance' => 'auto',
            'processes' => 3,
            'tries' => 3,
            'timeout' => 900, // 15 minutes
        ],
    ],
],
```

### 2. OpenAI Service Configuration

```php
// config/services.php
'openai' => [
    'key' => env('OPENAI_API_KEY'),
    'organization' => env('OPENAI_ORGANIZATION'),
],
```

### 3. Broadcasting Configuration

```php
// config/broadcasting.php
'connections' => [
    'pusher' => [
        'driver' => 'pusher',
        'key' => env('PUSHER_APP_KEY'),
        'secret' => env('PUSHER_APP_SECRET'),
        'app_id' => env('PUSHER_APP_ID'),
        'options' => [
            'cluster' => env('PUSHER_APP_CLUSTER'),
            'useTLS' => true,
        ],
    ],
],
```

## Performance Monitoring

### 1. Key Metrics

- **Streaming Rate**: Tokens per second
- **Processing Time**: Total analysis duration  
- **Queue Depth**: Pending jobs in streaming-analysis queue
- **Success Rate**: Completed vs failed analyses
- **Token Efficiency**: Actual tokens used vs limit

### 2. Monitoring Commands

```bash
# View Horizon dashboard
php artisan horizon

# Monitor queue status
php artisan horizon:status

# View queue stats
php artisan queue:monitor streaming-analysis

# Check streaming analysis performance
php artisan cache:postgres analytics --days=7
```

## Error Handling

### 1. Common Issues

#### OpenAI API Errors
- **Rate Limiting**: Automatic retry with exponential backoff
- **Token Limits**: Configurable max_tokens parameter
- **Network Issues**: Circuit breaker pattern in multi-chain manager

#### Job Failures
- **Timeout**: 15-minute default with configurable limits
- **Memory**: Large contracts may need increased memory_limit
- **Queue Issues**: Horizon automatic restarts and monitoring

### 2. Debugging

```bash
# View job failures
php artisan horizon:failed

# Retry failed jobs
php artisan queue:retry all

# View logs
tail -f storage/logs/laravel.log | grep "streaming"

# Debug specific analysis
php artisan test:streaming-analysis --status-check --analysis-id=12345
```

## Security Considerations

### 1. API Security
- All endpoints require authentication (`auth:sanctum`)
- Input validation on all parameters
- Rate limiting on analysis creation
- User-scoped data access

### 2. OpenAI Integration
- API keys stored in environment variables
- Request/response logging for debugging
- Token usage tracking and billing
- Content filtering and validation

### 3. Real-Time Features
- Websocket authentication
- Channel authorization
- User-specific broadcasting
- Connection management

## Scaling Considerations

### 1. Horizontal Scaling
- **Queue Workers**: Scale Horizon processes based on load
- **Database**: Read replicas for status checking
- **Redis**: Cluster for broadcasting and caching
- **Load Balancer**: Sticky sessions for SSE connections

### 2. Performance Optimization
- **Caching**: Aggressive PostgreSQL caching for contract source
- **Connection Pooling**: Database and Redis connections
- **Queue Priorities**: Critical analyses get higher priority
- **Batch Processing**: Multiple contracts in single analysis

## Frontend Integration

### 1. Vue.js Component (`resources/js/Components/Analysis/RealTimeAnalysis.vue`)
- Real-time token streaming display
- Progress tracking and status updates
- Interactive findings visualization
- WebSocket connection management

### 2. API Integration
```javascript
// Start analysis
const response = await fetch('/api/streaming/start', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    contract_address: '0x...',
    network: 'ethereum',
    analysis_type: 'security'
  })
});

// Connect to streaming
const eventSource = new EventSource(`/api/streaming/${analysisId}/stream`);
```

## Best Practices

### 1. Development
- Use synchronous mode for testing (`--sync` flag)
- Monitor Horizon dashboard during development
- Test with various contract sizes and complexities
- Implement proper error boundaries in frontend

### 2. Production
- Monitor queue depths and processing times
- Set up alerts for failed jobs
- Implement graceful degradation for streaming failures
- Use load balancing for SSE connections
- Regular database maintenance for analytics

### 3. Cost Management
- Monitor OpenAI token usage
- Implement user quotas and rate limiting
- Cache common contract patterns
- Use appropriate model selection based on complexity

## Troubleshooting

### 1. Streaming Not Working
```bash
# Check queue workers
php artisan horizon:status

# Verify job is queued
php artisan horizon:list

# Check OpenAI API key
php artisan test:streaming-analysis --sync --contract=0x...
```

### 2. SSE Connection Issues
```bash
# Check web server configuration
nginx -t

# Verify broadcasting setup
php artisan websockets:serve

# Test WebSocket connection
wscat -c ws://localhost:6001/app/pusher
```

### 3. Performance Issues
```bash
# Monitor memory usage
php artisan horizon:status

# Check database queries
php artisan telescope

# Analyze token usage
php artisan cache:postgres analytics
```

## API Reference

### Start Analysis
```http
POST /api/streaming/start
Content-Type: application/json
Authorization: Bearer {token}

{
  "contract_address": "0x...",
  "network": "ethereum|bsc|polygon|arbitrum|optimism|avalanche|fantom",
  "analysis_type": "security|gas|code_quality|comprehensive",
  "model": "gpt-4|gpt-4-turbo|gpt-3.5-turbo",
  "max_tokens": 100-4000,
  "temperature": 0.0-2.0,
  "priority": "low|normal|high|critical"
}
```

### Stream Updates
```http
GET /api/streaming/{analysisId}/stream
Authorization: Bearer {token}
Accept: text/event-stream

# Events:
# - status: Initial connection
# - status_change: Status updates
# - token_update: New tokens received
# - progress: Analysis progress
# - complete: Final results
# - error: Error occurred
```

### Get Results
```http
GET /api/streaming/{analysisId}/results
Authorization: Bearer {token}

# Response includes:
# - findings: Array of security findings
# - summary: Analysis statistics
# - metadata: Processing information
```

This comprehensive guide covers all aspects of the OpenAI streaming analysis system, from basic usage to advanced troubleshooting and scaling considerations.