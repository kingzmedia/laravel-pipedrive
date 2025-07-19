<?php

namespace Skeylup\LaravelPipedrive\Exceptions;

use Throwable;

/**
 * Exception for server errors (HTTP 500/503)
 * 
 * These errors are typically retryable as they indicate temporary server issues
 */
class PipedriveServerException extends PipedriveApiException
{
    protected bool $isTemporary = true;
    protected ?string $serviceStatus = null;
    protected ?int $estimatedRecoveryTime = null;

    public function __construct(
        string $message = 'Pipedrive server error',
        int $code = 500,
        ?Throwable $previous = null,
        array $context = [],
        int $retryAfter = 30,
        int $maxRetries = 5,
        ?array $pipedriveResponse = null,
        array $httpHeaders = [],
        ?string $httpMethod = null,
        ?string $httpUrl = null,
        ?array $requestData = null,
        ?string $responseBody = null,
        bool $isTemporary = true,
        ?string $serviceStatus = null,
        ?int $estimatedRecoveryTime = null
    ) {
        parent::__construct(
            message: $message,
            code: $code,
            previous: $previous,
            context: $context,
            retryable: true, // Server errors are typically retryable
            retryAfter: $retryAfter,
            maxRetries: $maxRetries,
            errorType: 'server',
            pipedriveResponse: $pipedriveResponse,
            httpStatusCode: $code,
            httpHeaders: $httpHeaders,
            httpMethod: $httpMethod,
            httpUrl: $httpUrl,
            requestData: $requestData,
            responseBody: $responseBody
        );

        $this->isTemporary = $isTemporary;
        $this->serviceStatus = $serviceStatus;
        $this->estimatedRecoveryTime = $estimatedRecoveryTime;
    }

    /**
     * Check if this is a temporary error
     */
    public function isTemporary(): bool
    {
        return $this->isTemporary;
    }

    /**
     * Set temporary status
     */
    public function setTemporary(bool $isTemporary): self
    {
        $this->isTemporary = $isTemporary;
        return $this;
    }

    /**
     * Get service status
     */
    public function getServiceStatus(): ?string
    {
        return $this->serviceStatus;
    }

    /**
     * Set service status
     */
    public function setServiceStatus(?string $serviceStatus): self
    {
        $this->serviceStatus = $serviceStatus;
        return $this;
    }

    /**
     * Get estimated recovery time
     */
    public function getEstimatedRecoveryTime(): ?int
    {
        return $this->estimatedRecoveryTime;
    }

    /**
     * Set estimated recovery time
     */
    public function setEstimatedRecoveryTime(?int $estimatedRecoveryTime): self
    {
        $this->estimatedRecoveryTime = $estimatedRecoveryTime;
        return $this;
    }

    /**
     * Check if this is an internal server error (500)
     */
    public function isInternalServerError(): bool
    {
        return $this->getHttpStatusCode() === 500;
    }

    /**
     * Check if this is a bad gateway error (502)
     */
    public function isBadGateway(): bool
    {
        return $this->getHttpStatusCode() === 502;
    }

    /**
     * Check if this is a service unavailable error (503)
     */
    public function isServiceUnavailable(): bool
    {
        return $this->getHttpStatusCode() === 503;
    }

    /**
     * Check if this is a gateway timeout error (504)
     */
    public function isGatewayTimeout(): bool
    {
        return $this->getHttpStatusCode() === 504;
    }

    /**
     * Get recommended retry delay based on error type
     */
    public function getRecommendedRetryDelay(): int
    {
        return match ($this->getHttpStatusCode()) {
            500 => 30,  // Internal server error - moderate delay
            502 => 10,  // Bad gateway - shorter delay
            503 => 60,  // Service unavailable - longer delay
            504 => 45,  // Gateway timeout - moderate delay
            default => $this->getRetryAfter()
        };
    }

    /**
     * Get error severity level
     */
    public function getSeverityLevel(): string
    {
        return match ($this->getHttpStatusCode()) {
            500 => 'high',     // Internal server error
            502 => 'medium',   // Bad gateway
            503 => 'high',     // Service unavailable
            504 => 'medium',   // Gateway timeout
            default => 'medium'
        };
    }

    /**
     * Get formatted error information for logging
     */
    public function getErrorInfo(): array
    {
        $info = parent::getErrorInfo();
        
        $info['server'] = [
            'is_temporary' => $this->isTemporary,
            'service_status' => $this->serviceStatus,
            'estimated_recovery_time' => $this->estimatedRecoveryTime,
            'is_internal_server_error' => $this->isInternalServerError(),
            'is_bad_gateway' => $this->isBadGateway(),
            'is_service_unavailable' => $this->isServiceUnavailable(),
            'is_gateway_timeout' => $this->isGatewayTimeout(),
            'recommended_retry_delay' => $this->getRecommendedRetryDelay(),
            'severity_level' => $this->getSeverityLevel(),
        ];

        return $info;
    }

    /**
     * Convert exception to array for serialization
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        
        $array['server'] = [
            'is_temporary' => $this->isTemporary,
            'service_status' => $this->serviceStatus,
            'estimated_recovery_time' => $this->estimatedRecoveryTime,
        ];

        return $array;
    }

    /**
     * Create exception for internal server error
     */
    public static function internalServerError(
        string $message = 'Pipedrive internal server error',
        ?array $pipedriveResponse = null
    ): static {
        return new static(
            message: $message,
            code: 500,
            retryAfter: 30,
            pipedriveResponse: $pipedriveResponse
        );
    }

    /**
     * Create exception for service unavailable
     */
    public static function serviceUnavailable(
        string $message = 'Pipedrive service temporarily unavailable',
        int $retryAfter = 60,
        ?string $serviceStatus = null,
        ?int $estimatedRecoveryTime = null
    ): static {
        return new static(
            message: $message,
            code: 503,
            retryAfter: $retryAfter,
            serviceStatus: $serviceStatus,
            estimatedRecoveryTime: $estimatedRecoveryTime
        );
    }

    /**
     * Create exception for gateway timeout
     */
    public static function gatewayTimeout(
        string $message = 'Pipedrive gateway timeout',
        ?array $pipedriveResponse = null
    ): static {
        return new static(
            message: $message,
            code: 504,
            retryAfter: 45,
            pipedriveResponse: $pipedriveResponse
        );
    }
}
