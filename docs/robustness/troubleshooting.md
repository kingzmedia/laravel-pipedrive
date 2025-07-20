# 🔧 Troubleshooting Guide

This guide covers common issues and solutions when using the Laravel Pipedrive package's robustness features.

## 🚦 **Rate Limiting Issues**

### **Problem: Rate Limit Exceeded**
```
PipedriveRateLimitException: Daily token budget exceeded
```

**Solutions:**
1. **Increase Daily Budget**
   ```env
   PIPEDRIVE_DAILY_TOKEN_BUDGET=20000
   ```

2. **Check Current Usage**
   ```php
   $rateLimitManager = app(PipedriveRateLimitManager::class);
   $status = $rateLimitManager->getStatus();
   echo "Usage: {$status['current_usage']}/{$status['daily_budget']}";
   ```

3. **Reset Rate Limit (for testing)**
   ```php
   $rateLimitManager->reset();
   ```

### **Problem: Frequent Rate Limit Warnings**
**Solutions:**
1. **Enable Jitter**
   ```env
   PIPEDRIVE_RATE_LIMIT_JITTER=true
   ```

2. **Increase Max Delay**
   ```env
   PIPEDRIVE_RATE_LIMIT_MAX_DELAY=30
   ```

3. **Use Async Processing**
   ```env
   PIPEDRIVE_PREFER_ASYNC=true
   ```

## 🧠 **Memory Issues**

### **Problem: Out of Memory Errors**
```
PipedriveMemoryException: Memory limit exceeded during sync
```

**Solutions:**
1. **Increase PHP Memory Limit**
   ```bash
   php -d memory_limit=2048M artisan pipedrive:sync-entities
   ```

2. **Enable Adaptive Pagination**
   ```env
   PIPEDRIVE_ADAPTIVE_PAGINATION=true
   PIPEDRIVE_MAX_BATCH_SIZE=250
   ```

3. **Lower Memory Threshold**
   ```env
   PIPEDRIVE_MEMORY_THRESHOLD=70
   ```

4. **Force Garbage Collection**
   ```env
   PIPEDRIVE_FORCE_GC=true
   ```

### **Problem: Memory Warnings**
**Solutions:**
1. **Adjust Alert Thresholds**
   ```env
   PIPEDRIVE_MEMORY_ALERT_THRESHOLD=90
   PIPEDRIVE_MEMORY_CRITICAL_THRESHOLD=95
   ```

2. **Monitor Memory Usage**
   ```php
   $memoryManager = app(PipedriveMemoryManager::class);
   $stats = $memoryManager->getMemoryStats();
   ```

## 🔄 **Circuit Breaker Issues**

### **Problem: Circuit Breaker Constantly Open**
```
Circuit breaker is open, not retrying
```

**Solutions:**
1. **Check Failure Threshold**
   ```env
   PIPEDRIVE_CIRCUIT_BREAKER_THRESHOLD=10
   ```

2. **Increase Timeout**
   ```env
   PIPEDRIVE_CIRCUIT_BREAKER_TIMEOUT=600
   ```

3. **Reset Circuit Breaker**
   ```php
   $errorHandler = app(PipedriveErrorHandler::class);
   $errorHandler->resetCircuitBreaker();
   ```

4. **Check Circuit Breaker Status**
   ```php
   $status = $errorHandler->getCircuitBreakerStatus();
   foreach ($status as $errorType => $info) {
       echo "{$errorType}: {$info['failures']} failures, open: {$info['is_open']}\n";
   }
   ```

## 💚 **Health Check Issues**

### **Problem: Health Checks Always Failing**
**Solutions:**
1. **Check Health Endpoint**
   ```env
   PIPEDRIVE_HEALTH_ENDPOINT=users
   ```

2. **Increase Timeout**
   ```env
   PIPEDRIVE_HEALTH_CHECK_TIMEOUT=30
   ```

3. **Adjust Failure Threshold**
   ```env
   PIPEDRIVE_HEALTH_FAILURE_THRESHOLD=5
   ```

4. **Manual Health Check**
   ```php
   $healthChecker = app(PipedriveHealthChecker::class);
   $status = $healthChecker->performHealthCheck();
   ```

### **Problem: Health Check Degradation Warnings**
**Solutions:**
1. **Adjust Degradation Threshold**
   ```env
   PIPEDRIVE_HEALTH_DEGRADATION_THRESHOLD=2000
   ```

2. **Check Recent Performance**
   ```php
   $healthChecker = app(PipedriveHealthChecker::class);
   $recentChecks = $healthChecker->getRecentHealthChecks(10);
   ```

## 🔧 **Job Processing Issues**

### **Problem: Jobs Failing Repeatedly**
**Solutions:**
1. **Check Job Logs**
   ```bash
   tail -f storage/logs/laravel.log | grep "pipedrive"
   ```

2. **Increase Max Tries**
   ```env
   PIPEDRIVE_JOB_MAX_TRIES=5
   ```

3. **Increase Timeout**
   ```env
   PIPEDRIVE_JOB_TIMEOUT=7200
   ```

4. **Check Failed Jobs**
   ```bash
   php artisan queue:failed
   ```

### **Problem: Webhook Jobs Not Processing**
**Solutions:**
1. **Check Queue Configuration**
   ```env
   PIPEDRIVE_WEBHOOK_QUEUE=pipedrive-webhooks
   ```

2. **Start Queue Worker**
   ```bash
   php artisan queue:work --queue=pipedrive-webhooks
   ```

3. **Check Dead Letter Queue**
   ```bash
   php artisan queue:work --queue=pipedrive-retry
   ```

## 📊 **Performance Issues**

### **Problem: Slow Sync Operations**
**Solutions:**
1. **Enable Async Processing**
   ```env
   PIPEDRIVE_PREFER_ASYNC=true
   ```

2. **Optimize Batch Sizes**
   ```env
   PIPEDRIVE_MAX_BATCH_SIZE=500
   PIPEDRIVE_MIN_BATCH_SIZE=50
   ```

3. **Use Standard Mode**
   ```bash
   # Instead of --full-data, use standard mode
   php artisan pipedrive:sync-entities --entity=deals
   ```

4. **Monitor Processing Speed**
   ```php
   // Check sync results for performance metrics
   $result = SyncPipedriveEntityJob::executeSync($options);
   echo "Processing speed: {$result->getProcessingSpeed()} items/sec";
   ```

## 🔍 **Debugging Tips**

### **Enable Verbose Logging**
```env
LOG_LEVEL=debug
```

### **Check Service Status**
```php
// Rate limiting
$rateLimitManager = app(PipedriveRateLimitManager::class);
$rateStatus = $rateLimitManager->getStatus();

// Memory management
$memoryManager = app(PipedriveMemoryManager::class);
$memoryStats = $memoryManager->getMemoryStats();

// Health monitoring
$healthChecker = app(PipedriveHealthChecker::class);
$healthStatus = $healthChecker->getHealthStatus();

// Error handling
$errorHandler = app(PipedriveErrorHandler::class);
$circuitStatus = $errorHandler->getCircuitBreakerStatus();
```

### **Test Individual Services**
```php
// Test rate limiting
$rateLimitManager = app(PipedriveRateLimitManager::class);
if ($rateLimitManager->canMakeRequest('deals')) {
    echo "Rate limit OK\n";
}

// Test memory management
$memoryManager = app(PipedriveMemoryManager::class);
if ($memoryManager->isMemorySafe()) {
    echo "Memory usage OK\n";
}

// Test health checking
$healthChecker = app(PipedriveHealthChecker::class);
if ($healthChecker->isHealthy()) {
    echo "API health OK\n";
}
```

## 🆘 **Emergency Procedures**

### **Disable All Robustness Features**
```env
PIPEDRIVE_RATE_LIMITING_ENABLED=false
PIPEDRIVE_HEALTH_MONITORING_ENABLED=false
PIPEDRIVE_ADAPTIVE_PAGINATION=false
PIPEDRIVE_CIRCUIT_BREAKER_THRESHOLD=999
```

### **Reset All State**
```php
$rateLimitManager = app(PipedriveRateLimitManager::class);
$rateLimitManager->reset();

$errorHandler = app(PipedriveErrorHandler::class);
$errorHandler->resetCircuitBreaker();

$healthChecker = app(PipedriveHealthChecker::class);
$healthChecker->resetHealthHistory();
```

### **Force Sync Without Robustness**
```bash
# Use the old command directly (if available)
php artisan pipedrive:sync-entities --entity=deals --force
```

## 📞 **Getting Help**

If you're still experiencing issues:

1. **Check the logs** in `storage/logs/laravel.log`
2. **Enable debug mode** with `LOG_LEVEL=debug`
3. **Test with minimal configuration** (disable robustness features)
4. **Check Pipedrive API status** at https://status.pipedrive.com/
5. **Review your API limits** in Pipedrive settings

For additional support, please check the [GitHub Issues](https://github.com/kingzmedia/laravel-pipedrive/issues) or create a new issue with:
- Laravel version
- Package version
- Configuration settings
- Error logs
- Steps to reproduce
