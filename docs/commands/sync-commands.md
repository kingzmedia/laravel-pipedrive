# Sync Commands Documentation

This document describes the synchronization commands available in the Laravel Pipedrive package and their usage patterns.

## Overview

The Laravel Pipedrive package provides three main synchronization commands:

- `pipedrive:sync-entities` - Synchronizes Pipedrive entities (deals, persons, organizations, etc.)
- `pipedrive:sync-custom-fields` - Synchronizes custom fields for different entity types
- `pipedrive:scheduled-sync` - Automated scheduled synchronization of all entities and custom fields

All sync commands support two operation modes:

1. **Standard Mode** (default): Fetches latest modifications with optimized performance
2. **Full Data Mode**: Retrieves all data with pagination (use with caution)

## API Rate Limiting

All sync commands now include automatic API rate limiting to prevent hitting Pipedrive's API limits:

- **Default delay**: 0.3 seconds between API calls
- **Configurable**: Adjust via `PIPEDRIVE_API_DELAY` environment variable
- **Can be disabled**: Set `PIPEDRIVE_API_DELAY_ENABLED=false` for testing
- **Verbose logging**: Use `-v` flag to see delay information

### Configuration

```env
# API rate limiting configuration
PIPEDRIVE_API_DELAY=0.3
PIPEDRIVE_API_DELAY_ENABLED=true
```

## Command: pipedrive:scheduled-sync

### Overview

The scheduled sync command provides automated synchronization of all Pipedrive entities and custom fields. It's designed for production environments where regular, unattended synchronization is required.

### Basic Usage

```bash
# Run scheduled sync (uses configuration settings)
php artisan pipedrive:scheduled-sync

# Dry run (show what would be synced)
php artisan pipedrive:scheduled-sync --dry-run

# Verbose output
php artisan pipedrive:scheduled-sync --verbose
```

### Configuration

The scheduled sync is configured via environment variables and the `config/pipedrive.php` file. See the [Scheduled Synchronization documentation](scheduled-sync.md) for complete configuration details.

### Key Features

- **Automatic full data sync**: Runs with `--full-data` and `--force` flags by default
- **Memory management**: Configurable memory limits to prevent out-of-memory errors
- **Comprehensive logging**: Detailed logs for monitoring and debugging
- **Error handling**: Graceful error handling with detailed error reporting
- **API rate limiting**: Built-in delays to prevent API rate limit issues

## Command: pipedrive:sync-entities

### Basic Usage

```bash
# Sync all entities (latest modifications only)
php artisan pipedrive:sync-entities

# Sync specific entity
php artisan pipedrive:sync-entities --entity=deals

# Sync with custom limit (max 500)
php artisan pipedrive:sync-entities --entity=persons --limit=200

# Force update existing records
php artisan pipedrive:sync-entities --entity=organizations --force

# Force full-data mode without confirmation (for automated scripts)
php artisan pipedrive:sync-entities --entity=deals --full-data --force

# Verbose output
php artisan pipedrive:sync-entities --entity=deals -v
```

### Full Data Mode

⚠️ **WARNING**: Use with extreme caution due to API rate limits and potential long execution times.

```bash
# Retrieve ALL deals with pagination
php artisan pipedrive:sync-entities --entity=deals --full-data

# Retrieve ALL data for all entities (very resource intensive)
php artisan pipedrive:sync-entities --full-data -v
```

### Available Entities

- `activities` - Activities and tasks
- `deals` - Sales deals
- `files` - Attached files
- `goals` - Sales goals
- `notes` - Notes and comments
- `organizations` - Companies and organizations
- `persons` - Individual contacts
- `pipelines` - Sales pipelines
- `products` - Products and services
- `stages` - Pipeline stages
- `users` - Pipedrive users

### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--entity` | Sync specific entity type | All entities |
| `--limit` | Max records per entity (max 500) | 500 |
| `--full-data` | Retrieve ALL data with pagination | false |
| `--force` | Force update existing records + skip confirmations | false |
| `-v, --verbose` | Show detailed output (Laravel standard) | false |

## Command: pipedrive:sync-custom-fields

### Basic Usage

```bash
# Sync all custom fields (latest modifications only)
php artisan pipedrive:sync-custom-fields

# Sync custom fields for specific entity
php artisan pipedrive:sync-custom-fields --entity=deal

# Force update existing fields
php artisan pipedrive:sync-custom-fields --entity=person --force

# Force full-data mode without confirmation (for automated scripts)
php artisan pipedrive:sync-custom-fields --entity=deal --full-data --force

# Verbose output
php artisan pipedrive:sync-custom-fields -v
```

### Full Data Mode

⚠️ **WARNING**: Use with extreme caution due to API rate limits.

```bash
# Retrieve ALL custom fields with pagination
php artisan pipedrive:sync-custom-fields --entity=deal --full-data

# Retrieve ALL custom fields for all entities
php artisan pipedrive:sync-custom-fields --full-data -v
```

### Available Entity Types

- `deal` - Deal custom fields
- `person` - Person custom fields
- `organization` - Organization custom fields
- `product` - Product custom fields
- `activity` - Activity custom fields
- `note` - Note custom fields

### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--entity` | Sync fields for specific entity | All entities |
| `--full-data` | Retrieve ALL fields with pagination | false |
| `--force` | Force update existing fields + skip confirmations | false |
| `-v, --verbose` | Show detailed output (Laravel standard) | false |

## Operation Modes

### Standard Mode (Default)

**Behavior:**
- Fetches latest modifications only
- Sorted by `update_time DESC` (most recent first)
- Limited to specified `--limit` (max 500 records)
- Optimized for performance and API efficiency

**Use Cases:**
- Regular synchronization
- Incremental updates
- Production environments
- When API rate limits are a concern

### Full Data Mode (--full-data)

**Behavior:**
- Retrieves ALL data using pagination
- Sorted by `add_time ASC` (oldest first for consistent pagination)
- Automatically handles pagination with 500 records per page
- Includes safety limits (max 1000 pages for entities, 100 pages for fields)

**Use Cases:**
- Initial data migration
- Complete data backup
- Data recovery scenarios
- Development/testing environments

⚠️ **Important Warnings:**
- Can consume significant API rate limits
- May take a very long time to complete
- **Requires significant memory** (1GB+ recommended for entities, 512MB+ for custom fields)
- Should be used sparingly in production
- Always test in development first

### **Memory Requirements**

Full-data mode loads large datasets into memory and requires adequate PHP memory limits:

**Recommended Memory Limits:**
- **Entities (--full-data)**: 1GB+ (`memory_limit = 1024M` or higher)
- **Custom Fields (--full-data)**: 512MB+ (`memory_limit = 512M` or higher)
- **Large datasets**: 2GB+ for organizations/deals with 10,000+ records

**Memory Error Solutions:**
```bash
# Option 1: Increase memory for single command
php -d memory_limit=2048M artisan pipedrive:sync-entities --full-data

# Option 2: Update php.ini permanently
# Add to php.ini: memory_limit = 2048M

# Option 3: Use standard mode instead
php artisan pipedrive:sync-entities --limit=500  # No --full-data flag
```

**Memory Monitoring:**
- Commands automatically check memory limits before starting
- Real-time memory monitoring during pagination
- Automatic stop at 80% memory usage with clear error messages
- Warnings at 60% memory usage

### **Force Mode (--force)**

The `--force` flag serves two purposes:

1. **Skip existing record checks**: Updates records even if they already exist locally
2. **Skip confirmation prompts**: Bypasses the safety confirmation for `--full-data` mode

**Use cases for --force:**
- **Automated scripts**: When running sync commands in cron jobs or CI/CD pipelines
- **Data recovery**: When you need to re-sync data without manual intervention
- **Batch operations**: When processing large datasets programmatically

**Examples:**
```bash
# Automated full sync without prompts (perfect for scripts)
php artisan pipedrive:sync-entities --entity=deals --full-data --force

# Force update all existing records
php artisan pipedrive:sync-entities --entity=persons --force

# Combine with verbose for automated monitoring
php artisan pipedrive:sync-entities --full-data --force -v >> /var/log/sync.log
```

⚠️ **Force Mode Warnings:**
- Use with extreme caution in production
- Always monitor resource usage when using --force with --full-data
- Consider API rate limits when automating with --force

## API Limitations

### Pipedrive API Limits

- **Maximum records per request**: 500 (API v2), 100 (API v1)
- **Rate limits**: Vary by plan (typically 100-10,000 requests per day)
- **Timeout limits**: Long-running requests may timeout

### Package Safeguards

- Automatic limit enforcement (max 500 per request)
- Pagination safety limits (max 1000 pages)
- Confirmation prompts for full-data mode
- Detailed progress reporting in verbose mode
- Error handling and recovery

## Best Practices

### For Regular Synchronization

```bash
# Daily sync of latest modifications
php artisan pipedrive:sync-entities --limit=500

# Sync specific high-activity entities more frequently
php artisan pipedrive:sync-entities --entity=deals --limit=200
php artisan pipedrive:sync-entities --entity=activities --limit=300
```

### For Initial Setup

```bash
# 1. Start with custom fields
php artisan pipedrive:sync-custom-fields --full-data -v

# 2. Sync core entities in order of dependency
php artisan pipedrive:sync-entities --entity=users --full-data
php artisan pipedrive:sync-entities --entity=pipelines --full-data
php artisan pipedrive:sync-entities --entity=stages --full-data
php artisan pipedrive:sync-entities --entity=organizations --full-data
php artisan pipedrive:sync-entities --entity=persons --full-data
php artisan pipedrive:sync-entities --entity=deals --full-data
```

### For Production Environments

```bash
# Use cron jobs for regular sync
# Example crontab entry for hourly sync:
# 0 * * * * cd /path/to/project && php artisan pipedrive:sync-entities --limit=100

# Monitor and log sync operations
php artisan pipedrive:sync-entities --verbose >> /var/log/pipedrive-sync.log 2>&1
```

## Troubleshooting

### Common Issues

1. **Memory Limit Errors** ⚠️ **Most Common with --full-data**
   ```
   Error: Allowed memory size of X bytes exhausted
   Error: Memory limit reached during pagination
   ```

   **Solutions:**
   ```bash
   # Immediate fix - increase memory for single command
   php -d memory_limit=2048M artisan pipedrive:sync-entities --full-data

   # Permanent fix - update php.ini
   memory_limit = 2048M

   # Alternative - use standard mode
   php artisan pipedrive:sync-entities --limit=500
   ```

   **Prevention:**
   - Commands now check memory limits before starting
   - Automatic warnings at 60% memory usage
   - Automatic stop at 80% memory usage
   - Use `--force` to bypass memory warnings (not recommended)

2. **API Rate Limit Exceeded**
   - Reduce `--limit` value
   - Avoid `--full-data` mode
   - Space out sync operations

3. **Timeout Errors**
   - Use smaller batch sizes
   - Sync specific entities instead of all
   - Check network connectivity
   - Increase PHP `max_execution_time`

### Error Recovery

```bash
# Resume sync with force flag if interrupted
php artisan pipedrive:sync-entities --entity=deals --force -v

# Check logs for detailed error information
tail -f storage/logs/laravel.log
```

## Monitoring and Logging

All sync operations are logged with detailed information including:

- Number of records processed
- Success/failure counts
- API response times
- Error details and stack traces
- Pagination progress (in verbose mode)

Use the `-v` or `--verbose` flag for detailed real-time output during sync operations.

## Examples

### Example 1: Daily Incremental Sync

```bash
#!/bin/bash
# daily-sync.sh - Script for daily incremental synchronization

echo "Starting daily Pipedrive sync..."

# Sync latest modifications for high-activity entities
php artisan pipedrive:sync-entities --entity=deals --limit=200 -v
php artisan pipedrive:sync-entities --entity=activities --limit=300 -v
php artisan pipedrive:sync-entities --entity=persons --limit=150 -v

# Sync custom fields (usually stable, but check for updates)
php artisan pipedrive:sync-custom-fields --entity=deal
php artisan pipedrive:sync-custom-fields --entity=person

echo "Daily sync completed!"
```

### Example 2: Initial Setup (Full Migration)

```bash
#!/bin/bash
# initial-setup.sh - Script for complete data migration

echo "⚠️  WARNING: This will perform a complete data migration from Pipedrive"
echo "This may take several hours and consume significant API rate limits."
read -p "Are you sure you want to continue? (y/N): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Operation cancelled."
    exit 1
fi

echo "Starting full Pipedrive migration..."

# Step 1: Custom fields first
echo "1/8 Syncing custom fields..."
php artisan pipedrive:sync-custom-fields --full-data -v

# Step 2: Core configuration entities
echo "2/8 Syncing users..."
php artisan pipedrive:sync-entities --entity=users --full-data -v

echo "3/8 Syncing pipelines..."
php artisan pipedrive:sync-entities --entity=pipelines --full-data -v

echo "4/8 Syncing stages..."
php artisan pipedrive:sync-entities --entity=stages --full-data -v

# Step 3: Master data entities
echo "5/8 Syncing organizations..."
php artisan pipedrive:sync-entities --entity=organizations --full-data -v

echo "6/8 Syncing persons..."
php artisan pipedrive:sync-entities --entity=persons --full-data -v

echo "7/8 Syncing products..."
php artisan pipedrive:sync-entities --entity=products --full-data -v

# Step 4: Transactional entities
echo "8/8 Syncing deals..."
php artisan pipedrive:sync-entities --entity=deals --full-data -v

echo "✅ Full migration completed!"
echo "You can now run incremental syncs for ongoing updates."
```

### Example 3: Monitoring and Alerting

```bash
#!/bin/bash
# monitored-sync.sh - Script with monitoring and alerting

LOG_FILE="/var/log/pipedrive-sync.log"
ERROR_LOG="/var/log/pipedrive-sync-errors.log"

# Function to log with timestamp
log_with_timestamp() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Function to handle errors
handle_error() {
    local entity=$1
    local exit_code=$2
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: Sync failed for $entity (exit code: $exit_code)" | tee -a "$ERROR_LOG"
    # Send alert (email, Slack, etc.)
    # curl -X POST -H 'Content-type: application/json' \
    #   --data '{"text":"Pipedrive sync failed for '$entity'"}' \
    #   YOUR_SLACK_WEBHOOK_URL
}

log_with_timestamp "Starting monitored Pipedrive sync"

# Sync entities with error handling
entities=("deals" "activities" "persons" "organizations")

for entity in "${entities[@]}"; do
    log_with_timestamp "Syncing $entity..."

    if php artisan pipedrive:sync-entities --entity="$entity" --limit=200 >> "$LOG_FILE" 2>&1; then
        log_with_timestamp "✅ $entity sync completed successfully"
    else
        handle_error "$entity" $?
    fi
done

log_with_timestamp "Monitored sync completed"
```
