# Custom Field Automation

This document describes the automated custom field synchronization features in Laravel-Pipedrive.

## Overview

Laravel-Pipedrive provides two automated mechanisms to keep your custom fields synchronized:

1. **Scheduled Synchronization**: Automatic hourly sync of custom fields
2. **Webhook Detection**: Real-time detection of new custom fields in webhook events

## Scheduled Synchronization

### Configuration

Add these environment variables to enable automatic custom field synchronization:

```env
# Enable custom fields scheduler (default: true)
PIPEDRIVE_CUSTOM_FIELDS_SCHEDULER_ENABLED=true

# Sync frequency in hours (default: 1 hour)
PIPEDRIVE_CUSTOM_FIELDS_SCHEDULER_FREQUENCY=1

# Force sync (skip confirmations, default: true)
PIPEDRIVE_CUSTOM_FIELDS_SCHEDULER_FORCE=true
```

### How It Works

The scheduler automatically runs the `pipedrive:sync-custom-fields` command at the configured frequency:

- **Hourly (default)**: Syncs custom fields every hour
- **Every 2 hours**: Set `PIPEDRIVE_CUSTOM_FIELDS_SCHEDULER_FREQUENCY=2`
- **Every 3 hours**: Set `PIPEDRIVE_CUSTOM_FIELDS_SCHEDULER_FREQUENCY=3`
- **Every 6 hours**: Set `PIPEDRIVE_CUSTOM_FIELDS_SCHEDULER_FREQUENCY=6`
- **Twice daily**: Set `PIPEDRIVE_CUSTOM_FIELDS_SCHEDULER_FREQUENCY=12`
- **Daily**: Set `PIPEDRIVE_CUSTOM_FIELDS_SCHEDULER_FREQUENCY=24`

### Manual Execution

You can also run the sync manually:

```bash
# Sync all entity types
php artisan pipedrive:sync-custom-fields --force

# Sync specific entity type
php artisan pipedrive:sync-custom-fields --entity=deal --force
```

## Webhook Detection

### Configuration

Enable webhook-based custom field detection:

```env
# Enable custom field detection in webhooks (default: true)
PIPEDRIVE_WEBHOOKS_DETECT_CUSTOM_FIELDS=true
```

### How It Works

When processing webhooks for entity updates (deals, persons, organizations, etc.), the system:

1. **Extracts custom fields** from the webhook data (40-character hash keys)
2. **Compares with known fields** in the local database
3. **Detects new fields** that aren't yet synchronized
4. **Triggers automatic sync** for the specific entity type

### Supported Events

- **Added Events**: Detects new custom fields in newly created entities
- **Updated Events**: Detects new custom fields added to existing entities
- **Deleted Events**: Skipped (no current data to analyze)

### Supported Entity Types

- Deals (`deal`)
- Persons (`person`)
- Organizations (`organization`)
- Products (`product`)
- Activities (`activity`)

## Technical Implementation

### Detection Service

The `PipedriveCustomFieldDetectionService` handles:

- Custom field extraction from webhook data
- Comparison with existing fields
- Triggering synchronization jobs

### Sync Job

The `SyncPipedriveCustomFieldsJob` provides:

- Asynchronous custom field synchronization
- Retry mechanism (3 attempts)
- Timeout protection (5 minutes)
- Proper error handling and logging

### Integration Points

1. **Webhook Processing**: Integrated into `ProcessPipedriveWebhookJob`
2. **Scheduler**: Registered in `LaravelPipedriveServiceProvider`
3. **Configuration**: Managed through `config/pipedrive.php`

## Monitoring and Logging

### Log Messages

The system logs important events:

```php
// New custom fields detected
Log::info('New custom fields detected in webhook', [
    'entity_type' => 'deal',
    'new_fields' => ['abcd1234...', 'efgh5678...'],
    'total_custom_fields' => 5,
]);

// Sync job dispatched
Log::info('Custom fields sync job dispatched', [
    'entity_type' => 'deal',
    'trigger' => 'webhook_detection',
]);

// Scheduler execution
Log::info('Pipedrive custom fields scheduled sync completed successfully');
```

### Error Handling

- **Detection errors**: Logged but don't fail webhook processing
- **Sync job failures**: Retried up to 3 times
- **Scheduler failures**: Logged with error details

## Performance Considerations

### Webhook Detection

- **Minimal overhead**: Only analyzes webhook data already being processed
- **Selective triggering**: Only syncs when new fields are detected
- **Asynchronous execution**: Uses queue jobs to avoid blocking webhooks

### Scheduled Sync

- **Configurable frequency**: Balance between freshness and API usage
- **Background execution**: Runs without overlapping
- **Memory management**: Handles large datasets efficiently

## Best Practices

### Configuration

1. **Start with hourly sync** for active development environments
2. **Reduce frequency** for production (2-4 hours) to minimize API calls
3. **Enable webhook detection** for real-time updates
4. **Monitor logs** for sync failures and adjust accordingly

### Monitoring

1. **Check scheduler logs** regularly
2. **Monitor queue job failures**
3. **Verify custom field synchronization** after Pipedrive changes
4. **Set up alerts** for repeated sync failures

### Troubleshooting

1. **Verify API credentials** if sync fails
2. **Check rate limits** if getting API errors
3. **Review queue configuration** for job processing
4. **Validate webhook configuration** for detection issues

## API Rate Limits

### Considerations

- **Scheduled sync**: Uses standard API calls with built-in delays
- **Webhook detection**: Minimal additional API usage
- **Batch processing**: Efficient handling of multiple fields

### Recommendations

- **Monitor API usage** in Pipedrive settings
- **Adjust frequency** if approaching limits
- **Use webhook detection** to reduce scheduled sync frequency
