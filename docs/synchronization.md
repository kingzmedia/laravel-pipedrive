# 🔄 Data Synchronization

## 🎯 **Overview**

Laravel-Pipedrive provides powerful synchronization commands to keep your local database in sync with Pipedrive. The package supports both entity synchronization and custom field management.

## 📊 **Entity Synchronization**

### **Basic Usage**

```bash
# Sync all entities
php artisan pipedrive:sync-entities

# Sync specific entity
php artisan pipedrive:sync-entities --entity=deals

# Limit number of records
php artisan pipedrive:sync-entities --entity=activities --limit=50

# Force update existing records
php artisan pipedrive:sync-entities --entity=users --force

# Verbose output for debugging
php artisan pipedrive:sync-entities --entity=deals --verbose
```

### **Available Entities**

| Entity | Description | Typical Use |
|--------|-------------|-------------|
| `activities` | Tasks, calls, meetings | Daily activity tracking |
| `deals` | Sales opportunities | Pipeline management |
| `files` | Documents and attachments | Document management |
| `goals` | Sales targets | Performance tracking |
| `notes` | Text notes and comments | Communication history |
| `organizations` | Companies | Account management |
| `persons` | Individual contacts | Contact management |
| `pipelines` | Sales pipelines | Process management |
| `products` | Products and services | Catalog management |
| `stages` | Pipeline stages | Sales process |
| `users` | Pipedrive users | Team management |

### **Synchronization Strategy**

The sync process follows this strategy:

1. **Fetch data** from Pipedrive API
2. **Check existing records** by `pipedrive_id`
3. **Create or update** records using `updateOrCreate()`
4. **Store essential data** in typed columns
5. **Store complete data** in JSON `pipedrive_data` field
6. **Update timestamps** for tracking

```php
// Example of what happens during sync
$data = $pipedrive->deals->all(['limit' => 100]);

foreach ($data as $item) {
    PipedriveDeal::updateOrCreate(
        ['pipedrive_id' => $item['id']],
        [
            'title' => $item['title'],
            'value' => $item['value'],
            'currency' => $item['currency'],
            'status' => $item['status'],
            'stage_id' => $item['stage_id'],
            'person_id' => $item['person_id'],
            'org_id' => $item['org_id'],
            'user_id' => $item['user_id'],
            'active_flag' => $item['active_flag'],
            'pipedrive_data' => $item, // Complete data
            'pipedrive_add_time' => $item['add_time'],
            'pipedrive_update_time' => $item['update_time'],
        ]
    );
}
```

## 🎯 **Custom Fields Synchronization**

### **Basic Usage**

```bash
# Sync all custom fields
php artisan pipedrive:sync-custom-fields

# Sync specific entity fields
php artisan pipedrive:sync-custom-fields --entity=deal

# Force update existing fields
php artisan pipedrive:sync-custom-fields --force

# Verbose output
php artisan pipedrive:sync-custom-fields --entity=person --verbose
```

### **Supported Entities**

| Entity | Pipedrive API | Description |
|--------|---------------|-------------|
| `deal` | `/dealFields` | Deal custom fields |
| `person` | `/personFields` | Person custom fields |
| `organization` | `/organizationFields` | Organization custom fields |
| `product` | `/productFields` | Product custom fields |
| `activity` | `/activityFields` | Activity custom fields |
| `note` | `/noteFields` | Note custom fields |

### **Field Types Supported**

| Type | Description | Example |
|------|-------------|---------|
| `varchar` | Short text (up to 255 chars) | Name, title |
| `text` | Long text | Description, notes |
| `double` | Numerical values | Price, quantity |
| `monetary` | Currency values | Revenue, cost |
| `set` | Multiple choice | Tags, categories |
| `enum` | Single choice | Status, priority |
| `user` | User reference | Owner, assignee |
| `org` | Organization reference | Parent company |
| `people` | Person reference | Contact person |
| `phone` | Phone number | Contact info |
| `time` | Time value | Meeting time |
| `date` | Date value | Deadline |
| `address` | Address | Location |

## ⚙️ **Configuration**

### **Sync Settings**

Configure synchronization in `config/pipedrive.php`:

```php
'sync' => [
    // Automatically sync custom fields on app boot
    'auto_sync' => env('PIPEDRIVE_AUTO_SYNC', false),

    // Entities to sync (leave empty to sync all)
    'entities' => [
        'deal',
        'person',
        'organization',
        'product',
        'activity',
    ],
],
```

### **Rate Limiting**

Pipedrive API has rate limits. The package handles this automatically:

- **API Token**: 10,000 requests per day
- **OAuth**: Higher limits based on plan
- **Automatic retry** on rate limit errors
- **Exponential backoff** for failed requests

## 🔍 **Monitoring Synchronization**

### **Verbose Output**

Use `--verbose` flag to see detailed synchronization progress:

```bash
php artisan pipedrive:sync-entities --entity=deals --verbose
```

Output example:
```
Starting Pipedrive entities synchronization...
Connected to Pipedrive as: John Doe (Acme Corp)
Using authentication method: token
Syncing deals...
  ✓ Created: New Deal Opportunity (ID: 12345)
  ↻ Updated: Existing Deal (ID: 12346)
  ⚠ Skipped: Inactive Deal (ID: 12347)
  deals: 1 created, 1 updated, 1 skipped
Entities synchronization completed!
```

### **Error Handling**

The sync process includes comprehensive error handling:

```bash
# Errors are logged and displayed
  ✗ Error processing deal 12345: Invalid stage_id
  ✗ Error processing deal 12346: Missing required field 'title'
```

### **Logging**

All sync operations are logged to Laravel's log system:

```php
// Check logs for sync issues
tail -f storage/logs/laravel.log | grep pipedrive
```

## 📈 **Performance Optimization**

### **Batch Processing**

Use limits to process data in batches:

```bash
# Process in smaller batches
php artisan pipedrive:sync-entities --entity=deals --limit=50
```

### **Selective Synchronization**

Sync only what you need:

```bash
# Sync only recent activities
php artisan pipedrive:sync-entities --entity=activities --limit=100

# Sync only specific entity types
php artisan pipedrive:sync-entities --entity=users
```

### **Scheduling**

Set up automatic synchronization with Laravel's scheduler:

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Sync entities daily
    $schedule->command('pipedrive:sync-entities --limit=500')
             ->daily()
             ->at('02:00');

    // Sync custom fields weekly
    $schedule->command('pipedrive:sync-custom-fields')
             ->weekly()
             ->sundays()
             ->at('03:00');
}
```

## 🔧 **Troubleshooting**

### **Common Issues**

1. **Connection Errors**
   ```bash
   # Test your connection first
   php artisan pipedrive:test-connection
   ```

2. **Rate Limit Exceeded**
   ```bash
   # Use smaller batches
   php artisan pipedrive:sync-entities --limit=25
   ```

3. **Memory Issues**
   ```bash
   # Increase memory limit
   php -d memory_limit=512M artisan pipedrive:sync-entities
   ```

4. **Timeout Issues**
   ```bash
   # Sync specific entities
   php artisan pipedrive:sync-entities --entity=deals
   ```

### **Debug Mode**

Enable verbose output for debugging:

```bash
php artisan pipedrive:sync-entities --verbose
```

This will show:
- Connection details
- Processing steps
- Error stack traces
- Performance metrics

## 🎯 **Best Practices**

1. **Start Small**: Begin with limited records to test
2. **Use Scheduling**: Automate regular synchronization
3. **Monitor Logs**: Check for errors and performance issues
4. **Test Connection**: Always verify API connectivity first
5. **Backup Data**: Backup before major sync operations
6. **Use Verbose**: Enable verbose mode for troubleshooting

The synchronization system ensures your local data stays current with Pipedrive while providing flexibility and error resilience! 🚀
