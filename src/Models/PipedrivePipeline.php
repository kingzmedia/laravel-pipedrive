<?php

namespace Skeylup\LaravelPipedrive\Models;

use Illuminate\Database\Eloquent\Builder;
use Skeylup\LaravelPipedrive\Data\PipedrivePipelineData;

class PipedrivePipeline extends BasePipedriveModel
{
    protected $table = 'pipedrive_pipelines';

    protected $fillable = [
        'pipedrive_id',
        'name',
        'order_nr',
        'active_flag',
        'pipedrive_data',
        'pipedrive_add_time',
        'pipedrive_update_time',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'active' => 'boolean',
            'deal_probability' => 'boolean',
            'selected' => 'boolean',
            'order_nr' => 'integer',
        ]);
    }

    public static function getPipedriveEntityName(): string
    {
        return 'pipelines';
    }

    protected static function getDtoClass(): string
    {
        return PipedrivePipelineData::class;
    }

    // Scopes
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order_nr');
    }

    public function scopeWithDealProbability(Builder $query): Builder
    {
        return $query->where('deal_probability', true);
    }

    public function scopeSelected(Builder $query): Builder
    {
        return $query->where('selected', true);
    }

    // Helper methods
    public function hasDealProbability(): ?bool
    {
        return $this->deal_probability;
    }

    public function isSelected(): bool
    {
        return $this->selected ?? false;
    }

    public function getUrlSlug(): string
    {
        return $this->url_title ?? str_slug($this->name);
    }

    // Relations
    public function stages()
    {
        return $this->hasMany(PipedriveStage::class, 'pipeline_id', 'pipedrive_id');
    }

    public function goals()
    {
        return $this->hasMany(PipedriveGoal::class, 'pipeline_id', 'pipedrive_id');
    }
}
