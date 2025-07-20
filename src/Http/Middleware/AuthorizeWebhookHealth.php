<?php

namespace Skeylup\LaravelPipedrive\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class AuthorizeWebhookHealth
{
    /**
     * Handle an incoming request for webhook health endpoint.
     * 
     * This middleware allows access if either:
     * 1. User is authenticated and authorized via dashboard gate, OR
     * 2. Request passes webhook verification (for Pipedrive servers)
     */
    public function handle(Request $request, Closure $next)
    {
        // Allow access in local environment by default
        if (app()->environment('local')) {
            return $next($request);
        }

        // Option 1: User is authenticated and authorized via dashboard
        if ($request->user() && Gate::allows('viewPipedrive', $request->user())) {
            return $next($request);
        }

        // Option 2: Request might be from Pipedrive servers
        // Check webhook verification criteria
        if ($this->isValidWebhookRequest($request)) {
            return $next($request);
        }

        // If neither condition is met, deny access
        return response()->json([
            'error' => 'Forbidden',
            'message' => 'You are not authorized to access this endpoint.'
        ], Response::HTTP_FORBIDDEN);
    }

    /**
     * Check if request is valid webhook request from Pipedrive
     */
    protected function isValidWebhookRequest(Request $request): bool
    {
        // Skip verification in testing environment
        if (app()->environment('testing')) {
            return true;
        }

        // Check HTTP Basic Auth if configured
        if ($this->shouldVerifyBasicAuth()) {
            if (!$this->verifyBasicAuth($request)) {
                return false;
            }
        }

        // Check IP whitelist if configured
        if ($this->shouldVerifyIpWhitelist()) {
            if (!$this->verifyIpWhitelist($request)) {
                return false;
            }
        }

        // Check signature if configured
        if ($this->shouldVerifySignature()) {
            if (!$this->verifySignature($request)) {
                return false;
            }
        }

        // If no webhook security is configured, allow access
        // This maintains backward compatibility
        if (!$this->shouldVerifyBasicAuth() && 
            !$this->shouldVerifyIpWhitelist() && 
            !$this->shouldVerifySignature()) {
            return true;
        }

        return true;
    }

    /**
     * Check if Basic Auth verification is enabled
     */
    protected function shouldVerifyBasicAuth(): bool
    {
        return config('pipedrive.webhooks.security.basic_auth.enabled', false);
    }

    /**
     * Verify HTTP Basic Auth credentials
     */
    protected function verifyBasicAuth(Request $request): bool
    {
        $username = config('pipedrive.webhooks.security.basic_auth.username');
        $password = config('pipedrive.webhooks.security.basic_auth.password');

        if (empty($username) || empty($password)) {
            return false;
        }

        return $request->getUser() === $username && $request->getPassword() === $password;
    }

    /**
     * Check if IP whitelist verification is enabled
     */
    protected function shouldVerifyIpWhitelist(): bool
    {
        return config('pipedrive.webhooks.security.ip_whitelist.enabled', false);
    }

    /**
     * Verify IP whitelist
     */
    protected function verifyIpWhitelist(Request $request): bool
    {
        $allowedIps = config('pipedrive.webhooks.security.ip_whitelist.ips', []);
        
        if (empty($allowedIps)) {
            return false;
        }

        $clientIp = $request->ip();
        
        foreach ($allowedIps as $allowedIp) {
            if ($this->ipMatches($clientIp, $allowedIp)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if signature verification is enabled
     */
    protected function shouldVerifySignature(): bool
    {
        return config('pipedrive.webhooks.security.signature.enabled', false);
    }

    /**
     * Verify webhook signature
     */
    protected function verifySignature(Request $request): bool
    {
        $secret = config('pipedrive.webhooks.security.signature.secret');
        $header = config('pipedrive.webhooks.security.signature.header', 'X-Pipedrive-Signature');
        
        if (empty($secret)) {
            return false;
        }

        $signature = $request->header($header);
        if (!$signature) {
            return false;
        }

        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Check if IP matches allowed pattern
     */
    protected function ipMatches(string $ip, string $pattern): bool
    {
        // Simple IP matching - could be enhanced with CIDR support
        if ($ip === $pattern) {
            return true;
        }

        // Basic CIDR support
        if (strpos($pattern, '/') !== false) {
            return $this->ipInCidr($ip, $pattern);
        }

        return false;
    }

    /**
     * Check if IP is in CIDR range
     */
    protected function ipInCidr(string $ip, string $cidr): bool
    {
        list($subnet, $mask) = explode('/', $cidr);
        
        if ((ip2long($ip) & ~((1 << (32 - $mask)) - 1)) == ip2long($subnet)) {
            return true;
        }

        return false;
    }
}
