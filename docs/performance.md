# ⚡ Performance Optimization

## 🎯 **Overview**

Laravel-Pipedrive is designed for performance with a hybrid data structure, efficient indexing, and smart querying strategies. This guide covers optimization techniques for maximum performance.

## 🏗️ **Database Optimization**

### **Indexed Queries (Fast)**

Always use indexed columns for frequent queries:

```php
// ✅ Fast: Uses indexes
$deals = PipedriveDeal::where('status', 'open')          // Indexed
    ->where('user_id', 123)                              // Indexed
    ->where('active_flag', true)                         // Indexed
    ->get();

// ✅ Fast: Relationship queries use indexes
$userDeals = PipedriveUser::find(123)->deals()
    ->where('status', 'open')
    ->get();
```

### **Available Indexes**

| Table | Indexed Columns | Use Case |
|-------|----------------|----------|
| **pipedrive_activities** | `user_id, done`, `deal_id, done`, `due_date, done` | Activity filtering |
| **pipedrive_deals** | `user_id, status`, `stage_id, status`, `status, value` | Deal queries |
| **pipedrive_persons** | `owner_id, active_flag`, `org_id, active_flag`, `email` | Contact lookup |
| **pipedrive_organizations** | `owner_id, active_flag`, `name` | Organization search |
| **pipedrive_custom_fields** | `entity_type, key`, `field_type` | Field management |

### **Query Performance Tips**

```php
// ✅ Good: Filter by indexed columns first
$deals = PipedriveDeal::where('status', 'open')
    ->where('user_id', 123)
    ->whereRaw("JSON_EXTRACT(pipedrive_data, '$.probability') > ?", [70])
    ->get();

// ❌ Slow: JSON query without indexed pre-filtering
$deals = PipedriveDeal::whereRaw("JSON_EXTRACT(pipedrive_data, '$.probability') > ?", [70])
    ->get();
```

## 🔗 **Relationship Optimization**

### **Eager Loading**

Always use eager loading to avoid N+1 queries:

```php
// ✅ Good: Single query with joins
$deals = PipedriveDeal::with(['user', 'person', 'organization', 'stage'])
    ->where('status', 'open')
    ->get();

// ❌ Bad: N+1 queries
$deals = PipedriveDeal::where('status', 'open')->get();
foreach ($deals as $deal) {
    echo $deal->user->name;     // Separate query for each deal
    echo $deal->person->name;   // Another query for each deal
}
```

### **Selective Loading**

Load only the columns you need:

```php
// ✅ Good: Load specific columns
$deals = PipedriveDeal::with([
    'user:pipedrive_id,name,email',
    'person:pipedrive_id,name,email,phone',
    'organization:pipedrive_id,name'
])->select('pipedrive_id', 'title', 'value', 'user_id', 'person_id', 'org_id')
  ->get();

// ✅ Good: Conditional loading
$deals = PipedriveDeal::with([
    'activities' => function($query) {
        $query->where('done', false)
              ->orderBy('due_date')
              ->limit(5);
    }
])->get();
```

### **Counting Relationships**

Use `withCount()` instead of loading full relationships:

```php
// ✅ Good: Count without loading
$users = PipedriveUser::withCount([
    'deals',
    'activities',
    'deals as open_deals_count' => function($query) {
        $query->where('status', 'open');
    }
])->get();

// ❌ Bad: Loading full relationships just to count
$users = PipedriveUser::with(['deals', 'activities'])->get();
foreach ($users as $user) {
    $dealCount = $user->deals->count();  // Already loaded all deals
}
```

## 📊 **JSON Data Optimization**

### **Smart JSON Queries**

```php
// ✅ Good: Combine indexed and JSON filtering
$highValueDeals = PipedriveDeal::where('status', 'open')  // Fast index lookup
    ->where('value', '>', 5000)                           // Fast numeric comparison
    ->whereRaw("JSON_EXTRACT(pipedrive_data, '$.probability') > ?", [70])  // JSON filter
    ->get();

// ✅ Good: Use JSON indexes (MySQL 5.7+)
// Add virtual columns for frequently queried JSON fields
Schema::table('pipedrive_deals', function (Blueprint $table) {
    $table->integer('probability')->virtualAs("JSON_EXTRACT(pipedrive_data, '$.probability')")->index();
});
```

### **Efficient JSON Access**

```php
// ✅ Good: Batch access to JSON data
$deals = PipedriveDeal::where('status', 'open')->get();
$probabilities = $deals->pluck('pipedrive_data.probability');

// ✅ Good: Use helper methods
$probability = $deal->getPipedriveAttribute('probability', 0);

// ❌ Avoid: Repeated JSON parsing
foreach ($deals as $deal) {
    $data = json_decode($deal->pipedrive_data, true);  // Inefficient
    $probability = $data['probability'] ?? 0;
}
```

## 🚀 **Synchronization Performance**

### **Batch Processing**

```bash
# ✅ Good: Process in batches
php artisan pipedrive:sync-entities --entity=deals --limit=100

# ✅ Good: Sync specific entities
php artisan pipedrive:sync-entities --entity=users --limit=50
```

### **Optimized Sync Strategy**

```php
// The sync process is optimized with:
1. Batch API requests (100 items per request)
2. updateOrCreate() for efficient upserts
3. Minimal data transformation
4. Proper error handling and retry logic
5. Memory-efficient processing
```

### **Scheduled Synchronization**

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Sync critical entities more frequently
    $schedule->command('pipedrive:sync-entities --entity=deals --limit=200')
             ->everyFifteenMinutes()
             ->withoutOverlapping();

    // Sync less critical entities daily
    $schedule->command('pipedrive:sync-entities --entity=products --limit=500')
             ->daily()
             ->at('02:00');

    // Sync custom fields weekly
    $schedule->command('pipedrive:sync-custom-fields')
             ->weekly()
             ->sundays()
             ->at('03:00');
}
```

## 💾 **Caching Strategies**

### **Query Result Caching**

```php
use Illuminate\Support\Facades\Cache;

// Cache expensive queries
$activeUsers = Cache::remember('pipedrive.active_users', 3600, function () {
    return PipedriveUser::where('active_flag', true)
        ->withCount(['deals', 'activities'])
        ->get();
});

// Cache custom fields
$dealFields = Cache::remember('pipedrive.deal_fields', 86400, function () {
    return PipedriveCustomField::forEntity('deal')->active()->get();
});
```

### **Model Caching**

```php
// Add to your models
use Illuminate\Database\Eloquent\Model;

class PipedriveDeal extends Model
{
    // Cache relationships
    public function user()
    {
        return $this->belongsTo(PipedriveUser::class, 'user_id', 'pipedrive_id')
                    ->remember(3600); // Cache for 1 hour
    }
    
    // Cache computed properties
    public function getFormattedValueAttribute()
    {
        return Cache::remember("deal.{$this->id}.formatted_value", 3600, function () {
            return number_format($this->value, 2) . ' ' . $this->currency;
        });
    }
}
```

### **API Response Caching**

```php
// Cache Pipedrive API responses
$deals = Cache::remember('pipedrive.api.deals.recent', 900, function () {
    $client = app(PipedriveAuthService::class)->getClient();
    return $client->deals->all(['limit' => 100, 'sort' => 'update_time DESC']);
});
```

## 🔧 **Memory Optimization**

### **Chunk Processing**

```php
// ✅ Good: Process large datasets in chunks
PipedriveDeal::where('status', 'open')
    ->chunk(100, function ($deals) {
        foreach ($deals as $deal) {
            // Process each deal
            $this->processDeal($deal);
        }
    });

// ✅ Good: Use lazy collections for memory efficiency
PipedriveDeal::where('status', 'open')
    ->lazy()
    ->each(function ($deal) {
        $this->processDeal($deal);
    });
```

### **Selective Field Loading**

```php
// ✅ Good: Load only needed columns
$deals = PipedriveDeal::select('pipedrive_id', 'title', 'value', 'status')
    ->where('status', 'open')
    ->get();

// ✅ Good: Exclude heavy JSON data when not needed
$dealIds = PipedriveDeal::where('status', 'open')
    ->pluck('pipedrive_id');
```

## 📈 **Monitoring Performance**

### **Query Logging**

```php
// Enable query logging in development
DB::enableQueryLog();

// Your queries here
$deals = PipedriveDeal::with(['user', 'person'])->get();

// Check executed queries
$queries = DB::getQueryLog();
foreach ($queries as $query) {
    echo $query['query'] . " (" . $query['time'] . "ms)\n";
}
```

### **Performance Monitoring**

```php
// Add to your monitoring
use Illuminate\Support\Facades\Log;

$start = microtime(true);

// Your operation
$deals = PipedriveDeal::with(['user', 'person', 'organization'])->get();

$duration = (microtime(true) - $start) * 1000;
Log::info("Deal query took {$duration}ms", [
    'count' => $deals->count(),
    'memory' => memory_get_peak_usage(true)
]);
```

### **Database Profiling**

```sql
-- Check slow queries
SHOW PROCESSLIST;

-- Analyze query performance
EXPLAIN SELECT * FROM pipedrive_deals 
WHERE status = 'open' AND user_id = 123;

-- Check index usage
SHOW INDEX FROM pipedrive_deals;
```

## 🎯 **Best Practices Summary**

### **Query Optimization**

1. **Use indexes** - Always filter by indexed columns first
2. **Eager load** - Prevent N+1 queries with `with()`
3. **Select specific columns** - Don't load unnecessary data
4. **Chunk large datasets** - Process in batches for memory efficiency
5. **Cache results** - Cache expensive queries and computations

### **Synchronization Optimization**

1. **Batch processing** - Use `--limit` for manageable batches
2. **Selective sync** - Sync only needed entities
3. **Schedule wisely** - Spread sync operations across time
4. **Monitor errors** - Use `--verbose` for debugging
5. **Handle failures** - Implement retry logic for failed syncs

### **JSON Data Optimization**

1. **Index first** - Filter by indexed columns before JSON queries
2. **Virtual columns** - Create indexes on frequently queried JSON paths
3. **Batch access** - Use `pluck()` for multiple JSON values
4. **Helper methods** - Use `getPipedriveAttribute()` for consistency

### **Memory Management**

1. **Chunk processing** - Use `chunk()` or `lazy()` for large datasets
2. **Selective loading** - Load only needed columns and relationships
3. **Clear cache** - Reset query cache between operations
4. **Monitor usage** - Track memory consumption in long-running processes

Following these optimization strategies will ensure your Pipedrive integration performs efficiently at scale! ⚡
