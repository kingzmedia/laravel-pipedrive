<?php

namespace Skeylup\LaravelPipedrive\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Skeylup\LaravelPipedrive\Exceptions\PipedriveApiException;
use Skeylup\LaravelPipedrive\Exceptions\PipedriveAuthException;
use Skeylup\LaravelPipedrive\Exceptions\PipedriveConnectionException;
use Skeylup\LaravelPipedrive\Exceptions\PipedriveException;
use Skeylup\LaravelPipedrive\Exceptions\PipedriveQuotaException;
use Skeylup\LaravelPipedrive\Exceptions\PipedriveRateLimitException;
use Skeylup\LaravelPipedrive\Exceptions\PipedriveServerException;
use Throwable;

/**
 * Error classification and retry logic service
 *
 * Implements circuit breaker pattern and intelligent retry strategies
 * for different error types
 */
class PipedriveErrorHandler
{
    protected array $config;

    protected string $cachePrefix = 'pipedrive_circuit_breaker';

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'max_retry_attempts' => 3,
            'circuit_breaker_threshold' => 5,
            'circuit_breaker_timeout' => 300, // 5 minutes
            'request_timeout' => 30,
        ], $config);
    }

    /**
     * Classify exception and determine retry strategy
     */
    public function classifyException(Throwable $exception, array $context = []): PipedriveException
    {
        // If already a PipedriveException, return as-is
        if ($exception instanceof PipedriveException) {
            return $exception->addContext('handler_context', $context);
        }

        // Classify based on exception type and message
        $classified = $this->classifyGenericException($exception, $context);

        Log::debug('Exception classified', [
            'original_class' => get_class($exception),
            'classified_class' => get_class($classified),
            'error_type' => $classified->getErrorType(),
            'retryable' => $classified->isRetryable(),
            'context' => $context,
        ]);

        return $classified;
    }

    /**
     * Classify generic exception into appropriate PipedriveException
     */
    protected function classifyGenericException(Throwable $exception, array $context): PipedriveException
    {
        $message = $exception->getMessage();
        $code = $exception->getCode();
        $exceptionClass = get_class($exception);

        // HTTP status code based classification
        if ($code >= 400 && $code < 600) {
            return $this->classifyHttpException($exception, $context);
        }

        // Specific Pipedrive API exceptions
        if ($this->isItemNotFoundException($exception)) {
            return $this->handleItemNotFoundException($exception, $context);
        }

        // Connection/network errors
        if ($this->isConnectionError($message)) {
            return new PipedriveConnectionException(
                message: $message,
                code: $code,
                previous: $exception,
                context: $context,
                retryable: true,
                retryAfter: 10,
                maxRetries: 5
            );
        }

        // Memory errors
        if ($this->isMemoryError($message)) {
            return new PipedriveMemoryException(
                message: $message,
                code: $code,
                previous: $exception,
                context: $context,
                retryable: true,
                retryAfter: 5,
                maxRetries: 2
            );
        }

        // Generic Pipedrive exception with better error message
        $enhancedMessage = $this->enhanceErrorMessage($message, $exceptionClass, $context);

        return new PipedriveException(
            message: $enhancedMessage,
            code: $code,
            previous: $exception,
            context: $context,
            retryable: false
        );
    }

    /**
     * Classify HTTP exceptions
     */
    protected function classifyHttpException(Throwable $exception, array $context): PipedriveApiException
    {
        $code = $exception->getCode();
        $message = $exception->getMessage();

        return match (true) {
            $code === 401 || $code === 403 => new PipedriveAuthException(
                message: $message,
                code: $code,
                previous: $exception,
                context: $context,
                maxRetries: $code === 401 ? 2 : 1
            ),
            $code === 402 => new PipedriveQuotaException(
                message: $message,
                code: $code,
                previous: $exception,
                context: $context,
                maxRetries: 1
            ),
            $code === 429 => new PipedriveRateLimitException(
                message: $message,
                code: $code,
                previous: $exception,
                context: $context,
                retryAfter: 60,
                maxRetries: 5
            ),
            $code >= 500 => new PipedriveServerException(
                message: $message,
                code: $code,
                previous: $exception,
                context: $context,
                retryAfter: 30,
                maxRetries: 5
            ),
            default => new PipedriveApiException(
                message: $message,
                code: $code,
                previous: $exception,
                context: $context,
                retryable: false
            )
        };
    }

    /**
     * Check if exception indicates connection error
     */
    protected function isConnectionError(string $message): bool
    {
        $connectionKeywords = [
            'connection', 'timeout', 'timed out', 'dns', 'ssl', 'certificate',
            'network', 'unreachable', 'refused', 'reset', 'broken pipe',
        ];

        $lowerMessage = strtolower($message);

        foreach ($connectionKeywords as $keyword) {
            if (str_contains($lowerMessage, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if exception indicates memory error
     */
    protected function isMemoryError(string $message): bool
    {
        $memoryKeywords = [
            'memory', 'out of memory', 'memory limit', 'memory exhausted',
            'allocation', 'fatal error',
        ];

        $lowerMessage = strtolower($message);

        foreach ($memoryKeywords as $keyword) {
            if (str_contains($lowerMessage, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if exception is ItemNotFoundException from Pipedrive
     */
    protected function isItemNotFoundException(Throwable $exception): bool
    {
        return $exception instanceof \Devio\Pipedrive\Exceptions\ItemNotFoundException;
    }

    /**
     * Handle ItemNotFoundException with context-aware messaging
     */
    protected function handleItemNotFoundException(Throwable $exception, array $context): PipedriveException
    {
        $entityType = $context['entity_type'] ?? 'unknown';
        $operation = $context['operation'] ?? 'unknown';

        $message = match ($operation) {
            'fetch_entity_data' => "Entity '{$entityType}' not found or not accessible. This entity may not exist in your Pipedrive account or you may not have permission to access it.",
            'process_item' => "Item not found for entity '{$entityType}'. The item may have been deleted or is not accessible.",
            default => "Item not found for entity '{$entityType}' during operation '{$operation}'. Please check if the entity exists and is accessible."
        };

        return new PipedriveException(
            message: $message,
            code: 404,
            previous: $exception,
            context: array_merge($context, [
                'error_type' => 'item_not_found',
                'entity_type' => $entityType,
                'operation' => $operation,
                'suggestion' => "Consider removing '{$entityType}' from PIPEDRIVE_ENABLED_ENTITIES if this entity is not available in your Pipedrive account.",
            ]),
            retryable: false
        );
    }

    /**
     * Enhance error message with context information
     */
    protected function enhanceErrorMessage(string $originalMessage, string $exceptionClass, array $context): string
    {
        if (empty($originalMessage) || $originalMessage === 'Error unknown.') {
            $entityType = $context['entity_type'] ?? 'unknown';
            $operation = $context['operation'] ?? 'unknown';

            return "Pipedrive API error during '{$operation}' for entity '{$entityType}'. Exception: ".class_basename($exceptionClass);
        }

        return $originalMessage;
    }

    /**
     * Determine if exception should be retried
     */
    public function shouldRetry(PipedriveException $exception, int $attemptNumber): bool
    {
        // Check circuit breaker
        if ($this->isCircuitBreakerOpen($exception->getErrorType())) {
            Log::warning('Circuit breaker is open, not retrying', [
                'error_type' => $exception->getErrorType(),
                'attempt' => $attemptNumber,
            ]);

            return false;
        }

        // Check if exception is retryable
        if (! $exception->isRetryable()) {
            return false;
        }

        // Check max retry attempts
        if ($attemptNumber >= $exception->getMaxRetries()) {
            return false;
        }

        return true;
    }

    /**
     * Get retry delay for exception
     */
    public function getRetryDelay(PipedriveException $exception, int $attemptNumber): int
    {
        $baseDelay = $exception->getRetryAfter();

        // Apply exponential backoff for certain error types
        if (in_array($exception->getErrorType(), ['server', 'connection', 'rate_limit'])) {
            $exponentialDelay = min(pow(2, $attemptNumber - 1), 60); // Max 60 seconds
            $baseDelay = max($baseDelay, $exponentialDelay);
        }

        // Add jitter to prevent thundering herd
        $jitter = rand(0, (int) ($baseDelay * 0.1));

        return $baseDelay + $jitter;
    }

    /**
     * Record failure for circuit breaker
     */
    public function recordFailure(PipedriveException $exception): void
    {
        $errorType = $exception->getErrorType();
        $cacheKey = $this->getCircuitBreakerKey($errorType);

        $failures = Cache::get($cacheKey, 0) + 1;
        Cache::put($cacheKey, $failures, now()->addMinutes(10));

        Log::debug('Recorded failure for circuit breaker', [
            'error_type' => $errorType,
            'failure_count' => $failures,
            'threshold' => $this->config['circuit_breaker_threshold'],
        ]);

        // Open circuit breaker if threshold exceeded
        if ($failures >= $this->config['circuit_breaker_threshold']) {
            $this->openCircuitBreaker($errorType);
        }
    }

    /**
     * Record success for circuit breaker
     */
    public function recordSuccess(string $errorType): void
    {
        $cacheKey = $this->getCircuitBreakerKey($errorType);
        Cache::forget($cacheKey);

        // Close circuit breaker
        $this->closeCircuitBreaker($errorType);

        Log::debug('Recorded success for circuit breaker', [
            'error_type' => $errorType,
        ]);
    }

    /**
     * Check if circuit breaker is open
     */
    public function isCircuitBreakerOpen(string $errorType): bool
    {
        $cacheKey = $this->getCircuitBreakerOpenKey($errorType);

        return Cache::has($cacheKey);
    }

    /**
     * Open circuit breaker
     */
    protected function openCircuitBreaker(string $errorType): void
    {
        $cacheKey = $this->getCircuitBreakerOpenKey($errorType);
        Cache::put($cacheKey, true, now()->addSeconds($this->config['circuit_breaker_timeout']));

        Log::warning('Circuit breaker opened', [
            'error_type' => $errorType,
            'timeout_seconds' => $this->config['circuit_breaker_timeout'],
        ]);
    }

    /**
     * Close circuit breaker
     */
    protected function closeCircuitBreaker(string $errorType): void
    {
        $openKey = $this->getCircuitBreakerOpenKey($errorType);
        $failureKey = $this->getCircuitBreakerKey($errorType);

        Cache::forget($openKey);
        Cache::forget($failureKey);

        Log::info('Circuit breaker closed', [
            'error_type' => $errorType,
        ]);
    }

    /**
     * Get circuit breaker cache key
     */
    protected function getCircuitBreakerKey(string $errorType): string
    {
        return "{$this->cachePrefix}:failures:{$errorType}";
    }

    /**
     * Get circuit breaker open cache key
     */
    protected function getCircuitBreakerOpenKey(string $errorType): string
    {
        return "{$this->cachePrefix}:open:{$errorType}";
    }

    /**
     * Get circuit breaker status
     */
    public function getCircuitBreakerStatus(): array
    {
        $errorTypes = ['api', 'rate_limit', 'auth', 'quota', 'server', 'connection', 'memory'];
        $status = [];

        foreach ($errorTypes as $errorType) {
            $failureKey = $this->getCircuitBreakerKey($errorType);
            $openKey = $this->getCircuitBreakerOpenKey($errorType);

            $status[$errorType] = [
                'failures' => Cache::get($failureKey, 0),
                'is_open' => Cache::has($openKey),
                'threshold' => $this->config['circuit_breaker_threshold'],
            ];
        }

        return $status;
    }

    /**
     * Reset circuit breaker (for testing)
     */
    public function resetCircuitBreaker(?string $errorType = null): void
    {
        if ($errorType) {
            Cache::forget($this->getCircuitBreakerKey($errorType));
            Cache::forget($this->getCircuitBreakerOpenKey($errorType));
        } else {
            // Reset all circuit breakers
            $errorTypes = ['api', 'rate_limit', 'auth', 'quota', 'server', 'connection', 'memory'];
            foreach ($errorTypes as $type) {
                Cache::forget($this->getCircuitBreakerKey($type));
                Cache::forget($this->getCircuitBreakerOpenKey($type));
            }
        }
    }
}
