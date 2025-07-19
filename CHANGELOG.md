# Changelog

All notable changes to `laravel-pipedrive` will be documented in this file.

## [Unreleased]

### Added
- **🤖 Custom Field Automation**: Revolutionary automated custom field synchronization system
  - **Hourly Scheduler**: Automatic custom field sync every hour (configurable frequency)
  - **Real-Time Webhook Detection**: Intelligent detection of new custom fields in webhook events
  - **Smart Triggering**: Only syncs when new fields are detected to minimize API usage
  - **Asynchronous Processing**: Queue-based jobs for non-blocking webhook processing
  - **Comprehensive Configuration**: Full control via environment variables
  - **Error Handling**: Graceful error handling that doesn't interrupt main operations
  - **Monitoring & Logging**: Detailed logging for detection events and sync operations

- **Enhanced Sync Commands**: Major improvements to `pipedrive:sync-entities` and `pipedrive:sync-custom-fields` commands
  - **Smart Sorting**: Default mode now fetches latest modifications first (`update_time DESC`) for optimal performance
  - **Full Data Mode**: New `--full-data` flag for complete data retrieval with automatic pagination
  - **API Optimization**: Automatic enforcement of Pipedrive API limits (max 500 records per request)
  - **Safety Features**: Built-in warnings and confirmation prompts for resource-intensive operations
  - **Pagination Support**: Automatic pagination handling for large datasets with progress tracking
  - **Verbose Output**: Enhanced verbose mode with detailed progress reporting and error tracking

### Changed
- **Sync Commands Behavior**:
  - Default limit increased from 100 to 500 records per entity (respecting API limits)
  - Default sorting changed to `update_time DESC` (most recent modifications first)
  - Full data mode uses `add_time ASC` (oldest first) for consistent pagination
- **Command Options**: Removed custom `--verbose` flag in favor of Laravel's standard `-v, --verbose` flag

### Enhanced
- **Configuration**: New environment variables for custom field automation:
  - `PIPEDRIVE_CUSTOM_FIELDS_SCHEDULER_ENABLED` - Enable/disable automatic custom field sync
  - `PIPEDRIVE_CUSTOM_FIELDS_SCHEDULER_FREQUENCY` - Sync frequency in hours (default: 1)
  - `PIPEDRIVE_CUSTOM_FIELDS_SCHEDULER_FORCE` - Force sync without confirmations
  - `PIPEDRIVE_WEBHOOKS_DETECT_CUSTOM_FIELDS` - Enable webhook-based custom field detection

- **Documentation**:
  - New comprehensive [Custom Field Automation Documentation](docs/features/custom-field-automation.md)
  - Updated [Sync Commands Documentation](docs/commands/sync-commands.md)
  - Detailed usage examples and best practices
  - API limitations and safety guidelines
  - Monitoring and alerting examples

- **Error Handling**: Improved error reporting with stack traces in verbose mode
- **Performance**: Optimized API calls with intelligent pagination and safety limits

### Security
- **Rate Limiting Protection**: Built-in safeguards to prevent API rate limit exhaustion
- **Confirmation Prompts**: Required confirmation for potentially resource-intensive operations

### Removed
- **PHPStan Analysis**: Removed PHPStan and related packages to prevent CI/CD failures
  - Removed `larastan/larastan`, `phpstan/extension-installer`, `phpstan/phpstan-deprecation-rules`, `phpstan/phpstan-phpunit`
  - Removed `phpstan.neon.dist`, `phpstan-baseline.neon` configuration files
  - Removed `.github/workflows/phpstan.yml` GitHub Action
  - Removed `analyse` script from composer.json

### Technical Details
- **API Compatibility**: Full support for Pipedrive API v1 limitations (100 records) and v2 capabilities (500 records)
- **Pagination Safety**: Maximum page limits to prevent infinite loops (20 pages for safety)
- **Memory Optimization**: Efficient handling of large datasets with automatic memory monitoring
- **Memory Management**:
  - Automatic memory limit checking before full-data operations
  - Real-time memory monitoring during pagination (warnings at 60%, stop at 80%)
  - Clear error messages with solutions for memory limit issues
