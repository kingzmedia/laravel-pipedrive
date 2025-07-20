<?php

use Illuminate\Support\Facades\Route;
use Skeylup\LaravelPipedrive\Http\Controllers\PipedriveOAuthController;
use Skeylup\LaravelPipedrive\Http\Middleware\AuthorizePipedrive;

/*
|--------------------------------------------------------------------------
| Pipedrive OAuth Routes
|--------------------------------------------------------------------------
|
| These routes handle OAuth 2.0 authentication flow with Pipedrive.
| They provide a complete web interface for connecting to Pipedrive
| using OAuth instead of API tokens.
|
*/

// OAuth authorization flow
Route::get('/pipedrive/oauth/authorize', [PipedriveOAuthController::class, 'authorize'])
    ->middleware(['web', AuthorizePipedrive::class])
    ->name('pipedrive.oauth.authorize');

Route::get('/pipedrive/oauth/callback', [PipedriveOAuthController::class, 'callback'])
    ->middleware(['web'])
    ->name('pipedrive.oauth.callback');

// OAuth management
Route::get('/pipedrive/oauth/status', [PipedriveOAuthController::class, 'status'])
    ->middleware(['web', AuthorizePipedrive::class])
    ->name('pipedrive.oauth.status');

Route::get('/pipedrive/oauth/disconnect', [PipedriveOAuthController::class, 'disconnect'])
    ->middleware(['web', AuthorizePipedrive::class])
    ->name('pipedrive.oauth.disconnect');
