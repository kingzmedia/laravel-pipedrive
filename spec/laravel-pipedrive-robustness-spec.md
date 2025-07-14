# Laravel Pipedrive - Robustness & Architecture Specification

## 🎯 Overview

This specification defines a comprehensive robustness improvement for the Laravel Pipedrive package, focusing on centralized architecture, intelligent error handling, rate limiting, memory management, and enhanced monitoring.

## 🏗️ Core Architecture

### Centralized Jobs System

**Objective**: Eliminate code duplication between commands and schedulers by creating a unified job-based parsing system.

```
Jobs/
├── SyncPipedriveEntityJob.php        # Main parsing job (sync/async)
├── ProcessPipedriveWebhookJob.php    # Webhook processing with retry
└── PushToPipedriveJob.php            # Enhanced with new error handling
```

**Key Features**:
- Dual execution modes (synchronous for commands, asynchronous for schedulers)
- Unified parsing logic extracted from duplicated command code
- Progress tracking and batch processing support
- Memory-aware adaptive pagination

### Robustness Services

```
Services/
├── PipedriveRateLimitManager.php     # Token-based rate limiting (Dec 2024)
├── PipedriveErrorHandler.php         # Error classification & retry logic
├── PipedriveMemoryManager.php        # Adaptive memory management
├── PipedriveHealthChecker.php        # API health monitoring & circuit breaker
└── PipedriveParsingService.php       # Centralized parsing logic
```

### Exception Hierarchy

```
Exceptions/
├── PipedriveException.php            # Base with context & retry info
├── PipedriveApiException.php         # HTTP-specific base class
├── PipedriveRateLimitException.php   # 429 errors with retry-after
├── PipedriveAuthException.php        # 401/403 errors (non-retryable)
├── PipedriveQuotaException.php       # 402 payment required
├── PipedriveServerException.php      # 500/503 errors (retryable)
├── PipedriveMemoryException.php      # Memory limit issues
└── PipedriveConnectionException.php  # Network connectivity issues
```

## 🔧 Key Improvements

### 1. Intelligent Rate Limiting
- **Token-based system** supporting Pipedrive's December 2024 changes
- **Daily budget tracking** per endpoint with configurable costs
- **Exponential backoff with jitter** (1s, 2s, 4s, 8s, 16s max)
- **Request queuing** during rate limits
- **Cache-based tracking** using Redis/database

### 2. Advanced Error Handling
- **Error classification**: Retryable (429, 500, 503) vs Non-retryable (400, 401, 403, 404, 422)
- **Circuit breaker pattern** after 5 consecutive failures
- **Intelligent retry strategies** with different delays per error type
- **Structured logging** with context and retry information

### 3. Memory Management
- **Real-time monitoring** of memory usage percentage
- **Adaptive pagination** reducing batch size when memory threshold exceeded
- **Automatic garbage collection** after processing batches
- **Memory alerts** at configurable thresholds (default 80%)
- **Minimum/maximum batch size** safety limits (10-500)

### 4. Health Monitoring
- **Continuous API health checks** using lightweight endpoints
- **Service degradation detection** with cached status
- **Circuit breaker integration** preventing cascading failures
- **Health metrics collection** for monitoring dashboards

## 📋 Configuration Structure

### Extended config/pipedrive.php
```php
'robustness' => [
    'rate_limiting' => [
        'enabled' => env('PIPEDRIVE_RATE_LIMITING_ENABLED', true),
        'daily_budget' => env('PIPEDRIVE_DAILY_TOKEN_BUDGET', 10000),
        'max_delay' => env('PIPEDRIVE_RATE_LIMIT_MAX_DELAY', 16),
        'jitter_enabled' => env('PIPEDRIVE_RATE_LIMIT_JITTER', true),
        'token_costs' => ['activities' => 1, 'deals' => 1, 'files' => 2, ...],
    ],
    
    'error_handling' => [
        'max_retry_attempts' => env('PIPEDRIVE_MAX_RETRY_ATTEMPTS', 3),
        'circuit_breaker_threshold' => env('PIPEDRIVE_CIRCUIT_BREAKER_THRESHOLD', 5),
        'circuit_breaker_timeout' => env('PIPEDRIVE_CIRCUIT_BREAKER_TIMEOUT', 300),
        'request_timeout' => env('PIPEDRIVE_REQUEST_TIMEOUT', 30),
    ],
    
    'memory_management' => [
        'adaptive_pagination' => env('PIPEDRIVE_ADAPTIVE_PAGINATION', true),
        'memory_threshold_percent' => env('PIPEDRIVE_MEMORY_THRESHOLD', 80),
        'min_batch_size' => env('PIPEDRIVE_MIN_BATCH_SIZE', 10),
        'max_batch_size' => env('PIPEDRIVE_MAX_BATCH_SIZE', 500),
        'force_gc' => env('PIPEDRIVE_FORCE_GC', true),
    ],
    
    'health_monitoring' => [
        'enabled' => env('PIPEDRIVE_HEALTH_MONITORING_ENABLED', true),
        'check_interval' => env('PIPEDRIVE_HEALTH_CHECK_INTERVAL', 300),
        'health_endpoint' => env('PIPEDRIVE_HEALTH_ENDPOINT', 'currencies'),
    ],
    
    'monitoring' => [
        'enabled' => env('PIPEDRIVE_MONITORING_ENABLED', true),
        'performance_logging' => env('PIPEDRIVE_PERFORMANCE_LOGGING', true),
        'failure_rate_threshold' => env('PIPEDRIVE_FAILURE_RATE_THRESHOLD', 10),
    ],
],

'jobs' => [
    'sync_queue' => env('PIPEDRIVE_SYNC_QUEUE', 'pipedrive-sync'),
    'webhook_queue' => env('PIPEDRIVE_WEBHOOK_QUEUE', 'pipedrive-webhooks'),
    'retry_queue' => env('PIPEDRIVE_RETRY_QUEUE', 'pipedrive-retry'),
    'timeout' => env('PIPEDRIVE_JOB_TIMEOUT', 3600),
    'max_tries' => env('PIPEDRIVE_JOB_MAX_TRIES', 3),
    'prefer_async' => env('PIPEDRIVE_PREFER_ASYNC', false),
    'batch_processing' => env('PIPEDRIVE_BATCH_PROCESSING', true),
],

'alerting' => [
    'enabled' => env('PIPEDRIVE_ALERTING_ENABLED', false),
    'channels' => [
        'mail' => ['enabled' => env('PIPEDRIVE_ALERT_MAIL_ENABLED', false)],
        'slack' => ['enabled' => env('PIPEDRIVE_ALERT_SLACK_ENABLED', false)],
    ],
    'conditions' => [
        'circuit_breaker_open' => true,
        'high_failure_rate' => true,
        'memory_threshold' => true,
        'rate_limit_exhaustion' => true,
    ],
],
```

## 🔄 Implementation Strategy

### Phase 1: Core Services & Exceptions
1. Create exception hierarchy with proper inheritance
2. Implement `PipedriveRateLimitManager` with token tracking
3. Build `PipedriveErrorHandler` with classification logic
4. Develop `PipedriveMemoryManager` with adaptive pagination
5. Create `PipedriveHealthChecker` with circuit breaker

### Phase 2: Centralized Jobs Architecture
1. Extract parsing logic into `PipedriveParsingService`
2. Create `SyncPipedriveEntityJob` with dual execution modes
3. Implement `SyncOptions` and `SyncResult` DTOs using Spatie Data
4. Refactor commands to use jobs internally (backward compatible)
5. Update scheduler to dispatch jobs instead of Artisan calls

### Phase 3: Enhanced Features
1. Add batch processing support for multiple entities
2. Implement progress tracking with events
3. Create webhook retry mechanism with dead letter queue
4. Add comprehensive monitoring and alerting
5. Update configuration with all robustness options

## 🧪 Testing Strategy

### Unit Tests
- Rate limiting logic with token consumption
- Error classification and retry mechanisms
- Memory management calculations
- Job execution in both sync/async modes
- Exception hierarchy behavior

### Integration Tests
- End-to-end sync scenarios with error injection
- Memory pressure testing with large datasets
- Rate limit simulation and recovery
- Circuit breaker activation and recovery
- Command vs job result consistency

### Performance Tests
- Large dataset processing (>10k records)
- Memory usage under sustained load
- Concurrent job execution
- API rate limit compliance verification

## 📊 Success Criteria

1. **Zero Code Duplication** - Single parsing logic for commands and schedulers
2. **<1% Failure Rate** - Under normal API conditions with automatic recovery
3. **Memory Optimization** - Adaptive pagination preventing OutOfMemoryError
4. **Rate Limit Compliance** - Intelligent handling of token-based limits
5. **Comprehensive Monitoring** - Real-time metrics and actionable alerts
6. **Backward Compatibility** - Existing command interfaces unchanged

## 🎯 Key Benefits

- **Unified Architecture** - Centralized parsing eliminates duplication
- **Intelligent Resilience** - Advanced error handling with appropriate retry strategies
- **Resource Optimization** - Memory-aware processing with adaptive batch sizing
- **Modern Rate Limiting** - Support for Pipedrive's token-based system
- **Production Ready** - Circuit breakers, health checks, and comprehensive monitoring
- **Developer Friendly** - Structured exceptions, detailed logging, and progress tracking

---

*This specification provides a comprehensive roadmap for transforming the Laravel Pipedrive package into a robust, production-ready integration with advanced error handling, intelligent resource management, and unified architecture.*
