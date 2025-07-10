<?php

namespace Keggermont\LaravelPipedrive\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class PipedriveEntityLink extends Model
{
    protected $table = 'pipedrive_entity_links';

    protected $fillable = [
        'linkable_type',
        'linkable_id',
        'pipedrive_entity_type',
        'pipedrive_entity_id',
        'pipedrive_model_type',
        'pipedrive_model_id',
        'metadata',
        'is_primary',
        'is_active',
        'last_synced_at',
        'sync_status',
        'sync_error',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * Get the owning linkable model (your Laravel model)
     */
    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the associated Pipedrive model (if exists locally)
     */
    public function pipedriveModel(): MorphTo
    {
        return $this->morphTo('pipedrive_model', 'pipedrive_model_type', 'pipedrive_model_id');
    }

    /**
     * Scopes
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    public function scopeForEntity(Builder $query, string $entityType, int $entityId): Builder
    {
        return $query->where('pipedrive_entity_type', $entityType)
                    ->where('pipedrive_entity_id', $entityId);
    }

    public function scopeForModel(Builder $query, Model $model): Builder
    {
        return $query->where('linkable_type', get_class($model))
                    ->where('linkable_id', $model->getKey());
    }

    public function scopeSynced(Builder $query): Builder
    {
        return $query->where('sync_status', 'synced');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('sync_status', 'pending');
    }

    public function scopeWithErrors(Builder $query): Builder
    {
        return $query->where('sync_status', 'error');
    }

    /**
     * Helper methods
     */
    public function isPrimary(): bool
    {
        return $this->is_primary;
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isSynced(): bool
    {
        return $this->sync_status === 'synced';
    }

    public function hasSyncError(): bool
    {
        return $this->sync_status === 'error';
    }

    public function markAsSynced(): self
    {
        $this->update([
            'sync_status' => 'synced',
            'last_synced_at' => now(),
            'sync_error' => null,
        ]);

        return $this;
    }

    public function markAsError(string $error): self
    {
        $this->update([
            'sync_status' => 'error',
            'sync_error' => $error,
        ]);

        return $this;
    }

    public function markAsPending(): self
    {
        $this->update([
            'sync_status' => 'pending',
            'sync_error' => null,
        ]);

        return $this;
    }

    /**
     * Get the Pipedrive model class for this entity type
     */
    public function getPipedriveModelClass(): ?string
    {
        $entityMap = [
            'deals' => PipedriveDeal::class,
            'persons' => PipedrivePerson::class,
            'organizations' => PipedriveOrganization::class,
            'activities' => PipedriveActivity::class,
            'products' => PipedriveProduct::class,
            'files' => PipedriveFile::class,
            'notes' => PipedriveNote::class,
            'users' => PipedriveUser::class,
            'pipelines' => PipedrivePipeline::class,
            'stages' => PipedriveStage::class,
            'goals' => PipedriveGoal::class,
        ];

        return $entityMap[$this->pipedrive_entity_type] ?? null;
    }

    /**
     * Get the local Pipedrive model instance
     */
    public function getLocalPipedriveModel(): ?Model
    {
        $modelClass = $this->getPipedriveModelClass();
        
        if (!$modelClass) {
            return null;
        }

        return $modelClass::where('pipedrive_id', $this->pipedrive_entity_id)->first();
    }

    /**
     * Sync the local Pipedrive model reference
     */
    public function syncLocalPipedriveModel(): bool
    {
        $localModel = $this->getLocalPipedriveModel();
        
        if ($localModel) {
            $this->update([
                'pipedrive_model_type' => get_class($localModel),
                'pipedrive_model_id' => $localModel->getKey(),
            ]);
            
            return true;
        }

        return false;
    }

    /**
     * Get metadata value
     */
    public function getMetadata(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Set metadata value
     */
    public function setMetadata(string $key, $value): self
    {
        $metadata = $this->metadata ?? [];
        $metadata[$key] = $value;
        $this->metadata = $metadata;
        $this->save();

        return $this;
    }

    /**
     * Get the age of this link in days
     */
    public function getAgeInDays(): int
    {
        return $this->created_at->diffInDays(now());
    }

    /**
     * Get the last sync age in days
     */
    public function getLastSyncAgeInDays(): ?int
    {
        if (!$this->last_synced_at) {
            return null;
        }

        return $this->last_synced_at->diffInDays(now());
    }
}
