<?php

// config for Keggermont/LaravelPipedrive
return [
    /*
    |--------------------------------------------------------------------------
    | Authentication Method
    |--------------------------------------------------------------------------
    |
    | Choose your authentication method: 'token' or 'oauth'
    |
    | - token: Use API token for authentication (simpler, for manual integrations)
    | - oauth: Use OAuth 2.0 for authentication (for public/private apps)
    |
    */
    'auth_method' => env('PIPEDRIVE_AUTH_METHOD', 'token'),

    /*
    |--------------------------------------------------------------------------
    | API Token Configuration
    |--------------------------------------------------------------------------
    |
    | Your Pipedrive API token. You can find it in your Pipedrive account
    | under Settings > Personal preferences > API
    |
    */
    'token' => env('PIPEDRIVE_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | OAuth Configuration
    |--------------------------------------------------------------------------
    |
    | OAuth 2.0 configuration for Pipedrive apps
    |
    */
    'oauth' => [
        'client_id' => env('PIPEDRIVE_CLIENT_ID'),
        'client_secret' => env('PIPEDRIVE_CLIENT_SECRET'),
        'redirect_url' => env('PIPEDRIVE_REDIRECT_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for data synchronization from Pipedrive API
    |
    */
    'sync' => [
        // Automatically sync custom fields on app boot
        'auto_sync' => env('PIPEDRIVE_AUTO_SYNC', false),

        // Entities to sync (leave empty to sync all)
        'entities' => [
            'deal',
            'person',
            'organization',
            'product',
            'activity',
        ],

        // API rate limiting configuration
        'api' => [
            // Delay between API calls in seconds (default: 0.3s)
            // This helps prevent hitting Pipedrive API rate limits
            'delay' => env('PIPEDRIVE_API_DELAY', 0.3),

            // Enable/disable API delay (useful for testing)
            'delay_enabled' => env('PIPEDRIVE_API_DELAY_ENABLED', true),
        ],

        // Automatic scheduled synchronization
        'scheduler' => [
            // Enable/disable automatic scheduled sync
            'enabled' => env('PIPEDRIVE_SCHEDULER_ENABLED', false),

            // Sync frequency in hours (default: 24 hours)
            'frequency_hours' => env('PIPEDRIVE_SCHEDULER_FREQUENCY', 24),

            // Time of day to run sync (24-hour format, e.g., '02:00')
            // Leave null to run based on frequency from app start
            'time' => env('PIPEDRIVE_SCHEDULER_TIME', '02:00'),

            // Include full data sync (WARNING: This can be resource intensive)
            'full_data' => env('PIPEDRIVE_SCHEDULER_FULL_DATA', true),

            // Force sync (skip confirmations and overwrite existing data)
            'force' => env('PIPEDRIVE_SCHEDULER_FORCE', true),

            // Sync custom fields along with entities
            'sync_custom_fields' => env('PIPEDRIVE_SCHEDULER_SYNC_CUSTOM_FIELDS', true),

            // Memory limit for scheduled sync (in MB, 0 = no limit)
            'memory_limit' => env('PIPEDRIVE_SCHEDULER_MEMORY_LIMIT', 2048),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks Configuration
    |--------------------------------------------------------------------------
    |
    | Configure webhook handling for real-time data synchronization
    |
    */
    'webhooks' => [
        // Enable automatic data synchronization from webhooks
        'auto_sync' => env('PIPEDRIVE_WEBHOOKS_AUTO_SYNC', true),

        // Webhook route configuration
        'route' => [
            'path' => env('PIPEDRIVE_WEBHOOK_PATH', 'pipedrive/webhook'),
            'name' => 'pipedrive.webhook',
            'middleware' => ['api'],
        ],

        // Security configuration
        'security' => [
            // HTTP Basic Authentication
            'basic_auth' => [
                'enabled' => env('PIPEDRIVE_WEBHOOK_BASIC_AUTH', false),
                'username' => env('PIPEDRIVE_WEBHOOK_USERNAME'),
                'password' => env('PIPEDRIVE_WEBHOOK_PASSWORD'),
            ],

            // IP Whitelist (Pipedrive IPs)
            'ip_whitelist' => [
                'enabled' => env('PIPEDRIVE_WEBHOOK_IP_WHITELIST', false),
                'ips' => [
                    // Add Pipedrive webhook IPs here
                    // Example: '185.166.142.0/24',
                ],
            ],

            // Custom signature verification
            'signature' => [
                'enabled' => env('PIPEDRIVE_WEBHOOK_SIGNATURE', false),
                'secret' => env('PIPEDRIVE_WEBHOOK_SECRET'),
                'header' => 'X-Pipedrive-Signature',
            ],
        ],

        // Logging configuration
        'logging' => [
            'enabled' => env('PIPEDRIVE_WEBHOOK_LOGGING', true),
            'channel' => env('PIPEDRIVE_WEBHOOK_LOG_CHANNEL', 'daily'),
            'level' => env('PIPEDRIVE_WEBHOOK_LOG_LEVEL', 'info'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure intelligent caching for Pipedrive data to improve performance.
    | Supports Redis and file-based caching with configurable TTL values.
    |
    */
    'cache' => [
        // Enable/disable caching
        'enabled' => env('PIPEDRIVE_CACHE_ENABLED', true),

        // Cache driver (redis, file, etc.)
        'driver' => env('PIPEDRIVE_CACHE_DRIVER', 'redis'),

        // Time-to-live (TTL) values in seconds for different data types
        'ttl' => [
            // Custom fields cache (1 hour default)
            'custom_fields' => env('PIPEDRIVE_CACHE_CUSTOM_FIELDS_TTL', 3600),

            // Pipelines cache (2 hours default)
            'pipelines' => env('PIPEDRIVE_CACHE_PIPELINES_TTL', 7200),

            // Stages cache (2 hours default)
            'stages' => env('PIPEDRIVE_CACHE_STAGES_TTL', 7200),

            // Users cache (30 minutes default)
            'users' => env('PIPEDRIVE_CACHE_USERS_TTL', 1800),
        ],

        // Automatically refresh cache when data is stale
        'auto_refresh' => env('PIPEDRIVE_CACHE_AUTO_REFRESH', true),

        // Cache key prefix
        'prefix' => env('PIPEDRIVE_CACHE_PREFIX', 'pipedrive'),
    ],
];
