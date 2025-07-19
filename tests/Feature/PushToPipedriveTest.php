<?php

namespace Skeylup\LaravelPipedrive\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Skeylup\LaravelPipedrive\Tests\TestCase;
use Skeylup\LaravelPipedrive\Traits\HasPipedriveEntity;
use Skeylup\LaravelPipedrive\Enums\PipedriveEntityType;
use Skeylup\LaravelPipedrive\Models\{PipedriveEntityLink, PipedriveDeal, PipedriveCustomField};

class PushToPipedriveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Run migrations
        $this->artisan('migrate');
    }

    /** @test */
    public function it_can_display_pipedrive_details_without_entity()
    {
        $testModel = TestModelForPush::create(['name' => 'Test Order']);
        
        $details = $testModel->displayPipedriveDetails();
        
        $this->assertFalse($details['has_entity']);
        $this->assertEquals('No primary Pipedrive entity found for this model', $details['error']);
    }

    /** @test */
    public function it_can_display_pipedrive_details_with_entity()
    {
        $testModel = TestModelForPush::create(['name' => 'Test Order']);
        
        // Create a linked Pipedrive deal
        $deal = PipedriveDeal::create([
            'pipedrive_id' => 123,
            'title' => 'Test Deal',
            'value' => 1000,
            'currency' => 'EUR',
            'status' => 'open',
            'stage_id' => 1,
            'user_id' => 1,
            'active_flag' => true,
            'pipedrive_data' => [
                'id' => 123,
                'title' => 'Test Deal',
                'value' => 1000,
                'custom_field_123' => 'Custom Value',
            ],
        ]);
        
        // Link the model to the deal
        $testModel->linkToPipedriveEntity(123, true);
        
        $details = $testModel->displayPipedriveDetails();
        
        $this->assertTrue($details['has_entity']);
        $this->assertEquals('deals', $details['entity_type']);
        $this->assertEquals(123, $details['pipedrive_id']);
        $this->assertEquals($deal->id, $details['local_id']);
        $this->assertArrayHasKey('basic_fields', $details);
        $this->assertArrayHasKey('custom_fields', $details);
        $this->assertArrayHasKey('metadata', $details);
    }

    /** @test */
    public function it_can_display_custom_fields_with_readable_names()
    {
        $testModel = TestModelForPush::create(['name' => 'Test Order']);
        
        // Create a custom field definition
        $customField = PipedriveCustomField::create([
            'pipedrive_id' => 123,
            'name' => 'Order Number',
            'key' => 'custom_field_123',
            'field_type' => 'varchar',
            'entity_type' => 'deal',
            'active_flag' => true,
            'pipedrive_data' => [],
        ]);
        
        // Create a linked Pipedrive deal with custom field data
        $deal = PipedriveDeal::create([
            'pipedrive_id' => 123,
            'title' => 'Test Deal',
            'value' => 1000,
            'currency' => 'EUR',
            'status' => 'open',
            'stage_id' => 1,
            'user_id' => 1,
            'active_flag' => true,
            'pipedrive_data' => [
                'id' => 123,
                'title' => 'Test Deal',
                'value' => 1000,
                'custom_field_123' => 'ORD-001',
            ],
        ]);
        
        // Link the model to the deal
        $testModel->linkToPipedriveEntity(123, true);
        
        $details = $testModel->displayPipedriveDetails();
        
        $this->assertTrue($details['has_entity']);
        $this->assertArrayHasKey('custom_fields', $details);
        $this->assertArrayHasKey('Order Number', $details['custom_fields']);
        
        $orderNumberField = $details['custom_fields']['Order Number'];
        $this->assertEquals('ORD-001', $orderNumberField['value']);
        $this->assertEquals('ORD-001', $orderNumberField['raw_value']);
        $this->assertEquals('varchar', $orderNumberField['field_type']);
        $this->assertEquals('custom_field_123', $orderNumberField['key']);
    }

    /** @test */
    public function it_throws_exception_when_no_primary_entity_for_push()
    {
        $testModel = TestModelForPush::create(['name' => 'Test Order']);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No primary Pipedrive entity found for this model');
        
        $testModel->pushToPipedrive(['title' => 'New Title']);
    }

    /** @test */
    public function it_can_prepare_data_for_pipedrive_with_custom_fields()
    {
        $testModel = TestModelForPush::create(['name' => 'Test Order']);
        
        // Create a custom field definition
        $customField = PipedriveCustomField::create([
            'pipedrive_id' => 123,
            'name' => 'Order Number',
            'key' => 'custom_field_123',
            'field_type' => 'varchar',
            'entity_type' => 'deal',
            'active_flag' => true,
            'pipedrive_data' => [],
        ]);
        
        // Create a linked Pipedrive deal
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
        
        // Link the model to the deal
        $testModel->linkToPipedriveEntity(123, true);
        
        // Use reflection to test the protected method
        $reflection = new \ReflectionClass($testModel);
        $method = $reflection->getMethod('prepareDataForPipedrive');
        $method->setAccessible(true);
        
        $modifications = ['title' => 'New Title', 'value' => 1500];
        $customFields = ['Order Number' => 'ORD-001'];
        
        $result = $method->invoke($testModel, $modifications, $customFields, 'deal');
        
        $this->assertEquals('New Title', $result['title']);
        $this->assertEquals(1500, $result['value']);
        $this->assertEquals('ORD-001', $result['custom_field_123']);
    }

    /** @test */
    public function it_logs_warning_for_unknown_custom_fields()
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('Custom field not found for entity type deal', \Mockery::type('array'));
        
        $testModel = TestModelForPush::create(['name' => 'Test Order']);
        
        // Create a linked Pipedrive deal
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
        
        // Link the model to the deal
        $testModel->linkToPipedriveEntity(123, true);
        
        // Use reflection to test the protected method
        $reflection = new \ReflectionClass($testModel);
        $method = $reflection->getMethod('prepareDataForPipedrive');
        $method->setAccessible(true);
        
        $modifications = [];
        $customFields = ['Unknown Field' => 'Some Value'];
        
        $result = $method->invoke($testModel, $modifications, $customFields, 'deal');
        
        // The unknown field should not be in the result
        $this->assertArrayNotHasKey('Unknown Field', $result);
    }

    /** @test */
    public function it_can_get_basic_fields_display()
    {
        $testModel = TestModelForPush::create(['name' => 'Test Order']);
        
        // Create a linked Pipedrive deal
        $deal = PipedriveDeal::create([
            'pipedrive_id' => 123,
            'title' => 'Test Deal',
            'value' => 1000.50,
            'currency' => 'EUR',
            'status' => 'open',
            'stage_id' => 1,
            'user_id' => 1,
            'active_flag' => true,
            'pipedrive_data' => [],
        ]);
        
        // Use reflection to test the protected method
        $reflection = new \ReflectionClass($testModel);
        $method = $reflection->getMethod('getBasicFieldsDisplay');
        $method->setAccessible(true);
        
        $result = $method->invoke($testModel, $deal);
        
        $this->assertEquals('Test Deal', $result['title']);
        $this->assertEquals(1000.50, $result['value']);
        $this->assertEquals('EUR', $result['currency']);
        $this->assertEquals('open', $result['status']);
        $this->assertEquals('Yes', $result['active_flag']); // Boolean formatted
        
        // Should not include pipedrive_data and pipedrive_id
        $this->assertArrayNotHasKey('pipedrive_data', $result);
        $this->assertArrayNotHasKey('pipedrive_id', $result);
    }
}

// Test model for testing
class TestModelForPush extends Model
{
    use HasPipedriveEntity;
    
    protected $table = 'test_models';
    protected $fillable = ['name'];
    protected PipedriveEntityType $pipedriveEntityType = PipedriveEntityType::DEALS;
    
    public $timestamps = false;
}
