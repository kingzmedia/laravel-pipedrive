<?php

namespace Skeylup\LaravelPipedrive\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Database\Eloquent\Model;

class PipedriveEntityMerged
{
    use Dispatchable, SerializesModels;

    public string $entityType;
    public int $mergedId;
    public int $survivingId;
    public ?Model $survivingEntity;
    public array $originalData;
    public string $source;
    public ?array $metadata;
    public int $migratedRelationsCount;

    /**
     * Create a new event instance.
     */
    public function __construct(
        string $entityType,
        int $mergedId,
        int $survivingId,
        ?Model $survivingEntity = null,
        array $originalData = [],
        string $source = 'unknown',
        ?array $metadata = null,
        int $migratedRelationsCount = 0
    ) {
        $this->entityType = $entityType;
        $this->mergedId = $mergedId;
        $this->survivingId = $survivingId;
        $this->survivingEntity = $survivingEntity;
        $this->originalData = $originalData;
        $this->source = $source;
        $this->metadata = $metadata;
        $this->migratedRelationsCount = $migratedRelationsCount;
    }

    /**
     * Get the Pipedrive ID of the merged (deleted) entity
     */
    public function getMergedId(): int
    {
        return $this->mergedId;
    }

    /**
     * Get the Pipedrive ID of the surviving entity
     */
    public function getSurvivingId(): int
    {
        return $this->survivingId;
    }

    /**
     * Get the surviving entity model (if available)
     */
    public function getSurvivingEntity(): ?Model
    {
        return $this->survivingEntity;
    }

    /**
     * Check if this entity was merged from a webhook
     */
    public function isFromWebhook(): bool
    {
        return $this->source === 'webhook';
    }

    /**
     * Check if this entity was merged from a command/sync
     */
    public function isFromSync(): bool
    {
        return $this->source === 'sync' || $this->source === 'command';
    }

    /**
     * Check if this entity was merged from API
     */
    public function isFromApi(): bool
    {
        return $this->source === 'api';
    }

    /**
     * Get metadata value
     */
    public function getMetadata(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Get the entity name (e.g., 'deals', 'persons')
     */
    public function getEntityName(): string
    {
        return $this->entityType;
    }

    /**
     * Get a summary of the merged entity
     */
    public function getSummary(): array
    {
        return [
            'event' => 'merged',
            'entity_type' => $this->entityType,
            'merged_id' => $this->mergedId,
            'surviving_id' => $this->survivingId,
            'surviving_entity_available' => $this->survivingEntity !== null,
            'source' => $this->source,
            'migrated_relations_count' => $this->migratedRelationsCount,
            'has_metadata' => !empty($this->metadata),
        ];
    }

    /**
     * Check if this is a specific entity type
     */
    public function isEntityType(string $type): bool
    {
        return $this->entityType === $type;
    }

    /**
     * Check if this is a deal
     */
    public function isDeal(): bool
    {
        return $this->isEntityType('deals');
    }

    /**
     * Check if this is a person
     */
    public function isPerson(): bool
    {
        return $this->isEntityType('persons');
    }

    /**
     * Check if this is an organization
     */
    public function isOrganization(): bool
    {
        return $this->isEntityType('organizations');
    }

    /**
     * Check if this is an activity
     */
    public function isActivity(): bool
    {
        return $this->isEntityType('activities');
    }

    /**
     * Check if this is a product
     */
    public function isProduct(): bool
    {
        return $this->isEntityType('products');
    }
}
