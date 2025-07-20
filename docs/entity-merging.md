# 🔄 Entity Merging in Pipedrive

When entities are merged in Pipedrive (e.g., two organizations with the same name), this package **automatically** handles the migration of relationships to maintain data continuity.

## 🎯 **Problem Solved**

When you merge two entities in Pipedrive:
1. The "source" entity is deleted
2. The "target" entity is updated with merged data
3. **Without this feature**: All relationships pointing to the deleted entity become orphaned
4. **With this feature**: Relationships are automatically migrated to the surviving entity

## ✨ **Key Features**

- **Zero-Config Automatic Migration**: Relationships in `pipedrive_entity_links` are migrated automatically
- **Configurable Behavior**: Can be disabled or customized via environment variables
- **Conflict Resolution**: Smart handling of relationship conflicts
- **Event-Driven**: `PipedriveEntityMerged` event for custom handling of your own tables

## 📋 **How It Works**

### **Automatic Detection**

The package detects merges in two ways:

1. **Explicit Merge Events**: When Pipedrive sends a `merged` webhook event
2. **Heuristic Detection**: When webhook patterns suggest a merge occurred:
   - Multiple entities of the same type are updated
   - One entity is then deleted
   - All within the same `correlation_id` and time window

### **Automatic Migration**

When a merge is detected, **by default**:
1. ✅ All relationships in `pipedrive_entity_links` pointing to the deleted entity are **automatically migrated**
2. ✅ Conflicts are handled based on your configured strategy
3. ✅ A `PipedriveEntityMerged` event is emitted for custom processing of your own tables

**No code required!** The package handles the migration automatically to ensure relationship continuity.

## ⚙️ **Configuration**

Configure merge handling in `config/pipedrive.php`:

```php
'merge' => [
    // Enable heuristic detection of merges
    'enable_heuristic_detection' => env('PIPEDRIVE_MERGE_HEURISTIC_DETECTION', true),

    // Time window to analyze webhook events for merge patterns (seconds)
    'detection_window_seconds' => env('PIPEDRIVE_MERGE_DETECTION_WINDOW', 30),

    // AUTOMATIC MIGRATION: Enable automatic migration of pipedrive_entity_links table
    // When enabled (default), relationships are automatically migrated without code
    'auto_migrate_relations' => env('PIPEDRIVE_MERGE_AUTO_MIGRATE', true),

    // Strategy for handling relation conflicts
    'strategy' => env('PIPEDRIVE_MERGE_STRATEGY', 'keep_both'),
],
```

### **Environment Variables**

```bash
# Enable/disable automatic migration (default: true)
PIPEDRIVE_MERGE_AUTO_MIGRATE=true

# Merge strategy for conflicts (default: keep_both)
PIPEDRIVE_MERGE_STRATEGY=keep_both

# Enable heuristic detection (default: true)
PIPEDRIVE_MERGE_HEURISTIC_DETECTION=true

# Detection window in seconds (default: 30)
PIPEDRIVE_MERGE_DETECTION_WINDOW=30
```

### **Merge Strategies**

When both the deleted and surviving entities have relationships to the same model:

- **`keep_both`** (recommended): Keep both relationships
- **`keep_surviving`**: Keep only the relationship to the surviving entity
- **`keep_merged`**: Keep the migrated relationship, remove the existing one

## 📡 **PipedriveEntityMerged Event**

The package automatically handles migrations in the `pipedrive_entity_links` table. You only need to listen for the event if you have **your own custom tables** that reference Pipedrive entities.

Listen for merge events in your `AppServiceProvider`:

```php
use Keggermont\LaravelPipedrive\Events\PipedriveEntityMerged;

Event::listen(PipedriveEntityMerged::class, function ($event) {
    Log::info('Entity merged', [
        'entity_type' => $event->entityType,
        'merged_id' => $event->getMergedId(),
        'surviving_id' => $event->getSurvivingId(),
        'migrated_relations' => $event->migratedRelationsCount,
        'auto_migration_enabled' => $event->getMetadata('auto_migration_enabled', true),
    ]);

    // ONLY needed for your custom tables - the package handles pipedrive_entity_links automatically!
    if ($event->isOrganization()) {
        // Update your custom tables that reference the organization
        DB::table('orders')
            ->where('pipedrive_organization_id', $event->getMergedId())
            ->update(['pipedrive_organization_id' => $event->getSurvivingId()]);
    }
});
```

### **Event Properties**

```php
$event->entityType;              // 'organizations', 'deals', etc.
$event->mergedId;                // ID of the deleted entity
$event->survivingId;             // ID of the surviving entity
$event->survivingEntity;         // Laravel model of surviving entity (if available)
$event->migratedRelationsCount;  // Number of relationships migrated
$event->source;                  // 'webhook', 'webhook_heuristic', etc.
$event->metadata;                // Additional metadata
```

### **Event Methods**

```php
// Check entity type
if ($event->isOrganization()) { /* ... */ }
if ($event->isDeal()) { /* ... */ }
if ($event->isPerson()) { /* ... */ }

// Check source
if ($event->isFromWebhook()) { /* ... */ }

// Get metadata
$correlationId = $event->getMetadata('correlation_id');
$migrationResults = $event->getMetadata('migration_results');
```

## 🔧 **Manual Migration**

You can manually migrate relationships:

```php
use Keggermont\LaravelPipedrive\Models\PipedriveEntityLink;

$results = PipedriveEntityLink::migrateEntityRelations(
    'organizations',  // Entity type
    7,               // Merged (deleted) entity ID
    6,               // Surviving entity ID
    'keep_both'      // Strategy
);

// Results contain:
// - migrated: Number of relationships migrated
// - skipped: Number of relationships skipped
// - conflicts: Number of conflicts encountered
// - errors: Number of errors
// - details: Detailed information about each operation
```

## 📊 **Example Scenario**

**Before Merge:**
- Organization A (ID: 6) linked to Order #123
- Organization B (ID: 7) linked to Order #456
- You merge Organization B into Organization A in Pipedrive

**After Merge (Automatic):**
- Organization A (ID: 6) exists and is updated
- Organization B (ID: 7) is deleted
- Order #123 still linked to Organization A (ID: 6)
- Order #456 now linked to Organization A (ID: 6) ✅
- All entries in `pipedrive_entity_links` for ID 7 are migrated to ID 6 ✅
- `PipedriveEntityMerged` event emitted for your custom tables

**No Code Required!** The package handles the migration automatically.

## 🚨 **Important Notes**

1. **Automatic by Default**: The package automatically migrates `pipedrive_entity_links` - no code required!
2. **Custom Tables Only**: You only need to write code for your own custom tables that reference Pipedrive entities
3. **Backup First**: Always backup your data before testing merge scenarios
4. **Test Strategy**: Test your merge strategy in a development environment
5. **Performance**: Large numbers of relationships may take time to migrate
6. **Disable if Needed**: Set `PIPEDRIVE_MERGE_AUTO_MIGRATE=false` to disable automatic migration

## 🔍 **Troubleshooting**

### **Merges Not Detected**

Check your configuration:
```bash
# Enable heuristic detection
PIPEDRIVE_MERGE_HEURISTIC_DETECTION=true

# Increase detection window if needed
PIPEDRIVE_MERGE_DETECTION_WINDOW=60
```

### **Disable Automatic Migration**

If you prefer to handle all migrations manually:
```bash
# Disable automatic migration
PIPEDRIVE_MERGE_AUTO_MIGRATE=false
```

When disabled:
- The `PipedriveEntityMerged` event is still emitted
- You must handle all migrations manually in your event listener
- Use `PipedriveEntityLink::migrateEntityRelations()` for manual migration

### **Check Migration Results**

Monitor logs for merge detection and migration results:
```bash
tail -f storage/logs/laravel.log | grep "merge"
```

### **Manual Verification**

Check if relationships were migrated correctly:
```php
// Check relationships for an entity
$links = PipedriveEntityLink::where('pipedrive_entity_type', 'organizations')
    ->where('pipedrive_entity_id', 6)
    ->get();

// Check for orphaned relationships
$orphaned = PipedriveEntityLink::where('pipedrive_entity_type', 'organizations')
    ->where('pipedrive_entity_id', 7)
    ->get();
```

The merge handling ensures your application maintains data integrity even when entities are merged in Pipedrive! 🔄
