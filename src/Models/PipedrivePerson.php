<?php

namespace Skeylup\LaravelPipedrive\Models;

use Illuminate\Database\Eloquent\Builder;
use Skeylup\LaravelPipedrive\Data\PipedrivePersonData;

class PipedrivePerson extends BasePipedriveModel
{
    protected $table = 'pipedrive_persons';

    protected $fillable = [
        'pipedrive_id',
        'name',
        'email',
        'phone',
        'org_id',
        'owner_id',
        'active_flag',
        'pipedrive_data',
        'pipedrive_add_time',
        'pipedrive_update_time',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'phone' => 'array',
            'email' => 'array',
            'label' => 'array',
            'label_ids' => 'array',
            'next_activity_date' => 'datetime',
            'last_activity_date' => 'datetime',
            'last_incoming_mail_time' => 'datetime',
            'last_outgoing_mail_time' => 'datetime',
        ]);
    }

    public static function getPipedriveEntityName(): string
    {
        return 'persons';
    }

    protected static function getDtoClass(): string
    {
        return PipedrivePersonData::class;
    }

    // Scopes
    public function scopeForOwner(Builder $query, int $ownerId): Builder
    {
        return $query->where('owner_id', $ownerId);
    }

    public function scopeForOrganization(Builder $query, int $orgId): Builder
    {
        return $query->where('org_id', $orgId);
    }

    public function scopeByMarketingStatus(Builder $query, string $status): Builder
    {
        return $query->where('marketing_status', $status);
    }

    // Helper methods
    public function getPrimaryEmail(): ?string
    {
        if (! $this->email || ! is_array($this->email)) {
            return null;
        }

        foreach ($this->email as $email) {
            if (isset($email['primary']) && $email['primary']) {
                return $email['value'];
            }
        }

        return $this->email[0]['value'] ?? null;
    }

    public function getPrimaryPhone(): ?string
    {
        if (! $this->phone || ! is_array($this->phone)) {
            return null;
        }

        foreach ($this->phone as $phone) {
            if (isset($phone['primary']) && $phone['primary']) {
                return $phone['value'];
            }
        }

        return $this->phone[0]['value'] ?? null;
    }

    public function getFullName(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? '')) ?: $this->name;
    }

    // Relations
    public function owner()
    {
        return $this->belongsTo(PipedriveUser::class, 'owner_id', 'pipedrive_id');
    }

    public function organization()
    {
        return $this->belongsTo(PipedriveOrganization::class, 'org_id', 'pipedrive_id');
    }

    public function activities()
    {
        return $this->hasMany(PipedriveActivity::class, 'person_id', 'pipedrive_id');
    }

    public function deals()
    {
        return $this->hasMany(PipedriveDeal::class, 'person_id', 'pipedrive_id');
    }

    public function notes()
    {
        return $this->hasMany(PipedriveNote::class, 'person_id', 'pipedrive_id');
    }

    public function files()
    {
        return $this->hasMany(PipedriveFile::class, 'person_id', 'pipedrive_id');
    }
}
