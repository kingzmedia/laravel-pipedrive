# Dashboard Authorization

The Laravel Pipedrive package includes a dashboard authorization system similar to Laravel Telescope, which controls access to sensitive management routes in non-local environments.

## Protected Routes

The following routes are protected by the authorization system:

- `pipedrive/oauth/authorize` - OAuth authorization page
- `pipedrive/oauth/status` - OAuth connection status
- `pipedrive/oauth/disconnect` - OAuth disconnection
- `pipedrive/webhook/health` - Webhook health check endpoint (special handling)

**Note:**
- The OAuth callback route (`pipedrive/oauth/callback`) is not protected as it needs to be accessible to Pipedrive's servers.
- The webhook health endpoint uses special authorization that allows access for both authorized users AND Pipedrive servers (via webhook security settings).

## Default Behavior

- **Local Environment**: All routes are accessible without authentication
- **Non-Local Environments**: Routes require authentication and authorization via the `viewPipedrive` gate

## Quick Installation

Use the installation command to set up everything automatically:

```bash
# Install with automatic vendor:publish
php artisan pipedrive:install

# Force overwrite existing files
php artisan pipedrive:install --force
```

This command will:
1. Publish configuration files
2. Publish migrations
3. Publish views
4. Create the PipedriveServiceProvider
5. Show you the next steps

## Configuration Methods

### Method 1: Configuration File (Simple)

Add authorized users to your `config/pipedrive.php` file:

```php
'dashboard' => [
    // List of authorized email addresses
    'authorized_emails' => [
        'admin@example.com',
        'developer@example.com',
    ],

    // List of authorized user IDs
    'authorized_user_ids' => [
        1, // Admin user ID
        2, // Developer user ID
    ],
],
```

### Method 2: Custom Service Provider (Recommended)

For more complex authorization logic, install and customize the Pipedrive service provider:

```bash
php artisan pipedrive:install
```

This creates `app/Providers/PipedriveServiceProvider.php` where you can define custom authorization logic:

```php
<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class PipedriveServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerPipedriveGate();
    }

    protected function registerPipedriveGate(): void
    {
        Gate::define('viewPipedrive', function ($user) {
            // Example: Check user role
            return $user->hasRole('admin') || $user->hasRole('developer');
            
            // Example: Check specific emails
            return in_array($user->email, [
                'admin@example.com',
                'developer@example.com',
            ]);
            
            // Example: Check user permissions
            return $user->can('manage-pipedrive');
        });
    }
}
```

**Important:** Don't forget to register this service provider in your `config/app.php`:

```php
'providers' => [
    // Other providers...
    App\Providers\PipedriveServiceProvider::class,
],
```

### Method 3: Custom Callback

You can also define a custom callback in your configuration:

```php
'dashboard' => [
    'authorization_callback' => function ($user) {
        return $user->hasRole('admin') || $user->can('manage-pipedrive');
    },
],
```

## Authorization Priority

The authorization system checks permissions in this order:

1. **Local Environment**: Always allows access
2. **Custom Service Provider Gate**: If `viewPipedrive` gate is defined in a custom service provider
3. **Configuration Emails**: Check `authorized_emails` array
4. **Configuration User IDs**: Check `authorized_user_ids` array
5. **Custom Callback**: Execute `authorization_callback` if defined
6. **Default**: Deny access in non-local environments

## Special Case: Webhook Health Endpoint

The `/pipedrive/webhook/health` endpoint has special authorization logic because it needs to be accessible by both:

1. **Authorized users** (for monitoring and debugging)
2. **Pipedrive servers** (for webhook URL validation)

### How it works:

The endpoint allows access if **either** condition is met:
- User is authenticated and passes the `viewPipedrive` gate, **OR**
- Request passes webhook security verification (Basic Auth, IP whitelist, or signature)

### Configuration:

```php
// config/pipedrive.php
'webhooks' => [
    'security' => [
        // Enable Basic Auth for Pipedrive servers
        'basic_auth' => [
            'enabled' => true,
            'username' => env('PIPEDRIVE_WEBHOOK_USERNAME'),
            'password' => env('PIPEDRIVE_WEBHOOK_PASSWORD'),
        ],

        // Or enable IP whitelist
        'ip_whitelist' => [
            'enabled' => true,
            'ips' => ['185.166.142.0/24'], // Pipedrive IPs
        ],
    ],
],
```

## Security Considerations

- The authorization system only applies to management routes, not main webhook endpoints
- Webhook endpoints have their own security mechanisms (Basic Auth, IP whitelist, signatures)
- The health endpoint uses dual authorization (user OR webhook security)
- Always test your authorization logic in a staging environment before deploying to production
- Consider using role-based permissions for better maintainability

## Troubleshooting

### 403 Forbidden Error

If you receive a 403 Forbidden error when accessing Pipedrive routes:

1. Check that you're authenticated in your application
2. Verify your email/user ID is in the authorized lists
3. Ensure your custom gate logic is correct
4. Check that the `PipedriveServiceProvider` is registered in `config/app.php`

### Local Environment Issues

If routes are not accessible in local environment:

1. Verify `APP_ENV=local` in your `.env` file
2. Clear configuration cache: `php artisan config:clear`

### Custom Gate Not Working

1. Ensure the service provider is registered in `config/app.php`
2. Clear cache: `php artisan cache:clear`
3. Check that the gate name is exactly `viewPipedrive`
