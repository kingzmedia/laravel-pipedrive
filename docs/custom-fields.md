# 🎯 Custom Fields Management

## 🎯 **Overview**

Laravel-Pipedrive provides comprehensive support for Pipedrive custom fields, allowing you to sync, query, and manage all custom field definitions across all entity types.

## 🔄 **Synchronization**

### **Sync All Custom Fields**

```bash
# Sync all entity custom fields
php artisan pipedrive:sync-custom-fields

# Sync specific entity
php artisan pipedrive:sync-custom-fields --entity=deal

# Force update existing fields
php artisan pipedrive:sync-custom-fields --force

# Verbose output
php artisan pipedrive:sync-custom-fields --entity=person --verbose
```

### **Supported Entities**

| Entity | Description | Common Fields |
|--------|-------------|---------------|
| `deal` | Deal custom fields | Industry, Source, Priority |
| `person` | Person custom fields | Job Title, Department, Birthday |
| `organization` | Organization custom fields | Industry, Size, Type |
| `product` | Product custom fields | Category, SKU, Specifications |
| `activity` | Activity custom fields | Meeting Type, Outcome |
| `note` | Note custom fields | Category, Visibility |

## 📊 **Field Types**

### **Text Fields**
```php
// Short text (varchar)
'field_type' => 'varchar'     // Up to 255 characters

// Long text
'field_type' => 'text'        // Unlimited text

// Auto-complete text
'field_type' => 'varchar_auto' // Text with suggestions
```

### **Numeric Fields**
```php
// Numbers
'field_type' => 'double'      // Decimal numbers

// Currency
'field_type' => 'monetary'    // Money values with currency
```

### **Choice Fields**
```php
// Single choice (dropdown)
'field_type' => 'enum'
'options' => [
    ['id' => 1, 'label' => 'High'],
    ['id' => 2, 'label' => 'Medium'],
    ['id' => 3, 'label' => 'Low']
]

// Multiple choice (checkboxes)
'field_type' => 'set'
'options' => [
    ['id' => 1, 'label' => 'Email'],
    ['id' => 2, 'label' => 'Phone'],
    ['id' => 3, 'label' => 'Meeting']
]
```

### **Reference Fields**
```php
// User reference
'field_type' => 'user'        // Link to Pipedrive user

// Organization reference
'field_type' => 'org'         // Link to organization

// Person reference
'field_type' => 'people'      // Link to person
```

### **Date/Time Fields**
```php
// Date
'field_type' => 'date'        // Date picker

// Time
'field_type' => 'time'        // Time picker

// Date range
'field_type' => 'daterange'   // Start and end dates

// Time range
'field_type' => 'timerange'   // Start and end times
```

### **Special Fields**
```php
// Phone number
'field_type' => 'phone'       // Phone with formatting

// Address
'field_type' => 'address'     // Full address fields
```

## 🔍 **Querying Custom Fields**

### **Basic Queries**

```php
use Keggermont\LaravelPipedrive\Models\PipedriveCustomField;

// All active fields for deals
$dealFields = PipedriveCustomField::forEntity('deal')->active()->get();

// Only custom fields (not default Pipedrive fields)
$customFields = PipedriveCustomField::forEntity('deal')->customOnly()->get();

// Mandatory fields
$mandatoryFields = PipedriveCustomField::forEntity('deal')->mandatory()->get();

// Fields by type
$textFields = PipedriveCustomField::forEntity('deal')->ofType('varchar')->get();
$choiceFields = PipedriveCustomField::forEntity('deal')->ofType('enum')->get();
```

### **Advanced Queries**

```php
// Fields visible in add dialog
$addFields = PipedriveCustomField::forEntity('deal')->visibleInAdd()->get();

// Fields visible in detail view
$detailFields = PipedriveCustomField::forEntity('deal')->visibleInDetails()->get();

// Option-based fields (enum/set)
$optionFields = PipedriveCustomField::forEntity('deal')
    ->whereIn('field_type', ['enum', 'set'])
    ->get();

// Relation fields (user/org/people)
$relationFields = PipedriveCustomField::forEntity('deal')
    ->whereIn('field_type', ['user', 'org', 'people'])
    ->get();
```

## 🎨 **Using Custom Field Service**

```php
use Keggermont\LaravelPipedrive\Services\PipedriveCustomFieldService;

$service = app(PipedriveCustomFieldService::class);

// Get fields for entity
$dealFields = $service->getFieldsForEntity('deal');

// Get only custom fields
$customFields = $service->getCustomFieldsForEntity('deal');

// Get fields by type
$textFields = $service->getFieldsByType('deal', 'varchar');

// Get mandatory fields
$mandatoryFields = $service->getMandatoryFields('deal');

// Find field by key
$field = $service->findByKey('custom_field_hash', 'deal');

// Find field by Pipedrive ID
$field = $service->findById(12345, 'deal');
```

## 📋 **Field Properties**

### **Essential Properties**

```php
$field = PipedriveCustomField::first();

echo $field->pipedrive_id;    // Pipedrive field ID
echo $field->name;            // Human-readable name
echo $field->key;             // API key (hash or default)
echo $field->field_type;      // Field type
echo $field->entity_type;     // Entity (deal, person, etc.)
echo $field->active_flag;     // Active status
```

### **Extended Properties (JSON)**

```php
// Access via pipedrive_data
$orderNr = $field->pipedrive_data['order_nr'] ?? 0;
$mandatory = $field->pipedrive_data['mandatory_flag'] ?? false;
$options = $field->pipedrive_data['options'] ?? [];

// Or use helper methods
$field->isMandatory();        // boolean
$field->isCustomField();      // boolean (not default Pipedrive field)
$field->hasOptions();         // boolean (enum/set fields)
$field->getOptions();         // array of options
$field->isRelationType();     // boolean (user/org/people fields)
```

## 🔧 **Field Validation**

### **Check Field Requirements**

```php
$field = PipedriveCustomField::findByKey('priority_level', 'deal');

if ($field->isMandatory()) {
    echo "This field is required";
}

if ($field->hasOptions()) {
    $options = $field->getOptions();
    echo "Available options: " . implode(', ', array_column($options, 'label'));
}
```

### **Validate Field Values**

```php
// For enum fields
$field = PipedriveCustomField::findByKey('priority', 'deal');
$validOptions = array_column($field->getOptions(), 'id');

if (!in_array($value, $validOptions)) {
    throw new InvalidArgumentException('Invalid option value');
}

// For mandatory fields
if ($field->isMandatory() && empty($value)) {
    throw new InvalidArgumentException('Field is mandatory');
}
```

## 🎯 **Working with Field Data**

### **Accessing Custom Field Values in Entities**

```php
use Keggermont\LaravelPipedrive\Models\PipedriveDeal;

$deal = PipedriveDeal::first();

// Access custom field values from JSON data
$priority = $deal->pipedrive_data['custom_field_hash'] ?? null;
$industry = $deal->pipedrive_data['another_field_hash'] ?? null;

// Or use the helper method
$priority = $deal->getPipedriveAttribute('custom_field_hash');
$industry = $deal->getPipedriveAttribute('another_field_hash', 'default_value');
```

### **Setting Custom Field Values**

```php
// Set custom field value
$deal->setPipedriveAttribute('custom_field_hash', 'high_priority');
$deal->save();

// Or update the JSON directly
$data = $deal->pipedrive_data;
$data['custom_field_hash'] = 'high_priority';
$deal->pipedrive_data = $data;
$deal->save();
```

## 📊 **Field Statistics**

```php
// Get field usage statistics
$stats = PipedriveCustomField::forEntity('deal')
    ->selectRaw('field_type, count(*) as count')
    ->groupBy('field_type')
    ->get();

foreach ($stats as $stat) {
    echo "{$stat->field_type}: {$stat->count} fields\n";
}

// Count by visibility
$addVisibleCount = PipedriveCustomField::forEntity('deal')
    ->visibleInAdd()
    ->count();

$mandatoryCount = PipedriveCustomField::forEntity('deal')
    ->mandatory()
    ->count();
```

## 🔄 **Field Synchronization Process**

### **What Gets Synced**

1. **Field Metadata**: Name, type, key, entity
2. **Configuration**: Mandatory, visibility, edit permissions
3. **Options**: For enum/set fields
4. **Validation Rules**: Field constraints
5. **Display Settings**: Order, visibility flags

### **Sync Strategy**

```php
// The sync process:
1. Fetch fields from Pipedrive API
2. Skip system fields (no ID)
3. Create or update by pipedrive_id + entity_type
4. Store essential data in columns
5. Store complete data in JSON
6. Update timestamps
```

### **Error Handling**

```bash
# Common sync errors and solutions:

# Duplicate entry error
✗ Error: Duplicate entry for unique constraint
# Solution: Run with --force to update existing

# Invalid field type
✗ Error: Unknown field type 'custom_type'
# Solution: Update package to support new types

# API rate limit
✗ Error: Rate limit exceeded
# Solution: Wait and retry, or use smaller batches
```

## 🎨 **Display Helpers**

```php
$field = PipedriveCustomField::first();

// Get human-readable field type
echo $field->getFieldTypeDescription();
// Output: "Single Option" for enum, "Multiple Options" for set, etc.

// Check field capabilities
if ($field->isRelationType()) {
    echo "This field links to other entities";
}

if ($field->hasOptions()) {
    echo "This field has predefined options";
}
```

## 🚀 **Best Practices**

1. **Sync Regularly**: Keep field definitions up to date
2. **Use Service**: Leverage PipedriveCustomFieldService for complex queries
3. **Validate Data**: Always validate against field requirements
4. **Cache Results**: Cache field definitions for performance
5. **Handle Errors**: Implement proper error handling for field operations
6. **Monitor Changes**: Track when fields are added/modified in Pipedrive

The custom fields system provides complete flexibility while maintaining data integrity and validation! 🎯
