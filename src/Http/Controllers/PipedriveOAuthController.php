<?php

namespace Keggermont\LaravelPipedrive\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Keggermont\LaravelPipedrive\Services\PipedriveAuthService;

class PipedriveOAuthController
{
    protected PipedriveAuthService $authService;

    public function __construct(PipedriveAuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Show OAuth authorization page
     */
    public function authorize(Request $request): View|RedirectResponse
    {
        // Check if OAuth is configured
        if (!$this->isOAuthConfigured()) {
            return view('pipedrive::oauth.error', [
                'error' => 'OAuth not configured',
                'message' => 'Please configure PIPEDRIVE_CLIENT_ID, PIPEDRIVE_CLIENT_SECRET, and PIPEDRIVE_REDIRECT_URL in your environment variables.'
            ]);
        }

        // Check if already authenticated
        if ($this->isAlreadyAuthenticated()) {
            return view('pipedrive::oauth.success', [
                'message' => 'Already connected to Pipedrive',
                'action' => 'reconnect'
            ]);
        }

        try {
            $pipedrive = $this->authService->getPipedriveInstance();
            
            // Default scopes - can be customized
            $scopes = $request->get('scopes', 'deals:read deals:write persons:read persons:write organizations:read organizations:write activities:read activities:write');
            
            // Generate authorization URL
            $authUrl = $pipedrive->getAuthorizationUrl([
                'scope' => $scopes,
                'state' => csrf_token() // CSRF protection
            ]);

            // Store state for verification
            Session::put('pipedrive_oauth_state', csrf_token());

            return view('pipedrive::oauth.authorize', [
                'authUrl' => $authUrl,
                'scopes' => explode(' ', $scopes),
                'clientId' => config('pipedrive.oauth.client_id')
            ]);

        } catch (\Exception $e) {
            Log::error('Pipedrive OAuth authorization error: ' . $e->getMessage());
            
            return view('pipedrive::oauth.error', [
                'error' => 'Authorization Error',
                'message' => 'Failed to generate authorization URL: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Handle OAuth callback from Pipedrive
     */
    public function callback(Request $request): View|RedirectResponse
    {
        // Verify state parameter (CSRF protection)
        $state = $request->get('state');
        $sessionState = Session::get('pipedrive_oauth_state');
        
        if (!$state || $state !== $sessionState) {
            return view('pipedrive::oauth.error', [
                'error' => 'Invalid State',
                'message' => 'OAuth state verification failed. This may be a security issue.'
            ]);
        }

        // Clear the state from session
        Session::forget('pipedrive_oauth_state');

        // Check for authorization errors
        if ($request->has('error')) {
            $error = $request->get('error');
            $errorDescription = $request->get('error_description', 'Unknown error');
            
            Log::warning('Pipedrive OAuth authorization denied', [
                'error' => $error,
                'description' => $errorDescription
            ]);

            return view('pipedrive::oauth.error', [
                'error' => 'Authorization Denied',
                'message' => "Pipedrive authorization was denied: {$errorDescription}"
            ]);
        }

        // Get authorization code
        $code = $request->get('code');
        if (!$code) {
            return view('pipedrive::oauth.error', [
                'error' => 'Missing Authorization Code',
                'message' => 'No authorization code received from Pipedrive.'
            ]);
        }

        try {
            $pipedrive = $this->authService->getPipedriveInstance();
            
            // Exchange code for access token
            $token = $pipedrive->getAccessToken($code);
            
            if (!$token) {
                throw new \Exception('Failed to obtain access token');
            }

            // Token is automatically stored via DatabaseTokenStorage
            Log::info('Pipedrive OAuth token obtained successfully', [
                'expires_at' => $token->expiresAt()
            ]);

            // Test the connection to ensure everything works
            $connectionTest = $this->authService->testConnection();
            
            if (!$connectionTest['success']) {
                throw new \Exception('Connection test failed: ' . $connectionTest['message']);
            }

            return view('pipedrive::oauth.success', [
                'message' => 'Successfully connected to Pipedrive!',
                'user' => $connectionTest['user'] ?? 'Unknown',
                'company' => $connectionTest['company'] ?? 'Unknown',
                'action' => 'connected'
            ]);

        } catch (\Exception $e) {
            Log::error('Pipedrive OAuth callback error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return view('pipedrive::oauth.error', [
                'error' => 'Token Exchange Failed',
                'message' => 'Failed to exchange authorization code for access token: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Disconnect/revoke OAuth token
     */
    public function disconnect(): View
    {
        try {
            // Clear the stored token
            $tokenStorage = app(\Keggermont\LaravelPipedrive\Contracts\PipedriveTokenStorageInterface::class);

            if (method_exists($tokenStorage, 'clearToken')) {
                $tokenStorage->clearToken();
            } else {
                // Fallback to cache clearing
                \Illuminate\Support\Facades\Cache::forget('pipedrive_oauth_token');
            }

            Log::info('Pipedrive OAuth token disconnected');

            return view('pipedrive::oauth.success', [
                'message' => 'Successfully disconnected from Pipedrive',
                'action' => 'disconnected'
            ]);

        } catch (\Exception $e) {
            Log::error('Pipedrive OAuth disconnect error: ' . $e->getMessage());

            return view('pipedrive::oauth.error', [
                'error' => 'Disconnect Failed',
                'message' => 'Failed to disconnect from Pipedrive: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Show OAuth status
     */
    public function status(): View
    {
        $isConfigured = $this->isOAuthConfigured();
        $isAuthenticated = $this->isAlreadyAuthenticated();
        $connectionTest = null;

        if ($isAuthenticated) {
            $connectionTest = $this->authService->testConnection();
        }

        return view('pipedrive::oauth.status', [
            'isConfigured' => $isConfigured,
            'isAuthenticated' => $isAuthenticated,
            'connectionTest' => $connectionTest,
            'authMethod' => $this->authService->getAuthMethod()
        ]);
    }

    /**
     * Check if OAuth is properly configured
     */
    protected function isOAuthConfigured(): bool
    {
        return !empty(config('pipedrive.oauth.client_id')) &&
               !empty(config('pipedrive.oauth.client_secret')) &&
               !empty(config('pipedrive.oauth.redirect_url'));
    }

    /**
     * Check if already authenticated
     */
    protected function isAlreadyAuthenticated(): bool
    {
        try {
            $tokenStorage = app(\Keggermont\LaravelPipedrive\Contracts\PipedriveTokenStorageInterface::class);
            $token = $tokenStorage->getToken();
            
            return $token !== null && !$token->isExpired();
        } catch (\Exception $e) {
            return false;
        }
    }
}
