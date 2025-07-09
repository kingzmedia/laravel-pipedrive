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
    | Custom Fields Sync Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for custom fields synchronization
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
];
