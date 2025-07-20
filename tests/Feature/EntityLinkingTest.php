<?php

namespace Skeylup\LaravelPipedrive\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Skeylup\LaravelPipedrive\Enums\PipedriveEntityType;
use Skeylup\LaravelPipedrive\Models\PipedriveEntityLink;
use Skeylup\LaravelPipedrive\Tests\TestCase;
use Skeylup\LaravelPipedrive\Traits\HasPipedriveEntity;

class EntityLinkingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run migrations
        $this->artisan('migrate');
    }

    /** @test */
    public function it_can_link_model_to_default_entity_type()
    {
        $testModel = TestModelWithDeals::create(['name' => 'Test Order']);

        // Link to deal using default entity type
        $link = $testModel->linkToPipedriveEntity(123, true, ['source' => 'test']);

        $this->assertInstanceOf(PipedriveEntityLink::class, $link);
        $this->assertEquals('deals', $link->pipedrive_entity_type);
        $this->assertEquals(123, $link->pipedrive_entity_id);
        $this->assertTrue($link->is_primary);
        $this->assertEquals('test', $link->getMetadata('source'));
    }

    /** @test */
    public function it_can_link_model_to_specific_entity_type()
    {
        $testModel = TestModelWithDeals::create(['name' => 'Test Order']);

        // Link to person (different from default)
        $link = $testModel->linkToSpecificPipedriveEntity('persons', 456, false, ['role' => 'customer']);

        $this->assertEquals('persons', $link->pipedrive_entity_type);
        $this->assertEquals(456, $link->pipedrive_entity_id);
        $this->assertFalse($link->is_primary);
        $this->assertEquals('customer', $link->getMetadata('role'));
    }

    /** @test */
    public function it_can_check_if_linked_to_entity()
    {
        $testModel = TestModelWithDeals::create(['name' => 'Test Order']);

        $testModel->linkToPipedriveEntity(123);

        $this->assertTrue($testModel->isLinkedToPipedriveEntity(123));
        $this->assertFalse($testModel->isLinkedToPipedriveEntity(999));
    }

    /** @test */
    public function it_can_unlink_from_entity()
    {
        $testModel = TestModelWithDeals::create(['name' => 'Test Order']);

        $testModel->linkToPipedriveEntity(123);
        $this->assertTrue($testModel->isLinkedToPipedriveEntity(123));

        $testModel->unlinkFromPipedriveEntity(123);
        $this->assertFalse($testModel->isLinkedToPipedriveEntity(123));
    }

    /** @test */
    public function it_can_get_default_entity_type()
    {
        $testModel = TestModelWithDeals::create(['name' => 'Test Order']);

        $this->assertEquals(PipedriveEntityType::DEALS, $testModel->getDefaultPipedriveEntityType());
        $this->assertEquals('deals', $testModel->getDefaultPipedriveEntityTypeString());
    }

    /** @test */
    public function it_can_handle_multiple_links()
    {
        $testModel = TestModelWithDeals::create(['name' => 'Test Order']);

        // Link to deal (primary)
        $testModel->linkToPipedriveEntity(123, true);

        // Link to person (secondary)
        $testModel->linkToPipedrivePerson(456, false);

        // Link to organization (secondary)
        $testModel->linkToPipedriveOrganization(789, false);

        $this->assertEquals(3, $testModel->pipedriveEntityLinks()->count());
        $this->assertEquals(1, $testModel->pipedriveEntityLinks()->primary()->count());
    }

    /** @test */
    public function it_auto_suggests_entity_type_based_on_model_name()
    {
        $orderModel = TestOrderModel::create(['name' => 'Test']);
        $customerModel = TestCustomerModel::create(['name' => 'Test']);
        $companyModel = TestCompanyModel::create(['name' => 'Test']);

        $this->assertEquals('deals', $orderModel->getDefaultPipedriveEntityTypeString());
        $this->assertEquals('persons', $customerModel->getDefaultPipedriveEntityTypeString());
        $this->assertEquals('organizations', $companyModel->getDefaultPipedriveEntityTypeString());
    }
}

// Test models for testing
class TestModelWithDeals extends Model
{
    use HasPipedriveEntity;

    protected $table = 'test_models';

    protected $fillable = ['name'];

    protected PipedriveEntityType $pipedriveEntityType = PipedriveEntityType::DEALS;

    public $timestamps = false;
}

class TestOrderModel extends Model
{
    use HasPipedriveEntity;

    protected $table = 'test_models';

    protected $fillable = ['name'];

    public $timestamps = false;
}

class TestCustomerModel extends Model
{
    use HasPipedriveEntity;

    protected $table = 'test_models';

    protected $fillable = ['name'];

    public $timestamps = false;
}

class TestCompanyModel extends Model
{
    use HasPipedriveEntity;

    protected $table = 'test_models';

    protected $fillable = ['name'];

    public $timestamps = false;
}
