# 🔗 Using Pipedrive Relations

## 🎯 **Available Relations**

All Pipedrive entities are now linked together via Eloquent relationships.

## 📋 **Relations by Entity**

### **PipedriveActivity**
```php
$activity = PipedriveActivity::with(['user', 'person', 'organization', 'deal'])->first();

// belongsTo relations
$activity->user;         // PipedriveUser (assigned)
$activity->person;       // PipedrivePerson
$activity->organization; // PipedriveOrganization
$activity->deal;         // PipedriveDeal
```

### **PipedriveDeal**
```php
$deal = PipedriveDeal::with(['user', 'person', 'organization', 'stage'])->first();

// belongsTo relations
$deal->user;         // PipedriveUser (owner)
$deal->person;       // PipedrivePerson
$deal->organization; // PipedriveOrganization
$deal->stage;        // PipedriveStage

// hasMany relations
$deal->activities;   // Collection of activities
$deal->notes;        // Collection of notes
$deal->files;        // Collection of files
```

### **PipedrivePerson**
```php
$person = PipedrivePerson::with(['owner', 'organization'])->first();

// belongsTo relations
$person->owner;        // PipedriveUser (owner)
$person->organization; // PipedriveOrganization

// hasMany relations
$person->activities;   // Activities linked to this person
$person->deals;        // Deals linked to this person
$person->notes;        // Notes linked to this person
$person->files;        // Files linked to this person
```

### **PipedriveOrganization**
```php
$org = PipedriveOrganization::with(['owner'])->first();

// belongsTo relations
$org->owner;       // PipedriveUser (owner)

// hasMany relations
$org->persons;     // Persons from this organization
$org->activities;  // Activities linked to this organization
$org->deals;       // Deals linked to this organization
$org->notes;       // Notes linked to this organization
$org->files;       // Files linked to this organization
```

### **PipedriveUser**
```php
$user = PipedriveUser::first();

// hasMany relations
$user->activities;           // Assigned activities
$user->deals;               // Owned deals
$user->notes;               // Created notes
$user->files;               // Uploaded files
$user->ownedPersons;        // Owned persons
$user->ownedOrganizations;  // Owned organizations
$user->ownedProducts;       // Owned products
$user->ownedGoals;          // Owned goals
```

### **PipedriveStage & PipedrivePipeline**
```php
$stage = PipedriveStage::with(['pipeline'])->first();
$stage->pipeline;  // PipedrivePipeline
$stage->deals;     // Deals in this stage

$pipeline = PipedrivePipeline::with(['stages'])->first();
$pipeline->stages; // Collection of stages
$pipeline->goals;  // Goals linked to this pipeline
```

## 🚀 **Advanced Usage Examples**

### **Get all deals from a user with their relations**
```php
$user = PipedriveUser::with([
    'deals.person',
    'deals.organization',
    'deals.stage.pipeline',
    'deals.activities'
])->find(1);

foreach ($user->deals as $deal) {
    echo "Deal: {$deal->title}\n";
    echo "Person: {$deal->person->name}\n";
    echo "Organization: {$deal->organization->name}\n";
    echo "Stage: {$deal->stage->name} (Pipeline: {$deal->stage->pipeline->name})\n";
    echo "Activities: {$deal->activities->count()}\n\n";
}
```

### **Get all activities from an organization**
```php
$org = PipedriveOrganization::with([
    'activities.user',
    'activities.deal'
])->find(1);

foreach ($org->activities as $activity) {
    echo "Activity: {$activity->subject}\n";
    echo "Assigned to: {$activity->user->name}\n";
    echo "Related deal: {$activity->deal->title}\n\n";
}
```

### **Statistics by pipeline**
```php
$pipeline = PipedrivePipeline::with(['stages.deals'])->find(1);

foreach ($pipeline->stages as $stage) {
    $totalValue = $stage->deals->sum('value');
    $dealCount = $stage->deals->count();

    echo "Stage: {$stage->name}\n";
    echo "Deals: {$dealCount}\n";
    echo "Total value: {$totalValue}\n\n";
}
```

### **Search with relations**
```php
// Deals with person and organization
$deals = PipedriveDeal::with(['person', 'organization'])
    ->whereHas('person', function($query) {
        $query->where('name', 'like', '%John%');
    })
    ->get();

// Overdue activities with user
$overdueActivities = PipedriveActivity::with(['user', 'deal'])
    ->where('done', false)
    ->where('due_date', '<', now())
    ->get();
```

### **Eager loading for performance**
```php
// Load all deal relations in a single query
$deal = PipedriveDeal::with([
    'user',
    'person.organization',
    'stage.pipeline',
    'activities.user',
    'notes.user',
    'files.user'
])->find(1);
```

## 🔧 **Relation Keys**

All relations use `pipedrive_id` as reference key:

```php
// Example belongsTo relation
public function user()
{
    return $this->belongsTo(PipedriveUser::class, 'user_id', 'pipedrive_id');
}

// Example hasMany relation
public function activities()
{
    return $this->hasMany(PipedriveActivity::class, 'deal_id', 'pipedrive_id');
}
```

## ✅ **Advantages**

1. **Intuitive navigation** between entities
2. **Eager loading** to optimize performance
3. **Relational queries** with `whereHas()` and `with()`
4. **More readable** and maintainable code
5. **Respect for Laravel/Eloquent conventions**

Relations are now ready to use! 🎉
