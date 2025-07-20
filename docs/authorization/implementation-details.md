# Dashboard Authorization - Implementation Details

This document provides technical details about how the dashboard authorization system works internally.

## Architecture Overview

The authorization system consists of several components working together:

1. **AuthorizePipedrive Middleware** - Handles route protection
2. **Gate Definition** - Defines authorization logic
3. **Service Provider Integration** - Registers the gate and applies middleware
4. **Configuration System** - Provides flexible authorization options

## Components

### 1. AuthorizePipedrive Middleware

**Location**: `src/Http/Middleware/AuthorizePipedrive.php`

This middleware is applied to protected routes and:
- Allows all access in local environment
- Checks the `viewPipedrive` gate for authenticated users
- Returns 403 Forbidden for unauthorized access

```php
public function handle(Request $request, Closure $next)
{
    // Allow access in local environment by default
    if (app()->environment('local')) {
        return $next($request);
    }

    // Check if the user is authorized to access Pipedrive routes
    if (Gate::allows('viewPipedrive', $request->user())) {
        return $next($request);
    }

    // Return 403 Forbidden if not authorized
    return response()->json([
        'error' => 'Forbidden',
        'message' => 'You are not authorized to access Pipedrive management interface.'
    ], Response::HTTP_FORBIDDEN);
}
```

### 2. Gate Registration

**Location**: `src/LaravelPipedriveServiceProvider.php`

The service provider registers a default `viewPipedrive` gate that:
- Always allows access in local environment
- Checks configuration-based authorization
- Supports custom callbacks
- Falls back to denying access

```php
protected function registerPipedriveGate(): void
{
    Gate::define('viewPipedrive', function ($user = null) {
        // Allow access in local environment
        if (app()->environment('local')) {
            return true;
        }

        // If no user is authenticated, deny access
        if (!$user) {
            return false;
        }

        // Check configuration-based authorization...
    });
}
```

### 3. Route Protection

**Protected Routes**:
- `GET /pipedrive/oauth/authorize` - OAuth authorization page
- `GET /pipedrive/oauth/status` - Connection status page
- `GET /pipedrive/oauth/disconnect` - Disconnection page
- `GET /pipedrive/webhook/health` - Webhook health check

**Unprotected Routes**:
- `GET /pipedrive/oauth/callback` - OAuth callback (must be accessible to Pipedrive)
- `POST /pipedrive/webhook` - Webhook endpoint (has its own security)

### 4. Configuration Structure

```php
'dashboard' => [
    // Simple email-based authorization
    'authorized_emails' => [
        'admin@example.com',
    ],

    // Simple ID-based authorization
    'authorized_user_ids' => [
        1, 2, 3,
    ],

    // Custom callback for complex logic
    'authorization_callback' => function ($user) {
        return $user->hasRole('admin');
    },
],
```

## Authorization Flow

1. **Request arrives** at protected route
2. **Middleware checks** environment:
   - If `local`: Allow access
   - If not `local`: Continue to gate check
3. **Gate evaluation**:
   - Check if custom gate is defined (overrides default)
   - If default gate: Check configuration options
   - Return boolean result
4. **Response**:
   - If authorized: Continue to controller
   - If not authorized: Return 403 Forbidden

## Customization Points

### 1. Custom Service Provider

Users can create their own service provider to override the gate:

```php
// app/Providers/PipedriveServiceProvider.php
Gate::define('viewPipedrive', function ($user) {
    // Custom logic here
    return $user->hasPermission('manage-pipedrive');
});
```

### 2. Configuration-Based

Simple authorization via configuration:

```php
// config/pipedrive.php
'dashboard' => [
    'authorized_emails' => ['admin@example.com'],
    'authorized_user_ids' => [1, 2],
    'authorization_callback' => fn($user) => $user->isAdmin(),
],
```

### 3. Environment Variables

For simple cases, you could extend the system to support:

```env
PIPEDRIVE_AUTHORIZED_EMAILS=admin@example.com,dev@example.com
PIPEDRIVE_AUTHORIZED_USER_IDS=1,2,3
```

## Security Considerations

### 1. Local Environment Bypass

The system automatically allows access in local environment. This is intentional for development convenience but means:
- Ensure `APP_ENV` is correctly set in production
- Never deploy with `APP_ENV=local` in production

### 2. Authentication Requirement

The middleware requires user authentication. Unauthenticated requests are denied access.

### 3. Gate Override

Custom service providers can completely override the authorization logic. This provides flexibility but requires careful implementation.

### 4. Route Selection

Only management routes are protected. Webhook endpoints have their own security mechanisms and should not be protected by this system.

## Testing

The system includes comprehensive tests covering:
- Local environment bypass
- Configuration-based authorization
- Custom gate definitions
- Unauthenticated access denial
- Route-specific protection

Run tests with:
```bash
./vendor/bin/pest tests/Feature/DashboardAuthorizationTest.php
```

## Comparison with Laravel Telescope

| Feature | Telescope | Laravel Pipedrive |
|---------|-----------|-------------------|
| Local bypass | ✅ | ✅ |
| Custom gate | ✅ | ✅ |
| Config-based auth | ❌ | ✅ |
| Service provider | ✅ | ✅ |
| Middleware protection | ✅ | ✅ |
| Installation command | ❌ | ✅ |

## Future Enhancements

Potential improvements could include:
- Role-based authorization helpers
- IP-based restrictions
- Time-based access controls
- Audit logging for access attempts
- Integration with Laravel Sanctum/Passport
