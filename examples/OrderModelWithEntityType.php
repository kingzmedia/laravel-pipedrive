<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Skeylup\LaravelPipedrive\Enums\PipedriveEntityType;
use Skeylup\LaravelPipedrive\Traits\HasPipedriveEntity;

/**
 * Example Order model showing how to use the HasPipedriveEntity trait
 * with a default entity type configuration
 */
class Order extends Model
{
    use HasPipedriveEntity;

    protected $fillable = [
        'order_number',
        'customer_name',
        'customer_email',
        'total_amount',
        'status',
    ];

    /**
     * Define the default Pipedrive entity type for this model
     * This model will be linked to Pipedrive Deals by default
     */
    protected PipedriveEntityType $pipedriveEntityType = PipedriveEntityType::DEALS;

    // Alternative way using string:
    // protected string $pipedriveEntityType = 'deals';

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
        ];
    }

    /**
     * Example usage methods with simplified API
     */

    /**
     * Link this order to a Pipedrive deal (using default entity type)
     */
    public function linkToDeal(int $dealId): void
    {
        // Since the default entity type is DEALS, we can use the simple method
        $this->linkToPipedriveEntity($dealId, true, [
            'linked_at' => now(),
            'linked_by' => auth()->id(),
            'reason' => 'Order created from deal',
        ]);
    }

    /**
     * Get the associated Pipedrive deal (using default entity type)
     */
    public function getPipedriveDeal()
    {
        // This will get the primary entity of the default type (DEALS)
        return $this->getPrimaryPipedriveEntity();
    }

    /**
     * Check if linked to a specific deal (using default entity type)
     */
    public function isLinkedToDeal(int $dealId): bool
    {
        // This will check the default entity type (DEALS)
        return $this->isLinkedToPipedriveEntity($dealId);
    }

    /**
     * Unlink from a deal (using default entity type)
     */
    public function unlinkFromDeal(int $dealId): bool
    {
        // This will unlink from the default entity type (DEALS)
        return $this->unlinkFromPipedriveEntity($dealId);
    }

    /**
     * Link to additional entities (person, organization) while keeping deal as primary
     */
    public function linkToCustomerAndCompany(int $personId, int $organizationId): void
    {
        // Link to person (not primary since deal is the main entity)
        $this->linkToPipedrivePerson($personId, false, [
            'role' => 'customer',
            'linked_at' => now(),
        ]);

        // Link to organization (not primary)
        $this->linkToPipedriveOrganization($organizationId, false, [
            'relationship' => 'client_company',
            'linked_at' => now(),
        ]);
    }

    /**
     * Get all linked entities summary
     */
    public function getPipedriveEntitySummary(): array
    {
        return [
            'default_entity_type' => $this->getDefaultPipedriveEntityTypeString(),
            'primary_deal' => $this->getPrimaryPipedriveEntity()?->id,
            'linked_persons' => $this->getPipedrivePersons()->pluck('pipedrive_id'),
            'linked_organizations' => $this->getPipedriveOrganizations()->pluck('pipedrive_id'),
            'total_links' => $this->pipedriveEntityLinks()->active()->count(),
        ];
    }
}

/**
 * Example Customer model that defaults to PERSONS
 */
class Customer extends Model
{
    use HasPipedriveEntity;

    protected $fillable = ['name', 'email', 'phone'];

    // This model defaults to linking with Pipedrive Persons
    protected PipedriveEntityType $pipedriveEntityType = PipedriveEntityType::PERSONS;

    /**
     * Link to a Pipedrive person (using default entity type)
     */
    public function linkToPerson(int $personId): void
    {
        $this->linkToPipedriveEntity($personId, true, [
            'source' => 'customer_import',
            'linked_at' => now(),
        ]);
    }

    /**
     * Get the linked Pipedrive person
     */
    public function getPipedrivePersonEntity()
    {
        return $this->getPrimaryPipedriveEntity();
    }
}

/**
 * Example Company model that defaults to ORGANIZATIONS
 */
class Company extends Model
{
    use HasPipedriveEntity;

    protected $fillable = ['name', 'website', 'industry'];

    // This model defaults to linking with Pipedrive Organizations
    protected PipedriveEntityType $pipedriveEntityType = PipedriveEntityType::ORGANIZATIONS;

    /**
     * Link to a Pipedrive organization (using default entity type)
     */
    public function linkToOrganization(int $orgId): void
    {
        $this->linkToPipedriveEntity($orgId, true, [
            'source' => 'company_sync',
            'linked_at' => now(),
        ]);
    }
}

/**
 * Example usage in controllers or services:
 */

/*
// Create an order and link it to a deal (simplified)
$order = Order::create([
    'order_number' => 'ORD-001',
    'customer_name' => 'John Doe',
    'total_amount' => 1500.00,
    'status' => 'pending',
]);

// Link to deal ID 123 (uses default entity type DEALS)
$order->linkToDeal(123);

// Check if linked to deal 123
if ($order->isLinkedToDeal(123)) {
    echo "Order is linked to deal 123";
}

// Get the primary deal
$deal = $order->getPipedriveDeal();

// Link to additional entities
$order->linkToCustomerAndCompany(456, 789);

// Get summary
$summary = $order->getPipedriveEntitySummary();

// Create a customer and link to person
$customer = Customer::create([
    'name' => 'Jane Doe',
    'email' => 'jane@example.com',
]);

// Link to person ID 456 (uses default entity type PERSONS)
$customer->linkToPerson(456);

// Create a company and link to organization
$company = Company::create([
    'name' => 'Acme Corp',
    'website' => 'acme.com',
]);

// Link to organization ID 789 (uses default entity type ORGANIZATIONS)
$company->linkToOrganization(789);

// You can still use specific methods when needed
$order->linkToSpecificPipedriveEntity('activities', 999, false, ['type' => 'follow_up']);
*/
