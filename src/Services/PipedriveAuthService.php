<?php

namespace Skeylup\LaravelPipedrive\Services;

use Devio\Pipedrive\Pipedrive;
use Devio\Pipedrive\PipedriveTokenStorage;
use Skeylup\LaravelPipedrive\Contracts\PipedriveTokenStorageInterface;
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

            // For OAuth, ensure token is valid and refreshed if needed
            if ($this->isUsingOAuth()) {
                if (!$this->ensureValidToken($pipedrive)) {
                    return [
                        'success' => false,
                        'message' => 'No valid OAuth token found. Please re-authenticate.',
                        'error' => 'Token missing or refresh failed',
                    ];
                }
            }

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

    /**
     * Get OAuth authorization URL without redirecting
     */
    public function getAuthorizationUrl(array $options = []): string
    {
        if (!$this->isUsingOAuth()) {
            throw new InvalidArgumentException('OAuth is not configured. Please set auth_method to "oauth" in your configuration.');
        }

        $clientId = config('pipedrive.oauth.client_id');
        $redirectUrl = config('pipedrive.oauth.redirect_url');

        if (empty($clientId) || empty($redirectUrl)) {
            throw new InvalidArgumentException(
                'OAuth configuration incomplete. Please set PIPEDRIVE_CLIENT_ID and PIPEDRIVE_REDIRECT_URL in your .env file'
            );
        }

        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUrl,
            'response_type' => 'code',
        ];

        // Add optional parameters
        if (isset($options['scope'])) {
            $params['scope'] = $options['scope'];
        }

        // Only add state if explicitly provided and not empty
        if (isset($options['state']) && !empty($options['state'])) {
            $params['state'] = $options['state'];
        }

        $query = http_build_query($params);
        return 'https://oauth.pipedrive.com/oauth/authorize?' . $query;
    }

    /**
     * Exchange authorization code for access token
     */
    public function exchangeCodeForToken(string $code): bool
    {
        if (!$this->isUsingOAuth()) {
            throw new InvalidArgumentException('OAuth is not configured. Please set auth_method to "oauth" in your configuration.');
        }

        try {
            $pipedrive = $this->getPipedriveInstance();

            // Use the authorize method from devio/pipedrive package
            // This method will exchange the code for a token and store it
            $pipedrive->authorize($code);

            return true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Pipedrive OAuth error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw new InvalidArgumentException('Failed to exchange authorization code for token: ' . $e->getMessage());
        }
    }

    /**
     * Ensure the OAuth token is valid and refresh if needed
     * Returns true if token is valid, false if no token or refresh failed
     */
    protected function ensureValidToken(Pipedrive $pipedrive): bool
    {
        if (!$this->isUsingOAuth()) {
            return true;
        }

        $tokenStorage = app(PipedriveTokenStorageInterface::class);
        $token = $tokenStorage->getToken();

        if (!$token) {
            return false;
        }

        // Use the built-in refresh mechanism from devio/pipedrive
        if ($token->needsRefresh()) {
            try {
                \Illuminate\Support\Facades\Log::info('Pipedrive token needs refresh, refreshing...');
                $token->refreshIfNeeded($pipedrive);
                \Illuminate\Support\Facades\Log::info('Pipedrive token refreshed successfully');
                return true;
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to refresh Pipedrive token: ' . $e->getMessage());
                return false;
            }
        }

        return true;
    }

    /**
     * Get token status information
     */
    public function getTokenStatus(): array
    {
        if (!$this->isUsingOAuth()) {
            return [
                'auth_method' => 'token',
                'status' => 'N/A for token auth'
            ];
        }

        try {
            $tokenStorage = app(PipedriveTokenStorageInterface::class);
            $token = $tokenStorage->getToken();

            if (!$token) {
                return [
                    'auth_method' => 'oauth',
                    'status' => 'no_token',
                    'message' => 'No token found'
                ];
            }

            return [
                'auth_method' => 'oauth',
                'status' => $token->needsRefresh() ? 'expired' : 'valid',
                'expires_at' => $token->expiresAt(),
                'expires_at_human' => date('Y-m-d H:i:s', $token->expiresAt()),
                'needs_refresh' => $token->needsRefresh(),
                'valid' => $token->valid(),
            ];

        } catch (\Exception $e) {
            return [
                'auth_method' => 'oauth',
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Clear the stored OAuth token
     */
    public function clearToken(): void
    {
        if (!$this->isUsingOAuth()) {
            return;
        }

        $tokenStorage = app(PipedriveTokenStorageInterface::class);
        if (method_exists($tokenStorage, 'clearToken')) {
            $tokenStorage->clearToken();
        }
    }
}
