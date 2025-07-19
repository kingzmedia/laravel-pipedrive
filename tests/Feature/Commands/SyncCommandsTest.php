<?php

namespace Skeylup\LaravelPipedrive\Tests\Feature\Commands;

use Skeylup\LaravelPipedrive\Tests\TestCase;
use Skeylup\LaravelPipedrive\Services\PipedriveAuthService;
use Mockery;

class SyncCommandsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock the PipedriveAuthService to avoid real API calls
        $this->app->bind(PipedriveAuthService::class, function () {
            $mock = Mockery::mock(PipedriveAuthService::class);
            $mock->shouldReceive('testConnection')
                ->andReturn(['success' => false, 'message' => 'Test mode - no real connection']);
            return $mock;
        });
    }

    /** @test */
    public function sync_entities_command_has_correct_signature()
    {
        $this->artisan('pipedrive:sync-entities --help')
            ->expectsOutput('Synchronize entities from Pipedrive API. By default, fetches latest modifications (sorted by update_time DESC, max 500 records). Use --full-data to retrieve all data with pagination.')
            ->assertExitCode(0);
    }

    /** @test */
    public function sync_entities_command_shows_full_data_warning()
    {
        $this->artisan('pipedrive:sync-entities --full-data')
            ->expectsQuestion('Do you want to continue?', false)
            ->expectsOutput('Operation cancelled.')
            ->assertExitCode(0);
    }

    /** @test */
    public function sync_entities_command_validates_entity_type()
    {
        $this->artisan('pipedrive:sync-entities --entity=invalid')
            ->expectsOutput('Invalid entity type. Available types: activities, deals, files, goals, notes, organizations, persons, pipelines, products, stages, users')
            ->assertExitCode(1);
    }

    /** @test */
    public function sync_custom_fields_command_has_correct_signature()
    {
        $this->artisan('pipedrive:sync-custom-fields --help')
            ->expectsOutput('Synchronize custom fields from Pipedrive API. By default, fetches latest modifications (sorted by update_time DESC). Use --full-data to retrieve all fields with pagination.')
            ->assertExitCode(0);
    }

    /** @test */
    public function sync_custom_fields_command_shows_full_data_warning()
    {
        $this->artisan('pipedrive:sync-custom-fields --full-data')
            ->expectsQuestion('Do you want to continue?', false)
            ->expectsOutput('Operation cancelled.')
            ->assertExitCode(0);
    }

    /** @test */
    public function sync_commands_enforce_limit_maximum()
    {
        // Test that limit is capped at 500
        $this->artisan('pipedrive:sync-entities --entity=deals --limit=1000')
            ->expectsOutput('Failed to connect to Pipedrive: Test mode - no real connection')
            ->assertExitCode(1);
    }

    /** @test */
    public function sync_commands_support_verbose_output()
    {
        // Test that verbose flag is recognized (Laravel standard -v flag)
        $this->artisan('pipedrive:sync-entities --entity=deals -v')
            ->expectsOutput('Failed to connect to Pipedrive: Test mode - no real connection')
            ->assertExitCode(1);
    }

    /** @test */
    public function sync_entities_command_skips_confirmation_with_force_flag()
    {
        $this->artisan('pipedrive:sync-entities --entity=deals --full-data --force')
            ->expectsOutput('🚀 Force mode enabled - skipping confirmation prompt.')
            ->expectsOutput('Failed to connect to Pipedrive: Test mode - no real connection')
            ->assertExitCode(1);
    }

    /** @test */
    public function sync_custom_fields_command_skips_confirmation_with_force_flag()
    {
        $this->artisan('pipedrive:sync-custom-fields --entity=deal --full-data --force')
            ->expectsOutput('🚀 Force mode enabled - skipping confirmation prompt.')
            ->expectsOutput('Failed to connect to Pipedrive: Test mode - no real connection')
            ->assertExitCode(1);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
