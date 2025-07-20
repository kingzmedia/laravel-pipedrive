# 📡 Pipedrive Events

This package emits Laravel events for all CRUD operations on Pipedrive entities, whether via webhooks or synchronization commands.

## ⚡ **Event Execution Order**

**Important**: Events are emitted **AFTER** the data is saved to the database, ensuring data availability for processing.

### Webhook Processing Order:
1. **`PipedriveWebhookReceived`** - Raw webhook event (emitted immediately upon reception)
2. **Database Save** - Entity is created/updated in local database
3. **`PipedriveEntityCreated/Updated/Deleted`** - Emitted after successful database operation

This guarantees that when you receive a `PipedriveEntityCreated/Updated/Deleted` event, the Laravel model is fully persisted and available for processing.

## 📋 **Event Types**

### 0. **`PipedriveWebhookReceived`** (Raw Webhook)

Emitted immediately when a webhook is received, before any processing.

```php
use Keggermont\LaravelPipedrive\Events\PipedriveWebhookReceived;

public function handle(PipedriveWebhookReceived $event)
{
    $action = $event->action;           // 'added', 'updated', 'deleted', 'merged'
    $object = $event->object;           // 'deal', 'person', 'organization', etc.
    $objectId = $event->objectId;       // Pipedrive ID
    $current = $event->current;         // Current data from webhook
    $previous = $event->previous;       // Previous data (for updates)

    // Check event type
    if ($event->isCreate()) { /* ... */ }
    if ($event->isUpdate()) { /* ... */ }
    if ($event->isDelete()) { /* ... */ }
    if ($event->isMerge()) { /* ... */ }
}
```

### 1. **`PipedriveEntityCreated`**

Emitted when a Pipedrive entity is created locally.

```php
use Keggermont\LaravelPipedrive\Events\PipedriveEntityCreated;

public function handle(PipedriveEntityCreated $event)
{
    $entity = $event->entity;                // The created Eloquent model
    $entityType = $event->entityType;        // Entity type (deals, persons, etc.)
    $pipedriveId = $event->getPipedriveId(); // Pipedrive ID
    $source = $event->source;                // 'webhook', 'sync', 'api', etc.

    if ($event->isDeal()) {
        // Deal-specific logic
    }
}
```

### 2. **`PipedriveEntityUpdated`**

Emitted when a Pipedrive entity is updated locally.

```php
use Keggermont\LaravelPipedrive\Events\PipedriveEntityUpdated;

public function handle(PipedriveEntityUpdated $event)
{
    $entity = $event->entity;                // The updated Eloquent model
    $changes = $event->changes;              // The changes made

    // Check specific changes
    if ($event->hasChanged('status')) {
        $oldStatus = $event->getOldValue('status');
        $newStatus = $event->getNewValue('status');
        // React to status change
    }

    // Check multiple changes
    if ($event->hasChangedAny(['value', 'currency'])) {
        // React to value or currency changes
    }
}
```

### 3. **`PipedriveEntityDeleted`**

Emitted when a Pipedrive entity is deleted locally.

```php
use Keggermont\LaravelPipedrive\Events\PipedriveEntityDeleted;

public function handle(PipedriveEntityDeleted $event)
{
    $pipedriveId = $event->pipedriveId;      // Pipedrive ID of deleted entity
    $localId = $event->localId;              // Local ID (if available)
    $entityData = $event->entityData;        // Entity data before deletion

    // Access deleted entity data
    $title = $event->getEntityTitle();
    $value = $event->getDeletedValue();      // For deals
}
```

### 4. **`PipedriveEntityMerged`**

Emitted when two Pipedrive entities are merged (one entity is merged into another).

```php
use Keggermont\LaravelPipedrive\Events\PipedriveEntityMerged;

public function handle(PipedriveEntityMerged $event)
{
    $mergedId = $event->getMergedId();           // ID of deleted entity
    $survivingId = $event->getSurvivingId();     // ID of surviving entity
    $survivingEntity = $event->getSurvivingEntity(); // Laravel model (if available)
    $migratedCount = $event->migratedRelationsCount; // Relations migrated

    // Handle your custom relationships
    if ($event->isOrganization()) {
        DB::table('orders')
            ->where('pipedrive_organization_id', $mergedId)
            ->update(['pipedrive_organization_id' => $survivingId]);
    }
}
```

## 🔄 **Event Sources**

Events include a `source` property that indicates where the operation originated:

- `webhook` : Triggered by a Pipedrive webhook (real-time)
- `sync` : Triggered by synchronization command
- `command` : Triggered by another command
- `api` : Triggered by API call
- `unknown` : Unknown source

You can check the source with:

```php
if ($event->isFromWebhook()) {
    // Real-time processing
}

if ($event->isFromSync()) {
    // Synchronization processing
}
```

## 🧩 **Utility Methods**

All events include utility methods to facilitate processing:

```php
// Check entity type
if ($event->isDeal()) { /* ... */ }
if ($event->isPerson()) { /* ... */ }
if ($event->isOrganization()) { /* ... */ }
if ($event->isActivity()) { /* ... */ }
if ($event->isProduct()) { /* ... */ }

// Get event summary
$summary = $event->getSummary();

// Access metadata
$userId = $event->getMetadata('user_id');
```

## 📝 **Listener Configuration**

Configure your listeners in `EventServiceProvider.php`:

```php
protected $listen = [
    PipedriveEntityCreated::class => [
        App\Listeners\PipedriveEntityCreatedListener::class,
    ],
    PipedriveEntityUpdated::class => [
        App\Listeners\PipedriveEntityUpdatedListener::class,
    ],
    PipedriveEntityDeleted::class => [
        App\Listeners\PipedriveEntityDeletedListener::class,
    ],
    PipedriveEntityMerged::class => [
        App\Listeners\PipedriveEntityMergedListener::class,
    ],
];
```

## 🔍 **Listener with Specific Methods**

You can also use a single listener with multiple methods:

```php
protected $listen = [
    PipedriveEntityCreated::class => [
        'App\Listeners\PipedriveEventsListener@handleCreated',
    ],
    PipedriveEntityUpdated::class => [
        'App\Listeners\PipedriveEventsListener@handleUpdated',
    ],
    PipedriveEntityDeleted::class => [
        'App\Listeners\PipedriveEventsListener@handleDeleted',
    ],
];
```

## 🚀 **Common Use Cases**

### **New Deal Notification**

```php
public function handle(PipedriveEntityCreated $event)
{
    if ($event->isDeal() && $event->isFromWebhook()) {
        $deal = $event->entity;
        Mail::to('sales@company.com')->send(new NewDealNotification($deal));
    }
}
```

### **React to Status Changes**

```php
public function handle(PipedriveEntityUpdated $event)
{
    if ($event->isDeal() && $event->hasChanged('status')) {
        $deal = $event->entity;
        $newStatus = $event->getNewValue('status');

        if ($newStatus === 'won') {
            CreateInvoiceJob::dispatch($deal);
        }
    }
}
```

### **Cleanup After Deletion**

```php
public function handle(PipedriveEntityDeleted $event)
{
    if ($event->isPerson()) {
        $email = $event->getEntityData('email');
        if ($email) {
            RemoveFromMailingListJob::dispatch($email);
        }
    }
}
```

### **Synchronization with External System**

```php
public function handle(PipedriveEntityCreated|PipedriveEntityUpdated $event)
{
    $entity = $event->entity;

    if ($event->isOrganization()) {
        SyncToExternalCrmJob::dispatch($entity);
    }
}
```

## 📊 **Real-Time Analytics**

```php
public function handle(PipedriveEntityUpdated $event)
{
    if ($event->isDeal() && $event->valueChanged()) {
        $deal = $event->entity;
        $oldValue = $event->getOldValue('value') ?? 0;
        $newValue = $event->getNewValue('value') ?? 0;
        $difference = $newValue - $oldValue;

        // Update metrics
        Cache::increment('deals_value_change_today', $difference);

        // Update dashboard in real-time
        broadcast(new DealValueChangedEvent($deal, $oldValue, $newValue));
    }
}
```

The event system provides powerful hooks for building reactive applications that respond instantly to Pipedrive changes! 📡
