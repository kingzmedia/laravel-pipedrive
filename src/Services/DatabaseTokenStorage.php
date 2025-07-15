<?php

namespace Keggermont\LaravelPipedrive\Services;

use Devio\Pipedrive\PipedriveToken;
use Illuminate\Support\Facades\Cache;
use Keggermont\LaravelPipedrive\Contracts\PipedriveTokenStorageInterface;

class DatabaseTokenStorage implements PipedriveTokenStorageInterface
{
    protected string $cacheKey = 'pipedrive_oauth_token';

    /**
     * Store the Pipedrive token
     */
    public function setToken(PipedriveToken $token): void
    {
        $tokenData = [
            'access_token' => $token->getAccessToken(),
            'refresh_token' => $token->getRefreshToken(),
            'expires_at' => $token->expiresAt(),
            'created_at' => now()->toISOString(),
        ];

        // Store in cache with long TTL for non-expiring tokens
        // If token doesn't expire, store for 1 year, otherwise use token expiry
        $expiresAt = $token->expiresAt();
        $ttl = $expiresAt ?
            now()->diffInSeconds(\Carbon\Carbon::createFromTimestamp($expiresAt)) :
            now()->addYear();

        Cache::put($this->cacheKey, $tokenData, $ttl);

        // You can also store in database if needed
        // This is a simple implementation using cache
        // For production, consider storing in a dedicated table
    }

    /**
     * Retrieve the stored Pipedrive token
     */
    public function getToken(): ?PipedriveToken
    {
        $tokenData = Cache::get($this->cacheKey);

        if (!$tokenData) {
            return null;
        }

        return new PipedriveToken([
            'accessToken' => $tokenData['access_token'],
            'refreshToken' => $tokenData['refresh_token'],
            'expiresAt' => $tokenData['expires_at'],
        ]);
    }

    /**
     * Clear the stored token
     */
    public function clearToken(): void
    {
        Cache::forget($this->cacheKey);
    }

    /**
     * Check if a token is stored
     */
    public function hasToken(): bool
    {
        return Cache::has($this->cacheKey);
    }

    /**
     * Get token metadata without creating PipedriveToken object
     */
    public function getTokenData(): ?array
    {
        return Cache::get($this->cacheKey);
    }
}
