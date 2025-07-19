<?php

namespace Skeylup\LaravelPipedrive\Exceptions;

use Throwable;

/**
 * Exception for rate limit errors (HTTP 429)
 * 
 * Handles Pipedrive's token-based rate limiting system
 */
class PipedriveRateLimitException extends PipedriveApiException
{
    protected int $tokensRemaining = 0;
    protected int $tokensUsed = 0;
    protected int $dailyLimit = 0;
    protected ?int $resetTime = null;
    protected string $limitType = 'requests'; // 'requests' or 'tokens'

    public function __construct(
        string $message = 'Rate limit exceeded',
        int $code = 429,
        ?Throwable $previous = null,
        array $context = [],
        int $retryAfter = 60,
        int $maxRetries = 5,
        ?array $pipedriveResponse = null,
        array $httpHeaders = [],
        ?string $httpMethod = null,
        ?string $httpUrl = null,
        ?array $requestData = null,
        ?string $responseBody = null,
        int $tokensRemaining = 0,
        int $tokensUsed = 0,
        int $dailyLimit = 0,
        ?int $resetTime = null,
        string $limitType = 'requests'
    ) {
        parent::__construct(
            message: $message,
            code: $code,
            previous: $previous,
            context: $context,
            retryable: true, // Rate limits are always retryable
            retryAfter: $retryAfter,
            maxRetries: $maxRetries,
            errorType: 'rate_limit',
            pipedriveResponse: $pipedriveResponse,
            httpStatusCode: 429,
            httpHeaders: $httpHeaders,
            httpMethod: $httpMethod,
            httpUrl: $httpUrl,
            requestData: $requestData,
            responseBody: $responseBody
        );

        $this->tokensRemaining = $tokensRemaining;
        $this->tokensUsed = $tokensUsed;
        $this->dailyLimit = $dailyLimit;
        $this->resetTime = $resetTime;
        $this->limitType = $limitType;
    }

    /**
     * Get remaining tokens/requests
     */
    public function getTokensRemaining(): int
    {
        return $this->tokensRemaining;
    }

    /**
     * Set remaining tokens/requests
     */
    public function setTokensRemaining(int $tokensRemaining): self
    {
        $this->tokensRemaining = $tokensRemaining;
        return $this;
    }

    /**
     * Get used tokens/requests
     */
    public function getTokensUsed(): int
    {
        return $this->tokensUsed;
    }

    /**
     * Set used tokens/requests
     */
    public function setTokensUsed(int $tokensUsed): self
    {
        $this->tokensUsed = $tokensUsed;
        return $this;
    }

    /**
     * Get daily limit
     */
    public function getDailyLimit(): int
    {
        return $this->dailyLimit;
    }

    /**
     * Set daily limit
     */
    public function setDailyLimit(int $dailyLimit): self
    {
        $this->dailyLimit = $dailyLimit;
        return $this;
    }

    /**
     * Get reset time (Unix timestamp)
     */
    public function getResetTime(): ?int
    {
        return $this->resetTime;
    }

    /**
     * Set reset time (Unix timestamp)
     */
    public function setResetTime(?int $resetTime): self
    {
        $this->resetTime = $resetTime;
        return $this;
    }

    /**
     * Get limit type (requests or tokens)
     */
    public function getLimitType(): string
    {
        return $this->limitType;
    }

    /**
     * Set limit type (requests or tokens)
     */
    public function setLimitType(string $limitType): self
    {
        $this->limitType = $limitType;
        return $this;
    }

    /**
     * Get seconds until reset
     */
    public function getSecondsUntilReset(): int
    {
        if ($this->resetTime === null) {
            return $this->getRetryAfter();
        }

        $secondsUntilReset = $this->resetTime - time();
        return max(0, $secondsUntilReset);
    }

    /**
     * Check if rate limit has reset
     */
    public function hasReset(): bool
    {
        if ($this->resetTime === null) {
            return false;
        }

        return time() >= $this->resetTime;
    }

    /**
     * Get usage percentage
     */
    public function getUsagePercentage(): float
    {
        if ($this->dailyLimit === 0) {
            return 0.0;
        }

        return ($this->tokensUsed / $this->dailyLimit) * 100;
    }

    /**
     * Get formatted error information for logging
     */
    public function getErrorInfo(): array
    {
        $info = parent::getErrorInfo();
        
        $info['rate_limit'] = [
            'tokens_remaining' => $this->tokensRemaining,
            'tokens_used' => $this->tokensUsed,
            'daily_limit' => $this->dailyLimit,
            'reset_time' => $this->resetTime,
            'seconds_until_reset' => $this->getSecondsUntilReset(),
            'usage_percentage' => $this->getUsagePercentage(),
            'limit_type' => $this->limitType,
            'has_reset' => $this->hasReset(),
        ];

        return $info;
    }

    /**
     * Convert exception to array for serialization
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        
        $array['rate_limit'] = [
            'tokens_remaining' => $this->tokensRemaining,
            'tokens_used' => $this->tokensUsed,
            'daily_limit' => $this->dailyLimit,
            'reset_time' => $this->resetTime,
            'limit_type' => $this->limitType,
        ];

        return $array;
    }

    /**
     * Create exception from rate limit headers
     */
    public static function fromRateLimitHeaders(
        array $headers,
        string $method = 'GET',
        string $url = '',
        ?array $requestData = null,
        ?string $responseBody = null
    ): static {
        // Parse rate limit headers (Pipedrive format)
        $tokensRemaining = (int) ($headers['x-ratelimit-remaining'] ?? $headers['X-RateLimit-Remaining'] ?? 0);
        $tokensUsed = (int) ($headers['x-ratelimit-used'] ?? $headers['X-RateLimit-Used'] ?? 0);
        $dailyLimit = (int) ($headers['x-ratelimit-limit'] ?? $headers['X-RateLimit-Limit'] ?? 0);
        $resetTime = isset($headers['x-ratelimit-reset']) ? (int) $headers['x-ratelimit-reset'] : 
                    (isset($headers['X-RateLimit-Reset']) ? (int) $headers['X-RateLimit-Reset'] : null);
        
        // Get retry-after
        $retryAfter = (int) ($headers['retry-after'] ?? $headers['Retry-After'] ?? 60);
        
        // Determine limit type based on headers
        $limitType = isset($headers['x-ratelimit-tokens']) || isset($headers['X-RateLimit-Tokens']) ? 'tokens' : 'requests';

        $message = "Rate limit exceeded. {$tokensUsed}/{$dailyLimit} {$limitType} used. ";
        $message .= $resetTime ? "Resets at " . date('Y-m-d H:i:s', $resetTime) : "Retry after {$retryAfter} seconds";

        return new static(
            message: $message,
            retryAfter: $retryAfter,
            httpHeaders: $headers,
            httpMethod: $method,
            httpUrl: $url,
            requestData: $requestData,
            responseBody: $responseBody,
            tokensRemaining: $tokensRemaining,
            tokensUsed: $tokensUsed,
            dailyLimit: $dailyLimit,
            resetTime: $resetTime,
            limitType: $limitType
        );
    }
}
