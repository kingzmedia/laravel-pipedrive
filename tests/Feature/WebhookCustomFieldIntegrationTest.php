<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use Keggermont\LaravelPipedrive\Jobs\ProcessPipedriveWebhookJob;
use Keggermont\LaravelPipedrive\Jobs\SyncPipedriveCustomFieldsJob;
use Keggermont\LaravelPipedrive\Services\PipedriveCustomFieldDetectionService;
use Keggermont\LaravelPipedrive\Services\PipedriveParsingService;
use Keggermont\LaravelPipedrive\Services\PipedriveErrorHandler;
use Keggermont\LaravelPipedrive\Models\PipedriveCustomField;

class WebhookCustomFieldIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Enable custom field detection
        config(['pipedrive.webhooks.detect_custom_fields' => true]);
    }

    /** @test */
    public function webhook_processing_detects_and_syncs_new_custom_fields()
    {
        Queue::fake();
        Log::fake();

        // Create existing custom field
        PipedriveCustomField::factory()->create([
            'key' => 'abcd1234567890abcd1234567890abcd12345678',
            'entity_type' => 'deal',
            'name' => 'Existing Field',
        ]);

        // Simulate webhook data with new custom field
        $webhookData = [
            'current' => [
                'id' => 123,
                'title' => 'Test Deal',
                'stage_id' => 1,
                'abcd1234567890abcd1234567890abcd12345678' => 'existing value',
                'efgh9876543210efgh9876543210efgh98765432' => 'new field value', // New field
            ],
            'previous' => [
                'id' => 123,
                'title' => 'Test Deal',
                'stage_id' => 1,
                'abcd1234567890abcd1234567890abcd12345678' => 'old value',
            ],
        ];

        // Create and process webhook job
        $job = new ProcessPipedriveWebhookJob(
            $webhookData,
            'updated',
            'deal',
            123
        );

        // Mock the parsing service to avoid actual API calls
        $parsingService = $this->createMock(PipedriveParsingService::class);
        $parsingService->method('processEntityData')->willReturn([
            'synced' => 1,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'processed_items' => [],
        ]);

        $errorHandler = $this->createMock(PipedriveErrorHandler::class);
        $errorHandler->method('recordSuccess');

        $detectionService = $this->app->make(PipedriveCustomFieldDetectionService::class);

        // Execute the job
        $job->handle($parsingService, $errorHandler, $detectionService);

        // Assert that custom field sync job was dispatched
        Queue::assertPushed(SyncPipedriveCustomFieldsJob::class, function ($job) {
            return $job->entityType === 'deal';
        });

        // Assert that detection was logged
        Log::assertLogged('info', function ($message, $context) {
            return str_contains($message, 'Custom field changes detected in webhook') &&
                   $context['entity_type'] === 'deal' &&
                   $context['event_type'] === 'updated';
        });
    }

    /** @test */
    public function webhook_processing_skips_detection_when_disabled()
    {
        Queue::fake();
        Log::fake();

        // Disable custom field detection
        config(['pipedrive.webhooks.detect_custom_fields' => false]);

        $webhookData = [
            'current' => [
                'id' => 123,
                'title' => 'Test Deal',
                'efgh9876543210efgh9876543210efgh98765432' => 'new field value',
            ],
        ];

        $job = new ProcessPipedriveWebhookJob(
            $webhookData,
            'added',
            'deal',
            123
        );

        $parsingService = $this->createMock(PipedriveParsingService::class);
        $parsingService->method('processEntityData')->willReturn([
            'synced' => 1,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'processed_items' => [],
        ]);

        $errorHandler = $this->createMock(PipedriveErrorHandler::class);
        $errorHandler->method('recordSuccess');

        $detectionService = $this->app->make(PipedriveCustomFieldDetectionService::class);

        $job->handle($parsingService, $errorHandler, $detectionService);

        // Assert that no custom field sync job was dispatched
        Queue::assertNotPushed(SyncPipedriveCustomFieldsJob::class);
    }

    /** @test */
    public function webhook_processing_skips_detection_for_unsupported_entities()
    {
        Queue::fake();

        $webhookData = [
            'current' => [
                'id' => 123,
                'name' => 'Test User',
                'efgh9876543210efgh9876543210efgh98765432' => 'custom field value',
            ],
        ];

        $job = new ProcessPipedriveWebhookJob(
            $webhookData,
            'updated',
            'user', // Unsupported entity for custom field detection
            123
        );

        $parsingService = $this->createMock(PipedriveParsingService::class);
        $parsingService->method('processEntityData')->willReturn([
            'synced' => 1,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'processed_items' => [],
        ]);

        $errorHandler = $this->createMock(PipedriveErrorHandler::class);
        $errorHandler->method('recordSuccess');

        $detectionService = $this->app->make(PipedriveCustomFieldDetectionService::class);

        $job->handle($parsingService, $errorHandler, $detectionService);

        // Assert that no custom field sync job was dispatched
        Queue::assertNotPushed(SyncPipedriveCustomFieldsJob::class);
    }

    /** @test */
    public function webhook_processing_skips_detection_for_delete_events()
    {
        Queue::fake();

        $webhookData = [
            'previous' => [
                'id' => 123,
                'title' => 'Deleted Deal',
                'efgh9876543210efgh9876543210efgh98765432' => 'custom field value',
            ],
            'current' => null,
        ];

        $job = new ProcessPipedriveWebhookJob(
            $webhookData,
            'deleted',
            'deal',
            123
        );

        $parsingService = $this->createMock(PipedriveParsingService::class);
        $parsingService->method('processEntityData')->willReturn([
            'synced' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'processed_items' => [],
        ]);

        $errorHandler = $this->createMock(PipedriveErrorHandler::class);
        $errorHandler->method('recordSuccess');

        $detectionService = $this->app->make(PipedriveCustomFieldDetectionService::class);

        $job->handle($parsingService, $errorHandler, $detectionService);

        // Assert that no custom field sync job was dispatched
        Queue::assertNotPushed(SyncPipedriveCustomFieldsJob::class);
    }

    /** @test */
    public function webhook_processing_handles_detection_errors_gracefully()
    {
        Queue::fake();
        Log::fake();

        $webhookData = [
            'current' => [
                'id' => 123,
                'title' => 'Test Deal',
                'efgh9876543210efgh9876543210efgh98765432' => 'new field value',
            ],
        ];

        $job = new ProcessPipedriveWebhookJob(
            $webhookData,
            'added',
            'deal',
            123
        );

        $parsingService = $this->createMock(PipedriveParsingService::class);
        $parsingService->method('processEntityData')->willReturn([
            'synced' => 1,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'processed_items' => [],
        ]);

        $errorHandler = $this->createMock(PipedriveErrorHandler::class);
        $errorHandler->method('recordSuccess');

        // Mock detection service to throw an exception
        $detectionService = $this->createMock(PipedriveCustomFieldDetectionService::class);
        $detectionService->method('isEnabled')->willReturn(true);
        $detectionService->method('getEntityTypeFromWebhookObject')->willReturn('deal');
        $detectionService->method('detectAndSyncCustomFields')
            ->willThrowException(new \Exception('Detection error'));

        // The job should not fail even if detection fails
        $job->handle($parsingService, $errorHandler, $detectionService);

        // Assert that error was logged
        Log::assertLogged('error', function ($message, $context) {
            return str_contains($message, 'Error during custom field detection in webhook') &&
                   $context['entity_type'] === 'deal';
        });

        // Main webhook processing should still succeed
        $this->assertTrue(true); // If we reach here, the job didn't fail
    }
}
