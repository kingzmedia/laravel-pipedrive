# Database Indexing Recommendations

This document provides database indexing recommendations for optimal performance of the Laravel-Pipedrive package.

## Overview

Proper database indexing is crucial for performance, especially when dealing with large datasets from Pipedrive. The following recommendations are based on common query patterns and relationships in the package.

## Core Pipedrive Entity Tables

### 1. Pipedrive Deals (`pipedrive_deals`)

**Recommended Indexes:**

```sql
-- Primary lookup by Pipedrive ID (most common)
CREATE INDEX idx_pipedrive_deals_pipedrive_id ON pipedrive_deals(pipedrive_id);

-- Active deals filtering
CREATE INDEX idx_pipedrive_deals_active ON pipedrive_deals(active_flag) WHERE active_flag = true;

-- Stage-based queries
CREATE INDEX idx_pipedrive_deals_stage_id ON pipedrive_deals(stage_id);

-- User assignment queries
CREATE INDEX idx_pipedrive_deals_user_id ON pipedrive_deals(user_id);

-- Person relationship queries
CREATE INDEX idx_pipedrive_deals_person_id ON pipedrive_deals(person_id);

-- Organization relationship queries
CREATE INDEX idx_pipedrive_deals_org_id ON pipedrive_deals(org_id);

-- Status-based filtering
CREATE INDEX idx_pipedrive_deals_status ON pipedrive_deals(status);

-- Composite index for active deals by stage
CREATE INDEX idx_pipedrive_deals_active_stage ON pipedrive_deals(active_flag, stage_id) WHERE active_flag = true;

-- Composite index for user's active deals
CREATE INDEX idx_pipedrive_deals_user_active ON pipedrive_deals(user_id, active_flag) WHERE active_flag = true;

-- Date-based queries
CREATE INDEX idx_pipedrive_deals_add_time ON pipedrive_deals(pipedrive_add_time);
CREATE INDEX idx_pipedrive_deals_update_time ON pipedrive_deals(pipedrive_update_time);
```

### 2. Pipedrive Persons (`pipedrive_persons`)

**Recommended Indexes:**

```sql
-- Primary lookup by Pipedrive ID
CREATE INDEX idx_pipedrive_persons_pipedrive_id ON pipedrive_persons(pipedrive_id);

-- Active persons filtering
CREATE INDEX idx_pipedrive_persons_active ON pipedrive_persons(active_flag) WHERE active_flag = true;

-- Organization relationship
CREATE INDEX idx_pipedrive_persons_org_id ON pipedrive_persons(org_id);

-- Owner assignment
CREATE INDEX idx_pipedrive_persons_owner_id ON pipedrive_persons(owner_id);

-- Email-based lookups (for contact matching)
CREATE INDEX idx_pipedrive_persons_email ON pipedrive_persons USING gin((pipedrive_data->'email'));

-- Name-based searches
CREATE INDEX idx_pipedrive_persons_name ON pipedrive_persons(name);

-- Composite index for owner's active persons
CREATE INDEX idx_pipedrive_persons_owner_active ON pipedrive_persons(owner_id, active_flag) WHERE active_flag = true;
```

### 3. Pipedrive Organizations (`pipedrive_organizations`)

**Recommended Indexes:**

```sql
-- Primary lookup by Pipedrive ID
CREATE INDEX idx_pipedrive_organizations_pipedrive_id ON pipedrive_organizations(pipedrive_id);

-- Active organizations filtering
CREATE INDEX idx_pipedrive_organizations_active ON pipedrive_organizations(active_flag) WHERE active_flag = true;

-- Owner assignment
CREATE INDEX idx_pipedrive_organizations_owner_id ON pipedrive_organizations(owner_id);

-- Name-based searches
CREATE INDEX idx_pipedrive_organizations_name ON pipedrive_organizations(name);

-- Composite index for owner's active organizations
CREATE INDEX idx_pipedrive_organizations_owner_active ON pipedrive_organizations(owner_id, active_flag) WHERE active_flag = true;
```

### 4. Pipedrive Activities (`pipedrive_activities`)

**Recommended Indexes:**

```sql
-- Primary lookup by Pipedrive ID
CREATE INDEX idx_pipedrive_activities_pipedrive_id ON pipedrive_activities(pipedrive_id);

-- Deal relationship (most common)
CREATE INDEX idx_pipedrive_activities_deal_id ON pipedrive_activities(deal_id);

-- Person relationship
CREATE INDEX idx_pipedrive_activities_person_id ON pipedrive_activities(person_id);

-- Organization relationship
CREATE INDEX idx_pipedrive_activities_org_id ON pipedrive_activities(org_id);

-- User assignment
CREATE INDEX idx_pipedrive_activities_user_id ON pipedrive_activities(user_id);

-- Activity type filtering
CREATE INDEX idx_pipedrive_activities_type ON pipedrive_activities(type);

-- Due date queries
CREATE INDEX idx_pipedrive_activities_due_date ON pipedrive_activities(due_date);

-- Done status filtering
CREATE INDEX idx_pipedrive_activities_done ON pipedrive_activities(done);

-- Composite index for deal activities by date
CREATE INDEX idx_pipedrive_activities_deal_date ON pipedrive_activities(deal_id, due_date);

-- Composite index for user's pending activities
CREATE INDEX idx_pipedrive_activities_user_pending ON pipedrive_activities(user_id, done) WHERE done = false;
```

## Support Tables

### 5. Pipedrive Custom Fields (`pipedrive_custom_fields`)

**Recommended Indexes:**

```sql
-- Entity type filtering (most common query pattern)
CREATE INDEX idx_pipedrive_custom_fields_entity_type ON pipedrive_custom_fields(entity_type);

-- Active fields filtering
CREATE INDEX idx_pipedrive_custom_fields_active ON pipedrive_custom_fields(active_flag) WHERE active_flag = true;

-- Field key lookups
CREATE INDEX idx_pipedrive_custom_fields_key ON pipedrive_custom_fields(key);

-- Pipedrive ID lookups
CREATE INDEX idx_pipedrive_custom_fields_pipedrive_id ON pipedrive_custom_fields(pipedrive_id);

-- Field type filtering
CREATE INDEX idx_pipedrive_custom_fields_type ON pipedrive_custom_fields(field_type);

-- Composite index for active fields by entity
CREATE INDEX idx_pipedrive_custom_fields_entity_active ON pipedrive_custom_fields(entity_type, active_flag) WHERE active_flag = true;

-- Composite index for entity and key lookup
CREATE UNIQUE INDEX idx_pipedrive_custom_fields_entity_key ON pipedrive_custom_fields(entity_type, key);
```

### 6. Pipedrive Entity Links (`pipedrive_entity_links`)

**Recommended Indexes:**

```sql
-- Linkable model lookups (most common)
CREATE INDEX idx_pipedrive_entity_links_linkable ON pipedrive_entity_links(linkable_type, linkable_id);

-- Pipedrive entity lookups
CREATE INDEX idx_pipedrive_entity_links_entity ON pipedrive_entity_links(pipedrive_entity_type, pipedrive_entity_id);

-- Active links filtering
CREATE INDEX idx_pipedrive_entity_links_active ON pipedrive_entity_links(is_active) WHERE is_active = true;

-- Primary links filtering
CREATE INDEX idx_pipedrive_entity_links_primary ON pipedrive_entity_links(is_primary) WHERE is_primary = true;

-- Sync status filtering
CREATE INDEX idx_pipedrive_entity_links_sync_status ON pipedrive_entity_links(sync_status);

-- Pipedrive model references
CREATE INDEX idx_pipedrive_entity_links_pipedrive_model ON pipedrive_entity_links(pipedrive_model_type, pipedrive_model_id);

-- Composite index for active links by model
CREATE INDEX idx_pipedrive_entity_links_model_active ON pipedrive_entity_links(linkable_type, linkable_id, is_active) WHERE is_active = true;

-- Composite index for active links by entity
CREATE INDEX idx_pipedrive_entity_links_entity_active ON pipedrive_entity_links(pipedrive_entity_type, pipedrive_entity_id, is_active) WHERE is_active = true;
```

### 7. Pipedrive Stages (`pipedrive_stages`)

**Recommended Indexes:**

```sql
-- Primary lookup by Pipedrive ID
CREATE INDEX idx_pipedrive_stages_pipedrive_id ON pipedrive_stages(pipedrive_id);

-- Pipeline relationship
CREATE INDEX idx_pipedrive_stages_pipeline_id ON pipedrive_stages(pipeline_id);

-- Active stages filtering
CREATE INDEX idx_pipedrive_stages_active ON pipedrive_stages(active_flag) WHERE active_flag = true;

-- Order-based queries
CREATE INDEX idx_pipedrive_stages_order ON pipedrive_stages(order_nr);

-- Composite index for pipeline stages in order
CREATE INDEX idx_pipedrive_stages_pipeline_order ON pipedrive_stages(pipeline_id, order_nr);
```

### 8. Pipedrive Users (`pipedrive_users`)

**Recommended Indexes:**

```sql
-- Primary lookup by Pipedrive ID
CREATE INDEX idx_pipedrive_users_pipedrive_id ON pipedrive_users(pipedrive_id);

-- Active users filtering
CREATE INDEX idx_pipedrive_users_active ON pipedrive_users(active_flag) WHERE active_flag = true;

-- Email-based lookups
CREATE INDEX idx_pipedrive_users_email ON pipedrive_users(email);

-- Name-based searches
CREATE INDEX idx_pipedrive_users_name ON pipedrive_users(name);
```

## Migration Example

Create a migration to add these indexes:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Add indexes using raw SQL for better control
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_pipedrive_deals_pipedrive_id ON pipedrive_deals(pipedrive_id)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_pipedrive_deals_active ON pipedrive_deals(active_flag) WHERE active_flag = true');
        // ... add more indexes as needed
    }

    public function down()
    {
        DB::statement('DROP INDEX IF EXISTS idx_pipedrive_deals_pipedrive_id');
        DB::statement('DROP INDEX IF EXISTS idx_pipedrive_deals_active');
        // ... drop indexes
    }
};
```

## Performance Monitoring

### Query Analysis

Use these queries to monitor performance:

```sql
-- Find slow queries related to Pipedrive tables
SELECT query, mean_time, calls, total_time
FROM pg_stat_statements 
WHERE query LIKE '%pipedrive_%'
ORDER BY mean_time DESC;

-- Check index usage
SELECT schemaname, tablename, attname, n_distinct, correlation
FROM pg_stats 
WHERE tablename LIKE 'pipedrive_%';
```

### Laravel Query Optimization

```php
// Use query optimization in your code
$deals = PipedriveDeal::optimizedForList()
    ->where('active_flag', true)
    ->paginate(50);

// For detail views
$deal = PipedriveDeal::optimizedForDetail()
    ->findByPipedriveId($pipedriveId);
```

## Best Practices

1. **Use Partial Indexes**: Create indexes with WHERE clauses for commonly filtered data (e.g., active records)
2. **Composite Indexes**: Create multi-column indexes for common query patterns
3. **Monitor Index Usage**: Regularly check which indexes are being used
4. **Avoid Over-Indexing**: Don't create indexes for rarely used queries
5. **Consider Index Maintenance**: Indexes require maintenance overhead
6. **Use EXPLAIN ANALYZE**: Always analyze query execution plans

## Cache Integration

Combine indexing with the cache system for optimal performance:

```php
// Cache frequently accessed data
$customFields = $cacheService->getCustomFields('deal') 
    ?? PipedriveCustomField::forEntity('deal')->active()->get();
```
