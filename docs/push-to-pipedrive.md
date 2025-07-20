# 🚀 Push to Pipedrive & Display Details

The `HasPipedriveEntity` trait includes advanced methods for synchronizing your modifications to Pipedrive and displaying details with readable custom field names.

## 🚀 **Push Modifications to Pipedrive**

### **`pushToPipedrive()` Method**

This method allows you to push modifications to Pipedrive and update your local database. **By default, it uses a job for asynchronous execution.**

```php
public function pushToPipedrive(
    array $modifications,
    array $customFields = [],
    bool $forceSync = false,
    ?string $queue = null,
    int $maxRetries = 3
): array
```

#### **Parameters:**
- **`$modifications`** : Array of base fields to modify
- **`$customFields`** : Array of custom fields to modify (readable name => value)
- **`$forceSync`** : Force synchronous execution (default: false = asynchronous)
- **`$queue`** : Specific queue to use (default: default queue)
- **`$maxRetries`** : Maximum number of attempts (default: 3)

#### **Return:**
```php
// Asynchronous execution (default)
[
    'success' => true|false,
    'pipedrive_id' => 123,
    'entity_type' => 'deals',
    'updated_fields' => ['title', 'value', 'Custom Field Name'],
    'processed_via' => 'job',
    'queue' => 'default',
    'max_retries' => 3,
    'job_dispatched' => true,
    'error' => 'Error message' // If success = false
]

// Synchronous execution (forceSync = true)
[
    'success' => true|false,
    'pipedrive_id' => 123,
    'entity_type' => 'deals',
    'updated_fields' => ['title', 'value', 'Custom Field Name'],
    'response' => [...], // Complete Pipedrive API response
    'processed_via' => 'sync',
    'error' => 'Error message' // If success = false
]
```

## 🔄 **Execution Modes**

### **Asynchronous Mode (default - recommended)**

By default, modifications are sent via a Laravel job for better performance and reliability:

```php
// Asynchronous execution (default)
$result = $order->pushToPipedrive([
    'title' => 'Updated Order',
    'value' => 1500.00,
]);

if ($result['success'] && $result['job_dispatched']) {
    echo "Job dispatched on queue: " . $result['queue'];
    echo "Max retries: " . $result['max_retries'];
}
```

**Advantages:**
- ✅ Non-blocking for user
- ✅ Automatic retry on failure
- ✅ Load spike management
- ✅ Detailed attempt logs

### **Synchronous Mode (immediate)**

For cases where you need an immediate response:

```php
// Synchronous execution
$result = $order->pushToPipedrive([
    'title' => 'Updated Order',
    'value' => 1500.00,
], [], true); // forceSync = true

if ($result['success']) {
    echo "Immediate synchronization successful!";
    echo "Pipedrive response: " . json_encode($result['response']);
}
```

**Use synchronous mode for:**
- 🔄 Immediate validation required
- 🧪 Testing and development
- ⚡ Real-time critical operations

### **Usage Examples**

#### **Modify base fields (asynchronous)**
```php
$order = Order::find(1);

$result = $order->pushToPipedrive([
    'title' => 'Updated Order',
    'value' => 1500.00,
    'status' => 'won',
]);

if ($result['success']) {
    if ($result['processed_via'] === 'job') {
        echo "Job dispatched successfully!";
    } else {
        echo "Immediate synchronization successful!";
    }
} else {
    echo "Error: " . $result['error'];
}
```

#### **Modify custom fields**
```php
$result = $order->pushToPipedrive([], [
    'Order Number' => 'ORD-001',
    'Delivery Date' => '2024-02-15',
    'Special Instructions' => 'Fragile',
]);
```

#### **Combined modification with custom queue**
```php
$result = $order->pushToPipedrive([
    'title' => 'New Order',
    'value' => 2000.00,
], [
    'Order Number' => $order->order_number,
    'Customer Email' => $order->customer_email,
    'Internal Status' => $order->status,
], false, 'high-priority', 5); // High priority queue, 5 attempts
```

## 🎯 **Queue Management**

### **Priority Queues**

Organize your modifications by priority with dedicated queues:

```php
// High priority - critical modifications
$result = $order->pushToPipedrive($modifications, $customFields, false, 'high-priority');

// Normal priority - default queue
$result = $order->pushToPipedrive($modifications, $customFields);

// Low priority - non-urgent modifications
$result = $order->pushToPipedrive($modifications, $customFields, false, 'low-priority');
```

### **Queue Configuration**

In your `config/queue.php`:

```php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 90,
        'block_for' => null,
    ],
],

// Specialized queues
'pipedrive-queues' => [
    'high-priority' => 'pipedrive-high',
    'default' => 'pipedrive-default',
    'low-priority' => 'pipedrive-low',
],
```

### **Workers by Priority**

Launch dedicated workers:

```bash
# High priority worker (more workers)
php artisan queue:work --queue=high-priority --tries=5

# Normal priority worker
php artisan queue:work --queue=default --tries=3

# Low priority worker
php artisan queue:work --queue=low-priority --tries=2
```

## 📋 **Display Details**

### **`displayPipedriveDetails()` Method**

This method displays Pipedrive entity details with readable custom field names.

```php
public function displayPipedriveDetails(bool $includeCustomFields = true): array
```

#### **Parameters:**
- **`$includeCustomFields`** : Include custom fields (default: true)

#### **Return:**
```php
[
    'entity_type' => 'deals',
    'pipedrive_id' => 123,
    'local_id' => 45,
    'has_entity' => true,
    'basic_fields' => [
        'title' => 'My Order',
        'value' => 1500.00,
        'status' => 'open',
        // ...
    ],
    'custom_fields' => [
        'Order Number' => [
            'value' => 'ORD-001',
            'raw_value' => 'ORD-001',
            'field_type' => 'varchar',
            'key' => 'dcf558aac1ae4e8c4f849ba5e668430d8df9be12',
        ],
        // ...
    ],
    'metadata' => [
        'created_at' => '2024-01-01 10:00:00',
        'updated_at' => '2024-01-02 15:30:00',
        'local_created_at' => '2024-01-01 10:05:00',
        'local_updated_at' => '2024-01-02 15:35:00',
    ]
]
```

### **Usage Examples**

#### **Complete Display**
```php
$details = $order->displayPipedriveDetails();

if ($details['has_entity']) {
    echo "Pipedrive ID: " . $details['pipedrive_id'] . "\n";

    echo "Basic fields:\n";
    foreach ($details['basic_fields'] as $field => $value) {
        echo "  {$field}: {$value}\n";
    }

    echo "Custom fields:\n";
    foreach ($details['custom_fields'] as $name => $fieldData) {
        echo "  {$name}: {$fieldData['value']}\n";
    }
} else {
    echo "No linked Pipedrive entity\n";
}
```

#### **Display without custom fields**
```php
$details = $order->displayPipedriveDetails(false);
// Only basic fields will be included
```

## 🔧 **Custom Fields Mapping**

### **Automatic Operation**

The system automatically maps custom fields:

1. **By name** : First searches by readable field name
2. **By key** : If name doesn't work, searches by Pipedrive key
3. **Logging** : Fields not found are logged with list of available fields

### **Mapping Example**

```php
// In Pipedrive, you have a custom field:
// - Name: "Order Number"
// - Key: "dcf558aac1ae4e8c4f849ba5e668430d8df9be12"

// You can use the readable name:
$order->pushToPipedrive([], [
    'Order Number' => 'ORD-001'
]);

// Or the Pipedrive key:
$order->pushToPipedrive([], [
    'dcf558aac1ae4e8c4f849ba5e668430d8df9be12' => 'ORD-001'
]);
```

## 🎯 **Supported Field Types**

### **Basic Fields**
- `title`, `value`, `currency`, `status`
- `stage_id`, `user_id`, `person_id`, `org_id`
- All fields in the model's `$fillable`

### **Custom Fields**
- **Text** (`varchar`, `text`) : Simple text
- **Number** (`int`, `double`) : Numbers
- **Money** (`monetary`) : Monetary values
- **Date** (`date`) : Dates (Y-m-d format)
- **DateTime** (`datetime`) : Date and time
- **Options** (`enum`, `set`) : Option lists
- **Relations** (`user`, `org`, `people`) : References to other entities

## 🔄 **Error Handling**

### **Common Errors**

```php
$result = $order->pushToPipedrive($modifications, $customFields);

if (!$result['success']) {
    switch ($result['error']) {
        case 'No primary Pipedrive entity found for this model':
            // Model is not linked to a Pipedrive entity
            break;
            
        case 'Pipedrive API update failed':
            // Problem with Pipedrive API
            break;

        default:
            // Other error
            Log::error('Pipedrive sync error', $result);
    }
}
```

### **Automatic Logging**

The system automatically logs:
- ✅ **Success** : Applied modifications with details
- ❌ **Errors** : Errors with complete context
- ⚠️ **Warnings** : Custom fields not found

## 🏗️ **Integration in Your Models**

### **Complete Example in a Model**

```php
class Order extends Model
{
    use HasPipedriveEntity;

    protected PipedriveEntityType $pipedriveEntityType = PipedriveEntityType::DEALS;

    public function markAsCompleted(string $notes = null): bool
    {
        $result = $this->pushToPipedrive([
            'status' => 'won',
        ], [
            'Order Status' => 'Completed',
            'Completion Notes' => $notes ?? 'No notes',
            'Completion Date' => now()->format('Y-m-d H:i:s'),
        ]);

        if ($result['success']) {
            $this->status = 'completed';
            $this->completed_at = now();
            $this->save();

            return true;
        }

        return false;
    }

    public function getFullDetails(): array
    {
        return [
            'order' => $this->toArray(),
            'pipedrive' => $this->displayPipedriveDetails(),
        ];
    }
}
```

## 🔒 **Best Practices**

1. **Always check the return** of `pushToPipedrive()`
2. **Use readable names** for custom fields
3. **Log errors** for debugging
4. **Test with real data** before production
5. **Synchronize custom fields** regularly with `php artisan pipedrive:sync-custom-fields`

The push to Pipedrive system provides powerful synchronization capabilities while maintaining data integrity and user experience! 🚀
