<?php

namespace Keggermont\LaravelPipedrive\Models;

use Illuminate\Database\Eloquent\Builder;
use Keggermont\LaravelPipedrive\Data\PipedrivePersonData;

class PipedrivePerson extends BasePipedriveModel
{
    protected $table = 'pipedrive_persons';

    protected $fillable = [
        'pipedrive_id', 'name', 'first_name', 'last_name', 'phone', 'email',
        'owner_id', 'org_id', 'open_deals_count', 'related_open_deals_count',
        'closed_deals_count', 'related_closed_deals_count', 'won_deals_count',
        'related_won_deals_count', 'lost_deals_count', 'related_lost_deals_count',
        'activities_count', 'done_activities_count', 'undone_activities_count',
        'files_count', 'notes_count', 'followers_count', 'email_messages_count',
        'visible_to', 'active_flag', 'label', 'label_ids', 'next_activity_date',
        'last_activity_date', 'last_incoming_mail_time', 'last_outgoing_mail_time',
        'picture_id', 'im', 'job_title', 'department', 'language', 'marketing_status',
        'pipedrive_add_time', 'pipedrive_update_time',
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
        if (!$this->email || !is_array($this->email)) {
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
        if (!$this->phone || !is_array($this->phone)) {
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
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? '')) ?: $this->name;
    }
}
