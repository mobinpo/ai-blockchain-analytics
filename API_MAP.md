# API Mapping Documentation

This document maps every UI widget and component to its corresponding backend API endpoint and data source.

## Dashboard Widgets → API Endpoints

### Main Dashboard Stats
| UI Widget | Component | API Endpoint | Data Source | Status |
|-----------|-----------|--------------|-------------|---------|
| Total Projects | `Dashboard.vue` | `GET /api/dashboard/stats` | `Project::count()` | ✅ Live |
| Active Analyses | `Dashboard.vue` | `GET /api/dashboard/stats` | `Analysis::whereIn('status', ['analyzing', 'processing'])` | ✅ Live |
| Critical Findings | `Dashboard.vue` | `GET /api/dashboard/stats` | `Analysis::whereHas('findings', severity='critical')` | ✅ Live |
| Avg Sentiment | `Dashboard.vue` | `GET /api/dashboard/stats` | `Analysis::avg('sentiment_score')` | ✅ Live |

### Analytics Components
| UI Widget | Component | API Endpoint | Data Source | Status |
|-----------|-----------|--------------|-------------|---------|
| Risk Matrix | `RiskMatrix.vue` | `GET /api/analytics/risk-matrix` | Generated risk analysis data | ✅ Live |
| Security Trends Chart | `SecurityChart.vue` | `GET /api/analytics/security-trend` | Security findings over time | ✅ Live |
| Sentiment Gauge | `SentimentGauge.vue` | `GET /api/dashboard/stats` | Real sentiment calculations | ✅ Live |
| Network Status | `NetworkStatus.vue` | `GET /api/network/status` | Blockchain network monitoring | ✅ Live |

### Real-Time Monitor
| UI Widget | Component | API Endpoint | Data Source | Status |
|-----------|-----------|--------------|-------------|---------|
| Active Analyses | `RealTimeMonitor.vue` | `GET /api/analyses/active` | Currently running analyses | ✅ Live |
| Queue Status | `RealTimeMonitor.vue` | `GET /api/analyses/queue` | Pending analyses queue | ✅ Live |
| Performance Metrics | `RealTimeMonitor.vue` | `GET /api/analyses/metrics` | System performance data | ✅ Live |

### AI Engine Status
| UI Widget | Component | API Endpoint | Data Source | Status |
|-----------|-----------|--------------|-------------|---------|
| AI Components | `AIEngineStatus.vue` | `GET /api/ai/components/status` | AI service health status | ✅ Live |
| Processing Queue | `AIEngineStatus.vue` | Props from parent | Real-time job queue data | ✅ Live |
| Performance Metrics | `AIEngineStatus.vue` | Props from parent | System performance data | ✅ Live |

## Blockchain Explorer Components

### Contract Analyzer
| UI Widget | Component | API Endpoint | Data Source | Status |
|-----------|-----------|--------------|-------------|---------|
| Network List | `LiveContractAnalyzer.vue` | `GET /api/blockchain/networks` | Supported blockchain networks | ✅ Live |
| Quick Examples | `LiveContractAnalyzer.vue` | `GET /api/blockchain/examples` | Popular contract examples | ✅ Live |
| Contract Analysis | `LiveContractAnalyzer.vue` | `POST /api/blockchain/security-analysis` | Smart contract security scan | ✅ Live |
| Contract Info | `BlockchainExplorer.vue` | `GET /api/blockchain/contract-info` | Contract metadata & details | ✅ Live |

### Demo Components
| UI Widget | Component | API Endpoint | Data Source | Status |
|-----------|-----------|--------------|-------------|---------|
| Explorer Search Examples | `ExplorerSearchDemo.vue` | `GET /api/blockchain/examples` | Real contract examples | ✅ Live |
| Blockchain Analysis | `ExplorerSearchDemo.vue` | `POST /api/blockchain/analyze` | Contract/address analysis | ✅ Live |

## Sentiment Analysis Components

### Chart Components
| UI Widget | Component | API Endpoint | Data Source | Status |
|-----------|-----------|--------------|-------------|---------|
| Sentiment Timeline | `SentimentPriceChart.vue` | `GET /api/sentiment/{symbol}/timeline` | Historical sentiment data | ✅ Live |
| Price Correlation | `SentimentPriceChart.vue` | `GET /api/sentiment/{symbol}/timeline` | Price correlation analysis | ✅ Live |
| Current Sentiment | `SentimentLiveDemo.vue` | `GET /api/sentiment/{symbol}/current` | Real-time sentiment score | ✅ Live |
| Live Trends | `SentimentLiveDemo.vue` | `GET /api/sentiment/live-trends` | Trending sentiment data | ✅ Live |

### Enhanced Charts
| UI Widget | Component | API Endpoint | Data Source | Status |
|-----------|-----------|--------------|-------------|---------|
| Enhanced Timeline | `EnhancedSentimentPriceTimeline.vue` | `GET /api/sentiment/{symbol}/timeline` | Enhanced sentiment analysis | ✅ Live |
| Timeline Chart | `SentimentPriceTimelineChart.vue` | `GET /api/sentiment/{symbol}/timeline` | Multi-timeframe data | ✅ Live |

## Data Sources and Integrations

### External APIs
| Data Type | Source | Integration Method | Caching |
|-----------|--------|-------------------|---------|
| Price Data | CoinGecko API | Direct API calls | 5min cache |
| Sentiment Data | Social media crawlers | Background jobs | 1min cache |
| Blockchain Data | Etherscan, Polygonscan | API proxy | 30s cache |
| Contract Analysis | Internal AI engine | Queue processing | Persistent |

### Database Tables
| UI Data | Database Table | Key Fields | Relationships |
|---------|----------------|------------|---------------|
| Projects | `projects` | id, name, contract_address, network | hasMany analyses |
| Analyses | `analyses` | id, project_id, status, findings, sentiment_score | belongsTo project |
| API Cache | `api_cache` | key, value, expires_at | N/A |

## API Response Formats

### Standard Success Response
```json
{
  "success": true,
  "data": {...},
  "timestamp": "2025-08-17T14:30:00.000Z"
}
```

### Standard Error Response
```json
{
  "success": false,
  "message": "Error description",
  "error": "Detailed error (dev only)",
  "timestamp": "2025-08-17T14:30:00.000Z"
}
```

### Pagination Format
```json
{
  "success": true,
  "data": [...],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 100,
    "last_page": 5
  }
}
```

## 🏗️ Backend Domain Architecture

### Repository Layer
| Domain | Repository Interface | Implementation | Data Source |
|--------|---------------------|----------------|-------------|
| Security Analytics | `SecurityAnalyticsRepositoryInterface` | `SecurityAnalyticsRepository` | `findings`, `analyses` tables |
| Queue Monitoring | `QueueMonitoringRepositoryInterface` | `QueueMonitoringRepository` | Laravel Horizon + `analyses` table |
| Contract Examples | `ContractExamplesRepositoryInterface` | `ContractExamplesRepository` | `famous_contracts`, `projects` tables |
| System Health | `SystemHealthRepositoryInterface` | `SystemHealthRepository` | DB health checks + microservice endpoints |

### Service Layer
| Service Type | Implementation | Responsibility | Upstream APIs |
|-------------|---------------|----------------|---------------|
| Price Data | `FreeCoinDataService` | Multi-source price aggregation | CoinGecko, CoinCap, CryptoCompare |
| Sentiment Analysis | `FreeSentimentAnalyzer` | NLP sentiment processing | Internal algorithm |
| Security Analysis | `OWASPSecurityAnalyzer` | Smart contract security auditing | OpenAI API |
| Blockchain Exploration | `BlockchainExplorerService` | Contract data fetching | Etherscan, Polygonscan APIs |

### Controller → Repository Mapping
| Controller | Repository Used | Cache TTL | Real Data Source |
|-----------|----------------|-----------|-----------------|
| `AnalyticsController` | `SecurityAnalyticsRepository` | 5min | Security findings aggregation |
| `AnalysisMonitorController` | `QueueMonitoringRepository` | 30s | Laravel Horizon job queue |
| `BlockchainController` | `ContractExamplesRepository` | 1h | Famous contracts database |
| `AIEngineController` | `SystemHealthRepository` | 1min | Service health endpoints |
| `DashboardController` | Multiple repositories | 3min | Real project/analysis data |

## Security & Rate Limiting

### Authentication
| Endpoint | Auth Required | Rate Limit | Scope |
|----------|--------------|------------|-------|
| `/api/dashboard/*` | Yes | 100/min | User data |
| `/api/blockchain/networks` | No | 1000/hour | Public data |
| `/api/sentiment/*` | No | 500/hour | Public data |
| `/api/analyses/*` | Yes | 200/min | User analyses |

### CORS Configuration
- Origins: `localhost:8003`, production domains
- Methods: GET, POST, PUT, DELETE
- Headers: Authorization, Content-Type, Accept
- Credentials: Included for authenticated requests

## Monitoring & Observability

### Performance Metrics
| Metric | Tracking Method | Alert Threshold |
|--------|----------------|------------------|
| API Response Time | Laravel Telescope | >500ms |
| Cache Hit Rate | Redis monitoring | <80% |
| Queue Processing | Laravel Horizon | >100 pending |
| Error Rate | Application logs | >5% |

### Health Checks
| Service | Endpoint | Check Frequency |
|---------|----------|-----------------|
| API Server | `/api/health` | 30s |
| Database | Internal connection | 60s |
| Redis Cache | Internal ping | 30s |
| External APIs | Scheduled jobs | 5min |
