<?php

namespace Keggermont\LaravelPipedrive\Services;

use Devio\Pipedrive\Pipedrive;
use Devio\Pipedrive\PipedriveTokenStorage;
use Keggermont\LaravelPipedrive\Contracts\PipedriveTokenStorageInterface;
use InvalidArgumentException;

class PipedriveAuthService
{
    protected ?Pipedrive $pipedrive = null;

    /**
     * Get configured Pipedrive instance
     */
    public function getPipedriveInstance(): Pipedrive
    {
        if ($this->pipedrive !== null) {
            return $this->pipedrive;
        }

        $authMethod = config('pipedrive.auth_method', 'token');

        $this->pipedrive = match ($authMethod) {
            'token' => $this->createTokenInstance(),
            'oauth' => $this->createOAuthInstance(),
            default => throw new InvalidArgumentException("Unsupported auth method: {$authMethod}"),
        };

        return $this->pipedrive;
    }

    /**
     * Create Pipedrive instance with API token
     */
    protected function createTokenInstance(): Pipedrive
    {
        $token = config('pipedrive.token');

        if (empty($token)) {
            throw new InvalidArgumentException(
                'Pipedrive API token not found. Please set PIPEDRIVE_TOKEN in your .env file or configure it in config/pipedrive.php'
            );
        }

        return new Pipedrive($token);
    }

    /**
     * Create Pipedrive instance with OAuth
     */
    protected function createOAuthInstance(): Pipedrive
    {
        $clientId = config('pipedrive.oauth.client_id');
        $clientSecret = config('pipedrive.oauth.client_secret');
        $redirectUrl = config('pipedrive.oauth.redirect_url');

        if (empty($clientId) || empty($clientSecret) || empty($redirectUrl)) {
            throw new InvalidArgumentException(
                'OAuth configuration incomplete. Please set PIPEDRIVE_CLIENT_ID, PIPEDRIVE_CLIENT_SECRET, and PIPEDRIVE_REDIRECT_URL in your .env file'
            );
        }

        // You'll need to implement a token storage class
        $tokenStorage = app(PipedriveTokenStorageInterface::class);

        return Pipedrive::OAuth([
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'redirectUrl' => $redirectUrl,
            'storage' => $tokenStorage,
        ]);
    }

    /**
     * Test the connection to Pipedrive
     */
    public function testConnection(): array
    {
        try {
            $pipedrive = $this->getPipedriveInstance();

            // Try the simplest possible endpoint - currencies (always available and simple)
            $response = $pipedrive->currencies->all();

            if ($response && $response->isSuccess()) {
                return [
                    'success' => true,
                    'message' => 'Connection successful',
                    'user' => 'API User',
                    'company' => 'Pipedrive Account',
                ];
            }

            return [
                'success' => false,
                'message' => 'Connection failed: ' . ($response ? $response->getStatusCode() : 'No response'),
                'error' => $response ? $response->getContent() : 'No response received',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage(),
                'error' => $e->getTraceAsString(),
            ];
        }
    }

    /**
     * Get authentication method being used
     */
    public function getAuthMethod(): string
    {
        return config('pipedrive.auth_method', 'token');
    }

    /**
     * Check if using token authentication
     */
    public function isUsingToken(): bool
    {
        return $this->getAuthMethod() === 'token';
    }

    /**
     * Check if using OAuth authentication
     */
    public function isUsingOAuth(): bool
    {
        return $this->getAuthMethod() === 'oauth';
    }
}
