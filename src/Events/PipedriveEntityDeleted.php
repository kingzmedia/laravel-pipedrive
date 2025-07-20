<?php

namespace Skeylup\LaravelPipedrive\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PipedriveEntityDeleted
{
    use Dispatchable, SerializesModels;

    public string $entityType;

    public int $pipedriveId;

    public ?int $localId;

    public array $entityData;

    public string $source;

    public ?array $metadata;

    /**
     * Create a new event instance.
     */
    public function __construct(
        string $entityType,
        int $pipedriveId,
        ?int $localId = null,
        array $entityData = [],
        string $source = 'unknown',
        ?array $metadata = null
    ) {
        $this->entityType = $entityType;
        $this->pipedriveId = $pipedriveId;
        $this->localId = $localId;
        $this->entityData = $entityData;
        $this->source = $source;
        $this->metadata = $metadata;
    }

    /**
     * Get the Pipedrive ID of the deleted entity
     */
    public function getPipedriveId(): int
    {
        return $this->pipedriveId;
    }

    /**
     * Get the local model ID (if it existed)
     */
    public function getLocalId(): ?int
    {
        return $this->localId;
    }

    /**
     * Check if this entity was deleted from a webhook
     */
    public function isFromWebhook(): bool
    {
        return $this->source === 'webhook';
    }

    /**
     * Check if this entity was deleted from a command/sync
     */
    public function isFromSync(): bool
    {
        return $this->source === 'sync' || $this->source === 'command';
    }

    /**
     * Check if this entity was deleted from API
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
     * Get a value from the entity data
     */
    public function getEntityData(string $key, $default = null)
    {
        return $this->entityData[$key] ?? $default;
    }

    /**
     * Get the entity title/name if available
     */
    public function getEntityTitle(): ?string
    {
        return $this->getEntityData('title')
            ?? $this->getEntityData('name')
            ?? $this->getEntityData('subject')
            ?? null;
    }

    /**
     * Check if the local record existed
     */
    public function hadLocalRecord(): bool
    {
        return $this->localId !== null;
    }

    /**
     * Get a summary of the deleted entity
     */
    public function getSummary(): array
    {
        return [
            'event' => 'deleted',
            'entity_type' => $this->entityType,
            'pipedrive_id' => $this->pipedriveId,
            'local_id' => $this->localId,
            'source' => $this->source,
            'had_local_record' => $this->hadLocalRecord(),
            'entity_title' => $this->getEntityTitle(),
            'deleted_at' => now()->toISOString(),
            'has_metadata' => ! empty($this->metadata),
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

    /**
     * Get the deleted entity value (for deals)
     */
    public function getDeletedValue(): ?float
    {
        $value = $this->getEntityData('value');

        return $value !== null ? (float) $value : null;
    }

    /**
     * Get the deleted entity status (for deals)
     */
    public function getDeletedStatus(): ?string
    {
        return $this->getEntityData('status');
    }

    /**
     * Get the deleted entity owner ID
     */
    public function getDeletedOwnerId(): ?int
    {
        return $this->getEntityData('user_id') ?? $this->getEntityData('owner_id');
    }
}
