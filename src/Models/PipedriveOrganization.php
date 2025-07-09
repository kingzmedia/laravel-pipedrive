<?php

namespace Keggermont\LaravelPipedrive\Models;

use Illuminate\Database\Eloquent\Builder;
use Keggermont\LaravelPipedrive\Data\PipedriveOrganizationData;

class PipedriveOrganization extends BasePipedriveModel
{
    protected $table = 'pipedrive_organizations';

        protected $fillable = [
        'pipedrive_id',
        'name',
        'owner_id',
        'active_flag',
        'pipedrive_data',
        'pipedrive_add_time',
        'pipedrive_update_time',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'address' => 'array',
            'address_lat' => 'decimal:8',
            'address_lng' => 'decimal:8',
            'next_activity_date' => 'datetime',
            'last_activity_date' => 'datetime',
            'last_incoming_mail_time' => 'datetime',
            'last_outgoing_mail_time' => 'datetime',
        ]);
    }

    public static function getPipedriveEntityName(): string
    {
        return 'organizations';
    }

    protected static function getDtoClass(): string
    {
        return PipedriveOrganizationData::class;
    }

    // Scopes
    public function scopeForOwner(Builder $query, int $ownerId): Builder
    {
        return $query->where('owner_id', $ownerId);
    }

    public function scopeByCountry(Builder $query, string $countryCode): Builder
    {
        return $query->where('country_code', $countryCode);
    }

    public function scopeByCategory(Builder $query, string $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    // Helper methods
    public function hasAddress(): bool
    {
        return !empty($this->address_formatted) || ($this->address_lat && $this->address_lng);
    }

    public function getFormattedAddress(): ?string
    {
        return $this->address_formatted;
    }

    // Relations
    public function owner()
    {
        return $this->belongsTo(PipedriveUser::class, 'owner_id', 'pipedrive_id');
    }

    public function persons()
    {
        return $this->hasMany(PipedrivePerson::class, 'org_id', 'pipedrive_id');
    }

    public function activities()
    {
        return $this->hasMany(PipedriveActivity::class, 'org_id', 'pipedrive_id');
    }

    public function deals()
    {
        return $this->hasMany(PipedriveDeal::class, 'org_id', 'pipedrive_id');
    }

    public function notes()
    {
        return $this->hasMany(PipedriveNote::class, 'org_id', 'pipedrive_id');
    }

    public function files()
    {
        return $this->hasMany(PipedriveFile::class, 'org_id', 'pipedrive_id');
    }
}