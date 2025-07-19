<?php

namespace Skeylup\LaravelPipedrive\Models;

use Illuminate\Database\Eloquent\Builder;
use Skeylup\LaravelPipedrive\Data\PipedriveGoalData;

class PipedriveGoal extends BasePipedriveModel
{
    protected $table = 'pipedrive_goals';

        protected $fillable = [
        'pipedrive_id',
        'title',
        'type',
        'expected_outcome',
        'owner_id',
        'pipeline_id',
        'active_flag',
        'pipedrive_data',
        'pipedrive_add_time',
        'pipedrive_update_time',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'active' => 'boolean',
            'expected_outcome' => 'decimal:2',
            'outcome' => 'decimal:2',
            'progress' => 'decimal:2',
            'owner_id' => 'integer',
            'pipeline_id' => 'integer',
            'stage_id' => 'integer',
            'activity_type_id' => 'integer',
            'duration_start' => 'date',
            'duration_end' => 'date',
        ]);
    }

    /**
     * Goal types constants
     */
    public const TYPE_DEALS_WON = 'deals_won';
    public const TYPE_DEALS_PROGRESSED = 'deals_progressed';
    public const TYPE_ACTIVITIES_COMPLETED = 'activities_completed';
    public const TYPE_ACTIVITIES_ADDED = 'activities_added';
    public const TYPE_REVENUE_FORECAST = 'revenue_forecast';

    /**
     * Assignee types constants
     */
    public const ASSIGNEE_PERSON = 'person';
    public const ASSIGNEE_TEAM = 'team';
    public const ASSIGNEE_COMPANY = 'company';

    /**
     * Interval constants
     */
    public const INTERVAL_WEEKLY = 'weekly';
    public const INTERVAL_MONTHLY = 'monthly';
    public const INTERVAL_QUARTERLY = 'quarterly';
    public const INTERVAL_YEARLY = 'yearly';

    public static function getPipedriveEntityName(): string
    {
        return 'goals';
    }

    protected static function getDtoClass(): string
    {
        return PipedriveGoalData::class;
    }

    // Scopes
    public function scopeForOwner(Builder $query, int $ownerId): Builder
    {
        return $query->where('owner_id', $ownerId);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeByInterval(Builder $query, string $interval): Builder
    {
        return $query->where('interval', $interval);
    }

    public function scopeForPipeline(Builder $query, int $pipelineId): Builder
    {
        return $query->where('pipeline_id', $pipelineId);
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('duration_start', '<=', now())
                    ->where('duration_end', '>=', now());
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('progress', '>=', 100);
    }

    // Helper methods
    public function isInProgress(): bool
    {
        $now = now();
        return $this->duration_start <= $now && $this->duration_end >= $now;
    }

    public function isCompleted(): bool
    {
        return $this->progress >= 100;
    }

    public function getProgressPercentage(): string
    {
        return number_format($this->progress ?? 0, 1) . '%';
    }

    public function getCompletionRate(): float
    {
        if (!$this->expected_outcome || $this->expected_outcome == 0) {
            return 0;
        }

        return ($this->outcome / $this->expected_outcome) * 100;
    }

    public function getFormattedExpectedOutcome(): string
    {
        if ($this->currency) {
            return $this->currency . ' ' . number_format($this->expected_outcome ?? 0, 2);
        }

        return number_format($this->expected_outcome ?? 0, 2);
    }

    public function getFormattedOutcome(): string
    {
        if ($this->currency) {
            return $this->currency . ' ' . number_format($this->outcome ?? 0, 2);
        }

        return number_format($this->outcome ?? 0, 2);
    }

    // Relations
    public function owner()
    {
        return $this->belongsTo(PipedriveUser::class, 'owner_id', 'pipedrive_id');
    }

    public function pipeline()
    {
        return $this->belongsTo(PipedrivePipeline::class, 'pipeline_id', 'pipedrive_id');
    }
}