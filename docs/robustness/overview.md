# 🛡️ Robustness & Production Features

The Laravel Pipedrive package includes enterprise-grade robustness features designed for production environments. These features ensure reliable operation under various conditions and provide comprehensive error handling, monitoring, and performance optimization.

## 🎯 **Key Benefits**

- **🚦 Zero Rate Limit Issues** - Smart token-based rate limiting prevents API exhaustion
- **🔄 Automatic Recovery** - Circuit breaker pattern with intelligent retry strategies  
- **🧠 Memory Safety** - Adaptive memory management prevents out-of-memory errors
- **💚 Health Monitoring** - Continuous API health checks with degradation detection
- **📊 Production Monitoring** - Comprehensive logging and metrics for operations teams

## 🏗️ **Architecture Overview**

The robustness system is built around five core services:

### **1. PipedriveRateLimitManager**
- Token-based rate limiting supporting Pipedrive's December 2024 changes
- Daily budget tracking with automatic token consumption
- Exponential backoff with jitter to prevent thundering herd
- Per-endpoint cost calculation for optimal API usage

### **2. PipedriveErrorHandler**
- Circuit breaker pattern preventing cascading failures
- Intelligent error classification with specific retry strategies
- Automatic exception classification (rate limits, auth, server errors, etc.)
- Failure tracking with automatic recovery detection

### **3. PipedriveMemoryManager**
- Real-time memory monitoring with automatic alerts
- Dynamic batch size adjustment based on memory usage
- Automatic garbage collection during large operations
- Memory threshold warnings with suggested optimizations

### **4. PipedriveHealthChecker**
- Continuous health checks with cached status
- Performance degradation detection with response time monitoring
- Automatic recovery when API health improves
- Health statistics and trend analysis

### **5. PipedriveParsingService**
- Centralized parsing logic eliminating code duplication
- Unified data fetching with robustness features
- Progress tracking with detailed statistics
- Event emission for monitoring and integration

## 🔧 **Configuration**

All robustness features are configurable via environment variables:

```env
# Rate Limiting
PIPEDRIVE_RATE_LIMITING_ENABLED=true
PIPEDRIVE_DAILY_TOKEN_BUDGET=10000
PIPEDRIVE_RATE_LIMIT_MAX_DELAY=16
PIPEDRIVE_RATE_LIMIT_JITTER=true

# Error Handling
PIPEDRIVE_MAX_RETRY_ATTEMPTS=3
PIPEDRIVE_CIRCUIT_BREAKER_THRESHOLD=5
PIPEDRIVE_CIRCUIT_BREAKER_TIMEOUT=300
PIPEDRIVE_REQUEST_TIMEOUT=30

# Memory Management
PIPEDRIVE_ADAPTIVE_PAGINATION=true
PIPEDRIVE_MEMORY_THRESHOLD=80
PIPEDRIVE_MIN_BATCH_SIZE=10
PIPEDRIVE_MAX_BATCH_SIZE=500
PIPEDRIVE_FORCE_GC=true
PIPEDRIVE_MEMORY_ALERT_THRESHOLD=85
PIPEDRIVE_MEMORY_CRITICAL_THRESHOLD=95

# Health Monitoring
PIPEDRIVE_HEALTH_MONITORING_ENABLED=true
PIPEDRIVE_HEALTH_CHECK_INTERVAL=300
PIPEDRIVE_HEALTH_ENDPOINT=currencies
PIPEDRIVE_HEALTH_CHECK_TIMEOUT=10
PIPEDRIVE_HEALTH_FAILURE_THRESHOLD=3
PIPEDRIVE_HEALTH_DEGRADATION_THRESHOLD=1000
PIPEDRIVE_HEALTH_CACHE_TTL=60

# Jobs Configuration
PIPEDRIVE_SYNC_QUEUE=pipedrive-sync
PIPEDRIVE_WEBHOOK_QUEUE=pipedrive-webhooks
PIPEDRIVE_RETRY_QUEUE=pipedrive-retry
PIPEDRIVE_JOB_TIMEOUT=3600
PIPEDRIVE_JOB_MAX_TRIES=3
PIPEDRIVE_PREFER_ASYNC=false
PIPEDRIVE_BATCH_PROCESSING=true
```

## 🚀 **Usage**

### **Automatic Integration**
All robustness features are automatically integrated into:
- ✅ Sync commands (`pipedrive:sync-entities`)
- ✅ Scheduled synchronization (`pipedrive:scheduled-sync`)
- ✅ Webhook processing (`ProcessPipedriveWebhookJob`)
- ✅ Push operations (`PushToPipedriveJob`)

### **Manual Usage**
You can also use the robustness services directly:

```php
use Keggermont\LaravelPipedrive\Services\PipedriveRateLimitManager;
use Keggermont\LaravelPipedrive\Services\PipedriveErrorHandler;
use Keggermont\LaravelPipedrive\Services\PipedriveMemoryManager;
use Keggermont\LaravelPipedrive\Services\PipedriveHealthChecker;

// Check rate limits before API calls
$rateLimitManager = app(PipedriveRateLimitManager::class);
if (!$rateLimitManager->canMakeRequest('deals')) {
    // Handle rate limit
}

// Monitor memory usage
$memoryManager = app(PipedriveMemoryManager::class);
$memoryManager->monitorMemoryUsage('my_operation');

// Check API health
$healthChecker = app(PipedriveHealthChecker::class);
if (!$healthChecker->isHealthy()) {
    // Handle degraded API
}

// Handle errors with circuit breaker
$errorHandler = app(PipedriveErrorHandler::class);
try {
    // Your API call
} catch (Exception $e) {
    $classified = $errorHandler->classifyException($e);
    if ($errorHandler->shouldRetry($classified, $attempt)) {
        // Retry with delay
        $delay = $errorHandler->getRetryDelay($classified, $attempt);
        sleep($delay);
    }
}
```

## 📊 **Monitoring & Alerting**

### **Built-in Logging**
All robustness features include comprehensive logging:
- Rate limit usage and exhaustion warnings
- Circuit breaker state changes
- Memory usage alerts and critical thresholds
- API health status changes
- Error classification and retry attempts

### **Metrics & Statistics**
Get real-time statistics from any service:

```php
// Rate limit status
$rateLimitStatus = $rateLimitManager->getStatus();
// Returns: usage, remaining tokens, percentage, etc.

// Memory statistics
$memoryStats = $memoryManager->getMemoryStats();
// Returns: usage, limits, batch sizes, thresholds, etc.

// Health status
$healthStatus = $healthChecker->getHealthStatus();
// Returns: current status, recent checks, success rate, etc.

// Circuit breaker status
$circuitStatus = $errorHandler->getCircuitBreakerStatus();
// Returns: failure counts, open/closed state per error type
```

## 🔗 **Integration with Laravel Features**

### **Queue Integration**
- Jobs are automatically tagged for monitoring
- Failed jobs include detailed error information
- Dead letter queue support for webhook processing
- Configurable queue names and timeouts

### **Event System**
- Events are emitted for all major operations
- Includes context and metadata for monitoring
- Compatible with Laravel's event listeners
- Supports custom event handlers

### **Cache Integration**
- Health status caching for performance
- Rate limit state persistence
- Circuit breaker state management
- Configurable cache TTL values

## 🎯 **Best Practices**

1. **Monitor Rate Limits** - Keep daily usage below 80% of your budget
2. **Set Memory Limits** - Use at least 1GB for full-data operations
3. **Enable Health Checks** - Monitor API degradation proactively
4. **Use Async Processing** - Leverage queues for large operations
5. **Configure Alerting** - Set up notifications for critical issues
6. **Test Error Scenarios** - Verify circuit breaker behavior
7. **Monitor Performance** - Track response times and success rates

## 🔧 **Troubleshooting**

See the [Troubleshooting Guide](troubleshooting.md) for common issues and solutions.

## 📚 **Advanced Configuration**

See the [Advanced Configuration Guide](advanced-configuration.md) for detailed configuration options.
