# Scheduled Synchronization

The Laravel-Pipedrive package provides automatic scheduled synchronization capabilities to keep your local data in sync with Pipedrive without manual intervention.

## Overview

The scheduled sync feature allows you to:
- Automatically sync all Pipedrive entities and custom fields
- Configure sync frequency and timing
- Run full data synchronization with force mode
- Monitor sync operations through logs
- Prevent API rate limiting with configurable delays

## Configuration

### Environment Variables

Add these variables to your `.env` file:

```env
# Enable/disable scheduled sync
PIPEDRIVE_SCHEDULER_ENABLED=true

# Sync frequency in hours (default: 24)
PIPEDRIVE_SCHEDULER_FREQUENCY=24

# Specific time to run sync (24-hour format, optional)
PIPEDRIVE_SCHEDULER_TIME=02:00

# Use full data mode (WARNING: Resource intensive)
PIPEDRIVE_SCHEDULER_FULL_DATA=true

# Force sync (skip confirmations, overwrite existing data)
PIPEDRIVE_SCHEDULER_FORCE=true

# Sync custom fields along with entities
PIPEDRIVE_SCHEDULER_SYNC_CUSTOM_FIELDS=true

# Memory limit for scheduled sync (in MB, 0 = no limit)
PIPEDRIVE_SCHEDULER_MEMORY_LIMIT=2048

# API rate limiting configuration
PIPEDRIVE_API_DELAY=0.3
PIPEDRIVE_API_DELAY_ENABLED=true
```

### Configuration File

The scheduler configuration is located in `config/pipedrive.php`:

```php
'sync' => [
    // ... other sync settings

    // API rate limiting configuration
    'api' => [
        // Delay between API calls in seconds (default: 0.3s)
        'delay' => env('PIPEDRIVE_API_DELAY', 0.3),
        
        // Enable/disable API delay
        'delay_enabled' => env('PIPEDRIVE_API_DELAY_ENABLED', true),
    ],

    // Automatic scheduled synchronization
    'scheduler' => [
        // Enable/disable automatic scheduled sync
        'enabled' => env('PIPEDRIVE_SCHEDULER_ENABLED', false),

        // Sync frequency in hours (default: 24 hours)
        'frequency_hours' => env('PIPEDRIVE_SCHEDULER_FREQUENCY', 24),

        // Time of day to run sync (24-hour format)
        'time' => env('PIPEDRIVE_SCHEDULER_TIME', '02:00'),

        // Include full data sync
        'full_data' => env('PIPEDRIVE_SCHEDULER_FULL_DATA', true),

        // Force sync (skip confirmations)
        'force' => env('PIPEDRIVE_SCHEDULER_FORCE', true),

        // Sync custom fields along with entities
        'sync_custom_fields' => env('PIPEDRIVE_SCHEDULER_SYNC_CUSTOM_FIELDS', true),

        // Memory limit for scheduled sync (in MB)
        'memory_limit' => env('PIPEDRIVE_SCHEDULER_MEMORY_LIMIT', 2048),
    ],
],
```

## Commands

### Manual Scheduled Sync

Run the scheduled sync manually:

```bash
# Run scheduled sync
php artisan pipedrive:scheduled-sync

# Dry run (show what would be synced)
php artisan pipedrive:scheduled-sync --dry-run

# Verbose output
php artisan pipedrive:scheduled-sync --verbose
```

### Individual Sync Commands with API Delays

All sync commands now include automatic API delays:

```bash
# Sync entities with API delays
php artisan pipedrive:sync-entities --verbose

# Sync custom fields with API delays
php artisan pipedrive:sync-custom-fields --verbose
```

## Scheduling Setup

### Laravel Task Scheduler

The package automatically registers the scheduled sync with Laravel's task scheduler when enabled. Ensure your Laravel scheduler is running:

```bash
# Add to your crontab
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

### Frequency Options

The scheduler supports various frequencies based on the `frequency_hours` setting:

- **24+ hours**: Daily
- **12-23 hours**: Twice daily
- **6-11 hours**: Every 6 hours
- **3-5 hours**: Every 3 hours
- **1-2 hours**: Hourly

### Specific Time Scheduling

If you set `PIPEDRIVE_SCHEDULER_TIME`, the sync will run daily at that specific time, regardless of the frequency setting.

## API Rate Limiting

### Delay Configuration

To prevent hitting Pipedrive's API rate limits, the package includes configurable delays between API calls:

- **Default delay**: 0.3 seconds between calls
- **Configurable**: Adjust via `PIPEDRIVE_API_DELAY`
- **Can be disabled**: Set `PIPEDRIVE_API_DELAY_ENABLED=false` for testing

### How It Works

1. Before each API call, the system waits for the configured delay
2. Delays are applied to both entity and custom field sync operations
3. Verbose mode shows delay information for debugging

## Memory Management

### Memory Limits

The scheduler can set memory limits to prevent out-of-memory errors:

```env
# Set memory limit to 2GB (default)
PIPEDRIVE_SCHEDULER_MEMORY_LIMIT=2048

# Set memory limit to 1GB
PIPEDRIVE_SCHEDULER_MEMORY_LIMIT=1024

# No memory limit (use system default)
PIPEDRIVE_SCHEDULER_MEMORY_LIMIT=0
```

### Memory Monitoring

The system monitors memory usage and provides warnings when limits may be insufficient for full-data operations.

## Logging and Monitoring

### Log Channels

Scheduled sync operations are logged using Laravel's logging system:

```php
// Successful completion
Log::info('Pipedrive scheduled sync completed successfully', [
    'duration' => $duration,
    'config' => $config,
]);

// Failures
Log::error('Pipedrive scheduled sync failed', [
    'exception' => $e->getMessage(),
    'trace' => $e->getTraceAsString(),
    'config' => $config,
]);
```

### Monitoring Commands

```bash
# Check Laravel logs
tail -f storage/logs/laravel.log

# Check scheduled tasks
php artisan schedule:list
```

## Best Practices

### Production Setup

1. **Enable scheduling**: Set `PIPEDRIVE_SCHEDULER_ENABLED=true`
2. **Set appropriate timing**: Use `PIPEDRIVE_SCHEDULER_TIME=02:00` for off-peak hours
3. **Monitor memory**: Set reasonable memory limits
4. **Enable logging**: Monitor sync operations through logs
5. **Test first**: Use `--dry-run` to validate configuration

### Development Setup

1. **Disable scheduling**: Set `PIPEDRIVE_SCHEDULER_ENABLED=false`
2. **Run manual syncs**: Use individual commands for testing
3. **Enable verbose mode**: Use `--verbose` for debugging
4. **Adjust delays**: Reduce `PIPEDRIVE_API_DELAY` for faster testing

### Performance Considerations

1. **Full data mode**: Use sparingly, very resource intensive
2. **Memory limits**: Set appropriate limits based on your data size
3. **API delays**: Balance between rate limiting and sync speed
4. **Scheduling frequency**: Don't over-sync, respect Pipedrive's limits

## Troubleshooting

### Common Issues

1. **Scheduler not running**: Check Laravel's task scheduler setup
2. **Memory errors**: Increase memory limits or disable full-data mode
3. **API rate limits**: Increase delay between API calls
4. **Sync failures**: Check logs for detailed error information

### Debug Commands

```bash
# Test scheduler configuration
php artisan pipedrive:scheduled-sync --dry-run --verbose

# Check individual sync commands
php artisan pipedrive:sync-entities --verbose
php artisan pipedrive:sync-custom-fields --verbose

# View scheduled tasks
php artisan schedule:list
```
