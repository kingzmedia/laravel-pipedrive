# Custom Field Automation Upgrade Guide

This guide helps you upgrade to the new custom field automation features introduced in Laravel-Pipedrive.

## 🆕 What's New

### Automatic Custom Field Synchronization
- **Hourly Scheduler**: Automatic sync of custom fields every hour
- **Webhook Detection**: Real-time detection of new custom fields in entity updates
- **Smart Triggering**: Only syncs when new fields are detected
- **Queue Integration**: Asynchronous processing for better performance

## 🔧 Migration Steps

### 1. Update Environment Configuration

Add these new environment variables to your `.env` file:

```env
# Custom fields automatic synchronization
PIPEDRIVE_CUSTOM_FIELDS_SCHEDULER_ENABLED=true
PIPEDRIVE_CUSTOM_FIELDS_SCHEDULER_FREQUENCY=1
PIPEDRIVE_CUSTOM_FIELDS_SCHEDULER_FORCE=true

# Webhook custom field detection
PIPEDRIVE_WEBHOOKS_DETECT_CUSTOM_FIELDS=true
```

### 2. Update Configuration File

If you've published the configuration file, update `config/pipedrive.php`:

```php
'sync' => [
    // ... existing configuration ...
    
    'scheduler' => [
        // ... existing scheduler configuration ...
        
        // Custom fields specific scheduler
        'custom_fields' => [
            // Enable/disable automatic custom fields sync (independent from main scheduler)
            'enabled' => env('PIPEDRIVE_CUSTOM_FIELDS_SCHEDULER_ENABLED', true),

            // Sync frequency in hours for custom fields (default: 1 hour)
            'frequency_hours' => env('PIPEDRIVE_CUSTOM_FIELDS_SCHEDULER_FREQUENCY', 1),

            // Force sync for custom fields
            'force' => env('PIPEDRIVE_CUSTOM_FIELDS_SCHEDULER_FORCE', true),
        ],
    ],
],

'webhooks' => [
    // ... existing webhook configuration ...
    
    // Enable custom fields detection in webhooks
    'detect_custom_fields' => env('PIPEDRIVE_WEBHOOKS_DETECT_CUSTOM_FIELDS', true),
],
```

### 3. Queue Configuration

Ensure your queue system is properly configured for the new jobs:

```env
# Make sure you have a queue driver configured
QUEUE_CONNECTION=redis  # or database, sqs, etc.

# Optional: Use a dedicated queue for Pipedrive operations
PIPEDRIVE_SYNC_QUEUE=pipedrive-sync
```

### 4. Verify Scheduler Setup

The new custom field scheduler will be automatically registered. Verify it's working:

```bash
# Check scheduled commands
php artisan schedule:list

# Run scheduler manually to test
php artisan schedule:run
```

## 🔍 Verification

### 1. Test Custom Field Detection

Create or update an entity in Pipedrive with a new custom field, then check your logs:

```bash
tail -f storage/logs/laravel.log | grep "custom field"
```

You should see logs like:
```
[INFO] New custom fields detected in webhook
[INFO] Custom fields sync job dispatched
```

### 2. Monitor Queue Jobs

Check that custom field sync jobs are being processed:

```bash
# Monitor queue jobs
php artisan queue:work --verbose

# Check failed jobs
php artisan queue:failed
```

### 3. Verify Scheduler

Check that the hourly custom field sync is scheduled:

```bash
# List all scheduled commands
php artisan schedule:list | grep custom-fields
```

## 📊 Monitoring

### Log Messages to Watch

**Successful Detection:**
```
[INFO] New custom fields detected in webhook
[INFO] Custom fields sync job dispatched
[INFO] Custom fields sync job completed successfully
```

**Scheduler Execution:**
```
[INFO] Pipedrive custom fields scheduled sync completed successfully
```

**Error Conditions:**
```
[ERROR] Custom fields sync job failed
[ERROR] Error during custom field detection in webhook
```

### Performance Monitoring

Monitor these metrics:
- Queue job processing time
- API rate limit usage
- Memory consumption during sync
- Webhook processing latency

## 🚨 Troubleshooting

### Common Issues

**1. Scheduler Not Running**
```bash
# Check if scheduler is enabled
php artisan pipedrive:config

# Verify cron job is set up
crontab -l | grep artisan
```

**2. Queue Jobs Not Processing**
```bash
# Start queue worker
php artisan queue:work

# Check queue configuration
php artisan queue:monitor
```

**3. Webhook Detection Not Working**
```bash
# Verify webhook configuration
php artisan pipedrive:webhooks list

# Check webhook logs
tail -f storage/logs/laravel.log | grep webhook
```

**4. High API Usage**
- Reduce scheduler frequency: `PIPEDRIVE_CUSTOM_FIELDS_SCHEDULER_FREQUENCY=2`
- Monitor API usage in Pipedrive settings
- Check for unnecessary full-data syncs

## 🔄 Rollback Plan

If you need to disable the new features:

```env
# Disable custom field automation
PIPEDRIVE_CUSTOM_FIELDS_SCHEDULER_ENABLED=false
PIPEDRIVE_WEBHOOKS_DETECT_CUSTOM_FIELDS=false
```

The system will fall back to manual custom field synchronization only.

## 📈 Best Practices

### Production Deployment

1. **Gradual Rollout**: Enable webhook detection first, then scheduler
2. **Monitor API Usage**: Watch Pipedrive API consumption
3. **Queue Scaling**: Ensure adequate queue workers
4. **Error Alerting**: Set up alerts for failed sync jobs

### Configuration Tuning

**High-Activity Environments:**
```env
PIPEDRIVE_CUSTOM_FIELDS_SCHEDULER_FREQUENCY=2  # Every 2 hours
PIPEDRIVE_WEBHOOKS_DETECT_CUSTOM_FIELDS=true   # Real-time detection
```

**Low-Activity Environments:**
```env
PIPEDRIVE_CUSTOM_FIELDS_SCHEDULER_FREQUENCY=6  # Every 6 hours
PIPEDRIVE_WEBHOOKS_DETECT_CUSTOM_FIELDS=true   # Still enable for real-time
```

## 📞 Support

If you encounter issues during migration:

1. Check the [troubleshooting guide](../robustness/troubleshooting.md)
2. Review the [custom field automation documentation](../features/custom-field-automation.md)
3. Enable verbose logging and check error messages
4. Create an issue with detailed logs and configuration

## 🎯 Next Steps

After successful migration:

1. Monitor system performance for 24-48 hours
2. Adjust scheduler frequency based on your needs
3. Set up monitoring and alerting
4. Review and optimize queue configuration
5. Consider implementing custom event listeners for specific business logic
