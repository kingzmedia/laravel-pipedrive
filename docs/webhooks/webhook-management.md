# Webhook Management

The Laravel-Pipedrive package provides an enhanced webhook management command that intelligently uses your configuration to streamline webhook setup.

## Command Overview

```bash
php artisan pipedrive:webhooks {action} [options]
```

### Available Actions

- `list` - List all existing webhooks
- `create` - Create a new webhook with smart configuration
- `delete` - Delete an existing webhook
- `test` - Test webhook URL connectivity

## Smart Configuration Features

### Auto-Configuration Mode

Use `--auto-config` to automatically use configuration values as defaults:

```bash
php artisan pipedrive:webhooks create --auto-config
```

This mode will:
- ✅ Use `APP_URL` + webhook path as default URL
- ✅ Apply configured HTTP Basic Auth credentials
- ✅ Suggest events based on enabled entities
- ✅ Skip interactive prompts where possible

### Configuration Detection

The command automatically detects and displays:

```
📋 Current webhook configuration:
  → App URL: https://your-app.com
  → Webhook path: pipedrive/webhook
  → Full URL: https://your-app.com/pipedrive/webhook
  → Auto-sync: enabled
  → Basic auth: enabled
  → Auth username: admin
  → Enabled entities: deals, activities, persons
```

### Smart Event Suggestions

Based on your `PIPEDRIVE_ENABLED_ENTITIES` configuration, the command suggests relevant webhook events:

```
💡 Suggested events based on your enabled entities:
   • *.* (catch all)
   • added.deal
   • updated.deal
   • deleted.deal
   • added.activity
   • updated.activity
   • deleted.activity
```

## Usage Examples

### 1. Quick Setup with Auto-Configuration

```bash
# Use all configuration defaults
php artisan pipedrive:webhooks create --auto-config

# With URL testing
php artisan pipedrive:webhooks create --auto-config --test-url
```

### 2. Interactive Setup with Smart Suggestions

```bash
# Interactive mode with configuration hints
php artisan pipedrive:webhooks create --verbose

# Specify some parameters, get suggestions for others
php artisan pipedrive:webhooks create --event="added.deal"
```

### 3. Manual Configuration

```bash
# Fully manual setup
php artisan pipedrive:webhooks create \
  --url="https://your-app.com/pipedrive/webhook" \
  --event="*.*" \
  --auth-user="webhook_user" \
  --auth-pass="secure_password"
```

### 4. Test Webhook Connectivity

```bash
# Test configured webhook URL
php artisan pipedrive:webhooks test

# Test specific URL
php artisan pipedrive:webhooks test --url="https://your-app.com/pipedrive/webhook"
```

## Webhook Testing

The `test` action performs comprehensive connectivity tests:

### Health Check
Tests the `/health` endpoint of your webhook:
```
→ Testing health endpoint: https://your-app.com/pipedrive/webhook/health
✅ Health check passed
   Status: ok
   Service: Laravel Pipedrive Webhook Handler
```

### Webhook Endpoint Test
Sends a test POST request to verify the webhook can receive data:
```
→ Testing main webhook endpoint: https://your-app.com/pipedrive/webhook
✅ Webhook endpoint is accessible
   Response status: received
```

## Configuration Requirements

### Environment Variables

```bash
# Required for URL auto-detection
APP_URL=https://your-app.com

# Entity filtering (affects event suggestions)
PIPEDRIVE_ENABLED_ENTITIES=deals,activities,persons

# Webhook security (used for auto-auth)
PIPEDRIVE_WEBHOOK_BASIC_AUTH_ENABLED=true
PIPEDRIVE_WEBHOOK_BASIC_AUTH_USERNAME=admin
PIPEDRIVE_WEBHOOK_BASIC_AUTH_PASSWORD=secure_password
```

### Configuration File

In `config/pipedrive.php`:

```php
'webhooks' => [
    'route' => [
        'path' => 'pipedrive/webhook',
    ],
    'security' => [
        'basic_auth' => [
            'enabled' => env('PIPEDRIVE_WEBHOOK_BASIC_AUTH_ENABLED', false),
            'username' => env('PIPEDRIVE_WEBHOOK_BASIC_AUTH_USERNAME'),
            'password' => env('PIPEDRIVE_WEBHOOK_BASIC_AUTH_PASSWORD'),
        ],
    ],
    'auto_sync' => true,
],
```

## Command Options

| Option | Description | Example |
|--------|-------------|---------|
| `--url` | Webhook URL | `--url="https://app.com/webhook"` |
| `--event` | Event pattern | `--event="added.deal"` |
| `--auth-user` | HTTP Basic Auth username | `--auth-user="admin"` |
| `--auth-pass` | HTTP Basic Auth password | `--auth-pass="secret"` |
| `--auto-config` | Use configuration defaults | `--auto-config` |
| `--test-url` | Test URL before creating | `--test-url` |
| `--verbose` | Show detailed output | `--verbose` |

## Event Patterns

### Common Patterns
- `*.*` - All events (recommended for development)
- `added.*` - All creation events
- `updated.*` - All update events
- `deleted.*` - All deletion events

### Entity-Specific Events
- `added.deal` - New deals
- `updated.person` - Person updates
- `deleted.organization` - Organization deletions

### Best Practices

1. **Start with `*.*`** for development and testing
2. **Use specific events** in production for better performance
3. **Enable only needed entities** via `PIPEDRIVE_ENABLED_ENTITIES`
4. **Always test webhook URLs** before creating webhooks
5. **Use HTTP Basic Auth** for webhook security
6. **Monitor webhook logs** for debugging

## Troubleshooting

### Common Issues

**APP_URL not configured:**
```
⚠️ APP_URL is not configured, cannot suggest webhook URL
```
Solution: Set `APP_URL` in your `.env` file

**Webhook URL test fails:**
```
❌ Health check failed: Connection refused
```
Solution: Ensure your application is accessible at the configured URL

**Authentication errors:**
```
⚠️ Webhook endpoint returned HTTP 401
```
Solution: Check HTTP Basic Auth credentials in configuration
