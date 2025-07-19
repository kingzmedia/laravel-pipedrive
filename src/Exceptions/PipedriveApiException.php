<?php

namespace Skeylup\LaravelPipedrive\Exceptions;

use Throwable;

/**
 * Base exception for HTTP API-related errors
 * 
 * Extends PipedriveException with HTTP-specific functionality
 */
class PipedriveApiException extends PipedriveException
{
    protected int $httpStatusCode = 0;
    protected array $httpHeaders = [];
    protected ?string $httpMethod = null;
    protected ?string $httpUrl = null;
    protected ?array $requestData = null;
    protected ?string $responseBody = null;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        array $context = [],
        bool $retryable = false,
        int $retryAfter = 0,
        int $maxRetries = 3,
        string $errorType = 'api',
        ?array $pipedriveResponse = null,
        int $httpStatusCode = 0,
        array $httpHeaders = [],
        ?string $httpMethod = null,
        ?string $httpUrl = null,
        ?array $requestData = null,
        ?string $responseBody = null
    ) {
        parent::__construct(
            $message,
            $code,
            $previous,
            $context,
            $retryable,
            $retryAfter,
            $maxRetries,
            $errorType,
            $pipedriveResponse
        );

        $this->httpStatusCode = $httpStatusCode;
        $this->httpHeaders = $httpHeaders;
        $this->httpMethod = $httpMethod;
        $this->httpUrl = $httpUrl;
        $this->requestData = $requestData;
        $this->responseBody = $responseBody;
    }

    /**
     * Get HTTP status code
     */
    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    /**
     * Set HTTP status code
     */
    public function setHttpStatusCode(int $httpStatusCode): self
    {
        $this->httpStatusCode = $httpStatusCode;
        return $this;
    }

    /**
     * Get HTTP response headers
     */
    public function getHttpHeaders(): array
    {
        return $this->httpHeaders;
    }

    /**
     * Set HTTP response headers
     */
    public function setHttpHeaders(array $httpHeaders): self
    {
        $this->httpHeaders = $httpHeaders;
        return $this;
    }

    /**
     * Get HTTP method used in request
     */
    public function getHttpMethod(): ?string
    {
        return $this->httpMethod;
    }

    /**
     * Set HTTP method used in request
     */
    public function setHttpMethod(?string $httpMethod): self
    {
        $this->httpMethod = $httpMethod;
        return $this;
    }

    /**
     * Get HTTP URL that was requested
     */
    public function getHttpUrl(): ?string
    {
        return $this->httpUrl;
    }

    /**
     * Set HTTP URL that was requested
     */
    public function setHttpUrl(?string $httpUrl): self
    {
        $this->httpUrl = $httpUrl;
        return $this;
    }

    /**
     * Get request data that was sent
     */
    public function getRequestData(): ?array
    {
        return $this->requestData;
    }

    /**
     * Set request data that was sent
     */
    public function setRequestData(?array $requestData): self
    {
        $this->requestData = $requestData;
        return $this;
    }

    /**
     * Get raw response body
     */
    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }

    /**
     * Set raw response body
     */
    public function setResponseBody(?string $responseBody): self
    {
        $this->responseBody = $responseBody;
        return $this;
    }

    /**
     * Check if this is a client error (4xx)
     */
    public function isClientError(): bool
    {
        return $this->httpStatusCode >= 400 && $this->httpStatusCode < 500;
    }

    /**
     * Check if this is a server error (5xx)
     */
    public function isServerError(): bool
    {
        return $this->httpStatusCode >= 500 && $this->httpStatusCode < 600;
    }

    /**
     * Get formatted error information for logging
     */
    public function getErrorInfo(): array
    {
        $info = parent::getErrorInfo();
        
        $info['http'] = [
            'status_code' => $this->httpStatusCode,
            'method' => $this->httpMethod,
            'url' => $this->httpUrl,
            'headers' => $this->httpHeaders,
            'request_data' => $this->requestData,
            'response_body' => $this->responseBody,
        ];

        return $info;
    }

    /**
     * Convert exception to array for serialization
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        
        $array['http'] = [
            'status_code' => $this->httpStatusCode,
            'method' => $this->httpMethod,
            'url' => $this->httpUrl,
            'headers' => $this->httpHeaders,
            'request_data' => $this->requestData,
            'response_body' => $this->responseBody,
        ];

        return $array;
    }

    /**
     * Create exception from HTTP response
     */
    public static function fromHttpResponse(
        int $statusCode,
        string $method,
        string $url,
        array $headers = [],
        ?array $requestData = null,
        ?string $responseBody = null,
        ?array $pipedriveResponse = null
    ): static {
        $message = "HTTP {$statusCode} error for {$method} {$url}";
        
        // Determine if retryable based on status code
        $retryable = in_array($statusCode, [429, 500, 502, 503, 504]);
        
        // Get retry-after from headers if available
        $retryAfter = 0;
        if (isset($headers['retry-after'])) {
            $retryAfter = (int) $headers['retry-after'];
        } elseif (isset($headers['Retry-After'])) {
            $retryAfter = (int) $headers['Retry-After'];
        }

        return new static(
            message: $message,
            code: $statusCode,
            retryable: $retryable,
            retryAfter: $retryAfter,
            httpStatusCode: $statusCode,
            httpHeaders: $headers,
            httpMethod: $method,
            httpUrl: $url,
            requestData: $requestData,
            responseBody: $responseBody,
            pipedriveResponse: $pipedriveResponse
        );
    }
}
