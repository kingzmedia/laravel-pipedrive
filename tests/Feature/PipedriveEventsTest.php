<?php

namespace Skeylup\LaravelPipedrive\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Skeylup\LaravelPipedrive\Events\PipedriveEntityCreated;
use Skeylup\LaravelPipedrive\Events\PipedriveEntityDeleted;
use Skeylup\LaravelPipedrive\Events\PipedriveEntityUpdated;
use Skeylup\LaravelPipedrive\Models\PipedriveDeal;
use Skeylup\LaravelPipedrive\Services\PipedriveWebhookService;
use Skeylup\LaravelPipedrive\Tests\TestCase;

class PipedriveEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run migrations
        $this->artisan('migrate');

        // Fake events
        Event::fake();
    }

    /** @test */
    public function it_emits_created_event_on_webhook_create()
    {
        $webhookService = app(PipedriveWebhookService::class);

        $webhookData = [
            'meta' => [
                'action' => 'added',
                'object' => 'deal',
                'id' => 123,
                'user_id' => 1,
                'company_id' => 1,
            ],
            'current' => [
                'id' => 123,
                'title' => 'Test Deal',
                'value' => 1000,
                'currency' => 'EUR',
                'status' => 'open',
                'stage_id' => 1,
                'user_id' => 1,
                'add_time' => '2024-01-01 10:00:00',
                'update_time' => '2024-01-01 10:00:00',
            ],
        ];

        $result = $webhookService->processWebhook($webhookData);

        $this->assertTrue($result['processed']);
        $this->assertEquals('created', $result['action']);

        // Verify the event was emitted
        Event::assertDispatched(PipedriveEntityCreated::class, function ($event) {
            return $event->entityType === 'deal'
                && $event->entity instanceof PipedriveDeal
                && $event->getPipedriveId() === 123
                && $event->source === 'webhook';
        });
    }

    /** @test */
    public function it_emits_updated_event_on_webhook_update()
    {
        // First create a deal
        $deal = PipedriveDeal::create([
            'pipedrive_id' => 123,
            'title' => 'Test Deal',
            'value' => 1000,
            'currency' => 'EUR',
            'status' => 'open',
            'stage_id' => 1,
            'user_id' => 1,
            'active_flag' => true,
            'pipedrive_data' => [],
        ]);

        $webhookService = app(PipedriveWebhookService::class);

        $webhookData = [
            'meta' => [
                'action' => 'updated',
                'object' => 'deal',
                'id' => 123,
                'user_id' => 1,
                'company_id' => 1,
            ],
            'current' => [
                'id' => 123,
                'title' => 'Updated Test Deal',
                'value' => 1500,
                'currency' => 'EUR',
                'status' => 'won',
                'stage_id' => 2,
                'user_id' => 1,
                'add_time' => '2024-01-01 10:00:00',
                'update_time' => '2024-01-01 11:00:00',
            ],
            'previous' => [
                'id' => 123,
                'title' => 'Test Deal',
                'value' => 1000,
                'status' => 'open',
                'stage_id' => 1,
            ],
        ];

        $result = $webhookService->processWebhook($webhookData);

        $this->assertTrue($result['processed']);
        $this->assertEquals('updated', $result['action']);

        // Verify the event was emitted
        Event::assertDispatched(PipedriveEntityUpdated::class, function ($event) {
            return $event->entityType === 'deal'
                && $event->entity instanceof PipedriveDeal
                && $event->getPipedriveId() === 123
                && $event->source === 'webhook'
                && $event->hasChanged('title')
                && $event->hasChanged('value')
                && $event->hasChanged('status');
        });
    }

    /** @test */
    public function it_emits_deleted_event_on_webhook_delete()
    {
        // First create a deal
        $deal = PipedriveDeal::create([
            'pipedrive_id' => 123,
            'title' => 'Test Deal',
            'value' => 1000,
            'currency' => 'EUR',
            'status' => 'open',
            'stage_id' => 1,
            'user_id' => 1,
            'active_flag' => true,
            'pipedrive_data' => [],
        ]);

        $webhookService = app(PipedriveWebhookService::class);

        $webhookData = [
            'meta' => [
                'action' => 'deleted',
                'object' => 'deal',
                'id' => 123,
                'user_id' => 1,
                'company_id' => 1,
            ],
            'previous' => [
                'id' => 123,
                'title' => 'Test Deal',
                'value' => 1000,
                'currency' => 'EUR',
                'status' => 'open',
                'stage_id' => 1,
                'user_id' => 1,
            ],
        ];

        $result = $webhookService->processWebhook($webhookData);

        $this->assertTrue($result['processed']);
        $this->assertEquals('deleted', $result['action']);

        // Verify the event was emitted
        Event::assertDispatched(PipedriveEntityDeleted::class, function ($event) {
            return $event->entityType === 'deal'
                && $event->pipedriveId === 123
                && $event->source === 'webhook'
                && $event->getEntityTitle() === 'Test Deal'
                && $event->getDeletedValue() === 1000;
        });

        // Verify the deal was deleted from database
        $this->assertDatabaseMissing('pipedrive_deals', ['pipedrive_id' => 123]);
    }

    /** @test */
    public function event_includes_correct_metadata()
    {
        $webhookService = app(PipedriveWebhookService::class);

        $webhookData = [
            'meta' => [
                'action' => 'added',
                'object' => 'deal',
                'id' => 123,
                'user_id' => 42,
                'company_id' => 1,
                'change_source' => 'app',
                'is_bulk_update' => false,
            ],
            'current' => [
                'id' => 123,
                'title' => 'Test Deal',
                'value' => 1000,
                'currency' => 'EUR',
                'status' => 'open',
                'stage_id' => 1,
                'user_id' => 1,
                'add_time' => '2024-01-01 10:00:00',
                'update_time' => '2024-01-01 10:00:00',
            ],
        ];

        $webhookService->processWebhook($webhookData);

        Event::assertDispatched(PipedriveEntityCreated::class, function ($event) {
            return $event->getMetadata('webhook_action') === 'added'
                && $event->getMetadata('user_id') === 42
                && $event->getMetadata('change_source') === 'app'
                && $event->getMetadata('is_bulk_update') === false;
        });
    }

    /** @test */
    public function event_helper_methods_work_correctly()
    {
        $webhookService = app(PipedriveWebhookService::class);

        $webhookData = [
            'meta' => [
                'action' => 'added',
                'object' => 'deal',
                'id' => 123,
            ],
            'current' => [
                'id' => 123,
                'title' => 'Test Deal',
                'value' => 1000,
                'currency' => 'EUR',
                'status' => 'open',
                'stage_id' => 1,
                'user_id' => 1,
                'add_time' => '2024-01-01 10:00:00',
                'update_time' => '2024-01-01 10:00:00',
            ],
        ];

        $webhookService->processWebhook($webhookData);

        Event::assertDispatched(PipedriveEntityCreated::class, function ($event) {
            return $event->isDeal() === true
                && $event->isPerson() === false
                && $event->isFromWebhook() === true
                && $event->isFromSync() === false
                && $event->getEntityName() === 'deal'
                && $event->getModelClass() === PipedriveDeal::class;
        });
    }
}
