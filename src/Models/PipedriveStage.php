<?php

namespace Keggermont\LaravelPipedrive\Models;

use Illuminate\Database\Eloquent\Builder;
use Keggermont\LaravelPipedrive\Data\PipedriveStageData;

class PipedriveStage extends BasePipedriveModel
{
    protected $table = 'pipedrive_stages';

        protected $fillable = [
        'pipedrive_id',
        'name',
        'order_nr',
        'deal_probability',
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
            'rotten_flag' => 'boolean',
            'order_nr' => 'integer',
            'deal_probability' => 'integer',
            'rotten_days' => 'integer',
            'pipeline_id' => 'integer',
        ]);
    }

    public static function getPipedriveEntityName(): string
    {
        return 'stages';
    }

    protected static function getDtoClass(): string
    {
        return PipedriveStageData::class;
    }

    // Scopes
    public function scopeForPipeline(Builder $query, int $pipelineId): Builder
    {
        return $query->where('pipeline_id', $pipelineId);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order_nr');
    }

    public function scopeRotten(Builder $query): Builder
    {
        return $query->where('rotten_flag', true);
    }

    public function scopeWithProbability(Builder $query): Builder
    {
        return $query->whereNotNull('deal_probability');
    }

    // Helper methods
    public function isRotten(): bool
    {
        return $this->rotten_flag;
    }

    public function hasProbability(): bool
    {
        return $this->deal_probability !== null;
    }

    public function getProbabilityPercentage(): ?string
    {
        return $this->deal_probability ? $this->deal_probability . '%' : null;
    }

    public function getRottenDaysText(): ?string
    {
        if (!$this->rotten_days) {
            return null;
        }

        return $this->rotten_days . ' day' . ($this->rotten_days > 1 ? 's' : '');
    }

    // Relations
    public function pipeline()
    {
        return $this->belongsTo(PipedrivePipeline::class, 'pipeline_id', 'pipedrive_id');
    }

    public function deals()
    {
        return $this->hasMany(PipedriveDeal::class, 'stage_id', 'pipedrive_id');
    }
}