# 🔗 Pipedrive Entity Linking

This system allows you to link your Laravel models to Pipedrive entities in a flexible and typed manner, with support for morphic relationships.

## 🎯 **Concept**

The `HasPipedriveEntity` trait allows any Laravel model to be linked to one or more Pipedrive entities. Each model can define a **default entity type** to simplify usage.

## 🚀 **Basic Configuration**

### 1. **Add the trait to your model**

```php
use Keggermont\LaravelPipedrive\Traits\HasPipedriveEntity;
use Keggermont\LaravelPipedrive\Enums\PipedriveEntityType;

class Order extends Model
{
    use HasPipedriveEntity;

    // Define default entity type
    protected PipedriveEntityType $pipedriveEntityType = PipedriveEntityType::DEALS;

    // Or with a string
    // protected string $pipedriveEntityType = 'deals';
}
```

### 2. **Available entity types**

```php
PipedriveEntityType::DEALS          // 'deals'
PipedriveEntityType::PERSONS        // 'persons'
PipedriveEntityType::ORGANIZATIONS  // 'organizations'
PipedriveEntityType::ACTIVITIES     // 'activities'
PipedriveEntityType::PRODUCTS       // 'products'
PipedriveEntityType::FILES          // 'files'
PipedriveEntityType::NOTES          // 'notes'
PipedriveEntityType::USERS          // 'users'
PipedriveEntityType::PIPELINES      // 'pipelines'
PipedriveEntityType::STAGES         // 'stages'
PipedriveEntityType::GOALS          // 'goals'
```

## 📝 **Usage**

### **Simplified methods (using default type)**

```php
$order = Order::find(1);

// Link to default entity (DEALS in this example)
$order->linkToPipedriveEntity(123, true, ['source' => 'manual']);

// Check if linked to an entity
$order->isLinkedToPipedriveEntity(123); // true

// Get primary entity of default type
$deal = $order->getPrimaryPipedriveEntity();

// Get all entities of default type
$entities = $order->getPipedriveEntities();

// Unlink from entity
$order->unlinkFromPipedriveEntity(123);
```

### **Specific methods (for different types)**

```php
// Link to specific types
$order->linkToSpecificPipedriveEntity('persons', 456, false, ['role' => 'customer']);
$order->linkToSpecificPipedriveEntity('organizations', 789, false, ['type' => 'client']);

// Convenience methods for common types
$order->linkToPipedriveDeal(123);
$order->linkToPipedrivePerson(456);
$order->linkToPipedriveOrganization(789);

// Get specific entities
$persons = $order->getSpecificPipedriveEntities('persons');
$deal = $order->getPrimaryPipedriveDeal();
$person = $order->getPrimaryPipedrivePerson();
```

## 🏗️ **Configuration Examples by Model**

### **Order Model → Deals**

```php
class Order extends Model
{
    use HasPipedriveEntity;

    protected PipedriveEntityType $pipedriveEntityType = PipedriveEntityType::DEALS;

    public function linkToDeal(int $dealId): void
    {
        $this->linkToPipedriveEntity($dealId, true, [
            'linked_at' => now(),
            'source' => 'order_creation'
        ]);
    }
}
```

### **Customer Model → Persons**

```php
class Customer extends Model
{
    use HasPipedriveEntity;

    protected PipedriveEntityType $pipedriveEntityType = PipedriveEntityType::PERSONS;

    public function linkToPerson(int $personId): void
    {
        $this->linkToPipedriveEntity($personId, true);
    }
}
```

### **Company Model → Organizations**

```php
class Company extends Model
{
    use HasPipedriveEntity;

    protected PipedriveEntityType $pipedriveEntityType = PipedriveEntityType::ORGANIZATIONS;

    public function linkToOrganization(int $orgId): void
    {
        $this->linkToPipedriveEntity($orgId, true);
    }
}
```

## 🔗 **Multiple Relationships**

A model can be linked to multiple entity types:

```php
$order = Order::create([...]);

// Primary link to a deal (default type)
$order->linkToPipedriveEntity(123, true);

// Secondary links to other entities
$order->linkToPipedrivePerson(456, false, ['role' => 'customer']);
$order->linkToPipedriveOrganization(789, false, ['type' => 'client']);

// Link summary
$summary = $order->getPipedriveEntitySummary();
/*
[
    'default_entity_type' => 'deals',
    'primary_deal' => 123,
    'linked_persons' => [456],
    'linked_organizations' => [789],
    'total_links' => 3
]
*/
```

## 📊 **Metadata and Status**

Each link can contain metadata and synchronization status:

```php
$order->linkToPipedriveEntity(123, true, [
    'source' => 'api_import',
    'imported_by' => auth()->id(),
    'import_date' => now(),
    'notes' => 'Imported from legacy system'
]);

// Access metadata via the link
$link = $order->pipedriveEntityLinks()->first();
$source = $link->getMetadata('source'); // 'api_import'
$link->setMetadata('last_updated', now());
```

## 🛠️ **Management via Artisan Commands**

```bash
# View link statistics
php artisan pipedrive:entity-links stats

# Synchronize links
php artisan pipedrive:entity-links sync --entity-type=deals

# List links
php artisan pipedrive:entity-links list --limit=50

# Clean orphaned links
php artisan pipedrive:entity-links cleanup
```

## 🔄 **Auto-suggestion of Types**

If you don't define a default type, the system automatically suggests based on the model name:

```php
// These models will have automatic suggestions:
Order → DEALS
Sale → DEALS
Customer → PERSONS
Client → PERSONS
User → PERSONS
Company → ORGANIZATIONS
Business → ORGANIZATIONS
Task → ACTIVITIES
Product → PRODUCTS
```

## 🎛️ **Advanced Service**

For complex operations, use the service:

```php
use Keggermont\LaravelPipedrive\Services\PipedriveEntityLinkService;

$linkService = app(PipedriveEntityLinkService::class);

// Bulk creation
$linksData = [
    ['model' => $order1, 'entity_type' => 'deals', 'entity_id' => 123],
    ['model' => $order2, 'entity_type' => 'deals', 'entity_id' => 124],
];
$results = $linkService->bulkCreateLinks($linksData);

// Global statistics
$stats = $linkService->getGlobalStats();

// Find orphaned links
$orphaned = $linkService->findOrphanedLinks();
```

## 🔒 **Best Practices**

1. **Always define a default type** in your models
2. **Use metadata** to trace the origin of links
3. **Mark a link as primary** for the main entity
4. **Synchronize regularly** with `php artisan pipedrive:entity-links sync`
5. **Clean orphaned links** periodically

The entity linking system provides maximum flexibility while maintaining data integrity and traceability! 🔗
