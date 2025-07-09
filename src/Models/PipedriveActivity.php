<?php

namespace Keggermont\LaravelPipedrive\Models;

use Illuminate\Database\Eloquent\Builder;
use Keggermont\LaravelPipedrive\Data\PipedriveActivityData;

class PipedriveActivity extends BasePipedriveModel
{
    protected $table = 'pipedrive_activities';

        protected $fillable = [
        'pipedrive_id',
        'subject',
        'type',
        'done',
        'due_date',
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
            'done' => 'boolean',
            'busy_flag' => 'boolean',
            'due_date' => 'date',
            'due_time' => 'datetime',
            'marked_as_done_time' => 'datetime',
            'last_notification_time' => 'datetime',
            'attendees' => 'array',
            'participants' => 'array',
            'location_lat' => 'decimal:8',
            'location_lng' => 'decimal:8',
        ]);
    }

    /**
     * Activity types constants
     */
    public const TYPE_CALL = 'call';
    public const TYPE_MEETING = 'meeting';
    public const TYPE_TASK = 'task';
    public const TYPE_DEADLINE = 'deadline';
    public const TYPE_EMAIL = 'email';
    public const TYPE_LUNCH = 'lunch';

    public static function getPipedriveEntityName(): string
    {
        return 'activities';
    }

    protected static function getDtoClass(): string
    {
        return PipedriveActivityData::class;
    }



    // Scopes
    public function scopeDone(Builder $query): Builder
    {
        return $query->where('done', true);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('done', false);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

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

    public function scopeDueToday(Builder $query): Builder
    {
        return $query->whereDate('due_date', today());
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('due_date', '<', today())
                    ->where('done', false);
    }

    // Helper methods
    public function isDone(): bool
    {
        return $this->done;
    }

    public function isPending(): bool
    {
        return !$this->done;
    }

    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isPast() && !$this->done;
    }

    public function isDueToday(): bool
    {
        return $this->due_date && $this->due_date->isToday();
    }

    public function hasLocation(): bool
    {
        return !empty($this->location) || ($this->location_lat && $this->location_lng);
    }

    public function getFormattedDuration(): ?string
    {
        if (!$this->duration) {
            return null;
        }

        $hours = intval($this->duration / 60);
        $minutes = $this->duration % 60;

        if ($hours > 0) {
            return $hours . 'h ' . $minutes . 'm';
        }

        return $minutes . 'm';
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
