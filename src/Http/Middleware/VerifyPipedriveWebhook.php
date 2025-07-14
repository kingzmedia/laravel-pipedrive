<?php

namespace Keggermont\LaravelPipedrive\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class VerifyPipedriveWebhook
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip verification in testing environment
        if (app()->environment('testing')) {
            return $next($request);
        }

        // Verify HTTP Basic Auth if configured
        if ($this->shouldVerifyBasicAuth()) {
            if (!$this->verifyBasicAuth($request)) {
                Log::warning('Pipedrive webhook authentication failed', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'auth_header' => $request->header('Authorization') ? 'present' : 'missing',
                ]);

                return response()->json([
                    'error' => 'Unauthorized'
                ], Response::HTTP_UNAUTHORIZED);
            }
        }

        // Verify IP whitelist if configured
        if ($this->shouldVerifyIpWhitelist()) {
            if (!$this->verifyIpWhitelist($request)) {
                Log::warning('Pipedrive webhook IP not whitelisted', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                return response()->json([
                    'error' => 'Forbidden'
                ], Response::HTTP_FORBIDDEN);
            }
        }

        // Verify webhook signature if configured
        if ($this->shouldVerifySignature()) {
            if (!$this->verifySignature($request)) {
                Log::warning('Pipedrive webhook signature verification failed', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                return response()->json([
                    'error' => 'Invalid signature'
                ], Response::HTTP_UNAUTHORIZED);
            }
        }

        // Verify request format
        if (!$this->verifyRequestFormat($request)) {
            Log::warning('Pipedrive webhook invalid format', [
                'ip' => $request->ip(),
                'content_type' => $request->header('Content-Type'),
                'has_event' => $request->has('event'),
                'has_meta' => $request->has('meta'),
            ]);

            return response()->json([
                'error' => 'Invalid webhook format'
            ], Response::HTTP_BAD_REQUEST);
        }

        return $next($request);
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
     * Verify request IP against whitelist
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
     * Check if IP matches pattern (supports CIDR notation)
     */
    protected function ipMatches(string $ip, string $pattern): bool
    {
        // Exact match
        if ($ip === $pattern) {
            return true;
        }

        // CIDR notation
        if (strpos($pattern, '/') !== false) {
            list($subnet, $mask) = explode('/', $pattern);
            return (ip2long($ip) & ~((1 << (32 - $mask)) - 1)) === ip2long($subnet);
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
     * Verify webhook signature (custom implementation)
     */
    protected function verifySignature(Request $request): bool
    {
        $secret = config('pipedrive.webhooks.security.signature.secret');
        
        if (empty($secret)) {
            return false;
        }

        $signature = $request->header('X-Pipedrive-Signature');
        
        if (empty($signature)) {
            return false;
        }

        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($signature, $expectedSignature);
    }

    /**
     * Verify basic webhook request format
     */
    protected function verifyRequestFormat(Request $request): bool
    {
        // Must be POST request
        if (!$request->isMethod('POST')) {
            return false;
        }

        // Must have JSON content type
        if (!$request->isJson()) {
            return false;
        }

        // Must have meta field (required for both v1 and v2)
        if (!$request->has('meta')) {
            return false;
        }

        $meta = $request->input('meta', []);

        // Check webhook version and validate accordingly
        $version = $meta['version'] ?? '1.0';

        if ($version === '2.0') {
            // Webhooks v2.0 format validation
            if (!isset($meta['action'], $meta['entity'], $meta['entity_id'])) {
                return false;
            }
        } else {
            // Webhooks v1.0 format validation (legacy)
            if (!$request->has('event')) {
                return false;
            }

            if (!isset($meta['action'], $meta['object'], $meta['id'])) {
                return false;
            }
        }

        return true;
    }
}
