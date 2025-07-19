<?php

namespace Skeylup\LaravelPipedrive\Models;

use Illuminate\Database\Eloquent\Builder;
use Skeylup\LaravelPipedrive\Data\PipedriveNoteData;

class PipedriveNote extends BasePipedriveModel
{
    protected $table = 'pipedrive_notes';

        protected $fillable = [
        'pipedrive_id',
        'content',
        'person_id',
        'org_id',
        'deal_id',
        'user_id',
        'active_flag',
        'pipedrive_data',
        'pipedrive_add_time',
        'pipedrive_update_time',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'pinned_to_deal_flag' => 'boolean',
            'pinned_to_person_flag' => 'boolean',
            'pinned_to_organization_flag' => 'boolean',
            'pinned_to_lead_flag' => 'boolean',
        ]);
    }

    public static function getPipedriveEntityName(): string
    {
        return 'notes';
    }

    protected static function getDtoClass(): string
    {
        return PipedriveNoteData::class;
    }

    // Scopes
    public function scopeForDeal(Builder $query, int $dealId): Builder
    {
        return $query->where('deal_id', $dealId);
    }

    public function scopeForPerson(Builder $query, int $personId): Builder
    {
        return $query->where('person_id', $personId);
    }

    public function scopeForOrganization(Builder $query, int $orgId): Builder
    {
        return $query->where('org_id', $orgId);
    }

    public function scopeForLead(Builder $query, int $leadId): Builder
    {
        return $query->where('lead_id', $leadId);
    }

    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopePinnedToDeal(Builder $query): Builder
    {
        return $query->where('pinned_to_deal_flag', true);
    }

    public function scopePinnedToPerson(Builder $query): Builder
    {
        return $query->where('pinned_to_person_flag', true);
    }

    public function scopePinnedToOrganization(Builder $query): Builder
    {
        return $query->where('pinned_to_organization_flag', true);
    }

    public function scopePinnedToLead(Builder $query): Builder
    {
        return $query->where('pinned_to_lead_flag', true);
    }

    // Helper methods
    public function isPinnedToDeal(): bool
    {
        return $this->pinned_to_deal_flag;
    }

    public function isPinnedToPerson(): bool
    {
        return $this->pinned_to_person_flag;
    }

    public function isPinnedToOrganization(): bool
    {
        return $this->pinned_to_organization_flag;
    }

    public function isPinnedToLead(): bool
    {
        return $this->pinned_to_lead_flag;
    }

    public function getShortContent(int $length = 100): string
    {
        if (!$this->content) {
            return '';
        }

        return strlen($this->content) > $length 
            ? substr($this->content, 0, $length) . '...'
            : $this->content;
    }

    // Relations
    public function user()
    {
        return $this->belongsTo(PipedriveUser::class, 'user_id', 'pipedrive_id');
    }

    public function person()
    {
        return $this->belongsTo(PipedrivePerson::class, 'person_id', 'pipedrive_id');
    }

    public function organization()
    {
        return $this->belongsTo(PipedriveOrganization::class, 'org_id', 'pipedrive_id');
    }

    public function deal()
    {
        return $this->belongsTo(PipedriveDeal::class, 'deal_id', 'pipedrive_id');
    }
}