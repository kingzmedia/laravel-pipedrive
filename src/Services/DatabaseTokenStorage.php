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
        ];

        // Store in cache for quick access
        Cache::put($this->cacheKey, $tokenData, now()->addHours(24));

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
}
