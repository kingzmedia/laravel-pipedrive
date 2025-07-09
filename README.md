# 🚀 Laravel Pipedrive Integration

[![Latest Version on Packagist](https://img.shields.io/packagist/v/kingzmedia/laravel-pipedrive.svg?style=flat-square)](https://packagist.org/packages/kingzmedia/laravel-pipedrive)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/kingzmedia/laravel-pipedrive/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/kingzmedia/laravel-pipedrive/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/kingzmedia/laravel-pipedrive/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/kingzmedia/laravel-pipedrive/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/kingzmedia/laravel-pipedrive.svg?style=flat-square)](https://packagist.org/packages/kingzmedia/laravel-pipedrive)

A comprehensive Laravel package for seamless Pipedrive CRM integration. Sync entities, manage custom fields, and leverage Eloquent relationships with a robust JSON-based data structure for maximum flexibility and performance.

## ✨ **Features**

- 🔄 **Complete Entity Synchronization** - Activities, Deals, Files, Notes, Organizations, Persons, Pipelines, Products, Stages, Users, Goals
- 🔔 **Real-Time Webhooks** - Instant synchronization with secure webhook handling
- 🔗 **Eloquent Relationships** - Navigate between entities with Laravel's relationship system
- 🎯 **Custom Fields Management** - Full support for Pipedrive custom fields with validation
- 🏗️ **Hybrid Data Structure** - Essential columns + JSON storage for maximum flexibility
- 🔐 **Dual Authentication** - Support for both API tokens and OAuth
- ⚡ **Performance Optimized** - Efficient queries with proper indexing
- 📊 **Rich Querying** - Advanced filtering and relationship queries

## 📦 **Installation**

Install the package via Composer:

```bash
composer require kingzmedia/laravel-pipedrive
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="laravel-pipedrive-migrations"
php artisan migrate
```

Publish the config file:

```bash
php artisan vendor:publish --tag="laravel-pipedrive-config"
```

## ⚙️ **Configuration**

Add your Pipedrive credentials to your `.env` file:

### **API Token Authentication (Recommended)**
```env
PIPEDRIVE_TOKEN=your_api_token_here
```

### **OAuth Authentication**
```env
PIPEDRIVE_CLIENT_ID=your_client_id
PIPEDRIVE_CLIENT_SECRET=your_client_secret
PIPEDRIVE_REDIRECT_URL=https://your-app.com/pipedrive/callback
```

## 🚀 **Quick Start**

### **Test Your Connection**
```bash
php artisan pipedrive:test-connection
```

### **Sync Entities from Pipedrive**
```bash
# Sync all entities
php artisan pipedrive:sync-entities

# Sync specific entity
php artisan pipedrive:sync-entities --entity=deals --limit=50

# Verbose output
php artisan pipedrive:sync-entities --entity=users --verbose
```

### **Sync Custom Fields**
```bash
# Sync all custom fields
php artisan pipedrive:sync-custom-fields

# Sync specific entity fields
php artisan pipedrive:sync-custom-fields --entity=deal --verbose
```

### **Real-Time Webhooks**
```bash
# Setup webhook for real-time sync
php artisan pipedrive:webhooks create \
    --url=https://your-app.com/pipedrive/webhook \
    --event=*.* \
    --auth-user=webhook_user \
    --auth-pass=secure_password

# List existing webhooks
php artisan pipedrive:webhooks list

# Test webhook endpoint
curl https://your-app.com/pipedrive/webhook/health
```

## 📊 **Models & Relationships**

All Pipedrive entities are available as Eloquent models with full relationship support:

```php
use Keggermont\LaravelPipedrive\Models\{
    PipedriveActivity, PipedriveDeal, PipedriveFile, PipedriveNote,
    PipedriveOrganization, PipedrivePerson, PipedrivePipeline,
    PipedriveProduct, PipedriveStage, PipedriveUser, PipedriveGoal
};

// Navigate relationships
$deal = PipedriveDeal::with(['user', 'person', 'organization', 'stage'])->first();
echo $deal->user->name;         // Deal owner
echo $deal->person->name;       // Contact person
echo $deal->organization->name; // Company
echo $deal->stage->name;        // Current stage

// Reverse relationships
$user = PipedriveUser::with(['deals', 'activities'])->first();
echo $user->deals->count();     // Number of deals
echo $user->activities->count(); // Number of activities
```

## 🔍 **Querying Data**

### **Basic Queries**
```php
// Active deals with high value
$deals = PipedriveDeal::where('status', 'open')
    ->where('value', '>', 10000)
    ->active()
    ->get();

// Overdue activities
$activities = PipedriveActivity::where('done', false)
    ->where('due_date', '<', now())
    ->with('user')
    ->get();
```

### **Relationship Queries**
```php
// Deals from specific organization
$deals = PipedriveDeal::whereHas('organization', function($query) {
    $query->where('name', 'like', '%Acme Corp%');
})->get();

// Activities assigned to specific user
$activities = PipedriveActivity::whereHas('user', function($query) {
    $query->where('email', 'john@example.com');
})->get();
```

### **JSON Data Access**
```php
$activity = PipedriveActivity::first();

// Essential data (columns)
echo $activity->subject;
echo $activity->type;
echo $activity->done;

// Extended data (JSON)
echo $activity->getPipedriveAttribute('note');
echo $activity->getPipedriveAttribute('duration');
echo $activity->getPipedriveAttribute('location');

// Direct JSON access
$allData = $activity->pipedrive_data;
$customField = $activity->pipedrive_data['custom_field_hash'] ?? null;
```

## 🎯 **Custom Fields**

```php
use Keggermont\LaravelPipedrive\Models\PipedriveCustomField;

// Get all deal fields
$dealFields = PipedriveCustomField::forEntity('deal')->active()->get();

// Get only custom fields (not default Pipedrive fields)
$customFields = PipedriveCustomField::forEntity('deal')->customOnly()->get();

// Get mandatory fields
$mandatoryFields = PipedriveCustomField::forEntity('deal')->mandatory()->get();

// Access field properties
foreach ($dealFields as $field) {
    echo "Field: {$field->name} ({$field->field_type})\n";
    echo "Mandatory: " . ($field->isMandatory() ? 'Yes' : 'No') . "\n";
    
    if ($field->hasOptions()) {
        echo "Options: " . implode(', ', $field->getOptions()) . "\n";
    }
}
```

## 📚 **Documentation**

- [📖 **Models & Relationships**](docs/models-relationships.md) - Complete guide to all models and their relationships
- [🔄 **Data Synchronization**](docs/synchronization.md) - Entity and custom field sync strategies
- [🔔 **Real-Time Webhooks**](docs/webhooks.md) - Instant synchronization with webhook handling
- [🎯 **Custom Fields**](docs/custom-fields.md) - Working with Pipedrive custom fields
- [🏗️ **Database Structure**](docs/database-structure.md) - Understanding the hybrid data approach
- [⚡ **Performance**](docs/performance.md) - Optimization tips and best practices
- [🔐 **Authentication**](docs/authentication.md) - API token and OAuth setup

## 🛠️ **Commands Reference**

| Command | Description | Options |
|---------|-------------|---------|
| `pipedrive:test-connection` | Test Pipedrive API connection | - |
| `pipedrive:sync-entities` | Sync Pipedrive entities | `--entity`, `--limit`, `--force`, `--verbose` |
| `pipedrive:sync-custom-fields` | Sync custom fields | `--entity`, `--force`, `--verbose` |
| `pipedrive:webhooks` | Manage webhooks (list/create/delete) | `--url`, `--event`, `--id`, `--verbose` |

## 🏗️ **Database Structure**

Each Pipedrive entity table follows this hybrid structure:

```sql
-- Essential columns (indexed, queryable)
id                    -- Laravel auto-increment
pipedrive_id          -- Unique Pipedrive ID
name/title/subject    -- Main identifier
[relationships]       -- Foreign keys (user_id, person_id, etc.)
active_flag           -- Status flag

-- JSON storage (flexible, all other data)
pipedrive_data        -- Complete Pipedrive data as JSON

-- Timestamps
pipedrive_add_time    -- Pipedrive creation time
pipedrive_update_time -- Pipedrive modification time
created_at/updated_at -- Laravel timestamps
```

## 🤝 **Contributing**

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## 🔒 **Security**

If you discover any security-related issues, please email security@kingzmedia.com instead of using the issue tracker.

## 📄 **License**

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## 🙏 **Credits**

- Built on top of [devio/pipedrive](https://github.com/IsraelOrtuno/pipedrive)
- Uses [spatie/laravel-package-tools](https://github.com/spatie/laravel-package-tools)
- Powered by [Laravel](https://laravel.com)
