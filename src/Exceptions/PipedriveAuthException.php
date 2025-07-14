<?php

namespace Keggermont\LaravelPipedrive\Exceptions;

use Throwable;

/**
 * Exception for authentication and authorization errors (HTTP 401/403)
 * 
 * These errors are typically not retryable as they indicate credential issues
 */
class PipedriveAuthException extends PipedriveApiException
{
    protected string $authMethod = 'unknown';
    protected bool $tokenExpired = false;
    protected ?int $tokenExpiresAt = null;

    public function __construct(
        string $message = 'Authentication failed',
        int $code = 401,
        ?Throwable $previous = null,
        array $context = [],
        int $maxRetries = 1, // Usually not retryable
        ?array $pipedriveResponse = null,
        array $httpHeaders = [],
        ?string $httpMethod = null,
        ?string $httpUrl = null,
        ?array $requestData = null,
        ?string $responseBody = null,
        string $authMethod = 'unknown',
        bool $tokenExpired = false,
        ?int $tokenExpiresAt = null
    ) {
        parent::__construct(
            message: $message,
            code: $code,
            previous: $previous,
            context: $context,
            retryable: false, // Auth errors are typically not retryable
            retryAfter: 0,
            maxRetries: $maxRetries,
            errorType: 'authentication',
            pipedriveResponse: $pipedriveResponse,
            httpStatusCode: $code,
            httpHeaders: $httpHeaders,
            httpMethod: $httpMethod,
            httpUrl: $httpUrl,
            requestData: $requestData,
            responseBody: $responseBody
        );

        $this->authMethod = $authMethod;
        $this->tokenExpired = $tokenExpired;
        $this->tokenExpiresAt = $tokenExpiresAt;
    }

    /**
     * Get authentication method used
     */
    public function getAuthMethod(): string
    {
        return $this->authMethod;
    }

    /**
     * Set authentication method used
     */
    public function setAuthMethod(string $authMethod): self
    {
        $this->authMethod = $authMethod;
        return $this;
    }

    /**
     * Check if token is expired
     */
    public function isTokenExpired(): bool
    {
        return $this->tokenExpired;
    }

    /**
     * Set token expired status
     */
    public function setTokenExpired(bool $tokenExpired): self
    {
        $this->tokenExpired = $tokenExpired;
        
        // If token is expired and we have OAuth, it might be retryable
        if ($tokenExpired && $this->authMethod === 'oauth') {
            $this->setRetryable(true);
            $this->setMaxRetries(2);
        }
        
        return $this;
    }

    /**
     * Get token expiration time
     */
    public function getTokenExpiresAt(): ?int
    {
        return $this->tokenExpiresAt;
    }

    /**
     * Set token expiration time
     */
    public function setTokenExpiresAt(?int $tokenExpiresAt): self
    {
        $this->tokenExpiresAt = $tokenExpiresAt;
        return $this;
    }

    /**
     * Check if this is an unauthorized error (401)
     */
    public function isUnauthorized(): bool
    {
        return $this->getHttpStatusCode() === 401;
    }

    /**
     * Check if this is a forbidden error (403)
     */
    public function isForbidden(): bool
    {
        return $this->getHttpStatusCode() === 403;
    }

    /**
     * Check if token refresh might help
     */
    public function canRefreshToken(): bool
    {
        return $this->authMethod === 'oauth' && $this->tokenExpired;
    }

    /**
     * Get suggested action for resolving the error
     */
    public function getSuggestedAction(): string
    {
        if ($this->isUnauthorized()) {
            if ($this->tokenExpired && $this->authMethod === 'oauth') {
                return 'refresh_token';
            }
            return 'check_credentials';
        }

        if ($this->isForbidden()) {
            return 'check_permissions';
        }

        return 'contact_support';
    }

    /**
     * Get formatted error information for logging
     */
    public function getErrorInfo(): array
    {
        $info = parent::getErrorInfo();
        
        $info['auth'] = [
            'auth_method' => $this->authMethod,
            'token_expired' => $this->tokenExpired,
            'token_expires_at' => $this->tokenExpiresAt,
            'is_unauthorized' => $this->isUnauthorized(),
            'is_forbidden' => $this->isForbidden(),
            'can_refresh_token' => $this->canRefreshToken(),
            'suggested_action' => $this->getSuggestedAction(),
        ];

        return $info;
    }

    /**
     * Convert exception to array for serialization
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        
        $array['auth'] = [
            'auth_method' => $this->authMethod,
            'token_expired' => $this->tokenExpired,
            'token_expires_at' => $this->tokenExpiresAt,
        ];

        return $array;
    }

    /**
     * Create exception for invalid credentials
     */
    public static function invalidCredentials(
        string $authMethod = 'api_token',
        ?array $pipedriveResponse = null
    ): static {
        return new static(
            message: 'Invalid Pipedrive credentials',
            code: 401,
            authMethod: $authMethod,
            pipedriveResponse: $pipedriveResponse
        );
    }

    /**
     * Create exception for expired token
     */
    public static function expiredToken(
        string $authMethod = 'oauth',
        ?int $tokenExpiresAt = null,
        ?array $pipedriveResponse = null
    ): static {
        return new static(
            message: 'Pipedrive token has expired',
            code: 401,
            authMethod: $authMethod,
            tokenExpired: true,
            tokenExpiresAt: $tokenExpiresAt,
            pipedriveResponse: $pipedriveResponse
        );
    }

    /**
     * Create exception for insufficient permissions
     */
    public static function insufficientPermissions(
        string $requiredPermission = '',
        ?array $pipedriveResponse = null
    ): static {
        $message = 'Insufficient permissions to access Pipedrive resource';
        if ($requiredPermission) {
            $message .= ": {$requiredPermission}";
        }

        return new static(
            message: $message,
            code: 403,
            context: ['required_permission' => $requiredPermission],
            pipedriveResponse: $pipedriveResponse
        );
    }
}
