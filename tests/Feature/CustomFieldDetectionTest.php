<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Skeylup\LaravelPipedrive\Services\PipedriveCustomFieldDetectionService;
use Skeylup\LaravelPipedrive\Services\PipedriveCustomFieldService;
use Skeylup\LaravelPipedrive\Jobs\SyncPipedriveCustomFieldsJob;
use Skeylup\LaravelPipedrive\Models\PipedriveCustomField;

class CustomFieldDetectionTest extends TestCase
{
    use RefreshDatabase;

    protected PipedriveCustomFieldDetectionService $detectionService;
    protected PipedriveCustomFieldService $customFieldService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->customFieldService = $this->app->make(PipedriveCustomFieldService::class);
        $this->detectionService = $this->app->make(PipedriveCustomFieldDetectionService::class);
    }

    /** @test */
    public function it_detects_new_custom_fields_in_added_events()
    {
        Queue::fake();

        // Create some existing custom fields
        PipedriveCustomField::factory()->create([
            'key' => 'abcd1234567890abcd1234567890abcd12345678',
            'entity_type' => 'deal',
            'name' => 'Existing Field',
        ]);

        // Simulate webhook data with new custom field
        $currentData = [
            'id' => 123,
            'title' => 'Test Deal',
            'abcd1234567890abcd1234567890abcd12345678' => 'existing value',
            'efgh9876543210efgh9876543210efgh98765432' => 'new field value', // New field
        ];

        $result = $this->detectionService->detectAndSyncCustomFields(
            'deal',
            $currentData,
            null,
            'added'
        );

        $this->assertTrue($result['detected_changes']);
        $this->assertTrue($result['sync_triggered']);
        $this->assertContains('efgh9876543210efgh9876543210efgh98765432', $result['new_fields']);
        $this->assertStringContains('New custom fields detected', $result['reason']);

        Queue::assertPushed(SyncPipedriveCustomFieldsJob::class);
    }

    /** @test */
    public function it_detects_new_custom_fields_in_updated_events()
    {
        Queue::fake();

        // Create existing custom field
        PipedriveCustomField::factory()->create([
            'key' => 'abcd1234567890abcd1234567890abcd12345678',
            'entity_type' => 'deal',
            'name' => 'Existing Field',
        ]);

        $previousData = [
            'id' => 123,
            'title' => 'Test Deal',
            'abcd1234567890abcd1234567890abcd12345678' => 'old value',
        ];

        $currentData = [
            'id' => 123,
            'title' => 'Test Deal Updated',
            'abcd1234567890abcd1234567890abcd12345678' => 'updated value',
            'ijkl5555555555ijkl5555555555ijkl55555555' => 'brand new field', // New field
        ];

        $result = $this->detectionService->detectAndSyncCustomFields(
            'deal',
            $currentData,
            $previousData,
            'updated'
        );

        $this->assertTrue($result['detected_changes']);
        $this->assertTrue($result['sync_triggered']);
        $this->assertContains('ijkl5555555555ijkl5555555555ijkl55555555', $result['new_fields']);

        Queue::assertPushed(SyncPipedriveCustomFieldsJob::class);
    }

    /** @test */
    public function it_does_not_trigger_sync_for_known_custom_fields()
    {
        Queue::fake();

        // Create existing custom fields
        PipedriveCustomField::factory()->create([
            'key' => 'abcd1234567890abcd1234567890abcd12345678',
            'entity_type' => 'deal',
            'name' => 'Known Field 1',
        ]);

        PipedriveCustomField::factory()->create([
            'key' => 'efgh9876543210efgh9876543210efgh98765432',
            'entity_type' => 'deal',
            'name' => 'Known Field 2',
        ]);

        $currentData = [
            'id' => 123,
            'title' => 'Test Deal',
            'abcd1234567890abcd1234567890abcd12345678' => 'value 1',
            'efgh9876543210efgh9876543210efgh98765432' => 'value 2',
        ];

        $result = $this->detectionService->detectAndSyncCustomFields(
            'deal',
            $currentData,
            null,
            'added'
        );

        $this->assertFalse($result['detected_changes']);
        $this->assertFalse($result['sync_triggered']);
        $this->assertEmpty($result['new_fields']);
        $this->assertEquals('All custom fields are known', $result['reason']);

        Queue::assertNotPushed(SyncPipedriveCustomFieldsJob::class);
    }

    /** @test */
    public function it_extracts_custom_fields_correctly()
    {
        $entityData = [
            'id' => 123,
            'title' => 'Regular Field',
            'stage_id' => 456,
            'abcd1234567890abcd1234567890abcd12345678' => 'custom field 1',
            'efgh9876543210efgh9876543210efgh98765432' => 'custom field 2',
            'short_key' => 'not a custom field',
            'way_too_long_key_that_exceeds_40_characters_limit' => 'not a custom field',
        ];

        $reflection = new \ReflectionClass($this->detectionService);
        $method = $reflection->getMethod('extractCustomFields');
        $method->setAccessible(true);

        $customFields = $method->invoke($this->detectionService, $entityData);

        $this->assertCount(2, $customFields);
        $this->assertArrayHasKey('abcd1234567890abcd1234567890abcd12345678', $customFields);
        $this->assertArrayHasKey('efgh9876543210efgh9876543210efgh98765432', $customFields);
        $this->assertEquals('custom field 1', $customFields['abcd1234567890abcd1234567890abcd12345678']);
        $this->assertEquals('custom field 2', $customFields['efgh9876543210efgh9876543210efgh98765432']);
    }

    /** @test */
    public function it_handles_entity_type_mapping_correctly()
    {
        $this->assertEquals('deal', $this->detectionService->getEntityTypeFromWebhookObject('deal'));
        $this->assertEquals('person', $this->detectionService->getEntityTypeFromWebhookObject('person'));
        $this->assertEquals('organization', $this->detectionService->getEntityTypeFromWebhookObject('organization'));
        $this->assertNull($this->detectionService->getEntityTypeFromWebhookObject('unsupported'));
    }

    /** @test */
    public function it_respects_detection_enabled_configuration()
    {
        // Test when detection is enabled
        config(['pipedrive.webhooks.detect_custom_fields' => true]);
        $this->assertTrue($this->detectionService->isEnabled());

        // Test when detection is disabled
        config(['pipedrive.webhooks.detect_custom_fields' => false]);
        $this->assertFalse($this->detectionService->isEnabled());
    }

    /** @test */
    public function it_handles_errors_gracefully()
    {
        Log::fake();

        // Mock the custom field service to throw an exception
        $mockService = $this->createMock(PipedriveCustomFieldService::class);
        $mockService->method('getFieldsForEntity')->willThrowException(new \Exception('Database error'));

        $detectionService = new PipedriveCustomFieldDetectionService($mockService);

        $result = $detectionService->detectAndSyncCustomFields(
            'deal',
            ['id' => 123, 'abcd1234567890abcd1234567890abcd12345678' => 'value'],
            null,
            'added'
        );

        $this->assertFalse($result['detected_changes']);
        $this->assertFalse($result['sync_triggered']);
        $this->assertStringContains('Error during detection', $result['reason']);

        Log::assertLogged('error');
    }
}
