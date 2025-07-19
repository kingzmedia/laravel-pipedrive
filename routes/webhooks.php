<?php

use Illuminate\Support\Facades\Route;
use Skeylup\LaravelPipedrive\Http\Controllers\PipedriveWebhookController;

/*
|--------------------------------------------------------------------------
| Pipedrive Webhook Routes
|--------------------------------------------------------------------------
|
| These routes handle incoming webhooks from Pipedrive for real-time
| data synchronization. The routes are automatically registered by
| the service provider.
|
*/

// Main webhook endpoint
Route::post(
    config('pipedrive.webhooks.route.path', 'pipedrive/webhook'),
    [PipedriveWebhookController::class, 'handle']
)->name(config('pipedrive.webhooks.route.name', 'pipedrive.webhook'));

// Health check endpoint for webhook URL validation
Route::get(
    config('pipedrive.webhooks.route.path', 'pipedrive/webhook') . '/health',
    [PipedriveWebhookController::class, 'health']
)->name('pipedrive.webhook.health');
