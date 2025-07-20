# 📊 Models & Relationships

## 🏗️ **Available Models**

Laravel-Pipedrive provides Eloquent models for all major Pipedrive entities:

| Model | Table | Description |
|-------|-------|-------------|
| `PipedriveActivity` | `pipedrive_activities` | Tasks, calls, meetings, emails |
| `PipedriveDeal` | `pipedrive_deals` | Sales opportunities |
| `PipedriveFile` | `pipedrive_files` | Attachments and documents |
| `PipedriveNote` | `pipedrive_notes` | Text notes and comments |
| `PipedriveOrganization` | `pipedrive_organizations` | Companies and organizations |
| `PipedrivePerson` | `pipedrive_persons` | Individual contacts |
| `PipedrivePipeline` | `pipedrive_pipelines` | Sales pipelines |
| `PipedriveProduct` | `pipedrive_products` | Products and services |
| `PipedriveStage` | `pipedrive_stages` | Pipeline stages |
| `PipedriveUser` | `pipedrive_users` | Pipedrive users |
| `PipedriveGoal` | `pipedrive_goals` | Sales goals and targets |
| `PipedriveCustomField` | `pipedrive_custom_fields` | Custom field definitions |

## 🔗 **Relationship Matrix**

### **BelongsTo Relationships (N:1)**

| Model | Relations |
|-------|-----------|
| **PipedriveActivity** | `user()`, `person()`, `organization()`, `deal()` |
| **PipedriveDeal** | `user()`, `person()`, `organization()`, `stage()` |
| **PipedriveFile** | `user()`, `person()`, `organization()`, `deal()` |
| **PipedriveNote** | `user()`, `person()`, `organization()`, `deal()` |
| **PipedrivePerson** | `owner()`, `organization()` |
| **PipedriveOrganization** | `owner()` |
| **PipedriveProduct** | `owner()` |
| **PipedriveStage** | `pipeline()` |
| **PipedriveGoal** | `owner()`, `pipeline()` |

### **HasMany Relationships (1:N)**

| Model | Relations |
|-------|-----------|
| **PipedriveUser** | `activities()`, `deals()`, `notes()`, `files()`, `ownedPersons()`, `ownedOrganizations()`, `ownedProducts()`, `ownedGoals()` |
| **PipedriveOrganization** | `persons()`, `activities()`, `deals()`, `notes()`, `files()` |
| **PipedrivePerson** | `activities()`, `deals()`, `notes()`, `files()` |
| **PipedriveDeal** | `activities()`, `notes()`, `files()` |
| **PipedrivePipeline** | `stages()`, `goals()` |
| **PipedriveStage** | `deals()` |

## 🎯 **Model Usage Examples**

### **PipedriveActivity**

```php
use Keggermont\LaravelPipedrive\Models\PipedriveActivity;

// Basic queries
$activities = PipedriveActivity::active()->get();
$pendingActivities = PipedriveActivity::pending()->get();
$overdueActivities = PipedriveActivity::overdue()->get();

// With relationships
$activity = PipedriveActivity::with(['user', 'person', 'deal'])->first();
echo $activity->user->name;         // Assigned user
echo $activity->person->name;       // Related person
echo $activity->deal->title;        // Related deal

// Scopes
$callActivities = PipedriveActivity::byType('call')->get();
$todayActivities = PipedriveActivity::dueToday()->get();
$userActivities = PipedriveActivity::forUser(123)->get();
```

### **PipedriveDeal**

```php
use Keggermont\LaravelPipedrive\Models\PipedriveDeal;

// Basic queries
$openDeals = PipedriveDeal::open()->get();
$wonDeals = PipedriveDeal::won()->get();
$highValueDeals = PipedriveDeal::where('value', '>', 10000)->get();

// With relationships
$deal = PipedriveDeal::with(['user', 'person', 'organization', 'stage'])->first();
echo $deal->user->name;             // Deal owner
echo $deal->person->name;           // Contact person
echo $deal->organization->name;     // Company
echo $deal->stage->name;            // Current stage

// Related entities
echo $deal->activities->count();    // Number of activities
echo $deal->notes->count();         // Number of notes
echo $deal->files->count();         // Number of files

// Scopes
$stageDeals = PipedriveDeal::byStage(15)->get();
$userDeals = PipedriveDeal::forUser(123)->get();
$personDeals = PipedriveDeal::forPerson(456)->get();
```

### **PipedriveUser**

```php
use Keggermont\LaravelPipedrive\Models\PipedriveUser;

// User with all related data
$user = PipedriveUser::with([
    'deals', 
    'activities', 
    'ownedPersons', 
    'ownedOrganizations'
])->first();

// Statistics
echo $user->deals->count();                    // Total deals
echo $user->deals->where('status', 'open')->count(); // Open deals
echo $user->activities->where('done', false)->count(); // Pending activities

// Owned entities
echo $user->ownedPersons->count();            // Persons owned
echo $user->ownedOrganizations->count();      // Organizations owned
echo $user->ownedProducts->count();           // Products owned
```

### **PipedriveOrganization**

```php
use Keggermont\LaravelPipedrive\Models\PipedriveOrganization;

// Organization with all related data
$org = PipedriveOrganization::with([
    'persons',
    'deals.stage',
    'activities.user'
])->first();

// Related data
echo $org->persons->count();                  // Number of contacts
echo $org->deals->count();                    // Number of deals
echo $org->activities->count();               // Number of activities

// Active deals value
$totalValue = $org->deals->where('status', 'open')->sum('value');
echo "Active deals value: {$totalValue}";
```

## 🔍 **Advanced Querying**

### **Eager Loading**

```php
// Load multiple relationships efficiently
$deals = PipedriveDeal::with([
    'user:pipedrive_id,name,email',
    'person:pipedrive_id,name,email',
    'organization:pipedrive_id,name',
    'stage:pipedrive_id,name,deal_probability',
    'activities' => function($query) {
        $query->where('done', false)->orderBy('due_date');
    }
])->get();
```

### **Relationship Queries**

```php
// Deals from organizations with specific criteria
$deals = PipedriveDeal::whereHas('organization', function($query) {
    $query->where('name', 'like', '%Tech%')
          ->where('active_flag', true);
})->get();

// Activities assigned to admin users
$activities = PipedriveActivity::whereHas('user', function($query) {
    $query->where('is_admin', true);
})->get();

// High-probability deals in specific pipeline
$deals = PipedriveDeal::whereHas('stage', function($query) {
    $query->where('deal_probability', '>', 70)
          ->whereHas('pipeline', function($subQuery) {
              $subQuery->where('name', 'Sales Pipeline');
          });
})->get();
```

### **Counting Relationships**

```php
// Users with deal and activity counts
$users = PipedriveUser::withCount([
    'deals',
    'activities',
    'deals as open_deals_count' => function($query) {
        $query->where('status', 'open');
    },
    'activities as pending_activities_count' => function($query) {
        $query->where('done', false);
    }
])->get();

foreach ($users as $user) {
    echo "{$user->name}: {$user->deals_count} deals, {$user->open_deals_count} open\n";
}
```

## 🎨 **Custom Scopes**

All models include useful scopes for common queries:

### **Common Scopes (All Models)**
- `active()` - Only active records
- `byPipedriveId($id)` - Find by Pipedrive ID

### **Activity-Specific Scopes**
- `done()` - Completed activities
- `pending()` - Incomplete activities
- `overdue()` - Past due activities
- `dueToday()` - Due today
- `byType($type)` - Filter by activity type
- `forUser($userId)` - Activities for specific user
- `forDeal($dealId)` - Activities for specific deal

### **Deal-Specific Scopes**
- `open()` - Open deals
- `won()` - Won deals
- `lost()` - Lost deals
- `byStage($stageId)` - Deals in specific stage
- `forUser($userId)` - Deals owned by user
- `forPerson($personId)` - Deals for specific person

## 🔧 **Model Methods**

### **Activity Methods**
```php
$activity = PipedriveActivity::first();

$activity->isDone();                    // boolean
$activity->isPending();                 // boolean
$activity->isOverdue();                 // boolean
$activity->getFormattedDuration();      // "2h 30m"
```

### **Deal Methods**
```php
$deal = PipedriveDeal::first();

$deal->isOpen();                        // boolean
$deal->isWon();                         // boolean
$deal->isLost();                        // boolean
$deal->getFormattedValue();             // "$1,500.00 USD"
$deal->getWeightedValue();              // Value * probability
$deal->getProbabilityPercentage();      // "75%"
```

### **User Methods**
```php
$user = PipedriveUser::first();

$user->isAdmin();                       // boolean
$user->isCurrentUser();                 // boolean
$user->getDisplayName();                // Name or email
$user->getTimezoneDisplay();            // "Europe/Paris (+02:00)"
```

This comprehensive relationship system allows you to navigate seamlessly between all Pipedrive entities using Laravel's powerful Eloquent ORM! 🚀
