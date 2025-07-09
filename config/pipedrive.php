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
];
