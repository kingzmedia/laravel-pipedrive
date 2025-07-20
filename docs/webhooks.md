# 🔔 Webhooks Real-Time Synchronization

## 🎯 **Overview**

Laravel-Pipedrive provides a complete webhook system for real-time data synchronization. When data changes in Pipedrive, your application is instantly notified and can automatically update local records.

## 🚀 **Quick Setup**

### **1. Configure Webhook Security**

Add to your `.env` file:

```env
# Basic webhook configuration
PIPEDRIVE_WEBHOOKS_AUTO_SYNC=true
PIPEDRIVE_WEBHOOK_PATH=pipedrive/webhook

# Security options (choose one or more)
PIPEDRIVE_WEBHOOK_BASIC_AUTH=true
PIPEDRIVE_WEBHOOK_USERNAME=your_webhook_user
PIPEDRIVE_WEBHOOK_PASSWORD=your_secure_password

# Optional: IP whitelist
PIPEDRIVE_WEBHOOK_IP_WHITELIST=false

# Optional: Custom signature verification
PIPEDRIVE_WEBHOOK_SIGNATURE=false
PIPEDRIVE_WEBHOOK_SECRET=your_webhook_secret
```

### **2. Create Webhook in Pipedrive**

```bash
# List existing webhooks
php artisan pipedrive:webhooks list

# Create webhook for all events
php artisan pipedrive:webhooks create \
    --url=https://your-app.com/pipedrive/webhook \
    --event=*.* \
    --auth-user=your_webhook_user \
    --auth-pass=your_secure_password

# Create webhook for specific events
php artisan pipedrive:webhooks create \
    --url=https://your-app.com/pipedrive/webhook \
    --event=updated.deal
```

### **3. Test Webhook Endpoint**

```bash
# Test webhook health
curl https://your-app.com/pipedrive/webhook/health

# Expected response:
{
  "status": "ok",
  "service": "Laravel Pipedrive Webhooks",
  "timestamp": "2024-01-15T10:30:00.000Z"
}
```

## 🔧 **Configuration**

### **Webhook Route Configuration**

```php
// config/pipedrive.php
'webhooks' => [
    'auto_sync' => true,  // Enable automatic synchronization
    
    'route' => [
        'path' => 'pipedrive/webhook',  // Webhook URL path
        'name' => 'pipedrive.webhook', // Route name
        'middleware' => ['api'],        // Applied middleware
    ],
    
    // ... security configuration
],
```

### **Security Options**

#### **HTTP Basic Authentication (Recommended)**

```env
PIPEDRIVE_WEBHOOK_BASIC_AUTH=true
PIPEDRIVE_WEBHOOK_USERNAME=webhook_user
PIPEDRIVE_WEBHOOK_PASSWORD=super_secure_password_123
```

```bash
# Create webhook with Basic Auth
php artisan pipedrive:webhooks create \
    --url=https://your-app.com/pipedrive/webhook \
    --event=*.* \
    --auth-user=webhook_user \
    --auth-pass=super_secure_password_123
```

#### **IP Whitelist**

```php
// config/pipedrive.php
'webhooks' => [
    'security' => [
        'ip_whitelist' => [
            'enabled' => true,
            'ips' => [
                '185.166.142.0/24',  // Pipedrive webhook IPs
                '185.166.143.0/24',
                // Add more Pipedrive IPs as needed
            ],
        ],
    ],
],
```

#### **Custom Signature Verification**

```env
PIPEDRIVE_WEBHOOK_SIGNATURE=true
PIPEDRIVE_WEBHOOK_SECRET=your_custom_secret_key
```

## 📊 **Supported Events**

### **Event Types**

| Action | Description | Triggered When |
|--------|-------------|----------------|
| `added` | New record created | Entity is created in Pipedrive |
| `updated` | Record modified | Entity is updated in Pipedrive |
| `deleted` | Record removed | Entity is deleted in Pipedrive |
| `merged` | Records combined | Two entities are merged |

### **Object Types**

| Object | Model | Description |
|--------|-------|-------------|
| `activity` | `PipedriveActivity` | Tasks, calls, meetings |
| `deal` | `PipedriveDeal` | Sales opportunities |
| `file` | `PipedriveFile` | Attachments |
| `note` | `PipedriveNote` | Text notes |
| `organization` | `PipedriveOrganization` | Companies |
| `person` | `PipedrivePerson` | Contacts |
| `pipeline` | `PipedrivePipeline` | Sales pipelines |
| `product` | `PipedriveProduct` | Products |
| `stage` | `PipedriveStage` | Pipeline stages |
| `user` | `PipedriveUser` | Pipedrive users |
| `goal` | `PipedriveGoal` | Sales goals |

### **Event Patterns**

```bash
# All events for all objects
--event=*.*

# All events for specific object
--event=*.deal
--event=*.person

# Specific event for all objects
--event=added.*
--event=updated.*

# Specific event for specific object
--event=added.deal
--event=updated.person
--event=deleted.organization
```

## 🎨 **Custom Event Handling**

### **Listen to Webhook Events**

```php
use Keggermont\LaravelPipedrive\Events\PipedriveWebhookReceived;

// In EventServiceProvider
protected $listen = [
    PipedriveWebhookReceived::class => [
        YourCustomWebhookListener::class,
    ],
];
```

### **Custom Webhook Listener**

```php
use Keggermont\LaravelPipedrive\Events\PipedriveWebhookReceived;

class YourCustomWebhookListener
{
    public function handle(PipedriveWebhookReceived $event)
    {
        // Check event type
        if ($event->isUpdate() && $event->isObjectType('deal')) {
            $this->handleDealUpdate($event);
        }
        
        if ($event->isCreate() && $event->isObjectType('person')) {
            $this->handlePersonCreate($event);
        }
        
        // Check change source
        if ($event->isFromApp()) {
            // Change made in Pipedrive app
        }
        
        if ($event->isFromApi()) {
            // Change made via API
        }
    }
    
    protected function handleDealUpdate(PipedriveWebhookReceived $event)
    {
        $current = $event->current;
        $previous = $event->previous;
        
        // Compare values to detect specific changes
        if ($current['stage_id'] !== $previous['stage_id']) {
            // Deal moved to different stage
            $this->notifyStageChange($current, $previous);
        }
        
        if ($current['value'] !== $previous['value']) {
            // Deal value changed
            $this->notifyValueChange($current, $previous);
        }
    }
}
```

### **Event Properties**

```php
$event = new PipedriveWebhookReceived($webhookData);

// Basic properties
$event->action;        // 'added', 'updated', 'deleted', 'merged'
$event->object;        // 'deal', 'person', 'organization', etc.
$event->objectId;      // Pipedrive ID of the object
$event->current;       // Current object data (for add/update/merge)
$event->previous;      // Previous object data (for update/delete/merge)

// Helper methods
$event->isCreate();    // true if action is 'added'
$event->isUpdate();    // true if action is 'updated'
$event->isDelete();    // true if action is 'deleted'
$event->isMerge();     // true if action is 'merged'

$event->isObjectType('deal');  // Check specific object type

// Metadata
$event->getUserId();           // User who made the change
$event->getCompanyId();        // Company where change occurred
$event->getChangeSource();     // 'app' or 'api'
$event->isFromApp();           // Change from Pipedrive app
$event->isFromApi();           // Change from API
$event->isBulkUpdate();        // Bulk operation
$event->getRetryCount();       // Retry attempt number
$event->isRetry();             // Is this a retry?
```

## 🔍 **Webhook Management**

### **List Webhooks**

```bash
# List all webhooks
php artisan pipedrive:webhooks list

# Verbose output with details
php artisan pipedrive:webhooks list --verbose
```

### **Create Webhooks**

```bash
# Interactive creation
php artisan pipedrive:webhooks create

# With parameters
php artisan pipedrive:webhooks create \
    --url=https://your-app.com/pipedrive/webhook \
    --event=updated.deal \
    --auth-user=webhook_user \
    --auth-pass=secure_password
```

### **Delete Webhooks**

```bash
# Interactive deletion (shows list first)
php artisan pipedrive:webhooks delete

# Direct deletion by ID
php artisan pipedrive:webhooks delete --id=12345
```

## 🚨 **Error Handling & Retry Logic**

### **Pipedrive Retry Behavior**

Pipedrive automatically retries failed webhooks:

1. **First attempt**: Immediate
2. **Second attempt**: After 3 seconds
3. **Third attempt**: After 30 seconds  
4. **Fourth attempt**: After 150 seconds

### **Response Requirements**

Your webhook endpoint must:

- **Respond within 10 seconds**
- **Return HTTP 200** for success
- **Return HTTP 5xx** to trigger retry
- **Return HTTP 4xx** to stop retries

### **Error Logging**

```php
// Webhook errors are automatically logged
Log::error('Pipedrive webhook processing failed', [
    'event' => 'updated.deal',
    'object_id' => 12345,
    'error' => 'Database connection failed',
    'retry' => 2,
]);
```

## 📈 **Monitoring & Debugging**

### **Webhook Logs**

```bash
# Monitor webhook activity
tail -f storage/logs/laravel.log | grep "Pipedrive webhook"

# Check for errors
grep "webhook processing failed" storage/logs/laravel.log
```

### **Health Check**

```bash
# Test webhook endpoint
curl https://your-app.com/pipedrive/webhook/health

# Test with authentication
curl -u webhook_user:password https://your-app.com/pipedrive/webhook/health
```

### **Debug Mode**

Enable detailed logging in your `.env`:

```env
PIPEDRIVE_WEBHOOK_LOGGING=true
PIPEDRIVE_WEBHOOK_LOG_LEVEL=debug
APP_DEBUG=true
```

## 🎯 **Best Practices**

### **Security**

1. **Always use HTTPS** for webhook URLs
2. **Enable Basic Auth** for additional security
3. **Use strong passwords** for webhook authentication
4. **Consider IP whitelisting** for extra protection
5. **Monitor failed attempts** for potential attacks

### **Performance**

1. **Keep processing fast** (under 10 seconds)
2. **Use queues** for heavy processing
3. **Return 200 quickly** then process asynchronously
4. **Handle retries gracefully**

### **Reliability**

1. **Implement idempotency** for duplicate webhooks
2. **Validate webhook data** before processing
3. **Log all webhook activity** for debugging
4. **Have fallback sync** for missed webhooks

### **Example: Async Processing**

```php
// In your webhook listener
public function handle(PipedriveWebhookReceived $event)
{
    // Queue heavy processing
    ProcessPipedriveWebhook::dispatch($event->webhookData)
        ->onQueue('webhooks');
}

// Job class
class ProcessPipedriveWebhook implements ShouldQueue
{
    public function handle()
    {
        // Heavy processing here
        // Send emails, update external systems, etc.
    }
}
```

Real-time synchronization keeps your data fresh and enables powerful automation workflows! 🔔
