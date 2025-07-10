# Changelog

All notable changes to `laravel-pipedrive` will be documented in this file.

## [Unreleased]

### Added
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
- **Documentation**:
  - New comprehensive [Sync Commands Documentation](docs/commands/sync-commands.md)
  - Detailed usage examples and best practices
  - API limitations and safety guidelines
  - Monitoring and alerting examples
- **Error Handling**: Improved error reporting with stack traces in verbose mode
- **Performance**: Optimized API calls with intelligent pagination and safety limits

### Security
- **Rate Limiting Protection**: Built-in safeguards to prevent API rate limit exhaustion
- **Confirmation Prompts**: Required confirmation for potentially resource-intensive operations

### Technical Details
- **API Compatibility**: Full support for Pipedrive API v1 limitations (100 records) and v2 capabilities (500 records)
- **Pagination Safety**: Maximum page limits to prevent infinite loops (1000 pages for entities, 100 for fields)
- **Memory Optimization**: Efficient handling of large datasets with streaming pagination
