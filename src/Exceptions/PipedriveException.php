<?php

namespace Keggermont\LaravelPipedrive\Exceptions;

use Exception;
use Throwable;

/**
 * Base exception for all Pipedrive-related errors
 * 
 * Provides context information and retry capabilities for all Pipedrive exceptions
 */
class PipedriveException extends Exception
{
    protected array $context = [];
    protected bool $retryable = false;
    protected int $retryAfter = 0;
    protected int $maxRetries = 3;
    protected string $errorType = 'general';
    protected ?array $pipedriveResponse = null;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        array $context = [],
        bool $retryable = false,
        int $retryAfter = 0,
        int $maxRetries = 3,
        string $errorType = 'general',
        ?array $pipedriveResponse = null
    ) {
        parent::__construct($message, $code, $previous);
        
        $this->context = $context;
        $this->retryable = $retryable;
        $this->retryAfter = $retryAfter;
        $this->maxRetries = $maxRetries;
        $this->errorType = $errorType;
        $this->pipedriveResponse = $pipedriveResponse;
    }

    /**
     * Get additional context information
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Set additional context information
     */
    public function setContext(array $context): self
    {
        $this->context = $context;
        return $this;
    }

    /**
     * Add context information
     */
    public function addContext(string $key, mixed $value): self
    {
        $this->context[$key] = $value;
        return $this;
    }

    /**
     * Check if this exception is retryable
     */
    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    /**
     * Set retryable status
     */
    public function setRetryable(bool $retryable): self
    {
        $this->retryable = $retryable;
        return $this;
    }

    /**
     * Get retry delay in seconds
     */
    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }

    /**
     * Set retry delay in seconds
     */
    public function setRetryAfter(int $retryAfter): self
    {
        $this->retryAfter = $retryAfter;
        return $this;
    }

    /**
     * Get maximum retry attempts
     */
    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    /**
     * Set maximum retry attempts
     */
    public function setMaxRetries(int $maxRetries): self
    {
        $this->maxRetries = $maxRetries;
        return $this;
    }

    /**
     * Get error type classification
     */
    public function getErrorType(): string
    {
        return $this->errorType;
    }

    /**
     * Set error type classification
     */
    public function setErrorType(string $errorType): self
    {
        $this->errorType = $errorType;
        return $this;
    }

    /**
     * Get Pipedrive API response if available
     */
    public function getPipedriveResponse(): ?array
    {
        return $this->pipedriveResponse;
    }

    /**
     * Set Pipedrive API response
     */
    public function setPipedriveResponse(?array $pipedriveResponse): self
    {
        $this->pipedriveResponse = $pipedriveResponse;
        return $this;
    }

    /**
     * Get formatted error information for logging
     */
    public function getErrorInfo(): array
    {
        return [
            'exception_class' => static::class,
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'error_type' => $this->errorType,
            'retryable' => $this->retryable,
            'retry_after' => $this->retryAfter,
            'max_retries' => $this->maxRetries,
            'context' => $this->context,
            'pipedrive_response' => $this->pipedriveResponse,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'trace' => $this->getTraceAsString(),
        ];
    }

    /**
     * Convert exception to array for serialization
     */
    public function toArray(): array
    {
        return [
            'class' => static::class,
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'error_type' => $this->errorType,
            'retryable' => $this->retryable,
            'retry_after' => $this->retryAfter,
            'max_retries' => $this->maxRetries,
            'context' => $this->context,
            'pipedrive_response' => $this->pipedriveResponse,
        ];
    }

    /**
     * Create exception from array
     */
    public static function fromArray(array $data): static
    {
        return new static(
            message: $data['message'] ?? '',
            code: $data['code'] ?? 0,
            context: $data['context'] ?? [],
            retryable: $data['retryable'] ?? false,
            retryAfter: $data['retry_after'] ?? 0,
            maxRetries: $data['max_retries'] ?? 3,
            errorType: $data['error_type'] ?? 'general',
            pipedriveResponse: $data['pipedrive_response'] ?? null
        );
    }
}
