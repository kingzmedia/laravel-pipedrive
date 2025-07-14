<?php

namespace Keggermont\LaravelPipedrive\Exceptions;

use Throwable;

/**
 * Exception for network connectivity issues
 * 
 * Handles network timeouts, DNS failures, and connection problems
 */
class PipedriveConnectionException extends PipedriveException
{
    protected string $connectionType = 'unknown';
    protected ?string $host = null;
    protected ?int $port = null;
    protected float $timeout = 0.0;
    protected int $connectionAttempts = 0;
    protected ?string $dnsError = null;
    protected ?string $sslError = null;

    public function __construct(
        string $message = 'Connection to Pipedrive failed',
        int $code = 0,
        ?Throwable $previous = null,
        array $context = [],
        bool $retryable = true,
        int $retryAfter = 10,
        int $maxRetries = 5,
        string $connectionType = 'unknown',
        ?string $host = null,
        ?int $port = null,
        float $timeout = 0.0,
        int $connectionAttempts = 0,
        ?string $dnsError = null,
        ?string $sslError = null
    ) {
        parent::__construct(
            message: $message,
            code: $code,
            previous: $previous,
            context: $context,
            retryable: $retryable,
            retryAfter: $retryAfter,
            maxRetries: $maxRetries,
            errorType: 'connection'
        );

        $this->connectionType = $connectionType;
        $this->host = $host;
        $this->port = $port;
        $this->timeout = $timeout;
        $this->connectionAttempts = $connectionAttempts;
        $this->dnsError = $dnsError;
        $this->sslError = $sslError;
    }

    /**
     * Get connection type (http, https, tcp, etc.)
     */
    public function getConnectionType(): string
    {
        return $this->connectionType;
    }

    /**
     * Set connection type
     */
    public function setConnectionType(string $connectionType): self
    {
        $this->connectionType = $connectionType;
        return $this;
    }

    /**
     * Get target host
     */
    public function getHost(): ?string
    {
        return $this->host;
    }

    /**
     * Set target host
     */
    public function setHost(?string $host): self
    {
        $this->host = $host;
        return $this;
    }

    /**
     * Get target port
     */
    public function getPort(): ?int
    {
        return $this->port;
    }

    /**
     * Set target port
     */
    public function setPort(?int $port): self
    {
        $this->port = $port;
        return $this;
    }

    /**
     * Get connection timeout
     */
    public function getTimeout(): float
    {
        return $this->timeout;
    }

    /**
     * Set connection timeout
     */
    public function setTimeout(float $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * Get number of connection attempts made
     */
    public function getConnectionAttempts(): int
    {
        return $this->connectionAttempts;
    }

    /**
     * Set number of connection attempts made
     */
    public function setConnectionAttempts(int $connectionAttempts): self
    {
        $this->connectionAttempts = $connectionAttempts;
        return $this;
    }

    /**
     * Get DNS error if any
     */
    public function getDnsError(): ?string
    {
        return $this->dnsError;
    }

    /**
     * Set DNS error
     */
    public function setDnsError(?string $dnsError): self
    {
        $this->dnsError = $dnsError;
        return $this;
    }

    /**
     * Get SSL error if any
     */
    public function getSslError(): ?string
    {
        return $this->sslError;
    }

    /**
     * Set SSL error
     */
    public function setSslError(?string $sslError): self
    {
        $this->sslError = $sslError;
        return $this;
    }

    /**
     * Check if this is a timeout error
     */
    public function isTimeout(): bool
    {
        return str_contains(strtolower($this->getMessage()), 'timeout') ||
               str_contains(strtolower($this->getMessage()), 'timed out');
    }

    /**
     * Check if this is a DNS error
     */
    public function isDnsError(): bool
    {
        return $this->dnsError !== null ||
               str_contains(strtolower($this->getMessage()), 'dns') ||
               str_contains(strtolower($this->getMessage()), 'name resolution');
    }

    /**
     * Check if this is an SSL error
     */
    public function isSslError(): bool
    {
        return $this->sslError !== null ||
               str_contains(strtolower($this->getMessage()), 'ssl') ||
               str_contains(strtolower($this->getMessage()), 'certificate');
    }

    /**
     * Check if this is a connection refused error
     */
    public function isConnectionRefused(): bool
    {
        return str_contains(strtolower($this->getMessage()), 'connection refused') ||
               str_contains(strtolower($this->getMessage()), 'connection reset');
    }

    /**
     * Get recommended retry delay based on error type
     */
    public function getRecommendedRetryDelay(): int
    {
        if ($this->isTimeout()) {
            return 30; // Longer delay for timeouts
        }

        if ($this->isDnsError()) {
            return 60; // DNS issues might take longer to resolve
        }

        if ($this->isSslError()) {
            return 15; // SSL issues might be temporary
        }

        if ($this->isConnectionRefused()) {
            return 45; // Server might be temporarily down
        }

        return $this->getRetryAfter();
    }

    /**
     * Get connection target as string
     */
    public function getConnectionTarget(): string
    {
        if ($this->host && $this->port) {
            return "{$this->host}:{$this->port}";
        }

        if ($this->host) {
            return $this->host;
        }

        return 'unknown';
    }

    /**
     * Get formatted error information for logging
     */
    public function getErrorInfo(): array
    {
        $info = parent::getErrorInfo();
        
        $info['connection'] = [
            'connection_type' => $this->connectionType,
            'host' => $this->host,
            'port' => $this->port,
            'timeout' => $this->timeout,
            'connection_attempts' => $this->connectionAttempts,
            'connection_target' => $this->getConnectionTarget(),
            'dns_error' => $this->dnsError,
            'ssl_error' => $this->sslError,
            'is_timeout' => $this->isTimeout(),
            'is_dns_error' => $this->isDnsError(),
            'is_ssl_error' => $this->isSslError(),
            'is_connection_refused' => $this->isConnectionRefused(),
            'recommended_retry_delay' => $this->getRecommendedRetryDelay(),
        ];

        return $info;
    }

    /**
     * Convert exception to array for serialization
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        
        $array['connection'] = [
            'connection_type' => $this->connectionType,
            'host' => $this->host,
            'port' => $this->port,
            'timeout' => $this->timeout,
            'connection_attempts' => $this->connectionAttempts,
            'dns_error' => $this->dnsError,
            'ssl_error' => $this->sslError,
        ];

        return $array;
    }

    /**
     * Create exception for connection timeout
     */
    public static function timeout(
        string $host,
        float $timeout,
        int $attempts = 1
    ): static {
        return new static(
            message: "Connection to {$host} timed out after {$timeout} seconds",
            connectionType: 'http',
            host: $host,
            timeout: $timeout,
            connectionAttempts: $attempts,
            retryAfter: 30
        );
    }

    /**
     * Create exception for DNS resolution failure
     */
    public static function dnsFailure(
        string $host,
        string $dnsError
    ): static {
        return new static(
            message: "DNS resolution failed for {$host}: {$dnsError}",
            connectionType: 'dns',
            host: $host,
            dnsError: $dnsError,
            retryAfter: 60
        );
    }

    /**
     * Create exception for SSL certificate error
     */
    public static function sslError(
        string $host,
        string $sslError
    ): static {
        return new static(
            message: "SSL certificate error for {$host}: {$sslError}",
            connectionType: 'https',
            host: $host,
            sslError: $sslError,
            retryAfter: 15,
            maxRetries: 2 // SSL errors are less likely to be resolved by retrying
        );
    }

    /**
     * Create exception for connection refused
     */
    public static function connectionRefused(
        string $host,
        ?int $port = null
    ): static {
        $target = $port ? "{$host}:{$port}" : $host;
        
        return new static(
            message: "Connection refused to {$target}",
            connectionType: 'tcp',
            host: $host,
            port: $port,
            retryAfter: 45
        );
    }
}
