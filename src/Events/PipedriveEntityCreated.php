<?php

namespace Keggermont\LaravelPipedrive\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Database\Eloquent\Model;

class PipedriveEntityCreated
{
    use Dispatchable, SerializesModels;

    public string $entityType;
    public Model $entity;
    public array $originalData;
    public string $source;
    public ?array $metadata;

    /**
     * Create a new event instance.
     */
    public function __construct(
        string $entityType,
        Model $entity,
        array $originalData = [],
        string $source = 'unknown',
        ?array $metadata = null
    ) {
        $this->entityType = $entityType;
        $this->entity = $entity;
        $this->originalData = $originalData;
        $this->source = $source;
        $this->metadata = $metadata;
    }

    /**
     * Get the Pipedrive ID of the entity
     */
    public function getPipedriveId(): ?int
    {
        return $this->entity->pipedrive_id ?? null;
    }

    /**
     * Get the local model ID
     */
    public function getLocalId(): mixed
    {
        return $this->entity->getKey();
    }

    /**
     * Check if this entity was created from a webhook
     */
    public function isFromWebhook(): bool
    {
        return $this->source === 'webhook';
    }

    /**
     * Check if this entity was created from a command/sync
     */
    public function isFromSync(): bool
    {
        return $this->source === 'sync' || $this->source === 'command';
    }

    /**
     * Check if this entity was created from API
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
     * Get the model class name
     */
    public function getModelClass(): string
    {
        return get_class($this->entity);
    }

    /**
     * Get a summary of the created entity
     */
    public function getSummary(): array
    {
        return [
            'event' => 'created',
            'entity_type' => $this->entityType,
            'pipedrive_id' => $this->getPipedriveId(),
            'local_id' => $this->getLocalId(),
            'model_class' => $this->getModelClass(),
            'source' => $this->source,
            'created_at' => $this->entity->created_at?->toISOString(),
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
