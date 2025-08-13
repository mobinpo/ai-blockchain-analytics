# PostgreSQL Cache Setup & Management Guide

## 🎯 Overview

This PostgreSQL caching system is designed to eliminate API rate limits and ensure smooth demo performance by caching data from external APIs (Coingecko, sentiment analysis, blockchain explorers) in your PostgreSQL database.

## 📊 Architecture

### **Database Tables**
- **`api_cache`**: Main cache table for API responses with TTL and metadata
- **`demo_cache_data`**: Specialized table for demo presentation data

### **Core Components**
- **`PostgresCacheService`**: Main caching service with intelligent TTL management
- **`ApiCache` Model**: Eloquent model for API cache with scopes and utilities
- **`DemoCacheData` Model**: Demo-specific cache data with refresh intervals
- **Cache Commands**: Artisan commands for warming and managing cache
- **Cache API**: RESTful endpoints for cache management

## 🚀 Quick Setup

### 1. **Database Configuration**

Ensure your `.env` file has proper PostgreSQL configuration:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=ai_blockchain_analytics
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 2. **Run Migrations**

```bash
# Create cache tables
php artisan migrate

# Verify tables were created
php artisan db:show --table=api_cache
php artisan db:show --table=demo_cache_data
```

### 3. **Warm Cache for Demo**

```bash
# Warm all cache with demo data
php artisan cache:warm-api

# Demo data only (no real API calls)
php artisan cache:warm-api --demo-only

# Force refresh existing cache
php artisan cache:warm-api --force

# Warm specific service
php artisan cache:warm-api --service=coingecko
```

## 🛠 Service Integration

### **CoingeckoService Integration**

The `CoingeckoService` has been updated to use PostgreSQL cache:

```php
// Before (Laravel Cache)
Cache::remember($key, $ttl, $callback)

// After (PostgreSQL Cache)  
$this->cache->remember('coingecko', 'price_current', $params, $callback)
```

### **TTL Configuration**

Smart TTL management based on data type:

```php
'coingecko' => [
    'price_current' => 300,        // 5 minutes
    'price_history' => 3600,       // 1 hour  
    'coins_list' => 86400,         // 24 hours
    'coin_search' => 3600,         // 1 hour
],
'sentiment' => [
    'daily_aggregate' => 1800,     // 30 minutes
    'live_feed' => 120,            // 2 minutes
],
'demo' => [
    'default' => 60,               // 1 minute
],
```

## 📱 API Management

### **Cache Statistics**
```bash
GET /api/cache/stats
```

**Response:**
```json
{
  "success": true,
  "data": {
    "api_cache": {
      "total_entries": 156,
      "valid_entries": 142,
      "expired_entries": 14,
      "demo_entries": 89,
      "cache_size_mb": 12.4,
      "services": ["coingecko", "sentiment", "blockchain"]
    },
    "demo_cache": {
      "total_entries": 8,
      "active_entries": 8,
      "stale_entries": 0,
      "total_size_kb": 45.2
    }
  }
}
```

### **Warm Cache**
```bash
POST /api/cache/warm
Content-Type: application/json

{
  "service": "all",
  "demo_only": false,
  "force": false
}
```

### **Clear Cache**
```bash
DELETE /api/cache/clear
Content-Type: application/json

{
  "service": "coingecko",
  "confirm": true
}
```

### **Get Cache Entry**
```bash
GET /api/cache/entry?service=coingecko&endpoint=price_current&params[coin_ids]=bitcoin
```

### **Initialize Demo Data**
```bash
POST /api/cache/demo/initialize
```

## 🎪 Demo Presentation Setup

### **Pre-Booth Checklist**

1. **Warm Demo Cache**
   ```bash
   php artisan cache:warm-api --demo-only
   ```

2. **Verify Cache Status**
   ```bash
   php artisan cache:manage-api stats
   ```

3. **Test API Endpoints**
   ```bash
   curl http://your-domain.com/api/cache/stats
   ```

### **Booth Maintenance Commands**

```bash
# Quick cache refresh during event
php artisan cache:warm-api --service=demo --force

# Check cache health
php artisan cache:manage-api stats

# Clean up if needed
php artisan cache:manage-api cleanup
```

## 🔧 Command Reference

### **Cache Warming Command**
```bash
php artisan cache:warm-api [options]

Options:
  --service=SERVICE    Which service to warm (all, coingecko, demo)
  --demo-only         Only warm demo data, skip real API calls  
  --force             Force refresh even if cache exists
```

### **Cache Management Command**
```bash
php artisan cache:manage-api {action} [options]

Actions:
  stats               Show comprehensive cache statistics
  cleanup             Clean up expired cache entries
  clear               Clear cache for service or all
  invalidate          Invalidate specific cache entry

Options:
  --service=SERVICE   Service to target for clear/invalidate
  --key=KEY          Specific cache key to invalidate
  --expired-only     Only cleanup expired entries
```

## 📊 Cache Performance

### **Optimization Features**

- **Intelligent TTL**: Different expiration times based on data volatility
- **Stale Fallback**: Returns expired data if API calls fail
- **Hit Tracking**: Monitors cache usage for optimization
- **Demo Data Separation**: Flagged demo data for easy management
- **Efficient Indexing**: PostgreSQL indexes for fast lookups

### **Memory Management**

- **Automatic Cleanup**: Expired entries are automatically removed
- **Size Monitoring**: Cache size tracking in MB/KB
- **Service Isolation**: Clear cache per service without affecting others

## 🚨 Troubleshooting

### **Common Issues**

1. **Database Connection Failed**
   ```bash
   # Check database connectivity
   php artisan db:show
   
   # Verify environment variables
   php artisan config:show database.connections.pgsql
   ```

2. **Cache Not Working**
   ```bash
   # Check if tables exist
   php artisan migrate:status
   
   # Warm cache manually
   php artisan cache:warm-api --demo-only
   ```

3. **API Rate Limits**
   ```bash
   # Use demo data only
   php artisan cache:warm-api --demo-only
   
   # Check cache hit rates
   php artisan cache:manage-api stats
   ```

### **Debug Mode**

Enable detailed logging:

```php
// In .env
LOG_LEVEL=debug

// Check logs
tail -f storage/logs/laravel.log | grep -i cache
```

## 🎯 Demo Data Examples

### **Live Statistics**
```json
{
  "contracts_analyzed": 1247,
  "security_issues": 89,
  "sentiment_score": 0.743,
  "api_requests": 15420
}
```

### **Security Threats**
```json
[
  {
    "type": "Reentrancy Attack",
    "contract": "0x1234...5678",
    "severity": "critical",
    "timestamp": "2025-01-08T10:30:00Z"
  }
]
```

### **Market Data**
```json
{
  "bitcoin": {
    "usd": 43250.00,
    "usd_24h_change": 2.34
  },
  "ethereum": {
    "usd": 2840.50,
    "usd_24h_change": 1.87
  }
}
```

## 🔄 Maintenance Schedule

### **Daily Operations**
- Automatic cache cleanup (expired entries)
- Demo data refresh every 1-5 minutes
- API cache refresh based on TTL

### **Booth Events**
- Pre-event: Warm all caches
- During event: Monitor cache stats
- Post-event: Optional cache clear

### **Monitoring**
```bash
# Set up cron job for maintenance
0 2 * * * php artisan cache:manage-api cleanup
0 6 * * * php artisan cache:warm-api --demo-only
```

## 📈 Performance Benefits

- **🚀 99% Faster**: Cache hits eliminate API latency
- **💰 Zero Cost**: No API rate limit charges
- **🛡️ Reliability**: Fallback to stale data if APIs fail
- **📊 Analytics**: Cache hit/miss tracking
- **🎪 Demo Ready**: Pre-warmed data for presentations

## 🔐 Security Notes

- Cache includes request validation
- Demo data is clearly flagged
- Access control through Laravel middleware
- Sensitive data can be excluded from cache

Your PostgreSQL cache system is now ready to eliminate API limits and ensure smooth demo performance! 🎉