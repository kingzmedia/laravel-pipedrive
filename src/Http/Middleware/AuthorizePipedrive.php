<?php

namespace Skeylup\LaravelPipedrive\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class AuthorizePipedrive
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Allow access in local environment by default
        if (app()->environment('local')) {
            return $next($request);
        }

        // For webhook health endpoint, allow access if either:
        // 1. User is authorized via dashboard gate, OR
        // 2. Request passes webhook verification (for Pipedrive servers)
        if ($this->isWebhookHealthEndpoint($request)) {
            return $this->handleWebhookHealthAuthorization($request, $next);
        }

        // For other routes, check dashboard authorization
        if (Gate::allows('viewPipedrive', $request->user())) {
            return $next($request);
        }

        // Return 403 Forbidden if not authorized
        return response()->json([
            'error' => 'Forbidden',
            'message' => 'You are not authorized to access Pipedrive management interface.',
        ], Response::HTTP_FORBIDDEN);
    }

    /**
     * Check if this is the webhook health endpoint
     */
    protected function isWebhookHealthEndpoint(Request $request): bool
    {
        $path = $request->path();
        $webhookPath = config('pipedrive.webhooks.route.path', 'pipedrive/webhook');

        return $path === $webhookPath.'/health';
    }

    /**
     * Handle authorization for webhook health endpoint
     */
    protected function handleWebhookHealthAuthorization(Request $request, Closure $next)
    {
        // Option 1: User is authenticated and authorized via dashboard
        if ($request->user() && Gate::allows('viewPipedrive', $request->user())) {
            return $next($request);
        }

        // Option 2: Request is from Pipedrive servers (webhook verification will handle this)
        // We let the request continue and let VerifyPipedriveWebhook middleware handle it
        // This allows Pipedrive servers to access the health endpoint
        return $next($request);
    }
}
