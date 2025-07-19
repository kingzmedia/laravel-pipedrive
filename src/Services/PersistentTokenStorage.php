<?php

namespace Skeylup\LaravelPipedrive\Services;

use Devio\Pipedrive\PipedriveToken;
use Skeylup\LaravelPipedrive\Contracts\PipedriveTokenStorageInterface;
use Skeylup\LaravelPipedrive\Models\PipedriveOAuthToken;
use Carbon\Carbon;

class PersistentTokenStorage implements PipedriveTokenStorageInterface
{
    /**
     * Store the Pipedrive token in database
     */
    public function setToken(PipedriveToken $token): void
    {
        $expiresAt = $token->expiresAt();
        
        PipedriveOAuthToken::updateDefault([
            'access_token' => $token->getAccessToken(),
            'refresh_token' => $token->getRefreshToken(),
            'expires_at' => $expiresAt ? Carbon::createFromTimestamp($expiresAt) : null,
        ]);
    }

    /**
     * Retrieve the stored Pipedrive token
     */
    public function getToken(): ?PipedriveToken
    {
        $tokenModel = PipedriveOAuthToken::getDefault();

        if (!$tokenModel) {
            return null;
        }

        return new PipedriveToken([
            'accessToken' => $tokenModel->access_token,
            'refreshToken' => $tokenModel->refresh_token,
            'expiresAt' => $tokenModel->expires_at ? $tokenModel->expires_at->timestamp : null,
        ]);
    }

    /**
     * Clear the stored token
     */
    public function clearToken(): void
    {
        PipedriveOAuthToken::clearDefault();
    }

    /**
     * Check if a token is stored
     */
    public function hasToken(): bool
    {
        return PipedriveOAuthToken::where('identifier', 'default')->exists();
    }

    /**
     * Get token metadata without creating PipedriveToken object
     */
    public function getTokenData(): ?array
    {
        $tokenModel = PipedriveOAuthToken::getDefault();

        if (!$tokenModel) {
            return null;
        }

        return [
            'access_token' => $tokenModel->access_token,
            'refresh_token' => $tokenModel->refresh_token,
            'expires_at' => $tokenModel->expires_at ? $tokenModel->expires_at->timestamp : null,
            'created_at' => $tokenModel->created_at->toISOString(),
            'updated_at' => $tokenModel->updated_at->toISOString(),
        ];
    }

    /**
     * Get the token model directly
     */
    public function getTokenModel(): ?PipedriveOAuthToken
    {
        return PipedriveOAuthToken::getDefault();
    }
}
