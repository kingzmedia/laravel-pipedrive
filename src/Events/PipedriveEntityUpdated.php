<?php

namespace Skeylup\LaravelPipedrive\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PipedriveEntityUpdated
{
    use Dispatchable, SerializesModels;

    public string $entityType;

    public Model $entity;

    public array $originalData;

    public array $changes;

    public string $source;

    public ?array $metadata;

    /**
     * Create a new event instance.
     */
    public function __construct(
        string $entityType,
        Model $entity,
        array $originalData = [],
        array $changes = [],
        string $source = 'unknown',
        ?array $metadata = null
    ) {
        $this->entityType = $entityType;
        $this->entity = $entity;
        $this->originalData = $originalData;
        $this->changes = $changes;
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
     * Check if this entity was updated from a webhook
     */
    public function isFromWebhook(): bool
    {
        return $this->source === 'webhook';
    }

    /**
     * Check if this entity was updated from a command/sync
     */
    public function isFromSync(): bool
    {
        return $this->source === 'sync' || $this->source === 'command';
    }

    /**
     * Check if this entity was updated from API
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
     * Check if a specific field was changed
     */
    public function hasChanged(string $field): bool
    {
        return array_key_exists($field, $this->changes);
    }

    /**
     * Get the old value of a field
     */
    public function getOldValue(string $field)
    {
        return $this->changes[$field]['old'] ?? null;
    }

    /**
     * Get the new value of a field
     */
    public function getNewValue(string $field)
    {
        return $this->changes[$field]['new'] ?? null;
    }

    /**
     * Get all changed fields
     */
    public function getChangedFields(): array
    {
        return array_keys($this->changes);
    }

    /**
     * Check if any of the specified fields were changed
     */
    public function hasChangedAny(array $fields): bool
    {
        return ! empty(array_intersect($fields, $this->getChangedFields()));
    }

    /**
     * Check if all of the specified fields were changed
     */
    public function hasChangedAll(array $fields): bool
    {
        return empty(array_diff($fields, $this->getChangedFields()));
    }

    /**
     * Get a summary of the updated entity
     */
    public function getSummary(): array
    {
        return [
            'event' => 'updated',
            'entity_type' => $this->entityType,
            'pipedrive_id' => $this->getPipedriveId(),
            'local_id' => $this->getLocalId(),
            'model_class' => $this->getModelClass(),
            'source' => $this->source,
            'changed_fields' => $this->getChangedFields(),
            'changes_count' => count($this->changes),
            'updated_at' => $this->entity->updated_at?->toISOString(),
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
     * Check if the status/stage changed (for deals)
     */
    public function statusChanged(): bool
    {
        return $this->hasChanged('status') || $this->hasChanged('stage_id');
    }

    /**
     * Check if the value changed (for deals)
     */
    public function valueChanged(): bool
    {
        return $this->hasChanged('value');
    }

    /**
     * Check if the owner changed
     */
    public function ownerChanged(): bool
    {
        return $this->hasChanged('user_id') || $this->hasChanged('owner_id');
    }
}
