<?php

namespace Skeylup\LaravelPipedrive\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Skeylup\LaravelPipedrive\Models\PipedriveCustomField;
use Skeylup\LaravelPipedrive\Services\PipedriveCustomFieldService;
use Skeylup\LaravelPipedrive\Tests\TestCase;

class PipedriveCustomFieldTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run migrations
        $this->artisan('migrate', ['--database' => 'testing']);
    }

    /** @test */
    public function it_can_create_a_custom_field()
    {
        $field = PipedriveCustomField::create([
            'pipedrive_field_id' => 12345,
            'field_key' => 'dcf558aac1ae4e8c4f849ba5e668430d8df9be12',
            'entity_type' => PipedriveCustomField::ENTITY_DEAL,
            'name' => 'Test Custom Field',
            'field_type' => PipedriveCustomField::TYPE_VARCHAR,
            'order_nr' => 1,
            'mandatory_flag' => true,
            'active_flag' => true,
            'edit_flag' => true,
        ]);

        $this->assertDatabaseHas('pipedrive_custom_fields', [
            'pipedrive_field_id' => 12345,
            'field_key' => 'dcf558aac1ae4e8c4f849ba5e668430d8df9be12',
            'entity_type' => 'deal',
            'name' => 'Test Custom Field',
        ]);

        $this->assertTrue($field->isCustomField());
        $this->assertTrue($field->isMandatory());
    }

    /** @test */
    public function it_can_scope_fields_by_entity()
    {
        PipedriveCustomField::create([
            'pipedrive_field_id' => 1,
            'field_key' => 'deal_field',
            'entity_type' => PipedriveCustomField::ENTITY_DEAL,
            'name' => 'Deal Field',
            'field_type' => PipedriveCustomField::TYPE_VARCHAR,
        ]);

        PipedriveCustomField::create([
            'pipedrive_field_id' => 2,
            'field_key' => 'person_field',
            'entity_type' => PipedriveCustomField::ENTITY_PERSON,
            'name' => 'Person Field',
            'field_type' => PipedriveCustomField::TYPE_VARCHAR,
        ]);

        $dealFields = PipedriveCustomField::forEntity('deal')->get();
        $personFields = PipedriveCustomField::forEntity('person')->get();

        $this->assertCount(1, $dealFields);
        $this->assertCount(1, $personFields);
        $this->assertEquals('Deal Field', $dealFields->first()->name);
        $this->assertEquals('Person Field', $personFields->first()->name);
    }

    /** @test */
    public function it_can_scope_custom_fields_only()
    {
        PipedriveCustomField::create([
            'pipedrive_field_id' => 1,
            'field_key' => 'custom_field',
            'entity_type' => PipedriveCustomField::ENTITY_DEAL,
            'name' => 'Custom Field',
            'field_type' => PipedriveCustomField::TYPE_VARCHAR,
            'edit_flag' => true,
        ]);

        PipedriveCustomField::create([
            'pipedrive_field_id' => 2,
            'field_key' => 'default_field',
            'entity_type' => PipedriveCustomField::ENTITY_DEAL,
            'name' => 'Default Field',
            'field_type' => PipedriveCustomField::TYPE_VARCHAR,
            'edit_flag' => false,
        ]);

        $customFields = PipedriveCustomField::forEntity('deal')->customOnly()->get();

        $this->assertCount(1, $customFields);
        $this->assertEquals('Custom Field', $customFields->first()->name);
    }

    /** @test */
    public function it_can_handle_option_fields()
    {
        $field = PipedriveCustomField::create([
            'pipedrive_field_id' => 1,
            'field_key' => 'enum_field',
            'entity_type' => PipedriveCustomField::ENTITY_DEAL,
            'name' => 'Enum Field',
            'field_type' => PipedriveCustomField::TYPE_ENUM,
            'options' => [
                ['id' => 1, 'label' => 'Option 1'],
                ['id' => 2, 'label' => 'Option 2'],
            ],
        ]);

        $this->assertTrue($field->hasOptions());
        $this->assertCount(2, $field->getOptions());
        $this->assertEquals('Option 1', $field->getOptions()[0]['label']);
    }

    /** @test */
    public function it_can_create_from_pipedrive_data()
    {
        $pipedriveData = [
            'id' => 12345,
            'key' => 'dcf558aac1ae4e8c4f849ba5e668430d8df9be12',
            'name' => 'Test Field',
            'field_type' => 'varchar',
            'order_nr' => 1,
            'mandatory_flag' => true,
            'active_flag' => true,
            'edit_flag' => true,
            'add_time' => '2023-01-01 12:00:00',
            'update_time' => '2023-01-02 12:00:00',
        ];

        $field = PipedriveCustomField::createOrUpdateFromPipedriveData($pipedriveData, 'deal');

        $this->assertEquals(12345, $field->pipedrive_field_id);
        $this->assertEquals('dcf558aac1ae4e8c4f849ba5e668430d8df9be12', $field->field_key);
        $this->assertEquals('Test Field', $field->name);
        $this->assertEquals('deal', $field->entity_type);
        $this->assertTrue($field->wasRecentlyCreated);
    }

    /** @test */
    public function service_can_get_fields_for_entity()
    {
        $service = app(PipedriveCustomFieldService::class);

        PipedriveCustomField::create([
            'pipedrive_field_id' => 1,
            'field_key' => 'field1',
            'entity_type' => PipedriveCustomField::ENTITY_DEAL,
            'name' => 'Field 1',
            'field_type' => PipedriveCustomField::TYPE_VARCHAR,
            'order_nr' => 1,
            'active_flag' => true,
        ]);

        PipedriveCustomField::create([
            'pipedrive_field_id' => 2,
            'field_key' => 'field2',
            'entity_type' => PipedriveCustomField::ENTITY_DEAL,
            'name' => 'Field 2',
            'field_type' => PipedriveCustomField::TYPE_VARCHAR,
            'order_nr' => 2,
            'active_flag' => false,
        ]);

        $activeFields = $service->getFieldsForEntity('deal', true);
        $allFields = $service->getFieldsForEntity('deal', false);

        $this->assertCount(1, $activeFields);
        $this->assertCount(2, $allFields);
    }

    /** @test */
    public function service_can_find_field_by_key()
    {
        $service = app(PipedriveCustomFieldService::class);

        $field = PipedriveCustomField::create([
            'pipedrive_field_id' => 1,
            'field_key' => 'test_key',
            'entity_type' => PipedriveCustomField::ENTITY_DEAL,
            'name' => 'Test Field',
            'field_type' => PipedriveCustomField::TYPE_VARCHAR,
        ]);

        $foundField = $service->findByKey('test_key', 'deal');

        $this->assertNotNull($foundField);
        $this->assertEquals($field->id, $foundField->id);
    }

    /** @test */
    public function service_can_validate_field_values()
    {
        $service = app(PipedriveCustomFieldService::class);

        // Test mandatory field validation
        $mandatoryField = PipedriveCustomField::create([
            'pipedrive_field_id' => 1,
            'field_key' => 'mandatory_field',
            'entity_type' => PipedriveCustomField::ENTITY_DEAL,
            'name' => 'Mandatory Field',
            'field_type' => PipedriveCustomField::TYPE_VARCHAR,
            'mandatory_flag' => true,
        ]);

        $errors = $service->validateFieldValue($mandatoryField, '');
        $this->assertNotEmpty($errors);
        $this->assertStringContains('mandatory', $errors[0]);

        $errors = $service->validateFieldValue($mandatoryField, 'valid value');
        $this->assertEmpty($errors);

        // Test varchar length validation
        $longValue = str_repeat('a', 300);
        $errors = $service->validateFieldValue($mandatoryField, $longValue);
        $this->assertNotEmpty($errors);
        $this->assertStringContains('255 characters', $errors[0]);

        // Test numeric field validation
        $numericField = PipedriveCustomField::create([
            'pipedrive_field_id' => 2,
            'field_key' => 'numeric_field',
            'entity_type' => PipedriveCustomField::ENTITY_DEAL,
            'name' => 'Numeric Field',
            'field_type' => PipedriveCustomField::TYPE_DOUBLE,
        ]);

        $errors = $service->validateFieldValue($numericField, 'not a number');
        $this->assertNotEmpty($errors);
        $this->assertStringContains('numeric', $errors[0]);

        $errors = $service->validateFieldValue($numericField, '123.45');
        $this->assertEmpty($errors);
    }

    /** @test */
    public function service_can_format_field_values()
    {
        $service = app(PipedriveCustomFieldService::class);

        // Test monetary formatting
        $monetaryField = PipedriveCustomField::create([
            'pipedrive_field_id' => 1,
            'field_key' => 'monetary_field',
            'entity_type' => PipedriveCustomField::ENTITY_DEAL,
            'name' => 'Monetary Field',
            'field_type' => PipedriveCustomField::TYPE_MONETARY,
        ]);

        $formatted = $service->formatFieldValue($monetaryField, [
            'amount' => 1500.50,
            'currency' => 'EUR',
        ]);
        $this->assertEquals('1,500.50 EUR', $formatted);

        // Test date formatting
        $dateField = PipedriveCustomField::create([
            'pipedrive_field_id' => 2,
            'field_key' => 'date_field',
            'entity_type' => PipedriveCustomField::ENTITY_DEAL,
            'name' => 'Date Field',
            'field_type' => PipedriveCustomField::TYPE_DATE,
        ]);

        $formatted = $service->formatFieldValue($dateField, '2023-12-25');
        $this->assertEquals('2023-12-25', $formatted);
    }

    /** @test */
    public function service_can_get_field_statistics()
    {
        $service = app(PipedriveCustomFieldService::class);

        PipedriveCustomField::create([
            'pipedrive_field_id' => 1,
            'field_key' => 'field1',
            'entity_type' => PipedriveCustomField::ENTITY_DEAL,
            'name' => 'Field 1',
            'field_type' => PipedriveCustomField::TYPE_VARCHAR,
            'active_flag' => true,
            'edit_flag' => true,
            'mandatory_flag' => true,
        ]);

        PipedriveCustomField::create([
            'pipedrive_field_id' => 2,
            'field_key' => 'field2',
            'entity_type' => PipedriveCustomField::ENTITY_DEAL,
            'name' => 'Field 2',
            'field_type' => PipedriveCustomField::TYPE_ENUM,
            'active_flag' => true,
            'edit_flag' => false,
            'mandatory_flag' => false,
        ]);

        $stats = $service->getFieldStatistics('deal');

        $this->assertEquals(2, $stats['total']);
        $this->assertEquals(2, $stats['active']);
        $this->assertEquals(1, $stats['custom']);
        $this->assertEquals(1, $stats['mandatory']);
        $this->assertEquals(1, $stats['by_type']['varchar']);
        $this->assertEquals(1, $stats['by_type']['enum']);
    }
}
