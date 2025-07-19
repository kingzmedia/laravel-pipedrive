<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Console\Scheduling\Schedule;
use Keggermont\LaravelPipedrive\Jobs\SyncPipedriveCustomFieldsJob;

class CustomFieldSchedulerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_registers_custom_field_scheduler_when_enabled()
    {
        config([
            'pipedrive.sync.scheduler.custom_fields.enabled' => true,
            'pipedrive.sync.scheduler.custom_fields.frequency_hours' => 1,
            'pipedrive.sync.scheduler.custom_fields.force' => true,
        ]);

        $schedule = $this->app->make(Schedule::class);
        
        // Trigger the service provider's boot method
        $this->app->make(\Keggermont\LaravelPipedrive\LaravelPipedriveServiceProvider::class)
            ->packageBooted();

        $events = $schedule->events();
        
        // Find the custom fields sync command in scheduled events
        $customFieldsEvent = collect($events)->first(function ($event) {
            return str_contains($event->command, 'pipedrive:sync-custom-fields');
        });

        $this->assertNotNull($customFieldsEvent, 'Custom fields sync command should be scheduled');
        $this->assertStringContains('--force', $customFieldsEvent->command);
    }

    /** @test */
    public function it_does_not_register_scheduler_when_disabled()
    {
        config([
            'pipedrive.sync.scheduler.custom_fields.enabled' => false,
        ]);

        $schedule = $this->app->make(Schedule::class);
        
        // Trigger the service provider's boot method
        $this->app->make(\Keggermont\LaravelPipedrive\LaravelPipedriveServiceProvider::class)
            ->packageBooted();

        $events = $schedule->events();
        
        // Find the custom fields sync command in scheduled events
        $customFieldsEvent = collect($events)->first(function ($event) {
            return str_contains($event->command, 'pipedrive:sync-custom-fields');
        });

        $this->assertNull($customFieldsEvent, 'Custom fields sync command should not be scheduled when disabled');
    }

    /** @test */
    public function it_configures_hourly_frequency_correctly()
    {
        config([
            'pipedrive.sync.scheduler.custom_fields.enabled' => true,
            'pipedrive.sync.scheduler.custom_fields.frequency_hours' => 1,
        ]);

        $schedule = $this->app->make(Schedule::class);
        
        // Trigger the service provider's boot method
        $this->app->make(\Keggermont\LaravelPipedrive\LaravelPipedriveServiceProvider::class)
            ->packageBooted();

        $events = $schedule->events();
        
        $customFieldsEvent = collect($events)->first(function ($event) {
            return str_contains($event->command, 'pipedrive:sync-custom-fields');
        });

        $this->assertNotNull($customFieldsEvent);
        
        // Check if it's scheduled hourly (cron expression should be '0 * * * *')
        $this->assertEquals('0 * * * *', $customFieldsEvent->expression);
    }

    /** @test */
    public function it_configures_different_frequencies_correctly()
    {
        // Test 2-hour frequency
        config([
            'pipedrive.sync.scheduler.custom_fields.enabled' => true,
            'pipedrive.sync.scheduler.custom_fields.frequency_hours' => 2,
        ]);

        $schedule = $this->app->make(Schedule::class);
        
        // Clear existing events
        $reflection = new \ReflectionClass($schedule);
        $eventsProperty = $reflection->getProperty('events');
        $eventsProperty->setAccessible(true);
        $eventsProperty->setValue($schedule, []);

        // Trigger the service provider's boot method
        $this->app->make(\Keggermont\LaravelPipedrive\LaravelPipedriveServiceProvider::class)
            ->packageBooted();

        $events = $schedule->events();
        
        $customFieldsEvent = collect($events)->first(function ($event) {
            return str_contains($event->command, 'pipedrive:sync-custom-fields');
        });

        $this->assertNotNull($customFieldsEvent);
        
        // Check if it's scheduled every two hours (cron expression should be '0 */2 * * *')
        $this->assertEquals('0 */2 * * *', $customFieldsEvent->expression);
    }

    /** @test */
    public function sync_custom_fields_job_can_be_dispatched()
    {
        Queue::fake();

        // Test job dispatch
        SyncPipedriveCustomFieldsJob::dispatch('deal', true, false);

        Queue::assertPushed(SyncPipedriveCustomFieldsJob::class, function ($job) {
            return $job->entityType === 'deal' && 
                   $job->force === true && 
                   $job->fullData === false;
        });
    }

    /** @test */
    public function sync_custom_fields_job_has_correct_configuration()
    {
        $job = new SyncPipedriveCustomFieldsJob('person', true, false);

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(300, $job->timeout);
        $this->assertContains('pipedrive', $job->tags());
        $this->assertContains('custom-fields', $job->tags());
        $this->assertContains('entity:person', $job->tags());
    }

    /** @test */
    public function sync_custom_fields_job_executes_artisan_command()
    {
        // Mock Artisan to capture the command call
        Artisan::shouldReceive('call')
            ->once()
            ->with('pipedrive:sync-custom-fields', [
                '--entity' => 'deal',
                '--force' => true,
            ])
            ->andReturn(0);

        $job = new SyncPipedriveCustomFieldsJob('deal', true, false);
        $job->handle();

        // If we reach here without exception, the test passes
        $this->assertTrue(true);
    }

    /** @test */
    public function sync_custom_fields_job_handles_command_failure()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Custom fields sync command failed with exit code: 1');

        // Mock Artisan to return failure
        Artisan::shouldReceive('call')
            ->once()
            ->with('pipedrive:sync-custom-fields', [
                '--force' => true,
            ])
            ->andReturn(1);

        $job = new SyncPipedriveCustomFieldsJob(null, true, false);
        $job->handle();
    }

    /** @test */
    public function it_uses_default_configuration_values()
    {
        // Clear config to test defaults
        config([
            'pipedrive.sync.scheduler.custom_fields.enabled' => null,
            'pipedrive.sync.scheduler.custom_fields.frequency_hours' => null,
            'pipedrive.sync.scheduler.custom_fields.force' => null,
        ]);

        // Test that defaults are applied correctly
        $enabled = config('pipedrive.sync.scheduler.custom_fields.enabled', true);
        $frequency = config('pipedrive.sync.scheduler.custom_fields.frequency_hours', 1);
        $force = config('pipedrive.sync.scheduler.custom_fields.force', true);

        $this->assertTrue($enabled);
        $this->assertEquals(1, $frequency);
        $this->assertTrue($force);
    }
}
