<?php

namespace Keggermont\LaravelPipedrive\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Keggermont\LaravelPipedrive\Exceptions\PipedriveRateLimitException;
use Carbon\Carbon;

/**
 * Token-based rate limiting manager for Pipedrive API
 * 
 * Supports Pipedrive's December 2024 token-based rate limiting system
 * with daily budget tracking, exponential backoff, and request queuing
 */
class PipedriveRateLimitManager
{
    protected array $config;
    protected string $cachePrefix = 'pipedrive_rate_limit';
    protected array $defaultTokenCosts = [
        'activities' => 1,
        'deals' => 1,
        'files' => 2,
        'goals' => 1,
        'notes' => 1,
        'organizations' => 1,
        'persons' => 1,
        'pipelines' => 1,
        'products' => 1,
        'stages' => 1,
        'users' => 1,
        'custom_fields' => 1,
        'webhooks' => 1,
    ];

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'enabled' => true,
            'daily_budget' => 10000,
            'max_delay' => 16,
            'jitter_enabled' => true,
            'token_costs' => $this->defaultTokenCosts,
        ], $config);
    }

    /**
     * Check if request can be made and consume tokens
     */
    public function canMakeRequest(string $endpoint, int $tokenCost = null): bool
    {
        if (!$this->config['enabled']) {
            return true;
        }

        $tokenCost = $tokenCost ?? $this->getTokenCost($endpoint);
        $currentUsage = $this->getCurrentUsage();
        $dailyBudget = $this->config['daily_budget'];

        if (($currentUsage + $tokenCost) > $dailyBudget) {
            $this->logRateLimitHit($endpoint, $tokenCost, $currentUsage, $dailyBudget);
            return false;
        }

        return true;
    }

    /**
     * Consume tokens for a request
     */
    public function consumeTokens(string $endpoint, int $tokenCost = null): void
    {
        if (!$this->config['enabled']) {
            return;
        }

        $tokenCost = $tokenCost ?? $this->getTokenCost($endpoint);
        $cacheKey = $this->getCacheKey('usage');
        
        $currentUsage = Cache::get($cacheKey, 0);
        $newUsage = $currentUsage + $tokenCost;
        
        // Cache until end of day
        $expiresAt = Carbon::tomorrow();
        Cache::put($cacheKey, $newUsage, $expiresAt);

        Log::debug('Pipedrive tokens consumed', [
            'endpoint' => $endpoint,
            'tokens_consumed' => $tokenCost,
            'total_usage' => $newUsage,
            'daily_budget' => $this->config['daily_budget'],
            'usage_percentage' => ($newUsage / $this->config['daily_budget']) * 100,
        ]);
    }

    /**
     * Wait for rate limit with exponential backoff
     */
    public function waitForRateLimit(int $retryAttempt = 1, ?int $retryAfter = null): void
    {
        $delay = $this->calculateBackoffDelay($retryAttempt, $retryAfter);
        
        Log::info('Waiting for Pipedrive rate limit', [
            'retry_attempt' => $retryAttempt,
            'delay_seconds' => $delay,
            'retry_after' => $retryAfter,
        ]);

        sleep($delay);
    }

    /**
     * Calculate exponential backoff delay with jitter
     */
    protected function calculateBackoffDelay(int $retryAttempt, ?int $retryAfter = null): int
    {
        // Use retry-after header if provided
        if ($retryAfter !== null && $retryAfter > 0) {
            return min($retryAfter, $this->config['max_delay']);
        }

        // Exponential backoff: 1s, 2s, 4s, 8s, 16s (max)
        $baseDelay = min(pow(2, $retryAttempt - 1), $this->config['max_delay']);

        // Add jitter to prevent thundering herd
        if ($this->config['jitter_enabled']) {
            $jitter = rand(0, (int) ($baseDelay * 0.1)); // 10% jitter
            $baseDelay += $jitter;
        }

        return $baseDelay;
    }

    /**
     * Get current token usage for today
     */
    public function getCurrentUsage(): int
    {
        if (!$this->config['enabled']) {
            return 0;
        }

        return Cache::get($this->getCacheKey('usage'), 0);
    }

    /**
     * Get remaining tokens for today
     */
    public function getRemainingTokens(): int
    {
        return max(0, $this->config['daily_budget'] - $this->getCurrentUsage());
    }

    /**
     * Get usage percentage
     */
    public function getUsagePercentage(): float
    {
        if ($this->config['daily_budget'] === 0) {
            return 0.0;
        }

        return ($this->getCurrentUsage() / $this->config['daily_budget']) * 100;
    }

    /**
     * Check if approaching rate limit (>80% usage)
     */
    public function isApproachingLimit(): bool
    {
        return $this->getUsagePercentage() > 80.0;
    }

    /**
     * Check if rate limit is exceeded
     */
    public function isLimitExceeded(): bool
    {
        return $this->getCurrentUsage() >= $this->config['daily_budget'];
    }

    /**
     * Get time until rate limit resets (next day)
     */
    public function getTimeUntilReset(): int
    {
        return Carbon::tomorrow()->diffInSeconds(Carbon::now());
    }

    /**
     * Get token cost for endpoint
     */
    protected function getTokenCost(string $endpoint): int
    {
        // Extract entity type from endpoint
        $entityType = $this->extractEntityType($endpoint);
        
        return $this->config['token_costs'][$entityType] ?? 1;
    }

    /**
     * Extract entity type from endpoint
     */
    protected function extractEntityType(string $endpoint): string
    {
        // Remove query parameters and leading/trailing slashes
        $path = strtok($endpoint, '?');
        $path = trim($path, '/');
        
        // Split by slash and get first segment
        $segments = explode('/', $path);
        $entityType = $segments[0] ?? 'unknown';
        
        // Map common variations
        $entityMap = [
            'activity' => 'activities',
            'deal' => 'deals',
            'file' => 'files',
            'goal' => 'goals',
            'note' => 'notes',
            'organization' => 'organizations',
            'person' => 'persons',
            'pipeline' => 'pipelines',
            'product' => 'products',
            'stage' => 'stages',
            'user' => 'users',
        ];

        return $entityMap[$entityType] ?? $entityType;
    }

    /**
     * Get cache key for rate limiting data
     */
    protected function getCacheKey(string $type): string
    {
        $date = Carbon::now()->format('Y-m-d');
        return "{$this->cachePrefix}:{$type}:{$date}";
    }

    /**
     * Log rate limit hit
     */
    protected function logRateLimitHit(string $endpoint, int $tokenCost, int $currentUsage, int $dailyBudget): void
    {
        Log::warning('Pipedrive rate limit would be exceeded', [
            'endpoint' => $endpoint,
            'token_cost' => $tokenCost,
            'current_usage' => $currentUsage,
            'daily_budget' => $dailyBudget,
            'would_exceed_by' => ($currentUsage + $tokenCost) - $dailyBudget,
            'usage_percentage' => ($currentUsage / $dailyBudget) * 100,
            'time_until_reset' => $this->getTimeUntilReset(),
        ]);
    }

    /**
     * Handle rate limit response from API
     */
    public function handleRateLimitResponse(array $headers, string $endpoint = ''): PipedriveRateLimitException
    {
        // Parse rate limit headers
        $tokensRemaining = (int) ($headers['x-ratelimit-remaining'] ?? $headers['X-RateLimit-Remaining'] ?? 0);
        $tokensUsed = (int) ($headers['x-ratelimit-used'] ?? $headers['X-RateLimit-Used'] ?? 0);
        $dailyLimit = (int) ($headers['x-ratelimit-limit'] ?? $headers['X-RateLimit-Limit'] ?? $this->config['daily_budget']);
        $resetTime = isset($headers['x-ratelimit-reset']) ? (int) $headers['x-ratelimit-reset'] : 
                    (isset($headers['X-RateLimit-Reset']) ? (int) $headers['X-RateLimit-Reset'] : null);
        $retryAfter = (int) ($headers['retry-after'] ?? $headers['Retry-After'] ?? 60);

        // Update local cache with server values
        if ($tokensUsed > 0) {
            $cacheKey = $this->getCacheKey('usage');
            $expiresAt = Carbon::tomorrow();
            Cache::put($cacheKey, $tokensUsed, $expiresAt);
        }

        // Create exception with detailed information
        return PipedriveRateLimitException::fromRateLimitHeaders(
            $headers,
            'GET',
            $endpoint
        );
    }

    /**
     * Reset rate limit counters (for testing)
     */
    public function reset(): void
    {
        Cache::forget($this->getCacheKey('usage'));
    }

    /**
     * Get rate limit status
     */
    public function getStatus(): array
    {
        return [
            'enabled' => $this->config['enabled'],
            'current_usage' => $this->getCurrentUsage(),
            'daily_budget' => $this->config['daily_budget'],
            'remaining_tokens' => $this->getRemainingTokens(),
            'usage_percentage' => $this->getUsagePercentage(),
            'is_approaching_limit' => $this->isApproachingLimit(),
            'is_limit_exceeded' => $this->isLimitExceeded(),
            'time_until_reset' => $this->getTimeUntilReset(),
            'reset_time' => Carbon::tomorrow()->toISOString(),
        ];
    }
}
