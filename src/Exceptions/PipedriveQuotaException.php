<?php

namespace Skeylup\LaravelPipedrive\Exceptions;

use Throwable;

/**
 * Exception for quota/payment required errors (HTTP 402)
 *
 * Indicates account limits or payment issues
 */
class PipedriveQuotaException extends PipedriveApiException
{
    protected string $quotaType = 'unknown';

    protected int $currentUsage = 0;

    protected int $quotaLimit = 0;

    protected ?string $upgradeUrl = null;

    public function __construct(
        string $message = 'Quota exceeded or payment required',
        int $code = 402,
        ?Throwable $previous = null,
        array $context = [],
        int $maxRetries = 1, // Usually not retryable without account changes
        ?array $pipedriveResponse = null,
        array $httpHeaders = [],
        ?string $httpMethod = null,
        ?string $httpUrl = null,
        ?array $requestData = null,
        ?string $responseBody = null,
        string $quotaType = 'unknown',
        int $currentUsage = 0,
        int $quotaLimit = 0,
        ?string $upgradeUrl = null
    ) {
        parent::__construct(
            message: $message,
            code: $code,
            previous: $previous,
            context: $context,
            retryable: false, // Quota errors are typically not retryable
            retryAfter: 0,
            maxRetries: $maxRetries,
            errorType: 'quota',
            pipedriveResponse: $pipedriveResponse,
            httpStatusCode: 402,
            httpHeaders: $httpHeaders,
            httpMethod: $httpMethod,
            httpUrl: $httpUrl,
            requestData: $requestData,
            responseBody: $responseBody
        );

        $this->quotaType = $quotaType;
        $this->currentUsage = $currentUsage;
        $this->quotaLimit = $quotaLimit;
        $this->upgradeUrl = $upgradeUrl;
    }

    /**
     * Get quota type (e.g., 'users', 'deals', 'storage')
     */
    public function getQuotaType(): string
    {
        return $this->quotaType;
    }

    /**
     * Set quota type
     */
    public function setQuotaType(string $quotaType): self
    {
        $this->quotaType = $quotaType;

        return $this;
    }

    /**
     * Get current usage
     */
    public function getCurrentUsage(): int
    {
        return $this->currentUsage;
    }

    /**
     * Set current usage
     */
    public function setCurrentUsage(int $currentUsage): self
    {
        $this->currentUsage = $currentUsage;

        return $this;
    }

    /**
     * Get quota limit
     */
    public function getQuotaLimit(): int
    {
        return $this->quotaLimit;
    }

    /**
     * Set quota limit
     */
    public function setQuotaLimit(int $quotaLimit): self
    {
        $this->quotaLimit = $quotaLimit;

        return $this;
    }

    /**
     * Get upgrade URL
     */
    public function getUpgradeUrl(): ?string
    {
        return $this->upgradeUrl;
    }

    /**
     * Set upgrade URL
     */
    public function setUpgradeUrl(?string $upgradeUrl): self
    {
        $this->upgradeUrl = $upgradeUrl;

        return $this;
    }

    /**
     * Get usage percentage
     */
    public function getUsagePercentage(): float
    {
        if ($this->quotaLimit === 0) {
            return 0.0;
        }

        return ($this->currentUsage / $this->quotaLimit) * 100;
    }

    /**
     * Get remaining quota
     */
    public function getRemainingQuota(): int
    {
        return max(0, $this->quotaLimit - $this->currentUsage);
    }

    /**
     * Check if quota is exceeded
     */
    public function isQuotaExceeded(): bool
    {
        return $this->currentUsage >= $this->quotaLimit;
    }

    /**
     * Get formatted error information for logging
     */
    public function getErrorInfo(): array
    {
        $info = parent::getErrorInfo();

        $info['quota'] = [
            'quota_type' => $this->quotaType,
            'current_usage' => $this->currentUsage,
            'quota_limit' => $this->quotaLimit,
            'remaining_quota' => $this->getRemainingQuota(),
            'usage_percentage' => $this->getUsagePercentage(),
            'is_quota_exceeded' => $this->isQuotaExceeded(),
            'upgrade_url' => $this->upgradeUrl,
        ];

        return $info;
    }

    /**
     * Convert exception to array for serialization
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        $array['quota'] = [
            'quota_type' => $this->quotaType,
            'current_usage' => $this->currentUsage,
            'quota_limit' => $this->quotaLimit,
            'upgrade_url' => $this->upgradeUrl,
        ];

        return $array;
    }

    /**
     * Create exception for exceeded user quota
     */
    public static function userQuotaExceeded(
        int $currentUsers,
        int $maxUsers,
        ?string $upgradeUrl = null
    ): static {
        return new static(
            message: "User quota exceeded: {$currentUsers}/{$maxUsers} users",
            quotaType: 'users',
            currentUsage: $currentUsers,
            quotaLimit: $maxUsers,
            upgradeUrl: $upgradeUrl
        );
    }

    /**
     * Create exception for exceeded storage quota
     */
    public static function storageQuotaExceeded(
        int $currentStorage,
        int $maxStorage,
        ?string $upgradeUrl = null
    ): static {
        return new static(
            message: "Storage quota exceeded: {$currentStorage}/{$maxStorage} MB",
            quotaType: 'storage',
            currentUsage: $currentStorage,
            quotaLimit: $maxStorage,
            upgradeUrl: $upgradeUrl
        );
    }

    /**
     * Create exception for payment required
     */
    public static function paymentRequired(
        string $reason = 'Account payment required',
        ?string $upgradeUrl = null
    ): static {
        return new static(
            message: $reason,
            quotaType: 'payment',
            upgradeUrl: $upgradeUrl
        );
    }
}
