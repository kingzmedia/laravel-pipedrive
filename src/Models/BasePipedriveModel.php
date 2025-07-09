<?php

namespace Keggermont\LaravelPipedrive\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

abstract class BasePipedriveModel extends Model
{
    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'pipedrive_add_time' => 'datetime',
            'pipedrive_update_time' => 'datetime',
            'active_flag' => 'boolean',
            'active' => 'boolean',
        ];
    }

    /**
     * Scope to get only active records
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function ($q) {
            if ($this->getTable() === 'pipedrive_pipelines' || 
                $this->getTable() === 'pipedrive_stages' || 
                $this->getTable() === 'pipedrive_goals') {
                $q->where('active', true);
            } else {
                $q->where('active_flag', true);
            }
        });
    }

    /**
     * Scope to get records by Pipedrive ID
     */
    public function scopeByPipedriveId(Builder $query, int $pipedriveId): Builder
    {
        return $query->where('pipedrive_id', $pipedriveId);
    }

    /**
     * Find by Pipedrive ID
     */
    public static function findByPipedriveId(int $pipedriveId): ?static
    {
        return static::where('pipedrive_id', $pipedriveId)->first();
    }

    /**
     * Create or update from Pipedrive API data using DTO
     */
    public static function createOrUpdateFromPipedriveData(array $data): static
    {
        // Get the DTO class for this model
        $dtoClass = static::getDtoClass();

        // Create DTO from Pipedrive API data
        $dto = $dtoClass::fromPipedriveApi($data);

        // Convert DTO to database format
        $preparedData = $dto->toDatabase();

        return static::updateOrCreate(
            ['pipedrive_id' => $data['id']],
            $preparedData
        );
    }

    /**
     * Get the DTO class for this model - to be implemented by each model
     */
    abstract protected static function getDtoClass(): string;

    /**
     * Get the Pipedrive entity name for API calls
     */
    abstract public static function getPipedriveEntityName(): string;

    /**
     * Get the Pipedrive API endpoint
     */
    public static function getPipedriveEndpoint(): string
    {
        return static::getPipedriveEntityName();
    }

    /**
     * Check if this record is active
     */
    public function isActive(): bool
    {
        if (property_exists($this, 'active')) {
            return $this->active;
        }
        
        return $this->active_flag ?? true;
    }

    /**
     * Get formatted Pipedrive add time
     */
    public function getFormattedAddTime(): ?string
    {
        return $this->pipedrive_add_time?->format('Y-m-d H:i:s');
    }

    /**
     * Get formatted Pipedrive update time
     */
    public function getFormattedUpdateTime(): ?string
    {
        return $this->pipedrive_update_time?->format('Y-m-d H:i:s');
    }

    /**
     * Get the age of this record in days
     */
    public function getAgeInDays(): ?int
    {
        if (!$this->pipedrive_add_time) {
            return null;
        }

        return $this->pipedrive_add_time->diffInDays(now());
    }

    /**
     * Get the last update age in days
     */
    public function getLastUpdateAgeInDays(): ?int
    {
        if (!$this->pipedrive_update_time) {
            return null;
        }

        return $this->pipedrive_update_time->diffInDays(now());
    }
}
