<?php

namespace Keggermont\LaravelPipedrive;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Keggermont\LaravelPipedrive\Commands\LaravelPipedriveCommand;
use Keggermont\LaravelPipedrive\Commands\SyncPipedriveCustomFieldsCommand;
use Keggermont\LaravelPipedrive\Commands\SyncPipedriveEntitiesCommand;
use Keggermont\LaravelPipedrive\Commands\TestPipedriveConnectionCommand;
use Keggermont\LaravelPipedrive\Services\PipedriveCustomFieldService;
use Keggermont\LaravelPipedrive\Services\PipedriveAuthService;
use Keggermont\LaravelPipedrive\Services\DatabaseTokenStorage;
use Keggermont\LaravelPipedrive\Contracts\PipedriveTokenStorageInterface;

class LaravelPipedriveServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-pipedrive')
            ->hasConfigFile() 
            ->hasMigrations([
                'create_pipedrive_custom_fields_table',
                'create_pipedrive_activities_table',
                'create_pipedrive_deals_table',
                'create_pipedrive_files_table',
                'create_pipedrive_goals_table',
                'create_pipedrive_notes_table',
                'create_pipedrive_organizations_table',
                'create_pipedrive_persons_table',
                'create_pipedrive_pipelines_table',
                'create_pipedrive_products_table',
                'create_pipedrive_stages_table',
                'create_pipedrive_users_table',
            ])
            ->hasCommands([
                LaravelPipedriveCommand::class,
                SyncPipedriveCustomFieldsCommand::class,
                SyncPipedriveEntitiesCommand::class,
                TestPipedriveConnectionCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(PipedriveCustomFieldService::class);
        $this->app->singleton(PipedriveAuthService::class);

        // Bind the token storage interface to the default implementation
        $this->app->bind(PipedriveTokenStorageInterface::class, DatabaseTokenStorage::class);
    }
}
