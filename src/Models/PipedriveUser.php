<?php

namespace Skeylup\LaravelPipedrive\Models;

use Illuminate\Database\Eloquent\Builder;
use Skeylup\LaravelPipedrive\Data\PipedriveUserData;

class PipedriveUser extends BasePipedriveModel
{
    protected $table = 'pipedrive_users';

    protected $fillable = [
        'pipedrive_id',
        'name',
        'email',
        'is_admin',
        'active_flag',
        'pipedrive_data',
        'pipedrive_add_time',
        'pipedrive_update_time',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'activated' => 'boolean',
            'is_admin' => 'boolean',
            'is_you' => 'boolean',
            'signup_flow_variation' => 'boolean',
            'has_created_company' => 'boolean',
            'role_id' => 'integer',
            'icon_url' => 'integer',
            'created' => 'datetime',
            'modified' => 'datetime',
        ]);
    }

    public static function getPipedriveEntityName(): string
    {
        return 'users';
    }

    protected static function getDtoClass(): string
    {
        return PipedriveUserData::class;
    }

    // Scopes
    public function scopeActivated(Builder $query): Builder
    {
        return $query->where('activated', true);
    }

    public function scopeAdmins(Builder $query): Builder
    {
        return $query->where('is_admin', true);
    }

    public function scopeByRole(Builder $query, int $roleId): Builder
    {
        return $query->where('role_id', $roleId);
    }

    public function scopeByLocale(Builder $query, string $locale): Builder
    {
        return $query->where('locale', $locale);
    }

    public function scopeByTimezone(Builder $query, string $timezone): Builder
    {
        return $query->where('timezone_name', $timezone);
    }

    // Helper methods
    public function isActivated(): bool
    {
        return $this->activated ?? false;
    }

    public function isAdmin(): bool
    {
        return $this->is_admin ?? false;
    }

    public function isCurrentUser(): bool
    {
        return $this->is_you ?? false;
    }

    public function hasCreatedCompany(): bool
    {
        return $this->has_created_company ?? false;
    }

    public function getDisplayName(): string
    {
        return $this->name ?: $this->email;
    }

    public function getTimezoneDisplay(): ?string
    {
        if (! $this->timezone_name) {
            return null;
        }

        $offset = $this->timezone_offset ? " ({$this->timezone_offset})" : '';

        return $this->timezone_name.$offset;
    }

    // Relations
    public function activities()
    {
        return $this->hasMany(PipedriveActivity::class, 'user_id', 'pipedrive_id');
    }

    public function deals()
    {
        return $this->hasMany(PipedriveDeal::class, 'user_id', 'pipedrive_id');
    }

    public function notes()
    {
        return $this->hasMany(PipedriveNote::class, 'user_id', 'pipedrive_id');
    }

    public function files()
    {
        return $this->hasMany(PipedriveFile::class, 'user_id', 'pipedrive_id');
    }

    public function ownedPersons()
    {
        return $this->hasMany(PipedrivePerson::class, 'owner_id', 'pipedrive_id');
    }

    public function ownedOrganizations()
    {
        return $this->hasMany(PipedriveOrganization::class, 'owner_id', 'pipedrive_id');
    }

    public function ownedProducts()
    {
        return $this->hasMany(PipedriveProduct::class, 'owner_id', 'pipedrive_id');
    }

    public function ownedGoals()
    {
        return $this->hasMany(PipedriveGoal::class, 'owner_id', 'pipedrive_id');
    }
}
