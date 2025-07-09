<?php

namespace Keggermont\LaravelPipedrive\Models;

use Illuminate\Database\Eloquent\Builder;
use Keggermont\LaravelPipedrive\Data\PipedriveOrganizationData;

class PipedriveOrganization extends BasePipedriveModel
{
    protected $table = 'pipedrive_organizations';

    protected $fillable = [
        'pipedrive_id', 'name', 'label', 'owner_id', 'address', 'address_formatted',
        'address_lat', 'address_lng', 'people_count', 'open_deals_count',
        'related_open_deals_count', 'closed_deals_count', 'related_closed_deals_count',
        'won_deals_count', 'related_won_deals_count', 'lost_deals_count',
        'related_lost_deals_count', 'activities_count', 'done_activities_count',
        'undone_activities_count', 'files_count', 'notes_count', 'followers_count',
        'email_messages_count', 'visible_to', 'active_flag', 'category_id',
        'next_activity_date', 'last_activity_date', 'last_incoming_mail_time',
        'last_outgoing_mail_time', 'picture_id', 'country_code', 'timezone',
        'pipedrive_add_time', 'pipedrive_update_time',
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
}
