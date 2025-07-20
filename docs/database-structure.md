# 🏗️ Database Structure

## 🎯 **Hybrid Architecture**

Laravel-Pipedrive uses a **hybrid data structure** that combines the best of both worlds:

- **Essential columns** for fast queries and relationships
- **JSON storage** for complete Pipedrive data and flexibility

This approach ensures **performance**, **flexibility**, and **future-proofing**.

## 📊 **Common Table Structure**

Every Pipedrive entity table follows this consistent pattern:

```sql
-- Laravel primary key
id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY

-- Pipedrive identification
pipedrive_id          INT UNSIGNED NOT NULL

-- Essential business columns (entity-specific)
name/title/subject    VARCHAR(255)        -- Main identifier
[entity_fields]       [VARIOUS_TYPES]     -- Key business fields
[relationship_ids]    INT UNSIGNED        -- Foreign keys

-- Status
active_flag           BOOLEAN DEFAULT TRUE

-- Complete Pipedrive data
pipedrive_data        JSON                -- All original data

-- Pipedrive timestamps
pipedrive_add_time    TIMESTAMP NULL      -- Created in Pipedrive
pipedrive_update_time TIMESTAMP NULL      -- Modified in Pipedrive

-- Laravel timestamps
created_at            TIMESTAMP NULL
updated_at            TIMESTAMP NULL

-- Indexes for performance
INDEX(pipedrive_id)
INDEX(active_flag)
[entity_specific_indexes]
```

## 📋 **Entity Tables**

### **pipedrive_activities**

```sql
CREATE TABLE pipedrive_activities (
    id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pipedrive_id          INT UNSIGNED NOT NULL,
    
    -- Essential fields
    subject               VARCHAR(255) NULL,
    type                  VARCHAR(255) NULL,
    done                  BOOLEAN DEFAULT FALSE,
    due_date              DATETIME NULL,
    
    -- Relationships
    person_id             INT UNSIGNED NULL,
    org_id                INT UNSIGNED NULL,
    deal_id               INT UNSIGNED NULL,
    user_id               INT UNSIGNED NULL,
    
    -- Status & data
    active_flag           BOOLEAN DEFAULT TRUE,
    pipedrive_data        JSON NULL,
    pipedrive_add_time    TIMESTAMP NULL,
    pipedrive_update_time TIMESTAMP NULL,
    created_at            TIMESTAMP NULL,
    updated_at            TIMESTAMP NULL,
    
    -- Indexes
    INDEX(pipedrive_id),
    INDEX(user_id, done),
    INDEX(deal_id, done),
    INDEX(person_id, done),
    INDEX(org_id, done),
    INDEX(due_date, done),
    INDEX(type, done)
);
```

### **pipedrive_deals**

```sql
CREATE TABLE pipedrive_deals (
    id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pipedrive_id          INT UNSIGNED NOT NULL,
    
    -- Essential fields
    title                 VARCHAR(255) NOT NULL,
    value                 DECIMAL(15,2) NULL,
    currency              VARCHAR(3) NULL,
    status                VARCHAR(255) NULL,
    stage_id              INT UNSIGNED NULL,
    
    -- Relationships
    user_id               INT UNSIGNED NULL,
    person_id             INT UNSIGNED NULL,
    org_id                INT UNSIGNED NULL,
    
    -- Status & data
    active_flag           BOOLEAN DEFAULT TRUE,
    pipedrive_data        JSON NULL,
    pipedrive_add_time    TIMESTAMP NULL,
    pipedrive_update_time TIMESTAMP NULL,
    created_at            TIMESTAMP NULL,
    updated_at            TIMESTAMP NULL,
    
    -- Indexes
    INDEX(pipedrive_id),
    INDEX(user_id, status),
    INDEX(person_id, status),
    INDEX(org_id, status),
    INDEX(stage_id, status),
    INDEX(status, value)
);
```

### **pipedrive_persons**

```sql
CREATE TABLE pipedrive_persons (
    id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pipedrive_id          INT UNSIGNED NOT NULL,
    
    -- Essential fields
    name                  VARCHAR(255) NOT NULL,
    email                 VARCHAR(255) NULL,
    phone                 VARCHAR(255) NULL,
    
    -- Relationships
    org_id                INT UNSIGNED NULL,
    owner_id              INT UNSIGNED NULL,
    
    -- Status & data
    active_flag           BOOLEAN DEFAULT TRUE,
    pipedrive_data        JSON NULL,
    pipedrive_add_time    TIMESTAMP NULL,
    pipedrive_update_time TIMESTAMP NULL,
    created_at            TIMESTAMP NULL,
    updated_at            TIMESTAMP NULL,
    
    -- Indexes
    INDEX(pipedrive_id),
    INDEX(owner_id, active_flag),
    INDEX(org_id, active_flag),
    INDEX(email),
    INDEX(name)
);
```

### **pipedrive_custom_fields**

```sql
CREATE TABLE pipedrive_custom_fields (
    id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pipedrive_id          INT UNSIGNED NOT NULL,
    
    -- Essential fields
    name                  VARCHAR(255) NOT NULL,
    key                   VARCHAR(255) NOT NULL,
    field_type            VARCHAR(255) NOT NULL,
    entity_type           VARCHAR(255) NOT NULL,
    
    -- Status & data
    active_flag           BOOLEAN DEFAULT TRUE,
    pipedrive_data        JSON NULL,
    pipedrive_add_time    TIMESTAMP NULL,
    pipedrive_update_time TIMESTAMP NULL,
    created_at            TIMESTAMP NULL,
    updated_at            TIMESTAMP NULL,
    
    -- Indexes
    UNIQUE KEY unique_field_per_entity (pipedrive_id, entity_type),
    INDEX(entity_type, key),
    INDEX(field_type),
    INDEX(active_flag)
);
```

## 🔗 **Relationships & Foreign Keys**

### **Relationship Mapping**

| Column | References | Description |
|--------|------------|-------------|
| `user_id` | `pipedrive_users.pipedrive_id` | Assigned/owner user |
| `person_id` | `pipedrive_persons.pipedrive_id` | Related person |
| `org_id` | `pipedrive_organizations.pipedrive_id` | Related organization |
| `deal_id` | `pipedrive_deals.pipedrive_id` | Related deal |
| `stage_id` | `pipedrive_stages.pipedrive_id` | Pipeline stage |
| `pipeline_id` | `pipedrive_pipelines.pipedrive_id` | Pipeline |
| `owner_id` | `pipedrive_users.pipedrive_id` | Owner user |

### **Why No Foreign Key Constraints?**

We use **soft references** instead of database foreign keys because:

1. **Pipedrive data integrity** - Pipedrive may have orphaned references
2. **Sync flexibility** - Entities can be synced independently
3. **Performance** - No constraint checking overhead
4. **Data migration** - Easier to handle data inconsistencies

## 📊 **JSON Data Structure**

### **pipedrive_data Column**

The `pipedrive_data` JSON column contains the **complete original response** from Pipedrive:

```json
{
  "id": 12345,
  "title": "Important Deal",
  "value": 15000,
  "currency": "USD",
  "status": "open",
  "stage_id": 15,
  "person_id": 456,
  "org_id": 789,
  "user_id": 123,
  "add_time": "2024-01-15 10:30:00",
  "update_time": "2024-01-20 14:45:00",
  "active_flag": true,
  "probability": 75,
  "expected_close_date": "2024-02-15",
  "lost_reason": null,
  "close_time": null,
  "won_time": null,
  "first_won_time": null,
  "lost_time": null,
  "products_count": 2,
  "files_count": 3,
  "notes_count": 5,
  "followers_count": 2,
  "email_messages_count": 8,
  "activities_count": 12,
  "done_activities_count": 8,
  "undone_activities_count": 4,
  "reference_activities_count": 0,
  "participants_count": 1,
  "custom_field_hash_1": "high",
  "custom_field_hash_2": "enterprise",
  "custom_field_hash_3": "2024-03-01",
  "visible_to": "3",
  "cc_email": "deals+12345@company.pipedrive.com"
}
```

### **Accessing JSON Data**

```php
$deal = PipedriveDeal::first();

// Direct access
$probability = $deal->pipedrive_data['probability'] ?? 0;
$customField = $deal->pipedrive_data['custom_field_hash'] ?? null;

// Helper method
$probability = $deal->getPipedriveAttribute('probability', 0);
$customField = $deal->getPipedriveAttribute('custom_field_hash');

// Setting values
$deal->setPipedriveAttribute('custom_field_hash', 'new_value');
$deal->save();
```

## 🔍 **Querying Strategies**

### **Essential Data Queries (Fast)**

```php
// Use indexed columns for fast queries
$deals = PipedriveDeal::where('status', 'open')
    ->where('value', '>', 10000)
    ->where('active_flag', true)
    ->get();

// Relationship queries
$userDeals = PipedriveDeal::where('user_id', 123)
    ->where('status', 'open')
    ->get();
```

### **JSON Data Queries (Flexible)**

```php
// MySQL JSON functions
$highProbabilityDeals = PipedriveDeal::whereRaw(
    "JSON_EXTRACT(pipedrive_data, '$.probability') > ?", [70]
)->get();

// Custom field queries
$enterpriseDeals = PipedriveDeal::whereRaw(
    "JSON_EXTRACT(pipedrive_data, '$.custom_field_hash') = ?", ['enterprise']
)->get();
```

### **Combined Queries (Best of Both)**

```php
// Fast filtering + JSON details
$deals = PipedriveDeal::where('status', 'open')  // Fast index lookup
    ->where('value', '>', 5000)                  // Fast numeric comparison
    ->whereRaw("JSON_EXTRACT(pipedrive_data, '$.probability') > ?", [50])  // JSON filter
    ->get();
```

## ⚡ **Performance Considerations**

### **Indexed Queries (Fast)**

✅ **Use these for frequent queries:**
```php
// Indexed columns
->where('pipedrive_id', 123)
->where('active_flag', true)
->where('status', 'open')
->where('user_id', 456)
->where('due_date', '>', now())
```

### **JSON Queries (Slower)**

⚠️ **Use sparingly for complex filtering:**
```php
// JSON extraction (not indexed)
->whereRaw("JSON_EXTRACT(pipedrive_data, '$.custom_field') = ?", ['value'])
->whereRaw("JSON_EXTRACT(pipedrive_data, '$.probability') > ?", [70])
```

### **Optimization Tips**

1. **Filter first** with indexed columns
2. **Then filter** with JSON for specific needs
3. **Use eager loading** for relationships
4. **Cache results** for repeated queries
5. **Consider indexes** on frequently queried JSON paths

```php
// Good: Filter by indexed columns first
$deals = PipedriveDeal::where('status', 'open')
    ->where('user_id', 123)
    ->whereRaw("JSON_EXTRACT(pipedrive_data, '$.probability') > ?", [70])
    ->get();

// Better: Use relationships for complex queries
$deals = PipedriveUser::find(123)
    ->deals()
    ->where('status', 'open')
    ->get();
```

## 🔧 **Migration Strategy**

### **Adding New Essential Columns**

If you need to promote JSON data to columns:

```php
// Migration
Schema::table('pipedrive_deals', function (Blueprint $table) {
    $table->integer('probability')->nullable()->after('stage_id');
    $table->index(['status', 'probability']);
});

// Data migration
PipedriveDeal::chunk(100, function ($deals) {
    foreach ($deals as $deal) {
        $deal->probability = $deal->pipedrive_data['probability'] ?? null;
        $deal->save();
    }
});
```

### **Removing Deprecated Columns**

```php
// Always backup first!
Schema::table('pipedrive_deals', function (Blueprint $table) {
    $table->dropColumn('deprecated_column');
});
```

This hybrid structure provides the perfect balance of **performance**, **flexibility**, and **maintainability** for your Pipedrive integration! 🚀
