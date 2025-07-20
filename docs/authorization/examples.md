# Dashboard Authorization Examples

This document provides practical examples of how to configure dashboard authorization for different scenarios.

## Example 1: Simple Email-Based Authorization

**Scenario**: Allow access to specific email addresses only.

```php
// config/pipedrive.php
'dashboard' => [
    'authorized_emails' => [
        'admin@company.com',
        'developer@company.com',
        'manager@company.com',
    ],
],
```

## Example 2: Role-Based Authorization

**Scenario**: Allow access based on user roles.

```php
// app/Providers/PipedriveServiceProvider.php
protected function registerPipedriveGate(): void
{
    Gate::define('viewPipedrive', function ($user) {
        return $user->hasRole(['admin', 'developer', 'manager']);
    });
}
```

## Example 3: Permission-Based Authorization

**Scenario**: Allow access based on specific permissions.

```php
// app/Providers/PipedriveServiceProvider.php
protected function registerPipedriveGate(): void
{
    Gate::define('viewPipedrive', function ($user) {
        return $user->can('manage-integrations') || $user->can('view-pipedrive');
    });
}
```

## Example 4: Mixed Authorization (Config + Custom Logic)

**Scenario**: Allow specific emails OR users with admin role.

```php
// app/Providers/PipedriveServiceProvider.php
protected function registerPipedriveGate(): void
{
    Gate::define('viewPipedrive', function ($user) {
        // Check config-based emails first
        $authorizedEmails = config('pipedrive.dashboard.authorized_emails', []);
        if (in_array($user->email, $authorizedEmails)) {
            return true;
        }
        
        // Then check role
        return $user->hasRole('admin');
    });
}
```

## Example 5: Team-Based Authorization

**Scenario**: Allow access to users in specific teams.

```php
// app/Providers/PipedriveServiceProvider.php
protected function registerPipedriveGate(): void
{
    Gate::define('viewPipedrive', function ($user) {
        $allowedTeams = ['development', 'sales', 'management'];
        return $user->teams()->whereIn('name', $allowedTeams)->exists();
    });
}
```

## Example 6: Environment-Specific Authorization

**Scenario**: Different authorization rules for different environments.

```php
// app/Providers/PipedriveServiceProvider.php
protected function registerPipedriveGate(): void
{
    Gate::define('viewPipedrive', function ($user) {
        if (app()->environment('local', 'development')) {
            // More permissive in development
            return $user->hasRole(['admin', 'developer']);
        }
        
        if (app()->environment('staging')) {
            // Moderate restrictions in staging
            return $user->hasRole('admin') || $user->email === 'tester@company.com';
        }
        
        // Strict in production
        return $user->hasRole('admin') && $user->email_verified_at;
    });
}
```

## Example 7: Webhook Health Endpoint Configuration

**Scenario**: Configure webhook security for Pipedrive servers while allowing user access.

```php
// config/pipedrive.php
'dashboard' => [
    'authorized_emails' => ['admin@company.com'],
],

'webhooks' => [
    'security' => [
        // For Pipedrive servers
        'basic_auth' => [
            'enabled' => true,
            'username' => env('PIPEDRIVE_WEBHOOK_USERNAME', 'pipedrive_webhook'),
            'password' => env('PIPEDRIVE_WEBHOOK_PASSWORD'),
        ],
    ],
],
```

```env
# .env file
PIPEDRIVE_WEBHOOK_USERNAME=pipedrive_webhook
PIPEDRIVE_WEBHOOK_PASSWORD=super_secure_password_123
```

Now the `/pipedrive/webhook/health` endpoint will be accessible by:
- Users with email `admin@company.com` (via dashboard auth)
- Pipedrive servers using Basic Auth credentials (via webhook auth)

## Example 8: IP-Based Webhook Security

**Scenario**: Restrict webhook health endpoint to specific IPs and authorized users.

```php
// config/pipedrive.php
'webhooks' => [
    'security' => [
        'ip_whitelist' => [
            'enabled' => true,
            'ips' => [
                '185.166.142.0/24',  // Pipedrive IP range
                '192.168.1.100',     // Your monitoring server
                '10.0.0.0/8',        // Internal network
            ],
        ],
    ],
],
```

## Example 9: Complete Production Setup

**Scenario**: Full production configuration with multiple security layers.

```php
// config/pipedrive.php
'dashboard' => [
    'authorized_emails' => [
        'admin@company.com',
        'devops@company.com',
    ],
],

'webhooks' => [
    'security' => [
        'basic_auth' => [
            'enabled' => true,
            'username' => env('PIPEDRIVE_WEBHOOK_USERNAME'),
            'password' => env('PIPEDRIVE_WEBHOOK_PASSWORD'),
        ],
        'ip_whitelist' => [
            'enabled' => true,
            'ips' => ['185.166.142.0/24'],
        ],
        'signature' => [
            'enabled' => true,
            'secret' => env('PIPEDRIVE_WEBHOOK_SECRET'),
            'header' => 'X-Pipedrive-Signature',
        ],
    ],
],
```

```php
// app/Providers/PipedriveServiceProvider.php
protected function registerPipedriveGate(): void
{
    Gate::define('viewPipedrive', function ($user) {
        // Must be verified and have admin role
        return $user->email_verified_at && 
               $user->hasRole('admin') && 
               $user->is_active;
    });
}
```

## Testing Your Configuration

```bash
# Test with authorized user
curl -H "Authorization: Bearer YOUR_TOKEN" https://your-app.com/pipedrive/oauth/status

# Test webhook health with basic auth (as Pipedrive would)
curl -u webhook_user:webhook_pass https://your-app.com/pipedrive/webhook/health

# Test webhook health as authorized user
curl -H "Authorization: Bearer YOUR_TOKEN" https://your-app.com/pipedrive/webhook/health
```
